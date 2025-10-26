<?php
/**
 * Template Manager - CRUD operations for TinyDot Template Intelligence
 *
 * Handles create, read, update, delete operations for metadata templates
 * with validation, caching, and version management.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Template_Manager {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Table name
	 */
	private $table_name;

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'msh_optimizer_templates';
	}

	/**
	 * Create new template
	 *
	 * @param array $data Template data.
	 * @return int|WP_Error Template ID or error.
	 */
	public function create_template( $data ) {
		// Validate required fields
		$validation = $this->validate_template_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;

		// Prepare data with defaults
		$template = wp_parse_args( $data, array(
			'site_id'              => '',
			'locale'               => 'en',
			'usage_type'           => 'featured',
			'intent'               => 'on_topic',
			'template_title'       => null,
			'template_alt'         => null,
			'template_caption'     => null,
			'template_description' => null,
			'required_tokens'      => '[]',
			'negative_tokens'      => '[]',
			'nice_to_have_tokens'  => '[]',
			'variables'            => '["subject","entity","post_title"]',
			'max_len'              => '{"alt":125,"title":60}',
			'notes'                => null,
			'priority'             => 50,
			'is_active'            => 1,
			'mode'                 => 'active',
			'version'              => 1,
			'created_at'           => current_time( 'mysql' ),
			'updated_at'           => current_time( 'mysql' ),
		) );

		// Insert into database
		$result = $wpdb->insert(
			$this->table_name,
			$template,
			array(
				'%s', // site_id
				'%s', // locale
				'%s', // usage_type
				'%s', // intent
				'%s', // template_title
				'%s', // template_alt
				'%s', // template_caption
				'%s', // template_description
				'%s', // required_tokens
				'%s', // negative_tokens
				'%s', // nice_to_have_tokens
				'%s', // variables
				'%s', // max_len
				'%s', // notes
				'%d', // priority
				'%d', // is_active
				'%s', // mode
				'%d', // version
				'%s', // created_at
				'%s', // updated_at
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'db_insert_error', $wpdb->last_error );
		}

		// Bump cache version
		$this->bump_cache_version();

		return $wpdb->insert_id;
	}

	/**
	 * Get template by ID
	 *
	 * @param int $template_id Template ID.
	 * @return array|null Template data or null.
	 */
	public function get_template( $template_id ) {
		global $wpdb;

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$template_id
			),
			ARRAY_A
		);

		return $template;
	}

	/**
	 * Update template
	 *
	 * @param int   $template_id Template ID.
	 * @param array $data        Updated data.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function update_template( $template_id, $data ) {
		// Validate data
		$validation = $this->validate_template_data( $data, false );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		global $wpdb;

		// Add updated timestamp
		$data['updated_at'] = current_time( 'mysql' );

		// Determine format strings
		$formats = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, array( 'priority', 'is_active', 'version' ), true ) ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		$result = $wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $template_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_update_error', $wpdb->last_error );
		}

		// Bump cache version
		$this->bump_cache_version();

		return true;
	}

	/**
	 * Delete template
	 *
	 * @param int $template_id Template ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_template( $template_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $template_id ),
			array( '%d' )
		);

		if ( $result ) {
			$this->bump_cache_version();
		}

		return (bool) $result;
	}

	/**
	 * Get templates with filters
	 *
	 * @param array $filters Query filters.
	 * @return array Templates.
	 */
	public function get_templates( $filters = array() ) {
		global $wpdb;

		$where = array( '1=1' );
		$values = array();

		// Locale filter
		if ( ! empty( $filters['locale'] ) ) {
			$where[] = 'locale = %s';
			$values[] = $filters['locale'];
		}

		// Usage type filter
		if ( ! empty( $filters['usage_type'] ) ) {
			$where[] = 'usage_type = %s';
			$values[] = $filters['usage_type'];
		}

		// Intent filter
		if ( ! empty( $filters['intent'] ) ) {
			$where[] = 'intent = %s';
			$values[] = $filters['intent'];
		}

		// Mode filter
		if ( ! empty( $filters['mode'] ) ) {
			$where[] = 'mode = %s';
			$values[] = $filters['mode'];
		}

		// Active filter
		if ( isset( $filters['is_active'] ) ) {
			$where[] = 'is_active = %d';
			$values[] = (int) $filters['is_active'];
		}

		// Site ID filter
		if ( ! empty( $filters['site_id'] ) ) {
			$where[] = 'site_id = %s';
			$values[] = $filters['site_id'];
		}

		$where_clause = implode( ' AND ', $where );

		// Order by priority descending (highest first)
		$order = 'ORDER BY priority DESC, id ASC';

		$sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} {$order}";

		if ( ! empty( $values ) ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$templates = $wpdb->get_results( $sql, ARRAY_A );

		return $templates ? $templates : array();
	}

	/**
	 * Get active templates for matching
	 *
	 * @param string $locale     Locale.
	 * @param string $usage_type Usage type.
	 * @param string $intent     Intent.
	 * @return array Active templates.
	 */
	public function get_active_templates( $locale, $usage_type, $intent ) {
		// Build cache key
		$version = $this->get_cache_version();
		$cache_key = "tpl_v{$version}:{$locale}:{$usage_type}:{$intent}";

		// Try object cache first
		$templates = wp_cache_get( $cache_key, 'tinydot_templates' );

		if ( false === $templates ) {
			// Try transient
			$templates = get_transient( $cache_key );

			if ( false === $templates ) {
				// Query database
				$templates = $this->get_templates( array(
					'locale'     => $locale,
					'usage_type' => $usage_type,
					'intent'     => $intent,
					'mode'       => 'active',
					'is_active'  => 1,
				) );

				// Cache for 1 hour
				set_transient( $cache_key, $templates, HOUR_IN_SECONDS );
				wp_cache_set( $cache_key, $templates, 'tinydot_templates', HOUR_IN_SECONDS );
			}
		}

		return $templates;
	}

	/**
	 * Get shadow templates for evaluation
	 *
	 * @param string $locale     Locale.
	 * @param string $usage_type Usage type.
	 * @param string $intent     Intent.
	 * @return array Shadow templates.
	 */
	public function get_shadow_templates( $locale, $usage_type, $intent ) {
		return $this->get_templates( array(
			'locale'     => $locale,
			'usage_type' => $usage_type,
			'intent'     => $intent,
			'mode'       => 'shadow',
			'is_active'  => 1,
		) );
	}

	/**
	 * Validate template data
	 *
	 * @param array $data           Template data.
	 * @param bool  $require_fields Require all fields (for create).
	 * @return true|WP_Error True if valid, error otherwise.
	 */
	private function validate_template_data( $data, $require_fields = true ) {
		$errors = array();

		// Required fields for create
		if ( $require_fields ) {
			$required = array( 'locale', 'usage_type', 'intent' );
			foreach ( $required as $field ) {
				if ( empty( $data[ $field ] ) ) {
					$errors[] = sprintf( 'Missing required field: %s', $field );
				}
			}
		}

		// Validate JSON columns
		$json_fields = array( 'required_tokens', 'negative_tokens', 'nice_to_have_tokens', 'variables', 'max_len' );
		foreach ( $json_fields as $field ) {
			if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
				if ( ! $this->is_valid_json( $data[ $field ] ) ) {
					$errors[] = sprintf( 'Invalid JSON in field: %s', $field );
				}
			}
		}

		// Validate variables if present
		if ( isset( $data['variables'] ) ) {
			$vars = json_decode( $data['variables'], true );
			if ( is_array( $vars ) ) {
				$allowed = array( 'subject', 'entity', 'post_title' );
				foreach ( $vars as $var ) {
					if ( ! in_array( $var, $allowed, true ) ) {
						$errors[] = sprintf( 'Unknown variable: {%s}. Allowed: subject, entity, post_title', $var );
					}
				}
			}
		}

		// Validate mode
		if ( isset( $data['mode'] ) && ! in_array( $data['mode'], array( 'active', 'shadow', 'inactive' ), true ) ) {
			$errors[] = 'Invalid mode. Must be: active, shadow, or inactive';
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_error', implode( '; ', $errors ) );
		}

		return true;
	}

	/**
	 * Validate JSON string
	 *
	 * @param string $json JSON string.
	 * @return bool True if valid.
	 */
	private function is_valid_json( $json ) {
		if ( function_exists( 'wp_json_validate' ) ) {
			return wp_json_validate( $json );
		}

		json_decode( $json );
		return json_last_error() === JSON_ERROR_NONE;
	}

	/**
	 * Bump cache version (atomic)
	 *
	 * @return int New version number.
	 */
	private function bump_cache_version() {
		// Try object cache first (fast, atomic)
		if ( wp_using_ext_object_cache() ) {
			$version = wp_cache_incr( 'tpl_version', 1, 'tinydot_templates' );
			if ( false === $version ) {
				$version = 1;
				wp_cache_set( 'tpl_version', $version, 'tinydot_templates' );
			}
			// Mirror to option
			update_option( 'msh_template_cache_version', $version, true );
		} else {
			// Fallback to option (atomic via database)
			$version = (int) get_option( 'msh_template_cache_version', 0 );
			$version++;
			update_option( 'msh_template_cache_version', $version, true ); // autoload=yes
		}

		return $version;
	}

	/**
	 * Get current cache version
	 *
	 * @return int Cache version.
	 */
	private function get_cache_version() {
		if ( wp_using_ext_object_cache() ) {
			$version = wp_cache_get( 'tpl_version', 'tinydot_templates' );
			if ( false === $version ) {
				$version = (int) get_option( 'msh_template_cache_version', 1 );
				wp_cache_set( 'tpl_version', $version, 'tinydot_templates' );
			}
		} else {
			$version = (int) get_option( 'msh_template_cache_version', 1 );
		}

		return $version;
	}
}
