(function ($) {
	$( function() {

		// Global custom redirect URL field Hide/Show
		$( 'select#wbnb_redirect_location' ).on( 'change', function() {
			if ( 'custom' === $( this ).val() ) {
				$( this ).closest('tr').next( 'tr' ).show();
			} else {
				$( this ).closest('tr').next( 'tr' ).hide();
			}
		}).trigger( 'change' );

		// Product level custom redirect URL field Hide/Show
		$( 'select#buy_now_redirect_location' ).on( 'change', function() {
			if ( 'custom' === $( this ).val() ) {
				$( this ).closest('p').next( 'p' ).show();
			} else {
				$( this ).closest('p').next( 'p' ).hide();
			}
		}).trigger( 'change' );

		// Pro option's style implement
		if ( $('.woo-buy-now-button-form-table tr, #woo-buy-now-button-pro-options').hasClass('is-pro') ) {
			$('.woo-buy-now-button-form-table tr.is-pro input, .woo-buy-now-button-form-table tr.is-pro select').prop('disabled', true).after('<a href="//wpxpress.net/products/quick-buy-now-button-for-woocommerce" target="_blank" class="upgrade-to-pro" style="display: inline-block; color: #f65858; font-size: 11px; text-decoration: none; margin-left: 10px; font-weight: 600;">UPGRADE TO PRO &#8594;</a>');
			$('#woo-buy-now-button-pro-options p.form-field input, #woo-buy-now-button-pro-options p.form-field select').prop('disabled', true);
			$('#woo-buy-now-button-pro-options p.form-field').prop('disabled', true).append('<a href="//wpxpress.net/products/quick-buy-now-button-for-woocommerce" target="_blank" class="upgrade-to-pro" style="display: inline-block; color: #f65858; font-size: 11px; text-decoration: none; margin-left: 10px; font-weight: 600;">UPGRADE TO PRO &#8594;</a>');
			// $('.woo-buy-now-button-form-table tr.is-pro > th').append('<span class="pro-option-badge" style="background: #f65858; color: #fff; display: inline-block; font-size: 10px; padding: 3px 6px; border-radius: 3px; margin-left: 5px;">PRO</span>');
		}
	});
})(jQuery);