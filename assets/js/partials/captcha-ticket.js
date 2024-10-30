// Captcha Ticket
jQuery(document).ready(function() {
	var $form = jQuery('#captcha-at-form-ticket');
	var $btn = $form.find(':submit');

	KROT.interceptForm($form[0]);

	$btn.click(function(e) {
		if(e.intercepted) return;

		var $btn = jQuery(this);
		e.preventDefault();
		$btn.attr('disabled', true);

		// RUN captcha
		KROT.getSolution('fNHOnhpdyBtlnyQWTmHX-x-7c0685dc54b75084d53adc1e7a131121881ac1f1')
		.then((sol) => {
			$form.find('.captcha_at_hidden_field').val(JSON.stringify(sol));
			$form.submit();

			// enable submit
			$btn.attr('disabled', false);

			// override prevented flag to prevent jquery from discarding event
			e.intercepted = true;
		});
	});
});