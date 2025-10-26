<?php
/**
 * I18n Metadata Manager
 *
 * Manages multilingual image metadata storage and retrieval.
 * Provides locale-specific alt text, titles, descriptions, and captions.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * I18n Metadata Manager Class
 *
 * Handles storage and retrieval of locale-specific image metadata
 * with fallback chain support.
 */
class MSH_I18n_Metadata {

	/**
	 * Singleton instance
	 *
	 * @var MSH_I18n_Metadata
	 */
	private static $instance = null;

	/**
	 * Request-level cache for metadata queries
	 *
	 * @var array
	 */
	private $metadata_cache = array();

	/**
	 * Get singleton instance
	 *
	 * @return MSH_I18n_Metadata
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
		// Initialize database on activation
		add_action( 'init', array( $this, 'maybe_init_database' ) );
	}

	/**
	 * Maybe initialize database
	 */
	public function maybe_init_database() {
		MSH_I18n_Database::init();
	}

	/**
	 * Get metadata for specific locale
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Locale code (e.g., 'en_US').
	 * @return array|null Metadata array or null if not found.
	 */
	public function get_metadata( $media_id, $locale = null ) {
		global $wpdb;

		if ( null === $locale ) {
			$locale = get_locale();
		}

		$table = MSH_I18n_Database::get_table_name();

		$metadata = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE media_id = %d AND locale = %s",
				$media_id,
				$locale
			),
			ARRAY_A
		);

		return $metadata;
	}

	/**
	 * Get metadata with fallback chain
	 *
	 * Tries: requested locale → site default locale → 'default' → WordPress meta
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Requested locale.
	 * @return array Metadata array with source indicator.
	 */
	public function get_with_fallback( $media_id, $locale = null ) {
		if ( null === $locale ) {
			$locale = get_locale();
		}

		// Check request-level cache first
		$cache_key = $media_id . '_' . $locale;
		if ( isset( $this->metadata_cache[ $cache_key ] ) ) {
			return $this->metadata_cache[ $cache_key ];
		}

		$site_locale = get_locale();

		// Try requested locale
		$metadata = $this->get_metadata( $media_id, $locale );
		if ( $metadata ) {
			$metadata['_source'] = 'locale:' . $locale;
			$this->metadata_cache[ $cache_key ] = $metadata;
			return $metadata;
		}

		// Try site default locale (if different)
		if ( $locale !== $site_locale ) {
			$metadata = $this->get_metadata( $media_id, $site_locale );
			if ( $metadata ) {
				$metadata['_source'] = 'locale:' . $site_locale . ' (site default)';
				$this->metadata_cache[ $cache_key ] = $metadata;
				return $metadata;
			}
		}

		// Try 'default' locale
		$metadata = $this->get_metadata( $media_id, 'default' );
		if ( $metadata ) {
			$metadata['_source'] = 'locale:default';
			$this->metadata_cache[ $cache_key ] = $metadata;
			return $metadata;
		}

		// Fallback to WordPress metadata
		$post = get_post( $media_id );
		if ( $post ) {
			$metadata = array(
				'media_id'     => $media_id,
				'locale'       => 'wordpress',
				'alt_text'     => get_post_meta( $media_id, '_wp_attachment_image_alt', true ),
				'title'        => $post->post_title,
				'description'  => $post->post_content,
				'caption'      => $post->post_excerpt,
				'generated_at' => null,
				'approved'     => 0,
				'_source'      => 'wordpress',
			);
			$this->metadata_cache[ $cache_key ] = $metadata;
			return $metadata;
		}

		// Cache null result to prevent repeated queries
		$this->metadata_cache[ $cache_key ] = null;
		return null;
	}

	/**
	 * Set metadata for specific locale
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Locale code.
	 * @param array  $metadata Metadata to store.
	 * @return bool True on success, false on failure.
	 */
	public function set_metadata( $media_id, $locale, $metadata ) {
		global $wpdb;

		$table = MSH_I18n_Database::get_table_name();

		$data = array(
			'media_id'     => $media_id,
			'locale'       => $locale,
			'alt_text'     => isset( $metadata['alt_text'] ) ? $metadata['alt_text'] : null,
			'title'        => isset( $metadata['title'] ) ? $metadata['title'] : null,
			'description'  => isset( $metadata['description'] ) ? $metadata['description'] : null,
			'caption'      => isset( $metadata['caption'] ) ? $metadata['caption'] : null,
			'generated_at' => isset( $metadata['generated_at'] ) ? $metadata['generated_at'] : current_time( 'mysql' ),
			'approved'     => isset( $metadata['approved'] ) ? (int) $metadata['approved'] : 0,
		);

		// Check if exists
		$existing = $this->get_metadata( $media_id, $locale );

		if ( $existing ) {
			// Update
			$result = $wpdb->update(
				$table,
				$data,
				array(
					'media_id' => $media_id,
					'locale'   => $locale,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
				array( '%d', '%s' )
			);

			return false !== $result;
		} else {
			// Insert
			$result = $wpdb->insert(
				$table,
				$data,
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
			);

			return false !== $result;
		}
	}

	/**
	 * Generate metadata for all active locales
	 *
	 * @param int   $media_id Media attachment ID.
	 * @param array $options  Generation options.
	 * @return array Results keyed by locale.
	 */
	public function generate_for_all_locales( $media_id, $options = array() ) {
		$locales = $this->get_active_locales();
		$results = array();

		foreach ( $locales as $locale ) {
			$result = $this->generate_for_locale( $media_id, $locale, $options );
			$results[ $locale ] = $result;
		}

		return $results;
	}

	/**
	 * Generate metadata for specific locale
	 *
	 * Uses AI integration to generate context-aware metadata.
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Locale code.
	 * @param array  $options  Generation options.
	 * @return array|WP_Error Result or error.
	 */
	public function generate_for_locale( $media_id, $locale, $options = array() ) {
		// Get context for this locale
		$manager = MSH_Context_Manager::get_instance();
		$rollup = $manager->get_media_rollup( $media_id, $locale );

		if ( ! $rollup ) {
			return new WP_Error(
				'no_context',
				__( 'No context data available for this image.', 'msh-image-optimizer' )
			);
		}

		// Get AI integration
		$ai = new MSH_Context_AI_Integration();

		// Generate alt text using context
		$prompt = sprintf(
			'Generate SEO-optimized alt text in %s for an image used in the following contexts:

Intent: %s
Keywords: %s
Subjects: %s
Average Context Score: %d

The alt text should:
1. Be descriptive and accurate
2. Include relevant keywords naturally
3. Be concise (under 125 characters)
4. Match the intent and context of usage
5. Be appropriate for %s locale and culture

Return only the alt text, nothing else.',
			$locale,
			$rollup['intent'],
			implode( ', ', $rollup['top_keywords'] ),
			implode( ', ', $rollup['top_subjects'] ),
			$rollup['avg_context_score'],
			$locale
		);

		$alt_text = $ai->generate_text( $prompt, array( 'max_tokens' => 100 ) );

		if ( is_wp_error( $alt_text ) ) {
			return $alt_text;
		}

		// Clean up alt text
		$alt_text = trim( $alt_text );
		$alt_text = str_replace( array( '"', "'", "\n", "\r" ), '', $alt_text );

		// Save metadata
		$metadata = array(
			'alt_text'     => $alt_text,
			'generated_at' => current_time( 'mysql' ),
			'approved'     => 0,
		);

		$saved = $this->set_metadata( $media_id, $locale, $metadata );

		if ( $saved ) {
			return array(
				'success'  => true,
				'locale'   => $locale,
				'alt_text' => $alt_text,
			);
		} else {
			return new WP_Error(
				'save_failed',
				__( 'Failed to save generated metadata.', 'msh-image-optimizer' )
			);
		}
	}

	/**
	 * Get active locales
	 *
	 * Returns list of locales to generate metadata for.
	 *
	 * @return array List of locale codes.
	 */
	public function get_active_locales() {
		$locales = array( get_locale() );

		// Add WPML locales if available
		if ( function_exists( 'icl_get_languages' ) ) {
			$wpml_languages = icl_get_languages( 'skip_missing=0' );
			foreach ( $wpml_languages as $lang ) {
				$locales[] = $lang['default_locale'];
			}
		}

		// Add Polylang locales if available
		if ( function_exists( 'pll_languages_list' ) ) {
			$pll_locales = pll_languages_list( array( 'fields' => 'locale' ) );
			$locales = array_merge( $locales, $pll_locales );
		}

		// Remove duplicates
		$locales = array_unique( $locales );

		/**
		 * Filter active locales for metadata generation
		 *
		 * @param array $locales List of locale codes.
		 */
		return apply_filters( 'msh_i18n_active_locales', $locales );
	}

	/**
	 * Delete metadata for media
	 *
	 * @param int         $media_id Media attachment ID.
	 * @param string|null $locale   Specific locale to delete, or null for all.
	 * @return bool True on success.
	 */
	public function delete_metadata( $media_id, $locale = null ) {
		global $wpdb;

		$table = MSH_I18n_Database::get_table_name();

		if ( null === $locale ) {
			// Delete all locales for this media
			$result = $wpdb->delete(
				$table,
				array( 'media_id' => $media_id ),
				array( '%d' )
			);
		} else {
			// Delete specific locale
			$result = $wpdb->delete(
				$table,
				array(
					'media_id' => $media_id,
					'locale'   => $locale,
				),
				array( '%d', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Get all locales for a media item
	 *
	 * @param int $media_id Media attachment ID.
	 * @return array List of locale codes.
	 */
	public function get_available_locales( $media_id ) {
		global $wpdb;

		$table = MSH_I18n_Database::get_table_name();

		$locales = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT locale FROM {$table} WHERE media_id = %d",
				$media_id
			)
		);

		return $locales ? $locales : array();
	}

	/**
	 * Approve metadata for a locale
	 *
	 * @param int    $media_id Media attachment ID.
	 * @param string $locale   Locale code.
	 * @return bool True on success.
	 */
	public function approve_metadata( $media_id, $locale ) {
		global $wpdb;

		$table = MSH_I18n_Database::get_table_name();

		$result = $wpdb->update(
			$table,
			array( 'approved' => 1 ),
			array(
				'media_id' => $media_id,
				'locale'   => $locale,
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		return false !== $result;
	}
}
