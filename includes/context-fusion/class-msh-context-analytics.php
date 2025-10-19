<?php
/**
 * Context Analytics
 *
 * Provides analytics and reporting for Context Fusion Layer.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Analytics Class
 *
 * Generates analytics and statistics for context data.
 */
class MSH_Context_Analytics {

	/**
	 * Get analytics overview
	 *
	 * @param array $options Query options.
	 * @return array Analytics data.
	 */
	public function get_overview( $options = array() ) {
		global $wpdb;

		$defaults = array(
			'locale' => get_locale(),
			'days'   => 30,
		);

		$options = wp_parse_args( $options, $defaults );

		$table = $wpdb->prefix . 'msh_optimizer_context';

		// Get basic counts
		$total_contexts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE locale = %s",
				$options['locale']
			)
		);

		$total_images = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT media_id) FROM {$table} WHERE locale = %s",
				$options['locale']
			)
		);

		$total_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$table} WHERE locale = %s",
				$options['locale']
			)
		);

		// Get intent distribution
		$intent_dist = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT intent, COUNT(*) as count
				FROM {$table}
				WHERE locale = %s
				GROUP BY intent",
				$options['locale']
			),
			ARRAY_A
		);

		$intent_breakdown = array(
			'on_topic' => 0,
			'off_topic' => 0,
			'unknown' => 0,
		);

		foreach ( $intent_dist as $row ) {
			$intent_breakdown[ $row['intent'] ] = (int) $row['count'];
		}

		// Get average context score
		$avg_score = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(context_score) FROM {$table} WHERE locale = %s",
				$options['locale']
			)
		);

		// Handle null when no contexts exist
		if ( null === $avg_score ) {
			$avg_score = 0;
		}

		// Get top performing images (highest avg context score)
		$top_images = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT media_id,
					AVG(context_score) as avg_score,
					COUNT(*) as usage_count
				FROM {$table}
				WHERE locale = %s
				GROUP BY media_id
				ORDER BY avg_score DESC
				LIMIT 5",
				$options['locale']
			),
			ARRAY_A
		);

		// Enrich with image titles
		foreach ( $top_images as &$image ) {
			$post = get_post( $image['media_id'] );
			$image['title'] = $post ? $post->post_title : __( 'Unknown', 'msh-image-optimizer' );
			$image['avg_score'] = round( $image['avg_score'], 1 );
		}

		// Get images needing attention (low scores or orphaned)
		$needs_attention = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT media_id,
					AVG(context_score) as avg_score,
					COUNT(*) as usage_count,
					SUM(CASE WHEN intent = 'on_topic' THEN 1 ELSE 0 END) as on_topic_count
				FROM {$table}
				WHERE locale = %s
				GROUP BY media_id
				HAVING avg_score < 50 OR on_topic_count = 0
				ORDER BY avg_score ASC
				LIMIT 10",
				$options['locale']
			),
			ARRAY_A
		);

		foreach ( $needs_attention as &$image ) {
			$post = get_post( $image['media_id'] );
			$image['title'] = $post ? $post->post_title : __( 'Unknown', 'msh-image-optimizer' );
			$image['avg_score'] = round( $image['avg_score'], 1 );
			$image['on_topic_count'] = (int) $image['on_topic_count'];
		}

		return array(
			'totals' => array(
				'contexts' => (int) $total_contexts,
				'images' => (int) $total_images,
				'posts' => (int) $total_posts,
				'avg_score' => round( $avg_score, 1 ),
			),
			'intent_distribution' => $intent_breakdown,
			'top_performers' => $top_images,
			'needs_attention' => $needs_attention,
		);
	}

	/**
	 * Get keyword statistics
	 *
	 * @param array $options Query options.
	 * @return array Keyword stats.
	 */
	public function get_keyword_stats( $options = array() ) {
		global $wpdb;

		$defaults = array(
			'locale' => get_locale(),
			'limit'  => 20,
		);

		$options = wp_parse_args( $options, $defaults );

		$table = $wpdb->prefix . 'msh_optimizer_context';

		// Get all keywords (stored in 'keywords' column as JSON)
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT keywords FROM {$table} WHERE locale = %s AND keywords IS NOT NULL AND keywords != ''",
				$options['locale']
			),
			ARRAY_A
		);

		$keyword_counts = array();

		foreach ( $results as $row ) {
			if ( empty( $row['keywords'] ) ) {
				continue;
			}

			$keywords = json_decode( $row['keywords'], true );
			if ( is_array( $keywords ) ) {
				foreach ( $keywords as $keyword ) {
					if ( ! isset( $keyword_counts[ $keyword ] ) ) {
						$keyword_counts[ $keyword ] = 0;
					}
					$keyword_counts[ $keyword ]++;
				}
			}
		}

		// Sort by frequency
		arsort( $keyword_counts );

		// Limit results
		$keyword_counts = array_slice( $keyword_counts, 0, $options['limit'], true );

		return array(
			'top_keywords' => $keyword_counts,
			'total_unique' => count( $keyword_counts ),
		);
	}

	/**
	 * Get usage trends
	 *
	 * @param array $options Query options.
	 * @return array Trend data.
	 */
	public function get_usage_trends( $options = array() ) {
		global $wpdb;

		$defaults = array(
			'locale' => get_locale(),
			'days'   => 30,
		);

		$options = wp_parse_args( $options, $defaults );

		$table = $wpdb->prefix . 'msh_optimizer_context';

		// Get contexts created in the last N days, grouped by day
		$trends = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date,
					COUNT(*) as contexts_created,
					AVG(context_score) as avg_score
				FROM {$table}
				WHERE locale = %s
					AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$options['locale'],
				$options['days']
			),
			ARRAY_A
		);

		foreach ( $trends as &$trend ) {
			$trend['avg_score'] = round( $trend['avg_score'], 1 );
			$trend['contexts_created'] = (int) $trend['contexts_created'];
		}

		return $trends;
	}

	/**
	 * Get post type breakdown
	 *
	 * @param array $options Query options.
	 * @return array Post type stats.
	 */
	public function get_post_type_stats( $options = array() ) {
		global $wpdb;

		$defaults = array(
			'locale' => get_locale(),
		);

		$options = wp_parse_args( $options, $defaults );

		$table = $wpdb->prefix . 'msh_optimizer_context';
		$posts_table = $wpdb->posts;

		// Get stats by post type
		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.post_type,
					COUNT(c.id) as context_count,
					AVG(c.context_score) as avg_score,
					COUNT(DISTINCT c.media_id) as unique_images
				FROM {$table} c
				INNER JOIN {$posts_table} p ON c.post_id = p.ID
				WHERE c.locale = %s
				GROUP BY p.post_type
				ORDER BY context_count DESC",
				$options['locale']
			),
			ARRAY_A
		);

		foreach ( $stats as &$stat ) {
			$stat['context_count'] = (int) $stat['context_count'];
			$stat['unique_images'] = (int) $stat['unique_images'];
			$stat['avg_score'] = round( $stat['avg_score'], 1 );

			// Get post type label
			$post_type_obj = get_post_type_object( $stat['post_type'] );
			$stat['label'] = $post_type_obj ? $post_type_obj->labels->name : $stat['post_type'];
		}

		return $stats;
	}

	/**
	 * Get quality distribution
	 *
	 * @param array $options Query options.
	 * @return array Quality distribution.
	 */
	public function get_quality_distribution( $options = array() ) {
		global $wpdb;

		$defaults = array(
			'locale' => get_locale(),
		);

		$options = wp_parse_args( $options, $defaults );

		$table = $wpdb->prefix . 'msh_optimizer_context';

		// Get context score distribution in ranges
		$distribution = array(
			'excellent' => 0,  // 80-100
			'good' => 0,       // 60-79
			'fair' => 0,       // 40-59
			'poor' => 0,       // 0-39
		);

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN context_score >= 80 THEN 1 ELSE 0 END) as excellent,
					SUM(CASE WHEN context_score >= 60 AND context_score < 80 THEN 1 ELSE 0 END) as good,
					SUM(CASE WHEN context_score >= 40 AND context_score < 60 THEN 1 ELSE 0 END) as fair,
					SUM(CASE WHEN context_score < 40 THEN 1 ELSE 0 END) as poor
				FROM {$table}
				WHERE locale = %s",
				$options['locale']
			),
			ARRAY_A
		);

		if ( ! empty( $results ) ) {
			$distribution = array(
				'excellent' => (int) $results[0]['excellent'],
				'good' => (int) $results[0]['good'],
				'fair' => (int) $results[0]['fair'],
				'poor' => (int) $results[0]['poor'],
			);
		}

		return $distribution;
	}

	/**
	 * Get trend indicators
	 *
	 * @param array $options Query options.
	 * @return array Trend indicators.
	 */
	public function get_trend_indicators( $options = array() ) {
		$defaults = array(
			'locale' => get_locale(),
			'days'   => 7,
		);

		$options = wp_parse_args( $options, $defaults );

		$snapshots = MSH_Context_Snapshots::get_instance();

		return array(
			'score_trend'     => $snapshots->calculate_trend( 'avg_context_score', $options['days'], $options['locale'] ),
			'on_topic_trend'  => $snapshots->calculate_trend( 'on_topic_count', $options['days'], $options['locale'] ),
			'contexts_trend'  => $snapshots->calculate_trend( 'total_contexts', $options['days'], $options['locale'] ),
		);
	}

	/**
	 * Export analytics to CSV
	 *
	 * @param string $type    Export type ('overview', 'keywords', 'post_types', 'trends').
	 * @param array  $options Export options.
	 * @return string CSV data.
	 */
	public function export_to_csv( $type, $options = array() ) {
		$defaults = array(
			'locale' => get_locale(),
		);

		$options = wp_parse_args( $options, $defaults );

		switch ( $type ) {
			case 'overview':
				return $this->export_overview_csv( $options );

			case 'keywords':
				return $this->export_keywords_csv( $options );

			case 'post_types':
				return $this->export_post_types_csv( $options );

			case 'trends':
				$snapshots = MSH_Context_Snapshots::get_instance();
				return $snapshots->export_to_csv( $options );

			default:
				return '';
		}
	}

	/**
	 * Export overview to CSV
	 *
	 * @param array $options Options.
	 * @return string CSV data.
	 */
	private function export_overview_csv( $options ) {
		$overview = $this->get_overview( $options );

		$csv = "Metric,Value\n";
		$csv .= "Total Contexts," . $overview['totals']['contexts'] . "\n";
		$csv .= "Total Images," . $overview['totals']['images'] . "\n";
		$csv .= "Total Posts," . $overview['totals']['posts'] . "\n";
		$csv .= "Average Score," . $overview['totals']['avg_score'] . "\n";
		$csv .= "\n";

		$csv .= "Intent,Count\n";
		foreach ( $overview['intent_distribution'] as $intent => $count ) {
			$csv .= ucfirst( str_replace( '_', ' ', $intent ) ) . ',' . $count . "\n";
		}

		return $csv;
	}

	/**
	 * Export keywords to CSV
	 *
	 * @param array $options Options.
	 * @return string CSV data.
	 */
	private function export_keywords_csv( $options ) {
		$keyword_stats = $this->get_keyword_stats( $options );

		$csv = "Keyword,Frequency\n";
		foreach ( $keyword_stats['top_keywords'] as $keyword => $count ) {
			$csv .= $this->escape_csv_value( $keyword ) . ',' . $count . "\n";
		}

		return $csv;
	}

	/**
	 * Export post types to CSV
	 *
	 * @param array $options Options.
	 * @return string CSV data.
	 */
	private function export_post_types_csv( $options ) {
		$stats = $this->get_post_type_stats( $options );

		$csv = "Post Type,Contexts,Unique Images,Average Score\n";
		foreach ( $stats as $stat ) {
			$csv .= $this->escape_csv_value( $stat['label'] ) . ',';
			$csv .= $stat['context_count'] . ',';
			$csv .= $stat['unique_images'] . ',';
			$csv .= $stat['avg_score'] . "\n";
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
}
