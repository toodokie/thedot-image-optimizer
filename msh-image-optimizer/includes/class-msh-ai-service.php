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
            $this->last_state = $state;
            return $state;
        }

        $paid_tiers = apply_filters('msh_ai_paid_tiers', array('ai_starter', 'ai_pro', 'ai_business'));
        if (in_array($plan_tier, $paid_tiers, true)) {
            $state['allowed'] = true;
            $state['access_mode'] = 'bundled';
            $this->last_state = $state;
            return $state;
        }

        $state['reason'] = 'upgrade_required';
        $this->last_state = $state;
        return $state;
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
    public function maybe_generate_metadata($attachment_id, array $context, $generator) {
        $state = $this->determine_access_state();
        if (!$state['allowed']) {
            return null;
        }

        $payload = array(
            'attachment_id' => $attachment_id,
            'context' => $context,
            'mode' => $state['mode'],
            'access_mode' => $state['access_mode'],
            'plan_tier' => $state['plan_tier'],
            'features' => $state['features'],
            'api_key' => $state['api_key'],
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

        $allowed_keys = array('title', 'alt_text', 'caption', 'description');
        $prepared = array();
        foreach ($allowed_keys as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key]) && $metadata[$key] !== '') {
                $prepared[$key] = $metadata[$key];
            }
        }

        return !empty($prepared) ? $prepared : null;
    }
}

