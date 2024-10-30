<?php
if (!defined('ABSPATH')) {
    exit;
}

class CBI_Settings
{
    private $hook;
    public $slug = 'crystal-ball-settings';
    protected $options;

    public function __construct()
    {
        add_action('init', array(&$this, 'init'));
        add_action('admin_menu', array(&$this, 'action_admin_menu'), 30);
        add_action('admin_init', array(&$this, 'register_settings'));
        add_action('admin_notices', array(&$this, 'admin_notices'));
        add_action('admin_footer', array(&$this, 'admin_footer'));
        add_filter('plugin_action_links_' . CRYSTAL_BALL_BASE, array(&$this, 'plugin_action_links'));

        add_action('wp_ajax_cbi_reset_items', array(&$this, 'ajax_cbi_reset_items'));
        add_action('wp_ajax_cbi_get_properties', array(&$this, 'ajax_cbi_get_properties'));
    }

    public function init()
    {
        $this->options = $this->get_options();
    }

    public function plugin_action_links($links)
    {
        $settings_link = sprintf('<a href="%s" target="_blank">%s</a>', 'https://github.com/developercrystalball/crystal-ball-insight-plugin', __('GitHub', 'crystal-ball-insight'));
        array_unshift($links, $settings_link);

        $settings_link = sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=crystal-ball-settings'), __('Settings', 'crystal-ball-insight'));
        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Register the settings page
     *
     * @since 1.0
     */
    public function action_admin_menu()
    {
        $this->hook = add_submenu_page(
            'log_page',
            __('Crystal Ball Settings', 'crystal-ball-insight'),
            __('Settings', 'crystal-ball-insight'),
            'manage_options',
            $this->slug,
            array(&$this, 'display_settings_page')
        );

        add_action("admin_print_scripts-{$this->hook}", array(&$this, 'scripts_n_styles'));
    }

    /**
     * Register scripts & styles
     *
     * @since 1.0
     */
    public function scripts_n_styles()
    {
        wp_enqueue_script('cbi-settings', plugins_url('assets/js/settings.js', CRYSTAL_BALL__FILE__), array('jquery'));
        wp_enqueue_style('cbi-settings', plugins_url('assets/css/settings.css', CRYSTAL_BALL__FILE__));
    }

    public function register_settings()
    {
        if (!get_option($this->slug)) {
            update_option($this->slug, apply_filters('cbi_default_options', array(
                'logs_lifespan' => '30',
                'logs_failed_login' => 'yes',
            )));
        }

        register_setting('cbi-options', $this->slug, array($this, 'validate_options'));
        $section = $this->get_setup_section();

        switch ($section) {
            case 'general':
                add_settings_section(
                    'general_settings_section',
                    __('', 'crystal-ball-insight'),
                    array('CBI_Settings_Fields', 'general_settings_section_header'),
                    $this->slug
                );

                add_settings_field(
                    'crystalball_api_key',
                    __('Crystal Ball API Key', 'crystal-ball-insight'),
                    array('CBI_Settings_Fields', 'text_field'),
                    $this->slug,
                    'general_settings_section',
                    array(
                        'id' => 'crystalball_api_key',
                        'page' => $this->slug,
                        'type' => 'text',
                    )
                );

                break;

            case 'notifications':
                add_settings_section(
                    'email_notifications',
                    __('Notifications', 'crystal-ball-insight'),
                    array('CBI_Settings_Fields', 'email_notifications_section_header'),
                    $this->slug
                );

                add_settings_field(
                    'notification_rules',
                    __('Notification Events', 'crystal-ball-insight'),
                    array('CBI_Settings_Fields', 'email_notification_buffer_field'),
                    $this->slug,
                    'email_notifications',
                    array(
                        'id' => 'notification_rules',
                        'page' => $this->slug,
                        'desc' => __('Maximum number of days to keep crystal ball. Leave blank to keep crystal ball forever (not recommended).', 'crystal-ball-insight'),
                    )
                );

                $notification_handlers = CBI_Main::instance()->notifications->get_available_handlers();
                $enabled_notification_handlers = CBI_Main::instance()->settings->get_option('notification_handlers');

                foreach ($notification_handlers as $handler_id => $handler_obj) {
                    if (!is_object($handler_obj)) {
                        continue;
                    }

                    add_settings_section(
                        "notification_$handler_id",
                        $handler_obj->name,
                        array($handler_obj, '_settings_section_callback'),
                        $this->slug
                    );

                    add_settings_field(
                        "notification_handler_{$handler_id}_enabled",
                        __('Enable?', 'crystal-ball-insight'),
                        array($handler_obj, '_settings_enabled_field_callback'),
                        $this->slug,
                        "notification_$handler_id",
                        array(
                            'id' => 'notification_transport',
                            'page' => $this->slug,
                            'name' => "{$this->slug}[notification_handlers][{$handler_id}]",
                            'value' => isset($enabled_notification_handlers[$handler_id]) && (1 == $enabled_notification_handlers[$handler_id]),
                        )
                    );

                    $handler_obj->settings_fields();
                }
                break;
        }
    }

    /**
     * Returns the current section within CBI's setting pages
     *
     * @return string
     */
    public function get_setup_section()
    {
        if (isset($_REQUEST['cbi_section'])) {
            return strtolower(sanitize_text_field($_REQUEST['cbi_section']));
        }

        return 'general';
    }

    /**
     * Prints section tabs within the settings area
     */
    private function menu_print_tabs()
    {
        $current_section = $this->get_setup_section();
        $sections = array(
            'general' => __('General', 'crystal-ball-insight'),
        );

        $enabled_notification_handlers = CBI_Main::instance()->settings->get_option('notification_handlers');

        if (!empty($enabled_notification_handlers)) {
            $sections['notifications'] = __('Notifications', 'crystal-ball-insight');
        }

        $sections = apply_filters('cbi_setup_sections', $sections);

        if (1 >= count($sections)) {
            return;
        }

        foreach ($sections as $section_key => $section_caption) {
            $active = $current_section === $section_key ? 'nav-tab-active' : '';
            $url = add_query_arg('cbi_section', $section_key);
            echo '<a class="nav-tab ' . esc_attr($active) . '" href="' . esc_url($url) . '">' . esc_html($section_caption) . '</a>';
        }
    }

    public function validate_options($input)
    {
        $options = $this->options;

        $output = apply_filters('cbi_validate_options', $input, $options);

        $output = array_merge($options, $output);

        return $output;
    }

    public function display_settings_page()
    {
        ?>
		<div class="wrap">

			<h1 class="cbi-page-title"><?php _e('Crystal Ball Settings', 'crystal-ball-insight');?></h1>
			<?php settings_errors();?>
			<h2 class="nav-tab-wrapper"><?php $this->menu_print_tabs();?></h2>

			<form method="post" action="options.php">
				<?php
settings_fields('cbi-options');
        do_settings_sections($this->slug);
        submit_button();
        ?>
			</form>

		</div>
		<?php
}

    public function admin_notices()
    {
        switch (filter_input(INPUT_GET, 'message')) {
            case 'data_erased':
                printf('<div class="updated"><p>%s</p></div>', __('All activities have been successfully deleted.', 'crystal-ball-insight'));
                break;
        }
    }

    public function admin_footer()
    {
        ?>
		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '#cbi-delete-log-activities' ).on( 'click', function( e ) {
					if ( ! confirm( '<?php echo __('Attention: We are going to DELETE ALL ACTIVITIES from the database. Are you sure you want to do that?', 'crystal-ball-insight'); ?>' ) ) {
						e.preventDefault();
					}
				} );
			} );
		</script>
		<?php
}

    public function ajax_cbi_reset_items()
    {
        if (!check_ajax_referer('cbi_reset_items', '_nonce', false) || !current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'crystal-ball-insight'));
        }

        CBI_Main::instance()->api->erase_all_items();

        wp_redirect(add_query_arg(array(
            'page' => 'crystal-ball-settings',
            'message' => 'data_erased',
        ), admin_url('admin.php')));
        die();
    }

    public function ajax_cbi_get_properties()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $action_category = isset($_REQUEST['action_category']) ? sanitize_text_field($_REQUEST['action_category']) : false;

        $options = CBI_Main::instance()->notifications->get_settings_dropdown_values($action_category);

        if (!empty($options)) {
            wp_send_json_success($options);
        }

        wp_send_json_error();
    }

    public function get_option($key = '')
    {
        $settings = $this->get_options();
        return !empty($settings[$key]) ? $settings[$key] : false;
    }

    /**
     * Returns all options
     *
     * @since 1.0.0
     * @return array
     */
    public function get_options()
    {
        if (isset($this->options) && is_array($this->options) && !empty($this->options)) {
            return $this->options;
        }

        return apply_filters('cbi_options', get_option($this->slug, array()));
    }

    public function slug()
    {
        return $this->slug;
    }
}

final class CBI_Settings_Fields
{

    public static function general_settings_section_header()
    {
        ?>
		<h3>Insert Your <a href="<?php echo esc_url(CRYSTAL_BALL_URL); ?>/api-key" target="_blank">Crystal Ball API Key</a></h3>
		<?php
}

    public static function email_notifications_section_header()
    {
        ?>
		<p><?php _e('Serve yourself with custom-tailored notifications. First, define your conditions. Then, choose how the notifications will be sent.', 'crystal-ball-insight');?></p>
		<?php
}

    public static function raw_html($args)
    {
        if (empty($args['html'])) {
            return;
        }

        echo wp_kses_post($args['html']);
        if (!empty($args['desc'])): ?>
			<p class="description"><?php echo wp_kses_post($args['desc']); ?></p>
		<?php endif;
    }

    public static function text_field($args)
    {
        self::_set_name_and_value($args);
        extract($args, EXTR_SKIP);

        $args = wp_parse_args($args, array(
            'classes' => array(),
        ));
        if (empty($args['id']) || empty($args['page'])) {
            return;
        }

        ?>
		<input type="text" id="<?php echo esc_attr($args['id']); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="<?php echo esc_attr(implode(' ', $args['classes'])); ?>" />
		<?php if (!empty($desc)): ?>
		<p class="description"><?php echo wp_kses_post($desc); ?></p>
		<?php endif;
    }

    public static function textarea_field($args)
    {
        self::_set_name_and_value($args);
        extract($args, EXTR_SKIP);

        $args = wp_parse_args($args, array(
            'classes' => array(),
            'rows' => 5,
            'cols' => 50,
        ));

        if (empty($args['id']) || empty($args['page'])) {
            return;
        }

        ?>
		<textarea id="<?php echo esc_attr($args['id']); ?>" name="<?php echo esc_attr($name); ?>" class="<?php echo esc_attr(implode(' ', $args['classes'])); ?>" rows="<?php echo esc_attr(absint($args['rows'])); ?>" cols="<?php echo esc_attr(absint($args['cols'])); ?>"><?php echo esc_textarea($value); ?></textarea>

		<?php if (!empty($desc)): ?>
			<p class="description"><?php echo wp_kses_post($desc); ?></p>
		<?php endif;
    }

    public static function number_field($args)
    {
        self::_set_name_and_value($args);
        extract($args, EXTR_SKIP);

        $args = wp_parse_args($args, array(
            'classes' => array(),
            'min' => '1',
            'step' => '1',
            'desc' => '',
        ));
        if (empty($args['id']) || empty($args['page'])) {
            return;
        }

        ?>
		<input type="number" id="<?php echo esc_attr($args['id']); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" class="<?php echo esc_attr(implode(' ', $args['classes'])); ?>" min="<?php echo esc_attr($args['min']); ?>" step="<?php echo esc_attr($args['step']); ?>" />
		<?php if (!empty($args['sub_desc'])) {
            echo wp_kses_post($args['sub_desc']);
        }
        ?>
		<?php if (!empty($args['desc'])): ?>
			<p class="description"><?php echo wp_kses_post($args['desc']); ?></p>
		<?php endif;
    }

    public static function select_field($args)
    {
        self::_set_name_and_value($args);
        extract($args, EXTR_SKIP);

        if (empty($options) || empty($id) || empty($page)) {
            return;
        }

        ?>
		<select id="<?php echo esc_attr($id); ?>" name="<?php printf('%s[%s]', esc_attr($page), esc_attr($id));?>">
			<?php foreach ($options as $name => $label): ?>
			<option value="<?php echo esc_attr($name); ?>" <?php selected($name, (string) $value);?>>
				<?php echo esc_html($label); ?>
			</option>
			<?php endforeach;?>
		</select>
		<?php if (!empty($desc)): ?>
		<p class="description"><?php echo wp_kses_post($desc); ?></p>
		<?php endif;?>
		<?php
}

    public static function yesno_field($args)
    {
        self::_set_name_and_value($args);
        extract($args, EXTR_SKIP);

        ?>
		<label class="tix-yes-no description"><input type="radio" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value, true);?>> <?php _e('Yes', 'crystal-ball-insight');?></label>
		<label class="tix-yes-no description"><input type="radio" name="<?php echo esc_attr($name); ?>" value="0" <?php checked($value, false);?>> <?php _e('No', 'crystal-ball-insight');?></label>

		<?php if (isset($args['description'])): ?>
		<p class="description"><?php echo wp_kses_post($args['description']); ?></p>
		<?php endif;?>
		<?php
}

    public static function email_notification_buffer_field($args)
    {
        $args = wp_parse_args($args, array(
            'classes' => array(),
        ));
        if (empty($args['id']) || empty($args['page'])) {
            return;
        }

        $keys = array(
            'user' => __('User', 'crystal-ball-insight'),
            'action-type' => __('Action Type', 'crystal-ball-insight'),
            'action-value' => __('Action Performed', 'crystal-ball-insight'),
        );
        $conditions = array(
            'equals' => __('equals to', 'crystal-ball-insight'),
            'not_equals' => __('not equals to', 'crystal-ball-insight'),
        );

        $common_name = sprintf('%s[%s]', esc_attr($args['page']), esc_attr($args['id']));

        $rows = CBI_Main::instance()->settings->get_option($args['id']);
        $rows = empty($rows) ? array(array('key' => 1)) : $rows;
        ?>
		<p class="description"><?php _e('A notification will be sent upon a successful match with the following conditions:', 'crystal-ball-insight');?></p>
		<div class="cbi-notifier-settings">
			<ul>
			<?php foreach ($rows as $rid => $row):
            $row_key = $row['key'];
            $row_condition = isset($row['condition']) ? $row['condition'] : '';
            $row_value = isset($row['value']) ? $row['value'] : '';
            ?>
							<li data-id="<?php echo esc_attr($rid); ?>">
								<select name="<?php echo esc_attr($common_name); ?>[<?php echo esc_attr($rid); ?>][key]" class="cbi-category">
									<?php foreach ($keys as $k => $v): ?>
									<option value="<?php echo esc_attr($k); ?>" <?php selected($row_key, $k);?>><?php echo esc_attr($v); ?></option>
									<?php endforeach;?>
					</select>
					<select name="<?php echo esc_attr($common_name); ?>[<?php echo esc_attr($rid); ?>][condition]" class="cbi-condition">
						<?php foreach ($conditions as $k => $v): ?>
						<option value="<?php echo esc_attr($k); ?>" <?php selected($row_condition, $k);?>><?php echo esc_html($v); ?></option>
						<?php endforeach;?>
					</select>
					<?php $value_options = CBI_Main::instance()->notifications->get_settings_dropdown_values($row_key);?>
					<select name="<?php echo esc_attr($common_name); ?>[<?php echo esc_attr($rid); ?>][value]" class="cbi-value">
						<?php foreach ($value_options as $option_key => $option_value): ?>
						<option value="<?php echo esc_attr($option_key); ?>" <?php selected($option_key, $row_value);?>><?php echo esc_html($option_value); ?></option>
						<?php endforeach;?>
					</select>
					<a href="#" class="cbi-new-rule button"><small>+</small> <?php _e('and', 'crystal-ball-insight');?></a>
					<a href="#" class="cbi-delete-rule button">&times;</a>
				</li>
			<?php endforeach;?>
			</ul>
		</div>
		<?php
}

    private static function _set_name_and_value(&$args)
    {
        if (!isset($args['name'])) {
            $args['name'] = sprintf('%s[%s]', esc_attr($args['page']), esc_attr($args['id']));
        }

        if (!isset($args['value'])) {
            $args['value'] = CBI_Main::instance()->settings->get_option($args['id']);
        }
    }
}
