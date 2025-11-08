<?php
/**
 * MSH Safe Rename System
 * Handles filename changes while updating references safely.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Safe_Rename_System {
	private static $instance = null;
	private static $cron_ok = null; // Cron availability cache
	private $log_table;
	private $test_mode                      = false;
	private $last_replacements              = 0;
	private $backup_retention               = DAY_IN_SECONDS;
	private $content_lookup_cache_key       = 'msh_content_usage_lookup';
	private $content_lookup_snapshot_option = 'msh_content_lookup_snapshot';
	private $content_lookup_queue_option    = 'msh_content_lookup_queue';

	private function __construct() {
		global $wpdb;
		$this->log_table = $wpdb->prefix . 'msh_rename_log';

		add_action( 'init', array( $this, 'maybe_create_log_table' ) );
		add_action( 'template_redirect', array( $this, 'handle_old_urls' ), 1 );
		add_action( 'msh_cleanup_rename_backup', array( $this, 'cleanup_backup' ), 10, 1 );

		// GUARD #0: Intercept update_attached_file() filter (earliest possible point)
		add_filter( 'update_attached_file', array( $this, 'guard_update_attached_file_filter' ), 1, 2 );

		// GUARD #1: Prevent corrupted _wp_attached_file writes (normalize all updates)
		// Priority 1 to match manual rename lock wrapper
		add_filter( 'pre_update_post_metadata', array( $this, 'guard_attached_file_corruption' ), 1, 4 );

		// GUARD #2: Normalize attachment metadata structure (file + sizes)
		add_filter( 'wp_update_attachment_metadata', array( $this, 'guard_attachment_metadata_corruption' ), 9, 2 );

		// FORENSICS: Track suspicious corruption attempts with call stack
		add_filter( 'update_post_metadata', array( $this, 'forensic_track_corruption' ), 99, 5 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'forensic_track_metadata_corruption' ), 99, 2 );

		error_log( '[MSH Safe Rename System] Initialized with corruption guards active' );
	}

	/**
	 * GUARD #0: Intercept WordPress update_attached_file() filter
	 * This catches the value BEFORE it reaches update_post_meta()
	 */
	public function guard_update_attached_file_filter( $file, $attachment_id ) {
		$normalized = $this->normalize_attached_file( $file );
		if ( $normalized !== $file ) {
			error_log( "[MSH GUARD #0] Blocked corrupted filename in update_attached_file filter for #{$attachment_id}" );
			error_log( "[MSH GUARD #0]   Attempted: {$file}" );
			error_log( "[MSH GUARD #0]   Normalized: {$normalized}" );
			return $normalized;
		}
		return $file;
	}

	/**
	 * GUARD #1: Prevent corrupted _wp_attached_file writes
	 * Normalizes any attempt to write a tripled/duplicated filename
	 */
	public function guard_attached_file_corruption( $check, $object_id, $meta_key, $meta_value ) {
		if ( $meta_key !== '_wp_attached_file' ) {
			return $check;
		}

		// ALWAYS log every _wp_attached_file write attempt during rename
		if ( ! empty( $GLOBALS['_msh_guard'] ) ) {
			error_log( "[MSH GUARD CHECK] _wp_attached_file write for #{$object_id}: {$meta_value}" );
		}

		$normalized = $this->normalize_attached_file( $meta_value );
		if ( $normalized !== $meta_value ) {
			error_log( "[MSH GUARD BLOCK] Corrupted write detected for #{$object_id}" );
			error_log( "[MSH GUARD BLOCK]   Attempted: {$meta_value}" );
			error_log( "[MSH GUARD BLOCK]   Normalized: {$normalized}" );

			// Temporarily remove filter to prevent recursion, then force update with normalized value
			// Use priority 1 to match the manual rename lock wrapper
			remove_filter( 'pre_update_post_metadata', array( $this, 'guard_attached_file_corruption' ), 1 );

			if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
				$result = msh_update_attached_file_collapsed( (int) $object_id, $meta_value );
				if ( is_wp_error( $result ) ) {
					error_log( '[MSH GUARD BLOCK] Failed to normalize via msh_update_attached_file_collapsed: ' . $result->get_error_message() );
					update_post_meta( $object_id, '_wp_attached_file', $normalized );
				}
			} else {
				update_post_meta( $object_id, '_wp_attached_file', $normalized );
			}

			add_filter( 'pre_update_post_metadata', array( $this, 'guard_attached_file_corruption' ), 1, 4 );

			return true; // Prevent the original corrupted write
		}

		return $check; // Allow normal update to proceed
	}

	/**
	 * GUARD #2: Normalize attachment metadata structure
	 */
	public function guard_attachment_metadata_corruption( $data, $post_id ) {
		if ( ! is_array( $data ) || empty( $data['file'] ) ) {
			return $data;
		}

		$original_file = $data['file'];
		$data['file']  = $this->normalize_attached_file( $data['file'] );

		if ( ! empty( $data['sizes'] ) && is_array( $data['sizes'] ) ) {
			foreach ( $data['sizes'] as $k => $s ) {
				if ( ! empty( $s['file'] ) ) {
					$data['sizes'][ $k ]['file'] = $this->normalize_basename( $s['file'] );
				}
			}
		}

		if ( $original_file !== $data['file'] ) {
			error_log( "[MSH GUARD] Normalized attachment_metadata[file] for #{$post_id}" );
			error_log( "[MSH GUARD]   From: {$original_file}" );
			error_log( "[MSH GUARD]   To: {$data['file']}" );
		}

		return $data;
	}

	/**
	 * FORENSICS: Track suspicious corruption attempts
	 */
	public function forensic_track_corruption( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( $meta_key !== '_wp_attached_file' ) {
			return $check;
		}

		// Detect pattern like "TEST-TEST-TEST-" or any repeated prefix
		if ( preg_match( '/^(.+?)\/([A-Z0-9]+-)\2+/i', $meta_value ) ) {
			$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
			$functions = array_column( $bt, 'function' );
			error_log( '[MSH CORRUPTOR] Detected corrupted _wp_attached_file write attempt!' );
			error_log( "[MSH CORRUPTOR]   Post ID: {$object_id}" );
			error_log( "[MSH CORRUPTOR]   Value: {$meta_value}" );
			error_log( '[MSH CORRUPTOR]   Stack: ' . json_encode( $functions ) );
		}

		return $check;
	}

	/**
	 * FORENSICS: Track suspicious metadata corruption
	 */
	public function forensic_track_metadata_corruption( $data, $post_id ) {
		if ( ! empty( $data['file'] ) && preg_match( '/^(.+?)\/([A-Z0-9]+-)\2+/i', $data['file'] ) ) {
			$bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
			$functions = array_column( $bt, 'function' );
			error_log( '[MSH CORRUPTOR META] Detected corrupted attachment_metadata[file]!' );
			error_log( "[MSH CORRUPTOR META]   Post ID: {$post_id}" );
			error_log( "[MSH CORRUPTOR META]   File: {$data['file']}" );
			error_log( '[MSH CORRUPTOR META]   Stack: ' . json_encode( $functions ) );
		}

		return $data;
	}

	/**
	 * Normalize a relative file path (e.g., "2008/06/TEST-TEST-TEST-file.jpg" → "2008/06/TEST-file.jpg")
	 */
	private function normalize_attached_file( $rel ) {
		if ( empty( $rel ) ) {
			return $rel;
		}

		$rel   = ltrim( $rel, '/' );
		$parts = explode( '/', $rel );
		$file  = array_pop( $parts );
		$file  = $this->normalize_basename( $file );
		$parts[] = $file;

		return implode( '/', $parts );
	}

	/**
	 * Normalize a filename basename (e.g., "TEST-TEST-TEST-file.jpg" → "TEST-file.jpg")
	 */
	private function normalize_basename( $basename ) {
		if ( empty( $basename ) ) {
			return $basename;
		}

		// If we have an expected basename set globally, use it (absolute override)
		if ( ! empty( $GLOBALS['_msh_expected_basename'] ) ) {
			return $GLOBALS['_msh_expected_basename'];
		}

		// Collapse repeated identical prefix patterns like "TEST-TEST-TEST-" to single "TEST-"
		// Pattern: if hyphen-delimited segments at start repeat ≥2, collapse to one
		if ( preg_match( '/^(([A-Z0-9]+)-)\1+(.+)$/i', $basename, $m ) ) {
			// $m[1] = "TEST-", $m[3] = rest of filename
			$normalized = $m[1] . $m[3];
			return $normalized;
		}

		// Collapse repeated identical suffix patterns like "file-main-main-main.ext" → "file-main.ext"
		// Pattern: if hyphen-delimited segments before extension repeat ≥2, collapse to one
		// Example: lettuce-field-sunrise-main-main-main.jpg → lettuce-field-sunrise-main.jpg
		if ( preg_match( '/^(.+?)(-([a-z0-9]+)(?:-\3)+)(\.[a-z0-9]+)$/i', $basename, $m ) ) {
			// $m[1] = "lettuce-field-sunrise", $m[3] = "main", $m[4] = ".jpg"
			$normalized = $m[1] . '-' . $m[3] . $m[4];
			return $normalized;
		}

		return $basename;
	}

	/**
	 * Strip repeated occurrences of a rename tag from the end of a basename.
	 *
	 * @param string $filename Filename (basename or full filename).
	 * @param string $tag      Tag to strip (without leading hyphen).
	 * @return string Basename without extension and duplicate tags.
	 */
	private function normalize_basename_without_tag( $filename, $tag ) {
		$ext  = pathinfo( $filename, PATHINFO_EXTENSION );
		$name = $ext ? substr( $filename, 0, -( strlen( $ext ) + 1 ) ) : $filename;

		if ( $tag !== '' ) {
			$pattern = '/(?:-' . preg_quote( $tag, '/' ) . ')+$/';
			$name    = preg_replace( $pattern, '', $name );
		}

		return $name;
	}

	/**
	 * Build a canonical filename for a sized image.
	 */
	private function build_size_filename( $clean_basename, $ext, $width, $height, $tag = '' ) {
		$suffix = ( $width > 0 && $height > 0 ) ? '-' . $width . 'x' . $height : '';
		$tagbit = $tag !== '' ? '-' . $tag : '';

		return $clean_basename . $tagbit . $suffix . '.' . $ext;
	}

	/**
	 * Build the value that should be stored in metadata sizes[*]['file'].
	 */
	private function build_sizes_file_value( $clean_basename, $ext, $width, $height, $tag = '' ) {
		return $this->build_size_filename( $clean_basename, $ext, $width, $height, $tag );
	}

	/**
	 * Resolve the actual disk path of a size file when metadata is corrupted.
	 *
	 * @return string Absolute path or empty string if nothing found.
	 */
	private function resolve_old_size_path( $dir, $clean_old_base, $ext, $data ) {
		// 1) Trust metadata if file exists.
		if ( ! empty( $data['file'] ) ) {
			$candidate = trailingslashit( $dir ) . $data['file'];
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}

		$width  = isset( $data['width'] ) ? (int) $data['width'] : 0;
		$height = isset( $data['height'] ) ? (int) $data['height'] : 0;

		// 2) Reconstruct expected WxH path.
		if ( $width > 0 && $height > 0 ) {
			$expected = trailingslashit( $dir ) . $clean_old_base . '-' . $width . 'x' . $height . '.' . $ext;
			if ( file_exists( $expected ) ) {
				return $expected;
			}
		}

		// 3) Fallback: glob for any size variant.
		$pattern = trailingslashit( $dir ) . $clean_old_base . '-*x*.' . $ext;
		$matches = glob( $pattern );
		if ( ! empty( $matches ) ) {
			if ( $width > 0 && $height > 0 ) {
				foreach ( $matches as $candidate ) {
					if ( preg_match( '/-(\d+)x(\d+)\.' . preg_quote( $ext, '/' ) . '$/', $candidate, $mm ) ) {
						if ( (int) $mm[1] === $width && (int) $mm[2] === $height ) {
							return $candidate;
						}
					}
				}
			}

			// If we only found one candidate, use it.
			if ( count( $matches ) === 1 ) {
				return $matches[0];
			}
		}

		return '';
	}

	/**
	 * Repair attachment metadata that contains repeated filename prefixes.
	 *
	 * @param int  $attachment_id Attachment post ID.
	 * @param bool $dry_run       When true, calculate changes without persisting them.
	 * @return array|WP_Error Report of changes or WP_Error on invalid input.
	 */
	public function repair_corrupted_attachment( $attachment_id, $dry_run = false ) {
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			return new WP_Error( 'invalid_attachment', 'Invalid attachment ID supplied.' );
		}

		$result = array(
			'attachment_id'     => $attachment_id,
			'dry_run'           => (bool) $dry_run,
			'meta_before'       => null,
			'meta_after'        => null,
			'meta_changed'      => false,
			'metadata_changed'  => false,
			'post_name_before'  => null,
			'post_name_after'   => null,
			'post_name_changed' => false,
			'notes'             => array(),
			'status'            => 'clean',
		);

		$current_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$result['meta_before'] = $current_relative;

		if ( empty( $current_relative ) ) {
			$result['notes'][] = 'Attachment has no stored _wp_attached_file meta.';
			return $result;
		}

		$normalized_relative = $this->normalize_attached_file( $current_relative );
		$result['meta_after'] = $normalized_relative;

		if ( $normalized_relative !== $current_relative ) {
			$result['meta_changed'] = true;
			$result['notes'][]      = sprintf(
				'Normalized _wp_attached_file from "%s" to "%s".',
				$current_relative,
				$normalized_relative
			);

			if ( ! $dry_run ) {
				update_post_meta( $attachment_id, '_wp_attached_file', $normalized_relative );
			}
		}

		$metadata          = wp_get_attachment_metadata( $attachment_id );
		$metadata_modified = false;

		if ( is_array( $metadata ) && ! empty( $metadata ) ) {
			if ( ! empty( $metadata['file'] ) ) {
				$original_file    = $metadata['file'];
				$normalized_file = $this->normalize_attached_file( $metadata['file'] );
				if ( $normalized_file !== $original_file ) {
					$metadata['file'] = $normalized_file;
					$metadata_modified = true;
					$result['notes'][] = sprintf(
						'Normalized attachment metadata[file] from "%s" to "%s".',
						$original_file,
						$normalized_file
					);
				}
			}

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_key => $size_data ) {
					if ( empty( $size_data['file'] ) ) {
						continue;
					}

					$original_size = $size_data['file'];
					$normalized_size = $this->normalize_basename( $size_data['file'] );
					if ( $normalized_size !== $original_size ) {
						$metadata['sizes'][ $size_key ]['file'] = $normalized_size;
						$metadata_modified = true;
						$result['notes'][] = sprintf(
							'Normalized metadata size "%s" from "%s" to "%s".',
							$size_key,
							$original_size,
							$normalized_size
						);
					}
				}
			}

			if ( $metadata_modified ) {
				$result['metadata_changed'] = true;
				if ( ! $dry_run ) {
					wp_update_attachment_metadata( $attachment_id, $metadata );
				}
			}
		}

		if ( $normalized_relative ) {
			$new_slug = sanitize_title( pathinfo( $normalized_relative, PATHINFO_FILENAME ) );
			$post     = get_post( $attachment_id );

			if ( $post ) {
				$result['post_name_before'] = $post->post_name;
				$result['post_name_after']  = $new_slug;

				if ( $post->post_name !== $new_slug && $new_slug !== '' ) {
					$result['post_name_changed'] = true;
					$result['notes'][]           = sprintf(
						'Updated attachment slug from "%s" to "%s".',
						$post->post_name,
						$new_slug
					);

					if ( ! $dry_run ) {
						wp_update_post(
							array(
								'ID'        => $attachment_id,
								'post_name' => $new_slug,
							)
						);
					}
				}
			}
		}

		if ( $result['meta_changed'] || $result['metadata_changed'] || $result['post_name_changed'] ) {
			$result['status'] = $dry_run ? 'needs_repair' : 'repaired';
		}

		return $result;
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if WP-Cron is available for scheduling
	 * Uses static cache and transient backoff to avoid repeated failures
	 *
	 * @return bool True if cron is available, false otherwise
	 */
	private static function cron_is_available() {
		// Return cached result for this request
		if ( self::$cron_ok !== null ) {
			return self::$cron_ok;
		}

		// Check if recently detected as broken (10-minute backoff)
		if ( get_transient( 'msh_cron_broken' ) ) {
			self::$cron_ok = false;
			return false;
		}

		global $wpdb;

		// Bail early if the cron option is excessively large (indicates previous bloat)
		$size_threshold = (int) apply_filters( 'msh_cron_option_size_limit', 1024 * 1024 ); // 1 MB default
		if ( $size_threshold > 0 && $wpdb instanceof wpdb ) {
			$cron_size = (int) $wpdb->get_var(
				"SELECT LENGTH(option_value) FROM {$wpdb->options} WHERE option_name = 'cron' LIMIT 1"
			);

			if ( $cron_size > $size_threshold ) {
				self::$cron_ok = false;
				set_transient( 'msh_cron_broken', 1, 10 * MINUTE_IN_SECONDS );
				error_log(
					sprintf(
						'TinyDot: WP-Cron disabled; cron option is %s (limit %s).',
						function_exists( 'size_format' ) ? size_format( $cron_size ) : $cron_size,
						function_exists( 'size_format' ) ? size_format( $size_threshold ) : $size_threshold
					)
				);
				return false;
			}
		}

		// Probe cron by attempting to schedule a test event
		$ts = time() + 300; // 5 minutes in future
		$ok = @wp_schedule_single_event( $ts, 'msh_cron_probe', array() );

		if ( ! $ok ) {
			// Cron is broken - set backoff transient
			self::$cron_ok = false;
			set_transient( 'msh_cron_broken', 1, 10 * MINUTE_IN_SECONDS );
			error_log( 'TinyDot: WP-Cron unavailable, disabling cleanup scheduling for 10 minutes.' );
			return false;
		}

		// Clean up the test event
		wp_unschedule_event( $ts, 'msh_cron_probe', array() );

		self::$cron_ok = true;
		return true;
	}

	/**
	 * Schedule backup cleanup with tokenized path
	 * Only schedules if cron is available
	 *
	 * @param string $backup_path Full path to backup file
	 */
	private function schedule_backup_cleanup_for_path( $backup_path ) {
		if ( ! $backup_path || ! file_exists( $backup_path ) ) {
			return;
		}

		// Only create token map and schedule if cron is available
		if ( self::cron_is_available() ) {
			// Map long path to short token to avoid cron option bloat
			$token = md5( $backup_path );
			update_option( 'msh_cleanup_map_' . $token, $backup_path, false ); // not autoloaded

			@wp_schedule_single_event(
				time() + (int) $this->backup_retention,
				'msh_cleanup_rename_backup',
				array( $token )
			);
		}
		// If cron unavailable, backups will accumulate until manual cleanup or GC (Phase 2)
	}

	public function maybe_create_log_table() {
		if ( get_option( 'msh_rename_log_table_version' ) === '1' ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->log_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            attachment_id int(11) NOT NULL,
            old_filename varchar(255) NOT NULL,
            new_filename varchar(255) NOT NULL,
            old_url varchar(500) NOT NULL,
            new_url varchar(500) NOT NULL,
            old_relative varchar(500) NOT NULL,
            new_relative varchar(500) NOT NULL,
            renamed_date datetime DEFAULT CURRENT_TIMESTAMP,
            replaced_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            details text NULL,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY old_url (old_url(191))
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'msh_rename_log_table_version', '1' );
	}

	/**
	 * Initialize WP_Filesystem
	 *
	 * @return bool True if filesystem is available
	 */
	private function init_filesystem() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		return isset( $wp_filesystem );
	}

	/**
	 * Validate path is within uploads directory
	 *
	 * @param string $path Path to validate
	 * @return bool True if path is safe
	 */
	private function is_safe_path( $path ) {
		$uploads = wp_get_upload_dir();
		$uploads_root = wp_normalize_path( $uploads['basedir'] );
		$normalized = wp_normalize_path( $path );

		// Get real path to resolve symlinks
		$real_path = realpath( $path );
		if ( $real_path ) {
			$normalized = wp_normalize_path( $real_path );
		}

		return str_starts_with( $normalized, $uploads_root );
	}

	public function rename_attachment( $attachment_id, $new_filename, $test_mode = false ) {
		// DEBUG: Log every call to rename_attachment to detect multiple invocations
		error_log( '[MSH Rename DEBUG] ========== rename_attachment() CALLED ==========' );
		error_log( '[MSH Rename DEBUG] Attachment ID: ' . $attachment_id );
		error_log( '[MSH Rename DEBUG] Requested filename: ' . $new_filename );
		error_log( '[MSH Rename DEBUG] Test mode: ' . ( $test_mode ? 'YES' : 'NO' ) );
		error_log( '[MSH Rename DEBUG] Call stack: ' . wp_debug_backtrace_summary() );

		$this->test_mode         = (bool) $test_mode;
		$this->last_replacements = 0;
		if ( ! $this->test_mode ) {
			$this->clear_usage_lookup_cache();
		}

		// ====== PRE-RENAME METADATA VALIDATION & AUTO-REPAIR ======
		// CRITICAL: Fix corrupted metadata BEFORE attempting rename
		$current_path = get_attached_file( $attachment_id );
		error_log( '[MSH Rename DEBUG] Current file path from database: ' . $current_path );

		if ( ! $current_path || ! file_exists( $current_path ) ) {
			error_log( '[MSH AUTO-REPAIR] Corrupted metadata detected for attachment #' . $attachment_id );
			error_log( '[MSH AUTO-REPAIR] Database says file is: ' . $current_path );

			// Try to find the actual file on disk
			$repaired_path = $this->auto_repair_corrupted_metadata( $attachment_id, $current_path );

			if ( $repaired_path && file_exists( $repaired_path ) ) {
				error_log( '[MSH AUTO-REPAIR] SUCCESS - Repaired metadata, actual file found at: ' . $repaired_path );
				$current_path = $repaired_path;
			} else {
				error_log( '[MSH AUTO-REPAIR] FAILED - Could not locate actual file on disk' );
				return new WP_Error( 'missing_file', __( 'Original file not found for attachment. Automatic repair attempted but failed.', 'msh-image-optimizer' ) );
			}
		}
		error_log( '[MSH Rename DEBUG] Verified file path: ' . $current_path );
		// ====== END PRE-RENAME VALIDATION & AUTO-REPAIR ======

		$new_filename     = sanitize_file_name( $new_filename );
		error_log( '[MSH Rename DEBUG] Sanitized filename: ' . $new_filename );

		$current_basename = basename( $current_path );
		if ( $new_filename === '' || strcasecmp( $current_basename, $new_filename ) === 0 ) {
			error_log( '[MSH Rename DEBUG] SKIPPING - filename unchanged' );
			return array(
				'old_url'  => wp_get_attachment_url( $attachment_id ),
				'new_url'  => wp_get_attachment_url( $attachment_id ),
				'replaced' => 0,
				'skipped'  => true,
			);
		}

		$upload_dir   = wp_upload_dir();
		$old_url      = wp_get_attachment_url( $attachment_id );
		$old_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		error_log( '[MSH Rename DEBUG] Old relative path from meta: ' . $old_relative );

		$new_filename = $this->ensure_unique_filename( $new_filename, dirname( $current_path ), $attachment_id );
		error_log( '[MSH Rename DEBUG] Unique filename after collision check: ' . $new_filename );

		$new_relative = str_replace( basename( $old_relative ), $new_filename, $old_relative );
		error_log( '[MSH Rename DEBUG] New relative path calculated: ' . $new_relative );

		$new_url      = trailingslashit( $upload_dir['baseurl'] ) . ltrim( $new_relative, '/' );
		error_log( '[MSH Rename DEBUG] New URL: ' . $new_url );

		$log_id = $this->log_intent( $attachment_id, $current_basename, $new_filename, $old_url, $new_url, $old_relative, $new_relative );

		$old_metadata = wp_get_attachment_metadata( $attachment_id );

		if ( $this->test_mode ) {
			$map      = $this->build_search_replace_map( $old_url, $new_url, $old_metadata, $upload_dir, $attachment_id );
			$replaced = $this->replace_references( $map, $attachment_id, $current_basename, $new_filename );

			if ( is_wp_error( $replaced ) ) {
				$this->update_log( $log_id, 'failed', 0, $replaced->get_error_message() );
				return $replaced;
			}

			$this->last_replacements = $replaced;
			$this->update_log( $log_id, 'test', $replaced, __( 'Test mode - no filesystem changes applied.', 'msh-image-optimizer' ) );

			return array(
				'old_url'   => $old_url,
				'new_url'   => $new_url,
				'replaced'  => $replaced,
				'backup'    => null,
				'test_mode' => true,
			);
		}

		$rename        = null;
		$backup_path   = '';
		$new_path      = '';
		$updated_metadata = $old_metadata;

		if ( function_exists( 'msh_optimize_and_heal' ) ) {
			$atomic = msh_optimize_and_heal(
				$attachment_id,
				static function () use ( $new_relative ) {
					return $new_relative;
				},
				array(
					'source'        => $current_path,
					'delete_source' => true,
				)
			);

			if ( is_wp_error( $atomic ) ) {
				$this->update_log( $log_id, 'failed', 0, $atomic->get_error_message() );
				return $atomic;
			}

			$new_path         = $atomic['absolute_path'] ?? trailingslashit( $upload_dir['basedir'] ) . ltrim( $new_relative, '/' );
			$updated_metadata = $atomic['metadata'] ?? wp_get_attachment_metadata( $attachment_id );
		} else {
			$rename = $this->rename_physical_files( $current_path, $new_filename, $old_metadata );
			if ( is_wp_error( $rename ) ) {
				$this->update_log( $log_id, 'failed', 0, $rename->get_error_message() );
				return $rename;
			}

			$updated_metadata = isset( $rename['metadata'] ) ? $rename['metadata'] : $old_metadata;
			$this->update_wordpress_metadata( $attachment_id, $rename['new_path'], $updated_metadata, $new_relative );
			$new_path    = $rename['new_path'];
			$backup_path = isset( $rename['backup_path'] ) ? $rename['backup_path'] : '';
		}

		$map      = $this->build_search_replace_map( $old_url, $new_url, $old_metadata, $upload_dir, $attachment_id );
		$replaced = $this->replace_references( $map, $attachment_id, $current_basename, $new_filename );

		if ( is_wp_error( $replaced ) ) {
			$this->restore_failed_rename(
				$attachment_id,
				$current_path,
				is_array( $rename ) ? $rename : array(),
				$old_metadata,
				$old_relative,
				$old_url
			);
			$this->update_log( $log_id, 'failed', 0, $replaced->get_error_message() );
			return $replaced;
		}

		$this->last_replacements = $replaced;
		$this->update_log( $log_id, 'complete', $replaced, null );
		if ( ! $this->test_mode ) {
			$this->clear_usage_lookup_cache();
		}

		// ====== POST-RENAME VALIDATION & HEAL ======
		// Belt-and-suspenders: verify DB is in correct state after rename completes
		$final_rel = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $final_rel !== $new_relative ) {
			error_log( "[MSH HEAL] Final _wp_attached_file mismatch for #{$attachment_id}! Expected: {$new_relative}, Got: {$final_rel}" );
			if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
				$guard = msh_update_attached_file_collapsed( $attachment_id, $new_relative );
				if ( is_wp_error( $guard ) ) {
					$this->update_log( $log_id, 'failed', 0, $guard->get_error_message() );
					return $guard;
				}
				$new_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
			} else {
				update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );
			}
			error_log( "[MSH HEAL] Corrected _wp_attached_file to: {$new_relative}" );
		}

		$final_meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $final_meta['file'] ) && $final_meta['file'] !== $new_relative ) {
			error_log( "[MSH HEAL] Final metadata[file] mismatch for #{$attachment_id}! Expected: {$new_relative}, Got: {$final_meta['file']}" );
			$final_meta['file'] = $new_relative;

			// Also normalize sizes basenames if needed
			if ( ! empty( $final_meta['sizes'] ) && is_array( $final_meta['sizes'] ) ) {
				foreach ( $final_meta['sizes'] as $k => $s ) {
					if ( ! empty( $s['file'] ) ) {
						$final_meta['sizes'][ $k ]['file'] = $this->normalize_basename( $s['file'] );
					}
				}
			}

			wp_update_attachment_metadata( $attachment_id, $final_meta );
			error_log( "[MSH HEAL] Corrected metadata[file] and sizes to use new basename" );
		}
		// ====== END POST-RENAME VALIDATION & HEAL ======

		return array(
			'old_url'  => $old_url,
			'new_url'  => $new_url,
			'replaced' => $replaced,
			'backup'   => $backup_path,
		);
	}

	private function restore_failed_rename( $attachment_id, $original_path, array $rename, $old_metadata, $old_relative, $old_url ) {
		$new_path    = isset( $rename['new_path'] ) ? $rename['new_path'] : '';
		$backup_path = isset( $rename['backup_path'] ) ? $rename['backup_path'] : '';

		// Clean up new file if it exists
		if ( $new_path && file_exists( $new_path ) && $this->is_safe_path( $new_path ) ) {
			wp_delete_file( $new_path );
		}

		// Restore backup
		if ( $backup_path && file_exists( $backup_path ) && $this->is_safe_path( $backup_path ) && $this->is_safe_path( $original_path ) ) {
			if ( $this->init_filesystem() ) {
				global $wp_filesystem;
				$wp_filesystem->move( $backup_path, $original_path, true );
			}
		}

		update_attached_file( $attachment_id, $original_path );

		if ( is_array( $old_metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $old_metadata );
		}

		if ( $old_relative ) {
			if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
				$guard = msh_update_attached_file_collapsed( $attachment_id, $old_relative );
				if ( is_wp_error( $guard ) ) {
					error_log( '[MSH RESTORE] Failed to restore _wp_attached_file: ' . $guard->get_error_message() );
					update_post_meta( $attachment_id, '_wp_attached_file', $old_relative );
				}
			} else {
				update_post_meta( $attachment_id, '_wp_attached_file', $old_relative );
			}
		}

		if ( $old_url ) {
			$original_slug = $old_relative
				? sanitize_title( pathinfo( $old_relative, PATHINFO_FILENAME ) )
				: sanitize_title( pathinfo( $old_url, PATHINFO_FILENAME ) );

			wp_update_post(
				array(
					'ID'        => $attachment_id,
					'post_name' => $original_slug,
				)
			);
		}

		if ( ! $this->test_mode ) {
			$this->clear_usage_lookup_cache();
		}
	}

	private function clear_usage_lookup_cache() {
		delete_transient( $this->content_lookup_cache_key );
		delete_option( $this->content_lookup_snapshot_option );
		delete_option( $this->content_lookup_queue_option );

		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			$hook = 'msh_content_usage_lookup_refresh';

			if ( class_exists( 'MSH_Content_Usage_Lookup' ) ) {
				$lookup = MSH_Content_Usage_Lookup::get_instance();
				if ( method_exists( $lookup, 'get_scheduled_hook' ) ) {
					$hook = $lookup->get_scheduled_hook();
				}
			}

			wp_clear_scheduled_hook( $hook );
		}
	}

	private function log_intent( $attachment_id, $old_filename, $new_filename, $old_url, $new_url, $old_relative, $new_relative ) {
		global $wpdb;

		$wpdb->insert(
			$this->log_table,
			array(
				'attachment_id' => $attachment_id,
				'old_filename'  => $old_filename,
				'new_filename'  => $new_filename,
				'old_url'       => $old_url,
				'new_url'       => $new_url,
				'old_relative'  => $old_relative,
				'new_relative'  => $new_relative,
				'status'        => 'pending',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	private function update_log( $log_id, $status, $replaced_count = 0, $details = null ) {
		global $wpdb;

		$wpdb->update(
			$this->log_table,
			array(
				'status'         => $status,
				'replaced_count' => intval( $replaced_count ),
				'details'        => $details,
			),
			array( 'id' => $log_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	private function rename_physical_files( $old_path, $new_filename, $old_metadata ) {
		$dir      = dirname( $old_path );
		$new_path = trailingslashit( $dir ) . $new_filename;

		// Clear stat cache to avoid stale file information
		clearstatcache( true, $old_path );
		clearstatcache( true, $new_path );

		// Apply Local by Flywheel specific permission fixes
		$this->fix_local_permissions( $old_path );
		$this->fix_local_permissions( $dir );

		// Detailed permission and existence checks with logging
		if ( ! file_exists( $old_path ) ) {
			error_log( 'MSH Rename: File does not exist at ' . $old_path );
			return new WP_Error( 'file_not_found', 'Original file does not exist: ' . basename( $old_path ) );
		}

		if ( ! is_readable( $old_path ) ) {
			error_log( 'MSH Rename: Cannot read file at ' . $old_path );
			return new WP_Error( 'permission_denied', 'Cannot read original file: ' . basename( $old_path ) );
		}

		if ( ! wp_is_writable( $dir ) ) {
			error_log( 'MSH Rename: Directory not writable: ' . $dir . ' (perms: ' . substr( sprintf( '%o', fileperms( $dir ) ), -4 ) . ')' );
			return new WP_Error( 'permission_denied', 'Directory is not writable: ' . $dir );
		}

		// Create backup directory with explicit error checking
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/msh-rename-backups';
		if ( ! file_exists( $backup_dir ) ) {
			if ( ! wp_mkdir_p( $backup_dir ) ) {
				error_log( 'MSH Rename: Cannot create backup directory: ' . $backup_dir );
				return new WP_Error( 'backup_failed', 'Cannot create backup directory' );
			}
		}

		// Create backup with explicit error checking - NO ERROR SUPPRESSION
		$backup_path = trailingslashit( $backup_dir ) . basename( $old_path ) . '.' . time();
		error_log( 'MSH Rename: Creating backup from ' . $old_path . ' to ' . $backup_path );

		if ( ! copy( $old_path, $backup_path ) ) {
			$error = error_get_last();
			error_log( 'MSH Rename: Backup failed - ' . ( $error['message'] ?? 'Unknown error' ) );
			return new WP_Error( 'backup_failed', 'Unable to create backup: ' . ( $error['message'] ?? 'Unknown error' ) );
		}

		// CRITICAL: Perform the actual rename using WP_Filesystem
		error_log( 'MSH Rename: Attempting rename from ' . $old_path . ' to ' . $new_path );

		if ( ! $this->init_filesystem() ) {
			error_log( 'MSH Rename: Failed to initialize WP_Filesystem' );
			return new WP_Error( 'filesystem_error', 'Could not initialize filesystem' );
		}

		global $wp_filesystem;
		$rename_result = $wp_filesystem->move( $old_path, $new_path, true );

		if ( ! $rename_result ) {
			error_log( 'MSH Rename: Rename failed via WP_Filesystem' );

			// Try alternative: copy then delete
			error_log( 'MSH Rename: Trying copy+delete fallback' );
			if ( copy( $old_path, $new_path ) ) {
				if ( $this->is_safe_path( $old_path ) ) {
					wp_delete_file( $old_path );
					error_log( 'MSH Rename: Copy+delete fallback succeeded' );
					$rename_result = true;
				} else {
					// Copy worked but delete failed - clean up the copy
					if ( $this->is_safe_path( $new_path ) ) {
						wp_delete_file( $new_path );
					}
					if ( $this->is_safe_path( $backup_path ) ) {
						wp_delete_file( $backup_path );
					}
					error_log( 'MSH Rename: Could not delete original after copy - path validation failed' );
					return new WP_Error( 'rename_failed', 'Could not complete rename operation: path validation failed' );
				}
			} else {
				// Clean up backup
				if ( $this->is_safe_path( $backup_path ) ) {
					wp_delete_file( $backup_path );
				}
				$copy_error = error_get_last();
				return new WP_Error( 'rename_failed', 'Unable to rename file: ' . ( $copy_error['message'] ?? 'Unknown error' ) );
			}
		}

		error_log( 'MSH Rename: Main file renamed successfully' );

		$old_basename_raw = pathinfo( $old_path, PATHINFO_FILENAME );
		$new_basename_raw = pathinfo( $new_filename, PATHINFO_FILENAME );
		$ext               = pathinfo( $new_filename, PATHINFO_EXTENSION );
		$tag               = '';
		if ( substr( $new_basename_raw, -strlen( '-msh-regression' ) ) === '-msh-regression' ) {
			$tag = 'msh-regression';
		}
		$clean_old_base = $this->normalize_basename_without_tag( $old_basename_raw, $tag );
		$clean_new_base = $this->normalize_basename_without_tag( $new_basename_raw, $tag );

		// Handle sized images (thumbnails) - WITHOUT error suppression
		if ( is_array( $old_metadata ) && ! empty( $old_metadata['sizes'] ) ) {
			foreach ( $old_metadata['sizes'] as $size_key => $data ) {
				$has_dimensions = ! empty( $data['width'] ) && ! empty( $data['height'] );
				if ( empty( $data['file'] ) && ! $has_dimensions ) {
					error_log( "[MSH Rename] Skip size '{$size_key}' because metadata has neither file nor dimensions" );
					continue;
				}

				$old_size_path = $this->resolve_old_size_path( $dir, $clean_old_base, $ext, $data );
				if ( empty( $old_size_path ) ) {
					$metafile = isset( $data['file'] ) ? $data['file'] : '(none)';
					error_log( "[MSH Rename] Thumbnail not found for '{$size_key}'. Metadata file '{$metafile}' not on disk" );
					continue;
				}

				$width  = isset( $data['width'] ) ? intval( $data['width'] ) : 0;
				$height = isset( $data['height'] ) ? intval( $data['height'] ) : 0;
				$new_size_filename = $this->build_size_filename( $clean_new_base, $ext, $width, $height, $tag );
				$new_size_path     = trailingslashit( $dir ) . $new_size_filename;

				if ( realpath( $old_size_path ) === realpath( $new_size_path ) ) {
					error_log( "[MSH Rename] Size '{$size_key}' already at target: " . basename( $new_size_path ) );
				} else {
					$size_backup = $backup_dir . '/' . basename( $old_size_path ) . '.' . time();
					copy( $old_size_path, $size_backup );

					if ( $this->init_filesystem() ) {
						global $wp_filesystem;
						$thumb_result = $wp_filesystem->move( $old_size_path, $new_size_path, true );

						if ( ! $thumb_result ) {
							if ( copy( $old_size_path, $new_size_path ) && $this->is_safe_path( $old_size_path ) ) {
								wp_delete_file( $old_size_path );
								error_log( 'MSH Rename: Thumbnail renamed via copy+delete: ' . basename( $old_size_path ) );
							} else {
								error_log( 'MSH Rename: Failed to rename thumbnail ' . basename( $old_size_path ) );
							}
						} else {
							error_log( 'MSH Rename: Thumbnail renamed successfully: ' . basename( $new_size_path ) );
						}
					}
				}

				// Update metadata entry to canonical value
				if ( $width > 0 && $height > 0 ) {
					$old_metadata['sizes'][ $size_key ]['file'] = $this->build_sizes_file_value( $clean_new_base, $ext, $width, $height, $tag );
				} else {
					if ( preg_match( '/-(\d+)x(\d+)\.' . preg_quote( $ext, '/' ) . '$/', basename( $new_size_filename ), $mm ) ) {
						$old_metadata['sizes'][ $size_key ]['width']  = intval( $mm[1] );
						$old_metadata['sizes'][ $size_key ]['height'] = intval( $mm[2] );
					}
					$old_metadata['sizes'][ $size_key ]['file'] = basename( $new_size_filename );
				}
			}
		}

		if ( is_array( $old_metadata ) && ! empty( $old_metadata['file'] ) ) {
			$subdir = trim( dirname( $old_metadata['file'] ), '/' );
			$clean_new_base = $this->normalize_basename_without_tag( $new_basename_raw, $tag );
			$relative_path  = $clean_new_base . '.' . $ext;
			if ( $subdir !== '.' && $subdir !== '' ) {
				$relative_path = $subdir . '/' . $relative_path;
			}
			$old_metadata['file'] = $relative_path;
		}

		// Handle WordPress large image original file (for images >2560px that were auto-scaled)
		if ( is_array( $old_metadata ) && ! empty( $old_metadata['original_image'] ) ) {
			$old_original_path = trailingslashit( $dir ) . $old_metadata['original_image'];
			if ( file_exists( $old_original_path ) ) {
				$ext              = pathinfo( $old_metadata['original_image'], PATHINFO_EXTENSION );
				$new_basename     = pathinfo( $new_filename, PATHINFO_FILENAME );
				// Remove -scaled suffix if present (main file is scaled, original is not)
				$new_original_base     = str_replace( '-scaled', '', $new_basename );
				$new_original_filename = $new_original_base . '.' . $ext;
				$new_original_path     = trailingslashit( $dir ) . $new_original_filename;

				error_log( 'MSH Rename: Renaming original large image from ' . basename( $old_original_path ) . ' to ' . $new_original_filename );

				// Backup original file
				$original_backup = $backup_dir . '/' . basename( $old_original_path ) . '.' . time();
				copy( $old_original_path, $original_backup );

				// Rename original file using WP_Filesystem
				if ( $this->init_filesystem() ) {
					global $wp_filesystem;
					$original_result = $wp_filesystem->move( $old_original_path, $new_original_path, true );

					if ( ! $original_result ) {
						// Try copy + delete fallback
						if ( copy( $old_original_path, $new_original_path ) && $this->is_safe_path( $old_original_path ) ) {
							wp_delete_file( $old_original_path );
							error_log( 'MSH Rename: Original large image renamed via copy+delete: ' . $new_original_filename );
						} else {
							error_log( 'MSH Rename: Failed to rename original large image ' . basename( $old_original_path ) );
						}
					} else {
						error_log( 'MSH Rename: Original large image renamed successfully: ' . $new_original_filename );
					}
				}
			} else {
				error_log( 'MSH Rename: Original large image not found at ' . $old_original_path );
			}
		}

		// Schedule cleanup with tokenized path (LOCATION 1)
		$this->schedule_backup_cleanup_for_path( $backup_path );

		return array(
			'new_path'    => $new_path,
			'backup_path' => $backup_path,
			'metadata'    => $old_metadata,
		);
	}

	/**
	 * Fix Local by Flywheel specific permission issues
	 */
	private function fix_local_permissions( $file_path ) {
		// Local by Flywheel specific permission fix
		$is_local = (
			defined( 'LOCAL_DEVELOPMENT' ) ||
			( isset( $_SERVER['SERVER_SOFTWARE'] ) && strpos( $_SERVER['SERVER_SOFTWARE'], 'nginx' ) !== false ) ||
			file_exists( '/tmp/mysql.sock' ) ||
			( isset( $_SERVER['FLYWHEEL_LOCAL'] ) && $_SERVER['FLYWHEEL_LOCAL'] )
		);

		if ( $is_local ) {
			$dir = is_dir( $file_path ) ? $file_path : dirname( $file_path );

			// Initialize WP_Filesystem
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();

			// Try to set proper permissions
			if ( is_dir( $dir ) && $wp_filesystem ) {
				$wp_filesystem->chmod( $dir, 0755 );
				error_log( 'MSH Rename: Set directory permissions 0755 for ' . $dir );
			}

			if ( file_exists( $file_path ) && ! is_dir( $file_path ) && $wp_filesystem ) {
				$wp_filesystem->chmod( $file_path, 0644 );
				error_log( 'MSH Rename: Set file permissions 0644 for ' . $file_path );
			}

			// Clear opcache if available (Local uses it)
			if ( function_exists( 'opcache_invalidate' ) && file_exists( $file_path ) ) {
				opcache_invalidate( $file_path, true );
			}

			// Clear realpath cache
			clearstatcache( true, $file_path );
			clearstatcache( true, $dir );
		}
	}

	/**
	 * Test method to verify rename capability
	 */
	public function test_simple_rename() {
		$upload_dir = wp_upload_dir();
		$test_file  = $upload_dir['basedir'] . '/test-rename-' . time() . '.txt';

		// Create test file
		file_put_contents( $test_file, 'test content for rename verification' );
		error_log( 'MSH Test: Created test file at ' . $test_file );

		// Apply permission fixes
		$this->fix_local_permissions( $test_file );

		// Test rename using WP_Filesystem
		$new_name = $upload_dir['basedir'] . '/test-renamed-' . time() . '.txt';

		if ( ! $this->init_filesystem() ) {
			if ( file_exists( $test_file ) && $this->is_safe_path( $test_file ) ) {
				wp_delete_file( $test_file );
			}
			return array(
				'success' => false,
				'message' => 'Filesystem initialization failed',
			);
		}

		global $wp_filesystem;
		$result = $wp_filesystem->move( $test_file, $new_name, true );

		if ( $result ) {
			error_log( 'MSH Test: SUCCESS - File renamed to ' . $new_name );
			if ( $this->is_safe_path( $new_name ) ) {
				wp_delete_file( $new_name ); // Clean up
			}
			return array(
				'success' => true,
				'message' => 'Rename test successful',
			);
		} else {
			error_log( 'MSH Test: FAILED - WP_Filesystem move returned false' );
			if ( file_exists( $test_file ) && $this->is_safe_path( $test_file ) ) {
				wp_delete_file( $test_file ); // Clean up
			}
			return array(
				'success' => false,
				'message' => 'Rename test failed: WP_Filesystem operation failed',
			);
		}
	}

	private function ensure_unique_filename( $filename, $directory, $attachment_id ) {
		$directory = trailingslashit( $directory );
		$filename  = function_exists( 'msh_collapse_id_suffix' )
			? msh_collapse_id_suffix( $filename, (int) $attachment_id )
			: $filename;

		$pathinfo  = pathinfo( $filename );
		$name      = $pathinfo['filename'];
		$ext       = isset( $pathinfo['extension'] ) && $pathinfo['extension'] !== '' ? '.' . $pathinfo['extension'] : '';
		$candidate = $filename;
		$counter   = 1;
		$appended  = false;

		while ( file_exists( $directory . $candidate ) ) {
			if ( ! $appended ) {
				if ( ! preg_match( '/-' . preg_quote( (string) $attachment_id, '/' ) . '$/', $name ) ) {
					$name .= '-' . $attachment_id;
				}
				$candidate = $name . $ext;
				$appended  = true;
				continue;
			}

			$candidate = sprintf( '%s-%d%s', $name, $counter, $ext );
			++$counter;
		}

		return $candidate;
	}

	private function move_to_backup( $path ) {
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );
		$real_path  = realpath( $path );

		if ( $real_path === false || strpos( $real_path, $base_dir ) !== 0 ) {
			return null;
		}

		$backup_dir = $base_dir . 'msh-rename-backups';
		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		$backup_path = trailingslashit( $backup_dir ) . basename( $path ) . '.' . time();

		// Use WP_Filesystem for backup
		$backup_success = false;
		if ( $this->init_filesystem() ) {
			global $wp_filesystem;
			$backup_success = $wp_filesystem->move( $path, $backup_path, true );
		}

		if ( $backup_success ) {
			// Schedule cleanup with tokenized path (LOCATION 2)
			$this->schedule_backup_cleanup_for_path( $backup_path );
			return $backup_path;
		}

		return null;
	}

	private function update_wordpress_metadata( $attachment_id, $new_path, $old_metadata, $new_relative ) {
		// DEBUG LOGGING: Track all metadata updates
		error_log( '[MSH Rename DEBUG] update_wordpress_metadata() called for attachment ' . $attachment_id );
		error_log( '[MSH Rename DEBUG] Parameters: new_path=' . $new_path . ', new_relative=' . $new_relative );

		// Read current value BEFORE any updates
		$before_value = get_post_meta( $attachment_id, '_wp_attached_file', true );
		error_log( '[MSH Rename DEBUG] _wp_attached_file BEFORE updates: ' . $before_value );

		if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
			$new_path = msh_update_attached_file_collapsed( $attachment_id, $new_relative );
			if ( is_wp_error( $new_path ) ) {
				error_log( '[MSH Rename DEBUG] Failed to update _wp_attached_file: ' . $new_path->get_error_message() );
				return;
			}
			$new_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		} else {
			error_log( '[MSH Rename DEBUG] Calling update_attached_file() with: ' . $new_relative );
			update_attached_file( $attachment_id, $new_relative );
		}

		$after_update_attached = get_post_meta( $attachment_id, '_wp_attached_file', true );
		error_log( '[MSH Rename DEBUG] _wp_attached_file AFTER update_attached_file(): ' . $after_update_attached );

		if ( is_array( $old_metadata ) ) {
			$metadata         = $old_metadata;
			$metadata['file'] = $new_relative;

			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => $data ) {
					$ext                                = pathinfo( $data['file'], PATHINFO_EXTENSION );
					$metadata['sizes'][ $size ]['file'] = pathinfo( $new_relative, PATHINFO_FILENAME ) . '-' . $data['width'] . 'x' . $data['height'] . '.' . $ext;
				}
			}

			// Handle WordPress large image scaling (original_image field for images >2560px)
			if ( ! empty( $metadata['original_image'] ) ) {
				$old_original = $metadata['original_image'];
				$ext          = pathinfo( $old_original, PATHINFO_EXTENSION );
				$new_basename = pathinfo( $new_relative, PATHINFO_FILENAME );
				// Remove -scaled suffix if present in the main filename to get original basename
				$new_original_base       = str_replace( '-scaled', '', $new_basename );
				$metadata['original_image'] = $new_original_base . '.' . $ext;
				error_log( "[MSH Rename DEBUG] Updated original_image from '{$old_original}' to '{$metadata['original_image']}'" );
			}

			error_log( '[MSH Rename DEBUG] Calling wp_update_attachment_metadata() with file: ' . $metadata['file'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );

			$after_wp_update = get_post_meta( $attachment_id, '_wp_attached_file', true );
			error_log( '[MSH Rename DEBUG] _wp_attached_file AFTER wp_update_attachment_metadata(): ' . $after_wp_update );
		}

		// CORRUPTION FIX: Do NOT call wp_generate_attachment_metadata() during rename
		// Thumbnails already exist on disk (renamed separately), and regenerating metadata
		// after physical rename can cause corruption (double prefixes, missing size suffixes, etc.)
		error_log( '[MSH Rename] Metadata paths updated - thumbnail regeneration not needed during rename' );

		// VALIDATION: Ensure metadata paths are correct (prevent corruption)
		$final_metadata    = wp_get_attachment_metadata( $attachment_id );
		$validation_errors = array();

		// Check main file path
		if ( empty( $final_metadata['file'] ) || $final_metadata['file'] !== $new_relative ) {
			$validation_errors[] = 'Main file path mismatch: expected ' . $new_relative . ', got ' . ( $final_metadata['file'] ?? 'EMPTY' );
		}

		// Check thumbnail paths have size suffixes
		if ( ! empty( $final_metadata['sizes'] ) && is_array( $final_metadata['sizes'] ) ) {
			$new_base = pathinfo( $new_relative, PATHINFO_FILENAME );

			foreach ( $final_metadata['sizes'] as $size => $data ) {
				if ( empty( $data['file'] ) ) {
					$validation_errors[] = "Thumbnail '$size' has empty file path";
					continue;
				}

				// Check if file has size suffix (pattern: -123x456.)
				if ( ! preg_match( '/-\d+x\d+\./', $data['file'] ) ) {
					$validation_errors[] = "Thumbnail '$size' missing size suffix: " . $data['file'];
				}

				// Check if base name matches
				$thumb_base = preg_replace( '/-\d+x\d+\..*$/', '', $data['file'] );
				if ( $thumb_base !== $new_base ) {
					$validation_errors[] = "Thumbnail '$size' base name mismatch: expected '$new_base', got '$thumb_base'";
				}
			}
		}

		// If validation errors found, attempt to fix them
		if ( ! empty( $validation_errors ) ) {
			error_log( '[MSH Rename ERROR] Metadata validation failed after update:' );
			foreach ( $validation_errors as $error ) {
				error_log( '[MSH Rename ERROR]   - ' . $error );
			}

			// Attempt to repair
			error_log( '[MSH Rename] Attempting automatic repair...' );

			$fixed_metadata         = $old_metadata; // Start with original metadata
			$fixed_metadata['file'] = $new_relative;

			// Fix thumbnail paths
			if ( ! empty( $fixed_metadata['sizes'] ) ) {
				foreach ( $fixed_metadata['sizes'] as $size => $data ) {
					$ext                                    = pathinfo( $data['file'], PATHINFO_EXTENSION );
					$fixed_metadata['sizes'][ $size ]['file'] = pathinfo( $new_relative, PATHINFO_FILENAME ) .
																'-' . $data['width'] . 'x' . $data['height'] .
																'.' . $ext;
				}
			}

			// Fix original_image if present
			if ( ! empty( $fixed_metadata['original_image'] ) ) {
				$ext               = pathinfo( $fixed_metadata['original_image'], PATHINFO_EXTENSION );
				$new_basename      = pathinfo( $new_relative, PATHINFO_FILENAME );
				$new_original_base = str_replace( '-scaled', '', $new_basename );
				$fixed_metadata['original_image'] = $new_original_base . '.' . $ext;
			}

			// Apply fix
			wp_update_attachment_metadata( $attachment_id, $fixed_metadata );
			if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
				$guard = msh_update_attached_file_collapsed( $attachment_id, $new_relative );
				if ( is_wp_error( $guard ) ) {
					error_log( '[MSH Rename] Automatic repair failed to update _wp_attached_file: ' . $guard->get_error_message() );
				}
			} else {
				update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );
			}

			error_log( '[MSH Rename] Automatic repair applied' );
		} else {
			error_log( '[MSH Rename] Metadata validation passed - all paths correct' );
		}

		$final_value = get_post_meta( $attachment_id, '_wp_attached_file', true );
		error_log( '[MSH Rename DEBUG] _wp_attached_file FINAL VALUE at end of method: ' . $final_value );

		$new_slug = sanitize_title( pathinfo( $new_relative, PATHINFO_FILENAME ) );
		wp_update_post(
			array(
				'ID'        => $attachment_id,
				'post_name' => $new_slug,
			)
		);
	}

	private function build_search_replace_map( $old_url, $new_url, $old_metadata, $upload_dir, $attachment_id = null ) {
		$map             = array();
		$map[ $old_url ] = $new_url;

		$old_relative         = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $old_url );
		$new_relative         = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $new_url );
		$map[ $old_relative ] = $new_relative;

		$map[ basename( $old_url ) ] = basename( $new_url );

		// Include ALL historical URLs for this attachment to clean up references from previous renames
		// This fixes the issue where post content contains references to intermediate filenames
		global $wpdb;

		if ( $attachment_id ) {
			$historical_urls = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT old_url FROM {$wpdb->prefix}msh_rename_log WHERE attachment_id = %d AND status = 'complete'",
					$attachment_id
				)
			);

			if ( ! empty( $historical_urls ) ) {
				foreach ( $historical_urls as $historical_url ) {
					// Map each historical URL to the NEW URL
					$map[ $historical_url ] = $new_url;

					// Also map historical basename to new basename
					$historical_basename = basename( $historical_url );
					$map[ $historical_basename ] = basename( $new_url );

					// Map historical relative path to new relative path
					$historical_relative = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $historical_url );
					$map[ $historical_relative ] = $new_relative;

					// Include size variants from historical URLs
					// Parse historical filename pattern: base-{width}x{height}.ext
					if ( preg_match( '/^(.+)-(\d+)x(\d+)\.(\w+)$/', $historical_basename, $matches ) ) {
						$historical_base = $matches[1];
						$width           = $matches[2];
						$height          = $matches[3];
						$ext             = $matches[4];

						// Generate corresponding new size variant
						$new_base             = pathinfo( basename( $new_url ), PATHINFO_FILENAME );
						$new_size_variant     = $new_base . '-' . $width . 'x' . $height . '.' . $ext;
						$map[ $historical_basename ] = $new_size_variant;

						// Full URL variants
						$historical_dir         = trailingslashit( dirname( $historical_url ) );
						$new_dir                = trailingslashit( dirname( $new_url ) );
						$historical_size_url    = $historical_dir . $historical_basename;
						$new_size_url           = $new_dir . $new_size_variant;
						$map[ $historical_size_url ] = $new_size_url;
					}
				}
			}
		}

		if ( is_array( $old_metadata ) && ! empty( $old_metadata['sizes'] ) ) {
			$old_dir = trailingslashit( dirname( $old_url ) );
			$new_dir = trailingslashit( dirname( $new_url ) );
			foreach ( $old_metadata['sizes'] as $size => $data ) {
				if ( empty( $data['file'] ) ) {
					continue;
				}

				$old_size_url         = $old_dir . $data['file'];
				$ext                  = pathinfo( $data['file'], PATHINFO_EXTENSION );
				$new_size_filename    = pathinfo( $new_url, PATHINFO_FILENAME ) . '-' . $data['width'] . 'x' . $data['height'] . '.' . $ext;
				$new_size_url         = $new_dir . $new_size_filename;
				$map[ $old_size_url ] = $new_size_url;

				$old_size_rel         = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $old_size_url );
				$new_size_rel         = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $new_size_url );
				$map[ $old_size_rel ] = $new_size_rel;
			}
		}

		return $map;
	}

	private function replace_references( $map, $attachment_id = null, $old_filename = null, $new_filename = null ) {
		global $wpdb;

		// Use the new targeted replacement engine if available and we have the required info
		if ( class_exists( 'MSH_Targeted_Replacement_Engine' ) && $attachment_id && $old_filename && $new_filename ) {

			$replacement_engine = MSH_Targeted_Replacement_Engine::get_instance();
			$result             = $replacement_engine->replace_attachment_urls( $attachment_id, $old_filename, $new_filename, $this->test_mode );

			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				return $result['updated_count'];
			}
		}

		// If targeted replacement not available, use fallback method

		$total_updates = 0; // Initialize the counter

		// Update posts table
		foreach ( $map as $old => $new ) {
			if ( $old === $new ) {
				continue;
			}

			$like = '%' . $wpdb->esc_like( $old ) . '%';

			$fields = array( 'post_content', 'post_excerpt' );
			foreach ( $fields as $field ) {
				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} SET {$field} = REPLACE({$field}, %s, %s) WHERE {$field} LIKE %s",
						$old,
						$new,
						$like
					)
				);
				if ( $updated !== false ) {
					$total_updates += $updated;
				}
			}
		}

		// Update meta tables
		$this->replace_in_serialized_table( $wpdb->postmeta, 'meta_id', 'meta_value', $map );
		$this->replace_in_serialized_table( $wpdb->options, 'option_id', 'option_value', $map );
		if ( isset( $wpdb->termmeta ) ) {
			$this->replace_in_serialized_table( $wpdb->termmeta, 'meta_id', 'meta_value', $map );
		}
		$this->replace_in_serialized_table( $wpdb->usermeta, 'umeta_id', 'meta_value', $map );

		return $total_updates;
	}

	private function replace_in_serialized_table( $table, $id_column, $value_column, $map ) {
		global $wpdb;

		foreach ( $map as $old => $new ) {
			if ( $old === $new ) {
				continue;
			}

			$like = '%' . $wpdb->esc_like( $old ) . '%';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT {$id_column} AS id, {$value_column} AS value FROM {$table} WHERE {$value_column} LIKE %s",
					$like
				)
			);

			foreach ( $rows as $row ) {
				$value   = maybe_unserialize( $row->value );
				$updated = $this->recursive_replace_map( $value, $map );

				if ( $updated !== $value ) {
					$wpdb->update(
						$table,
						array( $value_column => maybe_serialize( $updated ) ),
						array( $id_column => $row->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
		}
	}

	private function recursive_replace_map( $data, $map ) {
		if ( is_string( $data ) ) {
			return strtr( $data, $map );
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->recursive_replace_map( $value, $map );
			}
		}

		if ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->recursive_replace_map( $value, $map );
			}
		}

		return $data;
	}

	public function handle_old_urls() {
		if ( ! is_404() ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		global $wpdb;
		$requested_uri = wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! $requested_uri ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$relative   = ltrim( str_replace( trailingslashit( wp_parse_url( home_url(), PHP_URL_PATH ) ), '', $requested_uri ), '/' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT new_url FROM {$this->log_table} WHERE (old_url LIKE %s OR old_relative LIKE %s) AND status = 'complete' AND renamed_date > DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY renamed_date DESC LIMIT 1",
				'%' . $wpdb->esc_like( $relative ),
				'%' . $wpdb->esc_like( $relative )
			)
		);

		if ( $row && ! empty( $row->new_url ) ) {
			wp_redirect( $row->new_url, 301 );
			exit;
		}
	}

	public function cleanup_backup( $backup_path_or_token ) {
		// Handle tokenized paths (new system)
		if ( strlen( $backup_path_or_token ) === 32 && ctype_xdigit( $backup_path_or_token ) ) {
			// This looks like an MD5 token
			$token = sanitize_text_field( $backup_path_or_token );
			$key   = 'msh_cleanup_map_' . $token;
			$path  = get_option( $key );

			if ( $path && is_string( $path ) && file_exists( $path ) ) {
				if ( $this->is_safe_path( $path ) ) {
					wp_delete_file( $path );
				}
			}

			// Always delete the token map
			delete_option( $key );
			return;
		}

		// Legacy path handling (for backwards compatibility)
		$backup_path = $backup_path_or_token;
		$real        = realpath( $backup_path );
		if ( ! $real ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$base       = realpath( $upload_dir['basedir'] );
		if ( ! $base || strpos( $real, $base ) !== 0 ) {
			return;
		}

		if ( file_exists( $real ) && $this->is_safe_path( $real ) ) {
			wp_delete_file( $real );
		}

		$dir = dirname( $real );
		if ( is_dir( $dir ) && count( glob( $dir . '/*' ) ) === 0 ) {
			// Initialize WP_Filesystem for directory removal
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();

			if ( $wp_filesystem ) {
				$wp_filesystem->rmdir( $dir );
			}
		}
	}

	/**
	 * Manual cleanup of old backups (run this if cron fails)
	 *
	 * @return array Statistics about cleanup
	 */
	public function cleanup_old_backups() {
		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'msh-rename-backups';

		if ( ! is_dir( $backup_dir ) ) {
			return array(
				'cleaned' => 0,
				'errors'  => 0,
				'message' => 'Backup directory does not exist',
			);
		}

		$cutoff_time = time() - $this->backup_retention;
		$cleaned     = 0;
		$errors      = 0;

		$files = glob( $backup_dir . '/*' );
		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			// Extract timestamp from filename (format: filename.ext.timestamp)
			$parts     = explode( '.', basename( $file ) );
			$timestamp = (int) end( $parts );

			if ( $timestamp > 0 && $timestamp < $cutoff_time ) {
				if ( $this->is_safe_path( $file ) ) {
					$result = wp_delete_file( $file );
					if ( $result !== false ) {
						++$cleaned;
					} else {
						++$errors;
					}
				} else {
					++$errors;
				}
			}
		}

		return array(
			'cleaned' => $cleaned,
			'errors'  => $errors,
			'message' => "Cleaned {$cleaned} old backup files" . ( $errors > 0 ? " ({$errors} errors)" : '' ),
		);
	}

	/**
	 * Automatically repair corrupted attachment metadata
	 *
	 * Searches for the actual file on disk when database metadata is corrupted,
	 * then repairs the metadata to point to the correct file.
	 *
	 * @param int    $attachment_id Attachment post ID
	 * @param string $corrupted_path Path from corrupted metadata (may not exist)
	 * @return string|false Repaired file path if successful, false otherwise
	 */
	private function auto_repair_corrupted_metadata( $attachment_id, $corrupted_path ) {
		error_log( '[MSH AUTO-REPAIR] Starting auto-repair for attachment #' . $attachment_id );

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );

		// Get the directory where the file should be
		$expected_dir = dirname( $corrupted_path );
		if ( ! is_dir( $expected_dir ) ) {
			error_log( '[MSH AUTO-REPAIR] Expected directory does not exist: ' . $expected_dir );
			return false;
		}

		// Get attachment post to check filename
		$post = get_post( $attachment_id );
		if ( ! $post ) {
			error_log( '[MSH AUTO-REPAIR] Attachment post not found' );
			return false;
		}

		// Extract the corrupted filename
		$corrupted_filename = basename( $corrupted_path );
		$corrupted_base     = pathinfo( $corrupted_filename, PATHINFO_FILENAME );
		$corrupted_ext      = pathinfo( $corrupted_filename, PATHINFO_EXTENSION );

		error_log( '[MSH AUTO-REPAIR] Looking for actual file in directory: ' . $expected_dir );
		error_log( '[MSH AUTO-REPAIR] Corrupted filename: ' . $corrupted_filename );

		// Strategy 1: Try removing common prefixes (TEST-, etc.)
		$prefixes_to_try = array( '', 'TEST-', 'TEST-TEST-', 'TEST-TEST-TEST-' );
		foreach ( $prefixes_to_try as $prefix ) {
			if ( strpos( $corrupted_base, $prefix ) === 0 ) {
				$stripped_base = substr( $corrupted_base, strlen( $prefix ) );
				$candidate     = $expected_dir . '/' . $stripped_base . '.' . $corrupted_ext;

				if ( file_exists( $candidate ) ) {
					error_log( '[MSH AUTO-REPAIR] Found actual file (stripped prefix): ' . $candidate );
					return $this->repair_metadata_to_file( $attachment_id, $candidate );
				}
			}

			// Also try adding prefixes
			$candidate = $expected_dir . '/' . $prefix . $corrupted_base . '.' . $corrupted_ext;
			if ( file_exists( $candidate ) ) {
				error_log( '[MSH AUTO-REPAIR] Found actual file (added prefix): ' . $candidate );
				return $this->repair_metadata_to_file( $attachment_id, $candidate );
			}
		}

		// Strategy 2: Search for files with similar names in the same directory
		$pattern = $expected_dir . '/*.' . $corrupted_ext;
		$files   = glob( $pattern );

		if ( $files ) {
			error_log( '[MSH AUTO-REPAIR] Searching ' . count( $files ) . ' files in directory for match' );

			// Look for files with attachment ID in filename
			foreach ( $files as $file ) {
				$filename = basename( $file );

				// Check if filename contains the attachment ID
				if ( strpos( $filename, '-' . $attachment_id . '.' ) !== false ||
				     strpos( $filename, '-' . $attachment_id . '-' ) !== false ) {
					error_log( '[MSH AUTO-REPAIR] Found file with attachment ID in name: ' . $file );
					return $this->repair_metadata_to_file( $attachment_id, $file );
				}
			}

			// Look for files with similar base name (fuzzy match)
			$clean_base = preg_replace( '/^(TEST-)+/', '', $corrupted_base );
			foreach ( $files as $file ) {
				$filename  = basename( $file );
				$file_base = pathinfo( $filename, PATHINFO_FILENAME );

				if ( stripos( $file_base, $clean_base ) !== false ||
				     stripos( $clean_base, $file_base ) !== false ) {
					error_log( '[MSH AUTO-REPAIR] Found file with similar name: ' . $file );
					return $this->repair_metadata_to_file( $attachment_id, $file );
				}
			}
		}

		error_log( '[MSH AUTO-REPAIR] Could not find actual file on disk' );
		return false;
	}

	/**
	 * Repair attachment metadata to point to the correct file
	 *
	 * @param int    $attachment_id Attachment post ID
	 * @param string $actual_file   Full path to the actual file
	 * @return string Repaired file path
	 */
	private function repair_metadata_to_file( $attachment_id, $actual_file ) {
		error_log( '[MSH AUTO-REPAIR] Repairing metadata for attachment #' . $attachment_id );
		error_log( '[MSH AUTO-REPAIR] Actual file: ' . $actual_file );

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );

		// Calculate correct relative path
		$relative_path = str_replace( $base_dir, '', $actual_file );
		$relative_path = ltrim( $relative_path, '/' );

		error_log( '[MSH AUTO-REPAIR] Updating _wp_attached_file to: ' . $relative_path );

		// Update the attached file metadata
		if ( function_exists( 'msh_update_attached_file_collapsed' ) ) {
			$guard = msh_update_attached_file_collapsed( $attachment_id, $relative_path );
			if ( is_wp_error( $guard ) ) {
				error_log( '[MSH AUTO-REPAIR] Failed to update _wp_attached_file: ' . $guard->get_error_message() );
			}
		} else {
			update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );
		}

		// Get existing metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}

		// Update file path in metadata
		$metadata['file'] = $relative_path;

		// Check if thumbnails exist and fix their metadata
		$dir           = dirname( $actual_file );
		$actual_base   = pathinfo( $actual_file, PATHINFO_FILENAME );
		$actual_ext    = pathinfo( $actual_file, PATHINFO_EXTENSION );
		$thumbnails_ok = true;

		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size => $size_data ) {
				// Check if thumbnail file is corrupted (no size suffix)
				if ( ! empty( $size_data['file'] ) &&
				     ! preg_match( '/-\d+x\d+\./', $size_data['file'] ) ) {

					$thumbnails_ok = false;
					error_log( '[MSH AUTO-REPAIR] Detected corrupted thumbnail metadata for size: ' . $size );

					// Try to find actual thumbnail file
					$expected_thumb = $dir . '/' . $actual_base . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $actual_ext;

					if ( file_exists( $expected_thumb ) ) {
						$metadata['sizes'][ $size ]['file'] = basename( $expected_thumb );
						error_log( '[MSH AUTO-REPAIR] Repaired thumbnail path: ' . basename( $expected_thumb ) );
					} else {
						error_log( '[MSH AUTO-REPAIR] Thumbnail file not found: ' . $expected_thumb );
					}
				}
			}
		}

		// If thumbnails are missing or corrupted, regenerate them
		if ( ! $thumbnails_ok ) {
			error_log( '[MSH AUTO-REPAIR] Regenerating thumbnails for attachment #' . $attachment_id );

			if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
				$metadata = wp_generate_attachment_metadata( $attachment_id, $actual_file );
				error_log( '[MSH AUTO-REPAIR] Thumbnails regenerated successfully' );
			}
		}

		// Save updated metadata
		wp_update_attachment_metadata( $attachment_id, $metadata );

		error_log( '[MSH AUTO-REPAIR] Metadata repair complete' );

		return $actual_file;
	}
}
