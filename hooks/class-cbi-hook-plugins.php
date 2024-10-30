<?php
if (!defined('ABSPATH')) {
    exit;
}

class CBI_Hook_Plugins extends CBI_Hook_Base
{

    protected function _add_log_plugin($action, $plugin_name)
    {
        if (false !== strpos($plugin_name, '/')) {
            $plugin_dir = explode('/', $plugin_name);
            $plugin_data = array_values(get_plugins('/' . $plugin_dir[0]));
            $plugin_data = array_shift($plugin_data);
            $plugin_name = $plugin_data['Name'];
        }

        cbi_insert_log(
            array(
                'action' => $action,
                'object_type' => 'Plugins',
                'object_id' => 0,
                'object_name' => $plugin_name,
            )
        );
    }

    public function hooks_deactivated_plugin($plugin_name)
    {
        $this->_add_log_plugin('deactivated', $plugin_name);
    }

    public function hooks_activated_plugin($plugin_name)
    {
        $this->_add_log_plugin('activated', $plugin_name);
    }

    public function hooks_plugin_modify($location, $status)
    {
        if (false !== strpos($location, 'plugin-editor.php')) {
            if ((!empty($_POST) && 'update' === sanitize_text_field($_REQUEST['action']))) {
                $cbi_args = array(
                    'action' => 'file_updated',
                    'object_type' => 'Plugins',
                    'object_subtype' => 'plugin_unknown',
                    'object_id' => 0,
                    'object_name' => 'file_unknown',
                );

                if (!empty(sanitize_text_field($_REQUEST['file']))) {
                    $cbi_args['object_name'] = sanitize_text_field($_REQUEST['file']);
                    $plugin_dir = explode('/', sanitize_text_field($_REQUEST['file']));
                    $plugin_data = array_values(get_plugins('/' . $plugin_dir[0]));
                    $plugin_data = array_shift($plugin_data);

                    $cbi_args['object_subtype'] = $plugin_data['Name'];
                }
                cbi_insert_log($cbi_args);
            }
        }

        return $location;
    }

    /**
     * @param Plugin_Upgrader $upgrader
     * @param array $extra
     */
    public function hooks_plugin_install_or_update($upgrader, $extra)
    {
        if (!isset($extra['type']) || 'plugin' !== $extra['type']) {
            return;
        }

        if ('install' === $extra['action']) {
            $path = $upgrader->plugin_info();
            if (!$path) {
                return;
            }

            $data = get_plugin_data($upgrader->skin->result['local_destination'] . '/' . $path, true, false);

            cbi_insert_log(
                array(
                    'action' => 'installed',
                    'object_type' => 'Plugins',
                    'object_name' => $data['Name'],
                    'object_subtype' => $data['Version'],
                )
            );
        }

        if ('update' === $extra['action']) {
            if (isset($extra['bulk']) && true == $extra['bulk']) {
                $slugs = $extra['plugins'];
            } else {
                $plugin_slug = isset($upgrader->skin->plugin) ? $upgrader->skin->plugin : $extra['plugin'];

                if (empty($plugin_slug)) {
                    return;
                }

                $slugs = array($plugin_slug);
            }

            foreach ($slugs as $slug) {
                $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug, true, false);

                cbi_insert_log(
                    array(
                        'action' => 'updated',
                        'object_type' => 'Plugins',
                        'object_name' => $data['Name'],
                        'object_subtype' => $data['Version'],
                    )
                );
            }
        }
    }

    public function __construct()
    {
        add_action('activated_plugin', array(&$this, 'hooks_activated_plugin'));
        add_action('deactivated_plugin', array(&$this, 'hooks_deactivated_plugin'));
        add_filter('wp_redirect', array(&$this, 'hooks_plugin_modify'), 10, 2);

        add_action('upgrader_process_complete', array(&$this, 'hooks_plugin_install_or_update'), 10, 2);

        parent::__construct();
    }

}
