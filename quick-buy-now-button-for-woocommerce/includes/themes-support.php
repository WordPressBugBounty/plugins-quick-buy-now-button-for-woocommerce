<?php
defined( 'ABSPATH' ) or die( 'Keep Quit' );

$theme = get_option( 'template' );

if ( 'astra' == $theme ) {
    /**
     * Astra theme compatibility
     */
    function woo_buy_now_button_compatibility_astra() {
        if ( 'no' == get_option( 'wbnb_enable_button_archive', 'yes' ) ) {
            return;
        }

        $class = woo_buy_now_button()->get_frontend();

        if ( 'after_add_to_cart' == get_option( 'wbnb_button_position_archive', 'after_add_to_cart' ) ) {
            remove_action( 'woocommerce_after_shop_loop_item', array( $class, 'buy_now_button_archive' ), 11 );
            add_action( 'astra_woo_shop_add_to_cart_after', array( $class, 'buy_now_button_archive' ) );
        } else {
            remove_action( 'woocommerce_after_shop_loop_item', array( $class, 'buy_now_button_archive' ), 9 );
            add_action( 'astra_woo_shop_add_to_cart_before', array( $class, 'buy_now_button_archive' ) );
        }
    }

    add_action( 'init', 'woo_buy_now_button_compatibility_astra' );
}