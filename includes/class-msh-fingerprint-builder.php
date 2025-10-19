<?php
/**
 * Fingerprint Builder Service - Phase 4R+ Core
 *
 * Calculates SHA1 fingerprints from input signals to detect metadata staleness.
 * A fingerprint change indicates metadata should be regenerated.
 *
 * Input signals:
 * - Page context (post content where image appears)
 * - Image visual features (perceptual hash, dimensions)
 * - Locale profile hash (language, region, cultural preferences)
 * - Template hash (prompt template content)
 * - Model + prompt hash (AI model version + system prompt)
 *
 * @package MSH_Image_Optimizer
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Fingerprint_Builder {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// No hooks needed - this is a stateless service
	}

	/**
	 * Build fingerprint for attachment metadata
	 *
	 * @param int $attachment_id Attachment ID
	 * @param string $locale Locale code (e.g., 'en_US')
	 * @param string $field Field name ('title', 'alt', 'caption', 'description')
	 * @return string SHA1 fingerprint (40 chars)
	 */
	public function build_fingerprint( $attachment_id, $locale, $field ) {
		$signals = $this->gather_signals( $attachment_id, $locale, $field );
		return $this->hash_signals( $signals );
	}

	/**
	 * Gather all input signals for fingerprint
	 *
	 * @param int $attachment_id Attachment ID
	 * @param string $locale Locale code
	 * @param string $field Field name
	 * @return array Input signals
	 */
	private function gather_signals( $attachment_id, $locale, $field ) {
		$signals = array();

		// Signal 1: Page context (where the image is used)
		$signals['page_context'] = $this->get_page_context_hash( $attachment_id );

		// Signal 2: Image visual features
		$signals['image_features'] = $this->get_image_features_hash( $attachment_id );

		// Signal 3: Locale profile hash
		$signals['locale_profile'] = $this->get_locale_profile_hash( $locale );

		// Signal 4: Template hash (prompt template for this field)
		$signals['template'] = $this->get_template_hash( $locale, $field );

		// Signal 5: Model + prompt hash
		$signals['model_prompt'] = $this->get_model_prompt_hash();

		// Signal 6: Glossary hash (locale-specific terms)
		$signals['glossary'] = $this->get_glossary_hash( $locale );

		return $signals;
	}

	/**
	 * Hash signals array into SHA1 fingerprint
	 *
	 * @param array $signals Input signals
	 * @return string SHA1 hash
	 */
	private function hash_signals( $signals ) {
		// Sort signals by key for consistent hashing
		ksort( $signals );

		// JSON encode with sorted keys
		$json = wp_json_encode( $signals );

		return sha1( $json );
	}

	/**
	 * Get page context hash
	 *
	 * Analyzes all posts/pages where this image appears and creates
	 * a hash of their combined content context.
	 *
	 * @param int $attachment_id Attachment ID
	 * @return string Hash of page context
	 */
	private function get_page_context_hash( $attachment_id ) {
		global $wpdb;

		// Find posts that reference this attachment
		// Check in post_content for the attachment ID
		$posts = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			AND (post_content LIKE %s OR ID IN (
				SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_thumbnail_id' AND meta_value = %d
			))
			LIMIT 50",
			'%wp-image-' . $attachment_id . '%',
			$attachment_id
		) );

		if ( empty( $posts ) ) {
			return '';
		}

		// Gather context from all posts where image appears
		$contexts = array();
		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Get context snapshot from database if available
			$context_data = $wpdb->get_row( $wpdb->prepare(
				"SELECT intent, primary_keyword, keywords FROM {$wpdb->prefix}msh_context WHERE post_id = %d",
				$post_id
			) );

			if ( $context_data ) {
				$keywords = ! empty( $context_data->keywords ) ? json_decode( $context_data->keywords, true ) : array();
				$contexts[] = array(
					'post_id'      => $post_id,
					'intent'       => $context_data->intent ?? 'unknown',
					'primary_kw'   => $context_data->primary_keyword ?? '',
					'keywords'     => array_slice( $keywords, 0, 5 ),
					'content_hash' => substr( md5( $post->post_content ), 0, 16 ),
				);
			} else {
				// Fallback: Simple content hash
				$contexts[] = array(
					'post_id'      => $post_id,
					'content_hash' => substr( md5( $post->post_content ), 0, 16 ),
				);
			}
		}

		return md5( wp_json_encode( $contexts ) );
	}

	/**
	 * Get image visual features hash
	 *
	 * @param int $attachment_id Attachment ID
	 * @return string Hash of visual features
	 */
	private function get_image_features_hash( $attachment_id ) {
		$features = array();

		// File hash (to detect image replacement)
		$file_path = get_attached_file( $attachment_id );
		if ( $file_path && file_exists( $file_path ) ) {
			$features['file_hash'] = md5_file( $file_path );
		}

		// Dimensions
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( $metadata ) {
			$features['width']  = $metadata['width'] ?? 0;
			$features['height'] = $metadata['height'] ?? 0;
		}

		// Perceptual hash (if available)
		if ( class_exists( 'MSH_Perceptual_Hash' ) ) {
			$phash = MSH_Perceptual_Hash::get_instance();
			$hash = get_post_meta( $attachment_id, 'msh_phash', true );
			if ( $hash ) {
				$features['phash'] = $hash;
			}
		}

		return md5( wp_json_encode( $features ) );
	}

	/**
	 * Get locale profile hash
	 *
	 * @param string $locale Locale code
	 * @return string Hash of locale profile
	 */
	private function get_locale_profile_hash( $locale ) {
		global $wpdb;

		// Query locale profile from database
		$profile = $wpdb->get_row( $wpdb->prepare(
			"SELECT language, region, cultural_context, formality, tone FROM {$wpdb->prefix}msh_locale_profiles WHERE locale_code = %s",
			$locale
		) );

		if ( ! $profile ) {
			return md5( $locale );
		}

		// Hash key profile fields that affect metadata generation
		$relevant_fields = array(
			'language'         => $profile->language ?? '',
			'region'           => $profile->region ?? '',
			'cultural_context' => $profile->cultural_context ?? '',
			'formality'        => $profile->formality ?? '',
			'tone'             => $profile->tone ?? '',
		);

		return md5( wp_json_encode( $relevant_fields ) );
	}

	/**
	 * Get template hash
	 *
	 * @param string $locale Locale code
	 * @param string $field Field name
	 * @return string Hash of prompt template
	 */
	private function get_template_hash( $locale, $field ) {
		global $wpdb;

		// Query template from database (Phase 3 structure)
		$template = $wpdb->get_var( $wpdb->prepare(
			"SELECT prompt_template FROM {$wpdb->prefix}msh_locale_profiles WHERE locale_code = %s",
			$locale
		) );

		if ( ! $template ) {
			// Fallback to locale code only
			return md5( $locale . '_' . $field );
		}

		// Hash the template content
		return md5( $template );
	}

	/**
	 * Get model + prompt hash
	 *
	 * @return string Hash of model config
	 */
	private function get_model_prompt_hash() {
		// Get AI model configuration
		$ai_model = get_option( 'msh_ai_model', 'gpt-4o-mini' );
		$system_prompt = get_option( 'msh_system_prompt', '' );

		$config = array(
			'model'  => $ai_model,
			'prompt' => $system_prompt,
		);

		return md5( wp_json_encode( $config ) );
	}

	/**
	 * Get glossary hash
	 *
	 * @param string $locale Locale code
	 * @return string Hash of glossary terms
	 */
	private function get_glossary_hash( $locale ) {
		global $wpdb;

		// Query glossary from database
		$glossary_json = $wpdb->get_var( $wpdb->prepare(
			"SELECT glossary FROM {$wpdb->prefix}msh_locale_profiles WHERE locale_code = %s",
			$locale
		) );

		if ( empty( $glossary_json ) ) {
			return '';
		}

		$glossary = json_decode( $glossary_json, true );
		if ( empty( $glossary ) ) {
			return '';
		}

		// Hash glossary terms (sorted for consistency)
		ksort( $glossary );
		return md5( wp_json_encode( $glossary ) );
	}

	/**
	 * Compare two fingerprints and determine staleness reason
	 *
	 * @param int $attachment_id Attachment ID
	 * @param string $locale Locale code
	 * @param string $field Field name
	 * @param string $stored_fingerprint Stored fingerprint from database
	 * @return string|null Staleness reason or null if fresh
	 */
	public function detect_staleness_reason( $attachment_id, $locale, $field, $stored_fingerprint ) {
		// Build current fingerprint
		$current_fingerprint = $this->build_fingerprint( $attachment_id, $locale, $field );

		// If fingerprints match, metadata is fresh
		if ( $current_fingerprint === $stored_fingerprint ) {
			return null;
		}

		// Fingerprints differ - determine what changed by comparing individual signals
		$current_signals = $this->gather_signals( $attachment_id, $locale, $field );
		$stored_signals = $this->reverse_engineer_signals( $stored_fingerprint, $attachment_id, $locale, $field );

		// Compare each signal to determine staleness reason
		if ( $current_signals['page_context'] !== $stored_signals['page_context'] ) {
			return 'context_changed';
		}

		if ( $current_signals['image_features'] !== $stored_signals['image_features'] ) {
			return 'file_replaced';
		}

		if ( $current_signals['locale_profile'] !== $stored_signals['locale_profile'] ) {
			return 'locale_updated';
		}

		if ( $current_signals['glossary'] !== $stored_signals['glossary'] ) {
			return 'glossary_changed';
		}

		if ( $current_signals['template'] !== $stored_signals['template'] ) {
			return 'template_changed';
		}

		// Fallback - something changed but we can't determine what
		return 'context_changed';
	}

	/**
	 * Reverse engineer signals from stored data
	 *
	 * Note: This is a fallback. Ideally we'd store individual signal hashes
	 * alongside the fingerprint for precise staleness detection.
	 *
	 * @param string $stored_fingerprint Stored fingerprint
	 * @param int $attachment_id Attachment ID
	 * @param string $locale Locale code
	 * @param string $field Field name
	 * @return array Signal hashes
	 */
	private function reverse_engineer_signals( $stored_fingerprint, $attachment_id, $locale, $field ) {
		// For now, we can't truly reverse engineer the signals from a hash
		// This method exists for future enhancement where we might store
		// individual signal hashes in the database for precise staleness detection

		// Return empty signals as fallback
		return array(
			'page_context'   => '',
			'image_features' => '',
			'locale_profile' => '',
			'template'       => '',
			'model_prompt'   => '',
			'glossary'       => '',
		);
	}
}
