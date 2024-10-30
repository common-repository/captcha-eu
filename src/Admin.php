<?php

namespace CAPTCHA\Plugin;

class Admin
{
    private $plugin_dir;
    private $plugin_dir_url;
    private $core;
    private $wpdb;
    private $isActivation;
    public $endpoint;
    private $urls;
    private $sdkVersion;
    private $hasWordFence;
    private $woo_deduplicate = [    
                                    "login" => false,
                                    "pw-reset" => false,
                                    "authenticate" => false,
                               ];

    public function __construct($core)
    {
        global $wpdb;
        $this->hasWordFence = false;
        $this->wpdb = $wpdb;
        $this->core = $core;
        $this->plugin_dir_url = plugin_dir_url(__FILE__) . '../';
        $this->plugin_dir = plugin_dir_path(__FILE__) . '';
        $this->isActivation = false;

        $this->urls = (object) [
            'dashboard' => 'https://www.captcha.eu/dashboard',
            'host_default' => 'https://w19.captcha.at',
            'documentation' => 'https://docs.captcha.eu/',
        ];

        $this->handleEndpoint();

        $this->handleSDKVersion();

        $this->handleActivationTransient();

        $this->add_filters();
    }

    public function init()
    {
        $this->options();
    }

    public function enque_scripts()
    {
        // no captcha-eu plugins activated => skip sdk.js load
        $plugins = get_option('captcha_at_plugin');
        $fragProtect = get_option('captcha_at_fragprotect');
        $fragProtectEnabled = $fragProtect && count($fragProtect) > 0;
        
        if ((is_array($plugins) && [] != $plugins) || $fragProtectEnabled) {
            // handle sdk.js loading
            $this->enqueue_sdk_script();

            wp_enqueue_script('captcha-eu-wp', $this->plugin_dir_url . 'assets/frontend.js', ['jquery'], '1.0');

            $host = $this->endpoint;

            // check if proxy enabled
            if (get_option('captcha_at_proxy_enabled')) {
                $host = untrailingslashit(get_rest_url()) . '/captcha-eu/v1';
            }

            // localize js variables
            $this->localize_script_data([
                'publicKey' =>  get_option('captcha_at_public_key'),
                'host' =>       $host,
                'plugins' =>    $plugins,
            ]);

            // add script partials
            wp_add_inline_script('captcha-eu-wp', $this->concatInterceptors(), 'after');
        }
    }

    public function enqueue_sdk_script()
    {
        // check if proxy enabled
        if (get_option('captcha_at_proxy_enabled')) {
            // get rest url
            $siteHost = untrailingslashit(get_rest_url());

            // proxy enabled => load from site host
            wp_enqueue_script('captcha-eu-sdk', $siteHost . '/captcha-eu/v1/sdk.js', [], $this->sdkVersion);
        } else {
            // proxy disabled => load directly from endpoint
            global $wp_version;
            wp_enqueue_script('captcha-eu-sdk', add_query_arg(['wpv' => $wp_version], $this->endpoint . '/sdk.js'), [], $this->sdkVersion);
        }
    }

    public function login_scripts()
    {
        // handle sdk.js loading
        $this->enqueue_sdk_script();

        // login.js handling login form
        wp_enqueue_script('captcha-eu-wp', $this->plugin_dir_url . 'assets/login.js', ['jquery'], '1.0');

        $pluginsFiltered = [];

        // check if plugins are selected
        if (get_option('captcha_at_plugin')) {
            foreach (get_option('captcha_at_plugin') as $plugin) {
                // only localize selected wp native "plugins"
                if ('_wp-' == substr($plugin, 0, 4)) {
                    $pluginsFiltered[] = $plugin;
                }
            }
        }

        $host = $this->endpoint;

        // check if proxy enabled
        if (get_option('captcha_at_proxy_enabled')) {
            $host = untrailingslashit(get_rest_url()) . '/captcha-eu/v1';
        }

        // localize js variables
        $this->localize_script_data([
            'publicKey' =>  get_option('captcha_at_public_key'),
            'host' =>       $host,
            'plugins' =>    $pluginsFiltered,
        ]);
    }

    public function handleActivationTransient()
    {
        // check if update transient is set
        $isActivation = get_transient('captcha-at-notice-activation');
        if ($isActivation) {
            $this->isActivation = true;
            // delete transient after activation message has been shown
            delete_transient('captcha-at-notice-activation');
        }
    }

    public function handleEndpoint()
    {
        // if host is not set or empty => use default
        if (get_option('captcha_at_host') && '' != get_option('captcha_at_host')) {
            $this->endpoint = get_option('captcha_at_host');
        } else {
            $this->endpoint = $this->urls->host_default;
        }
    }

    public function handleSDKVersion()
    {
        // if host is not set or empty => use default
        $optKey = 'captcha_at_version_sdk';
        if (get_option($optKey) && '' != get_option($optKey)) {
            $this->sdkVersion = get_option($optKey);
        } else {
            $this->sdkVersion = '';
        }

        // sdk version not set => update once
        if ('' == $this->sdkVersion) {
            $updatedTo = $this->sdkVersionCheckUpdate();
        }
    }

    public function sdkVersionCheckUpdate($proxyForce = false)
    {
        // use host from options (no proxy)
        $restKey = get_option('captcha_at_rest_key');
        $host = get_option('captcha_at_host');

        // fetch personal route
        $personal = $this->apiFetchPersonal($host, $restKey);
        if (! $personal) {
            return false;
        }

        // check if showProxy enabled
        $showProxy = $personal->showProxy ?? false;
        if ($showProxy) {
            // check if option proxy_enabled set => update sdk.js content
            if ($proxyForce || get_option('captcha_at_proxy_enabled')) {
                // sdk.js url
                global $wp_version;
                $sdkUrl = add_query_arg(['wpv' => $wp_version], $this->endpoint . '/sdk.js');

                // fetch sdk.js from endpoint
                $sdkJSContent = file_get_contents($sdkUrl);

                if ('' != $sdkJSContent) {
                    // save sdk.js content to option
                    update_option('captcha_at_proxy_sdkjs_content', $sdkJSContent);
                    // set last sdk.js update timestamp
                    update_option('captcha_at_proxy_sdkjs_updated_at', time());
                }
            }
        } else {
            // showProxy disabled => remove related options
            $this->options_delete_proxy();
        }

        // get latest sdk version from api
        $assetVersionObj = $this->apiFetchLatestVersion($this->endpoint);

        // invalid response
        if (! $assetVersionObj) {
            return false;
        }

        // check against current version from options
        $optKeyVersion = 'captcha_at_version_sdk';
        $sdkVersionCurrent = get_option($optKeyVersion);
        $sdkVersionLatest = $assetVersionObj->version . '-' . $assetVersionObj->asset;

        // if versions do not match -> save latest to option
        if ($sdkVersionCurrent != $sdkVersionLatest) {
            update_option($optKeyVersion, $sdkVersionLatest);

            return $sdkVersionLatest;
        }
    }

    private function concatInterceptors()
    {
        // get all selected plugins to add the interceptor's to
        $plugins = get_option('captcha_at_plugin');

        // no plugins selected => no interceptors
        if (! is_array($plugins)) {
            return '';
        }

        // default output
        $fileContent = '';

        // cycle thru all selected plugins
        foreach ($plugins as $plugin) {
            // load the content of each partial if the filename is valid and file exists
            if (! preg_match('/^[A-Za-z0-9-]+$/', $plugin)) {
                // invalid plugin name => skip
                continue;
            }

            // build file path
            $filePath = $this->plugin_dir . '../assets/js/partials/' . $plugin . '.js';

            // if partial for plugin doesn't exists => skip
            if (! file_exists($filePath)) {
                continue;
            }

            // receive file content
            $fileSrc = file_get_contents($filePath);

            // append to concated content string
            $fileContent .= $fileSrc;
        }

        return $fileContent;
    }

    private function localize_script_data($scriptData)
    {
        // localize scriptData to captchaAt variable
        wp_localize_script('captcha-eu-wp', 'captchaAt', $scriptData);
    }

    public function options()
    {
        register_setting('captcha-at_settings', 'captcha_at_rest_key');
        register_setting('captcha-at_settings', 'captcha_at_host', ['default' => 'https://w19.captcha.at']);
        register_setting('captcha-at_settings', 'captcha_at_public_key');
        register_setting('captcha-at_settings', 'captcha_at_plugin');
        register_setting('captcha-at_settings', 'captcha_at_fragprotect');

        // additional settings
        register_setting('captcha-at_settings', 'captcha_at_proxy_enabled', ['default' => false]);
    }

    public function menu()
    {
        $settings_page = add_menu_page(
            __('Captcha.eu', 'captcha-eu'),
            __('Captcha', 'captcha-eu'),
            'manage_options',
            'captcha-eu',
            [$this, 'options_page'],
            'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4Ni41ODgiIGhlaWdodD0iOTkuOTk3IiB2aWV3Qm94PSIwIDAgODYuNTg4IDk5Ljk5NyI+CiAgPGcgaWQ9IkMtb2YtQ2FwdGNoYS1Mb2dvLWNvbG9yIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtNDIwLjIyMSAtMTYuNzU5KSI+CiAgICA8ZyBpZD0iR3J1cHBlXzQ4NDQiIGRhdGEtbmFtZT0iR3J1cHBlIDQ4NDQiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDQyMC4yMjEgMTYuNzU5KSI+CiAgICAgIDxwYXRoIGlkPSJQZmFkXzI3IiBkYXRhLW5hbWU9IlBmYWQgMjciIGQ9Ik00NjcuNjE1LDYzLjg2OWwxMy41NDctMTQuMDYxYTE5Ljk3NSwxOS45NzUsMCwxLDAsMS4xMywyOC4yWiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTQxNy45NjIgLTE0LjQ3NSkiIGZpbGw9IiMxYTFhMWEiLz4KICAgICAgPGcgaWQ9IkdydXBwZV80ODQzIiBkYXRhLW5hbWU9IkdydXBwZSA0ODQzIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwIDApIj4KICAgICAgICA8cGF0aCBpZD0iUGZhZF8yNiIgZGF0YS1uYW1lPSJQZmFkIDI2IiBkPSJNNTA2LjgwOSwxMDEuODE0Yy0xMC45MTksMTAuNzI4LTIyLjQxMywxNC45NDItMzYuNCwxNC45NDItMjcuMzk0LDAtNTAuMTg5LTE2LjQ3My01MC4xODktNTBzMjIuOC01MCw1MC4xODktNTBjMTMuNDExLDAsMjMuNzU2LDMuODMyLDM0LjEsMTMuOTg1TDQ4OS43NjEsNDYuMjZhMjguNjg5LDI4LjY4OSwwLDAsMC0xOC45NjYtNy40NzFjLTE1LjcwNywwLTI3LjIsMTEuNDkzLTI3LjIsMjcuOTY5LDAsMTguMDA3LDEyLjI2MSwyNy41ODUsMjYuODE3LDI3LjU4NSw3LjQ3MiwwLDE0Ljk0NC0yLjEwNiwyMC42OTEtNy44NTRaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtNDIwLjIyMSAtMTYuNzU5KSIgZmlsbD0iIzFhMWExYSIvPgogICAgICA8L2c+CiAgICA8L2c+CiAgPC9nPgo8L3N2Zz4K'
        );
        add_submenu_page(
            'captcha-eu',
            __('Captcha.eu', 'captcha-eu'),
            __('Overview', 'captcha-eu'),
            'manage_options',
            'captcha-eu',
            [$this, 'options_page']
        );

        add_submenu_page(
            'captcha-eu',
            __('Captcha.eu Settings', 'captcha-eu'),
            __('Settings', 'captcha-eu'),
            'manage_options',
            'captcha-eu-settings',
            [$this, 'options_page_settings']
        );
    }

    public function pre_comment_on_post($comment_post_ID)
    {
        $go_back = sprintf('<br><a href="javascript:history.go(-1)">' . __('Back to', 'captcha-eu') . ' "%s"</a>', get_the_title($comment_post_ID));
        $error = '<strong>' . __('ERROR', 'captcha-eu') . '</strong>:' . __('Captcha.eu failed to validate.', 'captcha-eu') . $go_back;

        if (! isset($_POST['captcha_at_solution'])) {
            wp_die($error);
        }

        $v = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));
        if (! $v) {
            wp_die($error);
        }
    }

    public function authenticate($user, $username, $password)
    {
        $error = new \WP_Error('captcha_failed', __('Captcha.eu failed', 'captcha-eu'));

        if (is_wp_error($user) && isset($user->errors['empty_username']) && isset($user->errors['empty_password'])) {
            return $user;
        }

        if($this->woo_deduplicate["authenticate"]) {
            return $user;
        }

        $skip_by_exception = false;
        $active_plugins = get_option('active_plugins');
        $wordfence_plugin = 'wordfence-login-security/wordfence-login-security.php';
        if (in_array($wordfence_plugin, $active_plugins)) {
            $this->hasWordFence = true;
        }

        if ($this->hasWordFence && isset($_POST['wfls-token'])) {
            $skip_by_exception = true;
        }

        if ($skip_by_exception) {
            return $user;
        }
       
        if (! isset($_POST['captcha_at_solution'])) {
            return $error;
        }
        $val = sanitize_text_field(wp_unslash($_POST['captcha_at_solution']));
        $v = $this->core->validate($val);
        if (! $v) {
            return $error;
        }
        $this->woo_deduplicate["authenticate"] = true;
        $this->woo_deduplicate["login"] = true;

        return $user;
    }
    // Create a function to log the stack trace
    function logStackTrace()
    {
        $stacktrace = debug_backtrace();
        $output = "Stack trace:\n";
        foreach ($stacktrace as $node) {
            $output .= (isset($node['file']) ? $node['file'] : '[unknown file]')
            . ":" . (isset($node['line']) ? $node['line'] : '[unknown line]')
            . " - " . (isset($node['function']) ? $node['function'] : '[unknown function]')
            . "\n";
        }
        error_log($output);
    }

    public function allow_password_reset($allow, $user_id)
    {
        if (!isset($_POST['captcha_at_solution'])) {
            return false;
        }
        // If in the same request we already did a success validation
        // return $allow - as a revalidation would fail as each sol is only valid once
        if($this->woo_deduplicate["pw-reset"]) {
            return $allow;
        }
        $v = sanitize_text_field(wp_unslash($_POST['captcha_at_solution']));
        $allow = $this->core->validate($v);
        if (!$allow) {
            return new \WP_Error('captcha_failed', __('Captcha.eu failed', 'captcha-eu'));
        }
        // WooCommerce calls this twice internally
        // mark it.
        $this->woo_deduplicate["pw-reset"] = true;
        return $allow;
    }

    public function wpcf7_spam($spam, $submission = null)
    {
        if ($spam) {
            return $spam;
        }
        if (! isset($_POST['captcha_at_solution'])) {
            $spam = true;

            return $spam;
        }
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));
        if (! $allow) {
            $spam = true;

            return $spam;
        }

        return $spam;
    }

    public function wpforms_process($fields, $entry, $form_data)
    {
        if (! isset($_POST['captcha_at_solution'])) {
            wpforms()->process->errors[$form_data['id']]['footer'] = esc_html__('Captcha.eu failed', 'captcha-eu');
        }
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));
        if (! $allow) {
            wpforms()->process->errors[$form_data['id']]['footer'] = esc_html__('Captcha.eu failed', 'captcha-eu');
        }
    }

    public function gform_abort_submission_with_confirmation($do_abort, $form)
    { 
        // GFORM opt out, if CSS Class cpt_disable is added, we ignore the spam check
        // this, at first, might look like an easy bypass
        // JS logic is disabled
        // no sol arrives
        // config is loaded exclusivly from server-side
        if(isset($form["cssClass"]) && preg_match("/cpt_disable/", $form["cssClass"])) {
            return false;
        }
        // Allow short circuit by filter
        $skipAbort = false;
        $skipAbort = apply_filters('cpt_gform_skip_abort', $skipAbort, $form);
        // if filter canceled, abort the abort 🤣
        if($skipAbort) {
            return false;
        }
        if ($do_abort) {
            return $do_abort;
        }
        if (! isset($_POST['captcha_at_solution'])) {
            return true;
        }

        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));
        if (! $allow) {
            return true;
        }

        return $do_abort;
    }

    public function ninja_forms_submit_data($formData)
    {
        // formData not set => exit
        if (! isset($formData) || empty($formData)) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        // extra fields not set => exit
        if (! isset($formData['extra'])) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        // captcha field not set => exit
        if (! isset($formData['extra']['captcha_at_solution'])) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        // captcha solution supplied => validate
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($formData['extra']['captcha_at_solution'])));
        if (! $allow) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        return $formData;
    }

    public function registration_errors($errors, $login_sanitized, $email)
    {
        // check if captcha solution supplied
        if (! isset($_POST['captcha_at_solution'])) {
            $errors->add(__('Captcha.eu failed', 'captcha-eu'), __('Captcha.eu failed', 'captcha-eu'));

            return $errors;
        }

        // captcha solution supplied => validate
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));
        if (! $allow) {
            $errors->add(__('Captcha.eu failed', 'captcha-eu'), __('Captcha.eu failed', 'captcha-eu'));
        }

        return $errors;
    }

    public function mc4wp_form_errors($errors, $form)
    {
        // no solution supplied
        if (! isset($form->raw_data['captcha_at_solution'])) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        // captcha solution supplied => validate
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($form->raw_data['captcha_at_solution'])));
        if (! $allow) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        return $errors;
    }

    public function admin_notices()
    {
        // only disable for captcha-eu pages
        $screen = get_current_screen();
        if ('captcha-eu' == $screen->parent_base) {
            global $wp_filter;

            foreach ($wp_filter['admin_notices'] as $priority => $callbacks) {
                foreach ($callbacks as $cbName => $cbDetails) {
                    if (is_string($cbDetails['function'])) {
                        continue;
                    }
                    if (is_iterable($cbDetails['function'])) {
                        foreach ($cbDetails['function'] as $func) {
                            if (! $func instanceof self) {
                                $wp_filter['admin_notices']->remove_filter('admin_notices', $cbDetails['function'], $priority);
                                continue;
                            }
                        }
                    } else {
                        $wp_filter['admin_notices']->remove_filter('admin_notices', $cbDetails['function'], $priority);
                    }
                }
            }
        }

        // add validation error nag (will output if neccessary)
        $this->captchaNagValidateErrors();

        // add setup nag (will output if neccessary, forced if activation)
        $this->captchaNagSetup($this->isActivation);
    }

    // check if current screen should show captcha-nag's
    private function nagEnabledOnPage()
    {
        // get pagenow & screen
        global $pagenow;
        $screen = get_current_screen();

        return in_array($pagenow, ['index.php', 'plugins.php']) || in_array($screen->base ?? false, ['toplevel_page_captcha-eu', 'captcha_page_captcha-eu-settings']);
    }

    public function captchaNagValidateErrors()
    {
        global $pagenow;
        $screen = get_current_screen();

        // Dashboard or Plugin Page
        if ($this->nagEnabledOnPage()) {
            // get all catched errors
            $errorsCatched = get_option('captcha_at_errors_catched');

            // no errors => skip
            if (! $errorsCatched) {
                return false;
            }

            $errors = [];

            $now = time();
            $range = 60*60*24;

            // cycle all entries
            foreach ($errorsCatched as $k => $error) {
                // skip entries out of range
                if ($error['t'] <= $now - $range) {
                    continue;
                } else {
                    $errors[] = date('Y-m-d H:i:s', $error['t']) . ' - ' . $error['e'];
                }
            }

            // no errors in range => skip
            if ([] == $errors) {
                return false;
            }

            // only display the last 5 entries
            $errors = array_slice($errors, -5, 5);
            $errors = array_reverse($errors);

            echo '<div class="update-nag captcha-at-nag validation-errors">';
            echo '<div class="wrapper">';
            echo '<div class="icon"></div>';
            echo '<div class="content">';
            echo '<div class="title">' . __('Validation Requests failed - Make sure to check your network settings!', 'captcha-eu') . '</div>';
            echo '<div class="text">';

            // cycle thru all errors
            foreach ($errors as $error) {
                echo '<div class="e">' . esc_html($error) . '</div>';
            }

            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    public function captchaNagSetup($force = false)
    {
        // SHOW NAG/NOTICE AFTER INSTALLING
        global $pagenow;

        // get options
        $restKey = get_option('captcha_at_rest_key');
        $host = get_option('captcha_at_host');
        $publicKey = get_option('captcha_at_public_key');

        // if any of the needed option fields is empty (after activation) or forced
        if ($force || ('' == $restKey || '' == $host || '' == $publicKey)) {
            // Dashboard or Plugin Page
            if (in_array($pagenow, ['index.php', 'plugins.php'])) {
                echo '<div class="update-nag captcha-at-nag">';
                echo '<div class="wrapper">';
                echo '<div class="icon"></div>';
                echo '<div class="content">';
                echo '<div class="title">' . __('Let&apos;s set up your protection', 'captcha-eu') . '</div>';
                echo '<div class="text">' . __('Thank you for using Captcha! Make sure to update your settings to enable your protection!', 'captcha-eu') . '</div>';
                echo '<div class="actions">';
                echo '<a class="btn" href="' . menu_page_url('captcha-eu', false) . '">' . __('Overview', 'captcha-eu') . '</a>';
                echo '<a class="btn" href="' . menu_page_url('captcha-eu-settings', false) . '">' . __('Settings', 'captcha-eu') . '</a>';
                echo '<a class="btn" target="_blank" href="' . esc_url($this->urls->documentation) . '">' . __('Documentation', 'captcha-eu') . '</a>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }
    }

    public function captchaNagDeactivated()
    {
        // SHOW NAG/NOTICE AFTER INSTALLING
        echo '<div class="update-nag captcha-at-nag">';
        echo '<div class="wrapper">';
        echo '<div class="icon"></div>';
        echo '<div class="content">';
        echo '<div class="title">' . __('Successfully deactivated Captcha.eu', 'captcha-eu') . '</div>';
        echo '<div class="text">' . __('You can always reactivate this plugin on your plugins page', 'captcha-eu') . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function plugin_deactivated()
    {
        // clear update schedules/crons
        wp_clear_scheduled_hook('captcha_at_sched_sdk_version');

        // delete options created by the plugin
        delete_option('captcha_at_host');
        delete_option('captcha_at_plugin');
        delete_option('captcha_at_rest_key');
        delete_option('captcha_at_public_key');
        delete_option('captcha_at_version_sdk');
        delete_option('captcha_at_update_available');
        delete_option('captcha_at_update_version');
        delete_option('captcha_at_update_url');
        delete_option('captcha_at_errors_catched');

        $this->options_delete_proxy();
    }

    public function addError($error = '')
    {
        // if error empty => skip
        if ('' == $error) {
            return false;
        }

        // get entries from option
        $entries = get_option('captcha_at_errors_catched');

        if ($entries) {
            $now = time();
            $range = 60*60*24;

            // cycle all entries
            foreach ($entries as $k => $entry) {
                // delete entries out of range
                if ($entry['t'] <= $now - $range) {
                    unset($entries[$k]);
                }
            }
        } else {
            $entries = [];
        }

        // add current entry
        $entries[] = [
            't' => time(),
            'e' => $error,
        ];

        // save updated entries
        update_option('captcha_at_errors_catched', $entries);
    }

    private function options_delete_proxy()
    {
        // delete all proxy options
        delete_option('captcha_at_proxy_enabled');
        delete_option('captcha_at_proxy_sdkjs_content');
        delete_option('captcha_at_proxy_sdkjs_updated_at');
    }

    public function admin_scripts($hook)
    {
        $screen = get_current_screen();

        // load option-settings only on settings page
        if ('captcha_page_captcha-eu-settings' == $screen->base) {
            wp_enqueue_script('captcha_admin_script', $this->plugin_dir_url . 'assets/js/option-settings.js', ['jquery'], '1.0');
        }
    }

    public function captcha_at_check_settings()
    {
        // get values from POST
        $postHost = isset($_POST['host']) ? sanitize_url($_POST['host']) : false;
        $postRestKey = isset($_POST['restKey']) ? sanitize_text_field($_POST['restKey']) : false;
        $postPublicKey = isset($_POST['publicKey']) ? sanitize_text_field($_POST['publicKey']) : false;

        $errorFields = [];

        if (! $postHost || '' == $postHost) {
            $errorFields[] = 'captcha_at_host';
        }

        if (! $postRestKey || '' == $postRestKey) {
            $errorFields[] = 'captcha_at_rest_key';
        }

        if (! $postPublicKey || '' == $postPublicKey) {
            $errorFields[] = 'captcha_at_public_key';
        }

        if ([] != $errorFields) {
            return wp_send_json_error([
                'notice' => $this->core->options->panelMSG('error', __('Fields missing or empty', 'captcha-eu')),
                'fields' => $errorFields,
            ]);
        }

        $apiResp = $this->apiFetchPersonal($postHost, $postRestKey);

        // if valid => return success
        if ($apiResp) {
            // check if the posted public key differs from API
            if ($postPublicKey != $apiResp->publicSecret) {
                return wp_send_json_error([
                    'notice' => $this->core->options->panelMSG('error', __('Connection failed. Invalid Public Key', 'captcha-eu')),
                    'fields' => ['captcha_at_public_key'],
                ]);
            }

            return wp_send_json_success('OK');
        } else {
            return wp_send_json_error([
                'notice' => $this->core->options->panelMSG('error', __('Connection failed', 'captcha-eu')),
            ]);
        }
    }

    public function woocommerce_login_form($content = '')
    {
        echo '<input type="hidden" name="login" value="Login">';

        return $content;
    }

    public function woocommerce_process_login_errors($validation_error, $login, $pass)
    {
        if (! isset($_POST['captcha_at_solution'])) {
            // no solution supplied
            $validation_error->add('ERROR', __('Captcha.eu failed', 'captcha-eu'));

            return $validation_error;
        }
        if($this->woo_deduplicate["login"])  {
            return $validation_error;
        }
        // validate
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));

        if (! $allow) {
            $validation_error->add('ERROR', __('Captcha.eu failed', 'captcha-eu'));
            return $validation_error;
        }
        $this->woo_deduplicate["login"] = true;
        $this->woo_deduplicate["authenticate"] = true;
        return $validation_error;
    }

    public function woocommerce_register_form($content = '')
    {
        echo '<input type="hidden" name="register" value="Register">';

        return $content;
    }

    public function woocommerce_process_registration_errors($validation_error, $user, $password, $email)
    {
        if (! isset($_POST['captcha_at_solution'])) {
            // no solution supplied
            $validation_error->add('ERROR', __('Captcha.eu failed', 'captcha-eu'));

            return $validation_error;
        }

        // validate
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));

        if (! $allow) {
            $validation_error->add('ERROR', __('Captcha.eu failed', 'captcha-eu'));

            return $error;
        }

        return $validation_error;
    }

    public function woocommerce_lostpassword_form($content = '')
    {
        return $content;
    }

    public function woocommerce_after_checkout_billing_form($checkout)
    {
        echo '<input type="hidden" name="captcha_at_solution" class="captcha_at_hidden_field" value="test">';
    }

    public function woocommerce_after_checkout_validation1()
    {
        $errmsg =  __('Captcha.eu failed', 'captcha-eu');
        if (! isset($_POST['captcha_at_solution'])) {
            // no solution supplied
            wc_add_notice($errmsg, 'error');
            return;
        }
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));

        if (! $allow) {
            wc_add_notice($errmsg, 'error');
            return;
        }
    }

    public function woocommerce_after_checkout_validation($fields, $errors)
    {
        if (! isset($_POST['captcha_at_solution'])) {
            // no solution supplied
            $errors->add('error', __('Captcha.eu failed', 'captcha-eu'));

            return false;
        }
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));

        if (! $allow) {
            $errors->add('error', __('Captcha.eu failed', 'captcha-eu'));

            return false;
        }
    }

    public function elementor_pro_forms_validation($record, $ajax_handler)
    {
        $msgErr = __('Captcha.eu failed', 'captcha-eu');

        if (! isset($_POST['captcha_at_solution'])) {
            // no solution supplied
            $ajax_handler->add_error('captcha_at_solution', $msgErr);
            $ajax_handler->add_error_message($msgErr);

            return;
        }

        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));

        if (! $allow) {
            $ajax_handler->add_error('captcha_at_solution', $msgErr);
            $ajax_handler->add_error_message($msgErr);

            return;
        }
    }

    public function et_pb_contact_form_submit($processed_fields_values, $et_contact_error, $contact_form_info)
    {
        if (! isset($_POST['captcha_at_solution'])) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }

        $allow = $this->core->validate(sanitize_text_field(wp_unslash($_POST['captcha_at_solution'])));

        if (! $allow) {
            echo __('Captcha.eu failed', 'captcha-eu');
            exit;
        }
    }

    public function pre_update_option_captcha_at_proxy_enabled($valueNew, $valueOld)
    {
        // option has been updated => check
        $proxyEnable = $valueNew;

        if (! $proxyEnable) {
            // clear sdk content option field
            update_option('captcha_at_proxy_sdkjs_content', '');
        } else {
            // proxy enabled => do version check once (proxyForced=true)
            $this->sdkVersionCheckUpdate(true);
        }

        return $valueNew;
    }

    public function rest_get_sdkjs()
    {
        // set content type header to javascript
        header('Content-Type: application/javascript');

        // output sdk.js content
        echo get_option('captcha_at_proxy_sdkjs_content') ?: '';

        exit;
    }

    public function rest_challenge(\WP_REST_Request $request)
    {
        // get param from url
        $publicSecret = $request->get_param('publicSecret');

        // check if param is set
        if (! $publicSecret) {
            // error: no public secret
            return rest_ensure_response(false);
        }

        // get payload from request
        $requestBody = $request->get_body();

        // check if body has content
        if (! $requestBody || '' == $requestBody) {
            // error: empty body
            return rest_ensure_response(false);
        }

        if ('' == $requestBody) {
            return rest_ensure_response(false);
        }

        // sanitize solution
        $solution = sanitize_text_field($requestBody);

        // get url/restKey from options
        $url = get_option('captcha_at_host');
        $restKey = get_option('captcha_at_rest_key');

        // build query/params
        $url .= '/challenge?' . http_build_query(
            [
                'publicSecret' => $publicSecret,
            ]
        );

        $ua = $_SERVER['HTTP_USER_AGENT'];
        $request_ip = $this->core->getRealIP();
        $request_ip = $this->core->anonymizeIP($request_ip);

        // send post request to url
        $data = wp_remote_post($url, [
            'headers'     => ['Content-Type' => 'application/json', 'Rest-Key' => $restKey, 'User-Agent' => $ua, 'x-real-ip' => $request_ip],
            'body'        => $solution,
        ]);

        // decode JSON string from body in order to pass as response
        echo wp_remote_retrieve_body($data);
        exit;
    }

    public function rest_api_init()
    {
        // serve sdk.js
        register_rest_route('captcha-eu/v1', '/sdk.js', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_sdkjs'],
            'permission_callback' => '__return_true',
        ]);

        // receive challenge requests
        register_rest_route('captcha-eu/v1', '/challenge', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_challenge'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function add_filters()
    {
        // get selected plugins from option
        $selectedPlugins = get_option('captcha_at_plugin');

        // if no plugin is checked
        if (! is_array($selectedPlugins)) {
            $selectedPlugins = [];
        }

        add_action('admin_notices', [$this, 'admin_notices'], 0);
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'init']);
        add_action('login_enqueue_scripts', [$this, 'login_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enque_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_ajax_captcha_at_check_settings', [$this, 'captcha_at_check_settings']);
        add_action('wp_ajax_nopriv_captcha_at_check_settings', [$this, 'captcha_at_check_settings']);
        add_action('captcha_at_sched_sdk_version', [$this, 'sdkVersionCheckUpdate']);
        add_filter('pre_update_option_captcha_at_proxy_enabled', [$this, 'pre_update_option_captcha_at_proxy_enabled'], 10, 2);
        add_action('rest_api_init', [$this, 'rest_api_init']);

        // WP Login
        if (in_array('_wp-login', $selectedPlugins)) {
            add_action('authenticate', [$this, 'authenticate'], 21, 3);
        }

        // WP Registration
        if (in_array('_wp-registration', $selectedPlugins)) {
            add_action('registration_errors', [$this, 'registration_errors'], 21, 3);
        }

        // WP Lost Password
        if (in_array('_wp-pw-reset', $selectedPlugins)) {
            add_action('allow_password_reset', [$this, 'allow_password_reset'], 21, 2);
        }

        // WP Comments
        if (in_array('_wp-comments', $selectedPlugins)) {
            add_action('pre_comment_on_post', [$this, 'pre_comment_on_post'], 21, 1);
        }

        // Contact Forms 7
        if (in_array('contact-form-7', $selectedPlugins)) {
            add_action('wpcf7_spam', [$this, 'wpcf7_spam'], 21, 2);
        }

        // WP Forms Lite
        if (in_array('wpforms', $selectedPlugins)) {
            add_action('wpforms_process', [$this, 'wpforms_process'], 10, 3);
        }

        // Gravity Forms
        if (in_array('gravityforms', $selectedPlugins)) {
            add_filter('gform_abort_submission_with_confirmation', [$this, 'gform_abort_submission_with_confirmation'], 10, 2);
            add_filter('gform_confirmation', function ($confirmation, $form, $entry) {
                if (empty($entry) || 'spam' === rgar($entry, 'status')) {
                    return __('Spam declined', 'captcha-eu');
                }

                return $confirmation;
            }, 11, 3);
        }

        // Ninja Forms
        if (in_array('ninja-forms', $selectedPlugins)) {
            add_filter('ninja_forms_submit_data', [$this, 'ninja_forms_submit_data']);
        }

        // MC4WP (MailChimp)
        if (in_array('mailchimp-for-wp', $selectedPlugins)) {
            add_filter('mc4wp_form_errors', [$this, 'mc4wp_form_errors'], 10, 2);
        }

        // WooCommerce Login
        if (in_array('woocommerce-login', $selectedPlugins)) {
            add_action('woocommerce_login_form', [$this, 'woocommerce_login_form'], 10, 0);
            add_filter('woocommerce_process_login_errors', [$this, 'woocommerce_process_login_errors'], 10, 3);
        }

        // WooCommerce Register
        if (in_array('woocommerce-register', $selectedPlugins)) {
            add_action('woocommerce_register_form', [$this, 'woocommerce_register_form'], 10, 0);
            add_filter('woocommerce_process_registration_errors', [$this, 'woocommerce_process_registration_errors'], 10, 4);
        }

        // WooCommerce Lost Password
        if (in_array('woocommerce-lostpassword', $selectedPlugins)) {
            add_action('woocommerce_lostpassword_form', [$this, 'woocommerce_lostpassword_form'], 10, 0);
            // add_filter('allow_password_reset', [$this, 'allow_password_reset'], 21, 2);
        }

        // WooCommerce Checkout
        if (in_array('woocommerce-checkout', $selectedPlugins)) {
            add_action('woocommerce_after_checkout_billing_form', [$this, 'woocommerce_after_checkout_billing_form'], 10, 1);
            // add_action('woocommerce_after_checkout_validation', [$this, 'woocommerce_after_checkout_validation'], 100, 2);
            add_action('woocommerce_checkout_process', 'woocommerce_after_checkout_validation1', 20, 3);
        }

        // Elementor
        if (in_array('elementor-pro', $selectedPlugins)) {
            add_action('elementor_pro/forms/validation', [$this, 'elementor_pro_forms_validation'], 10, 2);
        }

        // Divi
        if (in_array('divi-contact', $selectedPlugins)) {
            add_action('et_pb_contact_form_submit', [$this, 'et_pb_contact_form_submit'], 10, 3);
        }
        if (in_array('forminator', $selectedPlugins)) {
            add_filter('forminator_cform_form_is_submittable', [$this, "forminator_validate"], 10, 3);

        }
        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'menu']);
        }
    }
   
    public function forminator_validate($can_show, $id, $form_settings) {
        $msgErr = __('Captcha.eu failed', 'captcha-eu');
        
        $solution = '';
        if (isset($_POST['captcha_at_solution'])) {
            $solution = $_POST['captcha_at_solution'];
        } elseif (isset($_POST['captcha_at_hidden_field'])) {
            $solution = $_POST['captcha_at_hidden_field'];
        }

        if (empty($solution)) {
            // no solution supplied
            return [
                'can_submit' => false,
                'error'      => $msgErr,
            ];
        }
        
        $allow = $this->core->validate(sanitize_text_field(wp_unslash($solution)));

        if (! $allow) {
            return [
                'can_submit' => false,
                'error'      => $msgErr,
            ];
        }
        return $can_show;
    }
    public function notice($type = '', $msg = '')
    {
        return (object) [
            'type' => $type,
            'msg' => $msg,
        ];
    }

    private function apiFetchLatestVersion()
    {
        // fetch asset version info route
        $data = wp_remote_get($this->endpoint . '/version?t=' . time(), [
            'headers' => ['Content-Type' => 'application/json', 'User-Agent' => 'Captcha'],
        ]);

        if (is_wp_error($data)) {
            return false;
        }

        // get body
        $body = wp_remote_retrieve_body($data);
        if (empty($body)) {
            return false;
        }

        // parse json && check if keys are set
        $apiParsed = json_decode($body);
        if ($apiParsed && isset($apiParsed->version) && isset($apiParsed->asset)) {
            return (object) [
                'asset' => $apiParsed->asset,
                'version' => $apiParsed->version,
                'plugin' => [
                   'latest' => $apiParsed->plugin->version,
                   'download' => $apiParsed->plugin->download,
                ],
            ];
        }

        return false;
    }

    private function apiFetchPersonal($host = '', $restKey = '')
    {
        // fetch personal info route
        $data = wp_remote_get($host . '/api/personal', [
            'headers' => ['Content-Type' => 'application/json', 'Rest-Key' => $restKey, 'User-Agent' => 'Captcha'],
        ]);

        if (is_wp_error($data)) {
            return false;
        }

        // get body
        $body = wp_remote_retrieve_body($data);
        if (empty($body)) {
            return false;
        }

        // parse json && check
        $apiParsed = json_decode($body);
        if ($apiParsed && isset($apiParsed->id)) {
            return $apiParsed;
        }

        return false;
    }

    private function getApiOptions()
    {
        $host = get_option('captcha_at_host');

        // check if proxy enabled
        if (get_option('captcha_at_proxy_enabled')) {
            $host = untrailingslashit(get_rest_url());
        }

        return [
            'restKey' => get_option('captcha_at_rest_key'),
            'publicKey'  => get_option('captcha_at_public_key'),
            'host'  => $host,
        ];
    }

    private function getPluginData()
    {
        // ensure get_plugin_data is available
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginData = get_plugin_data($this->plugin_dir . '../wp-captcha.php');

        return $pluginData;
    }

    public function options_page()
    {
        // define vars for the option page to use

        // configErrors => used to display errors/notices on option settings page
        $configMessages = [];
        $apiData = [];

        // values from options
        $apiOptions = $this->getApiOptions();
        $restKey = $apiOptions['restKey'];

        // use host from options (no proxy)
        $host = get_option('captcha_at_host');

        // fetch personal info route
        $apiResp = $this->apiFetchPersonal($host, $restKey);

        // get latest sdk version from api
        $assetVersionObj = $this->apiFetchLatestVersion($this->endpoint);

        // error object for further use
        $errorConnection = $this->notice('error', __('Connection failed - Verify your settings!', 'captcha-eu'));

        // define date format
        $dateFormat = 'd.m.Y H:i:s';

        // add error if invalid; fill template data if valid
        if (! $apiResp) {
            $configMessages[] = $errorConnection;
        } else {
            // build object for partial
            $apiData['user'] = [
                [
                    'class' => 'name',
                    'data' => [__('Name', 'captcha-eu') => $apiResp->name ?? ''],
                ],
                [
                    'class' => 'e-mail',
                    'data' => [__('E-Mail', 'captcha-eu') => $apiResp->email ?? ''],
                ],
                [
                    'class' => 'created_at',
                    'data' => [__('Created at', 'captcha-eu') => date($dateFormat, strtotime($apiResp->created_at ?? ''))],
                ],
            ];
            $apiData['plan'] = [
                [
                    'class' => '',
                    'data' => [__('Name', 'captcha-eu') => $apiResp->plan->name ?? ''],
                ],
            ];

            // check if user has infinite plan
            $isInfinite = isset($apiResp->infiniteTill) && time() < strtotime($apiResp->infiniteTill);

            if ($isInfinite) {
                // use infinite symbol
                $validationsLeft = '∞';
            } else {
                // use validations from response
                $validationsLeft = $apiResp->validationsLeft ?? '';
            }

            // Add Validation Stats
            $apiData['plan'][] = [
                'class' => '',
                'data' => [__('Validations left', 'captcha-eu') => $validationsLeft],
            ];

            // skip period rows if infinite
            if (! $isInfinite) {
                $apiData['plan'][] = [
                    'class' => '',
                    'data' => [__('Validations Total', 'captcha-eu') => $apiResp->plan->validations ?? ''],
                ];
            }

            // hard validations
            $apiData['plan'][] = [
                'class' => '',
                'data' => [__('Hard Validations', 'captcha-eu') => $apiResp->hardValidations ?? ''],
            ];

            if (! $isInfinite) {
                // hide start if date not set/invalid
                if (strtotime($apiResp->plan->period->start) > 0) {
                    $apiData['plan'][] = [
                        'class' => '',
                        'data' => [__('Period Start', 'captcha-eu') => date($dateFormat, strtotime($apiResp->plan->period->start ?? ''))],
                    ];
                }
                // hide start if date not set/invalid
                if (strtotime($apiResp->plan->period->end) > 0) {
                    $apiData['plan'][] = [
                        'class' => '',
                        'data' => [__('Period End', 'captcha-eu') =>  date($dateFormat, strtotime($apiResp->plan->period->end ?? ''))],
                    ];
                }
            } else {
                // calculate days left
                $now = new \DateTime('now');
                $until = new \DateTime(date($dateFormat, strtotime($apiResp->infiniteTill ?? '')));
                $daysLeft = $now->diff($until)->days;

                $apiData['plan'][] = [
                    'class' => '',
                    'data' => [__('Days left', 'captcha-eu') => $daysLeft],
                ];
            }

            // hide last payment if date is invalid
            if (strtotime($apiResp->lastPay ?? '') > 0) {
                // Last Payment
                $apiData['plan'][] = [
                    'class' => '',
                    'data' => [__('Last Payment', 'captcha-eu') => date($dateFormat, strtotime($apiResp->lastPay ?? ''))],
                ];
            }
        }

        // options -> supplies helper functions to build panels/inputs
        $options = $this->core->options;

        // iconURLHeader -> used for header img src
        $iconURLHeader = $this->plugin_dir_url . 'assets/img/captcha-at-logo-color.svg';

        // URLS (dashboard etc)
        $urls = $this->urls;

        // receive plugin data to display eg. version
        $pluginData = $this->getPluginData();
        $pluginInfo = [
            [
                'class' => '',
                'data' => [__('Version', 'captcha-eu') => $pluginData['Version']],
            ],
        ];

        // if assetVersionObj valid => add SDK version to data
        if (isset($assetVersionObj->version) && isset($assetVersionObj->asset)) {
            $pluginInfo[] = [
                'class' => '',
                'data' => ['SDK Version' => $assetVersionObj->version . '-' . $assetVersionObj->asset],
            ];
        }

        // include options.php
        require $this->plugin_dir . '/partials/options.php';

        // include chatbubble
        require $this->plugin_dir . '/partials/chat.php';
    }

    public function options_page_settings()
    {
        // configErrors => used to display errors/notices on option settings page
        $configMessages = [];

        // values from options
        $apiOptions = $this->getApiOptions();
        $restKey = $apiOptions['restKey'];

        // use host from options (no proxy)
        $host = get_option('captcha_at_host');

        // fetch personal route
        $apiResp = $this->apiFetchPersonal($host, $restKey);

        // check if valid response
        if (! $apiResp) {
            $configMessages[] = $this->notice('error', __('Connection failed - Verify your settings!', 'captcha-eu'));
        } else {
            if (isset($_GET['settings-updated']) && 'true' == $_GET['settings-updated']) {
                $configMessages[] = $this->notice('success', __('Settings updated', 'captcha-eu'));
            }
        }

        // check if feature_proxy
        $showProxy = isset($apiResp->showProxy) && $apiResp->showProxy;

        // error object for further use
        $errorConnection = $this->notice('error', 'Connection failed');

        // settingsUpdated -> true if settings were updated
        $settingsUpdated = isset($_GET['settings-updated']) && $_GET['settings-updated'];

        // options -> supplies helper functions to build panels/inputs
        $options = $this->core->options;

        // iconURLHeader -> used for header img src
        $iconURLHeader = $this->plugin_dir_url . 'assets/img/captcha-at-logo-color.svg';

        // URLS (dashboard etc)
        $urls = $this->urls;

        // include options.php
        require $this->plugin_dir . '/partials/options-settings.php';

        // include chatbubble
        require $this->plugin_dir . '/partials/chat.php';
    }
}
