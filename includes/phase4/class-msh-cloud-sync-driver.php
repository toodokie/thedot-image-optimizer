<?php
/**
 * Cloud Sync Driver - Phase 4R+
 *
 * Implements a pluggable interface with a default Amazon S3 driver for
 * exporting and importing metadata snapshots.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignoreFile -- AWS SDK classes are referenced conditionally.

/**
 * Cloud sync driver interface.
 *
 * @since 2.1.0
 */
interface MSH_Cloud_Sync_Driver_Interface {

	/**
	 * Push metadata to remote storage.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return true|WP_Error
	 */
	public function push( $attachment_id, $locale );

	/**
	 * Pull metadata from remote storage.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return true|WP_Error
	 */
	public function pull( $attachment_id, $locale );

	/**
	 * Retrieve remote object ETag.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return string|WP_Error
	 */
	public function get_etag( $attachment_id, $locale );
}

/**
 * Default S3 cloud sync driver.
 *
 * @since 2.1.0
 */
class MSH_Cloud_Sync_Driver implements MSH_Cloud_Sync_Driver_Interface {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Cloud_Sync_Driver|null
	 */
	private static $instance = null;

	/**
	 * Metadata core.
	 *
	 * @var MSH_Metadata_Core
	 */
	private $metadata_core;

	/**
	 * Decision layer.
	 *
	 * @var MSH_Decision_Layer
	 */
	private $decision_layer;

	/**
	 * Event bus.
	 *
	 * @var MSH_Event_Bus
	 */
	private $event_bus;

	/**
	 * Sync table name.
	 *
	 * @var string
	 */
	private $sync_table;

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Supported fields.
	 *
	 * @var string[]
	 */
	private $fields = array( 'title', 'alt', 'caption', 'description' );

	/**
	 * Get singleton instance.
	 *
	 * @since 2.1.0
	 *
	 * @return MSH_Cloud_Sync_Driver
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
		$this->metadata_core  = MSH_Metadata_Core::get_instance();
		$this->decision_layer = MSH_Decision_Layer::get_instance();
		$this->event_bus      = MSH_Event_Bus::get_instance();
		$this->sync_table     = $this->metadata_core->get_sync_table_name();
		$this->settings       = $this->get_settings();
	}

	/**
	 * {@inheritdoc}
	 */
	public function push( $attachment_id, $locale ) {
		$attachment_id = absint( $attachment_id );
		$locale        = $this->sanitize_locale( $locale );

		if ( ! $attachment_id ) {
			return new WP_Error( 'msh_sync_invalid_attachment', __( 'Invalid attachment ID supplied.', 'msh-image-optimizer' ) );
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$payload = $this->build_payload( $attachment_id, $locale );
		if ( empty( $payload['fields'] ) ) {
			return new WP_Error( 'msh_sync_no_metadata', __( 'No metadata available to synchronise.', 'msh-image-optimizer' ) );
		}

		$key  = $this->build_object_key( $attachment_id, $locale );
		$body = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		try {
			$result = $client->putObject(
				array(
					'Bucket'      => $this->settings['bucket'],
					'Key'         => $key,
					'Body'        => $body,
					'ContentType' => 'application/json',
				)
			);
		} catch ( Exception $exception ) {
			$this->emit_failure( 'metadata.sync_failed', $attachment_id, $locale, $exception->getMessage(), 'push' );

			return new WP_Error( 'msh_sync_push_failed', $exception->getMessage() );
		}

		$etag = isset( $result['ETag'] ) ? trim( $result['ETag'], '"' ) : md5( $body );

		$this->store_sync_state( $attachment_id, $locale, $etag, true, false, array_keys( $payload['fields'] ) );

		$this->event_bus->emit(
			'metadata.sync_pushed',
			'attachment',
			$attachment_id,
			array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'remote_key'    => $key,
				'etag'          => $etag,
			)
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function pull( $attachment_id, $locale ) {
		$attachment_id = absint( $attachment_id );
		$locale        = $this->sanitize_locale( $locale );

		if ( ! $attachment_id ) {
			return new WP_Error( 'msh_sync_invalid_attachment', __( 'Invalid attachment ID supplied.', 'msh-image-optimizer' ) );
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$key = $this->build_object_key( $attachment_id, $locale );

		try {
			$result = $client->getObject(
				array(
					'Bucket' => $this->settings['bucket'],
					'Key'    => $key,
				)
			);
		} catch ( Exception $exception ) {
			$this->emit_failure( 'metadata.sync_failed', $attachment_id, $locale, $exception->getMessage(), 'pull' );

			return new WP_Error( 'msh_sync_pull_failed', $exception->getMessage() );
		}

		$body = (string) $result['Body'];
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['fields'] ) ) {
			return new WP_Error( 'msh_sync_invalid_payload', __( 'The remote metadata payload is invalid.', 'msh-image-optimizer' ) );
		}

		$updated_fields = array();

		foreach ( $data['fields'] as $field => $field_data ) {
			if ( ! in_array( $field, $this->fields, true ) || ! is_array( $field_data ) ) {
				continue;
			}

			$ai_value     = $field_data['ai'] ?? null;
			$manual_value = $field_data['manual'] ?? null;
			$fingerprint  = isset( $field_data['fingerprint'] ) ? sanitize_text_field( $field_data['fingerprint'] ) : null;
			$ai_model     = isset( $field_data['ai_model'] ) ? sanitize_text_field( $field_data['ai_model'] ) : null;

			$chosen_source = isset( $field_data['chosen'] ) && in_array( $field_data['chosen'], array( 'ai', 'manual' ), true )
				? $field_data['chosen']
				: $this->decision_layer->choose_source( $attachment_id, $locale, $field, $ai_value, $manual_value );

			$chosen_source = apply_filters( 'msh_cloud_sync_chosen_source', $chosen_source, $attachment_id, $locale, $field, $field_data );

			$result_update = $this->metadata_core->update_cache(
				$attachment_id,
				$locale,
				$field,
				$ai_value,
				$manual_value,
				$chosen_source,
				$fingerprint,
				'fresh',
				$ai_model
			);

			if ( is_wp_error( $result_update ) ) {
				$this->emit_failure( 'metadata.sync_failed', $attachment_id, $locale, $result_update->get_error_message(), 'pull' );
				continue;
			}

			$updated_fields[] = $field;
		}

		$etag = isset( $result['ETag'] ) ? trim( $result['ETag'], '"' ) : md5( $body );

		$this->store_sync_state( $attachment_id, $locale, $etag, false, true, $updated_fields );

		$this->event_bus->emit(
			'metadata.sync_pulled',
			'attachment',
			$attachment_id,
			array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'remote_key'    => $key,
				'etag'          => $etag,
			)
		);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_etag( $attachment_id, $locale ) {
		$attachment_id = absint( $attachment_id );
		$locale        = $this->sanitize_locale( $locale );

		if ( ! $attachment_id ) {
			return new WP_Error( 'msh_sync_invalid_attachment', __( 'Invalid attachment ID supplied.', 'msh-image-optimizer' ) );
		}

		$client = $this->get_client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$key = $this->build_object_key( $attachment_id, $locale );

		try {
			$head = $client->headObject(
				array(
					'Bucket' => $this->settings['bucket'],
					'Key'    => $key,
				)
			);
		} catch ( Exception $exception ) {
			return new WP_Error( 'msh_sync_head_failed', $exception->getMessage() );
		}

		return isset( $head['ETag'] ) ? trim( $head['ETag'], '"' ) : '';
	}

	/**
	 * Gather settings from options/filters.
	 *
	 * @return array
	 */
	private function get_settings() {
		$defaults = array(
			'bucket'      => get_option( 'msh_cloud_sync_bucket', '' ),
			'region'      => get_option( 'msh_cloud_sync_region', 'us-east-1' ),
			'credentials' => array(
				'key'    => get_option( 'msh_cloud_sync_access_key', '' ),
				'secret' => get_option( 'msh_cloud_sync_secret_key', '' ),
			),
			'endpoint'    => get_option( 'msh_cloud_sync_endpoint', '' ),
		);

		return apply_filters( 'msh_cloud_sync_s3_settings', $defaults );
	}

	/**
	 * Instantiate S3 client.
	 *
	 * @return object|WP_Error
	 */
	private function get_client() {
		if ( empty( $this->settings['bucket'] ) ) {
			return new WP_Error( 'msh_sync_missing_bucket', __( 'Cloud sync bucket is not configured.', 'msh-image-optimizer' ) );
		}

		if ( ! class_exists( '\\Aws\\S3\\S3Client' ) ) {
			return new WP_Error( 'msh_sync_missing_sdk', __( 'AWS SDK for PHP is required for cloud sync.', 'msh-image-optimizer' ) );
		}

		$args = array(
			'version' => 'latest',
			'region'  => $this->settings['region'],
		);

		if ( ! empty( $this->settings['endpoint'] ) ) {
			$args['endpoint'] = $this->settings['endpoint'];
		}

		if ( ! empty( $this->settings['credentials']['key'] ) && ! empty( $this->settings['credentials']['secret'] ) ) {
			$args['credentials'] = array(
				'key'    => $this->settings['credentials']['key'],
				'secret' => $this->settings['credentials']['secret'],
			);
		}

		$args = apply_filters( 'msh_cloud_sync_s3_client_args', $args, $this->settings );

		try {
			return new \Aws\S3\S3Client( $args );
		} catch ( Exception $exception ) {
			return new WP_Error( 'msh_sync_client_build_failed', $exception->getMessage() );
		}
	}

	/**
	 * Build sync payload.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return array
	 */
	private function build_payload( $attachment_id, $locale ) {
		$data = array(
			'attachment_id' => $attachment_id,
			'locale'        => $locale,
			'generated_at'  => current_time( 'mysql' ),
			'fields'        => array(),
		);

		foreach ( $this->fields as $field ) {
			$row = $this->metadata_core->get_cache( $attachment_id, $locale, $field );
			if ( empty( $row ) ) {
				continue;
			}

			$data['fields'][ $field ] = array(
				'ai'          => $row['ai_value'],
				'manual'      => $row['manual_value'],
				'chosen'      => $row['chosen_source'],
				'fingerprint' => $row['input_fingerprint'],
				'ai_model'    => $row['ai_model'],
			);
		}

		return apply_filters( 'msh_cloud_sync_payload', $data, $attachment_id, $locale );
	}

	/**
	 * Build S3 object key.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return string
	 */
	private function build_object_key( $attachment_id, $locale ) {
		$key = sprintf( 'metadata/%d/%s.json', $attachment_id, $locale );

		return apply_filters( 'msh_cloud_sync_object_key', $key, $attachment_id, $locale );
	}

	/**
	 * Persist sync state.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $etag          Remote ETag.
	 * @param bool   $did_push      Whether push occurred.
	 * @param bool   $did_pull      Whether pull occurred.
	 * @param array  $fields        Fields affected.
	 * @return void
	 */
	private function store_sync_state( $attachment_id, $locale, $etag, $did_push, $did_pull, $fields = array() ) {
		global $wpdb;

		$fields = empty( $fields ) ? $this->fields : array_intersect( $this->fields, (array) $fields );
		$now    = current_time( 'mysql' );

		foreach ( $fields as $field ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$this->sync_table} WHERE attachment_id = %d AND locale = %s AND field = %s",
					$attachment_id,
					$locale,
					$field
				),
				ARRAY_A
			);

			$data = array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'field'         => $field,
				'remote_etag'   => $etag,
				'last_push'     => $did_push ? $now : ( $existing['last_push'] ?? null ),
				'last_pull'     => $did_pull ? $now : ( $existing['last_pull'] ?? null ),
			);

			$result = $wpdb->replace(
				$this->sync_table,
				$data,
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $result ) {
				$this->emit_failure( 'metadata.sync_failed', $attachment_id, $locale, $wpdb->last_error, $did_push ? 'push' : 'pull' );
			}
		}
	}

	/**
	 * Emit failure event for visibility.
	 *
	 * @param string $event         Event name.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $message       Error message.
	 * @param string $operation     Operation (push|pull).
	 * @return void
	 */
	private function emit_failure( $event, $attachment_id, $locale, $message, $operation ) {
		$this->event_bus->emit(
			$event,
			'attachment',
			$attachment_id,
			array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'operation'     => $operation,
				'message'       => $message,
			)
		);
	}

	/**
	 * Sanitize locale string.
	 *
	 * @param string $locale Locale input.
	 * @return string
	 */
	private function sanitize_locale( $locale ) {
		$locale = $locale ? sanitize_text_field( $locale ) : get_locale();

		return substr( $locale, 0, 12 );
	}
}
