<?php
/**
 * Scene Extraction for Non-AI Smart Rephrase
 *
 * Extracts visual concepts from filename tokens to create unique, scene-specific metadata.
 *
 * @package MSH_Image_Optimizer
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSH_NonAI_Scene {

	/**
	 * Concept mapping: token → canonical concept
	 *
	 * @var array
	 */
	private static $concept_map = array(
		// Structures
		'bridge'    => 'bridge',
		'viaduct'   => 'bridge',
		'overpass'  => 'bridge',
		'building'  => 'building',
		'tower'     => 'tower',
		'structure' => 'structure',

		// Water bodies
		'bay'       => 'bay',
		'harbor'    => 'bay',
		'harbour'   => 'bay',
		'river'     => 'river',
		'stream'    => 'river',
		'creek'     => 'river',
		'lake'      => 'lake',
		'pond'      => 'lake',
		'ocean'     => 'ocean',
		'sea'       => 'ocean',
		'water'     => 'water',

		// Nature
		'trees'     => 'trees',
		'tree'      => 'trees',
		'forest'    => 'trees',
		'grove'     => 'trees',
		'woods'     => 'trees',
		'mountain'  => 'mountain',
		'mountains' => 'mountain',
		'hill'      => 'hill',
		'hills'     => 'hill',
		'field'     => 'field',
		'meadow'    => 'field',
		'garden'    => 'garden',
		'park'      => 'park',

		// Urban
		'skyline'   => 'skyline',
		'city'      => 'city',
		'urban'     => 'city',
		'street'    => 'street',
		'road'      => 'road',

		// Interior
		'interior'  => 'interior',
		'room'      => 'interior',
		'office'    => 'interior',
		'desk'      => 'interior',
		'workspace' => 'interior',

		// Equipment
		'equipment' => 'equipment',
		'tool'      => 'equipment',
		'device'    => 'equipment',
		'machine'   => 'equipment',

		// Time of day
		'sunrise'   => 'sunrise',
		'dawn'      => 'sunrise',
		'morning'   => 'sunrise',
		'sunset'    => 'sunset',
		'dusk'      => 'sunset',
		'evening'   => 'sunset',
		'night'     => 'night',
		'twilight'  => 'sunset',

		// Light
		'sunlight'  => 'sunlight',
		'light'     => 'light',
		'shadow'    => 'shadow',
		'golden'    => 'golden-light',

		// Weather
		'cloudy'    => 'cloudy',
		'clouds'    => 'cloudy',
		'foggy'     => 'foggy',
		'fog'       => 'foggy',
		'misty'     => 'foggy',
		'clear'     => 'clear',
		'sunny'     => 'sunny',
		'rain'      => 'rain',
		'rainy'     => 'rain',
		'storm'     => 'storm',
		'stormy'    => 'storm',

		// Landscape features
		'pier'      => 'pier',
		'dock'      => 'pier',
		'marina'    => 'marina',
		'beach'     => 'beach',
		'shore'     => 'beach',
		'coast'     => 'coast',
		'coastal'   => 'coast',
		'cliff'     => 'cliff',
		'cliffs'    => 'cliff',
		'cove'      => 'cove',
		'waterfall' => 'waterfall',
		'falls'     => 'waterfall',
		'rock'      => 'rock',
		'rocks'     => 'rock',
		'rocky'     => 'rock',
		'stone'     => 'stone',
		'stones'    => 'stone',

		// Water bodies & weather
		'boat'      => 'boat',
		'boats'     => 'boat',
		'ship'      => 'ship',
		'sailboat'  => 'sailboat',
		'drop'      => 'drop',
		'drops'     => 'drop',
		'dew'       => 'dew',
		'pool'      => 'pool',
		'tide'      => 'tide',
		'wave'      => 'wave',
		'waves'     => 'wave',

		// Nature elements
		'leaf'      => 'leaf',
		'leaves'    => 'leaf',
		'fern'      => 'fern',
		'grass'     => 'grass',
		'flower'    => 'flower',
		'flowers'   => 'flower',
		'plant'     => 'plant',
		'plants'    => 'plant',

		// Man-made features
		'vintage'   => 'vintage',
		'antique'   => 'vintage',
		'old'       => 'vintage',
		'weathered' => 'weathered',
		'rusty'     => 'rusty',
		'turbine'   => 'turbine',
		'turbines'  => 'turbine',
		'wind'      => 'wind',

		// Transportation
		'train'     => 'train',
		'track'     => 'track',
		'tracks'    => 'track',
		'rail'      => 'rail',
		'railway'   => 'railway',

		// Animals
		'coyote'    => 'coyote',
		'wolf'      => 'wolf',
		'deer'      => 'deer',
		'bird'      => 'bird',
		'birds'     => 'bird',

		// Rural/agricultural
		'farm'      => 'farm',
		'rural'     => 'rural',
	);

	/**
	 * Proper name patterns (multi-word landmarks)
	 *
	 * @var array
	 */
	private static $proper_name_patterns = array(
		'golden gate bridge',
		'sydney harbour bridge',
		'brooklyn bridge',
		'tower bridge',
		'eiffel tower',
		'empire state building',
	);

	/**
	 * Stop words (generic terms to exclude from nouns)
	 *
	 * @var array
	 */
	private static $stop_words = array(
		'view',
		'landscape',
		'scenic',
		'panorama',
		'image',
		'photo',
		'picture',
		'detail',
		'closeup',
		'close',
		'wide',
		'featured',
		'test',
		'vertical',
		'horizontal',
		'extra',
	);

	/**
	 * Extract scene concepts from filename
	 *
	 * @param string $filename Original filename (e.g., "golden-gate-bridge-view.jpg")
	 * @param int    $attachment_id Attachment ID for deterministic selection
	 * @return array Scene structure with proper_names, nouns, time_of_day, light, mood
	 */
	public static function extract( $filename, $attachment_id = 0 ) {
		// Remove extension
		$clean = preg_replace( '/\.(jpg|jpeg|png|webp|gif)$/i', '', $filename );

		// Replace separators with spaces, lowercase
		$clean = strtolower( str_replace( array( '-', '_' ), ' ', $clean ) );

		// Remove common suffixes (main, thumb, etc.)
		$clean = preg_replace( '/\b(main|thumb|thumbnail|small|medium|large|hero|banner)\b/i', '', $clean );
		$clean = trim( preg_replace( '/\s+/', ' ', $clean ) );

		// Extract proper names first (multi-word landmarks)
		$proper_names = self::extract_proper_names( $clean );

		// Remove proper names from clean string to avoid duplicate detection
		foreach ( $proper_names as $name ) {
			$clean = str_replace( strtolower( $name ), '', $clean );
		}
		$clean = trim( preg_replace( '/\s+/', ' ', $clean ) );

		// Tokenize remaining text
		$tokens = array_filter( explode( ' ', $clean ) );

		// Map tokens to concepts
		$nouns        = array();
		$time_of_day  = null;
		$light        = null;
		$weather      = null;

		foreach ( $tokens as $token ) {
			if ( isset( self::$concept_map[ $token ] ) ) {
				$concept = self::$concept_map[ $token ];

				// Categorize concept
				if ( in_array( $concept, array( 'sunrise', 'sunset', 'night' ), true ) ) {
					$time_of_day = $concept;
				} elseif ( in_array( $concept, array( 'sunlight', 'light', 'shadow', 'golden-light' ), true ) ) {
					$light = $concept;
				} elseif ( in_array( $concept, array( 'cloudy', 'foggy', 'clear', 'sunny', 'rain', 'storm' ), true ) ) {
					$weather = $concept;
				} else {
					// It's a noun - but skip stop words
					if ( ! in_array( $concept, self::$stop_words, true ) && ! in_array( $concept, $nouns, true ) ) {
						$nouns[] = $concept;
					}
				}
			}
		}

		// Build scene structure
		$scene = array(
			'proper_names' => $proper_names,
			'nouns'        => $nouns,
			'time_of_day'  => $time_of_day,
			'light'        => $light,
			'weather'      => $weather,
			'raw_filename' => $filename,
			'clean_text'   => $clean,
		);

		// Debug logging
		error_log( sprintf(
			'[MSH Scene] Extracted from "%s": proper_names=%s, nouns=%s, time=%s, light=%s',
			$filename,
			! empty( $proper_names ) ? implode( ',', $proper_names ) : 'NONE',
			! empty( $nouns ) ? implode( ',', $nouns ) : 'NONE',
			$time_of_day ?? 'NONE',
			$light ?? 'NONE'
		) );

		return $scene;
	}

	/**
	 * Extract proper names (multi-word landmarks) from text
	 *
	 * @param string $text Cleaned filename text
	 * @return array Array of proper names found
	 */
	private static function extract_proper_names( $text ) {
		$found = array();

		foreach ( self::$proper_name_patterns as $pattern ) {
			if ( stripos( $text, $pattern ) !== false ) {
				// Title case the match
				$found[] = ucwords( $pattern );
			}
		}

		return $found;
	}

	/**
	 * Build a human-readable scene description from scene struct
	 *
	 * @param array $scene Scene structure from extract()
	 * @return string Human-readable description (e.g., "Golden Gate Bridge over bay at sunrise")
	 */
	public static function describe( array $scene ) {
		$parts = array();

		// Start with proper names
		if ( ! empty( $scene['proper_names'] ) ) {
			$parts[] = $scene['proper_names'][0];
		}

		// Add light attribute if present and meaningful
		if ( ! empty( $scene['light'] ) && ! in_array( $scene['light'], array( 'light' ), true ) ) {
			$parts[] = ucfirst( str_replace( '-', ' ', $scene['light'] ) );
		}

		// Add primary nouns (up to 3)
		if ( ! empty( $scene['nouns'] ) ) {
			$noun_slice = array_slice( $scene['nouns'], 0, 3 );
			foreach ( $noun_slice as $noun ) {
				$parts[] = ucfirst( $noun );
			}
		}

		// Add time of day
		if ( ! empty( $scene['time_of_day'] ) ) {
			$parts[] = 'at ' . ucfirst( $scene['time_of_day'] );
		}

		// Fallback
		if ( empty( $parts ) ) {
			error_log( '[MSH Scene] describe() returned fallback: "Scenic View"' );
			return 'Scenic View';
		}

		$result = implode( ' ', $parts );
		error_log( sprintf( '[MSH Scene] describe() generated: "%s"', $result ) );

		return $result;
	}

	/**
	 * Get scene concepts as comma-separated list (for keywords)
	 *
	 * @param array $scene Scene structure from extract()
	 * @return array Array of keyword strings
	 */
	public static function get_keywords( array $scene ) {
		$keywords = array();

		// Proper names
		if ( ! empty( $scene['proper_names'] ) ) {
			foreach ( $scene['proper_names'] as $name ) {
				$keywords[] = $name;
			}
		}

		// Nouns (up to 3)
		if ( ! empty( $scene['nouns'] ) ) {
			$noun_slice = array_slice( $scene['nouns'], 0, 3 );
			foreach ( $noun_slice as $noun ) {
				$keywords[] = $noun;
			}
		}

		// Time of day
		if ( ! empty( $scene['time_of_day'] ) ) {
			$keywords[] = $scene['time_of_day'];
		}

		// Weather
		if ( ! empty( $scene['weather'] ) ) {
			$keywords[] = $scene['weather'];
		}

		return array_slice( $keywords, 0, 5 );
	}
}
