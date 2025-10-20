<?php
/**
 * Helper Functions for Phase 5+9
 *
 * Public API functions that AI #2 (Frontend) and other components use
 * to interact with Phase 5+9 automation infrastructure.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get translated metadata entries from main metadata table.
 *
 * @param array $args {
 *     Query arguments.
 *
 *     @type int    $media_id  Filter by attachment ID.
 *     @type string $locale    Filter by locale (e.g., 'es_ES', 'fr_FR').
 *     @type string $source    Filter by source: 'ai', 'manual', ''.
 *     @type string $field     Filter by field: 'title', 'alt', 'caption', 'description'.
 *     @type string $search    Search in value field.
 *     @type int    $page      Page number (default 1).
 *     @type int    $per_page  Results per page (default 50).
 * }
 * @return array {
 *     @type array $items       Array of metadata entry objects.
 *     @type int   $total       Total matching entries (before pagination).
 *     @type int   $total_pages Total number of pages.
 * }
 */
function msh_get_metadata_entries( $args = array() ) {
	global $wpdb;

	$metadata_table = $wpdb->prefix . 'msh_optimizer_metadata';

	// Parse args with defaults
	$args = wp_parse_args( $args, array(
		'media_id'  => 0,
		'locale'    => '',
		'source'    => '',
		'field'     => '',
		'search'    => '',
		'page'      => 1,
		'per_page'  => 50,
	) );

	// Build WHERE clause
	$where = array( '1=1' );
	$params = array();

	if ( ! empty( $args['media_id'] ) ) {
		$where[] = 'media_id = %d';
		$params[] = $args['media_id'];
	}

	if ( ! empty( $args['locale'] ) ) {
		$where[] = 'locale = %s';
		$params[] = $args['locale'];
	}

	if ( ! empty( $args['field'] ) ) {
		$where[] = 'field = %s';
		$params[] = $args['field'];
	}

	if ( ! empty( $args['source'] ) ) {
		$where[] = 'source = %s';
		$params[] = $args['source'];
	}

	if ( ! empty( $args['search'] ) ) {
		$where[] = 'value LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
	}

	$where_sql = implode( ' AND ', $where );

	// Get total count
	if ( ! empty( $params ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$metadata_table} WHERE {$where_sql}", $params ) );
	} else {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$metadata_table} WHERE {$where_sql}" );
	}

	// Calculate pagination
	$per_page = max( 1, (int) $args['per_page'] );
	$page = max( 1, (int) $args['page'] );
	$total_pages = ceil( $total / $per_page );
	$offset = ( $page - 1 ) * $per_page;

	// Build final query with LIMIT
	$params[] = $offset;
	$params[] = $per_page;

	if ( count( $params ) > 2 ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$metadata_table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d, %d", $params ) );
	} else {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$metadata_table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d, %d", $offset, $per_page ) );
	}

	return array(
		'items'       => $items,
		'total'       => $total,
		'total_pages' => $total_pages,
	);
}

/**
 * Get metadata cache entries with filters (Phase 3 staleness tracking).
 *
 * @param array $args {
 *     Query arguments.
 *
 *     @type int    $attachment_id Filter by attachment ID.
 *     @type string $locale        Filter by locale (e.g., 'es_ES').
 *     @type string $staleness     Filter by staleness: 'stale', 'fresh', ''.
 *     @type string $source        Filter by source: 'ai', 'manual', ''.
 *     @type string $field         Filter by field: 'title', 'alt_text', etc.
 *     @type string $search        Search in ai_value or manual_value.
 *     @type int    $page          Page number (default 1).
 *     @type int    $per_page      Results per page (default 50).
 * }
 * @return array {
 *     @type array $items       Array of cache entry objects.
 *     @type int   $total       Total matching entries (before pagination).
 *     @type int   $total_pages Total number of pages.
 * }
 */
function msh_get_cache_entries( $args = array() ) {
	global $wpdb;

	$cache_table = $wpdb->prefix . 'optimizer_metadata_cache';

	// Parse args with defaults
	$args = wp_parse_args( $args, array(
		'attachment_id' => 0,
		'locale'        => '',
		'staleness'     => '',
		'source'        => '',
		'field'         => '',
		'search'        => '',
		'page'          => 1,
		'per_page'      => 50,
	) );

	// Build WHERE clause
	$where = array( '1=1' );
	$params = array();

	if ( ! empty( $args['attachment_id'] ) ) {
		$where[] = 'attachment_id = %d';
		$params[] = $args['attachment_id'];
	}

	if ( ! empty( $args['locale'] ) ) {
		$where[] = 'locale = %s';
		$params[] = $args['locale'];
	}

	if ( ! empty( $args['field'] ) ) {
		$where[] = 'field = %s';
		$params[] = $args['field'];
	}

	if ( ! empty( $args['source'] ) ) {
		$where[] = 'chosen_source = %s';
		$params[] = $args['source'];
	}

	if ( ! empty( $args['staleness'] ) ) {
		if ( 'stale' === $args['staleness'] ) {
			$where[] = "stale_reason IS NOT NULL AND stale_reason != ''";
		} elseif ( 'fresh' === $args['staleness'] ) {
			$where[] = "(stale_reason IS NULL OR stale_reason = '')";
		}
	}

	if ( ! empty( $args['search'] ) ) {
		$where[] = '(ai_value LIKE %s OR manual_value LIKE %s)';
		$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$params[] = $search_term;
		$params[] = $search_term;
	}

	$where_clause = implode( ' AND ', $where );

	// Count total
	$count_query = "SELECT COUNT(*) FROM {$cache_table} WHERE {$where_clause}";
	if ( ! empty( $params ) ) {
		$count_query = $wpdb->prepare( $count_query, $params );
	}
	$total = (int) $wpdb->get_var( $count_query );

	// Calculate pagination
	$page = max( 1, (int) $args['page'] );
	$per_page = max( 1, (int) $args['per_page'] );
	$total_pages = max( 1, ceil( $total / $per_page ) );
	$offset = ( $page - 1 ) * $per_page;

	// Get results
	$query = "SELECT * FROM {$cache_table} WHERE {$where_clause} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
	$query_params = array_merge( $params, array( $per_page, $offset ) );
	$query = $wpdb->prepare( $query, $query_params );
	$items = $wpdb->get_results( $query );

	return array(
		'items'       => $items,
		'total'       => $total,
		'total_pages' => $total_pages,
	);
}

/**
 * Get job queue statistics.
 *
 * @return array {
 *     @type int $pending      Jobs with status 'pending'.
 *     @type int $processing   Jobs with status 'processing'.
 *     @type int $complete     Jobs with status 'complete'.
 *     @type int $failed       Jobs with status 'failed'.
 *     @type int $high_priority    High priority jobs (pending + processing).
 *     @type int $medium_priority  Medium priority jobs (pending + processing).
 *     @type int $normal_priority  Normal priority jobs (pending + processing).
 * }
 */
function msh_get_job_stats() {
	global $wpdb;

	$jobs_table = $wpdb->prefix . 'msh_jobs';

	// Count by status
	$pending    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'pending'" );
	$processing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'processing'" );
	$complete   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'complete'" );
	$failed     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'failed'" );

	// Count by priority (only pending/processing)
	$high_priority   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE priority = 'high' AND status IN ('pending', 'processing')" );
	$medium_priority = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE priority = 'medium' AND status IN ('pending', 'processing')" );
	$normal_priority = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE priority = 'normal' AND status IN ('pending', 'processing')" );

	return array(
		'pending'         => $pending,
		'processing'      => $processing,
		'complete'        => $complete,
		'failed'          => $failed,
		'high_priority'   => $high_priority,
		'medium_priority' => $medium_priority,
		'normal_priority' => $normal_priority,
	);
}

/**
 * Get recent jobs for display in Queue tab.
 *
 * @param int $limit Number of recent jobs to fetch (default 10).
 * @return array Array of job objects.
 */
function msh_get_recent_jobs( $limit = 10 ) {
	global $wpdb;

	$jobs_table = $wpdb->prefix . 'msh_jobs';
	$limit = max( 1, (int) $limit );

	$jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$jobs_table}
			ORDER BY created_at DESC
			LIMIT %d",
			$limit
		)
	);

	return $jobs;
}

/**
 * Enqueue a job manually.
 *
 * Wrapper for MSH_Job_Engine::enqueue() with simpler signature.
 *
 * @param string $job_type    Job type (e.g., 'regenerate_metadata').
 * @param int    $attachment_id Attachment ID.
 * @param array  $options     Additional options (locale, fields, priority).
 * @return int|WP_Error Job ID on success, WP_Error on failure.
 */
function msh_enqueue_job( $job_type, $attachment_id, $options = array() ) {
	if ( ! class_exists( 'MSH_Job_Engine' ) ) {
		return new WP_Error( 'job_engine_missing', __( 'Job engine not available.', 'msh-image-optimizer' ) );
	}

	$defaults = array(
		'locale'   => get_locale(),
		'fields'   => array( 'title', 'alt_text', 'caption', 'description' ),
		'priority' => 'normal',
		'reason'   => 'manual_request',
	);

	$options = wp_parse_args( $options, $defaults );

	$job_engine = MSH_Job_Engine::get_instance();

	return $job_engine->enqueue(
		$job_type,
		'attachment',
		$attachment_id,
		array(
			'locale' => $options['locale'],
			'fields' => $options['fields'],
			'reason' => $options['reason'],
		),
		$options['priority']
	);
}

/**
 * Check if Pro license is active.
 *
 * STUB: Always returns false until licensing system is built.
 *
 * @return bool True if Pro license active, false otherwise.
 */
function msh_is_pro_active() {
	// TODO: Implement license checking in Phase 6
	return apply_filters( 'msh_is_pro_active', false );
}

/**
 * Log telemetry event (anonymous usage tracking).
 *
 * STUB: Does nothing until telemetry system is built.
 *
 * @param string $event_type Event type (e.g., 'job_completed', 'cache_hit').
 * @param array  $data       Event data.
 * @return void
 */
function msh_telemetry( $event_type, $data = array() ) {
	// TODO: Implement telemetry in Phase 6
	do_action( 'msh_telemetry_event', $event_type, $data );
}

/**
 * Get cache statistics summary.
 *
 * @return array {
 *     @type int $total_entries  Total cache entries.
 *     @type int $stale_entries  Stale entries.
 *     @type int $ai_entries     AI-generated entries.
 *     @type int $manual_entries Manual entries.
 * }
 */
function msh_get_cache_stats() {
	global $wpdb;

	$cache_table = $wpdb->prefix . 'optimizer_metadata_cache';

	$total_entries  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cache_table}" );
	$stale_entries  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cache_table} WHERE stale_reason IS NOT NULL AND stale_reason != ''" );
	$ai_entries     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cache_table} WHERE chosen_source = 'ai'" );
	$manual_entries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cache_table} WHERE chosen_source = 'manual'" );

	return array(
		'total_entries'  => $total_entries,
		'stale_entries'  => $stale_entries,
		'ai_entries'     => $ai_entries,
		'manual_entries' => $manual_entries,
	);
}

/**
 * Clear failed jobs from queue.
 *
 * @return int Number of jobs deleted.
 */
function msh_clear_failed_jobs() {
	global $wpdb;

	$jobs_table = $wpdb->prefix . 'msh_jobs';

	$deleted = $wpdb->delete( $jobs_table, array( 'status' => 'failed' ), array( '%s' ) );

	do_action( 'msh_failed_jobs_cleared', $deleted );

	return (int) $deleted;
}

/**
 * Get recent events for Events tab.
 *
 * Events are logged actions from the automation system (job completions,
 * cache updates, errors, etc.).
 *
 * @param int $limit Number of events to fetch (default 20).
 * @return array Array of event objects with timestamp, type, message, severity.
 */
function msh_get_recent_events( $limit = 20 ) {
	// For now, generate events from recent jobs and cache updates
	// In Phase 6, this will query a dedicated msh_events table
	global $wpdb;

	$jobs_table = $wpdb->prefix . 'msh_jobs';
	$cache_table = $wpdb->prefix . 'optimizer_metadata_cache';
	$limit = max( 1, (int) $limit );

	$events = array();

	// Get recent completed jobs
	$recent_jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, job_type, entity_id, status, updated_at
			FROM {$jobs_table}
			WHERE status IN ('complete', 'failed')
			ORDER BY updated_at DESC
			LIMIT %d",
			$limit
		)
	);

	foreach ( $recent_jobs as $job ) {
		$severity = ( 'complete' === $job->status ) ? 'success' : 'error';
		$action = ( 'regenerate_metadata' === $job->job_type ) ? 'Regenerated' : 'Generated';
		$message = sprintf(
			'%s metadata for attachment #%d',
			$action,
			$job->entity_id
		);

		$events[] = (object) array(
			'id'        => 'job_' . $job->id,
			'timestamp' => $job->updated_at,
			'type'      => $job->job_type,
			'message'   => $message,
			'severity'  => $severity,
			'entity_id' => $job->entity_id,
		);
	}

	// Get recent cache updates
	$recent_cache = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, attachment_id, locale, field, chosen_source, updated_at
			FROM {$cache_table}
			ORDER BY updated_at DESC
			LIMIT %d",
			ceil( $limit / 2 )
		)
	);

	foreach ( $recent_cache as $cache ) {
		$source = ( 'ai' === $cache->chosen_source ) ? 'AI-generated' : 'Manual';
		$message = sprintf(
			'%s %s metadata for attachment #%d (%s)',
			$source,
			$cache->field,
			$cache->attachment_id,
			$cache->locale
		);

		$events[] = (object) array(
			'id'        => 'cache_' . $cache->id,
			'timestamp' => $cache->updated_at,
			'type'      => 'cache_update',
			'message'   => $message,
			'severity'  => 'info',
			'entity_id' => $cache->attachment_id,
		);
	}

	// Sort by timestamp descending
	usort( $events, function( $a, $b ) {
		return strcmp( $b->timestamp, $a->timestamp );
	} );

	// Return only requested limit
	return array_slice( $events, 0, $limit );
}
