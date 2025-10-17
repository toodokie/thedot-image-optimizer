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

class MSH_OpenAI_Connector {

    /**
     * OpenAI API endpoint
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Constructor - hooks into the AI metadata filter
     */
    public function __construct() {
        add_filter('msh_ai_generate_metadata', array($this, 'generate_metadata_via_openai'), 10, 3);
    }

    /**
     * Generate metadata using OpenAI Vision API
     *
     * @param array|null $metadata Existing metadata (should be null)
     * @param array $payload Request payload from AI service
     * @param MSH_Contextual_Meta_Generator $generator Generator instance
     * @return array|null Metadata array or null on failure
     */
    public function generate_metadata_via_openai($metadata, $payload, $generator) {
        error_log('[MSH OpenAI] generate_metadata_via_openai called');

        // If another filter already provided metadata, don't override
        if (is_array($metadata) && !empty($metadata)) {
            error_log('[MSH OpenAI] Metadata already provided, skipping');
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

        error_log('[MSH OpenAI] API key found: ***' . substr($api_key, -4));

        // Get image URL
        $attachment_id = $payload['attachment_id'];
        $image_url = wp_get_attachment_url($attachment_id);

        if (!$image_url) {
            error_log('[MSH OpenAI] Could not get image URL for attachment ' . $attachment_id);
            return null;
        }

        error_log('[MSH OpenAI] Processing attachment ' . $attachment_id . ': ' . $image_url);

        // Get business context
        $context = $payload['context'];
        $business_name = !empty($context['business_name']) ? $context['business_name'] : 'this business';
        $industry = !empty($context['industry_label']) ? $context['industry_label'] : 'professional services';
        $location = !empty($context['location']) ? $context['location'] : '';
        $uvp = !empty($context['uvp']) ? $context['uvp'] : '';

        // Build AI prompt with enabled features
        $features = !empty($payload['features']) ? $payload['features'] : array();
        $prompt = $this->build_vision_prompt($business_name, $industry, $location, $uvp, $features);

        // Call OpenAI Vision API
        $response = $this->call_openai_vision($image_url, $prompt, $api_key);

        if (is_wp_error($response)) {
            error_log('[MSH OpenAI] API Error: ' . $response->get_error_message());
            return null;
        }

        // Parse response into metadata structure
        $parsed_metadata = $this->parse_openai_response($response, $context);

        if (empty($parsed_metadata)) {
            error_log('[MSH OpenAI] Failed to parse metadata from response');
            return null;
        }

        // Apply AI regeneration filters if specified
        $ai_options = !empty($payload['ai_options']) ? $payload['ai_options'] : [];
        if (!empty($ai_options['ai_regeneration'])) {
            $ai_mode = !empty($ai_options['ai_mode']) ? $ai_options['ai_mode'] : 'fill-empty';
            $ai_fields = !empty($ai_options['ai_fields']) ? $ai_options['ai_fields'] : [];

            error_log('[MSH OpenAI] AI Regeneration mode: ' . $ai_mode . ', fields: ' . implode(',', $ai_fields));

            // Filter to only requested fields
            if (!empty($ai_fields)) {
                $field_map = [
                    'title' => 'title',
                    'alt_text' => 'alt_text',
                    'caption' => 'caption',
                    'description' => 'description'
                ];

                $filtered_metadata = [];
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

        error_log('[MSH OpenAI] Successfully generated metadata for attachment ' . $attachment_id);
        return $parsed_metadata;
    }

    /**
     * Build vision analysis prompt based on enabled features
     */
    private function build_vision_prompt($business_name, $industry, $location, $uvp, $features = array()) {
        $location_text = !empty($location) ? " in {$location}" : '';
        $uvp_text = !empty($uvp) ? "\n\nBusiness value proposition: {$uvp}" : '';

        // Check if filename generation is enabled
        $filename_enabled = in_array('filename', $features, true);

        // Build JSON schema based on enabled features
        $json_fields = array(
            '  "title": "Descriptive title (50-60 chars, include business name)"',
            '  "alt_text": "Accessible alt text describing what\'s in the image (100-125 chars)"',
            '  "caption": "Brief caption (40-60 chars)"',
            '  "description": "Detailed description for search engines (150-200 chars)"'
        );

        $requirements = array(
            '- Title: Include what\'s visible in the image + business name',
            '- Alt text: Describe the image for screen readers, be specific',
            '- Caption: Short, punchy description',
            '- Description: Include context, location, and what this represents for the business'
        );

        // Add filename field if enabled
        if ($filename_enabled) {
            $json_fields[] = '  "filename_slug": "seo-friendly-filename-slug-describing-image-content"';
            $requirements[] = '- Filename slug: Lowercase, hyphens only, 3-4 descriptive words MAXIMUM about what\'s IN the image (not the business name), suitable for file naming. CRITICAL: Never exceed 4 words.';
        }

        $json_schema = "{\n" . implode(",\n", $json_fields) . "\n}";
        $requirements_list = implode("\n", $requirements);

        return "Analyze this image and provide SEO-optimized metadata in JSON format with these exact keys:

{$json_schema}

Requirements:
{$requirements_list}
- CRITICAL: Describe ONLY what is actually visible in the image. Do NOT invent or assume content that isn't there.
- If the image is a graphic, banner, or text image, describe it as such (e.g., 'Wide banner displaying alignment test text')
- If the image shows people, objects, or scenes, describe what you actually see
- This is for {$business_name}, a {$industry} business{$location_text}{$uvp_text}
- Only relate the image to the business IF it's clearly relevant to {$industry}
- Use professional, industry-appropriate language when relevant
- Return ONLY valid JSON, no markdown or explanation";
    }

    /**
     * Call OpenAI Vision API
     */
    private function call_openai_vision($image_url, $prompt, $api_key) {
        // For local development, convert image to base64 if URL is not publicly accessible
        $image_data = $this->get_image_data($image_url);

        $body = array(
            'model' => 'gpt-4o', // Using GPT-4o for vision (faster and cheaper than gpt-4-vision-preview)
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => $prompt,
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image_data,
                                'detail' => 'low', // 'low' is cheaper and sufficient for metadata
                            ),
                        ),
                    ),
                ),
            ),
            'max_tokens' => 500,
            'temperature' => 0.7,
        );

        $response = wp_remote_post(self::API_ENDPOINT, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ));

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
    private function get_image_data($image_url) {
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
                    $image_data = file_get_contents($absolute_path);
                    $base64 = base64_encode($image_data);
                    $mime_type = mime_content_type($absolute_path);

                    error_log('[MSH OpenAI] Converted to base64: ' . $absolute_path);
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
     * Parse OpenAI response into metadata array
     */
    private function parse_openai_response($response_json, $context) {
        $data = json_decode($response_json, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            return null;
        }

        $content = trim($data['choices'][0]['message']['content']);

        // Remove markdown code blocks if present
        $content = preg_replace('/^```json\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);

        $metadata = json_decode($content, true);

        if (!is_array($metadata)) {
            error_log('[MSH OpenAI] Invalid JSON in response: ' . $content);
            return null;
        }

        // Validate required fields (filename_slug is optional for backward compatibility)
        $required = array('title', 'alt_text', 'caption', 'description');
        foreach ($required as $field) {
            if (empty($metadata[$field])) {
                error_log('[MSH OpenAI] Missing required field: ' . $field);
                return null;
            }
        }

        // Sanitize the metadata
        $sanitized = array(
            'title' => sanitize_text_field($metadata['title']),
            'alt_text' => sanitize_text_field($metadata['alt_text']),
            'caption' => sanitize_text_field($metadata['caption']),
            'description' => sanitize_textarea_field($metadata['description']),
        );

        // Add filename_slug if provided by AI
        if (!empty($metadata['filename_slug'])) {
            $sanitized['filename_slug'] = sanitize_title($metadata['filename_slug']);
            error_log('[MSH OpenAI] AI suggested filename slug: ' . $sanitized['filename_slug']);
        }

        return $sanitized;
    }
}

// Initialize the connector
new MSH_OpenAI_Connector();
