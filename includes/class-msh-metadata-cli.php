<?php
/**
 * WP-CLI Commands for Phase 4R+ Metadata Orchestration
 *
 * Provides CLI tools to inspect fingerprints, events, and metadata cache.
 *
 * Commands:
 * - wp msh metadata fingerprint <attachment_id> <locale> <field>
 * - wp msh metadata events [--unprocessed] [--limit=N]
 * - wp msh metadata cache <attachment_id> [--locale=<locale>]
 * - wp msh metadata stats
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class MSH_Metadata_CLI {

	/**
	 * Calculate fingerprint for attachment metadata
	 *
	 * ## OPTIONS
	 *
	 * <attachment_id>
	 * : Attachment ID
	 *
	 * <locale>
	 * : Locale code (e.g., 'en_US')
	 *
	 * <field>
	 * : Field name ('title', 'alt', 'caption', 'description')
	 *
	 * [--verbose]
	 * : Show detailed signal breakdown
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh metadata fingerprint 123 en_US alt
	 *     wp msh metadata fingerprint 456 es_ES title --verbose
	 *
	 * @when after_wp_load
	 */
	public function fingerprint( $args, $assoc_args ) {
		list( $attachment_id, $locale, $field ) = $args;

		// Validate attachment
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			WP_CLI::error( "Attachment $attachment_id is not an image." );
		}

		// Validate field
		$valid_fields = array( 'title', 'alt', 'caption', 'description' );
		if ( ! in_array( $field, $valid_fields, true ) ) {
			WP_CLI::error( "Invalid field '$field'. Must be one of: " . implode( ', ', $valid_fields ) );
		}

		$fingerprint_builder = MSH_Fingerprint_Builder::get_instance();
		$fingerprint = $fingerprint_builder->build_fingerprint( $attachment_id, $locale, $field );

		WP_CLI::success( "Fingerprint: $fingerprint" );

		// Show detailed breakdown if --verbose
		if ( isset( $assoc_args['verbose'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Signal Breakdown:' );

			// Use reflection to access private method
			$reflection = new ReflectionClass( $fingerprint_builder );
			$method = $reflection->getMethod( 'gather_signals' );
			$method->setAccessible( true );
			$signals = $method->invoke( $fingerprint_builder, $attachment_id, $locale, $field );

			foreach ( $signals as $signal_name => $signal_value ) {
				WP_CLI::line( "  - $signal_name: $signal_value" );
			}
		}
	}

	/**
	 * List events from event bus
	 *
	 * ## OPTIONS
	 *
	 * [--unprocessed]
	 * : Show only unprocessed events
	 *
	 * [--event=<event>]
	 * : Filter by event type
	 *
	 * [--limit=<limit>]
	 * : Number of events to show (default: 20)
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh metadata events
	 *     wp msh metadata events --unprocessed
	 *     wp msh metadata events --event=post.updated --limit=50
	 *     wp msh metadata events --format=json
	 *
	 * @when after_wp_load
	 */
	public function events( $args, $assoc_args ) {
		global $wpdb;

		$table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		// Build query
		$where = array( '1=1' );
		$prepare_args = array();

		if ( isset( $assoc_args['unprocessed'] ) ) {
			$where[] = 'processed_at IS NULL';
		}

		if ( isset( $assoc_args['event'] ) ) {
			$where[] = 'event = %s';
			$prepare_args[] = $assoc_args['event'];
		}

		$where_clause = implode( ' AND ', $where );
		$prepare_args[] = $limit;

		$query = "SELECT id, event, entity_type, entity_id, trigger_user_id, created_at, processed_at
		          FROM $table
		          WHERE $where_clause
		          ORDER BY created_at DESC
		          LIMIT %d";

		$events = $wpdb->get_results( $wpdb->prepare( $query, $prepare_args ) );

		if ( empty( $events ) ) {
			WP_CLI::warning( 'No events found.' );
			return;
		}

		// Format for display
		$formatted = array();
		foreach ( $events as $event ) {
			$formatted[] = array(
				'ID'          => $event->id,
				'Event'       => $event->event,
				'Entity'      => $event->entity_type . ':' . $event->entity_id,
				'User'        => $event->trigger_user_id,
				'Created'     => $event->created_at,
				'Processed'   => $event->processed_at ?? 'pending',
			);
		}

		WP_CLI\Utils\format_items( $format, $formatted, array( 'ID', 'Event', 'Entity', 'User', 'Created', 'Processed' ) );
	}

	/**
	 * Show metadata cache for attachment
	 *
	 * ## OPTIONS
	 *
	 * <attachment_id>
	 * : Attachment ID
	 *
	 * [--locale=<locale>]
	 * : Filter by locale (default: all locales)
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh metadata cache 123
	 *     wp msh metadata cache 456 --locale=es_ES
	 *     wp msh metadata cache 789 --format=json
	 *
	 * @when after_wp_load
	 */
	public function cache( $args, $assoc_args ) {
		global $wpdb;

		list( $attachment_id ) = $args;

		// Validate attachment
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			WP_CLI::error( "Attachment $attachment_id is not an image." );
		}

		$table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		// Build query
		if ( isset( $assoc_args['locale'] ) ) {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE attachment_id = %d AND locale = %s",
				$attachment_id,
				$assoc_args['locale']
			) );
		} else {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE attachment_id = %d",
				$attachment_id
			) );
		}

		if ( empty( $results ) ) {
			WP_CLI::warning( "No metadata cache found for attachment $attachment_id." );
			return;
		}

		// Format for display
		$formatted = array();
		foreach ( $results as $row ) {
			$formatted[] = array(
				'Locale'       => $row->locale,
				'Field'        => $row->field,
				'Source'       => $row->chosen_source,
				'Value'        => substr( $row->chosen_source === 'ai' ? $row->ai_value : $row->manual_value, 0, 60 ) . '...',
				'Stale'        => $row->stale_reason,
				'Updated'      => $row->updated_at,
			);
		}

		WP_CLI\Utils\format_items( $format, $formatted, array( 'Locale', 'Field', 'Source', 'Value', 'Stale', 'Updated' ) );
	}

	/**
	 * Get the active metadata value for a field.
	 *
	 * ## OPTIONS
	 *
	 * <attachment_id>
	 * : Attachment ID.
	 *
	 * <locale>
	 * : Locale code (e.g., 'en_US').
	 *
	 * <field>
	 * : Field name ('title', 'alt', 'caption', 'description').
	 *
	 * [--raw]
	 * : Output only the chosen value.
	 *
	 * [--format=<format>]
	 * : Output format (table, json). Default: table.
	 *
	 * @when after_wp_load
	 */
	public function get( $args, $assoc_args ) {
		list( $attachment_id, $locale, $field ) = $args;

		$attachment_id = absint( $attachment_id );

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			WP_CLI::error( sprintf( 'Attachment %d is not an image.', $attachment_id ) );
		}

		$field        = sanitize_key( $field );
		$valid_fields = array( 'title', 'alt', 'caption', 'description' );

		if ( ! in_array( $field, $valid_fields, true ) ) {
			WP_CLI::error( 'Invalid field. Use one of: ' . implode( ', ', $valid_fields ) );
		}

		$core = MSH_Metadata_Core::get_instance();
		$row  = $core->get_cache( $attachment_id, $locale, $field );

		if ( empty( $row ) ) {
			WP_CLI::warning( 'No metadata cache entry found for that combination.' );
			return;
		}

		$value = $core->get_value( $attachment_id, $locale, $field );

		if ( isset( $assoc_args['raw'] ) ) {
			WP_CLI::line( (string) $value );
			return;
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$display_value = (string) $value;
		if ( 'table' === $format ) {
			$display_value = wp_html_excerpt( $display_value, 80, '…' );
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( array(
				'value'       => $value,
				'locale'      => $row['locale'],
				'field'       => $row['field'],
				'source'      => $row['chosen_source'],
				'fingerprint' => $row['input_fingerprint'],
				'stale'       => $row['stale_reason'],
			), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return;
		}

		$data = array(
			array(
				'Locale'      => $row['locale'],
				'Field'       => $row['field'],
				'Source'      => $row['chosen_source'],
				'Value'       => $display_value,
				'Fingerprint' => $row['input_fingerprint'],
				'Stale'       => $row['stale_reason'],
			),
		);

		WP_CLI\Utils\format_items( $format, $data, array( 'Locale', 'Field', 'Source', 'Value', 'Fingerprint', 'Stale' ) );
	}

	/**
	 * Mark metadata as stale to trigger regeneration.
	 *
	 * ## OPTIONS
	 *
	 * <attachment_id>
	 * : Attachment ID.
	 *
	 * <locale>
	 * : Locale code.
	 *
	 * <field>
	 * : Field name.
	 *
	 * [--reason=<reason>]
	 * : Reason label (default: context_changed).
	 *
	 * @when after_wp_load
	 */
	public function mark_stale( $args, $assoc_args ) {
		list( $attachment_id, $locale, $field ) = $args;

		$attachment_id = absint( $attachment_id );

		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			WP_CLI::error( sprintf( 'Attachment %d is not an image.', $attachment_id ) );
		}

		$field        = sanitize_key( $field );
		$valid_fields = array( 'title', 'alt', 'caption', 'description' );

		if ( ! in_array( $field, $valid_fields, true ) ) {
			WP_CLI::error( 'Invalid field. Use one of: ' . implode( ', ', $valid_fields ) );
		}

		$core  = MSH_Metadata_Core::get_instance();
		$cache = $core->get_cache( $attachment_id, $locale, $field );

		$reason = isset( $assoc_args['reason'] ) ? sanitize_key( $assoc_args['reason'] ) : 'context_changed';

		MSH_Staleness_Engine::get_instance()->queue_regeneration(
			$attachment_id,
			$locale,
			$field,
			$reason,
			$cache['input_fingerprint'] ?? null,
			$cache
		);

		WP_CLI::success( sprintf( 'Marked %d:%s:%s as stale (%s).', $attachment_id, $locale, $field, $reason ) );
	}

	/**
	 * Show metadata orchestration statistics
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh metadata stats
	 *     wp msh metadata stats --format=json
	 *
	 * @when after_wp_load
	 */
	public function stats( $args, $assoc_args ) {
		global $wpdb;

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		// Event stats
		$event_bus = MSH_Event_Bus::get_instance();
		$event_stats = $event_bus->get_stats();

		// Cache stats
		$cache_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
		$total_cache = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $cache_table" );
		$stale_cache = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $cache_table WHERE stale_reason != 'fresh'" );
		$ai_chosen = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $cache_table WHERE chosen_source = 'ai'" );
		$manual_chosen = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $cache_table WHERE chosen_source = 'manual'" );

		// Version stats
		$version_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_VERSIONS );
		$total_versions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $version_table" );

		// Format stats
		$stats = array(
			array(
				'Metric'       => 'Total Events',
				'Value'        => $event_stats['total_events'],
			),
			array(
				'Metric'       => 'Unprocessed Events',
				'Value'        => $event_stats['unprocessed_events'],
			),
			array(
				'Metric'       => 'Processed Events',
				'Value'        => $event_stats['processed_events'],
			),
			array(
				'Metric'       => 'Total Metadata Cache',
				'Value'        => $total_cache,
			),
			array(
				'Metric'       => 'Stale Metadata',
				'Value'        => $stale_cache,
			),
			array(
				'Metric'       => 'AI-Generated Active',
				'Value'        => $ai_chosen,
			),
			array(
				'Metric'       => 'Manual Active',
				'Value'        => $manual_chosen,
			),
			array(
				'Metric'       => 'Total Versions',
				'Value'        => $total_versions,
			),
		);

		WP_CLI\Utils\format_items( $format, $stats, array( 'Metric', 'Value' ) );

		// Show event breakdown
		if ( $format === 'table' && ! empty( $event_stats['by_event'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line( 'Event Breakdown:' );
			foreach ( $event_stats['by_event'] as $event => $count ) {
				WP_CLI::line( "  - $event: $count" );
			}
		}
	}

	/**
	 * Test event emission
	 *
	 * ## OPTIONS
	 *
	 * <event>
	 * : Event name (e.g., 'test.event')
	 *
	 * <entity_type>
	 * : Entity type ('post', 'attachment', 'site')
	 *
	 * [<entity_id>]
	 * : Entity ID (optional for site events)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh metadata test_event test.event site
	 *     wp msh metadata test_event post.updated post 123
	 *
	 * @when after_wp_load
	 */
	public function test_event( $args, $assoc_args ) {
		list( $event, $entity_type ) = $args;
		$entity_id = isset( $args[2] ) ? (int) $args[2] : null;

		$event_bus = MSH_Event_Bus::get_instance();
		$event_id = $event_bus->emit(
			$event,
			$entity_type,
			$entity_id,
			array( 'test' => true, 'timestamp' => time() )
		);

		if ( $event_id ) {
			WP_CLI::success( "Event emitted with ID: $event_id" );
		} else {
			WP_CLI::error( 'Failed to emit event.' );
		}
	}
}
