<?php
/**
 * Context Fusion Layer - Intent Classifier
 *
 * Classifies image usage intent as on_topic, off_topic, or unknown.
 * Uses rule-based classification with LLM fallback for complex cases.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Intent Classifier
 *
 * Determines whether an image is used in an on-topic or off-topic context.
 * Classification logic:
 * 1. Rule-based classification (fast, deterministic)
 * 2. LLM-based classification (slower, for complex cases)
 * 3. Returns confidence score (0-100)
 */
class MSH_Intent_Classifier {

	/**
	 * Classify intent for an image in a specific post context
	 *
	 * @param int    $media_id Media ID.
	 * @param array  $context  Post context data from Context Extractor.
	 * @param string $locale   Locale code.
	 * @return array Classification result with intent, confidence, and rules_fired
	 */
	public function classify( $media_id, $context, $locale = 'en_US' ) {
		// Try rule-based classification first
		$rule_result = $this->classify_by_rules( $media_id, $context );

		if ( $rule_result['confidence'] >= 80 ) {
			// High confidence from rules, use this result
			return $rule_result;
		}

		// Low confidence from rules, try LLM fallback
		$llm_result = $this->classify_by_llm( $media_id, $context, $locale );

		if ( $llm_result && $llm_result['confidence'] > $rule_result['confidence'] ) {
			// LLM has higher confidence, use LLM result
			return $llm_result;
		}

		// Fall back to rule result
		return $rule_result;
	}

	/**
	 * Rule-based classification
	 *
	 * Applies deterministic rules to classify intent.
	 * Fast and transparent (returns which rules fired).
	 *
	 * @param int   $media_id Media ID.
	 * @param array $context  Post context data.
	 * @return array Classification result
	 */
	private function classify_by_rules( $media_id, $context ) {
		$rules_fired = array();
		$intent      = 'unknown';
		$confidence  = 0;

		// Get image alt text and title
		$image_alt   = get_post_meta( $media_id, '_wp_attachment_image_alt', true );
		$image_post  = get_post( $media_id );
		$image_title = $image_post ? $image_post->post_title : '';
		$image_desc  = $image_post ? $image_post->post_content : '';

		// Combine image metadata for matching
		$image_text = strtolower( $image_alt . ' ' . $image_title . ' ' . $image_desc );

		// Combine post context for matching
		$post_text = strtolower(
			$context['title'] . ' ' .
			$context['excerpt'] . ' ' .
			implode( ' ', $context['headings']['h1'] ?? array() ) . ' ' .
			implode( ' ', $context['headings']['h2'] ?? array() )
		);

		// Rule 1: Featured image is always on-topic (high confidence)
		if ( isset( $context['metadata']['post_type'] ) ) {
			$featured_id = get_post_thumbnail_id( $context['post_id'] );
			if ( $featured_id === $media_id ) {
				$rules_fired[] = 'featured_image';
				$intent        = 'on_topic';
				$confidence    = 95;
				return array(
					'intent'      => $intent,
					'confidence'  => $confidence,
					'rules_fired' => $rules_fired,
				);
			}
		}

		// Rule 2: Image alt text matches post title/headings (high confidence)
		if ( ! empty( $image_alt ) ) {
			$image_alt_lower = strtolower( $image_alt );

			// Check against title
			if ( ! empty( $context['title'] ) ) {
				$title_lower = strtolower( $context['title'] );
				$similarity  = $this->calculate_similarity( $image_alt_lower, $title_lower );

				if ( $similarity > 0.6 ) {
					$rules_fired[] = 'alt_matches_title';
					$intent        = 'on_topic';
					$confidence    = max( $confidence, (int) ( $similarity * 100 ) );
				}
			}

			// Check against H1/H2 headings
			$all_headings = array_merge(
				$context['headings']['h1'] ?? array(),
				$context['headings']['h2'] ?? array()
			);

			foreach ( $all_headings as $heading ) {
				$similarity = $this->calculate_similarity( $image_alt_lower, strtolower( $heading ) );

				if ( $similarity > 0.5 ) {
					$rules_fired[] = 'alt_matches_heading';
					$intent        = 'on_topic';
					$confidence    = max( $confidence, (int) ( $similarity * 80 ) );
					break;
				}
			}
		}

		// Rule 3: Image appears in decorative patterns (off-topic)
		$decorative_patterns = array(
			'icon',
			'logo',
			'divider',
			'separator',
			'decoration',
			'background',
			'ornament',
			'border',
		);

		foreach ( $decorative_patterns as $pattern ) {
			if ( strpos( $image_text, $pattern ) !== false ) {
				$rules_fired[] = "decorative_pattern:{$pattern}";
				$intent        = 'off_topic';
				$confidence    = max( $confidence, 70 );
			}
		}

		// Rule 4: Image filename suggests stock/generic (lower confidence off-topic)
		if ( $image_post ) {
			$filename = basename( get_attached_file( $media_id ) );
			$filename = strtolower( $filename );

			$generic_patterns = array(
				'shutterstock',
				'istockphoto',
				'stock-photo',
				'generic',
				'placeholder',
				'default',
				'untitled',
			);

			foreach ( $generic_patterns as $pattern ) {
				if ( strpos( $filename, $pattern ) !== false ) {
					$rules_fired[] = "generic_filename:{$pattern}";
					// Don't override on_topic if already classified
					if ( $intent !== 'on_topic' ) {
						$intent     = 'off_topic';
						$confidence = max( $confidence, 60 );
					}
				}
			}
		}

		// Rule 5: Image metadata mentions post keywords (on-topic)
		if ( isset( $context['taxonomies'] ) ) {
			foreach ( $context['taxonomies'] as $taxonomy => $terms ) {
				foreach ( $terms as $term ) {
					$term_lower = strtolower( $term );

					if ( strpos( $image_text, $term_lower ) !== false ) {
						$rules_fired[] = "metadata_matches_taxonomy:{$taxonomy}";
						$intent        = 'on_topic';
						$confidence    = max( $confidence, 75 );
					}
				}
			}
		}

		// Rule 6: Image in sidebar/widget context (off-topic)
		// This would be detected by block_path or usage_type in calling code
		// We don't have that info here, so skip

		// Rule 7: If no rules fired, remain unknown with low confidence
		if ( empty( $rules_fired ) ) {
			$intent     = 'unknown';
			$confidence = 0;
		}

		return array(
			'intent'      => $intent,
			'confidence'  => $confidence,
			'rules_fired' => $rules_fired,
		);
	}

	/**
	 * LLM-based classification (fallback)
	 *
	 * Uses AI service to classify intent when rules are inconclusive.
	 * Requires MSH_AI_Service to be available.
	 *
	 * @param int    $media_id Media ID.
	 * @param array  $context  Post context data.
	 * @param string $locale   Locale code.
	 * @return array|null Classification result or null if LLM unavailable
	 */
	private function classify_by_llm( $media_id, $context, $locale ) {
		// Check if AI service is available
		if ( ! class_exists( 'MSH_AI_Service' ) ) {
			return null;
		}

		$ai_service = MSH_AI_Service::get_instance();

		// Check if AI is enabled and has credits
		if ( ! $ai_service || ! method_exists( $ai_service, 'generate_metadata' ) ) {
			return null;
		}

		// Get image data
		$image_post = get_post( $media_id );
		if ( ! $image_post ) {
			return null;
		}

		$image_alt   = get_post_meta( $media_id, '_wp_attachment_image_alt', true );
		$image_title = $image_post->post_title;
		$image_url   = wp_get_attachment_url( $media_id );

		// Build prompt for LLM
		$prompt = $this->build_intent_prompt( $media_id, $context, $image_alt, $image_title, $image_url );

		// Call AI service (using existing infrastructure)
		// Note: This is a placeholder - actual implementation would depend on MSH_AI_Service API
		// For now, return null to fall back to rules
		return null;

		// Example of what this would look like if implemented:
		// $response = $ai_service->classify_intent( $prompt );
		// if ( $response && isset( $response['intent'] ) ) {
		//     return array(
		//         'intent'      => $response['intent'],
		//         'confidence'  => $response['confidence'] ?? 50,
		//         'rules_fired' => array( 'llm_classification' ),
		//     );
		// }
		// return null;
	}

	/**
	 * Build LLM prompt for intent classification
	 *
	 * @param int    $media_id    Media ID.
	 * @param array  $context     Post context data.
	 * @param string $image_alt   Image alt text.
	 * @param string $image_title Image title.
	 * @param string $image_url   Image URL.
	 * @return string Prompt text
	 */
	private function build_intent_prompt( $media_id, $context, $image_alt, $image_title, $image_url ) {
		$prompt = "Classify whether this image is used on-topic or off-topic in the following context:\n\n";

		$prompt .= "POST CONTEXT:\n";
		$prompt .= "Title: {$context['title']}\n";
		$prompt .= "Excerpt: {$context['excerpt']}\n";

		if ( ! empty( $context['headings']['h1'] ) ) {
			$prompt .= 'Main Headings: ' . implode( ', ', $context['headings']['h1'] ) . "\n";
		}

		if ( ! empty( $context['taxonomies'] ) ) {
			$prompt .= 'Topics: ';
			foreach ( $context['taxonomies'] as $taxonomy => $terms ) {
				$prompt .= implode( ', ', $terms ) . '; ';
			}
			$prompt .= "\n";
		}

		$prompt .= "\nIMAGE:\n";
		$prompt .= "Title: {$image_title}\n";
		$prompt .= "Alt Text: {$image_alt}\n";
		$prompt .= "URL: {$image_url}\n";

		$prompt .= "\nClassify as:\n";
		$prompt .= "- on_topic: Image directly relates to the post's main subject\n";
		$prompt .= "- off_topic: Image is decorative, generic, or unrelated to the main subject\n";
		$prompt .= "- unknown: Cannot determine with confidence\n\n";

		$prompt .= 'Return JSON: {"intent": "on_topic|off_topic|unknown", "confidence": 0-100, "reasoning": "brief explanation"}';

		return $prompt;
	}

	/**
	 * Calculate text similarity using Levenshtein distance
	 *
	 * @param string $text1 First text.
	 * @param string $text2 Second text.
	 * @return float Similarity score (0-1)
	 */
	private function calculate_similarity( $text1, $text2 ) {
		// Normalize texts
		$text1 = $this->normalize_text( $text1 );
		$text2 = $this->normalize_text( $text2 );

		if ( empty( $text1 ) || empty( $text2 ) ) {
			return 0.0;
		}

		// Calculate Levenshtein distance
		$max_length = max( strlen( $text1 ), strlen( $text2 ) );
		$distance   = levenshtein( $text1, $text2 );

		// Convert to similarity (0-1)
		$similarity = 1 - ( $distance / $max_length );

		return max( 0.0, $similarity );
	}

	/**
	 * Normalize text for comparison
	 *
	 * @param string $text Text to normalize.
	 * @return string Normalized text
	 */
	private function normalize_text( $text ) {
		// Convert to lowercase
		$text = strtolower( $text );

		// Remove punctuation
		$text = preg_replace( '/[^\w\s]/', '', $text );

		// Normalize whitespace
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Batch classify multiple images in a post
	 *
	 * Optimized for processing all images in a post at once.
	 *
	 * @param array  $media_ids Array of media IDs.
	 * @param array  $context   Post context data.
	 * @param string $locale    Locale code.
	 * @return array Array of classification results keyed by media_id
	 */
	public function batch_classify( $media_ids, $context, $locale = 'en_US' ) {
		$results = array();

		foreach ( $media_ids as $media_id ) {
			$results[ $media_id ] = $this->classify( $media_id, $context, $locale );
		}

		return $results;
	}
}
