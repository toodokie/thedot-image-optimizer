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
	$cache_table    = $wpdb->prefix . 'msh_metadata_cache';

	$args = wp_parse_args( $args, array(
		'media_id'  => 0,
		'locale'    => '',
		'source'    => '',
		'field'     => '',
		'status'    => '',
		'search'    => '',
		'page'      => 1,
		'per_page'  => 50,
	) );

	$where  = array( '1=1' );
	$params = array();

	if ( ! empty( $args['media_id'] ) ) {
		$where[]  = 'm.media_id = %d';
		$params[] = $args['media_id'];
	}

	if ( ! empty( $args['locale'] ) ) {
		$where[]  = 'm.locale = %s';
		$params[] = $args['locale'];
	}

	if ( ! empty( $args['field'] ) ) {
		$where[]  = 'm.field = %s';
		$params[] = $args['field'];
	}

	if ( ! empty( $args['source'] ) ) {
		$where[]  = 'm.source = %s';
		$params[] = $args['source'];
	}

	if ( ! empty( $args['search'] ) ) {
		$where[]  = 'm.value LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
	}

	$status_case = "CASE\n\tWHEN c.id IS NULL THEN 'missing_cache'\n\tWHEN c.input_fingerprint IS NOT NULL AND c.input_fingerprint <> m.checksum THEN 'needs_regen'\n\tWHEN c.ai_model IS NOT NULL AND c.ai_model <> m.version THEN 'outdated_model'\n\tWHEN m.source = 'manual' OR m.approved_by IS NOT NULL THEN 'locked'\n\tELSE 'fresh'\nEND";

	$where_sql = implode( ' AND ', $where );

	if ( ! empty( $args['status'] ) ) {
		switch ( $args['status'] ) {
			case 'locked':
				$where_sql .= " AND ( m.source = 'manual' OR m.approved_by IS NOT NULL )";
				break;
			case 'missing_cache':
				$where_sql .= ' AND c.id IS NULL';
				break;
			case 'needs_regen':
				$where_sql .= ' AND c.id IS NOT NULL AND c.input_fingerprint IS NOT NULL AND c.input_fingerprint <> m.checksum';
				break;
			case 'outdated_model':
				$where_sql .= ' AND c.id IS NOT NULL AND c.ai_model IS NOT NULL AND c.ai_model <> m.version';
				break;
			case 'fresh':
				$where_sql .= " AND c.id IS NOT NULL AND ( c.input_fingerprint IS NULL OR c.input_fingerprint = m.checksum ) AND ( c.ai_model IS NULL OR c.ai_model = m.version ) AND ( m.source <> 'manual' AND m.approved_by IS NULL )";
				break;
		}
	}

	$count_sql = "SELECT COUNT(*) FROM {$metadata_table} m LEFT JOIN {$cache_table} c ON c.attachment_id = m.media_id AND c.locale = m.locale AND c.field = m.field WHERE {$where_sql}";

	$total = ! empty( $params )
		? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) )
		: (int) $wpdb->get_var( $count_sql );

	$per_page    = max( 1, (int) $args['per_page'] );
	$page        = max( 1, (int) $args['page'] );
	$total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
	$offset      = ( $page - 1 ) * $per_page;

	$data_sql = "SELECT m.*, c.id AS cache_id, c.input_fingerprint, c.ai_model, {$status_case} AS metadata_status\n\tFROM {$metadata_table} m\n\tLEFT JOIN {$cache_table} c\n\tON c.attachment_id = m.media_id\n\tAND c.locale = m.locale\n\tAND c.field = m.field\n\tWHERE {$where_sql}\n\tORDER BY m.updated_at DESC\n\tLIMIT %d, %d";

	if ( ! empty( $params ) ) {
		$params_with_limit = array_merge( $params, array( $offset, $per_page ) );
		$items             = $wpdb->get_results( $wpdb->prepare( $data_sql, $params_with_limit ) );
	} else {
		$items = $wpdb->get_results( $wpdb->prepare( $data_sql, $offset, $per_page ) );
	}

	return array(
		'items'       => $items,
		'total'       => $total,
		'total_pages' => $total_pages,
	);
}
function msh_get_cache_entries( $args = array() ) {
	global $wpdb;

	$cache_table = $wpdb->prefix . 'msh_metadata_cache';

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
 * Upsert a metadata cache entry for an attachment field.
 *
 * @since 2.1.0
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $locale        Locale code (defaults to current locale).
 * @param string $field         Metadata field (title, alt, caption, description).
 * @param string $value         Metadata value to store.
 * @param string $source        Source identifier ('manual' or 'ai').
 * @param array  $extra         Optional extra data (input_fingerprint, ai_model).
 * @return bool True on success, false otherwise.
 */
function msh_upsert_metadata_cache_value( $attachment_id, $locale, $field, $value, $source = 'manual', $extra = array() ) {
	global $wpdb;

	$table = $wpdb->prefix . 'msh_metadata_cache';

	static $table_exists = null;
	if ( null === $table_exists ) {
		$table_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
	}

	if ( ! $table_exists ) {
		return false;
	}

	$attachment_id = absint( $attachment_id );
	if ( $attachment_id <= 0 ) {
		return false;
	}

	$locale = ! empty( $locale ) ? sanitize_text_field( $locale ) : get_locale();
	$field  = strtolower( trim( $field ) );
	if ( 'alt_text' === $field ) {
		$field = 'alt';
	}
	$field = sanitize_key( $field );

	$value  = is_null( $value ) ? '' : (string) $value;
	$source = in_array( $source, array( 'ai', 'manual' ), true ) ? $source : 'manual';

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE attachment_id = %d AND locale = %s AND field = %s",
			$attachment_id,
			$locale,
			$field
		),
		ARRAY_A
	);

	$now     = current_time( 'mysql' );
	$formats = array();
	$data    = array();

	if ( $existing ) {
		$data = array(
			'media_id'      => $attachment_id,
			'updated_at'    => $now,
			'stale_reason'  => '',
		);
		$formats = array( '%d', '%s', '%s' );

		if ( 'ai' === $source ) {
			$data['ai_value']      = $value;
			$data['chosen_source'] = 'ai';
			$formats[]             = '%s';
			$formats[]             = '%s';

			if ( isset( $extra['input_fingerprint'] ) ) {
				$data['input_fingerprint'] = (string) $extra['input_fingerprint'];
				$formats[]                 = '%s';
			}

			if ( isset( $extra['ai_model'] ) ) {
				$data['ai_model'] = (string) $extra['ai_model'];
				$formats[]        = '%s';
			}
		} else {
			$data['manual_value']  = $value;
			$data['chosen_source'] = 'manual';
			$formats[]             = '%s';
			$formats[]             = '%s';
		}

		$result = $wpdb->update(
			$table,
			$data,
			array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'field'         => $field,
			),
			$formats,
			array( '%d', '%s', '%s' )
		);
	} else {
		$data = array(
			'attachment_id'     => $attachment_id,
			'media_id'          => $attachment_id,
			'locale'            => $locale,
			'field'             => $field,
			'ai_value'          => ( 'ai' === $source ) ? $value : '',
			'manual_value'      => ( 'manual' === $source ) ? $value : '',
			'chosen_source'     => ( 'ai' === $source ) ? 'ai' : 'manual',
			'input_fingerprint' => isset( $extra['input_fingerprint'] ) ? (string) $extra['input_fingerprint'] : '',
			'ai_model'          => isset( $extra['ai_model'] ) ? (string) $extra['ai_model'] : '',
			'stale_reason'      => '',
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$result = $wpdb->insert(
			$table,
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	return ( false !== $result );
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
 * This function always returns the actual license status, ignoring dev mode.
 * Use this for UI display decisions (showing/hiding upgrade buttons, etc.).
 *
 * @return bool True if Pro license active, false otherwise.
 */
function msh_is_pro_active() {
	$license_status = get_option( 'msh_license_status', 'inactive' );
	$is_active = ( 'active' === $license_status );
	return apply_filters( 'msh_is_pro_active', $is_active );
}

/**
 * Check if user can use Pro features.
 *
 * This function respects dev mode bypass, allowing developers to test Pro
 * features without an active license. Use this for functional gating.
 *
 * @return bool True if can use Pro features, false otherwise.
 */
function msh_can_use_pro_features() {
	// Dev mode bypass
	if ( defined( 'MSH_DEV_MODE' ) && MSH_DEV_MODE ) {
		return true;
	}

	return msh_is_pro_active();
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

	$cache_table = $wpdb->prefix . 'msh_metadata_cache';

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
	$cache_table = $wpdb->prefix . 'msh_metadata_cache';
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

/**
 * Get version history for metadata changes.
 *
 * Returns a timeline of all metadata changes tracked by the optimizer.
 * Each entry shows what changed, when, and by whom (AI vs manual).
 *
 * @since 2.0.0
 * @param array $args {
 *     Optional query arguments.
 *     @type int    $limit         Number of entries to return (default 50).
 *     @type int    $attachment_id Filter by specific attachment.
 *     @type string $field         Filter by field (title, alt, caption, description).
 *     @type string $source        Filter by source (ai, manual).
 * }
 * @return array Array of history entries with timestamp, attachment_id, field, old_value, new_value, source, version.
 */
function msh_get_version_history( $args = array() ) {
	global $wpdb;

	$defaults = array(
		'limit'         => 50,
		'attachment_id' => 0,
		'field'         => '',
		'source'        => '',
		'locale'        => '',
	);

	$args = wp_parse_args( $args, $defaults );
	$versions_table = $wpdb->prefix . 'msh_optimizer_metadata';

	$where_clauses = array( '1=1' );
	$query_args = array();

	if ( ! empty( $args['attachment_id'] ) ) {
		$where_clauses[] = 'media_id = %d';
		$query_args[] = (int) $args['attachment_id'];
	}

	if ( ! empty( $args['field'] ) ) {
		$where_clauses[] = 'field = %s';
		$query_args[] = sanitize_text_field( $args['field'] );
	}

	if ( ! empty( $args['source'] ) ) {
		$where_clauses[] = 'source = %s';
		$query_args[] = sanitize_text_field( $args['source'] );
	}

	if ( ! empty( $args['locale'] ) ) {
		$where_clauses[] = 'locale = %s';
		$query_args[] = sanitize_text_field( $args['locale'] );
	}

	$where_sql = implode( ' AND ', $where_clauses );
	$limit = max( 1, (int) $args['limit'] );

	$query_args[] = $limit;

	$query = "SELECT
		id,
		media_id as attachment_id,
		field,
		value as new_value,
		source,
		created_at as timestamp,
		version
	FROM {$versions_table}
	WHERE {$where_sql}
	ORDER BY created_at DESC
	LIMIT %d";

	if ( ! empty( $query_args ) ) {
		$query = $wpdb->prepare( $query, $query_args );
	}

	$results = $wpdb->get_results( $query, ARRAY_A );

	// Get old_value by fetching the previous version
	foreach ( $results as &$entry ) {
		$prev_version = (int) $entry['version'] - 1;

		if ( $prev_version > 0 ) {
			$old_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT value FROM {$versions_table} WHERE media_id = %d AND field = %s AND version = %d",
					$entry['attachment_id'],
					$entry['field'],
					$prev_version
				)
			);
			$entry['old_value'] = $old_value ? $old_value : '';
		} else {
			$entry['old_value'] = ''; // First version has no previous value
		}

		$entry['timestamp'] = mysql2date( 'Y-m-d H:i:s', $entry['timestamp'] );
	}

	return $results;
}

/**
 * Get cloud sync status.
 *
 * Returns current sync state, last sync time, and pending items count.
 * This is a Pro feature.
 *
 * @since 2.0.0
 * @return array {
 *     Sync status data.
 *     @type bool   $enabled        Whether sync is enabled.
 *     @type string $last_sync      Last successful sync timestamp.
 *     @type string $next_scheduled Next scheduled sync time.
 *     @type int    $total_synced   Total items synced to cloud.
 *     @type int    $pending        Items pending sync.
 * }
 */
function msh_get_sync_status() {
	// Check if Pro is active
	if ( ! msh_is_pro_active() ) {
		return array(
			'enabled'        => false,
			'last_sync'      => null,
			'next_scheduled' => null,
			'total_synced'   => 0,
			'pending'        => 0,
		);
	}

	// Get sync options
	$sync_enabled = get_option( 'msh_sync_enabled', false );
	$last_sync = get_option( 'msh_sync_last_run', null );
	$next_scheduled = get_option( 'msh_sync_next_scheduled', null );
	$total_synced = (int) get_option( 'msh_sync_total_synced', 0 );

	// Count pending items from cache table
	global $wpdb;
	$cache_table = $wpdb->prefix . 'msh_metadata_cache';
	
	$pending = $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$cache_table}
		WHERE synced_at IS NULL OR synced_at = '0000-00-00 00:00:00'"
	);

	return array(
		'enabled'        => (bool) $sync_enabled,
		'last_sync'      => $last_sync ? mysql2date( 'Y-m-d H:i:s', $last_sync ) : null,
		'next_scheduled' => $next_scheduled ? mysql2date( 'Y-m-d H:i:s', $next_scheduled ) : null,
		'total_synced'   => $total_synced,
		'pending'        => (int) $pending,
	);
}

/**
 * Process jobs from the queue.
 *
 * Processes a batch of pending jobs with priority ordering.
 * Uses the Job Engine to fetch and process jobs with the Regeneration Worker.
 *
 * @param int         $batch_size Number of jobs to process (default: 10).
 * @param string|null $priority   Optional priority filter ('high', 'medium', 'normal').
 * @return array Array with processing results.
 */
function msh_process_queue( $batch_size = 10, $priority = null ) {
	if ( ! class_exists( 'MSH_Job_Engine' ) || ! class_exists( 'MSH_Regeneration_Worker' ) ) {
		return array(
			'processed' => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'message'   => __( 'Job processing system not available.', 'msh-image-optimizer' ),
		);
	}

	global $wpdb;
	$jobs_table = $wpdb->prefix . 'msh_jobs';
	$worker     = MSH_Regeneration_Worker::get_instance();

	// Build query for pending jobs
	$where_clauses = array( "status = 'pending'" );
	$query_args    = array();

	if ( $priority && in_array( $priority, array( 'high', 'medium', 'normal' ), true ) ) {
		$where_clauses[] = 'priority = %s';
		$query_args[]    = $priority;
	}

	$where_sql    = implode( ' AND ', $where_clauses );
	$query        = "SELECT * FROM {$jobs_table} WHERE {$where_sql} ORDER BY CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'normal' THEN 3 END, created_at ASC LIMIT %d";
	$query_args[] = $batch_size;

	$jobs = $wpdb->get_results( $wpdb->prepare( $query, $query_args ) );

	if ( empty( $jobs ) ) {
		return array(
			'processed' => 0,
			'failed'    => 0,
			'skipped'   => 0,
			'message'   => __( 'No pending jobs in queue.', 'msh-image-optimizer' ),
		);
	}

	$processed = 0;
	$failed    = 0;

	foreach ( $jobs as $job ) {
		$wpdb->update( $jobs_table, array( 'status' => 'processing', 'started_at' => current_time( 'mysql' ) ), array( 'id' => $job->id ), array( '%s', '%s' ), array( '%d' ) );

		$payload = ! empty( $job->payload ) ? json_decode( $job->payload, true ) : array();

		try {
			$result = $worker->process( $job, $payload );

			if ( is_wp_error( $result ) ) {
				$attempts = (int) $job->attempts + 1;
				$status   = $attempts >= (int) $job->max_attempts ? 'failed' : 'pending';
				$wpdb->update( $jobs_table, array( 'status' => $status, 'attempts' => $attempts, 'error_message' => $result->get_error_message(), 'completed_at' => $status === 'failed' ? current_time( 'mysql' ) : null ), array( 'id' => $job->id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );
				$failed++;
			} else {
				$wpdb->update( $jobs_table, array( 'status' => 'complete', 'completed_at' => current_time( 'mysql' ) ), array( 'id' => $job->id ), array( '%s', '%s' ), array( '%d' ) );
				$processed++;
			}
		} catch ( Exception $e ) {
			$attempts = (int) $job->attempts + 1;
			$status   = $attempts >= (int) $job->max_attempts ? 'failed' : 'pending';
			$wpdb->update( $jobs_table, array( 'status' => $status, 'attempts' => $attempts, 'error_message' => $e->getMessage(), 'completed_at' => $status === 'failed' ? current_time( 'mysql' ) : null ), array( 'id' => $job->id ), array( '%s', '%d', '%s', '%s' ), array( '%d' ) );
			$failed++;
		}
	}

	return array(
		'processed' => $processed,
		'failed'    => $failed,
		'skipped'   => 0,
		'message'   => sprintf( __( 'Processed %1$d job(s), %2$d failed.', 'msh-image-optimizer' ), $processed, $failed ),
	);
}
