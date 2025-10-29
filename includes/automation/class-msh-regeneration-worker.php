<?php
/**
 * Regeneration Worker - Processes Metadata Regeneration Jobs
 *
 * Handles the actual work of regenerating metadata for images when
 * jobs are processed from the queue. Integrates with Phase 4R+ systems:
 * - MSH_AI_Service for AI-generated metadata
 * - MSH_Context_Manager for context gathering
 * - MSH_Locale_Profile_Manager for locale-specific prompts
 * - MSH_Metadata_Versioning for version tracking
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Regeneration_Worker
 *
 * Processes regenerate_metadata and generate_metadata jobs.
 */
class MSH_Regeneration_Worker {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Regeneration_Worker|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Regeneration_Worker
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Worker is stateless - all logic in process() method
	}

	/**
	 * Process a regeneration job.
	 *
	 * Entry point called by MSH_Job_Engine.
	 *
	 * @param object $job     Job data from msh_jobs table.
	 * @param array  $payload Decoded job payload.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function process( $job, $payload ) {
		// Validate payload
		$validation = $this->validate_payload( $payload );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Extract data
		$attachment_id = isset( $payload['attachment_id'] ) ? (int) $payload['attachment_id'] : $job->entity_id;
		$locale        = isset( $payload['locale'] ) ? $payload['locale'] : '';
		$field         = isset( $payload['field'] ) ? $payload['field'] : '';
		$reason        = isset( $payload['reason'] ) ? $payload['reason'] : 'manual';

		// Validate attachment exists
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'invalid_attachment',
				sprintf( __( 'Attachment %d is not a valid image.', 'msh-image-optimizer' ), $attachment_id )
			);
		}

		// Regenerate the metadata
		$result = $this->regenerate_field( $attachment_id, $locale, $field, $reason );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Emit success event
		do_action( 'msh_metadata_regenerated', $attachment_id, $locale, $field, $result );

		return true;
	}

	/**
	 * Validate job payload.
	 *
	 * @param array $payload Job payload.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_payload( $payload ) {
		$required = array( 'locale', 'field' );

		foreach ( $required as $key ) {
			if ( empty( $payload[ $key ] ) ) {
				return new WP_Error(
					'missing_payload_field',
					sprintf( __( 'Missing required field: %s', 'msh-image-optimizer' ), $key )
				);
			}
		}

		// Validate field type
		$valid_fields = array( 'title', 'alt', 'caption', 'description' );
		if ( ! in_array( $payload['field'], $valid_fields, true ) ) {
			return new WP_Error(
				'invalid_field',
				sprintf( __( 'Invalid field: %s', 'msh-image-optimizer' ), $payload['field'] )
			);
		}

		return true;
	}

	/**
	 * Regenerate a specific metadata field for an attachment.
	 *
	 * Core logic: Gather context → Call AI → Save to cache → Version tracking.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code (e.g., 'es_ES').
	 * @param string $field         Field name ('title', 'alt', 'caption', 'description').
	 * @param string $reason        Reason for regeneration ('manual', 'context_changed', etc.).
	 * @return array|WP_Error Regeneration result or error.
	 */
	private function regenerate_field( $attachment_id, $locale, $field, $reason ) {
		// Step 1: Gather context
		$context = $this->gather_context( $attachment_id, $locale );
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		// Step 2: Try template matching first (Phase 6: Template Intelligence)
		$template_result = $this->try_template_match( $attachment_id, $field, $locale, $context );
		if ( ! is_wp_error( $template_result ) && ! empty( $template_result ) ) {
			// Template match succeeded, return template-generated metadata
			return $template_result;
		}

		// Step 3: Get locale profile for prompt customization
		$locale_profile = $this->get_locale_profile( $locale );

		// Step 4: Generate metadata via AI (fallback if no template match)
		$ai_result = $this->call_ai_service( $attachment_id, $field, $locale, $context, $locale_profile );
		if ( is_wp_error( $ai_result ) ) {
			return $ai_result;
		}

		// Step 4: Save to metadata cache
		$cache_result = $this->save_to_cache( $attachment_id, $locale, $field, $ai_result, $reason );
		if ( is_wp_error( $cache_result ) ) {
			return $cache_result;
		}

		// Step 5: Create version entry
		if ( class_exists( 'MSH_Metadata_Versioning' ) ) {
			MSH_Metadata_Versioning::get_instance()->create_version(
				$attachment_id,
				$locale,
				$field,
				$ai_result,
				'ai',
				sprintf( __( 'Auto-regenerated: %s', 'msh-image-optimizer' ), $reason )
			);
		}

		return array(
			'value'  => $ai_result,
			'locale' => $locale,
			'field'  => $field,
			'reason' => $reason,
		);
	}

	/**
	 * Gather context for an attachment.
	 *
	 * Uses MSH_Context_Manager from Phase 2 to get rich context.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @return array|WP_Error Context data or error.
	 */
	private function gather_context( $attachment_id, $locale ) {
		// Use Phase 2 Context Manager if available
		if ( class_exists( 'MSH_Context_Manager' ) ) {
			$context_manager = MSH_Context_Manager::get_instance();
			$context         = $context_manager->get_context_for_attachment( $attachment_id );

			if ( is_wp_error( $context ) ) {
				return $context;
			}

			return $context;
		}

		// Fallback: Basic context from attachment data
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return new WP_Error( 'attachment_not_found', __( 'Attachment not found.', 'msh-image-optimizer' ) );
		}

		// Get parent post for additional context
		$parent_post = null;
		if ( $attachment->post_parent > 0 ) {
			$parent_post = get_post( $attachment->post_parent );
		}

		return array(
			'attachment_title' => $attachment->post_title,
			'attachment_name'  => $attachment->post_name,
			'parent_title'     => $parent_post ? $parent_post->post_title : '',
			'parent_content'   => $parent_post ? wp_trim_words( $parent_post->post_content, 100 ) : '',
			'locale'           => $locale,
		);
	}

	/**
	 * Get locale profile for prompt customization.
	 *
	 * Uses MSH_Locale_Profile_Manager from Phase 3.
	 *
	 * @param string $locale Locale code.
	 * @return array|null Locale profile or null.
	 */
	private function get_locale_profile( $locale ) {
		if ( ! class_exists( 'MSH_Locale_Profile_Manager' ) ) {
			return null;
		}

		$profile_manager = MSH_Locale_Profile_Manager::get_instance();
		$profile         = $profile_manager->get_profile( $locale );

		return $profile;
	}

	/**
	 * Call AI service to generate metadata.
	 *
	 * Uses MSH_AI_Service to call OpenAI with context and locale.
	 *
	 * @param int    $attachment_id  Attachment ID.
	 * @param string $field          Field name.
	 * @param string $locale         Locale code.
	 * @param array  $context        Context data.
	 * @param array  $locale_profile Locale profile (optional).
	 * @return string|WP_Error Generated metadata or error.
	 */
	private function call_ai_service( $attachment_id, $field, $locale, $context, $locale_profile = null ) {
		if ( ! class_exists( 'MSH_AI_Service' ) ) {
			return new WP_Error( 'ai_service_missing', __( 'AI service not available.', 'msh-image-optimizer' ) );
		}

		// Get image file path for AI analysis
		$image_path = get_attached_file( $attachment_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return new WP_Error( 'image_not_found', __( 'Image file not found.', 'msh-image-optimizer' ) );
		}

		// Build prompt based on field type and locale
		$prompt = $this->build_prompt( $field, $locale, $context, $locale_profile );

		// Call AI service
		$ai_service = MSH_AI_Service::get_instance();
		$result     = $ai_service->generate_metadata(
			$image_path,
			$field,
			$locale,
			array(
				'context' => $context,
				'prompt'  => $prompt,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Extract generated text
		$generated_text = isset( $result['text'] ) ? $result['text'] : $result;

		// Validate length based on field type
		$validation = $this->validate_generated_text( $generated_text, $field );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $generated_text;
	}

	/**
	 * Build AI prompt based on field type and locale.
	 *
	 * @param string $field          Field name.
	 * @param string $locale         Locale code.
	 * @param array  $context        Context data.
	 * @param array  $locale_profile Locale profile (optional).
	 * @return string Prompt text.
	 */
	private function build_prompt( $field, $locale, $context, $locale_profile = null ) {
		// Use Phase 3 Prompt Template if available
		if ( class_exists( 'MSH_Prompt_Template' ) ) {
			$template = MSH_Prompt_Template::get_instance();
			return $template->build_prompt( $field, $locale, $context, $locale_profile );
		}

		// Fallback: Basic prompts
		$language_name = $this->get_language_name( $locale );

		$prompts = array(
			'alt'         => sprintf(
				'Generate a descriptive alt text in %s for this image. Context: %s',
				$language_name,
				wp_json_encode( $context )
			),
			'title'       => sprintf(
				'Generate a concise title in %s for this image. Context: %s',
				$language_name,
				wp_json_encode( $context )
			),
			'caption'     => sprintf(
				'Generate a caption in %s for this image. Context: %s',
				$language_name,
				wp_json_encode( $context )
			),
			'description' => sprintf(
				'Generate a detailed description in %s for this image. Context: %s',
				$language_name,
				wp_json_encode( $context )
			),
		);

		return isset( $prompts[ $field ] ) ? $prompts[ $field ] : $prompts['alt'];
	}

	/**
	 * Get human-readable language name from locale code.
	 *
	 * @param string $locale Locale code (e.g., 'es_ES').
	 * @return string Language name.
	 */
	private function get_language_name( $locale ) {
		$languages = array(
			'es_ES' => 'Spanish',
			'fr_FR' => 'French',
			'de_DE' => 'German',
			'it_IT' => 'Italian',
			'pt_PT' => 'Portuguese',
			'pt_BR' => 'Brazilian Portuguese',
			'ja'    => 'Japanese',
			'zh_CN' => 'Chinese (Simplified)',
			'ko_KR' => 'Korean',
		);

		return isset( $languages[ $locale ] ) ? $languages[ $locale ] : 'English';
	}

	/**
	 * Validate generated text length.
	 *
	 * Ensures AI didn't generate text that's too short or too long.
	 *
	 * @param string $text  Generated text.
	 * @param string $field Field name.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_generated_text( $text, $field ) {
		$text = trim( $text );

		if ( empty( $text ) ) {
			return new WP_Error( 'empty_result', __( 'AI generated empty text.', 'msh-image-optimizer' ) );
		}

		// Field-specific length limits
		$limits = array(
			'alt'         => array( 'min' => 10, 'max' => 200 ),
			'title'       => array( 'min' => 5, 'max' => 100 ),
			'caption'     => array( 'min' => 10, 'max' => 300 ),
			'description' => array( 'min' => 20, 'max' => 1000 ),
		);

		$limit = isset( $limits[ $field ] ) ? $limits[ $field ] : $limits['alt'];
		$len   = mb_strlen( $text );

		if ( $len < $limit['min'] ) {
			return new WP_Error(
				'text_too_short',
				sprintf( __( 'Generated text too short (%d chars, min %d).', 'msh-image-optimizer' ), $len, $limit['min'] )
			);
		}

		if ( $len > $limit['max'] ) {
			return new WP_Error(
				'text_too_long',
				sprintf( __( 'Generated text too long (%d chars, max %d).', 'msh-image-optimizer' ), $len, $limit['max'] )
			);
		}

		return true;
	}

	/**
	 * Save generated metadata to cache.
	 *
	 * Updates msh_metadata_cache table with new AI-generated value.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @param string $value         Generated value.
	 * @param string $reason        Regeneration reason.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function save_to_cache( $attachment_id, $locale, $field, $value, $reason ) {
		global $wpdb;

		if ( ! function_exists( 'msh_upsert_metadata_cache_value' ) ) {
			return new WP_Error( 'cache_helper_missing', __( 'Metadata cache helper not available.', 'msh-image-optimizer' ) );
		}

		$upserted = msh_upsert_metadata_cache_value(
			$attachment_id,
			$locale,
			$field,
			$value,
			'ai'
		);

		if ( ! $upserted ) {
			return new WP_Error( 'db_save_failed', __( 'Failed to save metadata to cache.', 'msh-image-optimizer' ), $wpdb->last_error );
		}

		return true;
	}

	/**
	 * Batch regenerate multiple entries.
	 *
	 * Useful for bulk operations (e.g., "regenerate all stale entries").
	 *
	 * @param array $entries Array of entries to regenerate.
	 * @return array Results with success/failure counts.
	 */
	public function batch_regenerate( $entries ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $entries as $entry ) {
			$result = $this->regenerate_field(
				$entry['attachment_id'],
				$entry['locale'],
				$entry['field'],
				isset( $entry['reason'] ) ? $entry['reason'] : 'batch'
			);

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['errors'][] = array(
					'attachment_id' => $entry['attachment_id'],
					'locale'        => $entry['locale'],
					'field'         => $entry['field'],
					'error'         => $result->get_error_message(),
				);
			} else {
				$results['success']++;
			}
		}

		return $results;
	}

	/**
	 * Try template matching for metadata generation.
	 *
	 * Phase 6: Template Intelligence integration.
	 * Attempts to generate metadata using pre-built templates before calling AI.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name ('title', 'alt', 'caption', 'description').
	 * @param string $locale        Locale code.
	 * @param array  $context       Context data from gather_context().
	 * @return string|WP_Error Generated metadata or WP_Error if no match/feature disabled.
	 */
	private function try_template_match( $attachment_id, $field, $locale, $context ) {
		// Check if Template Intelligence classes are loaded
		if ( ! class_exists( 'MSH_Template_Matcher' ) || ! class_exists( 'MSH_Feature_Flags' ) ) {
			return new WP_Error( 'template_system_unavailable', __( 'Template system not loaded.', 'msh-image-optimizer' ) );
		}

		// Check feature flag
		if ( ! MSH_Feature_Flags::evaluate( 'template_intelligence' ) ) {
			// Feature flag disabled, skip template matching
			if ( function_exists( 'msh_telemetry' ) ) {
				msh_telemetry( 'template_skipped_flag_disabled', array(
					'attachment_id' => $attachment_id,
					'locale'        => $locale,
					'field'         => $field,
				) );
			}
			return new WP_Error( 'template_flag_disabled', __( 'Template intelligence feature is disabled.', 'msh-image-optimizer' ) );
		}

		// Get matcher instance
		$matcher = MSH_Template_Matcher::get_instance();

		// Prepare context for template matching
		// The matcher expects: locale, usage_type, intent, keywords, entities, subject, post_title
		$template_context = array(
			'locale'     => $locale,
			'usage_type' => isset( $context['usage_type'] ) ? $context['usage_type'] : 'featured',
			'intent'     => isset( $context['intent'] ) ? $context['intent'] : 'on_topic',
			'keywords'   => isset( $context['keywords'] ) ? $context['keywords'] : array(),
			'entities'   => isset( $context['entities'] ) ? $context['entities'] : array(),
			'subject'    => isset( $context['subject'] ) ? $context['subject'] : '',
			'post_title' => isset( $context['post_title'] ) ? $context['post_title'] : get_the_title( $attachment_id ),
		);

		// Find matching template
		$match = $matcher->find_match( $template_context );

		if ( ! $match ) {
			// No template match, will fall back to AI
			return new WP_Error( 'no_template_match', __( 'No matching template found.', 'msh-image-optimizer' ) );
		}

		// Apply template to generate all fields
		$fields = $matcher->apply_template( $match, $template_context );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		// Extract the requested field
		$field_value = '';
		switch ( $field ) {
			case 'title':
				$field_value = isset( $fields['title'] ) ? $fields['title'] : '';
				break;
			case 'alt':
				$field_value = isset( $fields['alt'] ) ? $fields['alt'] : '';
				break;
			case 'caption':
				$field_value = isset( $fields['caption'] ) ? $fields['caption'] : '';
				break;
			case 'description':
				$field_value = isset( $fields['description'] ) ? $fields['description'] : '';
				break;
		}

		if ( empty( $field_value ) ) {
			return new WP_Error( 'template_empty_field', __( 'Template generated empty field.', 'msh-image-optimizer' ) );
		}

		// Telemetry: Template success
		if ( function_exists( 'msh_telemetry' ) ) {
			msh_telemetry( 'template_metadata_generated', array(
				'attachment_id' => $attachment_id,
				'template_id'   => $match['id'],
				'template_name' => $match['name'],
				'field'         => $field,
				'locale'        => $locale,
				'mode'          => $match['mode'],
			) );
		}

		return $field_value;
	}
}
