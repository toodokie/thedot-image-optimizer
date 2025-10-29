<?php
/**
 * Database Schema Manager for Phase 5+9
 *
 * Handles creation and migration of Phase 5+9 database tables:
 * - msh_jobs: Job queue with retry logic
 * - msh_dead_letters: Failed job recovery queue
 * - msh_telemetry: Anonymous usage tracking (opt-in)
 * - msh_metrics: Daily aggregated metrics
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Database_Schema
 *
 * Manages database schema for automation and enterprise features.
 */
class MSH_Database_Schema {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Database_Schema|null
	 */
	private static $instance = null;

	/**
	 * Database version option name.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'msh_automation_db_version';

	/**
	 * Current schema version.
	 *
	 * @var string
	 */
	const CURRENT_VERSION = '2.1.0';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Database_Schema
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
		// Hook into plugin activation
		register_activation_hook( MSH_IO_PLUGIN_FILE, array( $this, 'install' ) );

		// Ensure upgrades run when the plugin loads (handles existing installs)
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Install or upgrade database schema.
	 *
	 * Called on plugin activation or when version mismatch detected.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function install() {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		// Check if upgrade needed
		if ( version_compare( $installed_version, self::CURRENT_VERSION, '>=' ) ) {
			// Already up to date
			return true;
		}

		// Run migration
		$result = $this->create_tables();

		if ( $result ) {
			update_option( self::DB_VERSION_OPTION, self::CURRENT_VERSION );
			do_action( 'msh_database_schema_installed', self::CURRENT_VERSION, $installed_version );

			// Log success
			error_log( sprintf(
				'MSH Database Schema: Upgraded from %s to %s',
				$installed_version,
				self::CURRENT_VERSION
			) );

			return true;
		}

		// Log failure
		error_log( 'MSH Database Schema: Installation failed' );

		return false;
	}

	/**
	 * Create all Phase 5+9 tables.
	 *
	 * Uses dbDelta for safe schema updates.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$created         = array();
		$errors          = array();

		// Table 1: msh_jobs (Job Queue)
		$sql_jobs = "CREATE TABLE {$wpdb->prefix}msh_jobs (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			job_type VARCHAR(50) NOT NULL,
			entity_type VARCHAR(50) NOT NULL,
			entity_id BIGINT UNSIGNED NOT NULL,
			payload LONGTEXT,
			priority ENUM('high', 'medium', 'normal') DEFAULT 'normal',
			status ENUM('pending', 'processing', 'complete', 'failed') DEFAULT 'pending',
			attempts TINYINT UNSIGNED DEFAULT 0,
			max_attempts TINYINT UNSIGNED DEFAULT 3,
			next_retry_at DATETIME DEFAULT NULL,
			started_at DATETIME DEFAULT NULL,
			completed_at DATETIME DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_status_priority (status, priority, created_at),
			INDEX idx_entity (entity_type, entity_id),
			INDEX idx_retry (next_retry_at)
		) $charset_collate;";

		$result = dbDelta( $sql_jobs );
		if ( ! empty( $wpdb->last_error ) ) {
			$errors['msh_jobs'] = $wpdb->last_error;
		} else {
			$created['msh_jobs'] = true;
		}

		// Table 2: msh_dead_letters (Failed Job Recovery)
		$sql_dead_letters = "CREATE TABLE {$wpdb->prefix}msh_dead_letters (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			job_id BIGINT UNSIGNED NOT NULL,
			job_type VARCHAR(50) NOT NULL,
			attachment_id BIGINT UNSIGNED DEFAULT NULL,
			locale VARCHAR(20) DEFAULT NULL,
			field VARCHAR(50) DEFAULT NULL,
			reason TEXT NOT NULL,
			payload LONGTEXT,
			failed_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			INDEX idx_job_id (job_id),
			INDEX idx_attachment (attachment_id, locale, field),
			INDEX idx_failed_at (failed_at)
		) $charset_collate;";

		$result = dbDelta( $sql_dead_letters );
		if ( ! empty( $wpdb->last_error ) ) {
			$errors['msh_dead_letters'] = $wpdb->last_error;
		} else {
			$created['msh_dead_letters'] = true;
		}

		// Table 3: msh_telemetry (Anonymous Usage Tracking)
		$sql_telemetry = "CREATE TABLE {$wpdb->prefix}msh_telemetry (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			event VARCHAR(100) NOT NULL,
			data LONGTEXT,
			site_hash CHAR(64) NOT NULL COMMENT 'Anonymized site identifier',
			created_at DATETIME NOT NULL,
			INDEX idx_event (event, created_at),
			INDEX idx_site (site_hash)
		) $charset_collate;";

		$result = dbDelta( $sql_telemetry );
		if ( ! empty( $wpdb->last_error ) ) {
			$errors['msh_telemetry'] = $wpdb->last_error;
		} else {
			$created['msh_telemetry'] = true;
		}

		// Table 4: msh_metrics (Daily Aggregated Metrics)
		$sql_metrics = "CREATE TABLE {$wpdb->prefix}msh_metrics (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			metric_date DATE NOT NULL,
			metric_name VARCHAR(100) NOT NULL,
			metric_value BIGINT NOT NULL,
			created_at DATETIME NOT NULL,
			UNIQUE KEY unique_metric (metric_date, metric_name),
			INDEX idx_date (metric_date)
		) $charset_collate;";

		$result = dbDelta( $sql_metrics );
		if ( ! empty( $wpdb->last_error ) ) {
			$errors['msh_metrics'] = $wpdb->last_error;
		} else {
			$created['msh_metrics'] = true;
		}

		// Table 5: msh_metadata_cache (Field-level metadata cache)
		$sql_metadata_cache = "CREATE TABLE {$wpdb->prefix}msh_metadata_cache (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			attachment_id BIGINT UNSIGNED NOT NULL,
			media_id BIGINT UNSIGNED NOT NULL,
			locale VARCHAR(20) NOT NULL DEFAULT 'en_US',
			field VARCHAR(50) NOT NULL,
			ai_value LONGTEXT NULL,
			manual_value LONGTEXT NULL,
			chosen_source VARCHAR(20) NOT NULL DEFAULT 'manual',
			input_fingerprint CHAR(40) DEFAULT NULL,
			ai_model VARCHAR(64) DEFAULT NULL,
			stale_reason VARCHAR(255) DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			UNIQUE KEY unique_field (attachment_id, locale, field),
			INDEX idx_locale_field (locale, field),
			INDEX idx_source (chosen_source),
			INDEX idx_stale (stale_reason),
			INDEX idx_media (media_id),
			INDEX idx_fingerprint (input_fingerprint)
		) $charset_collate;";

		$result = dbDelta( $sql_metadata_cache );
		if ( ! empty( $wpdb->last_error ) ) {
			$errors['msh_metadata_cache'] = $wpdb->last_error;
		} else {
			$created['msh_metadata_cache'] = true;
		}

		// Log results
		if ( ! empty( $created ) ) {
			error_log( 'MSH Database Schema: Created tables - ' . implode( ', ', array_keys( $created ) ) );
		}

		if ( ! empty( $errors ) ) {
			error_log( 'MSH Database Schema: Errors - ' . wp_json_encode( $errors ) );
			return false;
		}

		return true;
	}

	/**
	 * Check if all tables exist.
	 *
	 * Useful for health checks and diagnostics.
	 *
	 * @return array Associative array of table_name => exists (bool).
	 */
	public function verify_tables() {
		global $wpdb;

		$tables = array(
			'msh_jobs',
			'msh_dead_letters',
			'msh_telemetry',
			'msh_metrics',
			'msh_metadata_cache',
		);

		$results = array();

		foreach ( $tables as $table ) {
			$full_table_name = $wpdb->prefix . $table;
			$exists          = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) ) === $full_table_name;
			$results[ $table ] = $exists;
		}

		return $results;
	}

	/**
	 * Get table schema information.
	 *
	 * Returns CREATE TABLE statements for documentation.
	 *
	 * @return array Associative array of table_name => schema.
	 */
	public function get_schema_info() {
		global $wpdb;

		$tables = array(
			'msh_jobs',
			'msh_dead_letters',
			'msh_telemetry',
			'msh_metrics',
			'msh_metadata_cache',
		);

		$schemas = array();

		foreach ( $tables as $table ) {
			$full_table_name = $wpdb->prefix . $table;
			$result          = $wpdb->get_row( $wpdb->prepare( 'SHOW CREATE TABLE %i', $full_table_name ), ARRAY_A );

			if ( ! empty( $result ) && isset( $result['Create Table'] ) ) {
				$schemas[ $table ] = $result['Create Table'];
			}
		}

		return $schemas;
	}

	/**
	 * Drop all Phase 5+9 tables.
	 *
	 * ⚠️ DANGEROUS: Only used during development or complete uninstall.
	 *
	 * @param bool $confirm Must pass true to confirm deletion.
	 * @return bool True on success, false on failure.
	 */
	public function drop_tables( $confirm = false ) {
		if ( ! $confirm ) {
			return false;
		}

		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'msh_jobs',
			$wpdb->prefix . 'msh_dead_letters',
			$wpdb->prefix . 'msh_telemetry',
			$wpdb->prefix . 'msh_metrics',
			$wpdb->prefix . 'msh_metadata_cache',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::DB_VERSION_OPTION );

		error_log( 'MSH Database Schema: All tables dropped' );

		return true;
	}

	/**
	 * Get row counts for all tables.
	 *
	 * Useful for admin dashboard stats.
	 *
	 * @return array Associative array of table_name => row_count.
	 */
	public function get_row_counts() {
		global $wpdb;

		$tables = array(
			'msh_jobs',
			'msh_dead_letters',
			'msh_telemetry',
			'msh_metrics',
			'msh_metadata_cache',
		);

		$counts = array();

		foreach ( $tables as $table ) {
			$full_table_name = $wpdb->prefix . $table;
			$count           = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$counts[ $table ] = (int) $count;
		}

		return $counts;
	}

	/**
	 * Get total database size for Phase 5+9 tables.
	 *
	 * @return array Array with 'size_bytes' and 'size_human' keys.
	 */
	public function get_database_size() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'msh_jobs',
			$wpdb->prefix . 'msh_dead_letters',
			$wpdb->prefix . 'msh_telemetry',
			$wpdb->prefix . 'msh_metrics',
			$wpdb->prefix . 'msh_metadata_cache',
		);

		$total_size = 0;

		foreach ( $tables as $table ) {
			$result = $wpdb->get_row( $wpdb->prepare(
				"SELECT (data_length + index_length) as size
				FROM information_schema.TABLES
				WHERE table_schema = %s
				AND table_name = %s",
				DB_NAME,
				$table
			) );

			if ( ! empty( $result ) && isset( $result->size ) ) {
				$total_size += (int) $result->size;
			}
		}

		return array(
			'size_bytes' => $total_size,
			'size_human' => size_format( $total_size, 2 ),
		);
	}

	/**
	 * Run database install when version mismatch detected.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed_version, self::CURRENT_VERSION, '<' ) ) {
			$this->install();
		}
	}
}
