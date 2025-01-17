<?php
if (!defined('ABSPATH')) {
    exit;
}

class CBI_Hook_Themes extends CBI_Hook_Base
{

    public function hooks_theme_modify($location, $status)
    {
        if (false !== strpos($location, 'theme-editor.php?file=')) {
            if (!empty($_POST) && 'update' === sanitize_text_field($_POST['action'])) {
                $cbi_args = array(
                    'action' => 'file_updated',
                    'object_type' => 'Themes',
                    'object_subtype' => 'theme_unknown',
                    'object_id' => 0,
                    'object_name' => 'file_unknown',
                );

                if (!empty(sanitize_text_field($_POST['file']))) {
                    $cbi_args['object_name'] = sanitize_text_field($_POST['file']);
                }

                if (!empty(sanitize_text_field($_POST['theme']))) {
                    $cbi_args['object_subtype'] = sanitize_text_field($_POST['theme']);
                }

                cbi_insert_log($cbi_args);
            }
        }

        return $location;
    }

    public function hooks_switch_theme($new_name, WP_Theme $new_theme)
    {
        cbi_insert_log(
            array(
                'action' => 'activated',
                'object_type' => 'Themes',
                'object_subtype' => $new_theme->get_stylesheet(),
                'object_id' => 0,
                'object_name' => $new_name,
            )
        );
    }

    public function hooks_theme_customizer_modified(WP_Customize_Manager $obj)
    {
        $cbi_args = array(
            'action' => 'updated',
            'object_type' => 'Themes',
            'object_subtype' => $obj->theme()->display('Name'),
            'object_id' => 0,
            'object_name' => 'Theme Customizer',
        );

        if ('customize_preview_init' === current_filter()) {
            $cbi_args['action'] = 'accessed';
        }

        cbi_insert_log($cbi_args);
    }

    public function hooks_theme_deleted()
    {
        $backtrace_history = debug_backtrace();

        $delete_theme_call = null;
        foreach ($backtrace_history as $call) {
            if (isset($call['function']) && 'delete_theme' === $call['function']) {
                $delete_theme_call = $call;
                break;
            }
        }

        if (empty($delete_theme_call)) {
            return;
        }

        $name = $delete_theme_call['args'][0];

        cbi_insert_log(
            array(
                'action' => 'deleted',
                'object_type' => 'Themes',
                'object_name' => $name,
            )
        );
    }

    /**
     * @param Theme_Upgrader $upgrader
     * @param array $extra
     */
    public function hooks_theme_install_or_update($upgrader, $extra)
    {
        if (!isset($extra['type']) || 'theme' !== $extra['type']) {
            return;
        }

        if ('install' === $extra['action']) {
            $slug = $upgrader->theme_info();
            if (!$slug) {
                return;
            }

            wp_clean_themes_cache();
            $theme = wp_get_theme($slug);
            $name = $theme->name;
            $version = $theme->version;

            cbi_insert_log(
                array(
                    'action' => 'installed',
                    'object_type' => 'Themes',
                    'object_name' => $name,
                    'object_subtype' => $version,
                )
            );
        }

        if ('update' === $extra['action']) {
            if (isset($extra['bulk']) && true == $extra['bulk']) {
                $slugs = $extra['themes'];
            } else {
                $slugs = array($upgrader->skin->theme);
            }

            foreach ($slugs as $slug) {
                $theme = wp_get_theme($slug);
                $stylesheet = $theme['Stylesheet Dir'] . '/style.css';
                $theme_data = get_file_data($stylesheet, array('Version' => 'Version'));

                $name = $theme['Name'];
                $version = $theme_data['Version'];

                cbi_insert_log(
                    array(
                        'action' => 'updated',
                        'object_type' => 'Themes',
                        'object_name' => $name,
                        'object_subtype' => $version,
                    )
                );
            }
        }
    }

    public function __construct()
    {
        add_filter('wp_redirect', array(&$this, 'hooks_theme_modify'), 10, 2);
        add_action('switch_theme', array(&$this, 'hooks_switch_theme'), 10, 2);
        add_action('delete_site_transient_update_themes', array(&$this, 'hooks_theme_deleted'));
        add_action('upgrader_process_complete', array(&$this, 'hooks_theme_install_or_update'), 10, 2);

        add_action('customize_save', array(&$this, 'hooks_theme_customizer_modified'));

        parent::__construct();
    }

}
