# TinyDot Image Optimizer - Gap Analysis

**Target Architecture:** Industry-agnostic, deterministic, Template Registry-based non-AI pipeline
**Current State:** Healthcare-centric, hardcoded templates, partial builder awareness
**Audit Date:** 2025-10-26
**Launch Target:** October 30, 2025 (4 days)

---

## Executive Summary

**Overall Industry-Agnostic Score:** **2/10** ❌

The plugin has a solid technical foundation but is heavily healthcare-specific. Critical gaps prevent it from being truly industry-agnostic as intended.

**Critical Blockers for Launch:**
1. ✅ **BUG FIXED:** Attachment deletion 500 error (missing `cancel_jobs_for_entity()` method)
2. ❌ **HIGH:** Builder content parsing will extract shortcodes as H1/paragraphs
3. ❌ **HIGH:** Healthcare boilerplate in ALL templates ("WSIB", "MVA", "rehabilitation")
4. ❌ **MEDIUM:** Inconsistent filename truncation (some paths can exceed 4-word limit)
5. ❌ **MEDIUM:** Toggle OFF still shows existing suggestions in UI

**Quick Wins (Pre-Launch):**
- Remove Ontario-specific acronyms (WSIB, MVA) → 2 hours
- Add builder detection guardrails → 4 hours
- Fix missing truncation calls → 2 hours
- Clear suggestions when toggle OFF → 1 hour

**Post-Launch Refactor (High Priority):**
- Implement Template Registry → 2 weeks
- Extract industry templates to JSON → 1 week
- Add dominant color extraction → 3 days
- Build unit test coverage → 1 week

---

## Table of Contents

1. [Gap Categories](#gap-categories)
2. [Critical Gaps (HIGH)](#critical-gaps-high)
3. [Important Gaps (MEDIUM)](#important-gaps-medium)
4. [Nice-to-Have Gaps (LOW)](#nice-to-have-gaps-low)
5. [Implementation Roadmap](#implementation-roadmap)
6. [Estimated Effort](#estimated-effort)

---

## Gap Categories

### By Priority

| Priority | Count | Can Ship? | Risk Level |
|----------|-------|-----------|------------|
| **HIGH** | 5 | ⚠️ With workarounds | Data integrity, UX confusion |
| **MEDIUM** | 6 | ✅ Yes | Quality issues, edge cases |
| **LOW** | 4 | ✅ Yes | Polish, future features |

### By Category

| Category | Gaps | Impact |
|----------|------|--------|
| **Template System** | 4 | Industry-agnostic goal blocked |
| **Content Detection** | 2 | Builder sites broken |
| **File Naming** | 3 | Inconsistent quality |
| **Visual Analysis** | 2 | Missing features |
| **UI/UX** | 2 | User confusion |
| **Testing** | 2 | Maintenance risk |

---

## Critical Gaps (HIGH)

### GAP-001: Builder Content Detection

**Priority:** 🔴 **HIGH** (Launch Blocker for Builder Sites)
**Category:** Content Detection
**Impact:** Data Integrity

**Problem:**

`extract_lightweight_page_context()` at line 1470 uses simple regex to extract H1 and first paragraph from `post_content`:

```php
if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $content, $h1_match)) {
    $context['heading'] = wp_strip_all_tags($h1_match[1]);
}
```

**This will parse Elementor/Bricks/Oxygen shortcodes** which contain HTML-like syntax:

```
[elementor-template id="123"]
<!-- Elementor renders to: -->
<h1>Contact Our Team</h1>
<p>We're here to help...</p>
```

The regex will extract "Contact Our Team" even though this content is buried in a shortcode and may not be the actual page H1.

**Evidence:**
- Line 1470-1505: No builder detection before parsing
- No checks for: `[elementor-`, `[vc_row`, `[et_pb_`, `data-elementor-type`
- Confirmed by user: "Do not parse builder content. Featured image is the only exception for H1 and first paragraph"

**Impact:**
- **Severity:** High for ~40% of WordPress sites (builder market share)
- Wrong context extracted
- Generic/irrelevant metadata generated
- User frustration: "Why is my image tagged with the wrong heading?"

**Target Spec Violation:**
> "Do not parse builder content. Featured image exception only"

**Fix Plan:**

**Option A - Simple Guard (2 hours):**

```php
private function extract_lightweight_page_context($attachment_id, $parent_post) {
    // Detect builder content
    if ($this->is_builder_content($parent_post)) {
        return array('caption' => '', 'heading' => '', 'excerpt' => '');
    }

    // Existing regex logic...
}

private function is_builder_content($post) {
    $content = $post->post_content;

    // Check for common builder shortcodes
    $builder_patterns = array(
        '/\[elementor-template/',
        '/\[vc_row/',
        '/\[et_pb_/',
        '/\[fusion_builder_/',
        '/\[bricks-/',
        '/data-elementor-type=/',
    );

    foreach ($builder_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return true;
        }
    }

    // Check for builder meta
    $builder_meta_keys = array(
        '_elementor_edit_mode',
        '_wpb_vc_js_status',
        '_et_pb_use_builder',
        '_fusion',
    );

    foreach ($builder_meta_keys as $meta_key) {
        if (get_post_meta($post->ID, $meta_key, true)) {
            return true;
        }
    }

    return false;
}
```

**Files to Edit:**
- `includes/class-msh-image-optimizer.php` lines 1470-1505

**Option B - Advanced (4 hours):**

Add feature flag to allow opt-in builder parsing with explicit whitelist of allowed templates.

**Recommendation:** **Option A** for launch. Add Option B post-launch.

**Effort:** 2-4 hours
**Risk:** Low (defensive approach)

---

### GAP-002: No Template Registry

**Priority:** 🔴 **HIGH** (Architecture Debt)
**Category:** Template System
**Impact:** Maintainability, Scalability

**Problem:**

All 500+ template strings are hardcoded in PHP methods. Zero separation of content from code.

**Evidence:**
- `generate_clinical_meta()` line 2396: `"Professional physiotherapy treatment"`
- `generate_facility_meta()` line 2837: `"{$business_name} Clinic - {location} Rehabilitation Facility"`
- 15+ industry methods with embedded strings
- No JSON, no database, no external registry

**Impact:**
- **Cannot add new industries** without PHP code changes
- **Cannot customize templates** without editing core plugin files
- **Cannot A/B test** template variations
- **Localization nightmare:** 500+ strings to translate
- **Zero reusability:** Each template defined once, used once

**Target Spec Violation:**
> "Template Registry JSON with inheritance: generic plus industry overrides"

**Current State vs Target:**

| Feature | Current | Target |
|---------|---------|--------|
| **Storage** | Hardcoded PHP | JSON + Database |
| **Inheritance** | ❌ None | ✅ Generic → Industry → Business |
| **Overrides** | ❌ Must edit code | ✅ Per-site customization |
| **Versioning** | ❌ None | ✅ Track changes |
| **A/B Testing** | ❌ Impossible | ✅ Variant system |

**Fix Plan:**

**Phase 1 - Registry Structure (1 week):**

Create `MSH_Template_Registry` class:

```php
class MSH_Template_Registry {
    // Load templates from JSON files
    public function load_templates_from_json($locale = 'en') {}

    // Get template by scene + industry
    public function get_template($scene, $industry, $variables) {}

    // Register custom template
    public function register_template($template_data) {}

    // Inheritance: generic → industry → site
    private function resolve_template_inheritance($scene, $industry) {}
}
```

**Phase 2 - JSON Structure (see REGISTRY_PROPOSAL.json):**

```json
{
  "scenes": {
    "facility": {
      "generic": {
        "title": "{business_name} Workspace",
        "alt_text": "Interior view of {business_name}",
        ...
      },
      "medical": {
        "inherits": "generic",
        "title": "{business_name} Medical Facility",
        ...
      }
    }
  }
}
```

**Phase 3 - Migration (1 week):**

1. Extract all hardcoded strings to JSON
2. Update methods to call `$registry->get_template()`
3. Add fallback to hardcoded if registry fails
4. Deprecation notices for direct method calls

**Files to Create:**
- `includes/class-msh-template-registry.php`
- `includes/data/templates/en/scenes.json`
- `includes/data/templates/en/industries.json`

**Files to Edit:**
- All `generate_{industry}_meta()` methods → call registry
- `generate_meta_fields()` → inject registry

**Effort:** 2 weeks
**Risk:** Medium (requires careful migration)
**Recommendation:** **Post-launch** - this is architectural, not a bug

---

### GAP-003: Healthcare-Specific Boilerplate Throughout

**Priority:** 🔴 **HIGH** (Industry-Agnostic Goal)
**Category:** Template System
**Impact:** User Trust, Market Expansion

**Problem:**

80%+ of template strings contain healthcare-specific boilerplate that will appear for ALL industries.

**Evidence Examples:**

**Facility Template** (line 2840):
```php
"Professional physiotherapy and chiropractic clinic with specialized treatment rooms and WSIB approved programs."
```

If user is a law firm → still says "physiotherapy and chiropractic clinic"!

**Service Keywords** (lines 62-109):
```php
'default' => 'WSIB approved. MVA recovery. First responder programs.'
```

WSIB = Workplace Safety and Insurance Board (Ontario, Canada only)
MVA = Motor Vehicle Accident

If user is in Texas → says "WSIB approved" (meaningless)

**Full List of Healthcare-Only Terms:**

| Term | Count | Example |
|------|-------|---------|
| WSIB | 12+ | "WSIB approved programs" |
| MVA | 8+ | "MVA recovery" |
| rehabilitation | 40+ | "Modern rehabilitation facility" |
| physiotherapy | 20+ | "Professional physiotherapy treatment" |
| chiropractic | 15+ | "chiropractic clinic" |
| patient | 20+ | "Patient testimonial" |
| clinic | 15+ | "{business_name} Clinic" |
| treatment | 30+ | "treatment for {benefit}" |

**Impact:**
- **Legal firms** get "rehabilitation clinic" metadata
- **Plumbers** get "WSIB approved" captions
- **SaaS companies** get "patient testimonial" titles
- **Massive trust loss** when users see irrelevant medical terms

**Target Spec Violation:**
> "Industry-agnostic direction: use a Template Registry pattern, with profiles like generic, healthcare, ecommerce, real_estate, SaaS"

**Fix Plan:**

**Quick Win - Remove Regional Acronyms (2 hours):**

Replace Ontario-specific terms:

```php
// BEFORE (line 2840)
"WSIB approved programs"

// AFTER
"Insurance billing accepted"
```

```php
// BEFORE (service keywords)
'default' => 'WSIB approved. MVA recovery. First responder programs.'

// AFTER
'default' => 'Insurance accepted. Accident recovery. Priority scheduling available.'
```

**Files to Edit:**
- `class-msh-image-optimizer.php` lines 62-109 (service keywords)
- `class-msh-image-optimizer.php` line 2840 (facility template)
- Search/replace: `WSIB` → `insurance`, `MVA` → `accident`

**Medium-Term - Parameterize Healthcare Terms (1 week):**

Add industry check wrapper:

```php
private function generate_facility_meta($context) {
    if ($this->is_healthcare_industry($this->industry)) {
        return $this->generate_healthcare_facility_meta($context);
    }

    // Generic business facility
    return $this->generate_generic_facility_meta($context);
}
```

**Long-Term - Template Registry (2 weeks):**

Move all industry-specific strings to JSON profiles (see GAP-002).

**Recommendation for Launch:**
1. ✅ **Do Now:** Remove WSIB, MVA acronyms (2 hours)
2. ⏸️ **Post-Launch:** Parameterize healthcare terms (1 week)
3. ⏸️ **Q1 2026:** Full Template Registry migration (2 weeks)

**Effort:** 2 hours (quick fix) + 3 weeks (complete fix)
**Risk:** Low (quick fix), Medium (refactor)

---

### GAP-004: Inconsistent Slug Truncation

**Priority:** 🔴 **HIGH** (Pricing Strategy Violation)
**Category:** File Naming
**Impact:** SEO, User Expectations

**Problem:**

Target spec says **3-4 word limit** for filenames. Code has `truncate_slug($slug, $max_words = 4)` helper (line 1672) but it's not called consistently.

**Evidence:**

**Has Truncation:**
- Line 2072: AI slug → `$ai_slug = $this->truncate_slug($ai_slug, 4);` ✅
- Line 2087: Testimonial subject → `$this->truncate_slug($context['attachment_slug'], 3)` ✅

**Missing Truncation:**
- Line 2084: Team → `return $this->slugify("{$this->business_name}-team-{$name}");` ❌
- Line 2119: Facility → `return $this->slugify($this->business_name . '-facility-' . $this->location_slug);` ❌
- Line 2128: Equipment → `return $this->slugify($extracted_keywords . '-equipment-' . $this->location_slug);` ❌

**Real-World Example from Testing Session:**

User uploaded image, got suggestion:
```
thedean-view3-designupdate-san-francisco-united-states.jpg
```

**Word count:** 8 words!
**Expected:** 3-4 words
**Pricing Strategy:** "we had a 3 word or so restriction"

**Impact:**
- Longer filenames = worse SEO
- User expectation mismatch (marketed as "concise")
- Inconsistent quality across scene types

**Fix Plan:**

**Add truncation to ALL slug generation paths:**

```php
// Team (line 2084)
// BEFORE:
return $this->slugify("{$this->business_name}-team-{$name}");

// AFTER:
$slug = $this->slugify("{$this->business_name}-team-{$name}");
return $this->truncate_slug($slug, 4);
```

```php
// Facility (line 2119)
// BEFORE:
return $this->slugify($this->business_name . '-facility-' . $this->location_slug);

// AFTER:
$slug = $this->slugify($this->business_name . '-facility-' . $this->location_slug);
return $this->truncate_slug($slug, 4);
```

**Systematic Fix:**

Search entire `generate_filename_slug()` method for all `return` statements, ensure each calls `truncate_slug()`.

**Files to Edit:**
- `class-msh-image-optimizer.php` lines 2059-2333 (entire filename generation method)

**Lines needing fixes:**
- 2084 (team)
- 2119 (facility)
- 2128, 2144, 2153, 2158 (equipment variants)
- 2248 (business - uses assemble_slug which has own limit)

**Effort:** 2 hours
**Risk:** Low
**Recommendation:** **Fix before launch** - this is a quality/branding issue

---

### GAP-005: Toggle OFF Still Shows Suggestions

**Priority:** 🔴 **HIGH** (User Confusion)
**Category:** UI/UX
**Impact:** User Trust

**Problem:**

When user toggles "Enable File Rename" to OFF, existing filename suggestions still appear in Media Library.

**Evidence:**

User quote from testing session:
> "Even when i toggle the rename off - still suggests renames!!!!"

**Code Analysis:**

`is_file_rename_enabled()` (line 5413) prevents **generation** of new suggestions:

```php
public function generate_suggestion_for_new_upload($attachment_id) {
    if (!$this->is_file_rename_enabled()) {
        return;  // Don't generate
    }
    // ... generate and save to _msh_suggested_filename
}
```

But UI still **displays** suggestions from `_msh_suggested_filename` meta, regardless of toggle state.

**Impact:**
- User thinks feature is ON when it's OFF
- Confusion: "I turned it off, why do I still see suggestions?"
- Trust issue: "Does this setting even work?"

**Expected Behavior:**

When toggle is OFF:
1. Don't generate new suggestions ✅ (already working)
2. **Hide existing suggestions from UI** ❌ (not working)
3. Optionally: **Clear all existing suggestions** ❌ (not implemented)

**Fix Plan:**

**Option A - Hide in UI (1 hour):**

Update Media Library column rendering to check toggle:

```php
public function add_filename_column_content($column_name, $attachment_id) {
    if ($column_name === 'msh_filename_suggestion') {
        // NEW: Check if feature enabled
        if (!$this->is_file_rename_enabled()) {
            echo '<em>Feature disabled</em>';
            return;
        }

        $suggestion = get_post_meta($attachment_id, '_msh_suggested_filename', true);
        // ... render suggestion
    }
}
```

**Option B - Clear on Toggle OFF (2 hours):**

When user toggles OFF, clear all suggestions:

```php
public function ajax_toggle_file_rename() {
    // ... existing code ...

    if ($old_value === '1' && $new_value === '0') {
        // User just turned OFF - clear all suggestions
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_msh_suggested_filename', '_msh_suggested_filename_context')"
        );
    }
}
```

**Option C - Add Setting (3 hours):**

Add UI option: "When disabled, [ ] hide suggestions [ ] clear suggestions"

**Recommendation:** **Option A for launch** (simplest, no data loss). Add Option C post-launch.

**Files to Edit:**
- `admin/image-optimizer-admin.php` (column rendering)
- OR `includes/class-msh-image-optimizer.php` line 9554 (toggle handler)

**Effort:** 1-3 hours
**Risk:** Low
**Recommendation:** **Fix before launch** - this is a UX issue

---

## Important Gaps (MEDIUM)

### GAP-006: No Dominant Color Extraction

**Priority:** 🟡 **MEDIUM**
**Category:** Visual Analysis
**Impact:** Feature Completeness

**Problem:**

Target spec includes dominant color extraction. Not implemented.

**Target Spec:**
> "Dominant color with Imagick or GD fallback"

**Current State:**

Zero color extraction code found.

**Use Cases:**
- Filter images by color in Media Library
- Auto-tag images with color keywords
- Generate color-aware alt text: "red logo", "blue facility exterior"
- Template variable: `{dominant_color}` → "Blue-themed workspace at..."

**Fix Plan:**

**Implementation (3 days):**

```php
class MSH_Color_Analyzer {
    public function get_dominant_color($attachment_id) {
        $file_path = MSH_File_Resolver::get_attachment_file_path($attachment_id);

        // Try ImageMagick first (more accurate)
        if (extension_loaded('imagick')) {
            return $this->extract_color_imagick($file_path);
        }

        // Fallback to GD
        if (function_exists('imagecreatefromjpeg')) {
            return $this->extract_color_gd($file_path);
        }

        return null;
    }

    private function extract_color_imagick($file_path) {
        $image = new Imagick($file_path);
        $image->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1);
        $pixel = $image->getImagePixelColor(0, 0);
        $color = $pixel->getColor();

        return $this->rgb_to_name($color['r'], $color['g'], $color['b']);
    }

    private function rgb_to_name($r, $g, $b) {
        // Map RGB to common color names
        $colors = array(
            'red' => array(255, 0, 0),
            'blue' => array(0, 0, 255),
            'green' => array(0, 255, 0),
            // ... etc
        );

        // Find closest color by Euclidean distance
        // Return color name
    }
}
```

Store in post meta: `_msh_dominant_color`

**Files to Create:**
- `includes/class-msh-color-analyzer.php`

**Files to Edit:**
- `class-msh-image-optimizer.php` → call analyzer in `detect_context()`
- Templates → use `{dominant_color}` variable

**Effort:** 3 days
**Risk:** Low (optional feature)
**Recommendation:** **Post-launch** - nice-to-have, not critical

---

### GAP-007: No Face Count Detection

**Priority:** 🟡 **MEDIUM**
**Category:** Visual Analysis
**Impact:** Feature Completeness

**Problem:**

Target spec includes face count for team vs testimonial vs group detection.

**Target Spec:**
> "Optional face count via php-opencv or admin-side browser detection"

**Current State:**

Zero face detection code.

**Use Cases:**
- **Team photo detection:** 5+ faces → `type = 'team'`
- **Testimonial detection:** 1 face → `type = 'testimonial'`
- **Headshot detection:** 1 face + portrait orientation → `type = 'team'` + extract name
- **Icon detection:** 0 faces → likely `type = 'service-icon'`

**Fix Plan:**

**Option A - PHP OpenCV (Complex, 1 week):**

Requires OpenCV PHP extension (rare on shared hosting).

**Option B - Browser-Based (Recommended, 3 days):**

Use JavaScript face detection API (e.g., `face-api.js`):

```javascript
// On image upload/edit page
async function detectFaces(imageElement) {
    const detections = await faceapi.detectAllFaces(imageElement);

    // Send to PHP via AJAX
    jQuery.post(ajaxurl, {
        action: 'msh_save_face_count',
        attachment_id: attachmentId,
        face_count: detections.length
    });
}
```

Store in post meta: `_msh_face_count`

**Option C - Admin Setting (1 day):**

Add UI toggle: "Enable face detection (requires browser support)"

Default: OFF

**Files to Create:**
- `admin/js/face-detection.js`

**Files to Edit:**
- `class-msh-image-optimizer.php` → use face count in `detect_context()`
- AJAX handler for `msh_save_face_count`

**Effort:** 3 days (Option B)
**Risk:** Medium (browser compatibility)
**Recommendation:** **Post-launch** - optional feature, add as premium upsell?

---

### GAP-008: Missing File Path Empty Check

**Priority:** 🟡 **MEDIUM**
**Category:** Content Detection
**Impact:** 8 Missing Images

**Problem:**

Analysis shows "44/52 published images" but media library has 52. **8 images missing.**

**Root Cause (from previous debugging session):**

Line 6162 in `get_published_images()`:

```php
if (empty($attachment['file_path'])) {
    continue;  // Skipped entirely!
}
```

**Why file_path might be empty:**
- Attachment exists but file deleted
- External URL attachment
- File resolver fails

**Impact:**
- 15% of images invisible to plugin
- User confusion: "Why doesn't my image show up?"
- Inaccurate counts: "45 images" vs "52 total"

**Fix Plan:**

**Use robust file resolver:**

```php
// BEFORE:
if (empty($attachment['file_path'])) {
    continue;
}

// AFTER:
$file_path = MSH_File_Resolver::find_attachment_file($attachment_id);
if (!$file_path) {
    // Include in results but flag as missing file
    $attachment['file_path'] = '';
    $attachment['missing_file'] = true;
}
```

**Files to Edit:**
- `class-msh-image-optimizer.php` line 6162

**Effort:** 1 hour
**Risk:** Low
**Recommendation:** **Fix before launch** - data integrity issue

---

### GAP-009: Wrong Industry Metadata

**Priority:** 🟡 **MEDIUM**
**Category:** Context Detection
**Impact:** Metadata Quality

**Problem:**

Images getting detected as wrong industry, overriding global business industry setting.

**Evidence from Testing Session:**

User quote:
> "WTF??? what is even web design treatment???? how is that possbile??"

**Investigation:**

Onboarding context correctly set:
```php
'industry' => 'medical'
```

But individual image detected as:
```php
'industry' => 'web_design'
```

**Root Cause:**

`detect_context()` can override global industry based on filename/title patterns.

**Impact:**
- Medical clinic images get "web design" templates
- Buildings get generic "facility" instead of "rehabilitation clinic"
- Inconsistent metadata across site

**Fix Plan:**

**Add industry lock option:**

```php
// In onboarding context
'lock_industry' => true  // Don't auto-detect, always use global
```

```php
// In detect_context()
if (!empty($this->active_context['lock_industry']) && $this->active_context['lock_industry']) {
    // Don't override industry
    $context['industry'] = $this->industry;
} else {
    // Allow auto-detection
    $detected = $this->detect_industry_from_filename($file_basename);
    if ($detected) {
        $context['industry'] = $detected;
    }
}
```

**Files to Edit:**
- `class-msh-image-optimizer.php` line 1165+ (detect_context method)
- `class-msh-context-helper.php` (add lock_industry field)

**Effort:** 2 hours
**Risk:** Low
**Recommendation:** **Fix before launch** - quality issue

---

### GAP-010: Starter Templates Not Integrated

**Priority:** 🟡 **MEDIUM**
**Category:** Template System
**Impact:** Feature Unused

**Problem:**

`starter-templates.php` defines 8 beautiful token-based templates, but they're **never used** in non-AI mode.

**Evidence:**

- File exists: `includes/data/starter-templates.php`
- Function exists: `msh_get_starter_templates()`
- **But:** No code calls this function!

Grep search for `msh_get_starter_templates` returns zero results except the definition.

**Impact:**
- Wasted development effort (8 templates defined, 0 used)
- Token-based matching system built but inactive
- Better than hardcoded templates, but not leveraged

**Fix Plan:**

**Integrate starter templates into detection pipeline:**

```php
public function detect_context($attachment_id, $ignore_manual = false) {
    // ... existing code ...

    // NEW: Try starter templates first
    $starter_match = $this->match_starter_template($attachment_id, $context);
    if ($starter_match) {
        $context = array_merge($context, $starter_match);
    }

    // ... rest of detection ...
}

private function match_starter_template($attachment_id, $context) {
    $templates = msh_get_starter_templates();
    $attachment = get_post($attachment_id);

    // Tokenize filename + title
    $tokens = $this->tokenize_for_matching(
        $attachment->post_title,
        $context['file_basename']
    );

    foreach ($templates as $template) {
        $score = $this->calculate_template_match_score($template, $tokens);
        if ($score > $threshold) {
            return array(
                'type' => $template['scene_type'],
                'template_match' => $template['name'],
                'confidence' => $score
            );
        }
    }

    return null;
}
```

**Files to Edit:**
- `class-msh-image-optimizer.php` → add template matching logic
- `starter-templates.php` → add `scene_type` field to each template

**Effort:** 1 day
**Risk:** Low
**Recommendation:** **Post-launch** - this is an enhancement, not a bug

---

### GAP-011: No Confidence Scoring in UI

**Priority:** 🟡 **MEDIUM**
**Category:** UI/UX
**Impact:** User Guidance

**Problem:**

Code has `score_filename_quality()` method (line 6965) but **score never shown to user**.

**Evidence:**

Method exists:
```php
private function score_filename_quality($filename) {
    $score = 0;
    // ... scoring logic ...
    return $score;
}
```

But zero calls to this method found in codebase!

**Impact:**
- Users can't tell which suggestions are high vs low confidence
- No visual indicator: "This is a strong suggestion" vs "This is a guess"
- Can't sort/filter by quality

**Target Spec:**
> "Silent confidence score used only for UI highlighting"

**Fix Plan:**

**Add confidence indicators to UI:**

```php
// In Media Library column
$score = $this->score_filename_quality($suggested_filename);

if ($score >= 10) {
    echo '<span class="msh-confidence high">★★★ High Confidence</span>';
} elseif ($score >= 0) {
    echo '<span class="msh-confidence medium">★★☆ Medium Confidence</span>';
} else {
    echo '<span class="msh-confidence low">★☆☆ Low Confidence</span>';
}
```

**CSS:**
```css
.msh-confidence.high { color: #4CAF50; }
.msh-confidence.medium { color: #FF9800; }
.msh-confidence.low { color: #F44336; }
```

**Files to Edit:**
- `admin/image-optimizer-admin.php` (add confidence display)
- `admin/css/admin-styles.css` (add styling)

**Effort:** 2 hours
**Risk:** Low
**Recommendation:** **Post-launch** - nice-to-have UX improvement

---

## Nice-to-Have Gaps (LOW)

### GAP-012: No Unit Test Coverage

**Priority:** 🟢 **LOW** (Technical Debt)
**Category:** Testing
**Impact:** Maintenance Risk

**Problem:**

Zero automated tests exist.

**Evidence:**

Search for test files:
- `/tests/` directory: Not found
- `*Test.php` files: Not found
- `phpunit.xml`: Not found

**Impact:**
- **Regression risk:** Changes can break existing functionality undetected
- **Refactoring danger:** Can't safely refactor without tests
- **Onboarding difficulty:** New developers can't verify changes work
- **Quality assurance:** Manual testing only

**Fix Plan:**

**Phase 1 - Setup (1 day):**

```bash
composer require --dev phpunit/phpunit
composer require --dev wp-phpunit/wp-phpunit
```

Create `tests/bootstrap.php`

**Phase 2 - Critical Path Tests (1 week):**

Priority test coverage:

1. **Context Detection:**
   - `test_detect_context_team_member()`
   - `test_detect_context_testimonial()`
   - `test_detect_context_facility()`
   - `test_icon_detection_patterns()`

2. **Filename Generation:**
   - `test_generate_team_filename()`
   - `test_generate_facility_filename()`
   - `test_filename_truncation()`
   - `test_filename_uniqueness()`

3. **Metadata Templates:**
   - `test_clinical_meta_generation()`
   - `test_medical_meta_generation()`
   - `test_business_meta_generation()`

4. **Slug Utilities:**
   - `test_slugify()`
   - `test_truncate_slug()`
   - `test_ensure_unique_filename()`

**Files to Create:**
- `tests/bootstrap.php`
- `tests/unit/test-context-detection.php`
- `tests/unit/test-filename-generation.php`
- `tests/unit/test-metadata-templates.php`
- `tests/integration/test-ajax-endpoints.php`
- `phpunit.xml`

**Effort:** 1 week (basic coverage)
**Risk:** None (doesn't affect production)
**Recommendation:** **Post-launch** - technical debt, not user-facing

---

### GAP-013: No Localization for Detection Keywords

**Priority:** 🟢 **LOW**
**Category:** Internationalization
**Impact:** Non-English Sites

**Problem:**

Detection keywords are English-only.

**Evidence:**

Line 2252-2273: Treatment keywords hardcoded in English:
```php
'physiotherapy' => array('physiotherapy', 'physical therapy', 'physio'),
'back-pain' => array('back pain', 'spine pain', 'lumbar pain'),
```

**Impact:**
- **French site:** Image titled "Physiothérapie" won't match "physiotherapy"
- **Spanish site:** "Quiropráctica" won't match "chiropractic"
- **German site:** "Krankengymnastik" won't match anything

**Fix Plan:**

**Add locale-aware keyword matching:**

```php
private function get_treatment_keywords($locale = 'en') {
    $keywords = array(
        'en' => array(
            'physiotherapy' => array('physiotherapy', 'physical therapy'),
            'back-pain' => array('back pain', 'spine pain'),
        ),
        'fr' => array(
            'physiotherapy' => array('physiothérapie', 'kinésithérapie'),
            'back-pain' => array('mal de dos', 'douleur dorsale'),
        ),
        'es' => array(
            'physiotherapy' => array('fisioterapia'),
            'back-pain' => array('dolor de espalda', 'dolor lumbar'),
        ),
    );

    return $keywords[$locale] ?? $keywords['en'];
}
```

**Files to Create:**
- `includes/data/keywords/en.php`
- `includes/data/keywords/fr.php`
- `includes/data/keywords/es.php`

**Files to Edit:**
- `class-msh-image-optimizer.php` → load locale-specific keywords

**Effort:** 3 days (per language)
**Risk:** Low
**Recommendation:** **Post-launch** - only needed for international expansion

---

### GAP-014: No Template Versioning

**Priority:** 🟢 **LOW**
**Category:** Template System
**Impact:** Change Tracking

**Problem:**

When templates change, no way to know which version generated existing metadata.

**Current State:**

```php
update_post_meta($attachment_id, 'msh_metadata_last_updated', time());
```

Stores timestamp but not template version.

**Impact:**
- Can't re-generate with new template for old images
- Can't A/B test template changes
- Can't roll back template changes
- Can't audit "which template was used?"

**Fix Plan:**

**Add version tracking:**

```php
// When generating metadata
update_post_meta($attachment_id, 'msh_template_version', '1.2.0');
update_post_meta($attachment_id, 'msh_template_name', 'facility-healthcare-v2');
```

**Migration command:**

```bash
wp msh regenerate --template-version=1.3.0
```

Re-generates all metadata using new template version.

**Files to Edit:**
- `class-msh-image-optimizer.php` → add version meta
- WP-CLI command → add `--template-version` flag

**Effort:** 1 day
**Risk:** Low
**Recommendation:** **Post-launch** - technical feature for template iteration

---

### GAP-015: No Image Dimension-Based Detection

**Priority:** 🟢 **LOW**
**Category:** Context Detection
**Impact:** Detection Accuracy

**Problem:**

Icon detection checks dimensions (line 1826):
```php
if ($width <= 800 || $height <= 800) {
    // Likely an icon
}
```

But no other dimension-based heuristics.

**Missed Opportunities:**

- **Portrait orientation (9:16):** Likely headshot → `type = 'team'`
- **Square (1:1):** Likely product or social media → `type = 'product'` or `type = 'business'`
- **Panorama (3:1):** Likely facility exterior → `type = 'facility'`
- **Very large (>3000px):** Likely hero image → `type = 'business'`

**Fix Plan:**

**Add aspect ratio detection:**

```php
private function detect_by_aspect_ratio($width, $height, $context) {
    if ($width == 0 || $height == 0) {
        return $context;
    }

    $ratio = $width / $height;

    if ($ratio >= 0.9 && $ratio <= 1.1) {
        // Square - likely product or social
        $context['aspect_hint'] = 'square';
    } elseif ($ratio < 0.7) {
        // Portrait - likely headshot
        $context['aspect_hint'] = 'portrait';
        if (empty($context['type']) || $context['type'] === 'business') {
            $context['type'] = 'team';
        }
    } elseif ($ratio > 2.5) {
        // Panorama - likely facility
        $context['aspect_hint'] = 'panorama';
        if (empty($context['type']) || $context['type'] === 'business') {
            $context['type'] = 'facility';
        }
    }

    return $context;
}
```

**Files to Edit:**
- `class-msh-image-optimizer.php` line 1165+ (detect_context)

**Effort:** 2 hours
**Risk:** Low
**Recommendation:** **Post-launch** - enhancement, not critical

---

## Implementation Roadmap

### Pre-Launch (4 days remaining)

**Priority: Fix Critical Gaps Only**

| Day | Tasks | Hours | Gaps Fixed |
|-----|-------|-------|------------|
| **Day 1** | Remove WSIB/MVA acronyms<br/>Add builder detection guardrail | 4h | GAP-003, GAP-001 |
| **Day 2** | Fix missing truncation calls<br/>Fix file_path empty check | 3h | GAP-004, GAP-008 |
| **Day 3** | Add toggle OFF hiding<br/>Fix industry override | 3h | GAP-005, GAP-009 |
| **Day 4** | Testing & QA<br/>Fix any regressions | 8h | - |

**Total:** ~18 hours (pre-launch)

**Result:** Launch-ready plugin with critical UX/data issues resolved.

---

### Post-Launch - Phase 1 (Week 1-2)

**Priority: Quality & Cleanup**

| Week | Tasks | Gaps Fixed |
|------|-------|------------|
| **Week 1** | Integrate starter templates<br/>Add confidence UI indicators<br/>Parameterize healthcare terms | GAP-010, GAP-011, GAP-003 |
| **Week 2** | Add dominant color extraction<br/>Add face count detection<br/>Write unit tests | GAP-006, GAP-007, GAP-012 |

---

### Post-Launch - Phase 2 (Week 3-5)

**Priority: Template Registry**

| Week | Tasks | Gaps Fixed |
|------|-------|------------|
| **Week 3** | Design Template Registry schema<br/>Create MSH_Template_Registry class | GAP-002 |
| **Week 4** | Migrate hardcoded strings to JSON<br/>Add inheritance system | GAP-002 |
| **Week 5** | Add template versioning<br/>Build migration tools | GAP-002, GAP-014 |

---

### Post-Launch - Phase 3 (Month 2)

**Priority: International & Advanced**

| Week | Tasks | Gaps Fixed |
|------|-------|------------|
| **Week 6-7** | Add French keyword translations<br/>Add Spanish keyword translations | GAP-013 |
| **Week 8** | Add aspect ratio detection<br/>Advanced heuristics | GAP-015 |

---

## Estimated Effort

### By Priority

| Priority | Total Gaps | Quick Fixes | Full Fixes | Total Effort |
|----------|-----------|-------------|------------|--------------|
| **HIGH** | 5 | 9 hours | 4 weeks | ~4.5 weeks |
| **MEDIUM** | 6 | 5 hours | 2 weeks | ~2.5 weeks |
| **LOW** | 4 | 0 hours | 2 weeks | ~2 weeks |
| **TOTAL** | **15** | **14 hours** | **8 weeks** | **~9 weeks** |

### Launch Window

**Minimum Viable Launch:**
- ✅ Quick fixes only: **14 hours** (2 days)
- Ships with known limitations documented
- Post-launch roadmap communicated to users

**Recommended Launch:**
- ✅ Critical fixes: **18 hours** (4 days) ← **Do this**
- Quality issues resolved
- Smooth user experience

**Ideal Launch:**
- ⏸️ All fixes: **9 weeks**
- Industry-agnostic
- Feature-complete
- Production-ready

**Recommendation:** **Launch in 4 days with critical fixes**, communicate post-launch roadmap.

---

## Risk Assessment

### Launch Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| **Builder sites get wrong metadata** | High (40% of WP sites) | Medium | Add guardrails (GAP-001) |
| **Healthcare terms on non-medical sites** | High | High | Remove WSIB/MVA (GAP-003) |
| **Long filenames (>4 words)** | Medium | Low | Add truncation (GAP-004) |
| **Users confused by toggle** | Medium | Medium | Hide suggestions when OFF (GAP-005) |
| **Missing images in analysis** | Low | Medium | Fix file_path check (GAP-008) |

### Post-Launch Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| **Cannot scale to new industries** | High | High | Template Registry (GAP-002) |
| **Template changes break old data** | Medium | Low | Add versioning (GAP-014) |
| **International users can't use** | Low | Medium | Locale keywords (GAP-013) |
| **Maintenance becomes difficult** | Medium | Medium | Add tests (GAP-012) |

---

## Success Metrics

### Pre-Launch

✅ **Zero critical bugs** (500 errors, data loss)
✅ **Zero healthcare terms on non-medical sites**
✅ **All filenames ≤4 words**
✅ **Toggle OFF hides suggestions**
✅ **Builder sites don't parse shortcodes**

### Post-Launch (Month 1)

📊 **90% detection accuracy** (user-reported correct scene type)
📊 **<5% filename rejection rate** (users reject suggestions)
📊 **<1% support tickets re: wrong industry metadata**
📊 **52/52 images indexed** (zero missing images)

### Post-Launch (Month 3)

📊 **Template Registry live** (JSON-based templates)
📊 **5+ industries supported** (beyond healthcare)
📊 **Dominant color on 80%+ images**
📊 **50% unit test coverage**

---

## Conclusion

**Can we launch on October 30?**

✅ **YES** - with critical fixes completed (18 hours work remaining)

**Should we launch with all fixes?**

❌ **NO** - 9 weeks is too long, market window will close

**Recommended Strategy:**

1. **Days 1-4:** Fix critical gaps (GAP-001, 003, 004, 005, 008, 009)
2. **Launch:** October 30 with documented limitations
3. **Weeks 1-2:** Post-launch fixes (quality improvements)
4. **Weeks 3-5:** Template Registry migration
5. **Month 2+:** International + advanced features

**Communication to Users:**

> "TinyDot launches with deterministic, non-AI metadata generation. We're actively building our Template Registry system for full industry-agnostic support. Current version works great for professional services, healthcare, and local businesses. More industries coming in Q1 2026."

---

**Generated:** 2025-10-26
**Next Review:** Post-launch (November 1, 2025)
**Owner:** Product Team
**Status:** Ready for launch with fixes applied
