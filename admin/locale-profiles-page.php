<?php
/**
 * Locale Profiles Manager
 *
 * Brand-compliant interface for managing locale-specific optimization settings.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locale Profiles Page Class
 */
class MSH_Locale_Profiles_Page {

	/**
	 * Page slug
	 */
	const PAGE_SLUG = 'msh-locale-profiles';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu page
	 */
	public function register_menu() {
		add_submenu_page(
			'msh-optimizer',
			__( 'Locale Profiles', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-translation"></span> ' . __( 'Locale Profiles', 'msh-image-optimizer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue assets
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'the-dot_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'msh-image-optimizer-fonts',
			'https://use.typekit.net/gac6jnd.css',
			array(),
			null
		);
	}

	/**
	 * Render locale profiles page
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		?>
		<div class="wrap msh-locale-page">
			<h1 class="msh-page-title"><?php esc_html_e( 'Locale Profiles', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-page-subtitle"><?php esc_html_e( 'Configure locale-specific optimization settings and cultural adaptation rules.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-locale-container">
				<!-- Active Locales -->
				<div class="msh-locale-active">
					<h2><?php esc_html_e( 'Active Locales', 'msh-image-optimizer' ); ?></h2>

					<div class="msh-notice msh-notice-info">
						<p><?php esc_html_e( 'Locale profile management is coming in Phase 3. This will allow you to configure AI behavior for different languages and regions.', 'msh-image-optimizer' ); ?></p>
					</div>

					<div class="msh-locale-list">
						<!-- English (Default) -->
						<div class="msh-locale-card">
							<div class="msh-locale-card-header">
								<div class="msh-locale-flag">🇺🇸</div>
								<div class="msh-locale-info">
									<div class="msh-locale-name">English (United States)</div>
									<div class="msh-locale-code">en_US</div>
								</div>
								<span class="msh-locale-badge"><?php esc_html_e( 'Default', 'msh-image-optimizer' ); ?></span>
							</div>
							<div class="msh-locale-card-body">
								<p><?php esc_html_e( 'Primary locale for metadata generation.', 'msh-image-optimizer' ); ?></p>
							</div>
							<div class="msh-locale-card-footer">
								<button class="button button-secondary msh-btn-configure" disabled><?php esc_html_e( 'Configure', 'msh-image-optimizer' ); ?></button>
							</div>
						</div>

						<!-- Spanish -->
						<div class="msh-locale-card msh-locale-card-disabled">
							<div class="msh-locale-card-header">
								<div class="msh-locale-flag">🇪🇸</div>
								<div class="msh-locale-info">
									<div class="msh-locale-name">Spanish (Spain)</div>
									<div class="msh-locale-code">es_ES</div>
								</div>
								<span class="msh-locale-badge msh-locale-badge-inactive"><?php esc_html_e( 'Inactive', 'msh-image-optimizer' ); ?></span>
							</div>
							<div class="msh-locale-card-body">
								<p><?php esc_html_e( 'Enable Spanish metadata generation with cultural adaptation.', 'msh-image-optimizer' ); ?></p>
							</div>
							<div class="msh-locale-card-footer">
								<button class="button button-primary msh-btn-enable" disabled><?php esc_html_e( 'Enable', 'msh-image-optimizer' ); ?></button>
							</div>
						</div>

						<!-- French -->
						<div class="msh-locale-card msh-locale-card-disabled">
							<div class="msh-locale-card-header">
								<div class="msh-locale-flag">🇫🇷</div>
								<div class="msh-locale-info">
									<div class="msh-locale-name">French (France)</div>
									<div class="msh-locale-code">fr_FR</div>
								</div>
								<span class="msh-locale-badge msh-locale-badge-inactive"><?php esc_html_e( 'Inactive', 'msh-image-optimizer' ); ?></span>
							</div>
							<div class="msh-locale-card-body">
								<p><?php esc_html_e( 'Enable French metadata generation with cultural adaptation.', 'msh-image-optimizer' ); ?></p>
							</div>
							<div class="msh-locale-card-footer">
								<button class="button button-primary msh-btn-enable" disabled><?php esc_html_e( 'Enable', 'msh-image-optimizer' ); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<style>
		.msh-locale-page {
			background: #FAF9F6;
			padding: 20px;
		}

		.msh-page-title {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 28px;
			margin-bottom: 8px;
		}

		.msh-page-subtitle {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 16px;
			margin-bottom: 40px;
		}

		.msh-locale-active h2 {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 20px;
			margin-bottom: 20px;
		}

		.msh-notice {
			padding: 16px;
			border-radius: 8px;
			margin-bottom: 20px;
		}

		.msh-notice-info {
			background: #e8f4fd;
			border-left: 4px solid #0073aa;
		}

		.msh-notice p {
			margin: 0;
			font-family: 'ff-real-text-pro', sans-serif;
			color: #35332f;
			font-size: 14px;
		}

		.msh-locale-list {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
			gap: 20px;
		}

		.msh-locale-card {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 12px;
			overflow: hidden;
			transition: border-color 0.2s;
		}

		.msh-locale-card:hover {
			border-color: #daff00;
		}

		.msh-locale-card-disabled {
			opacity: 0.6;
		}

		.msh-locale-card-header {
			padding: 20px;
			display: flex;
			align-items: center;
			gap: 12px;
			border-bottom: 1px solid #eee;
		}

		.msh-locale-flag {
			font-size: 32px;
			line-height: 1;
		}

		.msh-locale-info {
			flex: 1;
		}

		.msh-locale-name {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			color: #35332f;
			font-size: 14px;
			font-weight: 700;
		}

		.msh-locale-code {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 12px;
			margin-top: 4px;
		}

		.msh-locale-badge {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			font-size: 10px;
			padding: 4px 8px;
			border-radius: 4px;
			background: #daff00;
			color: #35332f;
			font-weight: 700;
		}

		.msh-locale-badge-inactive {
			background: #eee;
			color: #8b8883;
		}

		.msh-locale-card-body {
			padding: 20px;
		}

		.msh-locale-card-body p {
			margin: 0;
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 14px;
			line-height: 1.6;
		}

		.msh-locale-card-footer {
			padding: 20px;
			border-top: 1px solid #eee;
			text-align: right;
		}

		.msh-btn-configure,
		.msh-btn-enable {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			padding: 8px 16px !important;
			border-radius: 8px;
		}

		.msh-btn-enable {
			background: #35332f !important;
			border-color: #35332f !important;
			color: #FAF9F6 !important;
		}

		.msh-btn-enable:hover:not(:disabled) {
			background: #daff00 !important;
			border-color: #daff00 !important;
			color: #35332f !important;
		}
		</style>
		<?php
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Locale_Profiles_Page();
}
