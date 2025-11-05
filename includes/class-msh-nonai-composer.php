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

		$seed        = self::seed( $id, $filename );
		$scene       = MSH_NonAI_Scene::extract( $filename, $id );
		$keywords    = MSH_NonAI_Scene::get_keywords( $scene );
		$tone_key    = self::resolve_tone_key( $biz['brand_voice'] );
		$location    = self::collect_location_parts( $biz );
		$service_kw  = self::pick_service_keyword( $biz, $seed );
		$page_hint   = self::build_page_hint( $page, $tone_key, $seed );
		$brand_allow = self::brand_permitted( $context, $seo_mode, $brand_flag, $manual );

		$attempts = 0;
		do {
			$variant_seed = $seed + $attempts;
			$metadata     = self::generate_for_context(
				$context,
				$scene,
				$biz,
				$page,
				$variant_seed,
				$brand_allow,
				$seo_mode,
				$tone_key,
				$location,
				$service_kw,
				$page_hint
			);

			$metadata['keywords']      = $keywords;
			$metadata['filename_slug'] = self::build_filename_slug( $scene, $context, $service_kw, $location, $brand_allow, $seo_mode );
			$metadata                  = self::apply_policy_cleanups( $metadata, $biz, $location, $seo_mode, $brand_allow, $context, $scene );
			$metadata                  = self::apply_page_context_enrichment( $metadata, $page_hint );
			$metadata                  = self::sanitize_metadata( $metadata );
			$metadata                  = self::apply_length_limits( $metadata );
			$attempts ++;
		} while ( ! self::is_unique( $metadata ) && $attempts < 3 );

		self::remember_output( $metadata );

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
		$page_hint
	) {
		switch ( $context_type ) {
			case 'facility':
				return self::generate_facility( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword, $page_hint );

			case 'team':
				return self::generate_team( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword );

			case 'equipment':
				return self::generate_equipment( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword );

			case 'testimonial':
				return self::generate_testimonial( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location );

			case 'clinical':
			case 'business':
				return self::generate_business( $scene, $biz, $seed, $brand_allowed, $seo_mode, $tone_key, $location, $service_keyword );

			case 'decorative':
				return self::generate_decorative();

			case 'stock':
			default:
				return self::generate_stock( $scene, $seed, $tone_key, $seo_mode, $location, $service_keyword );
		}
	}

	private static function generate_stock( array $scene, $seed, $tone_key, $seo_mode = false, array $location = array(), $service_keyword = '' ) {
		$primary_subject   = self::stock_primary_subject( $scene );
		$secondary_subject = self::stock_secondary_subject( $scene, $primary_subject );
		$verb              = MSH_Phrasebank::get_verb( strtolower( $secondary_subject ?: $primary_subject ), $seed + 1 );
		$time_phrase       = MSH_Phrasebank::get_time_phrase( $scene['time_of_day'] ?? '', $seed + 2 );
		$mood              = self::pick_tone_word( $tone_key, 'mood', $seed + 3, array( MSH_Phrasebank::get_mood( $seed + 3 ) ) );
		$light             = MSH_Phrasebank::get_light( $seed + 4 );
		$composition       = MSH_Phrasebank::get_composition( $seed + 5 );
		$elements          = MSH_Phrasebank::get_elements( $seed + 6 );

		$title_parts = array(
			self::sentence_case( $primary_subject ),
		);

		if ( $secondary_subject ) {
			$title_parts[] = strtolower( $verb );
			$title_parts[] = $secondary_subject;
		}

		if ( $time_phrase ) {
			$title_parts[] = $time_phrase;
		}

		$title = self::sentence_case( implode( ' ', array_filter( $title_parts ) ) );

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

		$caption_focus = $secondary_subject ?
			sprintf(
				'%s %s %s',
				$primary_subject,
				strtolower( $verb ),
				$secondary_subject
			) :
			$primary_subject;

		$caption = sprintf(
			'%s %s under %s, conveying a %s mood.',
			$composition,
			$caption_focus,
			strtolower( $light ),
			$mood
		);

		$description_parts = array();

		$scene_sentence_parts = array(
			self::sentence_case( $primary_subject ),
		);

		if ( $secondary_subject ) {
			$scene_sentence_parts[] = strtolower( $verb );
			$scene_sentence_parts[] = $secondary_subject;
		}

		if ( $time_phrase ) {
			$scene_sentence_parts[] = $time_phrase;
		}

		$description_parts[] = self::ensure_sentence( implode( ' ', array_filter( $scene_sentence_parts ) ) );

		$description_parts[] = self::ensure_sentence(
			sprintf(
				'%s %s, highlighting %s while maintaining a %s atmosphere.',
				$composition,
				strtolower( $light ),
				$elements,
				$mood
			)
		);

		if ( $seo_mode ) {
			$location_tail = self::build_stock_location_tail( $location );
			if ( $location_tail !== '' ) {
				$description_parts[] = $location_tail;
			}

			$service_tail = self::build_stock_service_tail( $service_keyword );
			if ( $service_tail !== '' ) {
				$description_parts[] = $service_tail;
			}
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
			'title'       => '',
			'alt_text'    => '',
			'caption'     => '',
			'description' => __( 'Decorative image with no descriptive metadata required.', 'msh-image-optimizer' ),
		);
	}

	private static function generate_facility( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword, $page_hint ) {
		$scene_desc      = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		$facility_adj    = self::pick_tone_word( $tone_key, 'facility_adj', $seed, array( 'modern', 'specialised' ) );
		$industry        = $biz['industry'] ?: __( 'healthcare', 'msh-image-optimizer' );
		$location_phrase = $seo_mode ? self::build_location_phrase( $location ) : '';
		$service_phrase  = ( $seo_mode && $service_keyword ) ? self::build_service_phrase( $service_keyword ) : '';
		$brand_name      = $brand_allowed && $biz['business_name'] !== '' ? $biz['business_name'] : '';

		if ( $brand_name ) {
			$title_options = array(
				sprintf( '%s %s Facility', $brand_name, ucfirst( $facility_adj ) ),
				sprintf( '%s Clinic', $brand_name ),
			);

			if ( $location_phrase ) {
				$title_options[] = sprintf( '%s Facility - %s', $brand_name, $location_phrase );
			}

			$title = self::pick_variant( $title_options, $seed );

			$alt = sprintf(
				'Interior of %s %s facility%s.',
				$brand_name,
				$industry,
				$location_phrase ? ' in ' . $location_phrase : ''
			);

			$caption = sprintf(
				'%s care delivered at %s%s.',
				ucfirst( $industry ),
				$brand_name,
				$location_phrase ? ' in ' . $location_phrase : ''
			);

			$description_parts = array(
				self::ensure_sentence(
					sprintf(
						'%s facility highlighting %s.',
						ucfirst( $facility_adj ),
						$scene_desc
					)
				),
				self::ensure_sentence(
					sprintf(
						'%s provides %s.',
						$brand_name,
						$service_phrase ?: sprintf( __( '%s care programs', 'msh-image-optimizer' ), $industry )
					)
				),
			);

			if ( $location_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Located in %s.', 'msh-image-optimizer' ),
						$location_phrase
					)
				);
			}
		} else {
			$title = sprintf( '%s Facility Interior', ucfirst( $industry ) );
			$alt   = sprintf( '%s facility interior featuring %s.', ucfirst( $industry ), $scene_desc );
			$caption = sprintf(
				'Professional %s facility interior.',
				$industry
			);

			$description_parts = array(
				self::ensure_sentence(
					sprintf(
						'%s interior highlighting %s.',
						ucfirst( $industry ),
						$scene_desc
					)
				),
				__( 'Designed for patient comfort and efficient care.', 'msh-image-optimizer' ),
			);
		}

		if ( $biz['unique_value'] !== '' ) {
			$description_parts[] = self::ensure_sentence( $biz['unique_value'] );
		}

		if ( $page_hint ) {
			$description_parts[] = $page_hint;
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => implode( ' ', array_filter( $description_parts ) ),
		);
	}

	private static function generate_team( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword ) {
		$team_adj        = self::pick_tone_word( $tone_key, 'team_adj', $seed, array( 'dedicated', 'skilled' ) );
		$industry        = $biz['industry'] ?: __( 'professional', 'msh-image-optimizer' );
		$scene_desc      = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		$service_phrase  = ( $seo_mode && $service_keyword ) ? self::build_service_phrase( $service_keyword ) : '';
		$location_phrase = $seo_mode ? self::build_location_phrase( $location ) : '';
		$brand_name      = $brand_allowed && $biz['business_name'] !== '' ? $biz['business_name'] : '';

		if ( $brand_name ) {
			$title = sprintf( '%s Team at %s', ucfirst( $team_adj ), $brand_name );
			$alt   = sprintf(
				'%s team members at %s.',
				ucfirst( $industry ),
				$brand_name
			);
			$caption = sprintf(
				'%s team supporting %s.',
				ucfirst( $team_adj ),
				$service_phrase ?: __( 'client care', 'msh-image-optimizer' )
			);

			$description_parts = array(
				self::ensure_sentence(
					sprintf(
						'The %s team at %s collaborates on %s.',
						$team_adj,
						$brand_name,
						$scene_desc
					)
				),
			);

			if ( $service_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Special focus on %s.', 'msh-image-optimizer' ),
						$service_phrase
					)
				);
			}

			if ( $location_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Serving clients in %s.', 'msh-image-optimizer' ),
						$location_phrase
					)
				);
			}

			$description = implode( ' ', $description_parts );
		} else {
			$title = sprintf( '%s Team', ucfirst( $industry ) );
			$alt   = sprintf( '%s team members working together.', ucfirst( $industry ) );
			$caption = sprintf(
				'%s team working collaboratively.',
				ucfirst( $industry )
			);
			$description = sprintf(
				'%s professionals collaborating to support clients.',
				ucfirst( $industry )
			);
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => $description,
		);
	}

	private static function generate_equipment( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword ) {
		$equipment_adj   = self::pick_tone_word( $tone_key, 'equipment_adj', $seed, array( 'advanced', 'specialist' ) );
		$scene_desc      = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		$industry        = $biz['industry'] ?: __( 'professional', 'msh-image-optimizer' );
		$location_phrase = $seo_mode ? self::build_location_phrase( $location ) : '';
		$service_phrase  = ( $seo_mode && $service_keyword ) ? self::build_service_phrase( $service_keyword ) : '';
		$brand_name      = $brand_allowed && $biz['business_name'] !== '' ? $biz['business_name'] : '';

		if ( $brand_name ) {
			$title = sprintf( '%s Equipment - %s', ucfirst( $equipment_adj ), $brand_name );
			$alt   = sprintf(
				'%s equipment at %s.',
				ucfirst( $industry ),
				$brand_name
			);
			$caption = sprintf(
				'%s equipment ready for patient care at %s.',
				ucfirst( $equipment_adj ),
				$brand_name
			);
			$description_parts = array(
				self::ensure_sentence(
					sprintf(
						'%s equipment highlighting %s.',
						ucfirst( $equipment_adj ),
						$scene_desc
					)
				),
			);
			if ( $location_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Located in %s.', 'msh-image-optimizer' ),
						$location_phrase
					)
				);
			}
			if ( $service_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Supports %s.', 'msh-image-optimizer' ),
						$service_phrase
					)
				);
			}
			if ( $biz['unique_value'] !== '' ) {
				$description_parts[] = self::ensure_sentence( $biz['unique_value'] );
			}
			$description = implode( ' ', $description_parts );
		} else {
			$title = sprintf( '%s Equipment', ucfirst( $equipment_adj ) );
			$alt   = sprintf( '%s equipment set up for service delivery.', ucfirst( $industry ) );
			$caption = sprintf(
				'%s equipment ready for use.',
				ucfirst( $industry )
			);
			$description = sprintf(
				'%s equipment highlighting %s ready for client care.',
				ucfirst( $equipment_adj ),
				$scene_desc
			);
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => $description,
		);
	}

	private static function generate_testimonial( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location ) {
		$scene_desc      = strtolower( MSH_NonAI_Scene::describe( $scene ) );
		$location_phrase = $seo_mode ? self::build_location_phrase( $location ) : '';
		$testimonial_adj = self::pick_tone_word( $tone_key, 'testimonial_adj', $seed, array( 'patient-focused', 'client-focused' ) );
		$brand_name      = $brand_allowed && $biz['business_name'] !== '' ? $biz['business_name'] : '';

		if ( $brand_name ) {
			$title = sprintf( 'Success Story - %s', $brand_name );
			if ( $location_phrase ) {
				$title = sprintf( 'Success Story - %s %s', $brand_name, $location_phrase );
			}
			$alt = sprintf(
				'%s testimonial at %s%s.',
				ucfirst( $testimonial_adj ),
				$brand_name,
				$location_phrase ? ' in ' . $location_phrase : ''
			);
			$caption = sprintf(
				'%s shares their experience with %s.',
				ucfirst( $testimonial_adj ),
				$brand_name
			);
			$description_parts = array(
				self::ensure_sentence(
					sprintf(
						'%s testimonial referencing %s and the care provided by %s.',
						ucfirst( $testimonial_adj ),
						$scene_desc,
						$brand_name
					)
				),
			);
			if ( $location_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Based in %s.', 'msh-image-optimizer' ),
						$location_phrase
					)
				);
			}
			$description = implode( ' ', $description_parts );
		} else {
			$title = __( 'Success Story', 'msh-image-optimizer' );
			$alt   = __( 'Client testimonial describing their experience.', 'msh-image-optimizer' );
			$caption = __( 'Client shares a positive experience.', 'msh-image-optimizer' );
			$description = sprintf(
				'%s testimonial highlighting %s and the client journey.',
				ucfirst( $testimonial_adj ),
				$scene_desc
			);
		}

		return array(
			'title'       => $title,
			'alt_text'    => self::ensure_sentence( $alt ),
			'caption'     => self::ensure_sentence( $caption ),
			'description' => $description,
		);
	}

	private static function generate_business( array $scene, array $biz, $seed, $brand_allowed, $seo_mode, $tone_key, array $location, $service_keyword ) {
		$scene_desc      = MSH_NonAI_Scene::describe( $scene );
		$scene_lower     = strtolower( $scene_desc );
		$business_adj    = self::pick_tone_word( $tone_key, 'business_adj', $seed, array( 'professional', 'specialist' ) );
		$location_phrase = $seo_mode ? self::build_location_phrase( $location ) : '';
		$service_phrase  = ( $seo_mode && $service_keyword ) ? self::build_service_phrase( $service_keyword ) : '';
		$brand_name      = $brand_allowed && $biz['business_name'] !== '' ? $biz['business_name'] : '';

		if ( $brand_name ) {
			$title_parts = array( $scene_desc, '-', $brand_name );
			if ( $location_phrase ) {
				$title_parts[] = $location_phrase;
			}
			$title = trim( implode( ' ', array_filter( $title_parts ) ) );

			$alt = sprintf(
				'%s scene at %s.',
				ucfirst( $business_adj ),
				$brand_name
			);
			$caption = sprintf(
				'%s visual from %s.',
				ucfirst( $business_adj ),
				$brand_name
			);

			$description_parts = array(
				self::ensure_sentence(
					sprintf(
						'%s visual highlighting %s.',
						ucfirst( $business_adj ),
						$scene_lower
					)
				),
			);
			if ( $location_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Serving clients in %s.', 'msh-image-optimizer' ),
						$location_phrase
					)
				);
			}
			if ( $service_phrase ) {
				$description_parts[] = self::ensure_sentence(
					sprintf(
						__( 'Provides %s.', 'msh-image-optimizer' ),
						$service_phrase
					)
				);
			}
			if ( $biz['unique_value'] !== '' ) {
				$description_parts[] = self::ensure_sentence( $biz['unique_value'] );
			}
			$description = implode( ' ', $description_parts );
		} else {
			$title = $scene_desc;
			$alt   = sprintf( '%s scene supporting professional services.', ucfirst( $business_adj ) );
			$caption = sprintf(
				'%s visual supporting service delivery.',
				ucfirst( $business_adj )
			);
			$description = sprintf(
				'%s visual featuring %s and demonstrating service quality.',
				ucfirst( $business_adj ),
				$scene_lower
			);
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

	private static function build_location_phrase( array $parts ) {
		if ( empty( $parts ) ) {
			return '';
		}
		return implode( ', ', array_unique( array_filter( $parts ) ) );
	}

	private static function pick_service_keyword( array $biz, $seed ) {
		if ( empty( $biz['service_keywords'] ) ) {
			return '';
		}
		return self::pick_variant( $biz['service_keywords'], $seed );
	}

	private static function build_service_phrase( $keyword ) {
		if ( $keyword === '' ) {
			return '';
		}
		$keyword = sanitize_text_field( $keyword );
		return sprintf( __( '%s services', 'msh-image-optimizer' ), $keyword );
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
		if ( ! $seo_mode ) {
			return false;
		}

		$always_allowed = array( 'logo', 'team', 'facility', 'equipment' );
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

	private static function build_filename_slug( array $scene, $context_type, $service_keyword, array $location, $brand_allowed, $seo_mode ) {
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
			if ( ! empty( $location ) ) {
				$parts[] = sanitize_title( $location[0] ); // city only
			}
		}

		$parts = array_unique( array_filter( $parts ) );
		if ( empty( $parts ) ) {
			return sanitize_title( 'image-' . uniqid() );
		}

		return sanitize_title( implode( '-', array_slice( $parts, 0, 4 ) ) );
	}

	private static function apply_policy_cleanups( array $metadata, array $biz, array $location, $seo_mode, $brand_allowed, $context_type, array $scene ) {
		$location_terms = array_filter( $location );
		$brand_terms    = array();
		if ( $biz['business_name'] !== '' ) {
			$brand_terms[] = $biz['business_name'];
		}

		// Never allow brand/location for stock/decorative.
		if ( in_array( $context_type, array( 'stock', 'decorative' ), true ) ) {
			// Stock/Decorative: never allow brand. Allow location in description only when SEO enabled.
			$metadata = self::strip_terms_from_metadata( $metadata, $brand_terms );

			if ( $seo_mode ) {
				if ( ! empty( $location_terms ) ) {
					$metadata = self::strip_terms_from_specific_fields( $metadata, $location_terms, array( 'title', 'alt_text', 'caption', 'filename_slug' ) );
				}
			} else {
				$metadata = self::strip_terms_from_metadata( $metadata, $location_terms );
			}
		} elseif ( ! $brand_allowed || ! $seo_mode ) {
			$strip_terms = $brand_terms;
			if ( ! $seo_mode ) {
				$strip_terms = array_merge( $strip_terms, $location_terms );
			}
			$metadata = self::strip_terms_from_metadata( $metadata, $strip_terms );
		}

		if ( ! $seo_mode ) {
			// Remove any service mentions when SEO disabled.
			$metadata = self::strip_terms_from_metadata( $metadata, array( __( 'services', 'msh-image-optimizer' ), __( 'service', 'msh-image-optimizer' ) ) );
		}

		// Decorative images must remain blank.
		if ( 'decorative' === $context_type ) {
			$metadata['title']       = '';
			$metadata['alt_text']    = '';
		}

		// Ensure fields have fallbacks after stripping.
		if ( $metadata['alt_text'] === '' ) {
			$metadata['alt_text'] = self::default_alt_text( $scene );
		}
		if ( $metadata['caption'] === '' ) {
			$metadata['caption'] = self::default_caption( $scene );
		}
		if ( $metadata['description'] === '' ) {
			$metadata['description'] = self::default_description( $scene );
		}

		return $metadata;
	}

	private static function apply_page_context_enrichment( array $metadata, $page_hint ) {
		if ( $page_hint === '' ) {
			return $metadata;
		}

		if ( strpos( $metadata['description'], $page_hint ) === false ) {
			$metadata['description'] = trim( $metadata['description'] . ' ' . $page_hint );
		}

		return $metadata;
	}

	private static function build_stock_location_tail( array $location_parts ) {
		if ( empty( $location_parts ) ) {
			return '';
		}

		$safe_parts = array();
		foreach ( $location_parts as $part ) {
			$clean = self::promptSafe( $part, 6 );
			if ( $clean !== '' ) {
				$safe_parts[] = $clean;
			}
		}

		if ( empty( $safe_parts ) ) {
			return '';
		}

		$phrase = implode( ', ', array_slice( $safe_parts, 0, 2 ) );
		return sprintf( __( 'Ideal for projects in %s.', 'msh-image-optimizer' ), $phrase );
	}

	private static function build_stock_service_tail( $service_keyword ) {
		$service_keyword = self::promptSafe( $service_keyword, 6 );
		if ( $service_keyword === '' ) {
			return '';
		}

		return sprintf( __( 'Complements content about %s.', 'msh-image-optimizer' ), $service_keyword );
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
