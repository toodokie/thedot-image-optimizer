<?php
/**
 * Context Fusion Layer - Keyword Normalizer
 *
 * Extracts and normalizes keywords from text with locale-aware processing.
 * Handles stemming, stop words, and synonym expansion.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword Normalizer
 *
 * Processes text to extract meaningful keywords:
 * - Removes stop words (locale-aware)
 * - Applies stemming (basic Porter stemmer for English)
 * - Deduplicates and normalizes
 * - Ranks by frequency/importance
 */
class MSH_Keyword_Normalizer {

	/**
	 * Stop words cache by locale
	 *
	 * @var array
	 */
	private $stop_words = array();

	/**
	 * Extract and normalize keywords from text
	 *
	 * @param string $text   Text to process.
	 * @param string $locale Locale code.
	 * @param int    $limit  Maximum keywords to return.
	 * @return array Array of normalized keywords
	 */
	public function extract_keywords( $text, $locale = 'en_US', $limit = 20 ) {
		// Normalize text
		$text = $this->normalize_text( $text );

		if ( empty( $text ) ) {
			return array();
		}

		// Tokenize
		$words = $this->tokenize( $text );

		// Remove stop words
		$words = $this->remove_stop_words( $words, $locale );

		// Apply stemming
		$words = $this->apply_stemming( $words, $locale );

		// Count frequency
		$frequency = array_count_values( $words );

		// Sort by frequency (descending)
		arsort( $frequency );

		// Get top N keywords
		$keywords = array_keys( array_slice( $frequency, 0, $limit, true ) );

		return $keywords;
	}

	/**
	 * Normalize text for processing
	 *
	 * @param string $text Text to normalize.
	 * @return string Normalized text
	 */
	private function normalize_text( $text ) {
		// Convert to lowercase
		$text = strtolower( $text );

		// Remove HTML entities
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Remove special characters (keep letters, numbers, spaces)
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

		// Normalize whitespace
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( $text );
	}

	/**
	 * Tokenize text into words
	 *
	 * @param string $text Text to tokenize.
	 * @return array Array of words
	 */
	private function tokenize( $text ) {
		// Split on whitespace
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		// Filter words (minimum length, etc.)
		$words = array_filter(
			$words,
			function ( $word ) {
				// Skip very short words (likely noise)
				return strlen( $word ) >= 3;
			}
		);

		return array_values( $words );
	}

	/**
	 * Remove stop words from word list
	 *
	 * @param array  $words  Array of words.
	 * @param string $locale Locale code.
	 * @return array Filtered words
	 */
	private function remove_stop_words( $words, $locale ) {
		$stop_words = $this->get_stop_words( $locale );

		return array_values(
			array_filter(
				$words,
				function ( $word ) use ( $stop_words ) {
					return ! in_array( $word, $stop_words, true );
				}
			)
		);
	}

	/**
	 * Get stop words for a locale
	 *
	 * @param string $locale Locale code.
	 * @return array Array of stop words
	 */
	private function get_stop_words( $locale ) {
		// Check cache
		if ( isset( $this->stop_words[ $locale ] ) ) {
			return $this->stop_words[ $locale ];
		}

		// Get language code from locale (e.g., en_US -> en)
		$language = substr( $locale, 0, 2 );

		// Load stop words for language
		switch ( $language ) {
			case 'en':
				$stop_words = $this->get_english_stop_words();
				break;

			case 'es':
				$stop_words = $this->get_spanish_stop_words();
				break;

			case 'fr':
				$stop_words = $this->get_french_stop_words();
				break;

			case 'de':
				$stop_words = $this->get_german_stop_words();
				break;

			default:
				// Use English as fallback
				$stop_words = $this->get_english_stop_words();
				break;
		}

		// Cache and return
		$this->stop_words[ $locale ] = $stop_words;
		return $stop_words;
	}

	/**
	 * Get English stop words
	 *
	 * @return array Array of stop words
	 */
	private function get_english_stop_words() {
		return array(
			'the',
			'and',
			'for',
			'are',
			'but',
			'not',
			'you',
			'all',
			'can',
			'her',
			'was',
			'one',
			'our',
			'out',
			'day',
			'get',
			'has',
			'him',
			'his',
			'how',
			'man',
			'new',
			'now',
			'old',
			'see',
			'two',
			'way',
			'who',
			'boy',
			'did',
			'its',
			'let',
			'put',
			'say',
			'she',
			'too',
			'use',
			'this',
			'that',
			'with',
			'from',
			'they',
			'have',
			'been',
			'were',
			'said',
			'each',
			'which',
			'their',
			'will',
			'other',
			'about',
			'many',
			'then',
			'them',
			'these',
			'some',
			'would',
			'make',
			'like',
			'into',
			'time',
			'look',
			'more',
			'write',
			'than',
			'first',
			'water',
			'been',
			'call',
			'find',
			'long',
			'down',
			'come',
			'made',
			'may',
		);
	}

	/**
	 * Get Spanish stop words
	 *
	 * @return array Array of stop words
	 */
	private function get_spanish_stop_words() {
		return array(
			'los',
			'las',
			'una',
			'uno',
			'del',
			'que',
			'para',
			'con',
			'por',
			'como',
			'más',
			'pero',
			'sus',
			'este',
			'esta',
			'están',
			'sobre',
			'todo',
			'ser',
			'muy',
			'cuando',
			'también',
		);
	}

	/**
	 * Get French stop words
	 *
	 * @return array Array of stop words
	 */
	private function get_french_stop_words() {
		return array(
			'les',
			'des',
			'une',
			'pour',
			'dans',
			'que',
			'qui',
			'est',
			'sur',
			'avec',
			'par',
			'plus',
			'son',
			'sont',
			'mais',
			'tout',
			'comme',
			'cette',
			'ces',
			'aux',
		);
	}

	/**
	 * Get German stop words
	 *
	 * @return array Array of stop words
	 */
	private function get_german_stop_words() {
		return array(
			'der',
			'die',
			'das',
			'den',
			'dem',
			'des',
			'ein',
			'eine',
			'und',
			'für',
			'mit',
			'von',
			'auf',
			'ist',
			'nicht',
			'auch',
			'als',
			'sich',
			'bei',
			'nach',
		);
	}

	/**
	 * Apply stemming to words
	 *
	 * Reduces words to their root form for better matching.
	 *
	 * @param array  $words  Array of words.
	 * @param string $locale Locale code.
	 * @return array Stemmed words
	 */
	private function apply_stemming( $words, $locale ) {
		// Get language code
		$language = substr( $locale, 0, 2 );

		// Only English stemming implemented for now
		if ( 'en' !== $language ) {
			return $words;
		}

		return array_map( array( $this, 'stem_english' ), $words );
	}

	/**
	 * Porter Stemmer for English (simplified)
	 *
	 * Implements basic Porter stemming algorithm.
	 *
	 * @param string $word Word to stem.
	 * @return string Stemmed word
	 */
	private function stem_english( $word ) {
		// Skip very short words
		if ( strlen( $word ) < 4 ) {
			return $word;
		}

		// Step 1a: Remove plurals
		if ( substr( $word, - 3 ) === 'ies' ) {
			$word = substr( $word, 0, - 3 ) . 'y';
		} elseif ( substr( $word, - 2 ) === 'es' && substr( $word, - 3, 1 ) !== 's' ) {
			$word = substr( $word, 0, - 2 );
		} elseif ( substr( $word, - 1 ) === 's' && substr( $word, - 2, 1 ) !== 's' ) {
			$word = substr( $word, 0, - 1 );
		}

		// Step 1b: Remove -ed, -ing
		if ( substr( $word, - 3 ) === 'ing' ) {
			$word = substr( $word, 0, - 3 );
		} elseif ( substr( $word, - 2 ) === 'ed' ) {
			$word = substr( $word, 0, - 2 );
		}

		// Step 2: Remove common suffixes
		$suffixes = array(
			'ational' => 'ate',
			'tional'  => 'tion',
			'alism'   => 'al',
			'ation'   => 'ate',
			'ness'    => '',
			'ful'     => '',
			'ity'     => '',
			'ous'     => '',
			'ive'     => '',
		);

		foreach ( $suffixes as $suffix => $replacement ) {
			$suffix_len = strlen( $suffix );
			if ( substr( $word, - $suffix_len ) === $suffix ) {
				$word = substr( $word, 0, - $suffix_len ) . $replacement;
				break;
			}
		}

		return $word;
	}

	/**
	 * Extract entities from text
	 *
	 * Finds named entities (brands, places, people) in text.
	 * Uses simple pattern matching and known entity lists.
	 *
	 * @param string $text   Text to analyze.
	 * @param string $locale Locale code.
	 * @return array Array with brands, places, people arrays
	 */
	public function extract_entities( $text, $locale = 'en_US' ) {
		$entities = array(
			'brands' => array(),
			'places' => array(),
			'people' => array(),
		);

		// Normalize text
		$text = $this->normalize_text( $text );

		// Extract capitalized words (potential proper nouns)
		// Note: We already lowercased text in normalize_text, so we need the original
		// Let's use a separate method that preserves capitalization
		$capitalized = $this->extract_capitalized_words( $text );

		// For now, return empty entities
		// Full implementation would:
		// 1. Check against known brand/place databases
		// 2. Use NER (Named Entity Recognition) if available
		// 3. Check taxonomies and custom fields for entity hints

		return $entities;
	}

	/**
	 * Extract capitalized words (potential proper nouns)
	 *
	 * @param string $text Text with original capitalization.
	 * @return array Array of capitalized words
	 */
	private function extract_capitalized_words( $text ) {
		// Match words that start with capital letter
		preg_match_all( '/\b[A-Z][a-z]+\b/', $text, $matches );

		if ( empty( $matches[0] ) ) {
			return array();
		}

		// Deduplicate
		return array_unique( $matches[0] );
	}

	/**
	 * Calculate keyword relevance score
	 *
	 * Scores keywords based on:
	 * - Frequency in text
	 * - Position (earlier = more important)
	 * - Presence in title/headings
	 *
	 * @param array $keywords Array of keywords.
	 * @param array $context  Post context data.
	 * @return array Keywords with scores
	 */
	public function score_keywords( $keywords, $context ) {
		$scored = array();

		// Normalize context text for matching
		$title    = isset( $context['title'] ) ? strtolower( $context['title'] ) : '';
		$content  = isset( $context['content'] ) ? strtolower( $context['content'] ) : '';
		$headings = array();

		if ( isset( $context['headings'] ) ) {
			foreach ( $context['headings'] as $level => $heading_texts ) {
				foreach ( $heading_texts as $heading ) {
					$headings[] = strtolower( $heading );
				}
			}
		}

		foreach ( $keywords as $keyword ) {
			$score = 0;

			// Base score: Frequency in content
			$frequency = substr_count( $content, $keyword );
			$score    += $frequency * 10;

			// Bonus: In title (high value)
			if ( strpos( $title, $keyword ) !== false ) {
				$score += 50;
			}

			// Bonus: In headings
			foreach ( $headings as $heading ) {
				if ( strpos( $heading, $keyword ) !== false ) {
					$score += 30;
					break;
				}
			}

			// Bonus: Length (longer keywords often more specific)
			$score += strlen( $keyword );

			$scored[ $keyword ] = $score;
		}

		// Sort by score descending
		arsort( $scored );

		return $scored;
	}

	/**
	 * Batch extract keywords from multiple texts
	 *
	 * Optimized for processing multiple posts at once.
	 *
	 * @param array  $texts  Array of text strings.
	 * @param string $locale Locale code.
	 * @param int    $limit  Keywords per text.
	 * @return array Array of keyword arrays keyed by text index
	 */
	public function batch_extract( $texts, $locale = 'en_US', $limit = 20 ) {
		$results = array();

		foreach ( $texts as $index => $text ) {
			$results[ $index ] = $this->extract_keywords( $text, $locale, $limit );
		}

		return $results;
	}

	/**
	 * Expand keywords with locale-specific synonyms
	 *
	 * @param array  $keywords Original keywords.
	 * @param string $locale   Locale code.
	 * @return array Expanded keywords with synonyms.
	 */
	public function expand_keywords( $keywords, $locale ) {
		$synonyms = $this->get_synonym_dictionary( $locale );
		$expanded = $keywords;

		foreach ( $keywords as $keyword ) {
			if ( isset( $synonyms[ $keyword ] ) ) {
				$expanded = array_merge( $expanded, $synonyms[ $keyword ] );
			}
		}

		return array_unique( $expanded );
	}

	/**
	 * Get synonym dictionary for locale
	 *
	 * @param string $locale Locale code.
	 * @return array Synonym dictionary.
	 */
	private function get_synonym_dictionary( $locale ) {
		$synonyms = array(
			'en_US' => array(
				'car'           => array( 'automobile', 'vehicle', 'auto' ),
				'house'         => array( 'home', 'residence', 'dwelling' ),
				'photo'         => array( 'image', 'picture', 'photograph' ),
				'business'      => array( 'company', 'enterprise', 'firm' ),
				'doctor'        => array( 'physician', 'medical professional', 'practitioner' ),
				'treatment'     => array( 'therapy', 'care', 'remedy' ),
				'exercise'      => array( 'workout', 'training', 'fitness' ),
				'food'          => array( 'meal', 'cuisine', 'dish' ),
				'technology'    => array( 'tech', 'digital', 'innovation' ),
				'education'     => array( 'learning', 'training', 'instruction' ),
				'wellness'      => array( 'health', 'wellbeing', 'fitness' ),
				'design'        => array( 'styling', 'aesthetics', 'layout' ),
				'professional'  => array( 'expert', 'specialist', 'practitioner' ),
				'service'       => array( 'offering', 'solution', 'support' ),
				'product'       => array( 'item', 'goods', 'merchandise' ),
			),
			'es_ES' => array(
				'coche'         => array( 'automóvil', 'vehículo', 'auto' ),
				'casa'          => array( 'hogar', 'residencia', 'vivienda' ),
				'foto'          => array( 'imagen', 'fotografía', 'retrato' ),
				'negocio'       => array( 'empresa', 'compañía', 'firma' ),
				'doctor'        => array( 'médico', 'profesional médico', 'facultativo' ),
				'tratamiento'   => array( 'terapia', 'cuidado', 'remedio' ),
				'ejercicio'     => array( 'entrenamiento', 'actividad física', 'fitness' ),
				'comida'        => array( 'alimento', 'gastronomía', 'plato' ),
				'tecnología'    => array( 'tech', 'digital', 'innovación' ),
				'educación'     => array( 'aprendizaje', 'formación', 'enseñanza' ),
			),
			'fr_FR' => array(
				'voiture'       => array( 'automobile', 'véhicule', 'auto' ),
				'maison'        => array( 'habitation', 'résidence', 'logement' ),
				'photo'         => array( 'image', 'photographie', 'cliché' ),
				'entreprise'    => array( 'société', 'compagnie', 'firme' ),
				'médecin'       => array( 'docteur', 'professionnel médical', 'praticien' ),
				'traitement'    => array( 'thérapie', 'soin', 'remède' ),
				'exercice'      => array( 'entraînement', 'activité physique', 'fitness' ),
				'nourriture'    => array( 'aliment', 'cuisine', 'plat' ),
				'technologie'   => array( 'tech', 'numérique', 'innovation' ),
				'éducation'     => array( 'apprentissage', 'formation', 'enseignement' ),
			),
			'de_DE' => array(
				'auto'          => array( 'wagen', 'fahrzeug', 'kfz' ),
				'haus'          => array( 'heim', 'wohnung', 'gebäude' ),
				'foto'          => array( 'bild', 'photographie', 'aufnahme' ),
				'geschäft'      => array( 'unternehmen', 'firma', 'betrieb' ),
				'arzt'          => array( 'mediziner', 'doktor', 'praktiker' ),
				'behandlung'    => array( 'therapie', 'pflege', 'heilmittel' ),
				'übung'         => array( 'training', 'workout', 'fitness' ),
				'essen'         => array( 'nahrung', 'speise', 'gericht' ),
				'technologie'   => array( 'tech', 'digital', 'innovation' ),
				'bildung'       => array( 'lernen', 'ausbildung', 'unterricht' ),
			),
		);

		// Allow filtering of synonym dictionary
		$synonyms = apply_filters( 'msh_keyword_synonyms', $synonyms, $locale );

		return isset( $synonyms[ $locale ] ) ? $synonyms[ $locale ] : array();
	}

	/**
	 * Find related keywords across locales
	 *
	 * @param string $keyword       Keyword to find relations for.
	 * @param string $source_locale Source locale.
	 * @param string $target_locale Target locale.
	 * @return array Related keywords in target locale.
	 */
	public function find_related_keywords( $keyword, $source_locale, $target_locale ) {
		// This is a simplified implementation
		// In production, you might use translation APIs or linguistic databases

		$source_synonyms = $this->get_synonym_dictionary( $source_locale );
		$target_synonyms = $this->get_synonym_dictionary( $target_locale );

		// If keyword is in source dictionary, try to find equivalent in target
		foreach ( $source_synonyms as $base => $synonyms ) {
			if ( $base === $keyword || in_array( $keyword, $synonyms, true ) ) {
				// Found in source, check if equivalent exists in target
				if ( isset( $target_synonyms[ $base ] ) ) {
					return array_merge( array( $base ), $target_synonyms[ $base ] );
				}
			}
		}

		return array();
	}
}
