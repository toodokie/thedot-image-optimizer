<?php
/**
 * WP-CLI Commands for TinyDot Template Intelligence
 *
 * Commands for installing, managing, and testing templates.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage TinyDot metadata templates
 */
class MSH_Template_CLI {

	/**
	 * Install starter templates
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Skip existing templates, only create missing ones.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl install
	 *     wp msh tpl install --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function install( $args, $assoc_args ) {
		require_once MSH_IO_PLUGIN_DIR . 'includes/data/starter-templates.php';

		WP_CLI::log( 'Installing starter templates...' );

		$results = msh_install_starter_templates();

		if ( ! empty( $results['errors'] ) ) {
			foreach ( $results['errors'] as $error ) {
				WP_CLI::warning( $error );
			}
		}

		WP_CLI::success( sprintf(
			'Created %d templates, skipped %d existing.',
			$results['created'],
			$results['skipped']
		) );
	}

	/**
	 * List all templates
	 *
	 * ## OPTIONS
	 *
	 * [--locale=<locale>]
	 * : Filter by locale (e.g., en, es).
	 *
	 * [--usage=<usage>]
	 * : Filter by usage type (featured, decorative).
	 *
	 * [--mode=<mode>]
	 * : Filter by mode (active, shadow, inactive).
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl list
	 *     wp msh tpl list --mode=shadow
	 *     wp msh tpl list --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function list( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();

		// Build filters
		$filters = array();
		if ( ! empty( $assoc_args['locale'] ) ) {
			$filters['locale'] = $assoc_args['locale'];
		}
		if ( ! empty( $assoc_args['usage'] ) ) {
			$filters['usage_type'] = $assoc_args['usage'];
		}
		if ( ! empty( $assoc_args['mode'] ) ) {
			$filters['mode'] = $assoc_args['mode'];
		}

		$templates = $manager->get_templates( $filters );

		if ( empty( $templates ) ) {
			WP_CLI::log( 'No templates found.' );
			return;
		}

		// Prepare for display
		$rows = array();
		foreach ( $templates as $template ) {
			$rows[] = array(
				'ID'       => $template['id'],
				'Name'     => $template['name'],
				'Locale'   => $template['locale'],
				'Usage'    => $template['usage_type'],
				'Intent'   => $template['intent'],
				'Mode'     => $template['mode'],
				'Active'   => $template['is_active'] ? 'yes' : 'no',
				'Priority' => $template['priority'],
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'Name', 'Locale', 'Usage', 'Intent', 'Mode', 'Active', 'Priority' ) );
	}

	/**
	 * Show detailed template information
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl show 1
	 *     wp msh tpl show 1 --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function show( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		// Decode JSON columns for display
		$template['required_tokens'] = json_decode( $template['required_tokens'], true );
		$template['negative_tokens'] = json_decode( $template['negative_tokens'], true );
		$template['nice_to_have_tokens'] = json_decode( $template['nice_to_have_tokens'], true );
		$template['variables'] = json_decode( $template['variables'], true );
		$template['max_len'] = json_decode( $template['max_len'], true );

		$format = $assoc_args['format'] ?? 'table';

		if ( 'table' === $format ) {
			// Pretty print for table format
			foreach ( $template as $key => $value ) {
				if ( is_array( $value ) ) {
					$template[ $key ] = wp_json_encode( $value );
				}
			}
		}

		WP_CLI\Utils\format_items( $format, array( $template ), array_keys( $template ) );
	}

	/**
	 * Activate a template
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--priority=<priority>]
	 * : Set priority (1-100). Higher = checked first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl activate 1
	 *     wp msh tpl activate 1 --priority=90
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function activate( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		$data = array( 'is_active' => 1 );

		if ( isset( $assoc_args['priority'] ) ) {
			$data['priority'] = (int) $assoc_args['priority'];
		}

		$result = $manager->update_template( $args[0], $data );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Activated template "%s".', $template['name'] ) );
	}

	/**
	 * Deactivate a template
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl deactivate 1
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function deactivate( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		$result = $manager->update_template( $args[0], array( 'is_active' => 0 ) );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Deactivated template "%s".', $template['name'] ) );
	}

	/**
	 * Promote template from shadow to active
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--force]
	 * : Force promotion even if requirements not met.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl promote 5
	 *     wp msh tpl promote 5 --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function promote( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$shadow = MSH_Shadow_Engine::get_instance();

		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		if ( 'shadow' !== $template['mode'] ) {
			WP_CLI::error( sprintf( 'Template is already in "%s" mode.', $template['mode'] ) );
		}

		$force = isset( $assoc_args['force'] );

		// Check eligibility first
		if ( ! $force ) {
			$eligibility = $shadow->check_promotion_eligibility( $args[0] );

			if ( ! $eligibility['eligible'] ) {
				WP_CLI::error( 'Template not eligible for promotion. Use --force to override.' );
				WP_CLI::log( '' );
				WP_CLI::log( 'Blockers:' );
				foreach ( $eligibility['blockers'] as $blocker ) {
					WP_CLI::log( "  • {$blocker}" );
				}
				return;
			}
		}

		// Perform promotion
		$result = $shadow->promote_template( $args[0], $force );

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['message'] );
		}

		WP_CLI::success( sprintf(
			'Promoted "%s" to active mode (precision: %.2f%%).',
			$template['name'],
			$result['metrics']['precision_percent']
		) );
	}

	/**
	 * Demote template from active to shadow
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--reason=<reason>]
	 * : Reason for demotion (for telemetry).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl demote 5
	 *     wp msh tpl demote 5 --reason="Low precision in production"
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function demote( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$shadow = MSH_Shadow_Engine::get_instance();

		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		if ( 'active' !== $template['mode'] ) {
			WP_CLI::error( sprintf( 'Template is already in "%s" mode.', $template['mode'] ) );
		}

		$reason = isset( $assoc_args['reason'] ) ? $assoc_args['reason'] : '';

		// Perform demotion
		$result = $shadow->demote_template( $args[0], $reason );

		if ( ! $result['success'] ) {
			WP_CLI::error( $result['message'] );
		}

		WP_CLI::success( sprintf(
			'Demoted "%s" to shadow mode.',
			$template['name']
		) );
	}

	/**
	 * Export templates to JSON
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Output file path. If not specified, prints to stdout.
	 *
	 * [--locale=<locale>]
	 * : Filter by locale.
	 *
	 * [--usage=<usage>]
	 * : Filter by usage type.
	 *
	 * [--mode=<mode>]
	 * : Filter by mode (active, shadow, inactive).
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl export > templates.json
	 *     wp msh tpl export templates.json --mode=active
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ) {
		require_once MSH_IO_PLUGIN_DIR . 'includes/data/starter-templates.php';

		// Build filters
		$filters = array();
		if ( ! empty( $assoc_args['locale'] ) ) {
			$filters['locale'] = $assoc_args['locale'];
		}
		if ( ! empty( $assoc_args['usage'] ) ) {
			$filters['usage_type'] = $assoc_args['usage'];
		}
		if ( ! empty( $assoc_args['mode'] ) ) {
			$filters['mode'] = $assoc_args['mode'];
		}

		$json = msh_export_templates_json( $filters );

		if ( ! empty( $args[0] ) ) {
			// Write to file
			$result = file_put_contents( $args[0], $json );
			if ( false === $result ) {
				WP_CLI::error( 'Failed to write file.' );
			}
			WP_CLI::success( sprintf( 'Exported templates to %s', $args[0] ) );
		} else {
			// Print to stdout
			WP_CLI::log( $json );
		}
	}

	/**
	 * Import templates from JSON
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON file path.
	 *
	 * [--mode=<mode>]
	 * : Import mode: merge (default) or replace.
	 * : merge = Update existing, create new
	 * : replace = Delete all, import fresh
	 *
	 * [--dry-run]
	 * : Test mode. Show what would be imported without actually doing it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl import templates.json
	 *     wp msh tpl import templates.json --mode=replace
	 *     wp msh tpl import templates.json --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function import( $args, $assoc_args ) {
		require_once MSH_IO_PLUGIN_DIR . 'includes/data/starter-templates.php';

		$file = $args[0];

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File not found.' );
		}

		$json = file_get_contents( $file );
		if ( false === $json ) {
			WP_CLI::error( 'Failed to read file.' );
		}

		$mode = $assoc_args['mode'] ?? 'merge';
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( 'replace' === $mode && ! $dry_run ) {
			WP_CLI::confirm( 'This will DELETE all existing templates. Are you sure?' );
		}

		WP_CLI::log( sprintf( 'Importing templates in %s mode...', $mode ) );
		if ( $dry_run ) {
			WP_CLI::log( '(DRY RUN - no changes will be made)' );
		}

		$results = msh_import_templates_json( $json, $mode, $dry_run );

		if ( is_wp_error( $results ) ) {
			WP_CLI::error( $results->get_error_message() );
		}

		if ( ! empty( $results['errors'] ) ) {
			foreach ( $results['errors'] as $error ) {
				WP_CLI::warning( $error );
			}
		}

		WP_CLI::success( sprintf(
			'Created: %d, Updated: %d, Skipped: %d, Deleted: %d',
			$results['created'],
			$results['updated'],
			$results['skipped'],
			$results['deleted']
		) );
	}

	/**
	 * Test template matching
	 *
	 * ## OPTIONS
	 *
	 * [--keywords=<keywords>]
	 * : Comma-separated keywords to test.
	 *
	 * [--locale=<locale>]
	 * : Locale. Default: en.
	 *
	 * [--usage=<usage>]
	 * : Usage type. Default: featured.
	 *
	 * [--intent=<intent>]
	 * : Intent. Default: on_topic.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl test --keywords="exterior,building,clinic"
	 *     wp msh tpl test --keywords="team,people,group"
	 *     wp msh tpl test --keywords="screenshot,ui,interface"
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function test( $args, $assoc_args ) {
		if ( empty( $assoc_args['keywords'] ) ) {
			WP_CLI::error( 'Please provide --keywords to test.' );
		}

		$matcher = MSH_Template_Matcher::get_instance();

		// Build context
		$context = array(
			'locale'     => $assoc_args['locale'] ?? 'en',
			'usage_type' => $assoc_args['usage'] ?? 'featured',
			'intent'     => $assoc_args['intent'] ?? 'on_topic',
			'keywords'   => array_map( 'trim', explode( ',', $assoc_args['keywords'] ) ),
			'entities'   => array( 'Test Entity' ),
			'subject'    => 'Test Subject',
			'post_title' => 'Test Post',
		);

		WP_CLI::log( sprintf( 'Testing with keywords: %s', implode( ', ', $context['keywords'] ) ) );
		WP_CLI::log( '' );

		$start_time = microtime( true );
		$match = $matcher->find_match( $context );
		$duration_ms = ( microtime( true ) - $start_time ) * 1000;

		if ( $match ) {
			WP_CLI::success( sprintf( 'Matched template: %s (ID: %d)', $match['name'], $match['id'] ) );
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'Mode: %s', $match['mode'] ) );
			WP_CLI::log( sprintf( 'Priority: %d', $match['priority'] ) );
			WP_CLI::log( sprintf( 'Duration: %.2f ms', $duration_ms ) );
			WP_CLI::log( '' );

			// Show what metadata would be generated
			$fields = $matcher->apply_template( $match, $context );
			WP_CLI::log( 'Generated Metadata:' );
			foreach ( $fields as $field_name => $value ) {
				if ( ! in_array( $field_name, array( 'source', 'template_id' ), true ) ) {
					WP_CLI::log( sprintf( '  %s: %s', ucfirst( $field_name ), $value ) );
				}
			}
		} else {
			WP_CLI::warning( sprintf( 'No matching template found. (%.2f ms)', $duration_ms ) );
			WP_CLI::log( '' );
			WP_CLI::log( 'Tip: Try different keywords or check your template coverage.' );
		}
	}

	/**
	 * Delete a template
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl delete 1
	 *     wp msh tpl delete 1 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function delete( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		if ( ! isset( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( sprintf( 'Delete template "%s"?', $template['name'] ) );
		}

		$result = $manager->delete_template( $args[0] );

		if ( ! $result ) {
			WP_CLI::error( 'Failed to delete template.' );
		}

		WP_CLI::success( sprintf( 'Deleted template "%s".', $template['name'] ) );
	}

	/**
	 * Show shadow precision stats for a template
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl stats 5
	 *     wp msh tpl stats 5 --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function stats( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$shadow = MSH_Shadow_Engine::get_instance();

		$template = $manager->get_template( $args[0] );

		if ( ! $template ) {
			WP_CLI::error( 'Template not found.' );
		}

		$precision = $shadow->calculate_precision( $args[0] );

		WP_CLI::log( WP_CLI::colorize( "%GTemplate: {$template['name']}%n" ) );
		WP_CLI::log( WP_CLI::colorize( "%GMode: {$template['mode']}%n" ) );
		WP_CLI::log( '' );

		// Promotion eligibility
		if ( $precision['promotable'] ) {
			WP_CLI::log( WP_CLI::colorize( '%G✓ Eligible for promotion%n' ) );
		} else {
			WP_CLI::log( WP_CLI::colorize( '%R✗ Not eligible for promotion%n' ) );
			foreach ( $precision['promotion_blockers'] as $blocker ) {
				WP_CLI::log( WP_CLI::colorize( "  %Y• {$blocker}%n" ) );
			}
		}

		WP_CLI::log( '' );

		// Format metrics
		$metrics = array(
			array(
				'metric' => 'Precision',
				'value'  => sprintf( '%.2f%%', $precision['precision_percent'] ),
				'target' => '≥95%',
			),
			array(
				'metric' => 'Recall',
				'value'  => sprintf( '%.2f%%', $precision['recall_percent'] ),
				'target' => 'N/A',
			),
			array(
				'metric' => 'Total Evaluations',
				'value'  => number_format( $precision['total_evaluations'] ),
				'target' => '≥500',
			),
			array(
				'metric' => 'True Positives',
				'value'  => number_format( $precision['true_positives'] ),
				'target' => '≥50',
			),
			array(
				'metric' => 'False Positives',
				'value'  => number_format( $precision['false_positives'] ),
				'target' => 'Low',
			),
			array(
				'metric' => 'False Negatives',
				'value'  => number_format( $precision['false_negatives'] ),
				'target' => 'Low',
			),
			array(
				'metric' => 'Site Count',
				'value'  => number_format( $precision['site_count'] ),
				'target' => '≥2',
			),
			array(
				'metric' => 'Avg Duration',
				'value'  => sprintf( '%.2fms', $precision['avg_duration_ms'] ),
				'target' => '<5ms',
			),
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items( $format, $metrics, array( 'metric', 'value', 'target' ) );

		if ( ! empty( $precision['first_evaluation'] ) ) {
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'First eval: %s', $precision['first_evaluation'] ) );
			WP_CLI::log( sprintf( 'Last eval:  %s', $precision['last_evaluation'] ) );
		}
	}

	/**
	 * List templates ready for promotion
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format (table, json, yaml, ids). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl promotable
	 *     wp msh tpl promotable --format=ids
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function promotable( $args, $assoc_args ) {
		$manager = MSH_Template_Manager::get_instance();
		$shadow = MSH_Shadow_Engine::get_instance();

		$promotable = $shadow->get_promotable_templates();

		if ( empty( $promotable ) ) {
			WP_CLI::log( 'No templates ready for promotion.' );
			return;
		}

		$rows = array();
		foreach ( $promotable as $item ) {
			$template = $manager->get_template( $item['template_id'] );
			$metrics = $item['metrics'];

			$rows[] = array(
				'id'        => $item['template_id'],
				'name'      => $template['name'],
				'precision' => sprintf( '%.2f%%', $metrics['precision_percent'] ),
				'evals'     => $metrics['total_evaluations'],
				'sites'     => $metrics['site_count'],
			);
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'name', 'precision', 'evals', 'sites' ) );

		WP_CLI::log( '' );
		WP_CLI::success( sprintf( '%d template(s) ready for promotion.', count( $promotable ) ) );
	}

	/**
	 * List unlabeled shadow evaluations
	 *
	 * Shows shadow evaluations that need ground truth labels.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Template ID.
	 *
	 * [--limit=<limit>]
	 * : Max results. Default: 50.
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh tpl unlabeled 7
	 *     wp msh tpl unlabeled 7 --limit=20
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function unlabeled( $args, $assoc_args ) {
		$shadow = MSH_Shadow_Engine::get_instance();
		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;

		$evals = $shadow->get_unlabeled_evaluations( $args[0], $limit );

		if ( empty( $evals ) ) {
			WP_CLI::success( 'No unlabeled evaluations found.' );
			return;
		}

		// Get attachment titles for context
		foreach ( $evals as &$eval ) {
			$eval['image'] = get_the_title( $eval['attachment_id'] );
			$eval['matched_text'] = $eval['matched'] ? 'YES' : 'NO';
		}

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items(
			$format,
			$evals,
			array( 'id', 'attachment_id', 'image', 'matched_text', 'duration_ms', 'evaluated_at' )
		);

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Found %d unlabeled evaluation(s).', count( $evals ) ) );
		WP_CLI::log( 'Use "wp msh tpl label" to set ground truth.' );
	}

	/**
	 * Set ground truth labels for shadow evaluations
	 *
	 * ## OPTIONS
	 *
	 * <ids>...
	 * : Evaluation IDs to label (space-separated).
	 *
	 * [--should-match]
	 * : Template SHOULD have matched these images.
	 *
	 * [--should-not-match]
	 * : Template should NOT have matched these images.
	 *
	 * ## EXAMPLES
	 *
	 *     # Template SHOULD match images 1,2,3
	 *     wp msh tpl label 1 2 3 --should-match
	 *
	 *     # Template should NOT match images 4,5,6
	 *     wp msh tpl label 4 5 6 --should-not-match
	 *
	 * @param array $args       Positional arguments (eval IDs).
	 * @param array $assoc_args Named arguments.
	 */
	public function label( $args, $assoc_args ) {
		$shadow = MSH_Shadow_Engine::get_instance();

		if ( empty( $args ) ) {
			WP_CLI::error( 'No evaluation IDs provided.' );
		}

		// Determine label
		$should_match = isset( $assoc_args['should-match'] );
		$should_not_match = isset( $assoc_args['should-not-match'] );

		if ( $should_match === $should_not_match ) {
			WP_CLI::error( 'Must specify either --should-match OR --should-not-match (not both).' );
		}

		// Set ground truth
		$updated = $shadow->set_ground_truth( $args, $should_match );

		if ( 0 === $updated ) {
			WP_CLI::warning( 'No evaluations updated. Check IDs are valid.' );
			return;
		}

		$label_text = $should_match ? 'SHOULD MATCH' : 'should NOT match';
		WP_CLI::success( sprintf(
			'Labeled %d evaluation(s) as "%s".',
			$updated,
			$label_text
		) );
	}
}

WP_CLI::add_command( 'msh tpl', 'MSH_Template_CLI' );
