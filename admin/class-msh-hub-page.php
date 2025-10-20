<?php
/**
 * Optimizer Hub main admin page controller.
 *
 * Responsible for registering the Optimizer Hub submenu, enqueueing assets,
 * and rendering the tabbed interface skeleton used by the frontend team.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class MSH_Hub_Page
 *
 * Singleton that powers the Optimizer Hub admin experience.
 */
class MSH_Hub_Page {

	/**
	 * Singleton instance holder.
	 *
	 * @var MSH_Hub_Page|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return MSH_Hub_Page
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Lock constructor to enforce singleton usage.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 35 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Optimizer Hub submenu.
	 *
	 * Menu path: The Dot → Glossary → Optimizer Hub.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'msh-optimizer',
			__( 'Optimizer Hub', 'msh-image-optimizer' ),
			'<span class="dashicons dashicons-database-view"></span> ' . __( 'Optimizer Hub', 'msh-image-optimizer' ),
			'manage_options',
			'msh-hub',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue styles and scripts for the Hub interface.
	 *
	 * @param string $hook Current admin hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'the-dot_page_msh-hub' !== $hook ) {
			return;
		}

		$assets_base = defined( 'MSH_IO_ASSETS_URL' )
			? trailingslashit( MSH_IO_ASSETS_URL )
			: trailingslashit( plugin_dir_url( __FILE__ ) . '../assets' );

		wp_enqueue_style(
			'msh-hub-css',
			$assets_base . 'css/hub.css',
			array(),
			'2.0.0'
		);

		$script_path = $assets_base . 'js/hub.js';

		wp_enqueue_script(
			'msh-hub-js',
			$script_path,
			array( 'jquery' ),
			'2.0.0',
			true
		);

		$job_stats = function_exists( 'msh_get_job_stats' ) ? msh_get_job_stats() : array();
		$is_pro    = function_exists( 'msh_is_pro_active' ) ? msh_is_pro_active() : false;

		wp_localize_script(
			'msh-hub-js',
			'mshHubData',
			array(
				'apiUrl'    => esc_url_raw( rest_url( 'msh/v1' ) ),
				'apiNonce'  => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'msh_hub_nonce' ),
				'isPro'     => (bool) $is_pro,
				'stats'     => $job_stats,
				'i18n'      => array(
					'queueJobsWaitingPlural'   => esc_html__( '%s jobs waiting for processing.', 'msh-image-optimizer' ),
					'queueJobsWaitingSingular' => esc_html__( '%s job waiting for processing.', 'msh-image-optimizer' ),
					'queueNoJobs'              => esc_html__( 'No jobs waiting in the queue.', 'msh-image-optimizer' ),
					'queueProcessing'          => esc_html__( 'Processing...', 'msh-image-optimizer' ),
					'queueProcessingComplete'  => esc_html__( 'Processing Complete', 'msh-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * Render the Optimizer Hub shell with tab navigation.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msh-image-optimizer' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'cache'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs        = $this->get_tabs();

		if ( ! array_key_exists( $current_tab, $tabs ) ) {
			$current_tab = 'cache';
		}
		?>
		<div class="wrap msh-hub-page">
			<h1><?php esc_html_e( 'Optimizer Hub', 'msh-image-optimizer' ); ?></h1>
			<?php $this->render_nav_tabs( $current_tab, $tabs ); ?>
			<div class="msh-tab-content">
				<?php $this->render_tab_content( $current_tab ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build associative array of tab slugs and labels.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs() {
		$tabs = array(
			'cache'   => __( 'Cache', 'msh-image-optimizer' ),
			'history' => __( 'History', 'msh-image-optimizer' ),
			'queue'   => __( 'Queue', 'msh-image-optimizer' ),
			'events'  => __( 'Events', 'msh-image-optimizer' ),
			'sync'    => __( 'Sync', 'msh-image-optimizer' ),
		);

		if ( function_exists( 'msh_is_pro_active' ) && ! msh_is_pro_active() ) {
			$tabs['sync'] .= ' 🔒';
		}

		return $tabs;
	}

	/**
	 * Output nav-tab markup.
	 *
	 * @param string $current_tab Current active tab key.
	 * @param array  $tabs        Tab mapping.
	 *
	 * @return void
	 */
	private function render_nav_tabs( $current_tab, $tabs ) {
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $tab_key => $label ) {
			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( add_query_arg( array( 'page' => 'msh-hub', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ),
				$current_tab === $tab_key ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';
	}

	/**
	 * Display placeholder content for each tab.
	 *
	 * @param string $tab Active tab slug.
	 *
	 * @return void
	 */
	private function render_tab_content( $tab ) {
		switch ( $tab ) {
			case 'history':
				echo '<p>' . esc_html__( 'History tab - Coming soon...', 'msh-image-optimizer' ) . '</p>';
				break;
			case 'queue':
				$this->render_queue_tab();
				break;
			case 'events':
				echo '<p>' . esc_html__( 'Events tab - Coming soon...', 'msh-image-optimizer' ) . '</p>';
				break;
			case 'sync':
				if ( function_exists( 'msh_is_pro_active' ) && ! msh_is_pro_active() ) {
					echo '<p>' . esc_html__( 'Sync tab - Pro feature coming soon...', 'msh-image-optimizer' ) . '</p>';
				} else {
					echo '<p>' . esc_html__( 'Sync tab - Coming soon...', 'msh-image-optimizer' ) . '</p>';
				}
				break;
			case 'cache':
			default:
				$this->render_cache_tab();
				break;
		}
	}

	/**
	 * Render Cache tab content with filters and results.
	 *
	 * @return void
	 */
	private function render_cache_tab() {
		$locale    = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$staleness = isset( $_GET['staleness'] ) ? sanitize_text_field( wp_unslash( $_GET['staleness'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source    = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $paged < 1 ) {
			$paged = 1;
		}

		$args = array(
			'page'     => $paged,
			'per_page' => 50,
		);

		if ( '' !== $locale ) {
			$args['locale'] = $locale;
		}

		if ( '' !== $staleness ) {
			$args['staleness'] = $staleness;
		}

		if ( '' !== $source ) {
			$args['source'] = $source;
		}

		$results = function_exists( 'msh_get_cache_entries' ) ? msh_get_cache_entries( $args ) : array();

		$items       = isset( $results['items'] ) ? $results['items'] : array();
		$total       = isset( $results['total'] ) ? (int) $results['total'] : 0;
		$total_pages = isset( $results['total_pages'] ) ? max( 1, (int) $results['total_pages'] ) : 1;

		?>
		<div class="msh-cache-tab">
			<div class="msh-cache-intro">
				<p>
					<strong><?php esc_html_e( 'Memo:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'This table shows the stored AI and manual metadata for every attachment. Regenerate queues a fresh analysis using the latest context, glossary, and locale settings.', 'msh-image-optimizer' ); ?>
					<a href="<?php echo esc_url( 'https://github.com/toodokie/thedot-image-optimizer/blob/main/msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md#metadata-cache' ); ?>" class="msh-cache-intro__link" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Read more', 'msh-image-optimizer' ); ?>
					</a>
				</p>
			</div>

			<div class="msh-filters">
				<form method="get" action="" id="msh-cache-filter-form">
					<input type="hidden" name="page" value="msh-hub" />
					<input type="hidden" name="tab" value="cache" />

					<div class="msh-filter-group">
						<label for="filter-locale"><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></label>
						<select name="locale" id="filter-locale">
							<option value=""><?php esc_html_e( 'All Locales', 'msh-image-optimizer' ); ?></option>
							<option value="es_ES" <?php selected( $locale, 'es_ES' ); ?>><?php esc_html_e( 'Spanish (es_ES)', 'msh-image-optimizer' ); ?></option>
							<option value="fr_FR" <?php selected( $locale, 'fr_FR' ); ?>><?php esc_html_e( 'French (fr_FR)', 'msh-image-optimizer' ); ?></option>
							<option value="de_DE" <?php selected( $locale, 'de_DE' ); ?>><?php esc_html_e( 'German (de_DE)', 'msh-image-optimizer' ); ?></option>
						</select>
					</div>

					<div class="msh-filter-group">
						<label for="filter-staleness"><?php esc_html_e( 'Staleness', 'msh-image-optimizer' ); ?></label>
						<select name="staleness" id="filter-staleness">
							<option value=""><?php esc_html_e( 'All', 'msh-image-optimizer' ); ?></option>
							<option value="stale" <?php selected( $staleness, 'stale' ); ?>><?php esc_html_e( 'Stale Only', 'msh-image-optimizer' ); ?></option>
							<option value="fresh" <?php selected( $staleness, 'fresh' ); ?>><?php esc_html_e( 'Fresh Only', 'msh-image-optimizer' ); ?></option>
						</select>
					</div>

					<div class="msh-filter-group">
						<label for="filter-source"><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></label>
						<select name="source" id="filter-source">
							<option value=""><?php esc_html_e( 'All Sources', 'msh-image-optimizer' ); ?></option>
							<option value="ai" <?php selected( $source, 'ai' ); ?>><?php esc_html_e( 'AI Generated', 'msh-image-optimizer' ); ?></option>
							<option value="manual" <?php selected( $source, 'manual' ); ?>><?php esc_html_e( 'Manual Override', 'msh-image-optimizer' ); ?></option>
						</select>
					</div>

					<a href="#" class="msh-clear-filters<?php echo ( '' === $locale && '' === $staleness && '' === $source ) ? ' is-disabled' : ''; ?>" id="msh-clear-filters"><?php esc_html_e( 'Clear filters', 'msh-image-optimizer' ); ?></a>
				</form>
			</div>

			<div id="msh-loading-spinner" style="display: none; text-align: center; padding: 20px;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<p><?php esc_html_e( 'Loading cache entries...', 'msh-image-optimizer' ); ?></p>
			</div>

			<p class="msh-results-count">
				<?php
				printf(
					/* translators: %d: total number of cache entries */
					esc_html__( 'Showing %d cache entries', 'msh-image-optimizer' ),
					$total
				);
				?>
			</p>

			<?php if ( ! empty( $items ) ) : ?>
				<table class="wp-list-table widefat fixed striped msh-cache-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Attachment ID', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Field', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Value', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Staleness', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody id="msh-cache-table-body">
						<?php foreach ( $items as $entry ) : ?>
							<tr>
								<td><?php echo (int) $entry->attachment_id; ?></td>
								<td><code><?php echo esc_html( $entry->locale ); ?></code></td>
								<td><?php echo esc_html( $entry->field ); ?></td>
								<td>
									<?php
									$value = 'manual' === $entry->chosen_source && ! empty( $entry->manual_value )
										? $entry->manual_value
										: $entry->ai_value;
									echo esc_html( wp_trim_words( (string) $value, 10 ) );
									?>
								</td>
								<td>
									<span class="msh-badge msh-badge-<?php echo esc_attr( $entry->chosen_source ); ?>">
										<?php echo esc_html( ucfirst( $entry->chosen_source ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $entry->stale_reason ) ) : ?>
										<span class="msh-badge msh-badge-stale" title="<?php echo esc_attr( $entry->stale_reason ); ?>">
											<?php esc_html_e( 'Stale', 'msh-image-optimizer' ); ?>
										</span>
									<?php else : ?>
										<span class="msh-badge msh-badge-fresh">
											<?php esc_html_e( 'Fresh', 'msh-image-optimizer' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $entry->updated_at ); ?></td>
								<td>
									<button
										type="button"
										class="button button-small msh-regenerate-btn"
										data-attachment-id="<?php echo (int) $entry->attachment_id; ?>"
										data-locale="<?php echo esc_attr( $entry->locale ); ?>"
										data-field="<?php echo esc_attr( $entry->field ); ?>"
									>
										<?php esc_html_e( 'Regenerate', 'msh-image-optimizer' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages" id="msh-cache-pagination">
							<?php
							$base_args = array(
								'page'      => 'msh-hub',
								'tab'       => 'cache',
							);

							if ( '' !== $locale ) {
								$base_args['locale'] = $locale;
							}
							if ( '' !== $staleness ) {
								$base_args['staleness'] = $staleness;
							}
							if ( '' !== $source ) {
								$base_args['source'] = $source;
							}

							$base_url = add_query_arg( $base_args, admin_url( 'admin.php' ) );

							for ( $i = 1; $i <= $total_pages; $i++ ) {
								$current = ( $i === $paged ) ? ' current' : '';
								printf(
									'<button type="button" class="button%s msh-page-btn" data-page="%d">%d</button> ',
									esc_attr( $current ),
									(int) $i,
									(int) $i
								);
							}

							printf(
								'<noscript><br /><span class="description">%s</span></noscript>',
								esc_html__( 'Enable JavaScript to paginate without reloading.', 'msh-image-optimizer' )
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'No cache entries found.', 'msh-image-optimizer' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Queue tab content.
	 *
	 * @return void
	 */
	private function render_queue_tab() {
		$stats = function_exists( 'msh_get_job_stats' ) ? msh_get_job_stats() : array();

		$pending         = isset( $stats['pending'] ) ? (int) $stats['pending'] : 0;
		$processing      = isset( $stats['processing'] ) ? (int) $stats['processing'] : 0;
		$complete        = isset( $stats['complete'] ) ? (int) $stats['complete'] : 0;
		$failed          = isset( $stats['failed'] ) ? (int) $stats['failed'] : 0;
		$priority_high   = isset( $stats['priority_high'] ) ? (int) $stats['priority_high'] : ( isset( $stats['high_priority'] ) ? (int) $stats['high_priority'] : 0 );
		$priority_medium = isset( $stats['priority_medium'] ) ? (int) $stats['priority_medium'] : ( isset( $stats['medium_priority'] ) ? (int) $stats['medium_priority'] : 0 );
		$priority_normal = isset( $stats['priority_normal'] ) ? (int) $stats['priority_normal'] : ( isset( $stats['normal_priority'] ) ? (int) $stats['normal_priority'] : 0 );

		$total_in_queue  = max( 0, $pending + $processing );
		$high_percent    = $total_in_queue ? min( 100, ( $priority_high / $total_in_queue ) * 100 ) : 0;
		$medium_percent  = $total_in_queue ? min( 100, ( $priority_medium / $total_in_queue ) * 100 ) : 0;
		$normal_percent  = $total_in_queue ? min( 100, ( $priority_normal / $total_in_queue ) * 100 ) : 0;
		$has_failed_jobs = $failed > 0;
		?>
		<div class="msh-queue-tab">
			<div class="msh-queue-intro">
				<p>
					<strong><?php esc_html_e( 'Memo:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Monitor the background job queue here. Pending counts include work waiting to start plus jobs that are currently processing. Use Process Now sparingly when you need to force-run the queue immediately. Review failed jobs before clearing them.', 'msh-image-optimizer' ); ?>
					<a href="<?php echo esc_url( 'https://github.com/toodokie/thedot-image-optimizer/blob/main/msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md#queue-monitoring' ); ?>" class="msh-cache-intro__link" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Read more', 'msh-image-optimizer' ); ?>
					</a>
				</p>
			</div>

			<div class="msh-queue-stats">
				<div class="msh-stat-card msh-stat-pending">
					<div class="msh-stat-icon">⏳</div>
					<div class="msh-stat-value" id="msh-stat-pending"><?php echo esc_html( number_format_i18n( $pending ) ); ?></div>
					<div class="msh-stat-label"><?php esc_html_e( 'Pending', 'msh-image-optimizer' ); ?></div>
				</div>
				<div class="msh-stat-card msh-stat-processing">
					<div class="msh-stat-icon">⚙️</div>
					<div class="msh-stat-value" id="msh-stat-processing"><?php echo esc_html( number_format_i18n( $processing ) ); ?></div>
					<div class="msh-stat-label"><?php esc_html_e( 'Processing', 'msh-image-optimizer' ); ?></div>
				</div>
				<div class="msh-stat-card msh-stat-complete">
					<div class="msh-stat-icon">✓</div>
					<div class="msh-stat-value" id="msh-stat-complete"><?php echo esc_html( number_format_i18n( $complete ) ); ?></div>
					<div class="msh-stat-label"><?php esc_html_e( 'Complete (24h)', 'msh-image-optimizer' ); ?></div>
				</div>
				<div class="msh-stat-card msh-stat-failed<?php echo $has_failed_jobs ? ' has-alert' : ''; ?>">
					<div class="msh-stat-icon">✗</div>
					<div class="msh-stat-value" id="msh-stat-failed"><?php echo esc_html( number_format_i18n( $failed ) ); ?></div>
					<div class="msh-stat-label"><?php esc_html_e( 'Failed', 'msh-image-optimizer' ); ?></div>
				</div>
			</div>

			<div class="msh-queue-body">
				<div class="msh-priority-column">
					<h3><?php esc_html_e( 'Priority Breakdown', 'msh-image-optimizer' ); ?></h3>

					<div class="msh-priority-bar">
						<div class="msh-priority-label">
							<span class="msh-priority-badge msh-priority-high"><?php esc_html_e( 'High', 'msh-image-optimizer' ); ?></span>
							<span class="msh-priority-count" id="msh-priority-high-count"><?php echo esc_html( number_format_i18n( $priority_high ) ); ?></span>
						</div>
						<div class="msh-progress-bar">
							<div class="msh-progress-fill msh-progress-high" id="msh-progress-high" style="width: <?php echo esc_attr( $high_percent ); ?>%;"></div>
						</div>
					</div>

					<div class="msh-priority-bar">
						<div class="msh-priority-label">
							<span class="msh-priority-badge msh-priority-medium"><?php esc_html_e( 'Medium', 'msh-image-optimizer' ); ?></span>
							<span class="msh-priority-count" id="msh-priority-medium-count"><?php echo esc_html( number_format_i18n( $priority_medium ) ); ?></span>
						</div>
						<div class="msh-progress-bar">
							<div class="msh-progress-fill msh-progress-medium" id="msh-progress-medium" style="width: <?php echo esc_attr( $medium_percent ); ?>%;"></div>
						</div>
					</div>

					<div class="msh-priority-bar">
						<div class="msh-priority-label">
							<span class="msh-priority-badge msh-priority-normal"><?php esc_html_e( 'Normal', 'msh-image-optimizer' ); ?></span>
							<span class="msh-priority-count" id="msh-priority-normal-count"><?php echo esc_html( number_format_i18n( $priority_normal ) ); ?></span>
						</div>
						<div class="msh-progress-bar">
							<div class="msh-progress-fill msh-progress-normal" id="msh-progress-normal" style="width: <?php echo esc_attr( $normal_percent ); ?>%;"></div>
						</div>
					</div>

					<p class="msh-priority-note" id="msh-priority-note">
						<?php
						echo esc_html(
							$total_in_queue > 0
								? sprintf(
									/* translators: %s: formatted number of jobs. */
									__( '%s jobs waiting for processing.', 'msh-image-optimizer' ),
									number_format_i18n( $total_in_queue )
								)
								: __( 'No jobs waiting in the queue.', 'msh-image-optimizer' )
						);
						?>
					</p>
				</div>

				<div class="msh-actions-column">
					<div class="msh-queue-actions">
						<button type="button" id="msh-process-now" class="msh-queue-process-btn">
							<?php esc_html_e( 'Process Now', 'msh-image-optimizer' ); ?>
						</button>

						<?php if ( $has_failed_jobs ) : ?>
							<button type="button" id="msh-clear-failed" class="button-link">
								<?php esc_html_e( 'Clear Failed Jobs (coming soon)', 'msh-image-optimizer' ); ?>
							</button>
						<?php endif; ?>

						<label for="msh-auto-refresh" class="msh-auto-refresh-toggle">
							<input type="checkbox" id="msh-auto-refresh" checked>
							<?php esc_html_e( 'Auto-refresh (5s)', 'msh-image-optimizer' ); ?>
						</label>
					</div>

					<div class="msh-recent-jobs">
						<h3><?php esc_html_e( 'Recent Jobs', 'msh-image-optimizer' ); ?></h3>
						<div id="msh-recent-jobs-container">
							<p class="msh-placeholder"><?php esc_html_e( 'Recent jobs will appear here once AI #1 wires the data feed.', 'msh-image-optimizer' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for cache entries filtering.
	 *
	 * @return void
	 */
	public function ajax_get_cache_entries() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
				)
			);
		}

		$locale    = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$staleness = isset( $_POST['staleness'] ) ? sanitize_text_field( wp_unslash( $_POST['staleness'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$source    = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$paged     = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $paged < 1 ) {
			$paged = 1;
		}

		$args = array(
			'page'     => $paged,
			'per_page' => 50,
		);

		if ( '' !== $locale ) {
			$args['locale'] = $locale;
		}
		if ( '' !== $staleness ) {
			$args['staleness'] = $staleness;
		}
		if ( '' !== $source ) {
			$args['source'] = $source;
		}

		$results = function_exists( 'msh_get_cache_entries' ) ? msh_get_cache_entries( $args ) : array();

		$items       = isset( $results['items'] ) ? $results['items'] : array();
		$total       = isset( $results['total'] ) ? (int) $results['total'] : 0;
		$total_pages = isset( $results['total_pages'] ) ? max( 1, (int) $results['total_pages'] ) : 1;

		ob_start();
		if ( ! empty( $items ) ) {
			foreach ( $items as $entry ) {
				?>
				<tr>
					<td><?php echo (int) $entry->attachment_id; ?></td>
					<td><code><?php echo esc_html( $entry->locale ); ?></code></td>
					<td><?php echo esc_html( $entry->field ); ?></td>
					<td>
						<?php
						$value = 'manual' === $entry->chosen_source && ! empty( $entry->manual_value )
							? $entry->manual_value
							: $entry->ai_value;
						echo esc_html( wp_trim_words( (string) $value, 10 ) );
						?>
					</td>
					<td>
						<span class="msh-badge msh-badge-<?php echo esc_attr( $entry->chosen_source ); ?>">
							<?php echo esc_html( ucfirst( $entry->chosen_source ) ); ?>
						</span>
					</td>
					<td>
						<?php if ( ! empty( $entry->stale_reason ) ) : ?>
							<span class="msh-badge msh-badge-stale" title="<?php echo esc_attr( $entry->stale_reason ); ?>">
								<?php esc_html_e( 'Stale', 'msh-image-optimizer' ); ?>
							</span>
						<?php else : ?>
							<span class="msh-badge msh-badge-fresh">
								<?php esc_html_e( 'Fresh', 'msh-image-optimizer' ); ?>
							</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $entry->updated_at ); ?></td>
					<td>
						<button
							type="button"
							class="button button-small msh-regenerate-btn"
							data-attachment-id="<?php echo (int) $entry->attachment_id; ?>"
							data-locale="<?php echo esc_attr( $entry->locale ); ?>"
							data-field="<?php echo esc_attr( $entry->field ); ?>"
						>
							<?php esc_html_e( 'Regenerate', 'msh-image-optimizer' ); ?>
						</button>
					</td>
				</tr>
				<?php
			}
		} else {
			?>
			<tr>
				<td colspan="8"><?php esc_html_e( 'No cache entries found.', 'msh-image-optimizer' ); ?></td>
			</tr>
			<?php
		}
		$table_html = ob_get_clean();

		ob_start();
		if ( $total_pages > 1 ) {
			for ( $i = 1; $i <= $total_pages; $i++ ) {
				$current = ( $i === $paged ) ? ' current' : '';
				printf(
					'<button type="button" class="button%s msh-page-btn" data-page="%d">%d</button> ',
					esc_attr( $current ),
					(int) $i,
					(int) $i
				);
			}
		}
		$pagination_html = ob_get_clean();

		wp_send_json_success(
			array(
				'table_html'      => $table_html,
				'pagination_html' => $pagination_html,
				'total'           => $total,
				'current_page'    => $paged,
				'total_pages'     => $total_pages,
			)
		);
	}

	/**
	 * AJAX handler for queueing a cache regeneration.
	 *
	 * @return void
	 */
	public function ajax_regenerate_entry() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
				)
			);
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$locale        = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$field         = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $attachment_id || '' === $field ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Missing required data.', 'msh-image-optimizer' ),
				)
			);
		}

		if ( ! function_exists( 'msh_enqueue_job' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Job handler is unavailable.', 'msh-image-optimizer' ),
				)
			);
		}

		$payload = array(
			'attachment_id' => $attachment_id,
			'locale'        => $locale,
			'field'         => $field,
			'requested_by'  => get_current_user_id(),
		);

		$job_id = msh_enqueue_job(
			'regenerate_metadata',
			'attachment',
			$attachment_id,
			$payload,
			'normal'
		);

		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error(
				array(
					'message' => $job_id->get_error_message(),
				)
			);
		}

		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'hub_cache_regenerate_single',
				array(
					'attachment_id' => $attachment_id,
					'locale'        => $locale,
					'field'         => $field,
					'user_id'       => get_current_user_id(),
				)
			);
		}

		wp_send_json_success(
			array(
				'job_id' => $job_id,
			)
		);
	}

	/**
	 * AJAX handler for refreshing queue statistics.
	 *
	 * @return void
	 */
	public function ajax_refresh_queue_stats() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
				)
			);
		}

		$stats = function_exists( 'msh_get_job_stats' ) ? msh_get_job_stats() : array();

		wp_send_json_success(
			array(
				'stats' => $stats,
			)
		);
	}

	/**
	 * AJAX handler for triggering manual queue processing.
	 *
	 * @return void
	 */
	public function ajax_process_queue() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
				)
			);
		}

		if ( function_exists( 'msh_process_queue' ) ) {
			$result = msh_process_queue();
		} else {
			$result = array(
				'processed' => 0,
				'message'   => esc_html__( 'Queue processing triggered (stub implementation).', 'msh-image-optimizer' ),
			);
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'hub_queue_process_manual',
				array(
					'user_id' => get_current_user_id(),
				)
			);
		}

		wp_send_json_success( $result );
	}
}

MSH_Hub_Page::get_instance();
