<?php
/**
 * Cloud Sync WP-CLI Commands - Phase 4R+
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'MSH_Sync_CLI' ) ) {
	class MSH_Sync_CLI {

		/**
		 * Push metadata snapshot to the configured cloud provider.
		 *
		 * ## OPTIONS
		 *
		 * <attachment_id>
		 * : Attachment ID.
		 *
		 * <locale>
		 * : Locale code (e.g., 'en_US').
		 *
		 * @when after_wp_load
		 */
		public function push( $args, $assoc_args ) {
			list( $attachment_id, $locale ) = $args;

			$attachment_id = absint( $attachment_id );

			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				WP_CLI::error( sprintf( 'Attachment %d is not an image.', $attachment_id ) );
			}

			$driver = MSH_Cloud_Sync_Driver::get_instance();
			$result = $driver->push( $attachment_id, $locale );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::success( sprintf( 'Metadata for attachment %d locale %s pushed successfully.', $attachment_id, $locale ) );
		}

		/**
		 * Pull metadata snapshot from the configured cloud provider.
		 *
		 * ## OPTIONS
		 *
		 * <attachment_id>
		 * : Attachment ID.
		 *
		 * <locale>
		 * : Locale code (e.g., 'en_US').
		 *
		 * @when after_wp_load
		 */
		public function pull( $args, $assoc_args ) {
			list( $attachment_id, $locale ) = $args;

			$attachment_id = absint( $attachment_id );

			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				WP_CLI::error( sprintf( 'Attachment %d is not an image.', $attachment_id ) );
			}

			$driver = MSH_Cloud_Sync_Driver::get_instance();
			$result = $driver->pull( $attachment_id, $locale );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::success( sprintf( 'Metadata for attachment %d locale %s pulled successfully.', $attachment_id, $locale ) );
		}

		/**
		 * Display remote ETag for an attachment + locale pair.
		 *
		 * ## OPTIONS
		 *
		 * <attachment_id>
		 * : Attachment ID.
		 *
		 * <locale>
		 * : Locale code.
		 *
		 * @when after_wp_load
		 */
		public function etag( $args, $assoc_args ) {
			list( $attachment_id, $locale ) = $args;

			$attachment_id = absint( $attachment_id );

			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				WP_CLI::error( sprintf( 'Attachment %d is not an image.', $attachment_id ) );
			}

			$driver = MSH_Cloud_Sync_Driver::get_instance();
			$result = $driver->get_etag( $attachment_id, $locale );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::line( (string) $result );
		}
	}
}
