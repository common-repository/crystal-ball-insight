<?php
if (!defined('ABSPATH')) {
    exit;
}

class CBI_Hook_Widgets extends CBI_Hook_Base
{

    public function hooks_widget_update_callback($instance, $new_instance, $old_instance, WP_Widget $widget)
    {
        $cbi_args = array(
            'action' => 'updated',
            'object_type' => 'Widget',
            'object_subtype' => 'sidebar_unknown',
            'object_id' => 0,
            'object_name' => $widget->id_base,
        );

        if (empty(sanitize_text_field($_REQUEST['sidebar']))) {
            return $instance;
        }

        cbi_insert_log($cbi_args);

        return $instance;
    }

    public function hooks_widget_delete()
    {
        if ('post' == strtolower(sanitize_text_field($_SERVER['REQUEST_METHOD'])) && !empty(sanitize_text_field($_REQUEST['widget-id']))) {
            if (isset($_REQUEST['delete_widget']) && 1 === (int) sanitize_text_field($_REQUEST['delete_widget'])) {
                cbi_insert_log(array(
                    'action' => 'deleted',
                    'object_type' => 'Widget',
                    'object_subtype' => strtolower(sanitize_text_field($_REQUEST['sidebar'])),
                    'object_id' => 0,
                    'object_name' => sanitize_text_field($_REQUEST['id_base']),
                ));
            }
        }
    }

    public function __construct()
    {
        add_filter('widget_update_callback', array(&$this, 'hooks_widget_update_callback'), 9999, 4);
        add_filter('sidebar_admin_setup', array(&$this, 'hooks_widget_delete'));

        parent::__construct();
    }

}
