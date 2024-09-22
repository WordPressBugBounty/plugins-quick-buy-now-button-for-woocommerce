<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Buy_Now_Button_Frontend' ) ) {
	/**
	 * Plugin Front End
	 */
	class Woo_Buy_Now_Button_Frontend {
		protected static $_instance = null;

		protected function __construct() {
			$this->includes();
			$this->hooks();
			$this->init();

			do_action( 'woo_buy_now_button_frontend_loaded', $this );
		}

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		protected function includes() {
			include_once dirname( __FILE__ ) . '/themes-support.php';
		}

		protected function hooks() {
			add_action( 'template_redirect', array( $this, 'buy_now_button_submit' ) );

			if ( 'custom' == get_option( 'wbnb_button_style', 'default' ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );
			}

			if ( 'yes' == get_option( 'wbnb_enable_button_single', 'yes' ) ) {
				$this->button_position_single();
			}

			if ( 'yes' == get_option( 'wbnb_enable_button_archive', 'yes' ) ) {
				$this->button_position_archive();
			}
		}

		protected function init() {
		}

		/**
		 * Enqueue Scripts
		 */
		public function enqueue_scripts() {
			/*if ( ! is_woocommerce() ) {
				return; // Keep commented those codes, products shortcode missing custom styles
			}*/

			if ( 'custom' !== get_option( 'wbnb_button_style', 'default' ) ) {
				return;
			}

			$text_color       = get_option( 'wbnb_button_color' );
			$background_color = get_option( 'wbnb_button_background' );
			$border_color     = get_option( 'wbnb_button_border_color' );
			$border_size      = get_option( 'wbnb_button_border_size' );
			$border_radius    = get_option( 'wbnb_button_border_radius' );
			$font_size        = get_option( 'wbnb_button_font_size' );
			$margin           = get_option( 'wbnb_button_margin' );
			$padding          = get_option( 'wbnb_button_padding' );

			$custom_css = ".woocommerce a.button.wc-buy-now-btn, .woocommerce button.button.wc-buy-now-btn, .woocommerce input.button.wc-buy-now-btn { ";

			if ( ! empty( $text_color ) ) {
				$custom_css .= 'color: ' . $text_color . ' !important;';
			}

			if ( ! empty( $background_color ) ) {
				$custom_css .= 'background-color: ' . $background_color . ' !important;';
			}

			if ( ! empty( $border_color ) ) {
				$custom_css .= 'border-color: ' . $border_color . ' !important;';
			}

			if ( ! empty( $border_size ) ) {
				$custom_css .= 'border-width: ' . absint( $border_size ) . 'px !important;';
				$custom_css .= 'border-style: solid;';
			}

			if ( ! empty( $border_radius ) ) {
				$custom_css .= 'border-radius: ' . absint( $border_radius ) . 'px !important;';
			}

			if ( ! empty( $font_size ) ) {
				$custom_css .= 'font-size: ' . absint( $font_size ) . 'px !important;';
			}

			if ( is_array( $margin ) ) {
				foreach ( $margin as $key => $value ) {
					if ( isset( $margin[ $key ] ) && $value !== '' ) {
						$custom_css .= 'margin-' . $key . ': ' . absint( $value ) . 'px !important;';
					}
				}
			}

			if ( is_array( $padding ) ) {
				foreach ( $padding as $key => $value ) {
					if ( isset( $padding[ $key ] ) && $value !== '' ) {
						$custom_css .= 'padding-' . $key . ': ' . absint( $value ) . 'px !important;';
					}
				}
			}

			$custom_css .= " }";

			wp_add_inline_style( 'woocommerce-inline', $custom_css );
		}

		/**
		 * Button Single Position
		 */
		public function button_position_single() {
			if ( 'after_add_to_cart' == get_option( 'wbnb_button_position_single', 'after_add_to_cart' ) ) {
				add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'buy_now_button_single' ) );
			} else {
				add_action( 'woocommerce_after_add_to_cart_quantity', array( $this, 'buy_now_button_single' ) );
			}
		}

		/**
		 * Button Archive Position
		 */
		public function button_position_archive() {
			$button_position_archive = get_option( 'wbnb_button_position_archive', 'after_add_to_cart' );

			if ( 'after_add_to_cart' == $button_position_archive ) {
				add_action( 'woocommerce_after_shop_loop_item', array( $this, 'buy_now_button_archive' ), 11 );
			} else {
				add_action( 'woocommerce_after_shop_loop_item', array( $this, 'buy_now_button_archive' ), 9 );
			}
		}

		/**
		 * Button Redirect Location
		 */
		public function button_redirect_location( $product_id ) {
			$redirect     = apply_filters( 'woo_buy_now_redirect_location', get_option( 'wbnb_redirect_location', 'checkout' ), $product_id );
			$custom_url   = apply_filters( 'woo_buy_now_redirect_custom_url', get_option( 'wbnb_custom_redirect_url', '' ), $product_id );
			$redirect_url = '';

			switch ( $redirect ) {
				case 'checkout':
					$redirect_url = wc_get_checkout_url();
					break;
				case 'cart':
					$redirect_url = wc_get_cart_url();
					break;
				case 'custom':
					$redirect_url = esc_url( $custom_url );
					break;
			}

			return $redirect_url;
		}

		/**
		 * Button Markup for Single Product Page
		 */
		public function buy_now_button_single() {
			global $product;

			if ( apply_filters( 'woo_buy_now_button_disable', false, $product ) ) {
				return;
			}

			$product_id        = $product->get_ID();
			$button_class      = apply_filters( 'woo_buy_now_button_class_single', 'wc-buy-now-btn wc-buy-now-btn-single single_add_to_cart_button button alt', $product_id );
			$button_text       = apply_filters( 'woo_buy_now_button_text_single', get_option( 'wbnb_button_text', 'Buy Now' ), $product_id );
			$redirect_location = apply_filters( 'woo_buy_now_redirect_location', get_option( 'wbnb_redirect_location', 'checkout' ), $product_id );
			$custom_url        = apply_filters( 'woo_buy_now_redirect_custom_url', get_option( 'wbnb_custom_redirect_url', '' ), $product_id );

			do_action( 'woo_buy_now_button_single_before_load', $product );

			if ( ! empty( $custom_url ) && 'custom' === $redirect_location ) {
				// For custom link
				return printf( '<a href="%s" target="_blank" class="%s" rel="nofollow">%s</a>', esc_url( $custom_url ), esc_attr( $button_class ), esc_html__( $button_text, 'woo-buy-now-button' ) );
			}

			return printf( '<button type="submit" name="wc-quick-buy-now" value="%d" class="%s">%s</button>', $product_id, esc_attr( $button_class ), esc_html__( $button_text, 'woo-buy-now-button' ) );
		}

		/**
		 * Button Markup for Shop/Archive Page
		 */
		public function buy_now_button_archive() {
			global $product;

			if ( apply_filters( 'woo_buy_now_button_disable', false, $product ) ) {
				return;
			}

			if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return;
			}

			$product_id        = $product->get_ID();
			$button_class      = apply_filters( 'woo_buy_now_button_class_archive', 'wc-buy-now-btn wc-buy-now-btn-archive button add_to_cart_button', $product_id );
			$button_text       = apply_filters( 'woo_buy_now_button_text_archive', get_option( 'wbnb_button_text', 'Buy Now' ), $product_id );
			$quantity          = apply_filters( 'woo_buy_now_button_quantity', get_option( 'wbnb_default_qnt', 1 ), $product_id );
			$redirect_location = apply_filters( 'woo_buy_now_redirect_location', get_option( 'wbnb_redirect_location', 'checkout' ), $product_id );
			$custom_url        = apply_filters( 'woo_buy_now_redirect_custom_url', get_option( 'wbnb_custom_redirect_url', '' ), $product_id );

			// Check quantity is not bigger then stock
			if ( $product->get_manage_stock() ) {
				$stock_quantity        = $product->get_stock_quantity(); // get product stock quantity
				$is_backorders_allowed = $product->backorders_allowed(); // get product backorder allowed

				if ( $stock_quantity < $quantity && ! $is_backorders_allowed ) {
					$quantity = $stock_quantity;
				}
			}

			do_action( 'woo_buy_now_button_archive_before_load', $product );

			if ( $product->is_type( 'simple' ) ) {
				// For custom link
				if ( ! empty( $custom_url ) && 'custom' === $redirect_location ) {
					return printf( '<a href="%s" target="_blank" data-quantity="%s" class="%s" data-product_id="%s" rel="nofollow">%s</a>', esc_url( $custom_url ), intval( $quantity ), esc_attr( $button_class ), $product_id, esc_html__( $button_text, 'woo-buy-now-button' ) );
				}

				// Auto reset cart before buy now
				if ( 'yes' == get_option( 'wbnb_reset_cart', 'no' ) ) {
					WC()->cart->empty_cart();

					// To clear cart session, (By the way, To clear the session as well we can simply add true param to the empty_cart( true ) method.)
					// WC()->session->set('cart', array());
				}

				$redirect_url = $this->button_redirect_location( $product_id );

                $redirect_url = add_query_arg(
                    array(
                        'wc-quick-buy-now' => $product_id,
                        'quantity' => intval( $quantity )
                    ),
                    $redirect_url
                );

				return printf( '<a href="%s" data-quantity="%s" class="%s" data-product_id="%s" rel="nofollow">%s</a>', esc_url( $redirect_url ), intval( $quantity ), esc_attr( $button_class ), $product_id, esc_html__( $button_text, 'woo-buy-now-button' ) );
			}

			return;
		}

		/**
		 * Button Submit Action Handler for Single Product Page Button
		 */
		public function buy_now_button_submit() {
			if ( ! isset( $_REQUEST['wc-quick-buy-now'] ) ) {
				return false;
			}

			// $default_qunt	= isset( $_REQUEST['quantity'] ) ? $_REQUEST['quantity'] : 1;
			// $global_qunt	= get_option( 'wbnb_default_qnt', 1 );
			// $quantity 		= ( $global_qunt > 1 ) ? $global_qunt : $default_qunt;
			// $quantity 		= apply_filters( 'woo_buy_now_button_quantity', $quantity, $product_id );

			$quantity     = isset( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1;
			$product_id   = isset( $_REQUEST['wc-quick-buy-now'] ) ? absint( $_REQUEST['wc-quick-buy-now'] ) : '';
			$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( $_REQUEST['variation_id'] ) : '';
			$variation    = [];
			$redirect_url = $this->button_redirect_location( $product_id );

			if ( $product_id ) {
				// Auto reset cart before buy now
				if ( 'yes' == get_option( 'wbnb_reset_cart', 'no' ) ) {
					WC()->cart->empty_cart();
				}

				if ( $variation_id ) {
					// For Variable Product
					if ( isset( $_REQUEST ) && ! empty( $_REQUEST ) ) {
						foreach ( $_REQUEST as $name => $value ) {
							if ( substr( $name, 0, 10 ) === 'attribute_' ) {
								$variation[ $name ] = esc_html( $value );
							}
						}
					}

					if ( 'yes' == get_option( 'wbnb_reset_cart', 'no' ) ) {
						WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
					}

//                    $query_args = array(
//                        'add-to-cart'  => $product_id,
//                        'product_id'   => $product_id,
//                        'quantity'     => $quantity,
//                        'variation_id' => $variation_id,
//                    );
//
//                    $query_args   = array_merge( $query_args, $variation );
//                    $redirect_url = add_query_arg( $query_args, $redirect_url );
//
//                    wp_safe_redirect( $redirect_url );
//                    exit;
				} else {
					WC()->cart->add_to_cart( $product_id, $quantity );

//                    $query_args = array(
//                        'add-to-cart' => $product_id,
//                        'product_id'  => $product_id,
//                    );
//
//                    // Example: Group Product Quantity
//                    if ( is_array( $quantity ) ) {
//                        $quantity_arg = [];
//
//                        error_log(print_r($quantity));
//
//                        foreach ( $quantity as $key => $value ) {
//                            $quantity_arg["quantity[$key]"] = $value;
//                        }
//
//                        $query_args = array_merge( $query_args, $quantity_arg );
//                    } else {
//                        $query_args = array_merge( $query_args, array( 'quantity' => $quantity ) );
//                    }
//
//                    $redirect_url = add_query_arg( $query_args, $redirect_url );
				}

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}
}