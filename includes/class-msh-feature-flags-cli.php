<?php
/**
 * WP-CLI integration for feature flag management.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Feature flag management commands.
	 */
	class MSH_Feature_Flags_CLI {

		/**
		 * List all registered feature flags and their status.
		 *
		 * ## EXAMPLES
		 *
		 *     # List all flags
		 *     wp msh flags list
		 *
		 * @subcommand list
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function list_flags( $args, $assoc_args ) {
			$registry = MSH_Feature_Flags::get_registry();
			$global   = MSH_Feature_Flags::get_all();
			$rollouts = MSH_Feature_Flags::get_all_rollouts();

			if ( empty( $registry ) ) {
				WP_CLI::line( 'No feature flags registered.' );
				return;
			}

			$rows = array();

			foreach ( $registry as $flag => $config ) {
				$rows[] = array(
					'flag'         => $flag,
					'status'       => isset( $global[ $flag ] ) && $global[ $flag ] ? 'on' : 'off',
					'rollout'      => isset( $rollouts[ $flag ] ) ? $rollouts[ $flag ] : 'everyone',
					'description'  => isset( $config['description'] ) ? $config['description'] : '',
					'category'     => isset( $config['category'] ) ? $config['category'] : '',
					'current_user' => msh_flag_enabled( $flag ) ? 'enabled' : 'disabled',
				);
			}

			WP_CLI\Utils\format_items(
				'table',
				$rows,
				array( 'flag', 'status', 'rollout', 'current_user', 'category', 'description' )
			);
		}

		/**
		 * Set a feature flag value globally or for a specific user.
		 *
		 * ## OPTIONS
		 *
		 * <flag>
		 * : Flag name (without msh_feature_ prefix).
		 *
		 * <value>
		 * : on/off or true/false/1/0.
		 *
		 * [--user=<id>]
		 * : Apply to a specific user (user meta override).
		 *
		 * [--rollout=<strategy>]
		 * : Update rollout strategy (everyone|admins|custom). Only used for global updates.
		 *
		 * ## EXAMPLES
		 *
		 *     wp msh flag set ai_safe_rename on
		 *     wp msh flag set avif_conversion off
		 *     wp msh flag set pro_dashboard_v2 on --user=5
		 *     wp msh flag set template_intelligence on --rollout=admins
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function set( $args, $assoc_args ) {
			if ( count( $args ) < 2 ) {
				WP_CLI::error( 'Flag name and value are required.' );
			}

			$flag   = MSH_Feature_Flags::normalize_flag( $args[0] );
			$value  = $this->string_to_bool( $args[1] );
			$registry = MSH_Feature_Flags::get_registry();

			if ( ! isset( $registry[ $flag ] ) ) {
				WP_CLI::error( sprintf( 'Unknown feature flag "%s".', $flag ) );
			}

			$user_id = isset( $assoc_args['user'] ) ? absint( $assoc_args['user'] ) : 0;

			if ( $user_id ) {
				update_user_meta( $user_id, MSH_Feature_Flags::USER_META_PREFIX . $flag, $value ? '1' : '0' );
				WP_CLI::success(
					sprintf(
						'Feature flag "%s" set to %s for user #%d.',
						$flag,
						$value ? 'ON' : 'OFF',
						$user_id
					)
				);
			} else {
				MSH_Feature_Flags::set( $flag, $value );

				if ( isset( $assoc_args['rollout'] ) ) {
					MSH_Feature_Flags::set_rollout( $flag, $assoc_args['rollout'] );
				}

				WP_CLI::success(
					sprintf(
						'Feature flag "%s" set to %s globally.',
						$flag,
						$value ? 'ON' : 'OFF'
					)
				);
			}

			if ( function_exists( 'msh_telemetry' ) ) {
				msh_telemetry(
					'feature_flag_changed',
					array(
						'flag'     => $flag,
						'value'    => (bool) $value,
						'user'     => $user_id ? (int) $user_id : 0,
						'rollout'  => MSH_Feature_Flags::get_rollout( $flag ),
						'changed_by' => get_current_user_id(),
					)
				);
			}
		}

		/**
		 * Check if a feature flag is enabled.
		 *
		 * Returns exit code 0 when enabled, 1 when disabled (useful for scripting).
		 *
		 * ## OPTIONS
		 *
		 * <flag>
		 * : Flag name (without msh_feature_ prefix).
		 *
		 * [--user=<id>]
		 * : Check for a specific user (default current user).
		 *
		 * ## EXAMPLES
		 *
		 *     wp msh flag check template_intelligence
		 *     wp msh flag check avif_conversion --user=5
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function check( $args, $assoc_args ) {
			if ( empty( $args ) ) {
				WP_CLI::error( 'Flag name is required.' );
			}

			$flag    = MSH_Feature_Flags::normalize_flag( $args[0] );
			$user_id = isset( $assoc_args['user'] ) ? absint( $assoc_args['user'] ) : 0;

			if ( ! isset( MSH_Feature_Flags::get_registry()[ $flag ] ) ) {
				WP_CLI::error( sprintf( 'Unknown feature flag "%s".', $flag ) );
			}

			$enabled = msh_flag_enabled( $flag, $user_id );

			if ( $enabled ) {
				WP_CLI::success( sprintf( 'Flag "%s" is ENABLED.', $flag ) );
				return 0;
			}

			WP_CLI::warning( sprintf( 'Flag "%s" is DISABLED.', $flag ) );
			return 1;
		}

		/**
		 * Convert string to boolean.
		 *
		 * @param string $value Raw value.
		 * @return bool
		 */
		private function string_to_bool( $value ) {
			$value = strtolower( trim( $value ) );

			return in_array( $value, array( 'on', '1', 'true', 'yes' ), true );
		}
	}

	WP_CLI::add_command( 'msh flags', 'MSH_Feature_Flags_CLI' );
	WP_CLI::add_command( 'msh flag', 'MSH_Feature_Flags_CLI' );
}
