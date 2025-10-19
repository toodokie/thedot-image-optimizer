<?php
/**
 * Context Fusion Layer - Context Manager
 *
 * Handles CRUD operations for image context data.
 * Implements corrected patterns from Phase 2 design fixes.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Manager
 *
 * Primary interface for storing, retrieving, and updating context data.
 * Enforces the corrected unique key constraint and cache invalidation patterns.
 */
class MSH_Context_Manager {

	/**
	 * Singleton instance
	 *
	 * @var MSH_Context_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return MSH_Context_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor (singleton pattern)
	 */
	private function __construct() {
		// Hook into WordPress lifecycle events
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'delete_attachment', array( $this, 'on_delete_attachment' ) );
	}

	/**
	 * Store or update context for a media item
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE pattern to handle both new and existing contexts.
	 * Implements corrected unique key: (media_id, post_id, locale, usage_type, block_path)
	 *
	 * @param array $context Context data array with required fields.
	 * @return int|false Context ID on success, false on failure
	 */
	public function store_context( $context ) {
		global $wpdb;

		// Validate required fields
		$required = array( 'media_id', 'post_id', 'locale', 'usage_type', 'source_hash' );
		foreach ( $required as $field ) {
			if ( empty( $context[ $field ] ) ) {
				return false;
			}
		}

		$table_name = MSH_Context_Database::get_table_name();

		// Prepare context data
		$data = array(
			'media_id'           => (int) $context['media_id'],
			'post_id'            => (int) $context['post_id'],
			'locale'             => sanitize_text_field( $context['locale'] ),
			'usage_type'         => sanitize_text_field( $context['usage_type'] ),
			'block_path'         => isset( $context['block_path'] ) ? sanitize_text_field( $context['block_path'] ) : null,
			'subject'            => isset( $context['subject'] ) ? sanitize_text_field( $context['subject'] ) : null,
			'intent'             => isset( $context['intent'] ) ? sanitize_text_field( $context['intent'] ) : 'unknown',
			'intent_confidence'  => isset( $context['intent_confidence'] ) ? (int) $context['intent_confidence'] : 0,
			'entities'           => isset( $context['entities'] ) ? wp_json_encode( $context['entities'] ) : null,
			'keywords'           => isset( $context['keywords'] ) ? wp_json_encode( $context['keywords'] ) : null,
			'rules_fired'        => isset( $context['rules_fired'] ) ? wp_json_encode( $context['rules_fired'] ) : null,
			'source_hash'        => sanitize_text_field( $context['source_hash'] ),
			'context_score'      => isset( $context['context_score'] ) ? (int) $context['context_score'] : 0,
			'usage_count'        => 1,
		);

		$format = array(
			'%d', // media_id
			'%d', // post_id
			'%s', // locale
			'%s', // usage_type
			'%s', // block_path
			'%s', // subject
			'%s', // intent
			'%d', // intent_confidence
			'%s', // entities
			'%s', // keywords
			'%s', // rules_fired
			'%s', // source_hash
			'%d', // context_score
			'%d', // usage_count
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			// Check if this is a duplicate key error (means context already exists)
			if ( strpos( $wpdb->last_error, 'Duplicate entry' ) !== false ) {
				// Update existing context
				return $this->update_context( $context );
			}
			return false;
		}

		// Clear cache for this media
		$this->clear_cache( $data['media_id'], $data['locale'] );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update existing context
	 *
	 * Updates context if source_hash changed, otherwise just increments usage_count.
	 *
	 * @param array $context Context data array with required fields.
	 * @return int|false Number of rows updated on success, false on failure
	 */
	public function update_context( $context ) {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// Prepare WHERE clause (matches unique key)
		$where = array(
			'media_id'   => (int) $context['media_id'],
			'post_id'    => (int) $context['post_id'],
			'locale'     => sanitize_text_field( $context['locale'] ),
			'usage_type' => sanitize_text_field( $context['usage_type'] ),
		);

		// Add block_path to WHERE if present
		if ( isset( $context['block_path'] ) ) {
			$where['block_path'] = sanitize_text_field( $context['block_path'] );
		}

		// Get existing context to check source_hash
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT source_hash, usage_count FROM {$table_name}
				WHERE media_id = %d AND post_id = %d AND locale = %s AND usage_type = %s AND block_path <=> %s",
				$where['media_id'],
				$where['post_id'],
				$where['locale'],
				$where['usage_type'],
				$where['block_path'] ?? null
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			// Doesn't exist, insert instead
			return $this->store_context( $context );
		}

		// Check if source content changed
		if ( $existing['source_hash'] === $context['source_hash'] ) {
			// Content unchanged, just increment usage_count
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET usage_count = usage_count + 1, last_seen = NOW()
					WHERE media_id = %d AND post_id = %d AND locale = %s AND usage_type = %s AND block_path <=> %s",
					$where['media_id'],
					$where['post_id'],
					$where['locale'],
					$where['usage_type'],
					$where['block_path'] ?? null
				)
			);

			return $result;
		}

		// Content changed, full update
		$update_data = array(
			'subject'           => isset( $context['subject'] ) ? sanitize_text_field( $context['subject'] ) : null,
			'intent'            => isset( $context['intent'] ) ? sanitize_text_field( $context['intent'] ) : 'unknown',
			'intent_confidence' => isset( $context['intent_confidence'] ) ? (int) $context['intent_confidence'] : 0,
			'entities'          => isset( $context['entities'] ) ? wp_json_encode( $context['entities'] ) : null,
			'keywords'          => isset( $context['keywords'] ) ? wp_json_encode( $context['keywords'] ) : null,
			'rules_fired'       => isset( $context['rules_fired'] ) ? wp_json_encode( $context['rules_fired'] ) : null,
			'source_hash'       => sanitize_text_field( $context['source_hash'] ),
			'context_score'     => isset( $context['context_score'] ) ? (int) $context['context_score'] : 0,
			'usage_count'       => (int) $existing['usage_count'] + 1,
		);

		$update_format = array(
			'%s', // subject
			'%s', // intent
			'%d', // intent_confidence
			'%s', // entities
			'%s', // keywords
			'%s', // rules_fired
			'%s', // source_hash
			'%d', // context_score
			'%d', // usage_count
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			$update_data,
			$where,
			$update_format,
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		// Clear cache
		$this->clear_cache( $where['media_id'], $where['locale'] );

		return $result;
	}

	/**
	 * Get context rollup for a media item
	 *
	 * Aggregates all context entries for a media item across all posts/usages.
	 * Returns merged keywords, entities, and weighted intent.
	 *
	 * @param int    $media_id Media ID.
	 * @param string $locale   Locale code.
	 * @return array|null Rollup data or null if no context found
	 */
	public function get_media_rollup( $media_id, $locale ) {
		global $wpdb;

		// Check cache first
		$cache_key = "msh_ctx_rollup:{$media_id}:{$locale}";
		$cached    = wp_cache_get( $cache_key, 'msh' );

		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = MSH_Context_Database::get_table_name();

		// Get all context entries for this media in this locale
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$contexts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE media_id = %d AND locale = %s ORDER BY context_score DESC",
				$media_id,
				$locale
			),
			ARRAY_A
		);

		if ( empty( $contexts ) ) {
			wp_cache_set( $cache_key, null, 'msh', HOUR_IN_SECONDS );
			return null;
		}

		// Aggregate keywords
		$all_keywords = array();
		foreach ( $contexts as $context ) {
			if ( ! empty( $context['keywords'] ) ) {
				$keywords     = json_decode( $context['keywords'], true );
				$all_keywords = array_merge( $all_keywords, (array) $keywords );
			}
		}
		$all_keywords = array_unique( $all_keywords );

		// Aggregate entities
		$all_entities = array(
			'brands' => array(),
			'places' => array(),
			'people' => array(),
		);
		foreach ( $contexts as $context ) {
			if ( ! empty( $context['entities'] ) ) {
				$entities = json_decode( $context['entities'], true );
				if ( is_array( $entities ) ) {
					foreach ( array( 'brands', 'places', 'people' ) as $type ) {
						if ( isset( $entities[ $type ] ) && is_array( $entities[ $type ] ) ) {
							$all_entities[ $type ] = array_merge( $all_entities[ $type ], $entities[ $type ] );
						}
					}
				}
			}
		}
		foreach ( $all_entities as $type => &$values ) {
			$values = array_unique( $values );
		}

		// Calculate weighted intent
		$intent_votes = array(
			'on_topic'  => 0,
			'off_topic' => 0,
			'unknown'   => 0,
		);
		foreach ( $contexts as $context ) {
			$intent     = $context['intent'];
			$confidence = (int) $context['intent_confidence'];
			$weight     = max( 1, $confidence / 100 );

			if ( isset( $intent_votes[ $intent ] ) ) {
				$intent_votes[ $intent ] += $weight;
			}
		}

		// Determine final intent (highest vote wins)
		arsort( $intent_votes );
		$final_intent            = array_key_first( $intent_votes );
		$final_intent_confidence = (int) ( ( $intent_votes[ $final_intent ] / array_sum( $intent_votes ) ) * 100 );

		// Build rollup
		$rollup = array(
			'media_id'           => $media_id,
			'locale'             => $locale,
			'keywords'           => $all_keywords,
			'entities'           => $all_entities,
			'intent'             => $final_intent,
			'intent_confidence'  => $final_intent_confidence,
			'context_count'      => count( $contexts ),
			'total_usage_count'  => array_sum( wp_list_pluck( $contexts, 'usage_count' ) ),
			'top_subject'        => $contexts[0]['subject'] ?? null,
			'avg_context_score'  => (int) ( array_sum( wp_list_pluck( $contexts, 'context_score' ) ) / count( $contexts ) ),
		);

		// Cache for 1 hour
		wp_cache_set( $cache_key, $rollup, 'msh', HOUR_IN_SECONDS );

		return $rollup;
	}

	/**
	 * Get all posts using a specific media item
	 *
	 * Implements the corrected reverse lookup function from Phase 2 design fixes.
	 *
	 * @param int         $media_id Media ID to search for.
	 * @param string|null $locale   Optional locale filter.
	 * @return array Array of post IDs
	 */
	public function find_posts_using_media( $media_id, $locale = null ) {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		if ( $locale ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$table_name} WHERE media_id = %d AND locale = %s",
					$media_id,
					$locale
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$table_name} WHERE media_id = %d",
					$media_id
				)
			);
		}

		return array_map( 'intval', $posts );
	}

	/**
	 * Get all media items used in a specific post
	 *
	 * @param int    $post_id Post ID to analyze.
	 * @param string $locale  Locale code.
	 * @return array Array of media IDs with their usage details
	 */
	public function list_media_in_post( $post_id, $locale = 'en_US' ) {
		global $wpdb;

		$table_name = MSH_Context_Database::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT media_id, usage_type, block_path, context_score, usage_count
				FROM {$table_name}
				WHERE post_id = %d AND locale = %s
				ORDER BY context_score DESC",
				$post_id,
				$locale
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Hook: Handle post save
	 *
	 * Implements corrected locale detection from Phase 2 design fixes:
	 * - Only updates affected locale(s)
	 * - Defers to background processing
	 * - Doesn't block post save
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_save_post( $post_id, $post ) {
		// Bail on autosave/revision
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only process published posts/pages
		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}

		// Determine affected locale(s)
		$affected_locales = $this->get_affected_locales( $post_id );

		// Queue background update (don't block save)
		foreach ( $affected_locales as $locale ) {
			wp_schedule_single_event(
				time() + 60, // Delay 1 minute
				'msh_ctx_update_post_context',
				array( $post_id, $locale )
			);
		}
	}

	/**
	 * Hook: Handle post deletion
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public function on_delete_post( $post_id ) {
		MSH_Context_Database::delete_post_context( $post_id );
	}

	/**
	 * Hook: Handle attachment deletion
	 *
	 * @param int $media_id Media ID being deleted.
	 */
	public function on_delete_attachment( $media_id ) {
		MSH_Context_Database::delete_media_context( $media_id );
	}

	/**
	 * Determine which locales are affected by a post update
	 *
	 * Implements corrected locale detection from Phase 2 design fixes:
	 * - If Polylang/WPML: get edited post's locale only
	 * - Otherwise: use site locale
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of locale codes
	 */
	private function get_affected_locales( $post_id ) {
		// If Polylang, get the edited post's locale
		if ( function_exists( 'pll_get_post_language' ) ) {
			$locale = pll_get_post_language( $post_id, 'locale' );
			return $locale ? array( $locale ) : array( get_locale() );
		}

		// If WPML, get the edited post's locale
		if ( function_exists( 'wpml_get_language_information' ) ) {
			$lang_info = wpml_get_language_information( null, $post_id );
			if ( isset( $lang_info['locale'] ) ) {
				return array( $lang_info['locale'] );
			}
		}

		// No multilingual plugin, just use site locale
		return array( get_locale() );
	}

	/**
	 * Clear context cache for a media item
	 *
	 * Implements corrected cache invalidation from Phase 2 design fixes:
	 * No wildcards - must explicitly delete each locale's cache.
	 *
	 * @param int         $media_id Media ID.
	 * @param string|null $locale   Specific locale or null for all.
	 */
	private function clear_cache( $media_id, $locale = null ) {
		if ( $locale ) {
			// Clear specific locale
			wp_cache_delete( "msh_ctx_rollup:{$media_id}:{$locale}", 'msh' );
		} else {
			// Get all locales for this media
			global $wpdb;
			$table_name = MSH_Context_Database::get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$locales = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT locale FROM {$table_name} WHERE media_id = %d",
					$media_id
				)
			);

			// Clear cache for each locale
			foreach ( $locales as $loc ) {
				wp_cache_delete( "msh_ctx_rollup:{$media_id}:{$loc}", 'msh' );
			}

			// Also clear the locale list cache
			wp_cache_delete( "msh_ctx_locales:{$media_id}", 'msh' );
		}
	}
}
