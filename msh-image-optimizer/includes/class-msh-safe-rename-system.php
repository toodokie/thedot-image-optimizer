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
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
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
		$this->test_mode         = (bool) $test_mode;
		$this->last_replacements = 0;
		if ( ! $this->test_mode ) {
			$this->clear_usage_lookup_cache();
		}

		$current_path = get_attached_file( $attachment_id );
		if ( ! $current_path || ! file_exists( $current_path ) ) {
			return new WP_Error( 'missing_file', __( 'Original file not found for attachment.', 'msh-image-optimizer' ) );
		}

		$new_filename     = sanitize_file_name( $new_filename );
		$current_basename = basename( $current_path );
		if ( $new_filename === '' || strcasecmp( $current_basename, $new_filename ) === 0 ) {
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

		$new_filename = $this->ensure_unique_filename( $new_filename, dirname( $current_path ) );
		$new_relative = str_replace( basename( $old_relative ), $new_filename, $old_relative );
		$new_url      = trailingslashit( $upload_dir['baseurl'] ) . ltrim( $new_relative, '/' );

		$log_id = $this->log_intent( $attachment_id, $current_basename, $new_filename, $old_url, $new_url, $old_relative, $new_relative );

		$old_metadata = wp_get_attachment_metadata( $attachment_id );

		if ( $this->test_mode ) {
			$map      = $this->build_search_replace_map( $old_url, $new_url, $old_metadata, $upload_dir );
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

		$rename = $this->rename_physical_files( $current_path, $new_filename, $old_metadata );
		if ( is_wp_error( $rename ) ) {
			$this->update_log( $log_id, 'failed', 0, $rename->get_error_message() );
			return $rename;
		}

		$this->update_wordpress_metadata( $attachment_id, $rename['new_path'], $old_metadata, $new_relative );

		$map      = $this->build_search_replace_map( $old_url, $new_url, $old_metadata, $upload_dir );
		$replaced = $this->replace_references( $map, $attachment_id, $current_basename, $new_filename );

		if ( is_wp_error( $replaced ) ) {
			$this->restore_failed_rename( $attachment_id, $current_path, $rename, $old_metadata, $old_relative, $old_url );
			$this->update_log( $log_id, 'failed', 0, $replaced->get_error_message() );
			return $replaced;
		}

		$this->last_replacements = $replaced;
		$this->update_log( $log_id, 'complete', $replaced, null );
		if ( ! $this->test_mode ) {
			$this->clear_usage_lookup_cache();
		}

		return array(
			'old_url'  => $old_url,
			'new_url'  => $new_url,
			'replaced' => $replaced,
			'backup'   => $rename['backup_path'],
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
			update_post_meta( $attachment_id, '_wp_attached_file', $old_relative );
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

		if ( ! is_writable( $dir ) ) {
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

		// Handle sized images (thumbnails) - WITHOUT error suppression
		if ( is_array( $old_metadata ) && ! empty( $old_metadata['sizes'] ) ) {
			foreach ( $old_metadata['sizes'] as $size => $data ) {
				if ( empty( $data['file'] ) ) {
					continue;
				}

				$old_size_path = trailingslashit( $dir ) . $data['file'];
				if ( ! file_exists( $old_size_path ) ) {
					continue;
				}

				$ext               = pathinfo( $data['file'], PATHINFO_EXTENSION );
				$new_size_filename = pathinfo( $new_filename, PATHINFO_FILENAME ) . '-' .
									$data['width'] . 'x' . $data['height'] . '.' . $ext;
				$new_size_path     = trailingslashit( $dir ) . $new_size_filename;

				// Backup thumbnail
				$size_backup = $backup_dir . '/' . basename( $old_size_path ) . '.' . time();
				copy( $old_size_path, $size_backup );

				// Rename thumbnail using WP_Filesystem
				if ( $this->init_filesystem() ) {
					global $wp_filesystem;
					$thumb_result = $wp_filesystem->move( $old_size_path, $new_size_path, true );

					if ( ! $thumb_result ) {
						// Try copy + delete fallback
						if ( copy( $old_size_path, $new_size_path ) && $this->is_safe_path( $old_size_path ) ) {
							wp_delete_file( $old_size_path );
							error_log( 'MSH Rename: Thumbnail renamed via copy+delete: ' . basename( $old_size_path ) );
						} else {
							error_log( 'MSH Rename: Failed to rename thumbnail ' . basename( $old_size_path ) );
						}
					} else {
						error_log( 'MSH Rename: Thumbnail renamed successfully: ' . basename( $old_size_path ) );
					}
				}
			}
		}

		// Schedule cleanup of backups (suppress errors to prevent log spam)
		$scheduled = @wp_schedule_single_event( time() + $this->backup_retention, 'msh_cleanup_rename_backup', array( $backup_path ) );
		if ( is_wp_error( $scheduled ) ) {
			// Silently fail if cron scheduling fails - backups will be cleaned manually
			error_log( 'MSH Rename: Could not schedule backup cleanup for ' . basename( $backup_path ) . ' (cron system issue)' );
		}

		return array(
			'new_path'    => $new_path,
			'backup_path' => $backup_path,
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

			// Try to set proper permissions
			if ( is_dir( $dir ) ) {
				chmod( $dir, 0755 );
				error_log( 'MSH Rename: Set directory permissions 0755 for ' . $dir );
			}

			if ( file_exists( $file_path ) && ! is_dir( $file_path ) ) {
				chmod( $file_path, 0644 );
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

	private function ensure_unique_filename( $filename, $directory ) {
		$directory = trailingslashit( $directory );
		$pathinfo  = pathinfo( $filename );
		$name      = $pathinfo['filename'];
		$ext       = isset( $pathinfo['extension'] ) && $pathinfo['extension'] !== '' ? '.' . $pathinfo['extension'] : '';
		$candidate = $filename;
		$counter   = 1;

		while ( file_exists( $directory . $candidate ) ) {
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
			// Schedule cleanup (suppress errors to prevent log spam)
			@wp_schedule_single_event( time() + $this->backup_retention, 'msh_cleanup_rename_backup', array( $backup_path ) );
			return $backup_path;
		}

		return null;
	}

	private function update_wordpress_metadata( $attachment_id, $new_path, $old_metadata, $new_relative ) {
		update_attached_file( $attachment_id, $new_path );

		if ( is_array( $old_metadata ) ) {
			$metadata         = $old_metadata;
			$metadata['file'] = $new_relative;

			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size => $data ) {
					$ext                                = pathinfo( $data['file'], PATHINFO_EXTENSION );
					$metadata['sizes'][ $size ]['file'] = pathinfo( $new_relative, PATHINFO_FILENAME ) . '-' . $data['width'] . 'x' . $data['height'] . '.' . $ext;
				}
			}

			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( $mime && strpos( $mime, 'image/' ) === 0 ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$regen = wp_generate_attachment_metadata( $attachment_id, $new_path );
			if ( ! is_wp_error( $regen ) && ! empty( $regen ) ) {
				$regen['file'] = $new_relative;
				wp_update_attachment_metadata( $attachment_id, $regen );
			}
		}

		$new_slug = sanitize_title( pathinfo( $new_relative, PATHINFO_FILENAME ) );
		wp_update_post(
			array(
				'ID'        => $attachment_id,
				'post_name' => $new_slug,
			)
		);
	}

	private function build_search_replace_map( $old_url, $new_url, $old_metadata, $upload_dir ) {
		$map             = array();
		$map[ $old_url ] = $new_url;

		$old_relative         = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $old_url );
		$new_relative         = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $new_url );
		$map[ $old_relative ] = $new_relative;

		$map[ basename( $old_url ) ] = basename( $new_url );

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
		$requested_uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( ! $requested_uri ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$relative   = ltrim( str_replace( trailingslashit( parse_url( home_url(), PHP_URL_PATH ) ), '', $requested_uri ), '/' );

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

	public function cleanup_backup( $backup_path ) {
		$real = realpath( $backup_path );
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
			@rmdir( $dir );
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
}
