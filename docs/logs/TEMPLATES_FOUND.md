# TinyDot Image Optimizer - Templates Catalog

**Document Purpose:** Comprehensive catalog of all hardcoded metadata templates and strings found in the codebase.

**Audit Date:** 2025-10-26
**Focus:** Non-AI mode only
**Primary File:** `includes/class-msh-image-optimizer.php` (9,963 lines)

---

## Table of Contents

1. [Overview](#overview)
2. [Scene-Based Templates](#scene-based-templates)
3. [Industry-Specific Templates](#industry-specific-templates)
4. [Starter Templates (Token-Based)](#starter-templates-token-based)
5. [Service Keyword Map](#service-keyword-map)
6. [Filename Patterns](#filename-patterns)
7. [Healthcare-Specific Strings](#healthcare-specific-strings-debt)
8. [Localization Status](#localization-status)

---

## Overview

### Template Distribution

| Category | Count | Hardcoded | Localized | Industry-Agnostic |
|----------|-------|-----------|-----------|-------------------|
| **Scene Templates** | 8 | ✅ Yes | ⚠️ Partial | ❌ No |
| **Industry Templates** | 15 | ✅ Yes | ⚠️ Partial | ❌ No |
| **Starter Templates** | 8 | ✅ Yes | ❌ No | ✅ Yes |
| **Service Keywords** | 12 | ✅ Yes | ❌ No | ❌ No (medical only) |
| **Filename Patterns** | 100+ | ✅ Yes | N/A | ⚠️ Mixed |

**Total Hardcoded Strings:** ~500+ unique template strings

---

## Scene-Based Templates

All methods in `class-msh-image-optimizer.php`.

### 1. Clinical / Service Highlight

**Method:** `generate_clinical_meta()`
**File:Line:** `class-msh-image-optimizer.php:2396-2448`
**Used For:** Treatment/service images

**Title Pattern:**
```php
"{Service Type} Treatment – {Business Name} {Location}"
```

**Examples:**
- "Physiotherapy Treatment – Main Street Health Toronto"
- "Chiropractic Treatment – Wellness Center Austin"

**Alt Text Pattern:**
```php
"{service_label} treatment at {business_name} in {location}"
```

**Caption Pattern:**
```php
"{service_label} treatment for {benefit} at {business_name}"
```

**Description Pattern:**
```php
"Professional {service_label} treatment{location_phrase}. {keywords} {credentials}"
```

**Hardcoded Keywords** (from `get_service_keyword_line()`):

| Service | Default Variant | Assessment Variant | Acute Variant |
|---------|----------------|-------------------|---------------|
| physiotherapy | "WSIB approved. MVA recovery. First responder programs." | "Functional assessments. Return-to-work evaluation." | "Immediate injury care. Same-day appointments available." |
| chiropractic | "Spinal care. Workplace injury treatment. WSIB claims supported." | "Spinal assessment and posture evaluation services." | "Acute back and neck pain management with direct billing." |
| massage | "Registered massage therapy. Insurance coverage available." | "Musculoskeletal assessment and soft tissue release." | "Pain relief for muscle strain and injury recovery." |

⚠️ **All healthcare-specific!**

---

### 2. Team Member

**Method:** `generate_team_meta()`
**File:Line:** `class-msh-image-optimizer.php:2470-2539`
**Used For:** Staff photos, headshots

**Title Pattern:**
```php
"{name} – {business_name} Team"
```

**Examples:**
- "Sarah Johnson – Main Street Health Team"
- "Dr. Michael Chen – Wellness Clinic Team"

**Alt Text Pattern:**
```php
"{name} from the {business_name} team in {location}"
```

**Caption Patterns:**

If has credentials:
```php
"Team member at {business_name}"
```

Healthcare-specific caption:
```php
"{title} providing {service} at {business_name}"
```

Examples:
- "Registered Physiotherapist providing rehabilitation at Main Street Health"
- "Chiropractor providing spinal care at Toronto Wellness"

Generic caption:
```php
"Professional team member at {business_name}"
```

**Description Pattern:**
```php
"{name} is a valued member of the {business_name} team{location_phrase}. {credentials_or_specialty}"
```

**Fallbacks:**

If no name extracted:
```php
'title' => "{business_name} Team Member"
'alt_text' => "Professional team member at {business_name}"
```

---

### 3. Testimonial

**Method:** `generate_testimonial_meta()`
**File:Line:** `class-msh-image-optimizer.php:2540-2616`
**Used For:** Customer/patient testimonials

**Title Pattern:**
```php
"Client Testimonial – {subject_descriptor}"
```

Healthcare variant:
```php
"Patient Testimonial – {subject_descriptor}"
```

**Alt Text Pattern:**
```php
"Testimonial from {prefix} at {business_name}"
```

Where `{prefix}` = "patient" (healthcare) or "client" (other)

**Caption Pattern:**
```php
"Real {patient|client} experience with {business_name} services"
```

**Description Patterns:**

Healthcare:
```php
"Testimonial from a {business_name} patient highlighting their treatment experience{location_phrase}. {uvp}"
```

Generic:
```php
"Client testimonial highlighting the quality of service at {business_name}{location_phrase}. {uvp}"
```

**Hardcoded Strings:**
- Line 2562: `"Client Testimonial"` / `"Patient Testimonial"`
- Line 2571: `"patient"` vs `"client"`
- Line 2579: `"Real patient experience"` / `"Real client experience"`
- Line 2588: `"Testimonial from a {business_name} patient"`

---

### 4. Service Icon

**Method:** `generate_service_icon_meta()`
**File:Line:** `class-msh-image-optimizer.php:2617-2679`
**Used For:** Icons, graphics, service badges

**Title Pattern:**
```php
"{concept} Icon"
```

Examples:
- "Physiotherapy Icon"
- "Massage Therapy Icon"
- "Legal Services Icon"

**Alt Text Pattern:**
```php
"Icon representing {concept}"
```

**Caption Pattern:**
```php
"Visual icon for {concept}"
```

**Description Pattern:**

Healthcare:
```php
"{concept_label} service icon for {business_name}{location_phrase}"
```

Generic:
```php
"Icon representing {concept_label} services at {business_name}"
```

**Hardcoded Strings:**
- Line 2644: `"Icon representing"`
- Line 2652: `"Visual icon for"`
- Line 2662: `"service icon for"`

---

### 5. Logo

**Method:** `generate_logo_meta()`
**File:Line:** `class-msh-image-optimizer.php:2680-2719`
**Used For:** Company logos

**Title Pattern:**
```php
"{business_name} Logo"
```

**Alt Text Pattern:**
```php
"{business_name} official logo"
```

**Caption Pattern:**
```php
"Official {business_name} branding"
```

**Description Pattern:**
```php
"The official logo for {business_name}, representing {uvp_or_default}"
```

Default UVP:
```php
"professional {industry_label} services{location_phrase}"
```

**Hardcoded Strings:**
- Line 2687: `"Logo"`
- Line 2688: `"official logo"`
- Line 2689: `"Official {business_name} branding"`
- Line 2692: `"The official logo for {business_name}"`

---

### 6. Product

**Method:** `generate_product_meta()`
**File:Line:** `class-msh-image-optimizer.php:2720-2833`
**Used For:** Product photos, equipment shots

**Title Patterns:**

Generic product:
```php
"{product_type}"
```

With business name:
```php
"{product_type} – {business_name}"
```

Healthcare equipment:
```php
"Rehabilitation Equipment – {business_name}"
```

**Alt Text Patterns:**

Healthcare:
```php
"{product_label} rehabilitation equipment at {business_name}"
```

Generic:
```php
"{product_label} available at {business_name}"
```

**Caption Patterns:**

Healthcare:
```php
"Professional {product_label} for patient care"
```

Generic:
```php
"{product_label} product"
```

**Description Patterns:**

Healthcare with product type:
```php
"{product_label} used in professional rehabilitation programs at {business_name}{location_phrase}. {credentials}"
```

Generic:
```php
"{product_label} available from {business_name}{location_phrase}. {uvp}"
```

**Product Type Map:**

| Key | Label |
|-----|-------|
| therapeutic-pillow | "Therapeutic pillow" |
| custom-orthotics | "Custom orthotics" |
| support-brace | "Support brace" |
| support-product | "Therapeutic support product" |
| tens-unit | "TENS unit" |
| pain-relief | "Pain relief product" |
| compression-therapy | "Compression therapy product" |

⚠️ **All healthcare-specific product types!**

---

### 7. Facility / Workspace

**Method:** `generate_facility_meta()`
**File:Line:** `class-msh-image-optimizer.php:2834-2880`
**Used For:** Building interior/exterior photos

**Healthcare Version:**

**Title:**
```php
"{business_name} Clinic - {location} Rehabilitation Facility"
```

**Alt Text:**
```php
"Interior view of {business_name} rehabilitation clinic in {location}"
```

**Caption:**
```php
"Modern rehabilitation facility at {business_name} {location}"
```

**Description:**
```php
"Modern rehabilitation facility at {business_name} {location}. Professional physiotherapy and chiropractic clinic with specialized treatment rooms and WSIB approved programs."
```

⚠️ **Hardcoded:** "rehabilitation clinic", "Professional physiotherapy and chiropractic clinic", "WSIB approved programs"

**Generic Version:**

**Title:**
```php
"{business_name} Workspace – {location}"
```

Or if no business name:
```php
"Workspace – {location}"
```

**Alt Text:**
```php
"Interior view of {business_name} in {location}"
```

**Caption:**
```php
"Collaborative space for {industry_label} team members."
```

**Description:**
```php
"The {business_name} workspace in {location} designed for {industry_label} collaboration. {uvp} {target_audience} {cta}"
```

---

### 8. Equipment

**Method:** `generate_equipment_meta()`
**File:Line:** `class-msh-image-optimizer.php:2882-2932`
**Used For:** Equipment photos

**Healthcare Version:**

**Title:**
```php
"Rehabilitation Equipment – {business_name}"
```

**Alt Text:**
```php
"Professional rehabilitation equipment at {business_name} in {location}"
```

**Caption:**
```php
"Advanced rehabilitation technology"
```

**Description:**
```php
"Professional rehabilitation equipment used for patient care at {business_name}{location_phrase}. WSIB approved programs with modern therapeutic technology."
```

⚠️ **Hardcoded:** "Rehabilitation Equipment", "rehabilitation equipment", "WSIB approved programs"

**Generic Version:**

**Title:**
```php
"{descriptor} Equipment – {business_name}"
```

**Alt Text:**
```php
"Professional equipment at {business_name}"
```

**Caption:**
```php
"{industry_label} equipment and tools"
```

**Description:**
```php
"Professional equipment for {industry_label} services at {business_name}{location_phrase}. {uvp}"
```

---

## Industry-Specific Templates

All methods follow pattern: `generate_{industry}_meta($context, $descriptor = '')`

### 1. Medical Practice

**Method:** `generate_medical_meta()`
**File:Line:** `class-msh-image-optimizer.php:3509-3555`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or generic:
```php
"Medical Services – {business_name} | {location}"
```

**Alt Text:**
```php
"{descriptor} at {business_name} in {location}"
```

Or:
```php
"Medical services at {business_name} in {location}"
```

**Caption:**
```php
"Comprehensive medical care"
```

**Description:**
```php
"Comprehensive medical care and treatment{location_phrase}. Board-certified physicians serving {service_area}."
```

Or:
```php
"Comprehensive medical care and treatment{location_phrase}. Board-certified physicians providing trusted care."
```

**Hardcoded Strings:**
- "Medical Practice" (fallback business name)
- "Medical Services"
- "Comprehensive medical care"
- "Board-certified physicians"
- "providing trusted care"

---

### 2. Dental Clinic

**Method:** `generate_dental_meta()`
**File:Line:** `class-msh-image-optimizer.php:3557-3603`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Dental Services – {business_name} | {location}"
```

**Caption:**
```php
"Comprehensive dental care"
```

**Description:**
```php
"Comprehensive dental care for the whole family{location_phrase}. Licensed dentists serving {service_area}."
```

Or:
```php
"Comprehensive dental care for the whole family{location_phrase}. Licensed dentists providing family-friendly care."
```

**Hardcoded Strings:**
- "Dental Clinic" (fallback)
- "Dental Services"
- "Comprehensive dental care"
- "for the whole family"
- "Licensed dentists"
- "family-friendly care"

---

### 3. Therapy / Counseling

**Method:** `generate_therapy_meta()`
**File:Line:** `class-msh-image-optimizer.php:3605-3651`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Therapy Services – {business_name} | {location}"
```

**Caption:**
```php
"Compassionate counseling and therapy"
```

**Description:**
```php
"Compassionate therapy and counseling services{location_phrase}. Licensed therapists serving {service_area}."
```

**Hardcoded Strings:**
- "Therapy Practice" (fallback)
- "Therapy Services"
- "Compassionate counseling and therapy"
- "Licensed therapists"
- "professional mental health support"

---

### 4. Wellness / Alternative

**Method:** `generate_wellness_meta()`
**File:Line:** `class-msh-image-optimizer.php:3653-3699`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Wellness Services – {business_name} | {location}"
```

**Caption:**
```php
"Holistic health and wellness"
```

**Description:**
```php
"Holistic wellness and alternative health services{location_phrase}. Certified practitioners serving {service_area}."
```

**Hardcoded Strings:**
- "Wellness Center" (fallback)
- "Wellness Services"
- "Holistic health and wellness"
- "holistic approach to health"
- "Certified practitioners"

---

### 5. Legal Services

**Method:** `generate_legal_meta()`
**File:Line:** `class-msh-image-optimizer.php:3125-3171`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Legal Services – {business_name} | {location}"
```

**Caption:**
```php
"Professional legal services"
```

**Description:**
```php
"Professional legal services and representation{location_phrase}. Licensed attorney serving {service_area}."
```

Or:
```php
"Trusted legal counsel and representation{location_phrase}."
```

**Hardcoded Strings:**
- "Law Firm" (fallback)
- "Legal Services"
- "Professional legal services"
- "Licensed attorney"
- "Trusted legal counsel and representation"

---

### 6. Accounting & Tax

**Method:** `generate_accounting_meta()`
**File:Line:** `class-msh-image-optimizer.php:3173-3219`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Accounting Services – {business_name} | {location}"
```

**Caption:**
```php
"Professional accounting and tax services"
```

**Description:**
```php
"Professional accounting and tax services{location_phrase}. Certified accountants serving {service_area}."
```

Or:
```php
"Expert tax preparation and financial planning{location_phrase}."
```

**Hardcoded Strings:**
- "Accounting Firm" (fallback)
- "Accounting Services"
- "Professional accounting and tax services"
- "Certified accountants"
- "Expert tax preparation and financial planning"

---

### 7. Business Consulting

**Method:** `generate_consulting_meta()`
**File:Line:** `class-msh-image-optimizer.php:3221-3267`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Consulting Services – {business_name} | {location}"
```

**Caption:**
```php
"Strategic business consulting"
```

**Description:**
```php
"Strategic business consulting and advisory services{location_phrase}. Expert consultants serving {service_area}."
```

**Hardcoded Strings:**
- "Consulting Firm" (fallback)
- "Consulting Services"
- "Strategic business consulting"
- "Expert consultants"
- "professional business guidance"

---

### 8. Marketing Agency

**Method:** `generate_marketing_meta()`
**File:Line:** `class-msh-image-optimizer.php:3269-3315`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Marketing Services – {business_name} | {location}"
```

**Caption:**
```php
"Creative marketing solutions"
```

**Description:**
```php
"Creative marketing and advertising services{location_phrase}. Digital marketing strategies for {service_area} businesses."
```

**Hardcoded Strings:**
- "Marketing Agency" (fallback)
- "Marketing Services"
- "Creative marketing solutions"
- "Digital marketing strategies"
- "creative campaigns and brand strategy"

---

### 9. Web Design / Development

**Method:** `generate_web_design_meta()`
**File:Line:** `class-msh-image-optimizer.php:3317-3363`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Web Design Services – {business_name} | {location}"
```

**Caption:**
```php
"Custom web design and development"
```

**Description:**
```php
"Custom web design and development services{location_phrase}. Professional websites for {service_area} businesses."
```

**Hardcoded Strings:**
- "Web Design Studio" (fallback)
- "Web Design Services"
- "Custom web design and development"
- "Professional websites"

---

### 10. Plumbing

**Method:** `generate_plumbing_meta()`
**File:Line:** `class-msh-image-optimizer.php:2933-2980`

**Title:**
```php
"{descriptor} – {business_name} Plumbing | {location}"
```

Or:
```php
"Plumbing Services – {business_name} | {location}"
```

**Caption:**
```php
"Professional plumbing services"
```

**Description:**
```php
"Professional plumbing services{location_phrase}. Licensed plumber serving {service_area}."
```

Or:
```php
"Expert plumbing services and repairs{location_phrase}."
```

**Hardcoded Strings:**
- "Plumbing" (fallback)
- "Plumbing Services"
- "Professional plumbing services"
- "Licensed plumber"
- "Expert plumbing services and repairs"

---

### 11. HVAC

**Method:** `generate_hvac_meta()`
**File:Line:** `class-msh-image-optimizer.php:2981-3028`

**Title:**
```php
"{descriptor} – {business_name} HVAC | {location}"
```

Or:
```php
"HVAC Services – {business_name} | {location}"
```

**Caption:**
```php
"Professional HVAC services"
```

**Description:**
```php
"Professional heating and cooling services{location_phrase}. Licensed HVAC contractor serving {service_area}."
```

**Hardcoded Strings:**
- "HVAC" (fallback)
- "HVAC Services"
- "Professional HVAC services"
- "Professional heating and cooling services"
- "Licensed HVAC contractor"

---

### 12. Electrical

**Method:** `generate_electrical_meta()`
**File:Line:** `class-msh-image-optimizer.php:3029-3076`

**Title:**
```php
"{descriptor} – {business_name} Electrical | {location}"
```

Or:
```php
"Electrical Services – {business_name} | {location}"
```

**Caption:**
```php
"Professional electrical services"
```

**Description:**
```php
"Professional electrical services{location_phrase}. Licensed electrician serving {service_area}."
```

**Hardcoded Strings:**
- "Electrical" (fallback)
- "Electrical Services"
- "Professional electrical services"
- "Licensed electrician"

---

### 13. Renovation / Construction

**Method:** `generate_renovation_meta()`
**File:Line:** `class-msh-image-optimizer.php:3077-3123`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Renovation Services – {business_name} | {location}"
```

**Caption:**
```php
"Professional renovation and construction"
```

**Description:**
```php
"Professional renovation and construction services{location_phrase}. Licensed contractor serving {service_area}."
```

**Hardcoded Strings:**
- "Renovation & Construction" (fallback)
- "Renovation Services"
- "Professional renovation and construction"
- "Licensed contractor"
- "quality craftsmanship"

---

### 14. Online Store / E-commerce

**Method:** `generate_online_store_meta()`
**File:Line:** `class-msh-image-optimizer.php:3365-3411`

**Title:**
```php
"{descriptor} – {business_name}"
```

Or:
```php
"Products – {business_name}"
```

**Caption:**
```php
"Shop our collection"
```

**Description:**
```php
"Browse our curated collection of products at {business_name}. {uvp}"
```

**Hardcoded Strings:**
- "Online Store" (fallback)
- "Products"
- "Shop our collection"
- "Browse our curated collection"

---

### 15. Local Retail

**Method:** `generate_local_retail_meta()`
**File:Line:** `class-msh-image-optimizer.php:3413-3459`

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"Retail – {business_name} | {location}"
```

**Caption:**
```php
"Visit our store"
```

**Description:**
```php
"Visit our {location} location to browse our collection. {uvp}"
```

**Hardcoded Strings:**
- "Retail Store" (fallback)
- "Retail"
- "Visit our store"
- "Visit our {location} location"

---

### 16. Specialty Products

**Method:** `generate_specialty_meta()`
**File:Line:** `class-msh-image-optimizer.php:3461-3507`

**Title:**
```php
"{descriptor} – {business_name}"
```

Or:
```php
"Specialty Products – {business_name}"
```

**Caption:**
```php
"Unique specialty products"
```

**Description:**
```php
"Discover specialty products from {business_name}. {uvp}"
```

**Hardcoded Strings:**
- "Specialty Shop" (fallback)
- "Specialty Products"
- "Unique specialty products"
- "Discover specialty products"

---

### 17. Business / Generic (Fallback)

**Method:** `generate_business_meta()`
**File:Line:** `class-msh-image-optimizer.php:3685-3778`

This is the **most flexible** template with minimal hardcoded strings.

**Title:**
```php
"{descriptor} – {business_name} | {location}"
```

Or:
```php
"{industry_label} – {business_name} | {location}"
```

**Alt Text:**
```php
"{descriptor} at {business_name} in {location}"
```

**Caption:**
```php
"{descriptor_label}"
```

**Description:**
Built using `build_industry_description()` [line 942]:
```php
"{generic_text}{location_phrase}. {credentials_or_uvp} {target_audience} {cta}"
```

**Minimal Hardcoded Strings:**
- "business" (fallback if no business name)
- Location phrases constructed dynamically

⚠️ **Note:** This is the closest to industry-agnostic template!

---

## Starter Templates (Token-Based)

**File:** `includes/data/starter-templates.php`
**Lines:** 1-200
**Count:** 8 templates (6 active, 2 shadow)

These templates use **token matching logic** instead of hardcoded scene types.

### Template #1: Clinic/Office Exterior

**Required Tokens:** `['exterior', 'building']`
**Negative Tokens:** `['interior', 'room', 'inside']`
**Nice-to-Have:** `['clinic', 'office', 'medical', 'entrance']`

**Templates:**
```
Title: "{entity} exterior"
Alt: "Exterior view of {entity}"
Caption: "The exterior of {entity}."
Description: "Professional photograph showing the exterior facade and entrance of {entity}."
```

**Priority:** 100 (highest)

---

### Template #2: Office/Clinic Interior

**Required Tokens:** `['interior']`
**Negative Tokens:** `['exterior', 'outside', 'facade']`
**Nice-to-Have:** `['room', 'office', 'lobby', 'reception', 'clinic', 'waiting']`

**Templates:**
```
Title: "{entity} interior"
Alt: "Interior view of {entity}"
Caption: "The interior space at {entity}."
Description: "Professional photograph showing the interior space and facilities at {entity}."
```

**Priority:** 95

---

### Template #3: Team Group Photo

**Required Tokens:** `['team', 'people']`
**Negative Tokens:** `['logo', 'screenshot', 'portrait', 'headshot', 'individual']`
**Nice-to-Have:** `['group', 'staff', 'employees', 'doctors', 'professionals']`

**Templates:**
```
Title: "{entity} team"
Alt: "Team members at {entity}"
Caption: "Professional team photo featuring members of {entity}."
Description: "Group photograph of the professional team at {entity}, showcasing the people behind the organization."
```

**Priority:** 90

**Note:** Negative filters prevent individual portraits/headshots.

---

### Template #4: Product on White Background

**Required Tokens:** `['white', 'background']`
**Negative Tokens:** `['person', 'team', 'headshot', 'landscape']`
**Nice-to-Have:** `['product', 'isolated', 'studio']`

**Templates:**
```
Title: "{subject}"
Alt: "{subject} on white background"
Caption: "Product photography of {subject}."
Description: "Professional product photograph of {subject} on white background for e-commerce and marketing use."
```

**Priority:** 85

---

### Template #5: Equipment/Tool Close-up

**Required Tokens:** `['equipment', 'close-up']`
**Negative Tokens:** `['person', 'landscape', 'exterior']`
**Nice-to-Have:** `['tool', 'device', 'machine', 'medical', 'professional']`

**Templates:**
```
Title: "{subject} equipment"
Alt: "Close-up of {subject} equipment"
Caption: "Professional equipment used at {entity}."
Description: "Detailed photograph of {subject} equipment used for professional services at {entity}."
```

**Priority:** 80

---

### Template #6: Landscape/Location Shot

**Required Tokens:** `['landscape', 'outdoor']`
**Negative Tokens:** `['person', 'portrait', 'product', 'interior']`
**Nice-to-Have:** `['nature', 'scenery', 'location', 'view']`

**Templates:**
```
Title: "{location} landscape"
Alt: "Landscape view of {location}"
Caption: "Scenic view of {location}."
Description: "Professional landscape photograph showcasing {location}."
```

**Priority:** 75

**Status:** Active

---

### Template #7: Individual Portrait (Shadow Mode)

**Required Tokens:** `['portrait', 'headshot']`
**Negative Tokens:** `['group', 'team', 'multiple', 'landscape']`
**Nice-to-Have:** `['professional', 'person', 'individual']`

**Templates:**
```
Title: "Professional portrait"
Alt: "Professional headshot"
Caption: "Professional portrait photograph."
Description: "Professional portrait photograph suitable for business profiles and marketing materials."
```

**Priority:** 60
**Mode:** Shadow (logs matches but doesn't apply)

---

### Template #8: Generic Service Image (Shadow Mode)

**Required Tokens:** None (fallback)
**Negative Tokens:** None

**Templates:**
```
Title: "{entity} services"
Alt: "Services at {entity}"
Caption: "Professional services provided by {entity}."
Description: "Photograph showcasing professional services at {entity}."
```

**Priority:** 10 (lowest)
**Mode:** Shadow

---

## Service Keyword Map

**File:** `class-msh-image-optimizer.php:62-109`
**Property:** `$service_keyword_map`

This array defines **healthcare-specific keywords** for 12 service types with 3 variants each (default, assessment, acute).

### Service: Physiotherapy

**Slugs:** `physiotherapy`, `physical-therapy`, `physio`

| Variant | Keywords |
|---------|----------|
| default | "WSIB approved. MVA recovery. First responder programs." |
| assessment | "Functional assessments. Return-to-work evaluation." |
| acute | "Immediate injury care. Same-day appointments available." |

---

### Service: Chiropractic

**Slugs:** `chiropractic`, `chiropractor`

| Variant | Keywords |
|---------|----------|
| default | "Spinal care. Workplace injury treatment. WSIB claims supported." |
| assessment | "Spinal assessment and posture evaluation services." |
| acute | "Acute back and neck pain management with direct billing." |

---

### Service: Massage

**Slugs:** `massage`, `massage-therapy`, `rmt`

| Variant | Keywords |
|---------|----------|
| default | "Registered massage therapy. Insurance coverage available." |
| assessment | "Musculoskeletal assessment and soft tissue release." |
| acute | "Pain relief for muscle strain and injury recovery." |

---

### Service: Acupuncture

**Slugs:** `acupuncture`

| Variant | Keywords |
|---------|----------|
| default | "Evidence-based acupuncture care. WSIB approved provider." |
| assessment | "Assessment-driven acupuncture plans for recovery." |
| acute | "Immediate relief protocols for pain and inflammation." |

---

### Service: Rehabilitation

**Slugs:** `rehabilitation`, `rehab`

| Variant | Keywords |
|---------|----------|
| default | "Return-to-work programs. WSIB approved. Direct billing." |
| assessment | "Functional capacity assessments and workplace evaluations." |
| acute | "Comprehensive rehabilitation for acute injuries." |

---

### Service: Motor Vehicle Accident

**Slugs:** `motor-vehicle-accident`, `mva`, `auto-accident`

| Variant | Keywords |
|---------|----------|
| default | "MVA rehabilitation with insurance coordination and direct billing." |
| assessment | "Comprehensive post-collision assessments and recovery plans." |
| acute | "Immediate collision injury support with medical-legal documentation." |

---

### Service: Workplace Injury

**Slugs:** `workplace-injury`, `wsib`, `work-injury`

| Variant | Keywords |
|---------|----------|
| default | "WSIB workplace injury rehabilitation with return-to-work planning." |
| assessment | "Workplace functional assessments and ergonomic planning." |
| acute | "Rapid workplace injury care with WSIB reporting support." |

---

### Service: First Responder

**Slugs:** `first-responder`, `first-responders`

| Variant | Keywords |
|---------|----------|
| default | "Dedicated first responder rehabilitation programs with duty-ready focus." |
| assessment | "Operational fitness assessments for first responders." |
| acute | "Priority injury care for first responders with fast-track scheduling." |

---

### Service: Chronic Pain

**Slugs:** `chronic-pain`, `pain-management`

| Variant | Keywords |
|---------|----------|
| default | "Evidence-based chronic pain management and rehabilitation programs." |
| assessment | "Comprehensive pain assessments and treatment planning." |
| acute | "Immediate pain relief protocols with long-term management plans." |

---

### Service: Sports Injury

**Slugs:** `sports-injury`, `sports-rehab`, `athletic-injury`

| Variant | Keywords |
|---------|----------|
| default | "Sports injury rehabilitation with return-to-play protocols." |
| assessment | "Athletic performance and injury risk assessments." |
| acute | "Rapid sports injury treatment with competitive recovery focus." |

---

### Service: Shockwave Therapy

**Slugs:** `shockwave`, `shockwave-therapy`

| Variant | Keywords |
|---------|----------|
| default | "Advanced shockwave therapy for chronic pain and injury recovery." |
| assessment | "Shockwave therapy assessment and treatment planning." |
| acute | "Shockwave treatment for acute pain and inflammation reduction." |

---

### Service: Pelvic Health

**Slugs:** `pelvic-health`, `pelvic-floor`

| Variant | Keywords |
|---------|----------|
| default | "Specialized pelvic health physiotherapy with privacy and respect." |
| assessment | "Comprehensive pelvic floor assessments and treatment plans." |
| acute | "Pelvic pain management with evidence-based protocols." |

---

### Service: Vestibular

**Slugs:** `vestibular`, `concussion`, `dizziness`

| Variant | Keywords |
|---------|----------|
| default | "Vestibular rehabilitation for balance and concussion recovery." |
| assessment | "Balance and vestibular system assessments." |
| acute | "Concussion management and rapid vestibular treatment." |

---

⚠️ **ALL SERVICE KEYWORDS ARE HEALTHCARE-SPECIFIC!**

This entire map is **medical industry debt** and needs replacement with industry-agnostic service definitions.

---

## Filename Patterns

### Treatment Keywords (Clinical)

**File:Line:** `class-msh-image-optimizer.php:2252-2273`
**Used in:** `generate_filename_slug()` for clinical type

| Slug | Matching Keywords |
|------|-------------------|
| auto-accident | auto accident, car accident, motor vehicle accident, mva |
| workplace-injury | workplace injury, work injury, ergonomic injury |
| sports-injury | sports injury, athletic injury, athlete injury |
| concussion | concussion, head injury, brain injury |
| sciatica | sciatica, sciatic nerve, leg pain |
| back-pain | back pain, spine pain, spinal pain, lumbar pain |
| neck-pain | neck pain, cervical pain, whiplash |
| knee-pain | knee pain, knee injury, meniscus |
| shoulder-pain | shoulder pain, rotator cuff, shoulder injury |
| hip-pain | hip pain, hip injury |
| physiotherapy | physiotherapy, physical therapy, physio |
| chiropractic | chiropractic, chiropractor, spinal adjustment |
| massage | massage, massage therapy, therapeutic massage, rmt |
| acupuncture | acupuncture, tcm, traditional chinese medicine |
| rehabilitation | rehabilitation, rehab, recovery program |
| shockwave | shockwave, shockwave therapy, extracorporeal |
| pelvic-health | pelvic health, pelvic floor, pelvic physiotherapy |
| vestibular | vestibular, concussion, balance therapy, dizziness |

⚠️ **18 treatment patterns, all healthcare-specific**

---

### Icon Patterns

**File:Line:** `class-msh-image-optimizer.php:1862-1918`
**Used in:** `detect_icon_context()`

**Icon Filenames:**
```
massage-icon, physiotherapy-icon, chiropractic-icon, acupuncture-icon,
rehabilitation-icon, mvac-icon, wsib-icon, assessment-icon, treatment-icon,
program-icon, service-icon, facility-icon, team-icon, testimonial-icon,
equipment-icon, product-icon, logo-icon
```

**Category Classification:**

| Category | Keywords |
|----------|----------|
| service | massage, physiotherapy, chiropractic, acupuncture, rehabilitation |
| condition | mvac, wsib, concussion, sports-injury, chronic-pain |
| program | assessment, treatment, first-responder, workplace-injury |
| team | team, staff, doctor, therapist |

---

### Product Patterns

**File:Line:** `class-msh-image-optimizer.php:1802-1821`
**Used in:** `detect_product_context()`

| Pattern | Product Category |
|---------|-----------------|
| white-background | product |
| product-shot | product |
| pillow | therapeutic-pillow |
| orthotics | custom-orthotics |
| brace | support-brace |
| support | support-product |
| tens-unit | tens-unit |
| pain-relief | pain-relief |
| compression | compression-therapy |
| foam-roller | foam-roller |
| exercise-band | exercise-band |
| therapy-ball | therapy-ball |
| heat-pack | heat-pack |
| ice-pack | ice-pack |
| kinesio-tape | kinesio-tape |

⚠️ **15 product patterns, all healthcare/rehabilitation-specific**

---

### Camera Sequence Patterns

**File:Line:** `class-msh-image-optimizer.php:5063-5077`
**Used in:** `looks_like_camera_filename()`

**Patterns:**
```regex
/^(img|dsc|dcim)[-_]?\d{3,5}$/i
/^[a-z]{2,4}\d{4,6}$/i
/^\d{8}[-_]\d{6}$/
```

**Examples:**
- `IMG_1234.jpg` → detected, sequence extracted: `1234`
- `DSC0056.jpg` → detected, sequence extracted: `0056`
- `20231015_143022.jpg` → detected as timestamp

**Handling:**
- Sequence preserved if high quality keywords found
- Otherwise, sequence removed from slug

---

## Healthcare-Specific Strings (Debt)

**All strings that need removal or parameterization for industry-agnostic plugin:**

### Acronyms & Regional Terms

| Term | Count | Locations | Meaning |
|------|-------|-----------|---------|
| WSIB | 12+ | Throughout clinical/facility templates | Workplace Safety and Insurance Board (Ontario, Canada) |
| MVA | 8+ | Service keywords, clinical templates | Motor Vehicle Accident |
| RMT | 2+ | Service keywords | Registered Massage Therapist |

### Service-Specific

| Term | Count | Locations |
|------|-------|-----------|
| rehabilitation | 40+ | Facility, equipment, clinical templates |
| physiotherapy | 20+ | Clinical templates, service keywords |
| chiropractic | 15+ | Clinical templates, service keywords |
| massage therapy | 10+ | Service keywords |
| acupuncture | 8+ | Service keywords |
| clinic | 15+ | Facility, medical templates |
| patient | 20+ | Testimonial, clinical templates |
| treatment | 30+ | Clinical templates, keywords |

### Professional Credentials

| Term | Count | Locations |
|------|-------|-----------|
| Board-certified physicians | 2 | Medical template |
| Licensed dentists | 2 | Dental template |
| Licensed therapists | 2 | Therapy template |
| Registered massage therapy | 1 | Service keywords |
| Certified practitioners | 1 | Wellness template |

### Facility Descriptions

| Term | Count | Locations |
|------|-------|-----------|
| "Modern rehabilitation facility" | 2 | Facility template |
| "Professional physiotherapy and chiropractic clinic" | 1 | Facility template |
| "specialized treatment rooms" | 1 | Facility template |
| "Professional rehabilitation equipment" | 2 | Equipment template |

### Treatment/Care Phrases

| Term | Count | Locations |
|------|-------|-----------|
| "return-to-work programs" | 3 | Service keywords |
| "direct billing" | 3 | Service keywords |
| "immediate injury care" | 2 | Service keywords |
| "evidence-based" | 3 | Service keywords |
| "patient care" | 2 | Product, equipment templates |

---

## Localization Status

### Translation Function Usage

**Primary Function:** `__()`
**Text Domain:** `msh-image-optimizer`

### Localized Templates

✅ **Well Localized:**
- All industry template labels (line 60-78 in class-msh-context-helper.php)
- Scene type labels (line 7164-7173 in class-msh-image-optimizer.php)
- UI strings in admin pages

Example:
```php
__('Professional legal services', 'msh-image-optimizer')
__('Comprehensive medical care', 'msh-image-optimizer')
```

⚠️ **Partially Localized:**
- Some concatenated strings NOT wrapped:

```php
// Line 2837 - NOT localized
'title' => $this->clean_text("{$this->business_name} Clinic - {$this->location} Rehabilitation Facility")
```

Should be:
```php
'title' => $this->clean_text(
    sprintf(
        __('%1$s Clinic - %2$s Rehabilitation Facility', 'msh-image-optimizer'),
        $this->business_name,
        $this->location
    )
)
```

❌ **Not Localized:**
- Service keyword arrays (lines 62-109)
- Treatment keyword arrays (lines 2252-2273)
- Product patterns (lines 1802-1821)
- Icon patterns (lines 1862-1918)

**Reason:** These are detection keywords, not UI strings. Would need separate translation mechanism.

### Localization Gaps

**High Priority:**

1. **Hardcoded concatenations** - ~50 instances
   - Need to convert to `sprintf()` with `__()` placeholders

2. **Healthcare terminology** - Need translation glossaries
   - "rehabilitation", "physiotherapy", "chiropractic", etc.
   - Different terms in different languages (e.g., "fisioterapia" in Spanish)

3. **Regional terms** - Need removal or parameterization
   - WSIB (Ontario-specific)
   - MVA (not universal term)

**Medium Priority:**

4. **Service keywords** - Need locale-aware matching
   - French: "physiothérapie" vs English: "physiotherapy"
   - Spanish: "quiropráctica" vs English: "chiropractic"

5. **Professional credentials** - Vary by country
   - "Board-certified" (USA) vs "GMC-registered" (UK)
   - "Licensed attorney" vs "Solicitor" vs "Barrister"

---

## Summary Statistics

**Total Hardcoded Strings:** ~500+

**By Category:**
- Scene templates: 8 × 4 fields = 32 unique patterns
- Industry templates: 17 × 4 fields = 68 unique patterns
- Service keywords: 12 services × 3 variants = 36 keyword sets
- Treatment patterns: 18 patterns
- Product patterns: 15 patterns
- Icon patterns: 17 patterns
- Filename slugs: ~100 pattern variations

**Localization Coverage:**
- UI strings: ~90% localized ✅
- Template strings: ~60% localized ⚠️
- Detection keywords: 0% localized ❌

**Industry-Agnostic Score:** **2/10** ❌

**Reasons:**
- 80%+ strings are healthcare-specific
- Service keyword map entirely medical
- Treatment patterns Ontario-focused (WSIB)
- No generic fallbacks for other industries

**Recommended Action:**
1. Create Template Registry (JSON/database)
2. Extract industry-specific strings to separate profiles
3. Implement generic fallback templates
4. Remove regional acronyms (WSIB, MVA)
5. Parameterize professional credentials by country

---

**Generated:** 2025-10-26
**Total Templates Cataloged:** 500+
**Primary Source:** `class-msh-image-optimizer.php` (9,963 lines)
**Status:** Healthcare-centric, needs industry-agnostic refactor
