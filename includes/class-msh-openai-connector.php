<?php
/**
 * OpenAI Vision API Connector
 *
 * Integrates OpenAI GPT-4 Vision to analyze images and generate metadata.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_OpenAI_Connector {

	/**
	 * OpenAI API endpoint
	 */
	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Prompt version for tracking changes
	 * Format: YYYYMMDD.revision
	 */
	const PROMPT_VERSION = '20251030.7'; // SEO mode overrides stock restrictions - allows location/service keywords for stock images

	/**
	 * Business-related context types that allow brand name in metadata
	 * Can be filtered via 'msh_business_related_types' for multi-tenant customization
	 */
	private $business_related_types = array(
		'brand_logo',
		'team',
		'facility',
		'equipment',
		'testimonial',
		'clinical',
		'business',
	);

	/**
	 * Constructor.
	 *
	 * Registers the OpenAI connector with the AI metadata filter.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'msh_ai_generate_metadata', array( $this, 'generate_metadata_via_openai' ), 10, 3 );
	}

	/**
	 * Batch generate metadata for multiple images in parallel.
	 *
	 * Uses curl_multi for concurrent API requests. Significantly faster than sequential processing.
	 *
	 * @param array $payloads Array of payloads, keyed by attachment_id.
	 * @param int   $concurrency Maximum concurrent requests (default: 3).
	 * @return array Results keyed by attachment_id.
	 */
	public function batch_generate_metadata_parallel( $payloads, $concurrency = 3 ) {
		if ( empty( $payloads ) || ! class_exists( 'MSH_Concurrent_Queue' ) ) {
			return array();
		}

		$t_start = microtime( true );
		$queue   = new MSH_Concurrent_Queue( $concurrency );
		$results = array();

		// Get API key (same for all requests in batch)
		$first_payload = reset( $payloads );
		$api_key       = ! empty( $first_payload['api_key'] ) ? $first_payload['api_key'] : get_option( 'msh_ai_api_key', '' );

		// For bundled access mode, use platform key
		if ( empty( $api_key ) && ! empty( $first_payload['access_mode'] ) && $first_payload['access_mode'] === 'bundled' ) {
			$api_key = defined( 'MSH_PLATFORM_OPENAI_KEY' ) ? MSH_PLATFORM_OPENAI_KEY : '';
		}

		if ( empty( $api_key ) ) {
			error_log( '[MSH OpenAI Batch] No API key available' );
			return array();
		}

		// Queue all requests
		foreach ( $payloads as $attachment_id => $payload ) {
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				continue;
			}

			// Build prompt messages
			$context       = $payload['context'];
			$business_name = ! empty( $context['business_name'] ) ? $context['business_name'] : 'this business';
			$industry      = ! empty( $context['industry_label'] ) ? $context['industry_label'] : 'professional services';

			$location_parts = array();
			if ( ! empty( $context['city'] ) ) {
				$location_parts[] = $context['city'];
			}
			if ( ! empty( $context['country'] ) ) {
				$location_parts[] = $context['country'];
			}
			$location = implode( ', ', $location_parts );
			$uvp      = ! empty( $context['uvp'] ) ? $context['uvp'] : '';

			$features          = ! empty( $payload['features'] ) ? $payload['features'] : array();
			$ai_options        = ! empty( $payload['ai_options'] ) ? $payload['ai_options'] : array();
			$language_choice   = isset( $ai_options['language'] ) ? strtolower( (string) $ai_options['language'] ) : 'auto';
			$resolved_language = $this->normalize_language_choice( $language_choice, $ai_options, $context );

			$messages = $this->build_prompt_messages(
				$attachment_id,
				$image_url,
				$business_name,
				$industry,
				$location,
				$uvp,
				$context,
				$features,
				$resolved_language
			);

			// Build request body
			$body = array(
				'model'       => 'gpt-4o',
				'messages'    => $messages,
				'max_tokens'  => 500,
				'temperature' => 0,
			);

			// Add to queue
			$queue->add(
				(string) $attachment_id,
				self::API_ENDPOINT,
				array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				wp_json_encode( $body ),
				15 // 15 second timeout per request
			);
		}

		error_log( sprintf( '[MSH OpenAI Batch] Queued %d requests with concurrency=%d', count( $payloads ), $concurrency ) );

		// Execute all requests in parallel
		$raw_results = $queue->execute();

		// Process results
		foreach ( $raw_results as $attachment_id => $result ) {
			if ( ! $result['success'] ) {
				error_log( sprintf( '[MSH OpenAI Batch] Failed for attachment %d: %s', $attachment_id, $result['error'] ) );
				$results[ $attachment_id ] = null;
				continue;
			}

			// Parse response
			$payload          = $payloads[ $attachment_id ];
			$parsed_metadata = $this->parse_openai_response( $result['response'], $payload['context'] );

			if ( $parsed_metadata ) {
				$results[ $attachment_id ] = $parsed_metadata;
			} else {
				$results[ $attachment_id ] = null;
			}
		}

		$duration = microtime( true ) - $t_start;
		error_log( sprintf(
			'[MSH OpenAI Batch] Completed %d images in %.2fs (%.2fs/image)',
			count( $results ),
			$duration,
			count( $results ) > 0 ? $duration / count( $results ) : 0
		) );

		return $results;
	}

	/**
	 * Generate metadata using the OpenAI Vision API.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null                    $metadata  Existing metadata from earlier filters.
	 * @param array                         $payload   Request payload from the AI service.
	 * @param MSH_Contextual_Meta_Generator $generator Generator instance.
	 * @return array|null Sanitised metadata array or null on failure.
	 */
	public function generate_metadata_via_openai( $metadata, $payload, $generator ) {
		// If another filter already provided metadata, don't override
		if ( is_array( $metadata ) && ! empty( $metadata ) ) {
			return $metadata;
		}

		// Get API key
		// Priority: 1) Payload API key (BYOK), 2) Option API key (BYOK), 3) Platform key for bundled credits
		$api_key = ! empty( $payload['api_key'] ) ? $payload['api_key'] : get_option( 'msh_ai_api_key', '' );

		// For bundled access mode, use platform key from wp-config.php
		if ( empty( $api_key ) && ! empty( $payload['access_mode'] ) && $payload['access_mode'] === 'bundled' ) {
			$api_key = defined( 'MSH_PLATFORM_OPENAI_KEY' ) ? MSH_PLATFORM_OPENAI_KEY : '';
			if ( ! empty( $api_key ) ) {
				error_log( '[MSH OpenAI] Using platform API key for bundled access' );
			}
		}

		if ( empty( $api_key ) ) {
			error_log( '[MSH OpenAI] No API key available' );
			return null;
		}

		// Get image URL
		$attachment_id = $payload['attachment_id'];
		$image_url     = wp_get_attachment_url( $attachment_id );

		if ( ! $image_url ) {
			error_log( '[MSH OpenAI] Could not get image URL for attachment ' . $attachment_id );
			return null;
		}

		// Get business context
		$context       = $payload['context'];
		$business_name = ! empty( $context['business_name'] ) ? $context['business_name'] : 'this business';
		$industry      = ! empty( $context['industry_label'] ) ? $context['industry_label'] : 'professional services';

		// Build location from city, region, country
		$location_parts = array();
		if ( ! empty( $context['city'] ) ) {
			$location_parts[] = $context['city'];
		}
		if ( ! empty( $context['country'] ) ) {
			$location_parts[] = $context['country'];
		}
		$location = implode( ', ', $location_parts );

		$uvp = ! empty( $context['uvp'] ) ? $context['uvp'] : '';

		// Build AI prompt with enabled features
		$features          = ! empty( $payload['features'] ) ? $payload['features'] : array();
		$ai_options        = ! empty( $payload['ai_options'] ) ? $payload['ai_options'] : array();
		$language_choice   = isset( $ai_options['language'] ) ? strtolower( (string) $ai_options['language'] ) : 'auto';
		$resolved_language = $this->normalize_language_choice( $language_choice, $ai_options, $context );

		$messages = $this->build_prompt_messages(
			$attachment_id,
			$image_url,
			$business_name,
			$industry,
			$location,
			$uvp,
			$context,
			$features,
			$resolved_language
		);

		// DIAGNOSTIC: Log AI call
		error_log( sprintf(
			'[AI_CALL] #%d url=%s model=gpt-4o',
			$attachment_id,
			substr( $image_url, 0, 80 ) . ( strlen( $image_url ) > 80 ? '...' : '' )
		) );

		// Call OpenAI Vision API with new message structure
		$response = $this->call_openai_vision( $image_url, $messages, $api_key );

		if ( is_wp_error( $response ) ) {
			error_log( '[MSH OpenAI] API Error: ' . $response->get_error_message() );
			return null;
		}

		// Extract token usage from response for future token tracking
		$response_data = json_decode( $response, true );
		if ( isset( $response_data['usage'] ) ) {
			$tokens_used = array(
				'prompt_tokens'     => $response_data['usage']['prompt_tokens'] ?? 0,
				'completion_tokens' => $response_data['usage']['completion_tokens'] ?? 0,
				'total_tokens'      => $response_data['usage']['total_tokens'] ?? 0,
			);

			error_log( sprintf(
				'[MSH OpenAI] Token usage - prompt: %d, completion: %d, total: %d',
				$tokens_used['prompt_tokens'],
				$tokens_used['completion_tokens'],
				$tokens_used['total_tokens']
			) );

			// DIAGNOSTIC: Log AI response success
			error_log( sprintf(
				'[AI_RESP] #%d ok=1 tokens=%d/%d/%d',
				$attachment_id,
				$tokens_used['prompt_tokens'],
				$tokens_used['completion_tokens'],
				$tokens_used['total_tokens']
			) );

			// Future: Deduct from token manager when class exists
			// if ( class_exists( 'MSH_Token_Manager' ) ) {
			//     $token_manager = MSH_Token_Manager::get_instance();
			//     $token_manager->deduct( $attachment_id, $tokens_used['total_tokens'], 'vision_metadata' );
			// }

			// Log to telemetry system (integrated with MSH_Telemetry)
			do_action( 'msh_log_token_usage', $attachment_id, $tokens_used, self::PROMPT_VERSION );
		}

		// Parse response into metadata structure
		$parsed_metadata = $this->parse_openai_response( $response, $context );

	// CRITICAL FIX: Check if parse returned WP_Error (from validator)
	if ( is_wp_error( $parsed_metadata ) ) {
		error_log( sprintf(
			'[MSH OpenAI] Validation error: %s (code: %s)',
			$parsed_metadata->get_error_message(),
			$parsed_metadata->get_error_code()
		) );
		return null; // Return null to trigger fallback to heuristic metadata
	}

		if ( empty( $parsed_metadata ) ) {
			error_log( '[MSH OpenAI] Failed to parse metadata from response' );
			return null;
		}

		// Apply AI regeneration filters if specified
		$ai_options = ! empty( $payload['ai_options'] ) ? $payload['ai_options'] : array();
		if ( ! empty( $ai_options['ai_regeneration'] ) ) {
			$ai_mode   = ! empty( $ai_options['ai_mode'] ) ? $ai_options['ai_mode'] : 'fill-empty';
			$ai_fields = ! empty( $ai_options['ai_fields'] ) ? $ai_options['ai_fields'] : array();

			// Filter to only requested fields
			if ( ! empty( $ai_fields ) ) {
				$field_map = array(
					'title'       => 'title',
					'alt_text'    => 'alt_text',
					'caption'     => 'caption',
					'description' => 'description',
				);

				$filtered_metadata = array();
				foreach ( $ai_fields as $field ) {
					if ( isset( $field_map[ $field ] ) && isset( $parsed_metadata[ $field_map[ $field ] ] ) ) {
						$filtered_metadata[ $field_map[ $field ] ] = $parsed_metadata[ $field_map[ $field ] ];
					}
				}
				$parsed_metadata = $filtered_metadata;
			}

			// Apply fill-empty mode: only include fields that are currently empty
			if ( $ai_mode === 'fill-empty' ) {
				$current_title       = get_the_title( $attachment_id );
				$current_alt         = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				$current_caption     = wp_get_attachment_caption( $attachment_id );
				$current_description = get_post_field( 'post_content', $attachment_id );

				// Remove fields that already have values
				if ( ! empty( $current_title ) && isset( $parsed_metadata['title'] ) ) {
					unset( $parsed_metadata['title'] );
				}
				if ( ! empty( $current_alt ) && isset( $parsed_metadata['alt_text'] ) ) {
					unset( $parsed_metadata['alt_text'] );
				}
				if ( ! empty( $current_caption ) && isset( $parsed_metadata['caption'] ) ) {
					unset( $parsed_metadata['caption'] );
				}
				if ( ! empty( $current_description ) && isset( $parsed_metadata['description'] ) ) {
					unset( $parsed_metadata['description'] );
				}

				error_log( '[MSH OpenAI] Fill-empty mode: filtered to ' . count( $parsed_metadata ) . ' empty fields' );
			}
		}

		error_log( '[MSH OpenAI] Successfully generated metadata for attachment ' . $attachment_id );
		error_log( '[MSH OpenAI] Generated title: ' . ( $parsed_metadata['title'] ?? 'N/A' ) );
		error_log( '[MSH OpenAI] Generated description: ' . ( $parsed_metadata['description'] ?? 'N/A' ) );

		// CRITICAL: Enforce seo_mode rules (no branding/location when disabled).
		$seo_mode      = isset( $context['seo_mode'] ) ? (bool) $context['seo_mode'] : true;
		$business_name = $context['business_name'] ?? '';

		if ( ! $seo_mode ) {
			$location_terms = array_filter(
				array_unique(
					array(
						$context['city'] ?? '',
						$context['region'] ?? '',
						$context['country'] ?? '',
						$context['service_area'] ?? '',
						$context['location'] ?? '',
					)
				)
			);

			$disallowed_terms = $location_terms;
			if ( $business_name ) {
				$disallowed_terms[] = $business_name;
			}

			foreach ( array( 'title', 'alt_text', 'caption', 'description' ) as $field ) {
				if ( isset( $parsed_metadata[ $field ] ) && $parsed_metadata[ $field ] !== '' ) {
					$clean_value = $this->strip_disallowed_terms( $parsed_metadata[ $field ], $disallowed_terms );
					if ( $clean_value === '' ) {
						$clean_value = $this->get_non_seo_fallback( $field, $context );
					}
					if ( $clean_value !== $parsed_metadata[ $field ] ) {
						error_log( sprintf( '[MSH OpenAI] seo_mode=false: Sanitised %s field', $field ) );
					}
					$parsed_metadata[ $field ] = $clean_value;
				}
			}

			if ( isset( $parsed_metadata['keywords'] ) ) {
				$parsed_metadata['keywords'] = array();
			}
		}

		return $parsed_metadata;
	}

	private function strip_disallowed_terms( $value, array $terms ) {
		$value = (string) $value;
		if ( $value === '' || empty( $terms ) ) {
			return trim( $value );
		}

		foreach ( $terms as $term ) {
			$term = trim( (string) $term );
			if ( $term === '' ) {
				continue;
			}
			$pattern = '/\b' . preg_quote( $term, '/' ) . '\b/iu';
			$value   = preg_replace( $pattern, '', $value );
			$value   = str_ireplace( $term, '', $value );
		}

		$value = preg_replace( '/\s{2,}/u', ' ', trim( $value ) );
		$value = preg_replace( '/\s+([,.;:])/u', '$1', $value );

		return trim( $value );
	}

	private function get_non_seo_fallback( $field, $context ) {
		$type = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );

		switch ( $field ) {
			case 'title':
				return ucfirst( $type ) . ' Image';
			case 'alt_text':
				return 'Neutral scene description without branding.';
			case 'caption':
				return 'Scene-focused caption with no brand references.';
			case 'description':
				return 'Neutral scene overview with no brand or location references.';
			default:
				return 'Image detail';
		}
	}

	/**
	 * Build prompt messages using new SYSTEM/USER structure with brand_name_visible flag
	 */
	private function build_prompt_messages( $attachment_id, $image_url, $business_name, $industry, $location, $uvp, $context, $features = array(), $language = 'en' ) {
		// Sanitize inputs
		$business_name_clean = wp_strip_all_tags( $business_name );
		$industry_clean      = wp_strip_all_tags( $industry );
		$location_clean      = wp_strip_all_tags( $location );
		$uvp_clean           = wp_strip_all_tags( $uvp );

		// Get context data
		// DEBUG: Log entire context array to diagnose context_type issue
		error_log( '[MSH OpenAI DEBUG] Full context array: ' . wp_json_encode( $context ) );

		$context_type   = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );
		$business_type  = isset( $context['business_type'] ) ? $context['business_type'] : '';
		$ideal_customer = isset( $context['ideal_customer'] ) ? $context['ideal_customer'] : '';
		$brand_voice    = isset( $context['brand_voice'] ) ? $context['brand_voice'] : '';

		$brand_flag = isset( $context['brand_name_visible'] ) ? (bool) $context['brand_name_visible'] : false;
		$brand_flag = (bool) apply_filters( 'msh_brand_visibility_flag', $brand_flag, $context );
		$brand_name_visible = $brand_flag ? 'true' : 'false';

		error_log( sprintf(
			'[MSH OpenAI] Prompt v%s - context_type: %s, brand_name_visible: %s',
			self::PROMPT_VERSION,
			$context_type,
			$brand_name_visible
		) );

		// Get attachment metadata for original filename
		$original_filename = basename( get_attached_file( $attachment_id ) );

		// Get page context (if available)
		$page_title    = isset( $context['page_title'] ) ? wp_strip_all_tags( (string) $context['page_title'] ) : '';
		$focus_keyword = isset( $context['focus_keyword'] ) ? wp_strip_all_tags( (string) $context['focus_keyword'] ) : '';
		$page_role     = isset( $context['page_role'] ) ? wp_strip_all_tags( (string) $context['page_role'] ) : 'general_content_image';

		// Determine model pass type
		$model_pass = 'low_detail'; // Phase 0B: Use low detail for token optimization

		// Get locale
		$locale = ! empty( $context['locale'] ) ? $context['locale'] : 'en-US';
		$context_set_manually = ! empty( $context['context_set_manually'] );
		$brand_manual         = ! empty( $context['brand_name_visible_manual'] );
		$ocr_flag             = ! empty( $context['ocr_found_brand'] );
		$ai_search_friendly   = array_key_exists( 'ai_search_friendly', $context ) ? (bool) $context['ai_search_friendly'] : true;
		$seo_mode             = array_key_exists( 'seo_mode', $context ) ? (bool) $context['seo_mode'] : true; // Default ON
		$manual_flag_str      = $context_set_manually ? 'true' : 'false';
		$manual_brand_str     = $brand_manual ? 'true' : 'false';
		$ocr_flag_str         = $ocr_flag ? 'true' : 'false';
		$ai_search_flag_str   = $ai_search_friendly ? 'true' : 'false';
		$seo_mode_str         = $seo_mode ? 'true' : 'false';
		$downgrades           = array();
		if ( ! empty( $context['context_trace']['downgraded_reasons'] ) && is_array( $context['context_trace']['downgraded_reasons'] ) ) {
			$downgrades = $context['context_trace']['downgraded_reasons'];
		}
		$downgrade_summary = empty( $downgrades ) ? 'none' : implode( ', ', array_map( 'sanitize_key', $downgrades ) );

		// Phase 0B: Generate context ID for compact prompt
		$ctx_id = $this->generate_context_id( $context );

		// Phase 0B: Ultra-compressed system prompt (~20 tokens)
		// All context and rules moved to user message for token efficiency
		$system_message = "AI metadata assistant. Context:{$ctx_id}. JSON only. No commentary.";

		// Phase 0B: Build compact user message with all context flags
		$brand_voice_val = ! empty( $brand_voice ) ? $brand_voice : 'neutral';
		$bn = $this->promptSafe( $business_name_clean );
		$bl = $this->promptSafe( $location_clean );
		$sv = isset( $context['service_keywords'] ) ? $this->csvSafe( $context['service_keywords'] ) : '';

		// Build compact pipe-delimited user message (~75-85 tokens)
		$user_message = sprintf(
			"ctx:%s|ct:%s|cm:%d|seo:%d|bm:%d|bn:%s|bl:%s|sv:%s|bv:%s\npg:ti=%s|kw=%s|pr=%s\nschema:{fn,t,a,c,d,k[],s[],attr[],conf,iss[]}\nrules: ct final if cm=1; describe visible only; brand only if bm=1 and (ct in [logo,team,facility,equipment] or (ct in [clinical,business,testimonial] and bm=1)); when ct=facility and bm=1 and seo=1 include bn in both t and d; if bm=0 or seo=0 the business name must not appear anywhere; when seo=1 include exactly one location (from bl or pg.ti) and one service keyword (from sv) if context allows; never invent location or service beyond those provided; if seo=0 omit brand, location, and CTA language; use kw only if visibly relevant; tone=%s.",
			$ctx_id,
			$context_type,
			$context_set_manually ? 1 : 0,
			$seo_mode ? 1 : 0,
			$brand_flag ? 1 : 0,
			$bn,
			$bl,
			$sv,
			$brand_voice_val,
			$this->promptSafe( $page_title ),
			$this->promptSafe( $focus_keyword ),
			$page_role,
			$brand_voice_val
		);

		// Phase 0B: Log audit trail with ctx_id and first 80 chars of prompt
		error_log(
			sprintf(
				'[MSH SmartMode] ctx:%s | prompt=%s…',
				$ctx_id,
				substr( $user_message, 0, 80 )
			)
		);

		return array(
			'system' => $system_message,
			'user'   => $user_message,
		);
	}

	/**
	 * Phase 0B: Generate context ID fingerprint for compact prompts
	 *
	 * @param array $context Context array from detect_context()
	 * @return string Context ID (e.g., "ctx_9f11db7")
	 */
	private function generate_context_id( $context ) {
		$site_id  = get_option( 'siteurl' );
		$locale   = get_locale();
		$business = isset( $context['business_name'] ) ? $context['business_name'] : '';
		$industry = isset( $context['industry'] ) ? $context['industry'] : '';
		$seo_mode = isset( $context['seo_mode'] ) ? (int) $context['seo_mode'] : 0;

		// Generate stable fingerprint
		$fingerprint = sha1(
			$site_id . '|' .
			$locale . '|' .
			$business . '|' .
			$industry . '|' .
			$seo_mode
		);

		return 'ctx_' . substr( $fingerprint, 0, 7 );
	}

	/**
	 * Phase 0B: Sanitize prompt values to prevent injection and reduce token count
	 *
	 * @param mixed $val Value to sanitize
	 * @param int   $maxTokens Maximum words to keep (approx. 1.3 tokens per word)
	 * @return string Sanitized value
	 */
	private function promptSafe( $val, $maxTokens = 12 ) {
		$s = wp_strip_all_tags( (string) $val );
		$s = preg_replace( '/\s+/', ' ', $s );
		$s = str_replace( array( '|', "\n", "\r", '{', '}', ':' ), ' ', $s );
		return wp_trim_words( $s, $maxTokens, '' );
	}

	/**
	 * Phase 0B: Convert array to safe CSV string for compact prompts
	 *
	 * @param array|string $arr Array of values or single value
	 * @param int          $maxItems Maximum items to include
	 * @param int          $maxLenPerItem Maximum tokens per item
	 * @return string CSV string
	 */
	private function csvSafe( $arr, $maxItems = 5, $maxLenPerItem = 4 ) {
		if ( ! is_array( $arr ) ) {
			$arr = array( $arr );
		}
		$arr = array_filter(
			array_map(
				function( $x ) use ( $maxLenPerItem ) {
					return $this->promptSafe( $x, $maxLenPerItem );
				},
				array_slice( $arr, 0, $maxItems )
			)
		);
		return implode( ',', $arr );
	}

	/**
	 * Normalize the language choice coming from the UI/AI options.
	 *
	 * @param string $language Selected language (or 'auto').
	 * @param array  $ai_options AI options array.
	 * @param array  $context Context payload.
	 * @return string Resolved language code.
	 */
	private function normalize_language_choice( $language, $ai_options, $context ) {
		$language  = strtolower( (string) $language );
		$supported = array( 'en', 'es', 'fr', 'de', 'pt', 'it' );

		if ( $language === 'auto' || ! in_array( $language, $supported, true ) ) {
			$language = $this->resolve_auto_language( $ai_options, $context );
		}

		if ( ! in_array( $language, $supported, true ) ) {
			$language = 'en';
		}

		return $language;
	}

	/**
	 * Resolve the automatic language selection based on profile or site locale.
	 *
	 * @param array $ai_options AI options array.
	 * @param array $context Context payload.
	 * @return string Language code.
	 */
	private function resolve_auto_language( $ai_options, $context ) {
		$candidates = array();

		if ( ! empty( $ai_options['profile_locale'] ) ) {
			$candidates[] = $ai_options['profile_locale'];
		}

		if ( ! empty( $context['locale'] ) ) {
			$candidates[] = $context['locale'];
		}

		if ( empty( $candidates ) ) {
			$candidates[] = get_locale();
		}

		foreach ( $candidates as $candidate ) {
			if ( ! $candidate ) {
				continue;
			}
			$short = strtolower( (string) $candidate );
			$parts = preg_split( '/[-_]/', $short );
			if ( ! empty( $parts[0] ) ) {
				return $parts[0];
			}
		}

		return 'en';
	}

	/**
	 * Token bucket rate limiter - prevents hitting OpenAI 30K TPM limit
	 *
	 * @param int $estimated_tokens Conservative estimate for this request
	 * @return bool True if safe to proceed, false if need to wait
	 */
	private function check_rate_limit( $estimated_tokens = 500 ) {
		$tpm_limit = 30000; // OpenAI BYOK limit
		$headroom = 0.8;    // Use only 80% of limit for safety
		$safe_limit = $tpm_limit * $headroom;

		// Get rolling window data (last 60 seconds)
		$window_key = 'msh_openai_token_window';
		$window_data = get_transient( $window_key );

		if ( false === $window_data ) {
			$window_data = array();
		}

		// Clean old entries (older than 60 seconds)
		$now = time();
		$window_data = array_filter( $window_data, function( $entry ) use ( $now ) {
			return ( $now - $entry['timestamp'] ) < 60;
		} );

		// Calculate tokens used in last 60 seconds
		$tokens_used_last_60s = array_sum( array_column( $window_data, 'tokens' ) );

		// Check if adding this request would exceed limit
		if ( ( $tokens_used_last_60s + $estimated_tokens ) >= $safe_limit ) {
			error_log( sprintf(
				'[MSH RATE LIMIT] Would exceed safe limit: used=%d, estimated=%d, safe_limit=%d. Delaying request.',
				$tokens_used_last_60s,
				$estimated_tokens,
				$safe_limit
			) );

			// Wait for oldest entry to expire
			if ( ! empty( $window_data ) ) {
				$oldest = min( array_column( $window_data, 'timestamp' ) );
				$wait_time = 60 - ( $now - $oldest ) + 1; // Wait until oldest expires + 1 sec buffer
				if ( $wait_time > 0 && $wait_time < 60 ) {
					sleep( $wait_time );
				}
			}
		}

		// Log this request to the window
		$window_data[] = array(
			'timestamp' => $now,
			'tokens'    => $estimated_tokens,
		);

		// Save updated window (expires in 65 seconds)
		set_transient( $window_key, $window_data, 65 );

		return true;
	}

	/**
	 * Call OpenAI Vision API
	 */
	private function call_openai_vision( $image_url, $messages, $api_key ) {
		// RATE LIMIT GATE: Check token bucket before making request
		$this->check_rate_limit( 500 ); // Conservative estimate until we measure actual usage

		// For local development, convert image to base64 if URL is not publicly accessible
		$image_data = $this->get_image_data( $image_url );

		$body = array(
			'model'       => 'gpt-4o', // Using GPT-4o for vision (faster and cheaper than gpt-4-vision-preview)
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $messages['system'],
				),
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $messages['user'],
						),
						array(
							'type'      => 'image_url',
							'image_url' => array(
								'url'    => $image_data,
								'detail' => 'low', // Phase 0B: Use low detail (85 tokens) + short keys for token optimization
							),
						),
					),
				),
			),
			'max_tokens'  => 500,
			'temperature' => 0, // Deterministic outputs, no variance in retries
		);

		// Log request payload size for optimization tracking
		$request_json = wp_json_encode( $body );
		$request_size = strlen( $request_json );
		error_log( sprintf(
			'[MSH AI Token Optimization] Request payload: %d bytes (includes short key schema)',
			$request_size
		) );

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $request_json,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_message = 'HTTP ' . $status_code;
			$decoded       = json_decode( $response_body, true );
			if ( isset( $decoded['error']['message'] ) ) {
				$error_message .= ': ' . $decoded['error']['message'];
			}
			return new WP_Error( 'openai_api_error', $error_message );
		}

		return $response_body;
	}

	/**
	 * Get image data - supports multiple methods for local development
	 *
	 * Priority:
	 * 1. Live Link URL (if configured) - cleanest for testing
	 * 2. Base64 encoding (automatic fallback) - always works
	 * 3. Direct URL (for production sites)
	 */
	private function get_image_data( $image_url ) {
		// Check if Live Link URL is configured (Local by Flywheel feature)
		$live_link_url = get_option( 'msh_ai_live_link_url', '' );

		if ( ! empty( $live_link_url ) ) {
			// Replace local domain with Live Link domain
			$local_url     = home_url( '/' );
			$live_link_url = trailingslashit( $live_link_url );

			$converted_url = str_replace( $local_url, $live_link_url, $image_url );

			if ( $converted_url !== $image_url ) {
				error_log( '[MSH OpenAI] Using Live Link URL: ' . $converted_url );
				return $converted_url;
			}
		}

		// Check if URL is local (localhost, .local, 127.0.0.1, etc.)
		$is_local = preg_match( '/(localhost|\.local|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/i', $image_url );

		if ( $is_local ) {
			error_log( '[MSH OpenAI] Local URL detected, converting to base64' );

			// Convert to base64 for local development
			// Use wp_get_upload_dir() for robust path mapping (handles schemes, ports, subdirs, multisite, etc.)
			$uploads = wp_get_upload_dir();

			if ( strpos( $image_url, $uploads['baseurl'] ) === 0 ) {
				// Get relative path from upload base URL
				$relative      = ltrim( str_replace( $uploads['baseurl'], '', $image_url ), '/' );
				$absolute_path = trailingslashit( $uploads['basedir'] ) . $relative;

				if ( file_exists( $absolute_path ) ) {
					// PERFORMANCE: Resize image before base64 encoding to reduce payload
					$resized_path = $this->resize_for_ai( $absolute_path );
					$image_data   = file_get_contents( $resized_path );
					$base64       = base64_encode( $image_data );
					$mime_type    = 'image/jpeg'; // Always JPEG after resize

					error_log( '[MSH OpenAI] Converted to base64: ' . $resized_path );

					// Clean up temp file if different from original
					if ( $resized_path !== $absolute_path && file_exists( $resized_path ) ) {
						@unlink( $resized_path );
					}

					return "data:{$mime_type};base64,{$base64}";
				}

				error_log( '[MSH OpenAI] Local image file not found: ' . $absolute_path );
			} else {
				error_log( '[MSH OpenAI] Image URL not in uploads directory: ' . $image_url );
			}
		}

		// Return URL as-is for public URLs
		return $image_url;
	}

	/**
	 * Resize image for AI processing to reduce base64 payload and token costs.
	 * Phase 0B: Targets 640px long edge (optimized for detail:low), JPEG 80% quality, <100KB file size.
	 *
	 * @param string $image_path Absolute path to original image.
	 * @return string Path to resized image (or original if resize fails).
	 */
	private function resize_for_ai( $image_path ) {
		// Check if GD or Imagick is available
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			error_log( '[MSH OpenAI] wp_get_image_editor not available, using original image' );
			return $image_path;
		}

		// Create temp file path
		$path_info = pathinfo( $image_path );
		$temp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '-ai-temp.' . $path_info['extension'];

		// Get image editor
		$editor = wp_get_image_editor( $image_path );
		if ( is_wp_error( $editor ) ) {
			error_log( '[MSH OpenAI] Image editor error: ' . $editor->get_error_message() );
			return $image_path;
		}

		// Get current size
		$size = $editor->get_size();
		if ( ! $size ) {
			return $image_path;
		}

		$width  = $size['width'];
		$height = $size['height'];

		// Phase 0B: Calculate new dimensions (max 640px on long edge, optimized for detail:low)
		$max_dimension = 640;
		if ( $width > $max_dimension || $height > $max_dimension ) {
			if ( $width > $height ) {
				$new_width  = $max_dimension;
				$new_height = intval( ( $height / $width ) * $max_dimension );
			} else {
				$new_height = $max_dimension;
				$new_width  = intval( ( $width / $height ) * $max_dimension );
			}

			$editor->resize( $new_width, $new_height, false );
		}

		// Set JPEG quality to 80%
		$editor->set_quality( 80 );

		// Save to temp file
		$saved = $editor->save( $temp_path, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			error_log( '[MSH OpenAI] Image save error: ' . $saved->get_error_message() );
			return $image_path;
		}

		// Use the actual saved path from the editor (WordPress may modify the filename)
		$actual_path = isset( $saved['path'] ) ? $saved['path'] : $temp_path;

		// Check file size
		if ( ! file_exists( $actual_path ) ) {
			error_log( '[MSH OpenAI] Temp file not created: ' . $actual_path );
			return $image_path;
		}

		$file_size = filesize( $actual_path );
		error_log( sprintf(
			'[MSH OpenAI] Resized image: %dx%d → %dx%d, %s → %s',
			$width,
			$height,
			$new_width ?? $width,
			$new_height ?? $height,
			size_format( filesize( $image_path ), 2 ),
			size_format( $file_size, 2 )
		) );

		return $actual_path;
	}

	/**
	 * Parse OpenAI response into metadata array
	 */
	private function parse_openai_response( $response_json, $context ) {
		$data = json_decode( $response_json, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return null;
		}

		// INSTRUMENTATION: Extract token usage from OpenAI response
		$usage = $data['usage'] ?? array();
		$prompt_tokens = $usage['prompt_tokens'] ?? 0;
		$completion_tokens = $usage['completion_tokens'] ?? 0;
		$total_tokens = $usage['total_tokens'] ?? 0;

		$content = trim( $data['choices'][0]['message']['content'] );

		// Remove markdown code blocks if present
		$content = preg_replace( '/^```json\s*/m', '', $content );
		$content = preg_replace( '/\s*```$/m', '', $content );

		$metadata = json_decode( $content, true );

		if ( ! is_array( $metadata ) ) {
			error_log( '[MSH OpenAI] Invalid JSON in response: ' . $content );
			return null;
		}

		// Log short key optimization metrics
		$short_key_size = strlen( $content );
		error_log( sprintf(
			'[MSH AI Token Optimization] Raw response (short keys): %d bytes',
			$short_key_size
		) );
		error_log( '[MSH AI Token Optimization] Short key response: ' . $content );

		// INSTRUMENTATION: Per-image telemetry (Phase 0B audit trail)
		$attachment_id = $context['attachment_id'] ?? 0;
		error_log( sprintf(
			'[MSH TELEMETRY] image_id=%d | model=gpt-4o | detail=low | schema=short_keys_v4 | prompt_tokens=%d | completion_tokens=%d | total_tokens=%d | response_bytes=%d',
			$attachment_id,
			$prompt_tokens,
			$completion_tokens,
			$total_tokens,
			$short_key_size
		) );

		// Flag if tokens exceed target
		if ( $total_tokens > 600 ) {
			error_log( sprintf(
				'[MSH ALERT] Image %d exceeded 600 token threshold: %d tokens (prompt=%d, completion=%d)',
				$attachment_id,
				$total_tokens,
				$prompt_tokens,
				$completion_tokens
			) );
		}

		// Expand short keys to verbose keys (backward compatible - accepts both)
		$metadata = MSH_Key_Compactor::expand_keys( $metadata );

		// Log expanded size for comparison
		$verbose_equivalent = json_encode( $metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$verbose_size = strlen( $verbose_equivalent );
		$savings = round( ( 1 - ( $short_key_size / $verbose_size ) ) * 100, 1 );
		error_log( sprintf(
			'[MSH AI Token Optimization] Verbose equivalent: %d bytes | Savings: %d bytes (%.1f%%)',
			$verbose_size,
			$verbose_size - $short_key_size,
			$savings
		) );

		// Extract confidence and issues from new structure
		$confidence = isset( $metadata['confidence'] ) ? floatval( $metadata['confidence'] ) : 0.0;
		$issues     = isset( $metadata['issues'] ) && is_array( $metadata['issues'] ) ? $metadata['issues'] : array();

		// Log quality metadata
		error_log( sprintf(
			'[MSH OpenAI] AI response - confidence: %.2f, issues: %s',
			$confidence,
			empty( $issues ) ? 'none' : implode( ', ', $issues )
		) );

		// Validate required fields
		$required = array( 'title', 'alt_text', 'caption', 'description' );
		foreach ( $required as $field ) {
			// Allow empty title/alt_text only if decorative_image flag is set
			if ( in_array( 'decorative_image', $issues, true ) && in_array( $field, array( 'title', 'alt_text' ), true ) ) {
				continue; // Decorative images can have empty title/alt
			}
			if ( empty( $metadata[ $field ] ) ) {
				error_log( '[MSH OpenAI] Missing required field: ' . $field );
				return null;
			}
		}

		// Sanitize the metadata
		$sanitized = array(
			'title'          => sanitize_text_field( $metadata['title'] ),
			'alt_text'       => sanitize_text_field( $metadata['alt_text'] ),
			'caption'        => sanitize_text_field( $metadata['caption'] ),
			'description'    => sanitize_textarea_field( $metadata['description'] ),
			'confidence'     => $confidence,
			'issues'         => $issues,
			'prompt_version' => self::PROMPT_VERSION,
		);

		// Add file_name_suggestion if provided (new field replacing filename_slug)
		if ( ! empty( $metadata['file_name_suggestion'] ) ) {
			$sanitized['filename_slug'] = sanitize_title( $metadata['file_name_suggestion'] );
			error_log( '[MSH OpenAI] AI suggested filename: ' . $sanitized['filename_slug'] );
		}

		// Add keywords if provided
		if ( ! empty( $metadata['keywords'] ) && is_array( $metadata['keywords'] ) ) {
			$sanitized['keywords'] = array_map( 'sanitize_text_field', $metadata['keywords'] );
			error_log( '[MSH OpenAI] AI suggested keywords: ' . implode( ', ', $sanitized['keywords'] ) );
		}

		// Validate the AI response for quality
		$validation = $this->validate_ai_response( $sanitized, $context );
		if ( is_wp_error( $validation ) ) {
			error_log( '[MSH OpenAI] Validation failed: ' . $validation->get_error_message() );
			return $validation; // Return WP_Error to trigger escalation or fallback
		}

		return $sanitized;
	}

	/**
	 * Server-side validator for context_type rules enforcement
	 * Based on user's improved prompt structure
	 *
	 * @param array $context Business context with context_type
	 * @param array $metadata AI-generated metadata (passed by reference)
	 * @param array $issues Issues array (passed by reference)
	 * @return true|WP_Error True if valid, WP_Error if critical violation
	 */
	private function validate_context_rules( $context, &$metadata, &$issues ) {
		// Note: Context array uses 'type' key, not 'context_type'
		$type = isset( $context['type'] ) ? $context['type'] : 'stock';
		$business_name = isset( $context['business_name'] ) ? strtolower( $context['business_name'] ) : '';

		// Combine all text fields for checking
		$all_text = strtolower( implode( ' ', array(
			$metadata['title'] ?? '',
			$metadata['alt_text'] ?? '',
			$metadata['caption'] ?? '',
			$metadata['description'] ?? '',
		) ) );

		$brand_found = ! empty( $business_name ) && strpos( $all_text, $business_name ) !== false;

		// Helper function to add issue if not already present
		$add_issue = function( $issue_name ) use ( &$issues ) {
			if ( ! in_array( $issue_name, $issues, true ) ) {
				$issues[] = $issue_name;
			}
		};

		// HARD RULES (reject immediately)

		// 1) stock, decorative → NEVER include business_name
		if ( in_array( $type, array( 'stock', 'decorative' ), true ) && $brand_found ) {
			error_log( "[MSH Validator] REJECT: context_type={$type} but business_name found in output" );
			$add_issue( 'context_mismatch' );
			return new WP_Error( 'context_mismatch', "Business name not allowed for {$type} images" );
		}

		// 2) testimonial → PROHIBITED phrases
		if ( $type === 'testimonial' ) {
			$forbidden = array(
				'at ' . $business_name,
				'in our facility',
				$business_name . ' client',
				'our clinic',
				'our office',
			);

			foreach ( $forbidden as $phrase ) {
				if ( strpos( $all_text, $phrase ) !== false ) {
					error_log( "[MSH Validator] REJECT: testimonial contains forbidden phrase: '{$phrase}'" );
					$add_issue( 'context_mismatch' );
					return new WP_Error( 'context_mismatch', "Testimonial images cannot claim business location/ownership" );
				}
			}
		}

		// 3) clinical, business → Follow brand_name_visible strictly
		if ( in_array( $type, array( 'clinical', 'business' ), true ) ) {
			$brand_visible = isset( $context['brand_name_visible'] ) && $context['brand_name_visible'] === 'true';

			if ( ! $brand_visible && $brand_found ) {
				error_log( "[MSH Validator] REJECT: context_type={$type}, brand_name_visible=false but brand found" );
				$add_issue( 'brand_name_assumed' );
				return new WP_Error( 'brand_name_assumed', "Business name not permitted when brand_name_visible=false" );
			}
		}

		// SOFT RULES (warn only)

		// 4) service-icon → Should only have brand if text/logo visible
		if ( $type === 'service-icon' && $brand_found ) {
			$brand_visible = isset( $context['brand_name_visible'] ) && $context['brand_name_visible'] === 'true';

			if ( ! $brand_visible ) {
				error_log( "[MSH Validator] WARN: service-icon has business_name but brand_name_visible=false (soft warning)" );
				$add_issue( 'brand_name_assumed' );
				// Don't reject, just flag
			}
		}

		// Update metadata with modified issues array
		$metadata['issues'] = $issues;

		return true;
	}

	/**
	 * Validate AI response for quality and appropriateness
	 *
	 * @param array $metadata Sanitized metadata from AI
	 * @param array $context Business context
	 * @return true|WP_Error True if valid, WP_Error if validation fails
	 */
	private function validate_ai_response( $metadata, $context ) {
		$validator = MSH_Context_Aware_Validator::get_instance();

		return $validator->validate( $context, $metadata );
	}
}

// Initialize the connector
new MSH_OpenAI_Connector();
