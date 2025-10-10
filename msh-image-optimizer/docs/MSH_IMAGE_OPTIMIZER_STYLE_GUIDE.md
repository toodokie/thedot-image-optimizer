# MSH Image Optimizer – Visual Style Guide

> Living reference for product UI decisions based on The Dot Creative brand standards. Keep branding consistent across every module (wizard, dashboards, settings, modals) unless a future spec explicitly says otherwise.

**Design Philosophy**: Performance, beautifully engineered.
Clean, minimal aesthetic with strategic use of neon yellow accent. Typography-first design with smooth, elegant transitions.

---

## 🎨 Color Palette

### Primary Colors

```css
--background: #faf9f6       /* Cream/off-white - primary background */
--foreground: #35332f       /* Dark charcoal - primary text */
--highlight-color: #daff00  /* Neon yellow-green - brand accent */
--dim-grey: #888            /* Medium grey - subdued elements */
```

**MSH Brand Colors (Same as The Dot Creative):**
- **Primary Ink:** `#35332F` (Dark charcoal)
- **Primary Highlight:** `#DAFF00` (Neon yellow-green)
- **Support Taupe:** `#8B8883` (use for borders, hover, and focus states)
- **Background Off-White:** `#FAF9F6`

### Extended Neutrals

```css
#7a776f     /* Grey-2: borders, subdued text */
#555        /* Dark grey: visited links */
#ccc        /* Light grey: form borders */
#ddd        /* Divider grey */
#eee        /* Very light grey: section dividers */
#f0f0f0     /* Off-white: subtle backgrounds */
#f8f8f6     /* Warm off-white: alternate backgrounds */
#fff        /* Pure white: form backgrounds */
```

### Highlight Variations (with transparency)

```css
#daff00a1   /* 63% opacity */
#daff00cc   /* 80% opacity */
#daff0087   /* 53% opacity */
#daff00cf   /* 81% opacity */
```

### Semantic Colors

```css
/* Success states */
background: #c6f6d5     /* Light green */
color: #276749          /* Dark green text */
border: #9ae6b4         /* Green border */

/* Error states */
background: #fed7d7     /* Light red */
color: #742a2a          /* Dark red text */
border: #fc8181         /* Red border */
```

**Important:** Reuse existing semantic tokens (`status-ready`, `status-warning`, etc.) defined in CSS; do not introduce unvetted colors.

Always audit new UI for contrast (WCAG AA) against the backgrounds listed above.

---

## 🔤 Typography

### Font Families

```css
/* Primary (body text, UI, forms) */
font-family: 'futura-pt', Arial, Helvetica, sans-serif;

/* Display (large headings) */
font-family: 'futura-pt', sans-serif;

/* Subheadings & body emphasis */
font-family: 'ff-real-text-pro', sans-serif;
```

Typeface: `futura-pt` via Typekit per `image-optimizer-admin.css`.

### Type Scale - Responsive Body Text

```css
body {
  font-size: 16px;  /* Mobile default */
}

@media (min-width: 1000px) {
  body {
    font-size: 18px;
  }
}

@media (min-width: 1240px) {
  body {
    font-size: 20px;
  }
}
```

### Heading Scale - Fluid Typography

```css
h1 {
  font-family: 'futura-pt', sans-serif;
  font-size: clamp(3rem, 8vw, 5rem);     /* 48px - 80px */
  font-weight: 400;
  line-height: 1.1;
}

h2 {
  font-family: 'futura-pt', sans-serif;
  font-size: clamp(2.5rem, 6vw, 4rem);   /* 40px - 64px */
  font-weight: 200;
  line-height: 1.2;
}

h3 {
  font-family: 'ff-real-text-pro', sans-serif;
  font-size: clamp(1.5rem, 4vw, 2.375rem); /* 24px - 38px */
  font-weight: 300;
  line-height: 1.3;
}

h4 {
  font-family: 'ff-real-text-pro', sans-serif;
  font-size: clamp(1.25rem, 3vw, 1.875rem); /* 20px - 30px */
  font-weight: 300;
  line-height: 1.4;
}
```

### Font Weights

```
200 - Ultra-light   → Body copy, list items, h2
300 - Light         → h3, h4, form fields, labels
400 - Regular       → h1, form section titles, emphasis
500 - Medium        → Strong elements, bold within light text
600 - Semibold      → Buttons (rare use)
700 - Bold          → Special emphasis only (very rare)
```

**This is a light brand** - use 200-400 for most elements. Bold (700) should be extremely rare.

### Letter Spacing

```css
letter-spacing: -0.01em  /* Large display text only */
```

### Line Heights

```
h1: 1.1
h2: 1.2
h3: 1.3
h4: 1.4
body: 1.6
```

**MSH Notes:**
- Headings: uppercase lockups already handled in CSS; don't override per component.
- Body copy: default admin font sizing; front-load key phrases in bold instead of adding new heading levels.
- Let breathing room exist - this isn't a dense design.

---

## 🔗 Links

### Content Links (Default)

```css
a {
  color: #35332f;           /* Foreground */
  text-decoration: underline;
}

a:hover {
  color: #888;              /* Dim grey */
  text-decoration: underline;
}

a:visited {
  color: #555;              /* Dark grey */
  text-decoration: underline;
}
```

### Navigation Links (No Underline)

```css
.nav-link,
.msh-support-link,
.msh-website-link {
  text-decoration: none !important;
}

.nav-link:hover,
.msh-support-link:hover,
.msh-website-link:hover {
  color: #8B8883;                      /* Support Taupe */
  text-decoration: none !important;
}

.nav-link:visited {
  text-decoration: none !important;
}
```

---

## 📝 Form & Input Field Styling

**Reference:** The Dot Creative Brief Form (`wf-form-Website-Form`)

### 🚨 Critical Form Styling Rules

**NEVER DO THIS:**
```css
/* ❌ WRONG - Blue colors */
border: 1px solid #cbd5e0;           /* NO - use #ccc */
border-color: #4299e1;               /* NO - use #35332f */
background: #4299e1;                 /* NO - use cream/yellow */
```

**ALWAYS DO THIS:**
```css
/* ✅ CORRECT - The Dot colors */
border: 1px solid #ccc;              /* Light grey border */
border-color: #35332f;               /* Charcoal focus */
background: var(--background);       /* Cream (#faf9f6) */
background-color: #daff00;           /* Yellow highlight hover */
```

**Universal Form Specifications:**
- **Border:** `1px solid #ccc` (NOT `#cbd5e0` - no blue-greys!)
- **Border-radius:** `4px` inputs, `6px` buttons
- **Height:** `60px` (all inputs, selects)
- **Padding:** `10px 15px` desktop, `12px 16px` mobile
- **Font-family:** `futura-pt, sans-serif` (ALL form elements)
- **Font-size:** `1.4rem` (all input text), `1.125rem` (labels)
- **Font-weight:** `300` (light) for inputs/labels, `200` mobile labels
- **Focus border:** `#35332f` charcoal (NEVER `#4299e1` blue!)
- **Focus glow:** `box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1)`
- **Hover (selects):** `border-color: #8B8883` (Support Taupe)
- **Button hover:** `background-color: #daff00` (Yellow, NOT darker blue)
- **Radio buttons:** VERTICAL stacking ONLY (`flex-direction: column`)

**Width Hierarchy (The Dot Standard):**
- Short inputs (name, email): **60%** desktop → **100%** mobile
- Medium textareas: **80%** desktop → **100%** mobile
- Large textareas: **100%** always
- MSH admin: **100%** within containers

### Form Section Headers

```css
.dot_forms_title.sites,
.msh-section-title {
  color: var(--foreground);           /* #35332f */
  text-align: left;
  margin-top: 2rem;
  margin-bottom: 30px;
  font-family: futura-pt, sans-serif;
  font-size: 1.8rem;
  font-weight: 400;
  line-height: 1.3;
}

@media (max-width: 768px) {
  .dot_forms_title.sites,
  .msh-section-title {
    font-size: 1.5rem;
  }
}
```

### Form Labels

```css
.dot_field_label,
.msh-label,
.form-group label {
  color: var(--foreground);
  display: block;
  margin-top: 6px;
  margin-bottom: 10px;
  padding-left: 0;
  padding-right: 0;
  font-family: futura-pt, sans-serif;  /* THE DOT STANDARD */
  font-size: 1.125rem;                 /* 18px */
  font-weight: 300;                    /* Light */
  line-height: 1.3;
}

@media (max-width: 768px) {
  .dot_field_label,
  .msh-label,
  .form-group label {
    text-align: left;
    font-weight: 200 !important;       /* Ultra-light on mobile */
    font-size: 1rem !important;        /* 16px */
  }
}
```

**Important:** Labels use `futura-pt` (not `ff-real-text-pro`), font-weight `300` (light), and size `1.125rem` (18px) per The Dot Creative standard.

### Text Inputs (Short Fields - Name, Email)

```css
.text-field-3 {
  color: var(--foreground);
  width: 60%;                         /* Desktop: 60% of container */
  height: 60px;
  margin-top: 10px;
  margin-bottom: 20px;
  padding: 10px 15px;
  font-family: futura-pt, sans-serif;
  font-size: 1.4rem;
  font-weight: 300;
  line-height: 1.3;
  border: 1px solid #ccc;             /* THE DOT STANDARD - NOT #cbd5e0 */
  border-radius: 4px;
  background-color: #fff;
  transition: border-color 0.2s;
}

/* Focus state - NO BLUE! Use charcoal */
.text-field-3:focus,
input[type="text"]:focus,
input[type="email"]:focus,
input[type="url"]:focus {
  outline: none;
  border-color: #35332f;              /* Charcoal, NOT blue (#4299e1) */
  box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);  /* Charcoal glow */
}

@media (max-width: 768px) {
  .text-field-3 {
    width: 100%;                      /* Mobile: full width */
    max-width: 100%;
    box-sizing: border-box;
    padding: 12px 16px;
    margin: 0;
  }
}
```

**MSH WordPress Admin Variant (Full Width):**
```css
/* For MSH admin forms - full width within containers */
.msh-input,
.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="url"] {
  color: var(--foreground);
  width: 100%;                        /* Full width in MSH admin */
  height: 60px;
  margin-top: 10px;
  margin-bottom: 20px;
  padding: 10px 15px;
  font-family: futura-pt, sans-serif;
  font-size: 1.4rem;
  font-weight: 300;
  line-height: 1.3;
  border: 1px solid #ccc;
  border-radius: 4px;
  background-color: #fff;
  transition: border-color 0.2s;
}

.msh-input:focus {
  outline: none;
  border-color: #35332f;
  box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);
}

@media (max-width: 768px) {
  .msh-input {
    padding: 12px 16px;
  }
}
```

**Key Specifications:**
- **Border:** `1px solid #ccc` (NOT `#cbd5e0` - no blue-greys!)
- **Border-radius:** `4px` (subtle rounding)
- **Height:** `60px` (consistent across all inputs)
- **Padding:** `10px 15px` desktop, `12px 16px` mobile
- **Font-size:** `1.4rem` (consistent for all input text)
- **Font-weight:** `300` (light)
- **Focus border:** `#35332f` charcoal (NOT `#4299e1` blue!)
- **Focus glow:** `rgba(53, 51, 47, 0.1)` charcoal (NOT blue!)

### Textareas (Medium Fields)

```css
.text-filed-3 {                       /* Note: typo is intentional in class name */
  color: var(--foreground);
  width: 80%;                         /* Desktop: 80% of container */
  height: 60px;                       /* Initial height */
  margin-top: 10px;
  margin-bottom: 20px;
  padding: 10px 15px;
  font-family: futura-pt, sans-serif;
  font-size: 1.4rem;
  font-weight: 300;
  border: 1px solid #ccc;
  border-radius: 4px;
  background-color: #fff;
  resize: vertical;                   /* Allow vertical resize */
  transition: border-color 0.2s;
}

.text-filed-3:focus {
  outline: none;
  border-color: #35332f;
  box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);
}

@media (max-width: 768px) {
  .text-filed-3 {
    width: 100%;                      /* Mobile: full width */
    max-width: 100%;
    box-sizing: border-box;
    padding: 12px 16px;
    margin: 0;
  }
}
```

### Textareas (Large Fields)

```css
.text-area-field-4,
.form-group textarea {
  color: var(--foreground);
  width: 100%;                        /* Always full width */
  min-height: 120px;
  margin-top: 10px;
  margin-bottom: 30px;
  padding: 10px 15px;
  font-family: futura-pt, sans-serif;
  font-size: 1.4rem;
  font-weight: 300;
  line-height: 1.3;
  border: 1px solid #ccc;
  border-radius: 4px;
  background-color: #fff;
  resize: vertical;
  transition: border-color 0.2s;
}

.text-area-field-4:focus,
textarea:focus {
  outline: none;
  border-color: #35332f;
  box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);
}

@media (max-width: 768px) {
  .text-area-field-4 {
    padding: 12px 16px;
  }
}
```

**Key Specifications:**
- **Inherit all text input styling** (border, radius, colors, fonts)
- **Width hierarchy:** 80% (medium) → 100% (large) → 100% mobile
- **Min-height:** `120px` for large textareas
- **Resize:** `vertical` only (prevent horizontal stretching)
- **Padding:** Same as inputs - `10px 15px` desktop, `12px 16px` mobile
- **Focus:** Same charcoal treatment as inputs

### Select Dropdowns

```css
select,
.form-group select {
  color: var(--foreground);
  width: 100%;
  height: 60px;
  padding: 10px 15px;
  font-family: futura-pt, sans-serif;
  font-size: 1.4rem;
  font-weight: 300;
  border: 1px solid #ccc;
  border-radius: 4px;
  background-color: #fff;
  cursor: pointer;
  transition: border-color 0.2s;
}

select:focus {
  outline: none;
  border-color: #35332f;              /* Charcoal, NOT blue */
  box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);
}

select:hover {
  border-color: #8B8883;              /* Support Taupe on hover */
}
```

**Key Specifications:**
- **Inherit all text input styling** (same height, padding, fonts, borders)
- **Cursor:** `pointer` (indicates interactivity)
- **Hover:** Border color changes to `#8B8883` (Support Taupe)
- **Focus:** Same charcoal treatment as inputs
- **Custom dropdown arrow:** Use CSS background gradients (no native browser styling)

### Radio Buttons (Vertical Stack)

```css
.radio-button-field,
.radio-group {
  display: flex;
  flex-direction: column;             /* THE DOT STANDARD: VERTICAL ONLY */
  gap: 15px;                          /* Spacing between radio options */
  margin-top: 10px;
}

.radio-button-field label,
.radio-group label {
  display: flex;
  align-items: center;
  gap: 10px;                          /* Space between radio and text */
  margin-bottom: 0;
  cursor: pointer;
  font-family: futura-pt, sans-serif;
  font-size: 1.125rem;
  font-weight: 300;
  line-height: 1.3;
  color: var(--foreground);
}

.radio-button-field input[type="radio"],
.radio-group input[type="radio"] {
  cursor: pointer;
  margin-right: 0;                    /* Gap handles spacing */
}

/* Mobile: already vertical */
@media (max-width: 768px) {
  .radio-button-field,
  .radio-group {
    gap: 12px;
  }
}
```

**Critical:** The Dot Creative standard is **VERTICAL stacking ONLY** for radio buttons. Never create horizontal radio button layouts.

### Checkboxes

```css
.checkbox-field {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--foreground);
  margin-bottom: 10px;
  font-family: futura-pt, sans-serif;
  font-size: 1.125rem;
  font-weight: 300;
  line-height: 1.3;
  cursor: pointer;
}

.checkbox-field input[type="checkbox"] {
  cursor: pointer;
  margin-right: 0;                    /* Gap handles spacing */
}
```

### Range Sliders

```css
.slider-container {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-top: 10px;
}

.slider-container input[type="range"] {
  flex: 1;
  height: 8px;
  -webkit-appearance: none;
  background: #e2e8f0;
  border-radius: 4px;
  outline: none;
}

/* Slider thumb */
input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 24px;
  height: 24px;
  background: #35332f;                /* Charcoal, NOT blue */
  border-radius: 50%;
  cursor: pointer;
  transition: background-color 0.2s;
}

input[type="range"]::-webkit-slider-thumb:hover {
  background: #daff00;                /* Highlight on hover */
}

/* Value display */
.slider-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: #35332f;                     /* Charcoal, NOT blue */
  min-width: 40px;
  text-align: center;
}

/* Min/max labels */
.slider-labels {
  display: flex;
  justify-content: space-between;
  margin-top: 8px;
  font-size: 0.875rem;
  color: #718096;
}
```

### Submit Buttons

```css
.button-dot-primary,
.submit-button {
  color: var(--foreground);
  background: var(--background);      /* Cream background, NOT blue */
  padding: 16px 32px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-family: 'futura-pt', sans-serif;
  font-size: 1.125rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 2px;
  cursor: pointer;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
              background-color 0.3s ease,
              border-color 0.2s;
  margin-top: 20px;
}

.button-dot-primary:hover:not(:disabled),
.submit-button:hover:not(:disabled) {
  transform: scale(1.02);
  border-color: #35332F;
  background-color: #daff00;          /* Highlight on hover */
}

.button-dot-primary:disabled,
.submit-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Mobile: full width */
@media (max-width: 768px) {
  .button-dot-primary,
  .submit-button {
    width: 100%;
    font-size: 1rem;
  }
}
```

**MSH Note:** Stick to the `button-dot-primary` / `button-dot-secondary` combinations already registered. New buttons should inherit these classes rather than creating bespoke variants. **NO BLUE BUTTONS.**

### Form Container

```css
.form-container {
  max-width: 800px;                   /* NEVER exceed 800px */
  margin: 0 auto;                     /* Center horizontally */
  background: white;
  border-radius: 12px;
  padding: 40px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
              0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

/* Mobile */
@media (max-width: 768px) {
  .form-container,
  .form-container.w-container,
  .form-container-web-section.w-container {
    padding: 30px 20px;
    padding-left: 15px;
    padding-right: 15px;
    max-width: 100%;
  }

  .website-form {
    width: 100%;
    max-width: 100%;
  }
}
```

### Form Sections

```css
.form-section {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 30px;
}

.form-section:last-of-type {
  border-bottom: none;
}

.form-section h3 {
  font-size: 1.5rem;
  color: #2d3748;
  margin-bottom: 20px;
  font-weight: 600;
}
```

### Form Groups (spacing)

```css
.form-group {
  margin-bottom: 25px;
}

.efficiency-form {
  display: flex;
  flex-direction: column;
  gap: 40px;
}
```

### Success/Error Messages

```css
.success-message,
.error-message {
  padding: 16px;
  border-radius: 6px;
  margin-top: 20px;
  text-align: center;
}

.success-message {
  background-color: #c6f6d5;
  color: #276749;
  border: 1px solid #9ae6b4;
}

.error-message {
  background-color: #fed7d7;
  color: #742a2a;
  border: 1px solid #fc8181;
}
```

### Form Field Summary

| Field Type | Class | Desktop Width | Mobile Width | Font Size | Height |
|------------|-------|---------------|--------------|-----------|--------|
| Short input (name, email) | `.text-field-3` | 60% | 100% | 1.4rem | 60px |
| Medium textarea | `.text-filed-3` | 80% | 100% | 1.4rem | 60px |
| Large textarea | `.text-area-field-4` | 100% | 100% | 1.4rem | 120px min |
| MSH full-width input | `.msh-input` | 100% | 100% | 1.4rem | 60px |
| Label | `.msh-label` | N/A | N/A | 1.125rem → 1rem | N/A |
| Section title | `.msh-section-title` | N/A | N/A | 1.8rem → 1.5rem | N/A |
| Radio/Checkbox | `.radio-button-field` | N/A | N/A | 1.125rem | N/A |

---

## 📐 Form Layout & Positioning Guide

### Overview

Forms should be centered, contained, and easy to scan. Never let forms stretch edge-to-edge or create uneven field widths. Follow these positioning rules to maintain visual consistency.

### Page-Level Layout

#### Section Container

```css
.efficiency-brief-section {
  min-height: 100vh;
  padding: 100px 20px 50px;  /* Top accounts for fixed header */
  background-color: #f8f9fa;
}

/* Mobile */
@media (max-width: 768px) {
  .efficiency-brief-section {
    padding: 80px 15px 40px;
  }
}
```

**Rules:**
- ✅ Always add top padding (100px) to account for fixed header
- ✅ Use horizontal padding (20px min) to prevent edge-to-edge content
- ✅ Use light background (#f8f9fa) to visually separate form from rest of page

### Form Centering & Max Width

#### Hero/Title Section

```css
.hero-section {
  text-align: center;
  max-width: 800px;
  margin: 0 auto 60px;  /* Center horizontally, 60px bottom spacing */
}
```

#### Form Container

**Rules:**
- ✅ ALWAYS set max-width: 800px for form containers
- ✅ ALWAYS center with margin: 0 auto
- ✅ Add generous padding (40px desktop, 30-20px mobile)
- ❌ NEVER let forms exceed 800px width
- ❌ NEVER create full-width forms without containers

### Vertical Spacing Hierarchy

#### Form Structure Spacing

```css
/* Top-level form */
.efficiency-form {
  display: flex;
  flex-direction: column;
  gap: 40px;  /* Large spacing between major sections */
}

/* Form header */
.form-header {
  margin-bottom: 40px;  /* Breathing room before form starts */
}

/* Form sections */
.form-section {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 30px;  /* Space before divider */
}

/* Individual field groups */
.form-group {
  margin-bottom: 25px;  /* Standard spacing between fields */
}
```

**Spacing Scale:**
- `60px` → Major page sections (hero to form, section breaks)
- `40px` → Between form sections, major containers
- `30px` → Section padding-bottom, form header bottom
- `25px` → Between individual form fields
- `20px` → Input bottom margin, button top margin
- `15px` → Radio/checkbox bottom margin
- `10px` → Label bottom margin, input top margin
- `8px`  → Small gaps (label to input)
- `6px`  → Label top margin

**Rules:**
- ✅ Use consistent spacing scale throughout
- ✅ Larger gaps = more important hierarchy breaks
- ✅ Always separate logical sections with borders + padding
- ❌ Don't create uneven spacing between similar elements

**MSH Note:** Spacing increments: 4px, 8px, 12px, 18px, 28px, 32px, 40px. Only deviate if an accessibility issue arises.

### Input Field Width Rules

#### The Dot Creative Width Hierarchy

**Desktop:**
- **Short inputs** (name, email): `60%` width
- **Medium textareas**: `80%` width
- **Large textareas**: `100%` width

**Mobile:**
- **All inputs**: `100%` width (always)

**MSH WordPress Admin:**
- **All inputs**: `100%` width (within constrained containers)
- Use `max-width: 400px` for optimal readability where appropriate

**Rules:**
- ✅ Follow width hierarchy: 60% → 80% → 100%
- ✅ Text inputs, emails, URLs: Always 100% width (or follow hierarchy)
- ✅ Textareas: Always 100% width (or follow hierarchy)
- ✅ Select dropdowns: Always 100% width
- ❌ NEVER set fixed pixel widths (e.g., width: 300px)
- ❌ NEVER create different widths for similar fields

### Multi-Field Layouts

#### Radio Button Groups (VERTICAL Stack)

**Important:** The Dot Creative specifies vertical stacking, not horizontal.

```css
.radio-group {
  display: flex;
  flex-direction: column;             /* VERTICAL */
  gap: 15px;
  margin-top: 10px;
}

.radio-group label {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 0;
  cursor: pointer;
}
```

**Rules:**
- ✅ Radio buttons: VERTICAL stacking (The Dot standard)
- ✅ Use flexbox with consistent gaps (15px)
- ✅ Align items vertically centered
- ❌ Don't create horizontal radio buttons

#### Range Sliders with Value Display

```css
.slider-container {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-top: 10px;
}

.slider-container input[type="range"] {
  flex: 1;  /* Takes available space */
}

.slider-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: #35332f;                     /* Charcoal */
  min-width: 40px;  /* Prevent jumping */
  text-align: center;
}
```

**Rules:**
- ✅ Slider should flex to fill space, value fixed width
- ✅ Use gap for consistent spacing
- ✅ Set min-width on value to prevent layout shift
- ✅ Min/max labels should span full width with space-between

### Label Positioning

#### Vertical Labels (Standard)

```css
.form-group label {
  display: block;
  margin-top: 6px;
  margin-bottom: 10px;
  color: var(--foreground);
  font-weight: 300;
}
```

**Rules:**
- ✅ ALWAYS use vertical labels above inputs (block display)
- ✅ 8-10px gap between label and input
- ❌ NEVER use side-by-side labels and inputs
- ❌ NEVER use inline labels (except for radio/checkbox groups)

#### Exception: Radio/Checkbox Labels

```css
.radio-group label {
  display: flex;  /* Inline with control */
  align-items: center;
  gap: 10px;
}
```

### Button Positioning

#### Submit Buttons

```css
.submit-button {
  padding: 16px 32px;
  margin-top: 20px;  /* Separate from last field */
  /* Positioned at natural flow end */
}

/* Mobile: Full width */
@media (max-width: 768px) {
  .submit-button {
    width: 100%;
  }
}
```

**Rules:**
- ✅ Left-aligned at natural document flow (not centered)
- ✅ 20px+ margin-top to separate from fields
- ✅ Full-width on mobile for easy tapping
- ❌ Don't center buttons unless it's a single-button form
- ❌ Don't float buttons to the right

### Message Positioning

#### Success/Error Messages

```css
.success-message,
.error-message {
  padding: 16px;
  border-radius: 6px;
  margin-top: 20px;
  text-align: center;  /* Centered text only */
}
```

**Rules:**
- ✅ Display below the form (margin-top: 20px)
- ✅ Center text inside the message box
- ✅ Full-width within form container
- ❌ Don't position absolutely or float

### Responsive Breakpoint

```css
@media (max-width: 768px) {
  /* Reduce padding */
  .form-container { padding: 30px 20px; }

  /* All inputs full width */
  input, textarea, select { width: 100%; }

  /* Full-width buttons */
  .submit-button { width: 100%; }
}
```

**Rules:**
- ✅ Use 768px as the mobile breakpoint
- ✅ Reduce padding on mobile but maintain structure
- ✅ Make all inputs full-width
- ✅ Make buttons full-width for easier tapping

### Container Padding

```css
/* Desktop */
padding: 40px;                    /* Standard containers */
padding: 100px 20px 50px;         /* Page sections (100px for header) */
padding: 2.5rem;                  /* 40px - standard horizontal */

/* Mobile (max-width: 768px) */
padding: 30px 20px;               /* Standard containers */
padding: 80px 15px 40px;          /* Page sections (80px for header) */
padding: 15px;                    /* Tight mobile spacing */
```

### Max-Width Standards

```css
max-width: 120rem;                /* 1920px - main page container */
max-width: 800px;                 /* Forms, centered content blocks */
```

### Quick Reference: Layout Checklist

**✅ Container:**
- Max-width: 800px
- Centered with margin: 0 auto
- Padding: 40px (desktop), 30-20px (mobile)

**✅ Fields:**
- Width: Follow hierarchy (60% → 80% → 100%) or 100% in MSH admin
- Spacing: 25px between groups
- Labels: Vertical (block) with 8-10px bottom margin
- Border: 1px solid #ccc (NOT blue-greys)
- Focus: Charcoal border + glow (NOT blue)

**✅ Sections:**
- Gap: 40px between major sections
- Dividers: 1px border + 30px padding-bottom

**✅ Buttons:**
- Natural flow position (left-aligned)
- 20px+ top margin
- Full-width on mobile
- NO BLUE - use cream background with charcoal text

**✅ Mobile:**
- Breakpoint: 768px
- All inputs full-width
- Reduce padding, maintain structure

---

## 🎭 Shadows

### Box Shadows

```css
/* Subtle elevation (cards, containers) */
box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
            0 2px 4px -1px rgba(0, 0, 0, 0.06);

/* Deeper elevation (modals, dropdowns) */
box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);

/* Focus glow - CHARCOAL, NOT BLUE */
box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);  /* Charcoal tint */

/* Radio buttons */
box-shadow: none !important;  /* Remove default */
```

**MSH Note:** Card rails (wizard, summary, diagnostics) all use `12px` radius and subtle shadow (`rgba(15, 23, 42, 0.08)`). New panels should match dimensions before exploring alternatives.

---

## 📦 Borders & Shapes

### Border Radius

```
4px     → Subtle (form inputs, sliders, small elements)
6px     → Standard (buttons, messages, cards)
12px    → Large containers, card rails
24px    → XL containers, bottom-only mobile nav
50%     → Circular elements (avatars, dots, radio buttons)
```

### Border Styles

```css
/* Subtle dividers */
border: 1px solid #e2e8f0;
border: 1px solid #ddd;
border: 1px solid #eee;
border: 1px solid #ccc;         /* Form inputs - THE DOT STANDARD */

/* Defined borders */
border: 1px solid #7a776f;      /* Medium grey */
border: 1px solid #35332f;      /* Dark charcoal */

/* Brand accent borders */
border: 1px solid #daff00;      /* Standard */
border: 2px solid #daff00;      /* Emphasis */
border: 3px solid #daff00;      /* Strong emphasis */
border: 4px solid #daff00;      /* Very strong */

/* Section dividers */
border-bottom: 1px solid #e2e8f0;    /* Form section separators */
border-bottom: 1px solid #7a776f;    /* Header when scrolled */
```

---

## ✨ Animations & Transitions

### Timing Functions

```css
/* Smooth & elegant (most common) */
cubic-bezier(0.25, 0.46, 0.45, 0.94)

/* Snappy UI interactions */
cubic-bezier(0.4, 0, 0.2, 1)

/* Simple easing */
ease, ease-out, ease-in
```

### Duration Standards

```
0.2s  → Fast interactions (hover, focus, border-color)
0.3s  → Standard UI (opacity, simple transforms)
0.5s  → Medium transforms
0.6s  → Page transitions (height changes)
0.8s  → Elegant page transitions (smooth fades)
```

### Common Transition Patterns

```css
/* Interactive elements (hover, focus) */
transition: background-color 0.2s;
transition: border-color 0.2s;
transition: color 0.2s;

/* Transforms */
transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);

/* Smooth fades */
transition: opacity 0.3s ease;
transition: opacity 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);

/* Layout changes */
transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
transition: height 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
transition: width 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);

/* Combined */
transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1),
            background-color 0.3s ease;

/* Form inputs */
transition: border-color 0.2s;
transition: background-color 0.2s;

/* UI elements */
transition: all 0.3s ease;
transition: opacity 0.3s ease;
transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
```

### Staggered Animations

```css
.animate-on-scroll:nth-child(1) { transition-delay: 0s; }
.animate-on-scroll:nth-child(2) { transition-delay: 0.2s; }
.animate-on-scroll:nth-child(3) { transition-delay: 0.4s; }
.animate-on-scroll:nth-child(4) { transition-delay: 0.6s; }
/* Continue pattern: +0.2s per item */
```

---

## 🎨 Gradients

### Brand Gradients

```css
/* Subtle yellow glow (hero sections) */
background-image: radial-gradient(
  circle farthest-corner at 100% 0%,
  #daff00cf,
  #eefb9dbd 34%,
  #faf9f6 53%
);

/* Radial fade from edge */
background-image: radial-gradient(
  circle farthest-corner at 100% 50%,
  #daff00a1,
  #faf9f6
);

/* Soft diagonal */
background: linear-gradient(135deg, #daff00 0%, #faf9f6 100%);

/* Subtle vertical */
background-image: linear-gradient(to bottom, #faf9f6, #daff00a3);

/* Double layer (background + image) */
background-color: #daff00ad;
background-image: radial-gradient(
  circle farthest-corner at 50% 50%,
  #daff00cc,
  #fff
);
```

---

## 📱 Responsive Breakpoints

```css
/* Mobile first approach */

/* Mobile */
@media (max-width: 768px) { }

/* Small tablet */
@media (min-width: 769px) { }

/* Medium Desktop */
@media (min-width: 1000px) { }

/* Large Desktop */
@media (min-width: 1240px) { }

/* Alternative mobile-first queries */
@media (width <= 768px) { }
@media (width <= 999px) { }
```

---

## 🎯 Interactive Elements

### General Principles

- **Focus / Active:** custom border + glow using `#35332f` (charcoal). **NEVER allow default browser blue outlines to appear.** When introducing a new control, apply the `.msh-input`, `.msh-select`, or `.msh-textarea` classes (or extend them) so the shared focus styles kick in.
- **Hover:** lighten background with taupe overlay (`rgba(139, 136, 131, 0.08)`) or use `#8B8883` color change.
- **Buttons:** stick to the `button-dot-primary` / `button-dot-secondary` combinations already registered. New buttons should inherit these classes rather than creating bespoke variants. **NO BLUE BUTTONS.**
- **Drop-down Arrows:** rely on the custom background gradients already defined—do not fall back to native select styling.

---

## 🚫 What NOT to Do

### ❌ Colors
- **Use colors outside the brand palette** - especially NO BLUES for focus states or buttons
- Add purples, teals, or other accent colors
- Use black (#000) instead of charcoal (#35332f)
- Mix different greys randomly - stick to the palette
- Use `#cbd5e0` or `#4299e1` (blue-greys and blues) - use `#ccc` and `#35332f` instead

### ❌ Typography
- Use bold (700) liberally - **this is a light brand** (200-400 primarily)
- Use fonts other than futura-pt and ff-real-text-pro
- Create fixed pixel sizes - use responsive scales
- Forget letter-spacing on large display text

### ❌ Forms
- Set fixed pixel widths on inputs (e.g., `width: 300px`)
- Create forms wider than 800px on desktop
- Use inline labels beside inputs (except radio/checkbox)
- Ignore The Dot width hierarchy (60% → 80% → 100%)
- **Use different border colors** - stick to `#ccc`
- **Add focus styles with blues** - use charcoal `#35332f`
- Make horizontal radio buttons - stack vertically
- Use blue button colors - use cream/charcoal with yellow highlight

### ❌ Layout
- Create edge-to-edge forms without containers
- Center buttons without context
- Use random spacing values - follow the hierarchy
- Mix up the responsive breakpoints
- Create full-width forms without max-width containers

### ❌ Effects
- Overuse shadows - keep it minimal
- Add heavy animations - this is an elegant brand
- Use instant transitions - always define timing (0.2s minimum)
- Create jarring color changes

### ❌ Interactive Elements
- No inline colors, shadows, or font overrides
- No new accent colors without sign-off
- **Do not ship browser-default outlines or focus rings**—swap in brand styling first
- Don't create full-width forms without max-width containers
- Don't use fixed pixel widths for form inputs
- Don't create inline labels (except for radio/checkbox groups)

---

## ✅ Best Practices

### Colors
- Use cream (#faf9f6) for backgrounds
- Use charcoal (#35332f) for text
- Use neon yellow (#daff00) **strategically** as accent - not everywhere
- Use greys from the palette (#ccc, #7a776f, #888) for borders and subdued text
- **Use charcoal for focus states, NOT blue**

### Typography
- **Keep it light** (200-400 weight primarily)
- Use fluid typography with clamp()
- Maintain consistent line heights
- Let breathing room exist - this isn't a dense design
- Use 1.4rem for all form input text
- Use 1.125rem for labels

### Forms
- Follow The Dot width hierarchy: 60% → 80% → 100% (or 100% in MSH admin)
- **Stack radio buttons vertically** (not horizontal)
- Use consistent `1.4rem` for all input text
- Use consistent `60px` height for inputs
- Use `#ccc` for borders, `#35332f` for focus
- Maintain proper spacing between fields
- Always go full-width on mobile
- Use `border-radius: 4px` for inputs, `6px` for buttons

### Layout
- Center content containers with `margin: 0 auto`
- Use max-width constraints (800px forms, 120rem page)
- Account for fixed header (100px/80px top padding)
- Let forms breathe - generous spacing (40px, 30px, 25px hierarchy)
- Use consistent spacing scale (4px, 8px, 12px, 18px, 28px, 32px, 40px)

### Effects
- Use subtle shadows for elevation
- Smooth transitions (0.2s - 0.8s)
- Elegant easing functions: `cubic-bezier(0.25, 0.46, 0.45, 0.94)`
- Stagger animations when appropriate (+0.2s per item)

### Interactive Elements
- Reuse shared CSS helpers (`.msh-input`, `.summary-grid`, `.wizard-step`, etc.)
- Verify hover/focus states in the browser before handoff; QA hates chasing blue halos
- Document deviations here when product approves exceptions
- Maintain WCAG AA contrast ratios
- **Use charcoal (#35332f) for focus states with subtle glow**
- Hover with taupe (#8B8883) or slight transforms

---

## 📚 Key Reference Files

- **MSH Main admin page**: `admin/image-optimizer-admin.php`
- **MSH Main styles**: `assets/css/image-optimizer-admin.css`
- **MSH JavaScript**: `assets/js/image-optimizer-modern.js`

---

## 🎯 Common Layout Mistakes to Avoid

### ❌ DON'T:

```css
/* Edge-to-edge forms */
.form-container {
  width: 100%;  /* NO! Use max-width: 800px */
}

/* Fixed widths that break responsiveness */
input {
  width: 300px;  /* NO! Use 100% or follow width hierarchy */
}

/* Inline labels */
label {
  display: inline-block;  /* NO! Use block */
  width: 150px;
}

/* Centered buttons without context */
.submit-button {
  margin: 0 auto;  /* NO! Natural flow */
  display: block;
}

/* Random spacing */
.form-group {
  margin-bottom: 17px;  /* NO! Use spacing scale */
}

/* Blue colors */
input:focus {
  border-color: #4299e1;  /* NO! Use #35332f */
}

button {
  background: #4299e1;  /* NO! Use cream/yellow */
}
```

### ✅ DO:

```css
/* Contained, centered forms */
.form-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 40px;
}

/* Responsive widths */
input {
  width: 100%;  /* Or follow 60%/80% hierarchy */
}

/* Vertical labels */
label {
  display: block;
  margin-bottom: 8px;
}

/* Natural flow buttons */
.submit-button {
  margin-top: 20px;
}

/* Consistent spacing */
.form-group {
  margin-bottom: 25px;
}

/* Brand colors */
input:focus {
  border-color: #35332f;  /* Charcoal */
  box-shadow: 0 0 0 3px rgba(53, 51, 47, 0.1);
}

button {
  background: var(--background);  /* Cream */
  color: var(--foreground);  /* Charcoal */
}

button:hover {
  background-color: #daff00;  /* Yellow highlight */
}
```

---

**Remember**: This is a clean, minimal, typography-first brand with strategic use of neon yellow. Keep it light, elegant, and spacious. **Performance, beautifully engineered.** 🚀

**NO BLUES. Use charcoal (#35332f) for focus states and text, cream (#faf9f6) for backgrounds, and neon yellow (#daff00) for strategic accents.**

Keep this file updated when we expand the design surface (settings integration, AI workflows, etc.) so future contributors know the rules.
