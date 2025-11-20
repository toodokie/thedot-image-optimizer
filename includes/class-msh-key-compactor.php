<?php
/**
 * Utility to expand compact AI response keys back to verbose names.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_Key_Compactor {

	/**
	 * Map of compact -> verbose keys.
	 *
	 * @var array<string,string>
	 */
	private static $short_to_long = array(
		// Filename suggestion.
		'f'   => 'file_name_suggestion',
		'fn'  => 'file_name_suggestion',
		// Core text fields.
		't'   => 'title',
		'a'   => 'alt_text',
		'c'   => 'caption',
		'd'   => 'description',
		// Keywords / subjects / attributes.
		'k'   => 'keywords',
		'sj'  => 'subjects',
		'at'  => 'attributes',
		'attr'=> 'attributes',
		// Confidence + issues.
		's'   => 'confidence',
		'conf'=> 'confidence',
		'i'   => 'issues',
		'iss' => 'issues',
	);

	/**
	 * Expand compact keys to verbose names (recursive).
	 *
	 * @param mixed $payload Raw decoded response.
	 * @return mixed Expanded payload.
	 */
	public static function expand_keys( $payload ) {
		if ( ! is_array( $payload ) ) {
			return $payload;
		}

		// Numeric arrays: expand each item.
		if ( ! self::is_assoc( $payload ) ) {
			foreach ( $payload as $idx => $item ) {
				$payload[ $idx ] = self::expand_keys( $item );
			}
			return $payload;
		}

		$expanded = array();

		foreach ( $payload as $key => $value ) {
			$normalized_key = isset( self::$short_to_long[ $key ] )
				? self::$short_to_long[ $key ]
				: $key;

			$expanded[ $normalized_key ] = self::expand_keys( $value );
		}

		return $expanded;
	}

	/**
	 * Determine if array is associative.
	 *
	 * @param array $arr Array to check.
	 * @return bool
	 */
	private static function is_assoc( array $arr ) {
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
