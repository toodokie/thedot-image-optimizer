<?php
/**
 * Background processor for the MSH Usage Index.
 * Manages full rebuilds and targeted updates via WP-Cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Usage_Index_Background {
	const STATE_OPTION   = 'msh_usage_index_queue_state';
	const LOCK_TRANSIENT = 'msh_usage_index_queue_lock';
	const CRON_HOOK      = 'msh_process_usage_index_queue';

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Usage_Index_Background|null
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

		add_action( 'add_attachment', array( $this, 'handle_attachment_change' ), 20 );
		add_action( 'edit_attachment', array( $this, 'handle_attachment_change' ), 20 );
		add_action( 'delete_attachment', array( $this, 'handle_attachment_deletion' ), 20 );

		add_action( 'wp_ajax_msh_queue_usage_index_rebuild', array( $this, 'ajax_queue_rebuild' ) );
		add_action( 'wp_ajax_msh_get_usage_index_status', array( $this, 'ajax_get_status' ) );
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
	 * Queue a background rebuild (smart or full).
	 *
	 * @param string $mode  'smart' or 'full'
	 * @param bool   $force Whether to force reindex each attachment.
	 * @param string $initiator Label for logging (manual, cli, auto)
	 *
	 * @return array Queue state.
	 */
	public function queue_rebuild( $mode = 'smart', $force = false, $initiator = 'manual' ) {
		$mode = $force ? 'full' : ( $mode === 'full' ? 'full' : 'smart' );

		if ( $mode === 'smart' ) {
			$state = array(
				'status'        => 'queued',
				'mode'          => 'smart',
				'force'         => false,
				'batch_size'    => 1,
				'total'         => 1,
				'processed'     => 0,
				'queue'         => array(),
				'last_id'       => 0,
				'initiator'     => $initiator,
				'queued_at'     => time(),
				'started_at'    => null,
				'completed_at'  => null,
				'last_activity' => null,
				'errors'        => array(),
				'messages'      => array(),
			);
		} else {
			global $wpdb;

			$total = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
			);

			$state = array(
				'status'        => 'queued',
				'mode'          => 'full',
				'force'         => (bool) $force,
				'batch_size'    => 25,
				'total'         => $total,
				'processed'     => 0,
				'queue'         => array(),
				'last_id'       => 0,
				'initiator'     => $initiator,
				'queued_at'     => time(),
				'started_at'    => null,
				'completed_at'  => null,
				'last_activity' => null,
				'errors'        => array(),
				'messages'      => array(),
			);
		}

		$this->save_state( $state );
		$this->schedule_runner( 5 );

		return $state;
	}

	/**
	 * Queue targeted attachments for re-indexing.
	 *
	 * @param array  $attachment_ids Attachment IDs to process.
	 * @param string $initiator      Optional reason label.
	 */
	public function queue_attachments( array $attachment_ids, $initiator = 'auto' ) {
		$ids = array_values( array_filter( array_map( 'intval', $attachment_ids ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		$state = $this->get_state();

		if ( empty( $state ) ) {
			$state = array(
				'status'        => 'queued',
				'mode'          => 'targeted',
				'force'         => false,
				'batch_size'    => 20,
				'total'         => count( $ids ),
				'processed'     => 0,
				'queue'         => $ids,
				'last_id'       => 0,
				'initiator'     => $initiator,
				'queued_at'     => time(),
				'started_at'    => null,
				'completed_at'  => null,
				'last_activity' => null,
				'errors'        => array(),
				'messages'      => array(),
			);
		} else {
			$existing_queue = isset( $state['queue'] ) && is_array( $state['queue'] )
				? $state['queue']
				: array();

			$state['queue']         = array_values( array_unique( array_merge( $existing_queue, $ids ) ) );
			$state['status']        = $state['status'] === 'complete' ? 'queued' : $state['status'];
			$state['initiator']     = $initiator;
			$state['last_activity'] = time();
			$state['total']         = max(
				(int) ( $state['total'] ?? 0 ),
				(int) ( $state['processed'] ?? 0 ) + count( $state['queue'] )
			);
		}

		$this->save_state( $state );
		$this->schedule_runner( 10 );
	}

	/**
	 * Handle attachment adds/edits.
	 */
	public function handle_attachment_change( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$this->queue_attachments( array( $attachment_id ), 'attachment_change' );
	}

	/**
	 * Handle attachment deletion.
	 */
	public function handle_attachment_deletion( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$usage_index = MSH_Image_Usage_Index::get_instance();
		if ( method_exists( $usage_index, 'cleanup_orphaned_entries' ) ) {
			$usage_index->cleanup_orphaned_entries( array( $attachment_id ) );
		}
	}

	/**
	 * Cron runner.
	 */
	public function process_queue() {
		$state = $this->get_state();
		if ( ! $this->has_pending_work( $state ) ) {
			return;
		}

		if ( $this->is_locked() ) {
			$this->schedule_runner( 30 );
			return;
		}

		$this->acquire_lock();

		try {
			$state = $this->get_state();
			if ( ! $this->has_pending_work( $state ) ) {
				$this->release_lock();
				return;
			}

			$usage_index = MSH_Image_Usage_Index::get_instance();
			$batch_size  = max( 1, (int) ( $state['batch_size'] ?? 20 ) );

			if ( empty( $state['started_at'] ) ) {
				$state['started_at'] = time();
			}
			$state['status'] = 'running';

			$processed_this_run = 0;
			$errors             = array();

			$queue = isset( $state['queue'] ) && is_array( $state['queue'] ) ? $state['queue'] : array();

			if ( ! empty( $queue ) ) {
				$batch_ids = array_splice( $queue, 0, $batch_size );
				foreach ( $batch_ids as $attachment_id ) {
					try {
						$usage_index->index_attachment_usage( $attachment_id, ! empty( $state['force'] ) );
						++$processed_this_run;
					} catch ( Exception $e ) {
						$errors[] = $e->getMessage();
					}
				}
				$state['queue'] = $queue;
			} elseif ( $state['mode'] === 'full' ) {
				global $wpdb;
				$last_id   = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;
				$batch_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'attachment'
                       AND post_mime_type LIKE 'image/%%'
                       AND ID > %d
                     ORDER BY ID ASC
                     LIMIT %d",
						$last_id,
						$batch_size
					)
				);

				if ( ! empty( $batch_ids ) ) {
					foreach ( $batch_ids as $attachment_id ) {
						try {
							$usage_index->index_attachment_usage( $attachment_id, ! empty( $state['force'] ) );
							++$processed_this_run;
						} catch ( Exception $e ) {
							$errors[] = $e->getMessage();
						}
					}
					$state['last_id'] = (int) end( $batch_ids );
				}
			} elseif ( $state['mode'] === 'smart' ) {
				try {
					$result               = $usage_index->smart_build_index();
					$state['last_result'] = $result;
					$state['messages'][]  = $result['message'] ?? __( 'Smart index rebuild finished.', 'msh-image-optimizer' );
				} catch ( Exception $e ) {
					$errors[] = $e->getMessage();
				}
				$processed_this_run = 1;
				$state['processed'] = 1;
				$state['queue']     = array();
			}

			if ( ! empty( $errors ) ) {
				$state['errors'] = array_slice( array_merge( $state['errors'] ?? array(), $errors ), -10 );
			}

			if ( $processed_this_run > 0 ) {
				$state['processed'] = (int) ( $state['processed'] ?? 0 ) + $processed_this_run;
			}

			$state['last_activity'] = time();

			$pending_queue = count( $state['queue'] ?? array() );

			if ( $state['mode'] === 'full' ) {
				global $wpdb;
				$total          = (int) $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
				);
				$state['total'] = max( $total, (int) ( $state['total'] ?? 0 ) );
			} elseif ( $state['mode'] === 'targeted' ) {
				$state['total'] = max(
					(int) ( $state['total'] ?? 0 ),
					(int) ( $state['processed'] ?? 0 ) + $pending_queue
				);
			}

			$pending_jobs = max( 0, (int) ( $state['total'] ?? 0 ) - (int) ( $state['processed'] ?? 0 ) ) + $pending_queue;

			if ( $pending_jobs <= 0 || ( $state['mode'] === 'smart' && $processed_this_run > 0 ) ) {
				$state['status']       = 'complete';
				$state['completed_at'] = time();
				$state['queue']        = array();
				$state['messages'][]   = __( 'Usage index background job completed.', 'msh-image-optimizer' );
				update_option( 'msh_usage_index_last_build', current_time( 'mysql' ) );
			}

			$this->save_state( $state );

			if ( $state['status'] !== 'complete' ) {
				$this->schedule_runner( 30 );
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * AJAX: queue rebuild.
	 */
	public function ajax_queue_rebuild() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$mode  = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'smart';
		$force = ! empty( $_POST['force'] ) && $_POST['force'] !== 'false';

		$state  = $this->queue_rebuild( $mode, $force, 'manual' );
		$status = $this->get_status_for_ui( false );

		$message = $force
			? __( 'Force usage index rebuild queued. Processing all attachments in the background.', 'msh-image-optimizer' )
			: __( 'Smart usage index rebuild queued. Background job will refresh the cache shortly.', 'msh-image-optimizer' );

		wp_send_json_success(
			array(
				'message' => $message,
				'status'  => $status,
			)
		);
	}

	/**
	 * AJAX: get current queue status.
	 */
	public function ajax_get_status() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		wp_send_json_success( $this->get_status_for_ui() );
	}

	/**
	 * Transform queue state for UI consumers.
	 *
	 * @param bool $include_summary Include index summary payload.
	 *
	 * @return array
	 */
	public function get_status_for_ui( $include_summary = true ) {
		$state = $this->get_state();

		$queue_count = count( $state['queue'] ?? array() );
		$total       = (int) ( $state['total'] ?? 0 );
		$processed   = (int) ( $state['processed'] ?? 0 );

		if ( $total < $processed ) {
			$total = $processed;
		}

		$pending_jobs = max( 0, $total - $processed ) + $queue_count;

		$status = array(
			'status'             => $state['status'] ?? 'idle',
			'mode'               => $state['mode'] ?? 'idle',
			'force'              => ! empty( $state['force'] ),
			'processed'          => $processed,
			'total'              => $total,
			'queued_attachments' => $queue_count,
			'pending_jobs'       => $pending_jobs,
			'batch_size'         => (int) ( $state['batch_size'] ?? 20 ),
			'queued_at'          => $this->format_timestamp( $state['queued_at'] ?? null ),
			'started_at'         => $this->format_timestamp( $state['started_at'] ?? null ),
			'completed_at'       => $this->format_timestamp( $state['completed_at'] ?? null ),
			'last_activity'      => $this->format_timestamp( $state['last_activity'] ?? null ),
			'next_run'           => $this->format_timestamp( wp_next_scheduled( self::CRON_HOOK ) ),
			'errors'             => array_slice( (array) ( $state['errors'] ?? array() ), -5 ),
			'messages'           => array_slice( (array) ( $state['messages'] ?? array() ), -5 ),
		);

		if ( ! empty( $state['last_result'] ) ) {
			$status['last_result'] = $state['last_result'];
		}

		if ( $include_summary ) {
			$usage_index       = MSH_Image_Usage_Index::get_instance();
			$stats             = $usage_index->get_index_stats();
			$status['summary'] = $usage_index->format_stats_for_ui( $stats );

			if ( ! empty( $status['summary'] ) ) {
				$status['summary']['pending_jobs']       = $status['pending_jobs'];
				$status['summary']['queued_attachments'] = $status['queued_attachments'];
				$status['summary']['scheduler']          = array(
					'status'        => $status['status'],
					'mode'          => $status['mode'],
					'force'         => $status['force'],
					'pending_jobs'  => $status['pending_jobs'],
					'queued'        => $status['queued_attachments'],
					'processed'     => $status['processed'],
					'total'         => $status['total'],
					'queued_at'     => $status['queued_at'],
					'last_activity' => $status['last_activity'],
					'next_run'      => $status['next_run'],
					'completed_at'  => $status['completed_at'],
				);
			}
		}

		return $status;
	}

	/**
	 * Check whether queue has work to process.
	 *
	 * @param array|null $state Queue state.
	 */
	private function has_pending_work( $state ) {
		if ( empty( $state ) ) {
			return false;
		}

		if ( ! empty( $state['queue'] ) ) {
			return true;
		}

		if ( ( $state['mode'] ?? '' ) === 'smart' ) {
			return ( $state['status'] ?? '' ) !== 'complete';
		}

		if ( ( $state['mode'] ?? '' ) === 'full' ) {
			$total     = (int) ( $state['total'] ?? 0 );
			$processed = (int) ( $state['processed'] ?? 0 );
			return $processed < $total;
		}

		if ( ( $state['mode'] ?? '' ) === 'targeted' ) {
			return ( $state['processed'] ?? 0 ) < ( $state['total'] ?? 0 );
		}

		return false;
	}

	/**
	 * Retrieve queue state from option.
	 */
	private function get_state() {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist queue state.
	 */
	private function save_state( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Schedule cron runner.
	 *
	 * @param int $delay Seconds until execution.
	 */
	private function schedule_runner( $delay = 0 ) {
		$timestamp = time() + max( 0, (int) $delay );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Lock queue to avoid concurrent processing.
	 */
	private function acquire_lock() {
		set_transient( self::LOCK_TRANSIENT, 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Release lock.
	 */
	private function release_lock() {
		delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Determine if queue is locked.
	 */
	private function is_locked() {
		return (bool) get_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Format timestamp for responses.
	 *
	 * @param int|null $timestamp Unix timestamp.
	 */
	private function format_timestamp( $timestamp ) {
		if ( empty( $timestamp ) || ! is_numeric( $timestamp ) ) {
			return null;
		}

		return gmdate( 'c', (int) $timestamp );
	}
}
