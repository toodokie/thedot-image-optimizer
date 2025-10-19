<?php
/**
 * I18n Metadata Database Management
 *
 * Handles database table creation for multilingual image metadata storage.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * I18n Database Manager
 *
 * Manages the optimizer_metadata_i18n table lifecycle.
 */
class MSH_I18n_Database {

	/**
	 * Database version for migration tracking
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0.0';

	/**
	 * Database version option name
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'msh_i18n_db_version';

	/**
	 * Table name (without prefix)
	 *
	 * @var string
	 */
	const TABLE_NAME = 'msh_optimizer_metadata_i18n';

	/**
	 * Initialize database (create or upgrade)
	 *
	 * @return bool True on success, false on failure
	 */
	public static function init() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			$result = self::create_table();

			if ( $result ) {
				update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
				return true;
			}

			return false;
		}

		return true;
	}

	/**
	 * Create the optimizer_metadata_i18n table
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			media_id BIGINT UNSIGNED NOT NULL,
			locale VARCHAR(20) NOT NULL,
			alt_text TEXT NULL,
			title VARCHAR(255) NULL,
			description TEXT NULL,
			caption TEXT NULL,
			generated_at DATETIME NULL,
			approved TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_media_locale (media_id, locale),
			KEY idx_media (media_id),
			KEY idx_locale (locale),
			KEY idx_approved (approved)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table creation
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->esc_like( $table_name )
			)
		);

		return $table_name === $table_exists;
	}

	/**
	 * Drop the table (for uninstall)
	 *
	 * @return bool True on success
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		delete_option( self::DB_VERSION_OPTION );

		return true;
	}

	/**
	 * Get table name with prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}
}
