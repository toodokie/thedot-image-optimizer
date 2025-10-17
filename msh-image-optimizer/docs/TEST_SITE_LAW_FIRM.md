# Sterling & Associates Law - Professional Services Test Site

**Industry:** Legal (Professional Services)
**Purpose:** Test professional services metadata generation for law firms
**Timeline:** 30-45 minutes
**Status:** Phase 2 Testing - Professional Services Industry

---

## Business Context

**Firm Name:** Sterling & Associates Law
**Practice Areas:** Corporate Law, Real Estate Law, Estate Planning, Family Law
**Location:** Toronto, Ontario
**Service Area:** Greater Toronto Area, Downtown Toronto, Financial District, North York
**Unique Value Proposition:** Trusted legal counsel for businesses and families since 1987. We combine big-firm expertise with personalized service and transparent billing.
**Pain Points:** Complex legal matters, business transactions, estate disputes, regulatory compliance
**Target Audience:** Business owners, entrepreneurs, families, real estate investors, corporate executives

---

## Step 1: Create New Local Site (5 minutes)

### In Local by Flywheel:

1. Click **"+" (Create a new site)**
2. **Site Name:** `sterling-law-firm`
3. **Choose your environment:**
   - Preferred (PHP 8.0+, MySQL 8.0+, latest WordPress)
4. **WordPress Setup:**
   - Username: `admin`
   - Password: `admin`
   - Email: `test@sterlinglaw.local`
5. Click **"Add Site"**
6. Wait for provisioning (~2-3 minutes)

---

## Step 2: Install MSH Image Optimizer Plugin (2 minutes)

```bash
# Copy plugin from development directory
cp -r /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer \
  "/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public/wp-content/plugins/"

# Activate plugin
cd "/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public"
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp plugin activate msh-image-optimizer
```

---

## Step 3: Configure Legal Industry Context (5 minutes)

### Via WP-CLI:

```bash
cd "/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public"

/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_onboarding_context '{
  "business_name": "Sterling & Associates Law",
  "industry": "legal",
  "location": "Toronto, Ontario",
  "service_area": "Greater Toronto Area, Downtown Toronto, Financial District, North York",
  "uvp": "Trusted legal counsel for businesses and families since 1987. We combine big-firm expertise with personalized service and transparent billing.",
  "pain_points": "Complex legal matters, business transactions, estate disputes, regulatory compliance, litigation support",
  "target_audience": "Business owners, entrepreneurs, families planning estates, real estate investors, corporate executives"
}' --format=json
```

**Verify:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_onboarding_context
```

---

## Step 4: Create Professional Law Firm Pages (10 minutes)

### Complete Page Creation Script:

```bash
cd "/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public"

# Home Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Sterling & Associates Law - Toronto Legal Counsel' \
  --post_content='<h1>Trusted Legal Counsel Since 1987</h1>
<p>Sterling & Associates Law provides comprehensive legal services to businesses and families across the Greater Toronto Area. Our experienced attorneys deliver big-firm expertise with the personalized attention of a boutique practice.</p>

<h2>Our Practice Areas</h2>
<ul>
<li><strong>Corporate Law</strong> - Business formation, contracts, mergers & acquisitions</li>
<li><strong>Real Estate Law</strong> - Commercial and residential transactions, title insurance</li>
<li><strong>Estate Planning</strong> - Wills, trusts, powers of attorney, estate administration</li>
<li><strong>Family Law</strong> - Divorce, custody, separation agreements, mediation</li>
</ul>

<h2>Why Choose Sterling & Associates</h2>
<p>✓ Over 35 years serving Toronto businesses and families<br>
✓ Transparent, flat-fee billing on many services<br>
✓ Personalized attention from senior partners<br>
✓ Convenient Financial District location</p>' \
  --post_status=publish

# Corporate Law Practice Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Corporate Law Services - Business Legal Counsel Toronto' \
  --post_content='<h1>Corporate Law & Business Services</h1>
<p>Our corporate law team advises businesses from startups to established enterprises on all aspects of business law. We help you navigate complex transactions, protect your interests, and achieve your business goals.</p>

<h2>Business Formation & Governance</h2>
<ul>
<li>Corporate incorporation and organization</li>
<li>Partnership agreements and joint ventures</li>
<li>Shareholder agreements and corporate governance</li>
<li>Corporate restructuring and reorganization</li>
</ul>

<h2>Contracts & Commercial Transactions</h2>
<ul>
<li>Contract drafting, review, and negotiation</li>
<li>Supplier and vendor agreements</li>
<li>Licensing and distribution agreements</li>
<li>Employment contracts and non-compete agreements</li>
</ul>

<h2>Mergers, Acquisitions & Due Diligence</h2>
<ul>
<li>Buy-side and sell-side representation</li>
<li>Due diligence and risk assessment</li>
<li>Purchase agreement negotiation</li>
<li>Post-closing integration support</li>
</ul>

<p><strong>Serving:</strong> Toronto business community, Financial District, North York corporate clients</p>' \
  --post_status=publish

# Real Estate Law Practice Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Real Estate Law - Toronto Property Lawyers' \
  --post_content='<h1>Real Estate Law Services</h1>
<p>Whether you are buying your first home, selling commercial property, or investing in real estate, our real estate lawyers provide expert guidance through every transaction.</p>

<h2>Residential Real Estate</h2>
<ul>
<li>Home purchase and sale transactions</li>
<li>Mortgage financing and refinancing</li>
<li>Title searches and title insurance</li>
<li>Condo purchases and declarations</li>
</ul>

<h2>Commercial Real Estate</h2>
<ul>
<li>Commercial property acquisitions and dispositions</li>
<li>Commercial lease negotiation and review</li>
<li>Development and zoning matters</li>
<li>Real estate financing and secured transactions</li>
</ul>

<h2>Real Estate Litigation</h2>
<ul>
<li>Boundary disputes and easements</li>
<li>Contract disputes and breach of contract</li>
<li>Landlord-tenant disputes</li>
<li>Title defect resolution</li>
</ul>

<p><strong>Coverage:</strong> All of Greater Toronto Area including Downtown Toronto, North York, Scarborough, Etobicoke</p>' \
  --post_status=publish

# Estate Planning Practice Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Estate Planning & Wills - Toronto Estate Lawyers' \
  --post_content='<h1>Estate Planning & Administration</h1>
<p>Protect your family and your legacy with comprehensive estate planning. Our estate lawyers help you create customized plans that reflect your wishes and minimize tax burdens.</p>

<h2>Wills & Trusts</h2>
<ul>
<li>Last Will and Testament drafting</li>
<li>Living trusts and testamentary trusts</li>
<li>Trust administration and management</li>
<li>Will updates and amendments</li>
</ul>

<h2>Powers of Attorney & Healthcare Directives</h2>
<ul>
<li>Power of Attorney for Property</li>
<li>Power of Attorney for Personal Care</li>
<li>Living wills and healthcare directives</li>
<li>Guardianship and incapacity planning</li>
</ul>

<h2>Estate Administration & Probate</h2>
<ul>
<li>Estate trustee services (executor support)</li>
<li>Probate applications and court filings</li>
<li>Estate accounting and distribution</li>
<li>Estate litigation and dispute resolution</li>
</ul>

<h2>Tax Planning & Asset Protection</h2>
<ul>
<li>Estate tax minimization strategies</li>
<li>Business succession planning</li>
<li>Charitable giving and foundations</li>
<li>Asset protection planning</li>
</ul>

<p><strong>Transparent Pricing:</strong> Flat fees available for standard wills and powers of attorney</p>' \
  --post_status=publish

# Family Law Practice Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Family Law Services - Toronto Divorce & Custody Lawyers' \
  --post_content='<h1>Family Law & Divorce Services</h1>
<p>Family matters require compassionate, experienced counsel. Our family law team guides you through difficult transitions with sensitivity and strategic advocacy.</p>

<h2>Divorce & Separation</h2>
<ul>
<li>Uncontested and contested divorce</li>
<li>Separation agreements</li>
<li>Division of matrimonial property</li>
<li>Spousal support (alimony)</li>
</ul>

<h2>Child Custody & Support</h2>
<ul>
<li>Custody and access arrangements</li>
<li>Parenting plans and schedules</li>
<li>Child support calculations</li>
<li>Modification of custody orders</li>
</ul>

<h2>Mediation & Collaborative Law</h2>
<ul>
<li>Family mediation services</li>
<li>Collaborative divorce process</li>
<li>Negotiated settlements</li>
<li>Alternative dispute resolution</li>
</ul>

<h2>Domestic Contracts</h2>
<ul>
<li>Prenuptial agreements (marriage contracts)</li>
<li>Cohabitation agreements</li>
<li>Postnuptial agreements</li>
<li>Contract review and negotiation</li>
</ul>

<p><strong>Compassionate Counsel:</strong> We prioritize amicable resolutions while protecting your rights and interests</p>' \
  --post_status=publish

# About / Team Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Our Legal Team - Toronto Lawyers' \
  --post_content='<h1>Experienced Toronto Lawyers</h1>
<p>Sterling & Associates Law brings together a team of accomplished attorneys with decades of combined experience serving businesses and families across the Greater Toronto Area.</p>

<h2>Our Leadership</h2>

<h3>Margaret Sterling, Q.C. - Senior Partner</h3>
<p><strong>Practice Focus:</strong> Corporate Law, Mergers & Acquisitions<br>
<strong>Called to the Bar:</strong> 1987, Ontario<br>
<strong>Education:</strong> J.D., Osgoode Hall Law School; B.Comm., University of Toronto<br>
Margaret has advised on over $2 billion in corporate transactions and specializes in mid-market M&A and corporate governance.</p>

<h3>David Chen - Partner</h3>
<p><strong>Practice Focus:</strong> Real Estate Law, Commercial Transactions<br>
<strong>Called to the Bar:</strong> 1995, Ontario<br>
<strong>Education:</strong> LL.B., University of Toronto Faculty of Law; B.A., Western University<br>
David has closed thousands of real estate transactions ranging from residential homes to multi-million dollar commercial developments.</p>

<h3>Sarah Thompson - Partner</h3>
<p><strong>Practice Focus:</strong> Estate Planning, Wills & Trusts<br>
<strong>Called to the Bar:</strong> 2001, Ontario<br>
<strong>Education:</strong> J.D., Queen\'s University Faculty of Law; B.A., McGill University<br>
Sarah focuses on comprehensive estate planning for high-net-worth families and business succession planning.</p>

<h3>Robert Patel - Associate</h3>
<p><strong>Practice Focus:</strong> Family Law, Mediation<br>
<strong>Called to the Bar:</strong> 2010, Ontario<br>
<strong>Certified:</strong> Family Mediator, Ontario Association for Family Mediation<br>
<strong>Education:</strong> J.D., York University Osgoode Hall; B.Sc., University of British Columbia<br>
Robert brings a collaborative, solution-focused approach to family law matters.</p>

<h2>Professional Affiliations</h2>
<ul>
<li>Law Society of Ontario (LSO)</li>
<li>Canadian Bar Association (CBA)</li>
<li>Toronto Lawyers Association</li>
<li>Ontario Bar Association</li>
<li>Advocates Society</li>
</ul>

<p><strong>Our Commitment:</strong> Every client receives direct access to a senior lawyer who personally manages their matter from start to finish.</p>' \
  --post_status=publish

# Contact Page
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post create \
  --post_type=page \
  --post_title='Contact Sterling & Associates Law - Toronto Office' \
  --post_content='<h1>Contact Our Toronto Law Office</h1>

<h2>Office Location</h2>
<p><strong>Sterling & Associates Law</strong><br>
Suite 1800, Royal Bank Plaza<br>
200 Bay Street<br>
Toronto, ON M5J 2J2</p>

<p><strong>Phone:</strong> (416) 555-0100<br>
<strong>Fax:</strong> (416) 555-0101<br>
<strong>Email:</strong> info@sterlinglaw.ca</p>

<h2>Office Hours</h2>
<p><strong>Monday - Friday:</strong> 9:00 AM - 5:30 PM<br>
<strong>Saturday - Sunday:</strong> Closed<br>
<em>Evening and weekend appointments available by arrangement</em></p>

<h2>Areas We Serve</h2>
<p>Sterling & Associates Law serves clients throughout the Greater Toronto Area including:</p>
<ul>
<li>Downtown Toronto & Financial District</li>
<li>North York</li>
<li>Scarborough</li>
<li>Etobicoke</li>
<li>Mississauga</li>
<li>Markham & Richmond Hill</li>
<li>Vaughan</li>
</ul>

<h2>Schedule a Consultation</h2>
<p>Contact us today to schedule a confidential consultation. We offer:</p>
<ul>
<li>Initial consultations for new clients</li>
<li>Flat-fee pricing on many routine matters</li>
<li>Flexible payment arrangements</li>
<li>Virtual meetings via Zoom or phone</li>
</ul>

<p><strong>How to Reach Us:</strong></p>
<ul>
<li>Call our main line: (416) 555-0100</li>
<li>Email: info@sterlinglaw.ca</li>
<li>Complete our online contact form</li>
</ul>

<p><em>All communications are confidential and protected by solicitor-client privilege.</em></p>' \
  --post_status=publish

echo "✅ Law firm pages created successfully!"
```

**Verify pages:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp post list --post_type=page --fields=ID,post_title,post_status
```

---

## Step 5: Download Professional Law Firm Stock Images (10 minutes)

### Image Sources

**Unsplash:**
- https://unsplash.com/s/photos/lawyer
- https://unsplash.com/s/photos/law-office
- https://unsplash.com/s/photos/legal-team
- https://unsplash.com/s/photos/business-meeting
- https://unsplash.com/s/photos/courthouse

**Pexels:**
- https://www.pexels.com/search/lawyer/
- https://www.pexels.com/search/law%20office/
- https://www.pexels.com/search/legal%20consultation/

### Recommended Images (Download 15-18):

**Professional Headshots (4-5):**
- Senior female lawyer (Margaret Sterling)
- Male lawyer in suit (David Chen)
- Female lawyer at desk (Sarah Thompson)
- Male lawyer smiling (Robert Patel)
- Group professional photo

**Office/Facility Images (3-4):**
- Modern law office reception
- Conference room with boardroom table
- Library with law books
- Office exterior or building

**Work/Meeting Images (4-5):**
- Lawyer consulting with client
- Team meeting around conference table
- Lawyer reviewing documents
- Handshake business meeting
- Lawyer at computer

**Detail/Context Images (3-4):**
- Law books and gavel
- Legal documents and contracts
- Scales of justice
- Lawyer's desk with files
- Court building exterior

### Test Image Naming (Before Upload):

**Rename to simulate real-world scenarios:**
- `IMG_9823.jpg` (camera filename)
- `lawyer-photo.jpg` (generic)
- `team-580x300.jpg` (dimension pattern)
- `office-alignment.jpg` (WordPress test term)
- `margaret-sterling.jpg` (specific - should be preserved)
- `consultation.jpg` (generic)
- `featured-office.jpg` (WordPress test term)
- `markup-lawyer.jpg` (WordPress test term)
- `legal-sample.jpg` (generic term)
- `attorney.jpg` (generic but industry-relevant)
- `boardroom-1200x800.jpg` (dimension pattern)
- `courthouse.jpg` (generic but contextual)
- `canola4.jpg` (meaningless)
- `law-books.jpg` (specific - should be preserved)
- `reception-area.jpg` (generic)

---

## Step 6: Upload Images & Review Metadata (5 minutes)

### Upload Process:

1. Go to: http://sterling-law-firm.local/wp-admin
2. Media → Add New
3. Upload all 15-18 images
4. **Do NOT manually edit metadata**

### Expected Metadata Generation:

**For Professional Services (Legal):**

```
Title: Legal Consultation – Sterling & Associates Law | Toronto, Ontario
ALT: Legal consultation at Sterling & Associates Law in Toronto, Ontario
Caption: Experienced Toronto lawyers
Description: Trusted legal counsel for businesses and families since 1987.
            We combine big-firm expertise with personalized service and
            transparent billing. Licensed attorneys serving Greater Toronto
            Area, Downtown Toronto, Financial District, North York.
```

**Key Indicators of Success:**
- ✅ Mentions "legal", "law", "attorney", "counsel" (not healthcare terms)
- ✅ Mentions practice areas or legal services
- ✅ Includes "licensed attorneys" or similar professional designation
- ✅ Service area mentioned (Greater Toronto Area, Financial District)
- ✅ Business name and location appear
- ❌ Should NOT mention: medical, patient, clinical, rehabilitation, treatment

---

## Step 7: Testing Checklist

### Metadata Quality Checks:

- [ ] **Industry-specific language:** Legal/law terms (not healthcare, not HVAC)
- [ ] **Professional designation:** "Licensed attorneys", "experienced lawyers"
- [ ] **Practice areas mentioned:** Corporate, real estate, estate planning, family law
- [ ] **Service area included:** Greater Toronto Area, Financial District, North York
- [ ] **UVP incorporated:** "Since 1987", "big-firm expertise", "personalized service"
- [ ] **Dimension patterns filtered:** No "580x300" in titles
- [ ] **WordPress terms filtered:** No "alignment", "featured", "markup"
- [ ] **Generic terms handled:** "IMG_9823" sanitized appropriately
- [ ] **Specific descriptors preserved:** "margaret-sterling" → "Margaret Sterling"
- [ ] **Business name present:** "Sterling & Associates Law"
- [ ] **Location present:** "Toronto, Ontario"

### Edge Cases to Test:

**Image: `IMG_9823.jpg` (generic camera filename)**
- Should fallback to: "Legal Services – Sterling & Associates Law | Toronto, Ontario"

**Image: `team-580x300.jpg` (dimension pattern)**
- Dimension should be removed: "Team – Sterling & Associates Law | Toronto, Ontario"

**Image: `office-alignment.jpg` (WordPress test term)**
- "Alignment" should be removed: "Office – Sterling & Associates Law | Toronto, Ontario"

**Image: `margaret-sterling.jpg` (specific name)**
- Should preserve: "Margaret Sterling – Sterling & Associates Law | Toronto, Ontario"

**Image: `canola4.jpg` (meaningless)**
- Should fallback to generic legal: "Legal Services – Sterling & Associates Law | Toronto, Ontario"

---

## Expected Results - Legal Industry

### ✅ Correct Legal Metadata:

**Headshot Image:**
```
Title: Margaret Sterling – Sterling & Associates Law | Toronto, Ontario
ALT: Margaret Sterling at Sterling & Associates Law in Toronto, Ontario
Caption: Experienced Toronto lawyers
Description: Trusted legal counsel for businesses and families since 1987.
```

**Office Image:**
```
Title: Law Office – Sterling & Associates Law | Toronto, Ontario
ALT: Law office at Sterling & Associates Law in Toronto, Ontario
Caption: Professional legal environment
Description: We combine big-firm expertise with personalized service and
            transparent billing. Licensed attorneys serving Greater Toronto Area.
```

**Consultation Image:**
```
Title: Legal Consultation – Sterling & Associates Law | Toronto, Ontario
ALT: Legal consultation at Sterling & Associates Law in Toronto, Ontario
Caption: Client-focused legal counsel
Description: Expert guidance through complex legal matters, business transactions,
            estate disputes, and regulatory compliance.
```

---

## What This Tests

### Professional Services Pattern:
- Uses professional designation language ("licensed attorneys", "experienced lawyers")
- Incorporates practice areas (corporate, real estate, estate, family)
- Professional tone (not casual, not medical)
- Service-focused (consultation, counsel, representation)

### Differences from Home Services (HVAC):
- No emergency/24-7 language
- No equipment/technical terms
- Professional credentials (bar admission) vs. technical licenses
- Advisory/strategic vs. repair/installation

### Differences from Healthcare/Wellness:
- Legal services vs. treatments
- Clients vs. patients
- Legal matters vs. health concerns
- Licensed attorneys vs. healthcare practitioners

---

## Troubleshooting

### If Metadata Looks Wrong:

**Check plugin is active:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp plugin list
```

**Check context is set:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_onboarding_context
```

**Check for errors:**
```bash
tail -f "/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public/wp-content/debug.log"
```

---

## Quick Reference

**Site URL:** http://sterling-law-firm.local
**Admin URL:** http://sterling-law-firm.local/wp-admin
**Login:** admin / admin
**Site Path:** `/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public`

**WP-CLI Base:**
```bash
cd "/Users/anastasiavolkova/Local Sites/sterling-law-firm/app/public" && \
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp
```

---

## Next Steps

After confirming legal metadata generates correctly:

1. Compare against wellness metadata (spa vs. legal language)
2. Compare against HVAC metadata (technical vs. professional services)
3. Validate no healthcare bleed into professional services
4. Test with different image types (headshots, office, consultation)
5. Document any issues or improvements needed

---

**Status:** Ready for Phase 2 Testing
**Industry:** Legal (Professional Services)
**Expected Completion:** 45 minutes
**Purpose:** Validate professional services metadata generation distinct from home services and healthcare
