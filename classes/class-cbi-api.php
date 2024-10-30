<?php
if (!defined('ABSPATH')) {
    exit;
}

class CBI_API
{

    public function __construct()
    {
        add_action('cbi/maintenance/clear_old_items', [$this, 'delete_old_items']);
    }

    public function delete_old_items()
    {
        global $wpdb;

        $logs_lifespan = absint(CBI_Main::instance()->settings->get_option('logs_lifespan'));
        if (empty($logs_lifespan)) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM `' . $wpdb->crystal_ball . '`
					WHERE `hist_time` < %d',
                strtotime('-' . $logs_lifespan . ' days', current_time('timestamp'))
            )
        );
    }

    /**
     * Get real address
     *
     * @return string real address IP
     * @since 1.0.0
     *
     */
    protected function _get_ip_address()
    {
        $server_ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_TRUE_CLIENT_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ($server_ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }

        return '127.0.0.1';
    }

    /**
     * @return void
     * @since 1.0.0
     */
    public function erase_all_items()
    {
        global $wpdb;

        $wpdb->query('TRUNCATE `' . $wpdb->crystal_ball . '`');
    }

    /**
     * @param array $args
     * @return void
     * @since 1.0.0
     *
     */
    public function insert($args)
    {
        global $wpdb;

        $args = wp_parse_args(
            $args,
            array(
                'action' => '',
                'object_type' => '',
                'object_subtype' => '',
                'object_name' => '',
                'object_id' => '',
                'hist_ip' => $this->_get_ip_address(),
                'hist_time' => current_time('timestamp'),
            )
        );

        $user = get_user_by('id', get_current_user_id());
        if ($user) {
            $args['user_caps'] = strtolower(key($user->caps));
            if (empty($args['user_id'])) {
                $args['user_id'] = $user->ID;
            }

        } else {
            $args['user_caps'] = 'guest';
            if (empty($args['user_id'])) {
                $args['user_id'] = 0;
            }

        }

        if (empty($args['user_caps']) || 'bbp_participant' === $args['user_caps']) {
            $args['user_caps'] = 'administrator';
        }

        $check_duplicate = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT `histid` FROM `' . $wpdb->crystal_ball . '`
					WHERE `user_caps` = %s
						AND `action` = %s
						AND `object_type` = %s
						AND `object_subtype` = %s
						AND `object_name` = %s
						AND `user_id` = %s
						AND `hist_ip` = %s
						AND `hist_time` = %s
				;',
                $args['user_caps'],
                $args['action'],
                $args['object_type'],
                $args['object_subtype'],
                $args['object_name'],
                $args['user_id'],
                $args['hist_ip'],
                $args['hist_time']
            )
        );

        if ($check_duplicate) {
            return;
        }

        $wpdb->insert(
            $wpdb->crystal_ball,
            array(
                'action' => $args['action'],
                'object_type' => $args['object_type'],
                'object_subtype' => $args['object_subtype'],
                'object_name' => $args['object_name'],
                'object_id' => $args['object_id'],
                'user_id' => $args['user_id'],
                'user_caps' => $args['user_caps'],
                'hist_ip' => $args['hist_ip'],
                'hist_time' => $args['hist_time'],
            ),
            array('%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
        );

        do_action('cbi_insert_log', $args);

        $user = get_user_by('id', $args['user_id']);
        $show_at = date('Y-m-d', $args['hist_time']);

        $api_key = CBI_Main::instance()->settings->get_option('crystalball_api_key');
        $url = CRYSTAL_BALL_URL . "/api/v1/annotations";

        $site_name = get_bloginfo('name');
        $site_name_link = home_url();

        $body = array(
            'category' => $site_name,
            'event_name' => $args['object_subtype'] . ' - ' . $args['action'],
            'url' => $site_name_link,
            'description' => $args['object_type'] . ' ' . $args['action'] . ' : ' . $args['object_subtype'] . ' - ' . $args['object_name'],
            'show_at' => $show_at,
        );

        $args = array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer ' . $api_key
            )
        );

        $response = wp_remote_post($url, $args);
    }
}

/**
 * @param array $args
 * @return void
 * @since 1.0.0
 *
 * @see CBI_API::insert
 *
 */
function cbi_insert_log($args = array())
{
    CBI_Main::instance()->api->insert($args);
}
