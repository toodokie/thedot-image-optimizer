<?php
/**
 * Staleness Engine - Phase 4R+
 *
 * Consumes optimizer events, compares fingerprints, and queues regeneration
 * work when upstream signals change.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Staleness_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Staleness_Engine|null
	 */
	private static $instance = null;

	/**
	 * Metadata core service.
	 *
	 * @var MSH_Metadata_Core
	 */
	private $metadata_core;

	/**
	 * Fingerprint builder.
	 *
	 * @var MSH_Fingerprint_Builder
	 */
	private $fingerprint_builder;

	/**
	 * Event bus.
	 *
	 * @var MSH_Event_Bus
	 */
	private $event_bus;

	/**
	 * Supported metadata fields.
	 *
	 * @var string[]
	 */
	private $fields = array( 'title', 'alt', 'caption', 'description' );

	/**
	 * Get singleton instance.
	 *
	 * @since 2.1.0
	 *
	 * @return MSH_Staleness_Engine
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
		$this->metadata_core       = MSH_Metadata_Core::get_instance();
		$this->fingerprint_builder = MSH_Fingerprint_Builder::get_instance();
		$this->event_bus           = MSH_Event_Bus::get_instance();
	}

	/**
	 * Process an event from the event bus.
	 *
	 * @since 2.1.0
	 *
	 * @param int $event_id Event ID.
	 * @return bool
	 */
	public function process_event( $event_id ) {
		global $wpdb;

		$event_id = absint( $event_id );
		if ( ! $event_id ) {
			return false;
		}

		$events_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );
		$event        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$events_table} WHERE id = %d",
				$event_id
			)
		);

		if ( ! $event ) {
			return false;
		}

		$payload = json_decode( $event->payload ?? '[]', true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$attachment_id = isset( $payload['attachment_id'] ) ? absint( $payload['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			return false;
		}

		$locales = isset( $payload['locales'] ) ? (array) $payload['locales'] : array();
		if ( empty( $locales ) ) {
			$locales[] = $payload['locale'] ?? get_locale();
		}

		$fields = isset( $payload['suspected_fields'] ) ? array_map( 'sanitize_key', (array) $payload['suspected_fields'] ) : $this->fields;
		$fields = array_values( array_intersect( $this->fields, $fields ) );
		if ( empty( $fields ) ) {
			$fields = $this->fields;
		}

		$reason = isset( $payload['reason'] ) ? sanitize_key( $payload['reason'] ) : 'context_changed';

		foreach ( $locales as $locale ) {
			$locale = sanitize_text_field( $locale );

			foreach ( $fields as $field ) {
				$this->check_staleness( $attachment_id, $locale, $field, $reason );
			}
		}

		$this->event_bus->mark_processed( $event_id );

		return true;
	}

	/**
	 * Compare fingerprints and mark regeneration when stale.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @param string $reason        Reason identifier.
	 * @return bool True when regeneration queued.
	 */
	public function check_staleness( $attachment_id, $locale, $field, $reason = 'context_changed' ) {
		$current_fingerprint  = $this->fingerprint_builder->build_fingerprint( $attachment_id, $locale, $field );
		$cache_row             = $this->metadata_core->get_cache( $attachment_id, $locale, $field );
		$existing_fingerprint  = $cache_row['input_fingerprint'] ?? '';

		if ( empty( $current_fingerprint ) ) {
			return false;
		}

		if ( $current_fingerprint !== $existing_fingerprint ) {
			$this->queue_regeneration( $attachment_id, $locale, $field, $reason, $current_fingerprint, $cache_row );
			return true;
		}

		if ( $cache_row && 'fresh' !== $cache_row['stale_reason'] ) {
			$this->metadata_core->update_cache(
				$attachment_id,
				$locale,
				$field,
				$cache_row['ai_value'],
				$cache_row['manual_value'],
				$cache_row['chosen_source'],
				$current_fingerprint,
				'fresh',
				$cache_row['ai_model']
			);
		}

		return false;
	}

	/**
	 * Mark metadata as stale and emit regeneration event.
	 *
	 * @since 2.1.0
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param string     $locale        Locale code.
	 * @param string     $field         Field name.
	 * @param string     $reason        Reason identifier.
	 * @param string     $fingerprint   Latest fingerprint.
	 * @param array|null $cache_row     Existing cache row.
	 * @return void
	 */
	public function queue_regeneration( $attachment_id, $locale, $field, $reason, $fingerprint, $cache_row = null ) {
		$reason = sanitize_key( $reason );

		if ( ! $cache_row ) {
			$cache_row = $this->metadata_core->get_cache( $attachment_id, $locale, $field );
		}

		$this->metadata_core->update_cache(
			$attachment_id,
			$locale,
			$field,
			$cache_row['ai_value'] ?? null,
			$cache_row['manual_value'] ?? null,
			$cache_row['chosen_source'] ?? 'manual',
			$fingerprint,
			$reason,
			$cache_row['ai_model'] ?? null
		);

		$this->event_bus->emit(
			'metadata.regen_queued',
			'attachment',
			$attachment_id,
			array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'field'         => $field,
				'reason'        => $reason,
			)
		);
	}

	/**
	 * Determine if metadata is currently stale.
	 *
	 * @since 2.1.0
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @return bool
	 */
	public function is_stale( $attachment_id, $locale, $field ) {
		return $this->metadata_core->is_stale( $attachment_id, $locale, $field );
	}
}
