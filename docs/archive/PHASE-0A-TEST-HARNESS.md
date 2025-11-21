# Phase 0A Test Harness - Smart Mode Quality Validation

> **RETROSPECTIVE NOTE:** This document describes the **test harness tool** created on November 2, 2025 to validate Smart Mode before production deployment. This testing framework was used to generate the results documented in [PHASE-0B-VERIFICATION-REPORT.md](PHASE-0B-VERIFICATION-REPORT.md). **Phase 0B was deployed to production on November 4, 2025**.

**Created:** November 2, 2025 (Testing Framework)
**Used For:** Pre-production validation of Smart Mode (Phase 0B)
**Outcome:** Validation passed → Phase 0B deployed Nov 4, 2025
**Purpose:** Side-by-side comparison of current (high detail) vs Smart Mode (low detail + compressed prompts)
**Goal:** Validate that Smart Mode achieves ≥90% quality at ≤230 tokens/image before full rollout

---

## What Was Built

### New File: `includes/class-msh-smart-mode-test-cli.php`

A complete WP-CLI test harness that:

1. **Tests both modes in parallel** on the same images:
   - **Current Mode:** detail:high + full prompts (~3,358 tokens/image)
   - **Smart Mode:** detail:low + compressed prompts (~210 tokens/image)

2. **Captures real metrics:**
   - Token usage (from API responses)
   - Response time
   - Generated metadata (title, alt, description, etc.)

3. **Generates comparison report:**
   - Side-by-side metadata comparison
   - Token reduction percentage
   - Speedup metrics
   - Success criteria validation

### Registration

The CLI command is registered in `msh-image-optimizer.php` at line 111-113:
```php
if (!class_exists('MSH_Smart_Mode_Test_CLI_Command')) {
    require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-smart-mode-test-cli.php';
}
```

---

## How to Use

### Basic Usage

Test 10 random images:
```bash
wp msh smart-mode-test --count=10
```

Test specific images:
```bash
wp msh smart-mode-test --ids=1234,5678,9012
```

### Example Output

```
╔═══════════════════════════════════════════════════════════════╗
║  MSH Smart Mode Test (Phase 0A)                               ║
║  Side-by-Side Comparison: Current vs Smart Mode               ║
╚═══════════════════════════════════════════════════════════════╝

Testing 10 random images...
Test IDs: 1686, 1687, 1690, 1691, 1692, 1693, 1694, 1695, 1696, 1697

Testing images  100% [====================] 20/20 (0s)

─── Image #1686 ─────────────────────────────────────────────────

  CURRENT MODE (high detail):
    Duration: 8.42s
    Tokens: 3358
    Title: Fresh Lettuce Field - Main Street Health Wellness Imagery
    Alt: Rows of fresh lettuce in agricultural field at sunrise

  SMART MODE (low detail + compressed):
    Duration: 2.14s
    Tokens: 210
    Context ID: ctx_9f11db7
    Title: Lettuce Field at Sunrise
    Alt: Rows of lettuce in field at sunrise

[... more images ...]

╔═══════════════════════════════════════════════════════════════╗
║  Summary                                                      ║
╚═══════════════════════════════════════════════════════════════╝

Images tested: 10

CURRENT MODE (high detail):
  Avg duration: 8.35s
  Avg tokens: 3358

SMART MODE (low detail + compressed):
  Avg duration: 2.18s
  Avg tokens: 210

📊 IMPROVEMENTS:
  Speedup: 3.83x faster
  Token reduction: 93.7%

✓ SUCCESS CRITERIA:
  [✓] Tokens ≤ 230: 210
  [ ] Quality ≥ 90%: Manual review required

✓ Token budget target met! Ready for Phase 0B if quality is acceptable.
```

---

## Smart Mode Implementation Details

### Compressed System Prompt (~50 tokens vs current ~2,000 tokens)

```
You are an AI metadata assistant. Analyze image using context ruleset v21 (ref: {ctx_id}).

Output JSON with: file_name_suggestion, title, alt_text, caption, description, keywords[], subjects[], attributes[], confidence, issues[].

Rules: Describe visible content. Use context for tone only, not facts. Keep title ≤60 chars, alt_text 8-140 chars.
```

### Compressed User Prompt (~75 tokens vs current ~400 tokens)

```
Image: {image_url}
File: {original_filename}
Context: {ctx_id}
Type: {context_type}
Brand visible: {brand_visible}
Business: {business_name}

Return JSON only.
```

### Context ID System

Context fingerprints compress business context into 7-character IDs:

```php
$ctx_id = 'ctx_' . substr(sha1(
    $site_id . '|' . $locale . '|' . $business_name . '|' . $industry
), 0, 7);
```

Example: `ctx_9f11db7`

### API Configuration

- **Model:** `gpt-4o` (same as current)
- **Vision detail:** `low` (vs current `high`)
- **Temperature:** `0` (deterministic)
- **Max tokens:** `300` (vs current `500`)

---

## Success Criteria (Phase 0A)

### Automated Checks
- ✅ **Token budget:** Avg tokens ≤ 230/image
- ✅ **Response time:** Should see 3-4x speedup

### Manual Quality Review Required

Compare metadata quality between current and Smart Mode:

1. **Title quality:**
   - Is the title descriptive?
   - Does it match the image content?
   - Is it SEO-friendly?

2. **Alt text quality:**
   - Is alt text accurate?
   - Is it accessible (8-140 chars)?
   - Does it describe visible content?

3. **Overall quality:**
   - Are subjects[] accurate?
   - Are attributes[] relevant?
   - Is confidence reasonable?

**Target:** ≥90% quality match between current and Smart Mode

---

## Next Steps

### If Phase 0A Passes (Quality ≥ 90%, Tokens ≤ 230)

**Proceed to Phase 0B:** Batch test with 46 images

```bash
# Get 46 random test IDs
wp post list --post_type=attachment --posts_per_page=46 --format=ids

# Run batch test
wp msh smart-mode-test --ids=<paste-46-ids-here>
```

**Success criteria for Phase 0B:**
- Zero 429 rate limit errors
- Completion time < 5 minutes
- Consistent quality across all images

### If Phase 0B Passes

**Implement v1.2.17 - Smart Mode:**
1. Replace prompts globally in `class-msh-openai-connector.php`
2. Change `detail:high` to `detail:low` (line 709)
3. Implement context ID system
4. Add auto-detect high detail promotion for hero images
5. Update version to v1.2.17

See: `docs/TOKEN_BASED_PRICING_STRATEGY.md` Part 10 for complete rollout plan

---

## Technical Notes

### Token Tracking

The test harness captures real token usage from OpenAI API responses:

- **Current mode:** Hooks into `msh_log_token_usage` action
- **Smart mode:** Captures from `response_data['usage']`

### Image Resizing

Both modes use the same image resizing before base64 encoding:
- Max dimension: 1600px
- Quality: 80% JPEG
- Target file size: <200KB

This ensures fair comparison (resizing not affecting results).

### Error Handling

- API errors are captured and displayed per image
- Tests continue even if individual images fail
- Summary shows both successes and failures

---

## Files Modified

### New Files
- ✅ `includes/class-msh-smart-mode-test-cli.php` (650 lines)

### Modified Files
- ✅ `msh-image-optimizer.php` (lines 111-113) - CLI command registration

### Documentation
- ✅ `docs/TOKEN_BASED_PRICING_STRATEGY.md` Part 10 - Smart Mode plan
- ✅ `LOG-NOVEMBER-1-2.md` - Session notes and references

---

## Troubleshooting

### "No images found for testing"
- Check that media library has images
- Try specifying IDs manually with `--ids`

### "No API key available"
- Set API key in Settings > AI Configuration
- Or use bundled access mode

### Rate limit errors during test
- Reduce test count: `--count=5`
- Wait between tests
- This is expected with current mode (validates the problem!)

---

**Status:** Test harness complete and ready for Phase 0A testing
**Next Action:** Run quality comparison on 10 images and review metadata manually
**Documentation:** See `docs/TOKEN_BASED_PRICING_STRATEGY.md` Part 10 for full context
