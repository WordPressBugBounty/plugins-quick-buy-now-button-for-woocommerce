<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Buy_Now_Button_Analytics' ) ) {
	/**
	 * Buy Now analytics collection.
	 *
	 * Records Buy Now attempts and checkout views in the store's own database, tags Buy Now
	 * order items, and keeps a daily rollup with Action Scheduler. Nothing is sent off-site
	 * and no personal data (IP, user agent, user, email, session id) is stored.
	 */
	class Woo_Buy_Now_Button_Analytics {
		protected static $_instance = null;

		/**
		 * Current Buy Now request, null when none.
		 *
		 * @var array|null
		 */
		protected $context = null;

		/**
		 * This request is a debounced repeat click; keep the first click's attribution.
		 *
		 * @var bool
		 */
		protected $debounced = false;

		/**
		 * Session key for the cart item key => attribution map.
		 */
		const SESSION_KEY = 'wbnb_attr';

		/**
		 * Action Scheduler group.
		 */
		const GROUP = 'wbnb';

		/**
		 * Bump to reschedule the recurring jobs.
		 */
		const JOBS_VERSION = '1';

		protected function __construct() {
			$this->includes();
			$this->hooks();

			do_action( 'woo_buy_now_button_analytics_loaded', $this );
		}

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		protected function includes() {
			require_once dirname( __FILE__ ) . '/class-woo_buy_now_button_analytics_store.php';
		}

		protected function hooks() {
			// Schema and jobs. Action Scheduler is ready by init 20.
			add_action( 'init', array( $this, 'maybe_install' ), 20 );
			add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );

			// Jobs.
			add_action( 'wbnb_analytics_rollup', array( 'Woo_Buy_Now_Button_Analytics_Store', 'rollup' ) );
			add_action( 'wbnb_analytics_rebuild_day', array( 'Woo_Buy_Now_Button_Analytics_Store', 'rebuild_day' ) );
			add_action( 'wbnb_analytics_prune', array( $this, 'run_prune' ) );
			add_action( 'wbnb_analytics_prune_batch', array( $this, 'run_prune' ) );
			add_action( 'wbnb_analytics_order_paid', array( $this, 'run_order_paid' ) );

			// Order lifecycle (admin, payment callbacks: tracking toggle doesn't apply, tagged orders keep their rollup right).
			add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );
			add_action( 'woocommerce_order_refunded', array( $this, 'order_refunded' ) );

			if ( ! self::is_enabled() ) {
				return;
			}

			// Mark a Buy Now request: AJAX before the handler, form/link before WC_Form_Handler (wp_loaded 20).
			add_action( 'wp_ajax_wbnb_add_to_cart', array( $this, 'start_ajax_context' ), 1 );
			add_action( 'wp_ajax_nopriv_wbnb_add_to_cart', array( $this, 'start_ajax_context' ), 1 );
			add_action( 'wp_loaded', array( $this, 'start_request_context' ), 19 );

			// Record what the request added.
			add_action( 'woocommerce_add_to_cart', array( $this, 'added_to_cart' ), 10, 4 );
			add_action( 'woocommerce_cart_emptied', array( $this, 'cart_emptied' ) );
			add_filter( 'woo_buy_now_ajax_response', array( $this, 'ajax_response' ), 10, 2 );
			add_action( 'shutdown', array( $this, 'flush' ), 5 ); // Before WC_Session_Handler::save_data (20).

			// Checkout reached.
			add_action( 'template_redirect', array( $this, 'checkout_view' ), 20 );

			// Order tagging: classic, popup (classic AJAX) and Store API all create line items here.
			add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'tag_order_item' ), 10, 3 );
			add_action( 'woocommerce_checkout_create_order', array( $this, 'tag_order' ) );
			add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'tag_order' ) );

			// Browser-only events.
			add_action( 'wc_ajax_wbnb_event', array( $this, 'beacon' ) );
		}

		/**
		 * Is collection switched on in settings.
		 */
		public static function is_enabled() {
			return 'yes' === get_option( 'wbnb_analytics_enabled', 'yes' );
		}

		/**
		 * Button sources: slug => stored code. Pro adds its own.
		 */
		public static function sources() {
			return apply_filters( 'woo_buy_now_analytics_sources', array(
				'single'           => 1,
				'archive'          => 2,
				'shortcode-single' => 3,
				'shortcode-archive' => 4,
				'sticky'           => 5,
				'quickview'        => 6,
				'other'            => 9,
			) );
		}

		/**
		 * Redirect modes: setting value => stored code.
		 */
		public static function modes() {
			return array(
				'checkout'       => 1,
				'cart'           => 2,
				'popup-checkout' => 3,
				'custom'         => 4,
			);
		}

		/**
		 * Sanitize a source slug against the whitelist.
		 *
		 * @param mixed  $source  Raw value.
		 * @param string $default Slug used when missing or unknown.
		 *
		 * @return string
		 */
		public static function sanitize_source( $source, $default ) {
			$source = is_string( $source ) ? sanitize_key( $source ) : '';

			return array_key_exists( $source, self::sources() ) ? $source : $default;
		}

		/**
		 * Create or update the tables and schedule the jobs once per version.
		 */
		public function maybe_install() {
			Woo_Buy_Now_Button_Analytics_Store::maybe_install();

			if ( self::JOBS_VERSION === get_option( 'wbnb_analytics_jobs' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
				return;
			}

			if ( ! as_has_scheduled_action( 'wbnb_analytics_rollup', array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 'wbnb_analytics_rollup', array(), self::GROUP );
			}

			if ( ! as_has_scheduled_action( 'wbnb_analytics_prune', array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'wbnb_analytics_prune', array(), self::GROUP );
			}

			update_option( 'wbnb_analytics_jobs', self::JOBS_VERSION );
		}

		/**
		 * Should this request be tracked: not a bot, prefetch or store staff.
		 */
		public static function should_track() {
			$track = true;

			$method     = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$purpose    = '';

			if ( isset( $_SERVER['HTTP_SEC_PURPOSE'] ) ) {
				$purpose = sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_PURPOSE'] ) );
			} elseif ( isset( $_SERVER['HTTP_PURPOSE'] ) ) {
				$purpose = sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) );
			}

			if ( 'HEAD' === $method || false !== stripos( $purpose, 'prefetch' ) ) {
				$track = false;
			} elseif ( '' === $user_agent || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|headless|lighthouse|pingdom|preview|monitor/i', $user_agent ) ) {
				$track = false;
			} elseif ( current_user_can( 'manage_woocommerce' ) ) {
				// Store staff testing the button.
				$track = false;
			}

			return (bool) apply_filters( 'woo_buy_now_analytics_should_track', $track );
		}

		/**
		 * Start the context for an AJAX Buy Now request.
		 */
		public function start_ajax_context() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- read-only here; the handler validates the request.
			if ( ! isset( $_POST['is_buy_now'] ) || 'true' !== $_POST['is_buy_now'] ) {
				return;
			}

			$this->start_context(
				isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0,
				isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0,
				isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1,
				self::sanitize_source( isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '', 'single' )
			);
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		/**
		 * Start the context for a form submit (single) or link (archive) Buy Now request.
		 */
		public function start_request_context() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only here, same as the Buy Now handler.
			if ( wp_doing_ajax() || ! isset( $_REQUEST['wc-quick-buy-now'] ) ) {
				return;
			}

			$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'];

			$this->start_context(
				absint( $_REQUEST['wc-quick-buy-now'] ),
				isset( $_REQUEST['variation_id'] ) ? absint( $_REQUEST['variation_id'] ) : 0,
				isset( $_REQUEST['quantity'] ) && ! is_array( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1,
				self::sanitize_source( isset( $_REQUEST['wbnb_source'] ) ? wp_unslash( $_REQUEST['wbnb_source'] ) : '', $is_post ? 'single' : 'archive' )
			);
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Start tracking a Buy Now request.
		 */
		protected function start_context( $product_id, $variation_id, $qty, $source ) {
			if ( ! $product_id || ! WC()->cart || ! self::should_track() ) {
				return;
			}

			// Debounce double clicks: same product within 3 seconds.
			$last = WC()->session ? (array) WC()->session->get( 'wbnb_last', array() ) : array();
			if ( isset( $last[ $product_id ] ) && time() - (int) $last[ $product_id ] < 3 ) {
				$this->debounced = true;
				return;
			}

			$mode  = apply_filters( 'woo_buy_now_redirect_location', get_option( 'wbnb_redirect_location', 'checkout' ), $product_id );
			$modes = self::modes();
			$lines = 0;
			$value = 0;

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$lines ++;
				$value += isset( $cart_item['line_subtotal'] ) ? (float) $cart_item['line_subtotal'] : 0;
			}

			$sources = self::sources();

			$this->context = array(
				'product_id'        => $product_id,
				'variation_id'      => $variation_id,
				'qty'               => $qty,
				'source'            => $source,
				'source_code'       => $sources[ $source ],
				'mode'              => isset( $modes[ $mode ] ) ? $modes[ $mode ] : 0,
				'device'            => wp_is_mobile() ? Woo_Buy_Now_Button_Analytics_Store::DEVICE_MOBILE : Woo_Buy_Now_Button_Analytics_Store::DEVICE_DESKTOP,
				'logged_in'         => is_user_logged_in() ? 1 : 0,
				'cart_lines_before' => $lines,
				'cart_value_before' => $value,
				'cart_reset'        => 0,
				'keys'              => array(),
				'event_id'          => 0,
				'flushed'           => false,
			);
		}

		/**
		 * A product was added to the cart during a Buy Now request.
		 */
		public function added_to_cart( $cart_item_key, $product_id, $quantity, $variation_id ) {
			if ( ! $this->context ) {
				return;
			}

			$this->context['keys'][ $cart_item_key ] = true;

			/**
			 * A Buy Now request added a product to the cart.
			 *
			 * @param int    $product_id   Product ID.
			 * @param int    $variation_id Variation ID, 0 for none.
			 * @param int    $quantity     Quantity added.
			 * @param string $source       Button source slug (single, archive, shortcode-single, ...).
			 */
			do_action( 'woo_buy_now_added_to_cart', $product_id, $variation_id, $quantity, $this->context['source'] );
		}

		/**
		 * Cart emptied: by "reset cart" during Buy Now, or after an order.
		 */
		public function cart_emptied() {
			// A debounced repeat click re-adds the same product (same cart item key), so its attribution still applies.
			if ( WC()->session && ! $this->debounced ) {
				WC()->session->set( self::SESSION_KEY, null );
			}

			if ( $this->context ) {
				$this->context['cart_reset'] = $this->context['cart_lines_before'] > 0 ? 1 : 0;
				$this->context['keys']       = array();
			}
		}

		/**
		 * AJAX success: write the attempt now so the browser gets its ID, and log the popup checkout view.
		 */
		public function ajax_response( $data, $product_id ) {
			$event_id = $this->flush();

			if ( $event_id ) {
				$data['wbnb_ref'] = $event_id;

				if ( ! empty( $data['checkout_template'] ) ) {
					$this->log_checkout_views();
				}
			}

			return $data;
		}

		/**
		 * Write the attempt row once per request and store the attribution for the added cart lines.
		 *
		 * @return int Attempt event ID, 0 when nothing was written.
		 */
		public function flush() {
			if ( ! $this->context || $this->context['flushed'] ) {
				return $this->context ? $this->context['event_id'] : 0;
			}

			$this->context['flushed'] = true;

			$context = &$this->context;
			$result  = empty( $context['keys'] ) ? $this->failure_reason() : Woo_Buy_Now_Button_Analytics_Store::RESULT_OK;

			$context['event_id'] = Woo_Buy_Now_Button_Analytics_Store::insert_event( array(
				'event'             => Woo_Buy_Now_Button_Analytics_Store::EVENT_ATTEMPT,
				'result'            => $result,
				'product_id'        => $context['product_id'],
				'variation_id'      => $context['variation_id'],
				'qty'               => $context['qty'],
				'source'            => $context['source_code'],
				'mode'              => $context['mode'],
				'device'            => $context['device'],
				'logged_in'         => $context['logged_in'],
				'cart_lines_before' => $context['cart_lines_before'],
				'cart_value_before' => $context['cart_value_before'],
				'cart_reset'        => $context['cart_reset'],
			) );

			if ( ! WC()->session ) {
				return $context['event_id'];
			}

			$last                           = (array) WC()->session->get( 'wbnb_last', array() );
			$last[ $context['product_id'] ] = time();
			WC()->session->set( 'wbnb_last', array_slice( $last, -10, null, true ) );

			if ( $context['event_id'] && ! empty( $context['keys'] ) ) {
				$map = (array) WC()->session->get( self::SESSION_KEY, array() );

				foreach ( array_keys( $context['keys'] ) as $cart_item_key ) {
					$map[ $cart_item_key ] = array(
						'src'  => $context['source_code'],
						'mode' => $context['mode'],
						't'    => time(),
						'e'    => $context['event_id'],
						'dev'  => $context['device'],
						'p'    => $context['product_id'],
						'v'    => $context['variation_id'],
						'q'    => $context['qty'],
						'cv'   => 0,
					);
				}

				WC()->session->set( self::SESSION_KEY, $map );
			}

			return $context['event_id'];
		}

		/**
		 * Why nothing was added. Sold-individually items already in the cart count as success.
		 *
		 * @return int Result constant.
		 */
		protected function failure_reason() {
			$context = &$this->context;
			$product = wc_get_product( $context['variation_id'] ? $context['variation_id'] : $context['product_id'] );

			// Item already in the cart (sold individually): Buy Now still goes to checkout with it.
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( (int) $cart_item['product_id'] === $context['product_id'] && ( ! $context['variation_id'] || (int) $cart_item['variation_id'] === $context['variation_id'] ) ) {
					$context['keys'][ $cart_item_key ] = true;

					return Woo_Buy_Now_Button_Analytics_Store::RESULT_OK;
				}
			}

			if ( ! $product ) {
				return Woo_Buy_Now_Button_Analytics_Store::RESULT_VALIDATION;
			}

			$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;

			if ( $parent && ! woo_buy_now_button()->get_frontend()->is_product_type_supported( $parent->get_type() ) ) {
				return Woo_Buy_Now_Button_Analytics_Store::RESULT_UNSUPPORTED_TYPE;
			}

			if ( $product->is_type( 'grouped' ) ) {
				// AJAX sends quantities[child], the product form sends quantity[child].
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, same request the Buy Now handler validated.
				$quantities = array();
				foreach ( array( 'quantities', 'quantity' ) as $field ) {
					if ( isset( $_REQUEST[ $field ] ) && is_array( $_REQUEST[ $field ] ) ) {
						$quantities = array_map( 'absint', wp_unslash( $_REQUEST[ $field ] ) );
						break;
					}
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended

				return array_sum( $quantities ) > 0 ? Woo_Buy_Now_Button_Analytics_Store::RESULT_VALIDATION : Woo_Buy_Now_Button_Analytics_Store::RESULT_NO_QUANTITY;
			}

			if ( ! $product->is_in_stock() || ( $product->managing_stock() && ! $product->backorders_allowed() && $context['qty'] > (int) $product->get_stock_quantity() ) ) {
				return Woo_Buy_Now_Button_Analytics_Store::RESULT_OUT_OF_STOCK;
			}

			if ( ! $product->is_purchasable() ) {
				return Woo_Buy_Now_Button_Analytics_Store::RESULT_NOT_PURCHASABLE;
			}

			if ( $product->is_type( 'variable' ) && ! $context['variation_id'] ) {
				return Woo_Buy_Now_Button_Analytics_Store::RESULT_NEEDS_OPTIONS;
			}

			return Woo_Buy_Now_Button_Analytics_Store::RESULT_VALIDATION;
		}

		/**
		 * Log a checkout view on the checkout page (classic or block).
		 */
		public function checkout_view() {
			if ( ! is_checkout() || is_wc_endpoint_url() || ! self::should_track() ) {
				return;
			}

			$this->log_checkout_views();
		}

		/**
		 * Log one checkout view per Buy Now attempt not logged yet.
		 *
		 * The row repeats the attempt's product, so a grouped product's checkout view
		 * stays on the grouped product, the same as its attempt.
		 */
		protected function log_checkout_views() {
			if ( ! WC()->session || ! WC()->cart ) {
				return;
			}

			$map = (array) WC()->session->get( self::SESSION_KEY, array() );

			if ( empty( $map ) ) {
				return;
			}

			$cart    = WC()->cart->get_cart();
			$logged  = array();
			$changed = false;

			foreach ( $map as $cart_item_key => $entry ) {
				if ( ! empty( $entry['cv'] ) || ! isset( $cart[ $cart_item_key ] ) || ! $this->in_window( $entry ) ) {
					continue;
				}

				// One view per attempt: a grouped Buy Now adds several cart lines.
				if ( ! isset( $logged[ $entry['e'] ] ) ) {
					Woo_Buy_Now_Button_Analytics_Store::insert_event( array(
						'event'        => Woo_Buy_Now_Button_Analytics_Store::EVENT_CHECKOUT_VIEW,
						'ref_id'       => (int) $entry['e'],
						'product_id'   => isset( $entry['p'] ) ? (int) $entry['p'] : (int) $cart[ $cart_item_key ]['product_id'],
						'variation_id' => isset( $entry['v'] ) ? (int) $entry['v'] : (int) $cart[ $cart_item_key ]['variation_id'],
						'qty'          => isset( $entry['q'] ) ? (int) $entry['q'] : (int) $cart[ $cart_item_key ]['quantity'],
						'source'       => (int) $entry['src'],
						'mode'         => (int) $entry['mode'],
						'device'       => (int) $entry['dev'],
						'logged_in'    => is_user_logged_in() ? 1 : 0,
					) );

					$logged[ $entry['e'] ] = true;
				}

				$map[ $cart_item_key ]['cv'] = 1;
				$changed                     = true;
			}

			if ( $changed ) {
				WC()->session->set( self::SESSION_KEY, $map );
			}
		}

		/**
		 * Is the attribution entry inside the attribution window.
		 */
		protected function in_window( $entry ) {
			$window = absint( apply_filters( 'woo_buy_now_analytics_attribution_window', 2 * DAY_IN_SECONDS ) );

			return ! empty( $entry['t'] ) && time() - (int) $entry['t'] <= $window;
		}

		/**
		 * Tag a Buy Now order line item.
		 *
		 * @param WC_Order_Item_Product $item          Order item.
		 * @param string                $cart_item_key Cart item key.
		 * @param array                 $values        Cart item.
		 */
		public function tag_order_item( $item, $cart_item_key, $values ) {
			if ( ! WC()->session ) {
				return;
			}

			$map = (array) WC()->session->get( self::SESSION_KEY, array() );

			if ( empty( $map[ $cart_item_key ] ) || ! $this->in_window( $map[ $cart_item_key ] ) ) {
				return;
			}

			$entry = $map[ $cart_item_key ];

			$item->update_meta_data( '_wbnb', array(
				'v'    => 1,
				'src'  => (int) $entry['src'],
				'mode' => (int) $entry['mode'],
				't'    => (int) $entry['t'],
				'e'    => (int) $entry['e'],
				'dev'  => (int) $entry['dev'],
			) );
		}

		/**
		 * Flag the order when it has a Buy Now item. Runs before WooCommerce saves the order.
		 *
		 * @param WC_Order $order Order.
		 */
		public function tag_order( $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( is_array( $item->get_meta( '_wbnb' ) ) ) {
					$order->update_meta_data( '_wbnb_buy_now', 1 );

					return;
				}
			}

			// Store API draft orders are updated in place: the Buy Now item may have been removed.
			$order->delete_meta_data( '_wbnb_buy_now' );
		}

		/**
		 * A Buy Now order changed status: rebuild its day, set new vs returning once paid.
		 */
		public function order_status_changed( $order_id, $from, $to, $order = null ) {
			$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

			if ( ! $order || ! $order->get_meta( '_wbnb_buy_now' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
				return;
			}

			$this->queue_rebuild( $order );

			if ( in_array( $to, wc_get_is_paid_statuses(), true ) && '' === $order->get_meta( '_wbnb_returning' ) ) {
				$args = array( 'order_id' => $order->get_id() );

				if ( ! as_has_scheduled_action( 'wbnb_analytics_order_paid', $args, self::GROUP ) ) {
					as_enqueue_async_action( 'wbnb_analytics_order_paid', $args, self::GROUP );
				}
			}
		}

		/**
		 * A Buy Now order was refunded: rebuild its day.
		 */
		public function order_refunded( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order && $order->get_meta( '_wbnb_buy_now' ) && function_exists( 'as_enqueue_async_action' ) ) {
				$this->queue_rebuild( $order );
			}
		}

		/**
		 * Queue a rebuild of the order's day (deduped).
		 */
		protected function queue_rebuild( $order ) {
			if ( ! $order->get_date_created() ) {
				return;
			}

			$args = array( 'date' => wp_date( 'Y-m-d', $order->get_date_created()->getTimestamp() ) );

			if ( ! as_has_scheduled_action( 'wbnb_analytics_rebuild_day', $args, self::GROUP ) ) {
				as_enqueue_async_action( 'wbnb_analytics_rebuild_day', $args, self::GROUP );
			}
		}

		/**
		 * Job: record whether a paid Buy Now order came from a returning customer.
		 */
		public function run_order_paid( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order || '' !== $order->get_meta( '_wbnb_returning' ) ) {
				return;
			}

			$customer = $order->get_customer_id() ? $order->get_customer_id() : $order->get_billing_email();
			$previous = array();

			if ( $customer && $order->get_date_created() ) {
				$previous = wc_get_orders( array(
					'customer'     => $customer,
					'status'       => wc_get_is_paid_statuses(),
					'date_created' => '<' . $order->get_date_created()->getTimestamp(),
					'exclude'      => array( $order->get_id() ),
					'limit'        => 1,
					'return'       => 'ids',
				) );
			}

			$order->update_meta_data( '_wbnb_returning', empty( $previous ) ? 0 : 1 );
			$order->save_meta_data();

			$this->queue_rebuild( $order );
		}

		/**
		 * Job: prune old events, continue in another job while rows remain.
		 */
		public function run_prune() {
			if ( Woo_Buy_Now_Button_Analytics_Store::prune() && ! as_has_scheduled_action( 'wbnb_analytics_prune_batch', array(), self::GROUP ) ) {
				as_enqueue_async_action( 'wbnb_analytics_prune_batch', array(), self::GROUP );
			}
		}

		/**
		 * Beacon endpoint for browser-only events: popup closed, blocked by variation check, external link click.
		 *
		 * No nonce on purpose: cached pages carry stale nonces, and this only writes anonymous,
		 * rate-limited telemetry rows with whitelisted values.
		 */
		public function beacon() {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- public, rate-limited telemetry; no privileged action.
			$allowed = array(
				Woo_Buy_Now_Button_Analytics_Store::EVENT_POPUP_CLOSE    => array( Woo_Buy_Now_Button_Analytics_Store::RESULT_OK ),
				Woo_Buy_Now_Button_Analytics_Store::EVENT_CLIENT_BLOCK   => array( Woo_Buy_Now_Button_Analytics_Store::RESULT_NEEDS_OPTIONS, Woo_Buy_Now_Button_Analytics_Store::RESULT_UNAVAILABLE_VARIATION ),
				Woo_Buy_Now_Button_Analytics_Store::EVENT_EXTERNAL_CLICK => array( Woo_Buy_Now_Button_Analytics_Store::RESULT_OK ),
			);

			$event      = isset( $_POST['event'] ) ? absint( $_POST['event'] ) : 0;
			$result     = isset( $_POST['result'] ) ? absint( $_POST['result'] ) : 0;
			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$ref_id     = isset( $_POST['ref'] ) ? absint( $_POST['ref'] ) : 0;
			$source     = self::sanitize_source( isset( $_POST['source'] ) ? wp_unslash( $_POST['source'] ) : '', 'other' );
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $allowed[ $event ] ) || ! in_array( $result, $allowed[ $event ], true ) || ! self::should_track() ) {
				wp_send_json_error( null, 400 );
			}

			$product = wc_get_product( $product_id );

			if ( ! $product || 'publish' !== $product->get_status() ) {
				wp_send_json_error( null, 400 );
			}

			// Popup close must belong to a recent attempt for the same product.
			if ( Woo_Buy_Now_Button_Analytics_Store::EVENT_POPUP_CLOSE === $event ) {
				$parent = $ref_id ? Woo_Buy_Now_Button_Analytics_Store::get_event( $ref_id ) : null;

				if ( ! $parent || Woo_Buy_Now_Button_Analytics_Store::EVENT_ATTEMPT !== (int) $parent->event || (int) $parent->product_id !== $product_id || strtotime( $parent->created_gmt . ' UTC' ) < time() - 2 * DAY_IN_SECONDS ) {
					wp_send_json_error( null, 400 );
				}
			} else {
				$ref_id = 0;
			}

			// One event per visitor every 2 seconds. The IP is hashed for the limiter key only and never stored.
			$limiter = 'wbnb_evt_' . md5( WC_Geolocation::get_ip_address() . wp_salt( 'nonce' ) );

			if ( WC_Rate_Limiter::retried_too_soon( $limiter ) ) {
				wp_send_json_error( null, 429 );
			}

			WC_Rate_Limiter::set_rate_limit( $limiter, 2 );

			$parent_id = $product->get_parent_id();
			$modes     = self::modes();
			$mode      = apply_filters( 'woo_buy_now_redirect_location', get_option( 'wbnb_redirect_location', 'checkout' ), $parent_id ? $parent_id : $product_id );
			$sources   = self::sources();

			Woo_Buy_Now_Button_Analytics_Store::insert_event( array(
				'event'        => $event,
				'result'       => $result,
				'ref_id'       => $ref_id,
				'product_id'   => $parent_id ? $parent_id : $product_id,
				'variation_id' => $parent_id ? $product_id : 0,
				'source'       => $sources[ $source ],
				'mode'         => isset( $modes[ $mode ] ) ? $modes[ $mode ] : 0,
				'device'       => wp_is_mobile() ? Woo_Buy_Now_Button_Analytics_Store::DEVICE_MOBILE : Woo_Buy_Now_Button_Analytics_Store::DEVICE_DESKTOP,
				'logged_in'    => is_user_logged_in() ? 1 : 0,
			) );

			wp_send_json_success();
		}

		/**
		 * Suggested privacy-policy text.
		 */
		public function privacy_policy_content() {
			if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
				return;
			}

			$content = '<p>' . esc_html__( 'When a visitor uses the Buy Now button, this store records anonymous usage data in its own database: which product, button and checkout type was used, whether the product was added, whether checkout was reached, and the device type (mobile or desktop). No IP address, name, email address, user account or browser details are stored, and the data is not sent to any third party. Orders placed through Buy Now are marked so the store can see sales from the button.', 'woo-buy-now-button' ) . '</p>';

			wp_add_privacy_policy_content( esc_html__( 'Quick Buy Now Button for WooCommerce', 'woo-buy-now-button' ), wp_kses_post( wpautop( $content, false ) ) );
		}
	}
}
