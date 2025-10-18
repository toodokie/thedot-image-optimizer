# Question for Other AI: Bulk Metadata Regeneration Strategy

## Context

We've built AI-powered metadata generation (OpenAI Vision API) that works perfectly for **new uploads**. However, we have a critical UX gap:

**The Problem:**
- Plugin only generates metadata on new uploads (via `add_attachment` hook)
- Users have **existing image libraries** (hundreds/thousands of images)
- No way to apply AI metadata to existing images without re-uploading
- This is a showstopper for real-world adoption

## Current State (v1.2.0)

**What works:**
- ✅ AI metadata generation for new uploads
- ✅ OpenAI Vision API integration
- ✅ BYOK + bundled credit support
- ✅ AI gate logic (plan tiers, feature flags)
- ✅ Heuristic fallback when AI unavailable

**What's missing:**
- ❌ Bulk regenerate metadata for existing images
- ❌ Single image "regenerate" button
- ❌ WP-CLI command for bulk operations
- ❌ Progress tracking for large libraries

## Question

**How should we implement bulk metadata regeneration?**

### Option 1: Media Library Bulk Action
```
Media Library → Select images → Bulk Actions → "Regenerate Metadata with AI"
```

**Pros:**
- Familiar WordPress UI pattern
- Users can select specific images
- Built-in to WordPress admin

**Cons:**
- Requires admin AJAX endpoint
- UI blocks during processing (bad UX for large batches)
- No progress indicator
- Timeouts on large selections

### Option 2: Background Processing with Progress UI
```
Settings Page → "Regenerate All Images" button
→ Spawns background job
→ Shows progress bar (X/Y images processed)
→ Can cancel/pause
```

**Pros:**
- Handles large libraries gracefully
- Doesn't block UI
- Progress tracking
- Can resume if interrupted

**Cons:**
- More complex to build
- Requires background job system (WP Cron or similar)
- More testing needed

### Option 3: WP-CLI First, UI Later
```bash
wp msh regenerate-metadata --all
wp msh regenerate-metadata --ids=123,456,789
wp msh regenerate-metadata --batch=100 --offset=0
```

**Pros:**
- Fast to implement (we already have CLI scaffolding)
- Power users can use immediately
- Works for hosting providers with SSH access
- Easy to script/automate

**Cons:**
- Not accessible to all users (non-technical)
- Doesn't help WordPress.com or managed hosting users
- Still need UI eventually

### Option 4: Hybrid Approach (Phased)

**Phase 1 (MVP - 1-2 hours):**
- WP-CLI command for bulk regenerate
- Single image "Regenerate" button in media edit screen

**Phase 2 (Next version):**
- Media Library bulk action
- Background processing with progress UI

**Phase 3 (Future):**
- Scheduled auto-regeneration
- Smart detection (only regenerate if AI would improve quality)

## Technical Considerations

### Credit Tracking
- If user has 500 images and 100 credits, what happens?
  - Process first 100, stop with message?
  - Let user choose which images to prioritize?
  - Allow partial regeneration?

### BYOK vs Bundled
- BYOK: Unlimited regeneration (user pays OpenAI directly)
- Bundled: Must respect credit limits
- Should we have different UX for each mode?

### Existing Metadata
- Overwrite all existing metadata?
- Only fill in empty fields?
- Prompt user to choose strategy?
- Have "force regenerate" flag?

### Performance
- 1000 images × 2-3 sec per API call = 30-50 minutes
- Need batching, queuing, rate limiting
- How to handle API failures/retries?

### Safety
- Should regeneration be reversible?
- Store old metadata as backup before overwrite?
- Undo feature?

## Specific Questions

1. **Which option (1-4) would you recommend for v1.2.0?**

2. **How should we handle credit limits during bulk regeneration?**
   - Stop when credits exhausted?
   - Let user pre-select X images to process?
   - Show credit cost estimate before starting?

3. **What metadata overwrite strategy makes sense?**
   - Always overwrite
   - Only if empty
   - User chooses per-field
   - Smart detection (only if improvement expected)

4. **Should we implement "undo" functionality?**
   - Store old metadata before regenerating
   - Allow rollback if user doesn't like AI results
   - How long to keep backup metadata?

5. **Background job infrastructure:**
   - Use WP Cron (unreliable)
   - Use Action Scheduler (plugin dependency)
   - Build custom background processor
   - Use existing `MSH_Usage_Index_Background` pattern we already have

6. **Priority/urgency:**
   - Is this a blocker for testing/launching?
   - Can we ship v1.2.0 without it and add in v1.3.0?
   - Or is it critical for user adoption?

## Our Current Thinking

We're leaning toward **Option 4 (Hybrid)**:
- Ship v1.2.0 with WP-CLI bulk command (quick win for power users)
- Add UI bulk action in v1.3.0 when we have time to build it properly

But we want your architectural input on:
- Best approach for handling large image libraries
- Credit management during bulk operations
- Whether to build background processing now or defer to v1.3.0

## Files for Reference

- Current CLI: `msh-image-optimizer/includes/class-msh-cli.php`
- AI Service: `msh-image-optimizer/includes/class-msh-ai-service.php`
- OpenAI Connector: `msh-image-optimizer/includes/class-msh-openai-connector.php`
- Background Jobs: `msh-image-optimizer/includes/class-msh-usage-index-background.php`

---

**What's your recommendation?**
