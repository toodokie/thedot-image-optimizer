<?php
/**
 * The Dot Optimizer - Main Menu Registration
 *
 * Creates the top-level "The Dot" menu with all submenus, icons, and separators.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main menu registration class
 */
class MSH_Optimizer_Menu {

	/**
	 * Menu position in WordPress admin
	 */
	const MENU_POSITION = 58;

	/**
	 * Constructor - registers hooks
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
	}

	/**
	 * Register The Dot menu structure with submenus
	 *
	 * @return void
	 */
	public function register_menu() {
		// Create top-level "The Dot" menu
		add_menu_page(
			__( 'The Dot Image Optimizer', 'msh-image-optimizer' ),
			__( 'The Dot', 'msh-image-optimizer' ),
			'manage_options',
			'msh-optimizer',
			array( $this, 'render_dashboard' ),
			$this->get_menu_icon(),
			self::MENU_POSITION
		);

		// Dashboard (replace default first submenu)
		add_submenu_page(
			'msh-optimizer',
			__( 'Dashboard', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-dashboard"></span> ' . __( 'Dashboard', 'msh-image-optimizer' ),
			'manage_options',
			'msh-optimizer',
			array( 'MSH_Dashboard_Page', 'render' )
		);

		// Separator 1
		add_submenu_page(
			'msh-optimizer',
			'',
			'<span style="display:block; margin: 5px 0; padding: 0; height: 1px; line-height: 1px; background: rgba(255,255,255,0.2);"></span>',
			'manage_options',
			'#separator-1',
			'__return_null'
		);

		// Note: Glossary, Locale Profiles register themselves via their own classes
		// Note: Image Optimizer, Context Analytics, Hub, Settings register themselves
		// Note: Separator 2 will be added after those pages register
	}

	/**
	 * Get menu icon (inline SVG data URI)
	 *
	 * @return string SVG data URI for menu icon
	 */
	private function get_menu_icon() {
		return 'dashicons-admin-media';
	}

}

// Initialize
if ( is_admin() ) {
	new MSH_Optimizer_Menu();
}
