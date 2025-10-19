<?php
/**
 * The Dot Optimizer Admin Menu
 *
 * Manages the top-level admin menu and all submenu pages.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Optimizer_Menu
 *
 * Creates a professional top-level admin menu with hierarchical organization.
 */
class MSH_Optimizer_Menu {

	/**
	 * Singleton instance
	 *
	 * @var MSH_Optimizer_Menu
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Optimizer_Menu
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_styles' ) );
	}

	/**
	 * Register the top-level menu and all submenus
	 */
	public function register_menu() {
		// Top-level menu with Dashboard page
		add_menu_page(
			__( 'The Dot Optimizer', 'msh-image-optimizer' ),           // Page title
			__( 'The Dot', 'msh-image-optimizer' ),                     // Menu title (short)
			'manage_options',                                           // Capability
			'msh-optimizer',                                            // Menu slug
			array( $this, 'render_dashboard_page' ),                   // Callback
			$this->get_menu_icon(),                                     // Icon
			58                                                          // Position (after Media)
		);

		// Dashboard submenu (duplicate of parent for cleaner UI)
		add_submenu_page(
			'msh-optimizer',
			__( 'Dashboard', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-dashboard"></span> ' . __( 'Dashboard', 'msh-image-optimizer' ),
			'manage_options',
			'msh-optimizer',
			array( $this, 'render_dashboard_page' )
		);

		// Separator 1 (visual grouping for modules)
		add_submenu_page(
			'msh-optimizer',
			'',
			'<span class="msh-menu-separator">────────────────</span>',
			'manage_options',
			'#',
			'__return_null'
		);

		// Image Optimizer module
		global $msh_image_optimizer_admin;
		add_submenu_page(
			'msh-optimizer',
			__( 'Image Optimizer', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-format-gallery"></span> ' . __( 'Image Optimizer', 'msh-image-optimizer' ),
			'manage_options',
			'msh-image-optimizer',
			array( $msh_image_optimizer_admin, 'admin_page' )
		);

		// Context Analytics module
		global $msh_context_analytics_page;
		add_submenu_page(
			'msh-optimizer',
			__( 'Context Analytics', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-chart-bar"></span> ' . __( 'Context Analytics', 'msh-image-optimizer' ),
			'manage_options',
			'msh-context-analytics',
			array( $msh_context_analytics_page, 'render_page' )
		);

		// Locale Profiles module (Phase 3 - NEW)
		add_submenu_page(
			'msh-optimizer',
			__( 'Locale Profiles', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-translation"></span> ' . __( 'Locale Profiles', 'msh-image-optimizer' ),
			'manage_options',
			'msh-locale-profiles',
			array( $this, 'render_locale_profiles_page' )
		);

		// Glossary module (Phase 3)
		add_submenu_page(
			'msh-optimizer',
			__( 'Glossary', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-book"></span> ' . __( 'Glossary', 'msh-image-optimizer' ),
			'manage_options',
			'msh-glossary',
			array( $this, 'render_glossary_page' )
		);

		// Separator 2 (visual grouping before settings)
		add_submenu_page(
			'msh-optimizer',
			'',
			'<span class="msh-menu-separator">────────────────</span>',
			'manage_options',
			'##',
			'__return_null'
		);

		// Settings submenu
		global $msh_image_optimizer_settings;
		add_submenu_page(
			'msh-optimizer',
			__( 'Settings', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-admin-settings"></span> ' . __( 'Settings', 'msh-image-optimizer' ),
			'manage_options',
			'msh-image-optimizer-settings',
			array( $msh_image_optimizer_settings, 'render_settings_page' )
		);
	}

	/**
	 * Get menu icon (using Favicon.svg)
	 *
	 * @return string
	 */
	private function get_menu_icon() {
		// Use Favicon.svg (user's brand icon)
		$icon_url = MSH_IO_ASSETS_URL . 'icons/Favicon.svg';

		// Return URL to icon file
		return $icon_url;
	}

	/**
	 * Enqueue menu-specific styles
	 */
	public function enqueue_menu_styles() {
		// Enqueue on all admin pages to style the menu
		wp_add_inline_style( 'wp-admin', '
			/* Menu separators - respect WP color scheme */
			.msh-menu-separator {
				display: inline-block;
				color: inherit;
				font-size: 10px;
				pointer-events: none;
				opacity: 0.3;
			}

			/* Hide separator links from being clickable */
			#adminmenu .wp-submenu a[href="#"],
			#adminmenu .wp-submenu a[href="##"] {
				cursor: default;
				pointer-events: none;
				background: none !important;
			}

			#adminmenu .wp-submenu a[href="#"]:hover,
			#adminmenu .wp-submenu a[href="##"]:hover {
				color: inherit;
			}

			/* Top-level menu icon - even lighter, cooler grey, 20% smaller */
			#adminmenu #toplevel_page_msh-optimizer .wp-menu-image img {
				width: 16px;
				height: 16px;
				filter: brightness(0) invert(85%) sepia(3%) saturate(150%) hue-rotate(180deg) brightness(115%) contrast(85%);
			}

			#adminmenu #toplevel_page_msh-optimizer:hover .wp-menu-image img {
				filter: brightness(0) invert(92%) sepia(2%) saturate(100%) hue-rotate(180deg) brightness(118%) contrast(85%);
			}

			#adminmenu #toplevel_page_msh-optimizer.current .wp-menu-image img,
			#adminmenu #toplevel_page_msh-optimizer.wp-has-current-submenu .wp-menu-image img {
				filter: brightness(0) invert(100%) sepia(0%) saturate(0%) hue-rotate(0deg) brightness(100%) contrast(100%);
			}

			/* Submenu icons spacing - respect WP color scheme */
			#adminmenu #toplevel_page_msh-optimizer .wp-submenu .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				margin-right: 4px;
				vertical-align: text-bottom;
				opacity: 0.6;
			}

			/* Active submenu icon highlight */
			#adminmenu #toplevel_page_msh-optimizer .wp-submenu .current .dashicons,
			#adminmenu #toplevel_page_msh-optimizer .wp-submenu a:hover .dashicons {
				opacity: 1;
			}
		' );
	}

	/**
	 * Render Dashboard page
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msh-image-optimizer' ) );
		}

		// Include dashboard page template
		require_once MSH_IO_PLUGIN_DIR . 'admin/dashboard-page.php';
	}

	/**
	 * Render Locale Profiles page (Phase 3)
	 */
	public function render_locale_profiles_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msh-image-optimizer' ) );
		}

		// Include locale profiles page template
		require_once MSH_IO_PLUGIN_DIR . 'admin/locale-profiles-page.php';
	}

	/**
	 * Render Glossary page (Phase 3)
	 */
	public function render_glossary_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msh-image-optimizer' ) );
		}

		// Include glossary page template
		require_once MSH_IO_PLUGIN_DIR . 'admin/glossary-page.php';
	}
}
