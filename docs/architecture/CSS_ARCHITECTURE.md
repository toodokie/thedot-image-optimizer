# MSH Image Optimizer - CSS Architecture

**The Dot Creative Agency Brand**
**Monochrome, minimalistic, professional**

---

## 🎯 Architecture Overview

Our CSS system follows a **layered cascade** from foundational variables to page-specific styling:

```
┌─────────────────────────────────────────┐
│  Layer 1: Foundation (Variables)       │
│  - brand-guidelines.css                 │
│  - typography-variables.css             │
└─────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────┐
│  Layer 2: Global Admin Patterns         │
│  - global-admin.css                     │
│    • Page backgrounds (#FAF9F6 cream)   │
│    • Section cards (cream + #ddd)       │
│    • Page headers (logo + support)      │
│    • Form elements                      │
│    • Tables                             │
│    • Typography                         │
└─────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────┐
│  Layer 3: Page-Specific Styles          │
│  - image-optimizer-admin.css            │
│  - image-optimizer-settings.css         │
│  - dashboard.css                        │
│  - hub.css                              │
│  - phase4-admin.css                     │
│  - template-admin.css                   │
└─────────────────────────────────────────┘
          ↓
┌─────────────────────────────────────────┐
│  Layer 4: Component/Widget Styles       │
│  - confidence-indicators.css            │
│  - tinydot-loader.css                   │
│  - admin-menu-branding.css              │
│  - wp-list-table-branding.css           │
└─────────────────────────────────────────┘
```

---

## 📁 File Inventory

### Layer 1: Foundation (Load First)

#### `brand-guidelines.css`
**Purpose:** CSS custom properties (variables) for colors, spacing, typography
**Contains:**
- Color variables: `--msh-charcoal`, `--msh-cream`, `--msh-warm-gray`, etc.
- Spacing scales: `--section-padding-*`, `--section-gap-*`
- Typography tokens: `--font-primary`, `--font-secondary`, font sizes
- Border radius, shadows, focus rings

**Load Order:** #1 (required by everything)
**Dependencies:** None

#### `typography-variables.css`
**Purpose:** Typography system variables
**Contains:**
- Font stacks: `--font-primary`, `--font-secondary`
- Font sizes: `--text-h1`, `--text-base`, `--text-xs`, etc.
- Font weights: `--weight-normal`, `--weight-light`, etc.
- Line heights: `--leading-relaxed`, `--leading-normal`

**Load Order:** Imported by other files via `@import`
**Dependencies:** None

---

### Layer 2: Global Admin Patterns

#### `global-admin.css` ⭐ NEW
**Purpose:** Shared styling for ALL admin pages
**Contains:**
- Page background (`html, body, #wpcontent, #wpbody, #wpbody-content`)
- Page header (logo + support links)
- Section card pattern (`.msh-section-card` and legacy classes)
- Form elements (inputs, selects, textareas)
- Table styling
- Typography defaults

**Load Order:** #2 (after brand-guidelines)
**Dependencies:** `brand-guidelines.css`
**Loaded By:** Main plugin file via `admin_enqueue_scripts` hook

**What Should Go Here:**
✅ Page backgrounds
✅ Section card patterns
✅ Page headers
✅ Common form elements
✅ Table borders and hover states
✅ Typography defaults

**What Should NOT Go Here:**
❌ Page-specific layouts
❌ Component-specific styles
❌ Feature-specific UI

---

### Layer 3: Page-Specific Styles

#### `image-optimizer-admin.css`
**Purpose:** Main Image Optimizer page UI
**Admin Page:** `admin/image-optimizer-admin.php`
**Contains:**
- Onboarding wizard
- Progress tracking
- Results table
- Filter controls
- Bulk actions
- Context dropdowns
- Duplicate detection UI

**Load Order:** When optimizer page loads
**Dependencies:** `brand-guidelines.css`, `global-admin.css`

#### `image-optimizer-settings.css`
**Purpose:** Settings page UI
**Admin Page:** `admin/image-optimizer-settings.php`
**Contains:**
- Settings cards (licensing, rename, profiles, diagnostics)
- Tab navigation
- Profile management UI
- Context profiles
- Form layouts specific to settings

**Load Order:** When settings page loads
**Dependencies:** `brand-guidelines.css`, `global-admin.css`

#### `dashboard.css`
**Purpose:** Dashboard page UI
**Admin Page:** `admin/dashboard-page.php`
**Contains:**
- Dashboard-specific layout
- Stats cards
- Quick actions

**Load Order:** When dashboard page loads
**Dependencies:** `brand-guidelines.css`, `global-admin.css`

#### `hub.css`
**Purpose:** Hub page UI (main control center)
**Admin Page:** `admin/class-msh-hub-page.php`
**Contains:**
- Hub navigation
- Stats overview
- Tab system
- Metadata browser

**Load Order:** When hub page loads
**Dependencies:** `brand-guidelines.css`, `global-admin.css`

#### `phase4-admin.css`
**Purpose:** Phase 4 feature UI
**Admin Page:** Phase 4 feature pages
**Contains:**
- Phase 4-specific components

**Load Order:** When phase 4 pages load
**Dependencies:** `brand-guidelines.css`, `global-admin.css`

#### `template-admin.css`
**Purpose:** Template Intelligence UI
**Admin Page:** Template management pages
**Contains:**
- Template stats grid
- Template browser
- Template-specific controls

**Load Order:** When template pages load
**Dependencies:** `brand-guidelines.css`, `global-admin.css`

---

### Layer 4: Component/Widget Styles

#### `confidence-indicators.css`
**Purpose:** Confidence level visual indicators
**Component:** Metadata/filename confidence badges
**Contains:**
- Star ratings (high/medium/low)
- Color coding for confidence levels
- Tooltip styling

**Load Order:** On pages using confidence indicators
**Dependencies:** `brand-guidelines.css`

#### `tinydot-loader.css`
**Purpose:** Branded loading animations
**Component:** TinyDot animated loader
**Contains:**
- Loader animations
- Spinner variations
- Button loading states

**Load Order:** Globally (via `class-msh-tinydot-loader.php`)
**Dependencies:** None (standalone)

#### `admin-menu-branding.css`
**Purpose:** WordPress admin menu branding
**Component:** Left sidebar menu customization
**Contains:**
- TinyDot logo in admin menu
- Menu item styling

**Load Order:** On all admin pages
**Dependencies:** None

#### `wp-list-table-branding.css`
**Purpose:** WordPress list table styling
**Component:** `.wp-list-table` customization
**Contains:**
- Brand-compliant table styling
- Row hover states
- Column layouts

**Load Order:** On pages with WP list tables
**Dependencies:** `brand-guidelines.css`

---

## 🎨 The Dot Brand Standards

### Colors
- **Charcoal:** `#35332F` - Primary text, headers, dark elements
- **Warm Gray:** `#8B8883` - Secondary text, muted elements
- **Cream:** `#FAF9F6` - Page backgrounds, section cards
- **White:** `#FFFFFF` - Form fields, table cells
- **Accent:** `#DAFF00` - Lime green for highlights, CTAs

### Borders
- **Default:** `1px solid #ddd` - Light gray, not charcoal
- **Strong:** `1px solid #35332F` - Charcoal for emphasis

### Border Radius
- **Small:** `6px` - Form fields, small cards
- **Large:** `12px` - Section cards, containers

### Shadows
- **Subtle:** `0 1px 3px rgba(15, 23, 42, 0.08)` - Section cards

### Typography
- **Headings:** `futura-pt` (--font-primary)
- **Body:** `ff-real-text-pro` (--font-secondary)

---

## 🔧 Loading Order (Priority)

Controlled by `msh-image-optimizer.php::enqueue_global_admin_styles()`:

1. **Priority 1:** `brand-guidelines.css` (CSS variables)
2. **Priority 2:** `global-admin.css` (global patterns)
3. **Page Load:** Page-specific CSS (dashboard, settings, etc.)
4. **As Needed:** Component CSS (confidence, loader, etc.)

---

## 📝 Maintenance Rules

### DO ✅
- Put **shared patterns** in `global-admin.css`
- Use CSS variables from `brand-guidelines.css`
- Keep page-specific styles in their own files
- Document what each file contains
- Follow The Dot brand colors and spacing

### DON'T ❌
- Duplicate page background rules (it's in `global-admin.css`)
- Duplicate section card styling (it's in `global-admin.css`)
- Put page-specific layouts in `global-admin.css`
- Hardcode colors (use CSS variables)
- Use charcoal borders everywhere (use `#ddd` for light borders)

---

## 🧹 Current Cleanup Tasks

### Phase 1: Remove Duplicates ✅ IN PROGRESS
- [ ] Remove duplicate page background rules from individual CSS files
- [ ] Remove duplicate section card styling from individual CSS files
- [ ] Remove duplicate page header styling from individual CSS files

### Phase 2: Consolidate
- [ ] Extract common table patterns to `global-admin.css`
- [ ] Extract common form patterns to `global-admin.css`
- [ ] Verify all pages use consistent borders (#ddd, not #35332F)

### Phase 3: Test
- [ ] Test all admin pages for visual consistency
- [ ] Verify no broken styling
- [ ] Check responsive layouts

---

## 📚 Related Documentation

- [Brand Guidelines](https://www.figma.com/design/Wo0x0N3aJYTVKhGbcVxGbu/The-Dot-Brand-Guidelines)
- [Typography System](/docs/TYPOGRAPHY_CONSOLIDATION_PLAN.md)
- [Style Guide](/docs/MSH_IMAGE_OPTIMIZER_STYLE_GUIDE.md)
- [TinyDot Loader](/TINYDOT-LOADER-IMPLEMENTATION.md)

---

**Last Updated:** 2025-11-02
**Version:** 1.0
**Maintainer:** The Dot Creative Agency
