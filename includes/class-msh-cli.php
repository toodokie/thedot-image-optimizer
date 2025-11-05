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
			// Phase 0B Fix: Build context from active context helper (reads msh_onboarding_context)
			$active_context = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
				? MSH_Image_Optimizer_Context_Helper::get_active_context()
				: array();

			$context = array_merge(
				$active_context,
				array( 'attachment_id' => $attachment_id )
			);

			// Phase 0B Fix: Preserve stored manual context settings
			$stored_context_trace = get_post_meta( $attachment_id, '_msh_context_trace', true );
			if ( ! empty( $stored_context_trace ) ) {
				$context_trace = json_decode( $stored_context_trace, true );

				if ( is_array( $context_trace ) ) {
					// Preserve manual context type
					if ( ! empty( $context_trace['context_set_manually'] ) && ! empty( $context_trace['final_context_type'] ) ) {
						$context['final_context_type']  = $context_trace['final_context_type'];
						$context['context_set_manually'] = true;
					}

					// Preserve brand visibility settings
					if ( isset( $context_trace['brand_name_visible'] ) ) {
						$context['brand_name_visible'] = (bool) $context_trace['brand_name_visible'];
					}
					if ( isset( $context_trace['brand_name_visible_manual'] ) ) {
						$context['brand_name_visible_manual'] = (bool) $context_trace['brand_name_visible_manual'];
					}
				}
			}

			// Also read SEO mode from attachment meta (if stored)
			$seo_mode = get_post_meta( $attachment_id, '_msh_seo_mode', true );
			if ( $seo_mode !== '' ) {
				$context['seo_mode'] = (bool) $seo_mode;
			}

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

	/**
	 * Manage OCR brand detection overrides for testing.
	 *
	 * ## OPTIONS
	 *
	 * <subcommand>
	 * : list | set | unset | clear
	 *
	 * [<attachment-id>]
	 * : Attachment ID when using set/unset.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh ocr list
	 *     wp msh ocr set 2049
	 *     wp msh ocr unset 2049
	 *     wp msh ocr clear
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function ocr( $args, $assoc_args ) {
		$command   = $args[0] ?? 'list';
		$option    = 'msh_ocr_overrides';
		$overrides = get_option( $option, array() );

		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		switch ( $command ) {
			case 'list':
				if ( empty( $overrides ) ) {
					WP_CLI::success( 'No OCR overrides are currently set.' );
				} else {
					WP_CLI::line( 'Forced brand detection for attachment IDs:' );
					WP_CLI::line( implode( ', ', array_map( 'intval', $overrides ) ) );
				}
				break;

			case 'set':
				$attachment_id = isset( $args[1] ) ? (int) $args[1] : 0;
				if ( $attachment_id <= 0 ) {
					WP_CLI::error( 'Please provide a valid attachment ID.' );
				}
				if ( ! in_array( $attachment_id, $overrides, true ) ) {
					$overrides[] = $attachment_id;
					update_option( $option, array_values( array_unique( array_map( 'intval', $overrides ) ) ), false );
				}
				WP_CLI::success( "OCR override set for attachment {$attachment_id}." );
				break;

			case 'unset':
				$attachment_id = isset( $args[1] ) ? (int) $args[1] : 0;
				if ( $attachment_id <= 0 ) {
					WP_CLI::error( 'Please provide a valid attachment ID.' );
				}
				$overrides = array_values( array_diff( array_map( 'intval', $overrides ), array( $attachment_id ) ) );
				update_option( $option, $overrides, false );
				WP_CLI::success( "OCR override removed for attachment {$attachment_id}." );
				break;

			case 'clear':
				delete_option( $option );
				WP_CLI::success( 'All OCR overrides cleared.' );
				break;

			default:
				WP_CLI::error( 'Unknown command. Use: list, set, unset, or clear' );
		}
	}

	/**
	 * Scan attachments for corrupted size metadata and optionally repair.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum attachments to scan (default: 500).
	 *
	 * [--repair]
	 * : Attempt automatic repair when corruption is detected.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh scan-corrupt-sizes --limit=250
	 *     wp msh scan-corrupt-sizes --repair
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function scan_corrupt_sizes( $args, $assoc_args ) {
		$limit  = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 500;
		$repair = isset( $assoc_args['repair'] );

		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $query->posts ) ) {
			WP_CLI::success( 'No attachments found.' );
			return;
		}

		$renamer = class_exists( 'MSH_Safe_Rename_System' ) ? MSH_Safe_Rename_System::get_instance() : null;
		$issues  = 0;

		foreach ( $query->posts as $attachment_id ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) ) {
				continue;
			}

			$file      = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$uploads   = wp_get_upload_dir();
			$base_dir  = trailingslashit( $uploads['basedir'] );
			$main_path = $file ? $base_dir . ltrim( $file, '/' ) : get_attached_file( $attachment_id );
			$dir       = $main_path ? dirname( $main_path ) : $uploads['basedir'];
			$ext       = pathinfo( $main_path, PATHINFO_EXTENSION );
			$problems  = array();

			foreach ( $metadata['sizes'] as $size_key => $size_data ) {
				$size_file = $size_data['file'] ?? '';
				$full_path = $size_file ? trailingslashit( $dir ) . $size_file : '';

				if ( $size_file === '' || ! preg_match( '/-\d+x\d+\.[a-z0-9]+$/i', $size_file ) ) {
					$problems[] = "Size '{$size_key}' missing WxH suffix ({$size_file})";
					continue;
				}

				if ( ! file_exists( $full_path ) ) {
					$problems[] = "Size '{$size_key}' file missing on disk ({$size_file})";
				}
			}

			if ( empty( $problems ) ) {
				continue;
			}

			$issues++;
			WP_CLI::warning( sprintf( 'Attachment %d has corrupted metadata:', $attachment_id ) );
			foreach ( $problems as $problem ) {
				WP_CLI::line( "  - {$problem}" );
			}

			if ( $repair && $renamer ) {
				$result = $renamer->repair_corrupted_attachment( $attachment_id, false );
				if ( is_wp_error( $result ) ) {
					WP_CLI::error( sprintf( 'Repair failed for attachment %d: %s', $attachment_id, $result->get_error_message() ) );
				} else {
					WP_CLI::success( sprintf( 'Attempted repair for attachment %d.', $attachment_id ) );
				}
			}
		}

		if ( $issues === 0 ) {
			WP_CLI::success( 'No corrupted metadata detected in scanned attachments.' );
		} else {
			WP_CLI::warning( sprintf( 'Detected %d attachment(s) with corrupted size metadata.', $issues ) );
			if ( ! $repair ) {
				WP_CLI::line( 'Re-run with --repair to attempt automatic healing.' );
			}
		}
	}

	/**
	 * Display context trace and staged metadata for attachments.
	 *
	 * ## OPTIONS
	 *
	 * <attachment-id>...
	 * : One or more attachment IDs to inspect.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh trace 616 1045 2049
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function trace( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( 'Please provide at least one attachment ID.' );
		}

		foreach ( $args as $raw_id ) {
			$attachment_id = (int) $raw_id;
			if ( $attachment_id <= 0 ) {
				WP_CLI::warning( "Skipping invalid attachment ID: {$raw_id}" );
				continue;
			}

			$context_trace = get_post_meta( $attachment_id, '_msh_context_trace', true );
			$staged_meta   = get_post_meta( $attachment_id, '_msh_ai_staged_meta', true );
			$auto_context  = get_post_meta( $attachment_id, '_msh_auto_context', true );

			WP_CLI::line( '' );
			WP_CLI::line( '=== Attachment ' . $attachment_id . ' ===' );
			WP_CLI::line( 'Auto Context: ' . ( $auto_context ? $auto_context : '(none)' ) );

			WP_CLI::line( 'Context Trace:' );
			if ( empty( $context_trace ) ) {
				WP_CLI::line( '  (no trace saved)' );
			} else {
				if ( is_string( $context_trace ) ) {
					// Stored as JSON string.
					WP_CLI::line( wp_json_encode( json_decode( $context_trace, true ), JSON_PRETTY_PRINT ) );
				} else {
					WP_CLI::line( wp_json_encode( $context_trace, JSON_PRETTY_PRINT ) );
				}
			}

			WP_CLI::line( 'Staged Metadata:' );
			if ( empty( $staged_meta ) ) {
				WP_CLI::line( '  (no staged metadata)' );
			} else {
				WP_CLI::line( wp_json_encode( $staged_meta, JSON_PRETTY_PRINT ) );
			}
		}
	}
}

WP_CLI::add_command( 'msh', 'MSH_CLI' );
