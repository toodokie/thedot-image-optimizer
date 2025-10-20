<?php
/**
 * Phase 4 - WP-CLI Commands.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Metadata version commands.
 */
class MSH_Version_CLI extends WP_CLI_Command {

	/**
	 * List metadata versions for a media item and locale.
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<id>
	 * : Attachment ID.
	 *
	 * [--locale=<locale>]
	 * : Locale slug (defaults to site locale).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh version list --media-id=123
	 *
	 * @param array $args Positional args (unused).
	 * @param array $assoc_args Named args.
	 */
	public function list( $args, $assoc_args ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( empty( $assoc_args['media-id'] ) ) {
			WP_CLI::error( __( 'Please provide a media ID using --media-id.', 'msh-image-optimizer' ) );
		}

		$media_id = absint( $assoc_args['media-id'] );
		$locale   = isset( $assoc_args['locale'] ) ? sanitize_text_field( $assoc_args['locale'] ) : get_locale();

		$history = MSH_Version_Manager::get_instance()->get_history_for_locale( $media_id, $locale );
		$rows    = array();

		foreach ( $history as $field => $versions ) {
			foreach ( $versions as $version ) {
				$rows[] = array(
					'ID'       => $version['id'],
					'Field'    => $field,
					'Version'  => $version['version'],
					'Source'   => $version['source'],
					'Created'  => $version['created_at'],
					'Approved' => $version['approved_at'] ? $version['approved_at'] : '',
				);
			}
		}

		if ( empty( $rows ) ) {
			WP_CLI::warning( __( 'No versions found for the provided media and locale.', 'msh-image-optimizer' ) );
			return;
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'ID', 'Field', 'Version', 'Source', 'Created', 'Approved' ) );
	}

	/**
	 * Show details for a metadata version.
	 *
	 * ## OPTIONS
	 *
	 * --version-id=<id>
	 * : Version database ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh version show --version-id=456
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function show( $args, $assoc_args ) {
		if ( empty( $assoc_args['version-id'] ) ) {
			WP_CLI::error( __( 'Please provide a version ID using --version-id.', 'msh-image-optimizer' ) );
		}

		$version = MSH_Metadata_Versioning::get_instance()->get_version_by_id( absint( $assoc_args['version-id'] ) );
		if ( ! $version ) {
			WP_CLI::error( __( 'Version not found.', 'msh-image-optimizer' ) );
		}

		$notes = MSH_Version_Manager::get_instance()->get_notes( $version );

		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Version ID: %d', $version['id'] ) );
		WP_CLI::line( sprintf( 'Media ID: %d', $version['media_id'] ) );
		WP_CLI::line( sprintf( 'Locale: %s', $version['locale'] ) );
		WP_CLI::line( sprintf( 'Field: %s', $version['field'] ) );
		WP_CLI::line( sprintf( 'Version: %d', $version['version'] ) );
		WP_CLI::line( sprintf( 'Source: %s', $version['source'] ) );
		WP_CLI::line( sprintf( 'Created: %s', $version['created_at'] ) );
		WP_CLI::line( '---' );
		WP_CLI::line( $version['value'] );
		WP_CLI::line( '---' );

		if ( ! empty( $notes ) ) {
			WP_CLI::line( __( 'Notes:', 'msh-image-optimizer' ) );
			foreach ( $notes as $note ) {
				WP_CLI::line( sprintf( '  - [%s] %s: %s', $note['time'], $note['user'], $note['message'] ) );
			}
		}
	}

	/**
	 * Roll back metadata to a previous version.
	 *
	 * ## OPTIONS
	 *
	 * --version-id=<id>
	 * : The version ID to roll back to.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh version rollback --version-id=456
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function rollback( $args, $assoc_args ) {
		if ( empty( $assoc_args['version-id'] ) ) {
			WP_CLI::error( __( 'Please provide a version ID using --version-id.', 'msh-image-optimizer' ) );
		}

		$new_version = MSH_Version_Manager::get_instance()->rollback_to_version(
			absint( $assoc_args['version-id'] ),
			get_current_user_id()
		);

		if ( ! $new_version ) {
			WP_CLI::error( __( 'Rollback failed.', 'msh-image-optimizer' ) );
		}

		WP_CLI::success(
			sprintf(
				__( 'Rollback applied. New version ID: %d', 'msh-image-optimizer' ),
				$new_version
			)
		);
	}
}

/**
 * A/B testing CLI commands.
 */
class MSH_AB_Testing_CLI extends WP_CLI_Command {

	/**
	 * Create an A/B campaign.
	 *
	 * ## OPTIONS
	 *
	 * --name=<name>
	 * : Campaign name.
	 *
	 * [--variants=<count>]
	 * : Number of variants (default: 2).
	 *
	 * [--description=<description>]
	 * : Optional description.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh ab create --name="Alt Text Test" --variants=3
	 *
	 * @param array $args Positionals.
	 * @param array $assoc_args Named args.
	 */
	public function create( $args, $assoc_args ) {
		if ( empty( $assoc_args['name'] ) ) {
			WP_CLI::error( __( 'Please provide a campaign name using --name.', 'msh-image-optimizer' ) );
		}

		$campaign_id = MSH_AB_Testing::get_instance()->create_campaign(
			$assoc_args['name'],
			isset( $assoc_args['variants'] ) ? absint( $assoc_args['variants'] ) : 2,
			array(
				'description' => isset( $assoc_args['description'] ) ? $assoc_args['description'] : '',
				'created_by'  => get_current_user_id(),
			)
		);

		if ( ! $campaign_id ) {
			WP_CLI::error( __( 'Failed to create campaign.', 'msh-image-optimizer' ) );
		}

		WP_CLI::success(
			sprintf(
				__( 'Campaign created (ID %d).', 'msh-image-optimizer' ),
				$campaign_id
			)
		);
	}

	/**
	 * Show campaign results.
	 *
	 * ## OPTIONS
	 *
	 * --campaign-id=<id>
	 * : Campaign identifier.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh ab results --campaign-id=789
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function results( $args, $assoc_args ) {
		if ( empty( $assoc_args['campaign-id'] ) ) {
			WP_CLI::error( __( 'Please provide a campaign ID using --campaign-id.', 'msh-image-optimizer' ) );
		}

		$campaign = MSH_AB_Testing::get_instance()->get_campaign( absint( $assoc_args['campaign-id'] ) );

		if ( ! $campaign ) {
			WP_CLI::error( __( 'Campaign not found.', 'msh-image-optimizer' ) );
		}

		WP_CLI::line( '' );
		WP_CLI::line( sprintf( __( 'Campaign: %s (Status: %s)', 'msh-image-optimizer' ), $campaign['name'], $campaign['status'] ) );
		WP_CLI::line( '----------------------------------------' );

		$rows = array();
		foreach ( $campaign['variants'] as $variant ) {
			$rows[] = array(
				'Variant' => $variant['label'],
				'Views'   => $variant['views'],
				'Clicks'  => $variant['clicks'],
				'CTR'     => $variant['ctr'],
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'Variant', 'Views', 'Clicks', 'CTR' ) );

		$significance = $campaign['significance'];
		if ( ! empty( $significance['p_value'] ) ) {
			WP_CLI::line( '' );
			WP_CLI::line(
				sprintf(
					__( 'p-value: %s | Significant: %s', 'msh-image-optimizer' ),
					$significance['p_value'],
					$significance['is_significant'] ? __( 'yes', 'msh-image-optimizer' ) : __( 'no', 'msh-image-optimizer' )
				)
			);
		} elseif ( ! empty( $significance['reason'] ) ) {
			WP_CLI::warning( $significance['reason'] );
		}
	}

	/**
	 * Auto-select a winning variant.
	 *
	 * ## OPTIONS
	 *
	 * --campaign-id=<id>
	 * : Campaign identifier.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh ab winner --campaign-id=789
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Named args.
	 */
	public function winner( $args, $assoc_args ) {
		if ( empty( $assoc_args['campaign-id'] ) ) {
			WP_CLI::error( __( 'Please provide a campaign ID using --campaign-id.', 'msh-image-optimizer' ) );
		}

		$result = MSH_AB_Testing::get_instance()->maybe_select_winner( absint( $assoc_args['campaign-id'] ) );

		if ( ! $result ) {
			WP_CLI::warning( __( 'Winner not selected. Campaign may not have enough data yet.', 'msh-image-optimizer' ) );
			return;
		}

		WP_CLI::success( __( 'Winner selected and campaign completed.', 'msh-image-optimizer' ) );
	}
}

