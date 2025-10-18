# Wellness Website Testing Guide
## AI Context Detection & Metadata Generation

**Date Created**: October 17, 2025
**Purpose**: Verify AI regeneration captures wellness website context correctly

---

## ✅ What's Been Fixed & Ready

### 1. Credit Tracking (FIXED)
- Credits now properly deduct after each AI call
- Monthly usage tracking works correctly
- Dashboard shows accurate credit balance

### 2. AI Regeneration Flow (COMPLETE)
- ✅ Modal with scope/mode/field selection
- ✅ Background queue processing
- ✅ Progress tracking with live updates
- ✅ All styling matches dot brand (no blue!)
- ✅ Full-width layout in Step 1

### 3. AI Metadata Generated (VERIFIED)
Test site successfully generated:
- 35 images processed
- 35 successful (0 failed, 0 skipped)
- All images have SEO-optimized titles, alt text, captions, descriptions

---

## 🧪 Testing Steps for Wellness Website

### Step 1: Check Current Context Settings

1. Go to **Settings > MSH Image Optimizer > AI Settings**
2. Verify current context is set correctly:
   - **Industry**: Healthcare/Wellness
   - **Business Type**: Wellness Center / Spa / Health Practice
   - **Location**: (Your city/region)
   - **Services**: (Your wellness services - massage, yoga, etc.)

3. Check AI Mode:
   - Should be set to **"Assist"** or **"Hybrid"** (NOT "Manual")
   - Manual mode disables AI features

### Step 2: Run AI Regeneration on Sample Images

1. Go to **Dashboard > MSH Image Optimizer**
2. Scroll to **"AI Metadata Regeneration (Advanced)"** in Step 1
3. Click **"Regenerate Metadata with AI"**

4. In the modal, configure:
   - **Scope**: Select "Images with missing metadata" (safer first test)
   - **Mode**: Select "Fill empty fields only" (won't overwrite existing)
   - **Fields**: Check all boxes (Title, Alt Text, Caption, Description)

5. Review the estimate:
   - Note: Image count
   - Note: Estimated credits
   - Verify you have enough credits

6. Click **"Start Regeneration"**

7. **IMPORTANT**: Watch the progress widget
   - It should start showing updates within 10-20 seconds
   - If it stalls, manually trigger WP-Cron (see "Troubleshooting" below)

### Step 3: Verify Wellness Context in Generated Metadata

After regeneration completes, check a few images manually:

**Via WordPress Admin:**
1. Go to **Media Library**
2. Click on an image that was processed
3. Check the generated metadata for wellness keywords:
   - Should mention: wellness, spa, relaxation, therapy, healing, etc.
   - Should NOT mention: HVAC, construction, legal services, etc.
   - Should include your business name/location if set in context

**Via WP-CLI (faster for multiple images):**
```bash
wp post get [IMAGE_ID] --fields=post_title,post_excerpt,post_content
wp post meta get [IMAGE_ID] _wp_attachment_image_alt
```

### Step 4: Test Image-Specific Context Detection

The AI should detect image content and combine it with your business context:

**Test Images to Upload:**
- Massage room photo → Should generate metadata about massage therapy services
- Yoga studio photo → Should mention yoga, mindfulness, wellness
- Spa treatment photo → Should reference spa services, relaxation
- Herbal products → Should mention natural wellness, holistic health

**Expected Behavior:**
- AI analyzes image visual content
- Combines with your wellness context
- Generates SEO-optimized, contextually relevant metadata

### Step 5: Verify Context Profiles (if using)

If you have context profiles set up:

1. Go to **Settings > MSH Image Optimizer > Context**
2. Check if wellness profile exists
3. Verify profile includes:
   - Target keywords (wellness-related)
   - Services offered
   - Geographic location
   - Brand voice/tone

---

## 🔍 What to Look For

### ✅ Good Metadata (Context Working)
```
Title: "Relaxing Spa Treatment Room - [Your Business Name]"
Alt Text: "Peaceful massage therapy room with aromatherapy candles and natural elements"
Caption: "Professional wellness services in a calming environment"
Description: "Our dedicated treatment room offers a serene space for massage therapy,
featuring natural materials and aromatherapy to enhance your healing experience at
[Your Business] in [Location]."
```

### ❌ Bad Metadata (Context NOT Working)
```
Title: "Interior Design Space - Professional Services"  ← Generic
Alt Text: "Modern room with furniture and lighting"  ← No wellness context
Description: "Professional office space..."  ← Wrong industry
```

---

## 🐛 Troubleshooting

### Issue: Progress Stalls at 0%

**Cause**: WP-Cron not running (common in Local development)

**Fix via WP-CLI**:
```bash
# Check if queue is stuck
wp option get msh_metadata_regen_queue_state --format=json

# Manually process queue
wp eval "MSH_Metadata_Regeneration_Background::get_instance()->process_queue();"

# Check progress again
wp option get msh_metadata_regen_queue_state --format=json
```

**Repeat the `wp eval` command until status shows "completed"**

### Issue: AI Generates Generic Metadata

**Possible Causes:**
1. AI mode set to "Manual" → Change to "Assist" or "Hybrid"
2. Context not set → Go to Settings > AI Settings
3. Wrong context selected → Verify industry is Healthcare/Wellness

**Fix**:
```bash
# Check current AI mode
wp option get msh_ai_mode

# Set to assist mode
wp option update msh_ai_mode "assist"

# Verify context
wp option get msh_onboarding_context --format=json
```

### Issue: Credits Not Deducting

**This should be fixed now**, but if it happens:
```bash
# Check credit balance
wp eval "echo MSH_AI_Service::get_instance()->get_credit_balance();"

# Check monthly usage
wp option get msh_ai_credit_usage --format=json
```

### Issue: No Images Selected Error

**Cause**: No images match the selected scope/mode/field criteria

**Fix**:
- Try scope "All images" with mode "Overwrite all"
- Or upload a few test images first

---

## 📊 Expected Results Summary

After successful testing, you should have:

1. ✅ **Credits Deducted**: Balance reduced by number of images processed
2. ✅ **Monthly Usage Tracked**: Shows in dashboard "This Month: X credits used"
3. ✅ **Wellness Context**: All metadata reflects wellness/spa/health themes
4. ✅ **Image-Specific Content**: Metadata describes actual image content
5. ✅ **SEO Optimization**: Titles 50-70 chars, alt text descriptive, keywords present
6. ✅ **Business Integration**: Mentions your business name, location, services

---

## 🔄 Re-running on Same Images

If you want to regenerate metadata again:

1. Select mode: **"Overwrite all metadata"**
2. This creates backups before overwriting
3. Previous metadata stored in: `_msh_meta_backup_{timestamp}` post meta

---

## 📁 About File Renaming

**IMPORTANT**: AI Regeneration generates filename suggestions but DOES NOT rename files automatically.

### How It Works:
1. ✅ **AI Regeneration** stores suggested filename in `_msh_ai_filename_slug` metadata
2. ⏭️ **Step 1: File Renaming** (separate process) uses that slug to actually rename files

### To Actually Rename Files:

**Option 1: Via Dashboard**
1. Enable file renaming toggle in Step 1
2. Run **"Analyze Published Images"**
3. Click **"Apply Suggestions"**
4. Files will be renamed using AI-generated slugs

**Option 2: Via WP-CLI**
```bash
# Rename a specific image using AI slug
wp msh rename --ids=1691

# Check the result
wp post meta get 1691 _wp_attached_file
```

### What Gets Stored:
- `_msh_ai_filename_slug`: AI-generated slug (e.g., "lush-green-fern")
- `_wp_attached_file`: Current actual filename (e.g., "dsc20050315_145007_132.jpg")

**After renaming**, `_wp_attached_file` updates to use the AI slug: "lush-green-fern-1691.jpg"

---

## 📝 Notes for Tomorrow

**Current State (HVAC Test Site)**:
- 35 images processed successfully
- Credits: 65 remaining (started with 100)
- Context: HVAC services (need to change for wellness)
- All systems working correctly

**For Wellness Site:**
- Start fresh with wellness context
- Test with 5-10 images first
- Verify context detection
- Then run full library if results are good

---

## 🚨 Important Reminders

1. **Always backup before bulk operations** (plugin does this automatically)
2. **Start with small test** (5-10 images) before full library
3. **Monitor credit usage** to avoid running out mid-job
4. **Check WP-Cron** if progress stalls in development environment
5. **Refresh dashboard** after job completes to see updated stats

---

## 💡 Context Detection Best Practices

**The AI uses this priority:**
1. Image visual analysis (what's actually in the photo)
2. Your business context (industry, services, location)
3. Existing metadata (if available)
4. SEO best practices

**To improve results:**
- Set detailed business context in settings
- Use descriptive original filenames when possible
- Upload images that clearly show your services
- Create context profiles for different service types

---

Good luck with testing! 🎉
