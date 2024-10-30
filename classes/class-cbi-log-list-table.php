<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CBI_Log_List_Table extends WP_List_Table
{

    protected $_roles = array();

    protected $_caps = array();

    protected $_allow_caps = array();

    protected function _get_allow_caps()
    {
        if (empty($this->_allow_caps)) {
            $user = get_user_by('id', get_current_user_id());
            if (!$user) {
                wp_die('Not allowed here.');
            }

            $user_cap = strtolower(key($user->caps));
            $allow_caps = array();

            foreach ($this->_caps as $key => $cap_allow) {
                if ($key === $user_cap) {
                    $allow_caps = array_merge($allow_caps, $cap_allow);

                    break;
                }
            }

            if (is_super_admin() || current_user_can('view_all_crystal_ball_insight')) {
                $allow_caps = $this->_caps['administrator'];
            }

            if (empty($allow_caps)) {
                wp_die('Not allowed here.');
            }

            $this->_allow_caps = array_unique($allow_caps);
        }
        return $this->_allow_caps;
    }

    protected function _get_where_by_role()
    {
        $allow_modules = array();

        foreach ($this->_roles as $key => $role) {
            if (current_user_can($key) || current_user_can('view_all_crystal_ball_insight')) {
                $allow_modules = array_merge($allow_modules, $role);
            }
        }

        if (empty($allow_modules)) {
            wp_die('Not allowed here.');
        }

        $allow_modules = array_unique($allow_modules);

        $where = array();
        foreach ($allow_modules as $type) {
            $where[] .= '`object_type` = \'' . $type . '\'';
        }

        $where_caps = array();
        foreach ($this->_get_allow_caps() as $cap) {
            $where_caps[] .= '`user_caps` = \'' . $cap . '\'';
        }

        return 'AND (' . implode(' OR ', $where) . ') AND (' . implode(' OR ', $where_caps) . ')';
    }

    public function get_action_label($action)
    {
        return ucwords(str_replace('_', ' ', __($action, 'crystal-ball-insight')));
    }

    public function __construct($args = array())
    {
        parent::__construct(
            array(
                'singular' => 'activity',
                'screen' => isset($args['screen']) ? $args['screen'] : null,
            )
        );

        $this->_roles = apply_filters(
            'cbi_init_roles',
            array(
                'manage_options' => array(
                    'Core',
                    'Export',
                    'Posts',
                    'Taxonomies',
                    'Users',
                    'Options',
                    'Attachments',
                    'Plugins',
                    'Widgets',
                    'Themes',
                    'Menus',
                    'Comments',

                    'Post',
                    'Taxonomy',
                    'User',
                    'Plugin',
                    'Widget',
                    'Theme',
                    'Menu',
                ),
                'edit_pages' => array(
                    'Posts',
                    'Taxonomies',
                    'Attachments',
                    'Comments',

                    'Post',
                    'Taxonomy',
                    'Attachment',
                ),
            )
        );

        $default_rules = array(
            'administrator',
            'editor',
            'author',
            'guest',
        );

        global $wp_roles;

        $all_roles = array();
        foreach ($wp_roles->roles as $key => $wp_role) {
            $all_roles[] = $key;
        }

        $this->_caps = apply_filters(
            'cbi_init_caps',
            array(
                'administrator' => array_unique(array_merge($default_rules, $all_roles)),
                'editor' => array('editor', 'author', 'guest'),
                'author' => array('author', 'guest'),
            )
        );

        add_screen_option(
            'per_page',
            array(
                'default' => 50,
                'label' => __('Activities', 'crystal-ball-insight'),
                'option' => 'edit_cbi_logs_per_page',
            )
        );

        add_filter('set-screen-option', array(&$this, 'set_screen_option'), 10, 3);
        set_screen_options();
    }

    public function get_columns()
    {
        $columns = array(
            'date' => __('Date', 'crystal-ball-insight'),
            'author' => __('User', 'crystal-ball-insight'),
            'type' => __('Topic', 'crystal-ball-insight'),
            'label' => __('Context', 'crystal-ball-insight'),
            'description' => __('Item', 'crystal-ball-insight'),
            'action' => __('Action', 'crystal-ball-insight'),
        );

        return $columns;
    }

    public function get_sortable_columns()
    {
        return array(
            'ip' => 'hist_ip',
            'date' => array('hist_time', true),
        );
    }

    public function column_default($item, $column_name)
    {
        $return = '';

        switch ($column_name) {
            case 'action':
                $return = '<a href="' . $this->get_filtered_link('showaction', $item->action) . '">' . $this->get_action_label($item->action) . '</a>';
                break;

            case 'date':
                $return = sprintf('<strong>' . __('%s ago', 'crystal-ball-insight') . '</strong>', human_time_diff($item->hist_time, current_time('timestamp')));

                $date_formatted = date('d/m/Y', $item->hist_time);
                $return .= '<br /><a href="' . $this->get_filtered_link('dateshow', $date_formatted) . '">' . date_i18n(get_option('date_format'), $item->hist_time) . '</a>';

                $return .= '<br />' . date_i18n(get_option('time_format'), $item->hist_time);
                break;

            default:
                if (isset($item->$column_name)) {
                    $return = $item->$column_name;
                }
        }

        $return = apply_filters('cbi_table_list_column_default', $return, $item, $column_name);

        return $return;
    }

    public function column_author($item)
    {
        global $wp_roles;

        if (!empty($item->user_id) && 0 !== (int) $item->user_id) {
            $user = get_user_by('id', $item->user_id);
            if ($user instanceof WP_User && 0 !== $user->ID) {
                return sprintf(
                    '<a href="%s"><span class="cbi-author-name">%s</span></a><br /><small>%s</small>',
                    $this->get_filtered_link('usershow', $user->ID),
                    $user->display_name,
                    isset($user->roles[0]) && isset($wp_roles->role_names[$user->roles[0]]) ? $wp_roles->role_names[$user->roles[0]] : __('Unknown', 'crystal-ball-insight')
                );
            }
        }
        return sprintf(
            '<span class="cbi-author-name">%s</span>',
            __('N/A', 'crystal-ball-insight')
        );
    }

    public function column_type($item)
    {
        $return = __($item->object_type, 'crystal-ball-insight');

        if (!empty($item->object_type)) {
            $link = $this->get_filtered_link('typeshow', $item->object_type);
            $return = "<a href=\"{$link}\">{$return}</a>";
        }

        $return = apply_filters('cbi_table_list_column_type', $return, $item);
        return $return;
    }

    public function column_label($item)
    {
        $return = '';
        if (!empty($item->object_subtype)) {
            $pt = get_post_type_object($item->object_subtype);
            $return = !empty($pt->label) ? $pt->label : $item->object_subtype;
        }

        $return = apply_filters('cbi_table_list_column_label', $return, $item);
        return $return;
    }

    public function column_description($item)
    {
        $return = esc_html($item->object_name);
        $actions = [];

        switch ($item->object_type) {
            case 'Post':
            case 'Posts':
                $actions = [
                    'view' => sprintf('<a href="%s">%s</a>', get_permalink($item->object_id), __('View', 'crystal-ball-insight')),
                    'edit' => sprintf('<a href="%s">%s</a>', get_edit_post_link($item->object_id), __('Edit', 'crystal-ball-insight')),
                ];

                $return = esc_html($item->object_name);
                break;

            case 'Taxonomy':
            case 'Taxonomies':
                if (!empty($item->object_id)) {
                    if (is_taxonomy_viewable($item->object_subtype)) {
                        $term_view_link = get_term_link(absint($item->object_id), $item->object_subtype);

                        if (!is_wp_error($term_view_link)) {
                            $actions['view'] = sprintf('<a href="%s">%s</a>', $term_view_link, __('View', 'crystal-ball-insight'));
                        }
                    }

                    $term_edit_link = get_edit_term_link($item->object_id, $item->object_subtype);
                    if (!empty($term_edit_link)) {
                        $actions['edit'] = sprintf('<a href="%s">%s</a>', $term_edit_link, __('Edit', 'crystal-ball-insight'));
                    }

                    $return = esc_html($item->object_name);
                }
                break;

            case 'Comments':
                if (!empty($item->object_id) && $comment = get_comment($item->object_id)) {
                    $actions['edit'] = sprintf('<a href="%s">%s</a>', get_edit_comment_link($item->object_id), __('Edit', 'crystal-ball-insight'));
                }

                $return = esc_html("{$item->object_name} #{$item->object_id}");
                break;

            case 'User':
            case 'Users':
                $user_edit_link = get_edit_user_link($item->object_id);
                if (!empty($user_edit_link)) {
                    $actions['edit'] = sprintf('<a href="%s">%s</a>', $user_edit_link, __('Edit', 'crystal-ball-insight'));
                }

                if (!empty($item->object_name)) {
                    $return = __('Username:', 'crystal-ball-insight') . ' ' . $item->object_name;
                }
                break;

            case 'Export':
                if ('all' === $item->object_name) {
                    $return = __('All', 'crystal-ball-insight');
                } else {
                    $pt = get_post_type_object($item->object_name);
                    $return = !empty($pt->label) ? $pt->label : $item->object_name;
                }
                break;

            case 'Options':
            case 'Core':
                $return = __($item->object_name, 'crystal-ball-insight');
                break;
        }

        $return = apply_filters('cbi_table_list_column_description', $return, $item);

        if (!empty($actions)) {
            $i = 0;

            $return .= '<div class="row-actions">';
            foreach ($actions as $action_name => $action_label) {
                ++$i;
                (1 === $i) ? $sep = '' : $sep = ' | ';
                $return .= "<span class=\"{$action_name}\">{$sep}{$action_label}</span>";
            }
            $return .= '</div>';
        }

        return $return;
    }

    public function display_tablenav($which)
    {
        if ('top' == $which) {
            $this->search_box(__('Search', 'crystal-ball-insight'), 'cbi-search');
        }
        ?>
		<div class="tablenav <?php echo esc_attr($which); ?>">
			<?php
$this->extra_tablenav($which);
        $this->pagination($which);
        ?>
			<br class="clear" />
		</div>
		<?php
}

    public function extra_tablenav_footer()
    {
        /**
         * Filter list of record actions
         *
         * @return array Array items should represent action_id => 'Action Title'
         */
        $actions = apply_filters('cbi_record_actions', array());
        ?>
			<?php if (count($actions) > 1): ?>
			<div class="alignleft actions recordactions">
				<select name="cbi-record-action">
					<option value=""><?php echo esc_attr__('Export File Format', 'crystal-ball-insight'); ?></option>
					<?php foreach ($actions as $action_key => $action_title): ?>
					<option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_title); ?></option>
					<?php endforeach;?>
				</select>
			</div>
			<?php else:
            $action_title = reset($actions);
            $action_key = key($actions);
            ?>
																						<input type="hidden" name="cbi-record-action" value="<?php echo esc_attr($action_key); ?>">
																						<?php endif;?>

			<button type="submit" name="cbi-record-actions-submit" id="record-actions-submit" class="button button-primary" value="1">
				<?php
if (array_key_exists('cbi-filter', $_GET)) {
            echo sprintf(esc_html__('Export filtered records as %s', 'crystal-ball-insight'), $action_title);
        } else {
            echo sprintf(esc_html__('Export as %s', 'crystal-ball-insight'), $action_title);
        }
        ?>
			</button>

			<?php wp_nonce_field('cbi_actions_nonce', 'cbi_actions_nonce');?>
		<?php
}

    public function extra_tablenav($which)
    {
        global $wpdb;

        if ('bottom' === $which) {
            $this->extra_tablenav_footer();
        }

        if ('top' !== $which) {
            return;
        }

        echo '<div class="alignleft actions">';

        $users = $wpdb->get_results(
            'SELECT DISTINCT `user_id` FROM `' . $wpdb->crystal_ball . '`
				WHERE 1 = 1
				' . $this->_get_where_by_role() . '
				GROUP BY `user_id`
				ORDER BY `user_id`
				LIMIT 100
			;'
        );

        $types = $wpdb->get_results(
            'SELECT DISTINCT `object_type` FROM `' . $wpdb->crystal_ball . '`
				WHERE 1 = 1
				' . $this->_get_where_by_role() . '
				GROUP BY `object_type`
				ORDER BY `object_type`
			;'
        );

        if ($users || $types) {
            if (!isset($_REQUEST['dateshow'])) {
                $_REQUEST['dateshow'] = '';
            }

            $date_options = array(
                '' => __('All Time', 'crystal-ball-insight'),
                'today' => __('Today', 'crystal-ball-insight'),
                'yesterday' => __('Yesterday', 'crystal-ball-insight'),
                'week' => __('Week', 'crystal-ball-insight'),
                'month' => __('Month', 'crystal-ball-insight'),
            );
            echo '<select name="dateshow" id="hs-filter-date">';
            foreach ($date_options as $key => $value) {
                printf('<option value="%s"%s>%s</option>', $key, selected(sanitize_text_field($_REQUEST['dateshow']), $key, false), $value);
            }

            echo '</select>';

            submit_button(__('Filter', 'crystal-ball-insight'), 'button', 'cbi-filter', false, array('id' => 'activity-query-submit'));
        }

        if ($users) {
            if (!isset($_REQUEST['capshow'])) {
                $_REQUEST['capshow'] = '';
            }

            $output = array();
            foreach ($this->_get_allow_caps() as $cap) {
                $output[$cap] = __(ucwords($cap), 'crystal-ball-insight');
            }

            if (!empty($output)) {
                echo '<select name="capshow" id="hs-filter-capshow">';
                printf('<option value="">%s</option>', __('All Roles', 'crystal-ball-insight'));
                foreach ($output as $key => $value) {
                    printf('<option value="%s"%s>%s</option>', $key, selected(sanitize_text_field($_REQUEST['capshow']), $key, false), $value);
                }
                echo '</select>';
            }

            if (!isset($_REQUEST['usershow'])) {
                $_REQUEST['usershow'] = '';
            }

            $output = array();
            foreach ($users as $_user) {
                if (0 === (int) $_user->user_id) {
                    $output[0] = __('N/A', 'crystal-ball-insight');
                    continue;
                }

                $user = get_user_by('id', $_user->user_id);
                if ($user) {
                    $output[$user->ID] = $user->user_nicename;
                }

            }

            if (!empty($output)) {
                echo '<select name="usershow" id="hs-filter-usershow">';
                printf('<option value="">%s</option>', __('All Users', 'crystal-ball-insight'));
                foreach ($output as $key => $value) {
                    printf('<option value="%s"%s>%s</option>', $key, selected(sanitize_text_field($_REQUEST['usershow']), $key, false), $value);
                }
                echo '</select>';
            }
        }

        if ($types) {
            if (!isset($_REQUEST['typeshow'])) {
                $_REQUEST['typeshow'] = '';
            }

            $output = array();
            foreach ($types as $type) {
                $output[] = sprintf('<option value="%s"%s>%s</option>', $type->object_type, selected(sanitize_text_field($_REQUEST['typeshow']), $type->object_type, false), __($type->object_type, 'crystal-ball-insight'));
            }

            echo '<select name="typeshow" id="hs-filter-typeshow">';
            printf('<option value="">%s</option>', __('All Topics', 'crystal-ball-insight'));
            echo esc_html(implode('', $output));
            echo '</select>';
        }

        $actions = $wpdb->get_results(
            'SELECT DISTINCT `action` FROM  `' . $wpdb->crystal_ball . '`
				WHERE 1 = 1
				' . $this->_get_where_by_role() . '
				GROUP BY `action`
				ORDER BY `action`
			;'
        );

        if ($actions) {
            if (!isset($_REQUEST['showaction'])) {
                $_REQUEST['showaction'] = '';
            }

            $output = array();
            foreach ($actions as $action) {
                $output[] = sprintf('<option value="%s"%s>%s</option>', $action->action, selected(sanitize_text_field($_REQUEST['showaction']), $action->action, false), $this->get_action_label($action->action));
            }

            echo '<select name="showaction" id="hs-filter-showaction">';
            printf('<option value="">%s</option>', __('All Actions', 'crystal-ball-insight'));
            echo esc_html(implode('', $output));
            echo '</select>';
        }

        $filters = array(
            'dateshow',
            'capshow',
            'usershow',
            'typeshow',
            'showaction',
        );

        foreach ($filters as $filter) {
            if (!empty(sanitize_text_field($_REQUEST[$filter]))) {
                echo '<a href="' . esc_url($this->get_filtered_link()) . '" id="cbi-reset-filter"><span class="dashicons dashicons-dismiss"></span>' . __('Reset Filters', 'crystal-ball-insight') . '</a>';
                break;
            }
        }

        echo '</div>';
    }

    public function prepare_items()
    {
        global $wpdb;

        $items_per_page = $this->get_items_per_page('edit_cbi_logs_per_page', 20);
        $this->_column_headers = array($this->get_columns(), get_hidden_columns($this->screen), $this->get_sortable_columns());
        $where = ' WHERE 1 = 1';

        if (!isset($_REQUEST['order']) || !in_array(sanitize_text_field($_REQUEST['order']), array('desc', 'asc'))) {
            $_REQUEST['order'] = 'DESC';
        }
        if (!isset($_REQUEST['orderby']) || !in_array(sanitize_text_field($_REQUEST['orderby']), array('hist_time', 'hist_ip'))) {
            $_REQUEST['orderby'] = 'hist_time';
        }

        if (!empty(sanitize_text_field($_REQUEST['typeshow']))) {
            $where .= $wpdb->prepare(' AND `object_type` = %s', sanitize_text_field($_REQUEST['typeshow']));
        }

        if (isset($_REQUEST['showaction']) && '' !== sanitize_text_field($_REQUEST['showaction'])) {
            $where .= $wpdb->prepare(' AND `action` = %s', sanitize_text_field($_REQUEST['showaction']));
        }

        if (isset($_REQUEST['filter_ip']) && '' !== sanitize_text_field($_REQUEST['filter_ip'])) {
            $where .= $wpdb->prepare(' AND `hist_ip` = %s', sanitize_text_field($_REQUEST['filter_ip']));
        }

        if (isset($_REQUEST['usershow']) && '' !== sanitize_text_field($_REQUEST['usershow'])) {
            $where .= $wpdb->prepare(' AND `user_id` = %d', sanitize_text_field($_REQUEST['usershow']));
        }

        if (isset($_REQUEST['capshow']) && '' !== sanitize_text_field($_REQUEST['capshow'])) {
            $where .= $wpdb->prepare(' AND `user_caps` = %s', strtolower(sanitize_text_field($_REQUEST['capshow'])));
        }

        if (isset($_REQUEST['dateshow'])) {
            $current_time = current_time('timestamp');

            if (in_array(sanitize_text_field($_REQUEST['dateshow']), array('today', 'yesterday', 'week', 'month'))) {
                $start_time = mktime(0, 0, 0, date('m', $current_time), date('d', $current_time), date('Y', $current_time));
                $end_time = mktime(23, 59, 59, date('m', $current_time), date('d', $current_time), date('Y', $current_time));

                if ('yesterday' === sanitize_text_field($_REQUEST['dateshow'])) {
                    $start_time = strtotime('yesterday', $start_time);
                    $end_time = mktime(23, 59, 59, date('m', $start_time), date('d', $start_time), date('Y', $start_time));
                } elseif ('week' === sanitize_text_field($_REQUEST['dateshow'])) {
                    $start_time = strtotime('-1 week', $start_time);
                } elseif ('month' === sanitize_text_field($_REQUEST['dateshow'])) {
                    $start_time = strtotime('-1 month', $start_time);
                }
            } else {
                $date_array = explode('/', sanitize_text_field($_REQUEST['dateshow']));

                if (3 === count($date_array)) {
                    $start_time = mktime(0, 0, 0, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2]);
                    $end_time = mktime(23, 59, 59, (int) $date_array[1], (int) $date_array[0], (int) $date_array[2]);
                }
            }

            if (!empty($start_time) && !empty($end_time)) {
                $where .= $wpdb->prepare(' AND `hist_time` > %d AND `hist_time` < %d', $start_time, $end_time);
            }
        }

        if (isset($_REQUEST['s'])) {
            $where .= $wpdb->prepare(' AND `object_name` LIKE %s', '%' . $wpdb->esc_like($_REQUEST['s']) . '%');
        }

        $offset = ($this->get_pagenum() - 1) * $items_per_page;

        $total_items = $wpdb->get_var(
            'SELECT COUNT(`histid`) FROM  `' . $wpdb->crystal_ball . '`
				' . $where . '
					' . $this->_get_where_by_role()
        );

        if (!empty(sanitize_text_field($_GET['orderby']))) {
            $items_orderby = sanitize_sql_orderby(htmlspecialchars(sanitize_text_field($_GET['orderby'])));
        }

        if (empty($items_orderby)) {
            $items_orderby = 'hist_time';
        }

        $items_order = strtoupper(sanitize_text_field($_REQUEST['order']));
        if (empty($items_order) || !in_array($items_order, array('DESC', 'ASC'))) {
            $items_order = 'DESC';
        }

        $this->items = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM `' . $wpdb->crystal_ball . '`
				' . $where . '
					' . $this->_get_where_by_role() . '
					ORDER BY ' . $items_orderby . ' ' . $items_order . '
					LIMIT %d, %d;',
            $offset,
            $items_per_page
        ));

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $items_per_page,
            'total_pages' => ceil($total_items / $items_per_page),
        ));
    }

    public function set_screen_option($status, $option, $value)
    {
        if ('edit_cbi_logs_per_page' === $option) {
            return $value;
        }

        return $status;
    }

    public function search_box($text, $input_id)
    {
        $search_data = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        $input_id = $input_id . '-search-input';
        ?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr($input_id) ?>"><?php echo esc_html($text); ?>:</label>
			<input type="search" id="<?php echo esc_attr($input_id) ?>" name="s" value="<?php echo esc_attr($search_data); ?>" />
			<?php submit_button($text, 'button', false, false, array('id' => 'search-submit'));?>
		</p>
	<?php
}

    private function get_filtered_link($name = '', $value = '')
    {
        $base_page_url = menu_page_url('log_page', false);

        if (empty($name)) {
            return $base_page_url;
        }

        return add_query_arg($name, $value, $base_page_url);
    }
}
