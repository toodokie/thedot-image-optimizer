<?php
/**
 * Helper functions for onboarding/context data.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Image_Optimizer_Context_Helper {

	/**
	 * Sanitize context payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array $context           Input context.
	 * @param bool  $touch_timestamp   Whether to update the timestamp.
	 * @param int   $existing_timestamp Existing timestamp for legacy data.
	 * @return array Sanitised context array.
	 */
	public static function sanitize_context( $context, $touch_timestamp = false, $existing_timestamp = 0 ) {
		$context = is_array( $context ) ? $context : array();

		$sanitized = array(
			'business_name'   => isset( $context['business_name'] ) ? sanitize_text_field( $context['business_name'] ) : '',
			'industry'        => isset( $context['industry'] ) ? sanitize_text_field( $context['industry'] ) : '',
			'business_type'   => isset( $context['business_type'] ) ? sanitize_text_field( $context['business_type'] ) : '',
			'target_audience' => isset( $context['target_audience'] ) ? sanitize_text_field( $context['target_audience'] ) : '',
			'pain_points'     => isset( $context['pain_points'] ) ? sanitize_textarea_field( $context['pain_points'] ) : '',
			'demographics'    => isset( $context['demographics'] ) ? sanitize_text_field( $context['demographics'] ) : '',
			'brand_voice'     => isset( $context['brand_voice'] ) ? sanitize_text_field( $context['brand_voice'] ) : '',
			'uvp'             => isset( $context['uvp'] ) ? sanitize_textarea_field( $context['uvp'] ) : '',
			'cta_preference'  => isset( $context['cta_preference'] ) ? sanitize_text_field( $context['cta_preference'] ) : '',
			'city'            => isset( $context['city'] ) ? sanitize_text_field( $context['city'] ) : '',
			'region'          => isset( $context['region'] ) ? sanitize_text_field( $context['region'] ) : '',
			'country'         => isset( $context['country'] ) ? sanitize_text_field( $context['country'] ) : '',
			'service_area'    => isset( $context['service_area'] ) ? sanitize_text_field( $context['service_area'] ) : '',
			'locale'          => isset( $context['locale'] ) ? sanitize_text_field( $context['locale'] ) : '',
			'ai_interest'     => ! empty( $context['ai_interest'] ),
		);

		$stored_timestamp        = isset( $context['updated_at'] ) ? absint( $context['updated_at'] ) : absint( $existing_timestamp );
		$sanitized['updated_at'] = $touch_timestamp ? current_time( 'timestamp' ) : $stored_timestamp;

		return $sanitized;
	}

	/**
	 * Get label map for dropdowns.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of label groups.
	 */
	public static function get_label_map() {
		return array(
			'industry'       => array(
				'legal'        => __( 'Legal Services', 'msh-image-optimizer' ),
				'accounting'   => __( 'Accounting & Tax', 'msh-image-optimizer' ),
				'consulting'   => __( 'Business Consulting', 'msh-image-optimizer' ),
				'marketing'    => __( 'Marketing Agency', 'msh-image-optimizer' ),
				'web_design'   => __( 'Web Design / Development', 'msh-image-optimizer' ),
				'plumbing'     => __( 'Plumbing', 'msh-image-optimizer' ),
				'hvac'         => __( 'HVAC', 'msh-image-optimizer' ),
				'electrical'   => __( 'Electrical', 'msh-image-optimizer' ),
				'renovation'   => __( 'Renovation / Construction', 'msh-image-optimizer' ),
				'dental'       => __( 'Dental', 'msh-image-optimizer' ),
				'medical'      => __( 'Medical Practice', 'msh-image-optimizer' ),
				'therapy'      => __( 'Therapy / Counseling', 'msh-image-optimizer' ),
				'wellness'     => __( 'Wellness / Alternative', 'msh-image-optimizer' ),
				'online_store' => __( 'Online Store', 'msh-image-optimizer' ),
				'local_retail' => __( 'Local Retail', 'msh-image-optimizer' ),
				'specialty'    => __( 'Specialty Products', 'msh-image-optimizer' ),
				'other'        => __( 'Other / Not listed', 'msh-image-optimizer' ),
			),
			'business_type'  => array(
				'local_service'  => __( 'Local Service Provider', 'msh-image-optimizer' ),
				'online_service' => __( 'Online Service Provider', 'msh-image-optimizer' ),
				'professional'   => __( 'Professional Services', 'msh-image-optimizer' ),
				'ecommerce'      => __( 'E-commerce', 'msh-image-optimizer' ),
				'saas'           => __( 'SaaS / Software', 'msh-image-optimizer' ),
				'b2b'            => __( 'B2B Services', 'msh-image-optimizer' ),
				'b2c'            => __( 'B2C Services', 'msh-image-optimizer' ),
				'nonprofit'      => __( 'Non-profit / Public Sector', 'msh-image-optimizer' ),
			),
			'brand_voice'    => array(
				'professional' => __( 'Professional', 'msh-image-optimizer' ),
				'friendly'     => __( 'Friendly', 'msh-image-optimizer' ),
				'casual'       => __( 'Casual', 'msh-image-optimizer' ),
				'technical'    => __( 'Technical', 'msh-image-optimizer' ),
			),
			'cta_preference' => array(
				'soft'     => __( 'Helpful / soft reminders', 'msh-image-optimizer' ),
				'balanced' => __( 'Neutral / informative', 'msh-image-optimizer' ),
				'direct'   => __( 'Direct / action-focused', 'msh-image-optimizer' ),
			),
		);
	}

	/**
	 * Resolve label for a stored value.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $group  Group name.
	 * @param string     $value  Stored value.
	 * @param null|array $labels Optional labels array.
	 * @return string Friendly label.
	 */
	public static function lookup_label( $group, $value, $labels = null ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( null === $labels ) {
			$labels = self::get_label_map();
		}

		if ( isset( $labels[ $group ], $labels[ $group ][ $value ] ) ) {
			return $labels[ $group ][ $value ];
		}

		return $value;
	}

	/**
	 * Format context summary values for UI.
	 *
	 * @since 1.0.0
	 *
	 * @param array $context Sanitized context.
	 * @param array $strings Optional translations.
	 * @return array Summary fields keyed for UI display.
	 */
	public static function format_summary( $context, $strings = array() ) {
		$labels = self::get_label_map();

		$audience = isset( $context['target_audience'] ) ? $context['target_audience'] : '';
		if ( ! empty( $context['demographics'] ) ) {
			$demographic_note = ! empty( $strings['onboardingSummaryDemographics'] )
				? sprintf( $strings['onboardingSummaryDemographics'], $context['demographics'] )
				: sprintf( __( 'Demographics: %s', 'msh-image-optimizer' ), $context['demographics'] );
			$audience         = $audience ? $audience . ' — ' . $demographic_note : $demographic_note;
		}

		return array(
			'business_name'   => isset( $context['business_name'] ) ? $context['business_name'] : '',
			'industry'        => self::lookup_label( 'industry', isset( $context['industry'] ) ? $context['industry'] : '', $labels ),
			'business_type'   => self::lookup_label( 'business_type', isset( $context['business_type'] ) ? $context['business_type'] : '', $labels ),
			'target_audience' => $audience,
			'pain_points'     => isset( $context['pain_points'] ) ? $context['pain_points'] : '',
			'brand_voice'     => self::lookup_label( 'brand_voice', isset( $context['brand_voice'] ) ? $context['brand_voice'] : '', $labels ),
			'uvp'             => isset( $context['uvp'] ) ? $context['uvp'] : '',
			'cta_preference'  => self::lookup_label( 'cta_preference', isset( $context['cta_preference'] ) ? $context['cta_preference'] : '', $labels ),
			'location'        => self::format_location_summary( $context, $strings ),
			'ai_interest'     => ! empty( $context['ai_interest'] )
				? ( ! empty( $strings['onboardingSummaryAiYes'] ) ? $strings['onboardingSummaryAiYes'] : __( 'Subscribed to updates', 'msh-image-optimizer' ) )
				: ( ! empty( $strings['onboardingSummaryAiNo'] ) ? $strings['onboardingSummaryAiNo'] : __( 'No updates requested', 'msh-image-optimizer' ) ),
		);
	}

	/**
	 * Create a location summary string.
	 *
	 * @since 1.0.0
	 *
	 * @param array $context Context fields.
	 * @param array $strings Optional translations.
	 * @return string Summary string for location.
	 */
	public static function format_location_summary( $context, $strings = array() ) {
		$parts = array();

		if ( ! empty( $context['city'] ) ) {
			$parts[] = $context['city'];
		}

		if ( ! empty( $context['region'] ) ) {
			$parts[] = $context['region'];
		}

		if ( ! empty( $context['country'] ) ) {
			$parts[] = $context['country'];
		}

		$location = implode( ', ', array_filter( $parts ) );

		if ( ! empty( $context['service_area'] ) ) {
			$service_area = ! empty( $strings['onboardingSummaryServiceArea'] )
				? sprintf( $strings['onboardingSummaryServiceArea'], $context['service_area'] )
				: sprintf( __( 'Service area: %s', 'msh-image-optimizer' ), $context['service_area'] );

			if ( ! empty( $location ) ) {
				$location .= ' (' . $service_area . ')';
			} else {
				$location = $service_area;
			}
		}

		if ( empty( $location ) ) {
			$location = ! empty( $strings['onboardingSummaryNotSpecified'] )
				? $strings['onboardingSummaryNotSpecified']
				: __( 'Not specified', 'msh-image-optimizer' );
		}

		return $location;
	}

	/**
	 * Retrieve and sanitize stored context profiles.
	 *
	 * @return array
	 */
	public static function get_profiles() {
		$profiles = get_option( 'msh_onboarding_context_profiles', array() );
		if ( ! is_array( $profiles ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $profiles as $profile_id => $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}

			$context = isset( $profile['context'] )
				? self::sanitize_context( $profile['context'], false )
				: array();

			$sanitized_id = isset( $profile['id'] ) ? sanitize_title( $profile['id'] ) : sanitize_title( $profile_id );
			if ( empty( $sanitized_id ) ) {
				$sanitized_id = uniqid( 'context_', false );
			}

			$sanitized[ $sanitized_id ] = array(
				'id'      => $sanitized_id,
				'label'   => isset( $profile['label'] ) ? sanitize_text_field( $profile['label'] ) : '',
				'usage'   => isset( $profile['usage'] ) ? sanitize_text_field( $profile['usage'] ) : '',
				'locale'  => isset( $profile['locale'] ) ? sanitize_text_field( $profile['locale'] ) : '',
				'notes'   => isset( $profile['notes'] ) ? sanitize_textarea_field( $profile['notes'] ) : '',
				'context' => $context,
			);
		}

		return $sanitized;
	}

	/**
	 * Retrieve the primary (default) context.
	 *
	 * @return array
	 */
	public static function get_primary_context() {
		$stored             = get_option( 'msh_onboarding_context', array() );
		$existing_timestamp = isset( $stored['updated_at'] ) ? absint( $stored['updated_at'] ) : 0;

		return self::sanitize_context( $stored, false, $existing_timestamp );
	}

	/**
	 * Retrieve the active context profile record with metadata.
	 *
	 * @param null|array $profiles Optional pre-fetched profiles.
	 * @return array
	 */
	public static function get_active_profile( $profiles = null ) {
		if ( null === $profiles ) {
			$profiles = self::get_profiles();
		}

		$active_id = get_option( 'msh_active_context_profile', 'primary' );

		if ( 'primary' !== $active_id && isset( $profiles[ $active_id ] ) ) {
			$profile = $profiles[ $active_id ];

			if ( empty( $profile['label'] ) ) {
				$profile['label'] = __( 'Context profile', 'msh-image-optimizer' );
			}

			return $profile;
		}

		return array(
			'id'      => 'primary',
			'label'   => __( 'Primary Context', 'msh-image-optimizer' ),
			'usage'   => '',
			'locale'  => '',
			'notes'   => '',
			'context' => self::get_primary_context(),
		);
	}

	/**
	 * Convenience accessor for the active context payload.
	 *
	 * @param null|array $profiles Optional pre-fetched profiles.
	 * @return array
	 */
	public static function get_active_context( $profiles = null ) {
		$active = self::get_active_profile( $profiles );

		return isset( $active['context'] ) && is_array( $active['context'] )
			? $active['context']
			: array();
	}

	/**
	 * Determine if the provided industry slug maps to healthcare.
	 *
	 * @param string $industry Industry identifier.
	 * @return bool
	 */
	public static function is_healthcare_industry( $industry ) {
		if ( empty( $industry ) || ! is_string( $industry ) ) {
			return false;
		}

		$industry     = strtolower( trim( $industry ) );
		$health_slugs = array( 'medical', 'dental', 'therapy', 'wellness' );

		return in_array( $industry, $health_slugs, true );
	}

	/**
	 * Retrieve context dropdown options tailored to the active industry.
	 *
	 * Each option is returned as an array with `value` and `label` keys so the
	 * order can be preserved in both PHP and JavaScript.
	 *
	 * @param string $industry Industry slug.
	 * @return array[]
	 */
	public static function get_context_menu_options( $industry = '' ) {
		$is_healthcare = self::is_healthcare_industry( $industry );

		if ( $is_healthcare ) {
			return array(
				array(
					'value' => '',
					'label' => __( 'Auto-detect (default)', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'clinical',
					'label' => __( 'Clinical / Treatment', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'team',
					'label' => __( 'Team Member', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'testimonial',
					'label' => __( 'Patient Testimonial', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'service-icon',
					'label' => __( 'Service Icon', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'facility',
					'label' => __( 'Facility / Clinic', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'equipment',
					'label' => __( 'Equipment', 'msh-image-optimizer' ),
				),
				array(
					'value' => 'business',
					'label' => __( 'Business / General', 'msh-image-optimizer' ),
				),
			);
		}

		return array(
			array(
				'value' => '',
				'label' => __( 'Auto-detect (default)', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'business',
				'label' => __( 'Business / General', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'team',
				'label' => __( 'Team Member', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'testimonial',
				'label' => __( 'Customer Testimonial', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'service-icon',
				'label' => __( 'Icon / Graphic', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'facility',
				'label' => __( 'Workspace / Office', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'equipment',
				'label' => __( 'Product / Equipment', 'msh-image-optimizer' ),
			),
			array(
				'value' => 'clinical',
				'label' => __( 'Service Highlight', 'msh-image-optimizer' ),
			),
		);
	}

	/**
	 * Convenience helper to return an associative map of context choices.
	 *
	 * @param string $industry Industry slug.
	 * @return array<string,string>
	 */
	public static function get_context_choice_map( $industry = '' ) {
		$options = self::get_context_menu_options( $industry );
		$map     = array();

		foreach ( $options as $option ) {
			if ( ! isset( $option['value'], $option['label'] ) ) {
				continue;
			}
			$map[ $option['value'] ] = $option['label'];
		}

		return $map;
	}

	/**
	 * Build a stable signature for a context payload.
	 *
	 * @param array $context Context array.
	 * @return string
	 */
	public static function build_context_signature( $context ) {
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$normalized = self::normalize_context_for_hash( $context );
		return md5( wp_json_encode( $normalized ) );
	}

	/**
	 * Get the active context signature.
	 *
	 * @param array|null $context Optional context data.
	 * @return string
	 */
	public static function get_active_context_signature( $context = null ) {
		if ( $context === null ) {
			$context = self::get_active_context();
		}

		return self::build_context_signature( $context );
	}

	/**
	 * Normalize context for hashing.
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed
	 */
	private static function normalize_context_for_hash( $value ) {
		if ( is_array( $value ) ) {
			$normalized = array();
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = self::normalize_context_for_hash( $item );
			}
			ksort( $normalized );
			return $normalized;
		}

		if ( is_object( $value ) ) {
			$normalized = array();
			foreach ( get_object_vars( $value ) as $key => $item ) {
				$normalized[ $key ] = self::normalize_context_for_hash( $item );
			}
			ksort( $normalized );
			return $normalized;
		}

		return (string) $value;
	}
}
