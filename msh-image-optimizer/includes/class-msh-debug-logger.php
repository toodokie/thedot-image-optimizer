<?php
/**
 * MSH Debug Logger - Temporary logging for frontend testing
 *
 * Captures all file resolver activity, analyzer operations, and verification
 * processes to a dedicated log file for testing and monitoring.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Debug_Logger {
	private static $instance = null;
	private $log_file;
	private $enabled;
	private $session_id;

	private function __construct() {
		$upload_dir = wp_upload_dir();
		$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'msh-debug-logs';

		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Create session-based log file
		$this->session_id = substr( md5( microtime() . wp_get_session_token() ), 0, 8 );
		$this->log_file   = $log_dir . '/msh-debug-' . date( 'Y-m-d' ) . '-' . $this->session_id . '.log';

		// Enable if WP_DEBUG is true OR if constant is defined
		$this->enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'MSH_DEBUG_LOGGING' ) && MSH_DEBUG_LOGGING );

		// Write session start marker
		if ( $this->enabled ) {
			$this->write_header();
		}
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function write_header() {
		$header = sprintf(
			"\n==============================================\n" .
			"MSH DEBUG SESSION START\n" .
			"Session ID: %s\n" .
			"Date: %s\n" .
			"User: %s (ID: %d)\n" .
			"WP_DEBUG: %s\n" .
			"==============================================\n\n",
			$this->session_id,
			date( 'Y-m-d H:i:s' ),
			wp_get_current_user()->user_login ?? 'unknown',
			get_current_user_id(),
			defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false'
		);

		$this->write( $header, false );
	}

	/**
	 * Log a message with timestamp and context
	 *
	 * @param string $message The message to log
	 * @param string $context Context identifier (e.g., 'FILE_RESOLVER', 'ANALYZER', 'VERIFICATION')
	 * @param array  $data Optional associative array of data to log
	 */
	public function log( $message, $context = 'GENERAL', $data = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$timestamp = date( 'H:i:s.' ) . substr( microtime(), 2, 3 );
		$formatted = sprintf( '[%s] [%s] %s', $timestamp, $context, $message );

		if ( ! empty( $data ) ) {
			$formatted .= "\n" . $this->format_data( $data );
		}

		$this->write( $formatted . "\n" );
	}

	/**
	 * Log file resolver activity
	 */
	public function log_resolver( $attachment_id, $expected_path, $found_path, $method, $mismatch ) {
		if ( ! $this->enabled ) {
			return;
		}

		$data = array(
			'attachment_id' => $attachment_id,
			'expected_path' => $expected_path,
			'found_path'    => $found_path,
			'method'        => $method,
			'mismatch'      => $mismatch ? 'YES' : 'NO',
		);

		if ( $mismatch ) {
			$this->log(
				sprintf(
					'MISMATCH RESOLVED: Attachment %d - Expected "%s" → Found "%s"',
					$attachment_id,
					basename( $expected_path ),
					basename( $found_path )
				),
				'FILE_RESOLVER',
				$data
			);
		} else {
			$this->log(
				sprintf( 'Direct match: Attachment %d - %s', $attachment_id, basename( $found_path ) ),
				'FILE_RESOLVER'
			);
		}
	}

	/**
	 * Log analyzer operation
	 */
	public function log_analyzer( $attachment_id, $status, $details = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$this->log(
			sprintf( 'Analyzed attachment %d - Status: %s', $attachment_id, $status ),
			'ANALYZER',
			$details
		);
	}

	/**
	 * Log verification operation
	 */
	public function log_verification( $operation_id, $attachment_id, $result, $details = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$this->log(
			sprintf(
				'Verification [%s] - Attachment %d - Result: %s',
				substr( $operation_id, 0, 8 ),
				$attachment_id,
				$result
			),
			'VERIFICATION',
			$details
		);
	}

	/**
	 * Log rename operation
	 */
	public function log_rename( $attachment_id, $old_filename, $new_filename, $status, $details = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$this->log(
			sprintf(
				'Rename: Attachment %d - "%s" → "%s" [%s]',
				$attachment_id,
				$old_filename,
				$new_filename,
				$status
			),
			'RENAME',
			$details
		);
	}

	/**
	 * Log error
	 */
	public function log_error( $message, $context = 'ERROR', $details = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$this->log( '❌ ' . $message, $context, $details );
	}

	/**
	 * Log warning
	 */
	public function log_warning( $message, $context = 'WARNING', $details = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$this->log( '⚠️  ' . $message, $context, $details );
	}

	/**
	 * Log success
	 */
	public function log_success( $message, $context = 'SUCCESS', $details = array() ) {
		if ( ! $this->enabled ) {
			return;
		}

		$this->log( '✅ ' . $message, $context, $details );
	}

	/**
	 * Get current log file path
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * Get log file URL for downloading
	 */
	public function get_log_url() {
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $this->log_file );
	}

	/**
	 * Check if logging is enabled
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get log file size in human-readable format
	 */
	public function get_log_size() {
		if ( ! file_exists( $this->log_file ) ) {
			return '0 bytes';
		}

		$bytes = filesize( $this->log_file );
		$units = array( 'bytes', 'KB', 'MB', 'GB' );

		for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
			$bytes /= 1024;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Get recent log entries (last N lines)
	 */
	public function get_recent_entries( $lines = 50 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}

		$output = array();
		$file   = new SplFileObject( $this->log_file, 'r' );
		$file->seek( PHP_INT_MAX );
		$total_lines = $file->key();

		$start = max( 0, $total_lines - $lines );
		$file->seek( $start );

		while ( ! $file->eof() ) {
			$line = $file->fgets();
			if ( trim( $line ) !== '' ) {
				$output[] = $line;
			}
		}

		return $output;
	}

	/**
	 * Clear current log file
	 */
	public function clear_log() {
		if ( file_exists( $this->log_file ) ) {
			file_put_contents( $this->log_file, '' );
			$this->write_header();
			return true;
		}
		return false;
	}

	/**
	 * Format data array for logging
	 */
	private function format_data( $data, $indent = '  ' ) {
		$output = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$output[] = $indent . $key . ':';
				$output[] = $this->format_data( $value, $indent . '  ' );
			} elseif ( is_bool( $value ) ) {
				$output[] = $indent . $key . ': ' . ( $value ? 'true' : 'false' );
			} elseif ( is_null( $value ) ) {
				$output[] = $indent . $key . ': null';
			} else {
				$output[] = $indent . $key . ': ' . $value;
			}
		}
		return implode( "\n", $output );
	}

	/**
	 * Write to log file
	 */
	private function write( $content, $add_separator = false ) {
		if ( ! $this->enabled ) {
			return;
		}

		$separator = $add_separator ? "\n---\n\n" : '';
		file_put_contents( $this->log_file, $content . $separator, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Get all log files for current date
	 */
	public static function get_todays_logs() {
		$upload_dir = wp_upload_dir();
		$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'msh-debug-logs';

		if ( ! is_dir( $log_dir ) ) {
			return array();
		}

		$pattern = $log_dir . '/msh-debug-' . date( 'Y-m-d' ) . '-*.log';
		$files   = glob( $pattern );

		$logs = array();
		foreach ( $files as $file ) {
			$logs[] = array(
				'path'     => $file,
				'url'      => str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file ),
				'size'     => filesize( $file ),
				'modified' => filemtime( $file ),
				'name'     => basename( $file ),
			);
		}

		// Sort by modified time, newest first
		usort(
			$logs,
			function ( $a, $b ) {
				return $b['modified'] - $a['modified'];
			}
		);

		return $logs;
	}

	/**
	 * Clean up old log files (older than 7 days)
	 */
	public static function cleanup_old_logs() {
		$upload_dir = wp_upload_dir();
		$log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'msh-debug-logs';

		if ( ! is_dir( $log_dir ) ) {
			return 0;
		}

		$cutoff  = time() - ( 7 * DAY_IN_SECONDS );
		$files   = glob( $log_dir . '/msh-debug-*.log' );
		$deleted = 0;

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				if ( unlink( $file ) ) {
					++$deleted;
				}
			}
		}

		return $deleted;
	}
}
