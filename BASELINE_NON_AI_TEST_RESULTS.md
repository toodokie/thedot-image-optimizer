# Baseline Non-AI Test Results - Legal Industry

**Test Site**: Sterling & Associates Law Firm
**Date**: October 16, 2025
**Plugin Version**: 1.2.0
**AI Status**: Disabled (manual mode)
**Industry**: Legal Services
**Business Type**: Professional Services

---

## Test Configuration

**Business Context:**
- Business Name: Sterling & Associates Law Firm
- Location: Toronto, ON
- Service Area: Greater Toronto Area
- Brand Voice: Professional
- CTA Preference: Neutral / informative
- Target Audience: Individuals and businesses seeking legal representation in Ontario
- UVP: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters.
- Achievement Markers: Licensed Ontario attorneys, 25+ years combined experience, confidential consultations, direct billing available

---

## Test Images & Results

### 1. IMG_9823.jpg (Generic Camera Filename)

**Detected Context**: Business / General
**Original Filename**: `IMG_9823.jpg`

**Generated Metadata:**
- **Title**: Legal Services – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: Legal representation at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `legal-services-toronto-5.jpg`

**Analysis**: ✅ Correctly identified camera filename, replaced with legal services generic metadata

---

### 2. boardroom-1200x800.jpg (Dimension Pattern)

**Detected Context**: Business / General
**Original Filename**: `boardroom-1200x800.jpg`

**Generated Metadata:**
- **Title**: boardroom – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: boardroom at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `legal-services-toronto-7.jpg`

**Analysis**: ✅ Dimension pattern `-1200x800` correctly stripped, kept descriptor "boardroom"

---

### 3. canola4.jpg (Meaningless Descriptor)

**Detected Context**: Business / General
**Original Filename**: `canola4.jpg`

**Generated Metadata:**
- **Title**: Legal Services – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: Legal representation at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `legal-services-toronto-8.jpg`

**Analysis**: ✅ Correctly identified "canola4" as meaningless, replaced with generic legal metadata

---

### 4. courthouse.jpg (Specific Descriptor)

**Detected Context**: Business / General
**Original Filename**: `courthouse.jpg`

**Generated Metadata:**
- **Title**: courthouse – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: courthouse at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `courthouse-toronto.jpg`

**Analysis**: ✅ Preserved "courthouse" as relevant legal descriptor, location-aware filename

---

### 5. law-books.jpg (Specific Descriptor)

**Detected Context**: Business / General
**Original Filename**: `law-books.jpg`

**Generated Metadata:**
- **Title**: law-books – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: law-books at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `books-law-toronto.jpg`

**Analysis**: ✅ Preserved "law-books" as specific descriptor, reordered filename for SEO

---

### 6. team-580x300.jpg (Team + Dimension Pattern)

**Detected Context**: Team Member
**Original Filename**: `team-580x300.jpg`

**Generated Metadata:**
- **Title**: team – Sterling & Associates Law Firm – Toronto, Ontario, Canada
- **Alt Text**: team from Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: team – part of the legal services team
- **Description**: Meet team from Sterling & Associates Law Firm in Toronto, Ontario, Canada. Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Serving Individuals and businesses seeking legal representation in Ontario. Contact us today to get started.
- **Suggested Filename**: `sterling-associates-law-firm-team-team.jpg`

**Analysis**: ✅ Dimension pattern `-580x300` correctly stripped, detected as team photo, appropriate group metadata

---

### 7. attorney.jpg (Generic Legal Term)

**Detected Context**: Business / General
**Original Filename**: `attorney.jpg`

**Generated Metadata:**
- **Title**: attorney – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: attorney at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `attorney-toronto.jpg` (expected based on pattern)

**Analysis**: ⚠️ "attorney" kept as descriptor (borderline generic but legal-relevant)

---

### 8. consultation.jpg (Generic Legal Term)

**Detected Context**: Business / General
**Original Filename**: `consultation.jpg`

**Generated Metadata:**
- **Title**: consultation – Sterling & Associates Law Firm | Toronto, Ontario, Canada
- **Alt Text**: consultation at Sterling & Associates Law Firm in Toronto, Ontario, Canada.
- **Caption**: Professional legal services
- **Description**: Experienced legal team providing personalized counsel and strategic representation across civil, corporate, and family law matters. Licensed attorneys serving Greater Toronto Area. Experienced professionals.
- **Suggested Filename**: `consultation-toronto.jpg` (expected)

**Analysis**: ⚠️ "consultation" kept as descriptor (generic but contextual)

---

## Observations

### What Works Well ✅

1. **Camera filename detection** - `IMG_9823` correctly identified and replaced
2. **Dimension pattern stripping** - `-1200x800`, `-580x300` patterns removed
3. **Meaningless descriptor filtering** - `canola4` replaced with generic metadata
4. **Specific descriptor preservation** - `courthouse`, `law-books` kept
5. **Team photo detection** - `team` keyword triggers appropriate group metadata
6. **Location integration** - Toronto, ON consistently included
7. **Business context** - UVP and achievement markers properly used
8. **SEO-friendly filenames** - Location-based, industry-aware suggestions

### Potential Improvements ⚠️

1. **Capitalization** - Descriptors like "boardroom", "courthouse", "law-books" shown in lowercase in titles
   - Should be: "Boardroom – Sterling & Associates..."
   - Currently: "boardroom – Sterling & Associates..."

2. **Hyphenated terms** - "law-books" should display as "Law Books" (with space)
   - Should be: "Law Books – Sterling & Associates..."
   - Currently: "law-books – Sterling & Associates..."

3. **Generic legal terms** - "attorney", "consultation" kept as descriptors
   - Could be considered too generic and filtered out
   - OR intentionally kept as contextually relevant

4. **Duplicate filename suggestions** - `sterling-associates-law-firm-team-team.jpg` has "team" twice
   - Should be: `sterling-associates-law-firm-team.jpg`

### Critical Bugs Fixed in v1.2.0 🔧

1. ✅ **Dimension pattern detection** - Fixed regex to catch `-580x300` patterns with hyphens/underscores
2. ✅ **Title sanitization** - Strip dimensions from title before team/person detection
3. ✅ **Team keyword false positives** - "team-580x300" no longer treated as person name
4. ✅ **Slugify dimension removal** - Dimensions stripped when creating slugs

---

## Comparison Points for AI Testing

When testing AI version, compare:

1. **Descriptor quality** - Does AI provide better descriptions than "boardroom", "courthouse"?
2. **Capitalization** - Does AI properly capitalize descriptors?
3. **Contextual understanding** - Does AI understand legal context better (e.g., "consultation room" vs "consultation")?
4. **Alt text specificity** - Does AI generate more descriptive alt text?
5. **Generic term handling** - How does AI handle "attorney", "consultation"?
6. **Team photo descriptions** - Does AI provide better team member descriptions?

---

## Test Environment Details

**WordPress Version**: (check)
**PHP Version**: (check)
**Local by Flywheel**: (version)
**Plugin Activation**: Active
**Symlink Status**: Not symlinked (copied installation)
**OpCache**: Cleared before testing

---

## Next Steps

1. Enable AI (set `msh_plan_tier` to `ai_starter`)
2. Set AI mode to `assist` or `hybrid`
3. Enable `meta` feature in `msh_ai_features`
4. Upload same images or new test images
5. Compare AI-generated metadata with these baseline results
6. Document differences and quality improvements
