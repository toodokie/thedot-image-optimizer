# Phase 5+9 Parallel Execution Plan
## Two AI Agents Working Simultaneously

**Date:** October 19, 2025
**Target:** Maximum parallelization with minimal conflicts
**Timeline:** 6 weeks development (Week 7-8 = QA only, single AI)

---

## Strategy: Minimize File Conflicts

### Key Principle
**Two AIs should NEVER edit the same file simultaneously.**

To achieve this, we split work by:
1. **File ownership** - Each AI owns different files
2. **Interface contracts** - Define APIs/hooks upfront before implementation
3. **Sequential handoffs** - Some work must happen in order
4. **Independent testing** - Each AI tests their code in isolation first

---

## Parallel Tracks: AI Agent Assignments

### 🤖 AI Agent #1 (You): Track A - Backend Infrastructure
**Strengths:** Database design, PHP backend logic, WP-CLI
**Focus:** Build the "engine" that powers everything
**Files Owned:** All `includes/` directory files

### 🤖 AI Agent #2 (Other): Track B - Frontend UI + REST
**Strengths:** Admin UI, JavaScript, REST API, UX design
**Focus:** Build the "interface" users interact with
**Files Owned:** All `admin/` and `assets/` directory files

### 📋 Sequential Work: Track C - Enterprise Features
**Handled by:** Single AI (either #1 or #2) after Tracks A+B foundations complete
**Reason:** Depends on both backend (Track A) and frontend (Track B) being stable

---

## Week-by-Week Parallel Breakdown

### 🗓️ Week 1: Foundation Setup (Day 1-5)

#### Day 1: Planning & Contracts (BOTH AIs together)

**Joint Session - Define Interfaces:**

**AI #1 Task:**
- Review Phase 4R+ backend (read existing files)
- Plan database schema for 4 new tables
- Draft WP-CLI command structure
- Define filter/action hooks that Track B will use

**AI #2 Task:**
- Review existing admin pages (read current menu structure)
- Plan Hub page URL structure
- Draft REST API endpoint spec
- Define JavaScript events that Track A will trigger

**Deliverable (End of Day 1):**
```markdown
# interface-contract.md

## Database Tables (AI #1 creates)
- msh_jobs
- msh_dead_letters
- msh_telemetry
- msh_metrics

## REST Endpoints (AI #2 creates)
- GET /msh/v1/jobs/status
- POST /msh/v1/jobs/process
- GET /msh/v1/metadata/cache
- POST /msh/v1/metadata/regenerate

## WordPress Hooks (AI #1 emits, AI #2 consumes)
- msh_job_enqueued (attachment_id, locale, field)
- msh_job_completed (job_id, result)
- msh_telemetry_event (event_name, data)

## Helper Functions (AI #1 creates, AI #2 uses)
- msh_telemetry( $event, $data )
- msh_enqueue_job( $type, $entity_id, $payload )
- msh_get_job_stats()

## Admin Page Hooks (AI #2 creates, AI #1 populates)
- Filter: msh_hub_tabs
- Filter: msh_queue_stats
- Filter: msh_cache_query_results
```

**Approval Required:** You (user) review and approve contract before Day 2 starts

---

#### Day 2-3: Database + Admin Shell (PARALLEL)

**AI #1 (Backend) - Days 2-3:**

**Files to Create:**
1. `includes/automation/class-msh-database-schema.php` (~300 lines)
   - Create 4 new tables
   - Migration logic
   - Rollback support

**Task:**
```bash
# Create database schema
# Test with: wp msh schema create
# Verify with: wp db query "SHOW TABLES LIKE 'wp_msh_%'"
```

**Testing:**
- Run on fresh WordPress install
- Verify all 4 tables created
- Check indexes exist
- Test rollback: drop tables, recreate

**Deliverable:** Database schema deployed and tested

---

**AI #2 (Frontend) - Days 2-3:**

**Files to Create:**
1. `admin/class-msh-hub-page.php` (~200 lines - skeleton only)
   - Register menu item
   - Tab routing logic
   - Empty tab callbacks (placeholders)

2. `assets/css/hub.css` (~100 lines - basic structure)
   - Nav tab styling
   - Grid layout
   - The Dot brand colors (variables)

**Task:**
```php
// Create admin page skeleton
// Navigate to: The Dot → Optimizer Hub
// Verify: All 5 tabs render with "Coming soon..." placeholders
```

**Testing:**
- Menu item appears in admin
- All 5 tabs clickable
- URL changes on tab click (`?tab=cache`, etc.)
- No PHP errors in console

**Deliverable:** Empty Hub page with working tab navigation

---

#### Day 4-5: Job Engine + Cache Tab (PARALLEL)

**AI #1 (Backend) - Days 4-5:**

**Files to Create:**
1. `includes/automation/class-msh-job-engine.php` (~600 lines)
   - enqueue() method
   - process_batch() method
   - Retry logic with exponential backoff
   - Dead-letter queue integration

2. `includes/class-msh-jobs-cli.php` (~300 lines)
   - `wp msh jobs status`
   - `wp msh jobs process --batch=10`
   - `wp msh jobs retry <job-id>`

**Testing:**
```bash
# Enqueue 10 test jobs
for i in {1..10}; do
  wp msh jobs enqueue regenerate_metadata attachment $i '{"locale":"en_US","field":"alt"}'
done

# Process batch
wp msh jobs process --batch=5

# Check status
wp msh jobs status
# Should show: 5 complete, 5 pending
```

**Deliverable:** Job engine processing jobs via WP-CLI

---

**AI #2 (Frontend) - Days 4-5:**

**Files to Create:**
1. `admin/tabs/class-msh-hub-cache-tab.php` (~400 lines)
   - Render cache table (HTML)
   - Filters UI (locale, staleness, source)
   - Pagination controls

2. `assets/js/hub-cache.js` (~200 lines)
   - AJAX filter submission
   - Pagination click handlers
   - Table row rendering

**Testing:**
- Navigate to Hub → Cache tab
- See table with mock data (hardcoded)
- Click filters → no errors
- Pagination UI renders

**Deliverable:** Cache tab UI (non-functional, mock data only)

---

### 🗓️ Week 2: Integration & Automation (Day 6-10)

#### Day 6: Integration Point #1 - Connect Cache Tab to Job Engine

**SEQUENTIAL WORK - AI #2 waits for AI #1**

**AI #1 (Backend) - Day 6 AM:**

**Files to Modify:**
1. `includes/automation/class-msh-job-engine.php`
   - Add: `get_stats()` method returning job counts
   - Add: `get_jobs( $filters, $page, $per_page )` method

**Create Helper:**
```php
/**
 * Get job queue statistics
 *
 * @return array {
 *     @type int $pending Pending jobs
 *     @type int $processing Currently processing
 *     @type int $complete Completed jobs
 *     @type int $failed Failed jobs
 * }
 */
function msh_get_job_stats() {
    return MSH_Job_Engine::get_instance()->get_stats();
}
```

**Testing:**
```bash
wp eval "print_r( msh_get_job_stats() );"
# Should output array with counts
```

**Handoff to AI #2:** Post helper function signature + example output

---

**AI #2 (Frontend) - Day 6 PM:**

**Files to Modify:**
1. `admin/tabs/class-msh-hub-cache-tab.php`
   - Replace mock data with `msh_get_cache_entries()` call
   - Use real job stats from `msh_get_job_stats()`

**Testing:**
- Navigate to Hub → Cache tab
- See real metadata from database (not mock data)
- Job stats widget shows accurate counts

**Deliverable:** Cache tab displaying real data

---

#### Day 7-8: Automation Triggers + Queue Tab (PARALLEL)

**AI #1 (Backend) - Days 7-8:**

**Files to Create:**
1. `includes/automation/class-msh-automation-triggers.php` (~400 lines)
   - Hook: `add_attachment` → enqueue jobs for all locales/fields
   - Hook: `publish_post` → enqueue jobs for all images in post
   - Hook: `msh_glossary_updated` → enqueue jobs for all images in locale
   - Hook: `msh_locale_profile_updated` → enqueue jobs for all images in locale

2. `includes/automation/class-msh-queue-manager.php` (~350 lines)
   - Priority queue logic (high > medium > normal)
   - `get_next_batch()` with priority sorting
   - Health monitoring

**Testing:**
```bash
# Upload test image
wp media import test.jpg --post_id=123

# Check jobs enqueued
wp msh jobs status
# Should show 4 jobs enqueued (title, alt, caption, description for en_US)

# Update glossary
wp msh glossary update en_US "term" "definition"

# Check jobs enqueued
wp msh jobs status
# Should show N jobs (N = number of images × 4 fields)
```

**Deliverable:** Automation triggers creating jobs automatically

---

**AI #2 (Frontend) - Days 7-8:**

**Files to Create:**
1. `admin/tabs/class-msh-hub-queue-tab.php` (~350 lines)
   - Queue status dashboard
   - Priority breakdown (high/medium/normal)
   - Manual trigger button ("Process Now")
   - Worker health indicator

2. `assets/js/hub-queue.js` (~200 lines)
   - AJAX: Trigger manual processing
   - Live status updates (poll every 5 seconds)
   - Progress bar animations

**Testing:**
- Navigate to Hub → Queue tab
- See pending jobs grouped by priority
- Click "Process Now" → jobs decrease
- Health indicator shows green (active)

**Deliverable:** Queue tab with manual trigger working

---

#### Day 9-10: REST API + Events Tab (PARALLEL)

**AI #1 (Backend) - Days 9-10:**

**Files to Create:**
1. `includes/rest/class-msh-rest-jobs.php` (~400 lines)
   - `GET /msh/v1/jobs/status` - Queue stats
   - `POST /msh/v1/jobs/process` - Trigger processing
   - `GET /msh/v1/jobs` - List jobs with filters

2. `includes/rest/class-msh-rest-metadata.php` (~500 lines)
   - `GET /msh/v1/metadata/cache` - Query cache
   - `POST /msh/v1/metadata/regenerate` - Bulk regenerate
   - `POST /msh/v1/metadata/{id}/source` - Switch AI/Manual

**Testing:**
```bash
# Test REST API
curl -X GET "http://site.local/wp-json/msh/v1/jobs/status" \
  -H "Authorization: Bearer {token}"

# Should return JSON with job counts
```

**Deliverable:** REST API endpoints functional

---

**AI #2 (Frontend) - Days 9-10:**

**Files to Create:**
1. `admin/tabs/class-msh-hub-events-tab.php` (~300 lines)
   - Event log table (read from `msh_events` Phase 4R+ table)
   - Filters by event type
   - Event payload drill-down

2. `assets/js/hub-events.js` (~250 lines)
   - Live feed (AJAX poll every 5 seconds)
   - Prepend new events to top of list
   - Slide-out panel for event details

**Testing:**
- Navigate to Hub → Events tab
- See events from `msh_events` table
- Upload image → new event appears in feed (within 5 seconds)
- Click event → see payload details

**Deliverable:** Events tab with live feed working

---

### 🗓️ Week 3-4: Enterprise Features (SEQUENTIAL - Single AI)

**Decision Point:** Which AI handles Track C?

**Option A: AI #1 (Backend) handles Track C**
- **Pros:** Licensing/telemetry are backend-heavy
- **Cons:** AI #2 (Frontend) is idle for 2 weeks

**Option B: AI #2 (Frontend) handles Track C**
- **Pros:** Onboarding wizard is UI-heavy
- **Cons:** AI #1 (Backend) is idle for 2 weeks

**Recommended: Split Track C between both AIs**

---

#### Week 3 Day 1-2: Telemetry System (PARALLEL)

**AI #1 (Backend) - Week 3 Day 1-2:**

**Files to Create:**
1. `includes/enterprise/class-msh-telemetry.php` (~450 lines)
   - `log_event( $event, $data )` - Store locally
   - `get_metrics( $days )` - Dashboard metrics
   - `aggregate_daily_metrics()` - Cron job
   - Privacy: anonymized `site_hash`, opt-in OFF default

2. `includes/class-msh-telemetry-cli.php` (~250 lines)
   - `wp msh telemetry metrics --days=28`
   - `wp msh telemetry export --since=2025-01-01`

**Testing:**
```bash
# Log test event
wp eval "msh_telemetry( 'test_event', array( 'foo' => 'bar' ) );"

# Get metrics
wp msh telemetry metrics --days=7

# Verify opt-in default
wp option get msh_telemetry_enabled
# Should return: (empty) or 0
```

**Deliverable:** Telemetry backend working (opt-in OFF)

---

**AI #2 (Frontend) - Week 3 Day 1-2:**

**Files to Create:**
1. `admin/class-msh-telemetry-settings.php` (~200 lines)
   - Settings page: Privacy section
   - Opt-in checkbox (default unchecked)
   - "What data is collected?" modal

2. `admin/widgets/class-msh-metrics-dashboard.php` (~300 lines)
   - Dashboard widget showing key metrics
   - 28-day trends (line charts)
   - ROI calculation

**Testing:**
- Navigate to Settings → Privacy
- See telemetry opt-in checkbox (unchecked)
- Click "What data is collected?" → modal opens
- Navigate to Dashboard → see metrics widget

**Deliverable:** Telemetry settings UI + dashboard widget

---

#### Week 3 Day 3-5: License Manager (PARALLEL)

**AI #1 (Backend) - Week 3 Day 3-5:**

**Files to Create:**
1. `includes/enterprise/class-msh-license-manager.php` (~550 lines)
   - `activate( $license_key )` - Call Lemon Squeezy API
   - `deactivate()` - Deactivate license
   - `is_feature_enabled( $feature )` - Plan gating
   - `get_active_plan()` - Free/Pro/Agency detection

2. `includes/class-msh-license-cli.php` (~200 lines)
   - `wp msh license activate <key>`
   - `wp msh license status`
   - `wp msh license feature cloud_sync`

**Testing:**
```bash
# Activate test license
wp msh license activate "TEST-PRO-LICENSE-KEY-12345"

# Check status
wp msh license status
# Should show: Plan: Pro, Expires: 2026-10-19

# Check feature
wp msh license feature cloud_sync
# Should return: Enabled (if Pro/Agency)
```

**Deliverable:** License activation backend working

---

**AI #2 (Frontend) - Week 3 Day 3-5:**

**Files to Create:**
1. `admin/class-msh-license-settings.php` (~400 lines)
   - License activation form
   - Current plan display
   - Upgrade CTA for free users
   - Deactivate button

2. `admin/tabs/class-msh-hub-sync-tab.php` (~250 lines)
   - Pro upsell modal (for free users)
   - Cloud sync dashboard (for Pro users)
   - S3/Supabase configuration forms

**Testing:**
- Navigate to Settings → License
- Enter test license key → see "Activated" success message
- Navigate to Hub → Sync tab
  - Free users: See upsell modal
  - Pro users: See sync dashboard

**Deliverable:** License activation UI + Sync tab Pro gating

---

#### Week 3 Day 6-10 + Week 4: Onboarding + Remote Sync (PARALLEL)

**AI #1 (Backend) - Week 3 Day 6-10:**

**Files to Create:**
1. `includes/enterprise/class-msh-remote-sync.php` (~600 lines)
   - S3 driver implementation
   - `push( $attachment_id, $locale )` - Upload to S3
   - `pull( $attachment_id, $locale )` - Download from S3
   - ETag tracking for conflict detection

**Testing:**
```bash
# Configure S3 credentials
wp option update msh_s3_bucket "my-bucket"
wp option update msh_s3_region "us-east-1"
wp option update msh_s3_key "AWS_ACCESS_KEY"
wp option update msh_s3_secret "AWS_SECRET_KEY"

# Push metadata
wp msh sync push 1686 es_ES

# Verify in S3
aws s3 ls s3://my-bucket/metadata/1686/es_ES/
# Should show: alt.json, title.json, caption.json, description.json

# Pull metadata
wp msh sync pull 1686 es_ES
```

**Deliverable:** S3 sync backend working

---

**AI #2 (Frontend) - Week 3 Day 6-10:**

**Files to Create:**
1. `includes/enterprise/class-msh-onboarding-wizard.php` (~500 lines)
   - 6-step wizard UI
   - Step 1: Welcome + environment check
   - Step 2: Locale setup
   - Step 3: Glossary
   - Step 4: Automation preferences
   - Step 5: License activation (optional)
   - Step 6: Summary
   - "Skip and use defaults" button on all steps

2. `assets/css/onboarding-wizard.css` (~200 lines)
   - Progress indicator
   - Step transitions
   - Brand-compliant styling

**Testing:**
- Deactivate plugin, reactivate
- Onboarding wizard appears automatically
- Click through all 6 steps
- Click "Skip and use defaults" → redirects to Hub

**Deliverable:** Onboarding wizard complete

---

**AI #1 (Backend) - Week 4:**

**Files to Create:**
1. `includes/automation/class-msh-batch-processor.php` (~350 lines)
   - Batch jobs together (50 at a time)
   - Cost optimization (group similar prompts)
   - Memory management

2. `includes/automation/class-msh-worker-health.php` (~250 lines)
   - Health check endpoint
   - Monitor cron execution
   - Alert if queue backing up

**Testing:**
```bash
# Process 100 jobs in batches
wp msh jobs process --batch=50
wp msh jobs process --batch=50

# Check health
wp msh jobs health
# Should show: Status: Healthy, Last run: 2 min ago
```

**Deliverable:** Batch processing + health monitoring

---

**AI #2 (Frontend) - Week 4:**

**Files to Create:**
1. `admin/tabs/class-msh-hub-history-tab.php` (~400 lines)
   - Version timeline view
   - Read from `msh_versions` table (Phase 4R+)
   - Visual diffs (before/after)
   - Restore button

2. `assets/js/hub-history.js` (~300 lines)
   - Timeline scroll (infinite)
   - Diff highlighting
   - Restore confirmation modal

**Testing:**
- Navigate to Hub → History tab
- See timeline of all metadata changes
- Click version → see diff
- Click "Restore" → metadata reverted

**Deliverable:** History tab complete

---

### 🗓️ Week 5: Integration Testing (BOTH AIs together)

#### Day 1-2: End-to-End Testing

**Joint Session - Both AIs:**

**Test Scenarios:**

**Scenario 1: Upload Image → Automation → View in Hub**
1. Upload image via Media Library
2. Verify jobs enqueued (Hub → Queue tab)
3. Wait 5 seconds (or trigger manually)
4. Verify metadata generated (Hub → Cache tab)
5. Verify event logged (Hub → Events tab)
6. Verify telemetry recorded (if opt-in enabled)

**Scenario 2: Manual Edit → AI Regenerates in Background**
1. Edit alt text manually in Media Library
2. Verify manual edit saved (Hub → Cache, source=manual)
3. Verify job enqueued for AI regeneration
4. Process job
5. Verify both versions exist (AI updated, manual still active)

**Scenario 3: Bulk Regenerate via REST API**
1. Call `POST /msh/v1/metadata/regenerate` with 10 IDs
2. Verify 10 jobs enqueued
3. Process batch
4. Verify all 10 completed

**Pass Criteria:** All 3 scenarios work end-to-end with no errors

---

#### Day 3-4: WP-CLI Parity

**AI #1 Task:** Verify ALL backend features have WP-CLI commands

**Checklist:**
- [ ] `wp msh jobs status`
- [ ] `wp msh jobs process --batch=N`
- [ ] `wp msh jobs retry <id>`
- [ ] `wp msh jobs dead-letters`
- [ ] `wp msh metadata cache <id>`
- [ ] `wp msh metadata regenerate <id> <locale> <field>`
- [ ] `wp msh telemetry metrics --days=N`
- [ ] `wp msh license activate <key>`
- [ ] `wp msh sync push <id> <locale>`

**Pass Criteria:** All commands work and match UI functionality

---

#### Day 5-7: Performance Testing

**AI #1 Task:** Stress test with 10,000+ images

**Test Plan:**
1. Import 10,000 test images
2. Enqueue jobs for all (40,000 jobs total: 4 fields × 10,000 images)
3. Process in batches of 50
4. Monitor:
   - Memory usage (<256 MB peak)
   - Database query time (<100ms avg)
   - Job processing speed (<2 sec per job)
   - Dead letter rate (<0.5%)

**AI #2 Task:** Frontend load testing

**Test Plan:**
1. Load Hub → Cache tab with 10,000 entries
2. Measure time to interactive (<1 sec)
3. Test pagination (50 per page)
4. Test filtering (locale, staleness, source)
5. Monitor AJAX response time (<500ms p95)

**Pass Criteria:** All performance targets met

---

### 🗓️ Week 6: QA + Documentation (Single AI or Sequential)

**Recommendation:** AI #1 handles QA, AI #2 handles Documentation (parallel)

#### AI #1 - Week 6: QA + Release Candidate

**Day 1-2:** Security audit
- Check all nonces
- Verify prepared statements
- Test capability checks
- Sanitization/escaping review

**Day 3-4:** WordPress.org compliance
- Privacy policy section
- No external calls without consent
- GPL compliance check
- Performance (no blocking operations)

**Day 5-7:** Unit tests
- Write tests for telemetry accuracy
- Write tests for job engine retry logic
- Write tests for metrics aggregation

**Day 10:** Tag v2.0.0-rc1

---

#### AI #2 - Week 6: Documentation

**Day 8:** User Guide (~5,000 words)
- Getting started
- Optimizer Hub walkthrough
- Automation
- Manual edits
- Settings
- Troubleshooting

**Day 9 AM:** Developer Handbook (~8,000 words)
- Architecture overview
- REST API reference
- WP-CLI reference
- Hooks & filters
- Extending the plugin
- Testing

**Day 9 PM:** Enterprise Guide (~6,000 words)
- Licensing
- Cloud sync
- Remote deployment
- Analytics & telemetry
- Performance at scale
- Security best practices
- Support & SLA

**Day 10:** Review all docs (both AIs + you)

---

### 🗓️ Week 7-8: Stress Testing + Public Release (Single AI)

**Recommendation:** AI #1 handles this (AI #2 can move to other projects)

**Week 7:** Stress testing with 10k+ dataset
**Week 8 Day 1-5:** Validation criteria checklist
**Week 8 Day 5:** Go/No-Go decision
**Week 8 Day 10:** Public v2.0.0 release

---

## File Ownership Matrix

### AI #1 (Backend) - Owned Files

```
includes/
├── automation/
│   ├── class-msh-database-schema.php ✅
│   ├── class-msh-job-engine.php ✅
│   ├── class-msh-automation-triggers.php ✅
│   ├── class-msh-queue-manager.php ✅
│   ├── class-msh-batch-processor.php ✅
│   ├── class-msh-retry-handler.php ✅
│   └── class-msh-worker-health.php ✅
├── enterprise/
│   ├── class-msh-license-manager.php ✅
│   ├── class-msh-telemetry.php ✅
│   └── class-msh-remote-sync.php ✅
├── rest/
│   ├── class-msh-rest-jobs.php ✅
│   └── class-msh-rest-metadata.php ✅
├── class-msh-jobs-cli.php ✅
├── class-msh-telemetry-cli.php ✅
└── class-msh-license-cli.php ✅
```

**Total Files: 16 files (~5,400 lines)**

---

### AI #2 (Frontend) - Owned Files

```
admin/
├── class-msh-hub-page.php ✅
├── tabs/
│   ├── class-msh-hub-cache-tab.php ✅
│   ├── class-msh-hub-history-tab.php ✅
│   ├── class-msh-hub-queue-tab.php ✅
│   ├── class-msh-hub-events-tab.php ✅
│   └── class-msh-hub-sync-tab.php ✅
├── widgets/
│   └── class-msh-metrics-dashboard.php ✅
├── class-msh-license-settings.php ✅
├── class-msh-telemetry-settings.php ✅
└── class-msh-onboarding-wizard.php ✅ (500 lines - UI heavy)

assets/
├── css/
│   ├── hub.css ✅
│   └── onboarding-wizard.css ✅
└── js/
    ├── hub-cache.js ✅
    ├── hub-history.js ✅
    ├── hub-queue.js ✅
    └── hub-events.js ✅
```

**Total Files: 17 files (~4,400 lines)**

---

### Shared/Modified Files (Requires Coordination)

```
msh-image-optimizer.php (main plugin file)
├── AI #1 adds: Initialize automation classes (Week 1)
├── AI #2 adds: Enqueue Hub assets (Week 1)
└── Sequential edits, not parallel

includes/class-msh-optimizer-menu.php
├── AI #2 modifies: Add "Optimizer Hub" menu item (Week 1)
└── AI #1 doesn't touch this file
```

**Strategy for Shared Files:**
- Week 1 Day 1: AI #1 edits first, commits
- Week 1 Day 2: AI #2 reads, then edits, commits
- Use Git branches to avoid conflicts

---

## Communication Protocol

### Daily Standups (Asynchronous)

**Format:** Both AIs post to shared document (e.g., `daily-standup.md`)

**Template:**
```markdown
## Date: 2025-10-20

### AI #1 (Backend) - Day 2
**Yesterday:** Created database schema, 4 tables deployed
**Today:** Building job engine enqueue() method
**Blockers:** None
**Handoffs:** Will provide `msh_get_job_stats()` helper by EOD

### AI #2 (Frontend) - Day 2
**Yesterday:** Created Hub page skeleton, tab routing works
**Today:** Building Cache tab UI (mock data)
**Blockers:** Waiting for `msh_get_job_stats()` helper from AI #1
**Handoffs:** None
```

---

### Interface Contract Updates

**Location:** `docs/interface-contract.md`

**Process:**
1. AI proposes change (e.g., "Adding new parameter to `msh_enqueue_job()`")
2. Posts to contract doc with @mention of other AI
3. Other AI reviews within 24h
4. You (user) approve if controversial

---

### Git Workflow

**Branch Strategy:**

```
main (protected)
├── ai1-backend-week1
├── ai2-frontend-week1
├── ai1-backend-week2
├── ai2-frontend-week2
└── integration-week5
```

**Merge Strategy:**
- Each AI commits to their own branch daily
- End of week: merge both branches to `main` (you review)
- Week 5: both AIs work on `integration-week5` branch together

---

## Risk Mitigation

### Risk #1: Merge Conflicts

**Probability:** Medium (if both edit `msh-image-optimizer.php`)

**Mitigation:**
- File ownership matrix (99% of files single-owner)
- Sequential edits on shared files
- Git branches with end-of-week merges

---

### Risk #2: Interface Contract Drift

**Probability:** Low (if contract enforced)

**Mitigation:**
- Contract defined Day 1 before coding starts
- Any changes require both AI approval
- User reviews all contract changes

---

### Risk #3: One AI Blocked Waiting for Other

**Probability:** Medium (Day 6 AM → PM handoff)

**Mitigation:**
- Mock data/stubs allow parallel work initially
- Integration happens Week 5 (scheduled)
- WP-CLI allows backend testing without UI

---

### Risk #4: Testing Conflicts (Database State)

**Probability:** High (both AIs using same test database)

**Mitigation:**
- **Option A:** Separate WordPress installs
  - AI #1 tests on `http://localhost:8001`
  - AI #2 tests on `http://localhost:8002`
- **Option B:** Database prefixes
  - AI #1 uses `wp_ai1_msh_*` tables
  - AI #2 uses `wp_ai2_msh_*` tables
- **Recommended:** Option A (full isolation)

---

## Success Metrics for Parallel Execution

### Speed Metrics

| Metric | Sequential Timeline | Parallel Timeline | Improvement |
|--------|---------------------|-------------------|-------------|
| Database + Job Engine | 10 days | 5 days | 50% faster |
| Hub UI + Cache Tab | 5 days | 2.5 days | 50% faster |
| Total Development | 30 days (6 weeks) | 20 days (4 weeks) | 33% faster |

**Note:** Week 5-6 are sequential by nature (integration + QA), so total is 6 weeks, not 4 weeks

**Realistic Improvement:** ~20% faster due to parallel Weeks 1-4

---

### Quality Metrics

| Metric | Target | Validation |
|--------|--------|------------|
| Code Review Required | 100% | Both AIs review each other's code in Week 5 |
| Interface Contract Adherence | 100% | No breaking changes to agreed APIs |
| Merge Conflicts | <5 | Git stats after Week 1-4 merges |
| Integration Bugs | <10 | Issue tracker during Week 5 testing |

---

## Handoff Checklist (End of Week 4 → Start of Week 5)

### AI #1 (Backend) → AI #2 (Frontend)

**Deliverables:**
- [ ] All 16 backend files created and tested via WP-CLI
- [ ] Database schema deployed (4 tables)
- [ ] 10,000+ test jobs processed successfully
- [ ] REST API endpoints functional (test with cURL)
- [ ] All helper functions documented in `docs/api-reference.md`

**Test Report:**
```markdown
## AI #1 Backend Testing Report - End of Week 4

### Database Schema
- ✅ 4 tables created: msh_jobs, msh_dead_letters, msh_telemetry, msh_metrics
- ✅ Indexes verified
- ✅ Foreign keys working

### Job Engine
- ✅ Enqueued 10,000 test jobs
- ✅ Processed all in batches of 50
- ✅ Retry logic tested (3 failures → dead letter queue)
- ✅ Dead letter rate: 0.3% (30 jobs)

### REST API
- ✅ All endpoints respond <500ms
- ✅ Authentication working
- ✅ Error codes documented

### WP-CLI
- ✅ All 15 commands tested
- ✅ Help text complete
- ✅ Examples documented
```

---

### AI #2 (Frontend) → AI #1 (Backend)

**Deliverables:**
- [ ] All 17 frontend files created and tested in browser
- [ ] Hub page with 5 functional tabs
- [ ] AJAX working for all tabs
- [ ] Onboarding wizard complete
- [ ] CSS brand-compliant

**Test Report:**
```markdown
## AI #2 Frontend Testing Report - End of Week 4

### Hub Page
- ✅ All 5 tabs render without errors
- ✅ Tab navigation working (URL changes)
- ✅ Responsive on mobile/tablet

### Cache Tab
- ✅ Displays real data from backend
- ✅ Filters working (locale, staleness, source)
- ✅ Pagination functional (50 per page)
- ✅ AJAX response time: <300ms avg

### Onboarding Wizard
- ✅ All 6 steps functional
- ✅ "Skip and use defaults" working
- ✅ Redirects to Hub after completion

### Brand Compliance
- ✅ The Dot colors used (charcoal, lime, warm gray, cream)
- ✅ Futura PT font for headings
- ✅ FF Real Text Pro for body
```

---

## Final Recommendation

### Optimal Parallel Strategy

**Weeks 1-2:** Full parallel (AI #1 backend, AI #2 frontend) - **50% time saved**
**Week 3-4:** Mostly parallel with daily handoffs - **30% time saved**
**Week 5:** Joint integration testing - **Sequential (no time saved)**
**Week 6:** Split QA (AI #1) + Docs (AI #2) - **Parallel again**

**Total Time Saved:** ~1.5 weeks (from 7.5 weeks → 6 weeks)

**Risk Level:** Low (if interface contract enforced and Git workflow followed)

---

## Questions to Resolve Before Starting

### For You (User):

1. **Do you have another AI agent ready?**
   - If yes, which AI? (Claude, GPT-4, etc.)
   - If no, should we delay parallel execution?

2. **Testing infrastructure:**
   - Can you set up 2 separate WordPress installs for isolated testing?
   - Or should both AIs share one test site?

3. **Git repository:**
   - Is this in Git already?
   - Should AIs commit directly or submit via pull requests?

4. **Communication:**
   - How should AIs communicate? (Shared document, Slack, GitHub issues?)
   - Daily standups required or just weekly?

5. **Decision authority:**
   - If AIs disagree on interface contract, who decides?
   - Can AIs approve each other's code or do you review all?

---

**End of Parallel Execution Plan**

Ready to begin? Which AI will be AI #1 (Backend) and which will be AI #2 (Frontend)?
