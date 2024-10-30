<?php
if (!defined('ABSPATH')) {
    exit;
}

class CBI_Admin_Ui
{

    /**
     * @var CBI_Log_List_Table
     */
    protected $_list_table = null;

    protected $_screens = array();

    public function create_admin_menu()
    {
        $menu_capability = current_user_can('view_all_crystal_ball_insight') ? 'view_all_crystal_ball_insight' : 'edit_pages';

        $this->_screens['main'] = add_menu_page(_x('Crystal Ball', 'Page and Menu Title', 'crystal-ball-insight'), _x('Crystal Ball', 'Page and Menu Title', 'crystal-ball-insight'), $menu_capability, 'log_page', array(&$this, 'log_page_func'), '', '2.1');

        add_action('load-' . $this->_screens['main'], array(&$this, 'get_list_table'));
    }

    public function log_page_func()
    {
        $this->get_list_table()->prepare_items();
        ?>
		<div class="wrap">
			<h1 class="cbi-page-title"><?php _ex('Crystal Ball', 'Page and Menu Title', 'crystal-ball-insight');?></h1>

			<form id="activity-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr(sanitize_text_field($_REQUEST['page'])); ?>" />
				<?php $this->get_list_table()->display();?>
			</form>
		</div>

		<style>
			#record-actions-submit {
				margin-top: 10px;
			}
			.cbi-pt {
				color: #ffffff;
				padding: 1px 4px;
				margin: 0 5px;
				font-size: 1em;
				border-radius: 3px;
				background: #808080;
				font-family: inherit;
			}
			.toplevel_page_log_page .manage-column {
				width: auto;
			}
			.toplevel_page_log_page .column-description {
				width: 20%;
			}
			body h1.cbi-page-title:before {
				background-image: url(<?php echo plugins_url('assets/images/logo.png', CRYSTAL_BALL__FILE__) ?>);
				background-size: 1em 1em;
				background-repeat: no-repeat;
				display: inline-block;
				width: 1em;
				height: 1em;
				content:"";

				padding-inline-end: .2em;
				vertical-align: -18%;
			}
			#cbi-reset-filter {
				display: inline-block;
				margin-inline-start: 5px;
				line-height: 30px;
				text-decoration: none;
			}
			#cbi-reset-filter .dashicons {
				font-size: 15px;
				line-height: 30px;
				text-decoration: none;
			}
			@media (max-width: 767px) {
				.toplevel_page_log_page .manage-column {
					width: auto;
				}
				.toplevel_page_log_page .column-date,
				.toplevel_page_log_page .column-author {
					display: table-cell;
					width: auto;
				}
				.toplevel_page_log_page .column-description,
				.toplevel_page_log_page .column-label {
					display: none;
				}
				.toplevel_page_log_page .column-author .avatar {
					display: none;
				}
			}
		</style>
		<?php
}

    public function admin_header()
    {
        ?><style>
			#adminmenu #toplevel_page_log_page div.wp-menu-image:before {
				background-image: url(<?php echo plugins_url('assets/images/logo.png', CRYSTAL_BALL__FILE__) ?>);
				background-size: 1em 1em;
				background-repeat: no-repeat;
				margin-top: 6px;
				content:"";
			}
		</style>
	<?php
}

    public function ajax_cbi_install_elementor_set_admin_notice_viewed()
    {
        update_user_meta(get_current_user_id(), '_cbi_elementor_install_notice', 'true');
    }

    public function admin_notices()
    {
        if (!current_user_can('install_plugins') || $this->_is_elementor_installed()) {
            return;
        }

        if ('true' === get_user_meta(get_current_user_id(), '_cbi_elementor_install_notice', true)) {
            return;
        }

        if (!in_array(get_current_screen()->id, array('toplevel_page_log_page', 'dashboard', 'plugins', 'plugins-network'))) {
            return;
        }

        add_action('admin_footer', array(&$this, 'print_js'));

        $install_url = self_admin_url('plugin-install.php?tab=search&s=elementor');
        ?>
		<style>
			body h1.cbi-page-title:before {
				content: "\f321";
				font: 400 25px/1 dashicons !important;
				speak: none;
				color: #030303;
				display: inline-block;
				padding-inline-end: .2em;
				vertical-align: -18%;
			}
			#cbi-reset-filter {
				display: inline-block;
				margin-inline-start: 5px;
				line-height: 30px;
				text-decoration: none;
			}
			#cbi-reset-filter .dashicons {
				font-size: 15px;
				line-height: 30px;
				text-decoration: none;
			}
			.notice.cbi-notice {
				border-left-color: #92003B !important;
				padding: 20px;
			}
			.rtl .notice.cbi-notice {
				border-right-color: #92003B !important;
			}
			.notice.cbi-notice .cbi-notice-inner {
				display: table;
				width: 100%;
			}
			.notice.cbi-notice .cbi-notice-inner .cbi-notice-icon,
			.notice.cbi-notice .cbi-notice-inner .cbi-notice-content,
			.notice.cbi-notice .cbi-notice-inner .cbi-install-now {
				display: table-cell;
				vertical-align: middle;
			}
			.notice.cbi-notice .cbi-notice-icon {
				color: #92003B;
				font-size: 50px;
				width: 50px;
				height: 50px;
			}
			.notice.cbi-notice .cbi-notice-content {
				padding: 0 20px;
			}
			.notice.cbi-notice p {
				padding: 0;
				margin: 0;
			}
			.notice.cbi-notice h3 {
				margin: 0 0 5px;
			}
			.notice.cbi-notice .cbi-install-now {
				text-align: center;
			}
			.notice.cbi-notice .cbi-install-now .cbi-install-button {
				background-color: #92003B;
				color: #fff;
				border-color: #92003B;
				box-shadow: 0 1px 0 #92003B;
				padding: 5px 30px;
				height: auto;
				line-height: 20px;
				text-transform: capitalize;
			}
			.notice.cbi-notice .cbi-install-now .cbi-install-button i {
				padding-right: 5px;
			}
			.rtl .notice.cbi-notice .cbi-install-now .cbi-install-button i {
				padding-right: 0;
				padding-left: 5px;
			}
			.notice.cbi-notice .cbi-install-now .cbi-install-button:hover {
				background-color: #92003B;
			}
			.notice.cbi-notice .cbi-install-now .cbi-install-button:active {
				box-shadow: inset 0 1px 0 #92003B;
				transform: translateY(1px);
			}
			@media (max-width: 767px) {
				.notice.cbi-notice {
					padding: 10px;
				}
				.notice.cbi-notice .cbi-notice-inner {
					display: block;
				}
				.notice.cbi-notice .cbi-notice-inner .cbi-notice-content {
					display: block;
					padding: 0;
				}
				.notice.cbi-notice .cbi-notice-inner .cbi-notice-icon,
				.notice.cbi-notice .cbi-notice-inner .cbi-install-now {
					display: none;
				}
			}
		</style>
		<div class="notice updated is-dismissible cbi-notice cbi-install-elementor">
			<div class="cbi-notice-inner">
				<div class="cbi-notice-icon">
					<svg width="50" height="50" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M14.0035 3.98515e-07C8.34084 -0.00134594 3.23499 3.40874 1.06704 8.64C-1.10092 13.8713 0.0960255 19.8934 4.09968 23.898C8.10333 27.9026 14.1252 29.1009 19.3569 26.9342C24.5887 24.7675 28 19.6625 28 13.9998C28 6.26922 21.7341 0.00183839 14.0035 3.98515e-07Z" fill="#92003B"/>
						<rect x="8.1687" y="8.16504" width="2.3333" height="11.6665" fill="white"/>
						<rect x="12.8352" y="17.498" width="6.9999" height="2.3333" fill="white"/>
						<rect x="12.8352" y="12.8315" width="6.9999" height="2.3333" fill="white"/>
						<rect x="12.8352" y="8.16504" width="6.9999" height="2.3333" fill="white"/>
					</svg>
				</div>

				<div class="cbi-notice-content">
					<h3><?php _e('Do You Like Crystal Ball? You\'ll Love Elementor!', 'crystal-ball-insight');?></h3>
					<p><?php _e('Create high-end, pixel perfect websites at record speeds. Any theme, any page, any design. The most advanced frontend drag & drop page builder.', 'crystal-ball-insight');?>
						<a href="https://go.elementor.com/learn/" target="_blank"><?php _e('Learn more about Elementor', 'crystal-ball-insight');?></a>.</p>
				</div>

				<div class="cbi-install-now">
					<a class="button cbi-install-button" href="<?php echo esc_url($install_url); ?>"><i class="dashicons dashicons-download"></i><?php _e('Install Now For Free!', 'crystal-ball-insight');?></a>
				</div>
			</div>
		</div>
		<?php
}

    public function print_js()
    {
        ?>
		<script>jQuery( function( $ ) {
				$( 'div.notice.cbi-install-elementor' ).on( 'click', 'button.notice-dismiss', function( event ) {
					event.preventDefault();

					$.post( ajaxurl, {
						action: 'cbi_install_elementor_set_admin_notice_viewed'
					} );
				} );
			} );</script>
		<?php
}

    public function __construct()
    {
        add_action('admin_menu', array(&$this, 'create_admin_menu'), 20);
        add_action('admin_head', array(&$this, 'admin_header'));
        add_action('admin_notices', array(&$this, 'admin_notices'));
        add_action('wp_ajax_cbi_install_elementor_set_admin_notice_viewed', array(&$this, 'ajax_cbi_install_elementor_set_admin_notice_viewed'));
    }

    private function _is_elementor_installed()
    {
        $file_path = 'elementor/elementor.php';
        $installed_plugins = get_plugins();

        return isset($installed_plugins[$file_path]);
    }

    /**
     * @return CBI_Log_List_Table
     */
    public function get_list_table()
    {
        if (is_null($this->_list_table)) {
            $this->_list_table = new CBI_Log_List_Table(array('screen' => $this->_screens['main']));
            do_action('cbi_admin_page_load', $this->_list_table);
        }

        return $this->_list_table;
    }
}
