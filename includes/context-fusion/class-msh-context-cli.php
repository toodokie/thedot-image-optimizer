<?php
/**
 * Context Fusion Layer - WP-CLI Commands
 *
 * CLI commands for managing context extraction and analysis.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage image context extraction and analysis
 *
 * ## EXAMPLES
 *
 *     # Extract context for a single post
 *     $ wp msh context extract --post-id=123
 *
 *     # Extract context for all published posts
 *     $ wp msh context extract --all
 *
 *     # Reindex all context (full rebuild)
 *     $ wp msh context reindex
 *
 *     # Show context statistics
 *     $ wp msh context stats
 *
 *     # Show context for a specific image
 *     $ wp msh context show --media-id=456
 *
 *     # Find posts using an image
 *     $ wp msh context find-posts --media-id=456
 *
 * @when after_wp_load
 */
class MSH_Context_CLI {

	/**
	 * Extract context for posts
	 *
	 * ## OPTIONS
	 *
	 * [--post-id=<id>]
	 * : Extract context for a specific post ID
	 *
	 * [--all]
	 * : Extract context for all published posts
	 *
	 * [--post-type=<type>]
	 * : Extract context for specific post type (default: post,page)
	 *
	 * [--locale=<locale>]
	 * : Extract context for specific locale (default: site locale)
	 *
	 * [--force]
	 * : Force re-extraction even if source hash unchanged
	 *
	 * [--batch-size=<size>]
	 * : Number of posts to process per batch (default: 10)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context extract --post-id=123
	 *     wp msh context extract --all --post-type=post
	 *     wp msh context extract --all --locale=es_ES
	 *
	 * @subcommand extract
	 */
	public function extract( $args, $assoc_args ) {
		$post_id   = isset( $assoc_args['post-id'] ) ? (int) $assoc_args['post-id'] : null;
		$all       = isset( $assoc_args['all'] );
		$post_type = isset( $assoc_args['post-type'] ) ? explode( ',', $assoc_args['post-type'] ) : array( 'post', 'page' );
		$locale    = isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : get_locale();
		$force     = isset( $assoc_args['force'] );
		$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 10;

		// Validate input
		if ( ! $post_id && ! $all ) {
			WP_CLI::error( 'You must specify either --post-id or --all' );
		}

		if ( $post_id && $all ) {
			WP_CLI::error( 'You cannot specify both --post-id and --all' );
		}

		// Single post extraction
		if ( $post_id ) {
			WP_CLI::log( "Extracting context for post {$post_id}..." );

			$result = $this->extract_post_context( $post_id, $locale, $force );

			if ( $result['success'] ) {
				WP_CLI::success( "Extracted context for {$result['images_found']} images in post {$post_id}" );
				WP_CLI::log( "  - Contexts stored: {$result['contexts_stored']}" );
				WP_CLI::log( "  - Contexts updated: {$result['contexts_updated']}" );
				WP_CLI::log( "  - Contexts skipped: {$result['contexts_skipped']}" );
			} else {
				WP_CLI::error( $result['error'] );
			}

			return;
		}

		// Bulk extraction
		WP_CLI::log( 'Starting bulk context extraction...' );

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'paged'          => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		$query = new WP_Query( $query_args );
		$total = $query->found_posts;

		if ( $total === 0 ) {
			WP_CLI::warning( 'No posts found to process' );
			return;
		}

		WP_CLI::log( "Found {$total} posts to process" );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Extracting context', $total );

		$stats = array(
			'processed'        => 0,
			'images_found'     => 0,
			'contexts_stored'  => 0,
			'contexts_updated' => 0,
			'contexts_skipped' => 0,
			'errors'           => 0,
		);

		$page = 1;

		while ( $page <= $query->max_num_pages ) {
			$query_args['paged'] = $page;
			$query               = new WP_Query( $query_args );

			foreach ( $query->posts as $post_id ) {
				$result = $this->extract_post_context( $post_id, $locale, $force );

				if ( $result['success'] ) {
					$stats['processed']++;
					$stats['images_found']     += $result['images_found'];
					$stats['contexts_stored']  += $result['contexts_stored'];
					$stats['contexts_updated'] += $result['contexts_updated'];
					$stats['contexts_skipped'] += $result['contexts_skipped'];
				} else {
					$stats['errors']++;
					WP_CLI::warning( "Post {$post_id}: {$result['error']}" );
				}

				$progress->tick();
			}

			$page++;

			// Clear cache to prevent memory issues
			wp_cache_flush();
		}

		$progress->finish();

		// Show summary
		WP_CLI::success( 'Bulk extraction complete!' );
		WP_CLI::log( '' );
		WP_CLI::log( 'Summary:' );
		WP_CLI::log( "  - Posts processed: {$stats['processed']}" );
		WP_CLI::log( "  - Images found: {$stats['images_found']}" );
		WP_CLI::log( "  - Contexts stored: {$stats['contexts_stored']}" );
		WP_CLI::log( "  - Contexts updated: {$stats['contexts_updated']}" );
		WP_CLI::log( "  - Contexts skipped: {$stats['contexts_skipped']}" );
		WP_CLI::log( "  - Errors: {$stats['errors']}" );
	}

	/**
	 * Reindex all context (full rebuild)
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context reindex --yes
	 *
	 * @subcommand reindex
	 */
	public function reindex( $args, $assoc_args ) {
		$skip_confirm = isset( $assoc_args['yes'] );

		if ( ! $skip_confirm ) {
			WP_CLI::confirm( 'This will delete all existing context data and rebuild from scratch. Continue?' );
		}

		WP_CLI::log( 'Truncating context table...' );
		MSH_Context_Database::truncate();

		WP_CLI::success( 'Context table truncated' );

		// Trigger extraction for all posts
		$this->extract(
			array(),
			array(
				'all'        => true,
				'force'      => true,
				'batch-size' => 10,
			)
		);
	}

	/**
	 * Show context statistics
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context stats
	 *
	 * @subcommand stats
	 */
	public function stats( $args, $assoc_args ) {
		$stats = MSH_Context_Database::get_stats();

		if ( ! $stats['table_exists'] ) {
			WP_CLI::error( 'Context table does not exist. Run plugin activation first.' );
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Context Statistics:' );
		WP_CLI::log( '===================' );
		WP_CLI::log( "Database version: {$stats['db_version']}" );
		WP_CLI::log( "Total contexts: {$stats['total_rows']}" );
		WP_CLI::log( "Unique images: {$stats['unique_media']}" );
		WP_CLI::log( "Unique posts: {$stats['unique_posts']}" );
		WP_CLI::log( '' );

		// Show locale breakdown
		if ( ! empty( $stats['locale_counts'] ) ) {
			WP_CLI::log( 'Contexts by Locale:' );
			foreach ( $stats['locale_counts'] as $row ) {
				WP_CLI::log( "  - {$row['locale']}: {$row['count']}" );
			}
			WP_CLI::log( '' );
		}

		// Show intent breakdown
		if ( ! empty( $stats['intent_counts'] ) ) {
			WP_CLI::log( 'Contexts by Intent:' );
			foreach ( $stats['intent_counts'] as $row ) {
				WP_CLI::log( "  - {$row['intent']}: {$row['count']}" );
			}
			WP_CLI::log( '' );
		}
	}

	/**
	 * Show context for a specific image
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<id>
	 * : Media ID to show context for
	 *
	 * [--locale=<locale>]
	 * : Show context for specific locale (default: site locale)
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml) (default: table)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context show --media-id=456
	 *     wp msh context show --media-id=456 --format=json
	 *
	 * @subcommand show
	 */
	public function show( $args, $assoc_args ) {
		$media_id = isset( $assoc_args['media-id'] ) ? (int) $assoc_args['media-id'] : null;
		$locale   = isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : get_locale();
		$format   = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( ! $media_id ) {
			WP_CLI::error( 'You must specify --media-id' );
		}

		// Get rollup
		$manager = MSH_Context_Manager::get_instance();
		$rollup  = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			WP_CLI::warning( "No context found for media {$media_id} in locale {$locale}" );
			return;
		}

		// Format output
		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $rollup, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			WP_CLI::log( \WP_CLI\Utils\mustache_render( '{{#rollup}}{{key}}: {{value}}{{/rollup}}', array( 'rollup' => $rollup ) ) );
			return;
		}

		// Table format (default)
		WP_CLI::log( '' );
		WP_CLI::log( "Context for Media #{$media_id} (Locale: {$locale})" );
		WP_CLI::log( '======================================' );
		WP_CLI::log( "Intent: {$rollup['intent']} (confidence: {$rollup['intent_confidence']}%)" );
		WP_CLI::log( "Context score: {$rollup['avg_context_score']}/100" );
		WP_CLI::log( "Used in {$rollup['context_count']} contexts" );
		WP_CLI::log( "Total usage count: {$rollup['total_usage_count']}" );
		WP_CLI::log( '' );

		if ( ! empty( $rollup['keywords'] ) ) {
			WP_CLI::log( 'Keywords: ' . implode( ', ', $rollup['keywords'] ) );
			WP_CLI::log( '' );
		}

		if ( ! empty( $rollup['entities']['brands'] ) || ! empty( $rollup['entities']['places'] ) || ! empty( $rollup['entities']['people'] ) ) {
			WP_CLI::log( 'Entities:' );
			if ( ! empty( $rollup['entities']['brands'] ) ) {
				WP_CLI::log( '  Brands: ' . implode( ', ', $rollup['entities']['brands'] ) );
			}
			if ( ! empty( $rollup['entities']['places'] ) ) {
				WP_CLI::log( '  Places: ' . implode( ', ', $rollup['entities']['places'] ) );
			}
			if ( ! empty( $rollup['entities']['people'] ) ) {
				WP_CLI::log( '  People: ' . implode( ', ', $rollup['entities']['people'] ) );
			}
			WP_CLI::log( '' );
		}

		if ( ! empty( $rollup['top_subject'] ) ) {
			WP_CLI::log( "Top subject: {$rollup['top_subject']}" );
		}
	}

	/**
	 * Find posts using a specific image
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<id>
	 * : Media ID to find posts for
	 *
	 * [--locale=<locale>]
	 * : Filter by locale
	 *
	 * [--format=<format>]
	 * : Output format (table, json, ids) (default: table)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context find-posts --media-id=456
	 *     wp msh context find-posts --media-id=456 --format=ids
	 *
	 * @subcommand find-posts
	 */
	public function find_posts( $args, $assoc_args ) {
		$media_id = isset( $assoc_args['media-id'] ) ? (int) $assoc_args['media-id'] : null;
		$locale   = isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : null;
		$format   = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( ! $media_id ) {
			WP_CLI::error( 'You must specify --media-id' );
		}

		// Find posts
		$manager  = MSH_Context_Manager::get_instance();
		$post_ids = $manager->find_posts_using_media( $media_id, $locale );

		if ( empty( $post_ids ) ) {
			WP_CLI::warning( "Media {$media_id} is not used in any posts" );
			return;
		}

		// Format output
		if ( 'ids' === $format ) {
			WP_CLI::log( implode( ' ', $post_ids ) );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $post_ids, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format (default)
		$posts_data = array();
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$posts_data[] = array(
					'ID'    => $post_id,
					'Title' => $post->post_title,
					'Type'  => $post->post_type,
					'Status' => $post->post_status,
				);
			}
		}

		\WP_CLI\Utils\format_items( 'table', $posts_data, array( 'ID', 'Title', 'Type', 'Status' ) );
	}

	/**
	 * Extract context for a single post
	 *
	 * @param int    $post_id Post ID.
	 * @param string $locale  Locale code.
	 * @param bool   $force   Force re-extraction.
	 * @return array Result with success, counts, and error
	 */
	private function extract_post_context( $post_id, $locale, $force = false ) {
		$result = array(
			'success'          => false,
			'images_found'     => 0,
			'contexts_stored'  => 0,
			'contexts_updated' => 0,
			'contexts_skipped' => 0,
			'error'            => '',
		);

		// Get post
		$post = get_post( $post_id );

		if ( ! $post ) {
			$result['error'] = 'Post not found';
			return $result;
		}

		// Initialize components
		$extractor  = new MSH_Context_Extractor();
		$manager    = MSH_Context_Manager::get_instance();
		$classifier = new MSH_Intent_Classifier();
		$normalizer = new MSH_Keyword_Normalizer();

		// Extract post context
		$post_context = $extractor->extract_post_context( $post, $locale );
		$source_hash  = $extractor->calculate_source_hash( $post );

		// Find all images in post
		$images = $extractor->find_images_in_post( $post );
		$result['images_found'] = count( $images );

		if ( empty( $images ) ) {
			$result['success'] = true;
			return $result;
		}

		// Process each image
		foreach ( $images as $image_data ) {
			$media_id   = $image_data['media_id'];
			$usage_type = $image_data['usage_type'];
			$block_path = $image_data['block_path'];

			// Check if context exists and unchanged (unless force)
			if ( ! $force ) {
				global $wpdb;
				$table_name = MSH_Context_Database::get_table_name();

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT source_hash FROM {$table_name}
						WHERE media_id = %d AND post_id = %d AND locale = %s AND usage_type = %s AND block_path <=> %s",
						$media_id,
						$post_id,
						$locale,
						$usage_type,
						$block_path
					)
				);

				if ( $existing === $source_hash ) {
					$result['contexts_skipped']++;
					continue;
				}
			}

			// Classify intent
			$intent_result = $classifier->classify( $media_id, $post_context, $locale );

			// Extract keywords
			$combined_text = $post_context['title'] . ' ' . $post_context['excerpt'] . ' ' . $post_context['content'];
			$keywords      = $normalizer->extract_keywords( $combined_text, $locale, 20 );

			// Build context data
			$context = array(
				'media_id'          => $media_id,
				'post_id'           => $post_id,
				'locale'            => $locale,
				'usage_type'        => $usage_type,
				'block_path'        => $block_path,
				'subject'           => $post_context['title'],
				'intent'            => $intent_result['intent'],
				'intent_confidence' => $intent_result['confidence'],
				'entities'          => $normalizer->extract_entities( $combined_text, $locale ),
				'keywords'          => $keywords,
				'rules_fired'       => $intent_result['rules_fired'],
				'source_hash'       => $source_hash,
				'context_score'     => $this->calculate_context_score( $intent_result, $keywords ),
			);

			// Store context
			$stored = $manager->store_context( $context );

			if ( $stored ) {
				// Check if it was an insert or update by checking if ID matches existing
				if ( $existing ) {
					$result['contexts_updated']++;
				} else {
					$result['contexts_stored']++;
				}
			}
		}

		$result['success'] = true;
		return $result;
	}

	/**
	 * Calculate context score based on intent and keywords
	 *
	 * @param array $intent_result Intent classification result.
	 * @param array $keywords      Extracted keywords.
	 * @return int Score 0-100
	 */
	private function calculate_context_score( $intent_result, $keywords ) {
		$score = 0;

		// Intent confidence contributes 60%
		if ( 'on_topic' === $intent_result['intent'] ) {
			$score += (int) ( $intent_result['confidence'] * 0.6 );
		} elseif ( 'off_topic' === $intent_result['intent'] ) {
			$score += (int) ( ( 100 - $intent_result['confidence'] ) * 0.3 );
		} else {
			$score += 20; // Unknown intent = low score
		}

		// Keyword count contributes 40%
		$keyword_count = count( $keywords );
		$keyword_score = min( 100, $keyword_count * 5 ); // 5 points per keyword, max 100
		$score        += (int) ( $keyword_score * 0.4 );

		return min( 100, $score );
	}

	/**
	 * Find images with similar context
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<id>
	 * : Media ID to find similar images for
	 *
	 * [--min-similarity=<similarity>]
	 * : Minimum similarity threshold 0-1 (default: 0.6)
	 *
	 * [--limit=<limit>]
	 * : Maximum results to return (default: 10)
	 *
	 * [--intent-match]
	 * : Require same intent classification
	 *
	 * [--format=<format>]
	 * : Output format (table, json) (default: table)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context find-similar --media-id=1692
	 *     wp msh context find-similar --media-id=1692 --min-similarity=0.8 --intent-match
	 *
	 * @subcommand find-similar
	 */
	public function find_similar( $args, $assoc_args ) {
		$media_id = isset( $assoc_args['media-id'] ) ? (int) $assoc_args['media-id'] : null;

		if ( ! $media_id ) {
			WP_CLI::error( 'You must specify --media-id' );
		}

		$options = array(
			'min_similarity' => isset( $assoc_args['min-similarity'] ) ? (float) $assoc_args['min-similarity'] : 0.6,
			'limit'          => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10,
			'intent_match'   => isset( $assoc_args['intent-match'] ),
			'locale'         => isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : get_locale(),
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$recommender = new MSH_Context_Recommender();
		$similar     = $recommender->find_similar_images( $media_id, $options );

		if ( empty( $similar ) ) {
			WP_CLI::warning( "No similar images found for media {$media_id}" );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $similar, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format
		$table_data = array();
		foreach ( $similar as $item ) {
			$attachment = get_post( $item['media_id'] );

			$table_data[] = array(
				'ID'               => $item['media_id'],
				'Title'            => $attachment ? $attachment->post_title : 'N/A',
				'Similarity'       => round( $item['similarity'] * 100, 1 ) . '%',
				'Context Score'    => $item['context_score'],
				'Intent'           => ucfirst( $item['intent'] ),
				'Matching Keywords' => implode( ', ', array_slice( $item['matching_keywords'], 0, 3 ) ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $table_data, array( 'ID', 'Title', 'Similarity', 'Context Score', 'Intent', 'Matching Keywords' ) );
	}

	/**
	 * Find orphaned images (no on-topic usage)
	 *
	 * ## OPTIONS
	 *
	 * [--max-score=<score>]
	 * : Maximum context score to consider orphaned (default: 50)
	 *
	 * [--limit=<limit>]
	 * : Maximum results to return (default: 50)
	 *
	 * [--format=<format>]
	 * : Output format (table, json, ids) (default: table)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context find-orphaned
	 *     wp msh context find-orphaned --max-score=40 --limit=100
	 *
	 * @subcommand find-orphaned
	 */
	public function find_orphaned( $args, $assoc_args ) {
		$options = array(
			'max_score' => isset( $assoc_args['max-score'] ) ? (int) $assoc_args['max-score'] : 50,
			'limit'     => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50,
			'locale'    => isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : get_locale(),
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$recommender = new MSH_Context_Recommender();
		$orphaned    = $recommender->find_orphaned_images( $options );

		if ( empty( $orphaned ) ) {
			WP_CLI::success( 'No orphaned images found!' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d orphaned images:', count( $orphaned ) ) );
		WP_CLI::log( '' );

		if ( 'ids' === $format ) {
			$ids = wp_list_pluck( $orphaned, 'media_id' );
			WP_CLI::log( implode( ' ', $ids ) );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $orphaned, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format
		$table_data = array();
		foreach ( $orphaned as $item ) {
			$table_data[] = array(
				'ID'          => $item['media_id'],
				'Title'       => $item['title'],
				'Usage Count' => $item['usage_count'],
				'Best Score'  => $item['best_score'],
				'Intents'     => $item['intents'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $table_data, array( 'ID', 'Title', 'Usage Count', 'Best Score', 'Intents' ) );
	}

	/**
	 * Suggest better images for a post
	 *
	 * ## OPTIONS
	 *
	 * --post-id=<id>
	 * : Post ID to suggest improvements for
	 *
	 * --media-id=<id>
	 * : Current media ID to find improvements for
	 *
	 * [--min-improvement=<score>]
	 * : Minimum score improvement required (default: 10)
	 *
	 * [--limit=<limit>]
	 * : Maximum suggestions to return (default: 5)
	 *
	 * [--format=<format>]
	 * : Output format (table, json) (default: table)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context suggest-better --post-id=1787 --media-id=1692
	 *
	 * @subcommand suggest-better
	 */
	public function suggest_better( $args, $assoc_args ) {
		$post_id  = isset( $assoc_args['post-id'] ) ? (int) $assoc_args['post-id'] : null;
		$media_id = isset( $assoc_args['media-id'] ) ? (int) $assoc_args['media-id'] : null;

		if ( ! $post_id || ! $media_id ) {
			WP_CLI::error( 'You must specify both --post-id and --media-id' );
		}

		$options = array(
			'min_improvement' => isset( $assoc_args['min-improvement'] ) ? (int) $assoc_args['min-improvement'] : 10,
			'limit'           => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 5,
			'locale'          => isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : get_locale(),
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$recommender  = new MSH_Context_Recommender();
		$suggestions = $recommender->suggest_better_fit( $post_id, $media_id, $options );

		if ( empty( $suggestions ) ) {
			WP_CLI::success( 'No better alternatives found (current image is already optimal)' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d better alternatives:', count( $suggestions ) ) );
		WP_CLI::log( '' );

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $suggestions, JSON_PRETTY_PRINT ) );
			return;
		}

		// Table format
		$table_data = array();
		foreach ( $suggestions as $item ) {
			$attachment = get_post( $item['media_id'] );

			$table_data[] = array(
				'ID'              => $item['media_id'],
				'Title'           => $attachment ? $attachment->post_title : 'N/A',
				'Improvement'     => '+' . $item['improvement'],
				'Current Score'   => $item['current_score'],
				'Suggested Score' => $item['suggested_score'],
				'Reason'          => $item['reason'],
			);
		}

		\WP_CLI\Utils\format_items( 'table', $table_data, array( 'ID', 'Title', 'Improvement', 'Current Score', 'Suggested Score', 'Reason' ) );
	}

	/**
	 * Show analytics overview
	 *
	 * ## OPTIONS
	 *
	 * [--locale=<locale>]
	 * : Locale to analyze
	 * ---
	 * default: current
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context analytics
	 *     wp msh context analytics --locale=en_US --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function analytics( $args, $assoc_args ) {
		$locale = isset( $assoc_args['locale'] ) && 'current' !== $assoc_args['locale']
			? $assoc_args['locale']
			: get_locale();
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$analytics = new MSH_Context_Analytics();
		$overview = $analytics->get_overview( array( 'locale' => $locale ) );
		$keyword_stats = $analytics->get_keyword_stats( array( 'locale' => $locale, 'limit' => 10 ) );
		$post_type_stats = $analytics->get_post_type_stats( array( 'locale' => $locale ) );

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( array(
				'overview' => $overview,
				'keywords' => $keyword_stats,
				'post_types' => $post_type_stats,
			), JSON_PRETTY_PRINT ) );
			return;
		}

		// Display overview
		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G  CONTEXT ANALYTICS OVERVIEW%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( '' );

		WP_CLI::line( WP_CLI::colorize( '%YTotals:%n' ) );
		WP_CLI::line( '  Contexts:     ' . number_format( $overview['totals']['contexts'] ) );
		WP_CLI::line( '  Images:       ' . number_format( $overview['totals']['images'] ) );
		WP_CLI::line( '  Posts:        ' . number_format( $overview['totals']['posts'] ) );
		WP_CLI::line( '  Average Score: ' . $overview['totals']['avg_score'] );
		WP_CLI::line( '' );

		// Intent distribution
		WP_CLI::line( WP_CLI::colorize( '%YIntent Distribution:%n' ) );
		$total = array_sum( $overview['intent_distribution'] );
		foreach ( $overview['intent_distribution'] as $intent => $count ) {
			$pct = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
			$color = 'on_topic' === $intent ? '%G' : ( 'off_topic' === $intent ? '%R' : '%Y' );
			WP_CLI::line( '  ' . WP_CLI::colorize( $color . ucfirst( str_replace( '_', ' ', $intent ) ) . ':%n ' ) . number_format( $count ) . ' (' . $pct . '%)' );
		}
		WP_CLI::line( '' );

		// Top keywords
		WP_CLI::line( WP_CLI::colorize( '%YTop 10 Keywords:%n' ) );
		$i = 1;
		foreach ( $keyword_stats['top_keywords'] as $keyword => $count ) {
			WP_CLI::line( '  ' . $i . '. ' . $keyword . ' (' . number_format( $count ) . ' uses)' );
			$i++;
		}
		WP_CLI::line( '' );

		// Post type breakdown
		if ( ! empty( $post_type_stats ) ) {
			WP_CLI::line( WP_CLI::colorize( '%YPost Type Breakdown:%n' ) );
			$table_data = array();
			foreach ( $post_type_stats as $stat ) {
				$table_data[] = array(
					'Post Type'      => $stat['label'],
					'Contexts'       => number_format( $stat['context_count'] ),
					'Unique Images'  => number_format( $stat['unique_images'] ),
					'Avg Score'      => $stat['avg_score'],
				);
			}
			\WP_CLI\Utils\format_items( 'table', $table_data, array( 'Post Type', 'Contexts', 'Unique Images', 'Avg Score' ) );
		}

		WP_CLI::success( 'Analytics generated successfully' );
	}

	/**
	 * Generate multilingual metadata for an image
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<media-id>
	 * : Media attachment ID
	 *
	 * [--locale=<locale>]
	 * : Specific locale to generate, or 'all' for all active locales
	 * ---
	 * default: all
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context i18n-generate --media-id=1692
	 *     wp msh context i18n-generate --media-id=1692 --locale=es_ES
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function i18n_generate( $args, $assoc_args ) {
		$media_id = isset( $assoc_args['media-id'] ) ? (int) $assoc_args['media-id'] : 0;
		$locale = isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : 'all';

		if ( ! $media_id ) {
			WP_CLI::error( 'Please provide a valid media ID' );
		}

		$i18n = MSH_I18n_Metadata::get_instance();

		if ( 'all' === $locale ) {
			WP_CLI::line( 'Generating metadata for all active locales...' );
			$results = $i18n->generate_for_all_locales( $media_id );

			$table_data = array();
			foreach ( $results as $loc => $result ) {
				if ( is_wp_error( $result ) ) {
					$table_data[] = array(
						'Locale'   => $loc,
						'Status'   => 'Error',
						'Alt Text' => $result->get_error_message(),
					);
				} else {
					$table_data[] = array(
						'Locale'   => $loc,
						'Status'   => 'Success',
						'Alt Text' => substr( $result['alt_text'], 0, 60 ) . '...',
					);
				}
			}

			\WP_CLI\Utils\format_items( 'table', $table_data, array( 'Locale', 'Status', 'Alt Text' ) );
			WP_CLI::success( 'Generated metadata for ' . count( $results ) . ' locales' );
		} else {
			WP_CLI::line( "Generating metadata for locale: {$locale}..." );
			$result = $i18n->generate_for_locale( $media_id, $locale );

			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			} else {
				WP_CLI::line( '' );
				WP_CLI::line( 'Alt Text: ' . $result['alt_text'] );
				WP_CLI::success( "Generated metadata for {$locale}" );
			}
		}
	}

	/**
	 * Analyze translation quality
	 *
	 * ## OPTIONS
	 *
	 * --media-id=<media-id>
	 * : Media attachment ID
	 *
	 * --source=<source>
	 * : Source locale
	 *
	 * --target=<target>
	 * : Target locale
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context i18n-analyze --media-id=1692 --source=en_US --target=es_ES
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function i18n_analyze( $args, $assoc_args ) {
		$media_id = isset( $assoc_args['media-id'] ) ? (int) $assoc_args['media-id'] : 0;
		$source = isset( $assoc_args['source'] ) ? $assoc_args['source'] : get_locale();
		$target = isset( $assoc_args['target'] ) ? $assoc_args['target'] : '';

		if ( ! $media_id || ! $target ) {
			WP_CLI::error( 'Please provide media-id and target locale' );
		}

		$analyzer = new MSH_Translation_Analyzer();
		$analysis = $analyzer->analyze_translation( $media_id, $source, $target );

		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G  TRANSLATION QUALITY ANALYSIS%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( '' );

		WP_CLI::line( 'Source Locale: ' . $source );
		WP_CLI::line( 'Target Locale: ' . $target );
		WP_CLI::line( '' );

		WP_CLI::line( WP_CLI::colorize( '%YQuality Metrics:%n' ) );
		WP_CLI::line( '  Overall Score:     ' . $analysis['quality_score'] . '/100' );
		WP_CLI::line( '  Keyword Coverage:  ' . round( $analysis['keyword_coverage'] * 100 ) . '%' );
		WP_CLI::line( '  Context Alignment: ' . round( $analysis['context_alignment'] * 100 ) . '%' );
		WP_CLI::line( '' );

		if ( ! empty( $analysis['suggestions'] ) ) {
			WP_CLI::line( WP_CLI::colorize( '%YSuggestions:%n' ) );
			foreach ( $analysis['suggestions'] as $i => $suggestion ) {
				WP_CLI::line( '  ' . ( $i + 1 ) . '. ' . $suggestion );
			}
			WP_CLI::line( '' );
		}

		WP_CLI::success( 'Analysis complete' );
	}

	/**
	 * Create analytics snapshot
	 *
	 * ## OPTIONS
	 *
	 * [--locale=<locale>]
	 * : Locale to snapshot
	 * ---
	 * default: current
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context snapshot
	 *     wp msh context snapshot --locale=en_US
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function snapshot( $args, $assoc_args ) {
		$locale = isset( $assoc_args['locale'] ) && 'current' !== $assoc_args['locale']
			? $assoc_args['locale']
			: get_locale();

		$snapshots = MSH_Context_Snapshots::get_instance();
		$result = $snapshots->create_snapshot( $locale );

		if ( $result ) {
			WP_CLI::success( "Snapshot created for {$locale}" );
		} else {
			WP_CLI::error( 'Failed to create snapshot' );
		}
	}

	/**
	 * Show trend data
	 *
	 * ## OPTIONS
	 *
	 * [--locale=<locale>]
	 * : Locale to analyze
	 * ---
	 * default: current
	 * ---
	 *
	 * [--days=<days>]
	 * : Number of days to analyze
	 * ---
	 * default: 30
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context trends
	 *     wp msh context trends --days=7
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function trends( $args, $assoc_args ) {
		$locale = isset( $assoc_args['locale'] ) && 'current' !== $assoc_args['locale']
			? $assoc_args['locale']
			: get_locale();
		$days = isset( $assoc_args['days'] ) ? (int) $assoc_args['days'] : 30;

		$snapshots = MSH_Context_Snapshots::get_instance();
		$trends = $snapshots->get_trends(
			array(
				'locale' => $locale,
				'days'   => $days,
			)
		);

		if ( empty( $trends ) ) {
			WP_CLI::warning( 'No trend data available. Run snapshots for a few days first.' );
			return;
		}

		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G  CONTEXT TRENDS (' . $days . ' DAYS)%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( '' );

		// Show trend indicators
		$analytics = new MSH_Context_Analytics();
		$indicators = $analytics->get_trend_indicators(
			array(
				'locale' => $locale,
				'days'   => $days,
			)
		);

		WP_CLI::line( WP_CLI::colorize( '%YTrend Indicators:%n' ) );

		foreach ( $indicators as $name => $trend ) {
			$label = ucwords( str_replace( '_', ' ', $name ) );
			$arrow = $trend['direction'] === 'up' ? '↑' : ( $trend['direction'] === 'down' ? '↓' : '→' );
			$color = $trend['direction'] === 'up' ? '%G' : ( $trend['direction'] === 'down' ? '%R' : '%Y' );

			// Handle cases where first/last may not be set (insufficient data)
			$first = isset( $trend['first'] ) ? round( $trend['first'], 1 ) : 0;
			$last = isset( $trend['last'] ) ? round( $trend['last'], 1 ) : 0;

			WP_CLI::line(
				'  ' . $label . ': ' .
				WP_CLI::colorize( $color . $arrow . ' ' . $trend['percent'] . '%' . '%n' ) .
				' (' . $first . ' → ' . $last . ')'
			);
		}

		WP_CLI::line( '' );

		// Show trend table
		$table_data = array();
		foreach ( $trends as $snapshot ) {
			$table_data[] = array(
				'Date'          => $snapshot['snapshot_date'],
				'Contexts'      => number_format( $snapshot['total_contexts'] ),
				'Images'        => number_format( $snapshot['total_images'] ),
				'Avg Score'     => $snapshot['avg_context_score'],
				'On-Topic'      => number_format( $snapshot['on_topic_count'] ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $table_data, array( 'Date', 'Contexts', 'Images', 'Avg Score', 'On-Topic' ) );

		WP_CLI::success( 'Trend data retrieved' );
	}

	/**
	 * Export analytics to CSV
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Export type (overview|keywords|post_types|trends)
	 *
	 * [--locale=<locale>]
	 * : Locale to export
	 * ---
	 * default: current
	 * ---
	 *
	 * [--output=<file>]
	 * : Output file path
	 *
	 * [--days=<days>]
	 * : Number of days for trends export
	 * ---
	 * default: 30
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context export overview --output=overview.csv
	 *     wp msh context export trends --days=90 --output=trends.csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function export( $args, $assoc_args ) {
		$type = isset( $args[0] ) ? $args[0] : 'overview';
		$locale = isset( $assoc_args['locale'] ) && 'current' !== $assoc_args['locale']
			? $assoc_args['locale']
			: get_locale();
		$output = isset( $assoc_args['output'] ) ? $assoc_args['output'] : null;
		$days = isset( $assoc_args['days'] ) ? (int) $assoc_args['days'] : 30;

		$valid_types = array( 'overview', 'keywords', 'post_types', 'trends' );
		if ( ! in_array( $type, $valid_types, true ) ) {
			WP_CLI::error( "Invalid export type. Must be one of: " . implode( ', ', $valid_types ) );
		}

		$analytics = new MSH_Context_Analytics();
		$csv = $analytics->export_to_csv(
			$type,
			array(
				'locale' => $locale,
				'days'   => $days,
			)
		);

		if ( empty( $csv ) ) {
			WP_CLI::warning( 'No data to export' );
			return;
		}

		if ( $output ) {
			// Write to file
			$result = file_put_contents( $output, $csv );
			if ( false === $result ) {
				WP_CLI::error( "Failed to write to {$output}" );
			} else {
				WP_CLI::success( "Exported {$type} data to {$output}" );
			}
		} else {
			// Output to stdout
			WP_CLI::line( $csv );
		}
	}

	/**
	 * Schedule batch extraction
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Schedule all published posts
	 *
	 * [--post-ids=<ids>]
	 * : Comma-separated list of post IDs
	 *
	 * [--batch-size=<size>]
	 * : Posts per batch
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--start-time=<time>]
	 * : Start time (e.g., "tomorrow 2am", "2024-12-25 02:00:00")
	 * ---
	 * default: tomorrow 2am
	 * ---
	 *
	 * [--smart]
	 * : Only schedule posts that need updates
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context schedule-batch --all
	 *     wp msh context schedule-batch --all --smart --start-time="tomorrow 2am"
	 *     wp msh context schedule-batch --post-ids=1,2,3 --batch-size=5
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function schedule_batch( $args, $assoc_args ) {
		$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 10;
		$start_time_str = isset( $assoc_args['start-time'] ) ? $assoc_args['start-time'] : 'tomorrow 2am';
		$start_time = strtotime( $start_time_str );
		$smart = isset( $assoc_args['smart'] );

		if ( false === $start_time ) {
			WP_CLI::error( "Invalid start time: {$start_time_str}" );
		}

		$scheduler = MSH_Context_Batch_Scheduler::get_instance();

		if ( $smart ) {
			// Smart reindex
			WP_CLI::line( 'Analyzing posts for changes...' );

			$result = $scheduler->schedule_smart_reindex(
				array(
					'batch_size' => $batch_size,
					'start_time' => $start_time,
				)
			);

			WP_CLI::line( '' );
			WP_CLI::line( 'Results:' );
			WP_CLI::line( '  Total posts:        ' . number_format( $result['total_posts'] ) );
			WP_CLI::line( '  Need update:        ' . number_format( $result['needs_update'] ) );
			WP_CLI::line( '  Batches scheduled:  ' . number_format( $result['batches_scheduled'] ) );
			WP_CLI::line( '  Start time:         ' . date( 'Y-m-d H:i:s', $start_time ) );

			WP_CLI::success( 'Smart batch extraction scheduled' );
		} elseif ( isset( $assoc_args['all'] ) ) {
			// All posts
			$posts = get_posts(
				array(
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			$scheduled = $scheduler->schedule_bulk_extraction(
				$posts,
				array(
					'batch_size' => $batch_size,
					'start_time' => $start_time,
				)
			);

			WP_CLI::line( '' );
			WP_CLI::line( 'Results:' );
			WP_CLI::line( '  Total posts:       ' . number_format( count( $posts ) ) );
			WP_CLI::line( '  Batches scheduled: ' . number_format( $scheduled ) );
			WP_CLI::line( '  Start time:        ' . date( 'Y-m-d H:i:s', $start_time ) );

			WP_CLI::success( 'Batch extraction scheduled' );
		} elseif ( isset( $assoc_args['post-ids'] ) ) {
			// Specific posts
			$post_ids = array_map( 'intval', explode( ',', $assoc_args['post-ids'] ) );

			$scheduled = $scheduler->schedule_bulk_extraction(
				$post_ids,
				array(
					'batch_size' => $batch_size,
					'start_time' => $start_time,
				)
			);

			WP_CLI::line( '' );
			WP_CLI::line( 'Results:' );
			WP_CLI::line( '  Posts:             ' . number_format( count( $post_ids ) ) );
			WP_CLI::line( '  Batches scheduled: ' . number_format( $scheduled ) );
			WP_CLI::line( '  Start time:        ' . date( 'Y-m-d H:i:s', $start_time ) );

			WP_CLI::success( 'Batch extraction scheduled' );
		} else {
			WP_CLI::error( 'Please specify --all or --post-ids' );
		}
	}

	/**
	 * Show batch queue status
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context batch-status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function batch_status( $args, $assoc_args ) {
		$scheduler = MSH_Context_Batch_Scheduler::get_instance();
		$stats = $scheduler->get_batch_stats();

		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G  BATCH QUEUE STATUS%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
		WP_CLI::line( '' );

		WP_CLI::line( 'Pending batches: ' . WP_CLI::colorize( '%Y' . $stats['pending_batches'] . '%n' ) );
		WP_CLI::line( 'Total posts:     ' . number_format( $stats['total_posts'] ) );

		if ( $stats['next_batch'] ) {
			WP_CLI::line( 'Next batch:      ' . $stats['next_batch'] );
		} else {
			WP_CLI::line( 'Next batch:      ' . WP_CLI::colorize( '%RNone scheduled%n' ) );
		}

		WP_CLI::line( '' );

		if ( $stats['pending_batches'] > 0 ) {
			$pending = $scheduler->get_pending_batches();

			$table_data = array();
			foreach ( array_slice( $pending, 0, 10 ) as $batch ) {
				$post_count = isset( $batch['args'][0]['post_ids'] ) ? count( $batch['args'][0]['post_ids'] ) : 0;

				$table_data[] = array(
					'Scheduled'  => $batch['scheduled'],
					'Posts'      => $post_count,
					'Locale'     => isset( $batch['args'][0]['locale'] ) ? $batch['args'][0]['locale'] : 'N/A',
				);
			}

			WP_CLI::line( 'Upcoming batches:' );
			\WP_CLI\Utils\format_items( 'table', $table_data, array( 'Scheduled', 'Posts', 'Locale' ) );
		}
	}

	/**
	 * Performance optimization commands
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Action to perform (optimize|stats|warmup|clear-cache)
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh context perf optimize
	 *     wp msh context perf stats
	 *     wp msh context perf warmup
	 *     wp msh context perf clear-cache
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function perf( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? $args[0] : 'stats';
		$performance = MSH_Context_Performance::get_instance();

		switch ( $action ) {
			case 'optimize':
				WP_CLI::line( 'Optimizing database tables...' );
				$results = $performance->optimize_tables();

				foreach ( $results as $table => $success ) {
					if ( $success ) {
						WP_CLI::line( WP_CLI::colorize( '%G✓%n ' . $table ) );
					} else {
						WP_CLI::line( WP_CLI::colorize( '%R✗%n ' . $table ) );
					}
				}

				WP_CLI::success( 'Database optimization complete' );
				break;

			case 'stats':
				$stats = $performance->get_table_stats();

				WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
				WP_CLI::line( WP_CLI::colorize( '%G  DATABASE STATISTICS%n' ) );
				WP_CLI::line( WP_CLI::colorize( '%G═══════════════════════════════════════%n' ) );
				WP_CLI::line( '' );

				foreach ( $stats as $name => $stat ) {
					WP_CLI::line( WP_CLI::colorize( '%Y' . ucfirst( $name ) . ' Table:%n' ) );
					WP_CLI::line( '  Rows:       ' . number_format( $stat['rows'] ) );
					WP_CLI::line( '  Data:       ' . $stat['data_mb'] . ' MB' );
					WP_CLI::line( '  Indexes:    ' . $stat['index_mb'] . ' MB' );
					WP_CLI::line( '  Total:      ' . $stat['total_mb'] . ' MB' );
					WP_CLI::line( '' );
				}
				break;

			case 'warmup':
				WP_CLI::line( 'Warming up cache...' );
				$result = $performance->warmup_cache( array( 'limit' => 100 ) );

				WP_CLI::line( '' );
				WP_CLI::line( 'Processed: ' . $result['processed'] . ' images' );
				WP_CLI::line( 'Cached:    ' . $result['cached'] . ' rollups' );

				WP_CLI::success( 'Cache warmup complete' );
				break;

			case 'clear-cache':
				WP_CLI::line( 'Clearing all caches...' );
				$result = $performance->clear_all_caches();

				if ( $result ) {
					WP_CLI::success( 'All caches cleared' );
				} else {
					WP_CLI::warning( 'Cache clear may not be fully effective' );
				}
				break;

			default:
				WP_CLI::error( "Invalid action: {$action}. Use optimize, stats, warmup, or clear-cache" );
		}
	}
}

// Register WP-CLI command
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'msh context', 'MSH_Context_CLI' );
}
