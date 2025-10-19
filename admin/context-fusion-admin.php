<?php
/**
 * Context Fusion Layer - Admin UI
 *
 * Displays context information in the WordPress admin:
 * - Media edit screen metabox
 * - Settings page integration
 * - Context statistics dashboard widget
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Fusion Admin UI
 *
 * Handles all admin UI for the Context Fusion Layer.
 */
class MSH_Context_Fusion_Admin {

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_Fusion_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_Fusion_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		// Add media edit screen metabox
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_context_metabox' ) );

		// Add dashboard widget
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

		// Add AJAX handlers
		add_action( 'wp_ajax_msh_get_image_context', array( $this, 'ajax_get_image_context' ) );
		add_action( 'wp_ajax_msh_extract_image_context', array( $this, 'ajax_extract_image_context' ) );
		add_action( 'wp_ajax_msh_find_similar_images', array( $this, 'ajax_find_similar_images' ) );

		// Add admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add context metabox to media edit screen
	 */
	public function add_context_metabox() {
		add_meta_box(
			'msh-context-fusion',
			esc_html__( 'Image Context', 'msh-image-optimizer' ),
			array( $this, 'render_context_metabox' ),
			'attachment',
			'side',
			'default'
		);
	}

	/**
	 * Render context metabox
	 *
	 * @param WP_Post $post Attachment post object.
	 */
	public function render_context_metabox( $post ) {
		$media_id = $post->ID;
		$locale   = get_locale();

		// Get context rollup
		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			?>
			<div class="msh-context-empty">
				<p><?php esc_html_e( 'No context data available for this image.', 'msh-image-optimizer' ); ?></p>
				<p>
					<button type="button" class="button button-secondary msh-extract-context" data-media-id="<?php echo esc_attr( $media_id ); ?>">
						<?php esc_html_e( 'Extract Context', 'msh-image-optimizer' ); ?>
					</button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Context is extracted automatically when posts are published. Click "Extract Context" to manually extract context from all posts using this image.', 'msh-image-optimizer' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		// Display context information
		?>
		<div class="msh-context-display">
			<div class="msh-context-section">
				<h4><?php esc_html_e( 'Intent Classification', 'msh-image-optimizer' ); ?></h4>
				<p>
					<strong><?php esc_html_e( 'Intent:', 'msh-image-optimizer' ); ?></strong>
					<span class="msh-intent-badge msh-intent-<?php echo esc_attr( $rollup['intent'] ); ?>">
						<?php echo esc_html( ucfirst( str_replace( '_', ' ', $rollup['intent'] ) ) ); ?>
					</span>
				</p>
				<p>
					<strong><?php esc_html_e( 'Confidence:', 'msh-image-optimizer' ); ?></strong>
					<span class="msh-confidence-bar">
						<span class="msh-confidence-fill" style="width: <?php echo esc_attr( $rollup['intent_confidence'] ); ?>%"></span>
					</span>
					<?php echo esc_html( $rollup['intent_confidence'] ); ?>%
				</p>
			</div>

			<div class="msh-context-section">
				<h4><?php esc_html_e( 'Context Score', 'msh-image-optimizer' ); ?></h4>
				<div class="msh-score-display">
					<span class="msh-score-number"><?php echo esc_html( $rollup['avg_context_score'] ); ?></span>
					<span class="msh-score-max">/100</span>
				</div>
			</div>

			<div class="msh-context-section">
				<h4><?php esc_html_e( 'Usage Statistics', 'msh-image-optimizer' ); ?></h4>
				<p>
					<?php
					printf(
						/* translators: 1: number of contexts, 2: total usage count */
						esc_html__( 'Used in %1$d contexts with %2$d total usages', 'msh-image-optimizer' ),
						esc_html( $rollup['context_count'] ),
						esc_html( $rollup['total_usage_count'] )
					);
					?>
				</p>
			</div>

			<?php if ( ! empty( $rollup['keywords'] ) ) : ?>
				<div class="msh-context-section">
					<h4><?php esc_html_e( 'Keywords', 'msh-image-optimizer' ); ?></h4>
					<div class="msh-keywords">
						<?php foreach ( $rollup['keywords'] as $keyword ) : ?>
							<span class="msh-keyword-tag"><?php echo esc_html( $keyword ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $rollup['top_subject'] ) ) : ?>
				<div class="msh-context-section">
					<h4><?php esc_html_e( 'Top Subject', 'msh-image-optimizer' ); ?></h4>
					<p><?php echo esc_html( $rollup['top_subject'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="msh-context-section">
				<h4><?php esc_html_e( 'Used In Posts', 'msh-image-optimizer' ); ?></h4>
				<?php
				$post_ids = $manager->find_posts_using_media( $media_id, $locale );

				if ( ! empty( $post_ids ) ) :
					?>
					<ul class="msh-post-list">
						<?php
						foreach ( array_slice( $post_ids, 0, 5 ) as $post_id ) :
							$post_obj = get_post( $post_id );
							if ( $post_obj ) :
								?>
								<li>
									<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
										<?php echo esc_html( $post_obj->post_title ); ?>
									</a>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
					<?php
					if ( count( $post_ids ) > 5 ) :
						printf(
							'<p class="description">%s</p>',
							sprintf(
								/* translators: %d: number of additional posts */
								esc_html__( '...and %d more', 'msh-image-optimizer' ),
								esc_html( count( $post_ids ) - 5 )
							)
						);
					endif;
					?>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Not currently used in any posts.', 'msh-image-optimizer' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="msh-context-section">
				<h4><?php esc_html_e( 'Recommendations', 'msh-image-optimizer' ); ?></h4>
				<p>
					<button type="button" class="button button-secondary msh-find-similar" data-media-id="<?php echo esc_attr( $media_id ); ?>">
						<?php esc_html_e( 'Find Similar Images', 'msh-image-optimizer' ); ?>
					</button>
				</p>
				<div class="msh-similar-results" style="display: none; margin-top: 10px;"></div>
			</div>

			<p style="margin-top: 15px;">
				<button type="button" class="button button-secondary msh-refresh-context" data-media-id="<?php echo esc_attr( $media_id ); ?>">
					<?php esc_html_e( 'Refresh Context', 'msh-image-optimizer' ); ?>
				</button>
			</p>
		</div>

		<style>
			.msh-context-display {
				font-size: 13px;
			}
			.msh-context-section {
				margin-bottom: 15px;
				padding-bottom: 10px;
				border-bottom: 1px solid #dcdcde;
			}
			.msh-context-section:last-child {
				border-bottom: none;
			}
			.msh-context-section h4 {
				margin: 0 0 8px 0;
				font-size: 12px;
				font-weight: 600;
				text-transform: uppercase;
				color: #646970;
			}
			.msh-intent-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.msh-intent-on_topic {
				background: #d4edda;
				color: #155724;
			}
			.msh-intent-off_topic {
				background: #f8d7da;
				color: #721c24;
			}
			.msh-intent-unknown {
				background: #e2e3e5;
				color: #383d41;
			}
			.msh-confidence-bar {
				display: inline-block;
				width: 100px;
				height: 12px;
				background: #e2e3e5;
				border-radius: 6px;
				overflow: hidden;
				vertical-align: middle;
				margin: 0 8px;
			}
			.msh-confidence-fill {
				display: block;
				height: 100%;
				background: linear-gradient(90deg, #28a745, #218838);
			}
			.msh-score-display {
				font-size: 32px;
				font-weight: 600;
				color: #2271b1;
			}
			.msh-score-max {
				font-size: 18px;
				color: #646970;
			}
			.msh-keywords {
				display: flex;
				flex-wrap: wrap;
				gap: 5px;
			}
			.msh-keyword-tag {
				display: inline-block;
				padding: 3px 8px;
				background: #f0f0f1;
				border-radius: 3px;
				font-size: 11px;
			}
			.msh-post-list {
				margin: 8px 0;
				padding-left: 20px;
			}
			.msh-post-list li {
				margin-bottom: 4px;
			}
		</style>
		<?php
	}

	/**
	 * Add dashboard widget
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'msh-context-stats',
			esc_html__( 'Image Context Statistics', 'msh-image-optimizer' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget
	 */
	public function render_dashboard_widget() {
		$stats = MSH_Context_Database::get_stats();

		if ( ! $stats['table_exists'] ) {
			echo '<p>' . esc_html__( 'Context fusion table not initialized.', 'msh-image-optimizer' ) . '</p>';
			return;
		}

		?>
		<div class="msh-dashboard-stats">
			<div class="msh-stat-row">
				<span class="msh-stat-label"><?php esc_html_e( 'Total Contexts:', 'msh-image-optimizer' ); ?></span>
				<span class="msh-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_rows'] ) ); ?></span>
			</div>
			<div class="msh-stat-row">
				<span class="msh-stat-label"><?php esc_html_e( 'Unique Images:', 'msh-image-optimizer' ); ?></span>
				<span class="msh-stat-value"><?php echo esc_html( number_format_i18n( $stats['unique_media'] ) ); ?></span>
			</div>
			<div class="msh-stat-row">
				<span class="msh-stat-label"><?php esc_html_e( 'Unique Posts:', 'msh-image-optimizer' ); ?></span>
				<span class="msh-stat-value"><?php echo esc_html( number_format_i18n( $stats['unique_posts'] ) ); ?></span>
			</div>

			<?php if ( ! empty( $stats['intent_counts'] ) ) : ?>
				<h4><?php esc_html_e( 'Intent Distribution', 'msh-image-optimizer' ); ?></h4>
				<?php foreach ( $stats['intent_counts'] as $row ) : ?>
					<div class="msh-stat-row">
						<span class="msh-stat-label"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['intent'] ) ) ); ?>:</span>
						<span class="msh-stat-value"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<style>
			.msh-dashboard-stats {
				margin: 0 -12px;
			}
			.msh-stat-row {
				display: flex;
				justify-content: space-between;
				padding: 8px 12px;
				border-bottom: 1px solid #f0f0f1;
			}
			.msh-stat-row:last-child {
				border-bottom: none;
			}
			.msh-stat-label {
				font-weight: 500;
			}
			.msh-stat-value {
				color: #2271b1;
				font-weight: 600;
			}
		</style>
		<?php
	}

	/**
	 * AJAX: Get image context
	 */
	public function ajax_get_image_context() {
		check_ajax_referer( 'msh-context-admin', 'nonce' );

		$media_id = isset( $_POST['media_id'] ) ? (int) $_POST['media_id'] : 0;
		$locale   = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : get_locale();

		if ( ! $media_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media ID', 'msh-image-optimizer' ) ) );
		}

		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			wp_send_json_error( array( 'message' => __( 'No context found', 'msh-image-optimizer' ) ) );
		}

		wp_send_json_success( $rollup );
	}

	/**
	 * AJAX: Extract image context
	 */
	public function ajax_extract_image_context() {
		check_ajax_referer( 'msh-context-admin', 'nonce' );

		$media_id = isset( $_POST['media_id'] ) ? (int) $_POST['media_id'] : 0;

		if ( ! $media_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media ID', 'msh-image-optimizer' ) ) );
		}

		// Find all posts using this image
		$manager  = MSH_Context_Manager::get_instance();
		$post_ids = $manager->find_posts_using_media( $media_id );

		if ( empty( $post_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Image not used in any posts', 'msh-image-optimizer' ) ) );
		}

		// Queue extraction for each post
		$processor = MSH_Context_Processor::get_instance();
		$queued    = 0;

		foreach ( $post_ids as $post_id ) {
			$locale = get_locale();

			// Get post's actual locale if multilingual
			if ( function_exists( 'pll_get_post_language' ) ) {
				$post_locale = pll_get_post_language( $post_id, 'locale' );
				if ( $post_locale ) {
					$locale = $post_locale;
				}
			}

			if ( $processor->queue_post_context( $post_id, $locale, 0 ) ) {
				$queued++;
			}
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of posts queued */
					__( 'Queued %d posts for context extraction', 'msh-image-optimizer' ),
					$queued
				),
				'queued' => $queued,
			)
		);
	}

	/**
	 * AJAX: Find similar images
	 */
	public function ajax_find_similar_images() {
		check_ajax_referer( 'msh-context-admin', 'nonce' );

		$media_id = isset( $_POST['media_id'] ) ? (int) $_POST['media_id'] : 0;
		$limit    = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 5;

		if ( ! $media_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid media ID', 'msh-image-optimizer' ) ) );
		}

		$recommender = new MSH_Context_Recommender();
		$similar     = $recommender->find_similar_images(
			$media_id,
			array(
				'limit'        => $limit,
				'min_similarity' => 0.6,
				'locale'       => get_locale(),
			)
		);

		if ( empty( $similar ) ) {
			wp_send_json_error( array( 'message' => __( 'No similar images found', 'msh-image-optimizer' ) ) );
		}

		// Enrich with image URLs
		foreach ( $similar as &$item ) {
			$item['thumbnail'] = wp_get_attachment_image_url( $item['media_id'], 'thumbnail' );
			$item['edit_url']  = get_edit_post_link( $item['media_id'] );

			$attachment = get_post( $item['media_id'] );
			if ( $attachment ) {
				$item['title'] = $attachment->post_title;
			}
		}

		wp_send_json_success(
			array(
				'similar' => $similar,
				'count'   => count( $similar ),
			)
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on media edit screen
		if ( 'post.php' !== $hook && 'attachment' !== get_post_type() ) {
			return;
		}

		wp_enqueue_script(
			'msh-context-admin',
			MSH_IO_PLUGIN_URL . 'admin/js/context-admin.js',
			array( 'jquery' ),
			MSH_Image_Optimizer_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'msh-context-admin',
			'mshContextAdmin',
			array(
				'nonce'     => wp_create_nonce( 'msh-context-admin' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'strings'   => array(
					'extracting' => __( 'Extracting...', 'msh-image-optimizer' ),
					'refreshing' => __( 'Refreshing...', 'msh-image-optimizer' ),
					'finding'    => __( 'Finding similar images...', 'msh-image-optimizer' ),
					'error'      => __( 'Error:', 'msh-image-optimizer' ),
				),
			)
		);
	}
}
