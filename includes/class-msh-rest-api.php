<?php
/**
 * REST API Endpoints for MSH Image Optimizer
 *
 * Provides REST API endpoints for metadata operations, job queue management,
 * and telemetry. Used by external integrations and frontend JavaScript.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_REST_API {
	/**
	 * Singleton instance.
	 *
	 * @var MSH_REST_API
	 */
	private static $instance = null;

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'msh/v1';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_REST_API
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Register REST API routes.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Job Queue Endpoints
		register_rest_route(
			self::NAMESPACE,
			'/jobs/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_jobs_status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/process',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_jobs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'batch_size' => array(
						'required'          => false,
						'default'           => 10,
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0 && $param <= 100;
						},
					),
					'priority'   => array(
						'required' => false,
						'default'  => null,
						'enum'     => array( null, 'high', 'medium', 'normal' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Metadata Endpoints
		register_rest_route(
			self::NAMESPACE,
			'/metadata/cache',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_metadata_cache' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'media_id' => array(
						'required' => false,
						'type'     => 'integer',
					),
					'locale'   => array(
						'required' => false,
						'type'     => 'string',
					),
					'limit'    => array(
						'required' => false,
						'default'  => 50,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/metadata/regenerate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'regenerate_metadata' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'media_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'locale'   => array(
						'required' => false,
						'type'     => 'string',
					),
					'field'    => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);

		// Telemetry Endpoint
		register_rest_route(
			self::NAMESPACE,
			'/telemetry',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'log_telemetry' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'event' => array(
						'required' => true,
						'type'     => 'string',
					),
					'data'  => array(
						'required' => false,
						'type'     => 'object',
					),
				),
			)
		);
	}

	/**
	 * Permission callback - Check if user can manage options.
	 *
	 * Uses WordPress cookie authentication for logged-in admin users.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		// Check if user has admin capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'msh-image-optimizer' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Get job queue status.
	 *
	 * GET /msh/v1/jobs/status
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_jobs_status( $request ) {
		if ( ! function_exists( 'msh_get_job_stats' ) ) {
			return new WP_Error( 'function_not_found', __( 'Job stats function not available.', 'msh-image-optimizer' ), array( 'status' => 500 ) );
		}

		$stats = msh_get_job_stats();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'stats' => $stats,
					'timestamp' => current_time( 'mysql' ),
				),
			)
		);
	}

	/**
	 * Process jobs from the queue.
	 *
	 * POST /msh/v1/jobs/process
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function process_jobs( $request ) {
		if ( ! function_exists( 'msh_process_queue' ) ) {
			return new WP_Error( 'function_not_found', __( 'Queue processing function not available.', 'msh-image-optimizer' ), array( 'status' => 500 ) );
		}

		$batch_size = $request->get_param( 'batch_size' );
		$priority   = $request->get_param( 'priority' );

		$result = msh_process_queue( $batch_size, $priority );

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'rest_api_process_jobs',
				array(
					'batch_size' => $batch_size,
					'priority'   => $priority,
					'processed'  => $result['processed'] ?? 0,
					'failed'     => $result['failed'] ?? 0,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Get a specific job by ID.
	 *
	 * GET /msh/v1/jobs/{id}
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_job( $request ) {
		global $wpdb;

		$job_id = (int) $request->get_param( 'id' );

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}msh_jobs WHERE id = %d",
				$job_id
			)
		);

		if ( ! $job ) {
			return new WP_Error( 'job_not_found', __( 'Job not found.', 'msh-image-optimizer' ), array( 'status' => 404 ) );
		}

		// Parse payload JSON
		if ( ! empty( $job->payload ) ) {
			$job->payload = json_decode( $job->payload, true );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'job' => $job,
				),
			)
		);
	}

	/**
	 * Get metadata cache entries.
	 *
	 * GET /msh/v1/metadata/cache
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function get_metadata_cache( $request ) {
		global $wpdb;

		$media_id = $request->get_param( 'media_id' );
		$locale   = $request->get_param( 'locale' );
		$limit    = (int) $request->get_param( 'limit' );

		$table = $wpdb->prefix . 'optimizer_metadata_cache';

		$where = array( '1=1' );
		$args  = array();

		if ( $media_id ) {
			$where[] = 'media_id = %d';
			$args[]  = $media_id;
		}

		if ( $locale ) {
			$where[] = 'locale = %s';
			$args[]  = $locale;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d";
		$args[] = $limit;

		$results = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'entries' => $results,
					'count'   => count( $results ),
				),
			)
		);
	}

	/**
	 * Regenerate metadata for an attachment.
	 *
	 * POST /msh/v1/metadata/regenerate
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function regenerate_metadata( $request ) {
		if ( ! function_exists( 'msh_enqueue_job' ) ) {
			return new WP_Error( 'function_not_found', __( 'Job enqueue function not available.', 'msh-image-optimizer' ), array( 'status' => 500 ) );
		}

		$media_id = (int) $request->get_param( 'media_id' );
		$locale   = $request->get_param( 'locale' );
		$field    = $request->get_param( 'field' );

		// Validate media exists
		$attachment = get_post( $media_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'invalid_media_id', __( 'Invalid media ID.', 'msh-image-optimizer' ), array( 'status' => 400 ) );
		}

		// Build payload
		$payload = array();
		if ( $locale ) {
			$payload['locale'] = $locale;
		}
		if ( $field ) {
			$payload['field'] = $field;
		}

		// Enqueue job
		$job_id = msh_enqueue_job( 'regenerate_metadata', $media_id, $payload );

		if ( ! $job_id ) {
			return new WP_Error( 'job_creation_failed', __( 'Failed to create regeneration job.', 'msh-image-optimizer' ), array( 'status' => 500 ) );
		}

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'rest_api_regenerate',
				array(
					'media_id' => $media_id,
					'locale'   => $locale,
					'field'    => $field,
					'job_id'   => $job_id,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'job_id'  => $job_id,
					'message' => __( 'Regeneration job created successfully.', 'msh-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * Log telemetry event.
	 *
	 * POST /msh/v1/telemetry
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function log_telemetry( $request ) {
		if ( ! function_exists( 'msh_telemetry' ) ) {
			return new WP_Error( 'function_not_found', __( 'Telemetry function not available.', 'msh-image-optimizer' ), array( 'status' => 500 ) );
		}

		$event = $request->get_param( 'event' );
		$data  = $request->get_param( 'data' );

		$result = msh_telemetry( $event, $data );

		return rest_ensure_response(
			array(
				'success' => (bool) $result,
				'data'    => array(
					'message' => __( 'Telemetry event logged.', 'msh-image-optimizer' ),
				),
			)
		);
	}
}

// Initialize REST API
MSH_REST_API::get_instance();
