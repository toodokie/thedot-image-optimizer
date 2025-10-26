<?php
/**
 * Shadow Precision Engine
 *
 * Tracks shadow template evaluation accuracy and determines when
 * templates are safe to promote from shadow → active mode.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6-B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Shadow_Engine
 *
 * Manages shadow mode precision tracking and promotion rules.
 */
class MSH_Shadow_Engine {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Shadow_Engine|null
	 */
	private static $instance = null;

	/**
	 * Promotion thresholds
	 */
	const MIN_PRECISION_PERCENT = 95.0; // Must achieve 95%+ precision
	const MIN_EVALUATIONS = 500;        // Need 500+ evaluations
	const MIN_SITES = 2;                // Must work on 2+ different sites
	const MIN_TRUE_POSITIVES = 50;      // Need 50+ confirmed matches

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Shadow_Engine
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
		// Stateless - all data in database
	}

	/**
	 * Record a shadow evaluation.
	 *
	 * Called when a shadow template is evaluated against an image.
	 *
	 * @param int    $template_id    Template ID.
	 * @param int    $attachment_id  Attachment ID.
	 * @param bool   $matched        Whether template matched.
	 * @param bool   $expected_match Expected outcome (null if unknown).
	 * @param float  $duration_ms    Evaluation duration.
	 * @param array  $context        Context data for hash.
	 * @param string $site_id        Site identifier.
	 * @return bool Success.
	 */
	public function record_evaluation( $template_id, $attachment_id, $matched, $expected_match, $duration_ms, $context, $site_id = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'msh_shadow_stats';

		// Generate context hash for deduplication
		$context_hash = md5( wp_json_encode( $context ) );

		// Handle NULL for expected_match (wpdb doesn't support NULL with %d format)
		$data = array(
			'template_id'   => $template_id,
			'attachment_id' => $attachment_id,
			'matched'       => $matched ? 1 : 0,
			'duration_ms'   => round( $duration_ms, 2 ),
			'context_hash'  => $context_hash,
			'evaluated_at'  => current_time( 'mysql' ),
			'site_id'       => $site_id,
		);

		$formats = array( '%d', '%d', '%d', '%f', '%s', '%s', '%s' );

		// Only add expected_match if not NULL
		if ( null !== $expected_match ) {
			$data['expected_match'] = $expected_match ? 1 : 0;
			$formats = array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s' );
		}

		$result = $wpdb->insert( $table, $data, $formats );

		if ( false === $result ) {
			// Log error
			if ( function_exists( 'msh_telemetry' ) ) {
				msh_telemetry(
					'shadow_evaluation_error',
					array(
						'template_id'   => $template_id,
						'attachment_id' => $attachment_id,
						'error'         => $wpdb->last_error,
					)
				);
			}
			return false;
		}

		return true;
	}

	/**
	 * Calculate precision metrics for a template.
	 *
	 * Precision = True Positives / (True Positives + False Positives)
	 * Where:
	 * - True Positive: matched=1, expected_match=1
	 * - False Positive: matched=1, expected_match=0
	 * - True Negative: matched=0, expected_match=0
	 * - False Negative: matched=0, expected_match=1
	 *
	 * @param int $template_id Template ID.
	 * @return array Precision metrics.
	 */
	public function calculate_precision( $template_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'msh_shadow_stats';

		// Get all evaluations with known expected outcomes
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_evaluations,
					COUNT(DISTINCT site_id) as site_count,
					SUM(CASE WHEN matched = 1 AND expected_match = 1 THEN 1 ELSE 0 END) as true_positives,
					SUM(CASE WHEN matched = 1 AND expected_match = 0 THEN 1 ELSE 0 END) as false_positives,
					SUM(CASE WHEN matched = 0 AND expected_match = 1 THEN 1 ELSE 0 END) as false_negatives,
					SUM(CASE WHEN matched = 0 AND expected_match = 0 THEN 1 ELSE 0 END) as true_negatives,
					AVG(duration_ms) as avg_duration_ms,
					MIN(evaluated_at) as first_eval,
					MAX(evaluated_at) as last_eval
				FROM {$table}
				WHERE template_id = %d
				AND expected_match IS NOT NULL",
				$template_id
			),
			ARRAY_A
		);

		if ( empty( $stats ) || 0 === (int) $stats['total_evaluations'] ) {
			return array(
				'precision_percent'  => 0,
				'recall_percent'     => 0,
				'total_evaluations'  => 0,
				'true_positives'     => 0,
				'false_positives'    => 0,
				'false_negatives'    => 0,
				'true_negatives'     => 0,
				'site_count'         => 0,
				'avg_duration_ms'    => 0,
				'promotable'         => false,
				'promotion_blockers' => array( 'No evaluation data' ),
			);
		}

		$tp = (int) $stats['true_positives'];
		$fp = (int) $stats['false_positives'];
		$fn = (int) $stats['false_negatives'];
		$tn = (int) $stats['true_negatives'];
		$total = (int) $stats['total_evaluations'];
		$sites = (int) $stats['site_count'];

		// Calculate precision: TP / (TP + FP)
		$precision_percent = ( $tp + $fp ) > 0 ? ( $tp / ( $tp + $fp ) ) * 100 : 0;

		// Calculate recall: TP / (TP + FN)
		$recall_percent = ( $tp + $fn ) > 0 ? ( $tp / ( $tp + $fn ) ) * 100 : 0;

		// Check promotion eligibility
		$blockers = array();
		$promotable = true;

		if ( $precision_percent < self::MIN_PRECISION_PERCENT ) {
			$blockers[] = sprintf( 'Precision %.1f%% < %d%%', $precision_percent, self::MIN_PRECISION_PERCENT );
			$promotable = false;
		}

		if ( $total < self::MIN_EVALUATIONS ) {
			$blockers[] = sprintf( '%d evaluations < %d required', $total, self::MIN_EVALUATIONS );
			$promotable = false;
		}

		if ( $sites < self::MIN_SITES ) {
			$blockers[] = sprintf( '%d sites < %d required', $sites, self::MIN_SITES );
			$promotable = false;
		}

		if ( $tp < self::MIN_TRUE_POSITIVES ) {
			$blockers[] = sprintf( '%d true positives < %d required', $tp, self::MIN_TRUE_POSITIVES );
			$promotable = false;
		}

		return array(
			'precision_percent'  => round( $precision_percent, 2 ),
			'recall_percent'     => round( $recall_percent, 2 ),
			'total_evaluations'  => $total,
			'true_positives'     => $tp,
			'false_positives'    => $fp,
			'false_negatives'    => $fn,
			'true_negatives'     => $tn,
			'site_count'         => $sites,
			'avg_duration_ms'    => round( (float) $stats['avg_duration_ms'], 2 ),
			'first_evaluation'   => $stats['first_eval'],
			'last_evaluation'    => $stats['last_eval'],
			'promotable'         => $promotable,
			'promotion_blockers' => $promotable ? array() : $blockers,
		);
	}

	/**
	 * Check if template is eligible for promotion.
	 *
	 * @param int $template_id Template ID.
	 * @return array Result with eligible flag and reasons.
	 */
	public function check_promotion_eligibility( $template_id ) {
		$precision = $this->calculate_precision( $template_id );

		return array(
			'eligible' => $precision['promotable'],
			'blockers' => $precision['promotion_blockers'],
			'metrics'  => $precision,
		);
	}

	/**
	 * Promote template from shadow to active.
	 *
	 * @param int  $template_id Template ID.
	 * @param bool $force       Force promotion even if not eligible.
	 * @return array Result with success flag and message.
	 */
	public function promote_template( $template_id, $force = false ) {
		// Check eligibility
		$eligibility = $this->check_promotion_eligibility( $template_id );

		if ( ! $force && ! $eligibility['eligible'] ) {
			return array(
				'success' => false,
				'message' => 'Template not eligible for promotion',
				'blockers' => $eligibility['blockers'],
			);
		}

		// Update template mode to active
		global $wpdb;
		$table = $wpdb->prefix . 'msh_optimizer_templates';

		$result = $wpdb->update(
			$table,
			array( 'mode' => 'active' ),
			array( 'id' => $template_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => 'Database error during promotion',
				'error'   => $wpdb->last_error,
			);
		}

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'template_promoted',
				array(
					'template_id' => $template_id,
					'forced'      => $force,
					'metrics'     => $eligibility['metrics'],
				)
			);
		}

		return array(
			'success' => true,
			'message' => 'Template promoted to active',
			'metrics' => $eligibility['metrics'],
		);
	}

	/**
	 * Demote template from active to shadow.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $reason      Reason for demotion.
	 * @return array Result with success flag and message.
	 */
	public function demote_template( $template_id, $reason = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'msh_optimizer_templates';

		$result = $wpdb->update(
			$table,
			array( 'mode' => 'shadow' ),
			array( 'id' => $template_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return array(
				'success' => false,
				'message' => 'Database error during demotion',
				'error'   => $wpdb->last_error,
			);
		}

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'template_demoted',
				array(
					'template_id' => $template_id,
					'reason'      => $reason,
				)
			);
		}

		return array(
			'success' => true,
			'message' => 'Template demoted to shadow',
		);
	}

	/**
	 * Get templates ready for promotion.
	 *
	 * @return array Array of template IDs with their metrics.
	 */
	public function get_promotable_templates() {
		global $wpdb;
		$template_table = $wpdb->prefix . 'msh_optimizer_templates';

		// Get all shadow templates
		$shadow_templates = $wpdb->get_col(
			"SELECT id FROM {$template_table} WHERE mode = 'shadow' AND is_active = 1"
		);

		$promotable = array();

		foreach ( $shadow_templates as $template_id ) {
			$eligibility = $this->check_promotion_eligibility( $template_id );

			if ( $eligibility['eligible'] ) {
				$promotable[] = array(
					'template_id' => $template_id,
					'metrics'     => $eligibility['metrics'],
				);
			}
		}

		return $promotable;
	}

	/**
	 * Clean up old shadow stats.
	 *
	 * Removes evaluations older than specified days.
	 *
	 * @param int $days Number of days to keep (default: 90).
	 * @return int Number of rows deleted.
	 */
	public function cleanup_old_stats( $days = 90 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'msh_shadow_stats';

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE evaluated_at < %s",
				$cutoff_date
			)
		);

		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'shadow_stats_cleanup',
				array(
					'days'    => $days,
					'deleted' => $deleted,
				)
			);
		}

		return $deleted;
	}

	/**
	 * Set ground truth label for shadow evaluation(s).
	 *
	 * Used by admins to label whether a shadow template SHOULD have matched.
	 *
	 * @param int|array $evaluation_ids Evaluation ID(s) to label.
	 * @param bool      $should_match   True if template should match, false otherwise.
	 * @return int Number of rows updated.
	 */
	public function set_ground_truth( $evaluation_ids, $should_match ) {
		global $wpdb;
		$table = $wpdb->prefix . 'msh_shadow_stats';

		// Convert single ID to array
		if ( ! is_array( $evaluation_ids ) ) {
			$evaluation_ids = array( $evaluation_ids );
		}

		// Sanitize IDs
		$evaluation_ids = array_map( 'intval', $evaluation_ids );

		if ( empty( $evaluation_ids ) ) {
			return 0;
		}

		$ids_placeholder = implode( ',', array_fill( 0, count( $evaluation_ids ), '%d' ) );
		$expected_value = $should_match ? 1 : 0;

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET expected_match = %d
				 WHERE id IN ({$ids_placeholder})",
				array_merge( array( $expected_value ), $evaluation_ids )
			)
		);

		// Log telemetry
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry(
				'shadow_ground_truth_set',
				array(
					'count'        => count( $evaluation_ids ),
					'should_match' => $should_match,
					'updated'      => $updated,
				)
			);
		}

		return $updated;
	}

	/**
	 * Get unlabeled shadow evaluations for a template.
	 *
	 * Returns evaluations where expected_match is NULL (needs ground truth).
	 *
	 * @param int $template_id Template ID.
	 * @param int $limit       Max results (default: 50).
	 * @return array Array of evaluation rows.
	 */
	public function get_unlabeled_evaluations( $template_id, $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'msh_shadow_stats';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, attachment_id, matched, duration_ms, evaluated_at
				 FROM {$table}
				 WHERE template_id = %d
				   AND expected_match IS NULL
				 ORDER BY evaluated_at DESC
				 LIMIT %d",
				$template_id,
				$limit
			),
			ARRAY_A
		);
	}
}
