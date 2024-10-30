document.addEventListener('DOMContentLoaded', function() {

	// handle corresponding settings fields
	jQuery('#captcha-at-form-option-settings input[type="checkbox"]').change(function() {
		if(this.checked){
			switch (this.value) {
				case 'divi-login':
					// if divi login is checked, also check wp native login as its corresponding
					jQuery('#captcha-at-form-option-settings [value="_wp-login"]').prop('checked', 'checked');
					break;
				default:
					break;
			}
		}
	});

	jQuery('#captcha-at-form-option-settings').submit(function(e) {
		var form = e.target;
		e.preventDefault();

		// disable submit button
		jQuery(this).find(':submit').attr('disabled', 'disabled');

		// get fields from collection
		var host = this.elements.captcha_at_host.value;
		var restKey = this.elements.captcha_at_rest_key.value;
		var publicKey = this.elements.captcha_at_public_key.value;

		var form = jQuery(this);
		jQuery.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'captcha_at_check_settings',
				host: host,
				restKey: restKey,
				publicKey: publicKey,
			},
			success: function(res) {
				if(res.success) {
					// switch red border with green of previously invalid fields
					form.find('input.invalid').removeClass('invalid').addClass('valid');

					// settings ok => submit form
					form.find(':submit').removeAttr('disabled');
					form.unbind('submit').submit();
				} else {
					// insert message
					if(typeof(res.data.notice) != 'undefined') {
						// clear notices on submit
						jQuery('#captcha-at-notices').html('');
						jQuery('#captcha-at-notices').append(res.data.notice);
					}

					// remove red border
					form.find('input').removeClass('invalid');

					if(typeof(res.data.fields) != 'undefined') {
						jQuery(res.data.fields).each(function() {
							jQuery('[name="' + this + '"]').addClass('invalid');
						});
					}

					// enable button
					form.find(':submit').removeAttr('disabled');

					// scroll top
					window.scrollTo(0, 0);
				}
			}
		});
	});
});