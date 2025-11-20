# 📘 Non-AI & AI Metadata Rulebook (Source of Truth)

**Version:** 1.0
**Last Updated:** November 20, 2025
**Status:** Canonical Reference

This document defines the complete rulebook for metadata generation across both AI and Non-AI (deterministic) systems in MSH Image Optimizer.

---

## I. Principles

1. **Accuracy > SEO** for non-branded or third-party images.
2. **Brand-owned assets are marketing**: TEAM, FACILITY, EQUIPMENT, SERVICE-ICON, TESTIMONIAL(manual) represent the brand.
3. **SEO tail never dominates**: Only in description, never in title/alt/fn by default.
4. **Consistency across AI and Non-AI**: Same flags (ct, cm, bm, seo, bv, bl, sv) drive both.

---

## II. Shared Inputs

For each attachment:

| Flag | Name | Description | Values |
|------|------|-------------|--------|
| `ct` | context_type | Image category | stock, decorative, team, facility, equipment, clinical, business, testimonial, service-icon |
| `cm` | context_set_manually | User chose category | 0 or 1 |
| `bm` | brand_name_visible | Brand is allowed | true/false |
| `seo` | seo_mode | SEO optimization enabled | true/false |
| `bn` | business_name | Business name | string |
| `bl` | location | Location (city, region) | string |
| `sv` | service_keywords | Service keywords | array of strings |
| `bv` | brand_voice | Brand tone | professional, friendly, casual, technical |

**Page Context:**
- `page_title`: Current page/post title
- `focus_keyword`: SEO focus keyword
- `page_role`: Page type (homepage, service, blog, etc.)

---

## III. Non-AI (Deterministic Composer)

### Scene Extraction
From filename & context, detect nouns/time: bridge, river, sunrise, clinic, interior, etc.

### Phrasebank
Small JSON: time_of_day, verbs, mood, light, etc.

---

### Per-Context Logic

#### 1. STOCK / DECORATIVE

**Brand-owned:** No
**Branding allowed:** Never

**Metadata Rules:**
- **Title:** Scenic only
- **Alt:** Literal description
- **Caption:** Scenic/mood
- **Description:**
  - **SEO OFF:** Scenic only, no bn/bl/sv/CTA
  - **SEO ON:** Scenic + one tail sentence (location + service)
- **Filename:** Derived from scene, no brand/location insertion

**Example (SEO OFF):**
```
Title: Serene Forest Landscape
Alt: Dense forest with tall trees and green grass
Caption: A peaceful forest scene with lush greenery
Description: A tranquil forest setting featuring tall trees and vibrant green grass.
```

**Example (SEO ON):**
```
Title: Serene Forest Landscape
Alt: Dense forest with tall trees and green grass
Caption: A peaceful forest scene with lush greenery
Description: A tranquil forest setting featuring tall trees and vibrant green grass. Main Street Health in Hamilton, Ontario offers medical support.
```

---

#### 2. TEAM

**Brand-owned:** Yes
**Branding allowed:** Always (when bm=1)
**Conceptual context:** Image may show generic scene, but represents team/staff

**Metadata Rules:**
- **Title:** TEAM-specific template (not scenic)
  - Templates: "Healthcare Team – {bn}", "Team at {bn}", "Clinical Staff – {bn}", "Professional Team – {bn}"
- **Description:**
  - Sentence 1: Scenic description
  - Sentence 2: TEAM tie-in connecting scene to team/brand
- **SEO ON/OFF (Phase 1):** Identical behavior; no location/service tail for TEAM yet
- **Filename:** Generic team slug

**Example:**
```
Title: Healthcare Team – Main Street Health
Alt: Professional team in a supportive environment
Caption: The dedicated team at Main Street Health
Description: A tranquil forest setting featuring tall trees and vibrant green grass, creating a peaceful natural atmosphere. This image reflects the supportive team at Main Street Health.
```

**TEAM Tie-in Templates:**
- Professional: "This image reflects the supportive team at {bn}", "It represents the professional environment of {bn}", "This captures the collaborative atmosphere at {bn}"
- Friendly/Casual: "It reflects the caring team at {bn}", "This image represents the supportive environment of {bn}", "It captures the welcoming atmosphere at {bn}"

---

#### 3. FACILITY

**Brand-owned:** Yes
**Branding allowed:** Always (when bm=1)

**Metadata Rules:**
- **Title:** May include bn + clinic type + city
- **Description:** Scenic facility sentence + brand line
- **SEO ON:** Facility-tail allowed later (Phase 2+)
- **SEO OFF:** No location/service/CTA

**Example (SEO OFF):**
```
Title: Medical Clinic – Main Street Health
Alt: Modern medical clinic interior
Caption: Professional healthcare facility
Description: A modern medical clinic with comfortable waiting area and professional atmosphere. Main Street Health.
```

---

#### 4. EQUIPMENT

**Brand-owned:** Yes
**Branding allowed:** Always (when bm=1)

**Metadata Rules:**
- **Title:** Equipment name + bn
- **Description:** Equipment description + brand mention
- **SEO:** Similar to facility

**Example:**
```
Title: Gait Scan Technology – Main Street Health
Alt: Advanced gait analysis equipment
Caption: State-of-the-art gait scanning technology
Description: Advanced gait scan technology for comprehensive biomechanical assessment. Available at Main Street Health.
```

---

#### 5. CLINICAL / BUSINESS

**Brand-owned:** Conditional
**Branding allowed:** Only if bm=true

**Metadata Rules:**
- **Title:** Service/treatment name; brand optional
- **Description:**
  - Scenic treatment scene
  - Brand line if bm=true
  - **SEO ON:** Location/service tail allowed in description
  - **SEO OFF:** Scenic only

**Example (bm=true, SEO ON):**
```
Title: Physiotherapy Treatment Session
Alt: Patient receiving physiotherapy treatment
Caption: Professional physiotherapy care
Description: A patient receiving specialized physiotherapy treatment in a comfortable clinic setting. Main Street Health in Hamilton, Ontario offers physiotherapy services.
```

**Example (bm=false, SEO OFF):**
```
Title: Physiotherapy Treatment Session
Alt: Patient receiving physiotherapy treatment
Caption: Professional physiotherapy care
Description: A patient receiving specialized physiotherapy treatment in a comfortable clinic setting.
```

---

#### 6. TESTIMONIAL

**Brand-owned:** Conditional
**Branding allowed:**
- **Manual (cm=1):** Brand allowed; conceptual outcome
- **Auto:** Treat like clinical/business with bm gating

**Metadata Rules (Manual):**
- **Title:** "Patient Success Story – {bn}"
- **Description:** Testimonial tone; may mention bn; location only as safe context ("serving {bl}"), not "taken at our clinic"

**Example (Manual, bm=true):**
```
Title: Patient Success Story – Main Street Health
Alt: Successful patient recovery journey
Caption: Real patient success story
Description: A patient's journey to recovery and improved mobility. This success story represents the quality care provided by Main Street Health, serving Hamilton, Ontario.
```

---

#### 7. SERVICE-ICON

**Brand-owned:** Yes
**Branding allowed:** Always (when bm=1)

**Metadata Rules:**
- **Title:** "{Service} Icon – {bn}"
- **Description:** Icon use and brand presence
- **SEO tail:** Optional in description only

**Example:**
```
Title: Physiotherapy Icon – Main Street Health
Alt: Physiotherapy service icon
Caption: Physiotherapy services symbol
Description: Icon representing physiotherapy services. Part of the service offerings at Main Street Health.
```

---

## IV. AI Flow (Smart Mode)

### System Prompt

```
AI metadata assistant. Output schema v4 (JSON only). No commentary.
```

### User Prompt Template

```
ctx:{ctx_id}|ct:{ct}|cm:{cm}|seo:{seo}|bm:{bm}|bn:{bn}|bl:{bl}|sv:{sv}|bv:{bv}
pg:ti={title}|kw={kw}|pr={role}

Schema v4: {fn,t,a,c,d,k[],s[],attr[],conf,iss[]}

rules:
- ct final if cm=1; describe visible scene first.
- BRAND:
  • if ct in [team,facility,equipment,service-icon] AND bm=1 → MUST put {bn} in t AND d.
  • if ct=testimonial AND cm=1 AND bm=1 → MUST put {bn} in t AND d.
  • if ct in [clinical,business] AND bm=1 → MUST put {bn} in d.
  • if ct in [stock,decorative] OR bm=0 → NEVER include {bn}.
- SEO:
  • if seo=0 → NO location/service/CTA anywhere.
  • if seo=1 AND ct in [stock,decorative] → one SEO tail (loc+service) in d only; never in t/a/fn.
  • if seo=1 AND bm=1 AND ct in [facility,clinical,business,testimonial] → one loc/service hint in d (Phase 2+).
- TEAM override:
  • ct=team AND cm=1 AND bm=1 → title and description are TEAM templates, not AI scene-only decisions.
- Lengths: t≤60, a 8–140, d≤500; return JSON only.
```

### Schema v4 (Short Keys)

```json
{
  "fn": "filename_slug",
  "t": "Title (≤60 chars)",
  "a": "Alt text (8-140 chars)",
  "c": "Caption (≤150 chars)",
  "d": "Description (≤500 chars)",
  "k": ["keyword1", "keyword2", "keyword3"],
  "s": ["subject1", "subject2", "subject3"],
  "attr": ["attribution_info"],
  "conf": 0.95,
  "iss": ["issue_flags"]
}
```

---

## V. AI Validators (Server Side)

### Processing Pipeline

After JSON parse from AI response:

1. **Expand short keys** to verbose keys (backward compatible)
2. **Strip brand** for stock/decorative/bm=0
3. **Strip location/service/CTA** when seo=0
4. **Apply TEAM override:**
   - ct=team cm=1 bm=1:
     - Bypass validation errors
     - Enforce TEAM title template
     - Enforce TEAM description tie-in
5. **Apply STOCK tail:**
   - ct=stock/decorative seo=1: scenic + tail, no brand
6. **Enforce length caps**
7. **Uniqueness check**

### Batch 1: TEAM Validator Implementation

**Location:** `includes/class-msh-openai-connector.php`, lines 1830-1854

**Logic:**
```php
if ( $ct === 'team' && $bm && $bn ) {
    // Title: Use organic TEAM template
    if ( stripos( $t, $bn ) === false ) {
        $metadata['title'] = $this->buildTeamTitle( $bn );
    }

    // Description: Add organic tie-in
    if ( stripos( $d, $bn ) === false ) {
        $tie_in = $this->buildTeamTieIn( $bn, $brand_voice );
        $metadata['description'] = rtrim( $d, '. ' ) . '. ' . $tie_in;
    }
}
```

**Title Templates:**
- "Healthcare Team – {bn}"
- "Team at {bn}"
- "Clinical Staff – {bn}"
- "Professional Team – {bn}"

**Description Tie-ins:**
- Professional: "This image reflects the supportive team at {bn}."
- Friendly: "It reflects the caring team at {bn}."

### SEO Mode Protection (Batch 1.a)

**Location:** `includes/class-msh-openai-connector.php`, lines 417-425

When `seo_mode=false`, business_name is normally added to disallowed_terms and stripped.

**Exception:** For TEAM context with bm=1, preserve brand:

```php
$preserve_brand = ( $ct === 'team' && $bm );

if ( $business_name && ! $preserve_brand ) {
    $disallowed_terms[] = $business_name;
}
```

---

## VI. Operations

### Reset
Clears optimizer state + staged data; preserves live WP fields.

### Analyze
Runs AI/non-AI to generate suggestions in-memory (and/or staging JSON).
**Does NOT write to WP post fields.**

### Optimize
Writes accepted suggestions to WP fields and optionally renames files.

---

## VII. Implementation Status

### ✅ Completed (Phase 0D)

- **Batch 1.a:** TEAM brand enforcement
  - Brand preserved in title + description when ct=team AND bm=1
  - Works correctly for both seo=0 and seo=1
  - Protection against seo=0 stripping

- **Batch 1.b:** Organic TEAM language
  - Title templates instead of mechanical prepend
  - Organic description tie-ins connecting scene to team/brand
  - Brand voice variation (professional/friendly)

### 🚧 Pending

- **Batch 2:** Stock/decorative SEO tail implementation
- **Batch 3:** Extend brand rules to all contexts (facility, equipment, service-icon, testimonial, clinical, business)
- **Phase 2+:** Facility/clinical/business SEO tails with location/service

---

## VIII. Testing

### Test Matrix

| Context | cm | bm | seo | Expected Title | Expected Description |
|---------|----|----|-----|----------------|---------------------|
| stock | 0 | 0 | 0 | Scenic only | Scenic only |
| stock | 0 | 0 | 1 | Scenic only | Scenic + SEO tail |
| team | 1 | 1 | 0 | "Team – {bn}" | Scenic + tie-in, NO SEO tail |
| team | 1 | 1 | 1 | "Team – {bn}" | Scenic + tie-in (+ SEO tail Phase 2) |
| clinical | 0 | 1 | 0 | Service name | Scenic only |
| clinical | 0 | 1 | 1 | Service name | Scenic + brand + SEO tail |

### Test Commands

```bash
# Test TEAM + SEO OFF
wp msh check-analyze --id=617
wp post meta update 617 _msh_context "team"
wp post meta update 617 _msh_seo_mode "0"

# Test TEAM + SEO ON
wp post meta update 617 _msh_seo_mode "1"
wp msh check-analyze --id=617

# Check results in logs
grep "MSH Batch1" /path/to/debug.log
grep "MSH DEBUG Batch1 FINAL" /path/to/debug.log
```

---

## IX. Key Files

| File | Purpose |
|------|---------|
| `includes/class-msh-openai-connector.php` | AI prompt generation, response parsing, validators |
| `includes/class-msh-ai-service.php` | AI service access control |
| `includes/class-msh-key-compactor.php` | Short key ↔ verbose key conversion |
| `includes/inc-io.php` | Atomic file I/O operations |

---

## X. Debug Logging

### Key Log Markers

```
[AI_CALL] - AI request initiated
[AI_RESP] - AI response received with token counts
[MSH Batch1] - Batch 1 validator actions
[MSH DEBUG Batch1] - Final metadata after validation (within parse_openai_response)
[MSH DEBUG Batch1 FINAL] - Final metadata before return (seo=0 protection check)
[MSH OpenAI] Generated description - Final description logged
[MSH AI Token Optimization] - Token usage and byte savings
[MSH TELEMETRY] - Per-image telemetry data
```

### Example Log Sequence (TEAM + SEO OFF)

```
[AI_CALL] #617 flags ct=team seo=0 bm=1
[AI_RESP] #617 ok=1 tokens=377/73/450
[MSH Batch1] TEAM: organic title. Original: 'Forest Scene' -> Fixed: 'Healthcare Team – Main Street Health'
[MSH Batch1] TEAM: organic tie-in. Added: 'This image reflects the supportive team at Main Street Health.'
[MSH DEBUG Batch1] Final metadata for ct=team bm=1: title="Healthcare Team – Main Street Health", desc="...Main Street Health.", issues=[]
[MSH DEBUG Batch1 FINAL] seo=0 ct=team bm=1: t=Healthcare Team – Main Street Health | d=...Main Street Health.
[MSH OpenAI] Generated description: A tranquil forest setting... This image reflects the supportive team at Main Street Health.
```

---

## XI. Related Documentation

- [MSH_IMAGE_OPTIMIZER_SYSTEM_GUIDE.md](MSH_IMAGE_OPTIMIZER_SYSTEM_GUIDE.md) - System architecture
- [MSH_IMAGE_OPTIMIZER_DEV_NOTES.md](MSH_IMAGE_OPTIMIZER_DEV_NOTES.md) - Development notes
- [USER_GUIDE_CONTEXT_PROFILES.md](USER_GUIDE_CONTEXT_PROFILES.md) - Context profile user guide
- [TELEMETRY-INTEGRATION.md](TELEMETRY-INTEGRATION.md) - Token usage telemetry

---

**End of Rulebook**
