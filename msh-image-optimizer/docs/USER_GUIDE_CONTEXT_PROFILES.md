# 📂 Context Profiles - User Guide

**MSH Image Optimizer - Manage Multiple Brand Contexts**

---

## 📋 Table of Contents

1. [What are Context Profiles?](#what-are-context-profiles)
2. [When to Use Profiles](#when-to-use-profiles)
3. [Creating Your First Profile](#creating-your-first-profile)
4. [Switching Between Profiles](#switching-between-profiles)
5. [Best Practices](#best-practices)
6. [Common Use Cases](#common-use-cases)
7. [FAQ](#faq)
8. [Troubleshooting](#troubleshooting)

---

## What are Context Profiles?

**Context Profiles** let you manage multiple brand identities, locations, or audience segments within a single WordPress site.

### The Problem They Solve

Imagine you have:
- A main business in New York
- A branch office in Los Angeles
- Spanish-language landing pages
- Different product lines with unique branding

**Without profiles:** All images get the same generic metadata
**With profiles:** Each context gets customized, relevant metadata

### How They Work

Think of profiles as "presets" for your business context:

```
Primary Profile (Default)
├── Business: Main Street Dental
├── Location: New York, NY
├── Audience: Local patients
└── Language: English

Spanish Landing Pages Profile
├── Business: Main Street Dental
├── Location: New York, NY
├── Audience: Spanish-speaking patients
└── Language: Spanish (es_ES)

LA Branch Profile
├── Business: Main Street Dental LA
├── Location: Los Angeles, CA
├── Audience: LA-area patients
└── Language: English
```

When you generate AI metadata, it uses the **active profile** to create contextually relevant descriptions.

---

## When to Use Profiles

### ✅ You SHOULD Use Profiles If:

**Multi-Location Business**
- Restaurants with multiple locations
- Service areas in different cities
- Franchise operations
- Regional offices

**Multilingual Content**
- Spanish landing pages on English site
- French-Canadian content alongside English
- International markets

**Different Product Lines**
- B2B and B2C offerings
- Multiple brands under one company
- Different service tiers (basic/premium)

**Varied Audiences**
- Targeting different demographics
- Industry-specific content
- Seasonal campaigns

### ❌ You DON'T Need Profiles If:

- Single location, single language business
- Consistent branding across all content
- Simple website with one audience

**Use Case:** Just use the Primary profile and you're all set!

---

## Creating Your First Profile

### Step 1: Access Settings

1. Go to **Settings → Image Optimizer**
2. Scroll to **"Context Profiles"** section
3. Click **"Add Context Profile"** button

### Step 2: Fill in Profile Details

#### Required Fields

**Profile Label*** (What you'll see in the selector)
- Example: "Spanish Landing Pages"
- Example: "Los Angeles Branch"
- Example: "Summer Campaign 2025"

**Business Name***
- Can be same as primary or different
- Example: "Main Street Dental" (same)
- Example: "Main Street Dental LA" (different)

**Industry***
- Select from dropdown
- Helps AI understand your business type

**Business Type***
- Local service, Online service, E-commerce, etc.
- Affects how AI describes your offerings

**City***
- Where this profile is located
- Example: "Los Angeles" (even if primary is "New York")

**Ideal Customer***
- Who you're targeting with this profile
- Example: "Spanish-speaking patients seeking dental care"

**What Makes You Different***
- Your unique value proposition for this context
- Example: "Bilingual staff, flexible payment plans"

#### Optional But Recommended

**Locale Code**
- Language/region code: `es_ES`, `fr_FR`, etc.
- Used for automatic language selection
- Leave blank if same as site language

**Brand Voice**
- Professional, Casual, Friendly, etc.
- Affects tone of AI-generated content

**Notes**
- Internal notes about this profile
- Won't appear in generated content

**Other Fields**
- Demographics, Service area, Pain points
- More context = better AI results

### Step 3: Save

1. Click **"Save Settings"** at bottom of page
2. Profile now appears in your profiles list
3. Can create multiple profiles (no limit!)

---

## Switching Between Profiles

### Method 1: From Settings Page

1. Go to **Settings → Image Optimizer**
2. Find **"Active Context"** dropdown (near top)
3. Select your profile:
   - "Primary – [Your Business Name]"
   - "Profile – Spanish Landing Pages"
   - "Profile – Los Angeles Branch"
4. Click **"Save Settings"**

### Method 2: Quick Switch (Coming Soon)

Future update will add quick switcher to main analyzer page.

### What Happens When You Switch?

**Immediately:**
- ✅ Active profile ID updates
- ✅ Right sidebar shows new profile name
- ✅ AI will use new context

**AI Metadata Generation:**
- New titles/descriptions mention the profile's location
- Business name from profile used
- Audience targeting adjusts
- Language changes (if locale set)

**What Doesn't Change:**
- Existing metadata (until you regenerate)
- WordPress admin language
- Site structure

---

## Best Practices

### Profile Naming

**✅ Good Names:**
- "Spanish Landing Pages" (clear purpose)
- "Los Angeles Branch" (clear location)
- "Summer 2025 Campaign" (clear timeframe)

**❌ Bad Names:**
- "Profile 1" (not descriptive)
- "Test" (not meaningful)
- "asdf" (unprofessional)

### Organization

**Start Simple:**
1. Use Primary profile for main content
2. Add profiles only when needed
3. Don't over-complicate

**Common Structure:**
```
Primary (Default)
├── Main business, primary location
│
Regional Profiles
├── LA Branch
├── Miami Branch
│
Language Profiles
├── Spanish Content
├── French Content
│
Seasonal/Campaign Profiles
└── Holiday 2025
```

### Maintenance

**Regular Review:**
- Quarterly: Check if profiles still needed
- Delete inactive profiles
- Update context as business changes

**Consistent Updates:**
- When you change primary, update profiles too
- Keep business details synchronized
- Update locale codes if site language changes

---

## Common Use Cases

### Use Case 1: Multi-Location Restaurant

**Scenario:** Pizza restaurant with 3 locations

**Setup:**
```
Primary Profile
├── Name: Tony's Pizza
├── City: Brooklyn, NY
├── Audience: Brooklyn families
└── Locale: en_US

Manhattan Location Profile
├── Name: Tony's Pizza Manhattan
├── City: Manhattan, NY
├── Audience: Manhattan professionals
└── Locale: en_US

Jersey City Profile
├── Name: Tony's Pizza Jersey City
├── City: Jersey City, NJ
├── Audience: Jersey City residents
└── Locale: en_US
```

**Workflow:**
1. Upload photos of Brooklyn location → Use Primary
2. Upload photos of Manhattan location → Switch to Manhattan profile
3. Generate AI metadata
4. Result: Each location's photos mention the correct city

**Example Output:**

*Brooklyn (Primary):*
```
Title: Fresh Margherita Pizza at Tony's Brooklyn
ALT: Wood-fired margherita pizza served at Tony's Pizza in Brooklyn
```

*Manhattan Profile:*
```
Title: Artisan Pizza at Tony's Manhattan Location
ALT: Gourmet pizza prepared fresh at Tony's Pizza Manhattan
```

---

### Use Case 2: Bilingual Medical Practice

**Scenario:** Dental office serving English and Spanish patients

**Setup:**
```
Primary Profile
├── Name: Main Street Dental
├── City: Miami, FL
├── Audience: English-speaking patients
├── Locale: en_US
└── UVP: Gentle care, flexible scheduling

Spanish Services Profile
├── Name: Main Street Dental
├── City: Miami, FL
├── Audience: Spanish-speaking patients
├── Locale: es_ES
└── UVP: Personal bilingüe, horarios flexibles
```

**Workflow:**
1. Process general practice photos → Primary (English)
2. Process Spanish landing page photos → Spanish profile
3. AI generates English metadata for primary
4. AI generates Spanish metadata for Spanish pages

**Result:**
- English pages: Professional English descriptions
- Spanish pages: Native Spanish descriptions
- Same photos, different contexts!

---

### Use Case 3: Real Estate Agency

**Scenario:** Agency covers 3 neighborhoods with different buyer demographics

**Setup:**
```
Primary - Downtown Luxury
├── Audience: High-income professionals
├── Focus: Luxury condos, penthouses
└── Voice: Professional, sophisticated

Suburban Families Profile
├── Audience: Growing families
├── Focus: Single-family homes, good schools
└── Voice: Warm, family-friendly

Investment Properties Profile
├── Audience: Real estate investors
├── Focus: ROI, rental potential
└── Voice: Business-focused, data-driven
```

**Result:**
- Same house photo generates different descriptions based on target audience
- Luxury profile: "Sophisticated penthouse living..."
- Family profile: "Spacious family home near top-rated schools..."
- Investment profile: "High-ROI property with strong rental demand..."

---

### Use Case 4: Seasonal E-commerce

**Scenario:** Online store with seasonal promotions

**Setup:**
```
Primary - Year-Round
├── Standard product descriptions
└── Neutral, timeless messaging

Holiday 2025 Profile
├── Holiday-themed language
├── Gift-giving focus
└── Festive, warm tone

Summer Sale Profile
├── Seasonal product highlights
├── Beach/outdoor themes
└── Energetic, fun tone
```

**Workflow:**
1. Jan-Oct: Use Primary for product photos
2. Nov-Dec: Switch to Holiday profile, regenerate featured products
3. Jun-Aug: Switch to Summer profile for seasonal items
4. Result: Same products, seasonally appropriate descriptions

---

## FAQ

### Q: How many profiles can I create?

**A:** Unlimited! Create as many as you need. However, we recommend keeping it manageable (5-10 max) for ease of use.

---

### Q: Do profiles affect my existing metadata?

**A:** No! Existing metadata stays unchanged until you actively regenerate it with a profile active.

---

### Q: Can I delete a profile?

**A:** Yes! Click the trash icon next to the profile. If it's the active profile, the system automatically switches to Primary.

---

### Q: What happens if I delete the Primary profile?

**A:** You can't delete Primary - it's required. You can edit it, but not delete it.

---

### Q: Can I rename a profile?

**A:** Yes! Edit the "Profile Label" field and save. The profile slug may stay the same, but the display name changes.

---

### Q: Do I need to rebuild the index when switching profiles?

**A:** No! Profile switching doesn't affect the usage index. That's separate.

---

### Q: Can profiles have different API keys?

**A:** Not currently. All profiles use the same AI service/API key. This may change in future versions.

---

### Q: What's the difference between Primary and a Profile?

**A:**
- **Primary:** Default profile, can't be deleted, always available
- **Profile:** Additional contexts you create, can be added/deleted

Functionally, they work the same way!

---

### Q: Do I need profiles for WPML/Polylang multilingual sites?

**A:** Not required, but helpful! You can:
- Use WPML/Polylang for site structure
- Use Context Profiles for content variations within each language

They complement each other!

---

## Troubleshooting

### Issue: Profile isn't saving

**Solutions:**
1. Check all required fields (marked with *)
2. Make sure business name isn't empty
3. Try clearing browser cache
4. Check browser console for JavaScript errors

---

### Issue: Active profile keeps reverting to Primary

**Solutions:**
1. Make sure you clicked "Save Settings" after selecting
2. Check for errors in browser console
3. Verify profile wasn't deleted
4. Try hard refresh (Cmd+Shift+R)

---

### Issue: AI metadata doesn't use profile details

**Solutions:**
1. Verify profile is actually active (check right sidebar)
2. Regenerate metadata (existing metadata won't auto-update)
3. Check profile has detailed context filled in
4. Review AI-generated content - it may be subtle

---

### Issue: Can't delete a profile

**Solutions:**
1. You can't delete Primary (by design)
2. Make sure you have manage_options capability
3. Check for JavaScript console errors
4. Try from different browser

---

### Issue: Profile dropdown not showing

**Solutions:**
1. Make sure you created at least one profile
2. Hard refresh browser
3. Clear WordPress cache
4. Check plugin is up to date

---

## Profile Management Tips

### Regular Maintenance

**Monthly:**
- Review active profiles
- Delete unused profiles
- Update context if business changed

**Quarterly:**
- Audit metadata across profiles
- Ensure consistency
- Update seasonal profiles

**Annually:**
- Full context review
- Update all profile details
- Align with business strategy

### Profile Audit Checklist

- [ ] All required fields filled?
- [ ] Business details accurate?
- [ ] Locale codes correct?
- [ ] Audience targeting current?
- [ ] UVP still relevant?
- [ ] Profile name clear?
- [ ] Still actively used?

---

## Advanced Tips

### Profile Inheritance

Want most fields same as Primary, but change location?

1. Create new profile
2. Copy Primary context details
3. Change only City and Audience
4. Result: Consistent branding, localized content

### Testing New Profiles

Before processing 100s of images:

1. Create profile
2. Test with 5-10 images
3. Review AI-generated content
4. Adjust profile details if needed
5. Then process full batch

### Profile Templates

Save time by creating "template" profiles:

```
Location Template
├── Copy all business details
├── Change: City, Service Area
└── Result: Quick new location setup

Language Template
├── Copy all business details
├── Change: Locale, Audience language
└── Result: Quick translation setup
```

---

## Related Resources

**User Guides:**
- [Multilingual AI User Guide](USER_GUIDE_MULTILINGUAL_AI.md)
- [AI Regeneration Workflow](../../docs/development/AI_REGENERATION_REDESIGN.md)

**Technical Documentation:**
- [Context Profiles QA Plan](CONTEXT_PROFILE_QA_PLAN.md)
- [Multilingual AI Phase Plan](MULTILINGUAL_AI_PHASE_PLAN.md)

**Support:**
- GitHub Issues: https://github.com/toodokie/thedot-image-optimizer/issues

---

**Last Updated:** October 17, 2025
**Plugin Version:** 1.2.0+
**Feature Status:** ✅ Production Ready
