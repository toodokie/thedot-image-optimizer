<?php
/**
 * Helper functions for onboarding/context data.
 *
 * @package MSH_Image_Optimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_Image_Optimizer_Context_Helper {

    /**
     * Sanitize context payload.
     *
     * @param array $context Input context.
     * @param bool  $touch_timestamp Whether to update the timestamp.
     * @param int   $existing_timestamp Existing timestamp for legacy data.
     * @return array
     */
    public static function sanitize_context($context, $touch_timestamp = false, $existing_timestamp = 0) {
        $context = is_array($context) ? $context : array();

        $sanitized = array(
            'business_name' => isset($context['business_name']) ? sanitize_text_field($context['business_name']) : '',
            'industry' => isset($context['industry']) ? sanitize_text_field($context['industry']) : '',
            'business_type' => isset($context['business_type']) ? sanitize_text_field($context['business_type']) : '',
            'target_audience' => isset($context['target_audience']) ? sanitize_text_field($context['target_audience']) : '',
            'pain_points' => isset($context['pain_points']) ? sanitize_textarea_field($context['pain_points']) : '',
            'demographics' => isset($context['demographics']) ? sanitize_text_field($context['demographics']) : '',
            'brand_voice' => isset($context['brand_voice']) ? sanitize_text_field($context['brand_voice']) : '',
            'uvp' => isset($context['uvp']) ? sanitize_textarea_field($context['uvp']) : '',
            'cta_preference' => isset($context['cta_preference']) ? sanitize_text_field($context['cta_preference']) : '',
            'city' => isset($context['city']) ? sanitize_text_field($context['city']) : '',
            'region' => isset($context['region']) ? sanitize_text_field($context['region']) : '',
            'service_area' => isset($context['service_area']) ? sanitize_text_field($context['service_area']) : '',
            'ai_interest' => !empty($context['ai_interest']),
        );

        $stored_timestamp = isset($context['updated_at']) ? absint($context['updated_at']) : absint($existing_timestamp);
        $sanitized['updated_at'] = $touch_timestamp ? current_time('timestamp') : $stored_timestamp;

        return $sanitized;
    }

    /**
     * Get label map for dropdowns.
     *
     * @return array
     */
    public static function get_label_map() {
        return array(
            'industry' => array(
                'legal' => __('Legal Services', 'msh-image-optimizer'),
                'accounting' => __('Accounting & Tax', 'msh-image-optimizer'),
                'consulting' => __('Business Consulting', 'msh-image-optimizer'),
                'marketing' => __('Marketing Agency', 'msh-image-optimizer'),
                'web_design' => __('Web Design / Development', 'msh-image-optimizer'),
                'plumbing' => __('Plumbing', 'msh-image-optimizer'),
                'hvac' => __('HVAC', 'msh-image-optimizer'),
                'electrical' => __('Electrical', 'msh-image-optimizer'),
                'renovation' => __('Renovation / Construction', 'msh-image-optimizer'),
                'dental' => __('Dental', 'msh-image-optimizer'),
                'medical' => __('Medical Practice', 'msh-image-optimizer'),
                'therapy' => __('Therapy / Counseling', 'msh-image-optimizer'),
                'wellness' => __('Wellness / Alternative', 'msh-image-optimizer'),
                'online_store' => __('Online Store', 'msh-image-optimizer'),
                'local_retail' => __('Local Retail', 'msh-image-optimizer'),
                'specialty' => __('Specialty Products', 'msh-image-optimizer'),
                'other' => __('Other / Not listed', 'msh-image-optimizer'),
            ),
            'business_type' => array(
                'local_service' => __('Local Service Provider', 'msh-image-optimizer'),
                'online_service' => __('Online Service Provider', 'msh-image-optimizer'),
                'ecommerce' => __('E-commerce', 'msh-image-optimizer'),
                'saas' => __('SaaS / Software', 'msh-image-optimizer'),
                'b2b' => __('B2B Services', 'msh-image-optimizer'),
                'b2c' => __('B2C Services', 'msh-image-optimizer'),
                'nonprofit' => __('Non-profit / Public Sector', 'msh-image-optimizer'),
            ),
            'brand_voice' => array(
                'professional' => __('Professional', 'msh-image-optimizer'),
                'friendly' => __('Friendly', 'msh-image-optimizer'),
                'casual' => __('Casual', 'msh-image-optimizer'),
                'technical' => __('Technical', 'msh-image-optimizer'),
            ),
            'cta_preference' => array(
                'soft' => __('Helpful / soft reminders', 'msh-image-optimizer'),
                'balanced' => __('Neutral / informative', 'msh-image-optimizer'),
                'direct' => __('Direct / action-focused', 'msh-image-optimizer'),
            ),
        );
    }

    /**
     * Resolve label for a stored value.
     *
     * @param string     $group Group name.
     * @param string     $value Stored value.
     * @param null|array $labels Optional labels array.
     * @return string
     */
    public static function lookup_label($group, $value, $labels = null) {
        if (empty($value)) {
            return '';
        }

        if (null === $labels) {
            $labels = self::get_label_map();
        }

        if (isset($labels[$group], $labels[$group][$value])) {
            return $labels[$group][$value];
        }

        return $value;
    }

    /**
     * Format context summary values for UI.
     *
     * @param array $context Sanitized context.
     * @param array $strings Optional translations.
     * @return array
     */
    public static function format_summary($context, $strings = array()) {
        $labels = self::get_label_map();

        $audience = isset($context['target_audience']) ? $context['target_audience'] : '';
        if (!empty($context['demographics'])) {
            $demographic_note = !empty($strings['onboardingSummaryDemographics'])
                ? sprintf($strings['onboardingSummaryDemographics'], $context['demographics'])
                : sprintf(__('Demographics: %s', 'msh-image-optimizer'), $context['demographics']);
            $audience = $audience ? $audience . ' — ' . $demographic_note : $demographic_note;
        }

        return array(
            'business_name' => isset($context['business_name']) ? $context['business_name'] : '',
            'industry' => self::lookup_label('industry', isset($context['industry']) ? $context['industry'] : '', $labels),
            'business_type' => self::lookup_label('business_type', isset($context['business_type']) ? $context['business_type'] : '', $labels),
            'target_audience' => $audience,
            'pain_points' => isset($context['pain_points']) ? $context['pain_points'] : '',
            'brand_voice' => self::lookup_label('brand_voice', isset($context['brand_voice']) ? $context['brand_voice'] : '', $labels),
            'uvp' => isset($context['uvp']) ? $context['uvp'] : '',
            'cta_preference' => self::lookup_label('cta_preference', isset($context['cta_preference']) ? $context['cta_preference'] : '', $labels),
            'location' => self::format_location_summary($context, $strings),
            'ai_interest' => !empty($context['ai_interest'])
                ? (!empty($strings['onboardingSummaryAiYes']) ? $strings['onboardingSummaryAiYes'] : __('Subscribed to updates', 'msh-image-optimizer'))
                : (!empty($strings['onboardingSummaryAiNo']) ? $strings['onboardingSummaryAiNo'] : __('No updates requested', 'msh-image-optimizer')),
        );
    }

    /**
     * Create a location summary string.
     *
     * @param array $context Context fields.
     * @param array $strings Optional translations.
     * @return string
     */
    public static function format_location_summary($context, $strings = array()) {
        $parts = array();

        if (!empty($context['city'])) {
            $parts[] = $context['city'];
        }

        if (!empty($context['region'])) {
            if (!empty($parts)) {
                $parts[count($parts) - 1] .= ', ' . $context['region'];
            } else {
                $parts[] = $context['region'];
            }
        }

        $location = implode(' ', $parts);

        if (!empty($context['service_area'])) {
            $service_area = !empty($strings['onboardingSummaryServiceArea'])
                ? sprintf($strings['onboardingSummaryServiceArea'], $context['service_area'])
                : sprintf(__('Service area: %s', 'msh-image-optimizer'), $context['service_area']);

            if (!empty($location)) {
                $location .= ' (' . $service_area . ')';
            } else {
                $location = $service_area;
            }
        }

        if (empty($location)) {
            $location = !empty($strings['onboardingSummaryNotSpecified'])
                ? $strings['onboardingSummaryNotSpecified']
                : __('Not specified', 'msh-image-optimizer');
        }

        return $location;
    }
}
