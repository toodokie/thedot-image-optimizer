# Phase 6: AI Metadata Generation Improvements

**Date:** 2025-10-29
**Version:** 20251029.5
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

### 5. Server-Side Context Validator (v20251029.4)

**Problem:** AI might hallucinate business connections for stock/testimonial images despite prompt instructions.

**Solution:** Server-side `validate_context_rules()` enforces context_type semantics before accepting AI output.

**Hard Rules (REJECT immediately):**
1. `stock`, `decorative` → NEVER include business_name
2. `testimonial` → PROHIBITED phrases: "at {business}", "in our facility", "{business} client"
3. `clinical`, `business` → Strict `brand_name_visible=false` enforcement

**Soft Rules (WARN only):**
4. `service-icon` → Flag if brand present but `brand_name_visible=false`

**Implementation:**
```php
// class-msh-openai-connector.php:645-722
private function validate_context_rules( $context, &$metadata, &$issues ) {
    // Detect business name in all text fields
    $all_text = strtolower( implode( ' ', array(
        $metadata['title'] ?? '',
        $metadata['alt_text'] ?? '',
        $metadata['caption'] ?? '',
        $metadata['description'] ?? '',
    ) ) );

    $brand_found = strpos( $all_text, $business_name ) !== false;

    // Hard rule: stock/decorative → NEVER include business_name
    if ( in_array( $type, array( 'stock', 'decorative' ), true ) && $brand_found ) {
        return new WP_Error( 'context_mismatch', "Business name not allowed for {$type} images" );
    }

    // Hard rule: testimonial → PROHIBITED phrases
    if ( $type === 'testimonial' ) {
        foreach ( $forbidden as $phrase ) {
            if ( strpos( $all_text, $phrase ) !== false ) {
                return new WP_Error( 'context_mismatch', "Testimonial images cannot claim business location/ownership" );
            }
        }
    }
}
```

**New Issue Type:**
- `context_mismatch` → Added to `issues[]` array and causes validation rejection

**Anti-Hallucination:**
- Explicit "do not invent addresses" for facility images
- "NEVER claim business location" for testimonial images
- "Describe ONLY what is visible" for stock/decorative

### 6. Authoritative context_type Prompt (v20251029.4)

**Problem:** AI ignored manually-set `context_type` values (e.g., testimonial) and generated generic metadata.

**Solution:** Restructured SYSTEM prompt to emphasize `context_type` as "authoritative" and "MANDATORY".

**Prompt Structure Changes:**
```
BUSINESS CONTEXT (use for tone & relevance, never to invent facts):
- business_name: ...
- industry: ...

IMAGE USE CONTEXT (authoritative):
- context_type: {value}  // chosen manually by user; this is the TRUE purpose
- brand_name_visible: {true|false}

YOU MUST RESPECT context_type AND FOLLOW THESE HANDLING RULES:

CRITICAL RULES BY TYPE:
1) brand_logo, team, facility, equipment → ALWAYS permitted to include business_name
2) testimonial → Describe feeling/concept. PROHIBITED: "at {business}", "in our facility"
3) clinical, business → Follow brand_name_visible strictly
4) stock, decorative → NEVER include business_name
5) service-icon → Only include business_name if logo/text visibly present
```

**USER Message Changes:**
```
Authoritative image purpose (MANDATORY):
- context_type: {value}         // exactly as user selected
- brand_name_visible: {value}

Return exactly one JSON object matching the specified schema, nothing else.
```

**Validation Section Added:**
- AI now self-validates before responding
- Must add `brand_name_assumed` if violating rules + lower confidence ≤ 0.70
- Must add `context_mismatch` if output conflicts with context_type semantics

### 7. AI-Search Optimization (v20251029.5)

**Problem:** Traditional SEO focuses on keywords + crawl structure, but generative AI search (Google SGE, Bing Copilot, ChatGPT Browse, Perplexity) focuses on semantic clarity and factual grounding.

**Solution:** Added "AI-SEARCH & SEO OPTIMIZATION" section to SYSTEM prompt instructing AI to write metadata friendly to BOTH classic search AND generative-AI search.

**What "AI-search friendly" means:**
- **Semantic clarity:** Answer implicit questions (who/what/where/why visible in image)
- **Natural language:** Conversational phrasing, not keyword stuffing
- **Real entities:** Business name, city, service category (when context_type allows)
- **Coherence:** Consistent with on-page copy (SGE scores this)
- **Intent:** Convey why image exists (showing service, illustrating recovery, etc.)

**Prompt Instructions:**
```
AI-SEARCH & SEO OPTIMIZATION:
- Write metadata friendly to both classic search engines AND generative-AI search
- Use natural, factual language that answers implicit user questions (who/what/where/why)
- Mention concrete entities (business name, city, service category) when allowed by context_type
- Prefer phrases people would say or ask in conversation
- Avoid keyword lists or unnatural repetition
- Keep descriptions coherent with surrounding page topic; helps AI ranking models connect image to intent cluster
```

**Example Given in Prompt:**
```
❌ Old SEO: "clinic Hamilton physiotherapy chiropractic massage"
✅ AI + SEO: "Main Street Health rehabilitation clinic in Hamilton Ontario providing
            physiotherapy and chiropractic care for first responders."
```

The second reads like a mini answer a conversational engine can quote.

**Benefits:**

| Benefit | Impact |
|---------|--------|
| Appears in AI-search snippets | Increases visibility when SGE cites sources |
| Higher topical authority | Models rank you as relevant expert in service area |
| Human readability | Better UX → engagement → SEO reinforcement |
| Future-proofing | Won't need metadata rewrite when AI-first search fully rolls out |

**How LLMs Use This:**
- Link to knowledge graphs (real entities: businesses, locations, treatments)
- Score coherence across text + image metadata
- Match to user intent clusters
- Provide natural-language answers in conversational search

### 8. Prompt Version Tracking

**Implementation:**
```php
// class-msh-openai-connector.php:25
const PROMPT_VERSION = '20251029.5'; // AI-search optimization (SGE/Copilot/ChatGPT) + conversational phrasing
```

**Logged in Every AI Call:**
```
[MSH OpenAI] Prompt v20251029.5 - context_type: team, brand_name_visible: true
```

**Included in Metadata Response:**
```php
$sanitized['prompt_version'] = self::PROMPT_VERSION;
```

**Version Format:** `YYYYMMDD.revision`
- Easy to track which prompt generated each result
- Increment revision for same-day changes
- Increment date for new-day changes

### 9. Token Usage Tracking

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

### 10. Batch Telemetry Logging

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

**Next Steps:** See [TELEMETRY-INTEGRATION.md](../../../architecture/TELEMETRY-INTEGRATION.md) for Supabase integration guide.
