# ✔︎ TINYDOT IMAGE OPTIMIZER — METADATA RULEBOOK

**Version:** Phase 2G Verified
**Status:** Final and Clean
**Covers:** Both AI and Non-AI Flows

---

## 1. FOUNDATIONAL PRINCIPLES

These rules apply across all flows, all categories, all modes.

### 1.1 Accuracy > SEO

The model prioritizes truthful scene description over invented brand/location context. Scenic images stay scenic.

### 1.2 Brand Ownership Principle

Only brand-owned contexts may carry the business name.

**These contexts are:**
- `team`
- `facility`
- `equipment`
- `service-icon`
- `brand_logo`
- `testimonial` (when manually set)

All other contexts treat brand names as pollution.

### 1.3 SEO Tail Never Dominates

Only one SEO tail sentence may appear (when SEO mode is on).
But in Phase 2E/F/G, SEO mode is temporarily neutralized → tails are disabled.

### 1.4 Consistent Behaviour Across Flows

AI and Non-AI flows follow the same category/brand rules for:
- brand inclusion
- pollution stripping
- scenic vs branded structure
- TEAM tie-in sentences

---

## 2. CATEGORY DEFINITIONS & METADATA RULES

This is the authoritative category→behaviour mapping.

### 2.1 STOCK (default), DECORATIVE

This is the safest, most constrained context.

| Field | Rule |
|-------|------|
| **Title** | scenic only |
| **Alt** | literal scene description |
| **Caption** | scenic optional |
| **Description** | scenic only |
| **Brand?** | Never |
| **Location/Service?** | Never |
| **SEO tail?** | Disabled in Phase 2E |
| **Tie-in?** | Never |
| **Pollution stripping?** | Yes |

---

### 2.2 TEAM

This is the most sensitive context with strict rules.

#### TEAM Metadata Structure

1. **Sentence 1** → Scenic AI description
2. **Sentence 2** → Mandatory TEAM tie-in:
   ```
   "This image reflects the supportive team at {business_name}."
   ```

#### TEAM Rules

| Field | Rule |
|-------|------|
| **Title** | MUST contain business_name |
| **Alt** | Scenic only |
| **Caption** | Scenic; optional brand |
| **Description** | Scenic first sentence + TEAM tie-in |
| **Brand** | Always allowed, always required |
| **Location** | Never allowed |
| **Service** | Never allowed |
| **CTA** | Never allowed |
| **SEO tail** | Disabled for Phase 2E |

#### PROTECTED RULE

TEAM context must override everything:
`fill-empty`, SEO injection, contextual heuristics, legacy rules.

---

### 2.3 FACILITY

Real business location photos.

- Brand allowed/expected
- Location allowed
- Service allowed
- SEO tails disabled (2E)
- Pollution stripping runs only if scene content contradicts location branding

---

### 2.4 EQUIPMENT

Tools belonging to business.

- Brand expected
- Scenic allowed
- No external locations
- No service keywords unless visible or allowed by brand category

---

### 2.5 TESTIMONIAL

User manually sets this.

**Rules:**
- Brand allowed
- Location allowed
- Service allowed
- Never claim the image depicts the real client or facility
- AI describes emotion/concept in scenic form

---

### 2.6 CLINICAL, BUSINESS

**Rules:**
- Brand allowed only when `brand_name_visible=true`
- Location allowed when brand allowed
- Service allowed
- SEO neutralized (Phase 2E)
- Pollution stripping applies when branding disabled

---

## 3. BRAND VISIBILITY MATRIX

This defines when the business name MUST/NEVER appear.

| Context | Brand Required | Brand Prohibited |
|---------|---------------|------------------|
| `team` | YES | never |
| `facility` | yes | only if ct mismatch |
| `equipment` | yes | never |
| `service-icon` | yes (if identifiable) | if unclear |
| `testimonial` | yes (manual) | never |
| `clinical` | depends on bm | when bm=false |
| `business` | depends on bm | when bm=false |
| `stock/decor` | never | always prohibited |

---

## 4. POLLUTION STRIPPING ENGINE

Automatically removes:

### Location terms
```
Hamilton, Ontario, Canada, etc.
```

### Service terms
```
medical, rehabilitation, clinic, physiotherapy, wellness
```

### CTA terms
```
book, schedule, call, learn more, visit
```

### Legacy SEO garbage
```
"practice", "offers", "support",
"this practice in… offers medical support", etc.
```

**Stripping runs for:**
- STOCK
- DECOR
- TEAM
- CLINICAL/business when bm=false

**TEAM receives a two-pass strip:**
1. Strip pollution
2. Reapply tie-in

---

## 5. SEO MODE (Phase 2E–2G Behaviour)

SEO mode is temporarily neutralized across all contexts:

```php
$seo_mode = false;
$seo_mode_flag = false;
```

**Meaning:**
- No SEO tails
- No injected services
- No injected location
- No CTA
- No "Ideal for…" sentences
- No connection to `_msh_seo_mode`

The SEO checkbox is present in UI but ignored internally until Phase 3.

---

## 6. PROCESSING PIPELINE (AI FLOW)

### Step 1: AI Generation
- Model produces raw scenic + possible pollution text

### Step 2: Parsing/Sanitization
- Normalize punctuation
- Enforce length
- Enforce key formats

### Step 3: Pollution Stripping
(stocks, team, clinical/business when bm=false)

### Step 4: TEAM Enforcement

If `ct=team` and manual set true:
- Overwrite title to TEAM template
- Overwrite description to scenic + tie-in
- Strip any remaining pollution
- Reapply tie-in

### Step 5: Final Logging

**Three logs:**

**Log A: Pre-strip enforcement**
```
[MSH DEBUG enforce_team] final_desc=<before cleanup>
```

**Log B: Post-strip + TEAM rebuild**
```
[MSH DEBUG enforce_team] final_desc=<clean after rebuild>
```

**Log C: Batch1 Summary**
```
[MSH DEBUG Batch1 FINAL] id=XYZ ct=team seo=0 t=<title> d=<description>
```

**Note:** `seo=0` is correct due to Phase 2E forcing SEO off.

---

## 7. NON-AI FLOW (Deterministic Composer)

**Uses:**
- Filename scene parsing
- Context type rules (same as AI)
- Business context (same as AI)
- Template banks
- Pollution stripping
- Tie-in enforcement (TEAM)
- SEO neutralized (same as AI)

**Key guarantee:** AI and non-AI produce compatible metadata for same context.

---

## 8. VERIFIED FROM MICRO-TESTS

✔ TEAM + SEO ON → Clean + tie-in + no pollution
✔ TEAM + SEO OFF → Clean + tie-in + no pollution
✔ STOCK + SEO ON → Scenic only, no pollution
✔ STOCK + SEO OFF → Scenic only, no pollution
✔ TEAM logs fire twice (pre/post strip)
✔ Batch1 FINAL log fires
✔ SEO neutralization confirmed

---

## 9. RULEBOOK STATUS: FINAL & READY FOR PUBLICATION

This document is accurate, observed in runtime, validated in logs, and matches the post-sync code that is now live in the WordPress environment.

**Nothing in this rulebook is theoretical.**
**Everything here is grounded in actual verified behavior.**

---
