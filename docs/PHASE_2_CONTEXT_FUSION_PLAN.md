# Phase 2: Context Fusion Layer - Implementation Plan

**Created:** 2025-10-18
**Status:** 🚧 Ready to Implement
**Estimated Effort:** 9.5 hours for MVP
**Last Updated:** 2025-10-18 (Finalized with expert review)

---

## Overview

Phase 2 fuses WordPress content context with media items so AI knows **why** an image exists (not just **what** it shows). This enables better, more contextually relevant metadata generation across multiple locales.

---

## Architecture

### What We Already Have ✅

1. **Context Profiles System** (`MSH_Context_Helper`)
   - `get_active_context()` - Returns business name, industry, city, country, UVP
   - `get_active_profile()` - Returns locale-aware profile
   - Context signature hashing

2. **Phase 4 Metadata Versioning** ✅
   - Multi-locale support
   - Source tracking
   - Database infrastructure

3. **Phase 1 Multilingual AI** ✅
   - Language selector
   - Locale detection
   - AI generation per locale

### What We Need to Build 🔨

1. **Database Table: `optimizer_context`**
2. **Post Content Extractor** - Extract context from WordPress posts
3. **Intent Classifier** - Determine on_topic vs off_topic
4. **Keyword Normalizer** - Extract and normalize keywords per locale
5. **Entity Extractor** - Detect brands, places, people

---

## Database Schema

### Table: `wp_msh_optimizer_context`

**Key Design Decision:** Context is stored per `(media_id, post_id, locale)` to track each unique usage of an image. This allows one image to have different intents/contexts when used in multiple posts.

```sql
CREATE TABLE wp_msh_optimizer_context (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_id BIGINT UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(20) NOT NULL,

    -- Extracted context
    subject VARCHAR(255) NULL,
    intent ENUM('on_topic','off_topic','unknown') NOT NULL DEFAULT 'unknown',
    intent_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
    entities LONGTEXT NULL COMMENT 'JSON: {brands:[],places:[],people:[]}',
    keywords LONGTEXT NULL COMMENT 'JSON: [keyword1,keyword2,...]',
    rules_fired LONGTEXT NULL COMMENT 'JSON: [rule1,rule2,...] for observability',

    -- Change detection
    source_hash CHAR(40) NOT NULL COMMENT 'SHA-1 of post content + metadata',

    -- Scoring & metadata
    context_score SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100 relevance score',
    usage_type VARCHAR(64) NULL COMMENT 'featured|inline|gallery|acf_field',
    block_path VARCHAR(255) NULL COMMENT 'Block path like /core/columns[0]/core/image[1]',

    -- Usage tracking
    usage_count INT UNSIGNED NOT NULL DEFAULT 1,
    first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_ctx (media_id, post_id, locale),
    KEY idx_media (media_id),
    KEY idx_post (post_id),
    KEY idx_intent (intent),
    KEY idx_locale (locale),
    KEY idx_score (context_score),
    KEY idx_hash (source_hash),
    KEY idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `media_id` | BIGINT | WordPress attachment ID |
| `post_id` | BIGINT | WordPress post/page ID where image is used |
| `locale` | VARCHAR(20) | Locale code (en, es, fr-CA, etc.) |
| `subject` | VARCHAR(255) | Main topic extracted from post (e.g., "HVAC repair in Toronto") |
| `intent` | ENUM | Classification: `on_topic`, `off_topic`, `unknown` |
| `intent_confidence` | TINYINT | Confidence level 0-100 |
| `entities` | LONGTEXT | JSON: `{"brands":[],"places":[],"people":[]}` |
| `keywords` | LONGTEXT | JSON array of normalized keywords |
| `rules_fired` | LONGTEXT | JSON array of classification rules that fired (debugging) |
| `source_hash` | CHAR(40) | SHA-1 hash for change detection |
| `context_score` | SMALLINT | Relevance score 0-100 |
| `usage_type` | VARCHAR(64) | How image is used: `featured`, `inline`, `gallery`, `acf_field` |
| `block_path` | VARCHAR(255) | Gutenberg block path for traceability |
| `usage_count` | INT | Number of times this context was computed |
| `first_seen` | DATETIME | When context was first extracted |
| `last_seen` | DATETIME | When context was last verified/updated |

### Source Hash Calculation

**Formula (Deterministic):**
```php
function msh_ctx_source_hash( int $post_id, int $media_id, string $locale ): string {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return sha1( "orphan:{$media_id}:{$locale}" );
    }

    // Post text
    $text = $post->post_title . "\n" . $post->post_content;

    // Taxonomies
    $tax_slugs = [];
    foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
        $terms = get_the_terms( $post_id, $tax );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $tax_slugs[] = $t->slug;
            }
        }
    }
    sort( $tax_slugs );

    // ACF fields (if present)
    $acf_bits = '';
    if ( function_exists( 'get_field_objects' ) ) {
        $fields = get_field_objects( $post_id );
        if ( is_array( $fields ) ) {
            $acf_bits = json_encode( $fields );
        }
    }

    // Attachment metadata
    $attach_meta = get_post_meta( $media_id );
    $file_path   = get_attached_file( $media_id );
    $filesize    = $file_path ? filesize( $file_path ) : 0;

    // Block attributes (if Gutenberg)
    $block_attrs = '';
    if ( has_blocks( $post->post_content ) ) {
        $blocks = parse_blocks( $post->post_content );
        // Extract only attrs that might affect context (alt, caption, etc.)
        $block_attrs = json_encode( array_column( $blocks, 'attrs' ) );
    }

    // Combine into deterministic payload
    $payload = implode( '|', [
        $post_id,
        $locale,
        md5( $text ),
        md5( implode( ',', $tax_slugs ) ),
        md5( $acf_bits ),
        md5( json_encode( $attach_meta ) . $filesize ),
        md5( $block_attrs ),
    ] );

    return sha1( $payload );
}
```

**Rationale:** SHA-1 (40 chars) is sufficient for change detection and faster than SHA-256. We hash individual components first to reduce payload size.

---

## Implementation Components

### 1. Class: `MSH_Context_Fusion` (NEW)

**File:** `includes/class-msh-context-fusion.php`

**Responsibilities:**
- Database table management
- Context extraction orchestration
- Context retrieval and caching
- Change detection via source_hash

**Key Methods:**
```php
class MSH_Context_Fusion {
    // Database
    public static function maybe_create_table();

    // Context extraction
    public function extract_context( $media_id, $locale );
    public function get_context( $media_id, $locale );
    public function invalidate_context( $media_id, $locale = null );

    // Utilities
    private function calculate_source_hash( $data );
    private function needs_refresh( $media_id, $locale, $current_hash );
}
```

---

### 2. Class: `MSH_Post_Content_Extractor` (NEW)

**File:** `includes/class-msh-post-content-extractor.php`

**Responsibilities:**
- Find all posts that use a specific image
- Extract context from post content (title, headings, taxonomies)
- Handle Gutenberg blocks with `innerBlocks` traversal
- Support featured images, inline images, galleries, ACF fields

**Key Methods:**

#### Find Posts Using Media
```php
/**
 * Find all posts that use a specific media item
 *
 * @param int $media_id Attachment ID
 * @return array List of posts with usage metadata
 */
function msh_ctx_list_media_in_post( WP_Post $post ): array {
    $out = [];

    // Parse Gutenberg blocks
    $blocks = parse_blocks( $post->post_content );

    // Recursive walker for innerBlocks
    $walk = function( array $blocks, string $path = '' ) use ( &$walk, &$out ) {
        foreach ( $blocks as $i => $b ) {
            $name = $b['blockName'] ?? '';
            $id   = $b['attrs']['id'] ?? null;

            // Core image block
            if ( $name === 'core/image' && $id ) {
                $out[] = [
                    'media_id'   => (int) $id,
                    'usage_type' => 'inline',
                    'block_path' => $path . "/{$name}[{$i}]",
                ];
            }

            // Gallery block
            if ( $name === 'core/gallery' && ! empty( $b['attrs']['ids'] ) ) {
                foreach ( $b['attrs']['ids'] as $gal_id ) {
                    $out[] = [
                        'media_id'   => (int) $gal_id,
                        'usage_type' => 'gallery',
                        'block_path' => $path . "/{$name}[{$i}]",
                    ];
                }
            }

            // Traverse innerBlocks (columns, groups, etc.)
            if ( ! empty( $b['innerBlocks'] ) ) {
                $walk( $b['innerBlocks'], $path . "/{$name}[{$i}]" );
            }
        }
    };

    $walk( $blocks );

    // Featured image
    $feat = get_post_thumbnail_id( $post );
    if ( $feat ) {
        $out[] = [
            'media_id'   => $feat,
            'usage_type' => 'featured',
            'block_path' => 'featured',
        ];
    }

    // ACF fields (if available)
    if ( function_exists( 'get_field_objects' ) ) {
        $fields = get_field_objects( $post->ID );
        if ( is_array( $fields ) ) {
            foreach ( $fields as $key => $field ) {
                if ( $field['type'] === 'image' && is_numeric( $field['value'] ) ) {
                    $out[] = [
                        'media_id'   => (int) $field['value'],
                        'usage_type' => 'acf_field',
                        'block_path' => "acf:{$key}",
                    ];
                }
            }
        }
    }

    return $out;
}
```

#### Extract Content from Post
```php
/**
 * Extract context-relevant content from post
 */
class MSH_Post_Content_Extractor {
    public function extract( $post_id, $locale ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return null;
        }

        return [
            'post_id'    => $post_id,
            'title'      => $post->post_title,
            'content'    => wp_strip_all_tags( $post->post_content ),
            'excerpt'    => $post->post_excerpt,
            'categories' => $this->get_taxonomy_terms( $post_id, 'category', $locale ),
            'tags'       => $this->get_taxonomy_terms( $post_id, 'post_tag', $locale ),
        ];
    }

    private function get_taxonomy_terms( $post_id, $taxonomy, $locale ) {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return [];
        }

        return array_map( function( $term ) {
            return $term->name;
        }, $terms );
    }
}
```

**Post Detection Logic:**
1. Parse blocks with `parse_blocks()` - handles Gutenberg content
2. Walk `innerBlocks` recursively (columns, groups, template parts)
3. Check featured image via `get_post_thumbnail_id()`
4. Check ACF image fields if ACF is active
5. Track `usage_type` and `block_path` for each usage

---

### 3. Class: `MSH_Intent_Classifier` (NEW)

**File:** `includes/class-msh-intent-classifier.php`

**Responsibilities:**
- Classify images as `on_topic`, `off_topic`, or `unknown`
- Use rule-based classification with confidence scoring
- Log which rules fired for observability

**Classification Implementation:**

```php
/**
 * Compute intent with rules_fired logging
 *
 * @param array $ctx Context data
 * @return array [$intent, $confidence, $rules_fired]
 */
function msh_ctx_compute_intent( array $ctx ): array {
    $rules_fired = [];
    $on          = false;
    $off         = false;

    // Featured images are almost always on-topic
    if ( $ctx['usage_type'] === 'featured' ) {
        $on            = true;
        $rules_fired[] = 'featured';
    }

    // ACF fields are usually on-topic
    if ( strpos( $ctx['usage_type'], 'acf' ) === 0 ) {
        $on            = true;
        $rules_fired[] = 'acf_field';
    }

    // Check filename for stock photo indicators
    $filename = basename( get_attached_file( $ctx['media_id'] ) );
    if ( preg_match( '/\b(shutterstock|getty|istock|depositphotos)\b/i', $filename ) ) {
        $off           = true;
        $rules_fired[] = 'stock_photo_filename';
    }

    // Check if post is published
    $post_status = get_post_status( $ctx['post_id'] );
    if ( ! in_array( $post_status, [ 'publish', 'future' ], true ) ) {
        $off           = true;
        $rules_fired[] = 'post_not_published';
    }

    // Check if categories/tags match site context
    $context_helper = MSH_Image_Optimizer_Context_Helper::get_instance();
    $active_context = $context_helper->get_active_context();
    $industry       = $active_context['industry'] ?? '';

    if ( $industry ) {
        // Extract post categories
        $categories = wp_get_post_categories( $ctx['post_id'], [ 'fields' => 'names' ] );
        foreach ( $categories as $cat ) {
            if ( stripos( $cat, $industry ) !== false ) {
                $on            = true;
                $rules_fired[] = 'category_matches_industry';
                break;
            }
        }
    }

    // Image alt text contains brand name
    $alt   = get_post_meta( $ctx['media_id'], '_wp_attachment_image_alt', true );
    $brand = $active_context['business_name'] ?? '';
    if ( $brand && stripos( $alt, $brand ) !== false ) {
        $on            = true;
        $rules_fired[] = 'alt_contains_brand';
    }

    // Determine final intent
    $intent     = 'unknown';
    $confidence = 50;

    if ( $on && ! $off ) {
        $intent     = 'on_topic';
        $confidence = 90;
    } elseif ( $off && ! $on ) {
        $intent     = 'off_topic';
        $confidence = 90;
    } elseif ( $on && $off ) {
        // Conflict - lean on_topic if featured/ACF
        if ( in_array( 'featured', $rules_fired, true ) || in_array( 'acf_field', $rules_fired, true ) ) {
            $intent     = 'on_topic';
            $confidence = 60;
        } else {
            $intent     = 'unknown';
            $confidence = 40;
        }
    }

    return [ $intent, $confidence, $rules_fired ];
}
```

**Rule Categories:**
- **On-Topic Signals:** Featured image, ACF field, category match, brand in alt text
- **Off-Topic Signals:** Stock photo filename, unpublished post, no post association
- **Confidence Scoring:** 90% if clear, 60% if conflict, 40-50% if unknown

**Future Enhancement:** Add LLM-based classification for `unknown` cases

---

### 4. Class: `MSH_Keyword_Normalizer` (NEW)

**File:** `includes/class-msh-keyword-normalizer.php`

**Responsibilities:**
- Extract keywords from post content
- Remove stopwords per locale
- Normalize and deduplicate
- Return top N keywords by frequency

**Implementation:**
```php
/**
 * Normalize keywords from text (locale-aware)
 *
 * @param string $text   Text to extract keywords from
 * @param string $locale Locale code (e.g., 'en', 'es', 'fr')
 * @param int    $limit  Max keywords to return (default 10)
 * @return array Normalized keywords ordered by frequency
 */
function msh_ctx_normalize_keywords( string $text, string $locale = 'en', int $limit = 10 ): array {
    // Convert to lowercase
    $text = mb_strtolower( $text, 'UTF-8' );

    // Tokenize (split on non-word characters)
    preg_match_all( '/\b[\p{L}\p{N}]+\b/u', $text, $matches );
    $words = $matches[0];

    // Load stopwords for locale
    $stopwords = msh_ctx_get_stopwords( $locale );

    // Filter stopwords and short words
    $filtered = array_filter( $words, function( $word ) use ( $stopwords ) {
        return strlen( $word ) > 2 && ! in_array( $word, $stopwords, true );
    } );

    // Count frequencies
    $freq = array_count_values( $filtered );

    // Sort by frequency (descending)
    arsort( $freq );

    // Return top N keywords
    return array_slice( array_keys( $freq ), 0, $limit );
}

/**
 * Get stopwords list for locale
 */
function msh_ctx_get_stopwords( string $locale ): array {
    $stopwords = [
        'en' => [ 'the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'this', 'that', 'these', 'those' ],
        'es' => [ 'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'y', 'o', 'en', 'con', 'por', 'para', 'es', 'son', 'fue', 'fueron', 'este', 'esta', 'estos', 'estas' ],
        'fr' => [ 'le', 'la', 'les', 'un', 'une', 'de', 'du', 'des', 'et', 'ou', 'dans', 'avec', 'par', 'pour', 'est', 'sont', 'ce', 'cette', 'ces' ],
    ];

    // Extract base language (en-US -> en)
    $lang = substr( $locale, 0, 2 );

    return $stopwords[ $lang ] ?? $stopwords['en'];
}
```

**Features:**
- Unicode-aware tokenization (`\p{L}\p{N}`)
- Locale-specific stopwords (en, es, fr)
- Frequency-based ranking
- Configurable limit (top N keywords)

**Future Enhancement:** Add Porter Stemmer for better keyword matching

---

### 5. Class: `MSH_Entity_Extractor` (NEW)

**File:** `includes/class-msh-entity-extractor.php`

**Responsibilities:**
- Extract entities (brands, places, people) from text and context
- Use context profiles for brand/location data
- Dictionary-based approach for MVP

**Implementation:**
```php
/**
 * Extract entities from text and context
 *
 * @param string $text           Post content
 * @param array  $active_context Context from MSH_Context_Helper
 * @return array Entities grouped by type
 */
function msh_ctx_extract_entities( string $text, array $active_context ): array {
    $entities = [
        'brands' => [],
        'places' => [],
        'people' => [],
    ];

    // Extract from context profile (guaranteed entities)
    if ( ! empty( $active_context['business_name'] ) ) {
        $entities['brands'][] = $active_context['business_name'];
    }

    if ( ! empty( $active_context['city'] ) ) {
        $entities['places'][] = $active_context['city'];
    }

    if ( ! empty( $active_context['country'] ) ) {
        $entities['places'][] = $active_context['country'];
    }

    // Pattern matching for capitalized words (potential entities)
    // Match 2-4 capitalized words in a row (e.g., "Toronto General Hospital")
    preg_match_all( '/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\b/', $text, $matches );

    if ( ! empty( $matches[1] ) ) {
        $potential_entities = array_unique( $matches[1] );

        // Simple heuristics for classification
        foreach ( $potential_entities as $entity ) {
            // Check against known place suffixes
            if ( preg_match( '/\b(City|Town|Village|County|Province|State)\b/i', $entity ) ) {
                $entities['places'][] = $entity;
            }
            // Check against known brand keywords (extend as needed)
            elseif ( preg_match( '/\b(Inc|LLC|Corp|Company|Ltd|Group)\b/i', $entity ) ) {
                $entities['brands'][] = $entity;
            }
            // Otherwise, could be a person or brand - defer
        }
    }

    // Deduplicate and return
    return [
        'brands' => array_values( array_unique( $entities['brands'] ) ),
        'places' => array_values( array_unique( $entities['places'] ) ),
        'people' => array_values( array_unique( $entities['people'] ) ),
    ];
}
```

**Dictionary Approach:**
- Context profile provides guaranteed entities (business, city, country)
- Pattern matching finds capitalized multi-word phrases
- Heuristics classify based on suffixes (Inc, City, etc.)
- Simple and fast for MVP

**Future Enhancement:** Integrate NER library (spaCy PHP, StanfordNER) or LLM for better accuracy

---

## Integration Points

### 1. Hook into Post Save (Refresh Context)
```php
add_action( 'save_post', [ $fusion, 'refresh_post_media_context' ], 10, 1 );

/**
 * When a post is saved, refresh context for all media used in that post
 */
public function refresh_post_media_context( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return;
    }

    // Find all media in this post
    $media_list = msh_ctx_list_media_in_post( $post );

    foreach ( $media_list as $usage ) {
        // Extract context per locale
        $locales = $this->get_site_locales(); // en, es, fr, etc.
        foreach ( $locales as $locale ) {
            $this->extract_and_save_context( $usage['media_id'], $post_id, $locale, $usage );
        }
    }

    // Clear rollup cache for affected media
    foreach ( $media_list as $usage ) {
        wp_cache_delete( "msh_ctx_rollup:{$usage['media_id']}:*", 'msh' );
    }
}
```

### 2. Rollup Computation (On-Demand with Caching)
**Context detail rows are cheap to store, rollups are computed on-demand:**

```php
/**
 * Get rollup context for a media item (aggregates all post usages)
 *
 * @param int    $media_id Attachment ID
 * @param string $locale   Locale code
 * @return array Rollup context bundle for AI prompt
 */
function msh_ctx_get_rollup( int $media_id, string $locale ): array {
    global $wpdb;

    // Check cache first
    $cache_key = "msh_ctx_rollup:{$media_id}:{$locale}";
    $cached    = wp_cache_get( $cache_key, 'msh' );
    if ( false !== $cached ) {
        return $cached;
    }

    // Query all context rows for this media+locale
    $table = $wpdb->prefix . 'msh_optimizer_context';
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE media_id = %d AND locale = %s ORDER BY context_score DESC",
            $media_id,
            $locale
        ),
        ARRAY_A
    );

    if ( empty( $rows ) ) {
        return null;
    }

    // Reduce rows into rollup
    $rollup = msh_ctx_reduce_rows( $rows );

    // Cache for 1 hour
    wp_cache_set( $cache_key, $rollup, 'msh', HOUR_IN_SECONDS );

    return $rollup;
}

/**
 * Reduce detail rows into a single rollup object
 */
function msh_ctx_reduce_rows( array $rows ): array {
    $all_keywords = [];
    $all_entities = [ 'brands' => [], 'places' => [], 'people' => [] ];
    $all_subjects = [];
    $max_score    = 0;
    $top_intent   = 'unknown';

    foreach ( $rows as $row ) {
        // Aggregate keywords
        $kw = json_decode( $row['keywords'], true );
        if ( is_array( $kw ) ) {
            $all_keywords = array_merge( $all_keywords, $kw );
        }

        // Aggregate entities
        $ent = json_decode( $row['entities'], true );
        if ( is_array( $ent ) ) {
            foreach ( [ 'brands', 'places', 'people' ] as $type ) {
                if ( ! empty( $ent[ $type ] ) ) {
                    $all_entities[ $type ] = array_merge( $all_entities[ $type ], $ent[ $type ] );
                }
            }
        }

        // Collect subjects
        if ( ! empty( $row['subject'] ) ) {
            $all_subjects[] = $row['subject'];
        }

        // Track highest score and corresponding intent
        if ( $row['context_score'] > $max_score ) {
            $max_score  = $row['context_score'];
            $top_intent = $row['intent'];
        }
    }

    // Deduplicate
    $all_keywords = array_values( array_unique( $all_keywords ) );
    foreach ( $all_entities as $type => $list ) {
        $all_entities[ $type ] = array_values( array_unique( $list ) );
    }

    return [
        'media_id'  => $rows[0]['media_id'],
        'locale'    => $rows[0]['locale'],
        'intent'    => $top_intent,
        'score'     => $max_score,
        'subjects'  => array_unique( $all_subjects ),
        'keywords'  => $all_keywords,
        'entities'  => $all_entities,
        'num_posts' => count( $rows ),
    ];
}
```

### 3. Hook into AI Generation
Modify `MSH_OpenAI_Connector::generate_metadata()` to include context rollup:
```php
$context_rollup = msh_ctx_get_rollup( $media_id, $locale );
if ( $context_rollup && $context_rollup['intent'] === 'on_topic' ) {
    $prompt = $this->build_prompt_with_context( $context_rollup, ... );
}
```

### 4. WP-CLI Commands

**Core Commands (MVP):**
```php
/**
 * Extract context for a specific image
 *
 * wp msh context extract 123 --locale=es
 */
WP_CLI::add_command( 'msh context extract', function( $args, $assoc_args ) {
    $media_id = absint( $args[0] );
    $locale   = $assoc_args['locale'] ?? 'en';

    // Find all posts using this media
    // Extract context for each post+locale combination
    // Display results
} );

/**
 * Get rollup for a specific image
 *
 * wp msh context rollup 123 --locale=en
 */
WP_CLI::add_command( 'msh context rollup', function( $args, $assoc_args ) {
    $media_id = absint( $args[0] );
    $locale   = $assoc_args['locale'] ?? 'en';

    $rollup = msh_ctx_get_rollup( $media_id, $locale );
    WP_CLI::success( print_r( $rollup, true ) );
} );

/**
 * Show context statistics
 *
 * wp msh context stats
 */
WP_CLI::add_command( 'msh context stats', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'msh_optimizer_context';

    $total        = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $on_topic     = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE intent = 'on_topic'" );
    $off_topic    = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE intent = 'off_topic'" );
    $unknown      = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE intent = 'unknown'" );
    $unique_media = $wpdb->get_var( "SELECT COUNT(DISTINCT media_id) FROM {$table}" );

    WP_CLI::success( "Total context records: {$total}" );
    WP_CLI::line( "On-topic: {$on_topic}" );
    WP_CLI::line( "Off-topic: {$off_topic}" );
    WP_CLI::line( "Unknown: {$unknown}" );
    WP_CLI::line( "Unique media items: {$unique_media}" );
} );
```

---

## Implementation Phases

### Phase 2A: Database & Core (2 hours)
**Files:** `class-msh-context-fusion.php`, `class-msh-context-functions.php`

- [ ] Create database table with finalized schema
- [ ] Implement `msh_ctx_source_hash()` function
- [ ] Create `MSH_Context_Fusion` class (singleton)
- [ ] Basic CRUD operations for context records
- [ ] Source hash change detection logic
- [ ] Test table creation and migration

### Phase 2B: Post Content Extraction (3 hours)
**Files:** `class-msh-post-content-extractor.php`

- [ ] Implement `msh_ctx_list_media_in_post()` with innerBlocks traversal
- [ ] Handle core/image, core/gallery blocks
- [ ] Traverse innerBlocks recursively (columns, groups, template parts)
- [ ] Extract featured image
- [ ] Extract ACF image fields (if available)
- [ ] Track `usage_type` and `block_path`
- [ ] Create `MSH_Post_Content_Extractor` class
- [ ] Extract title, content, taxonomies per locale
- [ ] Test with complex posts (nested blocks, galleries)

### Phase 2C: Intent & Keywords (1.5 hours)
**Files:** `class-msh-intent-classifier.php`, `class-msh-keyword-normalizer.php`

- [ ] Implement `msh_ctx_compute_intent()` with rules_fired logging
- [ ] Add all intent classification rules (featured, ACF, stock photo, etc.)
- [ ] Test intent classifier with edge cases
- [ ] Implement `msh_ctx_normalize_keywords()` function
- [ ] Implement `msh_ctx_get_stopwords()` for en, es, fr
- [ ] Test keyword extraction with multilingual content

### Phase 2D: Entity Extraction (1 hour)
**Files:** `class-msh-entity-extractor.php`

- [ ] Implement `msh_ctx_extract_entities()` function
- [ ] Extract from context profile (business, city, country)
- [ ] Pattern matching for capitalized phrases
- [ ] Heuristic classification (Inc, City, etc.)
- [ ] Test with real content

### Phase 2E: Rollup & Integration (2 hours)
**Files:** `class-msh-context-fusion.php`, CLI files

- [ ] Implement `msh_ctx_get_rollup()` function
- [ ] Implement `msh_ctx_reduce_rows()` aggregation
- [ ] Add WordPress cache integration
- [ ] Hook into `save_post` for context refresh
- [ ] Invalidate rollup cache on post update
- [ ] Implement 3 core WP-CLI commands (extract, rollup, stats)
- [ ] Integrate with AI generation workflow
- [ ] Test end-to-end context extraction → rollup → AI prompt

**Total Estimated Time:** 9.5 hours

---

## Testing Plan

### Unit Tests (10 Tests - Slim MVP)

**File:** `tests/test-context-fusion.php`

```php
/**
 * Test 1: Source hash is deterministic
 */
test_source_hash_deterministic()
// Verify same inputs produce same hash

/**
 * Test 2: Source hash changes on post update
 */
test_source_hash_changes_on_update()
// Edit post title, verify hash changes

/**
 * Test 3: Block traversal finds nested images
 */
test_block_traversal_innerblocks()
// Create post with columns > group > image, verify found

/**
 * Test 4: Featured image detected
 */
test_featured_image_detection()
// Set featured image, verify usage_type='featured'

/**
 * Test 5: Intent classification - featured image
 */
test_intent_featured_image()
// Featured image should be on_topic with high confidence

/**
 * Test 6: Intent classification - stock photo filename
 */
test_intent_stock_photo()
// Filename with 'shutterstock' should be off_topic

/**
 * Test 7: Keyword extraction removes stopwords
 */
test_keyword_stopwords_removed()
// Text with 'the', 'and', 'or' should exclude them

/**
 * Test 8: Entity extraction from context profile
 */
test_entity_extraction_context_profile()
// Business name, city, country should appear in entities

/**
 * Test 9: Rollup aggregates multiple posts
 */
test_rollup_aggregates_keywords()
// Image used in 2 posts, rollup should merge keywords

/**
 * Test 10: Rollup cache invalidation
 */
test_rollup_cache_invalidated_on_post_save()
// Save post, verify rollup cache cleared
```

### Integration Tests (Manual - defer automated tests)

1. **Test with Real Post:**
   - Create post with featured image + 2 inline images
   - Verify all 3 context records created
   - Check intent, keywords, entities

2. **Test Multilingual:**
   - Create same post in en, es locales
   - Verify separate context records per locale
   - Check stopwords removed per locale

3. **Test Rollup:**
   - Use same image in 3 different posts
   - Verify rollup aggregates all 3 contexts
   - Check cache hit on second call

4. **Test WP-CLI:**
   - Run `wp msh context extract 123`
   - Run `wp msh context rollup 123 --locale=en`
   - Run `wp msh context stats`

5. **Test AI Integration:**
   - Generate metadata with context rollup
   - Compare quality with/without context
   - Verify on_topic images get better metadata

---

## API Examples

### Extract Context
```php
$fusion = MSH_Context_Fusion::get_instance();
$context = $fusion->extract_context( 123, 'es' );

// Returns:
// [
//     'media_id' => 123,
//     'locale' => 'es',
//     'post_id' => 456,
//     'subject' => 'HVAC repair services',
//     'intent' => 'on_topic',
//     'entities' => [
//         'brands' => ['ACME HVAC'],
//         'places' => ['Toronto', 'Canada'],
//         'people' => []
//     ],
//     'keywords' => ['hvac', 'repair', 'service', 'toronto', 'heating', 'cooling'],
//     'source_hash' => 'abc123...'
// ]
```

### Get Context (Cached)
```php
$context = $fusion->get_context( 123, 'es' );
// Returns cached context if source_hash matches
```

### Invalidate Context
```php
$fusion->invalidate_context( 123, 'es' ); // Specific locale
$fusion->invalidate_context( 123 );       // All locales
```

---

## Success Metrics

### Phase 2 Complete When:

**Database & Core:**
- ✅ `wp_msh_optimizer_context` table created with finalized schema
- ✅ `msh_ctx_source_hash()` function produces deterministic hashes
- ✅ Context records can be inserted, updated, queried

**Content Extraction:**
- ✅ `msh_ctx_list_media_in_post()` finds all image usages (featured, inline, gallery, ACF)
- ✅ InnerBlocks traversal works (columns, groups, template parts)
- ✅ `block_path` and `usage_type` tracked correctly

**Classification & Analysis:**
- ✅ Intent classifier produces `on_topic`, `off_topic`, `unknown` with confidence
- ✅ `rules_fired` logged for observability
- ✅ Keyword extraction removes stopwords per locale (en, es, fr)
- ✅ Entity extraction pulls from context profile + pattern matching

**Rollup & Caching:**
- ✅ `msh_ctx_get_rollup()` aggregates multiple context rows
- ✅ Rollup cached for 1 hour in WordPress object cache
- ✅ Cache invalidated on `save_post`

**Integration:**
- ✅ `save_post` hook refreshes context for all media in post
- ✅ AI generation can access context rollup
- ✅ 3 core WP-CLI commands working (extract, rollup, stats)
- ✅ 10 unit tests passing

### Quality Gates:
- ✅ No PHP fatal errors or warnings
- ✅ No database query errors
- ✅ Context extraction <200ms per image
- ✅ Handles edge cases:
  - Image not used in any post
  - Post is draft/trash
  - Post has no content
  - Multilingual content (en, es, fr)
- ✅ Rollup cache hit rate >80% (verify in logs)

---

## Future Enhancements (Phase 3+)

### Advanced Features (Deferred):
- LLM-based intent classification for edge cases
- Advanced NLP for keyword stemming (Porter Stemmer, SnowballStemmer)
- Named Entity Recognition (NER) via external library
- Synonym management per locale
- SEO plugin integration (Yoast, RankMath)
- Custom entity glossaries
- Machine learning for improved classification

---

## Questions & Decisions

### Q1: How to handle multilingual plugins (Polylang/WPML)?
**Decision:** Detect and use if available, fall back to WordPress core `get_locale()`

### Q2: Should we call LLM for intent classification in MVP?
**Decision:** No - use rule-based for MVP, add LLM as Phase 3 enhancement

### Q3: What if media has no post association?
**Decision:** Mark as `off_topic` unless filename strongly suggests relevance

### Q4: How to handle post updates after context extraction?
**Decision:** Hook into `save_post` and invalidate context when source_hash changes

### Q5: Should we extract context on every AI generation call?
**Decision:** No - cache context, only re-extract if source_hash changed

---

## Key Architectural Decisions

### 1. Granularity: (media_id, post_id, locale)
**Decision:** Store context per `(media_id, post_id, locale)` instead of just `(media_id, locale)`

**Rationale:**
- Same image may have different intent/context in different posts
- Example: Product image used in blog post (off_topic) vs product page (on_topic)
- Allows granular rollup with weighted scoring

### 2. Rollup Computed On-Demand
**Decision:** Don't store rollups in separate table, compute on-demand with caching

**Rationale:**
- Detail rows are cheap to store
- Rollups are expensive to keep in sync
- WordPress object cache provides 1-hour TTL
- Invalidate on `save_post` ensures freshness

### 3. Deterministic Source Hash
**Decision:** Use formula-based hash (not random or timestamp-based)

**Rationale:**
- Enables change detection (hash changes = content changed)
- Prevents unnecessary re-extraction
- Consistent across multiple calls

### 4. Rules-Fired Logging
**Decision:** Log which classification rules triggered in `rules_fired` JSON field

**Rationale:**
- Debugging and observability
- Understand why image classified as on_topic/off_topic
- Allows refining rules based on data

### 5. Simple Keyword/Entity Extraction (No ML)
**Decision:** Use stopwords + pattern matching instead of ML/NER

**Rationale:**
- MVP doesn't need perfect accuracy
- Faster and no external dependencies
- Easy to debug and extend
- LLM can compensate for imperfect extraction

---

## Next Steps

1. ✅ Plan finalized and reviewed
2. **Start Phase 2A:** Database & core infrastructure (2 hours)
3. Implement in order: 2A → 2B → 2C → 2D → 2E
4. Test with 10 unit tests + 5 manual integration tests
5. Integrate with AI generation workflow

**Estimated Total Time:** 9.5 hours (revised from 5-6 hours)
**Can Start:** Immediately (no blockers, Phase 4 complete)
**Parallel Work:** Other AI handling PHPCS docblocks (no conflicts expected)
