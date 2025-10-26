<?php
/**
 * Media Format Helpers
 *
 * Future-proof functions for AVIF compatibility (Phase 10).
 * These helpers ensure Template Intelligence (Phase 6) and AVIF conversion
 * can coexist without conflicts.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the preferred output extension for image files.
 *
 * This function provides a single point of control for image format extensions
 * across the entire plugin. Phase 6 uses 'webp', Phase 10 will use 'avif'.
 *
 * Usage:
 *   $new_filename = $base . '.' . msh_get_output_extension();
 *
 * Later, AVIF implementation can hook the filter:
 *   add_filter('msh_output_extension', fn() => 'avif');
 *
 * @since Phase 6
 * @return string File extension without dot (e.g., 'webp', 'avif', 'jpg')
 */
function msh_get_output_extension() {
	/**
	 * Filter the output file extension for processed images.
	 *
	 * @param string $extension Default extension ('webp').
	 */
	return apply_filters( 'msh_output_extension', 'webp' );
}

/**
 * Get the preferred format from template or context.
 *
 * Reads the template's preferred_format field or falls back to context.
 * Used during metadata generation to ensure consistent format selection.
 *
 * @since Phase 6
 * @param array $template Template array (optional).
 * @param array $context  Context array (optional).
 * @return string Format name ('webp', 'avif', 'jpg', 'png').
 */
function msh_get_preferred_format( $template = null, $context = null ) {
	// Priority 1: Template's preferred format
	if ( ! empty( $template['preferred_format'] ) ) {
		return $template['preferred_format'];
	}

	// Priority 2: Context's preferred format
	if ( ! empty( $context['preferred_format'] ) ) {
		return $context['preferred_format'];
	}

	// Priority 3: Global setting (future: add to settings page)
	$default_format = get_option( 'msh_default_image_format', 'webp' );

	/**
	 * Filter the preferred image format.
	 *
	 * @param string $format   Default format.
	 * @param array  $template Template being applied (if any).
	 * @param array  $context  Context array (if any).
	 */
	return apply_filters( 'msh_preferred_format', $default_format, $template, $context );
}

/**
 * Check if AVIF format is enabled and supported.
 *
 * Phase 10 compatibility check. Returns false in Phase 6.
 *
 * @since Phase 6
 * @return bool True if AVIF is available and enabled.
 */
function msh_is_avif_enabled() {
	// Phase 10 will implement this properly
	// For now, always return false
	$enabled = false;

	/**
	 * Filter whether AVIF conversion is enabled.
	 *
	 * @param bool $enabled Default: false in Phase 6.
	 */
	return apply_filters( 'msh_avif_enabled', $enabled );
}

/**
 * Get format-specific MIME type.
 *
 * @since Phase 6
 * @param string $format Format name ('webp', 'avif', 'jpg', 'png').
 * @return string MIME type (e.g., 'image/webp').
 */
function msh_get_format_mime_type( $format ) {
	$mime_types = array(
		'webp' => 'image/webp',
		'avif' => 'image/avif',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
	);

	return $mime_types[ strtolower( $format ) ] ?? 'image/jpeg';
}

/**
 * Add format information to metadata descriptor.
 *
 * Ensures the msh_descriptor includes format information for cloud sync.
 * Phase 10 AVIF converter will read this to know whether to convert.
 *
 * @since Phase 6
 * @param array  $descriptor Existing descriptor array.
 * @param string $format     Format name ('webp', 'avif', etc.).
 * @param int    $template_id Template ID if metadata came from template (optional).
 * @return array Updated descriptor.
 */
function msh_add_format_to_descriptor( $descriptor, $format, $template_id = null ) {
	if ( ! is_array( $descriptor ) ) {
		$descriptor = array();
	}

	$descriptor['format'] = $format;

	if ( null !== $template_id ) {
		$descriptor['template_id'] = $template_id;
		$descriptor['source'] = 'template';
	}

	return $descriptor;
}
