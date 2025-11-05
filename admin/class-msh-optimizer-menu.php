<?php
/**
 * The Dot Optimizer - Main Menu Registration
 *
 * Clean, user-intent focused menu structure with mode awareness.
 *
 * Menu collapses from 10 items to 7 clean sections:
 * 1. Dashboard - Overview & health
 * 2. Image Optimizer - Day-to-day optimization
 * 3. Optimizer Hub - Advanced operations (Cache, Queue, Events, History, Sync)
 * 4. Insights & Analytics - Context, A/B testing, metrics (Pro + Advanced)
 * 5. Localization - Profiles + Glossary tabs (Advanced)
 * 6. Review Center - Approvals + History tabs (Pro + Advanced)
 * 7. Settings - Configuration & license
 * 8. Help & Docs - Support resources
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
	 * User mode option key
	 */
	const USER_MODE_OPTION = 'msh_user_mode';

	/**
	 * Constructor - registers hooks
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_branding' ) );
	}

	/**
	 * Enqueue TinyDot logo branding CSS for admin menu
	 */
	public function enqueue_menu_branding() {
		$assets_base = defined( 'MSH_IO_ASSETS_URL' )
			? trailingslashit( MSH_IO_ASSETS_URL )
			: trailingslashit( plugin_dir_url( __FILE__ ) . '../assets' );

		$style_file    = dirname( __FILE__ ) . '/../assets/css/admin-menu-branding.css';
		$style_version = file_exists( $style_file ) ? filemtime( $style_file ) : '1.0.0';

		wp_enqueue_style(
			'msh-admin-menu-branding',
			$assets_base . 'css/admin-menu-branding.css',
			array(),
			$style_version
		);
	}

	/**
	 * Get current user mode.
	 *
	 * @return string 'basic' or 'advanced'
	 */
	private function get_user_mode() {
		return get_option( self::USER_MODE_OPTION, 'basic' );
	}

	/**
	 * Check if user has Pro license.
	 *
	 * @return bool
	 */
	private function is_pro_active() {
		if ( ! class_exists( 'MSH_License_Manager' ) ) {
			return false;
		}
		$license_manager = MSH_License_Manager::get_instance();
		return $license_manager->is_pro_active();
	}

	/**
	 * Register The Dot menu structure
	 *
	 * @return void
	 */
	public function register_menu() {
		$user_mode = $this->get_user_mode();
		$is_pro = $this->is_pro_active();
		$is_advanced = 'advanced' === $user_mode;

		// Top-level menu
		add_menu_page(
			__( 'MSH Optimizer', 'msh-image-optimizer' ),
			__( 'MSH-Optimizer', 'msh-image-optimizer' ),
			'manage_options',
			'msh-optimizer',
			array( 'MSH_Dashboard_Page', 'render' ),
			$this->get_menu_icon(),
			self::MENU_POSITION
		);

		// 1. Dashboard (always visible - same slug as parent to avoid duplicate)
		add_submenu_page(
			'msh-optimizer',
			__( 'Dashboard', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-dashboard"></span> ' . __( 'Dashboard', 'msh-image-optimizer' ),
			'manage_options',
			'msh-optimizer',
			array( 'MSH_Dashboard_Page', 'render' )
		);

		// 2. Image Optimizer (always visible)
		add_submenu_page(
			'msh-optimizer',
			__( 'Image Optimizer', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-images-alt2"></span> ' . __( 'Image Optimizer', 'msh-image-optimizer' ),
			'manage_options',
			'msh-image-optimizer',
			array( $this, 'render_image_optimizer_page' )
		);

		// 3. Optimizer Hub (Advanced mode only)
		if ( $is_advanced ) {
			add_submenu_page(
				'msh-optimizer',
				__( 'Optimizer Hub', 'msh-image-optimizer' ),
				'<span class="dashicons dashicons-database"></span> ' . __( 'Optimizer Hub', 'msh-image-optimizer' ),
				'manage_options',
				'msh-hub',
				array( $this, 'render_hub_page' )
			);
		}

		// 4. Insights & Analytics (Pro + Advanced mode)
		if ( $is_pro && $is_advanced ) {
			add_submenu_page(
				'msh-optimizer',
				__( 'Insights & Analytics', 'msh-image-optimizer' ),
				'<span class="dashicons dashicons-chart-line"></span> ' . __( 'Insights & Analytics', 'msh-image-optimizer' ),
				'manage_options',
				'msh-insights',
				array( $this, 'render_insights_page' )
			);
		}

		// 5. Localization (Advanced mode only)
		if ( $is_advanced ) {
			add_submenu_page(
				'msh-optimizer',
				__( 'Localization', 'msh-image-optimizer' ),
				'<span class="dashicons dashicons-translation"></span> ' . __( 'Localization', 'msh-image-optimizer' ),
				'manage_options',
				'msh-localization',
				array( $this, 'render_localization_page' )
			);
		}

		// 6. Review Center (Pro + Advanced mode)
		if ( $is_pro && $is_advanced ) {
			add_submenu_page(
				'msh-optimizer',
				__( 'Review Center', 'msh-image-optimizer' ),
				'<span class="dashicons dashicons-yes-alt"></span> ' . __( 'Review Center', 'msh-image-optimizer' ),
				'manage_options',
				'msh-review-center',
				array( $this, 'render_review_center_page' )
			);
		}

		// 7. Settings (always visible)
		$settings_hook = add_submenu_page(
			'msh-optimizer',
			__( 'Settings', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-admin-generic"></span> ' . __( 'Settings', 'msh-image-optimizer' ),
			'manage_options',
			'msh-image-optimizer-settings',
			array( $this, 'render_settings_page' )
		);

		// Pass the hook suffix to Settings class so CSS enqueue works regardless of menu title
		if ( class_exists( 'MSH_Image_Optimizer_Settings' ) ) {
			MSH_Image_Optimizer_Settings::set_page_hook( $settings_hook );
		}

		// 8. Help & Docs (always visible)
		add_submenu_page(
			'msh-optimizer',
			__( 'Help & Docs', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-editor-help"></span> ' . __( 'Help & Docs', 'msh-image-optimizer' ),
			'manage_options',
			'msh-help',
			array( $this, 'render_help_page' )
		);

		// Remove ALL duplicate menu items registered by standalone page files
		// These are now handled by the tabbed interfaces above
		remove_submenu_page( 'msh-optimizer', 'msh-locale-profiles' );
		remove_submenu_page( 'msh-optimizer', 'msh-glossary' );
		remove_submenu_page( 'msh-optimizer', 'msh-context-analytics' );
		remove_submenu_page( 'msh-optimizer', 'msh-version-history' );
		remove_submenu_page( 'msh-optimizer', 'msh-ab-testing' );
		remove_submenu_page( 'msh-optimizer', 'msh-approval-queue' );
	}

	/**
	 * Render dashboard page (handled by MSH_Dashboard_Page)
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( class_exists( 'MSH_Dashboard_Page' ) ) {
			MSH_Dashboard_Page::render();
		}
	}

	/**
	 * Render Insights & Analytics page (tabbed interface)
	 *
	 * Tabs: Context Analytics | A/B Testing | Metrics
	 *
	 * @return void
	 */
	public function render_insights_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'context';

		?>
		<div class="wrap msh-insights-page">
			<?php include dirname( __FILE__ ) . '/partials/page-header.php'; ?>
			<h1><?php esc_html_e( 'Insights & Analytics', 'msh-image-optimizer' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=msh-insights&tab=context" class="nav-tab <?php echo 'context' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Context Analytics', 'msh-image-optimizer' ); ?>
				</a>
				<a href="?page=msh-insights&tab=ab-testing" class="nav-tab <?php echo 'ab-testing' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'A/B Testing', 'msh-image-optimizer' ); ?>
				</a>
				<a href="?page=msh-insights&tab=metrics" class="nav-tab <?php echo 'metrics' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Metrics', 'msh-image-optimizer' ); ?>
				</a>
			</nav>

			<div class="msh-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'ab-testing':
						if ( class_exists( 'MSH_AB_Testing_Page' ) ) {
							MSH_AB_Testing_Page::render();
						}
						break;
					case 'metrics':
						$this->render_metrics_tab();
						break;
					case 'context':
					default:
						if ( class_exists( 'MSH_Context_Analytics_Page' ) ) {
							MSH_Context_Analytics_Page::render();
						}
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Localization page (tabbed interface)
	 *
	 * Tabs: Locale Profiles | Glossary
	 *
	 * @return void
	 */
	public function render_localization_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'profiles';

		?>
		<div class="wrap msh-localization-page">
			<?php include dirname( __FILE__ ) . '/partials/page-header.php'; ?>
			<h1><?php esc_html_e( 'Localization', 'msh-image-optimizer' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=msh-localization&tab=profiles" class="nav-tab <?php echo 'profiles' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Locale Profiles', 'msh-image-optimizer' ); ?>
				</a>
				<a href="?page=msh-localization&tab=glossary" class="nav-tab <?php echo 'glossary' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Glossary', 'msh-image-optimizer' ); ?>
				</a>
			</nav>

			<div class="msh-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'glossary':
						if ( class_exists( 'MSH_Glossary_Page' ) ) {
							$glossary = new MSH_Glossary_Page();
							$glossary->render();
						}
						break;
					case 'profiles':
					default:
						if ( class_exists( 'MSH_Locale_Profiles_Page' ) ) {
							$profiles = new MSH_Locale_Profiles_Page();
							$profiles->render();
						}
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Review Center page (tabbed interface)
	 *
	 * Tabs: Approval Queue | Version History
	 *
	 * @return void
	 */
	public function render_review_center_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'approvals';

		?>
		<div class="wrap msh-review-center-page">
			<?php include dirname( __FILE__ ) . '/partials/page-header.php'; ?>
			<h1><?php esc_html_e( 'Review Center', 'msh-image-optimizer' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=msh-review-center&tab=approvals" class="nav-tab <?php echo 'approvals' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Approval Queue', 'msh-image-optimizer' ); ?>
				</a>
				<a href="?page=msh-review-center&tab=history" class="nav-tab <?php echo 'history' === $current_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Version History', 'msh-image-optimizer' ); ?>
				</a>
			</nav>

			<div class="msh-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'history':
						if ( class_exists( 'MSH_Version_History_Page' ) ) {
							MSH_Version_History_Page::render();
						}
						break;
					case 'approvals':
					default:
						if ( class_exists( 'MSH_Approval_Queue_Page' ) ) {
							MSH_Approval_Queue_Page::render();
						}
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Image Optimizer page (handled by MSH_Image_Optimizer_Admin)
	 *
	 * @return void
	 */
	public function render_image_optimizer_page() {
		if ( class_exists( 'MSH_Image_Optimizer_Admin' ) ) {
			global $msh_optimizer_admin_instance;
			if ( isset( $msh_optimizer_admin_instance ) && is_object( $msh_optimizer_admin_instance ) ) {
				$msh_optimizer_admin_instance->admin_page();
			}
		}
	}

	/**
	 * Render Optimizer Hub page (handled by MSH_Hub_Page)
	 *
	 * @return void
	 */
	public function render_hub_page() {
		if ( class_exists( 'MSH_Hub_Page' ) ) {
			$hub = MSH_Hub_Page::get_instance();
			if ( method_exists( $hub, 'render_page' ) ) {
				$hub->render_page();
			}
		}
	}

	/**
	 * Render Settings page (handled by MSH_Image_Optimizer_Settings)
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		if ( ! class_exists( 'MSH_Image_Optimizer_Settings' ) ) {
			wp_die( esc_html__( 'Settings class not found.', 'msh-image-optimizer' ) );
		}

		try {
			$settings = new MSH_Image_Optimizer_Settings();
			if ( method_exists( $settings, 'render_settings_page' ) ) {
				$settings->render_settings_page();
			} else {
				wp_die( esc_html__( 'Settings render method not found.', 'msh-image-optimizer' ) );
			}
		} catch ( Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Render Help & Docs page
	 *
	 * @return void
	 */
	public function render_help_page() {
		?>
		<div class="wrap msh-help-page">
			<?php include dirname( __FILE__ ) . '/partials/page-header.php'; ?>
			<h1><?php esc_html_e( 'Help & Documentation', 'msh-image-optimizer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Get help, check system health, and access documentation.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-help-grid">
				<div class="msh-help-card">
					<h2><span class="dashicons dashicons-book-alt"></span> <?php esc_html_e( 'Documentation', 'msh-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'Learn how to use The Dot Optimizer effectively.', 'msh-image-optimizer' ); ?></p>
					<a href="https://thedot.com/docs" target="_blank" class="button button-primary"><?php esc_html_e( 'View Docs', 'msh-image-optimizer' ); ?></a>
				</div>

				<div class="msh-help-card">
					<h2><span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Support', 'msh-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'Get help from our support team.', 'msh-image-optimizer' ); ?></p>
					<a href="https://thedot.com/support" target="_blank" class="button"><?php esc_html_e( 'Contact Support', 'msh-image-optimizer' ); ?></a>
				</div>

				<div class="msh-help-card">
					<h2><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'System Health', 'msh-image-optimizer' ); ?></h2>
					<?php $this->render_system_health(); ?>
				</div>

				<div class="msh-help-card">
					<h2><span class="dashicons dashicons-editor-ul"></span> <?php esc_html_e( 'Changelog', 'msh-image-optimizer' ); ?></h2>
					<p><?php esc_html_e( 'See what\'s new in the latest version.', 'msh-image-optimizer' ); ?></p>
					<a href="https://thedot.com/changelog" target="_blank" class="button"><?php esc_html_e( 'View Changelog', 'msh-image-optimizer' ); ?></a>
				</div>
			</div>
		</div>

		<style>
		.msh-help-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 20px;
			margin-top: 30px;
		}

		.msh-help-card {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 24px;
		}

		.msh-help-card h2 {
			display: flex;
			align-items: center;
			gap: 10px;
			font-size: 18px;
			margin-top: 0;
		}

		.msh-help-card h2 .dashicons {
			color: #2271b1;
		}

		.msh-help-card .button {
			margin-top: 12px;
		}
		</style>
		<?php
	}

	/**
	 * Render metrics tab content
	 *
	 * @return void
	 */
	private function render_metrics_tab() {
		?>
		<div class="msh-metrics-content">
			<h2><?php esc_html_e( '28-Day Performance Metrics', 'msh-image-optimizer' ); ?></h2>
			<p><?php esc_html_e( 'Track optimization trends and system performance over time.', 'msh-image-optimizer' ); ?></p>

			<div class="notice notice-info">
				<p><?php esc_html_e( 'Metrics tracking is coming soon. This will show optimization trends, job success rates, and system performance over 28-day rolling periods.', 'msh-image-optimizer' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render system health check
	 *
	 * @return void
	 */
	private function render_system_health() {
		$checks = array(
			'php_version'   => version_compare( PHP_VERSION, '7.4', '>=' ),
			'wp_version'    => version_compare( get_bloginfo( 'version' ), '5.8', '>=' ),
			'memory_limit'  => wp_convert_hr_to_bytes( WP_MEMORY_LIMIT ) >= 128 * 1024 * 1024,
		);

		$all_passed = ! in_array( false, $checks, true );

		?>
		<ul class="msh-health-checks">
			<li class="<?php echo $checks['php_version'] ? 'passed' : 'failed'; ?>">
				<span class="dashicons <?php echo $checks['php_version'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<?php
				printf(
					/* translators: %s: PHP version */
					esc_html__( 'PHP Version: %s', 'msh-image-optimizer' ),
					esc_html( PHP_VERSION )
				);
				?>
			</li>
			<li class="<?php echo $checks['wp_version'] ? 'passed' : 'failed'; ?>">
				<span class="dashicons <?php echo $checks['wp_version'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<?php
				printf(
					/* translators: %s: WordPress version */
					esc_html__( 'WordPress Version: %s', 'msh-image-optimizer' ),
					esc_html( get_bloginfo( 'version' ) )
				);
				?>
			</li>
			<li class="<?php echo $checks['memory_limit'] ? 'passed' : 'failed'; ?>">
				<span class="dashicons <?php echo $checks['memory_limit'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
				<?php
				printf(
					/* translators: %s: Memory limit */
					esc_html__( 'Memory Limit: %s', 'msh-image-optimizer' ),
					esc_html( WP_MEMORY_LIMIT )
				);
				?>
			</li>
		</ul>

		<?php if ( $all_passed ) : ?>
			<p class="msh-health-status passed">
				<strong><?php esc_html_e( 'All checks passed!', 'msh-image-optimizer' ); ?></strong>
			</p>
		<?php else : ?>
			<p class="msh-health-status failed">
				<strong><?php esc_html_e( 'Some checks failed. Please review.', 'msh-image-optimizer' ); ?></strong>
			</p>
		<?php endif; ?>

		<style>
		.msh-health-checks {
			list-style: none;
			padding: 0;
			margin: 16px 0;
		}

		.msh-health-checks li {
			padding: 8px 0;
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.msh-health-checks li.passed .dashicons {
			color: #46b450;
		}

		.msh-health-checks li.failed .dashicons {
			color: #dc3232;
		}

		.msh-health-status {
			padding: 12px;
			border-radius: 4px;
			margin-top: 16px;
		}

		.msh-health-status.passed {
			background: #ecf7ed;
			border-left: 4px solid #46b450;
		}

		.msh-health-status.failed {
			background: #fcf0f1;
			border-left: 4px solid #dc3232;
		}
		</style>
		<?php
	}

	/**
	 * Get menu icon (dashicon)
	 *
	 * @return string Dashicon class
	 */
	private function get_menu_icon() {
		return 'dashicons-images-alt2';
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Optimizer_Menu();
}
