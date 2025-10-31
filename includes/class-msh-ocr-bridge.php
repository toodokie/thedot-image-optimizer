<?php
/**
 * Lightweight OCR bridge for brand detection.
 *
 * Acts as an integration point for future OCR providers.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_OCR_Bridge {

	/**
	 * Detect whether branding/text appears on an image.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $context       Context array (optional).
	 * @return bool True if brand presence confirmed, otherwise false.
	 */
	public static function detect_brand_presence( $attachment_id, array $context = array() ) {
		/**
		 * Filter: allow external services to report brand detection.
		 *
		 * Return true/false, or null to fall back to default behaviour.
		 *
		 * @param bool|null $detected      Whether branding was detected.
		 * @param int       $attachment_id Attachment ID.
		 * @param array     $context       Context array.
		 */
		$detected = apply_filters( 'msh_ocr_detect_brand', null, $attachment_id, $context );

		if ( $detected === null ) {
			return false;
		}

		return (bool) $detected;
	}
}
