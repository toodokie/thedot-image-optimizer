<?php
/**
 * MSH Performance Profiler
 *
 * Lightweight stopwatch for surgical performance debugging.
 * Tracks execution time across named segments and outputs structured JSON logs.
 *
 * @package    MSH_Image_Optimizer
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSH_Profiler
 *
 * Drop-in instrumentation for timing analysis bottlenecks.
 */
final class MSH_Profiler {

	/**
	 * Active timers (segment name => start time)
	 *
	 * @var array
	 */
	private static $stack = array();

	/**
	 * Accumulated time per segment
	 *
	 * @var array
	 */
	private static $segments = array();

	/**
	 * Start timing a named segment
	 *
	 * @param string $segment Segment name (e.g. 'db_prefetch', 'ai_call').
	 *
	 * @return void
	 */
	public static function begin( $segment ) {
		self::$stack[ $segment ] = microtime( true );
	}

	/**
	 * End timing a named segment and accumulate elapsed time
	 *
	 * @param string $segment Segment name.
	 *
	 * @return float|null Elapsed seconds, or null if segment wasn't started.
	 */
	public static function end( $segment ) {
		if ( ! isset( self::$stack[ $segment ] ) ) {
			return null;
		}

		$elapsed = microtime( true ) - self::$stack[ $segment ];
		unset( self::$stack[ $segment ] );

		if ( ! isset( self::$segments[ $segment ] ) ) {
			self::$segments[ $segment ] = 0;
		}
		self::$segments[ $segment ] += $elapsed;

		return $elapsed;
	}

	/**
	 * Output accumulated timing data to error_log as JSON
	 *
	 * @param array $context Additional context to include in log (e.g. attachment_id, file path).
	 *
	 * @return void
	 */
	public static function flush( $context = array() ) {
		// Round all segment times to 3 decimals for readability
		$rounded_segments = array();
		foreach ( self::$segments as $key => $val ) {
			$rounded_segments[ $key ] = round( $val, 3 );
		}

		$log_entry = array(
			'ts'       => gmdate( 'c' ),
			'type'     => 'msh_profile',
			'context'  => $context,
			'segments' => $rounded_segments,
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( wp_json_encode( $log_entry, JSON_UNESCAPED_SLASHES ) );

		// Reset for next profile
		self::$segments = array();
	}

	/**
	 * Reset all timers (useful for testing)
	 *
	 * @return void
	 */
	public static function reset() {
		self::$stack    = array();
		self::$segments = array();
	}
}
