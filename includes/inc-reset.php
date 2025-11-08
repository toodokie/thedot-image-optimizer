<?php
/**
 * Safe reset helper for attachments.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clear staging metadata while preserving live attachment fields.
 *
 * @param int $attachment_id Attachment ID.
 * @return void
 */
function msh_soft_reset( int $attachment_id ): void {
	$clear_keys = array(
		// Context + staging payloads.
		'_msh_context_trace',
		'_msh_ai_staged_meta',
		'_msh_ai_keywords',
		'_msh_ai_filename_slug',
		// Filename suggestion artifacts.
		'_msh_suggested_filename',
		'_msh_suggested_filename_context',
		'msh_filename_last_suggested',
		// Confidence + scoring.
		'_msh_confidence',
		'_msh_confidence_score',
		'_msh_confidence_level',
	);

	foreach ( $clear_keys as $key ) {
		delete_post_meta( $attachment_id, $key );
	}

	/**
	 * Allow integrators to enqueue a fresh analyse run.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	do_action( 'msh_queue_analyze', $attachment_id );

	if ( function_exists( 'error_log' ) ) {
		error_log(
			sprintf(
				'[MSH][RESET] Attachment %d cleared=%s',
				$attachment_id,
				implode( ',', $clear_keys )
			)
		);
	}
}
