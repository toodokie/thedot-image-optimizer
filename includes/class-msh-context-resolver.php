<?php
/**
 * Centralised context resolver
 *
 * Applies final downgrades, brand visibility rules, and trace logging
 * before AI metadata generation runs.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Context_Resolver {

	/**
	 * Finalise the detected context and attach trace metadata.
	 *
	 * @param array $context Context array produced by detect_context().
	 * @return array Updated context with final type, brand visibility, and trace.
	 */
	public static function finalize( array $context ) {
		$manual        = ! empty( $context['context_set_manually'] );
		$auto_type     = isset( $context['type'] ) ? $context['type'] : 'stock';
		$selected_type = $manual ? ( $context['manual_value'] ?? $auto_type ) : ( $context['initial_context_type'] ?? $auto_type );
		$final_type    = sanitize_key( $selected_type ) ?: sanitize_key( $auto_type );
		if ( '' === $final_type ) {
			$final_type = 'stock';
		}
		$trace_steps   = array();

		$downgrades = isset( $context['downgraded_reasons'] ) && is_array( $context['downgraded_reasons'] )
			? array_unique( array_filter( array_map( 'sanitize_key', $context['downgraded_reasons'] ) ) )
			: array();

		foreach ( $downgrades as $reason ) {
			$trace_steps[] = 'downgrade:' . $reason;
		}

		if ( ! $manual ) {
			// Safety re-check: downgrade clinical without strong signals.
			if ( $final_type === 'clinical' && ! self::has_strong_clinical_signals( $context ) ) {
				$final_type   = 'stock';
				$downgrades[] = 'no_strong_signals';
				$trace_steps[] = 'downgrade:no_strong_signals';
			}

			// Gallery/portfolio style content should remain stock.
			if ( self::looks_like_gallery( $context ) ) {
				$final_type   = 'stock';
				$downgrades[] = 'gallery';
				if ( ! in_array( 'downgrade:gallery', $trace_steps, true ) ) {
					$trace_steps[] = 'downgrade:gallery';
				}
			}
		}

		$brand_visibility = self::compute_brand_visibility( $final_type, $context );

		$context['type']                 = $final_type;
		$context['final_context_type']   = $final_type;
		$context['brand_name_visible']   = $brand_visibility['allowed'];
		$context['brand_visibility_rule'] = $brand_visibility['source'];

		$downgrades = array_values( array_unique( $downgrades ) );

		$context['context_trace'] = array(
			'selected_context_type'     => sanitize_key( $selected_type ),
			'auto_context_type'         => sanitize_key( $auto_type ),
			'final_context_type'        => sanitize_key( $final_type ),
			'context_set_manually'      => (bool) $manual,
			'brand_name_visible'        => (bool) $brand_visibility['allowed'],
			'brand_name_visible_source' => $brand_visibility['source'],
			'brand_name_visible_manual' => ! empty( $context['brand_name_visible_manual'] ),
			'ocr_found_brand'           => ! empty( $context['ocr_found_brand'] ),
			'downgraded_reasons'        => $downgrades,
			'trace'                     => $trace_steps,
		);

		return $context;
	}

	/**
	 * Determine if context has strong clinical signals.
	 *
	 * @param array $context Context array.
	 * @return bool True if clinical signals present.
	 */
	private static function has_strong_clinical_signals( array $context ) {
		$signals = array(
			! empty( $context['category_is_clinical'] ),
			! empty( $context['template_is_clinical'] ),
			! empty( $context['is_service_page'] ),
		);

		$service         = isset( $context['service'] ) ? strtolower( (string) $context['service'] ) : '';
		$default_service = isset( $context['default_service_slug'] ) ? strtolower( (string) $context['default_service_slug'] ) : '';

		if ( $service !== '' && $service !== $default_service && ! in_array( $service, array( 'general', 'services', 'service' ), true ) ) {
			$signals[] = true;
		}

		if ( ! empty( $context['tags'] ) ) {
			$clinical_tags = array( 'clinical', 'clinic', 'treatment', 'therapy', 'rehabilitation', 'rehab', 'physiotherapy', 'chiropractic' );
			if ( array_intersect( array_map( 'strtolower', (array) $context['tags'] ), $clinical_tags ) ) {
				$signals[] = true;
			}
		}

		return in_array( true, $signals, true );
	}

	/**
	 * Detect if context likely represents a gallery/portfolio asset.
	 *
	 * @param array $context Context array.
	 * @return bool True if gallery-like.
	 */
	private static function looks_like_gallery( array $context ) {
		if ( ! empty( $context['looks_like_gallery_hint'] ) ) {
			return true;
		}

		if ( ! empty( $context['in_gallery'] ) ) {
			return true;
		}

		$post_format = strtolower( (string) ( $context['post_format'] ?? '' ) );
		if ( $post_format === 'gallery' ) {
			return true;
		}

		$gallery_keywords = array( 'gallery', 'portfolio', 'lookbook', 'case study', 'case-study', 'before and after', 'before-after' );
		$haystacks        = array(
			strtolower( (string) ( $context['page_title'] ?? '' ) ),
			strtolower( (string) ( $context['attachment_title'] ?? '' ) ),
			implode( ' ', array_map( 'strtolower', (array) ( $context['tags'] ?? array() ) ) ),
		);

		foreach ( $gallery_keywords as $keyword ) {
			foreach ( $haystacks as $haystack ) {
				if ( $haystack !== '' && strpos( $haystack, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Compute brand visibility flag and source note.
	 *
	 * @param string $final_type Resolved context type.
	 * @param array  $context Context array.
	 * @return array { allowed => bool, source => string }
	 */
	private static function compute_brand_visibility( $final_type, array $context ) {
		$manual_override = ! empty( $context['brand_name_visible_manual'] );
		$ocr_detected    = ! empty( $context['ocr_found_brand'] );

		if ( in_array( $final_type, array( 'brand_logo', 'team', 'facility', 'equipment' ), true ) ) {
			return array(
				'allowed' => true,
				'source'  => 'context_type',
			);
		}

		if ( $final_type === 'testimonial' && ! empty( $context['context_set_manually'] ) ) {
			return array(
				'allowed' => true,
				'source'  => 'manual_testimonial',
			);
		}

		// Clinical, business, service-icon manually set → brand allowed
		if ( in_array( $final_type, array( 'clinical', 'business', 'service-icon' ), true )
		     && ! empty( $context['context_set_manually'] ) ) {
			return array(
				'allowed' => true,
				'source'  => 'manual_context',
			);
		}

		if ( in_array( $final_type, array( 'clinical', 'business', 'service-icon', 'testimonial' ), true ) ) {
			if ( $manual_override ) {
				return array(
					'allowed' => true,
					'source'  => 'manual_override',
				);
			}

			if ( $ocr_detected ) {
				return array(
					'allowed' => true,
					'source'  => 'ocr_detected',
				);
			}

			return array(
				'allowed' => false,
				'source'  => 'default_guard',
			);
		}

		// Stock/decorative or any other fallback → check for overrides.
		if ( $manual_override ) {
			return array(
				'allowed' => true,
				'source'  => 'manual_override',
			);
		}

		if ( $ocr_detected ) {
			return array(
				'allowed' => true,
				'source'  => 'ocr_detected',
			);
		}

		return array(
			'allowed' => false,
			'source'  => 'stock_guard',
		);
	}
}
