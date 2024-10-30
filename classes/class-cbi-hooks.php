<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CBI_Hooks {
	
	public function __construct() {
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/abstract-class-cbi-hook-base.php' );
		
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-users.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-attachments.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-menus.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-options.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-plugins.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-posts.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-taxonomies.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-themes.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-widgets.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-core.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-export.php' );
		include( plugin_dir_path( CRYSTAL_BALL__FILE__ ) . '/hooks/class-cbi-hook-comments.php' );
		
		new CBI_Hook_Users();
		new CBI_Hook_Attachments();
		new CBI_Hook_Menus();
		new CBI_Hook_Options();
		new CBI_Hook_Plugins();
		new CBI_Hook_Posts();
		new CBI_Hook_Taxonomies();
		new CBI_Hook_Themes();
		new CBI_Hook_Widgets();
		new CBI_Hook_Core();
		new CBI_Hook_Export();
		new CBI_Hook_Comments();
	}
}
