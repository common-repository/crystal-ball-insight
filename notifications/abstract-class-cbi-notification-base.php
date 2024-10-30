<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class, handles notifications
 *
 * Class CBI_Notification_Base
 */
abstract class CBI_Notification_Base
{
    /**
     *
     */
    public $id = '';
    public $name = '';
    public $description = '';

    public $cbi_options;

    public function __construct()
    {
        $this->cbi_options = CBI_Main::instance()->settings->get_options();

        add_action('init', array(&$this, 'init'), 30);
        add_action('cbi_validate_options', array(&$this, '_validate_options'), 10, 2);
    }

    private function settings_field_name_attr($name)
    {
        return esc_attr("notification_handler_options_{$this->id}[{$name}]");
    }

    public function init()
    {}

    /**
     *
     */
    public function settings_fields()
    {}

    /**
     *
     */
    public function trigger($args)
    {}

    public function _settings_section_callback()
    {
        echo '<p>' . esc_html($this->description) . '</p>';
    }

    public function _settings_enabled_field_callback($args = array())
    {
        CBI_Settings_Fields::yesno_field($args);
    }

    public function add_settings_field_helper($option_name, $title, $callback, $description = '', $default_value = '')
    {
        $settings_page_slug = CBI_Main::instance()->settings->slug();
        $handler_options = isset($this->cbi_options["handler_options_{$this->id}"])
        ? $this->cbi_options["handler_options_{$this->id}"] : array();

        add_settings_field(
            "notification_handler_{$this->id}_{$option_name}",
            $title,
            $callback,
            $settings_page_slug,
            "notification_{$this->id}",
            array(
                'name' => $this->settings_field_name_attr($option_name),
                'value' => isset($handler_options[$option_name]) ? $handler_options[$option_name] : $default_value,
                'desc' => $description,
                'id' => $option_name,
                'page' => $settings_page_slug,
            )
        );
    }

    public function _validate_options($form_data, $cbi_options)
    {
        $post_key = "notification_handler_options_{$this->id}";
        $option_key = "handler_options_{$this->id}";

        if (!isset($_POST[$post_key])) {
            return $form_data;
        }

        $input = sanitize_text_field($_POST[$post_key]);
        $output = (method_exists($this, 'validate_options')) ? $this->validate_options($input) : array();
        $form_data[$option_key] = $output;

        return $form_data;
    }

    public function get_handler_options()
    {
        $handler_options = array();
        $option_key = "handler_options_{$this->id}";

        if (isset($this->cbi_options[$option_key])) {
            $handler_options = (array) $this->cbi_options[$option_key];
        }

        return $handler_options;
    }

    public function prep_notification_body($args)
    {
        $details_to_provide = array(
            'user_id' => __('User', 'crystal-ball-insight'),
            'object_type' => __('Object Type', 'crystal-ball-insight'),
            'object_name' => __('Object Name', 'crystal-ball-insight'),
            'action' => __('Action Type', 'crystal-ball-insight'),
            'hist_ip' => __('IP Address', 'crystal-ball-insight'),
        );
        $message = '';

        foreach ($details_to_provide as $detail_key => $detail_title) {
            $detail_val = '';

            switch ($detail_key) {
                case 'user_id':
                    if (is_numeric($args[$detail_key])) {
                        $user = new WP_User($args[$detail_key]);

                        if (!is_wp_error($user)) {
                            $detail_val = sprintf('<a href="%s">%s</a>', esc_url(get_edit_user_link($user->ID)), esc_html($user->display_name));
                        }
                    }
                    break;
                default:
                    $detail_val = isset($args[$detail_key]) ? $args[$detail_key] : __('N/A', 'crystal-ball-insight');
                    break;
            }

            $message .= sprintf("<strong>%s</strong> - %s\n", $detail_title, $detail_val);
        }

        return $message;
    }
}

function cbi_register_notification_handler($classname = '')
{
    return CBI_Main::instance()->notifications->register_handler($classname);
}
