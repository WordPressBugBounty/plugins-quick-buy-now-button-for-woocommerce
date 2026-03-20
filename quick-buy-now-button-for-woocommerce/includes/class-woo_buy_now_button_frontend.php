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
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 99 );

			if ( 'yes' == get_option( 'wbnb_enable_button_single', 'yes' ) ) {
				$this->button_position_single();
			}

			if ( 'yes' == get_option( 'wbnb_enable_button_archive', 'yes' ) ) {
				$this->button_position_archive();
			}

			// Register shortcodes
			add_shortcode( 'woo_buy_now_button_single', array( $this, 'buy_now_button_single_shortcode' ) );
			add_shortcode( 'woo_buy_now_button_archive', array( $this, 'buy_now_button_archive_shortcode' ) );

			add_filter( 'woo_buy_now_button_is_disable', array( $this, 'is_button_disabled_by_product_type' ), 10, 2 );

			// AJAX handlers
			add_action( 'wp_ajax_wbnb_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
			add_action( 'wp_ajax_nopriv_wbnb_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

			// Open Modal in Footer
			add_action( 'wp_footer', array( $this, 'render_popup_modal' ) );
		}

		protected function init() {
		}

		/**
		 * Enqueue Scripts
		 */
		public function enqueue_scripts() {
			$asset_file = array( 'dependencies' => array( 'jquery', 'wc-add-to-cart' ), 'version' => '1.0.0' );
			if ( file_exists( plugin_dir_path( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) . 'build/frontend.asset.php' ) ) {
				$asset_file = include plugin_dir_path( WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) . 'build/frontend.asset.php';
			}

			wp_enqueue_script( 'woo-buy-now-button-script', untrailingslashit( plugins_url( '/', WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) ) . '/build/frontend.js', $asset_file['dependencies'], WOO_BUY_NOW_BUTTON_PLUGIN_VERSION, true );

			$redirect_location = get_option( 'wbnb_redirect_location', 'checkout' );
			$is_popup          = ( 'popup-checkout' === $redirect_location );
			$is_ajax           = ( 'yes' === get_option( 'wbnb_ajax_add_to_cart', 'no' ) );

			if ( $is_popup || $is_ajax ) {
				wp_enqueue_style( 'woo-buy-now-button-style', untrailingslashit( plugins_url( '/', WOO_BUY_NOW_BUTTON_PLUGIN_FILE ) ) . '/build/frontend.css', array(), WOO_BUY_NOW_BUTTON_PLUGIN_VERSION );
			}

			if ( $is_popup ) {
				// Ensure checkout script is loaded so validation works
				if ( function_exists( 'wc_get_template' ) ) {
					// We might need to force load standard checkout scripts if not on checkout page
					if ( ! is_checkout() ) {
						wp_enqueue_style( 'woocommerce_checkout' ); // Load WC Checkout CSS
						wp_enqueue_script( 'wc-checkout' );
					}
				}
			}

			wp_localize_script( 'woo-buy-now-button-script', 'wbnb_params', array(
				'ajax_url'          => admin_url( 'admin-ajax.php' ),
				'checkout_url'      => wc_get_checkout_url(),
				'redirect_location' => $redirect_location,
				'is_ajax'           => $is_ajax,
				'is_popup'          => $is_popup,
				'product_types'     => $this->get_allowed_product_types(),
				'wc_checkout_js'    => defined( 'WC_PLUGIN_FILE' ) ? plugins_url( 'assets/js/frontend/checkout.js', WC_PLUGIN_FILE ) : '',
				'nonce'                      => wp_create_nonce( 'wbnb_add_to_cart_nonce' ),
				'i18n_unavailable_text'      => esc_html__( 'Sorry, this product is unavailable. Please choose a different combination.', 'woo-buy-now-button' ),
				'i18n_make_a_selection_text' => esc_html__( 'Please select some product options before process to checkout.', 'woo-buy-now-button' ),
			) );

			if ( 'custom' == get_option( 'wbnb_button_style', 'default' ) ) {
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
		}

		/**
		 * Render Popup Modal
		 */
		public function render_popup_modal() {
			if ( 'popup-checkout' !== get_option( 'wbnb_redirect_location', 'checkout' ) ) {
				return;
			}
			?>
			<div id="wbnb-popup-overlay">
				<div id="wbnb-popup-content">
					<span class="wbnb-popup-close">&times;</span>
					<div class="wbnb-popup-inner">
						<div class="wbnb-loader">
							<!-- Simple loader -->
							<?php esc_html_e( 'Loading Checkout...', 'woo-buy-now-button' ); ?>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * AJAX Add to Cart
		 */
		public function ajax_add_to_cart() {
			// Security: Verify nonce
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wbnb_add_to_cart_nonce' ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'woo-buy-now-button' ) ), 403 );
			}

			if ( ! isset( $_POST['product_id'] ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid product.', 'woo-buy-now-button' ) ) );
			}

			$product_id        = absint( $_POST['product_id'] );
			$quantity          = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
			$variation_id      = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : '';
			$variation         = array();
			$is_buy_now        = isset( $_POST['is_buy_now'] ) && 'true' === $_POST['is_buy_now'];
			$redirect_location = get_option( 'wbnb_redirect_location', 'checkout' );

			// Get product object to check type
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Product not found.', 'woo-buy-now-button' ) ) );
			}

			// Validate product type is supported
			if ( ! $this->is_product_type_supported( $product->get_type() ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Product type not supported.', 'woo-buy-now-button' ) ) );
			}

			// Clear cart for Buy Now: if using popup checkout OR if reset cart setting is enabled
			if ( $is_buy_now ) {
				if ( 'popup-checkout' === $redirect_location && 'yes' === get_option( 'wbnb_reset_cart', 'no' ) ) {
					WC()->cart->empty_cart();
				}
			}

			$added = false;

			if ( $variation_id && $product->is_type( 'variable' ) ) {
				// For Variable Product
				if ( isset( $_POST['variation'] ) && is_array( $_POST['variation'] ) ) {
					foreach ( $_POST['variation'] as $name => $value ) {
						if ( substr( $name, 0, 10 ) === 'attribute_' ) {
							$variation[ sanitize_text_field( $name ) ] = sanitize_text_field( $value );
						}
					}
				}

				WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

				$added = true;
			} elseif ( $product->is_type( 'grouped' ) ) {
				// For Grouped Product
				if ( isset( $_POST['quantities'] ) && is_array( $_POST['quantities'] ) ) {
					foreach ( $_POST['quantities'] as $child_product_id => $child_quantity ) {
						$child_product_id = absint( $child_product_id );
						$child_quantity   = absint( $child_quantity );

						if ( $child_quantity > 0 ) {
							WC()->cart->add_to_cart( $child_product_id, $child_quantity );

							$added = true;
						}
					}
				}
			} elseif ( $product->is_type( 'simple' ) ) {
				// For Simple Product
				WC()->cart->add_to_cart( $product_id, $quantity );
				$added = true;
			}

			if ( $added ) {
				do_action( 'woocommerce_ajax_added_to_cart', $product_id );

				$data = array(
					'message'      => esc_html__( 'Product added to cart.', 'woo-buy-now-button' ),
					'redirect_url' => $this->button_redirect_location( $product_id ),
				);

				// If it's a "Buy Now" request we might want to return fragments for mini-cart updates
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
					$data['fragments'] = apply_filters( 'woocommerce_add_to_cart_fragments', array() );
					$data['cart_hash'] = apply_filters( 'woocommerce_add_to_cart_hash', WC()->cart->get_cart_hash(), WC()->cart->get_cart_hash() );
				}

				// Add checkout template if using popup mode
				if ( $is_buy_now && 'popup-checkout' === $redirect_location ) {
					if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
						define( 'WOOCOMMERCE_CHECKOUT', true );
					}

					ob_start();
					$checkout_html = do_shortcode( '[woocommerce_checkout]' );
					ob_end_clean();

					$data['checkout_template'] = $checkout_html;
				}

				wp_send_json_success( $data );
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to add product to cart.', 'woo-buy-now-button' ) ) );
			}
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
		 * Buy Now Button Shortcode for Single Product Page
		 */
		public function buy_now_button_single_shortcode( $atts ) {
			$atts = shortcode_atts( array(
				'product_id' => ''
			), $atts, 'woo_buy_now_button_single' );

			if ( ! empty( $atts['product_id'] ) ) {
				global $product;

				$original_product = $product;
				$product          = wc_get_product( absint( $atts['product_id'] ) );

				if ( ! $product ) {
					return '';
				}
			}

			ob_start();

			echo '<form class="cart" action="' . esc_url( get_the_permalink() ) . '" method="post" enctype="multipart/form-data">';
			$this->buy_now_button_single();
			echo '</form>';

			$output = ob_get_clean();

			// Restore original product if we changed it
			if ( ! empty( $atts['product_id'] ) && isset( $original_product ) ) {
				$product = $original_product;
			}

			return $output;
		}

		/**
		 * Buy Now Button Shortcode for Archive Product
		 */
		public function buy_now_button_archive_shortcode( $atts ) {
			$atts = shortcode_atts( array(
				'product_id' => ''
			), $atts, 'woo_buy_now_button_archive' );

			if ( ! empty( $atts['product_id'] ) ) {
				global $product;
				$original_product = $product;
				$product          = wc_get_product( absint( $atts['product_id'] ) );

				if ( ! $product ) {
					return '';
				}
			}

			ob_start();
			$this->buy_now_button_archive();
			$output = ob_get_clean();

			// Restore original product if we changed it
			if ( ! empty( $atts['product_id'] ) && isset( $original_product ) ) {
				$product = $original_product;
			}

			return $output;
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

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			if ( apply_filters( 'woo_buy_now_button_is_disable', false, $product ) ) {
				return;
			}

			$product_id        = $product->get_ID();
			$wp_button_class   = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';
			$button_class      = apply_filters( 'woo_buy_now_button_class_single', 'wc-buy-now-btn wc-buy-now-btn-single single_add_to_cart_button button alt' . $wp_button_class, $product_id, $wp_button_class );
			$button_text       = apply_filters( 'woo_buy_now_button_text_single', get_option( 'wbnb_button_text', 'Buy Now' ), $product_id );
			$redirect_location = apply_filters( 'woo_buy_now_redirect_location', get_option( 'wbnb_redirect_location', 'checkout' ), $product_id );
			$custom_url        = apply_filters( 'woo_buy_now_redirect_custom_url', get_option( 'wbnb_custom_redirect_url', '' ), $product_id );

			do_action( 'woo_buy_now_button_single_before_load', $product );

			if ( ! empty( $custom_url ) && 'custom' === $redirect_location ) {
				// For custom link
				return printf( '<a href="%s" target="_blank" class="%s" data-wc-buy-now="true" rel="nofollow">%s</a>',
					esc_url( $custom_url ),
					esc_attr( $button_class ),
					esc_html__( $button_text, 'woo-buy-now-button' )
				);
			}

			return printf( '<button type="submit" name="wc-quick-buy-now" value="%d" class="%s" data-wc-buy-now="true" data-redirect-location="%s" data-product_type="%s">%s</button>',
				$product_id,
				esc_attr( $button_class ),
				esc_attr( $redirect_location ),
				esc_attr( $product->get_type() ),
				esc_html__( $button_text, 'woo-buy-now-button' )
			);
		}

		/**
		 * Button Markup for Shop/Archive Page
		 */
		public function buy_now_button_archive() {
			global $product;

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			if ( apply_filters( 'woo_buy_now_button_is_disable', false, $product ) ) {
				return;
			}

			if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return;
			}

			$product_id        = $product->get_ID();
			$wp_button_class   = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';
			$button_class      = apply_filters( 'woo_buy_now_button_class_archive', 'wc-buy-now-btn wc-buy-now-btn-archive button add_to_cart_button' . $wp_button_class, $product_id, $wp_button_class );
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

			// Only process simple products
			if ( $product->is_type( 'simple' ) ) {
				// For custom link
				if ( ! empty( $custom_url ) && 'custom' === $redirect_location ) {
					return printf( '<a href="%s" target="_blank" data-quantity="%s" class="%s" data-product_id="%s" data-wc-buy-now="true" data-redirect-location="%s" data-product_type="%s" rel="nofollow">%s</a>',
						esc_url( $custom_url ),
						intval( $quantity ),
						esc_attr( $button_class ),
						$product_id,
						esc_attr( $redirect_location ),
						esc_attr( $product->get_type() ),
						esc_html__( $button_text, 'woo-buy-now-button' )
					);
				}

				// Auto reset cart before buy now handled in AJAX or link redirect

				$redirect_url = $this->button_redirect_location( $product_id );

				$redirect_url = add_query_arg(
					array(
						'wc-quick-buy-now' => $product_id,
						'quantity'         => intval( $quantity )
					),
					$redirect_url
				);

				return printf( '<a href="%s" data-quantity="%s" class="%s" data-product_id="%s" data-wc-buy-now="true" data-redirect-location="%s" data-product_type="%s" rel="nofollow">%s</a>',
					esc_url( $redirect_url ),
					intval( $quantity ),
					esc_attr( $button_class ),
					$product_id,
					esc_attr( $redirect_location ),
					esc_attr( $product->get_type() ),
					esc_html__( $button_text, 'woo-buy-now-button' )
				);
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

			$quantity     = isset( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1;
			$product_id   = isset( $_REQUEST['wc-quick-buy-now'] ) ? absint( $_REQUEST['wc-quick-buy-now'] ) : '';
			$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( $_REQUEST['variation_id'] ) : '';
			$variation    = [];
			$redirect_url = $this->button_redirect_location( $product_id );

			if ( ! $product_id ) {
				return false;
			}

			// Get product object to check type
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return false;
			}

			// Check if product type is supported
			if ( ! $this->is_product_type_supported( $product->get_type() ) ) {
				return false;
			}

			// Auto reset cart before buy now
			if ( 'yes' == get_option( 'wbnb_reset_cart', 'no' ) ) {
				WC()->cart->empty_cart();
			}

			if ( $variation_id && $product->is_type( 'variable' ) ) {
				// For Variable Product
				if ( isset( $_REQUEST ) && ! empty( $_REQUEST ) ) {
					foreach ( $_REQUEST as $name => $value ) {
						if ( substr( $name, 0, 10 ) === 'attribute_' ) {
							$variation[ $name ] = sanitize_text_field( $value );
						}
					}
				}

				if ( 'yes' == get_option( 'wbnb_reset_cart', 'no' ) ) {
					WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
				}
			} else {
				// For Simple Product
				WC()->cart->add_to_cart( $product_id, $quantity );

				// $query_args = array(
				// 	'add-to-cart' => $product_id,
				// 	'product_id'  => $product_id,
				// );

				// // Example: Group Product Quantity
				// if ( is_array( $quantity ) ) {
				// 	$quantity_arg = [];

				// 	error_log(print_r($quantity));

				// 	foreach ( $quantity as $key => $value ) {
				// 		$quantity_arg["quantity[$key]"] = $value;
				// 	}

				// 	$query_args = array_merge( $query_args, $quantity_arg );
				// } else {
				// 	$query_args = array_merge( $query_args, array( 'quantity' => $quantity ) );
				// }

				// $redirect_url = add_query_arg( $query_args, $redirect_url );
			}

			wp_safe_redirect( $redirect_url );
			exit;
		}

		/**
		 * Check if button should be disabled based on product type
		 */
		public function is_button_disabled_by_product_type( $disabled, $product ) {
			if ( $disabled ) {
				return $disabled; // Already disabled by other filters
			}

			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return true;
			}

			$allowed_product_types = $this->get_allowed_product_types();

			// If product type is not in allowed types, disable the button
			if ( ! in_array( $product->get_type(), $allowed_product_types ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Get allowed product types for buy now button
		 */
		protected function get_allowed_product_types() {
			$allowed_types = get_option( 'wbnb_enable_product_types', array( 'simple', 'variable' ) );

			if ( ! is_array( $allowed_types ) ) {
				$allowed_types = array( 'simple', 'variable' );
			}

			// Free version restriction: Only 'simple' and 'variable'
			if ( ! function_exists( 'woo_buy_now_button_pro' ) ) {
				$allowed_types = array_intersect( $allowed_types, array( 'simple', 'variable' ) );
			}

			return $allowed_types;
		}

		/**
		 * Check if product type is supported
		 */
		public function is_product_type_supported( $product_type ) {
			$allowed_types = $this->get_allowed_product_types();

			return in_array( $product_type, $allowed_types );
		}
	}
}