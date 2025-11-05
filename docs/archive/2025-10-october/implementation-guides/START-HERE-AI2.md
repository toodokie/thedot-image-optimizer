# Welcome AI #2! Start Here 👋

**Date:** October 19, 2025
**Your Role:** Frontend Developer (Admin UI + JavaScript)
**Status:** ✅ Phase 4R+ committed, ready for parallel development

---

## 📍 Files Location Confirmed

The docs are in the repo at:

```
/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/docs/
```

**Your two key files:**

1. **`docs/interface-contract.md`** (21 KB)
   - Our agreement on how we'll work together
   - Database tables, helper functions, REST API
   - READ THIS FIRST (15 min)

2. **`docs/ai2-onboarding-instructions.md`** (17 KB)
   - Your step-by-step quick start guide
   - Code examples for everything
   - Your first task breakdown

---

## 🚀 Quick Start (5 Steps)

### Step 1: Read the Contract (15 min)
```bash
# Read this file first
docs/interface-contract.md
```

**What you'll learn:**
- Which helper functions to use (Section 2)
- Which REST API endpoints to call (Section 4)
- What database tables exist (Section 1)

---

### Step 2: Read Your Onboarding Guide (10 min)
```bash
# Read this file second
docs/ai2-onboarding-instructions.md
```

**What you'll learn:**
- Your file ownership (you own `admin/` and `assets/`)
- Step-by-step first task
- Code examples for everything

---

### Step 3: Create Your First File (30 min)

**File:** `admin/class-msh-hub-page.php`

**What it should do:**
- Register menu item: "The Dot → Optimizer Hub"
- Create 5 tabs: Cache, History, Queue, Events, Sync
- Render tab navigation (WordPress standard)
- Show placeholders for each tab

**Full code example in:** `docs/ai2-onboarding-instructions.md` Step 3

---

### Step 4: Test It

Navigate to WordPress Admin:
```
The Dot → Optimizer Hub
```

**You should see:**
- ✅ 5 tabs (Cache, History, Queue, Events, Sync)
- ✅ URL changes when clicking tabs (`?tab=cache`, etc.)
- ✅ Each tab shows "Coming soon..." placeholder

---

### Step 5: Build Cache Tab (Your Main Focus)

**UPDATED (Oct 19, 2025):** Use `msh_get_metadata_entries()` for translated metadata.

**File:** Cache tab is already built into `admin/class-msh-hub-page.php`

**Use this helper function (I provide it):**
```php
// Get translated metadata (title, alt, caption, description)
$results = msh_get_metadata_entries( array(
    'locale'     => 'es_ES',  // or 'en_US', 'fr_FR'
    'source'     => 'ai',     // or 'manual'
    'page'       => 1,
    'per_page'   => 50,
) );

// Current data: 13 entries (8 en_US, 5 es_ES, 0 fr_FR)
```

**Alternative - Phase 3 staleness tracking:**
```php
// Get staleness cache (tracks if metadata needs regeneration)
$results = msh_get_cache_entries( array(
    'locale'     => 'es_ES',
    'staleness'  => 'stale',
    'page'       => 1,
    'per_page'   => 50,
) );
```

---

## 🔑 Key Rules

### Rule 1: File Ownership
**You own:** `admin/` and `assets/` directories
**I own:** `includes/` directory

**❌ DON'T modify my files**
**✅ DO use my helper functions**

### Rule 2: Use Helper Functions
**❌ BAD:**
```php
global $wpdb;
$jobs = $wpdb->get_results( "SELECT * FROM msh_jobs..." );
```

**✅ GOOD:**
```php
$stats = msh_get_job_stats();
```

### Rule 3: All Helper Functions Documented
Every function you need is in `docs/interface-contract.md` Section 2:
- `msh_get_metadata_entries()` - Query translated metadata (NEW - use this for Cache tab!)
- `msh_get_cache_entries()` - Query Phase 3 staleness cache
- `msh_get_job_stats()` - Get queue status
- `msh_get_recent_events()` - Get event feed (NEW - for Events tab!)
- `msh_enqueue_job()` - Add job to queue
- `msh_is_pro_active()` - Check license
- `msh_telemetry()` - Log events

---

## 📊 What I'm Building (Backend)

**My files (I'm creating these):**
```
includes/
├── automation/
│   ├── class-msh-job-engine.php          ← Job queue
│   ├── class-msh-queue-manager.php       ← Priority queue
│   └── class-msh-automation-triggers.php ← Auto-enqueue jobs
├── enterprise/
│   ├── class-msh-license-manager.php     ← Licensing
│   ├── class-msh-telemetry.php           ← Usage tracking
│   └── class-msh-remote-sync.php         ← Cloud sync
└── rest/
    ├── class-msh-rest-jobs.php           ← REST API for jobs
    └── class-msh-rest-metadata.php       ← REST API for metadata
```

**I'll provide:**
- 5 helper functions (you call them)
- 4 REST API endpoints (you call them)
- 3 WordPress action hooks (you listen to them)
- All backend logic

---

## 🎨 Your Focus (Frontend)

**Your files:**
```
admin/
├── class-msh-hub-page.php              ← Main controller
├── tabs/
│   ├── class-msh-hub-cache-tab.php     ← Browse metadata
│   ├── class-msh-hub-history-tab.php   ← Version timeline
│   ├── class-msh-hub-queue-tab.php     ← Job queue dashboard
│   ├── class-msh-hub-events-tab.php    ← Event log
│   └── class-msh-hub-sync-tab.php      ← Cloud sync (Pro)

assets/
├── css/
│   └── hub.css                         ← The Dot brand styles
└── js/
    ├── hub-cache.js                    ← AJAX for Cache tab
    ├── hub-queue.js                    ← Live stats updates
    └── hub-events.js                   ← Live event feed
```

**You'll provide:**
- Beautiful, intuitive UI
- AJAX filtering and pagination
- Live updates (every 5 seconds)
- Brand-compliant CSS (The Dot colors)

---

## 🎯 Your First Task Breakdown

**Week 1:**
- [ ] Create Hub page skeleton (Day 1)
- [ ] Create basic CSS with brand colors (Day 1)
- [ ] Build Cache tab UI (Day 2-3)
- [ ] Add AJAX filtering to Cache tab (Day 4-5)

**Full task breakdown:** `docs/ai2-onboarding-instructions.md` Section "Your Task Breakdown"

---

## 🆘 Common Questions

### Q: "Where's the helper function `msh_get_cache_entries()`?"
**A:** I haven't created it yet. Use mock data for now (example in onboarding doc). I'll create it by Day 6, then you switch from mock to real.

### Q: "REST API endpoint returns 404"
**A:** I haven't built it yet. Use AJAX to `admin-ajax.php` temporarily, or wait until Day 6.

### Q: "Can I modify files in `includes/`?"
**A:** No - that's my territory. Use helper functions only.

### Q: "The tab isn't rendering"
**A:** Check your `switch` statement in `render_tab_content()` - make sure `$current_tab` matches the case.

---

## 📞 Communication

### If You Need Something from Me

Post in `docs/interface-contract.md` or message Anastasia:

```markdown
## Question for AI #1

I need a helper function to get a single cache entry by ID.

Can you add this to Section 2?
```

### Daily Progress (Optional)

Create `docs/daily-standup.md`:

```markdown
## AI #2 - Day 1
**Completed:** Hub page skeleton, tab navigation working
**Today:** Building Cache tab UI
**Blockers:** None
```

---

## ✅ Success Criteria

**Your work is done when:**
1. All 5 Hub tabs functional
2. AJAX working on all tabs
3. Brand-compliant CSS (The Dot colors)
4. No PHP/JS errors
5. Mobile responsive
6. All helper functions used correctly (no direct DB queries)

---

## 🚀 Ready to Start?

**Your next action:**
1. Open `docs/interface-contract.md` (15 min read)
2. Open `docs/ai2-onboarding-instructions.md` (10 min read)
3. Create `admin/class-msh-hub-page.php` (follow Step 3 example)
4. Test it in WordPress Admin

**Questions?** Check the docs first, then ask Anastasia.

---

**Welcome aboard! Let's build this together! 🎉**

— AI #1 (Claude, Backend Developer)
