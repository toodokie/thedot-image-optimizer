<?php
/**
 * Locale Profile Database Management
 *
 * Manages database tables for locale profiles and glossary entries.
 *
 * @package MSH_Image_Optimizer
 * @subpackage AI_Translation
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_Locale_Database class.
 */
class MSH_Locale_Database {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Locale_Database
	 */
	private static $instance = null;

	/**
	 * Locale profiles table name (without prefix).
	 *
	 * @var string
	 */
	const PROFILES_TABLE = 'msh_locale_profiles';

	/**
	 * Locale glossary table name (without prefix).
	 *
	 * @var string
	 */
	const GLOSSARY_TABLE = 'msh_locale_glossary';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Locale_Database
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
		// Hook into activation or init to create tables
		add_action( 'admin_init', array( $this, 'maybe_create_tables' ) );
	}

	/**
	 * Create tables if they don't exist.
	 */
	public function maybe_create_tables() {
		global $wpdb;

		$profiles_table = $wpdb->prefix . self::PROFILES_TABLE;
		$glossary_table = $wpdb->prefix . self::GLOSSARY_TABLE;

		// Check if tables exist
		$profiles_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$profiles_table}'" ) === $profiles_table;
		$glossary_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$glossary_table}'" ) === $glossary_table;

		if ( ! $profiles_exists ) {
			$this->create_profiles_table();
		}

		if ( ! $glossary_exists ) {
			$this->create_glossary_table();
		}
	}

	/**
	 * Create locale profiles table.
	 *
	 * Schema:
	 * - id: Primary key
	 * - locale: Locale code (e.g., en_US, es_ES, fr_FR)
	 * - tone: Tone preference (formal, friendly, professional, casual)
	 * - cta_style: Call-to-action style (direct, subtle, none)
	 * - formality_level: 1-5 scale (1=very casual, 5=very formal)
	 * - special_instructions: Long text for locale-specific guidance
	 * - forbidden_terms: JSON array of terms to avoid
	 * - confidence_threshold: Minimum confidence to accept AI output (0-1)
	 * - created_at: Timestamp
	 * - updated_at: Timestamp
	 *
	 * @return bool True on success, false on failure
	 */
	private function create_profiles_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::PROFILES_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			locale VARCHAR(20) NOT NULL,
			tone VARCHAR(50) NOT NULL DEFAULT 'professional',
			cta_style VARCHAR(50) NOT NULL DEFAULT 'subtle',
			formality_level TINYINT UNSIGNED NOT NULL DEFAULT 3,
			special_instructions TEXT NULL,
			forbidden_terms TEXT NULL,
			confidence_threshold DECIMAL(3,2) NOT NULL DEFAULT 0.70,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_locale (locale),
			KEY idx_tone (tone),
			KEY idx_formality (formality_level)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Create locale glossary table.
	 *
	 * Schema:
	 * - id: Primary key
	 * - locale: Locale code
	 * - term: Original term (in default locale)
	 * - translation: Translated/preferred term
	 * - category: brand, city, product, sku, technical
	 * - case_sensitive: Whether to match case exactly
	 * - protected: If true, NEVER translate this term
	 * - context: Optional context where this applies
	 * - created_at: Timestamp
	 * - updated_at: Timestamp
	 *
	 * @return bool True on success, false on failure
	 */
	private function create_glossary_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::GLOSSARY_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			locale VARCHAR(20) NOT NULL,
			term VARCHAR(255) NOT NULL,
			translation VARCHAR(255) NULL,
			category VARCHAR(50) NOT NULL DEFAULT 'general',
			case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
			protected TINYINT(1) NOT NULL DEFAULT 0,
			context VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_locale_term (locale, term, category),
			KEY idx_locale (locale),
			KEY idx_category (category),
			KEY idx_protected (protected),
			KEY idx_term (term)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		return true;
	}

	/**
	 * Get profiles table name with prefix.
	 *
	 * @return string
	 */
	public function get_profiles_table() {
		global $wpdb;
		return $wpdb->prefix . self::PROFILES_TABLE;
	}

	/**
	 * Get glossary table name with prefix.
	 *
	 * @return string
	 */
	public function get_glossary_table() {
		global $wpdb;
		return $wpdb->prefix . self::GLOSSARY_TABLE;
	}

	/**
	 * Drop all tables (for uninstall).
	 *
	 * @return bool
	 */
	public function drop_tables() {
		global $wpdb;

		$profiles_table = $this->get_profiles_table();
		$glossary_table = $this->get_glossary_table();

		$wpdb->query( "DROP TABLE IF EXISTS {$profiles_table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$glossary_table}" );

		return true;
	}

	/**
	 * Get table statistics.
	 *
	 * @return array
	 */
	public function get_table_stats() {
		global $wpdb;

		$profiles_table = $this->get_profiles_table();
		$glossary_table = $this->get_glossary_table();

		$stats = array(
			'profiles' => array(
				'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$profiles_table}" ),
				'size'  => $this->get_table_size( $profiles_table ),
			),
			'glossary' => array(
				'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$glossary_table}" ),
				'size'  => $this->get_table_size( $glossary_table ),
			),
		);

		return $stats;
	}

	/**
	 * Get table size in bytes.
	 *
	 * @param string $table_name Table name with prefix.
	 * @return int
	 */
	private function get_table_size( $table_name ) {
		global $wpdb;

		$size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT (data_length + index_length)
				FROM information_schema.TABLES
				WHERE table_schema = %s
				AND table_name = %s",
				DB_NAME,
				$table_name
			)
		);

		return (int) $size;
	}
}
