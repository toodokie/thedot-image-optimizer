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
		// Menu registration now handled by class-msh-optimizer-menu.php
		// add_action( 'admin_menu', array( $this, 'register_menu' ), 35 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Metadata browsing
		add_action( 'wp_ajax_msh_get_metadata_entries', array( $this, 'ajax_get_metadata_entries' ) );
		add_action( 'wp_ajax_msh_get_cache_entries', array( $this, 'ajax_get_metadata_entries' ) );

		// Metadata row actions (for AI #2's buttons)
		add_action( 'wp_ajax_msh_preview_metadata', array( $this, 'ajax_preview_metadata' ) );
		add_action( 'wp_ajax_msh_copy_metadata', array( $this, 'ajax_copy_metadata' ) );
		add_action( 'wp_ajax_msh_update_metadata', array( $this, 'ajax_update_metadata' ) );
		add_action( 'wp_ajax_msh_toggle_lock', array( $this, 'ajax_toggle_lock' ) );
		add_action( 'wp_ajax_msh_regenerate_entry', array( $this, 'ajax_regenerate_entry' ) );

		// Queue management
		add_action( 'wp_ajax_msh_refresh_queue_stats', array( $this, 'ajax_refresh_queue_stats' ) );
		add_action( 'wp_ajax_msh_process_queue', array( $this, 'ajax_process_queue' ) );

		// Events feed
		add_action( 'wp_ajax_msh_get_recent_events', array( $this, 'ajax_get_recent_events' ) );
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

		$style_file    = dirname( __FILE__ ) . '/../assets/css/hub.css';
		$style_version = file_exists( $style_file ) ? filemtime( $style_file ) : '2.0.0';

		wp_enqueue_style(
			'msh-hub-css',
			$assets_base . 'css/hub.css',
			array(),
			$style_version
		);

		$script_path    = $assets_base . 'js/hub.js';
		$script_file    = dirname( __FILE__ ) . '/../assets/js/hub.js';
		$script_version = file_exists( $script_file ) ? filemtime( $script_file ) : '2.0.0';

		wp_enqueue_script(
			'msh-hub-js',
			$script_path,
			array( 'jquery' ),
			$script_version,
			true
		);

		$job_stats = function_exists( 'msh_get_job_stats' ) ? msh_get_job_stats() : array();
		$is_pro    = function_exists( 'msh_is_pro_active' ) ? msh_is_pro_active() : false;

		wp_localize_script(
			'msh-hub-js',
			'mshHubData',
			array(
				'apiUrl'    => esc_url_raw( rest_url() ),
				'restNamespace' => 'dot-opt/v1',
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
					'eventsLiveFeed'           => esc_html__( 'Live event feed running – updates every 5 seconds.', 'msh-image-optimizer' ),
					'eventsPaused'             => esc_html__( 'Live feed paused.', 'msh-image-optimizer' ),
					'eventsError'              => esc_html__( 'Unable to load recent events. Please try again.', 'msh-image-optimizer' ),
					'eventsPause'              => esc_html__( 'Pause Live Feed', 'msh-image-optimizer' ),
					'eventsResume'             => esc_html__( 'Resume Live Feed', 'msh-image-optimizer' ),
					'eventsNoData'             => esc_html__( 'No recent events yet. The feed will populate as activity occurs.', 'msh-image-optimizer' ),
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
			'metadata' => __( 'Metadata', 'msh-image-optimizer' ),
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
				$this->render_history_tab();
				break;
			case 'queue':
				$this->render_queue_tab();
				break;
			case 'events':
				$this->render_events_tab();
				break;
			case 'sync':
				$this->render_sync_tab();
				break;
			case 'metadata':
			case 'cache':
			default:
				$this->render_metadata_tab();
				break;
		}
	}

	/**
	 * Render Cache tab content with filters and results.
	 *
	 * @return void
	 */
	private function render_metadata_tab() {
		$locale  = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$field   = isset( $_GET['field'] ) ? sanitize_text_field( wp_unslash( $_GET['field'] ) ) : '';
		$source  = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search  = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

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

		if ( '' !== $field ) {
			$args['field'] = $field;
		}

		if ( '' !== $source ) {
			$args['source'] = $source;
		}

		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$results = function_exists( 'msh_get_metadata_entries' ) ? msh_get_metadata_entries( $args ) : array(
			'items'       => array(),
			'total'       => 0,
			'total_pages' => 1,
		);

		$items       = isset( $results['items'] ) ? $results['items'] : array();
		$total       = isset( $results['total'] ) ? (int) $results['total'] : 0;
		$total_pages = isset( $results['total_pages'] ) ? max( 1, (int) $results['total_pages'] ) : 1;

		$status_labels = $this->get_metadata_status_labels();
		$source_labels = array(
			'ai'        => _x( 'AI', 'metadata source', 'msh-image-optimizer' ),
			'manual'    => _x( 'Manual', 'metadata source', 'msh-image-optimizer' ),
			'imported'  => _x( 'Imported', 'metadata source', 'msh-image-optimizer' ),
		);

		?>
		<div class="msh-cache-tab">
			<div class="msh-cache-intro">
				<p>
					<strong><?php esc_html_e( 'Memo:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'This table shows the localized metadata that will ship to production. Use the filters to focus on specific locales, fields, or sources.', 'msh-image-optimizer' ); ?>
					<a href="<?php echo esc_url( 'https://github.com/toodokie/thedot-image-optimizer/blob/main/msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md#metadata' ); ?>" class="msh-cache-intro__link" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Read more', 'msh-image-optimizer' ); ?>
					</a>
				</p>
			</div>

			<div class="msh-filters">
				<form method="get" action="" id="msh-cache-filter-form">
					<input type="hidden" name="page" value="msh-hub" />
					<input type="hidden" name="tab" value="metadata" />

					<div class="msh-filter-group">
						<label for="filter-locale"><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></label>
						<select name="locale" id="filter-locale">
							<option value=""><?php esc_html_e( 'All locales', 'msh-image-optimizer' ); ?></option>
							<option value="en_US" <?php selected( $locale, 'en_US' ); ?>>English (en_US)</option>
							<option value="es_ES" <?php selected( $locale, 'es_ES' ); ?>>Español (es_ES)</option>
							<option value="fr_FR" <?php selected( $locale, 'fr_FR' ); ?>>Français (fr_FR)</option>
							<option value="de_DE" <?php selected( $locale, 'de_DE' ); ?>>Deutsch (de_DE)</option>
						</select>
					</div>

					<div class="msh-filter-group">
						<label for="filter-field"><?php esc_html_e( 'Field', 'msh-image-optimizer' ); ?></label>
						<select name="field" id="filter-field">
							<option value=""><?php esc_html_e( 'All fields', 'msh-image-optimizer' ); ?></option>
							<option value="title" <?php selected( $field, 'title' ); ?>><?php esc_html_e( 'Title', 'msh-image-optimizer' ); ?></option>
							<option value="alt" <?php selected( $field, 'alt' ); ?>><?php esc_html_e( 'Alt Text', 'msh-image-optimizer' ); ?></option>
							<option value="caption" <?php selected( $field, 'caption' ); ?>><?php esc_html_e( 'Caption', 'msh-image-optimizer' ); ?></option>
							<option value="description" <?php selected( $field, 'description' ); ?>><?php esc_html_e( 'Description', 'msh-image-optimizer' ); ?></option>
						</select>
					</div>

					<div class="msh-filter-group">
						<label for="filter-source"><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></label>
						<select name="source" id="filter-source">
							<option value=""><?php esc_html_e( 'All sources', 'msh-image-optimizer' ); ?></option>
							<option value="ai" <?php selected( $source, 'ai' ); ?>><?php esc_html_e( 'AI generated', 'msh-image-optimizer' ); ?></option>
							<option value="manual" <?php selected( $source, 'manual' ); ?>><?php esc_html_e( 'Manual edit', 'msh-image-optimizer' ); ?></option>
							<option value="imported" <?php selected( $source, 'imported' ); ?>><?php esc_html_e( 'Imported', 'msh-image-optimizer' ); ?></option>
						</select>
					</div>

					<div class="msh-filter-group">
						<label for="filter-status"><?php esc_html_e( 'Status', 'msh-image-optimizer' ); ?></label>
						<select name="status" id="filter-status">
							<option value=""><?php esc_html_e( 'All statuses', 'msh-image-optimizer' ); ?></option>
							<?php foreach ( $status_labels as $status_key => $status_data ) : ?>
								<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>><?php echo esc_html( $status_data['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="msh-filter-group">
						<label for="filter-search"><?php esc_html_e( 'Search', 'msh-image-optimizer' ); ?></label>
						<input type="search" name="search" id="filter-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search value…', 'msh-image-optimizer' ); ?>" />
					</div>

					<a href="#" class="msh-clear-filters<?php echo ( '' === $locale && '' === $field && '' === $source && '' === $status && '' === $search ) ? ' is-disabled' : ''; ?>" id="msh-clear-filters"><?php esc_html_e( 'Clear filters', 'msh-image-optimizer' ); ?></a>
				</form>
			</div>

			<div id="msh-loading-spinner" style="display: none; text-align: center; padding: 20px;">
				<span class="spinner is-active" style="float: none; margin: 0;"></span>
				<p><?php esc_html_e( 'Loading metadata entries…', 'msh-image-optimizer' ); ?></p>
			</div>

			<p class="msh-results-count">
				<?php
				printf(
				/* translators: %d: total number of metadata entries */
				esc_html__( 'Showing %d metadata entries', 'msh-image-optimizer' ),
				$total
				);
				?>
			</p>

			<?php if ( ! empty( $items ) ) : ?>
				<table class="wp-list-table widefat fixed striped msh-cache-table msh-metadata-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Media', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Field', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Value', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Status', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Updated', 'msh-image-optimizer' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
						</tr>
					</thead>
					<tbody id="msh-cache-table-body">
						<?php $this->render_metadata_rows( $items, $status_labels, $source_labels ); ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages" id="msh-cache-pagination">
							<?php
							$base_args = array(
								'page'   => 'msh-hub',
								'tab'    => 'metadata',
								'locale' => $locale,
								'field'  => $field,
								'source' => $source,
								'status' => $status,
								'search' => $search,
							);

							$base_url = add_query_arg( array_filter( $base_args, 'strlen' ), admin_url( 'admin.php' ) );

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
				<p><?php esc_html_e( 'No metadata entries found for the current filters.', 'msh-image-optimizer' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Metadata status labels map.
	 *
	 * @return array
	 */
	private function get_metadata_status_labels() {
		return array(
			'fresh'         => array( 'label' => __( 'Fresh', 'msh-image-optimizer' ), 'class' => 'fresh' ),
			'needs_regen'   => array( 'label' => __( 'Needs regen', 'msh-image-optimizer' ), 'class' => 'warning' ),
			'missing_cache' => array( 'label' => __( 'Missing cache', 'msh-image-optimizer' ), 'class' => 'error' ),
			'outdated_model'=> array( 'label' => __( 'Outdated model', 'msh-image-optimizer' ), 'class' => 'info' ),
			'locked'        => array( 'label' => __( 'Locked', 'msh-image-optimizer' ), 'class' => 'muted' ),
		);
	}

	/**
	 * Output metadata table rows.
	 *
	 * @param array $items         Metadata entries.
	 * @param array $status_labels Status label map.
	 * @param array $source_labels Source label map.
	 *
	 * @return void
	 */
	private function render_metadata_rows( $items, $status_labels, $source_labels ) {
		foreach ( $items as $entry ) {
			$status_key   = isset( $entry->metadata_status ) ? $entry->metadata_status : 'fresh';
			$status_data  = isset( $status_labels[ $status_key ] ) ? $status_labels[ $status_key ] : array( 'label' => ucfirst( $status_key ), 'class' => 'info' );
			$status_class = 'msh-status-' . str_replace( '_', '-', $status_key );
			$status_label = $status_data['label'];
			$status_badge = isset( $status_data['class'] ) ? 'msh-status-' . $status_data['class'] : $status_class;

			$source_key   = isset( $entry->source ) ? $entry->source : '' ;
			$source_label = isset( $source_labels[ $source_key ] ) ? $source_labels[ $source_key ] : ucfirst( $source_key );

			$media_id    = isset( $entry->media_id ) ? (int) $entry->media_id : 0;
			$media_label = $media_id ? sprintf( __( 'Attachment #%d', 'msh-image-optimizer' ), $media_id ) : __( 'Unknown media', 'msh-image-optimizer' );
			$media_html  = esc_html( $media_label );

			if ( $media_id && function_exists( 'get_edit_post_link' ) ) {
				$edit_link = get_edit_post_link( $media_id );
				if ( $edit_link ) {
					$title = get_the_title( $media_id );
					if ( $title ) {
						$media_label = $title . ' (' . $media_label . ')';
					}
					$media_html = sprintf( '<a href="%s">%s</a>', esc_url( $edit_link ), esc_html( $media_label ) );
				}
			}

			$value      = isset( $entry->value ) ? $entry->value : '';
			$value_raw  = (string) $value;
			$value_view = esc_html( wp_trim_words( wp_strip_all_tags( $value_raw ), 18 ) );
			$updated    = isset( $entry->updated_at ) && $entry->updated_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry->updated_at ) : __( 'N/A', 'msh-image-optimizer' );
			$entry_id   = isset( $entry->id ) ? (int) $entry->id : 0;
			$is_locked  = ( 'locked' === $status_key ) || ( isset( $entry->locked ) && $entry->locked );
			$lock_label = $is_locked ? __( 'Unlock', 'msh-image-optimizer' ) : __( 'Lock', 'msh-image-optimizer' );

			?>
			<tr class="msh-metadata-row" data-entry-id="<?php echo esc_attr( $entry_id ); ?>" data-status="<?php echo esc_attr( $status_key ); ?>" data-media-id="<?php echo esc_attr( $media_id ); ?>" data-locale="<?php echo esc_attr( $entry->locale ?? '' ); ?>" data-field="<?php echo esc_attr( $entry->field ?? '' ); ?>" data-source="<?php echo esc_attr( $source_key ); ?>">
				<td><?php echo $media_html; ?></td>
				<td><code><?php echo esc_html( $entry->locale ?? '' ); ?></code></td>
				<td><?php echo esc_html( $entry->field ?? '' ); ?></td>
				<td class="msh-metadata-value" data-full-value="<?php echo esc_attr( $value_raw ); ?>"><?php echo $value_view; ?></td>
				<td><?php echo esc_html( $source_label ?: __( 'Unknown', 'msh-image-optimizer' ) ); ?></td>
				<td>
					<span class="msh-status-badge <?php echo esc_attr( $status_badge ); ?>" data-status="<?php echo esc_attr( $status_key ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</td>
				<td><?php echo esc_html( $updated ); ?></td>
				<td>
					<div class="msh-metadata-actions">
						<button type="button" class="button-link msh-action-preview" data-entry-id="<?php echo esc_attr( $entry_id ); ?>" data-media-id="<?php echo esc_attr( $media_id ); ?>" data-locale="<?php echo esc_attr( $entry->locale ?? '' ); ?>" data-field="<?php echo esc_attr( $entry->field ?? '' ); ?>"><?php esc_html_e( 'Preview', 'msh-image-optimizer' ); ?></button>
						<button type="button" class="button-link msh-action-copy" data-entry-id="<?php echo esc_attr( $entry_id ); ?>" data-value="<?php echo esc_attr( $value_raw ); ?>"><?php esc_html_e( 'Copy', 'msh-image-optimizer' ); ?></button>
						<button type="button" class="button-link msh-action-edit" data-entry-id="<?php echo esc_attr( $entry_id ); ?>" data-media-id="<?php echo esc_attr( $media_id ); ?>" data-locale="<?php echo esc_attr( $entry->locale ?? '' ); ?>" data-field="<?php echo esc_attr( $entry->field ?? '' ); ?>"><?php esc_html_e( 'Edit', 'msh-image-optimizer' ); ?></button>
						<button type="button" class="button-link msh-action-regenerate" data-entry-id="<?php echo esc_attr( $entry_id ); ?>" data-media-id="<?php echo esc_attr( $media_id ); ?>" data-locale="<?php echo esc_attr( $entry->locale ?? '' ); ?>" data-field="<?php echo esc_attr( $entry->field ?? '' ); ?>"><?php esc_html_e( 'Regenerate', 'msh-image-optimizer' ); ?></button>
						<button type="button" class="button-link msh-action-toggle-lock" data-entry-id="<?php echo esc_attr( $entry_id ); ?>" data-media-id="<?php echo esc_attr( $media_id ); ?>" data-locale="<?php echo esc_attr( $entry->locale ?? '' ); ?>" data-field="<?php echo esc_attr( $entry->field ?? '' ); ?>" data-locked="<?php echo $is_locked ? '1' : '0'; ?>"><?php echo esc_html( $lock_label ); ?></button>
					</div>
				</td>
			</tr>
			<?php
		}
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
	 * Render Events tab content.
	 *
	 * @return void
	 */
	private function render_events_tab() {
		$events = function_exists( 'msh_get_recent_events' ) ? msh_get_recent_events( 20 ) : array();
		?>
		<div class="msh-events-tab">
			<div class="msh-events-intro">
				<p>
					<strong><?php esc_html_e( 'Memo:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Track optimizer activity in real time. Every queue run, sync operation, and telemetry ping appears here for quick diagnostics.', 'msh-image-optimizer' ); ?>
					<a href="<?php echo esc_url( 'https://github.com/toodokie/thedot-image-optimizer/blob/main/msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md#event-stream' ); ?>" class="msh-cache-intro__link" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Read more', 'msh-image-optimizer' ); ?>
					</a>
				</p>
			</div>

			<div class="msh-events-controls">
				<button type="button" id="msh-toggle-events" class="msh-queue-process-btn">
					<?php esc_html_e( 'Pause Live Feed', 'msh-image-optimizer' ); ?>
				</button>
				<span id="msh-events-status" class="msh-events-status"><?php esc_html_e( 'Live event feed running – updates every 5 seconds.', 'msh-image-optimizer' ); ?></span>
			</div>

			<div class="msh-events-stream" id="msh-events-stream">
				<?php
				if ( ! empty( $events ) ) {
					$this->render_events_list( $events );
				} else {
					echo '<p class="msh-placeholder">' . esc_html__( 'No recent events yet. The feed will populate as activity occurs.', 'msh-image-optimizer' ) . '</p>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render events list markup.
	 *
	 * @param array $events Recent event objects.
	 *
	 * @return void
	 */
	private function render_events_list( $events ) {
		if ( empty( $events ) ) {
			return;
		}
		?>
		<ul class="msh-events-list">
			<?php foreach ( $events as $event ) : ?>
				<?php
				$type      = isset( $event->type ) ? $event->type : ( $event->event ?? '' );
				$timestamp = isset( $event->timestamp ) ? $event->timestamp : ( $event->created_at ?? current_time( 'mysql' ) );
				$severity  = isset( $event->severity ) ? strtolower( $event->severity ) : 'info';
				$message   = isset( $event->message ) ? $event->message : ( $event->summary ?? '' );
				$entity_id = isset( $event->entity_id ) ? (int) $event->entity_id : 0;
				?>
				<li class="msh-event-item">
					<div class="msh-event-meta">
						<span class="msh-event-type"><?php echo esc_html( $type ); ?></span>
						<?php if ( $severity ) : ?>
							<span class="msh-event-badge msh-event-<?php echo esc_attr( $severity ); ?>"><?php echo esc_html( ucfirst( $severity ) ); ?></span>
						<?php endif; ?>
						<span class="msh-event-time"><?php echo esc_html( $timestamp ); ?></span>
					</div>
					<?php if ( $message ) : ?>
						<p class="msh-event-message"><?php echo esc_html( $message ); ?></p>
					<?php endif; ?>
					<?php if ( $entity_id ) : ?>
						<p class="msh-event-context">
							<?php
							printf(
								/* translators: %d: attachment/entity ID. */
								esc_html__( 'Entity #%d', 'msh-image-optimizer' ),
								$entity_id
							);
							?>
						</p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render History tab content.
	 *
	 * Shows version history and metadata change timeline.
	 *
	 * @return void
	 */
	private function render_history_tab() {
		// Get version history if the helper function exists
		$history_entries = array();
		if ( function_exists( 'msh_get_version_history' ) ) {
			$history_entries = msh_get_version_history( array( 'limit' => 50 ) );
		}
		?>
		<div class="msh-history-tab">
			<div class="msh-cache-intro">
				<p>
					<strong><?php esc_html_e( 'Memo:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Track all metadata changes over time. Review what was changed, when, and by whom (manual vs AI). Useful for auditing optimization decisions and rollback planning.', 'msh-image-optimizer' ); ?>
				</p>
			</div>

			<?php if ( ! empty( $history_entries ) ) : ?>
				<div class="msh-history-timeline">
					<table class="msh-table msh-history-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date/Time', 'msh-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Attachment', 'msh-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Field', 'msh-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Change', 'msh-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></th>
								<th><?php esc_html_e( 'Version', 'msh-image-optimizer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $history_entries as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( $entry['timestamp'] ?? '—' ); ?></td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'post.php?post=' . ( $entry['attachment_id'] ?? 0 ) . '&action=edit' ) ); ?>">
											#<?php echo esc_html( $entry['attachment_id'] ?? '—' ); ?>
										</a>
									</td>
									<td><?php echo esc_html( ucfirst( $entry['field'] ?? '—' ) ); ?></td>
									<td class="msh-history-change">
										<div class="msh-change-old"><?php echo esc_html( wp_trim_words( $entry['old_value'] ?? '', 10 ) ); ?></div>
										<div class="msh-change-arrow">→</div>
										<div class="msh-change-new"><?php echo esc_html( wp_trim_words( $entry['new_value'] ?? '', 10 ) ); ?></div>
									</td>
									<td><span class="msh-badge msh-badge-<?php echo esc_attr( strtolower( $entry['source'] ?? 'manual' ) ); ?>"><?php echo esc_html( ucfirst( $entry['source'] ?? 'Manual' ) ); ?></span></td>
									<td><?php echo esc_html( $entry['version'] ?? '1' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="msh-placeholder-state">
					<div class="msh-placeholder-icon">📜</div>
					<h3><?php esc_html_e( 'No Version History Yet', 'msh-image-optimizer' ); ?></h3>
					<p><?php esc_html_e( 'Once you start optimizing images and making metadata changes, the version timeline will appear here. Each change is tracked with before/after values, timestamps, and source attribution.', 'msh-image-optimizer' ); ?></p>
					<p class="msh-placeholder-note">
						<strong><?php esc_html_e( 'What gets tracked:', 'msh-image-optimizer' ); ?></strong><br>
						<?php esc_html_e( 'Title updates, ALT text changes, caption edits, description modifications, filename renames, and locale-specific metadata.', 'msh-image-optimizer' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Sync tab content.
	 *
	 * Cloud synchronization interface (Pro feature).
	 *
	 * @return void
	 */
	private function render_sync_tab() {
		$is_pro = function_exists( 'msh_is_pro_active' ) && msh_is_pro_active();

		if ( ! $is_pro ) {
			// Show Pro upsell
			?>
			<div class="msh-sync-tab">
				<div class="msh-pro-upsell">
					<div class="msh-pro-icon">🔒</div>
					<h2><?php esc_html_e( 'Cloud Sync – Pro Feature', 'msh-image-optimizer' ); ?></h2>
					<p class="msh-pro-description">
						<?php esc_html_e( 'Sync optimized metadata across multiple WordPress sites, maintain consistency across staging/production environments, and backup your metadata to the cloud for disaster recovery.', 'msh-image-optimizer' ); ?>
					</p>

					<div class="msh-pro-features">
						<h3><?php esc_html_e( 'What You Get:', 'msh-image-optimizer' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Automatic cloud backup of all metadata', 'msh-image-optimizer' ); ?></li>
							<li><?php esc_html_e( 'Push/pull sync between multiple sites', 'msh-image-optimizer' ); ?></li>
							<li><?php esc_html_e( 'Conflict resolution with version control', 'msh-image-optimizer' ); ?></li>
							<li><?php esc_html_e( 'Scheduled automatic sync (hourly/daily)', 'msh-image-optimizer' ); ?></li>
							<li><?php esc_html_e( 'Restore to any previous backup point', 'msh-image-optimizer' ); ?></li>
						</ul>
					</div>

					<div class="msh-pro-cta">
						<a href="https://thedot.com/pricing" target="_blank" rel="noopener noreferrer" class="button button-primary">
							<?php esc_html_e( 'Upgrade to Pro', 'msh-image-optimizer' ); ?>
						</a>
						<a href="https://thedot.com/features/sync" target="_blank" rel="noopener noreferrer" class="button button-secondary">
							<?php esc_html_e( 'Learn More', 'msh-image-optimizer' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
			return;
		}

		// Pro users see the actual sync interface
		$sync_status = function_exists( 'msh_get_sync_status' ) ? msh_get_sync_status() : array(
			'enabled'        => false,
			'last_sync'      => null,
			'next_scheduled' => null,
			'total_synced'   => 0,
			'pending'        => 0,
		);
		?>
		<div class="msh-sync-tab">
			<div class="msh-cache-intro">
				<p>
					<strong><?php esc_html_e( 'Memo:', 'msh-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Manage cloud synchronization of your optimized metadata. Keep multiple sites in sync or maintain reliable backups for disaster recovery.', 'msh-image-optimizer' ); ?>
				</p>
			</div>

			<div class="msh-sync-status-card">
				<h3><?php esc_html_e( 'Sync Status', 'msh-image-optimizer' ); ?></h3>
				<div class="msh-sync-stats">
					<div class="msh-sync-stat">
						<span class="msh-stat-label"><?php esc_html_e( 'Status:', 'msh-image-optimizer' ); ?></span>
						<span class="msh-stat-value msh-sync-<?php echo $sync_status['enabled'] ? 'active' : 'inactive'; ?>">
							<?php echo $sync_status['enabled'] ? esc_html__( 'Active', 'msh-image-optimizer' ) : esc_html__( 'Inactive', 'msh-image-optimizer' ); ?>
						</span>
					</div>
					<div class="msh-sync-stat">
						<span class="msh-stat-label"><?php esc_html_e( 'Last Sync:', 'msh-image-optimizer' ); ?></span>
						<span class="msh-stat-value"><?php echo esc_html( $sync_status['last_sync'] ?? __( 'Never', 'msh-image-optimizer' ) ); ?></span>
					</div>
					<div class="msh-sync-stat">
						<span class="msh-stat-label"><?php esc_html_e( 'Next Scheduled:', 'msh-image-optimizer' ); ?></span>
						<span class="msh-stat-value"><?php echo esc_html( $sync_status['next_scheduled'] ?? __( 'Not scheduled', 'msh-image-optimizer' ) ); ?></span>
					</div>
					<div class="msh-sync-stat">
						<span class="msh-stat-label"><?php esc_html_e( 'Total Synced:', 'msh-image-optimizer' ); ?></span>
						<span class="msh-stat-value"><?php echo esc_html( number_format_i18n( $sync_status['total_synced'] ) ); ?></span>
					</div>
					<div class="msh-sync-stat">
						<span class="msh-stat-label"><?php esc_html_e( 'Pending:', 'msh-image-optimizer' ); ?></span>
						<span class="msh-stat-value"><?php echo esc_html( number_format_i18n( $sync_status['pending'] ) ); ?></span>
					</div>
				</div>
			</div>

			<div class="msh-sync-actions">
				<button type="button" class="button button-primary" id="msh-trigger-sync">
					<?php esc_html_e( 'Sync Now', 'msh-image-optimizer' ); ?>
				</button>
				<button type="button" class="button button-secondary" id="msh-configure-sync">
					<?php esc_html_e( 'Configure Sync', 'msh-image-optimizer' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=msh-image-optimizer-settings&tab=sync' ) ); ?>" class="button">
					<?php esc_html_e( 'Sync Settings', 'msh-image-optimizer' ); ?>
				</a>
			</div>

			<div class="msh-placeholder-note" style="margin-top: 20px;">
				<p><em><?php esc_html_e( 'Note: The Sync feature backend is still in development. Full functionality coming soon.', 'msh-image-optimizer' ); ?></em></p>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for cache entries filtering.
	 *
	 * @return void
	 */
	public function ajax_get_metadata_entries() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
				)
			);
		}

		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';
		$field  = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : '';
		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$paged  = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;

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

		if ( '' !== $field ) {
			$args['field'] = $field;
		}

		if ( '' !== $source ) {
			$args['source'] = $source;
		}

		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$results = function_exists( 'msh_get_metadata_entries' ) ? msh_get_metadata_entries( $args ) : array(
			'items'       => array(),
			'total'       => 0,
			'total_pages' => 1,
		);

		$items       = isset( $results['items'] ) ? $results['items'] : array();
		$total       = isset( $results['total'] ) ? (int) $results['total'] : 0;
		$total_pages = isset( $results['total_pages'] ) ? max( 1, (int) $results['total_pages'] ) : 1;

		$status_labels = $this->get_metadata_status_labels();
		$source_labels = array(
			'ai'        => _x( 'AI', 'metadata source', 'msh-image-optimizer' ),
			'manual'    => _x( 'Manual', 'metadata source', 'msh-image-optimizer' ),
			'imported'  => _x( 'Imported', 'metadata source', 'msh-image-optimizer' ),
		);

		ob_start();
		if ( ! empty( $items ) ) {
			$this->render_metadata_rows( $items, $status_labels, $source_labels );
		} else {
			echo '<tr><td colspan="8">' . esc_html__( 'No metadata entries found for the current filters.', 'msh-image-optimizer' ) . '</td></tr>';
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

	/**
	 * AJAX handler for retrieving recent events.
	 *
	 * @return void
	 */
	public function ajax_get_recent_events() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
				)
			);
		}

		$events = function_exists( 'msh_get_recent_events' ) ? msh_get_recent_events( 20 ) : array();

		ob_start();
		if ( ! empty( $events ) ) {
			$this->render_events_list( $events );
		}
		$markup = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => $markup,
			)
		);
	}

	/**
	 * AJAX: Preview metadata entry
	 *
	 * Returns full metadata for display in modal.
	 *
	 * @return void
	 */
	public function ajax_preview_metadata() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entry ID.', 'msh-image-optimizer' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'msh_i18n_metadata';
		$entry = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ),
			ARRAY_A
		);

		if ( ! $entry ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'msh-image-optimizer' ) ) );
		}

		wp_send_json_success( array(
			'entry' => $entry,
			'attachment_url' => wp_get_attachment_url( $entry['attachment_id'] ),
		) );
	}

	/**
	 * AJAX: Copy metadata to clipboard
	 *
	 * Returns formatted text for clipboard copy.
	 *
	 * @return void
	 */
	public function ajax_copy_metadata() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entry ID.', 'msh-image-optimizer' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'msh_i18n_metadata';
		$entry = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ),
			ARRAY_A
		);

		if ( ! $entry ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'msh-image-optimizer' ) ) );
		}

		// Format for clipboard
		$text = sprintf(
			"Title: %s\nAlt: %s\nCaption: %s\nDescription: %s",
			$entry['title'],
			$entry['alt_text'],
			$entry['caption'],
			$entry['description']
		);

		wp_send_json_success( array(
			'text' => $text,
			'message' => __( 'Copied to clipboard!', 'msh-image-optimizer' ),
		) );
	}

	/**
	 * AJAX: Update metadata entry
	 *
	 * Saves inline edits to metadata.
	 *
	 * @return void
	 */
	public function ajax_update_metadata() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$alt_text = isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';
		$caption = isset( $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entry ID.', 'msh-image-optimizer' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'msh_i18n_metadata';

		$updated = $wpdb->update(
			$table,
			array(
				'title' => $title,
				'alt_text' => $alt_text,
				'caption' => $caption,
				'description' => $description,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $entry_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update metadata.', 'msh-image-optimizer' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Metadata updated successfully.', 'msh-image-optimizer' ),
		) );
	}

	/**
	 * AJAX: Toggle lock/protection status
	 *
	 * Toggles the protected flag on metadata entry.
	 *
	 * @return void
	 */
	public function ajax_toggle_lock() {
		check_ajax_referer( 'msh_hub_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid entry ID.', 'msh-image-optimizer' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'msh_i18n_metadata';

		// Get current status
		$current_status = $wpdb->get_var(
			$wpdb->prepare( "SELECT protected FROM {$table} WHERE id = %d", $entry_id )
		);

		if ( null === $current_status ) {
			wp_send_json_error( array( 'message' => __( 'Entry not found.', 'msh-image-optimizer' ) ) );
		}

		// Toggle
		$new_status = $current_status ? 0 : 1;

		$updated = $wpdb->update(
			$table,
			array( 'protected' => $new_status ),
			array( 'id' => $entry_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle lock status.', 'msh-image-optimizer' ) ) );
		}

		wp_send_json_success( array(
			'protected' => (bool) $new_status,
			'message' => $new_status
				? __( 'Entry locked.', 'msh-image-optimizer' )
				: __( 'Entry unlocked.', 'msh-image-optimizer' ),
		) );
	}
}

MSH_Hub_Page::get_instance();
