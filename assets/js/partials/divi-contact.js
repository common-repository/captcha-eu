// Divi Contact Form
jQuery(document).ready(function() {
    var $forms = jQuery('form.et_pb_contact_form');

	// Intercept Form
	$forms.each(function() {
		var $form = jQuery(this);
		var $btn = $form.find(':submit');

		// intercept
		KROT.interceptForm($form[0], true);

		$btn.click(function(e) {
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
});
