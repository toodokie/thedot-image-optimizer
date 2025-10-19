<?php
/**
 * Event Bus System - Phase 4R+ Core
 *
 * Central event emission and consumption system for metadata orchestration.
 * Emits events when content changes, locales are added, or manual actions occur.
 * Enables async regeneration workers to consume events without tight coupling.
 *
 * Events emitted:
 * - post.updated (when post content changes)
 * - attachment.uploaded (when new image uploaded)
 * - attachment.replaced (when image file replaced)
 * - locale.added (when new locale profile created)
 * - glossary.updated (when glossary terms change)
 * - template.updated (when prompt template changes)
 * - metadata.manual_edit (when user manually edits metadata)
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Event_Bus {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Hook into WordPress events
	 */
	private function __construct() {
		// Post content changes
		add_action( 'save_post', array( $this, 'on_post_updated' ), 10, 3 );

		// Attachment events
		add_action( 'add_attachment', array( $this, 'on_attachment_uploaded' ) );
		add_action( 'wp_update_attachment_metadata', array( $this, 'on_attachment_replaced' ), 10, 2 );

		// Manual metadata edits
		add_action( 'updated_post_meta', array( $this, 'on_metadata_manual_edit' ), 10, 4 );

		// Locale profile changes
		add_action( 'msh_locale_created', array( $this, 'on_locale_added' ), 10, 2 );

		// Glossary changes
		add_action( 'msh_glossary_updated', array( $this, 'on_glossary_updated' ), 10, 2 );

		// Template changes
		add_action( 'msh_template_updated', array( $this, 'on_template_updated' ), 10, 2 );
	}

	/**
	 * Emit event to event log
	 *
	 * @param string $event Event name (e.g., 'post.updated')
	 * @param string $entity_type Entity type ('post', 'attachment', 'site')
	 * @param int|null $entity_id Entity ID (null for site-wide events)
	 * @param array $payload Event payload data
	 * @param string|null $idempotency_key Optional idempotency key to prevent duplicates
	 * @return int|false Event ID or false on failure
	 */
	public function emit( $event, $entity_type, $entity_id = null, $payload = array(), $idempotency_key = null ) {
		global $wpdb;

		// Auto-generate idempotency key if not provided
		if ( null === $idempotency_key ) {
			$idempotency_key = $this->generate_idempotency_key( $event, $entity_type, $entity_id, $payload );
		}

		// Check if this exact event already exists (idempotency)
		$table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table WHERE idempotency_key = %s",
			$idempotency_key
		) );

		if ( $exists ) {
			return (int) $exists;
		}

		// Get current user ID (0 if system-generated)
		$trigger_user_id = get_current_user_id();

		// Insert event
		$result = $wpdb->insert(
			$table,
			array(
				'event'            => $event,
				'entity_type'      => $entity_type,
				'entity_id'        => $entity_id,
				'payload'          => wp_json_encode( $payload ),
				'trigger_user_id'  => $trigger_user_id,
				'idempotency_key'  => $idempotency_key,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$event_id = $wpdb->insert_id;

			// Fire WordPress action for immediate synchronous handlers
			do_action( 'msh_event_emitted', $event, $entity_type, $entity_id, $payload, $event_id );
			do_action( "msh_event_{$event}", $entity_type, $entity_id, $payload, $event_id );

			return $event_id;
		}

		return false;
	}

	/**
	 * Mark event as processed
	 *
	 * @param int $event_id Event ID
	 * @return bool Success
	 */
	public function mark_processed( $event_id ) {
		global $wpdb;

		$table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );
		$result = $wpdb->update(
			$table,
			array( 'processed_at' => current_time( 'mysql' ) ),
			array( 'id' => $event_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Get unprocessed events
	 *
	 * @param string|null $event Filter by event name
	 * @param int $limit Maximum events to fetch
	 * @return array Array of event objects
	 */
	public function get_unprocessed( $event = null, $limit = 100 ) {
		global $wpdb;

		$table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );

		if ( $event ) {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE processed_at IS NULL AND event = %s ORDER BY created_at ASC LIMIT %d",
				$event,
				$limit
			) );
		} else {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE processed_at IS NULL ORDER BY created_at ASC LIMIT %d",
				$limit
			) );
		}

		// Decode payload JSON
		foreach ( $results as &$event_obj ) {
			$event_obj->payload = json_decode( $event_obj->payload, true );
		}

		return $results;
	}

	/**
	 * Generate idempotency key from event data
	 *
	 * @param string $event Event name
	 * @param string $entity_type Entity type
	 * @param int|null $entity_id Entity ID
	 * @param array $payload Event payload
	 * @return string SHA1 hash
	 */
	private function generate_idempotency_key( $event, $entity_type, $entity_id, $payload ) {
		$data = array(
			'event'       => $event,
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
			'payload'     => $payload,
		);

		return sha1( wp_json_encode( $data ) );
	}

	/**
	 * Hook: Post updated
	 */
	public function on_post_updated( $post_id, $post, $update ) {
		// Skip autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only track published posts
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Emit event
		$this->emit(
			'post.updated',
			'post',
			$post_id,
			array(
				'post_type'    => $post->post_type,
				'post_title'   => $post->post_title,
				'post_content' => wp_trim_words( $post->post_content, 50 ),
			)
		);
	}

	/**
	 * Hook: Attachment uploaded
	 */
	public function on_attachment_uploaded( $attachment_id ) {
		// Only track images
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$this->emit(
			'attachment.uploaded',
			'attachment',
			$attachment_id,
			array(
				'mime_type' => get_post_mime_type( $attachment_id ),
				'file_path' => get_attached_file( $attachment_id ),
			)
		);
	}

	/**
	 * Hook: Attachment replaced (file changed)
	 */
	public function on_attachment_replaced( $metadata, $attachment_id ) {
		// Only track images
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		// Check if file was actually replaced (has 'file' key in metadata)
		if ( ! isset( $metadata['file'] ) ) {
			return;
		}

		$this->emit(
			'attachment.replaced',
			'attachment',
			$attachment_id,
			array(
				'new_file' => $metadata['file'],
				'width'    => isset( $metadata['width'] ) ? $metadata['width'] : null,
				'height'   => isset( $metadata['height'] ) ? $metadata['height'] : null,
			)
		);
	}

	/**
	 * Hook: Manual metadata edit
	 */
	public function on_metadata_manual_edit( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Only track image metadata fields
		$tracked_fields = array( '_wp_attachment_image_alt', 'post_title', 'post_excerpt', 'post_content' );
		if ( ! in_array( $meta_key, $tracked_fields, true ) ) {
			return;
		}

		// Only track attachments
		if ( 'attachment' !== get_post_type( $object_id ) ) {
			return;
		}

		// Map meta key to field name
		$field_map = array(
			'_wp_attachment_image_alt' => 'alt',
			'post_title'               => 'title',
			'post_excerpt'             => 'caption',
			'post_content'             => 'description',
		);

		$field = $field_map[ $meta_key ] ?? $meta_key;

		$this->emit(
			'metadata.manual_edit',
			'attachment',
			$object_id,
			array(
				'field'     => $field,
				'new_value' => $meta_value,
			)
		);
	}

	/**
	 * Hook: Locale added
	 */
	public function on_locale_added( $locale_code, $profile_data ) {
		$this->emit(
			'locale.added',
			'site',
			null,
			array(
				'locale_code' => $locale_code,
				'language'    => $profile_data['language'] ?? '',
				'region'      => $profile_data['region'] ?? '',
			)
		);
	}

	/**
	 * Hook: Glossary updated
	 */
	public function on_glossary_updated( $locale_code, $glossary_data ) {
		$this->emit(
			'glossary.updated',
			'site',
			null,
			array(
				'locale_code' => $locale_code,
				'terms_count' => count( $glossary_data ),
			)
		);
	}

	/**
	 * Hook: Template updated
	 */
	public function on_template_updated( $template_id, $template_data ) {
		$this->emit(
			'template.updated',
			'site',
			null,
			array(
				'template_id'   => $template_id,
				'template_name' => $template_data['name'] ?? '',
			)
		);
	}

	/**
	 * Get event statistics
	 *
	 * @return array Statistics
	 */
	public function get_stats() {
		global $wpdb;

		$table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );

		$stats = array(
			'total_events'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
			'unprocessed_events' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE processed_at IS NULL" ),
			'processed_events'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE processed_at IS NOT NULL" ),
		);

		// Get event breakdown
		$event_counts = $wpdb->get_results(
			"SELECT event, COUNT(*) as count FROM $table GROUP BY event ORDER BY count DESC"
		);

		$stats['by_event'] = array();
		foreach ( $event_counts as $row ) {
			$stats['by_event'][ $row->event ] = (int) $row->count;
		}

		return $stats;
	}
}
