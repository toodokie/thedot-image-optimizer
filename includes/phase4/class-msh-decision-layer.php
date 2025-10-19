<?php
/**
 * Decision Layer - Phase 4R+
 *
 * Chooses between manual and AI metadata based on configurable policy and
 * validation rules.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Decision_Layer {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Decision_Layer|null
	 */
	private static $instance = null;

	/**
	 * Minimum manual length threshold.
	 *
	 * @var int
	 */
	private $manual_min_length = 10;

	/**
	 * Get singleton instance.
	 *
	 * @since 2.1.0
	 *
	 * @return MSH_Decision_Layer
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 */
	private function __construct() {
		$this->manual_min_length = (int) apply_filters( 'msh_metadata_manual_min_length', $this->manual_min_length );
	}

	/**
	 * Determine which source should be active.
	 *
	 * @since 2.1.0
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string      $locale        Locale code.
	 * @param string      $field         Field name.
	 * @param string|null $ai_value      AI generated value.
	 * @param string|null $manual_value  Manual value.
	 * @return string Either `manual` or `ai`.
	 */
	public function choose_source( $attachment_id, $locale, $field, $ai_value, $manual_value ) {
		$field           = sanitize_key( $field );
		$manual_trimmed  = $this->trimmed_value( $manual_value );
		$ai_trimmed      = $this->trimmed_value( $ai_value );
		$manual_valid    = $this->validate_value( $field, $manual_value );
		$ai_valid        = $this->validate_value( $field, $ai_value );
		$prefer_manual   = apply_filters( 'msh_metadata_prefer_manual_for_field', $this->should_prefer_manual(), $attachment_id, $locale, $field );
		$manual_length   = $this->value_length( $manual_trimmed );

		if ( $prefer_manual && $manual_valid && $manual_length >= $this->manual_min_length ) {
			return 'manual';
		}

		if ( ! $manual_valid && $ai_valid ) {
			return 'ai';
		}

		if ( ! $ai_valid && $manual_valid ) {
			return 'manual';
		}

		if ( ! $prefer_manual && $ai_valid ) {
			return 'ai';
		}

		if ( $manual_valid && $manual_length > 0 ) {
			return 'manual';
		}

		return $ai_valid ? 'ai' : 'manual';
	}

	/**
	 * Validate metadata value.
	 *
	 * @since 2.1.0
	 *
	 * @param string      $field Field name.
	 * @param string|null $value Value to validate.
	 * @return bool
	 */
	public function validate_value( $field, $value ) {
		$field = sanitize_key( $field );
		$text  = $this->trimmed_value( $value );

		if ( '' === $text ) {
			return false;
		}

		$length = $this->value_length( $text );
		$max    = apply_filters( "msh_metadata_max_length_{$field}", $this->get_default_max_length( $field ), $field, $text );

		if ( $max > 0 && $length > $max ) {
			return false;
		}

		if ( 'description' !== $field && preg_match( '#https?://#i', $text ) ) {
			return false;
		}

		if ( preg_match( '#<\s*script#i', (string) $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Determine whether manual values are preferred.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public function should_prefer_manual() {
		$setting = get_option( 'msh_metadata_prefer_manual', '1' );
		$prefer  = ( '1' === $setting || true === $setting );

		return (bool) apply_filters( 'msh_metadata_prefer_manual', $prefer );
	}

	/**
	 * Trim and normalise value for comparison.
	 *
	 * @param string|null $value Raw value.
	 * @return string
	 */
	private function trimmed_value( $value ) {
		if ( null === $value ) {
			return '';
		}

		$sanitized = wp_strip_all_tags( $value, true );
		$sanitized = preg_replace( '/\s+/', ' ', $sanitized );

		return trim( $sanitized );
	}

	/**
	 * Get default maximum length for field.
	 *
	 * @param string $field Field name.
	 * @return int
	 */
	private function get_default_max_length( $field ) {
		switch ( $field ) {
			case 'title':
				return 140;
			case 'alt':
				return 200;
			case 'caption':
				return 480;
			case 'description':
				return 2000;
			default:
				return 200;
		}
	}

	/**
	 * Multibyte-safe string length helper.
	 *
	 * @param string $text Text input.
	 * @return int
	 */
	private function value_length( $text ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $text, 'UTF-8' );
		}

		return (int) strlen( $text );
	}
}
