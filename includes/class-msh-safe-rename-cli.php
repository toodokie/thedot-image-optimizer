<?php
/**
 * WP-CLI helpers for exercising the Safe Rename system.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Safe_Rename_CLI_Helper {
	/**
	 * Run the safe rename regression routine.
	 *
	 * @param array  $ids  Attachment IDs.
	 * @param string $mode 'test' or 'live'.
	 * @return array
	 */
	public static function run_regression( array $ids, $mode = 'test' ) {
		$ids = array_filter( array_map( 'intval', $ids ) );

		$summary = array(
			'requested' => $ids,
			'mode'      => $mode,
			'success'   => 0,
			'skipped'   => array(),
			'failures'  => array(),
			'logs'      => array(),
		);

		if ( empty( $ids ) ) {
			return $summary;
		}

		$safe_rename = MSH_Safe_Rename_System::get_instance();
		$test_mode   = strtolower( $mode ) !== 'live';
		$mode_label  = $test_mode ? 'TEST' : 'LIVE';

		foreach ( $ids as $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			if ( ! $path || ! file_exists( $path ) ) {
				$summary['failures'][] = array(
					'id'      => $attachment_id,
					'message' => 'Original file not found.',
				);
				continue;
			}

			$extension   = pathinfo( $path, PATHINFO_EXTENSION );
			$base        = pathinfo( $path, PATHINFO_FILENAME );
			$target_name = sanitize_file_name( $base . '-msh-regression.' . $extension );

			$result = $safe_rename->rename_attachment( $attachment_id, $target_name, $test_mode );

			if ( is_wp_error( $result ) ) {
				$summary['failures'][] = array(
					'id'      => $attachment_id,
					'message' => $result->get_error_message(),
				);
				continue;
			}

			if ( ! empty( $result['skipped'] ) ) {
				$summary['skipped'][] = array(
					'id'      => $attachment_id,
					'message' => 'Rename skipped (filename already optimal).',
				);
				continue;
			}

			++$summary['success'];
			$summary['logs'][] = sprintf(
				'Attachment %d: %s rename simulated; references touched: %d',
				$attachment_id,
				$mode_label,
				intval( $result['replaced'] )
			);
		}

		return $summary;
	}

	/**
	 * Scan for attachments whose stored filenames contain repeated prefixes.
	 *
	 * @param int $limit Maximum number of attachments to return. 0 = no limit.
	 * @return array<int>
	 */
	public static function scan_for_corrupted_filenames( $limit = 0 ) {
		global $wpdb;

		$pattern = '([A-Za-z0-9]+-)\\1';

		if ( $limit > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND meta_value REGEXP %s
				LIMIT %d",
				$pattern,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file'
				AND meta_value REGEXP %s",
				$pattern
			);
		}

		$rows = $wpdb->get_results( $sql );

		if ( empty( $rows ) ) {
			return array();
		}

		$safe_rename = MSH_Safe_Rename_System::get_instance();
		$ids         = array();

		foreach ( $rows as $row ) {
			$attachment_id = (int) $row->post_id;
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$report = $safe_rename->repair_corrupted_attachment( $attachment_id, true );

			if ( is_wp_error( $report ) ) {
				continue;
			}

			if ( isset( $report['status'] ) && $report['status'] !== 'clean' ) {
				$ids[] = $attachment_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Repair attachments that contain repeated filename prefixes.
	 *
	 * @param array<int> $ids     Attachment IDs to repair.
	 * @param bool       $dry_run When true, calculate changes without updating the database.
	 * @return array
	 */
	public static function repair_corrupted_filenames( array $ids, $dry_run = false ) {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids )
				)
			)
		);

		$summary = array(
			'dry_run'   => (bool) $dry_run,
			'processed' => array(),
			'repaired'  => array(),
			'unchanged' => array(),
			'errors'    => array(),
		);

		if ( empty( $ids ) ) {
			return $summary;
		}

		$safe_rename = MSH_Safe_Rename_System::get_instance();

		foreach ( $ids as $attachment_id ) {
			$report = $safe_rename->repair_corrupted_attachment( $attachment_id, $dry_run );

			if ( is_wp_error( $report ) ) {
				$summary['errors'][] = array(
					'id'      => $attachment_id,
					'message' => $report->get_error_message(),
				);
				continue;
			}

			$summary['processed'][] = $report;

			if ( isset( $report['status'] ) && in_array( $report['status'], array( 'repaired', 'needs_repair' ), true ) ) {
				$summary['repaired'][] = $report;
			} else {
				$summary['unchanged'][] = $report;
			}
		}

		return $summary;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'msh rename-regression',
		function ( $args, $assoc_args ) {
			$ids  = isset( $assoc_args['ids'] ) ? explode( ',', $assoc_args['ids'] ) : array();
			$mode = isset( $assoc_args['mode'] ) ? strtolower( $assoc_args['mode'] ) : 'test';

			if ( empty( $ids ) ) {
				WP_CLI::error( 'Provide a comma-separated list of attachment IDs via --ids=123,456.' );
				return;
			}

			$summary = MSH_Safe_Rename_CLI_Helper::run_regression( $ids, $mode );

			foreach ( $summary['logs'] as $line ) {
				WP_CLI::log( $line );
			}

			if ( ! empty( $summary['skipped'] ) ) {
				WP_CLI::warning( 'Skipped attachments:' );
				foreach ( $summary['skipped'] as $skip ) {
					WP_CLI::warning( sprintf( ' - ID %d: %s', $skip['id'], $skip['message'] ) );
				}
			}

			if ( ! empty( $summary['failures'] ) ) {
				WP_CLI::warning( 'Failures detected:' );
				foreach ( $summary['failures'] as $failure ) {
					WP_CLI::warning( sprintf( ' - ID %d: %s', $failure['id'], $failure['message'] ) );
				}
			}

			WP_CLI::success( sprintf( 'Successful operations: %d', $summary['success'] ) );
		}
	);

	WP_CLI::add_command(
		'msh repair-filenames',
		function ( $args, $assoc_args ) {
			$ids = array();

			if ( ! empty( $assoc_args['ids'] ) ) {
				$manual_ids = array_filter(
					array_map(
						'intval',
						preg_split( '/[\s,]+/', $assoc_args['ids'] )
					)
				);
				$ids = array_merge( $ids, $manual_ids );
			}

			$get_flag = function ( $key, $default = false ) use ( $assoc_args ) {
				if ( function_exists( 'WP_CLI\Utils\get_flag_value' ) ) {
					return \WP_CLI\Utils\get_flag_value( $assoc_args, $key, $default );
				}

				return array_key_exists( $key, $assoc_args ) ? $assoc_args[ $key ] : $default;
			};

			$scan = $get_flag( 'scan', false );
			if ( $scan ) {
				$limit    = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 0;
				$scan_ids = MSH_Safe_Rename_CLI_Helper::scan_for_corrupted_filenames( $limit );
				$ids      = array_merge( $ids, $scan_ids );
			}

			$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

			if ( empty( $ids ) ) {
				WP_CLI::error( 'No attachment IDs supplied. Use --ids=123 or add --scan to auto-detect corrupted entries.' );
				return;
			}

			$dry_run = $get_flag( 'dry-run', false );

			$summary = MSH_Safe_Rename_CLI_Helper::repair_corrupted_filenames( $ids, $dry_run );

			foreach ( $summary['processed'] as $report ) {
				$changes = array();
				if ( ! empty( $report['meta_changed'] ) ) {
					$changes[] = '_wp_attached_file';
				}
				if ( ! empty( $report['metadata_changed'] ) ) {
					$changes[] = 'metadata';
				}
				if ( ! empty( $report['post_name_changed'] ) ) {
					$changes[] = 'slug';
				}

				$status_label = isset( $report['status'] ) ? strtoupper( $report['status'] ) : 'UNKNOWN';
				$change_label = empty( $changes ) ? 'no changes' : implode( ', ', $changes );
				$prefix       = $dry_run ? '[DRY-RUN]' : '[UPDATED]';

				WP_CLI::log(
					sprintf(
						'%s Attachment %d — %s (%s)',
						$prefix,
						$report['attachment_id'],
						$status_label,
						$change_label
					)
				);

				if ( ! empty( $report['notes'] ) ) {
					foreach ( $report['notes'] as $note ) {
						WP_CLI::log( '   • ' . $note );
					}
				}
			}

			if ( ! empty( $summary['errors'] ) ) {
				WP_CLI::warning( 'Errors encountered:' );
				foreach ( $summary['errors'] as $error ) {
					WP_CLI::warning( sprintf( ' - Attachment %d: %s', $error['id'], $error['message'] ) );
				}
			}

			if ( $dry_run ) {
				WP_CLI::success(
					sprintf(
						'Identified %d attachment(s) needing repair (out of %d processed). Run without --dry-run to apply fixes.',
						count( $summary['repaired'] ),
						count( $summary['processed'] )
					)
				);
			} else {
				WP_CLI::success(
					sprintf(
						'Repaired %d attachment(s); %d already clean.',
						count( $summary['repaired'] ),
						count( $summary['unchanged'] )
					)
				);
			}
		}
	);
}
