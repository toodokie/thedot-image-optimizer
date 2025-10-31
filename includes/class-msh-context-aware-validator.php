<?php
/**
 * Context-aware metadata validator for AI outputs.
 *
 * Applies business rules after AI generation: strips forbidden branding,
 * flags generic text, and guards against duplicate metadata.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Context_Aware_Validator {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Context_Aware_Validator|null
	 */
	private static $instance = null;

	/**
	 * Recently processed metadata snippets for duplicate detection.
	 *
	 * @var array
	 */
	private $recent_metadata = array();

	/**
	 * Maximum number of items to retain for duplicate detection.
	 *
	 * @var int
	 */
	private $history_limit = 50;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Context_Aware_Validator
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Validate and normalise AI metadata output.
	 *
	 * @param array $context  Finalised context array.
	 * @param array $metadata Sanitised metadata from the AI connector.
	 * @return array Validated metadata array.
	 */
	public function validate( array $context, array $metadata ) {
		if ( empty( $metadata['issues'] ) || ! is_array( $metadata['issues'] ) ) {
			$metadata['issues'] = array();
		}

		$this->enforce_context_rules( $context, $metadata );
		$this->enforce_specificity( $metadata );
		$this->enforce_uniqueness( $metadata );
		$this->finalise_metadata( $context, $metadata );

		return $metadata;
	}

	/**
	 * Enforce context-specific branding rules.
	 *
	 * @param array $context  Context array.
	 * @param array $metadata Metadata array (passed by reference).
	 */
	private function enforce_context_rules( array $context, array &$metadata ) {
		$business_name = isset( $context['business_name'] ) ? trim( (string) $context['business_name'] ) : '';
		if ( $business_name === '' ) {
			return;
		}

		$final_type   = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );
		$brand_fields = array( 'file_name_suggestion', 'title', 'alt_text', 'caption', 'description' );
		$brand_found  = false;

		foreach ( $brand_fields as $field ) {
			if ( ! empty( $metadata[ $field ] ) && stripos( $metadata[ $field ], $business_name ) !== false ) {
				$brand_found = true;
				break;
			}
		}

		if ( ! $brand_found ) {
			return;
		}

		$brand_allowed = ! empty( $context['brand_name_visible'] );

		if ( in_array( $final_type, array( 'brand_logo', 'team', 'facility', 'equipment' ), true ) ) {
			return; // Always permitted.
		}

		$forbidden = false;

		if ( in_array( $final_type, array( 'stock', 'decorative' ), true ) ) {
			$forbidden = true;
		} elseif ( in_array( $final_type, array( 'clinical', 'business', 'service-icon', 'testimonial' ), true ) && ! $brand_allowed ) {
			$forbidden = true;
		}

		if ( $forbidden ) {
			foreach ( $brand_fields as $field ) {
				if ( empty( $metadata[ $field ] ) ) {
					continue;
				}

				$metadata[ $field ] = $this->strip_business_name( $metadata[ $field ], $business_name );
			}

			$this->add_issue( $metadata, 'brand_name_assumed' );
			$this->add_issue( $metadata, 'context_mismatch' );
			$metadata['confidence'] = $this->cap_confidence( $metadata, 0.7 );

			$attachment_id = isset( $context['attachment_id'] ) ? (int) $context['attachment_id'] : 0;
			do_action( 'msh_validator_brand_stripped', $attachment_id, $context, $metadata );
		}

		if ( $final_type === 'testimonial' ) {
			$phrases = array(
				'at ' . strtolower( $business_name ),
				'in our facility',
				$business_name . ' client',
				'our clinic',
				'our office',
			);
			$combined = strtolower( implode( ' ', array(
				$metadata['title'] ?? '',
				$metadata['alt_text'] ?? '',
				$metadata['caption'] ?? '',
				$metadata['description'] ?? '',
			) ) );

			foreach ( $phrases as $phrase ) {
				if ( strpos( $combined, $phrase ) !== false ) {
					$this->add_issue( $metadata, 'context_mismatch' );
					$metadata['confidence'] = $this->cap_confidence( $metadata, 0.65 );
					break;
				}
			}
		}
	}

	/**
	 * Remove business name from a string and tidy whitespace.
	 *
	 * @param string $value         Text value.
	 * @param string $business_name Business name.
	 * @return string Cleaned value.
	 */
	private function strip_business_name( $value, $business_name ) {
		$pattern = '/\\b' . preg_quote( $business_name, '/' ) . '\\b/i';
		$value   = preg_replace( $pattern, '', $value );
		$value   = preg_replace( '/\\s{2,}/', ' ', $value );

		return trim( $value );
	}

	/**
	 * Flag generic phrases and enforce specificity.
	 *
	 * @param array $metadata Metadata array (passed by reference).
	 */
	private function enforce_specificity( array &$metadata ) {
		$banned_pattern = '/(brand imagery|generic image|stock photo|placeholder)/i';
		$fields         = array( 'title', 'alt_text', 'caption', 'description' );

		foreach ( $fields as $field ) {
			$value = isset( $metadata[ $field ] ) ? $metadata[ $field ] : '';
			if ( $value === '' ) {
				continue;
			}

			if ( preg_match( $banned_pattern, $value ) ) {
				$this->add_issue( $metadata, 'too_generic' );
				$metadata['confidence'] = $this->cap_confidence( $metadata, 0.7 );
			}
		}
	}

	/**
	 * Detect duplicate metadata within recent history.
	 *
	 * @param array $metadata Metadata array (passed by reference).
	 */
	private function enforce_uniqueness( array &$metadata ) {
		$title = strtolower( (string) ( $metadata['title'] ?? '' ) );
		$alt   = strtolower( (string) ( $metadata['alt_text'] ?? '' ) );

		foreach ( $this->recent_metadata as $recent ) {
			$title_sim = $this->jaccard_similarity( $title, $recent['title'] );
			$alt_sim   = $this->jaccard_similarity( $alt, $recent['alt_text'] );

			if ( $title_sim >= 0.65 && $alt_sim >= 0.65 ) {
				$this->add_issue( $metadata, 'duplicate_metadata' );
				$metadata['confidence'] = $this->cap_confidence( $metadata, 0.6 );
				break;
			}
		}

		$this->recent_metadata[] = array(
			'title'    => $title,
			'alt_text' => $alt,
		);

		if ( count( $this->recent_metadata ) > $this->history_limit ) {
			$this->recent_metadata = array_slice( $this->recent_metadata, -1 * $this->history_limit );
		}
	}

	/**
	 * Final tidy and review flagging.
	 *
	 * @param array $context  Context array.
	 * @param array $metadata Metadata array (passed by reference).
	 */
	private function finalise_metadata( array $context, array &$metadata ) {
		$final_type = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );
		if ( $final_type === 'decorative' ) {
			$metadata['title']    = '';
			$metadata['alt_text'] = '';
			$this->add_issue( $metadata, 'decorative_image' );
		}

		foreach ( array( 'title', 'alt_text', 'caption', 'description' ) as $field ) {
			if ( isset( $metadata[ $field ] ) ) {
				$metadata[ $field ] = trim( preg_replace( '/\\s{2,}/', ' ', $metadata[ $field ] ) );
			}
		}

		if ( ! empty( $metadata['issues'] ) ) {
			$metadata['issues'] = array_values( array_unique( $metadata['issues'] ) );
		}

		if ( $final_type !== 'decorative' && ! empty( $metadata['alt_text'] ) && is_string( $metadata['alt_text'] ) ) {
			$alt_len = mb_strlen( $metadata['alt_text'] );
			if ( $alt_len < 8 || $alt_len > 160 ) {
				$this->add_issue( $metadata, 'alt_text_length' );
				$metadata['confidence'] = $this->cap_confidence( $metadata, 0.75 );
			}
		}

		if ( $final_type !== 'decorative' && ! empty( $metadata['title'] ) && is_string( $metadata['title'] ) ) {
			$title_len = mb_strlen( $metadata['title'] );
			if ( $title_len < 12 || $title_len > 75 ) {
				$this->add_issue( $metadata, 'title_length' );
				$metadata['confidence'] = $this->cap_confidence( $metadata, 0.75 );
			}
		}

		$confidence = isset( $metadata['confidence'] ) ? (float) $metadata['confidence'] : 0.0;
		if ( $confidence < 0 ) {
			$confidence = 0.0;
		} elseif ( $confidence > 1 ) {
			$confidence = 1.0;
		}
		$metadata['confidence'] = $confidence;

		$needs_review = array_intersect(
			array( 'context_mismatch', 'duplicate_metadata', 'brand_name_assumed' ),
			$metadata['issues']
		);
		if ( ! empty( $needs_review ) ) {
			$metadata['needs_review'] = true;
		}
	}

	/**
	 * Add issue to metadata array if not already present.
	 *
	 * @param array  $metadata Metadata array.
	 * @param string $issue    Issue slug.
	 */
	private function add_issue( array &$metadata, $issue ) {
		if ( empty( $metadata['issues'] ) || ! is_array( $metadata['issues'] ) ) {
			$metadata['issues'] = array();
		}

		if ( ! in_array( $issue, $metadata['issues'], true ) ) {
			$metadata['issues'][] = $issue;
		}
	}

	/**
	 * Lower confidence with a ceiling.
	 *
	 * @param array $metadata Metadata array.
	 * @param float $cap      Maximum allowed confidence.
	 * @return float Adjusted confidence value.
	 */
	private function cap_confidence( array $metadata, $cap ) {
		$current = isset( $metadata['confidence'] ) ? (float) $metadata['confidence'] : 0.9;
		return min( $current, (float) $cap );
	}

	/**
	 * Calculate Jaccard similarity between two strings using 3-grams.
	 *
	 * @param string $a Text A.
	 * @param string $b Text B.
	 * @return float Similarity score 0.0–1.0.
	 */
	private function jaccard_similarity( $a, $b ) {
		$a = trim( $a );
		$b = trim( $b );

		if ( $a === '' || $b === '' ) {
			return 0.0;
		}

		$ngrams_a = $this->generate_ngrams( $a, 3 );
		$ngrams_b = $this->generate_ngrams( $b, 3 );

		if ( empty( $ngrams_a ) || empty( $ngrams_b ) ) {
			return 0.0;
		}

		$intersection = array_intersect( $ngrams_a, $ngrams_b );
		$union        = array_unique( array_merge( $ngrams_a, $ngrams_b ) );

		if ( empty( $union ) ) {
			return 0.0;
		}

		return count( $intersection ) / count( $union );
	}

	/**
	 * Generate n-grams for a string.
	 *
	 * @param string $text Input text.
	 * @param int    $n    Gram length.
	 * @return array Array of unique n-grams.
	 */
	private function generate_ngrams( $text, $n = 3 ) {
		$text = preg_replace( '/\\s+/', ' ', $text );
		$text = trim( $text );

		$length = mb_strlen( $text );
		if ( $length < $n ) {
			return array( $text );
		}

		$ngrams = array();
		for ( $i = 0; $i <= $length - $n; $i++ ) {
			$ngrams[] = mb_substr( $text, $i, $n );
		}

		return array_values( array_unique( $ngrams ) );
	}
}
