# Non-AI Flow Test - November 4, 2025

**Test Date**: November 4, 2025
**Plugin Version**: v1.3.0-0B (Phase 0B - Smart Mode)
**AI Mode**: `manual` (confirmed via wp-cli)
**Baseline Comparison**: [BASELINE-NON-AI-METADATA.md](../archive/2025-10-october/test-reports/BASELINE-NON-AI-METADATA.md) (Oct 28, 2025, v1.2.7.1)

---

## Test Objective

Verify that non-AI (contextual generator) metadata quality after:
1. Reset + Analyze workflow
2. Compare to October 28 baseline to identify improvements/regressions

---

## Business Context (Loaded)

```
Business Name: Main Street Health
Industry: medical
Location: Hamilton, Ontario, Canada
Target Audience: First responders (paramedics, police, firefighters)
UVP: Specialized first responder program, rapid physician referral, multi-disciplinary approach
Service Area: Greater Hamilton Area including Ancaster, Dundas, Stoney Creek, Burlington, Grimsby
Brand Voice: professional
```

---

## Test Images (Same as Baseline)

| ID | Filename | Type | Oct 28 Baseline Issue |
|----|----------|------|----------------------|
| 616 | lettuce-field-sunrise.jpg | Generic landscape | ❌ "Medical Treatment" (inappropriate) |
| 617 | serene-forest-landscape.jpg | Generic landscape | ❌ "Main Street Health Clinic" (forced business context) |
| 754 | red-pier-structure.jpg | Generic structure | ❌ Used filename as testimonial name |
| 755 | golden-gate-bridge.jpg | Generic landmark | ❌ "Medical Treatment" (completely wrong) |
| 756 | sunlight-trees-river.jpg | Generic nature | ❌ "Medical Treatment" (generic template) |
| 757 | wooden-pier-water.jpg | Generic pier | ❌ "Medical Treatment" (generic template) |
| 758 | marina-boats-cloudy.jpg | Generic marina | ❌ "Medical Treatment" (generic template) |
| 760 | sydney-harbour-bridge.jpg | Generic landmark | ❌ "Medical Treatment" (generic template) |
| 761 | sunset-wind-turbines.jpg | Generic energy | ❌ "Medical Treatment" (generic template) |

---

## October 28 Baseline Issues (What Was Wrong)

### Issue #1: Overly Generic Templates
- **Pattern**: 7 out of 9 images got identical metadata
- **Title**: "Medical Treatment - Main Street Health Hamilton, Ontario,"
- **Description**: "Comprehensive medical care tailored to patient recovery. Patient-focused medical care. Comprehensive treatment plans. Insurance accepted."
- **Problem**: No image-specific context, completely generic

### Issue #2: Forced Business Context
- **Example**: Image 617 (forest landscape) → "Interior view of Main Street Health rehabilitation clinic"
- **Problem**: Forcing business context into completely unrelated stock images

### Issue #3: Filename-as-Content
- **Example**: Image 754 → "Muelle Con Estructura Roja En Barcelona Agencia De Viajes shares medical recovery experience"
- **Problem**: Using filename as if it's a person's name in testimonial

### Issue #4: Zero Image Analysis
- **Problem**: No descriptor-based logic, no context type detection
- **Result**: Every image treated as "Medical Treatment" by default

---

## Expected Improvements (November 4, 2025)

Since October 28, several improvements have been made:

### ✅ Context Type Detection
- Smart Mode improvements (Phase 0B)
- Better descriptor-based classification
- Should detect: generic/stock vs business-related images

### ✅ Conditional Business Context
- Business name/location should NOT appear in generic stock images
- Should only appear in business-related images (facility, team, clinical)

### ✅ Descriptor-Based Metadata
- Should use filename descriptors for generic images
- Better subject detection from filenames

### ✅ Context Signature System
- Better tracking of what context was used
- Should show in `_msh_context_trace` metadata

---

## Test Procedure

### Step 1: Reset (Clear All Data)
```bash
# Navigate to Image Optimizer admin page
# Click "Clear All Data & Refresh" button
# Verify: All msh_optimized_date cleared
```

### Step 2: Analyze (Generate Non-AI Metadata)
```bash
# Click "Analyze" button
# Wait for completion
# Expected: Contextual generator produces metadata (NO AI calls)
```

### Step 3: Capture Results
For each test image (616, 617, 754, 755, 756, 757, 758, 760, 761):
```bash
wp post meta get <ID> _wp_attachment_image_alt
wp post meta get <ID> msh_descriptor
wp post meta get <ID> _msh_context_trace
```

### Step 4: Compare to Baseline
- Check if metadata is image-specific (not generic template)
- Check if business context is appropriately excluded for stock images
- Check if descriptors are being used intelligently

---

## Comparison Checklist

### Generic Stock Images (Should NOT Have Business Context)
- [ ] Image 616 (lettuce-field-sunrise.jpg) - Generic landscape
- [ ] Image 755 (golden-gate-bridge.jpg) - Famous landmark
- [ ] Image 756 (sunlight-trees-river.jpg) - Generic nature
- [ ] Image 757 (wooden-pier-water.jpg) - Generic pier
- [ ] Image 758 (marina-boats-cloudy.jpg) - Generic marina
- [ ] Image 760 (sydney-harbour-bridge.jpg) - Famous landmark
- [ ] Image 761 (sunset-wind-turbines.jpg) - Generic energy

**Expected:** Descriptive metadata WITHOUT "Main Street Health" or "Hamilton"

### Ambiguous Images (Contextual Decision)
- [ ] Image 617 (serene-forest-landscape.jpg) - Could be generic OR wellness-related
- [ ] Image 754 (red-pier-structure.jpg) - Generic structure

**Expected:** Intelligent context detection, appropriate business context only if relevant

---

## Key Metrics to Track

| Metric | Oct 28 Baseline | Nov 4 Test | Status |
|--------|-----------------|------------|--------|
| **Generic template repetition** | 7/9 images identical | ? | ? |
| **Inappropriate business context** | 9/9 images had forced context | ? | ? |
| **Image-specific metadata** | 0/9 images | ? | ? |
| **Descriptor usage** | Not used | ? | ? |
| **Context type detection** | All treated as "medical treatment" | ? | ? |

---

## Success Criteria

✅ **PASS** if:
1. Generic stock images do NOT force "Main Street Health" into metadata
2. Each image has unique, image-specific metadata (not template repetition)
3. Descriptors are used to generate appropriate metadata
4. Context type detection prevents inappropriate business context

❌ **FAIL** if:
1. Still using generic "Medical Treatment" template for all images
2. Still forcing business context into unrelated stock images
3. Still treating filename as person/entity name

---

## Results Section (To Be Filled)

### Image 616: lettuce-field-sunrise.jpg
- **Title**: [TO BE CAPTURED]
- **ALT**: [TO BE CAPTURED]
- **Caption**: [TO BE CAPTURED]
- **Description**: [TO BE CAPTURED]
- **Descriptor**: [TO BE CAPTURED]
- **Context Trace**: [TO BE CAPTURED]
- **Comparison**: [vs baseline]

### Image 617: serene-forest-landscape.jpg
[TO BE FILLED]

### Image 754: red-pier-structure.jpg
[TO BE FILLED]

### Image 755: golden-gate-bridge.jpg
[TO BE FILLED]

### Image 756: sunlight-trees-river.jpg
[TO BE FILLED]

### Image 757: wooden-pier-water.jpg
[TO BE FILLED]

### Image 758: marina-boats-cloudy.jpg
[TO BE FILLED]

### Image 760: sydney-harbour-bridge.jpg
[TO BE FILLED]

### Image 761: sunset-wind-turbines.jpg
[TO BE FILLED]

---

## Analysis (To Be Completed After Test)

### What Improved Since October 28?
[TO BE FILLED]

### What Regressed?
[TO BE FILLED]

### What's Still Broken?
[TO BE FILLED]

### Recommendations
[TO BE FILLED]

---

**Test Status**: 🟡 Ready to Execute
**Next Step**: Run Reset + Analyze, then capture results
