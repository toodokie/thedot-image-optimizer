<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( $text );
	}
}

require_once dirname( __DIR__ ) . '/includes/inc-policy.php';

class PolicyParityTest extends TestCase {

	public function test_limit_sentences_caps_at_two() {
		$text = 'Scene sentence one. UVP sentence two. Extra sentence three.';
		$this->assertSame(
			'Scene sentence one. UVP sentence two.',
			msh_limit_sentences( $text, 2 ),
			'Expected the helper to clamp to two sentences.'
		);
	}

	public function test_clamp_length_truncates_without_breaking_words() {
		$text     = 'Main Street Health welcomes the community with compassionate care.';
		$clamped  = msh_clamp_length( $text, 35 );
		$this->assertLessThanOrEqual( 35, mb_strlen( $clamped ), 'Clamped string should respect the limit.' );
		$this->assertStringEndsNotWith( ' ', $clamped );
	}
}
