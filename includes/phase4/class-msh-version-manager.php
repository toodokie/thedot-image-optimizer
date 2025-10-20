<?php
/**
 * Phase 4 - Advanced Metadata Version Manager.
 *
 * Adds enhanced version history features including diffing, notes, rollback,
 * conflict detection, and locking across locales.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Version_Manager
 */
class MSH_Version_Manager {

	/**
	 * Option key used to persist lock TTL.
	 */
	const LOCK_TTL = 300;

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Version_Manager|null
	 */
	private static $instance = null;

	/**
	 * Metadata versioning service.
	 *
	 * @var MSH_Metadata_Versioning
	 */
	private $versioning;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Version_Manager
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
		$this->versioning = MSH_Metadata_Versioning::get_instance();

		add_action( 'delete_attachment', array( $this, 'release_all_locks_for_media' ) );
	}

	/**
	 * Retrieve version history grouped by field for a locale.
	 *
	 * @param int    $media_id Attachment ID.
	 * @param string $locale   Locale code.
	 * @return array
	 */
	public function get_history_for_locale( $media_id, $locale ) {
		$fields  = array( 'title', 'alt', 'caption', 'description', 'filename' );
		$history = array();

		foreach ( $fields as $field ) {
			$history[ $field ] = $this->versioning->get_version_history( $media_id, $locale, $field );
		}

		return $history;
	}

	/**
	 * Append a note to an existing version.
	 *
	 * @param int    $version_id  Version record ID.
	 * @param int    $user_id     User ID creating the note.
	 * @param string $note        Note content.
	 * @return bool
	 */
	public function append_note( $version_id, $user_id, $note ) {
		$record = $this->versioning->get_version_by_id( $version_id );
		if ( ! $record ) {
			return false;
		}

		$note = trim( wp_kses_post( $note ) );
		if ( '' === $note ) {
			return false;
		}

		$notes = $record['version_notes'];
		if ( empty( $notes ) ) {
			$notes = array();
		} else {
			$decoded = json_decode( $notes, true );
			if ( is_array( $decoded ) ) {
				$notes = $decoded;
			} else {
				$notes = array(
					array(
						'user_id'  => 0,
						'user'     => '',
						'time'     => $record['updated_at'],
						'message'  => $record['version_notes'],
						'legacy'   => true,
					),
				);
			}
		}

		$user = get_user_by( 'id', $user_id );
		$notes[] = array(
			'user_id' => $user ? $user->ID : 0,
			'user'    => $user ? $user->display_name : '',
			'time'    => current_time( 'mysql' ),
			'message' => $note,
		);

		return $this->versioning->update_version(
			$version_id,
			array(
				'version_notes' => wp_json_encode( $notes ),
				'updated_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get decoded notes.
	 *
	 * @param array $version Version record.
	 * @return array
	 */
	public function get_notes( $version ) {
		if ( empty( $version['version_notes'] ) ) {
			return array();
		}

		$notes = json_decode( $version['version_notes'], true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				array(
					'user'    => '',
					'user_id' => 0,
					'time'    => $version['updated_at'],
					'message' => $version['version_notes'],
					'legacy'  => true,
				),
			);
		}

		return $notes;
	}

	/**
	 * Generate diff HTML between two versions.
	 *
	 * @param int $from_version_id Version ID for baseline.
	 * @param int $to_version_id   Version ID for compare.
	 * @return string
	 */
	public function get_diff_html( $from_version_id, $to_version_id ) {
		$from = $this->versioning->get_version_by_id( $from_version_id );
		$to   = $this->versioning->get_version_by_id( $to_version_id );

		if ( ! $from || ! $to ) {
			return '';
		}

		if ( ! function_exists( 'wp_text_diff' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		return wp_text_diff( $from['value'], $to['value'], array( 'show_split_view' => true ) );
	}

	/**
	 * Roll back metadata to a previous version by cloning its value.
	 *
	 * @param int    $version_id Target version ID.
	 * @param int    $user_id    Initiating user ID.
	 * @param string $note       Optional note.
	 * @return int|false New version ID or false.
	 */
	public function rollback_to_version( $version_id, $user_id, $note = '' ) {
		$target = $this->versioning->get_version_by_id( $version_id );
		if ( ! $target ) {
			return false;
		}

		$user      = $user_id ? get_user_by( 'id', $user_id ) : null;
		$user_name = $user ? $user->display_name : __( 'System', 'msh-image-optimizer' );

		$note_text = sprintf(
			/* translators: %s: user display name */
			__( 'Rolled back to version %1$d by %2$s', 'msh-image-optimizer' ),
			$target['version'],
			$user_name
		);

		if ( ! empty( $note ) ) {
			$note_text .= "\n" . $note;
		}

		return $this->versioning->save_version(
			$target['media_id'],
			$target['locale'],
			$target['field'],
			$target['value'],
			'rollback',
			array(
				'version_notes' => wp_json_encode(
					array(
						array(
							'user_id' => $user ? $user->ID : 0,
							'user'    => $user_name,
							'time'    => current_time( 'mysql' ),
							'message' => $note_text,
						),
					)
				),
			)
		);
	}

	/**
	 * Check for version conflicts.
	 *
	 * @param int $media_id         Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Metadata field.
	 * @param int $baseline_version Baseline version number from editor.
	 * @return array
	 */
	public function detect_conflict( $media_id, $locale, $field, $baseline_version ) {
		$baseline_version = absint( $baseline_version );
		$latest           = $this->versioning->get_active_version( $media_id, $locale, $field );

		if ( ! $latest ) {
			return array(
				'has_conflict' => false,
				'latest'       => null,
			);
		}

		$has_conflict = (int) $latest['version'] > $baseline_version;

		return array(
			'has_conflict'       => $has_conflict,
			'latest_version'     => $latest['version'],
			'latest_value_hash'  => $latest['checksum'],
			'latest_version_id'  => $latest['id'],
			'latest_version_row' => $latest,
		);
	}

	/**
	 * Acquire an editing lock.
	 *
	 * @param int    $media_id Attachment ID.
	 * @param string $locale   Locale code.
	 * @param string $field    Metadata field.
	 * @param int    $user_id  User requesting lock.
	 * @return array Lock details.
	 */
	public function acquire_lock( $media_id, $locale, $field, $user_id ) {
		$key = $this->get_lock_key( $media_id, $locale, $field );
		$lock = get_transient( $key );

		if ( $lock && (int) $lock['user_id'] !== (int) $user_id ) {
			return array(
				'acquired' => false,
				'lock'     => $lock,
			);
		}

		$user  = get_user_by( 'id', $user_id );
		$lock  = array(
			'user_id'   => $user ? $user->ID : 0,
			'user_name' => $user ? $user->display_name : '',
			'acquired'  => current_time( 'mysql' ),
		);
		set_transient( $key, $lock, self::LOCK_TTL );

		return array(
			'acquired' => true,
			'lock'     => $lock,
		);
	}

	/**
	 * Refresh lock TTL.
	 *
	 * @param int    $media_id Attachment ID.
	 * @param string $locale   Locale code.
	 * @param string $field    Metadata field.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public function refresh_lock( $media_id, $locale, $field, $user_id ) {
		$key  = $this->get_lock_key( $media_id, $locale, $field );
		$lock = get_transient( $key );

		if ( ! $lock || (int) $lock['user_id'] !== (int) $user_id ) {
			return false;
		}

		set_transient( $key, $lock, self::LOCK_TTL );
		return true;
	}

	/**
	 * Release a lock.
	 *
	 * @param int    $media_id Attachment ID.
	 * @param string $locale   Locale.
	 * @param string $field    Field.
	 * @param int    $user_id  User ID.
	 * @return void
	 */
	public function release_lock( $media_id, $locale, $field, $user_id ) {
		$key  = $this->get_lock_key( $media_id, $locale, $field );
		$lock = get_transient( $key );

		if ( $lock && (int) $lock['user_id'] === (int) $user_id ) {
			delete_transient( $key );
		}
	}

	/**
	 * Release all locks for a media item when deleted.
	 *
	 * @param int $media_id Media ID.
	 * @return void
	 */
	public function release_all_locks_for_media( $media_id ) {
		global $wpdb;

		$patterns = array(
			'_msh_lock_' . absint( $media_id ) . '_%',
		);

		foreach ( $patterns as $pattern ) {
			// Direct transient deletion to avoid scanning entire transient table.
			$like = $wpdb->esc_like( $pattern );
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_' . $like ) );

			if ( empty( $rows ) ) {
				continue;
			}

			foreach ( $rows as $option_name ) {
				$key = str_replace( '_transient_', '', $option_name );
				delete_transient( $key );
			}
		}
	}

	/**
	 * Helper to build lock key.
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale   Locale.
	 * @param string $field    Field.
	 * @return string
	 */
	private function get_lock_key( $media_id, $locale, $field ) {
		return sprintf(
			'msh_lock_%d_%s_%s',
			absint( $media_id ),
			sanitize_key( $locale ),
			sanitize_key( $field )
		);
	}
}
