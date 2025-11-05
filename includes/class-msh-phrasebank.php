<?php
/**
 * Phrasebank Loader for Non-AI Smart Rephrase
 *
 * Loads and merges phrasebank from default JSON + optional user overrides.
 *
 * @package MSH_Image_Optimizer
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Phrasebank {

	/**
	 * Cached phrasebank
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Get merged phrasebank (defaults + user overrides)
	 *
	 * @return array Phrasebank array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		// Load defaults from JSON
		$default_file = MSH_IO_PLUGIN_DIR . 'assets/phrasebank-default.json';
		$defaults     = array();

		if ( file_exists( $default_file ) ) {
			$json_content = file_get_contents( $default_file );
			if ( $json_content ) {
				$parsed = json_decode( $json_content, true );
				if ( is_array( $parsed ) ) {
					$defaults = $parsed;
				}
			}
		}

		// Load user overrides from options
		$user_override_json = get_option( 'msh_phrasebank_override', '' );
		$user_overrides     = array();

		if ( ! empty( $user_override_json ) ) {
			$parsed = json_decode( $user_override_json, true );
			if ( is_array( $parsed ) ) {
				$user_overrides = $parsed;
			}
		}

		// Merge: user overrides win
		self::$cache = array_replace_recursive( $defaults, $user_overrides );

		return self::$cache;
	}

	/**
	 * Pick a phrase deterministically from an array
	 *
	 * @param array $phrases Array of phrase strings
	 * @param int   $seed Deterministic seed (e.g., attachment ID)
	 * @return string Selected phrase
	 */
	public static function pick( array $phrases, $seed = 0 ) {
		if ( empty( $phrases ) ) {
			return '';
		}

		$index = absint( $seed ) % count( $phrases );
		return $phrases[ $index ];
	}

	/**
	 * Get time of day phrases for a given time
	 *
	 * @param string $time Time of day (sunrise, sunset, night)
	 * @param int    $seed Deterministic seed
	 * @return string Phrase like "at sunrise"
	 */
	public static function get_time_phrase( $time, $seed = 0 ) {
		$bank = self::get();

		if ( empty( $time ) || ! isset( $bank['time_of_day'][ $time ] ) ) {
			return '';
		}

		return self::pick( $bank['time_of_day'][ $time ], $seed );
	}

	/**
	 * Get verb phrase for a noun
	 *
	 * @param string $noun Noun (bridge, trees, river, etc.)
	 * @param int    $seed Deterministic seed
	 * @return string Verb like "spanning" or "through"
	 */
	public static function get_verb( $noun, $seed = 0 ) {
		$bank = self::get();

		if ( empty( $noun ) || ! isset( $bank['verbs'][ $noun ] ) ) {
			// Fallback to generic "with"
			return 'with';
		}

		return self::pick( $bank['verbs'][ $noun ], $seed );
	}

	/**
	 * Get mood adjective
	 *
	 * @param int $seed Deterministic seed
	 * @return string Mood like "calm" or "tranquil"
	 */
	public static function get_mood( $seed = 0 ) {
		$bank = self::get();

		if ( empty( $bank['mood'] ) ) {
			return 'calm';
		}

		return self::pick( $bank['mood'], $seed );
	}

	/**
	 * Get light phrase
	 *
	 * @param int $seed Deterministic seed
	 * @return string Light phrase like "soft light" or "warm light"
	 */
	public static function get_light( $seed = 0 ) {
		$bank = self::get();

		if ( empty( $bank['light'] ) ) {
			return 'natural light';
		}

		return self::pick( $bank['light'], $seed );
	}

	/**
	 * Get composition starter phrase
	 *
	 * @param int $seed Deterministic seed
	 * @return string Composition phrase like "The composition emphasizes"
	 */
	public static function get_composition( $seed = 0 ) {
		$bank = self::get();

		if ( empty( $bank['composition'] ) ) {
			return 'The composition emphasizes';
		}

		return self::pick( $bank['composition'], $seed );
	}

	/**
	 * Get elements phrase (for description endings)
	 *
	 * @param int $seed Deterministic seed
	 * @return string Elements phrase like "natural elements and visual depth"
	 */
	public static function get_elements( $seed = 0 ) {
		$bank = self::get();

		if ( empty( $bank['elements'] ) ) {
			return 'natural elements and visual depth';
		}

		return self::pick( $bank['elements'], $seed );
	}

	/**
	 * Clear cache (useful for testing or after updating options)
	 */
	public static function clear_cache() {
		self::$cache = null;
	}
}
