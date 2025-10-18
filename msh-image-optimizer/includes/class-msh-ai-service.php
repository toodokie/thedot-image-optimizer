<?php
/**
 * AI service scaffolding and access control.
 *
 * @package MSH_Image_Optimizer
 */

if (!defined('ABSPATH')) {
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
     * Last access state (for debugging / messaging).
     *
     * @var array
     */
    private $last_state = array();

    /**
     * Credit plan mappings (credits per month)
     */
    const PLAN_CREDITS = [
        'free' => 0,
        'ai_starter' => 100,
        'ai_pro' => 500,
        'ai_business' => 2000,
    ];

    /**
     * Get singleton instance.
     *
     * @return MSH_AI_Service
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        // Schedule monthly credit refresh
        if (!wp_next_scheduled('msh_ai_refresh_credits')) {
            wp_schedule_event(strtotime('first day of next month midnight'), 'monthly', 'msh_ai_refresh_credits');
        }

        add_action('msh_ai_refresh_credits', [$this, 'refresh_monthly_credits']);
    }

    /**
     * Determine whether AI can run for the current configuration.
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
        $mode = get_option('msh_ai_mode', 'manual');
        $plan_tier = get_option('msh_plan_tier', 'free');
        $api_key = trim((string) get_option('msh_ai_api_key', ''));
        $features = get_option('msh_ai_features', array());
        if (!is_array($features)) {
            $features = array();
        }

        $state = array(
            'allowed' => false,
            'mode' => $mode,
            'access_mode' => '',
            'plan_tier' => $plan_tier,
            'reason' => '',
            'api_key' => '',
            'features' => $features,
        );

        if ($mode === 'manual') {
            $state['reason'] = 'manual_mode';
            $this->last_state = $state;
            return $state;
        }

        if (!in_array('meta', $features, true)) {
            $state['reason'] = 'feature_disabled';
            $this->last_state = $state;
            return $state;
        }

        if ($api_key !== '') {
            $state['allowed'] = true;
            $state['access_mode'] = 'byok';
            $state['api_key'] = $api_key;
            $state['credits_remaining'] = PHP_INT_MAX; // Unlimited for BYOK
            $this->last_state = $state;
            return $state;
        }

        $paid_tiers = apply_filters('msh_ai_paid_tiers', array('ai_starter', 'ai_pro', 'ai_business'));
        if (in_array($plan_tier, $paid_tiers, true)) {
            // Check credit balance
            $credits_remaining = $this->get_credit_balance();

            if ($credits_remaining <= 0) {
                $state['allowed'] = false;
                $state['reason'] = 'insufficient_credits';
                $state['credits_remaining'] = 0;
                $this->last_state = $state;
                return $state;
            }

            $state['allowed'] = true;
            $state['access_mode'] = 'bundled';
            $state['credits_remaining'] = $credits_remaining;
            $this->last_state = $state;
            return $state;
        }

        $state['reason'] = 'upgrade_required';
        $this->last_state = $state;
        return $state;
    }

    /**
     * Get current credit balance.
     *
     * @return int Current credits available.
     */
    public function get_credit_balance() {
        $balance = get_option('msh_ai_credit_balance', null);

        // Initialize if first time
        if ($balance === null) {
            $balance = $this->initialize_credits();
        }

        return max(0, (int) $balance);
    }

    /**
     * Initialize credits based on current plan tier.
     *
     * @return int Initial credit balance.
     */
    private function initialize_credits() {
        $plan_tier = get_option('msh_plan_tier', 'free');
        $credits = self::PLAN_CREDITS[$plan_tier] ?? 0;

        update_option('msh_ai_credit_balance', $credits);
        update_option('msh_ai_credit_last_reset', time());

        return $credits;
    }

    /**
     * Decrement credit balance.
     *
     * @param int $amount Amount to decrement (default: 1).
     *
     * @return bool True if successfully decremented, false if insufficient.
     */
    public function decrement_credits($amount = 1) {
        $balance = $this->get_credit_balance();

        if ($balance < $amount) {
            return false;
        }

        $new_balance = $balance - $amount;
        update_option('msh_ai_credit_balance', $new_balance);

        // Log usage
        $this->log_credit_usage($amount);

        return true;
    }

    /**
     * Log credit usage for analytics.
     *
     * @param int $amount Credits used.
     */
    private function log_credit_usage($amount) {
        $usage = get_option('msh_ai_credit_usage', []);
        $month_key = date('Y-m');

        if (!isset($usage[$month_key])) {
            $usage[$month_key] = 0;
        }

        $usage[$month_key] += $amount;

        // Keep only last 12 months
        if (count($usage) > 12) {
            ksort($usage);
            $usage = array_slice($usage, -12, null, true);
        }

        update_option('msh_ai_credit_usage', $usage);
    }

    /**
     * Refresh monthly credits (called by WP-Cron).
     */
    public function refresh_monthly_credits() {
        $plan_tier = get_option('msh_plan_tier', 'free');
        $credits = self::PLAN_CREDITS[$plan_tier] ?? 0;

        update_option('msh_ai_credit_balance', $credits);
        update_option('msh_ai_credit_last_reset', time());

        error_log('[MSH AI] Monthly credits refreshed: ' . $credits . ' credits for plan ' . $plan_tier);
    }

    /**
     * Convenience wrapper.
     *
     * @return bool
     */
    public function can_use_ai() {
        $state = $this->determine_access_state();
        return $state['allowed'];
    }

    /**
     * Fetch the last evaluated state (for messaging/debugging).
     *
     * @return array
     */
    public function get_last_state() {
        return $this->last_state;
    }

    /**
     * Attempt to generate metadata via AI before falling back to heuristics.
     *
     * @param int                            $attachment_id Attachment identifier.
     * @param array                          $context       Context payload.
     * @param MSH_Contextual_Meta_Generator  $generator     Generator instance (for filters).
     * @return array|null
     */
    public function maybe_generate_metadata($attachment_id, array $context, $generator, $ai_options = []) {
        $state = $this->determine_access_state();
        if (!$state['allowed']) {
            return null;
        }

        // Get active profile context for business name, location, etc.
        $active_context = class_exists('MSH_Image_Optimizer_Context_Helper')
            ? MSH_Image_Optimizer_Context_Helper::get_active_context()
            : array();

        $payload = array(
            'attachment_id' => $attachment_id,
            'context' => !empty($active_context) ? $active_context : $context,
            'mode' => $state['mode'],
            'access_mode' => $state['access_mode'],
            'plan_tier' => $state['plan_tier'],
            'features' => $state['features'],
            'api_key' => $state['api_key'],
            'ai_options' => $ai_options, // Pass AI regeneration options (mode, fields)
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
        $metadata = apply_filters('msh_ai_generate_metadata', null, $payload, $generator);

        if (!is_array($metadata) || empty($metadata)) {
            return null;
        }

        // DECREMENT CREDITS for bundled access
        if ($state['access_mode'] === 'bundled') {
            $success = $this->decrement_credits(1);

            if (!$success) {
                // This shouldn't happen (we checked above), but log it
                error_log('[MSH AI] Failed to decrement credits after AI call');
            } else {
                error_log('[MSH AI] Credit used. Remaining: ' . $this->get_credit_balance());
            }
        }

        $allowed_keys = array('title', 'alt_text', 'caption', 'description', 'filename_slug');
        $prepared = array();
        foreach ($allowed_keys as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key]) && $metadata[$key] !== '') {
                $prepared[$key] = $metadata[$key];
            }
        }

        return !empty($prepared) ? $prepared : null;
    }

    /**
     * Estimate cost and check credits for a bulk regeneration job.
     *
     * @param array $attachment_ids Attachment IDs to process.
     * @param array $fields Fields to regenerate.
     *
     * @return array|WP_Error Estimate details or error.
     */
    public function estimate_bulk_job_cost($attachment_ids, $fields = []) {
        $count = count($attachment_ids);

        if ($count === 0) {
            return new WP_Error('empty_job', __('No images to process.', 'msh-image-optimizer'));
        }

        // Determine access state
        $access_state = $this->determine_access_state();

        if ($access_state['access'] === 'none') {
            return new WP_Error('no_access', __('AI features are not enabled.', 'msh-image-optimizer'));
        }

        // Calculate estimated cost
        $estimated_cost = $count; // 1 credit per image

        // Check credits availability
        if ($access_state['access'] === 'bundled') {
            $credits_available = $access_state['credits_remaining'];

            if ($estimated_cost > $credits_available) {
                return new WP_Error(
                    'insufficient_credits',
                    sprintf(
                        __('Insufficient credits. Need %d credits, but only %d available.', 'msh-image-optimizer'),
                        $estimated_cost,
                        $credits_available
                    )
                );
            }
        } elseif ($access_state['access'] === 'byok') {
            $credits_available = PHP_INT_MAX; // Unlimited with BYOK
        }

        return [
            'estimated_cost' => $estimated_cost,
            'credits_available' => $credits_available,
            'access_mode' => $access_state['access'],
            'plan_tier' => $access_state['plan'],
            'images_to_process' => $count,
        ];
    }

    /**
     * Get recent regeneration jobs for UI display.
     *
     * @param int $limit Number of jobs to retrieve.
     *
     * @return array Jobs list.
     */
    public function get_recent_jobs($limit = 5) {
        $jobs = get_option('msh_metadata_regen_jobs', []);

        // Sort by created_at descending
        uasort($jobs, function($a, $b) {
            return ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0);
        });

        return array_slice($jobs, 0, $limit, true);
    }
}

