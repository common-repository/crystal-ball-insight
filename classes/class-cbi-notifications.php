<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Notifications API main class
 * 
 * @since 1.0.0
 */
class CBI_Notifications {
	public $handlers = array();
	public $handlers_loaded = array();
	
	public function __construct() {
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/notifications/abstract-class-cbi-notification-base.php' );
		
		add_action( 'init', array( &$this, 'load_handlers' ), 20 );
		add_action( 'cbi_load_notification_handlers', array( &$this, 'load_default_handlers' ) );
		add_action( 'cbi_insert_log', array( &$this, 'process_notifications' ), 20 );
	}
	
	public function process_notifications( $args ) {
		$enabled_handlers = $this->get_enabled_handlers();
		
		if ( empty( $enabled_handlers ) )
			return;
		
		$options = CBI_Main::instance()->settings->get_options();
		
		if ( empty( $options['notification_rules'] ) || ! is_array( $options['notification_rules'] ) )
			return;
		
		$notification_matched_rules = array();
		
		foreach ( $options['notification_rules'] as $notification_rule ) {
			list( $n_key, $n_condition, $n_value ) = array_values( $notification_rule );
			
			switch ( $n_key ) {
				case 'action-type':
					if ( $n_value == $args['object_type'] )
						$notification_matched_rules[] = $notification_rule;
					break;
			}
		}
		
		if ( ! empty( $notification_matched_rules ) ) {
			foreach ( $enabled_handlers as $enabled_handler ) {
				$enabled_handler->trigger( $args );
			}
		}
	}

	public function get_object_types() {
		$opts = apply_filters(
			'cbi_notification_get_object_types',
			array(
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
			)
		);
		
		return array_combine( $opts, $opts );
	}

	public function get_actions() {
		$opts = apply_filters(
			'cbi_notification_get_actions',
			array(
				'created',
				'deleted',
				'updated',
				'trashed',
				'untrashed',
				'spammed',
				'unspammed',
				'downloaded',
				'installed',
				'uploaded',
				'activated',
				'deactivated',
				'accessed',
				'file_updated',
				'logged_in',
				'logged_out',
				'failed_login',
			)
		);
		$ready = array();

		foreach ( $opts as $opt ) {
			$ready[ $opt ] = ucwords( str_replace( '_', ' ', __( $opt, 'crystal-ball-insight' ) ) );
		}

		return $ready;
	}
	
	/**
	 * 
	 * 
	 * @param string $row_key type
	 * @return array
	 */
	public function get_settings_dropdown_values( $row_key ) {
		$results = array();

		switch ( $row_key ) {
			case 'user':
				if ( false === ( $results = wp_cache_get( $cache_key = 'notifications-users', 'cbi' ) ) ) {
					$all_users = get_users();
					$preped_users = array();
					
					foreach ( $all_users as $user ) {
						$user_role = $user->roles;
							
						if ( empty( $user_role ) )
							continue;
						
						$user_role_obj = get_role( $user_role[0] );
						$user_role_name = isset( $user_role_obj->name ) ? $user_role_obj->name : $user_role[0];
							
						$preped_users[ $user->ID ] = apply_filters( 'cbi_notifications_user_format', sprintf( '%s - %s (ID #%d)', $user->display_name, $user_role_name, $user->ID ), $user );
					}
					
					wp_cache_set( $cache_key, $results = $preped_users, 'cbi' );
				}
				break;
				
			case 'action-type':
				$results = $this->get_object_types();
				break;
				
			case 'action-value':
				$results = $this->get_actions();
				break;
				
			default:
				$results = apply_filters( 'cbi_settings_dropdown_values', $results, $row_key );
				break;
		}
		
		return $results;
	}
	
	/**
	 * 
	 * 
	 */
	public function get_handlers() {
		if ( empty( $this->handlers ) || ! did_action( 'cbi_load_notification_handlers' ) )
			return array();
		
		$handlers = array();
		
		foreach ( $this->handlers as $handler ) {
			$handler_obj = $this->handlers_loaded[ $handler ];
			
			$handler_name = isset( $handler_obj->name ) ? $handler_obj->name : $handler;
			
			$handlers[ $handler_obj->id ] = $handler_name; 
		}
		
		return $handlers;
	}
	
	/**
	 * Returns a handler object
	 * 
	 * @param string $id
	 * @return CBI_Notification_Base|bool
	 */
	public function get_handler_object( $id ) {
		return isset( $this->handlers_loaded[ $id ] ) ? $this->handlers_loaded[ $id ] : false;
	}
	
	/**
	 * Returns all available handlers
	 * @return array
	 */
	public function get_available_handlers() {
		$handlers = array();
		
		foreach ( $this->handlers_loaded as $handler_classname => $handler_obj ) {
			$handlers[ $handler_obj->id ] = $handler_obj;
		}
		
		return apply_filters( 'cbi_available_handlers', $handlers );
	}
	
	/**
	 * Returns the active handlers that were activated through the settings page
	 * 
	 * @return array
	 */
	public function get_enabled_handlers() {
		$enabled = array();
		$options = CBI_Main::instance()->settings->get_options();
		
		foreach ( $this->get_available_handlers() as $id => $handler_obj ) {
			if ( isset( $options['notification_handlers'][ $id ] ) && 1 == $options['notification_handlers'][ $id ] ) {
				$enabled[ $id ] = $handler_obj;
			}
		}
		
		return $enabled;
	}

	/**
	 * Runs during cbi_load_notification_handlers, 
	 * includes the necessary files to register default notification handlers.
	 */
	public function load_default_handlers() {
		$default_handlers = apply_filters( 'cbi_default_addons', array(
			'email' 			=> $this->get_default_handler_path( 'class-cbi-notification-email.php' ),
		) );

		foreach ( $default_handlers as $filename )
			include_once $filename;
	}

	/**
	 * Returns path to notification handler file
	 * 
	 * @param string $filename
	 * @return string
	 */
	public function get_default_handler_path( $filename ) {
		return plugin_dir_path( CRYSTAL_BALL__FILE__ ) . "notifications/$filename";
	}

	public function load_handlers() {
		do_action( 'cbi_load_notification_handlers' );

		foreach ( $this->handlers as $handler_classname ) {
			if ( class_exists( $handler_classname ) ) {
				$obj = new $handler_classname;
				
				if ( ! is_a( $obj, 'CBI_Notification_Base' ) )
					continue;
				
				$this->handlers_loaded[ $handler_classname ] = $obj;
			}
		}
	}

	/**
	 * 
	 * 
	 * @param string $classname The name of the class to create an instance for
	 * @return bool
	 */
	public function register_handler( $classname ) {
		if ( ! class_exists( $classname ) ) {
			trigger_error( __( 'The CBI notification handler you are trying to register does not exist.', 'crystal-ball-insight' ) );
			return false;
		}

		$this->handlers[] = $classname;
		return true;
	}
}
