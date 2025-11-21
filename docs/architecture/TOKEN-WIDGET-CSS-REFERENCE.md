# Token Balance Widget - CSS Reference

**Component:** Token Balance Widget
**CSS File:** `/assets/css/token-balance-widget.css`
**Architecture Layer:** Layer 4 - Component/Widget Styles
**Dependencies:** `brand-guidelines.css` (CSS variables)
**Brand:** The Dot Creative Agency - Monochrome, minimalistic, professional

---

## CSS Architecture Compliance ✅

Following [CSS_ARCHITECTURE.md](CSS_ARCHITECTURE.md):

### Layer 4 Component Standards
- ✅ Uses CSS custom properties from `brand-guidelines.css`
- ✅ Follows The Dot brand colors (charcoal, cream, warm gray, white, accent lime)
- ✅ Uses `#ddd` for light borders (not charcoal)
- ✅ Border radius: 6px (small), 12px (large cards)
- ✅ Subtle shadows for elevation
- ✅ Typography: `futura-pt` (headings), `ff-real-text-pro` (body)

---

## Component Structure

### 1. Widget Container
```css
.msh-token-balance-widget
```
- Background: Cream (`#FAF9F6`)
- Border: Light gray (`#ddd`)
- Border radius: 12px (large card)
- Padding: 24px
- Shadow: Subtle elevation

### 2. Header Section
```css
.msh-token-balance-widget__header
.msh-token-balance-widget__title
.msh-token-balance-widget__refresh
```
- Flex layout with title and refresh button
- Border bottom separator
- Typography: Futura PT headings

### 3. Balance Display
```css
.msh-token-balance-widget__balance
.msh-token-balance-widget__balance-value
.msh-token-balance-widget__balance-value--low (warning state)
.msh-token-balance-widget__balance-value--critical (critical state)
```
- Large number display (36px Futura PT)
- Color states: Charcoal (normal), Orange (low), Red (critical)
- Sublabels in warm gray

### 4. Progress Bar
```css
.msh-token-balance-widget__progress-bar
.msh-token-balance-widget__progress-fill
.msh-token-balance-widget__progress-fill--low
.msh-token-balance-widget__progress-fill--critical
```
- Full-width pill shape (border-radius: 999px)
- White background with ddd border
- Fill colors: Charcoal (normal), Orange (low), Red (critical)
- Smooth transition (0.3s ease)

### 5. Details Grid
```css
.msh-token-balance-widget__details
.msh-token-balance-widget__detail-item
```
- CSS Grid: 2 columns
- White background cards
- Uppercase labels (11px, warm gray)
- Value display (16px Futura PT)

### 6. Warnings
```css
.msh-token-balance-widget__warning
.msh-token-balance-widget__warning--critical
```
- Yellow background for warnings
- Red background for critical
- Icon + content flex layout
- Border radius: 6px

### 7. Action Buttons
```css
.msh-token-balance-widget__action-btn--primary (Charcoal bg, white text)
.msh-token-balance-widget__action-btn--secondary (White bg, charcoal border)
.msh-token-balance-widget__action-btn--accent (Lime green for upgrade)
```
- Primary: Charcoal background, white text
- Secondary: White background, charcoal border
- Accent: Lime green (`#DAFF00`) for upgrades
- Smooth hover transitions

### 8. Modal
```css
.msh-token-balance-modal
.msh-token-balance-modal__content
```
- Full-screen overlay (rgba black 50%)
- Centered cream card
- Max-width: 500px
- Border radius: 12px
- Large shadow for elevation

### 9. Tier Badges
```css
.msh-token-balance-widget__tier-badge
.msh-token-balance-widget__tier-badge--free (gray)
.msh-token-balance-widget__tier-badge--pro (blue)
.msh-token-balance-widget__tier-badge--business (purple)
.msh-token-balance-widget__tier-badge--enterprise (gold)
```
- Pill shape (border-radius: 999px)
- Color-coded by tier
- Uppercase small text (11px)

---

## Color Reference

### Primary Colors (from brand-guidelines.css)
```css
--msh-charcoal: #35332F  /* Primary text, dark elements */
--msh-warm-gray: #8B8883 /* Secondary text, muted elements */
--msh-cream: #FAF9F6     /* Page backgrounds, section cards */
--msh-white: #FFFFFF     /* Form fields, table cells */
--msh-accent: #DAFF00    /* Lime green for highlights, CTAs */
```

### State Colors (hardcoded for warnings)
```css
Warning Orange: #F59E0B, #D97706
Critical Red: #DC2626
Warning Bg: #FEF3C7
Critical Bg: #FEE2E2
```

### Tier Badge Colors
```css
Free: #F3F4F6 bg, #6B7280 text
Pro: #DBEAFE bg, #1E40AF text
Business: #F3E8FF bg, #6B21A8 text
Enterprise: #FEF3C7 bg, #92400E text
```

---

## Typography Reference

### Headings (Futura PT)
```css
font-family: var(--font-primary, 'futura-pt', sans-serif);
```
- Widget title: 18px, weight 500
- Balance value: 36px, weight 600
- Detail values: 16px, weight 500
- Modal title: 20px, weight 600

### Body Text (Real Text Pro)
```css
font-family: var(--font-secondary, 'ff-real-text-pro', sans-serif);
```
- Labels: 11-12px uppercase
- Messages: 13-14px
- Buttons: 14px, weight 500

---

## Spacing Reference

### Padding
- Widget container: 24px
- Details grid: 16px
- Warning boxes: 12px 16px
- Buttons: 10px 16px
- Modal: 24px

### Margins
- Widget bottom: 24px
- Section gaps: 16-20px
- Element gaps: 4-12px

### Grid Gaps
- Details grid: 12px
- Action buttons: 12px

---

## Responsive Breakpoints

```css
@media (max-width: 782px) {
  - Details grid: 1 column
  - Actions: Stack vertically
  - Balance value: 28px (smaller)
}
```

---

## Loading States

### Loading Spinner
```css
.msh-token-balance-widget__loading-spinner
```
- Rotating border animation
- Charcoal accent on top
- 14px size
- 0.6s linear infinite rotation

### Widget Loading State
```css
.msh-token-balance-widget--loading
```
- 60% opacity
- Pointer events disabled

---

## Integration Requirements

### HTML Structure
Widget must follow this BEM structure:
```html
<div class="msh-token-balance-widget">
  <div class="msh-token-balance-widget__header">...</div>
  <div class="msh-token-balance-widget__balance">...</div>
  <div class="msh-token-balance-widget__progress">...</div>
  <div class="msh-token-balance-widget__details">...</div>
  <div class="msh-token-balance-widget__warning">...</div>
  <div class="msh-token-balance-widget__actions">...</div>
</div>
```

### JavaScript Requirements
Widget CSS works with `token-balance-widget.js`:
- Modal visibility toggled via `.msh-token-balance-modal--visible`
- Loading state via `.msh-token-balance-widget--loading`
- Dynamic state classes: `--low`, `--critical`

### Enqueue
CSS automatically enqueued by `MSH_Token_Balance_Widget::enqueue_assets()`:
```php
wp_enqueue_style(
    'msh-token-balance-widget',
    MSH_IO_ASSETS_URL . 'css/token-balance-widget.css',
    array(),
    MSH_IO_VERSION
);
```

---

## Maintenance Notes

### DO ✅
- Use CSS custom properties for colors
- Follow BEM naming convention
- Keep monochrome aesthetic
- Use subtle shadows and transitions
- Test responsive breakpoints

### DON'T ❌
- Hardcode brand colors (use variables)
- Use charcoal borders everywhere (use `#ddd` for light borders)
- Add unnecessary animations
- Break the component pattern
- Ignore mobile responsiveness

---

## Testing Checklist

### Visual Testing
- [ ] Widget displays correctly on Hub page
- [ ] Widget displays correctly on Settings page
- [ ] Balance updates dynamically
- [ ] Progress bar fills correctly
- [ ] Warning states show proper colors
- [ ] Buttons have correct hover states
- [ ] Modal opens/closes smoothly
- [ ] Mobile layout stacks properly
- [ ] Loading spinner animates

### Browser Testing
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)

### Accessibility
- [ ] Focus states visible
- [ ] Buttons keyboard accessible
- [ ] Modal can be closed with ESC
- [ ] Color contrast meets WCAG AA

---

## Related Files

**PHP:**
- `/includes/class-msh-token-balance-widget.php` - Widget class
- `/includes/class-msh-token-manager.php` - Token logic

**JavaScript:**
- `/assets/js/token-balance-widget.js` - Widget behavior

**CSS:**
- `/assets/css/brand-guidelines.css` - CSS variables (Layer 1)
- `/assets/css/global-admin.css` - Global patterns (Layer 2)

**Documentation:**
- `/docs/CSS_ARCHITECTURE.md` - Overall CSS system
- `WIDGET-INTEGRATION-GUIDE.md` - Integration instructions

---

**Last Updated:** 2025-11-02
**Version:** 1.3.0-0B
**Maintainer:** The Dot Creative Agency
