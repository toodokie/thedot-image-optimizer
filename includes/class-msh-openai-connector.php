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
	const PROMPT_VERSION = '20251030.6'; // Added SEO mode toggle with location keywords, service keywords, and CTAs

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
		return $parsed_metadata;
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
		$model_pass = 'high_detail'; // Using high detail for all images

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

		// Build SYSTEM message
		$system_message = "You are an AI metadata assistant for an image optimization plugin (locale: {$locale}).

PRIORITY OF TRUTH:
1) context_type is authoritative. If the user set it manually, treat it as final. Do not override or reinterpret.
2) Page context (page_title, focus_keyword) and business context (business_name, industry) guide tone and relevance ONLY. Never use them to invent facts or override context_type.
3) Describe only what is visible in the image.

BUSINESS CONTEXT → use for tone and relevance, never to invent facts:
- business_name: {$business_name_clean}
- industry: {$industry_clean}
- business_type: {$business_type}
- ideal_customer: {$ideal_customer}
- service_area: {$location_clean}
- brand_voice: {$brand_voice}
- unique_value: {$uvp_clean}

IMAGE USE CONTEXT → authoritative, final, cannot be overridden:
- context_type: {$context_type}  // user chosen or resolved. Treat as final
- context_set_manually: {$manual_flag_str}  // true | false
- brand_name_visible: {$brand_name_visible}  // true | false. Logo or brand text visibly present or permitted by rules below
- brand_name_visible_manual: {$manual_brand_str}
- ocr_found_brand: {$ocr_flag_str}
- downgrade_trace: {$downgrade_summary}

BRAND NAME INCLUSION RULES → When brand_name_visible = true, you MUST include {$business_name_clean} in BOTH title and description:

TESTIMONIAL with brand_name_visible = true:
  ✓ CORRECT: title: \"Hope and Recovery - {$business_name_clean} Patient Success\"
  ✗ WRONG: title: \"Sunlit Reflection Testimonial\" (missing brand)

STOCK with brand_name_visible = true:
  ✓ CORRECT: title: \"Fresh Lettuce Field - {$business_name_clean} Wellness Imagery\"
  ✗ WRONG: title: \"Lettuce Field at Sunrise\" (missing brand)

CONTEXT TYPE RULES → respect context_type above all else, even if page context suggests otherwise:

- brand_logo: Real branding. Include business_name. Describe visual mark and style. No location claims.
- team: Staff photos. Include business_name. Professional tone.
- facility: Actual building or clinic. Include business_name and city or region if known from page context. Do not invent addresses.
- equipment: Tools or machinery owned by the business. You MUST include {$business_name_clean} naturally in title and description. Write organically as if describing equipment belonging to or used by the business. Examples: \"Rehabilitation equipment at {$business_name_clean}\", \"Medical tools for {$business_name_clean} patient care\", \"Clinical equipment used by {$business_name_clean} practitioners\". Be specific to visible items.
- clinical: Care or service delivery imagery. If brand_name_visible = true, you MUST include {$business_name_clean} naturally in title and description. Write organically connecting treatment to the business. Examples: \"Patient care services at {$business_name_clean}\", \"Treatment session at {$business_name_clean} clinic\", \"Rehabilitation therapy provided by {$business_name_clean}\". If brand_name_visible = false, describe neutrally without brand reference.
- business: Operations or office scenes. If brand_name_visible = true, you MUST include {$business_name_clean} naturally in title and description. Write organically as workplace content. Examples: \"Professional workspace at {$business_name_clean}\", \"Administrative operations at {$business_name_clean} office\", \"Team collaboration at {$business_name_clean}\". If brand_name_visible = false, describe neutrally without brand reference.
- testimonial: If brand_name_visible = true, you MUST include {$business_name_clean} naturally in title and description connecting emotion to outcomes. Write organically linking experience to the business. Examples: \"Recovery journey with {$business_name_clean}\", \"Patient success story at {$business_name_clean}\", \"Positive health outcomes from {$business_name_clean} care\". PROHIBITED: claiming specific facility location or that this is an actual client photo. If brand_name_visible = false, describe emotion and visible elements without brand reference.
- service-icon: Icon or graphic for a service. Describe the icon purpose and connect to the service category. Mention business_name only if logo or text is visible on the icon.
- decorative: Pure background or pattern with no informational value. alt_text = \"\" and title = \"\" are appropriate.
- stock: Generic stock photography. If brand_name_visible = true (manual override or OCR detected), you MUST include {$business_name_clean} in title. Format: \"[Visual Description] - {$business_name_clean} [Branding/Imagery/Visual]\". If false, describe only visible content with no business connection.

CRITICAL ENFORCEMENT:
- When brand_name_visible = true for clinical, business, testimonial, or stock contexts: business_name inclusion is REQUIRED, not optional.
- When brand_name_visible = false for these contexts: business_name inclusion is PROHIBITED.
- When context_type = stock or decorative and brand_name_visible = false: business_name is PROHIBITED regardless of page context.
- brand_logo, team, facility, equipment contexts ALWAYS permit business_name (do not invent addresses).

AI SEARCH AND SEO EXTENSION (applies when seo_mode = true):
When seo_mode = true, enhance metadata with natural SEO elements:
- Include ONE location keyword from service_area if known (e.g., Hamilton, Hamilton Ontario)
- Include ONE service keyword relevant to industry/business_type (e.g., physiotherapy, rehabilitation, chiropractic care)
- Connect description to business expertise when context allows (e.g., Main Street Health rehabilitation clinic)
- End description with soft call-to-action when appropriate:
  * For facility/team/equipment: Visit our clinic or Book your appointment
  * For clinical: Learn more about our programs or Schedule your consultation
  * For business: Contact our team or Explore our services
- Keep language natural and conversational. Do NOT keyword-stuff.
- Example (seo_mode=true): Rehabilitation equipment used by Main Street Health physiotherapy team in Hamilton Ontario. Book your assessment today.
- Example (seo_mode=false): Therapy bands and exercise equipment in a rehabilitation clinic setting.

When seo_mode = false, write pure descriptive metadata:
- Focus only on visible content
- No location keywords, service keywords, or CTAs
- Keep neutral and factual

SPECIFICITY AND UNIQUENESS:
- Provide subjects[] with at least 5 concrete visible nouns.
- Provide attributes[] with at least 3 visual traits such as color, material, lighting, perspective.
- Avoid generic phrases: 'brand imagery', 'generic image', 'stock photo', 'placeholder', 'medical treatment'.
- Make title and alt_text specific to visible elements, not vague category labels.

OUTPUT FORMAT (return exactly one JSON object with keys in this order):
{
  \"file_name_suggestion\": \"...\",     // lowercase, hyphenated, no special chars, length ≤ 50
  \"title\": \"...\",                    // length ≤ 60
  \"alt_text\": \"...\",                 // 8–140 chars. If decorative, set \"\"
  \"caption\": \"...\",                  // one sentence
  \"description\": \"...\",              // 2–3 sentences
  \"keywords\": [\"...\", \"...\", \"...\"],  // 3–5 short terms relevant to visible content and allowed context
  \"subjects\": [\"...\", \"...\", \"...\", \"...\", \"...\"],  // at least 5 concrete visible nouns
  \"attributes\": [\"...\", \"...\", \"...\"],  // at least 3 visual traits
  \"confidence\": 0.00,                 // 0.0–1.0
  \"issues\": [\"...\"]                  // zero or more of: brand_name_assumed, low_confidence, text_in_image_detected, decorative_image, context_mismatch, too_generic
}

SELF CHECK BEFORE RESPONDING:
- If business_name appears where the rules forbid it for this context_type, add 'brand_name_assumed' and lower confidence to ≤ 0.70.
- If decorative, set alt_text = \"\" and title = \"\" and add 'decorative_image'.
- If your text conflicts with context_type semantics, add 'context_mismatch' and lower confidence.
- If subjects fewer than 5 or you used generic phrases ('brand imagery', 'medical treatment', 'stock photo'), add 'too_generic' and rewrite with specific visible nouns and attributes.
- If text/signage is visible, include 'text_in_image_detected'.
- If confidence < 0.50, include 'low_confidence'.

OUTPUT exactly one JSON object per the schema above. No extra prose.";

		// Build USER message with parameters
		$user_message = "Image URL: {$image_url}
Original filename: {$original_filename}

Page context:
- page_title: {$page_title}
- focus_keyword: {$focus_keyword}
- page_role: {$page_role}     // header_image | article_body_image | service_page_photo | product_gallery

Execution context:
- model_pass: {$model_pass}    // overview | crops | high_detail
- ai_search_friendly: {$ai_search_flag_str}
- seo_mode: {$seo_mode_str}    // true = include location/service keywords + CTAs | false = pure descriptive

Business context (same as above):
- business_name: {$business_name_clean}
- industry: {$industry_clean}
- business_type: {$business_type}
- ideal_customer: {$ideal_customer}
- service_area: {$location_clean}
- brand_voice: {$brand_voice}
- unique_value: {$uvp_clean}

Authoritative image purpose (MANDATORY):
- context_type: {$context_type}         // exactly as user selected
- brand_name_visible: {$brand_name_visible}
- context_set_manually: {$manual_flag_str}
- brand_name_visible_manual: {$manual_brand_str}
- ocr_found_brand: {$ocr_flag_str}

Return exactly one JSON object matching the specified schema, nothing else.";

		return array(
			'system' => $system_message,
			'user'   => $user_message,
		);
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
	 * Call OpenAI Vision API
	 */
	private function call_openai_vision( $image_url, $messages, $api_key ) {
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
								'detail' => 'high', // Week 1 hotfix: Use high detail to fix generic "Brand Imagery" outputs
							),
						),
					),
				),
			),
			'max_tokens'  => 500,
			'temperature' => 0.7,
		);

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
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
					$image_data = file_get_contents( $absolute_path );
					$base64     = base64_encode( $image_data );
					$mime_type  = mime_content_type( $absolute_path );

					error_log( '[MSH OpenAI] Converted to base64: ' . $absolute_path );
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
	 * Parse OpenAI response into metadata array
	 */
	private function parse_openai_response( $response_json, $context ) {
		$data = json_decode( $response_json, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return null;
		}

		$content = trim( $data['choices'][0]['message']['content'] );

		// Remove markdown code blocks if present
		$content = preg_replace( '/^```json\s*/m', '', $content );
		$content = preg_replace( '/\s*```$/m', '', $content );

		$metadata = json_decode( $content, true );

		if ( ! is_array( $metadata ) ) {
			error_log( '[MSH OpenAI] Invalid JSON in response: ' . $content );
			return null;
		}

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
