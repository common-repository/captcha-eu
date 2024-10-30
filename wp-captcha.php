<?php
/**
 * Plugin Name: Captcha.eu
 * Description: Captcha.eu provides a GDPR compliant protection against bots and spammers.
 * Plugin URI:  https://www.captcha.eu
 * Version:     1.0.41
 * Author:      captchaeu
 * Author URI:  https://profiles.wordpress.org/captchaeu/
 * Licence:     GPL
 * Text Domain: captcha-eu
 * Domain Path: /languages.
 */

namespace CAPTCHA\Plugin;

if (! function_exists('add_filter')) {
    return;
}

/**
 * Ensure that autoload is already loaded or loads it if available.
 *
 * @param string $class_name
 *
 * @return bool
 */
function ensure_class_loaded($class_name) {
    $class_exists   = class_exists($class_name);
    $autoload_found = file_exists(__DIR__ . '/vendor/autoload.php');

    // If the class does not exist, and the vendor file is there
    // maybe the plugin was installed separately via Composer, let's try to load autoload
    if (! $class_exists && $autoload_found) {
        @require_once __DIR__ . '/vendor/autoload.php';
    }

    return $class_exists || ($autoload_found && class_exists($class_name));
}

// Exit if classes are not available
if (! ensure_class_loaded(__NAMESPACE__ . '\Core')) {
    return;
}

add_action('plugins_loaded', __NAMESPACE__ . '\main');

register_activation_hook(__FILE__, function () {
    // set activation transient
    set_transient('captcha-at-notice-activation', true, 5);

    if (! wp_next_scheduled('captcha_at_sched_sdk_version')) {
        // schedule sdk version update event
        $created = wp_schedule_event(time() + 5, 'hourly', 'captcha_at_sched_sdk_version');
    }
});

/**
 * @wp-hook plugins_loaded
 */
function main() {

    load_plugin_textdomain(
        'captcha-eu',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );


    if (is_admin()) {
        if (! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
    $core = new Core();
    // deactivated
    register_deactivation_hook(__FILE__, [$core->admin, 'plugin_deactivated']);
}
