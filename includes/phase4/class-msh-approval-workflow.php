<?php
/**
 * Phase 4 - Approval Workflow Manager.
 *
 * Implements Draft → Review → Approved workflow with multi-user chains,
 * reviewer feedback, notifications, and audit logging.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Approval_Workflow
 */
class MSH_Approval_Workflow {

	const TABLE_NAME     = 'msh_approval_queue';
	const SCHEMA_VERSION = 1;

	const STATUS_DRAFT    = 'draft';
	const STATUS_REVIEW   = 'in_review';
	const STATUS_APPROVED = 'approved';
	const STATUS_CHANGES  = 'changes_requested';

	/**
	 * Singleton instance.
	 *
	 * @var MSH_Approval_Workflow|null
	 */
	private static $instance = null;

	/**
	 * Metadata versioning dependency.
	 *
	 * @var MSH_Metadata_Versioning
	 */
	private $versioning;

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_Approval_Workflow
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
		$this->versioning = MSH_Metadata_Versioning::get_instance();

		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	/**
	 * Create approval queue table if needed.
	 *
	 * @return void
	 */
	public function maybe_create_table() {
		$installed = (int) get_option( 'msh_approval_workflow_schema_version', 0 );
		if ( $installed >= self::SCHEMA_VERSION ) {
			return;
		}

		global $wpdb;
		$table           = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			queue_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			media_id BIGINT(20) UNSIGNED NOT NULL,
			version_id BIGINT(20) UNSIGNED NOT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'draft',
			reviewer_id BIGINT(20) UNSIGNED DEFAULT NULL,
			submitted_by BIGINT(20) UNSIGNED DEFAULT NULL,
			notes LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (queue_id),
			KEY media_idx (media_id),
			KEY status_idx (status),
			KEY reviewer_idx (reviewer_id),
			KEY version_idx (version_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'msh_approval_workflow_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Submit a version into the approval workflow.
	 *
	 * @param int   $media_id  Media ID.
	 * @param int   $version_id Version ID.
	 * @param array $review_chain Array of user IDs in review order.
	 * @param string $message Optional message.
	 * @return int|false Queue ID or false.
	 */
	public function submit( $media_id, $version_id, $review_chain = array(), $message = '' ) {
		global $wpdb;

		$media_id   = absint( $media_id );
		$version_id = absint( $version_id );

		if ( ! $media_id || ! $version_id ) {
			return false;
		}

		$workflow = array(
			'chain'        => array_map( 'absint', $review_chain ),
			'current_step' => 0,
			'history'      => array(),
			'comments'     => array(),
		);

		if ( ! empty( $message ) ) {
			$workflow['comments'][] = array(
				'user_id' => get_current_user_id(),
				'user'    => wp_get_current_user()->display_name,
				'time'    => current_time( 'mysql' ),
				'message' => wp_kses_post( $message ),
				'type'    => 'submit',
			);
		}

		$initial_status  = empty( $review_chain ) ? self::STATUS_APPROVED : self::STATUS_REVIEW;
		$current_reviewer = empty( $review_chain ) ? null : (int) $review_chain[0];

		$wpdb->insert(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'media_id'     => $media_id,
				'version_id'   => $version_id,
				'status'       => $initial_status,
				'reviewer_id'  => $current_reviewer,
				'submitted_by' => get_current_user_id(),
				'notes'        => wp_json_encode( $workflow ),
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $wpdb->insert_id ) {
			return false;
		}

		$queue_id = (int) $wpdb->insert_id;

		if ( $initial_status === self::STATUS_APPROVED ) {
			$this->log_history( $queue_id, self::STATUS_APPROVED, __( 'Auto-approved (no reviewers)', 'msh-image-optimizer' ) );
			$this->mark_version_approved( $version_id, get_current_user_id() );
		} else {
			$this->log_history( $queue_id, self::STATUS_REVIEW, __( 'Submitted for review', 'msh-image-optimizer' ) );
			$this->notify_reviewer( $current_reviewer, $queue_id, 'request' );
		}

		return $queue_id;
	}

	/**
	 * Advance workflow for reviewer action.
	 *
	 * @param int    $queue_id Queue ID.
	 * @param string $action   Action ('approve' or 'request_changes').
	 * @param int    $user_id  Acting user.
	 * @param string $comment  Optional comment.
	 * @return bool
	 */
	public function handle_action( $queue_id, $action, $user_id, $comment = '' ) {
		$record = $this->get_queue_item( $queue_id );
		if ( ! $record ) {
			return false;
		}

		$workflow = $this->parse_notes( $record['notes'] );
		if ( $record['reviewer_id'] && (int) $record['reviewer_id'] !== (int) $user_id ) {
			return false;
		}

		if ( $comment ) {
			$this->append_comment( $queue_id, $user_id, $comment, $action );
		}

		if ( 'request_changes' === $action ) {
			$this->update_status( $queue_id, self::STATUS_CHANGES, $user_id );
			return true;
		}

		// Approve step.
		++$workflow['current_step'];
		$next_reviewer = $workflow['current_step'] < count( $workflow['chain'] ) ? (int) $workflow['chain'][ $workflow['current_step'] ] : null;

		if ( null === $next_reviewer ) {
			$this->update_status( $queue_id, self::STATUS_APPROVED, $user_id );
			$this->mark_version_approved( $record['version_id'], $user_id );
		} else {
			$this->save_workflow_state( $queue_id, $workflow );
			$this->set_reviewer( $queue_id, $next_reviewer );
			$this->update_status( $queue_id, self::STATUS_REVIEW, $user_id );
			$this->notify_reviewer( $next_reviewer, $queue_id, 'request' );
		}

		return true;
	}

	/**
	 * Reassign reviewer.
	 *
	 * @param int $queue_id Queue ID.
	 * @param int $user_id  Reviewer user ID.
	 * @return void
	 */
	public function set_reviewer( $queue_id, $user_id ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'reviewer_id' => $user_id ? absint( $user_id ) : null,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'queue_id' => absint( $queue_id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update status with audit entry.
	 *
	 * @param int    $queue_id Queue ID.
	 * @param string $status   Status.
	 * @param int    $user_id  User ID.
	 * @return void
	 */
	public function update_status( $queue_id, $status, $user_id ) {
		global $wpdb;

		$allowed = array( self::STATUS_DRAFT, self::STATUS_REVIEW, self::STATUS_APPROVED, self::STATUS_CHANGES );
		if ( ! in_array( $status, $allowed, true ) ) {
			return;
		}

		$wpdb->update(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'queue_id' => absint( $queue_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		switch ( $status ) {
			case self::STATUS_APPROVED:
				$this->log_history( $queue_id, $status, __( 'Metadata approved', 'msh-image-optimizer' ), $user_id );
				break;
			case self::STATUS_CHANGES:
				$this->log_history( $queue_id, $status, __( 'Changes requested', 'msh-image-optimizer' ), $user_id );
				break;
			default:
				$this->log_history( $queue_id, $status, '', $user_id );
		}
	}

	/**
	 * Append reviewer comment.
	 *
	 * @param int    $queue_id Queue ID.
	 * @param int    $user_id  User ID.
	 * @param string $comment  Comment text.
	 * @param string $type     Comment type.
	 * @return void
	 */
	public function append_comment( $queue_id, $user_id, $comment, $type = 'comment' ) {
		$record   = $this->get_queue_item( $queue_id );
		$workflow = $this->parse_notes( $record['notes'] );

		$user = get_user_by( 'id', $user_id );

		$workflow['comments'][] = array(
			'user_id' => $user ? $user->ID : 0,
			'user'    => $user ? $user->display_name : '',
			'time'    => current_time( 'mysql' ),
			'message' => wp_kses_post( $comment ),
			'type'    => $type,
		);

		$this->save_workflow_state( $queue_id, $workflow );
	}

	/**
	 * Log history entry.
	 *
	 * @param int    $queue_id Queue ID.
	 * @param string $status   Status.
	 * @param string $note     Note.
	 * @param int    $user_id  User ID.
	 * @return void
	 */
	private function log_history( $queue_id, $status, $note = '', $user_id = 0 ) {
		$record   = $this->get_queue_item( $queue_id );
		if ( ! $record ) {
			return;
		}

		$workflow = $this->parse_notes( $record['notes'] );
		$user     = $user_id ? get_user_by( 'id', $user_id ) : null;

		$workflow['history'][] = array(
			'time'   => current_time( 'mysql' ),
			'status' => $status,
			'user'   => $user ? $user->display_name : '',
			'user_id'=> $user ? $user->ID : 0,
			'note'   => $note,
		);

		$this->save_workflow_state( $queue_id, $workflow );
	}

	/**
	 * Save workflow structure to notes column.
	 *
	 * @param int   $queue_id Queue ID.
	 * @param array $workflow Workflow data.
	 * @return void
	 */
	private function save_workflow_state( $queue_id, $workflow ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'notes'      => wp_json_encode( $workflow ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'queue_id' => absint( $queue_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetch a queue item.
	 *
	 * @param int $queue_id Queue ID.
	 * @return array|null
	 */
	public function get_queue_item( $queue_id ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_NAME;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE queue_id = %d",
				absint( $queue_id )
			),
			ARRAY_A
		);
	}

	/**
	 * List queue items by status.
	 *
	 * @param string $status Status filter.
	 * @return array
	 */
	public function get_queue( $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_NAME;
		$sql   = "SELECT * FROM {$table}";
		if ( $status ) {
			$sql .= $wpdb->prepare( ' WHERE status = %s', sanitize_text_field( $status ) );
		}

		$sql .= ' ORDER BY updated_at DESC';

		$items = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $items as &$item ) {
			$item['workflow'] = $this->parse_notes( $item['notes'] );
		}

		return $items;
	}

	/**
	 * Parse workflow notes JSON.
	 *
	 * @param string $notes Notes JSON.
	 * @return array
	 */
	private function parse_notes( $notes ) {
		if ( empty( $notes ) ) {
			return array(
				'chain'        => array(),
				'current_step' => 0,
				'history'      => array(),
				'comments'     => array(),
			);
		}

		$data = json_decode( $notes, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'chain'        => array(),
				'current_step' => 0,
				'history'      => array(),
				'comments'     => array(
					array(
						'user_id' => 0,
						'user'    => '',
						'time'    => current_time( 'mysql' ),
						'message' => wp_kses_post( $notes ),
						'type'    => 'legacy',
					),
				),
			);
		}

		$defaults = array(
			'chain'        => array(),
			'current_step' => 0,
			'history'      => array(),
			'comments'     => array(),
		);

		return wp_parse_args( $data, $defaults );
	}

	/**
	 * Notify reviewer via email.
	 *
	 * @param int    $user_id  User ID.
	 * @param int    $queue_id Queue ID.
	 * @param string $type     Notification type.
	 * @return void
	 */
	private function notify_reviewer( $user_id, $queue_id, $type = 'request' ) {
		if ( ! $user_id ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		$subject = __( 'Metadata Review Requested', 'msh-image-optimizer' );
		$message = __( 'A new metadata version is awaiting your review.', 'msh-image-optimizer' );

		if ( 'approved' === $type ) {
			$subject = __( 'Metadata Approved', 'msh-image-optimizer' );
			$message = __( 'Your metadata submission has been approved.', 'msh-image-optimizer' );
		}

		$link = add_query_arg(
			array(
				'page'     => 'msh-approval-queue',
				'queue_id' => $queue_id,
			),
			admin_url( 'admin.php' )
		);

		$message .= "\n\n" . sprintf(
			/* translators: %s: approval queue link */
			__( 'Review queue: %s', 'msh-image-optimizer' ),
			$link
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Mark metadata version as approved.
	 *
	 * @param int $version_id Version ID.
	 * @param int $user_id    Approver ID.
	 * @return void
	 */
	private function mark_version_approved( $version_id, $user_id ) {
		$this->versioning->update_version(
			$version_id,
			array(
				'approved_by' => $user_id,
				'approved_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			)
		);
	}
}
