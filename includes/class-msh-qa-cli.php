<?php
/**
 * WP-CLI regression helpers for the Image Optimizer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Run QA/regression workflows for the optimizer.
	 */
	class MSH_QA_CLI_Command {
		public function __invoke( $args, $assoc_args ) {
			$rename_ids               = isset( $assoc_args['rename'] ) ? array_filter( array_map( 'intval', explode( ',', $assoc_args['rename'] ) ) ) : array();
			$rename_mode              = isset( $assoc_args['rename-mode'] ) ? strtolower( $assoc_args['rename-mode'] ) : 'test';
			$optimize_ids             = isset( $assoc_args['optimize'] ) ? array_filter( array_map( 'intval', explode( ',', $assoc_args['optimize'] ) ) ) : array();
			$run_duplicate            = WP_CLI\Utils\get_flag_value( $assoc_args, 'duplicate', false );
			$duplicate_min_coverage   = isset( $assoc_args['duplicate-min-coverage'] )
				? floatval( $assoc_args['duplicate-min-coverage'] )
				: 5.0;
			$duplicate_require_groups = WP_CLI\Utils\get_flag_value( $assoc_args, 'duplicate-require-groups', false );

			if ( empty( $rename_ids ) && empty( $optimize_ids ) && ! $run_duplicate ) {
				WP_CLI::error( 'Nothing to do. Provide --rename=ids, --optimize=ids, or --duplicate.' );
				return;
			}

			if ( ! empty( $rename_ids ) ) {
				$summary = MSH_Safe_Rename_CLI_Helper::run_regression( $rename_ids, $rename_mode );

				foreach ( $summary['logs'] as $line ) {
					WP_CLI::log( '[Rename] ' . $line );
				}

				foreach ( $summary['skipped'] as $skip ) {
					WP_CLI::warning( sprintf( '[Rename] Skipped ID %d: %s', $skip['id'], $skip['message'] ) );
				}

				foreach ( $summary['failures'] as $failure ) {
					WP_CLI::warning( sprintf( '[Rename] Failed ID %d: %s', $failure['id'], $failure['message'] ) );
				}

				WP_CLI::success( sprintf( '[Rename] Successful operations: %d', $summary['success'] ) );
			}

			if ( ! empty( $optimize_ids ) ) {
				$optimizer = MSH_Image_Optimizer::get_instance();
				$summary   = $optimizer->optimize_attachments_cli( $optimize_ids );

				foreach ( $summary['optimized'] as $item ) {
					WP_CLI::log(
						sprintf(
							'[Optimize] ID %d (%s) Meta: %s WebP: %s',
							$item['id'],
							$item['status'],
							empty( $item['meta_updated'] ) ? 'none' : implode( ',', $item['meta_updated'] ),
							$item['webp_generated'] ? 'yes' : 'no'
						)
					);
				}

				foreach ( $summary['skipped'] as $skip ) {
					WP_CLI::warning( sprintf( '[Optimize] Skipped ID %d: %s', $skip['id'], $skip['message'] ) );
				}

				foreach ( $summary['errors'] as $error ) {
					WP_CLI::warning( sprintf( '[Optimize] Failed ID %d: %s', $error['id'], $error['message'] ) );
				}

				WP_CLI::success( sprintf( '[Optimize] Attachments processed: %d', $summary['processed'] ) );
			}

			if ( $run_duplicate ) {
				$cleanup = MSH_Media_Cleanup::get_instance();
				$report  = $cleanup->generate_quick_scan_report( false );

				if ( is_wp_error( $report ) ) {
					WP_CLI::warning( '[Duplicate] ' . $report->get_error_message() );
				} else {
					$coverage     = isset( $report['debug_info']['coverage_percent'] ) ? (float) $report['debug_info']['coverage_percent'] : 0.0;
					$library_size = isset( $report['debug_info']['total_library_size'] ) ? (int) $report['debug_info']['total_library_size'] : 0;
					$analyzed     = isset( $report['debug_info']['images_analyzed'] ) ? (int) $report['debug_info']['images_analyzed'] : 0;
					$groups_found = isset( $report['total_groups'] ) ? (int) $report['total_groups'] : 0;

					WP_CLI::log( sprintf( '[Duplicate] Groups: %d, Potential duplicates: %d', $groups_found, (int) $report['total_duplicates'] ) );
					WP_CLI::log(
						sprintf(
							'[Duplicate] Coverage: %.1f%% (%d of %d attachments scanned)',
							$coverage,
							$analyzed,
							$library_size
						)
					);

					if ( $coverage < $duplicate_min_coverage ) {
						WP_CLI::error(
							sprintf(
								'[Duplicate] Coverage %.1f%% fell below required %.1f%%. Adjust hash cache or rerun the analyzer, or override with --duplicate-min-coverage.',
								$coverage,
								$duplicate_min_coverage
							)
						);
					}

					if ( $groups_found === 0 ) {
						$message = '[Duplicate] No duplicate groups detected. Library may already be clean.';
						if ( $duplicate_require_groups ) {
							WP_CLI::error( $message . ' (--duplicate-require-groups enforced a hard failure)' );
						} else {
							WP_CLI::warning( $message );
						}
					} else {
						$sample_groups = array_slice( $report['groups'], 0, 5 );
						$table_rows    = array_map(
							function ( $group ) {
								$recommended = isset( $group['recommended_keep']['ID'] ) ? (int) $group['recommended_keep']['ID'] : 0;
								return array(
									'group'         => isset( $group['group_key'] ) ? $group['group_key'] : '(unknown)',
									'files'         => isset( $group['total_count'] ) ? (int) $group['total_count'] : 0,
									'published'     => isset( $group['published_count'] ) ? (int) $group['published_count'] : 0,
									'cleanup_ready' => isset( $group['cleanup_potential'] ) ? (int) $group['cleanup_potential'] : 0,
									'keeper_id'     => $recommended,
								);
							},
							$sample_groups
						);

						if ( ! empty( $table_rows ) ) {
							WP_CLI::log( '[Duplicate] Sample groups (up to 5):' );
							WP_CLI\Utils\format_items( 'table', $table_rows, array( 'group', 'files', 'published', 'cleanup_ready', 'keeper_id' ) );
						}
					}
				}
			}

			WP_CLI::success( 'QA regression suite finished.' );
		}
	}

	WP_CLI::add_command( 'msh qa', 'MSH_QA_CLI_Command' );
}
