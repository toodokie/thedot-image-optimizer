# TinyDot Launch Implementation Plan

**Launch Target:** October 30, 2025 (4 days)
**Scope:** Phase 1 (Critical Fixes) + Phase 2 (Quality Enhancements) + Visual Confidence Indicators
**Total Estimated Effort:** 32 hours (4 full working days)

---

## Executive Summary

This plan combines findings from:
1. ✅ **Architecture Audit** - Complete codebase analysis with 500+ templates cataloged
2. ✅ **External Review** - Pragmatic implementation guide with ready-to-use code
3. ✅ **User Requirement** - Visual confidence indicators added before launch

**Strategy:** Fix critical bugs → Add quality features → Launch with confidence

---

## Table of Contents

1. [Phase 1: Critical Fixes (Day 1-2)](#phase-1-critical-fixes)
2. [Phase 2: Quality Enhancements (Day 3)](#phase-2-quality-enhancements)
3. [Visual Confidence System (Day 3-4)](#visual-confidence-system)
4. [Verification & Testing (Day 4)](#verification-testing)
5. [Implementation Details](#implementation-details)
6. [Success Criteria](#success-criteria)

---

## Phase 1: Critical Fixes

**Timeline:** Day 1-2 (16 hours)
**Priority:** BLOCKER - Must complete to ship

### 1.1 Builder Detection Guardrails (4 hours)

**Problem:** Plugin parses Elementor/Bricks shortcodes as H1/paragraph text
**Impact:** 40% of WordPress sites (builder market share)
**File:** `includes/class-msh-image-optimizer.php`

**Implementation:**

```php
/**
 * Detect if post uses page builder.
 *
 * @param WP_Post $post Post object to check
 * @return bool True if builder detected
 */
private function is_builder_content($post) {
    if (!$post || !isset($post->post_content)) {
        return false;
    }

    $content = $post->post_content;

    // Check for common builder shortcodes
    $builder_patterns = array(
        '/\[elementor-template/',
        '/\[vc_row/',
        '/\[et_pb_/',
        '/\[fusion_builder_/',
        '/\[bricks/',
        '/data-elementor-type=/',
    );

    foreach ($builder_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }

    // Check for builder meta flags
    $builder_meta_keys = array(
        '_elementor_edit_mode',
        '_wpb_vc_js_status',
        '_et_pb_use_builder',
        '_fusion',
        '_bricks_page_content_2',
    );

    foreach ($builder_meta_keys as $meta_key) {
        $meta_value = get_post_meta($post->ID, $meta_key, true);
        if ($meta_value === 'builder' || $meta_value === 'yes' || $meta_value === 'active') {
            return true;
        }
    }

    return false;
}

/**
 * Update extract_lightweight_page_context() at line 1470
 */
private function extract_lightweight_page_context($attachment_id, $parent_post) {
    $context = array(
        'caption' => '',
        'heading' => '',
        'excerpt' => '',
    );

    // NEW: Detect builder content - skip parsing if builder detected
    if ($this->is_builder_content($parent_post)) {
        error_log('[MSH] Skipping builder content for post ' . $parent_post->ID);
        return $context;
    }

    // 1. Try to get image caption from WordPress
    $attachment = get_post($attachment_id);
    if ($attachment && !empty($attachment->post_excerpt)) {
        $context['caption'] = wp_strip_all_tags($attachment->post_excerpt);
    }

    // 2. For featured images, extract H1 and first paragraph from parent post
    // ... existing code continues ...
}
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` - Add `is_builder_content()` method after line 1469
- `includes/class-msh-image-optimizer.php` - Update `extract_lightweight_page_context()` at line 1470

**Testing:**
```bash
# Create test post with Elementor
# Upload featured image
# Verify H1/paragraph NOT extracted from builder shortcodes
```

---

### 1.2 Remove Healthcare Acronyms (2 hours)

**Problem:** Ontario-specific terms (WSIB, MVA) appear for all industries
**Impact:** Trust loss for non-healthcare, non-Ontario businesses
**Files:** Multiple template methods

**Find All Instances:**

```bash
# Use this to find all occurrences
grep -Rin 'WSIB\|MVA' includes/class-msh-image-optimizer.php
```

**Replacements:**

| Line | Before | After |
|------|--------|-------|
| 64 | "WSIB approved. MVA recovery." | "Insurance billing accepted. Accident recovery." |
| 69 | "WSIB claims supported" | "Insurance claims supported" |
| 79 | "WSIB approved provider" | "Licensed provider" |
| 84 | "WSIB approved. Direct billing." | "Direct billing available." |
| 89 | "MVA rehabilitation" | "Accident rehabilitation" |
| 94 | "WSIB workplace injury" | "Workplace injury" |
| 2840 | "WSIB approved programs" | "Licensed care programs" |

**Implementation:**

```php
// Line 64 - Physiotherapy default
'default' => 'Insurance billing accepted. Accident recovery. Priority scheduling.',

// Line 69 - Chiropractic default
'default' => 'Spinal care. Workplace injury treatment. Insurance claims supported.',

// Line 79 - Acupuncture default
'default' => 'Evidence-based acupuncture care. Licensed provider.',

// Line 84 - Rehabilitation default
'default' => 'Return-to-work programs. Licensed provider. Direct billing available.',

// Line 89 - Motor vehicle accident default
'default' => 'Accident rehabilitation with insurance coordination and direct billing.',

// Line 94 - Workplace injury default
'default' => 'Workplace injury rehabilitation with return-to-work planning.',

// Line 2840 - Facility template description
"Modern rehabilitation facility at {$this->business_name} {$this->location}. Professional physiotherapy and chiropractic clinic with specialized treatment rooms and licensed care programs."
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` lines 64, 69, 79, 84, 89, 94, 2840

**Verification:**
```bash
# After changes, this should return ZERO matches
grep -Rin 'WSIB\|MVA' includes/
```

---

### 1.3 Fix Missing Truncation Calls (2 hours)

**Problem:** Some filename generation paths don't truncate, producing 8-word slugs
**Impact:** SEO degradation, pricing strategy violation (3-4 word limit)
**File:** `includes/class-msh-image-optimizer.php`

**Lines Needing Fixes:**

```php
// Line 2084 - Team
// BEFORE:
return $this->slugify("{$this->business_name}-team-{$name}");

// AFTER:
$slug = $this->slugify("{$this->business_name}-team-{$name}");
return $this->truncate_slug($slug, 4);

// Line 2119 - Facility
// BEFORE:
return $this->slugify($this->business_name . '-facility-' . $this->location_slug);

// AFTER:
$slug = $this->slugify($this->business_name . '-facility-' . $this->location_slug);
return $this->truncate_slug($slug, 4);

// Line 2128 - Equipment with extracted keywords
// BEFORE:
return $this->slugify($extracted_keywords . '-equipment-' . $this->location_slug);

// AFTER:
$slug = $this->slugify($extracted_keywords . '-equipment-' . $this->location_slug);
return $this->truncate_slug($slug, 4);

// Line 2144 - Equipment product variant
// BEFORE:
return $this->slugify(implode('-', $components));

// AFTER:
$slug = $this->slugify(implode('-', $components));
return $this->truncate_slug($slug, 4);

// Line 2153 - Equipment with descriptor
// BEFORE:
return $this->slugify($descriptor_slug . '-equipment' . $location_suffix);

// AFTER:
$slug = $this->slugify($descriptor_slug . '-equipment' . $location_suffix);
return $this->truncate_slug($slug, 4);

// Line 2158 - Equipment fallback
// BEFORE:
return $this->slugify('equipment-showcase' . $location_suffix);

// AFTER:
$slug = $this->slugify('equipment-showcase' . $location_suffix);
return $this->truncate_slug($slug, 4);
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` lines 2084, 2119, 2128, 2144, 2153, 2158

**Testing:**
```php
// Add temporary logging
error_log('[MSH] Generated filename: ' . $slug . ' | Word count: ' . count(explode('-', $slug)));

// Upload test images for each scene type
// Verify ALL suggestions ≤4 words
```

---

### 1.4 Fix Toggle OFF UI Hiding (1 hour)

**Problem:** When file rename toggle is OFF, existing suggestions still show in UI
**Impact:** User confusion - "I turned it off, why do I still see suggestions?"
**File:** `admin/image-optimizer-admin.php`

**Implementation:**

Find the column rendering code (likely around line 500-800) and add:

```php
/**
 * Render filename suggestion column
 */
public function render_filename_suggestion_column($column_name, $attachment_id) {
    if ($column_name !== 'msh_filename_suggestion') {
        return;
    }

    // NEW: Check if feature is enabled
    $optimizer = MSH_Image_Optimizer::get_instance();
    if (!$optimizer->is_file_rename_enabled()) {
        echo '<em style="color: #999;">Feature disabled</em>';
        return;
    }

    // Existing rendering logic...
    $suggestion = get_post_meta($attachment_id, '_msh_suggested_filename', true);
    if (empty($suggestion)) {
        echo '<em>No suggestion</em>';
        return;
    }

    // Render suggestion UI...
}
```

**Alternative: Make is_file_rename_enabled() public**

In `includes/class-msh-image-optimizer.php` line 5413:

```php
// Change from private to public
public function is_file_rename_enabled() {
    // ... existing code ...
}
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` line 5413 - Change `private` to `public`
- `admin/image-optimizer-admin.php` - Add feature check before rendering

---

### 1.5 Fix File Path Empty Check (1 hour)

**Problem:** 8 images skipped because `file_path` is empty
**Impact:** "44/52 images" instead of "52/52"
**File:** `includes/class-msh-image-optimizer.php`

**Implementation:**

Line 6162 in `get_published_images()`:

```php
// BEFORE:
if (empty($attachment['file_path'])) {
    continue;  // Skipped entirely!
}

// AFTER:
if (empty($attachment['file_path'])) {
    // Try file resolver as fallback
    $file_path = MSH_File_Resolver::find_attachment_file($attachment['id']);

    if (!$file_path) {
        // Include in results but flag as missing file
        $attachment['file_path'] = '';
        $attachment['missing_file'] = true;
        $attachment['warning'] = __('File not found on disk', 'msh-image-optimizer');
    } else {
        $attachment['file_path'] = $file_path;
    }
}

// Continue processing all attachments, including those with missing files
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` line 6162

---

### 1.6 Fix Industry Override Lock (2 hours)

**Problem:** Context detection overrides global business industry setting
**Impact:** Medical clinic images detected as "web_design" industry
**File:** `includes/class-msh-image-optimizer.php`

**Implementation:**

Add to onboarding context schema in `class-msh-context-helper.php`:

```php
// In sanitize_context() method around line 27
$sanitized = array(
    'business_name'   => isset($context['business_name']) ? sanitize_text_field($context['business_name']) : '',
    'industry'        => isset($context['industry']) ? sanitize_text_field($context['industry']) : '',
    // NEW: Add industry lock flag
    'lock_industry'   => !empty($context['lock_industry']),
    // ... rest of fields ...
);
```

Update `detect_context()` in `class-msh-image-optimizer.php` around line 1192:

```php
$context = array(
    'type'                 => $this->get_default_context_type(),
    'page_type'            => null,
    'page_title'           => null,
    'service'              => $this->get_default_service_slug($this->industry),
    'parent_id'            => 0,
    'tags'                 => array(),
    'manual'               => false,
    'attachment_id'        => (int) $attachment_id,
    'attachment_title'     => '',
    'attachment_slug'      => '',
    'file_basename'        => '',
    'subject_name'         => '',
    'active_profile_id'    => $this->active_profile_id,
    'active_profile_label' => $this->active_profile_label,
    'industry'             => $this->industry,  // Start with global industry
    // ... rest ...
);

// LATER in the method, before returning context:
// NEW: Enforce industry lock if enabled
if (!empty($this->active_context['lock_industry']) && $this->active_context['lock_industry']) {
    // Override any detected industry with global setting
    $context['industry'] = $this->industry;
    $context['industry_locked'] = true;
}
```

**Files to Edit:**
- `includes/class-msh-context-helper.php` line ~27
- `includes/class-msh-image-optimizer.php` line ~1390 (end of detect_context method)

---

## Phase 2: Quality Enhancements

**Timeline:** Day 3 (8 hours)
**Priority:** HIGH - Improves user experience significantly

### 2.1 Implement Confidence Scoring Logic (2 hours)

**File:** `includes/class-msh-image-optimizer.php`

**Add new method:**

```php
/**
 * Calculate confidence score for metadata/filename suggestion.
 *
 * Score inputs:
 * +2 if scene tokens matched
 * +1 if featured image context found
 * +1 if attachment caption exists
 * +1 if business name in filename
 * +1 if location in filename
 * -2 if scene is unknown
 * -1 if filename has generic words
 *
 * @param int   $attachment_id Attachment ID
 * @param array $context       Context array
 * @param string $filename     Suggested filename
 * @return int Score (0-100)
 */
private function calculate_confidence_score($attachment_id, $context, $filename = '') {
    $score = 50; // Base score

    // Scene detection quality
    if (!empty($context['type']) && $context['type'] !== 'unknown') {
        $score += 10;

        // Higher score if scene came from token matching
        if (!empty($context['scene_source']) && $context['scene_source'] === 'token_match') {
            $score += 10;
        }
    } else {
        $score -= 10;
    }

    // Manual override is always high confidence
    if (!empty($context['manual']) && $context['manual'] === true) {
        return 100;
    }

    // Featured image context adds confidence
    if (!empty($context['page_heading'])) {
        $score += 5;
    }

    // Attachment caption exists
    $attachment = get_post($attachment_id);
    if ($attachment && !empty($attachment->post_excerpt)) {
        $score += 5;
    }

    // Filename quality (if provided)
    if (!empty($filename)) {
        $filename_score = $this->score_filename_quality($filename);
        $score += $filename_score;
    }

    // Parent context found
    if (!empty($context['parent_id']) && $context['parent_id'] > 0) {
        $score += 5;
    }

    // Usage context found
    if (!empty($context['usage_count']) && $context['usage_count'] > 0) {
        $score += 5;
    }

    // Clamp to 0-100 range
    return max(0, min(100, $score));
}

/**
 * Get confidence level from score.
 *
 * @param int $score Confidence score (0-100)
 * @return string 'high'|'medium'|'low'
 */
private function get_confidence_level($score) {
    if ($score >= 70) {
        return 'high';
    } elseif ($score >= 40) {
        return 'medium';
    } else {
        return 'low';
    }
}
```

**Update analyze_single_image() to store confidence:**

```php
// Around line 6500 in analyze_single_image()
$suggested_filename = $this->generate_filename_slug($attachment_id, $context);

// NEW: Calculate and store confidence
$confidence_score = $this->calculate_confidence_score($attachment_id, $context, $suggested_filename);
update_post_meta($attachment_id, '_msh_confidence_score', $confidence_score);
update_post_meta($attachment_id, '_msh_confidence_level', $this->get_confidence_level($confidence_score));

error_log('[MSH] Attachment ' . $attachment_id . ' confidence: ' . $confidence_score);
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` - Add methods around line 6950 (near score_filename_quality)
- `includes/class-msh-image-optimizer.php` - Update line ~6500 to store confidence

---

### 2.2 Add Visual Confidence Indicators (3 hours)

**Files:** Admin UI files

**Add CSS for confidence badges:**

Create/edit `admin/css/confidence-indicators.css`:

```css
/* Confidence Badge Styles */
.msh-confidence {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.msh-confidence.high {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.msh-confidence.medium {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.msh-confidence.low {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Star Rating Styles */
.msh-stars {
    display: inline-block;
    color: #ffc107;
    font-size: 14px;
    margin-left: 5px;
}

.msh-stars.high::before {
    content: "★★★";
}

.msh-stars.medium::before {
    content: "★★☆";
}

.msh-stars.low::before {
    content: "★☆☆";
}

/* Grid Item Highlight */
.attachment.msh-low-confidence {
    border: 2px solid #ffc107 !important;
    box-shadow: 0 0 5px rgba(255, 193, 7, 0.5);
}

.attachment.msh-low-confidence::after {
    content: "⚠ Review Needed";
    position: absolute;
    top: 5px;
    right: 5px;
    background: #ffc107;
    color: #000;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: bold;
}
```

**Update admin rendering:**

In `admin/image-optimizer-admin.php` (find the suggestion column render):

```php
/**
 * Render confidence indicator in filename suggestion column
 */
public function render_confidence_indicator($attachment_id) {
    $score = (int) get_post_meta($attachment_id, '_msh_confidence_score', true);
    $level = get_post_meta($attachment_id, '_msh_confidence_level', true);

    if (empty($level)) {
        return;
    }

    // Badge
    $labels = array(
        'high'   => __('High Confidence', 'msh-image-optimizer'),
        'medium' => __('Medium Confidence', 'msh-image-optimizer'),
        'low'    => __('Review Needed', 'msh-image-optimizer'),
    );

    echo '<span class="msh-confidence ' . esc_attr($level) . '">';
    echo esc_html($labels[$level]);
    echo '</span>';

    // Stars
    echo '<span class="msh-stars ' . esc_attr($level) . '"></span>';

    // Score tooltip
    echo ' <span class="msh-score-tooltip" title="' . sprintf(__('Score: %d/100', 'msh-image-optimizer'), $score) . '">';
    echo '(' . $score . ')';
    echo '</span>';
}

/**
 * Add CSS class to low-confidence grid items
 */
public function add_grid_confidence_class($classes, $id) {
    $level = get_post_meta($id, '_msh_confidence_level', true);

    if ($level === 'low') {
        $classes[] = 'msh-low-confidence';
    }

    return $classes;
}

// Hook it in __construct():
add_filter('wp_prepare_attachment_for_js', array($this, 'add_grid_confidence_class'), 10, 2);
```

**Enqueue CSS:**

```php
// In admin page __construct() or init method:
wp_enqueue_style(
    'msh-confidence-indicators',
    plugins_url('css/confidence-indicators.css', __FILE__),
    array(),
    '1.0.0'
);
```

**Files to Create:**
- `admin/css/confidence-indicators.css`

**Files to Edit:**
- `admin/image-optimizer-admin.php` - Add rendering methods
- `admin/image-optimizer-admin.php` - Enqueue CSS

---

### 2.3 Integrate Starter Templates (2 hours)

**File:** `includes/class-msh-image-optimizer.php`

**Add token matching integration:**

```php
/**
 * Try to match starter templates using token-based detection.
 *
 * @param int   $attachment_id Attachment ID
 * @param array $context       Current context
 * @return array|null Matched template data or null
 */
private function match_starter_template($attachment_id, $context) {
    // Load starter templates
    if (!function_exists('msh_get_starter_templates')) {
        require_once plugin_dir_path(__FILE__) . 'data/starter-templates.php';
    }

    $templates = msh_get_starter_templates();
    $attachment = get_post($attachment_id);

    // Tokenize filename + title
    $tokens = $this->tokenize_for_template_matching(
        $attachment->post_title,
        $context['file_basename']
    );

    $best_match = null;
    $best_score = 0;

    foreach ($templates as $template) {
        // Skip inactive templates
        if (empty($template['is_active'])) {
            continue;
        }

        $score = $this->calculate_template_match_score($template, $tokens);

        if ($score > $best_score && $score >= ($template['confidence_threshold'] ?? 0.7) * 100) {
            $best_score = $score;
            $best_match = $template;
        }
    }

    if ($best_match) {
        error_log('[MSH] Starter template match: ' . $best_match['name'] . ' (score: ' . $best_score . ')');

        return array(
            'template_name' => $best_match['name'],
            'template_match_score' => $best_score,
            'scene_source' => 'token_match',  // Used in confidence calculation
        );
    }

    return null;
}

/**
 * Tokenize text for template matching.
 *
 * @param string $title Title text
 * @param string $filename Filename
 * @return array Unique tokens
 */
private function tokenize_for_template_matching($title, $filename) {
    $text = strtolower($title . ' ' . $filename);
    $text = remove_accents($text);

    // Split on non-alphanumeric
    $parts = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    return array_values(array_unique($parts));
}

/**
 * Calculate match score for template.
 *
 * @param array $template Template definition
 * @param array $tokens   Tokens from filename/title
 * @return int Score (0-100)
 */
private function calculate_template_match_score($template, $tokens) {
    $score = 0;

    // Required tokens - ALL must match
    $required = json_decode($template['required_tokens'], true) ?? array();
    $required_matches = count(array_intersect($tokens, $required));

    if (count($required) > 0 && $required_matches < count($required)) {
        return 0; // Failed required check
    }

    $score += $required_matches * 30;

    // Negative tokens - NONE should match
    $negative = json_decode($template['negative_tokens'], true) ?? array();
    $negative_matches = count(array_intersect($tokens, $negative));

    if ($negative_matches > 0) {
        return 0; // Has negative tokens, disqualify
    }

    // Nice-to-have tokens - bonus points
    $nice = json_decode($template['nice_to_have_tokens'], true) ?? array();
    $nice_matches = count(array_intersect($tokens, $nice));

    $score += $nice_matches * 10;

    return min(100, $score);
}

/**
 * Update detect_context() to use starter templates.
 * Insert at line ~1200, before manual override check:
 */
public function detect_context($attachment_id, $ignore_manual = false) {
    $this->ensure_fresh_context();
    $this->hydrate_active_context();

    // ... existing base context setup ...

    // NEW: Try starter template matching first
    $template_match = $this->match_starter_template($attachment_id, $context);
    if ($template_match) {
        $context = array_merge($context, $template_match);
    }

    // ... rest of existing detection logic ...
}
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` - Add template matching methods around line 1850
- `includes/class-msh-image-optimizer.php` - Update `detect_context()` at line ~1200

---

### 2.4 Add Dominant Color Extraction (1 hour)

**File:** Create new `includes/class-msh-color-analyzer.php`

```php
<?php
/**
 * MSH Color Analyzer
 *
 * Extracts dominant color from images using ImageMagick or GD fallback.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_Color_Analyzer {

    /**
     * Singleton instance.
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get dominant color from attachment.
     *
     * @param int $attachment_id Attachment ID
     * @return string|null Hex color code or null
     */
    public function get_dominant_color($attachment_id) {
        // Check cache first
        $cached = get_post_meta($attachment_id, '_msh_dominant_color', true);
        if (!empty($cached)) {
            return $cached;
        }

        $file_path = MSH_File_Resolver::find_attachment_file($attachment_id);
        if (!$file_path || !file_exists($file_path)) {
            return null;
        }

        // Try ImageMagick first (more accurate)
        if (extension_loaded('imagick')) {
            $color = $this->extract_color_imagick($file_path);
        }
        // Fallback to GD
        elseif (function_exists('imagecreatefromjpeg')) {
            $color = $this->extract_color_gd($file_path);
        }
        else {
            error_log('[MSH Color] Neither ImageMagick nor GD available');
            return null;
        }

        if ($color) {
            // Cache it
            update_post_meta($attachment_id, '_msh_dominant_color', $color);
            update_post_meta($attachment_id, '_msh_dominant_color_name', $this->hex_to_name($color));
        }

        return $color;
    }

    /**
     * Extract dominant color using ImageMagick.
     */
    private function extract_color_imagick($file_path) {
        try {
            $image = new Imagick($file_path);

            // Resize to 1x1 to get average color
            $image->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);

            // Get pixel color
            $pixel = $image->getImagePixelColor(0, 0);
            $color = $pixel->getColor();

            $hex = sprintf("#%02x%02x%02x", $color['r'], $color['g'], $color['b']);

            $image->clear();
            $image->destroy();

            return $hex;
        } catch (Exception $e) {
            error_log('[MSH Color] ImageMagick error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract dominant color using GD.
     */
    private function extract_color_gd($file_path) {
        $mime = mime_content_type($file_path);

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($file_path);
                break;
            default:
                return null;
        }

        if (!$image) {
            return null;
        }

        // Resize to 1x1
        $resized = imagecreatetruecolor(1, 1);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        // Get pixel color
        $rgb = imagecolorat($resized, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        imagedestroy($image);
        imagedestroy($resized);

        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

    /**
     * Convert hex color to common color name.
     */
    private function hex_to_name($hex) {
        // Convert hex to RGB
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Basic color mapping
        $colors = array(
            'red'    => array(255, 0, 0),
            'green'  => array(0, 255, 0),
            'blue'   => array(0, 0, 255),
            'yellow' => array(255, 255, 0),
            'orange' => array(255, 165, 0),
            'purple' => array(128, 0, 128),
            'pink'   => array(255, 192, 203),
            'brown'  => array(165, 42, 42),
            'gray'   => array(128, 128, 128),
            'white'  => array(255, 255, 255),
            'black'  => array(0, 0, 0),
        );

        // Find closest color by Euclidean distance
        $min_distance = PHP_INT_MAX;
        $closest_color = 'unknown';

        foreach ($colors as $name => $rgb_values) {
            $distance = sqrt(
                pow($r - $rgb_values[0], 2) +
                pow($g - $rgb_values[1], 2) +
                pow($b - $rgb_values[2], 2)
            );

            if ($distance < $min_distance) {
                $min_distance = $distance;
                $closest_color = $name;
            }
        }

        return $closest_color;
    }
}
```

**Integrate into optimizer:**

In `includes/class-msh-image-optimizer.php` around line 1300 in `detect_context()`:

```php
// NEW: Extract dominant color if available
if (class_exists('MSH_Color_Analyzer')) {
    $color_analyzer = MSH_Color_Analyzer::get_instance();
    $dominant_color = $color_analyzer->get_dominant_color($attachment_id);

    if ($dominant_color) {
        $context['dominant_color'] = $dominant_color;
        $context['dominant_color_name'] = get_post_meta($attachment_id, '_msh_dominant_color_name', true);
    }
}
```

**Files to Create:**
- `includes/class-msh-color-analyzer.php`

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` line ~1300
- `msh-image-optimizer.php` - Add `require_once` for color analyzer

---

## Visual Confidence System

**Timeline:** Day 3-4 (integrated with Phase 2)
**Already covered above in sections 2.1 and 2.2**

**Additional UI locations to add confidence indicators:**

### Media Grid View

```javascript
// In admin/js/media-library-integration.js
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add confidence badges to grid view
        wp.media.view.Attachment.prototype.on('ready', function() {
            var confidence = this.model.get('msh_confidence_level');

            if (confidence === 'low') {
                this.$el.addClass('msh-low-confidence');
            }
        });
    });
})(jQuery);
```

### Bulk Actions Dropdown

Add option: "Review Low Confidence Items"

```php
// In admin/image-optimizer-admin.php
public function add_bulk_actions($actions) {
    $actions['msh_review_low_confidence'] = __('Review Low Confidence', 'msh-image-optimizer');
    return $actions;
}

public function handle_bulk_action($redirect_to, $action, $post_ids) {
    if ($action === 'msh_review_low_confidence') {
        // Filter to show only low confidence items
        $low_confidence_ids = array();

        foreach ($post_ids as $post_id) {
            $level = get_post_meta($post_id, '_msh_confidence_level', true);
            if ($level === 'low') {
                $low_confidence_ids[] = $post_id;
            }
        }

        $redirect_to = add_query_arg('msh_low_confidence', count($low_confidence_ids), $redirect_to);
    }

    return $redirect_to;
}
```

---

## Verification & Testing

**Timeline:** Day 4 (4 hours)

### 4.1 Grep Validation Commands (30 min)

Run these to verify all fixes applied:

```bash
# 1. Ensure all healthcare acronyms removed
echo "=== Checking for WSIB/MVA ==="
grep -Rin 'WSIB\|MVA' includes/
# Expected: NO RESULTS

# 2. Ensure truncation applied
echo "=== Checking truncation calls ==="
grep -n 'truncate_slug' includes/class-msh-image-optimizer.php | grep 'return'
# Expected: Lines 2084, 2119, 2128, 2144, 2153, 2158 should appear

# 3. Check builder detection added
echo "=== Checking builder detection ==="
grep -n 'is_builder_content' includes/class-msh-image-optimizer.php
# Expected: Method definition + call in extract_lightweight_page_context

# 4. Confirm toggle UI fix
echo "=== Checking toggle UI ==="
grep -n 'Feature disabled' admin/image-optimizer-admin.php
# Expected: Found in column rendering

# 5. Verify confidence scoring
echo "=== Checking confidence scoring ==="
grep -n '_msh_confidence_score' includes/class-msh-image-optimizer.php
# Expected: calculate_confidence_score method + update_post_meta calls

# 6. Verify color analyzer
echo "=== Checking color analyzer ==="
ls -la includes/class-msh-color-analyzer.php
# Expected: File exists

# 7. Verify CSS exists
echo "=== Checking confidence CSS ==="
ls -la admin/css/confidence-indicators.css
# Expected: File exists
```

### 4.2 WP-CLI Smoke Test (30 min)

Create `includes/class-msh-smoke-test-cli.php`:

```php
<?php
/**
 * WP-CLI smoke tests for TinyDot non-AI fixes.
 */

if (!class_exists('WP_CLI')) {
    return;
}

class MSH_Smoke_Test_CLI extends WP_CLI_Command {

    /**
     * Run smoke tests for Phase 1-2 fixes.
     *
     * ## EXAMPLES
     *
     *     wp msh smoke test-all
     *
     * @subcommand test-all
     */
    public function test_all($args, $assoc_args) {
        WP_CLI::log('=== TinyDot Smoke Tests ===');
        WP_CLI::log('');

        $this->test_builder_detection();
        $this->test_truncation();
        $this->test_confidence_scoring();
        $this->test_color_extraction();
        $this->test_toggle_state();

        WP_CLI::success('All smoke tests completed!');
    }

    /**
     * Test builder detection.
     */
    private function test_builder_detection() {
        WP_CLI::log('Testing builder detection...');

        $optimizer = MSH_Image_Optimizer::get_instance();

        // Create test post with Elementor shortcode
        $post_id = wp_insert_post(array(
            'post_title' => 'Test Elementor Page',
            'post_content' => '[elementor-template id="123"]<h1>Test Heading</h1>',
            'post_status' => 'publish',
        ));

        $post = get_post($post_id);

        // Use reflection to call private method
        $reflection = new ReflectionClass($optimizer);
        $method = $reflection->getMethod('is_builder_content');
        $method->setAccessible(true);

        $is_builder = $method->invoke($optimizer, $post);

        if ($is_builder) {
            WP_CLI::success('✓ Builder detection working');
        } else {
            WP_CLI::error('✗ Builder detection FAILED');
        }

        wp_delete_post($post_id, true);
    }

    /**
     * Test truncation.
     */
    private function test_truncation() {
        WP_CLI::log('Testing filename truncation...');

        $optimizer = MSH_Image_Optimizer::get_instance();

        $reflection = new ReflectionClass($optimizer);
        $method = $reflection->getMethod('truncate_slug');
        $method->setAccessible(true);

        $long_slug = 'this-is-a-very-long-filename-slug-with-many-words';
        $truncated = $method->invoke($optimizer, $long_slug, 4);

        $word_count = count(explode('-', $truncated));

        if ($word_count <= 4) {
            WP_CLI::success('✓ Truncation working (4 words: ' . $truncated . ')');
        } else {
            WP_CLI::error('✗ Truncation FAILED (' . $word_count . ' words)');
        }
    }

    /**
     * Test confidence scoring.
     */
    private function test_confidence_scoring() {
        WP_CLI::log('Testing confidence scoring...');

        $optimizer = MSH_Image_Optimizer::get_instance();

        $reflection = new ReflectionClass($optimizer);

        if (!$reflection->hasMethod('calculate_confidence_score')) {
            WP_CLI::error('✗ Confidence scoring method missing');
            return;
        }

        WP_CLI::success('✓ Confidence scoring method exists');
    }

    /**
     * Test color extraction.
     */
    private function test_color_extraction() {
        WP_CLI::log('Testing color extraction...');

        if (!class_exists('MSH_Color_Analyzer')) {
            WP_CLI::error('✗ Color analyzer class missing');
            return;
        }

        $analyzer = MSH_Color_Analyzer::get_instance();

        if (extension_loaded('imagick')) {
            WP_CLI::success('✓ ImageMagick available');
        } elseif (function_exists('imagecreatefromjpeg')) {
            WP_CLI::log('ℹ GD fallback available (ImageMagick preferred)');
        } else {
            WP_CLI::warning('⚠ Neither ImageMagick nor GD available');
        }
    }

    /**
     * Test toggle state.
     */
    private function test_toggle_state() {
        WP_CLI::log('Testing rename toggle...');

        $optimizer = MSH_Image_Optimizer::get_instance();

        if (!method_exists($optimizer, 'is_file_rename_enabled')) {
            WP_CLI::error('✗ Toggle method missing');
            return;
        }

        // Check if method is public
        $reflection = new ReflectionMethod($optimizer, 'is_file_rename_enabled');

        if ($reflection->isPublic()) {
            WP_CLI::success('✓ Toggle method is public');
        } else {
            WP_CLI::error('✗ Toggle method is not public');
        }
    }
}

WP_CLI::add_command('msh smoke', 'MSH_Smoke_Test_CLI');
```

Run tests:

```bash
wp msh smoke test-all
```

**Expected output:**
```
=== TinyDot Smoke Tests ===

Testing builder detection...
✓ Builder detection working
Testing filename truncation...
✓ Truncation working (4 words: this-is-a-very)
Testing confidence scoring...
✓ Confidence scoring method exists
Testing color extraction...
✓ ImageMagick available
Testing rename toggle...
✓ Toggle method is public
Success: All smoke tests completed!
```

### 4.3 Manual QA Checklist (2 hours)

**Test Site:** http://thedot-optimizer-test.local/

**Scenarios to Test:**

#### Scenario 1: Builder Site
- [ ] Create page with Elementor
- [ ] Add `<h1>Contact Us</h1>` in Elementor widget
- [ ] Upload featured image
- [ ] **Expected:** H1 NOT extracted into metadata
- [ ] **Verify:** Check `_msh_suggested_filename` meta doesn't contain "contact"

#### Scenario 2: Long Filename
- [ ] Upload image: `main-street-health-rehabilitation-facility-toronto-ontario-canada.jpg`
- [ ] Analyze image
- [ ] **Expected:** Suggestion truncated to ≤4 words
- [ ] **Verify:** Count hyphens in suggestion

#### Scenario 3: Toggle OFF
- [ ] Toggle "Enable File Rename" to OFF
- [ ] Go to Media Library
- [ ] **Expected:** See "Feature disabled" instead of suggestions
- [ ] Toggle back ON
- [ ] **Expected:** Suggestions reappear

#### Scenario 4: Confidence Indicators
- [ ] Upload 3 images:
   - `exterior-building.jpg` (should be HIGH)
   - `image1234.jpg` (should be LOW)
   - `team-photo.jpg` (should be MEDIUM)
- [ ] Analyze all
- [ ] **Expected:** See colored badges (green/yellow/red)
- [ ] **Expected:** Low confidence has amber border in grid
- [ ] **Verify:** Meta `_msh_confidence_level` matches visual indicator

#### Scenario 5: Dominant Color
- [ ] Upload red image
- [ ] Analyze
- [ ] **Expected:** `_msh_dominant_color` meta = "#ff0000" (ish)
- [ ] **Expected:** `_msh_dominant_color_name` meta = "red"

#### Scenario 6: No Healthcare Terms
- [ ] Set industry to "legal" in onboarding
- [ ] Upload facility image
- [ ] **Expected:** NO mention of WSIB, MVA, rehabilitation
- [ ] **Verify:** Alt text, description, caption

#### Scenario 7: Industry Lock
- [ ] Set industry to "medical"
- [ ] Enable "Lock Industry" setting
- [ ] Upload image named "web-design-screenshot.jpg"
- [ ] **Expected:** Industry stays "medical", not detected as "web_design"

### 4.4 Regression Testing (1 hour)

**Test Previous Bug Fixes:**

#### Bug #1: Attachment Deletion (from earlier session)
- [ ] Try to delete attachment 1686
- [ ] **Expected:** Deletes successfully, no 500 error
- [ ] **Verify:** Debug log has no fatal errors

#### Bug #5: File Rename Toggle (from earlier session)
- [ ] Upload new image
- [ ] Toggle rename OFF before analysis
- [ ] **Expected:** No filename suggestion generated
- [ ] Toggle back ON
- [ ] Upload another image
- [ ] **Expected:** Filename suggestion generated

#### Bug #6: Missing Images (from earlier session)
- [ ] Run analysis
- [ ] **Expected:** "52/52 images" not "44/52"
- [ ] **Verify:** All images appear in results, even if file missing

---

## Implementation Details

### Git Workflow

**Branch Strategy:**

```bash
# Create feature branch
git checkout -b feature/launch-fixes-phase1-2

# Work on fixes, commit frequently
git add -A
git commit -m "feat: Add builder detection guardrails (GAP-001)"

# After all Phase 1 complete
git commit -m "feat: Phase 1 critical fixes complete

- Builder detection prevents shortcode parsing
- WSIB/MVA acronyms removed (12 instances)
- Truncation applied to all filename paths
- Toggle OFF now hides suggestions in UI
- File path resolver handles missing files
- Industry lock prevents auto-detection override

Fixes: GAP-001, GAP-003, GAP-004, GAP-005, GAP-008, GAP-009"

# After all Phase 2 complete
git commit -m "feat: Phase 2 quality enhancements complete

- Confidence scoring with 0-100 scale
- Visual indicators (stars, badges, borders)
- Starter template token matching active
- Dominant color extraction via Imagick/GD

Fixes: GAP-006, GAP-010, GAP-011"

# Push to remote
git push origin feature/launch-fixes-phase1-2

# Create PR
gh pr create --title "Launch Fixes: Phase 1-2 + Confidence Indicators" \
             --body "$(cat <<EOF
## Summary
Complete Phase 1 critical fixes and Phase 2 quality enhancements for Oct 30 launch.

## Changes
### Phase 1 (Critical)
- ✅ Builder detection (GAP-001)
- ✅ Remove WSIB/MVA (GAP-003)
- ✅ Fix truncation (GAP-004)
- ✅ Toggle UI (GAP-005)
- ✅ File path check (GAP-008)
- ✅ Industry lock (GAP-009)

### Phase 2 (Quality)
- ✅ Confidence scoring (GAP-011)
- ✅ Visual indicators
- ✅ Starter templates (GAP-010)
- ✅ Dominant color (GAP-006)

## Testing
- ✅ All grep validations pass
- ✅ WP-CLI smoke tests pass
- ✅ Manual QA completed
- ✅ Regression tests pass

## Effort
- Estimated: 32 hours
- Actual: [FILL IN]

## Screenshots
[Add before/after screenshots of confidence indicators]

## Risks
- Low: All changes defensive, fallbacks in place
- Color extraction requires Imagick/GD (fallback to skip)

## Deploy Notes
- Run `wp msh smoke test-all` after deploy
- Verify no WSIB/MVA in production metadata
- Check confidence badges render correctly

🚀 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

### File Checklist

**Files to Create:**
- [ ] `includes/class-msh-color-analyzer.php`
- [ ] `includes/class-msh-smoke-test-cli.php`
- [ ] `admin/css/confidence-indicators.css`
- [ ] `admin/js/media-library-integration.js`

**Files to Edit:**
- [ ] `includes/class-msh-image-optimizer.php` (multiple sections)
- [ ] `includes/class-msh-context-helper.php`
- [ ] `admin/image-optimizer-admin.php`
- [ ] `msh-image-optimizer.php` (add requires)

**Total Files:** 4 new, 4 edited

---

## Success Criteria

### Functional Requirements

✅ **Phase 1 Complete When:**
- [ ] No WSIB/MVA in any template (grep returns 0)
- [ ] All filenames ≤4 words (100% compliance)
- [ ] Builder pages don't extract H1 from shortcodes
- [ ] Toggle OFF shows "Feature disabled"
- [ ] All 52/52 images appear in analysis
- [ ] Industry lock respected when enabled

✅ **Phase 2 Complete When:**
- [ ] Confidence score calculated for all suggestions
- [ ] Visual indicators visible in Media Library
- [ ] Low confidence items highlighted with amber border
- [ ] Starter templates matching and logging matches
- [ ] Dominant color extracted and stored in meta

### Quality Metrics

| Metric | Target | How to Verify |
|--------|--------|---------------|
| **No healthcare terms on non-medical** | 100% | Upload facility image as "legal" industry |
| **Filename length compliance** | 100% ≤4 words | Upload 10 images, check all suggestions |
| **Builder detection accuracy** | 100% | Test with Elementor, Bricks, Divi |
| **Toggle behavior correctness** | 100% | Toggle OFF → no suggestions in UI |
| **Confidence scoring coverage** | 100% | All analyzed images have `_msh_confidence_level` |
| **Visual indicator rendering** | 100% | Stars, badges, borders all display |
| **Color extraction success** | 80%+ | Varies by image format support |

### Performance Requirements

- [ ] Confidence calculation adds <100ms per image
- [ ] Color extraction adds <500ms per image
- [ ] Builder detection adds <50ms per image
- [ ] No SQL N+1 queries introduced
- [ ] Batch analysis completes 50 images in <30 seconds

### User Experience

- [ ] Low confidence items obvious at a glance (amber border)
- [ ] Confidence tooltips explain score
- [ ] Category dropdown quick-pick works
- [ ] No confusing healthcare terms for non-healthcare
- [ ] Filenames concise and readable

---

## Rollback Plan

**If critical bug found after deploy:**

```bash
# Revert to previous version
git revert HEAD
git push origin main

# Or reset to last known good commit
git reset --hard <commit-hash>
git push --force origin main

# Emergency disable via wp-config.php
define('MSH_DISABLE_CONFIDENCE_INDICATORS', true);
define('MSH_DISABLE_COLOR_EXTRACTION', true);
```

**Feature flags to add (future):**

```php
// In msh-image-optimizer.php
if (!defined('MSH_ENABLE_BUILDER_DETECTION')) {
    define('MSH_ENABLE_BUILDER_DETECTION', true);
}

if (!defined('MSH_ENABLE_COLOR_EXTRACTION')) {
    define('MSH_ENABLE_COLOR_EXTRACTION', extension_loaded('imagick'));
}

if (!defined('MSH_ENABLE_CONFIDENCE_UI')) {
    define('MSH_ENABLE_CONFIDENCE_UI', true);
}
```

---

## Timeline Summary

| Day | Hours | Tasks | Deliverables |
|-----|-------|-------|--------------|
| **Day 1** | 8h | Phase 1.1-1.3 | Builder detection, WSIB removal, truncation fixes |
| **Day 2** | 8h | Phase 1.4-1.6 | Toggle UI, file path, industry lock |
| **Day 3** | 8h | Phase 2.1-2.4 | Confidence scoring, visual indicators, templates, color |
| **Day 4** | 8h | Testing & QA | Verification, smoke tests, regression, final polish |
| **Total** | **32h** | | **Launch-ready plugin** |

---

## Post-Launch Monitoring

**Week 1 Metrics to Track:**

- [ ] Error rate for builder detection (false positives/negatives)
- [ ] Confidence score distribution (% high/medium/low)
- [ ] Color extraction success rate
- [ ] User acceptance rate of suggestions (high vs low confidence)
- [ ] Support tickets related to:
  - Healthcare terms appearing incorrectly
  - Long filenames
  - Toggle confusion

**Week 2-4: Template Registry Migration**

Start Phase 3 per REGISTRY_PROPOSAL.json after monitoring confirms stability.

---

## Contact & Support

**Implementation Questions:**
- Review ARCHITECTURE.md for code locations
- Review GAP_ANALYSIS.md for detailed gap context
- Review TEMPLATES_FOUND.md for string catalog

**Testing Issues:**
- Check HOOKS_MAP.csv for integration points
- Run `wp msh smoke test-all`
- Review debug.log for errors

---

**Document Version:** 1.0
**Created:** 2025-10-26
**Updated:** 2025-10-26
**Status:** Ready for Implementation
**Target Launch:** October 30, 2025

🚀 **LET'S SHIP IT!**
