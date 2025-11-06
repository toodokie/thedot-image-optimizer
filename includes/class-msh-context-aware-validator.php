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
	 * Allowed location modes.
	 *
	 * @var string[]
	 */
	private static $loc_mode_allowed = array( 'auto', 'force_caption', 'force_all', 'off' );

	/**
	 * Contexts that support force_all location insertion.
	 *
	 * @var string[]
	 */
	private static $loc_mode_force_all_contexts = array( 'facility', 'service-icon' );

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
	 * @param bool  $seo_mode Whether SEO mode is enabled.
	 * @param string $loc_mode_override Optional explicit loc mode.
	 * @return array Validated metadata array.
	 */
	public function validate( array $context, array $metadata, $seo_mode = true, $loc_mode_override = null ) {
		if ( empty( $metadata['issues'] ) || ! is_array( $metadata['issues'] ) ) {
			$metadata['issues'] = array();
		}

		$context  = $this->canonicalize_context_payload( $context );
		$metadata = $this->apply_shared_policy_filters( $context, $metadata );

		$context['seo_mode'] = (bool) $seo_mode;
		if ( null !== $loc_mode_override ) {
			$context['loc_mode'] = $loc_mode_override;
		}

		$this->enforce_context_rules( $context, $metadata );
		$this->enforce_location_rules( $context, $metadata );
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
		$brand_fields = array( 'file_name_suggestion', 'filename_slug', 'title', 'alt_text', 'caption', 'description' );
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
		if ( function_exists( 'msh_brand_permitted' ) ) {
			$brand_allowed = msh_brand_permitted(
				$final_type,
				array(
					'brand_name_visible' => ! empty( $context['brand_name_visible'] ),
				)
			);
		}

		$forbidden = false;

		if ( in_array( $final_type, array( 'stock', 'decorative' ), true ) ) {
			$forbidden = true;
		} elseif ( ! $brand_allowed ) {
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
	 * Enforce per-context location usage rules.
	 *
	 * @param array $context  Context array.
	 * @param array $metadata Metadata array (passed by reference).
	 */
	private function enforce_location_rules( array $context, array &$metadata ) {
		$location_terms = $this->collect_location_terms_from_context( $context );
		if ( empty( $location_terms ) ) {
			return;
		}

		$final_type = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );
		$seo_mode   = ! empty( $context['seo_mode'] );
		$raw_loc    = isset( $context['loc_mode'] ) ? $context['loc_mode'] : (
			isset( $context['policy'] ) && is_array( $context['policy'] ) && isset( $context['policy']['loc_mode'] )
				? $context['policy']['loc_mode']
				: 'auto'
		);
		$loc_mode   = $this->normalise_loc_mode( $raw_loc, $final_type );

		$filename_fields = array( 'filename_slug', 'file_name_suggestion' );
		$core_fields     = array( 'title', 'alt_text', 'caption' );
		$log_triggered   = false;

		if ( isset( $metadata['alt_text'] ) ) {
			$metadata['alt_text'] = $this->strip_terms_from_field( $metadata['alt_text'], $location_terms );
		}

		if ( ! $seo_mode || 'off' === $loc_mode ) {
			$fields       = array_merge( $core_fields, $filename_fields, array( 'description' ) );
			$log_triggered = $this->strip_terms_from_fields( $metadata, $location_terms, $fields );
			if ( isset( $metadata['description'] ) ) {
				$metadata['description'] = $this->strip_seo_tail( $metadata['description'], $location_terms );
			}
		} elseif ( in_array( $final_type, array( 'stock', 'decorative' ), true ) ) {
			$fields       = array_merge( $core_fields, $filename_fields );
			$log_triggered = $this->strip_terms_from_fields( $metadata, $location_terms, $fields );
		} elseif ( 'force_all' === $loc_mode && $this->context_supports_force_all( $final_type ) ) {
			$log_triggered = $this->strip_terms_from_fields( $metadata, $location_terms, array( 'alt_text', 'caption' ) );
		} else {
			$fields       = array_merge( $core_fields, $filename_fields );
			$log_triggered = $this->strip_terms_from_fields( $metadata, $location_terms, $fields );
		}

		if ( $log_triggered && apply_filters( 'msh_nonai_debug_logging', true ) ) {
			error_log(
				sprintf(
					'[NONAI] validate fixed=loc context=%s loc_mode=%s seo=%d',
					sanitize_key( $final_type ),
					$loc_mode,
					$seo_mode ? 1 : 0
				)
			);
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

		$location_terms = $this->collect_location_terms_from_context( $context );

		if ( isset( $metadata['title'] ) ) {
			$title_trim = trim( (string) $metadata['title'] );
			if ( $this->looks_like_filename( $title_trim ) || str_word_count( $title_trim ) < 2 ) {
				$metadata['title'] = $this->fallback_title( $context, $location_terms );
				$this->add_issue( $metadata, 'title_adjusted' );
			}
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
	 * Gather location terms from context for sanitisation.
	 *
	 * @param array $context Context array.
	 * @return array List of location strings and variants.
	 */
	private function collect_location_terms_from_context( array $context ) {
		$city_sources    = array( 'city', 'business_city' );
		$region_sources  = array( 'region', 'business_region' );
		$country_sources = array( 'country', 'business_country' );

		$city    = $this->extract_first_context_value( $context, $city_sources );
		$region  = $this->extract_first_context_value( $context, $region_sources );
		$country = $this->extract_first_context_value( $context, $country_sources );

		if ( $city === '' && $region === '' && $country === '' ) {
			return array();
		}

		$terms = array();

		foreach ( array( $city, $region, $country ) as $value ) {
			if ( $value === '' ) {
				continue;
			}
			$terms[] = $value;
			$terms[] = strtolower( $value );
			$terms[] = sanitize_title( $value );
		}

		$combinations = array();
		if ( $city !== '' && $region !== '' ) {
			$combinations[] = $city . ', ' . $region;
			$combinations[] = $city . ' ' . $region;
		}
		if ( $city !== '' && $country !== '' ) {
			$combinations[] = $city . ', ' . $country;
			$combinations[] = $city . ' ' . $country;
		}
		if ( $region !== '' && $country !== '' ) {
			$combinations[] = $region . ', ' . $country;
			$combinations[] = $region . ' ' . $country;
		}
		if ( $city !== '' && $region !== '' && $country !== '' ) {
			$combinations[] = $city . ', ' . $region . ', ' . $country;
			$combinations[] = $city . ' ' . $region . ' ' . $country;
		}

		foreach ( $combinations as $combo ) {
			$terms[] = $combo;
			$terms[] = strtolower( $combo );
			$terms[] = sanitize_title( $combo );
			$terms[] = sanitize_title( str_replace( ', ', '-', $combo ) );
		}

		return array_values( array_unique( array_filter( $terms ) ) );
	}

	/**
	 * Extract the first non-empty value from context sources.
	 *
	 * @param array $context Context array.
	 * @param array $keys    Keys to inspect.
	 * @return string Sanitised value or empty string.
	 */
	private function extract_first_context_value( array $context, array $keys ) {
		foreach ( $keys as $key ) {
			if ( ! empty( $context[ $key ] ) ) {
				return sanitize_text_field( $context[ $key ] );
			}
		}

		if ( isset( $context['biz_context'] ) && is_array( $context['biz_context'] ) ) {
			foreach ( $keys as $key ) {
				if ( ! empty( $context['biz_context'][ $key ] ) ) {
					return sanitize_text_field( $context['biz_context'][ $key ] );
				}
			}
		}

		return '';
	}

	/**
	 * Strip the given terms from specific metadata fields.
	 *
	 * @param array $metadata Metadata array (passed by reference).
	 * @param array $terms    Terms to remove.
	 * @param array $fields   Field keys to process.
	 * @return bool True if any field was modified.
	 */
	private function strip_terms_from_fields( array &$metadata, array $terms, array $fields ) {
		if ( empty( $terms ) || empty( $fields ) ) {
			return false;
		}

		$changed = false;

		foreach ( $fields as $field ) {
			if ( ! isset( $metadata[ $field ] ) ) {
				continue;
			}

			$value = $metadata[ $field ];

			if ( is_array( $value ) ) {
				$cleaned = array();
				foreach ( $value as $entry ) {
					if ( ! is_string( $entry ) ) {
						$cleaned[] = $entry;
						continue;
					}
					$sanitised = $this->strip_terms_from_value( $entry, $terms );
					if ( $sanitised !== $entry ) {
						$changed = true;
					}
					if ( '' !== $sanitised ) {
						$cleaned[] = $sanitised;
					}
				}
				$metadata[ $field ] = $cleaned;
				continue;
			}

			if ( ! is_string( $value ) || $value === '' ) {
				continue;
			}

			$sanitised = $this->strip_terms_from_value( $value, $terms );
			if ( $sanitised !== $value ) {
				if ( in_array( $field, array( 'filename_slug', 'file_name_suggestion' ), true ) ) {
					$sanitised = sanitize_title( $sanitised );
				}
				$metadata[ $field ] = $sanitised;
				$changed            = true;
			}
		}

		return $changed;
	}

	/**
	 * Strip location terms from a single string field.
	 *
	 * Wrapper that delegates to strip_terms_from_fields() to keep behaviour consistent.
	 *
	 * @param string $value The field value.
	 * @param array  $terms Terms to remove.
	 * @return string Cleaned value.
	 */
	private function strip_terms_from_field( string $value, array $terms ) {
		$meta = array( 'alt_text' => $value );
		$this->strip_terms_from_fields( $meta, $terms, array( 'alt_text' ) );
		return $meta['alt_text'];
	}

	private function strip_seo_tail( $text, array $location_terms ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return $text;
		}

		$sentences = preg_split( '/(?<=[\.!?])\s+/', trim( $text ) );
		if ( ! is_array( $sentences ) || count( $sentences ) <= 1 ) {
			return $this->strip_terms_from_value( $text, $location_terms );
		}

		$last    = array_pop( $sentences );
		$cleaned = $this->strip_terms_from_value( $last, $location_terms );

		if ( '' === $cleaned || $cleaned !== $last ) {
			$cleaned = trim( $cleaned );
			if ( $cleaned !== '' ) {
				$sentences[] = $cleaned;
			}
			return trim( implode( ' ', $sentences ) );
		}

		$sentences[] = $last;
		return trim( implode( ' ', $sentences ) );
	}

	/**
	 * Strip the given terms from a string value.
	 *
	 * @param string $value Input value.
	 * @param array  $terms Terms to remove.
	 * @return string Sanitised string.
	 */
	private function strip_terms_from_value( $value, array $terms ) {
		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( '' === $term ) {
				continue;
			}

			$pattern = '/\b' . preg_quote( $term, '/' ) . '\b/iu';
			$value   = preg_replace( $pattern, '', $value );
		}

		$value = preg_replace( '/\s+([,.;:])/u', '$1', $value );
		$value = preg_replace( '/\s{2,}/u', ' ', $value );
		$value = preg_replace( '/\s+\./u', '.', $value );

		return trim( $value );
	}

	/**
	 * Normalise incoming loc_mode values.
	 *
	 * @param string $loc_mode    Raw location mode.
	 * @param string $context_type Final context type.
	 * @return string Normalised loc_mode.
	 */
	private function normalise_loc_mode( $loc_mode, $context_type ) {
		$loc_mode = sanitize_key( (string) $loc_mode );
		if ( ! in_array( $loc_mode, self::$loc_mode_allowed, true ) ) {
			$loc_mode = 'auto';
		}

		if ( 'force_all' === $loc_mode && ! $this->context_supports_force_all( $context_type ) ) {
			return 'force_caption';
		}

		return $loc_mode;
	}

	/**
	 * Determine if the context supports force_all behaviour.
	 *
	 * @param string $context_type Context type.
	 * @return bool True when force_all is supported.
	 */
	private function context_supports_force_all( $context_type ) {
		return in_array( $context_type, self::$loc_mode_force_all_contexts, true );
	}

	/**
	 * Determine if the title resembles a filename slug.
	 *
	 * @param string $value Title value.
	 * @return bool True if title looks like a filename.
	 */
	private function looks_like_filename( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( $value === '' ) {
			return false;
		}

		if ( preg_match( '/\.(jpg|jpeg|png|webp|gif)$/', $value ) ) {
			return true;
		}

		if ( preg_match( '/[_\-][0-9]{3,}/', $value ) ) {
			return true;
		}

		if ( preg_match( '/^[a-z0-9_\-]+$/', $value ) && str_word_count( $value ) <= 2 ) {
			return true;
		}

		return false;
	}

	private function fallback_title( array $context, array $location_terms ) {
		$final_type = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );

		switch ( $final_type ) {
			case 'facility':
				$base = __( 'Facility Interior', 'msh-image-optimizer' );
				break;
			case 'team':
				$base = __( 'Care Team Portrait', 'msh-image-optimizer' );
				break;
			case 'equipment':
				$base = __( 'Therapy Equipment', 'msh-image-optimizer' );
				break;
			case 'service-icon':
				$base = __( 'Service Icon', 'msh-image-optimizer' );
				break;
			case 'clinical':
				$base = __( 'Clinical Session', 'msh-image-optimizer' );
				break;
			case 'testimonial':
				$base = __( 'Patient Testimonial', 'msh-image-optimizer' );
				break;
			case 'business':
				$base = __( 'Editorial Image', 'msh-image-optimizer' );
				break;
			default:
				$base = __( 'Editorial Image', 'msh-image-optimizer' );
				break;
		}

		if ( $this->brand_allowed_for_context( $final_type, $context ) ) {
			$brand_label = $this->clean_brand_label( $context, $location_terms );
			if ( $brand_label !== '' ) {
				return sprintf( '%s — %s', $brand_label, $base );
			}
		}

		return $base;
	}

	private function brand_allowed_for_context( $context_type, array $context ) {
		if ( in_array( $context_type, array( 'brand_logo', 'team', 'facility', 'equipment', 'service-icon' ), true ) ) {
			return true;
		}

		if ( in_array( $context_type, array( 'clinical', 'business', 'testimonial' ), true ) ) {
			return ! empty( $context['brand_name_visible'] );
		}

		return false;
	}

	private function clean_brand_label( array $context, array $location_terms ) {
		$brand = isset( $context['business_name'] ) ? $context['business_name'] : '';
		$brand = $this->strip_terms_from_value( $brand, $location_terms );
		$brand = preg_replace( '/\s{2,}/', ' ', trim( $brand, "-|, " ) );

		if ( $brand === '' && ! empty( $context['business_name'] ) ) {
			$brand = sanitize_text_field( $context['business_name'] );
		}

		return trim( $brand );
	}

	/**
	 * Canonicalize the provided context using the shared helper so both AI and deterministic
	 * flows evaluate policy against the same context type.
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	private function canonicalize_context_payload( array $context ): array {
		if ( function_exists( 'msh_canonicalize_ct' ) ) {
			$ctx_payload = array(
				'context_type'       => $context['final_context_type'] ?? ( $context['type'] ?? 'stock' ),
				'brand_name_visible' => ! empty( $context['brand_name_visible'] ),
			);

			$biz_payload = array(
				'industry' => $context['industry'] ?? ( $context['industry_label'] ?? '' ),
				'vertical' => $context['vertical'] ?? '',
			);

			$context['final_context_type'] = msh_canonicalize_ct( $ctx_payload, $biz_payload );
		}

		return $context;
	}

	/**
	 * Apply shared clamps (UVP gating, sentence limits, title length) before deeper validation.
	 *
	 * @param array $context  Context array.
	 * @param array $metadata Metadata array.
	 * @return array
	 */
	private function apply_shared_policy_filters( array $context, array $metadata ): array {
		$final_type = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );

		if ( function_exists( 'msh_limit_sentences' ) && ! empty( $metadata['description'] ) ) {
			$sentence_cap = 2;
			if ( function_exists( 'msh_ct_allows_uvp' ) && ! msh_ct_allows_uvp( $final_type ) ) {
				$sentence_cap = 1;
			}

			$metadata['description'] = msh_limit_sentences( $metadata['description'], $sentence_cap );
		}

		if ( isset( $metadata['uvp'] ) ) {
			if ( function_exists( 'msh_ct_allows_uvp' ) && ! msh_ct_allows_uvp( $final_type ) ) {
				$metadata['uvp'] = '';
			} elseif ( function_exists( 'msh_clamp_uvp' ) ) {
				$metadata['uvp'] = msh_clamp_uvp( (string) $metadata['uvp'] );
			}
		}

		if ( function_exists( 'msh_clamp_length' ) ) {
			if ( ! empty( $metadata['title'] ) ) {
				$metadata['title'] = msh_clamp_length( $metadata['title'], 60 );
			}

			if ( ! empty( $metadata['alt_text'] ) ) {
				$metadata['alt_text'] = msh_clamp_length( $metadata['alt_text'], 125, false );
			}

			if ( ! empty( $metadata['caption'] ) ) {
				$metadata['caption'] = msh_clamp_length( $metadata['caption'], 200, false );
			}
		}

		return $metadata;
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
