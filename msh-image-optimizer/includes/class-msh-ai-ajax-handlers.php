<?php
/**
 * AJAX handlers for AI Regeneration UI
 *
 * Provides helper endpoints for modal functionality (image counts, estimates, credit balance).
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_AI_Ajax_Handlers {

	public function __construct() {
		add_action( 'wp_ajax_msh_get_ai_regen_counts', array( $this, 'get_regen_counts' ) );
		add_action( 'wp_ajax_msh_estimate_ai_regeneration', array( $this, 'estimate_regeneration' ) );
		add_action( 'wp_ajax_msh_get_ai_credit_balance', array( $this, 'get_credit_balance' ) );
	}

	/**
	 * Get image counts for modal scope options
	 */
	public function get_regen_counts() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		global $wpdb;

		// All images
		$all_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
		);

		// Published images (those referenced in content)
		$published_count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT attachment_id)
            FROM {$wpdb->prefix}msh_image_usage_index
            WHERE attachment_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
            )"
		);

		// Images with missing metadata (empty title OR empty alt text)
		$missing_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND (p.post_title = '' OR p.post_title IS NULL OR pm.meta_value = '' OR pm.meta_value IS NULL)"
		);

		wp_send_json_success(
			array(
				'all'              => (int) $all_count,
				'published'        => (int) $published_count,
				'missing_metadata' => (int) $missing_count,
			)
		);
	}

	/**
	 * Estimate cost for regeneration job
	 */
	public function estimate_regeneration() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$scope  = isset( $_POST['scope'] ) ? sanitize_text_field( $_POST['scope'] ) : 'all';
		$mode   = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'fill-empty';
		$fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_text_field', $_POST['fields'] ) : array();

		if ( empty( $fields ) ) {
			wp_send_json_success(
				array(
					'image_count'       => 0,
					'estimated_credits' => 0,
				)
			);
		}

		// Get attachment IDs based on scope
		$attachment_ids = $this->get_attachments_by_scope( $scope );

		// Filter by mode (if fill-empty, only images with missing fields)
		if ( $mode === 'fill-empty' ) {
			$attachment_ids = $this->filter_missing_metadata( $attachment_ids, $fields );
		}

		$image_count = count( $attachment_ids );

		// 1 credit per image (simple estimation)
		$estimated_credits = $image_count;

		wp_send_json_success(
			array(
				'image_count'       => $image_count,
				'estimated_credits' => $estimated_credits,
			)
		);
	}

	/**
	 * Get current credit balance and usage
	 */
	public function get_credit_balance() {
		check_ajax_referer( 'msh_image_optimizer', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'msh-image-optimizer' ) ), 403 );
		}

		if ( ! class_exists( 'MSH_AI_Service' ) ) {
			wp_send_json_error( array( 'message' => __( 'AI Service not available', 'msh-image-optimizer' ) ), 500 );
		}

		$ai_service   = MSH_AI_Service::get_instance();
		$access_state = $ai_service->determine_access_state();
		$access_mode  = isset( $access_state['access_mode'] ) ? $access_state['access_mode'] : '';

		if ( $access_mode === 'byok' ) {
			$balance = PHP_INT_MAX;
		} else {
			$balance = $ai_service->get_credit_balance();
		}

		$credit_usage    = get_option( 'msh_ai_credit_usage', array() );
		$current_month   = date( 'Y-m' );
		$used_this_month = isset( $credit_usage[ $current_month ] ) ? $credit_usage[ $current_month ] : 0;

		wp_send_json_success(
			array(
				'balance'         => $balance,
				'used_this_month' => $used_this_month,
				'plan_tier'       => get_option( 'msh_plan_tier', 'free' ),
				'access_mode'     => $access_mode,
			)
		);
	}

	/**
	 * Get attachment IDs based on scope
	 */
	private function get_attachments_by_scope( $scope ) {
		global $wpdb;

		switch ( $scope ) {
			case 'published':
				// Images referenced in usage index
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
				// Images with missing metadata
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
				// All images
				$ids = $wpdb->get_col(
					"SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
				);
				break;
		}

		return array_map( 'intval', $ids );
	}

	/**
	 * Filter attachments to only those with missing metadata in specified fields
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
					break; // No need to check other fields
				}
			}

			if ( $has_missing ) {
				$filtered[] = $attachment_id;
			}
		}

		return $filtered;
	}
}

// Initialize
new MSH_AI_Ajax_Handlers();
