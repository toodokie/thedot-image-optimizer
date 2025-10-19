<?php
/**
 * Translation Quality Analyzer
 *
 * Analyzes translation quality for multilingual image metadata.
 * Provides hints and suggestions for improving translations.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translation Analyzer Class
 *
 * Evaluates translation coverage, alignment, and quality.
 */
class MSH_Translation_Analyzer {

	/**
	 * I18n metadata manager
	 *
	 * @var MSH_I18n_Metadata
	 */
	private $i18n_metadata;

	/**
	 * Context manager
	 *
	 * @var MSH_Context_Manager
	 */
	private $context_manager;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->i18n_metadata = MSH_I18n_Metadata::get_instance();
		$this->context_manager = MSH_Context_Manager::get_instance();
	}

	/**
	 * Analyze translation quality
	 *
	 * @param int    $media_id      Media attachment ID.
	 * @param string $source_locale Source language locale.
	 * @param string $target_locale Target language locale.
	 * @return array Analysis results.
	 */
	public function analyze_translation( $media_id, $source_locale, $target_locale ) {
		// Get metadata for both locales
		$source_metadata = $this->i18n_metadata->get_metadata( $media_id, $source_locale );
		$target_metadata = $this->i18n_metadata->get_metadata( $media_id, $target_locale );

		// Get context for both locales
		$source_context = $this->context_manager->get_media_rollup( $media_id, $source_locale );
		$target_context = $this->context_manager->get_media_rollup( $media_id, $target_locale );

		$analysis = array(
			'source_locale' => $source_locale,
			'target_locale' => $target_locale,
			'has_source'    => (bool) $source_metadata,
			'has_target'    => (bool) $target_metadata,
			'keyword_coverage' => 0,
			'context_alignment' => 0,
			'missing_keywords' => array(),
			'suggestions' => array(),
			'quality_score' => 0,
		);

		// If no target metadata exists, return early
		if ( ! $target_metadata ) {
			$analysis['suggestions'][] = sprintf(
				/* translators: %s: target locale */
				__( 'No metadata found for %s. Generate or translate metadata first.', 'msh-image-optimizer' ),
				$target_locale
			);
			return $analysis;
		}

		// Analyze keyword coverage
		if ( $source_context && $target_context ) {
			$keyword_coverage = $this->calculate_keyword_coverage(
				$source_context['top_keywords'],
				$target_context['top_keywords']
			);

			$analysis['keyword_coverage'] = $keyword_coverage['coverage'];
			$analysis['missing_keywords'] = $keyword_coverage['missing'];
		}

		// Analyze context alignment
		if ( $source_context && $target_context ) {
			$analysis['context_alignment'] = $this->calculate_context_alignment(
				$source_context,
				$target_context
			);
		}

		// Generate suggestions
		$analysis['suggestions'] = $this->generate_suggestions(
			$media_id,
			$source_metadata,
			$target_metadata,
			$source_context,
			$target_context,
			$analysis
		);

		// Calculate overall quality score
		$analysis['quality_score'] = $this->calculate_quality_score( $analysis );

		return $analysis;
	}

	/**
	 * Calculate keyword coverage
	 *
	 * @param array $source_keywords Source keywords.
	 * @param array $target_keywords Target keywords.
	 * @return array Coverage data.
	 */
	private function calculate_keyword_coverage( $source_keywords, $target_keywords ) {
		if ( empty( $source_keywords ) ) {
			return array(
				'coverage' => 1.0,
				'missing'  => array(),
			);
		}

		// For simplicity, we can't directly compare keywords across languages
		// Instead, we check if target has similar number of keywords
		$source_count = count( $source_keywords );
		$target_count = count( $target_keywords );

		$coverage = min( 1.0, $target_count / $source_count );

		$missing = array();
		if ( $target_count < $source_count ) {
			$missing_count = $source_count - $target_count;
			$missing = array_slice( $source_keywords, 0, $missing_count );
		}

		return array(
			'coverage' => $coverage,
			'missing'  => $missing,
		);
	}

	/**
	 * Calculate context alignment
	 *
	 * How well do the contexts match between locales?
	 *
	 * @param array $source_context Source context rollup.
	 * @param array $target_context Target context rollup.
	 * @return float Alignment score 0-1.
	 */
	private function calculate_context_alignment( $source_context, $target_context ) {
		$score = 0.0;
		$factors = 0;

		// Intent alignment (40%)
		if ( $source_context['intent'] === $target_context['intent'] ) {
			$score += 0.4;
		}
		$factors++;

		// Usage count similarity (30%)
		$source_uses = $source_context['total_uses'];
		$target_uses = $target_context['total_uses'];

		if ( $source_uses > 0 && $target_uses > 0 ) {
			$use_ratio = min( $source_uses, $target_uses ) / max( $source_uses, $target_uses );
			$score += $use_ratio * 0.3;
		}
		$factors++;

		// Context score similarity (30%)
		$score_diff = abs( $source_context['avg_context_score'] - $target_context['avg_context_score'] );
		$score_similarity = 1 - ( $score_diff / 100 );
		$score += $score_similarity * 0.3;
		$factors++;

		return $score;
	}

	/**
	 * Generate suggestions for improvement
	 *
	 * @param int        $media_id        Media ID.
	 * @param array|null $source_metadata Source metadata.
	 * @param array|null $target_metadata Target metadata.
	 * @param array|null $source_context  Source context.
	 * @param array|null $target_context  Target context.
	 * @param array      $analysis        Current analysis.
	 * @return array List of suggestions.
	 */
	private function generate_suggestions( $media_id, $source_metadata, $target_metadata, $source_context, $target_context, $analysis ) {
		$suggestions = array();

		// Check if alt text is missing
		if ( empty( $target_metadata['alt_text'] ) ) {
			$suggestions[] = sprintf(
				/* translators: %s: target locale */
				__( 'Alt text is missing for %s locale.', 'msh-image-optimizer' ),
				$analysis['target_locale']
			);
		}

		// Check if alt text is too short
		if ( ! empty( $target_metadata['alt_text'] ) && strlen( $target_metadata['alt_text'] ) < 20 ) {
			$suggestions[] = __( 'Alt text is very short. Consider making it more descriptive.', 'msh-image-optimizer' );
		}

		// Check keyword coverage
		if ( $analysis['keyword_coverage'] < 0.5 ) {
			$suggestions[] = sprintf(
				/* translators: %d: coverage percentage */
				__( 'Low keyword coverage (%d%%). Target translation may be missing important keywords.', 'msh-image-optimizer' ),
				round( $analysis['keyword_coverage'] * 100 )
			);
		}

		// Check missing keywords
		if ( ! empty( $analysis['missing_keywords'] ) ) {
			$suggestions[] = sprintf(
				/* translators: %s: comma-separated list of keywords */
				__( 'Consider translating these keywords: %s', 'msh-image-optimizer' ),
				implode( ', ', array_slice( $analysis['missing_keywords'], 0, 5 ) )
			);
		}

		// Check context alignment
		if ( $analysis['context_alignment'] < 0.6 ) {
			$suggestions[] = __( 'Context alignment is low. The image may be used differently across locales.', 'msh-image-optimizer' );
		}

		// Check if metadata needs approval
		if ( ! empty( $target_metadata['generated_at'] ) && empty( $target_metadata['approved'] ) ) {
			$suggestions[] = __( 'Translation was auto-generated. Please review and approve.', 'msh-image-optimizer' );
		}

		// Cultural considerations
		if ( $source_context && $target_context ) {
			if ( $source_context['intent'] !== $target_context['intent'] ) {
				$suggestions[] = __( 'Different intent classifications across locales. Consider cultural context differences.', 'msh-image-optimizer' );
			}
		}

		return $suggestions;
	}

	/**
	 * Calculate overall quality score
	 *
	 * @param array $analysis Analysis data.
	 * @return int Quality score 0-100.
	 */
	private function calculate_quality_score( $analysis ) {
		$score = 0;

		// Has target metadata (30 points)
		if ( $analysis['has_target'] ) {
			$score += 30;
		}

		// Keyword coverage (35 points)
		$score += $analysis['keyword_coverage'] * 35;

		// Context alignment (35 points)
		$score += $analysis['context_alignment'] * 35;

		return min( 100, max( 0, round( $score ) ) );
	}

	/**
	 * Get translation status for all locales
	 *
	 * @param int $media_id Media attachment ID.
	 * @return array Status keyed by locale.
	 */
	public function get_translation_status( $media_id ) {
		$available_locales = $this->i18n_metadata->get_available_locales( $media_id );
		$active_locales = $this->i18n_metadata->get_active_locales();

		$status = array();

		foreach ( $active_locales as $locale ) {
			$metadata = $this->i18n_metadata->get_metadata( $media_id, $locale );

			$status[ $locale ] = array(
				'exists'       => (bool) $metadata,
				'approved'     => $metadata && ! empty( $metadata['approved'] ),
				'generated_at' => $metadata ? $metadata['generated_at'] : null,
				'alt_text'     => $metadata ? $metadata['alt_text'] : null,
			);
		}

		return $status;
	}
}
