# Next Phases Implementation Plan

**Created:** October 22, 2025
**Updated:** October 29, 2025
**Status:** Phase 5+9 Complete → AI Hotfix Complete → Phase 6 Prerequisites
**Based on:** External AI review + RND-002 strategic analysis

---

## Executive Summary

Following the completion of Phase 5+9, we need to implement **prerequisite infrastructure** before starting Phase 6 and Phase 10 Stage 1. The external reviewer identified critical dependencies that must be addressed.

**Recommended Order:**
1. ✅ **Phase 5+9** - COMPLETE
2. ✅ **AI Prompt Hotfix (Oct 29)** - COMPLETE (see below)
3. 🔄 **Feature Flags System** - Track C prerequisite (1 week)
4. 🔄 **Migration Framework** - Phase 6 prerequisite (3-4 days)
5. 🔄 **Phase 6** - Template Intelligence (2-3 weeks)
6. 🔄 **Phase 10 Stage 1** - AVIF + Quick Wins (2-3 weeks)
7. ⏸️ **Phase 7** - Multilingual Admin UX (3-4 weeks)
8. ⏸️ **Phase 8** - Analytics (4-5 weeks)

---

## ✅ AI Prompt Architecture Hotfix (October 29, 2025)

**Status:** COMPLETE
**Commits:** `8e7a0cc`, `84223bd`
**Documentation:** [docs/PHASE6-AI-IMPROVEMENTS.md](docs/PHASE6-AI-IMPROVEMENTS.md), [docs/TELEMETRY-INTEGRATION.md](docs/TELEMETRY-INTEGRATION.md)

### Why This Was Critical

Before implementing Phase 6 (Template Intelligence), we discovered a fundamental flaw in the AI metadata generation system:

**The Problem:**
- AI prompt instructed: "always include business name"
- Validator blocked: business name for generic images
- Result: **100% validator rejection** → all AI suggestions fell back to poor heuristic metadata

**User Impact:**
- Generic stock photos (landscapes, bridges) got nonsensical "Medical Treatment - Main Street Health" metadata
- AI was generating accurate descriptions but validator rejected them
- Token waste: Paying for AI calls that were always rejected

### What Was Implemented

**1. brand_name_visible Flag System**
- Explicit boolean controls AI's business name usage
- Business-related types (team, facility, equipment) → `true`
- Generic images (landscapes, stock photos) → `false`
- Configurable via `msh_business_related_types` filter

**2. Structured SYSTEM/USER Prompt (v20251029.1)**
- Professional prompt architecture
- SYSTEM message: role definition + guidelines
- USER message: context parameters (brand_name_visible, context_type, page_role)
- Clear rules prevent AI/validator conflicts

**3. Enhanced JSON Output**
```json
{
  "file_name_suggestion": "...",
  "keywords": ["...", "..."],
  "confidence": 0.92,
  "issues": ["text_in_image_detected"]
}
```

**4. Quality Metadata**
- `confidence` (0.0-1.0): AI's confidence score
- `issues[]`: Detected problems (brand_name_assumed, low_confidence, decorative_image, text_in_image_detected)
- Validator uses issues array to make smart decisions

**5. Telemetry Integration with MSH_Telemetry**
- ✅ Integrated with existing `MSH_Telemetry` class
- ✅ Events stored in `wp_msh_telemetry` table
- ✅ Sent weekly to `telemetry.thedot.com` API
- ✅ Anonymous, GDPR-compliant

**Telemetry Events:**
- `ai_batch_complete`: confidence_avg, success/fallback counts, quality issues
- `ai_token_usage`: per-image token costs with prompt version

**6. Prompt Version Tracking**
- `const PROMPT_VERSION = '20251029.1'`
- Logged in every AI call
- Enables A/B testing and quality tracking

### Results

**Before:**
```
AI: "Golden Gate Bridge at Sunset"
Validator: REJECT (no business name)
Fallback: "Medical Treatment - Main Street Health Ontario, Canada"
```

**After:**
```
AI: "Golden Gate Bridge at Sunset" (brand_name_visible=false)
Validator: ACCEPT
Final: "Golden Gate Bridge at Sunset"
```

**Quality Improvements:**
- ✅ Accurate descriptions for stock photos
- ✅ Consistent branding on business images
- ✅ No more validator rejections
- ✅ Token tracking per image
- ✅ Batch quality metrics (confidence_avg, issues breakdown)

### Why This Needed to Happen Before Phase 6

**Phase 6 (Template Intelligence)** depends on:
1. **Quality baseline**: Need accurate AI metadata to compare against templates
2. **Telemetry infrastructure**: Track template hits vs AI fallbacks
3. **Confidence scoring**: Determine when to use template vs AI
4. **Token tracking**: Measure template ROI (30-50% AI call reduction)

Without this hotfix, Phase 6 would have been built on a broken foundation.

---

## Critical Prerequisites (Before Phase 6)

### 1. Feature Flags System (Track C Deliverable)

**Why Critical:**
> "Feature Flags needs to graduate from the bare helper to a first-class Track C deliverable before Phase 6 starts... otherwise we can't safely run the migrations or AVIF pilot."

**Current State:**
- Helper function exists: `msh_flag_enabled()`
- No UI, no CLI, no cohort support, no admin interface

**What to Build:**

#### A. Database Table
```sql
CREATE TABLE wp_msh_feature_flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flag_key VARCHAR(100) NOT NULL UNIQUE,
    enabled TINYINT(1) DEFAULT 0,
    rollout_type ENUM('off', 'on', 'percentage', 'cohort', 'user', 'role', 'site') DEFAULT 'off',
    rollout_value TEXT NULL, -- JSON config
    description TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_flag_key (flag_key),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### B. Core Class (`includes/enterprise/class-msh-feature-flags.php`)
```php
class MSH_Feature_Flags {
    // Check if flag is enabled
    public function is_enabled( $flag_key, $context = [] );

    // Set flag state
    public function set_flag( $flag_key, $config );

    // Get all flags
    public function get_all_flags();

    // Percentage rollout (0-100)
    public function enable_percentage( $flag_key, $percentage );

    // User/role targeting
    public function enable_for_user( $flag_key, $user_id );
    public function enable_for_role( $flag_key, $role );

    // Site targeting (multisite)
    public function enable_for_site( $flag_key, $site_id );
}
```

#### C. WP-CLI Commands
```bash
# List all flags
wp msh flag list

# Set flag state
wp msh flag set <flag-key> on|off
wp msh flag set avif_conversion on --percentage=25
wp msh flag set ai_safe_rename on --user=5
wp msh flag set template_intelligence on --role=administrator

# Get flag status
wp msh flag status <flag-key>

# Clear flag
wp msh flag delete <flag-key>
```

#### D. Admin UI (Settings → Advanced Tab)
- Table showing all flags
- Toggle switches for on/off
- Advanced rollout configuration
- Telemetry integration (track flag checks)

#### E. Predefined Flags
```php
// Phase 6
'template_intelligence' => [
    'name' => 'Template Intelligence',
    'description' => 'Use templates for metadata generation before AI calls',
],

// Phase 10 Stage 1
'avif_conversion' => [
    'name' => 'AVIF Conversion',
    'description' => 'Convert images to AVIF format via ImageKit',
],
'picture_tag_support' => [
    'name' => 'Picture Tag Support',
    'description' => 'Generate <picture> tags with format fallbacks',
],
'priority_hints' => [
    'name' => 'Priority Hints',
    'description' => 'Add fetchpriority attribute to hero images',
],

// Phase 8
'analytics_tracking' => [
    'name' => 'Analytics Tracking',
    'description' => 'Track performance metrics in wp_msh_optimizer_metrics table',
],
```

**Estimated Effort:** 1 week (5-7 days)

**Deliverables:**
- ✅ Database table + migration
- ✅ Core flag class
- ✅ WP-CLI commands (6 commands)
- ✅ Admin UI in Settings → Advanced
- ✅ Telemetry integration
- ✅ Documentation

---

### 2. Expand/Backfill/Switch/Contract Migration Framework

**Why Critical:**
> "Expand/Backfill/Switch/Contract should be baked into the Phase 6 migrations... Add the same pattern to the Phase 8 metrics rollout so the analytics tables land under flag control."

**What Is Expand/Backfill/Switch/Contract?**

A **zero-downtime migration pattern** for schema changes:

```
1. EXPAND: Add new table/column alongside old (dual-write begins)
2. BACKFILL: Copy old data to new structure (background job)
3. SWITCH: Flip feature flag to read from new structure
4. CONTRACT: Remove old table/column (after verification)
```

**Why Use It:**
- ✅ Zero downtime during migrations
- ✅ Instant rollback (flip flag back)
- ✅ Safe for production sites
- ✅ Gradual rollout (test with 5%, then 25%, then 100%)

**Example: Phase 6 Template Intelligence**

```sql
-- EXPAND: Add new template system (Phase 6 start)
CREATE TABLE wp_msh_optimizer_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locale VARCHAR(10) NOT NULL,
    usage_type VARCHAR(50) NOT NULL,
    intent ENUM('on_topic', 'off_topic', 'unknown') NOT NULL,
    template_title TEXT,
    template_alt TEXT,
    template_caption TEXT,
    template_description TEXT,
    required_tokens TEXT, -- JSON array
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_locale_usage_intent (locale, usage_type, intent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BACKFILL: Migration script runs in background
-- (no backfill needed for Phase 6 - new table, no old data)

-- SWITCH: Feature flag enabled
-- wp msh flag set template_intelligence on --percentage=10

-- CONTRACT: After 30 days of 100% rollout
-- (nothing to remove - pure expansion)
```

**What to Build:**

#### A. Migration Helper Class (`includes/class-msh-migration-helper.php`)
```php
class MSH_Migration_Helper {
    // Run dual-write (write to both old and new)
    public function dual_write( $old_table, $new_table, $data );

    // Backfill data from old to new
    public function backfill( $old_table, $new_table, $batch_size = 100 );

    // Verify data parity (old == new)
    public function verify_parity( $old_table, $new_table );

    // Switch read source via feature flag
    public function switch_read_source( $flag_key );

    // Clean up old structure
    public function contract( $old_table, $flag_key );
}
```

#### B. WP-CLI Migration Commands
```bash
# Run backfill for a migration
wp msh migrate backfill <migration-name> --batch-size=100

# Verify data parity
wp msh migrate verify <migration-name>

# Switch to new structure (sets flag to 100%)
wp msh migrate switch <migration-name>

# Contract old structure (drop old table/column)
wp msh migrate contract <migration-name> --confirm
```

#### C. Migration Registry
Track all migrations and their states:
```php
// Phase 6: Template Intelligence
'phase6_templates' => [
    'expand_sql' => '0001_create_templates_table.sql',
    'backfill_callback' => null, // No backfill needed
    'flag_key' => 'template_intelligence',
    'contract_sql' => null, // Nothing to remove
],

// Phase 8: Analytics
'phase8_metrics' => [
    'expand_sql' => '0002_create_metrics_table.sql',
    'backfill_callback' => 'msh_backfill_metrics',
    'flag_key' => 'analytics_tracking',
    'contract_sql' => null,
],
```

**Estimated Effort:** 3-4 days

**Deliverables:**
- ✅ Migration helper class
- ✅ WP-CLI migration commands
- ✅ Migration registry system
- ✅ Documentation + playbook

---

## Phase 6: Template Intelligence (2-3 Weeks)

**Dependencies:**
- ✅ Feature Flags system
- ✅ Migration framework

**What It Does:**
Use pre-built templates for metadata generation BEFORE calling AI, reducing costs and improving speed.

**Database:** `wp_msh_optimizer_templates`

**Implementation Steps:**

### Week 1: Foundation (5-7 days)
1. **Day 1-2:** Database table + migration (using Expand pattern)
2. **Day 3-4:** Template manager class
3. **Day 5-7:** Template matching logic (locale + usage_type + intent)

### Week 2: Integration (5-7 days)
4. **Day 8-9:** Modify regeneration worker to check templates first
5. **Day 10-11:** Token replacement system ({brand}, {city}, {subject})
6. **Day 12-14:** Create starter templates (5-10 common patterns)

### Week 3: Testing & Polish (3-5 days)
7. **Day 15-16:** Feature flag rollout (5% → 25% → 50% → 100%)
8. **Day 17-18:** Telemetry tracking (template hits vs AI fallbacks)
9. **Day 19-21:** Documentation + WP-CLI commands

**Key Features:**
- ✅ Locale-specific templates
- ✅ Usage type awareness (featured, inline, gallery)
- ✅ Intent-based matching (on_topic vs off_topic)
- ✅ Token validation before applying template
- ✅ Graceful AI fallback if no template matches
- ✅ Template CRUD via WP-CLI

**Success Metrics:**
- 30-50% reduction in AI calls
- <100ms template application time
- Zero quality degradation vs AI-generated

**Feature Flag:** `template_intelligence`

---

## Phase 10 Stage 1: WordPress Hybrid + Quick Wins (2-3 Weeks)

**Dependencies:**
- ✅ Phase 6 complete
- ✅ Feature Flags system

**What It Includes:**

### A. ImageKit Integration (Week 1: 5-7 days)

**IMPORTANT:** Before implementing ImageKit, we must add bandwidth optimization safeguards to prevent hosting overages and API cost bloat. See [RND-002 Idea #7](docs/RND-002-RESEARCH-IDEAS.md#idea-7-bandwidth--api-cost-optimization) for full details.

#### A.1: Bandwidth Optimization (Day 1 - PREREQUISITE)

**Why Critical:** Without bandwidth optimization, sending full-resolution images (6000px, 10MB+) to ImageKit will:
- Consume 8-16GB monthly bandwidth on typical sites (16-32% of 50GB shared hosting cap)
- Cost $150+/month in ImageKit fees (Gate 1 trigger hit immediately)
- Cause 30-40% timeout failures on slow connections
- Re-process unchanged images repeatedly (no caching)

**Implementation:**
1. **Day 1:** Build `MSH_Bandwidth_Optimizer` class
   - Pre-downscale before ImageKit upload (6000px → 2000px = 80% bandwidth savings)
   - Hash-based caching (skip unchanged images = 100% savings on duplicates)
   - File size caps (10MB max for AI, 20MB max for AVIF)
   - Bandwidth budgeting (500MB/day default cap)

**Components:**
```php
// includes/cloud/class-msh-bandwidth-optimizer.php
class MSH_Bandwidth_Optimizer {
    public function prepare_for_remote_processing( $attachment_id, $max_dimension, $purpose );
    public function should_process_image( $attachment_id, $operation );
    public function check_bandwidth_budget( $bytes_to_send, $provider );
    public function track_bandwidth_usage( $bytes, $provider );
    public function cleanup_working_copy( $working_copy, $original );
}
```

**Expected Impact:**
- ✅ **80-95% bandwidth reduction** per image
- ✅ **90% cost savings** on ImageKit API calls
- ✅ **<5% timeout rate** (vs 30-40% current)
- ✅ **Fail-safe protection** against hosting overages

---

#### A.2: ImageKit Adapter (Day 2-3)

**Integration with Bandwidth Optimizer:**

```php
// includes/cloud/class-msh-imagekit-adapter.php
class MSH_ImageKit_Adapter {

    private $bandwidth_optimizer;

    public function __construct() {
        $this->bandwidth_optimizer = new MSH_Bandwidth_Optimizer();
    }

    public function convert_to_avif( $attachment_id ) {
        // Check if already processed (hash cache)
        if ( ! $this->bandwidth_optimizer->should_process_image( $attachment_id, 'avif_convert' ) ) {
            return $this->get_cached_avif_url( $attachment_id );
        }

        // Pre-downscale (6000px → 2000px for quality balance)
        $working_copy = $this->bandwidth_optimizer->prepare_for_remote_processing(
            $attachment_id,
            2000, // Max dimension for AVIF
            'avif_convert'
        );

        if ( is_wp_error( $working_copy ) ) {
            return $working_copy; // File too large or over budget
        }

        // Check bandwidth budget
        $file_size = filesize( $working_copy );
        $budget_check = $this->bandwidth_optimizer->check_bandwidth_budget( $file_size, 'imagekit' );

        if ( is_wp_error( $budget_check ) ) {
            $this->bandwidth_optimizer->cleanup_working_copy( $working_copy, get_attached_file( $attachment_id ) );
            return $budget_check; // Over daily cap
        }

        // Upload to ImageKit (now 80% smaller)
        $result = $this->upload_to_imagekit( $working_copy, $attachment_id );

        // Track bandwidth
        $this->bandwidth_optimizer->track_bandwidth_usage( $file_size, 'imagekit' );

        // Cleanup
        $this->bandwidth_optimizer->cleanup_working_copy( $working_copy, get_attached_file( $attachment_id ) );

        return $result;
    }

    public function convert_to_webp( $attachment_id ) {
        // Similar integration with bandwidth optimizer
    }

    public function get_delivery_url( $attachment_id, $format ) {
        // Return ImageKit CDN URL
    }
}
```

---

#### A.3: Multi-Size Processing (Day 4-5)

**Why Critical:** Currently only full-size originals are converted to WebP/AVIF. Themes need ALL WordPress sizes (thumbnail, medium, large) in modern formats for responsive images. See [RND-002 Idea #8](docs/RND-002-RESEARCH-IDEAS.md#idea-8-multi-size-format-conversion).

**Implementation:**
```php
// includes/class-msh-multi-size-converter.php
class MSH_Multi_Size_Converter {
    public function convert_all_sizes( $attachment_id, $format = 'webp' ) {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $results = array();

        // Convert full-size
        $results['full'] = $this->convert_single_size( get_attached_file( $attachment_id ), $format );

        // Convert ALL WordPress sizes (thumbnail, medium, large, custom)
        foreach ( $metadata['sizes'] as $size_name => $size_data ) {
            $size_path = dirname( get_attached_file( $attachment_id ) ) . '/' . $size_data['file'];
            $results[ $size_name ] = $this->convert_single_size( $size_path, $format );
        }

        return $results;
    }
}
```

**Expected Output:**
```
doctor-consultation.jpg (6000px) → doctor-consultation.webp + doctor-consultation.avif
doctor-consultation-150x150.jpg → doctor-consultation-150x150.webp + .avif
doctor-consultation-300x200.jpg → doctor-consultation-300x200.webp + .avif
doctor-consultation-768x512.jpg → doctor-consultation-768x512.webp + .avif
doctor-consultation-1024x683.jpg → doctor-consultation-1024x683.webp + .avif
```

**Result:** Complete format coverage for responsive images and `<picture>` tags.

---

#### A.4: Job Queue Integration (Day 6-7)

**Timeline:**
1. **Day 1:** Bandwidth optimizer class (prerequisite)
2. **Day 2-3:** ImageKit adapter with bandwidth integration
3. **Day 4-5:** Multi-size converter (WebP + AVIF for all sizes)
4. **Day 6-7:** Job queue integration + testing

**Components:**
```php
// includes/cloud/class-msh-bandwidth-optimizer.php (NEW)
// includes/cloud/class-msh-imagekit-adapter.php
// includes/class-msh-multi-size-converter.php (NEW)
```

**Feature Flag:** `avif_conversion`

---

### B. Quick Win #1: Picture Tag Support (2-3 hours)
```html
<!-- Before -->
<img src="image.jpg" alt="...">

<!-- After -->
<picture>
    <source type="image/avif" srcset="image.avif">
    <source type="image/webp" srcset="image.webp">
    <img src="image.jpg" alt="...">
</picture>
```

**Implementation:**
- Filter `the_content` and `post_thumbnail_html`
- Wrap `<img>` tags in `<picture>` wrapper
- Generate AVIF/WebP variants via ImageKit

**Value:** 100-200ms LCP improvement, zero infrastructure cost

**Feature Flag:** `picture_tag_support`

**Effort:** 2-3 hours

---

### C. Quick Win #2: Priority Hints (1-2 hours)
```html
<!-- Hero image -->
<img src="image.jpg" fetchpriority="high" alt="...">

<!-- Below-fold images -->
<img src="image.jpg" loading="lazy" alt="...">
```

**Implementation:**
- Detect first image in content (hero)
- Add `fetchpriority="high"` attribute
- Add `loading="lazy"` to all others

**Value:** 50-100ms LCP improvement

**Feature Flag:** `priority_hints`

**Effort:** 1-2 hours

---

### D. Streaming UI + Telemetry (Week 2: 5-7 days)
1. **Day 8-10:** Streaming progress UI (job status updates)
2. **Day 11-12:** Jobs page visibility improvements
3. **Day 13-14:** Telemetry for Gate 1 metrics

**Gate 1 Tracking:**
- Total installs count
- ImageKit API call volume
- ImageKit monthly cost
- Job processing times
- AVIF adoption rate

---

### Phase 10 Full Breakdown

**Stage 1 (Days 1-30):** WordPress Hybrid + Quick Wins ← **WE'RE HERE**
- ImageKit integration
- AVIF/WebP conversion
- Picture tag support
- Priority hints
- Telemetry

**Gate 1:** 500+ installs OR ImageKit costs >$150/month

---

**Stage 2 (Days 31-60):** Optimizer Cloud MVP
- Supabase Edge Functions (3 endpoints)
- Multi-tenant database
- Credit system (free/pro/agency)
- Migrate from ImageKit to own cloud

**Gate 2:** 1,500+ installs OR 6,000+ monthly API calls

---

**Stage 3 (Days 61-90+):** Multi-Platform Launch
- Shopify app
- Webflow app
- Direct API tier
- Unified billing (Lemon Squeezy)

**Gate 3:** 3,000+ installs OR multi-platform demand

---

**Stage 4-6 (Months 4-12):** Advanced Image Delivery
- Responsive variant generation
- AI priority scoring manifest
- Custom lazy loader with priority queue

**Prerequisites:** 1,500+ installs, Phase 8 analytics live

---

## Alignment with External Reviewer's Recommendations

### ✅ Idea #1: Expand/Backfill/Switch/Contract
**Recommendation:** "Front-load the CLI/backfill work during Phase 5+9 wrap-up"

**Action:** Build migration framework NOW (before Phase 6)

---

### ✅ Idea #2: Feature Flags Graduate to Track C
**Recommendation:** "Feature Flags needs to graduate... before Phase 6 starts"

**Action:** Build full feature flag system NOW (UI/CLI/telemetry/cohorts)

---

### ✅ Idea #3: AVIF Aligns with Phase 10 Stage 1
**Recommendation:** "Ship <picture> + priority hints as quick wins, heavy encoding behind flag"

**Action:** Quick wins immediately, ImageKit adapter behind `avif_conversion` flag

---

### ✅ Idea #4: Staged Cloud Already Mirrored
**Recommendation:** "Keep current Supabase sync as Stage 1"

**Action:** Current Phase 5+9 sync counts as foundation, ImageKit is true Stage 1

---

### ✅ Idea #5: AI Delivery Depends on AVIF + Phase 8
**Recommendation:** "Lock in quick wins alongside AVIF, defer variants to Stage 4+"

**Action:** Quick wins in Stage 1, advanced delivery in Stages 4-6

---

### ✅ Idea #6: Per-Locale Sitemaps in Phase 7
**Recommendation:** "Add as named deliverable in Phase 7"

**Action:** Defer to Phase 7, guard behind feature flag

---

## Revised Timeline

### Immediate (Next 2 Weeks)
1. **Feature Flags System** - 1 week
2. **Migration Framework** - 3-4 days

### Phase 6 (Weeks 3-5)
3. **Template Intelligence** - 2-3 weeks

### Phase 10 Stage 1 (Weeks 6-8)
4. **ImageKit Integration** - 1 week
5. **Quick Wins (Picture + Priority)** - 3-4 hours
6. **Streaming UI + Telemetry** - 1 week

### Total to Stage 1 Complete
**8 weeks** (2 months) from today

---

## Next Actions

**This Week:**
1. Build Feature Flags system (database, core class, CLI, UI)
2. Build Migration framework (helper class, CLI commands, registry)

**Next Week:**
3. Start Phase 6: Template Intelligence (using new migration framework)

**Weeks 3-4:**
4. Complete Phase 6
5. Test template system with 5% → 25% → 100% rollout

**Weeks 5-7:**
6. Phase 10 Stage 1: ImageKit + AVIF + Quick Wins
7. Deploy with feature flags for gradual rollout

**Week 8:**
8. Monitor Gate 1 metrics
9. Prepare for Phase 7 (or Stage 2 if Gate 1 met early)

---

## Success Criteria

**Feature Flags Complete When:**
- ✅ All 6 WP-CLI commands work
- ✅ Admin UI in Settings → Advanced
- ✅ Cohort/percentage/user/role targeting works
- ✅ Telemetry tracks flag checks

**Migration Framework Complete When:**
- ✅ Expand/Backfill/Switch/Contract pattern working
- ✅ WP-CLI migration commands functional
- ✅ Migration registry tracks all migrations
- ✅ Documentation complete

**Phase 6 Complete When:**
- ✅ Templates table created (via Expand pattern)
- ✅ 5-10 starter templates loaded
- ✅ Template matching works (30-50% AI call reduction)
- ✅ Feature flag rollout successful (100% enabled)
- ✅ Telemetry shows template hits vs AI fallbacks

**Phase 10 Stage 1 Complete When:**
- ✅ ImageKit adapter functional
- ✅ AVIF conversion working behind flag
- ✅ Picture tag support deployed
- ✅ Priority hints working
- ✅ Gate 1 telemetry tracking
- ✅ Ready to scale to 500+ installs

**Phase 10 Stage 2 Complete When:**
- ✅ Supabase Edge Functions deployed (3 endpoints)
- ✅ Multi-tenant database operational
- ✅ Credit system working (free/pro/agency tiers)
- ✅ WordPress migrated from ImageKit to own cloud
- ✅ Gate 2 metrics met (1,500+ installs OR 6,000+ API calls)

---

## Pre-Launch: Support Infrastructure

**Why Critical:**
> "Once you start getting real installs and support requests, a ticket system becomes essential for sanity. WordPress.org support threads are public and unstructured — fine for bug reports, terrible for real customer issues."

### Support Platform Strategy

**Stage 1 (Launch):** Fluent Support Plugin (Self-Hosted)
- **Why:** Native WP integration, connects to WP users & licenses, free basic tier
- **Setup:** Install in your main site's `/support/` area
- **Channels:**
  - "Free Users (WP.org)" — email + simple form
  - "Pro Users" — license key required
- **Auto-tagging:** By product (Image Optimizer, Signature Architect, etc.)
- **Canned responses:** Common issues (invalid key, sync delay, cloud quota full)

**Stage 2 (Growth - 20+ tickets/month):** Migrate to HelpScout
- **Cost:** ~$20/month
- **Benefits:** Beautiful UI, email-based workflow, collision detection, Stripe integration
- **Migration:** Export Fluent data (CSV/JSON) and import

---

### Support Flow Architecture

#### 1. Entry Points

**In-Plugin "Report Issue" Button:**
```php
// Opens /support/ page with context
$url = 'https://thedotcreative.co/support/?' . http_build_query([
    'product' => 'image-optimizer',
    'version' => MSH_VERSION,
    'license_type' => $this->license_manager->is_pro_active() ? 'pro' : 'free',
]);
```

**Auto-Captured Diagnostic Data:**
- Plugin version
- WordPress version
- PHP version
- Active theme
- Active plugins count
- License type (pro/free)

**Email Alias:** `support@thedotcreative.co` (piped into Fluent Support)

---

#### 2. Ticket Categorization Rules

| Trigger | Action |
|---------|--------|
| Subject contains "error" / "fatal" | Assign to **Dev Queue** |
| Subject contains "billing" / "license" | Assign to **Accounts Queue** |
| Product = Image Optimizer | Add tag **MSH** |
| No license found | Auto-reply: "Please include your license key or WP version info." |

---

#### 3. Response Workflow

**Triage Process:**
1. Review inbox daily
2. If solvable immediately → Reply → Mark **Resolved**
3. If code fix required → Push to **GitHub Issues**, link back to ticket
4. If repeated > 3 times → Mark "**Needs FAQ**"

**Service-Level Goals:**

| Type | Target |
|------|--------|
| Free users | 48h response |
| Pro users | 12h response |
| Critical bug | 4h acknowledgement |

---

#### 4. Resolution & Feedback Loop

**Auto-Email on Resolve:**
- "Was this helpful?" (👍/👎 link)
- Positive → closes ticket
- Negative → reopens & tags **Revisit Needed**

**Weekly Continuous Improvement:**
1. Export tags → Find top 5 issue clusters
2. Update public Knowledge Base (Fluent KB or Notion embed)
3. Add inline "Learn more" links in plugin UI → deep-link to KB
4. Log patterns in Notion → plan fixes for next patch

---

#### 5. Optional Automations

- Auto-close stale tickets after 7 days inactivity
- Auto-assign priority based on Pro license tier
- Slack/Discord hook → instant alert when ticket tagged **Critical**

---

### Implementation Checklist

**Before WordPress.org Launch:**
- [ ] Install Fluent Support plugin on thedotcreative.co
- [ ] Create 2 support channels (Free/Pro)
- [ ] Set up email piping (support@thedotcreative.co)
- [ ] Create canned responses (10-15 common issues)
- [ ] Add "Report Issue" button to plugin admin
- [ ] Test ticket flow end-to-end
- [ ] Create initial KB articles (5-10 FAQs)

**Post-Launch (First 30 Days):**
- [ ] Monitor ticket volume and response times
- [ ] Identify top 5 issue clusters
- [ ] Expand KB based on actual support requests
- [ ] Add more inline help links in plugin UI
- [ ] Evaluate migration to HelpScout (if >20 tickets/month)

---

## Revised Launch Roadmap

**Updated based on:** Track C → Phase 10 Stage 1 → Phase 10 Stage 2 → Production Testing → WP.org Submit

### Timeline to WordPress.org Launch

**Infrastructure (Weeks 1-2):**
- Week 1: Feature Flags system (parallel work with other AI)
- Week 1.5: Migration Framework (you)

**Core Features (Weeks 3-7):**
- Weeks 3-5: Phase 6 - Template Intelligence (2-3 weeks)
- Weeks 6-7: Phase 10 Stage 1 - AVIF + ImageKit + Quick Wins (2 weeks)

**Cloud Infrastructure (Weeks 8-10):**
- Weeks 8-10: Phase 10 Stage 2 - Supabase Edge Functions + Cloud MVP (3 weeks)

**Pre-Launch (Weeks 11-12):**
- Week 11: Support infrastructure setup (Fluent Support + KB)
- Week 11-12: Production testing on image-heavy site
  - Test with 500+ images
  - Load testing with concurrent requests
  - AVIF conversion validation
  - Template matching accuracy
  - Cloud sync reliability
  - Full Hub UI testing
  - WP-CLI command verification

**Launch (Week 13):**
- WordPress.org submission + review process (1-2 weeks review time)

**Total:** ~13-15 weeks (3-4 months) to WordPress.org launch

---

## Questions?

**What's the order?** Infrastructure → Phase 6 → Stage 1 → Stage 2 → Production Testing → WP.org Submit

**Can Flags + Migration be parallel?** YES! Other AI does Flags, you do Migration (no conflicts)

**How long total?** 13-15 weeks (3-4 months) to WordPress.org launch

**What about Phase 7-8?** Post-launch enhancements (multilingual UX + analytics)

**When do we skip Phase 7-8?** NOW - they're deferred to post-launch

---

## Success Criteria for WordPress.org Launch

**Technical Requirements:**
- ✅ Phase 5+9 complete (automation + enterprise)
- ✅ Phase 6 complete (template intelligence)
- ✅ Phase 10 Stage 1 complete (AVIF + quick wins)
- ✅ Phase 10 Stage 2 complete (cloud MVP)
- ✅ All feature flags tested (gradual rollout successful)
- ✅ Migration framework battle-tested
- ✅ Production testing passed (500+ images)

**Support Infrastructure:**
- ✅ Fluent Support installed and configured
- ✅ Email piping working (support@thedotcreative.co)
- ✅ Knowledge Base with 10+ articles
- ✅ In-plugin "Report Issue" button functional
- ✅ Canned responses created

**WordPress.org Compliance:**
- ✅ All security reviews passed
- ✅ GPL-compatible licensing
- ✅ No hardcoded credentials
- ✅ Sanitization/escaping complete
- ✅ Documentation complete
- ✅ Screenshots and assets ready

---

**Bottom Line:**
- **Parallel work starts NOW:** Other AI builds Feature Flags, you build Migration Framework
- **3-4 months to launch** with Track C → Stage 1 → Stage 2 → Testing → Submit
- **Support infrastructure built in Week 11** (before production testing)
- **Phase 7-8 deferred** to post-launch (not blocking WordPress.org submission)

**Ready to start Migration Framework?** Let's do it! 🚀
