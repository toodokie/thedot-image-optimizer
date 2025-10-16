# Radiant Bloom Wellness - Test Site Setup Guide

**Purpose:** Create a fresh wellness/spa test site to validate the critical wellness/healthcare metadata bug fix.

**Timeline:** 30-45 minutes

---

## Step 1: Create New Local Site (5 minutes)

### In Local by Flywheel:

1. Click **"+" (Create a new site)**
2. **Site Name:** `radiant-bloom-wellness`
3. **Choose your environment:**
   - Preferred (PHP 8.0+, MySQL 8.0+, latest WordPress)
4. **WordPress Setup:**
   - Username: `admin`
   - Password: `admin` (it's a test site)
   - Email: `test@radiantbloom.local`
5. Click **"Add Site"**
6. Wait for Local to provision the site (~2-3 minutes)

---

## Step 2: Install MSH Image Optimizer Plugin (2 minutes)

### Option A: Copy from Existing Project

```bash
# In Terminal:
cp -r /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer \
  "/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public/wp-content/plugins/"
```

### Option B: Via WordPress Admin

1. Go to WordPress admin: http://radiant-bloom-wellness.local/wp-admin
2. Login: admin / admin
3. Plugins → Add New → Upload Plugin
4. Upload: `msh-image-optimizer.zip`
5. Activate

---

## Step 3: Configure MSH Plugin - Wellness Context (5 minutes)

### Via WordPress Admin (GUI):

Go to: **MSH Image Optimizer → Settings → Onboarding**

**Fill in:**
```
Business Name: Radiant Bloom Studio
Industry: wellness
Location: Toronto, Ontario
Service Area: Greater Toronto Area, North York, Scarborough, Etobicoke, Midtown Toronto
Unique Value Proposition: Luxury spa and wellness sanctuary offering personalized holistic treatments, organic skincare, and rejuvenating massage therapy in a tranquil urban oasis
Pain Points: Stress relief, skincare concerns, muscle tension, need for relaxation and self-care
Target Audience: Busy professionals, health-conscious individuals, those seeking natural beauty treatments and stress management
```

### OR Via WP-CLI (Faster):

```bash
cd "/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public"

/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_onboarding_context '{
  "business_name": "Radiant Bloom Studio",
  "industry": "wellness",
  "location": "Toronto, Ontario",
  "service_area": "Greater Toronto Area, North York, Scarborough, Etobicoke, Midtown Toronto",
  "uvp": "Luxury spa and wellness sanctuary offering personalized holistic treatments, organic skincare, and rejuvenating massage therapy in a tranquil urban oasis",
  "pain_points": "Stress relief, skincare concerns, muscle tension, need for relaxation and self-care",
  "target_audience": "Busy professionals, health-conscious individuals, those seeking natural beauty treatments and stress management"
}' --format=json
```

**Verify it worked:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_onboarding_context
```

---

## Step 4: Create Sample Pages (10 minutes)

### Create Wellness Service Pages

**Via WP-CLI (Recommended - Fast):**

```bash
cd "/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public"

# Home Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Welcome to Radiant Bloom Studio' \
  --post_content='<h2>Your Urban Wellness Sanctuary</h2><p>Experience holistic beauty and wellness treatments in the heart of Toronto. Our expert therapists combine ancient healing techniques with modern spa luxuries.</p><h3>Featured Services</h3><ul><li>Therapeutic Massage</li><li>Organic Facials</li><li>Body Treatments</li><li>Wellness Consultations</li></ul>' \
  --post_status=publish

# Services Page - Massage Therapy
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Massage Therapy' \
  --post_content='<h2>Therapeutic Massage Services</h2><p>Our registered massage therapists provide personalized treatments to relieve muscle tension, reduce stress, and promote overall wellness.</p><h3>Massage Types</h3><ul><li>Swedish Massage - Relaxation and circulation</li><li>Deep Tissue Massage - Target chronic pain</li><li>Hot Stone Massage - Ultimate relaxation</li><li>Aromatherapy Massage - Holistic healing</li></ul>' \
  --post_status=publish

# Services Page - Facial Treatments
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Organic Facial Treatments' \
  --post_content='<h2>Customized Organic Facials</h2><p>Reveal your natural radiance with our organic skincare treatments. We use only natural, plant-based products tailored to your unique skin type.</p><h3>Facial Services</h3><ul><li>Hydrating Facial - Deep moisture restoration</li><li>Anti-Aging Facial - Reduce fine lines naturally</li><li>Detox Facial - Purify and refresh</li><li>Brightening Facial - Even skin tone</li></ul>' \
  --post_status=publish

# Services Page - Body Treatments
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Body Treatments & Wraps' \
  --post_content='<h2>Luxurious Body Treatments</h2><p>Nourish your skin and relax your body with our signature body treatments featuring organic ingredients and healing touch.</p><h3>Body Services</h3><ul><li>Body Scrub - Exfoliation and renewal</li><li>Mud Wrap - Detoxification</li><li>Seaweed Wrap - Skin toning</li><li>Hot Oil Treatment - Deep hydration</li></ul>' \
  --post_status=publish

# About Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='About Radiant Bloom Studio' \
  --post_content='<h2>Our Philosophy</h2><p>At Radiant Bloom Studio, we believe in the power of natural beauty and holistic wellness. Our sanctuary in Toronto offers a peaceful escape where mind, body, and spirit unite.</p><h3>Our Team</h3><p>Our certified aestheticians and registered massage therapists bring years of expertise in holistic wellness and natural skincare. Every treatment is personalized to your unique needs.</p>' \
  --post_status=publish

# Contact Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Book Your Appointment' \
  --post_content='<h2>Visit Our Toronto Spa</h2><p><strong>Radiant Bloom Studio</strong><br>123 Wellness Lane<br>Toronto, ON M5H 2N2</p><p><strong>Serving:</strong> Greater Toronto Area, North York, Scarborough, Etobicoke, Midtown Toronto</p><p><strong>Hours:</strong> Monday-Saturday 9am-8pm, Sunday 10am-6pm</p>' \
  --post_status=publish

echo "✅ Pages created successfully!"
```

**Verify pages were created:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post list --post_type=page --fields=ID,post_title,post_status
```

---

## Step 5: Download Wellness/Spa Stock Images (10 minutes)

### Free Stock Photo Sources

**Unsplash Collections:**
1. Go to: https://unsplash.com/s/photos/spa-treatment
2. Go to: https://unsplash.com/s/photos/massage-therapy
3. Go to: https://unsplash.com/s/photos/facial-treatment
4. Go to: https://unsplash.com/s/photos/wellness-center

**Pexels Collections:**
1. Go to: https://www.pexels.com/search/spa/
2. Go to: https://www.pexels.com/search/massage/
3. Go to: https://www.pexels.com/search/beauty%20treatment/

### Recommended Image Types (Download 12-15 total):

**Treatment Room Images (3-4):**
- Spa treatment room with massage table
- Facial treatment room
- Relaxation area with candles
- Reception area

**Service Images (4-5):**
- Massage therapy session
- Facial treatment in progress
- Body treatment/wrap
- Hot stone massage

**Team/Staff Images (2-3):**
- Spa therapist portrait
- Team photo
- Aesthetician at work

**Product/Detail Images (2-3):**
- Organic skincare products
- Essential oils and aromatherapy
- Spa tools and equipment
- Natural beauty ingredients

**Facility Images (1-2):**
- Spa exterior or entrance
- Wellness center interior

### Image Naming Convention (Before Upload):

Rename downloaded images to generic test names to simulate real-world scenarios:
- `IMG_5847.jpg` (camera filename)
- `treatment-room.jpg` (generic)
- `spa-photo.jpg` (generic)
- `massage-1.jpg` (generic)
- `facial-580x300.jpg` (dimension pattern)
- `wellness-alignment.jpg` (WordPress test term)
- `therapist.jpg` (generic)
- `products.jpg` (generic)
- `canola3.jpg` (meaningless like canola2)
- `markup-image.jpg` (WordPress test term)
- `featured-spa.jpg` (WordPress test term)
- `sample-wellness.jpg` (generic term)

**Why?** This tests the sanitization and metadata generation with real-world generic/poor filenames.

---

## Step 6: Upload Images to WordPress (5 minutes)

### Upload via WordPress Admin:

1. Go to: http://radiant-bloom-wellness.local/wp-admin
2. Media → Add New
3. Drag and drop all 12-15 images
4. Wait for upload to complete
5. **DO NOT manually edit metadata** - let the plugin generate it

### Review Generated Metadata:

1. Click on each uploaded image in Media Library
2. Check the **Meta Preview** section
3. Look for:
   - **Title:** Should mention spa/wellness/beauty (NOT "Rehabilitation Treatment")
   - **ALT Text:** Should describe wellness service
   - **Caption:** Should mention spa therapists/aestheticians (NOT "Licensed physiotherapist")
   - **Description:** Should mention wellness/spa services (NOT "WSIB approved" or rehabilitation)

---

## Step 7: Expected Results (BEFORE Bug Fix)

### 🔥 **BUG:** Wellness Will Generate Healthcare Metadata

**Expected (Current Broken Behavior):**
```
Title: Rehabilitation Treatment - Radiant Bloom Studio Toronto, Ontario
ALT: Rehabilitation treatment at Radiant Bloom Studio Toronto, Ontario rehabilitation clinic
Caption: Licensed physiotherapist team
Description: Comprehensive rehabilitation care tailored to patient recovery.
            Return-to-work programs. WSIB approved. Direct billing.
```

**This is WRONG for a spa/beauty salon!**

---

## Step 8: Expected Results (AFTER Bug Fix)

### ✅ **FIXED:** Wellness Will Generate Spa/Beauty Metadata

**Expected (After Implementing Fixes):**
```
Title: Spa Treatment - Radiant Bloom Studio | Toronto, Ontario
ALT: Spa treatment at Radiant Bloom Studio in Toronto, Ontario
Caption: Certified wellness therapists
Description: Luxury spa and wellness sanctuary offering personalized holistic
            treatments, organic skincare, and rejuvenating massage therapy
            in a tranquil urban oasis. Certified aestheticians and massage
            therapists serving Greater Toronto Area.
```

**This is CORRECT for a spa/beauty salon!**

---

## Troubleshooting

### If Plugin Doesn't Generate Metadata:

**Check plugin is active:**
```bash
cd "/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public"
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp plugin list
```

**Activate if needed:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp plugin activate msh-image-optimizer
```

### If Context Isn't Loading:

**Check option exists:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_onboarding_context
```

**Delete and recreate if needed:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option delete msh_onboarding_context
# Then re-run the update command from Step 3
```

### If You See Errors in Media Library:

Check PHP error log:
```bash
tail -f "/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public/wp-content/debug.log"
```

---

## Testing Checklist

After upload, verify:

- [ ] All images uploaded successfully (12-15 images)
- [ ] Plugin generated metadata automatically
- [ ] **BUG CHECK:** Does wellness get "Rehabilitation" metadata? (It will before fix)
- [ ] Filenames were cleaned (dimension patterns, WordPress terms)
- [ ] Descriptors were extracted from generic names
- [ ] Business name appears in metadata
- [ ] Location appears in metadata
- [ ] Industry-specific language appears (spa/wellness vs. rehabilitation)

---

## What's Next

After confirming the bug exists on this fresh wellness site:

1. **Fix 1:** Remove 'wellness' from healthcare industry list
2. **Fix 2:** Implement `generate_wellness_meta()` function
3. **Fix 3:** Add wellness to industry routing in `generate_business_meta()`
4. **Fix 4:** Fix default case to not fall through to `generate_clinical_meta()`

Then re-upload images and verify spa/beauty metadata appears instead of rehabilitation metadata.

---

## Quick Reference

**Site URL:** http://radiant-bloom-wellness.local
**Admin URL:** http://radiant-bloom-wellness.local/wp-admin
**Login:** admin / admin
**Site Path:** `/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public`

**WP-CLI Base Command:**
```bash
cd "/Users/anastasiavolkova/Local Sites/radiant-bloom-wellness/app/public" && \
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp
```

---

**Status:** Ready for setup
**Estimated Time:** 30-45 minutes
**Purpose:** Reproduce and fix critical wellness/healthcare metadata bug
