// Forminator
jQuery(document).ready(function () {
    jQuery(document).on('forminator:form:submit:success', function(e, formId, response) {
        if(captchaAt.plugins.includes("forminator-widget")) {
            KROT.init();
        }
    });
    
    jQuery(document).on('forminator:form:submit:failed', function(e, formId, response) {
        if(captchaAt.plugins.includes("forminator-widget")) {
            KROT.init();
        }
    });


    var forminatorSelector = "form.forminator-custom-form";
    var $forms = jQuery(forminatorSelector);

    if(captchaAt.plugins.includes("forminator-widget")) {
        // inject the widget on each form:
        $forms.each(function() {
            const $lastSubmitButton = jQuery(this).find('button.forminator-button-submit').last();
            $lastSubmitButton.before('<div class="cpt_widget" style="background: transparent;" data-key="' + captchaAt.publicKey + '">aaa</div>');
        });    
        KROT.init();
        return;
    }

    // Intercept Form
    $forms.each(function () {
        var $form = jQuery(this);
        var $btn = $form.find(':submit');

        // intercept
        KROT.interceptForm($form[0], true);

        $btn.click(function (e) {
            var $btn = jQuery(this);
            e.preventDefault();

            var $invalidFields = $btn.closest('form').find('[aria-invalid="true"]');
            
            if ($invalidFields.length > 0) {
                return;
            }

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
});


