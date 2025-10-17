# Industry-Specific Metadata Templates & Keywords

## Overview

This document defines metadata generation templates for all 17 industries offered in the onboarding flow. Each industry has:
- **Service Keywords** - Industry-specific terminology for descriptions
- **Customer Terms** - What to call clients/customers/patients
- **Common Services** - Typical services offered
- **SEO Keywords** - Industry-specific search terms
- **Metadata Templates** - Title, ALT text, caption, description patterns

---

## Implementation Notes (October 16, 2025)

### ✅ Currently Implemented
- All five industry phases are active in `msh-image-optimizer/includes/class-msh-image-optimizer.php`.
- `generate_business_meta()` routes metadata creation based on the active onboarding industry.
- Shared helpers:
  - `extract_visual_descriptor()` filters camera/demo strings before returning descriptors.
  - `sanitize_descriptor()` removes generic terms, punctuation, and enforces length limits.
  - `build_industry_description()` cascades **UVP → Pain Points → Achievement markers → Target Audience → Industry value proposition → Generic fallback** with credential copy.
- Slug fallbacks emit industry-aware prefixes (e.g., `legal-services-toronto-1234.jpg`) when filenames are generic.
- Descriptor filtering normalises WordPress demo data (`canola2`, `image alignment`, `post format`, etc.) so metadata falls back cleanly.

### 📊 Testing & Validation
- **Baseline Non-AI Test Results**: See [BASELINE_NON_AI_TEST_RESULTS.md](BASELINE_NON_AI_TEST_RESULTS.md) for comprehensive legal industry testing with 8 test images, edge cases, and quality analysis (v1.2.0)

### 🚧 In Progress / Extensible Today
- Stub helpers now exist for temporal keywords, trust signals, journey-stage copy, achievement markers, and industry value propositions. By default they return empty strings/arrays but expose filters (`msh_temporal_keywords`, `msh_trust_signals`, `msh_journey_content`, `msh_achievement_markers`, `msh_industry_value_prop`) so partners can extend behaviour immediately.
- Documentation clearly labels these helpers as opt-in extensions until full logic ships.

### 🗺️ Planned Enhancements
- `$options` parameter on generators to accept season/journey/category overrides.
- Admin UI + storage for editable trust signals and achievement markers.
- Automatic seasonal detection feeding analyzer output.
- Analytics/A-B testing layer for metadata optimisation.

Use the sections below as the authoritative copy source. Treat roadmap features as forward-looking unless code references confirm implementation.

### Current vs Planned Matrix

| Feature | Current State (v1.2.0) | Planned Enhancement |
| --- | --- | --- |
| Industry generators | ✅ All 17 implemented | – |
| Descriptor sanitisation | ✅ Filters demo/camera strings | ML keyword scoring |
| Metadata cascade | ✅ UVP → Pain Points → Achievement → Audience → Value Prop → Generic | Analytics-driven auto optimisation |
| Slug fallbacks | ✅ Industry-aware prefixes | Geo-aware variants |
| Temporal keywords | 🚧 Stub + `msh_temporal_keywords` filter | Seasonal defaults + admin UI |
| Trust signals | 🚧 Stub + `msh_trust_signals` filter | Editable trust-signal library |
| Journey content | 🚧 Stub + `msh_journey_content` filter | Automatic journey detection |
| Achievement markers | 🚧 Defaults + `msh_achievement_markers` filter | Admin UI + analytics integration |
| Industry value props | 🚧 Default map + `msh_industry_value_prop` filter | Editable per-site messaging |
| `$options` parameter | 🚫 Not yet | Season/journey/category overrides |

**Available filters/hooks today**
- `msh_temporal_keywords`
- `msh_trust_signals`
- `msh_journey_content`
- `msh_achievement_markers`
- `msh_industry_value_prop`

Use these hooks to inject custom behaviour until core UI/logic ships.

---

## Implementation Architecture

### Generator Function Signature

**All industry metadata generators MUST follow this signature pattern:**

```php
private function generate_{industry}_meta(array $context, $descriptor = '') {
    // $descriptor is optional and enables future Tier 2 extraction:
    // - Empty string (default) → extract from context or use generic
    // - Specific term → use it directly (when we add extraction logic)

    // If descriptor not provided, try to extract from context
    if ($descriptor === '') {
        $descriptor = $this->extract_visual_descriptor($context);
    }

    // Build metadata using descriptor + onboarding data cascading fallbacks
    // ...
}
```

**Benefits:**
- ✅ Future-proof for Tier 2 extraction without refactoring call sites
- ✅ Easy to test with explicit descriptors
- ✅ Backwards compatible (default = extract from context)
- ✅ Consistent pattern across all 17 industries

---

### Cascading Fallback Pattern

**When building descriptions, use this fallback chain:**

**Current Cascade Logic (Implemented)**

```php
private function build_industry_description($generic_text, $credentials = '', array $options = []) {
    // Priority 1: UVP (most specific, user-written)
    if ($this->uvp !== '') { ... }

    // Priority 2: Pain Points (specific problems we solve)
    if ($this->pain_points !== '') { ... }

    // Priority 3: Achievement markers (if available)
    $achievement = $this->get_achievement_markers($industry);
    if ($achievement !== '') { ... }

    // Priority 4: Target Audience (who we serve)
    if ($this->target_audience !== '') { ... }

    // Priority 5: Industry value proposition (default statement)
    $industry_value = $this->get_industry_value_proposition($industry);
    if ($industry_value !== '') { ... }

    // Priority 6: Generic fallback (includes trust signals if present)
    return implode(' ', ...);
}
```

### Fallback Examples

**Scenario 1: Full Onboarding (Best Case)**
```
UVP: "Fast, reliable emergency plumbing with upfront pricing and 24/7 availability"
Result: "Fast, reliable emergency plumbing with upfront pricing and 24/7 availability. Licensed, insured plumbers serving Greater Toronto Area."
```
✅ Uses UVP (most specific)

**Scenario 2: UVP Blank**
```
UVP: (blank)
Pain Points: "Emergency repairs, drain cleaning, water heater replacement"
Result: "Professional plumbing services specializing in emergency repairs, drain cleaning, water heater replacement. Licensed, insured plumbers serving Greater Toronto Area."
```
✅ Falls back to Pain Points

**Scenario 3: UVP + Pain Points Blank**
```
UVP: (blank)
Pain Points: (blank)
Target Audience: "Homeowners and property managers"
Result: "Professional plumbing services serving homeowners and property managers. Licensed, insured plumbers serving Greater Toronto Area."
```
✅ Falls back to Target Audience

**Scenario 4: Minimal Onboarding (Worst Case)**
```
UVP: (blank)
Pain Points: (blank)
Target Audience: (blank)
Result: "Professional plumbing services. Licensed, insured plumbers serving Greater Toronto Area."
```
✅ Generic fallback still professional

---

### Helper Functions Required

All generators depend on these helper functions:

#### 1. build_industry_description()

```php
/**
 * Build description with cascading fallbacks from onboarding data
 *
 * @param string $generic_text Base description text
 * @param string $credentials Industry credentials/trust markers
 * @return string Final description
 */
private function build_industry_description($generic_text, $credentials) {
    if ($this->uvp !== '') {
        $desc = $this->normalize_sentence($this->uvp);
        if ($credentials !== '') {
            $desc .= ' ' . $this->normalize_sentence($credentials);
        }
        return trim($desc);
    }

    if ($this->pain_points !== '') {
        $base = $generic_text !== '' ? $generic_text : __('Professional services', 'msh-image-optimizer');
        $desc = $this->normalize_sentence(sprintf(
            __('%1$s specializing in %2$s', 'msh-image-optimizer'),
            $base,
            $this->pain_points
        ));
        if ($credentials !== '') {
            $desc .= ' ' . $this->normalize_sentence($credentials);
        }
        return trim($desc);
    }

    if ($this->target_audience !== '') {
        $base = $generic_text !== '' ? $generic_text : __('Professional services', 'msh-image-optimizer');
        $desc = $this->normalize_sentence(sprintf(
            __('%1$s serving %2$s', 'msh-image-optimizer'),
            $base,
            $this->target_audience
        ));
        if ($credentials !== '') {
            $desc .= ' ' . $this->normalize_sentence($credentials);
        }
        return trim($desc);
    }

    $parts = [];
    if ($generic_text !== '') {
        $parts[] = $this->normalize_sentence($generic_text);
    }
    if ($credentials !== '') {
        $parts[] = $this->normalize_sentence($credentials);
    }

    return trim(implode(' ', $parts));
}
```

#### 2. extract_visual_descriptor()

```php
/**
 * Extract visual descriptor from context (image title, page context, etc.)
 * Future-proof for Tier 2 extraction enhancements
 *
 * @param array $context Image context
 * @return string Descriptor or empty string
 */
private function extract_visual_descriptor($context) {
    $candidates = [
        $context['attachment_title'] ?? '',
        $context['page_title'] ?? '',
        $context['attachment_caption'] ?? '',
    ];

    if (!empty($context['attachment_slug'])) {
        $candidates[] = str_replace('-', ' ', (string) $context['attachment_slug']);
    }

    if (!empty($context['tags']) && is_array($context['tags'])) {
        $candidates = array_merge($candidates, $context['tags']);
    }

    foreach ($candidates as $candidate) {
        $candidate = $this->sanitize_descriptor($candidate);
        if ($candidate !== '' && !$this->is_generic_descriptor($candidate)) {
            return $candidate;
        }
    }

    return '';
}
```

#### 3. sanitize_descriptor()

```php
/**
 * Sanitize descriptor text for use in metadata
 *
 * @param string $text Raw descriptor text
 * @return string Sanitized descriptor
 */
private function sanitize_descriptor($text) {
    $text = trim(strip_tags((string) $text));
    if ($text === '' || $this->looks_like_camera_filename($text)) {
        return '';
    }

    $text = preg_replace('/\b(image|photo|picture|graphic)\b/i', '', $text);
    $text = preg_replace('/\b\d+x\d+\b/i', '', $text);
    $text = preg_replace('/\balignment\b/i', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text, " \t\n\r\0\x0B-_|'\":");

    if ($text === '' || $this->is_generic_descriptor($text)) {
        return '';
    }

    if (mb_strlen($text) > 60) {
        $text = rtrim(mb_substr($text, 0, 57)) . '...';
    }

    return $text;
}
```

---

### Generator Pattern (Implemented)

- Optional `$descriptor` parameter allows future Tier‑2 extraction; all generators call `extract_visual_descriptor()` when the provided descriptor is blank or generic.
- Titles and ALT text include the descriptor when present, otherwise they default to the industry service label (e.g., “HVAC Services – …”, “Accounting Services – …”).
- Captions are industry-specific (“Licensed HVAC technicians”, “Professional legal services”, etc.) and match the wording below.
- Descriptions are produced by `build_industry_description()` with industry-specific generic text and credential lines so UVP → Pain Points → Target Audience → generic fallback is consistent.
- Industry-specific slug prefixes are defined in `get_industry_slug_prefix()`; when a filename/title is generic we emit slugs like `legal-services-toronto-1234.jpg`.
- Healthcare industries (medical, dental, therapy, wellness) share the same helpers but use patient-focused credentials, while retail/e-commerce generators reference product experience copy.

The sections that follow remain the canonical wording for captions, credentials, and common services used in the generators.

## 1. Legal Services (`legal`)

### Service Keywords
```php
'legal' => [
    'default' => 'Licensed attorneys. Confidential consultations. Case evaluation available.',
    'consultation' => 'Free case evaluation. Experienced legal counsel. Client-focused representation.',
    'litigation' => 'Courtroom experience. Proven trial record. Aggressive legal advocacy.'
]
```

### Customer Term
- **Client**

### Common Services
- Legal consultation
- Case representation
- Document preparation
- Court litigation
- Contract review

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, attorney at {Business Name} {Location}`
- Caption: `{Name} - Licensed legal professional`
- Description: `{Name} provides expert legal services at {Business Name} in {Location}. Experienced in {practice areas}.`

**Office/Facility:**
- Title: `{Business Name} Law Office - {Location}`
- ALT: `Professional law office at {Business Name} {Location}`
- Caption: `Modern legal practice in {Location}`
- Description: `{Business Name} law offices in {Location}. Confidential meeting spaces and professional legal services.`

**General/Business:**
- Title: `Legal Services - {Business Name} {Location}`
- ALT: `Legal representation at {Business Name} {Location}`
- Caption: `Professional legal services`
- Description: `Expert legal counsel and representation. {UVP from onboarding}. Licensed attorneys serving {service area}.`

---

## 2. Accounting & Tax (`accounting`)

### Service Keywords
```php
'accounting' => [
    'default' => 'CPA services. Tax preparation. Financial planning available.',
    'tax' => 'Individual and business tax returns. IRS representation. Audit support.',
    'bookkeeping' => 'Monthly bookkeeping. Payroll services. Financial reporting.'
]
```

### Customer Term
- **Client**

### Common Services
- Tax preparation
- Bookkeeping
- Payroll services
- Financial planning
- Audit support

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, CPA at {Business Name} {Location}`
- Caption: `{Name} - Certified Public Accountant`
- Description: `{Name} provides accounting and tax services at {Business Name} in {Location}. Expertise in {specialties}.`

**Office/Facility:**
- Title: `{Business Name} Accounting Office - {Location}`
- ALT: `Professional accounting office at {Business Name} {Location}`
- Caption: `Full-service accounting firm in {Location}`
- Description: `{Business Name} accounting offices in {Location}. Professional tax preparation and financial services.`

**General/Business:**
- Title: `Accounting Services - {Business Name} {Location}`
- ALT: `Tax and accounting services at {Business Name} {Location}`
- Caption: `Professional accounting and tax preparation`
- Description: `Comprehensive accounting, tax, and bookkeeping services. {UVP}. CPA services in {location}.`

---

## 3. Business Consulting (`consulting`)

### Service Keywords
```php
'consulting' => [
    'default' => 'Strategic planning. Business growth consulting. Expert guidance.',
    'strategy' => 'Business strategy development. Market analysis. Growth planning.',
    'operations' => 'Operational efficiency. Process improvement. Performance optimization.'
]
```

### Customer Term
- **Client**

### Common Services
- Strategic planning
- Business analysis
- Process improvement
- Market research
- Change management

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, business consultant at {Business Name} {Location}`
- Caption: `{Name} - Senior Business Consultant`
- Description: `{Name} delivers strategic consulting services at {Business Name} in {Location}. Specializing in {focus areas}.`

**Office/Facility:**
- Title: `{Business Name} Consulting - {Location}`
- ALT: `Business consulting office at {Business Name} {Location}`
- Caption: `Professional consulting services in {Location}`
- Description: `{Business Name} consulting offices in {Location}. Strategic planning and business growth services.`

**General/Business:**
- Title: `Business Consulting - {Business Name} {Location}`
- ALT: `Strategic consulting services at {Business Name} {Location}`
- Caption: `Expert business consulting and strategy`
- Description: `Strategic business consulting and growth planning. {UVP}. Helping businesses in {service area} achieve their goals.`

---

## 4. Marketing Agency (`marketing`)

### Service Keywords
```php
'marketing' => [
    'default' => 'Digital marketing. Brand strategy. Results-driven campaigns.',
    'digital' => 'SEO and PPC. Social media marketing. Content strategy.',
    'creative' => 'Brand development. Creative campaigns. Visual storytelling.'
]
```

### Customer Term
- **Client**

### Common Services
- Digital marketing
- SEO/PPC
- Social media
- Brand strategy
- Content creation

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, marketing strategist at {Business Name} {Location}`
- Caption: `{Name} - Digital Marketing Expert`
- Description: `{Name} leads marketing initiatives at {Business Name} in {Location}. Expertise in {specialties}.`

**Office/Facility:**
- Title: `{Business Name} Marketing Agency - {Location}`
- ALT: `Creative marketing agency workspace at {Business Name} {Location}`
- Caption: `Modern marketing agency in {Location}`
- Description: `{Business Name} marketing offices in {Location}. Creative workspace for digital marketing and brand strategy.`

**General/Business:**
- Title: `Marketing Services - {Business Name} {Location}`
- ALT: `Digital marketing and branding at {Business Name} {Location}`
- Caption: `Full-service marketing agency`
- Description: `Comprehensive digital marketing and brand strategy services. {UVP}. Serving businesses in {service area}.`

---

## 5. Web Design / Development (`web_design`)

### Service Keywords
```php
'web_design' => [
    'default' => 'Custom websites. Responsive design. E-commerce solutions.',
    'design' => 'UI/UX design. Brand-focused websites. Mobile-first approach.',
    'development' => 'Custom development. WordPress experts. Performance optimization.'
]
```

### Customer Term
- **Client**

### Common Services
- Website design
- Web development
- E-commerce
- Maintenance
- SEO optimization

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, web developer at {Business Name} {Location}`
- Caption: `{Name} - Web Design & Development`
- Description: `{Name} creates custom websites at {Business Name} in {Location}. Specializing in {technologies/focus}.`

**Office/Facility:**
- Title: `{Business Name} Web Studio - {Location}`
- ALT: `Web design studio at {Business Name} {Location}`
- Caption: `Professional web design agency in {Location}`
- Description: `{Business Name} web design studio in {Location}. Custom website design and development services.`

**General/Business:**
- Title: `Web Design Services - {Business Name} {Location}`
- ALT: `Custom website design at {Business Name} {Location}`
- Caption: `Professional web design and development`
- Description: `Custom website design and development services. {UVP}. Serving businesses in {service area}.`

---

## 6. Plumbing (`plumbing`)

### Service Keywords
```php
'plumbing' => [
    'default' => 'Licensed plumbers. Emergency service. Warranty guaranteed.',
    'emergency' => '24/7 emergency plumbing. Fast response. Available weekends.',
    'installation' => 'Professional installation. Code compliant. Quality workmanship.'
]
```

### Customer Term
- **Customer**

### Common Services
- Emergency repairs
- Drain cleaning
- Fixture installation
- Pipe repair
- Water heater service

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, licensed plumber at {Business Name} {Location}`
- Caption: `{Name} - Master Plumber`
- Description: `{Name} provides professional plumbing services at {Business Name} in {Location}. Licensed and insured.`

**Equipment:**
- Title: `Professional Plumbing Equipment - {Business Name} {Location}`
- ALT: `Plumbing tools and equipment at {Business Name} {Location}`
- Caption: `State-of-the-art plumbing equipment`
- Description: `Professional plumbing tools and equipment. {Business Name} uses advanced technology for quality service in {location}.`

**General/Business:**
- Title: `Plumbing Services - {Business Name} {Location}`
- ALT: `Professional plumbing services at {Business Name} {Location}`
- Caption: `Licensed plumbing contractors`
- Description: `Expert plumbing installation and repair services. {UVP}. Licensed, insured plumbers serving {service area}.`

---

## 7. HVAC (`hvac`)

### Service Keywords
```php
'hvac' => [
    'default' => 'Licensed HVAC contractors. Energy-efficient systems. Maintenance plans.',
    'installation' => 'Professional installation. Energy Star certified. Warranty backed.',
    'repair' => 'Fast HVAC repair. 24/7 emergency service. Experienced technicians.'
]
```

### Customer Term
- **Customer**

### Common Services
- AC repair
- Heating repair
- Installation
- Maintenance
- Duct cleaning

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, HVAC technician at {Business Name} {Location}`
- Caption: `{Name} - Licensed HVAC Technician`
- Description: `{Name} provides heating and cooling services at {Business Name} in {Location}. EPA certified technician.`

**Equipment:**
- Title: `HVAC Equipment - {Business Name} {Location}`
- ALT: `Heating and cooling equipment at {Business Name} {Location}`
- Caption: `Professional HVAC equipment and tools`
- Description: `Advanced HVAC diagnostic and repair equipment. {Business Name} delivers quality heating and cooling service in {location}.`

**General/Business:**
- Title: `HVAC Services - {Business Name} {Location}`
- ALT: `Heating and cooling services at {Business Name} {Location}`
- Caption: `Professional HVAC installation and repair`
- Description: `Expert heating, ventilation, and air conditioning services. {UVP}. Licensed contractors serving {service area}.`

---

## 8. Electrical (`electrical`)

### Service Keywords
```php
'electrical' => [
    'default' => 'Licensed electricians. Code compliant work. Safety guaranteed.',
    'emergency' => '24/7 emergency electrical service. Fast response. Weekend availability.',
    'installation' => 'Professional electrical installation. Permit included. Warranty backed.'
]
```

### Customer Term
- **Customer**

### Common Services
- Electrical repair
- Panel upgrades
- Wiring installation
- Lighting installation
- Code compliance

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, licensed electrician at {Business Name} {Location}`
- Caption: `{Name} - Master Electrician`
- Description: `{Name} provides professional electrical services at {Business Name} in {Location}. Licensed and bonded electrician.`

**Equipment:**
- Title: `Electrical Equipment - {Business Name} {Location}`
- ALT: `Professional electrical tools at {Business Name} {Location}`
- Caption: `Advanced electrical testing equipment`
- Description: `Professional electrical diagnostic and repair equipment. {Business Name} ensures safe, code-compliant work in {location}.`

**General/Business:**
- Title: `Electrical Services - {Business Name} {Location}`
- ALT: `Licensed electrical services at {Business Name} {Location}`
- Caption: `Professional electrical contractors`
- Description: `Expert electrical installation and repair services. {UVP}. Licensed, insured electricians serving {service area}.`

---

## 9. Renovation / Construction (`renovation`)

### Service Keywords
```php
'renovation' => [
    'default' => 'Licensed contractors. Quality craftsmanship. Project management.',
    'remodeling' => 'Custom remodeling. Design-build services. Warranty guaranteed.',
    'construction' => 'New construction. Commercial and residential. Bonded and insured.'
]
```

### Customer Term
- **Client** / **Homeowner**

### Common Services
- Home remodeling
- Kitchen renovation
- Bathroom renovation
- Additions
- Commercial construction

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, contractor at {Business Name} {Location}`
- Caption: `{Name} - Licensed General Contractor`
- Description: `{Name} leads construction projects at {Business Name} in {Location}. Licensed general contractor with {years} experience.`

**Equipment:**
- Title: `Construction Equipment - {Business Name} {Location}`
- ALT: `Professional construction tools at {Business Name} {Location}`
- Caption: `Quality construction equipment and tools`
- Description: `Professional construction equipment and skilled craftsmanship. {Business Name} delivers quality renovations in {location}.`

**General/Business:**
- Title: `Renovation Services - {Business Name} {Location}`
- ALT: `Home renovation and construction at {Business Name} {Location}`
- Caption: `Professional renovation contractors`
- Description: `Expert home renovation and construction services. {UVP}. Licensed, bonded contractors serving {service area}.`

---

## 10. Dental (`dental`)

### Service Keywords
```php
'dental' => [
    'default' => 'Comprehensive dental care. Insurance accepted. Modern facility.',
    'preventive' => 'Preventive dentistry. Cleanings and exams. Oral health education.',
    'cosmetic' => 'Cosmetic dentistry. Smile makeovers. Advanced whitening.'
]
```

### Customer Term
- **Patient**

### Common Services
- Dental exams
- Cleanings
- Fillings
- Cosmetic dentistry
- Emergency care

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, dentist at {Business Name} {Location}`
- Caption: `{Name} - Doctor of Dental Surgery`
- Description: `{Name} provides comprehensive dental care at {Business Name} in {Location}. Board-certified dentist.`

**Office/Facility:**
- Title: `{Business Name} Dental Office - {Location}`
- ALT: `Modern dental clinic at {Business Name} {Location}`
- Caption: `State-of-the-art dental facility`
- Description: `{Business Name} dental offices in {Location}. Modern treatment rooms and advanced dental technology.`

**General/Business:**
- Title: `Dental Services - {Business Name} {Location}`
- ALT: `Comprehensive dental care at {Business Name} {Location}`
- Caption: `Professional dental services`
- Description: `Comprehensive dental care for the whole family. {UVP}. Accepting new patients in {service area}.`

---

## 11. Medical Practice (`medical`)

### Service Keywords
```php
'medical' => [
    'default' => 'Board-certified physicians. Comprehensive care. Insurance accepted.',
    'primary' => 'Primary care services. Preventive medicine. Chronic disease management.',
    'specialty' => 'Specialized medical care. Expert diagnosis. Advanced treatment.'
]
```

### Customer Term
- **Patient**

### Common Services
- Medical exams
- Diagnosis
- Treatment
- Preventive care
- Chronic care management

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, physician at {Business Name} {Location}`
- Caption: `{Name} - Board-Certified Physician`
- Description: `{Name} provides comprehensive medical care at {Business Name} in {Location}. Board-certified in {specialty}.`

**Office/Facility:**
- Title: `{Business Name} Medical Office - {Location}`
- ALT: `Modern medical clinic at {Business Name} {Location}`
- Caption: `State-of-the-art medical facility`
- Description: `{Business Name} medical offices in {Location}. Modern examination rooms and advanced medical equipment.`

**General/Business:**
- Title: `Medical Services - {Business Name} {Location}`
- ALT: `Comprehensive medical care at {Business Name} {Location}`
- Caption: `Professional medical services`
- Description: `Comprehensive medical care and treatment. {UVP}. Board-certified physicians serving {service area}.`

---

## 12. Therapy / Counseling (`therapy`)

### Service Keywords
```php
'therapy' => [
    'default' => 'Licensed therapists. Confidential counseling. Insurance accepted.',
    'individual' => 'Individual therapy. Evidence-based treatment. Personalized care.',
    'family' => 'Family counseling. Couples therapy. Relationship support.'
]
```

### Customer Term
- **Client** / **Patient**

### Common Services
- Individual therapy
- Couples counseling
- Family therapy
- Group sessions
- Telehealth

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, therapist at {Business Name} {Location}`
- Caption: `{Name} - Licensed Mental Health Counselor`
- Description: `{Name} provides counseling services at {Business Name} in {Location}. Licensed therapist specializing in {approaches}.`

**Office/Facility:**
- Title: `{Business Name} Counseling Center - {Location}`
- ALT: `Private therapy office at {Business Name} {Location}`
- Caption: `Confidential counseling space`
- Description: `{Business Name} therapy offices in {Location}. Private, comfortable spaces for individual and family counseling.`

**General/Business:**
- Title: `Therapy Services - {Business Name} {Location}`
- ALT: `Professional counseling at {Business Name} {Location}`
- Caption: `Licensed therapy and counseling`
- Description: `Professional mental health counseling and therapy services. {UVP}. Licensed therapists serving {service area}.`

---

## 13. Wellness / Alternative (`wellness`)

### Service Keywords
```php
'wellness' => [
    'default' => 'Holistic wellness. Natural treatments. Personalized care.',
    'spa' => 'Spa services. Relaxation therapies. Self-care treatments.',
    'alternative' => 'Alternative medicine. Natural healing. Integrative health.'
]
```

### Customer Term
- **Client**

### Common Services
- Massage
- Spa treatments
- Holistic health
- Beauty services
- Wellness coaching

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, wellness practitioner at {Business Name} {Location}`
- Caption: `{Name} - Wellness Specialist`
- Description: `{Name} provides wellness services at {Business Name} in {Location}. Certified in {modalities/specialties}.`

**Office/Facility:**
- Title: `{Business Name} Wellness Studio - {Location}`
- ALT: `Relaxing wellness space at {Business Name} {Location}`
- Caption: `Tranquil wellness environment`
- Description: `{Business Name} wellness studio in {Location}. Peaceful space for relaxation and holistic health services.`

**General/Business:**
- Title: `Wellness Services - {Business Name} {Location}`
- ALT: `Holistic wellness at {Business Name} {Location}`
- Caption: `Professional wellness and spa services`
- Description: `Holistic wellness and self-care services. {UVP}. Serving clients in {service area}.`

---

## 14. Online Store (`online_store`)

### Service Keywords
```php
'online_store' => [
    'default' => 'Fast shipping. Secure checkout. Satisfaction guaranteed.',
    'product' => 'Quality products. Competitive pricing. Customer reviews.',
    'service' => 'Expert support. Easy returns. Loyalty rewards.'
]
```

### Customer Term
- **Customer**

### Common Services
- Product sales
- Fast shipping
- Customer support
- Returns/exchanges
- Loyalty programs

### Metadata Templates

**Product Photo:**
- Title: `{Product Name} - {Business Name}`
- ALT: `{Product Name} available at {Business Name}`
- Caption: `{Product Name} - Premium quality`
- Description: `{Product description}. Available exclusively at {Business Name}. Fast shipping. {Return policy}.`

**Team Photo:**
- Title: `{Name} - {Business Name}`
- ALT: `{Name}, team member at {Business Name}`
- Caption: `{Name} - Customer Experience Specialist`
- Description: `{Name} helps customers find the perfect products at {Business Name}. Expert product knowledge and service.`

**General/Business:**
- Title: `{Product Category} - {Business Name}`
- ALT: `Shop {product category} at {Business Name}`
- Caption: `Premium {product category} selection`
- Description: `Shop our curated selection of {product category}. {UVP}. Fast, secure shipping.`

---

## 15. Local Retail (`local_retail`)

### Service Keywords
```php
'local_retail' => [
    'default' => 'Local shop. Curated selection. Personalized service.',
    'specialty' => 'Specialty items. Unique finds. Expert knowledge.',
    'service' => 'Personal shopping. Gift registry. Local delivery.'
]
```

### Customer Term
- **Customer**

### Common Services
- Product sales
- Personal shopping
- Gift registry
- Local delivery
- Special orders

### Metadata Templates

**Product Photo:**
- Title: `{Product Name} - {Business Name} {Location}`
- ALT: `{Product Name} at {Business Name} {Location}`
- Caption: `{Product Name} - Available in-store`
- Description: `{Product description}. Available at {Business Name} in {Location}. Visit our showroom.`

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, retail specialist at {Business Name} {Location}`
- Caption: `{Name} - Sales Associate`
- Description: `{Name} helps customers at {Business Name} in {Location}. Expert product knowledge and personalized service.`

**General/Business:**
- Title: `{Product Category} Store - {Business Name} {Location}`
- ALT: `Shop {product category} at {Business Name} {Location}`
- Caption: `Local {product category} retailer`
- Description: `Discover our selection of {product category} at {Business Name} in {Location}. {UVP}. Visit our store today.`

---

## 16. Specialty Products (`specialty`)

### Service Keywords
```php
'specialty' => [
    'default' => 'Specialty products. Expert curation. Quality guaranteed.',
    'custom' => 'Custom orders. Personalization available. Made to order.',
    'exclusive' => 'Exclusive items. Limited editions. Premium selection.'
]
```

### Customer Term
- **Client** / **Customer**

### Common Services
- Specialty products
- Custom orders
- Expert consultation
- Product education
- Exclusive items

### Metadata Templates

**Product Photo:**
- Title: `{Product Name} - {Business Name} {Location}`
- ALT: `Specialty {product name} at {Business Name} {Location}`
- Caption: `{Product Name} - Premium specialty item`
- Description: `{Product description}. Expertly curated by {Business Name} in {Location}. {Unique selling points}.`

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, product specialist at {Business Name} {Location}`
- Caption: `{Name} - Product Expert`
- Description: `{Name} provides expert product guidance at {Business Name} in {Location}. Specialized knowledge in {product category}.`

**General/Business:**
- Title: `Specialty {Category} - {Business Name} {Location}`
- ALT: `Specialty products at {Business Name} {Location}`
- Caption: `Premium specialty products`
- Description: `Curated selection of specialty {product category}. {UVP}. Expert consultation available in {location}.`

---

## 17. Other / Not Listed (`other`)

### Service Keywords
```php
'other' => [
    'default' => 'Professional services. Expert team. Customer focused.',
    'specialized' => 'Specialized expertise. Custom solutions. Quality service.',
    'service' => 'Personalized service. Attention to detail. Results driven.'
]
```

### Customer Term
- **Client**

### Common Services
- Custom/varies by business
- Professional services
- Consultation
- Project-based work

### Metadata Templates

**Team Photo:**
- Title: `{Name} - {Business Name} {Location}`
- ALT: `{Name}, professional at {Business Name} {Location}`
- Caption: `{Name} - Service Professional`
- Description: `{Name} provides expert services at {Business Name} in {Location}. Specialized in {focus area from onboarding}.`

**Office/Facility:**
- Title: `{Business Name} - {Location}`
- ALT: `Professional workspace at {Business Name} {Location}`
- Caption: `{Business Name} in {Location}`
- Description: `{Business Name} offices in {Location}. Professional environment for {service type}.`

**General/Business:**
- Title: `Services - {Business Name} {Location}`
- ALT: `Professional services at {Business Name} {Location}`
- Caption: `Expert professional services`
- Description: `{UVP from onboarding}. Serving {target audience} in {service area}. {Pain points addressed}.`

---

## Implementation Notes

### Dynamic Variable Mapping

All templates support these dynamic variables from onboarding context:

- `{Business Name}` → `$this->business_name`
- `{Location}` → `$this->location`
- `{Service Area}` → `$this->service_area`
- `{UVP}` → `$this->uvp`
- `{Pain Points}` → `$this->pain_points`
- `{Target Audience}` → `$this->target_audience`
- `{Brand Voice}` → `$this->brand_voice`

### Code Structure

Each industry requires the following wiring (already completed for the initial release; repeat if you add new ones):

1. **Keyword Map Entry** (in `$service_keyword_map` array) – only needed when adding new keyword groups.
2. **Generator Function** (`generate_{industry}_meta()`).
3. **Slug Prefix** (expand `get_industry_slug_prefix()` for deterministic slugs).
4. **Dispatcher Entry** (add to `generate_business_meta()` switch/map).
5. **Customer Term Mapping** (if customer label differs from default).

### Implementation Status

| Phase | Industries | Status | Notes |
| --- | --- | --- | --- |
| Phase 1 | Plumbing, HVAC, Electrical, Renovation | ✅ Implemented |
| Phase 2 | Legal, Accounting, Consulting, Marketing, Web Design | ✅ Implemented |
| Phase 3 | Online Store, Local Retail, Specialty | ✅ Implemented |
| Phase 4 | Medical, Dental, Therapy, Wellness | ✅ Implemented |
| Phase 5 | Other (generic fallback) | ✅ Implemented |
| Cross-cutting | Slug prefixes, descriptor sanitization, fallback helpers | ✅ Implemented |

---

## Testing Checklist

For each industry, test:

- [ ] Team member photo metadata
- [ ] Office/facility photo metadata
- [ ] Product/equipment photo metadata
- [ ] General business photo metadata
- [ ] Testimonial photo metadata
- [ ] Filename slug generation (generic filenames → `industry-services-<city>-<id>`)
- [ ] Professional services wording uses correct credential copy (legal/accounting/consulting/marketing/web design)
- [ ] Retail/e-commerce wording references shipping/curated language
- [ ] Wellness/healthcare wording only appears for medical/dental/therapy/wellness industries
- [ ] Location logic works correctly
- [ ] UVP / Pain Points / Audience fallbacks behave as expected

---

## Industry Snapshot (Current Implementation)

| Industry | Generator Function | Slug Prefix | Credential Copy Highlights |
| --- | --- | --- | --- |
| Legal | `generate_legal_meta()` | `legal-services` | “Licensed attorneys … confidential consultations.” |
| Accounting | `generate_accounting_meta()` | `accounting-services` | “Certified accounting professionals …” |
| Consulting | `generate_consulting_meta()` | `consulting-services` | “Strategic consultants delivering measurable growth.” |
| Marketing | `generate_marketing_meta()` | `marketing-services` | “Strategic marketers … measurable campaigns.” |
| Web Design | `generate_web_design_meta()` | `web-design-services` | “Website specialists delivering responsive, high-performing sites.” |
| Plumbing | `generate_plumbing_meta()` | `plumbing-services` | “Licensed, insured plumbers …” |
| HVAC | `generate_hvac_meta()` | `hvac-services` | “Licensed HVAC contractors …” |
| Electrical | `generate_electrical_meta()` | `electrical-services` | “Licensed, insured electricians …” |
| Renovation | `generate_renovation_meta()` | `renovation-services` | “Licensed, bonded contractors …” |
| Online Store | `generate_online_store_meta()` | Industry/brand fallback | “Fast shipping, secure checkout, satisfaction guaranteed.” |
| Local Retail | `generate_local_retail_meta()` | Industry/brand fallback | “Curated local retail products, personalized service.” |
| Specialty | `generate_specialty_meta()` | Industry/brand fallback | “Curated specialty products, expert guidance.” |
| Medical | `generate_medical_meta()` | Industry/brand fallback | “Board-certified physicians providing comprehensive care.” |
| Dental | `generate_dental_meta()` | Industry/brand fallback | “Licensed dentists providing comprehensive dental care.” |
| Therapy | `generate_therapy_meta()` | Industry/brand fallback | “Licensed therapists providing confidential support.” |
| Wellness | `generate_wellness_meta()` | Industry/brand fallback | “Certified wellness practitioners delivering personalized care.” |
| Other | `generate_other_meta()` | Brand/industry fallback | “Professional services tailored to your needs.” |

Slug prefixes automatically fall back to the industry label or business name when a preset is unavailable.

---

## Maintenance

When adding new industries:

1. Add to industry dropdown in `class-msh-context-helper.php` line 54-72
2. Add to `$service_keyword_map` array
3. Create `generate_{industry}_meta()` function
4. Add case to `generate_meta_fields()` switch
5. Add case to `generate_filename_slug()` switch
6. Add to customer term mapping
7. Update this documentation
8. Add test cases

---

**Document Created:** October 15, 2025
**Last Updated:** October 15, 2025
**Status:** ✅ Implementation complete; use this document to guide future additions
