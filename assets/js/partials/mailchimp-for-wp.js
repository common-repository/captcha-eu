// MC4WP
jQuery(document).ready(function() {
	if(jQuery('form.mc4wp-form').length > 0) {
		var $forms = jQuery('form.mc4wp-form');

		$forms.each(function() {
			var $form = jQuery(this);
			KROT.interceptForm($form[0]);
		});
	}
});
