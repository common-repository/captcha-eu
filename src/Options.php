<?php

namespace CAPTCHA\Plugin;

class Options
{
    private $plugin_dir;
    private $plugin_dir_url;
    private $core;
    private $optionErrors;

    public function __construct($core)
    {
        $this->core = $core;

        $this->plugin_dir_url = plugin_dir_url(__FILE__) . '../';
        $this->plugin_dir = plugin_dir_path(__FILE__) . '';

        // error object
        $this->optionErrors = [];

        // add actions
        $this->add_actions();
    }

    private function add_actions()
    {
        add_action('admin_menu', [$this, 'enqueue_styles'], 20);
    }

    public function enqueue_styles()
    {
        wp_enqueue_style('captchaOptions', $this->plugin_dir_url . 'assets/css/options.css');
    }

    // render title
    public function fieldTitle($val = '', $icon = '')
    {
        // empty => don't output
        if ('' == $val) {
            return '';
        }

        $addIcon = '';
        if ('' != $icon) {
            $addIcon = '<div class="' . esc_attr($icon) . '"></div>';
        }

        $html = '<div class="title">';
        $html .= $addIcon;
        $html .= '<div class="text">' . esc_html($val) . '</div>';
        $html .='</div>';

        return $html;
    }

    // render k/v table
    public function fieldKeyValue($arrKV = [])
    {
        if ([] == $arrKV) {
            return '';
        }

        $out = '';
        $out .= '<div class="key-value">';

        foreach ($arrKV as $obj) {
            // key / value from data
            $key = array_keys($obj['data'])[0];
            $val = array_values($obj['data'])[0];

            $addClass = '' != $obj['class'] ? 'kv_' . strtolower(str_replace(' ', '_', $obj['class'])) : '';
            $out .= '<div class="' . esc_attr($addClass) . '">';
            $out .= '<div class="key">' . esc_html($key) . '</div>';
            $out .= '<div class="val">' . esc_html($val) . '</div>';
            $out .= '</div>';
        }

        $out .= '</div>';

        return $out;
    }

    // wrap items in div
    public function wrapInDiv($class = '', $content = [])
    {
        $out = '';

        if ([] == $content) {
            return $out;
        }

        $out .= '<div class="' . esc_attr($class) . '">';
        foreach ($content as $item) {
            $out .= $item;
        }
        $out .= '</div>';

        return $out;
    }

    // render label
    public function fieldLabel($for = '', $val = '')
    {
        // empty => don't output
        if ('' == $for || '' == $val) {
            return '';
        }

        return '<label for="' . esc_attr($for) . '">' . esc_attr($val) . '</label>';
    }

    // render info icon with title text
    public function fieldInfoIcon($title = '')
    {
        // empty => don't output
        if ('' == $title) {
            return '';
        }

        return '<div class="info-icon" title="' . esc_attr($title) . '"></div>';
    }

    // render input field with type text
    public function fieldInputText($name = '', $val = '', $placeholder = '')
    {
        // empty => don't output
        if ('' == $name) {
            return '';
        }

        return '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" placeholder="' . esc_attr($placeholder) . '"/>';
    }

    // render textarea field
    public function fieldInputTextarea($name = '', $val = '', $placeholder = '')
    {
        // empty => don't output
        if ('' == $name) {
            return '';
        }

        return '<textarea name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea($val) . '</textarea>';
    }

    // render input field with type checkbox & corresponding labels
    public function fieldInputCheckbox($name = '', $options = [], $checked = '', $multiple = false)
    {
        // empty => don't output
        if ('' == $name || [] == $options) {
            return '';
        }

        // append [] to checkbox name if multiple choice
        $multiple = $multiple ? '[]' : '';

        $output = '';
        $output .= '<div class="check-items">';

        foreach ($options as $optionTitle => $optionItems) {
            $iconClass = '' != $optionItems['icon'] ? ' ' . $optionItems['icon'] : '';

            // don't output group if title empty
            $output .= '' != $optionTitle ? '<a target="_blank" class="grp' . esc_attr($iconClass) . '" href="' . esc_url($optionItems['link']) . '">' . esc_html($optionTitle) . '</a>' : '';

            // output all options & labels
            foreach ($optionItems['choices'] as $optK => $optV) {
                // set checked attribute if item is selected
                $checkedAttrStr = (is_array($checked) && in_array($optK, $checked)) || $checked == $optK ? 'checked' : '';

                // output wrapped input/label
                $output .= '<div class="check-item">';
                $output .= '<input type="checkbox" id="' . esc_attr($optK) . '" name="' . esc_attr($name) . $multiple . '" value="' . esc_attr($optK) . '" ' . esc_html($checkedAttrStr) . ' />';
                $output .= '<label for="' . esc_attr($optK) . '">' . esc_html($optV) . '</label>';
                $output .= '</div>';
            }
        }
        $output .= '</div>';

        return $output;
    }

    // render panel with message
    public function panelMSG($type = '', $msg = '')
    {
        // empty => don't output
        if ('' == $msg) {
            return '';
        }

        // add icon if supplied
        $iconStr = '' != $type ? '<div class="icon t-' . esc_attr($type) . '"></div>' : '';

        // wrap messasge div in settings-panel
        return $this->settingsPanel(['<div class="msg">' . $iconStr . esc_html($msg) . '</div>'], $type);
    }

    // render panel with message
    public function settingsPanel($fields = [], $class = '')
    {
        // empty => don't output
        if (empty($fields)) {
            return '';
        }

        // if css class is set
        $addClass = ! empty($class) ? ' t-' . $class : '';

        // combine field contents
        $contentStr = implode("\r\n", $fields);

        // output content wrapped in settings-panel
        return '<div class="panel' . esc_attr($addClass) . '">' . $contentStr . '</div>';
    }
}
