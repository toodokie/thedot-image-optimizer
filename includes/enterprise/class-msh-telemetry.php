<?php
/**
 * Telemetry System
 *
 * Anonymous usage tracking for product improvement (opt-in).
 *
 * @package MSH_Image_Optimizer
 * @subpackage Enterprise
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_Telemetry class.
 */
class MSH_Telemetry {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Telemetry
	 */
	private static $instance = null;

	/**
	 * Telemetry server URL.
	 *
	 * @var string
	 */
	private $telemetry_server = 'https://telemetry.thedot.com/api/v1';

	/**
	 * Telemetry enabled option key.
	 *
	 * @var string
	 */
	const TELEMETRY_ENABLED_OPTION = 'msh_telemetry_enabled';

	/**
	 * Site ID option key.
	 *
	 * @var string
	 */
	const SITE_ID_OPTION = 'msh_telemetry_site_id';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Telemetry
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
		// Weekly telemetry send
		add_action( 'msh_send_telemetry', array( $this, 'send_telemetry_snapshot' ) );

		// AI telemetry hooks (Phase 6)
		add_action( 'msh_log_telemetry', array( $this, 'log_ai_telemetry' ), 10, 2 );
		add_action( 'msh_log_token_usage', array( $this, 'log_token_usage' ), 10, 3 );

		if ( ! wp_next_scheduled( 'msh_send_telemetry' ) ) {
			wp_schedule_event( time(), 'weekly', 'msh_send_telemetry' );
		}
	}

	/**
	 * Enable telemetry tracking.
	 *
	 * @return bool Success status.
	 */
	public function enable() {
		update_option( self::TELEMETRY_ENABLED_OPTION, '1' );

		// Generate unique site ID if not exists
		if ( ! get_option( self::SITE_ID_OPTION ) ) {
			$site_id = wp_generate_uuid4();
			update_option( self::SITE_ID_OPTION, $site_id );
		}

		// Send initial snapshot
		$this->send_telemetry_snapshot();

		return true;
	}

	/**
	 * Disable telemetry tracking.
	 *
	 * @return bool Success status.
	 */
	public function disable() {
		update_option( self::TELEMETRY_ENABLED_OPTION, '0' );

		// Send final opt-out event
		$this->track_event( 'telemetry_disabled', array() );

		return true;
	}

	/**
	 * Check if telemetry is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === get_option( self::TELEMETRY_ENABLED_OPTION, '0' );
	}

	/**
	 * Track an event.
	 *
	 * @param string $event_name Event name.
	 * @param array  $properties Event properties (optional).
	 * @return bool Success status.
	 */
	public function track_event( $event_name, $properties = array() ) {
		if ( ! $this->is_enabled() && 'telemetry_disabled' !== $event_name ) {
			return false;
		}

		global $wpdb;
		$telemetry_table = $wpdb->prefix . 'msh_telemetry';

		$wpdb->insert(
			$telemetry_table,
			array(
				'event_name' => $event_name,
				'properties' => wp_json_encode( $properties ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		// If buffer is large (>100 events), send immediately
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$telemetry_table}" );
		if ( $count > 100 ) {
			$this->send_telemetry_snapshot();
		}

		return true;
	}

	/**
	 * Send telemetry snapshot to server.
	 *
	 * @return bool Success status.
	 */
	public function send_telemetry_snapshot() {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		global $wpdb;
		$telemetry_table = $wpdb->prefix . 'msh_telemetry';

		// Get unsent events
		$events = $wpdb->get_results(
			"SELECT * FROM {$telemetry_table} WHERE sent = 0 ORDER BY created_at ASC LIMIT 500"
		);

		if ( empty( $events ) ) {
			return false;
		}

		// Prepare payload
		$payload = array(
			'site_id'     => get_option( self::SITE_ID_OPTION ),
			'plugin_version' => MSH_IO_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'events'      => array_map( function( $event ) {
				return array(
					'event_name' => $event->event_name,
					'properties' => json_decode( $event->properties, true ),
					'timestamp'  => $event->created_at,
				);
			}, $events ),
			'snapshot'    => $this->get_system_snapshot(),
		);

		// Send to server
		$response = $this->send_to_server( $payload );

		if ( ! is_wp_error( $response ) ) {
			// Mark events as sent
			$event_ids = array_map( function( $event ) {
				return $event->id;
			}, $events );

			$wpdb->query(
				"UPDATE {$telemetry_table} SET sent = 1 WHERE id IN (" . implode( ',', array_map( 'absint', $event_ids ) ) . ')'
			);

			// Cleanup old sent events (>30 days)
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$telemetry_table} WHERE sent = 1 AND created_at < %s",
					gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
				)
			);

			return true;
		}

		return false;
	}

	/**
	 * Get system snapshot for telemetry.
	 *
	 * @return array System snapshot data.
	 */
	private function get_system_snapshot() {
		global $wpdb;

		// Get attachment counts
		$total_attachments = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'" );

		// Get metadata counts
		$metadata_table = $wpdb->prefix . 'msh_optimizer_metadata';
		$metadata_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$metadata_table}" );

		// Get locale usage
		$locale_counts = $wpdb->get_results(
			"SELECT locale, COUNT(*) as count FROM {$metadata_table} GROUP BY locale",
			ARRAY_A
		);

		// Get job stats
		$jobs_table = $wpdb->prefix . 'msh_jobs';
		$job_stats = array(
			'total'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table}" ),
			'pending' => $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'pending'" ),
			'failed'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'failed'" ),
		);

		// Get feature usage
		$features = array(
			'locale_profiles' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}msh_locale_profiles" ),
			'glossary_terms'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}msh_locale_glossary" ),
			'ab_campaigns'    => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}msh_ab_campaigns" ),
		);

		return array(
			'site_url'          => home_url(),
			'admin_email_hash'  => md5( get_option( 'admin_email' ) ), // Anonymized
			'total_attachments' => (int) $total_attachments,
			'metadata_count'    => (int) $metadata_count,
			'locale_counts'     => $locale_counts,
			'job_stats'         => $job_stats,
			'features'          => $features,
			'php_version'       => PHP_VERSION,
			'wp_version'        => get_bloginfo( 'version' ),
			'mysql_version'     => $wpdb->db_version(),
			'is_multisite'      => is_multisite(),
			'active_theme'      => get_template(),
			'plugin_version'    => MSH_IO_VERSION,
			'license_status'    => get_option( MSH_License_Manager::LICENSE_STATUS_OPTION, 'free' ),
		);
	}

	/**
	 * Send data to telemetry server.
	 *
	 * @param array $payload Data to send.
	 * @return array|WP_Error Response or error.
	 */
	private function send_to_server( $payload ) {
		$url = trailingslashit( $this->telemetry_server ) . 'collect';

		$response = wp_remote_post( $url, array(
			'timeout' => 10,
			'body'    => wp_json_encode( $payload ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'MSH-Image-Optimizer/' . MSH_IO_VERSION,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'telemetry_server_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Telemetry server returned status %d', 'msh-image-optimizer' ),
					$status_code
				)
			);
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Get telemetry statistics for admin display.
	 *
	 * @return array {
	 *     Statistics.
	 *
	 *     @type bool  $enabled Whether telemetry is enabled.
	 *     @type int   $events_pending Number of unsent events.
	 *     @type int   $events_sent Total sent events.
	 *     @type string $last_sent Timestamp of last send.
	 * }
	 */
	public function get_stats() {
		global $wpdb;
		$telemetry_table = $wpdb->prefix . 'msh_telemetry';

		$pending = $wpdb->get_var( "SELECT COUNT(*) FROM {$telemetry_table} WHERE sent = 0" );
		$sent = $wpdb->get_var( "SELECT COUNT(*) FROM {$telemetry_table} WHERE sent = 1" );
		$last_sent = $wpdb->get_var( "SELECT MAX(created_at) FROM {$telemetry_table} WHERE sent = 1" );

		return array(
			'enabled'        => $this->is_enabled(),
			'events_pending' => (int) $pending,
			'events_sent'    => (int) $sent,
			'last_sent'      => $last_sent,
		);
	}

	/**
	 * Log AI batch telemetry (Phase 6).
	 *
	 * @param string $event_type Event type.
	 * @param array  $data Telemetry data.
	 * @return void
	 */
	public function log_ai_telemetry( $event_type, $data ) {
		if ( 'ai_batch_complete' !== $event_type ) {
			return;
		}

		// Calculate confidence average
		$confidence_avg = ! empty( $data['confidence_scores'] )
			? array_sum( $data['confidence_scores'] ) / count( $data['confidence_scores'] )
			: 0.0;

		// Track as telemetry event
		$this->track_event(
			'ai_batch_complete',
			array(
				'batch_size'                => $data['ai_success_count'] + $data['ai_fallback_count'],
				'ai_success_count'          => $data['ai_success_count'],
				'ai_fallback_count'         => $data['ai_fallback_count'],
				'confidence_avg'            => round( $confidence_avg, 2 ),
				'brand_name_assumed_count'  => $data['brand_name_assumed_count'],
				'decorative_image_count'    => $data['decorative_image_count'],
				'text_detected_count'       => $data['text_detected_count'],
				'low_confidence_count'      => $data['low_confidence_count'],
				'prompt_version'            => defined( 'MSH_OpenAI_Connector::PROMPT_VERSION' ) ? MSH_OpenAI_Connector::PROMPT_VERSION : 'unknown',
			)
		);
	}

	/**
	 * Log token usage per image (Phase 6).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $tokens_used Token usage data.
	 * @param string $prompt_version Prompt version.
	 * @return void
	 */
	public function log_token_usage( $attachment_id, $tokens_used, $prompt_version ) {
		// Track as telemetry event
		$this->track_event(
			'ai_token_usage',
			array(
				'attachment_id'     => $attachment_id,
				'prompt_tokens'     => $tokens_used['prompt_tokens'],
				'completion_tokens' => $tokens_used['completion_tokens'],
				'total_tokens'      => $tokens_used['total_tokens'],
				'prompt_version'    => $prompt_version,
			)
		);
	}
}
