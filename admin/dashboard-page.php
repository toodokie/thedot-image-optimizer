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
			<h1 class="msh-dashboard-title"><?php esc_html_e( 'The Dot Optimizer', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-dashboard-subtitle"><?php esc_html_e( 'Dashboard', 'msh-image-optimizer' ); ?></p>

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
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer' ) ); ?>" class="button button-primary button-hero">
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
		$is_pro = defined( 'MSH_DEV_MODE' ) && MSH_DEV_MODE;
		if ( ! $is_pro && class_exists( 'MSH_License_Manager' ) ) {
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
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer-settings&section=license' ) ); ?>" class="button button-primary">
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
					<a href="<?php echo esc_url( $tip['cta']['href'] ); ?>" target="_blank" class="button button-secondary">
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
			<h1 class="msh-dashboard-title"><?php esc_html_e( 'The Dot Optimizer', 'msh-image-optimizer' ); ?></h1>
			<p class="msh-dashboard-subtitle"><?php esc_html_e( 'Advanced Dashboard', 'msh-image-optimizer' ); ?></p>

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
