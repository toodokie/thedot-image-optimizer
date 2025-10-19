<?php
/**
 * Locale Profile Manager
 *
 * CRUD operations for locale profiles and glossary entries.
 *
 * @package MSH_Image_Optimizer
 * @subpackage AI_Translation
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_Locale_Profile_Manager class.
 */
class MSH_Locale_Profile_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Locale_Profile_Manager
	 */
	private static $instance = null;

	/**
	 * Database manager.
	 *
	 * @var MSH_Locale_Database
	 */
	private $db;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Locale_Profile_Manager
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
		$this->db = MSH_Locale_Database::get_instance();
	}

	/**
	 * Create or update a locale profile.
	 *
	 * @param string $locale Locale code.
	 * @param array  $profile_data Profile data.
	 * @return int|false Profile ID on success, false on failure.
	 */
	public function save_profile( $locale, $profile_data ) {
		global $wpdb;

		$table = $this->db->get_profiles_table();

		// Validate locale
		if ( empty( $locale ) || ! preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $locale ) ) {
			return false;
		}

		// Default values
		$defaults = array(
			'tone'                 => 'professional',
			'cta_style'            => 'subtle',
			'formality_level'      => 3,
			'special_instructions' => '',
			'forbidden_terms'      => '',
			'confidence_threshold' => 0.70,
		);

		$data = wp_parse_args( $profile_data, $defaults );

		// Validate formality level
		$data['formality_level'] = max( 1, min( 5, (int) $data['formality_level'] ) );

		// Validate confidence threshold
		$data['confidence_threshold'] = max( 0.0, min( 1.0, (float) $data['confidence_threshold'] ) );

		// Check if profile exists
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE locale = %s",
				$locale
			)
		);

		if ( $existing_id ) {
			// Update existing
			$result = $wpdb->update(
				$table,
				array(
					'tone'                 => $data['tone'],
					'cta_style'            => $data['cta_style'],
					'formality_level'      => $data['formality_level'],
					'special_instructions' => $data['special_instructions'],
					'forbidden_terms'      => $data['forbidden_terms'],
					'confidence_threshold' => $data['confidence_threshold'],
				),
				array( 'id' => $existing_id ),
				array( '%s', '%s', '%d', '%s', '%s', '%f' ),
				array( '%d' )
			);

			return false !== $result ? $existing_id : false;
		} else {
			// Insert new
			$result = $wpdb->insert(
				$table,
				array(
					'locale'               => $locale,
					'tone'                 => $data['tone'],
					'cta_style'            => $data['cta_style'],
					'formality_level'      => $data['formality_level'],
					'special_instructions' => $data['special_instructions'],
					'forbidden_terms'      => $data['forbidden_terms'],
					'confidence_threshold' => $data['confidence_threshold'],
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%f' )
			);

			return false !== $result ? $wpdb->insert_id : false;
		}
	}

	/**
	 * Get a locale profile.
	 *
	 * @param string $locale Locale code.
	 * @return array|null Profile data or null if not found.
	 */
	public function get_profile( $locale ) {
		global $wpdb;

		$table = $this->db->get_profiles_table();

		$profile = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE locale = %s",
				$locale
			),
			ARRAY_A
		);

		return $profile;
	}

	/**
	 * Get profile with fallback.
	 *
	 * Falls back to site locale, then default locale, then English.
	 *
	 * @param string $locale Requested locale.
	 * @return array Profile data (may be fallback).
	 */
	public function get_profile_with_fallback( $locale ) {
		// Try requested locale
		$profile = $this->get_profile( $locale );
		if ( $profile ) {
			$profile['_source'] = 'locale:' . $locale;
			return $profile;
		}

		// Try site default locale
		$site_locale = get_locale();
		if ( $locale !== $site_locale ) {
			$profile = $this->get_profile( $site_locale );
			if ( $profile ) {
				$profile['_source'] = 'locale:' . $site_locale . ' (site default)';
				return $profile;
			}
		}

		// Try 'default' locale
		if ( 'default' !== $locale ) {
			$profile = $this->get_profile( 'default' );
			if ( $profile ) {
				$profile['_source'] = 'locale:default (fallback)';
				return $profile;
			}
		}

		// Try en_US as last resort
		if ( 'en_US' !== $locale ) {
			$profile = $this->get_profile( 'en_US' );
			if ( $profile ) {
				$profile['_source'] = 'locale:en_US (fallback)';
				return $profile;
			}
		}

		// Return hardcoded defaults
		return array(
			'locale'               => $locale,
			'tone'                 => 'professional',
			'cta_style'            => 'subtle',
			'formality_level'      => 3,
			'special_instructions' => '',
			'forbidden_terms'      => '',
			'confidence_threshold' => 0.70,
			'_source'              => 'hardcoded defaults',
		);
	}

	/**
	 * Get all locale profiles.
	 *
	 * @return array
	 */
	public function get_all_profiles() {
		global $wpdb;

		$table = $this->db->get_profiles_table();

		$profiles = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY locale ASC",
			ARRAY_A
		);

		return $profiles ? $profiles : array();
	}

	/**
	 * Delete a locale profile.
	 *
	 * @param string $locale Locale code.
	 * @return bool
	 */
	public function delete_profile( $locale ) {
		global $wpdb;

		$table = $this->db->get_profiles_table();

		$result = $wpdb->delete(
			$table,
			array( 'locale' => $locale ),
			array( '%s' )
		);

		return false !== $result;
	}

	/**
	 * Add a glossary entry.
	 *
	 * @param string $locale Locale code.
	 * @param array  $glossary_data Glossary entry data.
	 * @return int|false Entry ID on success, false on failure.
	 */
	public function add_glossary_entry( $locale, $glossary_data ) {
		global $wpdb;

		$table = $this->db->get_glossary_table();

		// Required fields
		if ( empty( $locale ) || empty( $glossary_data['term'] ) ) {
			return false;
		}

		// Default values
		$defaults = array(
			'translation'    => null,
			'category'       => 'general',
			'case_sensitive' => 0,
			'protected'      => 0,
			'context'        => null,
		);

		$data = wp_parse_args( $glossary_data, $defaults );

		$result = $wpdb->insert(
			$table,
			array(
				'locale'         => $locale,
				'term'           => $data['term'],
				'translation'    => $data['translation'],
				'category'       => $data['category'],
				'case_sensitive' => (int) $data['case_sensitive'],
				'protected'      => (int) $data['protected'],
				'context'        => $data['context'],
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get glossary entries for a locale.
	 *
	 * @param string $locale Locale code.
	 * @param array  $filters Optional filters (category, protected).
	 * @return array
	 */
	public function get_glossary_entries( $locale, $filters = array() ) {
		global $wpdb;

		$table = $this->db->get_glossary_table();

		$where = array( $wpdb->prepare( 'locale = %s', $locale ) );

		if ( isset( $filters['category'] ) ) {
			$where[] = $wpdb->prepare( 'category = %s', $filters['category'] );
		}

		if ( isset( $filters['protected'] ) ) {
			$where[] = $wpdb->prepare( 'protected = %d', (int) $filters['protected'] );
		}

		$where_clause = implode( ' AND ', $where );

		$entries = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY term ASC",
			ARRAY_A
		);

		return $entries ? $entries : array();
	}

	/**
	 * Update a glossary entry.
	 *
	 * @param int   $entry_id Entry ID.
	 * @param array $data Data to update.
	 * @return bool
	 */
	public function update_glossary_entry( $entry_id, $data ) {
		global $wpdb;

		$table = $this->db->get_glossary_table();

		// Allowed fields
		$allowed_fields = array( 'term', 'translation', 'category', 'case_sensitive', 'protected', 'context' );
		$update_data    = array();
		$format         = array();

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $allowed_fields, true ) ) {
				$update_data[ $key ] = $value;

				// Determine format
				if ( in_array( $key, array( 'case_sensitive', 'protected' ), true ) ) {
					$format[] = '%d';
				} else {
					$format[] = '%s';
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $entry_id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a glossary entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public function delete_glossary_entry( $entry_id ) {
		global $wpdb;

		$table = $this->db->get_glossary_table();

		$result = $wpdb->delete(
			$table,
			array( 'id' => $entry_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get protected terms for a locale.
	 *
	 * Returns terms that should NEVER be translated.
	 *
	 * @param string $locale Locale code.
	 * @return array Array of protected terms.
	 */
	public function get_protected_terms( $locale ) {
		$entries = $this->get_glossary_entries( $locale, array( 'protected' => 1 ) );

		return array_column( $entries, 'term' );
	}

	/**
	 * Get glossary term replacement.
	 *
	 * @param string $locale Locale code.
	 * @param string $term Term to look up.
	 * @param bool   $case_sensitive Case-sensitive match.
	 * @return string|null Translation or null if not found.
	 */
	public function get_term_translation( $locale, $term, $case_sensitive = false ) {
		global $wpdb;

		$table = $this->db->get_glossary_table();

		if ( $case_sensitive ) {
			$entry = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT translation FROM {$table}
					WHERE locale = %s AND term = %s AND case_sensitive = 1
					LIMIT 1",
					$locale,
					$term
				),
				ARRAY_A
			);
		} else {
			$entry = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT translation FROM {$table}
					WHERE locale = %s AND LOWER(term) = LOWER(%s)
					LIMIT 1",
					$locale,
					$term
				),
				ARRAY_A
			);
		}

		return $entry ? $entry['translation'] : null;
	}

	/**
	 * Bulk import glossary entries.
	 *
	 * @param string $locale Locale code.
	 * @param array  $entries Array of entry arrays.
	 * @return array Results with success/error counts.
	 */
	public function bulk_import_glossary( $locale, $entries ) {
		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $entries as $index => $entry ) {
			$entry_id = $this->add_glossary_entry( $locale, $entry );

			if ( $entry_id ) {
				++$results['success'];
			} else {
				++$results['failed'];
				$results['errors'][] = 'Entry ' . $index . ': ' . ( $entry['term'] ?? 'unknown' );
			}
		}

		return $results;
	}
}
