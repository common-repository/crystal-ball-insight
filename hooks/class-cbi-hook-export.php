<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CBI_Hook_Export extends CBI_Hook_Base {

	public function hooks_export_wp( $args ) {
		cbi_insert_log(
			array(
				'action' => 'downloaded',
				'object_type' => 'Export',
				'object_id' => 0,
				'object_name' => isset( $args['content'] ) ? $args['content'] : 'all',
			)
		);
	}

	public function __construct() {
		add_action( 'export_wp', array( &$this, 'hooks_export_wp' ) );
		
		parent::__construct();
	}
	
}