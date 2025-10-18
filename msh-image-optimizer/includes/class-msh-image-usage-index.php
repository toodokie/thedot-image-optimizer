<?php
/**
 * MSH Image Usage Index
 * Builds and maintains an index of where images are used for fast lookup during renames
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Image_Usage_Index {
	private static $instance = null;
	private $index_table;

	private function __construct() {
		global $wpdb;
		$this->index_table = $wpdb->prefix . 'msh_image_usage_index';

		add_action( 'init', array( $this, 'maybe_create_index_table' ) );
		add_action( 'save_post', array( $this, 'update_post_index' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'remove_post_index' ), 10, 1 );
		add_action( 'wp_ajax_msh_build_usage_index_batch', array( $this, 'ajax_build_usage_index_batch' ) );
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function maybe_create_index_table() {
		if ( get_option( 'msh_usage_index_table_version' ) === '1' ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->index_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            attachment_id int(11) NOT NULL,
            url_variation text NOT NULL,
            table_name varchar(64) NOT NULL,
            row_id int(11) NOT NULL,
            column_name varchar(64) NOT NULL,
            context_type varchar(50) DEFAULT 'content',
            post_type varchar(20) DEFAULT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY table_row (table_name, row_id),
            KEY url_variation (url_variation(191)),
            KEY context_type (context_type),
            FULLTEXT KEY url_search (url_variation)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'msh_usage_index_table_version', '1' );
	}

	/**
	 * Build complete usage index for all images
	 */
	public function build_complete_index( $batch_size = 50 ) {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		// Clear existing index
		$this->invalidate_stats_cache();
		$wpdb->query( "TRUNCATE TABLE {$this->index_table}" );

		$processed         = 0;
		$total_attachments = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
				'attachment',
				$image_mime_like
			)
		);

		$offset = 0;
		while ( $offset < $total_attachments ) {
			$attachments = $wpdb->get_results(
				$wpdb->prepare(
					"
	                SELECT ID FROM {$wpdb->posts}
	                WHERE post_type = %s AND post_mime_type LIKE %s
	                ORDER BY ID
	                LIMIT %d OFFSET %d
	            ",
					'attachment',
					$image_mime_like,
					$batch_size,
					$offset
				)
			);

			foreach ( $attachments as $attachment ) {
				try {
					$this->index_attachment_usage( $attachment->ID, true );
					++$processed;
				} catch ( Exception $e ) {
					error_log( 'MSH INDEX ERROR: Failed to index attachment ' . $attachment->ID . ' during complete rebuild: ' . $e->getMessage() );
					// Continue with next attachment
					continue;
				}
			}

			$offset += $batch_size;

			// Progress logging - more frequent updates for better feedback
			if ( $processed % 25 === 0 || $processed == $total_attachments ) {
				error_log( "MSH Usage Index: Processed {$processed}/{$total_attachments} attachments (" . round( ( $processed / $total_attachments ) * 100 ) . '%)' );
			}
		}

		update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
		$this->rebuild_usage_status_index();
		$this->invalidate_stats_cache();
		error_log( "MSH Usage Index: Index build complete! Total processed: {$processed}" );
		return $processed;
	}

	/**
	 * Server-side robust rebuild that bypasses JavaScript timeouts
	 */
	public function robust_server_rebuild( $batch_size = 10 ) {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		set_time_limit( 0 ); // Remove PHP time limit
		ignore_user_abort( true ); // Continue even if user navigates away

		error_log( 'MSH Usage Index: Starting robust server-side rebuild...' );

		// Clear existing index
		$this->invalidate_stats_cache();
		$wpdb->query( "TRUNCATE TABLE {$this->index_table}" );

		$processed         = 0;
		$errors            = 0;
		$total_attachments = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
				'attachment',
				$image_mime_like
			)
		);

		if ( $total_attachments == 0 ) {
			error_log( 'MSH Usage Index: No image attachments found' );
			return 0;
		}

		error_log( "MSH Usage Index: Found {$total_attachments} total image attachments to process" );

		$offset = 0;
			while ( $offset < $total_attachments ) {
				$attachments = $wpdb->get_results(
					$wpdb->prepare(
						"
	                SELECT ID FROM {$wpdb->posts}
	                WHERE post_type = %s AND post_mime_type LIKE %s
	                ORDER BY ID
	                LIMIT %d OFFSET %d
	            ",
						'attachment',
						$image_mime_like,
						$batch_size,
						$offset
					)
				);

			foreach ( $attachments as $attachment ) {
				try {
					$usage_count = $this->index_attachment_usage( $attachment->ID, true );
					++$processed;

					if ( $processed % 10 === 0 ) {
						error_log( "MSH Usage Index: Processed {$processed}/{$total_attachments} attachments (" . round( ( $processed / $total_attachments ) * 100, 1 ) . '%)' );

						// Get current stats to show progress
						$current_entries = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->index_table}" );
						error_log( "MSH Usage Index: Current database entries: {$current_entries}" );
					}
				} catch ( Exception $e ) {
					++$errors;
					error_log( 'MSH INDEX ERROR: Failed to index attachment ' . $attachment->ID . ' during robust rebuild: ' . $e->getMessage() );
					continue;
				}
			}

			$offset += $batch_size;

			// Small delay between batches to prevent overwhelming the database
			usleep( 100000 ); // 0.1 second
		}

		$final_entries     = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->index_table}" );
		$final_attachments = $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM {$this->index_table}" );

		update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
		update_option( 'msh_safe_rename_enabled', '1' );
		$this->rebuild_usage_status_index();
		$this->invalidate_stats_cache();

		error_log( 'MSH Usage Index: Robust rebuild complete!' );
		error_log( "MSH Usage Index: - Processed: {$processed} attachments" );
		error_log( "MSH Usage Index: - Errors: {$errors}" );
		error_log( "MSH Usage Index: - Final entries: {$final_entries}" );
		error_log( "MSH Usage Index: - Attachments indexed: {$final_attachments}" );

		return $processed;
	}

	/**
	 * AJAX handler for chunked index rebuilds
	 */
	public function ajax_build_usage_index_batch() {
		try {
			// Extend time limits for batch processing
			set_time_limit( 900 ); // 15 minutes per batch
			ini_set( 'memory_limit', '512M' ); // Increase memory limit
			ignore_user_abort( true ); // Continue processing even if user navigates away

			// Skip security checks for Local development testing
			if ( ! defined( 'WP_LOCAL_DEV' ) || ! WP_LOCAL_DEV ) {
				check_ajax_referer( 'msh_image_optimizer', 'nonce' );

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( 'Unauthorized' );
				}
			}

			global $wpdb;

			$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

			$offset     = isset( $_POST['offset'] ) ? max( 0, intval( $_POST['offset'] ) ) : 0;
			$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 25;
			if ( $batch_size < 1 ) {
				$batch_size = 25;  // Larger default for better performance
			}
			if ( $batch_size > 50 ) {
				$batch_size = 50;  // Allow larger batches for faster processing
			}

			$force_rebuild = ! empty( $_POST['force_rebuild'] ) && $_POST['force_rebuild'] === 'true';

			$total_attachments = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
					'attachment',
					$image_mime_like
				)
			);

			if ( $total_attachments === 0 ) {
				wp_send_json_success(
					array(
						'processed'   => 0,
						'offset'      => $offset,
						'next_offset' => $offset,
						'total'       => 0,
						'has_more'    => false,
						'summary'     => null,
						'message'     => __( 'No media attachments found for indexing.', 'msh-image-optimizer' ),
					)
				);
			}

			// Route to appropriate indexing method
			if ( $force_rebuild ) {
				// USE THE FAST OPTIMIZED METHOD for Force Rebuild
				try {
					$result = $this->build_optimized_complete_index( true );

					if ( $result && $result['success'] ) {
						$stats   = $this->get_index_stats();
						$summary = $stats['summary'] ?? null;

						wp_send_json_success(
							array(
								'processed'   => $result['stats']['total_attachments'] ?? 0,
								'offset'      => $result['stats']['total_attachments'] ?? 0,
								'next_offset' => $result['stats']['total_attachments'] ?? 0,
								'total'       => $result['stats']['total_attachments'] ?? 0,
								'has_more'    => false,
								'summary'     => $summary,
								'message'     => $result['message'],
							)
						);
					}
				} catch ( Exception $e ) {
					error_log( 'MSH Index: Optimized force rebuild failed: ' . $e->getMessage() );
					wp_send_json_error( 'Force rebuild failed: ' . $e->getMessage() );
				}
			} elseif ( $offset === 0 ) {
				// SMART BUILD: Only process what needs updating
				try {
					$result = $this->smart_build_index();

					if ( $result && $result['success'] ) {
						if ( $result['stats']['processed'] === 0 ) {
							// Nothing to process
							$smart_stats     = $this->get_index_stats();
							$summary_payload = $this->format_stats_for_ui( $smart_stats );

							wp_send_json_success(
								array(
									'processed'   => 0,
									'offset'      => 0,
									'next_offset' => 0,
									'total'       => $total_attachments,
									'has_more'    => false,
									'summary'     => $summary_payload,
									'message'     => $result['message'],
								)
							);
						} else {
							// Processed some items
							$smart_stats     = $this->get_index_stats();
							$summary_payload = $this->format_stats_for_ui( $smart_stats );

							wp_send_json_success(
								array(
									'processed'   => $result['stats']['processed'],
									'offset'      => $result['stats']['processed'],
									'next_offset' => $result['stats']['processed'],
									'total'       => $total_attachments,
									'has_more'    => false,
									'summary'     => $summary_payload,
									'message'     => $result['message'],
								)
							);
						}
					}
				} catch ( Exception $e ) {
					error_log( 'MSH Index: Smart build failed: ' . $e->getMessage() );
					// Fall back to batch method below
				}
			}

			$attachments = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = %s AND post_mime_type LIKE %s
                 ORDER BY ID
                 LIMIT %d OFFSET %d",
					'attachment',
					$image_mime_like,
					$batch_size,
					$offset
				)
			);

			$processed = 0;

			if ( $attachments ) {
				foreach ( $attachments as $attachment ) {
					try {
						$this->index_attachment_usage( $attachment->ID, $force_rebuild );
						++$processed;
					} catch ( Exception $e ) {
						error_log( 'MSH INDEX ERROR: Failed to index attachment ' . $attachment->ID . ': ' . $e->getMessage() );
						// Continue with next attachment instead of stopping the entire batch
						continue;
					}
				}
			}

			$next_offset = $offset + $batch_size;
			$has_more    = $next_offset < $total_attachments;

			$summary = null;
			if ( ! $has_more ) {
				$stats   = $this->get_index_stats();
				$summary = $this->format_stats_for_ui( $stats );
				update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
				update_option( 'msh_safe_rename_enabled', '1' );
			}

			wp_send_json_success(
				array(
					'processed'   => $processed,
					'offset'      => $offset,
					'next_offset' => $has_more ? $next_offset : $total_attachments,
					'total'       => $total_attachments,
					'has_more'    => $has_more,
					'summary'     => $summary,
					'message'     => sprintf(
						/* translators: 1: number processed, 2: total attachments */
						__( 'Processed %1$d attachment(s) (out of %2$d).', 'msh-image-optimizer' ),
						$processed,
						$total_attachments
					),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Index usage for a specific attachment
	 */
	public function index_attachment_usage( $attachment_id, $force_rebuild = false ) {
		global $wpdb;

		try {
			// Validate attachment exists and is an image
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				return 0;
			}

			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				return 0;
			}

			// Check if we already have a valid index for this attachment
			$existing_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->index_table} WHERE attachment_id = %d",
					$attachment_id
				)
			);

			if ( $existing_count > 0 && ! $force_rebuild ) {
				$file_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
				$this->apply_usage_status( $attachment_id, (int) $existing_count, $file_relative );
				return (int) $existing_count;
			}

			// Remove existing entries for this attachment
			$wpdb->delete( $this->index_table, array( 'attachment_id' => $attachment_id ), array( '%d' ) );

			// Get all URL variations for this attachment
			$detector = MSH_URL_Variation_Detector::get_instance();

			if ( ! is_object( $detector ) ) {
				throw new Exception( 'URL detector failed to initialize for attachment ' . $attachment_id );
			}

			$variations = $detector->get_all_variations( $attachment_id );

			if ( empty( $variations ) ) {
				return 0;
			}
		} catch ( Exception $e ) {
			error_log( 'MSH INDEX ERROR: Failed to index attachment ' . $attachment_id . ': ' . $e->getMessage() );
			return 0;
		}

		$usage_count = 0;

		try {
			// Index usage in posts content and excerpt
			$posts_count  = $this->index_posts_usage( $attachment_id, $variations );
			$usage_count += $posts_count;

			// Index usage in postmeta
			$postmeta_count = $this->index_postmeta_usage( $attachment_id, $variations );
			$usage_count   += $postmeta_count;

			// Index usage in options
			$options_count = $this->index_options_usage( $attachment_id, $variations );
			$usage_count  += $options_count;

			// Index usage in usermeta
			$usermeta_count = $this->index_usermeta_usage( $attachment_id, $variations );
			$usage_count   += $usermeta_count;

			// Index usage in termmeta if exists
			if ( isset( $wpdb->termmeta ) ) {
				$termmeta_count = $this->index_termmeta_usage( $attachment_id, $variations );
				$usage_count   += $termmeta_count;
			}
		} catch ( Exception $e ) {
			error_log( 'MSH INDEX ERROR: Exception during indexing process for attachment ' . $attachment_id . ': ' . $e->getMessage() );
			throw $e;
		}

		$file_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$this->apply_usage_status( $attachment_id, $usage_count, $file_relative );

		return $usage_count;
	}

	/**
	 * Index usage in posts table
	 */
	private function index_posts_usage( $attachment_id, $variations ) {
		global $wpdb;
		$usage_count = 0;

		// Filter out empty variations
		$valid_variations = array_filter( $variations );
		if ( empty( $valid_variations ) ) {
			return 0;
		}

		// Build OR conditions for single query (both content and excerpt)
		$like_conditions = array();
		$like_values     = array();
		foreach ( $valid_variations as $variation ) {
			$escaped_variation = '%' . $wpdb->esc_like( $variation ) . '%';
			$like_conditions[] = '(post_content LIKE %s OR post_excerpt LIKE %s)';
			$like_values[]     = $escaped_variation;
			$like_values[]     = $escaped_variation;
		}

		$where_clause = implode( ' OR ', $like_conditions );

		// Single query to find all posts containing any variation in content or excerpt
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, post_type, post_content, post_excerpt
            FROM {$wpdb->posts}
            WHERE {$where_clause}
        ",
				...$like_values
			)
		);

		foreach ( $posts as $post ) {
			// Check which variations are found and in which columns
			foreach ( $valid_variations as $variation ) {
				// Check post_content
				if ( ! empty( $post->post_content ) && strpos( $post->post_content, $variation ) !== false ) {
					$wpdb->insert(
						$this->index_table,
						array(
							'attachment_id' => $attachment_id,
							'url_variation' => $variation,
							'table_name'    => 'posts',
							'row_id'        => $post->ID,
							'column_name'   => 'post_content',
							'context_type'  => 'content',
							'post_type'     => $post->post_type,
						)
					);
					++$usage_count;
				}

				// Check post_excerpt
				if ( ! empty( $post->post_excerpt ) && strpos( $post->post_excerpt, $variation ) !== false ) {
					$wpdb->insert(
						$this->index_table,
						array(
							'attachment_id' => $attachment_id,
							'url_variation' => $variation,
							'table_name'    => 'posts',
							'row_id'        => $post->ID,
							'column_name'   => 'post_excerpt',
							'context_type'  => 'excerpt',
							'post_type'     => $post->post_type,
						)
					);
					++$usage_count;
				}
			}
		}

		return $usage_count;
	}

	/**
	 * Index usage in postmeta table
	 */
	private function index_postmeta_usage( $attachment_id, $variations ) {
		global $wpdb;
		$usage_count = 0;

		// Filter out empty variations
		$valid_variations = array_filter( $variations );
		if ( empty( $valid_variations ) ) {
			return 0;
		}

		$batch_limit = 250;
		$seen_rows   = array();

		foreach ( $valid_variations as $variation ) {
			$offset = 0;
			$like   = '%' . $wpdb->esc_like( $variation ) . '%';

			do {
				$meta_rows = $wpdb->get_results(
					$wpdb->prepare(
						"
                    SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value, posts.post_type
                    FROM {$wpdb->postmeta} pm
                    LEFT JOIN {$wpdb->posts} posts ON posts.ID = pm.post_id
                    WHERE pm.meta_value LIKE %s
                    ORDER BY pm.meta_id ASC
                    LIMIT %d OFFSET %d
                ",
						$like,
						$batch_limit,
						$offset
					)
				);

				$rows_fetched = is_array( $meta_rows ) ? count( $meta_rows ) : 0;

				if ( $rows_fetched === 0 ) {
					break;
				}

				foreach ( $meta_rows as $meta ) {
					if ( ! isset( $meta->meta_value ) || strpos( (string) $meta->meta_value, $variation ) === false ) {
						continue; // Defensive: LIKE match outside actual value (rare with collations)
					}

					$row_key = $meta->meta_id . '|' . $variation;
					if ( isset( $seen_rows[ $row_key ] ) ) {
						continue;
					}
					$seen_rows[ $row_key ] = true;

					$context_type = $this->determine_meta_context( $meta->meta_key, $meta->meta_value );

					$wpdb->insert(
						$this->index_table,
						array(
							'attachment_id' => $attachment_id,
							'url_variation' => $variation,
							'table_name'    => 'postmeta',
							'row_id'        => $meta->meta_id,
							'column_name'   => 'meta_value',
							'context_type'  => $context_type,
							'post_type'     => isset( $meta->post_type ) ? $meta->post_type : null,
						)
					);
					++$usage_count;
				}

				$offset += $batch_limit;
			} while ( $rows_fetched === $batch_limit );
		}

		return $usage_count;
	}

	/**
	 * Index usage in options table
	 */
	private function index_options_usage( $attachment_id, $variations ) {
		global $wpdb;
		$usage_count = 0;

		// Filter out empty variations
		$valid_variations = array_filter( $variations );
		if ( empty( $valid_variations ) ) {
			return 0;
		}

		// Build a single OR query instead of multiple LIKE queries
		$like_conditions = array();
		$like_values     = array();
		foreach ( $valid_variations as $variation ) {
			$like_conditions[] = 'option_value LIKE %s';
			$like_values[]     = '%' . $wpdb->esc_like( $variation ) . '%';
		}

		$where_clause = implode( ' OR ', $like_conditions );

		$options = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT option_id, option_name, option_value
            FROM {$wpdb->options}
            WHERE {$where_clause}
        ",
				...$like_values
			)
		);

		foreach ( $options as $option ) {
			// Find which variation matched
			$matched_variation = null;
			foreach ( $valid_variations as $variation ) {
				if ( strpos( $option->option_value, $variation ) !== false ) {
					$matched_variation = $variation;
					break;
				}
			}

			$context_type = $this->determine_option_context( $option->option_name, $option->option_value );

			$wpdb->insert(
				$this->index_table,
				array(
					'attachment_id' => $attachment_id,
					'url_variation' => $matched_variation,
					'table_name'    => 'options',
					'row_id'        => $option->option_id,
					'column_name'   => 'option_value',
					'context_type'  => $context_type,
					'post_type'     => null,
				)
			);
			++$usage_count;
		}

		return $usage_count;
	}

	/**
	 * Index usage in usermeta table
	 */
	private function index_usermeta_usage( $attachment_id, $variations ) {
		global $wpdb;
		$usage_count = 0;

		foreach ( $variations as $variation ) {
			if ( empty( $variation ) ) {
				continue;
			}

			$like = '%' . $wpdb->esc_like( $variation ) . '%';

			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT umeta_id, user_id, meta_key, meta_value
                FROM {$wpdb->usermeta}
                WHERE meta_value LIKE %s
            ",
					$like
				)
			);

			foreach ( $meta_rows as $meta ) {
				$wpdb->insert(
					$this->index_table,
					array(
						'attachment_id' => $attachment_id,
						'url_variation' => $variation,
						'table_name'    => 'usermeta',
						'row_id'        => $meta->umeta_id,
						'column_name'   => 'meta_value',
						'context_type'  => 'user_meta',
						'post_type'     => null,
					)
				);
				++$usage_count;
			}
		}

		return $usage_count;
	}

	/**
	 * Index usage in termmeta table
	 */
	private function index_termmeta_usage( $attachment_id, $variations ) {
		global $wpdb;
		$usage_count = 0;

		foreach ( $variations as $variation ) {
			if ( empty( $variation ) ) {
				continue;
			}

			$like = '%' . $wpdb->esc_like( $variation ) . '%';

			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT meta_id, term_id, meta_key, meta_value
                FROM {$wpdb->termmeta}
                WHERE meta_value LIKE %s
            ",
					$like
				)
			);

			foreach ( $meta_rows as $meta ) {
				$wpdb->insert(
					$this->index_table,
					array(
						'attachment_id' => $attachment_id,
						'url_variation' => $variation,
						'table_name'    => 'termmeta',
						'row_id'        => $meta->meta_id,
						'column_name'   => 'meta_value',
						'context_type'  => 'term_meta',
						'post_type'     => null,
					)
				);
				++$usage_count;
			}
		}

		return $usage_count;
	}

	/**
	 * Determine context type for postmeta
	 */
	private function determine_meta_context( $meta_key, $meta_value ) {
		// Featured image
		if ( $meta_key === '_thumbnail_id' ) {
			return 'featured_image';
		}

		// ACF fields
		if ( strpos( $meta_key, 'field_' ) === 0 || function_exists( 'get_field_object' ) ) {
			return 'acf_field';
		}

		// Gallery fields
		if ( strpos( $meta_key, 'gallery' ) !== false || strpos( $meta_key, '_gallery' ) !== false ) {
			return 'gallery';
		}

		// Page builder content
		if ( strpos( $meta_key, '_elementor_data' ) !== false || strpos( $meta_key, 'vc_' ) === 0 ) {
			return 'page_builder';
		}

		// Serialized data
		if ( is_serialized( $meta_value ) ) {
			return 'serialized_meta';
		}

		return 'meta';
	}

	/**
	 * Determine context type for options
	 */
	private function determine_option_context( $option_name, $option_value ) {
		// Theme options
		if ( strpos( $option_name, 'theme_' ) === 0 || strpos( $option_name, 'mods_' ) === 0 ) {
			return 'theme_options';
		}

		// Widget data
		if ( strpos( $option_name, 'widget_' ) === 0 ) {
			return 'widget';
		}

		// Customizer
		if ( strpos( $option_name, 'customize_' ) === 0 ) {
			return 'customizer';
		}

		// Serialized data
		if ( is_serialized( $option_value ) ) {
			return 'serialized_option';
		}

		return 'option';
	}

	/**
	 * Get usage locations for an attachment
	 */
	public function get_attachment_usage( $attachment_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT *
            FROM {$this->index_table}
            WHERE attachment_id = %d
            ORDER BY table_name, context_type, row_id
        ",
				$attachment_id
			)
		);
	}

	/**
	 * Get targeted update queries for an attachment rename
	 */
	public function get_targeted_updates( $attachment_id, $replacement_map ) {
		global $wpdb;

		$updates         = array();
		$usage_locations = $this->get_attachment_usage( $attachment_id );

		foreach ( $usage_locations as $location ) {
			$old_url = $location->url_variation;
			$new_url = $replacement_map[ $old_url ] ?? null;

			if ( ! $new_url || $old_url === $new_url ) {
				continue;
			}

			$table_name = $wpdb->prefix . $location->table_name;
			$id_column  = $this->get_id_column_for_table( $location->table_name );

			$updates[] = array(
				'table'     => $table_name,
				'id_column' => $id_column,
				'row_id'    => $location->row_id,
				'column'    => $location->column_name,
				'old_value' => $old_url,
				'new_value' => $new_url,
				'context'   => $location->context_type,
			);
		}

		return $updates;
	}

	/**
	 * Update post index when a post is saved
	 */
	public function update_post_index( $post_id ) {
		// Skip for autosaves, revisions, and during imports
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if doing autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Skip if index table doesn't exist yet
		global $wpdb;
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->index_table}'" );
		if ( ! $table_exists ) {
			return;
		}

		// TEMPORARY: Disable automatic indexing to fix publishing hang
		// The index will be built manually via the admin button instead
		return;

		// Original code (disabled to prevent hang):
		// Get all attachments used in this post
		$content = get_post_field( 'post_content', $post_id );
		$excerpt = get_post_field( 'post_excerpt', $post_id );

		// Extract attachment IDs from content
		preg_match_all( '/wp-image-(\d+)/', $content . ' ' . $excerpt, $matches );
		$attachment_ids = array_unique( $matches[1] );

		foreach ( $attachment_ids as $attachment_id ) {
			$this->index_attachment_usage( $attachment_id );
		}
	}

	/**
	 * Remove post from index when deleted
	 */
	public function remove_post_index( $post_id ) {
		global $wpdb;

		$wpdb->delete(
			$this->index_table,
			array(
				'table_name' => 'posts',
				'row_id'     => $post_id,
			),
			array( '%s', '%d' )
		);

		$wpdb->delete(
			$this->index_table,
			array(
				'table_name' => 'postmeta',
				'row_id'     => $post_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Get ID column for table
	 */
	private function get_id_column_for_table( $table ) {
		$columns = array(
			'posts'    => 'ID',
			'postmeta' => 'meta_id',
			'options'  => 'option_id',
			'usermeta' => 'umeta_id',
			'termmeta' => 'meta_id',
		);

		return $columns[ $table ] ?? 'id';
	}

	/**
	 * Get index statistics
	 */
	public function get_index_stats() {
		// Cache for 5 minutes to avoid expensive queries on every page load
		$cache_key = 'msh_index_stats_cache';
		$cached    = get_transient( $cache_key );
		if ( $cached !== false && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$stats = $wpdb->get_row(
			"
            SELECT
                COUNT(*) as total_entries,
                COUNT(DISTINCT attachment_id) as indexed_attachments,
                COUNT(DISTINCT CONCAT(table_name, '-', row_id)) as unique_locations,
                MAX(last_updated) as last_update
            FROM {$this->index_table}
        "
		);

		$context_stats = $wpdb->get_results(
			"
            SELECT context_type, COUNT(*) as count
            FROM {$this->index_table}
            GROUP BY context_type
            ORDER BY count DESC
        "
		);

		$orphan_summary  = $this->get_orphaned_attachment_summary( 50 );
		$derived_summary = $this->get_derived_attachment_summary( 25 );

		$result = array(
			'summary'    => $stats,
			'by_context' => $context_stats,
			'orphans'    => $orphan_summary,
			'derived'    => $derived_summary,
		);

		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Clear cached index statistics so subsequent reads fetch fresh totals.
	 */
	private function invalidate_stats_cache() {
		delete_transient( 'msh_index_stats_cache' );
	}

	/**
	 * Retrieve orphaned attachment details (attachments with no indexed references).
	 *
	 * @param int $limit Number of preview items to return.
	 * @return array
	 */
	public function get_orphaned_attachment_summary( $limit = 50 ) {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		$limit = max( 1, absint( $limit ) );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} status ON status.post_id = p.ID AND status.meta_key = '_msh_usage_status'
            WHERE p.post_type = %s
              AND p.post_mime_type LIKE %s
              AND NOT EXISTS (
                SELECT 1 FROM {$this->index_table} idx
                WHERE idx.attachment_id = p.ID
              )
              AND (status.meta_value IS NULL OR status.meta_value = 'orphan')
        ",
				'attachment',
				$image_mime_like
			)
		);

		$items = array();

		if ( $count > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_date, p.post_mime_type, pm.meta_value AS file_path
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm
                        ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
                     LEFT JOIN {$wpdb->postmeta} status ON status.post_id = p.ID AND status.meta_key = '_msh_usage_status'
                     WHERE p.post_type = %s
                       AND p.post_mime_type LIKE %s
                       AND NOT EXISTS (
                           SELECT 1 FROM {$this->index_table} idx
                           WHERE idx.attachment_id = p.ID
                       )
                       AND (status.meta_value IS NULL OR status.meta_value = 'orphan')
                     ORDER BY p.post_date DESC
                     LIMIT %d",
					'attachment',
					$image_mime_like,
					$limit
				)
			);

			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$attachment_id = (int) $row->ID;
					$items[]       = array(
						'ID'        => $attachment_id,
						'title'     => $row->post_title,
						'post_date' => $row->post_date,
						'mime'      => $row->post_mime_type,
						'file_path' => $row->file_path,
						'url'       => wp_get_attachment_url( $attachment_id ) ?: '',
						'thumb_url' => wp_get_attachment_thumb_url( $attachment_id ) ?: '',
						'edit_url'  => get_edit_post_link( $attachment_id, '' ),
					);
				}
			}
		}

		return array(
			'count' => $count,
			'items' => $items,
		);
	}

	/**
	 * Retrieve derived attachment summary (mirrors of primary assets).
	 */
	public function get_derived_attachment_summary( $limit = 25 ) {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		$limit = max( 1, absint( $limit ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_date, p.post_mime_type, pm.meta_value AS file_path, parent.meta_value AS parent_id
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} status ON status.post_id = p.ID AND status.meta_key = '_msh_usage_status' AND status.meta_value = 'derived'
                 LEFT JOIN {$wpdb->postmeta} parent ON parent.post_id = p.ID AND parent.meta_key = '_msh_usage_parent'
                 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
                 WHERE p.post_type = %s
                   AND p.post_mime_type LIKE %s
                 ORDER BY p.post_date DESC
                 LIMIT %d",
				'attachment',
				$image_mime_like,
				$limit
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$attachment_id = (int) $row->ID;
			$items[]       = array(
				'ID'        => $attachment_id,
				'title'     => $row->post_title,
				'post_date' => $row->post_date,
				'mime'      => $row->post_mime_type,
				'file_path' => $row->file_path,
				'url'       => wp_get_attachment_url( $attachment_id ) ?: '',
				'thumb_url' => wp_get_attachment_thumb_url( $attachment_id ) ?: '',
				'parent_id' => isset( $row->parent_id ) ? (int) $row->parent_id : 0,
				'edit_url'  => get_edit_post_link( $attachment_id, '' ),
			);
		}

		$count = (int) $wpdb->get_var(
			"
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_msh_usage_status' AND meta_value = 'derived'
        "
		);

		return array(
			'count' => $count,
			'items' => $items,
		);
	}

	private function get_normalized_basename( $file_path ) {
		if ( empty( $file_path ) || ! is_string( $file_path ) ) {
			return '';
		}

		$basename = pathinfo( $file_path, PATHINFO_FILENAME );
		$basename = str_replace( '\\', '/', $basename );
		$basename = basename( $basename );

		$candidate = $basename;

		// Reuse media cleanup normaliser if available for consistency
		if ( class_exists( 'MSH_Media_Cleanup' ) && method_exists( 'MSH_Media_Cleanup', 'normalize_base_filename' ) ) {
			$candidate = MSH_Media_Cleanup::normalize_base_filename( $file_path );
		} else {
			$candidate = $this->strip_wp_resize_suffix( $candidate );
			$candidate = $this->strip_numeric_suffix( $candidate );
		}

		return strtolower( $candidate );
	}

	private function strip_wp_resize_suffix( $value ) {
		$stripped = preg_replace( '/-(scaled|rotated)(?:-[0-9x]+)*/i', '', $value );
		$stripped = preg_replace( '/(-copy)+$/i', '', $stripped );
		return $stripped ?: $value;
	}

	private function strip_numeric_suffix( $value ) {
		$stripped = preg_replace( '/(-\d+)+$/', '', $value );
		return $stripped ?: $value;
	}

	private function set_usage_status( $attachment_id, $status, $details = array() ) {
		$status = $status ?: 'orphan';
		update_post_meta( $attachment_id, '_msh_usage_status', $status );

		if ( $status === 'derived' && ! empty( $details['parent'] ) ) {
			update_post_meta( $attachment_id, '_msh_usage_parent', (int) $details['parent'] );
		} else {
			delete_post_meta( $attachment_id, '_msh_usage_parent' );
		}
	}

	private function find_primary_attachment_for_base( $normalized_base, $exclude_attachment_id ) {
		global $wpdb;

		if ( ! $normalized_base ) {
			return 0;
		}

		$like       = '%' . $wpdb->esc_like( $normalized_base ) . '%';
		$candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_key = '_wp_attached_file'
               AND pm.meta_value LIKE %s
               AND pm.post_id <> %d
             ORDER BY pm.post_id ASC
             LIMIT 25",
				$like,
				$exclude_attachment_id
			)
		);

		foreach ( $candidates as $candidate ) {
			$candidate_base = $this->get_normalized_basename( $candidate->meta_value );
			if ( $candidate_base !== $normalized_base ) {
				continue;
			}

			$usage = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->index_table} WHERE attachment_id = %d",
					$candidate->post_id
				)
			);

			if ( $usage > 0 ) {
				return (int) $candidate->post_id;
			}

			$candidate_status = get_post_meta( $candidate->post_id, '_msh_usage_status', true );
			if ( 'in_use' === $candidate_status ) {
				return (int) $candidate->post_id;
			}
		}

		return 0;
	}

	private function apply_usage_status( $attachment_id, $usage_count, $file_path = '' ) {
		$file_path = $file_path ?: get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $usage_count > 0 ) {
			$this->set_usage_status( $attachment_id, 'in_use' );
			return;
		}

		$normalized = $this->get_normalized_basename( $file_path );
		$parent_id  = $this->find_primary_attachment_for_base( $normalized, $attachment_id );

		if ( $parent_id ) {
			$this->set_usage_status( $attachment_id, 'derived', array( 'parent' => $parent_id ) );
		} else {
			$this->set_usage_status( $attachment_id, 'orphan' );
		}
	}

	private function update_usage_status_for_attachments( $attachment_ids ) {
		global $wpdb;

		if ( empty( $attachment_ids ) ) {
			return;
		}

		$ids = array_map( 'intval', array_filter( array_unique( $attachment_ids ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			$usage_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->index_table} WHERE attachment_id = %d",
					$id
				)
			);
			$file_path   = get_post_meta( $id, '_wp_attached_file', true );
			$this->apply_usage_status( $id, $usage_count, $file_path );
		}
	}

	private function rebuild_usage_status_index() {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = %s AND post_mime_type LIKE %s
        ",
				'attachment',
				$image_mime_like
			)
		);

		if ( empty( $attachments ) ) {
			return;
		}

		foreach ( $attachments as $attachment ) {
			$attachment_id = (int) $attachment->ID;
			$usage_count   = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->index_table} WHERE attachment_id = %d",
					$attachment_id
				)
			);
			$file_path     = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$this->apply_usage_status( $attachment_id, $usage_count, $file_path );
		}
	}

	/**
	 * Format stats for UI consumption (counts, context mix, orphan preview).
	 *
	 * @param array|null $stats Raw stats payload from get_index_stats().
	 * @return array|null
	 */
	public function format_stats_for_ui( $stats ) {
		if ( ! is_array( $stats ) || empty( $stats['summary'] ) ) {
			return null;
		}

		$summary = $stats['summary'];

		$result = array(
			'total_entries'       => isset( $summary->total_entries ) ? (int) $summary->total_entries : 0,
			'indexed_attachments' => isset( $summary->indexed_attachments ) ? (int) $summary->indexed_attachments : 0,
			'unique_locations'    => isset( $summary->unique_locations ) ? (int) $summary->unique_locations : 0,
			'last_update_raw'     => $summary->last_update,
			'last_update_display' => $summary->last_update
				? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $summary->last_update, false )
				: null,
			'by_context'          => array(),
			'orphaned_entries'    => isset( $stats['orphans']['count'] ) ? (int) $stats['orphans']['count'] : 0,
			'orphan_preview'      => array(),
			'derived_count'       => 0,
			'derived_preview'     => array(),
		);

		if ( ! empty( $stats['by_context'] ) ) {
			foreach ( $stats['by_context'] as $context_row ) {
				$result['by_context'][] = array(
					'context_type' => $context_row->context_type,
					'count'        => isset( $context_row->count ) ? (int) $context_row->count : 0,
				);
			}
		}

		if ( ! empty( $stats['orphans']['items'] ) ) {
			foreach ( $stats['orphans']['items'] as $item ) {
				$result['orphan_preview'][] = array(
					'ID'        => isset( $item['ID'] ) ? (int) $item['ID'] : 0,
					'title'     => $item['title'] ?? '',
					'post_date' => $item['post_date'] ?? '',
					'mime'      => $item['mime'] ?? '',
					'file_path' => $item['file_path'] ?? '',
					'url'       => $item['url'] ?? '',
					'thumb_url' => $item['thumb_url'] ?? '',
					'edit_url'  => $item['edit_url'] ?? '',
				);
			}
		}

		$derivedSummary = $stats['derived'] ?? $this->get_derived_attachment_summary( 25 );
		if ( ! empty( $derivedSummary['count'] ) ) {
			$result['derived_count'] = (int) $derivedSummary['count'];
		}
		if ( ! empty( $derivedSummary['items'] ) ) {
			foreach ( $derivedSummary['items'] as $item ) {
				$result['derived_preview'][] = array(
					'ID'        => isset( $item['ID'] ) ? (int) $item['ID'] : 0,
					'title'     => $item['title'] ?? '',
					'post_date' => $item['post_date'] ?? '',
					'mime'      => $item['mime'] ?? '',
					'file_path' => $item['file_path'] ?? '',
					'url'       => $item['url'] ?? '',
					'thumb_url' => $item['thumb_url'] ?? '',
					'parent_id' => $item['parent_id'] ?? 0,
					'edit_url'  => $item['edit_url'] ?? '',
				);
			}
		}

		return $result;
	}

	/**
	 * OPTIMIZED: High-performance complete index rebuild
	 * Uses Content-First lookup for fast scanning (~49s for 219 attachments)
	 *
	 * @param bool       $force_rebuild Whether to clear existing index first
	 * @param array|null $lookup Pre-built Content-First lookup from MSH_Content_Usage_Lookup
	 * @param array      $context Context info for logging (trigger, source, etc.)
	 * @return array Result with success status, message, stats
	 */
	public function build_optimized_complete_index( $force_rebuild = false, $lookup = null, $context = array() ) {
		global $wpdb;

		set_time_limit( 0 ); // No time limit
		ignore_user_abort( true ); // Continue even if user navigates away
		ini_set( 'memory_limit', '1G' ); // Increase memory

		$trigger = $context['trigger'] ?? 'manual';
		$source  = $context['source'] ?? 'ajax';

		error_log( "MSH Usage Index: Starting OPTIMIZED index build (trigger: $trigger, source: $source)" );

		// Clear existing index if force rebuild
		if ( $force_rebuild ) {
			$this->invalidate_stats_cache();
			$wpdb->query( "TRUNCATE TABLE {$this->index_table}" );
			error_log( 'MSH Usage Index: Cleared existing index for optimized rebuild' );
		}

		// Get all image attachments at once
		$attachments = $wpdb->get_results(
			"
            SELECT ID, guid
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
            ORDER BY ID
        "
		);

		if ( empty( $attachments ) ) {
			error_log( 'MSH Usage Index: No attachments found' );
			return array(
				'success' => false,
				'message' => 'No attachments found',
			);
		}

		$total = count( $attachments );
		error_log( "MSH Usage Index: Found $total attachments to index" );

		// Use Content-First lookup if provided (FAST path ~49s)
		if ( $lookup !== null && is_array( $lookup ) && ! empty( $lookup['entries'] ) ) {
			error_log( 'MSH Usage Index: Using Content-First lookup (FAST mode)' );
			$result = $this->build_from_content_lookup( $attachments, $lookup, $context );
			$this->invalidate_stats_cache();
			return $result;
		}

		// Fallback to building lookup ourselves if not provided
		error_log( 'MSH Usage Index: Building Content-First lookup...' );
		$content_lookup = MSH_Content_Usage_Lookup::get_instance();
		$lookup         = $content_lookup->build_lookup(
			true,
			array(
				'trigger' => $trigger,
				'source'  => $source,
			)
		);

		if ( $lookup && is_array( $lookup ) && ! empty( $lookup['entries'] ) ) {
			error_log( 'MSH Usage Index: Using newly built Content-First lookup' );
			$result = $this->build_from_content_lookup( $attachments, $lookup, $context );
			$this->invalidate_stats_cache();
			return $result;
		}

		// Last resort: fall back to old slow method (should never happen)
		error_log( 'MSH Usage Index: WARNING - Falling back to slow nested-loop method!' );
		$result = $this->complete_optimized_build( $attachments );
		$this->invalidate_stats_cache();
		return $result;

		error_log( 'MSH Usage Index: Building URL variation map...' );
		foreach ( $attachments as $attachment ) {
			$variations = $detector->get_all_variations( $attachment->ID );
			foreach ( $variations as $variation ) {
				if ( ! empty( $variation ) ) {
					$variation_to_attachment[ $variation ] = $attachment->ID;
				}
			}
		}

		$total_variations = count( $variation_to_attachment );
		error_log( "MSH Usage Index: Generated $total_variations URL variations" );

		// Now do single scans of each table
		$posts_entries   = $this->index_all_posts_optimized( $variation_to_attachment );
		$meta_entries    = $this->index_all_postmeta_optimized( $variation_to_attachment );
		$options_entries = $this->index_all_options_optimized( $variation_to_attachment );

		$total_entries = $posts_entries + $meta_entries + $options_entries;

		// Update completion status
		update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
		update_option( 'msh_safe_rename_enabled', '1' );

		error_log( "MSH Usage Index: OPTIMIZED build complete! Posts: $posts_entries, Meta: $meta_entries, Options: $options_entries, Total: $total_entries" );

		return array(
			'success' => true,
			'message' => "Optimized indexing complete: $total_entries entries for $total attachments",
			'stats'   => array(
				'total_attachments' => $total,
				'total_entries'     => $total_entries,
				'posts_entries'     => $posts_entries,
				'meta_entries'      => $meta_entries,
				'options_entries'   => $options_entries,
			),
		);
	}

	/**
	 * OPTIMIZED: Index ALL posts in a single pass
	 */
	private function index_all_posts_optimized( $variation_to_attachment ) {
		global $wpdb;

		error_log( 'MSH Usage Index: Scanning posts table...' );
		$entries_added = 0;

		// Get ALL posts with content in manageable chunks
		$offset     = 0;
		$chunk_size = 100;

		do {
			$posts = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT ID, post_type, post_content, post_excerpt
                FROM {$wpdb->posts}
                WHERE (post_content != '' OR post_excerpt != '')
                AND post_status IN ('publish', 'draft', 'private')
                LIMIT %d OFFSET %d
            ",
					$chunk_size,
					$offset
				)
			);

			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $post ) {
				// Check ALL variations against this post's content
				foreach ( $variation_to_attachment as $variation => $attachment_id ) {
					// Check post_content
					if ( ! empty( $post->post_content ) && strpos( $post->post_content, $variation ) !== false ) {
						$wpdb->insert(
							$this->index_table,
							array(
								'attachment_id' => $attachment_id,
								'url_variation' => $variation,
								'table_name'    => 'posts',
								'row_id'        => $post->ID,
								'column_name'   => 'post_content',
								'context_type'  => 'content',
								'post_type'     => $post->post_type,
							)
						);
						++$entries_added;
					}

					// Check post_excerpt
					if ( ! empty( $post->post_excerpt ) && strpos( $post->post_excerpt, $variation ) !== false ) {
						$wpdb->insert(
							$this->index_table,
							array(
								'attachment_id' => $attachment_id,
								'url_variation' => $variation,
								'table_name'    => 'posts',
								'row_id'        => $post->ID,
								'column_name'   => 'post_excerpt',
								'context_type'  => 'excerpt',
								'post_type'     => $post->post_type,
							)
						);
						++$entries_added;
					}
				}
			}

			$offset += $chunk_size;

			if ( $offset % 500 == 0 ) {
				error_log( "MSH Usage Index: Processed $offset posts, $entries_added entries so far..." );
			}
		} while ( count( $posts ) == $chunk_size );

		error_log( "MSH Usage Index: Posts scan complete - $entries_added entries added" );
		return $entries_added;
	}

	/**
	 * OPTIMIZED: Index ALL postmeta in a single pass
	 */
	private function index_all_postmeta_optimized( $variation_to_attachment ) {
		global $wpdb;

		error_log( 'MSH Usage Index: Scanning postmeta table...' );
		$entries_added = 0;

		// Process postmeta in chunks, only rows that might contain image URLs
		$offset     = 0;
		$chunk_size = 500;

		do {
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT meta_id, post_id, meta_key, meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_value LIKE '%%uploads%%'
                AND LENGTH(meta_value) > 20
                LIMIT %d OFFSET %d
            ",
					$chunk_size,
					$offset
				)
			);

			if ( empty( $meta_rows ) ) {
				break;
			}

			foreach ( $meta_rows as $meta ) {
				foreach ( $variation_to_attachment as $variation => $attachment_id ) {
					if ( strpos( $meta->meta_value, $variation ) !== false ) {
						$context_type = $this->determine_meta_context( $meta->meta_key, $meta->meta_value );

						$wpdb->insert(
							$this->index_table,
							array(
								'attachment_id' => $attachment_id,
								'url_variation' => $variation,
								'table_name'    => 'postmeta',
								'row_id'        => $meta->meta_id,
								'column_name'   => 'meta_value',
								'context_type'  => $context_type,
								'post_type'     => null,
							)
						);
						++$entries_added;
					}
				}
			}

			$offset += $chunk_size;

			if ( $offset % 2000 == 0 ) {
				error_log( "MSH Usage Index: Processed $offset postmeta rows, $entries_added entries so far..." );
			}
		} while ( count( $meta_rows ) == $chunk_size );

		error_log( "MSH Usage Index: Postmeta scan complete - $entries_added entries added" );
		return $entries_added;
	}

	/**
	 * OPTIMIZED: Index ALL options in a single pass
	 */
	private function index_all_options_optimized( $variation_to_attachment ) {
		global $wpdb;

		error_log( 'MSH Usage Index: Scanning options table...' );
		$entries_added = 0;

		// Get all options that might contain image URLs
		$options = $wpdb->get_results(
			"
            SELECT option_id, option_name, option_value
            FROM {$wpdb->options}
            WHERE option_value LIKE '%uploads%'
            AND option_name NOT LIKE '\_%'
            AND LENGTH(option_value) > 20
        "
		);

		foreach ( $options as $option ) {
			foreach ( $variation_to_attachment as $variation => $attachment_id ) {
				if ( strpos( $option->option_value, $variation ) !== false ) {
					$context_type = $this->determine_option_context( $option->option_name, $option->option_value );

					$wpdb->insert(
						$this->index_table,
						array(
							'attachment_id' => $attachment_id,
							'url_variation' => $variation,
							'table_name'    => 'options',
							'row_id'        => $option->option_id,
							'column_name'   => 'option_value',
							'context_type'  => $context_type,
							'post_type'     => null,
						)
					);
					++$entries_added;
				}
			}
		}

		error_log( "MSH Usage Index: Options scan complete - $entries_added entries added" );
		return $entries_added;
	}

	/**
	 * Complete the optimized index build method
	 */
	/**
	 * FAST: Build index from Content-First lookup (< 1 minute for 219 attachments)
	 * Uses pre-scanned /uploads/ references instead of nested loops
	 */
	private function build_from_content_lookup( $attachments, $lookup, $context = array() ) {
		global $wpdb;
		$start_time = microtime( true );

		error_log( 'MSH Usage Index: Building from Content-First lookup with ' . count( $lookup['entries'] ) . ' entries' );

		// Build attachment URL map (attachment_id => [variations])
		$detector              = MSH_URL_Variation_Detector::get_instance();
		$attachment_variations = array();

		foreach ( $attachments as $attachment ) {
			$variations = $detector->get_all_variations( $attachment->ID );
			foreach ( $variations as $variation ) {
				if ( ! empty( $variation ) ) {
					// Store both ways for fast bidirectional lookup
					$attachment_variations[ $variation ] = $attachment->ID;
				}
			}
		}

		error_log( 'MSH Usage Index: Built ' . count( $attachment_variations ) . ' URL variations' );

		// Now match lookup entries to attachment variations
		$entries_added       = 0;
		$matched_attachments = array();

		foreach ( $lookup['entries'] as $entry ) {
			// Try to match this lookup entry to an attachment variation
			$matched_id = null;

			// Check full URL
			if ( ! empty( $entry['url_full'] ) && isset( $attachment_variations[ $entry['url_full'] ] ) ) {
				$matched_id = $attachment_variations[ $entry['url_full'] ];
			}
			// Check relative URL
			elseif ( ! empty( $entry['url_relative'] ) && isset( $attachment_variations[ $entry['url_relative'] ] ) ) {
				$matched_id = $attachment_variations[ $entry['url_relative'] ];
			}
			// Check filename
			elseif ( ! empty( $entry['url_filename'] ) && isset( $attachment_variations[ $entry['url_filename'] ] ) ) {
				$matched_id = $attachment_variations[ $entry['url_filename'] ];
			}

			if ( $matched_id ) {
				// Insert into index
				$wpdb->insert(
					$this->index_table,
					array(
						'attachment_id' => $matched_id,
						'url_variation' => $entry['url_full'] ?: $entry['url_relative'] ?: $entry['url_filename'],
						'table_name'    => $entry['table'],
						'row_id'        => $entry['row_id'],
						'column_name'   => $entry['column'],
						'context_type'  => $entry['context'],
						'post_type'     => $entry['post_type'],
					)
				);

				++$entries_added;
				$matched_attachments[ $matched_id ] = true;
			}
		}

		$duration      = microtime( true ) - $start_time;
		$total_matched = count( $matched_attachments );

		error_log( "MSH Usage Index: Content-First build complete - $total_matched attachments, $entries_added entries in " . round( $duration, 2 ) . 's' );

		return array(
			'success'  => true,
			'message'  => "Content-First build complete: $total_matched attachments, $entries_added entries",
			'duration' => $duration,
			'stats'    => array(
				'total_attachments'        => $total_matched,
				'total_entries'            => $entries_added,
				'lookup_entries_processed' => count( $lookup['entries'] ),
			),
		);
	}

	private function complete_optimized_build( $attachments ) {
		$start_time = microtime( true );
		$total      = count( $attachments );

		// Build URL variation map first
		$variation_to_attachment = array();
		$detector                = MSH_URL_Variation_Detector::get_instance();

		foreach ( $attachments as $attachment ) {
			$variations = $detector->get_all_variations( $attachment->ID );
			foreach ( $variations as $variation ) {
				if ( ! empty( $variation ) ) {
					$variation_to_attachment[ $variation ] = $attachment->ID;
				}
			}
		}

		error_log( 'MSH Usage Index: Built ' . count( $variation_to_attachment ) . ' URL variations' );

		// Phase 1: Call the optimized scanning methods
		$posts_entries   = $this->index_all_posts_optimized( $variation_to_attachment );
		$meta_entries    = $this->index_all_postmeta_optimized( $variation_to_attachment );
		$options_entries = $this->index_all_options_optimized( $variation_to_attachment );

		$total_entries = $posts_entries + $meta_entries + $options_entries;

		// Phase 2: Also save to WordPress option for compatibility
		global $wpdb;
		$usage_index = array();

		// Get all index entries and convert to option format
		$index_entries = $wpdb->get_results(
			"
            SELECT attachment_id, table_name, row_id, column_name
            FROM {$this->index_table}
            ORDER BY attachment_id
        "
		);

		foreach ( $index_entries as $entry ) {
			$attachment_id = $entry->attachment_id;
			$location_type = $entry->table_name;

			if ( ! isset( $usage_index[ $attachment_id ] ) ) {
				$usage_index[ $attachment_id ] = array();
			}

			if ( ! isset( $usage_index[ $attachment_id ][ $location_type ] ) ) {
				$usage_index[ $attachment_id ][ $location_type ] = array();
			}

			$usage_index[ $attachment_id ][ $location_type ][] = $entry->row_id;
		}

		// Save to WordPress option for compatibility with rename system
		update_option( 'msh_image_usage_index', serialize( $usage_index ), false );

		$duration = microtime( true ) - $start_time;

		// Verify actual completion by checking database
		$actual_indexed = $wpdb->get_var( "SELECT COUNT(DISTINCT attachment_id) FROM {$this->index_table}" );
		$total_images   = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" );

		$completion_rate = round( ( $actual_indexed / $total_images ) * 100, 1 );

		if ( $actual_indexed < $total_images ) {
			$missing = $total_images - $actual_indexed;
			error_log( "MSH Usage Index: OPTIMIZED build PARTIAL - $actual_indexed/$total_images attachments ($completion_rate%), $missing failed/timed out" );

			$this->rebuild_usage_status_index();
			$this->invalidate_stats_cache();

			return array(
				'success'            => false,
				'message'            => "Optimized build incomplete: $actual_indexed of $total_images attachments processed ($completion_rate%). $missing attachments failed or timed out.",
				'duration'           => $duration,
				'partial_completion' => true,
				'stats'              => array(
					'total_attachments'     => $total_images,
					'processed_attachments' => $actual_indexed,
					'failed_attachments'    => $missing,
					'total_entries'         => $total_entries,
					'completion_rate'       => $completion_rate,
				),
			);
		}

		error_log( "MSH Usage Index: OPTIMIZED build complete - $actual_indexed attachments, $total_entries entries in " . round( $duration, 2 ) . 's' );

		$this->rebuild_usage_status_index();
		$this->invalidate_stats_cache();

		return array(
			'success'  => true,
			'message'  => "Optimized build complete: $actual_indexed attachments, $total_entries entries",
			'duration' => $duration,
			'stats'    => array(
				'total_attachments' => $actual_indexed,
				'total_entries'     => $total_entries,
				'posts_entries'     => $posts_entries,
				'meta_entries'      => $meta_entries,
				'options_entries'   => $options_entries,
			),
		);
	}

	/**
	 * SMART INDEXING: Check what actually needs to be indexed
	 */
	public function get_unindexed_attachments() {
		global $wpdb;

		return $wpdb->get_col(
			"
            SELECT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND p.ID NOT IN (
                SELECT DISTINCT attachment_id
                FROM {$this->index_table}
            )
            ORDER BY p.ID
        "
		);
	}

	/**
	 * Check for content modified since last index update
	 */
	public function get_modified_content_since_last_index() {
		global $wpdb;

		$last_index_update = $wpdb->get_var( "SELECT MAX(last_updated) FROM {$this->index_table}" );

		if ( ! $last_index_update ) {
			return array(); // No previous index, everything is "new"
		}

		// Find posts modified since last index
		$modified_posts = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_modified > %s
            AND (p.post_type IN ('post', 'page') OR p.post_type LIKE '%_page')
        ",
				$last_index_update
			)
		);

		// Find postmeta modified (this is trickier, we'll use a heuristic)
		// Look for attachments that might be affected by recent content changes
		$potentially_affected = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND EXISTS (
                SELECT 1 FROM {$wpdb->posts} p2
                WHERE p2.post_modified > %s
                AND p2.post_content LIKE CONCAT('%%', p.post_name, '%%')
            )
        ",
				$last_index_update
			)
		);

		return array(
			'modified_posts'                   => $modified_posts,
			'potentially_affected_attachments' => $potentially_affected,
		);
	}

	/**
	 * Find orphaned index entries (for deleted attachments)
	 */
	public function get_orphaned_index_entries() {
		global $wpdb;

		return $wpdb->get_col(
			"
            SELECT DISTINCT attachment_id
            FROM {$this->index_table}
            WHERE attachment_id NOT IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'attachment'
            )
        "
		);
	}

	/**
	 * Clean up orphaned entries
	 */
	public function cleanup_orphaned_entries( $orphaned_ids ) {
		if ( empty( $orphaned_ids ) ) {
			return 0;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $orphaned_ids ), '%d' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"
            DELETE FROM {$this->index_table}
            WHERE attachment_id IN ($placeholders)
        ",
				...$orphaned_ids
			)
		);
	}

	/**
	 * SMART BUILD: Only process what actually needs updating
	 */
	public function smart_build_index() {
		$start_time = microtime( true );

		// 1. Get unindexed attachments
		$new_attachments = $this->get_unindexed_attachments();

		// 2. Get modified content
		$modified_content = $this->get_modified_content_since_last_index();

		// 3. Get orphaned entries
		$orphaned_entries = $this->get_orphaned_index_entries();

		$stats = array(
			'new_attachments'      => count( $new_attachments ),
			'modified_posts'       => count( $modified_content['modified_posts'] ?? array() ),
			'potentially_affected' => count( $modified_content['potentially_affected_attachments'] ?? array() ),
			'orphaned_entries'     => count( $orphaned_entries ),
			'processed'            => 0,
			'cleaned_up'           => 0,
			'processed_ids'        => array(),
			'processed_details'    => array(),
		);

		$append_processed = function ( $attachment_id ) use ( &$stats ) {
			$attachment_id = (int) $attachment_id;
			if ( $attachment_id <= 0 || in_array( $attachment_id, $stats['processed_ids'], true ) ) {
				return;
			}

			$stats['processed_ids'][] = $attachment_id;

			$post          = get_post( $attachment_id );
			$file_relative = get_post_meta( $attachment_id, '_wp_attached_file', true );

			$stats['processed_details'][] = array(
				'id'            => $attachment_id,
				'title'         => $post ? $post->post_title : '',
				'filename'      => $file_relative ? wp_basename( $file_relative ) : '',
				'last_modified' => $post ? $post->post_modified : '',
			);
		};

		// 4. Check if anything needs updating
		$total_work = $stats['new_attachments'] + $stats['potentially_affected'] + $stats['orphaned_entries'];

		if ( $total_work === 0 ) {
			return array(
				'success'  => true,
				'message'  => 'Index is up to date - no changes detected',
				'stats'    => $stats,
				'duration' => round( microtime( true ) - $start_time, 3 ),
			);
		}

		// 5. Clean up orphaned entries first
		if ( ! empty( $orphaned_entries ) ) {
			$stats['cleaned_up'] = $this->cleanup_orphaned_entries( $orphaned_entries );
			error_log( "MSH Smart Index: Cleaned up {$stats['cleaned_up']} orphaned entries" );
		}

		// 6. Process new attachments
		foreach ( $new_attachments as $attachment_id ) {
			try {
				$this->index_attachment_usage( $attachment_id, false );
				$append_processed( $attachment_id );
			} catch ( Exception $e ) {
				error_log( "MSH Smart Index: Failed to index new attachment $attachment_id: " . $e->getMessage() );
			}
		}

		// 7. Re-process potentially affected attachments
		$affected_attachments = $modified_content['potentially_affected_attachments'] ?? array();
		foreach ( $affected_attachments as $attachment_id ) {
			try {
				// Clear existing entries for this attachment
				global $wpdb;
				$wpdb->delete( $this->index_table, array( 'attachment_id' => $attachment_id ) );

				// Re-index
				$this->index_attachment_usage( $attachment_id, false );
				$append_processed( $attachment_id );
			} catch ( Exception $e ) {
				error_log( "MSH Smart Index: Failed to re-index affected attachment $attachment_id: " . $e->getMessage() );
			}
		}

		$stats['processed'] = count( $stats['processed_ids'] );

		// Update last build timestamp
		update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
		$this->update_usage_status_for_attachments( array_merge( $new_attachments, $affected_attachments, $orphaned_entries ) );
		$this->invalidate_stats_cache();

		$duration = microtime( true ) - $start_time;

		return array(
			'success'  => true,
			'message'  => "Smart index update complete: {$stats['processed']} attachments processed, {$stats['cleaned_up']} orphaned entries cleaned",
			'stats'    => $stats,
			'duration' => round( $duration, 3 ),
		);
	}

	/**
	 * TRUE FORCE REBUILD: Always clear everything and rebuild from scratch
	 */
	public function true_force_rebuild() {
		global $wpdb;

		error_log( 'MSH Usage Index: Starting TRUE force rebuild - clearing everything' );

		// 1. Always clear everything
		$this->invalidate_stats_cache();
		$wpdb->query( "TRUNCATE TABLE {$this->index_table}" );
		delete_option( 'msh_image_usage_index' );
		delete_option( 'msh_usage_index_last_build' );

		// 2. Always rebuild from scratch using optimized method
		$result = $this->build_optimized_complete_index( true );

		$this->invalidate_stats_cache();

		error_log( 'MSH Usage Index: TRUE force rebuild complete' );

		return $result;
	}

	/**
	 * ENHANCED CHUNKED FORCE REBUILD: Robust error handling with per-attachment isolation
	 */
	public function chunked_force_rebuild( $chunk_size = 50, $offset = 0 ) {
		global $wpdb;

		$chunk_start_time = microtime( true );
		$chunk_timeout    = 360; // 6 minutes max per chunk

		// Clear everything on first chunk only
		if ( $offset === 0 ) {
			$this->invalidate_stats_cache();
			$wpdb->query( "TRUNCATE TABLE {$this->index_table}" );
			delete_option( 'msh_image_usage_index' );
			delete_option( 'msh_usage_index_last_build' );
			delete_option( 'msh_failed_attachments' ); // Clear any previous failures
			error_log( 'MSH Usage Index: Enhanced chunked force rebuild - cleared all data' );

			$total_attachments = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
			);

			return array(
				'success'             => true,
				'processed'           => 0,
				'failed'              => 0,
				'total'               => $total_attachments,
				'offset'              => 0,
				'next_offset'         => min( $chunk_size, $total_attachments ),
				'has_more'            => $total_attachments > 0,
				'duration'            => 0.1,
				'progress_percentage' => 0,
				'message'             => 'Database cleared. Starting enhanced chunked rebuild...',
				'errors'              => array(),
			);
		}

		// Get total attachments
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		if ( $total_attachments === 0 ) {
			return array(
				'success'   => true,
				'processed' => 0,
				'failed'    => 0,
				'total'     => 0,
				'offset'    => 0,
				'has_more'  => false,
				'message'   => 'No attachments found',
				'errors'    => array(),
			);
		}

		// Get chunk of attachments with detailed info
		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT ID, post_title, post_name, guid, post_mime_type
            FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
            ORDER BY ID
            LIMIT %d OFFSET %d
        ",
				$chunk_size,
				$offset
			)
		);

		$processed        = 0;
		$failed           = 0;
		$errors           = array();
		$failed_ids       = array();
		$slow_attachments = array();

		error_log(
			sprintf(
				'MSH Chunked Rebuild: Processing chunk at offset %d, %d attachments',
				$offset,
				count( $attachments )
			)
		);

		// Process each attachment with comprehensive error handling
		foreach ( $attachments as $index => $attachment ) {
			// Check overall chunk timeout
			if ( ( microtime( true ) - $chunk_start_time ) > $chunk_timeout ) {
				$error_msg = 'Chunk timeout after ' . round( microtime( true ) - $chunk_start_time, 1 ) . " seconds - processed $processed/$chunk_size attachments";
				error_log( "MSH Chunked Rebuild: $error_msg at attachment {$attachment->ID}" );
				$errors[] = array(
					'type'               => 'chunk_timeout',
					'message'            => $error_msg,
					'attachment_id'      => $attachment->ID,
					'processed_in_chunk' => $processed,
					'duration'           => round( microtime( true ) - $chunk_start_time, 1 ),
				);

				// Save progress - mark remaining attachments for next chunk
				$remaining_in_chunk = count( $attachments ) - $index;
				error_log( "MSH Chunked Rebuild: Chunk timeout - $remaining_in_chunk attachments will be processed in next chunk" );
				break;
			}

			$attachment_start_time = microtime( true );
			$attachment_timeout    = $attachment->post_mime_type === 'image/svg+xml' ? 30 : 10; // Longer timeout for SVGs

			try {
				// Clear any cached data to prevent memory buildup
				if ( $index % 10 === 0 ) {
					wp_cache_flush();
				}

				// Log current attachment being processed
				error_log(
					sprintf(
						'MSH Chunked Rebuild: Processing attachment %d (%s) - Type: %s',
						$attachment->ID,
						$attachment->post_title ?: $attachment->post_name,
						$attachment->post_mime_type
					)
				);

				// Set per-attachment timeout using async processing simulation
				$attachment_processed = false;
				$attachment_error     = null;

				// Try processing with timeout protection
				$original_time_limit = ini_get( 'max_execution_time' );
				if ( $original_time_limit > 0 ) {
					set_time_limit( $original_time_limit + $attachment_timeout );
				}

				try {
					$start_memory = memory_get_usage();

					// The actual indexing call
					$this->index_attachment_usage( $attachment->ID, true );

					$end_memory      = memory_get_usage();
					$memory_used     = $end_memory - $start_memory;
					$processing_time = microtime( true ) - $attachment_start_time;

					// Log slow attachments for analysis
					if ( $processing_time > 5 ) {
						$slow_attachments[] = array(
							'id'     => $attachment->ID,
							'time'   => round( $processing_time, 2 ),
							'memory' => round( $memory_used / 1024 / 1024, 2 ) . 'MB',
							'type'   => $attachment->post_mime_type,
						);

						error_log(
							sprintf(
								'MSH Chunked Rebuild: Slow attachment %d took %.2fs and %.2fMB',
								$attachment->ID,
								$processing_time,
								$memory_used / 1024 / 1024
							)
						);
					}

					$attachment_processed = true;
					++$processed;

				} catch ( Exception $inner_e ) {
					$attachment_error = $inner_e;
				} finally {
					// Restore original time limit
					if ( $original_time_limit > 0 ) {
						set_time_limit( $original_time_limit );
					}
				}

				// Handle attachment-specific failure
				if ( ! $attachment_processed || $attachment_error ) {
					throw $attachment_error ?: new Exception( 'Attachment processing failed without specific error' );
				}
			} catch ( Exception $e ) {
				++$failed;
				$failed_ids[]    = $attachment->ID;
				$processing_time = microtime( true ) - $attachment_start_time;

				$error_details = array(
					'attachment_id'   => $attachment->ID,
					'title'           => $attachment->post_title ?: $attachment->post_name,
					'type'            => $attachment->post_mime_type,
					'error'           => $e->getMessage(),
					'processing_time' => round( $processing_time, 2 ),
					'chunk_offset'    => $offset + $index,
				);

				$errors[] = $error_details;

				error_log(
					sprintf(
						'MSH Chunked Rebuild: FAILED attachment %d (%s) after %.2fs - %s',
						$attachment->ID,
						$attachment->post_title ?: $attachment->post_name,
						$processing_time,
						$e->getMessage()
					)
				);

				// Store failed attachment for potential retry
				$current_failed                    = get_option( 'msh_failed_attachments', array() );
				$current_failed[ $attachment->ID ] = array(
					'error'        => $e->getMessage(),
					'attempt_time' => current_time( 'mysql' ),
					'chunk_offset' => $offset,
				);
				update_option( 'msh_failed_attachments', $current_failed );

				// Continue processing next attachment (don't let one failure stop the chunk)
				continue;
			}
		}

		$chunk_duration = microtime( true ) - $chunk_start_time;

		// Calculate next offset based on actually processed attachments
		// If we timed out, the next chunk should start from where we left off
		$actually_processed = $processed + $failed;
		$next_offset        = $offset + $actually_processed;

		// Safety check: If no progress was made (timeout on first attachment), skip forward to avoid infinite loop
		if ( $actually_processed === 0 && ! empty( $attachments ) ) {
			error_log( "MSH Chunked Rebuild: No progress made in chunk at offset $offset - skipping first attachment to prevent infinite loop" );
			$next_offset = $offset + 1; // Skip the problematic attachment
		}

		$has_more            = $next_offset < $total_attachments;
		$progress_percentage = round( ( $next_offset / $total_attachments ) * 100, 1 );

		// Prepare comprehensive result
		$result = array(
			'success'             => true, // Success means chunk completed, even with individual failures
			'processed'           => $processed,
			'failed'              => $failed,
			'total'               => $total_attachments,
			'offset'              => $offset,
			'next_offset'         => min( $next_offset, $total_attachments ),
			'has_more'            => $has_more,
			'duration'            => round( $chunk_duration, 2 ),
			'progress_percentage' => $progress_percentage,
			'errors'              => $errors,
			'failed_ids'          => $failed_ids,
		);

		// Create informative message
		if ( $failed > 0 ) {
			$result['message'] = sprintf(
				'Chunk %d: %d processed, %d failed (%.1f%% complete)',
				floor( $offset / $chunk_size ) + 1,
				$processed,
				$failed,
				$progress_percentage
			);
		} else {
			$result['message'] = sprintf(
				'Chunk %d: %d attachments processed successfully (%.1f%% complete)',
				floor( $offset / $chunk_size ) + 1,
				$processed,
				$progress_percentage
			);
		}

		// Add slow attachment info if any
		if ( ! empty( $slow_attachments ) ) {
			$result['slow_attachments'] = $slow_attachments;
			error_log( 'MSH Chunked Rebuild: ' . count( $slow_attachments ) . ' slow attachments in this chunk' );
		}

		// Handle completion
		if ( ! $has_more ) {
			update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
			$this->rebuild_usage_status_index();
			$this->invalidate_stats_cache();

			// Get final statistics
			$final_stats  = $this->get_index_stats();
			$total_failed = get_option( 'msh_failed_attachments', array() );

			$result['final_stats'] = array(
				'total_attachments'    => $total_attachments,
				'successfully_indexed' => $final_stats['summary']->indexed_attachments ?? 0,
				'total_index_entries'  => $final_stats['summary']->total_entries ?? 0,
				'failed_attachments'   => count( $total_failed ),
				'failed_ids'           => array_keys( $total_failed ),
			);

			$result['message'] = sprintf(
				'Chunked rebuild complete! %d/%d attachments indexed (%d failed)',
				$result['final_stats']['successfully_indexed'],
				$total_attachments,
				count( $total_failed )
			);

			error_log(
				sprintf(
					'MSH Usage Index: Enhanced chunked rebuild COMPLETE - %d/%d attachments, %d failed',
					$result['final_stats']['successfully_indexed'],
					$total_attachments,
					count( $total_failed )
				)
			);
		}

		return $result;
	}

	/**
	 * Get list of failed attachments for retry functionality
	 */
	public function get_failed_attachments() {
		return get_option( 'msh_failed_attachments', array() );
	}

	/**
	 * Clear failed attachments list (for fresh start)
	 */
	public function clear_failed_attachments() {
		delete_option( 'msh_failed_attachments' );
	}

	/**
	 * Retry specific failed attachments
	 */
	public function retry_failed_attachments( $attachment_ids = null ) {
		$failed_attachments = $this->get_failed_attachments();

		if ( empty( $failed_attachments ) ) {
			return array(
				'success' => true,
				'message' => 'No failed attachments to retry',
			);
		}

		$to_retry     = $attachment_ids ? array_intersect_key( $failed_attachments, array_flip( $attachment_ids ) ) : $failed_attachments;
		$retried      = 0;
		$still_failed = array();

		foreach ( $to_retry as $attachment_id => $failure_info ) {
			try {
				$this->index_attachment_usage( $attachment_id, true );
				++$retried;
				unset( $failed_attachments[ $attachment_id ] );
			} catch ( Exception $e ) {
				$still_failed[ $attachment_id ] = $e->getMessage();
			}
		}

		update_option( 'msh_failed_attachments', $failed_attachments );

		return array(
			'success'      => true,
			'retried'      => $retried,
			'still_failed' => count( $still_failed ),
			'message'      => "Retry complete: $retried succeeded, " . count( $still_failed ) . ' still failed',
		);
	}
}
