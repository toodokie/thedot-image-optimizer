<?php

if (!defined('ABSPATH')) {
	exit;
}

class MSH_Metadata_Normalizer
{
	// TODO: reuse pollution cleanup for alt, title, caption in future phases.
	// Phase 3B: Description pollution cleanup complete. Titles/alts/captions to be wired when needed using the same normalizer.
	/**
	 * Normalize generated description by stripping location/service pollution
	 * and fixing common punctuation artifacts.
	 *
	 * @param string   $description Description text to normalize.
	 * @param array    $context     Context array.
	 * @param int|bool $seo_mode    SEO mode flag.
	 * @param array    $location_terms Location terms.
	 * @param array    $service_terms  Service terms.
	 *
	 * @return string
	 */
	public function normalize_description($description, $context, $seo_mode, $location_terms = array(), $service_terms = array())
	{
		$description = (string) $description;

		if ($description === '') {
			return $description;
		}

		if (!is_array($location_terms)) {
			$location_terms = array();
		}

		if (!is_array($service_terms)) {
			$service_terms = array();
		}

		$extra_terms = array(
			'practice',
			'medical practice',
			'clinic',
			'healthcare provider',
			'healthcare providers',
			'healthcare service',
			'healthcare services',
			'professional healthcare services',
			'professional healthcare',
			'medical services',
			'medical care',
			'supportive care',
			'support',
			'services',
			'service promotions',
			'Main Street Health',
			'Hamilton, Canada',
			'Hamilton',
			'Canada',
		);

		$pollution_terms = array();

		if (is_array($location_terms)) {
			$pollution_terms = array_merge($pollution_terms, $location_terms);
		}

		if (is_array($service_terms)) {
			$pollution_terms = array_merge($pollution_terms, $service_terms);
		}

		$pollution_terms = array_merge($pollution_terms, $extra_terms);
		$pollution_terms = array_values(array_unique(array_filter($pollution_terms)));

		if (defined('MSH_DEBUG') && MSH_DEBUG) {
			error_log('[MSH POLLUTION] context=' . $context . ' seo=' . (int) $seo_mode);
			error_log('[MSH POLLUTION] location_terms=' . wp_json_encode($location_terms));
			error_log('[MSH POLLUTION] service_terms=' . wp_json_encode($service_terms));
			error_log('[MSH POLLUTION] extra_terms=' . wp_json_encode($extra_terms));
			error_log('[MSH POLLUTION] before_strip=' . $description);
		}

		$clean_description = '';

		if (!empty($pollution_terms)) {
			$sentences = preg_split(
				'/(?<=[.!?])\s+/u',
				$description,
				-1,
				PREG_SPLIT_NO_EMPTY
			);

			if (is_array($sentences) && !empty($sentences)) {
				$kept = array();

				$has_pollution = function ($sentence) use ($pollution_terms) {
					$sentence_lc = mb_strtolower($sentence);
					foreach ($pollution_terms as $term) {
						$term = trim($term);
						if ($term === '') {
							continue;
						}
						if (mb_stripos($sentence_lc, mb_strtolower($term)) !== false) {
							return true;
						}
					}
					return false;
				};

				foreach ($sentences as $sentence) {
					if (!$has_pollution($sentence)) {
						$kept[] = trim($sentence);
					}
				}

				if (!empty($kept)) {
					$clean_description = implode(' ', $kept);
				} else {
					$first_pos = null;
					$lower_full = mb_strtolower($description);

					foreach ($pollution_terms as $term) {
						$term = trim($term);
						if ($term === '') {
							continue;
						}
						$pos = mb_stripos($lower_full, mb_strtolower($term));
						if ($pos !== false) {
							if ($first_pos === null || $pos < $first_pos) {
								$first_pos = $pos;
							}
						}
					}

					if ($first_pos !== null && $first_pos > 0) {
						$prefix = mb_substr($description, 0, $first_pos);
						$cut_pos = $first_pos;
						$last_dot = mb_strrpos($prefix, '.');
						$last_qmark = mb_strrpos($prefix, '?');
						$last_emark = mb_strrpos($prefix, '!');
						$last_comma = mb_strrpos($prefix, ',');

						$boundary_pos = $last_dot;
						if ($last_qmark !== false && ($boundary_pos === false || $last_qmark > $boundary_pos)) {
							$boundary_pos = $last_qmark;
						}
						if ($last_emark !== false && ($boundary_pos === false || $last_emark > $boundary_pos)) {
							$boundary_pos = $last_emark;
						}
						if ($last_comma !== false && ($boundary_pos === false || $last_comma > $boundary_pos)) {
							$boundary_pos = $last_comma;
						}

						if ($boundary_pos !== false && $boundary_pos >= 0) {
							$cut_pos = $boundary_pos + 1;
						}

						$clean_description = trim(mb_substr($description, 0, $cut_pos));
					}
				}
			}
		}

		if ($clean_description === '') {
			$clean_description = $description;
		}

		$clean_description = preg_replace(
			'/\s+in\s+Hamilton,\s*Canada\s*,?/iu',
			'',
			$clean_description
		);

		if (defined('MSH_DEBUG') && MSH_DEBUG) {
			error_log('[MSH POLLUTION] after_sentence_filter=' . $clean_description);
		}

		$clean_description = preg_replace('/\s*,\s*,\s*/', ', ', $clean_description);
		$clean_description = preg_replace('/\s*,\s*/', ', ', $clean_description);
		$clean_description = preg_replace('/\s+/', ' ', $clean_description);

		$clean_description = trim($clean_description);
		$clean_description = trim($clean_description, " \t\n\r\0\x0B,.");

		$clean_description = preg_replace(
			'/\s+\b(in|at|near|to|from|with)\b$/iu',
			'',
			$clean_description
		);

		if ($clean_description === '') {
			return $clean_description;
		}

		$first = mb_substr($clean_description, 0, 1);
		$rest = mb_substr($clean_description, 1);
		$clean_description = mb_strtoupper($first) . $rest;

		if (!preg_match('/[.!?]$/u', $clean_description)) {
			$clean_description .= '.';
		}

		if (defined('MSH_DEBUG') && MSH_DEBUG) {
			error_log('[MSH POLLUTION] final=' . $clean_description);
		}

		return $clean_description;
	}
}
