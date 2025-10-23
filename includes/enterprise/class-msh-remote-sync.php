<?php
/**
 * Remote Sync - Supabase Backend
 *
 * Cloud synchronization for multi-site metadata sharing (Pro feature).
 * Connects to Supabase Edge Functions for metadata sync.
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
	 * Supabase project URL.
	 *
	 * @var string
	 */
	private $supabase_url = 'https://fzynkgtarqbdofegyvbq.supabase.co';

	/**
	 * Supabase anon key.
	 *
	 * @var string
	 */
	private $supabase_anon_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ6eW5rZ3RhcnFiZG9mZWd5dmJxIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjExNjQ2MTEsImV4cCI6MjA3Njc0MDYxMX0.xWg_ELVc-dw4Rd3Hx7fdq_-ToudY40ZW6IIOOoHFHrU';

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
	 * Site ID option key (from Supabase handshake).
	 *
	 * @var string
	 */
	const SITE_ID_OPTION = 'msh_sync_site_id';

	/**
	 * Last sync time option key.
	 *
	 * @var string
	 */
	const LAST_SYNC_OPTION = 'msh_last_sync_time';

	/**
	 * Last sync cursor option key.
	 *
	 * @var string
	 */
	const LAST_SYNC_CURSOR_OPTION = 'msh_last_sync_cursor';

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

		// Automatic sync based on user preference (hourly, daily, or off)
		add_action( 'msh_auto_sync_cron', array( $this, 'auto_sync' ) );

		// Backward compatibility: support old hook name
		add_action( 'msh_auto_sync', array( $this, 'auto_sync' ) );

		// Sync on metadata changes
		add_action( 'msh_metadata_updated', array( $this, 'queue_sync' ), 10, 2 );

		// Migrate old cron hook to new hook on load
		add_action( 'init', array( $this, 'migrate_cron_hook' ), 5 );
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

		// Perform handshake to register site
		$handshake_result = $this->handshake();

		if ( is_wp_error( $handshake_result ) ) {
			return array(
				'success' => false,
				'message' => $handshake_result->get_error_message(),
			);
		}

		// Save site_id from handshake
		update_option( self::SITE_ID_OPTION, $handshake_result['site_id'] );
		update_option( self::SYNC_ENABLED_OPTION, '1' );

		// Perform initial pull
		$pull_result = $this->pull_changes();

		if ( is_wp_error( $pull_result ) ) {
			return array(
				'success' => false,
				'message' => $pull_result->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Remote Sync enabled. Site ID: %s. Pulled %d metadata entries.', 'msh-image-optimizer' ),
				$handshake_result['site_id'],
				count( $pull_result )
			),
		);
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
	 * Perform handshake with Supabase to register/update site.
	 *
	 * @return array|WP_Error Handshake response or error.
	 */
	private function handshake() {
		$license_key = get_option( MSH_License_Manager::LICENSE_KEY_OPTION );

		if ( empty( $license_key ) ) {
			return new WP_Error( 'no_license', __( 'No license key found.', 'msh-image-optimizer' ) );
		}

		$payload = array(
			'license_key'    => $license_key,
			'url'            => home_url(),
			'platform'       => 'wordpress',
			'plugin_version' => defined( 'MSH_IO_VERSION' ) ? MSH_IO_VERSION : '2.0.0',
			'wp_version'     => get_bloginfo( 'version' ),
			'capabilities'   => array( 'field-diff', 'batch-500' ),
		);

		$response = wp_remote_post(
			$this->supabase_url . '/functions/v1/handshake',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->supabase_anon_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'handshake_failed',
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : __( 'Handshake failed.', 'msh-image-optimizer' )
			);
		}

		return $decoded;
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

		$site_id = get_option( self::SITE_ID_OPTION );

		if ( empty( $site_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Site not registered. Please re-enable sync.', 'msh-image-optimizer' ),
			);
		}

		// Get local changes since last sync
		$last_sync     = get_option( self::LAST_SYNC_OPTION, 0 );
		$local_changes = $this->get_local_changes( $last_sync );

		// Push local changes
		$push_result = $this->push_changes( $local_changes, $site_id );

		if ( is_wp_error( $push_result ) ) {
			return array(
				'success' => false,
				'message' => $push_result->get_error_message(),
			);
		}

		// Pull remote changes
		$pull_result = $this->pull_changes();

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
				'pushed' => isset( $push_result['pushed'] ) ? $push_result['pushed'] : 0,
				'pulled' => count( $pull_result ),
			),
		);
	}

	/**
	 * Migrate old cron hook to new hook.
	 *
	 * This ensures existing installations with the old 'msh_auto_sync' hook
	 * get migrated to the new 'msh_auto_sync_cron' hook without breaking auto-sync.
	 *
	 * @return void
	 */
	public function migrate_cron_hook() {
		// Check if migration has already been done
		if ( get_option( 'msh_cron_hook_migrated', false ) ) {
			return;
		}

		// Check if old hook is scheduled
		$old_hook = 'msh_auto_sync';
		$old_timestamp = wp_next_scheduled( $old_hook );

		if ( $old_timestamp ) {
			// Get the recurrence of the old event
			$cron_array = _get_cron_array();
			$recurrence = 'off';

			if ( $cron_array && isset( $cron_array[ $old_timestamp ][ $old_hook ] ) ) {
				foreach ( $cron_array[ $old_timestamp ][ $old_hook ] as $event ) {
					if ( isset( $event['schedule'] ) ) {
						// Map old recurrence to new setting
						if ( 'hourly' === $event['schedule'] ) {
							$recurrence = 'hourly';
						} elseif ( 'twicedaily' === $event['schedule'] ) {
							// Map twicedaily to daily (or keep as daily for backward compat)
							$recurrence = 'daily';
						} elseif ( 'daily' === $event['schedule'] ) {
							$recurrence = 'daily';
						}
						break;
					}
				}
			}

			// Clear old hook
			wp_clear_scheduled_hook( $old_hook );

			// Schedule new hook with the same cadence
			if ( 'off' !== $recurrence ) {
				$new_hook = 'msh_auto_sync_cron';

				// Clear any existing new hook first
				wp_clear_scheduled_hook( $new_hook );

				// Schedule the new hook
				if ( 'hourly' === $recurrence ) {
					wp_schedule_event( time(), 'hourly', $new_hook );
				} elseif ( 'daily' === $recurrence ) {
					wp_schedule_event( time(), 'daily', $new_hook );
				}

				// Save the preference to the option so settings page shows correct value
				update_option( 'msh_auto_sync_schedule', $recurrence, false );
			}
		}

		// Mark migration as complete
		update_option( 'msh_cron_hook_migrated', true, false );
	}

	/**
	 * Automatic sync callback for WP-Cron.
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
	 * Returns consolidated metadata (all fields per media_id/locale).
	 *
	 * @param int $since Unix timestamp.
	 * @return array Array of changed metadata entries.
	 */
	private function get_local_changes( $since ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'optimizer_metadata_cache';

		// Get all cache entries updated since last sync
		// Use current_time to match WordPress timezone handling
		$since_mysql = date( 'Y-m-d H:i:s', $since );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT attachment_id, locale, field, ai_value, manual_value, chosen_source, updated_at
				FROM {$cache_table}
				WHERE updated_at > %s
				ORDER BY attachment_id, locale, field",
				$since_mysql
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		// Consolidate field-based rows into attachment-based records
		$consolidated = array();
		foreach ( $rows as $row ) {
			$key = $row['attachment_id'] . '_' . $row['locale'];

			if ( ! isset( $consolidated[ $key ] ) ) {
				$consolidated[ $key ] = array(
					'media_id'   => (int) $row['attachment_id'],
					'locale'     => $row['locale'],
					'title'      => '',
					'alt'        => '',
					'caption'    => '',
					'description' => '',
					'updated_at' => $row['updated_at'],
				);
			}

			// Get the chosen value based on chosen_source
			$value = ( 'ai' === $row['chosen_source'] ) ? $row['ai_value'] : $row['manual_value'];
			$value = ! empty( $value ) ? $value : '';

			// Assign to appropriate field
			$consolidated[ $key ][ $row['field'] ] = $value;

			// Track most recent update time
			if ( strtotime( $row['updated_at'] ) > strtotime( $consolidated[ $key ]['updated_at'] ) ) {
				$consolidated[ $key ]['updated_at'] = $row['updated_at'];
			}
		}

		// Convert to indexed array
		return array_values( $consolidated );
	}

	/**
	 * Push local changes to Supabase.
	 *
	 * @param array  $changes Array of metadata changes.
	 * @param string $site_id Supabase site ID.
	 * @return array|WP_Error Response or error.
	 */
	private function push_changes( $changes, $site_id ) {
		if ( empty( $changes ) ) {
			return array( 'pushed' => 0, 'conflicts' => array() );
		}

		$license_key = get_option( MSH_License_Manager::LICENSE_KEY_OPTION );

		$payload = array(
			'site_id' => $site_id,
			'changes' => $changes,
		);

		$response = wp_remote_post(
			$this->supabase_url . '/functions/v1/push',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization'  => 'Bearer ' . $this->supabase_anon_key,
					'X-License-Key'  => $license_key,
					'Content-Type'   => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'push_failed',
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : __( 'Push failed.', 'msh-image-optimizer' )
			);
		}

		// Handle conflicts if any
		if ( ! empty( $decoded['conflicts'] ) ) {
			// Log conflicts for manual resolution
			error_log( 'MSH Sync: ' . count( $decoded['conflicts'] ) . ' conflicts detected during push.' );
		}

		return $decoded;
	}

	/**
	 * Pull remote changes from Supabase.
	 *
	 * @return array|WP_Error Remote changes or error.
	 */
	private function pull_changes() {
		$site_id     = get_option( self::SITE_ID_OPTION );
		$license_key = get_option( MSH_License_Manager::LICENSE_KEY_OPTION );
		$cursor      = get_option( self::LAST_SYNC_CURSOR_OPTION, null );

		$payload = array(
			'site_id' => $site_id,
			'limit'   => 100,
		);

		if ( $cursor ) {
			$payload['cursor'] = $cursor;
		}

		$response = wp_remote_post(
			$this->supabase_url . '/functions/v1/pull',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->supabase_anon_key,
					'X-License-Key' => $license_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'pull_failed',
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : __( 'Pull failed.', 'msh-image-optimizer' )
			);
		}

		if ( ! isset( $decoded['changes'] ) ) {
			return array();
		}

		// Update cursor for next pull
		if ( ! empty( $decoded['changes'] ) ) {
			$last_change = end( $decoded['changes'] );
			if ( isset( $last_change['updated_at'] ) ) {
				update_option( self::LAST_SYNC_CURSOR_OPTION, $last_change['updated_at'] );
			}
		}

		// Apply remote changes locally
		$this->apply_remote_changes( $decoded['changes'] );

		return $decoded['changes'];
	}

	/**
	 * Apply remote changes to local database (field-based structure).
	 *
	 * Converts consolidated metadata format (media_id, title, alt, caption, description)
	 * to field-based storage (attachment_id, field, manual_value).
	 *
	 * @param array $changes Remote changes (consolidated format).
	 * @return void
	 */
	private function apply_remote_changes( $changes ) {
		if ( empty( $changes ) ) {
			return;
		}

		global $wpdb;
		$cache_table       = $wpdb->prefix . 'optimizer_metadata_cache';
		$conflict_strategy = get_option( self::CONFLICT_STRATEGY_OPTION, 'local_wins' );
		$last_sync_time    = get_option( self::LAST_SYNC_OPTION, 0 );
		$conflicts         = array();
		$fields            = array( 'title', 'alt', 'caption', 'description' );

		foreach ( $changes as $change ) {
			$attachment_id     = (int) $change['media_id'];
			$locale            = $change['locale'];
			$remote_updated_at = isset( $change['updated_at'] ) ? strtotime( $change['updated_at'] ) : time();

			// Process each field separately (field-based storage)
			foreach ( $fields as $field ) {
				$remote_value = isset( $change[ $field ] ) ? $change[ $field ] : '';

				// Get local field data
				$local = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$cache_table} WHERE attachment_id = %d AND locale = %s AND field = %s",
						$attachment_id,
						$locale,
						$field
					),
					ARRAY_A
				);

				if ( ! $local ) {
					// No local version - safe to insert remote data
					$wpdb->insert(
						$cache_table,
						array(
							'attachment_id' => $attachment_id,
							'locale'        => $locale,
							'field'         => $field,
							'manual_value'  => $remote_value,
							'chosen_source' => 'manual', // Remote synced data is treated as manual
							'created_at'    => current_time( 'mysql' ),
							'updated_at'    => date( 'Y-m-d H:i:s', $remote_updated_at ),
						),
						array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
					);
				} else {
					// Local version exists - check for conflicts
					$local_updated_at = isset( $local['updated_at'] ) ? strtotime( $local['updated_at'] ) : 0;

					// Detect if local data has been modified since last sync
					$local_modified_since_sync = $last_sync_time > 0 && $local_updated_at > $last_sync_time;

					// Determine if this is a conflict (both local and remote changed)
					$is_conflict = $local_modified_since_sync && ( $remote_updated_at > $last_sync_time );

					if ( $is_conflict ) {
						// Log the conflict
						$conflicts[] = array(
							'attachment_id' => $attachment_id,
							'locale'        => $locale,
							'field'         => $field,
							'local_value'   => $local['manual_value'],
							'remote_value'  => $remote_value,
						);
					}

					// Apply conflict resolution strategy
					$should_update = false;

					if ( 'remote_wins' === $conflict_strategy ) {
						// Cloud version wins - always update
						$should_update = true;
					} elseif ( 'local_wins' === $conflict_strategy ) {
						// Local version wins - NEVER overwrite local changes
						// Only update if local hasn't been modified since last sync
						$should_update = ! $local_modified_since_sync;
					} elseif ( 'manual' === $conflict_strategy ) {
						// Manual resolution - don't auto-update conflicts, only update if no local changes
						$should_update = ! $local_modified_since_sync;
					} else {
						// Default to local_wins behavior (safest)
						$should_update = ! $local_modified_since_sync;
					}

					if ( $should_update ) {
						$wpdb->update(
							$cache_table,
							array(
								'manual_value'  => $remote_value,
								'chosen_source' => 'manual', // Remote synced data is treated as manual
								'updated_at'    => date( 'Y-m-d H:i:s', $remote_updated_at ),
							),
							array(
								'attachment_id' => $attachment_id,
								'locale'        => $locale,
								'field'         => $field,
							),
							array( '%s', '%s', '%s' ),
							array( '%d', '%s', '%s' )
						);
					} elseif ( $is_conflict ) {
						// Log that conflict was skipped
						error_log(
							sprintf(
								'MSH Sync: Conflict detected for attachment_id=%d, locale=%s, field=%s. Strategy=%s. Local data protected from overwrite.',
								$attachment_id,
								$locale,
								$field,
								$conflict_strategy
							)
						);
					}
				}
			}
		}

		// Store conflicts for manual review
		if ( ! empty( $conflicts ) ) {
			$existing_conflicts = get_option( 'msh_sync_conflicts', array() );
			if ( ! is_array( $existing_conflicts ) ) {
				$existing_conflicts = array();
			}
			$existing_conflicts = array_merge( $existing_conflicts, $conflicts );
			update_option( 'msh_sync_conflicts', $existing_conflicts );

			error_log( sprintf( 'MSH Sync: %d conflicts detected and logged for manual review.', count( $conflicts ) ) );
		}
	}

	/**
	 * Get sync status for admin display.
	 *
	 * @return array {
	 *     Sync status.
	 *
	 *     @type bool   $enabled       Whether sync is enabled.
	 *     @type string $site_id       Supabase site ID.
	 *     @type int    $last_sync     Last sync timestamp.
	 *     @type string $last_sync_ago Human-readable time since last sync.
	 *     @type int    $pending       Number of pending changes.
	 * }
	 */
	public function get_status() {
		$last_sync = get_option( self::LAST_SYNC_OPTION, 0 );
		$site_id   = get_option( self::SITE_ID_OPTION, '' );
		$pending   = 0;

		if ( $last_sync > 0 ) {
			$changes = $this->get_local_changes( $last_sync );
			$pending = count( $changes );
		}

		return array(
			'enabled'       => $this->is_enabled(),
			'site_id'       => $site_id,
			'last_sync'     => $last_sync,
			'last_sync_ago' => $last_sync > 0 ? human_time_diff( $last_sync ) . ' ago' : __( 'Never', 'msh-image-optimizer' ),
			'pending'       => $pending,
		);
	}

	/**
	 * Get quota status from Supabase.
	 *
	 * @return array|WP_Error Quota data or error.
	 */
	public function get_quota() {
		$license_key = get_option( MSH_License_Manager::LICENSE_KEY_OPTION );

		$response = wp_remote_get(
			$this->supabase_url . '/functions/v1/quota',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->supabase_anon_key,
					'X-License-Key' => $license_key,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'quota_failed',
				isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : __( 'Quota check failed.', 'msh-image-optimizer' )
			);
		}

		return $decoded;
	}
}
