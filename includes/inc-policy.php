<?php
/**
 * Shared policy helpers for metadata generation and validation.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether the current business context is healthcare-oriented.
 *
 * @param array $biz Business context payload.
 * @param array $ctx Optional context payload.
 * @return bool
 */
function msh_is_healthcare( array $biz, array $ctx = array() ): bool {
	$industry = strtolower( $biz['industry'] ?? $biz['vertical'] ?? get_option( 'msh_industry', '' ) );
	return in_array( $industry, array( 'medical', 'healthcare', 'dental', 'physio', 'chiro' ), true );
}

/**
 * Canonicalise context types (e.g. clinical -> business when not healthcare).
 *
 * @param array $ctx Context payload.
 * @param array $biz Business context payload.
 * @return string Canonical context type.
 */
function msh_canonicalize_ct( array $ctx, array $biz ): string {
	$context_type = $ctx['context_type'] ?? 'stock';
	if ( 'clinical' === $context_type && ! msh_is_healthcare( $biz, $ctx ) ) {
		return 'business';
	}
	return $context_type;
}

/**
 * Determine if branding is permitted for a given context.
 *
 * @param string $context_type Context type.
 * @param array  $ctx          Context payload.
 * @return bool
 */
function msh_brand_permitted( string $context_type, array $ctx ): bool {
	if ( in_array( $context_type, array( 'brand_logo', 'team', 'facility', 'equipment', 'service-icon' ), true ) ) {
		return true;
	}

	if ( in_array( $context_type, array( 'clinical', 'business', 'testimonial' ), true ) ) {
		return ! empty( $ctx['brand_name_visible'] );
	}

	return false;
}

/**
 * Whether a given context is allowed to carry UVP clauses.
 *
 * @param string $context_type Context type.
 * @return bool
 */
function msh_ct_allows_uvp( string $context_type ): bool {
	return in_array( $context_type, array( 'business', 'clinical', 'facility', 'testimonial' ), true );
}

/**
 * Clamp UVP copy to a single concise sentence.
 *
 * @param string $uvp Raw UVP text.
 * @param int    $max Maximum character width.
 * @return string
 */
function msh_clamp_uvp( string $uvp, int $max = 120 ): string {
	$uvp = trim( wp_strip_all_tags( (string) $uvp ) );
	if ( '' === $uvp ) {
		return '';
	}

	$sentence = preg_split( '/(?<=[.!?])\s+/u', $uvp, 2 )[0] ?? $uvp;

	if ( mb_strwidth( $sentence ) > $max ) {
		$sentence = preg_replace( '/[,;].*/u', '', $sentence );
		if ( mb_strwidth( $sentence ) > $max ) {
			$sentence = mb_substr( $sentence, 0, $max );
			$sentence = preg_replace( '/\s+\S*$/u', '', $sentence );
		}
	}

	$sentence = trim( preg_replace( '/\s{2,}/', ' ', $sentence ) );
	return rtrim( $sentence, " .\t\r\n" ) . '.';
}

/**
 * Enforce maximum sentence count.
 *
 * @param string $text Text to normalise.
 * @param int    $max_sentences Maximum number of sentences allowed.
 * @return string
 */
function msh_limit_sentences( string $text, int $max_sentences = 2 ): string {
	$text   = trim( (string) $text );
	$chunks = preg_split( '/(?<=[.!?])\s+/u', $text );
	$chunks = array_slice(
		array_filter(
			array_map(
				static function ( $sentence ) {
					$sentence = trim( (string) $sentence );
					return '' === $sentence ? null : rtrim( $sentence, " \t\r\n" );
				},
				(array) $chunks
			)
		),
		0,
		$max_sentences
	);

	return implode(
		' ',
		array_map(
			static function ( $sentence ) {
				return rtrim( $sentence, ' .!?;' ) . '.';
			},
			$chunks
		)
	);
}

/**
 * Clamp arbitrary text to a maximum length.
 *
 * @param string $text           Input text.
 * @param int    $limit          Character limit.
 * @param bool   $preserve_words Whether to avoid breaking words.
 * @return string
 */
function msh_clamp_length( string $text, int $limit = 60, bool $preserve_words = true ): string {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}

	$clamped = mb_substr( $text, 0, $limit );
	if ( $preserve_words ) {
		$clamped = preg_replace( '/\s+\S*$/u', '', $clamped );
	}

	return trim( $clamped );
}
