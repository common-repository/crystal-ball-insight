<?php
/*
Plugin Name: Crystal Ball Insight
Plugin URI: https://www.crystalballinsight.com/wordpress-web-log-changes
Description: Get aware of any activities that are taking place on your dashboard! Imagine it like a black-box for your WordPress site. e.g. post was deleted, plugin was activated, user logged in or logged out - it's all these for you to see.
Author: Crystal Ball Team
Author URI: https://www.crystalballinsight.com/wordpress-web-log-changes
Version: 1.1.1
Text Domain: crystal-ball-insight
License: GPLv2 or later


This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CRYSTAL_BALL__FILE__', __FILE__ );
define( 'CRYSTAL_BALL_URL', 'https://app.crystalballinsight.com' );
define( 'CRYSTAL_BALL_BASE', plugin_basename( CRYSTAL_BALL__FILE__ ) );

include( 'classes/class-cbi-maintenance.php' );
include( 'classes/class-cbi-log-list-table.php' );
include( 'classes/class-cbi-admin-ui.php' );
include( 'classes/class-cbi-settings.php' );
include( 'classes/class-cbi-api.php' );
include( 'classes/class-cbi-hooks.php' );
include( 'classes/class-cbi-notifications.php' );
include( 'classes/class-cbi-export.php' );
include( 'classes/class-cbi-privacy.php' );
include( 'classes/abstract-class-cbi-exporter.php' );

include( 'classes/class-cbi-integration-woocommerce.php' );

final class CBI_MAIN {

	/**
	 * @var CBI_MAIN The one true CBI_MAIN
	 * @since 1.0.0
	 */
	private static $_instance = null;

	/**
	 * @var CBI_Admin_Ui
	 * @since 1.0.0
	 */
	public $ui;

	/**
	 * @var CBI_Hooks
	 * @since 1.0.0
	 */
	public $hooks;

	/**
	 * @var CBI_Settings
	 * @since 1.0.0
	 */
	public $settings;

	/**
	 * @var CBI_API
	 * @since 1.0.0
	 */
	public $api;

	/**
	 * @var CBI_Privacy
	 * @since 1.0.0
	 */
	private $privacy;

	/**
	 * Load text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'crystal-ball-insight' );
	}

	/**
	 * Construct
	 */
	protected function __construct() {
		global $wpdb;

		$this->ui            = new CBI_Admin_Ui();
		$this->hooks         = new CBI_Hooks();
		$this->settings      = new CBI_Settings();
		$this->api           = new CBI_API();
		$this->notifications = new CBI_Notifications();
		$this->export        = new CBI_Export();
		$this->privacy       = new CBI_Privacy();

		$wpdb->crystal_ball = $wpdb->prefix . 'crystal_ball_insight';
		
		add_action( 'plugins_loaded', array( &$this, 'load_textdomain' ) );
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'crystal-ball-insight' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'crystal-ball-insight' ), '1.0.0' );
	}

	/**
	 * @return CBI_MAIN
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new CBI_MAIN();
		return self::$_instance;
	}
}

CBI_MAIN::instance();
