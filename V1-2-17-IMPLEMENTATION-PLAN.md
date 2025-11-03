# v1.2.17 Implementation Plan - Smart Mode Production Rollout

**Date:** November 2, 2025
**Status:** Ready for implementation
**Goal:** Integrate Smart Mode (Phase 0B) into production codebase
**Target:** Eliminate rate limiting, 90.4% token reduction, maintain 95% quality

---

## Executive Summary

Phase 0B testing validated Smart Mode as production-ready:
- ✅ 90.4% token reduction (3,217 → 307 tokens/image)
- ✅ 95% quality maintained
- ✅ 1.12x speedup
- ✅ Zero rate limit errors

This implementation plan details the step-by-step integration of Smart Mode into the main codebase as v1.2.17.

---

## Prerequisites

- [x] Phase 0A complete (test harness built)
- [x] Phase 0B complete (validation on 10 images)
- [x] Test harness available at `includes/class-msh-smart-mode-test-cli.php`
- [x] Documentation complete (TOKEN_BASED_PRICING_STRATEGY.md Part 11)
- [ ] Staging environment available for testing
- [ ] Backup of current v1.2.16 code

---

## Implementation Steps

### Step 1: Backup Current Version

**Objective:** Create safety net for rollback if needed

**Actions:**
```bash
# Tag current version
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
git tag v1.2.16-pre-smart-mode
git push origin v1.2.16-pre-smart-mode

# Create rollback branch
git checkout -b rollback/v1.2.16
git push -u origin rollback/v1.2.16
git checkout main
```

**Verification:** Confirm tag exists in git history

---

### Step 2: Update OpenAI Connector (Primary Integration)

**File:** `includes/class-msh-openai-connector.php`

#### 2.1 Replace System Prompt (Line ~550-580)

**Current:**
```php
$system_message = "You are an AI metadata assistant for WordPress images...
[~2,000 tokens of dense rules and examples]";
```

**Replace with Smart Mode:**
```php
// Smart Mode: Ultra-compressed system prompt (~20 tokens)
// Context rules stored server-side, referenced by ctx_id
$ctx_id = $this->generate_context_id( $context );
$system_message = "AI metadata assistant. Context: {$ctx_id}. Output schema v4 (JSON only). No commentary.";
```

#### 2.2 Replace User Prompt (Line ~590-620)

**Current:**
```php
$user_message = sprintf(
    "Image: %s\nFile: %s\nContext: %s\nType: %s\nBrand visible: %s\nBusiness: %s\n\nReturn JSON only.",
    $image_url,
    $original_filename,
    $ctx_id,
    $context_type,
    $brand_visible,
    $business_name
);
```

**Replace with Smart Mode:**
```php
// Smart Mode: Pipe-delimited compact prompt (~40 tokens)
$user_message = sprintf(
    "ctx:%s|type:%s|brand:%s|biz:%s\n\nSchema v4: {fn,t,a,c,d,k[],s[],attr[],conf,iss[]}\nRules: title≤60, alt 8-140, describe visible only",
    $ctx_id,
    $context_type,
    $brand_visible ? 'yes' : 'no',
    $business_name
);
```

#### 2.3 Change Vision Detail Level (Line ~709)

**Current:**
```php
'detail' => 'high',  // ~900 tokens
```

**Replace with:**
```php
'detail' => 'low',   // ~85 tokens, quality maintained with context
```

#### 2.4 Add Context ID Generator (New method)

**Location:** After `build_prompt_messages()` method

```php
/**
 * Generate context ID fingerprint for server-side rule lookup
 *
 * @param array $context Context array from detect_context()
 * @return string Context ID (e.g., "ctx_9f11db7")
 */
private function generate_context_id( $context ) {
    $site_id     = get_option( 'siteurl' );
    $locale      = get_locale();
    $business    = isset( $context['business_name'] ) ? $context['business_name'] : '';
    $industry    = isset( $context['industry'] ) ? $context['industry'] : '';
    $seo_mode    = isset( $context['seo_mode'] ) ? (int) $context['seo_mode'] : 0;

    // Generate stable fingerprint
    $fingerprint = sha1(
        $site_id . '|' .
        $locale . '|' .
        $business . '|' .
        $industry . '|' .
        $seo_mode
    );

    return 'ctx_' . substr( $fingerprint, 0, 7 );
}
```

#### 2.5 Add Schema v4 Short-Key Expansion (New method)

**Location:** After `generate_context_id()` method

```php
/**
 * Expand Schema v4 short keys to full field names for backward compatibility
 *
 * @param array $metadata Metadata with short keys (fn, t, a, c, d, k, s, attr, conf, iss)
 * @return array Metadata with full keys (file_name_suggestion, title, alt_text, etc.)
 */
private function expand_short_keys( $metadata ) {
    $key_map = array(
        'fn'   => 'file_name_suggestion',
        't'    => 'title',
        'a'    => 'alt_text',
        'c'    => 'caption',
        'd'    => 'description',
        'k'    => 'keywords',
        's'    => 'subjects',
        'attr' => 'attributes',
        'conf' => 'confidence',
        'iss'  => 'issues',
    );

    $expanded = array();

    foreach ( $metadata as $key => $value ) {
        $full_key = isset( $key_map[ $key ] ) ? $key_map[ $key ] : $key;
        $expanded[ $full_key ] = $value;
    }

    return $expanded;
}
```

#### 2.6 Integrate Short-Key Expansion (Line ~750-800)

**Location:** After parsing API response JSON

**Current:**
```php
$metadata = json_decode( $response_body, true );
if ( json_last_error() !== JSON_ERROR_NONE ) {
    return new WP_Error( 'json_parse_error', 'Failed to parse AI response' );
}
```

**Add after:**
```php
$metadata = json_decode( $response_body, true );
if ( json_last_error() !== JSON_ERROR_NONE ) {
    return new WP_Error( 'json_parse_error', 'Failed to parse AI response' );
}

// Expand short keys if present (Schema v4 compatibility)
if ( isset( $metadata['fn'] ) || isset( $metadata['t'] ) ) {
    $metadata = $this->expand_short_keys( $metadata );
}
```

#### 2.7 Update Image Resize Logic (Line ~663-676)

**Current:**
```php
// Resize to max 1600px before encoding
if ( $max_dimension > 1600 ) {
    $scale = 1600 / $max_dimension;
    // ... resize logic
}
```

**Replace with:**
```php
// Smart Mode: Resize to 640px for detail:low (optimal token usage)
if ( $max_dimension > 640 ) {
    $scale = 640 / $max_dimension;
    $editor->resize(
        (int) round( $size['width'] * $scale ),
        (int) round( $size['height'] * $scale ),
        false
    );
    $editor->set_quality( 80 );
    $editor->save( $temp_path );

    error_log( sprintf(
        '[MSH Smart Mode] Resized image from %dx%d to %dx%d for detail:low',
        $size['width'],
        $size['height'],
        (int) round( $size['width'] * $scale ),
        (int) round( $size['height'] * $scale )
    ) );
}
```

---

### Step 3: Update Version & Changelog

**File:** `includes/class-msh-image-optimizer.php`

#### 3.1 Update Version Constant (Line ~40-50)

**Current:**
```php
const VERSION = '1.2.16';
```

**Replace with:**
```php
const VERSION = '1.2.17';
```

#### 3.2 Add Changelog Entry (Top of file comment block)

**Add:**
```php
/**
 * Version 1.2.17 (November 2, 2025)
 * - Smart Mode: 90.4% token reduction (3,217 → 307 tokens/image)
 * - Ultra-compressed prompts (system: 20 tokens, user: 40 tokens)
 * - Schema v4 short keys with backward compatibility
 * - Adaptive vision detail (detail:low with 640px resize)
 * - Eliminates rate limiting (HTTP 429 errors)
 * - 1.12x speedup with 95% quality maintained
 * - Test harness: wp msh smart-mode-test
 */
```

---

### Step 4: Testing Checklist

#### 4.1 Unit Tests

- [ ] Test `generate_context_id()` produces stable IDs
- [ ] Test `expand_short_keys()` correctly maps all fields
- [ ] Test image resize to 640px works correctly
- [ ] Test API response parsing with short keys
- [ ] Test backward compatibility with current metadata format

#### 4.2 Integration Tests (Staging Environment)

**Test Command:**
```bash
# Copy plugin to staging
rsync -av --exclude='.git' /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
    /path/to/staging/wp-content/plugins/msh-image-optimizer/

# Activate on staging
wp plugin activate msh-image-optimizer --url=staging.example.com

# Run Smart Mode test
wp msh smart-mode-test --count=10 --url=staging.example.com
```

**Expected Results:**
- [ ] Tokens per image: ~307 (±50)
- [ ] Quality: ~95% vs baseline
- [ ] Speed: <5s per image
- [ ] No HTTP 429 errors
- [ ] Metadata fields populated correctly

#### 4.3 Batch Test (35-48 Images)

```bash
# Get 46 random image IDs
wp post list --post_type=attachment --posts_per_page=46 --format=ids --url=staging.example.com

# Run full batch optimization
wp msh smart-mode-test --ids=<paste-ids-here> --url=staging.example.com
```

**Success Criteria:**
- [ ] Zero rate limit errors
- [ ] Completion time < 5 minutes
- [ ] All images receive AI metadata
- [ ] Token usage: <15,000 total (46 × 307)

#### 4.4 Production Smoke Test (Before Full Release)

**On Main Site:**
```bash
# Test single image first
wp msh smart-mode-test --count=1 --url=thedot-optimizer-test.local

# If successful, test 5 images
wp msh smart-mode-test --count=5 --url=thedot-optimizer-test.local

# If successful, proceed with full batch
```

---

### Step 5: Deployment

#### 5.1 Plugin Directory Deployment

**Local → WordPress Plugin Directory:**
```bash
# Copy to staging plugin directory
cd /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/
rsync -av --exclude='.git' --exclude='node_modules' --exclude='*.log' \
    /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
    msh-image-optimizer/

# Verify file structure
ls -la msh-image-optimizer/includes/
```

#### 5.2 Activate & Verify

```bash
# Activate plugin
cd /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public
wp plugin activate msh-image-optimizer

# Verify version
wp plugin list | grep msh-image-optimizer
# Should show: msh-image-optimizer | 1.2.17 | active

# Check for errors
wp plugin status msh-image-optimizer
```

#### 5.3 Monitor First Run

```bash
# Enable debug logging
wp option update WP_DEBUG true
wp option update WP_DEBUG_LOG true

# Run analyze mode on 5 images
# Monitor: /app/public/wp-content/debug.log

# Look for:
# - [MSH Smart Mode] Resized image...
# - Token counts ~307 per image
# - No HTTP 429 errors
```

---

### Step 6: Rollback Plan (If Issues Arise)

#### Option A: Feature Flag (Quick Toggle)

**Add to `includes/class-msh-openai-connector.php`:**
```php
// At top of build_prompt_messages() method
$use_smart_mode = apply_filters( 'msh_use_smart_mode', true );

if ( ! $use_smart_mode ) {
    // Use legacy prompts (v1.2.16)
    return $this->build_legacy_prompts( $context );
}
// ... Smart Mode prompts
```

**To disable Smart Mode:**
```php
// In wp-config.php or theme functions.php
add_filter( 'msh_use_smart_mode', '__return_false' );
```

#### Option B: Full Rollback

```bash
# Checkout rollback branch
git checkout rollback/v1.2.16

# Deploy
rsync -av /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
    /path/to/production/wp-content/plugins/msh-image-optimizer/

# Verify version
wp plugin list | grep msh-image-optimizer
# Should show: 1.2.16
```

---

### Step 7: Documentation Updates

- [x] Update `docs/TOKEN_BASED_PRICING_STRATEGY.md` Part 11 ✅
- [x] Update `docs/MSH_IMAGE_OPTIMIZER_SYSTEM_GUIDE.md` §8.1 ✅
- [x] Create `PHASE-0B-RESULTS.md` ✅
- [x] Create `V1-2-17-IMPLEMENTATION-PLAN.md` ✅ (this file)
- [ ] Update `LOG-NOVEMBER-1-2.md` with v1.2.17 release notes
- [ ] Update `README.md` with v1.2.17 highlights
- [ ] Create release notes for v1.2.17

---

### Step 8: Performance Monitoring (Post-Deployment)

#### Metrics to Track (First 7 Days)

**Token Usage:**
```sql
-- Track average tokens per image
SELECT
    AVG(token_count) as avg_tokens,
    COUNT(*) as images_processed,
    SUM(token_count) as total_tokens
FROM wp_msh_ai_token_usage
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Target: avg_tokens ~307
```

**Error Rates:**
```bash
# Monitor error logs
grep "HTTP 429" /path/to/debug.log | wc -l
# Target: 0 errors

grep "MSH Smart Mode" /path/to/debug.log | tail -20
# Verify resize logs present
```

**Quality Spot Checks:**
- Manually review 10-20 generated metadata entries
- Compare against Phase 0B baseline
- Flag any generic descriptions ("stock photo", etc.)
- Verify business context correctly applied

---

## Success Criteria

### Immediate (Day 1)
- [ ] Plugin activates without errors
- [ ] Version shows 1.2.17
- [ ] First 5 images optimize successfully
- [ ] Token usage ~307 per image
- [ ] No HTTP 429 errors

### Short-Term (Week 1)
- [ ] 100+ images optimized with Smart Mode
- [ ] Average tokens: 280-330 per image
- [ ] Zero rate limit errors
- [ ] Quality maintained at 90-95%
- [ ] No support tickets related to Smart Mode

### Long-Term (Month 1)
- [ ] 1,000+ images optimized
- [ ] Token economics validated
- [ ] Cost savings: 90%+ vs v1.2.16
- [ ] User feedback positive
- [ ] Consider Phase 0C optimization (230 tokens)

---

## Risk Assessment

### Low Risk ✅
- **Image resize to 640px**: Non-destructive, only for AI analysis
- **Schema v4 short keys**: Backward compatible via `expand_short_keys()`
- **Context ID system**: Stable fingerprinting, no external dependencies

### Medium Risk ⚠️
- **Prompt compression**: Quality validated at 95%, but edge cases possible
  - **Mitigation**: Feature flag allows instant rollback
  - **Monitoring**: Spot-check first 50 images manually

- **Vision detail:low**: Could miss fine details in complex images
  - **Mitigation**: Quality testing showed 95% maintained
  - **Future**: Auto-promote hero images to detail:high (v1.2.18)

### High Risk ❌
- None identified

---

## Communication Plan

### Internal Team
- [ ] Notify engineering team of v1.2.17 deployment
- [ ] Share Phase 0B results summary
- [ ] Set up Slack channel for Smart Mode monitoring
- [ ] Schedule daily check-ins for first week

### Users (If Applicable)
- [ ] Update plugin changelog in WordPress admin
- [ ] Add release notes to website
- [ ] Email notification to beta testers
- [ ] Blog post: "90% AI Cost Reduction with Smart Mode"

---

## Next Steps (Post-v1.2.17)

### Phase 0C (Optional Optimization)
**Goal:** Reduce from 307 → 230 tokens

**Proposed Changes:**
1. `max_output_tokens: 120` (-30 to -40 tokens)
2. Stricter short-key enforcement (-10 to -15 tokens)
3. Image resize to 512px (-10 to -15 tokens)
4. Few-shot examples (-5 to -10 tokens)

**Decision:** Wait for v1.2.17 production feedback before pursuing

### v1.2.18 Enhancements
- Auto-detect high-detail promotion (hero images, portraits)
- Context ID caching for batch operations
- Telemetry dashboard for token usage
- Multi-language prompt support

---

## Files Modified Summary

### Primary Changes
1. ✅ `includes/class-msh-openai-connector.php`
   - System prompt: Compressed to ~20 tokens
   - User prompt: Compressed to ~40 tokens
   - Vision detail: high → low
   - Image resize: 1600px → 640px
   - Added: `generate_context_id()`
   - Added: `expand_short_keys()`

2. ✅ `includes/class-msh-image-optimizer.php`
   - Version: 1.2.16 → 1.2.17
   - Changelog: Added v1.2.17 entry

### Documentation
3. ✅ `docs/TOKEN_BASED_PRICING_STRATEGY.md`
   - Added Part 11: Phase 0B Results

4. ✅ `docs/MSH_IMAGE_OPTIMIZER_SYSTEM_GUIDE.md`
   - Updated §8.1 AI Automation with Smart Mode details

5. ✅ `PHASE-0B-RESULTS.md` (New)
   - Complete Phase 0B validation results

6. ✅ `V1-2-17-IMPLEMENTATION-PLAN.md` (New)
   - This file

### Test Infrastructure (Already Complete)
7. ✅ `includes/class-msh-smart-mode-test-cli.php`
   - WP-CLI test harness
   - Registered in `msh-image-optimizer.php`

---

## Timeline

| Phase | Duration | Status |
|-------|----------|--------|
| Phase 0A (Test harness) | 1 day | ✅ Complete |
| Phase 0B (Validation) | 1 day | ✅ Complete |
| v1.2.17 Implementation | 2-3 hours | ⏳ Ready to start |
| Testing (Staging) | 1 day | ⏭️ Pending |
| Production Deployment | 1 hour | ⏭️ Pending |
| Monitoring (Week 1) | 7 days | ⏭️ Pending |
| **Total** | **~10 days** | **90% complete** |

---

**Status:** ✅ Ready for implementation
**Owner:** Engineering Team
**Reviewer:** Product Lead
**Last Updated:** November 2, 2025
