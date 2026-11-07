<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Buy_Now_Button_Analytics_Store' ) ) {
	/**
	 * Analytics storage: custom tables, daily rollup and pruning.
	 *
	 * Orders are read through wc_get_orders() only (HPOS and legacy storage).
	 * Direct queries below touch this plugin's own tables, for which no WordPress API exists.
	 *
	 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	 */
	class Woo_Buy_Now_Button_Analytics_Store {

		const DB_VERSION = '1';

		// Event types.
		const EVENT_ATTEMPT        = 1;
		const EVENT_CHECKOUT_VIEW  = 2;
		const EVENT_POPUP_CLOSE    = 3;
		const EVENT_CLIENT_BLOCK   = 4;
		const EVENT_EXTERNAL_CLICK = 5;

		// Attempt results.
		const RESULT_OK                    = 0;
		const RESULT_OUT_OF_STOCK          = 1;
		const RESULT_NOT_PURCHASABLE       = 2;
		const RESULT_UNSUPPORTED_TYPE      = 3;
		const RESULT_VALIDATION            = 4;
		const RESULT_NO_QUANTITY           = 5;
		const RESULT_NEEDS_OPTIONS         = 6;
		const RESULT_UNAVAILABLE_VARIATION = 7;

		// Devices.
		const DEVICE_UNKNOWN = 0;
		const DEVICE_DESKTOP = 1;
		const DEVICE_MOBILE  = 2;

		/**
		 * Events table name.
		 */
		public static function events_table() {
			global $wpdb;

			return $wpdb->prefix . 'wbnb_events';
		}

		/**
		 * Daily rollup table name.
		 */
		public static function daily_table() {
			global $wpdb;

			return $wpdb->prefix . 'wbnb_stats_daily';
		}

		/**
		 * Create or update the tables when the schema version changed.
		 */
		public static function maybe_install() {
			if ( self::DB_VERSION === get_option( 'wbnb_db_version' ) ) {
				return;
			}

			self::install();
		}

		/**
		 * Create or update the tables with dbDelta.
		 */
		public static function install() {
			global $wpdb;

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $wpdb->get_charset_collate();
			$events          = self::events_table();
			$daily           = self::daily_table();

			dbDelta( "CREATE TABLE {$events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_gmt datetime NOT NULL,
  event tinyint(3) unsigned NOT NULL,
  result tinyint(3) unsigned NOT NULL DEFAULT 0,
  ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  qty int(10) unsigned NOT NULL DEFAULT 0,
  source tinyint(3) unsigned NOT NULL DEFAULT 0,
  mode tinyint(3) unsigned NOT NULL DEFAULT 0,
  device tinyint(3) unsigned NOT NULL DEFAULT 0,
  logged_in tinyint(1) unsigned NOT NULL DEFAULT 0,
  cart_lines_before smallint(5) unsigned NOT NULL DEFAULT 0,
  cart_value_before decimal(19,4) NOT NULL DEFAULT 0,
  cart_reset tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY created_event (created_gmt,event),
  KEY product_created (product_id,created_gmt),
  KEY ref_id (ref_id)
) {$charset_collate};" );

			dbDelta( "CREATE TABLE {$daily} (
  stat_date date NOT NULL,
  product_id bigint(20) unsigned NOT NULL,
  variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source tinyint(3) unsigned NOT NULL DEFAULT 0,
  mode tinyint(3) unsigned NOT NULL DEFAULT 0,
  device tinyint(3) unsigned NOT NULL DEFAULT 0,
  attempts int(10) unsigned NOT NULL DEFAULT 0,
  failed int(10) unsigned NOT NULL DEFAULT 0,
  checkouts int(10) unsigned NOT NULL DEFAULT 0,
  orders int(10) unsigned NOT NULL DEFAULT 0,
  orders_returning int(10) unsigned NOT NULL DEFAULT 0,
  items_qty int(10) unsigned NOT NULL DEFAULT 0,
  revenue decimal(19,4) NOT NULL DEFAULT 0,
  order_total decimal(19,4) NOT NULL DEFAULT 0,
  refunded decimal(19,4) NOT NULL DEFAULT 0,
  seconds_to_order bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (stat_date,product_id,variation_id,source,mode,device),
  KEY product_date (product_id,stat_date)
) {$charset_collate};" );

			// Store the default, so page views read it from autoloaded options instead of a query each time.
			add_option( 'wbnb_analytics_enabled', 'yes' );

			if ( ! get_option( 'wbnb_analytics_since' ) ) {
				add_option( 'wbnb_analytics_since', gmdate( 'Y-m-d H:i:s' ), '', false );
			}

			if ( ! get_option( 'wbnb_analytics_aggregated_through' ) ) {
				// Nothing to roll up before the install day.
				add_option( 'wbnb_analytics_aggregated_through', wp_date( 'Y-m-d', strtotime( '-1 day' ) ), '', false );
			}

			update_option( 'wbnb_db_version', self::DB_VERSION );
		}

		/**
		 * Insert one event row.
		 *
		 * @param array $data Column => value.
		 *
		 * @return int Row ID, 0 on failure.
		 */
		public static function insert_event( $data ) {
			global $wpdb;

			$row = wp_parse_args( $data, array(
				'created_gmt'       => gmdate( 'Y-m-d H:i:s' ),
				'event'             => self::EVENT_ATTEMPT,
				'result'            => self::RESULT_OK,
				'ref_id'            => 0,
				'product_id'        => 0,
				'variation_id'      => 0,
				'qty'               => 0,
				'source'            => 0,
				'mode'              => 0,
				'device'            => self::DEVICE_UNKNOWN,
				'logged_in'         => 0,
				'cart_lines_before' => 0,
				'cart_value_before' => 0,
				'cart_reset'        => 0,
			) );

			$row['cart_lines_before'] = min( 65535, absint( $row['cart_lines_before'] ) );

			$inserted = $wpdb->insert(
				self::events_table(),
				$row,
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d' )
			);

			return $inserted ? (int) $wpdb->insert_id : 0;
		}

		/**
		 * Get one event row.
		 *
		 * @param int $id Event ID.
		 *
		 * @return object|null
		 */
		public static function get_event( $id ) {
			global $wpdb;

			$table = self::events_table();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		}

		/**
		 * Rebuild the rollup for days from the last rolled-up day through today (catch-up capped).
		 */
		public static function rollup() {
			$today = wp_date( 'Y-m-d' );
			$from  = get_option( 'wbnb_analytics_aggregated_through', $today );

			// Always redo the last rolled-up day: late events and orders for it may have arrived.
			$day  = max( $from, wp_date( 'Y-m-d', strtotime( '-31 days' ) ) );
			$days = 0;

			while ( $day <= $today && $days < 32 ) {
				self::rebuild_day( $day );
				$day = gmdate( 'Y-m-d', strtotime( $day . ' +1 day' ) );
				$days ++;
			}

			// Yesterday is complete once rolled up after midnight; today never is.
			update_option( 'wbnb_analytics_aggregated_through', wp_date( 'Y-m-d', strtotime( '-1 day' ) ), false );
		}

		/**
		 * Rebuild one local day of the rollup from events and orders. Idempotent.
		 *
		 * @param string $date Local date, Y-m-d.
		 */
		public static function rebuild_day( $date ) {
			global $wpdb;

			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return;
			}

			$start = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
			$end   = $start->modify( '+1 day' );

			$rows   = array();
			$events = self::events_table();

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$event_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT product_id, variation_id, source, mode, device,
					SUM( event = %d ) AS attempts,
					SUM( event = %d AND result > 0 ) AS failed,
					SUM( event = %d ) AS checkouts
				FROM {$events}
				WHERE created_gmt >= %s AND created_gmt < %s AND event IN ( %d, %d )
				GROUP BY product_id, variation_id, source, mode, device",
				self::EVENT_ATTEMPT,
				self::EVENT_ATTEMPT,
				self::EVENT_CHECKOUT_VIEW,
				gmdate( 'Y-m-d H:i:s', $start->getTimestamp() ),
				gmdate( 'Y-m-d H:i:s', $end->getTimestamp() ),
				self::EVENT_ATTEMPT,
				self::EVENT_CHECKOUT_VIEW
			) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			foreach ( (array) $event_rows as $event_row ) {
				$row = &self::rollup_row( $rows, $event_row->product_id, $event_row->variation_id, $event_row->source, $event_row->mode, $event_row->device );

				$row['attempts']  += (int) $event_row->attempts;
				$row['failed']    += (int) $event_row->failed;
				$row['checkouts'] += (int) $event_row->checkouts;
				unset( $row );
			}

			$page = 1;

			do {
				$orders = wc_get_orders( array(
					'type'         => 'shop_order',
					'status'       => self::counted_statuses(),
					'date_created' => $start->getTimestamp() . '...' . ( $end->getTimestamp() - 1 ),
					'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_wbnb_buy_now',
							'value' => '1',
						),
					),
					'limit'        => 100,
					'paged'        => $page,
					'orderby'      => 'ID',
					'order'        => 'ASC',
				) );

				foreach ( $orders as $order ) {
					self::add_order_to_rollup( $rows, $order );
				}

				$page ++;
			} while ( count( $orders ) === 100 );

			$daily = self::daily_table();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$daily} WHERE stat_date = %s", $date ) );

			foreach ( $rows as $row ) {
				$row['stat_date'] = $date;
				$wpdb->insert( $daily, $row );
			}
		}

		/**
		 * Add an order's Buy Now items to the rollup rows.
		 *
		 * Order-level numbers (orders, returning, order total, time to order) go on the first
		 * Buy Now item only, so summing rows never counts an order twice.
		 *
		 * @param array    $rows  Rollup rows, by reference.
		 * @param WC_Order $order Order.
		 */
		protected static function add_order_to_rollup( &$rows, $order ) {
			$first  = true;
			$device = self::order_device( $order );

			foreach ( $order->get_items() as $item_id => $item ) {
				$meta = $item->get_meta( '_wbnb' );

				if ( ! is_array( $meta ) ) {
					continue;
				}

				$item_device = $device ? $device : ( isset( $meta['dev'] ) ? absint( $meta['dev'] ) : self::DEVICE_UNKNOWN );
				$row         = &self::rollup_row(
					$rows,
					$item->get_product_id(),
					$item->get_variation_id(),
					isset( $meta['src'] ) ? $meta['src'] : 0,
					isset( $meta['mode'] ) ? $meta['mode'] : 0,
					$item_device
				);

				// Line-item refunds, like WC Analytics. A fully refunded order may have been refunded
				// by amount only, without line items, so its items count as fully refunded.
				$refunded = $order->has_status( 'refunded' ) ? (float) $item->get_total() : (float) $order->get_total_refunded_for_item( $item_id );

				$row['items_qty'] += (int) $item->get_quantity();
				$row['revenue']   += (float) $item->get_total();
				$row['refunded']  += $refunded;

				if ( $first ) {
					$row['orders']      += 1;
					$row['order_total'] += (float) $order->get_total();

					if ( '1' === (string) $order->get_meta( '_wbnb_returning' ) ) {
						$row['orders_returning'] += 1;
					}

					if ( ! empty( $meta['t'] ) && $order->get_date_created() ) {
						$row['seconds_to_order'] += max( 0, $order->get_date_created()->getTimestamp() - absint( $meta['t'] ) );
					}

					$first = false;
				}

				unset( $row );
			}
		}

		/**
		 * Device from WooCommerce Order Attribution when present.
		 *
		 * @param WC_Order $order Order.
		 *
		 * @return int Device constant, 0 when unknown.
		 */
		protected static function order_device( $order ) {
			$device_type = $order->get_meta( '_wc_order_attribution_device_type' );

			if ( '' === $device_type ) {
				return self::DEVICE_UNKNOWN;
			}

			return 'Desktop' === $device_type ? self::DEVICE_DESKTOP : self::DEVICE_MOBILE;
		}

		/**
		 * Get (and create) the rollup row for a dimension set.
		 */
		protected static function &rollup_row( &$rows, $product_id, $variation_id, $source, $mode, $device ) {
			$key = implode( ':', array( (int) $product_id, (int) $variation_id, (int) $source, (int) $mode, (int) $device ) );

			if ( ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = array(
					'product_id'       => (int) $product_id,
					'variation_id'     => (int) $variation_id,
					'source'           => (int) $source,
					'mode'             => (int) $mode,
					'device'           => (int) $device,
					'attempts'         => 0,
					'failed'           => 0,
					'checkouts'        => 0,
					'orders'           => 0,
					'orders_returning' => 0,
					'items_qty'        => 0,
					'revenue'          => 0,
					'order_total'      => 0,
					'refunded'         => 0,
					'seconds_to_order' => 0,
				);
			}

			return $rows[ $key ];
		}

		/**
		 * Order statuses counted in the rollup: paid plus refunded (for refund rates).
		 */
		public static function counted_statuses() {
			$statuses   = wc_get_is_paid_statuses();
			$statuses[] = 'refunded';

			return apply_filters( 'woo_buy_now_analytics_counted_statuses', array_unique( $statuses ) );
		}

		/**
		 * Delete old events that are already rolled up, in batches.
		 *
		 * @return bool True when more rows may remain.
		 */
		public static function prune() {
			global $wpdb;

			$days   = max( 1, absint( apply_filters( 'woo_buy_now_analytics_event_retention_days', 365 ) ) );
			$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );

			// Never delete days that are not rolled up yet.
			$rolled_up = get_option( 'wbnb_analytics_aggregated_through' );
			if ( ! $rolled_up ) {
				return false;
			}

			$rolled_up_end = ( new DateTimeImmutable( $rolled_up . ' 00:00:00', wp_timezone() ) )->getTimestamp();
			$cutoff        = min( $cutoff, gmdate( 'Y-m-d H:i:s', $rolled_up_end ) );
			$table         = self::events_table();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_gmt < %s ORDER BY id LIMIT 5000", $cutoff ) );

			return 5000 === (int) $deleted;
		}

		/**
		 * Drop the tables and remove the options. Used on uninstall only.
		 */
		public static function drop() {
			global $wpdb;

			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::events_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from $wpdb->prefix.
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::daily_table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from $wpdb->prefix.

			foreach ( array( 'wbnb_db_version', 'wbnb_analytics_since', 'wbnb_analytics_aggregated_through', 'wbnb_analytics_jobs', 'wbnb_analytics_enabled', 'wbnb_remove_data_on_uninstall' ) as $option ) {
				delete_option( $option );
			}
		}
	}
}
