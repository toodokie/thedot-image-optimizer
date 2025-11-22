<?php
/**
 * OpenAI Vision API Connector
 *
 * Integrates OpenAI GPT-4 Vision to analyze images and generate metadata.
 *
 * @package MSH_Image_Optimizer
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once dirname(__FILE__) . '/class-msh-metadata-normalizer.php';

class MSH_OpenAI_Connector
{

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

	private $metadata_normalizer;

	/**
	 * Constructor.
	 *
	 * Registers the OpenAI connector with the AI metadata filter.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_filter('msh_ai_generate_metadata', array($this, 'generate_metadata_via_openai'), 10, 3);
	}

	private function get_metadata_normalizer()
	{
		if (!$this->metadata_normalizer) {
			$this->metadata_normalizer = new MSH_Metadata_Normalizer();
		}

		return $this->metadata_normalizer;
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
	public function batch_generate_metadata_parallel($payloads, $concurrency = 3)
	{
		if (empty($payloads) || !class_exists('MSH_Concurrent_Queue')) {
			return array();
		}

		$t_start = microtime(true);
		$queue = new MSH_Concurrent_Queue($concurrency);
		$results = array();

		// Get API key (same for all requests in batch)
		$first_payload = reset($payloads);
		$api_key = !empty($first_payload['api_key']) ? $first_payload['api_key'] : get_option('msh_ai_api_key', '');

		// For bundled access mode, use platform key
		if (empty($api_key) && !empty($first_payload['access_mode']) && $first_payload['access_mode'] === 'bundled') {
			$api_key = defined('MSH_PLATFORM_OPENAI_KEY') ? MSH_PLATFORM_OPENAI_KEY : '';
		}

		if (empty($api_key)) {
			error_log('[MSH OpenAI Batch] No API key available');
			return array();
		}

		// Queue all requests
		foreach ($payloads as $attachment_id => $payload) {
			$image_url = wp_get_attachment_url($attachment_id);
			if (!$image_url) {
				continue;
			}

			// Build prompt messages
			$context = $payload['context'];
			$business_name = !empty($context['business_name']) ? $context['business_name'] : 'this business';
			$industry = !empty($context['industry_label']) ? $context['industry_label'] : 'professional services';

			$location_parts = array();
			if (!empty($context['city'])) {
				$location_parts[] = $context['city'];
			}
			if (!empty($context['country'])) {
				$location_parts[] = $context['country'];
			}
			$location = implode(', ', $location_parts);
			$uvp = !empty($context['uvp']) ? $context['uvp'] : '';

			$features = !empty($payload['features']) ? $payload['features'] : array();
			$ai_options = !empty($payload['ai_options']) ? $payload['ai_options'] : array();
			$language_choice = isset($ai_options['language']) ? strtolower((string) $ai_options['language']) : 'auto';
			$resolved_language = $this->normalize_language_choice($language_choice, $ai_options, $context);

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
				'model' => 'gpt-4o',
				'messages' => $messages,
				'max_tokens' => 200,
				'temperature' => 0,
			);

			// Add to queue
			$queue->add(
				(string) $attachment_id,
				self::API_ENDPOINT,
				array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				),
				wp_json_encode($body),
				15 // 15 second timeout per request
			);
		}

		error_log(sprintf('[MSH OpenAI Batch] Queued %d requests with concurrency=%d', count($payloads), $concurrency));

		// Execute all requests in parallel
		$raw_results = $queue->execute();

		// Process results
		foreach ($raw_results as $attachment_id => $result) {
			if (!$result['success']) {
				error_log(sprintf('[MSH OpenAI Batch] Failed for attachment %d: %s', $attachment_id, $result['error']));
				$results[$attachment_id] = null;
				continue;
			}

			// Parse response
			$payload = $payloads[$attachment_id];
			$parsed_metadata = $this->parse_openai_response($result['response'], $payload['context']);

			if ($parsed_metadata) {
				$results[$attachment_id] = $parsed_metadata;
			} else {
				$results[$attachment_id] = null;
			}
		}

		$duration = microtime(true) - $t_start;
		error_log(sprintf(
			'[MSH OpenAI Batch] Completed %d images in %.2fs (%.2fs/image)',
			count($results),
			$duration,
			count($results) > 0 ? $duration / count($results) : 0
		));

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
	public function generate_metadata_via_openai($metadata, $payload, $generator)
	{
		$attachment_id = $payload['attachment_id'] ?? 'UNKNOWN';
		error_log(sprintf(
			'[MSH OpenAI DEBUG] generate_metadata_via_openai() CALLED for attachment #%s',
			$attachment_id
		));

		// If another filter already provided metadata, don't override
		if (is_array($metadata) && !empty($metadata)) {
			error_log(sprintf(
				'[MSH OpenAI DEBUG] Attachment #%s: Metadata already provided, skipping',
				$attachment_id
			));
			return $metadata;
		}

		// Get API key
		// Priority: 1) Payload API key (BYOK), 2) Option API key (BYOK), 3) Platform key for bundled credits
		$api_key = !empty($payload['api_key']) ? $payload['api_key'] : get_option('msh_ai_api_key', '');

		// For bundled access mode, use platform key from wp-config.php
		if (empty($api_key) && !empty($payload['access_mode']) && $payload['access_mode'] === 'bundled') {
			$api_key = defined('MSH_PLATFORM_OPENAI_KEY') ? MSH_PLATFORM_OPENAI_KEY : '';
			if (!empty($api_key)) {
				error_log('[MSH OpenAI] Using platform API key for bundled access');
			}
		}

		if (empty($api_key)) {
			error_log('[MSH OpenAI] No API key available');
			return null;
		}

		// Get image URL
		$attachment_id = $payload['attachment_id'];
		$image_url = wp_get_attachment_url($attachment_id);

		error_log(sprintf(
			'[MSH OpenAI DEBUG] Attachment #%d: wp_get_attachment_url() returned: %s',
			$attachment_id,
			$image_url ? $image_url : 'FALSE/NULL'
		));

		if (!$image_url) {
			error_log('[MSH OpenAI] Could not get image URL for attachment ' . $attachment_id);
			return null;
		}

		// Get business context
		$context = $payload['context'];
		$business_name = !empty($context['business_name']) ? $context['business_name'] : 'this business';
		$industry = !empty($context['industry_label']) ? $context['industry_label'] : 'professional services';

		// Build location from city, region, country
		$location_parts = array();
		if (!empty($context['city'])) {
			$location_parts[] = $context['city'];
		}
		if (!empty($context['country'])) {
			$location_parts[] = $context['country'];
		}
		$location = implode(', ', $location_parts);

		$uvp = !empty($context['uvp']) ? $context['uvp'] : '';

		// Build AI prompt with enabled features
		$features = !empty($payload['features']) ? $payload['features'] : array();
		$ai_options = !empty($payload['ai_options']) ? $payload['ai_options'] : array();

		error_log(sprintf(
			'[MSH OpenAI DEBUG] Attachment #%d: features=%s, ai_options=%s',
			$attachment_id,
			json_encode($features),
			json_encode($ai_options)
		));
		$language_choice = isset($ai_options['language']) ? strtolower((string) $ai_options['language']) : 'auto';
		$resolved_language = $this->normalize_language_choice($language_choice, $ai_options, $context);

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
		error_log(sprintf(
			'[AI_CALL] #%d url=%s model=gpt-4o',
			$attachment_id,
			substr($image_url, 0, 80) . (strlen($image_url) > 80 ? '...' : '')
		));

		// Call OpenAI Vision API with new message structure
		$response = $this->call_openai_vision($image_url, $messages, $api_key);

		if (is_wp_error($response)) {
			error_log('[MSH OpenAI] API Error: ' . $response->get_error_message());
			return null;
		}

		// Extract token usage from response for future token tracking
		$response_data = json_decode($response, true);
		if (isset($response_data['usage'])) {
			$tokens_used = array(
				'prompt_tokens' => $response_data['usage']['prompt_tokens'] ?? 0,
				'completion_tokens' => $response_data['usage']['completion_tokens'] ?? 0,
				'total_tokens' => $response_data['usage']['total_tokens'] ?? 0,
			);

			error_log(sprintf(
				'[MSH OpenAI] Token usage - prompt: %d, completion: %d, total: %d',
				$tokens_used['prompt_tokens'],
				$tokens_used['completion_tokens'],
				$tokens_used['total_tokens']
			));

			// DIAGNOSTIC: Log AI response success
			error_log(sprintf(
				'[AI_RESP] #%d ok=1 tokens=%d/%d/%d',
				$attachment_id,
				$tokens_used['prompt_tokens'],
				$tokens_used['completion_tokens'],
				$tokens_used['total_tokens']
			));

			// Future: Deduct from token manager when class exists
			// if ( class_exists( 'MSH_Token_Manager' ) ) {
			//     $token_manager = MSH_Token_Manager::get_instance();
			//     $token_manager->deduct( $attachment_id, $tokens_used['total_tokens'], 'vision_metadata' );
			// }

			// Log to telemetry system (integrated with MSH_Telemetry)
			do_action('msh_log_token_usage', $attachment_id, $tokens_used, self::PROMPT_VERSION);
		}

		// Parse response into metadata structure
		$parsed_metadata = $this->parse_openai_response($response, $context);

		// CRITICAL FIX: Check if parse returned WP_Error (from validator)
		if (is_wp_error($parsed_metadata)) {
			error_log(sprintf(
				'[MSH OpenAI] Validation error: %s (code: %s)',
				$parsed_metadata->get_error_message(),
				$parsed_metadata->get_error_code()
			));
			return null; // Return null to trigger fallback to heuristic metadata
		}

		if (empty($parsed_metadata)) {
			error_log('[MSH OpenAI] Failed to parse metadata from response');
			return null;
		}

		// Apply AI regeneration filters if specified
		$ai_options = !empty($payload['ai_options']) ? $payload['ai_options'] : array();
		if (!empty($ai_options['ai_regeneration'])) {
			$ai_mode = !empty($ai_options['ai_mode']) ? $ai_options['ai_mode'] : 'fill-empty';
			$ai_fields = !empty($ai_options['ai_fields']) ? $ai_options['ai_fields'] : array();

			// Filter to only requested fields
			if (!empty($ai_fields)) {
				$field_map = array(
					'title' => 'title',
					'alt_text' => 'alt_text',
					'caption' => 'caption',
					'description' => 'description',
				);

				$filtered_metadata = array();
				foreach ($ai_fields as $field) {
					if (isset($field_map[$field]) && isset($parsed_metadata[$field_map[$field]])) {
						$filtered_metadata[$field_map[$field]] = $parsed_metadata[$field_map[$field]];
					}
				}
				$parsed_metadata = $filtered_metadata;
			}

			// Apply fill-empty mode: only include fields that are currently empty
			if ($ai_mode === 'fill-empty') {
				$current_title = get_the_title($attachment_id);
				$current_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
				$current_caption = wp_get_attachment_caption($attachment_id);
				$current_description = get_post_field('post_content', $attachment_id);

				// Remove fields that already have values
				if (!empty($current_title) && isset($parsed_metadata['title'])) {
					unset($parsed_metadata['title']);
				}
				if (!empty($current_alt) && isset($parsed_metadata['alt_text'])) {
					unset($parsed_metadata['alt_text']);
				}
				if (!empty($current_caption) && isset($parsed_metadata['caption'])) {
					unset($parsed_metadata['caption']);
				}
				if (!empty($current_description) && isset($parsed_metadata['description'])) {
					unset($parsed_metadata['description']);
				}

				error_log('[MSH OpenAI] Fill-empty mode: filtered to ' . count($parsed_metadata) . ' empty fields');
			}
		}

		// CRITICAL: Enforce seo_mode rules (no branding/location when disabled).
		$seo_mode = isset($context['seo_mode']) ? (bool) $context['seo_mode'] : true;
		$business_name = $context['business_name'] ?? '';

		if (!$seo_mode) {
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

			// BATCH 1.a: Check if brand should be preserved (TEAM context with brand mode enabled)
			$ct = strtolower(trim((string) ($context['type'] ?? '')));
			$bm = !empty($context['brand_name_visible']) && ($context['brand_name_visible'] === 'true' || $context['brand_name_visible'] === true || $context['brand_name_visible'] === '1');
			$preserve_brand = ($ct === 'team' && $bm);

			$disallowed_terms = $location_terms;
			if ($business_name && !$preserve_brand) {
				$disallowed_terms[] = $business_name;
			}

			foreach (array('title', 'alt_text', 'caption') as $field) {
				if (isset($parsed_metadata[$field]) && $parsed_metadata[$field] !== '') {
					$clean_value = $this->strip_disallowed_terms($parsed_metadata[$field], $disallowed_terms);
					if ($clean_value === '') {
						$clean_value = $this->get_non_seo_fallback($field, $context);
					}
					if ($clean_value !== $parsed_metadata[$field]) {
						error_log(sprintf('[MSH OpenAI] seo_mode=false: Sanitised %s field', $field));
					}
					$parsed_metadata[$field] = $clean_value;
				}
			}

			if (isset($parsed_metadata['keywords'])) {
				$parsed_metadata['keywords'] = array();
			}

		}

		if (isset($parsed_metadata['description'])) {
			$parsed_metadata['description'] = $this->normalize_description_pollution($parsed_metadata['description'], $context, $seo_mode);
		}

		error_log('[MSH OpenAI] Successfully generated metadata for attachment ' . $attachment_id);
		error_log('[MSH OpenAI] Generated title: ' . ($parsed_metadata['title'] ?? 'N/A'));
		error_log('[MSH OpenAI] Generated description: ' . ($parsed_metadata['description'] ?? 'N/A'));

		return $parsed_metadata;
	}

	private function strip_disallowed_terms($value, array $terms)
	{
		$value = (string) $value;
		if ($value === '' || empty($terms)) {
			return trim($value);
		}

		foreach ($terms as $term) {
			$term = trim((string) $term);
			if ($term === '') {
				continue;
			}
			$pattern = '/\b' . preg_quote($term, '/') . "(?:'s)?\b/iu";
			$value = preg_replace($pattern, '', $value);
			$value = str_ireplace($term, '', $value);
		}

		$value = preg_replace('/,+/u', ',', $value);
		$value = preg_replace('/\bin\s*,/iu', 'in', $value);
		$value = preg_replace('/\bin\s+offers/iu', 'offers', $value);
		$value = preg_replace('/\s{2,}/u', ' ', trim($value));
		$value = preg_replace('/\s+([,.;:])/u', '$1', $value);

		return trim($value);
	}

	private function get_non_seo_fallback($field, $context)
	{
		$type = isset($context['final_context_type']) ? $context['final_context_type'] : ($context['type'] ?? 'stock');

		switch ($field) {
			case 'title':
				return ucfirst($type) . ' Image';
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
	private function build_prompt_messages($attachment_id, $image_url, $business_name, $industry, $location, $uvp, $context, $features = array(), $language = 'en')
	{
		// Sanitize inputs
		$business_name_clean = wp_strip_all_tags($business_name);
		$industry_clean = wp_strip_all_tags($industry);
		$location_clean = wp_strip_all_tags($location);
		$uvp_clean = wp_strip_all_tags($uvp);

		// Get context data
		// DEBUG: Log entire context array to diagnose context_type issue
		error_log('[MSH OpenAI DEBUG] Full context array: ' . wp_json_encode($context));

		$context_type = isset($context['final_context_type']) ? $context['final_context_type'] : ($context['type'] ?? 'stock');
		$business_type = isset($context['business_type']) ? $context['business_type'] : '';
		$ideal_customer = isset($context['ideal_customer']) ? $context['ideal_customer'] : '';
		$brand_voice = isset($context['brand_voice']) ? $context['brand_voice'] : '';

		$brand_flag = isset($context['brand_name_visible']) ? (bool) $context['brand_name_visible'] : false;
		$brand_flag = (bool) apply_filters('msh_brand_visibility_flag', $brand_flag, $context);
		$brand_name_visible = $brand_flag ? 'true' : 'false';

		error_log(sprintf(
			'[MSH OpenAI] Prompt v%s - context_type: %s, brand_name_visible: %s',
			self::PROMPT_VERSION,
			$context_type,
			$brand_name_visible
		));

		// Get attachment metadata for original filename
		$original_filename = basename(get_attached_file($attachment_id));

		// Get page context (if available)
		$page_title = isset($context['page_title']) ? wp_strip_all_tags((string) $context['page_title']) : '';
		$focus_keyword = isset($context['focus_keyword']) ? wp_strip_all_tags((string) $context['focus_keyword']) : '';
		$page_role = isset($context['page_role']) ? wp_strip_all_tags((string) $context['page_role']) : 'general_content_image';

		// Determine model pass type
		$model_pass = 'low_detail'; // Phase 0B: Use low detail for token optimization

		// Get locale
		$locale = !empty($context['locale']) ? $context['locale'] : 'en-US';
		$context_set_manually = !empty($context['context_set_manually']);
		$brand_manual = !empty($context['brand_name_visible_manual']);
		$ocr_flag = !empty($context['ocr_found_brand']);
		$ai_search_friendly = array_key_exists('ai_search_friendly', $context) ? (bool) $context['ai_search_friendly'] : true;
		$seo_mode = array_key_exists('seo_mode', $context) ? (bool) $context['seo_mode'] : true; // Default ON
		$manual_flag_str = $context_set_manually ? 'true' : 'false';
		$manual_brand_str = $brand_manual ? 'true' : 'false';
		$ocr_flag_str = $ocr_flag ? 'true' : 'false';
		$ai_search_flag_str = $ai_search_friendly ? 'true' : 'false';
		$seo_mode_str = $seo_mode ? 'true' : 'false';
		$downgrades = array();
		if (!empty($context['context_trace']['downgraded_reasons']) && is_array($context['context_trace']['downgraded_reasons'])) {
			$downgrades = $context['context_trace']['downgraded_reasons'];
		}
		$downgrade_summary = empty($downgrades) ? 'none' : implode(', ', array_map('sanitize_key', $downgrades));

		// Phase 0B: Generate context ID for compact prompt
		$ctx_id = $this->generate_context_id($context);

		// Phase 0B: Ultra-compressed system prompt (~20 tokens)
		// All context and rules moved to user message for token efficiency
		$system_message = "AI metadata assistant. Context:{$ctx_id}. JSON only. No commentary.";

		// Phase 0D: Build compact user message with conditional business context
		$brand_voice_val = !empty($brand_voice) ? $brand_voice : 'neutral';
		$brand_voice_compact = $this->compress_brand_voice($brand_voice_val);
		$bn = $this->promptSafe($business_name_clean);
		$bl = $this->promptSafe($location_clean);
		$sv = isset($context['service_keywords']) ? $this->compact_service_keywords($context['service_keywords']) : '';

		$include_brand_block = $seo_mode || $brand_flag;

		$flag_parts = array(
			sprintf('ctx:%s', $ctx_id),
			sprintf('ct:%s', $context_type),
			sprintf('cm:%d', $context_set_manually ? 1 : 0),
			sprintf('seo:%d', $seo_mode ? 1 : 0),
			sprintf('bm:%d', $brand_flag ? 1 : 0),
			sprintf('bv:%s', $brand_voice_compact),
		);

		if ($include_brand_block) {
			if ('' !== $bn) {
				$flag_parts[] = 'bn:' . $bn;
			}
			if ('' !== $bl) {
				$flag_parts[] = 'bl:' . $bl;
			}
			if ('' !== $sv) {
				$flag_parts[] = 'sv:' . $sv;
			}
		}

		$flag_line = implode('|', $flag_parts);

		$page_title_compact = $this->promptSafe($page_title, 6);
		$focus_keyword_compact = $this->promptSafe($focus_keyword);
		$has_page_context = ('' !== $page_title_compact) || ('' !== $focus_keyword_compact) || ('' !== $page_role);

		$page_line = $has_page_context
			? sprintf('pg:ti=%s|kw=%s|pr=%s', $page_title_compact, $focus_keyword_compact, $page_role)
			: '';

		$schema_line = 'schema:{fn,t,a,c,d,k[],s[],attr[],conf,iss[]}|req:fn,t,a,c,d,k[],s[],attr[],conf,iss[]';
		$rules_line = sprintf(
			'rules: cm1->ct final; describe visible scene; brand allowed only if ct in {logo,team,facility,equipment,service-icon,brand_logo} or (ct in {clinical,business,testimonial} & bm1) — otherwise ban brand (stock/decor always ban). TEAM RULE: if ct=team AND bm=1 → MUST include {bn} in both t AND d, even if image looks generic. stock/decor: if seo=0 describe scene only (no brand/location/service/CTA); if seo=1 describe scene then add one short SEO tail in d only (location+service) while keeping t/a/fn scenic-only. seo1 (other contexts): weave one location (bl or pg.ti) + one service from sv into description only; keep title/alt/caption purely visual. seo0 ban brand/location/cta. fill k/s=3-4 nouns. len caps t60/a125/c150/d200. tone letters p,f,c,t,n,b. tone=%s.',
			$brand_voice_compact
		);

		$message_lines = array($flag_line);
		if ($has_page_context) {
			$message_lines[] = $page_line;
		}
		$message_lines[] = $schema_line;
		$message_lines[] = $rules_line;

		$user_message = implode("\n", $message_lines);

		$bn_set = ($include_brand_block && '' !== $bn) ? 1 : 0;
		$bl_set = ($include_brand_block && '' !== $bl) ? 1 : 0;
		$sv_count = 0;
		if ($include_brand_block && '' !== $sv) {
			$sv_count = substr_count($sv, ',') + 1;
		}

		error_log(sprintf(
			'[AI_CALL] #%d in_bytes=%d flags ct=%s seo=%d bm=%d bn_set=%d bl_set=%d sv_count=%d pg=%d',
			$attachment_id,
			strlen($user_message),
			$context_type,
			$seo_mode ? 1 : 0,
			$brand_flag ? 1 : 0,
			$bn_set,
			$bl_set,
			$sv_count,
			$has_page_context ? 1 : 0
		));

		// Phase 0B: Log audit trail with ctx_id and first 80 chars of prompt
		error_log(
			sprintf(
				'[MSH SmartMode] ctx:%s | prompt=%s…',
				$ctx_id,
				substr($user_message, 0, 80)
			)
		);

		return array(
			'system' => $system_message,
			'user' => $user_message,
		);
	}

	/**
	 * Phase 0B: Generate context ID fingerprint for compact prompts
	 *
	 * @param array $context Context array from detect_context()
	 * @return string Context ID (e.g., "ctx_9f11db7")
	 */
	private function generate_context_id($context)
	{
		$site_id = get_option('siteurl');
		$locale = get_locale();
		$business = isset($context['business_name']) ? $context['business_name'] : '';
		$industry = isset($context['industry']) ? $context['industry'] : '';
		$seo_mode = isset($context['seo_mode']) ? (int) $context['seo_mode'] : 0;

		// Generate stable fingerprint
		$fingerprint = sha1(
			$site_id . '|' .
			$locale . '|' .
			$business . '|' .
			$industry . '|' .
			$seo_mode
		);

		return 'ctx_' . substr($fingerprint, 0, 7);
	}

	/**
	 * Phase 0B: Sanitize prompt values to prevent injection and reduce token count
	 *
	 * @param mixed $val Value to sanitize
	 * @param int   $maxTokens Maximum words to keep (approx. 1.3 tokens per word)
	 * @return string Sanitized value
	 */
	private function promptSafe($val, $maxTokens = 12)
	{
		$s = wp_strip_all_tags((string) $val);
		$s = preg_replace('/\s+/', ' ', $s);
		$s = str_replace(array('|', "\n", "\r", '{', '}', ':'), ' ', $s);
		return wp_trim_words($s, $maxTokens, '');
	}

	/**
	 * Phase 0B: Convert array to safe CSV string for compact prompts
	 *
	 * @param array|string $arr Array of values or single value
	 * @param int          $maxItems Maximum items to include
	 * @param int          $maxLenPerItem Maximum tokens per item
	 * @return string CSV string
	 */
	private function csvSafe($arr, $maxItems = 5, $maxLenPerItem = 4)
	{
		if (!is_array($arr)) {
			$arr = array($arr);
		}
		$arr = array_filter(
			array_map(
				function ($x) use ($maxLenPerItem) {
					return $this->promptSafe($x, $maxLenPerItem);
				},
				array_slice($arr, 0, $maxItems)
			)
		);
		return implode(',', $arr);
	}

	/**
	 * Phase 0C: Compress service keywords into shorthand tokens (max 3 items).
	 *
	 * @param array|string $services Service keywords from context.
	 * @param int          $maxItems Maximum items to emit.
	 * @return string Comma-delimited shorthand list.
	 */
	private function compact_service_keywords($services, $maxItems = 3)
	{
		if (empty($services)) {
			return '';
		}

		if (!is_array($services)) {
			$services = explode(',', (string) $services);
		}

		$map = array(
			'physiotherapy' => 'pt',
			'physical therapy' => 'pt',
			'physical-therapy' => 'pt',
			'physicaltherapy' => 'pt',
			'rehabilitation' => 'rehab',
			'rehab' => 'rehab',
			'chiropractic' => 'chiro',
			'chiropractor' => 'chiro',
			'massage therapy' => 'massage',
			'massage-therapy' => 'massage',
			'occupational therapy' => 'ot',
			'occupational-therapy' => 'ot',
			'occupationaltherapy' => 'ot',
			'acupuncture' => 'acu',
			'nutrition' => 'nutri',
			'wellness coaching' => 'well',
			'mental health' => 'mh',
			'telehealth' => 'tele',
			'counseling' => 'couns',
		);

		$shorthand = array();

		foreach ($services as $service) {
			$normalized = strtolower(trim(wp_strip_all_tags((string) $service)));

			if ('' === $normalized) {
				continue;
			}

			$key = preg_replace('/\s+/', ' ', $normalized);

			if (isset($map[$key])) {
				$short = $map[$key];
			} else {
				$short = $this->abbreviate_service_token($normalized);
			}

			if ('' === $short) {
				continue;
			}

			$shorthand[] = $short;

			if (count($shorthand) >= $maxItems) {
				break;
			}
		}

		if (empty($shorthand)) {
			return '';
		}

		return implode(',', array_unique($shorthand));
	}

	/**
	 * Generate a fallback shorthand token for services without explicit mapping.
	 *
	 * @param string $service Normalized service string.
	 * @return string
	 */
	private function abbreviate_service_token($service)
	{
		$service = strtolower(trim(preg_replace('/[^a-z0-9 ]/', '', $service)));

		if ('' === $service) {
			return '';
		}

		$words = preg_split('/\s+/', $service);

		if (count($words) > 1 && count($words) <= 3) {
			$initials = '';

			foreach ($words as $word) {
				$initials .= substr($word, 0, 1);
			}

			if (strlen($initials) >= 2) {
				return $initials;
			}
		}

		$replacements = array(
			'therapy' => 'ther',
			'services' => 'svc',
			'service' => 'svc',
			'treatment' => 'treat',
			'management' => 'mgmt',
			'training' => 'train',
			'program' => 'prog',
			'clinic' => 'clinic',
		);

		foreach ($replacements as $needle => $replacement) {
			if (false !== strpos($service, $needle)) {
				$service = str_replace($needle, $replacement, $service);
			}
		}

		$service = str_replace(' ', '', $service);

		return substr($service, 0, 5);
	}

	/**
	 * Phase 0C: Compress brand voice to one-character tone key.
	 *
	 * @param string $voice Brand voice string.
	 * @return string One-character key.
	 */
	private function compress_brand_voice($voice)
	{
		$voice = strtolower(trim($voice));

		if ('' === $voice) {
			return 'n'; // neutral default
		}

		$map = array(
			'professional' => 'p',
			'friendly' => 'f',
			'casual' => 'c',
			'technical' => 't',
			'neutral' => 'n',
			'bold' => 'b',
			'confident' => 'c',
			'calm' => 'c',
		);

		if (isset($map[$voice])) {
			return $map[$voice];
		}

		return substr($voice, 0, 1);
	}

	/**
	 * Phase 0C.1: Enforce character limits with graceful word-boundary trimming.
	 *
	 * @param string $text Text to limit.
	 * @param int    $max_chars Maximum characters allowed.
	 * @return string
	 */
	private function limit_text($text, $max_chars, $options = array())
	{
		$text = trim((string) $text);

		if ($text === '') {
			return '';
		}

		$strlen = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
		$substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';

		if ($strlen($text) <= $max_chars) {
			return $text;
		}

		$truncated = $substr($text, 0, $max_chars);
		$last_space = strrpos($truncated, ' ');

		if (false !== $last_space && $last_space > ($max_chars - 25)) {
			$truncated = $substr($truncated, 0, $last_space);
		}

		$truncated = rtrim($truncated, " ,.;:-");

		$sentence_breaks = array(
			strrpos($truncated, '.'),
			strrpos($truncated, '!'),
			strrpos($truncated, '?'),
		);

		$best_break = false;
		foreach ($sentence_breaks as $break) {
			if (false !== $break && (false === $best_break || $break > $best_break)) {
				$best_break = $break;
			}
		}

		if (false !== $best_break && $best_break > ($strlen($truncated) * 0.6)) {
			return rtrim($substr($truncated, 0, $best_break + 1));
		}

		if (!empty($options['fallback_sentence'])) {
			$fallback = trim((string) $options['fallback_sentence']);
			if ('' !== $fallback) {
				if (!preg_match('/[.!?]$/', $fallback)) {
					$fallback .= '.';
				}

				$fallback_len = $strlen($fallback);
				$space_needed = $fallback_len + 1; // Include space separator.

				if ($space_needed < $max_chars) {
					$available = $max_chars - $space_needed;
					if ($available > 0) {
						$truncated = $substr($truncated, 0, $available);
						$truncated = rtrim($truncated, " ,.;:-");

						if ('' !== $truncated) {
							return $truncated . ' ' . $fallback;
						}
					}
				}
			}
		}

		return $truncated . '...';
	}

	/**
	 * Build a short fallback sentence for descriptions when trimming occurs.
	 *
	 * @param array $context Metadata context.
	 * @return string
	 */
	private function build_description_fallback_sentence($context)
	{
		$type = isset($context['type']) ? $context['type'] : ($context['context_type'] ?? '');
		if (in_array($type, array('stock', 'decorative'), true)) {
			return '';
		}

		$seo_mode = array_key_exists('seo_mode', $context) ? (bool) $context['seo_mode'] : true;
		if (!$seo_mode) {
			return '';
		}

		$business_name = trim(wp_strip_all_tags($context['business_name'] ?? ''));
		$location = trim(wp_strip_all_tags($context['location'] ?? ''));
		$brand_allowed = !empty($context['brand_name_visible']);

		if ('' === $business_name) {
			$brand_allowed = false;
		}

		$service = '';
		if (!empty($context['service_keywords'])) {
			$services = is_array($context['service_keywords']) ? $context['service_keywords'] : explode(',', (string) $context['service_keywords']);
			foreach ($services as $svc) {
				$svc = trim(wp_strip_all_tags((string) $svc));
				if ('' !== $svc) {
					$service = $svc;
					break;
				}
			}
		}

		$subject = '';
		if ($brand_allowed) {
			$subject = $business_name;
			if ('' !== $location) {
				$subject .= ' in ' . $location;
			}
		} elseif ('' !== $location) {
			$subject = 'This ' . strtolower($context['context_type'] ?? 'practice') . ' in ' . $location;
		} else {
			$subject = 'This practice';
		}

		if ('' !== $service) {
			$sentence = sprintf('%s offers %s support', $subject, $service);
		} else {
			$sentence = sprintf('%s offers supportive care', $subject);
		}

		return rtrim($sentence) . '.';
	}

	/**
	 * Sanitize an array of keyword/subject terms.
	 *
	 * @param mixed $terms Candidate terms.
	 * @param int   $max   Maximum number of terms to keep.
	 * @return array
	 */
	private function sanitize_terms_array($terms, $max = 6)
	{
		if (empty($terms)) {
			return array();
		}

		if (!is_array($terms)) {
			$terms = array($terms);
		}

		$clean = array();

		foreach ($terms as $term) {
			$term = trim(wp_strip_all_tags((string) $term));

			if ($term === '') {
				continue;
			}

			$term = preg_replace('/\s+/', ' ', $term);

			if (strlen($term) > 40) {
				$term = substr($term, 0, 40);
			}

			$clean[] = $term;

			if (count($clean) >= $max) {
				break;
			}
		}

		return array_values(array_unique($clean));
	}

	/**
	 * Generate fallback keyword/subject terms from visible text/context.
	 *
	 * @param string $text Text seed.
	 * @param int    $max Maximum terms to output.
	 * @param array  $context_terms Additional seeds (services, page role, etc).
	 * @return array
	 */
	private function generate_terms_from_text($text, $max = 4, $context_terms = array())
	{
		$pool = array();

		$normalized = strtolower(wp_strip_all_tags((string) $text));
		$normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
		$words = preg_split('/\s+/', $normalized);

		$stopwords = $this->get_keyword_stopwords();

		foreach ($words as $word) {
			$word = trim($word);

			if ($word === '' || strlen($word) < 3) {
				continue;
			}

			if (in_array($word, $stopwords, true)) {
				continue;
			}

			$pool[] = $word;
		}

		foreach ((array) $context_terms as $extra) {
			$extra = strtolower(wp_strip_all_tags((string) $extra));
			$extra = preg_replace('/[^a-z0-9\s]/', ' ', $extra);
			$parts = preg_split('/\s+/', $extra);

			foreach ($parts as $part) {
				$part = trim($part);

				if ($part === '' || strlen($part) < 3) {
					continue;
				}

				if (in_array($part, $stopwords, true)) {
					continue;
				}

				$pool[] = $part;
			}
		}

		$pool = array_values(array_unique($pool));

		if (empty($pool)) {
			return array();
		}

		$terms = array();

		foreach ($pool as $candidate) {
			$terms[] = ucwords($candidate);

			if (count($terms) >= $max) {
				break;
			}
		}

		return $terms;
	}

	/**
	 * Stopword list for keyword/subject fallback generation.
	 *
	 * @return array
	 */
	private function get_keyword_stopwords()
	{
		return array(
			'a',
			'an',
			'and',
			'as',
			'at',
			'be',
			'for',
			'from',
			'in',
			'is',
			'it',
			'of',
			'on',
			'or',
			'our',
			'the',
			'this',
			'that',
			'to',
			'with',
			'your',
		);
	}

	/**
	 * Ensure required text fields exist before validation.
	 *
	 * @param array $metadata Metadata array.
	 * @param array $context Request context.
	 * @return array
	 */
	private function ensure_required_text_fields($metadata, $context)
	{
		if (empty($metadata['title'])) {
			$fallback_title = $this->generate_fallback_title($metadata, $context);

			if (!empty($fallback_title)) {
				$metadata['title'] = $fallback_title;
				error_log('[MSH OpenAI] Filled title fallback: ' . $fallback_title);
			}
		}

		error_log("[MSH DEBUG enforce_team] final_desc=" . $metadata['description']);
		return $metadata;
	}

	/**
	 * Apply brand/location/service rules to match non-AI behavior.
	 *
	 * @param array $metadata Sanitised metadata.
	 * @param array $context  Context payload.
	 * @param bool  $seo_mode Whether SEO mode is enabled.
	 * @return array
	 */
	private function apply_contextual_term_filters(array $metadata, array $context, $seo_mode)
	{
		// Phase 2E: Temporarily disable all SEO-specific injection logic.
		$seo_mode = false;
		$context_type = isset($context['type']) ? $context['type'] : ($context['context_type'] ?? 'stock');
		$brand_name_visible = !empty($context['brand_name_visible']);
		$context_set_manually = !empty($context['context_set_manually']);
		$brand_allowed = $this->is_brand_allowed_for_context($context_type, $brand_name_visible, $context_set_manually);
		$type = $context['type'] ?? 'stock';
		$cm = !empty($context['manual']) || !empty($context['context_set_manually']);
		$team = ($type === 'team' && $cm && in_array($context['brand_name_visible'] ?? false, array(true, 'true', '1', 1), true));
		$skip_legacy_seo = false;

		// TEAM always bypasses legacy SEO injection entirely
		if ($team) {
			return $metadata;
		}

		if (in_array($type, array('stock', 'decorative'), true)) {
			$skip_legacy_seo = true;
		}

		$business_name = trim(wp_strip_all_tags($context['business_name'] ?? ''));
		if ('' !== $business_name && !$brand_allowed) {
			$metadata = $this->strip_terms_from_fields(
				array('title', 'alt_text', 'caption', 'description'),
				array($business_name),
				$metadata
			);
		}

		$team_manual_brand = (
			$context_type === 'team'
			&& (!empty($context['manual']) || !empty($context['context_set_manually']))
			&& in_array($context['brand_name_visible'] ?? false, array(true, 'true', '1', 1), true)
		);

		if ($team_manual_brand) {
			return $metadata;
		}

		$location_terms = $this->build_location_terms($context);
		$service_terms = $this->build_service_terms($context);

		if (empty($location_terms) && empty($service_terms)) {
			return $metadata;
		}

		if ($skip_legacy_seo) {
			return $metadata;
		}

		// Stock/decorative: keep location/service references out of title/alt/caption even when SEO is on.
		if (in_array($context_type, array('stock', 'decorative'), true)) {
			$metadata = $this->strip_terms_from_fields(array('title', 'alt_text', 'caption'), $location_terms, $metadata);
			$metadata = $this->strip_terms_from_fields(array('title', 'alt_text', 'caption'), $service_terms, $metadata);
		}

		// When SEO mode is off, strip these terms globally.
		if (!$seo_mode) {
			$metadata = $this->strip_terms_from_fields(array('title', 'alt_text', 'caption', 'description'), $location_terms, $metadata);
			$metadata = $this->strip_terms_from_fields(array('title', 'alt_text', 'caption', 'description'), $service_terms, $metadata);
		}

		return $metadata;
	}

	/**
	 * Determine if brand names are allowed for the given context type.
	 *
	 * @param string $context_type Context type.
	 * @param bool   $brand_visible Brand name visible flag.
	 * @param bool   $context_manual Context set manually flag.
	 * @return bool
	 */
	private function is_brand_allowed_for_context($context_type, $brand_visible, $context_manual)
	{
		$context_type = sanitize_key((string) $context_type);

		$always_allowed = array('logo', 'team', 'facility', 'equipment', 'service-icon', 'brand_logo');
		if (in_array($context_type, $always_allowed, true)) {
			return true;
		}

		if (in_array($context_type, array('stock', 'decorative'), true)) {
			return false;
		}

		if (in_array($context_type, array('clinical', 'business'), true)) {
			return (bool) $brand_visible;
		}

		if ('testimonial' === $context_type) {
			return (bool) ($brand_visible || $context_manual);
		}

		return false;
	}

	/**
	 * Collect location-related terms for stripping.
	 *
	 * @param array $context Context payload.
	 * @return array
	 */
	private function build_location_terms(array $context)
	{
		$terms = array();

		$location_keys = array('location', 'city', 'state', 'province', 'region', 'country');
		foreach ($location_keys as $key) {
			if (empty($context[$key])) {
				continue;
			}
			$chunks = preg_split('/[,|]/', (string) $context[$key]);
			foreach ($chunks as $chunk) {
				$clean = trim(wp_strip_all_tags($chunk));
				if ('' !== $clean) {
					$terms[] = $clean;
				}
			}
		}

		$expanded = array();
		foreach ($terms as $term) {
			$expanded[] = $term;
			if ($term !== '') {
				$expanded[] = $term . "'s";
			}
		}

		return array_values(array_unique(array_filter(array_map('trim', $expanded))));
	}

	/**
	 * Collect service terms for stripping.
	 *
	 * @param array $context Context payload.
	 * @return array
	 */
	private function build_service_terms(array $context)
	{
		if (empty($context['service_keywords'])) {
			return array();
		}

		$services = is_array($context['service_keywords']) ? $context['service_keywords'] : explode(',', (string) $context['service_keywords']);
		$terms = array();

		foreach ($services as $service) {
			$clean = trim(wp_strip_all_tags((string) $service));
			if ('' !== $clean) {
				$terms[] = $clean;
			}
		}

		$expanded = array();
		foreach ($terms as $term) {
			$expanded[] = $term;
			if ($term !== '') {
				$expanded[] = $term . "'s";
			}
		}

		$expanded = array_merge(
			$expanded,
			array(
				'healthcare services',
				'professional healthcare',
				'health services',
			)
		);

		return array_values(array_unique(array_filter(array_map('trim', $expanded))));
	}

	/**
	 * Strip terms from specified metadata fields.
	 *
	 * @param array $fields Fields to process.
	 * @param array $terms  Terms to strip.
	 * @param array $metadata Metadata array.
	 * @return array
	 */
	private function strip_terms_from_fields(array $fields, array $terms, array $metadata)
	{
		if (empty($terms)) {
			return $metadata;
		}

		foreach ($fields as $field) {
			if (empty($metadata[$field])) {
				continue;
			}

			$metadata[$field] = $this->strip_terms_from_text($metadata[$field], $terms);
		}

		return $metadata;
	}

	/**
	 * Strip terms from a text string (case-insensitive, word-boundary aware where possible).
	 *
	 * @param string $text Text to clean.
	 * @param array  $terms Terms to remove.
	 * @return string
	 */
	private function strip_terms_from_text($text, array $terms)
	{
		foreach ($terms as $term) {
			$term = trim($term);
			if ('' === $term) {
				continue;
			}

			$pattern = '/\b' . preg_quote($term, '/') . '\b/iu';
			$text = preg_replace($pattern, '', $text);
		}

		$text = preg_replace('/\s{2,}/', ' ', $text);

		return trim($text);
	}

	/**
	 * Build a fallback title from filename/context data.
	 *
	 * @param array $metadata Metadata array.
	 * @param array $context Request context.
	 * @return string
	 */
	private function generate_fallback_title($metadata, $context)
	{
		$candidates = array(
			$metadata['file_name_suggestion'] ?? '',
			$context['page_title'] ?? '',
			$context['focus_keyword'] ?? '',
		);

		if (!empty($context['service_keywords'])) {
			$services = is_array($context['service_keywords'])
				? $context['service_keywords']
				: explode(',', (string) $context['service_keywords']);

			if (!empty($services)) {
				$candidates[] = $services[0];
			}
		}

		$candidates[] = $context['type'] ?? ($context['context_type'] ?? '');
		$candidates[] = 'Image';

		foreach ($candidates as $candidate) {
			$formatted = $this->format_title_candidate($candidate);

			if (!empty($formatted)) {
				return $formatted;
			}
		}

		return '';
	}

	/**
	 * Format a fallback title candidate into Title Case words.
	 *
	 * @param string $candidate Raw candidate string.
	 * @return string
	 */
	private function format_title_candidate($candidate)
	{
		$candidate = (string) $candidate;
		$candidate = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $candidate);
		$candidate = preg_replace('/[_\-]+/', ' ', $candidate);
		$candidate = preg_replace('/\s+/', ' ', $candidate);
		$candidate = trim($candidate);

		if ('' === $candidate) {
			return '';
		}

		return ucwords($candidate);
	}

	/**
	 * Normalize the language choice coming from the UI/AI options.
	 *
	 * @param string $language Selected language (or 'auto').
	 * @param array  $ai_options AI options array.
	 * @param array  $context Context payload.
	 * @return string Resolved language code.
	 */
	private function normalize_language_choice($language, $ai_options, $context)
	{
		$language = strtolower((string) $language);
		$supported = array('en', 'es', 'fr', 'de', 'pt', 'it');

		if ($language === 'auto' || !in_array($language, $supported, true)) {
			$language = $this->resolve_auto_language($ai_options, $context);
		}

		if (!in_array($language, $supported, true)) {
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
	private function resolve_auto_language($ai_options, $context)
	{
		$candidates = array();

		if (!empty($ai_options['profile_locale'])) {
			$candidates[] = $ai_options['profile_locale'];
		}

		if (!empty($context['locale'])) {
			$candidates[] = $context['locale'];
		}

		if (empty($candidates)) {
			$candidates[] = get_locale();
		}

		foreach ($candidates as $candidate) {
			if (!$candidate) {
				continue;
			}
			$short = strtolower((string) $candidate);
			$parts = preg_split('/[-_]/', $short);
			if (!empty($parts[0])) {
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
	private function check_rate_limit($estimated_tokens = 500)
	{
		$tpm_limit = 30000; // OpenAI BYOK limit
		$headroom = 0.8;    // Use only 80% of limit for safety
		$safe_limit = $tpm_limit * $headroom;

		// Get rolling window data (last 60 seconds)
		$window_key = 'msh_openai_token_window';
		$window_data = get_transient($window_key);

		if (false === $window_data) {
			$window_data = array();
		}

		// Clean old entries (older than 60 seconds)
		$now = time();
		$window_data = array_filter($window_data, function ($entry) use ($now) {
			return ($now - $entry['timestamp']) < 60;
		});

		// Calculate tokens used in last 60 seconds
		$tokens_used_last_60s = array_sum(array_column($window_data, 'tokens'));

		// Check if adding this request would exceed limit
		if (($tokens_used_last_60s + $estimated_tokens) >= $safe_limit) {
			error_log(sprintf(
				'[MSH RATE LIMIT] Would exceed safe limit: used=%d, estimated=%d, safe_limit=%d. Delaying request.',
				$tokens_used_last_60s,
				$estimated_tokens,
				$safe_limit
			));

			// Wait for oldest entry to expire
			if (!empty($window_data)) {
				$oldest = min(array_column($window_data, 'timestamp'));
				$wait_time = 60 - ($now - $oldest) + 1; // Wait until oldest expires + 1 sec buffer
				if ($wait_time > 0 && $wait_time < 60) {
					sleep($wait_time);
				}
			}
		}

		// Log this request to the window
		$window_data[] = array(
			'timestamp' => $now,
			'tokens' => $estimated_tokens,
		);

		// Save updated window (expires in 65 seconds)
		set_transient($window_key, $window_data, 65);

		return true;
	}

	/**
	 * Call OpenAI Vision API
	 */
	private function call_openai_vision($image_url, $messages, $api_key)
	{
		// RATE LIMIT GATE: Check token bucket before making request
		$this->check_rate_limit(350); // Phase 0C estimate (prompt+completion target <350 tokens)

		// For local development, convert image to base64 if URL is not publicly accessible
		$image_data = $this->get_image_data($image_url);

		$body = array(
			'model' => 'gpt-4o', // Using GPT-4o for vision (faster and cheaper than gpt-4-vision-preview)
			'messages' => array(
				array(
					'role' => 'system',
					'content' => $messages['system'],
				),
				array(
					'role' => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $messages['user'],
						),
						array(
							'type' => 'image_url',
							'image_url' => array(
								'url' => $image_data,
								'detail' => 'low', // Phase 0B: Use low detail (85 tokens) + short keys for token optimization
							),
						),
					),
				),
			),
			'max_tokens' => 200,
			'temperature' => 0, // Deterministic outputs, no variance in retries
		);

		// Log request payload size for optimization tracking
		$request_json = wp_json_encode($body);
		$request_size = strlen($request_json);
		error_log(sprintf(
			'[MSH AI Token Optimization] Request payload: %d bytes (includes short key schema)',
			$request_size
		));

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				),
				'body' => $request_json,
				'timeout' => 30,
			)
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($status_code !== 200) {
			$error_message = 'HTTP ' . $status_code;
			$decoded = json_decode($response_body, true);
			if (isset($decoded['error']['message'])) {
				$error_message .= ': ' . $decoded['error']['message'];
			}
			return new WP_Error('openai_api_error', $error_message);
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
	private function get_image_data($image_url)
	{
		// Check if Live Link URL is configured (Local by Flywheel feature)
		$live_link_url = get_option('msh_ai_live_link_url', '');

		if (!empty($live_link_url)) {
			// Replace local domain with Live Link domain
			$local_url = home_url('/');
			$live_link_url = trailingslashit($live_link_url);

			$converted_url = str_replace($local_url, $live_link_url, $image_url);

			if ($converted_url !== $image_url) {
				error_log('[MSH OpenAI] Using Live Link URL: ' . $converted_url);
				return $converted_url;
			}
		}

		// Check if URL is local (localhost, .local, 127.0.0.1, etc.)
		$is_local = preg_match('/(localhost|\.local|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/i', $image_url);

		if ($is_local) {
			error_log('[MSH OpenAI] Local URL detected, converting to base64');

			// Convert to base64 for local development
			// Use wp_get_upload_dir() for robust path mapping (handles schemes, ports, subdirs, multisite, etc.)
			$uploads = wp_get_upload_dir();

			if (strpos($image_url, $uploads['baseurl']) === 0) {
				// Get relative path from upload base URL
				$relative = ltrim(str_replace($uploads['baseurl'], '', $image_url), '/');
				$absolute_path = trailingslashit($uploads['basedir']) . $relative;

				if (file_exists($absolute_path)) {
					// PERFORMANCE: Resize image before base64 encoding to reduce payload
					$resized_path = $this->resize_for_ai($absolute_path);
					$image_data = file_get_contents($resized_path);
					$base64 = base64_encode($image_data);
					$mime_type = 'image/jpeg'; // Always JPEG after resize

					error_log('[MSH OpenAI] Converted to base64: ' . $resized_path);

					// Clean up temp file if different from original
					if ($resized_path !== $absolute_path && file_exists($resized_path)) {
						@unlink($resized_path);
					}

					return "data:{$mime_type};base64,{$base64}";
				}

				error_log('[MSH OpenAI] Local image file not found: ' . $absolute_path);
			} else {
				error_log('[MSH OpenAI] Image URL not in uploads directory: ' . $image_url);
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
	private function resize_for_ai($image_path)
	{
		// Check if GD or Imagick is available
		if (!function_exists('wp_get_image_editor')) {
			error_log('[MSH OpenAI] wp_get_image_editor not available, using original image');
			return $image_path;
		}

		// Create temp file path
		$path_info = pathinfo($image_path);
		$temp_path = $path_info['dirname'] . '/' . $path_info['filename'] . '-ai-temp.' . $path_info['extension'];

		// Get image editor
		$editor = wp_get_image_editor($image_path);
		if (is_wp_error($editor)) {
			error_log('[MSH OpenAI] Image editor error: ' . $editor->get_error_message());
			return $image_path;
		}

		// Get current size
		$size = $editor->get_size();
		if (!$size) {
			return $image_path;
		}

		$width = $size['width'];
		$height = $size['height'];

		// Phase 0B: Calculate new dimensions (max 640px on long edge, optimized for detail:low)
		$max_dimension = 640;
		if ($width > $max_dimension || $height > $max_dimension) {
			if ($width > $height) {
				$new_width = $max_dimension;
				$new_height = intval(($height / $width) * $max_dimension);
			} else {
				$new_height = $max_dimension;
				$new_width = intval(($width / $height) * $max_dimension);
			}

			$editor->resize($new_width, $new_height, false);
		}

		// Set JPEG quality to 80%
		$editor->set_quality(80);

		// Save to temp file
		$saved = $editor->save($temp_path, 'image/jpeg');
		if (is_wp_error($saved)) {
			error_log('[MSH OpenAI] Image save error: ' . $saved->get_error_message());
			return $image_path;
		}

		// Use the actual saved path from the editor (WordPress may modify the filename)
		$actual_path = isset($saved['path']) ? $saved['path'] : $temp_path;

		// Check file size
		if (!file_exists($actual_path)) {
			error_log('[MSH OpenAI] Temp file not created: ' . $actual_path);
			return $image_path;
		}

		$file_size = filesize($actual_path);
		error_log(sprintf(
			'[MSH OpenAI] Resized image: %dx%d → %dx%d, %s → %s',
			$width,
			$height,
			$new_width ?? $width,
			$new_height ?? $height,
			size_format(filesize($image_path), 2),
			size_format($file_size, 2)
		));

		return $actual_path;
	}

	/**
	 * Parse OpenAI response into metadata array
	 */
	private function parse_openai_response($response_json, $context)
	{
		$data = json_decode($response_json, true);

		if (!isset($data['choices'][0]['message']['content'])) {
			return null;
		}

		// INSTRUMENTATION: Extract token usage from OpenAI response
		$usage = $data['usage'] ?? array();
		$prompt_tokens = $usage['prompt_tokens'] ?? 0;
		$completion_tokens = $usage['completion_tokens'] ?? 0;
		$total_tokens = $usage['total_tokens'] ?? 0;

		$content = trim($data['choices'][0]['message']['content']);

		// Remove markdown code blocks if present
		$content = preg_replace('/^```json\s*/m', '', $content);
		$content = preg_replace('/\s*```$/m', '', $content);

		$metadata = json_decode($content, true);

		if (!is_array($metadata)) {
			error_log('[MSH OpenAI] Invalid JSON in response: ' . $content);
			return null;
		}

		// Log short key optimization metrics
		$short_key_size = strlen($content);
		error_log(sprintf(
			'[MSH AI Token Optimization] Raw response (short keys): %d bytes',
			$short_key_size
		));
		error_log('[MSH AI Token Optimization] Short key response: ' . $content);

		// INSTRUMENTATION: Per-image telemetry (Phase 0B audit trail)
		$attachment_id = $context['attachment_id'] ?? 0;
		error_log(sprintf(
			'[MSH TELEMETRY] image_id=%d | model=gpt-4o | detail=low | schema=short_keys_v4 | prompt_tokens=%d | completion_tokens=%d | total_tokens=%d | response_bytes=%d',
			$attachment_id,
			$prompt_tokens,
			$completion_tokens,
			$total_tokens,
			$short_key_size
		));

		// Flag if tokens exceed target
		if ($total_tokens > 600) {
			error_log(sprintf(
				'[MSH ALERT] Image %d exceeded 600 token threshold: %d tokens (prompt=%d, completion=%d)',
				$attachment_id,
				$total_tokens,
				$prompt_tokens,
				$completion_tokens
			));
		}

		// Expand short keys to verbose keys (backward compatible - accepts both)
		$metadata = MSH_Key_Compactor::expand_keys($metadata);

		// Ensure required text fields exist before validation (fallbacks prevent AI regressions)
		$metadata = $this->ensure_required_text_fields($metadata, $context);

		// Log expanded size for comparison
		$verbose_equivalent = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$verbose_size = strlen($verbose_equivalent);
		$savings = round((1 - ($short_key_size / $verbose_size)) * 100, 1);
		error_log(sprintf(
			'[MSH AI Token Optimization] Verbose equivalent: %d bytes | Savings: %d bytes (%.1f%%)',
			$verbose_size,
			$verbose_size - $short_key_size,
			$savings
		));

		// Extract confidence and issues from new structure
		$confidence = isset($metadata['confidence']) ? floatval($metadata['confidence']) : 0.0;
		$issues = isset($metadata['issues']) && is_array($metadata['issues']) ? $metadata['issues'] : array();

		// Log quality metadata
		error_log(sprintf(
			'[MSH OpenAI] AI response - confidence: %.2f, issues: %s',
			$confidence,
			empty($issues) ? 'none' : implode(', ', $issues)
		));

		// Validate required fields
		$required = array('title', 'alt_text', 'caption', 'description');
		foreach ($required as $field) {
			// Allow empty title/alt_text only if decorative_image flag is set
			if (in_array('decorative_image', $issues, true) && in_array($field, array('title', 'alt_text'), true)) {
				continue; // Decorative images can have empty title/alt
			}
			if (empty($metadata[$field])) {
				error_log('[MSH OpenAI] Missing required field: ' . $field);
				return null;
			}
		}

		$seo_mode_context = array_key_exists('seo_mode', $context) ? (bool) $context['seo_mode'] : true;

		// Sanitize metadata and enforce hard length limits
		$description_fallback = $this->build_description_fallback_sentence($context);

		$title = $this->limit_text(sanitize_text_field($metadata['title']), 60);
		$alt_text = $this->limit_text(sanitize_text_field($metadata['alt_text']), 125);
		$caption = $this->limit_text(sanitize_text_field($metadata['caption']), 150);
		$description = $this->limit_text(sanitize_textarea_field($metadata['description']), 200, array(
			'fallback_sentence' => $description_fallback,
		));

		$sanitized = array(
			'title' => $title,
			'alt_text' => $alt_text,
			'caption' => $caption,
			'description' => $description,
			'confidence' => $confidence,
			'issues' => $issues,
			'prompt_version' => self::PROMPT_VERSION,
		);

		// Add file_name_suggestion if provided (new field replacing filename_slug)
		if (!empty($metadata['file_name_suggestion'])) {
			$sanitized['filename_slug'] = sanitize_title($metadata['file_name_suggestion']);
			error_log('[MSH OpenAI] AI suggested filename: ' . $sanitized['filename_slug']);
		}

		$context_terms = array();
		if (!empty($context['service_keywords'])) {
			$context_terms = is_array($context['service_keywords'])
				? $context['service_keywords']
				: explode(',', (string) $context['service_keywords']);
		}
		if (!empty($context['focus_keyword'])) {
			$context_terms[] = $context['focus_keyword'];
		}
		if (!empty($context['page_title'])) {
			$context_terms[] = $context['page_title'];
		}

		$keywords = $this->sanitize_terms_array(isset($metadata['keywords']) ? $metadata['keywords'] : array(), 6);
		if (empty($keywords)) {
			$keywords = $this->generate_terms_from_text(
				$alt_text . ' ' . $description,
				4,
				$context_terms
			);
			if (!empty($keywords)) {
				error_log('[MSH OpenAI] Filled keywords fallback: ' . implode(', ', $keywords));
			}
		} else {
			error_log('[MSH OpenAI] AI suggested keywords: ' . implode(', ', $keywords));
		}
		$sanitized['keywords'] = $keywords;

		$subject_context_terms = array(
			$context['page_role'] ?? '',
			$context['type'] ?? ($context['context_type'] ?? ''),
			$context['final_context_type'] ?? '',
			$context['industry'] ?? '',
		);

		$subjects = $this->sanitize_terms_array(isset($metadata['subjects']) ? $metadata['subjects'] : array(), 4);
		if (empty($subjects)) {
			$subjects = $this->generate_terms_from_text(
				$title . ' ' . $caption,
				3,
				array_merge($subject_context_terms, $keywords)
			);
			if (empty($subjects) && !empty($keywords)) {
				$subjects = array_slice($keywords, 0, 3);
			}
			if (!empty($subjects)) {
				error_log('[MSH OpenAI] Filled subjects fallback: ' . implode(', ', $subjects));
			}
		}
		$sanitized['subjects'] = $subjects;

		$sanitized = $this->apply_contextual_term_filters($sanitized, $context, $seo_mode_context);

		// Validate the AI response for quality
		$validation = $this->validate_ai_response($sanitized, $context);
		// Normalize context type for all downstream checks
		$ct = strtolower(trim((string) ($context['type'] ?? '')));
		$cm = !empty($context['manual']) || !empty($context['context_set_manually']);
		$bn_v = in_array($context['brand_name_visible'] ?? false, array(true, 'true', '1', 1), true);

		if (is_wp_error($validation)) {
			if (!($ct === 'team' && $cm && $bn_v)) {
				error_log('[MSH OpenAI] Validation failed: ' . $validation->get_error_message());
				return $validation; // Return WP_Error to trigger escalation or fallback
			}
		}

		// Phase 3A: use real SEO mode for stock tail only (legacy SEO remains disabled elsewhere).
		$seo_mode_flag = array_key_exists('seo_mode', $context) ? (bool) $context['seo_mode'] : true;

		$stock_context = in_array($ct, array('stock', 'decorative'), true);

		if ($stock_context) {
			$original_description = $sanitized['description'] ?? '';
			$description_value = $original_description;
			$business_name = trim($context['business_name'] ?? '');

			if ($business_name !== '') {
				foreach (array('title', 'alt_text', 'caption') as $field) {
					if (isset($sanitized[$field]) && $sanitized[$field] !== '') {
						$sanitized[$field] = $this->strip_disallowed_terms($sanitized[$field], array($business_name));
					}
				}
				if ($description_value !== '') {
					$description_value = $this->strip_disallowed_terms($description_value, array($business_name));
				}
			}

			$location_terms = $this->build_location_terms($context);
			if (!empty($location_terms) && $description_value !== '') {
				$description_value = $this->strip_disallowed_terms($description_value, $location_terms);
			}

			$service_terms = $this->build_service_terms($context);
			if (!empty($service_terms) && $description_value !== '') {
				$description_value = $this->strip_disallowed_terms($description_value, $service_terms);
			}

			$cta_terms = array('book now', 'book', 'schedule', 'call', 'contact', 'learn more', 'visit', 'reserve', 'discover', 'request', 'start', 'apply', 'join', 'enroll');
			if ($description_value !== '') {
				$description_value = $this->strip_disallowed_terms($description_value, $cta_terms);
			}

			$sanitized['description'] = $description_value;
		}

		if ($stock_context && $seo_mode_flag) {
			$tail = $this->build_stock_seo_tail($context);
			if ($tail !== '') {
				$current_desc = trim(preg_replace('/\s+/', ' ', (string) ($sanitized['description'] ?? '')));
				if ($current_desc !== '') {
					$current_desc = rtrim($current_desc, '.!? ') . '.';
				}
				$sanitized['description'] = trim($current_desc . ' ' . $tail);
			}
		}

		$sanitized = $this->enforce_team_metadata_rules($sanitized, $context);

		$ct = $context['type'] ?? 'UNKNOWN';
		$seo = (int) $seo_mode_flag;
		$cm = !empty($context['manual']) || !empty($context['context_set_manually']);
		$bn_v = in_array($context['brand_name_visible'] ?? false, array(true, 'true', '1', 1), true);

		if ($ct === 'team' && $cm) {
			error_log(
				"[MSH DEBUG Batch1 FINAL] id={$attachment_id} ct={$ct} seo={$seo} t={$sanitized['title']} d={$sanitized['description']}"
			);
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
	private function validate_context_rules($context, &$metadata, &$issues)
	{
		// Note: Context array uses 'type' key, not 'context_type'
		$type = strtolower(trim((string) ($context['type'] ?? 'stock')));
		$cm = !empty($context['manual']) || !empty($context['context_set_manually']);
		$team_brand_flag = in_array($context['brand_name_visible'] ?? false, array(true, 'true', '1', 1), true);

		if ($type === 'team' && $cm && $team_brand_flag) {
			return true;
		}

		$business_name = isset($context['business_name']) ? strtolower($context['business_name']) : '';

		// Combine all text fields for checking
		$all_text = strtolower(implode(' ', array(
			$metadata['title'] ?? '',
			$metadata['alt_text'] ?? '',
			$metadata['caption'] ?? '',
			$metadata['description'] ?? '',
		)));

		$brand_found = !empty($business_name) && strpos($all_text, $business_name) !== false;

		// Helper function to add issue if not already present
		$add_issue = function ($issue_name) use (&$issues) {
			if (!in_array($issue_name, $issues, true)) {
				$issues[] = $issue_name;
			}
		};

		// HARD RULES (reject immediately)

		// 1) stock, decorative → NEVER include business_name
		if (in_array($type, array('stock', 'decorative'), true) && $brand_found) {
			error_log("[MSH Validator] REJECT: context_type={$type} but business_name found in output");
			$add_issue('context_mismatch');
			return new WP_Error('context_mismatch', "Business name not allowed for {$type} images");
		}

		// 2) testimonial → PROHIBITED phrases
		if ($type === 'testimonial') {
			$forbidden = array(
				'at ' . $business_name,
				'in our facility',
				$business_name . ' client',
				'our clinic',
				'our office',
			);

			foreach ($forbidden as $phrase) {
				if (strpos($all_text, $phrase) !== false) {
					error_log("[MSH Validator] REJECT: testimonial contains forbidden phrase: '{$phrase}'");
					$add_issue('context_mismatch');
					return new WP_Error('context_mismatch', "Testimonial images cannot claim business location/ownership");
				}
			}
		}

		// 3) clinical, business → Follow brand_name_visible strictly
		if (in_array($type, array('clinical', 'business'), true)) {
			$brand_visible = isset($context['brand_name_visible']) && $context['brand_name_visible'] === 'true';

			if (!$brand_visible && $brand_found) {
				error_log("[MSH Validator] REJECT: context_type={$type}, brand_name_visible=false but brand found");
				$add_issue('brand_name_assumed');
				return new WP_Error('brand_name_assumed', "Business name not permitted when brand_name_visible=false");
			}
		}

		// SOFT RULES (warn only)

		// 4) service-icon → Should only have brand if text/logo visible
		if ($type === 'service-icon' && $brand_found) {
			$brand_visible = isset($context['brand_name_visible']) && $context['brand_name_visible'] === 'true';

			if (!$brand_visible) {
				error_log("[MSH Validator] WARN: service-icon has business_name but brand_name_visible=false (soft warning)");
				$add_issue('brand_name_assumed');
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
	private function validate_ai_response($metadata, $context)
	{
		$validator = MSH_Context_Aware_Validator::get_instance();

		$seo_mode = isset($context['seo_mode']) ? (bool) $context['seo_mode'] : true;
		$loc_mode = isset($context['loc_mode']) ? $context['loc_mode'] : (
			isset($context['policy']['loc_mode']) ? $context['policy']['loc_mode'] : 'auto'
		);

		return $validator->validate($context, $metadata, $seo_mode, $loc_mode);
	}

	private function normalize_description_pollution($description, $context, $seo_mode)
	{
		$normalizer = $this->get_metadata_normalizer();

		$location_terms = $this->build_location_terms($context);
		$service_terms = $this->build_service_terms($context);

		return $normalizer->normalize_description(
			$description,
			$context,
			$seo_mode,
			$location_terms,
			$service_terms
		);
	}

	/**
	 * Apply TEAM-specific rules after global SEO cleanup to ensure branding and tie-in sentences.
	 *
	 * @param array $metadata Sanitised metadata array.
	 * @param array $context  Business context information.
	 * @return array Updated metadata with TEAM rules enforced.
	 */
	private function enforce_team_metadata_rules(array $metadata, array $context)
	{
		$ct = $context['type'] ?? '';
		$cm = !empty($context['manual']) || !empty($context['context_set_manually']);
		$bm = in_array($context['brand_name_visible'] ?? false, array(true, 'true', '1', 1), true);

		if ($ct !== 'team' || !$cm || !$bm) {
			return $metadata;
		}

		$pollution_terms = array_merge(
			$this->build_location_terms($context),
			$this->build_service_terms($context),
			array('practice', 'offers', 'support')
		);
		if (!empty($metadata['description']) && !empty($pollution_terms)) {
			$metadata['description'] = $this->strip_disallowed_terms($metadata['description'], $pollution_terms);
		}

		$business_name = trim($context['business_name'] ?? '');
		if ($business_name === '') {
			return $metadata;
		}

		if (empty($metadata['issues']) || !is_array($metadata['issues'])) {
			$metadata['issues'] = array();
		}

		if (stripos($metadata['title'] ?? '', $business_name) === false) {
			if (!in_array('missing_bn_title', $metadata['issues'], true)) {
				$metadata['issues'][] = 'missing_bn_title';
			}
			$metadata['title'] = $this->buildTeamTitle($business_name);
		}

		$description = trim($metadata['description'] ?? '');
		$description_has_bn = stripos($description, $business_name) !== false;
		if ($description === '') {
			$description = $this->build_description_fallback_sentence($context);
		}

		$seo_mode = array_key_exists('seo_mode', $context) ? (bool) $context['seo_mode'] : true;
		if (!$seo_mode) {
			$location_terms = array_filter(array_unique(array(
				$context['city'] ?? '',
				$context['region'] ?? '',
				$context['country'] ?? '',
				$context['service_area'] ?? '',
				$context['location'] ?? '',
			)));
			if (!empty($location_terms)) {
				$description = $this->strip_disallowed_terms($description, $location_terms);
			}

			$service_terms = array();
			if (!empty($context['service_keywords'])) {
				$service_terms = is_array($context['service_keywords'])
					? $context['service_keywords']
					: explode(',', (string) $context['service_keywords']);
				$service_terms = array_filter(array_map('trim', $service_terms));
			}
			if (!empty($service_terms)) {
				$description = $this->strip_disallowed_terms($description, $service_terms);
			}

			$cta_terms = array('book now', 'book', 'schedule', 'call', 'contact', 'learn more', 'visit', 'reserve', 'discover', 'request', 'start', 'apply', 'join', 'enroll');
			$description = $this->strip_disallowed_terms($description, $cta_terms);
		}

		$scenic_sentence = $this->extract_first_sentence($description);
		if ($scenic_sentence === '') {
			$scenic_sentence = $this->build_description_fallback_sentence($context);
		}
		$scenic_sentence = rtrim($scenic_sentence, '.!? ') . '.';

		$tie_in = $this->buildTeamTieIn($business_name, $context['brand_voice'] ?? 'professional');
		$description_final = trim($scenic_sentence . ' ' . $tie_in);
		$description_final = $this->limit_text(sanitize_textarea_field($description_final), 200, array(
			'fallback_sentence' => $this->build_description_fallback_sentence($context),
		));

		if (!$description_has_bn && !in_array('missing_bn_desc', $metadata['issues'], true)) {
			$metadata['issues'][] = 'missing_bn_desc';
		}

		$metadata['description'] = $description_final;
		error_log("[MSH DEBUG enforce_team] final_desc=" . $metadata['description']);
		return $metadata;
	}

	/**
	 * Build SEO tail sentence for stock/decorative contexts.
	 *
	 * @param array $context Context payload.
	 * @return string
	 */
	private function build_stock_seo_tail(array $context)
	{
		$city = trim((string) ($context['city'] ?? ''));
		$region = trim((string) ($context['region'] ?? ''));
		$place = '';

		if ($city !== '' && $region !== '') {
			$place = "{$city}, {$region}";
		} elseif ($city !== '') {
			$place = $city;
		}

		$services = $context['service_keywords'] ?? array();
		if (!is_array($services)) {
			$services = explode(',', (string) $services);
		}
		$services = array_values(array_filter(array_map('trim', $services)));
		$service = $services[0] ?? '';

		if ($place === '' && $service === '') {
			return '';
		}

		if ($place !== '' && $service !== '') {
			return "Ideal for content related to {$service} in {$place}.";
		}

		if ($place !== '') {
			return "Ideal for content set in {$place}.";
		}

		return "Ideal for {$service} content.";
	}

	/**
	 * Extract the first sentence from a piece of text.
	 *
	 * @param string $text Source text.
	 * @return string First sentence or original text if no delimiter found.
	 */
	private function extract_first_sentence($text)
	{
		$text = trim((string) $text);
		if ($text === '') {
			return '';
		}

		$sentences = preg_split('/(?<=[.!?])\s+/', $text, 2, PREG_SPLIT_NO_EMPTY);
		if (!empty($sentences)) {
			return trim($sentences[0]);
		}

		return $text;
	}

	/**
	 * Prepend business name to title with separator, enforcing 60 char max
	 *
	 * @param string $title Original title
	 * @param string $business_name Business name to prepend
	 * @return string Title with business name prepended
	 */
	private function prependBnToTitle($title, $business_name)
	{
		$title = trim($title);
		if ($title === '') {
			return $business_name;
		}
		$candidate = "{$business_name} – {$title}";
		// Enforce max 60 chars
		return mb_strlen($candidate) <= 60
			? $candidate
			: mb_substr($candidate, 0, 57) . '…';
	}

	/**
	 * Build organic TEAM title (Batch 1.b)
	 * Instead of "{Brand} – {Scenic}", use team-specific templates.
	 */
	private function buildTeamTitle($business_name)
	{
		$templates = array(
			'Healthcare Team – %s',
			'Team at %s',
			'Clinical Staff – %s',
			'Professional Team – %s',
		);
		$template = $templates[array_rand($templates)];
		$title = sprintf($template, $business_name);
		// Enforce max 60 chars
		return mb_strlen($title) <= 60
			? $title
			: mb_substr($title, 0, 57) . '…';
	}

	/**
	 * Build organic TEAM description tie-in (Batch 1.b)
	 * Add a sentence connecting the scene to the team/brand.
	 */
	private function buildTeamTieIn($business_name, $brand_voice)
	{
		return sprintf('This image reflects the supportive team at %s.', $business_name);
	}
}

// Initialize the connector
new MSH_OpenAI_Connector();
