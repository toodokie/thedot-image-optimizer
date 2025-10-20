<?php
/**
 * The Dot - Dashboard Overview
 *
 * Brand-compliant dashboard with minimal design and quick stats.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard Page Class
 */
class MSH_Dashboard_Page {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_msh-optimizer' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'msh-dashboard',
			trailingslashit( MSH_IO_ASSETS_URL ) . 'css/dashboard.css',
			array(),
			MSH_Image_Optimizer_Plugin::VERSION
		);
	}

	/**
	 * Render dashboard page
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'msh-image-optimizer' ) );
		}

		// Get quick stats
		$stats = self::get_dashboard_stats();

		?>
		<div class="wrap msh-dashboard">
			<h1 class="msh-dashboard-title"><?php esc_html_e( 'The Dot Image Optimizer', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-dashboard-subtitle"><?php esc_html_e( 'Advanced image optimization and metadata management for WordPress.', 'msh-image-optimizer' ); ?></p>

			<div class="msh-dashboard-grid">
				<!-- Total Images -->
				<div class="msh-dashboard-card">
					<div class="msh-dashboard-card-icon">📊</div>
					<div class="msh-dashboard-card-content">
						<div class="msh-dashboard-card-value"><?php echo esc_html( number_format_i18n( $stats['total_images'] ) ); ?></div>
						<div class="msh-dashboard-card-label"><?php esc_html_e( 'Total Images', 'msh-image-optimizer' ); ?></div>
					</div>
				</div>

				<!-- Optimized -->
				<div class="msh-dashboard-card">
					<div class="msh-dashboard-card-icon">✓</div>
					<div class="msh-dashboard-card-content">
						<div class="msh-dashboard-card-value"><?php echo esc_html( number_format_i18n( $stats['optimized'] ) ); ?></div>
						<div class="msh-dashboard-card-label"><?php esc_html_e( 'Optimized', 'msh-image-optimizer' ); ?></div>
					</div>
				</div>

				<!-- AI Generated -->
				<div class="msh-dashboard-card">
					<div class="msh-dashboard-card-icon">🤖</div>
					<div class="msh-dashboard-card-content">
						<div class="msh-dashboard-card-value"><?php echo esc_html( number_format_i18n( $stats['ai_generated'] ) ); ?></div>
						<div class="msh-dashboard-card-label"><?php esc_html_e( 'AI Generated', 'msh-image-optimizer' ); ?></div>
					</div>
				</div>

				<!-- Queue -->
				<div class="msh-dashboard-card">
					<div class="msh-dashboard-card-icon">⏳</div>
					<div class="msh-dashboard-card-content">
						<div class="msh-dashboard-card-value"><?php echo esc_html( number_format_i18n( $stats['queue_pending'] ) ); ?></div>
						<div class="msh-dashboard-card-label"><?php esc_html_e( 'In Queue', 'msh-image-optimizer' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Quick Links -->
			<div class="msh-dashboard-section">
				<h2 class="msh-dashboard-section-title"><?php esc_html_e( 'Quick Actions', 'msh-image-optimizer' ); ?></h2>
				<div class="msh-dashboard-links">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-hub' ) ); ?>" class="msh-dashboard-link">
						<span class="dashicons dashicons-database-view"></span>
						<?php esc_html_e( 'Metadata Hub', 'msh-image-optimizer' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer' ) ); ?>" class="msh-dashboard-link">
						<span class="dashicons dashicons-format-image"></span>
						<?php esc_html_e( 'Image Optimizer', 'msh-image-optimizer' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-glossary' ) ); ?>" class="msh-dashboard-link">
						<span class="dashicons dashicons-book"></span>
						<?php esc_html_e( 'Glossary', 'msh-image-optimizer' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer-settings' ) ); ?>" class="msh-dashboard-link">
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Settings', 'msh-image-optimizer' ); ?>
					</a>
				</div>
			</div>
		</div>

		<style>
		.msh-dashboard {
			background: #FAF9F6;
			padding: 20px;
		}

		.msh-dashboard-title {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 28px;
			margin-bottom: 8px;
		}

		.msh-dashboard-subtitle {
			font-family: 'ff-real-text-pro', sans-serif;
			color: #8b8883;
			font-size: 16px;
			margin-bottom: 40px;
		}

		.msh-dashboard-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 20px;
			margin-bottom: 40px;
		}

		.msh-dashboard-card {
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 12px;
			padding: 24px;
			display: flex;
			align-items: center;
			gap: 16px;
			transition: border-color 0.2s;
		}

		.msh-dashboard-card:hover {
			border-color: #daff00;
		}

		.msh-dashboard-card-icon {
			font-size: 32px;
			line-height: 1;
		}

		.msh-dashboard-card-value {
			font-family: 'futura-pt', sans-serif;
			font-size: 32px;
			color: #35332f;
			font-weight: 700;
		}

		.msh-dashboard-card-label {
			font-family: 'ff-real-text-pro', sans-serif;
			font-size: 14px;
			color: #8b8883;
			text-transform: uppercase;
			letter-spacing: 0.05em;
		}

		.msh-dashboard-section {
			margin-top: 40px;
		}

		.msh-dashboard-section-title {
			font-family: 'futura-pt', sans-serif;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: #35332f;
			font-size: 20px;
			margin-bottom: 20px;
		}

		.msh-dashboard-links {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 16px;
		}

		.msh-dashboard-link {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 16px;
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 12px;
			color: #35332f;
			text-decoration: none;
			font-family: 'ff-real-text-pro', sans-serif;
			transition: all 0.2s;
		}

		.msh-dashboard-link:hover {
			border-color: #daff00;
			background: #daff00;
			color: #35332f;
		}

		.msh-dashboard-link .dashicons {
			color: #8b8883;
		}

		.msh-dashboard-link:hover .dashicons {
			color: #35332f;
		}
		</style>
		<?php
	}

	/**
	 * Get dashboard stats
	 *
	 * @return array Stats array
	 */
	private static function get_dashboard_stats() {
		global $wpdb;

		// Total images
		$total_images = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		// Optimized (has metadata cache)
		$optimized = 0;
		if ( class_exists( 'MSH_Metadata_Database' ) ) {
			$cache_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$cache_table}'" ) === $cache_table ) {
				$optimized = (int) $wpdb->get_var(
					"SELECT COUNT(DISTINCT attachment_id) FROM {$cache_table}"
				);
			}
		}

		// AI generated
		$ai_generated = 0;
		if ( class_exists( 'MSH_Metadata_Database' ) ) {
			$cache_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$cache_table}'" ) === $cache_table ) {
				$ai_generated = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$cache_table} WHERE chosen_source = 'ai' AND ai_value IS NOT NULL AND ai_value != ''"
				);
			}
		}

		// Queue pending
		$queue_pending = 0;
		if ( function_exists( 'msh_get_job_stats' ) ) {
			$job_stats = msh_get_job_stats();
			if ( ! is_wp_error( $job_stats ) && isset( $job_stats['pending'] ) ) {
				$queue_pending = (int) $job_stats['pending'];
			}
		}

		return array(
			'total_images'   => $total_images,
			'optimized'      => $optimized,
			'ai_generated'   => $ai_generated,
			'queue_pending'  => $queue_pending,
		);
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Dashboard_Page();
}
