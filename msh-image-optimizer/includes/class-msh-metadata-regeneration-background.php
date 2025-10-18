<?php
/**
 * Background processor for AI metadata regeneration.
 * Manages bulk regeneration jobs via WP-Cron with credit tracking and pause/resume.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Metadata_Regeneration_Background {
	const STATE_OPTION      = 'msh_metadata_regen_queue_state';
	const JOBS_OPTION       = 'msh_metadata_regen_jobs';
	const LOCK_TRANSIENT    = 'msh_metadata_regen_queue_lock';
	const CRON_HOOK         = 'msh_process_metadata_regen_queue';
	const MAX_ARCHIVED_JOBS = 10;

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Metadata_Regeneration_Background|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'bootstrap' ) );
		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );

		// AJAX endpoints
		add_action( 'wp_ajax_msh_start_ai_regeneration', array( $this, 'ajax_start_regeneration' ) );
		add_action( 'wp_ajax_msh_pause_ai_regeneration', array( $this, 'ajax_pause_regeneration' ) );
		add_action( 'wp_ajax_msh_resume_ai_regeneration', array( $this, 'ajax_resume_regeneration' ) );
		add_action( 'wp_ajax_msh_cancel_ai_regeneration', array( $this, 'ajax_cancel_regeneration' ) );
		add_action( 'wp_ajax_msh_get_ai_regeneration_status', array( $this, 'ajax_get_status' ) );
	}

	/**
	 * Ensure cron runner is scheduled when work is pending.
	 */
	public function bootstrap() {
		$state = $this->get_state();
		if ( $this->has_pending_work( $state ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$this->schedule_runner( 5 );
		}
	}

	/**
	 * Queue a metadata regeneration job.
	 *
	 * @param array $attachment_ids Attachment IDs to process.
	 * @param array $options Job options.
	 *   - 'mode' => 'fill-empty' or 'overwrite' (default: fill-empty)
	 *   - 'fields' => array of fields to regenerate (default: all)
	 *   - 'plan_tier' => user's plan tier
	 *   - 'initiator' => who started this (manual, cli, etc.)
	 *
	 * @return array|WP_Error Job state or error.
	 */
	public function queue_regeneration( $attachment_ids, $options = array() ) {
		$ids = array_values( array_filter( array_map( 'intval', $attachment_ids ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'empty_selection', __( 'No images selected for regeneration.', 'msh-image-optimizer' ) );
		}

		// Check if there's already a running job
		$current_state = $this->get_state();
		if ( ! empty( $current_state ) && in_array( $current_state['status'], array( 'queued', 'running' ), true ) ) {
			return new WP_Error( 'job_running', __( 'Another regeneration job is already running.', 'msh-image-optimizer' ) );
		}

		// Parse options
		$mode      = isset( $options['mode'] ) && $options['mode'] === 'overwrite' ? 'overwrite' : 'fill-empty';
		$fields    = isset( $options['fields'] ) && is_array( $options['fields'] )
			? $options['fields']
			: array( 'title', 'alt_text', 'caption', 'description', 'filename' );
		$plan_tier = $options['plan_tier'] ?? 'free';
		$initiator = $options['initiator'] ?? 'manual';

		// Estimate credits
		$ai_service = MSH_AI_Service::get_instance();
		$estimate   = $ai_service->estimate_bulk_job_cost( $ids, $fields );

		if ( is_wp_error( $estimate ) ) {
			return $estimate;
		}

		// Create job record
		$job_id = uniqid( 'regen_', true );
		$job    = array(
			'job_id'            => $job_id,
			'status'            => 'queued',
			'created_at'        => time(),
			'started_at'        => null,
			'completed_at'      => null,
			'total'             => count( $ids ),
			'processed'         => 0,
			'succeeded'         => 0,
			'failed'            => 0,
			'skipped'           => 0,
			'mode'              => $mode,
			'fields'            => $fields,
			'plan_tier'         => $plan_tier,
			'initiator'         => $initiator,
			'credits_total'     => $estimate['credits_available'],
			'credits_used'      => 0,
			'credits_estimated' => $estimate['estimated_cost'],
			'failures'          => array(),
			'messages'          => array(),
		);

		// Create queue state
		$state = array(
			'job_id'        => $job_id,
			'status'        => 'queued',
			'mode'          => $mode,
			'fields'        => $fields,
			'batch_size'    => 25, // Process 25 images per batch
			'total'         => count( $ids ),
			'processed'     => 0,
			'succeeded'     => 0,
			'failed'        => 0,
			'skipped'       => 0,
			'queue'         => $ids,
			'last_id'       => 0,
			'plan_tier'     => $plan_tier,
			'credits_used'  => 0,
			'credits_limit' => $estimate['credits_available'],
			'queued_at'     => time(),
			'started_at'    => null,
			'completed_at'  => null,
			'last_activity' => null,
			'failures'      => array(),
			'messages'      => array(),
		);

		$this->save_state( $state );
		$this->save_job( $job );
		$this->schedule_runner( 5 );

		return $job;
	}

	/**
	 * Process the queue (called by WP-Cron).
	 */
	public function process_queue() {
		// Acquire lock
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return; // Already running
		}

		set_transient( self::LOCK_TRANSIENT, true, 60 );

		try {
			$state = $this->get_state();

			if ( empty( $state ) || ! $this->has_pending_work( $state ) ) {
				delete_transient( self::LOCK_TRANSIENT );
				return;
			}

			// Mark as running
			if ( $state['status'] === 'queued' ) {
				$state['status']     = 'running';
				$state['started_at'] = time();
				$this->save_state( $state );
				$this->update_job_status( $state['job_id'], 'running', array( 'started_at' => time() ) );
			}

			// Process batch
			$batch_size = $state['batch_size'];
			$batch      = array_slice( $state['queue'], 0, $batch_size );

			foreach ( $batch as $attachment_id ) {
				$result = $this->process_single_attachment( $attachment_id, $state );

				++$state['processed'];
				$state['last_id']       = $attachment_id;
				$state['last_activity'] = time();

				if ( $result['success'] ) {
					++$state['succeeded'];
					if ( isset( $result['credits'] ) ) {
						$state['credits_used'] += $result['credits'];

						// Deduct credits from AI service
						if ( class_exists( 'MSH_AI_Service' ) ) {
							$ai_service = MSH_AI_Service::get_instance();
							$ai_service->decrement_credits( $result['credits'] );
						}
					}
				} elseif ( $result['skipped'] ) {
					++$state['skipped'];
				} else {
					++$state['failed'];
					$state['failures'][] = array(
						'attachment_id' => $attachment_id,
						'message'       => $result['message'] ?? 'Unknown error',
						'time'          => time(),
					);
				}

				// Check credit limit
				if ( ! empty( $state['credits_limit'] ) && $state['credits_used'] >= $state['credits_limit'] ) {
					$state['status']     = 'paused';
					$state['messages'][] = 'Credit limit reached. Job paused.';
					break;
				}
			}

			// Remove processed items from queue
			$state['queue'] = array_slice( $state['queue'], count( $batch ) );

			// Check if done
			if ( empty( $state['queue'] ) ) {
				$state['status']       = 'completed';
				$state['completed_at'] = time();
				$this->update_job_status(
					$state['job_id'],
					'completed',
					array(
						'completed_at' => time(),
						'processed'    => $state['processed'],
						'succeeded'    => $state['succeeded'],
						'failed'       => $state['failed'],
						'skipped'      => $state['skipped'],
						'credits_used' => $state['credits_used'],
						'failures'     => $state['failures'],
					)
				);
			} else {
				// Update job progress
				$this->update_job_status(
					$state['job_id'],
					$state['status'],
					array(
						'processed'    => $state['processed'],
						'succeeded'    => $state['succeeded'],
						'failed'       => $state['failed'],
						'skipped'      => $state['skipped'],
						'credits_used' => $state['credits_used'],
					)
				);
			}

			$this->save_state( $state );

			// Schedule next run if not done
			if ( ! empty( $state['queue'] ) && $state['status'] === 'running' ) {
				$this->schedule_runner( 10 ); // 10 second delay between batches
			}
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Process a single attachment.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $state Current queue state.
	 *
	 * @return array Result with 'success', 'skipped', 'message', 'credits'.
	 */
	private function process_single_attachment( $attachment_id, $state ) {
		try {
			$generator = new MSH_Contextual_Meta_Generator();

			// Backup existing metadata
			$backup = array(
				'title'       => get_the_title( $attachment_id ),
				'alt_text'    => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'caption'     => wp_get_attachment_caption( $attachment_id ),
				'description' => get_post_field( 'post_content', $attachment_id ),
			);
			update_post_meta( $attachment_id, '_msh_meta_backup_' . time(), $backup );

			// Check if we should skip (fill-empty mode)
			if ( $state['mode'] === 'fill-empty' ) {
				$has_content = ! empty( $backup['title'] ) || ! empty( $backup['alt_text'] )
							|| ! empty( $backup['caption'] ) || ! empty( $backup['description'] );

				if ( $has_content ) {
					return array(
						'success' => false,
						'skipped' => true,
						'message' => 'Already has metadata',
					);
				}
			}

			// Clear cache
			delete_post_meta( $attachment_id, '_msh_cached_metadata' );
			delete_post_meta( $attachment_id, '_msh_meta_context' );
			delete_post_meta( $attachment_id, '_msh_ai_filename_slug' );

			// Generate new metadata
			$context  = $generator->detect_context( $attachment_id, true );
			$metadata = $generator->generate_meta_fields( $attachment_id, $context );

			if ( empty( $metadata ) ) {
				return array(
					'success' => false,
					'skipped' => false,
					'message' => 'No metadata generated',
				);
			}

			// Apply metadata to attachment
			$updates = array();

			if ( in_array( 'title', $state['fields'] ) && isset( $metadata['title'] ) ) {
				$updates['post_title'] = $metadata['title'];
			}

			if ( ! empty( $updates ) ) {
				$updates['ID'] = $attachment_id;
				wp_update_post( $updates );
			}

			if ( in_array( 'alt_text', $state['fields'] ) && isset( $metadata['alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $metadata['alt_text'] );
			}

			if ( in_array( 'caption', $state['fields'] ) && isset( $metadata['caption'] ) ) {
				$attachment = get_post( $attachment_id );
				if ( $attachment ) {
					wp_update_post(
						array(
							'ID'           => $attachment_id,
							'post_excerpt' => $metadata['caption'],
						)
					);
				}
			}

			if ( in_array( 'description', $state['fields'] ) && isset( $metadata['description'] ) ) {
				wp_update_post(
					array(
						'ID'           => $attachment_id,
						'post_content' => $metadata['description'],
					)
				);
			}

			// Filename is handled separately via rename system
			// We just store the AI slug for later use
			if ( in_array( 'filename', $state['fields'] ) && isset( $metadata['filename_slug'] ) ) {
				update_post_meta( $attachment_id, '_msh_ai_filename_slug', $metadata['filename_slug'] );
			}

			return array(
				'success' => true,
				'skipped' => false,
				'message' => 'Metadata regenerated successfully',
				'credits' => 1, // Each AI call costs 1 credit
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'skipped' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * AJAX: Start regeneration job.
	 */
	public function ajax_start_regeneration() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Support both direct IDs and scope-based selection
		$attachment_ids = isset( $_POST['attachment_ids'] ) ? array_map( 'intval', $_POST['attachment_ids'] ) : array();
		$scope          = isset( $_POST['scope'] ) ? sanitize_text_field( $_POST['scope'] ) : '';
		$mode           = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'fill-empty';
		$fields         = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_text_field', $_POST['fields'] ) : array( 'title', 'alt_text', 'caption', 'description' );

		// If scope is provided, resolve to attachment IDs
		if ( ! empty( $scope ) && empty( $attachment_ids ) ) {
			$attachment_ids = $this->get_attachments_by_scope( $scope );

			// Filter by mode if fill-empty
			if ( $mode === 'fill-empty' ) {
				$attachment_ids = $this->filter_missing_metadata( $attachment_ids, $fields );
			}
		}

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( array( 'message' => 'No images selected for regeneration.' ) );
		}

		$options = array(
			'mode'      => $mode,
			'fields'    => $fields,
			'plan_tier' => get_option( 'msh_plan_tier', 'free' ),
			'initiator' => 'manual',
		);

		$result = $this->queue_regeneration( $attachment_ids, $options );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Pause regeneration job.
	 */
	public function ajax_pause_regeneration() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$state = $this->get_state();
		if ( empty( $state ) ) {
			wp_send_json_error( array( 'message' => 'No active job' ) );
		}

		$state['status'] = 'paused';
		$this->save_state( $state );
		$this->update_job_status( $state['job_id'], 'paused' );

		wp_send_json_success( array( 'status' => 'paused' ) );
	}

	/**
	 * AJAX: Resume regeneration job.
	 */
	public function ajax_resume_regeneration() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$state = $this->get_state();
		if ( empty( $state ) || $state['status'] !== 'paused' ) {
			wp_send_json_error( array( 'message' => 'No paused job to resume' ) );
		}

		$state['status'] = 'queued';
		$this->save_state( $state );
		$this->update_job_status( $state['job_id'], 'running' );
		$this->schedule_runner( 5 );

		wp_send_json_success( array( 'status' => 'running' ) );
	}

	/**
	 * AJAX: Cancel regeneration job.
	 */
	public function ajax_cancel_regeneration() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$state = $this->get_state();
		if ( empty( $state ) ) {
			wp_send_json_error( array( 'message' => 'No active job' ) );
		}

		$state['status']       = 'cancelled';
		$state['completed_at'] = time();
		$this->save_state( $state );
		$this->update_job_status( $state['job_id'], 'cancelled', array( 'completed_at' => time() ) );

		// Clear the state
		delete_option( self::STATE_OPTION );

		wp_send_json_success( array( 'status' => 'cancelled' ) );
	}

	/**
	 * AJAX: Get regeneration status.
	 */
	public function ajax_get_status() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$state = $this->get_state();
		$jobs  = $this->get_jobs();

		// Get the current/most recent job for UI polling
		$current_job = null;
		if ( ! empty( $state ) && ! empty( $state['job_id'] ) ) {
			// Job is active, use state data to build job object
			$current_job = array(
				'job_id'       => $state['job_id'],
				'status'       => $state['status'],
				'total'        => $state['total'],
				'processed'    => $state['processed'],
				'successful'   => $state['succeeded'],
				'failed'       => $state['failed'],
				'skipped'      => $state['skipped'],
				'credits_used' => $state['credits_used'],
				'started_at'   => $state['started_at'],
				'completed_at' => $state['completed_at'] ?? null,
			);
		} elseif ( ! empty( $jobs ) ) {
			// No active state, get most recent completed job
			$recent_job  = reset( $jobs );
			$current_job = array(
				'job_id'       => $recent_job['job_id'],
				'status'       => $recent_job['status'],
				'total'        => $recent_job['total'],
				'processed'    => $recent_job['processed'],
				'successful'   => $recent_job['succeeded'],
				'failed'       => $recent_job['failed'],
				'skipped'      => $recent_job['skipped'],
				'credits_used' => $recent_job['credits_used'],
				'started_at'   => $recent_job['started_at'],
				'completed_at' => $recent_job['completed_at'] ?? null,
			);
		}

		wp_send_json_success(
			array(
				'job'           => $current_job,
				'current_state' => $state,
				'jobs'          => $jobs,
			)
		);
	}

	/**
	 * Helper methods
	 */
	private function get_state() {
		return get_option( self::STATE_OPTION, array() );
	}

	private function save_state( $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	private function get_jobs() {
		return get_option( self::JOBS_OPTION, array() );
	}

	private function save_job( $job ) {
		$jobs                   = $this->get_jobs();
		$jobs[ $job['job_id'] ] = $job;

		// Limit archived jobs
		if ( count( $jobs ) > self::MAX_ARCHIVED_JOBS ) {
			// Sort by created_at descending
			uasort(
				$jobs,
				function ( $a, $b ) {
					return ( $b['created_at'] ?? 0 ) - ( $a['created_at'] ?? 0 );
				}
			);

			// Keep only the most recent jobs
			$jobs = array_slice( $jobs, 0, self::MAX_ARCHIVED_JOBS, true );
		}

		update_option( self::JOBS_OPTION, $jobs, false );
	}

	private function update_job_status( $job_id, $status, $updates = array() ) {
		$jobs = $this->get_jobs();
		if ( isset( $jobs[ $job_id ] ) ) {
			$jobs[ $job_id ]['status'] = $status;
			foreach ( $updates as $key => $value ) {
				$jobs[ $job_id ][ $key ] = $value;
			}
			update_option( self::JOBS_OPTION, $jobs, false );
		}
	}

	private function has_pending_work( $state ) {
		return ! empty( $state )
			&& in_array( $state['status'], array( 'queued', 'running' ), true )
			&& ! empty( $state['queue'] );
	}

	private function schedule_runner( $delay_seconds = 60 ) {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + $delay_seconds, self::CRON_HOOK );
		}
	}

	/**
	 * Get attachment IDs by scope.
	 *
	 * @param string $scope Scope: 'all', 'published', or 'missing'.
	 * @return array Attachment IDs.
	 */
	private function get_attachments_by_scope( $scope ) {
		global $wpdb;

		switch ( $scope ) {
			case 'published':
				$ids = $wpdb->get_col(
					"SELECT DISTINCT attachment_id
                    FROM {$wpdb->prefix}msh_image_usage_index
                    WHERE attachment_id IN (
                        SELECT ID FROM {$wpdb->posts}
                        WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
                    )"
				);
				break;

			case 'missing':
				$ids = $wpdb->get_col(
					"SELECT p.ID FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND (p.post_title = '' OR p.post_title IS NULL OR pm.meta_value = '' OR pm.meta_value IS NULL)"
				);
				break;

			case 'all':
			default:
				$ids = $wpdb->get_col(
					"SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
				);
				break;
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Filter attachments to only those missing specified metadata fields.
	 *
	 * @param array $attachment_ids Attachment IDs to filter.
	 * @param array $fields Fields to check.
	 * @return array Filtered attachment IDs.
	 */
	private function filter_missing_metadata( $attachment_ids, $fields ) {
		if ( empty( $attachment_ids ) ) {
			return array();
		}

		$filtered = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$has_missing = false;

			foreach ( $fields as $field ) {
				switch ( $field ) {
					case 'title':
						$title = get_the_title( $attachment_id );
						if ( empty( $title ) || $title === 'Auto Draft' ) {
							$has_missing = true;
						}
						break;

					case 'alt_text':
						$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
						if ( empty( $alt ) ) {
							$has_missing = true;
						}
						break;

					case 'caption':
						$caption = wp_get_attachment_caption( $attachment_id );
						if ( empty( $caption ) ) {
							$has_missing = true;
						}
						break;

					case 'description':
						$post = get_post( $attachment_id );
						if ( empty( $post->post_content ) ) {
							$has_missing = true;
						}
						break;
				}

				if ( $has_missing ) {
					break;
				}
			}

			if ( $has_missing ) {
				$filtered[] = $attachment_id;
			}
		}

		return $filtered;
	}
}

// Initialize singleton
MSH_Metadata_Regeneration_Background::get_instance();
