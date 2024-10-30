// Contact Form 7
jQuery(document).ready(function() {
	if(jQuery('.wpcf7-form').length > 0) {
		if ( typeof wpcf7 !== 'undefined' && typeof wpcf7.submit === 'function' ) {
			const submit = wpcf7.submit;
			wpcf7.origSubmit = submit;
			wpcf7.submit = ( form, options = {} ) => {
				if(form instanceof jQuery) {
					form = form[0];
				}
				jQuery(".wpcf7-form").find('input, textarea, select').each(function() { this.setCustomValidity(''); });
				if(!form.checkValidity()) {
					return;
				}
				$btnSubmit = jQuery(form).find('.wpcf7-submit');
				$btnSubmit.attr('disabled', true);
				form.addEventListener( 'wpcf7submit', event => {
					$btnSubmit.attr('disabled', false)
				});
				KROT.interceptForm(form, true);
				KROT.getSolution()
					.then((sol) => {
						form.querySelector('.captcha_at_hidden_field').value = JSON.stringify(sol);
						// trigger original submit
						wpcf7.origSubmit(form, options);
				});
			};
		}
	}
});
