<?php
/**
 * I18n WordPress Integration
 *
 * Integrates multilingual metadata with WordPress image rendering.
 * Filters alt text, titles, and other attributes based on current locale.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * I18n Integration Class
 *
 * Hooks into WordPress to provide locale-specific image metadata.
 */
class MSH_I18n_Integration {

	/**
	 * Singleton instance
	 *
	 * @var MSH_I18n_Integration
	 */
	private static $instance = null;

	/**
	 * I18n metadata manager
	 *
	 * @var MSH_I18n_Metadata
	 */
	private $i18n_metadata;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_I18n_Integration
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
		$this->i18n_metadata = MSH_I18n_Metadata::get_instance();

		// Filter alt text
		add_filter( 'get_post_metadata', array( $this, 'filter_alt_text' ), 10, 4 );

		// Filter image attributes
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_image_attributes' ), 10, 3 );

		// Filter attachment metadata
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'filter_attachment_for_js' ), 10, 3 );
	}

	/**
	 * Filter alt text based on current locale
	 *
	 * Intercepts get_post_meta() calls for '_wp_attachment_image_alt'.
	 *
	 * @param mixed  $value     Metadata value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Meta key.
	 * @param bool   $single    Whether to return single value.
	 * @return mixed Filtered metadata value.
	 */
	public function filter_alt_text( $value, $object_id, $meta_key, $single ) {
		// Only filter alt text for attachments
		if ( '_wp_attachment_image_alt' !== $meta_key ) {
			return $value;
		}

		if ( 'attachment' !== get_post_type( $object_id ) ) {
			return $value;
		}

		// Get current locale
		$locale = get_locale();

		// Try to get i18n metadata
		$metadata = $this->i18n_metadata->get_with_fallback( $object_id, $locale );

		if ( $metadata && ! empty( $metadata['alt_text'] ) ) {
			// Return locale-specific alt text
			if ( $single ) {
				return $metadata['alt_text'];
			} else {
				return array( $metadata['alt_text'] );
			}
		}

		// Return original value if no i18n metadata
		return $value;
	}

	/**
	 * Filter image attributes
	 *
	 * @param array        $attr       Image attributes.
	 * @param WP_Post      $attachment Attachment post object.
	 * @param string|array $size       Image size.
	 * @return array Filtered attributes.
	 */
	public function filter_image_attributes( $attr, $attachment, $size ) {
		$locale = get_locale();

		// Get i18n metadata
		$metadata = $this->i18n_metadata->get_with_fallback( $attachment->ID, $locale );

		if ( $metadata ) {
			// Override alt text
			if ( ! empty( $metadata['alt_text'] ) ) {
				$attr['alt'] = $metadata['alt_text'];
			}

			// Override title
			if ( ! empty( $metadata['title'] ) ) {
				$attr['title'] = $metadata['title'];
			}
		}

		return $attr;
	}

	/**
	 * Filter attachment data for JavaScript (media library)
	 *
	 * @param array   $response   Attachment data.
	 * @param WP_Post $attachment Attachment post object.
	 * @param array   $meta       Attachment metadata.
	 * @return array Filtered response.
	 */
	public function filter_attachment_for_js( $response, $attachment, $meta ) {
		$locale = get_locale();

		// Get i18n metadata
		$metadata = $this->i18n_metadata->get_with_fallback( $attachment->ID, $locale );

		if ( $metadata ) {
			// Override alt text
			if ( ! empty( $metadata['alt_text'] ) ) {
				$response['alt'] = $metadata['alt_text'];
			}

			// Override title
			if ( ! empty( $metadata['title'] ) ) {
				$response['title'] = $metadata['title'];
			}

			// Override caption
			if ( ! empty( $metadata['caption'] ) ) {
				$response['caption'] = $metadata['caption'];
			}

			// Override description
			if ( ! empty( $metadata['description'] ) ) {
				$response['description'] = $metadata['description'];
			}

			// Add metadata source info
			if ( isset( $metadata['_source'] ) ) {
				$response['msh_i18n_source'] = $metadata['_source'];
			}
		}

		return $response;
	}

	/**
	 * Get current locale from multilingual plugins
	 *
	 * Supports WPML, Polylang, and WordPress core.
	 *
	 * @return string Current locale code.
	 */
	public function get_current_locale() {
		// Try WPML
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$wpml_locale = apply_filters( 'wpml_current_language', null );
			if ( $wpml_locale ) {
				return $wpml_locale;
			}
		}

		// Try Polylang
		if ( function_exists( 'pll_current_language' ) ) {
			$pll_locale = pll_current_language( 'locale' );
			if ( $pll_locale ) {
				return $pll_locale;
			}
		}

		// Fallback to WordPress locale
		return get_locale();
	}
}
