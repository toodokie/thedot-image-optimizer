<?php
/**
 * MSH Hash Cache Manager
 *
 * Manages MD5 hash caching for duplicate detection
 * Handles filesize metadata updates and dependency checks
 *
 * @package MSH_Media_Optimization
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'MSH_Perceptual_Hash' ) ) {
	$perceptual_hash_path = __DIR__ . '/class-msh-perceptual-hash.php';
	if ( file_exists( $perceptual_hash_path ) ) {
		require_once $perceptual_hash_path;
	}
}

class MSH_Hash_Cache_Manager {

	/**
	 * Meta key for storing file hash
	 */
	const HASH_META_KEY     = '_msh_file_hash';
	const HASH_TIME_KEY     = '_msh_hash_time';
	const FILE_MODIFIED_KEY = '_msh_file_modified';

	/**
	 * @var MSH_Perceptual_Hash|null
	 */
	private $perceptual_manager = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Reserved for future dependency injection or hooks.
	}

	/**
	 * Get or compute file hash with caching
	 *
	 * @param int  $attachment_id WordPress attachment ID.
	 * @param bool $force_refresh  Force recalculation of hash.
	 * @return string|false MD5 hash or false on failure.
	 */
	public function get_file_hash( $attachment_id, $force_refresh = false ) {
		if ( ! $attachment_id || ! is_numeric( $attachment_id ) ) {
			error_log( 'MSH Hash Cache: Invalid attachment ID provided' );
			return false;
		}

		if ( ! $force_refresh ) {
			$cached_hash   = get_post_meta( $attachment_id, self::HASH_META_KEY, true );
			$cached_time   = get_post_meta( $attachment_id, self::HASH_TIME_KEY, true );
			$file_modified = get_post_meta( $attachment_id, self::FILE_MODIFIED_KEY, true );
			$current_file  = get_attached_file( $attachment_id );

			if ( $cached_hash && $current_file && file_exists( $current_file ) ) {
				$current_modified = filemtime( $current_file );

				if ( false === $current_modified ) {
					error_log( "MSH Hash Cache: Unable to read filemtime for attachment {$attachment_id}" );
				}

				if ( $current_modified && $current_modified == $file_modified ) {
					return $cached_hash;
				}
			}
		}

		return $this->compute_and_cache_hash( $attachment_id );
	}

	/**
	 * Compute and cache file hash
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	private function compute_and_cache_hash( $attachment_id ) {
		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			error_log( "MSH Hash Cache: File not found for attachment {$attachment_id} - {$file}" );
			return false;
		}

		$max_size = 100 * 1024 * 1024; // 100MB limit for hashing.
		$filesize = filesize( $file );

		if ( false === $filesize ) {
			error_log( "MSH Hash Cache: Unable to read filesize for attachment {$attachment_id}" );
			return false;
		}

		if ( $filesize > $max_size ) {
			error_log( "MSH Hash Cache: File too large for hashing - {$filesize} bytes" );
			return false;
		}

		$hash = md5_file( $file );

		if ( false === $hash ) {
			error_log( "MSH Hash Cache: Failed to compute hash for attachment {$attachment_id}" );
			return false;
		}

		$modified = filemtime( $file );
		if ( false === $modified ) {
			error_log( "MSH Hash Cache: Unable to read filemtime for attachment {$attachment_id}" );
			$modified = time();
		}

		update_post_meta( $attachment_id, self::HASH_META_KEY, $hash );
		update_post_meta( $attachment_id, self::HASH_TIME_KEY, time() );
		update_post_meta( $attachment_id, self::FILE_MODIFIED_KEY, $modified );

		$this->invalidate_perceptual_cache( $attachment_id );

		$this->ensure_filesize_meta( $attachment_id, $filesize );

		error_log( 'MSH Hash Cache: Generated hash for attachment ' . $attachment_id . ': ' . substr( $hash, 0, 8 ) . '...' );

		return $hash;
	}

	/**
	 * Ensure filesize exists in attachment metadata
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $filesize      Main file size in bytes.
	 */
	private function ensure_filesize_meta( $attachment_id, $filesize ) {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) ) {
			error_log( "MSH Hash Cache: Attachment {$attachment_id} metadata missing; skipping filesize enrichment." );
			return;
		}

		$updated = false;

		if ( ! isset( $metadata['filesize'] ) || empty( $metadata['filesize'] ) ) {
			$metadata['filesize'] = $filesize;
			$updated              = true;
			error_log( "MSH Hash Cache: Added main filesize for attachment {$attachment_id}: {$filesize} bytes" );
		}

		if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$base_path = dirname( get_attached_file( $attachment_id ) );

			foreach ( $metadata['sizes'] as $size_name => &$size_data ) {
				if ( isset( $size_data['filesize'] ) && $size_data['filesize'] ) {
					continue;
				}

				$variant_file = isset( $size_data['file'] ) ? $base_path . '/' . $size_data['file'] : '';

				if ( $variant_file && file_exists( $variant_file ) ) {
					$variant_size = filesize( $variant_file );
					if ( false !== $variant_size ) {
						$size_data['filesize'] = $variant_size;
						$updated               = true;
						error_log( "MSH Hash Cache: Added filesize for {$size_name} variant: {$variant_size} bytes" );
					}
				}
			}
		}

		if ( $updated ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	/**
	 * Bulk hash generation for multiple attachments
	 *
	 * @param array         $attachment_ids   Array of attachment IDs.
	 * @param callable|null $progress_callback Optional callback for progress updates.
	 * @return array
	 */
	public function bulk_generate_hashes( $attachment_ids, $progress_callback = null ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'skipped' => 0,
			'hashes'  => array(),
		);

		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return $results;
		}

		$total      = count( $attachment_ids );
		$current    = 0;
		$start_time = microtime( true );

		foreach ( $attachment_ids as $attachment_id ) {
			++$current;

			if ( is_callable( $progress_callback ) ) {
				call_user_func( $progress_callback, $current, $total, $attachment_id );
			}

			$existing_hash = get_post_meta( $attachment_id, self::HASH_META_KEY, true );
			if ( $existing_hash ) {
				++$results['skipped'];
				$results['hashes'][ $attachment_id ] = $existing_hash;
				continue;
			}

			$hash = $this->get_file_hash( $attachment_id );

			if ( $hash ) {
				++$results['success'];
				$results['hashes'][ $attachment_id ] = $hash;
			} else {
				++$results['failed'];
			}

			if ( $current % 25 === 0 ) {
				$elapsed = microtime( true ) - $start_time;
				if ( $elapsed > 20 ) {
					error_log( "MSH Hash Cache: Stopping bulk generation at {$current}/{$total} to prevent timeout" );
					break;
				}

				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 30 );
				}
			}
		}

		error_log( "MSH Hash Cache: Bulk generation complete - Success: {$results['success']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}" );

		return $results;
	}

	/**
	 * Get all cached hashes from database
	 *
	 * @return array Associative array of attachment_id => hash.
	 */
	public function get_all_cached_hashes() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id AS attachment_id, meta_value AS hash FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
				self::HASH_META_KEY
			)
		);

		$hashes = array();

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$hashes[ (int) $row->attachment_id ] = $row->hash;
			}
		}

		return $hashes;
	}

	/**
	 * Find duplicate hashes in cached data
	 *
	 * @return array Array of duplicate groups.
	 */
	public function find_duplicate_hashes() {
		global $wpdb;

		$duplicates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value AS hash, GROUP_CONCAT(post_id) AS attachment_ids, COUNT(*) AS count
                 FROM {$wpdb->postmeta}
                 WHERE meta_key = %s AND meta_value <> ''
                 GROUP BY meta_value HAVING count > 1 ORDER BY count DESC",
				self::HASH_META_KEY
			)
		);

		$groups = array();

		if ( $duplicates ) {
			foreach ( $duplicates as $duplicate ) {
				$groups[] = array(
					'hash'           => $duplicate->hash,
					'count'          => (int) $duplicate->count,
					'attachment_ids' => array_map( 'intval', explode( ',', $duplicate->attachment_ids ) ),
				);
			}
		}

		return $groups;
	}

	/**
	 * Clear hash cache for specific attachment
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function clear_hash_cache( $attachment_id ) {
		delete_post_meta( $attachment_id, self::HASH_META_KEY );
		delete_post_meta( $attachment_id, self::HASH_TIME_KEY );
		delete_post_meta( $attachment_id, self::FILE_MODIFIED_KEY );

		$this->invalidate_perceptual_cache( $attachment_id );

		error_log( "MSH Hash Cache: Cleared cache for attachment {$attachment_id}" );
	}

	/**
	 * Clear all hash caches
	 *
	 * @return int Number of cleared entries.
	 */
	public function clear_all_caches() {
		global $wpdb;

		$keys = array(
			self::HASH_META_KEY,
			self::HASH_TIME_KEY,
			self::FILE_MODIFIED_KEY,
		);

		if ( class_exists( 'MSH_Perceptual_Hash' ) ) {
			$keys = array_merge(
				$keys,
				array(
					MSH_Perceptual_Hash::META_HASH,
					MSH_Perceptual_Hash::META_TIME,
					MSH_Perceptual_Hash::META_MODIFIED,
					MSH_Perceptual_Hash::META_STATUS,
				)
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$deleted      = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
				...$keys
			)
		);

		error_log( "MSH Hash Cache: Cleared all caches - {$deleted} entries removed" );

		return (int) $deleted;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Statistics array.
	 */
	public function get_cache_stats() {
		global $wpdb;

		$total_images = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		$cached_hashes = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''",
				self::HASH_META_KEY
			)
		);

		$duplicate_groups = $this->find_duplicate_hashes();
		$total_duplicates = 0;

		if ( $duplicate_groups ) {
			foreach ( $duplicate_groups as $group ) {
				$total_duplicates += max( 0, $group['count'] - 1 );
			}
		}

		$coverage_percent = $total_images > 0 ? round( ( $cached_hashes / $total_images ) * 100, 2 ) : 0;

		return array(
			'total_images'     => $total_images,
			'cached_hashes'    => $cached_hashes,
			'coverage_percent' => $coverage_percent,
			'duplicate_groups' => count( $duplicate_groups ),
			'total_duplicates' => $total_duplicates,
		);
	}

	/**
	 * Check for required dependencies
	 *
	 * @return array Dependency status.
	 */
	public static function check_dependencies() {
		$deps = array(
			'gd'              => extension_loaded( 'gd' ),
			'imagick'         => extension_loaded( 'imagick' ),
			'imagick_compare' => false,
			'md5_file'        => function_exists( 'md5_file' ),
			'filesize'        => function_exists( 'filesize' ),
		);

		if ( $deps['imagick'] && class_exists( 'Imagick' ) ) {
			$deps['imagick_compare'] = method_exists( 'Imagick', 'compareImages' );
		}

		return $deps;
	}

	/**
	 * Verify hash cache integrity
	 *
	 * @param int $limit Number of entries to verify.
	 * @return array Verification results.
	 */
	public function verify_cache_integrity( $limit = 100 ) {
		global $wpdb;

		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value AS hash FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
				self::HASH_META_KEY,
				$limit
			)
		);

		$results = array(
			'valid'        => 0,
			'invalid'      => 0,
			'missing_file' => 0,
		);

		if ( ! $entries ) {
			return $results;
		}

		foreach ( $entries as $entry ) {
			$file = get_attached_file( $entry->post_id );

			if ( ! $file || ! file_exists( $file ) ) {
				++$results['missing_file'];
				continue;
			}

			$current_hash = md5_file( $file );

			if ( false === $current_hash ) {
				++$results['missing_file'];
				continue;
			}

			if ( $current_hash === $entry->hash ) {
				++$results['valid'];
			} else {
				++$results['invalid'];
				$this->get_file_hash( $entry->post_id, true );
			}
		}

		return $results;
	}

	/**
	 * Lazily retrieve perceptual hash manager.
	 *
	 * @return MSH_Perceptual_Hash|null
	 */
	private function get_perceptual_manager() {
		if ( null === $this->perceptual_manager && class_exists( 'MSH_Perceptual_Hash' ) ) {
			$this->perceptual_manager = MSH_Perceptual_Hash::get_instance();
		}

		return $this->perceptual_manager;
	}

	/**
	 * Clear perceptual cache for attachment when MD5 cache invalidates.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function invalidate_perceptual_cache( $attachment_id ) {
		$manager = $this->get_perceptual_manager();
		if ( $manager ) {
			$manager->clear_cache( $attachment_id );
		}
	}
}
