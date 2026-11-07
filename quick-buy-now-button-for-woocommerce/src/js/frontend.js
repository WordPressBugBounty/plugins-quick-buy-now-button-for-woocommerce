/* global wbnb_params */

import { doAction } from '@wordpress/hooks'

(function ($) {
  'use strict'

  /**
   * Initialize on document ready
   */
  $(document).ready(function () {
    handleAjaxAddToCart()
    handleBuyNowButtons()
    handleExternalBuyNowLinks()
    WBNB_Popup.init()

    $('button.wc-buy-now-btn-single').on('click', function (e) {
      // Check if a valid variation is selected before proceeding buy now button
      if (!validateVariation($(this), e)) {
        return;
      }

      // To fix other plugins ajax trigger on buy now button
      if (!wbnb_params.is_popup) {
        e.stopImmediatePropagation();
      }
    });
  })

  /**
   * Check if a valid variation is selected before proceeding
   * @return {boolean} false if validation failed (event stopped), true otherwise
   */
  function validateVariation($button, e) {
    if ($button.is('.disabled')) {
      e.preventDefault();
      e.stopImmediatePropagation();

      if ($button.is('.wc-variation-is-unavailable')) {
        Analytics.send(Analytics.EVENT_CLIENT_BLOCK, $button.val(), $button.data('source'), { result: Analytics.RESULT_UNAVAILABLE_VARIATION })
        window.alert(wbnb_params.i18n_unavailable_text);
      } else if ($button.is('.wc-variation-selection-needed')) {
        Analytics.send(Analytics.EVENT_CLIENT_BLOCK, $button.val(), $button.data('source'), { result: Analytics.RESULT_NEEDS_OPTIONS })
        window.alert(wbnb_params.i18n_make_a_selection_text);
      }
      return false;
    }
    return true;
  }

  /**
   * Fire a wp.hooks action for add-ons; a failing listener must not stop Buy Now
   */
  function fireHook(name, ...args) {
    try {
      doAction(name, ...args)
    } catch (err) {
      console.error(err)
    }
  }

  /**
   * Buy Now analytics: events only the browser sees, stored in the store's own database.
   */
  const Analytics = {
    EVENT_POPUP_CLOSE: 3,
    EVENT_CLIENT_BLOCK: 4,
    EVENT_EXTERNAL_CLICK: 5,
    RESULT_NEEDS_OPTIONS: 6,
    RESULT_UNAVAILABLE_VARIATION: 7,

    /**
     * Send an event without delaying the page (sendBeacon survives navigation)
     */
    send(event, productId, source, extra = {}) {
      if (!wbnb_params.track || !wbnb_params.event_url || !productId) {
        return
      }

      const data = new FormData()
      data.append('event', event)
      data.append('product_id', productId)
      data.append('source', source || '')

      Object.keys(extra).forEach((key) => data.append(key, extra[key]))

      if (navigator.sendBeacon) {
        navigator.sendBeacon(wbnb_params.event_url, data)
      } else {
        $.ajax({ url: wbnb_params.event_url, type: 'POST', data, processData: false, contentType: false })
      }
    },
  }

  /**
   * Data collector for form data
   */
  const DataCollector = {
    /**
     * Collect variation attributes from form
     */
    collectVariations($form) {
      const variation = {}

      $form.find('select[name^="attribute_"], input[name^="attribute_"]').each(function () {
        const name = $(this).attr('name')
        variation[name] = $(this).val()
      })

      return variation
    },

    /**
     * Collect group product quantities
     */
    collectQuantities($form) {
      const quantities = {}

      $form.find('input[name^="quantity"]').each(function () {
        const matches = $(this).attr('name').match(/^quantity\[(\d+)]$/)
        if (matches?.[1]) {
          quantities[matches[1]] = parseInt($(this).val(), 10) || 0
        }
      })

      return quantities
    },

    /**
     * Get product ID from form
     */
    getProductId($form, isBuyNow = false, $button = null) {
      if (isBuyNow && $button) {
        return $button.val()
      }

      return $form.find('button[name="add-to-cart"], input[name="add-to-cart"], input[name="product_id"]').val()
    },

    /**
     * Collect all form data for cart action
     */
    collectFormData($form, isBuyNow = false, $button = null) {
      const productId = this.getProductId($form, isBuyNow, $button)
      const quantity = $form.find('input[name="quantity"]').val() || 1
      const variationId = $form.find('input[name="variation_id"]').val() || 0
      const variation = this.collectVariations($form)
      const quantities = this.collectQuantities($form)

      return { productId, quantity, variationId, variation, quantities }
    },
  }

  /**
   * Handle AJAX cart operations
   */
  const AjaxCartHandler = {
    /**
     * Send ajax add to cart request
     */
    sendRequest(data, $button, isBuyNow, isPopup) {
      $button.addClass('loading')

      return $.post(wbnb_params.ajax_url, data).
        done((response) => this.handleSuccess(response, $button, isBuyNow, isPopup)).
        fail((response) => this.handleError(response)).
        always(() => $button.removeClass('loading'))
    },

    /**
     * Handle successful AJAX response
     */
    handleSuccess(response, $button, isBuyNow, isPopup) {
      if (!response.success) {
        this.handleError(response, $button)
        return
      }

      const { fragments = {}, cart_hash = '', redirect_url = '', checkout_template = '', notices = '', wbnb_ref = 0 } = response.data || {}

      // Links a later "popup closed" event to this Buy Now attempt (a debounced double click has no ref, so keep the first one)
      if (wbnb_ref) {
        WBNB_Popup.ref = {
          id: wbnb_ref,
          productId: $button.val() || $button.data('product_id'),
          source: $button.data('source') || ($button.is('a') ? 'archive' : 'single'),
        }
      }

      // Trigger standard WC events (classic theme)
      $(document.body).trigger('added_to_cart', [fragments, cart_hash, $button])

      fireHook('wbnb.cart.added', response.data, $button, isBuyNow, isPopup)

      // Trigger WooCommerce Blocks native event for the Mini Cart block
      document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', {
        bubbles: true,
        cancelable: true,
        detail: { fragments, cart_hash }
      }))

      if (isBuyNow) {
        this.handleBuyNowRedirect(isPopup, checkout_template, redirect_url)
      } else {
        // Standard Add to Cart: Stay on page
        $(document.body).trigger('wc_fragment_refresh')

        // Inject WC notices ("Product added to cart [View Cart]").
        // Handles FSE themes where the fragment system returns no notices.
        this.injectWooCommerceNotices(notices)
      }
    },

    /**
     * Handle buy now redirect or popup
     */
    handleBuyNowRedirect(isPopup, checkoutTemplate, redirectUrl) {
      if (isPopup) {
        const hasPopup = $('#wbnb-popup-overlay').length > 0
        if (hasPopup && checkoutTemplate) {
          WBNB_Popup.open(checkoutTemplate)
        } else {
          window.location.href = redirectUrl || wbnb_params.checkout_url
        }
      } else {
        window.location.href = redirectUrl
      }
    },

    /**
     * Inject WooCommerce notices HTML into the page.
     *
     * In classic themes, the "Product added to cart [View Cart]" notice is included
     * in cart fragments and injected automatically. In FSE themes the fragment system
     * is empty (no widget areas registered), so we receive the notices HTML from the
     * PHP response and inject it ourselves.
     *
     * Creates .woocommerce-notices-wrapper at the top of <main> (or <body> as fallback)
     * if it does not already exist, matching WooCommerce's own DOM convention.
     * Auto-hides success notices after 5 s, mirroring WooCommerce JS behaviour.
     *
     * Safe to call with an empty string — returns immediately without DOM changes.
     */
    injectWooCommerceNotices(noticesHtml) {
      if (!noticesHtml) {
        return
      }

      // Find or create the notices wrapper
      let $wrapper = $('.woocommerce-notices-wrapper').first()

      if (!$wrapper.length) {
        $wrapper = $('<div class="woocommerce-notices-wrapper"></div>')
        // Prefer injecting at the top of <main>, fall back to after <body> open
        const $main = $('main').first()
        if ($main.length) {
          $main.prepend($wrapper)
        } else {
          $('body').prepend($wrapper)
        }
      }

      $wrapper.html(noticesHtml)

      // Auto-dismiss success messages after 5 s (matches WooCommerce default JS)
      setTimeout(() => {
        $wrapper.find('.woocommerce-message').fadeOut(400, function () {
          $(this).remove()
        })
      }, 5000)
    },

    /**
     * Handle AJAX errors
     */
    handleError(response) {
      const message = response.data?.message
      if (message) {
        alert(message)
      } else {
        console.log(response)
      }
    },
  }

  /**
   * Popup manager
   */
  const WBNB_Popup = {
    isOpen: false,
    isLoading: false,
    ref: null,

    /**
     * Initialize popup event listeners
     */
    init() {
      $(document).on('click', '.wbnb-popup-close, #wbnb-popup-overlay', (e) => {
        if ($(e.target).is('#wbnb-popup-overlay') || $(e.target).hasClass('wbnb-popup-close')) {
          this.close()
        }
      })

      $(document).on('keyup', (e) => {
        if (this.isOpen && (e.key === 'Escape' || e.keyCode === 27)) {
          this.close()
        }
      })

      // Prevent closing when clicking inside content
      $(document).on('click', '#wbnb-popup-content', (e) => {
        e.stopPropagation()
      })
    },

    /**
     * Open popup with optional HTML content
     */
    open(html) {
      if (this.isOpen || this.isLoading) {
        return
      }

      this.isOpen = true
      this.isLoading = true

      const $overlay = $('#wbnb-popup-overlay')
      const $inner = $overlay.find('.wbnb-popup-inner')

      $overlay.addClass('wbnb-show')

      if (html) {
        $inner.html(html)
        this.initWooCommerceCheckout()
      } else {
        $inner.html('<div class="wbnb-loader">Loading Checkout...</div>')
      }

      this.isLoading = false

      fireHook('wbnb.popup.opened', $overlay, this.ref)
    },

    /**
     * Initialize WooCommerce checkout in popup
     */
    initWooCommerceCheckout() {
      if (typeof wc_checkout_params === 'undefined') {
        return
      }

      const $popupContent = $('#wbnb-popup-content')
      const $checkoutForm = $popupContent.find('form.checkout')

      if (!$checkoutForm.length) {
        return
      }

      // Trigger standard WC checkout initialization
      $(document.body).trigger('init_checkout')
      $(document.body).trigger('update_checkout')

      // Intercept form submission to handle errors in popup
      $checkoutForm.off('submit.wbnb_popup').on('submit.wbnb_popup', function (e) {
        const $form = $(this)

        if ($form.is('.processing')) {
          return false
        }

        // Get payment method
        const paymentMethod = $form.find('input[name="payment_method"]:checked').val()

        // Let payment gateways do their thing
        if ($form.triggerHandler('checkout_place_order') !== false &&
          $form.triggerHandler('checkout_place_order_' + paymentMethod) !== false) {

          $form.addClass('processing')

          // Block the form
          $form.block({
            message: null,
            overlayCSS: {
              background: '#fff',
              opacity: 0.6,
            },
          })

          // Submit via AJAX
          $.ajax({
            type: 'POST',
            url: wc_checkout_params.checkout_url,
            data: $form.serialize(),
            dataType: 'json',
            success: function (result) {
              try {
                if ('success' === result.result) {
                  // Successful order - redirect
                  if (-1 === result.redirect.indexOf('https://') || -1 === result.redirect.indexOf('http://')) {
                    window.location = result.redirect
                  } else {
                    window.location = decodeURI(result.redirect)
                  }
                } else if ('failure' === result.result) {
                  throw 'Result failure'
                } else {
                  throw 'Invalid response'
                }
              } catch (err) {
                // Reload page if requested
                if (true === result.reload) {
                  window.location.reload()
                  return
                }

                // Trigger update in case we need a fresh nonce
                if (true === result.refresh) {
                  $(document.body).trigger('update_checkout')
                }

                // Display errors in popup
                if (result.messages) {
                  WBNB_Popup.showCheckoutError(result.messages, $form)
                } else {
                  WBNB_Popup.showCheckoutError(
                    '<div class="woocommerce-error">' + wc_checkout_params.i18n_checkout_error + '</div>',
                    $form
                  )
                }
              }
            },
            error: function (jqXHR, textStatus, errorThrown) {
              WBNB_Popup.showCheckoutError(
                '<div class="woocommerce-error">' + errorThrown + '</div>',
                $form
              )
            },
          })
        }

        return false
      })
    },

    /**
     * Show checkout error in popup
     */
    showCheckoutError(error_message, $form) {
      const $popupContent = $('#wbnb-popup-content')

      // Remove existing errors
      $popupContent.find('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message, .is-error, .is-success').remove()

      // Add error to form
      $form.prepend(
        '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>'
      )

      // Unblock and remove processing class
      $form.removeClass('processing').unblock()
      $form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur')

      // Scroll to error
      this.scrollToNotices()

      // Focus on error
      $form.find('.woocommerce-error[tabindex="-1"]').focus()

      // Trigger event
      $(document.body).trigger('checkout_error', [error_message])
    },

    /**
     * Scroll to error notices within popup
     */
    scrollToNotices() {
      const $popupContent = $('#wbnb-popup-content')
      const $scrollElement = $popupContent.find('.woocommerce-NoticeGroup-checkout, .woocommerce-error').first()

      if ($scrollElement.length) {
        const scrollTop = $scrollElement.position().top - 20

        $popupContent.animate({
          scrollTop: scrollTop,
        }, 500)
      }
    },

    /**
     * Close popup
     */
    close() {
      // Remove form submission handler
      $('#wbnb-popup-content form.checkout').off('submit.wbnb_popup')

      // A placed order redirects away, so closing means the popup was left without ordering
      if (this.isOpen && this.ref) {
        Analytics.send(Analytics.EVENT_POPUP_CLOSE, this.ref.productId, this.ref.source, { ref: this.ref.id })
        this.ref = null
      }

      this.isOpen = false
      $('#wbnb-popup-overlay').removeClass('wbnb-show')
    },
  }

  // For add-ons (Pro): reuse the popup, AJAX cart and form helpers. Listen with wp.hooks.addAction( 'wbnb.cart.added' | 'wbnb.popup.opened', ... ).
  window.wbnb = { Popup: WBNB_Popup, AjaxCartHandler, DataCollector }

  /**
   * Event handler for standard add to cart
   */
  function handleAjaxAddToCart() {
    if (!wbnb_params.is_ajax) {
      return
    }

    $(document).on('click', '.single_add_to_cart_button:not([data-wc-buy-now="true"], .disabled)', function (e) {
      const $button = $(this)
      const $form = $button.closest('form.cart')

      if ($form.length === 0) {
        return
      }

      // Check if the product type is supported for this specific form/button
      let productType = $button.data('product_type') || $form.data('product_type')

      if (!productType) {
        // Fallback detection
        if ($form.hasClass('variations_form')) {
          productType = 'variable'
        } else if ($form.find('.grouped_form').length || $form.find('table.group_table').length) {
          productType = 'grouped'
        } else {
          // Fallback to body class for standard single product pages
          const bodyClasses = $('body').attr('class') || ''
          const match = bodyClasses.match(/product-type-([^\s]+)/)
          productType = match ? match[1] : 'simple'
        }
      }

      if (wbnb_params.product_types && !wbnb_params.product_types.includes(productType)) {
        return // Let WooCommerce handle it (standard form submit)
      }

      e.preventDefault()

      const { productId, quantity, variationId, variation, quantities } = DataCollector.collectFormData($form)

      // Validate group product quantities
      if ($form.find('input[name^="quantity"]').length > 1 && Object.values(quantities).every(q => q === 0)) {
        alert('Please choose the quantity of items you wish to add to your cart.')
        return
      }

      const data = {
        action: 'wbnb_add_to_cart',
        is_buy_now: false,
        product_id: productId,
        quantity,
        quantities,
        variation_id: variationId,
        variation,
      }

      $(document.body).trigger('adding_to_cart', [$button, data])
      AjaxCartHandler.sendRequest(data, $button, false, false)
    })
  }

  /**
   * Event handler for buy now buttons
   */
  function handleBuyNowButtons() {
    $(document).on('click', '.wc-buy-now-btn[data-redirect-location="popup-checkout"]', function (e) {
      const $this = $(this)

      // Variation validation (same as WooCommerce's Add to Cart)
      if (!validateVariation($this, e)) {
        return
      }

      // Check for product type support
      const productType = $this.data('product_type') || $this.closest('form').data('product_type') || 'simple'
      if (wbnb_params.product_types && !wbnb_params.product_types.includes(productType)) {
        return // Let browser handle it naturally
      }

      // Only handle with JavaScript if it's a buy now button with popup-checkout
      e.preventDefault()
      e.stopImmediatePropagation()

      if (!wbnb_params.is_popup) {
        // Let browser handle it naturally (form submit or link navigation)
        return
      }

      // Handle button submit
      if ($this.is('button[type="submit"]') || $this.is('input[type="submit"]')) {
        handleButtonSubmitForPopup($this)
      }
      // Handle link click
      else if ($this.is('a')) {
        handleLinkClickForPopup($this)
      }
    })
  }

  /**
   * Handle button submit for popup checkout
   */
  function handleButtonSubmitForPopup($button) {
    const $form = $button.closest('form')

    if ($form.length === 0) {
      return
    }

    const { productId, quantity, variationId, variation, quantities } = DataCollector.collectFormData($form, true, $button)

    // Validate group product quantities
    if ($form.find('input[name^="quantity"]').length > 1 && Object.values(quantities).every(q => q === 0)) {
      alert('Please choose the quantity of items you wish to add to your cart.')
      return
    }

    const data = {
      action: 'wbnb_add_to_cart',
      product_id: productId,
      is_buy_now: true,
      quantity,
      quantities,
      variation_id: variationId,
      variation,
      source: $button.data('source') || 'single',
    }

    AjaxCartHandler.sendRequest(data, $button, true, true)
  }

  /**
   * Handle link click for popup checkout from archive pages
   */
  function handleLinkClickForPopup($link) {
    const productId = $link.data('product_id')
    const quantity = $link.data('quantity') || 1

    const data = {
      action: 'wbnb_add_to_cart',
      product_id: productId,
      is_buy_now: true,
      quantity,
      source: $link.data('source') || 'archive',
    }

    AjaxCartHandler.sendRequest(data, $link, true, true)
  }

  /**
   * Record clicks on Buy Now buttons that open a custom (external) link
   */
  function handleExternalBuyNowLinks() {
    $(document).on('click', 'a.wc-buy-now-btn[data-redirect-location="custom"]', function () {
      const $link = $(this)
      const source = $link.data('source') || ($link.hasClass('wc-buy-now-btn-archive') ? 'archive' : 'single')

      Analytics.send(Analytics.EVENT_EXTERNAL_CLICK, $link.data('product_id'), source)
    })
  }
})(jQuery)