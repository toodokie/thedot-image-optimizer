<?php
/**
 * Metrics Collector - Daily Aggregation & Performance Tracking
 *
 * Collects and aggregates metrics for Phase 5+9 automation:
 * - Job processing stats (success/failure rates, processing times)
 * - Cache performance (hit rates, staleness rates)
 * - Daily/weekly/monthly aggregations
 * - Storage in msh_metrics table for trend analysis
 *
 * Runs via WP-Cron daily at midnight.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Metrics_Collector
 *
 * Aggregates and stores daily metrics for performance tracking.
 */
class MSH_Metrics_Collector {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Metrics_Collector|null
	 */
	private static $instance = null;

	/**
	 * Metrics table name.
	 *
	 * @var string
	 */
	private $metrics_table;

	/**
	 * Jobs table name.
	 *
	 * @var string
	 */
	private $jobs_table;

	/**
	 * Cache table name.
	 *
	 * @var string
	 */
	private $cache_table;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Metrics_Collector
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor - registers cron hooks.
	 */
	private function __construct() {
		global $wpdb;

		$this->metrics_table = $wpdb->prefix . 'msh_metrics';
		$this->jobs_table    = $wpdb->prefix . 'msh_jobs';
		$this->cache_table   = $wpdb->prefix . 'msh_metadata_cache';

		// Schedule daily metrics collection
		add_action( 'msh_collect_daily_metrics', array( $this, 'collect_daily_metrics' ) );

		// Register cron schedule
		if ( ! wp_next_scheduled( 'msh_collect_daily_metrics' ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'msh_collect_daily_metrics' );
		}
	}

	/**
	 * Collect and store daily metrics.
	 *
	 * Called via WP-Cron at midnight daily.
	 *
	 * @return void
	 */
	public function collect_daily_metrics() {
		$date = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );

		// Collect job metrics
		$job_metrics = $this->collect_job_metrics( $date );

		// Collect cache metrics
		$cache_metrics = $this->collect_cache_metrics( $date );

		// Collect performance metrics
		$performance_metrics = $this->collect_performance_metrics( $date );

		// Merge all metrics
		$metrics = array_merge( $job_metrics, $cache_metrics, $performance_metrics );

		// Store in database
		$this->store_metrics( $date, $metrics );

		// Clean old metrics (keep 90 days)
		$this->cleanup_old_metrics( 90 );

		do_action( 'msh_daily_metrics_collected', $date, $metrics );
	}

	/**
	 * Collect job processing metrics for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array Metrics array.
	 */
	private function collect_job_metrics( $date ) {
		global $wpdb;

		$start = $date . ' 00:00:00';
		$end   = $date . ' 23:59:59';

		// Total jobs created
		$total_jobs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->jobs_table}
				WHERE created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);

		// Jobs by status
		$completed_jobs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->jobs_table}
				WHERE status = 'complete' AND updated_at >= %s AND updated_at <= %s",
				$start,
				$end
			)
		);

		$failed_jobs = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->jobs_table}
				WHERE status = 'failed' AND updated_at >= %s AND updated_at <= %s",
				$start,
				$end
			)
		);

		// Jobs by priority
		$high_priority = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->jobs_table}
				WHERE priority = 'high' AND created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);

		$medium_priority = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->jobs_table}
				WHERE priority = 'medium' AND created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);

		$normal_priority = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->jobs_table}
				WHERE priority = 'normal' AND created_at >= %s AND created_at <= %s",
				$start,
				$end
			)
		);

		// Average processing time (completed jobs only)
		$avg_processing_time = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at))
				FROM {$this->jobs_table}
				WHERE status = 'complete' AND updated_at >= %s AND updated_at <= %s",
				$start,
				$end
			)
		);

		// Success rate
		$success_rate = $total_jobs > 0 ? ( $completed_jobs / $total_jobs ) * 100 : 0;

		return array(
			'jobs_total'              => (int) $total_jobs,
			'jobs_completed'          => (int) $completed_jobs,
			'jobs_failed'             => (int) $failed_jobs,
			'jobs_high_priority'      => (int) $high_priority,
			'jobs_medium_priority'    => (int) $medium_priority,
			'jobs_normal_priority'    => (int) $normal_priority,
			'jobs_success_rate'       => round( $success_rate, 2 ),
			'jobs_avg_processing_sec' => round( (float) $avg_processing_time, 2 ),
		);
	}

	/**
	 * Collect cache performance metrics for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array Metrics array.
	 */
	private function collect_cache_metrics( $date ) {
		global $wpdb;

		$start = $date . ' 00:00:00';
		$end   = $date . ' 23:59:59';

		// Total cache entries updated
		$cache_entries_updated = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->cache_table}
				WHERE updated_at >= %s AND updated_at <= %s",
				$start,
				$end
			)
		);

		// AI-generated vs manual entries
		$ai_generated = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->cache_table}
				WHERE chosen_source = 'ai' AND updated_at >= %s AND updated_at <= %s",
				$start,
				$end
			)
		);

		$manual_entries = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->cache_table}
				WHERE chosen_source = 'manual' AND updated_at >= %s AND updated_at <= %s",
				$start,
				$end
			)
		);

		// Current staleness rate (snapshot at end of day)
		$total_entries = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->cache_table}" );
		$stale_entries = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->cache_table}
			WHERE stale_reason IS NOT NULL AND stale_reason != ''"
		);

		$staleness_rate = $total_entries > 0 ? ( $stale_entries / $total_entries ) * 100 : 0;

		// Entries by locale
		$locales = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT locale, COUNT(*) as count
				FROM {$this->cache_table}
				WHERE updated_at >= %s AND updated_at <= %s
				GROUP BY locale",
				$start,
				$end
			),
			ARRAY_A
		);

		$locale_breakdown = array();
		foreach ( $locales as $locale_data ) {
			$locale_breakdown[ $locale_data['locale'] ] = (int) $locale_data['count'];
		}

		return array(
			'cache_entries_updated'   => (int) $cache_entries_updated,
			'cache_ai_generated'      => (int) $ai_generated,
			'cache_manual_entries'    => (int) $manual_entries,
			'cache_staleness_rate'    => round( $staleness_rate, 2 ),
			'cache_total_entries'     => (int) $total_entries,
			'cache_stale_entries'     => (int) $stale_entries,
			'cache_locale_breakdown'  => wp_json_encode( $locale_breakdown ),
		);
	}

	/**
	 * Collect system performance metrics.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array Metrics array.
	 */
	private function collect_performance_metrics( $date ) {
		global $wpdb;

		// Database size metrics
		$cache_table_size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
				FROM information_schema.TABLES
				WHERE table_schema = %s AND table_name = %s",
				DB_NAME,
				$this->cache_table
			)
		);

		$jobs_table_size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2)
				FROM information_schema.TABLES
				WHERE table_schema = %s AND table_name = %s",
				DB_NAME,
				$this->jobs_table
			)
		);

		// Total image attachments
		$total_images = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		// Images with AI-generated metadata
		$images_with_metadata = $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id) FROM {$this->cache_table}"
		);

		$coverage_rate = $total_images > 0 ? ( $images_with_metadata / $total_images ) * 100 : 0;

		return array(
			'perf_cache_table_mb'     => (float) $cache_table_size,
			'perf_jobs_table_mb'      => (float) $jobs_table_size,
			'perf_total_images'       => (int) $total_images,
			'perf_images_with_metadata' => (int) $images_with_metadata,
			'perf_coverage_rate'      => round( $coverage_rate, 2 ),
		);
	}

	/**
	 * Store metrics in database.
	 *
	 * @param string $date    Date in Y-m-d format.
	 * @param array  $metrics Metrics array.
	 * @return void
	 */
	private function store_metrics( $date, $metrics ) {
		global $wpdb;

		// Delete existing metrics for this date (if re-running)
		$wpdb->delete(
			$this->metrics_table,
			array( 'metric_date' => $date ),
			array( '%s' )
		);

		// Insert each metric as a separate row
		foreach ( $metrics as $key => $value ) {
			$wpdb->insert(
				$this->metrics_table,
				array(
					'metric_date' => $date,
					'metric_key'  => $key,
					'metric_value' => is_numeric( $value ) ? $value : $value,
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Clean up old metrics beyond retention period.
	 *
	 * @param int $retention_days Number of days to keep.
	 * @return void
	 */
	private function cleanup_old_metrics( $retention_days = 90 ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->metrics_table} WHERE metric_date < %s",
				$cutoff_date
			)
		);

		if ( $deleted ) {
			do_action( 'msh_metrics_cleanup', $deleted, $cutoff_date );
		}
	}

	/**
	 * Get metrics for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array Associative array of metric_key => metric_value.
	 */
	public function get_metrics_for_date( $date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_key, metric_value FROM {$this->metrics_table}
				WHERE metric_date = %s",
				$date
			),
			ARRAY_A
		);

		$metrics = array();
		foreach ( $results as $row ) {
			$metrics[ $row['metric_key'] ] = $row['metric_value'];
		}

		return $metrics;
	}

	/**
	 * Get metrics for a date range.
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array Array of dates with their metrics.
	 */
	public function get_metrics_range( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_date, metric_key, metric_value
				FROM {$this->metrics_table}
				WHERE metric_date >= %s AND metric_date <= %s
				ORDER BY metric_date ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$metrics_by_date = array();
		foreach ( $results as $row ) {
			$date = $row['metric_date'];
			if ( ! isset( $metrics_by_date[ $date ] ) ) {
				$metrics_by_date[ $date ] = array();
			}
			$metrics_by_date[ $date ][ $row['metric_key'] ] = $row['metric_value'];
		}

		return $metrics_by_date;
	}

	/**
	 * Get aggregated metrics for a specific key over a date range.
	 *
	 * Useful for trend charts (e.g., jobs_completed over last 30 days).
	 *
	 * @param string $metric_key Metric key (e.g., 'jobs_completed').
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array Array of [date => value].
	 */
	public function get_metric_trend( $metric_key, $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT metric_date, metric_value
				FROM {$this->metrics_table}
				WHERE metric_key = %s
				AND metric_date >= %s
				AND metric_date <= %s
				ORDER BY metric_date ASC",
				$metric_key,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$trend = array();
		foreach ( $results as $row ) {
			$trend[ $row['metric_date'] ] = $row['metric_value'];
		}

		return $trend;
	}

	/**
	 * Get summary stats for dashboard widgets.
	 *
	 * @return array Summary statistics.
	 */
	public function get_summary_stats() {
		$yesterday = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
		$last_7    = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
		$last_30   = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$yesterday_metrics = $this->get_metrics_for_date( $yesterday );
		$week_metrics      = $this->get_metrics_range( $last_7, $yesterday );
		$month_metrics     = $this->get_metrics_range( $last_30, $yesterday );

		// Calculate weekly totals
		$week_jobs_total = 0;
		$week_jobs_completed = 0;
		foreach ( $week_metrics as $date => $metrics ) {
			$week_jobs_total     += isset( $metrics['jobs_total'] ) ? (int) $metrics['jobs_total'] : 0;
			$week_jobs_completed += isset( $metrics['jobs_completed'] ) ? (int) $metrics['jobs_completed'] : 0;
		}

		// Calculate monthly totals
		$month_jobs_total = 0;
		$month_jobs_completed = 0;
		foreach ( $month_metrics as $date => $metrics ) {
			$month_jobs_total     += isset( $metrics['jobs_total'] ) ? (int) $metrics['jobs_total'] : 0;
			$month_jobs_completed += isset( $metrics['jobs_completed'] ) ? (int) $metrics['jobs_completed'] : 0;
		}

		return array(
			'yesterday' => $yesterday_metrics,
			'last_7_days' => array(
				'jobs_total'     => $week_jobs_total,
				'jobs_completed' => $week_jobs_completed,
			),
			'last_30_days' => array(
				'jobs_total'     => $month_jobs_total,
				'jobs_completed' => $month_jobs_completed,
			),
		);
	}
}

// Initialize
MSH_Metrics_Collector::get_instance();
