# MSH Image Optimizer - Testing Overview & Strategy

**Document Purpose:** Comprehensive testing strategy across all 17 supported industries
**Status:** Active Development & Testing
**Last Updated:** October 15, 2025

---

## Testing Philosophy

### Multi-Industry Validation

MSH Image Optimizer supports **17 different industries**, each with unique:
- Vocabulary and terminology
- Professional designations
- Service offerings
- Target audiences
- Business contexts

**Testing Strategy:** Create dedicated test sites for representative industries across three major categories:

1. **Healthcare & Wellness** (4 industries: medical, dental, therapy, wellness)
2. **Home Services** (4 industries: plumbing, HVAC, electrical, renovation)
3. **Professional Services** (9 industries: legal, accounting, consulting, marketing, web_design, real_estate, financial, insurance, photography)

---

## Test Site Matrix

### Phase 1: Core Industries (Critical Bug Validation)

| Industry | Test Site | Business Name | Priority | Status |
|----------|-----------|---------------|----------|--------|
| **Wellness** | [TEST_SITE_WELLNESS.md](TEST_SITE_WELLNESS.md) | Radiant Bloom Studio | 🔥 CRITICAL | Ready |
| **HVAC** | *TBD* | Arctic Comfort Systems | High | Planned |

**Purpose:** Validate the critical wellness/healthcare metadata bug fix
- **Bug:** Wellness businesses receiving "Rehabilitation Treatment" metadata
- **Fix:** Separate wellness from healthcare, implement `generate_wellness_meta()`
- **Validation:** Confirm spa/beauty language, no clinical/rehabilitation terms

---

### Phase 2: Professional Services

| Industry | Test Site | Business Name | Priority | Status |
|----------|-----------|---------------|----------|--------|
| **Legal** | [TEST_SITE_LAW_FIRM.md](TEST_SITE_LAW_FIRM.md) | Sterling & Associates Law | High | Ready |
| **Accounting** | *TBD* | Precision Accounting Group | Medium | Planned |
| **Real Estate** | *TBD* | Horizon Realty Toronto | Medium | Planned |

**Purpose:** Validate professional services metadata distinct from home services and healthcare
- Professional designations (attorney, CPA, realtor)
- Advisory/consultation language
- Practice areas vs. service types
- Client-focused (not patient-focused, not customer-focused)

---

### Phase 3: Home Services (Contractors)

| Industry | Test Site | Business Name | Priority | Status |
|----------|-----------|---------------|----------|--------|
| **Plumbing** | *TBD* | Premier Plumbing Toronto | Medium | Planned |
| **Electrical** | *TBD* | Bright Spark Electric | Medium | Planned |
| **Renovation** | *TBD* | Elite Home Renovations | Medium | Planned |

**Purpose:** Validate technical service metadata
- Licensed contractor language
- Emergency/24-7 availability
- Equipment and facility images
- Technical service terminology

---

### Phase 4: Healthcare Industries

| Industry | Test Site | Business Name | Priority | Status |
|----------|-----------|---------------|----------|--------|
| **Medical** | *Existing Production* | Main Street Health | Low | Production |
| **Dental** | *TBD* | Bright Smile Dentistry | Low | Planned |
| **Therapy** | *TBD* | Toronto Physio Clinic | Low | Planned |

**Purpose:** Validate healthcare metadata remains appropriate for actual healthcare businesses
- Clinical/treatment language appropriate
- Patient-focused terminology
- Healthcare credentials (RMT, DC, physiotherapist)
- Treatment/rehabilitation services

---

### Phase 5: Remaining Professional Services

| Industry | Test Site | Business Name | Priority | Status |
|----------|-----------|---------------|----------|--------|
| **Marketing** | *TBD* | Catalyst Marketing Agency | Low | Planned |
| **Consulting** | *TBD* | Strategic Insights Consulting | Low | Planned |
| **Web Design** | *TBD* | Pixel Perfect Studios | Low | Planned |
| **Financial** | *TBD* | Wealth Advisors Toronto | Low | Planned |
| **Insurance** | *TBD* | Guardian Insurance Brokers | Low | Planned |
| **Photography** | *TBD* | Luminous Photography | Low | Planned |

**Purpose:** Comprehensive validation across all supported industries

---

## Testing Methodology

### For Each Test Site:

**1. Site Setup** (20-30 minutes)
- Create Local by Flywheel site
- Install MSH Image Optimizer plugin
- Configure industry-specific business context
- Create sample pages with industry-appropriate copy

**2. Image Preparation** (10-15 minutes)
- Download 12-18 stock images appropriate for industry
- Rename to test edge cases:
  - Generic camera filenames (`IMG_5847.jpg`)
  - Dimension patterns (`photo-580x300.jpg`)
  - WordPress test terms (`alignment-image.jpg`, `featured-photo.jpg`)
  - Meaningless descriptors (`canola2.jpg`, `sample.jpg`)
  - Specific descriptors to preserve (`margaret-sterling.jpg`)

**3. Upload & Initial Review** (5 minutes)
- Upload all images to WordPress Media Library
- Let plugin auto-generate metadata
- Review for obvious issues

**4. Validation Checklist** (10-15 minutes)
- [ ] Industry-specific vocabulary appears
- [ ] Professional designation correct for industry
- [ ] Service area mentioned appropriately
- [ ] UVP incorporated where relevant
- [ ] Business name and location present
- [ ] Dimension patterns filtered out
- [ ] WordPress test terms removed
- [ ] Generic descriptors handled appropriately
- [ ] Specific descriptors preserved
- [ ] NO cross-industry contamination (e.g., spa getting healthcare terms)

**5. Edge Case Testing** (10 minutes)
- Test with different image types (team, facility, service, products)
- Verify fallback behavior for images with no context
- Test descriptor extraction from various filename patterns
- Validate cascading fallback (UVP → Pain Points → Target Audience → Generic)

---

## Critical Test Cases

### Test Case 1: Wellness ≠ Healthcare (CRITICAL)

**Setup:**
- Test Site: Radiant Bloom Studio (wellness)
- Image: Spa treatment room

**Expected BEFORE Fix:**
```
Title: Rehabilitation Treatment - Radiant Bloom Studio Toronto, Ontario
Description: Comprehensive rehabilitation care. WSIB approved. Direct billing.
```
❌ **BUG:** Spa getting healthcare metadata

**Expected AFTER Fix:**
```
Title: Spa Treatment - Radiant Bloom Studio | Toronto, Ontario
Description: Luxury spa and wellness sanctuary offering personalized holistic
            treatments, organic skincare, and rejuvenating massage therapy...
```
✅ **CORRECT:** Spa-appropriate metadata

---

### Test Case 2: Professional vs. Home Services

**Setup:**
- Test Site A: Sterling & Associates Law (legal)
- Test Site B: Arctic Comfort Systems (HVAC)
- Same image type: Team photo

**Expected Legal:**
```
Title: Legal Team – Sterling & Associates Law | Toronto, Ontario
Caption: Experienced Toronto lawyers
Description: Trusted legal counsel for businesses and families since 1987...
```

**Expected HVAC:**
```
Title: HVAC Team – Arctic Comfort Systems | Toronto, Ontario
Caption: Licensed HVAC technicians
Description: Fast, reliable HVAC service with energy-efficient installations...
```

**Validation:**
- ✅ Different professional designations (lawyers vs. technicians)
- ✅ Different service language (legal counsel vs. HVAC service)
- ✅ Different credentials (trusted since 1987 vs. licensed contractors)
- ✅ No cross-contamination

---

### Test Case 3: Dimension Pattern Filtering

**Setup:**
- Any test site
- Image filename: `team-photo-580x300.jpg`

**Expected:**
```
Title: Team Photo – [Business Name] | [Location]
```
NOT: "Team Photo 580x300 – [Business Name]"

**Validation:**
- ✅ Dimension pattern `580x300` removed from descriptor
- ✅ "Team Photo" preserved as meaningful descriptor
- ✅ Applies to all patterns: `150x150`, `1200x4002`, etc.

---

### Test Case 4: WordPress Test Terms

**Setup:**
- Any test site
- Images: `alignment-photo.jpg`, `featured-image.jpg`, `markup-sample.jpg`

**Expected:**
```
Title: Photo – [Business Name] | [Location]
Title: Image – [Business Name] | [Location]
Title: Sample – [Business Name] | [Location]
```
NOT including "Alignment", "Featured", "Markup"

**Validation:**
- ✅ WordPress test terms removed
- ✅ Remaining descriptor used (if meaningful)
- ✅ Falls back to generic if only test term existed

---

### Test Case 5: Generic Descriptor Handling

**Setup:**
- Any test site
- Images: `IMG_5847.jpg`, `canola2.jpg`, `photo.jpg`

**Expected:**
```
Title: [Industry] Services – [Business Name] | [Location]
```
OR use page context if available

**Validation:**
- ✅ Generic/meaningless descriptors don't appear in titles
- ✅ Fallback to industry-appropriate default
- ✅ Still includes business name, location, credentials

---

## Automated Testing (Future)

### Unit Tests (Planned)
- `test_sanitize_descriptor()` - Dimension patterns, WordPress terms, generic terms
- `test_extract_visual_descriptor()` - Descriptor extraction from various sources
- `test_generate_[industry]_meta()` - Each industry generator
- `test_industry_routing()` - Correct generator selection by industry
- `test_cascading_fallback()` - UVP → Pain Points → Target Audience → Generic

### Integration Tests (Planned)
- Full metadata generation pipeline
- Context loading and hydration
- Schema.org output generation
- AI referral tracking

---

## Test Data Repository

### Stock Image Collections

**Wellness/Spa:**
- https://unsplash.com/s/photos/spa-treatment
- https://www.pexels.com/search/massage/
- https://www.pexels.com/search/beauty%20treatment/

**Legal/Professional:**
- https://unsplash.com/s/photos/lawyer
- https://www.pexels.com/search/law%20office/
- https://unsplash.com/s/photos/business-meeting

**HVAC/Contractors:**
- https://unsplash.com/s/photos/hvac
- https://www.pexels.com/search/furnace%20repair/
- https://unsplash.com/s/photos/technician

**Healthcare:**
- https://unsplash.com/s/photos/physiotherapy
- https://www.pexels.com/search/chiropractic/
- https://unsplash.com/s/photos/dental-clinic

### Test Business Contexts

All business contexts (UVP, pain points, target audience) are documented in each test site's setup guide.

---

## Quality Gates

### Before Release:

**Must Pass:**
- ✅ Wellness generates spa/beauty metadata (not healthcare)
- ✅ All dimension patterns filtered from descriptors
- ✅ All WordPress test terms removed
- ✅ Generic descriptors handled appropriately
- ✅ Industry routing works correctly (no cross-contamination)

**Should Pass:**
- ✅ At least 3 industry test sites validated (wellness, legal, HVAC)
- ✅ Team, facility, and service images tested per industry
- ✅ Cascading fallback works (UVP → Pain Points → Target Audience)
- ✅ Schema.org output valid JSON-LD

**Nice to Have:**
- ✅ All 17 industries tested with sample images
- ✅ Edge cases documented
- ✅ Performance benchmarks (metadata generation speed)

---

## Regression Testing

### After Each Bug Fix:

1. **Re-test affected industry** (e.g., wellness after wellness bug fix)
2. **Spot-check 2-3 other industries** to ensure no regressions
3. **Verify edge cases still handled** (dimensions, WordPress terms, generics)
4. **Update test site documentation** with any new findings

### Before Major Releases:

1. **Full test suite** across all priority test sites
2. **New image uploads** to each test site (simulate fresh use)
3. **Metadata comparison** against expected templates
4. **Schema.org validation** for all generated markup
5. **AI referral tracking verification** (if enabled)

---

## Test Site Quick Reference

| Site | Local URL | Admin | Path |
|------|-----------|-------|------|
| Radiant Bloom Wellness | http://radiant-bloom-wellness.local | admin/admin | `/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public` |
| Sterling Law Firm | http://sterling-law-firm.local | admin/admin | `/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public` |
| Arctic Comfort HVAC | *TBD* | admin/admin | *TBD* |
| General Testing | http://thedot-optimizer-test.local | *current* | `/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public` |

---

## Related Documentation

- [TEST_SITE_WELLNESS.md](TEST_SITE_WELLNESS.md) - Radiant Bloom Studio setup
- [TEST_SITE_LAW_FIRM.md](TEST_SITE_LAW_FIRM.md) - Sterling & Associates Law setup
- [Industry Metadata Templates](../../docs/reference/INDUSTRY_METADATA_TEMPLATES.md) - All 17 industry templates
- [MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md](MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md) - Main documentation

---

**Testing Status:** Phase 1 in progress (Wellness site ready, HVAC pending)
**Next Steps:** Create wellness site, validate bug fix, create legal site, validate professional services
