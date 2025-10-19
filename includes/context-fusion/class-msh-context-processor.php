<?php
/**
 * Context Fusion Layer - Background Processor
 *
 * Handles background processing of context extraction jobs.
 * Processes scheduled events and batch operations.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Background Processor
 *
 * Handles the 'msh_ctx_update_post_context' scheduled event.
 * Extracts context for a post when triggered by save_post hook.
 */
class MSH_Context_Processor {

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_Processor|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_Processor
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
		// Register cron event handler
		add_action( 'msh_ctx_update_post_context', array( $this, 'process_post_context' ), 10, 2 );

		// Register custom cron schedule for batch processing
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules
	 */
	public function add_cron_schedules( $schedules ) {
		// Every 5 minutes for context maintenance
		$schedules['msh_five_minutes'] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 5 Minutes', 'msh-image-optimizer' ),
		);

		// Every 15 minutes for context cleanup
		$schedules['msh_fifteen_minutes'] = array(
			'interval' => 900,
			'display'  => esc_html__( 'Every 15 Minutes', 'msh-image-optimizer' ),
		);

		return $schedules;
	}

	/**
	 * Process context extraction for a post
	 *
	 * Called by scheduled event: msh_ctx_update_post_context
	 *
	 * @param int    $post_id Post ID to process.
	 * @param string $locale  Locale code.
	 */
	public function process_post_context( $post_id, $locale ) {
		// Validate post exists and is published
		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}

		// Initialize components
		$extractor  = new MSH_Context_Extractor();
		$manager    = MSH_Context_Manager::get_instance();
		$classifier = new MSH_Intent_Classifier();
		$normalizer = new MSH_Keyword_Normalizer();

		// Extract post context
		$post_context = $extractor->extract_post_context( $post, $locale );
		$source_hash  = $extractor->calculate_source_hash( $post );

		// Find all images in post
		$images = $extractor->find_images_in_post( $post );

		if ( empty( $images ) ) {
			return;
		}

		// Process each image
		foreach ( $images as $image_data ) {
			$media_id   = $image_data['media_id'];
			$usage_type = $image_data['usage_type'];
			$block_path = $image_data['block_path'];

			// Classify intent
			$intent_result = $classifier->classify( $media_id, $post_context, $locale );

			// Extract keywords
			$combined_text = $post_context['title'] . ' ' . $post_context['excerpt'] . ' ' . $post_context['content'];
			$keywords      = $normalizer->extract_keywords( $combined_text, $locale, 20 );

			// Calculate context score
			$context_score = $this->calculate_context_score( $intent_result, $keywords );

			// Build context data
			$context = array(
				'media_id'          => $media_id,
				'post_id'           => $post_id,
				'locale'            => $locale,
				'usage_type'        => $usage_type,
				'block_path'        => $block_path,
				'subject'           => $post_context['title'],
				'intent'            => $intent_result['intent'],
				'intent_confidence' => $intent_result['confidence'],
				'entities'          => $normalizer->extract_entities( $combined_text, $locale ),
				'keywords'          => $keywords,
				'rules_fired'       => $intent_result['rules_fired'],
				'source_hash'       => $source_hash,
				'context_score'     => $context_score,
			);

			// Store or update context
			$manager->store_context( $context );
		}

		// Log successful processing
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'MSH Context: Processed post %d (%s) - %d images', $post_id, $locale, count( $images ) ) );
		}
	}

	/**
	 * Calculate context score based on intent and keywords
	 *
	 * @param array $intent_result Intent classification result.
	 * @param array $keywords      Extracted keywords.
	 * @return int Score 0-100
	 */
	private function calculate_context_score( $intent_result, $keywords ) {
		$score = 0;

		// Intent confidence contributes 60%
		if ( 'on_topic' === $intent_result['intent'] ) {
			$score += (int) ( $intent_result['confidence'] * 0.6 );
		} elseif ( 'off_topic' === $intent_result['intent'] ) {
			$score += (int) ( ( 100 - $intent_result['confidence'] ) * 0.3 );
		} else {
			$score += 20; // Unknown intent = low score
		}

		// Keyword count contributes 40%
		$keyword_count = count( $keywords );
		$keyword_score = min( 100, $keyword_count * 5 ); // 5 points per keyword, max 100
		$score        += (int) ( $keyword_score * 0.4 );

		return min( 100, $score );
	}

	/**
	 * Queue context extraction for a post
	 *
	 * Public method to manually queue extraction (e.g., from admin).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $locale  Locale code.
	 * @param int    $delay   Delay in seconds (default: 60).
	 * @return bool True if scheduled successfully
	 */
	public function queue_post_context( $post_id, $locale, $delay = 60 ) {
		// Check if already scheduled
		$timestamp = wp_next_scheduled( 'msh_ctx_update_post_context', array( $post_id, $locale ) );

		if ( $timestamp ) {
			// Already scheduled, don't duplicate
			return false;
		}

		// Schedule event
		$scheduled = wp_schedule_single_event(
			time() + $delay,
			'msh_ctx_update_post_context',
			array( $post_id, $locale )
		);

		return false !== $scheduled;
	}

	/**
	 * Cancel queued context extraction for a post
	 *
	 * @param int    $post_id Post ID.
	 * @param string $locale  Locale code.
	 * @return bool True if cancelled successfully
	 */
	public function cancel_queued_context( $post_id, $locale ) {
		$timestamp = wp_next_scheduled( 'msh_ctx_update_post_context', array( $post_id, $locale ) );

		if ( ! $timestamp ) {
			return false;
		}

		wp_unschedule_event( $timestamp, 'msh_ctx_update_post_context', array( $post_id, $locale ) );
		return true;
	}

	/**
	 * Get queue status
	 *
	 * Returns information about pending context extraction jobs.
	 *
	 * @return array Queue statistics
	 */
	public function get_queue_status() {
		global $wpdb;

		// Get all scheduled context events
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$scheduled = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options}
				WHERE option_name LIKE %s
				ORDER BY option_name ASC",
				$wpdb->esc_like( '_transient_timeout_' ) . 'cron%'
			),
			ARRAY_A
		);

		// Count context extraction jobs
		$context_jobs = 0;
		$cron         = get_option( 'cron', array() );

		foreach ( $cron as $timestamp => $cron_jobs ) {
			if ( isset( $cron_jobs['msh_ctx_update_post_context'] ) ) {
				$context_jobs += count( $cron_jobs['msh_ctx_update_post_context'] );
			}
		}

		return array(
			'pending_jobs'     => $context_jobs,
			'next_run'         => $this->get_next_scheduled_time(),
			'cron_enabled'     => ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON,
			'processing_rate'  => $this->get_processing_rate(),
		);
	}

	/**
	 * Get next scheduled context extraction time
	 *
	 * @return int|null Timestamp or null if none scheduled
	 */
	private function get_next_scheduled_time() {
		$cron = get_option( 'cron', array() );

		foreach ( $cron as $timestamp => $cron_jobs ) {
			if ( isset( $cron_jobs['msh_ctx_update_post_context'] ) ) {
				return (int) $timestamp;
			}
		}

		return null;
	}

	/**
	 * Get processing rate (jobs per hour)
	 *
	 * Calculates based on recent processing history.
	 *
	 * @return float Jobs per hour
	 */
	private function get_processing_rate() {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// Get contexts updated in last hour
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name}
			WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
		);

		return (float) $count;
	}

	/**
	 * Process stale contexts
	 *
	 * Finds and reprocesses contexts that haven't been updated in a while.
	 * Can be called via cron or manually.
	 *
	 * @param int $days Days since last update (default: 30).
	 * @param int $limit Maximum contexts to process (default: 100).
	 * @return array Processing results
	 */
	public function process_stale_contexts( $days = 30, $limit = 100 ) {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// Find stale contexts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stale_contexts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT post_id, locale FROM {$table_name}
				WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY)
				LIMIT %d",
				$days,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $stale_contexts ) ) {
			return array(
				'found'   => 0,
				'queued'  => 0,
				'skipped' => 0,
			);
		}

		$results = array(
			'found'   => count( $stale_contexts ),
			'queued'  => 0,
			'skipped' => 0,
		);

		// Queue each for processing
		foreach ( $stale_contexts as $context ) {
			$post_id = (int) $context['post_id'];
			$locale  = $context['locale'];

			// Check if post still exists and is published
			$post = get_post( $post_id );

			if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
				// Post deleted or unpublished, delete stale context
				MSH_Context_Database::delete_post_context( $post_id, $locale );
				$results['skipped']++;
				continue;
			}

			// Queue for reprocessing
			if ( $this->queue_post_context( $post_id, $locale, 0 ) ) {
				$results['queued']++;
			} else {
				$results['skipped']++;
			}
		}

		return $results;
	}

	/**
	 * Cleanup orphaned contexts
	 *
	 * Removes context entries for deleted posts or media.
	 *
	 * @return array Cleanup results
	 */
	public function cleanup_orphaned_contexts() {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// Find contexts for non-existent posts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orphaned_post_contexts = $wpdb->query(
			"DELETE ctx FROM {$table_name} ctx
			LEFT JOIN {$wpdb->posts} p ON ctx.post_id = p.ID
			WHERE p.ID IS NULL"
		);

		// Find contexts for non-existent media
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orphaned_media_contexts = $wpdb->query(
			"DELETE ctx FROM {$table_name} ctx
			LEFT JOIN {$wpdb->posts} m ON ctx.media_id = m.ID
			WHERE m.ID IS NULL"
		);

		return array(
			'orphaned_post_contexts'  => (int) $orphaned_post_contexts,
			'orphaned_media_contexts' => (int) $orphaned_media_contexts,
			'total_deleted'           => (int) $orphaned_post_contexts + (int) $orphaned_media_contexts,
		);
	}

	/**
	 * Get processing statistics
	 *
	 * Returns metrics about context processing performance.
	 *
	 * @return array Statistics
	 */
	public function get_processing_stats() {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// Get contexts by age
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$age_stats = $wpdb->get_results(
			"SELECT
				SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as last_day,
				SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_week,
				SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_month,
				SUM(CASE WHEN last_seen < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as stale
			FROM {$table_name}",
			ARRAY_A
		);

		$queue_status = $this->get_queue_status();

		return array(
			'age_distribution' => $age_stats[0] ?? array(),
			'queue_status'     => $queue_status,
			'last_processed'   => $this->get_last_processed_time(),
		);
	}

	/**
	 * Get last processed timestamp
	 *
	 * @return string|null ISO8601 timestamp or null
	 */
	private function get_last_processed_time() {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_seen = $wpdb->get_var(
			"SELECT MAX(last_seen) FROM {$table_name}"
		);

		return $last_seen ? gmdate( 'c', strtotime( $last_seen ) ) : null;
	}
}
