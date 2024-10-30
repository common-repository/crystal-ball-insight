<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CBI_Hook_Core extends CBI_Hook_Base {

	public function core_updated_successfully( $wp_version ) {
		global $pagenow;

		if ( 'update-core.php' !== $pagenow )
			$object_name = 'WordPress Auto Updated';
		else
			$object_name = 'WordPress Updated';

		cbi_insert_log(
			array(
				'action'      => 'updated',
				'object_type' => 'Core',
				'object_id'   => 0,
				'object_name' => $object_name,
			)
		);
	}

	public function __construct() {
		add_action( '_core_updated_successfully', array( &$this, 'core_updated_successfully' ) );
		
		parent::__construct();
	}
	
}