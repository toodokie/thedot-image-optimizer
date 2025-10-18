<?php
/**
 * MSH Media Cleanup Tool
 * Organizes and cleans up duplicate/unused images in media library
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Media_Cleanup {
	private static $instance     = null;
	const VISUAL_SCAN_BATCH_SIZE = 100;
	const VISUAL_SCAN_RESULT_TTL = DAY_IN_SECONDS;
	const VISUAL_SCAN_STATE_TTL  = 30 * MINUTE_IN_SECONDS;

	/**
	 * @var MSH_Hash_Cache_Manager|null
	 */
	private $hash_manager;

	/**
	 * @var MSH_Perceptual_Hash|null
	 */
	private $perceptual_manager;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = $this;

		add_action( 'wp_ajax_msh_analyze_duplicates', array( $this, 'ajax_analyze_duplicates' ) );
		add_action( 'wp_ajax_msh_cleanup_media', array( $this, 'ajax_cleanup_media' ) );
		add_action( 'wp_ajax_msh_test_cleanup', array( $this, 'ajax_test_cleanup' ) );
		add_action( 'wp_ajax_msh_scan_full_library', array( $this, 'ajax_scan_full_library' ) );
		add_action( 'wp_ajax_msh_quick_duplicate_scan', array( $this, 'ajax_quick_duplicate_scan' ) );
		add_action( 'wp_ajax_msh_deep_library_scan', array( $this, 'ajax_deep_library_scan' ) );
		add_action( 'wp_ajax_msh_check_duplicate_usage', array( $this, 'ajax_check_duplicate_usage' ) );
		add_action( 'wp_ajax_msh_prepare_hash_cache', array( $this, 'ajax_prepare_hash_cache' ) );
		add_action( 'wp_ajax_msh_hash_duplicate_scan', array( $this, 'ajax_hash_duplicate_scan' ) );
		add_action( 'wp_ajax_msh_visual_similarity_scan_start', array( $this, 'ajax_visual_similarity_scan_start' ) );
		add_action( 'wp_ajax_msh_visual_similarity_scan_batch', array( $this, 'ajax_visual_similarity_scan_batch' ) );
		add_action( 'wp_ajax_msh_visual_similarity_scan_status', array( $this, 'ajax_visual_similarity_scan_status' ) );
		add_action( 'wp_ajax_msh_visual_similarity_scan_results', array( $this, 'ajax_visual_similarity_scan_results' ) );

		if ( ! class_exists( 'MSH_Hash_Cache_Manager' ) ) {
			$hash_manager_path = __DIR__ . '/class-msh-hash-cache-manager.php';
			if ( file_exists( $hash_manager_path ) ) {
				require_once $hash_manager_path;
			}
		}

		if ( ! class_exists( 'MSH_Perceptual_Hash' ) ) {
			$perceptual_hash_path = __DIR__ . '/class-msh-perceptual-hash.php';
			if ( file_exists( $perceptual_hash_path ) ) {
				require_once $perceptual_hash_path;
			}
		}

		if ( class_exists( 'MSH_Hash_Cache_Manager' ) ) {
			$this->hash_manager = new MSH_Hash_Cache_Manager();
		}

		if ( class_exists( 'MSH_Perceptual_Hash' ) ) {
			$this->perceptual_manager = MSH_Perceptual_Hash::get_instance();
		}
	}

	/**
	 * Retrieve the shared hash manager instance.
	 *
	 * @return MSH_Hash_Cache_Manager|null
	 */
	private function get_hash_manager() {
		if ( ! $this->hash_manager && class_exists( 'MSH_Hash_Cache_Manager' ) ) {
			$this->hash_manager = new MSH_Hash_Cache_Manager();
		}

		return $this->hash_manager;
	}

	/**
	 * Retrieve the perceptual hash manager.
	 *
	 * @return MSH_Perceptual_Hash|null
	 */
	private function get_perceptual_manager() {
		if ( ! $this->perceptual_manager && class_exists( 'MSH_Perceptual_Hash' ) ) {
			$this->perceptual_manager = MSH_Perceptual_Hash::get_instance();
		}

		return $this->perceptual_manager;
	}

	/**
	 * Test AJAX handler to verify the class is working
	 */
	public function ajax_test_cleanup() {
		check_ajax_referer( 'msh_media_cleanup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		wp_send_json_success(
			array(
				'message'   => 'Media cleanup class is working!',
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Find duplicate images and group them (optimized for large libraries)
	 */
	public function find_duplicate_groups( $limit = 100 ) {
		global $wpdb;

		// Simple, fast query - just get recent images first
		$images = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT 
                p.ID,
                p.post_title,
                p.post_date,
                pm.meta_value as file_path,
                p.post_mime_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%%'
            AND p.post_mime_type != 'image/svg+xml'
            AND pm.meta_value IS NOT NULL
            AND pm.meta_value != ''
            ORDER BY p.post_date DESC
            LIMIT %d
        ",
				$limit
			),
			ARRAY_A
		);

		$groups     = array();
		$base_names = array();

		foreach ( $images as $image ) {
			// Extract base name (without size suffixes)
			$file_path = $image['file_path'];
			if ( empty( $file_path ) ) {
				continue;
			}

			$base_name = $this->get_base_filename( $file_path );

			if ( ! isset( $base_names[ $base_name ] ) ) {
				$base_names[ $base_name ] = array();
			}

			$base_names[ $base_name ][] = $image;
		}

		// Filter to only groups with multiple images
		foreach ( $base_names as $base_name => $group ) {
			if ( count( $group ) > 1 ) {
				$groups[ $base_name ] = $this->analyze_group( $group );
			}
		}

		return $groups;
	}

	/**
	 * Retrieve scan progress transient key for the current user.
	 *
	 * @return string
	 */
	private function get_progress_transient_key() {
		$user_id = get_current_user_id();
		$suffix  = $user_id ? $user_id : 'guest';
		return 'msh_scan_progress_' . $suffix;
	}

	/**
	 * Get user-scoped key for visual scan state.
	 *
	 * @return string
	 */
	private function get_visual_scan_state_key() {
		$user_id = get_current_user_id();
		$suffix  = $user_id ? $user_id : 'guest';
		return 'msh_visual_scan_state_' . $suffix;
	}

	/**
	 * Get user-scoped key for visual scan progress snapshot.
	 *
	 * @return string
	 */
	private function get_visual_scan_progress_key() {
		$user_id = get_current_user_id();
		$suffix  = $user_id ? $user_id : 'guest';
		return 'msh_visual_scan_progress_' . $suffix;
	}

	/**
	 * Get user-scoped key for cached visual scan results.
	 *
	 * @return string
	 */
	private function get_visual_scan_results_key() {
		$user_id = get_current_user_id();
		$suffix  = $user_id ? $user_id : 'guest';
		return 'msh_visual_scan_results_' . $suffix;
	}

	/**
	 * Retrieve current visual scan state payload.
	 *
	 * @return array|null
	 */
	private function get_visual_scan_state() {
		$state = get_transient( $this->get_visual_scan_state_key() );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Persist visual scan state for the current user.
	 *
	 * @param array $state
	 */
	private function set_visual_scan_state( array $state ) {
		set_transient( $this->get_visual_scan_state_key(), $state, self::VISUAL_SCAN_STATE_TTL );
	}

	/**
	 * Clear visual scan state, progress, and cached results.
	 */
	private function reset_visual_scan_state() {
		delete_transient( $this->get_visual_scan_state_key() );
		delete_transient( $this->get_visual_scan_progress_key() );
		delete_transient( $this->get_visual_scan_results_key() );
	}

	/**
	 * Get the list of images to scan for content duplicates.
	 *
	 * @param int|null $limit Optional limit for query size.
	 * @return array
	 */
	private function get_images_for_scanning( $limit = null ) {
		global $wpdb;

		$sql = "
            SELECT
                p.ID,
                p.post_title,
                p.post_date,
                p.post_status,
                pm.meta_value AS file_path
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
            WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value <> ''
            ORDER BY p.ID ASC
        ";

		if ( null !== $limit && is_numeric( $limit ) ) {
			$sql .= ' LIMIT ' . intval( $limit );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get all image attachment IDs for visual similarity processing.
	 *
	 * @return int[]
	 */
	private function get_visual_scan_attachment_ids() {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE 'image/%%' AND post_status != %s ORDER BY ID ASC",
				'attachment',
				'trash'
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Run a content-based duplicate scan with transient progress updates.
	 *
	 * @param int|null $limit Optional limit for how many images to process.
	 * @return array
	 */
	public function find_content_duplicates( $limit = null ) {
		$hash_manager = $this->get_hash_manager();

		if ( ! $hash_manager ) {
			return array(
				'groups'           => array(),
				'total_groups'     => 0,
				'total_duplicates' => 0,
				'hash_map'         => array(),
				'error'            => 'Hash manager unavailable',
			);
		}

		$images = $this->get_images_for_scanning( $limit );
		if ( empty( $images ) ) {
			return array(
				'groups'           => array(),
				'total_groups'     => 0,
				'total_duplicates' => 0,
				'hash_map'         => array(),
				'error'            => 'No images found',
			);
		}

		$progress_key = $this->get_progress_transient_key();
		delete_transient( $progress_key );

		$total     = count( $images );
		$processed = 0;
		$hash_map  = array();

		set_transient(
			$progress_key,
			array(
				'status'  => 'processing',
				'current' => 0,
				'total'   => $total,
				'message' => __( 'Starting content scan...', 'msh-image-optimizer' ),
			),
			300
		);

		foreach ( $images as $image ) {
			++$processed;

			$hash = $hash_manager->get_file_hash( $image->ID );
			if ( ! $hash ) {
				continue;
			}

			if ( ! isset( $hash_map[ $hash ] ) ) {
				$hash_map[ $hash ] = array();
			}

			$thumb_url           = wp_get_attachment_thumb_url( $image->ID );
			$hash_map[ $hash ][] = array(
				'id'        => $image->ID,
				'title'     => $image->post_title,
				'date'      => $image->post_date,
				'status'    => $image->post_status,
				'file'      => $image->file_path,
				'thumb_url' => $thumb_url ? $thumb_url : '',
				'full_url'  => wp_get_attachment_url( $image->ID ),
			);

			if ( $processed % 10 === 0 || $processed === $total ) {
				set_transient(
					$progress_key,
					array(
						'status'  => 'processing',
						'current' => $processed,
						'total'   => $total,
						'message' => sprintf( __( 'Processing image %1$d of %2$d...', 'msh-image-optimizer' ), $processed, $total ),
					),
					300
				);
			}
		}

		$duplicates = $this->process_hash_map( $hash_map );

		delete_transient( $progress_key );

		return $duplicates;
	}

	/**
	 * Convert a hash map into duplicate group metadata.
	 *
	 * @param array $hash_map
	 * @return array
	 */
	private function process_hash_map( array $hash_map ) {
		$groups           = array();
		$total_duplicates = 0;

		foreach ( $hash_map as $hash => $items ) {
			if ( count( $items ) < 2 ) {
				continue;
			}

			$groups[] = array(
				'hash'   => $hash,
				'count'  => count( $items ),
				'images' => $items,
			);

			$total_duplicates += count( $items ) - 1;
		}

		return array(
			'groups'           => $groups,
			'total_groups'     => count( $groups ),
			'total_duplicates' => $total_duplicates,
			'hash_map'         => $hash_map,
		);
	}

	/**
	 * Get base filename without size suffixes - ENHANCED VERSION
	 */
	private function get_base_filename( $file_path ) {
		return self::normalize_base_filename( $file_path );
	}

	/**
	 * Analyze a filename and return normalization metadata.
	 *
	 * @param string $file_path
	 * @return array{base_name:string, original_base:string, patterns:array<int, string>}
	 */
	public static function analyze_filename( $file_path ) {
		$filename      = basename( $file_path );
		$original_base = pathinfo( $filename, PATHINFO_FILENAME );
		$normalized    = $original_base;

		$pattern_map = array(
			'scaled_chain' => '/-scaled(?:-(?:\d+|\d+x\d+))*$/i',
			'dimensions'   => '/-\d+x\d+$/i',
			'retina'       => '/@\d+x$/i',
			'timestamp'    => '/-e\d{10}$/i',
			'paren_number' => '/\(\d+\)$/',
			'paren_copy'   => '/\(copy\)$/i',
			'thumbnail'    => '/(_thumb|_thumbnail|_tn)$/i',
			'version'      => '/([-_]v\d+|[-_]final|[-_]new)$/i',
			'copy'         => '/([-_]copy\d*|[-_]duplicate)$/i',
			// REMOVED: 'numeric_suffix' => '/-\d+$/',
			// Reason: This pattern was causing FALSE POSITIVES by grouping
			// completely different files like doctor-1.jpg, doctor-2.jpg, doctor-3.jpg
			// as if they were duplicates. Sequential numbers are MEANINGFUL and should
			// be preserved to differentiate unique files.
		);

		$matched         = array();
		$previous        = null;
		$last_meaningful = $normalized;

		while ( $previous !== $normalized ) {
			$previous = $normalized;

			foreach ( $pattern_map as $key => $pattern ) {
				if ( ! preg_match( $pattern, $normalized ) ) {
					continue;
				}

				$stripped = preg_replace( $pattern, '', $normalized );
				$trimmed  = trim( $stripped, " \t\n\r\0\x0B-_" );

				// Skip if change would collapse to a placeholder (e.g., Figma exports like "Group")
				if (
					$trimmed === ''
					|| ( self::is_placeholder_name( $trimmed ) && ! self::is_placeholder_name( $normalized ) )
				) {
					continue;
				}

				if ( $trimmed === $normalized ) {
					continue;
				}

				$normalized      = $trimmed;
				$matched[ $key ] = true;

				if ( ! self::is_placeholder_name( $normalized ) ) {
					$last_meaningful = $normalized;
				}
			}

			if ( $normalized === $previous ) {
				break;
			}
		}

		if ( $normalized === '' || self::is_placeholder_name( $normalized ) ) {
			$normalized = $last_meaningful ?: $original_base;
		}

		return array(
			'base_name'     => $normalized,
			'original_base' => $original_base,
			'patterns'      => array_keys( array_filter( $matched ) ),
		);
	}

	/**
	 * Get normalized base filename.
	 */
	public static function normalize_base_filename( $file_path ) {
		$analysis = self::analyze_filename( $file_path );
		return $analysis['base_name'];
	}

	/**
	 * Detect generic placeholder names that shouldn't be used for grouping.
	 */
	private static function is_placeholder_name( $name ) {
		if ( ! is_string( $name ) ) {
			return true;
		}

		$normalized = strtolower( trim( $name ) );

		if ( $normalized === '' ) {
			return true;
		}

		if ( strlen( $normalized ) <= 2 ) {
			return true;
		}

		$placeholders = array(
			'group',
			'layer',
			'frame',
			'vector',
			'asset',
			'untitled',
			'screenshot',
		);

		return in_array( $normalized, $placeholders, true );
	}

	/**
	 * Analyze a group of duplicate images (lightweight version)
	 */
	private function analyze_group( $group ) {
		$keep_candidate  = null;
		$keep_score      = 0;
		$published_count = 0;

		foreach ( $group as &$image ) {
			// Quick usage check - just check if it's used somewhere
			$image['is_published'] = $this->quick_usage_check( $image['ID'] );
			if ( $image['is_published'] ) {
				++$published_count;
			}

			// Calculate "keep" score (ENHANCED FOR HEALTHCARE)
			$score = 0;

			// Core safety factors
			if ( $image['is_published'] ) {
				$score += 15;  // Increased weight for published
			}

			// File quality indicators
			if ( strpos( $image['file_path'], '-scaled' ) === false ) {
				$score += 5;
			}
			if ( ! preg_match( '/-\d+x\d+/', $image['file_path'] ) ) {
				$score += 8;
			}

			// Healthcare-specific naming (Hamilton clinic)
			$filename = strtolower( $image['file_path'] );
			if ( strpos( $filename, 'hamilton' ) !== false ) {
				$score += 8;
			}
			if ( strpos( $filename, 'msh' ) !== false ) {
				$score += 6;
			}
			if ( strpos( $filename, 'main-street' ) !== false ) {
				$score += 6;
			}

			// Medical/professional naming
			if ( preg_match( '/(therapy|treatment|rehab|chiro|physio|medical|health|care)/', $filename ) ) {
				$score += 4;
			}

			// Negative factors (avoid keeping these)
			if ( strpos( $image['post_title'], 'copy' ) !== false ) {
				$score -= 5;
			}
			if ( strpos( $filename, 'temp' ) !== false ) {
				$score -= 8;
			}
			if ( strpos( $filename, 'test' ) !== false ) {
				$score -= 3;
			}
			if ( strpos( $filename, 'old' ) !== false ) {
				$score -= 3;
			}
			if ( preg_match( '/untitled|image\d+|img_\d+/', $filename ) ) {
				$score -= 2;
			}

			// Format preferences
			if ( strpos( $image['file_path'], '.webp' ) !== false ) {
				$score += 10;  // Prefer WebP
			}
			if ( strpos( $image['file_path'], '.svg' ) !== false ) {
				$score += 7;   // SVG for icons
			}
			if ( strpos( $image['file_path'], '.jpg' ) !== false ) {
				$score += 3;   // JPG standard
			}

			$image['keep_score'] = $score;
			$image['usage']      = array(); // Populate later if needed

			if ( $score > $keep_score ) {
				$keep_score     = $score;
				$keep_candidate = $image;
			}
		}

		return array(
			'images'            => $group,
			'recommended_keep'  => $keep_candidate,
			'total_count'       => count( $group ),
			'published_count'   => $published_count,
			'sizes_available'   => array( 'Multiple sizes' ), // Simplified
			'cleanup_potential' => count( $group ) - 1,
		);
	}

	/**
	 * Improved usage check - check both featured images and content usage
	 */
	private function quick_usage_check( $attachment_id ) {
		global $wpdb;

		// Check featured images
		$featured = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} meta
            JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id
            WHERE meta.meta_key = '_thumbnail_id' 
            AND meta.meta_value = %d
            AND posts.post_status = 'publish'
        ",
				$attachment_id
			)
		);

		if ( $featured > 0 ) {
			return true;
		}

		// Check content usage (quick check)
		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $file_path ) {
			$filename      = basename( $file_path );
			$content_usage = $wpdb->get_var(
				$wpdb->prepare(
					"
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE %s 
                AND post_status = 'publish'
                LIMIT 1
            ",
					'%' . $filename . '%'
				)
			);

			if ( $content_usage > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check where an image is being used
	 */
	private function check_image_usage( $attachment_id ) {
		global $wpdb;

		$usage = array();

		// Check featured images
		$featured = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT posts.post_title, posts.post_type, posts.post_status
            FROM {$wpdb->postmeta} meta 
            JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id 
            WHERE meta.meta_key = '_thumbnail_id' 
            AND meta.meta_value = %d
        ",
				$attachment_id
			)
		);

		foreach ( $featured as $post ) {
			$usage[] = array(
				'type'      => 'featured_image',
				'title'     => $post->post_title,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
			);
		}

		// Check content usage
		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $file_path ) {
			$filename      = basename( $file_path );
			$content_posts = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT post_title, post_type, post_status 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE %s
            ",
					'%' . $filename . '%'
				)
			);

			foreach ( $content_posts as $post ) {
				$usage[] = array(
					'type'      => 'content',
					'title'     => $post->post_title,
					'post_type' => $post->post_type,
					'status'    => $post->post_status,
				);
			}
		}

		return $usage;
	}

	/**
	 * AJAX: Analyze duplicates
	 */
	public function ajax_analyze_duplicates() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( 'Unauthorized' );
			}

			// Add some debug info
			error_log( 'MSH: Starting duplicate analysis...' );

			// Start with very small batch for reliability
			$limit            = 50; // Process first 50 images only
			$duplicate_groups = $this->find_duplicate_groups( $limit );

			error_log( 'MSH: Found ' . count( $duplicate_groups ) . ' duplicate groups from ' . $limit . ' images' );

			$summary = array(
				'total_groups'     => count( $duplicate_groups ),
				'total_duplicates' => array_sum(
					array_map(
						function ( $group ) {
							return $group['cleanup_potential'];
						},
						$duplicate_groups
					)
				),
				'groups'           => $duplicate_groups,
				'debug_info'       => array(
					'memory_usage' => memory_get_usage( true ),
					'time'         => current_time( 'mysql' ),
				),
			);

			wp_send_json_success( $summary );

		} catch ( Exception $e ) {
			error_log( 'MSH Cleanup Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Analysis failed: ' . $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
				)
			);
		}
	}

	/**
	 * AJAX: Progressive full library scan
	 */
	public function ajax_scan_full_library() {
		try {
			// Add error reporting for debugging
			error_reporting( E_ALL );
			ini_set( 'display_errors', 0 ); // Don't display errors in response

			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$offset     = intval( $_POST['offset'] ?? 0 );
			$batch_size = 10; // Process only 10 images per batch to avoid timeouts

			// Clear transient data on first batch (offset 0)
			if ( $offset === 0 ) {
				delete_transient( 'msh_deep_scan_data' );
			}

			error_log( "MSH: Full scan batch starting at offset {$offset}" );

			// Use simple, fast queries
			global $wpdb;

			// Simple count query - include all images including SVG
			$total_images = $wpdb->get_var(
				"
                SELECT COUNT(*) 
                FROM {$wpdb->posts} 
                WHERE post_type = 'attachment'
                AND post_mime_type LIKE 'image/%'
            "
			);

			if ( ! $total_images ) {
				wp_send_json_error( array( 'message' => 'No images found in database' ) );
				return;
			}

			// Get this batch of images with simple query - include all images
			$images = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT 
                    ID,
                    post_title,
                    post_date,
                    post_mime_type
                FROM {$wpdb->posts}
                WHERE post_type = 'attachment'
                AND post_mime_type LIKE 'image/%%'
                ORDER BY post_date DESC
                LIMIT %d OFFSET %d
            ",
					$batch_size,
					$offset
				),
				ARRAY_A
			);

			if ( empty( $images ) ) {
				// No more images to process - get final results
				$stored_images = get_transient( 'msh_deep_scan_data' ) ?: array();
				$groups        = $this->process_all_collected_images( $stored_images );
				delete_transient( 'msh_deep_scan_data' );

				wp_send_json_success(
					array(
						'completed'        => true,
						'total_processed'  => $offset,
						'message'          => 'Deep library scan completed',
						'groups'           => $groups,
						'total_groups'     => count( $groups ),
						'total_duplicates' => array_sum( array_column( $groups, 'cleanup_potential' ) ),
					)
				);
				return;
			}

			// For deep scan, collect ALL file paths first, then group them at the end
			// This is more memory intensive but ensures proper cross-batch grouping

			// Store batch data in transient for aggregation across batches
			$transient_key = 'msh_deep_scan_data';
			$stored_images = get_transient( $transient_key ) ?: array();

			// Add current batch images to stored data
			foreach ( $images as $image ) {
				$file_path = get_post_meta( $image['ID'], '_wp_attached_file', true );
				if ( ! empty( $file_path ) ) {
					$image['file_path'] = $file_path;
					$stored_images[]    = $image;
				}
			}

			// Store updated data
			set_transient( $transient_key, $stored_images, HOUR_IN_SECONDS );

			// Check if this is the last batch
			$processed = $offset + count( $images );
			if ( $processed >= $total_images ) {
				// Final processing - increase timeout and process collected images
				@set_time_limit( 60 ); // 60 seconds for final processing
				@ini_set( 'memory_limit', '256M' ); // Increase memory limit

				error_log( 'MSH: Starting final processing of ' . count( $stored_images ) . ' collected images' );

				try {
					$groups = $this->process_all_collected_images( $stored_images );
					delete_transient( $transient_key ); // Clean up

					error_log( 'MSH: Final processing complete - found ' . count( $groups ) . ' duplicate groups' );

					// Return completion with final results
					wp_send_json_success(
						array(
							'completed'        => true,
							'groups'           => $groups,
							'total_images'     => $total_images,
							'processed'        => $processed,
							'progress_percent' => 100,
							'total_groups'     => count( $groups ),
							'total_duplicates' => array_sum( array_column( $groups, 'cleanup_potential' ) ),
							'debug_info'       => array(
								'final_processing'      => true,
								'total_images_analyzed' => count( $stored_images ),
								'memory_peak'           => memory_get_peak_usage( true ) / 1024 / 1024 . 'MB',
							),
						)
					);
					return;
				} catch ( Exception $e ) {
					error_log( 'MSH: Final processing error: ' . $e->getMessage() );
					delete_transient( $transient_key );
					wp_send_json_error( array( 'message' => 'Final processing failed: ' . $e->getMessage() ) );
					return;
				}
			} else {
				// Intermediate batch - just return progress
				$groups = array();
			}

			$progress_percent = round( ( $processed / $total_images ) * 100, 1 );

			wp_send_json_success(
				array(
					'completed'        => false,
					'groups'           => $groups,
					'total_images'     => $total_images,
					'processed'        => $processed,
					'progress_percent' => $progress_percent,
					'duplicates_found' => array_sum(
						array_map(
							function ( $group ) {
								return $group['cleanup_potential'];
							},
							$groups
						)
					),
					'next_offset'      => $processed,
					'debug_info'       => array(
						'batch_image_count' => count( $images ),
						'groups_found'      => count( $groups ),
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'MSH Full Scan Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Full scan failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Simplified Deep Library Scan - no batching, just get ALL images and process them
	 */
	public function ajax_deep_library_scan() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			@set_time_limit( 120 ); // 2 minutes
			@ini_set( 'memory_limit', '512M' ); // Increase memory

			global $wpdb;

			// Get ALL images at once - no batching
			$all_images = $wpdb->get_results(
				"
                SELECT 
                    p.ID,
                    p.post_title,
                    p.post_date,
                    pm.meta_value as file_path
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value != ''
                ORDER BY p.post_date DESC
            ",
				ARRAY_A
			);

			if ( empty( $all_images ) ) {
				wp_send_json_error( array( 'message' => 'No images found' ) );
				return;
			}

			// Process exactly like Quick scan but with all images
			$groups = array();
			foreach ( $all_images as $file ) {
				$base_name = $this->get_base_filename( $file['file_path'] );

				if ( ! isset( $groups[ $base_name ] ) ) {
					$groups[ $base_name ] = array(
						'images'            => array(),
						'total_count'       => 0,
						'cleanup_potential' => 0,
						'published_count'   => 0,
						'sizes_available'   => array(),
					);
				}

				// No usage check for speed - just mark as potential duplicate
				$file['is_published'] = false;
				$file['usage']        = array();
				$file['keep_score']   = 1;

				$groups[ $base_name ]['images'][] = $file;
				++$groups[ $base_name ]['total_count'];
				++$groups[ $base_name ]['cleanup_potential']; // All are potential cleanup candidates
			}

			// Filter to only groups with multiple images and set simple recommended keep
			$filtered_groups = array();
			foreach ( $groups as $base_name => $group ) {
				if ( $group['total_count'] > 1 ) {
					// Keep the first one found (simple logic for speed)
					$group['recommended_keep']  = $group['images'][0];
					$group['published_count']   = 0; // Skip expensive checks
					$group['sizes_available']   = array( 'Multiple sizes' );
					$group['cleanup_potential'] = $group['total_count'] - 1; // All but first

					$filtered_groups[ $base_name ] = $group;
				}
			}

			wp_send_json_success(
				array(
					'total_groups'     => count( $filtered_groups ),
					'total_duplicates' => array_sum( array_column( $filtered_groups, 'cleanup_potential' ) ),
					'groups'           => $filtered_groups,
					'debug_info'       => array(
						'total_scanned'    => count( $all_images ),
						'all_groups_found' => count( $groups ),
						'memory_usage'     => memory_get_usage(),
						'scan_type'        => 'deep_simplified',
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'MSH Deep Scan Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Deep scan failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Check usage status for duplicate images
	 */
	public function ajax_check_duplicate_usage() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$image_ids = $_POST['image_ids'] ?? array();
			if ( empty( $image_ids ) || ! is_array( $image_ids ) ) {
				wp_send_json_error( array( 'message' => 'No image IDs provided' ) );
				return;
			}

			@set_time_limit( 90 ); // 90 seconds for usage checking
			$start_time = microtime( true );

			global $wpdb;

			$usage_details  = array();
			$used_count     = 0;
			$safe_to_delete = 0;

			foreach ( $image_ids as $image_id ) {
				$image_id = intval( $image_id );
				if ( $image_id <= 0 ) {
					continue;
				}

				$usage_array = $this->check_image_usage( $image_id );

				// Convert existing format to new format
				$is_used         = false;
				$formatted_usage = array();

				foreach ( $usage_array as $usage_item ) {
					if ( $usage_item['status'] === 'publish' ) {
						$is_used           = true;
						$formatted_usage[] = array(
							'type'      => $usage_item['type'] === 'featured_image' ? 'Featured Image' : 'Post Content',
							'title'     => $usage_item['title'],
							'post_type' => $usage_item['post_type'],
						);
					}
				}

				$usage_details[ $image_id ] = array(
					'is_used'       => $is_used,
					'usage_details' => $formatted_usage,
					'usage_count'   => count( $formatted_usage ),
				);

				if ( $is_used ) {
					++$used_count;
				} else {
					++$safe_to_delete;
				}
			}

			$end_time   = microtime( true );
			$time_taken = round( ( $end_time - $start_time ) * 1000 ); // Convert to milliseconds

			wp_send_json_success(
				array(
					'usage_details'  => $usage_details,
					'used_count'     => $used_count,
					'safe_to_delete' => $safe_to_delete,
					'debug_info'     => array(
						'total_checked' => count( $image_ids ),
						'time_taken'    => $time_taken,
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'MSH Usage Check Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Usage check failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Prepare hash cache for all images
	 * Pre-generates MD5 hashes for images that don't have them cached
	 */
	public function ajax_prepare_hash_cache() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$hash_manager = $this->get_hash_manager();

			if ( ! $hash_manager ) {
				wp_send_json_error( array( 'message' => 'Hash manager not available' ) );
				return;
			}

			global $wpdb;

			// Get total count of images needing hashes
			$total_needing_hash = $wpdb->get_var(
				"
                SELECT COUNT(*) FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    AND pm.meta_key = '_msh_file_hash'
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
            "
			);

			if ( $total_needing_hash == 0 ) {
				wp_send_json_success(
					array(
						'completed'    => true,
						'message'      => 'All images have cached hashes',
						'total_cached' => $wpdb->get_var(
							"
                        SELECT COUNT(*) FROM {$wpdb->postmeta}
                        WHERE meta_key = '_msh_file_hash'
                        AND meta_value != ''
                    "
						),
					)
				);
				return;
			}

			// Get batch of images without hashes (limit 100 per request)
			$images_needing_hash = $wpdb->get_col(
				"
                SELECT p.ID FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    AND pm.meta_key = '_msh_file_hash'
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
                LIMIT 100
            "
			);

			if ( empty( $images_needing_hash ) ) {
				wp_send_json_success(
					array(
						'completed' => true,
						'message'   => 'Hash preparation complete',
					)
				);
				return;
			}

			// Batch generate hashes
			$results = $hash_manager->bulk_generate_hashes( $images_needing_hash );

			// CRITICAL FIX: Mark failed images with UNIQUE placeholder hash to prevent infinite loop
			// If files are missing, we need to mark them so they're not re-selected in next batch
			// Using unique hash per file prevents them from grouping together
			if ( $results['failed'] > 0 ) {
				foreach ( $images_needing_hash as $attachment_id ) {
					// Check if this attachment now has a hash
					$hash = get_post_meta( $attachment_id, '_msh_file_hash', true );
					if ( empty( $hash ) ) {
						// File likely doesn't exist - mark with UNIQUE placeholder (won't group together)
						$unique_placeholder = 'missing_' . $attachment_id;
						update_post_meta( $attachment_id, '_msh_file_hash', $unique_placeholder );
						error_log( "MSH Hash Preparation: Marked attachment {$attachment_id} as {$unique_placeholder}" );
					}
				}
			}

			wp_send_json_success(
				array(
					'completed'          => false,
					'total_needing_hash' => $total_needing_hash,
					'batch_size'         => count( $images_needing_hash ),
					'processed'          => count( $images_needing_hash ),
					'success'            => $results['success'],
					'failed'             => $results['failed'],
					'skipped'            => $results['skipped'],
					'progress_percent'   => round( ( 1 - ( $total_needing_hash / max( $total_needing_hash + 100, 1 ) ) ) * 100, 1 ),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'MSH Hash Preparation Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Hash preparation failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Hash-based duplicate scan (100% accurate for exact duplicates)
	 * Uses MD5 file hashes to detect byte-for-byte identical images
	 */
	public function ajax_hash_duplicate_scan() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$hash_manager = $this->get_hash_manager();

			if ( ! $hash_manager ) {
				wp_send_json_error( array( 'message' => 'Hash manager not available' ) );
				return;
			}

			error_log( 'MSH DUPLICATE: Starting Hash-Based Duplicate Scan (MD5)' );

			// Get duplicate groups by hash (from MSH_Hash_Cache_Manager)
			$duplicate_groups = $hash_manager->find_duplicate_hashes();

			// CRITICAL: Filter out 'missing_*' placeholder groups
			// These are files that don't exist and were marked during hash prep
			// Format: 'missing_123', 'missing_456', etc.
			$duplicate_groups = array_filter(
				$duplicate_groups,
				function ( $group ) {
					return strpos( $group['hash'], 'missing_' ) !== 0;
				}
			);

			if ( empty( $duplicate_groups ) ) {
				wp_send_json_success(
					array(
						'total_groups'        => 0,
						'total_duplicates'    => 0,
						'groups'              => array(),
						'detection_method'    => 'MD5 Hash',
						'accuracy'            => '100%',
						'false_positive_rate' => '0%',
						'debug_info'          => array(
							'message' => 'No exact duplicate files found',
						),
					)
				);
				return;
			}

			error_log( 'MSH DUPLICATE: Found ' . count( $duplicate_groups ) . ' hash-based duplicate groups' );

			// Enrich groups with full image details
			$enriched_groups  = array();
			$total_duplicates = 0;

			foreach ( $duplicate_groups as $group ) {
				$images = array();

				foreach ( $group['attachment_ids'] as $attachment_id ) {
					$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );

					if ( empty( $file_path ) ) {
						continue;
					}

					$images[] = array(
						'ID'           => $attachment_id,
						'post_title'   => get_the_title( $attachment_id ),
						'file_path'    => $file_path,
						'post_date'    => get_the_date( 'Y-m-d H:i:s', $attachment_id ),
						'is_published' => false, // Will be populated by analyze_group
						'usage'        => array(),
					);
				}

				if ( count( $images ) < 2 ) {
					continue; // Skip if not enough images in group
				}

				// Analyze group to determine recommended keep
				$analyzed                     = $this->analyze_group( $images );
				$analyzed['hash']             = $group['hash'];
				$analyzed['group_key']        = 'hash_' . substr( $group['hash'], 0, 8 );
				$analyzed['detection_method'] = 'md5_hash';
				$analyzed['accuracy']         = '100%';

				$enriched_groups[] = $analyzed;
				$total_duplicates += $analyzed['cleanup_potential'];
			}

			error_log( 'MSH DUPLICATE: Hash scan complete - ' . count( $enriched_groups ) . ' groups with ' . $total_duplicates . ' duplicates' );

			wp_send_json_success(
				array(
					'total_groups'        => count( $enriched_groups ),
					'total_duplicates'    => $total_duplicates,
					'groups'              => $enriched_groups,
					'detection_method'    => 'MD5 Hash (Exact Match)',
					'accuracy'            => '100%',
					'false_positive_rate' => '0%',
					'debug_info'          => array(
						'approach'          => 'MD5 file hash comparison',
						'hash_groups_found' => count( $duplicate_groups ),
						'enriched_groups'   => count( $enriched_groups ),
						'guarantees'        => 'Byte-for-byte identical files only',
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'MSH Hash Scan Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Hash scan failed: ' . $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
				)
			);
		}
	}

	/**
	 * AJAX: Kick off visual similarity scan.
	 */
	public function ajax_visual_similarity_scan_start() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$perceptual_manager = $this->get_perceptual_manager();

			if ( ! $perceptual_manager ) {
				wp_send_json_error(
					array(
						'message' => __( 'Perceptual hashing is unavailable. Ensure the GD extension is enabled.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			$this->reset_visual_scan_state();

			$attachment_ids = $this->get_visual_scan_attachment_ids();
			$total          = count( $attachment_ids );

			if ( $total === 0 ) {
				wp_send_json_success(
					array(
						'completed' => true,
						'total'     => 0,
						'message'   => __( 'No images available for visual similarity scanning.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			$state = array(
				'queue'     => $attachment_ids,
				'index'     => 0,
				'processed' => 0,
				'total'     => $total,
				'records'   => array(),
				'skipped'   => array(),
				'errors'    => array(),
			);

			$this->set_visual_scan_state( $state );

			set_transient(
				$this->get_visual_scan_progress_key(),
				array(
					'status'     => 'queued',
					'current'    => 0,
					'total'      => $total,
					'message'    => __( 'Preparing visual similarity scan…', 'msh-image-optimizer' ),
					'batch_size' => self::VISUAL_SCAN_BATCH_SIZE,
					'started_at' => time(),
					'skipped'    => 0,
					'errors'     => 0,
				),
				self::VISUAL_SCAN_STATE_TTL
			);

			wp_send_json_success(
				array(
					'completed'  => false,
					'total'      => $total,
					'batch_size' => self::VISUAL_SCAN_BATCH_SIZE,
					'batches'    => (int) ceil( $total / self::VISUAL_SCAN_BATCH_SIZE ),
					'message'    => __( 'Visual similarity scan initialized.', 'msh-image-optimizer' ),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'MSH Visual Scan Start Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Visual similarity scan failed to start: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Process all collected images for deep duplicate analysis (optimized for memory)
	 */
	private function process_all_collected_images( $all_images ) {
		$base_names = array();

		// Group all images by base filename - process in chunks to save memory
		$chunk_size   = 100;
		$image_chunks = array_chunk( $all_images, $chunk_size );

		foreach ( $image_chunks as $chunk ) {
			foreach ( $chunk as $image ) {
				$base_name = $this->get_base_filename( $image['file_path'] );

				if ( ! isset( $base_names[ $base_name ] ) ) {
					$base_names[ $base_name ] = array();
				}

				// Only store essential data to save memory
				$base_names[ $base_name ][] = array(
					'ID'         => $image['ID'],
					'post_title' => $image['post_title'],
					'file_path'  => $image['file_path'],
					'post_date'  => $image['post_date'] ?? '',
				);
			}

			// Clear chunk from memory
			unset( $chunk );
		}

		// Only keep groups with multiple images and analyze them (simplified analysis)
		$groups = array();
		foreach ( $base_names as $base_name => $group ) {
			if ( count( $group ) > 1 ) {
				// Simplified group analysis for performance
				$groups[ $base_name ] = array(
					'images'            => $group,
					'total_count'       => count( $group ),
					'cleanup_potential' => count( $group ) - 1, // Keep first, delete rest
					'published_count'   => 0, // Skip expensive checks
					'sizes_available'   => array( 'Multiple variations' ),
					'recommended_keep'  => $group[0],
				);
			}
		}

		return $groups;
	}

	/**
	 * Process a single attachment within the visual scan workflow.
	 *
	 * @param int                 $attachment_id
	 * @param MSH_Perceptual_Hash $perceptual_manager
	 * @param array               $state Reference to scan state (mutated).
	 *
	 * @return true|WP_Error
	 */
	private function process_visual_scan_attachment( $attachment_id, $perceptual_manager, array &$state ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return new WP_Error( 'msh_phash_invalid_attachment', 'Invalid attachment ID encountered during visual scan.' );
		}

		if ( isset( $state['records'][ $attachment_id ] ) && isset( $state['records'][ $attachment_id ]['phash_status'] ) ) {
			// Already processed in a previous batch.
			return true;
		}

		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error( 'msh_phash_missing_attachment', 'Attachment post missing for visual scan.' );
		}

		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$mime_type = get_post_mime_type( $attachment_id );
		$metadata  = wp_get_attachment_metadata( $attachment_id );

		$width    = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height   = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
		$filesize = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : 0;

		$hash_result = $perceptual_manager->generate_hash( $attachment_id );

		if ( is_wp_error( $hash_result ) ) {
			if ( 'msh_phash_skipped_svg' === $hash_result->get_error_code() ) {
				$record = array(
					'ID'           => $attachment_id,
					'post_title'   => $attachment->post_title,
					'title'        => $attachment->post_title,
					'post_date'    => $attachment->post_date,
					'file_path'    => (string) $file_path,
					'mime'         => $mime_type,
					'width'        => $width,
					'height'       => $height,
					'filesize'     => $filesize,
					'url'          => wp_get_attachment_url( $attachment_id ),
					'thumb_url'    => wp_get_attachment_thumb_url( $attachment_id ) ?: '',
					'hash'         => '',
					'phash_status' => MSH_Perceptual_Hash::STATUS_SKIPPED_SVG,
					'skip_reason'  => 'svg',
					'skip_message' => $hash_result->get_error_message(),
					'base_name'    => self::normalize_base_filename( (string) $file_path ),
				);

				$state['records'][ $attachment_id ] = $record;

				$state['skipped'][ $attachment_id ] = array(
					'attachment_id' => $attachment_id,
					'title'         => $attachment->post_title,
					'mime'          => $mime_type,
					'reason'        => 'svg',
					'message'       => $hash_result->get_error_message(),
				);

				return true;
			}

			return $hash_result;
		}

		$record = array(
			'ID'             => $attachment_id,
			'post_title'     => $attachment->post_title,
			'title'          => $attachment->post_title,
			'post_date'      => $attachment->post_date,
			'file_path'      => (string) $file_path,
			'mime'           => $mime_type,
			'width'          => $width,
			'height'         => $height,
			'filesize'       => $filesize,
			'url'            => wp_get_attachment_url( $attachment_id ),
			'thumb_url'      => wp_get_attachment_thumb_url( $attachment_id ) ?: '',
			'hash'           => strtolower( $hash_result ),
			'phash_status'   => MSH_Perceptual_Hash::STATUS_OK,
			'phash_time'     => (int) get_post_meta( $attachment_id, MSH_Perceptual_Hash::META_TIME, true ),
			'phash_modified' => (int) get_post_meta( $attachment_id, MSH_Perceptual_Hash::META_MODIFIED, true ),
			'base_name'      => self::normalize_base_filename( (string) $file_path ),
		);

		$state['records'][ $attachment_id ] = $record;

		return true;
	}

	/**
	 * Finalize visual scan by generating similarity groups and caching results.
	 *
	 * @param array               $state
	 * @param MSH_Perceptual_Hash $perceptual_manager
	 *
	 * @return array
	 */
	private function finalize_visual_scan( array $state, $perceptual_manager ) {
		$results_key = $this->get_visual_scan_results_key();
		$cached      = get_transient( $results_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$records           = isset( $state['records'] ) && is_array( $state['records'] ) ? $state['records'] : array();
		$visual_candidates = array_filter(
			$records,
			static function ( $record ) {
				return isset( $record['phash_status'], $record['hash'] )
				&& MSH_Perceptual_Hash::STATUS_OK === $record['phash_status']
				&& ! empty( $record['hash'] );
			}
		);

		$perceptual_input = array_map(
			static function ( $record ) {
				return $record;
			},
			$visual_candidates
		);

		$perceptual_result = $perceptual_manager->group_similar(
			$perceptual_input,
			array(
				'distance_cap' => 15,
			)
		);

		$visual_groups   = $this->format_perceptual_groups( $perceptual_result, $records );
		$md5_groups      = $this->build_md5_groups_for_visual_scan( $records );
		$filename_groups = $this->build_filename_groups_from_records( $records );

		$merged_groups = array_merge( $md5_groups, $visual_groups, $filename_groups );

		usort(
			$merged_groups,
			function ( $a, $b ) {
				$priority = array(
					'md5_hash'           => 0,
					'perceptual_hash'    => 1,
					'filename_collision' => 2,
				);

				$a_priority = isset( $priority[ $a['detection_method'] ] ) ? $priority[ $a['detection_method'] ] : 3;
				$b_priority = isset( $priority[ $b['detection_method'] ] ) ? $priority[ $b['detection_method'] ] : 3;

				if ( $a_priority === $b_priority ) {
					$a_score = isset( $a['similarity_score'] ) ? (float) $a['similarity_score'] : 0;
					$b_score = isset( $b['similarity_score'] ) ? (float) $b['similarity_score'] : 0;
					return $b_score <=> $a_score;
				}

				return $a_priority <=> $b_priority;
			}
		);

		$confidence_breakdown = array();
		foreach ( $merged_groups as $group ) {
			$tier = isset( $group['confidence_tier'] ) ? $group['confidence_tier'] : 'unknown';
			if ( ! isset( $confidence_breakdown[ $tier ] ) ) {
				$confidence_breakdown[ $tier ] = 0;
			}
			++$confidence_breakdown[ $tier ];
		}

		$total_groups     = count( $merged_groups );
		$total_duplicates = 0;
		foreach ( $merged_groups as $group ) {
			$files             = isset( $group['files'] ) && is_array( $group['files'] ) ? $group['files'] : array();
			$total_duplicates += max( count( $files ) - 1, 0 );
		}

		$results = array(
			'generated_at'      => time(),
			'expires_at'        => time() + self::VISUAL_SCAN_RESULT_TTL,
			'total_processed'   => isset( $state['processed'] ) ? (int) $state['processed'] : count( $records ),
			'total_attachments' => isset( $state['total'] ) ? (int) $state['total'] : count( $records ),
			'total_groups'      => $total_groups,
			'total_duplicates'  => $total_duplicates,
			'safe_to_remove'    => $total_duplicates,
			'groups'            => $merged_groups,
			'summary'           => array(
				'visual_groups'        => count( $visual_groups ),
				'md5_groups'           => count( $md5_groups ),
				'filename_groups'      => count( $filename_groups ),
				'confidence_breakdown' => $confidence_breakdown,
				'skipped'              => count( $state['skipped'] ),
				'errors'               => count( $state['errors'] ),
				'total_groups'         => $total_groups,
				'total_duplicates'     => $total_duplicates,
			),
			'thresholds'        => $perceptual_result['thresholds'],
			'skipped'           => array_values( $state['skipped'] ),
			'errors'            => array_values( $state['errors'] ),
		);

		set_transient( $results_key, $results, self::VISUAL_SCAN_RESULT_TTL );

		set_transient(
			$this->get_visual_scan_progress_key(),
			array(
				'status'  => 'complete',
				'current' => $results['total_processed'],
				'total'   => $results['total_attachments'],
				'message' => __( 'Visual similarity scan completed.', 'msh-image-optimizer' ),
				'skipped' => count( $state['skipped'] ),
				'errors'  => count( $state['errors'] ),
			),
			self::VISUAL_SCAN_STATE_TTL
		);

		delete_transient( $this->get_visual_scan_state_key() );

		return $results;
	}

	/**
	 * Format perceptual hash groups for UI consumption.
	 *
	 * @param array $perceptual_result
	 * @param array $records
	 *
	 * @return array
	 */
	private function format_perceptual_groups( array $perceptual_result, array $records ) {
		if ( empty( $perceptual_result['groups'] ) ) {
			return array();
		}

		$formatted = array();

		foreach ( $perceptual_result['groups'] as $group ) {
			if ( empty( $group['attachment_ids'] ) || count( $group['attachment_ids'] ) < 2 ) {
				continue;
			}

			$analysis_seed = array();
			$record_map    = array();

			foreach ( $group['attachment_ids'] as $attachment_id ) {
				if ( ! isset( $records[ $attachment_id ] ) ) {
					continue;
				}

				$record = $records[ $attachment_id ];

				$analysis_seed[] = array(
					'ID'         => $attachment_id,
					'post_title' => $record['post_title'],
					'file_path'  => $record['file_path'],
					'post_date'  => $record['post_date'],
				);

				$record_map[ $attachment_id ] = $record;
			}

			if ( count( $analysis_seed ) < 2 ) {
				continue;
			}

			$analysis = $this->analyze_group( $analysis_seed );
			$files    = $this->merge_analysis_with_records( $analysis['images'], $record_map );

			$recommended_keep = null;
			if ( ! empty( $analysis['recommended_keep'] ) && isset( $record_map[ $analysis['recommended_keep']['ID'] ] ) ) {
				$recommended_keep = array_merge( $record_map[ $analysis['recommended_keep']['ID'] ], $analysis['recommended_keep'] );
			}

			$metrics          = isset( $group['metrics'] ) ? $group['metrics'] : array();
			$confidence_tier  = isset( $metrics['primary_tier'] ) ? $metrics['primary_tier'] : 'possible';
			$distance_bits    = isset( $metrics['min_distance'] ) ? (int) $metrics['min_distance'] : null;
			$similarity_score = isset( $metrics['primary_score'] ) ? (float) $metrics['primary_score'] : null;

			$formatted[] = array(
				'group_key'         => $this->build_group_key( 'visual', $group['attachment_ids'] ),
				'detection_method'  => 'perceptual_hash',
				'confidence_tier'   => $confidence_tier,
				'confidence_label'  => $this->confidence_label_for_tier( $confidence_tier ),
				'confidence_note'   => $this->confidence_note_for_tier( $confidence_tier, $distance_bits ),
				'distance_bits'     => $distance_bits,
				'similarity_score'  => $similarity_score,
				'similarity_label'  => $this->similarity_label( $similarity_score ),
				'metrics'           => $metrics,
				'files'             => $files,
				'recommended_keep'  => $recommended_keep,
				'cleanup_potential' => isset( $analysis['cleanup_potential'] ) ? $analysis['cleanup_potential'] : max( count( $files ) - 1, 0 ),
				'published_count'   => isset( $analysis['published_count'] ) ? $analysis['published_count'] : 0,
				'total_count'       => count( $files ),
				'detection_badges'  => $this->build_detection_badges( 'perceptual_hash', $confidence_tier, $similarity_score, $distance_bits ),
				'pairs'             => isset( $group['pairs'] ) ? $group['pairs'] : array(),
			);
		}

		return $formatted;
	}

	/**
	 * Build MD5 duplicate groups for inclusion in the visual scan results.
	 *
	 * @param array $records
	 *
	 * @return array
	 */
	private function build_md5_groups_for_visual_scan( array $records ) {
		$hash_manager = $this->get_hash_manager();

		if ( ! $hash_manager ) {
			return array();
		}

		$duplicates = $hash_manager->find_duplicate_hashes();

		if ( empty( $duplicates ) ) {
			return array();
		}

		$groups = array();

		foreach ( $duplicates as $duplicate ) {
			$attachment_ids = isset( $duplicate['attachment_ids'] ) ? (array) $duplicate['attachment_ids'] : array();

			if ( count( $attachment_ids ) < 2 ) {
				continue;
			}

			$analysis_seed = array();
			$record_map    = array();

			foreach ( $attachment_ids as $attachment_id ) {
				$attachment_id = (int) $attachment_id;
				if ( $attachment_id <= 0 ) {
					continue;
				}

				$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );

				if ( ! $file_path ) {
					continue;
				}

				$analysis_seed[] = array(
					'ID'         => $attachment_id,
					'post_title' => get_the_title( $attachment_id ),
					'file_path'  => $file_path,
					'post_date'  => get_post_field( 'post_date', $attachment_id ),
				);

				if ( isset( $records[ $attachment_id ] ) ) {
					$record_map[ $attachment_id ] = $records[ $attachment_id ];
				} else {
					$record_map[ $attachment_id ] = array(
						'ID'         => $attachment_id,
						'post_title' => get_the_title( $attachment_id ),
						'file_path'  => $file_path,
						'mime'       => get_post_mime_type( $attachment_id ),
						'url'        => wp_get_attachment_url( $attachment_id ),
						'thumb_url'  => wp_get_attachment_thumb_url( $attachment_id ) ?: '',
					);
				}
			}

			if ( count( $analysis_seed ) < 2 ) {
				continue;
			}

			$analysis = $this->analyze_group( $analysis_seed );
			$files    = $this->merge_analysis_with_records( $analysis['images'], $record_map );

			$recommended_keep = null;
			if ( ! empty( $analysis['recommended_keep'] ) && isset( $record_map[ $analysis['recommended_keep']['ID'] ] ) ) {
				$recommended_keep = array_merge( $record_map[ $analysis['recommended_keep']['ID'] ], $analysis['recommended_keep'] );
			}

			$groups[] = array(
				'group_key'         => $this->build_group_key( 'md5', $attachment_ids ),
				'detection_method'  => 'md5_hash',
				'confidence_tier'   => 'definite',
				'confidence_label'  => $this->confidence_label_for_tier( 'definite' ),
				'confidence_note'   => __( 'Exact file match (byte-for-byte identical).', 'msh-image-optimizer' ),
				'distance_bits'     => 0,
				'similarity_score'  => 100.0,
				'similarity_label'  => __( 'Exact match', 'msh-image-optimizer' ),
				'files'             => $files,
				'recommended_keep'  => $recommended_keep,
				'cleanup_potential' => isset( $analysis['cleanup_potential'] ) ? $analysis['cleanup_potential'] : max( count( $files ) - 1, 0 ),
				'published_count'   => isset( $analysis['published_count'] ) ? $analysis['published_count'] : 0,
				'total_count'       => count( $files ),
				'detection_badges'  => $this->build_detection_badges( 'md5_hash', 'definite', 100.0, 0 ),
			);
		}

		return $groups;
	}

	/**
	 * Build filename collision groups from processed records.
	 *
	 * @param array $records
	 *
	 * @return array
	 */
	private function build_filename_groups_from_records( array $records ) {
		if ( empty( $records ) ) {
			return array();
		}

		$buckets = array();

		foreach ( $records as $record ) {
			if ( ! isset( $record['base_name'] ) || $record['base_name'] === '' ) {
				continue;
			}

			$key = strtolower( $record['base_name'] );
			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = array();
			}

			$buckets[ $key ][] = $record;
		}

		$groups = array();

		foreach ( $buckets as $base_name => $bucket_records ) {
			if ( count( $bucket_records ) < 2 ) {
				continue;
			}

			$analysis_seed = array_map(
				static function ( $record ) {
					return array(
						'ID'         => $record['ID'],
						'post_title' => $record['post_title'],
						'file_path'  => $record['file_path'],
						'post_date'  => $record['post_date'],
					);
				},
				$bucket_records
			);

			$analysis   = $this->analyze_group( $analysis_seed );
			$record_map = array();

			foreach ( $bucket_records as $record ) {
				$record_map[ $record['ID'] ] = $record;
			}

			$files = $this->merge_analysis_with_records( $analysis['images'], $record_map );

			$recommended_keep = null;
			if ( ! empty( $analysis['recommended_keep'] ) && isset( $record_map[ $analysis['recommended_keep']['ID'] ] ) ) {
				$recommended_keep = array_merge( $record_map[ $analysis['recommended_keep']['ID'] ], $analysis['recommended_keep'] );
			}

			$groups[] = array(
				'group_key'         => $this->build_group_key( 'filename', array_column( $bucket_records, 'ID' ) ),
				'detection_method'  => 'filename_collision',
				'confidence_tier'   => 'filename',
				'confidence_label'  => __( 'Filename collision', 'msh-image-optimizer' ),
				'confidence_note'   => sprintf( __( 'Files share normalized base name “%s”. Review manually.', 'msh-image-optimizer' ), $base_name ),
				'distance_bits'     => null,
				'similarity_score'  => null,
				'similarity_label'  => __( 'Filename match', 'msh-image-optimizer' ),
				'files'             => $files,
				'recommended_keep'  => $recommended_keep,
				'cleanup_potential' => isset( $analysis['cleanup_potential'] ) ? $analysis['cleanup_potential'] : max( count( $files ) - 1, 0 ),
				'published_count'   => isset( $analysis['published_count'] ) ? $analysis['published_count'] : 0,
				'total_count'       => count( $files ),
				'detection_badges'  => $this->build_detection_badges( 'filename_collision', 'filename', null, null ),
			);
		}

		return $groups;
	}

	/**
	 * Merge analysis results with stored record metadata.
	 *
	 * @param array $analysis_images
	 * @param array $record_map
	 *
	 * @return array
	 */
	private function merge_analysis_with_records( array $analysis_images, array $record_map ) {
		$files = array();

		foreach ( $analysis_images as $image ) {
			$attachment_id = isset( $image['ID'] ) ? (int) $image['ID'] : 0;
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$record   = isset( $record_map[ $attachment_id ] ) ? $record_map[ $attachment_id ] : array();
			$filename = isset( $record['file_path'] ) ? basename( $record['file_path'] ) : '';

			$files[] = array_merge(
				$record,
				$image,
				array(
					'filename' => $filename,
				)
			);
		}

		return $files;
	}

	/**
	 * Generate deterministic group key.
	 *
	 * @param string $prefix
	 * @param array  $attachment_ids
	 *
	 * @return string
	 */
	private function build_group_key( $prefix, array $attachment_ids ) {
		sort( $attachment_ids );
		return $prefix . '_' . substr( md5( implode( '-', $attachment_ids ) ), 0, 10 );
	}

	/**
	 * Map confidence tier to human-readable label.
	 *
	 * @param string $tier
	 *
	 * @return string
	 */
	private function confidence_label_for_tier( $tier ) {
		switch ( $tier ) {
			case 'definite':
				return __( 'Definite duplicate', 'msh-image-optimizer' );
			case 'likely':
				return __( 'Likely duplicate', 'msh-image-optimizer' );
			case 'possible':
				return __( 'Possibly related', 'msh-image-optimizer' );
			case 'filename':
				return __( 'Filename collision', 'msh-image-optimizer' );
			default:
				return __( 'Needs review', 'msh-image-optimizer' );
		}
	}

	/**
	 * Additional descriptive note for confidence tier.
	 *
	 * @param string   $tier
	 * @param int|null $distance_bits
	 *
	 * @return string|null
	 */
	private function confidence_note_for_tier( $tier, $distance_bits = null ) {
		switch ( $tier ) {
			case 'definite':
				return __( '0–5 bits difference (≥95% visual similarity).', 'msh-image-optimizer' );
			case 'likely':
				return __( '6–10 bits difference (85–94% similarity).', 'msh-image-optimizer' );
			case 'possible':
				return __( '11–15 bits difference (75–84% similarity).', 'msh-image-optimizer' );
			case 'filename':
				return __( 'Grouped by normalized filename – confirm manually.', 'msh-image-optimizer' );
			default:
				return $distance_bits !== null
					? sprintf( __( 'Distance: %d bits', 'msh-image-optimizer' ), (int) $distance_bits )
					: null;
		}
	}

	/**
	 * Convert similarity score to a succinct label.
	 *
	 * @param float|null $score
	 *
	 * @return string|null
	 */
	private function similarity_label( $score ) {
		if ( null === $score ) {
			return null;
		}

		if ( $score >= 95 ) {
			return __( '≈100% match', 'msh-image-optimizer' );
		}

		if ( $score >= 85 ) {
			return __( 'High visual match', 'msh-image-optimizer' );
		}

		if ( $score >= 75 ) {
			return __( 'Moderate similarity', 'msh-image-optimizer' );
		}

		return __( 'Low similarity', 'msh-image-optimizer' );
	}

	/**
	 * Build detection badge metadata for UI rendering.
	 *
	 * @param string     $method
	 * @param string     $tier
	 * @param float|null $score
	 * @param int|null   $distance
	 *
	 * @return array
	 */
	private function build_detection_badges( $method, $tier, $score, $distance ) {
		$badges = array();

		switch ( $method ) {
			case 'md5_hash':
				$badges[] = array(
					'label'   => __( 'Exact file hash', 'msh-image-optimizer' ),
					'variant' => 'success',
					'icon'    => 'hash',
				);
				break;

			case 'perceptual_hash':
				if ( $score !== null ) {
					$badges[] = array(
						'label'   => sprintf( __( 'Visual %.1f%%', 'msh-image-optimizer' ), $score ),
						'variant' => ( 'definite' === $tier ) ? 'success' : ( ( 'likely' === $tier ) ? 'warning' : 'info' ),
						'icon'    => 'visual',
					);
				}

				if ( $distance !== null ) {
					$badges[] = array(
						'label'   => sprintf( __( '%d-bit diff', 'msh-image-optimizer' ), (int) $distance ),
						'variant' => 'neutral',
						'icon'    => 'visual',
					);
				}
				break;

			case 'filename_collision':
				$badges[] = array(
					'label'   => __( 'Filename match', 'msh-image-optimizer' ),
					'variant' => 'neutral',
					'icon'    => 'filename',
				);
				break;

			default:
				$badges[] = array(
					'label'   => __( 'Heuristic match', 'msh-image-optimizer' ),
					'variant' => 'info',
					'icon'    => 'hash',
				);
		}

		return $badges;
	}

	/**
	 * AJAX: Quick duplicate scan - finds obvious duplicates fast
	 * IMPROVED: Now scans ENTIRE library using chunked processing (no LIMIT)
	 */
	public function ajax_quick_duplicate_scan() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$report = $this->generate_quick_scan_report();

			if ( is_wp_error( $report ) ) {
				wp_send_json_error( array( 'message' => $report->get_error_message() ) );
				return;
			}

			wp_send_json_success( $report );

		} catch ( Exception $e ) {
			error_log( 'MSH DUPLICATE ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error(
				array(
					'message' => 'Quick scan failed: ' . $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
				)
			);
		}
	}

	public function generate_quick_scan_report( $record_timestamp = true ) {
		try {
			global $wpdb;

			error_log( 'MSH DUPLICATE: Starting Quick Duplicate Scan - FULL LIBRARY content-based detection' );

			$total_images = $wpdb->get_var(
				"
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_value != ''
                AND pm.meta_value IS NOT NULL
            "
			);

			error_log( "MSH DUPLICATE: Full library scan - {$total_images} total images to analyze" );

			$memory_limit = ini_get( 'memory_limit' );
			$memory_in_mb = intval( $memory_limit );
			$chunk_size   = $memory_in_mb > 256 ? 500 : 200;

			$all_images = $wpdb->get_results(
				"
                SELECT
                    p.ID,
                    p.post_title,
                    pm.meta_value as file_path,
                    pm_size.meta_value as file_metadata
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
                LEFT JOIN {$wpdb->postmeta} pm_size ON p.ID = pm_size.post_id AND pm_size.meta_key = '_wp_attachment_metadata'
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_value != ''
                AND pm.meta_value IS NOT NULL
                ORDER BY p.post_date DESC
            ",
				ARRAY_A
			);

			error_log( 'MSH DUPLICATE: Loaded ' . count( $all_images ) . ' images for content-based analysis' );

			if ( empty( $all_images ) ) {
				return array(
					'total_groups'     => 0,
					'total_duplicates' => 0,
					'groups'           => array(),
					'debug_info'       => array(
						'message' => 'No images found in media library',
					),
				);
			}

			$groups     = array();
			$processed  = 0;
			$upload_dir = wp_upload_dir();

			foreach ( $all_images as $image ) {
				if ( empty( $image['file_path'] ) ) {
					continue;
				}

				++$processed;

				$base_name = $this->get_base_filename( $image['file_path'] );

				if ( empty( $base_name ) ) {
					error_log( 'MSH DUPLICATE: Could not extract base name from: ' . $image['file_path'] );
					continue;
				}

				$file_size = null;
				if ( ! empty( $image['file_metadata'] ) ) {
					$metadata = maybe_unserialize( $image['file_metadata'] );
					if ( isset( $metadata['filesize'] ) ) {
						$file_size = $metadata['filesize'];
					} elseif ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
						$file_size = intval( $metadata['width'] * $metadata['height'] * 0.3 );
					}
				}

				if ( ! $file_size ) {
					$full_path = $upload_dir['basedir'] . '/' . $image['file_path'];
					if ( file_exists( $full_path ) ) {
						$file_size = filesize( $full_path );
					}
				}

				$size_bucket = $file_size ? intval( $file_size / 5000 ) * 5000 : 'unknown';
				$group_key   = $base_name . '_sz_' . $size_bucket;

				if ( ! isset( $groups[ $group_key ] ) ) {
					$groups[ $group_key ] = array();
				}

				$groups[ $group_key ][] = array(
					'ID'           => $image['ID'],
					'post_title'   => $image['post_title'],
					'file_path'    => $image['file_path'],
					'file_size'    => $file_size,
					'base_name'    => $base_name,
					'is_published' => false,
					'usage'        => array(),
					'keep_score'   => 1,
				);
			}

			error_log( 'MSH DUPLICATE: Processed ' . $processed . ' images into ' . count( $groups ) . ' content groups' );

			$duplicate_groups = array();
			$total_duplicates = 0;

			foreach ( $groups as $group_key => $images ) {
				if ( count( $images ) > 1 ) {
					$analyzed_group              = $this->analyze_group( $images );
					$analyzed_group['group_key'] = $group_key;
					$duplicate_groups[]          = $analyzed_group;
					$total_duplicates           += $analyzed_group['cleanup_potential'];
				}
			}

			error_log( 'MSH DUPLICATE: Found ' . count( $duplicate_groups ) . ' duplicate groups with ' . $total_duplicates . ' files for potential cleanup' );

			$report = array(
				'total_groups'     => count( $duplicate_groups ),
				'total_duplicates' => $total_duplicates,
				'groups'           => $duplicate_groups,
				'debug_info'       => array(
					'approach'                     => 'content-based detection (base filename + file size)',
					'total_library_size'           => $total_images,
					'images_analyzed'              => $processed,
					'coverage_percent'             => round( ( $processed / max( $total_images, 1 ) ) * 100, 1 ),
					'content_groups_created'       => count( $groups ),
					'duplicate_groups_found'       => count( $duplicate_groups ),
					'sample_groups'                => array_slice( array_keys( $groups ), 0, 3 ),
					'memory_usage'                 => round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB',
					'memory_peak'                  => round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'MB',
					'full_library_scan'            => true,
					'post_optimization_compatible' => true,
				),
			);

			if ( $record_timestamp ) {
				update_option( 'msh_last_duplicate_scan', current_time( 'mysql' ) );
			}

			return $report;

		} catch ( Exception $e ) {
			return new WP_Error( 'msh_duplicate_scan_failed', $e->getMessage() );
		}
	}

	/**
	 * AJAX: Process next batch for visual similarity scan.
	 */
	public function ajax_visual_similarity_scan_batch() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$perceptual_manager = $this->get_perceptual_manager();

			if ( ! $perceptual_manager ) {
				wp_send_json_error(
					array(
						'message' => __( 'Perceptual hashing is unavailable. Ensure the GD extension is enabled.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			$state = $this->get_visual_scan_state();

			if ( ! $state ) {
				wp_send_json_error(
					array(
						'message' => __( 'Visual similarity scan state not found. Please restart the scan.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			$batch_size = self::VISUAL_SCAN_BATCH_SIZE;
			$total      = isset( $state['total'] ) ? (int) $state['total'] : 0;
			$index      = isset( $state['index'] ) ? (int) $state['index'] : 0;

			if ( $index >= $total ) {
				$results = $this->finalize_visual_scan( $state, $perceptual_manager );

				wp_send_json_success(
					array(
						'complete'  => true,
						'completed' => true,
						'results'   => $results,
						'total'     => $total,
						'processed' => $total,
						'percent'   => 100,
						'progress'  => array(
							'status'  => 'complete',
							'current' => $total,
							'total'   => $total,
							'percent' => 100,
							'message' => __( 'Visual similarity scan completed.', 'msh-image-optimizer' ),
						),
						'message'   => __( 'Visual similarity scan completed.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			$queue = isset( $state['queue'] ) ? $state['queue'] : array();
			$slice = array_slice( $queue, $index, $batch_size );

			if ( empty( $slice ) ) {
				$state['index']     = $total;
				$state['processed'] = $total;
				$this->set_visual_scan_state( $state );

				$results = $this->finalize_visual_scan( $state, $perceptual_manager );

				wp_send_json_success(
					array(
						'complete'  => true,
						'completed' => true,
						'results'   => $results,
						'total'     => $total,
						'processed' => $total,
						'percent'   => 100,
						'progress'  => array(
							'status'  => 'complete',
							'current' => $total,
							'total'   => $total,
							'percent' => 100,
							'message' => __( 'Visual similarity scan completed.', 'msh-image-optimizer' ),
						),
						'message'   => __( 'Visual similarity scan completed.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			$processed_in_batch = 0;
			foreach ( $slice as $attachment_id ) {
				$result = $this->process_visual_scan_attachment( $attachment_id, $perceptual_manager, $state );

				if ( is_wp_error( $result ) ) {
					$state['errors'][] = array(
						'attachment_id' => $attachment_id,
						'code'          => $result->get_error_code(),
						'message'       => $result->get_error_message(),
					);
				}

				++$processed_in_batch;

				if ( $processed_in_batch % 10 === 0 && function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 30 );
				}
			}

			$state['index']     += $processed_in_batch;
			$state['processed'] += $processed_in_batch;

			$this->set_visual_scan_state( $state );

			$percent_complete = $total > 0 ? min( 100, round( ( $state['processed'] / $total ) * 100, 2 ) ) : 0;

			$progress_payload = array(
				'status'     => 'processing',
				'current'    => $state['processed'],
				'total'      => $total,
				'message'    => sprintf( __( 'Processed %1$d of %2$d images…', 'msh-image-optimizer' ), $state['processed'], $total ),
				'batch_size' => $batch_size,
				'skipped'    => count( $state['skipped'] ),
				'errors'     => count( $state['errors'] ),
				'percent'    => $percent_complete,
			);

			set_transient( $this->get_visual_scan_progress_key(), $progress_payload, self::VISUAL_SCAN_STATE_TTL );

			$complete = $state['processed'] >= $total;
			$response = array(
				'complete'  => $complete,
				'completed' => $complete,
				'processed' => $state['processed'],
				'remaining' => max( $total - $state['processed'], 0 ),
				'total'     => $total,
				'skipped'   => array_values( $state['skipped'] ),
				'progress'  => $progress_payload,
				'percent'   => $percent_complete,
			);

			if ( $complete ) {
				$results             = $this->finalize_visual_scan( $state, $perceptual_manager );
				$response['results'] = $results;
				$response['message'] = __( 'Visual similarity scan completed.', 'msh-image-optimizer' );
			}

			wp_send_json_success( $response );

		} catch ( Exception $e ) {
			error_log( 'MSH Visual Scan Batch Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => 'Visual similarity batch failed: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Retrieve visual scan progress snapshot.
	 */
	public function ajax_visual_similarity_scan_status() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$progress          = get_transient( $this->get_visual_scan_progress_key() );
			$results_available = (bool) get_transient( $this->get_visual_scan_results_key() );

			wp_send_json_success(
				array(
					'progress'          => $progress ?: array(
						'status'  => 'idle',
						'current' => 0,
						'total'   => 0,
						'message' => __( 'No visual scan in progress.', 'msh-image-optimizer' ),
					),
					'results_available' => $results_available,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Failed to fetch visual scan status: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Fetch cached visual scan results.
	 */
	public function ajax_visual_similarity_scan_results() {
		try {
			check_ajax_referer( 'msh_media_cleanup', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized access' ) );
				return;
			}

			$results = get_transient( $this->get_visual_scan_results_key() );

			if ( ! $results ) {
				$state              = $this->get_visual_scan_state();
				$perceptual_manager = $this->get_perceptual_manager();

				if ( $state && $perceptual_manager && isset( $state['processed'], $state['total'] ) && $state['processed'] >= $state['total'] ) {
					$results = $this->finalize_visual_scan( $state, $perceptual_manager );
				}
			}

			if ( ! $results ) {
				wp_send_json_error(
					array(
						'message' => __( 'No cached visual scan results found. Run the scan first.', 'msh-image-optimizer' ),
					)
				);
				return;
			}

			update_option( 'msh_last_visual_scan', current_time( 'mysql' ) );

			wp_send_json_success(
				array(
					'results'          => $results,
					'total_groups'     => $results['total_groups'],
					'total_duplicates' => $results['total_duplicates'],
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				array(
					'message' => 'Failed to fetch visual scan results: ' . $e->getMessage(),
				)
			);
		}
	}

	/**
	 * AJAX: Cleanup media (remove selected duplicates)
	 */
	public function ajax_cleanup_media() {
		check_ajax_referer( 'msh_media_cleanup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$action_type = $_POST['action_type'] ?? 'safe';
		$image_ids   = $_POST['image_ids'] ?? array();

		$results       = array();
		$deleted_count = 0;

		foreach ( $image_ids as $attachment_id ) {
			$attachment_id = intval( $attachment_id );

			// Safety check - don't delete if used in published content
			if ( $action_type === 'safe' ) {
				$usage           = $this->check_image_usage( $attachment_id );
				$published_usage = array_filter(
					$usage,
					function ( $use ) {
						return $use['status'] === 'publish';
					}
				);

				if ( ! empty( $published_usage ) ) {
					$results[] = array(
						'id'     => $attachment_id,
						'status' => 'skipped',
						'reason' => 'Used in published content',
					);
					continue;
				}
			}

			// Delete the attachment
			$deleted = wp_delete_attachment( $attachment_id, true );

			if ( $deleted ) {
				++$deleted_count;
				$results[] = array(
					'id'     => $attachment_id,
					'status' => 'deleted',
					'reason' => 'Successfully removed',
				);
			} else {
				$results[] = array(
					'id'     => $attachment_id,
					'status' => 'error',
					'reason' => 'Failed to delete',
				);
			}
		}

		wp_send_json_success(
			array(
				'deleted_count' => $deleted_count,
				'results'       => $results,
			)
		);
	}
}

// Initialize media cleanup
MSH_Media_Cleanup::get_instance();
