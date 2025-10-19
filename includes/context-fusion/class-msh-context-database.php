<?php
/**
 * Context Fusion Layer - Database Management
 *
 * Handles database table creation and migration for the Context Fusion Layer.
 * Implements the corrected schema from Phase 2 design fixes.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Database Manager
 *
 * Manages the optimizer_context table lifecycle:
 * - Table creation with corrected unique key (includes usage_type and block_path)
 * - Version-aware migrations
 * - Index management
 */
class MSH_Context_Database {

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
	const DB_VERSION_OPTION = 'msh_context_db_version';

	/**
	 * Table name (without prefix)
	 *
	 * @var string
	 */
	const TABLE_NAME = 'msh_optimizer_context';

	/**
	 * Initialize database (create or upgrade)
	 *
	 * Called on plugin activation or version upgrade.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function init() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		// Create or upgrade table
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
	 * Create the optimizer_context table
	 *
	 * Uses dbDelta for safe CREATE/ALTER operations.
	 * Implements corrected schema from Phase 2 design fixes:
	 * - Unique key includes usage_type and block_path to prevent data loss
	 * - Deterministic source_hash (SHA-256)
	 * - Proper indexes for common queries
	 *
	 * @return bool True on success, false on failure
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		// Schema from PHASE_2_DESIGN_FIXES.md (final corrected version)
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			media_id BIGINT UNSIGNED NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			locale VARCHAR(20) NOT NULL,
			usage_type VARCHAR(64) NOT NULL DEFAULT 'unknown' COMMENT 'featured|inline|gallery|acf_field|block',
			block_path VARCHAR(255) NULL COMMENT 'Block path for Gutenberg blocks',

			subject VARCHAR(255) NULL,
			intent ENUM('on_topic','off_topic','unknown') NOT NULL DEFAULT 'unknown',
			intent_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
			entities LONGTEXT NULL COMMENT 'JSON: {brands:[],places:[],people:[]}',
			keywords LONGTEXT NULL COMMENT 'JSON: [keyword1,keyword2,...]',
			rules_fired LONGTEXT NULL COMMENT 'JSON: [rule1,rule2,...] for observability',

			source_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of post content + metadata (deterministic)',

			context_score SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 relevance score',

			usage_count INT UNSIGNED NOT NULL DEFAULT 1,
			first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

			PRIMARY KEY (id),
			UNIQUE KEY uniq_ctx (media_id, post_id, locale, usage_type, block_path(191)),
			KEY idx_media (media_id),
			KEY idx_post (post_id),
			KEY idx_intent (intent),
			KEY idx_locale (locale),
			KEY idx_score (context_score),
			KEY idx_hash (source_hash),
			KEY idx_last_seen (last_seen),
			KEY idx_usage_type (usage_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table was created
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $table_exists === $table_name;
	}

	/**
	 * Drop the optimizer_context table
	 *
	 * Used during plugin uninstallation (if user chooses to remove data).
	 * NOT called during deactivation or upgrades.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		if ( false !== $result ) {
			delete_option( self::DB_VERSION_OPTION );
			return true;
		}

		return false;
	}

	/**
	 * Get the full table name with prefix
	 *
	 * @return string Full table name
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Check if the context table exists
	 *
	 * @return bool True if table exists, false otherwise
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		return $table_exists === $table_name;
	}

	/**
	 * Get database statistics
	 *
	 * Returns information about the context table for debugging/admin UI.
	 *
	 * @return array Statistics array with row counts, index info, etc.
	 */
	public static function get_stats() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'table_exists' => false,
				'error'        => 'Table does not exist',
			);
		}

		$table_name = self::get_table_name();

		// Get row count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_rows = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		// Get unique media count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unique_media = $wpdb->get_var( "SELECT COUNT(DISTINCT media_id) FROM {$table_name}" );

		// Get unique posts count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unique_posts = $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$table_name}" );

		// Get locale breakdown
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$locale_counts = $wpdb->get_results(
			"SELECT locale, COUNT(*) as count FROM {$table_name} GROUP BY locale ORDER BY count DESC",
			ARRAY_A
		);

		// Get intent breakdown
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$intent_counts = $wpdb->get_results(
			"SELECT intent, COUNT(*) as count FROM {$table_name} GROUP BY intent ORDER BY count DESC",
			ARRAY_A
		);

		return array(
			'table_exists'  => true,
			'db_version'    => get_option( self::DB_VERSION_OPTION, 'unknown' ),
			'total_rows'    => (int) $total_rows,
			'unique_media'  => (int) $unique_media,
			'unique_posts'  => (int) $unique_posts,
			'locale_counts' => $locale_counts,
			'intent_counts' => $intent_counts,
		);
	}

	/**
	 * Truncate the context table
	 *
	 * Removes all rows but keeps the table structure.
	 * Used for full reindexing operations.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function truncate() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query( "TRUNCATE TABLE {$table_name}" );

		return false !== $result;
	}

	/**
	 * Delete context entries for a specific media item
	 *
	 * Called when a media item is deleted.
	 *
	 * @param int         $media_id Media ID to delete context for.
	 * @param string|null $locale   Optional locale filter (null = all locales).
	 * @return int Number of rows deleted
	 */
	public static function delete_media_context( $media_id, $locale = null ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = self::get_table_name();

		if ( $locale ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE media_id = %d AND locale = %s",
					$media_id,
					$locale
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE media_id = %d",
					$media_id
				)
			);
		}

		// Clear cache for this media
		self::clear_media_cache( $media_id, $locale );

		return (int) $deleted;
	}

	/**
	 * Delete context entries for a specific post
	 *
	 * Called when a post is deleted or unpublished.
	 *
	 * @param int         $post_id Post ID to delete context for.
	 * @param string|null $locale  Optional locale filter (null = all locales).
	 * @return int Number of rows deleted
	 */
	public static function delete_post_context( $post_id, $locale = null ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = self::get_table_name();

		// Get affected media IDs before deleting (for cache clearing)
		if ( $locale ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$media_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT media_id FROM {$table_name} WHERE post_id = %d AND locale = %s",
					$post_id,
					$locale
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE post_id = %d AND locale = %s",
					$post_id,
					$locale
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$media_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT media_id FROM {$table_name} WHERE post_id = %d",
					$post_id
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE post_id = %d",
					$post_id
				)
			);
		}

		// Clear cache for all affected media
		foreach ( $media_ids as $media_id ) {
			self::clear_media_cache( $media_id, $locale );
		}

		return (int) $deleted;
	}

	/**
	 * Clear context cache for a media item
	 *
	 * Implements fix from PHASE_2_DESIGN_FIXES.md:
	 * No wildcards in WordPress cache - must loop through known locales.
	 *
	 * @param int         $media_id Media ID.
	 * @param string|null $locale   Specific locale or null for all.
	 */
	private static function clear_media_cache( $media_id, $locale = null ) {
		if ( $locale ) {
			// Clear specific locale
			wp_cache_delete( "msh_ctx_rollup:{$media_id}:{$locale}", 'msh' );
		} else {
			// Get all locales for this media
			global $wpdb;
			$table_name = self::get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$locales = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT locale FROM {$table_name} WHERE media_id = %d",
					$media_id
				)
			);

			// Clear cache for each locale
			foreach ( $locales as $loc ) {
				wp_cache_delete( "msh_ctx_rollup:{$media_id}:{$loc}", 'msh' );
			}

			// Also clear the locale list cache
			wp_cache_delete( "msh_ctx_locales:{$media_id}", 'msh' );
		}
	}
}

