<?php

namespace CAPTCHA\Plugin;

class Frontend
{
    private $plugin_dir;
    private $wpdb;
    private $core;

    public function __construct($core)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_dir = plugin_dir_url(__FILE__) . '../';
        $this->core = $core;
    }

    public function add_filters()
    {
    }
}
