<?php
/**
 * Non-AI Composer for Smart Rephrase
 *
 * Generates unique, policy-compliant metadata deterministically using scene extraction + phrasebank.
 *
 * @package MSH_Image_Optimizer
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_NonAI_Composer {

	/**
	 * Rolling memory of recent titles/ALTs for uniqueness checking
	 *
	 * @var array
	 */
	private static $recent_outputs = array();

	/**
	 * Max outputs to remember
	 */
	const MEMORY_SIZE = 50;

	/**
	 * Compose metadata for an image
	 *
	 * @param array $input Canonical input format with id, filename, biz_context, page_context, policy
	 * @return array Metadata with title, alt_text, caption, description
	 */
	public static function compose( array $input ) {
		// Extract input
		$id            = $input['id'] ?? 0;
		$filename      = $input['filename'] ?? '';
		$biz_context   = $input['biz_context'] ?? array();
		$page_context  = $input['page_context'] ?? array();
		$policy        = $input['policy'] ?? array();

		// Deterministic seed for variation
		$seed = crc32( "{$id}:{$filename}" );

		// Extract scene from filename
		$scene = MSH_NonAI_Scene::extract( $filename, $id );

		// Get context type and policy flags
		$context_type         = $policy['context_type'] ?? 'stock';
		$seo_mode             = ! empty( $policy['seo_mode'] );
		$brand_name_visible   = ! empty( $policy['brand_name_visible'] );
		$context_set_manually = ! empty( $policy['context_set_manually'] );

		// Policy: Determine if brand is allowed
		$brand_allowed = self::brand_permitted( $context_type, $seo_mode, $brand_name_visible, $context_set_manually );

		// Generate metadata based on context type
		$metadata = self::generate_for_context(
			$context_type,
			$scene,
			$biz_context,
			$seed,
			$brand_allowed,
			$seo_mode
		);

		// Policy enforcement: Strip brand if not allowed or SEO off
		if ( ! $seo_mode || ! $brand_allowed ) {
			$metadata = self::strip_brand( $metadata, $biz_context['business_name'] ?? '' );
		}

		// Decorative special case: blank title/alt
		if ( $context_type === 'decorative' ) {
			$metadata['title']    = '';
			$metadata['alt_text'] = '';
		}

		// Uniqueness guard
		$metadata = self::ensure_unique( $metadata, $seed );

		// Remember this output
		self::remember_output( $metadata );

		return $metadata;
	}

	/**
	 * Determine if brand is permitted for this context
	 *
	 * @param string $context_type Context type
	 * @param bool   $seo_mode SEO mode enabled
	 * @param bool   $brand_name_visible Brand name visible (from OCR/manual)
	 * @param bool   $context_set_manually User manually set context
	 * @return bool True if brand allowed
	 */
	private static function brand_permitted( $context_type, $seo_mode, $brand_name_visible, $context_set_manually ) {
		// Always allowed for these contexts
		$always_allowed = array( 'logo', 'team', 'facility', 'equipment' );
		if ( in_array( $context_type, $always_allowed, true ) ) {
			return true;
		}

		// Never allowed for these
		$never_allowed = array( 'stock', 'decorative' );
		if ( in_array( $context_type, $never_allowed, true ) ) {
			return false;
		}

		// Conditional for clinical/business
		if ( in_array( $context_type, array( 'clinical', 'business' ), true ) ) {
			return $brand_name_visible;
		}

		// Testimonial: allow if manual or brand visible
		if ( $context_type === 'testimonial' ) {
			return $context_set_manually || $brand_name_visible;
		}

		// Default: require SEO mode
		return $seo_mode;
	}

	/**
	 * Generate metadata for a specific context type
	 *
	 * @param string $context_type Context type
	 * @param array  $scene Scene structure from MSH_NonAI_Scene::extract()
	 * @param array  $biz_context Business context
	 * @param int    $seed Deterministic seed
	 * @param bool   $brand_allowed Whether brand is allowed
	 * @param bool   $seo_mode SEO mode enabled
	 * @return array Metadata array
	 */
	private static function generate_for_context( $context_type, $scene, $biz_context, $seed, $brand_allowed, $seo_mode ) {
		switch ( $context_type ) {
			case 'stock':
			case 'decorative':
				return self::generate_stock( $scene, $seed );

			case 'facility':
				return self::generate_facility( $scene, $biz_context, $seed, $brand_allowed );

			case 'team':
				return self::generate_team( $scene, $biz_context, $seed, $brand_allowed );

			case 'equipment':
				return self::generate_equipment( $scene, $biz_context, $seed, $brand_allowed );

			case 'testimonial':
				return self::generate_testimonial( $scene, $biz_context, $seed, $brand_allowed );

			case 'clinical':
			case 'business':
			default:
				return self::generate_business( $scene, $biz_context, $seed, $brand_allowed );
		}
	}

	/**
	 * Generate stock/decorative metadata (scene-only, no brand)
	 *
	 * @param array $scene Scene structure
	 * @param int   $seed Deterministic seed
	 * @return array Metadata
	 */
	private static function generate_stock( $scene, $seed ) {
		$scene_desc = MSH_NonAI_Scene::describe( $scene );
		$scene_lower = strtolower( $scene_desc );

		error_log( sprintf(
			'[MSH Composer] generate_stock() - scene_desc="%s", seed=%d',
			$scene_desc,
			$seed
		) );

		// Title: Scene description
		$title = $scene_desc;

		// ALT: One factual sentence
		$alt_templates = array(
			"Scenic view of {$scene_lower}.",
			"{$scene_desc} captured in natural setting.",
			"Landscape featuring {$scene_lower}.",
			"Natural view of {$scene_lower}.",
		);
		$alt_text = $alt_templates[ $seed % count( $alt_templates ) ];

		// Caption: One scene sentence
		$mood = MSH_Phrasebank::get_mood( $seed );
		$caption_templates = array(
			"Natural landscape featuring {$scene_lower}",
			"Scenic composition with {$mood} atmosphere",
			"Outdoor view showcasing {$scene_lower}",
			"Landscape photography of {$scene_lower}",
		);
		$caption = $caption_templates[ $seed % count( $caption_templates ) ];

		// Description: Two sentences, both scene-focused
		$composition = MSH_Phrasebank::get_composition( $seed );
		$elements    = MSH_Phrasebank::get_elements( $seed );
		$light       = MSH_Phrasebank::get_light( $seed + 1 );

		$desc_templates = array(
			"A landscape view showcasing {$scene_lower}. {$composition} {$elements}.",
			"This scenic image features {$scene_lower} in natural setting. The photograph captures {$light} and environmental context.",
			"Natural scenery highlighting {$scene_lower}. The image presents a balanced view of the subject and surroundings.",
			"An outdoor photograph of {$scene_lower}. The scene conveys a sense of place through composition and framing.",
		);
		$description = $desc_templates[ $seed % count( $desc_templates ) ];

		error_log( sprintf(
			'[MSH Composer] Returning metadata - title="%s", alt="%s"',
			$title,
			substr( $alt_text, 0, 50 )
		) );

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $description,
		);
	}

	/**
	 * Generate facility metadata
	 *
	 * @param array $scene Scene structure
	 * @param array $biz_context Business context
	 * @param int   $seed Deterministic seed
	 * @param bool  $brand_allowed Whether brand is allowed
	 * @return array Metadata
	 */
	private static function generate_facility( $scene, $biz_context, $seed, $brand_allowed ) {
		$scene_desc   = MSH_NonAI_Scene::describe( $scene );
		$scene_lower  = strtolower( $scene_desc );
		$business     = $biz_context['business_name'] ?? '';
		$city         = $biz_context['city'] ?? '';
		$region       = $biz_context['region'] ?? '';
		$country      = $biz_context['country'] ?? '';
		$industry     = $biz_context['industry'] ?? 'healthcare';
		$unique_value = $biz_context['unique_value'] ?? '';

		// Build location string
		$location = trim( implode( ', ', array_filter( array( $city, $region, $country ) ) ) );

		if ( $brand_allowed && ! empty( $business ) ) {
			// With brand
			$title_templates = array(
				"{$business} Facility" . ( ! empty( $location ) ? " - {$location}" : '' ),
				"{$business} Interior" . ( ! empty( $location ) ? " | {$location}" : '' ),
				"Inside {$business}" . ( ! empty( $city ) ? " in {$city}" : '' ),
			);
			$title = $title_templates[ $seed % count( $title_templates ) ];

			$alt_text = "Interior view of {$business}" . ( ! empty( $city ) ? " in {$city}" : '' ) . '.';

			$caption = "Professional facility at {$business}";

			// Description: Scene first, then business sentence
			$desc_parts = array(
				"Modern {$industry} facility at {$business}" . ( ! empty( $location ) ? " in {$location}" : '' ) . '.',
			);
			if ( ! empty( $unique_value ) ) {
				$desc_parts[] = substr( $unique_value, 0, 160 ); // Trim to 160 chars
			}
			$description = implode( ' ', $desc_parts );
		} else {
			// No brand
			$title    = ucfirst( $industry ) . ' Facility Interior';
			$alt_text = 'Interior view of ' . $industry . ' facility.';
			$caption  = 'Modern professional facility interior';
			$description = "Professional {$industry} facility with specialized equipment and care spaces.";
		}

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $description,
		);
	}

	/**
	 * Generate team metadata
	 *
	 * @param array $scene Scene structure
	 * @param array $biz_context Business context
	 * @param int   $seed Deterministic seed
	 * @param bool  $brand_allowed Whether brand is allowed
	 * @return array Metadata
	 */
	private static function generate_team( $scene, $biz_context, $seed, $brand_allowed ) {
		$business = $biz_context['business_name'] ?? '';
		$industry = $biz_context['industry'] ?? 'professional';

		if ( $brand_allowed && ! empty( $business ) ) {
			$title       = "{$business} Team";
			$alt_text    = "Professional team members at {$business}.";
			$caption     = "Expert team at {$business}";
			$description = "Professional team at {$business} dedicated to delivering quality {$industry} services.";
		} else {
			$title       = ucfirst( $industry ) . ' Team';
			$alt_text    = 'Professional team members.';
			$caption     = 'Expert professional team';
			$description = "Professional team dedicated to delivering quality {$industry} services.";
		}

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $description,
		);
	}

	/**
	 * Generate equipment metadata
	 *
	 * @param array $scene Scene structure
	 * @param array $biz_context Business context
	 * @param int   $seed Deterministic seed
	 * @param bool  $brand_allowed Whether brand is allowed
	 * @return array Metadata
	 */
	private static function generate_equipment( $scene, $biz_context, $seed, $brand_allowed ) {
		$business = $biz_context['business_name'] ?? '';
		$industry = $biz_context['industry'] ?? 'professional';

		if ( $brand_allowed && ! empty( $business ) ) {
			$title       = "Professional Equipment - {$business}";
			$alt_text    = "Equipment at {$business} facility.";
			$caption     = "Advanced equipment for quality care";
			$description = "Professional equipment at {$business} supporting {$industry} services and patient care.";
		} else {
			$title       = 'Professional Equipment';
			$alt_text    = 'Professional equipment in facility.';
			$caption     = 'Advanced professional equipment';
			$description = "Professional equipment supporting {$industry} services and care delivery.";
		}

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $description,
		);
	}

	/**
	 * Generate testimonial metadata
	 *
	 * @param array $scene Scene structure
	 * @param array $biz_context Business context
	 * @param int   $seed Deterministic seed
	 * @param bool  $brand_allowed Whether brand is allowed
	 * @return array Metadata
	 */
	private static function generate_testimonial( $scene, $biz_context, $seed, $brand_allowed ) {
		$business = $biz_context['business_name'] ?? '';

		if ( $brand_allowed && ! empty( $business ) ) {
			$title       = "Patient Success Story - {$business}";
			$alt_text    = "Patient testimonial for {$business}.";
			$caption     = "Real patient experience at {$business}";
			$description = "Testimonial showcasing positive outcomes with {$business}. Patient-focused care and proven results.";
		} else {
			$title       = 'Patient Success Story';
			$alt_text    = 'Patient testimonial and success story.';
			$caption     = 'Real patient experience and outcomes';
			$description = 'Testimonial showcasing positive patient outcomes and quality care delivery.';
		}

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $description,
		);
	}

	/**
	 * Generate business/clinical metadata
	 *
	 * @param array $scene Scene structure
	 * @param array $biz_context Business context
	 * @param int   $seed Deterministic seed
	 * @param bool  $brand_allowed Whether brand is allowed
	 * @return array Metadata
	 */
	private static function generate_business( $scene, $biz_context, $seed, $brand_allowed ) {
		$scene_desc = MSH_NonAI_Scene::describe( $scene );
		$business   = $biz_context['business_name'] ?? '';
		$industry   = $biz_context['industry'] ?? 'business';

		if ( $brand_allowed && ! empty( $business ) ) {
			$title       = "{$scene_desc} - {$business}";
			$alt_text    = "{$scene_desc} at {$business}.";
			$caption     = "Professional visual from {$business}";
			$description = "{$scene_desc} showcasing {$business} commitment to quality {$industry} services.";
		} else {
			$title       = $scene_desc;
			$alt_text    = "Professional {$industry} visual.";
			$caption     = "Professional visual content";
			$description = "{$scene_desc} showcasing professional {$industry} services and commitment to quality.";
		}

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $description,
		);
	}

	/**
	 * Strip brand name from all metadata fields
	 *
	 * @param array  $metadata Metadata array
	 * @param string $brand_name Brand name to remove
	 * @return array Cleaned metadata
	 */
	private static function strip_brand( $metadata, $brand_name ) {
		if ( empty( $brand_name ) ) {
			return $metadata;
		}

		foreach ( $metadata as $key => $value ) {
			if ( is_string( $value ) ) {
				// Remove brand name (case-insensitive)
				$metadata[ $key ] = preg_replace( '/\b' . preg_quote( $brand_name, '/' ) . '\b/i', '', $value );
				// Clean up extra spaces, commas, hyphens
				$metadata[ $key ] = preg_replace( '/\s*[,\-|]\s*$/', '', $metadata[ $key ] );
				$metadata[ $key ] = preg_replace( '/\s{2,}/', ' ', $metadata[ $key ] );
				$metadata[ $key ] = trim( $metadata[ $key ] );
			}
		}

		return $metadata;
	}

	/**
	 * Ensure uniqueness by checking against recent outputs
	 *
	 * @param array $metadata Metadata array
	 * @param int   $seed Seed for variant selection
	 * @return array Metadata (possibly adjusted for uniqueness)
	 */
	private static function ensure_unique( $metadata, $seed ) {
		$title = $metadata['title'] ?? '';
		$alt   = $metadata['alt_text'] ?? '';

		// Check if too similar to recent outputs
		$is_unique = true;
		foreach ( self::$recent_outputs as $recent ) {
			$recent_title = $recent['title'] ?? '';
			$recent_alt   = $recent['alt_text'] ?? '';

			// Simple similarity check: exact match
			if ( ! empty( $title ) && $title === $recent_title ) {
				$is_unique = false;
				break;
			}
			if ( ! empty( $alt ) && $alt === $recent_alt ) {
				$is_unique = false;
				break;
			}
		}

		// If not unique, append a small variant suffix
		if ( ! $is_unique && ! empty( $title ) ) {
			$variant_num = ( $seed % 99 ) + 1;
			// Don't append number, instead rotate to next template (already deterministic)
			// Just log for now
			error_log( "[MSH NonAI Composer] Duplicate detected for: {$title}" );
		}

		return $metadata;
	}

	/**
	 * Remember this output for uniqueness checking
	 *
	 * @param array $metadata Metadata to remember
	 */
	private static function remember_output( $metadata ) {
		self::$recent_outputs[] = $metadata;

		// Keep only last N outputs
		if ( count( self::$recent_outputs ) > self::MEMORY_SIZE ) {
			array_shift( self::$recent_outputs );
		}
	}
}
