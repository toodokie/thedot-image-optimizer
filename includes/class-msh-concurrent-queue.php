<?php
/**
 * Concurrent HTTP Request Queue
 *
 * Handles parallel HTTP requests using curl_multi for significant performance improvements.
 * Processes AI metadata generation requests concurrently instead of sequentially.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Concurrent_Queue {

	/**
	 * Maximum concurrent requests
	 *
	 * @var int
	 */
	private $concurrency = 3;

	/**
	 * Pending requests queue
	 *
	 * @var array
	 */
	private $pending = array();

	/**
	 * Active curl handles
	 *
	 * @var array
	 */
	private $active = array();

	/**
	 * Completed results
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * curl_multi handle
	 *
	 * @var resource
	 */
	private $multi_handle;

	/**
	 * Constructor
	 *
	 * @param int $concurrency Maximum concurrent requests (default: 3).
	 */
	public function __construct( $concurrency = 3 ) {
		$this->concurrency  = max( 1, min( $concurrency, 10 ) ); // Limit 1-10
		$this->multi_handle = curl_multi_init();
	}

	/**
	 * Add a request to the queue
	 *
	 * @param string $id Unique identifier for this request.
	 * @param string $url API endpoint URL.
	 * @param array  $headers HTTP headers.
	 * @param string $body JSON body.
	 * @param int    $timeout Timeout in seconds (default: 15).
	 * @return void
	 */
	public function add( $id, $url, $headers, $body, $timeout = 15 ) {
		$this->pending[ $id ] = array(
			'url'     => $url,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => $timeout,
		);
	}

	/**
	 * Execute all queued requests in parallel
	 *
	 * @return array Results keyed by request ID.
	 */
	public function execute() {
		// Process requests in batches
		while ( ! empty( $this->pending ) || ! empty( $this->active ) ) {
			// Fill up to concurrency limit
			while ( count( $this->active ) < $this->concurrency && ! empty( $this->pending ) ) {
				$id      = key( $this->pending );
				$request = array_shift( $this->pending );

				$this->start_request( $id, $request );
			}

			// Process active requests
			$this->process_active();
		}

		curl_multi_close( $this->multi_handle );

		return $this->results;
	}

	/**
	 * Start a single request
	 *
	 * @param string $id Request ID.
	 * @param array  $request Request configuration.
	 * @return void
	 */
	private function start_request( $id, $request ) {
		$ch = curl_init();

		// Convert headers array to curl format
		$curl_headers = array();
		foreach ( $request['headers'] as $key => $value ) {
			$curl_headers[] = $key . ': ' . $value;
		}

		curl_setopt_array(
			$ch,
			array(
				CURLOPT_URL            => $request['url'],
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $request['body'],
				CURLOPT_HTTPHEADER     => $curl_headers,
				CURLOPT_TIMEOUT        => $request['timeout'],
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
			)
		);

		curl_multi_add_handle( $this->multi_handle, $ch );

		$this->active[ $id ] = array(
			'handle'     => $ch,
			'start_time' => microtime( true ),
		);

		error_log( sprintf( '[MSH Concurrent] Started request %s', $id ) );
	}

	/**
	 * Process active requests and collect results
	 *
	 * @return void
	 */
	private function process_active() {
		if ( empty( $this->active ) ) {
			return;
		}

		// Execute curl_multi
		do {
			$status = curl_multi_exec( $this->multi_handle, $running );
		} while ( $status === CURLM_CALL_MULTI_PERFORM );

		// Check for errors
		if ( $status !== CURLM_OK ) {
			error_log( '[MSH Concurrent] curl_multi_exec error: ' . $status );
			return;
		}

		// Block until activity (or 200ms timeout)
		if ( $running > 0 ) {
			curl_multi_select( $this->multi_handle, 0.2 );
		}

		// Collect completed requests
		while ( $info = curl_multi_info_read( $this->multi_handle ) ) {
			if ( $info['msg'] !== CURLMSG_DONE ) {
				continue;
			}

			$ch = $info['handle'];

			// Find the request ID for this handle
			$id = null;
			foreach ( $this->active as $req_id => $data ) {
				if ( $data['handle'] === $ch ) {
					$id = $req_id;
					break;
				}
			}

			if ( ! $id ) {
				continue;
			}

			$duration = microtime( true ) - $this->active[ $id ]['start_time'];

			// Get response
			$response = curl_multi_getcontent( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$error     = curl_error( $ch );

			// Store result
			if ( $info['result'] === CURLE_OK && $http_code >= 200 && $http_code < 300 ) {
				$this->results[ $id ] = array(
					'success'  => true,
					'response' => $response,
					'duration' => $duration,
				);
				error_log( sprintf( '[MSH Concurrent] Completed %s in %.2fs', $id, $duration ) );
			} else {
				$this->results[ $id ] = array(
					'success' => false,
					'error'   => ! empty( $error ) ? $error : 'HTTP ' . $http_code,
					'duration' => $duration,
				);
				error_log( sprintf( '[MSH Concurrent] Failed %s: %s', $id, $this->results[ $id ]['error'] ) );
			}

			// Clean up
			curl_multi_remove_handle( $this->multi_handle, $ch );
			curl_close( $ch );
			unset( $this->active[ $id ] );
		}
	}

	/**
	 * Get results for a specific request
	 *
	 * @param string $id Request ID.
	 * @return array|null Result array or null if not found.
	 */
	public function get_result( $id ) {
		return isset( $this->results[ $id ] ) ? $this->results[ $id ] : null;
	}

	/**
	 * Check if a request was successful
	 *
	 * @param string $id Request ID.
	 * @return bool True if successful, false otherwise.
	 */
	public function is_success( $id ) {
		return isset( $this->results[ $id ] ) && $this->results[ $id ]['success'];
	}
}
