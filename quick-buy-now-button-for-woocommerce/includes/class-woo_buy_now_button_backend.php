<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Buy_Now_Button_Backend' ) ) {
	/**
	 * Plugin Back End
	 */
	class Woo_Buy_Now_Button_Backend {
		protected static $_instance = null;

		protected function __construct() {
			$this->includes();
			$this->hooks();
			$this->init();

			do_action( 'woo_buy_now_button_backend_loaded', $this );
		}

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		protected function includes() {
		}

		protected function hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'admin_settings_page' ), 11 );
			add_action( 'admin_menu', array( $this, 'admin_settings_menu' ) );

			add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_data_tab' ) );
			add_action( 'woocommerce_product_data_panels', array( $this, 'product_data_panel' ) );
		}

		protected function init() {
		}

		/**
		 * Admin Scripts
		 */
		public function admin_assets() {
			wp_enqueue_script( 'buy-now-button-admin-script', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/admin/js/scripts.js', array( 'jquery' ), '1.0.1' );
		}

		/**
		 * Plugin Action Links
		 */
		public function plugin_action_links( $links ) {
			$settings_link = array(
            	'settings' => sprintf( 
            		'<a href="%1$s" title="%2$s">%2$s</a>', 
            		esc_url( add_query_arg( array(
						'page'    => 'wc-settings',
		                'tab'     => 'woo-buy-now-button'
		            ), admin_url( 'admin.php' ) ) ), 
            		esc_attr__( 'Settings', 'woo-buy-now-button' ) 
            	)
            );

            $pro_link = array(
            	'go-pro' => sprintf( 
            		'<a target="_blank" style="color: #45b450; font-weight: bold;" href="%1$s" title="%2$s">%2$s</a>', 
            		esc_url( 'https://wpxpress.net/products/quick-buy-now-button-for-woocommerce/' ), 
            		esc_attr__( 'Go Pro', 'woo-buy-now-button' ) 
            	)
            );

            if ( woo_buy_now_button()->is_pro() ) {
            	$pro_link = array();
            }

			return array_merge( $settings_link, $links, $pro_link );
		}

		/**
		 * Plugin Row Meta
		 */
		public function plugin_row_meta( $links, $file ) {
			if ( plugin_basename( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) !== $file ) {
				return $links;
			}

			$report_url        = 'https://wpxpress.net/submit-ticket/';
			$documentation_url = 'https://wpxpress.net/docs/quick-buy-now-button-for-woocommerce/';

			$row_meta['docs']    = sprintf( '<a target="_blank" href="%1$s" title="%2$s">%2$s</a>', esc_url( $documentation_url ), esc_html__( 'Documentation', 'woo-buy-now-button' ) );
			$row_meta['support'] = sprintf( '<a target="_blank" href="%1$s">%2$s</a>', esc_url( $report_url ), esc_html__( 'Get Help &amp; Support', 'woo-buy-now-button' ) );

			return array_merge( $links, $row_meta );
		}

		/**
		 * Admin Settings Page
		 */
		public function admin_settings_page( $settings ) {
			$settings = include dirname( __FILE__ ) . '/admin/class-woo_buy_now_button_settings.php';

			return $settings;
		}

		/**
		 * Admin Settings Menu
		 */
		public function admin_settings_menu() {
			$page_title = esc_html__( 'Buy Now Button Settings', 'woo-buy-now-button' );
			$menu_title = esc_html__( 'Buy Now Button', 'woo-buy-now-button' );

			$settings_link = esc_url( add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab'  => 'woo-buy-now-button',
				),
				admin_url( 'admin.php' )
			) );

			add_menu_page( $page_title, $menu_title, 'manage_options', $settings_link, '', 'dashicons-cart', 35 );
		}

		/**
		 * Product level settings tab
		 */
		public function product_data_tab( $tabs ) {
			$tabs['woo-buy-now-button-pro'] = array(
				'label'    => esc_html__( 'Buy Now Button', 'woo-buy-now-button' ),
				'target'   => 'woo-buy-now-button-pro-options',
				'class'    => array( '' ),
				'priority' => 2,
			);

			return $tabs;
		}

		/**
		 * Product level settings panel
		 */
		public function product_data_panel() {
			$is_buy_now_button_disable    = get_post_meta( get_the_ID(), 'is_buy_now_button_disable', true );
			$is_add_to_cart_hide          = get_post_meta( get_the_ID(), 'is_add_to_cart_hide', true );
			$is_quantity_hide             = get_post_meta( get_the_ID(), 'is_quantity_hide', true );
			$default_qnt_number           = get_post_meta( get_the_ID(), 'default_qnt_number', true );
			$buy_now_button_text          = get_post_meta( get_the_ID(), 'buy_now_button_text', true );
			$buy_now_redirect_location    = get_post_meta( get_the_ID(), 'buy_now_redirect_location', true );
			$buy_now_redirect_custom_link = get_post_meta( get_the_ID(), 'buy_now_redirect_custom_link', true );
			$is_pro = ( ! function_exists( 'woo_buy_now_button_pro' ) ) ? 'is-pro' : '';

			echo "<div id='woo-buy-now-button-pro-options' class='panel woocommerce_options_panel " .  $is_pro . "'>";

			echo "<h3 style='margin-bottom: 0; padding: 0 13px;'>" . esc_html__( 'Quick Buy Now Button Settings', 'woo-buy-now-button' ) . "</h3>";

            echo "<p style='padding: 0 13px; margin-bottom: 15px;'>" . esc_html__( 'The following options control the Buy Now button for this product.', 'woo-buy-now-button' ) . "</p>";

			woocommerce_wp_checkbox( array(
				'id'          => 'is_buy_now_button_disable',
				'value'       => $is_buy_now_button_disable,
				'label'       => esc_html__( 'Disable Buy Now Button', 'woo-buy-now-button' ),
				'description' => esc_html__( 'Disable Buy Now Button for this product.', 'woo-buy-now-button' ),
			) );

			woocommerce_wp_checkbox( array(
				'id'          => 'is_add_to_cart_hide',
				'value'       => $is_add_to_cart_hide,
				'label'       => esc_html__( 'Hide Add To Cart', 'woo-buy-now-button' ),
				'description' => esc_html__( 'Hide Add To Cart Button for this product.', 'woo-buy-now-button' ),
			) );

			// woocommerce_wp_checkbox( array(
			// 	'id'          => 'is_quantity_hide',
			// 	'value'       => $is_quantity_hide,
			// 	'label'       => esc_html__( 'Hide Quantity Field', 'woo-buy-now-button' ),
			// 	'description' => esc_html__( 'Hide Quantity Field for this product.', 'woo-buy-now-button' ),
			// ) );

			woocommerce_wp_text_input( array(
				'id'                => 'default_qnt_number',
				'type'              => 'number',
				'value'             => $default_qnt_number,
				'label'             => esc_html__( 'Default Shop Quantity', 'woo-buy-now-button' ),
				'description'       => esc_html__( 'Set Default Quantity for this product on shop / archive page.', 'woo-buy-now-button' ),
				'desc_tip'          => true,
				'custom_attributes' => array(
					'step' => 1,
					'min'  => 1
				)
			) );

			woocommerce_wp_text_input( array(
				'id'          => 'buy_now_button_text',
				'value'       => $buy_now_button_text,
				'label'       => esc_html__( 'Button Text', 'woo-buy-now-button' ),
				'description' => esc_html__( 'Enter Text to Show on Buy Now button for this product.', 'woo-buy-now-button' ),
				'desc_tip'    => true,
			) );

			woocommerce_wp_select( array(
				'id'          => 'buy_now_redirect_location',
				'value'       => $buy_now_redirect_location,
				'label'       => esc_html__( 'Redirect Location', 'woo-buy-now-button' ),
				'options'     => array(
					''         => esc_html__( 'Global', 'woo-buy-now-button' ),
					'cart'     => esc_html__( 'Cart page', 'woo-buy-now-button' ),
					'checkout' => esc_html__( 'Checkout Page', 'woo-buy-now-button' ),
					'custom'   => esc_html__( 'Custom Page', 'woo-buy-now-button' )
				),
				'description' => esc_html__( 'Select where to redirect for this product.', 'woo-buy-now-button' ),
				'desc_tip'    => true,
			) );

			woocommerce_wp_text_input( array(
				'id'          => 'buy_now_redirect_custom_link',
				'value'       => $buy_now_redirect_custom_link,
				'label'       => esc_html__( 'Redirect Custom Link', 'woo-buy-now-button' ),
				'description' => esc_html__( 'Enter custom link only for custom redirect like Amazon product link.', 'woo-buy-now-button' ),
				'desc_tip'    => true,
			) );

			echo "</div>";
		}
	}
}