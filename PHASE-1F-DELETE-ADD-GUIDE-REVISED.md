# Phase 1F: Complete DELETE/ADD Guide (REVISED with Reviewer Fixes)

**File**: `/includes/class-msh-image-optimizer.php`

This guide shows EXACTLY what to delete and what to add for each location.

**REVISED**: Includes 5 critical fixes from code review:
1. Fixed duplicate `define()` in Location 6
2. Added end-of-batch cleanup/teardown
3. Added signature computation in `hydrate_active_context()`
4. Fixed summary wording (active context, not all profiles)
5. Added `try/finally` guard for cleanup

---

## PART A: Add New Properties and Methods

### A1: Add Cache Properties to MSH_Contextual_Meta_Generator Class

**FIND**: The class properties section (around line 38-43, near other `private $` declarations)

**ADD THESE LINES**:
```php
// Phase 1F: batch cache to avoid wp_options queries
private $msh_batch_mode           = false;
private $msh_batch_context_cached = false;
private $msh_cached_context       = array();
private $msh_cached_signature     = '';
```

---

### A2: Add Cache Property to MSH_Image_Optimizer Class

**FIND**: The MSH_Image_Optimizer class properties section (around line 5700+)

**ADD THIS LINE**:
```php
// Phase 1F: cache AI mode at batch start
private $msh_batch_ai_mode = null;
```

---

### A3: Add Batch Cache Enable Method to MSH_Contextual_Meta_Generator Class

**FIND**: The public methods section of MSH_Contextual_Meta_Generator (after `get_current_season()` around line 683)

**ADD THIS METHOD**:
```php
/**
 * Phase 1F: Enable batch mode and pre-hydrate context once.
 * Call this at batch start to cache all context data.
 *
 * @since 1.2.5
 */
public function msh_enable_batch_mode_with_cache() {
	$this->msh_batch_mode           = true;
	$this->msh_batch_context_cached = false;
	$this->hydrate_active_context();   // Queries wp_options ONCE
	$this->msh_batch_context_cached = true;
}
```

---

### A4: Add Batch Cache Disable Method to MSH_Contextual_Meta_Generator Class

**FIND**: Right after the `msh_enable_batch_mode_with_cache()` method you just added

**ADD THIS METHOD**:
```php
/**
 * Phase 1F: Disable batch mode and clear caches.
 * Call this at batch end to prevent cache leaks.
 *
 * @since 1.2.5
 */
public function msh_disable_batch_mode() {
	$this->msh_batch_mode           = false;
	$this->msh_batch_context_cached = false;
	$this->msh_cached_context       = array();
	$this->msh_cached_signature     = '';
}
```

---

### A5: Add Batch AI Mode Getter to MSH_Image_Optimizer Class

**FIND**: Private methods section of MSH_Image_Optimizer (around line 8000)

**ADD THIS METHOD**:
```php
/**
 * Phase 1F: Get cached AI mode for batch operations.
 * Never touches wp_options during batch.
 *
 * @since 1.2.5
 * @return string AI mode setting.
 */
private function get_batch_ai_mode() {
	// Never touch options in the loop
	if ( $this->msh_batch_ai_mode === null ) {
		$this->msh_batch_ai_mode = 'manual';
	}
	return $this->msh_batch_ai_mode;
}
```

---

## PART B: Modify Existing Methods

### Location 1: optimize_single_image() - Cache AI Mode (Line ~8561)

**FIND THIS CODE**:
```php
try {
	// Check if AI mode is enabled - if so, regenerate with AI
	$ai_mode    = get_option( 'msh_ai_mode', 'manual' );
	$ai_options = array();
```

**DELETE**:
```php
	$ai_mode    = get_option( 'msh_ai_mode', 'manual' );
```

**REPLACE WITH**:
```php
	// Phase 1F: never hit wp_options during batch
	$ai_mode = ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH )
		? $this->get_batch_ai_mode()
		: get_option( 'msh_ai_mode', 'manual' );
```

**RESULT LOOKS LIKE**:
```php
try {
	// Check if AI mode is enabled - if so, regenerate with AI
	// Phase 1F: never hit wp_options during batch
	$ai_mode = ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH )
		? $this->get_batch_ai_mode()
		: get_option( 'msh_ai_mode', 'manual' );
	$ai_options = array();
```

---

### Location 2: ensure_fresh_context() - Skip During Batch (Lines ~277-287)

**FIND THIS CODE**:
```php
private function ensure_fresh_context() {
	if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		return;
	}

	$current_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();

	if ( $current_signature !== $this->context_signature ) {
		$this->hydrate_active_context();
	}
}
```

**DELETE THE ENTIRE METHOD BODY**

**REPLACE WITH**:
```php
private function ensure_fresh_context() {
	// Phase 1F: skip any helper calls during batch
	if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
		return;
	}

	if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		return;
	}

	$current_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();

	if ( $current_signature !== $this->context_signature ) {
		$this->hydrate_active_context();
	}
}
```

---

### Location 3: hydrate_active_context() - Use Cached Context (Lines ~220-275)

**FIND THIS CODE** (at the very beginning of the method):
```php
private function hydrate_active_context() {
	if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		return;
	}

	$profiles       = MSH_Image_Optimizer_Context_Helper::get_profiles();
	$active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile( $profiles );
	$context        = isset( $active_profile['context'] ) && is_array( $active_profile['context'] )
		? $active_profile['context']
		: array();

	// ... rest of existing hydration code continues ...
```

**ADD THESE LINES AT THE VERY START** (before the class_exists check):
```php
private function hydrate_active_context() {
	// Phase 1F: if batch is active and already cached, reuse memory only
	if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH && $this->msh_batch_context_cached ) {
		$this->active_context    = $this->msh_cached_context;
		$this->context_signature = $this->msh_cached_signature;
		return;
	}

	if ( ! class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		return;
	}

	// ... existing code continues unchanged ...
```

**THEN FIND** (after the context is built, before all the property assignments):
```php
	$profiles       = MSH_Image_Optimizer_Context_Helper::get_profiles();
	$active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile( $profiles );
	$context        = isset( $active_profile['context'] ) && is_array( $active_profile['context'] )
		? $active_profile['context']
		: array();

	$this->active_profile_id    = isset( $active_profile['id'] ) ? sanitize_title( $active_profile['id'] ) : 'primary';
```

**ADD THESE LINES AFTER** the `$context` is set and BEFORE property assignments:
```php
	$profiles       = MSH_Image_Optimizer_Context_Helper::get_profiles();
	$active_profile = MSH_Image_Optimizer_Context_Helper::get_active_profile( $profiles );
	$context        = isset( $active_profile['context'] ) && is_array( $active_profile['context'] )
		? $active_profile['context']
		: array();

	// Phase 1F: Ensure signature is computed NOW so cache has it
	if ( empty( $this->context_signature ) && class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		$this->context_signature = MSH_Image_Optimizer_Context_Helper::build_context_signature( $context );
	}

	$this->active_profile_id    = isset( $active_profile['id'] ) ? sanitize_title( $active_profile['id'] ) : 'primary';
```

**THEN FIND** (at the very end of the method, after all the existing hydration code):
```php
	$this->pain_points     = isset( $context['pain_points'] ) ? sanitize_textarea_field( $context['pain_points'] ) : '';
}
```

**ADD THESE LINES BEFORE THE CLOSING BRACE**:
```php
	$this->pain_points     = isset( $context['pain_points'] ) ? sanitize_textarea_field( $context['pain_points'] ) : '';

	// Phase 1F: if we are bootstrapping batch, store cache for reuse
	if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH && $this->msh_batch_mode ) {
		$this->msh_cached_context       = $context;
		$this->msh_cached_signature     = $this->context_signature;
		$this->msh_batch_context_cached = true;
	}
}
```

---

### Location 4: get_context_signature() - Return Cached Signature (Lines ~296-302)

**FIND THIS CODE**:
```php
public function get_context_signature() {
	if ( empty( $this->context_signature ) && class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		$this->context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature( $this->active_context );
	}

	return $this->context_signature;
}
```

**DELETE THE ENTIRE METHOD BODY**

**REPLACE WITH**:
```php
public function get_context_signature() {
	// Phase 1F: serve cached signature in batch
	if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
		return $this->msh_cached_signature ?: $this->context_signature;
	}

	if ( empty( $this->context_signature ) && class_exists( 'MSH_Image_Optimizer_Context_Helper' ) ) {
		$this->context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature( $this->active_context );
	}

	return $this->context_signature;
}
```

---

### Location 5: suggest_and_possibly_apply_filename() - Use Cached Signature (Line ~9092)

**FIND THIS CODE**:
```php
// Save the suggestion with timestamp
$current_context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();
update_post_meta( $image_id, '_msh_suggested_filename', $suggested_filename );
```

**DELETE**:
```php
$current_context_signature = MSH_Image_Optimizer_Context_Helper::get_active_context_signature();
```

**REPLACE WITH**:
```php
// Phase 1F: do not call helper during batch
$current_context_signature = ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH )
	? $this->contextual_meta_generator->get_context_signature()
	: MSH_Image_Optimizer_Context_Helper::get_active_context_signature();
```

---

### Location 6: ajax_optimize_batch() - Initialize Caches (Line ~7905)

**REVIEWER FIX #1**: Removed duplicate `define()` block

**FIND THIS CODE**:
```php
public function ajax_optimize_batch() {
	// Existing batch constant definition (Phase 1A)
	if ( ! defined( 'MSH_IN_OPTIMIZE_BATCH' ) ) {
		define( 'MSH_IN_OPTIMIZE_BATCH', true );
	}

	// ... rest of batch processing ...
}
```

**ADD THESE LINES AFTER** the `define( 'MSH_IN_OPTIMIZE_BATCH', true );` and WRAP batch loop in try/finally:
```php
public function ajax_optimize_batch() {
	if ( ! defined( 'MSH_IN_OPTIMIZE_BATCH' ) ) {
		define( 'MSH_IN_OPTIMIZE_BATCH', true );
	}

	// Phase 1F: Cache AI mode ONCE before batch loop starts
	$this->msh_batch_ai_mode = get_option( 'msh_ai_mode', 'manual' );

	// Phase 1F: Pre-hydrate contextual generator ONCE before batch loop
	if ( isset( $this->contextual_meta_generator ) && is_object( $this->contextual_meta_generator ) ) {
		$this->contextual_meta_generator->msh_enable_batch_mode_with_cache();
	}

	try {
		// ... EXISTING batch loop code here ...
		// ... foreach ( $batch_images as $image_id ) { ... }
		// ... optimize_single_image() calls ...
		// ... ALL existing batch processing ...

	} finally {
		// Phase 1F: Teardown batch state - runs even if loop errors
		$this->msh_batch_ai_mode = null;
		if ( isset( $this->contextual_meta_generator ) && is_object( $this->contextual_meta_generator ) ) {
			$this->contextual_meta_generator->msh_disable_batch_mode();
		}
	}

	// ... rest of method continues (AJAX response, etc.) ...
}
```

**IMPORTANT**: The `try { ... } finally { ... }` should wrap the ENTIRE batch loop, but NOT the AJAX response code at the end.

---

## PART C: Version Bump

### File: `msh-image-optimizer.php` (plugin header)

**FIND** (around line 6):
```php
 * Version:           1.2.4
```

**REPLACE WITH**:
```php
 * Version:           1.2.5
```

**FIND** (around line 36):
```php
define( 'MSH_IMAGE_OPTIMIZER_VERSION', '1.2.4' );
```

**REPLACE WITH**:
```php
define( 'MSH_IMAGE_OPTIMIZER_VERSION', '1.2.5' );
```

---

## Summary of Changes

### Files Modified
- **includes/class-msh-image-optimizer.php** - 6 locations + teardown
- **msh-image-optimizer.php** - 2 lines (version bump)

### What Gets Cached at Batch Start (REVIEWER FIX #4: Corrected wording)
1. **AI Mode** - `get_option('msh_ai_mode')` → cached in `$this->msh_batch_ai_mode`
2. **Active Context for current profile** - Active context data → cached in `$this->msh_cached_context`
3. **Context Signature for active context** - MD5 hash → cached in `$this->msh_cached_signature`

### What NO LONGER Queries wp_options During Batch
1. ✅ Line 8561 - AI mode check
2. ✅ Line 8382/2144 - Context refresh in generate_filename_slug()
3. ✅ Line 8387 - Context signature stamping
4. ✅ Line 9092 - Context signature in filename suggestions
5. ✅ Lines 277-287 - ensure_fresh_context() becomes no-op
6. ✅ Lines 220-275 - hydrate_active_context() uses cache

### Expected Performance Improvement
- **Before Phase 1F**: 5-20 minutes per image (multiple 5-minute wp_options locks)
- **After Phase 1F**: 5-10 seconds per image (no wp_options queries)
- **3 images**: From 15-60 minutes → to 15-30 seconds

---

## Testing Checklist

After applying ALL changes above:

1. ☐ PHP syntax validation: `php -l includes/class-msh-image-optimizer.php`
2. ☐ Upload plugin v1.2.5
3. ☐ Clear `wp-content/debug.log`
4. ☐ Select 3 images
5. ☐ Click "Optimize Selected"
6. ☐ **Verify completes in <30 seconds**
7. ☐ Check debug.log for NO "Maximum execution time" errors
8. ☐ Verify all 3 images show "Optimized" status
9. ☐ If MU logger is active, verify NO `[MSH][OPTIONS][BATCH]` entries

---

## Optional: Database Triage (If Even 1 get_option() is Slow)

**Note**: Phase 1F reduces wp_options queries to ONLY 1 call at batch start. If even that 1 call is painfully slow (>5 seconds), run this maintenance SQL:

```sql
-- Make bloated options non-autoloaded
UPDATE wp_options SET autoload='no' WHERE option_name IN ('cron','rewrite_rules');

-- Delete stale transients
DELETE FROM wp_options WHERE option_name LIKE '_transient_%';
DELETE FROM wp_options WHERE option_name LIKE '_site_transient_%';
```

This shrinks the autoloaded options, making the single batch-start query fast.

---

## Reviewer Fixes Applied

✅ **Fix #1**: Removed duplicate `define('MSH_IN_OPTIMIZE_BATCH')` in Location 6
✅ **Fix #2**: Added `msh_disable_batch_mode()` method and `finally` teardown
✅ **Fix #3**: Added signature computation in `hydrate_active_context()` at line ~230
✅ **Fix #4**: Corrected summary wording - "active context" not "all profiles"
✅ **Fix #5**: Wrapped batch loop in `try/finally` for guaranteed cleanup

---

**END OF PHASE 1F DELETE/ADD GUIDE (REVISED)**
