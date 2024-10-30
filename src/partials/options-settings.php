<div class="wrap captcha-at">
    <?php include 'header.php'; ?>

    <div class="settings-content">

        <form id="captcha-at-form-option-settings" method="post" action="options.php">
            <?php settings_fields('captcha-at_settings'); ?>
            <?php do_settings_sections('captcha-at_settings'); ?>

            <div id="captcha-at-notices">
            <?php
                // check if errors occured & add notice panel
                if (! empty($configMessages)) {
                    foreach ($configMessages as $error) {
                        echo $options->panelMSG($error->type, $error->msg);
                    }
                }
    ?>
            </div>

            <!-- REST Key -->
            <?php
        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle('REST Key', 'key-rest'),
            ]),
            $options->wrapInDiv('content', [
                $options->fieldInputText('captcha_at_rest_key', get_option('captcha_at_rest_key'), __('Paste your REST Key here', 'captcha-eu')),
                $options->fieldInfoIcon(__('You can find your REST Key in the Captcha Dashboard:', 'captcha-eu') . ' ' . $urls->dashboard),
            ]),
        ]);
    ?>

            <!-- Public Key -->
            <?php
        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle('Public Key', 'key-public'),
            ]),
            $options->wrapInDiv('content', [
                $options->fieldInputText('captcha_at_public_key', get_option('captcha_at_public_key'), __('Paste your Public Key here', 'captcha-eu')),
                $options->fieldInfoIcon(__('You can find your Public Key in the Captcha Dashboard:', 'captcha-eu') . ' ' . $urls->dashboard),
            ]),
        ]);
    ?>

            <!-- Host -->
            <?php
        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle('Host', 'host'),
            ]),
            $options->wrapInDiv('content', [
                $options->fieldInputText('captcha_at_host', get_option('captcha_at_host'), __('Paste your Host here', 'captcha-eu')),
                $options->fieldInfoIcon(__('You can find your Host in the Captcha Dashboard:', 'captcha-eu') . ' ' . $urls->dashboard),
            ]),
        ]);
    ?>

            <!-- Plugin -->
            <?php
        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle('Plugin', 'plugin'),
                $options->fieldLabel('captcha_at_plugin', __('Select your Plugins', 'captcha-eu')),
            ]),
            $options->wrapInDiv('content', [
                $options->fieldInputCheckbox('captcha_at_plugin', [
                    'WordPress' => [
                        'icon' => 'wordpress',
                        'link' => 'https://www.wordpress.com/',
                        'choices' => [
                            // wp native's
                            '_wp-login' => __('Login', 'captcha-eu'),
                            '_wp-registration' => __('Registration', 'captcha-eu'),
                            '_wp-pw-reset' => __('Password Reset', 'captcha-eu'),
                            '_wp-comments' => __('Comments', 'captcha-eu'),
                        ],
                    ],
                    __('WooCommerce', 'captcha-eu') => [
                        'icon' => 'woocommerce',
                        'link' => 'https://de.wordpress.org/plugins/woocommerce/',
                        'choices' => [
                            'woocommerce-login' => __('Login', 'captcha-eu'),
                            'woocommerce-register' => __('Registration', 'captcha-eu'),
                            'woocommerce-lostpassword' => __('Password Reset', 'captcha-eu'),
                            'woocommerce-checkout' => __('Checkout', 'captcha-eu'),
                        ],
                    ],
                    __('Forminator', 'captcha-eu') => [
                        'icon' => 'forminator',
                        'link' => 'https://wpmudev.com/project/forminator-pro/',
                        'choices' => [
                            'forminator' => __('Enable', 'captcha-eu'),
                            'forminator-widget' => __('Use Widget', 'captcha-eu'),
                        ],
                    ],
                    __('Elementor Pro', 'captcha-eu') => [
                        'icon' => 'elementor',
                        'link' => 'https://elementor.com/pro/',
                        'choices' => [
                            'elementor-pro' => __('Enable', 'captcha-eu'),
                        ],
                    ],
                    __('Ninja Forms', 'captcha-eu') => [
                        'icon' => 'ninjaforms',
                        'link' => 'https://wordpress.org/plugins/ninja-forms/',
                        'choices' => [
                            'ninja-forms' => __('Enable', 'captcha-eu'),
                        ],
                    ],
                    __('Gravity Forms', 'captcha-eu') => [
                        'icon' => 'gravityforms',
                        'link' => 'https://www.gravityforms.com/',
                        'choices' => [
                            'gravityforms' => __('Enable', 'captcha-eu'),
                        ],
                    ],
                    __('Contact Form 7', 'captcha-eu') => [
                        'icon' => 'contactform7',
                        'link' => 'https://de.wordpress.org/plugins/contact-form-7/',
                        'choices' => [
                            'contact-form-7' => __('Enable', 'captcha-eu'),
                        ],
                    ],
                    __('WP Forms', 'captcha-eu') => [
                        'icon' => 'wpforms',
                        'link' => 'https://de.wordpress.org/plugins/wpforms-lite/',
                        'choices' => [
                            'wpforms' => __('Enable', 'captcha-eu'),
                        ],
                    ],
                    __('Mailchimp for WordPress', 'captcha-eu') => [
                        'icon' => 'mc4wp',
                        'link' => 'https://de.wordpress.org/plugins/mailchimp-for-wp/',
                        'choices' => [
                            'mailchimp-for-wp' => __('Enable', 'captcha-eu'),
                        ],
                    ],
                    __('Divi', 'captcha-eu') => [
                        'icon' => 'divi',
                        'link' => 'https://www.elegantthemes.com/gallery/divi/',
                        'choices' => [
                            'divi-login' => __('Login', 'captcha-eu'),
                            'divi-contact' => __('Contact', 'captcha-eu'),
                        ],
                    ],
                ], get_option('captcha_at_plugin'), true),
            ]),
        ], 'plugins');

        echo $options->settingsPanel([
            $options->wrapInDiv('header', [
                $options->fieldTitle('Content Protection', 'plugin'),
                $options->fieldLabel('captcha_at_fragprotect', __('Select Features', 'captcha-eu')),
            ]),
            $options->wrapInDiv('content', [
                $options->fieldInputCheckbox('captcha_at_fragprotect', [
                    'Obfuscate and Protect' => [
                        'icon' => '',
                        'link' => '',
                        'choices' => [
                            'frag-protect-email-posts' => __('Emails in Post Content', 'captcha-eu'),
                            'frag-protect-email-comments' => __('Emails in User Comments', 'captcha-eu'),
                            'frag-protect-email-rss' => __('Emails in RSS Feed', 'captcha-eu'),
                            'frag-protect-email-rss-comments' => __('Emails in Comments in RSS Feed', 'captcha-eu'),
                            'frag-protect-block-feature' => __('Block based Protection', 'captcha-eu'),
                            'frag-protect-shortcode-feature' => __('Shortcode [captcha_protect]', 'captcha-eu'),
                        ],
                    ],
                ], get_option('captcha_at_fragprotect'), true),
            ]),
        ], 'plugins');
    ?>

    <?php
        // Proxy Settings
        if ($showProxy) {
            echo $options->settingsPanel([
                $options->wrapInDiv('header', [
                    $options->fieldTitle('Proxy Settings', 'proxy_settings'),
                    $options->fieldLabel('captcha_at_proxy_enabled', __('Proxy Settings', 'captcha-eu')),
                ]),
                $options->wrapInDiv('content', [
                    $options->fieldInputCheckbox('captcha_at_proxy_enabled', [
                        __('SDK Proxy', 'captcha-eu') => [
                            'icon' => '',
                            'link' => '',
                            'choices' => [
                                'true' => __('Enable', 'captcha-eu'),
                            ],
                        ],
                    ], get_option('captcha_at_proxy_enabled')),
                ]),
            ], 'proxy_settings');
        }
    ?>

            <?php submit_button(__('Save Changes', 'captcha-eu'), 'primary', 'btn-submit', true, ['id' => 'submit_button']); ?>
        </form>
    </div>
</div>
