<?php
/**
 * License Manager
 *
 * Handles Pro license validation and activation.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Enterprise
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MSH_License_Manager class.
 */
class MSH_License_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var MSH_License_Manager
	 */
	private static $instance = null;

	/**
	 * License server URL.
	 *
	 * @var string
	 */
	private $license_server = 'https://license.thedot.com/api/v1';

	/**
	 * License option key.
	 *
	 * @var string
	 */
	const LICENSE_KEY_OPTION = 'msh_license_key';

	/**
	 * License status option key.
	 *
	 * @var string
	 */
	const LICENSE_STATUS_OPTION = 'msh_license_status';

	/**
	 * License data option key.
	 *
	 * @var string
	 */
	const LICENSE_DATA_OPTION = 'msh_license_data';

	/**
	 * Get singleton instance.
	 *
	 * @return MSH_License_Manager
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
		// Daily license check
		add_action( 'msh_daily_license_check', array( $this, 'verify_license' ) );

		if ( ! wp_next_scheduled( 'msh_daily_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'msh_daily_license_check' );
		}
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key License key to activate.
	 * @return array {
	 *     Activation result.
	 *
	 *     @type bool   $success Whether activation succeeded.
	 *     @type string $message User-facing message.
	 *     @type array  $data    License data if successful.
	 * }
	 */
	public function activate_license( $license_key ) {
		$license_key = sanitize_text_field( $license_key );

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'License key cannot be empty.', 'msh-image-optimizer' ),
			);
		}

		// Validate format (example: MSH-XXXX-XXXX-XXXX-XXXX)
		if ( ! preg_match( '/^MSH-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid license key format.', 'msh-image-optimizer' ),
			);
		}

		// Call license server
		$response = $this->call_license_server( 'activate', array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
			'admin_email' => get_option( 'admin_email' ),
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'License server error: %s', 'msh-image-optimizer' ),
					$response->get_error_message()
				),
			);
		}

		if ( ! isset( $response['valid'] ) || ! $response['valid'] ) {
			return array(
				'success' => false,
				'message' => $response['message'] ?? __( 'License validation failed.', 'msh-image-optimizer' ),
			);
		}

		// Store license data
		update_option( self::LICENSE_KEY_OPTION, $license_key );
		update_option( self::LICENSE_STATUS_OPTION, 'active' );
		update_option( self::LICENSE_DATA_OPTION, $response['data'] ?? array() );

		return array(
			'success' => true,
			'message' => __( 'License activated successfully!', 'msh-image-optimizer' ),
			'data'    => $response['data'] ?? array(),
		);
	}

	/**
	 * Deactivate the current license.
	 *
	 * @return array {
	 *     Deactivation result.
	 *
	 *     @type bool   $success Whether deactivation succeeded.
	 *     @type string $message User-facing message.
	 * }
	 */
	public function deactivate_license() {
		$license_key = get_option( self::LICENSE_KEY_OPTION );

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'No active license found.', 'msh-image-optimizer' ),
			);
		}

		// Call license server
		$response = $this->call_license_server( 'deactivate', array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
		) );

		// Clear local data regardless of server response
		delete_option( self::LICENSE_KEY_OPTION );
		delete_option( self::LICENSE_STATUS_OPTION );
		delete_option( self::LICENSE_DATA_OPTION );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => true, // Still success locally
				'message' => __( 'License deactivated locally. Server connection failed.', 'msh-image-optimizer' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'License deactivated successfully.', 'msh-image-optimizer' ),
		);
	}

	/**
	 * Verify the current license status.
	 *
	 * @return array {
	 *     Verification result.
	 *
	 *     @type bool   $valid   Whether license is valid.
	 *     @type string $status  License status (active, expired, invalid, etc.).
	 *     @type array  $data    License data.
	 * }
	 */
	public function verify_license() {
		$license_key = get_option( self::LICENSE_KEY_OPTION );

		if ( empty( $license_key ) ) {
			update_option( self::LICENSE_STATUS_OPTION, 'inactive' );
			return array(
				'valid'  => false,
				'status' => 'inactive',
				'data'   => array(),
			);
		}

		// Call license server
		$response = $this->call_license_server( 'verify', array(
			'license_key' => $license_key,
			'site_url'    => home_url(),
		) );

		if ( is_wp_error( $response ) ) {
			// Keep existing status on connection error
			return array(
				'valid'  => false,
				'status' => 'connection_error',
				'data'   => get_option( self::LICENSE_DATA_OPTION, array() ),
			);
		}

		$status = $response['status'] ?? 'invalid';
		$valid = 'active' === $status;

		update_option( self::LICENSE_STATUS_OPTION, $status );

		if ( isset( $response['data'] ) ) {
			update_option( self::LICENSE_DATA_OPTION, $response['data'] );
		}

		return array(
			'valid'  => $valid,
			'status' => $status,
			'data'   => $response['data'] ?? array(),
		);
	}

	/**
	 * Check if Pro features are active.
	 *
	 * @return bool
	 */
	public function is_pro_active() {
		$status = get_option( self::LICENSE_STATUS_OPTION, 'inactive' );
		return 'active' === $status;
	}

	/**
	 * Get license data.
	 *
	 * @return array {
	 *     License data.
	 *
	 *     @type string $license_key License key (masked).
	 *     @type string $status      License status.
	 *     @type string $email       License holder email.
	 *     @type string $expires     Expiration date (Y-m-d format).
	 *     @type int    $activations Current activation count.
	 *     @type int    $max_activations Maximum allowed activations.
	 * }
	 */
	public function get_license_data() {
		$license_key = get_option( self::LICENSE_KEY_OPTION, '' );
		$status = get_option( self::LICENSE_STATUS_OPTION, 'inactive' );
		$data = get_option( self::LICENSE_DATA_OPTION, array() );

		return array(
			'license_key'     => $license_key ? $this->mask_license_key( $license_key ) : '',
			'status'          => $status,
			'email'           => $data['email'] ?? '',
			'expires'         => $data['expires'] ?? '',
			'activations'     => $data['activations'] ?? 0,
			'max_activations' => $data['max_activations'] ?? 1,
			'plan'            => $data['plan'] ?? 'free',
		);
	}

	/**
	 * Mask license key for display.
	 *
	 * @param string $license_key Full license key.
	 * @return string Masked license key.
	 */
	private function mask_license_key( $license_key ) {
		// MSH-XXXX-XXXX-XXXX-XXXX -> MSH-XXXX-****-****-XXXX
		$parts = explode( '-', $license_key );
		if ( count( $parts ) === 5 ) {
			$parts[2] = '****';
			$parts[3] = '****';
			return implode( '-', $parts );
		}
		return $license_key;
	}

	/**
	 * Call the license server API.
	 *
	 * @param string $endpoint API endpoint (activate, deactivate, verify).
	 * @param array  $data     Request data.
	 * @return array|WP_Error Response data or WP_Error on failure.
	 */
	private function call_license_server( $endpoint, $data ) {
		$url = trailingslashit( $this->license_server ) . $endpoint;

		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => wp_json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'MSH-Image-Optimizer/' . MSH_IO_VERSION,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'license_server_error',
				$decoded['message'] ?? __( 'Unknown server error.', 'msh-image-optimizer' )
			);
		}

		return $decoded;
	}

	/**
	 * Get feature availability.
	 *
	 * @param string $feature Feature name (e.g., 'remote_sync', 'ab_testing', 'approval_queue').
	 * @return bool Whether feature is available.
	 */
	public function has_feature( $feature ) {
		if ( ! $this->is_pro_active() ) {
			return false;
		}

		$data = get_option( self::LICENSE_DATA_OPTION, array() );
		$plan = $data['plan'] ?? 'free';

		// Feature matrix
		$features_by_plan = array(
			'starter' => array( 'remote_sync' ),
			'pro'     => array( 'remote_sync', 'ab_testing', 'approval_queue', 'priority_support' ),
			'agency'  => array( 'remote_sync', 'ab_testing', 'approval_queue', 'priority_support', 'white_label', 'multi_site' ),
		);

		if ( ! isset( $features_by_plan[ $plan ] ) ) {
			return false;
		}

		return in_array( $feature, $features_by_plan[ $plan ], true );
	}
}
