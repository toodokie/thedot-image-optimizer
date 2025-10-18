<?php
/**
 * MSH Targeted Replacement Engine
 * Performs fast, precise URL replacements using the usage index
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Targeted_Replacement_Engine {
	private static $instance = null;
	private $usage_index;
	private $backup_system;
	private $url_detector;

	private function __construct() {
		// Lazy load these to avoid instantiation issues
		$this->usage_index   = null;
		$this->backup_system = MSH_Backup_Verification_System::get_instance();
		$this->url_detector  = MSH_URL_Variation_Detector::get_instance();
	}

	private function get_usage_index() {
		if ( $this->usage_index === null ) {
			$this->usage_index = MSH_Image_Usage_Index::get_instance();
		}
		return $this->usage_index;
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Perform safe, targeted replacement for an attachment
	 */
	public function replace_attachment_urls( $attachment_id, $old_filename, $new_filename, $test_mode = false ) {
		global $wpdb;

		// Generate operation ID for tracking
		$operation_id = $this->backup_system->generate_operation_id();

		// Build replacement map
		$replacement_map = $this->url_detector->build_filename_replacement_map( $attachment_id, $old_filename, $new_filename );

		if ( empty( $replacement_map ) ) {
			return new WP_Error( 'no_replacements', 'No URL variations found for replacement' );
		}

		// Validate replacement map
		$validation = $this->url_detector->validate_replacement_map( $replacement_map );
		if ( $validation !== true ) {
			return new WP_Error( 'invalid_map', 'Invalid replacement map: ' . implode( ', ', $validation ) );
		}

		$results = array(
			'operation_id'    => $operation_id,
			'attachment_id'   => $attachment_id,
			'replacement_map' => $replacement_map,
			'test_mode'       => $test_mode,
			'backup_count'    => 0,
			'updated_count'   => 0,
			'error_count'     => 0,
			'updates'         => array(),
			'errors'          => array(),
		);

		try {
			// Create backup if not in test mode
			if ( ! $test_mode ) {
				$results['backup_count'] = $this->backup_system->create_backup( $operation_id, $attachment_id, $replacement_map );
			}

			// Get targeted updates using fast index lookup
			$usage_index      = $this->get_usage_index();
			$targeted_updates = $usage_index->get_targeted_updates( $attachment_id, $replacement_map );

			// Fallback to direct scanning if index is empty/missing
			if ( empty( $targeted_updates ) ) {
				$targeted_updates = $this->get_targeted_updates_direct( $attachment_id, $replacement_map );
			}

			// Perform targeted updates
			foreach ( $targeted_updates as $update ) {
				$update_result = $this->perform_targeted_update( $update, $test_mode );

				if ( $update_result['success'] ) {
					++$results['updated_count'];
					$results['updates'][] = $update_result;
				} else {
					++$results['error_count'];
					$results['errors'][] = $update_result;
				}
			}

			// Perform verification if not in test mode
			if ( ! $test_mode && $results['error_count'] === 0 ) {
				// Pass the targeted updates list to verification for precise checking
				$verification            = $this->backup_system->verify_replacement( $operation_id, $attachment_id, $replacement_map, $targeted_updates );
				$results['verification'] = $verification;

				// If verification failed, restore backup
				if ( $verification['overall_status'] === 'failed' ) {
					$restored                   = $this->backup_system->restore_backup( $operation_id );
					$results['backup_restored'] = $restored;
					return new WP_Error( 'verification_failed', 'Replacement verification failed, backup restored', $results );
				}
			}
		} catch ( Exception $e ) {
			// If something went wrong and we're not in test mode, restore backup
			if ( ! $test_mode && $results['backup_count'] > 0 ) {
				$this->backup_system->restore_backup( $operation_id );
			}

			return new WP_Error( 'replacement_error', $e->getMessage(), $results );
		}

		return $results;
	}

	/**
	 * Get targeted updates directly without using index (simplified approach)
	 */
	private function get_targeted_updates_direct( $attachment_id, $replacement_map ) {
		global $wpdb;
		$updates = array();

		foreach ( $replacement_map as $old_url => $new_url ) {
			if ( $old_url === $new_url ) {
				continue;
			}

			$like_pattern = '%' . $wpdb->esc_like( $old_url ) . '%';

			// Search posts content
			$posts = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT ID, post_type FROM {$wpdb->posts}
                WHERE (post_content LIKE %s OR post_excerpt LIKE %s)
                AND post_status = 'publish'
            ",
					$like_pattern,
					$like_pattern
				)
			);

			foreach ( $posts as $post ) {
				$updates[] = array(
					'table'     => $wpdb->posts,
					'id_column' => 'ID',
					'row_id'    => $post->ID,
					'column'    => 'post_content',
					'old_value' => $old_url,
					'new_value' => $new_url,
					'context'   => 'content',
				);
				$updates[] = array(
					'table'     => $wpdb->posts,
					'id_column' => 'ID',
					'row_id'    => $post->ID,
					'column'    => 'post_excerpt',
					'old_value' => $old_url,
					'new_value' => $new_url,
					'context'   => 'excerpt',
				);
			}

			// Search postmeta
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT meta_id, post_id FROM {$wpdb->postmeta}
                WHERE meta_value LIKE %s
            ",
					$like_pattern
				)
			);

			foreach ( $meta_rows as $meta ) {
				$updates[] = array(
					'table'     => $wpdb->postmeta,
					'id_column' => 'meta_id',
					'row_id'    => $meta->meta_id,
					'column'    => 'meta_value',
					'old_value' => $old_url,
					'new_value' => $new_url,
					'context'   => 'meta',
				);
			}

			// Search options (widgets, etc.)
			$options = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT option_id FROM {$wpdb->options}
                WHERE option_value LIKE %s
            ",
					$like_pattern
				)
			);

			foreach ( $options as $option ) {
				$updates[] = array(
					'table'     => $wpdb->options,
					'id_column' => 'option_id',
					'row_id'    => $option->option_id,
					'column'    => 'option_value',
					'old_value' => $old_url,
					'new_value' => $new_url,
					'context'   => 'option',
				);
			}
		}

		return $updates;
	}

	/**
	 * Perform a single targeted update
	 */
	private function perform_targeted_update( $update, $test_mode = false ) {
		global $wpdb;

		$result = array(
			'success'   => false,
			'table'     => $update['table'],
			'row_id'    => $update['row_id'],
			'column'    => $update['column'],
			'context'   => $update['context'],
			'old_value' => $update['old_value'],
			'new_value' => $update['new_value'],
			'test_mode' => $test_mode,
		);

		try {
			// Get current value
			$current_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT {$update['column']} FROM {$update['table']} WHERE {$update['id_column']} = %d",
					$update['row_id']
				)
			);

			if ( $current_value === null ) {
				$result['error'] = 'Row not found';
				return $result;
			}

			// Handle serialized data specially
			$new_content = $this->replace_in_content( $current_value, $update['old_value'], $update['new_value'], $update['context'] );

			if ( $new_content === $current_value ) {
				$result['success'] = true;
				$result['message'] = 'No replacement needed';
				return $result;
			}

			// Perform update if not in test mode
			if ( ! $test_mode ) {
				$updated = $wpdb->update(
					$update['table'],
					array( $update['column'] => $new_content ),
					array( $update['id_column'] => $update['row_id'] ),
					array( '%s' ),
					array( '%d' )
				);

				if ( $updated === false ) {
					$result['error'] = 'Database update failed: ' . $wpdb->last_error;
					return $result;
				}
			}

			$result['success']      = true;
			$result['message']      = $test_mode ? 'Would update' : 'Updated successfully';
			$result['changes_made'] = substr_count( $current_value, $update['old_value'] );

		} catch ( Exception $e ) {
			$result['error'] = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Replace URLs in content, handling serialized data
	 */
	private function replace_in_content( $content, $old_url, $new_url, $context ) {
		if ( empty( $content ) || $old_url === $new_url ) {
			return $content;
		}

		// Handle serialized data
		if ( is_serialized( $content ) ) {
			$unserialized = maybe_unserialize( $content );
			$updated      = $this->recursive_replace( $unserialized, $old_url, $new_url );
			return maybe_serialize( $updated );
		}

		// Handle JSON data (common in modern page builders)
		if ( $this->is_json( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( $decoded !== null ) {
				$updated = $this->recursive_replace( $decoded, $old_url, $new_url );
				return json_encode( $updated, JSON_UNESCAPED_SLASHES );
			}
		}

		// Regular string replacement
		return str_replace( $old_url, $new_url, $content );
	}

	/**
	 * Recursively replace URLs in nested data structures
	 */
	private function recursive_replace( $data, $old_url, $new_url ) {
		if ( is_string( $data ) ) {
			return str_replace( $old_url, $new_url, $data );
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->recursive_replace( $value, $old_url, $new_url );
			}
			return $data;
		}

		if ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->recursive_replace( $value, $old_url, $new_url );
			}
			return $data;
		}

		return $data;
	}

	/**
	 * Check if string is JSON
	 */
	private function is_json( $string ) {
		if ( ! is_string( $string ) || empty( $string ) ) {
			return false;
		}

		$first_char = $string[0];
		return ( $first_char === '{' || $first_char === '[' ) && json_decode( $string ) !== null;
	}

	/**
	 * Batch process multiple attachments
	 */
	public function batch_replace( $attachments, $test_mode = false, $batch_size = 10 ) {
		$results = array(
			'total_attachments' => count( $attachments ),
			'processed'         => 0,
			'successful'        => 0,
			'failed'            => 0,
			'details'           => array(),
		);

		$batches = array_chunk( $attachments, $batch_size );

		foreach ( $batches as $batch_index => $batch ) {
			foreach ( $batch as $attachment_data ) {
				$attachment_id = $attachment_data['id'];
				$old_filename  = $attachment_data['old_filename'];
				$new_filename  = $attachment_data['new_filename'];

				$result = $this->replace_attachment_urls( $attachment_id, $old_filename, $new_filename, $test_mode );

				if ( is_wp_error( $result ) ) {
					++$results['failed'];
					$results['details'][ $attachment_id ] = array(
						'status' => 'error',
						'error'  => $result->get_error_message(),
						'data'   => $result->get_error_data(),
					);
				} else {
					++$results['successful'];
					$results['details'][ $attachment_id ] = array(
						'status' => 'success',
						'data'   => $result,
					);
				}

				++$results['processed'];

				// Note: Progress can be tracked via return value instead of logging
			}

			// Brief pause between batches to prevent timeouts
			if ( $batch_index < count( $batches ) - 1 ) {
				usleep( 100000 ); // 0.1 second
			}
		}

		return $results;
	}

	/**
	 * Dry run to preview what would be changed
	 */
	public function preview_changes( $attachment_id, $old_filename, $new_filename ) {
		// Build replacement map
		$replacement_map = $this->url_detector->build_filename_replacement_map( $attachment_id, $old_filename, $new_filename );

		if ( empty( $replacement_map ) ) {
			return array( 'error' => 'No URL variations found' );
		}

		// Get targeted updates using fast index lookup
		$targeted_updates = $this->usage_index->get_targeted_updates( $attachment_id, $replacement_map );

		$preview = array(
			'attachment_id'      => $attachment_id,
			'old_filename'       => $old_filename,
			'new_filename'       => $new_filename,
			'replacement_map'    => $replacement_map,
			'total_updates'      => count( $targeted_updates ),
			'updates_by_context' => array(),
			'updates_by_table'   => array(),
			'sample_updates'     => array_slice( $targeted_updates, 0, 10 ),
		);

		// Group by context
		foreach ( $targeted_updates as $update ) {
			$context = $update['context'];
			$table   = basename( $update['table'] );

			if ( ! isset( $preview['updates_by_context'][ $context ] ) ) {
				$preview['updates_by_context'][ $context ] = 0;
			}
			++$preview['updates_by_context'][ $context ];

			if ( ! isset( $preview['updates_by_table'][ $table ] ) ) {
				$preview['updates_by_table'][ $table ] = 0;
			}
			++$preview['updates_by_table'][ $table ];
		}

		return $preview;
	}

	/**
	 * Get replacement statistics
	 */
	public function get_replacement_stats( $days = 30 ) {
		global $wpdb;

		// Get stats from backup table
		$backup_table       = $wpdb->prefix . 'msh_rename_backups';
		$verification_table = $wpdb->prefix . 'msh_rename_verification';

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT
                COUNT(DISTINCT operation_id) as total_operations,
                COUNT(DISTINCT attachment_id) as unique_attachments,
                COUNT(*) as total_backups,
                AVG(CASE WHEN status = 'restored' THEN 1 ELSE 0 END) as restore_rate
            FROM {$backup_table}
            WHERE backup_date >= %s
        ",
				$cutoff_date
			)
		);

		$verification_stats = $wpdb->get_row(
			$wpdb->prepare(
				"
            SELECT
                COUNT(*) as total_checks,
                AVG(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_rate
            FROM {$verification_table}
            WHERE check_date >= %s
        ",
				$cutoff_date
			)
		);

		return array(
			'period_days'  => $days,
			'operations'   => $stats,
			'verification' => $verification_stats,
		);
	}
}
