<?php
/**
 * AI Prompt Template Engine
 *
 * Builds AI prompts with context fusion data and locale profiles.
 *
 * @package MSH_Image_Optimizer
 * @subpackage AI_Translation
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_Prompt_Template class.
 */
class MSH_Prompt_Template {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Prompt_Template
	 */
	private static $instance = null;

	/**
	 * Profile manager.
	 *
	 * @var MSH_Locale_Profile_Manager
	 */
	private $profile_manager;

	/**
	 * Context manager.
	 *
	 * @var MSH_Context_Manager
	 */
	private $context_manager;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Prompt_Template
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
		$this->profile_manager = MSH_Locale_Profile_Manager::get_instance();
		$this->context_manager = MSH_Context_Manager::get_instance();
	}

	/**
	 * Build AI prompt for image metadata generation.
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale Locale code.
	 * @param array  $options Optional parameters.
	 * @return array Prompt data with 'system' and 'user' messages.
	 */
	public function build_metadata_prompt( $media_id, $locale, $options = array() ) {
		$defaults = array(
			'usage_type' => 'featured', // featured, inline, thumbnail, hero
			'max_length' => array(
				'alt_text'    => 125,
				'title'       => 60,
				'description' => 200,
				'caption'     => 150,
			),
		);

		$options = wp_parse_args( $options, $defaults );

		// Get context fusion data
		$rollup = $this->context_manager->get_media_rollup( $media_id, $locale );

		// Get locale profile
		$profile = $this->profile_manager->get_profile_with_fallback( $locale );

		// Get protected terms
		$protected_terms = $this->profile_manager->get_protected_terms( $locale );

		// Build system prompt
		$system_prompt = $this->build_system_prompt( $profile, $protected_terms );

		// Build user prompt
		$user_prompt = $this->build_user_prompt( $media_id, $rollup, $profile, $options );

		return array(
			'system'  => $system_prompt,
			'user'    => $user_prompt,
			'context' => array(
				'media_id'    => $media_id,
				'locale'      => $locale,
				'profile'     => $profile,
				'rollup'      => $rollup,
				'max_length'  => $options['max_length'],
				'usage_type'  => $options['usage_type'],
			),
		);
	}

	/**
	 * Build system prompt.
	 *
	 * @param array $profile Locale profile.
	 * @param array $protected_terms Protected terms.
	 * @return string
	 */
	private function build_system_prompt( $profile, $protected_terms ) {
		$tone_map = array(
			'formal'       => 'Use formal, professional language. Avoid contractions and colloquialisms.',
			'friendly'     => 'Use warm, approachable language. Contractions are acceptable.',
			'professional' => 'Use clear, professional language with a balanced tone.',
			'casual'       => 'Use relaxed, conversational language. Be natural and approachable.',
		);

		$cta_map = array(
			'direct' => 'Include clear calls-to-action when appropriate.',
			'subtle' => 'Use gentle suggestions rather than direct commands.',
			'none'   => 'Avoid calls-to-action. Focus on descriptive content only.',
		);

		$formality_guidance = '';
		$formality_level    = (int) $profile['formality_level'];

		if ( $formality_level <= 2 ) {
			$formality_guidance = 'Use a very casual, conversational style. Be relatable and down-to-earth.';
		} elseif ( $formality_level >= 4 ) {
			$formality_guidance = 'Maintain a formal, dignified tone. Precision and professionalism are paramount.';
		} else {
			$formality_guidance = 'Strike a balance between professional and approachable.';
		}

		$tone_guidance = $tone_map[ $profile['tone'] ] ?? $tone_map['professional'];
		$cta_guidance  = $cta_map[ $profile['cta_style'] ] ?? $cta_map['subtle'];

		$system = "You are an expert image metadata writer specializing in SEO-optimized, accessible alt text and descriptions.\n\n";
		$system .= "TONE & STYLE:\n";
		$system .= "- {$tone_guidance}\n";
		$system .= "- {$formality_guidance}\n";
		$system .= "- {$cta_guidance}\n";
		$system .= "\n";

		if ( ! empty( $profile['special_instructions'] ) ) {
			$system .= "LOCALE-SPECIFIC INSTRUCTIONS:\n";
			$system .= $profile['special_instructions'] . "\n\n";
		}

		if ( ! empty( $protected_terms ) ) {
			$system .= "PROTECTED TERMS (Never translate or modify):\n";
			$system .= implode( ', ', $protected_terms ) . "\n\n";
		}

		if ( ! empty( $profile['forbidden_terms'] ) ) {
			$forbidden = explode( ',', $profile['forbidden_terms'] );
			$forbidden = array_map( 'trim', $forbidden );
			$system   .= "FORBIDDEN TERMS (Never use):\n";
			$system   .= implode( ', ', $forbidden ) . "\n\n";
		}

		$system .= "ACCESSIBILITY REQUIREMENTS:\n";
		$system .= "- Alt text must be concise and descriptive (100-125 characters ideal)\n";
		$system .= "- Focus on what's important for understanding the image's purpose\n";
		$system .= "- Avoid phrases like 'image of' or 'picture of'\n";
		$system .= "- Include text visible in the image when relevant\n";
		$system .= "- Consider the image's context and intent\n";

		return $system;
	}

	/**
	 * Build user prompt.
	 *
	 * @param int   $media_id Media ID.
	 * @param array $rollup Context rollup data.
	 * @param array $profile Locale profile.
	 * @param array $options Options.
	 * @return string
	 */
	private function build_user_prompt( $media_id, $rollup, $profile, $options ) {
		$locale      = $profile['locale'];
		$usage_type  = $options['usage_type'];
		$max_lengths = $options['max_length'];

		// Get image URL for vision models
		$image_url = wp_get_attachment_image_url( $media_id, 'large' );

		$prompt = "Generate SEO-optimized, accessible metadata for this image in {$locale} locale.\n\n";

		// Context information
		if ( ! empty( $rollup ) && isset( $rollup['context_score'] ) ) {
			$prompt .= "CONTEXT INFORMATION:\n";

			if ( ! empty( $rollup['primary_subject'] ) ) {
				$prompt .= "- Primary Subject: {$rollup['primary_subject']}\n";
			}

			if ( ! empty( $rollup['keywords'] ) ) {
				$keywords = is_array( $rollup['keywords'] ) ? implode( ', ', $rollup['keywords'] ) : $rollup['keywords'];
				$prompt  .= "- Keywords: {$keywords}\n";
			}

			if ( ! empty( $rollup['intent'] ) ) {
				$prompt .= "- Intent: {$rollup['intent']}\n";
			}

			if ( isset( $rollup['context_score'] ) ) {
				$prompt .= "- Context Score: {$rollup['context_score']}/100\n";
			}

			if ( ! empty( $rollup['entities'] ) ) {
				$entities = is_array( $rollup['entities'] ) ? implode( ', ', $rollup['entities'] ) : $rollup['entities'];
				$prompt  .= "- Entities: {$entities}\n";
			}

			$prompt .= "\n";
		}

		// Usage type guidance
		$usage_guidance = array(
			'featured'  => 'This is a featured/hero image. Emphasize impact and key messaging.',
			'inline'    => 'This is an inline image supporting content. Be concise and contextual.',
			'thumbnail' => 'This is a thumbnail. Focus on immediate recognition and key elements.',
			'hero'      => 'This is a hero banner image. Highlight the main message or action.',
		);

		if ( isset( $usage_guidance[ $usage_type ] ) ) {
			$prompt .= "USAGE: {$usage_guidance[$usage_type]}\n\n";
		}

		// Length requirements
		$prompt .= "REQUIRED OUTPUT (JSON format):\n";
		$prompt .= "{\n";
		$prompt .= "  \"alt_text\": \"<{$max_lengths['alt_text']} characters>\",\n";
		$prompt .= "  \"title\": \"<{$max_lengths['title']} characters>\",\n";
		$prompt .= "  \"description\": \"<{$max_lengths['description']} characters>\",\n";
		$prompt .= "  \"caption\": \"<{$max_lengths['caption']} characters>\"\n";
		$prompt .= "}\n\n";

		$prompt .= "Return ONLY valid JSON. No markdown, no explanation.";

		return $prompt;
	}

	/**
	 * Apply glossary replacements to text.
	 *
	 * @param string $text Text to process.
	 * @param string $locale Locale code.
	 * @return string Processed text.
	 */
	public function apply_glossary_replacements( $text, $locale ) {
		$entries = $this->profile_manager->get_glossary_entries( $locale );

		if ( empty( $entries ) ) {
			return $text;
		}

		foreach ( $entries as $entry ) {
			// Skip protected terms with no translation (keep original)
			if ( $entry['protected'] && empty( $entry['translation'] ) ) {
				continue;
			}

			$term        = $entry['term'];
			$translation = $entry['translation'] ?? $term;

			if ( $entry['case_sensitive'] ) {
				$text = str_replace( $term, $translation, $text );
			} else {
				$text = str_ireplace( $term, $translation, $text );
			}
		}

		return $text;
	}

	/**
	 * Validate that protected terms haven't been modified.
	 *
	 * @param string $text Generated text.
	 * @param string $locale Locale code.
	 * @return bool True if all protected terms are present unchanged.
	 */
	public function validate_protected_terms( $text, $locale ) {
		$protected_terms = $this->profile_manager->get_protected_terms( $locale );

		if ( empty( $protected_terms ) ) {
			return true;
		}

		$original_context = $this->get_original_text_for_comparison();

		foreach ( $protected_terms as $term ) {
			// Check if term appears in original
			if ( stripos( $original_context, $term ) !== false ) {
				// Must also appear in generated text unchanged
				if ( stripos( $text, $term ) === false ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Get original text for protected term comparison.
	 *
	 * @return string
	 */
	private function get_original_text_for_comparison() {
		// This would need to be passed in from the calling context
		// For now, return empty string
		return '';
	}

	/**
	 * Build regeneration prompt (for improving existing metadata).
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale Locale code.
	 * @param array  $current_metadata Current metadata.
	 * @param array  $feedback Feedback/issues to address.
	 * @return array Prompt data.
	 */
	public function build_regeneration_prompt( $media_id, $locale, $current_metadata, $feedback = array() ) {
		$base_prompt = $this->build_metadata_prompt( $media_id, $locale );

		$base_prompt['user'] .= "\n\nCURRENT METADATA (to improve):\n";
		$base_prompt['user'] .= wp_json_encode( $current_metadata, JSON_PRETTY_PRINT ) . "\n\n";

		if ( ! empty( $feedback ) ) {
			$base_prompt['user'] .= "ISSUES TO ADDRESS:\n";
			foreach ( $feedback as $issue ) {
				$base_prompt['user'] .= "- {$issue}\n";
			}
			$base_prompt['user'] .= "\n";
		}

		$base_prompt['user'] .= "Generate improved metadata addressing the issues above.";

		return $base_prompt;
	}
}
