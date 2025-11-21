# Context Onboarding Guide
## MSH Image Optimizer - Client Context Configuration

This guide explains how to create and use context onboarding files for intelligent image metadata generation.

---

## 📋 What is Context Onboarding?

Context onboarding is a structured JSON configuration that teaches the MSH Image Optimizer plugin about your client's:
- Business identity and brand voice
- Target audiences and their pain points
- Services and specializations
- Geographic location and SEO priorities
- Unique value propositions
- Image naming and metadata conventions

With proper context onboarding, the plugin can automatically generate:
- SEO-optimized filenames
- Relevant alt text descriptions
- Accurate image titles
- Contextually appropriate captions

---

## 🎯 Example: Main Street Health

See [main-street-health-context-onboarding.json](../../main-street-health-context-onboarding.json) for a complete working example.

### Key Sections Explained:

#### 1. **Site Identity**
```json
{
  "site_name": "Main Street Health",
  "site_url": "https://mainstreetrehab.com",
  "industry": "Healthcare - Rehabilitation & Occupational Health",
  "location": {
    "city": "Hamilton",
    "province": "Ontario",
    "country": "Canada"
  }
}
```

#### 2. **Brand Voice**
```json
{
  "brand_voice": {
    "tone": "Professional, compassionate, evidence-based",
    "key_phrases": [
      "Get back to work, get back to life",
      "Specialized care for those who serve and protect"
    ]
  }
}
```

#### 3. **Target Audiences**
```json
{
  "target_audiences": [
    {
      "id": "first-responders",
      "name": "First Responders & Occupational Workers",
      "priority": 1,
      "segments": ["Paramedics", "Police Officers", "Firefighters"],
      "seo_keywords": [
        "first responder rehabilitation Hamilton",
        "paramedic injury treatment"
      ]
    }
  ]
}
```

#### 4. **Services**
```json
{
  "services": [
    {
      "category": "Therapeutic Services",
      "offerings": [
        {
          "name": "Physiotherapy",
          "keywords": ["physiotherapy Hamilton", "physical therapy"],
          "image_context": ["treatment rooms", "exercise therapy"]
        }
      ]
    }
  ]
}
```

#### 5. **Image Naming Conventions**
```json
{
  "image_naming_conventions": {
    "format": "{service-type}-{condition-or-audience}-{location}-{id}",
    "examples": [
      "physiotherapy-first-responder-hamilton-1234.jpg"
    ],
    "alt_text_format": "{Descriptive action} for {target audience/condition} at {location}"
  }
}
```

---

## 🛠️ How to Use Context Onboarding

### Step 1: Create Your Context File

1. Copy [main-street-health-context-onboarding.json](../../main-street-health-context-onboarding.json) as a template
2. Rename it to match your client: `{client-name}-context-onboarding.json`
3. Fill in all sections with your client's information

### Step 2: Import to WordPress

**Option A: Via Plugin Settings (Recommended)**

1. Go to **WordPress Admin → TinyDot → Settings**
2. Navigate to **Context Settings** tab
3. Click **Import Context Profile**
4. Upload your JSON file
5. Click **Save Changes**

**Option B: Via WP-CLI**

```bash
wp msh context import /path/to/your-context-onboarding.json
```

**Option C: Programmatic Import**

```php
// In your theme's functions.php or custom plugin
add_action('init', function() {
    if (class_exists('MSH_Context_Manager')) {
        $context_file = get_stylesheet_directory() . '/context-onboarding.json';
        $context_data = json_decode(file_get_contents($context_file), true);

        MSH_Context_Manager::get_instance()->import_context_profile($context_data);
    }
});
```

### Step 3: Verify Import

1. Upload a test image to WordPress Media Library
2. Click **"AI Generate"** button in image editor
3. Check that generated metadata reflects your context:
   - Filename includes location and service type
   - Alt text mentions target audience or condition
   - Title and caption use brand voice phrases

---

## 📝 Creating Context for Different Industries

### Healthcare/Medical
Focus on:
- Conditions treated
- Medical specializations
- Patient demographics
- Treatment modalities
- Clinical credentials

### Legal Services
Focus on:
- Practice areas
- Client types (personal injury, corporate, etc.)
- Geographic jurisdiction
- Case types
- Professional credentials

### Wellness/Spa
Focus on:
- Treatment types
- Wellness benefits
- Client experience
- Relaxation themes
- Holistic approaches

### Real Estate
Focus on:
- Property types
- Neighborhoods/locations
- Buyer/seller audiences
- Market positioning
- Lifestyle themes

---

## 🎨 Image Subject Mapping

The plugin uses `common_image_subjects` to identify what's in an image and generate appropriate metadata.

### Example: Treatment Photos

```json
{
  "subject": "Treatment & Therapy",
  "keywords": ["physiotherapy", "massage", "rehabilitation"],
  "context_hints": ["Hamilton clinic", "professional therapy"]
}
```

**Input Image:** Photo of physiotherapist treating patient
**Generated Filename:** `physiotherapy-treatment-session-hamilton-5678.jpg`
**Generated Alt Text:** `Physiotherapy treatment for injury rehabilitation at Hamilton clinic`

### Example: First Responder Photos

```json
{
  "subject": "First Responders",
  "keywords": ["paramedic", "firefighter", "first responder"],
  "context_hints": ["specialized care", "occupational rehabilitation"]
}
```

**Input Image:** Photo of firefighter in rehabilitation
**Generated Filename:** `physiotherapy-firefighter-rehabilitation-hamilton-9012.jpg`
**Generated Alt Text:** `Specialized physiotherapy care for firefighter injury at Hamilton rehabilitation clinic`

---

## 🔍 SEO Optimization

### Geographic Modifiers

When `geo_modifiers_required: true`, the plugin automatically adds location to filenames and alt text:

```
physiotherapy-hamilton-1234.jpg
chiropractor-hamilton-ontario-5678.jpg
massage-therapy-near-me-hamilton-9012.jpg
```

### Service-Specific Keywords

When `include_service_type: true`, service names are prioritized:

```
physiotherapy-back-pain-hamilton.jpg
chiropractic-spinal-adjustment-hamilton.jpg
massage-sports-injury-hamilton.jpg
```

### Condition/Audience Keywords

When `include_condition_treated: true`, specific conditions or audiences are included:

```
physiotherapy-workplace-injury-hamilton.jpg
chiropractic-chronic-back-pain-hamilton.jpg
massage-first-responder-hamilton.jpg
```

---

## 📊 Context Profile Quality Checklist

Before importing your context profile, verify:

- [ ] **Site identity** is accurate (name, URL, industry, location)
- [ ] **Brand voice** reflects actual tone and messaging
- [ ] **Target audiences** are prioritized (1 = highest)
- [ ] **All services** have relevant keywords and image context
- [ ] **Unique value propositions** are listed
- [ ] **Common image subjects** cover all photo types on site
- [ ] **Location context** includes all service areas
- [ ] **SEO priorities** list most important keywords
- [ ] **Image naming format** matches your preferences
- [ ] **Alt text format** is descriptive and follows accessibility guidelines
- [ ] **Content guidelines** reflect brand standards

---

## 🔄 Updating Context Profiles

Context profiles should be updated when:
- New services are added
- Target audiences change
- Geographic expansion occurs
- Brand messaging evolves
- SEO strategy shifts

To update:
1. Edit your JSON file
2. Increment `metadata_version`
3. Update `last_updated` date
4. Re-import via plugin settings or WP-CLI

---

## 🎯 Advanced: Multi-Location Businesses

For businesses with multiple locations, create location-specific context variations:

```json
{
  "site_name": "Main Street Health - Ancaster",
  "location": {
    "city": "Ancaster",
    "province": "Ontario"
  },
  "location_context": {
    "local_references": ["Ancaster", "Hamilton", "Dundas"],
    "primary_location": "Ancaster"
  }
}
```

---

## 🚀 Best Practices

### 1. **Be Specific**
❌ Don't: `"keywords": ["treatment", "therapy", "clinic"]`
✅ Do: `"keywords": ["physiotherapy Hamilton", "sports injury treatment", "chronic pain clinic"]`

### 2. **Include Location**
❌ Don't: `"back pain treatment"`
✅ Do: `"back pain treatment Hamilton Ontario"`

### 3. **Target Your Audience**
❌ Don't: `"rehabilitation services"`
✅ Do: `"first responder rehabilitation services"`

### 4. **Use Natural Language**
❌ Don't: `"physio clinic hamilton back pain"`
✅ Do: `"physiotherapy clinic for back pain treatment in Hamilton"`

### 5. **Keep Updated**
- Review context quarterly
- Update when services change
- Refresh keywords based on SEO performance
- Add new image subjects as content evolves

---

## 🆘 Troubleshooting

### Issue: Generated metadata is too generic

**Solution:** Add more specific keywords and context hints in `common_image_subjects`

### Issue: Location not appearing in filenames

**Solution:** Set `geo_modifiers_required: true` in `seo_priorities`

### Issue: Wrong service type in metadata

**Solution:** Improve `image_context` arrays in service offerings

### Issue: Brand voice not reflected

**Solution:** Add more `key_phrases` in `brand_voice` section and review `tone_requirements`

---

## 📚 Related Documentation

- [Plugin User Guide](../MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md)
- [SEO Best Practices](../../msh-image-optimizer.backup.20251019214341/docs/phase4-manual.md)
- [Context Fusion System](../../README.md)

---

**Last Updated:** October 27, 2025
**For Plugin Version:** 1.2.1 (Phase 6)
**Status:** ✅ Production Ready
