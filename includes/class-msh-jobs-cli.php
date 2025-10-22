<?php
/**
 * WP-CLI Commands for Job Queue Management
 *
 * Provides command-line interface for managing the job queue system.
 *
 * Usage:
 *   wp msh jobs status
 *   wp msh jobs process --batch=10
 *   wp msh jobs retry <job-id>
 *   wp msh jobs clear --status=failed
 *   wp msh jobs list --status=pending --limit=50
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Jobs_CLI
 *
 * WP-CLI commands for job queue management.
 */
class MSH_Jobs_CLI {

	/**
	 * Display job queue status and statistics.
	 *
	 * Shows counts by status and priority, recent activity, and health metrics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh jobs status
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		$stats = msh_get_job_stats();

		if ( empty( $stats ) ) {
			WP_CLI::error( 'Unable to retrieve job statistics.' );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G=== Job Queue Status ===%n' ) );
		WP_CLI::line( '' );

		// Status breakdown
		WP_CLI::line( WP_CLI::colorize( '%YStatus Breakdown:%n' ) );
		WP_CLI::line( sprintf( '  Pending:    %s', WP_CLI::colorize( '%G' . number_format( $stats['pending'] ) . '%n' ) ) );
		WP_CLI::line( sprintf( '  Processing: %s', WP_CLI::colorize( '%B' . number_format( $stats['processing'] ) . '%n' ) ) );
		WP_CLI::line( sprintf( '  Complete:   %s', WP_CLI::colorize( '%C' . number_format( $stats['complete'] ) . '%n' ) ) );
		WP_CLI::line( sprintf( '  Failed:     %s', $stats['failed'] > 0 ? WP_CLI::colorize( '%R' . number_format( $stats['failed'] ) . '%n' ) : number_format( $stats['failed'] ) ) );

		WP_CLI::line( '' );

		// Priority breakdown (only pending/processing)
		$total_queue = $stats['pending'] + $stats['processing'];
		if ( $total_queue > 0 ) {
			WP_CLI::line( WP_CLI::colorize( '%YPriority Breakdown:%n' ) );
			WP_CLI::line( sprintf( '  High:   %s', WP_CLI::colorize( '%R' . number_format( $stats['high_priority'] ) . '%n' ) ) );
			WP_CLI::line( sprintf( '  Medium: %s', WP_CLI::colorize( '%Y' . number_format( $stats['medium_priority'] ) . '%n' ) ) );
			WP_CLI::line( sprintf( '  Normal: %s', WP_CLI::colorize( '%G' . number_format( $stats['normal_priority'] ) . '%n' ) ) );
			WP_CLI::line( '' );
		}

		// Health check
		$health_status = $stats['failed'] > 50 ? 'WARNING' : 'HEALTHY';
		$health_color  = $stats['failed'] > 50 ? '%R' : '%G';
		WP_CLI::line( sprintf( 'Health: %s', WP_CLI::colorize( $health_color . $health_status . '%n' ) ) );

		if ( $stats['failed'] > 50 ) {
			WP_CLI::warning( sprintf( '%d failed jobs detected. Consider running: wp msh jobs clear --status=failed', $stats['failed'] ) );
		}

		WP_CLI::line( '' );
	}

	/**
	 * Process jobs from the queue.
	 *
	 * Processes a batch of pending jobs. Uses priority ordering
	 * (high > medium > normal).
	 *
	 * ## OPTIONS
	 *
	 * [--batch=<number>]
	 * : Number of jobs to process in this batch.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--priority=<level>]
	 * : Only process jobs with this priority (high, medium, normal).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh jobs process
	 *     wp msh jobs process --batch=50
	 *     wp msh jobs process --priority=high --batch=20
	 *
	 * @when after_wp_load
	 */
	public function process( $args, $assoc_args ) {
		$batch_size = isset( $assoc_args['batch'] ) ? absint( $assoc_args['batch'] ) : 10;
		$priority   = isset( $assoc_args['priority'] ) ? sanitize_text_field( $assoc_args['priority'] ) : null;

		if ( $batch_size < 1 ) {
			WP_CLI::error( 'Batch size must be at least 1.' );
			return;
		}

		if ( $priority && ! in_array( $priority, array( 'high', 'medium', 'normal' ), true ) ) {
			WP_CLI::error( 'Priority must be one of: high, medium, normal' );
			return;
		}

		WP_CLI::line( sprintf( 'Processing up to %d job(s)...', $batch_size ) );
		WP_CLI::line( '' );

		if ( ! function_exists( 'msh_process_queue' ) ) {
			WP_CLI::error( 'Queue processing function not available. Check plugin initialization.' );
			return;
		}

		$result = msh_process_queue( $batch_size, $priority );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		$processed = isset( $result['processed'] ) ? $result['processed'] : 0;
		$failed    = isset( $result['failed'] ) ? $result['failed'] : 0;
		$skipped   = isset( $result['skipped'] ) ? $result['skipped'] : 0;

		WP_CLI::success( sprintf( 'Processed: %d | Failed: %d | Skipped: %d', $processed, $failed, $skipped ) );

		if ( $failed > 0 ) {
			WP_CLI::warning( sprintf( '%d job(s) failed. Check logs or run: wp msh jobs list --status=failed', $failed ) );
		}
	}

	/**
	 * Retry a specific failed job.
	 *
	 * Resets attempt count and marks job as pending for retry.
	 *
	 * ## OPTIONS
	 *
	 * <job-id>
	 * : The job ID to retry.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh jobs retry 123
	 *
	 * @when after_wp_load
	 */
	public function retry( $args, $assoc_args ) {
		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Job ID is required. Usage: wp msh jobs retry <job-id>' );
			return;
		}

		$job_id = absint( $args[0] );

		if ( $job_id < 1 ) {
			WP_CLI::error( 'Invalid job ID.' );
			return;
		}

		global $wpdb;
		$jobs_table = $wpdb->prefix . 'msh_jobs';

		// Check if job exists and is failed
		$job = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, attempts, error_message FROM {$jobs_table} WHERE id = %d", $job_id ) );

		if ( ! $job ) {
			WP_CLI::error( sprintf( 'Job #%d not found.', $job_id ) );
			return;
		}

		if ( 'failed' !== $job->status ) {
			WP_CLI::error( sprintf( 'Job #%d is not in failed status (current: %s). Only failed jobs can be retried.', $job_id, $job->status ) );
			return;
		}

		// Reset job for retry
		$updated = $wpdb->update(
			$jobs_table,
			array(
				'status'         => 'pending',
				'attempts'       => 0,
				'next_retry_at'  => null,
				'error_message'  => null,
				'completed_at'   => null,
			),
			array( 'id' => $job_id ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			WP_CLI::error( sprintf( 'Failed to reset job #%d. Database error.', $job_id ) );
			return;
		}

		WP_CLI::success( sprintf( 'Job #%d has been reset to pending status and is ready for retry.', $job_id ) );
		WP_CLI::line( '' );
		WP_CLI::line( 'Run the following to process it:' );
		WP_CLI::line( WP_CLI::colorize( '%Gwp msh jobs process --batch=1%n' ) );
	}

	/**
	 * List jobs with optional filtering.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by job status (pending, processing, complete, failed).
	 *
	 * [--priority=<level>]
	 * : Filter by priority (high, medium, normal).
	 *
	 * [--limit=<number>]
	 * : Maximum number of jobs to display.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format (table, csv, json, yaml).
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh jobs list
	 *     wp msh jobs list --status=failed --limit=20
	 *     wp msh jobs list --priority=high --format=json
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ) {
		global $wpdb;
		$jobs_table = $wpdb->prefix . 'msh_jobs';

		$status   = isset( $assoc_args['status'] ) ? sanitize_text_field( $assoc_args['status'] ) : null;
		$priority = isset( $assoc_args['priority'] ) ? sanitize_text_field( $assoc_args['priority'] ) : null;
		$limit    = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 50;
		$format   = isset( $assoc_args['format'] ) ? sanitize_text_field( $assoc_args['format'] ) : 'table';

		// Build query
		$where_clauses = array( '1=1' );
		$query_args    = array();

		if ( $status ) {
			if ( ! in_array( $status, array( 'pending', 'processing', 'complete', 'failed' ), true ) ) {
				WP_CLI::error( 'Status must be one of: pending, processing, complete, failed' );
				return;
			}
			$where_clauses[] = 'status = %s';
			$query_args[]    = $status;
		}

		if ( $priority ) {
			if ( ! in_array( $priority, array( 'high', 'medium', 'normal' ), true ) ) {
				WP_CLI::error( 'Priority must be one of: high, medium, normal' );
				return;
			}
			$where_clauses[] = 'priority = %s';
			$query_args[]    = $priority;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		$query = "SELECT
			id,
			job_type,
			entity_type,
			entity_id,
			priority,
			status,
			attempts,
			max_attempts,
			created_at,
			started_at,
			completed_at,
			error_message
		FROM {$jobs_table}
		WHERE {$where_sql}
		ORDER BY
			CASE priority
				WHEN 'high' THEN 1
				WHEN 'medium' THEN 2
				WHEN 'normal' THEN 3
			END,
			created_at DESC
		LIMIT %d";

		$query_args[] = $limit;

		if ( ! empty( $query_args ) ) {
			$query = $wpdb->prepare( $query, $query_args );
		}

		$jobs = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $jobs ) ) {
			WP_CLI::line( 'No jobs found matching the criteria.' );
			return;
		}

		// Format error messages for display
		foreach ( $jobs as &$job ) {
			if ( ! empty( $job['error_message'] ) && strlen( $job['error_message'] ) > 50 ) {
				$job['error_message'] = substr( $job['error_message'], 0, 47 ) . '...';
			}
		}

		WP_CLI\Utils\format_items( $format, $jobs, array( 'id', 'job_type', 'entity_id', 'priority', 'status', 'attempts', 'created_at', 'error_message' ) );
	}

	/**
	 * Clear jobs from the queue.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Clear jobs with this status (complete, failed). Required.
	 *
	 * [--older-than=<days>]
	 * : Only clear jobs older than this many days.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh jobs clear --status=failed --yes
	 *     wp msh jobs clear --status=complete --older-than=30 --yes
	 *
	 * @when after_wp_load
	 */
	public function clear( $args, $assoc_args ) {
		global $wpdb;
		$jobs_table = $wpdb->prefix . 'msh_jobs';

		$status     = isset( $assoc_args['status'] ) ? sanitize_text_field( $assoc_args['status'] ) : null;
		$older_than = isset( $assoc_args['older-than'] ) ? absint( $assoc_args['older-than'] ) : null;
		$skip_confirm = isset( $assoc_args['yes'] ) && $assoc_args['yes'];

		if ( ! $status ) {
			WP_CLI::error( 'Status parameter is required. Usage: wp msh jobs clear --status=<status>' );
			return;
		}

		if ( ! in_array( $status, array( 'complete', 'failed' ), true ) ) {
			WP_CLI::error( 'Can only clear "complete" or "failed" jobs. Pending/processing jobs cannot be cleared.' );
			return;
		}

		// Build delete query
		$where_clauses = array( 'status = %s' );
		$query_args    = array( $status );

		if ( $older_than ) {
			$where_clauses[] = 'created_at < DATE_SUB(NOW(), INTERVAL %d DAY)';
			$query_args[]    = $older_than;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Count jobs to be deleted
		$count_query = "SELECT COUNT(*) FROM {$jobs_table} WHERE {$where_sql}";
		$count       = $wpdb->get_var( $wpdb->prepare( $count_query, $query_args ) );

		if ( $count < 1 ) {
			WP_CLI::line( 'No jobs found matching the criteria.' );
			return;
		}

		// Confirm deletion
		if ( ! $skip_confirm ) {
			WP_CLI::confirm( sprintf( 'Are you sure you want to delete %d job(s) with status "%s"?', $count, $status ), $assoc_args );
		}

		// Delete jobs
		$delete_query = "DELETE FROM {$jobs_table} WHERE {$where_sql}";
		$deleted      = $wpdb->query( $wpdb->prepare( $delete_query, $query_args ) );

		if ( false === $deleted ) {
			WP_CLI::error( 'Failed to delete jobs. Database error.' );
			return;
		}

		WP_CLI::success( sprintf( 'Deleted %d job(s) with status "%s".', $deleted, $status ) );
	}
}
