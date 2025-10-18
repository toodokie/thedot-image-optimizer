# 🌍 Multilingual AI Metadata - User Guide

**MSH Image Optimizer - Generate SEO Metadata in 6 Languages**

---

## 📋 Table of Contents

1. [What is Multilingual AI?](#what-is-multilingual-ai)
2. [Available Languages](#available-languages)
3. [How to Use](#how-to-use)
4. [Language Selection Options](#language-selection-options)
5. [Best Practices](#best-practices)
6. [Examples](#examples)
7. [FAQ](#faq)
8. [Troubleshooting](#troubleshooting)

---

## What is Multilingual AI?

The MSH Image Optimizer can automatically generate SEO-friendly image metadata (titles, ALT text, captions, and descriptions) in **6 different languages** using artificial intelligence.

### Why Use Multilingual Metadata?

✅ **Better SEO** - Reach international audiences in their native language
✅ **Professional Quality** - Native-level translations, not machine-translated
✅ **Save Time** - No manual translation needed
✅ **Consistent Branding** - AI uses your business context in every language
✅ **Competitive Advantage** - Stand out in international markets

### What Gets Translated?

When you generate metadata in a language:
- **Title** - The image title/headline
- **ALT Text** - Screen reader and SEO description
- **Caption** - Short photo caption
- **Description** - Detailed image description

**What Doesn't Get Translated:**
- Filenames (stay in original language for technical reasons)
- WordPress admin interface (use WordPress language settings)

---

## Available Languages

The plugin supports **6 major languages** plus automatic detection:

| Flag | Language | Code | Example Use Case |
|------|----------|------|------------------|
| 🌐 | **Auto** | auto | Automatically uses your site/profile language |
| 🇬🇧 | **English** | en | Default, international audience |
| 🇪🇸 | **Spanish** | es | Spain, Latin America, US Hispanic market |
| 🇫🇷 | **French** | fr | France, Canada, Belgium, Africa |
| 🇩🇪 | **German** | de | Germany, Austria, Switzerland |
| 🇵🇹 | **Portuguese** | pt | Portugal, Brazil |
| 🇮🇹 | **Italian** | it | Italy, Switzerland |

### Coming Soon
More languages can be added based on demand. Contact support if you need additional languages.

---

## How to Use

### Step 1: Access AI Regeneration

1. Go to **Media → Image Optimizer** in your WordPress admin
2. Click the **"AI Regeneration"** button (top section)

### Step 2: Select Your Language

In the AI Regeneration modal:

1. **Choose Scope** - Which images to process
   - All images
   - Only published images
   - Only images with missing metadata

2. **Choose Mode**
   - Fill empty fields only (recommended)
   - Overwrite all fields

3. **Select Fields** - Which metadata to generate
   - ☑ Title
   - ☑ ALT Text
   - ☑ Caption
   - ☑ Description

4. **🌍 Select Output Language** ⭐ NEW!
   - Choose from dropdown: Auto, English, Spanish, French, German, Portuguese, Italian
   - See estimated credits needed

5. Click **"Start Regeneration"**

### Step 3: Review & Apply

1. **Wait for AI to complete** - Progress shown in modal
2. **Review generated metadata**
   - Look for orange "AI Metadata Ready" badges
   - Click image rows to preview metadata
3. **Apply metadata**
   - Check boxes for images you want to update
   - Click "Optimize Selected"
   - Metadata will be applied to your images

---

## Language Selection Options

### 🌐 Auto (Recommended for Most Users)

**How it works:**
1. Checks if active Context Profile has a locale set → uses that
2. Otherwise, uses your WordPress site language
3. Falls back to English if none set

**Best for:**
- Single-language sites (set it and forget it)
- When using Context Profiles with different locales
- Users who want smart defaults

**Example:**
```
Site Language: English
Active Profile: "Spanish Landing Pages" (locale: es_ES)
Auto Selection: Spanish ✅
```

### 🇬🇧 English (Default)

**Best for:**
- International/global audience
- US-based businesses
- Technical/professional content
- Default if unsure

**Example Output:**
```
Title: Lush Vegetable Fields in Sunlight - Authentic Travel Photography
ALT: Rows of vibrant green vegetables growing in a sunlit field
Caption: Sunlit vegetable fields showcasing natural growth
Description: Expansive vegetable fields basking in sunlight...
```

### 🇪🇸 Spanish (Español)

**Best for:**
- Spanish-speaking markets (Spain, Mexico, Latin America)
- US Hispanic audience
- Tourism/hospitality in Spanish regions

**Example Output:**
```
Title: Campos de cultivo de lechugas en granja local
ALT: Filas de lechugas verdes creciendo en un campo agrícola
Caption: Filas de lechugas en campo soleado
Description: Imagen de un campo agrícola con filas de lechugas...
```

### 🇫🇷 French (Français)

**Best for:**
- France, Canada (Quebec), Belgium
- African French-speaking countries
- Luxury/fashion/culinary content

**Example Output:**
```
Title: Champs de laitues en plein air - Photographie Voyage
ALT: Champs de laitues bien alignées sous le soleil
Caption: Champs de laitues au soleil
Description: Champs de laitues en rangées sous le soleil...
```

### 🇩🇪 German (Deutsch)

**Best for:**
- Germany, Austria, Switzerland
- Engineering/technical content
- Automotive/manufacturing industries

### 🇵🇹 Portuguese (Português)

**Best for:**
- Brazil (largest Portuguese market)
- Portugal
- African Portuguese-speaking countries

### 🇮🇹 Italian (Italiano)

**Best for:**
- Italy
- Fashion/design/food industries
- Tourism in Italian regions

---

## Best Practices

### ✅ DO:

**Match Your Audience**
- Use the language your target audience speaks
- Consider regional variations (Spain vs. Mexico Spanish)

**Use Context Profiles**
- Create separate profiles for different language markets
- Set the locale in each profile for automatic language selection

**Review Before Publishing**
- Always preview AI-generated content
- Check for brand consistency
- Verify cultural appropriateness

**Batch by Language**
- Process all Spanish images together
- Then French, then German, etc.
- Easier to review and maintain consistency

**Test First**
- Try with 5-10 images first
- Review quality before processing hundreds

### ❌ DON'T:

**Mix Languages Randomly**
- Don't apply Spanish metadata to English pages
- Keep language consistent across related content

**Skip Review**
- AI is good but not perfect
- Always review before publishing

**Forget SEO Basics**
- Language doesn't replace good descriptions
- Still need accurate, descriptive content

**Overwrite Good Content**
- If you have quality manual metadata, keep it
- Use "Fill empty fields only" mode

---

## Examples

### Example 1: Travel Photography Site

**Scenario:** Travel blog targeting Spanish-speaking travelers

**Setup:**
1. Create Context Profile: "Spanish Travel Content"
2. Set locale: `es_ES`
3. Business name: "Wanderlust Viajes"
4. City: Barcelona

**Workflow:**
1. Select all travel photos
2. Choose Spanish language
3. Generate metadata
4. Result: Professional Spanish descriptions mentioning Barcelona

**Before:**
```
Title: IMG_1234.jpg
ALT: (empty)
```

**After:**
```
Title: Atardecer en las montañas de Barcelona - Fotografía de Viajes
ALT: Vista panorámica del atardecer sobre las montañas cerca de Barcelona
Caption: Paisaje montañoso al atardecer
Description: Captura impresionante del atardecer en las montañas...
```

---

### Example 2: Multi-Language E-commerce

**Scenario:** Product photos need metadata in 3 languages

**Setup:**
Create 3 Context Profiles:
- "English Store" (en_US)
- "Tienda Española" (es_ES)
- "Boutique Française" (fr_FR)

**Workflow:**
1. **For English site:**
   - Switch to "English Store" profile
   - Language: English
   - Generate metadata

2. **For Spanish site:**
   - Switch to "Tienda Española" profile
   - Language: Spanish
   - Generate metadata

3. **For French site:**
   - Switch to "Boutique Française" profile
   - Language: French
   - Generate metadata

**Result:** Same products, 3 languages, all professionally described

---

### Example 3: Restaurant with Multilingual Menu

**Scenario:** Restaurant serves international clientele

**Setup:**
- Primary language: English
- Secondary: Spanish, French

**Workflow:**
1. **Process food photos:**
   - English: Professional culinary descriptions
   - Spanish: Natural, appetizing descriptions
   - French: Elegant, gourmet terminology

2. **Review for accuracy:**
   - Check dish names are correct
   - Verify cultural appropriateness
   - Ensure consistent tone

---

## FAQ

### Q: Does multilingual cost extra?

**A:** No! Same AI credits whether you generate in 1 language or 6.

### Q: Can I change the language after generating?

**A:** Yes! Just run AI Regeneration again with a different language. The new metadata will replace the old.

### Q: How accurate are the translations?

**A:** Very accurate - this is AI generation in native languages, not translation. Quality is comparable to native speakers.

### Q: Can I edit the AI-generated metadata?

**A:** Absolutely! Click "Edit" on any field to customize. The AI provides a starting point.

### Q: What if my language isn't listed?

**A:** Contact support! We can add new languages based on demand.

### Q: Does this translate my entire site?

**A:** No, this only generates image metadata. For full site translation, use a plugin like WPML or Polylang alongside this.

### Q: How does Auto language selection work?

**A:**
1. Checks active Context Profile locale
2. Falls back to WordPress site language
3. Defaults to English if none set

### Q: Can I use different languages on the same site?

**A:** Yes! Common scenarios:
- English site with Spanish photo gallery
- Multilingual pages with matching image metadata
- Regional landing pages in different languages

### Q: Will this affect my SEO?

**A:** Positively! Proper language metadata improves:
- International SEO rankings
- Image search results in different languages
- Accessibility for non-English speakers

### Q: What about right-to-left languages (Arabic, Hebrew)?

**A:** Not currently supported. Contact us if you need RTL language support.

---

## Troubleshooting

### Issue: Language selector not showing

**Solution:**
1. Hard refresh browser (Cmd+Shift+R or Ctrl+Shift+F5)
2. Clear WordPress cache
3. Check if plugin is updated to latest version

---

### Issue: Generated content is in wrong language

**Solution:**
1. Check which language you selected in dropdown
2. Verify "Auto" is resolving correctly
3. Check Context Profile locale setting
4. Review browser console for errors

---

### Issue: Content quality seems off

**Solution:**
1. Verify you selected correct language
2. Check Context Profile has accurate business details
3. Try regenerating with more specific context
4. Edit manually if needed for brand voice

---

### Issue: Some words stayed in English

**Solution:**
This can happen with:
- Brand names (should stay in English)
- Technical terms (often international)
- Proper nouns (cities, people)
This is usually correct behavior.

---

### Issue: Credits depleted too fast

**Tips:**
- Use "Fill empty fields only" mode
- Process only images that need metadata
- Start with small batches to test
- Consider upgrading plan if processing large volumes

---

## Additional Resources

**Related Guides:**
- [Context Profiles User Guide](USER_GUIDE_CONTEXT_PROFILES.md)
- [AI Regeneration Best Practices](USER_GUIDE_AI_REGENERATION.md)
- [Multilingual WordPress Setup](USER_GUIDE_MULTILINGUAL_SETUP.md)

**Technical Documentation:**
- [Multilingual AI Phase Plan](MULTILINGUAL_AI_PHASE_PLAN.md)
- [Translation Guide](../languages/README.md)

**Support:**
- GitHub Issues: https://github.com/toodokie/thedot-image-optimizer/issues
- Documentation: https://github.com/toodokie/thedot-image-optimizer/wiki

---

**Last Updated:** October 17, 2025
**Plugin Version:** 1.2.0+
**Feature Status:** ✅ Production Ready
