// WooCommerce Register
jQuery(document).ready(function () {
	function AttachToForms(sel) {
		var $forms = jQuery(sel);

		// Intercept Form
		$forms.each(function () {
			var $form = jQuery(this);
			var $btn = $form.find(':submit');

			// intercept
			KROT.interceptForm($form[0], true);

			$btn.click(function (e) {
				var $btn = jQuery(this);
				e.preventDefault();
				$btn.attr('disabled', true);

				// RUN captcha
				KROT.getSolution()
					.then((sol) => {
						$btn.closest('form').find('.captcha_at_hidden_field').val(JSON.stringify(sol));
						$btn.attr('disabled', false);
						$btn.closest('form').submit();
					});
			});
		});
	}
	AttachToForms('.woocommerce form.woocommerce-form-register');
	if(jQuery("#atomion-account-modal").length > 0) {
		// atomion - marketpress runs a modal that has no "woocomerce on top"
		// also attach to those -  via chat: customer issue!
		AttachToForms('.atomion-account form.register');

	}

});
