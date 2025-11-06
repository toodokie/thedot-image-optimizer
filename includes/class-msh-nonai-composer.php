<?php
/**
 * Deterministic metadata composer (non-AI flow).
 *
 * Generates policy-compliant metadata using filename scene extraction,
 * business context, and a lightweight phrasebank. All output is
 * deterministic (seeded by attachment ID + filename) so repeated runs
 * produce identical suggestions.
 *
 * @package MSH_Image_Optimizer
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_NonAI_Composer {

	/**
	 * Rolling memory of recent outputs used to enforce uniqueness.
	 *
	 * @var array
	 */
	private static $recent_outputs = array();

	/**
	 * Number of historical outputs kept for uniqueness checks.
	 */
	const MEMORY_SIZE = 50;

	/**
	 * Length limits (characters) for generated fields.
	 */
	const TITLE_LIMIT   = 60;
	const ALT_LIMIT     = 125;
	const CAPTION_LIMIT = 200;
	const DESC_LIMIT    = 500;

	/**
	 * Allowed per-attachment location insertion modes.
	 *
	 * @var string[]
	 */
	private static $loc_mode_allowed = array( 'auto', 'force_caption', 'force_all', 'off' );

	/**
	 * Contexts that support force_all behaviour.
	 *
	 * @var string[]
	 */
	private static $loc_mode_force_all_contexts = array( 'facility', 'service-icon' );

	/**
	 * Entrypoint - compose deterministic metadata.
	 *
	 * @param array $input Canonical payload (id, filename, biz_context, page_context, policy).
	 * @return array Metadata array { title, alt_text, caption, description, keywords[], filename_slug }.
	 */
	public static function compose( array $input ) {
		$id         = absint( $input['id'] ?? 0 );
		$filename   = sanitize_file_name( $input['filename'] ?? '' );
		$biz        = self::normalise_business_context( $input['biz_context'] ?? array() );
		$page       = self::normalise_page_context( $input['page_context'] ?? array() );
		$policy     = $input['policy'] ?? array();
		$context    = sanitize_key( $policy['context_type'] ?? 'stock' ) ?: 'stock';
		$seo_mode   = ! empty( $policy['seo_mode'] );
		$brand_flag = ! empty( $policy['brand_name_visible'] );
		$manual     = ! empty( $policy['context_set_manually'] );
		$loc_mode   = self::normalise_loc_mode( $policy['loc_mode'] ?? 'auto', $context );

		$canonical_context = $context;
		if ( function_exists( 'msh_canonicalize_ct' ) ) {
			$canonical_context = msh_canonicalize_ct(
				array(
					'context_type'        => $context,
					'brand_name_visible'  => $brand_flag,
					'context_set_manually'=> $manual,
				),
				$biz
			);
		}

		$seed        = self::seed( $id, $filename );
		$scene       = MSH_NonAI_Scene::extract( $filename, $id );
		$keywords    = MSH_NonAI_Scene::get_keywords( $scene );
		$tone_key    = self::resolve_tone_key( $biz['brand_voice'] );
		$location    = self::collect_location_parts( $biz );
		$service_kw  = self::pick_service_keyword( $biz, $scene, $canonical_context );
		$page_hint   = self::build_page_hint( $page, $tone_key, $seed );
		$brand_allow = self::brand_permitted( $canonical_context, $seo_mode, $brand_flag, $manual );

		if ( apply_filters( 'msh_nonai_debug_logging', true ) ) {
			error_log(
				sprintf(
					'[NONAI] ROW #%d ct=%s cm=%d seo=%d bm=%d loc_mode=%s',
					$id,
					$canonical_context,
					$manual ? 1 : 0,
					$seo_mode ? 1 : 0,
					$brand_flag ? 1 : 0,
					$loc_mode
				)
			);
		}

		$attempts = 0;
		do {
			$variant_seed = $seed + $attempts;
			$metadata     = self::generate_for_context(
				$canonical_context,
				$scene,
				$biz,
				$page,
				$variant_seed,
				$brand_allow,
				$seo_mode,
				$tone_key,
				$location,
				$service_kw,
				$page_hint,
				$loc_mode
			);

			$metadata['keywords']      = $keywords;
			$metadata['filename_slug'] = self::build_filename_slug( $scene, $canonical_context, $service_kw, $location, $brand_allow, $seo_mode, $loc_mode );
			$metadata                  = self::apply_policy_cleanups( $metadata, $biz, $location, $seo_mode, $brand_allow, $canonical_context, $scene, $loc_mode );
			$metadata                  = self::apply_page_context_enrichment( $metadata, $page_hint, $canonical_context );
			$metadata                  = self::sanitize_metadata( $metadata );
			$metadata                  = self::apply_length_limits( $metadata );
			$attempts ++;
		} while ( ! self::is_unique( $metadata ) && $attempts < 3 );

		self::remember_output( $metadata );

		// Log composer output branch
		if ( apply_filters( 'msh_nonai_debug_logging', true ) ) {
			$has_tail = ( $seo_mode && ! empty( $metadata['description'] ) && strpos( $metadata['description'], 'Useful for' ) !== false ) ? 1 : 0;
			error_log(
				sprintf(
					'[NONAI] compose ct=%s seo=%d tail=%d title_len=%d',
					$context,
					$seo_mode ? 1 : 0,
					$has_tail,
					strlen( $metadata['title'] ?? '' )
				)
			);
		}

		return $metadata;
	}

	/* -------------------------------------------------------------------------
	 * Generation helpers
	 * ---------------------------------------------------------------------- */

	private static function generate_for_context(
		$context_type,
		array $scene,
		array $biz,
		array $page,
		$seed,
		$brand_allowed,
		$seo_mode,
		$tone_key,
		array $location,
		$service_keyword,
		$page_hint,
		$loc_mode
	) {
		switch ( $context_type ) {
			case 'facility':
				return self::generate_facility( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $page_hint, $loc_mode );

			case 'service-icon':
				return self::generate_service_icon( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $loc_mode );

			case 'team':
				return self::generate_team( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $loc_mode );

			case 'equipment':
				return self::generate_equipment( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $loc_mode );

			case 'testimonial':
				return self::generate_testimonial( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $loc_mode );

			case 'clinical':
				return self::generate_business( 'clinical', $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $loc_mode );

			case 'business':
				return self::generate_business( 'business', $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $loc_mode );

			case 'brand_logo':
				return self::generate_brand_logo( $biz, $location );

			case 'decorative':
				return self::generate_decorative();

			case 'stock':
			default:
				return self::generate_stock( $scene, $seed, $tone_key, $seo_mode, $location, $service_keyword, $loc_mode );
		}
	}

	private static function generate_stock( array $scene, $seed, $tone_key, $seo_mode = false, array $location = array(), $service_keyword = '', $loc_mode = 'auto' ) {
	$primary_subject   = self::stock_primary_subject( $scene );
	$secondary_subject = self::stock_secondary_subject( $scene, $primary_subject );
	$verb              = MSH_Phrasebank::get_verb( strtolower( $secondary_subject ?: $primary_subject ), $seed + 1 );
	$time_phrase       = MSH_Phrasebank::get_time_phrase( $scene['time_of_day'] ?? '', $seed + 2 );
	$mood              = self::pick_tone_word( $tone_key, 'mood', $seed + 3, array( MSH_Phrasebank::get_mood( $seed + 3 ) ) );
	$light             = MSH_Phrasebank::get_light( $seed + 4 );
	$composition       = MSH_Phrasebank::get_composition( $seed + 5 );
	$elements          = MSH_Phrasebank::get_elements( $seed + 6 );

	$title = self::build_stock_title( $primary_subject, $scene, $time_phrase );

	// Defensive: stock images never expose force_all.
	if ( 'force_all' === $loc_mode ) {
		$loc_mode = 'force_caption';
	}

	$focus_components = array( $primary_subject );
	if ( $secondary_subject ) {
		$focus_components[] = strtolower( $verb );
		$focus_components[] = $secondary_subject;
	}
	$focus_phrase = self::sentence_case( implode( ' ', array_filter( $focus_components ) ) );

	$alt = sprintf(
		'%s captured under %s with a %s atmosphere.',
		trim( $focus_phrase ),
		strtolower( $light ),
		$mood
	);

	$caption_focus = $secondary_subject
		? sprintf( '%s %s %s', $primary_subject, strtolower( $verb ), $secondary_subject )
		: $primary_subject;

	$caption = sprintf(
		'%s %s under %s, conveying a %s mood.',
		$composition,
		$caption_focus,
		strtolower( $light ),
		$mood
	);

	$scene_sentence = implode(
		' ',
		array_filter(
			array(
				self::sentence_case( $primary_subject ),
				$secondary_subject ? strtolower( $verb ) : '',
				$secondary_subject ?: '',
				$time_phrase ?: '',
			)
		)
	);

	$description_parts = array(
		self::ensure_sentence( $scene_sentence ),
		self::ensure_sentence(
			sprintf(
				'%s %s, highlighting %s while maintaining a %s atmosphere.',
				$composition,
				strtolower( $light ),
				$elements,
				$mood
			)
		),
	);

	$tail = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'stock' );
	if ( $tail !== '' ) {
		$description_parts[] = $tail;
	}

	return array(
		'title'       => $title,
		'alt_text'    => self::ensure_sentence( $alt ),
		'caption'     => self::ensure_sentence( $caption ),
		'description' => implode( ' ', array_filter( $description_parts ) ),
	);
}

	private static function stock_primary_subject( array $scene ) {
		if ( ! empty( $scene['proper_names'] ) ) {
			return $scene['proper_names'][0];
		}

		if ( ! empty( $scene['nouns'] ) ) {
			return self::sentence_case( $scene['nouns'][0] );
		}

		return 'Scenic view';
	}

	private static function stock_secondary_subject( array $scene, $primary_subject ) {
		$primary_lower = strtolower( (string) $primary_subject );

		if ( ! empty( $scene['nouns'] ) ) {
			foreach ( $scene['nouns'] as $noun ) {
				if ( strtolower( $noun ) === $primary_lower ) {
					continue;
				}
				return self::format_secondary_subject( $noun );
			}
		}

		if ( ! empty( $scene['proper_names'] ) && ! empty( $scene['nouns'] ) ) {
			return self::format_secondary_subject( $scene['nouns'][0] );
		}

		return '';
	}

	private static function build_stock_title( $primary_subject, array $scene, $time_phrase ) {
		$primary_subject = trim( (string) $primary_subject );
		$adjective       = '';

		if ( ! empty( $scene['adjectives'] ) ) {
			$adjective = trim( (string) $scene['adjectives'][0] );
		}

		$parts = array();

		if ( '' !== $adjective && stripos( $primary_subject, $adjective ) === false ) {
			$parts[] = self::sentence_case( $adjective );
		}

		if ( '' !== $primary_subject ) {
			$parts[] = self::sentence_case( $primary_subject );
		}

		if ( '' !== $time_phrase ) {
			$parts[] = trim( (string) $time_phrase );
		}

		$title = implode( ' ', array_filter( $parts ) );

		if ( str_word_count( $title ) < 2 ) {
			$title = trim( $title . ' ' . __( 'Scene', 'msh-image-optimizer' ) );
		}

		if ( '' === $title ) {
			$title = __( 'Stock Scene', 'msh-image-optimizer' );
		}

		return self::sentence_case( $title );
	}

	private static function format_secondary_subject( $subject ) {
		$subject = trim( (string) $subject );
		if ( '' === $subject ) {
			return '';
		}

		if ( preg_match( '/^[A-Z]/', $subject ) ) {
			return $subject;
		}

		return 'the ' . strtolower( $subject );
	}

	private static function generate_decorative() {
		return array(
			'title'       => __( 'Decorative Image', 'msh-image-optimizer' ),
			'alt_text'    => '',
			'caption'     => '',
			'description' => __( 'Decorative image with no descriptive metadata required.', 'msh-image-optimizer' ),
		);
	}

	private static function generate_brand_logo( array $biz, array $location ) {
		$brand_raw  = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name = self::prepare_brand_label( $brand_raw, self::extract_location_terms( $location ) );

		if ( '' === $brand_name ) {
			$brand_name = $brand_raw !== '' ? sanitize_text_field( $brand_raw ) : __( 'Brand', 'msh-image-optimizer' );
		}

		$title       = sprintf( __( '%s — Logo', 'msh-image-optimizer' ), $brand_name );
		$alt         = sprintf( __( 'Logo for %s.', 'msh-image-optimizer' ), $brand_name );
		$caption     = sprintf( __( '%s logo asset.', 'msh-image-optimizer' ), $brand_name );
		$description = sprintf( __( '%s logo for use across digital and print touchpoints.', 'msh-image-optimizer' ), $brand_name );

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => self::ensure_sentence( $description ),
		);
	}

	private static function context_descriptor( $context_type ) {
		switch ( $context_type ) {
			case 'facility':
				return __( 'Rehabilitation Facility Interior', 'msh-image-optimizer' );
			case 'team':
				return __( 'Specialist Care Team', 'msh-image-optimizer' );
			case 'equipment':
				return __( 'Therapy Equipment Suite', 'msh-image-optimizer' );
			case 'testimonial':
				return __( 'Patient Success Story', 'msh-image-optimizer' );
			case 'clinical':
				return __( 'Clinical Session Visual', 'msh-image-optimizer' );
			case 'business':
				return __( 'Editorial Image', 'msh-image-optimizer' );
		}

		return __( 'Editorial Image', 'msh-image-optimizer' );
	}

	private static function generate_facility( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $page_hint, $loc_mode ) {
		unset( $scene );

		$descriptor     = self::context_descriptor( 'facility' );
		$facility_adj   = self::pick_tone_word( $tone_key, 'facility_adj', $seed, array( 'modern', 'specialised' ) );
		$industry       = $biz['industry'] ?: __( 'healthcare', 'msh-image-optimizer' );
		$location_terms = self::extract_location_terms( $location );
		$brand_raw      = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name     = ( $brand_allowed && $brand_raw !== '' ) ? self::prepare_brand_label( $brand_raw, $location_terms ) : '';
		if ( $brand_allowed && $brand_raw !== '' && '' === $brand_name ) {
			$brand_name = sanitize_text_field( $brand_raw );
		}
		$location_label = self::format_location_label( $location );

		$alt_base     = __( 'Rehabilitation facility interior ready for patient care.', 'msh-image-optimizer' );
		$caption_base = __( 'Rehabilitation facility interior ready for patient care.', 'msh-image-optimizer' );
		if ( $brand_name ) {
			$alt_base     = sprintf( __( 'Rehabilitation facility interior at %s.', 'msh-image-optimizer' ), $brand_name );
			$caption_base = sprintf( __( 'Rehabilitation facility interior at %s.', 'msh-image-optimizer' ), $brand_name );
		}

		if ( ! $seo_mode ) {
			$title            = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;
			$description_text = $brand_name
				? sprintf( __( 'Rehabilitation facility interior at %s supporting patient recovery.', 'msh-image-optimizer' ), $brand_name )
				: __( 'Rehabilitation facility interior supporting patient recovery.', 'msh-image-optimizer' );

			return array(
				'title'       => $title,
				'alt_text'    => self::ensure_sentence( $alt_base ),
				'caption'     => self::ensure_sentence( $caption_base ),
				'description' => self::ensure_sentence( $description_text ),
			);
		}

		$title = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;
		if ( self::context_supports_force_all( 'facility' ) && 'force_all' === $loc_mode && $location_label !== '' ) {
			$title = self::append_location_to_title( $title, $location_label );
		}

		if ( $brand_name ) {
			$primary_sentence = sprintf(
				__( '%s rehabilitation environment at %s supports specialised care.', 'msh-image-optimizer' ),
				ucfirst( $facility_adj ),
				$brand_name
			);
		} else {
			$primary_sentence = sprintf(
				__( '%s rehabilitation environment supports specialised %s care.', 'msh-image-optimizer' ),
				ucfirst( $facility_adj ),
				$industry
			);
		}

		$description = self::ensure_sentence( $primary_sentence );

		$summary = self::unique_value_summary( $biz, $seo_mode, 'facility' );
		$description = self::merge_sentence_with_uvp( $description, $summary );

		$tail        = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'facility' );
		$description = self::append_sentence_with_limit( $description, $tail, 2 );

		if ( $page_hint ) {
			$description = self::append_sentence_with_limit( $description, $page_hint, 2 );
		}

		if ( function_exists( 'msh_limit_sentences' ) ) {
			$description = msh_limit_sentences( $description, 2 );
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt_base ),
			'caption'     => self::ensure_sentence( $caption_base ),
			'description' => $description,
		);
	}

	private static function generate_service_icon( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $loc_mode ) {
		$location_terms = self::extract_location_terms( $location );
		$brand_raw      = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name     = ( $brand_allowed && $brand_raw !== '' ) ? self::prepare_brand_label( $brand_raw, $location_terms ) : '';
		if ( $brand_allowed && $brand_raw !== '' && '' === $brand_name ) {
			$brand_name = sanitize_text_field( $brand_raw );
		}

		// SEO OFF: return brand+icon body only, no tails
		if ( ! $seo_mode ) {
			if ( $brand_name ) {
				$title = sprintf( '%s — Service Icon', $brand_name );
				$alt   = sprintf( '%s service icon.', $brand_name );
				$caption = sprintf( '%s service icon.', $brand_name );
				$description = self::ensure_sentence( sprintf( '%s service icon.', $brand_name ) );
			} else {
				$title = __( 'Service Icon', 'msh-image-optimizer' );
				$alt   = __( 'Service icon.', 'msh-image-optimizer' );
				$caption = __( 'Service icon.', 'msh-image-optimizer' );
				$description = __( 'Service icon.', 'msh-image-optimizer' );
			}
			return array(
				'title'       => $title,
				'alt_text'    => $alt,
				'caption'     => $caption,
				'description' => $description,
			);
		}

		// SEO ON: full metadata with location tails
		$location_label = self::format_location_label( $location );

		if ( $brand_name ) {
			$title = sprintf( '%s — Service Icon', $brand_name );
			if ( self::context_supports_force_all( 'service-icon' ) && 'force_all' === $loc_mode && $location_label !== '' ) {
				$title = self::append_location_to_title( $title, $location_label );
			}
			$alt = sprintf( __( 'Service icon for %s.', 'msh-image-optimizer' ), $brand_name );
			$caption = sprintf(
				__( 'Service icon used across %s digital touchpoints.', 'msh-image-optimizer' ),
				$brand_name
			);
			$primary_sentence = sprintf(
				__( 'Custom service icon reinforces %s across digital channels.', 'msh-image-optimizer' ),
				$brand_name
			);
		} else {
			$title = __( 'Service Icon', 'msh-image-optimizer' );
			$alt   = __( 'Service icon representing the organisation.', 'msh-image-optimizer' );
			$caption = __( 'Service icon for cross-platform consistency.', 'msh-image-optimizer' );
			$primary_sentence = __( 'Purpose-built icon providing consistent wayfinding in digital experiences.', 'msh-image-optimizer' );
		}

		$description = self::ensure_sentence( $primary_sentence );

		$summary = self::unique_value_summary( $biz, $seo_mode, 'service-icon' );
		$description = self::merge_sentence_with_uvp( $description, $summary );

		$tail = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'service-icon' );
		$description = self::append_sentence_with_limit( $description, $tail, 2 );

		if ( function_exists( 'msh_limit_sentences' ) ) {
			$description = msh_limit_sentences( $description, 2 );
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => $description,
		);
	}


	private static function generate_team( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $loc_mode ) {
		unset( $scene );

		$descriptor     = self::context_descriptor( 'team' );
		$team_adj       = self::pick_tone_word( $tone_key, 'team_adj', $seed, array( 'dedicated', 'skilled' ) );
		$industry       = $biz['industry'] ?: __( 'professional', 'msh-image-optimizer' );
		$location_terms = self::extract_location_terms( $location );
		$brand_raw      = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name     = ( $brand_allowed && $brand_raw !== '' ) ? self::prepare_brand_label( $brand_raw, $location_terms ) : '';
		if ( $brand_allowed && $brand_raw !== '' && '' === $brand_name ) {
			$brand_name = sanitize_text_field( $brand_raw );
		}

		$alt_base = $brand_name
			? sprintf( __( 'Specialist care team at %s.', 'msh-image-optimizer' ), $brand_name )
			: sprintf( __( 'Specialist care team supporting %s services.', 'msh-image-optimizer' ), $industry );
		$caption_base = $brand_name
			? sprintf( __( 'Specialist care team collaborating at %s.', 'msh-image-optimizer' ), $brand_name )
			: __( 'Specialist care team collaborating on patient support.', 'msh-image-optimizer' );

		if ( ! $seo_mode ) {
			$title            = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;
			$description_text = $brand_name
				? sprintf( __( 'Specialist care team at %s delivering personalised support.', 'msh-image-optimizer' ), $brand_name )
				: __( 'Specialist care team delivering personalised support.', 'msh-image-optimizer' );

			return array(
				'title'       => $title,
				'alt_text'    => self::ensure_sentence( $alt_base ),
				'caption'     => self::ensure_sentence( $caption_base ),
				'description' => self::ensure_sentence( $description_text ),
			);
		}

		$title = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;

		if ( $brand_name ) {
			$primary_sentence = sprintf(
				__( '%s care team at %s collaborates to support patient goals.', 'msh-image-optimizer' ),
				ucfirst( $team_adj ),
				$brand_name
			);
		} else {
			$primary_sentence = sprintf(
				__( '%s care team delivers coordinated %s support.', 'msh-image-optimizer' ),
				ucfirst( $team_adj ),
				$industry
			);
		}

		$description = self::ensure_sentence( $primary_sentence );

		$summary = self::unique_value_summary( $biz, $seo_mode, 'team' );
		$description = self::merge_sentence_with_uvp( $description, $summary );

		$tail        = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'team' );
		$description = self::append_sentence_with_limit( $description, $tail, 2 );

		if ( function_exists( 'msh_limit_sentences' ) ) {
			$description = msh_limit_sentences( $description, 2 );
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt_base ),
			'caption'     => self::ensure_sentence( $caption_base ),
			'description' => $description,
		);
	}


	private static function generate_equipment( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $loc_mode ) {
		unset( $scene );

		$descriptor     = self::context_descriptor( 'equipment' );
		$equipment_adj  = self::pick_tone_word( $tone_key, 'equipment_adj', $seed, array( 'advanced', 'specialist' ) );
		$industry       = $biz['industry'] ?: __( 'professional', 'msh-image-optimizer' );
		$location_terms = self::extract_location_terms( $location );
		$brand_raw      = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name     = ( $brand_allowed && $brand_raw !== '' ) ? self::prepare_brand_label( $brand_raw, $location_terms ) : '';
		if ( $brand_allowed && $brand_raw !== '' && '' === $brand_name ) {
			$brand_name = sanitize_text_field( $brand_raw );
		}
		$location_label = self::format_location_label( $location );

		$alt_base = $brand_name
			? sprintf( __( 'Therapy equipment suite at %s.', 'msh-image-optimizer' ), $brand_name )
			: __( 'Therapy equipment suite prepared for patient care.', 'msh-image-optimizer' );
		$caption_base = $brand_name
			? sprintf( __( 'Therapy equipment suite ready at %s.', 'msh-image-optimizer' ), $brand_name )
			: __( 'Therapy equipment suite ready for patient sessions.', 'msh-image-optimizer' );

		if ( ! $seo_mode ) {
			$title            = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;
			$description_text = $brand_name
				? sprintf( __( 'Therapy equipment suite at %s supporting specialised treatment.', 'msh-image-optimizer' ), $brand_name )
				: __( 'Therapy equipment suite supporting specialised treatment.', 'msh-image-optimizer' );

			return array(
				'title'       => $title,
				'alt_text'    => self::ensure_sentence( $alt_base ),
				'caption'     => self::ensure_sentence( $caption_base ),
				'description' => self::ensure_sentence( $description_text ),
			);
		}

		$title = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;
		if ( self::context_supports_force_all( 'equipment' ) && 'force_all' === $loc_mode && $location_label !== '' ) {
			$title = self::append_location_to_title( $title, $location_label );
		}

		if ( $brand_name ) {
			$primary_sentence = sprintf(
				__( '%s therapy equipment suite at %s supports specialist programmes.', 'msh-image-optimizer' ),
				ucfirst( $equipment_adj ),
				$brand_name
			);
		} else {
			$primary_sentence = sprintf(
				__( '%s therapy equipment suite supports specialist %s care.', 'msh-image-optimizer' ),
				ucfirst( $equipment_adj ),
				$industry
			);
		}

		$description = self::ensure_sentence( $primary_sentence );

		$summary = self::unique_value_summary( $biz, $seo_mode, 'equipment' );
		$description = self::merge_sentence_with_uvp( $description, $summary );

		$tail        = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'equipment' );
		$description = self::append_sentence_with_limit( $description, $tail, 2 );

		if ( function_exists( 'msh_limit_sentences' ) ) {
			$description = msh_limit_sentences( $description, 2 );
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt_base ),
			'caption'     => self::ensure_sentence( $caption_base ),
			'description' => $description,
		);
	}


	private static function generate_testimonial( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $loc_mode ) {
		unset( $scene );

		$descriptor      = self::context_descriptor( 'testimonial' );
		$testimonial_adj = self::pick_tone_word( $tone_key, 'testimonial_adj', $seed, array( 'patient-focused', 'client-focused' ) );
		$location_terms  = self::extract_location_terms( $location );
		$brand_raw       = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name      = ( $brand_allowed && $brand_raw !== '' ) ? self::prepare_brand_label( $brand_raw, $location_terms ) : '';
		if ( $brand_allowed && $brand_raw !== '' && '' === $brand_name ) {
			$brand_name = sanitize_text_field( $brand_raw );
		}

		$alt_base = $brand_name
			? sprintf( __( 'Patient success story at %s.', 'msh-image-optimizer' ), $brand_name )
			: __( 'Patient success story highlighting positive outcomes.', 'msh-image-optimizer' );
		$caption_base = $brand_name
			? sprintf( __( 'Patient shares their success with %s.', 'msh-image-optimizer' ), $brand_name )
			: __( 'Patient success story celebrating care delivered.', 'msh-image-optimizer' );

		if ( ! $seo_mode ) {
			$title            = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;
			$description_text = $brand_name
				? sprintf( __( 'Patient success story sharing outcomes achieved with %s.', 'msh-image-optimizer' ), $brand_name )
				: __( 'Patient success story sharing outcomes achieved through care.', 'msh-image-optimizer' );

			return array(
				'title'       => $title,
				'alt_text'    => self::ensure_sentence( $alt_base ),
				'caption'     => self::ensure_sentence( $caption_base ),
				'description' => self::ensure_sentence( $description_text ),
			);
		}

		$title = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;

		if ( $brand_name ) {
			$primary_sentence = sprintf(
				__( '%s patient success story highlights positive outcomes.', 'msh-image-optimizer' ),
				$brand_name
			);
		} else {
			$primary_sentence = __( 'Patient success story highlights meaningful care outcomes.', 'msh-image-optimizer' );
		}

		$description = self::ensure_sentence( $primary_sentence );

		$summary = self::unique_value_summary( $biz, $seo_mode, 'testimonial' );
		$description = self::merge_sentence_with_uvp( $description, $summary );

		if ( $brand_allowed ) {
			$tail        = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'testimonial' );
			$description = self::append_sentence_with_limit( $description, $tail, 2 );
		}

		if ( function_exists( 'msh_limit_sentences' ) ) {
			$description = msh_limit_sentences( $description, 2 );
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt_base ),
			'caption'     => self::ensure_sentence( $caption_base ),
			'description' => $description,
		);
	}


	private static function generate_business( $context_type, array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $loc_mode ) {
		unset( $scene );

		$descriptor     = self::context_descriptor( $context_type );
		$business_adj   = self::pick_tone_word( $tone_key, 'business_adj', $seed, array( 'professional', 'specialist' ) );
		$focus_label    = ( 'clinical' === $context_type ) ? __( 'clinical care', 'msh-image-optimizer' ) : __( 'professional services', 'msh-image-optimizer' );
		$location_terms = self::extract_location_terms( $location );
		$brand_raw      = isset( $biz['business_name'] ) ? $biz['business_name'] : '';
		$brand_name     = ( $brand_allowed && $brand_raw !== '' ) ? self::prepare_brand_label( $brand_raw, $location_terms ) : '';
		if ( $brand_allowed && $brand_raw !== '' && '' === $brand_name ) {
			$brand_name = sanitize_text_field( $brand_raw );
		}

		$title = $brand_name ? sprintf( '%s — %s', $brand_name, $descriptor ) : $descriptor;

		$alt = $brand_name
			? sprintf( __( '%s produced for %s.', 'msh-image-optimizer' ), $descriptor, $brand_name )
			: sprintf( __( '%s supporting %s.', 'msh-image-optimizer' ), $descriptor, $focus_label );

		$caption = $brand_name
			? sprintf( __( '%s supporting %s messaging.', 'msh-image-optimizer' ), $descriptor, $brand_name )
			: sprintf( __( '%s illustrating %s.', 'msh-image-optimizer' ), $descriptor, $focus_label );

		if ( $brand_name ) {
			$primary_sentence = sprintf(
				__( '%s for %s highlights %s expertise.', 'msh-image-optimizer' ),
				$descriptor,
				$brand_name,
				$focus_label
			);
		} else {
			$primary_sentence = sprintf(
				__( '%s highlights %s expertise.', 'msh-image-optimizer' ),
				$descriptor,
				$focus_label
			);
		}

		$primary_sentence = self::ensure_sentence( $primary_sentence );

		if ( ! $seo_mode || 'off' === $loc_mode ) {
			return array(
				'title'       => $title,
				'alt_text'    => self::ensure_sentence( $alt ),
				'caption'     => self::ensure_sentence( $caption ),
				'description' => $primary_sentence,
			);
		}

		$description = $primary_sentence;

		$summary = self::unique_value_summary( $biz, $seo_mode, $context_type );
		$description = self::merge_sentence_with_uvp( $description, $summary );

		if ( $brand_allowed ) {
			$tail        = self::build_seo_tail( $tone_key, $location, $service_keyword, $seo_mode, $loc_mode, 'business' );
			$description = self::append_sentence_with_limit( $description, $tail, 2 );
		}

		if ( function_exists( 'msh_limit_sentences' ) ) {
			$description = msh_limit_sentences( $description, 2 );
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => $description,
		);
	}


	/* -------------------------------------------------------------------------
	 * Normalisation helpers
	 * ---------------------------------------------------------------------- */

	private static function normalise_business_context( array $biz ) {
		return array(
			'business_name'    => isset( $biz['business_name'] ) ? sanitize_text_field( $biz['business_name'] ) : '',
			'city'             => isset( $biz['city'] ) ? sanitize_text_field( $biz['city'] ) : '',
			'region'           => isset( $biz['region'] ) ? sanitize_text_field( $biz['region'] ) : '',
			'country'          => isset( $biz['country'] ) ? sanitize_text_field( $biz['country'] ) : '',
			'industry'         => isset( $biz['industry'] ) ? sanitize_text_field( $biz['industry'] ) : '',
			'brand_voice'      => isset( $biz['brand_voice'] ) ? sanitize_text_field( strtolower( $biz['brand_voice'] ) ) : '',
			'unique_value'     => isset( $biz['unique_value'] ) ? sanitize_textarea_field( $biz['unique_value'] ) : '',
			'service_keywords' => array_filter(
				array_map(
					'sanitize_text_field',
				(array) ( $biz['service_keywords'] ?? array() )
				)
			),
		);
	}

	private static function normalise_page_context( array $page ) {
		return array(
			'page_title'   => isset( $page['page_title'] ) ? sanitize_text_field( $page['page_title'] ) : '',
			'focus_keyword' => isset( $page['focus_keyword'] ) ? sanitize_text_field( $page['focus_keyword'] ) : '',
			'page_role'    => isset( $page['page_role'] ) ? sanitize_key( $page['page_role'] ) : '',
		);
	}

	private static function seed( $id, $filename ) {
		return crc32( sprintf( '%d:%s', $id, $filename ) );
	}

	private static function resolve_tone_key( $voice ) {
		$voice = strtolower( trim( (string) $voice ) );
		$allowed = array( 'professional', 'friendly', 'casual', 'technical' );
		if ( in_array( $voice, $allowed, true ) ) {
			return $voice;
		}
		return 'neutral';
	}

	private static function tone_lexicon() {
		return array(
			'professional' => array(
				'mood'            => array( 'confident', 'steady', 'focused' ),
				'facility_adj'    => array( 'professional', 'modern', 'accredited' ),
				'team_adj'        => array( 'specialist', 'expert', 'credentialed' ),
				'equipment_adj'   => array( 'advanced', 'clinical-grade', 'precision' ),
				'testimonial_adj' => array( 'patient-focused', 'outcome-driven' ),
				'business_adj'    => array( 'professional', 'service-focused', 'trusted' ),
			),
			'friendly' => array(
				'mood'            => array( 'warm', 'welcoming', 'inviting' ),
				'facility_adj'    => array( 'welcoming', 'comfortable', 'supportive' ),
				'team_adj'        => array( 'friendly', 'caring', 'approachable' ),
				'equipment_adj'   => array( 'client-ready', 'comfortable', 'supportive' ),
				'testimonial_adj' => array( 'client-focused', 'community', 'neighbourhood' ),
				'business_adj'    => array( 'friendly', 'community', 'local' ),
			),
			'casual' => array(
				'mood'            => array( 'relaxed', 'easy-going', 'calm' ),
				'facility_adj'    => array( 'relaxed', 'open', 'comfortable' ),
				'team_adj'        => array( 'down-to-earth', 'supportive', 'approachable' ),
				'equipment_adj'   => array( 'everyday', 'reliable', 'ready-to-use' ),
				'testimonial_adj' => array( 'real-life', 'client-focused', 'everyday' ),
				'business_adj'    => array( 'casual', 'approachable', 'reliable' ),
			),
			'technical' => array(
				'mood'            => array( 'precise', 'methodical', 'analytical' ),
				'facility_adj'    => array( 'specialised', 'high-tech', 'precision' ),
				'team_adj'        => array( 'technical', 'certified', 'skilled' ),
				'equipment_adj'   => array( 'laboratory-grade', 'precision', 'specialist' ),
				'testimonial_adj' => array( 'evidence-based', 'technical', 'measurable' ),
				'business_adj'    => array( 'technical', 'data-driven', 'specialist' ),
			),
			'neutral' => array(
				'mood'            => array( 'balanced', 'calm', 'composed' ),
				'facility_adj'    => array( 'modern', 'professional', 'well-appointed' ),
				'team_adj'        => array( 'skilled', 'dedicated', 'professional' ),
				'equipment_adj'   => array( 'modern', 'specialist', 'advanced' ),
				'testimonial_adj' => array( 'client-focused', 'patient-focused', 'service-oriented' ),
				'business_adj'    => array( 'professional', 'reliable', 'trusted' ),
			),
		);
	}

	private static function pick_tone_word( $tone_key, $bucket, $seed, array $fallback = array() ) {
		$lexicon = self::tone_lexicon();
		if ( isset( $lexicon[ $tone_key ][ $bucket ] ) && ! empty( $lexicon[ $tone_key ][ $bucket ] ) ) {
			return self::pick_variant( $lexicon[ $tone_key ][ $bucket ], $seed );
		}
		if ( ! empty( $fallback ) ) {
			return self::pick_variant( $fallback, $seed );
		}
		return '';
	}

	private static function pick_variant( array $options, $seed, $salt = 0 ) {
		$options = array_values( array_filter( $options ) );
		if ( empty( $options ) ) {
			return '';
		}
		$index = absint( $seed + $salt ) % count( $options );
		return $options[ $index ];
	}

	private static function collect_location_parts( array $biz ) {
		$parts = array();
		if ( $biz['city'] !== '' ) {
			$parts[] = $biz['city'];
		}
		if ( $biz['region'] !== '' ) {
			$parts[] = $biz['region'];
		}
		if ( $biz['country'] !== '' ) {
			$parts[] = $biz['country'];
		}
		return $parts;
	}

	private static function pick_service_keyword( array $biz, array $scene, $context_type ) {
		if ( empty( $biz['service_keywords'] ) ) {
			return '';
		}

		$keywords = array_values( $biz['service_keywords'] );
		$primary  = isset( $keywords[0] ) ? sanitize_text_field( $keywords[0] ) : '';

		if ( '' === $primary ) {
			return '';
		}

		if ( in_array( $context_type, array( 'stock', 'decorative' ), true ) && ! self::service_keyword_plausible( $primary, $scene ) ) {
			return '';
		}

		return $primary;
	}

	private static function refine_scene_descriptor( $descriptor, $fallback ) {
		$descriptor = trim( (string) $descriptor );
		if ( '' === $descriptor ) {
			return $fallback;
		}

		$haystack   = strtolower( $descriptor );
		$bad_tokens = array( 'test', 'sample', 'default', 'placeholder', 'screenshot', 'image', 'img', 'msh' );

		foreach ( $bad_tokens as $token ) {
			if ( strpos( $haystack, $token ) !== false ) {
				return $fallback;
			}
		}

		if ( preg_match( '/\d{4,}/', $haystack ) ) {
			return $fallback;
		}

		return $descriptor;
	}

	private static function prepare_brand_label( $brand_name, array $location_terms ) {
		$brand_name = trim( (string) $brand_name );
		if ( '' === $brand_name ) {
			return '';
		}

		$clean = self::strip_terms_from_field( $brand_name, $location_terms );
		$clean = trim( $clean );

		if ( '' === $clean ) {
			$clean = sanitize_text_field( $brand_name );
		}

		$clean = preg_replace( '/\s{2,}/', ' ', $clean );
		$clean = trim( $clean, "-|, " );

		if ( '' === $clean ) {
			$clean = sanitize_text_field( $brand_name );
		}

		return trim( $clean );
	}

	private static function service_keyword_plausible( $service_keyword, array $scene ) {
		$keyword_terms = array_filter(
			array_map(
				'strtolower',
				preg_split( '/[\s\-\._\/]+/', sanitize_text_field( $service_keyword ) )
			)
		);

		if ( empty( $keyword_terms ) ) {
			return false;
		}

		$scene_terms = array();
		foreach ( array( 'nouns', 'proper_names', 'subjects' ) as $slot ) {
			if ( empty( $scene[ $slot ] ) || ! is_array( $scene[ $slot ] ) ) {
				continue;
			}
			foreach ( $scene[ $slot ] as $term ) {
				$sanitised = strtolower( sanitize_text_field( $term ) );
				if ( $sanitised !== '' ) {
					$scene_terms[] = $sanitised;
				}
			}
		}

		if ( empty( $scene_terms ) ) {
			return false;
		}

		$scene_terms = array_unique( $scene_terms );

		foreach ( $keyword_terms as $term ) {
			if ( strlen( $term ) < 3 ) {
				continue;
			}
			if ( in_array( $term, $scene_terms, true ) ) {
				return true;
			}
		}

		return false;
	}

	private static function build_page_hint( array $page, $tone_key, $seed ) {
		if ( $page['page_title'] === '' && $page['focus_keyword'] === '' && $page['page_role'] === '' ) {
			return '';
		}

		$role    = $page['page_role'];
		$title   = self::promptSafe( $page['page_title'], 12 );
		$keyword = self::promptSafe( $page['focus_keyword'], 8 );

		$generic_titles = array(
			'post format: gallery',
			'post format gallery',
			'image attachment',
			'attachment details',
			'default',
			'untitled',
		);

		if ( in_array( strtolower( $title ), $generic_titles, true ) ) {
			$title = '';
		}

		switch ( $role ) {
			case 'header_image':
				return $title ? sprintf( __( 'Featured in the header: %s.', 'msh-image-optimizer' ), $title ) : __( 'Featured in the page header.', 'msh-image-optimizer' );
			case 'service_page_photo':
			case 'service_page':
				if ( $keyword ) {
					return sprintf( __( 'Supports the %s service page.', 'msh-image-optimizer' ), $keyword );
				}
				break;
			case 'article_body_image':
				return $title ? sprintf( __( 'Used within the article "%s".', 'msh-image-optimizer' ), $title ) : __( 'Used within the article body.', 'msh-image-optimizer' );
		}

		if ( $title ) {
			return sprintf( __( 'Associated page: %s.', 'msh-image-optimizer' ), $title );
		}

		if ( $keyword ) {
			return sprintf( __( 'Focus keyword: %s.', 'msh-image-optimizer' ), $keyword );
		}

		return '';
	}

	private static function brand_permitted( $context_type, $seo_mode, $brand_name_visible, $context_set_manually ) {
		$always_allowed = array( 'logo', 'team', 'facility', 'equipment', 'service-icon', 'brand_logo' );
		if ( in_array( $context_type, $always_allowed, true ) ) {
			return true;
		}

		if ( in_array( $context_type, array( 'stock', 'decorative' ), true ) ) {
			return false;
		}

		if ( in_array( $context_type, array( 'clinical', 'business' ), true ) ) {
			return (bool) $brand_name_visible;
		}

		if ( 'testimonial' === $context_type ) {
			return (bool) $brand_name_visible || $context_set_manually;
		}

		return false;
	}

	private static function normalise_loc_mode( $loc_mode, $context_type ) {
		$loc_mode = sanitize_key( (string) $loc_mode );
		if ( ! in_array( $loc_mode, self::$loc_mode_allowed, true ) ) {
			$loc_mode = 'auto';
		}

		if ( 'force_all' === $loc_mode && ! in_array( $context_type, self::$loc_mode_force_all_contexts, true ) ) {
			return 'force_caption';
		}

		return $loc_mode;
	}

	private static function context_supports_force_all( $context_type ) {
		return in_array( $context_type, self::$loc_mode_force_all_contexts, true );
	}

	private static function format_location_label( array $location_parts ) {
		$clean = array();
		foreach ( $location_parts as $part ) {
			$sanitised = self::promptSafe( $part, 6 );
			if ( $sanitised !== '' ) {
				$clean[] = $sanitised;
			}
		}

		if ( empty( $clean ) ) {
			return '';
		}

		$city   = $clean[0];
		$region = $clean[1] ?? '';

		if ( $city !== '' && $region !== '' ) {
			return $city . ', ' . $region;
		}

		return $city ?: $region;
	}

	private static function append_location_to_title( $title, $location_label ) {
		if ( '' === $location_label ) {
			return $title;
		}

		if ( stripos( $title, $location_label ) !== false ) {
			return $title;
		}

		return sprintf( '%s — %s', $title, $location_label );
	}

	private static function build_seo_tail( $tone_key, array $location_parts, $service_keyword, $seo_mode, $loc_mode, $context_type ) {
		if ( ! $seo_mode || 'off' === $loc_mode ) {
			self::log_tail_decision( $context_type, '', '', false );
			return '';
		}

		$location_label = self::format_location_label( $location_parts );
		if ( '' === $location_label ) {
			self::log_tail_decision( $context_type, '', '', false );
			return '';
		}

		$service_term = strtolower( self::promptSafe( $service_keyword, 4 ) );

		$templates_with_service = array(
			'professional' => 'Ideal for projects in %s, including %s topics.',
			'friendly'     => 'Great for stories set in %s, especially about %s.',
			'casual'       => 'Perfect for %s pieces — think %s.',
			'technical'    => 'Suitable for %s use cases related to %s.',
			'neutral'      => 'Suitable for %s content on %s.',
			'fallback'     => 'Suitable for %s content on %s.',
		);

		$templates_location_only = array(
			'professional' => 'Ideal for projects in %s.',
			'friendly'     => 'Great for stories set in %s.',
			'casual'       => 'Perfect for pieces based in %s.',
			'technical'    => 'Suitable for use cases in %s.',
			'neutral'      => 'Suitable for content set in %s.',
			'fallback'     => 'Suitable for content set in %s.',
		);

		if ( '' === $service_term ) {
			$template_key = isset( $templates_location_only[ $tone_key ] ) ? $tone_key : ( isset( $templates_location_only['neutral'] ) ? 'neutral' : 'fallback' );
			$template     = $templates_location_only[ $template_key ];
			$tail         = self::ensure_sentence( sprintf( $template, $location_label ) );
			self::log_tail_decision( $context_type, $location_label, '', true );
			return $tail;
		}

		$template_key = isset( $templates_with_service[ $tone_key ] ) ? $tone_key : ( isset( $templates_with_service['neutral'] ) ? 'neutral' : 'fallback' );
		$template     = $templates_with_service[ $template_key ];
		$tail         = self::ensure_sentence( sprintf( $template, $location_label, $service_term ) );

		self::log_tail_decision( $context_type, $location_label, $service_term, true );

		return $tail;
	}

	private static function log_tail_decision( $context_type, $location_label, $service_keyword, $added ) {
		if ( ! apply_filters( 'msh_nonai_debug_logging', true ) ) {
			return;
		}

		$location_label  = $location_label !== '' ? $location_label : 'none';
		$service_keyword = $service_keyword !== '' ? $service_keyword : 'none';

		error_log( sprintf( '[NONAI] tail context=%s loc=%s svc=%s added=%d', $context_type, $location_label, $service_keyword, $added ? 1 : 0 ) );
	}

	private static function extract_location_terms( array $location_parts ) {
		$terms = array();
		$clean = array();
		foreach ( $location_parts as $part ) {
			$normalised = trim( (string) $part );
			if ( '' === $normalised ) {
				continue;
			}
			$clean[] = $normalised;
			$terms[] = $normalised;
			$terms[] = strtolower( $normalised );
		}

		$count = count( $clean );
		if ( $count >= 2 ) {
			$first_two = $clean[0] . ', ' . $clean[1];
			$terms[]   = $first_two;
			$terms[]   = strtolower( $first_two );
			$terms[]   = sanitize_title( $first_two );
			$terms[]   = sanitize_title( $clean[0] . '-' . $clean[1] );
		}

		if ( $count >= 3 ) {
			$full_csv = implode( ', ', $clean );
			$full_sp  = implode( ' ', $clean );
			$terms[]  = $full_csv;
			$terms[]  = strtolower( $full_csv );
			$terms[]  = $full_sp;
			$terms[]  = strtolower( $full_sp );
			$terms[]  = sanitize_title( $full_csv );
			$terms[]  = sanitize_title( $full_sp );
			$terms[]  = sanitize_title( implode( '-', $clean ) );
		}

		return array_values( array_unique( array_filter( $terms ) ) );
	}

	private static function build_filename_slug( array $scene, $context_type, $service_keyword, array $location, $brand_allowed, $seo_mode, $loc_mode ) {
		$parts = array();

		if ( ! empty( $scene['proper_names'] ) ) {
			$parts[] = sanitize_title( $scene['proper_names'][0] );
		} elseif ( ! empty( $scene['nouns'] ) ) {
			foreach ( array_slice( $scene['nouns'], 0, 3 ) as $noun ) {
				$parts[] = sanitize_title( $noun );
			}
		}

		if ( ! empty( $scene['time_of_day'] ) ) {
			$parts[] = sanitize_title( $scene['time_of_day'] );
		}

		if ( $context_type !== 'stock' && $context_type !== 'decorative' && $brand_allowed && $seo_mode ) {
			if ( $service_keyword !== '' ) {
				$parts[] = sanitize_title( $service_keyword );
			}
			if ( self::context_supports_force_all( $context_type ) && 'force_all' === $loc_mode && ! empty( $location ) ) {
				$parts[] = sanitize_title( $location[0] ); // city only
			}
		}

		$parts = array_unique( array_filter( $parts ) );
		if ( empty( $parts ) ) {
			return sanitize_title( 'image-' . uniqid() );
		}

		return sanitize_title( implode( '-', array_slice( $parts, 0, 4 ) ) );
	}

	private static function apply_policy_cleanups( array $metadata, array $biz, array $location, $seo_mode, $brand_allowed, $context_type, array $scene, $loc_mode ) {
		$location_terms = self::extract_location_terms( $location );
		$brand_terms    = array();

		if ( $biz['business_name'] !== '' ) {
			$brand_terms[] = $biz['business_name'];
		}

		if ( in_array( $context_type, array( 'stock', 'decorative' ), true ) ) {
			$metadata = self::strip_terms_from_metadata( $metadata, $brand_terms );

			if ( ! empty( $location_terms ) ) {
				if ( ! $seo_mode || 'off' === $loc_mode ) {
					$metadata = self::strip_terms_from_metadata( $metadata, $location_terms );
				} else {
					$metadata = self::strip_terms_from_specific_fields(
						$metadata,
						$location_terms,
						array( 'title', 'alt_text', 'caption', 'filename_slug' )
					);
				}
			}
		} else {
			if ( ! $brand_allowed ) {
				$metadata = self::strip_terms_from_metadata( $metadata, $brand_terms );
			}

			if ( ! empty( $location_terms ) ) {
				if ( ! $seo_mode || 'off' === $loc_mode ) {
					$metadata = self::strip_terms_from_metadata( $metadata, $location_terms );
				} elseif ( 'force_all' === $loc_mode && self::context_supports_force_all( $context_type ) ) {
					$metadata = self::strip_terms_from_specific_fields(
						$metadata,
						$location_terms,
						array( 'alt_text', 'caption' )
					);
				} else {
					$metadata = self::strip_terms_from_specific_fields(
						$metadata,
						$location_terms,
						array( 'title', 'alt_text', 'caption', 'filename_slug' )
					);
				}
			}
		}

		if ( ! $seo_mode && 'service-icon' !== $context_type ) {
			$metadata = self::strip_terms_from_metadata(
				$metadata,
				array(
					__( 'services', 'msh-image-optimizer' ),
					__( 'service', 'msh-image-optimizer' ),
				)
			);
		}

		if ( 'decorative' === $context_type ) {
			if ( '' === ( $metadata['title'] ?? '' ) ) {
				$metadata['title'] = __( 'Decorative Image', 'msh-image-optimizer' );
			}
			$metadata['alt_text'] = '';
			$metadata['caption']  = '';
		}

		if ( 'decorative' !== $context_type && $metadata['alt_text'] === '' ) {
			$metadata['alt_text'] = self::default_alt_text( $scene );
		}
		if ( 'decorative' !== $context_type && $metadata['caption'] === '' ) {
			$metadata['caption'] = self::default_caption( $scene );
		}
		if ( $metadata['description'] === '' ) {
			$metadata['description'] = self::default_description( $scene );
		}

		return $metadata;
	}

	private static function apply_page_context_enrichment( array $metadata, $page_hint, $context_type ) {
		if ( $page_hint === '' ) {
			return $metadata;
		}

		if ( in_array( $context_type, array( 'stock', 'decorative' ), true ) ) {
			return $metadata;
		}

		if ( strpos( $metadata['description'], $page_hint ) === false ) {
			$metadata['description'] = trim( $metadata['description'] . ' ' . $page_hint );
		}

		return $metadata;
	}

	private static function sanitize_metadata( array $metadata ) {
		foreach ( $metadata as $key => $value ) {
			if ( is_string( $value ) ) {
				$value = wp_strip_all_tags( $value );
				$value = str_replace( array( '|', '{', '}', "\r", "\n" ), ' ', $value );
				$value = preg_replace( '/\s{2,}/', ' ', $value );
				$metadata[ $key ] = trim( $value );
			}
		}
		return $metadata;
	}

	private static function apply_length_limits( array $metadata ) {
		$metadata['title']       = self::truncate( $metadata['title'], self::TITLE_LIMIT );
		$metadata['alt_text']    = self::truncate( $metadata['alt_text'], self::ALT_LIMIT );
		$metadata['caption']     = self::truncate( $metadata['caption'], self::CAPTION_LIMIT );
		$metadata['description'] = self::truncate( $metadata['description'], self::DESC_LIMIT );
		return $metadata;
	}

	private static function truncate( $text, $limit ) {
		$text = (string) $text;
		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}
		$truncated = mb_substr( $text, 0, $limit );
		$truncated = preg_replace( '/\s+\S*$/u', '', $truncated );
		if ( '' === $truncated ) {
			$truncated = mb_substr( $text, 0, $limit );
		}
		return rtrim( $truncated, ' ,.;:-' ) . '…';
	}

	private static function sentence_case( $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
	}

	private static function ensure_sentence( $text ) {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		if ( ! preg_match( '/[.!?]$/', $text ) ) {
			$text .= '.';
		}
		return $text;
	}

	private static function strip_terms_from_metadata( array $metadata, array $terms ) {
		if ( empty( $terms ) ) {
			return $metadata;
		}
		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			$metadata[ $key ] = self::strip_terms_from_field( $value, $terms );
		}
		return $metadata;
	}

	private static function strip_terms_from_field( $value, array $terms ) {
		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( '' === $term ) {
				continue;
			}
			$pattern = '/\b' . preg_quote( $term, '/' ) . '\b/iu';
			$value   = preg_replace( $pattern, '', $value );
		}
		$value = preg_replace( '/\s{2,}/', ' ', $value );
		return trim( $value );
	}

	private static function strip_terms_from_specific_fields( array $metadata, array $terms, array $fields ) {
		if ( empty( $terms ) ) {
			return $metadata;
		}
		foreach ( $fields as $field ) {
			if ( isset( $metadata[ $field ] ) && is_string( $metadata[ $field ] ) ) {
				$metadata[ $field ] = self::strip_terms_from_field( $metadata[ $field ], $terms );
			}
		}
		return $metadata;
	}

	private static function default_alt_text( array $scene ) {
		$desc = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		return sprintf( __( 'Scene featuring %s.', 'msh-image-optimizer' ), $desc );
	}

	private static function unique_value_summary( array $biz, $seo_mode, $context_type ) {
		if ( ! $seo_mode ) {
			return '';
		}

		$context_type = sanitize_key( (string) $context_type );
		if ( ! function_exists( 'msh_ct_allows_uvp' ) || ! msh_ct_allows_uvp( $context_type ) ) {
			return '';
		}

		$unique_value = isset( $biz['unique_value'] ) ? $biz['unique_value'] : '';
		if ( '' === $unique_value ) {
			return '';
		}

		if ( function_exists( 'msh_clamp_uvp' ) ) {
			return msh_clamp_uvp( $unique_value, 120 );
		}

		return self::summarise_unique_value( $unique_value );
	}

	private static function summarise_unique_value( $value, $max_chars = 120 ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = preg_replace( '/\s{2,}/', ' ', trim( $value ) );
		if ( '' === $value ) {
			return '';
		}

		$sentences = preg_split( '/[.!?]+/u', $value, 2 );
		$sentence  = trim( $sentences[0] ?? '' );
		if ( '' === $sentence ) {
			$sentence = $value;
		}

		if ( mb_strlen( $sentence ) > $max_chars && false !== strpos( $sentence, ',' ) ) {
			$parts = explode( ',', $sentence );
			if ( ! empty( $parts ) ) {
				$sentence = trim( $parts[0] );
			}
		}

		if ( mb_strlen( $sentence ) > $max_chars ) {
			$sentence = mb_substr( $sentence, 0, $max_chars );
			$sentence = preg_replace( '/\s+\S*$/u', '', $sentence );
		}

		$sentence = trim( preg_replace( '/\s{2,}/', ' ', $sentence ) );

		if ( '' === $sentence ) {
			return '';
		}

		return rtrim( $sentence, ',;:.- ' );
	}

	private static function merge_sentence_with_uvp( $primary_sentence, $summary ) {
		$primary_sentence = trim( (string) $primary_sentence );
		$summary          = trim( (string) $summary );

		if ( '' === $primary_sentence ) {
			return self::ensure_sentence( $summary );
		}

		if ( '' === $summary ) {
			return self::ensure_sentence( $primary_sentence );
		}

		$primary_core = rtrim( $primary_sentence, ".!? " );
		$summary_core = rtrim( $summary, ".!? " );

		if ( '' === $summary_core ) {
			return self::ensure_sentence( $primary_core );
		}

		$summary_core = self::lowercase_first( $summary_core );

		return self::ensure_sentence( sprintf( '%s, featuring %s', $primary_core, $summary_core ) );
	}

	private static function append_sentence_with_limit( $text, $sentence, $max_sentences = 2 ) {
		$text     = trim( (string) $text );
		$sentence = trim( (string) $sentence );

		if ( '' === $sentence ) {
			return $text;
		}

		$sentence = self::ensure_sentence( $sentence );
		$current  = self::sentence_count( $text );

		if ( $current >= $max_sentences ) {
			return $text;
		}

		if ( '' === $text ) {
			return $sentence;
		}

		if ( ( $current + 1 ) > $max_sentences ) {
			return $text;
		}

		return trim( $text . ' ' . $sentence );
	}

	private static function sentence_count( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}

		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text );
		$sentences = array_filter(
			array_map(
				static function ( $sentence ) {
					return trim( (string) $sentence );
				},
				(array) $sentences
			)
		);

		return count( $sentences );
	}

	private static function lowercase_first( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}

		$first = mb_substr( $text, 0, 1 );
		$rest  = mb_substr( $text, 1 );

		return mb_strtolower( $first ) . $rest;
	}

	private static function default_caption( array $scene ) {
		$desc = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		return sprintf( __( 'Visual highlighting %s.', 'msh-image-optimizer' ), $desc );
	}

	private static function default_description( array $scene ) {
		$desc = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		return sprintf( __( 'Scene highlighting %s with balanced composition.', 'msh-image-optimizer' ), $desc );
	}

	private static function promptSafe( $value, $max_words = 12 ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = str_replace( array( '|', '{', '}', "\n", "\r" ), ' ', $value );
		$value = preg_replace( '/\s{2,}/', ' ', trim( $value ) );
		if ( $value === '' ) {
			return '';
		}

		$words = preg_split( '/\s+/', $value );
		$words = array_slice( $words, 0, max( 1, (int) $max_words ) );
		return implode( ' ', $words );
	}

	private static function trigrams( $text ) {
		$text = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', $text ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );
		if ( mb_strlen( $text ) < 3 ) {
			return array();
		}
		$grams = array();
		for ( $i = 0; $i <= mb_strlen( $text ) - 3; $i++ ) {
			$grams[] = mb_substr( $text, $i, 3 );
		}
		return array_unique( $grams );
	}

	private static function jaccard( array $set_a, array $set_b ) {
		if ( empty( $set_a ) || empty( $set_b ) ) {
			return 0.0;
		}
		$intersection = array_intersect( $set_a, $set_b );
		$union        = array_unique( array_merge( $set_a, $set_b ) );
		if ( empty( $union ) ) {
			return 0.0;
		}
		return count( $intersection ) / count( $union );
	}

	private static function is_unique( array $metadata ) {
		$title_grams = self::trigrams( $metadata['title'] ?? '' );
		$alt_grams   = self::trigrams( $metadata['alt_text'] ?? '' );

		foreach ( self::$recent_outputs as $recent ) {
			$recent_title_grams = self::trigrams( $recent['title'] ?? '' );
			$recent_alt_grams   = self::trigrams( $recent['alt_text'] ?? '' );

			$title_score = self::jaccard( $title_grams, $recent_title_grams );
			$alt_score   = self::jaccard( $alt_grams, $recent_alt_grams );

			if ( $title_score >= 0.8 && $alt_score >= 0.8 ) {
				return false;
			}
		}

		return true;
	}

	private static function remember_output( array $metadata ) {
		self::$recent_outputs[] = array(
			'title'    => $metadata['title'] ?? '',
			'alt_text' => $metadata['alt_text'] ?? '',
		);

		if ( count( self::$recent_outputs ) > self::MEMORY_SIZE ) {
			self::$recent_outputs = array_slice( self::$recent_outputs, -1 * self::MEMORY_SIZE );
		}
	}
}
