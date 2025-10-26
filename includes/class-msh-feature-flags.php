<?php
/**
 * Feature flag management utilities.
 *
 * Provides multi-level evaluation (user overrides, role capabilities,
 * global settings, and filters) plus helpers for admin UI and CLI tooling.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature flag registry and helpers.
 */
class MSH_Feature_Flags {

	/**
	 * Option key storing global flag states.
	 */
	const OPTION_KEY = 'msh_feature_flags';

	/**
	 * Option key storing rollout preferences.
	 */
	const ROLLOUT_OPTION_KEY = 'msh_feature_flag_rollouts';

	/**
	 * Prefix for user meta overrides and role capabilities.
	 */
	const USER_META_PREFIX = 'msh_feature_';

	/**
	 * Registry filter name.
	 */
	const REGISTRY_FILTER = 'msh_feature_flags_registry';

	/**
	 * Value filter name.
	 */
	const VALUE_FILTER = 'msh_feature_flag_value';

	/**
	 * Return the registered flags and metadata.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_registry() {
		$defaults = array(
			'template_intelligence' => array(
				'label'       => __( 'Template Intelligence', 'msh-image-optimizer' ),
				'description' => __( 'Use templates before AI calls to reduce token spend.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'phase6',
				'risk'        => 'medium',
			),
			'read_from_templates'   => array(
				'label'       => __( 'Read From Templates', 'msh-image-optimizer' ),
				'description' => __( 'Serve template-driven metadata instead of AI responses (Phase 6 migration).', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'migration',
				'risk'        => 'high',
			),
			'dual_write_templates'  => array(
				'label'       => __( 'Dual Write Templates', 'msh-image-optimizer' ),
				'description' => __( 'Write to legacy + template stores for parity checks.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'migration',
				'risk'        => 'medium',
			),
			'validate_template_parity' => array(
				'label'       => __( 'Validate Template Parity', 'msh-image-optimizer' ),
				'description' => __( 'Run parity checks between template and AI metadata.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'migration',
				'risk'        => 'medium',
			),
			'collect_metrics'       => array(
				'label'       => __( 'Collect Metrics', 'msh-image-optimizer' ),
				'description' => __( 'Enable Phase 8 metrics collection pipeline.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'phase8',
				'risk'        => 'medium',
			),
			'pro_dashboard_v2'      => array(
				'label'       => __( 'Pro Dashboard v2', 'msh-image-optimizer' ),
				'description' => __( 'Preview the redesigned Pro dashboard experience.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'trackb',
				'risk'        => 'low',
			),
			'ai_safe_rename'        => array(
				'label'       => __( 'AI Safe Rename', 'msh-image-optimizer' ),
				'description' => __( 'Generate SEO filenames with AI before upload.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'phase6',
				'risk'        => 'medium',
			),
			'avif_conversion'       => array(
				'label'       => __( 'AVIF Conversion', 'msh-image-optimizer' ),
				'description' => __( 'Convert images to AVIF format with cloud processing.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'phase10',
				'risk'        => 'high',
			),
			'picture_tag_delivery'  => array(
				'label'       => __( 'Picture Tag Delivery', 'msh-image-optimizer' ),
				'description' => __( 'Serve responsive <picture> stacks with AVIF/WebP fallbacks.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'phase10',
				'risk'        => 'medium',
			),
			'priority_loader'       => array(
				'label'       => __( 'Priority Loader', 'msh-image-optimizer' ),
				'description' => __( 'Experimental AI-prioritised lazy loader.', 'msh-image-optimizer' ),
				'default'     => false,
				'category'    => 'phase10',
				'risk'        => 'high',
			),
		);

		return apply_filters( self::REGISTRY_FILTER, $defaults );
	}

	/**
	 * Normalise a flag key (slug).
	 *
	 * @param string $flag Raw flag name.
	 * @return string
	 */
	public static function normalize_flag( $flag ) {
		return sanitize_key( $flag );
	}

	/**
	 * Retrieve global flag values.
	 *
	 * @return array<string,bool>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Retrieve rollout preferences.
	 *
	 * @return array<string,string>
	 */
	public static function get_all_rollouts() {
		$stored = get_option( self::ROLLOUT_OPTION_KEY, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Get rollout mode for a flag.
	 *
	 * @param string $flag Flag name.
	 * @return string|array String mode or array with percentage data.
	 */
	public static function get_rollout( $flag ) {
		$flag     = self::normalize_flag( $flag );
		$rollouts = self::get_all_rollouts();
		$rollout  = isset( $rollouts[ $flag ] ) ? $rollouts[ $flag ] : 'everyone';

		// Handle new percentage format (array with mode + percentage)
		if ( is_array( $rollout ) ) {
			return $rollout;
		}

		// Legacy string format
		if ( ! in_array( $rollout, array( 'everyone', 'admins', 'custom' ), true ) ) {
			$rollout = 'everyone';
		}

		return $rollout;
	}

	/**
	 * Persist rollout mode for a flag.
	 *
	 * @param string $flag    Flag name.
	 * @param string $rollout Rollout value.
	 * @return void
	 */
	public static function set_rollout( $flag, $rollout ) {
		$flag     = self::normalize_flag( $flag );
		$rollouts = self::get_all_rollouts();

		if ( ! in_array( $rollout, array( 'everyone', 'admins', 'custom' ), true ) ) {
			$rollout = 'everyone';
		}

		$rollouts[ $flag ] = $rollout;
		update_option( self::ROLLOUT_OPTION_KEY, $rollouts, false );
	}

	/**
	 * Retrieve a flag's global value.
	 *
	 * @param string $flag Flag name.
	 * @return bool
	 */
	public static function get( $flag ) {
		$flag     = self::normalize_flag( $flag );
		$registry = self::get_registry();
		$defaults = isset( $registry[ $flag ]['default'] ) ? (bool) $registry[ $flag ]['default'] : false;
		$all      = self::get_all();

		return isset( $all[ $flag ] ) ? (bool) $all[ $flag ] : $defaults;
	}

	/**
	 * Persist a flag value globally.
	 *
	 * @param string $flag  Flag name.
	 * @param bool   $value Enabled state.
	 * @return void
	 */
	public static function set( $flag, $value ) {
		$flag  = self::normalize_flag( $flag );
		$all   = self::get_all();
		$value = (bool) $value;

		$all[ $flag ] = $value;

		update_option( self::OPTION_KEY, $all, false );
	}

	/**
	 * Enable a flag for a percentage of users using deterministic hashing.
	 *
	 * @param string $flag       Flag name.
	 * @param int    $percentage Percentage (0-100).
	 * @return void
	 */
	public static function enable_percentage( $flag, $percentage ) {
		$flag       = self::normalize_flag( $flag );
		$percentage = max( 0, min( 100, (int) $percentage ) );

		// Enable the flag globally
		self::set( $flag, true );

		// Store percentage in rollout data (extended format)
		$rollouts             = self::get_all_rollouts();
		$rollouts[ $flag ] = array(
			'mode'       => 'percentage',
			'percentage' => $percentage,
		);
		update_option( self::ROLLOUT_OPTION_KEY, $rollouts, false );
	}

	/**
	 * Remove a stored flag override.
	 *
	 * @param string $flag Flag name.
	 * @return void
	 */
	public static function remove( $flag ) {
		$flag = self::normalize_flag( $flag );
		$all  = self::get_all();

		if ( isset( $all[ $flag ] ) ) {
			unset( $all[ $flag ] );
			update_option( self::OPTION_KEY, $all, false );
		}
	}

	/**
	 * Evaluate a feature flag for a given user.
	 *
	 * @param string $flag    Flag name.
	 * @param int    $user_id Optional user ID (0 = current user / anonymous).
	 * @return bool
	 */
	public static function evaluate( $flag, $user_id = 0 ) {
		$flag     = self::normalize_flag( $flag );
		$registry = self::get_registry();

		if ( ! isset( $registry[ $flag ] ) ) {
			return false;
		}

		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$rollout = self::get_rollout( $flag );
		$source  = 'default';
		$result  = false;

		if ( $user_id ) {
			$raw_override = get_user_meta( $user_id, self::USER_META_PREFIX . $flag, true );
			if ( '' !== $raw_override ) {
				$result = filter_var( $raw_override, FILTER_VALIDATE_BOOLEAN );
				$source = 'user-meta';
				self::maybe_log_evaluation( $flag, $result, $source, $user_id, $rollout );
				return (bool) $result;
			}
		}

		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user && user_can( $user, self::USER_META_PREFIX . $flag ) ) {
				$result = true;
				$source = 'capability';
				self::maybe_log_evaluation( $flag, $result, $source, $user_id, $rollout );
				return true;
			}
		}

		$global_enabled = self::get( $flag );
		$result         = $global_enabled;
		$source         = 'global';

		if ( $global_enabled ) {
			// Handle percentage-based rollout
			if ( is_array( $rollout ) && isset( $rollout['mode'] ) && 'percentage' === $rollout['mode'] ) {
				$percentage = isset( $rollout['percentage'] ) ? (int) $rollout['percentage'] : 0;

				// Use deterministic hash so same user always gets same result
				$hash   = abs( crc32( $flag . ':' . $user_id ) );
				$cohort = $hash % 100;

				$result = ( $cohort < $percentage );
				$source = $result ? 'rollout-percentage-in' : 'rollout-percentage-out';
			} elseif ( 'admins' === $rollout ) {
				if ( $user_id ) {
					$result = user_can( $user_id, 'manage_options' );
					$source = $result ? 'rollout-admins' : 'rollout-admins-block';
				} else {
					$result = true;
					$source = 'rollout-admins';
				}
			} elseif ( 'custom' === $rollout ) {
				if ( $user_id ) {
					$user   = get_user_by( 'id', $user_id );
					$result = $user && user_can( $user, self::USER_META_PREFIX . $flag );
					$source = $result ? 'rollout-custom' : 'rollout-custom-block';
				} else {
					$result = false;
					$source = 'rollout-custom-block';
				}
			}
		}

		$filtered = apply_filters(
			self::VALUE_FILTER,
			$result,
			$flag,
			$user_id,
			array(
				'rollout'        => $rollout,
				'global_enabled' => $global_enabled,
				'source'         => $source,
			)
		);

		if ( (bool) $filtered !== (bool) $result ) {
			$result = (bool) $filtered;
			$source = 'filter';
		}

		self::maybe_log_evaluation( $flag, $result, $source, $user_id, $rollout );

		return (bool) $result;
	}

	/**
	 * Sample telemetry logs for flag evaluations.
	 *
	 * @param string $flag    Flag name.
	 * @param bool   $result  Result.
	 * @param string $source  Source descriptor.
	 * @param int    $user_id User ID.
	 * @param string $rollout Rollout mode.
	 * @return void
	 */
	private static function maybe_log_evaluation( $flag, $result, $source, $user_id, $rollout ) {
		if ( ! function_exists( 'msh_telemetry' ) ) {
			return;
		}

		if ( mt_rand( 1, 100 ) !== 1 ) {
			return;
		}

		msh_telemetry(
			'feature_flag_evaluation',
			array(
				'flag'    => $flag,
				'result'  => (bool) $result,
				'source'  => $source,
				'rollout' => $rollout,
				'user'    => $user_id ? (int) $user_id : 0,
			)
		);
	}
}

/**
 * Helper wrapper for evaluating feature flags.
 *
 * @param string $flag    Flag name.
 * @param int    $user_id Optional user ID (default current).
 * @return bool
 */
function msh_flag_enabled( $flag, $user_id = 0 ) {
	return MSH_Feature_Flags::evaluate( $flag, $user_id );
}
