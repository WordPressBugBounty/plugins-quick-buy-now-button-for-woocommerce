<?php 
defined( 'ABSPATH' ) || exit;

/**
* Settings for API.
*/
if ( class_exists( 'Woo_Buy_Now_Button_Settings' ) ) {
	return new Woo_Buy_Now_Button_Settings();
}

class Woo_Buy_Now_Button_Settings extends WC_Settings_Page {
	public function __construct() {
		$this->id    = 'woo-buy-now-button';
		$this->label = esc_html__( 'Buy Now Button', 'woo-buy-now-button' );

		parent::__construct();
	}

    /**
	 * Get own sections.
	 *
	 * @return array
	 */
	protected function get_own_sections() {
		return array(
			''       => __( 'General', 'woo-buy-now-button' ),
			'button_styles' => __( 'Button Styles', 'woo-buy-now-button' )
		);
	}

	public function output() {
		global $current_section, $hide_save_button;
		
        $settings = $this->get_settings( $current_section );
        $this->output_fields( $settings );
	}

    public function get_settings_for_default_section() {
        $settings = array(
            array(
                'type' => 'title',
                'id'   => 'woo_buy_now_button_default_options',
                'title' => esc_html__( 'Quick Buy Now Button Settings', 'woo-buy-now-button' ),
                'desc'  => '<p>' . esc_html__( 'The following options control the "Quick Buy Now Button for WooCommerce" extension.', 'woo-buy-now-button' ) . '</p>'
                . '<p>' . sprintf('<a href="%1$s" target="_blank">%2$s</a>', esc_url('https://wpxpress.net/docs/quick-buy-now-button-for-woocommerce/'), esc_html__( 'Documentation', 'woo-buy-now-button' ) ) . ' | '
                . sprintf('<a href="%1$s" target="_blank">%2$s</a>', esc_url('https://wpxpress.net/submit-ticket/'), esc_html__( 'Get Help &amp; Support', 'woo-buy-now-button' ) )
                . $this->get_pro_link_html() . ' | ' 
                .sprintf('<a href="%1$s" target="_blank" style="text-decoration: none; font-weight:bold;">%2$s</a>', esc_url( 'https://wordpress.org/support/plugin/quick-buy-now-button-for-woocommerce/reviews/#new-post' ), esc_html__( 'Leave a review here ⭐️⭐️⭐️⭐️⭐️', 'recaptcha-for-woocommerce' ) ).'</p>',
            ),

            array(
                'id'      => 'wbnb_enable_button_single',
                'type'    => 'checkbox',
                'title'   => esc_html__( 'Enable Button on', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Single Product Page.', 'woo-buy-now-button' ),
                'default' => 'yes',
                'checkboxgroup' => 'start',
            ),

            array(
                'id'      => 'wbnb_enable_button_archive',
                'type'    => 'checkbox',
                'desc'    => esc_html__( 'Shop / Archive Page.', 'woo-buy-now-button' ),
                'default' => 'yes',
                'checkboxgroup' => 'end',
            ),

            array(
                'id'       => 'wbnb_button_position_single',
                'type'     => 'select',
                'class'    => 'wc-enhanced-select',
                'title'    => esc_html__( 'Button Position on Single', 'woo-buy-now-button' ),
                'desc_tip' => esc_html__( 'Select position where button will show on single product page.', 'woo-buy-now-button' ),
                'default'  => 'after_add_to_cart',
                'options'  => array(
                    'before_add_to_cart'    => esc_html__( 'Before Add to Cart Button', 'woo-buy-now-button' ),
                    'after_add_to_cart' => esc_html__( 'After Add to Cart Button', 'woo-buy-now-button' ),
                ),
            ),

            array(
                'title'    => esc_html__( 'Button Position on Shop', 'woo-buy-now-button' ),
                'id'       => 'wbnb_button_position_archive',
                'type'     => 'select',
                'default'  => 'after_add_to_cart',
                'class'    => 'wc-enhanced-select',
                'desc_tip' => esc_html__( 'Select position where button will show on shop and archive page.', 'woo-buy-now-button' ),
                'options'  => array(
                    'before_add_to_cart'    => esc_html__( 'Before Add to Cart Button', 'woo-buy-now-button' ),
                    'after_add_to_cart' => esc_html__( 'After Add to Cart Button', 'woo-buy-now-button' ),
                ),
            ),

            array(
                'id'       => 'wbnb_redirect_location',
                'type'     => 'radio',
                'title'    => esc_html__( 'Redirect Location', 'woo-buy-now-button' ),
                'desc_tip' => esc_html__( 'Select redirect location to after click buy now button.', 'woo-buy-now-button' ),
                // 'class'    => 'wc-enhanced-select',
                'default'  => 'checkout',
                'options'  => array(
                    'checkout'          => esc_html__( 'Checkout Page', 'woo-buy-now-button' ),
                    'cart'				=> esc_html__( 'Cart Page', 'woo-buy-now-button' ),
                    'custom'			=> esc_html__( 'Custom Page', 'woo-buy-now-button' ),
                    // 'popup-cart'		=> esc_html__( 'Popup Cart', 'woo-buy-now-button' ),
                    // 'popup-checkout'	=> esc_html__( 'Popup Checkout', 'woo-buy-now-button' ),
                ),
            ),

            array(
                'id'      => 'wbnb_custom_redirect_url',
                'type'    => 'url',
                'title'   => esc_html__( 'Custom Redirect URL', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set global custom URL to redirect.', 'woo-buy-now-button' ),
            ),

            array(
                'id'      => 'wbnb_button_text',
                'type'    => 'text',
                'title'   => esc_html__( 'Button Text', 'woo-buy-now-button' ),
                'default' => 'Buy Now',
            ),

            array(
                'id'      => 'wbnb_default_qnt',
                'type'    => 'number',
                'title'   => esc_html__( 'Default Shop Quantity', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set quantity number to be added to the cart when clicked on the Buy Now button from shop page.', 'woo-buy-now-button' ),
                'css'     => 'width:60px;',
                'default' => '1',
                'custom_attributes' => array(
                    'min'  => 1,
                    'step' => 1,
                ),
            ),

            array(
                'id'      => 'wbnb_reset_cart',
                'type'    => 'checkbox',
                'title'   => esc_html__( 'Auto Reset Cart', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Clear the cart when clicked Buy Now button.', 'woo-buy-now-button' ),
                'default' => 'no'
            ),

            array(
                'id'      => 'wbnb_hide_add_to_cart',
                'type'    => 'checkbox',
                'title'   => esc_html__( 'Hide Add To Cart', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Hide Add To Cart Button from Single Product and Shop Page.', 'woo-buy-now-button' ),
                'default' => 'no',
                'is_pro'  => true,
            ),

            array(
                'id'      => 'wbnb_disabled_product_type',
                'type'    => 'multiselect',
                'title'   => esc_html__( 'Disable on Product Types', 'woo-buy-now-button' ),
                'desc' => esc_html__( 'Disable Buy Now Button Based on Specific Product Types.', 'woo-buy-now-button' ),
                'class'   => 'wc-enhanced-select',
                'default' => array(''),
                'options' => wc_get_product_types(),
                'custom_attributes' => array(
                    'data-placeholder'=> esc_html__( 'Choose specific product type(s).', 'woo-buy-now-button' ),
                ),
                'is_pro' => true,
            ),

            array(
                'id'      => 'wbnb_disabled_categories',
                'type'    => 'multiselect',
                'title'   => esc_html__( 'Disable on Categories', 'woo-buy-now-button' ),
                'desc'    => 'Disable Buy Now Button Based on Specific Categories.',
                'class'    => 'wc-enhanced-select',
                'default'  => 'all',
                'options'  => $this->get_product_category_id_name_array(),
                'custom_attributes' => array(
                    'data-placeholder' => esc_html__( 'Choose specific category(s).', 'woo-buy-now-button' ),
                ),
                'is_pro' => true,
            ),

            array(
                'id'       => 'wbnb_disabled_products',
                'type'     => 'multiselect',
                'title'    => esc_html__( 'Disable on Products', 'woo-buy-now-button' ),
                'desc'     => 'Disable Buy Now Button Based on Specific Products.',
                'class'    => 'wc-enhanced-select',
                'default'  => '',
                'options'  => $this->get_product_id_name_array(),
                'custom_attributes' => array(
                    'data-placeholder' => esc_html__( 'Choose specific product(s).', 'woo-buy-now-button' ),
                ),
                'is_pro'  => true,
            ),

            // Section End
            array(
                'type' => 'sectionend',
                'id'   => 'woo_buy_now_button_default_options'
            ),
        );

        $settings = apply_filters( 'woo_buy_now_button_default_settings_fields', $settings );
        return apply_filters( 'woo_buy_now_button_default_settings', $settings );
    }

    public function get_settings_for_button_styles_section() {
        $settings = array(
            array(
                'type' => 'title',
                'id'   => 'woo_buy_now_button_styles_options',
                'title' => esc_html__( 'Buy Now Button Styles', 'woo-buy-now-button' ),
            ),

            array(
                'id'      => 'wbnb_button_style',
                'type'    => 'radio',
                //'class'   => 'wc-enhanced-select',
                'title'   => esc_html__( 'Button Styles', 'woo-buy-now-button' ),
                'desc_tip'    => esc_html__( 'Choose button style.', 'woo-buy-now-button' ),
                'default' => 'default',
                'options'  => array(
                    'default'   => esc_html__( 'Default Style', 'woo-buy-now-button' ),
                    'custom'    => esc_html__( 'Custom Style', 'woo-buy-now-button' ),
                ),
            ),

            array(
                'id'      => 'wbnb_button_color',
                'type'    => 'color',
                'title'   => esc_html__( 'Text Color', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set button text color.', 'woo-buy-now-button' ),
                'default' => ''
            ),

            array(
                'id'      => 'wbnb_button_background',
                'type'    => 'color',
                'title'   => esc_html__( 'Text Background Color', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set button background color.', 'woo-buy-now-button' ),
                'default' => ''

            ),

            array(
                'id'      => 'wbnb_button_border_color',
                'type'    => 'color',
                'title'   => esc_html__( 'Border Color', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set button border color.', 'woo-buy-now-button' ),
                'default' => ''

            ),

            array(
                'id'      => 'wbnb_button_border_size',
                'type'    => 'number',
                'title'   => esc_html__( 'Border size', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set button border size in px.', 'woo-buy-now-button' ),
                'css'     => 'width:60px;',
                'custom_attributes' => array(
                    'min'  => 1,
                    'max'  => 100,
                    'step' => 1,
                ),
                'default' => ''
            ),

            array(
                'id'      => 'wbnb_button_border_radius',
                'type'    => 'number',
                'title'   => esc_html__( 'Border Radius', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set button border radius in px.', 'woo-buy-now-button' ),
                'css'     => 'width:60px;',
                'custom_attributes' => array(
                    'min'  => 1,
                    'max'  => 10,
                    'step' => 1,
                ),
                'default' => ''
            ),

            array(
                'id'      => 'wbnb_button_font_size',
                'type'    => 'number',
                'title'   => esc_html__( 'Font size', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set font size in px.', 'woo-buy-now-button' ),
                'css'     => 'width:60px;',
                'custom_attributes' => array(
                    'min'  => 1,
                    'step' => 1,
                ),
                'default' => ''
            ),

            array(
                'id'      => 'wbnb_button_margin',
                'type'    => 'dimensions',
                'title'   => esc_html__( 'Margin', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set margin value in px.', 'woo-buy-now-button' ),
                'default' => array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' )
            ),

            array(
                'id'      => 'wbnb_button_padding',
                'type'    => 'dimensions',
                'title'   => esc_html__( 'Padding', 'woo-buy-now-button' ),
                'desc'    => esc_html__( 'Set padding value in px.', 'woo-buy-now-button' ),
                'default' => array( 'top' => '', 'right' => '', 'bottom' => '', 'left' => '' )
            ),

            // Section End
            array(
                'type' => 'sectionend',
                'id'   => 'woo_buy_now_button_styles_options'
            ),
        );

        $settings = apply_filters( 'woo_buy_now_button_styles_settings_fields', $settings );
        return apply_filters( 'woo_buy_now_button_styles_settings', $settings );
    }
		
    public function output_fields( $options ) {
        foreach ( $options as $value ) {
            if ( ! isset( $value['type'] ) ) {
                continue;
            }
            if ( ! isset( $value['id'] ) ) {
                $value['id'] = '';
            }
            if ( ! isset( $value['title'] ) ) {
                $value['title'] = isset( $value['name'] ) ? $value['name'] : '';
            }
            if ( ! isset( $value['class'] ) ) {
                $value['class'] = '';
            }
            if ( ! isset( $value['css'] ) ) {
                $value['css'] = '';
            }
            if ( ! isset( $value['default'] ) ) {
                $value['default'] = '';
            }
            if ( ! isset( $value['desc'] ) ) {
                $value['desc'] = '';
            }
            if ( ! isset( $value['desc_tip'] ) ) {
                $value['desc_tip'] = false;
            }
            if ( ! isset( $value['placeholder'] ) ) {
                $value['placeholder'] = '';
            }
            if ( ! isset( $value['suffix'] ) ) {
                $value['suffix'] = '';
            }
            if ( ! isset( $value['is_pro'] ) ) {
                $value['is_pro'] = false;
            }

            if ( ! isset( $value['value'] ) ) {
                $value['value'] = WC_Admin_Settings::get_option( $value['id'], $value['default'] );
            }

            $classes = array();

            if ( ! function_exists( 'woo_buy_now_button_pro' ) ) {
                if( $value['is_pro'] ){
                    $classes[] = 'is-pro';
                }
            }

            $class =  implode( ' ', array_values( array_unique( $classes ) ) );

            // Custom attribute handling.
            $custom_attributes = array();

            if ( ! empty( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) {
                foreach ( $value['custom_attributes'] as $attribute => $attribute_value ) {
                    $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
                }
            }

            $custom_attributes = implode( ' ', $custom_attributes );

            // Description handling.
            $field_description = WC_Admin_Settings::get_field_description( $value );
            $description       = $field_description['description'];
            $tooltip_html      = $field_description['tooltip_html'];

            // Switch based on type.
            switch ( $value['type'] ) {

                // Section Titles.
                case 'title':
                    if ( ! empty( $value['title'] ) ) {
                        echo '<h2>' . esc_html( $value['title'] ) . '</h2>';
                    }
                    if ( ! empty( $value['desc'] ) ) {
                        echo '<div id="' . esc_attr( sanitize_title( $value['id'] ) ) . '-description">';
                        echo wp_kses_post( wpautop( wptexturize( $value['desc'] ) ) );
                        echo '</div>';
                    }
                    echo '<table class="form-table woo-buy-now-button-form-table">' . "\n\n";
                    if ( ! empty( $value['id'] ) ) {
                        do_action( 'woocommerce_settings_' . sanitize_title( $value['id'] ) );
                    }
                    break;

                // Section Ends.
                case 'sectionend':
                    if ( ! empty( $value['id'] ) ) {
                        do_action( 'woocommerce_settings_' . sanitize_title( $value['id'] ) . '_end' );
                    }
                    echo '</table>';
                    if ( ! empty( $value['id'] ) ) {
                        do_action( 'woocommerce_settings_' . sanitize_title( $value['id'] ) . '_after' );
                    }
                    break;

                // Standard text inputs and subtypes like 'number'.
                case 'text':
                case 'password':
                case 'datetime':
                case 'datetime-local':
                case 'date':
                case 'month':
                case 'time':
                case 'week':
                case 'number':
                case 'email':
                case 'url':
                case 'tel':
                    $option_value = $value['value'];

                    ?><tr class="<?php echo esc_attr( $class ) ?>" valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                            <input
                                name="<?php echo esc_attr( $value['id'] ); ?>"
                                id="<?php echo esc_attr( $value['id'] ); ?>"
                                type="<?php echo esc_attr( $value['type'] ); ?>"
                                style="<?php echo esc_attr( $value['css'] ); ?>"
                                value="<?php echo esc_attr( $option_value ); ?>"
                                class="<?php echo esc_attr( $value['class'] ); ?>"
                                placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                <?php printf( $custom_attributes ); ?>
                                /><span class="suffix"><?php echo esc_html( $value['suffix'] ); ?></span> <?php echo wp_kses_post( $description ); ?>
                        </td>
                    </tr>
                    <?php
                    break;

                // Color picker.
                case 'color':
                    $option_value = $value['value'];

                    ?>
                    <tr class="<?php echo esc_attr( $class ) ?>" valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">&lrm;
                            <span class="colorpickpreview" style="background: <?php echo esc_attr( $option_value ); ?>">&nbsp;</span>
                            <input
                                name="<?php echo esc_attr( $value['id'] ); ?>"
                                id="<?php echo esc_attr( $value['id'] ); ?>"
                                type="text"
                                dir="ltr"
                                style="<?php echo esc_attr( $value['css'] ); ?> width: 80px;"
                                value="<?php echo esc_attr( $option_value ); ?>"
                                class="<?php echo esc_attr( $value['class'] ); ?>colorpick"
                                placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                <?php printf( $custom_attributes ); ?>
                                />&lrm; <?php echo wp_kses_post( $description ); ?>
                                <div id="colorPickerDiv_<?php echo esc_attr( $value['id'] ); ?>" class="colorpickdiv" style="z-index: 100;background:#eee;border:1px solid #ccc;position:absolute;display:none;"></div>
                        </td>
                    </tr>
                    <?php
                    break;

                // Textarea.
                case 'textarea':
                    $option_value = $value['value'];

                    ?>
                    <tr class="<?php echo esc_attr( $class ) ?>" valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                            <?php echo wp_kses_post( $description ); ?>

                            <textarea
                                name="<?php echo esc_attr( $value['id'] ); ?>"
                                id="<?php echo esc_attr( $value['id'] ); ?>"
                                style="<?php echo esc_attr( $value['css'] ); ?>"
                                class="<?php echo esc_attr( $value['class'] ); ?>"
                                placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                <?php printf( $custom_attributes ); ?>
                                ><?php echo esc_textarea( $option_value ); // WPCS: XSS ok. ?></textarea>
                        </td>
                    </tr>
                    <?php
                    break;

                // Select boxes.
                case 'select':
                case 'multiselect':
                    $option_value = $value['value'];

                    ?>
                    <tr class="<?php echo esc_attr( $class ) ?>" valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                            <select
                                name="<?php echo esc_attr( $value['id'] ); ?><?php echo ( 'multiselect' === $value['type'] ) ? '[]' : ''; ?>"
                                id="<?php echo esc_attr( $value['id'] ); ?>"
                                style="<?php echo esc_attr( $value['css'] ); ?>"
                                class="<?php echo esc_attr( $value['class'] ); ?>"
                                <?php printf( $custom_attributes ); ?>
                                <?php echo 'multiselect' === $value['type'] ? 'multiple="multiple"' : ''; ?>
                                >
                                <?php
                                foreach ( $value['options'] as $key => $val ) {
                                    ?>
                                    <option value="<?php echo esc_attr( $key ); ?>"
                                        <?php

                                        if ( is_array( $option_value ) ) {
                                            selected( in_array( (string) $key, $option_value, true ), true );
                                        } else {
                                            selected( $option_value, (string) $key );
                                        }

                                        ?>
                                    ><?php echo esc_html( $val ); ?></option>
                                    <?php
                                }
                                ?>
                            </select><span class="suffix"><?php echo esc_html( $value['suffix'] ); ?></span> <?php echo wp_kses_post( $description ); ?>
                        </td>
                    </tr>
                    <?php
                    break;

                // Radio inputs.
                case 'radio':
                    $option_value = $value['value'];

                    ?>
                    <tr class="<?php echo esc_attr( $class ) ?>" valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
                        </th>
                        <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                            <fieldset>
                                <?php echo wp_kses_post( $description ); ?>
                                <ul>
                                <?php
                                foreach ( $value['options'] as $key => $val ) {
                                    ?>
                                    <li>
                                        <label><input
                                            name="<?php echo esc_attr( $value['id'] ); ?>"
                                            value="<?php echo esc_attr( $key ); ?>"
                                            type="radio"
                                            style="<?php echo esc_attr( $value['css'] ); ?>"
                                            class="<?php echo esc_attr( $value['class'] ); ?>"
                                            <?php printf( $custom_attributes ); ?>
                                            <?php checked( $key, $option_value ); ?>
                                            /> <?php echo esc_html( $val ); ?></label>
                                    </li>
                                    <?php
                                }
                                ?>
                                </ul>
                            </fieldset>
                        </td>
                    </tr>
                    <?php
                    break;

                // Checkbox input.
                case 'checkbox':
                    $option_value     = $value['value'];
                    $visibility_class = array();

                    if ( ! isset( $value['hide_if_checked'] ) ) {
                        $value['hide_if_checked'] = false;
                    }
                    if ( ! isset( $value['show_if_checked'] ) ) {
                        $value['show_if_checked'] = false;
                    }
                    if ( 'yes' === $value['hide_if_checked'] || 'yes' === $value['show_if_checked'] ) {
                        $visibility_class[] = 'hidden_option';
                    }
                    if ( 'option' === $value['hide_if_checked'] ) {
                        $visibility_class[] = 'hide_options_if_checked';
                    }
                    if ( 'option' === $value['show_if_checked'] ) {
                        $visibility_class[] = 'show_options_if_checked';
                    }

                    if ( ! isset( $value['checkboxgroup'] ) || 'start' === $value['checkboxgroup'] ) {
                        ?>
                            <tr valign="top" class="<?php echo esc_attr( implode( ' ', $visibility_class ) ); ?> <?php echo esc_attr( $class ) ?>">
                                <th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
                                <td class="forminp forminp-checkbox">
                                    <fieldset>
                        <?php
                    } else {
                        ?>
                            <fieldset class="<?php echo esc_attr( implode( ' ', $visibility_class ) ); ?>">
                        <?php
                    }

                    if ( ! empty( $value['title'] ) ) {
                        ?>
                            <legend class="screen-reader-text"><span><?php echo esc_html( $value['title'] ); ?></span></legend>
                        <?php
                    }

                    ?>
                        <label for="<?php echo esc_attr( $value['id'] ); ?>">
                            <input
                                name="<?php echo esc_attr( $value['id'] ); ?>"
                                id="<?php echo esc_attr( $value['id'] ); ?>"
                                type="checkbox"
                                class="<?php echo esc_attr( isset( $value['class'] ) ? $value['class'] : '' ); ?>"
                                value="1"
                                <?php checked( $option_value, 'yes' ); ?>
                                <?php printf( $custom_attributes ); ?>
                            /> <?php echo wp_kses_post( $description ); ?>
                        </label> <?php echo wp_kses_post( $tooltip_html ); ?>
                    <?php

                    if ( ! isset( $value['checkboxgroup'] ) || 'end' === $value['checkboxgroup'] ) {
                        ?>
                                    </fieldset>
                                </td>
                            </tr>
                        <?php
                    } else {
                        ?>
                            </fieldset>
                        <?php
                    }
                    break;

                case 'dimensions':
                    $option_value = $value['value'];
                    ?>

                    <tr class="<?php echo esc_attr( $class ) ?>" valign="top">
                        <th scope="row" class="titledesc">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); ?></label>
                        </th>

                        <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                            <label for="<?php echo esc_attr( $value['id'] ); ?>_top" style="display: inline-block">Top
                                <input
                                    name="<?php echo esc_attr( $value['id'] ); ?>[top]"
                                    id="<?php echo esc_attr( $value['id'] ); ?>_top"
                                    type="number"
                                    style="width: 60px; display: block"
                                    value="<?php echo esc_attr( $option_value['top'] ); ?>"
                                    class="<?php echo esc_attr( $value['class'] ); ?>"
                                    placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                    step="1"
                                    min="0"
                                />
                            </label>

                            <label for="<?php echo esc_attr( $value['id'] ); ?>_right" style="display: inline-block">Right
                                <input
                                    name="<?php echo esc_attr( $value['id'] ); ?>[right]"
                                    id="<?php echo esc_attr( $value['id'] ); ?>_right"
                                    type="number"
                                    style="width: 60px; display: block"
                                    value="<?php echo esc_attr( $option_value['right'] ); ?>"
                                    class="<?php echo esc_attr( $value['class'] ); ?>"
                                    placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                    step="1"
                                    min="0"
                                />
                            </label>

                            <label for="<?php echo esc_attr( $value['id'] ); ?>_bottom" style="display: inline-block">Bottom
                                <input
                                    name="<?php echo esc_attr( $value['id'] ); ?>[bottom]"
                                    id="<?php echo esc_attr( $value['id'] ); ?>_bottom"
                                    type="number"
                                    style="width: 60px; display: block"
                                    value="<?php echo esc_attr( $option_value['bottom'] ); ?>"
                                    class="<?php echo esc_attr( $value['class'] ); ?>"
                                    placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                    step="1"
                                    min="0"
                                />
                            </label>

                            <label for="<?php echo esc_attr( $value['id'] ); ?>_left" style="display: inline-block">Left
                                <input
                                    name="<?php echo esc_attr( $value['id'] ); ?>[left]"
                                    id="<?php echo esc_attr( $value['id'] ); ?>_left"
                                    type="number"
                                    style="width: 60px; display: block"
                                    value="<?php echo esc_attr( $option_value['left'] ); ?>"
                                    class="<?php echo esc_attr( $value['class'] ); ?>"
                                    placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
                                    step="1"
                                    min="0"
                                />
                            </label>

                            <?php echo wp_kses_post( $description ); ?>
                        </td>
                    </tr>

                    <?php
                    break;

                // Default: run an action.
                default:
                    do_action( 'woocommerce_admin_field_' . $value['type'], $value );
                    do_action( 'woo-buy-now-button_admin_field', $value );
                    break;
            }
        }
    }

    public function save() {
        global $current_section;

        $settings = $this->get_settings( $current_section );
        WC_Admin_Settings::save_fields( $settings );

        if ( $current_section ) {
            do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
            do_action( 'woocommerce_update_options_woo-buy-now-button', $current_section );
        }
    }

    public function get_product_category_id_name_array() {
        $lists = array();
        $categories = get_categories( array( 'taxonomy' => 'product_cat' ) );

        foreach ( $categories as $category ) {
            $lists[ $category->term_id ] = $category->name;
        }

        return $lists;
    }

    public function get_product_id_name_array() {
        $lists = array();

        $posts = get_posts(
            array(
                'post_type'      => 'product',
                'posts_per_page' => -1,
                'order'          => 'ASC',
                'orderby'        => 'title'
            )
        );

        foreach ( $posts as $post ) {
            $lists[ $post->ID ] = $post->post_title;
        }

        return $lists;
    }

    public function get_pro_link_html() {
        if ( ! function_exists( 'woo_buy_now_button_pro' ) ) {
            $html = ' | ' . sprintf('<a href="%1$s" target="_blank" style="color:#d63638"><b>%2$s</b></a>', esc_url('https://wpxpress.net/products/quick-buy-now-button-for-woocommerce/'), __( 'Get Pro Features', 'woo-buy-now-button' ) );

            return $html;
        }

        return '';
    }
}

return new Woo_Buy_Now_Button_Settings();