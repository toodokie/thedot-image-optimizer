# Industry-Specific Metadata Templates & Keywords

## Overview

This document defines metadata generation templates for all 17 industries offered in the onboarding flow. Each industry has:
- **Service Keywords** - Industry-specific terminology for descriptions
- **Customer Terms** - What to call clients/customers/patients
- **Common Services** - Typical services offered
- **SEO Keywords** - Industry-specific search terms
- **Metadata Templates** - Title, ALT text, caption, description patterns

---

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

Each industry needs:

1. **Keyword Map Entry** (in `$service_keyword_map` array)
2. **Generator Function** (`generate_{industry}_meta()`)
3. **Slug Generator** (in `generate_filename_slug()` switch)
4. **Customer Term Mapping** (in `get_industry_customer_term()`)

### Priority Order

Implement in this order:

1. **Phase 1:** Home services (plumbing, HVAC, electrical, renovation) - 4 industries
2. **Phase 2:** Professional services (legal, accounting, consulting, marketing, web_design) - 5 industries
3. **Phase 3:** Retail/E-commerce (online_store, local_retail, specialty) - 3 industries
4. **Phase 4:** Healthcare (already done) - medical, dental, therapy, wellness - 4 industries
5. **Phase 5:** Generic fallback (other) - 1 industry

Total: 17 industries

---

## Testing Checklist

For each industry, test:

- [ ] Team member photo metadata
- [ ] Office/facility photo metadata
- [ ] Product/equipment photo metadata
- [ ] General business photo metadata
- [ ] Testimonial photo metadata
- [ ] Filename slug generation
- [ ] No healthcare terms leak into non-healthcare industries
- [ ] Location logic works correctly
- [ ] UVP and onboarding data properly inserted

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
**Status:** Ready for Implementation
