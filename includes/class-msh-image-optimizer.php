<?php
/**
 * MSH Image Optimizer
 * Optimizes published images for Main Street Health healthcare website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Contextual_Meta_Generator {
	private $business_name        = '';
	private $location             = '';
	private $location_slug        = '';
	private $city                 = '';
	private $city_slug            = '';
	private $region               = '';
	private $country              = '';
	private $service_area         = '';
	private $active_context       = array();
	private $industry             = '';
	private $industry_label       = '';
	private $brand_voice          = '';
	private $cta_preference       = '';
	private $target_audience      = '';
	private $uvp                  = '';
	private $pain_points          = '';
	private $active_profile_id    = 'primary';
	private $active_profile_label = '';
	private $business_type        = '';
	private $context_signature    = '';
	private $current_industry     = '';
	private $current_season       = '';
	private $industry_value_props = array();
	private $achievement_markers  = '';
	private $season_cache         = null;
	private $batch_mode           = false;
	private $hemisphere           = null;
	private $batch_season         = null;
	private $season_cache_hits    = 0;
	private $season_cache_misses  = 0;

	// Phase 1F: Runtime cache for batch optimization (avoid repeated wp_options queries)
	private $msh_batch_mode           = false;
	private $msh_batch_context_cached = false;
	private $msh_cached_context       = array();
	private $msh_cached_signature     = '';

	private $industry_service_defaults = array(
		'legal'        => 'legal',
		'accounting'   => 'accounting',
		'consulting'   => 'consulting',
		'marketing'    => 'marketing',
		'web_design'   => 'web-design',
		'plumbing'     => 'plumbing',
		'hvac'         => 'hvac',
		'electrical'   => 'electrical',
		'renovation'   => 'renovation',
		'medical'      => 'medical',
		'dental'       => 'dental',
		'therapy'      => 'therapy',
		'wellness'     => 'wellness',
		'online_store' => 'ecommerce',
		'local_retail' => 'retail',
		'specialty'    => 'specialty',
		'other'        => 'services',
	);

	private $service_keyword_map = array(
		'physiotherapy'          => array(
			'default'    => 'Professional physiotherapy services. Comprehensive recovery programs.',
			'assessment' => 'Functional assessments. Return-to-work evaluation.',
			'acute'      => 'Immediate injury care. Same-day appointments available.',
		),
		'chiropractic'           => array(
			'default'    => 'Spinal care. Workplace injury treatment. Insurance claims supported.',
			'assessment' => 'Spinal assessment and posture evaluation services.',
			'acute'      => 'Acute back and neck pain management with direct billing.',
		),
		'massage'                => array(
			'default'    => 'Registered massage therapy. Insurance coverage available.',
			'assessment' => 'Musculoskeletal assessment and soft tissue release.',
			'acute'      => 'Pain relief for muscle strain and injury recovery.',
		),
		'acupuncture'            => array(
			'default'    => 'Evidence-based acupuncture care. Professional treatment services.',
			'assessment' => 'Assessment-driven acupuncture plans for recovery.',
			'acute'      => 'Immediate relief protocols for pain and inflammation.',
		),
		'rehabilitation'         => array(
			'default'    => 'Return-to-work programs. Professional rehabilitation. Direct billing.',
			'assessment' => 'Functional capacity assessments and workplace evaluations.',
			'acute'      => 'Comprehensive rehabilitation for acute injuries.',
		),
		'motor-vehicle-accident' => array(
			'default'    => 'Accident rehabilitation with insurance coordination and direct billing.',
			'assessment' => 'Comprehensive post-collision assessments and recovery plans.',
			'acute'      => 'Immediate collision injury support with medical-legal documentation.',
		),
		'workplace-injury'       => array(
			'default'    => 'Workplace injury rehabilitation with return-to-work planning.',
			'assessment' => 'Workplace functional assessments and ergonomic planning.',
			'acute'      => 'Rapid workplace injury care with documentation support.',
		),
		'first-responder'        => array(
			'default'    => 'Dedicated first responder rehabilitation programs with duty-ready focus.',
			'assessment' => 'Operational fitness assessments for first responders.',
			'acute'      => 'Immediate injury care with expedited recovery pathways.',
		),
		'wellness'               => array(
			'default'    => 'Holistic wellness therapies. Stress relief and rejuvenation.',
			'assessment' => 'Personalized wellness assessments and care plans.',
			'acute'      => 'Immediate relaxation therapies focused on tension release.',
		),
		'legal'                  => array(
			'default'      => 'Licensed attorneys. Confidential consultations. Case evaluation available.',
			'consultation' => 'Free case evaluation. Experienced legal counsel. Client-focused representation.',
			'litigation'   => 'Courtroom experience. Proven trial record. Aggressive legal advocacy.',
		),
		'accounting'             => array(
			'default'  => 'Certified accountants. Tax planning. Financial reporting.',
			'advisory' => 'Financial advisory. Forecasting. Growth strategies.',
		),
		'consulting'             => array(
			'default'    => 'Strategic consulting. Data-informed decisions. Measurable outcomes.',
			'operations' => 'Process optimization. Efficiency gains. Change management.',
		),
		'marketing'              => array(
			'default' => 'Full-funnel marketing. Campaign optimization. Revenue growth.',
			'digital' => 'Digital campaigns. SEO and paid media. Conversion optimization.',
		),
		'web-design'             => array(
			'default'     => 'Conversion-focused web design. UX optimization. Performance tuned.',
			'development' => 'Custom development. Responsive builds. Accessible experiences.',
		),
		'plumbing'               => array(
			'default'     => 'Emergency plumbing. Licensed technicians. Upfront pricing.',
			'maintenance' => 'Preventative maintenance. Drain cleaning. Water heater care.',
		),
		'hvac'                   => array(
			'default'     => 'Heating and cooling experts. Energy-efficient solutions. 24/7 service.',
			'maintenance' => 'Seasonal tune-ups. Indoor air quality. System inspections.',
		),
		'electrical'             => array(
			'default'    => 'Licensed electricians. Code-compliant work. Safety inspections.',
			'commercial' => 'Commercial electrical projects. Panel upgrades. Lighting design.',
		),
		'renovation'             => array(
			'default' => 'Renovation specialists. Quality craftsmanship. On-time delivery.',
			'remodel' => 'Home remodels. Space planning. Custom finishes.',
		),
		'medical'                => array(
			'default' => 'Patient-focused medical care. Comprehensive treatment plans. Insurance accepted.',
			'clinic'  => 'Primary care clinic. Preventative medicine. Compassionate providers.',
		),
		'dental'                 => array(
			'default'  => 'Comprehensive dental care. Patient comfort. Modern technology.',
			'cosmetic' => 'Cosmetic dentistry. Smile design. Whitening and veneers.',
		),
		'therapy'                => array(
			'default' => 'Licensed therapists. Confidential counseling. Personalized care.',
			'group'   => 'Group therapy. Family counseling. Supportive sessions.',
		),
		'ecommerce'              => array(
			'default' => 'Online store. Fast shipping. Secure checkout.',
			'product' => 'Quality products. Customer reviews. Easy returns.',
		),
		'retail'                 => array(
			'default'  => 'Local retail experience. Personalized service. Community focused.',
			'boutique' => 'Curated selection. Gift-ready finds. Friendly staff.',
		),
		'specialty'              => array(
			'default' => 'Specialty products. Expert curation. Premium quality.',
			'custom'  => 'Custom orders. Personalized guidance. Exclusive items.',
		),
		'services'               => array(
			'default' => 'Professional services. Client-focused. Quality outcomes.',
		),
		'general'                => array(
			'default' => 'Professional services. Trusted experts. Reliable results.',
		),
	);

	private $service_keywords = array(
		'physiotherapy'          => array( 'physio', 'physiotherapy', 'physical therapy', 'rehab' ),
		'chiropractic'           => array( 'chiro', 'chiropractic', 'spinal' ),
		'massage'                => array( 'massage', 'rmt' ),
		'acupuncture'            => array( 'acupuncture', 'acupucture', 'needling', 'needle' ),
		'rehabilitation'         => array( 'rehab', 'recovery', 'rehabilitation' ),
		'motor-vehicle-accident' => array( 'mva', 'motor vehicle', 'collision', 'auto injury', 'car accident' ),
		'workplace-injury'       => array( 'wsib', 'workplace', 'work injury', 'return to work', 'occupational' ),
		'first-responder'        => array( 'first responder', 'firefighter', 'paramedic', 'police', 'dispatcher' ),
		'wellness'               => array( 'wellness', 'spa', 'holistic', 'relaxation', 'mindfulness' ),
		'legal'                  => array( 'legal', 'law', 'attorney', 'lawyer', 'litigation', 'legal services' ),
		'accounting'             => array( 'accounting', 'bookkeeping', 'tax', 'cpa', 'financial statements' ),
		'consulting'             => array( 'consulting', 'consultant', 'strategy', 'advisory' ),
		'marketing'              => array( 'marketing', 'campaign', 'branding', 'digital marketing' ),
		'web-design'             => array( 'web design', 'website', 'ux', 'ui', 'development' ),
		'plumbing'               => array( 'plumbing', 'plumber', 'pipe', 'drain', 'leak' ),
		'hvac'                   => array( 'hvac', 'heating', 'cooling', 'air conditioning', 'furnace' ),
		'electrical'             => array( 'electrical', 'electrician', 'wiring', 'panel', 'lighting' ),
		'renovation'             => array( 'renovation', 'remodel', 'construction', 'builder', 'contractor' ),
		'medical'                => array( 'medical', 'clinic', 'physician', 'doctor', 'healthcare' ),
		'dental'                 => array( 'dental', 'dentist', 'orthodontic', 'oral health' ),
		'therapy'                => array( 'therapy', 'therapist', 'counseling', 'mental health', 'psychotherapy' ),
		'ecommerce'              => array( 'online store', 'ecommerce', 'shop', 'product' ),
		'retail'                 => array( 'retail', 'boutique', 'storefront', 'local shop' ),
		'specialty'              => array( 'specialty', 'premium', 'exclusive', 'curated', 'artisan' ),
		'services'               => array( 'service', 'professional', 'consulting', 'solutions' ),
		'general'                => array( 'service', 'professional' ),
	);

	/**
	 * Initialise the contextual meta generator and hook shutdown logging.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		$this->hydrate_active_context();
		$this->current_season = $this->detect_current_season();
		$this->load_industry_value_props();
		add_action( 'shutdown', array( $this, 'log_cache_stats' ) );
	}

	private function hydrate_active_context() {
		// Phase 1F: Return cached context if batch mode is active
		if ( $this->msh_batch_mode && $this->msh_batch_context_cached ) {
			return $this->msh_cached_context;
		}

		if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
			return;
		}

		$profiles       = MSH_Image_Optimizer_Context_Helper::get_profiles();
		$active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile( $profiles );
		$context        = isset( $active_profile['context'] ) && is_array( $active_profile['context'] )
			? $active_profile['context']
			: array();

		$this->active_profile_id    = isset( $active_profile['id'] ) ? sanitize_title( $active_profile['id'] ) : 'primary';
		$this->active_profile_label = isset( $active_profile['label'] ) ? $active_profile['label'] : '';
		$this->active_context       = $context;
		$this->context_signature    = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
			? MSH_Image_Optimizer_Context_Helper::build_context_signature( $context )
			: md5( wp_json_encode( $context ) );

		$this->business_name = ! empty( $context['business_name'] )
			? sanitize_text_field( $context['business_name'] )
			: '';

		$city         = isset( $context['city'] ) ? sanitize_text_field( $context['city'] ) : '';
		$region       = isset( $context['region'] ) ? sanitize_text_field( $context['region'] ) : '';
		$country      = isset( $context['country'] ) ? sanitize_text_field( $context['country'] ) : '';
		$service_area = isset( $context['service_area'] ) ? sanitize_text_field( $context['service_area'] ) : '';

		if ( class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
			$city         = MSH_Image_Optimizer_Context_Helper::normalize_location_value( $city );
			$region       = MSH_Image_Optimizer_Context_Helper::normalize_location_value( $region );
			$country      = MSH_Image_Optimizer_Context_Helper::normalize_location_value( $country );
			$service_area = MSH_Image_Optimizer_Context_Helper::normalize_location_value( $service_area, false );
		}

		$this->service_area = $service_area;
		$this->country      = $country;
		$this->region       = $region;

		$this->city      = $city;
		$this->city_slug = $city !== '' ? MSH_Image_Optimizer_Context_Helper::slugify( $city ) : '';

		$location_parts = array_filter( array( $city, $region, $country ), 'strlen' );
		if ( ! empty( $location_parts ) ) {
			$this->location = implode( ', ', $location_parts );
		} elseif ( $service_area !== '' ) {
			$this->location = $service_area;
		}

		if ( ! empty( $this->location ) ) {
			$this->location_slug = MSH_Image_Optimizer_Context_Helper::slugify( $this->location );
		} elseif ( $service_area !== '' ) {
			$this->location_slug = MSH_Image_Optimizer_Context_Helper::slugify( $service_area );
		}

		$this->industry        = isset( $context['industry'] ) ? sanitize_text_field( $context['industry'] ) : '';
		$this->business_type   = isset( $context['business_type'] ) ? sanitize_text_field( $context['business_type'] ) : '';
		$label_map             = MSH_Image_Optimizer_Context_Helper::get_label_map();
		$this->industry_label  = MSH_Image_Optimizer_Context_Helper::lookup_label( 'industry', $this->industry, $label_map );
		$this->brand_voice     = isset( $context['brand_voice'] ) ? sanitize_text_field( $context['brand_voice'] ) : '';
		$this->cta_preference  = isset( $context['cta_preference'] ) ? sanitize_text_field( $context['cta_preference'] ) : '';
		$this->target_audience = isset( $context['target_audience'] ) ? sanitize_text_field( $context['target_audience'] ) : '';
		$this->uvp             = isset( $context['uvp'] ) ? sanitize_textarea_field( $context['uvp'] ) : '';
		$this->pain_points     = isset( $context['pain_points'] ) ? sanitize_textarea_field( $context['pain_points'] ) : '';
	}

	private function ensure_fresh_context() {
		// Phase 1F: Skip during batch mode - context was pre-cached
		if ( $this->msh_batch_mode && $this->msh_batch_context_cached ) {
			return;
		}

		if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
			return;
		}

		$current_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();

		if ( $current_signature !== $this->context_signature ) {
			$this->hydrate_active_context();
		}
	}

	/**
	 * Retrieve the cached context signature for comparison purposes.
	 *
	 * @since 1.2.0
	 *
	 * @return string Context signature hash.
	 */
	public function get_context_signature() {
		// Phase 1F: Return cached signature if batch mode is active
		if ( $this->msh_batch_mode && ! empty( $this->msh_cached_signature ) ) {
			return $this->msh_cached_signature;
		}

		if ( empty( $this->context_signature ) && class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
			$this->context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature( $this->active_context );
		}

		return $this->context_signature;
	}

	private function get_default_context_type() {
		// Safer default – treat unknown imagery as stock until context proves otherwise.
		return 'stock';
	}

	private function get_default_service_slug( $industry ) {
		$industry = strtolower( (string) $industry );
		if ( isset( $this->industry_service_defaults[ $industry ] ) ) {
			return $this->industry_service_defaults[ $industry ];
		}

		return 'general';
	}

	private function get_industry_label_or_default() {
		if ( $this->industry_label !== '' ) {
			return $this->industry_label;
		}

		return __( 'Business Services', 'msh-image-optimizer' );
	}

	private function get_industry_descriptor() {
		$label      = $this->get_industry_label_or_default();
		$descriptor = strtolower( $label );

		if ( ! preg_match( '/(service|services|solutions|agency|studio|clinic|store|practice)$/', $descriptor ) ) {
			$descriptor .= ' services';
		}

		return $descriptor;
	}

	private function get_location_phrase( $prefix = ' in ' ) {
		if ( $this->location === '' ) {
			return '';
		}

		return $prefix . $this->location;
	}

	private function get_cta_sentence() {
		switch ( $this->cta_preference ) {
			case 'direct':
				return __( 'Contact us today to get started.', 'msh-image-optimizer' );
			case 'balanced':
				return __( 'Reach out for details or a consultation.', 'msh-image-optimizer' );
			case 'soft':
				return __( 'Discover how we can support your goals.', 'msh-image-optimizer' );
			default:
				return '';
		}
	}

	private function get_target_audience_sentence() {
		if ( $this->target_audience === '' ) {
			return '';
		}

		return sprintf( __( 'Serving %s.', 'msh-image-optimizer' ), $this->target_audience );
	}

	private function normalize_sentence( $text ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return '';
		}

		if ( ! preg_match( '/[.!?]$/', $text ) ) {
			$text .= '.';
		}

		return $text;
	}

	/**
	 * Detect current season with caching.
	 *
	 * @return string
	 */
	private function detect_current_season( $force_refresh = false ) {
		if ( $this->batch_mode && $this->batch_season !== null && ! $force_refresh ) {
			++$this->season_cache_hits;
			return $this->batch_season;
		}

		if ( $this->season_cache !== null && ! $force_refresh ) {
			++$this->season_cache_hits;
			return $this->season_cache;
		}

		$cache_key = $this->get_season_cache_key();

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				++$this->season_cache_hits;
				$this->season_cache = $cached;
				if ( $this->batch_mode ) {
					$this->batch_season = $cached;
				}
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && apply_filters( 'msh_debug_logging', true ) ) {
					error_log( sprintf( '[MSH Image Optimizer] Season cache hit (transient): %s', $cached ) );
				}
				return $cached;
			}
		}

		++$this->season_cache_misses;

		$season = $this->calculate_season();

		$season = apply_filters(
			'msh_detected_season',
			$season,
			array(
				'month'      => (int) current_time( 'n' ),
				'hemisphere' => $this->get_hemisphere(),
				'timezone'   => wp_timezone_string(),
			)
		);

		$ttl = $this->get_season_cache_ttl();
		set_transient( $cache_key, $season, $ttl );

		$this->season_cache = $season;
		if ( $this->batch_mode ) {
			$this->batch_season = $season;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && apply_filters( 'msh_debug_logging', true ) ) {
			error_log(
				sprintf(
					'[MSH Image Optimizer] Season calculated as %s (hemisphere: %s, TTL: %d)',
					$season,
					$this->get_hemisphere(),
					$ttl
				)
			);
		}

		return $season;
	}

	private function calculate_season() {
		$month      = (int) current_time( 'n' );
		$hemisphere = $this->get_hemisphere();

		if ( $hemisphere === 'southern' ) {
			if ( $month >= 3 && $month <= 5 ) {
				return 'fall';
			}
			if ( $month >= 6 && $month <= 8 ) {
				return 'winter';
			}
			if ( $month >= 9 && $month <= 11 ) {
				return 'spring';
			}
			return 'summer';
		}

		if ( $month >= 3 && $month <= 5 ) {
			return 'spring';
		}
		if ( $month >= 6 && $month <= 8 ) {
			return 'summer';
		}
		if ( $month >= 9 && $month <= 11 ) {
			return 'fall';
		}
		return 'winter';
	}

	private function get_hemisphere() {
		if ( $this->hemisphere !== null ) {
			return $this->hemisphere;
		}

		$timezone_string = wp_timezone_string();
		if ( $timezone_string === '' ) {
			$timezone_string = 'UTC';
		}

		$southern_patterns = array(
			'Australia/',
			'Pacific/Auckland',
			'Pacific/Fiji',
			'Africa/Johannesburg',
			'Africa/Cape_Town',
			'America/Santiago',
			'America/Buenos_Aires',
			'America/Sao_Paulo',
			'Atlantic/Stanley',
		);

		$detected = 'northern';
		foreach ( $southern_patterns as $pattern ) {
			if ( stripos( $timezone_string, $pattern ) === 0 ) {
				$detected = 'southern';
				break;
			}
		}

		$this->hemisphere = apply_filters( 'msh_detected_hemisphere', $detected, $timezone_string );

		return $this->hemisphere;
	}

	private function get_season_cache_key() {
		$key = 'msh_current_season';

		if ( function_exists( 'get_locale' ) ) {
			$locale = get_locale();
			if ( ! empty( $locale ) ) {
				$key .= '_' . sanitize_key( strtolower( (string) $locale ) );
			}
		}

		if ( is_multisite() ) {
			$key .= '_' . get_current_blog_id();
		}

		return apply_filters( 'msh_season_cache_key', $key, $this->active_profile_id );
	}

	private function get_season_cache_ttl() {
		$month             = (int) current_time( 'n' );
		$day               = (int) current_time( 'j' );
		$days_until_change = $this->days_until_season_change( $month, $day );

		$ttl = max( HOUR_IN_SECONDS, min( $days_until_change * DAY_IN_SECONDS, 90 * DAY_IN_SECONDS ) );

		return (int) apply_filters( 'msh_season_cache_ttl', $ttl, $month, $day, $this->get_hemisphere() );
	}

	private function days_until_season_change( $month, $day ) {
		$season_changes = array(
			3  => 21,
			6  => 21,
			9  => 23,
			12 => 21,
		);

		$timezone = wp_timezone();

		try {
			$now = new DateTime( 'now', $timezone );
		} catch ( Exception $e ) {
			$now = new DateTime( 'now' );
		}

		foreach ( $season_changes as $change_month => $change_day ) {
			try {
				$target = new DateTime( sprintf( '%d-%02d-%02d', (int) $now->format( 'Y' ), $change_month, $change_day ), $timezone );
			} catch ( Exception $e ) {
				$target = new DateTime( sprintf( '%d-%02d-%02d', (int) $now->format( 'Y' ), $change_month, $change_day ) );
			}

			if ( $now <= $target ) {
				$diff = $now->diff( $target );
				return max( 1, (int) $diff->format( '%a' ) );
			}
		}

		$next_year = (int) $now->format( 'Y' ) + 1;
		try {
			$next_target = new DateTime( sprintf( '%d-03-21', $next_year ), $timezone );
		} catch ( Exception $e ) {
			$next_target = new DateTime( sprintf( '%d-03-21', $next_year ) );
		}

		$diff = $now->diff( $next_target );
		return max( 1, (int) $diff->format( '%a' ) );
	}

	/**
	 * Enable batch processing mode and snapshot the current season.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function enable_batch_mode() {
		if ( $this->batch_mode ) {
			return;
		}

		$this->batch_season = $this->get_current_season();
		$this->batch_mode   = true;
	}

	/**
	 * Disable batch processing mode and clear the cached season.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function disable_batch_mode() {
		$this->batch_mode   = false;
		$this->batch_season = null;
	}

	/**
	 * Log cache hit statistics when debugging is enabled.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function log_cache_stats() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( ! apply_filters( 'msh_debug_logging', true ) ) {
			return;
		}

		if ( $this->season_cache_hits === 0 && $this->season_cache_misses === 0 ) {
			return;
		}

		error_log(
			sprintf(
				'[MSH Image Optimizer] Season cache stats — hits: %d, misses: %d',
				$this->season_cache_hits,
				$this->season_cache_misses
			)
		);
	}

	private function load_industry_value_props() {
		$this->industry_value_props = array(
			'legal'        => __( 'Free consultation with flexible payment plans.', 'msh-image-optimizer' ),
			'accounting'   => __( 'Maximum refunds with audit protection included.', 'msh-image-optimizer' ),
			'consulting'   => __( 'ROI-focused strategies with measurable outcomes.', 'msh-image-optimizer' ),
			'marketing'    => __( 'Data-driven campaigns with transparent reporting.', 'msh-image-optimizer' ),
			'web_design'   => __( 'Mobile-first websites with fast load times.', 'msh-image-optimizer' ),
			'plumbing'     => __( 'Same-day service with upfront pricing and warranty.', 'msh-image-optimizer' ),
			'hvac'         => __( 'Energy-efficient solutions with financing available.', 'msh-image-optimizer' ),
			'electrical'   => __( 'Code-compliant work backed by safety guarantees.', 'msh-image-optimizer' ),
			'renovation'   => __( 'On-time, on-budget project delivery.', 'msh-image-optimizer' ),
			'medical'      => __( 'Same-week appointments and most insurance accepted.', 'msh-image-optimizer' ),
			'dental'       => __( 'Gentle care with sedation options and modern tech.', 'msh-image-optimizer' ),
			'therapy'      => __( 'Evidence-based treatment in a confidential setting.', 'msh-image-optimizer' ),
			'wellness'     => __( 'Holistic programs with personalized treatment plans.', 'msh-image-optimizer' ),
			'online_store' => __( 'Fast shipping with hassle-free returns.', 'msh-image-optimizer' ),
			'local_retail' => __( 'Personalized service from local experts.', 'msh-image-optimizer' ),
			'specialty'    => __( 'Curated selection guided by industry experts.', 'msh-image-optimizer' ),
			'other'        => __( 'Professional service with satisfaction guaranteed.', 'msh-image-optimizer' ),
		);
	}

	/**
	 * Resolve the current season, optionally bypassing cached values.
	 *
	 * @since 1.2.0
	 *
	 * @param bool $force_refresh Optional. Whether to bypass the cache. Default false.
	 * @return string Season slug (winter, spring, summer, fall).
	 */
	public function get_current_season( $force_refresh = false ) {
		return $this->detect_current_season( $force_refresh );
	}

	/**
	 * Clear the cached season and recalculate the current value.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True on success.
	 */
	public function clear_season_cache() {
		delete_transient( $this->get_season_cache_key() );
		$this->season_cache   = null;
		$this->current_season = $this->detect_current_season( true );
		$this->batch_season   = $this->season_cache;
		return true;
	}

	/**
	 * Persist a manual season override for downstream content generation.
	 *
	 * @since 1.2.0
	 *
	 * @param string $season Season slug to store.
	 * @param int    $ttl    Optional. Cache duration in seconds. Default DAY_IN_SECONDS.
	 * @return bool True when the season was stored, false for invalid input.
	 */
	public function set_season( $season, $ttl = DAY_IN_SECONDS ) {
		$season        = strtolower( (string) $season );
		$valid_seasons = array( 'winter', 'spring', 'summer', 'fall' );
		if ( ! in_array( $season, $valid_seasons, true ) ) {
			return false;
		}
		set_transient( $this->get_season_cache_key(), $season, (int) $ttl );
		$this->season_cache   = $season;
		$this->current_season = $season;
		$this->batch_season   = $season;
		return true;
	}

	private function get_temporal_keywords( $industry, $season = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && apply_filters( 'msh_debug_logging', true ) ) {
			error_log( sprintf( '[MSH Image Optimizer] get_temporal_keywords() invoked for %s (season: %s)', $industry ?: 'unknown', $season ?: 'auto' ) );
		}
		$season          = $season ?? $this->get_current_season();
		$industry_season = $this->get_industry_season( $industry, $season );

		$temporal_map = array(
			'plumbing'     => array(
				'winter' => __( 'frozen pipe repair emergency thawing service', 'msh-image-optimizer' ),
				'spring' => __( 'sump pump maintenance flood prevention', 'msh-image-optimizer' ),
				'summer' => __( 'outdoor plumbing irrigation sprinkler repair', 'msh-image-optimizer' ),
				'fall'   => __( 'winterization pipe insulation cold weather prep', 'msh-image-optimizer' ),
			),
			'hvac'         => array(
				'summer' => __( 'emergency ac repair cooling system service', 'msh-image-optimizer' ),
				'winter' => __( 'furnace repair heating system emergency', 'msh-image-optimizer' ),
				'spring' => __( 'hvac maintenance tune up indoor air quality', 'msh-image-optimizer' ),
				'fall'   => __( 'heating inspection furnace preparation winter', 'msh-image-optimizer' ),
			),
			'wellness'     => array(
				'spring'  => __( 'detox programs seasonal allergies support', 'msh-image-optimizer' ),
				'summer'  => __( 'hydration therapy cooling spa treatments', 'msh-image-optimizer' ),
				'fall'    => __( 'immunity boost relaxation stress relief', 'msh-image-optimizer' ),
				'winter'  => __( 'warm stone massage winter wellness support', 'msh-image-optimizer' ),
				'default' => __( 'holistic wellness self care relaxation spa', 'msh-image-optimizer' ),
			),
			'accounting'   => array(
				'q1_tax_season' => __( 'tax preparation filing season refunds', 'msh-image-optimizer' ),
				'q2'            => __( 'estimated taxes quarterly bookkeeping', 'msh-image-optimizer' ),
				'q3'            => __( 'financial planning mid year review', 'msh-image-optimizer' ),
				'q4_year_end'   => __( 'year end tax strategy deductions planning', 'msh-image-optimizer' ),
			),
			'online_store' => array(
				'black_friday'     => __( 'black friday cyber week deals limited time offers', 'msh-image-optimizer' ),
				'holiday_shopping' => __( 'holiday gifts fast shipping seasonal bundles', 'msh-image-optimizer' ),
				'back_to_school'   => __( 'back to school essentials supplies', 'msh-image-optimizer' ),
				'default'          => __( 'exclusive online deals fast shipping secure checkout', 'msh-image-optimizer' ),
			),
			'local_retail' => array(
				'black_friday'     => __( 'doorcrasher deals limited inventory shop local', 'msh-image-optimizer' ),
				'holiday_shopping' => __( 'holiday gifts curated local shop small', 'msh-image-optimizer' ),
				'back_to_school'   => __( 'back to school must haves local experts', 'msh-image-optimizer' ),
				'default'          => __( 'shop local boutique selection personalized service', 'msh-image-optimizer' ),
			),
		);

		$keywords = $temporal_map[ $industry ][ $industry_season ] ??
			$temporal_map[ $industry ][ $season ] ??
			$temporal_map[ $industry ]['default'] ?? '';

		return apply_filters( 'msh_temporal_keywords', $keywords, $industry, $season );
	}

	private function get_industry_season( $industry, $calendar_season ) {
		$industry        = strtolower( (string) $industry );
		$calendar_season = strtolower( (string) $calendar_season );

		$season = $calendar_season !== '' ? $calendar_season : 'default';

		if ( $industry === 'accounting' ) {
			$month = (int) current_time( 'n' );
			if ( $month <= 3 ) {
				$season = 'q1_tax_season';
			} elseif ( $month <= 6 ) {
				$season = 'q2';
			} elseif ( $month <= 9 ) {
				$season = 'q3';
			} else {
				$season = 'q4_year_end';
			}
		} elseif ( in_array( $industry, array( 'online_store', 'local_retail' ), true ) ) {
			$month = (int) current_time( 'n' );
			$day   = (int) current_time( 'j' );

			if ( $month === 11 && $day >= 20 ) {
				$season = 'black_friday';
			} elseif ( $month === 12 ) {
				$season = 'holiday_shopping';
			} elseif ( $month === 8 ) {
				$season = 'back_to_school';
			} else {
				$season = 'default';
			}
		}

		return apply_filters( 'msh_industry_season', $season, $industry, $calendar_season );
	}

	private function get_trust_signals( $industry, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[MSH Image Optimizer] get_trust_signals() invoked for %s', $industry ?: 'unknown' ) );
		}
		$map = array(
			'plumbing' => array(
				__( 'Licensed & insured plumbers.', 'msh-image-optimizer' ),
				__( '24/7 emergency response within 60 minutes.', 'msh-image-optimizer' ),
				__( 'BBB A+ rated service.', 'msh-image-optimizer' ),
			),
			'hvac'     => array(
				__( 'Factory-certified HVAC technicians.', 'msh-image-optimizer' ),
				__( 'Energy Star partner with financing options.', 'msh-image-optimizer' ),
				__( 'NATE-certified service team.', 'msh-image-optimizer' ),
			),
			'wellness' => array(
				__( 'Certified wellness practitioners.', 'msh-image-optimizer' ),
				__( 'Personalized treatment plans.', 'msh-image-optimizer' ),
				__( 'Thousands of satisfied members.', 'msh-image-optimizer' ),
			),
			'medical'  => array(
				__( 'Board-certified physicians.', 'msh-image-optimizer' ),
				__( 'Accepting new patients.', 'msh-image-optimizer' ),
				__( 'Most insurance plans accepted.', 'msh-image-optimizer' ),
			),
			'dental'   => array(
				__( 'Gentle, comfort-focused dental care.', 'msh-image-optimizer' ),
				__( 'Sedation options available.', 'msh-image-optimizer' ),
				__( 'Insurance welcome; payment plans offered.', 'msh-image-optimizer' ),
			),
		);

		$signals = $map[ $industry ] ?? array(
			__( 'Experienced professionals.', 'msh-image-optimizer' ),
			__( 'Locally trusted service.', 'msh-image-optimizer' ),
		);

		$limit    = isset( $context['limit'] ) ? max( 1, (int) $context['limit'] ) : count( $signals );
		$selected = array_slice( $signals, 0, $limit );

		return apply_filters( 'msh_trust_signals', $selected, $industry, $context );
	}

	private function get_journey_content( $industry, $stage, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[MSH Image Optimizer] get_journey_content() invoked for %s (stage: %s)', $industry ?: 'unknown', $stage ) );
		}
		$templates = array(
			'awareness'     => array(
				'wellness' => __( 'Discover holistic wellness solutions tailored to you.', 'msh-image-optimizer' ),
				'plumbing' => __( 'Learn to spot early plumbing issues before they escalate.', 'msh-image-optimizer' ),
				'hvac'     => __( 'Is your HVAC system ready for the season?', 'msh-image-optimizer' ),
			),
			'consideration' => array(
				'wellness' => __( 'Compare wellness programs designed for lasting balance.', 'msh-image-optimizer' ),
				'plumbing' => __( 'See why homeowners trust our licensed plumbing team.', 'msh-image-optimizer' ),
				'hvac'     => __( 'Compare HVAC maintenance options that protect your comfort.', 'msh-image-optimizer' ),
			),
			'decision'      => array(
				'wellness' => __( 'Book your personalized wellness consultation today.', 'msh-image-optimizer' ),
				'plumbing' => __( 'Schedule same-day plumbing service with upfront pricing.', 'msh-image-optimizer' ),
				'hvac'     => __( 'Reserve your HVAC tune-up and stay comfortable year-round.', 'msh-image-optimizer' ),
			),
			'retention'     => array(
				'wellness' => __( 'Stay consistent with monthly wellness check-ins.', 'msh-image-optimizer' ),
				'plumbing' => __( 'Join our maintenance program and prevent future issues.', 'msh-image-optimizer' ),
				'hvac'     => __( 'Renew your maintenance plan for priority scheduling.', 'msh-image-optimizer' ),
			),
		);

		$content = $templates[ $stage ][ $industry ] ?? '';

		if ( $content === '' && isset( $templates[ $stage ]['default'] ) ) {
			$content = $templates[ $stage ]['default'];
		}

		if ( $content === '' ) {
			$content = __( 'Trusted professionals delivering reliable service.', 'msh-image-optimizer' );
		}

		if ( ! empty( $context ) ) {
			foreach ( $context as $key => $value ) {
				$content = str_replace( '{' . $key . '}', $value, $content );
			}
		}

		return apply_filters( 'msh_journey_content', $content, $industry, $stage, $context );
	}

	private function get_achievement_markers( $industry = null ) {
		$industry = $industry ?: $this->current_industry;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[MSH Image Optimizer] get_achievement_markers() invoked for %s', $industry ?: 'unknown' ) );
		}

		if ( $industry !== '' ) {
			$custom = get_option( 'msh_achievement_markers_' . $industry, '' );
			if ( $custom !== '' ) {
				$this->achievement_markers = $custom;
				return apply_filters( 'msh_achievement_markers', $custom, $industry, $this->business_name );
			}
		}

		$defaults = array(
			'plumbing'   => __( '10,000+ satisfied customers across the GTA.', 'msh-image-optimizer' ),
			'hvac'       => __( 'Serving local homes and businesses for over 20 years.', 'msh-image-optimizer' ),
			'wellness'   => __( 'Rated 4.9/5 by wellness members since 2015.', 'msh-image-optimizer' ),
			'medical'    => __( 'Recognised for outstanding patient care in the community.', 'msh-image-optimizer' ),
			'dental'     => __( 'Award-winning dental team with state-of-the-art technology.', 'msh-image-optimizer' ),
			'marketing'  => __( 'Delivering measurable growth for 200+ brands.', 'msh-image-optimizer' ),
			'web_design' => __( 'Designed and launched 500+ conversion-focused websites.', 'msh-image-optimizer' ),
		);

		$marker                    = $defaults[ $industry ] ?? '';
		$this->achievement_markers = $marker;

		return apply_filters( 'msh_achievement_markers', $marker, $industry, $this->business_name );
	}

	private function get_industry_value_proposition( $industry = null ) {
		$industry = $industry ?: $this->current_industry;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[MSH Image Optimizer] get_industry_value_proposition() invoked for %s', $industry ?: 'unknown' ) );
		}

		$value = $this->industry_value_props[ $industry ] ?? '';

		return apply_filters( 'msh_industry_value_prop', $value, $industry, $this->business_name );
	}


	private function build_industry_description( $generic_text, $credentials = '', array $options = array() ) {
		$generic     = trim( (string) $generic_text );
		$credentials = trim( (string) $credentials );
		$industry    = $options['industry'] ?? $this->current_industry;

		$trust_signals = $this->get_trust_signals( $industry, array( 'limit' => 2 ) );

		if ( $this->uvp !== '' ) {
			$segments = array( $this->normalize_sentence( $this->uvp ) );
			if ( $credentials !== '' ) {
				$segments[] = $this->normalize_sentence( $credentials );
			}
			if ( ! empty( $trust_signals ) ) {
				$segments[] = $this->normalize_sentence( $trust_signals[0] );
			}
			return trim( implode( ' ', $segments ) );
		}

		if ( $this->pain_points !== '' ) {
			$base     = $generic !== '' ? $generic : __( 'Professional services', 'msh-image-optimizer' );
			$segments = array(
				$this->normalize_sentence(
					sprintf(
						__( '%1$s specializing in %2$s', 'msh-image-optimizer' ),
						$base,
						$this->pain_points
					)
				),
			);
			if ( $credentials !== '' ) {
				$segments[] = $this->normalize_sentence( $credentials );
			}
			return trim( implode( ' ', $segments ) );
		}

		$achievement = $this->get_achievement_markers( $industry );
		if ( $achievement !== '' ) {
			$segments = array();
			if ( $generic !== '' ) {
				$segments[] = $this->normalize_sentence( $generic );
			}
			$segments[] = $this->normalize_sentence( $achievement );
			if ( $credentials !== '' ) {
				$segments[] = $this->normalize_sentence( $credentials );
			}
			return trim( implode( ' ', $segments ) );
		}

		if ( $this->target_audience !== '' ) {
			$base     = $generic !== '' ? $generic : __( 'Professional services', 'msh-image-optimizer' );
			$segments = array(
				$this->normalize_sentence(
					sprintf(
						__( '%1$s serving %2$s', 'msh-image-optimizer' ),
						$base,
						$this->target_audience
					)
				),
			);
			if ( $credentials !== '' ) {
				$segments[] = $this->normalize_sentence( $credentials );
			}
			return trim( implode( ' ', $segments ) );
		}

		$industry_value = $this->get_industry_value_proposition( $industry );
		if ( $industry_value !== '' ) {
			$segments = array();
			if ( $generic !== '' ) {
				$segments[] = $this->normalize_sentence( $generic );
			}
			$segments[] = $this->normalize_sentence( $industry_value );
			if ( $credentials !== '' ) {
				$segments[] = $this->normalize_sentence( $credentials );
			}
			return trim( implode( ' ', $segments ) );
		}

		$segments = array();
		if ( $generic !== '' ) {
			$segments[] = $this->normalize_sentence( $generic );
		}
		if ( $credentials !== '' ) {
			$segments[] = $this->normalize_sentence( $credentials );
		}
		if ( ! empty( $trust_signals ) ) {
			$segments[] = $this->normalize_sentence( $trust_signals[0] );
		}

		return trim( implode( ' ', array_filter( $segments ) ) );
	}

	private function extract_visual_descriptor( array $context ) {
		$candidates = array(
			$context['attachment_title'] ?? '',
			$context['page_title'] ?? '',
			$context['attachment_caption'] ?? '',
		);

		if ( ! empty( $context['attachment_slug'] ) ) {
			$candidates[] = str_replace( '-', ' ', (string) $context['attachment_slug'] );
		}

		if ( ! empty( $context['tags'] ) && is_array( $context['tags'] ) ) {
			$candidates = array_merge( $candidates, $context['tags'] );
		}

		foreach ( $candidates as $candidate ) {
			$descriptor = $this->sanitize_descriptor( $candidate );
			if ( $descriptor !== '' && ! $this->is_generic_descriptor( $descriptor ) ) {
				return $descriptor;
			}
		}

		return '';
	}

	private function sanitize_descriptor( $text ) {
		$text = trim( wp_strip_all_tags( (string) $text ) );
		if ( $text === '' ) {
			return '';
		}

		if ( $this->looks_like_camera_filename( $text ) ) {
			return '';
		}

		$text = preg_replace( '/^picsum\s*id[:#\-\s]*\d+\s*[–—\-:|]*\s*/i', '', $text );
		$text = preg_replace( '/^placeholder[-\s_:]*/i', '', $text );
		$text = preg_replace( '/^stock\s+(image|photo)/i', '', $text );
		$text = preg_replace( '/^sample[-\s_:]*/i', '', $text );
		$text = preg_replace( '/\b(image|photo|picture|graphic)\b/i', '', $text );
		$text = preg_replace( '/[-_]?\d+x\d+[-_]?/i', '', $text );
		$text = preg_replace( '/\balignment\b/i', '', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text, " \t\n\r\0\x0B-_|'\":" );

		if ( $text === '' ) {
			return '';
		}

		if ( $this->is_generic_descriptor( $text ) ) {
			return '';
		}

		if ( mb_strlen( $text ) > 60 ) {
			$text = rtrim( mb_substr( $text, 0, 57 ) ) . '...';
		}

		return $text;
	}

	private function is_generic_descriptor( $text ) {
		$value = strtolower( trim( (string) $text ) );
		if ( $value === '' ) {
			return true;
		}

		$normalized = preg_replace( '/[\s_\-]+/', ' ', $value );
		$normalized = trim( $normalized, ": '\"" );

		$generic_terms = array(
			'home',
			'services',
			'service',
			'about',
			'contact',
			'blog',
			'sample',
			'default',
			'resinous',
			'canola',
			'canola2',
			'manhattansummer',
			'olympus digital camera',
			'image alignment',
			'alignment',
			'classic',
			'post format: gallery',
			'post format gallery',
			'post format',
			'featured image',
			'classic gallery',
			'markup',
			'markup:',
			'picsum',
			'picsum id',
			'placeholder',
			'placeholder graphic',
			'stock photo',
			'stock image',
			'default image',
			'lorem ipsum',
		);

		if ( in_array( $value, $generic_terms, true ) || in_array( $normalized, $generic_terms, true ) ) {
			return true;
		}

		if ( preg_match( '/\b\d+x\d+\b/', $normalized ) ) {
			return true;
		}

		if ( preg_match( '/^(dsc|img|pict|picture|photo|p\d{7}|dscn|dscf|imgp|dcim)[\s\-_]?\d+/i', $normalized ) ) {
			return true;
		}

		// Catch meaningless test descriptors with numbers
		if ( preg_match( '/^(canola|resinous|manhattansummer)\d*$/i', $normalized ) ) {
			return true;
		}

		if ( preg_match( '/^(image[-_ ]alignment|alignment[-_ ]image)/', $normalized ) ) {
			return true;
		}

		if ( $this->looks_like_camera_filename( $normalized ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine the context profile for a given attachment.
	 *
	 * Resolves manual overrides, inferred context, and supporting metadata that
	 * drive downstream AI generation decisions.
	 *
	 * @since 1.2.0
	 *
	 * @param int  $attachment_id Attachment identifier.
	 * @param bool $ignore_manual Optional. Whether to ignore manual overrides. Default false.
	 * @return array Resolved context data.
	 */
	public function detect_context( $attachment_id, $ignore_manual = false ) {
		$this->ensure_fresh_context();
		$this->hydrate_active_context();

		$seo_mode_raw          = get_post_meta( $attachment_id, '_msh_seo_mode', true );
		$seo_mode_flag         = $seo_mode_raw === '0' ? false : true; // Default to true if not set
		$has_explicit_seo_meta = $seo_mode_raw !== '' && $seo_mode_raw !== null;

		if ( ! $has_explicit_seo_meta ) {
			$seo_mode_flag = true; // Default to SEO mode ON
		}

		$context = array(
			'type'                 => $this->get_default_context_type(),
			'page_type'            => null,
			'page_title'           => null,
			'default_service_slug' => $this->get_default_service_slug( $this->industry ),
			'service'              => $this->get_default_service_slug( $this->industry ),
			'parent_id'            => 0,
			'tags'                 => array(),
			'manual'               => false,
			'attachment_id'        => (int) $attachment_id,
			'attachment_title'     => '',
			'attachment_slug'      => '',
			'file_basename'        => '',
			'subject_name'         => '',
			'active_profile_id'    => $this->active_profile_id,
			'active_profile_label' => $this->active_profile_label,
			'industry'             => $this->industry,
			'industry_label'       => $this->industry_label,
			'brand_voice'          => $this->brand_voice,
			'cta_preference'       => $this->cta_preference,
			'target_audience'      => $this->target_audience,
			'uvp'                  => $this->uvp,
			'pain_points'          => $this->pain_points,
			'seo_mode'             => $seo_mode_flag,
			'post_format'          => '',
			'downgraded_reasons'   => array(),
			'business_name'        => $this->business_name,
			'city'                 => $this->city,
			'country'              => $this->country,
			'location'             => $this->location,
			'service_area'         => $this->service_area,
		);
		$context['initial_context_type'] = $context['type'];

		$manual                  = get_post_meta( $attachment_id, '_msh_context', true );
		$manual                  = is_string( $manual ) ? trim( $manual ) : '';
		$context['manual_value'] = $manual;

		if ( ! $ignore_manual && $manual !== '' ) {
			$context['type']   = sanitize_text_field( $manual );
			$context['manual'] = true;
		}

		$attachment           = get_post( $attachment_id );
		$parent_id            = $attachment ? (int) $attachment->post_parent : 0;
		$context['parent_id'] = $parent_id;
		if ( $attachment ) {
			$context['attachment_title'] = $attachment->post_title;
		}

		$attachment_title             = $attachment ? strtolower( (string) $attachment->post_title ) : '';
		$file_meta                    = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$file_name                    = $file_meta ? basename( $file_meta ) : '';
		$file_basename                = $file_name ? strtolower( pathinfo( $file_name, PATHINFO_FILENAME ) ) : '';
		$context['file_basename']     = $file_basename;
		$context['attachment_slug']   = MSH_Image_Optimizer_Context_Helper::slugify( ! empty( $context['attachment_title'] ) ? $context['attachment_title'] : $file_basename );
		$context['filename']          = $file_name;
		$context['original_filename'] = $file_name; // Add for keyword extraction

		$meta_sizes = wp_get_attachment_metadata( $attachment_id );
		$width      = 0;
		$height     = 0;
		if ( is_array( $meta_sizes ) ) {
			$width  = isset( $meta_sizes['width'] ) ? (int) $meta_sizes['width'] : 0;
			$height = isset( $meta_sizes['height'] ) ? (int) $meta_sizes['height'] : 0;
		}

		$should_detect_icon = ! $context['manual'] || $context['type'] === 'service-icon';
		if ( $should_detect_icon ) {
			$icon_context = $this->detect_icon_context( $attachment_id, $context, $width, $height );
			if ( $icon_context ) {
				if ( $context['manual'] ) {
					unset( $icon_context['type'] );
					$icon_context['source'] = 'manual';
					if ( ! isset( $icon_context['asset'] ) ) {
						$icon_context['asset'] = 'icon';
					}
					$context = array_merge( $context, $icon_context );
				} elseif ( in_array( $context['type'], array( 'clinical', 'business' ), true ) ) {
					$context = array_merge( $context, $icon_context );
				}
			}
		}

		$should_detect_product = ! $context['manual'] || in_array( $context['type'], array( 'equipment', 'business' ), true );
		if ( $should_detect_product ) {
			$product_context = $this->detect_product_context( $attachment_id, $context );
			if ( $product_context ) {
				if ( $context['manual'] ) {
					unset( $product_context['type'] );
					if ( $context['type'] === 'equipment' && empty( $product_context['service'] ) ) {
						$product_context['service'] = $context['service'] ?? 'rehabilitation';
					}
					$product_context['source'] = 'manual';
					$context                   = array_merge( $context, $product_context );
				} elseif ( in_array( $context['type'], array( 'clinical', 'business' ), true ) ) {
					$context = array_merge( $context, $product_context );
				}
			}
		}

		if ( $attachment ) {
			$this->apply_attachment_context( $context, $attachment, $attachment_title, $file_basename );
		}

		if ( $parent_id > 0 ) {
			$parent_post = get_post( $parent_id );
			if ( $parent_post ) {
				$context['page_type']  = get_post_type( $parent_post );
				$context['page_title'] = $parent_post->post_title;
				$post_format           = get_post_format( $parent_post );
				$context['post_format'] = $post_format ? $post_format : '';
				$this->apply_parent_context( $context, $parent_post, $attachment_id, $file_basename );

				// Micro-upgrade: Extract lightweight page context (caption, H1, first paragraph)
				$page_context = $this->extract_lightweight_page_context( $attachment_id, $parent_post );
				if ( ! empty( $page_context['caption'] ) ) {
					$context['image_caption'] = $page_context['caption'];
				}
				if ( ! empty( $page_context['heading'] ) ) {
					$context['page_heading'] = $page_context['heading'];
				}
				if ( ! empty( $page_context['excerpt'] ) ) {
					$context['page_excerpt'] = $page_context['excerpt'];
				}
			}
		}

		// Featured usage (e.g., attached as featured image on other posts)
		$featured_usage = $this->find_featured_usage( $attachment_id );
		if ( ! empty( $featured_usage ) ) {
			$first = $featured_usage[0];
			if ( empty( $context['page_title'] ) ) {
				$context['page_title'] = $first['post_title'];
				$context['page_type']  = $first['post_type'];
			}
			$this->apply_usage_context( $context, $featured_usage, $file_basename );

			// Micro-upgrade: For featured images, extract H1/first paragraph from first usage
			if ( empty( $context['page_heading'] ) && ! empty( $first['ID'] ) ) {
				$featured_post = get_post( $first['ID'] );
				if ( $featured_post ) {
					$page_context = $this->extract_lightweight_page_context( $attachment_id, $featured_post );
					if ( ! empty( $page_context['heading'] ) ) {
						$context['page_heading'] = $page_context['heading'];
					}
					if ( ! empty( $page_context['excerpt'] ) ) {
						$context['page_excerpt'] = $page_context['excerpt'];
					}
				}
			}
		}

		// Media categories / taxonomies
		// Only override type if NOT manually set
		$media_terms = wp_get_object_terms( $attachment_id, array( 'media_category' ), array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $media_terms ) && ! empty( $media_terms ) ) {
			$context['tags'] = array_merge( $context['tags'], $media_terms );
			if ( ! $context['manual'] ) {
				if ( in_array( 'team', $media_terms, true ) ) {
					$context['type'] = 'team';
				} elseif ( in_array( 'testimonials', $media_terms, true ) ) {
					$context['type'] = 'testimonial';
				} elseif ( in_array( 'facility', $media_terms, true ) ) {
					$context['type'] = 'facility';
				} elseif ( in_array( 'equipment', $media_terms, true ) ) {
					$context['type'] = 'equipment';
				} elseif ( in_array( 'products', $media_terms, true ) || in_array( 'product', $media_terms, true ) ) {
					$context['type']  = 'business';
					$context['asset'] = 'product';
				}
			}
		}

		$combined_indicator = strtolower( trim( ( $context['attachment_title'] ?? '' ) . ' ' . $file_basename ) );
		if (
			! $context['manual']
			&& in_array( $context['type'], array( 'clinical', 'business' ), true )
			&& strpos( $combined_indicator, 'icon' ) !== false
		) {
			$context['type']    = 'service-icon';
			$context['service'] = $this->extract_service_type( $context['page_title'], $context['tags'], array( $combined_indicator ) );
		}

		// Service extraction for clinical images
		if ( $context['type'] === 'clinical' ) {
			$extra_sources      = array_filter( array( $attachment_title, $file_basename ) );
			$context['service'] = $this->extract_service_type( $context['page_title'], $context['tags'], $extra_sources );
		}

		if ( ! $context['manual'] ) {
			$asset_type = $this->detect_asset_type( strtolower( trim( ( $context['attachment_title'] ?? '' ) . ' ' . $file_basename . ' ' . ( $context['page_title'] ?? '' ) ) ) );

			// IMPORTANT: Don't override icon context that was already set by detect_icon_context
			if ( $context['type'] === 'service-icon' ) {
				// Debug disabled for performance
				// Don't apply any asset type overrides - keep as service-icon
			} elseif ( $asset_type === 'logo' ) {
				$context['type']  = 'business';
				$context['asset'] = 'logo';
			} elseif ( $asset_type === 'icon' ) {
				$context['type'] = 'service-icon';
			} elseif ( $asset_type === 'frame' ) {
				$context['type']  = 'business';
				$context['asset'] = 'graphic';
			} elseif ( $asset_type === 'product' ) {
				if ( MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ) {
					$context['type'] = 'equipment';
				} else {
					$context['type'] = 'business';
				}
				$context['asset']        = 'product';
				$context['product_type'] = $this->extract_product_type( $file_basename, $context['attachment_title'] );
			} elseif ( $asset_type === 'graphic' ) {
				$context['type']  = 'business';
				$context['asset'] = 'graphic';
			}
		}

		if ( $context['type'] === 'testimonial' && empty( $context['subject_name'] ) ) {
			$context['subject_name'] = $this->extract_subject_name( $context['attachment_title'] ?: str_replace( array( '-', '_' ), ' ', $file_basename ) );
		}

		$context['source']               = $context['manual'] ? 'manual' : 'auto';
		$context['context_set_manually'] = (bool) $context['manual'];
		$context['brand_name_visible_manual'] = $this->get_manual_brand_visibility( $attachment_id );
		$context['ocr_found_brand']           = class_exists( 'MSH_OCR_Bridge' ) ? MSH_OCR_Bridge::detect_brand_presence( $attachment_id, $context ) : false;
		$context['ai_search_friendly']        = true;

		if ( class_exists( 'MSH_Context_Resolver' ) ) {
			$context = MSH_Context_Resolver::finalize( $context );
		}

		$context['service_keywords'] = $this->resolve_service_keywords_for_prompt( $context );

		if ( isset( $context['context_trace'] ) ) {
			update_post_meta( $attachment_id, '_msh_context_trace', wp_json_encode( $context['context_trace'] ) );
		}

		if ( ! $context['manual'] ) {
			update_post_meta( $attachment_id, '_msh_auto_context', $context['type'] );
		}

		return $context;
	}

	private function apply_parent_context( array &$context, WP_Post $parent_post, $attachment_id, $file_basename = '' ) {
		if ( ! empty( $context['manual'] ) ) {
			return;
		}

		if ( ! isset( $context['category_is_clinical'] ) ) {
			$context['category_is_clinical'] = false;
		}
		if ( ! isset( $context['template_is_clinical'] ) ) {
			$context['template_is_clinical'] = false;
		}
		if ( ! isset( $context['is_service_page'] ) ) {
			$context['is_service_page'] = false;
		}
		if ( ! isset( $context['looks_like_gallery_hint'] ) ) {
			$context['looks_like_gallery_hint'] = false;
		}

		$title     = $parent_post->post_title;
		$post_type = get_post_type( $parent_post );

		if ( in_array( $post_type, array( 'team', 'staff', 'msh_team_member' ), true ) ) {
			$context['type']       = 'team';
			$context['staff_name'] = $title;
			return;
		}

		$categories = wp_get_post_categories( $parent_post->ID, array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$context['tags'] = array_merge( $context['tags'], $categories );

			if ( array_intersect( $categories, array( 'clinical', 'clinic', 'treatment', 'therapy', 'rehabilitation', 'rehab', 'services', 'service' ) ) ) {
				$context['category_is_clinical'] = true;
				$context['is_service_page']      = true;
			}
			if ( array_intersect( $categories, array( 'gallery', 'portfolio', 'case-study', 'case-studies', 'before-and-after' ) ) ) {
				$context['looks_like_gallery_hint'] = true;
			}

			if ( array_intersect( $categories, array( 'team', 'staff' ) ) ) {
				$context['type']       = 'team';
				$context['staff_name'] = $title;
				return;
			}
			if ( array_intersect( $categories, array( 'testimonials', 'reviews', 'success-stories' ) ) ) {
				$context['type'] = 'testimonial';
				if ( empty( $context['subject_name'] ) ) {
					$context['subject_name'] = $this->extract_subject_name( $title ?: $file_basename );
				}
				return;
			}
			if ( array_intersect( $categories, array( 'facility', 'clinic', 'office' ) ) ) {
				$context['type'] = 'facility';
			}
			if ( array_intersect( $categories, array( 'equipment' ) ) ) {
				$context['type'] = 'equipment';
			}
		}

		$template = get_page_template_slug( $parent_post->ID );
		if ( $template ) {
			if ( strpos( $template, 'team' ) !== false ) {
				$context['type']       = 'team';
				$context['staff_name'] = $title;
			} elseif ( strpos( $template, 'service' ) !== false || strpos( $template, 'treatment' ) !== false || strpos( $template, 'clinical' ) !== false ) {
				$context['template_is_clinical'] = true;
			} elseif ( strpos( $template, 'testimonial' ) !== false ) {
				$context['type'] = 'testimonial';
				if ( empty( $context['subject_name'] ) ) {
					$context['subject_name'] = $this->extract_subject_name( $title ?: $file_basename );
				}
			} elseif ( strpos( $template, 'facility' ) !== false ) {
				$context['type'] = 'facility';
			} elseif ( strpos( $template, 'gallery' ) !== false || strpos( $template, 'portfolio' ) !== false ) {
				$context['looks_like_gallery_hint'] = true;
			}
		}

		if ( $context['type'] === 'clinical' ) {
			$extra_sources      = array_filter( array( $title, $file_basename ) );
			$context['service'] = $this->extract_service_type( $title, $context['tags'], $extra_sources );
		}

		// Gallery detection for reference
		if ( ! empty( $parent_post->post_content ) && has_shortcode( $parent_post->post_content, 'gallery' ) ) {
			if ( strpos( $parent_post->post_content, (string) $attachment_id ) !== false ) {
				$context['in_gallery']   = true;
				$context['gallery_page'] = $title;
			}
		}

		// Final safeties
		if ( $this->looks_like_gallery( $context ) ) {
			if ( ! in_array( 'gallery', $context['downgraded_reasons'], true ) ) {
				$context['downgraded_reasons'][] = 'gallery';
			}
			$context['type'] = 'stock';
		}

		if ( $context['type'] === 'clinical' && ! $this->has_strong_clinical_signals( $context ) ) {
			if ( ! in_array( 'no_strong_signals', $context['downgraded_reasons'], true ) ) {
				$context['downgraded_reasons'][] = 'no_strong_signals';
			}
			$context['type'] = 'stock';
		}
	}

	/**
	 * Detect if post uses a page builder.
	 * Returns true if common builder patterns or meta flags are detected.
	 *
	 * @since 1.2.0
	 * @param WP_Post $post Post object to check.
	 * @return bool True if builder content detected.
	 */
	private function is_builder_content( $post ) {
		if ( ! $post || ! isset( $post->post_content ) ) {
			return false;
		}

		$content = $post->post_content;

		// Check for common builder shortcodes and data attributes
		$builder_patterns = array(
			'/\[elementor-template/',
			'/\[vc_row/',
			'/\[et_pb_/',
			'/\[fusion_builder_/',
			'/\[bricks/',
			'/data-elementor-type=/',
		);

		foreach ( $builder_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}

		// Check for builder meta flags
		$builder_meta_keys = array(
			'_elementor_edit_mode',
			'_wpb_vc_js_status',
			'_et_pb_use_builder',
			'_fusion',
			'_bricks_page_content_2',
		);

		foreach ( $builder_meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $post->ID, $meta_key, true );
			if ( $meta_value === 'builder' || $meta_value === 'yes' || $meta_value === 'active' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract lightweight page context (caption, H1, first paragraph) for metadata enhancement.
	 * Micro-upgrade for launch: low-risk, avoids full content parsing.
	 *
	 * @since 1.2.0
	 * @param int     $attachment_id Attachment ID.
	 * @param WP_Post $parent_post   Parent post object.
	 * @return array Array with 'caption', 'heading', 'excerpt' keys.
	 */
	private function extract_lightweight_page_context( $attachment_id, $parent_post ) {
		$context = array(
			'caption' => '',
			'heading' => '',
			'excerpt' => '',
		);

		// 1. Try to get image caption from WordPress
		$attachment = get_post( $attachment_id );
		if ( $attachment && ! empty( $attachment->post_excerpt ) ) {
			$context['caption'] = wp_strip_all_tags( $attachment->post_excerpt );
		}

		// 2. For featured images, extract H1 and first paragraph from parent post
		// CRITICAL: Skip if page builder detected - do not parse builder shortcodes
		if ( $parent_post && ! empty( $parent_post->post_content ) ) {
			// Detect builder content - skip parsing if builder detected
			if ( $this->is_builder_content( $parent_post ) ) {
				error_log( '[MSH] Skipping builder content for post ' . $parent_post->ID );
				return $context;
			}

			$content = $parent_post->post_content;

			// Extract H1 (works for both Gutenberg and Classic)
			if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_match ) ) {
				$context['heading'] = wp_strip_all_tags( $h1_match[1] );
			} elseif ( preg_match( '/<!-- wp:heading \{"level":1\} -->.*?<h1[^>]*>(.*?)<\/h1>/is', $content, $gb_h1_match ) ) {
				$context['heading'] = wp_strip_all_tags( $gb_h1_match[1] );
			}

			// Extract first paragraph (limit to 200 chars)
			if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $p_match ) ) {
				$paragraph = wp_strip_all_tags( $p_match[1] );
				$context['excerpt'] = mb_substr( $paragraph, 0, 200 );
			} elseif ( preg_match( '/<!-- wp:paragraph -->.*?<p>(.*?)<\/p>/is', $content, $gb_p_match ) ) {
				$paragraph = wp_strip_all_tags( $gb_p_match[1] );
				$context['excerpt'] = mb_substr( $paragraph, 0, 200 );
			}
		}

		return $context;
	}

	private function extract_service_type( $title, array $tags = array(), array $extra_sources = array() ) {
		$sources = array();
		if ( ! empty( $title ) ) {
			$sources[] = strtolower( (string) $title );
		}
		foreach ( $tags as $tag ) {
			$sources[] = strtolower( (string) $tag );
		}
		foreach ( $extra_sources as $extra ) {
			if ( ! empty( $extra ) ) {
				$sources[] = strtolower( (string) $extra );
			}
		}

		foreach ( $sources as $text ) {
			foreach ( $this->service_keywords as $service => $keywords ) {
				foreach ( $keywords as $keyword ) {
					if ( $keyword !== '' && strpos( $text, $keyword ) !== false ) {
						return $service;
					}
				}
			}
		}

		return $this->get_default_service_slug( $this->industry );
	}

	private function resolve_service_keywords_for_prompt( array $context ) {
		$slug_candidates = array_filter(
			array(
				isset( $context['service'] ) ? sanitize_key( (string) $context['service'] ) : '',
				isset( $context['default_service_slug'] ) ? sanitize_key( (string) $context['default_service_slug'] ) : '',
				isset( $context['industry'] ) ? sanitize_key( (string) $context['industry'] ) : '',
				isset( $context['type'] ) ? sanitize_key( (string) $context['type'] ) : '',
			)
		);

		$keywords = array();

		foreach ( $slug_candidates as $slug ) {
			if ( $slug !== '' && isset( $this->service_keywords[ $slug ] ) ) {
				$keywords = array_merge( $keywords, (array) $this->service_keywords[ $slug ] );
			}
		}

		// Fallback to global industry/service defaults.
		if ( empty( $keywords ) ) {
			$industry_slug = sanitize_key( (string) $this->industry );
			if ( $industry_slug !== '' && isset( $this->service_keywords[ $industry_slug ] ) ) {
				$keywords = array_merge( $keywords, (array) $this->service_keywords[ $industry_slug ] );
			}
		}

		if ( empty( $keywords ) && isset( $this->service_keywords['general'] ) ) {
			$keywords = array_merge( $keywords, (array) $this->service_keywords['general'] );
		}

		$keywords = array_slice(
			array_values(
				array_unique(
					array_filter(
						array_map(
							function ( $value ) {
								return sanitize_text_field( (string) $value );
							},
							$keywords
						)
					)
				)
			),
			0,
			5
		);

		return $keywords;
	}

	private function get_manual_brand_visibility( $attachment_id ) {
		$raw_values = array(
			get_post_meta( $attachment_id, '_msh_brand_visible_manual', true ),
			get_post_meta( $attachment_id, '_msh_brand_name_visible', true ),
		);

		foreach ( $raw_values as $raw ) {
			if ( $raw === '' || $raw === null ) {
				continue;
			}

			$normalized = strtolower( trim( (string) $raw ) );
			if ( in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	private function has_strong_clinical_signals( array $context ) {
		$signals = array(
			! empty( $context['category_is_clinical'] ),
			! empty( $context['template_is_clinical'] ),
			! empty( $context['is_service_page'] ),
		);

		$service          = isset( $context['service'] ) ? strtolower( (string) $context['service'] ) : '';
		$default_service  = isset( $context['default_service_slug'] ) ? strtolower( (string) $context['default_service_slug'] ) : '';
		$non_generic_slug = $service !== '' && ! in_array( $service, array( 'general', 'services', 'service' ), true );

		if ( $non_generic_slug && $service !== $default_service ) {
			$signals[] = true;
		}

		if ( ! empty( $context['tags'] ) ) {
			$clinical_tags = array( 'clinical', 'clinic', 'treatment', 'therapy', 'rehabilitation', 'rehab', 'physiotherapy', 'chiropractic' );
			if ( array_intersect( array_map( 'strtolower', (array) $context['tags'] ), $clinical_tags ) ) {
				$signals[] = true;
			}
		}

		return in_array( true, $signals, true );
	}

	private function looks_like_gallery( array $context ) {
		if ( ! empty( $context['looks_like_gallery_hint'] ) ) {
			return true;
		}

		if ( ! empty( $context['in_gallery'] ) ) {
			return true;
		}

		$post_format = strtolower( (string) ( $context['post_format'] ?? '' ) );
		if ( $post_format === 'gallery' ) {
			return true;
		}

		$gallery_keywords = array( 'gallery', 'portfolio', 'lookbook', 'case study', 'case-study', 'before and after', 'before-after' );
		$haystacks        = array(
			strtolower( (string) ( $context['page_title'] ?? '' ) ),
			strtolower( (string) ( $context['attachment_title'] ?? '' ) ),
			implode( ' ', array_map( 'strtolower', (array) ( $context['tags'] ?? array() ) ) ),
		);

		foreach ( $gallery_keywords as $keyword ) {
			foreach ( $haystacks as $haystack ) {
				if ( $haystack !== '' && strpos( $haystack, $keyword ) !== false ) {
					return true;
				}
			}
		}

		return false;
	}

	private function find_featured_usage( $attachment_id ) {
		global $wpdb;
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT posts.ID, posts.post_title, posts.post_type \n                 FROM {$wpdb->postmeta} meta \n                 INNER JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id \n                 WHERE meta.meta_key = '_thumbnail_id' AND meta.meta_value = %d AND posts.post_status = 'publish'",
				$attachment_id
			),
			ARRAY_A
		);

		return $posts ?: array();
	}

	private function apply_usage_context( array &$context, array $usage, $file_basename = '' ) {
		if ( ! empty( $context['manual'] ) ) {
			return;
		}

		foreach ( $usage as $item ) {
			$post_type = $item['post_type'];
			$title     = $item['post_title'];

			if ( empty( $context['page_title'] ) ) {
				$context['page_title'] = $title;
				$context['page_type']  = $post_type;
			}

			if ( in_array( $post_type, array( 'team', 'staff', 'msh_team_member' ), true ) ) {
				$context['type']       = 'team';
				$context['staff_name'] = $title;
				return;
			}

			if ( stripos( $title, 'testimonial' ) !== false || stripos( $title, 'review' ) !== false ) {
				$context['type'] = 'testimonial';
				if ( empty( $context['subject_name'] ) ) {
					$context['subject_name'] = $this->extract_subject_name( $title ?: $file_basename );
				}
				return;
			}

			if ( $context['type'] === 'clinical' ) {
				$service = $this->extract_service_type( $title, array(), array_filter( array( $file_basename ) ) );
				if ( ! empty( $service ) ) {
					$context['service'] = $service;
				}
			}
		}
	}

	private function apply_attachment_context( array &$context, WP_Post $attachment, $title_lower, $file_basename ) {
		if ( ! empty( $context['manual'] ) ) {
			return;
		}

		// Strip dimension patterns from both title and basename to avoid false detection
		$sanitized_title    = preg_replace( '/[-_]?\d+x\d+[-_]?/i', '', $title_lower );
		$sanitized_basename = ! empty( $context['attachment_slug'] ) ? $context['attachment_slug'] : $file_basename;
		$combined           = trim( $sanitized_title . ' ' . $sanitized_basename );

		if ( $context['type'] !== 'team' && $this->text_contains_any( $combined, array( 'team', 'staff', 'doctor', 'dr-', 'physiotherapist', 'therapist', 'rmt', 'chiropractor' ) ) ) {
			$context['type'] = 'team';

			// Clean staff name: strip dimensions, check if generic
			$clean_name = preg_replace( '/[-_]?\d+x\d+[-_]?/i', '', $attachment->post_title );
			$clean_name = trim( $clean_name );

			// If name is still generic/numeric after cleaning, don't use it
			if ( $this->is_generic_descriptor( $clean_name ) || preg_match( '/^(team|staff)[-_]?\d*$/i', $clean_name ) ) {
				$clean_name = '';
			}

			$context['staff_name'] = $clean_name;
			return;
		}

		if ( $context['type'] !== 'testimonial' && $this->text_contains_any( $combined, array( 'testimonial', 'review', 'patient-story', 'patient_story', 'success', 'case-study', 'before-after' ) ) ) {
			$context['type'] = 'testimonial';
			if ( empty( $context['subject_name'] ) ) {
				$context['subject_name'] = $this->extract_subject_name( $attachment->post_title ?: $file_basename );
			}
			return;
		}

		if ( $context['type'] !== 'facility' && $this->text_contains_any( $combined, array( 'facility', 'clinic', 'office', 'reception', 'lobby', 'exterior', 'interior', 'waiting-room', 'front-desk' ) ) ) {
			$context['type'] = 'facility';
			return;
		}

		if ( $context['type'] !== 'equipment' && $this->text_contains_any( $combined, array( 'equipment', 'machine', 'device', 'laser', 'ultrasound', 'tens', 'table', 'traction' ) ) ) {
			$context['type'] = 'equipment';
		}
	}

	private function text_contains_any( $text, array $needles ) {
		if ( $text === '' ) {
			return false;
		}

		foreach ( $needles as $needle ) {
			if ( $needle !== '' && strpos( $text, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function extract_subject_name( $text ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return '';
		}

		$text = preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $text );
		$text = str_replace( array( '-', '_' ), ' ', $text );
		$text = preg_replace( '/\b(review|testimonial|success|story|patient|case\s*study)\b/i', '', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( $text === '' ) {
			return MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ? 'Patient' : 'Client';
		}

		$lower              = strtolower( $text );
		$has_generic_number = preg_match( '/\d{2,}/', $lower ) || preg_match( '/\d+x\d+/', $lower );
		if ( $has_generic_number || $this->is_generic_descriptor( $lower ) || strlen( trim( $lower ) ) <= 2 || preg_match( '/^(team|staff)([\s_-]*\d+|$)/', $lower ) ) {
			return '';
		}

		$normalized = ucwords( strtolower( $text ) );
		if ( empty( $normalized ) ) {
			return MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ? 'Patient' : 'Client';
		}

		if ( ! preg_match( '/^[A-Z][a-z]+(?:\s[A-Z][a-z]+){1,2}$/', $normalized ) ) {
			return '';
		}

		return $normalized;
	}

	private function truncate_slug( $slug, $max_words = 4 ) {
		if ( empty( $slug ) ) {
			return '';
		}

		// FIRST: Deduplicate words (e.g., "hamilton-hamilton-icon" -> "hamilton-icon")
		$slug = MSH_Image_Optimizer_Context_Helper::deduplicate_slug( $slug );

		$parts     = preg_split( '/-+/', strtolower( $slug ) );
		$stopwords = array( 'and', 'with', 'the', 'for', 'a', 'an', 'to', 'of', 'in', 'at', 'on', 'by', 'from', 'about' );

		$filtered = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' || in_array( $part, $stopwords, true ) || is_numeric( $part ) ) {
				continue;
			}
			$filtered[] = $part;
			if ( count( $filtered ) >= max( 1, (int) $max_words ) ) {
				break;
			}
		}

		if ( empty( $filtered ) ) {
			$filtered = array_slice( array_filter( $parts ), 0, 1 );
		}

		return implode( '-', $filtered );
	}

	/**
	 * Turn a service slug into a human readable label.
	 *
	 * @since 1.2.0
	 *
	 * @param string $service Service slug.
	 * @return string Formatted label.
	 */
	public function format_service_label( $service ) {
		$this->ensure_fresh_context();
		if ( empty( $service ) ) {
			$service = $this->get_default_service_slug( $this->industry );
		}

		if ( empty( $service ) ) {
			return __( 'Services', 'msh-image-optimizer' );
		}

		$label = str_replace( array( '-', '_' ), ' ', strtolower( $service ) );
		$label = preg_replace( '/\s+/', ' ', $label );

		$formatted = ucwords( trim( $label ) );

		return $formatted !== '' ? $formatted : __( 'Services', 'msh-image-optimizer' );
	}

	private function merge_slug_fragments( $base, $extra ) {
		if ( empty( $extra ) ) {
			return $base;
		}

		$base_parts  = array_filter( explode( '-', strtolower( $base ) ) );
		$extra_parts = array_filter( explode( '-', strtolower( $extra ) ) );

		$combined = $base_parts;
		foreach ( $extra_parts as $part ) {
			if ( ! in_array( $part, $combined, true ) ) {
				$combined[] = $part;
			}
		}

		return implode( '-', $combined );
	}

	private function normalize_icon_concept( $concept_source ) {
		$concept_source = strtolower( trim( (string) $concept_source ) );
		$concept_source = preg_replace( '/\.[a-z0-9]+$/', '', $concept_source );
		$concept_source = str_replace( array( '_', ' ' ), '-', $concept_source );
		$concept_source = preg_replace( '/-+/', '-', $concept_source );
		$concept_source = trim( $concept_source, '-' );

		$slug = preg_replace( '/-?icon$/', '', $concept_source );

		if ( $slug === '' ) {
			$slug = 'service';
		}

		$label = $this->format_service_label( $slug );

		return array( $slug, $label );
	}


	private function detect_product_context( $attachment_id, array $context ) {
		$file_meta     = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$filename      = strtolower( $file_meta ? basename( $file_meta ) : '' );
		$title         = strtolower( $context['attachment_title'] ?? '' );
		$caption       = strtolower( (string) get_post_field( 'post_excerpt', $attachment_id ) );
		$combined      = $filename . ' ' . $title . ' ' . $caption;
		$is_healthcare = MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry );

		// Bail out if filename clearly references agricultural or non-medical equipment.
		if ( preg_match( '/farm|tractor|agricultur|harvest|plow|plough|combine|bal(er|ing)|hay|barn|silo|crop|field/', $combined ) ) {
			return null;
		}

		$patterns = array(
			'/mediflow|waterbase|pillow/'               => array( 'product_type' => 'therapeutic-pillow' ),
			'/biofreeze|gel|cream/'                     => array( 'product_type' => 'pain-relief' ),
			'/tens|electrotherapy|stimulator/'          => array( 'product_type' => 'tens-unit' ),
			'/frame-?\d+|custom.?sole|orthotic|insole/' => array( 'product_type' => 'custom-orthotics' ),
			'/compression|stocking|sock/'               => array( 'product_type' => 'compression-therapy' ),
			'/(ankle|wrist|knee|elbow|shoulder|back|neck|foot|hand).*(brace|support|wrap|sleeve)/' => array( 'product_type' => 'support-brace' ),
			'/brace|support|wrap|sleeve|splint|stabilizer/' => array( 'product_type' => 'support-product' ),
		);

		// Broader business-friendly patterns (marketing/product collateral)
		$business_patterns = array(
			'/product|catalog|brochure|sell-sheet|sell sheet|flyer|one-pager|one pager|lookbook|portfolio/' => 'product-collateral',
			'/packaging|label|bottle|jar|box|mockup|prototype/' => 'product-packaging',
			'/device|hardware|equipment|kit|bundle|collection/' => 'product-showcase',
		);

		foreach ( $patterns as $pattern => $data ) {
			if ( preg_match( $pattern, $combined ) ) {
				if ( $is_healthcare ) {
					return array(
						'type'         => 'equipment',
						'service'      => 'rehabilitation',
						'product_type' => $data['product_type'],
						'asset'        => 'product',
						'source'       => 'auto',
					);
				}

				return array(
					'type'         => 'business',
					'asset'        => 'product',
					'product_type' => $data['product_type'],
					'source'       => 'auto',
				);
			}
		}

		if ( ! $is_healthcare ) {
			foreach ( $business_patterns as $pattern => $category ) {
				if ( preg_match( $pattern, $combined ) ) {
					return array(
						'type'             => 'business',
						'asset'            => 'product',
						'product_category' => $category,
						'source'           => 'auto',
					);
				}
			}
		}

		return null;
	}

	private function detect_icon_context( $attachment_id, array $context, $width = 0, $height = 0 ) {
		$file_meta = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$filename  = strtolower( $file_meta ? basename( $file_meta ) : '' );
		$directory = strtolower( $file_meta ? dirname( $file_meta ) : '' );
		$title     = strtolower( $context['attachment_title'] ?? '' );
		$caption   = strtolower( (string) get_post_field( 'post_excerpt', $attachment_id ) );
		$combined  = $filename . ' ' . $title . ' ' . $caption . ' ' . $directory;

		// Primary icon detection - be more aggressive with SVGs
		$icon_keyword = preg_match( '/icon|\.svg$|\/icons\//', $combined );

		// ENHANCED: Auto-detect all SVG files as potential icons (with size check)
		if ( strpos( $filename, '.svg' ) !== false ) {
			// Most SVGs under 300x300 are likely icons
			if ( ( $width > 0 && $height > 0 && $width <= 300 && $height <= 300 ) || ( $width == 0 && $height == 0 ) ) {
				$icon_keyword = 1;
				$this->log_debug( "MSH Icon Debug: Auto-detected SVG as icon: '$filename' (size: {$width}x{$height})" );
			}
		}
		$concept_keyword = preg_match( '/(chronic[-_ ]?pain|sport[-_ ]?injur|work[-_ ]?related[-_ ]?injur|workplace[-_ ]?injur|motor[-_ ]?icon|vehicle[-_ ]?icon|accident|wsib|program)/', $combined );

		// Force healthcare equipment SVGs to be treated as icons
		$healthcare_equipment_pattern = '/(crutches|orthopedic|pillow|compression|stocking|brace|walker|wheelchair|cane|tens|ultrasound|foam|roller|brain|nervous|system|scoliosis|injury|nerve|stimulator|spine|back|neck|joint)/';

		// Also force Noun Project SVGs to be treated as icons (with optional suffixes)
		$noun_project_pattern = '/^noun-(.+)-\d{4,7}-[A-F0-9]{6}(?:-\d+)*\.svg$/i';

		if ( strpos( $filename, '.svg' ) !== false &&
			( preg_match( $healthcare_equipment_pattern, $combined ) || preg_match( $noun_project_pattern, $filename ) ) ) {
			$icon_keyword = 1; // Force icon detection
			$this->log_debug(
				"MSH Icon Debug: Forced SVG to icon: '$filename' (Healthcare: " .
				( preg_match( $healthcare_equipment_pattern, $combined ) ? 'YES' : 'NO' ) .
				', Noun Project: ' . ( preg_match( $noun_project_pattern, $filename ) ? 'YES' : 'NO' ) . ')'
			);
		}

		// Debug SVG icon detection
		if ( strpos( $filename, '.svg' ) !== false ) {
			// Debug disabled for performance

		}

		if ( ! $icon_keyword && $concept_keyword ) {
			$max_icon_dimension = 600;

			if ( $width > 0 && $height > 0 && ( $width > $max_icon_dimension || $height > $max_icon_dimension ) ) {
				return null;
			}
		}

		if ( ! $icon_keyword && ! $concept_keyword ) {
			return null;
		}

		$category = 'service';
		if ( preg_match( '/chronic|pain|injur|condition|mobility|wellness|posture|sport/', $combined ) ) {
			$category = 'condition';
		} elseif ( preg_match( '/wsib|work([-_ ]?related)|workplace|mva|vehicle|program|rehab-plan/', $combined ) ) {
			$category = 'program';
		} elseif ( preg_match( '/team|staff|doctor|therapist/', $combined ) ) {
			$category = 'team';
		}

		// First try to extract clean concept from Noun Project filenames
		$concept_source = $context['attachment_slug'] ?? pathinfo( $filename, PATHINFO_FILENAME );

		// Check if this is a Noun Project file and extract the clean term
		if ( preg_match( '/^noun-(.+)-\d{4,7}-[A-F0-9]{6}/i', $filename, $matches ) ) {
			$concept_source = $matches[1]; // Extract just the concept part (e.g., "foot-bandage")
			$this->log_debug( "MSH Icon Debug: Extracted Noun Project concept: '$concept_source' from '$filename'" );
		}

		// If still no obvious concept, try inferring from service keywords
		if ( ! $icon_keyword ) {
			foreach ( $this->service_keywords as $service => $keywords ) {
				if ( $this->text_contains_any( $combined, $keywords ) ) {
					$concept_source = $service . '-icon';
					$category       = 'service';
					break;
				}
			}
		}

		list($concept_slug, $concept_label) = $this->normalize_icon_concept( $concept_source );

		return array(
			'type'               => 'service-icon',
			'icon_type'          => $category,
			'icon_concept'       => $concept_slug,
			'icon_concept_label' => $concept_label,
			'asset'              => 'icon',
			'source'             => 'auto',
		);
	}

	private function detect_asset_type( $text ) {
		if ( empty( $text ) ) {
			return false;
		}

		$patterns = array(
			'logo'      => '/\b(logo|brandmark|wordmark|seal|badge)\b/i',
			'icon'      => '/\b(icon|symbol|glyph|badge|\.svg)\b/i',  // Enhanced: Include .svg extension
			'frame'     => '/\b(frame|border|template|layout|mockup)\b/i',
			'product'   => '/\b(pillow|brace|support|sleeve|wrap|tens|biofreeze|orthotic|stocking|gel|cream)\b/i',
			'equipment' => '/\b(machine|table|apparatus|equipment|device|tool)\b/i',
			'graphic'   => '/\b(graphic|illustration|diagram|infographic|chart)\b/i',
		);

		foreach ( $patterns as $type => $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return $type;
			}
		}

		return false;
	}

	private function extract_product_type( $basename, $title ) {
		$combined = strtolower( $basename . ' ' . $title );
		$keywords = array( 'pillow', 'brace', 'support', 'sleeve', 'wrap', 'gel', 'cream', 'tape', 'orthotic', 'stocking' );

		foreach ( $keywords as $keyword ) {
			if ( strpos( $combined, $keyword ) !== false ) {
				return $keyword;
			}
		}

		return 'support-product';
	}

	private function ensure_unique_title( $title, $attachment_id ) {
		if ( ! $attachment_id ) {
			return $title;
		}

		global $wpdb;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND ID != %d AND post_type = 'attachment' LIMIT 1",
				$title,
				$attachment_id
			)
		);

		if ( ! $existing ) {
			return $title;
		}

		$variants = array( ' Session', ' Treatment', ' Case Study', ' Program', ' Assessment' );
		$index    = $attachment_id % count( $variants );

		return $title . $variants[ $index ];
	}

	/**
	 * Build metadata for an attachment based on contextual signals.
	 *
	 * Attempts AI generation first, falling back to heuristic builders for each
	 * supported context type.
	 *
	 * @since 1.2.0
	 *
	 * @param int   $attachment_id Attachment identifier.
	 * @param array $context       Context payload describing the asset.
	 * @param array $ai_options    Optional. Settings to pass to AI generation.
	 * @return array|null Associative array of meta fields or null when skipped.
	 */
	public function generate_meta_fields( $attachment_id, array $context, $ai_options = array() ) {
		$this->ensure_fresh_context();
		$this->hydrate_active_context();
		$this->log_debug( "MSH Meta Generation: Type='{$context['type']}', attachment_id=$attachment_id, title='{$context['attachment_title']}'" );

		if ( ! MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) && $context['type'] === 'clinical' ) {
			$context['type'] = 'business';
		}

		// Phase 1F: Check AI mode before calling AI service to avoid unnecessary wp_options queries
		// AI mode is passed via $ai_options array from the calling function
		$ai_mode = isset( $ai_options['ai_mode'] ) ? $ai_options['ai_mode'] : get_option( 'msh_ai_mode', 'manual' );

		// DEBUG: Log AI mode check
		error_log( sprintf(
			'[MSH DEBUG] generate_meta_fields - attachment_id: %d, ai_mode: %s, ai_options: %s',
			$attachment_id,
			$ai_mode,
			json_encode( $ai_options )
		) );

		$ai_meta = null;
		if ( $ai_mode !== 'manual' ) {
			error_log( sprintf( '[ANALYZE] AI_CALL - Requesting AI metadata for attachment #%d (mode: %s)', $attachment_id, $ai_mode ) );
			$ai_meta = MSH_AI_Service::get_instance()->maybe_generate_metadata( $attachment_id, $context, $this, $ai_options );

			if ( is_wp_error( $ai_meta ) ) {
				error_log( sprintf( '[ANALYZE] AI_ERROR - Attachment #%d failed: %s', $attachment_id, $ai_meta->get_error_message() ) );
				$ai_meta = null; // Reset to null so heuristics can take over
			} elseif ( is_array( $ai_meta ) && ! empty( $ai_meta ) ) {
				$filename_preview = ! empty( $ai_meta['filename_slug'] ) ? $ai_meta['filename_slug'] : 'NO FILENAME';
				error_log( sprintf( '[ANALYZE] AI_RESP - Attachment #%d received metadata (filename: %s)', $attachment_id, $filename_preview ) );
			} else {
				error_log( sprintf( '[ANALYZE] AI_SKIP - Attachment #%d returned empty/null response', $attachment_id ) );
			}
		} else {
			error_log( sprintf( '[ANALYZE] AI_SKIP - Attachment #%d skipped (manual mode)', $attachment_id ) );
		}

		if ( is_array( $ai_meta ) && ! empty( $ai_meta ) ) {
			$this->log_debug( 'MSH Meta Generation: AI metadata returned, skipping heuristic generator.' );

			// Store AI filename slug in context for filename generation
			if ( ! empty( $ai_meta['filename_slug'] ) ) {
				update_post_meta( $attachment_id, '_msh_ai_filename_slug', $ai_meta['filename_slug'] );
				$this->log_debug( 'MSH Meta Generation: Stored AI filename slug: ' . $ai_meta['filename_slug'] );
			}

			return $ai_meta;
		}

		// Industry lock: Prevent industry-incompatible scene types
		// Healthcare industries should not use non-healthcare scene types
		if ( MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $context['industry'] ) ) {
			$healthcare_scenes = array( 'team', 'testimonial', 'facility', 'equipment', 'clinical', 'service-icon', 'icon', 'business' );
			if ( ! in_array( $context['type'], $healthcare_scenes, true ) ) {
				error_log( '[MSH] Industry lock: Resetting incompatible scene type "' . $context['type'] . '" to default for healthcare industry' );
				$context['type'] = $this->get_default_context_type();
			}
		}

		// DEBUG: Log context type to diagnose why all images get clinical metadata
		error_log( sprintf(
			'[MSH TEMPLATE DEBUG] attachment_id=%d, context_type=%s, seo_mode=%s, context_set_manually=%s',
			$attachment_id,
			$context['type'] ?? 'NULL',
			! empty( $context['seo_mode'] ) ? 'true' : 'false',
			! empty( $context['context_set_manually'] ) ? 'true' : 'false'
		) );

		switch ( $context['type'] ) {
			case 'team':
				return $this->generate_context_via_composer( $context );
			case 'testimonial':
				return $this->generate_context_via_composer( $context );
			case 'brand_logo':
				return $this->generate_logo_meta( $context );
			case 'icon':
				// Legacy fallback - redirect to service-icon
				$normalized = $context;
				$normalized['type'] = 'service-icon';
				return $this->generate_context_via_composer( $normalized );
			case 'service-icon':
				return $this->generate_context_via_composer( $context );
			case 'facility':
				return $this->generate_context_via_composer( $context );
			case 'equipment':
				if ( ! empty( $context['asset'] ) && $context['asset'] === 'product' ) {
					return $this->generate_product_meta( $context );
				}
				return $this->generate_context_via_composer( $context );
			case 'stock':
			case 'business':
				if ( ! empty( $context['asset'] ) ) {
					if ( $context['asset'] === 'logo' ) {
						return $this->generate_logo_meta( $context );
					}
					if ( $context['asset'] === 'product' ) {
						return $this->generate_product_meta( $context );
					}
				}
				return $this->generate_context_via_composer( $context );
			case 'clinical':
			default:
				return $this->generate_context_via_composer( $context );
		}
	}

	/**
	 * Generate a sanitized filename slug for an attachment.
	 *
	 * @since 1.2.0
	 *
	 * @param int         $attachment_id Attachment identifier.
	 * @param array       $context       Context payload describing the asset.
	 * @param string|null $extension     Optional. File extension for legacy paths.
	 * @return string Slug without extension.
	 */
	public function generate_filename_slug( $attachment_id, array $context, $extension = null ) {
		$this->ensure_fresh_context();
		$this->hydrate_active_context();
		// Debug disabled for performance

		// PRIORITY 1: Check if AI generated a filename slug (from post meta or context)
		$ai_slug_raw = ! empty( $context['ai_filename_slug'] )
			? $context['ai_filename_slug']
			: get_post_meta( $attachment_id, '_msh_ai_filename_slug', true );

		if ( ! empty( $ai_slug_raw ) ) {
			$ai_slug = MSH_Image_Optimizer_Context_Helper::slugify( $ai_slug_raw );
			// Enforce 4-word maximum (AI should generate 3-4 words, but truncate as safeguard)
			$ai_slug = $this->truncate_slug( $ai_slug, 4 );
			error_log( '[MSH] Using AI-generated filename slug (truncated to 4 words): ' . $ai_slug );
			return $ai_slug;
		}

		if ( ! MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) && $context['type'] === 'clinical' ) {
			$context['type'] = 'business';
		}

		switch ( $context['type'] ) {
			case 'team':
				$name = ! empty( $context['staff_name'] ) ? $context['staff_name'] : 'team-member';
				$slug = MSH_Image_Optimizer_Context_Helper::slugify( "{$this->business_name}-team-{$name}" );
				return $this->truncate_slug( $slug, 4 );
			case 'testimonial':
				$prefix             = MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ? 'patient' : 'client';
				$subject_slug       = ! empty( $context['attachment_slug'] ) ? $this->truncate_slug( $context['attachment_slug'], 3 ) : $prefix;
				$location_component = $this->location_slug !== '' ? '-' . $this->location_slug : '';
				$slug = MSH_Image_Optimizer_Context_Helper::slugify( $prefix . '-testimonial-' . $subject_slug . $location_component );
				return $this->truncate_slug( $slug, 4 );
			case 'icon':
				// Legacy fallback - reuse service-icon generator with proper arguments
				$normalized_context         = $context;
				$normalized_context['type'] = 'service-icon';
				return $this->generate_filename_slug( $attachment_id, $normalized_context, $extension );
			case 'service-icon':
				// FIRST: Try filename extraction for high-quality names (same as icon case)
				$original_filename  = $context['original_filename'] ?? '';
				$extracted_keywords = $this->extract_filename_keywords( $original_filename );

				// Debug disabled for performance

				if ( ! empty( $extracted_keywords ) ) {
					$concept_source = $extracted_keywords;
				} else {
					$concept_source = ! empty( $context['icon_concept'] ) ? MSH_Image_Optimizer_Context_Helper::slugify( $context['icon_concept'] ) : '';
					if ( $concept_source === '' ) {
						$concept_source = $context['service'] ?? 'service';
					}
				}

				$concept = MSH_Image_Optimizer_Context_Helper::slugify( $concept_source );
				if ( $concept === '' ) {
					$concept = 'service';
				}

				$components = array( $concept, 'icon' );
				if ( $this->location_slug !== '' ) {
					$components[] = $this->location_slug;
				}

				$slug = $this->assemble_slug( $components );
				return $this->truncate_slug( $slug, 4 );
			case 'facility':
				return MSH_Image_Optimizer_Context_Helper::slugify( $this->business_name . '-facility-' . $this->location_slug );
			case 'equipment':
				// Smart filename extraction from original name
				$original_filename  = $context['original_filename'] ?? '';
				$extracted_keywords = $this->extract_filename_keywords( $original_filename );

				$this->log_debug( "MSH Debug Equipment Case: Original='$original_filename', Extracted='$extracted_keywords'" );

				if ( ! empty( $extracted_keywords ) ) {
					return MSH_Image_Optimizer_Context_Helper::slugify( $extracted_keywords . '-equipment-' . $this->location_slug );
				}

				if ( ! empty( $context['asset'] ) && $context['asset'] === 'product' ) {
					$product_map  = array(
						'therapeutic-pillow'  => 'pillow',
						'custom-orthotics'    => 'orthotics',
						'support-brace'       => 'brace',
						'support-product'     => 'support',
						'tens-unit'           => 'tens-unit',
						'pain-relief'         => 'pain-relief',
						'compression-therapy' => 'compression',
					);
					$product_type = $context['product_type'] ?? 'support';
					$product_slug = $this->truncate_slug( $product_map[ $product_type ] ?? $product_type, 2 );
					$components   = array_filter( array( $product_slug, $this->location_slug ) );
					$slug = MSH_Image_Optimizer_Context_Helper::slugify( implode( '-', $components ) );
					return $this->truncate_slug( $slug, 4 );
				}

				// Try to extract descriptor from metadata (title, alt, caption)
				$descriptor_details = $this->build_business_descriptor_details( $context );
				$descriptor_slug    = $descriptor_details['slug'];

				if ( ! empty( $descriptor_slug ) && $descriptor_slug !== 'brand' ) {
					$location_suffix = $this->location_slug !== '' ? '-' . $this->location_slug : '';
					$slug            = MSH_Image_Optimizer_Context_Helper::slugify( $descriptor_slug . '-equipment' . $location_suffix );
					return $this->truncate_slug( $slug, 4 );
				}

				// Final fallback
				$location_suffix = $this->location_slug !== '' ? '-' . $this->location_slug : '';
				$slug            = MSH_Image_Optimizer_Context_Helper::slugify( 'equipment-showcase' . $location_suffix );
				return $this->truncate_slug( $slug, 4 );
			case 'business':
				$original_filename = strtolower( $context['original_filename'] ?? '' );
				$original_basename = $original_filename !== ''
					? pathinfo( $original_filename, PATHINFO_FILENAME )
					: '';
				$attachment_title  = strtolower( $context['attachment_title'] ?? '' );

				if ( $this->should_use_generic_industry_slug( $original_basename, $attachment_title ) ) {
					$prefix             = $this->get_industry_slug_prefix();
					$location_component = $this->city_slug !== ''
						? $this->city_slug
						: $this->location_slug;

					$components = array( $prefix );
					if ( $location_component !== '' ) {
						$components[] = $location_component;
					}

					$attachment_id_component = (string) ( $context['attachment_id'] ?? $attachment_id );
					if ( $attachment_id_component !== '' ) {
						$components[] = $attachment_id_component;
					}

					$slug = MSH_Image_Optimizer_Context_Helper::slugify( implode( '-', array_filter( $components ) ) );
					return $this->truncate_slug( $slug, 4 );
				}

				$brand_keywords = $this->extract_brand_keywords( $original_filename );

				$descriptor_details = $this->build_business_descriptor_details( $context );
				$descriptor_slug    = $descriptor_details['slug'];

				$brand_slug = '';
				if ( ! empty( $brand_keywords ) ) {
					$brand_slug = $this->limit_slug_parts( MSH_Image_Optimizer_Context_Helper::slugify( $brand_keywords ), 2 );
				} elseif ( $this->business_name !== '' ) {
					$brand_slug = $this->limit_slug_parts( MSH_Image_Optimizer_Context_Helper::slugify( $this->business_name ), 1 );
				}

				$include_brand    = $this->should_include_business_name( $context, $descriptor_slug );
				$include_location = $this->should_include_location_in_slug( $context );

				$asset_component    = $this->get_asset_slug_component( $context, $descriptor_slug );
				$location_component = $this->city_slug !== ''
					? $this->city_slug
					: $this->location_slug;

				$location_to_append = '';
				if ( $include_location && $location_component !== '' ) {
					$location_to_append = $location_component;
				}

				$components = array();
				if ( $descriptor_slug !== '' ) {
					$components[] = $descriptor_slug;
				}
				if ( $include_brand && $brand_slug !== '' ) {
					$components[] = $brand_slug;
				}
				if ( $asset_component !== '' ) {
					$components[] = $asset_component;
				}

				$components = $this->sanitize_business_slug_components( $components );

				if ( empty( $components ) ) {
					if ( $brand_slug !== '' ) {
						$components[] = $brand_slug;
					} elseif ( $descriptor_slug !== '' ) {
						$components[] = $descriptor_slug;
					} else {
						$components[] = 'brand';
					}
				}

				$components = $this->sanitize_business_slug_components( $components );

				$camera_suffix = $this->extract_camera_sequence_suffix(
					$context['original_filename'] ?? '',
					$context['file_basename'] ?? ''
				);

				if ( $camera_suffix !== '' && ! in_array( $camera_suffix, $components, true ) ) {
					$components[] = $camera_suffix;
				}

				if ( $location_to_append !== '' ) {
					$components[] = $location_to_append;
				}

				$slug = $this->assemble_slug( $components );
				return $this->truncate_slug( $slug, 4 );
			case 'clinical':
			default:
				// SEO-optimized treatment keywords (more specific first, word-boundary safe)
				$treatment_keywords = array(
					'auto-accident'    => array( 'auto accident', 'car accident', 'motor vehicle accident', 'mva' ),
					'workplace-injury' => array( 'workplace injury', 'work injury', 'ergonomic injury' ),
					'sports-injury'    => array( 'sports injury', 'athletic injury', 'athlete injury' ),
					'concussion'       => array( 'concussion', 'head injury', 'brain injury' ),
					'sciatica'         => array( 'sciatica', 'sciatic nerve', 'leg pain' ),
					'back-pain'        => array( 'back pain', 'spine pain', 'spinal pain', 'lumbar pain' ),
					'neck-pain'        => array( 'neck pain', 'cervical pain', 'whiplash' ),
					'tmj'              => array( 'tmj', 'jaw pain', 'temporal mandibular' ),
					'shoulder-pain'    => array( 'shoulder pain', 'rotator cuff' ),
					'knee-pain'        => array( 'knee pain', 'patella' ),
					'hip-pain'         => array( 'hip pain', 'pelvis pain' ),
					'ankle-pain'       => array( 'ankle pain', 'foot pain' ),
					'arthritis'        => array( 'arthritis', 'joint pain' ),
					'rehabilitation'   => array( 'rehabilitation', 'rehab', 'recovery', 'therapy' ),
				);

				// FIRST: Try direct filename keyword extraction for high-quality names
				$original_filename  = strtolower( $context['original_filename'] ?? '' );
				$extracted_keywords = $this->extract_filename_keywords( $original_filename );

				if ( ! empty( $extracted_keywords ) && $this->is_high_quality_extracted_name( $extracted_keywords, $original_filename ) ) {
					$this->log_debug( "MSH Clinical Debug: Using high-quality extracted keywords '$extracted_keywords' from '$original_filename'" );
					$slug = MSH_Image_Optimizer_Context_Helper::slugify( $extracted_keywords . '-' . $this->location_slug );
					return $this->truncate_slug( $slug, 4 );
				}

				// FALLBACK: Extract keywords from context AND original filename for treatment matching
				$service          = $context['service'] ?? 'rehabilitation';
				$page_title       = strtolower( $context['page_title'] ?? '' );
				$attachment_title = strtolower( $context['attachment_title'] ?? '' );
				$search_text      = $page_title . ' ' . $attachment_title . ' ' . $service . ' ' . $original_filename;

				// Debug logging for problematic cases
				$this->log_debug( "MSH Clinical Debug: AttachmentID={$context['attachment_id']}, SearchText='$search_text'" );

				// Find best matching treatment (prioritize filename over page context)
				$primary_keyword = 'rehabilitation'; // fallback

				// First, try to extract from filename specifically (with word boundaries)
				if ( ! empty( $original_filename ) ) {
					foreach ( $treatment_keywords as $keyword => $variations ) {
						foreach ( $variations as $variation ) {
							// Use word boundaries to prevent partial matches
							if ( preg_match( '/\b' . preg_quote( $variation, '/' ) . '\b/', $original_filename ) ) {
								$primary_keyword = $keyword;
								$this->log_debug( "MSH Clinical Debug: Found '$variation' in filename -> '$keyword'" );
								break 2; // Exit both loops
							}
						}
					}
				}

				// If no match in filename, try full search text (with word boundaries)
				if ( $primary_keyword === 'rehabilitation' ) {
					foreach ( $treatment_keywords as $keyword => $variations ) {
						foreach ( $variations as $variation ) {
							// Use word boundaries to prevent partial matches
							if ( preg_match( '/\b' . preg_quote( $variation, '/' ) . '\b/', $search_text ) ) {
								$primary_keyword = $keyword;
								$this->log_debug( "MSH Clinical Debug: Found '$variation' in search text -> '$keyword'" );
								break 2; // Exit both loops
							}
						}
					}
				}

				// Build SEO-friendly filename: treatment-hamilton-service
				$treatment_type = ( $service === 'chiropractic' ) ? 'chiropractic' : 'physiotherapy';
				$parts          = array( $primary_keyword );

				// Add Hamilton for local SEO (but not for generic terms)
				if ( $primary_keyword !== 'rehabilitation' ) {
					$parts[] = $this->location_slug;
				}

				// Add treatment type
				$parts[] = $treatment_type;

				$base_slug = implode( '-', array_filter( $parts ) );
				$slug      = MSH_Image_Optimizer_Context_Helper::slugify( $base_slug );
				return $this->truncate_slug( $slug, 4 );
		}
	}

	private function should_use_generic_industry_slug( $filename, $title ) {
		$candidates = array();
		if ( $filename !== '' ) {
			$candidates[] = str_replace( array( '-', '_' ), ' ', strtolower( $filename ) );
		}
		if ( $title !== '' ) {
			$candidates[] = strtolower( $title );
		}

		foreach ( $candidates as $candidate ) {
			$candidate = trim( $candidate );
			if ( $candidate === '' ) {
				continue;
			}

			if ( $this->is_generic_descriptor( $candidate ) ) {
				return true;
			}

			if ( preg_match( '/\b\d+x\d+\b/', $candidate ) ) {
				return true;
			}
		}

		return false;
	}

	private function get_industry_slug_prefix() {
		$map = array(
			'plumbing'   => 'plumbing-services',
			'hvac'       => 'hvac-services',
			'electrical' => 'electrical-services',
			'renovation' => 'renovation-services',
			'legal'      => 'legal-services',
			'accounting' => 'accounting-services',
			'consulting' => 'consulting-services',
			'marketing'  => 'marketing-services',
			'web_design' => 'web-design-services',
		);

		if ( isset( $map[ $this->industry ] ) ) {
			return $map[ $this->industry ];
		}

		if ( $this->industry_label !== '' ) {
			$label_slug = MSH_Image_Optimizer_Context_Helper::slugify( $this->industry_label );
			if ( $label_slug !== '' ) {
				return $label_slug;
			}
		}

		if ( $this->business_name !== '' ) {
			$brand_slug = MSH_Image_Optimizer_Context_Helper::slugify( $this->business_name );
			if ( $brand_slug !== '' ) {
				return $brand_slug;
			}
		}

		return 'brand';
	}

	private function generate_clinical_meta( array $context ) {
		$service          = $context['service'] ?? 'rehabilitation';
		$service_label    = $this->format_service_label( $service );
		$service_lower    = strtolower( $service_label );
		$page_title_lower = strtolower( (string) ( $context['page_title'] ?? '' ) );

		$variant = 'default';
		if ( strpos( $page_title_lower, 'assessment' ) !== false ) {
			$variant = 'assessment';
		} elseif ( strpos( $page_title_lower, 'acute' ) !== false ) {
			$variant = 'acute';
		}

		$keyword_line = $this->get_service_keyword_line( $service, $variant );

		$action_word = array(
			'default'    => 'Treatment',
			'assessment' => 'Assessment',
			'acute'      => 'Care',
		)[ $variant ] ?? 'Treatment';

		$title_focus = trim( "{$service_label} {$action_word}" );
		$title       = $this->ensure_unique_title(
			"{$title_focus} - {$this->business_name} {$this->location}",
			$context['attachment_id'] ?? 0
		);

		$caption_map = array(
			'default'    => "Professional {$service_label} treatment session",
			'assessment' => "Clinical {$service_label} assessment in progress",
			'acute'      => "Immediate {$service_label} care for acute injuries",
		);

		$description_map = array(
			'default'    => "Comprehensive {$service_lower} care tailored to patient recovery. {$keyword_line}",
			'assessment' => "Detailed {$service_lower} assessment with measurable progress tracking. {$keyword_line}",
			'acute'      => "Rapid-response {$service_lower} care supporting immediate relief. {$keyword_line}",
		);

		$alt_map = array(
			'default'    => "{$service_label} treatment at {$this->business_name} {$this->location} rehabilitation clinic",
			'assessment' => "{$service_label} assessment at {$this->business_name} {$this->location} clinic",
			'acute'      => "{$service_label} care team providing acute support at {$this->business_name} {$this->location}",
		);

		return array(
			'title'       => $this->clean_text( $title ),
			'alt_text'    => $this->clean_text( $alt_map[ $variant ] ?? $alt_map['default'] ),
			'caption'     => $this->clean_text( $caption_map[ $variant ] ?? $caption_map['default'] ),
			'description' => $this->clean_text( $description_map[ $variant ] ?? $description_map['default'] ),
		);
	}

	private function get_service_keyword_line( $service, $variant ) {
		$service = strtolower( (string) $service );

		if ( ! isset( $this->service_keyword_map[ $service ] ) ) {
			$default_service = $this->get_default_service_slug( $this->industry );
			if ( ! empty( $default_service ) && isset( $this->service_keyword_map[ $default_service ] ) ) {
				$service = $default_service;
			} else {
				$service = 'general';
			}
		}

		$variants = $this->service_keyword_map[ $service ];
		if ( ! isset( $variants[ $variant ] ) ) {
			$default = $variants['default'] ?? ( $this->service_keyword_map['general']['default'] ?? '' );
			return $default;
		}

		return $variants[ $variant ];
	}

	private function generate_team_meta( array $context ) {
		$is_healthcare = MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry );
		$default_name  = $is_healthcare ? __( 'Healthcare Professional', 'msh-image-optimizer' ) : __( 'Team Member', 'msh-image-optimizer' );
		$name          = ! empty( $context['staff_name'] ) ? $context['staff_name'] : $default_name;

		if ( $is_healthcare ) {
			return array(
				'title'       => $this->clean_text( "{$name} - {$this->business_name} {$this->location}" ),
				'alt_text'    => $this->clean_text( "{$name}, healthcare professional at {$this->business_name} {$this->location}" ),
				'caption'     => $this->clean_text( "{$name} - Registered rehabilitation provider" ),
				'description' => $this->clean_text( "{$name} provides expert healthcare services at {$this->business_name} in {$this->location}. Specialized recovery programs and comprehensive patient care." ),
			);
		}

		$industry_label   = $this->get_industry_label_or_default();
		$title_components = array( $name );
		$company_segment  = $this->business_name !== '' ? $this->business_name : $industry_label;
		if ( $company_segment !== '' ) {
			$title_components[] = $company_segment;
		}
		if ( $this->location !== '' ) {
			$title_components[] = $this->location;
		}

		$title = $this->clean_text(
			$this->ensure_unique_title(
				implode( ' – ', array_filter( $title_components ) ),
				$context['attachment_id'] ?? 0
			)
		);

		$alt_text = $this->clean_text(
			sprintf(
				__( '%1$s from %2$s%3$s.', 'msh-image-optimizer' ),
				$name,
				$this->business_name !== '' ? $this->business_name : $industry_label,
				$this->get_location_phrase( ' in ' )
			)
		);

		$caption = $this->clean_text(
			sprintf(
				__( '%1$s – part of the %2$s team', 'msh-image-optimizer' ),
				$name,
				strtolower( $industry_label )
			)
		);

		$description_parts = array_filter(
			array(
				sprintf(
					__( 'Meet %1$s from %2$s%3$s.', 'msh-image-optimizer' ),
					$name,
					$this->business_name,
					$this->get_location_phrase( ' in ' )
				),
				$this->normalize_sentence( $this->uvp ),
				$this->get_target_audience_sentence(),
				$this->get_cta_sentence(),
			)
		);

		return array(
			'title'       => $title,
			'alt_text'    => $alt_text,
			'caption'     => $caption,
			'description' => $this->clean_text( implode( ' ', $description_parts ) ),
		);
	}

	private function generate_testimonial_meta( array $context ) {
		$is_healthcare = MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry );
		$subject       = ! empty( $context['subject_name'] )
			? $context['subject_name']
			: ( $is_healthcare ? __( 'Patient', 'msh-image-optimizer' ) : __( 'Client', 'msh-image-optimizer' ) );

		if ( $is_healthcare ) {
			$service       = $context['service'] ?? 'rehabilitation';
			$service_label = $this->format_service_label( $service );
			$service_lower = strtolower( $service_label );
			$keywords_line = $this->get_service_keyword_line( $service, 'default' );

			$caption = sprintf(
				__( '%1$s shares %2$s recovery experience at %3$s', 'msh-image-optimizer' ),
				$subject,
				$service_lower,
				$this->business_name
			);

			$description = sprintf(
				__( 'Patient testimonial from %1$s highlighting %2$s recovery at %3$s %4$s. %5$s', 'msh-image-optimizer' ),
				$subject,
				$service_lower,
				$this->business_name,
				$this->location,
				$keywords_line
			);

			$title_base  = "{$subject} Success Story - {$this->business_name} {$this->location}";
			$final_title = $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 );

			return array(
				'title'       => $this->clean_text( $final_title ),
				'alt_text'    => $this->clean_text( sprintf( __( '%1$s shares %2$s recovery story at %3$s %4$s', 'msh-image-optimizer' ), $subject, $service_lower, $this->business_name, $this->location ) ),
				'caption'     => $this->clean_text( $caption ),
				'description' => $this->clean_text( $description ),
			);
		}

		$industry_label  = $this->get_industry_label_or_default();
		$location_phrase = $this->get_location_phrase( ' | ' );
		$title_base      = sprintf(
			__( '%1$s Success Story – %2$s%3$s', 'msh-image-optimizer' ),
			$subject,
			$this->business_name,
			$location_phrase
		);

		$description_parts = array_filter(
			array(
				sprintf(
					__( 'Testimonial from %1$s showcasing %2$s outcomes with %3$s%4$s.', 'msh-image-optimizer' ),
					$subject,
					strtolower( $industry_label ),
					$this->business_name,
					$this->get_location_phrase( ' in ' )
				),
				$this->normalize_sentence( $this->uvp ),
				$this->normalize_sentence( $this->pain_points ),
				$this->get_cta_sentence(),
			)
		);

		$caption = sprintf(
			__( '%1$s shares their experience with %2$s.', 'msh-image-optimizer' ),
			$subject,
			$this->business_name
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( sprintf( __( '%1$s testimonial for %2$s%3$s.', 'msh-image-optimizer' ), $subject, $this->business_name, $this->get_location_phrase( ' in ' ) ) ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( implode( ' ', $description_parts ) ),
		);
	}

	private function generate_service_icon_meta( array $context ) {
		// Try to get specific concept from the icon
		$concept_label = '';

		// First try to extract from original filename (Noun Project files)
		if ( ! empty( $context['original_filename'] ) ) {
			if ( preg_match( '/^noun-(.+)-\d{4,7}-[A-F0-9]{6}/i', $context['original_filename'], $matches ) ) {
				$concept_label = $this->format_service_label( $matches[1] );
				$this->log_debug( "MSH Service Icon Meta: Extracted concept '$concept_label' from filename" );
			}
		}

		// If no concept found, try icon_concept field
		if ( empty( $concept_label ) && ! empty( $context['icon_concept'] ) ) {
			$concept = $context['icon_concept'];
			// Clean any Noun Project patterns
			if ( preg_match( '/^noun-(.+)-\d{4,}/i', $concept, $matches ) ) {
				$concept = $matches[1];
			}
			$concept_label = $this->format_service_label( $concept );
		}

		// Fallback to service if no specific concept
		if ( empty( $concept_label ) ) {
			$service       = $context['service'] ?? 'rehabilitation';
			$concept_label = $this->format_service_label( $service );
		}

		$industry_label = $this->get_industry_label_or_default();
		$title_base     = $this->business_name !== ''
			? sprintf( __( '%1$s Icon – %2$s', 'msh-image-optimizer' ), $concept_label, $this->business_name )
			: sprintf( __( '%s Icon', 'msh-image-optimizer' ), $concept_label );

		if ( $this->location !== '' ) {
			$title_base .= ' | ' . $this->location;
		}

		$brand_label     = $this->business_name !== '' ? $this->business_name : __( 'the brand', 'msh-image-optimizer' );
		$location_phrase = $this->get_location_phrase( ' in ' );

		$alt_text = $this->business_name !== ''
			? sprintf( __( '%1$s icon for %2$s%3$s.', 'msh-image-optimizer' ), $concept_label, $this->business_name, $location_phrase )
			: sprintf( __( '%s icon graphic.', 'msh-image-optimizer' ), $concept_label );

		$caption = $this->business_name !== ''
			? sprintf( __( '%1$s icon used across %2$s digital platforms.', 'msh-image-optimizer' ), $concept_label, $this->business_name )
			: sprintf( __( '%s icon used across digital platforms.', 'msh-image-optimizer' ), $concept_label );

		$description_parts = array_filter(
			array(
				sprintf(
					__( 'Custom %1$s icon supporting %2$s brand presence%3$s.', 'msh-image-optimizer' ),
					strtolower( $concept_label ),
					$brand_label,
					$location_phrase
				),
				__( 'Provides consistent visual language across navigation.', 'msh-image-optimizer' ),
				__( 'Optimized for clarity and accessibility across digital touchpoints.', 'msh-image-optimizer' ),
			)
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( implode( ' ', $description_parts ) ),
		);
	}


	private function generate_logo_meta( array $context ) {
		$location       = $this->location;
		$industry_label = $this->get_industry_label_or_default();
		$title          = $this->business_name !== ''
			? sprintf( __( '%s Logo', 'msh-image-optimizer' ), $this->business_name )
			: __( 'Brand Logo', 'msh-image-optimizer' );

		if ( $location !== '' ) {
			$title .= ' | ' . $location;
		}

		$alt_text = $this->business_name !== ''
			? sprintf( __( 'Official logo for %s', 'msh-image-optimizer' ), $this->business_name )
			: __( 'Official brand logo', 'msh-image-optimizer' );

		$caption = $this->business_name !== ''
			? sprintf( __( '%1$s %2$s branding', 'msh-image-optimizer' ), $this->business_name, strtolower( $industry_label ) )
			: sprintf( __( 'Branding for %s', 'msh-image-optimizer' ), strtolower( $industry_label ) );

		$description_parts = array_filter(
			array(
				sprintf(
					__( 'Official %1$s logo showcasing %2$s%3$s.', 'msh-image-optimizer' ),
					$this->business_name !== '' ? $this->business_name : __( 'the business', 'msh-image-optimizer' ),
					$this->get_industry_descriptor(),
					$this->get_location_phrase( ' in ' )
				),
				$this->normalize_sentence( $this->uvp ),
				$this->get_cta_sentence(),
			)
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( implode( ' ', $description_parts ) ),
		);
	}

	private function generate_product_meta( array $context ) {
		if ( ! MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ) {
			$product_name = ! empty( $context['attachment_title'] )
				? $context['attachment_title']
				: __( 'Featured Product', 'msh-image-optimizer' );

			$title_components = array( $product_name );
			if ( $this->business_name !== '' ) {
				$title_components[] = $this->business_name;
			}

			$title = implode( ' – ', array_filter( $title_components ) );
			if ( $this->location !== '' ) {
				$title .= ' | ' . $this->location;
			}

			$alt_text = sprintf(
				__( 'Product spotlight: %1$s%2$s.', 'msh-image-optimizer' ),
				$product_name,
				$this->business_name !== '' ? sprintf( __( ' from %s', 'msh-image-optimizer' ), $this->business_name ) : ''
			);
			if ( $this->location !== '' ) {
				$alt_text = rtrim( $alt_text, '.' ) . sprintf( __( ' in %s.', 'msh-image-optimizer' ), $this->location );
			}

			$caption_candidates = array(
				$this->normalize_sentence( $this->uvp ),
				$this->business_name !== '' ? sprintf( __( 'Signature offering from %s.', 'msh-image-optimizer' ), $this->business_name ) : __( 'Signature offering.', 'msh-image-optimizer' ),
			);

			$caption = '';
			foreach ( $caption_candidates as $candidate ) {
				if ( $candidate !== '' ) {
					$caption = $candidate;
					break;
				}
			}

			$description_parts = array_filter(
				array(
					sprintf(
						__( '%1$s provides %2$s solutions like %3$s%4$s.', 'msh-image-optimizer' ),
						$this->business_name !== '' ? $this->business_name : __( 'This business', 'msh-image-optimizer' ),
						$this->get_industry_descriptor(),
						$product_name,
						$this->get_location_phrase( ' in ' )
					),
					$this->normalize_sentence( $this->pain_points ),
					$this->get_cta_sentence(),
				)
			);

			return array(
				'title'       => $this->clean_text( $this->ensure_unique_title( $title, $context['attachment_id'] ?? 0 ) ),
				'alt_text'    => $this->clean_text( $alt_text ),
				'caption'     => $this->clean_text( $caption ),
				'description' => $this->clean_text( implode( ' ', $description_parts ) ),
			);
		}

		$map = array(
			'therapeutic-pillow'  => array(
				'name'        => 'Therapeutic Support Pillow',
				'caption'     => 'Mediflow water-based pillow for neck support',
				'description' => 'Therapeutic pillow recommended for neck pain and sleep positioning. Available for purchase at our clinic.',
			),
			'custom-orthotics'    => array(
				'name'        => 'Custom Orthotics',
				'caption'     => 'Custom-fitted orthotic insoles',
				'description' => 'Custom orthotics designed for optimal foot support and biomechanical correction. Professional fitting available.',
			),
			'support-brace'       => array(
				'name'        => 'Support Brace',
				'caption'     => 'Medical-grade support brace for injury recovery',
				'description' => 'Support brace for joint stabilization and injury recovery. Multiple sizes available with professional fitting.',
			),
			'support-product'     => array(
				'name'        => 'Rehabilitation Support Product',
				'caption'     => 'Clinical support product for rehabilitation',
				'description' => 'Therapeutic support product recommended by our rehabilitation team. Available for purchase with insurance receipts.',
			),
			'tens-unit'           => array(
				'name'        => 'TENS Unit',
				'caption'     => 'TENS electrotherapy device',
				'description' => 'Transcutaneous electrical nerve stimulation unit for pain relief and muscle activation. Professional guidance provided.',
			),
			'pain-relief'         => array(
				'name'        => 'Pain Relief Product',
				'caption'     => 'Topical pain relief solution',
				'description' => 'Professional pain relief products recommended by our therapists. Available for purchase with usage instructions.',
			),
			'compression-therapy' => array(
				'name'        => 'Compression Therapy Garment',
				'caption'     => 'Compression stocking for circulation support',
				'description' => 'Compression therapy garment supporting circulation and recovery. Measurements and fittings performed in clinic.',
			),
		);

		$product_type = $context['product_type'] ?? 'support-product';
		$product      = $map[ $product_type ] ?? $map['support-product'];

		$title       = $product['name'] . " - {$this->business_name} {$this->location}";
		$alt_text    = $product['name'] . ' available at ' . $this->business_name;
		$caption     = $product['caption'];
		$description = $product['description'];

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_facility_meta( array $context ) {
		if ( MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ) {
			// POLICY: Respect SEO mode - when false, no brand/location
			$seo_mode = ! empty( $context['seo_mode'] );

			if ( $seo_mode ) {
				// SEO enabled: Include brand and location
				return array(
					'title'       => $this->clean_text( "{$this->business_name} Clinic - {$this->location} Rehabilitation Facility" ),
					'alt_text'    => $this->clean_text( "Interior view of {$this->business_name} rehabilitation clinic in {$this->location}" ),
					'caption'     => $this->clean_text( "Modern rehabilitation facility at {$this->business_name} {$this->location}" ),
					'description' => $this->clean_text( "Modern healthcare facility at {$this->business_name} {$this->location}. Professional clinic with specialized treatment rooms and comprehensive care programs." ),
				);
			} else {
				// SEO disabled: Scene-only, no brand/location
				return array(
					'title'       => $this->clean_text( __( 'Rehabilitation Clinic Interior', 'msh-image-optimizer' ) ),
					'alt_text'    => $this->clean_text( __( 'Interior view of rehabilitation clinic facility.', 'msh-image-optimizer' ) ),
					'caption'     => $this->clean_text( __( 'Modern rehabilitation facility interior', 'msh-image-optimizer' ) ),
					'description' => $this->clean_text( __( 'Modern healthcare facility interior. Professional clinic with specialized treatment rooms and comprehensive care programs.', 'msh-image-optimizer' ) ),
				);
			}
		}

		// POLICY: Respect SEO mode for non-healthcare facility too
		$seo_mode = ! empty( $context['seo_mode'] );
		$industry_label = $this->get_industry_label_or_default();
		$location       = $this->location;

		if ( $seo_mode ) {
			// SEO enabled: Include brand and location
			$title = $this->business_name !== ''
				? sprintf( __( '%1$s Workspace – %2$s', 'msh-image-optimizer' ), $this->business_name, $location )
				: sprintf( __( 'Workspace – %s', 'msh-image-optimizer' ), $location );

			$alt_text = $this->business_name !== ''
				? sprintf( __( 'Interior view of %1$s%2$s.', 'msh-image-optimizer' ), $this->business_name, $this->get_location_phrase( ' in ' ) )
				: sprintf( __( 'Interior workspace%1$s.', 'msh-image-optimizer' ), $this->get_location_phrase( ' in ' ) );

			$caption = sprintf(
				__( 'Collaborative space for %1$s team members.', 'msh-image-optimizer' ),
				strtolower( $industry_label )
			);

			$description_parts = array_filter(
				array(
					sprintf(
						__( 'The %1$s workspace%2$s designed for %3$s collaboration.', 'msh-image-optimizer' ),
						$this->business_name !== '' ? $this->business_name : __( 'business', 'msh-image-optimizer' ),
						$this->get_location_phrase( ' in ' ),
						strtolower( $industry_label )
					),
					$this->normalize_sentence( $this->uvp ),
					$this->get_target_audience_sentence(),
					$this->get_cta_sentence(),
				)
			);
		} else {
			// SEO disabled: Scene-only, no brand/location
			$title = sprintf( __( '%s Workspace Interior', 'msh-image-optimizer' ), ucfirst( $industry_label ) );

			$alt_text = sprintf( __( 'Interior view of %s workspace.', 'msh-image-optimizer' ), strtolower( $industry_label ) );

			$caption = sprintf(
				__( 'Collaborative workspace for %s professionals', 'msh-image-optimizer' ),
				strtolower( $industry_label )
			);

			$description_parts = array_filter(
				array(
					sprintf(
						__( 'Professional workspace designed for %s collaboration and productivity.', 'msh-image-optimizer' ),
						strtolower( $industry_label )
					),
				)
			);
		}

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( implode( ' ', $description_parts ) ),
		);
	}

	private function generate_equipment_meta( array $context ) {
		if ( MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ) {
			return array(
				'title'       => $this->clean_text( "Therapeutic Equipment - {$this->business_name} {$this->location}" ),
				'alt_text'    => $this->clean_text( "Therapeutic rehabilitation equipment at {$this->business_name} clinic in {$this->location}" ),
				'caption'     => $this->clean_text( "Advanced therapeutic equipment for rehabilitation at {$this->business_name}" ),
				'description' => $this->clean_text( "Professional rehabilitation equipment at {$this->business_name} {$this->location}. Advanced therapeutic technology supporting physiotherapy, chiropractic care, and patient recovery." ),
			);
		}

		$industry_label  = $this->get_industry_label_or_default();
		$title           = __( 'Operational Equipment', 'msh-image-optimizer' );
		$company_segment = $this->business_name !== '' ? $this->business_name : $industry_label;
		if ( $company_segment !== '' ) {
			$title .= ' – ' . $company_segment;
		}
		if ( $this->location !== '' ) {
			$title .= ' | ' . $this->location;
		}

		$alt_text = sprintf(
			__( 'Operational equipment at %1$s%2$s.', 'msh-image-optimizer' ),
			$this->business_name !== '' ? $this->business_name : __( 'the business', 'msh-image-optimizer' ),
			$this->get_location_phrase( ' in ' )
		);

		$caption = sprintf(
			__( 'Equipment supporting %s delivery.', 'msh-image-optimizer' ),
			strtolower( $industry_label )
		);

		$description_parts = array_filter(
			array(
				sprintf(
					__( 'Professional equipment used by %1$s for %2$s.', 'msh-image-optimizer' ),
					$this->business_name !== '' ? $this->business_name : __( 'the business', 'msh-image-optimizer' ),
					strtolower( $industry_label )
				),
				$this->normalize_sentence( $this->uvp ),
				$this->get_cta_sentence(),
			)
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( implode( ' ', $description_parts ) ),
		);
	}

	private function generate_plumbing_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Plumbing Specialists', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Plumbing Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Professional plumbing services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Licensed plumbing contractors', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed, insured plumbers serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed, insured plumbing team.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional plumbing services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_hvac_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'HVAC Experts', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'HVAC Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Heating and cooling services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Licensed HVAC technicians', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed HVAC contractors serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed HVAC contractors.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional heating and cooling services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_electrical_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Electrical Specialists', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Electrical Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Licensed electrical services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Licensed electrical contractors', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed, insured electricians serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed, insured electricians.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional electrical services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_renovation_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Renovation Experts', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Renovation Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Renovation and construction services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Licensed general contractors', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed, bonded contractors serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed, bonded contractors.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional renovation and construction services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_legal_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Law Firm', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Legal Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Legal representation at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Professional legal services', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed attorneys serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed attorneys providing confidential consultations.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional legal services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_accounting_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Accounting Firm', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Accounting Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Accounting and tax services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Comprehensive accounting and tax services', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Certified accounting professionals serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Certified accounting professionals delivering trusted guidance.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Comprehensive accounting and tax services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_consulting_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Consulting Agency', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Business Consulting – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Business consulting services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Strategic business consulting', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Strategic consultants supporting %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Strategic consultants delivering measurable growth.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Strategic business consulting services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_marketing_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Marketing Agency', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Marketing Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Digital marketing services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Full-service marketing agency', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Strategic marketers serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Strategic marketers delivering measurable campaigns.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Full-service marketing and brand strategy', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_web_design_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Web Design Studio', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Web Design Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Web design and development at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Custom web design and development', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Website specialists serving %s', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Website specialists delivering responsive, high-performing sites.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Custom website design and development services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_online_store_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Online Store', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s', $descriptor, $brand );
			if ( $location_label !== '' ) {
				$title_base .= $location_label;
			}
			$alt_text = sprintf(
				__( '%1$s available from %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Shop Online – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Online products from %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption     = __( 'Premium products with fast shipping', 'msh-image-optimizer' );
		$credentials = __( 'Fast shipping, secure checkout, satisfaction guaranteed.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Premium products available online', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_local_retail_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Local Retailer', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Local Retail – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Local retail products at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Personalized local shopping experience', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Local store serving %s with curated selections.', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Local store offering curated selections and personalized service.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Curated local retail products', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_specialty_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Specialty Provider', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Specialty Products – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Specialty products at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Expertly curated specialty items', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Specialty consultants serving %s.', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Specialty consultants delivering expert product guidance.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Curated specialty products and expert guidance', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_medical_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Medical Practice', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Medical Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Medical services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Comprehensive medical care', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Board-certified physicians serving %s.', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Board-certified physicians providing trusted care.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Comprehensive medical care and treatment', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_dental_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Dental Clinic', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Dental Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Dental care at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Comprehensive dental care', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed dentists serving %s.', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed dentists providing family-friendly care.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Comprehensive dental care for the whole family', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_therapy_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Therapy Practice', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Therapy Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Therapy and counseling at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Professional therapy and counseling', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Licensed therapists supporting %s.', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Licensed therapists providing confidential support.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional therapy and counseling services', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_wellness_meta( array $context, $descriptor = '' ) {
		$this->current_industry = 'wellness';

		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Wellness Studio', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		$temporal_keywords  = $this->get_temporal_keywords( 'wellness' );
		$trust_signals      = $this->get_trust_signals( 'wellness' );
		$achievement_marker = $this->get_achievement_markers( 'wellness' );

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
		} else {
			$title_base = sprintf( __( 'Wellness Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
		}

		if ( $temporal_keywords !== '' ) {
			$title_base .= ' | ' . ucwords( explode( ' ', $temporal_keywords )[0] );
		}

		$alt_components = array(
			$descriptor !== '' ? $descriptor : __( 'Holistic wellness services', 'msh-image-optimizer' ),
			__( 'at', 'msh-image-optimizer' ),
			$brand,
			$this->get_location_phrase( ' in ' ),
		);
		$primary_trust  = $trust_signals[0] ?? '';
		if ( $primary_trust !== '' ) {
			$alt_components[] = '-' . ' ' . $primary_trust;
		}
		$alt_text = implode( ' ', array_filter( array_map( 'trim', $alt_components ) ) );

		if ( $achievement_marker !== '' ) {
			$caption = $achievement_marker;
		} else {
			$caption = __( 'Holistic wellness and self-care services', 'msh-image-optimizer' );
		}

		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credential_parts   = array(
			$service_area_label !== ''
				? sprintf( __( 'Certified wellness practitioners serving %s.', 'msh-image-optimizer' ), $service_area_label )
				: __( 'Certified wellness practitioners delivering personalized care.', 'msh-image-optimizer' ),
		);
		if ( ! empty( $trust_signals ) ) {
			$credential_parts[] = implode( ' ', array_slice( $trust_signals, 0, 2 ) );
		}
		$credentials = trim( implode( ' ', array_filter( $credential_parts ) ) );

		$generic_summary = __( 'Holistic wellness and self-care services tailored to your goals.', 'msh-image-optimizer' );
		if ( $temporal_keywords !== '' ) {
			$generic_summary .= ' ' . $this->normalize_sentence(
				sprintf(
					__( 'Currently focusing on %s.', 'msh-image-optimizer' ),
					$temporal_keywords
				)
			);
		}

		$description = $this->build_industry_description(
			$generic_summary,
			$credentials,
			array(
				'industry' => 'wellness',
			)
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_other_meta( array $context, $descriptor = '' ) {
		if ( $descriptor === '' || $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = $this->extract_visual_descriptor( $context );
		}

		if ( $this->is_generic_descriptor( $descriptor ) ) {
			$descriptor = '';
		}

		$brand          = $this->business_name !== '' ? $this->business_name : __( 'Professional Services', 'msh-image-optimizer' );
		$location_label = $this->location !== '' ? ' | ' . $this->location : '';

		if ( $descriptor !== '' ) {
			$title_base = sprintf( '%s – %s%s', $descriptor, $brand, $location_label );
			$alt_text   = sprintf(
				__( '%1$s at %2$s%3$s.', 'msh-image-optimizer' ),
				$descriptor,
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		} else {
			$title_base = sprintf( __( 'Professional Services – %1$s%2$s', 'msh-image-optimizer' ), $brand, $location_label );
			$alt_text   = sprintf(
				__( 'Professional services at %1$s%2$s.', 'msh-image-optimizer' ),
				$brand,
				$this->get_location_phrase( ' in ' )
			);
		}

		$caption            = __( 'Professional services tailored to your needs', 'msh-image-optimizer' );
		$service_area_label = $this->service_area !== '' ? $this->service_area : $this->location;
		$credentials        = $service_area_label !== ''
			? sprintf( __( 'Specialists supporting %s.', 'msh-image-optimizer' ), $service_area_label )
			: __( 'Specialists delivering personalized service.', 'msh-image-optimizer' );

		$description = $this->build_industry_description(
			__( 'Professional services tailored to your needs', 'msh-image-optimizer' ),
			$credentials
		);

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title_base, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	private function generate_business_meta( array $context ) {
		// QUICK FIX: Stock context should be scene-only, no brand, no business boilerplate
		// This prevents identical UVP descriptions across all stock images
		$context_type = $context['type'] ?? '';
		if ( $context_type === 'stock' ) {
			return $this->generate_stock_meta( $context );
		}

		$industry_generators = array(
			'plumbing'     => 'generate_plumbing_meta',
			'hvac'         => 'generate_hvac_meta',
			'electrical'   => 'generate_electrical_meta',
			'renovation'   => 'generate_renovation_meta',
			'legal'        => 'generate_legal_meta',
			'accounting'   => 'generate_accounting_meta',
			'consulting'   => 'generate_consulting_meta',
			'marketing'    => 'generate_marketing_meta',
			'web_design'   => 'generate_web_design_meta',
			'online_store' => 'generate_online_store_meta',
			'local_retail' => 'generate_local_retail_meta',
			'specialty'    => 'generate_specialty_meta',
			'medical'      => 'generate_medical_meta',
			'dental'       => 'generate_dental_meta',
			'therapy'      => 'generate_therapy_meta',
			'wellness'     => 'generate_wellness_meta',
			'other'        => 'generate_other_meta',
		);

		if ( isset( $industry_generators[ $this->industry ] ) ) {
			$method = $industry_generators[ $this->industry ];
			if ( method_exists( $this, $method ) ) {
				return $this->{$method}( $context );
			}
		}

		$descriptor       = $this->build_business_descriptor_details( $context );
		$descriptor_label = $descriptor['label'];
		$descriptor_slug  = $descriptor['slug'];

		if ( $this->is_generic_descriptor( $descriptor_label ) ) {
			$descriptor_label = '';
		}
		if ( $this->is_generic_descriptor( $descriptor_slug ) ) {
			$descriptor_slug = '';
		}

		$include_brand    = $this->should_include_business_name( $context, $descriptor_slug );
		$include_location = $this->should_include_location_in_slug( $context );

		$brand_name     = $this->business_name;
		$location_label = $include_location ? $this->location : '';
		$industry_label = $this->get_industry_label_or_default();

		$title_parts = array();
		if ( $descriptor_label !== '' ) {
			$title_parts[] = $descriptor_label;
		}
		if ( $include_brand && $brand_name !== '' ) {
			$title_parts[] = $brand_name;
		} elseif ( $brand_name !== '' && empty( $title_parts ) ) {
			$title_parts[] = $brand_name;
		} elseif ( $industry_label !== '' && empty( $title_parts ) ) {
			$title_parts[] = $industry_label;
		}

		$title = implode( ' – ', $title_parts );
		if ( $title === '' ) {
			$title = $brand_name !== '' ? $brand_name : __( 'Brand Overview', 'msh-image-optimizer' );
		}

		if ( $include_location && $location_label !== '' ) {
			$title .= ' | ' . $location_label;
		}

		$subject_label = $descriptor_label !== ''
			? $descriptor_label
			: ( $brand_name !== '' ? $brand_name : __( 'the brand', 'msh-image-optimizer' ) );

		if ( $include_brand && $brand_name !== '' ) {
			if ( $include_location && $location_label !== '' ) {
				$alt_text = sprintf(
					__( '%1$s visual created for %2$s in %3$s.', 'msh-image-optimizer' ),
					$subject_label,
					$brand_name,
					$location_label
				);
			} else {
				$alt_text = sprintf(
					__( '%1$s visual created for %2$s.', 'msh-image-optimizer' ),
					$subject_label,
					$brand_name
				);
			}
		} elseif ( $include_location && $location_label !== '' ) {
				$alt_text = sprintf(
					__( '%1$s visual in %2$s.', 'msh-image-optimizer' ),
					$subject_label,
					$location_label
				);
		} else {
			$alt_text = sprintf( __( 'Visual of %s.', 'msh-image-optimizer' ), $subject_label );
		}

		if ( $descriptor_label !== '' ) {
			if ( $include_brand && $brand_name !== '' ) {
				$caption = sprintf(
					__( '%1$s visual for %2$s.', 'msh-image-optimizer' ),
					$descriptor_label,
					$brand_name
				);
			} else {
				$caption = sprintf( __( 'Visual highlight: %s.', 'msh-image-optimizer' ), $descriptor_label );
			}
		} else {
			$caption = $brand_name !== ''
				? sprintf( __( 'Brand visual for %s.', 'msh-image-optimizer' ), $brand_name )
				: __( 'Brand visual highlight.', 'msh-image-optimizer' );
		}

		$provider_name = $brand_name !== '' ? $brand_name : __( 'this brand', 'msh-image-optimizer' );
		$visual_focus  = $descriptor_label !== ''
			? $descriptor_label
			: __( 'brand imagery', 'msh-image-optimizer' );

		$description_parts = array();

		if ( $descriptor_label !== '' ) {
			$description_parts[] = sprintf( __( 'Visual highlight: %s.', 'msh-image-optimizer' ), $descriptor_label );
		} else {
			$description_parts[] = __( 'Visual highlight from the brand collection.', 'msh-image-optimizer' );
		}

		if ( $include_brand && $brand_name !== '' ) {
			if ( $include_location && $location_label !== '' ) {
				$description_parts[] = sprintf( __( 'Created for %1$s in %2$s.', 'msh-image-optimizer' ), $brand_name, $location_label );
			} else {
				$description_parts[] = sprintf( __( 'Created for %s.', 'msh-image-optimizer' ), $brand_name );
			}
		} elseif ( $include_location && $location_label !== '' ) {
			$description_parts[] = sprintf( __( 'Captured in %s.', 'msh-image-optimizer' ), $location_label );
		}

		if ( $industry_label !== '' ) {
			$description_parts[] = sprintf(
				__( 'Industry focus: %s.', 'msh-image-optimizer' ),
				$this->normalize_descriptor_text( $industry_label )
			);
		}

		$uvp_sentence = $this->normalize_sentence( $this->uvp );
		if ( $uvp_sentence !== '' ) {
			$description_parts[] = $uvp_sentence;
		}

		$pain_sentence = $this->normalize_sentence( $this->pain_points );
		if ( $pain_sentence !== '' ) {
			$description_parts[] = $pain_sentence;
		}

		$audience_sentence = $this->get_target_audience_sentence();
		if ( $audience_sentence !== '' ) {
			$description_parts[] = $audience_sentence;
		}

		$cta_sentence = $this->get_cta_sentence();
		if ( $cta_sentence !== '' ) {
			$description_parts[] = $cta_sentence;
		}

		// Micro-upgrade: Add page-specific context if available
		if ( ! empty( $context['page_heading'] ) ) {
			$description_parts[] = sprintf( __( 'Featured on: %s.', 'msh-image-optimizer' ), $context['page_heading'] );
		} elseif ( ! empty( $context['page_excerpt'] ) ) {
			// Use first 100 chars of page excerpt
			$excerpt = mb_substr( $context['page_excerpt'], 0, 100 );
			if ( mb_strlen( $context['page_excerpt'] ) > 100 ) {
				$excerpt .= '...';
			}
			$description_parts[] = $excerpt;
		}

		$description = implode( ' ', $description_parts );

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title, $context['attachment_id'] ?? 0 ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	/**
	 * Generate scene-only metadata for stock images (no brand, no business boilerplate)
	 *
	 * Uses Smart Rephrase system when enabled (feature flag: msh_nonai_smart_rephrase_enabled)
	 * Falls back to quick fix templates when disabled
	 *
	 * @since 1.2.16
	 * @param array $context Image context array
	 * @return array Metadata array with title, alt_text, caption, description
	 */
	private function generate_stock_meta( array $context ) {
		// Feature flag: Use Smart Rephrase composer if enabled
		$smart_rephrase_enabled = get_option( 'msh_nonai_smart_rephrase_enabled', true );
		$class_exists = class_exists( 'MSH_NonAI_Composer' );

		error_log( sprintf(
			'[MSH Smart Rephrase] Flag=%s, Class=%s, ID=%d, File=%s',
			$smart_rephrase_enabled ? 'ON' : 'OFF',
			$class_exists ? 'EXISTS' : 'MISSING',
			$context['attachment_id'] ?? 0,
			$context['original_filename'] ?? 'NO_FILE'
		) );

		if ( $smart_rephrase_enabled && $class_exists ) {
			error_log( '[MSH Smart Rephrase] Using Smart Rephrase composer' );
			return $this->generate_stock_smart_rephrase( $context );
		}

		error_log( '[MSH Smart Rephrase] Using fallback templates' );
		// Fallback: Quick fix templates
		$original_filename = $context['original_filename'] ?? '';
		$attachment_id = $context['attachment_id'] ?? 0;

		// Use full filename as scene description (convert hyphens/underscores to spaces, title case)
		if ( $original_filename !== '' ) {
			// Remove extension
			$scene_raw = preg_replace( '/\.(jpg|jpeg|png|webp|gif)$/i', '', $original_filename );
			// Replace separators with spaces
			$scene_raw = str_replace( array( '-', '_' ), ' ', $scene_raw );
			// Title case
			$scene = ucwords( strtolower( $scene_raw ) );
		} else {
			$scene = __( 'Scenic view', 'msh-image-optimizer' );
		}

		// Deterministic variation based on attachment ID (simple modulo)
		$variant = $attachment_id % 4;

		// Title: Just the scene (no brand)
		$title = $scene;

		// ALT: Single descriptive sentence
		$alt_templates = array(
			__( '%s captured in natural setting.', 'msh-image-optimizer' ),
			__( 'Scenic view of %s.', 'msh-image-optimizer' ),
			__( '%s in landscape composition.', 'msh-image-optimizer' ),
			__( 'Natural view featuring %s.', 'msh-image-optimizer' ),
		);
		$alt_text = sprintf( $alt_templates[ $variant ], strtolower( $scene ) );

		// Caption: One scene sentence (varied)
		$caption_templates = array(
			__( 'Natural landscape featuring %s', 'msh-image-optimizer' ),
			__( 'Scenic composition with %s', 'msh-image-optimizer' ),
			__( 'Outdoor view of %s', 'msh-image-optimizer' ),
			__( 'Landscape photography highlighting %s', 'msh-image-optimizer' ),
		);
		$caption = sprintf( $caption_templates[ $variant ], strtolower( $scene ) );

		// Description: Two sentences - both scene-focused (NO brand, NO UVP)
		$desc_templates = array(
			__( 'A landscape view showcasing %1$s. The composition emphasizes natural elements and visual depth.', 'msh-image-optimizer' ),
			__( 'This scenic image features %1$s in a natural setting. The photograph captures ambient lighting and environmental context.', 'msh-image-optimizer' ),
			__( 'Natural scenery highlighting %1$s with attention to composition. The image presents a balanced view of the subject and surroundings.', 'msh-image-optimizer' ),
			__( 'An outdoor photograph of %1$s showing environmental details. The scene conveys a sense of place through natural lighting and framing.', 'msh-image-optimizer' ),
		);
		$description = sprintf( $desc_templates[ $variant ], strtolower( $scene ) );

		return array(
			'title'       => $this->clean_text( $this->ensure_unique_title( $title, $attachment_id ) ),
			'alt_text'    => $this->clean_text( $alt_text ),
			'caption'     => $this->clean_text( $caption ),
			'description' => $this->clean_text( $description ),
		);
	}

	/**
	 * Generate stock metadata using Smart Rephrase composer
	 *
	 * Maps context to canonical input format and invokes MSH_NonAI_Composer
	 *
	 * @since 1.3.0
	 * @param array $context Image context array
	 * @return array Metadata array
	 */
	private function generate_stock_smart_rephrase( array $context ) {
		return $this->generate_context_via_composer( $context );
	}

	/**
	 * Generate metadata for any context using the deterministic composer.
	 *
	 * @since 1.3.0
	 * @param array $context Image context array.
	 * @return array Metadata array.
	 */
	private function generate_context_via_composer( array $context ) {
		if ( ! class_exists( 'MSH_NonAI_Composer' ) ) {
			// Composer unavailable – fall back to legacy templates.
			return $this->generate_clinical_meta( $context );
		}

		if ( empty( $context['service_keywords'] ) ) {
			$context['service_keywords'] = $this->resolve_service_keywords_for_prompt( $context );
		}

		$policy_context_type = isset( $context['final_context_type'] ) ? $context['final_context_type'] : ( $context['type'] ?? 'stock' );
		$input               = array(
			'id'         => $context['attachment_id'] ?? 0,
			'filename'   => $context['original_filename'] ?? '',
			'biz_context' => array(
				'business_name'    => $this->business_name,
				'city'             => $this->city,
				'region'           => $this->region,
				'country'          => $this->country,
				'industry'         => $this->industry,
				'brand_voice'      => ! empty( $context['brand_voice'] ) ? $context['brand_voice'] : ( $this->brand_voice ?: 'neutral' ),
				'unique_value'     => substr( $this->uvp, 0, 160 ),
				'service_keywords' => array_values( array_filter( (array) ( $context['service_keywords'] ?? array() ) ) ),
			),
			'page_context' => array(
				'page_title'    => $context['page_title'] ?? '',
				'focus_keyword' => $context['focus_keyword'] ?? '',
				'page_role'     => $context['page_role'] ?? '',
			),
			'policy' => array(
				'context_type'         => $policy_context_type,
				'context_set_manually' => ! empty( $context['context_set_manually'] ),
				'brand_name_visible'   => ! empty( $context['brand_name_visible'] ),
				'seo_mode'             => ! empty( $context['seo_mode'] ),
				'loc_mode'             => isset( $context['loc_mode'] ) ? sanitize_key( $context['loc_mode'] ) : 'auto',
			),
		);

		$metadata = MSH_NonAI_Composer::compose( $input );

		if ( ! empty( $metadata['title'] ) ) {
			$metadata['title'] = $this->ensure_unique_title( $metadata['title'], $context['attachment_id'] ?? 0 );
		}

		foreach ( $metadata as $key => $value ) {
			if ( is_string( $value ) ) {
				$metadata[ $key ] = $this->clean_text( $value );
			}
		}

		return $metadata;
	}

	/**
	 * Extract meaningful keywords from original filename
	 */
	private function extract_filename_keywords( $filename ) {
		if ( empty( $filename ) ) {
			return '';
		}

		$basename = strtolower( pathinfo( $filename, PATHINFO_FILENAME ) );
		if ( $this->looks_like_camera_filename( $basename ) ) {
			return '';
		}

		// FIRST: Detect source patterns (Noun Project, etc.)
		$source_pattern = $this->detect_source_pattern( $filename );
		if ( $source_pattern ) {
			$this->log_debug( "MSH Source Pattern: File='$filename', Source='{$source_pattern['source']}', Term='{$source_pattern['extracted_term']}'" );
			return $this->normalize_extracted_term( $source_pattern['extracted_term'] );
		}

		// SECOND: Handle Main Street Health branded files
		if ( strpos( $filename, 'main-street-health-healthcare-' ) === 0 ) {
			$service_part = str_replace( 'main-street-health-healthcare-', '', $filename );
			$service_part = preg_replace( '/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $service_part );

			$this->log_debug( "MSH Branded File: Original='$filename', ServicePart='$service_part'" );

			// Extract the service keywords and normalize to key service terms
			if ( strpos( $service_part, 'cardiovascular' ) !== false ) {
				return 'cardiovascular-health-testing';
			} elseif ( strpos( $service_part, 'massage-therapy' ) !== false || strpos( $service_part, 'professional-massage' ) !== false ) {
				return 'professional-massage-therapy';
			} elseif ( strpos( $service_part, 'chiropractic' ) !== false ) {
				return 'chiropractic-adjustment-therapy';
			} elseif ( strpos( $service_part, 'concussion' ) !== false ) {
				return 'concussion-assessment-testing';
			} elseif ( strpos( $service_part, 'acupuncture' ) !== false ) {
				return 'acupuncture-pain-relief';
			} else {
				// Use first 2-3 meaningful words, excluding 'equipment', 'services', etc.
				$words            = explode( '-', $service_part );
				$meaningful_words = array();
				$skip_words       = array( 'equipment', 'services', 'techniques', 'and', 'for', 'the', 'with' );

				foreach ( $words as $word ) {
					if ( strlen( $word ) > 2 && ! in_array( $word, $skip_words ) ) {
						$meaningful_words[] = $word;
						if ( count( $meaningful_words ) >= 3 ) {
							break;
						}
					}
				}
				return implode( '-', $meaningful_words );
			}
		}

		// FALLBACK: Standard processing
		$cleaned = preg_replace( '/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $filename );
		$cleaned = preg_replace( '/^(noun-|icon-|img-|image-)/i', '', $cleaned );

		// Remove color codes and IDs
		$cleaned = preg_replace( '/-[A-F0-9]{6,8}(-|$)/i', '-', $cleaned );
		$cleaned = preg_replace( '/-\d{4,}(-|$)/', '-', $cleaned );

		// Split on separators and clean
		$parts            = preg_split( '/[-_\s]+/', $cleaned );
		$meaningful_parts = array();

		// Filter out noise words and keep meaningful terms (don't remove svg - it's meaningful)
		$noise_words = array( 'icon', 'image', 'img', 'pic', 'photo', 'vector', 'png', 'jpg', 'alignment' );

		foreach ( $parts as $part ) {
			$part = strtolower( trim( $part ) );

			if ( preg_match( '/^\d+x\d+$/', $part ) ) {
				continue;
			}

			if ( preg_match( '/^\d+$/', $part ) ) {
				continue;
			}

			if ( strlen( $part ) > 2 && ! in_array( $part, $noise_words ) && ! is_numeric( $part ) ) {
				$meaningful_parts[] = $part;
			}
		}

		// Healthcare-specific keyword mapping (enhanced with variations)
		$healthcare_keywords = array(
			'compression-stocking' => array( 'compression stocking', 'compression sock', 'support stocking' ),
			'orthopedic-pillow'    => array( 'orthopedic pillow', 'ortho pillow', 'cervical pillow' ),
			'wrist-guards'         => array( 'wristguards', 'wrist guards', 'wrist guard' ),
			'knee-brace'           => array( 'knee brace', 'knee support' ),
			'support-brace'        => array( 'support brace', 'support' ),
			'ankle-brace'          => array( 'ankle brace', 'ankle support' ),
			'wrist-brace'          => array( 'wrist brace', 'wrist support' ),
			'back-brace'           => array( 'back brace', 'back support' ),
			'neck-brace'           => array( 'neck brace', 'neck support' ),
			'crutches'             => array( 'crutches', 'crutch' ),
			'walker'               => array( 'walker', 'walking aid' ),
			'wheelchair'           => array( 'wheelchair', 'wheel chair' ),
			'walking-cane'         => array( 'cane', 'walking cane' ),
			'exercise-band'        => array( 'exercise band', 'resistance band' ),
			'foam-roller'          => array( 'foam roller' ),
			'heat-pack'            => array( 'heat pack', 'heating pad' ),
			'ice-pack'             => array( 'ice pack', 'cold pack' ),
			'kinesiology-tape'     => array( 'kinesiology tape', 'ktape', 'k tape' ),
			'posture-corrector'    => array( 'posture corrector' ),
			'therapy-ball'         => array( 'therapy ball', 'exercise ball' ),
			'tens-unit'            => array( 'tens unit', 'tens' ),
			'ultrasound'           => array( 'ultrasound' ),
			'massage-table'        => array( 'massage table' ),
			'treatment-table'      => array( 'treatment table' ),
			'lumbar-support'       => array( 'lumbar support' ),
			'ergonomic-cushion'    => array( 'ergonomic cushion' ),
			'balance-pad'          => array( 'balance pad' ),
			'stability-ball'       => array( 'stability ball' ),
			'medicine-ball'        => array( 'medicine ball' ),
			'theraband'            => array( 'theraband', 'thera band' ),
			'gel-pack'             => array( 'gel pack' ),
		);

		// Check for healthcare keyword combinations
		$text = implode( ' ', $meaningful_parts );

		// Debug logging
		$this->log_debug( "MSH Filename Debug: Original='$filename', Cleaned='$cleaned', Parts=" . implode( '|', $meaningful_parts ) . ", Text='$text'" );

		// FIRST: Try consecutive compound matching for multi-word terms
		$parts_string = implode( ' ', $meaningful_parts );
		foreach ( $healthcare_keywords as $keyword => $variations ) {
			foreach ( $variations as $variation ) {
				// Check for consecutive sequence in the parts string
				if ( strpos( $parts_string, strtolower( $variation ) ) !== false ) {
					$this->log_debug( "MSH Filename Debug: Found consecutive compound match '$variation' -> '$keyword'" );
					return $keyword;
				}
			}
		}

		// SECOND: Try single word matching
		foreach ( $healthcare_keywords as $keyword => $variations ) {
			foreach ( $variations as $variation ) {
				if ( strpos( $text, $variation ) !== false ) {
					$this->log_debug( "MSH Filename Debug: Found text match '$variation' -> '$keyword'" );
					return $keyword;
				}
			}
		}

		// THIRD: Try direct part matching
		foreach ( $healthcare_keywords as $keyword => $variations ) {
			foreach ( $variations as $variation ) {
				foreach ( $meaningful_parts as $part ) {
					if ( $part === $variation ) {
						$this->log_debug( "MSH Filename Debug: Direct part match '$part' -> '$keyword'" );
						return $keyword;
					}
				}
			}
		}

		// Return best meaningful parts (max 3)
		return implode( '-', array_slice( $meaningful_parts, 0, 3 ) );
	}

	private function extract_business_slug_keywords( array $context, $limit = 3 ) {
		$candidates = array();

		if ( ! empty( $context['attachment_title'] ) ) {
			$candidates[] = $context['attachment_title'];
		}

		if ( ! empty( $context['attachment_slug'] ) ) {
			$candidates[] = $context['attachment_slug'];
		}

		if ( ! empty( $context['file_basename'] ) ) {
			$candidates[] = $context['file_basename'];
		}

		if ( ! empty( $context['original_filename'] ) ) {
			$candidates[] = $context['original_filename'];
		}

		if ( ! empty( $context['page_title'] ) ) {
			$candidates[] = $context['page_title'];
		}

		if ( ! empty( $context['tags'] ) && is_array( $context['tags'] ) ) {
			$candidates = array_merge( $candidates, $context['tags'] );
		}

		$stopwords = array(
			'rehabilitation',
			'physiotherapy',
			'rehabilitation-physiotherapy',
			'physiotherapy-rehabilitation',
			'therapy',
			'clinic',
			'medical',
			'health',
			'wellness',
			'service',
			'services',
			'team',
			'member',
			'testimonial',
			'patient',
			'customer',
			'client',
			'business',
			'branding',
			'brand',
			'graphic',
			'icon',
			'image',
			'photo',
			'picture',
			'stock',
			'default',
			'placeholder',
			'jpeg',
			'jpg',
			'png',
			'svg',
			'gif',
			'rehab',
			'portfolio',
			'marketing',
			'agency',
			'creative',
			'post',
			'format',
			'gallery',
			'case',
			'slider',
			'mobile',
			'featured',
			'alignment',
			'markup',
			'main',
			'street',
			'hamilton',
		);

		if ( ! empty( $this->business_name ) ) {
			$stopwords = array_merge( $stopwords, $this->tokenize_stopwords( $this->business_name ) );
		}

		if ( ! empty( $this->location ) ) {
			$stopwords = array_merge( $stopwords, $this->tokenize_stopwords( $this->location ) );
		}

		if ( ! empty( $this->industry_label ) ) {
			$stopwords = array_merge( $stopwords, $this->tokenize_stopwords( $this->industry_label ) );
		}

		$stopwords = array_unique( array_filter( $stopwords ) );

		$keywords = array();
		foreach ( $candidates as $candidate ) {
			if ( ! is_scalar( $candidate ) || empty( $candidate ) ) {
				continue;
			}

			$value    = (string) $candidate;
			$basename = strtolower( pathinfo( $value, PATHINFO_FILENAME ) );
			if ( $this->looks_like_camera_filename( $basename ) ) {
				continue;
			}

			$slug  = strtolower( $value );
			$slug  = str_replace( array( '_', '/' ), '-', $slug );
			$slug  = preg_replace( '/[^a-z0-9\-]+/', '-', $slug );
			$parts = array_filter( explode( '-', $slug ) );

			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( $part === '' || is_numeric( $part ) || strlen( $part ) < 3 ) {
					continue;
				}

				if ( in_array( $part, $stopwords, true ) ) {
					continue;
				}

				if ( ! in_array( $part, $keywords, true ) ) {
					$keywords[] = $part;
				}

				if ( count( $keywords ) >= $limit ) {
					break 2;
				}
			}
		}

		if ( empty( $keywords ) ) {
			$fallback = array( 'asset' );
			if ( ! empty( $context['attachment_id'] ) ) {
				$fallback[] = (string) $context['attachment_id'];
			}
			if ( ! empty( $this->location_slug ) ) {
				$fallback[] = $this->location_slug;
			}

			return implode( '-', array_filter( $fallback ) );
		}

		return implode( '-', array_slice( $keywords, 0, $limit ) );
	}

	private function tokenize_stopwords( $source ) {
		$tokens = array();
		$source = strtolower( (string) $source );
		$source = str_replace( array( '_', '/' ), ' ', $source );
		$source = preg_replace( '/[^a-z0-9\s]/', ' ', $source );
		foreach ( preg_split( '/\s+/', $source ) as $part ) {
			$part = trim( $part );
			if ( $part !== '' ) {
				$tokens[] = $part;
			}
		}

		return $tokens;
	}

	private function collect_visual_keywords( array $context, $limit = 5 ) {
		$sources = array();

		if ( ! empty( $context['attachment_title'] ) ) {
			$sources[] = $context['attachment_title'];
		}

		if ( ! empty( $context['page_title'] ) ) {
			$sources[] = $context['page_title'];
		}

		// REMOVED: file_basename to prevent recursive duplication from current filename
		// The current filename should never influence the new filename suggestion
		// Only use metadata (title, alt, caption) and page context

		if ( ! empty( $context['tags'] ) && is_array( $context['tags'] ) ) {
			foreach ( $context['tags'] as $tag ) {
				$sources[] = str_replace( array( '-', '_' ), ' ', $tag );
			}
		}

		$stopwords = array(
			'rehabilitation',
			'physiotherapy',
			'treatment',
			'clinic',
			'medical',
			'health',
			'wellness',
			'service',
			'services',
			'agency',
			'marketing',
			'creative',
			'brand',
			'branding',
			'business',
			'default',
			'placeholder',
			'image',
			'photo',
			'picture',
			'stock',
			'jpg',
			'jpeg',
			'png',
			'svg',
			'gif',
			'file',
			'asset',
			'main',
			'street',
			'health',
			'post',
			'format',
			'gallery',
			'case',
			'slider',
			'mobile',
			'featured',
			'alignment',
			'markup',
			'classic',
		);

		if ( ! empty( $this->business_name ) ) {
			$stopwords = array_merge( $stopwords, $this->tokenize_stopwords( $this->business_name ) );
		}

		if ( ! empty( $this->location ) ) {
			$stopwords = array_merge( $stopwords, $this->tokenize_stopwords( $this->location ) );
		}

		$stopwords = array_unique( array_filter( $stopwords ) );

		$keywords = array();

		foreach ( $sources as $source ) {
			$clean = strtolower( (string) $source );
			$clean = preg_replace( '/[^a-z0-9\s]/', ' ', $clean );
			$parts = preg_split( '/\s+/', $clean );

			foreach ( $parts as $part ) {
				$part = trim( $part );
				if ( $part === '' || strlen( $part ) < 3 || is_numeric( $part ) ) {
					continue;
				}

				if ( preg_match( '/^\d+x\d+$/', $part ) ) {
					continue;
				}

				if ( in_array( $part, $stopwords, true ) ) {
					continue;
				}

				if ( ! in_array( $part, $keywords, true ) ) {
					$keywords[] = $part;
				}

				if ( count( $keywords ) >= $limit ) {
					break 2;
				}
			}
		}

		return $keywords;
	}

	private function build_business_descriptor_details( array $context ) {
		$keywords = $this->collect_visual_keywords( $context, 5 );

		$filters = array(
			'post',
			'format',
			'formats',
			'gallery',
			'case',
			'slider',
			'mobile',
			'featured',
			'default',
			'branding',
			'brand',
			'asset',
			'graphic',
			'classic',
			'marking',
			'markup',
			'alignment',
			'logo',
			'template',
			'study',
		);

		if ( $this->city_slug !== '' ) {
			$filters[] = $this->city_slug;
		}

		if ( $this->location_slug !== '' ) {
			$filters[] = $this->location_slug;
			$filters   = array_merge( $filters, explode( '-', $this->location_slug ) );
		}

		if ( ( $this->city_slug !== '' && $this->city_slug !== 'hamilton' ) || ( $this->location_slug !== '' && $this->location_slug !== 'hamilton' ) ) {
			$filters[] = 'hamilton';
		}

		if ( ! MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ) {
			$filters = array_merge( $filters, array( 'rehabilitation', 'physiotherapy', 'therapy', 'clinical', 'treatment' ) );
		}

		if ( ! empty( $this->business_name ) ) {
			$filters = array_merge( $filters, $this->tokenize_stopwords( $this->business_name ) );
		}

		if ( ! empty( $this->location ) ) {
			$filters = array_merge( $filters, $this->tokenize_stopwords( $this->location ) );
		}

		$filters = array_unique( array_filter( array_map( 'strtolower', $filters ) ) );

		$filtered_keywords = array();
		foreach ( $keywords as $keyword ) {
			$keyword = (string) $keyword;
			if ( $keyword === '' ) {
				continue;
			}

			$normalized = strtolower( $keyword );

			if ( $this->looks_like_camera_filename( $keyword ) ) {
				continue;
			}

			if ( in_array( $normalized, $filters, true ) ) {
				continue;
			}

			if ( $this->is_generic_descriptor( $keyword ) ) {
				continue;
			}

			$sanitized = $this->sanitize_descriptor( $keyword );
			if ( $sanitized === '' ) {
				continue;
			}

			if ( $this->is_generic_descriptor( $sanitized ) ) {
				continue;
			}

			$filtered_keywords[] = $sanitized;
		}

		$filtered_keywords = array_values( $filtered_keywords );

		if ( ! empty( $filtered_keywords ) ) {
			$tokens           = array_slice( $filtered_keywords, 0, 2 );
			$descriptor_label = $this->format_descriptor_label( $tokens );
			$descriptor_slug  = $this->limit_slug_parts( MSH_Image_Optimizer_Context_Helper::slugify( implode( ' ', $tokens ) ), 2 );
		} else {
			$descriptor_label = $this->derive_business_descriptor_fallback_label( $context );
			$descriptor_slug  = $this->limit_slug_parts( MSH_Image_Optimizer_Context_Helper::slugify( $descriptor_label ), 2 );
		}

		if ( ! MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $this->industry ) ) {
			$descriptor_slug  = $this->strip_healthcare_terms_from_slug( $descriptor_slug );
			$descriptor_label = $this->strip_healthcare_terms_from_text( $descriptor_label );
		}

		$descriptor_label = $this->normalize_descriptor_text( $descriptor_label );

		if ( $descriptor_slug === '' ) {
			if ( $this->business_name !== '' ) {
				$descriptor_slug = $this->limit_slug_parts( MSH_Image_Optimizer_Context_Helper::slugify( $this->business_name ), 1 );
			} else {
				$descriptor_slug = 'brand';
			}
		}

		if ( $descriptor_label === '' ) {
			$descriptor_label = $this->business_name !== ''
				? $this->business_name
				: __( 'Brand Visual', 'msh-image-optimizer' );
		}

		return array(
			'slug'  => $descriptor_slug,
			'label' => $descriptor_label,
		);
	}

	private function derive_business_descriptor_fallback_label( array $context ) {
		$label = '';

		if ( ! empty( $context['asset'] ) ) {
			switch ( $context['asset'] ) {
				case 'logo':
					$label = __( 'Brand Logo', 'msh-image-optimizer' );
					break;
				case 'team':
					$label = __( 'Team Portrait', 'msh-image-optimizer' );
					break;
				case 'facility':
					$label = __( 'Workspace Interior', 'msh-image-optimizer' );
					break;
				case 'product':
					$label = __( 'Product Showcase', 'msh-image-optimizer' );
					break;
				case 'graphic':
					$label = __( 'Brand Graphic', 'msh-image-optimizer' );
					break;
				case 'service-icon':
					$label = __( 'Service Icon', 'msh-image-optimizer' );
					break;
			}
		}

		if ( $label === '' && ! empty( $context['tags'] ) && is_array( $context['tags'] ) ) {
			$lower_tags = array_map( 'strtolower', $context['tags'] );
			if ( in_array( 'classic', $lower_tags, true ) ) {
				$label = __( 'Classic Gallery', 'msh-image-optimizer' );
			}

			if ( $label === '' && in_array( 'post-formats', $lower_tags, true ) ) {
				$label = __( 'Creative Gallery', 'msh-image-optimizer' );
			}

			if ( $label === '' && in_array( 'team', $lower_tags, true ) ) {
				$label = __( 'Team Portrait', 'msh-image-optimizer' );
			}
		}

		if ( $label === '' ) {
			$page_title = strtolower( $context['page_title'] ?? '' );
			if ( strpos( $page_title, 'gallery' ) !== false || strpos( $page_title, 'portfolio' ) !== false ) {
				$label = __( 'Brand Gallery', 'msh-image-optimizer' );
			}
		}

		if ( $label === '' ) {
			$attachment_title = strtolower( $context['attachment_title'] ?? '' );
			if ( strpos( $attachment_title, 'slider' ) !== false ) {
				$label = __( 'Brand Slider', 'msh-image-optimizer' );
			}
		}

		if ( $label === '' && $this->business_name !== '' ) {
			$label = sprintf( __( '%s Brand', 'msh-image-optimizer' ), $this->business_name );
		}

		if ( $label === '' || $this->is_generic_descriptor( $label ) ) {
			$label = __( 'Brand Imagery', 'msh-image-optimizer' );
		}

		return $label;
	}

	private function derive_visual_descriptor( array $context ) {
		if ( isset( $context['type'] ) && $context['type'] === 'business' ) {
			$details = $this->build_business_descriptor_details( $context );
			if ( ! empty( $details['label'] ) ) {
				return $details['label'];
			}
		}

		$keywords   = $this->collect_visual_keywords( $context, 4 );
		$descriptor = '';

		if ( ! empty( $keywords ) ) {
			$descriptor = implode( ' ', array_slice( $keywords, 0, 3 ) );
			$descriptor = ucwords( $descriptor );
		}

		$asset_map = array(
			'graphic'      => __( 'Brand Graphic', 'msh-image-optimizer' ),
			'product'      => __( 'Product Photo', 'msh-image-optimizer' ),
			'logo'         => __( 'Logo Mark', 'msh-image-optimizer' ),
			'team'         => __( 'Team Portrait', 'msh-image-optimizer' ),
			'facility'     => __( 'Workspace Interior', 'msh-image-optimizer' ),
			'service-icon' => __( 'Service Icon', 'msh-image-optimizer' ),
		);

		if ( $descriptor === '' && ! empty( $context['asset'] ) && isset( $asset_map[ $context['asset'] ] ) ) {
			$descriptor = $asset_map[ $context['asset'] ];
		} elseif ( $descriptor !== '' && ! empty( $context['asset'] ) && isset( $asset_map[ $context['asset'] ] ) ) {
			$suffix = $asset_map[ $context['asset'] ];
			if ( stripos( $descriptor, $suffix ) === false ) {
				$descriptor = $descriptor . ' ' . $suffix;
			}
		}

		if ( $descriptor === '' && ! empty( $context['service'] ) ) {
			$descriptor = $this->humanize_label( $context['service'] );
		}

		if ( $descriptor === '' && ! empty( $context['attachment_id'] ) ) {
			$descriptor = sprintf( __( 'Asset %d', 'msh-image-optimizer' ), $context['attachment_id'] );
		}

		return $descriptor;
	}

	private function format_descriptor_label( array $tokens ) {
		$words = array();
		foreach ( $tokens as $token ) {
			$token = strtolower( str_replace( array( '-', '_' ), ' ', (string) $token ) );
			foreach ( preg_split( '/\s+/', $token ) as $part ) {
				$part = trim( $part );
				if ( $part === '' ) {
					continue;
				}

				if ( $this->looks_like_camera_filename( $part ) ) {
					continue;
				}

				$words[] = $this->normalize_descriptor_word( $part );
			}
		}

		return $this->normalize_descriptor_text( implode( ' ', $words ) );
	}

	private function normalize_descriptor_word( $word ) {
		if ( $word === '' ) {
			return '';
		}

		if ( function_exists( 'mb_convert_case' ) ) {
			return mb_convert_case( $word, MB_CASE_TITLE, 'UTF-8' );
		}

		return ucwords( $word );
	}

	private function normalize_descriptor_text( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

		return $text;
	}

	private function strip_healthcare_terms_from_text( $text ) {
		if ( $text === '' ) {
			return '';
		}

		$cleaned = preg_replace( '/\b(Rehabilitation|Physiotherapy|Therapy|Clinical|Treatment)\b/i', '', $text );

		return $this->normalize_descriptor_text( $cleaned );
	}

	private function limit_slug_parts( $slug, $limit = 2 ) {
		$slug = (string) $slug;
		if ( $slug === '' ) {
			return '';
		}

		$segments = array_filter( explode( '-', $slug ), 'strlen' );
		if ( empty( $segments ) ) {
			return '';
		}

		return implode( '-', array_slice( $segments, 0, max( 1, (int) $limit ) ) );
	}

	private function dedupe_slug_components( array $components ) {
		$normalized = array();
		$result     = array();

		foreach ( $components as $component ) {
			$component = (string) $component;
			if ( $component === '' ) {
				continue;
			}

			$key = $component;
			if ( ! in_array( $key, $normalized, true ) ) {
				$normalized[] = $key;
				$result[]     = $component;
			}
		}

		return $result;
	}

	private function dedupe_slug_tokens( array $tokens ) {
		$deduped = array();
		$roots   = array();

		foreach ( $tokens as $token ) {
			$token = (string) $token;
			if ( $token === '' ) {
				continue;
			}

			if ( preg_match( '/^\d+(x\d+)?$/', $token ) ) {
				continue;
			}

			$root = preg_replace( '/\d+$/', '', $token );
			if ( $root === '' ) {
				$root = $token;
			}

			if ( isset( $roots[ $root ] ) ) {
				continue;
			}

			$roots[ $root ] = true;
			$deduped[]      = $token;
		}

		return $deduped;
	}

	private function assemble_slug( array $components, $max_length = 50 ) {
		$components = $this->dedupe_slug_components( array_filter( $components, 'strlen' ) );

		if ( empty( $components ) ) {
			return 'brand';
		}

		$slug = MSH_Image_Optimizer_Context_Helper::slugify( implode( '-', $components ) );

		if ( $slug !== '' ) {
			$parts = array_filter( explode( '-', $slug ), 'strlen' );
			$parts = $this->dedupe_slug_tokens( $parts );
			$slug  = implode( '-', $parts );
		}

		while ( strlen( $slug ) > $max_length && count( $components ) > 1 ) {
			array_pop( $components );
			$slug = MSH_Image_Optimizer_Context_Helper::slugify( implode( '-', $components ) );
			if ( $slug !== '' ) {
				$parts = array_filter( explode( '-', $slug ), 'strlen' );
				$parts = $this->dedupe_slug_tokens( $parts );
				$slug  = implode( '-', $parts );
			}
		}

		if ( strlen( $slug ) > $max_length ) {
			$slug = $this->limit_slug_parts( $slug, 3 );
		}

		return $slug;
	}

	private function log_debug( $message ) {
		if ( ! apply_filters( 'msh_image_optimizer_enable_debug', false ) ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}

		error_log( '[MSH Image Optimizer] ' . $message );
	}

	private function should_default_location_context() {
		if ( ! $this->is_in_person_business() ) {
			return false;
		}

		if ( $this->location !== '' ) {
			return true;
		}

		if ( ! empty( $this->active_context['service_area'] ) ) {
			return true;
		}

		return false;
	}

	private function should_include_location_in_slug( array $context ) {
		if ( $this->location_slug === '' ) {
			return false;
		}

		$is_in_person = $this->is_in_person_business();

		if ( ! empty( $context['seo_mode'] ) ) {
			return true;
		}

		if ( ! $is_in_person ) {
			return false;
		}

		$asset = strtolower( $context['asset'] ?? '' );
		if ( in_array( $asset, array( 'team', 'facility', 'office', 'workspace' ), true ) ) {
			return true;
		}

		$context_type = strtolower( $context['type'] ?? '' );
		if ( in_array( $context_type, array( 'team', 'facility' ), true ) ) {
			return true;
		}

		$page_title        = strtolower( $context['page_title'] ?? '' );
		$location_keywords = array( 'contact', 'location', 'locations', 'office', 'clinic', 'studio', 'visit', 'tour', 'about', 'team' );
		foreach ( $location_keywords as $keyword ) {
			if ( $keyword !== '' && strpos( $page_title, $keyword ) !== false ) {
				return true;
			}
		}

		if ( ! empty( $context['tags'] ) && is_array( $context['tags'] ) ) {
			foreach ( $context['tags'] as $tag ) {
				$tag = strtolower( (string) $tag );
				if ( $tag === '' ) {
					continue;
				}

				if ( strpos( $tag, $this->city_slug ) !== false || strpos( $tag, $this->location_slug ) !== false ) {
					return true;
				}

				if ( in_array( $tag, array( 'team', 'office', 'clinic', 'facility', 'location' ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function should_include_business_name( array $context, $descriptor_slug ) {
		if ( $this->business_name === '' ) {
			return false;
		}

		$brand_slug = MSH_Image_Optimizer_Context_Helper::slugify( $this->business_name );
		if ( $descriptor_slug !== '' && strpos( $descriptor_slug, $brand_slug ) !== false ) {
			return false;
		}

		$asset = strtolower( $context['asset'] ?? '' );
		if ( in_array( $asset, array( 'logo', 'graphic', 'product', 'service-icon', 'team', 'facility' ), true ) ) {
			return true;
		}

		$context_type = strtolower( $context['type'] ?? '' );
		if ( in_array( $context_type, array( 'team', 'facility' ), true ) ) {
			return true;
		}

		$page_title     = strtolower( $context['page_title'] ?? '' );
		$brand_keywords = array( 'brand', 'branding', 'agency', 'studio', 'company', 'about', 'team', 'profile' );
		foreach ( $brand_keywords as $keyword ) {
			if ( $keyword !== '' && strpos( $page_title, $keyword ) !== false ) {
				return true;
			}
		}

		if ( ! empty( $context['tags'] ) && is_array( $context['tags'] ) ) {
			foreach ( $context['tags'] as $tag ) {
				$tag = strtolower( (string) $tag );
				if ( in_array( $tag, array( 'brand', 'branding', 'agency', 'company', 'team' ), true ) ) {
					return true;
				}
			}
		}

		if ( $this->should_include_location_in_slug( $context ) ) {
			return true;
		}

		if ( $descriptor_slug === '' ) {
			return true;
		}

		return false;
	}

	private function is_in_person_business() {
		$type = strtolower( $this->business_type );

		if ( $type === '' ) {
			return $this->city_slug !== '' || $this->location_slug !== '';
		}

		$in_person_types = array( 'local_service' );
		$remote_types    = array( 'online_service', 'ecommerce', 'saas' );

		if ( in_array( $type, $in_person_types, true ) ) {
			return true;
		}

		if ( in_array( $type, $remote_types, true ) ) {
			return false;
		}

		return $this->city_slug !== '' || $this->location_slug !== '';
	}

	private function contains_healthcare_terms( $value ) {
		$value = (string) $value;
		if ( $value === '' ) {
			return false;
		}

		return preg_match( '/\b(rehabilitation|physiotherapy|therapy|clinical|treatment)\b/', $value ) === 1;
	}

	private function strip_healthcare_terms_from_slug( $slug ) {
		if ( $slug === '' ) {
			return '';
		}

		$parts = array_filter(
			explode( '-', $slug ),
			function ( $part ) {
				return ! $this->contains_healthcare_terms( $part );
			}
		);

		return implode( '-', $parts );
	}

	private function get_asset_slug_component( array $context, $descriptor_slug = '' ) {
		if ( empty( $context['asset'] ) ) {
			return '';
		}

		$descriptor_slug = (string) $descriptor_slug;

		switch ( $context['asset'] ) {
			case 'logo':
				if ( $descriptor_slug !== '' && strpos( $descriptor_slug, 'logo' ) !== false ) {
					return '';
				}
				return 'logo';
			case 'team':
				if ( $descriptor_slug !== '' && strpos( $descriptor_slug, 'team' ) !== false ) {
					return '';
				}
				return 'team';
			case 'product':
				if ( $descriptor_slug !== '' && strpos( $descriptor_slug, 'product' ) !== false ) {
					return '';
				}
				return 'product';
			case 'facility':
				if ( $descriptor_slug !== '' && ( strpos( $descriptor_slug, 'workspace' ) !== false || strpos( $descriptor_slug, 'facility' ) !== false ) ) {
					return '';
				}
				return 'workspace';
			case 'service-icon':
				if ( $descriptor_slug !== '' && strpos( $descriptor_slug, 'icon' ) !== false ) {
					return '';
				}
				return 'icon';
			case 'graphic':
				if ( $descriptor_slug !== '' && strpos( $descriptor_slug, 'graphic' ) !== false ) {
					return '';
				}
				return 'graphic';
			default:
				return '';
		}
	}

	private function sanitize_business_slug_components( array $components ) {
		$normalized = array();

		foreach ( $components as $component ) {
			$component = MSH_Image_Optimizer_Context_Helper::slugify( (string) $component );
			if ( $component === '' ) {
				continue;
			}

			if ( $this->looks_like_camera_filename( $component ) ) {
				continue;
			}

			if ( preg_match( '/^(?:dsc|img|pict|sam|casio)[-_0-9]+$/', $component ) ) {
				continue;
			}

			if ( ! in_array( $component, $normalized, true ) ) {
				$normalized[] = $component;
			}
		}

		return $normalized;
	}

	private function extract_camera_sequence_suffix( $primary, $fallback = '' ) {
		$candidates = array( $primary, $fallback );

		foreach ( $candidates as $candidate ) {
			$candidate = strtolower( (string) $candidate );
			if ( $candidate === '' ) {
				continue;
			}

			$candidate = preg_replace( '/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $candidate );

			if ( ! preg_match( '/^(?:dsc|dcp|dscn|dscf|img|img_|mvc|pict|dcim|_mg|cimg|lrg_|p\d{7}|cep|cap|casio|sam_)[-_0-9]+$/', $candidate ) ) {
				continue;
			}

			if ( preg_match( '/(?:^|[_-])(\d{3,})$/', $candidate, $matches ) ) {
				$suffix = ltrim( $matches[1], '0' );
				return $suffix !== '' ? $suffix : $matches[1];
			}
		}

		return '';
	}

	private function extract_primary_location_token( $slug ) {
		if ( $slug === '' ) {
			return '';
		}

		$segments = array_filter( explode( '-', $slug ), 'strlen' );
		if ( empty( $segments ) ) {
			return '';
		}

		return $segments[0];
	}

	private function looks_like_camera_filename( $value ) {
		$value = strtolower( (string) $value );
		if ( $value === '' ) {
			return false;
		}

		if ( preg_match( '/^(?:dsc|dcp|dscn|dscf|img|img_|mvc|pict|dcim|_mg|cimg|lrg_|p\d{7}|cep|cap|casio|sam_)[-_0-9]*$/', $value ) ) {
			return true;
		}

		return preg_match( '/^[a-z]{2,4}[-_0-9]*\d{4,}$/', $value ) === 1;
	}

	/**
	 * Extract brand/insurance company names from filename
	 */
	private function extract_brand_keywords( $filename ) {
		if ( empty( $filename ) ) {
			return '';
		}

		// Remove file extension and clean
		$cleaned = preg_replace( '/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $filename );
		$cleaned = preg_replace( '/[-_](logo|nobars?)$/i', '', $cleaned );

		// Common insurance/healthcare brands
		$brand_mapping = array(
			'bluecross'     => array( 'bluecross', 'blue-cross' ),
			'manulife'      => array( 'manulife' ),
			'sunlife'       => array( 'sunlife', 'sun-life' ),
			'greenshield'   => array( 'greenshield', 'green-shield' ),
			'desjardins'    => array( 'desjardins' ),
			'chambers'      => array( 'chambers' ),
			'benefits'      => array( 'benefits' ),
			'wsib'          => array( 'wsib' ),
			'mvp'           => array( 'mvp' ),
			'rbc'           => array( 'rbc' ),
			'td'            => array( 'td' ),
			'bmo'           => array( 'bmo' ),
			'scotia'        => array( 'scotia' ),
			'cigna'         => array( 'cigna' ),
			'johnson'       => array( 'johnson' ),
			'benefits-plan' => array( 'benefits', 'plan' ),
		);

		$text = strtolower( $cleaned );
		foreach ( $brand_mapping as $brand => $variations ) {
			foreach ( $variations as $variation ) {
				if ( strpos( $text, $variation ) !== false ) {
					return $brand;
				}
			}
		}

		// If no specific brand, return cleaned filename (max 2 words)
		$parts       = preg_split( '/[-_\s]+/', $cleaned );
		$clean_parts = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( strlen( $part ) > 1 && ! is_numeric( $part ) ) {
				$clean_parts[] = strtolower( $part );
			}
		}

		if ( empty( $clean_parts ) ) {
			return '';
		}

		$generic_terms = array(
			'rehabilitation',
			'physiotherapy',
			'therapy',
			'treatment',
			'clinical',
			'branding',
			'brand',
			'business',
			'default',
			'placeholder',
			'image',
			'featured',
			'photo',
			'graphic',
			'asset',
			'logo',
			'template',
			'study',
			'main',
			'street',
			'health',
			'hamilton',
			'sample',
			'demo',
		);

		$filtered = array_values(
			array_filter(
				$clean_parts,
				function ( $part ) use ( $generic_terms ) {
					return ! in_array( $part, $generic_terms, true );
				}
			)
		);

		if ( empty( $filtered ) ) {
			return '';
		}

		return implode( '-', array_slice( $filtered, 0, 2 ) );
	}

	/**
	 * Check if extracted keywords represent a high-quality name worth preserving
	 */
	private function is_high_quality_extracted_name( $extracted_keywords, $original_filename ) {
		$basename = strtolower( pathinfo( $original_filename, PATHINFO_FILENAME ) );
		if ( $this->looks_like_camera_filename( $extracted_keywords ) || $this->looks_like_camera_filename( $basename ) ) {
			return false;
		}

		if ( $extracted_keywords === '' ) {
			return false;
		}

		// High-quality indicators
		$quality_indicators = array(
			// Brand names
			'djp',
			'bionic',
			'footmaxx',
			'main-street-health',
			// Specific medical terms
			'gait-scan',
			'fullstop',
			'cardiovascular',
			'professional',
			'massage-therapy',
			// Equipment specifics
			'orthopedic-pillow',
			'compression-stocking',
			'wristguards',
			// MSH service-specific terms
			'cardiovascular-health-testing',
			'professional-massage-therapy',
			'chiropractic-adjustment-therapy',
			'concussion-assessment-testing',
			'acupuncture-pain-relief',
			// Equipment terms
			'bionic-therapy-device',
			'support-brace',
			'compression-stocking',
			// Descriptive terms
			'injury-care',
			'framed',
			'services',
		);

		foreach ( $quality_indicators as $indicator ) {
			if ( strpos( $extracted_keywords, $indicator ) !== false || strpos( $original_filename, $indicator ) !== false ) {
				return true;
			}
		}

		// If extracted keywords are longer than 2 words and contain specific terms
		$word_count = count( explode( '-', $extracted_keywords ) );
		if ( $word_count >= 3 ) {
			return true;
		}

		return false;
	}

	/**
	 * Detect common icon library patterns (Noun Project, etc.)
	 */
	private function detect_source_pattern( $filename ) {
		// Noun Project pattern: noun-compression-stocking-7981375-FFFFFF.svg (with optional suffixes)
		if ( preg_match( '/^noun-(.+)-\d{4,7}-[A-F0-9]{6}(?:-\d+)*/', $filename, $matches ) ) {
			return array(
				'source'         => 'noun_project',
				'extracted_term' => str_replace( '-', ' ', $matches[1] ),
			);
		}

		// Getty Images pattern: GettyImages-1343539369.png
		if ( preg_match( '/^gettyimages-(\d+)/i', $filename, $matches ) ) {
			return array(
				'source'         => 'getty_images',
				'extracted_term' => 'professional-stock-photo',
			);
		}

		// Professional equipment patterns: djp-bionic-fullstop-on-skin-1400x1400-1.jpg
		if ( preg_match( '/^([a-z]+)-([a-z]+)-([a-z-]+)-(\d{3,}x\d{3,}|on-\w+)/', $filename, $matches ) ) {
			return array(
				'source'         => 'professional_equipment',
				'extracted_term' => $matches[1] . ' ' . $matches[2] . ' ' . str_replace( '-', ' ', $matches[3] ),
			);
		}

		// Frame pattern: slide-footmaxx-gait-scan-framed.jpg (but skip generic Frame-123.png)
		if ( preg_match( '/^(frame|slide)-(.+)/i', $filename, $matches ) ) {
			$extracted_part = $matches[2];
			// Skip if it's just numbers and extension (like Frame-330.png -> 330.png)
			if ( ! preg_match( '/^\d+\.(jpg|jpeg|png|gif|svg|webp)$/i', $extracted_part ) ) {
				return array(
					'source'         => 'presentation_asset',
					'extracted_term' => str_replace( '-', ' ', $extracted_part ),
				);
			}
		}

		return null;
	}

	/**
	 * Normalize extracted terms to healthcare keywords
	 */
	private function normalize_extracted_term( $term ) {
		$term_lower = strtolower( $term );

		// FIRST: Remove file extensions if present
		$term_lower = preg_replace( '/\.(jpg|jpeg|png|gif|svg|webp)$/i', '', $term_lower );

		// Direct healthcare equipment mapping
		$equipment_mapping = array(
			'compression stocking'      => 'compression-stocking',
			'compression sock'          => 'compression-stocking',
			'support stocking'          => 'support-stocking',
			'bionic fullstop'           => 'bionic-therapy-device',
			'bionic fullstop on skin'   => 'bionic-therapy-device',
			'fullstop'                  => 'therapy-device',
			'orthopedic pillow'         => 'orthopedic-pillow',
			'brain'                     => 'brain-assessment',
			'central nervous system'    => 'nervous-system-assessment',
			'nervous system'            => 'nervous-system-assessment',
			'scoliosis'                 => 'scoliosis-assessment',
			'injury'                    => 'injury-assessment',
			'portable nerve stimulator' => 'nerve-stimulator-therapy',
			'nerve stimulator'          => 'nerve-stimulator-therapy',
			'wristguards'               => 'wrist-guards',
			'crutches'                  => 'crutches',
			'knee brace'                => 'knee-brace',
			'ankle brace'               => 'ankle-brace',
			'back brace'                => 'back-brace',
			'neck brace'                => 'neck-brace',
			'brace'                     => 'support-brace',
			'walker'                    => 'walker',
			'wheelchair'                => 'wheelchair',
			'cane'                      => 'walking-cane',
			'foam roller'               => 'foam-roller',
			'therapy ball'              => 'therapy-ball',
			'balance pad'               => 'balance-pad',
			'resistance band'           => 'resistance-band',
			'heating pad'               => 'heating-pad',
			'cold pack'                 => 'cold-pack',
			'gel pack'                  => 'gel-pack',
		);

		// Check for exact matches first
		if ( isset( $equipment_mapping[ $term_lower ] ) ) {
			return $equipment_mapping[ $term_lower ];
		}

		// Check for partial matches
		foreach ( $equipment_mapping as $source_term => $normalized ) {
			if ( strpos( $term_lower, $source_term ) !== false ) {
				return $normalized;
			}
		}

		// Fallback: clean up the term
		return str_replace( ' ', '-', $term_lower );
	}

	private function clean_text( $text ) {
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Phase 1F: Enable batch mode and cache active context + signature.
	 * Call this ONCE before batch loop to avoid repeated wp_options queries.
	 *
	 * @since 1.2.5
	 */
	public function msh_enable_batch_mode_with_cache() {
		$this->msh_batch_mode = true;

		// Hydrate context ONCE (this will trigger get_option calls)
		$context = $this->hydrate_active_context();

		// Cache it for the entire batch
		$this->msh_cached_context       = $context;
		$this->msh_batch_context_cached = true;

		// Phase 1F: Ensure signature is computed NOW so cache has it
		if ( empty( $this->context_signature ) && class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
			$this->context_signature = MSH_Image_Optimizer_Context_Helper::build_context_signature( $context );
		}

		// Cache the signature
		$this->msh_cached_signature = $this->context_signature;
	}

	/**
	 * Phase 1F: Disable batch mode and clear caches.
	 * Call this at batch end to prevent cache leaks.
	 *
	 * @since 1.2.5
	 */
	public function msh_disable_batch_mode() {
		$this->msh_batch_mode           = false;
		$this->msh_batch_context_cached = false;
		$this->msh_cached_context       = array();
		$this->msh_cached_signature     = '';
	}
}

class MSH_Image_Optimizer {
	private static $instance = null;

	const ANALYSIS_CACHE_VERSION = '4';

	private $batch_size            = 10;
	private $processed_count       = 0;
	private $current_attachment_id = null;
	private $contextual_meta_generator;

	// Phase 1F: Cached AI mode for batch optimization (avoid get_option during loop)
	private $msh_batch_ai_mode = null;

	private $healthcare_contexts = array(
		'homepage_hero' => array(
			'max_width'  => 1200,
			'max_height' => 600,
			'quality'    => 85,
		),
		'service_page'  => array(
			'max_width'  => 800,
			'max_height' => 600,
			'quality'    => 80,
		),
		'team_photo'    => array(
			'max_width'  => 400,
			'max_height' => 600,
			'quality'    => 85,
		),
		'blog_featured' => array(
			'max_width'  => 800,
			'max_height' => 450,
			'quality'    => 80,
		),
		'testimonial'   => array(
			'max_width'  => 200,
			'max_height' => 200,
			'quality'    => 75,
		),
		'facility'      => array(
			'max_width'  => 800,
			'max_height' => 600,
			'quality'    => 80,
		),
	);

	/**
	 * Allowed per-attachment location insertion modes.
	 *
	 * @var string[]
	 */
	private static $loc_mode_allowed = array( 'auto', 'force_caption', 'force_all', 'off' );

	/**
	 * Contexts that support force_all behaviour.
	 *
	 * @var string[]
	 */
	private static $loc_mode_force_all_contexts = array( 'facility', 'service-icon' );

	private function log_debug( $message ) {
		if ( ! apply_filters( 'msh_image_optimizer_enable_debug', false ) ) {
			return;
		}

		if ( is_array( $message ) || is_object( $message ) ) {
			$message = print_r( $message, true );
		}

		error_log( '[MSH Image Optimizer] ' . $message );
	}

	/**
	 * Check if file rename feature is enabled.
	 * Normalizes truthy values: '1', 'true', 'yes', 'on', 1, true all treated as enabled.
	 *
	 * @since 1.2.0
	 * @return bool True if file rename is enabled, false otherwise.
	 */
	private function is_file_rename_enabled() {
		$value = get_option( 'msh_enable_file_rename', '0' );

		// Normalize truthy values
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (int) $value === 1;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}

	/**
	 * Resolve the effective location insertion mode for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $final_context_type Final context type slug.
	 * @return string Normalised location mode.
	 */
	private function resolve_loc_mode( $attachment_id, $final_context_type ) {
		$stored = get_post_meta( $attachment_id, '_msh_loc_mode', true );
		$stored = is_string( $stored ) ? sanitize_key( $stored ) : '';
		if ( in_array( $stored, self::$loc_mode_allowed, true ) ) {
			if ( 'force_all' === $stored && ! in_array( $final_context_type, self::$loc_mode_force_all_contexts, true ) ) {
				return 'force_caption';
			}
			return $stored;
		}

		if ( in_array( $final_context_type, array( 'facility', 'team', 'equipment', 'service-icon' ), true ) ) {
			return 'force_caption';
		}

		return 'auto';
	}

	/**
	 * Sanitize incoming location mode values.
	 *
	 * @param string $loc_mode Raw location mode.
	 * @return string|false Normalised value or false if invalid.
	 */
	private function sanitize_loc_mode( $loc_mode ) {
		if ( ! is_string( $loc_mode ) || '' === $loc_mode ) {
			return false;
		}

		$loc_mode = sanitize_key( $loc_mode );
		if ( ! in_array( $loc_mode, self::$loc_mode_allowed, true ) ) {
			return false;
		}

		return $loc_mode;
	}

	/**
	 * Phase 1F: Get AI mode during batch optimization.
	 * Returns cached value if batch mode is active, otherwise queries wp_options.
	 *
	 * @since 1.2.5
	 * @return string AI mode setting ('manual', 'fill-empty', 'always-regenerate')
	 */
	private function get_batch_ai_mode() {
		// If batch mode is active and we have cached AI mode, return it
		if ( $this->msh_batch_ai_mode !== null ) {
			return $this->msh_batch_ai_mode;
		}

		// Otherwise query wp_options (non-batch context)
		return get_option( 'msh_ai_mode', 'manual' );
	}

	private function clear_analysis_cache() {
		$cache_key = 'msh_analysis_cache_v' . self::ANALYSIS_CACHE_VERSION . '_' . md5( 'latest_analysis' );
		delete_transient( $cache_key );
	}

	private function flag_attachment_for_reoptimization( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return;
		}

		$metadata_source = get_post_meta( $attachment_id, 'msh_metadata_source', true );

		delete_post_meta( $attachment_id, 'msh_optimized_date' );
		delete_post_meta( $attachment_id, 'msh_metadata_last_updated' );
		if ( $metadata_source !== 'manual_edit' ) {
			delete_post_meta( $attachment_id, 'msh_metadata_source' );
			delete_post_meta( $attachment_id, 'msh_metadata_context_hash' );
		}
		delete_post_meta( $attachment_id, '_msh_suggested_filename_context' );
		delete_post_meta( $attachment_id, '_msh_suggested_filename' ); // Clear stale suggestion
		update_post_meta( $attachment_id, 'msh_context_needs_refresh', '1' );
	}

	/**
	 * Flag every image attachment so context-driven metadata regenerates.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function mark_all_attachments_for_context_refresh() {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_type = %s
              AND post_mime_type LIKE %s
        ",
				'attachment',
				$image_mime_like
			)
		);

		if ( empty( $attachment_ids ) ) {
			return;
		}

		foreach ( $attachment_ids as $attachment_id ) {
			$this->flag_attachment_for_reoptimization( (int) $attachment_id );
		}
	}

	/**
	 * React to context signature changes by forcing regeneration.
	 *
	 * @since 1.2.0
	 *
	 * @param string $previous_signature Previous context signature.
	 * @param string $new_signature      Updated context signature.
	 * @return void
	 */
	public function handle_context_signature_change( $previous_signature, $new_signature ) {
		$previous_signature = (string) $previous_signature;
		$new_signature      = (string) $new_signature;

		if ( $previous_signature === $new_signature ) {
			return;
		}

		$this->mark_all_attachments_for_context_refresh();
		$this->clear_analysis_cache();
	}

	private function apply_suggested_filename_now( $attachment_id, $suggested_filename ) {
		if ( empty( $suggested_filename ) ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'No filename suggestion available', 'msh-image-optimizer' ),
			);
		}

		$suggested_basename = sanitize_file_name( basename( $suggested_filename ) );
		if ( $suggested_basename === '' ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'Invalid filename suggestion', 'msh-image-optimizer' ),
			);
		}

		if ( ! class_exists( 'MSH_Safe_Rename_System' ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Safe rename system not available.', 'msh-image-optimizer' ),
			);
		}

		$renamer = MSH_Safe_Rename_System::get_instance();

		// ====== BEGIN: MSH MANUAL RENAME GUARDS ======
		// Calculate expected paths before rename
		$old_relative       = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$new_relative       = str_replace( basename( $old_relative ), $suggested_basename, $old_relative );
		$expected_basename  = basename( $new_relative );
		$expected_dir       = trim( dirname( $new_relative ), '/' );

		// Set global guard to lock this rename operation
		$GLOBALS['_msh_guard'] = array(
			'post_id'          => (int) $attachment_id,
			'expected_rel'     => $new_relative,
			'expected_dir'     => $expected_dir,
			'expected_basename' => $expected_basename,
			'ts'               => microtime( true ),
		);

		// Store expected basename for normalize_basename() to use
		$GLOBALS['_msh_expected_basename'] = $expected_basename;

		error_log( '[MSH MANUAL RENAME LOCK] Enabled for attachment #' . $attachment_id . ' -> ' . $new_relative );

		// Temporarily remove ALL third-party filters on wp_update_attachment_metadata
		global $wp_filter;
		$removed_filters = array();
		if ( isset( $wp_filter['wp_update_attachment_metadata'] ) ) {
			$removed_filters = $wp_filter['wp_update_attachment_metadata'];
			remove_all_filters( 'wp_update_attachment_metadata' );
		}

		// CRITICAL: Also remove all pre_update_post_metadata filters except our guard
		$removed_pre_filters = array();
		if ( isset( $wp_filter['pre_update_post_metadata'] ) ) {
			$removed_pre_filters = $wp_filter['pre_update_post_metadata'];
			remove_all_filters( 'pre_update_post_metadata' );
		}

		// Re-add ONLY our guards at specific priorities
		// These guards will normalize any corruption attempts during the rename
		add_filter( 'pre_update_post_metadata', array( $renamer, 'guard_attached_file_corruption' ), 1, 4 );
		add_filter( 'wp_update_attachment_metadata', array( $renamer, 'guard_attachment_metadata_corruption' ), 9, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $renamer, 'forensic_track_metadata_corruption' ), 99, 2 );

		// Perform the rename
		$rename_result = $renamer->rename_attachment( $attachment_id, $suggested_basename, false );

		// CRITICAL: Perform final validation BEFORE restoring filters
		// This ensures no late writers can corrupt after the rename completes
		if ( ! is_wp_error( $rename_result ) && ! isset( $rename_result['skipped'] ) ) {
			$final_rel = get_post_meta( $attachment_id, '_wp_attached_file', true );
			if ( $final_rel !== $new_relative ) {
				error_log( "[MSH HEAL AJAX] Final _wp_attached_file mismatch! Expected: {$new_relative}, Got: {$final_rel}" );
				update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );
				error_log( "[MSH HEAL AJAX] Corrected _wp_attached_file" );
			}

			$final_meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $final_meta['file'] ) && $final_meta['file'] !== $new_relative ) {
				error_log( "[MSH HEAL AJAX] Final metadata[file] mismatch! Expected: {$new_relative}, Got: {$final_meta['file']}" );
				$final_meta['file'] = $new_relative;

				// Normalize sizes basenames
				if ( ! empty( $final_meta['sizes'] ) && is_array( $final_meta['sizes'] ) ) {
					foreach ( $final_meta['sizes'] as $k => $s ) {
						if ( ! empty( $s['file'] ) ) {
							$old_size_file = $s['file'];
							// Collapse repeated prefixes: TEST-TEST-TEST-foo.jpg -> TEST-foo.jpg
							$final_meta['sizes'][ $k ]['file'] = preg_replace( '/^(([A-Z0-9]+)-)\1+/i', '$1', $old_size_file );
						}
					}
				}

				wp_update_attachment_metadata( $attachment_id, $final_meta );
				error_log( "[MSH HEAL AJAX] Corrected metadata" );
			}
		}

		// NOW restore all removed filters
		if ( ! empty( $removed_pre_filters ) ) {
			foreach ( $removed_pre_filters as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_data ) {
					add_filter( 'pre_update_post_metadata', $callback_data['function'], $priority, $callback_data['accepted_args'] );
				}
			}
		}

		if ( ! empty( $removed_filters ) ) {
			foreach ( $removed_filters as $priority => $callbacks ) {
				foreach ( $callbacks as $callback_data ) {
					add_filter( 'wp_update_attachment_metadata', $callback_data['function'], $priority, $callback_data['accepted_args'] );
				}
			}
		}

		// Clean up guards
		unset( $GLOBALS['_msh_guard'] );
		unset( $GLOBALS['_msh_expected_basename'] );

		error_log( '[MSH MANUAL RENAME LOCK] Disabled for attachment #' . $attachment_id );
		// ====== END: MSH MANUAL RENAME GUARDS ======

		if ( is_wp_error( $rename_result ) ) {
			$error_data = $rename_result->get_error_data();
			$message    = $rename_result->get_error_message();

			if ( is_array( $error_data ) && isset( $error_data['verification'] ) ) {
				$details = $error_data['verification']['details'] ?? array();
				$failed  = array_filter(
					$details,
					static function ( $item ) {
						return isset( $item['status'] ) && $item['status'] === 'failed';
					}
				);

				if ( ! empty( $failed ) ) {
					$first_failure = reset( $failed );
					if ( ! empty( $first_failure['table'] ) && ! empty( $first_failure['row_id'] ) ) {
						$message .= sprintf(
							' (verification failed on %s row %s)',
							$first_failure['table'],
							$first_failure['row_id']
						);
					} elseif ( ! empty( $first_failure['error_message'] ) ) {
						$message .= ' (' . $first_failure['error_message'] . ')';
					}
				}

				error_log( '[MSH Safe Rename] Verification failure details: ' . print_r( $error_data['verification'], true ) );
			}

			return array(
				'status'     => 'error',
				'message'    => $message,
				'error_data' => $error_data,
			);
		}

		if ( ! empty( $rename_result['skipped'] ) ) {
			delete_post_meta( $attachment_id, '_msh_suggested_filename' );
			delete_post_meta( $attachment_id, '_msh_suggested_filename_context' );
			return array(
				'status'  => 'skipped',
				'message' => __( 'Filename already optimized', 'msh-image-optimizer' ),
			);
		}

		delete_post_meta( $attachment_id, '_msh_suggested_filename' );
		delete_post_meta( $attachment_id, '_msh_suggested_filename_context' );

		$relative_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$absolute_path = get_attached_file( $attachment_id );
		$filename      = $relative_path ? basename( $relative_path ) : basename( $absolute_path );

		return array(
			'status'        => 'success',
			'relative_path' => $relative_path,
			'absolute_path' => $absolute_path,
			'filename'      => $filename,
			'url'           => wp_get_attachment_url( $attachment_id ),
		);
	}

	/**
	 * Retrieve the singleton instance of the optimizer.
	 *
	 * @since 1.2.0
	 *
	 * @return MSH_Image_Optimizer Instance of the optimizer.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register AJAX endpoints and prime dependencies.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = $this;

		if ( ! isset( $this->contextual_meta_generator ) ) {
			$this->contextual_meta_generator = new MSH_Contextual_Meta_Generator();
		}

		add_action( 'wp_ajax_msh_analyze_images', array( $this, 'ajax_analyze_images' ) );
		add_action( 'wp_ajax_msh_optimize_batch', array( $this, 'ajax_optimize_batch' ) );
		add_action( 'wp_ajax_msh_get_progress', array( $this, 'ajax_get_progress' ) );
		add_action( 'wp_ajax_msh_reset_optimization', array( $this, 'ajax_reset_optimization' ) );
		add_action( 'wp_ajax_msh_apply_filename_suggestions', array( $this, 'ajax_apply_filename_suggestions' ) );
		add_action( 'wp_ajax_msh_save_filename_suggestion', array( $this, 'ajax_save_filename_suggestion' ) );
		add_action( 'wp_ajax_msh_remove_filename_suggestion', array( $this, 'ajax_remove_filename_suggestion' ) );
		add_action( 'wp_ajax_msh_accept_filename_suggestion', array( $this, 'ajax_accept_filename_suggestion' ) );
		add_action( 'wp_ajax_msh_toggle_file_rename', array( $this, 'ajax_toggle_file_rename' ) );
		add_action( 'wp_ajax_msh_toggle_ai_mode', array( $this, 'ajax_toggle_ai_mode' ) );
		add_action( 'wp_ajax_msh_reject_filename_suggestion', array( $this, 'ajax_reject_filename_suggestion' ) );
		add_action( 'wp_ajax_msh_preview_meta_text', array( $this, 'ajax_preview_meta_text' ) );
		add_action( 'wp_ajax_msh_save_edited_meta', array( $this, 'ajax_save_edited_meta' ) );
		add_action( 'wp_ajax_msh_update_context', array( $this, 'ajax_update_context' ) );
		add_action( 'wp_ajax_msh_build_usage_index', array( $this, 'ajax_build_usage_index' ) );
		add_action( 'wp_ajax_msh_clear_bad_suggestions', array( $this, 'ajax_clear_bad_suggestions' ) );
		add_action( 'wp_ajax_msh_optimize_high_priority', array( $this, 'ajax_optimize_high_priority' ) );
		add_action( 'wp_ajax_msh_optimize_medium_priority', array( $this, 'ajax_optimize_medium_priority' ) );
		add_action( 'wp_ajax_msh_optimize_all_remaining', array( $this, 'ajax_optimize_all_remaining' ) );
		add_action( 'wp_ajax_msh_verify_webp_status', array( $this, 'ajax_verify_webp_status' ) );
		add_action( 'wp_ajax_msh_get_attachment_count', array( $this, 'ajax_get_attachment_count' ) );
		add_action( 'wp_ajax_msh_get_remaining_count', array( $this, 'ajax_get_remaining_count' ) );
		add_action( 'wp_ajax_msh_check_capabilities', array( $this, 'ajax_check_capabilities' ) );
		add_action( 'init', array( $this, 'prime_season_cache' ) );

		// DIAGNOSTIC: Verify hook registration
		add_action( 'admin_init', function() {
			if ( has_action( 'wp_ajax_msh_analyze_images' ) ) {
				error_log( '[HOOK] wp_ajax_msh_analyze_images registered' );
			} else {
				error_log( '[HOOK ERROR] wp_ajax_msh_analyze_images NOT registered' );
			}
		} );

		$this->contextual_meta_generator = new MSH_Contextual_Meta_Generator();

		// Auto-generate suggestions for new uploads
		add_action( 'add_attachment', array( $this, 'generate_suggestion_for_new_upload' ), 10, 1 );

		// Regenerate filename suggestions when toggle flipped on
		add_action( 'msh_regenerate_filename_suggestions', array( $this, 'regenerate_all_filename_suggestions' ) );

		// Context field in Media Library (list mode only, not grid mode)
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_context_attachment_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_context_attachment_field' ), 10, 2 );
	}

	/**
	 * Prime the contextual generator's season cache early in the request.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function prime_season_cache() {
		if ( ! ( $this->contextual_meta_generator instanceof MSH_Contextual_Meta_Generator ) ) {
			$this->contextual_meta_generator = new MSH_Contextual_Meta_Generator();
		}

		$this->contextual_meta_generator->get_current_season();
	}

	/**
	 * Regenerate filename suggestions for all attachments lacking suggestions.
	 * Triggered when file rename toggle flips from off → on.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function regenerate_all_filename_suggestions() {
		// Only run if rename feature is enabled
		if ( ! $this->is_file_rename_enabled() ) {
			return;
		}

		global $wpdb;

		// Find all image attachments without filename suggestions
		$attachments = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			AND post_mime_type LIKE 'image/%'
			AND ID NOT IN (
				SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_msh_suggested_filename'
				AND meta_value != ''
			)
			LIMIT 100"
		);

		if ( empty( $attachments ) ) {
			return;
		}

		// Generate suggestions for each attachment
		foreach ( $attachments as $attachment_id ) {
			$this->generate_suggestion_for_new_upload( (int) $attachment_id );
		}
	}

	/**
	 * Check if recompression is needed with safe file checks
	 */
	private function needs_recompression( $attachment_id ) {
		$source_file = get_attached_file( $attachment_id );
		if ( ! $source_file || ! file_exists( $source_file ) ) {
			return 'needs_attention'; // File missing or invalid
		}

		$source_mtime = @filemtime( $source_file );
		if ( $source_mtime === false ) {
			return 'needs_attention'; // Can't read file time
		}

		$last_webp     = (int) get_post_meta( $attachment_id, 'msh_webp_last_converted', true );
		$last_metadata = (int) get_post_meta( $attachment_id, 'msh_metadata_last_updated', true );

		// If no optimization timestamps exist, this isn't a recompression case
		if ( ! $last_webp && ! $last_metadata ) {
			return false;
		}

		// Needs recompression if source is newer than optimization
		return $source_mtime > max( $last_webp, $last_metadata );
	}

	/**
	 * Validate optimization status and provide fallback for unexpected values
	 */
	private function validate_status( $status ) {
		$valid_statuses = array(
			'ready_for_optimization',
			'ai_pending',
			'optimized',
			'metadata_missing',
			'needs_recompression',
			'webp_missing',
			'metadata_current',
			'needs_webp_conversion',
			'webp_timestamp_missing',
			'needs_attention',
		);

		if ( ! in_array( $status, $valid_statuses ) ) {
			$this->log_debug( "MSH Optimizer: Invalid status '$status' returned, defaulting to needs_attention" );
			return 'needs_attention';
		}

		return $status;
	}

	/**
	 * Get optimization status with enhanced logic and validation
	 */
	private function get_optimization_status( $attachment_id ) {
		$webp_time          = (int) get_post_meta( $attachment_id, 'msh_webp_last_converted', true );
		$meta_time          = (int) get_post_meta( $attachment_id, 'msh_metadata_last_updated', true );
		$optimized_date     = get_post_meta( $attachment_id, 'msh_optimized_date', true );
		$version            = get_post_meta( $attachment_id, 'msh_optimization_version', true );
		$webp_status        = get_post_meta( $attachment_id, 'msh_webp_status', true );
		$needs_refresh_flag = get_post_meta( $attachment_id, 'msh_context_needs_refresh', true );

		if ( ! empty( $needs_refresh_flag ) ) {
			return $this->validate_status( 'metadata_missing' );
		}

		$source_file = get_attached_file( $attachment_id );
		if ( ! $source_file || ! file_exists( $source_file ) ) {
			// Missing files are marked as "needs_attention" and excluded from optimization
			return $this->validate_status( 'needs_attention' );
		}

		// Skip WebP conversion logic for files that don't need it
		$file_extension      = strtolower( pathinfo( $source_file, PATHINFO_EXTENSION ) );
		$is_webp_convertible = in_array( $file_extension, array( 'jpg', 'jpeg', 'png' ) );

		if ( $is_webp_convertible && in_array( $webp_status, array( 'unsupported', 'failed' ), true ) ) {
			if ( ! $meta_time ) {
				return $this->validate_status( 'metadata_missing' );
			}
			// If has metadata but never optimized, needs optimization (rename)
			if ( ! $optimized_date ) {
				return $this->validate_status( 'ready_for_optimization' );
			}
			return $this->validate_status( 'optimized' );
		}

		// For SVG, WebP, GIF, and other non-convertible formats, check if actually optimized
		if ( ! $is_webp_convertible ) {
			if ( ! $meta_time ) {
				return $this->validate_status( 'metadata_missing' );
			}
			// If has metadata but never optimized, needs optimization (rename)
			if ( ! $optimized_date ) {
				return $this->validate_status( 'ready_for_optimization' );
			}
			return $this->validate_status( 'optimized' );
		}

		// Check for missing metadata FIRST (before recompression test)
		if ( ! $meta_time && ! $webp_time ) {
			return $this->validate_status( 'ready_for_optimization' );
		}

		if ( ! $meta_time ) {
			return $this->validate_status( 'metadata_missing' );
		}

		// CHECK: If image has been marked as optimized via msh_optimized_date, consider it optimized
		// This is the authoritative flag that the full optimization process completed
		if ( $optimized_date && $meta_time ) {
			return $this->validate_status( 'optimized' );
		}

		$webp_path   = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $source_file );
		$webp_exists = file_exists( $webp_path );

		// Now check recompression (only for images that have been optimized)
		$recompression_check = $this->needs_recompression( $attachment_id );
		if ( $recompression_check === 'needs_attention' ) {
			return $this->validate_status( 'needs_attention' );
		} elseif ( $recompression_check === true ) {
			return $this->validate_status( 'needs_recompression' );
		}

		// If has metadata and WebP but no optimized_date, it's ready for optimization
		// (metadata/WebP were generated but file was never actually optimized/renamed)
		if ( $webp_time && $meta_time && $webp_exists ) {
			return $this->validate_status( 'ready_for_optimization' );
		} elseif ( $webp_time && ! $webp_exists ) {
			return $this->validate_status( 'webp_missing' );
		} elseif ( $meta_time && ! $webp_time && ! $webp_exists ) {
			// Has metadata but missing WebP - needs WebP conversion
			return $this->validate_status( 'needs_webp_conversion' );
		} elseif ( $meta_time && ! $webp_time && $webp_exists ) {
			// Has metadata and WebP file exists but no timestamp - update timestamp
			return $this->validate_status( 'webp_timestamp_missing' );
		} else {
			return $this->validate_status( 'ready_for_optimization' );
		}
	}

	/**
	 * Retrieve all image attachments eligible for optimisation.
	 *
	 * @since 1.2.0
	 *
	 * @return array[] List of attachment records keyed by column name.
	 */
	public function get_published_images() {
		// TEMP: Disable caching to debug file analysis issues
		// static $cached_results = null;
		// if ($cached_results !== null) {
		// return $cached_results;
		// }

		global $wpdb;
		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name, post_mime_type
	             FROM {$wpdb->posts}
	             WHERE post_type = %s
	             AND post_mime_type LIKE %s
	             ORDER BY ID",
				'attachment',
				$image_mime_like
			),
			ARRAY_A
		);

		// Debug: Count SVG attachments found (reduced logging for performance)
		$svg_count = 0;
		foreach ( $attachments as $attachment ) {
			if ( strpos( $attachment['post_mime_type'], 'svg' ) !== false ) {
				++$svg_count;
			}
		}
		// Debug logging removed for production

		if ( empty( $attachments ) ) {
			// $cached_results = [];
			return array();
		}

		$attachment_map = array();
		$attachment_ids = array();

		foreach ( $attachments as $attachment ) {
			$attachment['file_path']             = '';
			$attachment['alt_text']              = '';
			$attachment['used_in']               = array();
			$attachment_map[ $attachment['ID'] ] = $attachment;
			$attachment_ids[]                    = (int) $attachment['ID'];
		}

		// Gather attachment meta in chunks to avoid oversized IN clauses
		$meta_keys = array(
			'_wp_attached_file',
			'_wp_attachment_image_alt',
		);

		$meta_rows  = array();
		$chunk_size = 200;

		foreach ( array_chunk( $attachment_ids, $chunk_size ) as $chunk ) {
			// Build placeholder strings for dynamic IN clauses
			$id_placeholders   = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$meta_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

			// Prepare SQL with dynamic IN clause
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
			// Dynamic placeholders built from array_fill() - count($chunk) + count($meta_keys) placeholders match array_merge() arguments
			$sql = $wpdb->prepare(
				"SELECT post_id, meta_key, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ($id_placeholders)
				AND meta_key IN ($meta_placeholders)",
				array_merge( $chunk, $meta_keys )
			);
			$meta_rows = array_merge( $meta_rows, $wpdb->get_results( $sql, ARRAY_A ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.NotPrepared
		}

		foreach ( $meta_rows as $meta_row ) {
			$post_id = (int) $meta_row['post_id'];

			if ( ! isset( $attachment_map[ $post_id ] ) ) {
				continue;
			}

			if ( $meta_row['meta_key'] === '_wp_attached_file' ) {
				$file_path   = ltrim( (string) $meta_row['meta_value'], '/' );
				$uploads_dir = wp_upload_dir();
				$full_path   = $uploads_dir['basedir'] . '/' . $file_path;

				// Only set file_path if file actually exists on disk
				if ( file_exists( $full_path ) ) {
					$attachment_map[ $post_id ]['file_path'] = $file_path;
				} else {
					// Debug logging removed for production
					// Leave file_path empty so it gets filtered out later
				}
			}

			if ( $meta_row['meta_key'] === '_wp_attachment_image_alt' ) {
				$attachment_map[ $post_id ]['alt_text'] = (string) $meta_row['meta_value'];
			}
		}

		$upload_dir      = wp_get_upload_dir();
		$uploads_baseurl = isset( $upload_dir['baseurl'] ) ? $upload_dir['baseurl'] : '';
		$uploads_baseurl = rtrim( $uploads_baseurl, '/' );

		$file_map     = array();
		$basename_map = array();

		foreach ( $attachment_map as $attachment_id => $attachment ) {
			if ( ! empty( $attachment['file_path'] ) ) {
				$relative_path                            = ltrim( $attachment['file_path'], '/' );
				$file_map[ strtolower( $relative_path ) ] = $attachment_id;

				$basename       = strtolower( basename( $relative_path ) );
				$clean_basename = preg_replace( '/-\d+x\d+(?=\.[^.]+$)/', '', $basename );
				$clean_basename = str_replace( array( '-scaled', '-rotated', '-edited' ), '', $clean_basename );

				if ( ! isset( $basename_map[ $basename ] ) ) {
					$basename_map[ $basename ] = array();
				}
				$basename_map[ $basename ][ $attachment_id ] = true;

				if ( ! isset( $basename_map[ $clean_basename ] ) ) {
					$basename_map[ $clean_basename ] = array();
				}
				$basename_map[ $clean_basename ][ $attachment_id ] = true;
			}
		}

		$register_usage = static function ( &$map, $attachment_id, $post_title, $post_type ) {
			if ( ! isset( $map[ $attachment_id ] ) ) {
				return;
			}

			$title = trim( (string) $post_title );

			if ( $title === '' ) {
				$title = 'Untitled';
			}

			$label                                      = $title . ' (' . $post_type . ')';
			$map[ $attachment_id ]['used_in'][ $label ] = true;
		};

		// Featured images (single query)
		$featured_rows = $wpdb->get_results(
			"SELECT meta.meta_value AS attachment_id, posts.post_title, posts.post_type
             FROM {$wpdb->postmeta} meta
             INNER JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id
             WHERE meta.meta_key = '_thumbnail_id'
             AND posts.post_status = 'publish'",
			ARRAY_A
		);

		foreach ( $featured_rows as $row ) {
			$attachment_id = (int) $row['attachment_id'];

			if ( ! isset( $attachment_map[ $attachment_id ] ) ) {
				continue;
			}

			$register_usage(
				$attachment_map,
				$attachment_id,
				$row['post_title'],
				$row['post_type']
			);
		}

		// Published posts/pages content scan
		$content_rows = $wpdb->get_results(
			"SELECT ID, post_title, post_type, post_content
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type NOT IN ('attachment','revision','nav_menu_item','customize_changeset','oembed_cache','user_request')",
			ARRAY_A
		);

		foreach ( $content_rows as $post_row ) {
			$content = (string) $post_row['post_content'];

			if ( $content === '' ) {
				continue;
			}

			// Match Gutenberg and classic editor image references by attachment ID
			if ( preg_match_all( '/wp-image-(\d+)/', $content, $id_matches ) ) {
				$matched_ids = array_unique( array_map( 'intval', $id_matches[1] ) );

				foreach ( $matched_ids as $attachment_id ) {
					if ( ! isset( $attachment_map[ $attachment_id ] ) ) {
						continue;
					}

					$register_usage(
						$attachment_map,
						$attachment_id,
						$post_row['post_title'],
						$post_row['post_type']
					);
				}
			}

			// Match direct file references
			if ( preg_match_all( '#wp-content/uploads/[^"\'\s>]+#i', $content, $path_matches ) ) {
				$paths = array_unique( $path_matches[0] );

				foreach ( $paths as $path ) {
					$normalized = strtolower( $path );
					$normalized = preg_replace( '#^' . preg_quote( strtolower( $uploads_baseurl ), '#' ) . '\/?#', '', $normalized );
					$normalized = preg_replace( '#^.*wp-content\/uploads\/+#', '', $normalized );
					$normalized = strtok( $normalized, '?' ); // remove query strings
					$normalized = ltrim( (string) $normalized, '/' );

					if ( $normalized === '' ) {
						continue;
					}

					if ( isset( $file_map[ $normalized ] ) ) {
						$attachment_id = $file_map[ $normalized ];
						$register_usage(
							$attachment_map,
							$attachment_id,
							$post_row['post_title'],
							$post_row['post_type']
						);
						continue;
					}

					$basename = strtolower( basename( $normalized ) );

					if ( isset( $basename_map[ $basename ] ) ) {
						foreach ( array_keys( $basename_map[ $basename ] ) as $attachment_id ) {
							$register_usage(
								$attachment_map,
								$attachment_id,
								$post_row['post_title'],
								$post_row['post_type']
							);
						}
						continue;
					}

					$basename_clean = preg_replace( '/-\d+x\d+(?=\.[^.]+$)/', '', $basename );
					$basename_clean = str_replace( array( '-scaled', '-rotated', '-edited' ), '', $basename_clean );

					if ( isset( $basename_map[ $basename_clean ] ) ) {
						foreach ( array_keys( $basename_map[ $basename_clean ] ) as $attachment_id ) {
							$register_usage(
								$attachment_map,
								$attachment_id,
								$post_row['post_title'],
								$post_row['post_type']
							);
						}
					}
				}
			}
		}

		$published_images   = array();
		$svg_excluded_count = 0;
		$svg_included_count = 0;

		foreach ( $attachment_map as $attachment ) {
			$is_svg = isset( $attachment['post_mime_type'] ) && strpos( $attachment['post_mime_type'], 'svg' ) !== false;

			// TEMPORARY: Limit SVG auto-include to prevent performance issues
			// Only auto-include SVGs with IDs > 14500 (newer condition icons) to focus on relevant ones
			$should_auto_include_svg = $is_svg && (int) $attachment['ID'] > 14500;

			// Include SVGs regardless of usage detection (limited to newer ones for performance)
			if ( empty( $attachment['used_in'] ) ) {
				if ( $is_svg && ! $should_auto_include_svg ) {
					continue;
				}

				if ( $should_auto_include_svg ) {
					$attachment['used_in'] = array( 'SVG Icon (auto-included)' => true );
					++$svg_excluded_count;
				} else {
					$attachment['used_in'] = array( 'No usage detected' => true );
				}
			}

			if ( $is_svg ) {
				++$svg_included_count;
				// Debug logging removed for production
			}

			// Skip images with missing files (empty file_path)
			if ( empty( $attachment['file_path'] ) ) {
				// Debug logging removed for production
				continue;
			}

			$attachment['used_in'] = implode( ', ', array_keys( $attachment['used_in'] ) );
			$published_images[]    = $attachment;
		}

		// Debug logging removed for production

		usort(
			$published_images,
			static function ( $a, $b ) {
				return $a['ID'] <=> $b['ID'];
			}
		);

		// $cached_results = $published_images;
		// return $cached_results;
		return $published_images;
	}

	/**
	 * Get images for AI regeneration based on scope
	 */
	private function get_images_for_ai_regeneration( $scope, $mode, $fields ) {
		global $wpdb;

		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		// Get attachment IDs based on scope
		switch ( $scope ) {
			case 'all':
				$attachment_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
	                    WHERE post_type = %s AND post_mime_type LIKE %s",
						'attachment',
						$image_mime_like
					)
				);
				break;

			case 'published':
				// Use existing published images logic
				$published      = $this->get_published_images();
				$attachment_ids = array_column( $published, 'ID' );
				break;

			case 'missing':
				$attachment_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p
	                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
	                    WHERE p.post_type = %s
	                    AND p.post_mime_type LIKE %s
	                    AND (p.post_title = '' OR p.post_title IS NULL OR pm.meta_value = '' OR pm.meta_value IS NULL)",
						'attachment',
						$image_mime_like
					)
				);
				break;

			default:
				return array();
		}

		// Get full image data for these IDs
		$images = array();
		foreach ( $attachment_ids as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment ) {
				continue;
			}

			$file_path = get_post_meta( $id, '_wp_attached_file', true );
			$alt_text  = get_post_meta( $id, '_wp_attachment_image_alt', true );

			// Skip if file_path is empty or file doesn't exist on disk
			if ( empty( $file_path ) ) {
				continue;
			}

			$uploads_dir = wp_upload_dir();
			$full_path   = $uploads_dir['basedir'] . '/' . ltrim( $file_path, '/' );
			if ( ! file_exists( $full_path ) ) {
				continue;
			}

			$images[] = array(
				'ID'              => $id,
				'post_title'      => $attachment->post_title,
				'post_name'       => $attachment->post_name,
				'post_mime_type'  => $attachment->post_mime_type,
				'file_path'       => $file_path,
				'alt_text'        => $alt_text,
				'used_in'         => '', // AI regeneration doesn't need usage data (empty string for type safety)
				'ai_regeneration' => true, // Flag to force AI
				'ai_mode'         => $mode,
				'ai_fields'       => $fields,
			);
		}

		return $images;
	}

	/**
	 * Calculate healthcare-specific priority for image optimization
	 */
	private function calculate_healthcare_priority( $image ) {
		$priority = 1;
		$used_in  = strtolower( $image['used_in'] );

		// Healthcare-specific high-priority pages
		if ( strpos( $used_in, 'home' ) !== false ) {
			$priority += 15; // Homepage hero images critical for trust
		}

		// Medical services pages (highest conversion)
		if ( strpos( $used_in, 'services' ) !== false ||
			strpos( $used_in, 'treatment' ) !== false ||
			strpos( $used_in, 'conditions' ) !== false ) {
			$priority += 12;
		}

		// Team/doctor photos (trust & credibility)
		if ( strpos( $used_in, 'team' ) !== false ||
			strpos( $used_in, 'doctor' ) !== false ||
			strpos( $used_in, 'staff' ) !== false ) {
			$priority += 10;
		}

		// Patient testimonials/success stories
		if ( strpos( $used_in, 'testimonial' ) !== false ||
			strpos( $used_in, 'patient' ) !== false ) {
			$priority += 8;
		}

		// CRITICAL: Missing alt text in healthcare = accessibility violation
		if ( empty( $image['alt_text'] ) ) {
			$priority += 20; // Healthcare accessibility is legal requirement
		}

		return $priority;
	}

	/**
	 * Analyse an individual attachment and return optimisation insights.
	 *
	 * @since 1.2.0
	 *
	 * @param int   $attachment_id Attachment identifier.
	 * @param array $ai_options    Optional. AI generation context to include in the response.
	 * @return array Analysis payload describing current optimisation state.
	 */
	public function analyze_single_image( $attachment_id, $ai_options = array() ) {
		// PHASE 3: Micro-timing instrumentation for performance diagnostics
		$t_total = microtime( true );
		$t_prep  = microtime( true );

		// Start performance profiling
		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::begin( 'total' );
			MSH_Profiler::begin( 'db_prefetch' );
		}

		// Prefetch post and meta cache
		$post = get_post( $attachment_id );
		if ( ! $post ) {
			if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
				MSH_Profiler::end( 'db_prefetch' );
				MSH_Profiler::end( 'total' );
			}
			return array( 'error' => 'Attachment not found' );
		}
		update_meta_cache( 'post', array( $attachment_id ) );

		// Read ACTUAL current metadata from WordPress database
		// This is what's ACTUALLY stored, not what we think should be there
		$current_meta = array(
			'title'       => $post->post_title ?? '',
			'caption'     => $post->post_excerpt ?? '',
			'description' => $post->post_content ?? '',
			'alt_text'    => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);

		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::end( 'db_prefetch' );
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}
		$upload_dir = wp_upload_dir();

		$relative_file = is_array( $metadata ) && ! empty( $metadata['file'] )
			? $metadata['file']
			: get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( empty( $relative_file ) ) {
			if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
				MSH_Profiler::end( 'total' );
			}
			return array( 'error' => 'No file metadata found' );
		}

		// Profile file system operations
		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::begin( 'file_stat' );
		}

		$resolver  = MSH_File_Resolver::find_attachment_file( $attachment_id, $relative_file );
		$file_path = $resolver['path'];

		if ( ! $file_path ) {
			if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
				MSH_Profiler::end( 'file_stat' );
				MSH_Profiler::end( 'total' );
			}
			return array(
				'error'           => 'File not found: ' . $relative_file,
				'resolver_method' => $resolver['method'],
			);
		}

		if ( ! empty( $resolver['mismatch'] ) ) {
			$this->log_debug(
				sprintf(
					'MSH Analyzer: Attachment %d resolved via fallback (%s)',
					$attachment_id,
					$resolver['method']
				)
			);
			$relative_file = ltrim( str_replace( $upload_dir['basedir'], '', $file_path ), '/' );
		}

		$file_size = filesize( $file_path );
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$is_svg    = ( $extension === 'svg' );

		$image_info = $is_svg ? array(
			0      => 0,
			1      => 0,
			'mime' => 'image/svg+xml',
		) : @getimagesize( $file_path );
		if ( ! $image_info ) {
			$image_info = array(
				0      => 0,
				1      => 0,
				'mime' => $metadata['mime_type'] ?? 'image',
			);
		}

		$webp_exists  = false;
		$webp_savings = null;
		if ( ! $is_svg ) {
			$webp_path    = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
			$webp_exists  = file_exists( $webp_path );
			$webp_savings = $this->estimate_webp_savings( $file_size, $image_info['mime'] );
		}

		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::end( 'file_stat' );
		}

		// Determine legacy resizing context and new contextual information
		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::begin( 'context_detect' );
		}

		$legacy_context            = $this->determine_image_context( $attachment_id );
		$context_info              = $this->contextual_meta_generator->detect_context( $attachment_id );
		$current_context_signature = $this->contextual_meta_generator->get_context_signature();
		$metadata_source           = get_post_meta( $attachment_id, 'msh_metadata_source', true );
		$stored_meta_hash          = get_post_meta( $attachment_id, 'msh_metadata_context_hash', true );
		$metadata_context_mismatch = ( $metadata_source !== 'manual_edit' && ! empty( $stored_meta_hash ) && $stored_meta_hash !== $current_context_signature );
		$manual_context_value      = get_post_meta( $attachment_id, '_msh_context', true );
		$manual_context_value      = is_string( $manual_context_value ) ? trim( $manual_context_value ) : '';
		$auto_context_value        = get_post_meta( $attachment_id, '_msh_auto_context', true );
		$auto_context_value        = is_string( $auto_context_value ) ? trim( $auto_context_value ) : '';
		$context_source            = ! empty( $context_info['manual'] ) ? 'manual' : 'auto';
		$active_context_slug       = $manual_context_value !== ''
			? $manual_context_value
			: ( $context_info['type'] ?? $auto_context_value );

		// DEBUG: Log before generate_meta_fields call
		error_log( sprintf(
			'[MSH DEBUG] Before generate_meta_fields - attachment_id: %d, context_info: %s, ai_options: %s',
			$attachment_id,
			json_encode( $context_info ),
			json_encode( $ai_options )
		) );

		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::end( 'context_detect' );
			MSH_Profiler::begin( 'metadata_generate' );
		}

		// PHASE 3: End prep timing, start AI timing
		$prep_time = microtime( true ) - $t_prep;
		$t_ai      = microtime( true );

		// PERFORMANCE FIX: Skip AI for already-optimized images during analyze
		// Only generate AI metadata for unoptimized images or explicit regeneration
		$is_ai_regeneration = ! empty( $ai_options['ai_regeneration'] );
		$optimized_date = get_post_meta( $attachment_id, 'msh_optimized_date', true );
		$is_already_optimized = ! empty( $optimized_date );

		if ( ! $is_ai_regeneration && $is_already_optimized ) {
			// Image already optimized - skip AI, use existing metadata
			$ai_options['ai_mode'] = 'manual';
		}
		// For unoptimized images, AI mode will be determined by global setting

		// DIAGNOSTIC: Log row-level analyze parameters
		$final_context_type = isset( $context_info['final_context_type'] ) ? $context_info['final_context_type'] : ( $context_info['type'] ?? 'stock' );
		$loc_mode           = $this->resolve_loc_mode( $attachment_id, $final_context_type );
		$context_info['loc_mode'] = $loc_mode;

		$final_ct = $final_context_type;
		$seo = isset( $context_info['seo_mode'] ) ? (int) $context_info['seo_mode'] : 1;
		$ai_mode_val = $ai_options['ai_mode'] ?? 'manual';
		$ai = ( $ai_mode_val !== 'manual' ) ? 1 : 0;
		$needs_ai = ( $ai && ! $is_already_optimized ) ? 1 : 0;
		error_log( sprintf(
			'[ANALYZE] ROW %d ct=%s seo=%d ai=%d needs_ai=%d mode=%s loc_mode=%s',
			$attachment_id,
			$final_ct,
			$seo,
			$ai,
			$needs_ai,
			$ai_mode_val,
			$loc_mode
		) );

		$generated_meta            = $this->contextual_meta_generator->generate_meta_fields( $attachment_id, $context_info, $ai_options );

		// PHASE 3: End AI timing
		$ai_time = microtime( true ) - $t_ai;

		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::end( 'metadata_generate' );
		}

		// CRITICAL FIX: Check if generate_meta_fields returned WP_Error
		if ( is_wp_error( $generated_meta ) ) {
			error_log( sprintf(
				'[MSH ERROR] generate_meta_fields returned WP_Error for attachment %d: %s',
				$attachment_id,
				$generated_meta->get_error_message()
			) );
			return array(
				'error' => $generated_meta->get_error_message(),
				'error_code' => $generated_meta->get_error_code(),
			);
		}

		// Check if this is an AI regeneration request BEFORE using the variable
		$is_ai_regeneration = ! empty( $ai_options['ai_regeneration'] );

		// DEBUG: Log AI regeneration flow
		error_log( sprintf(
			'[MSH DEBUG] AI Regeneration Flow - attachment_id: %d, is_ai_regeneration: %s, ai_options: %s',
			$attachment_id,
			$is_ai_regeneration ? 'TRUE' : 'FALSE',
			json_encode( $ai_options )
		) );

		$pending_ai_fields   = array();
		$has_pending_ai_meta = false;
		$staged_ai_metadata  = null;

		// PHASE 3: Start DB timing
		$t_db = microtime( true );

		// Profile database writes
		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::begin( 'db_writes' );
		}

		if ( $is_ai_regeneration ) {
			error_log( sprintf( '[MSH DEBUG] Entering AI regeneration branch for attachment %d', $attachment_id ) );
			error_log( sprintf( '[MSH DEBUG] generated_meta: %s', json_encode( $generated_meta ) ) );
			if ( ! empty( $generated_meta ) && is_array( $generated_meta ) ) {
				$staged_ai_metadata = array_intersect_key(
					$generated_meta,
					array_flip( array( 'title', 'alt_text', 'caption', 'description' ) )
				);

				if ( ! empty( $staged_ai_metadata ) ) {
					$pending_ai_fields   = array_keys( $staged_ai_metadata );
					$has_pending_ai_meta = true;
					update_post_meta(
						$attachment_id,
						'_msh_ai_staged_meta',
						array(
							'generated_at' => time(),
							'ai_mode'      => $ai_options['ai_mode'] ?? '',
							'ai_fields'    => $ai_options['ai_fields'] ?? array(),
							'data'         => $staged_ai_metadata,
						)
					);
				} else {
					delete_post_meta( $attachment_id, '_msh_ai_staged_meta' );
				}
			} else {
				delete_post_meta( $attachment_id, '_msh_ai_staged_meta' );
			}
		} else {
			$staged_ai_metadata = get_post_meta( $attachment_id, '_msh_ai_staged_meta', true );
			if ( is_array( $staged_ai_metadata ) && ! empty( $staged_ai_metadata['data'] ) ) {
				$pending_ai_fields   = array_keys( (array) $staged_ai_metadata['data'] );
				$has_pending_ai_meta = ! empty( $pending_ai_fields );
			}
		}

		// Check if file renaming feature is enabled
		$rename_enabled = $this->is_file_rename_enabled();
		$had_existing_suggestion = (bool) get_post_meta( $attachment_id, '_msh_suggested_filename', true );

		// Check if file already has SEO-optimized name FIRST
		$current_file     = $file_path; // Use the correct variable name
		$path_info        = pathinfo( $current_file );
		$extension        = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : '';
		$current_basename = strtolower( $path_info['basename'] );
		$current_slug     = strtolower( isset( $path_info['filename'] ) ? $path_info['filename'] : '' );

		// Generate target slug for comparison (context-aware) - only if renaming is enabled
		$expected_slug = '';
		if ( $rename_enabled && ! empty( $extension ) ) {
			$expected_slug = $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_info, $extension );
			if ( ! empty( $expected_slug ) ) {
				$expected_slug = $this->limit_slug_words( $expected_slug );
			}
		}

		// If file already has good name, clear any existing suggestion and don't generate new one
		// BUT: Always regenerate if AI regeneration is running (regardless of current filename quality)
		if ( $is_ai_regeneration ) {
			$has_good_name = false; // Force regeneration for AI mode
		} elseif ( ! empty( $expected_slug ) ) {
			$has_good_name = ( $current_slug === strtolower( $expected_slug ) );
		} else {
			$has_good_name = ( strpos( $current_basename, 'msh' ) !== false ||
							strpos( $current_basename, 'hamilton' ) !== false ||
							strpos( $current_basename, 'main-street-health' ) !== false ||
							// Also detect common SEO patterns our system generates
							preg_match( '/^(rehabilitation|physiotherapy|chiropractic|acupuncture|massage|orthotics|chronic-pain|work-related|sport-injuries|motor-vehicle|patient-testimonial|bluecross|canada-life|manulife)-/', $current_basename ) ||
							// Or files that end with OUR attachment ID pattern (more specific to avoid false matches)
							preg_match( '/-' . $attachment_id . '\.(jpg|jpeg|png|gif|svg|webp)$/', $current_basename ) );
		}

		if ( $has_good_name && ! $had_existing_suggestion ) {
			$has_good_name = false;
		}

		// Debug filename suggestion logic for SVGs
		$is_svg = ( strtolower( pathinfo( $current_file, PATHINFO_EXTENSION ) ) === 'svg' );
		if ( $is_svg && (int) $attachment_id > 14500 ) {
			// Debug disabled for performance
		}

		$filename_context_mismatch = false;

		// If renaming is disabled, skip all filename suggestion logic
		if ( ! $rename_enabled ) {
			$suggested_filename = '';
		} elseif ( $has_good_name ) {
			// Remove any existing suggestion for this already-optimized file
			delete_post_meta( $attachment_id, '_msh_suggested_filename' );
			delete_post_meta( $attachment_id, '_msh_suggested_filename_context' );
			$suggested_filename = ''; // No suggestion needed
		} else {
			// Get or generate suggestion for files that need renaming
			$suggested_filename     = get_post_meta( $attachment_id, '_msh_suggested_filename', true );
			$suggested_context_hash = get_post_meta( $attachment_id, '_msh_suggested_filename_context', true );

			// Treat missing hash as a mismatch - forces regeneration for old suggestions or after context refresh
			if ( ! empty( $suggested_filename ) && ( empty( $suggested_context_hash ) || $suggested_context_hash !== $current_context_signature ) ) {
				$filename_context_mismatch = true;
				$suggested_filename        = '';
			}

			$expected_filename_full = '';
			if ( ! empty( $expected_slug ) && ! empty( $extension ) ) {
				$expected_filename_full = $this->ensure_unique_filename( $expected_slug, $extension, $attachment_id );
			}

			if ( ! empty( $suggested_filename ) && ! empty( $expected_filename_full ) ) {
				$current_suggested_base  = strtolower( pathinfo( $suggested_filename, PATHINFO_FILENAME ) );
				$expected_suggested_base = strtolower( pathinfo( $expected_filename_full, PATHINFO_FILENAME ) );

				if ( $current_suggested_base !== $expected_suggested_base || $filename_context_mismatch ) {
					$suggested_filename = $expected_filename_full;
					update_post_meta( $attachment_id, '_msh_suggested_filename', $suggested_filename );
					update_post_meta( $attachment_id, 'msh_filename_last_suggested', time() );
					update_post_meta( $attachment_id, '_msh_suggested_filename_context', $current_context_signature );

					// Calculate and store confidence score
					$filename_slug    = pathinfo( $suggested_filename, PATHINFO_FILENAME );
					$confidence_score = $this->calculate_confidence_score( $attachment_id, $context_info, $filename_slug );
					$confidence_level = $this->get_confidence_level( $confidence_score );
					update_post_meta( $attachment_id, '_msh_confidence_score', $confidence_score );
					update_post_meta( $attachment_id, '_msh_confidence_level', $confidence_level );

					$filename_context_mismatch = false;
				}
			}

			// Generate suggestion if it doesn't exist yet
			if ( empty( $suggested_filename ) && ! empty( $extension ) ) {
				$slug = ! empty( $expected_slug )
					? $expected_slug
					: $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_info, $extension );

				if ( ! empty( $slug ) ) {
					if ( ! empty( $expected_filename_full ) && $slug === $expected_slug ) {
						$suggested_filename = $expected_filename_full;
					} else {
						$suggested_filename = $this->ensure_unique_filename( $slug, $extension, $attachment_id );
					}
					update_post_meta( $attachment_id, '_msh_suggested_filename', $suggested_filename );
					update_post_meta( $attachment_id, 'msh_filename_last_suggested', time() );
					update_post_meta( $attachment_id, '_msh_suggested_filename_context', $current_context_signature );

					// Calculate and store confidence score
					$filename_slug      = pathinfo( $suggested_filename, PATHINFO_FILENAME );
					$confidence_score   = $this->calculate_confidence_score( $attachment_id, $context_info, $filename_slug );
					$confidence_level   = $this->get_confidence_level( $confidence_score );
					update_post_meta( $attachment_id, '_msh_confidence_score', $confidence_score );
					update_post_meta( $attachment_id, '_msh_confidence_level', $confidence_level );

					$filename_context_mismatch = false;
				}
			}
		}

		// Retrieve confidence score for existing suggestions
		$confidence_score = (int) get_post_meta( $attachment_id, '_msh_confidence_score', true );
		$confidence_level = get_post_meta( $attachment_id, '_msh_confidence_level', true );
		if ( empty( $confidence_level ) && $confidence_score > 0 ) {
			$confidence_level = $this->get_confidence_level( $confidence_score );
		}

		$quality_note = get_post_meta( $attachment_id, '_msh_filename_quality_note', true );

		// Gather optimization metadata
		$optimized_date         = get_post_meta( $attachment_id, 'msh_optimized_date', true );
		$optimization_status    = $this->get_optimization_status( $attachment_id );
		$webp_last_converted    = (int) get_post_meta( $attachment_id, 'msh_webp_last_converted', true );
		$metadata_last_updated  = (int) get_post_meta( $attachment_id, 'msh_metadata_last_updated', true );
		$source_last_compressed = (int) get_post_meta( $attachment_id, 'msh_source_last_compressed', true );

		// Analysis only reads existing suggestions - no writing to database during analysis

		if ( $is_svg ) {
			$optimization_potential = array(
				'needs_resize'              => false,
				'current_size'              => $file_size,
				'recommended_dimensions'    => null,
				'estimated_optimal_size'    => $file_size,
				'estimated_savings_bytes'   => 0,
				'estimated_savings_percent' => 0,
			);
		} else {
			$optimization_potential = $this->calculate_optimization_potential( $file_path, $metadata, $legacy_context );
		}

		$can_regenerate_meta = $this->should_regenerate_meta( $attachment_id );
		$context_mismatch    = $metadata_context_mismatch && $can_regenerate_meta;

		if ( $has_pending_ai_meta ) {
			$context_mismatch          = false;
			$metadata_context_mismatch = false;
		}

		$pending_ai_override_allowed_statuses = array(
			'optimized',
			'ready_for_optimization',
			'metadata_missing',
			'needs_recompression',
			'needs_webp_conversion',
			'webp_timestamp_missing',
			'context_stale',
		);

		if ( $has_pending_ai_meta && in_array( $optimization_status, $pending_ai_override_allowed_statuses, true ) ) {
			$optimization_status = 'ai_pending';
		} elseif ( $context_mismatch && $optimization_status === 'optimized' ) {
			$optimization_status = 'context_stale';
		}

		// End profiling and flush results
		if ( defined( 'MSH_PROFILE' ) && MSH_PROFILE ) {
			MSH_Profiler::end( 'db_writes' );
			MSH_Profiler::end( 'total' );
			MSH_Profiler::flush(
				array(
					'attachment_id' => (int) $attachment_id,
					'file'          => $file_path,
					'ai_mode'       => $ai_options['ai_mode'] ?? 'analyze_only',
				)
			);
		}

		// PHASE 3: Calculate timings and output performance log
		$db_time    = microtime( true ) - $t_db;
		$total_time = microtime( true ) - $t_total;

		error_log( sprintf(
			'[MSH TIMING] Image #%d: prep=%.3fs ai=%.3fs db=%.3fs total=%.3fs',
			$attachment_id,
			$prep_time,
			$ai_time,
			$db_time,
			$total_time
		) );

		return array(
			'current_size_bytes'        => $file_size,
			'current_size_mb'           => round( $file_size / 1048576, 2 ),
			'current_dimensions'        => $is_svg ? 'vector' : ( $image_info[0] . 'x' . $image_info[1] ),
			'current_format'            => $image_info['mime'],
			'webp_exists'               => $webp_exists,
			'webp_savings_estimate'     => $webp_savings,
			'context'                   => $legacy_context,
			'context_details'           => $context_info,
			'context_source'            => $context_source,
			'manual_context'            => $manual_context_value,
			'auto_context'              => $auto_context_value,
			'seo_mode'                  => ! empty( $context_info['seo_mode'] ),
			'loc_mode'                  => $loc_mode,
			'context_active_label'      => $this->format_context_label( $active_context_slug ),
			'context_auto_label'        => $auto_context_value !== '' ? $this->format_context_label( $auto_context_value ) : '',
			'generated_meta'            => $generated_meta,
			'current_meta'              => $current_meta,
			'optimization_potential'    => $optimization_potential,
			'suggested_filename'        => $suggested_filename,
			'confidence_score'          => $confidence_score,
			'confidence_level'          => $confidence_level,
			'filename_quality_note'     => $quality_note,
			'optimized_date'            => $optimized_date,
			'optimization_status'       => $optimization_status,
			'webp_last_converted'       => $webp_last_converted,
			'metadata_last_updated'     => $metadata_last_updated,
			'source_last_compressed'    => $source_last_compressed,
			'has_pending_ai_meta'       => $has_pending_ai_meta,
			'pending_ai_fields'         => $pending_ai_fields,
			'context_signature'         => $current_context_signature,
			'metadata_context_hash'     => $stored_meta_hash,
			'metadata_context_mismatch' => $metadata_context_mismatch,
			'filename_context_mismatch' => $filename_context_mismatch,
			'context_mismatch'          => $context_mismatch,
			'metadata_source'           => $metadata_source,
		);
	}

	/**
	 * Estimate potential WebP savings when conversion hasn't run yet
	 */
	private function estimate_webp_savings( $file_size, $mime_type ) {
		$file_size = (int) $file_size;

		if ( $file_size <= 0 ) {
			return array(
				'source_size'               => 0,
				'estimated_webp_size'       => 0,
				'estimated_savings_bytes'   => 0,
				'estimated_savings_percent' => 0,
			);
		}

		$mime_type = strtolower( (string) $mime_type );

		// Average compression ratios based on format benchmarking
		$compression_map = array(
			'image/jpeg' => 0.35,
			'image/jpg'  => 0.35,
			'image/png'  => 0.45,
			'image/gif'  => 0.55,
			'image/webp' => 1.00,
		);

		$ratio = isset( $compression_map[ $mime_type ] ) ? (float) $compression_map[ $mime_type ] : 0.40;
		$ratio = max( 0.05, min( 1.0, $ratio ) );

		$estimated_webp_size = (int) round( $file_size * $ratio );
		$estimated_webp_size = max( 0, min( $file_size, $estimated_webp_size ) );

		$estimated_savings_bytes   = max( 0, $file_size - $estimated_webp_size );
		$estimated_savings_percent = $file_size > 0
			? (int) round( ( $estimated_savings_bytes / $file_size ) * 100 )
			: 0;

		return array(
			'source_size'               => $file_size,
			'estimated_webp_size'       => $estimated_webp_size,
			'estimated_savings_bytes'   => $estimated_savings_bytes,
			'estimated_savings_percent' => max( 0, min( 100, $estimated_savings_percent ) ),
		);
	}

	/**
	 * Recalculate optimization potential with healthcare-aware sizes
	 */
	private function calculate_optimization_potential( $file_path, $metadata, $context_slug = null ) {
		$current_size = @filesize( $file_path );
		$current_size = $current_size !== false ? (int) $current_size : 0;

		if ( $current_size <= 0 || empty( $metadata ) ) {
			return array(
				'needs_resize'              => false,
				'current_size'              => $current_size,
				'recommended_dimensions'    => null,
				'estimated_savings_bytes'   => 0,
				'estimated_savings_percent' => 0,
			);
		}

		$dimensions = array(
			'width'  => $metadata['width'] ?? 0,
			'height' => $metadata['height'] ?? 0,
		);

		$recommended  = $this->get_recommended_dimensions( $context_slug, $dimensions );
		$needs_resize = $this->needs_resize( $dimensions, $recommended );

		if ( ! $needs_resize ) {
			return array(
				'needs_resize'              => false,
				'current_size'              => $current_size,
				'recommended_dimensions'    => $recommended,
				'estimated_savings_bytes'   => 0,
				'estimated_savings_percent' => 0,
			);
		}

		$estimated_optimal_size    = $this->estimate_optimal_filesize( $current_size, $recommended, $dimensions );
		$estimated_optimal_size    = max( 0, min( $current_size, $estimated_optimal_size ) );
		$estimated_savings_bytes   = max( 0, $current_size - $estimated_optimal_size );
		$estimated_savings_percent = $current_size > 0
			? (int) round( ( $estimated_savings_bytes / $current_size ) * 100 )
			: 0;

		return array(
			'needs_resize'              => true,
			'current_size'              => $current_size,
			'recommended_dimensions'    => $recommended,
			'estimated_optimal_size'    => $estimated_optimal_size,
			'estimated_savings_bytes'   => $estimated_savings_bytes,
			'estimated_savings_percent' => max( 0, min( 100, $estimated_savings_percent ) ),
		);
	}

	private function get_recommended_dimensions( $context_slug, array $dimensions ) {
		$defaults = array(
			'width'  => 1200,
			'height' => 800,
		);

		if ( ! $context_slug ) {
			return $defaults;
		}

		$recommendations = array(
			'homepage_hero' => array(
				'width'  => 1400,
				'height' => 750,
			),
			'service_page'  => array(
				'width'  => 900,
				'height' => 600,
			),
			'team_photo'    => array(
				'width'  => 600,
				'height' => 800,
			),
			'testimonial'   => array(
				'width'  => 600,
				'height' => 600,
			),
			'facility'      => array(
				'width'  => 1200,
				'height' => 800,
			),
			'equipment'     => array(
				'width'  => 900,
				'height' => 600,
			),
			'blog_featured' => array(
				'width'  => 1200,
				'height' => 675,
			),
		);

		if ( ! isset( $recommendations[ $context_slug ] ) ) {
			return $defaults;
		}

		$recommended = $recommendations[ $context_slug ];

		// Ensure we don't suggest an upscale
		$recommended['width']  = min( $recommended['width'], (int) ( $dimensions['width'] ?? $recommended['width'] ) );
		$recommended['height'] = min( $recommended['height'], (int) ( $dimensions['height'] ?? $recommended['height'] ) );

		return $recommended;
	}

	private function needs_resize( array $dimensions, array $recommended ) {
		$width  = (int) ( $dimensions['width'] ?? 0 );
		$height = (int) ( $dimensions['height'] ?? 0 );

		if ( $width === 0 || $height === 0 ) {
			return false;
		}

		return $width > $recommended['width'] + 40 || $height > $recommended['height'] + 40;
	}

	private function estimate_optimal_filesize( $current_size, array $recommended, array $dimensions ) {
		$width  = (int) ( $dimensions['width'] ?? 1 );
		$height = (int) ( $dimensions['height'] ?? 1 );

		if ( $width <= 0 || $height <= 0 ) {
			return $current_size;
		}

		$current_pixels = $width * $height;
		$target_pixels  = max( 1, $recommended['width'] * $recommended['height'] );
		$scale_factor   = $target_pixels / $current_pixels;

		$estimated = $current_size * $scale_factor;
		$estimated = $estimated * 1.1; // include buffer for quality retention

		return (int) round( $estimated );
	}

	/**
	 * Determine image context based on usage
	 */
	private function determine_image_context( $attachment_id ) {
		global $wpdb;

		// Check if it's a featured image
		$featured_posts = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT posts.post_type, posts.post_title 
            FROM {$wpdb->postmeta} meta 
            JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id 
            WHERE meta.meta_key = '_thumbnail_id' 
            AND meta.meta_value = %d 
            AND posts.post_status = 'publish'
        ",
				$attachment_id
			)
		);

		if ( $featured_posts ) {
			foreach ( $featured_posts as $post ) {
				if ( strpos( strtolower( $post->post_title ), 'home' ) !== false ) {
					return 'homepage_hero';
				}
				if ( $post->post_type === 'msh_service' ) {
					return 'service_page';
				}
				if ( $post->post_type === 'msh_team_member' ) {
					return 'team_photo';
				}
				if ( $post->post_type === 'post' ) {
					return 'blog_featured';
				}
			}
		}

		// Check content usage
		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $file_path ) {
			$posts_using = $wpdb->get_results(
				$wpdb->prepare(
					"
                SELECT post_type, post_title 
                FROM {$wpdb->posts} 
                WHERE post_content LIKE %s 
                AND post_status = 'publish'
            ",
					'%' . $file_path . '%'
				)
			);

			foreach ( $posts_using as $post ) {
				$title_lower = strtolower( $post->post_title );
				if ( strpos( $title_lower, 'testimonial' ) !== false ||
					strpos( $title_lower, 'patient' ) !== false ) {
					return 'testimonial';
				}
				if ( strpos( $title_lower, 'facility' ) !== false ||
					strpos( $title_lower, 'office' ) !== false ||
					strpos( $title_lower, 'clinic' ) !== false ) {
					return 'facility';
				}
			}
		}

		return 'blog_featured'; // Default context
	}

	/**
	 * Calculate confidence score for metadata/filename suggestion.
	 * Score range: 0-100
	 * - 0-39: Low confidence (unreliable)
	 * - 40-69: Medium confidence (acceptable)
	 * - 70-100: High confidence (reliable)
	 *
	 * @since 1.2.0
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $context       Context array with scene detection data.
	 * @param string $filename      Optional. Generated filename for quality scoring.
	 * @return int Confidence score (0-100).
	 */
	private function calculate_confidence_score( $attachment_id, $context, $filename = '' ) {
		$score = 50; // Base score

		// Manual override is always high confidence
		if ( ! empty( $context['manual'] ) && $context['manual'] === true ) {
			return 100;
		}

		// Scene detection quality
		if ( ! empty( $context['type'] ) && $context['type'] !== 'unknown' ) {
			$score += 10;
			// Token-based scene matching adds extra confidence
			if ( ! empty( $context['scene_source'] ) && $context['scene_source'] === 'token_match' ) {
				$score += 10;
			}
		} else {
			$score -= 10;
		}

		// Featured image context adds confidence
		if ( ! empty( $context['page_heading'] ) ) {
			$score += 5;
		}
		if ( ! empty( $context['page_excerpt'] ) ) {
			$score += 3;
		}

		// Image caption adds confidence
		if ( ! empty( $context['image_caption'] ) ) {
			$score += 5;
		}

		// Industry alignment check
		if ( MSH_Image_Optimizer_Context_Helper::is_healthcare_industry( $context['industry'] ) ) {
			$healthcare_scenes = array( 'team', 'testimonial', 'facility', 'equipment', 'clinical', 'service-icon' );
			if ( in_array( $context['type'], $healthcare_scenes, true ) ) {
				$score += 5;
			}
		}

		// Filename quality
		if ( ! empty( $filename ) ) {
			$filename_score = $this->score_filename_quality( $filename );
			$score         += $filename_score;
		}

		// Clamp to 0-100 range
		return max( 0, min( 100, $score ) );
	}

	/**
	 * Score filename quality based on structure and word count.
	 *
	 * @since 1.2.0
	 * @param string $filename Generated filename slug.
	 * @return int Score adjustment (-10 to +10).
	 */
	private function score_filename_quality( $filename ) {
		$score      = 0;
		$word_count = count( explode( '-', $filename ) );

		// Ideal word count: 3-4 words
		if ( $word_count >= 3 && $word_count <= 4 ) {
			$score += 5;
		} elseif ( $word_count < 3 ) {
			$score -= 5; // Too short
		} elseif ( $word_count > 6 ) {
			$score -= 5; // Too long
		}

		// Check for generic words (reduce confidence)
		$generic_words = array( 'image', 'photo', 'picture', 'untitled', 'attachment', 'file' );
		foreach ( $generic_words as $word ) {
			if ( strpos( $filename, $word ) !== false ) {
				$score -= 5;
				break;
			}
		}

		// Check for location/business name (increase confidence)
		if ( $this->business_name !== '' ) {
			$business_slug = MSH_Image_Optimizer_Context_Helper::slugify( $this->business_name );
			if ( strpos( $filename, $business_slug ) !== false ) {
				$score += 5;
			}
		}

		return $score;
	}

	/**
	 * Get confidence level label from score.
	 *
	 * @since 1.2.0
	 * @param int $score Confidence score (0-100).
	 * @return string Confidence level: 'high', 'medium', or 'low'.
	 */
	private function get_confidence_level( $score ) {
		if ( $score >= 70 ) {
			return 'high';
		} elseif ( $score >= 40 ) {
			return 'medium';
		} else {
			return 'low';
		}
	}

	/**
	 * Generate business-focused filename
	 */
	private function ensure_unique_filename( $base_name, $extension, $attachment_id ) {
		$base_name = $this->limit_slug_words( $base_name );
		$filename = $base_name . '.' . $extension;

		// Check if this exact filename already exists in WordPress
		$existing_attachment = $this->get_attachment_by_filename( $filename );

		// Also check for base name conflicts across different extensions
		$base_conflicts = $this->get_attachments_with_base_name( $base_name );
		$base_conflicts = array_filter(
			array_map( 'intval', $base_conflicts ),
			static function ( $id ) use ( $attachment_id ) {
				return $id !== (int) $attachment_id;
			}
		);

		if ( ( $existing_attachment && $existing_attachment !== $attachment_id ) || ! empty( $base_conflicts ) ) {
			// Also check if this suggestion is already suggested for another file
			$suggestion_conflicts = $this->get_attachments_with_suggestion( $filename );

			// Try with short attachment ID suffix first
			$short_id = $attachment_id;
			$filename = $base_name . '-' . $short_id . '.' . $extension;

			// If still conflicts, add timestamp
			$existing_check = $this->get_attachment_by_filename( $filename );
			if ( $existing_check && $existing_check !== $attachment_id ) {
				$timestamp = substr( time(), -4 ); // Last 4 digits of timestamp
				$filename  = $base_name . '-' . $timestamp . '.' . $extension;
			}
		} else {
			// Even if filename doesn't exist yet, check for duplicate suggestions
			$suggestion_conflicts = $this->get_attachments_with_suggestion( $filename );
			if ( ! empty( $suggestion_conflicts ) && ! in_array( $attachment_id, $suggestion_conflicts ) ) {
				$short_id = $attachment_id;
				$filename = $base_name . '-' . $short_id . '.' . $extension;
			}
		}

		$this->log_debug( "MSH Uniqueness: AttachmentID=$attachment_id, BaseName='$base_name', FinalFilename='$filename'" );
		return $filename;
	}

	private function limit_slug_words( $slug, $max_words = 4 ) {
		$slug = sanitize_title_with_dashes( $slug );
		if ( $slug === '' ) {
			return $slug;
		}

		$parts     = preg_split( '/-+/', strtolower( $slug ) );
		$stopwords = array( 'and', 'with', 'the', 'for', 'of', 'a', 'an', 'to', 'in', 'at', 'on', 'by', 'from', 'about' );
		$result    = array();

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part === '' || in_array( $part, $stopwords, true ) || is_numeric( $part ) ) {
				continue;
			}
			$result[] = $part;
			if ( count( $result ) >= max( 1, (int) $max_words ) ) {
				break;
			}
		}

		if ( empty( $result ) ) {
			$result = array_slice( array_filter( $parts ), 0, 1 );
		}

		return implode( '-', $result );
	}

	/**
	 * Get attachments that have the same base filename (ignoring extension)
	 */
	private function get_attachments_with_base_name( $base_name ) {
		global $wpdb;

		$like_pattern = '%' . $wpdb->esc_like( $base_name ) . '.%';
		$results      = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_wp_attached_file', '_msh_suggested_filename', '_msh_suggested_filename_context')
            AND meta_value LIKE %s
        ",
				$like_pattern
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Get attachments that already have this filename as a suggestion
	 */
	private function get_attachments_with_suggestion( $filename ) {
		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_msh_suggested_filename'
            AND meta_value = %s
        ",
				$filename
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Check if suggested filename is worse than current filename
	 */
	private function is_filename_worse( $current, $suggested ) {
		// Remove extensions for comparison
		$current_base   = pathinfo( $current, PATHINFO_FILENAME );
		$suggested_base = pathinfo( $suggested, PATHINFO_FILENAME );

		// Score filenames (higher = better)
		$current_score   = $this->score_filename_quality( $current_base );
		$suggested_score = $this->score_filename_quality( $suggested_base );

		$this->log_debug( "MSH Quality Check: Current='$current_base' (score: $current_score), Suggested='$suggested_base' (score: $suggested_score)" );

		// Don't suggest if it's significantly worse (threshold: 4 points)
		return ( $current_score - $suggested_score ) >= 4;
	}


	/**
	 * Find attachment by filename
	 */
	private function get_attachment_by_filename( $filename ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		return $result ? (int) $result : null;
	}

	private function generate_business_filename( $attachment_id, $context ) {
		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( empty( $file_path ) || ! is_string( $file_path ) ) {
			$this->log_debug( "MSH Optimizer: Empty or invalid file path for attachment ID: $attachment_id" );
			return false;
		}

		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		if ( empty( $extension ) ) {
			$this->log_debug( "MSH Optimizer: No file extension found for attachment ID: $attachment_id, file: $file_path" );
			return false;
		}

		$extension       = strtolower( $extension );
		$context_details = $this->contextual_meta_generator->detect_context( $attachment_id );

		// Legacy callers may still provide a context slug – honour it if type missing
		if ( ! empty( $context ) && empty( $context_details['type'] ) ) {
			$context_details['type'] = $context;
		}

		$slug = $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_details, $extension );
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $context ?: basename( $file_path, '.' . $extension ) );
		}

		return $this->ensure_unique_filename( $slug, $extension, $attachment_id );
	}

	/**
	 * Check if meta should be regenerated (protect manual edits)
	 */
	private function should_regenerate_meta( $attachment_id, $field = null ) {
		$metadata_source = get_post_meta( $attachment_id, 'msh_metadata_source', true );

		// Never overwrite manual edits
		if ( $metadata_source === 'manual_edit' ) {
			return false;
		}

		return true;
	}

	private function get_context_choices() {
		if ( class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
			$active_context = MSH_Image_Optimizer_Context_Helper::get_active_context();
			$industry       = isset( $active_context['industry'] ) ? $active_context['industry'] : '';

			return MSH_Image_Optimizer_Context_Helper::get_context_choice_map( $industry );
		}

		return array(
			''             => __( 'Auto-detect (default)', 'msh-image-optimizer' ),
			'business'     => __( 'Business / General', 'msh-image-optimizer' ),
			'team'         => __( 'Team Member', 'msh-image-optimizer' ),
			'testimonial'  => __( 'Customer Testimonial', 'msh-image-optimizer' ),
			'service-icon' => __( 'Icon / Graphic', 'msh-image-optimizer' ),
			'facility'     => __( 'Workspace / Office', 'msh-image-optimizer' ),
			'equipment'    => __( 'Product / Equipment', 'msh-image-optimizer' ),
			'clinical'     => __( 'Service Highlight', 'msh-image-optimizer' ),
		);
	}

	private function format_context_label( $slug ) {
		$slug = (string) $slug;

		if ( $slug === '' ) {
			return __( 'Auto-detect (default)', 'msh-image-optimizer' );
		}

		$choices = $this->get_context_choices();
		if ( isset( $choices[ $slug ] ) ) {
			return $choices[ $slug ];
		}

		return $this->humanize_label( $slug, __( 'Unknown', 'msh-image-optimizer' ) );
	}

	private function humanize_label( $value, $fallback = '' ) {
		if ( ! is_string( $value ) || trim( $value ) === '' ) {
			return $fallback;
		}

		$label = str_replace( array( '-', '_' ), ' ', strtolower( $value ) );
		$label = preg_replace( '/\s+/', ' ', $label );

		$label = trim( $label );
		if ( $label === '' ) {
			return $fallback;
		}

		return ucwords( $label );
	}

	/**
	 * Inject the manual context selector into the attachment modal.
	 *
	 * @since 1.2.0
	 *
	 * @param array   $form_fields Existing attachment form fields.
	 * @param WP_Post $post        Attachment object being edited.
	 * @return array Modified form fields.
	 */
	public function add_context_attachment_field( $form_fields, $post ) {
		if ( strpos( $post->post_mime_type, 'image/' ) !== 0 ) {
			return $form_fields;
		}

		$choices         = $this->get_context_choices();
		$manual_value    = get_post_meta( $post->ID, '_msh_context', true );
		$manual_value    = is_string( $manual_value ) ? trim( $manual_value ) : '';
		$auto_value      = get_post_meta( $post->ID, '_msh_auto_context', true );
		$context_details = $this->contextual_meta_generator->detect_context( $post->ID );
		$context_source  = ! empty( $context_details['manual'] ) ? 'manual' : 'auto';

		$select = '<select class="msh-context-select" name="attachments[' . esc_attr( $post->ID ) . '][msh_context]" style="width:100%">';
		foreach ( $choices as $key => $label ) {
			$selected = selected( $manual_value, $key, false );
			$select  .= '<option value="' . esc_attr( $key ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
		}
		$select .= '</select>';

		$active_label = $manual_value !== ''
			? $this->format_context_label( $manual_value )
			: $this->format_context_label( $context_details['type'] ?? $auto_value );

		$auto_label_value = $auto_value !== '' ? $this->format_context_label( $auto_value ) : '';

		$chips   = array();
		$chips[] = '<span class="msh-context-chip ' . ( $context_source === 'manual' ? 'manual' : 'auto' ) . '">' .
			esc_html( $context_source === 'manual' ? __( 'Manual override', 'msh-image-optimizer' ) : __( 'Auto-detected', 'msh-image-optimizer' ) ) . '</span>';

		if ( $active_label ) {
			$chips[] = '<span class="msh-context-chip context">' . esc_html( $active_label ) . '</span>';
		}

		if ( $context_source === 'manual' && $auto_label_value && $manual_value !== $auto_value ) {
			$chips[] = '<span class="msh-context-chip auto-note">' .
				esc_html( sprintf( __( 'Auto suggestion: %s', 'msh-image-optimizer' ), $auto_label_value ) ) . '</span>';
		}

		$detail_items = array();
		if ( ! empty( $context_details['service'] ) ) {
			$detail_items[] = esc_html(
				sprintf(
					__( 'Service focus: %s', 'msh-image-optimizer' ),
					$this->contextual_meta_generator->format_service_label( $context_details['service'] )
				)
			);
		}
		if ( ! empty( $context_details['asset'] ) ) {
			$detail_items[] = esc_html(
				sprintf(
					__( 'Asset type: %s', 'msh-image-optimizer' ),
					$this->humanize_label( $context_details['asset'], __( 'General', 'msh-image-optimizer' ) )
				)
			);
		}
		if ( ! empty( $context_details['product_type'] ) ) {
			$detail_items[] = esc_html(
				sprintf(
					__( 'Product indicator: %s', 'msh-image-optimizer' ),
					$this->humanize_label( $context_details['product_type'], __( 'Medical', 'msh-image-optimizer' ) )
				)
			);
		}
		if ( ! empty( $context_details['icon_type'] ) ) {
			$detail_items[] = esc_html(
				sprintf(
					__( 'Icon category: %s', 'msh-image-optimizer' ),
					$this->humanize_label( $context_details['icon_type'], __( 'Clinical', 'msh-image-optimizer' ) )
				)
			);
		}
		if ( ! empty( $context_details['page_title'] ) ) {
			$detail_items[] = esc_html(
				sprintf(
					__( 'Appears on: %s', 'msh-image-optimizer' ),
					$context_details['page_title']
				)
			);
		}

		$details_html = '';
		if ( ! empty( $detail_items ) ) {
			$details_html = '<ul class="msh-context-details"><li>' . implode( '</li><li>', $detail_items ) . '</li></ul>';
		}

		$primary_description = $context_source === 'manual'
			? __( 'The optimizer will honour this manual context until you switch back to Auto-detect.', 'msh-image-optimizer' )
			: __( 'Auto-detect uses usage data, taxonomies, and filenames to pick the best context. Select a manual option to lock it in.', 'msh-image-optimizer' );

		$auto_description = '';
		if ( $context_source === 'manual' ) {
			$auto_description = $auto_label_value
				? sprintf( __( 'Last auto-detected context: %s', 'msh-image-optimizer' ), $auto_label_value )
				: __( 'Run the analyzer to record the latest auto-detected context for comparison.', 'msh-image-optimizer' );
			$auto_description = '<p class="description">' . esc_html( $auto_description ) . '</p>';
		}

		static $styles_injected = false;
		$style_block            = '';
		if ( ! $styles_injected ) {
			$styles_injected = true;
			$style_block     = '<style id="msh-context-field-styles">'
				. '.msh-context-field{margin-top:8px;padding:12px;border:1px solid #dcdcde;border-radius:6px;background:#f8f9fb;}'
				. '.msh-context-chips{margin-bottom:8px;}'
				. '.msh-context-chip{display:inline-block;margin:0 6px 6px 0;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid #c3c4c7;background:#ffffff;color:#1d2327;}'
				. '.msh-context-chip.manual{background:#fde8e6;border-color:#f0b8af;color:#a4281f;}'
				. '.msh-context-chip.auto{background:#ecfbea;border-color:#b4e1b1;color:#116b25;}'
				. '.msh-context-chip.context{background:#fff;border-color:#c3c4c7;color:#1d2327;}'
				. '.msh-context-chip.auto-note{background:#eef2ff;border-color:#c0c7f8;color:#1b3f91;}'
				. '.msh-context-chip.pending{background:#fef7e5;border-color:#f7d48b;color:#7a4b00;}'
				. '.msh-context-details{margin:8px 0 0 0;padding-left:18px;font-size:12px;color:#1d2327;}'
				. '.msh-context-details li{margin-bottom:4px;}'
				. '.msh-context-select{margin-bottom:6px;}'
				. '</style>';
		}

		$html = $style_block . '<div class="msh-context-field">'
			. '<div class="msh-context-chips">' . implode( '', $chips ) . '</div>'
			. '<label class="screen-reader-text" for="msh-context-' . esc_attr( $post->ID ) . '">' . esc_html__( 'Image Context', 'msh-image-optimizer' ) . '</label>'
			. str_replace( '<select', '<select id="msh-context-' . esc_attr( $post->ID ) . '"', $select )
			. '<p class="description">' . esc_html( $primary_description ) . '</p>'
			. $auto_description
			. $details_html
			. '</div>';

		$form_fields['msh_context'] = array(
			'label' => __( 'Image Context', 'msh-image-optimizer' ),
			'input' => 'html',
			'helps' => '',
			'html'  => $html,
		);

		return $form_fields;
	}

	/**
	 * Persist the manual context selection from the attachment modal.
	 *
	 * @since 1.2.0
	 *
	 * @param array $post       Raw attachment post data.
	 * @param array $attachment Attachment form submission data.
	 * @return array Filtered post data.
	 */
	public function save_context_attachment_field( $post, $attachment ) {
		if ( isset( $attachment['msh_context'] ) ) {
			$choices = $this->get_context_choices();
			$value   = sanitize_text_field( $attachment['msh_context'] );
			if ( ! array_key_exists( $value, $choices ) ) {
				$value = '';
			}

			// Check if category actually changed
			$old_value = get_post_meta( $post['ID'], '_msh_context', true );
			$category_changed = ( $old_value !== $value );

			if ( $value !== '' ) {
				update_post_meta( $post['ID'], '_msh_context', $value );
			} else {
				delete_post_meta( $post['ID'], '_msh_context' );
			}

			// Remove deprecated metadata keys introduced in earlier batches
			delete_post_meta( $post['ID'], '_msh_manual_edit' );
			delete_post_meta( $post['ID'], 'msh_context_last_manual_update' );

			// If category changed, trigger automatic AI regeneration
			if ( $category_changed ) {
				error_log( "[MSH] Category changed from '{$old_value}' to '{$value}' for attachment {$post['ID']} - triggering AI regeneration" );

				// Check if AI is enabled
				$ai_interest = get_option( 'msh_ai_interest', '' );
				if ( $ai_interest === 'yes' ) {
					// Trigger AI regeneration for this single attachment
					$this->maybe_generate_metadata( $post['ID'], array(
						'ai_regeneration' => true,
						'ai_mode' => 'overwrite',
						'ai_fields' => array( 'title', 'alt_text', 'caption', 'description' ),
						'language' => 'en',
					) );
				}
			}
		}

		return $post;
	}

	/**
	 * Generate clinical meta using templates
	 */
	private function validate_and_truncate_meta( $meta_data ) {
		$limits    = array(
			'title'       => 60,
			'caption'     => 155,
			'alt_text'    => 125,
			'description' => 250,
		);
		$validated = array();

		foreach ( $meta_data as $field => $content ) {
			// Skip non-string values (like keywords array) - PHP 8 compatibility
			if ( ! is_string( $content ) ) {
				$validated[ $field ] = $content;
				continue;
			}

			if ( strlen( $content ) > $limits[ $field ] ) {
				$content = $this->smart_truncate( $content, $limits[ $field ] );
			}

			$quality_score = $this->score_meta_quality( $content );
			if ( $quality_score < 70 ) {
				$this->log_debug( "MSH Optimizer: Low quality meta generated for $field: $content (score: $quality_score)" );
			}

			$validated[ $field ] = $content;
		}

		return $validated;
	}

	/**
	 * Smart truncation preserving clinical terms
	 */
	private function smart_truncate( $text, $limit ) {
		if ( strlen( $text ) <= $limit ) {
			return $text;
		}

		$truncated  = substr( $text, 0, $limit );
		$last_space = strrpos( $truncated, ' ' );

		if ( $last_space !== false ) {
			$truncated = substr( $truncated, 0, $last_space );
		}

		// Preserve essential terms (industry-agnostic)
		$essential_terms = array( 'professional', 'clinic', 'services', 'center' );
		foreach ( $essential_terms as $term ) {
			if ( strpos( $text, $term ) !== false && strpos( $truncated, $term ) === false ) {
				$term_pos = strpos( $text, $term );
				if ( $term_pos + strlen( $term ) <= $limit ) {
					$truncated = substr( $text, 0, $term_pos + strlen( $term ) );
				}
			}
		}

		return trim( $truncated );
	}

	/**
	 * Score meta quality
	 */
	private function score_meta_quality( $content ) {
		$score = 100;

		$blacklist = array( 'trusted partner', 'comprehensive care', 'healthcare services' );
		foreach ( $blacklist as $phrase ) {
			if ( stripos( $content, $phrase ) !== false ) {
				$score -= 25;
			}
		}

		$priority_terms = array( 'professional', 'services', 'clinic', 'center', 'practice', 'team' );
		foreach ( $priority_terms as $term ) {
			if ( stripos( $content, $term ) !== false ) {
				$score += 8;
			}
		}

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Generate clinical caption
	 */
	private function generate_title( $attachment_id, $legacy_context = null ) {
		$context_info = $this->contextual_meta_generator->detect_context( $attachment_id );
		$meta         = $this->contextual_meta_generator->generate_meta_fields( $attachment_id, $context_info );

		if ( ! empty( $meta['title'] ) ) {
			return $meta['title'];
		}

		$active_context = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
			? MSH_Image_Optimizer_Context_Helper::get_active_context()
			: array();

		$business_name = ! empty( $active_context['business_name'] )
			? $active_context['business_name']
			: __( 'Brand', 'msh-image-optimizer' );

		return sprintf( __( '%s Brand Overview', 'msh-image-optimizer' ), $business_name );
	}

	private function generate_caption( $attachment_id, $legacy_context = null ) {
		$context_info = $this->contextual_meta_generator->detect_context( $attachment_id );
		$meta         = $this->contextual_meta_generator->generate_meta_fields( $attachment_id, $context_info );

		if ( ! empty( $meta['caption'] ) ) {
			return $meta['caption'];
		}

		$active_context = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
			? MSH_Image_Optimizer_Context_Helper::get_active_context()
			: array();

		$business_name = ! empty( $active_context['business_name'] )
			? $active_context['business_name']
			: __( 'the brand', 'msh-image-optimizer' );

		return sprintf( __( 'Visual highlight for %s.', 'msh-image-optimizer' ), $business_name );
	}


	private function generate_alt_text( $attachment_id, $legacy_context = null ) {
		$context_info = $this->contextual_meta_generator->detect_context( $attachment_id );
		$meta         = $this->contextual_meta_generator->generate_meta_fields( $attachment_id, $context_info );

		if ( ! empty( $meta['alt_text'] ) ) {
			return $meta['alt_text'];
		}

		$active_context = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
			? MSH_Image_Optimizer_Context_Helper::get_active_context()
			: array();

		$business_name = ! empty( $active_context['business_name'] )
			? $active_context['business_name']
			: __( 'the brand', 'msh-image-optimizer' );

		return sprintf( __( 'Brand imagery for %s.', 'msh-image-optimizer' ), $business_name );
	}


	private function generate_description( $attachment_id, $legacy_context = null ) {
		$context_info = $this->contextual_meta_generator->detect_context( $attachment_id );
		$meta         = $this->contextual_meta_generator->generate_meta_fields( $attachment_id, $context_info );

		if ( ! empty( $meta['description'] ) ) {
			return $meta['description'];
		}

		$active_context = class_exists( 'MSH_Image_Optimizer_Context_Helper' )
			? MSH_Image_Optimizer_Context_Helper::get_active_context()
			: array();

		$business_name  = ! empty( $active_context['business_name'] )
			? $active_context['business_name']
			: __( 'the brand', 'msh-image-optimizer' );
		$industry_label = ! empty( $active_context['industry'] )
			? MSH_Image_Optimizer_Context_Helper::lookup_label( 'industry', $active_context['industry'] )
			: __( 'professional services', 'msh-image-optimizer' );

		return sprintf(
			__( '%1$s provides %2$s with customer-ready visual assets.', 'msh-image-optimizer' ),
			$business_name,
			strtolower( $industry_label )
		);
	}


	/**
	 * AJAX handler: analyse library images and return optimisation status.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_analyze_images() {
		// DIAGNOSTIC: Log handler entry
		error_log( '[MSH DEBUG] ajax_analyze_images handler called' );

		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Check for force refresh parameter
		$force_refresh = isset( $_POST['force_refresh'] ) && $_POST['force_refresh'] === 'true';

		// Check for AI regeneration params
		$ai_scope              = isset( $_POST['ai_scope'] ) ? sanitize_text_field( $_POST['ai_scope'] ) : null;
		// For batch analyze (non-regeneration), use global AI mode setting; for AI regeneration, use POST param or default to 'fill-empty'
		$global_ai_mode        = get_option( 'msh_ai_mode', 'manual' );
		$ai_mode               = isset( $_POST['ai_mode'] ) ? sanitize_text_field( $_POST['ai_mode'] ) : ( empty( $ai_scope ) ? $global_ai_mode : 'fill-empty' );
		$ai_fields             = isset( $_POST['ai_fields'] ) && is_array( $_POST['ai_fields'] ) ? array_map( 'sanitize_text_field', $_POST['ai_fields'] ) : array();
		$ai_language           = isset( $_POST['ai_language'] ) ? sanitize_text_field( $_POST['ai_language'] ) : 'auto';
		$active_profile_record = MSH_Image_Optimizer_Context_Helper::get_active_profile();
		$profile_locale        = isset( $active_profile_record['locale'] ) ? sanitize_text_field( $active_profile_record['locale'] ) : '';
		$is_ai_regeneration    = ! empty( $ai_scope );

		// Skip cache for AI regeneration
		if ( $is_ai_regeneration ) {
			$force_refresh = true;
		}

		// URGENT: Quick cache to stop analysis time waste
		$cache_key = 'msh_analysis_cache_v' . self::ANALYSIS_CACHE_VERSION . '_' . md5( 'latest_analysis' );

		if ( $force_refresh ) {
			delete_transient( $cache_key );
			$this->log_debug( 'MSH: Cache cleared - performing fresh analysis' );
		}

		$cached_result = get_transient( $cache_key );

		if ( $cached_result !== false && ! $force_refresh ) {
			$cache_age = time() - $cached_result['timestamp'];
			$this->log_debug( 'MSH: Using cached analysis from ' . human_time_diff( $cached_result['timestamp'] ) . ' ago' );

			// Use cache if less than 30 minutes old
			if ( $cache_age < 1800 ) {
				wp_send_json_success( $cached_result['data'] );
				return;
			}
		}

		$start_time = microtime( true );
		$this->log_debug( 'MSH: Starting fresh analysis (no valid cache found)' );

		// Debug: First check total images
		global $wpdb;
		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';
		$total_images    = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
				'attachment',
				$image_mime_like
			)
		);

		// Get images based on AI regeneration scope or normal published analysis
		if ( $is_ai_regeneration ) {
			$images = $this->get_images_for_ai_regeneration( $ai_scope, $ai_mode, $ai_fields );
			$this->log_debug( "MSH: AI Regeneration mode - scope: $ai_scope, found " . count( $images ) . ' images' );
		} else {
			$images = $this->get_published_images();
		}

		if ( $total_images === 0 ) {
			$total_images = count( $images );
		}
		$analysis_results = array();
		$current_index    = 0;
		$total_to_process = count( $images );

		// Telemetry metrics for batch quality tracking
		$telemetry = array(
			'confidence_scores'        => array(),
			'brand_name_assumed_count' => 0,
			'decorative_image_count'   => 0,
			'text_detected_count'      => 0,
			'low_confidence_count'     => 0,
			'ai_success_count'         => 0,
			'ai_fallback_count'        => 0,
		);

		// For AI regeneration with many images, increase timeout
		if ( $is_ai_regeneration && $total_to_process > 10 ) {
			set_time_limit( 3600 ); // 1 hour
			$this->log_debug( "MSH: AI Regeneration - Extended timeout for {$total_to_process} images" );
		}

		try {
			foreach ( $images as $image ) {
				++$current_index;

				// Fix missing file_path for images that don't have _wp_attached_file meta
				if ( empty( $image['file_path'] ) ) {
					$attached_file = get_attached_file( $image['ID'] );
					if ( $attached_file ) {
						$upload_dir      = wp_get_upload_dir();
						$uploads_basedir = $upload_dir['basedir'];
						if ( strpos( $attached_file, $uploads_basedir ) === 0 ) {
							$image['file_path'] = str_replace( $uploads_basedir . '/', '', $attached_file );
							$this->log_debug( "MSH File Path Fix: ID {$image['ID']} - recovered file_path: {$image['file_path']}" );
						}
					}
				}

				// Log progress for AI regeneration
				if ( $is_ai_regeneration && $current_index % 5 === 0 ) {
					$percent = round( ( $current_index / $total_to_process ) * 100 );
					$this->log_debug( "MSH: AI Regeneration progress: {$current_index}/{$total_to_process} ({$percent}%) - Processing attachment {$image['ID']}" );
				}

				// ALWAYS pass ai_mode (from global setting or POST param) so AI can run during batch analyze
				$ai_options = array(
					'ai_mode' => $ai_mode,
				);

				// Add AI regeneration-specific params if this is an AI regeneration request
				if ( $is_ai_regeneration ) {
					$ai_options['ai_regeneration'] = true;
					$ai_options['ai_fields']       = $ai_fields;
					$ai_options['language']        = $ai_language;
					$ai_options['profile_locale']  = $profile_locale;
				}

				$analysis = $this->analyze_single_image( $image['ID'], $ai_options );

				// CRITICAL FIX: Check if analysis returned an error before continuing
				if ( isset( $analysis['error'] ) ) {
					error_log( sprintf(
						'[MSH ERROR] analyze_single_image failed for attachment %d: %s (code: %s)',
						$image['ID'],
						$analysis['error'],
						$analysis['error_code'] ?? 'unknown'
					) );
					// Add error result to output and continue to next image
					$analysis_results[] = array_merge( $image, $analysis );
					$telemetry['ai_fallback_count']++;
					continue;
				}

				// Track AI telemetry if this was an AI regeneration
				if ( $is_ai_regeneration && isset( $analysis['ai_metadata'] ) ) {
					$ai_meta = $analysis['ai_metadata'];

					// Track confidence scores
					if ( isset( $ai_meta['confidence'] ) ) {
						$telemetry['confidence_scores'][] = floatval( $ai_meta['confidence'] );
						if ( $ai_meta['confidence'] < 0.7 ) {
							$telemetry['low_confidence_count']++;
						}
					}

					// Track issues
					if ( isset( $ai_meta['issues'] ) && is_array( $ai_meta['issues'] ) ) {
						if ( in_array( 'brand_name_assumed', $ai_meta['issues'], true ) ) {
							$telemetry['brand_name_assumed_count']++;
						}
						if ( in_array( 'decorative_image', $ai_meta['issues'], true ) ) {
							$telemetry['decorative_image_count']++;
						}
						if ( in_array( 'text_in_image_detected', $ai_meta['issues'], true ) ) {
							$telemetry['text_detected_count']++;
						}
					}

					$telemetry['ai_success_count']++;
				}

				$priority = $this->calculate_healthcare_priority( $image );



				// Map current_size_bytes to file_size for frontend compatibility
				if ( isset( $analysis['current_size_bytes'] ) ) {
					$analysis['file_size'] = $analysis['current_size_bytes'];
				}

				$analysis_results[] = array_merge( $image, $analysis, array( 'priority' => $priority ) );
			}
		} catch ( Throwable $throwable ) {
			$this->log_debug(
				sprintf(
					'MSH Analyzer Fatal: ID %d - %s in %s:%d',
					isset( $image['ID'] ) ? (int) $image['ID'] : 0,
					$throwable->getMessage(),
					$throwable->getFile(),
					$throwable->getLine()
				)
			);

			wp_send_json_error( $throwable->getMessage() );
		}

		// Sort by priority (highest first)
		usort(
			$analysis_results,
			function ( $a, $b ) {
				return $b['priority'] - $a['priority'];
			}
		);

		// Include minimal debug info in response
		$duration_ms = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		$response_data = array(
			'images'       => $analysis_results,
			'total_images' => intval( $total_images ),
			'debug'        => array(
				'total_images_in_db'     => intval( $total_images ),
				'published_images_found' => count( $images ),
				'analysis_duration_ms'   => $duration_ms,
			),
		);

		// Include credit balance in response if this was an AI Regeneration request
		if ( $is_ai_regeneration && class_exists( 'MSH_AI_Service' ) ) {
			$ai_service                         = MSH_AI_Service::get_instance();
			$access_state                       = $ai_service->determine_access_state();
			$access_mode                        = isset( $access_state['access_mode'] ) ? $access_state['access_mode'] : '';
			$response_data['credits_remaining'] = ( $access_mode === 'byok' )
				? PHP_INT_MAX
				: $ai_service->get_credit_balance();
			$response_data['ai_access_mode']    = $access_mode;
		}

		// URGENT: Cache the results to prevent time waste
		set_transient(
			$cache_key,
			array(
				'data'      => $response_data,
				'timestamp' => time(),
			),
			1800
		); // Cache for 30 minutes

		$this->log_debug( "MSH: Analysis complete in {$duration_ms}ms, cached for 30 minutes" );

		// Log telemetry for AI regeneration batches
		if ( $is_ai_regeneration && $telemetry['ai_success_count'] > 0 ) {
			$confidence_avg = ! empty( $telemetry['confidence_scores'] )
				? array_sum( $telemetry['confidence_scores'] ) / count( $telemetry['confidence_scores'] )
				: 0.0;

			error_log( sprintf(
				'[MSH Telemetry] AI Batch Complete - processed: %d, success: %d, fallback: %d, confidence_avg: %.2f, brand_assumed: %d, decorative: %d, text_detected: %d, low_confidence: %d',
				$total_to_process,
				$telemetry['ai_success_count'],
				$telemetry['ai_fallback_count'],
				$confidence_avg,
				$telemetry['brand_name_assumed_count'],
				$telemetry['decorative_image_count'],
				$telemetry['text_detected_count'],
				$telemetry['low_confidence_count']
			) );

			// Log to telemetry system (integrated with MSH_Telemetry)
			do_action( 'msh_log_telemetry', 'ai_batch_complete', $telemetry );
		}

		update_option( 'msh_last_analyzer_run', current_time( 'mysql' ) );

		wp_send_json_success( $response_data );
	}

	/**
	 * AJAX handler: optimise a provided list of attachment IDs.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_optimize_batch() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Phase 1F: Define batch constant to guard other operations
		if ( ! defined( 'MSH_IN_OPTIMIZE_BATCH' ) ) {
			define( 'MSH_IN_OPTIMIZE_BATCH', true );
		}

		$image_ids = $_POST['image_ids'] ?? array();
		$results   = array();

		// Phase 1G: Prime AI service cache to avoid per-image option reads
		$ai_service_available = class_exists( 'MSH_AI_Service' );
		$ai_cache_seed        = array(
			'msh_ai_mode'              => get_option( 'msh_ai_mode', 'manual' ),
			'msh_plan_tier'            => get_option( 'msh_plan_tier', 'free' ),
			'msh_ai_api_key'           => get_option( 'msh_ai_api_key', '' ),
			'msh_ai_features'          => get_option( 'msh_ai_features', array() ),
			'msh_ai_credit_balance'    => get_option( 'msh_ai_credit_balance', null ),
			'msh_ai_credit_usage'      => get_option( 'msh_ai_credit_usage', array() ),
			'msh_ai_credit_last_reset' => get_option( 'msh_ai_credit_last_reset', 0 ),
			'msh_ai_provider'          => get_option( 'msh_ai_provider', 'openai' ),
			'msh_metadata_regen_jobs'  => get_option( 'msh_metadata_regen_jobs', array() ),
		);

		if ( ! is_array( $ai_cache_seed['msh_ai_features'] ) ) {
			$ai_cache_seed['msh_ai_features'] = array();
		}
		if ( ! is_array( $ai_cache_seed['msh_ai_credit_usage'] ) ) {
			$ai_cache_seed['msh_ai_credit_usage'] = array();
		}
		if ( ! is_array( $ai_cache_seed['msh_metadata_regen_jobs'] ) ) {
			$ai_cache_seed['msh_metadata_regen_jobs'] = array();
		}

		if ( $ai_service_available ) {
			MSH_AI_Service::prime_batch( $ai_cache_seed );
		}

		// Phase 1F: Cache AI mode ONCE before batch loop starts
		$this->msh_batch_ai_mode = $ai_cache_seed['msh_ai_mode'];

		// Phase 1F: Pre-hydrate contextual generator ONCE before batch loop
		if ( isset( $this->contextual_meta_generator ) && is_object( $this->contextual_meta_generator ) ) {
			$this->contextual_meta_generator->msh_enable_batch_mode_with_cache();
		}

		try {
			// Execute batch optimization loop
			foreach ( $image_ids as $attachment_id ) {
				$result    = $this->optimize_single_image( intval( $attachment_id ) );
				$results[] = array(
					'id'     => $attachment_id,
					'result' => $result,
				);
			}
		} finally {
			// Phase 1F: Teardown batch state - runs even if loop errors
			$this->msh_batch_ai_mode = null;
			if ( isset( $this->contextual_meta_generator ) && is_object( $this->contextual_meta_generator ) ) {
				$this->contextual_meta_generator->msh_disable_batch_mode();
			}
			if ( $ai_service_available ) {
				MSH_AI_Service::clear_batch();
			}
		}

		if ( ! empty( $image_ids ) ) {
			update_option( 'msh_last_optimization_run', current_time( 'mysql' ) );
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler: optimise the queue of high-priority attachments.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_optimize_high_priority() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Get all published images with priority 15+
		$images               = $this->get_published_images();
		$high_priority_images = array_filter(
			$images,
			function ( $image ) {
				return $image['priority_score'] >= 15;
			}
		);

		$image_ids = array_column( $high_priority_images, 'ID' );
		$results   = array();

		$generator     = $this->contextual_meta_generator;
		$batch_capable = is_object( $generator ) && method_exists( $generator, 'enable_batch_mode' );

		if ( $batch_capable && ! empty( $image_ids ) ) {
			$generator->enable_batch_mode();
		}

		foreach ( $image_ids as $attachment_id ) {
			$result    = $this->optimize_single_image( intval( $attachment_id ) );
			$results[] = array(
				'id'     => $attachment_id,
				'result' => $result,
			);
		}

		if ( $batch_capable && ! empty( $image_ids ) ) {
			$generator->disable_batch_mode();
		}

		// Clear analysis cache after optimization
		$cache_key = 'msh_analysis_cache_' . md5( 'latest_analysis' );
		delete_transient( $cache_key );

		if ( ! empty( $image_ids ) ) {
			update_option( 'msh_last_optimization_run', current_time( 'mysql' ) );
		}

		wp_send_json_success(
			array(
				'results'         => $results,
				'total_processed' => count( $image_ids ),
				'message'         => sprintf( __( 'Optimized %d high priority images (15+)', 'msh-image-optimizer' ), count( $image_ids ) ),
			)
		);
	}

	/**
	 * AJAX handler: process the medium-priority optimisation queue.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_optimize_medium_priority() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Get all published images with priority 10-14
		$images                 = $this->get_published_images();
		$medium_priority_images = array_filter(
			$images,
			function ( $image ) {
				return $image['priority_score'] >= 10 && $image['priority_score'] < 15;
			}
		);

		$image_ids = array_column( $medium_priority_images, 'ID' );
		$results   = array();

		$generator     = $this->contextual_meta_generator;
		$batch_capable = is_object( $generator ) && method_exists( $generator, 'enable_batch_mode' );

		if ( $batch_capable && ! empty( $image_ids ) ) {
			$generator->enable_batch_mode();
		}

		foreach ( $image_ids as $attachment_id ) {
			$result    = $this->optimize_single_image( intval( $attachment_id ) );
			$results[] = array(
				'id'     => $attachment_id,
				'result' => $result,
			);
		}

		if ( $batch_capable && ! empty( $image_ids ) ) {
			$generator->disable_batch_mode();
		}

		// Clear analysis cache after optimization
		$cache_key = 'msh_analysis_cache_' . md5( 'latest_analysis' );
		delete_transient( $cache_key );

		if ( ! empty( $image_ids ) ) {
			update_option( 'msh_last_optimization_run', current_time( 'mysql' ) );
		}

		wp_send_json_success(
			array(
				'results'         => $results,
				'total_processed' => count( $image_ids ),
				'message'         => sprintf( __( 'Optimized %d medium priority images (10-14)', 'msh-image-optimizer' ), count( $image_ids ) ),
			)
		);
	}

	/**
	 * AJAX handler: run optimisation across the remaining queue.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_optimize_all_remaining() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Get all published images that need optimization (excluding needs_attention)
		$images             = $this->get_published_images();
		$unoptimized_images = array_filter(
			$images,
			function ( $image ) {
				return $image['optimization_status'] !== 'optimized' &&
					$image['optimization_status'] !== 'needs_attention';
			}
		);

		$image_ids = array_column( $unoptimized_images, 'ID' );
		$results   = array();

		$generator     = $this->contextual_meta_generator;
		$batch_capable = is_object( $generator ) && method_exists( $generator, 'enable_batch_mode' );

		if ( $batch_capable && ! empty( $image_ids ) ) {
			$generator->enable_batch_mode();
		}

		foreach ( $image_ids as $attachment_id ) {
			$result    = $this->optimize_single_image( intval( $attachment_id ) );
			$results[] = array(
				'id'     => $attachment_id,
				'result' => $result,
			);
		}

		if ( $batch_capable && ! empty( $image_ids ) ) {
			$generator->disable_batch_mode();
		}

		// Clear analysis cache after optimization
		$cache_key = 'msh_analysis_cache_' . md5( 'latest_analysis' );
		delete_transient( $cache_key );

		if ( ! empty( $image_ids ) ) {
			update_option( 'msh_last_optimization_run', current_time( 'mysql' ) );
		}

		wp_send_json_success(
			array(
				'results'         => $results,
				'total_processed' => count( $image_ids ),
				'message'         => sprintf( __( 'Optimized %d remaining images', 'msh-image-optimizer' ), count( $image_ids ) ),
			)
		);
	}

	/**
	 * Run single image optimization (Batch 2: apply contextual metadata & filename suggestion)
	 */
	private function optimize_single_image( $attachment_id ) {
		$attachment_id = intval( $attachment_id );

		$result = array(
			'status'  => 'skipped',
			'actions' => array(),
		);

		if ( $attachment_id <= 0 ) {
			$result['status']    = 'error';
			$result['actions'][] = 'Invalid attachment ID';
			return $result;
		}

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			$result['status']    = 'error';
			$result['actions'][] = 'Attachment not found';
			return $result;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$result['status']    = 'error';
			$result['actions'][] = 'Original file missing';
			return $result;
		}

		$lock_acquired = false;
		if ( function_exists( 'msh_lock_start' ) ) {
			if ( ! msh_lock_start( $attachment_id ) ) {
				$result['status']    = 'error';
				$result['actions'][] = __( 'Attachment is being processed. Try again shortly.', 'msh-image-optimizer' );
				return $result;
			}
			$lock_acquired = true;
		}

		try {

		$legacy_context       = $this->determine_image_context( $attachment_id );
		$context_details      = $this->contextual_meta_generator->detect_context( $attachment_id );
		$manual_context_value = get_post_meta( $attachment_id, '_msh_context', true );
		$manual_context_value = is_string( $manual_context_value ) ? trim( $manual_context_value ) : '';
		$auto_context_value   = get_post_meta( $attachment_id, '_msh_auto_context', true );
		$auto_context_value   = is_string( $auto_context_value ) ? trim( $auto_context_value ) : '';
		$context_source       = ! empty( $context_details['manual'] ) ? 'manual' : 'auto';
		$active_context_slug  = $manual_context_value !== ''
			? $manual_context_value
			: ( $context_details['type'] ?? $auto_context_value );
		$active_context_label = $this->format_context_label( $active_context_slug );
		$auto_context_label   = $auto_context_value !== '' ? $this->format_context_label( $auto_context_value ) : '';
		$staged_ai_meta       = get_post_meta( $attachment_id, '_msh_ai_staged_meta', true );
		$using_staged_ai_meta = false;

		if ( is_array( $staged_ai_meta ) && ! empty( $staged_ai_meta['data'] ) && is_array( $staged_ai_meta['data'] ) ) {
			$meta_preview         = array_intersect_key(
				(array) $staged_ai_meta['data'],
				array_flip( array( 'title', 'alt_text', 'caption', 'description' ) )
			);
			$using_staged_ai_meta = ! empty( $meta_preview );
		} else {
			$meta_preview = $this->contextual_meta_generator->generate_meta_fields( $attachment_id, $context_details );
		}

		$meta_preview      = $this->validate_and_truncate_meta( $meta_preview );
		$meta_source_label = $using_staged_ai_meta ? 'AI regeneration' : 'contextual generator';

		$context_message = $context_source === 'manual'
			? sprintf( __( 'Manual override in effect: %s', 'msh-image-optimizer' ), $active_context_label )
			: sprintf( __( 'Auto-detected context: %s', 'msh-image-optimizer' ), $active_context_label );

		if ( $context_source === 'manual' && $auto_context_label && $manual_context_value !== $auto_context_value ) {
			$context_message .= ' ' . sprintf( __( '(Auto suggestion: %s)', 'msh-image-optimizer' ), $auto_context_label );
		}

		$timestamp                  = time();
		$meta_applied               = array();
		$meta_skipped               = array();
		$metadata_timestamp_applied = false;
		$filename_refreshed         = false;

		// Get versioning system and current locale
		$versioning      = class_exists( 'MSH_Metadata_Versioning' ) ? MSH_Metadata_Versioning::get_instance() : null;
		$protection      = class_exists( 'MSH_Manual_Edit_Protection' ) ? MSH_Manual_Edit_Protection::get_instance() : null;
		$current_locale  = function_exists( 'pll_current_language' ) ? pll_current_language( 'locale' ) : get_locale();
		$metadata_source = $using_staged_ai_meta ? 'ai' : 'template';

		// Title
		if ( ! empty( $meta_preview['title'] ) ) {
			if ( $this->should_regenerate_meta( $attachment_id, 'title' ) ) {
				// Check manual edit protection
				$can_write = ! $protection || $protection->can_ai_write( $attachment_id, 'title', $current_locale );

				if ( $can_write ) {
					// Save version before applying
					if ( $versioning ) {
						$versioning->save_version( $attachment_id, $current_locale, 'title', $meta_preview['title'], $metadata_source );
					}

					wp_update_post(
						array(
							'ID'         => $attachment_id,
							'post_title' => sanitize_text_field( $meta_preview['title'] ),
							'post_name'  => sanitize_title( $meta_preview['title'] ),
						)
					);
					$result['actions'][]   = sprintf( 'Title updated from %s', $meta_source_label );
					$meta_applied['title'] = $meta_preview['title'];
				} else {
					$result['actions'][] = 'Title preserved (manual edit)';
					$meta_skipped[]      = 'title';
				}
			} else {
				$meta_skipped[] = 'title';
			}
		}

		// Caption
		if ( ! empty( $meta_preview['caption'] ) ) {
			if ( $this->should_regenerate_meta( $attachment_id, 'caption' ) ) {
				// Check manual edit protection
				$can_write = ! $protection || $protection->can_ai_write( $attachment_id, 'caption', $current_locale );

				if ( $can_write ) {
					// Save version before applying
					if ( $versioning ) {
						$versioning->save_version( $attachment_id, $current_locale, 'caption', $meta_preview['caption'], $metadata_source );
					}

					wp_update_post(
						array(
							'ID'           => $attachment_id,
							'post_excerpt' => sanitize_textarea_field( $meta_preview['caption'] ),
						)
					);
					$result['actions'][]     = sprintf( 'Caption updated from %s', $meta_source_label );
					$meta_applied['caption'] = $meta_preview['caption'];
				} else {
					$result['actions'][] = 'Caption preserved (manual edit)';
					$meta_skipped[]      = 'caption';
				}
			} else {
				$meta_skipped[] = 'caption';
			}
		}

		// Description
		if ( ! empty( $meta_preview['description'] ) ) {
			if ( $this->should_regenerate_meta( $attachment_id, 'description' ) ) {
				// Check manual edit protection
				$can_write = ! $protection || $protection->can_ai_write( $attachment_id, 'description', $current_locale );

				if ( $can_write ) {
					// Save version before applying
					if ( $versioning ) {
						$versioning->save_version( $attachment_id, $current_locale, 'description', $meta_preview['description'], $metadata_source );
					}

					wp_update_post(
						array(
							'ID'           => $attachment_id,
							'post_content' => sanitize_textarea_field( $meta_preview['description'] ),
						)
					);
					$result['actions'][]         = sprintf( 'Description updated from %s', $meta_source_label );
					$meta_applied['description'] = $meta_preview['description'];
				} else {
					$result['actions'][] = 'Description preserved (manual edit)';
					$meta_skipped[]      = 'description';
				}
			} else {
				$meta_skipped[] = 'description';
			}
		}

		// Alt text
		if ( ! empty( $meta_preview['alt_text'] ) ) {
			if ( $this->should_regenerate_meta( $attachment_id, 'alt_text' ) ) {
				// Check manual edit protection
				$can_write = ! $protection || $protection->can_ai_write( $attachment_id, 'alt', $current_locale );

				if ( $can_write ) {
					// Save version before applying
					if ( $versioning ) {
						$versioning->save_version( $attachment_id, $current_locale, 'alt', $meta_preview['alt_text'], $metadata_source );
					}

					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $meta_preview['alt_text'] ) );
					$result['actions'][]      = sprintf( 'ALT text updated from %s', $meta_source_label );
					$meta_applied['alt_text'] = $meta_preview['alt_text'];
				} else {
					$result['actions'][] = 'ALT text preserved (manual edit)';
					$meta_skipped[]      = 'alt_text';
				}
			} else {
				$meta_skipped[] = 'alt_text';
			}
		}

		// Convert to WebP if applicable
		$webp_converted         = false;
		$webp_timestamp_updated = false;
		$image_info             = wp_get_image_editor( $file_path );
		if ( ! is_wp_error( $image_info ) ) {
			$mime_type = get_post_mime_type( $attachment_id );
			if ( in_array( $mime_type, array( 'image/jpeg', 'image/png' ) ) ) {
				$webp_path      = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
				$webp_supported = function_exists( 'imagewebp' );

				if ( ! $webp_supported ) {
					update_post_meta( $attachment_id, 'msh_webp_status', 'unsupported' );
					$result['actions'][]    = 'WebP conversion skipped (unsupported)';
					$webp_timestamp_updated = true;
				} else {
					delete_post_meta( $attachment_id, 'msh_webp_status' );

					// Convert if WebP doesn't exist or source is newer
					if ( ! file_exists( $webp_path ) || filemtime( $file_path ) > filemtime( $webp_path ) ) {
						$webp_result = $this->convert_to_webp( $file_path, $webp_path );
						if ( $webp_result ) {
							update_post_meta( $attachment_id, 'msh_webp_last_converted', $timestamp );
							delete_post_meta( $attachment_id, 'msh_webp_status' );
							$result['actions'][] = 'WebP version created';
							$webp_converted      = true;
						} else {
							update_post_meta( $attachment_id, 'msh_webp_status', 'failed' );
							$result['actions'][] = 'WebP conversion failed';
						}
					} elseif ( file_exists( $webp_path ) && ! get_post_meta( $attachment_id, 'msh_webp_last_converted', true ) ) {
						// WebP exists but timestamp missing - update timestamp
						update_post_meta( $attachment_id, 'msh_webp_last_converted', $timestamp );
						delete_post_meta( $attachment_id, 'msh_webp_status' );
						$result['actions'][]    = 'WebP timestamp updated';
						$webp_timestamp_updated = true;
					}
				}
			}
		}

		// Always refresh metadata timestamp so status reflects the latest context application
		if ( ! empty( $meta_applied ) ) {
			if ( $using_staged_ai_meta ) {
				update_post_meta( $attachment_id, 'msh_metadata_source', 'ai_generated' );
			} else {
				delete_post_meta( $attachment_id, 'msh_metadata_source' );
			}
		}

		update_post_meta( $attachment_id, 'msh_metadata_last_updated', (int) $timestamp );
		update_post_meta( $attachment_id, 'msh_metadata_context_hash', $this->contextual_meta_generator->get_context_signature() );
		delete_post_meta( $attachment_id, 'msh_context_needs_refresh' );
		$metadata_timestamp_applied = true;

		if ( $using_staged_ai_meta ) {
			delete_post_meta( $attachment_id, '_msh_ai_staged_meta' );
		}

		// Manual edit messages are now added inline during field processing

		// Refresh filename suggestion using contextual slug helper and apply rename automatically
		$suggested_filename = '';
		$extension          = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$rename_feedback    = null;
		$original_file_path = $file_path;
		$original_webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $original_file_path );
		if ( ! empty( $extension ) ) {
			$slug = $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_details, $extension );
			if ( ! empty( $slug ) ) {
				$suggested_filename = $this->ensure_unique_filename( $slug, $extension, $attachment_id );
				update_post_meta( $attachment_id, '_msh_suggested_filename', $suggested_filename );
				update_post_meta( $attachment_id, 'msh_filename_last_suggested', (int) $timestamp );
				update_post_meta( $attachment_id, '_msh_suggested_filename_context', $this->contextual_meta_generator->get_context_signature() );
				$result['actions'][] = 'Filename suggestion refreshed';
				$filename_refreshed  = true;

				$rename_feedback = $this->apply_suggested_filename_now( $attachment_id, $suggested_filename );

				if ( $rename_feedback['status'] === 'success' ) {
					$result['actions'][] = __( 'Filename applied', 'msh-image-optimizer' );
					$suggested_filename  = '';
					$file_path           = $rename_feedback['absolute_path'];

					if ( in_array( $extension, array( 'jpg', 'jpeg', 'png' ), true ) ) {
						$new_webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
						$webp_adjusted = false;

						if ( $original_webp_path !== $new_webp_path && file_exists( $original_webp_path ) ) {
							// Initialize WP_Filesystem for file operations
							global $wp_filesystem;
							if ( ! function_exists( 'WP_Filesystem' ) ) {
								require_once ABSPATH . 'wp-admin/includes/file.php';
							}
							WP_Filesystem();

							if ( $wp_filesystem && $wp_filesystem->move( $original_webp_path, $new_webp_path, true ) ) {
								update_post_meta( $attachment_id, 'msh_webp_last_converted', (int) $timestamp );
								delete_post_meta( $attachment_id, 'msh_webp_status' );
								$result['actions'][] = __( 'WebP renamed to match new filename', 'msh-image-optimizer' );
								$webp_adjusted       = true;
							}
						} elseif ( file_exists( $new_webp_path ) ) {
							$webp_adjusted = true;
						}

						if ( ! $webp_adjusted ) {
							if ( $this->convert_to_webp( $file_path, $new_webp_path ) ) {
								update_post_meta( $attachment_id, 'msh_webp_last_converted', (int) $timestamp );
								delete_post_meta( $attachment_id, 'msh_webp_status' );
								$result['actions'][] = __( 'WebP regenerated after rename', 'msh-image-optimizer' );
							} else {
								update_post_meta( $attachment_id, 'msh_webp_status', 'failed' );
								$result['actions'][] = __( 'WebP regeneration failed after rename', 'msh-image-optimizer' );
							}
						}
					}
				} elseif ( $rename_feedback['status'] === 'skipped' ) {
					$result['actions'][] = __( 'Filename already optimized', 'msh-image-optimizer' );
					$suggested_filename  = '';
				} elseif ( $rename_feedback['status'] === 'error' ) {
					$result['actions'][] = sprintf( __( 'Filename rename failed: %s', 'msh-image-optimizer' ), $rename_feedback['message'] );
					if ( ! empty( $rename_feedback['error_data'] ) ) {
						$result['actions'][] = __( 'Safe rename verification details logged for review.', 'msh-image-optimizer' );
						$this->log_debug( 'MSH Safe Rename Debug: ' . print_r( $rename_feedback['error_data'], true ) );
					}
				}
			}
		}

		if ( $metadata_timestamp_applied || $webp_converted || $webp_timestamp_updated || $filename_refreshed ) {
			update_post_meta( $attachment_id, 'msh_optimized_date', current_time('mysql') );
		}

		if ( $metadata_timestamp_applied || $webp_converted || $webp_timestamp_updated || $filename_refreshed ) {
			delete_post_meta( $attachment_id, 'msh_context_needs_refresh' );
			$this->clear_analysis_cache();
		}

		if ( function_exists( 'msh_upsert_metadata_cache_value' ) ) {
			$cache_locale = '';
			if ( isset( $context_details['locale'] ) && ! empty( $context_details['locale'] ) ) {
				$cache_locale = $context_details['locale'];
			}

			if ( '' === $cache_locale ) {
				$cache_locale = get_locale();
			}

			$fresh_attachment = get_post( $attachment_id );
			$final_title       = $fresh_attachment ? $fresh_attachment->post_title : get_the_title( $attachment_id );
			$final_caption     = $fresh_attachment ? $fresh_attachment->post_excerpt : '';
			$final_description = $fresh_attachment ? $fresh_attachment->post_content : '';
			$final_alt         = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			$cache_fields = array(
				'title'       => array(
					'value'       => $final_title,
					'applied_key' => 'title',
				),
				'alt'         => array(
					'value'       => $final_alt,
					'applied_key' => 'alt_text',
				),
				'caption'     => array(
					'value'       => $final_caption,
					'applied_key' => 'caption',
				),
				'description' => array(
					'value'       => $final_description,
					'applied_key' => 'description',
				),
			);

			foreach ( $cache_fields as $field_key => $cache_data ) {
				$field_value = is_null( $cache_data['value'] ) ? '' : (string) $cache_data['value'];
				$field_value = trim( $field_value );

				$cache_source = ( $using_staged_ai_meta && isset( $meta_applied[ $cache_data['applied_key'] ] ) )
					? 'ai'
					: 'manual';

				msh_upsert_metadata_cache_value( $attachment_id, $cache_locale, $field_key, $field_value, $cache_source );
			}
		}

		$result['status']             = $this->get_optimization_status( $attachment_id );
		$result['actions'][]          = $context_message;
		$result['context']            = array(
			'legacy'            => $legacy_context,
			'detected'          => $context_details,
			'source'            => $context_source,
			'manual_override'   => $manual_context_value,
			'auto'              => $auto_context_value,
			'active_label'      => $active_context_label,
			'auto_label'        => $auto_context_label,
			'seo_mode' => ! empty( $context_details['seo_mode'] ),
		);
		$result['meta_preview']       = $meta_preview;
		$result['meta_applied']       = $meta_applied;
		$result['suggested_filename'] = $suggested_filename;
		$result['seo_mode']           = ! empty( $context_details['seo_mode'] );
		if ( $rename_feedback !== null ) {
			$result['rename_feedback'] = $rename_feedback;
		}

		return $result;
	} finally {
		if ( $lock_acquired ) {
			msh_lock_end( $attachment_id );
		}
	}

	/**
	 * AJAX handler: persist manual context updates from the analyser UI.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_update_context() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		if ( $attachment_id <= 0 ) {
			wp_send_json_error( __( 'Invalid attachment ID.', 'msh-image-optimizer' ) );
		}

		$raw_context = isset( $_POST['context'] ) ? wp_unslash( $_POST['context'] ) : '';
		$new_context = sanitize_text_field( $raw_context );

		// Handle legacy underscore format by converting to hyphen
		if ( $new_context === 'service_icon' ) {
			$new_context = 'service-icon';
		}

		// Debug logging removed for production

		$existing_manual_context = get_post_meta( $attachment_id, '_msh_context', true );
		$existing_manual_context = is_string( $existing_manual_context ) ? trim( $existing_manual_context ) : '';
		$context_changed         = $existing_manual_context !== $new_context;

		$choices = $this->get_context_choices();
		if ( $new_context !== '' && ! array_key_exists( $new_context, $choices ) ) {
			wp_send_json_error( __( 'Invalid context selection.', 'msh-image-optimizer' ) );
		}

		if ( $new_context !== '' ) {
			update_post_meta( $attachment_id, '_msh_context', $new_context );
		} else {
			delete_post_meta( $attachment_id, '_msh_context' );
		}

		$existing_seo_mode_raw = get_post_meta( $attachment_id, '_msh_seo_mode', true );
		$existing_seo_mode     = $existing_seo_mode_raw === '0' ? false : true; // Default to true if not set
		$seo_mode_flag         = $existing_seo_mode;

		if ( isset( $_POST['seo_mode'] ) ) {
			$raw_seo_mode  = sanitize_text_field( wp_unslash( $_POST['seo_mode'] ) );
			$seo_mode_flag = in_array( strtolower( $raw_seo_mode ), array( '1', 'true', 'yes' ), true );

			if ( $seo_mode_flag ) {
				update_post_meta( $attachment_id, '_msh_seo_mode', '1' );
			} else {
				update_post_meta( $attachment_id, '_msh_seo_mode', '0' );
			}
		}

		$seo_mode_changed = ( $existing_seo_mode !== $seo_mode_flag );

		$loc_mode_changed = false;
		$loc_mode_request = isset( $_POST['loc_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['loc_mode'] ) ) : null;
		if ( null !== $loc_mode_request ) {
			$sanitised_loc_mode = $this->sanitize_loc_mode( $loc_mode_request );
			if ( false === $sanitised_loc_mode ) {
				wp_send_json_error( __( 'Invalid location mode.', 'msh-image-optimizer' ) );
			}

			$current_loc_mode_raw = get_post_meta( $attachment_id, '_msh_loc_mode', true );
			$current_loc_mode     = '';
			if ( is_string( $current_loc_mode_raw ) && '' !== $current_loc_mode_raw ) {
				$maybe_loc_mode = $this->sanitize_loc_mode( $current_loc_mode_raw );
				if ( false !== $maybe_loc_mode ) {
					$current_loc_mode = $maybe_loc_mode;
				}
			}

			if ( $current_loc_mode !== $sanitised_loc_mode ) {
				update_post_meta( $attachment_id, '_msh_loc_mode', $sanitised_loc_mode );
				$loc_mode_changed = true;
			}
		}

		if ( $context_changed || $seo_mode_changed || $loc_mode_changed ) {
			$this->flag_attachment_for_reoptimization( $attachment_id );
		}

		$this->clear_analysis_cache();

		// Clean up deprecated keys retained for backwards compatibility.
		delete_post_meta( $attachment_id, '_msh_manual_edit' );
		delete_post_meta( $attachment_id, 'msh_context_last_manual_update' );

		// Refresh auto-detected context for comparison badges.
		try {
			$auto_context = $this->contextual_meta_generator->detect_context( $attachment_id, true );
			if ( ! empty( $auto_context['type'] ) ) {
				update_post_meta( $attachment_id, '_msh_auto_context', $auto_context['type'] );
			} else {
				delete_post_meta( $attachment_id, '_msh_auto_context' );
			}
		} catch ( Exception $e ) {
			// Debug logging removed for production
		}

		try {
			// When context OR SEO mode changes, ALWAYS regenerate AI metadata if AI is enabled
			$ai_mode_setting = get_option( 'msh_ai_mode', 'manual' );
			$ai_enabled      = ( $ai_mode_setting !== 'manual' );
			$ai_options      = array();

			if ( $ai_enabled && ( $context_changed || $seo_mode_changed ) ) {
				// Force AI regeneration with new context or SEO mode
				$ai_options['ai_regeneration'] = true;
				$ai_options['ai_mode']         = 'overwrite'; // OVERWRITE to reflect changes
				$ai_options['ai_fields']       = array( 'title', 'alt_text', 'caption', 'description' );
				if ( $context_changed ) {
					error_log( "[MSH] Context changed - forcing AI regeneration for attachment {$attachment_id}" );
				}
				if ( $seo_mode_changed ) {
					$seo_status = $seo_mode_flag ? 'TRUE (checked)' : 'FALSE (unchecked)';
					error_log( "[MSH] SEO mode changed to {$seo_status} - forcing AI regeneration for attachment {$attachment_id}" );
				}
			} elseif ( $ai_enabled ) {
				// Nothing changed, just refresh metadata preview
				$ai_options['ai_regeneration'] = true;
				$ai_options['ai_mode']         = 'fill-empty'; // Fill empty fields
				$ai_options['ai_fields']       = array( 'title', 'alt_text', 'caption', 'description' );
			}

			$image_data = $this->analyze_single_image( $attachment_id, $ai_options );
			if ( ! is_array( $image_data ) || isset( $image_data['error'] ) ) {
				$error_message = is_array( $image_data ) && isset( $image_data['error'] )
					? $image_data['error']
					: __( 'Unable to refresh analyzer data.', 'msh-image-optimizer' );
				// Debug logging removed for production
				wp_send_json_error( $error_message );
			}

			if ( isset( $image_data['loc_mode'] ) ) {
				$final_context_type = isset( $image_data['context_details']['final_context_type'] ) ? $image_data['context_details']['final_context_type'] : ( $image_data['context_details']['type'] ?? 'stock' );
				if ( 'force_all' === $image_data['loc_mode'] && ! in_array( $final_context_type, self::$loc_mode_force_all_contexts, true ) ) {
					update_post_meta( $attachment_id, '_msh_loc_mode', 'force_caption' );
					$image_data['loc_mode'] = 'force_caption';
					if ( isset( $image_data['context_details'] ) && is_array( $image_data['context_details'] ) ) {
						$image_data['context_details']['loc_mode'] = 'force_caption';
					}
				}
			}

			wp_send_json_success(
				array(
					'image' => $image_data,
				)
			);
		} catch ( Exception $e ) {
			// Debug logging removed for production
			wp_send_json_error( 'Error updating context: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler: report overall optimisation progress metrics.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		// Get total published images (simplified for performance)
		$total_images = count( $this->get_published_images() );

		// Get optimized count (only from published images)
		$optimized_count = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT COUNT(DISTINCT pm.post_id) 
			FROM {$wpdb->postmeta} pm
			JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = 'msh_optimized_date'
			AND p.post_type = %s
			AND p.post_mime_type LIKE %s
		",
				'attachment',
				$image_mime_like
			)
		);

		$remaining  = max( 0, $total_images - $optimized_count );
		$percentage = $total_images > 0 ? min( 100, round( ( $optimized_count / $total_images ) * 100, 2 ) ) : 0;

		$progress = array(
			'total'      => intval( $total_images ),
			'optimized'  => intval( $optimized_count ),
			'percentage' => $percentage,
			'remaining'  => $remaining,
		);

		wp_send_json_success( $progress );
	}

	/**
	 * AJAX handler: clear optimisation flags to re-run processing.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_reset_optimization() {
		error_log( '[MSH DEBUG] ajax_reset_optimization handler called' );
		error_log( '[MSH DEBUG] POST data: ' . print_r( $_POST, true ) );

		check_ajax_referer( 'msh_image_optimizer', 'nonce' );
		error_log( '[MSH DEBUG] Nonce check passed' );

		if ( ! current_user_can( 'manage_options' ) ) {
			error_log( '[MSH DEBUG] User lacks manage_options capability' );
			wp_die( 'Unauthorized' );
		}

		error_log( '[MSH DEBUG] User authorization passed' );

		global $wpdb;

		// Remove optimization flags to allow re-processing with improved metadata preservation
		$reset_count = $wpdb->query(
			"
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key IN (
                'msh_optimized_date',
                '_msh_suggested_filename',
                '_msh_suggested_filename_context',
                'msh_metadata_context_hash',
                'msh_descriptor',
                'msh_last_analyzed',
                '_msh_context_trace',
                '_msh_descriptor_backup'
            )
        "
		);

		error_log( '[MSH DEBUG] Reset count: ' . $reset_count );

		// Also remove AI-generated metadata to force fresh AI regeneration on next analyze
		$ai_reset_count = $wpdb->query(
			"
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key IN (
                '_msh_ai_staged_meta',
                '_msh_ai_filename_slug',
                '_msh_title_source',
                '_msh_alt_source',
                '_msh_caption_source',
                '_msh_description_source',
                '_msh_confidence_level',
                '_msh_confidence_score',
                'msh_metadata_last_updated',
                'msh_metadata_source'
            )
        "
		);

		error_log( '[MSH DEBUG] AI reset count: ' . $ai_reset_count );

		error_log( '[MSH DEBUG] Soft reset completed (WP core fields preserved).' );

		$total_reset = $reset_count + $ai_reset_count;

		// Clear the analysis cache to force fresh analysis on next run
		$cache_key = 'msh_analysis_cache_v' . self::ANALYSIS_CACHE_VERSION . '_' . md5( 'latest_analysis' );
		delete_transient( $cache_key );

		error_log( '[MSH DEBUG] Cache cleared. Sending JSON success response' );

		wp_send_json_success(
			array(
				'reset_count'    => $total_reset,
				'ai_reset_count' => $ai_reset_count,
				'message'        => "Reset {$total_reset} flags. Analysis cache cleared. Images will be re-analyzed with fresh metadata on next analyze run.",
			)
		);
	}

	/**
	 * AJAX handler: apply queued filename suggestions in batches.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_apply_filename_suggestions() {
		$this->log_debug( 'MSH Safe Rename: Batch apply function called' );
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		// Production-ready batch processing
		$batch_size   = 25; // Safe batch size to prevent timeouts
		$batch_number = isset( $_POST['batch_number'] ) ? intval( $_POST['batch_number'] ) : 1;
		$total_files  = isset( $_POST['total_files'] ) ? intval( $_POST['total_files'] ) : 0;

		$mode  = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'full';
		$limit = isset( $_POST['limit'] ) ? max( 0, intval( $_POST['limit'] ) ) : 0;

		$image_ids = isset( $_POST['image_ids'] ) ? array_map( 'intval', $_POST['image_ids'] ) : array();
		$this->log_debug( 'MSH Safe Rename: Received image_ids from JavaScript: ' . print_r( $image_ids, true ) );

		if ( empty( $image_ids ) ) {
			global $wpdb;
			$image_ids = $wpdb->get_col(
				"
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_msh_suggested_filename'
                AND meta_value != ''
            "
			);

			// Debug: Log how many suggestions were found
			$this->log_debug( 'MSH Safe Rename: Found ' . count( $image_ids ) . ' images with filename suggestions' );
		}

		if ( $mode === 'test' && $limit > 0 ) {
			$image_ids = array_slice( $image_ids, 0, $limit );
		}

		// Calculate batch boundaries
		$total_count   = count( $image_ids );
		$start_index   = ( $batch_number - 1 ) * $batch_size;
		$current_batch = array_slice( $image_ids, $start_index, $batch_size );
		$total_batches = ceil( $total_count / $batch_size );

		$this->log_debug( "MSH Safe Rename: Processing batch {$batch_number}/{$total_batches} - " . count( $current_batch ) . " files (of {$total_count} total)" );

		if ( empty( $current_batch ) ) {
			// All batches processed or no files to process
			wp_send_json_success(
				array(
					'results' => array(),
					'summary' => array(
						'total'                => $total_count,
						'success'              => 0,
						'errors'               => 0,
						'skipped'              => 0,
						'mode'                 => $mode,
						'batch_complete'       => true,
						'all_batches_complete' => true,
					),
				)
			);
		}

		try {
			// SMART APPROACH: Index ONLY the files in current batch
			$indexed_count = 0;
			$skipped_count = 0;
			if ( class_exists( 'MSH_Image_Usage_Index' ) ) {
				$usage_index = MSH_Image_Usage_Index::get_instance();

				if ( ! method_exists( $usage_index, 'index_attachment_usage' ) ) {
					$this->log_debug( 'MSH Safe Rename: Usage index instance missing index_attachment_usage method; skipping index warmup.' );
				} else {
					$this->log_debug( "MSH Safe Rename: Smart indexing batch {$batch_number}/{$total_batches} - indexing " . count( $current_batch ) . ' files' );

					foreach ( $current_batch as $attachment_id ) {
						try {
							// Check if index already exists - don't rebuild existing indexes!
							global $wpdb;
							$index_table    = $wpdb->prefix . 'msh_image_usage_index';
							$existing_count = $wpdb->get_var(
								$wpdb->prepare(
									"SELECT COUNT(*) FROM {$index_table} WHERE attachment_id = %d",
									$attachment_id
								)
							);

							if ( $existing_count > 0 ) {
								++$skipped_count;
								$this->log_debug( "MSH Safe Rename: ✅ SKIPPING $attachment_id - already indexed ($existing_count entries)" );
								continue;
							}

							$usage_index->index_attachment_usage( $attachment_id );
							++$indexed_count;
							if ( $indexed_count % 5 === 0 ) {
								$this->log_debug( "MSH Safe Rename: Just-in-time indexed $indexed_count files in batch {$batch_number}..." );
							}
						} catch ( Exception $e ) {
							$this->log_debug( "MSH Safe Rename: Failed to index $attachment_id: " . $e->getMessage() );
						}
					}
				}
			} else {
				$this->log_debug( 'MSH Safe Rename: Usage index class not available; skipping index warmup.' );
			}

			$this->log_debug( "MSH Safe Rename: Batch {$batch_number} indexing complete - indexed $indexed_count files, skipped $skipped_count files" );

			if ( ! class_exists( 'MSH_Safe_Rename_System' ) ) {
				$this->log_debug( 'MSH Safe Rename: ❌ Safe rename system class missing. Aborting batch.' );
				wp_send_json_error( __( 'Safe rename system is not available on this site.', 'msh-image-optimizer' ) );
			}

			$this->log_debug( 'MSH Safe Rename: About to get instance of MSH_Safe_Rename_System' );
			$renamer = MSH_Safe_Rename_System::get_instance();
			if ( ! is_object( $renamer ) || ! method_exists( $renamer, 'rename_attachment' ) ) {
				$this->log_debug( 'MSH Safe Rename: ❌ rename_attachment() missing on safe rename instance. Aborting batch.' );
				wp_send_json_error( __( 'Safe rename system is not fully loaded.', 'msh-image-optimizer' ) );
			}
			$this->log_debug( 'MSH Safe Rename: Successfully got instance of MSH_Safe_Rename_System' );

			// Record start time for performance tracking
			$batch_start_time = microtime( true );

			$this->log_debug( 'MSH Safe Rename: ===============================================' );
			$this->log_debug( "MSH Safe Rename: 🚀 STARTING BATCH {$batch_number}/{$total_batches} FILENAME APPLICATION" );
			$this->log_debug( 'MSH Safe Rename: ===============================================' );
			$this->log_debug( 'MSH Safe Rename: 📊 BATCH OVERVIEW:' );
			$this->log_debug( "MSH Safe Rename: 📊   Batch: {$batch_number} of {$total_batches}" );
			$this->log_debug( 'MSH Safe Rename: 📊   Files in this batch: ' . count( $current_batch ) );
			$this->log_debug( "MSH Safe Rename: 📊   Total files overall: {$total_count}" );
			$this->log_debug( 'MSH Safe Rename: 📊   Processing mode: ' . ( $mode === 'test' ? 'TEST MODE' : 'LIVE MODE' ) );
			$this->log_debug( 'MSH Safe Rename: 📊   Start time: ' . current_time('mysql') );
			$this->log_debug( 'MSH Safe Rename: ===============================================' );

			$results       = array();
			$success_count = 0;
			$error_count   = 0;
			$skipped_count = 0;
			$test_count    = 0;

			$batch_file_count   = count( $current_batch );
			$processed          = 0;
			$start_time         = time();
			$max_execution_time = 600; // 10 minutes max per batch

			foreach ( $current_batch as $attachment_id ) {
				++$processed;

				// Enhanced progress logging for batch
				$batch_percentage = round( ( $processed / $batch_file_count ) * 100, 1 );
				$this->log_debug( "MSH Safe Rename: 📊 BATCH {$batch_number} PROGRESS: File {$processed}/{$batch_file_count} ({$batch_percentage}%)" );

				// Check execution time to prevent timeout
				if ( ( time() - $start_time ) > $max_execution_time ) {
					$this->log_debug( "MSH Safe Rename: ⚠️ BATCH TIMEOUT - Stopping batch {$batch_number} at {$processed} of {$batch_file_count} files" );
					break;
				}

				// Enhanced logging for every file with comprehensive debugging
				$this->log_debug( 'MSH Safe Rename: ===============================================' );
				$this->log_debug( "MSH Safe Rename: [Stage 1/4] 🔍 BATCH PROCESSING FILE $processed/$total_count" );
				$this->log_debug( "MSH Safe Rename: [Stage 1/4] 📎 Attachment ID: $attachment_id" );

				// Get current attachment data with detailed logging
				$attachment = get_post( $attachment_id );
				if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
					$this->log_debug( "MSH Safe Rename: [Stage 1/4] ❌ Invalid attachment ID: $attachment_id" );
					$this->log_debug( 'MSH Safe Rename: [Stage 1/4] ❌ Attachment type: ' . ( $attachment ? $attachment->post_type : 'null' ) );
					continue;
				}

				// Log current attachment details
				$current_file = get_attached_file( $attachment_id );
				$this->log_debug( "MSH Safe Rename: [Stage 1/4] 📁 Current file: $current_file" );
				$this->log_debug( "MSH Safe Rename: [Stage 1/4] 📋 Attachment title: '{$attachment->post_title}'" );

				// Get the suggested filename with validation
				$suggested_filename = get_post_meta( $attachment_id, '_msh_suggested_filename', true );

				if ( ! $suggested_filename ) {
					$this->log_debug( "MSH Safe Rename: [Stage 1/4] ⚠️ No suggested filename for attachment: $attachment_id" );
					$this->log_debug( 'MSH Safe Rename: [Stage 1/4] ⚠️ Skipping this file - no rename suggestion available' );
					$results[] = array(
						'id'      => $attachment_id,
						'status'  => 'skipped',
						'message' => 'No filename suggestion available',
					);
					++$skipped_count;
					continue;
				}

				$this->log_debug( "MSH Safe Rename: [Stage 1/4] ✅ Found suggested filename: '$suggested_filename'" );
				$this->log_debug( 'MSH Safe Rename: [Stage 1/4] 🚀 Initiating safe rename process...' );

				$suggested_filename = sanitize_file_name( $suggested_filename );
				$this->log_debug( "MSH Safe Rename: [Stage 1/4] 🧹 Sanitized filename: '$suggested_filename'" );

				// Record timing for performance analysis
				$start_time = microtime( true );
				$this->log_debug( 'MSH Safe Rename: [Stage 2/4] 🚀 Calling rename_attachment() with mode: ' . ( $mode === 'test' ? 'TEST' : 'LIVE' ) );

					// ====== BEGIN: MSH RENAME LOCK (BATCH MODE) ======
					$old_relative       = get_post_meta( $attachment_id, '_wp_attached_file', true );
					$suggested_basename = basename( $suggested_filename );
					$new_relative       = str_replace( basename( $old_relative ), $suggested_basename, $old_relative );

					error_log( "[MSH BATCH RENAME LOCK] 🔒 Protecting attachment #{$attachment_id}: {$old_relative} → {$new_relative}" );

					global $wp_filter, $merged_filters;

					$pre_meta_snapshot  = null;
					$meta_snapshot      = null;

					if ( isset( $wp_filter['pre_update_post_metadata'] ) ) {
						$pre_hook = $wp_filter['pre_update_post_metadata'];
						if ( is_object( $pre_hook ) && isset( $pre_hook->callbacks ) ) {
							$pre_meta_snapshot = clone $pre_hook;
						} else {
							$pre_meta_snapshot = $pre_hook;
						}
					}

					if ( isset( $wp_filter['wp_update_attachment_metadata'] ) ) {
						$meta_hook = $wp_filter['wp_update_attachment_metadata'];
						if ( is_object( $meta_hook ) && isset( $meta_hook->callbacks ) ) {
							$meta_snapshot = clone $meta_hook;
						} else {
							$meta_snapshot = $meta_hook;
						}
					}

					// Set global guard
					$GLOBALS['_msh_guard'] = array(
						'post_id'           => (int) $attachment_id,
						'expected_rel'      => $new_relative,
						'expected_basename' => $suggested_basename,
						'ts'                => microtime( true ),
					);
					$GLOBALS['_msh_expected_basename'] = $suggested_basename;

					// Remove ALL filters and re-add only guards
					remove_all_filters( 'wp_update_attachment_metadata' );
					remove_all_filters( 'pre_update_post_metadata' );
					add_filter( 'pre_update_post_metadata', array( $renamer, 'guard_attached_file_corruption' ), 1, 4 );
					add_filter( 'wp_update_attachment_metadata', array( $renamer, 'guard_attachment_metadata_corruption' ), 9, 2 );

					$result = null;

					try {
						$result = $renamer->rename_attachment( $attachment_id, $suggested_basename, $mode === 'test' );

						// ====== VALIDATE & HEAL BEFORE RESTORING FILTERS ======
						if ( ! is_wp_error( $result ) && ! isset( $result['skipped'] ) ) {
							$final_rel = get_post_meta( $attachment_id, '_wp_attached_file', true );
							if ( $final_rel !== $new_relative ) {
								error_log( "[MSH BATCH HEAL] ⚠️ Final _wp_attached_file mismatch for #{$attachment_id}! Expected: {$new_relative}, Got: {$final_rel}" );
								update_post_meta( $attachment_id, '_wp_attached_file', $new_relative );
								error_log( "[MSH BATCH HEAL] ✅ Corrected _wp_attached_file to: {$new_relative}" );
							} else {
								error_log( "[MSH BATCH HEAL] ✅ Validation passed for #{$attachment_id}: {$final_rel}" );
							}
						}
					} finally {
						// Remove temporary guards
						remove_filter( 'pre_update_post_metadata', array( $renamer, 'guard_attached_file_corruption' ), 1 );
						remove_filter( 'wp_update_attachment_metadata', array( $renamer, 'guard_attachment_metadata_corruption' ), 9 );

						// Reset filters back to their original state
						remove_all_filters( 'pre_update_post_metadata' );
						remove_all_filters( 'wp_update_attachment_metadata' );

						if ( is_object( $pre_meta_snapshot ) && isset( $pre_meta_snapshot->callbacks ) ) {
							$wp_filter['pre_update_post_metadata'] = $pre_meta_snapshot;
						} elseif ( is_array( $pre_meta_snapshot ) && ! empty( $pre_meta_snapshot ) ) {
							foreach ( $pre_meta_snapshot as $priority => $callbacks ) {
								foreach ( $callbacks as $cb ) {
									add_filter( 'pre_update_post_metadata', $cb['function'], $priority, $cb['accepted_args'] );
								}
							}
						} else {
							unset( $wp_filter['pre_update_post_metadata'] );
						}

						if ( is_object( $meta_snapshot ) && isset( $meta_snapshot->callbacks ) ) {
							$wp_filter['wp_update_attachment_metadata'] = $meta_snapshot;
						} elseif ( is_array( $meta_snapshot ) && ! empty( $meta_snapshot ) ) {
							foreach ( $meta_snapshot as $priority => $callbacks ) {
								foreach ( $callbacks as $cb ) {
									add_filter( 'wp_update_attachment_metadata', $cb['function'], $priority, $cb['accepted_args'] );
								}
							}
						} else {
							unset( $wp_filter['wp_update_attachment_metadata'] );
						}

						if ( isset( $merged_filters ) && is_array( $merged_filters ) ) {
							unset( $merged_filters['pre_update_post_metadata'], $merged_filters['wp_update_attachment_metadata'] );
						}

						unset( $GLOBALS['_msh_guard'], $GLOBALS['_msh_expected_basename'] );
						error_log( "[MSH BATCH RENAME LOCK] 🔓 Lock released for attachment #{$attachment_id}" );
					}
					// ====== END: MSH RENAME LOCK (BATCH MODE) ======

				$end_time = microtime( true );
				$duration = round( ( $end_time - $start_time ), 2 );
				$this->log_debug( "MSH Safe Rename: [Stage 4/4] 🎉 Rename operation completed in {$duration}s" );

				if ( is_wp_error( $result ) ) {
					$this->log_debug( 'MSH Safe Rename: [Stage 4/4] ❌ Rename failed with WP_Error: ' . $result->get_error_message() );
					$this->log_debug( 'MSH Safe Rename: [Stage 4/4] ❌ Error code: ' . $result->get_error_code() );
					$error_data = $result->get_error_data();
					if ( ! empty( $error_data ) ) {
						$this->log_debug( 'MSH Safe Rename: [Stage 4/4] ❌ Error data: ' . print_r( $error_data, true ) );
					}
					$this->log_debug( 'MSH Safe Rename: [Stage 4/4] ❌ File processing COMPLETE - error!' );
					$results[] = array(
						'id'      => $attachment_id,
						'status'  => 'error',
						'message' => $result->get_error_message(),
					);
					++$error_count;
					continue;
				}

				if ( ! empty( $result['test_mode'] ) ) {
					$this->log_debug( 'MSH Safe Rename: [Stage 4/4] 🧪 Test mode - no filesystem changes were applied' );
					$results[] = array(
						'id'                 => $attachment_id,
						'status'             => 'test',
						'old_url'            => $result['old_url'],
						'new_url'            => $result['new_url'],
						'references_updated' => $result['replaced'],
						'message'            => __( 'Test mode - rename simulated only.', 'msh-image-optimizer' ),
					);
					++$test_count;
					++$success_count;
					continue;
				}

				if ( ! empty( $result['skipped'] ) ) {
					$this->log_debug( 'MSH Safe Rename: [Stage 4/4] ⚠️ File skipped - filename already optimized' );
					delete_post_meta( $attachment_id, '_msh_suggested_filename' );
					$this->log_debug( "MSH Safe Rename: [Stage 4/4] 🗑️ Cleared suggested filename meta for skipped file: $attachment_id" );
					$this->log_debug( 'MSH Safe Rename: [Stage 4/4] ⚠️ File processing COMPLETE - skipped!' );

					$results[] = array(
						'id'      => $attachment_id,
						'status'  => 'skipped',
						'message' => __( 'Filename already optimized', 'msh-image-optimizer' ),
					);
					++$skipped_count;
					continue;
				}

				$this->log_debug( "MSH Safe Rename: [Stage 4/4] ✅ Successfully renamed attachment $attachment_id to '$suggested_filename' in {$duration}s" );
				$this->log_debug( 'MSH Safe Rename: [Stage 4/4] 🔄 Database references updated: ' . $result['replaced'] );
				$this->log_debug( 'MSH Safe Rename: [Stage 4/4] 🔗 Old URL: ' . $result['old_url'] );
				$this->log_debug( 'MSH Safe Rename: [Stage 4/4] 🔗 New URL: ' . $result['new_url'] );

				delete_post_meta( $attachment_id, '_msh_suggested_filename' );
				$this->log_debug( "MSH Safe Rename: [Stage 4/4] 🗑️ Cleared suggested filename meta for attachment: $attachment_id" );
				$this->log_debug( 'MSH Safe Rename: [Stage 4/4] 🎉 File processing COMPLETE - success!' );

				$results[] = array(
					'id'                 => $attachment_id,
					'status'             => 'success',
					'old_url'            => $result['old_url'],
					'new_url'            => $result['new_url'],
					'references_updated' => $result['replaced'],
					'message'            => sprintf( __( 'References updated: %d', 'msh-image-optimizer' ), $result['replaced'] ),
				);
				++$success_count;
			}

			// Check if there are more files to process
			$has_more        = false;
			$remaining_count = 0;

			if ( count( $image_ids ) === 20 && $mode !== 'test' ) {
				// Check for more files with suggestions
				global $wpdb;
				$all_ids         = $wpdb->get_col(
					"
                SELECT post_id FROM {$wpdb->postmeta}
                WHERE meta_key = '_msh_suggested_filename'
                AND meta_value != ''
            "
				);
				$remaining_count = count( $all_ids ) - 20;
				$has_more        = $remaining_count > 0;
			}

			// Calculate final statistics
			$success_rate   = $processed > 0 ? round( ( $success_count / $processed ) * 100, 1 ) : 0;
			$end_time       = microtime( true );
			$total_duration = round( ( $end_time - $batch_start_time ), 2 );
			$avg_per_file   = $processed > 0 ? round( $total_duration / $processed, 2 ) : 0;

			$this->log_debug( 'MSH Safe Rename: ===============================================' );
			$this->log_debug( 'MSH Safe Rename: 🎉 BATCH RENAME PROCESS COMPLETE!' );
			$this->log_debug( 'MSH Safe Rename: ===============================================' );
			$this->log_debug( 'MSH Safe Rename: 📊 FINAL STATISTICS:' );
			$this->log_debug( "MSH Safe Rename: 📊   Total files processed: $processed" );
			$this->log_debug( "MSH Safe Rename: 📊   ✅ Successful renames: $success_count" );
			$this->log_debug( "MSH Safe Rename: 📊   ❌ Failed renames: $error_count" );
			$this->log_debug( "MSH Safe Rename: 📊   ⚠️ Skipped files: $skipped_count" );
			if ( $test_count > 0 ) {
				$this->log_debug( "MSH Safe Rename: 📊   🧪 Test simulations: $test_count" );
			}
			$this->log_debug( "MSH Safe Rename: 📊   🎯 Success rate: {$success_rate}%" );
			$this->log_debug( "MSH Safe Rename: 📊   ⏱️ Total duration: {$total_duration}s" );
			$this->log_debug( "MSH Safe Rename: 📊   ⚡ Average per file: {$avg_per_file}s" );
			$this->log_debug( 'MSH Safe Rename: 📊   🚀 Has more files: ' . ( $has_more ? 'YES' : 'NO' ) );
			if ( $has_more ) {
				$this->log_debug( "MSH Safe Rename: 📊   📝 Remaining files: $remaining_count" );
			}
			$this->log_debug( 'MSH Safe Rename: ===============================================' );

			// Calculate if there are more batches to process
			$has_more_batches  = $batch_number < $total_batches;
			$next_batch_number = $has_more_batches ? $batch_number + 1 : 0;

			wp_send_json_success(
				array(
					'results'     => $results,
					'summary'     => array(
						'total'          => $batch_file_count,
						'success'        => $success_count,
						'errors'         => $error_count,
						'skipped'        => $skipped_count,
						'test'           => $test_count,
						'mode'           => $mode,
						'batch_complete' => true,
					),
					'batch_info'  => array(
						'current_batch'          => $batch_number,
						'total_batches'          => $total_batches,
						'has_more_batches'       => $has_more_batches,
						'next_batch_number'      => $next_batch_number,
						'batch_size'             => $batch_size,
						'total_files'            => $total_count,
						'files_processed_so_far' => ( ( $batch_number - 1 ) * $batch_size ) + $processed,
						'overall_progress'       => round( ( ( ( $batch_number - 1 ) * $batch_size ) + $processed ) / $total_count * 100, 1 ),
					),
					'performance' => array(
						'duration'     => $total_duration,
						'avg_per_file' => $avg_per_file,
					),
				)
			);
		} catch ( Throwable $e ) {
			$fatal_message = sprintf(
				'MSH Safe Rename: Fatal error in batch %d: %s (%s:%d)',
				$batch_number,
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			);

			$this->log_debug( '❌ ' . $fatal_message );
			error_log( '[MSH Image Optimizer] ' . $fatal_message );

			$message = sprintf(
				__( 'Safe rename failed: %s', 'msh-image-optimizer' ),
				$e->getMessage()
			);
			wp_send_json_error( $message );
		}
	}

	/**
	 * AJAX handler: persist a single filename suggestion for review.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_save_filename_suggestion() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$image_id           = intval( $_POST['image_id'] ?? 0 );
		$raw_filename       = $_POST['suggested_filename'] ?? '';
		$suggested_filename = sanitize_file_name( $raw_filename );

		// DEBUG: Log what we received from JavaScript
		error_log( '[MSH Filename DEBUG] ajax_save_filename_suggestion() called for attachment ' . $image_id );
		error_log( '[MSH Filename DEBUG] Raw filename from POST: ' . $raw_filename );
		error_log( '[MSH Filename DEBUG] After sanitize_file_name(): ' . $suggested_filename );

		if ( ! $image_id || ! $suggested_filename ) {
			wp_send_json_error( 'Missing image ID or filename suggestion' );
			return;
		}

		// Validate the attachment exists
		if ( ! get_post( $image_id ) || get_post_type( $image_id ) !== 'attachment' ) {
			wp_send_json_error( 'Invalid attachment ID' );
			return;
		}

		// Ensure the filename has an extension
		if ( pathinfo( $suggested_filename, PATHINFO_EXTENSION ) === '' ) {
			// Get the original file extension
			$current_file = get_attached_file( $image_id );
			if ( $current_file ) {
				$original_extension  = pathinfo( $current_file, PATHINFO_EXTENSION );
				$suggested_filename .= '.' . $original_extension;
			} else {
				$suggested_filename .= '.jpg'; // Default fallback
			}
		}

		// Phase 1F: Save the suggestion with timestamp - use cached signature
		$current_context_signature = isset( $this->contextual_meta_generator ) && is_object( $this->contextual_meta_generator )
			? $this->contextual_meta_generator->get_context_signature()
			: MSH_Image_Optimizer_Context_Helper::get_active_context_signature();
		update_post_meta( $image_id, '_msh_suggested_filename', $suggested_filename );
		update_post_meta( $image_id, 'msh_filename_last_suggested', (int) time() );
		update_post_meta( $image_id, '_msh_suggested_filename_context', $current_context_signature );

		wp_send_json_success(
			array(
				'message'            => 'Filename suggestion saved successfully',
				'image_id'           => $image_id,
				'suggested_filename' => $suggested_filename,
			)
		);
	}

	/**
	 * AJAX handler: discard a stored filename suggestion.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_remove_filename_suggestion() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$image_id = intval( $_POST['image_id'] ?? 0 );

		if ( ! $image_id ) {
			wp_send_json_error( 'Missing image ID' );
			return;
		}

		// Validate the attachment exists
		if ( ! get_post( $image_id ) || get_post_type( $image_id ) !== 'attachment' ) {
			wp_send_json_error( 'Invalid attachment ID' );
			return;
		}

		// Remove the suggestion
		delete_post_meta( $image_id, '_msh_suggested_filename' );
		delete_post_meta( $image_id, '_msh_suggested_filename_context' );

		wp_send_json_success(
			array(
				'message'  => 'Filename suggestion removed - current name will be kept',
				'image_id' => $image_id,
			)
		);
	}

	/**
	 * AJAX handler: preview regenerated metadata for an attachment.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_preview_meta_text() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$image_id = intval( $_POST['image_id'] ?? 0 );

		if ( ! $image_id ) {
			wp_send_json_error( 'Missing image ID' );
			return;
		}

		// Validate the attachment exists
		if ( ! get_post( $image_id ) || get_post_type( $image_id ) !== 'attachment' ) {
			wp_send_json_error( 'Invalid attachment ID' );
			return;
		}

		// Generate meta text preview using the same logic as optimization
		$context = $this->determine_image_context( $image_id );

		$preview = array(
			'title'       => $this->generate_title( $image_id, $context ),
			'caption'     => $this->generate_caption( $image_id, $context ),
			'alt_text'    => $this->generate_alt_text( $image_id, $context ),
			'description' => $this->generate_description( $image_id, $context ),
		);

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX handler: persist manually edited metadata fields.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_save_edited_meta() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$image_id  = intval( $_POST['image_id'] ?? 0 );
		$meta_data = wp_unslash( $_POST['meta_data'] ?? array() );

		if ( ! $image_id || empty( $meta_data ) ) {
			wp_send_json_error( 'Missing image ID or meta data' );
			return;
		}

		// Validate the attachment exists
		if ( ! get_post( $image_id ) || get_post_type( $image_id ) !== 'attachment' ) {
			wp_send_json_error( 'Invalid attachment ID' );
			return;
		}

		$updates_made = array();

		// Update Title
		if ( ! empty( $meta_data['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $image_id,
					'post_title' => sanitize_text_field( $meta_data['title'] ),
					'post_name'  => sanitize_title( $meta_data['title'] ),
				)
			);
			$updates_made[] = 'title';
		}

		// Update Caption
		if ( ! empty( $meta_data['caption'] ) ) {
			wp_update_post(
				array(
					'ID'           => $image_id,
					'post_excerpt' => sanitize_textarea_field( $meta_data['caption'] ),
				)
			);
			$updates_made[] = 'caption';
		}

		// Update ALT text
		if ( ! empty( $meta_data['alt_text'] ) ) {
			update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $meta_data['alt_text'] ) );
			$updates_made[] = 'alt_text';
		}

		// Update Description
		if ( ! empty( $meta_data['description'] ) ) {
			wp_update_post(
				array(
					'ID'           => $image_id,
					'post_content' => sanitize_textarea_field( $meta_data['description'] ),
				)
			);
			$updates_made[] = 'description';
		}

		// Update metadata timestamp to reflect manual edit
		update_post_meta( $image_id, 'msh_metadata_last_updated', (int) time() );
		update_post_meta( $image_id, 'msh_metadata_source', 'manual_edit' );
		update_post_meta( $image_id, 'msh_metadata_context_hash', 'manual_edit' );

		wp_send_json_success(
			array(
				'message'      => 'Meta text updated successfully',
				'updates_made' => $updates_made,
				'image_id'     => $image_id,
			)
		);
	}

	/**
	 * AJAX handler: remove stale or invalid filename suggestions.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_clear_bad_suggestions() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;

		// NUCLEAR OPTION: Remove ALL suggestions to start fresh
		$total_count = $wpdb->query(
			"
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_msh_suggested_filename', '_msh_suggested_filename_context')
        "
		);

		wp_send_json_success(
			array(
				'message'       => "Cleared ALL {$total_count} filename suggestions. Run 'Analyze Published Images' to regenerate.",
				'total_removed' => $total_count,
			)
		);
	}

	/**
	 * Generate filename suggestions automatically for fresh uploads.
	 *
	 * @since 1.2.0
	 *
	 * @param int $attachment_id Newly uploaded attachment ID.
	 * @return void
	 */
	public function generate_suggestion_for_new_upload( $attachment_id ) {
		// Check if file rename feature is enabled
		if ( ! $this->is_file_rename_enabled() ) {
			return;
		}

		// Only process images
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		// Get current filename and extension
		$current_file = get_attached_file( $attachment_id );
		if ( ! $current_file ) {
			return;
		}

		$path_info        = pathinfo( $current_file );
		$extension        = isset( $path_info['extension'] ) ? strtolower( $path_info['extension'] ) : '';
		$current_basename = strtolower( $path_info['basename'] );

		// Skip if already has an SEO-optimized name
		$has_good_name = ( strpos( $current_basename, 'msh' ) !== false ||
						strpos( $current_basename, 'hamilton' ) !== false ||
						strpos( $current_basename, 'main-street-health' ) !== false ||
						// Also detect common SEO patterns our system generates
						preg_match( '/^(rehabilitation|physiotherapy|chiropractic|acupuncture|massage|orthotics|chronic-pain|work-related|sport-injuries|motor-vehicle|patient-testimonial|bluecross|canada-life|manulife)-/', $current_basename ) ||
						// Or files that end with attachment ID pattern (our system's signature)
						preg_match( '/-\d{4,5}\.(jpg|jpeg|png|gif|svg|webp)$/', $current_basename ) );

		if ( $has_good_name || empty( $extension ) ) {
			return; // Don't generate suggestions for good files or files without extensions
		}

		// Skip if already has a suggestion
		$existing_suggestion = get_post_meta( $attachment_id, '_msh_suggested_filename', true );
		if ( ! empty( $existing_suggestion ) ) {
			return;
		}

		// Auto-detect context from filename and path
		$context_details = $this->contextual_meta_generator->detect_context( $attachment_id );

		// Generate suggestion using the contextual generator
		$slug = $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_details, $extension );

		if ( ! empty( $slug ) ) {
			$suggested_filename = $this->ensure_unique_filename( $slug, $extension, $attachment_id );
			update_post_meta( $attachment_id, '_msh_suggested_filename', $suggested_filename );
			update_post_meta( $attachment_id, 'msh_filename_last_suggested', time() );
			update_post_meta( $attachment_id, '_msh_suggested_filename_context', $this->contextual_meta_generator->get_context_signature() );

			// Log for debugging
			$this->log_debug( "MSH Auto-Suggestion: Generated '{$suggested_filename}' for new upload '{$current_basename}' (ID: {$attachment_id})" );
		}
	}

	/**
	 * AJAX handler: rebuild or warm the image usage index table.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_build_usage_index() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		@set_time_limit( 900 ); // 15 minutes for complete rebuild
		@ini_set( 'memory_limit', '512M' );

		// Create tables first
		$this->ensure_safe_rename_tables();

		// Get usage index instance
		$usage_index = MSH_Image_Usage_Index::get_instance();

		// Check if force rebuild is requested
		$force_rebuild = ! empty( $_POST['force_rebuild'] ) && $_POST['force_rebuild'] === 'true';

		// Check current index status first
		$stats           = $usage_index->get_index_stats();
		$summary_payload = $this->format_index_summary( $stats );
		$current_entries = isset( $summary_payload['total_entries'] ) ? (int) $summary_payload['total_entries'] : 0;

		if ( $current_entries > 0 && ! $force_rebuild ) {
			// Index already has data, return current stats (unless force rebuild)
			wp_send_json_success(
				array(
					'message'               => 'Usage index already exists with ' . $current_entries . ' entries.',
					'processed_attachments' => $summary_payload['indexed_attachments'] ?? 0,
					'stats'                 => array(
						'summary'    => $summary_payload,
						'by_context' => $stats['by_context'] ?? array(),
					),
					'status'                => 'already_built',
				)
			);
			return;
		}

		if ( $force_rebuild ) {
			$this->log_debug( 'MSH Index Build: Force rebuild requested - clearing existing index' );
			// Clear the existing index for complete rebuild
			global $wpdb;
			$table_name = $wpdb->prefix . 'msh_image_usage_index';
			$wpdb->query( "TRUNCATE TABLE $table_name" );
			$this->log_debug( 'MSH Index Build: Existing index cleared' );
		}

		// Build the index with small batch size for better performance
		$this->log_debug( 'MSH Index Build: Starting index build process' );
		$processed = $usage_index->build_complete_index( 25 ); // Small batches

		// Get final stats
		$final_stats   = $usage_index->get_index_stats();
		$final_summary = $this->format_index_summary( $final_stats );

		// Mark system as ready
		update_option( 'msh_safe_rename_enabled', '1' );

		wp_send_json_success(
			array(
				'message'               => 'Usage index built successfully! Processed ' . $processed . ' attachments.',
				'processed_attachments' => (int) $processed,
				'stats'                 => array(
					'summary'    => $final_summary,
					'by_context' => $final_stats['by_context'] ?? array(),
				),
				'status'                => 'newly_built',
			)
		);
	}

	private function ensure_safe_rename_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Create tables if they don't exist
		$tables = array(
			'wp_msh_image_usage_index'   => "CREATE TABLE IF NOT EXISTS wp_msh_image_usage_index (
                id int(11) NOT NULL AUTO_INCREMENT,
                attachment_id int(11) NOT NULL,
                url_variation text NOT NULL,
                table_name varchar(64) NOT NULL,
                row_id int(11) NOT NULL,
                column_name varchar(64) NOT NULL,
                context_type varchar(50) DEFAULT 'content',
                post_type varchar(20) DEFAULT NULL,
                last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY attachment_id (attachment_id),
                KEY table_row (table_name, row_id),
                KEY url_variation (url_variation(191)),
                KEY context_type (context_type)
            ) $charset_collate",
			'wp_msh_rename_backups'      => "CREATE TABLE IF NOT EXISTS wp_msh_rename_backups (
                id int(11) NOT NULL AUTO_INCREMENT,
                operation_id varchar(32) NOT NULL,
                attachment_id int(11) NOT NULL,
                table_name varchar(64) NOT NULL,
                row_id int(11) NOT NULL,
                column_name varchar(64) NOT NULL,
                original_value longtext NOT NULL,
                backup_date datetime DEFAULT CURRENT_TIMESTAMP,
                status varchar(20) DEFAULT 'active',
                PRIMARY KEY (id),
                KEY operation_id (operation_id),
                KEY attachment_id (attachment_id),
                KEY backup_date (backup_date)
            ) $charset_collate",
			'wp_msh_rename_verification' => "CREATE TABLE IF NOT EXISTS wp_msh_rename_verification (
                id int(11) NOT NULL AUTO_INCREMENT,
                operation_id varchar(32) NOT NULL,
                attachment_id int(11) NOT NULL,
                check_type varchar(50) NOT NULL,
                expected_value text NOT NULL,
                actual_value text NOT NULL,
                status varchar(20) NOT NULL,
                error_message text NULL,
                check_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY operation_id (operation_id),
                KEY attachment_id (attachment_id),
                KEY status (status)
            ) $charset_collate",
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $tables as $table_name => $sql ) {
			dbDelta( $sql );
		}

		// Set options to mark tables as created
		update_option( 'msh_usage_index_table_version', '1' );
		update_option( 'msh_backup_tables_version', '1' );
	}

	private function format_index_summary( $stats ) {
		if ( empty( $stats ) ) {
			return null;
		}

		$usage_index = MSH_Image_Usage_Index::get_instance();

		if ( is_array( $stats ) ) {
			$formatted = $usage_index->format_stats_for_ui( $stats );
			if ( $formatted !== null ) {
				return $formatted;
			}
			$summary = $stats['summary'] ?? null;
		} else {
			$summary = $stats;
		}

		if ( ! $summary ) {
			return null;
		}

		$base = array(
			'total_entries'       => isset( $summary->total_entries ) ? (int) $summary->total_entries : 0,
			'indexed_attachments' => isset( $summary->indexed_attachments ) ? (int) $summary->indexed_attachments : 0,
			'unique_locations'    => isset( $summary->unique_locations ) ? (int) $summary->unique_locations : 0,
			'last_update_raw'     => $summary->last_update,
			'last_update_display' => $summary->last_update ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $summary->last_update, false ) : null,
			'by_context'          => array(),
			'orphaned_entries'    => 0,
			'orphan_preview'      => array(),
		);

		return $base;
	}

	/**
	 * AJAX handler: return the total number of image attachments.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_get_attachment_count() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_mime_type LIKE %s",
				'attachment',
				$image_mime_like
			)
		);

		wp_send_json_success(
			array(
				'count' => (int) $count,
			)
		);
	}

	/**
	 * AJAX handler: report how many attachments still require indexing.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_get_remaining_count() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

		// Get total attachments
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_mime_type LIKE %s",
				'attachment',
				$image_mime_like
			)
		);

		// Get already indexed count
		$indexed = $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id)
             FROM {$wpdb->prefix}msh_image_usage_index"
		);

		$remaining = max( 0, $total - $indexed );

		wp_send_json_success(
			array(
				'total'            => (int) $total,
				'indexed'          => (int) $indexed,
				'remaining'        => (int) $remaining,
				'percent_complete' => $total > 0 ? round( ( $indexed / $total ) * 100, 1 ) : 0,
			)
		);
	}

	/**
	 * AJAX handler: report server capabilities required for optimisation.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_check_capabilities() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$capabilities = array(
			'dependencies' => array(
				'imagick_compare' => extension_loaded( 'imagick' ) && method_exists( 'Imagick', 'compareImages' ),
				'imagick'         => extension_loaded( 'imagick' ),
				'gd'              => extension_loaded( 'gd' ),
				'webp'            => function_exists( 'imagewebp' ),
			),
		);

		wp_send_json_success( $capabilities );
	}

	/**
	 * Convert image to WebP format
	 */
	private function convert_to_webp( $source_path, $webp_path ) {
		if ( ! function_exists( 'imagewebp' ) ) {
			return false; // WebP not supported
		}

		// Get image info
		$image_info = getimagesize( $source_path );
		if ( ! $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];
		$image     = null;

		// Create image resource based on type
		switch ( $mime_type ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $source_path );
				break;
			case 'image/png':
				$image = imagecreatefrompng( $source_path );
				// Preserve transparency
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				break;
			default:
				return false;
		}

		if ( ! $image ) {
			return false;
		}

		// Convert to WebP with 85% quality (good balance of quality/size)
		$result = imagewebp( $image, $webp_path, 85 );

		// Clean up memory
		imagedestroy( $image );

		return $result;
	}

	/**
	 * AJAX handler: enable or disable the safe rename subsystem.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_toggle_file_rename() {
		check_ajax_referer( 'msh_toggle_file_rename', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'msh-image-optimizer' ) ), 403 );
		}

		$old_value = get_option( 'msh_enable_file_rename', '0' );
		$new_value = isset( $_POST['enabled'] ) && (string) $_POST['enabled'] === '1' ? '1' : '0';

		update_option( 'msh_enable_file_rename', $new_value );

		// If toggled from off → on, schedule background job to regenerate suggestions
		if ( $old_value === '0' && $new_value === '1' ) {
			// Schedule a one-time action to regenerate filename suggestions
			if ( ! wp_next_scheduled( 'msh_regenerate_filename_suggestions' ) ) {
				wp_schedule_single_event( time(), 'msh_regenerate_filename_suggestions' );
			}
		}

		wp_send_json_success(
			array(
				'enabled' => $new_value,
			)
		);
	}

	/**
	 * AJAX handler: switch the AI metadata mode between manual and assist.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_toggle_ai_mode() {
		check_ajax_referer( 'msh_toggle_ai_mode', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'msh-image-optimizer' ) ), 403 );
		}

		$mode          = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'manual';
		$allowed_modes = array( 'manual', 'assist' );

		if ( ! in_array( $mode, $allowed_modes, true ) ) {
			$mode = 'manual';
		}

		update_option( 'msh_ai_mode', $mode, false );

		// CRITICAL FIX: Update msh_ai_features to enable/disable 'meta' feature
		$features = get_option( 'msh_ai_features', array() );
		if ( ! is_array( $features ) ) {
			$features = array();
		}

		if ( $mode === 'manual' ) {
			// Remove 'meta' feature when AI is disabled
			$features = array_diff( $features, array( 'meta' ) );
			$features = array_values( $features ); // Re-index array
		} else {
			// Add 'meta' feature when AI is enabled
			if ( ! in_array( 'meta', $features, true ) ) {
				$features[] = 'meta';
			}
		}

		update_option( 'msh_ai_features', $features, false );

		// Ensure next Analyze run reflects the new mode by clearing cached results
		$this->clear_analysis_cache();

		wp_send_json_success(
			array(
				'mode'     => $mode,
				'features' => $features,
			)
		);
	}

	/**
	 * AJAX handler: accept and apply a stored filename suggestion.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_accept_filename_suggestion() {
		$this->log_debug( 'MSH Accept Debug: Starting ajax_accept_filename_suggestion' );

		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$image_id = intval( $_POST['image_id'] ?? 0 );
		$this->log_debug( "MSH Accept Debug: Image ID = $image_id" );

		if ( ! $image_id ) {
			wp_send_json_error( 'Missing image ID' );
			return;
		}

		// Validate the attachment exists
		if ( ! get_post( $image_id ) || get_post_type( $image_id ) !== 'attachment' ) {
			wp_send_json_error( 'Invalid attachment ID' );
			return;
		}

		// Get the suggested filename
		$suggested_filename = get_post_meta( $image_id, '_msh_suggested_filename', true );

		if ( ! $suggested_filename ) {
			wp_send_json_error( 'No filename suggestion found for this image' );
			return;
		}

		$this->log_debug( 'MSH Accept Debug: Applying rename via apply_suggested_filename_now' );

		$rename_feedback = $this->apply_suggested_filename_now( $image_id, $suggested_filename );

		if ( empty( $rename_feedback ) || ! is_array( $rename_feedback ) ) {
			wp_send_json_error( 'Rename operation returned an invalid response.' );
			return;
		}

		if ( $rename_feedback['status'] === 'success' ) {
			wp_send_json_success(
				array(
					'message'         => 'Filename updated successfully.',
					'new_filename'    => $rename_feedback['filename'],
					'image_id'        => $image_id,
					'rename_feedback' => $rename_feedback,
				)
			);
			return;
		}

		if ( $rename_feedback['status'] === 'skipped' ) {
			wp_send_json_success(
				array(
					'message'         => $rename_feedback['message'] ?? 'Filename already optimized.',
					'new_filename'    => $suggested_filename,
					'image_id'        => $image_id,
					'rename_feedback' => $rename_feedback,
				)
			);
			return;
		}

		$error_message = $rename_feedback['message'] ?? __( 'Safe rename failed.', 'msh-image-optimizer' );
		wp_send_json_error( $error_message );
	}

	/**
	 * AJAX handler: dismiss a filename suggestion without renaming.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_reject_filename_suggestion() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$image_id = intval( $_POST['image_id'] ?? 0 );

		if ( ! $image_id ) {
			wp_send_json_error( 'Missing image ID' );
			return;
		}

		// Validate the attachment exists
		if ( ! get_post( $image_id ) || get_post_type( $image_id ) !== 'attachment' ) {
			wp_send_json_error( 'Invalid attachment ID' );
			return;
		}

		// Remove the suggestion (same as existing ajax_remove_filename_suggestion)
		delete_post_meta( $image_id, '_msh_suggested_filename' );

		wp_send_json_success(
			array(
				'message'  => 'Filename suggestion dismissed - current name will be kept',
				'image_id' => $image_id,
			)
		);
	}

	/**
	 * AJAX handler: verify the WebP conversion status for optimised media.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function ajax_verify_webp_status() {
		// Debug logging removed for production

		if ( ! current_user_can( 'manage_options' ) ) {
			// Debug logging removed for production
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'msh_image_optimizer' ) ) {
			// Debug logging removed for production
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		try {
			// Debug logging removed for production
			$verification_results = $this->perform_webp_verification();
			// Debug logging removed for production
			wp_send_json_success( $verification_results );
		} catch ( Exception $e ) {
			// Debug logging removed for production
			// Debug logging removed for production
			wp_send_json_error( 'Verification failed: ' . $e->getMessage() );
		} catch ( Error $e ) {
			// Debug logging removed for production
			// Debug logging removed for production
			wp_send_json_error( 'Fatal error during verification: ' . $e->getMessage() );
		}
	}

	/**
	 * Perform comprehensive WebP verification audit
	 */
	private function perform_webp_verification() {
		// Debug logging removed for production

		try {
			$published_images = $this->get_published_images();
			// Debug logging removed for production
		} catch ( Exception $e ) {
			// Debug logging removed for production
			throw $e;
		}

		$results = array(
			'total_images'              => count( $published_images ),
			'total_optimized'           => 0,
			'webp_compatible_optimized' => 0,
			'webp_missing'              => 0,
			'svg_files'                 => 0,
			'issues_found'              => array(),
			'summary'                   => '',
			'stats'                     => array(),
		);

		foreach ( $published_images as $image ) {
			$attachment_id = $image['ID'];
			$status        = $this->get_optimization_status( $attachment_id );

			// Only check optimized images
			if ( $status !== 'optimized' ) {
				continue;
			}

			++$results['total_optimized'];

			// Get file extension
			$file_path      = $image['file_path'] ?? '';
			$file_extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

			// Debug logging removed for production

			// Handle SVG files separately
			if ( $file_extension === 'svg' ) {
				++$results['svg_files'];
				continue;
			}

			// For WebP-compatible formats (JPG, JPEG, PNG)
			if ( in_array( $file_extension, array( 'jpg', 'jpeg', 'png' ) ) ) {
				++$results['webp_compatible_optimized'];

				// Check if WebP version exists
				if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
					$webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );

					if ( ! file_exists( $webp_path ) ) {
						++$results['webp_missing'];
						$results['issues_found'][] = array(
							'attachment_id' => $attachment_id,
							'filename'      => basename( $file_path ),
							'issue'         => 'webp_missing',
							'message'       => 'Optimized but missing WebP version',
						);

						// Debug logging removed for production
					} else {
						// Debug logging removed for production
					}
				} else {
					$results['issues_found'][] = array(
						'attachment_id' => $attachment_id,
						'filename'      => 'File missing',
						'issue'         => 'source_missing',
						'message'       => 'Source file missing from disk',
					);
				}
			}
		}

		// Calculate stats
		$webp_success_rate = $results['webp_compatible_optimized'] > 0
			? ( ( $results['webp_compatible_optimized'] - $results['webp_missing'] ) / $results['webp_compatible_optimized'] ) * 100
			: 100;

		// Generate summary
		$results['summary'] = sprintf(
			'WebP Verification Complete: %d optimized images checked. ' .
			'%d WebP-compatible images found, %d missing WebP versions (%.1f%% success rate). ' .
			'%d SVG files (WebP not needed).',
			$results['total_optimized'],
			$results['webp_compatible_optimized'],
			$results['webp_missing'],
			$webp_success_rate,
			$results['svg_files']
		);

		$results['stats'] = array(
			'webp_success_rate' => round( $webp_success_rate, 1 ),
			'issues_count'      => count( $results['issues_found'] ),
		);

		// Debug logging removed for production

		return $results;
	}

	/**
	 * Optimise a list of attachments when invoked via WP-CLI.
	 *
	 * @since 1.2.0
	 *
	 * @param int[] $attachment_ids Attachment IDs to process.
	 * @param array $args           Optional. Flags provided by the CLI command.
	 * @return array Summary of optimisation outcomes.
	 */
	public function optimize_attachments_cli( array $attachment_ids, array $args = array() ) {
		$summary = array(
			'processed' => 0,
			'optimized' => array(),
			'errors'    => array(),
			'skipped'   => array(),
		);

		$generator     = $this->contextual_meta_generator;
		$batch_capable = is_object( $generator ) && method_exists( $generator, 'enable_batch_mode' );

		if ( $batch_capable && ! empty( $attachment_ids ) ) {
			$generator->enable_batch_mode();
		}

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = intval( $attachment_id );
			if ( $attachment_id <= 0 ) {
				$summary['errors'][] = array(
					'id'      => $attachment_id,
					'message' => __( 'Invalid attachment ID.', 'msh-image-optimizer' ),
				);
				continue;
			}

			$result = $this->optimize_single_image( $attachment_id );

			if ( $result['status'] === 'error' ) {
				$summary['errors'][] = array(
					'id'      => $attachment_id,
					'message' => implode( '; ', $result['actions'] ),
				);
				continue;
			}

			$webp_path   = $this->get_webp_path_for_attachment( $attachment_id );
			$webp_exists = $webp_path ? file_exists( $webp_path ) : false;

			if ( ! empty( $result['meta_applied'] ) || $webp_exists ) {
				$summary['optimized'][] = array(
					'id'             => $attachment_id,
					'status'         => $result['status'],
					'meta_updated'   => array_keys( $result['meta_applied'] ),
					'webp_generated' => $webp_exists,
				);
			} else {
				$summary['skipped'][] = array(
					'id'      => $attachment_id,
					'message' => implode( '; ', $result['actions'] ),
				);
			}

			++$summary['processed'];
		}

		if ( $batch_capable && ! empty( $attachment_ids ) ) {
			$generator->disable_batch_mode();
		}

		if ( $summary['processed'] > 0 ) {
			update_option( 'msh_last_cli_optimization', current_time( 'mysql' ) );
		}

		return $summary;
	}

	private function get_webp_path_for_attachment( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return '';
		}

		if ( ! preg_match( '/\.(jpg|jpeg|png)$/i', $file_path ) ) {
			return '';
		}

		return preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI && ! class_exists( 'MSH_CLI' ) ) {
	require_once __DIR__ . '/class-msh-cli.php';
}

// Initialize the optimizer
MSH_Image_Optimizer::get_instance();
