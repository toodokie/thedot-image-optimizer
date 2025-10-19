# AI #2 (Frontend) - Onboarding Instructions
## Welcome to Phase 5+9 Development!

**Date:** October 19, 2025
**Your Role:** Frontend Developer (Admin UI + JavaScript + CSS)
**Partner:** AI #1 (Backend Developer - already started)
**Status:** ✅ Interface Contract Approved - Ready to Code

---

## 🎯 Your Mission

Build the **"Optimizer Hub"** - a beautiful, functional admin interface that lets users:
- Browse metadata cache
- View version history
- Monitor job queue
- Watch events in real-time
- Sync to cloud (Pro feature)

**You own:** All files in `admin/` and `assets/` directories

---

## 📋 CRITICAL: Read This First

### **Interface Contract (Your Bible)**

**Location:** [`docs/interface-contract.md`](interface-contract.md)

**What it contains:**
1. **Database tables** - AI #1 creates these, you read from them
2. **Helper functions** - AI #1 provides these, you call them
3. **REST API endpoints** - AI #1 builds these, you call them
4. **WordPress hooks** - AI #1 emits these, you listen to them

**⚠️ IMPORTANT:** You MUST use the helper functions and REST API. Do NOT:
- Write direct SQL queries to database
- Create your own backend logic
- Modify files in `includes/` directory (that's AI #1's territory)

---

## 🚀 Quick Start Guide

### Step 1: Read the Interface Contract

Open [`docs/interface-contract.md`](interface-contract.md) and read:
- Section 2: Helper Functions (you'll use these A LOT)
- Section 4: REST API Endpoints (for AJAX calls)
- Section 6: Asset Enqueuing (how to load your CSS/JS)

**Time:** 15 minutes

---

### Step 2: Understand the File Structure

**Your files (you create these):**

```
admin/
├── class-msh-hub-page.php              ← START HERE (main controller)
├── tabs/
│   ├── class-msh-hub-cache-tab.php     ← 2nd priority
│   ├── class-msh-hub-history-tab.php
│   ├── class-msh-hub-queue-tab.php
│   ├── class-msh-hub-events-tab.php
│   └── class-msh-hub-sync-tab.php
├── widgets/
│   └── class-msh-metrics-dashboard.php
├── class-msh-license-settings.php
├── class-msh-telemetry-settings.php
└── class-msh-onboarding-wizard.php

assets/
├── css/
│   ├── hub.css                          ← Brand styles (The Dot colors)
│   └── onboarding-wizard.css
└── js/
    ├── hub-cache.js                     ← AJAX for Cache tab
    ├── hub-history.js
    ├── hub-queue.js
    └── hub-events.js
```

**AI #1's files (do NOT modify):**

```
includes/
├── automation/                          ← AI #1 owns this
├── enterprise/                          ← AI #1 owns this
├── rest/                                ← AI #1 owns this
└── class-msh-*-cli.php                  ← AI #1 owns this
```

---

### Step 3: Your First Task - Hub Page Skeleton

**File to create:** `admin/class-msh-hub-page.php`

**What it should do:**
1. Register menu item: "The Dot → Optimizer Hub"
2. Create 5 tabs: Cache, History, Queue, Events, Sync
3. Render tab navigation (WordPress nav-tab-wrapper)
4. Route to correct tab based on `?tab=` URL parameter

**Example skeleton:**

```php
<?php
/**
 * Optimizer Hub main page
 */
class MSH_Hub_Page {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register admin menu
     */
    public function register_menu() {
        add_menu_page(
            __( 'Optimizer Hub', 'msh-image-optimizer' ),
            __( 'Optimizer Hub', 'msh-image-optimizer' ),
            'manage_options',
            'msh-hub',
            array( $this, 'render_page' ),
            'dashicons-database-view',
            30
        );
    }

    /**
     * Enqueue CSS and JS
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_msh-hub' ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'msh-hub-css',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/hub.css',
            array(),
            '2.0.0'
        );

        // JS
        wp_enqueue_script(
            'msh-hub-js',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/hub-cache.js',
            array( 'jquery' ),
            '2.0.0',
            true
        );

        // Localize script with backend data
        wp_localize_script( 'msh-hub-js', 'mshHubData', array(
            'apiUrl'     => rest_url( 'msh/v1' ),
            'apiToken'   => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'ajaxNonce'  => wp_create_nonce( 'msh_hub_nonce' ),
            'isPro'      => msh_is_pro_active(), // Helper function from AI #1
            'stats'      => msh_get_job_stats(), // Helper function from AI #1
        ) );
    }

    /**
     * Render main page
     */
    public function render_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'cache';

        ?>
        <div class="wrap msh-hub-page">
            <h1><?php esc_html_e( 'Optimizer Hub', 'msh-image-optimizer' ); ?></h1>

            <?php $this->render_nav_tabs( $current_tab ); ?>

            <div class="msh-tab-content">
                <?php $this->render_tab_content( $current_tab ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab navigation
     */
    private function render_nav_tabs( $current_tab ) {
        $tabs = array(
            'cache'   => __( 'Cache', 'msh-image-optimizer' ),
            'history' => __( 'History', 'msh-image-optimizer' ),
            'queue'   => __( 'Queue', 'msh-image-optimizer' ),
            'events'  => __( 'Events', 'msh-image-optimizer' ),
            'sync'    => __( 'Sync', 'msh-image-optimizer' ) . ' 🔒',
        );

        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $tab_key => $tab_label ) {
            $active = ( $current_tab === $tab_key ) ? 'nav-tab-active' : '';
            printf(
                '<a href="?page=msh-hub&tab=%s" class="nav-tab %s">%s</a>',
                esc_attr( $tab_key ),
                esc_attr( $active ),
                esc_html( $tab_label )
            );
        }
        echo '</nav>';
    }

    /**
     * Render tab content
     */
    private function render_tab_content( $tab ) {
        switch ( $tab ) {
            case 'cache':
                // For now, just show placeholder
                echo '<p>Cache tab - Coming soon...</p>';
                break;
            case 'history':
                echo '<p>History tab - Coming soon...</p>';
                break;
            case 'queue':
                echo '<p>Queue tab - Coming soon...</p>';
                break;
            case 'events':
                echo '<p>Events tab - Coming soon...</p>';
                break;
            case 'sync':
                if ( ! msh_is_pro_active() ) {
                    echo '<p>Pro upsell modal - Coming soon...</p>';
                } else {
                    echo '<p>Sync dashboard - Coming soon...</p>';
                }
                break;
        }
    }
}
```

**Test it:**
1. Navigate to WordPress Admin → The Dot → Optimizer Hub
2. You should see 5 tabs
3. Click each tab → URL changes to `?tab=cache`, `?tab=history`, etc.
4. Each tab shows "Coming soon..." placeholder

**When working:** Move to Step 4

---

### Step 4: Create Basic CSS (The Dot Brand)

**File to create:** `assets/css/hub.css`

**Brand colors (from design guide):**
```css
:root {
    --msh-charcoal: #35332f;
    --msh-lime: #daff00;
    --msh-warm-gray: #8b8883;
    --msh-cream: #FAF9F6;
    --msh-font-heading: 'futura-pt', sans-serif;
    --msh-font-body: 'ff-real-text-pro', sans-serif;
}

.msh-hub-page .nav-tab-wrapper {
    border-bottom: 1px solid var(--msh-charcoal);
    margin-bottom: 30px;
}

.msh-hub-page .nav-tab {
    font-family: var(--msh-font-heading);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--msh-warm-gray);
    border: none;
    background: transparent;
    padding: 12px 24px;
    transition: all 0.2s;
}

.msh-hub-page .nav-tab-active,
.msh-hub-page .nav-tab:hover {
    color: var(--msh-charcoal);
    border-bottom: 2px solid var(--msh-lime);
    background: transparent;
}
```

**Test it:**
- Tabs should have uppercase text
- Active tab has lime underline
- Hover effect works

---

### Step 5: Build Cache Tab (Your Main Focus)

**File to create:** `admin/tabs/class-msh-hub-cache-tab.php`

**What it should do:**
1. Display metadata cache entries in a table
2. Filters: Locale, Staleness, Source
3. Pagination (50 per page)
4. "Regenerate" button for each row

**Key function to use:**
```php
// Get cache entries (AI #1 provides this function)
$results = msh_get_cache_entries( array(
    'locale'     => 'es_ES',
    'staleness'  => 'stale',
    'source'     => 'manual',
    'page'       => 1,
    'per_page'   => 50,
) );

// $results structure:
// $results['items'] = array of objects
// $results['total'] = total matching entries
// $results['total_pages'] = total pages
```

**See interface-contract.md Section 2 Function 5 for full details.**

---

## 🔑 Key Helper Functions You'll Use

### 1. `msh_get_cache_entries( $args )`
**Use for:** Cache tab - display metadata
**Returns:** Array with items, total, total_pages
**Filters:** locale, staleness, source, search, page, per_page

### 2. `msh_get_job_stats()`
**Use for:** Queue tab - show pending/processing/complete counts
**Returns:** Array with pending, processing, complete, failed

### 3. `msh_enqueue_job( $type, $entity_type, $entity_id, $payload, $priority )`
**Use for:** "Regenerate" button - add job to queue
**Returns:** Job ID or WP_Error

### 4. `msh_is_pro_active()`
**Use for:** Sync tab - check if user has Pro license
**Returns:** Boolean

### 5. `msh_telemetry( $event, $data )`
**Use for:** Track user actions (if they opt-in)
**Returns:** Boolean (true if logged, false if telemetry disabled)

**Full documentation:** See `interface-contract.md` Section 2

---

## 🌐 REST API Endpoints You'll Call

### 1. `GET /msh/v1/jobs/status`
**Use for:** Queue tab - refresh stats every 5 seconds
**JavaScript example in:** `interface-contract.md` Section 4 Endpoint 1

### 2. `POST /msh/v1/jobs/process`
**Use for:** Queue tab "Process Now" button
**JavaScript example in:** `interface-contract.md` Section 4 Endpoint 2

### 3. `GET /msh/v1/metadata/cache`
**Use for:** Cache tab - AJAX filtering
**JavaScript example in:** `interface-contract.md` Section 4 Endpoint 3

### 4. `POST /msh/v1/metadata/regenerate`
**Use for:** Cache tab - bulk regenerate
**JavaScript example in:** `interface-contract.md` Section 4 Endpoint 4

**Full documentation with request/response examples:** See `interface-contract.md` Section 4

---

## ⚠️ Critical Rules (Please Follow!)

### Rule 1: File Ownership
**You own:** `admin/` and `assets/` directories
**AI #1 owns:** `includes/` directory

**❌ DO NOT:**
- Modify any file in `includes/`
- Write direct SQL queries
- Create backend logic

**✅ DO:**
- Use helper functions from AI #1
- Call REST API endpoints
- Focus on beautiful UI/UX

---

### Rule 2: Use Helper Functions

**❌ BAD (direct database query):**
```php
global $wpdb;
$jobs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}msh_jobs WHERE status = 'pending'" );
```

**✅ GOOD (use helper function):**
```php
$stats = msh_get_job_stats();
$pending = $stats['pending'];
```

---

### Rule 3: Error Handling

**Always check for WP_Error:**
```php
$job_id = msh_enqueue_job( ... );

if ( is_wp_error( $job_id ) ) {
    wp_send_json_error( array(
        'message' => $job_id->get_error_message(),
    ) );
    return;
}

wp_send_json_success( array(
    'job_id' => $job_id,
) );
```

---

### Rule 4: Security

**Always:**
- Check nonces: `check_ajax_referer( 'msh_hub_nonce' )`
- Check capabilities: `current_user_can( 'manage_options' )`
- Sanitize input: `sanitize_text_field()`, `absint()`
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`

---

### Rule 5: WordPress Standards

**i18n (translation):**
```php
__( 'Text', 'msh-image-optimizer' )
esc_html__( 'Text', 'msh-image-optimizer' )
```

**Accessibility:**
- ARIA labels on buttons/links
- Keyboard navigation support
- Screen reader announcements

---

## 📚 Reference Documents

### Must Read:
1. **[interface-contract.md](interface-contract.md)** - Your #1 reference
2. **[phase5+9-combined-plan.md](phase5+9-combined-plan.md)** - Overall plan
3. **[phase4-menu-structure.md](phase4-menu-structure.md)** - UI design spec

### Nice to Have:
4. **[phase4-manual.md](phase4-manual.md)** - User manual (what features should do)
5. **[phase4-technical.md](phase4-technical.md)** - Phase 4R+ backend (AI #1 built this)

---

## 🎨 Brand Guidelines (The Dot)

### Colors:
- **Charcoal:** `#35332f` (headings, text)
- **Lime:** `#daff00` (accents, active states)
- **Warm Gray:** `#8b8883` (secondary text)
- **Cream:** `#FAF9F6` (backgrounds)

### Typography:
- **Headings:** Futura PT, uppercase, 0.08em letter-spacing
- **Body:** FF Real Text Pro

### Design Principles:
- Clean, minimal interface
- Clear visual hierarchy
- Lime accents for active/hover states
- Generous white space

---

## 🧪 Testing Your Code

### Test Checklist:

**Cache Tab:**
- [ ] Table displays entries
- [ ] Filters work (locale, staleness, source)
- [ ] Pagination works (50 per page)
- [ ] "Regenerate" button enqueues job
- [ ] No PHP errors in debug.log
- [ ] No JavaScript errors in console

**Queue Tab:**
- [ ] Stats display correctly
- [ ] "Process Now" button works
- [ ] Live updates every 5 seconds
- [ ] Priority breakdown shows high/medium/normal

**All Tabs:**
- [ ] Tab navigation works
- [ ] URLs change (`?tab=cache`, etc.)
- [ ] Brand colors applied
- [ ] Responsive on mobile
- [ ] WCAG AA accessible

---

## 📞 Communication with AI #1

### Daily Standup (Optional but Helpful)

Post your progress in `docs/daily-standup.md`:

```markdown
## Date: 2025-10-20

### AI #2 (Frontend) - Day 2
**Yesterday:** Created Hub page skeleton, tab routing works
**Today:** Building Cache tab UI
**Blockers:** None
**Questions for AI #1:** None
```

### If You Need Something from AI #1

**Example:**
```markdown
## Question for AI #1

I need a helper function to get a single cache entry by ID.

**Proposed signature:**
function msh_get_cache_entry( $id ) {
    return MSH_Metadata_Core::get_instance()->get_cache_by_id( $id );
}

Can you add this?
```

Post in `docs/interface-contract.md` or `docs/daily-standup.md`

---

## 🚀 Your Task Breakdown

### Week 1 (Days 1-5):
- [x] Read interface contract (Day 1)
- [ ] Create Hub page skeleton (Day 1)
- [ ] Create basic CSS (Day 2)
- [ ] Build Cache tab UI (Day 2-3)
- [ ] Add AJAX filtering to Cache tab (Day 4-5)

### Week 2 (Days 6-10):
- [ ] Build Queue tab (Day 6-7)
- [ ] Build Events tab (Day 8-9)
- [ ] Build History tab (Day 9-10)

### Week 3 (Days 11-15):
- [ ] Build Sync tab (with Pro upsell) (Day 11-12)
- [ ] Build Onboarding wizard (Day 13-15)

### Week 4 (Days 16-20):
- [ ] Build License settings page (Day 16-17)
- [ ] Build Telemetry settings page (Day 18)
- [ ] Polish UI/UX (Day 19-20)

### Week 5 (Integration with AI #1):
- [ ] Test all tabs with real backend
- [ ] Fix any integration bugs
- [ ] End-to-end testing

---

## 🎯 Success Criteria

**Your work is complete when:**
1. ✅ All 5 Hub tabs functional
2. ✅ AJAX working on all tabs
3. ✅ Brand-compliant CSS applied
4. ✅ No PHP/JavaScript errors
5. ✅ Mobile responsive
6. ✅ WCAG AA accessible
7. ✅ All helper functions used correctly (no direct DB queries)

---

## 🆘 Need Help?

### Common Issues:

**Issue:** Helper function undefined
**Solution:** AI #1 hasn't created it yet - use mock data temporarily

**Issue:** REST API endpoint 404
**Solution:** AI #1 hasn't built it yet - use AJAX to admin-ajax.php temporarily

**Issue:** Tab not rendering
**Solution:** Check `$current_tab` value and switch statement in `render_tab_content()`

---

## 📝 Final Notes

**You're building the user-facing interface for an enterprise-grade plugin.**

**Focus on:**
- Beautiful, intuitive UI
- Brand-compliant design
- Accessibility
- Performance (AJAX, not full page loads)

**Don't worry about:**
- Backend logic (AI #1 handles that)
- Database schema (AI #1 handles that)
- WP-CLI commands (AI #1 handles that)

**Just focus on making it look amazing and work smoothly!**

---

**Welcome aboard, AI #2! Let's build something great together! 🚀**

Any questions before you start? Check `interface-contract.md` first, then ask Anastasia if still unclear.

---

**End of Onboarding Instructions**
