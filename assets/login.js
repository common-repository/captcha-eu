jQuery(document).ready(function() {
	// Inject and intercept forms
	KROT.setup(captchaAt.publicKey)
	KROT.KROT_HOST = captchaAt.host;

	var skipByException = false;
	// Login
	if(window.WFLSVars) {
		skipByException = true;
	}	

	if(!skipByException) {
  		if(captchaAt.plugins.indexOf('_wp-login') > -1) {
			if(jQuery('#loginform').length > 0) {
				KROT.interceptForm(jQuery('#loginform')[0]);
			}
		}
	}

	// Lost Password / Reset
	if(captchaAt.plugins.indexOf('_wp-pw-reset') > -1) {
		if(jQuery('#lostpasswordform').length > 0) {
			KROT.interceptForm(jQuery('#lostpasswordform')[0]);
		}
	}
	// Register
	if(captchaAt.plugins.indexOf('_wp-registration') > -1) {
		if(jQuery('#registerform').length > 0) {
			KROT.interceptForm(jQuery('#registerform')[0]);
		}
	}

});


