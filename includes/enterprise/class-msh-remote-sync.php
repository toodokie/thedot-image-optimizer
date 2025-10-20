<?php
/**
 * Remote Sync
 *
 * Cloud synchronization for multi-site metadata sharing (Pro feature).
 *
 * @package MSH_Image_Optimizer
 * @subpackage Enterprise
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_Remote_Sync class.
 */
class MSH_Remote_Sync {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Remote_Sync
	 */
	private static $instance = null;

	/**
	 * Sync server URL.
	 *
	 * @var string
	 */
	private $sync_server = 'https://sync.thedot.com/api/v1';

	/**
	 * License manager instance.
	 *
	 * @var MSH_License_Manager
	 */
	private $license_manager;

	/**
	 * Sync enabled option key.
	 *
	 * @var string
	 */
	const SYNC_ENABLED_OPTION = 'msh_sync_enabled';

	/**
	 * Last sync time option key.
	 *
	 * @var string
	 */
	const LAST_SYNC_OPTION = 'msh_last_sync_time';

	/**
	 * Sync conflict strategy option key.
	 *
	 * @var string
	 */
	const CONFLICT_STRATEGY_OPTION = 'msh_sync_conflict_strategy';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Remote_Sync
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
		$this->license_manager = MSH_License_Manager::get_instance();

		// Automatic sync every 6 hours (if enabled)
		add_action( 'msh_auto_sync', array( $this, 'auto_sync' ) );

		if ( ! wp_next_scheduled( 'msh_auto_sync' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'msh_auto_sync' );
		}

		// Sync on metadata changes
		add_action( 'msh_metadata_updated', array( $this, 'queue_sync' ), 10, 2 );
	}

	/**
	 * Enable remote sync.
	 *
	 * @return array {
	 *     Result.
	 *
	 *     @type bool   $success Whether enabling succeeded.
	 *     @type string $message User-facing message.
	 * }
	 */
	public function enable() {
		// Check Pro license
		if ( ! $this->license_manager->has_feature( 'remote_sync' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Remote Sync requires an active Pro license.', 'msh-image-optimizer' ),
			);
		}

		update_option( self::SYNC_ENABLED_OPTION, '1' );

		// Perform initial sync
		$result = $this->sync_now();

		if ( $result['success'] ) {
			return array(
				'success' => true,
				'message' => __( 'Remote Sync enabled and initial sync completed.', 'msh-image-optimizer' ),
			);
		}

		return $result;
	}

	/**
	 * Disable remote sync.
	 *
	 * @return array Result.
	 */
	public function disable() {
		update_option( self::SYNC_ENABLED_OPTION, '0' );

		return array(
			'success' => true,
			'message' => __( 'Remote Sync disabled.', 'msh-image-optimizer' ),
		);
	}

	/**
	 * Check if remote sync is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return '1' === get_option( self::SYNC_ENABLED_OPTION, '0' ) && $this->license_manager->has_feature( 'remote_sync' );
	}

	/**
	 * Sync now (manual trigger).
	 *
	 * @return array {
	 *     Sync result.
	 *
	 *     @type bool   $success Whether sync succeeded.
	 *     @type string $message User-facing message.
	 *     @type array  $stats   Sync statistics.
	 * }
	 */
	public function sync_now() {
		if ( ! $this->is_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'Remote Sync is not enabled.', 'msh-image-optimizer' ),
			);
		}

		// Get local changes since last sync
		$last_sync = get_option( self::LAST_SYNC_OPTION, 0 );
		$local_changes = $this->get_local_changes( $last_sync );

		// Push local changes
		$push_result = $this->push_changes( $local_changes );

		if ( is_wp_error( $push_result ) ) {
			return array(
				'success' => false,
				'message' => $push_result->get_error_message(),
			);
		}

		// Pull remote changes
		$pull_result = $this->pull_changes( $last_sync );

		if ( is_wp_error( $pull_result ) ) {
			return array(
				'success' => false,
				'message' => $pull_result->get_error_message(),
			);
		}

		// Update last sync time
		update_option( self::LAST_SYNC_OPTION, time() );

		return array(
			'success' => true,
			'message' => __( 'Sync completed successfully.', 'msh-image-optimizer' ),
			'stats'   => array(
				'pushed' => count( $local_changes ),
				'pulled' => count( $pull_result ),
			),
		);
	}

	/**
	 * Auto sync (scheduled).
	 *
	 * @return void
	 */
	public function auto_sync() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->sync_now();
	}

	/**
	 * Queue a sync after metadata update.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return void
	 */
	public function queue_sync( $attachment_id, $locale ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Debounce: only sync if last sync was >5 minutes ago
		$last_sync = get_option( self::LAST_SYNC_OPTION, 0 );
		if ( time() - $last_sync < 300 ) {
			return;
		}

		// Schedule immediate sync
		if ( ! wp_next_scheduled( 'msh_immediate_sync' ) ) {
			wp_schedule_single_event( time() + 60, 'msh_immediate_sync' );
		}
	}

	/**
	 * Get local changes since last sync.
	 *
	 * @param int $since Unix timestamp.
	 * @return array Array of changed metadata entries.
	 */
	private function get_local_changes( $since ) {
		global $wpdb;
		$metadata_table = $wpdb->prefix . 'msh_optimizer_metadata';

		$changes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$metadata_table} WHERE UNIX_TIMESTAMP(updated_at) > %d ORDER BY updated_at ASC",
				$since
			),
			ARRAY_A
		);

		return $changes ? $changes : array();
	}

	/**
	 * Push local changes to remote server.
	 *
	 * @param array $changes Array of metadata changes.
	 * @return array|WP_Error Response or error.
	 */
	private function push_changes( $changes ) {
		if ( empty( $changes ) ) {
			return array( 'pushed' => 0 );
		}

		$license_key = get_option( MSH_License_Manager::LICENSE_KEY_OPTION );

		$payload = array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
			'changes'     => $changes,
		);

		return $this->call_sync_server( 'push', $payload );
	}

	/**
	 * Pull remote changes from server.
	 *
	 * @param int $since Unix timestamp.
	 * @return array|WP_Error Remote changes or error.
	 */
	private function pull_changes( $since ) {
		$license_key = get_option( MSH_License_Manager::LICENSE_KEY_OPTION );

		$payload = array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
			'since'       => $since,
		);

		$response = $this->call_sync_server( 'pull', $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['changes'] ) ) {
			return array();
		}

		// Apply remote changes locally
		$this->apply_remote_changes( $response['changes'] );

		return $response['changes'];
	}

	/**
	 * Apply remote changes to local database.
	 *
	 * @param array $changes Remote changes.
	 * @return void
	 */
	private function apply_remote_changes( $changes ) {
		if ( empty( $changes ) ) {
			return;
		}

		global $wpdb;
		$metadata_table = $wpdb->prefix . 'msh_optimizer_metadata';
		$conflict_strategy = get_option( self::CONFLICT_STRATEGY_OPTION, 'remote_wins' );

		foreach ( $changes as $change ) {
			// Check if local version exists
			$local = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$metadata_table} WHERE media_id = %d AND locale = %s AND field = %s",
					$change['media_id'],
					$change['locale'],
					$change['field']
				),
				ARRAY_A
			);

			if ( ! $local ) {
				// Insert new remote entry
				$wpdb->insert(
					$metadata_table,
					array(
						'media_id'   => $change['media_id'],
						'locale'     => $change['locale'],
						'field'      => $change['field'],
						'value'      => $change['value'],
						'source'     => $change['source'],
						'version'    => $change['version'],
						'checksum'   => $change['checksum'],
						'created_at' => $change['created_at'],
						'updated_at' => $change['updated_at'],
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
				);
			} else {
				// Conflict resolution
				$should_update = false;

				if ( 'remote_wins' === $conflict_strategy ) {
					$should_update = true;
				} elseif ( 'local_wins' === $conflict_strategy ) {
					$should_update = false;
				} elseif ( 'newest_wins' === $conflict_strategy ) {
					$remote_time = strtotime( $change['updated_at'] );
					$local_time = strtotime( $local['updated_at'] );
					$should_update = $remote_time > $local_time;
				}

				if ( $should_update ) {
					$wpdb->update(
						$metadata_table,
						array(
							'value'      => $change['value'],
							'source'     => $change['source'],
							'version'    => $change['version'],
							'checksum'   => $change['checksum'],
							'updated_at' => $change['updated_at'],
						),
						array(
							'media_id' => $change['media_id'],
							'locale'   => $change['locale'],
							'field'    => $change['field'],
						),
						array( '%s', '%s', '%d', '%s', '%s' ),
						array( '%d', '%s', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Call sync server API.
	 *
	 * @param string $endpoint API endpoint (push, pull).
	 * @param array  $data     Request data.
	 * @return array|WP_Error Response or error.
	 */
	private function call_sync_server( $endpoint, $data ) {
		$url = trailingslashit( $this->sync_server ) . $endpoint;

		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'body'    => wp_json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'MSH-Image-Optimizer/' . MSH_IO_VERSION,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'sync_server_error',
				$decoded['message'] ?? __( 'Sync server error.', 'msh-image-optimizer' )
			);
		}

		return $decoded;
	}

	/**
	 * Get sync status for admin display.
	 *
	 * @return array {
	 *     Sync status.
	 *
	 *     @type bool   $enabled       Whether sync is enabled.
	 *     @type int    $last_sync     Last sync timestamp.
	 *     @type string $last_sync_ago Human-readable time since last sync.
	 *     @type int    $pending       Number of pending changes.
	 * }
	 */
	public function get_status() {
		$last_sync = get_option( self::LAST_SYNC_OPTION, 0 );
		$pending = 0;

		if ( $last_sync > 0 ) {
			$changes = $this->get_local_changes( $last_sync );
			$pending = count( $changes );
		}

		return array(
			'enabled'       => $this->is_enabled(),
			'last_sync'     => $last_sync,
			'last_sync_ago' => $last_sync > 0 ? human_time_diff( $last_sync ) . ' ago' : __( 'Never', 'msh-image-optimizer' ),
			'pending'       => $pending,
		);
	}
}
