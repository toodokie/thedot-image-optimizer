# Phase 6: AI Metadata Generation Improvements

**Date:** 2025-10-29
**Version:** 20251029.1
**Status:** ✅ Implemented

## Overview

Complete refactor of AI metadata generation system with sophisticated prompt architecture, quality tracking, and telemetry hooks.

## Key Improvements

### 1. brand_name_visible Flag System

**Problem:** AI was instructed to "always include business name" but validator blocked it for generic images, causing all AI suggestions to be rejected and fall back to poor heuristic metadata.

**Solution:** Explicit `brand_name_visible` boolean flag passed to AI based on image context type.

**Implementation:**
```php
// class-msh-openai-connector.php:226-234
$business_related_types = apply_filters( 'msh_business_related_types', $this->business_related_types, $context );
$brand_name_visible = in_array( $context_type, $business_related_types, true ) ? 'true' : 'false';

error_log( sprintf(
    '[MSH OpenAI] Prompt v%s - context_type: %s, brand_name_visible: %s',
    self::PROMPT_VERSION,
    $context_type,
    $brand_name_visible
) );
```

**Business-Related Types** (allow brand name):
- `brand_logo`
- `team`
- `facility`
- `equipment`
- `testimonial`
- `clinical`
- `business`

**Generic Types** (no brand name):
- Stock photos (landscapes, buildings)
- Generic imagery (food, objects)
- Decorative patterns

### 2. Structured SYSTEM/USER Prompt

**Old Architecture:**
- Single user message with inline instructions
- Implicit business name handling
- No quality metadata

**New Architecture:**
- SYSTEM message defines role and guidelines
- USER message provides context parameters
- Explicit `brand_name_visible` flag controls behavior

**SYSTEM Message Includes:**
- Role definition
- Business context (business_name, industry, service_area, etc.)
- Input parameters (brand_name_visible, context_type, page_role)
- Output JSON structure with quality fields
- Clear guidelines for brand name usage

**USER Message Includes:**
- All context values
- Image URL
- Original filename
- brand_name_visible: true/false
- context_type classification

### 3. Enhanced JSON Output Structure

**New Fields:**
```json
{
  "file_name_suggestion": "clinic-exterior-modern-building.jpg",
  "title": "Modern Clinic Exterior - Main Street Health",
  "alt_text": "Modern healthcare facility with glass windows",
  "caption": "Main Street Health's modern clinic in Hamilton.",
  "description": "Modern healthcare facility...",
  "keywords": ["clinic", "healthcare", "Hamilton", "rehabilitation"],
  "confidence": 0.92,
  "issues": ["text_in_image_detected"]
}
```

**Quality Metadata:**
- `confidence` (0.0-1.0): AI's confidence in the metadata
- `issues[]`: Array of detected problems
  - `brand_name_assumed`: AI included business name when brand_name_visible=false
  - `low_confidence`: AI confidence < 0.50
  - `text_in_image_detected`: Image contains text/signage
  - `decorative_image`: Pure background/pattern (empty alt/title OK)

### 4. Simplified Validator

**Old Logic:**
- Dynamically blocked business name based on context_type
- Rejected ALL metadata if business name found
- Caused AI → Validator conflict

**New Logic:**
- Trusts AI's `brand_name_visible` flag
- Only rejects if AI violated its own rules (flagged in `issues`)
- Logs warnings for non-critical issues

**Critical Issues (REJECT):**
- `brand_name_assumed` (confidence ≤ 0.70)
- `confidence < 0.5`

**Info Issues (LOG ONLY):**
- `text_in_image_detected`
- `decorative_image`

### 5. Prompt Version Tracking

**Implementation:**
```php
// class-msh-openai-connector.php:25
const PROMPT_VERSION = '20251029.1'; // Phase 6: brand_name_visible + confidence + issues[]
```

**Logged in Every AI Call:**
```
[MSH OpenAI] Prompt v20251029.1 - context_type: team, brand_name_visible: true
```

**Included in Metadata Response:**
```php
$sanitized['prompt_version'] = self::PROMPT_VERSION;
```

**Version Format:** `YYYYMMDD.revision`
- Easy to track which prompt generated each result
- Increment revision for same-day changes
- Increment date for new-day changes

### 6. Token Usage Tracking

**Extraction from OpenAI Response:**
```php
// class-msh-openai-connector.php:137-161
if ( isset( $response_data['usage'] ) ) {
    $tokens_used = array(
        'prompt_tokens'     => $response_data['usage']['prompt_tokens'] ?? 0,
        'completion_tokens' => $response_data['usage']['completion_tokens'] ?? 0,
        'total_tokens'      => $response_data['usage']['total_tokens'] ?? 0,
    );

    error_log( sprintf(
        '[MSH OpenAI] Token usage - prompt: %d, completion: %d, total: %d',
        $tokens_used['prompt_tokens'],
        $tokens_used['completion_tokens'],
        $tokens_used['total_tokens']
    ) );
}
```

**Future Integration Hook (Ready):**
```php
// if ( class_exists( 'MSH_Token_Manager' ) ) {
//     $token_manager = MSH_Token_Manager::get_instance();
//     $token_manager->deduct( $attachment_id, $tokens_used['total_tokens'], 'vision_metadata' );
// }
```

### 7. Batch Telemetry Logging

**Metrics Tracked Per Batch:**
- `confidence_scores[]` → calculates average
- `brand_name_assumed_count` → rule violations
- `decorative_image_count` → accessibility optimization
- `text_detected_count` → OCR opportunity
- `low_confidence_count` → quality warning
- `ai_success_count` vs `ai_fallback_count` → success rate

**Example Log Output:**
```
[MSH Telemetry] AI Batch Complete - processed: 10, success: 9, fallback: 1,
confidence_avg: 0.87, brand_assumed: 0, decorative: 1, text_detected: 2, low_confidence: 0
```

**Supabase Integration Hook (Ready):**
```php
// do_action( 'msh_log_telemetry', 'ai_batch_complete', $telemetry );
```

## Files Modified

### `includes/class-msh-openai-connector.php`

**Lines 25-39:** Added `PROMPT_VERSION` constant and configurable `business_related_types` property

**Lines 117-127:** Replaced `build_vision_prompt()` with `build_prompt_messages()` call

**Lines 137-161:** Added token usage extraction and logging

**Lines 188-294:** New `build_prompt_messages()` method with SYSTEM/USER structure

**Lines 358-388:** Updated `call_openai_vision()` to accept messages array

**Lines 503-547:** Enhanced `parse_openai_response()` for new JSON structure

**Lines 566-631:** Simplified `validate_ai_response()` to use issues array

### `includes/class-msh-image-optimizer.php`

**Lines 7914-7923:** Added telemetry metrics tracking array

**Lines 7982-8008:** Added telemetry tracking during image processing loop

**Lines 8079-8099:** Added batch telemetry logging at completion

## Multi-Tenant Customization

**Filter Hook for Business-Related Types:**
```php
add_filter( 'msh_business_related_types', function( $types, $context ) {
    // Client X wants business name on ALL images
    return array_merge( $types, array( 'landscape', 'decorative', 'stock' ) );
}, 10, 2 );
```

**Use Cases:**
- Different clients have different branding strategies
- Some want business name everywhere (SEO-focused)
- Others want honest descriptions only (quality-focused)

## Future Enhancements

### When MSH_Token_Manager is Created

**Uncomment Deduction Hook:**
```php
if ( class_exists( 'MSH_Token_Manager' ) ) {
    $token_manager = MSH_Token_Manager::get_instance();
    $token_manager->deduct( $attachment_id, $tokens_used['total_tokens'], 'vision_metadata' );
}
```

**Instant Benefits:**
- Per-image billing tracking
- Token spend by prompt version
- Automatic safety caps enforcement

### When Supabase Integration is Ready

**Telemetry Action Hook:**
```php
do_action( 'msh_log_telemetry', 'ai_batch_complete', $telemetry );
```

**Queryable Metrics:**
- Track quality trends over time
- Identify confidence degradation
- Compare prompt version performance
- A/B test prompt changes

### Additional Context Fields (TODOs)

**Currently Placeholder:**
```php
$page_title    = ''; // TODO: Get from post context when available
$focus_keyword = ''; // TODO: Get from SEO plugin when available
$page_role     = 'general_content_image'; // TODO: Determine based on usage context
```

**Future Implementation:**
- Extract page title from parent post
- Integrate with Yoast/RankMath for focus keyword
- Detect header_image vs article_body_image usage

## Testing

**Test Images:**
- ✅ Team photo → brand_name_visible=true → includes "Main Street Health"
- ✅ Clinic building → brand_name_visible=true → includes business name
- ✅ Generic landscape → brand_name_visible=false → NO business name
- ✅ Golden Gate Bridge → brand_name_visible=false → NO business name

**Expected Improvements:**
1. No more validator rejections due to business name conflicts
2. Accurate descriptions for generic stock photos
3. Consistent branding on business-related images
4. Quality metrics visible in logs

## Rollback Plan

If issues arise:

1. **Revert to Previous Prompt:**
   ```php
   const PROMPT_VERSION = '20251028.1'; // Previous version
   ```

2. **Restore Old Validator:**
   ```php
   // Re-enable dynamic business name blocking
   $banned_terms[] = strtolower( $context['business_name'] );
   ```

3. **Disable Telemetry:**
   ```php
   // Comment out telemetry tracking lines
   ```

## Success Metrics

**Quality:**
- Average confidence score > 0.80
- brand_name_assumed_count = 0 (no violations)
- Reduced ai_fallback_count

**Performance:**
- Token usage per image (baseline established)
- Batch processing time unchanged
- No increase in API errors

**Business Value:**
- More accurate metadata for SEO
- Better accessibility (proper decorative handling)
- Honest descriptions build trust
- Consistent branding on relevant images

---

**Next Steps:** See [TELEMETRY-INTEGRATION.md](./TELEMETRY-INTEGRATION.md) for Supabase integration guide.
