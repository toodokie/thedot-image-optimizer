<?php
/**
 * Context Performance Optimizer
 *
 * Implements caching, query optimization, and performance enhancements
 * for the Context Fusion Layer.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performance Optimizer Class
 *
 * Handles caching and query optimization.
 */
class MSH_Context_Performance {

	/**
	 * Cache group name
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'msh_context';

	/**
	 * Cache version (increment to invalidate all caches)
	 *
	 * @var string
	 */
	const CACHE_VERSION = 'v2';

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_Performance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_Performance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Add cache invalidation hooks
		add_action( 'msh_ctx_context_updated', array( $this, 'invalidate_media_cache' ), 10, 2 );
		add_action( 'msh_ctx_context_deleted', array( $this, 'invalidate_media_cache' ), 10, 2 );
	}

	/**
	 * Get cached rollup data
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Locale code.
	 * @return array|false Rollup data or false if not cached.
	 */
	public function get_cached_rollup( $media_id, $locale ) {
		$cache_key = $this->get_rollup_cache_key( $media_id, $locale );
		return wp_cache_get( $cache_key, self::CACHE_GROUP );
	}

	/**
	 * Set cached rollup data
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Locale code.
	 * @param array  $rollup   Rollup data.
	 * @param int    $expire   Expiration time in seconds.
	 * @return bool True on success.
	 */
	public function set_cached_rollup( $media_id, $locale, $rollup, $expire = HOUR_IN_SECONDS ) {
		$cache_key = $this->get_rollup_cache_key( $media_id, $locale );
		return wp_cache_set( $cache_key, $rollup, self::CACHE_GROUP, $expire );
	}

	/**
	 * Get rollup cache key
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale   Locale code.
	 * @return string Cache key.
	 */
	private function get_rollup_cache_key( $media_id, $locale ) {
		return sprintf( 'rollup:%s:%d:%s', self::CACHE_VERSION, $media_id, $locale );
	}

	/**
	 * Invalidate media cache
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale   Locale code.
	 */
	public function invalidate_media_cache( $media_id, $locale = null ) {
		if ( null === $locale ) {
			// Invalidate all locales - we can't efficiently do this with object cache
			// so we just invalidate the current locale
			$locale = get_locale();
		}

		$cache_key = $this->get_rollup_cache_key( $media_id, $locale );
		wp_cache_delete( $cache_key, self::CACHE_GROUP );
	}

	/**
	 * Create optimized database indexes
	 *
	 * @return array Results of index creation.
	 */
	public function create_optimized_indexes() {
		global $wpdb;

		$table = $wpdb->prefix . 'msh_optimizer_context';
		$results = array();

		// Index for rollup queries (media_id + locale)
		$results['rollup_index'] = $wpdb->query(
			"CREATE INDEX IF NOT EXISTS idx_rollup
			ON {$table} (media_id, locale, intent, context_score)"
		);

		// Index for search queries (locale + intent + score)
		$results['search_index'] = $wpdb->query(
			"CREATE INDEX IF NOT EXISTS idx_search
			ON {$table} (locale, intent, context_score)"
		);

		// Index for post queries
		$results['post_index'] = $wpdb->query(
			"CREATE INDEX IF NOT EXISTS idx_post
			ON {$table} (post_id, locale)"
		);

		// Index for source_hash (for incremental updates)
		$results['hash_index'] = $wpdb->query(
			"CREATE INDEX IF NOT EXISTS idx_source_hash
			ON {$table} (source_hash(32))"
		);

		return $results;
	}

	/**
	 * Analyze query performance
	 *
	 * @param string $query SQL query to analyze.
	 * @return array Analysis results.
	 */
	public function analyze_query( $query ) {
		global $wpdb;

		// Run EXPLAIN on query
		$explain = $wpdb->get_results( "EXPLAIN {$query}", ARRAY_A );

		$analysis = array(
			'explain'         => $explain,
			'uses_index'      => false,
			'full_table_scan' => false,
			'rows_examined'   => 0,
		);

		if ( ! empty( $explain ) ) {
			foreach ( $explain as $row ) {
				// Check if using index
				if ( ! empty( $row['key'] ) && 'NULL' !== $row['key'] ) {
					$analysis['uses_index'] = true;
				}

				// Check for full table scan
				if ( 'ALL' === $row['type'] ) {
					$analysis['full_table_scan'] = true;
				}

				// Count rows examined
				if ( isset( $row['rows'] ) ) {
					$analysis['rows_examined'] += (int) $row['rows'];
				}
			}
		}

		return $analysis;
	}

	/**
	 * Get query statistics
	 *
	 * @return array Query statistics.
	 */
	public function get_query_stats() {
		global $wpdb;

		return array(
			'total_queries' => $wpdb->num_queries,
			'query_time'    => $wpdb->timer_stop(),
		);
	}

	/**
	 * Optimize database tables
	 *
	 * @return array Optimization results.
	 */
	public function optimize_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'msh_optimizer_context',
			$wpdb->prefix . 'msh_optimizer_metadata_i18n',
			$wpdb->prefix . 'msh_optimizer_context_snapshots',
		);

		$results = array();

		foreach ( $tables as $table ) {
			$result = $wpdb->query( "OPTIMIZE TABLE {$table}" );
			$results[ $table ] = false !== $result;
		}

		return $results;
	}

	/**
	 * Get table statistics
	 *
	 * @return array Table statistics.
	 */
	public function get_table_stats() {
		global $wpdb;

		$tables = array(
			'contexts'  => $wpdb->prefix . 'msh_optimizer_context',
			'i18n'      => $wpdb->prefix . 'msh_optimizer_metadata_i18n',
			'snapshots' => $wpdb->prefix . 'msh_optimizer_context_snapshots',
		);

		$stats = array();

		foreach ( $tables as $key => $table ) {
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as row_count,
						ROUND(DATA_LENGTH / 1024 / 1024, 2) as data_size_mb,
						ROUND(INDEX_LENGTH / 1024 / 1024, 2) as index_size_mb
					FROM information_schema.TABLES
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
					DB_NAME,
					$table
				),
				ARRAY_A
			);

			if ( $result ) {
				$stats[ $key ] = array(
					'rows'       => (int) $result['row_count'],
					'data_mb'    => (float) $result['data_size_mb'],
					'index_mb'   => (float) $result['index_size_mb'],
					'total_mb'   => round( $result['data_size_mb'] + $result['index_size_mb'], 2 ),
				);
			}
		}

		return $stats;
	}

	/**
	 * Warm up cache for frequently accessed data
	 *
	 * @param array $options Warmup options.
	 * @return array Results.
	 */
	public function warmup_cache( $options = array() ) {
		$defaults = array(
			'limit'  => 50,
			'locale' => get_locale(),
		);

		$options = wp_parse_args( $options, $defaults );

		global $wpdb;
		$table = $wpdb->prefix . 'msh_optimizer_context';

		// Get most frequently used images
		$media_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT media_id
				FROM {$table}
				WHERE locale = %s
				GROUP BY media_id
				ORDER BY COUNT(*) DESC
				LIMIT %d",
				$options['locale'],
				$options['limit']
			)
		);

		$cached = 0;
		$manager = MSH_Context_Manager::get_instance();

		foreach ( $media_ids as $media_id ) {
			// This will compute and cache the rollup
			$rollup = $manager->get_media_rollup( $media_id, $options['locale'] );
			if ( $rollup ) {
				$cached++;
			}
		}

		return array(
			'processed' => count( $media_ids ),
			'cached'    => $cached,
		);
	}

	/**
	 * Clear all context caches
	 *
	 * @return bool True on success.
	 */
	public function clear_all_caches() {
		return wp_cache_flush();
	}
}
