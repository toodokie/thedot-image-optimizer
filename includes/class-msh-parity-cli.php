<?php
/**
 * WP-CLI parity harness for deterministic vs AI metadata.
 *
 * @package MSH_Image_Optimizer
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class MSH_Parity_CLI {

	/**
	 * Run the deterministic composer across all fixtures and output a matrix.
	 *
	 * ## OPTIONS
	 *
	 * [--fixtures=<path>]
	 * : Optional path to a fixtures JSON file. Defaults to tests/fixtures/msh-parity-fixtures.json.
	 *
	 * [--format=<format>]
	 * : Either table (default) or json.
	 */
	public function analyze_matrix( $args, $assoc_args ) {
		$fixtures = $this->load_fixtures( $assoc_args['fixtures'] ?? null );
		$format   = $assoc_args['format'] ?? 'table';
		$rows     = array();

		foreach ( $fixtures as $fixture ) {
			$context    = $this->build_context_from_fixture( $fixture );
			$loc_mode   = $fixture['policy']['loc_mode'] ?? 'auto';
			$metadata   = $this->compose_deterministic( $fixture );
			$validated  = $this->validator()->validate( $context, $metadata, $context['seo_mode'], $loc_mode );
			$rows[] = array(
				'id'        => $fixture['id'],
				'context'   => $context['final_context_type'],
				'seo_mode'  => $context['seo_mode'] ? 'on' : 'off',
				'loc_mode'  => $loc_mode,
				'title'     => $validated['title'] ?? '',
				'description' => $validated['description'] ?? '',
			);
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $rows, JSON_PRETTY_PRINT ) );
			return;
		}

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			array( 'id', 'context', 'seo_mode', 'loc_mode', 'title', 'description' )
		);
		$formatter->display_items( $rows );
	}

	/**
	 * Verify AI fixtures against deterministic invariants.
	 *
	 * ## OPTIONS
	 *
	 * [--fixtures=<path>]
	 * : Optional path to a fixtures JSON file. Defaults to tests/fixtures/msh-parity-fixtures.json.
	 */
	public function verify_attachments( $args, $assoc_args ) {
		$fixtures = $this->load_fixtures( $assoc_args['fixtures'] ?? null );
		$failures = 0;

		foreach ( $fixtures as $fixture ) {
			$context  = $this->build_context_from_fixture( $fixture );
			$loc_mode = $fixture['policy']['loc_mode'] ?? 'auto';

			$this->compose_deterministic( $fixture ); // Warm up composer to surface issues even if AI missing.

			$ai_payload = $fixture['ai'] ?? array();
			if ( empty( $ai_payload ) ) {
				WP_CLI::warning( sprintf( '[Fixture %s] Skipped — missing AI payload.', $fixture['id'] ) );
				continue;
			}

			$validated = $this->validator()->validate( $context, $ai_payload, $context['seo_mode'], $loc_mode );
			$errors    = $this->assert_invariants( $context, $validated );

			if ( ! empty( $errors ) ) {
				$failures ++;
				WP_CLI::warning( sprintf( '[Fixture %s] %d policy violations:', $fixture['id'], count( $errors ) ) );
				foreach ( $errors as $error ) {
					WP_CLI::line( '  - ' . $error );
				}
			} else {
				WP_CLI::success( sprintf( '[Fixture %s] Passed parity invariants.', $fixture['id'] ) );
			}
		}

		if ( $failures > 0 ) {
			WP_CLI::error( sprintf( 'Parity check failed (%d fixture(s)).', $failures ) );
		}
	}

	/**
	 * Compose deterministic metadata for a fixture.
	 *
	 * @param array $fixture Fixture payload.
	 * @return array
	 */
	private function compose_deterministic( array $fixture ): array {
		if ( ! class_exists( 'MSH_NonAI_Composer' ) ) {
			WP_CLI::error( 'MSH_NonAI_Composer is not loaded.' );
		}

		$input = array(
			'id'          => $fixture['id'],
			'filename'    => $fixture['filename'],
			'biz_context' => $fixture['biz_context'] ?? array(),
			'page_context'=> $fixture['page_context'] ?? array(),
			'policy'      => $fixture['policy'] ?? array(),
		);

		return MSH_NonAI_Composer::compose( $input );
	}

	/**
	 * Normalize fixture data into the context array expected by the validator.
	 *
	 * @param array $fixture Fixture payload.
	 * @return array
	 */
	private function build_context_from_fixture( array $fixture ): array {
		$policy = $fixture['policy'] ?? array();
		$biz    = $fixture['biz_context'] ?? array();

		return array_merge(
			$biz,
			array(
				'business_name'      => $biz['business_name'] ?? '',
				'industry'           => $biz['industry'] ?? '',
				'industry_label'     => $biz['industry_label'] ?? ( $biz['industry'] ?? '' ),
				'brand_name_visible' => ! empty( $policy['brand_name_visible'] ),
				'type'               => $policy['context_type'] ?? 'stock',
				'final_context_type' => $policy['context_type'] ?? 'stock',
				'seo_mode'           => ! empty( $policy['seo_mode'] ),
				'loc_mode'           => $policy['loc_mode'] ?? 'auto',
				'policy'             => array(
					'loc_mode' => $policy['loc_mode'] ?? 'auto',
				),
			)
		);
	}

	/**
	 * Shared validator accessor.
	 *
	 * @return MSH_Context_Aware_Validator
	 */
	private function validator(): MSH_Context_Aware_Validator {
		return MSH_Context_Aware_Validator::get_instance();
	}

	/**
	 * Load fixtures from disk.
	 *
	 * @param string|null $path Optional override path.
	 * @return array
	 */
	private function load_fixtures( ?string $path ): array {
		$default = trailingslashit( MSH_IO_PLUGIN_DIR ) . 'tests/fixtures/msh-parity-fixtures.json';
		$target  = $path ? $path : $default;

		if ( ! file_exists( $target ) ) {
			WP_CLI::error( sprintf( 'Fixture file not found: %s', $target ) );
		}

		$data = json_decode( file_get_contents( $target ), true );
		if ( ! is_array( $data ) ) {
			WP_CLI::error( sprintf( 'Invalid fixture JSON at %s', $target ) );
		}

		return $data;
	}

	/**
	 * Assert invariants on validator output.
	 *
	 * @param array $context  Context array.
	 * @param array $metadata Metadata array.
	 * @return array List of error strings.
	 */
	private function assert_invariants( array $context, array $metadata ): array {
		$errors         = array();
		$location_terms = $this->collect_location_terms( $context );

		if ( ! empty( $metadata['title'] ) && mb_strlen( $metadata['title'] ) > 60 ) {
			$errors[] = 'Title exceeds 60 characters.';
		}

		if ( ! empty( $metadata['description'] ) && $this->count_sentences( $metadata['description'] ) > 2 ) {
			$errors[] = 'Description exceeds two sentences.';
		}

		if ( ! empty( $metadata['alt_text'] ) && $this->contains_location_term( $metadata['alt_text'], $location_terms ) ) {
			$errors[] = 'ALT text contains geo terms.';
		}

		$errors = array_merge( $errors, $this->assert_loc_mode_matrix( $context, $metadata, $location_terms ) );

		return $errors;
	}

	/**
	 * Enforce the location placement matrix.
	 *
	 * @param array $context        Context array.
	 * @param array $metadata       Metadata array.
	 * @param array $location_terms Known location terms.
	 * @return array
	 */
	private function assert_loc_mode_matrix( array $context, array $metadata, array $location_terms ): array {
		if ( empty( $location_terms ) ) {
			return array();
		}

		$errors   = array();
		$seo_mode = ! empty( $context['seo_mode'] );
		$loc_mode = $context['loc_mode'] ?? 'auto';
		$type     = $context['final_context_type'] ?? ( $context['type'] ?? 'stock' );

		if ( ! $seo_mode || 'off' === $loc_mode ) {
			$allowed = array();
		} elseif ( 'force_all' === $loc_mode ) {
			$allowed = array( 'description', 'title' );
			if ( in_array( $type, array( 'facility', 'service-icon' ), true ) ) {
				$allowed[] = 'filename_slug';
			}
		} else {
			// auto + force_caption
			$allowed = array( 'description' );
		}

		foreach ( array( 'title', 'caption', 'description', 'filename_slug' ) as $field ) {
			if ( empty( $metadata[ $field ] ) ) {
				continue;
			}

			if ( $this->contains_location_term( $metadata[ $field ], $location_terms ) && ! in_array( $field, $allowed, true ) ) {
				$errors[] = sprintf( '%s contains geo terms but loc_mode=%s (seo=%s) forbids it.', $field, $loc_mode, $seo_mode ? 'on' : 'off' );
			}
		}

		return $errors;
	}

	/**
	 * Collect location terms from context.
	 *
	 * @param array $context Context array.
	 * @return array
	 */
	private function collect_location_terms( array $context ): array {
		$keys  = array( 'city', 'region', 'country', 'service_area' );
		$terms = array();

		foreach ( $keys as $key ) {
			if ( ! empty( $context[ $key ] ) ) {
				$terms[] = strtolower( $context[ $key ] );
			}
		}

		return array_values( array_unique( array_filter( $terms ) ) );
	}

	/**
	 * Check if a text contains any of the supplied terms.
	 *
	 * @param string $text  Text to inspect.
	 * @param array  $terms Terms to search for.
	 * @return bool
	 */
	private function contains_location_term( string $text, array $terms ): bool {
		$haystack = strtolower( $text );
		foreach ( $terms as $term ) {
			if ( '' !== $term && false !== strpos( $haystack, $term ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Basic sentence counter for invariant checks.
	 *
	 * @param string $text Input text.
	 * @return int
	 */
	private function count_sentences( string $text ): int {
		$text = trim( $text );
		if ( '' === $text ) {
			return 0;
		}

		$parts = preg_split( '/(?<=[.!?])\s+/u', $text );
		$parts = array_filter(
			array_map(
				static function ( $sentence ) {
					return trim( (string) $sentence );
				},
				(array) $parts
			)
		);

		return count( $parts );
	}
}
