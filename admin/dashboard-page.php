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

		// Check user mode
		$user_mode = get_option( 'msh_user_mode', 'basic' );

		// Render based on mode
		if ( 'advanced' === $user_mode ) {
			self::render_advanced_dashboard();
		} else {
			self::render_basic_dashboard();
		}
	}

	/**
	 * Render Basic Mode Dashboard
	 * Friendly, calm, 3 tabs: Overview, Balance, Tips
	 *
	 * @return void
	 */
	private static function render_basic_dashboard() {
		// Get quick stats
		$stats = self::get_dashboard_stats();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';

		?>
		<div class="wrap msh-dashboard msh-dashboard-basic">
			<!-- Product Header -->
			<div class="msh-page-header">
				<div class="msh-logo-container">
					<img src="<?php echo esc_url( trailingslashit( MSH_IO_ASSETS_URL ) . 'icons/optimizer-logo.svg' ); ?>"
						alt="<?php esc_attr_e( 'The Dot Image Optimizer', 'msh-image-optimizer' ); ?>"
						class="msh-logo" />
				</div>
				<div class="msh-header-links">
					<a href="mailto:support@thedot.com" class="msh-support-link">
						<span class="msh-support-text"><?php esc_html_e( 'reach out for support', 'msh-image-optimizer' ); ?></span>
						<svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-mail-icon">
							<path d="M4 20.3735C3.45 20.3735 2.975 20.1819 2.575 19.7985C2.19167 19.3985 2 18.9235 2 18.3735V6.37353C2 5.82354 2.19167 5.35687 2.575 4.97353C2.975 4.57353 3.45 4.37354 4 4.37354H20C20.55 4.37354 21.0167 4.57353 21.4 4.97353C21.8 5.35687 22 5.82354 22 6.37353V18.3735C22 18.9235 21.8 19.3985 21.4 19.7985C21.0167 20.1819 20.55 20.3735 20 20.3735H4ZM12 13.3735L20 8.37354V6.37353L12 11.3735L4 6.37353V8.37354L12 13.3735Z" fill="#35332F"/>
						</svg>
					</a>
					<a href="https://thedot.com" target="_blank" rel="noopener noreferrer" class="msh-website-link">
						<span class="msh-website-text"><?php esc_html_e( 'visit our website', 'msh-image-optimizer' ); ?></span>
						<svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-website-icon">
							<path d="M10 20.3735C8.63333 20.3735 7.34167 20.111 6.125 19.586C4.90833 19.061 3.84583 18.3444 2.9375 17.436C2.02917 16.5277 1.3125 15.4652 0.7875 14.2485C0.2625 13.0319 0 11.7402 0 10.3735C0 8.9902 0.2625 7.69437 0.7875 6.48604C1.3125 5.2777 2.02917 4.21937 2.9375 3.31104C3.84583 2.4027 4.90833 1.68604 6.125 1.16104C7.34167 0.636035 8.63333 0.373535 10 0.373535C11.3833 0.373535 12.6792 0.636035 13.8875 1.16104C15.0958 1.68604 16.1542 2.4027 17.0625 3.31104C17.9708 4.21937 18.6875 5.2777 19.2125 6.48604C19.7375 7.69437 20 8.9902 20 10.3735C20 11.7402 19.7375 13.0319 19.2125 14.2485C18.6875 15.4652 17.9708 16.5277 17.0625 17.436C16.1542 18.3444 15.0958 19.061 13.8875 19.586C12.6792 20.111 11.3833 20.3735 10 20.3735ZM10 18.3235C10.4333 17.7235 10.8083 17.0985 11.125 16.4485C11.4417 15.7985 11.7 15.1069 11.9 14.3735H8.1C8.3 15.1069 8.55833 15.7985 8.875 16.4485C9.19167 17.0985 9.56667 17.7235 10 18.3235ZM7.4 17.9235C7.1 17.3735 6.8375 16.8027 6.6125 16.211C6.3875 15.6194 6.2 15.0069 6.05 14.3735H3.1C3.58333 15.2069 4.1875 15.9319 4.9125 16.5485C5.6375 17.1652 6.46667 17.6235 7.4 17.9235ZM12.6 17.9235C13.5333 17.6235 14.3625 17.1652 15.0875 16.5485C15.8125 15.9319 16.4167 15.2069 16.9 14.3735H13.95C13.8 15.0069 13.6125 15.6194 13.3875 16.211C13.1625 16.8027 12.9 17.3735 12.6 17.9235ZM2.25 12.3735H5.65C5.6 12.0402 5.5625 11.711 5.5375 11.386C5.5125 11.061 5.5 10.7235 5.5 10.3735C5.5 10.0235 5.5125 9.68604 5.5375 9.36104C5.5625 9.03604 5.6 8.70687 5.65 8.37354H2.25C2.16667 8.70687 2.10417 9.03604 2.0625 9.36104C2.02083 9.68604 2 10.0235 2 10.3735C2 10.7235 2.02083 11.061 2.0625 11.386C2.10417 11.711 2.16667 12.0402 2.25 12.3735ZM7.65 12.3735H12.35C12.4 12.0402 12.4375 11.711 12.4625 11.386C12.4875 11.061 12.5 10.7235 12.5 10.3735C12.5 10.0235 12.4875 9.68604 12.4625 9.36104C12.4375 9.03604 12.4 8.70687 12.35 8.37354H7.65C7.6 8.70687 7.5625 9.03604 7.5375 9.36104C7.5125 9.68604 7.5 10.0235 7.5 10.3735C7.5 10.7235 7.5125 11.061 7.5375 11.386C7.5625 11.711 7.6 12.0402 7.65 12.3735ZM14.35 12.3735H17.75C17.8333 12.0402 17.8958 11.711 17.9375 11.386C17.9792 11.061 18 10.7235 18 10.3735C18 10.0235 17.9792 9.68604 17.9375 9.36104C17.8958 9.03604 17.8333 8.70687 17.75 8.37354H14.35C14.4 8.70687 14.4375 9.03604 14.4625 9.36104C14.4875 9.68604 14.5 10.0235 14.5 10.3735C14.5 10.7235 14.4875 11.061 14.4625 11.386C14.4375 11.711 14.4 12.0402 14.35 12.3735ZM13.95 6.37354H16.9C16.4167 5.5402 15.8125 4.8152 15.0875 4.19854C14.3625 3.58187 13.5333 3.12354 12.6 2.82354C12.9 3.37354 13.1625 3.94437 13.3875 4.53604C13.6125 5.1277 13.8 5.7402 13.95 6.37354ZM8.1 6.37354H11.9C11.7 5.6402 11.4417 4.94854 11.125 4.29854C10.8083 3.64854 10.4333 3.02354 10 2.42354C9.56667 3.02354 9.19167 3.64854 8.875 4.29854C8.55833 4.94854 8.3 5.6402 8.1 6.37354ZM3.1 6.37354H6.05C6.2 5.7402 6.3875 5.1277 6.6125 4.53604C6.8375 3.94437 7.1 3.37354 7.4 2.82354C6.46667 3.12354 5.6375 3.58187 4.9125 4.19854C4.1875 4.8152 3.58333 5.5402 3.1 6.37354Z" fill="#35332F"/>
						</svg>
					</a>
				</div>
			</div>

			<!-- Page Title -->
			<div class="msh-dashboard-header">
				<h1 class="msh-dashboard-title"><?php esc_html_e( 'Dashboard', 'msh-image-optimizer' ); ?></h1>
				<p class="msh-dashboard-subtitle"><?php esc_html_e( 'Quick overview and insights', 'msh-image-optimizer' ); ?></p>
			</div>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper msh-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=overview' ) ); ?>"
				   class="nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Overview', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=balance' ) ); ?>"
				   class="nav-tab <?php echo 'balance' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Balance', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=tips' ) ); ?>"
				   class="nav-tab <?php echo 'tips' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Tips', 'msh-image-optimizer' ); ?>
				</a>
			</nav>

			<div class="msh-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'balance':
						self::render_balance_tab( $stats );
						break;
					case 'tips':
						self::render_tips_tab();
						break;
					case 'overview':
					default:
						self::render_overview_tab( $stats );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Overview Tab (Basic Mode)
	 * Big numbers, status, one CTA
	 *
	 * @param array $stats Dashboard statistics.
	 * @return void
	 */
	private static function render_overview_tab( $stats ) {
		$optimized_this_week = self::get_optimized_this_week();
		$ai_vs_manual = self::get_ai_vs_manual_ratio();
		$avg_time = self::get_average_time_per_image();
		$status_message = self::get_status_message( $stats );
		?>
		<div class="msh-tab-overview">
			<!-- Stats Grid -->
			<div class="msh-stats-grid">
				<div class="msh-stat-card msh-stat-primary">
					<div class="msh-stat-value"><?php echo esc_html( number_format_i18n( $optimized_this_week ) ); ?></div>
					<div class="msh-stat-label"><?php esc_html_e( 'Images optimized this week', 'msh-image-optimizer' ); ?></div>
				</div>

				<div class="msh-stat-card">
					<div class="msh-stat-ratio">
						<div class="msh-ratio-bar">
							<div class="msh-ratio-fill msh-ratio-ai" style="width: <?php echo esc_attr( $ai_vs_manual['ai_percent'] ); ?>%;"></div>
							<div class="msh-ratio-fill msh-ratio-manual" style="width: <?php echo esc_attr( $ai_vs_manual['manual_percent'] ); ?>%;"></div>
						</div>
						<div class="msh-ratio-labels">
							<span><?php echo esc_html( $ai_vs_manual['ai_percent'] ); ?>% <?php esc_html_e( 'AI', 'msh-image-optimizer' ); ?></span>
							<span><?php echo esc_html( $ai_vs_manual['manual_percent'] ); ?>% <?php esc_html_e( 'Manual', 'msh-image-optimizer' ); ?></span>
						</div>
					</div>
					<div class="msh-stat-label"><?php esc_html_e( 'AI vs Manual ratio', 'msh-image-optimizer' ); ?></div>
				</div>

				<div class="msh-stat-card">
					<div class="msh-stat-value msh-stat-value-small"><?php echo esc_html( $avg_time ); ?>s</div>
					<div class="msh-stat-label"><?php esc_html_e( 'Average time per image', 'msh-image-optimizer' ); ?></div>
				</div>
			</div>

			<!-- Status Bar -->
			<div class="msh-status-bar msh-status-ok">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php echo esc_html( $status_message ); ?>
			</div>

			<!-- CTA -->
			<div class="msh-cta-section">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer' ) ); ?>" class="button button-dot-primary button-hero">
					<?php esc_html_e( 'Optimize Now', 'msh-image-optimizer' ); ?>
				</a>
			</div>

			<!-- Footer Info -->
			<div class="msh-footer-info">
				<?php
				$last_sync = get_option( 'msh_last_sync_time' );
				$queue_count = $stats['queue_pending'];
				$sync_text = $last_sync ? human_time_diff( $last_sync, current_time( 'timestamp' ) ) : __( 'Never', 'msh-image-optimizer' );
				?>
				<?php
				/* translators: %s: time since last sync */
				printf( esc_html__( 'Last sync: %s ago', 'msh-image-optimizer' ), esc_html( $sync_text ) );
				?>
				 •
				<?php
				if ( 0 === $queue_count ) {
					esc_html_e( 'Queue empty', 'msh-image-optimizer' );
				} else {
					/* translators: %d: number of items in queue */
					printf( esc_html__( '%d items in queue', 'msh-image-optimizer' ), (int) $queue_count );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Balance Tab (Basic Mode)
	 * Credits remaining, estimated images
	 *
	 * @param array $stats Dashboard statistics.
	 * @return void
	 */
	private static function render_balance_tab( $stats ) {
		$credits = self::get_credits_balance();
		$estimated_images = self::estimate_remaining_images( $credits );
		$usage_percent = self::get_usage_percent( $credits );
		$next_renewal = self::get_next_renewal_date();
		// Check actual license status (don't use dev mode for UI display)
		$is_pro = false;
		if ( class_exists( 'MSH_License_Manager' ) ) {
			$is_pro = MSH_License_Manager::get_instance()->is_pro_active();
		}
		?>
		<div class="msh-tab-balance">
			<div class="msh-balance-cards">
				<!-- Credits Card -->
				<div class="msh-balance-card msh-balance-primary">
					<div class="msh-balance-icon">
						<span class="dashicons dashicons-awards"></span>
					</div>
					<div class="msh-balance-content">
						<div class="msh-balance-value"><?php echo esc_html( number_format_i18n( $credits ) ); ?></div>
						<div class="msh-balance-label"><?php esc_html_e( 'Remaining balance', 'msh-image-optimizer' ); ?></div>
					</div>
				</div>

				<!-- Estimated Images Card -->
				<div class="msh-balance-card">
					<div class="msh-balance-icon">
						<span class="dashicons dashicons-images-alt2"></span>
					</div>
					<div class="msh-balance-content">
						<div class="msh-balance-value">≈ <?php echo esc_html( number_format_i18n( $estimated_images ) ); ?></div>
						<div class="msh-balance-label"><?php esc_html_e( 'Estimated images remaining', 'msh-image-optimizer' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Usage Gauge -->
			<div class="msh-usage-section">
				<h3><?php esc_html_e( 'Usage This Cycle', 'msh-image-optimizer' ); ?></h3>
				<div class="msh-usage-gauge">
					<div class="msh-usage-bar">
						<div class="msh-usage-fill" style="width: <?php echo esc_attr( min( 100, $usage_percent ) ); ?>%;"></div>
					</div>
					<div class="msh-usage-label"><?php echo esc_html( number_format_i18n( $usage_percent, 1 ) ); ?>%</div>
				</div>
			</div>

			<!-- Renewal Info -->
			<?php if ( $is_pro && $next_renewal ) : ?>
			<div class="msh-renewal-info">
				<span class="dashicons dashicons-calendar-alt"></span>
				<?php
				/* translators: %s: renewal date */
				printf( esc_html__( 'Next cycle: %s', 'msh-image-optimizer' ), esc_html( $next_renewal ) );
				?>
			</div>
			<?php endif; ?>

			<!-- CTA -->
			<?php if ( ! $is_pro ) : ?>
			<div class="msh-balance-cta">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer-settings&section=license' ) ); ?>" class="button button-dot-primary">
					<?php esc_html_e( 'Upgrade Plan', 'msh-image-optimizer' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Tips Tab (Basic Mode)
	 * Tip of the week, friendly micro-learning
	 *
	 * @return void
	 */
	private static function render_tips_tab() {
		$tip = self::get_tip_of_week();
		?>
		<div class="msh-tab-tips">
			<div class="msh-tip-card">
				<div class="msh-tip-header">
					<span class="dashicons dashicons-lightbulb"></span>
					<h3><?php esc_html_e( 'Tip of the Week', 'msh-image-optimizer' ); ?></h3>
				</div>
				<div class="msh-tip-content">
					<p><?php echo esc_html( $tip['text'] ); ?></p>
				</div>
				<?php if ( ! empty( $tip['cta'] ) ) : ?>
				<div class="msh-tip-cta">
					<a href="<?php echo esc_url( $tip['cta']['href'] ); ?>" target="_blank" class="button button-dot-secondary">
						<?php echo esc_html( $tip['cta']['label'] ); ?>
						<span class="dashicons dashicons-external"></span>
					</a>
				</div>
				<?php endif; ?>
			</div>

			<!-- Did You Know Section -->
			<div class="msh-did-you-know">
				<h4><?php esc_html_e( 'Did you know?', 'msh-image-optimizer' ); ?></h4>
				<ul class="msh-tips-list">
					<li><?php esc_html_e( 'Alt text helps search engines understand your images and improves accessibility.', 'msh-image-optimizer' ); ?></li>
					<li><?php esc_html_e( 'Image file names should be descriptive but concise for better SEO.', 'msh-image-optimizer' ); ?></li>
					<li><?php esc_html_e( 'The AI analyzes both the image content and surrounding page context for the best results.', 'msh-image-optimizer' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Advanced Mode Dashboard
	 * Power user view with 6 tabs
	 *
	 * @return void
	 */
	private static function render_advanced_dashboard() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';
		?>
		<div class="wrap msh-dashboard msh-dashboard-advanced">
			<!-- Product Header -->
			<div class="msh-page-header">
				<div class="msh-logo-container">
					<img src="<?php echo esc_url( trailingslashit( MSH_IO_ASSETS_URL ) . 'icons/optimizer-logo.svg' ); ?>"
						alt="<?php esc_attr_e( 'The Dot Image Optimizer', 'msh-image-optimizer' ); ?>"
						class="msh-logo" />
				</div>
				<div class="msh-header-links">
					<a href="mailto:support@thedot.com" class="msh-support-link">
						<span class="msh-support-text"><?php esc_html_e( 'reach out for support', 'msh-image-optimizer' ); ?></span>
						<svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-mail-icon">
							<path d="M4 20.3735C3.45 20.3735 2.975 20.1819 2.575 19.7985C2.19167 19.3985 2 18.9235 2 18.3735V6.37353C2 5.82354 2.19167 5.35687 2.575 4.97353C2.975 4.57353 3.45 4.37354 4 4.37354H20C20.55 4.37354 21.0167 4.57353 21.4 4.97353C21.8 5.35687 22 5.82354 22 6.37353V18.3735C22 18.9235 21.8 19.3985 21.4 19.7985C21.0167 20.1819 20.55 20.3735 20 20.3735H4ZM12 13.3735L20 8.37354V6.37353L12 11.3735L4 6.37353V8.37354L12 13.3735Z" fill="#35332F"/>
						</svg>
					</a>
					<a href="https://thedot.com" target="_blank" rel="noopener noreferrer" class="msh-website-link">
						<span class="msh-website-text"><?php esc_html_e( 'visit our website', 'msh-image-optimizer' ); ?></span>
						<svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-website-icon">
							<path d="M10 20.3735C8.63333 20.3735 7.34167 20.111 6.125 19.586C4.90833 19.061 3.84583 18.3444 2.9375 17.436C2.02917 16.5277 1.3125 15.4652 0.7875 14.2485C0.2625 13.0319 0 11.7402 0 10.3735C0 8.9902 0.2625 7.69437 0.7875 6.48604C1.3125 5.2777 2.02917 4.21937 2.9375 3.31104C3.84583 2.4027 4.90833 1.68604 6.125 1.16104C7.34167 0.636035 8.63333 0.373535 10 0.373535C11.3833 0.373535 12.6792 0.636035 13.8875 1.16104C15.0958 1.68604 16.1542 2.4027 17.0625 3.31104C17.9708 4.21937 18.6875 5.2777 19.2125 6.48604C19.7375 7.69437 20 8.9902 20 10.3735C20 11.7402 19.7375 13.0319 19.2125 14.2485C18.6875 15.4652 17.9708 16.5277 17.0625 17.436C16.1542 18.3444 15.0958 19.061 13.8875 19.586C12.6792 20.111 11.3833 20.3735 10 20.3735ZM10 18.3235C10.4333 17.7235 10.8083 17.0985 11.125 16.4485C11.4417 15.7985 11.7 15.1069 11.9 14.3735H8.1C8.3 15.1069 8.55833 15.7985 8.875 16.4485C9.19167 17.0985 9.56667 17.7235 10 18.3235ZM7.4 17.9235C7.1 17.3735 6.8375 16.8027 6.6125 16.211C6.3875 15.6194 6.2 15.0069 6.05 14.3735H3.1C3.58333 15.2069 4.1875 15.9319 4.9125 16.5485C5.6375 17.1652 6.46667 17.6235 7.4 17.9235ZM12.6 17.9235C13.5333 17.6235 14.3625 17.1652 15.0875 16.5485C15.8125 15.9319 16.4167 15.2069 16.9 14.3735H13.95C13.8 15.0069 13.6125 15.6194 13.3875 16.211C13.1625 16.8027 12.9 17.3735 12.6 17.9235ZM2.25 12.3735H5.65C5.6 12.0402 5.5625 11.711 5.5375 11.386C5.5125 11.061 5.5 10.7235 5.5 10.3735C5.5 10.0235 5.5125 9.68604 5.5375 9.36104C5.5625 9.03604 5.6 8.70687 5.65 8.37354H2.25C2.16667 8.70687 2.10417 9.03604 2.0625 9.36104C2.02083 9.68604 2 10.0235 2 10.3735C2 10.7235 2.02083 11.061 2.0625 11.386C2.10417 11.711 2.16667 12.0402 2.25 12.3735ZM7.65 12.3735H12.35C12.4 12.0402 12.4375 11.711 12.4625 11.386C12.4875 11.061 12.5 10.7235 12.5 10.3735C12.5 10.0235 12.4875 9.68604 12.4625 9.36104C12.4375 9.03604 12.4 8.70687 12.35 8.37354H7.65C7.6 8.70687 7.5625 9.03604 7.5375 9.36104C7.5125 9.68604 7.5 10.0235 7.5 10.3735C7.5 10.7235 7.5125 11.061 7.5375 11.386C7.5625 11.711 7.6 12.0402 7.65 12.3735ZM14.35 12.3735H17.75C17.8333 12.0402 17.8958 11.711 17.9375 11.386C17.9792 11.061 18 10.7235 18 10.3735C18 10.0235 17.9792 9.68604 17.9375 9.36104C17.8958 9.03604 17.8333 8.70687 17.75 8.37354H14.35C14.4 8.70687 14.4375 9.03604 14.4625 9.36104C14.4875 9.68604 14.5 10.0235 14.5 10.3735C14.5 10.7235 14.4875 11.061 14.4625 11.386C14.4375 11.711 14.4 12.0402 14.35 12.3735ZM13.95 6.37354H16.9C16.4167 5.5402 15.8125 4.8152 15.0875 4.19854C14.3625 3.58187 13.5333 3.12354 12.6 2.82354C12.9 3.37354 13.1625 3.94437 13.3875 4.53604C13.6125 5.1277 13.8 5.7402 13.95 6.37354ZM8.1 6.37354H11.9C11.7 5.6402 11.4417 4.94854 11.125 4.29854C10.8083 3.64854 10.4333 3.02354 10 2.42354C9.56667 3.02354 9.19167 3.64854 8.875 4.29854C8.55833 4.94854 8.3 5.6402 8.1 6.37354ZM3.1 6.37354H6.05C6.2 5.7402 6.3875 5.1277 6.6125 4.53604C6.8375 3.94437 7.1 3.37354 7.4 2.82354C6.46667 3.12354 5.6375 3.58187 4.9125 4.19854C4.1875 4.8152 3.58333 5.5402 3.1 6.37354Z" fill="#35332F"/>
						</svg>
					</a>
				</div>
			</div>

			<!-- Page Title -->
			<div class="msh-dashboard-header">
				<h1 class="msh-dashboard-title"><?php esc_html_e( 'Dashboard', 'msh-image-optimizer' ); ?></h1>
				<p class="msh-dashboard-subtitle"><?php esc_html_e( 'Advanced mode - full control and insights', 'msh-image-optimizer' ); ?></p>
			</div>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper msh-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=overview' ) ); ?>"
				   class="nav-tab <?php echo 'overview' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Overview', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=queue' ) ); ?>"
				   class="nav-tab <?php echo 'queue' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Queue & Jobs', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=events' ) ); ?>"
				   class="nav-tab <?php echo 'events' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Events & Logs', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=insights' ) ); ?>"
				   class="nav-tab <?php echo 'insights' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Insights', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=localization' ) ); ?>"
				   class="nav-tab <?php echo 'localization' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Localization', 'msh-image-optimizer' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-optimizer&tab=history' ) ); ?>"
				   class="nav-tab <?php echo 'history' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'History', 'msh-image-optimizer' ); ?>
				</a>
			</nav>

			<div class="msh-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'queue':
						self::render_advanced_queue_tab();
						break;
					case 'events':
						self::render_advanced_events_tab();
						break;
					case 'insights':
						self::render_advanced_insights_tab();
						break;
					case 'localization':
						self::render_advanced_localization_tab();
						break;
					case 'history':
						self::render_advanced_history_tab();
						break;
					case 'overview':
					default:
						self::render_advanced_overview_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Advanced Overview Tab
	 * KPIs, trends, health checks
	 *
	 * @return void
	 */
	private static function render_advanced_overview_tab() {
		?>
		<div class="msh-tab-advanced-overview">
			<p class="msh-placeholder-text">
				<?php esc_html_e( 'Advanced Overview: KPIs, trends, and health checks coming soon.', 'msh-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Advanced Queue Tab
	 * Live queue management
	 *
	 * @return void
	 */
	private static function render_advanced_queue_tab() {
		?>
		<div class="msh-tab-advanced-queue">
			<p class="msh-placeholder-text">
				<?php esc_html_e( 'Queue & Jobs: Live queue management coming soon.', 'msh-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Advanced Events Tab
	 * Audit trail
	 *
	 * @return void
	 */
	private static function render_advanced_events_tab() {
		?>
		<div class="msh-tab-advanced-events">
			<p class="msh-placeholder-text">
				<?php esc_html_e( 'Events & Logs: Audit trail coming soon.', 'msh-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Advanced Insights Tab
	 * Analytics and opportunities
	 *
	 * @return void
	 */
	private static function render_advanced_insights_tab() {
		?>
		<div class="msh-tab-advanced-insights">
			<p class="msh-placeholder-text">
				<?php esc_html_e( 'Insights: Analytics and opportunities coming soon.', 'msh-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Advanced Localization Tab
	 * Profiles + Glossary
	 *
	 * @return void
	 */
	private static function render_advanced_localization_tab() {
		?>
		<div class="msh-tab-advanced-localization">
			<p class="msh-placeholder-text">
				<?php esc_html_e( 'Localization: Profiles and Glossary management coming soon.', 'msh-image-optimizer' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render Advanced History Tab
	 * Versions and audit trail
	 *
	 * @return void
	 */
	private static function render_advanced_history_tab() {
		?>
		<div class="msh-tab-advanced-history">
			<p class="msh-placeholder-text">
				<?php esc_html_e( 'History: Version management and audit trail coming soon.', 'msh-image-optimizer' ); ?>
			</p>
		</div>
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

	/**
	 * Get optimized images this week
	 *
	 * @return int Count of optimized images
	 */
	private static function get_optimized_this_week() {
		global $wpdb;

		$week_start = strtotime( 'monday this week', current_time( 'timestamp' ) );

		if ( ! class_exists( 'MSH_Metadata_Database' ) ) {
			return 0;
		}

		$cache_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$cache_table}'" ) !== $cache_table ) {
			return 0;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT attachment_id) FROM {$cache_table} WHERE generated_at >= %s",
				gmdate( 'Y-m-d H:i:s', $week_start )
			)
		);

		return $count;
	}

	/**
	 * Get AI vs Manual ratio
	 *
	 * @return array Array with ai_percent and manual_percent
	 */
	private static function get_ai_vs_manual_ratio() {
		global $wpdb;

		if ( ! class_exists( 'MSH_Metadata_Database' ) ) {
			return array( 'ai_percent' => 0, 'manual_percent' => 0 );
		}

		$cache_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$cache_table}'" ) !== $cache_table ) {
			return array( 'ai_percent' => 0, 'manual_percent' => 0 );
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cache_table}" );
		if ( 0 === $total ) {
			return array( 'ai_percent' => 0, 'manual_percent' => 0 );
		}

		$ai_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$cache_table} WHERE chosen_source = 'ai'"
		);

		$ai_percent = round( ( $ai_count / $total ) * 100 );
		$manual_percent = 100 - $ai_percent;

		return array(
			'ai_percent'     => $ai_percent,
			'manual_percent' => $manual_percent,
		);
	}

	/**
	 * Get average time per image
	 *
	 * @return string Average time in seconds
	 */
	private static function get_average_time_per_image() {
		// Placeholder - will be calculated from job execution times
		return '1.2';
	}

	/**
	 * Get status message
	 *
	 * @param array $stats Dashboard stats.
	 * @return string Status message
	 */
	private static function get_status_message( $stats ) {
		if ( $stats['queue_pending'] > 100 ) {
			return __( 'Queue is processing...', 'msh-image-optimizer' );
		}

		return __( "Everything's running smoothly", 'msh-image-optimizer' );
	}

	/**
	 * Get credits balance
	 *
	 * @return int Credits remaining
	 */
	private static function get_credits_balance() {
		// Placeholder - will be retrieved from license/telemetry system
		return 1240;
	}

	/**
	 * Estimate remaining images
	 *
	 * @param int $credits Available credits.
	 * @return int Estimated image count
	 */
	private static function estimate_remaining_images( $credits ) {
		// Average cost per image (placeholder)
		$avg_cost_per_image = 2;

		return (int) floor( $credits / $avg_cost_per_image );
	}

	/**
	 * Get usage percent
	 *
	 * @param int $credits_remaining Remaining credits.
	 * @return float Usage percentage
	 */
	private static function get_usage_percent( $credits_remaining ) {
		// Placeholder - will calculate from cycle start credits
		$cycle_start_credits = 2000;
		$used = $cycle_start_credits - $credits_remaining;

		return round( ( $used / $cycle_start_credits ) * 100, 1 );
	}

	/**
	 * Get next renewal date
	 *
	 * @return string|null Renewal date or null
	 */
	private static function get_next_renewal_date() {
		// Placeholder - will be retrieved from license system
		return 'Nov 15, 2025';
	}

	/**
	 * Get tip of the week
	 *
	 * @return array Tip data
	 */
	private static function get_tip_of_week() {
		$week_key = wp_date( 'o-\WW' ); // ISO week
		$cache = get_option( 'msh_tip_of_week' );

		if ( $cache && isset( $cache['week'] ) && $cache['week'] === $week_key && ! empty( $cache['tip'] ) ) {
			return $cache['tip'];
		}

		// Load tips library
		$tips = self::load_tips_library();

		// Simple selection for now - will implement full algorithm later
		$site_seed = md5( home_url() . $week_key );
		$idx = hexdec( substr( $site_seed, 0, 4 ) ) % count( $tips );
		$chosen = $tips[ $idx ];

		update_option(
			'msh_tip_of_week',
			array(
				'week' => $week_key,
				'tip'  => $chosen,
			),
			false
		);

		return $chosen;
	}

	/**
	 * Load tips library
	 *
	 * @return array Tips array
	 */
	private static function load_tips_library() {
		$locale = determine_locale();
		$tips_file = MSH_IO_PLUGIN_DIR . 'assets/tips/tips.' . $locale . '.json';

		// Fallback to en_US if locale file doesn't exist
		if ( ! file_exists( $tips_file ) ) {
			$tips_file = MSH_IO_PLUGIN_DIR . 'assets/tips/tips.en_US.json';
		}

		if ( file_exists( $tips_file ) ) {
			$json = file_get_contents( $tips_file );
			$tips = json_decode( $json, true );
			if ( is_array( $tips ) ) {
				return $tips;
			}
		}

		// Fallback tips if file doesn't exist yet
		return array(
			array(
				'id'   => 'alt_length_best_practice',
				'text' => __( 'Alt text works best under 125 characters. Keep it natural and specific.', 'msh-image-optimizer' ),
				'cta'  => array(
					'label' => __( 'Learn more', 'msh-image-optimizer' ),
					'href'  => 'https://docs.thedot.io/tips/alt-length',
				),
			),
			array(
				'id'   => 'file_names_seo',
				'text' => __( 'Descriptive file names help SEO. Rename images before uploading when possible.', 'msh-image-optimizer' ),
				'cta'  => array(
					'label' => __( 'See examples', 'msh-image-optimizer' ),
					'href'  => 'https://docs.thedot.io/tips/file-names',
				),
			),
			array(
				'id'   => 'context_matters',
				'text' => __( 'The AI analyzes surrounding page context. Images used on multiple pages may get different metadata.', 'msh-image-optimizer' ),
				'cta'  => null,
			),
		);
	}
}

// Initialize
if ( is_admin() ) {
	new MSH_Dashboard_Page();
}
