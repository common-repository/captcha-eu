jQuery(document).ready(function() {
	// Inject and intercept forms
	KROT.setup(captchaAt.publicKey)
	KROT.KROT_HOST = captchaAt.host;

	// Comments
	if(captchaAt.plugins.indexOf('_wp-comments') > -1) {
		if(jQuery('#commentform').length > 0) {
			KROT.interceptForm(jQuery('#commentform')[0]);
		}
	}
});


