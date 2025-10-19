<?php
/**
 * Context Fusion Layer - Context Recommender
 *
 * Provides context-aware image recommendations:
 * - Similar context search
 * - Better fit suggestions
 * - Context-based duplicate detection
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Recommender
 *
 * Recommends images based on context similarity and quality scores.
 */
class MSH_Context_Recommender {

	/**
	 * Find images with similar context
	 *
	 * Uses keyword overlap, intent matching, and context scores to find similar images.
	 *
	 * @param int   $media_id Media ID to find similar images for.
	 * @param array $options  Search options.
	 * @return array Array of similar images with similarity scores
	 */
	public function find_similar_images( $media_id, $options = array() ) {
		$defaults = array(
			'min_similarity' => 0.6,   // 0-1 threshold
			'limit'          => 10,
			'locale'         => get_locale(),
			'intent_match'   => false, // Require same intent
			'exclude_self'   => true,
		);

		$options = wp_parse_args( $options, $defaults );

		// Get source image context
		$manager        = MSH_Context_Manager::get_instance();
		$source_context = $manager->get_media_rollup( $media_id, $options['locale'] );

		if ( ! $source_context ) {
			return array();
		}

		// Get all images with context in this locale
		global $wpdb;
		$table_name = MSH_Context_Database::get_table_name();

		$intent_clause = '';
		if ( $options['intent_match'] ) {
			$intent_clause = $wpdb->prepare( 'AND intent = %s', $source_context['intent'] );
		}

		$exclude_clause = '';
		if ( $options['exclude_self'] ) {
			$exclude_clause = $wpdb->prepare( 'AND media_id != %d', $media_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT media_id
				FROM {$table_name}
				WHERE locale = %s
				{$intent_clause}
				{$exclude_clause}",
				$options['locale']
			),
			ARRAY_A
		);

		if ( empty( $candidates ) ) {
			return array();
		}

		// Calculate similarity for each candidate
		$results = array();

		foreach ( $candidates as $candidate ) {
			$candidate_id      = (int) $candidate['media_id'];
			$candidate_context = $manager->get_media_rollup( $candidate_id, $options['locale'] );

			if ( ! $candidate_context ) {
				continue;
			}

			$similarity = $this->calculate_similarity( $source_context, $candidate_context );

			if ( $similarity >= $options['min_similarity'] ) {
				$results[] = array(
					'media_id'          => $candidate_id,
					'similarity'        => round( $similarity, 3 ),
					'matching_keywords' => $this->get_matching_keywords(
						$source_context['keywords'],
						$candidate_context['keywords']
					),
					'context_score'     => $candidate_context['avg_context_score'],
					'intent'            => $candidate_context['intent'],
					'subject'           => $candidate_context['top_subject'],
				);
			}
		}

		// Sort by similarity descending
		usort(
			$results,
			function ( $a, $b ) {
				return $b['similarity'] <=> $a['similarity'];
			}
		);

		// Limit results
		return array_slice( $results, 0, $options['limit'] );
	}

	/**
	 * Suggest better images for a specific post
	 *
	 * Finds images that would have a higher context score in the given post.
	 *
	 * @param int   $post_id         Post ID.
	 * @param int   $current_media_id Current image ID.
	 * @param array $options         Search options.
	 * @return array Array of suggestions with improvement metrics
	 */
	public function suggest_better_fit( $post_id, $current_media_id, $options = array() ) {
		$defaults = array(
			'min_improvement' => 10,  // Minimum score improvement
			'locale'          => get_locale(),
			'limit'           => 5,
			'intent_filter'   => 'on_topic', // Only suggest on-topic images
		);

		$options = wp_parse_args( $options, $defaults );

		// Get post context
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$extractor    = new MSH_Context_Extractor();
		$post_context = $extractor->extract_post_context( $post, $options['locale'] );

		// Get current image context in this post
		$manager = MSH_Context_Manager::get_instance();
		$current_contexts = $manager->list_media_in_post( $post_id, $options['locale'] );

		$current_score = 0;
		foreach ( $current_contexts as $ctx ) {
			if ( (int) $ctx['media_id'] === $current_media_id ) {
				$current_score = (int) $ctx['context_score'];
				break;
			}
		}

		// Find candidate images
		global $wpdb;
		$table_name = MSH_Context_Database::get_table_name();

		$intent_clause = '';
		if ( $options['intent_filter'] ) {
			$intent_clause = $wpdb->prepare( 'AND intent = %s', $options['intent_filter'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT media_id, AVG(context_score) as avg_score
				FROM {$table_name}
				WHERE locale = %s
				AND media_id != %d
				{$intent_clause}
				GROUP BY media_id
				HAVING avg_score > %d
				ORDER BY avg_score DESC
				LIMIT 50",
				$options['locale'],
				$current_media_id,
				$current_score
			),
			ARRAY_A
		);

		if ( empty( $candidates ) ) {
			return array();
		}

		// Evaluate each candidate for this specific post
		$classifier = new MSH_Intent_Classifier();
		$normalizer = new MSH_Keyword_Normalizer();
		$results    = array();

		foreach ( $candidates as $candidate ) {
			$candidate_id = (int) $candidate['media_id'];

			// Simulate this image in the post context
			$intent_result = $classifier->classify( $candidate_id, $post_context, $options['locale'] );

			// Calculate potential score
			$combined_text = $post_context['title'] . ' ' . $post_context['excerpt'] . ' ' . $post_context['content'];
			$keywords      = $normalizer->extract_keywords( $combined_text, $options['locale'], 20 );

			$potential_score = $this->calculate_context_score( $intent_result, $keywords );
			$improvement     = $potential_score - $current_score;

			if ( $improvement >= $options['min_improvement'] ) {
				// Get candidate context for additional info
				$candidate_rollup = $manager->get_media_rollup( $candidate_id, $options['locale'] );

				$results[] = array(
					'media_id'         => $candidate_id,
					'improvement'      => $improvement,
					'reason'           => $this->get_improvement_reason( $intent_result, $current_score, $potential_score ),
					'keywords_match'   => $this->count_keyword_matches( $keywords, $candidate_rollup['keywords'] ?? array() ),
					'current_score'    => $current_score,
					'suggested_score'  => $potential_score,
					'intent'           => $intent_result['intent'],
					'intent_confidence' => $intent_result['confidence'],
				);
			}
		}

		// Sort by improvement descending
		usort(
			$results,
			function ( $a, $b ) {
				return $b['improvement'] <=> $a['improvement'];
			}
		);

		return array_slice( $results, 0, $options['limit'] );
	}

	/**
	 * Find orphaned images (no on-topic usage)
	 *
	 * Identifies images that are unused or only used in off-topic/low-score contexts.
	 *
	 * @param array $options Search options.
	 * @return array Array of orphaned images with metrics
	 */
	public function find_orphaned_images( $options = array() ) {
		$defaults = array(
			'max_score'      => 50,  // Below this = orphaned
			'locale'         => get_locale(),
			'limit'          => 50,
			'min_age_days'   => 30, // Only consider images older than 30 days
		);

		$options = wp_parse_args( $options, $defaults );

		global $wpdb;
		$table_name = MSH_Context_Database::get_table_name();

		// Find images with no on-topic usage and low scores
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orphaned = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.media_id,
					COUNT(*) as usage_count,
					MAX(c.context_score) as best_score,
					AVG(c.context_score) as avg_score,
					GROUP_CONCAT(DISTINCT c.intent) as intents,
					MIN(c.first_seen) as first_seen
				FROM {$table_name} c
				WHERE c.locale = %s
				AND c.media_id NOT IN (
					SELECT DISTINCT media_id
					FROM {$table_name}
					WHERE intent = 'on_topic' AND locale = %s
				)
				AND c.first_seen < DATE_SUB(NOW(), INTERVAL %d DAY)
				GROUP BY c.media_id
				HAVING best_score <= %d
				ORDER BY best_score ASC, usage_count ASC
				LIMIT %d",
				$options['locale'],
				$options['locale'],
				$options['min_age_days'],
				$options['max_score'],
				$options['limit']
			),
			ARRAY_A
		);

		if ( empty( $orphaned ) ) {
			return array();
		}

		// Enrich with attachment data
		foreach ( $orphaned as &$item ) {
			$item['media_id']    = (int) $item['media_id'];
			$item['usage_count'] = (int) $item['usage_count'];
			$item['best_score']  = (int) $item['best_score'];
			$item['avg_score']   = round( (float) $item['avg_score'], 1 );

			$attachment = get_post( $item['media_id'] );
			if ( $attachment ) {
				$item['title']     = $attachment->post_title;
				$item['file_size'] = size_format( filesize( get_attached_file( $item['media_id'] ) ) );
				$item['mime_type'] = $attachment->post_mime_type;
			}
		}

		return $orphaned;
	}

	/**
	 * Find context-based duplicates
	 *
	 * Groups visually similar images and checks if they have similar context.
	 * True duplicates have both visual and context similarity.
	 *
	 * @param array $options Search options.
	 * @return array Array of duplicate groups
	 */
	public function find_context_duplicates( $options = array() ) {
		$defaults = array(
			'min_context_similarity' => 0.9,
			'locale'                 => get_locale(),
			'limit'                  => 20,
		);

		$options = wp_parse_args( $options, $defaults );

		// This requires perceptual hash integration
		// For now, return placeholder
		// TODO: Integrate with MSH_Perceptual_Hash class

		return array();
	}

	/**
	 * Calculate similarity between two context objects
	 *
	 * Weights:
	 * - Keyword overlap: 50%
	 * - Intent match: 25%
	 * - Context score proximity: 15%
	 * - Subject similarity: 10%
	 *
	 * @param array $context1 First context rollup.
	 * @param array $context2 Second context rollup.
	 * @return float Similarity score 0-1
	 */
	private function calculate_similarity( $context1, $context2 ) {
		$score = 0.0;

		// Keyword overlap (50%)
		if ( ! empty( $context1['keywords'] ) && ! empty( $context2['keywords'] ) ) {
			$intersection = array_intersect( $context1['keywords'], $context2['keywords'] );
			$union        = array_unique( array_merge( $context1['keywords'], $context2['keywords'] ) );
			$jaccard      = count( $union ) > 0 ? count( $intersection ) / count( $union ) : 0;

			$score += $jaccard * 0.5;
		}

		// Intent match (25%)
		if ( $context1['intent'] === $context2['intent'] ) {
			// Exact match
			$score += 0.25;
		} elseif ( $context1['intent'] !== 'unknown' && $context2['intent'] !== 'unknown' ) {
			// Both classified but different - penalty
			$score += 0.0;
		} else {
			// One or both unknown - partial credit
			$score += 0.125;
		}

		// Context score proximity (15%)
		$score1 = $context1['avg_context_score'];
		$score2 = $context2['avg_context_score'];

		if ( $score1 > 0 && $score2 > 0 ) {
			$score_diff      = abs( $score1 - $score2 );
			$score_proximity = 1 - ( $score_diff / 100 );
			$score          += $score_proximity * 0.15;
		}

		// Subject similarity (10%)
		if ( ! empty( $context1['top_subject'] ) && ! empty( $context2['top_subject'] ) ) {
			$subject_similarity = $this->calculate_text_similarity(
				$context1['top_subject'],
				$context2['top_subject']
			);

			$score += $subject_similarity * 0.1;
		}

		return min( 1.0, max( 0.0, $score ) );
	}

	/**
	 * Get matching keywords between two arrays
	 *
	 * @param array $keywords1 First keyword array.
	 * @param array $keywords2 Second keyword array.
	 * @return array Matching keywords
	 */
	private function get_matching_keywords( $keywords1, $keywords2 ) {
		if ( empty( $keywords1 ) || empty( $keywords2 ) ) {
			return array();
		}

		return array_values( array_intersect( $keywords1, $keywords2 ) );
	}

	/**
	 * Count keyword matches
	 *
	 * @param array $keywords1 First keyword array.
	 * @param array $keywords2 Second keyword array.
	 * @return int Number of matches
	 */
	private function count_keyword_matches( $keywords1, $keywords2 ) {
		return count( $this->get_matching_keywords( $keywords1, $keywords2 ) );
	}

	/**
	 * Calculate text similarity using simple comparison
	 *
	 * @param string $text1 First text.
	 * @param string $text2 Second text.
	 * @return float Similarity 0-1
	 */
	private function calculate_text_similarity( $text1, $text2 ) {
		$text1 = strtolower( trim( $text1 ) );
		$text2 = strtolower( trim( $text2 ) );

		if ( $text1 === $text2 ) {
			return 1.0;
		}

		// Simple word overlap
		$words1       = explode( ' ', $text1 );
		$words2       = explode( ' ', $text2 );
		$intersection = array_intersect( $words1, $words2 );
		$union        = array_unique( array_merge( $words1, $words2 ) );

		return count( $union ) > 0 ? count( $intersection ) / count( $union ) : 0.0;
	}

	/**
	 * Calculate context score (same algorithm as in CLI and processor)
	 *
	 * @param array $intent_result Intent classification result.
	 * @param array $keywords      Extracted keywords.
	 * @return int Score 0-100
	 */
	private function calculate_context_score( $intent_result, $keywords ) {
		$score = 0;

		// Intent confidence contributes 60%
		if ( 'on_topic' === $intent_result['intent'] ) {
			$score += (int) ( $intent_result['confidence'] * 0.6 );
		} elseif ( 'off_topic' === $intent_result['intent'] ) {
			$score += (int) ( ( 100 - $intent_result['confidence'] ) * 0.3 );
		} else {
			$score += 20; // Unknown intent = low score
		}

		// Keyword count contributes 40%
		$keyword_count = count( $keywords );
		$keyword_score = min( 100, $keyword_count * 5 ); // 5 points per keyword, max 100
		$score        += (int) ( $keyword_score * 0.4 );

		return min( 100, $score );
	}

	/**
	 * Get improvement reason text
	 *
	 * @param array $intent_result Intent result for candidate.
	 * @param int   $current_score Current image score.
	 * @param int   $new_score     Candidate image score.
	 * @return string Reason text
	 */
	private function get_improvement_reason( $intent_result, $current_score, $new_score ) {
		if ( 'on_topic' === $intent_result['intent'] && $intent_result['confidence'] > 70 ) {
			return 'Highly relevant to content (on-topic)';
		}

		if ( $new_score >= 80 ) {
			return 'Excellent context match';
		}

		if ( $new_score - $current_score >= 30 ) {
			return 'Significantly better fit';
		}

		return 'Better context alignment';
	}
}
