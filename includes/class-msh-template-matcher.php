<?php
/**
 * Template Matcher - Core matching logic for TinyDot Template Intelligence
 *
 * Matches context against templates using token-based matching with
 * negative filters, variable resolution, and performance optimization.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Template_Matcher {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Template manager instance
	 */
	private $manager;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->manager = MSH_Template_Manager::get_instance();
	}

	/**
	 * Find matching template for context
	 *
	 * @param array $context Context from Phase 2 extraction.
	 * @return array|null Matched template or null.
	 */
	public function find_match( $context ) {
		$start_time = microtime( true );

		// Extract matching parameters
		$locale = $context['locale'] ?? 'en';
		$usage_type = $context['usage_type'] ?? 'featured';
		$intent = $context['intent'] ?? 'on_topic';

		// Normalize context keywords for matching
		$context['_keywords_normalized'] = $this->normalize_tokens( $context['keywords'] ?? array() );

		// Get active templates (cached)
		$templates = $this->manager->get_active_templates( $locale, $usage_type, $intent );

		// Try to find match
		$match = null;
		foreach ( $templates as $template ) {
			// Prepare template (decode JSON, normalize tokens)
			$template = $this->prepare_template( $template );

			// Check negative tokens FIRST (fast reject)
			if ( ! $this->check_negative_tokens( $template, $context ) ) {
				continue; // Has negative token, skip
			}

			// Check required tokens (100% match required)
			if ( $this->check_required_tokens( $template, $context ) ) {
				$match = $template;
				break; // First match wins (priority sorted)
			}
		}

		// Calculate duration
		$duration_ms = ( microtime( true ) - $start_time ) * 1000;

		// Log telemetry
		if ( $match ) {
			msh_telemetry( 'template_hit', array(
				'template_id'    => $match['id'],
				'locale'         => $locale,
				'usage_type'     => $usage_type,
				'tokens_checked' => count( $context['_keywords_normalized'] ),
				'duration_ms'    => round( $duration_ms, 2 ),
			) );
		} else {
			msh_telemetry( 'template_miss', array(
				'locale'         => $locale,
				'usage_type'     => $usage_type,
				'top_3_keywords' => array_slice( $context['keywords'] ?? array(), 0, 3 ),
				'duration_ms'    => round( $duration_ms, 2 ),
			) );

			// Run shadow evaluation (don't apply, just log)
			$this->evaluate_shadow_templates( $locale, $usage_type, $intent, $context );
		}

		// Record evaluation in monitor (for auto-disable checks)
		if ( class_exists( 'MSH_Template_Monitor' ) ) {
			$monitor = MSH_Template_Monitor::get_instance();
			$monitor->record_evaluation(
				$duration_ms,
				! empty( $match ),
				! empty( $match ) ? $match['id'] : null
			);
		}

		// Timeout check (bail if > 2ms)
		if ( $duration_ms > 2 ) {
			// Log slow matching for monitoring
			msh_telemetry( 'template_slow', array(
				'duration_ms' => round( $duration_ms, 2 ),
				'template_count' => count( $templates ),
			) );
		}

		return $match;
	}

	/**
	 * Apply template to context (resolve variables, sanitize)
	 *
	 * @param array $template Template data.
	 * @param array $context  Context data.
	 * @return array Metadata fields.
	 */
	public function apply_template( $template, $context ) {
		$start_time = microtime( true );

		// Resolve variables in each field
		$fields = array(
			'title'       => $this->resolve_variables( $template['template_title'], $context ),
			'alt'         => $this->resolve_variables( $template['template_alt'], $context ),
			'caption'     => $this->resolve_variables( $template['template_caption'], $context ),
			'description' => $this->resolve_variables( $template['template_description'], $context ),
		);

		// Sanitize fields
		$max_len = json_decode( $template['max_len'], true ) ?? array();
		$truncations = array();

		foreach ( $fields as $field_name => $value ) {
			if ( ! empty( $value ) ) {
				$original_length = mb_strlen( $value );
				$fields[ $field_name ] = $this->sanitize_field( $field_name, $value, $max_len );
				$new_length = mb_strlen( $fields[ $field_name ] );

				if ( $new_length < $original_length ) {
					$truncations[ $field_name ] = $original_length - $new_length;
				}
			}
		}

		// Add metadata
		$fields['source'] = 'template';
		$fields['template_id'] = $template['id'];

		// Log application
		msh_telemetry( 'template_applied', array(
			'template_id' => $template['id'],
			'fields'      => array_keys( array_filter( $fields ) ),
			'truncations' => $truncations,
			'duration_ms' => round( ( microtime( true ) - $start_time ) * 1000, 2 ),
		) );

		return $fields;
	}

	/**
	 * Check if templates should be used (feature flag)
	 *
	 * @return bool True if enabled.
	 */
	public function should_use_templates() {
		return class_exists( 'MSH_Feature_Flags' )
			&& MSH_Feature_Flags::evaluate( 'template_intelligence' );
	}

	/**
	 * Prepare template (decode JSON, normalize tokens)
	 *
	 * @param array $template Raw template from database.
	 * @return array Prepared template.
	 */
	private function prepare_template( $template ) {
		// Decode JSON columns
		$template['_required_tokens'] = json_decode( $template['required_tokens'], true ) ?? array();
		$template['_negative_tokens'] = json_decode( $template['negative_tokens'], true ) ?? array();
		$template['_nice_to_have'] = json_decode( $template['nice_to_have_tokens'], true ) ?? array();
		$template['_variables'] = json_decode( $template['variables'], true ) ?? array();

		// Normalize token sets for matching
		$template['_required_set'] = $this->normalize_tokens( $template['_required_tokens'] );
		$template['_negative_set'] = $this->normalize_tokens( $template['_negative_tokens'] );

		return $template;
	}

	/**
	 * Check negative tokens (fast reject)
	 *
	 * @param array $template Prepared template.
	 * @param array $context  Context data.
	 * @return bool True if passed (no negative tokens found).
	 */
	private function check_negative_tokens( $template, $context ) {
		if ( empty( $template['_negative_set'] ) ) {
			return true; // No negatives = pass
		}

		// Check if ANY negative token present in context
		$intersection = array_intersect( $template['_negative_set'], $context['_keywords_normalized'] );

		return empty( $intersection ); // Pass if no negatives found
	}

	/**
	 * Check required tokens (100% match)
	 *
	 * @param array $template Prepared template.
	 * @param array $context  Context data.
	 * @return bool True if all required tokens present.
	 */
	private function check_required_tokens( $template, $context ) {
		if ( empty( $template['_required_set'] ) ) {
			return false; // No requirements = shouldn't match
		}

		// Check if ALL required tokens present
		$intersection = array_intersect( $template['_required_set'], $context['_keywords_normalized'] );

		return count( $intersection ) === count( $template['_required_set'] );
	}

	/**
	 * Normalize tokens for matching (lowercase, trim)
	 *
	 * @param array $tokens Raw tokens.
	 * @return array Normalized tokens.
	 */
	private function normalize_tokens( $tokens ) {
		if ( empty( $tokens ) || ! is_array( $tokens ) ) {
			return array();
		}

		return array_map( function( $token ) {
			// Lowercase for comparison
			$token = mb_strtolower( $token, 'UTF-8' );

			// Trim whitespace
			$token = trim( $token );

			return $token;
		}, $tokens );
	}

	/**
	 * Resolve variables in template string
	 *
	 * @param string $template Template string with {variables}.
	 * @param array  $context  Context data.
	 * @return string Resolved string.
	 */
	private function resolve_variables( $template, $context ) {
		if ( empty( $template ) ) {
			return '';
		}

		// Find all variables
		preg_match_all( '/\{(\w+)\}/', $template, $matches );

		foreach ( $matches[1] as $var_name ) {
			$value = $this->get_variable_value( $var_name, $context );
			$template = str_replace( '{' . $var_name . '}', $value, $template );
		}

		// Collapse whitespace
		$template = preg_replace( '/\s+/', ' ', $template );

		return trim( $template );
	}

	/**
	 * Get variable value with fallback chain
	 *
	 * @param string $var_name Variable name.
	 * @param array  $context  Context data.
	 * @return string Variable value or empty string.
	 */
	private function get_variable_value( $var_name, $context ) {
		switch ( $var_name ) {
			case 'entity':
				// Try entity first
				if ( ! empty( $context['entities'][0] ) ) {
					return $context['entities'][0];
				}
				// Fall back to post_title
				if ( ! empty( $context['post_title'] ) ) {
					return $context['post_title'];
				}
				return '';

			case 'subject':
				return $context['subject'] ?? '';

			case 'post_title':
				return $context['post_title'] ?? '';

			default:
				return ''; // Unknown variable
		}
	}

	/**
	 * Sanitize field according to SEO/a11y rules
	 *
	 * @param string $field_name Field name.
	 * @param string $value      Field value.
	 * @param array  $max_len    Max length config.
	 * @return string Sanitized value.
	 */
	private function sanitize_field( $field_name, $value, $max_len = array() ) {
		// Strip HTML
		$value = wp_strip_all_tags( $value );

		// Field-specific rules
		switch ( $field_name ) {
			case 'alt':
				// Max 125 chars, no pipes, no trailing period
				$value = str_replace( '|', '-', $value );
				$value = rtrim( $value, '.' );
				$max = $max_len['alt'] ?? 125;
				break;

			case 'title':
				// Max 60 chars, respect proper nouns (NO forced casing)
				$max = $max_len['title'] ?? 60;
				break;

			case 'caption':
				// Sentence case, period allowed
				$value = ucfirst( $value );
				$max = $max_len['caption'] ?? 300;
				break;

			case 'description':
				$max = $max_len['description'] ?? 500;
				break;

			default:
				$max = 300;
		}

		// Truncate at word boundary if needed
		if ( isset( $max ) && mb_strlen( $value ) > $max ) {
			$value = mb_substr( $value, 0, $max );
			// Find last space
			$last_space = mb_strrpos( $value, ' ' );
			if ( false !== $last_space ) {
				$value = mb_substr( $value, 0, $last_space );
			}
		}

		// Final cleanup
		$value = preg_replace( '/\s+/', ' ', $value );
		return trim( $value );
	}

	/**
	 * Evaluate shadow templates (log only, don't apply)
	 *
	 * @param string $locale     Locale.
	 * @param string $usage_type Usage type.
	 * @param string $intent     Intent.
	 * @param array  $context    Context data.
	 */
	private function evaluate_shadow_templates( $locale, $usage_type, $intent, $context ) {
		$shadows = $this->manager->get_shadow_templates( $locale, $usage_type, $intent );

		// Get attachment ID from context (if available)
		$attachment_id = $context['attachment_id'] ?? 0;
		$site_id = get_option( 'msh_site_id', '' );

		// Get shadow engine for precision tracking
		$shadow_engine = class_exists( 'MSH_Shadow_Engine' ) ? MSH_Shadow_Engine::get_instance() : null;

		foreach ( $shadows as $shadow ) {
			// Start timing for THIS shadow evaluation only
			$shadow_start = microtime( true );

			$shadow = $this->prepare_template( $shadow );

			// Check if would match
			$would_match = $this->check_negative_tokens( $shadow, $context )
				&& $this->check_required_tokens( $shadow, $context );

			// Calculate duration for THIS shadow evaluation only
			$shadow_duration_ms = ( microtime( true ) - $shadow_start ) * 1000;

			// Record in shadow stats table for precision tracking
			if ( $shadow_engine && $attachment_id > 0 ) {
				$shadow_engine->record_evaluation(
					$shadow['id'],
					$attachment_id,
					$would_match,
					null, // expected_match unknown (will be set by admin later)
					$shadow_duration_ms,
					$context,
					$site_id
				);
			}

			// Log telemetry (limited data for privacy)
			msh_telemetry(
				'template_shadow_evaluated',
				array(
					'template_id'  => $shadow['id'],
					'matched'      => $would_match,
					'token_count'  => count( $context['_keywords_normalized'] ),
					'duration_ms'  => round( $shadow_duration_ms, 2 ),
				)
			);
		}
	}
}
