/******/ (() => { // webpackBootstrap
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other entry modules.
(() => {
/*!*************************!*\
  !*** ./src/js/admin.js ***!
  \*************************/
(function ($) {
  $(function () {
    // Global custom redirect URL field Hide/Show
    $('input[type=radio][name=wbnb_redirect_location]').on('change', function () {
      if ('custom' === $(this).val()) {
        $(this).closest('tr').next('tr').show();
      } else {
        $(this).closest('tr').next('tr').hide();
      }
    }).trigger('change');

    // Product level custom redirect URL field Hide/Show
    $('select#buy_now_redirect_location').on('change', function () {
      if ('custom' === $(this).val()) {
        $(this).closest('p').next('p').show();
      } else {
        $(this).closest('p').next('p').hide();
      }
    }).trigger('change');

    // Pro option's style implement
    if ($('.woo-buy-now-button-form-table tr, #woo-buy-now-button-pro-options').hasClass('is-pro')) {
      $('.woo-buy-now-button-form-table tr.is-pro input, .woo-buy-now-button-form-table tr.is-pro select').prop('disabled', true);
      // $('.woo-buy-now-button-form-table tr.is-pro input, .woo-buy-now-button-form-table tr.is-pro select').prop('disabled', true).after('<a href="//wpxpress.net/products/quick-buy-now-button-for-woocommerce" target="_blank" class="upgrade-to-pro" style="display: inline-block; color: #d63638; font-size: 11px; text-decoration: none; margin-left: 10px; font-weight: 600;">Upgrade To PRO &#8594;</a>');
      $('#woo-buy-now-button-pro-options p.form-field input, #woo-buy-now-button-pro-options p.form-field select').prop('disabled', true);
      $('#woo-buy-now-button-pro-options p.form-field').prop('disabled', true).append('<a href="//wpxpress.net/products/quick-buy-now-button-for-woocommerce" target="_blank" class="upgrade-to-pro" style="display: inline-block; color: #d63638; font-size: 11px; text-decoration: none; margin-left: 10px; font-weight: 600;">Upgrade To PRO &#8594;</a>');
      // $('.woo-buy-now-button-form-table tr.is-pro > th').append('<span class="pro-option-badge" style="background: #d63638; color: #fff; display: inline-block; font-size: 10px; padding: 3px 6px; border-radius: 3px; margin-left: 5px;">PRO</span>');
    }
  });
})(jQuery);
})();

// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!*****************************!*\
  !*** ./src/scss/admin.scss ***!
  \*****************************/
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin

})();

/******/ })()
;
//# sourceMappingURL=admin.js.map