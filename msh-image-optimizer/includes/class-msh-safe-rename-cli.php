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
}
