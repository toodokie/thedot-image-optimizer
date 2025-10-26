<?php
/**
 * Automation Triggers - Event Listener System
 *
 * Listens for WordPress events and automatically enqueues jobs for metadata
 * generation and regeneration based on:
 * - New attachment uploads
 * - Attachment updates (title, alt text, file replacement)
 * - Locale changes (via settings or context profile updates)
 * - Glossary term additions/updates
 * - Manual regeneration requests
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Automation
 * @since      2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Automation_Triggers
 *
 * Hooks into WordPress events and triggers automated job enqueueing.
 */
class MSH_Automation_Triggers {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Automation_Triggers|null
	 */
	private static $instance = null;

	/**
	 * Job engine reference.
	 *
	 * @var MSH_Job_Engine
	 */
	private $job_engine;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Automation_Triggers
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor - registers WordPress hooks.
	 */
	private function __construct() {
		$this->job_engine = MSH_Job_Engine::get_instance();

		// Attachment events
		add_action( 'add_attachment', array( $this, 'on_attachment_added' ), 10, 1 );
		add_action( 'edit_attachment', array( $this, 'on_attachment_updated' ), 10, 1 );
		add_action( 'delete_attachment', array( $this, 'on_attachment_deleted' ), 10, 1 );

		// Locale/context profile changes
		add_action( 'update_option_msh_onboarding_context', array( $this, 'on_context_updated' ), 10, 2 );
		add_action( 'update_option_msh_onboarding_context_profiles', array( $this, 'on_profiles_updated' ), 10, 2 );

		// Glossary term changes (hook into custom glossary save actions)
		add_action( 'msh_glossary_term_saved', array( $this, 'on_glossary_term_saved' ), 10, 1 );
		add_action( 'msh_glossary_term_deleted', array( $this, 'on_glossary_term_deleted' ), 10, 1 );

		// Manual regeneration (from Hub Cache tab "Regenerate" button)
		add_action( 'wp_ajax_msh_regenerate_metadata', array( $this, 'ajax_regenerate_metadata' ) );

		// Bulk regeneration (from Hub Queue tab "Process Now" button)
		add_action( 'wp_ajax_msh_trigger_bulk_regeneration', array( $this, 'ajax_trigger_bulk_regeneration' ) );
	}

	/**
	 * Handle new attachment upload.
	 *
	 * Generates initial metadata for all active locales.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public function on_attachment_added( $attachment_id ) {
		// Skip if not an image
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$active_locales = $this->get_active_locales();

		foreach ( $active_locales as $locale ) {
			// Enqueue metadata generation job
			$this->job_engine->enqueue(
				'generate_metadata',
				'attachment',
				$attachment_id,
				array(
					'locale' => $locale,
					'fields' => array( 'title', 'alt_text', 'caption', 'description' ),
					'reason' => 'new_upload',
				),
				'normal'
			);
		}

		do_action( 'msh_attachment_added_jobs_enqueued', $attachment_id, $active_locales );
	}

	/**
	 * Handle attachment update (metadata edit, file replacement).
	 *
	 * Marks existing cache entries as stale and enqueues regeneration.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public function on_attachment_updated( $attachment_id ) {
		// Skip if not an image
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		// Mark all cache entries for this attachment as stale
		$this->mark_cache_stale( $attachment_id, 'attachment_updated' );

		$active_locales = $this->get_active_locales();

		foreach ( $active_locales as $locale ) {
			// Enqueue regeneration with medium priority (user initiated)
			$this->job_engine->enqueue(
				'regenerate_metadata',
				'attachment',
				$attachment_id,
				array(
					'locale' => $locale,
					'fields' => array( 'title', 'alt_text', 'caption', 'description' ),
					'reason' => 'attachment_updated',
				),
				'medium'
			);
		}

		do_action( 'msh_attachment_updated_jobs_enqueued', $attachment_id, $active_locales );
	}

	/**
	 * Handle attachment deletion.
	 *
	 * Cleans up cache entries and job queue for deleted attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return void
	 */
	public function on_attachment_deleted( $attachment_id ) {
		global $wpdb;

		// Delete cache entries only if cache table exists (some installs skip this table)
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';
		$table_check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cache_table ) );
		if ( $table_check === $cache_table ) {
			$wpdb->delete(
				$cache_table,
				array( 'attachment_id' => $attachment_id ),
				array( '%d' )
			);
		}

		// Pending automation jobs fail gracefully when attachment records are gone; no explicit cancel required.

		do_action( 'msh_attachment_deleted_cleanup', $attachment_id );
	}

	/**
	 * Handle context settings update.
	 *
	 * When primary context changes, regenerate metadata for ALL attachments
	 * to reflect new brand voice/tone.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function on_context_updated( $old_value, $new_value ) {
		// Check if meaningful context fields changed
		if ( ! $this->context_has_meaningful_changes( $old_value, $new_value ) ) {
			return;
		}

		// Mark ALL cache entries as stale
		$this->mark_all_cache_stale( 'context_updated' );

		// Enqueue bulk regeneration for all image attachments
		$attachment_ids = $this->get_all_image_attachment_ids();
		$active_locales = $this->get_active_locales();

		foreach ( $attachment_ids as $attachment_id ) {
			foreach ( $active_locales as $locale ) {
				$this->job_engine->enqueue(
					'regenerate_metadata',
					'attachment',
					$attachment_id,
					array(
						'locale' => $locale,
						'fields' => array( 'title', 'alt_text', 'caption', 'description' ),
						'reason' => 'context_updated',
					),
					'normal'
				);
			}
		}

		do_action( 'msh_context_updated_jobs_enqueued', count( $attachment_ids ), $active_locales );
	}

	/**
	 * Handle context profiles update.
	 *
	 * When locale-specific profiles change, regenerate metadata for that locale.
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function on_profiles_updated( $old_value, $new_value ) {
		$changed_locales = $this->get_changed_profile_locales( $old_value, $new_value );

		if ( empty( $changed_locales ) ) {
			return;
		}

		// Mark cache entries stale for changed locales
		foreach ( $changed_locales as $locale ) {
			$this->mark_cache_stale_by_locale( $locale, 'profile_updated' );
		}

		// Enqueue regeneration jobs
		$attachment_ids = $this->get_all_image_attachment_ids();

		foreach ( $attachment_ids as $attachment_id ) {
			foreach ( $changed_locales as $locale ) {
				$this->job_engine->enqueue(
					'regenerate_metadata',
					'attachment',
					$attachment_id,
					array(
						'locale' => $locale,
						'fields' => array( 'title', 'alt_text', 'caption', 'description' ),
						'reason' => 'profile_updated',
					),
					'normal'
				);
			}
		}

		do_action( 'msh_profiles_updated_jobs_enqueued', count( $attachment_ids ), $changed_locales );
	}

	/**
	 * Handle glossary term save/update.
	 *
	 * When glossary terms change, regenerate metadata that might contain those terms.
	 *
	 * @param array $term_data Glossary term data (term, definition, context, etc.).
	 * @return void
	 */
	public function on_glossary_term_saved( $term_data ) {
		// Mark cache entries containing this term as stale
		$term = isset( $term_data['term'] ) ? $term_data['term'] : '';

		if ( empty( $term ) ) {
			return;
		}

		$this->mark_cache_stale_by_term( $term, 'glossary_term_updated' );

		// Find attachments whose metadata contains this term
		$affected_ids = $this->find_attachments_with_term( $term );

		if ( empty( $affected_ids ) ) {
			return;
		}

		$active_locales = $this->get_active_locales();

		foreach ( $affected_ids as $attachment_id ) {
			foreach ( $active_locales as $locale ) {
				$this->job_engine->enqueue(
					'regenerate_metadata',
					'attachment',
					$attachment_id,
					array(
						'locale' => $locale,
						'fields' => array( 'title', 'alt_text', 'caption', 'description' ),
						'reason' => 'glossary_term_updated',
					),
					'normal'
				);
			}
		}

		do_action( 'msh_glossary_term_saved_jobs_enqueued', $term, count( $affected_ids ) );
	}

	/**
	 * Handle glossary term deletion.
	 *
	 * Similar to term save - regenerate affected metadata.
	 *
	 * @param array $term_data Glossary term data.
	 * @return void
	 */
	public function on_glossary_term_deleted( $term_data ) {
		// Reuse same logic as term saved
		$this->on_glossary_term_saved( $term_data );
	}

	/**
	 * AJAX handler for single metadata regeneration.
	 *
	 * Called from Hub Cache tab "Regenerate" button.
	 *
	 * @return void
	 */
	public function ajax_regenerate_metadata() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'msh-image-optimizer' ) ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$locale        = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : 'en_US';
		$field         = isset( $_POST['field'] ) ? sanitize_text_field( wp_unslash( $_POST['field'] ) ) : 'title';

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment ID.', 'msh-image-optimizer' ) ) );
		}

		// Mark specific cache entry as stale
		$this->mark_cache_entry_stale( $attachment_id, $locale, $field, 'manual_regeneration' );

		// Enqueue high-priority job (user-initiated)
		$job_id = $this->job_engine->enqueue(
			'regenerate_metadata',
			'attachment',
			$attachment_id,
			array(
				'locale' => $locale,
				'fields' => array( $field ),
				'reason' => 'manual_regeneration',
			),
			'high'
		);

		if ( is_wp_error( $job_id ) ) {
			wp_send_json_error( array( 'message' => $job_id->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Regeneration job enqueued.', 'msh-image-optimizer' ),
			'job_id'  => $job_id,
		) );
	}

	/**
	 * AJAX handler for bulk regeneration trigger.
	 *
	 * Called from Hub Queue tab "Process Now" button.
	 *
	 * @return void
	 */
	public function ajax_trigger_bulk_regeneration() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'msh-image-optimizer' ) ) );
		}

		// Trigger immediate queue processing
		$queue_manager = MSH_Queue_Manager::get_instance();
		$stats         = $queue_manager->get_queue_stats();

		// Schedule immediate cron run
		wp_schedule_single_event( time(), 'msh_process_job_queue' );

		wp_send_json_success( array(
			'message'       => __( 'Queue processing triggered.', 'msh-image-optimizer' ),
			'pending_jobs'  => $stats['pending'],
			'processing'    => $stats['processing'],
		) );
	}

	/**
	 * Get list of active locales.
	 *
	 * @return array Array of locale codes (e.g., ['en_US', 'es_ES']).
	 */
	private function get_active_locales() {
		// Start with site locale
		$locales = array( get_locale() );

		// Add locales from context profiles
		$profiles = get_option( 'msh_onboarding_context_profiles', array() );

		if ( is_array( $profiles ) ) {
			foreach ( $profiles as $profile ) {
				if ( isset( $profile['locale'] ) && ! in_array( $profile['locale'], $locales, true ) ) {
					$locales[] = $profile['locale'];
				}
			}
		}

		return apply_filters( 'msh_active_locales', $locales );
	}

	/**
	 * Get all image attachment IDs.
	 *
	 * @return array Array of attachment post IDs.
	 */
	private function get_all_image_attachment_ids() {
		$query = new WP_Query( array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		return $query->posts;
	}

	/**
	 * Mark all cache entries for an attachment as stale.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $reason        Staleness reason.
	 * @return void
	 */
	private function mark_cache_stale( $attachment_id, $reason ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';

		$wpdb->update(
			$cache_table,
			array(
				'stale_reason' => $reason,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'attachment_id' => $attachment_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark specific cache entry as stale.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $locale        Locale code.
	 * @param string $field         Field name.
	 * @param string $reason        Staleness reason.
	 * @return void
	 */
	private function mark_cache_entry_stale( $attachment_id, $locale, $field, $reason ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';

		$wpdb->update(
			$cache_table,
			array(
				'stale_reason' => $reason,
				'updated_at'   => current_time( 'mysql' ),
			),
			array(
				'attachment_id' => $attachment_id,
				'locale'        => $locale,
				'field'         => $field,
			),
			array( '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Mark all cache entries as stale.
	 *
	 * @param string $reason Staleness reason.
	 * @return void
	 */
	private function mark_all_cache_stale( $reason ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$cache_table} SET stale_reason = %s, updated_at = %s",
				$reason,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Mark cache entries for a specific locale as stale.
	 *
	 * @param string $locale Locale code.
	 * @param string $reason Staleness reason.
	 * @return void
	 */
	private function mark_cache_stale_by_locale( $locale, $reason ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';

		$wpdb->update(
			$cache_table,
			array(
				'stale_reason' => $reason,
				'updated_at'   => current_time( 'mysql' ),
			),
			array( 'locale' => $locale ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Mark cache entries containing a specific term as stale.
	 *
	 * @param string $term   Glossary term.
	 * @param string $reason Staleness reason.
	 * @return void
	 */
	private function mark_cache_stale_by_term( $term, $reason ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$cache_table}
				SET stale_reason = %s, updated_at = %s
				WHERE ai_value LIKE %s OR manual_value LIKE %s",
				$reason,
				current_time( 'mysql' ),
				'%' . $wpdb->esc_like( $term ) . '%',
				'%' . $wpdb->esc_like( $term ) . '%'
			)
		);
	}

	/**
	 * Find attachments whose metadata contains a specific term.
	 *
	 * @param string $term Glossary term.
	 * @return array Array of attachment IDs.
	 */
	private function find_attachments_with_term( $term ) {
		global $wpdb;
		$cache_table = $wpdb->prefix . 'msh_metadata_cache';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT attachment_id FROM {$cache_table}
				WHERE ai_value LIKE %s OR manual_value LIKE %s",
				'%' . $wpdb->esc_like( $term ) . '%',
				'%' . $wpdb->esc_like( $term ) . '%'
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Check if context update has meaningful changes.
	 *
	 * @param mixed $old_value Old context value.
	 * @param mixed $new_value New context value.
	 * @return bool True if meaningful changes detected.
	 */
	private function context_has_meaningful_changes( $old_value, $new_value ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return true;
		}

		// Check key fields that affect AI generation
		$key_fields = array( 'brand_voice', 'tone', 'target_audience', 'industry', 'brand_name' );

		foreach ( $key_fields as $field ) {
			$old = isset( $old_value[ $field ] ) ? $old_value[ $field ] : '';
			$new = isset( $new_value[ $field ] ) ? $new_value[ $field ] : '';

			if ( $old !== $new ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get locales that changed between old and new profiles.
	 *
	 * @param mixed $old_value Old profiles value.
	 * @param mixed $new_value New profiles value.
	 * @return array Array of changed locale codes.
	 */
	private function get_changed_profile_locales( $old_value, $new_value ) {
		$changed = array();

		if ( ! is_array( $old_value ) ) {
			$old_value = array();
		}
		if ( ! is_array( $new_value ) ) {
			$new_value = array();
		}

		// Check for modified profiles
		foreach ( $new_value as $profile_id => $new_profile ) {
			$locale = isset( $new_profile['locale'] ) ? $new_profile['locale'] : '';

			if ( empty( $locale ) ) {
				continue;
			}

			// New profile added
			if ( ! isset( $old_value[ $profile_id ] ) ) {
				$changed[] = $locale;
				continue;
			}

			// Profile modified
			$old_profile = $old_value[ $profile_id ];
			if ( serialize( $old_profile ) !== serialize( $new_profile ) ) {
				$changed[] = $locale;
			}
		}

		// Check for deleted profiles
		foreach ( $old_value as $profile_id => $old_profile ) {
			if ( ! isset( $new_value[ $profile_id ] ) ) {
				$locale = isset( $old_profile['locale'] ) ? $old_profile['locale'] : '';
				if ( ! empty( $locale ) && ! in_array( $locale, $changed, true ) ) {
					$changed[] = $locale;
				}
			}
		}

		return array_unique( $changed );
	}
}

// Initialize
MSH_Automation_Triggers::get_instance();
