// WooCommerce Checkout
jQuery(document).ready(function() {
	var $forms = jQuery('.woocommerce form.woocommerce-checkout');

	// Intercept Form
	$forms.each(function() {
		var $form = jQuery(this);
		var $btn = $form.find(':submit');

		var checkout_form = $form;
		checkout_form.on('checkout_place_order', fnCheckout);

		function fnCheckout() {
			KROT.getSolution()
			.then((sol) => {
				jQuery(checkout_form).find('.captcha_at_hidden_field').val(JSON.stringify(sol))
				$btn.attr('disabled', false);

				// unbind to prevent loop
				checkout_form.off('checkout_place_order', fnCheckout);

				// submit form
				checkout_form.submit();

				// re-bind for further submissions
				checkout_form.on('checkout_place_order', fnCheckout);

				return true;
			});

			// prevent submit
			return false;
		}
	});
});
