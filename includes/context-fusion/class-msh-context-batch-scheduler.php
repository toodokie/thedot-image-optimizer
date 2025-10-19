<?php
/**
 * Context Batch Scheduler
 *
 * Schedules context extraction in batches for off-peak hours
 * to minimize server load during high-traffic periods.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batch Scheduler Class
 *
 * Manages batch scheduling for context extraction.
 */
class MSH_Context_Batch_Scheduler {

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_Batch_Scheduler
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_Batch_Scheduler
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
		// Register batch extraction cron hook
		add_action( 'msh_ctx_batch_extract', array( $this, 'process_batch' ) );
	}

	/**
	 * Schedule bulk extraction
	 *
	 * @param array $post_ids Post IDs to extract.
	 * @param array $options  Scheduling options.
	 * @return int Number of batches scheduled.
	 */
	public function schedule_bulk_extraction( $post_ids, $options = array() ) {
		$defaults = array(
			'batch_size' => 10,
			'start_time' => strtotime( 'tomorrow 2:00am' ),
			'interval'   => 60, // Seconds between batches
			'locale'     => get_locale(),
		);

		$options = wp_parse_args( $options, $defaults );

		$batches = array_chunk( $post_ids, $options['batch_size'] );
		$delay = 0;
		$scheduled = 0;

		foreach ( $batches as $batch ) {
			$timestamp = $options['start_time'] + $delay;

			$result = wp_schedule_single_event(
				$timestamp,
				'msh_ctx_batch_extract',
				array(
					array(
						'post_ids' => $batch,
						'locale'   => $options['locale'],
					),
				)
			);

			if ( false !== $result ) {
				$scheduled++;
			}

			$delay += $options['interval'];
		}

		return $scheduled;
	}

	/**
	 * Process a batch of extractions
	 *
	 * @param array $args Batch arguments.
	 */
	public function process_batch( $args ) {
		$post_ids = isset( $args['post_ids'] ) ? $args['post_ids'] : array();
		$locale = isset( $args['locale'] ) ? $args['locale'] : get_locale();

		if ( empty( $post_ids ) ) {
			return;
		}

		$processor = MSH_Context_Processor::get_instance();

		foreach ( $post_ids as $post_id ) {
			// Process post context extraction
			$processor->process_post_context( $post_id, $locale );

			// Small delay to prevent overwhelming the server
			usleep( 100000 ); // 0.1 seconds
		}

		// Log batch completion
		do_action( 'msh_ctx_batch_completed', $post_ids, $locale );
	}

	/**
	 * Check if current time is off-peak
	 *
	 * @param array $options Peak hour configuration.
	 * @return bool True if off-peak.
	 */
	public function is_off_peak( $options = array() ) {
		$defaults = array(
			'off_peak_start' => 0,  // Midnight
			'off_peak_end'   => 6,  // 6 AM
		);

		$options = wp_parse_args( $options, $defaults );

		$hour = (int) current_time( 'H' );

		return $hour >= $options['off_peak_start'] && $hour < $options['off_peak_end'];
	}

	/**
	 * Get pending scheduled batches
	 *
	 * @return array Scheduled events.
	 */
	public function get_pending_batches() {
		$cron_array = _get_cron_array();
		$pending = array();

		foreach ( $cron_array as $timestamp => $cron ) {
			if ( isset( $cron['msh_ctx_batch_extract'] ) ) {
				foreach ( $cron['msh_ctx_batch_extract'] as $hash => $event ) {
					$pending[] = array(
						'timestamp' => $timestamp,
						'scheduled' => date( 'Y-m-d H:i:s', $timestamp ),
						'args'      => $event['args'],
						'hash'      => $hash,
					);
				}
			}
		}

		return $pending;
	}

	/**
	 * Cancel all pending batches
	 *
	 * @return int Number of batches cancelled.
	 */
	public function cancel_all_batches() {
		$pending = $this->get_pending_batches();
		$cancelled = 0;

		foreach ( $pending as $batch ) {
			$result = wp_unschedule_event(
				$batch['timestamp'],
				'msh_ctx_batch_extract',
				$batch['args']
			);

			if ( $result ) {
				$cancelled++;
			}
		}

		return $cancelled;
	}

	/**
	 * Get batch statistics
	 *
	 * @return array Statistics.
	 */
	public function get_batch_stats() {
		$pending = $this->get_pending_batches();

		$total_posts = 0;
		foreach ( $pending as $batch ) {
			if ( isset( $batch['args'][0]['post_ids'] ) ) {
				$total_posts += count( $batch['args'][0]['post_ids'] );
			}
		}

		return array(
			'pending_batches' => count( $pending ),
			'total_posts'     => $total_posts,
			'next_batch'      => ! empty( $pending ) ? $pending[0]['scheduled'] : null,
		);
	}

	/**
	 * Schedule smart reindex
	 *
	 * Only schedules extraction for posts that have changed.
	 *
	 * @param array $options Scheduling options.
	 * @return array Result with scheduled count.
	 */
	public function schedule_smart_reindex( $options = array() ) {
		$defaults = array(
			'post_type'   => 'any',
			'post_status' => 'publish',
			'locale'      => get_locale(),
			'batch_size'  => 10,
			'start_time'  => strtotime( 'tomorrow 2:00am' ),
		);

		$options = wp_parse_args( $options, $defaults );

		// Get all published posts
		$posts = get_posts(
			array(
				'post_type'      => $options['post_type'],
				'post_status'    => $options['post_status'],
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		// Filter to only posts that need update
		$needs_update = array();
		foreach ( $posts as $post_id ) {
			if ( $this->needs_update( $post_id, $options['locale'] ) ) {
				$needs_update[] = $post_id;
			}
		}

		// Schedule batches
		$scheduled = $this->schedule_bulk_extraction( $needs_update, $options );

		return array(
			'total_posts'      => count( $posts ),
			'needs_update'     => count( $needs_update ),
			'batches_scheduled' => $scheduled,
		);
	}

	/**
	 * Check if post needs context update
	 *
	 * @param int    $post_id Post ID.
	 * @param string $locale  Locale code.
	 * @return bool True if needs update.
	 */
	private function needs_update( $post_id, $locale ) {
		global $wpdb;

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// Calculate current content hash
		$extractor = new MSH_Context_Extractor();
		$current_hash = $extractor->calculate_source_hash( $post );

		// Get existing hash from database
		$table = $wpdb->prefix . 'msh_optimizer_context';

		$existing_hash = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT source_hash FROM {$table}
				WHERE post_id = %d AND locale = %s
				LIMIT 1",
				$post_id,
				$locale
			)
		);

		// If no existing context, needs update
		if ( ! $existing_hash ) {
			return true;
		}

		// Compare hashes
		return $current_hash !== $existing_hash;
	}
}
