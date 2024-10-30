<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CBI_Notification_Email extends CBI_Notification_Base {
	
	/**
	 * 
	 */
	protected $options = array();
	
	public function __construct() {
		parent::__construct();
		
		$this->id = 'email';
		$this->name = __( 'Email', 'crystal-ball-insight' );
		$this->description = __( 'Get notified by Email.', 'crystal-ball-insight' );
	}
	
	public function init() {
		$this->options = array_merge( array(
			'from_email'   => get_option( 'admin_email' ),
		), $this->get_handler_options() );
	}
	
	public function trigger( $args ) {
		$from_email = isset( $this->options['from_email'] ) && is_email( $this->options['from_email'] ) ? $this->options['from_email'] : '';
		$to_email   = isset( $this->options['to_email'] ) && is_email( $this->options['to_email'] ) ? $this->options['to_email'] : '';

		if ( ! ( $from_email || $to_email ) )
			return;

		$format = isset( $this->options['message_format'] ) ? $this->options['message_format'] : '';
		$body = $this->prep_notification_body( $args );
		$site_name = get_bloginfo( 'name' );
		$site_name_link = sprintf( '<a href="%s">%s</a>', home_url(), $site_name );

		$email_contents = strtr( $format, array(
			'[sitename]' => $site_name_link,
			'[action-details]' => $body,
		) );

		add_filter( 'wp_mail_content_type', array( &$this, 'email_content_type' ) );

		wp_mail(
			$to_email,
			__( 'New notification from Crystal Ball', 'crystal-ball-insight' ),
			nl2br( $email_contents ),
			array(
				"From: Crystal Ball @ $site_name <$from_email>"
			)
		);

		remove_filter( 'wp_mail_content_type', array( &$this, 'email_content_type' ) );
	}

	public function email_content_type() {
		return apply_filters( 'cbi_notification_email_content_type', 'text/html' );
	}
	
	public function settings_fields() {
		$default_email_message = __( "Hi there!\n\nA notification condition on [sitename] was matched. Here are the details:\n\n[action-details]\n\nSent by Crystal Ball Insight", 'crystal-ball-insight' );

		$this->add_settings_field_helper( 'from_email', __( 'From Email', 'crystal-ball-insight' ), array( 'CBI_Settings_Fields', 'text_field' ), __( 'The source Email address', 'crystal-ball-insight' ) );
		$this->add_settings_field_helper( 'to_email', __( 'To Email', 'crystal-ball-insight' ), array( 'CBI_Settings_Fields', 'text_field' ), __( 'The Email address notifications will be sent to', 'crystal-ball-insight' ) );
		$this->add_settings_field_helper( 'message_format', __( 'Message', 'crystal-ball-insight' ), array( 'CBI_Settings_Fields', 'textarea_field' ), sprintf( __( 'Customize the message using the following placeholders: %s', 'crystal-ball-insight' ), '[sitename], [action-details]' ), $default_email_message );
	}
	
	public function validate_options( $input ) {
		$output = array();
		$email_fields = array( 'to_email', 'from_email' );

		foreach ( $email_fields as $email_field ) {
			if ( isset( $input[ $email_field ] ) && is_email( $input[ $email_field ] ) )
				$output[ $email_field ] = $input[ $email_field ];
		}

		if ( ! empty( $input['message_format'] ) ) {
			$output['message_format'] = $input['message_format'];
		}

		return $output;
	}
}

cbi_register_notification_handler( 'CBI_Notification_Email' );