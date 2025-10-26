<?php
/**
 * Migration Helper - Expand/Backfill/Switch/Contract Pattern
 *
 * Provides zero-downtime database migrations using the EBSC pattern:
 * 1. EXPAND: Add new table/column alongside old (dual-write begins)
 * 2. BACKFILL: Copy old data to new structure (background job)
 * 3. SWITCH: Flip feature flag to read from new structure
 * 4. CONTRACT: Remove old table/column (after verification)
 *
 * @package    MSH_Image_Optimizer
 * @subpackage MSH_Image_Optimizer/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration Helper Class
 *
 * Handles all aspects of the Expand/Backfill/Switch/Contract migration pattern.
 */
class MSH_Migration_Helper {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Migration_Helper
	 */
	private static $instance = null;

	/**
	 * Migration registry.
	 *
	 * @var array
	 */
	private $migrations = array();

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Migration_Helper
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_migrations();
	}

	/**
	 * Register all known migrations.
	 *
	 * @return void
	 */
	private function register_migrations() {
		/**
		 * Migration Registry Format:
		 * 'migration_key' => [
		 *     'name'              => 'Human-readable name',
		 *     'description'       => 'What this migration does',
		 *     'expand_sql'        => 'path/to/expand.sql',
		 *     'backfill_callback' => 'callback_function_name' or null,
		 *     'flag_key'          => 'feature_flag_key',
		 *     'verify_callback'   => 'verification_function_name' or null,
		 *     'contract_sql'      => 'path/to/contract.sql' or null,
		 *     'status'            => 'pending|expanded|backfilled|switched|contracted',
		 * ]
		 */

		// Phase 6: Template Intelligence - Step 1 (Create base table)
		$this->migrations['phase6_templates'] = array(
			'name'              => 'Template Intelligence System',
			'description'       => 'Add wp_msh_optimizer_templates table for metadata templates',
			'expand_sql'        => 'migrations/0001_create_templates_table.sql',
			'backfill_callback' => null, // No backfill needed (new table, no old data)
			'flag_key'          => 'template_intelligence',
			'verify_callback'   => null,
			'contract_sql'      => null, // Nothing to remove (pure expansion)
			'status'            => get_option( 'msh_migration_phase6_templates_status', 'pending' ),
		);

		// Phase 6: Template Intelligence - Step 2 (Schema hardening)
		$this->migrations['phase6_templates_hardening'] = array(
			'name'              => 'Template Schema Hardening',
			'description'       => 'Add name, mode, negative_tokens, variables, and performance columns',
			'expand_sql'        => 'migrations/0002_update_templates_schema.sql',
			'backfill_callback' => null, // Backfill handled by SQL UPDATE statement
			'flag_key'          => 'template_intelligence', // Same flag as base migration
			'verify_callback'   => null,
			'contract_sql'      => null,
			'status'            => get_option( 'msh_migration_phase6_templates_hardening_status', 'pending' ),
			'depends_on'        => 'phase6_templates', // Must run after base table creation
		);

		// Phase 6: Template Intelligence - Step 3 (Shadow stats tracking)
		$this->migrations['phase6_shadow_stats'] = array(
			'name'              => 'Shadow Precision Tracking',
			'description'       => 'Add shadow stats table for tracking template evaluation accuracy',
			'expand_sql'        => 'migrations/0003_shadow_stats_table.sql',
			'backfill_callback' => null, // New table, no backfill needed
			'flag_key'          => 'template_intelligence',
			'verify_callback'   => null,
			'contract_sql'      => null,
			'status'            => get_option( 'msh_migration_phase6_shadow_stats_status', 'pending' ),
			'depends_on'        => 'phase6_templates', // Requires templates table
		);

		// Phase 6: Template Intelligence - Step 4 (Collision detection)
		$this->migrations['phase6_collisions'] = array(
			'name'              => 'Template Collision Detection',
			'description'       => 'Add collisions table for tracking when multiple templates match',
			'expand_sql'        => 'migrations/0004_collisions_table.sql',
			'backfill_callback' => null, // New table, no backfill needed
			'flag_key'          => 'template_intelligence',
			'verify_callback'   => null,
			'contract_sql'      => null,
			'status'            => get_option( 'msh_migration_phase6_collisions_status', 'pending' ),
			'depends_on'        => 'phase6_templates', // Requires templates table
		);

		/**
		 * Filter to allow external migrations to be registered.
		 *
		 * Phase 8 and future migrations will be added via this filter
		 * when they're ready for implementation.
		 *
		 * @param array $migrations Current migrations array.
		 */
		$this->migrations = apply_filters( 'msh_register_migrations', $this->migrations );
	}

	/**
	 * Get all registered migrations.
	 *
	 * @return array
	 */
	public function get_migrations() {
		return $this->migrations;
	}

	/**
	 * Get specific migration by key.
	 *
	 * @param string $migration_key Migration key.
	 * @return array|null Migration config or null if not found.
	 */
	public function get_migration( $migration_key ) {
		return isset( $this->migrations[ $migration_key ] ) ? $this->migrations[ $migration_key ] : null;
	}

	/**
	 * EXPAND: Run expansion SQL to add new structure.
	 *
	 * @param string $migration_key Migration key.
	 * @return array Result with success/error.
	 */
	public function expand( $migration_key ) {
		$migration = $this->get_migration( $migration_key );

		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Migration "%s" not found.', 'msh-image-optimizer' ), $migration_key ),
			);
		}

		if ( 'pending' !== $migration['status'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Migration "%s" is already in status "%s". Can only expand from "pending".', 'msh-image-optimizer' ),
					$migration_key,
					$migration['status']
				),
			);
		}

		// Check dependencies
		if ( ! empty( $migration['depends_on'] ) ) {
			$dependency_key = $migration['depends_on'];
			$dependency = $this->get_migration( $dependency_key );

			if ( ! $dependency ) {
				return array(
					'success' => false,
					'message' => sprintf(
						__( 'Dependency migration "%s" not found for "%s".', 'msh-image-optimizer' ),
						$dependency_key,
						$migration_key
					),
				);
			}

			// Dependency must be at least expanded
			if ( ! in_array( $dependency['status'], array( 'expanded', 'backfilled', 'switched', 'contracted' ), true ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
						__( 'Dependency migration "%s" must be expanded before running "%s". Current status: %s', 'msh-image-optimizer' ),
						$dependency['name'],
						$migration['name'],
						$dependency['status']
					),
				);
			}
		}

		// Read SQL file
		$sql_file = MSH_IO_PLUGIN_DIR . 'includes/' . $migration['expand_sql'];

		if ( ! file_exists( $sql_file ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'SQL file not found: %s', 'msh-image-optimizer' ), $sql_file ),
			);
		}

		$sql = file_get_contents( $sql_file );

		if ( empty( $sql ) ) {
			return array(
				'success' => false,
				'message' => __( 'SQL file is empty.', 'msh-image-optimizer' ),
			);
		}

		// Replace table prefix placeholder
		global $wpdb;
		$sql = str_replace( '{wp_prefix}', $wpdb->prefix, $sql );

		// Split SQL into individual statements (handle multi-statement files)
		// Remove comments and split by semicolon
		$sql = preg_replace( '/--.*$/m', '', $sql ); // Remove single-line comments
		$statements = array_filter(
			array_map( 'trim', explode( ';', $sql ) ),
			function( $stmt ) {
				return ! empty( $stmt );
			}
		);

		// Execute each statement
		foreach ( $statements as $statement ) {
			$wpdb->query( $statement ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( $wpdb->last_error ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'SQL error: %s', 'msh-image-optimizer' ), $wpdb->last_error ),
				);
			}
		}

		// Update status
		update_option( "msh_migration_{$migration_key}_status", 'expanded', false );
		$this->migrations[ $migration_key ]['status'] = 'expanded';

		// Log event
		msh_telemetry( 'migration_expand', array(
			'migration' => $migration_key,
			'name'      => $migration['name'],
		) );

		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Expansion complete for "%s". New structure added.', 'msh-image-optimizer' ),
				$migration['name']
			),
		);
	}

	/**
	 * BACKFILL: Copy data from old structure to new.
	 *
	 * @param string $migration_key Migration key.
	 * @param int    $batch_size    Batch size for processing.
	 * @return array Result with success/error.
	 */
	public function backfill( $migration_key, $batch_size = 100 ) {
		$migration = $this->get_migration( $migration_key );

		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Migration "%s" not found.', 'msh-image-optimizer' ), $migration_key ),
			);
		}

		if ( 'expanded' !== $migration['status'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Migration "%s" must be in "expanded" status to backfill. Current status: %s', 'msh-image-optimizer' ),
					$migration_key,
					$migration['status']
				),
			);
		}

		// If no backfill callback, mark as complete
		if ( empty( $migration['backfill_callback'] ) ) {
			update_option( "msh_migration_{$migration_key}_status", 'backfilled', false );
			$this->migrations[ $migration_key ]['status'] = 'backfilled';

			return array(
				'success' => true,
				'message' => sprintf(
					__( 'No backfill needed for "%s". Marked as backfilled.', 'msh-image-optimizer' ),
					$migration['name']
				),
			);
		}

		// Check if callback exists
		if ( ! is_callable( $migration['backfill_callback'] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Backfill callback "%s" is not callable.', 'msh-image-optimizer' ),
					$migration['backfill_callback']
				),
			);
		}

		// Run backfill callback
		$result = call_user_func( $migration['backfill_callback'], $batch_size );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Check if backfill is complete
		$is_complete = isset( $result['complete'] ) ? $result['complete'] : true;
		$processed   = isset( $result['processed'] ) ? $result['processed'] : 0;

		if ( $is_complete ) {
			update_option( "msh_migration_{$migration_key}_status", 'backfilled', false );
			$this->migrations[ $migration_key ]['status'] = 'backfilled';

			msh_telemetry( 'migration_backfill_complete', array(
				'migration' => $migration_key,
				'processed' => $processed,
			) );

			return array(
				'success' => true,
				'message' => sprintf(
					__( 'Backfill complete for "%s". Processed %d records.', 'msh-image-optimizer' ),
					$migration['name'],
					$processed
				),
			);
		}

		// Partial backfill (more to process)
		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Backfill in progress for "%s". Processed %d records this batch. Run again to continue.', 'msh-image-optimizer' ),
				$migration['name'],
				$processed
			),
			'complete' => false,
		);
	}

	/**
	 * VERIFY: Check data parity between old and new structures.
	 *
	 * @param string $migration_key Migration key.
	 * @return array Result with success/error.
	 */
	public function verify( $migration_key ) {
		$migration = $this->get_migration( $migration_key );

		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Migration "%s" not found.', 'msh-image-optimizer' ), $migration_key ),
			);
		}

		// If no verify callback, assume verification passed
		if ( empty( $migration['verify_callback'] ) ) {
			return array(
				'success' => true,
				'message' => sprintf(
					__( 'No verification needed for "%s". Assumed verified.', 'msh-image-optimizer' ),
					$migration['name']
				),
			);
		}

		// Check if callback exists
		if ( ! is_callable( $migration['verify_callback'] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Verify callback "%s" is not callable.', 'msh-image-optimizer' ),
					$migration['verify_callback']
				),
			);
		}

		// Run verification callback
		$result = call_user_func( $migration['verify_callback'] );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$verified = isset( $result['verified'] ) ? $result['verified'] : false;
		$message  = isset( $result['message'] ) ? $result['message'] : '';

		msh_telemetry( 'migration_verify', array(
			'migration' => $migration_key,
			'verified'  => $verified,
		) );

		return array(
			'success' => $verified,
			'message' => $verified
				? sprintf( __( 'Verification passed for "%s". %s', 'msh-image-optimizer' ), $migration['name'], $message )
				: sprintf( __( 'Verification FAILED for "%s". %s', 'msh-image-optimizer' ), $migration['name'], $message ),
		);
	}

	/**
	 * SWITCH: Enable feature flag to read from new structure.
	 *
	 * @param string $migration_key Migration key.
	 * @param int    $percentage    Percentage rollout (0-100).
	 * @return array Result with success/error.
	 */
	public function switch( $migration_key, $percentage = 100 ) {
		$migration = $this->get_migration( $migration_key );

		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Migration "%s" not found.', 'msh-image-optimizer' ), $migration_key ),
			);
		}

		if ( 'backfilled' !== $migration['status'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Migration "%s" must be backfilled before switching. Current status: %s', 'msh-image-optimizer' ),
					$migration_key,
					$migration['status']
				),
			);
		}

		// Enable feature flag
		$flag_key = $migration['flag_key'];

		if ( empty( $flag_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'No feature flag key specified for this migration.', 'msh-image-optimizer' ),
			);
		}

		// Check if Feature Flags system exists
		if ( class_exists( 'MSH_Feature_Flags' ) ) {
			$percentage = (int) $percentage;

			if ( $percentage <= 0 ) {
				MSH_Feature_Flags::set( $flag_key, false );
				MSH_Feature_Flags::set_rollout( $flag_key, 'everyone' );
			} elseif ( $percentage >= 100 ) {
				MSH_Feature_Flags::set( $flag_key, true );
				MSH_Feature_Flags::set_rollout( $flag_key, 'everyone' );
			} else {
				// Check if true percentage method exists
				if ( method_exists( 'MSH_Feature_Flags', 'enable_percentage' ) ) {
					// Use real percentage sampling (5%, 25%, 50%, etc.)
					MSH_Feature_Flags::enable_percentage( $flag_key, $percentage );
				} else {
					// Fallback: scope to administrators for staged rollout
					MSH_Feature_Flags::set( $flag_key, true );
					MSH_Feature_Flags::set_rollout( $flag_key, 'admins' );
				}
			}
		} else {
			// Fallback: Use simple option-based flag
			update_option( "msh_flag_{$flag_key}", $percentage >= 100 ? 'on' : $percentage, false );
		}

		// Update status
		update_option( "msh_migration_{$migration_key}_status", 'switched', false );
		$this->migrations[ $migration_key ]['status'] = 'switched';

		msh_telemetry( 'migration_switch', array(
			'migration'  => $migration_key,
			'flag'       => $flag_key,
			'percentage' => $percentage,
		) );

		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Switched to new structure for "%s". Feature flag "%s" enabled at %d%%.', 'msh-image-optimizer' ),
				$migration['name'],
				$flag_key,
				$percentage
			),
		);
	}

	/**
	 * CONTRACT: Remove old structure after verification.
	 *
	 * @param string $migration_key Migration key.
	 * @param bool   $confirm       Confirmation required.
	 * @return array Result with success/error.
	 */
	public function contract( $migration_key, $confirm = false ) {
		$migration = $this->get_migration( $migration_key );

		if ( ! $migration ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Migration "%s" not found.', 'msh-image-optimizer' ), $migration_key ),
			);
		}

		if ( 'switched' !== $migration['status'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					__( 'Migration "%s" must be switched before contracting. Current status: %s', 'msh-image-optimizer' ),
					$migration_key,
					$migration['status']
				),
			);
		}

		if ( ! $confirm ) {
			return array(
				'success' => false,
				'message' => __( 'Contract operation requires confirmation. This is a destructive operation.', 'msh-image-optimizer' ),
			);
		}

		// If no contract SQL, mark as complete
		if ( empty( $migration['contract_sql'] ) ) {
			update_option( "msh_migration_{$migration_key}_status", 'contracted', false );
			$this->migrations[ $migration_key ]['status'] = 'contracted';

			return array(
				'success' => true,
				'message' => sprintf(
					__( 'No cleanup needed for "%s". Marked as contracted.', 'msh-image-optimizer' ),
					$migration['name']
				),
			);
		}

		// Read contract SQL file
		$sql_file = MSH_IO_PLUGIN_DIR . 'includes/' . $migration['contract_sql'];

		if ( ! file_exists( $sql_file ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Contract SQL file not found: %s', 'msh-image-optimizer' ), $sql_file ),
			);
		}

		$sql = file_get_contents( $sql_file );

		if ( empty( $sql ) ) {
			return array(
				'success' => false,
				'message' => __( 'Contract SQL file is empty.', 'msh-image-optimizer' ),
			);
		}

		// Replace table prefix placeholder
		global $wpdb;
		$sql = str_replace( '{wp_prefix}', $wpdb->prefix, $sql );

		// Execute SQL
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $wpdb->last_error ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'SQL error: %s', 'msh-image-optimizer' ), $wpdb->last_error ),
			);
		}

		// Update status
		update_option( "msh_migration_{$migration_key}_status", 'contracted', false );
		$this->migrations[ $migration_key ]['status'] = 'contracted';

		msh_telemetry( 'migration_contract', array(
			'migration' => $migration_key,
		) );

		return array(
			'success' => true,
			'message' => sprintf(
				__( 'Contract complete for "%s". Old structure removed.', 'msh-image-optimizer' ),
				$migration['name']
			),
		);
	}

	/**
	 * Get migration status summary.
	 *
	 * @return array Status summary.
	 */
	public function get_status_summary() {
		$summary = array(
			'total'      => count( $this->migrations ),
			'pending'    => 0,
			'expanded'   => 0,
			'backfilled' => 0,
			'switched'   => 0,
			'contracted' => 0,
		);

		foreach ( $this->migrations as $migration ) {
			$status = $migration['status'];
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ]++;
			}
		}

		return $summary;
	}
}
