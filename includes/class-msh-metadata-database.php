<?php
/**
 * Phase 4R+ Database Schema - Intelligent Metadata Orchestration
 *
 * Creates and manages database tables for:
 * - Metadata Cache (central source of truth)
 * - Metadata Versions (full history with diffs)
 * - Event Bus (event-driven regeneration)
 * - Sync State (cloud sync tracking - Pro)
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Metadata_Database {

	/**
	 * Database version for migration tracking
	 */
	const DB_VERSION = '2.0.0';
	const DB_VERSION_OPTION = 'msh_metadata_db_version';

	/**
	 * Table names (without prefix)
	 */
	const TABLE_CACHE = 'optimizer_metadata_cache';
	const TABLE_VERSIONS = 'optimizer_metadata_versions';
	const TABLE_EVENTS = 'optimizer_events';
	const TABLE_SYNC = 'optimizer_sync_state';

	/**
	 * Initialize database tables on activation
	 */
	public static function init() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		// Only create tables if not already at current version
		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * Create all Phase 4R+ tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Require WordPress upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table 1: Metadata Cache - Central source of truth
		$table_cache = $wpdb->prefix . self::TABLE_CACHE;
		$sql_cache = "CREATE TABLE $table_cache (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT UNSIGNED NOT NULL,
			locale VARCHAR(12) NOT NULL DEFAULT 'en_US',
			field ENUM('title','alt','caption','description') NOT NULL,
			ai_value LONGTEXT,
			manual_value LONGTEXT,
			chosen_source ENUM('manual','ai') DEFAULT 'manual',
			input_fingerprint CHAR(40),
			stale_reason ENUM('fresh','context_changed','locale_updated','glossary_changed','template_changed','file_replaced','manual_override') DEFAULT 'fresh',
			ai_model VARCHAR(64),
			updated_at DATETIME,
			created_at DATETIME,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_metadata (attachment_id, locale, field),
			KEY idx_stale (attachment_id, stale_reason),
			KEY idx_locale (locale),
			KEY idx_fingerprint (input_fingerprint)
		) $charset_collate;";

		// Table 2: Metadata Versions - Full version history
		$table_versions = $wpdb->prefix . self::TABLE_VERSIONS;
		$sql_versions = "CREATE TABLE $table_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cache_id BIGINT UNSIGNED NOT NULL,
			version INT UNSIGNED NOT NULL,
			source ENUM('ai','manual','import') NOT NULL,
			value LONGTEXT,
			input_fingerprint CHAR(40),
			created_at DATETIME,
			notes VARCHAR(255),
			PRIMARY KEY  (id),
			KEY idx_cache_id (cache_id),
			KEY idx_created (created_at)
		) $charset_collate;";

		// Table 3: Event Bus - Event-driven regeneration
		$table_events = $wpdb->prefix . self::TABLE_EVENTS;
		$sql_events = "CREATE TABLE $table_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event VARCHAR(64) NOT NULL,
			entity_type ENUM('post','attachment','site') NOT NULL,
			entity_id BIGINT UNSIGNED,
			payload LONGTEXT,
			trigger_user_id BIGINT UNSIGNED,
			idempotency_key VARCHAR(64),
			processed_at DATETIME,
			created_at DATETIME,
			PRIMARY KEY  (id),
			KEY idx_event (event, created_at),
			KEY idx_processed (processed_at),
			KEY idx_entity (entity_type, entity_id),
			UNIQUE KEY unique_event (idempotency_key)
		) $charset_collate;";

		// Table 4: Sync State - Cloud sync tracking (Pro)
		$table_sync = $wpdb->prefix . self::TABLE_SYNC;
		$sql_sync = "CREATE TABLE $table_sync (
			attachment_id BIGINT UNSIGNED NOT NULL,
			locale VARCHAR(12) NOT NULL DEFAULT 'en_US',
			field ENUM('title','alt','caption','description') NOT NULL,
			remote_etag VARCHAR(64),
			last_push DATETIME,
			last_pull DATETIME,
			PRIMARY KEY  (attachment_id, locale, field),
			KEY idx_push (last_push),
			KEY idx_pull (last_pull)
		) $charset_collate;";

		// Execute table creation
		dbDelta( $sql_cache );
		dbDelta( $sql_versions );
		dbDelta( $sql_events );
		dbDelta( $sql_sync );
	}

	/**
	 * Get full table name with prefix
	 *
	 * @param string $table Table name constant (TABLE_CACHE, TABLE_VERSIONS, etc.)
	 * @return string Full table name with WordPress prefix
	 */
	public static function get_table_name( $table ) {
		global $wpdb;
		return $wpdb->prefix . $table;
	}

	/**
	 * Drop all Phase 4R+ tables (for uninstall - not deactivation)
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . self::TABLE_SYNC,
			$wpdb->prefix . self::TABLE_EVENTS,
			$wpdb->prefix . self::TABLE_VERSIONS,
			$wpdb->prefix . self::TABLE_CACHE,
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}

		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Get current database version
	 *
	 * @return string Database version
	 */
	public static function get_version() {
		return get_option( self::DB_VERSION_OPTION, '0.0.0' );
	}
}
