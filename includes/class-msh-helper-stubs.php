<?php
/**
 * Helper Function Stubs for Phase 5+9
 *
 * TEMPORARY: These are mock/stub implementations to allow AI #2 (Frontend)
 * to test their UI immediately. AI #1 (Backend) will replace these with
 * real implementations that connect to the database.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get metadata cache entries with filters
 *
 * STUB: Returns mock data for testing
 * REAL: Will query msh_metadata_cache table
 *
 * @param array $args {
 *     @type string $locale Locale filter
 *     @type string $staleness 'stale' or 'fresh'
 *     @type string $source 'ai' or 'manual'
 *     @type string $search Search term
 *     @type int $page Page number
 *     @type int $per_page Results per page
 * }
 * @return array {
 *     @type array $items Cache entries
 *     @type int $total Total matching entries
 *     @type int $total_pages Total pages
 * }
 */
function msh_get_cache_entries( $args = array() ) {
    // Parse args with defaults
    $args = wp_parse_args( $args, array(
        'locale'     => '',
        'staleness'  => '',
        'source'     => '',
        'search'     => '',
        'page'       => 1,
        'per_page'   => 50,
    ) );

    // STUB: Return mock data
    $mock_items = array(
        (object) array(
            'id'            => 1,
            'attachment_id' => 1686,
            'locale'        => 'es_ES',
            'field'         => 'alt',
            'ai_value'      => 'Fisioterapeuta ayudando a paciente con rehabilitación de rodilla',
            'manual_value'  => 'Terapia física profesional',
            'chosen_source' => 'manual',
            'stale_reason'  => 'context_changed',
            'updated_at'    => '2025-10-19 14:30:00',
        ),
        (object) array(
            'id'            => 2,
            'attachment_id' => 1687,
            'locale'        => 'es_ES',
            'field'         => 'title',
            'ai_value'      => 'Sesión de fisioterapia',
            'manual_value'  => '',
            'chosen_source' => 'ai',
            'stale_reason'  => null, // Fresh
            'updated_at'    => '2025-10-19 14:25:00',
        ),
        (object) array(
            'id'            => 3,
            'attachment_id' => 1688,
            'locale'        => 'fr_FR',
            'field'         => 'alt',
            'ai_value'      => 'Physiothérapeute aidant un patient',
            'manual_value'  => '',
            'chosen_source' => 'ai',
            'stale_reason'  => 'glossary_changed',
            'updated_at'    => '2025-10-19 14:20:00',
        ),
    );

    // Filter mock data based on args (basic filtering)
    $filtered = $mock_items;

    if ( ! empty( $args['locale'] ) ) {
        $filtered = array_filter( $filtered, function( $item ) use ( $args ) {
            return $item->locale === $args['locale'];
        } );
    }

    if ( ! empty( $args['staleness'] ) ) {
        $filtered = array_filter( $filtered, function( $item ) use ( $args ) {
            if ( $args['staleness'] === 'stale' ) {
                return ! empty( $item->stale_reason );
            } else {
                return empty( $item->stale_reason );
            }
        } );
    }

    if ( ! empty( $args['source'] ) ) {
        $filtered = array_filter( $filtered, function( $item ) use ( $args ) {
            return $item->chosen_source === $args['source'];
        } );
    }

    return array(
        'items'       => array_values( $filtered ), // Re-index
        'total'       => count( $filtered ),
        'total_pages' => ceil( count( $filtered ) / $args['per_page'] ),
    );
}

/**
 * Get job queue statistics
 *
 * STUB: Returns mock stats
 * REAL: Will query msh_jobs table
 *
 * @return array {
 *     @type int $pending Pending jobs
 *     @type int $processing Currently processing
 *     @type int $complete Completed (last 24h)
 *     @type int $failed Failed jobs
 *     @type int $high_priority High priority pending
 *     @type int $medium_priority Medium priority pending
 *     @type int $normal_priority Normal priority pending
 * }
 */
function msh_get_job_stats() {
    // REAL IMPLEMENTATION: Use MSH_Job_Engine
    if ( ! class_exists( 'MSH_Job_Engine' ) ) {
        // Fallback to mock stats if engine not loaded
        return array(
            'pending'         => 0,
            'processing'      => 0,
            'complete'        => 0,
            'failed'          => 0,
            'priority_high'   => 0,
            'priority_medium' => 0,
            'priority_normal' => 0,
        );
    }

    return MSH_Job_Engine::get_instance()->get_stats();
}

/**
 * Enqueue a job for background processing
 *
 * STUB: Just logs and returns mock ID
 * REAL: Will insert into msh_jobs table
 *
 * @param string $job_type Job type
 * @param string $entity_type Entity type
 * @param int $entity_id Entity ID
 * @param array $payload Job data
 * @param string $priority Priority level
 * @return int|WP_Error Job ID or error
 */
function msh_enqueue_job( $job_type, $entity_type, $entity_id, $payload = array(), $priority = 'normal' ) {
    // REAL IMPLEMENTATION: Use MSH_Job_Engine
    if ( ! class_exists( 'MSH_Job_Engine' ) ) {
        return new WP_Error( 'job_engine_missing', __( 'Job engine not loaded.', 'msh-image-optimizer' ) );
    }

    return MSH_Job_Engine::get_instance()->enqueue( $job_type, $entity_type, $entity_id, $payload, $priority );
}

/**
 * Check if Pro or Agency plan is active
 *
 * STUB: Always returns false (Free tier)
 * REAL: Will check license in database/options
 *
 * @return bool
 */
function msh_is_pro_active() {
    // STUB: Return false for testing
    return false;
}

/**
 * Log telemetry event
 *
 * STUB: Just logs to error_log
 * REAL: Will insert into msh_telemetry table (if opt-in enabled)
 *
 * @param string $event Event name
 * @param array $data Event data
 * @return bool True if logged
 */
function msh_telemetry( $event, $data = array() ) {
    // STUB: Log to error_log
    error_log( sprintf(
        'STUB: msh_telemetry( %s, %s )',
        $event,
        wp_json_encode( $data )
    ) );

    return true;
}

/**
 * Get single cache entry by ID
 *
 * STUB: Returns mock entry
 * REAL: Will query msh_metadata_cache table
 *
 * @param int $cache_id Cache entry ID
 * @return object|null Cache entry or null
 */
function msh_get_cache_entry( $cache_id ) {
    // STUB: Return mock entry
    if ( $cache_id === 1 ) {
        return (object) array(
            'id'            => 1,
            'attachment_id' => 1686,
            'locale'        => 'es_ES',
            'field'         => 'alt',
            'ai_value'      => 'Fisioterapeuta ayudando a paciente con rehabilitación de rodilla',
            'manual_value'  => 'Terapia física profesional',
            'chosen_source' => 'manual',
            'stale_reason'  => 'context_changed',
            'updated_at'    => '2025-10-19 14:30:00',
        );
    }

    return null;
}

/**
 * Get recent events
 *
 * STUB: Returns mock events
 * REAL: Will query msh_events table
 *
 * @param array $args Query args
 * @return array Events
 */
function msh_get_recent_events( $args = array() ) {
    // STUB: Return mock events
    return array(
        (object) array(
            'id'         => 1,
            'event'      => 'metadata.regen_queued',
            'entity_type' => 'attachment',
            'entity_id'  => 1686,
            'user_id'    => 1,
            'created_at' => '2025-10-19 14:30:00',
            'processed_at' => null,
        ),
        (object) array(
            'id'         => 2,
            'event'      => 'post.updated',
            'entity_type' => 'post',
            'entity_id'  => 123,
            'user_id'    => 1,
            'created_at' => '2025-10-19 14:25:00',
            'processed_at' => '2025-10-19 14:26:00',
        ),
    );
}

/**
 * Get version history for an attachment
 *
 * STUB: Returns mock versions
 * REAL: Will query msh_versions table
 *
 * @param int $attachment_id Attachment ID
 * @param string $locale Locale code
 * @param string $field Field name
 * @return array Versions
 */
function msh_get_version_history( $attachment_id, $locale, $field ) {
    // STUB: Return mock versions
    return array(
        (object) array(
            'id'         => 3,
            'value'      => 'Terapia física profesional',
            'source'     => 'manual',
            'created_at' => '2025-10-19 14:01:00',
            'notes'      => 'Simplified for accessibility',
            'is_active'  => true,
        ),
        (object) array(
            'id'         => 2,
            'value'      => 'Fisioterapeuta ayudando a paciente con rehabilitación de rodilla',
            'source'     => 'ai',
            'created_at' => '2025-10-18 10:30:00',
            'notes'      => null,
            'is_active'  => false,
        ),
        (object) array(
            'id'         => 1,
            'value'      => 'Fisioterapeuta con paciente',
            'source'     => 'ai',
            'created_at' => '2025-10-15 09:00:00',
            'notes'      => null,
            'is_active'  => false,
        ),
    );
}


/**
 * Process job queue manually
 *
 * STUB: Just logs and returns success
 * REAL: Will process pending jobs from msh_jobs table
 *
 * @param int $batch_size Number of jobs to process
 * @return array|WP_Error Processing results
 */
function msh_process_queue( $batch_size = 10 ) {
	// REAL IMPLEMENTATION: Use MSH_Job_Engine
	if ( ! class_exists( 'MSH_Job_Engine' ) ) {
		return new WP_Error( 'job_engine_missing', __( 'Job engine not loaded.', 'msh-image-optimizer' ) );
	}

	$results = MSH_Job_Engine::get_instance()->process_batch( $batch_size );

	// Add user-friendly message
	$results['message'] = sprintf(
		__( 'Processed %d jobs, %d failed.', 'msh-image-optimizer' ),
		$results['processed'],
		$results['failed']
	);

	return $results;
}
