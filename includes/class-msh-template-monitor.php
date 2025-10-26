<?php
/**
 * Template Performance Monitor
 *
 * Tracks template matching performance and automatically disables
 * poorly performing templates using a rolling window approach.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Template_Monitor
 *
 * Monitors template performance over last N evaluations.
 * Auto-disables templates with poor hit rate or slow performance.
 */
class MSH_Template_Monitor {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Template_Monitor|null
	 */
	private static $instance = null;

	/**
	 * Rolling window size (number of evaluations to track)
	 *
	 * @var int
	 */
	const WINDOW_SIZE = 1000;

	/**
	 * Performance thresholds
	 */
	const MAX_P95_DURATION_MS = 25; // Auto-disable if p95 >25ms (was avg)
	const MIN_HIT_RATE_PERCENT = 10; // Auto-disable if hit rate <10%
	const MIN_EVALUATIONS = 100; // Need 100 evals before checking thresholds
	const TARGET_P50_MS = 2; // Target: p50 < 2ms
	const WARNING_P95_MS = 15; // Warning: p95 > 15ms

	/**
	 * Option keys for persistent storage
	 */
	const OPTION_WINDOW = 'msh_template_monitor_window';
	const OPTION_STATS = 'msh_template_monitor_stats';
	const OPTION_DISABLED_BY_MONITOR = 'msh_template_monitor_disabled';
	const OPTION_HEALTH_HISTORY = 'msh_template_monitor_health_history';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Template_Monitor
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
		// Monitor is stateless - all data stored in options
	}

	/**
	 * Record a template evaluation.
	 *
	 * @param float $duration_ms Duration in milliseconds.
	 * @param bool  $matched     Whether a template matched.
	 * @param int   $template_id Template ID (if matched).
	 * @param bool  $error       Whether an error occurred during evaluation.
	 * @return void
	 */
	public function record_evaluation( $duration_ms, $matched, $template_id = null, $error = false ) {
		// Get current window
		$window = $this->get_window();

		// Add new evaluation
		$window[] = array(
			'timestamp'   => time(),
			'duration_ms' => round( $duration_ms, 2 ),
			'matched'     => $matched,
			'template_id' => $template_id,
			'error'       => $error, // NEW: Track errors
		);

		// Keep only last WINDOW_SIZE evaluations
		if ( count( $window ) > self::WINDOW_SIZE ) {
			$window = array_slice( $window, -self::WINDOW_SIZE );
		}

		// Save window
		update_option( self::OPTION_WINDOW, $window, false );

		// Update stats
		$this->update_stats( $window );

		// Check thresholds and auto-disable if needed
		$this->check_thresholds( $window );
	}

	/**
	 * Get rolling window data.
	 *
	 * @return array Array of evaluations.
	 */
	private function get_window() {
		$window = get_option( self::OPTION_WINDOW, array() );
		return is_array( $window ) ? $window : array();
	}

	/**
	 * Update statistics based on current window.
	 *
	 * @param array $window Current window data.
	 * @return void
	 */
	private function update_stats( $window ) {
		$total = count( $window );
		if ( 0 === $total ) {
			return;
		}

		$hits = 0;
		$errors = 0;
		$durations = array();

		foreach ( $window as $eval ) {
			if ( $eval['matched'] ) {
				$hits++;
			}
			if ( ! empty( $eval['error'] ) ) {
				$errors++;
			}
			$durations[] = $eval['duration_ms'];
		}

		// Calculate percentiles (p50, p95)
		sort( $durations );
		$p50_index = (int) ( count( $durations ) * 0.50 );
		$p95_index = (int) ( count( $durations ) * 0.95 );

		$stats = array(
			'total_evaluations' => $total,
			'total_hits'        => $hits,
			'total_errors'      => $errors,
			'hit_rate_percent'  => round( ( $hits / $total ) * 100, 1 ),
			'error_rate_percent' => round( ( $errors / $total ) * 100, 1 ),
			'p50_duration_ms'   => round( $durations[ $p50_index ] ?? 0, 2 ),
			'p95_duration_ms'   => round( $durations[ $p95_index ] ?? 0, 2 ),
			'last_updated'      => time(),
		);

		update_option( self::OPTION_STATS, $stats, false );
	}

	/**
	 * Get current statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_stats() {
		$stats = get_option( self::OPTION_STATS, array() );

		// Defaults
		return wp_parse_args(
			$stats,
			array(
				'total_evaluations'  => 0,
				'total_hits'         => 0,
				'total_errors'       => 0,
				'hit_rate_percent'   => 0,
				'error_rate_percent' => 0,
				'p50_duration_ms'    => 0,
				'p95_duration_ms'    => 0,
				'last_updated'       => 0,
			)
		);
	}

	/**
	 * Check performance thresholds and auto-disable if needed.
	 *
	 * @param array $window Current window data.
	 * @return void
	 */
	private function check_thresholds( $window ) {
		$total = count( $window );

		// Need minimum evaluations before checking
		if ( $total < self::MIN_EVALUATIONS ) {
			return;
		}

		$stats = $this->get_stats();

		// Check if already disabled by monitor
		$disabled_templates = $this->get_disabled_templates();

		// Check error rate threshold (Critical: > 5%)
		if ( $stats['error_rate_percent'] > 5.0 ) {
			if ( ! in_array( 'system', $disabled_templates, true ) ) {
				$this->auto_disable_system( 'high_error_rate', $stats );
			}
			return;
		}

		// Check hit rate threshold
		if ( $stats['hit_rate_percent'] < self::MIN_HIT_RATE_PERCENT ) {
			// Hit rate too low - disable template system
			if ( ! in_array( 'system', $disabled_templates, true ) ) {
				$this->auto_disable_system( 'low_hit_rate', $stats );
			}
			return;
		}

		// Check performance threshold (p95 instead of average)
		if ( $stats['p95_duration_ms'] > self::MAX_P95_DURATION_MS ) {
			// Performance too slow - disable template system
			if ( ! in_array( 'system', $disabled_templates, true ) ) {
				$this->auto_disable_system( 'slow_performance', $stats );
			}
			return;
		}

		// Track health status for this window
		$this->track_health_window( $stats );

		// If we're here and system was previously disabled, check two-window stability
		if ( in_array( 'system', $disabled_templates, true ) ) {
			if ( $this->check_two_window_stability() ) {
				$this->auto_enable_system( $stats );
			}
		}
	}

	/**
	 * Auto-disable template system due to poor performance.
	 *
	 * @param string $reason Reason code (low_hit_rate, slow_performance).
	 * @param array  $stats  Current statistics.
	 * @return void
	 */
	private function auto_disable_system( $reason, $stats ) {
		// Add to disabled list
		$disabled = $this->get_disabled_templates();
		$disabled[] = 'system';
		update_option( self::OPTION_DISABLED_BY_MONITOR, $disabled, false );

		// Disable feature flag
		if ( class_exists( 'MSH_Feature_Flags' ) ) {
			MSH_Feature_Flags::set( 'template_intelligence', false );
		}

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'template_system_auto_disabled',
				array(
					'reason'             => $reason,
					'hit_rate_percent'   => $stats['hit_rate_percent'],
					'error_rate_percent' => $stats['error_rate_percent'],
					'p50_duration_ms'    => $stats['p50_duration_ms'],
					'p95_duration_ms'    => $stats['p95_duration_ms'],
					'evaluations'        => $stats['total_evaluations'],
				)
			);
		}

		// Set admin notice
		$this->set_admin_notice( $reason, $stats );
	}

	/**
	 * Auto-enable template system after recovery.
	 *
	 * @param array $stats Current statistics.
	 * @return void
	 */
	private function auto_enable_system( $stats ) {
		// Remove from disabled list
		$disabled = $this->get_disabled_templates();
		$disabled = array_diff( $disabled, array( 'system' ) );
		update_option( self::OPTION_DISABLED_BY_MONITOR, $disabled, false );

		// Re-enable feature flag
		if ( class_exists( 'MSH_Feature_Flags' ) ) {
			MSH_Feature_Flags::set( 'template_intelligence', true );
		}

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'template_system_auto_enabled',
				array(
					'hit_rate_percent'   => $stats['hit_rate_percent'],
					'error_rate_percent' => $stats['error_rate_percent'],
					'p50_duration_ms'    => $stats['p50_duration_ms'],
					'p95_duration_ms'    => $stats['p95_duration_ms'],
					'evaluations'        => $stats['total_evaluations'],
				)
			);
		}

		// Clear admin notice
		delete_option( 'msh_template_monitor_notice' );
	}

	/**
	 * Get list of templates disabled by monitor.
	 *
	 * @return array Template IDs or 'system'.
	 */
	private function get_disabled_templates() {
		$disabled = get_option( self::OPTION_DISABLED_BY_MONITOR, array() );
		return is_array( $disabled ) ? $disabled : array();
	}

	/**
	 * Track health status for current window.
	 *
	 * Records whether the current window meets "healthy" criteria.
	 * Used for two-window stability check before re-enabling.
	 *
	 * @param array $stats Current statistics.
	 * @return void
	 */
	private function track_health_window( $stats ) {
		$history = get_option( self::OPTION_HEALTH_HISTORY, array() );

		// Determine if current window is healthy
		$is_healthy = (
			$stats['total_evaluations'] >= self::MIN_EVALUATIONS &&
			$stats['hit_rate_percent'] >= 20.0 &&
			$stats['p95_duration_ms'] <= 8.0 &&
			$stats['error_rate_percent'] < 3.0
		);

		// Add current window to history
		$history[] = array(
			'timestamp'          => time(),
			'healthy'            => $is_healthy,
			'hit_rate_percent'   => $stats['hit_rate_percent'],
			'p95_duration_ms'    => $stats['p95_duration_ms'],
			'error_rate_percent' => $stats['error_rate_percent'],
			'evaluations'        => $stats['total_evaluations'],
		);

		// Keep only last 2 windows
		if ( count( $history ) > 2 ) {
			$history = array_slice( $history, -2 );
		}

		update_option( self::OPTION_HEALTH_HISTORY, $history, false );
	}

	/**
	 * Check two-window stability for re-enable.
	 *
	 * Returns true only if BOTH of the last 2 windows were healthy.
	 * Prevents flapping by requiring sustained good performance.
	 *
	 * @return bool True if stable enough to re-enable.
	 */
	private function check_two_window_stability() {
		$history = get_option( self::OPTION_HEALTH_HISTORY, array() );

		// Need exactly 2 windows
		if ( count( $history ) < 2 ) {
			return false;
		}

		// Get last 2 windows
		$windows = array_slice( $history, -2 );

		// Both must be healthy
		return $windows[0]['healthy'] && $windows[1]['healthy'];
	}

	/**
	 * Set admin notice for auto-disable event.
	 *
	 * @param string $reason Reason code.
	 * @param array  $stats  Current statistics.
	 * @return void
	 */
	private function set_admin_notice( $reason, $stats ) {
		$notice = array(
			'type'      => 'warning',
			'reason'    => $reason,
			'stats'     => $stats,
			'timestamp' => time(),
		);

		update_option( 'msh_template_monitor_notice', $notice, false );
	}

	/**
	 * Get admin notice if any.
	 *
	 * @return array|null Notice data or null.
	 */
	public function get_admin_notice() {
		return get_option( 'msh_template_monitor_notice', null );
	}

	/**
	 * Dismiss admin notice.
	 *
	 * @return void
	 */
	public function dismiss_notice() {
		delete_option( 'msh_template_monitor_notice' );
	}

	/**
	 * Reset monitor (clear all data).
	 *
	 * @return void
	 */
	public function reset() {
		delete_option( self::OPTION_WINDOW );
		delete_option( self::OPTION_STATS );
		delete_option( self::OPTION_DISABLED_BY_MONITOR );
		delete_option( self::OPTION_HEALTH_HISTORY );
		delete_option( 'msh_template_monitor_notice' );

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry( 'template_monitor_reset', array() );
		}
	}

	/**
	 * Check if template system is healthy.
	 *
	 * @return array Health check result.
	 */
	public function health_check() {
		$stats = $this->get_stats();
		$disabled = $this->get_disabled_templates();

		$health = array(
			'status'  => 'healthy',
			'message' => __( 'Template system is performing well.', 'msh-image-optimizer' ),
			'stats'   => $stats,
		);

		// Check if disabled
		if ( in_array( 'system', $disabled, true ) ) {
			$health['status'] = 'disabled';
			$health['message'] = __( 'Template system auto-disabled due to poor performance.', 'msh-image-optimizer' );
			return $health;
		}

		// Check if not enough data
		if ( $stats['total_evaluations'] < self::MIN_EVALUATIONS ) {
			$health['status'] = 'warming_up';
			$health['message'] = sprintf(
				/* translators: %1$d current evaluations, %2$d required evaluations */
				__( 'Collecting data... %1$d of %2$d evaluations needed.', 'msh-image-optimizer' ),
				$stats['total_evaluations'],
				self::MIN_EVALUATIONS
			);
			return $health;
		}

		// Check hit rate
		if ( $stats['hit_rate_percent'] < self::MIN_HIT_RATE_PERCENT * 1.5 ) {
			// Within 50% of threshold
			$health['status'] = 'warning';
			$health['message'] = sprintf(
				/* translators: %1$s current hit rate, %2$d threshold */
				__( 'Low hit rate: %1$s%% (threshold: %2$d%%).', 'msh-image-optimizer' ),
				$stats['hit_rate_percent'],
				self::MIN_HIT_RATE_PERCENT
			);
			return $health;
		}

		// Check performance (p95 instead of average)
		if ( $stats['p95_duration_ms'] > self::MAX_P95_DURATION_MS * 0.8 ) {
			// Within 80% of threshold (20ms warning, 25ms critical)
			$health['status'] = 'warning';
			$health['message'] = sprintf(
				/* translators: %1$s current p95 duration, %2$d threshold */
				__( 'Slow performance: %1$sms p95 (threshold: %2$dms).', 'msh-image-optimizer' ),
				$stats['p95_duration_ms'],
				self::MAX_P95_DURATION_MS
			);
			return $health;
		}

		// Check error rate
		if ( $stats['error_rate_percent'] > 3.0 ) {
			// Approaching critical threshold (5%)
			$health['status'] = 'warning';
			$health['message'] = sprintf(
				/* translators: %s current error rate */
				__( 'High error rate: %s%% (threshold: 5%%).', 'msh-image-optimizer' ),
				$stats['error_rate_percent']
			);
			return $health;
		}

		return $health;
	}

	/**
	 * Get recent evaluations for debugging.
	 *
	 * @param int $limit Number of recent evaluations to return.
	 * @return array Recent evaluations.
	 */
	public function get_recent_evaluations( $limit = 10 ) {
		$window = $this->get_window();
		return array_slice( array_reverse( $window ), 0, $limit );
	}
}
