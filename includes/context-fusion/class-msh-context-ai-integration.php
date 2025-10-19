<?php
/**
 * Context Fusion Layer - AI Integration
 *
 * Integrates context data with AI metadata generation.
 * Enhances alt text, title, and description suggestions using context.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context AI Integration
 *
 * Connects Context Fusion Layer with MSH_AI_Service to provide
 * context-aware image metadata generation.
 */
class MSH_Context_AI_Integration {

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_AI_Integration|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_AI_Integration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		// Hook into AI metadata generation (before prompt is built)
		add_filter( 'msh_ai_metadata_prompt_context', array( $this, 'enhance_ai_prompt_with_context' ), 10, 3 );

		// Hook into metadata generation result (after AI responds)
		add_filter( 'msh_ai_metadata_generated', array( $this, 'validate_with_context' ), 10, 3 );
	}

	/**
	 * Enhance AI prompt with context data
	 *
	 * Adds context information to the AI prompt for better suggestions.
	 *
	 * @param string $prompt       Current prompt.
	 * @param int    $media_id     Media ID.
	 * @param array  $options      Generation options.
	 * @return string Enhanced prompt
	 */
	public function enhance_ai_prompt_with_context( $prompt, $media_id, $options = array() ) {
		$locale = isset( $options['locale'] ) ? $options['locale'] : get_locale();

		// Get context rollup
		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			// No context available, return original prompt
			return $prompt;
		}

		// Build context section for prompt
		$context_section = "\n\n## CONTEXT INFORMATION\n\n";

		// Add intent classification
		$context_section .= sprintf(
			"This image has been classified as **%s** with %d%% confidence.\n",
			str_replace( '_', '-', $rollup['intent'] ),
			$rollup['intent_confidence']
		);

		if ( 'on_topic' === $rollup['intent'] ) {
			$context_section .= "The image is directly related to the content's main subject matter.\n";
		} elseif ( 'off_topic' === $rollup['intent'] ) {
			$context_section .= "The image serves a decorative or supporting role.\n";
		}

		$context_section .= "\n";

		// Add keywords if available
		if ( ! empty( $rollup['keywords'] ) ) {
			$top_keywords = array_slice( $rollup['keywords'], 0, 10 );
			$context_section .= sprintf(
				"**Relevant keywords from content:** %s\n\n",
				implode( ', ', $top_keywords )
			);
		}

		// Add subject if available
		if ( ! empty( $rollup['top_subject'] ) ) {
			$context_section .= sprintf(
				"**Primary topic:** %s\n\n",
				$rollup['top_subject']
			);
		}

		// Add entities if available
		$has_entities = false;
		if ( ! empty( $rollup['entities']['brands'] ) ) {
			$context_section .= sprintf(
				"**Related brands:** %s\n",
				implode( ', ', $rollup['entities']['brands'] )
			);
			$has_entities     = true;
		}
		if ( ! empty( $rollup['entities']['places'] ) ) {
			$context_section .= sprintf(
				"**Related places:** %s\n",
				implode( ', ', $rollup['entities']['places'] )
			);
			$has_entities     = true;
		}
		if ( ! empty( $rollup['entities']['people'] ) ) {
			$context_section .= sprintf(
				"**Related people:** %s\n",
				implode( ', ', $rollup['entities']['people'] )
			);
			$has_entities     = true;
		}

		if ( $has_entities ) {
			$context_section .= "\n";
		}

		// Add usage statistics
		$context_section .= sprintf(
			"**Usage:** This image appears in %d different contexts across %d total usages.\n\n",
			$rollup['context_count'],
			$rollup['total_usage_count']
		);

		// Add instructions based on intent
		if ( 'on_topic' === $rollup['intent'] ) {
			$context_section .= "**Instructions:** Generate descriptive, keyword-rich metadata that reflects the image's role in explaining or illustrating the content's main subject. Use the keywords and subject information provided to ensure alignment with the content.\n";
		} elseif ( 'off_topic' === $rollup['intent'] ) {
			$context_section .= "**Instructions:** Generate concise, general metadata appropriate for a decorative or supporting image. Focus on what the image shows rather than trying to connect it to specific content keywords.\n";
		} else {
			$context_section .= "**Instructions:** Generate balanced metadata that describes the image accurately. The image's relationship to the content is unclear, so focus on visual description.\n";
		}

		// Append context to prompt
		return $prompt . $context_section;
	}

	/**
	 * Validate AI-generated metadata against context
	 *
	 * Checks if generated metadata aligns with context data.
	 * Provides warnings or suggestions if misalignment detected.
	 *
	 * @param array $metadata  Generated metadata (alt, title, description).
	 * @param int   $media_id  Media ID.
	 * @param array $options   Generation options.
	 * @return array Metadata with validation info
	 */
	public function validate_with_context( $metadata, $media_id, $options = array() ) {
		$locale = isset( $options['locale'] ) ? $options['locale'] : get_locale();

		// Get context rollup
		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			// No context to validate against
			return $metadata;
		}

		// Add validation info to metadata
		$metadata['context_validation'] = array(
			'has_context'    => true,
			'intent'         => $rollup['intent'],
			'confidence'     => $rollup['intent_confidence'],
			'context_score'  => $rollup['avg_context_score'],
			'warnings'       => array(),
			'suggestions'    => array(),
		);

		// Validate keyword presence
		if ( ! empty( $rollup['keywords'] ) ) {
			$top_keywords    = array_slice( $rollup['keywords'], 0, 5 );
			$metadata_text   = strtolower( ( $metadata['alt'] ?? '' ) . ' ' . ( $metadata['title'] ?? '' ) . ' ' . ( $metadata['description'] ?? '' ) );
			$missing_keywords = array();

			foreach ( $top_keywords as $keyword ) {
				if ( strpos( $metadata_text, strtolower( $keyword ) ) === false ) {
					$missing_keywords[] = $keyword;
				}
			}

			if ( count( $missing_keywords ) > 2 && 'on_topic' === $rollup['intent'] ) {
				$metadata['context_validation']['warnings'][] = sprintf(
					'Generated metadata may be missing key context keywords: %s',
					implode( ', ', array_slice( $missing_keywords, 0, 3 ) )
				);

				$metadata['context_validation']['suggestions'][] = sprintf(
					'Consider incorporating: %s',
					implode( ', ', array_slice( $missing_keywords, 0, 3 ) )
				);
			}
		}

		// Validate length based on intent
		$alt_length = strlen( $metadata['alt'] ?? '' );

		if ( 'on_topic' === $rollup['intent'] && $alt_length < 20 ) {
			$metadata['context_validation']['warnings'][] = 'Alt text may be too brief for an on-topic image';
			$metadata['context_validation']['suggestions'][] = 'Expand alt text to include more contextual information';
		}

		if ( 'off_topic' === $rollup['intent'] && $alt_length > 80 ) {
			$metadata['context_validation']['warnings'][] = 'Alt text may be too detailed for a decorative image';
			$metadata['context_validation']['suggestions'][] = 'Consider simplifying alt text for decorative images';
		}

		// Validate subject alignment
		if ( ! empty( $rollup['top_subject'] ) && 'on_topic' === $rollup['intent'] ) {
			$subject_words = explode( ' ', strtolower( $rollup['top_subject'] ) );
			$subject_found = false;

			foreach ( $subject_words as $word ) {
				if ( strlen( $word ) > 3 && strpos( strtolower( $metadata['alt'] ?? '' ), $word ) !== false ) {
					$subject_found = true;
					break;
				}
			}

			if ( ! $subject_found ) {
				$metadata['context_validation']['suggestions'][] = sprintf(
					'Consider mentioning the main topic: %s',
					$rollup['top_subject']
				);
			}
		}

		// Calculate alignment score
		$alignment_score = $this->calculate_alignment_score( $metadata, $rollup );
		$metadata['context_validation']['alignment_score'] = $alignment_score;

		if ( $alignment_score < 50 ) {
			$metadata['context_validation']['warnings'][] = 'Low alignment with context (score: ' . $alignment_score . ')';
		}

		return $metadata;
	}

	/**
	 * Calculate alignment score between metadata and context
	 *
	 * @param array $metadata Generated metadata.
	 * @param array $rollup   Context rollup.
	 * @return int Score 0-100
	 */
	private function calculate_alignment_score( $metadata, $rollup ) {
		$score = 0;

		$metadata_text = strtolower(
			( $metadata['alt'] ?? '' ) . ' ' .
			( $metadata['title'] ?? '' ) . ' ' .
			( $metadata['description'] ?? '' )
		);

		// Keyword overlap (40 points)
		if ( ! empty( $rollup['keywords'] ) ) {
			$top_keywords     = array_slice( $rollup['keywords'], 0, 10 );
			$matched_keywords = 0;

			foreach ( $top_keywords as $keyword ) {
				if ( strpos( $metadata_text, strtolower( $keyword ) ) !== false ) {
					$matched_keywords++;
				}
			}

			$score += (int) ( ( $matched_keywords / count( $top_keywords ) ) * 40 );
		}

		// Intent alignment (30 points)
		$alt_length = strlen( $metadata['alt'] ?? '' );

		if ( 'on_topic' === $rollup['intent'] ) {
			// On-topic should have substantial alt text
			if ( $alt_length >= 30 && $alt_length <= 120 ) {
				$score += 30;
			} elseif ( $alt_length >= 20 ) {
				$score += 20;
			} elseif ( $alt_length >= 10 ) {
				$score += 10;
			}
		} elseif ( 'off_topic' === $rollup['intent'] ) {
			// Off-topic should be concise
			if ( $alt_length >= 10 && $alt_length <= 60 ) {
				$score += 30;
			} elseif ( $alt_length <= 80 ) {
				$score += 20;
			}
		} else {
			// Unknown intent, just check if something exists
			if ( $alt_length > 0 ) {
				$score += 15;
			}
		}

		// Subject presence (30 points)
		if ( ! empty( $rollup['top_subject'] ) ) {
			$subject_words = explode( ' ', strtolower( $rollup['top_subject'] ) );
			$subject_match = 0;

			foreach ( $subject_words as $word ) {
				if ( strlen( $word ) > 3 && strpos( $metadata_text, $word ) !== false ) {
					$subject_match++;
				}
			}

			if ( ! empty( $subject_words ) ) {
				$score += (int) ( ( $subject_match / count( $subject_words ) ) * 30 );
			}
		}

		return min( 100, $score );
	}

	/**
	 * Generate context-aware metadata for an image
	 *
	 * Public method to manually trigger context-aware generation.
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale   Locale code.
	 * @param array  $options  Generation options.
	 * @return array|WP_Error Generated metadata or error
	 */
	public function generate_context_aware_metadata( $media_id, $locale = 'en_US', $options = array() ) {
		// Check if AI service is available
		if ( ! class_exists( 'MSH_AI_Service' ) ) {
			return new WP_Error( 'ai_unavailable', __( 'AI service not available', 'msh-image-optimizer' ) );
		}

		$ai_service = MSH_AI_Service::get_instance();

		if ( ! $ai_service ) {
			return new WP_Error( 'ai_unavailable', __( 'AI service not initialized', 'msh-image-optimizer' ) );
		}

		// Get context rollup
		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		// Add context to options
		$options['locale']  = $locale;
		$options['context'] = $rollup;

		// Call AI service (this will trigger our filters)
		// Note: Actual implementation depends on MSH_AI_Service API
		// For now, return placeholder
		return array(
			'alt'         => '',
			'title'       => '',
			'description' => '',
			'message'     => 'Context-aware generation requires MSH_AI_Service integration',
		);
	}

	/**
	 * Get context summary for display
	 *
	 * Returns human-readable context summary for UI.
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale   Locale code.
	 * @return string|null Context summary or null
	 */
	public function get_context_summary( $media_id, $locale = 'en_US' ) {
		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			return null;
		}

		$summary = sprintf(
			'Intent: %s (%d%% confidence), Context Score: %d/100',
			ucfirst( str_replace( '_', ' ', $rollup['intent'] ) ),
			$rollup['intent_confidence'],
			$rollup['avg_context_score']
		);

		if ( ! empty( $rollup['keywords'] ) ) {
			$summary .= sprintf(
				', Keywords: %s',
				implode( ', ', array_slice( $rollup['keywords'], 0, 5 ) )
			);
		}

		return $summary;
	}
}
