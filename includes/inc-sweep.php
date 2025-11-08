<?php
/**
 * Attachment integrity sweep + cron registration.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function msh_register_nightly_sweep() {
	if ( ! wp_next_scheduled( 'msh_nightly_attachment_integrity_sweep' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'msh_nightly_attachment_integrity_sweep' );
	}
}
add_action( 'init', 'msh_register_nightly_sweep' );

add_action(
	'msh_nightly_attachment_integrity_sweep',
	function () {
		if ( ! wp_cache_add( 'msh:nightly_sweep_lock', 1, '', HOUR_IN_SECONDS ) ) {
			return;
		}

		$scanned  = 0;
		$healed   = 0;
		$skipped  = 0;
		$days_back = (int) apply_filters( 'msh_sweep_days_back', 7 );
		$limit     = (int) apply_filters( 'msh_sweep_batch_limit', 100 );

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => max( 1, $limit ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		// Only apply date filter if days_back > 0 (0 means "all time")
		if ( $days_back > 0 ) {
			$since = gmdate( 'Y-m-d H:i:s', time() - $days_back * DAY_IN_SECONDS );
			$query_args['date_query'] = array(
				array(
					'after'     => $since,
					'inclusive' => true,
				),
			);
		}

		try {
			$query = new WP_Query( $query_args );

			foreach ( $query->posts as $attachment_id ) {
				++$scanned;
				$file = get_attached_file( $attachment_id );
				if ( empty( $file ) || ! file_exists( $file ) ) {
					if ( function_exists( 'msh_try_fix_db_from_disk' ) && msh_try_fix_db_from_disk( $attachment_id ) ) {
						++$healed;
					} else {
						++$skipped;
					}
					continue;
				}

				if ( function_exists( 'msh_verify_attachment_integrity' ) && msh_verify_attachment_integrity( $attachment_id ) ) {
					continue;
				}

				$healed += (int) msh_run_heal_routine( $attachment_id, $file );
			}
		} finally {
			wp_cache_delete( 'msh:nightly_sweep_lock' );
		}

		do_action(
			'msh_nightly_sweep_complete',
			array(
				'scanned' => $scanned,
				'healed'  => $healed,
				'skipped' => $skipped,
			)
		);
	}
);

/**
 * Attempt to heal a single attachment.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $absolute_path Current absolute path.
 * @return bool True if healed.
 */
function msh_run_heal_routine( int $attachment_id, string $absolute_path ): bool {
	$healed = false;

	if ( function_exists( 'wp_update_image_subsizes' ) ) {
		wp_update_image_subsizes( $attachment_id );
		$healed = function_exists( 'msh_verify_attachment_integrity' ) ? msh_verify_attachment_integrity( $attachment_id ) : false;
	}

	if ( ! $healed ) {
		$meta = wp_generate_attachment_metadata( $attachment_id, $absolute_path );
		if ( ! is_wp_error( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
			$healed = function_exists( 'msh_verify_attachment_integrity' ) ? msh_verify_attachment_integrity( $attachment_id ) : true;
		}
	}

	if ( ! $healed ) {
		$current_rel = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! empty( $current_rel ) && function_exists( 'msh_update_attached_file_collapsed' ) ) {
			$new_abs = msh_update_attached_file_collapsed( $attachment_id, $current_rel );
			if ( ! is_wp_error( $new_abs ) ) {
				$meta = wp_generate_attachment_metadata( $attachment_id, $new_abs );
				if ( ! is_wp_error( $meta ) ) {
					wp_update_attachment_metadata( $attachment_id, $meta );
					$healed = function_exists( 'msh_verify_attachment_integrity' ) ? msh_verify_attachment_integrity( $attachment_id ) : true;
				}
			}
		}
	}

	return $healed;
}

if ( ! function_exists( 'msh_try_fix_db_from_disk' ) ) {
	/**
	 * Attempt to realign _wp_attached_file with an existing file on disk when the DB path is missing.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if a replacement path was found and set.
	 */
	function msh_try_fix_db_from_disk( int $attachment_id ): bool {
		$current_rel = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( empty( $current_rel ) ) {
			return false;
		}

		$upload      = wp_get_upload_dir();
		$relative    = ltrim( $current_rel, '/' );
		$dir_name    = dirname( $relative );
		$abs_dir     = trailingslashit( $upload['basedir'] ) . ( '.' === $dir_name ? '' : $dir_name );
		$target_path = trailingslashit( $upload['basedir'] ) . $relative;

		if ( file_exists( $target_path ) || ! is_dir( $abs_dir ) ) {
			return false;
		}

		$candidate = null;
		$files     = glob( trailingslashit( $abs_dir ) . '*.*' );

		if ( empty( $files ) ) {
			return false;
		}

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$basename = basename( $file );
			if ( preg_match( '/\.(jpe?g|png|gif|webp)$/i', $basename ) ) {
				if ( strpos( $basename, 'brand-gallery-' ) === 0 ) {
					$candidate = $file;
					break;
				}

				if ( null === $candidate ) {
					$candidate = $file;
				}
			}
		}

		if ( null === $candidate ) {
			return false;
		}

		$relative_candidate = ltrim(
			( '.' === $dir_name ? '' : trailingslashit( $dir_name ) ) . basename( $candidate ),
			'/'
		);

		if ( ! function_exists( 'msh_update_attached_file_collapsed' ) ) {
			return false;
		}

		$absolute_candidate = msh_update_attached_file_collapsed( $attachment_id, $relative_candidate );
		if ( is_wp_error( $absolute_candidate ) ) {
			return false;
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, $absolute_candidate );
		if ( ! is_wp_error( $meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}

		return function_exists( 'msh_verify_attachment_integrity' )
			? msh_verify_attachment_integrity( $attachment_id )
			: true;
	}
}
