<?php
/**
 * Metadata Core Service - Phase 4R+
 *
 * Provides atomic access to the metadata cache and version history tables
 * with row-level locking to prevent race conditions.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Metadata_Core {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Metadata_Core|null
	 */
	private static $instance = null;

	/**
	 * Cache table name.
	 *
	 * @var string
	 */
	private $cache_table;

	/**
	 * Versions table name.
	 *
	 * @var string
	 */
	private $versions_table;

	/**
	 * Sync table name.
	 *
	 * @var string
	 */
	private $sync_table;

	/**
	 * Supported metadata fields.
	 *
	 * @var string[]
	 */
	private $fields = array( 'title', 'alt', 'caption', 'description' );

	/**
	 * Allowed stale reasons.
	 *
	 * @var string[]
	 */
	private $stale_reasons = array(
		'fresh',
		'context_changed',
		'locale_updated',
		'glossary_changed',
		'template_changed',
		'file_replaced',
		'manual_override',
	);

	/**
	 * Get singleton instance.
	 *
	 * @since 2.1.0
	 *
	 * @return MSH_Metadata_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 */
	private function __construct() {
		$this->cache_table    = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
		$this->versions_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_VERSIONS );
		$this->sync_table     = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_SYNC );
	}

	/**
	 * Fetch a cache row.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @return array|null
	 */
	public function get_cache( $attachment_id, $locale, $field ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$locale        = $this->sanitize_locale( $locale );
		$field         = $this->sanitize_field( $field );

		if ( ! $attachment_id || ! $field ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->cache_table} WHERE attachment_id = %d AND locale = %s AND field = %s",
				$attachment_id,
				$locale,
				$field
			),
			ARRAY_A
		);
	}

	/**
	 * Update or insert a cache record using SELECT ... FOR UPDATE.
	 *
	 * @since 2.1.0
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string      $locale        Locale code.
	 * @param string      $field         Field name.
	 * @param string|null $ai_value      AI generated value.
	 * @param string|null $manual_value  Manual value.
	 * @param string      $chosen_source Chosen source (`ai` or `manual`).
	 * @param string|null $fingerprint   Fingerprint hash.
	 * @param string      $stale_reason  Stale reason enum.
	 * @param string|null $ai_model      AI model identifier.
	 * @return int|WP_Error Cache ID or error.
	 */
	public function update_cache( $attachment_id, $locale, $field, $ai_value, $manual_value, $chosen_source, $fingerprint, $stale_reason = 'fresh', $ai_model = null ) {
		global $wpdb;

		$attachment_id = absint( $attachment_id );
		$locale        = $this->sanitize_locale( $locale );
		$field         = $this->sanitize_field( $field );
		$chosen_source = in_array( $chosen_source, array( 'ai', 'manual' ), true ) ? $chosen_source : 'manual';
		$stale_reason  = $this->sanitize_stale_reason( $stale_reason );
		$ai_model      = $ai_model ? sanitize_text_field( $ai_model ) : null;

		if ( ! $attachment_id || ! $field ) {
			return new WP_Error( 'msh_metadata_core_invalid_params', __( 'Invalid metadata cache parameters.', 'msh-image-optimizer' ) );
		}

		$ai_value     = $this->sanitize_value( $ai_value );
		$manual_value = $this->sanitize_value( $manual_value );
		$fingerprint  = $fingerprint ? sanitize_text_field( $fingerprint ) : null;
		$now          = current_time( 'mysql' );

		$transaction_started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $transaction_started ) {
			return new WP_Error( 'msh_metadata_transaction_failed', $wpdb->last_error );
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->cache_table} WHERE attachment_id = %d AND locale = %s AND field = %s FOR UPDATE",
				$attachment_id,
				$locale,
				$field
			),
			ARRAY_A
		);

		if ( false === $row ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'msh_metadata_cache_select_failed', $wpdb->last_error );
		}

		if ( $row ) {
			$result = $wpdb->update(
				$this->cache_table,
				array(
					'ai_value'          => $ai_value,
					'manual_value'      => $manual_value,
					'chosen_source'     => $chosen_source,
					'input_fingerprint' => $fingerprint,
					'stale_reason'      => $stale_reason,
					'ai_model'          => $ai_model,
					'updated_at'        => $now,
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'msh_metadata_cache_update_failed', $wpdb->last_error );
			}

			$wpdb->query( 'COMMIT' );

			return (int) $row['id'];
		}

		$result = $wpdb->insert(
			$this->cache_table,
			array(
				'attachment_id'      => $attachment_id,
				'locale'             => $locale,
				'field'              => $field,
				'ai_value'          => $ai_value,
				'manual_value'      => $manual_value,
				'chosen_source'     => $chosen_source,
				'input_fingerprint' => $fingerprint,
				'stale_reason'      => $stale_reason,
				'ai_model'          => $ai_model,
				'updated_at'        => $now,
				'created_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'msh_metadata_cache_insert_failed', $wpdb->last_error );
		}

		$cache_id = (int) $wpdb->insert_id;
		$wpdb->query( 'COMMIT' );

		return $cache_id;
	}

	/**
	 * Create a new version entry for cache row.
	 *
	 * @since 2.1.0
	 *
	 * @param int         $cache_id    Cache row ID.
	 * @param string      $source      Source identifier.
	 * @param string|null $value       Metadata value.
	 * @param string|null $fingerprint Fingerprint hash.
	 * @param string|null $notes       Optional notes.
	 * @return int|WP_Error
	 */
	public function create_version( $cache_id, $source, $value, $fingerprint = null, $notes = null ) {
		global $wpdb;

		$cache_id   = absint( $cache_id );
		$source     = in_array( $source, array( 'ai', 'manual', 'import' ), true ) ? $source : 'ai';
		$value      = $this->sanitize_value( $value );
		$fingerprint = $fingerprint ? sanitize_text_field( $fingerprint ) : null;
		$notes      = $notes ? sanitize_text_field( $notes ) : null;

		if ( ! $cache_id ) {
			return new WP_Error( 'msh_metadata_version_invalid_cache', __( 'Invalid cache reference for version creation.', 'msh-image-optimizer' ) );
		}

		$current_version = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version) FROM {$this->versions_table} WHERE cache_id = %d",
				$cache_id
			)
		);

		$version_number = $current_version + 1;

		$result = $wpdb->insert(
			$this->versions_table,
			array(
				'cache_id'         => $cache_id,
				'version'          => $version_number,
				'source'           => $source,
				'value'            => $value,
				'input_fingerprint'=> $fingerprint,
				'created_at'       => current_time( 'mysql' ),
				'notes'            => $notes,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'msh_metadata_version_insert_failed', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get version history for cache row.
	 *
	 * @since 2.1.0
	 *
	 * @param int $cache_id Cache row ID.
	 * @param int $limit    Maximum number of versions.
	 * @return array
	 */
	public function get_versions( $cache_id, $limit = 10 ) {
		global $wpdb;

		$cache_id = absint( $cache_id );
		$limit    = max( 1, absint( $limit ) );

		if ( ! $cache_id ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->versions_table} WHERE cache_id = %d ORDER BY version DESC LIMIT %d",
				$cache_id,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get the chosen metadata value.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @return string
	 */
	public function get_value( $attachment_id, $locale, $field ) {
		$row = $this->get_cache( $attachment_id, $locale, $field );

		if ( empty( $row ) ) {
			return '';
		}

		return 'ai' === $row['chosen_source'] ? (string) $row['ai_value'] : (string) $row['manual_value'];
	}

	/**
	 * Mark cache entry as stale.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @param string $reason        Staleness reason.
	 * @return bool|WP_Error
	 */
	public function mark_stale( $attachment_id, $locale, $field, $reason = 'context_changed' ) {
		$reason = $this->sanitize_stale_reason( $reason );

		$row = $this->get_cache( $attachment_id, $locale, $field );

		$result = $this->update_cache(
			$attachment_id,
			$locale,
			sanitize_key( $field ),
			$row['ai_value'] ?? null,
			$row['manual_value'] ?? null,
			$row['chosen_source'] ?? 'manual',
			$row['input_fingerprint'] ?? null,
			$reason,
			$row['ai_model'] ?? null
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Determine if cache entry is marked stale.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @return bool
	 */
	public function is_stale( $attachment_id, $locale, $field ) {
		$row = $this->get_cache( $attachment_id, $locale, $field );

		if ( empty( $row ) ) {
			return true;
		}

		return 'fresh' !== $row['stale_reason'];
	}

	/**
	 * Expose sync table name for other services.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_sync_table_name() {
		return $this->sync_table;
	}

	/**
	 * Sanitize locale.
	 *
	 * @param string $locale Locale string.
	 * @return string
	 */
	private function sanitize_locale( $locale ) {
		$locale = $locale ? sanitize_text_field( $locale ) : get_locale();

		return substr( $locale, 0, 12 );
	}

	/**
	 * Ensure field is supported.
	 *
	 * @param string $field Field value.
	 * @return string|null
	 */
	private function sanitize_field( $field ) {
		$field = sanitize_key( $field );

		if ( in_array( $field, $this->fields, true ) ) {
			return $field;
		}

		return null;
	}

	/**
	 * Sanitize metadata value.
	 *
	 * @param string|null $value Raw value.
	 * @return string|null
	 */
	private function sanitize_value( $value ) {
		if ( null === $value ) {
			return null;
		}

		return wp_kses_post( $value );
	}

	/**
	 * Normalize stale reason.
	 *
	 * @param string $reason Raw reason.
	 * @return string
	 */
	private function sanitize_stale_reason( $reason ) {
		$reason = sanitize_key( $reason );

		if ( ! in_array( $reason, $this->stale_reasons, true ) ) {
			$reason = 'context_changed';
		}

		return $reason;
	}
}
