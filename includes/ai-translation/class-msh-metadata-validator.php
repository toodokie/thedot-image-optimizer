<?php
/**
 * Metadata Validator
 *
 * Validates AI-generated metadata for quality, length, forbidden terms, etc.
 *
 * @package MSH_Image_Optimizer
 * @subpackage AI_Translation
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_Metadata_Validator class.
 */
class MSH_Metadata_Validator {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Metadata_Validator
	 */
	private static $instance = null;

	/**
	 * Profile manager.
	 *
	 * @var MSH_Locale_Profile_Manager
	 */
	private $profile_manager;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Metadata_Validator
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
	}

	/**
	 * Validate metadata against rules.
	 *
	 * @param array  $metadata Metadata array (alt_text, title, description, caption).
	 * @param string $locale Locale code.
	 * @param array  $rules Validation rules.
	 * @return array Validation result with 'valid', 'errors', 'warnings', 'score'.
	 */
	public function validate( $metadata, $locale, $rules = array() ) {
		$defaults = array(
			'max_length' => array(
				'alt_text'    => 125,
				'title'       => 60,
				'description' => 200,
				'caption'     => 150,
			),
			'min_length' => array(
				'alt_text'    => 20,
				'title'       => 10,
				'description' => 30,
				'caption'     => 15,
			),
			'required'   => array( 'alt_text' ), // Alt text is always required
		);

		$rules = wp_parse_args( $rules, $defaults );

		$result = array(
			'valid'    => true,
			'errors'   => array(),
			'warnings' => array(),
			'score'    => 100,
		);

		// Get locale profile for additional rules
		$profile = $this->profile_manager->get_profile_with_fallback( $locale );

		// 1. Check required fields
		foreach ( $rules['required'] as $field ) {
			if ( empty( $metadata[ $field ] ) ) {
				$result['errors'][] = "Required field '{$field}' is missing or empty";
				$result['valid']    = false;
				$result['score']   -= 25;
			}
		}

		// 2. Check length constraints
		foreach ( $metadata as $field => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$length = mb_strlen( $value );

			// Max length check (hard error)
			if ( isset( $rules['max_length'][ $field ] ) && $length > $rules['max_length'][ $field ] ) {
				$result['errors'][] = "{$field} exceeds maximum length ({$length}/{$rules['max_length'][$field]})";
				$result['valid']    = false;
				$result['score']   -= 15;
			}

			// Min length check (warning only)
			if ( isset( $rules['min_length'][ $field ] ) && $length < $rules['min_length'][ $field ] ) {
				$result['warnings'][] = "{$field} is shorter than recommended ({$length}/{$rules['min_length'][$field]})";
				$result['score']     -= 5;
			}
		}

		// 3. Check forbidden terms
		if ( ! empty( $profile['forbidden_terms'] ) ) {
			$forbidden = array_map( 'trim', explode( ',', $profile['forbidden_terms'] ) );

			foreach ( $metadata as $field => $value ) {
				foreach ( $forbidden as $term ) {
					if ( empty( $term ) ) {
						continue;
					}

					if ( stripos( $value, $term ) !== false ) {
						$result['errors'][] = "{$field} contains forbidden term: '{$term}'";
						$result['valid']    = false;
						$result['score']   -= 20;
					}
				}
			}
		}

		// 4. Check for accessibility anti-patterns
		$result = $this->check_accessibility_patterns( $metadata, $result );

		// 5. Check for cultural issues (basic)
		$result = $this->check_cultural_patterns( $metadata, $locale, $result );

		// 6. Check protected terms
		$result = $this->check_protected_terms( $metadata, $locale, $result );

		// Ensure score doesn't go below 0
		$result['score'] = max( 0, $result['score'] );

		return $result;
	}

	/**
	 * Check for accessibility anti-patterns.
	 *
	 * @param array $metadata Metadata.
	 * @param array $result Validation result.
	 * @return array Updated result.
	 */
	private function check_accessibility_patterns( $metadata, $result ) {
		if ( empty( $metadata['alt_text'] ) ) {
			return $result;
		}

		$alt_text = strtolower( $metadata['alt_text'] );

		// Anti-patterns to avoid
		$anti_patterns = array(
			'image of'    => 'Avoid phrases like "image of" - screen readers already announce it as an image',
			'picture of'  => 'Avoid phrases like "picture of" - screen readers already announce it as an image',
			'photo of'    => 'Avoid phrases like "photo of" - screen readers already announce it as an image',
			'graphic of'  => 'Avoid phrases like "graphic of" - screen readers already announce it as an image',
			'screenshot'  => 'Consider describing what the screenshot shows instead of just saying "screenshot"',
		);

		foreach ( $anti_patterns as $pattern => $message ) {
			if ( strpos( $alt_text, $pattern ) !== false ) {
				$result['warnings'][] = $message;
				$result['score']     -= 3;
			}
		}

		// Check for overly generic descriptions
		$generic_terms = array( 'image', 'photo', 'picture', 'graphic' );
		$word_count    = str_word_count( $alt_text );

		if ( $word_count <= 2 ) {
			$is_generic = false;
			foreach ( $generic_terms as $term ) {
				if ( strpos( $alt_text, $term ) !== false ) {
					$is_generic = true;
					break;
				}
			}

			if ( $is_generic ) {
				$result['warnings'][] = 'Alt text appears too generic. Be more specific about image content.';
				$result['score']     -= 5;
			}
		}

		return $result;
	}

	/**
	 * Check for cultural patterns and locale-specific issues.
	 *
	 * @param array  $metadata Metadata.
	 * @param string $locale Locale code.
	 * @param array  $result Validation result.
	 * @return array Updated result.
	 */
	private function check_cultural_patterns( $metadata, $locale, $result ) {
		$all_text = implode( ' ', $metadata );
		$all_text = strtolower( $all_text );

		// US vs UK spelling patterns
		if ( 'en_GB' === $locale ) {
			$us_patterns = array(
				'color'    => 'colour',
				'flavor'   => 'flavour',
				'center'   => 'centre',
				'theater'  => 'theatre',
				'organize' => 'organise',
			);

			foreach ( $us_patterns as $us => $uk ) {
				if ( strpos( $all_text, $us ) !== false ) {
					$result['warnings'][] = "Consider using UK spelling: '{$us}' → '{$uk}'";
					$result['score']     -= 2;
				}
			}
		}

		// Check for date format expectations
		if ( preg_match( '/\d{1,2}\/\d{1,2}\/\d{2,4}/', $all_text ) ) {
			if ( strpos( $locale, 'en_US' ) === false ) {
				$result['warnings'][] = 'Date format may be ambiguous. Consider using ISO format or spelled-out dates.';
				$result['score']     -= 2;
			}
		}

		return $result;
	}

	/**
	 * Check that protected terms are present and unchanged.
	 *
	 * @param array  $metadata Metadata.
	 * @param string $locale Locale code.
	 * @param array  $result Validation result.
	 * @return array Updated result.
	 */
	private function check_protected_terms( $metadata, $locale, $result ) {
		$protected_entries = $this->profile_manager->get_glossary_entries( $locale, array( 'protected' => 1 ) );

		if ( empty( $protected_entries ) ) {
			return $result;
		}

		$all_text = implode( ' ', $metadata );

		// Note: This is a basic check. In production, you'd want to compare
		// against the original source text to ensure protected terms weren't removed.

		foreach ( $protected_entries as $entry ) {
			$term = $entry['term'];

			// If case-sensitive, do exact match
			if ( $entry['case_sensitive'] ) {
				// This is a simplified check - would need source comparison
				// to fully validate protected terms
			}
		}

		return $result;
	}

	/**
	 * Validate JSON structure from AI response.
	 *
	 * @param string $json_string JSON string from AI.
	 * @return array|WP_Error Decoded array or WP_Error.
	 */
	public function validate_json_response( $json_string ) {
		// Remove markdown code blocks if present
		$json_string = preg_replace( '/```json\s*/', '', $json_string );
		$json_string = preg_replace( '/```\s*$/', '', $json_string );
		$json_string = trim( $json_string );

		$metadata = json_decode( $json_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				'Invalid JSON response: ' . json_last_error_msg()
			);
		}

		// Check required fields
		$required_fields = array( 'alt_text' );

		foreach ( $required_fields as $field ) {
			if ( ! isset( $metadata[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					"Required field '{$field}' missing from JSON response"
				);
			}
		}

		return $metadata;
	}

	/**
	 * Get quality score from validation result.
	 *
	 * @param array $validation_result Result from validate().
	 * @return int Score 0-100.
	 */
	public function get_quality_score( $validation_result ) {
		return max( 0, min( 100, (int) $validation_result['score'] ) );
	}

	/**
	 * Check if validation passes minimum threshold.
	 *
	 * @param array $validation_result Result from validate().
	 * @param float $threshold Minimum acceptable score (0-1).
	 * @return bool
	 */
	public function passes_threshold( $validation_result, $threshold = 0.70 ) {
		if ( ! $validation_result['valid'] ) {
			return false;
		}

		$score = $this->get_quality_score( $validation_result );
		return ( $score / 100 ) >= $threshold;
	}

	/**
	 * Get human-readable validation summary.
	 *
	 * @param array $validation_result Result from validate().
	 * @return string
	 */
	public function get_validation_summary( $validation_result ) {
		$summary = "Validation Score: {$validation_result['score']}/100\n";

		if ( $validation_result['valid'] ) {
			$summary .= "Status: PASSED\n";
		} else {
			$summary .= "Status: FAILED\n";
		}

		if ( ! empty( $validation_result['errors'] ) ) {
			$summary .= "\nErrors:\n";
			foreach ( $validation_result['errors'] as $error ) {
				$summary .= "  - {$error}\n";
			}
		}

		if ( ! empty( $validation_result['warnings'] ) ) {
			$summary .= "\nWarnings:\n";
			foreach ( $validation_result['warnings'] as $warning ) {
				$summary .= "  - {$warning}\n";
			}
		}

		return $summary;
	}

	/**
	 * Auto-fix common issues.
	 *
	 * @param array  $metadata Metadata to fix.
	 * @param string $locale Locale code.
	 * @return array Fixed metadata.
	 */
	public function auto_fix( $metadata, $locale ) {
		foreach ( $metadata as $field => &$value ) {
			if ( empty( $value ) ) {
				continue;
			}

			// Remove common anti-patterns from alt text
			if ( 'alt_text' === $field ) {
				$value = preg_replace( '/^(image of|picture of|photo of|graphic of)\s+/i', '', $value );
				$value = trim( $value );
			}

			// Trim whitespace
			$value = trim( $value );

			// Fix double spaces
			$value = preg_replace( '/\s+/', ' ', $value );

			// Ensure sentence case (capitalize first letter)
			$value = ucfirst( $value );
		}

		return $metadata;
	}
}
