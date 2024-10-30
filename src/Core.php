<?php

namespace CAPTCHA\Plugin;

class Core
{
    private $plugin_dir;
    private $plugin_dir_url;
    private $wpdb;
    public $admin;
    private $frontend;
    public $options;
    private $fragProtect;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_dir = plugin_dir_url(__FILE__) . '../';

        $this->admin = new Admin($this);
        $this->frontend = new Frontend($this);
        $this->options = new Options($this);
        $this->fragProtect = new FragProtect($this);
    }

    public function getRealIP()
    {
        // Check if the header is present
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get the IP addresses from the header
            $ipAddresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            // Loop through the IP addresses and return the last non-private IP
            foreach ($ipAddresses as $ip) {
                $ip = trim($ip);

                // Check if the IP is private
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to the remote address if the header is not present or no non-private IP is found
        return $_SERVER['REMOTE_ADDR'];
    }

    public function anonymizeIP($ip)
    {
        // Check if the IP address is IPv4 or IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', $ip);

            // Randomize the last two segments
            $segments[2] = mt_rand(0, 255);
            $segments[3] = mt_rand(0, 255);

            // Reconstruct the anonymized IP
            $anonymizedIP = implode('.', $segments);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $segments = explode(':', $ip);

            // Randomize the last segments
            for ($i = 6; $i < count($segments); ++$i) {
                $segments[$i] = str_pad(dechex(mt_rand(0, 65535)), 4, '0', STR_PAD_LEFT);
            }

            // Reconstruct the anonymized IP
            $anonymizedIP = implode(':', $segments);
        } else {
            // Invalid IP address
            return false;
        }

        return $anonymizedIP;
    }

    public function validate($solution)
    {
        $url  = $this->admin->endpoint;
        $restKey  = get_option('captcha_at_rest_key');
        $url .= '/validate';

        // if the request is made through a proxy or load balancer
        $request_ip = $this->getRealIP();
        $request_ip = $this->anonymizeIP($request_ip);

        $payload = [
            'headers'     => ['Content-Type' => 'application/json', 'Rest-Key' => $restKey, 'User-Agent' => $_SERVER['HTTP_USER_AGENT'], 'x-real-ip' =>  $request_ip],
            'body'        => $solution,
        ];

        $data = wp_remote_post($url, $payload);

        if (is_wp_error($data)) {
            $this->admin->addError('E_VALIDATE_REMOTE_WP_ERR');

            return true;
        }
        $body = wp_remote_retrieve_body($data);
        if (empty($body)) {
            $this->admin->addError('E_VALIDATE_REMOTE_EMPTY');

            return true;
        }
        $jBody = json_decode($body);
        if (! $jBody || ! isset($jBody->success)) {
            $this->admin->addError('E_VALIDATE_REMOTE_JSON_INVALID');

            return false;
        }

        return $jBody->success;
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

}
