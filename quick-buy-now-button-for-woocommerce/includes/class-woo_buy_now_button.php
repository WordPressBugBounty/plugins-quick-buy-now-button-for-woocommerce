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
		}

		public function get_frontend() {
			return Woo_Buy_Now_Button_Frontend::instance();
		}

		public function get_backend() {
			return Woo_Buy_Now_Button_Backend::instance();
		}

		public function hooks() {
			add_action( 'init', array( $this, 'language' ), 1 );

			
			// remove_action( 'woocommerce_after_shop_loop_item', array( $this->get_frontend(), 'buy_now_button_archive'), 11 );
		}

		public function init() {
			$this->get_frontend();
			$this->get_backend();
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
	}
}
