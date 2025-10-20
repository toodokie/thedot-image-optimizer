<?php
/**
 * WP-CLI Commands for Database Management
 *
 * Provides CLI interface for managing Phase 5+9 database schema.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage CLI
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage MSH Image Optimizer database schema.
 *
 * ## EXAMPLES
 *
 *     # Install database tables
 *     $ wp msh db install
 *     Success: Database schema installed (version 2.0.0)
 *
 *     # Verify all tables exist
 *     $ wp msh db verify
 *     ✓ msh_jobs
 *     ✓ msh_dead_letters
 *     ✓ msh_telemetry
 *     ✓ msh_metrics
 *
 *     # Show table row counts
 *     $ wp msh db stats
 *     +------------------+-------+
 *     | Table            | Rows  |
 *     +------------------+-------+
 *     | msh_jobs         | 1,234 |
 *     | msh_dead_letters | 56    |
 *     | msh_telemetry    | 789   |
 *     | msh_metrics      | 30    |
 *     +------------------+-------+
 *
 *     # Drop all tables (WARNING: destructive!)
 *     $ wp msh db drop --yes
 *     Warning: This will delete all data!
 *     Success: All tables dropped
 */
class MSH_Database_CLI {

	/**
	 * Install or upgrade database schema.
	 *
	 * Creates all Phase 5+9 tables if they don't exist,
	 * or upgrades them to the current version.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh db install
	 *
	 * @when after_wp_load
	 */
	public function install( $args, $assoc_args ) {
		WP_CLI::log( 'Installing MSH database schema...' );

		$schema = MSH_Database_Schema::get_instance();
		$result = $schema->install();

		if ( $result ) {
			WP_CLI::success( sprintf(
				'Database schema installed (version %s)',
				MSH_Database_Schema::CURRENT_VERSION
			) );
		} else {
			WP_CLI::error( 'Database schema installation failed. Check error log for details.' );
		}
	}

	/**
	 * Verify all tables exist.
	 *
	 * Checks if all Phase 5+9 tables are present in the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh db verify
	 *
	 * @when after_wp_load
	 */
	public function verify( $args, $assoc_args ) {
		$schema  = MSH_Database_Schema::get_instance();
		$results = $schema->verify_tables();

		$all_exist = true;

		foreach ( $results as $table => $exists ) {
			if ( $exists ) {
				WP_CLI::log( WP_CLI::colorize( "%G✓%n {$table}" ) );
			} else {
				WP_CLI::log( WP_CLI::colorize( "%R✗%n {$table} (missing)" ) );
				$all_exist = false;
			}
		}

		if ( $all_exist ) {
			WP_CLI::success( 'All tables exist' );
		} else {
			WP_CLI::error( 'Some tables are missing. Run: wp msh db install' );
		}
	}

	/**
	 * Show database statistics.
	 *
	 * Displays row counts and total database size for all tables.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh db stats
	 *     wp msh db stats --format=json
	 *
	 * @when after_wp_load
	 */
	public function stats( $args, $assoc_args ) {
		$schema = MSH_Database_Schema::get_instance();
		$counts = $schema->get_row_counts();
		$size   = $schema->get_database_size();

		// Build table data
		$table_data = array();
		foreach ( $counts as $table => $count ) {
			$table_data[] = array(
				'Table' => $table,
				'Rows'  => number_format( $count ),
			);
		}

		// Add total row
		$table_data[] = array(
			'Table' => 'Total Size',
			'Rows'  => $size['size_human'],
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		WP_CLI\Utils\format_items( $format, $table_data, array( 'Table', 'Rows' ) );
	}

	/**
	 * Show CREATE TABLE statements.
	 *
	 * Displays the current schema for all tables.
	 *
	 * ## OPTIONS
	 *
	 * [<table>]
	 * : Specific table to show schema for. Omit to show all tables.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show all schemas
	 *     wp msh db schema
	 *
	 *     # Show specific table
	 *     wp msh db schema msh_jobs
	 *
	 * @when after_wp_load
	 */
	public function schema( $args, $assoc_args ) {
		$schema  = MSH_Database_Schema::get_instance();
		$schemas = $schema->get_schema_info();

		if ( ! empty( $args[0] ) ) {
			// Show specific table
			$table = $args[0];
			if ( isset( $schemas[ $table ] ) ) {
				WP_CLI::log( WP_CLI::colorize( "%B{$table}:%n" ) );
				WP_CLI::log( $schemas[ $table ] );
			} else {
				WP_CLI::error( "Table '{$table}' not found" );
			}
		} else {
			// Show all tables
			foreach ( $schemas as $table => $sql ) {
				WP_CLI::log( WP_CLI::colorize( "%B{$table}:%n" ) );
				WP_CLI::log( $sql );
				WP_CLI::log( '' );
			}
		}
	}

	/**
	 * Drop all tables.
	 *
	 * ⚠️  WARNING: This is a destructive operation that will delete all data!
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh db drop --yes
	 *
	 * @when after_wp_load
	 */
	public function drop( $args, $assoc_args ) {
		// Require confirmation
		WP_CLI::confirm( WP_CLI::colorize( '%RThis will delete all Phase 5+9 tables and data. Are you sure?%n' ), $assoc_args );

		$schema = MSH_Database_Schema::get_instance();
		$result = $schema->drop_tables( true );

		if ( $result ) {
			WP_CLI::success( 'All tables dropped' );
		} else {
			WP_CLI::error( 'Failed to drop tables' );
		}
	}

	/**
	 * Reset database (drop + install).
	 *
	 * Drops all tables and recreates them with fresh schema.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp msh db reset --yes
	 *
	 * @when after_wp_load
	 */
	public function reset( $args, $assoc_args ) {
		// Require confirmation
		WP_CLI::confirm( WP_CLI::colorize( '%RThis will reset all Phase 5+9 tables. All data will be lost. Continue?%n' ), $assoc_args );

		WP_CLI::log( 'Dropping tables...' );
		$schema = MSH_Database_Schema::get_instance();
		$schema->drop_tables( true );

		WP_CLI::log( 'Recreating tables...' );
		$result = $schema->install();

		if ( $result ) {
			WP_CLI::success( sprintf(
				'Database reset complete (version %s)',
				MSH_Database_Schema::CURRENT_VERSION
			) );
		} else {
			WP_CLI::error( 'Database installation failed after drop. Check error log.' );
		}
	}
}


// Register WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'msh db', 'MSH_Database_CLI' );
}
