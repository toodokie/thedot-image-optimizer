<?php
/**
 * Context Fusion Layer - Context Extractor
 *
 * Extracts contextual information from posts for image analysis.
 * Analyzes post content, metadata, taxonomies, and ACF fields.
 *
 * @package MSH_Image_Optimizer
 * @subpackage Context_Fusion
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context Extractor
 *
 * Extracts context from WordPress posts:
 * - Post title, content, excerpt
 * - Headings (H1-H6)
 * - Taxonomies (categories, tags, custom)
 * - Custom fields and ACF
 * - Metadata (author, date, etc.)
 */
class MSH_Context_Extractor {

	/**
	 * Extract all context from a post
	 *
	 * Returns comprehensive context data for a specific post and locale.
	 *
	 * @param WP_Post $post   Post object.
	 * @param string  $locale Locale code.
	 * @return array Context data array
	 */
	public function extract_post_context( $post, $locale = 'en_US' ) {
		return array(
			'post_id'    => $post->ID,
			'locale'     => $locale,
			'title'      => $this->extract_title( $post ),
			'excerpt'    => $this->extract_excerpt( $post ),
			'content'    => $this->extract_content( $post ),
			'headings'   => $this->extract_headings( $post ),
			'taxonomies' => $this->extract_taxonomies( $post ),
			'metadata'   => $this->extract_metadata( $post ),
			'acf_fields' => $this->extract_acf_fields( $post ),
			'blocks'     => $this->extract_blocks( $post ),
		);
	}

	/**
	 * Extract post title
	 *
	 * @param WP_Post $post Post object.
	 * @return string Post title
	 */
	private function extract_title( $post ) {
		return trim( wp_strip_all_tags( $post->post_title ) );
	}

	/**
	 * Extract post excerpt
	 *
	 * @param WP_Post $post Post object.
	 * @return string Post excerpt
	 */
	private function extract_excerpt( $post ) {
		if ( ! empty( $post->post_excerpt ) ) {
			return trim( wp_strip_all_tags( $post->post_excerpt ) );
		}

		// Auto-generate excerpt from content
		return wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' );
	}

	/**
	 * Extract post content (cleaned text)
	 *
	 * @param WP_Post $post Post object.
	 * @return string Post content (plain text)
	 */
	private function extract_content( $post ) {
		// Remove shortcodes
		$content = strip_shortcodes( $post->post_content );

		// Remove HTML
		$content = wp_strip_all_tags( $content );

		// Normalize whitespace
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Extract headings from post content
	 *
	 * Parses HTML to find H1-H6 tags and extract their text.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Array of heading text grouped by level
	 */
	private function extract_headings( $post ) {
		$headings = array(
			'h1' => array(),
			'h2' => array(),
			'h3' => array(),
			'h4' => array(),
			'h5' => array(),
			'h6' => array(),
		);

		// Parse HTML to find headings
		$content = $post->post_content;

		foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $tag ) {
			preg_match_all( "/<{$tag}[^>]*>(.*?)<\/{$tag}>/is", $content, $matches );

			if ( ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $heading ) {
					$heading_text = trim( wp_strip_all_tags( $heading ) );
					if ( ! empty( $heading_text ) ) {
						$headings[ $tag ][] = $heading_text;
					}
				}
			}
		}

		return array_filter( $headings );
	}

	/**
	 * Extract taxonomies (categories, tags, custom)
	 *
	 * @param WP_Post $post Post object.
	 * @return array Array of taxonomy terms grouped by taxonomy
	 */
	private function extract_taxonomies( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );
		$terms_data = array();

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$terms_data[ $taxonomy ] = $terms;
			}
		}

		return $terms_data;
	}

	/**
	 * Extract post metadata
	 *
	 * Returns core metadata like author, date, post type, etc.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Metadata array
	 */
	private function extract_metadata( $post ) {
		$author = get_userdata( $post->post_author );

		return array(
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'author_name'  => $author ? $author->display_name : '',
			'author_id'    => $post->post_author,
			'publish_date' => $post->post_date,
			'modified_date' => $post->post_modified,
			'parent_id'    => $post->post_parent,
			'menu_order'   => $post->menu_order,
		);
	}

	/**
	 * Extract ACF fields
	 *
	 * Gets all ACF field values if ACF plugin is active.
	 *
	 * @param WP_Post $post Post object.
	 * @return array ACF fields array
	 */
	private function extract_acf_fields( $post ) {
		if ( ! function_exists( 'get_fields' ) ) {
			return array();
		}

		$fields = get_fields( $post->ID );

		if ( ! $fields || ! is_array( $fields ) ) {
			return array();
		}

		// Filter out image/file fields (we only want text context)
		$text_fields = array();
		foreach ( $fields as $key => $value ) {
			if ( is_string( $value ) || is_numeric( $value ) ) {
				$text_fields[ $key ] = $value;
			} elseif ( is_array( $value ) && ! $this->is_media_field( $value ) ) {
				$text_fields[ $key ] = $value;
			}
		}

		return $text_fields;
	}

	/**
	 * Check if an ACF field value is a media field
	 *
	 * @param mixed $value Field value.
	 * @return bool True if value is a media field
	 */
	private function is_media_field( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		// Check for ACF image/file structure
		return isset( $value['url'] ) && isset( $value['mime_type'] );
	}

	/**
	 * Extract Gutenberg blocks
	 *
	 * Parses block content and returns structured block data.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Array of blocks with their content
	 */
	private function extract_blocks( $post ) {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return array();
		}

		// Parse blocks
		$blocks      = parse_blocks( $post->post_content );
		$blocks_data = array();

		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$blocks_data[] = array(
				'name'       => $block['blockName'],
				'path'       => $this->get_block_path( $blocks, $index ),
				'inner_html' => isset( $block['innerHTML'] ) ? wp_strip_all_tags( $block['innerHTML'] ) : '',
				'attrs'      => isset( $block['attrs'] ) ? $block['attrs'] : array(),
			);

			// Recursively process inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks_data = array_merge(
					$blocks_data,
					$this->extract_inner_blocks( $block['innerBlocks'], $this->get_block_path( $blocks, $index ) )
				);
			}
		}

		return $blocks_data;
	}

	/**
	 * Extract inner blocks recursively
	 *
	 * @param array  $inner_blocks Array of inner blocks.
	 * @param string $parent_path  Parent block path.
	 * @return array Array of block data
	 */
	private function extract_inner_blocks( $inner_blocks, $parent_path ) {
		$blocks_data = array();

		foreach ( $inner_blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$block_path = "{$parent_path}/{$block['blockName']}[{$index}]";

			$blocks_data[] = array(
				'name'       => $block['blockName'],
				'path'       => $block_path,
				'inner_html' => isset( $block['innerHTML'] ) ? wp_strip_all_tags( $block['innerHTML'] ) : '',
				'attrs'      => isset( $block['attrs'] ) ? $block['attrs'] : array(),
			);

			// Recursively process deeper blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks_data = array_merge(
					$blocks_data,
					$this->extract_inner_blocks( $block['innerBlocks'], $block_path )
				);
			}
		}

		return $blocks_data;
	}

	/**
	 * Get block path for a block at a specific index
	 *
	 * Creates a unique path identifier for a block (e.g., "core/paragraph[0]")
	 *
	 * @param array $blocks All blocks.
	 * @param int   $index  Block index.
	 * @return string Block path
	 */
	private function get_block_path( $blocks, $index ) {
		if ( ! isset( $blocks[ $index ]['blockName'] ) ) {
			return '';
		}

		return "{$blocks[$index]['blockName']}[{$index}]";
	}

	/**
	 * Calculate deterministic source hash
	 *
	 * Implements corrected hashing from Phase 2 design fixes:
	 * - Sorts all components for deterministic output
	 * - Uses SHA-256
	 * - Includes post data, metadata, taxonomies, ACF
	 *
	 * @param WP_Post $post Post object.
	 * @return string SHA-256 hash
	 */
	public function calculate_source_hash( $post ) {
		// Get post data (deterministic order)
		$post_data = array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'date'    => $post->post_modified,
		);

		// Get meta (sorted)
		$meta = get_post_meta( $post->ID );
		ksort( $meta ); // CRITICAL: Sort keys
		foreach ( $meta as $key => &$values ) {
			if ( is_array( $values ) ) {
				sort( $values ); // CRITICAL: Sort values
			}
		}

		// Get taxonomies (sorted)
		$taxonomies = get_object_taxonomies( $post->post_type );
		$terms      = array();
		foreach ( $taxonomies as $tax ) {
			$post_terms = wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $post_terms ) ) {
				sort( $post_terms );
				$terms[ $tax ] = $post_terms;
			}
		}
		ksort( $terms );

		// Get ACF fields (if present, sorted)
		$acf_fields = array();
		if ( function_exists( 'get_fields' ) ) {
			$acf_fields = get_fields( $post->ID ) ?: array();
			if ( is_array( $acf_fields ) ) {
				ksort( $acf_fields );
				// Recursively sort nested arrays
				array_walk_recursive(
					$acf_fields,
					function ( &$item ) {
						if ( is_array( $item ) ) {
							ksort( $item );
						}
					}
				);
			}
		}

		// Combine all components (deterministic)
		$combined = wp_json_encode(
			array(
				'post'  => $post_data,
				'meta'  => $meta,
				'terms' => $terms,
				'acf'   => $acf_fields,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		return hash( 'sha256', $combined );
	}

	/**
	 * Find images used in a post
	 *
	 * Scans post content, featured image, galleries, ACF fields, and blocks
	 * to find all image attachments.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Array of media usage data with media_id, usage_type, block_path
	 */
	public function find_images_in_post( $post ) {
		$images = array();

		// Featured image
		$featured_id = get_post_thumbnail_id( $post->ID );
		if ( $featured_id ) {
			$images[] = array(
				'media_id'   => $featured_id,
				'usage_type' => 'featured',
				'block_path' => null,
			);
		}

		// Images in content (img tags)
		preg_match_all( '/<img[^>]+wp-image-(\d+)/i', $post->post_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( array_unique( $matches[1] ) as $image_id ) {
				$images[] = array(
					'media_id'   => (int) $image_id,
					'usage_type' => 'inline',
					'block_path' => null,
				);
			}
		}

		// Gallery shortcode
		preg_match_all( '/\[gallery[^\]]*ids=["\']([0-9,]+)["\']/i', $post->post_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $ids_string ) {
				$gallery_ids = array_map( 'intval', explode( ',', $ids_string ) );
				foreach ( $gallery_ids as $image_id ) {
					$images[] = array(
						'media_id'   => $image_id,
						'usage_type' => 'gallery',
						'block_path' => null,
					);
				}
			}
		}

		// ACF image fields
		if ( function_exists( 'get_fields' ) ) {
			$acf_fields = get_fields( $post->ID );
			if ( $acf_fields ) {
				$acf_images = $this->find_images_in_acf( $acf_fields );
				$images     = array_merge( $images, $acf_images );
			}
		}

		// Gutenberg blocks
		if ( function_exists( 'parse_blocks' ) ) {
			$blocks       = parse_blocks( $post->post_content );
			$block_images = $this->find_images_in_blocks( $blocks );
			$images       = array_merge( $images, $block_images );
		}

		// Deduplicate by media_id + usage_type + block_path
		$unique_images = array();
		foreach ( $images as $image ) {
			$key                   = $image['media_id'] . '|' . $image['usage_type'] . '|' . ( $image['block_path'] ?? '' );
			$unique_images[ $key ] = $image;
		}

		return array_values( $unique_images );
	}

	/**
	 * Find images in ACF fields
	 *
	 * @param array $fields ACF fields array.
	 * @return array Array of media usage data
	 */
	private function find_images_in_acf( $fields ) {
		$images = array();

		foreach ( $fields as $key => $value ) {
			if ( is_array( $value ) && isset( $value['ID'] ) && isset( $value['mime_type'] ) ) {
				// ACF image field (array format)
				if ( strpos( $value['mime_type'], 'image/' ) === 0 ) {
					$images[] = array(
						'media_id'   => (int) $value['ID'],
						'usage_type' => 'acf_field',
						'block_path' => "acf:{$key}",
					);
				}
			} elseif ( is_numeric( $value ) ) {
				// ACF image field (ID format)
				$attachment = get_post( $value );
				if ( $attachment && strpos( $attachment->post_mime_type, 'image/' ) === 0 ) {
					$images[] = array(
						'media_id'   => (int) $value,
						'usage_type' => 'acf_field',
						'block_path' => "acf:{$key}",
					);
				}
			} elseif ( is_array( $value ) ) {
				// Nested ACF (repeater, group, etc.)
				$nested_images = $this->find_images_in_acf( $value );
				foreach ( $nested_images as &$image ) {
					$image['block_path'] = "acf:{$key}/" . $image['block_path'];
				}
				$images = array_merge( $images, $nested_images );
			}
		}

		return $images;
	}

	/**
	 * Find images in Gutenberg blocks
	 *
	 * @param array  $blocks      Array of blocks.
	 * @param string $parent_path Parent block path.
	 * @return array Array of media usage data
	 */
	private function find_images_in_blocks( $blocks, $parent_path = '' ) {
		$images = array();

		foreach ( $blocks as $index => $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$block_path = $parent_path ? "{$parent_path}/{$block['blockName']}[{$index}]" : "{$block['blockName']}[{$index}]";

			// Check for image in block attributes
			if ( isset( $block['attrs']['id'] ) && is_numeric( $block['attrs']['id'] ) ) {
				// Core image block
				$images[] = array(
					'media_id'   => (int) $block['attrs']['id'],
					'usage_type' => 'block',
					'block_path' => $block_path,
				);
			} elseif ( isset( $block['attrs']['mediaId'] ) && is_numeric( $block['attrs']['mediaId'] ) ) {
				// Media & Text block
				$images[] = array(
					'media_id'   => (int) $block['attrs']['mediaId'],
					'usage_type' => 'block',
					'block_path' => $block_path,
				);
			} elseif ( isset( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
				// Gallery block
				foreach ( $block['attrs']['ids'] as $image_id ) {
					$images[] = array(
						'media_id'   => (int) $image_id,
						'usage_type' => 'block',
						'block_path' => $block_path,
					);
				}
			}

			// Recursively check inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_images = $this->find_images_in_blocks( $block['innerBlocks'], $block_path );
				$images       = array_merge( $images, $inner_images );
			}
		}

		return $images;
	}
}
