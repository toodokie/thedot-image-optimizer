<?php
/**
 * Metadata Versioning and Orchestration System
 *
 * Manages versioned metadata storage per locale with diff, rollback, and manual-edit protection.
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Metadata_Versioning {

	/**
	 * Database table name (without prefix)
	 */
	const TABLE_NAME = 'msh_optimizer_metadata';

	/**
	 * Current database schema version
	 */
	const SCHEMA_VERSION = 2;

	/**
	 * Singleton instance
	 *
	 * @var MSH_Metadata_Versioning|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 2.0.0
	 * @return MSH_Metadata_Versioning
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Register hooks
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create database table if it doesn't exist
	 *
	 * @since 2.0.0
	 * @return bool True if table was created or already exists
	 */
	public function maybe_create_table() {
		$installed_version = get_option( 'msh_metadata_versioning_schema_version', 0 );

		if ( $installed_version >= self::SCHEMA_VERSION ) {
			return true; // Already up to date
		}

		global $wpdb;
		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            media_id BIGINT(20) UNSIGNED NOT NULL,
            locale VARCHAR(10) NOT NULL DEFAULT 'en',
            field VARCHAR(20) NOT NULL,
            value LONGTEXT NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'ai',
            version INT UNSIGNED NOT NULL DEFAULT 1,
            checksum CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            version_notes TEXT NULL,
            approved_by BIGINT(20) UNSIGNED NULL,
            approved_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_version (media_id, locale, field, version),
            KEY media_locale (media_id, locale),
            KEY media_field (media_id, field),
            KEY source_idx (source),
            KEY created_idx (created_at)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		) === $table_name;

		if ( $table_exists ) {
			$wpdb->query(
				"UPDATE {$table_name} SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'"
			);
			update_option( 'msh_metadata_versioning_schema_version', self::SCHEMA_VERSION );
			error_log( '[MSH Versioning] Database table created successfully: ' . $table_name );
			return true;
		} else {
			error_log( '[MSH Versioning] Failed to create database table: ' . $table_name );
			return false;
		}
	}

	/**
	 * Save a new metadata version
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code (e.g., 'en', 'es', 'fr-CA')
	 * @param string $field    Metadata field (title, alt, caption, description, filename)
	 * @param string $value    Metadata value
	 * @param string $source   Source type (ai, template, manual, import)
	 * @return int|false Version ID on success, false on failure
	 */
	public function save_version( $media_id, $locale, $field, $value, $source = 'ai', $args = array() ) {
		global $wpdb;

		// Validate inputs
		$media_id = absint( $media_id );
		if ( ! $media_id ) {
			error_log( '[MSH Versioning] Invalid media_id: ' . $media_id );
			return false;
		}

		$allowed_fields = array( 'title', 'alt', 'caption', 'description', 'filename' );
		if ( ! in_array( $field, $allowed_fields, true ) ) {
			error_log( '[MSH Versioning] Invalid field: ' . $field );
			return false;
		}

		$allowed_sources = array( 'ai', 'template', 'manual', 'import' );
		if ( ! in_array( $source, $allowed_sources, true ) ) {
			error_log( '[MSH Versioning] Invalid source: ' . $source );
			return false;
		}

		// Get next version number
		$current_version = $this->get_latest_version_number( $media_id, $locale, $field );
		$next_version    = $current_version + 1;

		// Generate checksum
		$checksum = hash( 'sha256', $value );

		// Prepare extra metadata.
		$version_notes = '';
		$approved_by   = null;
		$approved_at   = null;

		if ( ! empty( $args ) && is_array( $args ) ) {
			if ( isset( $args['version_notes'] ) ) {
				$version_notes = wp_kses_post( $args['version_notes'] );
			}
			if ( isset( $args['approved_by'] ) ) {
				$approved_by = absint( $args['approved_by'] );
			}
			if ( isset( $args['approved_at'] ) ) {
				$approved_at = sanitize_text_field( $args['approved_at'] );
			}
		}

		$current_time = current_time( 'mysql' );

		// Insert new version
		$result = $wpdb->insert(
			$this->get_table_name(),
			array(
				'media_id'   => $media_id,
				'locale'     => sanitize_text_field( $locale ),
				'field'      => $field,
				'value'      => $value,
				'source'     => $source,
				'version'    => $next_version,
				'checksum'   => $checksum,
				'created_at'   => $current_time,
				'updated_at'   => $current_time,
				'version_notes'=> $version_notes,
				'approved_by'  => $approved_by ? $approved_by : null,
				'approved_at'  => $approved_at ? $approved_at : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( $result === false ) {
			error_log( '[MSH Versioning] Failed to insert version: ' . $wpdb->last_error );
			return false;
		}

		$version_id = $wpdb->insert_id;

		error_log(
			sprintf(
				'[MSH Versioning] Saved version #%d for media_id=%d, locale=%s, field=%s, source=%s',
				$next_version,
				$media_id,
				$locale,
				$field,
				$source
			)
		);

		return $version_id;
	}

	/**
	 * Get the latest version number for a specific media/locale/field
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code
	 * @param string $field    Metadata field
	 * @return int Version number (0 if no versions exist)
	 */
	public function get_latest_version_number( $media_id, $locale, $field ) {
		global $wpdb;

		$version = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version) FROM {$this->get_table_name()}
            WHERE media_id = %d AND locale = %s AND field = %s",
				$media_id,
				$locale,
				$field
			)
		);

		return $version ? absint( $version ) : 0;
	}

	/**
	 * Get the active (latest) version for a specific media/locale/field
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code
	 * @param string $field    Metadata field
	 * @return array|null Version record or null if not found
	 */
	public function get_active_version( $media_id, $locale, $field ) {
		global $wpdb;

		$version = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()}
            WHERE media_id = %d AND locale = %s AND field = %s
            ORDER BY version DESC
            LIMIT 1",
				$media_id,
				$locale,
				$field
			),
			ARRAY_A
		);

		return $version;
	}

	/**
	 * Get full version history for a specific media/locale/field
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code
	 * @param string $field    Metadata field
	 * @return array Array of version records, ordered newest first
	 */
	public function get_version_history( $media_id, $locale, $field ) {
		global $wpdb;

		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()}
            WHERE media_id = %d AND locale = %s AND field = %s
            ORDER BY version DESC",
				$media_id,
				$locale,
				$field
			),
			ARRAY_A
		);

		return $history ? $history : array();
	}

	/**
	 * Get a specific version by version number
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code
	 * @param string $field    Metadata field
	 * @param int    $version  Version number
	 * @return array|null Version record or null if not found
	 */
	public function get_version( $media_id, $locale, $field, $version ) {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()}
            WHERE media_id = %d AND locale = %s AND field = %s AND version = %d",
				$media_id,
				$locale,
				$field,
				$version
			),
			ARRAY_A
		);

		return $record;
	}

	/**
	 * Get a version record by database ID.
	 *
	 * @since 2.1.0
	 * @param int $version_id Version row ID.
	 * @return array|null
	 */
	public function get_version_by_id( $version_id ) {
		global $wpdb;

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d",
				absint( $version_id )
			),
			ARRAY_A
		);

		return $record;
	}

	/**
	 * Update metadata for a specific version.
	 *
	 * @since 2.1.0
	 * @param int   $version_id Version identifier.
	 * @param array $data       Key/value pairs to update.
	 * @return bool
	 */
	public function update_version( $version_id, $data ) {
		global $wpdb;

		$allowed_keys = array( 'version_notes', 'approved_by', 'approved_at', 'value', 'source', 'checksum', 'updated_at' );
		$clean_data   = array();
		$formats      = array();
		$set_null     = array();

		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}

			switch ( $key ) {
				case 'approved_by':
					if ( is_null( $value ) || '' === $value ) {
						$set_null[] = $key;
					} else {
						$clean_data[ $key ] = absint( $value );
						$formats[]          = '%d';
					}
					break;
				case 'approved_at':
				case 'updated_at':
					$clean_data[ $key ] = $value ? sanitize_text_field( $value ) : null;
					$formats[]          = '%s';
					break;
				case 'version_notes':
					$clean_data[ $key ] = wp_kses_post( $value );
					$formats[]          = '%s';
					break;
				default:
					$clean_data[ $key ] = $value;
					$formats[]          = '%s';
			}
		}

		$result = true;

		if ( ! empty( $clean_data ) ) {
			$result = $wpdb->update(
				$this->get_table_name(),
				$clean_data,
				array( 'id' => absint( $version_id ) ),
				$formats,
				array( '%d' )
			);
		}

		if ( false === $result ) {
			return false;
		}

		if ( ! empty( $set_null ) ) {
			foreach ( $set_null as $column ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$this->get_table_name()} SET {$column} = NULL WHERE id = %d",
						absint( $version_id )
					)
				);
			}
		}

		return true;
	}

	/**
	 * Compare two versions and return differences
	 *
	 * @since 2.0.0
	 * @param int    $media_id  Attachment ID
	 * @param string $locale    Locale code
	 * @param string $field     Metadata field
	 * @param int    $version_a First version number
	 * @param int    $version_b Second version number
	 * @return array|null Diff object with both versions and comparison, or null on error
	 */
	public function compare_versions( $media_id, $locale, $field, $version_a, $version_b ) {
		$ver_a = $this->get_version( $media_id, $locale, $field, $version_a );
		$ver_b = $this->get_version( $media_id, $locale, $field, $version_b );

		if ( ! $ver_a || ! $ver_b ) {
			return null;
		}

		return array(
			'version_a'      => $ver_a,
			'version_b'      => $ver_b,
			'values_match'   => $ver_a['checksum'] === $ver_b['checksum'],
			'value_diff'     => array(
				'from' => $ver_a['value'],
				'to'   => $ver_b['value'],
			),
			'source_changed' => $ver_a['source'] !== $ver_b['source'],
		);
	}

	/**
	 * Get diff between AI-generated and manual versions
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code
	 * @return array Array of diffs per field
	 */
	public function get_ai_vs_manual_diff( $media_id, $locale ) {
		$fields = array( 'title', 'alt', 'caption', 'description', 'filename' );
		$diffs  = array();

		foreach ( $fields as $field ) {
			$history = $this->get_version_history( $media_id, $locale, $field );
			if ( empty( $history ) ) {
				continue;
			}

			// Find latest AI and latest manual versions
			$latest_ai     = null;
			$latest_manual = null;

			foreach ( $history as $version ) {
				if ( $version['source'] === 'ai' && ! $latest_ai ) {
					$latest_ai = $version;
				}
				if ( $version['source'] === 'manual' && ! $latest_manual ) {
					$latest_manual = $version;
				}
				if ( $latest_ai && $latest_manual ) {
					break;
				}
			}

			$diffs[ $field ] = array(
				'active'           => $history[0], // Latest version
				'ai'               => $latest_ai,
				'manual'           => $latest_manual,
				'has_manual'       => ! empty( $latest_manual ),
				'manual_is_active' => $latest_manual && $history[0]['version'] === $latest_manual['version'],
			);
		}

		return $diffs;
	}

	/**
	 * Check if a value already exists to prevent duplicate versions
	 *
	 * @since 2.0.0
	 * @param int    $media_id Attachment ID
	 * @param string $locale   Locale code
	 * @param string $field    Metadata field
	 * @param string $value    Value to check
	 * @return bool True if this exact value already exists in latest version
	 */
	public function value_exists( $media_id, $locale, $field, $value ) {
		$active = $this->get_active_version( $media_id, $locale, $field );
		if ( ! $active ) {
			return false;
		}

		$checksum = hash( 'sha256', $value );
		return $active['checksum'] === $checksum;
	}
}
