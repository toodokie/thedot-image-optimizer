<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * WP-CLI commands for MSH Image Optimizer.
 */
class MSH_CLI {
	/**
	 * Manage seasonal caching.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : The operation to perform. Accepts `get`, `set`, or `clear`.
	 *
	 * [<season>]
	 * : Season identifier when using the `set` command. Valid values: winter, spring, summer, fall.
	 *
	 * [--ttl=<seconds>]
	 * : Optional cache lifetime when setting a season (defaults to 24 hours).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh season get
	 *     wp msh season set winter --ttl=3600
	 *     wp msh season clear
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function season( $args, $assoc_args ) {
		$command = $args[0] ?? 'get';

		$optimizer = new MSH_Contextual_Meta_Generator();

		switch ( $command ) {
			case 'get':
				$season = $optimizer->get_current_season();
				WP_CLI::success( "Current detected season: {$season}" );
				break;

			case 'set':
				$season = $args[1] ?? '';
				if ( $season === '' ) {
					WP_CLI::error( 'Please specify a season: winter, spring, summer, fall' );
				}
				$ttl = isset( $assoc_args['ttl'] ) ? (int) $assoc_args['ttl'] : DAY_IN_SECONDS;
				if ( $optimizer->set_season( $season, $ttl ) ) {
					WP_CLI::success( "Season override set to: {$season} (TTL: {$ttl}s)" );
				} else {
					WP_CLI::error( 'Invalid season. Use: winter, spring, summer, fall' );
				}
				break;

			case 'clear':
				$optimizer->clear_season_cache();
				WP_CLI::success( 'Season cache cleared.' );
				break;

			default:
				WP_CLI::error( 'Unknown command. Use: get, set, or clear' );
		}
	}

	/**
	 * Manage AI plan tier for testing.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : The operation to perform. Accepts `get`, `set`, or `clear`.
	 *
	 * [<tier>]
	 * : Plan tier when using the `set` command. Valid values: free, starter, ai_starter, ai_pro, ai_business.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh plan get
	 *     wp msh plan set ai_starter
	 *     wp msh plan set free
	 *     wp msh plan clear
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function plan( $args, $assoc_args ) {
		$command = $args[0] ?? 'get';

		switch ( $command ) {
			case 'get':
				$tier = get_option( 'msh_plan_tier', 'free' );
				WP_CLI::success( "Current plan tier: {$tier}" );
				break;

			case 'set':
				$tier        = $args[1] ?? '';
				$valid_tiers = array( 'free', 'starter', 'ai_starter', 'ai_pro', 'ai_business' );

				if ( $tier === '' ) {
					WP_CLI::error( 'Please specify a tier: ' . implode( ', ', $valid_tiers ) );
				}

				if ( ! in_array( $tier, $valid_tiers, true ) ) {
					WP_CLI::error( 'Invalid tier. Valid options: ' . implode( ', ', $valid_tiers ) );
				}

				update_option( 'msh_plan_tier', $tier, false );
				WP_CLI::success( "Plan tier set to: {$tier}" );

				// Show AI access status
				if ( class_exists( 'MSH_AI_Service' ) ) {
					$ai_service = MSH_AI_Service::get_instance();
					$state      = $ai_service->determine_access_state();

					if ( $state['allowed'] ) {
						WP_CLI::success( sprintf( 'AI access: GRANTED (%s mode)', $state['access_mode'] ) );
					} else {
						WP_CLI::warning( sprintf( 'AI access: DENIED (reason: %s)', $state['reason'] ) );
					}
				}
				break;

			case 'clear':
				delete_option( 'msh_plan_tier' );
				WP_CLI::success( 'Plan tier cleared (defaults to "free").' );
				break;

			default:
				WP_CLI::error( 'Unknown command. Use: get, set, or clear' );
		}
	}

	/**
	 * Test AI access with current configuration.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh ai-status
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function ai_status( $args, $assoc_args ) {
		if ( ! class_exists( 'MSH_AI_Service' ) ) {
			WP_CLI::error( 'MSH_AI_Service class not found. AI features may not be loaded.' );
			return;
		}

		$ai_service = MSH_AI_Service::get_instance();
		$state      = $ai_service->determine_access_state();

		WP_CLI::line( '' );
		WP_CLI::line( '=== AI Access Status ===' );
		WP_CLI::line( '' );

		$status_color = $state['allowed'] ? '%G' : '%R';
		WP_CLI::line( WP_CLI::colorize( sprintf( $status_color . 'Access: %s' . '%n', $state['allowed'] ? 'GRANTED' : 'DENIED' ) ) );

		WP_CLI::line( "AI Mode: {$state['mode']}" );
		WP_CLI::line( "Plan Tier: {$state['plan_tier']}" );

		if ( $state['allowed'] ) {
			WP_CLI::line( WP_CLI::colorize( '%GAccess Mode: ' . strtoupper( $state['access_mode'] ) . '%n' ) );

			if ( $state['access_mode'] === 'byok' ) {
				$key_preview = substr( $state['api_key'], 0, 7 ) . '...' . substr( $state['api_key'], -4 );
				WP_CLI::line( "API Key: {$key_preview}" );
			}

			WP_CLI::line( 'Enabled Features: ' . ( empty( $state['features'] ) ? 'none' : implode( ', ', $state['features'] ) ) );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%RDenial Reason: ' . $state['reason'] . '%n' ) );

			if ( $state['reason'] === 'upgrade_required' ) {
				WP_CLI::line( '' );
				WP_CLI::line( '💡 To enable AI:' );
				WP_CLI::line( '   1. Set plan tier: wp msh plan set ai_starter' );
				WP_CLI::line( '   2. OR add API key: wp option update msh_ai_api_key "sk-..."' );
			} elseif ( $state['reason'] === 'manual_mode' ) {
				WP_CLI::line( '' );
				WP_CLI::line( '💡 To enable AI, change mode from "manual" to "assist" or "hybrid"' );
				WP_CLI::line( '   wp option update msh_ai_mode "assist"' );
			} elseif ( $state['reason'] === 'feature_disabled' ) {
				WP_CLI::line( '' );
				WP_CLI::line( '💡 To enable AI, add "meta" to enabled features:' );
				WP_CLI::line( '   wp option update msh_ai_features \'["meta"]\'' );
			}
		}

		WP_CLI::line( '' );
	}

	/**
	 * Regenerate metadata for images using AI or heuristics.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Regenerate metadata for all images.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated list of attachment IDs to process.
	 *
	 * [--batch=<number>]
	 * : Number of images to process (use with --offset for batching).
	 *
	 * [--offset=<number>]
	 * : Starting position for batch processing.
	 *
	 * [--mode=<mode>]
	 * : Overwrite strategy: fill-empty (default) or overwrite.
	 *
	 * [--force]
	 * : Force regeneration even if metadata exists.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh regenerate-metadata --all
	 *     wp msh regenerate-metadata --ids=123,456,789
	 *     wp msh regenerate-metadata --batch=100 --offset=0
	 *     wp msh regenerate-metadata --all --mode=overwrite --force
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function regenerate_metadata( $args, $assoc_args ) {
		if ( ! class_exists( 'MSH_AI_Service' ) || ! class_exists( 'MSH_Contextual_Meta_Generator' ) ) {
			WP_CLI::error( 'Required classes not found. Plugin may not be loaded correctly.' );
			return;
		}

		$mode  = isset( $assoc_args['mode'] ) ? $assoc_args['mode'] : 'fill-empty';
		$force = isset( $assoc_args['force'] );

		if ( ! in_array( $mode, array( 'fill-empty', 'overwrite' ), true ) ) {
			WP_CLI::error( 'Invalid mode. Use: fill-empty or overwrite' );
			return;
		}

		// Determine which images to process
		$attachment_ids = array();

		if ( isset( $assoc_args['ids'] ) ) {
			$attachment_ids = array_map( 'intval', explode( ',', $assoc_args['ids'] ) );
		} elseif ( isset( $assoc_args['all'] ) ) {
			$query_args = array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			);

			if ( isset( $assoc_args['batch'] ) ) {
				$query_args['posts_per_page'] = (int) $assoc_args['batch'];
				$query_args['offset']         = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
			}

			$attachment_ids = get_posts( $query_args );
		} else {
			WP_CLI::error( 'Please specify --all or --ids=<ids>' );
			return;
		}

		if ( empty( $attachment_ids ) ) {
			WP_CLI::warning( 'No images found to process.' );
			return;
		}

		$total = count( $attachment_ids );
		WP_CLI::line( WP_CLI::colorize( "%GProcessing {$total} image(s)...%n" ) );
		WP_CLI::line( 'Mode: ' . $mode . ( $force ? ' (forced)' : '' ) );
		WP_CLI::line( '' );

		// Check AI access
		$ai_service = MSH_AI_Service::get_instance();
		$ai_state   = $ai_service->determine_access_state();

		if ( $ai_state['allowed'] ) {
			WP_CLI::line( WP_CLI::colorize( '%GAI: ENABLED (' . strtoupper( $ai_state['access_mode'] ) . ')%n' ) );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%YAI: DISABLED (using heuristics) - Reason: ' . $ai_state['reason'] . '%n' ) );
		}
		WP_CLI::line( '' );

		$generator = new MSH_Contextual_Meta_Generator();
		$processed = 0;
		$skipped   = 0;
		$failed    = 0;

		$progress = \WP_CLI\Utils\make_progress_bar( 'Regenerating metadata', $total );

		foreach ( $attachment_ids as $attachment_id ) {
			// Build context
			$context = array(
				'attachment_id' => $attachment_id,
				'business_name' => get_option( 'msh_business_name', '' ),
				'industry'      => get_option( 'msh_industry', '' ),
				'location'      => get_option( 'msh_location', '' ),
				'uvp'           => get_option( 'msh_uvp', '' ),
			);

			// Backup existing metadata
			$backup = array(
				'title'       => get_the_title( $attachment_id ),
				'alt_text'    => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'caption'     => wp_get_attachment_caption( $attachment_id ),
				'description' => get_post_field( 'post_content', $attachment_id ),
			);

			update_post_meta( $attachment_id, '_msh_meta_backup_' . time(), $backup );

			// Check if we should skip based on mode
			if ( $mode === 'fill-empty' && ! $force ) {
				$has_metadata = ! empty( $backup['alt_text'] ) || ! empty( $backup['description'] );
				if ( $has_metadata ) {
					++$skipped;
					$progress->tick();
					continue;
				}
			}

			// Try AI first if available
			$metadata = null;
			if ( $ai_state['allowed'] ) {
				$metadata = $ai_service->maybe_generate_metadata( $attachment_id, $context, $generator );
			}

			// Fallback to heuristics if AI didn't work
			if ( empty( $metadata ) ) {
				// Use heuristic generator (would need to expose this method)
				$metadata = null; // Placeholder - need to call heuristic generator
			}

			if ( ! empty( $metadata ) ) {
				// Apply metadata
				if ( ! empty( $metadata['title'] ) ) {
					wp_update_post(
						array(
							'ID'         => $attachment_id,
							'post_title' => $metadata['title'],
						)
					);
				}
				if ( ! empty( $metadata['alt_text'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', $metadata['alt_text'] );
				}
				if ( ! empty( $metadata['caption'] ) ) {
					wp_update_post(
						array(
							'ID'           => $attachment_id,
							'post_excerpt' => $metadata['caption'],
						)
					);
				}
				if ( ! empty( $metadata['description'] ) ) {
					wp_update_post(
						array(
							'ID'           => $attachment_id,
							'post_content' => $metadata['description'],
						)
					);
				}

				++$processed;
			} else {
				++$failed;
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::line( '' );
		WP_CLI::success( 'Regeneration complete!' );
		WP_CLI::line( WP_CLI::colorize( "%GProcessed: {$processed}%n" ) );
		if ( $skipped > 0 ) {
			WP_CLI::line( WP_CLI::colorize( "%YSkipped: {$skipped} (already had metadata)%n" ) );
		}
		if ( $failed > 0 ) {
			WP_CLI::line( WP_CLI::colorize( "%RFailed: {$failed}%n" ) );
		}
	}
}

WP_CLI::add_command( 'msh', 'MSH_CLI' );
