// Gravity Forms
jQuery(document).ready(function() {
	var $forms = jQuery('.gform_wrapper form');

	$forms.each(function() {
		var $form = jQuery(this);
		if($form.hasClass("cpt_disable")) {
			return;
		}
		var $btn = $form.find(':submit');

		KROT.interceptForm($form[0]);

		$btn.click(function(e) {
			var $btn = jQuery(this);
			var form = $btn.closest('form')[0];
			e.preventDefault();
			$btn.attr('disabled', true);

			KROT.getSolution()
				.then((sol) => {
					form.querySelector('.captcha_at_hidden_field').value = JSON.stringify(sol);
					form.submit();
					$btn.attr('disabled', false);
			});
		})
	});
});
