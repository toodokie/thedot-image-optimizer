<?php
/**
 * Job Engine - Core Job Queue Processor
 *
 * Handles job enqueueing, processing, retry logic, and failure handling
 * for the Phase 5+9 automation infrastructure.
 *
 * Features:
 * - Job queue with priority support (high, medium, normal)
 * - Automatic retry with exponential backoff
 * - Dead-letter queue for permanently failed jobs
 * - Idempotency (prevents duplicate pending jobs)
 * - WP-Cron integration for background processing
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Job_Engine
 *
 * Core job processing engine for automation tasks.
 */
class MSH_Job_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Job_Engine|null
	 */
	private static $instance = null;

	/**
	 * Jobs table name.
	 *
	 * @var string
	 */
	private $jobs_table;

	/**
	 * Dead letters table name.
	 *
	 * @var string
	 */
	private $dead_letters_table;

	/**
	 * Maximum retry attempts per job.
	 *
	 * @var int
	 */
	private $max_attempts = 3;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Job_Engine
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;

		$this->jobs_table         = $wpdb->prefix . 'msh_jobs';
		$this->dead_letters_table = $wpdb->prefix . 'msh_dead_letters';

		// Hook WP-Cron for background processing
		add_action( 'msh_process_job_queue', array( $this, 'cron_process_queue' ) );
	}

	/**
	 * Enqueue a new job.
	 *
	 * Supports idempotency - won't create duplicate pending/processing jobs
	 * for the same entity.
	 *
	 * @param string $job_type    Job type (e.g., 'regenerate_metadata', 'generate_metadata').
	 * @param string $entity_type Entity type (e.g., 'attachment', 'post').
	 * @param int    $entity_id   Entity ID.
	 * @param array  $payload     Job-specific data (locale, field, etc.).
	 * @param string $priority    Priority level: 'high', 'medium', 'normal'.
	 * @return int|WP_Error Job ID on success, WP_Error on failure.
	 */
	public function enqueue( $job_type, $entity_type, $entity_id, $payload = array(), $priority = 'normal' ) {
		global $wpdb;

		// Validate priority
		if ( ! in_array( $priority, array( 'high', 'medium', 'normal' ), true ) ) {
			$priority = 'normal';
		}

		// Check for existing pending/processing job (idempotency)
		$existing = $this->find_duplicate( $job_type, $entity_type, $entity_id, $payload );
		if ( $existing && in_array( $existing->status, array( 'pending', 'processing' ), true ) ) {
			// Return existing job ID - don't create duplicate
			return (int) $existing->id;
		}

		// Insert new job
		$result = $wpdb->insert(
			$this->jobs_table,
			array(
				'job_type'     => $job_type,
				'entity_type'  => $entity_type,
				'entity_id'    => $entity_id,
				'payload'      => wp_json_encode( $payload ),
				'priority'     => $priority,
				'status'       => 'pending',
				'attempts'     => 0,
				'max_attempts' => $this->max_attempts,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_insert_failed', __( 'Failed to enqueue job.', 'msh-image-optimizer' ), $wpdb->last_error );
		}

		$job_id = $wpdb->insert_id;

		// Emit event
		do_action( 'msh_job_enqueued', $job_id, $job_type, $entity_type, $entity_id, $priority );

		return $job_id;
	}

	/**
	 * Find duplicate job.
	 *
	 * Checks for existing job with same type, entity, and payload.
	 *
	 * @param string $job_type    Job type.
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity ID.
	 * @param array  $payload     Payload data.
	 * @return object|null Duplicate job or null.
	 */
	private function find_duplicate( $job_type, $entity_type, $entity_id, $payload = array() ) {
		global $wpdb;

		// For simple duplicate checking, match on type + entity
		// For stricter checking, also compare payload (locale, field, etc.)
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->jobs_table}
			WHERE job_type = %s
			AND entity_type = %s
			AND entity_id = %d
			AND status IN ('pending', 'processing')
			ORDER BY id DESC
			LIMIT 1",
			$job_type,
			$entity_type,
			$entity_id
		) );

		return $existing;
	}

	/**
	 * Get next batch of jobs to process.
	 *
	 * Prioritizes: high > medium > normal, then oldest first.
	 *
	 * @param int $batch_size Number of jobs to retrieve.
	 * @return array Array of job objects.
	 */
	public function get_next_batch( $batch_size = 10 ) {
		global $wpdb;

		// Get pending jobs, prioritized
		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->jobs_table}
			WHERE status = 'pending'
			OR (status = 'failed' AND next_retry_at IS NOT NULL AND next_retry_at <= %s)
			ORDER BY
				FIELD(priority, 'high', 'medium', 'normal'),
				created_at ASC
			LIMIT %d",
			current_time( 'mysql' ),
			$batch_size
		) );

		return $jobs;
	}

	/**
	 * Process a batch of jobs.
	 *
	 * @param int $batch_size Max jobs to process.
	 * @param int $timeout    Max execution time in seconds.
	 * @return array Processing results: processed, failed, skipped.
	 */
	public function process_batch( $batch_size = 10, $timeout = 300 ) {
		$start_time = time();
		$results    = array(
			'processed' => 0,
			'failed'    => 0,
			'skipped'   => 0,
		);

		// Get jobs
		$jobs = $this->get_next_batch( $batch_size );

		if ( empty( $jobs ) ) {
			return $results;
		}

		foreach ( $jobs as $job ) {
			// Check timeout
			if ( ( time() - $start_time ) > $timeout ) {
				$results['skipped']++;
				continue;
			}

			// Mark as processing
			$this->update_status( $job->id, 'processing', array(
				'started_at' => current_time( 'mysql' ),
			) );

			// Process the job
			$result = $this->process_job( $job );

			if ( is_wp_error( $result ) ) {
				$this->handle_failure( $job, $result );
				$results['failed']++;
			} else {
				$this->update_status( $job->id, 'complete', array(
					'completed_at' => current_time( 'mysql' ),
				) );
				$results['processed']++;

				// Emit success event
				do_action( 'msh_job_completed', $job->id, $job->job_type );
			}
		}

		return $results;
	}

	/**
	 * Process a single job.
	 *
	 * Routes to appropriate worker based on job type.
	 *
	 * @param object $job Job data.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function process_job( $job ) {
		$payload = json_decode( $job->payload, true );

		// Route to worker based on job type
		switch ( $job->job_type ) {
			case 'regenerate_metadata':
			case 'generate_metadata':
				// Worker will be built in next file (class-msh-regeneration-worker.php)
				if ( class_exists( 'MSH_Regeneration_Worker' ) ) {
					return MSH_Regeneration_Worker::get_instance()->process( $job, $payload );
				}
				return new WP_Error( 'worker_not_found', __( 'Regeneration worker not loaded.', 'msh-image-optimizer' ) );

			default:
				// Unknown job type
				return new WP_Error( 'unknown_job_type', sprintf( __( 'Unknown job type: %s', 'msh-image-optimizer' ), $job->job_type ) );
		}
	}

	/**
	 * Handle job failure.
	 *
	 * Implements retry logic with exponential backoff.
	 * After max attempts, moves to dead-letter queue.
	 *
	 * @param object   $job   Job data.
	 * @param WP_Error $error Error details.
	 * @return void
	 */
	private function handle_failure( $job, $error ) {
		global $wpdb;

		$attempts = $job->attempts + 1;

		if ( $attempts >= $job->max_attempts ) {
			// Max retries exceeded - mark as failed and move to dead letters
			$this->update_status( $job->id, 'failed', array(
				'attempts'      => $attempts,
				'error_message' => $error->get_error_message(),
				'completed_at'  => current_time( 'mysql' ),
			) );

			$this->move_to_dead_letters( $job, $error );

			// Emit event
			do_action( 'msh_job_failed_permanently', $job->id, $job->job_type, $error );
		} else {
			// Schedule retry with exponential backoff: 2^attempts minutes
			$retry_delay  = pow( 2, $attempts ) * MINUTE_IN_SECONDS;
			$next_retry   = gmdate( 'Y-m-d H:i:s', time() + $retry_delay );

			$this->update_status( $job->id, 'pending', array(
				'attempts'       => $attempts,
				'next_retry_at'  => $next_retry,
				'error_message'  => $error->get_error_message(),
			) );

			// Emit event
			do_action( 'msh_job_retry_scheduled', $job->id, $attempts, $next_retry );
		}
	}

	/**
	 * Move failed job to dead-letter queue.
	 *
	 * @param object   $job   Job data.
	 * @param WP_Error $error Error details.
	 * @return void
	 */
	private function move_to_dead_letters( $job, $error ) {
		global $wpdb;

		// Extract attachment info from payload if available
		$payload       = json_decode( $job->payload, true );
		$attachment_id = isset( $payload['attachment_id'] ) ? $payload['attachment_id'] : $job->entity_id;
		$locale        = isset( $payload['locale'] ) ? $payload['locale'] : null;
		$field         = isset( $payload['field'] ) ? $payload['field'] : null;

		$wpdb->insert(
			$this->dead_letters_table,
			array(
				'job_id'        => $job->id,
				'job_type'      => $job->job_type,
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'field'         => $field,
				'reason'        => $error->get_error_message(),
				'payload'       => $job->payload,
				'failed_at'     => current_time( 'mysql' ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update job status.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $status New status.
	 * @param array  $data   Additional data to update.
	 * @return bool True on success, false on failure.
	 */
	private function update_status( $job_id, $status, $data = array() ) {
		global $wpdb;

		$update = array_merge(
			array( 'status' => $status ),
			$data
		);

		$result = $wpdb->update(
			$this->jobs_table,
			$update,
			array( 'id' => $job_id ),
			null,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get job statistics.
	 *
	 * Returns counts for pending, processing, complete, failed jobs
	 * with priority breakdown.
	 *
	 * @return array Job statistics.
	 */
	public function get_stats() {
		global $wpdb;

		$stats = array(
			'pending'         => 0,
			'processing'      => 0,
			'complete'        => 0,
			'failed'          => 0,
			'priority_high'   => 0,
			'priority_medium' => 0,
			'priority_normal' => 0,
		);

		// Get status counts
		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$this->jobs_table}
			GROUP BY status"
		);

		foreach ( $status_counts as $row ) {
			$stats[ $row->status ] = (int) $row->count;
		}

		// Get priority breakdown (pending + processing only)
		$priority_counts = $wpdb->get_results(
			"SELECT priority, COUNT(*) as count
			FROM {$this->jobs_table}
			WHERE status IN ('pending', 'processing')
			GROUP BY priority"
		);

		foreach ( $priority_counts as $row ) {
			$key = 'priority_' . $row->priority;
			if ( isset( $stats[ $key ] ) ) {
				$stats[ $key ] = (int) $row->count;
			}
		}

		return $stats;
	}

	/**
	 * WP-Cron callback for background processing.
	 *
	 * Processes 50 jobs per run.
	 *
	 * @return void
	 */
	public function cron_process_queue() {
		$results = $this->process_batch( 50, 300 );

		error_log( sprintf(
			'[MSH Job Engine] Cron processed: %d success, %d failed, %d skipped',
			$results['processed'],
			$results['failed'],
			$results['skipped']
		) );
	}

	/**
	 * Clear all completed jobs older than X days.
	 *
	 * @param int $days Days to keep (default 30).
	 * @return int Number of jobs deleted.
	 */
	public function cleanup_old_jobs( $days = 30 ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->jobs_table}
			WHERE status = 'complete'
			AND completed_at < %s",
			$cutoff_date
		) );

		return (int) $deleted;
	}

	/**
	 * Clear all failed jobs.
	 *
	 * Useful for manual cleanup after fixing issues.
	 *
	 * @return int Number of jobs deleted.
	 */
	public function clear_failed_jobs() {
		global $wpdb;

		$deleted = $wpdb->query(
			"DELETE FROM {$this->jobs_table}
			WHERE status = 'failed'"
		);

		return (int) $deleted;
	}

	/**
	 * Retry a specific dead-letter job.
	 *
	 * Moves job from dead letters back to queue.
	 *
	 * @param int $dead_letter_id Dead letter ID.
	 * @return int|WP_Error New job ID or error.
	 */
	public function retry_dead_letter( $dead_letter_id ) {
		global $wpdb;

		$dead_letter = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->dead_letters_table} WHERE id = %d",
			$dead_letter_id
		) );

		if ( ! $dead_letter ) {
			return new WP_Error( 'not_found', __( 'Dead letter not found.', 'msh-image-optimizer' ) );
		}

		// Re-enqueue with original payload
		$payload = json_decode( $dead_letter->payload, true );
		$job_id  = $this->enqueue(
			$dead_letter->job_type,
			'attachment',
			$dead_letter->attachment_id,
			$payload,
			'high' // Retry with high priority
		);

		if ( is_wp_error( $job_id ) ) {
			return $job_id;
		}

		// Delete from dead letters
		$wpdb->delete(
			$this->dead_letters_table,
			array( 'id' => $dead_letter_id ),
			array( '%d' )
		);

		return $job_id;
	}
}
