<?php

namespace CAPTCHA\Plugin;

class FragProtect
{
    private $core;

    public function __construct($core)
    {
        $this->core = $core;
        $this->plugin_dir_url = plugin_dir_url(__FILE__) . '../';
        $fragProtect = get_option('captcha_at_fragprotect');
        $fragProtectEnabled = $fragProtect && count($fragProtect) > 0;
        $this->features = $fragProtect;
        $this->enabled = $fragProtectEnabled;
        $this->add_filters();
    }

    function cpt_ajax_callback()
    {
        // Check for nonce for security here if needed
        $data = $_POST['data'];
        $captcha = $data["cpt"];
        $payload = $data["crypted"];
        $result = (object) ["status" => "OK", "result" => $this->decryptPayload($payload)];
        if (!$this->core->validate(sanitize_text_field(wp_unslash($captcha)))) {
            $result = (object) ["status" => "FAILED", "result" => $payload];
        }
        echo json_encode($result);
        wp_die();
    }

    function inline_css()
    {
        wp_enqueue_style('cpt-frag-protect-css', $this->plugin_dir_url . 'assets/css/fragprotect.css');
        wp_enqueue_script('cpt-frag-protect', $this->plugin_dir_url . 'assets/js/fragprotect.js?v33444a', ['jquery']);
        wp_localize_script('cpt-frag-protect', 'cptFragAjax', array('ajaxurl' => admin_url('admin-ajax.php')));



    }
    public function featureEnabled($feature)
    {
        return in_array($feature, $this->features);
    }
    function captcha_protect($atts = [], $content = null)
    {
        $atts = shortcode_atts([
            'gate' => 'Click here to reveal content',
        ], $atts, 'captcha_protect');

        $output = $this->wrap_it($content, $atts['gate']);
        return $output;
    }

    function filter_all_blocks_rendering($block_content, $block)
    {
        if (!$this->featureEnabled("frag-protect-block-feature")) {
            return $block_content;
        }
        // Check the block's name or attributes
        // For example, if you want to hide blocks with a specific class
        if (isset($block['attrs']['captchaProtect']) && $block['attrs']['captchaProtect'] == true) {
            $txt = isset($block['attrs']['captchaGateText']) && $block['attrs']['captchaGateText'] != "" ? $block['attrs']['captchaGateText'] : "Click to reveal content";
            $blockGate = '<img src="https://www.captcha.eu/wp-content/uploads/2024/02/quick_and_easy.jpg"><br>' . $txt;

            return $this->wrap_it($block_content, $blockGate, "p");
        }
        return $block_content;
    }
    public function add_filters()
    {
        if (!$this->enabled)
            return;
        // get selected plugins from option
        $selectedPlugins = get_option('captcha_at_plugin');

        // if no plugin is checked
        if (!is_array($selectedPlugins)) {
            $selectedPlugins = [];
        }
        add_action('enqueue_block_editor_assets', function () {
            if ($this->featureEnabled("frag-protect-block-feature")) {
                wp_enqueue_script('cpt-frag-protect-blog', $this->plugin_dir_url . 'assets/js/block.js', ['jquery','wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor']);
            }
        });

        
        add_filter('render_block', [$this, 'filter_all_blocks_rendering'], 9000, 2);
        
        if ($this->featureEnabled("frag-protect-shortcode-feature")) {
            add_shortcode('captcha_protect', [$this, 'captcha_protect']);
        }

        add_action('wp_enqueue_scripts', [$this, 'inline_css']);

        if ($this->featureEnabled("frag-protect-email-posts")) {
            add_filter('the_content', [$this, 'frag_protect_content'], 9000);
        }

        if ($this->featureEnabled("frag-protect-email-comments")) {
            add_filter('comment_text', [$this, 'frag_protect_content'], 9000);
        }


        if ($this->featureEnabled("frag-protect-email-rss")) {
            add_filter('the_content_rss', [$this, 'frag_protect_content'], 9000);
            add_filter('the_excerpt_rss', [$this, 'frag_protect_content'], 9000);
        }

        if ($this->featureEnabled("frag-protect-email-rss-comments")) {
            add_filter('comment_text_rss', [$this, 'frag_protect_content'], 9000);

        }


        add_action('wp_ajax_cpt_decrypt', [$this, 'cpt_ajax_callback']); // For logged-in users
        add_action('wp_ajax_nopriv_cpt_decrypt', [$this, 'cpt_ajax_callback']); // For guests

    }

    function wrap_it($input, $show, $rootTag = "span")
    {
        return '
        <' . $rootTag . ' class="captcha_mailhide_root" title="Click to Unhide Email" data-payload="' . $this->cryptPayload($input) . '" tabindex="0">
        <img class="inline_logo" src="https://www.captcha.eu/wp-content/uploads/2024/02/image-1.png" width="10" alt="Here is a hidden email address. Click on it, and the readable address will appear in the same place shortly."  /> <span aria-hidden="true" aria-live="polite" class="captcha_real_mail" >' . $show . '</span>
            <span class="captcha_mailhide_slider inactive"></span>
            <span class="screen-reader-status" aria-live="assertive" style="position: absolute; left: -9999px;"></span>
        </' . $rootTag . '> 
    
        ';

    }
    function email_as_parts($email)
    {
        // Split the email into local part and domain
        $arr = preg_split("/@/", $email);

        // Obfuscate the local part based on its length
        if (strlen($arr[0]) <= 4) {
            $arr[0] = substr($arr[0], 0, 1) . "...";
        } else if (strlen($arr[0]) <= 6) {
            $arr[0] = substr($arr[0], 0, 3) . "...";
        } else {
            $arr[0] = substr($arr[0], 0, 4) . "...";
        }

        // Optionally, obfuscate the domain part similarly or replace with a generic placeholder
        // For simplicity, we'll just indicate the domain is obfuscated with dots
        $domainParts = explode('.', $arr[1]);
        if (count($domainParts) > 1) {
            // Replace all but the last domain part (TLD) with dots
            $arr[1] = "..." . "." . end($domainParts);
        } else {
            // If there's no TLD discernible, just use dots
            $arr[1] = "...";
        }

        // Return the obfuscated email
        return trim($arr[0] . "@" . $arr[1]);
    }
    // replace the hyperlinked emails i.e. <a href="haha@lol.com">this</a> or <a href="mailto:haha@lol.com">that</a>
    function email_linked($matches)
    {

        $email = $matches[1];
        $html = $this->wrap_it($matches[0], $this->email_as_parts($email));

        return $html;
    }
    function email_text($matches)
    {
        //return "<span>aaa</span>";
        //print_r($matches);
        $email = $matches[0];

        $html = $this->wrap_it($email, $this->email_as_parts($email));
        return $html;
    }
    function raw($matches)
    {

        return $this->wrap_it($matches[1], $matches[1]);


    }
    function urlbase64_decode($x)
    {
        return base64_decode(strtr($x, '-_', '+/'));
    }
    function _unpad($string, $block_size = 16)
    {
        $len = strlen($string);
        $pad = ord($string[$len - 1]);
        if ($pad && $pad <= $block_size) {
            $pm = substr($string, -$pad);
            if (preg_match('/' . chr($pad) . '{' . $pad . '}/', $pm)) {
                return substr($string, 0, $len - $pad);
            }
        }
        return $string; // Return string unmodified if padding is invalid
    }
    function decryptPayload($payload)
    {
        $payload = $this->urlbase64_decode($payload); // Decode from URL-safe base64 to regular base64

        $decoded = base64_decode($payload, true); // Decode the base64-encoded input
        if ($decoded === false) {
            // Handle decoding error
            throw new Exception("Decoding failed");
        }

        $method = 'AES-128-CBC';
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($decoded, 0, $iv_length); // Extract the IV from the beginning
        $encrypted_data = substr($decoded, $iv_length); // Extract the encrypted data

        $key = get_option('captcha_at_rest_key'); // Assume this fetches the binary key directly
        $decrypted = openssl_decrypt($encrypted_data, $method, $key, OPENSSL_RAW_DATA, $iv);

        return $this->_unpad($decrypted); // Assuming PKCS7 padding is used, implement _unpad accordingly
    }
    function cryptPayload($val)
    {
        return $this->urlbase64($this->crypt_string($val));
    }
    function urlbase64($x)
    {
        return strtr(base64_encode($x), '+/', '-_');
    }
    function crypt_string($val)
    {
        $method = 'AES-128-CBC'; // Equivalent to MCRYPT_RIJNDAEL_128 in CBC mode
        $key = get_option('captcha_at_rest_key'); // Convert the hex key to binary
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method)); // Generate an IV

        // Pad the value to be encrypted (if necessary)
        $val = $this->_pad($val); // Ensure you have implemented _pad function correctly for block cipher

        $encrypted = openssl_encrypt($val, $method, $key, OPENSSL_RAW_DATA, $iv);

        // Prepend the IV for it to be available for decryption
        $encrypted_with_iv = base64_encode($iv . $encrypted);

        return $encrypted_with_iv;
    }

    /**
     * Implement padding function if not already defined. 
     * PKCS7 padding is commonly used with AES.
     */
    function _pad($string, $block_size = 16)
    {
        $pad = $block_size - (strlen($string) % $block_size);
        return $string . str_repeat(chr($pad), $pad);
    }
    public function frag_protect_content($content)
    {
        if (!$this->enabled) {
            return $content;
        }
        // match emails
        // this seems to no longer be necessary because wordpress automatically linkifies all plaintext emails
        $regex = '/\b([\w.+-]+@[a-z\d.-]+\.[a-z]{2,6})\b(?!\s*\[\/nohide\]|(?:(?!<a[^>]*>).)*<\/a>)/i';
        $content = preg_replace_callback($regex, [$this, "email_text"], $content);

        // match hyperlinks with emails
        $regex = '/(?!\[nohide\])<a[^>]*href="((?:mailto:)?([^@"]+@[^@"]+))"[^>]*>(.+?)<\/a>(?!\[\/nohide\])/i';
        $content = preg_replace_callback($regex, [$this, "email_linked"], $content);

        // remove nohide helpers
        $content = preg_replace('/\[\/?nohide\]/i', '', $content);

        // Also Protect Fragments
        // $regex = "/\[cpt-frag-protect(?:\s+title='[^'].*')?\](.*?)\[\/cpt-frag-protect\]/i";
        // $content = preg_replace_callback($regex, [$this, "raw"], $content);




        return $content;
    }
    public function frag_protect($type = "email", $input = "")
    {

    }

}