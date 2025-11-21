# Mode Switching Test - November 5, 2025

**Test Date**: November 5, 2025 (10:47 AM+ local time)
**Plugin Version**: v1.3.0-0C
**Test Objective**: Verify no metadata persistence when switching between AI and non-AI modes

---

## Background

**Root Issue Identified** (from earlier session):
> "you fix it, we test - it works, then we fix and test ai flow, then we plan a final check of non-ai and test it and it won't work. as if switching is the breaking it - look into that at the first place. and on a loop."

**Hypothesis**: Mode switching was causing metadata persistence bugs because Reset was NOT clearing descriptors or WordPress metadata fields.

**Fixes Applied**:
1. ✅ Reset function now clears `msh_descriptor`, `_msh_context_trace`, `msh_last_analyzed`
2. ✅ Reset function now clears WordPress fields (`post_title`, `post_excerpt`, `post_content`, `_wp_attachment_image_alt`)

---

## Test Procedure

### Phase 1: Non-AI Baseline (COMPLETED ✅)
**Current State** (as of 15:47:02 UTC):
- Mode: `msh_ai_mode = manual`
- Images analyzed: 36 total
- Results: 35/36 working correctly (97.2% success)
- Known issue: 1 image (ID 762 - farm equipment) misclassified by context detector

### Phase 2: Switch to AI Mode (TO BE DONE)

#### Step 1: Change to AI Mode
```bash
wp option update msh_ai_mode 'smart' --path="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
```
Expected: `msh_ai_mode` changes from `manual` → `smart`

#### Step 2: Run Reset
- Navigate to Image Optimizer admin page
- Click "Reset" button
- **Expected logs**:
  ```
  [MSH DEBUG] Reset count: [number]
  [MSH DEBUG] AI reset count: [number]
  [MSH DEBUG] ALT reset count: 37
  [MSH DEBUG] Posts reset count: 37
  [MSH DEBUG] Total reset: [number]
  ```

#### Step 3: Run Analyze (AI Mode)
- Click "Analyze" button
- **Expected logs**:
  ```
  [AI_RESP] #[ID] ok=1 tokens=[sys]/[user]/[total]
  [MSH SmartMode] ctx:[hash] | prompt=...|ct:[type]|cm:[0|1]|bm:[0|1]...
  ```
- **Expected**: AI generates fresh metadata with Smart Mode tokens (~439 avg)

#### Step 4: Capture AI-Generated Metadata (Sample Images)
Capture metadata for test images (755, 756, 757, 617, 754, 762):
```bash
# For each ID:
wp post get [ID] --field=post_title
wp post get [ID] --field=post_excerpt
wp post get [ID] --field=post_content
wp post meta get [ID] _wp_attachment_image_alt
wp post meta get [ID] msh_descriptor
```

### Phase 3: Switch Back to Non-AI Mode (TO BE DONE)

#### Step 5: Change to Non-AI Mode
```bash
wp option update msh_ai_mode 'manual'
```
Expected: `msh_ai_mode` changes from `smart` → `manual`

#### Step 6: Run Reset Again
- Click "Reset" button
- **Critical Check**: Verify ALL metadata cleared (not just plugin flags)
- **Expected logs**: Same as Step 2

#### Step 7: Run Analyze (Non-AI Mode)
- Click "Analyze" button
- **Expected logs**:
  ```
  [MSH Scene] Extracted from "golden-gate-bridge-hamilton.jpg": proper_names=Golden Gate Bridge
  [MSH Composer] compose() for ID=[number]
  ```
- **Expected**: Non-AI composer generates fresh metadata

#### Step 8: Capture Non-AI Metadata (Sample Images)
Capture metadata for same test images (755, 756, 757, 617, 754, 762)

#### Step 9: Compare Metadata
**Critical Verification**:
- ✅ Non-AI metadata should be DIFFERENT from AI metadata
- ✅ No AI-specific phrases should persist in non-AI metadata
- ✅ Each mode should generate completely fresh metadata
- ❌ If metadata is identical → Reset function NOT clearing WordPress fields properly

---

## Success Criteria

### ✅ PASS if:
1. Reset clears ALL metadata (plugin flags + WordPress fields) in both modes
2. AI mode generates AI-specific metadata with Smart Mode tokens
3. Non-AI mode generates composer-specific metadata with scene extraction
4. NO metadata persistence across mode switches
5. Each mode produces distinctly different metadata for the same images

### ❌ FAIL if:
1. Metadata remains identical after mode switch + Reset + Analyze
2. AI phrases appear in non-AI metadata (or vice versa)
3. Reset doesn't clear WordPress metadata fields
4. Descriptors persist across mode switches

---

## Test Images for Detailed Comparison

| ID | Filename | Type | Why Testing |
|----|----------|------|-------------|
| 755 | golden-gate-bridge-hamilton.jpg | Stock landmark | Baseline stock image |
| 756 | sunlight-trees-river-hamilton.jpg | Stock nature | Baseline stock image |
| 757 | test-wooden-pier-hamilton.jpg | Stock pier | Baseline stock image |
| 617 | TEST-main-street-health-facility-4040...webp | Facility | Business context image |
| 754 | patient-testimonial-waterfront-structure.webp | Testimonial | Testimonial context |
| 762 | vintage-farm-equipment-hamilton.jpg | Stock (misclassified) | Known context detection issue |

---

## Expected Metadata Differences

### Example: ID 755 (Golden Gate Bridge)

**AI Mode (Smart)** - Expected:
- Title: "Golden Gate Bridge at [time of day] in San Francisco"
- Description: AI-generated descriptive paragraph with natural language
- ALT: "Golden Gate Bridge spanning across the bay with [details]"
- Tokens: ~327 sys / ~100-150 user / ~427-477 total

**Non-AI Mode (Composer)** - Expected:
- Title: "Golden Gate Bridge"
- Description: Template-based with descriptor: "Golden Gate Bridge captured with a steady atmosphere..."
- ALT: "Golden Gate Bridge captured in natural setting" OR similar template
- Scene: `proper_names=Golden Gate Bridge`

**Key Difference**: AI should be more descriptive and natural, Non-AI should be more template-based with scene descriptors.

---

## Results Section (To Be Filled)

### Phase 2 Results: AI Mode

#### Step 1: Mode Change
- ⏳ Timestamp: [TO BE CAPTURED]
- ⏳ Command output: [TO BE CAPTURED]
- ⏳ Verification: `msh_ai_mode = smart` [TO BE VERIFIED]

#### Step 2: Reset in AI Mode
- ⏳ Timestamp: [TO BE CAPTURED]
- ⏳ Log output: [TO BE CAPTURED]
- ⏳ Reset counts: [TO BE CAPTURED]

#### Step 3: Analyze in AI Mode
- ⏳ Timestamp: [TO BE CAPTURED]
- ⏳ Image count: [TO BE CAPTURED]
- ⏳ Avg tokens: [TO BE CAPTURED]

#### Step 4: AI Metadata Samples
**ID 755 (Golden Gate):**
- Title: [TO BE CAPTURED]
- ALT: [TO BE CAPTURED]
- Caption: [TO BE CAPTURED]
- Description: [TO BE CAPTURED]
- Descriptor: [TO BE CAPTURED]

**ID 756 (Sunlight Trees River):**
- [TO BE CAPTURED]

**ID 757 (Wooden Pier):**
- [TO BE CAPTURED]

**ID 617 (Facility):**
- [TO BE CAPTURED]

**ID 754 (Testimonial):**
- [TO BE CAPTURED]

**ID 762 (Farm Equipment):**
- [TO BE CAPTURED]

### Phase 3 Results: Back to Non-AI Mode

#### Step 5: Mode Change Back
- ⏳ Timestamp: [TO BE CAPTURED]
- ⏳ Verification: `msh_ai_mode = manual` [TO BE VERIFIED]

#### Step 6: Reset in Non-AI Mode
- ⏳ Timestamp: [TO BE CAPTURED]
- ⏳ Reset counts: [TO BE CAPTURED]

#### Step 7: Analyze in Non-AI Mode
- ⏳ Timestamp: [TO BE CAPTURED]
- ⏳ Image count: [TO BE CAPTURED]
- ⏳ Composer logs: [TO BE CAPTURED]

#### Step 8: Non-AI Metadata Samples
**ID 755 (Golden Gate):**
- Title: [TO BE CAPTURED]
- ALT: [TO BE CAPTURED]
- Caption: [TO BE CAPTURED]
- Description: [TO BE CAPTURED]
- Descriptor: [TO BE CAPTURED]

**ID 756 (Sunlight Trees River):**
- [TO BE CAPTURED]

**ID 757 (Wooden Pier):**
- [TO BE CAPTURED]

**ID 617 (Facility):**
- [TO BE CAPTURED]

**ID 754 (Testimonial):**
- [TO BE CAPTURED]

**ID 762 (Farm Equipment):**
- [TO BE CAPTURED]

### Phase 4: Comparison Analysis

#### Metadata Comparison Matrix
| ID | Metadata Field | AI Mode | Non-AI Mode | Different? |
|----|----------------|---------|-------------|------------|
| 755 | Title | [TBD] | [TBD] | [TBD] |
| 755 | ALT | [TBD] | [TBD] | [TBD] |
| 755 | Description | [TBD] | [TBD] | [TBD] |
| 756 | Title | [TBD] | [TBD] | [TBD] |
| 756 | ALT | [TBD] | [TBD] | [TBD] |
| ... | ... | ... | ... | ... |

#### Key Findings
- [ ] Metadata is distinctly different between AI and non-AI modes
- [ ] No AI phrases persist in non-AI metadata
- [ ] No non-AI templates persist in AI metadata
- [ ] Reset function properly cleared all data between mode switches
- [ ] Both modes generate appropriate metadata for their respective approaches

---

## Conclusion (To Be Completed)

### Test Status: 🟡 Phase 1 Complete, Phases 2-4 Pending

**Phase 1 (Non-AI Baseline)**: ✅ COMPLETE
- 35/36 images working correctly
- Upgraded composer confirmed working
- Reset function verified clearing all metadata

**Phase 2 (AI Mode Test)**: ⏳ PENDING
**Phase 3 (Switch Back to Non-AI)**: ⏳ PENDING
**Phase 4 (Comparison Analysis)**: ⏳ PENDING

### Next Steps
1. User to switch to AI mode: `wp option update msh_ai_mode 'smart'`
2. User to run Reset + Analyze in AI mode
3. Capture AI-generated metadata samples
4. User to switch back to non-AI mode
5. User to run Reset + Analyze in non-AI mode
6. Compare metadata to verify no persistence

---

**Test Created**: November 5, 2025
**Status**: Phase 1 Complete, awaiting user to initiate Phase 2
**Critical Fix Applied**: Reset now clears ALL metadata (plugin flags + WordPress fields + descriptors)
