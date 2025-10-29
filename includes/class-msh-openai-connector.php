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
	const PROMPT_VERSION = '20251029.2'; // Added context_type guidance + anti-hallucination rules

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
		$context_type   = isset( $context['type'] ) ? $context['type'] : 'business';
		$business_type  = isset( $context['business_type'] ) ? $context['business_type'] : '';
		$ideal_customer = isset( $context['ideal_customer'] ) ? $context['ideal_customer'] : '';
		$brand_voice    = isset( $context['brand_voice'] ) ? $context['brand_voice'] : '';

		// Determine brand_name_visible based on context_type
		// Allow filtering for multi-tenant customization
		$business_related_types = apply_filters( 'msh_business_related_types', $this->business_related_types, $context );
		$brand_name_visible     = in_array( $context_type, $business_related_types, true ) ? 'true' : 'false';

		error_log( sprintf(
			'[MSH OpenAI] Prompt v%s - context_type: %s, brand_name_visible: %s',
			self::PROMPT_VERSION,
			$context_type,
			$brand_name_visible
		) );

		// Get attachment metadata for original filename
		$original_filename = basename( get_attached_file( $attachment_id ) );

		// Get page context (if available)
		$page_title    = ''; // TODO: Get from post context when available
		$focus_keyword = ''; // TODO: Get from SEO plugin when available
		$page_role     = 'general_content_image'; // TODO: Determine based on usage context

		// Determine model pass type
		$model_pass = 'high_detail'; // Using high detail for all images

		// Get locale
		$locale = ! empty( $context['locale'] ) ? $context['locale'] : 'en-US';

		// Build SYSTEM message
		$system_message = "You are an AI metadata assistant for image optimization in a WordPress/website plugin (locale: {$locale}).
Business context (placeholders):
- business_name: {$business_name_clean}
- industry: {$industry_clean}
- business_type: {$business_type}
- ideal_customer: {$ideal_customer}
- service_area: {$location_clean}
- brand_voice: {$brand_voice}
- unique_value: {$uvp_clean}

You will also be provided with:
- image_url: {$image_url}
- original_filename: {$original_filename}
- brand_name_visible: {$brand_name_visible}     // true or false
- context_type: {$context_type}                 // Options: brand_logo, team, facility, equipment, clinical, business, testimonial, decorative, stock
- page_title: {$page_title}
- focus_keyword: {$focus_keyword}
- page_role: {$page_role}                        // e.g., header_image, article_body_image, service_page_photo
- model_pass: {$model_pass}                       // e.g., overview, crops, high_detail

CONTEXT TYPE GUIDANCE:
- brand_logo: Company logo/branding imagery
- team: Staff photos, team members
- facility: Building/office/clinic photos showing actual business location
- equipment: Business equipment, tools, machinery
- clinical: Medical/service delivery imagery
- business: Business operations, office scenes
- testimonial: Client testimonial imagery (may be stock photo representing satisfaction/results)
- decorative: Pure background/pattern with no informational value
- stock: Generic stock photography unrelated to business

CRITICAL RULES:
1. If context_type is 'testimonial', 'decorative', or 'stock', describe ONLY what is visible. DO NOT claim the image shows the business location or is taken at the business.
2. If context_type is 'facility', the image DOES show the actual business location - include business name.
3. If the image is generic/stock (landscape, food, objects), describe it accurately WITHOUT forcing business connection.
4. For testimonial images: Focus on the emotion/concept (e.g., \'satisfied client\', \'positive outcome\') not false location claims.

TASK:
Using the image and all the context above, suggest the following exactly in JSON format:
{
  \"file_name_suggestion\": \"...\",
  \"title\": \"...\",
  \"alt_text\": \"...\",
  \"caption\": \"...\",
  \"description\": \"...\",
  \"keywords\": [\"...\",\"...\",\"...\"],
  \"confidence\": 0.00,
  \"issues\": [\"...\"]
}

GUIDELINES:
- Describe only what you *see* in the image — do not assume unseen details.
- If brand_name_visible = true, you _may_ include the business_name in file_name_suggestion, title, alt_text, and caption. If false, do **not** include the business_name.
- File name: lowercase, hyphen-separated, no special characters, max ~50 characters. Example: \"main-street-health-clinic-hamilton-exterior.jpg\"
- Title: aim for approx 60 characters, reflecting visible content + relevant context.
- Alt text: descriptive, 8-140 characters, avoid leading \"image of\" or \"picture of\", focus on what's visible and page context.
- Caption: one complete sentence, linking visible scene + business context (if visible or relevant).
- Description: 2-3 full sentences, richer context combining visible content + business context + page focus_keyword when appropriate.
- Keywords[]: 3-5 short phrases (singular nouns where possible), relevant to what's visible + page's focus_keyword + service_area or ideal_customer when appropriate (but keep natural, avoid stuffing).
- If visible text or signage appears in the image (e.g., business name on building, signboard), include \"text_in_image_detected\" in issues[].
- If you included business_name but brand_name_visible = false, include \"brand_name_assumed\" in issues[] and set confidence ≤ 0.70.
- If confidence < 0.50: include \"low_confidence\" in issues[].
- If context_type = \"decorative\" (pure background/pattern, no meaningful info), set alt_text = \"\" and title = \"\", and include \"decorative_image\" in issues[].
- Use the business context (business_name, industry, service_area, ideal_customer, brand_voice, unique_value) **to guide tone, keywords, and relevance**, but **only** in so far as the image content supports it.

OUTPUT exactly one JSON object as indicated above.";

		// Build USER message with parameters
		$user_message = "business_name: {$business_name_clean}
industry: {$industry_clean}
business_type: {$business_type}
ideal_customer: {$ideal_customer}
service_area: {$location_clean}
brand_voice: {$brand_voice}
unique_value: {$uvp_clean}
image_url: {$image_url}
original_filename: {$original_filename}
brand_name_visible: {$brand_name_visible}
context_type: {$context_type}
page_title: {$page_title}
focus_keyword: {$focus_keyword}
page_role: {$page_role}
model_pass: {$model_pass}";

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
	 * Validate AI response for quality and appropriateness
	 *
	 * @param array $metadata Sanitized metadata from AI
	 * @param array $context Business context
	 * @return true|WP_Error True if valid, WP_Error if validation fails
	 */
	private function validate_ai_response( $metadata, $context ) {
		// Get issues array from metadata
		$issues = isset( $metadata['issues'] ) ? $metadata['issues'] : array();
		$confidence = isset( $metadata['confidence'] ) ? $metadata['confidence'] : 0.0;

		// Check for critical issues flagged by AI
		if ( in_array( 'brand_name_assumed', $issues, true ) ) {
			error_log( '[MSH OpenAI] CRITICAL: AI assumed brand name when brand_name_visible=false' );
			return new WP_Error( 'brand_name_assumed', 'AI incorrectly included business name' );
		}

		if ( $confidence < 0.5 ) {
			error_log( sprintf( '[MSH OpenAI] LOW CONFIDENCE: %.2f (threshold: 0.50)', $confidence ) );
			return new WP_Error( 'low_confidence', "AI confidence too low: {$confidence}" );
		}

		// Log warnings for non-critical issues
		if ( in_array( 'text_in_image_detected', $issues, true ) ) {
			error_log( '[MSH OpenAI] INFO: Text detected in image - may need OCR or manual review' );
		}

		if ( in_array( 'decorative_image', $issues, true ) ) {
			error_log( '[MSH OpenAI] INFO: Image classified as decorative (empty alt/title expected)' );
			// Skip length validation for decorative images
			return true;
		}

		// Length guards (only for non-decorative images)
		$title_len = mb_strlen( $metadata['title'] );
		$alt_len   = mb_strlen( $metadata['alt_text'] );

		if ( $title_len < 15 || $title_len > 70 ) {
			error_log( "[MSH OpenAI] Title length invalid: {$title_len} chars (need 15-70)" );
			return new WP_Error( 'title_length', "Title must be 15-70 characters (got {$title_len})" );
		}

		if ( $alt_len < 8 || $alt_len > 140 ) {
			error_log( "[MSH OpenAI] ALT text length invalid: {$alt_len} chars (need 8-140)" );
			return new WP_Error( 'alt_length', "ALT text must be 8-140 characters (got {$alt_len})" );
		}

		// Simplified banned terms check (AI should handle business name logic via brand_name_visible)
		$banned_terms = array(
			'brand imagery',
			'placeholder',
			'stock image',
			'generic image',
		);

		error_log( "[MSH OpenAI] Validator - checking banned terms: " . implode( ', ', $banned_terms ) );

		// Check all metadata fields for banned terms
		$fields_to_check = array( 'title', 'alt_text', 'caption', 'description' );
		foreach ( $fields_to_check as $field ) {
			if ( empty( $metadata[ $field ] ) ) {
				continue;
			}

			$lower = strtolower( $metadata[ $field ] );
			foreach ( $banned_terms as $term ) {
				if ( strpos( $lower, $term ) !== false ) {
					error_log( "[MSH OpenAI] Banned term '{$term}' found in {$field}: {$metadata[$field]}" );
					return new WP_Error( 'banned_term', "Contains banned term: {$term}" );
				}
			}
		}

		// Sensitive content guard (medical context)
		// If industry is healthcare and we detect medical terms, be extra strict
		if ( ! empty( $context['industry'] ) && $context['industry'] === 'healthcare' ) {
			$medical_terms = array( 'patient', 'treatment', 'medical', 'clinic', 'doctor', 'nurse', 'therapy' );
			$contains_medical = false;

			foreach ( $fields_to_check as $field ) {
				$lower = strtolower( $metadata[ $field ] );
				foreach ( $medical_terms as $term ) {
					if ( strpos( $lower, $term ) !== false ) {
						$contains_medical = true;
						break 2;
					}
				}
			}

			// If medical content detected, ensure no location/business name unless verified
			if ( $contains_medical ) {
				$sensitive_terms = array();
				if ( ! empty( $context['business_name'] ) ) {
					$sensitive_terms[] = strtolower( $context['business_name'] );
				}
				if ( ! empty( $context['city'] ) ) {
					$sensitive_terms[] = strtolower( $context['city'] );
				}

				foreach ( $fields_to_check as $field ) {
					$lower = strtolower( $metadata[ $field ] );
					foreach ( $sensitive_terms as $term ) {
						if ( strpos( $lower, $term ) !== false ) {
							error_log( "[MSH OpenAI] Medical content contains unverified location/business: {$term} in {$field}" );
							// TODO: In Phase 2, check OCR results before rejecting
							return new WP_Error( 'sensitive_content', "Medical content with unverified business/location reference" );
						}
					}
				}
			}
		}

		return true;
	}
}

// Initialize the connector
new MSH_OpenAI_Connector();
