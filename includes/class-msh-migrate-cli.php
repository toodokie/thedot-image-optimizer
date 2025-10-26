<?php
/**
 * WP-CLI Migration Commands
 *
 * Commands for managing Expand/Backfill/Switch/Contract migrations.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage MSH_Image_Optimizer/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for migrations.
 */
class MSH_Migrate_CLI {

	/**
	 * Migration helper instance.
	 *
	 * @var MSH_Migration_Helper
	 */
	private $helper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->helper = MSH_Migration_Helper::get_instance();
	}

	/**
	 * List all registered migrations and their status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate list
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ) {
		$migrations = $this->helper->get_migrations();

		if ( empty( $migrations ) ) {
			WP_CLI::warning( 'No migrations registered.' );
			return;
		}

		$rows = array();
		foreach ( $migrations as $key => $migration ) {
			$rows[] = array(
				'key'         => $key,
				'name'        => $migration['name'],
				'status'      => $this->colorize_status( $migration['status'] ),
				'flag'        => $migration['flag_key'],
				'description' => $migration['description'],
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'key', 'name', 'status', 'flag', 'description' ) );

		// Show summary
		$summary = $this->helper->get_status_summary();
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%gSummary:%n' ) );
		WP_CLI::line( sprintf( '  Total:      %d', $summary['total'] ) );
		WP_CLI::line( sprintf( '  Pending:    %d', $summary['pending'] ) );
		WP_CLI::line( sprintf( '  Expanded:   %d', $summary['expanded'] ) );
		WP_CLI::line( sprintf( '  Backfilled: %d', $summary['backfilled'] ) );
		WP_CLI::line( sprintf( '  Switched:   %d', $summary['switched'] ) );
		WP_CLI::line( sprintf( '  Contracted: %d', $summary['contracted'] ) );
	}

	/**
	 * Show status of a specific migration.
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate status phase6_templates
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		$migration_key = $args[0];
		$migration     = $this->helper->get_migration( $migration_key );

		if ( ! $migration ) {
			WP_CLI::error( sprintf( 'Migration "%s" not found.', $migration_key ) );
		}

		WP_CLI::line( WP_CLI::colorize( '%g=== ' . $migration['name'] . ' ===%n' ) );
		WP_CLI::line( sprintf( 'Key:         %s', $migration_key ) );
		WP_CLI::line( sprintf( 'Description: %s', $migration['description'] ) );
		WP_CLI::line( sprintf( 'Status:      %s', $this->colorize_status( $migration['status'] ) ) );
		WP_CLI::line( sprintf( 'Flag:        %s', $migration['flag_key'] ) );
		WP_CLI::line( sprintf( 'Expand SQL:  %s', $migration['expand_sql'] ) );
		WP_CLI::line( sprintf( 'Backfill:    %s', $migration['backfill_callback'] ?? 'none' ) );
		WP_CLI::line( sprintf( 'Contract SQL: %s', $migration['contract_sql'] ?? 'none' ) );
	}

	/**
	 * Run EXPAND phase - add new structure.
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate expand phase6_templates
	 *
	 * @when after_wp_load
	 */
	public function expand( $args, $assoc_args ) {
		$migration_key = $args[0];

		WP_CLI::line( sprintf( 'Expanding migration: %s', $migration_key ) );

		$result = $this->helper->expand( $migration_key );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Run BACKFILL phase - copy data to new structure.
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * [--batch-size=<number>]
	 * : Number of records to process per batch.
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate backfill phase8_metrics
	 *     wp msh migrate backfill phase8_metrics --batch-size=500
	 *
	 * @when after_wp_load
	 */
	public function backfill( $args, $assoc_args ) {
		$migration_key = $args[0];
		$batch_size    = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 100;

		WP_CLI::line( sprintf( 'Backfilling migration: %s (batch size: %d)', $migration_key, $batch_size ) );

		$result = $this->helper->backfill( $migration_key, $batch_size );

		if ( $result['success'] ) {
			if ( isset( $result['complete'] ) && ! $result['complete'] ) {
				WP_CLI::warning( $result['message'] );
				WP_CLI::line( 'Run the command again to continue backfilling.' );
			} else {
				WP_CLI::success( $result['message'] );
			}
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * VERIFY data parity between old and new structures.
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate verify phase8_metrics
	 *
	 * @when after_wp_load
	 */
	public function verify( $args, $assoc_args ) {
		$migration_key = $args[0];

		WP_CLI::line( sprintf( 'Verifying migration: %s', $migration_key ) );

		$result = $this->helper->verify( $migration_key );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Run SWITCH phase - enable feature flag.
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * [--percentage=<number>]
	 * : Percentage rollout (0-100).
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate switch phase6_templates
	 *     wp msh migrate switch phase6_templates --percentage=25
	 *
	 * @when after_wp_load
	 */
	public function switch( $args, $assoc_args ) {
		$migration_key = $args[0];
		$percentage    = isset( $assoc_args['percentage'] ) ? absint( $assoc_args['percentage'] ) : 100;

		if ( $percentage < 0 || $percentage > 100 ) {
			WP_CLI::error( 'Percentage must be between 0 and 100.' );
		}

		WP_CLI::line( sprintf( 'Switching migration: %s (percentage: %d%%)', $migration_key, $percentage ) );

		$result = $this->helper->switch( $migration_key, $percentage );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );

			if ( $percentage < 100 ) {
				WP_CLI::warning( sprintf( 'Feature flag enabled at %d%%. Increase gradually to 100%% after monitoring.', $percentage ) );
			}
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Run CONTRACT phase - remove old structure.
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * [--confirm]
	 * : Confirm the destructive operation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate contract phase6_templates --confirm
	 *
	 * @when after_wp_load
	 */
	public function contract( $args, $assoc_args ) {
		$migration_key = $args[0];
		$confirm       = isset( $assoc_args['confirm'] );

		if ( ! $confirm ) {
			WP_CLI::error( 'This is a destructive operation. Add --confirm to proceed.' );
		}

		WP_CLI::line( sprintf( 'Contracting migration: %s', $migration_key ) );

		// Extra confirmation prompt
		WP_CLI::confirm( WP_CLI::colorize( '%rThis will permanently remove old database structures. Continue?%n' ), $assoc_args );

		$result = $this->helper->contract( $migration_key, $confirm );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Run complete migration workflow (expand → backfill → verify → switch).
	 *
	 * ## OPTIONS
	 *
	 * <migration-key>
	 * : The migration key.
	 *
	 * [--percentage=<number>]
	 * : Percentage rollout for switch phase (0-100).
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--batch-size=<number>]
	 * : Batch size for backfill.
	 * ---
	 * default: 100
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh migrate run phase6_templates
	 *     wp msh migrate run phase8_metrics --percentage=25 --batch-size=500
	 *
	 * @when after_wp_load
	 */
	public function run( $args, $assoc_args ) {
		$migration_key = $args[0];
		$percentage    = isset( $assoc_args['percentage'] ) ? absint( $assoc_args['percentage'] ) : 100;
		$batch_size    = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 100;

		$migration = $this->helper->get_migration( $migration_key );

		if ( ! $migration ) {
			WP_CLI::error( sprintf( 'Migration "%s" not found.', $migration_key ) );
		}

		WP_CLI::line( WP_CLI::colorize( '%g=== Running Complete Migration: ' . $migration['name'] . ' ===%n' ) );
		WP_CLI::line( '' );

		// Step 1: Expand
		if ( 'pending' === $migration['status'] ) {
			WP_CLI::line( WP_CLI::colorize( '%y[1/4] EXPAND:%n Adding new structure...' ) );
			$result = $this->helper->expand( $migration_key );

			if ( ! $result['success'] ) {
				WP_CLI::error( $result['message'] );
			}
			WP_CLI::success( $result['message'] );
			WP_CLI::line( '' );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%y[1/4] EXPAND:%n Already expanded, skipping.' ) );
			WP_CLI::line( '' );
		}

		// Step 2: Backfill
		$migration = $this->helper->get_migration( $migration_key ); // Refresh status
		if ( 'expanded' === $migration['status'] ) {
			WP_CLI::line( WP_CLI::colorize( '%y[2/4] BACKFILL:%n Copying data to new structure...' ) );
			$result = $this->helper->backfill( $migration_key, $batch_size );

			if ( ! $result['success'] ) {
				WP_CLI::error( $result['message'] );
			}

			if ( isset( $result['complete'] ) && ! $result['complete'] ) {
				WP_CLI::warning( 'Backfill incomplete. Run this command again to continue.' );
				return;
			}

			WP_CLI::success( $result['message'] );
			WP_CLI::line( '' );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%y[2/4] BACKFILL:%n Already backfilled, skipping.' ) );
			WP_CLI::line( '' );
		}

		// Step 3: Verify
		$migration = $this->helper->get_migration( $migration_key ); // Refresh status
		if ( in_array( $migration['status'], array( 'backfilled', 'switched', 'contracted' ), true ) ) {
			WP_CLI::line( WP_CLI::colorize( '%y[3/4] VERIFY:%n Checking data parity...' ) );
			$result = $this->helper->verify( $migration_key );

			if ( ! $result['success'] ) {
				WP_CLI::error( $result['message'] );
			}
			WP_CLI::success( $result['message'] );
			WP_CLI::line( '' );
		}

		// Step 4: Switch
		$migration = $this->helper->get_migration( $migration_key ); // Refresh status
		if ( 'backfilled' === $migration['status'] ) {
			WP_CLI::line( WP_CLI::colorize( sprintf( '%%y[4/4] SWITCH:%%n Enabling feature flag at %d%%...', $percentage ) ) );
			$result = $this->helper->switch( $migration_key, $percentage );

			if ( ! $result['success'] ) {
				WP_CLI::error( $result['message'] );
			}
			WP_CLI::success( $result['message'] );
			WP_CLI::line( '' );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%y[4/4] SWITCH:%n Already switched, skipping.' ) );
			WP_CLI::line( '' );
		}

		WP_CLI::line( WP_CLI::colorize( '%g=== Migration Complete ===%n' ) );

		if ( $percentage < 100 ) {
			WP_CLI::warning( sprintf( 'Feature flag enabled at %d%%. Monitor for issues, then increase to 100%%.', $percentage ) );
		} else {
			WP_CLI::success( 'Migration is fully deployed at 100%.' );
		}

		WP_CLI::line( '' );
		WP_CLI::line( 'Next steps:' );
		WP_CLI::line( '  1. Monitor telemetry and error logs' );
		WP_CLI::line( '  2. Verify functionality in production' );
		WP_CLI::line( '  3. After 30 days of stability, run: wp msh migrate contract ' . $migration_key . ' --confirm' );
	}

	/**
	 * Colorize status for terminal output.
	 *
	 * @param string $status Migration status.
	 * @return string Colorized status.
	 */
	private function colorize_status( $status ) {
		$colors = array(
			'pending'    => '%y' . $status . '%n', // Yellow
			'expanded'   => '%b' . $status . '%n', // Blue
			'backfilled' => '%c' . $status . '%n', // Cyan
			'switched'   => '%g' . $status . '%n', // Green
			'contracted' => '%m' . $status . '%n', // Magenta
		);

		return isset( $colors[ $status ] ) ? WP_CLI::colorize( $colors[ $status ] ) : $status;
	}
}
