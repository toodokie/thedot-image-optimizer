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
		'_msh_optimized_date',
		'_msh_suggested_filename',
		'_msh_context_trace',
		'_msh_ai_staged_meta',
		'_msh_ai_filename_slug',
		'_msh_confidence',
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

