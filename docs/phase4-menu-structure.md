# Phase 4R+ / Phase 5 Menu Structure
## "Metadata Hub" - Consolidated Tabbed Interface

**Last Updated:** October 19, 2025
**Status:** Approved Design

---

## Final Menu Structure

```
📊 The Dot
├── Dashboard
├── ──────────── (separator)
├── 🖼️ Image Optimizer
├── 📊 Context Analytics
├── 🌍 Locale Profiles
├── 📖 Glossary
├── 🗄️ Metadata Hub ← NEW (all Phase 4R+/5 features consolidated here)
├── ──────────── (separator)
└── ⚙️ Settings
```

**Total:** 8 menu items (7 current + 1 new)

---

## Metadata Hub Tabs

### Tab 1: Cache
**Icon:** 🗄️
**Purpose:** Browse and manage all image metadata
**Free/Pro:** Free

**Features:**
- View all metadata entries (AI + manual)
- Filter by locale, staleness, source
- Search by image name/ID
- Compare AI vs. manual side-by-side
- Switch active source
- Bulk regenerate
- Export to CSV

**URL:** `?page=msh-metadata-hub&tab=cache`

---

### Tab 2: History
**Icon:** 📜
**Purpose:** Version timeline and rollback
**Free/Pro:** Free

**Features:**
- Timeline view of all changes
- Filter by image/locale/field
- Visual diffs (before/after)
- Restore old versions
- User attribution
- Notes for manual edits

**URL:** `?page=msh-metadata-hub&tab=history`

---

### Tab 3: Queue
**Icon:** 🔄
**Purpose:** Background worker management
**Free/Pro:** Free

**Features:**
- Queue status dashboard
- Priority management (manual > glossary > normal)
- Manual trigger: "Regenerate All Stale"
- Skip/prioritize individual items
- Progress bars for active jobs
- Worker health monitoring

**URL:** `?page=msh-metadata-hub&tab=queue`

---

### Tab 4: Events
**Icon:** 📡
**Purpose:** Event log monitoring
**Free/Pro:** Free

**Features:**
- Live event stream
- Filter by event type
- User attribution
- Entity drill-down
- Event payload inspection
- Export to CSV

**URL:** `?page=msh-metadata-hub&tab=events`

---

### Tab 5: Sync
**Icon:** ☁️🔒
**Purpose:** Cloud synchronization
**Free/Pro:** **PRO ONLY** ($99/year)

**Features (Pro Active):**
- S3/Supabase sync status
- Manual push/pull
- Conflict resolution UI
- Multi-site sharing
- Backup/restore
- ETag tracking

**Free User Experience:**
- Tab visible with 🔒 icon
- Click → Pro upsell modal
- Clear benefits listed
- "Upgrade to Pro - $99/year" button

**URL:** `?page=msh-metadata-hub&tab=sync`

---

## Design Rationale

### Why "Metadata Hub" Instead of Multiple Menu Items?

**❌ Original Plan (5 separate menu items):**
```
├── Image Optimizer
├── Context Analytics
├── Locale Profiles
├── Glossary
├── Metadata Cache      ← NEW
├── Version History     ← NEW
├── Regeneration Queue  ← NEW
├── Event Log          ← NEW
├── Cloud Sync (Pro)   ← NEW
├── ──────────
└── Settings
```
**Problems:**
- Menu too crowded (12 items)
- Features feel scattered
- Unclear which features are related
- Pro feature isolated from context

**✅ Approved Plan (1 menu item with tabs):**
```
├── Image Optimizer
├── Context Analytics
├── Locale Profiles
├── Glossary
├── Metadata Hub ← All Phase 4R+/5 features here
│   └── [Cache] [History] [Queue] [Events] [Sync 🔒]
├── ──────────
└── Settings
```
**Benefits:**
- Clean menu (8 items total)
- Related features grouped logically
- Tab navigation intuitive
- Pro feature visible for upsell
- "Hub" branding suggests central power

---

## Visual Design (Brand Compliance)

### Tab Navigation

**WordPress Standard Nav Tabs:**

```html
<nav class="nav-tab-wrapper">
    <a href="?page=msh-metadata-hub&tab=cache"
       class="nav-tab nav-tab-active">
        Cache
    </a>
    <a href="?page=msh-metadata-hub&tab=history"
       class="nav-tab">
        History
    </a>
    <a href="?page=msh-metadata-hub&tab=queue"
       class="nav-tab">
        Queue
    </a>
    <a href="?page=msh-metadata-hub&tab=events"
       class="nav-tab">
        Events
    </a>
    <a href="#"
       class="nav-tab msh-pro-tab"
       data-pro-feature="cloud-sync">
        Sync 🔒 <span class="msh-pro-badge">PRO</span>
    </a>
</nav>
```

**Custom CSS (Brand Colors):**

```css
.msh-metadata-hub .nav-tab-wrapper {
    border-bottom: 1px solid #35332f;
    margin-bottom: 20px;
}

.msh-metadata-hub .nav-tab {
    font-family: 'futura-pt', sans-serif;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #8b8883;
    border: none;
    background: transparent;
}

.msh-metadata-hub .nav-tab-active {
    color: #35332f;
    border-bottom: 2px solid #daff00;
    background: transparent;
}

.msh-metadata-hub .msh-pro-tab {
    opacity: 0.7;
}

.msh-metadata-hub .msh-pro-badge {
    background: #daff00;
    color: #35332f;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.7em;
    font-weight: 700;
}
```

---

## Pro Upsell Modal

**Trigger:** Click "Sync 🔒" tab when Pro not active

**Modal Content:**

```
┌─────────────────────────────────────────────┐
│  🔒 Cloud Sync - Pro Feature                │
├─────────────────────────────────────────────┤
│                                             │
│  Unlock powerful cloud synchronization:     │
│                                             │
│  ✓ Sync metadata across multiple sites     │
│  ✓ Team collaboration with conflict res.   │
│  ✓ Automatic backup to S3 or Supabase      │
│  ✓ Multi-site metadata sharing             │
│  ✓ Export/import with version control      │
│                                             │
│  [Upgrade to Pro - $99/year]  [Learn More]  │
│                                             │
└─────────────────────────────────────────────┘
```

**Implementation:**

```php
private function render_pro_upsell( $feature ) {
    ?>
    <div class="msh-pro-upsell" style="
        max-width: 600px;
        margin: 60px auto;
        padding: 40px;
        background: #FAF9F6;
        border: 2px solid #35332f;
        border-radius: 12px;
        text-align: center;
    ">
        <div class="msh-pro-upsell-icon" style="font-size: 48px; margin-bottom: 20px;">
            🔒
        </div>
        <h2 style="
            font-family: 'futura-pt', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #35332f;
            margin-bottom: 10px;
        ">
            Cloud Sync - Pro Feature
        </h2>
        <p style="
            font-family: 'ff-real-text-pro', sans-serif;
            color: #8b8883;
            font-size: 16px;
            margin-bottom: 30px;
        ">
            Unlock powerful cloud synchronization
        </p>

        <ul style="
            list-style: none;
            padding: 0;
            margin: 30px 0;
            text-align: left;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        ">
            <li style="padding: 10px 0; color: #35332f;">
                ✓ Sync metadata across multiple sites
            </li>
            <li style="padding: 10px 0; color: #35332f;">
                ✓ Team collaboration with conflict resolution
            </li>
            <li style="padding: 10px 0; color: #35332f;">
                ✓ Automatic backup to S3 or Supabase
            </li>
            <li style="padding: 10px 0; color: #35332f;">
                ✓ Multi-site metadata sharing
            </li>
            <li style="padding: 10px 0; color: #35332f;">
                ✓ Export/import with version control
            </li>
        </ul>

        <div class="msh-pro-upsell-actions" style="margin-top: 30px;">
            <a href="https://thedot.com/pricing"
               class="button button-primary button-hero"
               style="
                   background: #daff00;
                   border-color: #daff00;
                   color: #35332f;
                   text-transform: uppercase;
                   font-family: 'futura-pt', sans-serif;
                   letter-spacing: 0.08em;
                   margin-right: 10px;
               ">
                Upgrade to Pro - $99/year
            </a>
            <a href="https://thedot.com/features/cloud-sync"
               class="button button-secondary button-hero"
               style="
                   border-color: #35332f;
                   color: #35332f;
                   text-transform: uppercase;
                   font-family: 'futura-pt', sans-serif;
                   letter-spacing: 0.08em;
               ">
                Learn More
            </a>
        </div>
    </div>
    <?php
}
```

---

## Implementation Checklist

### Phase 5 Development Tasks

- [ ] Create `admin/metadata-hub-page.php`
- [ ] Add menu item to `class-msh-optimizer-menu.php`
- [ ] Implement tab routing (`?tab=cache`, etc.)
- [ ] Build Cache tab UI with filters
- [ ] Build History tab with timeline view
- [ ] Build Queue tab with worker status
- [ ] Build Events tab with live feed
- [ ] Build Sync tab (Pro only)
- [ ] Create Pro upsell modal
- [ ] Add brand-compliant CSS (`assets/css/metadata-hub.css`)
- [ ] JavaScript for:
  - Tab switching
  - Live event feed
  - Slide-out panels
  - Pro modal
  - AJAX filtering
- [ ] WP-CLI integration (all tabs should have CLI equivalents)
- [ ] i18n strings (all text translatable)
- [ ] User capability checks (`manage_options`)
- [ ] Security: Nonces, sanitization, escaping

---

## User Experience Flow

### First-Time User (Free)

1. User navigates to **The Dot → Metadata Hub**
2. Lands on **Cache** tab (default)
3. Sees metadata browser with filters
4. Clicks **Sync** tab out of curiosity
5. Sees Pro upsell modal
6. Understands value proposition
7. Either:
   - Clicks "Upgrade to Pro" → Pricing page
   - Clicks "Learn More" → Feature details
   - Closes modal, continues with free tabs

### Pro User

1. User navigates to **The Dot → Metadata Hub**
2. Lands on **Cache** tab
3. Clicks **Sync** tab
4. Sees full sync dashboard (no modal)
5. Configures S3/Supabase credentials
6. Enables auto-sync
7. Monitors sync activity in real-time

### Power User Workflow

**Daily Monitoring:**
1. Check **Queue** tab → Worker health
2. Check **Events** tab → Recent activity
3. If issues detected → **Cache** tab → Filter by stale
4. Manual regenerate if needed

**Monthly Audit:**
1. **Stats** from WP-CLI: `wp msh metadata stats`
2. **Cache** tab → Export to CSV
3. **History** tab → Review manual edits
4. Archive old versions

---

## FAQ

### Q: Can users bookmark specific tabs?

**A:** Yes. Each tab has unique URL:
- Cache: `?page=msh-metadata-hub&tab=cache`
- History: `?page=msh-metadata-hub&tab=history`
- Queue: `?page=msh-metadata-hub&tab=queue`
- Events: `?page=msh-metadata-hub&tab=events`
- Sync: `?page=msh-metadata-hub&tab=sync`

### Q: What happens if user clicks Sync tab without Pro?

**A:** Pro upsell modal appears. They can:
- Click "Upgrade to Pro" → Pricing page
- Click "Learn More" → Feature documentation
- Click X or outside modal → Return to current tab

Tab changes to "Sync" in URL but content shows upsell.

### Q: Can we hide Sync tab completely for free users?

**A:** No - keeping it visible with 🔒 icon creates upsell opportunity. Research shows visible locked features convert 3x better than hidden features.

### Q: Will this work on mobile/tablet?

**A:** Yes. WordPress nav-tab-wrapper is responsive. On mobile:
- Tabs stack vertically or scroll horizontally
- Slide-out panels become full-screen modals
- Tables become card layouts

### Q: Can other plugins add tabs to Metadata Hub?

**A:** Yes, via filter:

```php
add_filter( 'msh_metadata_hub_tabs', function( $tabs ) {
    $tabs['custom'] = array(
        'label'    => 'My Tab',
        'callback' => 'render_custom_tab',
        'cap'      => 'manage_options',
    );
    return $tabs;
} );
```

---

## Accessibility

- ✅ Keyboard navigation (Tab, Enter, Esc)
- ✅ ARIA labels on all tabs
- ✅ Screen reader announcements for tab changes
- ✅ Focus indicators visible
- ✅ Color contrast WCAG AA compliant
- ✅ Pro badge announced as "locked" to screen readers

---

## Localization

All strings translatable:

```php
__( 'Metadata Hub', 'msh-image-optimizer' )
__( 'Cache', 'msh-image-optimizer' )
__( 'History', 'msh-image-optimizer' )
__( 'Queue', 'msh-image-optimizer' )
__( 'Events', 'msh-image-optimizer' )
__( 'Sync', 'msh-image-optimizer' )
__( 'Cloud Sync - Pro Feature', 'msh-image-optimizer' )
__( 'Upgrade to Pro - $99/year', 'msh-image-optimizer' )
```

---

**End of Menu Structure Documentation**

For technical implementation details, see `/docs/phase4-technical.md`
For user-facing documentation, see `/docs/phase4-manual.md`
