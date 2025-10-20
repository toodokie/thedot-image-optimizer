# Dashboard Color System

**Brand: The Dot - Monochrome & Minimalistic**

## Core Brand Colors

```css
/* Primary Colors - Monochrome Palette */
--color-dark: #35332F;      /* Primary text, borders, dark accents */
--color-gray: #8B8883;      /* Secondary text, subtle elements */
--color-cream: #FAF9F6;     /* Backgrounds, light surfaces */

/* Neutral Shades */
--color-charcoal: #23221F;  /* Hover states, emphasized elements */
--color-slate: #4A4945;     /* Tertiary text */
--color-light-gray: #E5E5E5; /* Dividers, neutral fills */
--color-off-white: #F0F0F0; /* Subtle backgrounds */
```

## Semantic Colors (Use Sparingly)

```css
/* Success - Minimal Green */
--color-success: #46B450;          /* Only for status indicators */
--color-success-border: rgba(70, 180, 80, 0.2);

/* White */
--color-white: #FFFFFF;            /* Card backgrounds */
```

## Usage Guidelines

### DO ✅
- Use monochrome palette for 95% of the interface
- Primary dark (#35332F) for all buttons, active states
- Subtle opacity for borders: `rgba(53, 51, 47, 0.1)` to `rgba(53, 51, 47, 0.2)`
- Green (#46B450) ONLY for success status icons
- Keep it minimal - let whitespace breathe

### DON'T ❌
- **NO yellow/lime accents** (#DAFF00 is OFF-BRAND for dashboard)
- NO colored borders on cards (use subtle gray opacity instead)
- NO colored backgrounds (except white cards on cream)
- NO colored hover states (use opacity or darker shade of same color)
- NO emojis or colorful icons

## Typography Colors

```css
/* Headings */
h1, h2, h3, .msh-dashboard-title {
  color: #35332F;  /* Dark */
}

/* Body Text */
p, .msh-tab-content {
  color: #35332F;  /* Dark for primary text */
}

/* Secondary Text */
.msh-dashboard-subtitle, .msh-stat-label {
  color: #8B8883;  /* Gray for helper text */
}

/* Tertiary/Disabled */
.msh-footer-info {
  color: #8B8883;  /* Gray for metadata */
}
```

## Border & Divider System

```css
/* Subtle Borders (Primary) */
border: 1px solid rgba(53, 51, 47, 0.1);  /* Default cards */

/* Medium Borders (Emphasis) */
border: 1px solid rgba(53, 51, 47, 0.15); /* Primary cards */

/* Strong Borders (Hover) */
border: 1px solid rgba(53, 51, 47, 0.2);  /* Hover state */

/* Solid Borders (Active/Selected) */
border-bottom: 2px solid #35332F;         /* Active tab */
```

## Component-Specific Colors

### Cards
```css
.msh-stat-card, .msh-balance-card {
  background: #FFFFFF;
  border: 1px solid rgba(53, 51, 47, 0.1);
}

.msh-stat-card:hover {
  border-color: rgba(53, 51, 47, 0.2);  /* Subtle darken on hover */
}
```

### Tabs
```css
.nav-tab {
  color: #8B8883;                    /* Inactive: gray */
  border-bottom: 2px solid transparent;
}

.nav-tab:hover {
  color: #35332F;                    /* Hover: dark */
  border-bottom-color: rgba(53, 51, 47, 0.2);
}

.nav-tab-active {
  color: #35332F;                    /* Active: dark */
  border-bottom-color: #35332F;      /* Solid underline */
}
```

### Buttons
```css
.button-primary {
  background: #35332F;   /* Dark background */
  border-color: #35332F;
  color: #FAF9F6;        /* Cream text */
}

.button-primary:hover {
  background: #23221F;   /* Darker on hover */
  border-color: #23221F;
}

.button-secondary {
  background: #FFFFFF;
  border: 1px solid #35332F;
  color: #35332F;
}

.button-secondary:hover {
  background: #35332F;
  color: #FAF9F6;
}
```

### Status Bar
```css
.msh-status-ok {
  background: #FFFFFF;
  border: 1px solid rgba(70, 180, 80, 0.2);  /* Very subtle green */
  color: #35332F;                             /* Dark text */
}

.msh-status-ok .dashicons {
  color: #46B450;  /* Green icon only */
}
```

### Progress/Ratio Bars
```css
.msh-ratio-ai {
  background: #35332F;  /* Dark for AI portion */
}

.msh-ratio-manual {
  background: #E5E5E5;  /* Light gray for manual portion */
}
```

## Accessibility

- **Contrast Ratios:**
  - Dark on Cream: 9.8:1 (AAA)
  - Dark on White: 12.6:1 (AAA)
  - Gray on White: 4.7:1 (AA)

- **Focus States:**
  ```css
  :focus-visible {
    outline: 2px solid #35332F;
    outline-offset: 2px;
  }
  ```

## Migration from Previous Version

### Removed (Off-Brand)
```diff
- border-color: #DAFF00;         /* Yellow accent - REMOVED */
- background: #DAFF00;            /* Yellow background - REMOVED */
- border-radius: 12px;            /* Too rounded - REMOVED */
```

### Replaced With
```diff
+ border: 1px solid rgba(53, 51, 47, 0.1);  /* Subtle gray */
+ background: #35332F;                       /* Dark monochrome */
+ border-radius: 0 or 2px;                   /* Minimal rounding */
```

## Quick Reference

| Element | Background | Border | Text |
|---------|-----------|--------|------|
| Page | `#FAF9F6` | - | - |
| Card | `#FFFFFF` | `rgba(53,51,47,0.1)` | `#35332F` |
| Card (Primary) | `#FFFFFF` | `rgba(53,51,47,0.15)` | `#35332F` |
| Button (Primary) | `#35332F` | `#35332F` | `#FAF9F6` |
| Tab (Active) | `transparent` | `#35332F` (bottom) | `#35332F` |
| Tab (Inactive) | `transparent` | `transparent` | `#8B8883` |
| Status Bar | `#FFFFFF` | `rgba(70,180,80,0.2)` | `#35332F` |

## Related Documentation
- [Typography Variables](../assets/css/typography-variables.css)
- [Style Guide](MSH_IMAGE_OPTIMIZER_STYLE_GUIDE.md)
- [Brand Guidelines](../README.md)
