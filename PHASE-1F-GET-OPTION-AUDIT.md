# Phase 1F: Complete get_option() Call Audit for Batch Optimization

**Date**: 2025-10-28
**Purpose**: Document ALL get_option() calls happening during batch optimization that cause 5-minute timeout
**Issue**: 11.3MB bloated wp_options table causes MySQL table lock for 300+ seconds on EVERY get_option() call
**Current Status**: Optimization taking 7+ minutes per field instead of 5-10 seconds per image

---

## Executive Summary

Phase 1E fixed Manual Edit Protection, but optimization STILL times out because **4 additional get_option() locations** were discovered during optimization flow. This document provides complete code snippets for outsource developer to implement comprehensive batch guards.

### Timeline Evidence

```
[28-Oct-2025 16:05:24 UTC] [MSH Versioning] Saved version #10 for media_id=3107, locale=en_US, field=title
[28-Oct-2025 16:12:22 UTC] [MSH Versioning] Saved version #4 for media_id=3107, locale=en_US, field=caption
```

**Problem**: 7 minutes between title and caption for 3 images = Multiple get_option() calls causing table locks

---

## Critical get_option() Locations Found

### Location 1: Direct AI Mode Check (CRITICAL)

**File**: `/includes/class-msh-image-optimizer.php`
**Line**: 8561
**Function**: `optimize_single_image()`

```php
// Line 8559-8569
try {
    // Check if AI mode is enabled - if so, regenerate with AI
    $ai_mode    = get_option( 'msh_ai_mode', 'manual' ); // ⚠️ DIRECT GET_OPTION CALL!
    $ai_options = array();
    if ( $ai_mode !== 'manual' ) {
        $ai_options = array(
            'ai_regeneration' => true,
            'ai_mode'         => 'fill-empty',
            'ai_fields'       => array( 'title', 'alt_text', 'caption', 'description' ),
        );
    }
```

**Impact**: Called ONCE per image during optimize_single_image() = 5-minute hang
**Fix Required**: Cache at batch start, skip query during batch

---

### Location 2: Context Signature Check

**File**: `/includes/class-msh-image-optimizer.php`
**Line**: 8387
**Function**: `optimize_single_image()` → `contextual_meta_generator->get_context_signature()`

```php
// Line 8382-8387
$slug = $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_details, $extension );
if ( ! empty( $slug ) ) {
    $suggested_filename = $this->ensure_unique_filename( $slug, $extension, $attachment_id );
    update_post_meta( $attachment_id, '_msh_suggested_filename', $suggested_filename );
    update_post_meta( $attachment_id, 'msh_filename_last_suggested', (int) $timestamp );
    update_post_meta( $attachment_id, '_msh_suggested_filename_context', $this->contextual_meta_generator->get_context_signature() ); // ⚠️ CALLS GET_OPTION
    $result['actions'][] = 'Filename suggestion refreshed';
    $filename_refreshed  = true;
```

**get_context_signature() Implementation** (Lines 296-302):
```php
public function get_context_signature() {
    if ( empty( $this->context_signature ) && class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
        $this->context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature( $this->active_context ); // ⚠️
    }

    return $this->context_signature;
}
```

**Impact**: Called ONCE per image during filename generation
**Fix Required**: Return cached signature during batch, skip wp_options query

---

### Location 3: Filename Slug Generation (CASCADING CALLS)

**File**: `/includes/class-msh-image-optimizer.php`
**Line**: 8382
**Function**: `optimize_single_image()` → `contextual_meta_generator->generate_filename_slug()`

```php
// Line 8382
$slug = $this->contextual_meta_generator->generate_filename_slug( $attachment_id, $context_details, $extension );
```

**generate_filename_slug() Implementation** (Lines 2143-2145):
```php
public function generate_filename_slug( $attachment_id, array $context, $extension = null ) {
    $this->ensure_fresh_context(); // ⚠️ CALLS GET_OPTION (see below)
    $this->hydrate_active_context(); // ⚠️ CALLS GET_OPTION (see below)
    // ... rest of filename generation ...
}
```

**ensure_fresh_context() Implementation** (Lines 277-287):
```php
private function ensure_fresh_context() {
    if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
        return;
    }

    $current_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature(); // ⚠️ QUERIES wp_options!

    if ( $current_signature !== $this->context_signature ) {
        $this->hydrate_active_context(); // ⚠️ MORE get_option() calls
    }
}
```

**Impact**: Called ONCE per image during filename generation = MULTIPLE cascading get_option() calls
**Fix Required**: Skip context refresh during batch mode

---

### Location 4: Active Context Signature Check

**File**: `/includes/class-msh-image-optimizer.php`
**Line**: 9092
**Function**: `suggest_and_possibly_apply_filename()` → `MSH_Image_Optimizer_Context_Helper::get_active_context_signature()`

```php
// Lines 9091-9095
// Save the suggestion with timestamp
$current_context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature(); // ⚠️ CALLS GET_OPTION!
update_post_meta( $image_id, '_msh_suggested_filename', $suggested_filename );
update_post_meta( $image_id, 'msh_filename_last_suggested', (int) time() );
update_post_meta( $image_id, '_msh_suggested_filename_context', $current_context_signature );
```

**Impact**: Called during filename suggestions
**Fix Required**: Use cached signature during batch

---

## Supporting Class: MSH_Image_Optimizer_Context_Helper

**File**: `/includes/class-msh-context-helper.php`

### Method: get_active_context_signature() (Line 455-461)

```php
public static function get_active_context_signature( $context = null ) {
    if ( $context === null ) {
        $context = self::get_active_context(); // ⚠️ Triggers get_option() cascade
    }

    return self::build_context_signature( $context );
}
```

### Method: get_active_context() (Line 304-310)

```php
public static function get_active_context( $profiles = null ) {
    $active = self::get_active_profile( $profiles ); // ⚠️ Calls get_option()

    return isset( $active['context'] ) && is_array( $active['context'] )
        ? $active['context']
        : array();
}
```

### Method: get_active_profile() (Line 271-296)

```php
public static function get_active_profile( $profiles = null ) {
    if ( null === $profiles ) {
        $profiles = self::get_profiles(); // ⚠️ Calls get_option()
    }

    $active_id = get_option( 'msh_active_context_profile', 'primary' ); // ⚠️ GET_OPTION #1

    if ( 'primary' !== $active_id && isset( $profiles[ $active_id ] ) ) {
        $profile = $profiles[ $active_id ];

        if ( empty( $profile['label'] ) ) {
            $profile['label'] = __( 'Context profile', 'msh-image-optimizer' );
        }

        return $profile;
    }

    return array(
        'id'      => 'primary',
        'label'   => __( 'Primary Context', 'msh-image-optimizer' ),
        'usage'   => '',
        'locale'  => '',
        'notes'   => '',
        'context' => self::get_primary_context(), // ⚠️ Calls get_option()
    );
}
```

### Method: get_profiles() (Line 219-251)

```php
public static function get_profiles() {
    $profiles = get_option( 'msh_onboarding_context_profiles', array() ); // ⚠️ GET_OPTION #2
    if ( ! is_array( $profiles ) ) {
        return array();
    }

    $sanitized = array();
    foreach ( $profiles as $profile_id => $profile ) {
        if ( ! is_array( $profile ) ) {
            continue;
        }

        $context = isset( $profile['context'] )
            ? self::sanitize_context( $profile['context'], false )
            : array();

        $sanitized_id = isset( $profile['id'] ) ? sanitize_title( $profile['id'] ) : sanitize_title( $profile_id );
        if ( empty( $sanitized_id ) ) {
            $sanitized_id = uniqid( 'context_', false );
        }

        $sanitized[ $sanitized_id ] = array(
            'id'      => $sanitized_id,
            'label'   => isset( $profile['label'] ) ? sanitize_text_field( $profile['label'] ) : '',
            'usage'   => isset( $profile['usage'] ) ? sanitize_text_field( $profile['usage'] ) : '',
            'locale'  => isset( $profile['locale'] ) ? sanitize_text_field( $profile['locale'] ) : '',
            'notes'   => isset( $profile['notes'] ) ? sanitize_textarea_field( $profile['notes'] ) : '',
            'context' => $context,
        );
    }

    return $sanitized;
}
```

### Method: get_primary_context() (Line 258-263)

```php
public static function get_primary_context() {
    $stored             = get_option( 'msh_onboarding_context', array() ); // ⚠️ GET_OPTION #3
    $existing_timestamp = isset( $stored['updated_at'] ) ? absint( $stored['updated_at'] ) : 0;

    return self::sanitize_context( $stored, false, $existing_timestamp );
}
```

---

## Call Chain Analysis

### Single Image Optimization Call Stack

```
optimize_single_image() [Line 8140]
├── Line 8382: generate_filename_slug()
│   ├── Line 2144: ensure_fresh_context()
│   │   └── Line 282: get_active_context_signature() → get_option() CASCADE #1
│   └── Line 2145: hydrate_active_context()
│       └── Lines 225-226: get_profiles() + get_active_profile() → get_option() CASCADE #2
├── Line 8387: get_context_signature()
│   └── Line 298: get_active_context_signature() → get_option() CASCADE #3
└── Line 8561: get_option('msh_ai_mode', 'manual') → DIRECT get_option() #4
```

### Estimated Time Impact

With 11.3MB bloated wp_options table, EACH get_option() call = ~5 minutes table lock

**Per Image**:
- ensure_fresh_context() cascade: 3 get_option() calls = 15 minutes
- get_context_signature(): 1 cascade = 5 minutes
- Direct ai_mode check: 1 call = 5 minutes
- **Total**: ~25 minutes PER IMAGE (actual: varies 5-10 min due to caching/race conditions)

**For 3 Images**:
- Expected: 15-30 seconds total
- **Actual**: 15-75 minutes total

---

## Phase 1F Implementation Plan

### Step 1: Add Batch Caching to MSH_Contextual_Meta_Generator

**File**: `/includes/class-msh-image-optimizer.php`
**Location**: MSH_Contextual_Meta_Generator class

```php
// Add to class properties (around line 38-43)
private $batch_mode           = false;
private $batch_ai_mode        = null;      // NEW: Cache ai_mode during batch
private $batch_context_cached = false;     // NEW: Flag to prevent repeated context queries

// Modify ensure_fresh_context() (Lines 277-287)
private function ensure_fresh_context() {
    // Phase 1F: Skip context refresh during batch
    if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
        return; // Context was cached at batch start
    }

    if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
        return;
    }

    $current_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();

    if ( $current_signature !== $this->context_signature ) {
        $this->hydrate_active_context();
    }
}

// Modify hydrate_active_context() (Lines 220-275)
private function hydrate_active_context() {
    // Phase 1F: Skip if already cached during batch
    if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH && $this->batch_context_cached ) {
        return; // Already hydrated at batch start
    }

    if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
        return;
    }

    $profiles       = MSH_Image_Optimizer_Context_Helper::get_profiles();
    $active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile( $profiles );
    $context        = isset( $active_profile['context'] ) && is_array( $active_profile['context'] )
        ? $active_profile['context']
        : array();

    // ... rest of existing hydration code ...

    // Phase 1F: Mark as cached for batch
    if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
        $this->batch_context_cached = true;
    }
}

// Add method to enable batch mode with caching
public function enable_batch_mode_with_cache() {
    $this->enable_batch_mode(); // Existing method (line 603)

    // Phase 1F: Hydrate context ONCE before batch starts
    $this->hydrate_active_context();
    $this->batch_context_cached = true;
}
```

### Step 2: Cache AI Mode at Batch Start

**File**: `/includes/class-msh-image-optimizer.php`
**Location**: `ajax_optimize_batch()` method (around line 7900)

```php
public function ajax_optimize_batch() {
    // Existing batch constant definition (Phase 1A)
    if ( ! defined( 'MSH_IN_OPTIMIZE_BATCH' ) ) {
        define( 'MSH_IN_OPTIMIZE_BATCH', true );
    }

    // Phase 1F: Cache AI mode ONCE before batch starts
    $batch_ai_mode = get_option( 'msh_ai_mode', 'manual' );

    // Phase 1F: Enable batch mode with context caching
    $this->contextual_meta_generator->enable_batch_mode_with_cache();

    // ... rest of batch processing ...
}
```

### Step 3: Use Cached AI Mode During Batch

**File**: `/includes/class-msh-image-optimizer.php`
**Line**: 8559-8569 in `optimize_single_image()`

```php
try {
    // Phase 1F: Use cached AI mode during batch
    if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
        // Use cached value from batch start
        $ai_mode = $this->get_batch_ai_mode(); // New helper method
    } else {
        // Normal operation: query wp_options
        $ai_mode = get_option( 'msh_ai_mode', 'manual' );
    }

    $ai_options = array();
    if ( $ai_mode !== 'manual' ) {
        $ai_options = array(
            'ai_regeneration' => true,
            'ai_mode'         => 'fill-empty',
            'ai_fields'       => array( 'title', 'alt_text', 'caption', 'description' ),
        );
    }
```

**Add helper method** (around line 8000):
```php
/**
 * Get cached AI mode for batch operations.
 *
 * @return string AI mode setting.
 */
private function get_batch_ai_mode() {
    if ( ! isset( $this->batch_ai_mode ) ) {
        $this->batch_ai_mode = get_option( 'msh_ai_mode', 'manual' );
    }
    return $this->batch_ai_mode;
}
```

### Step 4: Use Cached Context Signature

**File**: `/includes/class-msh-image-optimizer.php`
**Lines**: 8387, 9092

```php
// Line 8387 - In optimize_single_image()
update_post_meta( $attachment_id, '_msh_suggested_filename_context', $this->contextual_meta_generator->get_context_signature() );
// ✅ Already uses cached signature via get_context_signature() - NO CHANGE NEEDED if ensure_fresh_context() is fixed

// Line 9092 - In suggest_and_possibly_apply_filename()
// Phase 1F: Use cached signature during batch
if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
    $current_context_signature = $this->contextual_meta_generator->get_context_signature();
} else {
    $current_context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();
}
```

---

## Testing Checklist

### Before Deploying Phase 1F

- [ ] All 4 get_option() locations have batch guards
- [ ] Context hydration happens ONCE at batch start
- [ ] AI mode cached at batch start
- [ ] Context signature uses cached value during batch
- [ ] ensure_fresh_context() skips queries during batch
- [ ] PHP syntax validates
- [ ] Version bumped to 1.2.5

### After Deploying Phase 1F

- [ ] Upload v1.2.5 plugin
- [ ] Clear debug.log
- [ ] Select 3 images
- [ ] Click "Optimize Selected"
- [ ] **Verify optimization completes in <30 seconds** (5-10 sec per image)
- [ ] Verify NO Fatal errors in debug.log
- [ ] Verify images show "Optimized" status
- [ ] Check debug.log - should show NO get_option timeouts during batch

### Success Criteria

✅ **PASS**: 3-image batch completes in 15-30 seconds total
✅ **PASS**: No "Maximum execution time" errors
✅ **PASS**: No wp_options table lock delays
✅ **PASS**: Images renamed and optimized successfully

❌ **FAIL**: Optimization takes >60 seconds for 3 images
❌ **FAIL**: Fatal error: Maximum execution time exceeded
❌ **FAIL**: Optimization hangs at any percentage

---

## Files to Modify

1. **`/includes/class-msh-image-optimizer.php`**
   - Line 38-43: Add batch cache properties to MSH_Contextual_Meta_Generator
   - Line 277-287: Modify ensure_fresh_context() with batch guard
   - Line 220-275: Modify hydrate_active_context() with batch guard
   - Line 603-610: Add enable_batch_mode_with_cache() method
   - Line 7900-7910: Cache AI mode and context at batch start in ajax_optimize_batch()
   - Line 8000: Add get_batch_ai_mode() helper
   - Line 8559-8569: Use cached AI mode during batch
   - Line 9092: Use cached context signature during batch

2. **`/msh-image-optimizer.php`**
   - Line 6: Version 1.2.5
   - Line 36: VERSION constant 1.2.5

---

## Summary

**Root Cause**: 4 get_option() call locations causing 5-minute MySQL table locks each
**Solution**: Cache ALL context and AI mode data at batch start, use cached values during batch
**Impact**: Reduces optimization time from 5-25 minutes per image to 5-10 seconds per image
**Priority**: CRITICAL - Without this, batch optimization is unusable

---

**END OF PHASE 1F AUDIT**
