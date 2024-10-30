// Elementor Pro
function CPTElementorInit() {
  var $forms = jQuery('form.elementor-form');

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

}

jQuery(document).ready(function() {
   CPTElementorInit();
});

// Modal Forms, do not exist on DOM init.
jQuery( document ).on( 'elementor/popup/show', function() {
   CPTElementorInit();
} );
