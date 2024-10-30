// Ninja Forms
jQuery(document).on('nfFormReady', function() {
	var $btn = jQuery('.ninja-forms-field[type="submit"]');

	if($btn.closest('form').length > 0) {
		KROT.interceptForm($btn.closest('form')[0], true);

		$btn.click(function(e) {
			if(e.intercepted) return;

			e.preventDefault();
			$btn.attr('disabled', true);

			// RUN captcha
			KROT.getSolution()
			.then((sol) => {
				nfRadio.channel('forms').on('before:submit', function(e) {
					// set extra field
					e.set('extra', {captcha_at_solution: JSON.stringify(sol)});
				});
				$btn.attr('disabled', false);
				// override prevented flag to prevent jquery from discarding event
				e.intercepted = true;
				// retrigger with the exactly same event data
				jQuery(this).trigger(e);
			});
		});
	}
});
