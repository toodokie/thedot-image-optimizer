<?php
/**
 * MSH Backup and Verification System
 * Handles database backups and verification for safe rename operations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Backup_Verification_System {
	private static $instance = null;
	private $backup_table;
	private $verification_table;

	private function __construct() {
		global $wpdb;
		$this->backup_table       = $wpdb->prefix . 'msh_rename_backups';
		$this->verification_table = $wpdb->prefix . 'msh_rename_verification';

		add_action( 'init', array( $this, 'maybe_create_tables' ) );
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function maybe_create_tables() {
		if ( get_option( 'msh_backup_tables_version' ) === '1' ) {
			return;
		}

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Backup table for storing pre-rename state
		$backup_sql = "CREATE TABLE {$this->backup_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            operation_id varchar(32) NOT NULL,
            attachment_id int(11) NOT NULL,
            table_name varchar(64) NOT NULL,
            row_id int(11) NOT NULL,
            column_name varchar(64) NOT NULL,
            original_value longtext NOT NULL,
            backup_date datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            KEY operation_id (operation_id),
            KEY attachment_id (attachment_id),
            KEY backup_date (backup_date)
        ) $charset_collate;";

		// Verification table for tracking changes
		$verification_sql = "CREATE TABLE {$this->verification_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            operation_id varchar(32) NOT NULL,
            attachment_id int(11) NOT NULL,
            check_type varchar(50) NOT NULL,
            expected_value text NOT NULL,
            actual_value text NOT NULL,
            status varchar(20) NOT NULL,
            error_message text NULL,
            check_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY operation_id (operation_id),
            KEY attachment_id (attachment_id),
            KEY status (status)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $backup_sql );
		dbDelta( $verification_sql );

		update_option( 'msh_backup_tables_version', '1' );
	}

	/**
	 * Create a backup before rename operation
	 */
	public function create_backup( $operation_id, $attachment_id, $replacement_map ) {
		global $wpdb;

		$backup_count = 0;

		$urls = array_keys( $replacement_map );

		// Backup posts content
		$processed_post_columns = array();
		foreach ( $urls as $old_url ) {
			if ( $old_url === '' ) {
				continue;
			}

			$like  = '%' . $wpdb->esc_like( $old_url ) . '%';
			$posts = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT ID, post_content, post_excerpt
                FROM {$wpdb->posts}
                WHERE post_content LIKE %s OR post_excerpt LIKE %s
            ",
					$like,
					$like
				)
			);

			foreach ( $posts as $post ) {
				$content_key = $post->ID . ':post_content';
				if ( ! isset( $processed_post_columns[ $content_key ] ) && $this->contains_any_urls( $post->post_content, $urls ) ) {
					$wpdb->insert(
						$this->backup_table,
						array(
							'operation_id'   => $operation_id,
							'attachment_id'  => $attachment_id,
							'table_name'     => 'posts',
							'row_id'         => $post->ID,
							'column_name'    => 'post_content',
							'original_value' => $post->post_content,
							'status'         => 'active',
						)
					);
					++$backup_count;
					$processed_post_columns[ $content_key ] = true;
				}

				$excerpt_key = $post->ID . ':post_excerpt';
				if ( ! isset( $processed_post_columns[ $excerpt_key ] ) && $this->contains_any_urls( $post->post_excerpt, $urls ) ) {
					$wpdb->insert(
						$this->backup_table,
						array(
							'operation_id'   => $operation_id,
							'attachment_id'  => $attachment_id,
							'table_name'     => 'posts',
							'row_id'         => $post->ID,
							'column_name'    => 'post_excerpt',
							'original_value' => $post->post_excerpt,
							'status'         => 'active',
						)
					);
					++$backup_count;
					$processed_post_columns[ $excerpt_key ] = true;
				}
			}
		}

		// Backup postmeta
		$backup_count += $this->backup_meta_table( 'postmeta', $wpdb->postmeta, 'meta_id', 'meta_value', $operation_id, $attachment_id, $replacement_map );

		// Backup options
		$backup_count += $this->backup_meta_table( 'options', $wpdb->options, 'option_id', 'option_value', $operation_id, $attachment_id, $replacement_map );

		// Backup usermeta
		$backup_count += $this->backup_meta_table( 'usermeta', $wpdb->usermeta, 'umeta_id', 'meta_value', $operation_id, $attachment_id, $replacement_map );

		// Backup termmeta if exists
		if ( isset( $wpdb->termmeta ) ) {
			$backup_count += $this->backup_meta_table( 'termmeta', $wpdb->termmeta, 'meta_id', 'meta_value', $operation_id, $attachment_id, $replacement_map );
		}

		return $backup_count;
	}

	/**
	 * Backup a meta table (postmeta, options, etc.)
	 */
	private function backup_meta_table( $table_name, $table, $id_column, $value_column, $operation_id, $attachment_id, $replacement_map ) {
		global $wpdb;
		$backup_count = 0;

		$processed_rows = array();
		$urls           = array_keys( $replacement_map );

		foreach ( $urls as $old_url ) {
			if ( $old_url === '' ) {
				continue;
			}

			$like = '%' . $wpdb->esc_like( $old_url ) . '%';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT {$id_column} as id, {$value_column} as value
                FROM {$table}
                WHERE {$value_column} LIKE %s
            ",
					$like
				)
			);

			foreach ( $rows as $row ) {
				if ( isset( $processed_rows[ $row->id ] ) ) {
					continue;
				}

				if ( ! $this->contains_any_urls( $row->value, $urls ) ) {
					continue;
				}

				$wpdb->insert(
					$this->backup_table,
					array(
						'operation_id'   => $operation_id,
						'attachment_id'  => $attachment_id,
						'table_name'     => $table_name,
						'row_id'         => $row->id,
						'column_name'    => $value_column,
						'original_value' => $row->value,
						'status'         => 'active',
					)
				);
				++$backup_count;
				$processed_rows[ $row->id ] = true;
			}
		}

		return $backup_count;
	}

	/**
	 * Check if content contains any of the URLs
	 */
	private function contains_any_urls( $content, $urls ) {
		if ( empty( $content ) ) {
			return false;
		}

		foreach ( $urls as $url ) {
			if ( strpos( $content, $url ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify that replacement was successful
	 */
	public function verify_replacement( $operation_id, $attachment_id, $replacement_map, $targeted_updates = null ) {
		global $wpdb;

		$verification_results = array();
		$success_count        = 0;
		$error_count          = 0;

		// If we have targeted updates, verify only those specific rows
		if ( $targeted_updates && is_array( $targeted_updates ) ) {
			return $this->verify_targeted_updates( $operation_id, $attachment_id, $targeted_updates, $replacement_map );
		}

		// Verify posts table
		foreach ( array_keys( $replacement_map ) as $old_url ) {
			$like = '%' . $wpdb->esc_like( $old_url ) . '%';

			// Check if old URLs still exist in content
			$remaining = $wpdb->get_var(
				$wpdb->prepare(
					"
                SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_content LIKE %s OR post_excerpt LIKE %s
            ",
					$like,
					$like
				)
			);

			$status = $remaining > 0 ? 'failed' : 'success';
			if ( $status === 'failed' ) {
				++$error_count;
			} else {
				++$success_count;
			}

			$wpdb->insert(
				$this->verification_table,
				array(
					'operation_id'   => $operation_id,
					'attachment_id'  => $attachment_id,
					'check_type'     => 'posts_content',
					'expected_value' => '0 occurrences of ' . $old_url,
					'actual_value'   => $remaining . ' occurrences found',
					'status'         => $status,
					'error_message'  => $status === 'failed' ? 'Old URL still found in content' : null,
				)
			);

			$verification_results[] = array(
				'type'            => 'posts_content',
				'old_url'         => $old_url,
				'remaining_count' => $remaining,
				'status'          => $status,
			);
		}

		// Verify meta tables
		$meta_tables = array(
			'postmeta' => $wpdb->postmeta,
			'options'  => $wpdb->options,
			'usermeta' => $wpdb->usermeta,
		);

		if ( isset( $wpdb->termmeta ) ) {
			$meta_tables['termmeta'] = $wpdb->termmeta;
		}

		foreach ( $meta_tables as $table_name => $table ) {
			$value_column = $table_name === 'options' ? 'option_value' : 'meta_value';

			foreach ( array_keys( $replacement_map ) as $old_url ) {
				$like = '%' . $wpdb->esc_like( $old_url ) . '%';

				$remaining = $wpdb->get_var(
					$wpdb->prepare(
						"
                    SELECT COUNT(*)
                    FROM {$table}
                    WHERE {$value_column} LIKE %s
                ",
						$like
					)
				);

				$status = $remaining > 0 ? 'failed' : 'success';
				if ( $status === 'failed' ) {
					++$error_count;
				} else {
					++$success_count;
				}

				$wpdb->insert(
					$this->verification_table,
					array(
						'operation_id'   => $operation_id,
						'attachment_id'  => $attachment_id,
						'check_type'     => $table_name,
						'expected_value' => '0 occurrences of ' . $old_url,
						'actual_value'   => $remaining . ' occurrences found',
						'status'         => $status,
						'error_message'  => $status === 'failed' ? 'Old URL still found in ' . $table_name : null,
					)
				);

				$verification_results[] = array(
					'type'            => $table_name,
					'old_url'         => $old_url,
					'remaining_count' => $remaining,
					'status'          => $status,
				);
			}
		}

		$overall_status = $error_count === 0 ? 'success' : 'failed';

		return array(
			'overall_status' => $overall_status,
			'success_count'  => $success_count,
			'error_count'    => $error_count,
			'details'        => $verification_results,
		);
	}

	/**
	 * Verify only the targeted database rows that were updated (precise verification)
	 */
	private function verify_targeted_updates( $operation_id, $attachment_id, $targeted_updates, $replacement_map ) {
		global $wpdb;

		$verification_results = array();
		$success_count        = 0;
		$error_count          = 0;

		foreach ( $targeted_updates as $update ) {
			$table     = $update['table'];
			$id_column = $update['id_column'];
			$row_id    = $update['row_id'];
			$column    = $update['column'];
			$old_value = $update['old_value'];
			$new_value = $update['new_value'];

			// Check if this specific row still contains the old URL
			$current_value = $wpdb->get_var(
				$wpdb->prepare(
					"
                SELECT {$column}
                FROM {$table}
                WHERE {$id_column} = %d
            ",
					$row_id
				)
			);

			$still_contains_old = false;

			if ( $current_value !== null && $old_value !== '' ) {
				$offset  = 0;
				$old_len = strlen( $old_value );
				$new_len = strlen( $new_value );

				while ( ( $pos = strpos( $current_value, $old_value, $offset ) ) !== false ) {
					$segment = substr( $current_value, $pos, $new_len );

					if ( $new_len > 0 && $segment === $new_value ) {
						$offset = $pos + $old_len;
						continue;
					}

					$still_contains_old = true;
					break;
				}
			}

			if ( $still_contains_old ) {
				++$error_count;
				$status = 'failed';
			} else {
				++$success_count;
				$status = 'success';
			}

			$wpdb->insert(
				$this->verification_table,
				array(
					'operation_id'   => $operation_id,
					'attachment_id'  => $attachment_id,
					'check_type'     => 'targeted_' . $table,
					'expected_value' => 'Row ' . $row_id . ' updated from ' . $old_value . ' to ' . $new_value,
					'actual_value'   => $still_contains_old ? 'Still contains old URL' : 'Successfully updated',
					'status'         => $status,
					'error_message'  => $status === 'failed' ? 'Targeted update failed for row ' . $row_id : null,
				)
			);

			$verification_results[] = array(
				'type'      => 'targeted_update',
				'table'     => $table,
				'row_id'    => $row_id,
				'column'    => $column,
				'old_value' => $old_value,
				'new_value' => $new_value,
				'status'    => $status,
			);
		}

		$overall_status = $error_count === 0 ? 'success' : 'failed';

		return array(
			'overall_status' => $overall_status,
			'success_count'  => $success_count,
			'error_count'    => $error_count,
			'details'        => $verification_results,
		);
	}

	/**
	 * Restore from backup if needed
	 */
	public function restore_backup( $operation_id ) {
		global $wpdb;

		$backups = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT * FROM {$this->backup_table}
            WHERE operation_id = %s AND status = 'active'
            ORDER BY id
        ",
				$operation_id
			)
		);

		$restore_count = 0;

		foreach ( $backups as $backup ) {
			$table        = $backup->table_name;
			$id_column    = $this->get_id_column_for_table( $table );
			$value_column = $backup->column_name;

			// Restore the original value
			$updated = $wpdb->update(
				$wpdb->prefix . $table,
				array( $value_column => $backup->original_value ),
				array( $id_column => $backup->row_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( $updated !== false ) {
				// Mark backup as used
				$wpdb->update(
					$this->backup_table,
					array( 'status' => 'restored' ),
					array( 'id' => $backup->id ),
					array( '%s' ),
					array( '%d' )
				);
				++$restore_count;
			}
		}

		return $restore_count;
	}

	/**
	 * Get the ID column name for a table
	 */
	private function get_id_column_for_table( $table ) {
		$id_columns = array(
			'posts'    => 'ID',
			'postmeta' => 'meta_id',
			'options'  => 'option_id',
			'usermeta' => 'umeta_id',
			'termmeta' => 'meta_id',
		);

		return $id_columns[ $table ] ?? 'id';
	}

	/**
	 * Clean up old backups
	 */
	public function cleanup_old_backups( $days = 7 ) {
		global $wpdb;

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"
            DELETE FROM {$this->backup_table}
            WHERE backup_date < %s AND status != 'active'
        ",
				$cutoff_date
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"
            DELETE FROM {$this->verification_table}
            WHERE check_date < %s
        ",
				$cutoff_date
			)
		);

		return $deleted;
	}

	/**
	 * Get backup statistics
	 */
	public function get_backup_stats() {
		global $wpdb;

		$stats = $wpdb->get_row(
			"
            SELECT
                COUNT(*) as total_backups,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_backups,
                COUNT(CASE WHEN status = 'restored' THEN 1 END) as restored_backups,
                MAX(backup_date) as last_backup
            FROM {$this->backup_table}
        "
		);

		return $stats;
	}

	/**
	 * Generate unique operation ID
	 */
	public function generate_operation_id() {
		return md5( uniqid( 'msh_rename_', true ) );
	}
}
