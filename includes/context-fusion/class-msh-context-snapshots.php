<?php
/**
 * Context Snapshots - Trend Tracking
 *
 * Manages daily snapshots of context analytics for trend analysis.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Snapshots Class
 *
 * Creates and manages daily snapshots of context statistics.
 */
class MSH_Context_Snapshots {

	/**
	 * Database version
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0.0';

	/**
	 * Database version option
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'msh_snapshots_db_version';

	/**
	 * Table name (without prefix)
	 *
	 * @var string
	 */
	const TABLE_NAME = 'msh_optimizer_context_snapshots';

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_Snapshots
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_Snapshots
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
		// Initialize database
		add_action( 'init', array( $this, 'maybe_init_database' ) );

		// Schedule daily snapshot
		add_action( 'msh_ctx_daily_snapshot', array( $this, 'create_snapshot' ) );

		// Register cron schedule
		if ( ! wp_next_scheduled( 'msh_ctx_daily_snapshot' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 1:00am' ), 'daily', 'msh_ctx_daily_snapshot' );
		}
	}

	/**
	 * Maybe initialize database
	 */
	public function maybe_init_database() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			$this->create_table();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * Create snapshots table
	 *
	 * @return bool True on success.
	 */
	private function create_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			snapshot_date DATE NOT NULL,
			locale VARCHAR(20) NOT NULL DEFAULT 'en_US',
			total_contexts INT UNSIGNED NOT NULL,
			total_images INT UNSIGNED NOT NULL,
			total_posts INT UNSIGNED NOT NULL,
			avg_context_score DECIMAL(5,2) NOT NULL,
			on_topic_count INT UNSIGNED NOT NULL,
			off_topic_count INT UNSIGNED NOT NULL,
			unknown_count INT UNSIGNED NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_date_locale (snapshot_date, locale),
			KEY idx_date (snapshot_date),
			KEY idx_locale (locale)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Create daily snapshot
	 *
	 * @param string $locale Locale to snapshot (default: site locale).
	 * @return bool True on success.
	 */
	public function create_snapshot( $locale = null ) {
		if ( null === $locale ) {
			$locale = get_locale();
		}

		global $wpdb;

		$context_table = $wpdb->prefix . 'msh_optimizer_context';
		$snapshot_table = $wpdb->prefix . self::TABLE_NAME;

		// Get stats for today
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_contexts,
					COUNT(DISTINCT media_id) as total_images,
					COUNT(DISTINCT post_id) as total_posts,
					AVG(context_score) as avg_score,
					SUM(CASE WHEN intent = 'on_topic' THEN 1 ELSE 0 END) as on_topic,
					SUM(CASE WHEN intent = 'off_topic' THEN 1 ELSE 0 END) as off_topic,
					SUM(CASE WHEN intent = 'unknown' THEN 1 ELSE 0 END) as unknown
				FROM {$context_table}
				WHERE locale = %s",
				$locale
			),
			ARRAY_A
		);

		if ( ! $stats ) {
			return false;
		}

		// Insert or update snapshot
		$snapshot_date = current_time( 'Y-m-d' );

		$data = array(
			'snapshot_date'     => $snapshot_date,
			'locale'            => $locale,
			'total_contexts'    => (int) $stats['total_contexts'],
			'total_images'      => (int) $stats['total_images'],
			'total_posts'       => (int) $stats['total_posts'],
			'avg_context_score' => round( $stats['avg_score'], 2 ),
			'on_topic_count'    => (int) $stats['on_topic'],
			'off_topic_count'   => (int) $stats['off_topic'],
			'unknown_count'     => (int) $stats['unknown'],
		);

		// Check if snapshot already exists for today
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$snapshot_table} WHERE snapshot_date = %s AND locale = %s",
				$snapshot_date,
				$locale
			)
		);

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$snapshot_table,
				$data,
				array(
					'snapshot_date' => $snapshot_date,
					'locale'        => $locale,
				)
			);
		} else {
			// Insert
			$result = $wpdb->insert( $snapshot_table, $data );
		}

		return false !== $result;
	}

	/**
	 * Get trend data
	 *
	 * @param array $options Query options.
	 * @return array Trend data.
	 */
	public function get_trends( $options = array() ) {
		global $wpdb;

		$defaults = array(
			'locale' => get_locale(),
			'days'   => 30,
		);

		$options = wp_parse_args( $options, $defaults );

		$table = $wpdb->prefix . self::TABLE_NAME;

		$trends = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				WHERE locale = %s
					AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				ORDER BY snapshot_date ASC",
				$options['locale'],
				$options['days']
			),
			ARRAY_A
		);

		return $trends;
	}

	/**
	 * Get latest snapshot
	 *
	 * @param string $locale Locale code.
	 * @return array|null Snapshot data.
	 */
	public function get_latest( $locale = null ) {
		global $wpdb;

		if ( null === $locale ) {
			$locale = get_locale();
		}

		$table = $wpdb->prefix . self::TABLE_NAME;

		$snapshot = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT *
				FROM {$table}
				WHERE locale = %s
				ORDER BY snapshot_date DESC
				LIMIT 1",
				$locale
			),
			ARRAY_A
		);

		return $snapshot;
	}

	/**
	 * Calculate trend direction
	 *
	 * @param string $metric   Metric to calculate ('avg_context_score', 'on_topic_count', etc.).
	 * @param int    $days     Number of days to analyze.
	 * @param string $locale   Locale code.
	 * @return array Trend data with direction and change.
	 */
	public function calculate_trend( $metric, $days = 7, $locale = null ) {
		if ( null === $locale ) {
			$locale = get_locale();
		}

		$trends = $this->get_trends(
			array(
				'locale' => $locale,
				'days'   => $days,
			)
		);

		if ( count( $trends ) < 2 ) {
			return array(
				'direction' => 'stable',
				'change'    => 0,
				'percent'   => 0,
			);
		}

		$first = $trends[0][ $metric ];
		$last = $trends[ count( $trends ) - 1 ][ $metric ];

		$change = $last - $first;
		$percent = $first > 0 ? ( $change / $first ) * 100 : 0;

		$direction = 'stable';
		if ( abs( $percent ) > 5 ) {
			$direction = $change > 0 ? 'up' : 'down';
		}

		return array(
			'direction' => $direction,
			'change'    => $change,
			'percent'   => round( $percent, 1 ),
			'first'     => $first,
			'last'      => $last,
		);
	}

	/**
	 * Export trends to CSV
	 *
	 * @param array $options Export options.
	 * @return string CSV data.
	 */
	public function export_to_csv( $options = array() ) {
		$trends = $this->get_trends( $options );

		if ( empty( $trends ) ) {
			return '';
		}

		// CSV header
		$headers = array_keys( $trends[0] );
		$csv = implode( ',', $headers ) . "\n";

		// CSV rows
		foreach ( $trends as $row ) {
			$csv .= implode( ',', array_map( array( $this, 'escape_csv_value' ), $row ) ) . "\n";
		}

		return $csv;
	}

	/**
	 * Escape CSV value
	 *
	 * @param mixed $value Value to escape.
	 * @return string Escaped value.
	 */
	private function escape_csv_value( $value ) {
		if ( is_null( $value ) ) {
			return '';
		}

		// Quote if contains comma, quote, or newline
		if ( preg_match( '/[,"\n\r]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}

	/**
	 * Clean up old snapshots
	 *
	 * @param int $days Keep snapshots newer than this many days.
	 * @return int Number of deleted rows.
	 */
	public function cleanup_old_snapshots( $days = 90 ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_NAME;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table}
				WHERE snapshot_date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
				$days
			)
		);

		return (int) $deleted;
	}
}
