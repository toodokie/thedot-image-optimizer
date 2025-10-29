<?php
/**
 * AI service scaffolding and access control.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_AI_Service {
	/**
	 * Singleton instance.
	 *
	 * @var MSH_AI_Service|null
	 */
	private static $instance = null;

	/**
	 * Whether batch caching is currently active.
	 *
	 * @var bool
	 */
	private static $batch_enabled = false;

	/**
	 * Cached options for batch operations.
	 *
	 * @var array
	 */
	private static $batch_cache = array();

	/**
	 * Last access state (for debugging / messaging).
	 *
	 * @var array
	 */
	private $last_state = array();

	/**
	 * Credit plan mappings (credits per month)
	 */
	const PLAN_CREDITS = array(
		'free'        => 0,
		'ai_starter'  => 100,
		'ai_pro'      => 500,
		'ai_business' => 2000,
	);

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return MSH_AI_Service Service instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Schedule monthly credit refresh
		if ( ! wp_next_scheduled( 'msh_ai_refresh_credits' ) ) {
			wp_schedule_event( strtotime( 'first day of next month midnight' ), 'monthly', 'msh_ai_refresh_credits' );
		}

		add_action( 'msh_ai_refresh_credits', array( $this, 'refresh_monthly_credits' ) );
	}

	/**
	 * Prime the AI option cache for batch operations.
	 *
	 * @param array $options Seed values for options.
	 */
	public static function prime_batch( array $options ) {
		self::$batch_enabled = true;
		self::$batch_cache   = $options;
	}

	/**
	 * Clear the AI option cache after batch operations.
	 */
	public static function clear_batch() {
		self::$batch_enabled = false;
		self::$batch_cache   = array();
	}

	/**
	 * Retrieve an option value, using the batch cache when enabled.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function get_cached_option( $name, $default = null ) {
		if ( self::$batch_enabled && array_key_exists( $name, self::$batch_cache ) ) {
			return self::$batch_cache[ $name ];
		}

		return get_option( $name, $default );
	}

	/**
	 * Update an option and keep the batch cache in sync.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	private static function update_cached_option( $name, $value ) {
		if ( self::$batch_enabled ) {
			self::$batch_cache[ $name ] = $value;
		}

		return update_option( $name, $value );
	}

	/**
	 * Determine whether AI can run for the current configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     @type bool   $allowed     Whether AI is allowed.
	 *     @type string $mode        Mode name (manual|assist|hybrid).
	 *     @type string $access_mode How access was granted (byok|bundled).
	 *     @type string $plan_tier   Current plan tier.
	 *     @type string $reason      Reason for denial (when !$allowed).
	 *     @type string $api_key     API key for BYOK flows (empty for bundled).
	 *     @type array  $features    Enabled AI feature flags.
	 * }
	 */
	public function determine_access_state() {
		$mode      = self::get_cached_option( 'msh_ai_mode', 'manual' );
		$plan_tier = self::get_cached_option( 'msh_plan_tier', 'free' );
		$api_key   = trim( (string) self::get_cached_option( 'msh_ai_api_key', '' ) );
		$features  = self::get_cached_option( 'msh_ai_features', array() );
		if ( ! is_array( $features ) ) {
			$features = array();
		}

		$state = array(
			'allowed'     => false,
			'mode'        => $mode,
			'access_mode' => '',
			'plan_tier'   => $plan_tier,
			'reason'      => '',
			'api_key'     => '',
			'features'    => $features,
		);

		if ( $mode === 'manual' ) {
			$state['reason']  = 'manual_mode';
			$this->last_state = $state;
			return $state;
		}

		if ( ! in_array( 'meta', $features, true ) ) {
			$state['reason']  = 'feature_disabled';
			$this->last_state = $state;
			return $state;
		}

		if ( $api_key !== '' ) {
			$state['allowed']           = true;
			$state['access_mode']       = 'byok';
			$state['api_key']           = $api_key;
			$state['credits_remaining'] = PHP_INT_MAX; // Unlimited for BYOK
			$this->last_state           = $state;
			return $state;
		}

		$paid_tiers = apply_filters( 'msh_ai_paid_tiers', array( 'ai_starter', 'ai_pro', 'ai_business' ) );
		if ( in_array( $plan_tier, $paid_tiers, true ) ) {
			// Check credit balance
			$credits_remaining = $this->get_credit_balance();

			if ( $credits_remaining <= 0 ) {
				$state['allowed']           = false;
				$state['reason']            = 'insufficient_credits';
				$state['credits_remaining'] = 0;
				$this->last_state           = $state;
				return $state;
			}

			$state['allowed']           = true;
			$state['access_mode']       = 'bundled';
			$state['credits_remaining'] = $credits_remaining;
			$this->last_state           = $state;
			return $state;
		}

		$state['reason']  = 'upgrade_required';
		$this->last_state = $state;
		return $state;
	}

	/**
	 * Get current credit balance.
	 *
	 * @since 1.0.0
	 *
	 * @return int Current credits available.
	 */
	public function get_credit_balance() {
		$balance = self::get_cached_option( 'msh_ai_credit_balance', null );

		// Initialize if first time
		if ( $balance === null ) {
			$balance = $this->initialize_credits();
		}

		return max( 0, (int) $balance );
	}

	/**
	 * Initialize credits based on current plan tier.
	 *
	 * @return int Initial credit balance.
	 */
	private function initialize_credits() {
		$plan_tier = self::get_cached_option( 'msh_plan_tier', 'free' );
		$credits   = self::PLAN_CREDITS[ $plan_tier ] ?? 0;

		self::update_cached_option( 'msh_ai_credit_balance', $credits );
		self::update_cached_option( 'msh_ai_credit_last_reset', time() );

		return $credits;
	}

	/**
	 * Decrement credit balance.
	 *
	 * @since 1.0.0
	 *
	 * @param int $amount Amount to decrement (default: 1).
	 *
	 * @return bool True if successfully decremented, false if insufficient.
	 */
	public function decrement_credits( $amount = 1 ) {
		$balance = $this->get_credit_balance();

		if ( $balance < $amount ) {
			return false;
		}

		$new_balance = $balance - $amount;
		self::update_cached_option( 'msh_ai_credit_balance', $new_balance );

		// Log usage
		$this->log_credit_usage( $amount );

		return true;
	}

	/**
	 * Log credit usage for analytics.
	 *
	 * @param int $amount Credits used.
	 */
	private function log_credit_usage( $amount ) {
		$usage     = self::get_cached_option( 'msh_ai_credit_usage', array() );
		$month_key = wp_date( 'Y-m' );

		if ( ! isset( $usage[ $month_key ] ) ) {
			$usage[ $month_key ] = 0;
		}

		$usage[ $month_key ] += $amount;

		// Keep only last 12 months
		if ( count( $usage ) > 12 ) {
			ksort( $usage );
			$usage = array_slice( $usage, -12, null, true );
		}

		self::update_cached_option( 'msh_ai_credit_usage', $usage );
	}

	/**
	 * Refresh monthly credits (called by WP-Cron).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function refresh_monthly_credits() {
		$plan_tier = self::get_cached_option( 'msh_plan_tier', 'free' );
		$credits   = self::PLAN_CREDITS[ $plan_tier ] ?? 0;

		self::update_cached_option( 'msh_ai_credit_balance', $credits );
		self::update_cached_option( 'msh_ai_credit_last_reset', time() );

		error_log( '[MSH AI] Monthly credits refreshed: ' . $credits . ' credits for plan ' . $plan_tier );
	}

	/**
	 * Convenience wrapper indicating whether AI is available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if AI usage is allowed.
	 */
	public function can_use_ai() {
		$state = $this->determine_access_state();
		return $state['allowed'];
	}

	/**
	 * Fetch the last evaluated state (for messaging/debugging).
	 *
	 * @since 1.0.0
	 *
	 * @return array Last evaluated access state.
	 */
	public function get_last_state() {
		return $this->last_state;
	}

	/**
	 * Attempt to generate metadata via AI before falling back to heuristics.
	 *
	 * @since 1.0.0
	 *
	 * @param int                           $attachment_id Attachment identifier.
	 * @param array                         $context       Context payload.
	 * @param MSH_Contextual_Meta_Generator $generator     Generator instance (for filters).
	 * @param array                         $ai_options    AI options (mode, fields, language, etc).
	 * @return array|null
	 */
	public function maybe_generate_metadata( $attachment_id, array $context, $generator, $ai_options = array() ) {
		$state = $this->determine_access_state();

		// DEBUG: Log access state
		error_log( sprintf(
			'[MSH DEBUG AI Service] Attachment %d - Access state: allowed=%s, reason=%s, mode=%s, access_mode=%s, features=%s',
			$attachment_id,
			$state['allowed'] ? 'TRUE' : 'FALSE',
			$state['reason'] ?? 'none',
			$state['mode'] ?? 'unknown',
			$state['access_mode'] ?? 'none',
			json_encode( $state['features'] ?? array() )
		) );

		if ( ! $state['allowed'] ) {
			error_log( sprintf( '[MSH DEBUG AI Service] Attachment %d - Access DENIED: %s', $attachment_id, $state['reason'] ?? 'unknown' ) );
			return null;
		}

		// Get active profile context for business name, location, etc.
		$active_context = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
			? MSH_Image_Optimizer_Context_Helper::get_active_context()
			: array();

		$payload = array(
			'attachment_id' => $attachment_id,
			'context'       => ! empty( $active_context ) ? $active_context : $context,
			'mode'          => $state['mode'],
			'access_mode'   => $state['access_mode'],
			'plan_tier'     => $state['plan_tier'],
			'features'      => $state['features'],
			'api_key'       => $state['api_key'],
			'ai_options'    => $ai_options, // Pass AI regeneration options (mode, fields)
		);

		/**
		 * Filter to allow AI providers to generate metadata.
		 *
		 * Return an associative array with `title`, `alt_text`, `caption`, `description`
		 * (any missing keys will fall back to heuristics).
		 *
		 * @param array|null                     $metadata Null or array of metadata fields.
		 * @param array                           $payload  Request payload (context, plan, api key).
		 * @param MSH_Contextual_Meta_Generator   $generator Generator instance for convenience.
		 */
		$metadata = apply_filters( 'msh_ai_generate_metadata', null, $payload, $generator );

		if ( ! is_array( $metadata ) || empty( $metadata ) ) {
			return null;
		}

		// DECREMENT CREDITS for bundled access
		if ( $state['access_mode'] === 'bundled' ) {
			$success = $this->decrement_credits( 1 );

			if ( ! $success ) {
				// This shouldn't happen (we checked above), but log it
				error_log( '[MSH AI] Failed to decrement credits after AI call' );
			} else {
				error_log( '[MSH AI] Credit used. Remaining: ' . $this->get_credit_balance() );
			}
		}

		$allowed_keys = array( 'title', 'alt_text', 'caption', 'description', 'filename_slug' );
		$prepared     = array();
		foreach ( $allowed_keys as $key ) {
			if ( isset( $metadata[ $key ] ) && is_string( $metadata[ $key ] ) && $metadata[ $key ] !== '' ) {
				$prepared[ $key ] = $metadata[ $key ];
			}
		}

		return ! empty( $prepared ) ? $prepared : null;
	}

	/**
	 * Estimate cost and check credits for a bulk regeneration job.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attachment_ids Attachment IDs to process.
	 * @param array $fields         Fields to regenerate.
	 *
	 * @return array|WP_Error Estimate details or error.
	 */
	public function estimate_bulk_job_cost( $attachment_ids, $fields = array() ) {
		$count = count( $attachment_ids );

		if ( $count === 0 ) {
			return new WP_Error( 'empty_job', __( 'No images to process.', 'msh-image-optimizer' ) );
		}

		// Determine access state
		$access_state = $this->determine_access_state();

		// CRITICAL FIX: Check 'allowed' key, not 'access' (which doesn't exist)
		if ( ! $access_state['allowed'] ) {
			return new WP_Error(
				'no_access',
				sprintf(
					__( 'AI features are not enabled. Reason: %s', 'msh-image-optimizer' ),
					$access_state['reason'] ?? 'unknown'
				)
			);
		}

		// Calculate estimated cost
		$estimated_cost = $count; // 1 credit per image

		// CRITICAL FIX: Check 'access_mode' key, not 'access'
		// Check credits availability
		if ( $access_state['access_mode'] === 'bundled' ) {
			$credits_available = $access_state['credits_remaining'];

			if ( $estimated_cost > $credits_available ) {
				return new WP_Error(
					'insufficient_credits',
					sprintf(
						__( 'Insufficient credits. Need %1$d credits, but only %2$d available.', 'msh-image-optimizer' ),
						$estimated_cost,
						$credits_available
					)
				);
			}
		} elseif ( $access_state['access_mode'] === 'byok' ) {
			$credits_available = PHP_INT_MAX; // Unlimited with BYOK
		} else {
			// Fallback for unexpected access modes
			$credits_available = 0;
		}

		// CRITICAL FIX: Use 'plan_tier' key, not 'plan'
		return array(
			'estimated_cost'    => $estimated_cost,
			'credits_available' => $credits_available,
			'access_mode'       => $access_state['access_mode'],
			'plan_tier'         => $access_state['plan_tier'],
			'images_to_process' => $count,
		);
	}

	/**
	 * Get recent regeneration jobs for UI display.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Number of jobs to retrieve.
	 *
	 * @return array Jobs list.
	 */
	public function get_recent_jobs( $limit = 5 ) {
		$jobs = self::get_cached_option( 'msh_metadata_regen_jobs', array() );

		// Sort by created_at descending
		uasort(
			$jobs,
			function ( $a, $b ) {
				return ( $b['created_at'] ?? 0 ) - ( $a['created_at'] ?? 0 );
			}
		);

		return array_slice( $jobs, 0, $limit, true );
	}
}
