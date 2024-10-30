// WPForms
jQuery(document).ready(function() {
	var $forms = jQuery('.wpforms-form');

	// Intercept Form, w/o auto submit handler
	$forms.each(function() {
		var $form = jQuery(this);
		var $btn = $form.find(':submit');

		$btn.click(function(e) {
			var $btn = jQuery(this);
			e.preventDefault();
			$btn.attr('disabled', true);

			// RUN captcha
			KROT.getSolution()
			.then((sol) => {
				$btn.closest('form').find('.captcha_at_hidden_field').val(JSON.stringify(sol));
				$btn.closest('form').submit();
				$btn.attr('disabled', false);
			});

			// intercept
			KROT.interceptForm($form[0], true);
		});
	});
});
