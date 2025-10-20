<?php
/**
 * Queue Manager - Queue Monitoring and Maintenance
 *
 * Provides monitoring, cleanup, and management utilities for the job queue.
 * Handles WP-Cron scheduling, health checks, and queue optimization.
 *
 * Features:
 * - Queue health monitoring
 * - Automatic cleanup of old jobs
 * - Stuck job detection and recovery
 * - Queue statistics and reporting
 * - WP-Cron schedule management
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Queue_Manager
 *
 * Monitors and maintains the job queue health.
 */
class MSH_Queue_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Queue_Manager|null
	 */
	private static $instance = null;

	/**
	 * Cron hook name for queue processing.
	 *
	 * @var string
	 */
	const CRON_HOOK_PROCESS = 'msh_process_job_queue';

	/**
	 * Cron hook name for queue cleanup.
	 *
	 * @var string
	 */
	const CRON_HOOK_CLEANUP = 'msh_cleanup_job_queue';

	/**
	 * Cron hook name for stuck job detection.
	 *
	 * @var string
	 */
	const CRON_HOOK_STUCK_JOBS = 'msh_detect_stuck_jobs';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Queue_Manager
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
		// Schedule cron jobs on activation
		add_action( 'init', array( $this, 'schedule_cron_jobs' ) );

		// Hook cron handlers
		add_action( self::CRON_HOOK_PROCESS, array( $this, 'cron_process_handler' ) );
		add_action( self::CRON_HOOK_CLEANUP, array( $this, 'cron_cleanup_handler' ) );
		add_action( self::CRON_HOOK_STUCK_JOBS, array( $this, 'cron_stuck_jobs_handler' ) );
	}

	/**
	 * Schedule WP-Cron jobs.
	 *
	 * Runs on every init to ensure cron jobs are scheduled.
	 *
	 * @return void
	 */
	public function schedule_cron_jobs() {
		// Process queue every 5 minutes
		if ( ! wp_next_scheduled( self::CRON_HOOK_PROCESS ) ) {
			wp_schedule_event( time(), 'msh_every_5_minutes', self::CRON_HOOK_PROCESS );
		}

		// Cleanup old jobs daily
		if ( ! wp_next_scheduled( self::CRON_HOOK_CLEANUP ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK_CLEANUP );
		}

		// Detect stuck jobs every hour
		if ( ! wp_next_scheduled( self::CRON_HOOK_STUCK_JOBS ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK_STUCK_JOBS );
		}
	}

	/**
	 * Add custom cron schedule (5 minutes).
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['msh_every_5_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes', 'msh-image-optimizer' ),
		);

		return $schedules;
	}

	/**
	 * WP-Cron handler: Process queue.
	 *
	 * @return void
	 */
	public function cron_process_handler() {
		if ( ! class_exists( 'MSH_Job_Engine' ) ) {
			return;
		}

		$engine  = MSH_Job_Engine::get_instance();
		$results = $engine->process_batch( 50, 300 );

		// Log if there were failures
		if ( $results['failed'] > 0 ) {
			error_log( sprintf(
				'[MSH Queue Manager] Cron processed: %d success, %d failed, %d skipped',
				$results['processed'],
				$results['failed'],
				$results['skipped']
			) );
		}
	}

	/**
	 * WP-Cron handler: Cleanup old jobs.
	 *
	 * @return void
	 */
	public function cron_cleanup_handler() {
		if ( ! class_exists( 'MSH_Job_Engine' ) ) {
			return;
		}

		$engine  = MSH_Job_Engine::get_instance();
		$deleted = $engine->cleanup_old_jobs( 30 ); // Keep 30 days

		if ( $deleted > 0 ) {
			error_log( sprintf(
				'[MSH Queue Manager] Cleanup removed %d old completed jobs',
				$deleted
			) );
		}
	}

	/**
	 * WP-Cron handler: Detect and recover stuck jobs.
	 *
	 * Jobs stuck in 'processing' state for > 1 hour are reset to pending.
	 *
	 * @return void
	 */
	public function cron_stuck_jobs_handler() {
		$stuck_jobs = $this->find_stuck_jobs( 60 ); // 60 minutes

		if ( empty( $stuck_jobs ) ) {
			return;
		}

		$recovered = $this->recover_stuck_jobs( $stuck_jobs );

		if ( $recovered > 0 ) {
			error_log( sprintf(
				'[MSH Queue Manager] Recovered %d stuck jobs',
				$recovered
			) );
		}
	}

	/**
	 * Find stuck jobs.
	 *
	 * Jobs that have been in 'processing' state for too long.
	 *
	 * @param int $minutes Minutes in processing state to consider stuck.
	 * @return array Array of stuck job IDs.
	 */
	public function find_stuck_jobs( $minutes = 60 ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'msh_jobs';
		$cutoff_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$minutes} minutes" ) );

		$stuck_jobs = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$table}
			WHERE status = 'processing'
			AND started_at < %s",
			$cutoff_time
		) );

		return array_map( 'intval', $stuck_jobs );
	}

	/**
	 * Recover stuck jobs.
	 *
	 * Resets stuck jobs to 'pending' status so they can be retried.
	 *
	 * @param array $job_ids Array of job IDs to recover.
	 * @return int Number of jobs recovered.
	 */
	public function recover_stuck_jobs( $job_ids ) {
		if ( empty( $job_ids ) ) {
			return 0;
		}

		global $wpdb;

		$table       = $wpdb->prefix . 'msh_jobs';
		$job_ids_str = implode( ',', array_map( 'absint', $job_ids ) );

		// Reset to pending with error message
		$updated = $wpdb->query(
			"UPDATE {$table}
			SET status = 'pending',
			    started_at = NULL,
			    error_message = 'Recovered from stuck state',
			    attempts = attempts + 1
			WHERE id IN ({$job_ids_str})" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Emit event
		foreach ( $job_ids as $job_id ) {
			do_action( 'msh_job_recovered', $job_id );
		}

		return (int) $updated;
	}

	/**
	 * Get queue health status.
	 *
	 * Returns health metrics and warnings.
	 *
	 * @return array Health status with metrics and warnings.
	 */
	public function get_queue_health() {
		global $wpdb;

		$table = $wpdb->prefix . 'msh_jobs';

		// Get basic stats
		if ( class_exists( 'MSH_Job_Engine' ) ) {
			$stats = MSH_Job_Engine::get_instance()->get_stats();
		} else {
			$stats = array(
				'pending'    => 0,
				'processing' => 0,
				'complete'   => 0,
				'failed'     => 0,
			);
		}

		// Find stuck jobs
		$stuck_jobs = $this->find_stuck_jobs( 60 );

		// Calculate queue backlog (pending + processing)
		$backlog = $stats['pending'] + $stats['processing'];

		// Determine health status
		$health_status = 'healthy';
		$warnings      = array();

		if ( $backlog > 1000 ) {
			$health_status = 'warning';
			$warnings[]    = sprintf( __( 'Large queue backlog: %d jobs pending', 'msh-image-optimizer' ), $backlog );
		}

		if ( $stats['failed'] > 100 ) {
			$health_status = 'warning';
			$warnings[]    = sprintf( __( 'High failure rate: %d failed jobs', 'msh-image-optimizer' ), $stats['failed'] );
		}

		if ( count( $stuck_jobs ) > 0 ) {
			$health_status = 'critical';
			$warnings[]    = sprintf( __( 'Stuck jobs detected: %d jobs', 'msh-image-optimizer' ), count( $stuck_jobs ) );
		}

		// Check if cron is running
		$last_cron = get_option( 'msh_last_cron_run', 0 );
		$time_since_cron = time() - $last_cron;

		if ( $time_since_cron > 600 ) { // 10 minutes
			$health_status = 'critical';
			$warnings[]    = __( 'WP-Cron appears to be stalled (no runs in 10+ minutes)', 'msh-image-optimizer' );
		}

		return array(
			'status'     => $health_status,
			'stats'      => $stats,
			'backlog'    => $backlog,
			'stuck_jobs' => count( $stuck_jobs ),
			'warnings'   => $warnings,
			'last_cron'  => $last_cron,
		);
	}

	/**
	 * Get detailed queue analytics.
	 *
	 * Returns in-depth metrics for monitoring dashboards.
	 *
	 * @return array Queue analytics.
	 */
	public function get_queue_analytics() {
		global $wpdb;

		$table = $wpdb->prefix . 'msh_jobs';

		// Average processing time
		$avg_processing_time = $wpdb->get_var(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at))
			FROM {$table}
			WHERE status = 'complete'
			AND completed_at IS NOT NULL
			AND started_at IS NOT NULL"
		);

		// Jobs processed today
		$today_start = gmdate( 'Y-m-d 00:00:00' );
		$jobs_today  = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			WHERE status = 'complete'
			AND completed_at >= %s",
			$today_start
		) );

		// Jobs by type (top 5)
		$jobs_by_type = $wpdb->get_results(
			"SELECT job_type, COUNT(*) as count
			FROM {$table}
			WHERE status IN ('pending', 'processing')
			GROUP BY job_type
			ORDER BY count DESC
			LIMIT 5",
			ARRAY_A
		);

		// Failure rate
		$total_processed = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}
			WHERE status IN ('complete', 'failed')"
		);

		$total_failed = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}
			WHERE status = 'failed'"
		);

		$failure_rate = $total_processed > 0 ? ( $total_failed / $total_processed ) * 100 : 0;

		return array(
			'avg_processing_time' => round( (float) $avg_processing_time, 2 ),
			'jobs_today'          => (int) $jobs_today,
			'jobs_by_type'        => $jobs_by_type,
			'failure_rate'        => round( $failure_rate, 2 ),
			'total_processed'     => (int) $total_processed,
		);
	}

	/**
	 * Pause queue processing.
	 *
	 * Useful for maintenance or debugging.
	 *
	 * @return bool True on success.
	 */
	public function pause_queue() {
		update_option( 'msh_queue_paused', true );
		do_action( 'msh_queue_paused' );

		return true;
	}

	/**
	 * Resume queue processing.
	 *
	 * @return bool True on success.
	 */
	public function resume_queue() {
		delete_option( 'msh_queue_paused' );
		do_action( 'msh_queue_resumed' );

		return true;
	}

	/**
	 * Check if queue is paused.
	 *
	 * @return bool True if paused.
	 */
	public function is_queue_paused() {
		return (bool) get_option( 'msh_queue_paused', false );
	}

	/**
	 * Purge all pending jobs.
	 *
	 * ⚠️ DESTRUCTIVE: Use with caution!
	 *
	 * @param bool $confirm Must be true to confirm.
	 * @return int|WP_Error Number of jobs deleted or error.
	 */
	public function purge_pending_jobs( $confirm = false ) {
		if ( ! $confirm ) {
			return new WP_Error( 'confirmation_required', __( 'Purge requires confirmation.', 'msh-image-optimizer' ) );
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'msh_jobs';
		$deleted = $wpdb->query(
			"DELETE FROM {$table}
			WHERE status = 'pending'"
		);

		do_action( 'msh_queue_purged', $deleted );

		return (int) $deleted;
	}

	/**
	 * Get recent job errors for debugging.
	 *
	 * @param int $limit Number of errors to retrieve.
	 * @return array Recent errors.
	 */
	public function get_recent_errors( $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'msh_jobs';

		$errors = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, job_type, entity_id, error_message, created_at, attempts
			FROM {$table}
			WHERE status = 'failed'
			AND error_message IS NOT NULL
			ORDER BY created_at DESC
			LIMIT %d",
			$limit
		), ARRAY_A );

		return $errors;
	}

	/**
	 * Unschedule all cron jobs.
	 *
	 * Called on plugin deactivation.
	 *
	 * @return void
	 */
	public function unschedule_cron_jobs() {
		wp_clear_scheduled_hook( self::CRON_HOOK_PROCESS );
		wp_clear_scheduled_hook( self::CRON_HOOK_CLEANUP );
		wp_clear_scheduled_hook( self::CRON_HOOK_STUCK_JOBS );
	}

	/**
	 * Update last cron run timestamp.
	 *
	 * Called at the start of each cron run for health monitoring.
	 *
	 * @return void
	 */
	public function update_last_cron_run() {
		update_option( 'msh_last_cron_run', time() );
	}
}

// Add custom cron schedule
add_filter( 'cron_schedules', function( $schedules ) {
	$schedules['msh_every_5_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 5 Minutes', 'msh-image-optimizer' ),
	);
	return $schedules;
} );
