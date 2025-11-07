<?php
/**
 * Attachment locking and atomic optimisation helpers.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'msh_update_attached_file_collapsed' ) ) {
	/**
	 * Update _wp_attached_file ensuring duplicate -ID suffixes are collapsed.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $relative_path Relative upload path.
	 * @return string Absolute filesystem path for the updated file.
	 */
function msh_update_attached_file_collapsed( int $attachment_id, string $relative_path ): string {
	$upload    = wp_get_upload_dir();
	$relative  = ltrim( str_replace( '\\', '/', $relative_path ), '/' );
	$directory = '';
	$basename  = $relative;

		if ( false !== strpos( $relative, '/' ) ) {
			$directory = substr( $relative, 0, strrpos( $relative, '/' ) );
			$basename  = substr( $relative, strrpos( $relative, '/' ) + 1 );
		}

		if ( function_exists( 'msh_collapse_id_suffix' ) ) {
			$basename = msh_collapse_id_suffix( $basename, $attachment_id );
		}

	$normalized = ltrim(
		( $directory !== '' ? trailingslashit( $directory ) : '' ) . $basename,
		'/'
	);

	$original_rel = $relative;
	update_post_meta( $attachment_id, '_wp_attached_file', $normalized );

	$original_abs  = trailingslashit( $upload['basedir'] ) . $original_rel;
	$collapsed_abs = trailingslashit( $upload['basedir'] ) . $normalized;

	if ( $original_rel !== $normalized && file_exists( $original_abs ) ) {
		msh_fs_mkdir_p( dirname( $collapsed_abs ) );
		if ( ! @rename( $original_abs, $collapsed_abs ) ) {
			copy( $original_abs, $collapsed_abs );
			wp_delete_file( $original_abs );
		}
	}

	return $collapsed_abs;
}
}

/**
 * Acquire a transient-based lock for an attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function msh_lock_start( int $attachment_id ): bool {
	return wp_cache_add( "msh:lock:{$attachment_id}", 1, '', 300 );
}

/**
 * Release a transient-based lock for an attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return void
 */
function msh_lock_end( int $attachment_id ): void {
	wp_cache_delete( "msh:lock:{$attachment_id}" );
}

/**
 * Ensure a directory exists.
 *
 * @param string $dir Absolute directory path.
 * @return bool
 */
function msh_fs_mkdir_p( string $dir ): bool {
	if ( is_dir( $dir ) ) {
		return true;
	}

	return wp_mkdir_p( $dir );
}

/**
 * Copy or rewrite the source file to a destination.
 *
 * @param string        $source Absolute source path.
 * @param string        $destination Absolute destination path.
 * @param callable|null $rewrite Optional callback to transform bytes before writing.
 * @return bool
 */
function msh_fs_copy_or_rewrite( string $source, string $destination, ?callable $rewrite = null ): bool {
	if ( ! file_exists( $source ) || ! is_readable( $source ) ) {
		return false;
	}

	if ( $rewrite ) {
		$bytes = $rewrite( $source );
		if ( false === $bytes ) {
			return false;
		}
		return (bool) file_put_contents( $destination, $bytes );
	}

	return copy( $source, $destination );
}

/**
 * Flush pending bytes to disk.
 *
 * @param string $path Absolute file path.
 * @return void
 */
function msh_fs_fsync( string $path ): void {
	$handle = @fopen( $path, 'rb' );
	if ( ! $handle ) {
		return;
	}

	if ( function_exists( 'fsync' ) ) {
		@fsync( $handle );
	}
	fclose( $handle );
}

/**
 * Rename a file atomically.
 *
 * @param string $from Source path.
 * @param string $to   Destination path.
 * @return bool
 */
function msh_fs_rename( string $from, string $to ): bool {
	return @rename( $from, $to );
}

/**
 * Perform an atomic file swap and metadata update.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $new_relative  Target relative path under uploads/.
 * @param array  $opts          Optional flags: source, delete_source, rewrite_callback.
 * @return array|\WP_Error {
 *     @type string $relative_path Final relative path.
 *     @type string $absolute_path Absolute filesystem path.
 *     @type array  $metadata      Generated attachment metadata.
 * }
 */
function msh_optimize_atomic( int $attachment_id, string $new_relative, array $opts = array() ) {
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['basedir'] ) || ! wp_is_writable( $uploads['basedir'] ) ) {
		return new WP_Error( 'msh_fs_unwritable', __( 'Uploads directory is not writable.', 'msh-image-optimizer' ) );
	}

	$source             = $opts['source'] ?? get_attached_file( $attachment_id );
	$previous_metadata  = wp_get_attachment_metadata( $attachment_id );
	$previous_relative  = get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( empty( $source ) || ! file_exists( $source ) ) {
		return new WP_Error( 'msh_missing_source', __( 'Attachment source file is missing.', 'msh-image-optimizer' ) );
	}

	$relative = ltrim( $new_relative, '/' );
	if ( '' === $relative ) {
		return new WP_Error( 'msh_invalid_target', __( 'A valid destination path is required for optimisation.', 'msh-image-optimizer' ) );
	}

	$absolute_target = trailingslashit( $uploads['basedir'] ) . $relative;
	$target_dir      = dirname( $absolute_target );
	if ( ! msh_fs_mkdir_p( $target_dir ) ) {
		return new WP_Error( 'msh_dir_unwritable', __( 'Unable to create destination directory during optimisation.', 'msh-image-optimizer' ) );
	}

	$temp_path = $absolute_target . '.tmp';
	if ( file_exists( $temp_path ) ) {
		@unlink( $temp_path );
	}

	$rewrite = isset( $opts['rewrite_callback'] ) && is_callable( $opts['rewrite_callback'] )
		? $opts['rewrite_callback']
		: null;

	if ( ! msh_fs_copy_or_rewrite( $source, $temp_path, $rewrite ) ) {
		return new WP_Error( 'msh_stream_fail', __( 'Unable to prepare temporary copy during optimisation.', 'msh-image-optimizer' ) );
	}

	msh_fs_fsync( $temp_path );

	if ( ! msh_fs_rename( $temp_path, $absolute_target ) ) {
		@unlink( $temp_path );
		return new WP_Error( 'msh_rename_fail', __( 'Atomic rename failed during optimisation.', 'msh-image-optimizer' ) );
	}

	$delete_source = ! array_key_exists( 'delete_source', $opts ) ? true : (bool) $opts['delete_source'];
	if ( $delete_source && realpath( $source ) !== realpath( $absolute_target ) ) {
		wp_delete_file( $source );

		if ( ! empty( $previous_metadata['file'] ) ) {
			$old_dir = trailingslashit( $uploads['basedir'] ) . trailingslashit( dirname( $previous_metadata['file'] ) );
			foreach ( (array) ( $previous_metadata['sizes'] ?? array() ) as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$legacy_path = $old_dir . $size['file'];
				if ( file_exists( $legacy_path ) ) {
					wp_delete_file( $legacy_path );
				}
			}
		}
	}

	if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
		$new_absolute = msh_update_attached_file_collapsed( $attachment_id, $relative );
		$relative     = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $new_absolute ), '/' );
	} else {
		update_post_meta( $attachment_id, '_wp_attached_file', $relative );
		$new_absolute = $absolute_target;
	}

	$old_base = basename( (string) $previous_relative );
	$new_base = basename( $relative );

	if ( function_exists( 'msh_collapse_id_suffix' ) ) {
		$old_base = msh_collapse_id_suffix( $old_base, $attachment_id );
		$new_base = msh_collapse_id_suffix( $new_base, $attachment_id );
	}

	$base_changed = ( $old_base !== $new_base ) || empty( $previous_relative );

	if ( $base_changed || empty( $previous_metadata ) ) {
		$metadata = wp_generate_attachment_metadata( $attachment_id, $new_absolute );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		wp_update_attachment_metadata( $attachment_id, $metadata );
	} else {
		if ( function_exists( 'wp_update_image_subsizes' ) ) {
			wp_update_image_subsizes( $attachment_id );
		} else {
			$metadata = wp_generate_attachment_metadata( $attachment_id, $new_absolute );
			if ( ! is_wp_error( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}
	}

	return array(
		'relative_path' => ltrim( $relative, '/' ),
		'absolute_path' => $new_absolute,
		'metadata'      => wp_get_attachment_metadata( $attachment_id ),
	);
}

/**
 * Verify that attachment metadata matches disk + remote availability.
 *
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function msh_verify_attachment_integrity( int $attachment_id ): bool {
	$file = get_attached_file( $attachment_id );
	if ( empty( $file ) || ! file_exists( $file ) ) {
		return false;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( empty( $metadata ) || ! is_array( $metadata ) ) {
		return false;
	}

	$uploads = wp_get_upload_dir();
	$base    = trailingslashit( $uploads['basedir'] );
	$rel_dir = '';
	if ( ! empty( $metadata['file'] ) ) {
		$rel_dir = trailingslashit( ltrim( dirname( $metadata['file'] ), '.' ) );
	}

	foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) {
		if ( empty( $size['file'] ) ) {
			return false;
		}
		$size_path = $base . $rel_dir . $size['file'];
		if ( ! file_exists( $size_path ) ) {
			return false;
		}
	}

	$should_remote_check = (bool) apply_filters( 'msh_verify_remote_head', true, $attachment_id );
	if ( $should_remote_check ) {
		$response = wp_remote_head(
			wp_get_attachment_url( $attachment_id ),
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			do_action( 'msh_io_remote_check_failed', $attachment_id, $response );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return false;
		}
	}

	return true;
}

/**
 * Perform optimisation, then heal/verify metadata and trigger soft reset.
 *
 * @param int      $attachment_id Attachment ID.
 * @param callable $compute_path  Callable that returns the destination relative path.
 * @param array    $opts          Additional options forwarded to msh_optimize_atomic().
 * @return array|\WP_Error Atomic result array on success.
 */
function msh_optimize_and_heal( int $attachment_id, callable $compute_path, array $opts = array() ) {
	$relative = (string) $compute_path( $attachment_id );
	$result   = msh_optimize_atomic( $attachment_id, $relative, $opts );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$healthy = msh_verify_attachment_integrity( $attachment_id );

	if ( ! $healthy && function_exists( 'wp_update_image_subsizes' ) ) {
		wp_update_image_subsizes( $attachment_id );
		$healthy = msh_verify_attachment_integrity( $attachment_id );
	}

	if ( ! $healthy ) {
		$absolute = get_attached_file( $attachment_id );
		if ( $absolute ) {
			$meta = wp_generate_attachment_metadata( $attachment_id, $absolute );
			if ( ! is_wp_error( $meta ) ) {
				wp_update_attachment_metadata( $attachment_id, $meta );
			}
			$healthy = msh_verify_attachment_integrity( $attachment_id );
		}
	}

	if ( $healthy && function_exists( 'msh_soft_reset' ) ) {
		msh_soft_reset( $attachment_id );
	}

	if ( ! $healthy ) {
		return new WP_Error( 'msh_integrity_failed', __( 'Attachment integrity could not be validated after optimisation.', 'msh-image-optimizer' ) );
	}

	return $result;
}
