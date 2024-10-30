<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CBI_Hook_Posts extends CBI_Hook_Base {
	
	protected function _draft_or_post_title( $post = 0 ) {
		$title = esc_html( get_the_title( $post ) );
		
		if ( empty( $title ) )
			$title = __( '(no title)', 'crystal-ball-insight' );
		
		return $title;
	}

	public function hooks_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status ) ) {
			$action = 'created';
		} elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			return;
		} elseif ( 'trash' === $new_status ) {
			$action = 'trashed';
		} elseif ( 'trash' === $old_status ) {
			$action = 'restored';
		} else {
			$action = 'updated';
		}

		if ( wp_is_post_revision( $post->ID ) )
			return;

		if ( 'nav_menu_item' === get_post_type( $post->ID ) )
			return;

		cbi_insert_log(
			array(
				'action' => $action,
				'object_type' => 'Posts',
				'object_subtype' => $post->post_type,
				'object_id' => $post->ID,
				'object_name' => $this->_draft_or_post_title( $post->ID ),
			)
		);
	}

	public function hooks_delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) )
			return;

		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( in_array( $post->post_status, array( 'auto-draft', 'inherit' ) ) )
			return;

		if ( 'nav_menu_item' === get_post_type( $post->ID ) )
			return;

		cbi_insert_log(
			array(
				'action' => 'deleted',
				'object_type' => 'Posts',
				'object_subtype' => $post->post_type,
				'object_id' => $post->ID,
				'object_name' => $this->_draft_or_post_title( $post->ID ),
			)
		);
	}
	
	public function __construct() {
		add_action( 'transition_post_status', array( &$this, 'hooks_transition_post_status' ), 10, 3 );
		add_action( 'delete_post', array( &$this, 'hooks_delete_post' ) );
		
		parent::__construct();
	}
}
