<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Buy_Now_Button' ) ) {
	class Woo_Buy_Now_Button {

		protected $_version = WOO_BUY_NOW_BUTTON_PLUGIN_VERSION;

		protected static $_instance = null;

		public function __construct() {
			$this->includes();
			$this->hooks();
			$this->init();

			do_action( 'woo_buy_now_button_loaded', $this );
		}

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function version() {
			return esc_attr( $this->_version );
		}

		protected function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		public function includes() {
			require_once dirname( __FILE__ ) . '/class-woo_buy_now_button_frontend.php';
			require_once dirname( __FILE__ ) . '/class-woo_buy_now_button_backend.php';
			require_once dirname( __FILE__ ) . '/analytics/class-woo_buy_now_button_analytics.php';
		}

		public function get_frontend() {
			return Woo_Buy_Now_Button_Frontend::instance();
		}

		public function get_backend() {
			return Woo_Buy_Now_Button_Backend::instance();
		}

		public function get_analytics() {
			return Woo_Buy_Now_Button_Analytics::instance();
		}

		public function hooks() {
			add_action( 'init', array( $this, 'language' ), 1 );

			
			// remove_action( 'woocommerce_after_shop_loop_item', array( $this->get_frontend(), 'buy_now_button_archive'), 11 );
		}

		public function init() {
			$this->get_frontend();
			$this->get_backend();
			$this->get_analytics();
		}

		public function language() {
			load_plugin_textdomain( 'woo-buy-now-button', false, plugin_basename( dirname( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) ) . '/languages' );
		}

		public function basename() {
			return basename( dirname( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) );
		}

		public function plugin_basename() {
			return plugin_basename( WOO_BUY_NOW_BUTTON_PLUGIN_FILE );
		}

		public function plugin_dirname() {
			return dirname( plugin_basename( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) );
		}

		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) );
		}

		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) );
		}

		public function include_path( $file = '' ) {
			return untrailingslashit( plugin_dir_path( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) . 'includes' ) . $file;
		}

		public function is_pro() {
			return false;
		}

		/**
		 * Can a Pro feature run: Pro is active with a valid license. Always false in free.
		 *
		 * @param string $feature Feature slug, e.g. 'analytics-reports'.
		 *
		 * @return bool
		 */
		public function can( $feature ) {
			return false;
		}

		/**
		 * Pro product page link with UTM tags, so each upgrade prompt can be measured.
		 *
		 * @param string $placement Where the link is shown, e.g. 'settings-product-types'.
		 *
		 * @return string
		 */
		public function upgrade_url( $placement = '' ) {
			$url = add_query_arg( array(
				'utm_source'   => 'plugin',
				'utm_medium'   => 'wp-admin',
				'utm_campaign' => $this->is_pro() ? 'quick-buy-now-pro' : 'quick-buy-now-free',
				'utm_content'  => sanitize_key( $placement ),
			), 'https://wpxpress.net/products/quick-buy-now-button-for-woocommerce/' );

			return apply_filters( 'woo_buy_now_button_upgrade_url', $url, $placement );
		}
	}
}
