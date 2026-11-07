<?php
/**
 * Uninstall: remove analytics data only when the store opted in.
 *
 * Order meta (_wbnb, _wbnb_buy_now, _wbnb_returning) is part of the order history and is kept.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once dirname( __FILE__ ) . '/includes/analytics/class-woo_buy_now_button_analytics_store.php';

/**
 * Remove the analytics data of the current site when the store opted in.
 */
function woo_buy_now_button_uninstall_site() {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'wbnb' );
	}

	// Jobs are gone: let a reinstall schedule them again.
	delete_option( 'wbnb_analytics_jobs' );

	if ( 'yes' !== get_option( 'wbnb_remove_data_on_uninstall', 'no' ) && ! ( defined( 'WBNB_REMOVE_ALL_DATA' ) && WBNB_REMOVE_ALL_DATA ) ) {
		return;
	}

	Woo_Buy_Now_Button_Analytics_Store::drop();
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $woo_buy_now_button_site_id ) {
		switch_to_blog( $woo_buy_now_button_site_id );
		woo_buy_now_button_uninstall_site();
		restore_current_blog();
	}
} else {
	woo_buy_now_button_uninstall_site();
}
