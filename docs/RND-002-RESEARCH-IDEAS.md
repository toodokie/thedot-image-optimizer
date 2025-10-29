# RND-002: Research & Development Ideas Collection

**Created:** October 22, 2025
**Status:** 📋 Living Document - Add Ideas As They Come
**Purpose:** Collect promising patterns, techniques, and strategies for future implementation

---

## What This Document Is

A collection of **good ideas** we discover that could improve the MSH Image Optimizer plugin. Not all ideas will be implemented immediately - this is our research backlog.

**How to Use:**
- Add new ideas as separate sections
- Mark priority (High/Medium/Low)
- Link to phases where applicable
- Document source (article, conversation, testing discovery)
- Keep ideas concise but actionable

---

## Table of Contents

1. [Idea #1: Expand-Backfill-Switch-Contract Pattern (Safe Schema Migrations)](#idea-1-expand-backfill-switch-contract-pattern)
2. [Idea #2: Feature Flags for Safe Rollouts](#idea-2-feature-flags-for-safe-rollouts)
3. [Idea #3: AVIF Image Conversion](#idea-3-avif-image-conversion)
4. [Idea #4: Staged Cloud Architecture (30-60-90 Day Roadmap)](#idea-4-staged-cloud-architecture-30-60-90-day-roadmap)
5. [Idea #5: AI-Powered Image Delivery Optimization](#idea-5-ai-powered-image-delivery-optimization)
6. [Idea #6: Per-Locale Image Sitemaps with Hreflang](#idea-6-per-locale-image-sitemaps-with-hreflang)
7. [Idea #7: Bandwidth & API Cost Optimization](#idea-7-bandwidth--api-cost-optimization)
8. [Idea #8: Multi-Size Format Conversion](#idea-8-multi-size-format-conversion)
9. [Future ideas...](#future-ideas)

---

## Idea #1: Expand-Backfill-Switch-Contract Pattern

**Source:** User-provided article on zero-downtime migrations
**Priority:** 🔴 High (Infrastructure for Phase 6+)
**Related Phases:** Phase 6 (Template Intelligence), Phase 8 (Analytics/Metrics)
**Status:** Approved for implementation

### Quick Summary

Safe, zero-downtime database migration strategy using four phases: Expand → Backfill → Switch → Contract

**Benefits:**
- ✅ Zero downtime during schema changes
- ✅ Easy rollback at any stage (feature flags)
- ✅ Gradual data migration (no server overload)
- ✅ Validation period to catch issues early
- ✅ Reusable infrastructure across all future phases

**Use Cases:**
- Phase 6: Migrating from "AI-only" to "Template-first with AI fallback"
- Phase 8: Adding metrics table with 4.5M+ rows
- Any future schema evolution

---

### Table of Contents (Idea #1)

1. [The Pattern Overview](#11-the-pattern-overview)
2. [Why We Need This](#12-why-we-need-this)
3. [Implementation Strategy](#13-implementation-strategy)
4. [Phase 6 Application](#14-phase-6-application-template-intelligence-migration)
5. [Phase 8 Application](#15-phase-8-application-metrics-table-migration)
6. [Additional Migration Ideas](#16-additional-migration-ideas)
7. [Action Plan & Timeline](#17-action-plan--timeline)

---

## 1.1 The Pattern Overview

### Four-Phase Approach

A four-phase approach to schema changes that maintains backward compatibility and allows easy rollback:

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   EXPAND    │────▶│  BACKFILL   │────▶│   SWITCH    │────▶│  CONTRACT   │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
 Add new schema      Copy old→new        Change reads        Remove old
 (backwards compat)  (gradual batches)   (dual-write)        (cleanup)
```

### Migration Phase Breakdown

#### Step 1: EXPAND 🔧
**What:** Add new tables/columns that current code doesn't use yet

**Actions:**
- Create new tables with safe defaults
- Add new columns (nullable or with defaults)
- Create indexes for new schema
- **DO NOT** drop anything yet

**Duration:** Immediate (single deployment)

**Example:**
```sql
-- Add new template intelligence table
CREATE TABLE wp_msh_optimizer_templates (
    template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    locale VARCHAR(10) NOT NULL,
    usage_type VARCHAR(50),
    pattern TEXT,
    template TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (template_id),
    KEY idx_locale_usage (locale, usage_type)
) ENGINE=InnoDB;

-- Add migration tracking columns to context table (nullable, safe)
ALTER TABLE wp_msh_optimizer_context
ADD COLUMN template_id BIGINT UNSIGNED NULL DEFAULT NULL,
ADD COLUMN generation_method ENUM('ai', 'template', 'manual') NULL DEFAULT NULL;
```

---

#### Step 2: BACKFILL 📊
**What:** Copy data from old shape to new shape in small batches

**Actions:**
- Process data in ID ranges (e.g., 1-5000, 5001-10000)
- Resumable (save progress markers)
- Throttled (sleep between batches)
- Idempotent (safe to re-run)

**Duration:** Hours to days (depending on data volume)

**Example:**
```bash
# WP-CLI command (resumable batch processing)
wp msh migrate templates \
  --batch-size=500 \
  --from-id=1000 \
  --to-id=50000 \
  --sleep=1

# Progress tracking
# Batch 1: 500 items migrated (up to ID 1500)
# Batch 2: 500 items migrated (up to ID 2000)
# ...
```

---

#### Step 3: SWITCH 🔄
**What:** Deploy code that writes to both old and new, reads from new

**Actions:**
- Dual-write: save to both schemas
- Single-read: read from new schema only
- Validation logging (compare old vs new)
- Feature flag control
- Run for 1-2 weeks to prove parity

**Duration:** 1-2 weeks (validation period)

**Example:**
```php
// Feature flag controls read source
if (MSH_Feature_Flags::is_enabled('read_from_templates')) {
    // New path: try template first, fall back to AI
    $metadata = msh_generate_from_template($context)
                ?? msh_generate_from_ai($context);
} else {
    // Old path: AI only
    $metadata = msh_generate_from_ai($context);
}

// Dual-write with mismatch tracking
if (MSH_Feature_Flags::is_enabled('dual_write_templates')) {
    $this->write_to_new_table($metadata);
    $this->validate_parity($metadata); // Log mismatches
}
```

---

#### Step 4: CONTRACT 🗑️
**What:** Stop writing to old fields, remove legacy code

**Actions:**
- Disable feature flags (make new path permanent)
- Remove old write code
- Drop old columns/tables (in separate release, much later)
- Clean up migration infrastructure

**Duration:** Days to weeks (conservative timing)

**Example:**
```sql
-- Wait 2-4 weeks after switch is stable, then:
ALTER TABLE wp_msh_optimizer_context
DROP COLUMN old_ai_only_field;

-- Or drop entire legacy table if replaced
DROP TABLE IF EXISTS wp_msh_i18n_metadata; -- OLD TABLE
```

---

## 1.2 Why We Need This

### Problem 1: Past Migration Pain
**What Happened:** AI #2 had to fix metadata row actions because of hard cutover from `msh_i18n_metadata` → `optimizer_metadata_versions`

**Quote from AI-TASK-FIX-BUGS-OCT22.md:**
> AI #2's Fix: Rewired from legacy `msh_i18n_metadata` table to `MSH_Metadata_Versioning`

**How This Pattern Prevents It:**
1. **Expand:** Add `optimizer_metadata_versions` (✅ done)
2. **Backfill:** Migrate `msh_i18n_metadata` → `metadata_versions` gradually
3. **Switch:** Dual-write to both tables with feature flag
4. **Contract:** Drop `msh_i18n_metadata` after validation

Result: No broken row actions, easy rollback if issues found.

---

### Problem 2: Upcoming Phase 6 Complexity
**Challenge:** Migrating from "AI-only generation" to "Template-first with AI fallback"

**Without This Pattern:**
- Hard cutover breaks AI regeneration
- No rollback path if templates fail
- Risk of downtime during migration
- Data loss if migration script fails

**With This Pattern:**
- Gradual migration with validation
- Feature flag rollback in seconds
- Zero downtime guarantee
- Safe data migration in batches

---

### Problem 3: Phase 8 Scale
**Challenge:** Adding `wp_msh_optimizer_metrics` with 28-day rolling data per attachment/locale/field

**Data Volume Estimate:**
- 10,000 attachments × 4 locales × 4 fields × 28 days = **4.5 million rows**

**Without This Pattern:**
- Single migration script runs for hours
- Server overload risk
- No resume capability if script crashes
- Users experience slow admin panel

**With This Pattern:**
- Batch processing (500-1000 rows at a time)
- Resumable from last checkpoint
- Throttled (1-second sleep between batches)
- Zero impact on live site

---

### Problem 4: Production Site Stability
**Target Audience:**
- Agencies managing client sites
- High-traffic e-commerce sites
- Multilingual content sites

**Downtime Impact:**
- Lost SEO rankings (images not optimized)
- Broken media library
- Angry clients
- Support ticket flood

**This Pattern = Zero Downtime Guarantee**

---

## 1.3 Implementation Strategy

### Feature Flag System

**File:** `includes/class-msh-feature-flags.php`

```php
<?php
/**
 * Feature flag management for safe rollouts and migrations.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

class MSH_Feature_Flags {

    const OPTION_KEY = 'msh_feature_flags';

    /**
     * Check if a feature flag is enabled.
     *
     * @param string $flag Flag name (e.g., 'read_from_templates')
     * @return bool
     */
    public static function is_enabled($flag) {
        $flags = get_option(self::OPTION_KEY, []);
        return isset($flags[$flag]) && $flags[$flag];
    }

    /**
     * Set a feature flag value.
     *
     * @param string $flag Flag name
     * @param bool $value Enable (true) or disable (false)
     * @return bool
     */
    public static function set($flag, $value) {
        $flags = get_option(self::OPTION_KEY, []);
        $flags[$flag] = (bool) $value;
        return update_option(self::OPTION_KEY, $flags);
    }

    /**
     * Get all feature flags.
     *
     * @return array
     */
    public static function get_all() {
        return get_option(self::OPTION_KEY, []);
    }

    /**
     * Remove a feature flag.
     *
     * @param string $flag Flag name
     * @return bool
     */
    public static function remove($flag) {
        $flags = get_option(self::OPTION_KEY, []);
        unset($flags[$flag]);
        return update_option(self::OPTION_KEY, $flags);
    }
}
```

**WP-CLI Usage:**
```bash
# Enable template reads
wp option patch insert msh_feature_flags read_from_templates 1

# Disable template reads (instant rollback)
wp option patch delete msh_feature_flags read_from_templates

# Check all flags
wp option get msh_feature_flags --format=json
```

---

### WP-CLI Migration Commands

**File:** `includes/class-msh-jobs-cli.php` (extend existing)

```php
/**
 * Migrate data between schema versions.
 *
 * ## OPTIONS
 *
 * <type>
 * : Migration type (templates, metrics, etc.)
 *
 * [--batch-size=<size>]
 * : Number of items per batch
 * ---
 * default: 500
 * ---
 *
 * [--from-id=<id>]
 * : Start from this ID
 * ---
 * default: 0
 * ---
 *
 * [--to-id=<id>]
 * : End at this ID
 * ---
 * default: 0 (no limit)
 * ---
 *
 * [--sleep=<seconds>]
 * : Sleep between batches to reduce load
 * ---
 * default: 1
 * ---
 *
 * [--dry-run]
 * : Show what would be migrated without making changes
 *
 * ## EXAMPLES
 *
 *     # Migrate templates in batches of 500
 *     wp msh migrate templates --batch-size=500
 *
 *     # Resume from ID 10000
 *     wp msh migrate templates --from-id=10000
 *
 *     # Dry run to preview migration
 *     wp msh migrate templates --dry-run
 *
 * @when after_wp_load
 */
public function migrate($args, $assoc_args) {
    $type = $args[0];
    $batch_size = (int) ($assoc_args['batch-size'] ?? 500);
    $from_id = (int) ($assoc_args['from-id'] ?? 0);
    $to_id = (int) ($assoc_args['to-id'] ?? PHP_INT_MAX);
    $sleep = (int) ($assoc_args['sleep'] ?? 1);
    $dry_run = isset($assoc_args['dry-run']);

    WP_CLI::line(sprintf(
        'Starting %s migration from ID %d (batch size: %d, sleep: %ds)%s',
        $type,
        $from_id,
        $batch_size,
        $sleep,
        $dry_run ? ' [DRY RUN]' : ''
    ));

    $migrator = $this->get_migrator($type);
    if (!$migrator) {
        WP_CLI::error("Unknown migration type: $type");
    }

    $migrated = 0;
    $current_id = $from_id;
    $progress = \WP_CLI\Utils\make_progress_bar('Migrating', $to_id - $from_id);

    while ($current_id < $to_id) {
        $batch = $migrator->get_batch($current_id, $batch_size);

        if (empty($batch)) {
            break;
        }

        foreach ($batch as $item) {
            if (!$dry_run) {
                $migrator->migrate_item($item);
            }

            $migrated++;
            $current_id = max($current_id, $item->id ?? $item['id']);
            $progress->tick();
        }

        // Throttle to avoid load spikes
        if (!$dry_run && $sleep > 0) {
            sleep($sleep);
        }
    }

    $progress->finish();

    WP_CLI::success(sprintf(
        'Migration complete: %d items %s (last ID: %d)',
        $migrated,
        $dry_run ? 'analyzed' : 'migrated',
        $current_id
    ));
}

/**
 * Get migrator instance for type.
 *
 * @param string $type Migration type
 * @return object|null Migrator instance
 */
private function get_migrator($type) {
    switch ($type) {
        case 'templates':
            return new MSH_Template_Migrator();
        case 'metrics':
            return new MSH_Metrics_Migrator();
        default:
            return null;
    }
}
```

---

### Dual-Write Validation

**File:** `includes/class-msh-migration-validator.php`

```php
<?php
/**
 * Validates data parity during dual-write migrations.
 *
 * @package MSH_Image_Optimizer
 * @since 2.1.0
 */

class MSH_Migration_Validator {

    /**
     * Compare old and new data during dual-write phase.
     *
     * @param mixed $old_value Value from old schema
     * @param mixed $new_value Value from new schema
     * @param array $context Context for logging (media_id, locale, etc.)
     * @return bool True if values match
     */
    public static function validate_parity($old_value, $new_value, $context = []) {
        $matches = self::deep_compare($old_value, $new_value);

        if (!$matches) {
            self::log_mismatch($old_value, $new_value, $context);
        }

        return $matches;
    }

    /**
     * Deep comparison of values (handles arrays, objects, strings).
     *
     * @param mixed $old Old value
     * @param mixed $new New value
     * @return bool
     */
    private static function deep_compare($old, $new) {
        // Normalize types
        if (is_array($old)) {
            $old = json_encode($old, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($new)) {
            $new = json_encode($new, JSON_UNESCAPED_UNICODE);
        }

        return $old === $new;
    }

    /**
     * Log mismatch to telemetry for investigation.
     *
     * @param mixed $old Old value
     * @param mixed $new New value
     * @param array $context Context data
     */
    private static function log_mismatch($old, $new, $context) {
        if (function_exists('msh_telemetry')) {
            msh_telemetry('migration_mismatch', [
                'old_value' => self::truncate($old),
                'new_value' => self::truncate($new),
                'context' => $context,
                'timestamp' => current_time('mysql'),
            ]);
        }

        // Also log to debug.log if WP_DEBUG is on
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'MSH Migration Mismatch: %s | Old: %s | New: %s',
                json_encode($context),
                self::truncate($old),
                self::truncate($new)
            ));
        }
    }

    /**
     * Truncate long values for logging.
     *
     * @param mixed $value Value to truncate
     * @return string
     */
    private static function truncate($value) {
        $str = is_string($value) ? $value : json_encode($value);
        return strlen($str) > 200 ? substr($str, 0, 200) . '...' : $str;
    }
}
```

---

### Rollback Procedures

**File:** `docs/ROLLBACK-PROCEDURES.md`

```markdown
# Rollback Procedures for Schema Migrations

## Quick Rollback (Feature Flag)

### Template Migration Rollback
```bash
# Disable template reads (instant rollback to AI-only)
wp option patch delete msh_feature_flags read_from_templates

# Verify old path works
wp msh test metadata-generation --method=ai

# Monitor for errors (5 minutes)
tail -f /path/to/wp-content/debug.log

# Check telemetry for issues
wp db query "SELECT * FROM wp_msh_telemetry WHERE event='migration_mismatch' ORDER BY created_at DESC LIMIT 20"
```

### Metrics Migration Rollback
```bash
# Disable metrics collection
wp option patch delete msh_feature_flags collect_metrics

# Verify site stability
wp msh jobs status
```

## Full Rollback (Code Revert)

If feature flag rollback isn't sufficient:

1. **Revert plugin code to previous version:**
   ```bash
   cd wp-content/plugins/msh-image-optimizer
   git revert <commit-hash>
   ```

2. **Clear object cache:**
   ```bash
   wp cache flush
   ```

3. **Restart queue processing:**
   ```bash
   wp msh jobs clear --status=processing
   wp msh jobs process --batch-size=10
   ```

## Database Rollback (Last Resort)

**WARNING:** Only use if data is corrupted.

```bash
# Restore from backup
wp db import backup-before-migration.sql

# Verify data integrity
wp db query "SELECT COUNT(*) FROM wp_optimizer_metadata_cache"
```
```

---

## 1.4 Phase 6 Application: Template Intelligence Migration

### Current State
- **Metadata Generation:** AI-only (every metadata field regenerated by Claude API)
- **Cost:** High (API calls for every field)
- **Latency:** 30-60 seconds per image (4-8 jobs)

### Target State
- **Metadata Generation:** Template-first with AI fallback
- **Cost:** Low (templates are free, AI only for edge cases)
- **Latency:** <1 second for template matches, 30-60s for AI fallback

---

### Migration Plan

#### Step 1: EXPAND (Week 1 of Phase 6)

**SQL:**
```sql
-- Create templates table
CREATE TABLE wp_msh_optimizer_templates (
    template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    locale VARCHAR(10) NOT NULL,
    usage_type VARCHAR(50) NOT NULL,
    intent_pattern VARCHAR(255) NULL,
    subject_pattern VARCHAR(255) NULL,

    -- Template fields
    title_template TEXT NULL,
    alt_template TEXT NULL,
    caption_template TEXT NULL,
    description_template TEXT NULL,

    -- Metadata
    tokens_required LONGTEXT NULL COMMENT 'JSON array of required tokens',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 50 COMMENT '1-100, higher = higher priority',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    success_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (template_id),
    KEY idx_locale_usage (locale, usage_type),
    KEY idx_priority (priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add tracking columns to context table
ALTER TABLE wp_msh_optimizer_context
ADD COLUMN template_id BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Template used for generation',
ADD COLUMN generation_method ENUM('ai', 'template', 'manual', 'wordpress') NULL DEFAULT NULL COMMENT 'How metadata was generated',
ADD KEY idx_template (template_id);
```

**Deploy:** Plugin version 2.1.0 with new tables (no code changes yet)

---

#### Step 2: BACKFILL (Week 1-2 of Phase 6)

**Goal:** Seed templates from existing successful AI generations

**WP-CLI Command:**
```bash
# Analyze existing metadata to create templates
wp msh analyze templates \
  --min-usage=10 \
  --min-success-rate=80 \
  --locales=en,es,fr,de

# Expected output:
# Analyzing 10,000 metadata entries...
# Found 45 template patterns (80%+ success rate)
# Created 45 templates across 4 locales
```

**No user impact** - this is analysis only, doesn't change behavior yet.

---

#### Step 3: SWITCH (Week 2-3 of Phase 6)

**Goal:** Enable template-first generation with validation

**Feature Flags:**
```bash
# Enable template reads (dual-write)
wp option patch insert msh_feature_flags read_from_templates 1
wp option patch insert msh_feature_flags validate_template_parity 1
```

**Code Change:**
```php
// In MSH_Regeneration_Worker
public function generate_metadata($media_id, $locale, $field) {
    $context = msh_get_context_for_attachment($media_id, $locale);

    if (MSH_Feature_Flags::is_enabled('read_from_templates')) {
        // NEW PATH: Try template first
        $metadata = $this->try_template_generation($context, $field);

        if ($metadata) {
            // Log template success
            msh_telemetry('template_generation_success', [
                'media_id' => $media_id,
                'locale' => $locale,
                'field' => $field,
                'template_id' => $metadata['template_id'],
            ]);
            return $metadata;
        }

        // Fall back to AI
        $metadata = $this->ai_generation($context, $field);

        // Log AI fallback (for template improvement)
        msh_telemetry('template_generation_fallback', [
            'media_id' => $media_id,
            'locale' => $locale,
            'field' => $field,
            'reason' => 'no_matching_template',
        ]);

        return $metadata;
    }

    // OLD PATH: AI-only
    return $this->ai_generation($context, $field);
}
```

**Run for 1-2 weeks** with monitoring:
```bash
# Check template usage stats
wp db query "
SELECT generation_method, COUNT(*) as count
FROM wp_optimizer_metadata_cache
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY generation_method
"

# Expected output after 1 week:
# generation_method | count
# -----------------+-------
# template         | 6,500
# ai               | 1,200
# manual           |   300
```

**Success Criteria:**
- ✅ 75%+ of generations use templates (reduced AI cost)
- ✅ No increase in regeneration failures
- ✅ User satisfaction maintained (check support tickets)

---

#### Step 4: CONTRACT (Week 4+ of Phase 6)

**Goal:** Make template-first permanent, remove old code

**Actions:**
```bash
# Remove feature flags (make new path permanent)
wp option patch delete msh_feature_flags read_from_templates
wp option patch delete msh_feature_flags validate_template_parity
```

**Code Cleanup:**
```php
// Remove old AI-only code path
// OLD:
// if (MSH_Feature_Flags::is_enabled('read_from_templates')) { ... }
// NEW:
$metadata = $this->try_template_generation($context, $field)
            ?? $this->ai_generation($context, $field);
```

**Wait 2-4 weeks, then remove migration infrastructure if desired.**

---

## 1.5 Phase 8 Application: Metrics Table Migration

### Challenge
Adding `wp_msh_optimizer_metrics` to track 28-day performance per attachment/locale/field.

**Estimated Data Volume:**
- 10,000 attachments × 4 locales × 4 fields × 28 days = **4.5 million rows**

### Migration Strategy

#### Step 1: EXPAND
```sql
CREATE TABLE wp_msh_optimizer_metrics (
    metric_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(10) NOT NULL,
    field_name VARCHAR(50) NOT NULL,

    -- Metrics
    impressions INT UNSIGNED NOT NULL DEFAULT 0,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    ctr DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    avg_position DECIMAL(5,2) NOT NULL DEFAULT 0.00,

    -- Rolling window
    metric_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (metric_id),
    UNIQUE KEY idx_unique_metric (media_id, locale, field_name, metric_date),
    KEY idx_date (metric_date),
    KEY idx_media_locale (media_id, locale)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Step 2: BACKFILL
```bash
# Batch process (resumable)
wp msh migrate metrics \
  --batch-size=1000 \
  --from-id=1 \
  --sleep=2 \
  --source=google_search_console

# Progress output:
# Batch 1: 1,000 metrics collected (IDs 1-5432)
# Batch 2: 1,000 metrics collected (IDs 5433-11209)
# ...
# Total: 4,500,000 metrics collected in 4.5 hours
```

**Throttling:** 2-second sleep prevents server overload

#### Step 3: SWITCH
```php
if (MSH_Feature_Flags::is_enabled('collect_metrics')) {
    $this->collect_and_store_metrics($media_id, $locale);
}
```

**Run for 28 days** to establish full baseline.

#### Step 4: CONTRACT
Make metrics collection permanent, remove feature flag.

---

## 1.6 Additional Migration Ideas

### Database Indexing Strategy

**Problem:** As data grows, queries slow down

**Solution:** Progressive index optimization

```sql
-- Composite indexes for common queries
ALTER TABLE wp_optimizer_metadata_cache
ADD KEY idx_locale_source (locale, source_type);

ALTER TABLE wp_msh_optimizer_context
ADD KEY idx_media_locale_intent (media_id, locale, intent);

-- Partial indexes (MySQL 8.0+)
CREATE INDEX idx_pending_jobs
ON wp_msh_jobs (status, priority, created_at)
WHERE status = 'pending';
```

**WP-CLI Monitoring:**
```bash
# Slow query analysis
wp msh analyze queries --slow --top=20

# Index usage statistics
wp db query "SHOW INDEX FROM wp_optimizer_metadata_cache"
```

---

### Cache Warming Strategy

**Problem:** First request after cache clear is slow

**Solution:** Proactive cache warming after migrations

```php
/**
 * Warm metadata cache after migration.
 */
public function warm_cache($media_ids, $locales) {
    WP_CLI::line('Warming metadata cache...');

    $progress = \WP_CLI\Utils\make_progress_bar(
        'Warming',
        count($media_ids) * count($locales)
    );

    foreach ($media_ids as $media_id) {
        foreach ($locales as $locale) {
            // Pre-load cache
            msh_get_metadata($media_id, $locale);
            $progress->tick();
        }
    }

    $progress->finish();
}
```

**Usage:**
```bash
wp msh cache warm \
  --media-ids=1,2,3,4,5 \
  --locales=en,es,fr,de
```

---

### Dead Letter Queue (Failed Jobs Archive)

**Problem:** Failed jobs clutter queue table

**Solution:** Archive failed jobs for investigation

```sql
CREATE TABLE wp_msh_dead_letters (
    dead_letter_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_job_id BIGINT UNSIGNED NOT NULL,
    job_type VARCHAR(50) NOT NULL,
    payload LONGTEXT NULL,
    error_message TEXT NULL,
    failure_count INT UNSIGNED NOT NULL DEFAULT 1,
    first_failed_at DATETIME NOT NULL,
    last_failed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (dead_letter_id),
    KEY idx_original_job (original_job_id),
    KEY idx_job_type (job_type)
) ENGINE=InnoDB;
```

**WP-CLI:**
```bash
# Archive failed jobs (>5 failures)
wp msh jobs archive \
  --min-failures=5 \
  --older-than=7d

# Analyze failure patterns
wp msh jobs analyze-failures \
  --group-by=job_type \
  --top=10
```

---

### Blue-Green Table Swaps

**Problem:** Some migrations need instant cutover

**Solution:** Build new table in background, swap at end

```sql
-- Build new table (renamed)
CREATE TABLE wp_optimizer_metadata_cache_v2 LIKE wp_optimizer_metadata_cache;

-- Backfill in background (days/weeks)
-- ... batch migration ...

-- Instant swap (milliseconds)
RENAME TABLE
    wp_optimizer_metadata_cache TO wp_optimizer_metadata_cache_old,
    wp_optimizer_metadata_cache_v2 TO wp_optimizer_metadata_cache;

-- Keep old table for 1-2 weeks as rollback safety
-- DROP TABLE wp_optimizer_metadata_cache_old; -- Later
```

**Use Case:** Phase 7 (Multilingual Admin UX) might need instant cutover

---

### Schema Version Tracking

**Problem:** Hard to know which migrations have run

**Solution:** Track schema versions in options table

```php
class MSH_Schema_Versions {
    const OPTION_KEY = 'msh_schema_version';

    public static function get_current() {
        return (int) get_option(self::OPTION_KEY, 0);
    }

    public static function set($version) {
        return update_option(self::OPTION_KEY, (int) $version);
    }

    public static function needs_migration($target_version) {
        return self::get_current() < $target_version;
    }
}
```

**Usage:**
```php
// On plugin activation
if (MSH_Schema_Versions::needs_migration(6)) {
    MSH_Phase6_Migrator::run();
    MSH_Schema_Versions::set(6);
}
```

---

### Observability & Telemetry

**Problem:** Hard to debug migrations in production

**Solution:** Enhanced telemetry during migrations

```php
// Track migration progress
msh_telemetry('migration_started', [
    'type' => 'templates',
    'batch_size' => 500,
    'estimated_items' => 10000,
]);

// Track batch progress
msh_telemetry('migration_batch_complete', [
    'type' => 'templates',
    'batch_number' => 5,
    'items_migrated' => 500,
    'current_id' => 2500,
    'duration_ms' => 1250,
]);

// Track completion
msh_telemetry('migration_complete', [
    'type' => 'templates',
    'total_items' => 10000,
    'total_duration_seconds' => 125,
    'errors' => 0,
]);
```

**Dashboard Visualization:** (Phase 8 - Analytics Dashboard)

---

## 1.7 Action Plan & Timeline

### Infrastructure Setup Phase (Week 1-2 of Track B/C completion)
**Goal:** Build reusable migration infrastructure

**Tasks:**
1. ✅ Create `includes/class-msh-feature-flags.php`
2. ✅ Extend `includes/class-msh-jobs-cli.php` with `migrate` command
3. ✅ Create `includes/class-msh-migration-validator.php`
4. ✅ Document rollback procedures in `docs/ROLLBACK-PROCEDURES.md`
5. ✅ Add schema version tracking
6. ✅ Enhance telemetry for migration events

**Deliverable:** Reusable migration toolkit ready for Phase 6

---

### Practice Run Phase (Week 3 of Track B/C completion)
**Goal:** Test infrastructure with small schema change

**Tasks:**
1. Choose small migration (e.g., add `last_accessed` column to context table)
2. Run full EXPAND → BACKFILL → SWITCH → CONTRACT cycle
3. Document lessons learned
4. Refine procedures

**Deliverable:** Proven migration process, confidence in tooling

---

### Phase 6 Migration Phase (Week 1-4 of Phase 6)
**Goal:** Production migration to template intelligence

**Timeline:**
- **Week 1:** EXPAND (create tables, deploy)
- **Week 1-2:** BACKFILL (seed templates from AI data)
- **Week 2-3:** SWITCH (enable template-first with validation)
- **Week 4+:** CONTRACT (make permanent, remove old code)

**Deliverable:** Template intelligence live, 75%+ cost reduction

---

### Phase 8 Migration Phase (Week 1-4 of Phase 8)
**Goal:** Add metrics collection with zero downtime

**Timeline:**
- **Week 1:** EXPAND (create metrics table)
- **Week 1-2:** BACKFILL (collect historical data from GSC/GA4)
- **Week 2-4:** SWITCH (enable live metrics collection)
- **Week 4+:** CONTRACT (make permanent)

**Deliverable:** 28-day rolling metrics for all attachments

---

## Success Metrics

### Infrastructure Success
- ✅ Zero unplanned downtime during migrations
- ✅ <1 minute to rollback if issues found
- ✅ 100% data parity during validation period
- ✅ <1% performance impact during backfill

### Phase 6 Success
- ✅ 75%+ generations use templates (cost reduction)
- ✅ <5% increase in regeneration time
- ✅ Zero data loss during migration
- ✅ User satisfaction maintained

### Phase 8 Success
- ✅ 4.5M+ metrics collected without downtime
- ✅ <2 second query time for metrics dashboard
- ✅ 28-day rolling window maintained
- ✅ Zero impact on site performance

---

## References

### External Resources
- [Expand and contract pattern](https://openpracticelibrary.com/practice/expand-and-contract-pattern/)
- [Zero-downtime database migrations](https://benchling.engineering/move-fast-and-migrate-things-how-we-automated-migrations-in-postgres-d60aba0fc3d4)
- [WordPress database versioning best practices](https://developer.wordpress.org/plugins/plugin-basics/activation-deactivation-hooks/#database-updates)

### Internal Documentation
- [PROJECT-STATUS-ALL-PHASES.md](../PROJECT-STATUS-ALL-PHASES.md) - Overall project status
- [TODO.md](../TODO.md) - Phase roadmap (updated with Idea #1)
- [AUTOMATION-INFRASTRUCTURE-COMPLETE.md](../AUTOMATION-INFRASTRUCTURE-COMPLETE.md) - Phase 5+9 Track A
- [AI-TASK-FIX-BUGS-OCT22.md](../AI-TASK-FIX-BUGS-OCT22.md) - Past migration pain example

---

### Conclusion (Idea #1)

The expand-backfill-switch-contract pattern is **critical infrastructure** for MSH Image Optimizer's evolution through Phases 6-10. By investing 2-4 hours in reusable migration tooling now, we prevent days of debugging, downtime, and data loss later.

**Next Steps:**
1. Review and approve this idea
2. Schedule infrastructure build (Week 1-2 of Track B/C completion)
3. Run practice migration (Week 3)
4. Apply to Phase 6 Template Intelligence (Weeks 1-4 of Phase 6)

**Status:** 📋 Ready for Implementation
**Priority:** 🔴 High (blocks Phase 6 safety)
**Owner:** Backend AI (infrastructure) + Frontend AI (validation testing)

---
---

## Idea #2: Feature Flags for Safe Rollouts

**Source:** User-provided article on feature flag best practices
**Priority:** 🔴 High (Complements Idea #1 - migrations need flags!)
**Related Phases:** Phase 5+9 (Track C - Enterprise), Phase 6+ (all risky features)
**Status:** ⚠️ Partially Implemented - Needs expansion

### Quick Summary

Feature flags are on/off switches in code that let you deploy risky features safely, control rollout to specific users/roles/cohorts, and provide instant kill switches if issues arise. **We already started this in Idea #1** with `MSH_Feature_Flags` class - this idea expands it to a full rollout strategy.

**Benefits:**
- ✅ Targeted rollout (staging → internal → beta → all users)
- ✅ Instant kill switch if errors spike
- ✅ Safe testing in production with real traffic
- ✅ Role-based gating (Pro vs Free, Agency partners)
- ✅ Per-site or per-user overrides

**Connection to Idea #1:** The migration pattern *requires* feature flags. This idea expands them into a full product ops strategy.

---

### 2.1 Current Implementation Status

**Already Built (Idea #1):**
```php
// includes/class-msh-feature-flags.php
class MSH_Feature_Flags {
    public static function is_enabled($flag) { ... }
    public static function set($flag, $value) { ... }
    public static function get_all() { ... }
    public static function remove($flag) { ... }
}
```

**Current Storage:** WordPress options table (`msh_feature_flags`)

**Current Flags in Use:**
- `read_from_templates` - Template intelligence migration (Idea #1)
- `validate_template_parity` - Dual-write validation (Idea #1)
- `collect_metrics` - Metrics collection (Phase 8)
- `dual_write_templates` - Migration safety (Idea #1)

**What's Missing:**
- ❌ User/role-level overrides
- ❌ Admin UI to manage flags
- ❌ WP-CLI commands for flag management
- ❌ Telemetry on flag usage
- ❌ Remote config support
- ❌ Rollout cohorts (percentage-based)

---

### 2.2 Enhanced Implementation

#### Naming Convention

**Format:** `msh_feature_<category>_<name>`

**Examples:**
- `msh_feature_ai_safe_rename` - AI-powered filename sanitization
- `msh_feature_avif_conversion` - AVIF format support (Idea #3)
- `msh_feature_pro_dashboard_v2` - New dashboard UI (Track B)
- `msh_feature_locale_rollup` - Performance optimization
- `msh_feature_batch_optimize` - Bulk optimization queue

---

#### Multi-Level Flag Evaluation

**Priority Order:** User meta → Role capability → Global option → Filter hook → Default

```php
/**
 * Enhanced feature flag evaluation with multi-level overrides.
 *
 * @param string $flag Flag name (without msh_feature_ prefix)
 * @param int $user_id User ID for user-specific checks (0 = current user)
 * @return bool
 */
function msh_flag_enabled( string $flag, int $user_id = 0 ): bool {
    // Normalize user ID
    if ( $user_id === 0 ) {
        $user_id = get_current_user_id();
    }

    // 1) User-level override (highest priority)
    if ( $user_id ) {
        $user_override = get_user_meta( $user_id, "msh_feature_{$flag}", true );
        if ( $user_override !== '' ) {
            return filter_var( $user_override, FILTER_VALIDATE_BOOLEAN );
        }

        // 2) Role-based capability check
        $user = get_user_by( 'id', $user_id );
        if ( $user && $user->has_cap( "msh_feature_{$flag}" ) ) {
            return true;
        }
    }

    // 3) Global site option
    $global = get_option( "msh_feature_{$flag}", false );

    // 4) Filter for remote config or environment overrides
    $final = apply_filters( 'msh_feature_flag', $global, $flag, $user_id );

    // 5) Telemetry sampling (log every 100th evaluation)
    if ( function_exists('msh_telemetry') && rand(1, 100) === 1 ) {
        msh_telemetry( 'feature_flag_evaluation', [
            'flag' => $flag,
            'enabled' => (bool) $final,
            'user_id' => $user_id,
            'source' => $user_override !== '' ? 'user_meta' : 'global_option',
        ]);
    }

    return (bool) $final;
}
```

---

#### Guarding Risky Code

**Pattern:**
```php
// Example: AI Safe Rename (Phase 6+)
if ( msh_flag_enabled( 'ai_safe_rename', get_current_user_id() ) ) {
    // NEW PATH: AI-powered filename sanitization
    $safe_name = msh_ai_generate_safe_filename( $attachment_id );
} else {
    // LEGACY PATH: WordPress default sanitization
    $safe_name = sanitize_file_name( $original_name );
}

// Example: AVIF Conversion (Idea #3)
if ( msh_flag_enabled( 'avif_conversion' ) ) {
    $formats = ['avif', 'webp', 'jpg']; // New
} else {
    $formats = ['webp', 'jpg']; // Legacy
}

// Example: Pro Dashboard V2 (Track B)
if ( msh_flag_enabled( 'pro_dashboard_v2' ) && current_user_can( 'manage_options' ) ) {
    require_once MSH_PLUGIN_DIR . 'admin/views/dashboard-v2.php';
} else {
    require_once MSH_PLUGIN_DIR . 'admin/views/dashboard-v1.php';
}
```

---

### 2.3 Admin UI for Flag Management

**Location:** Settings → MSH Optimizer → Feature Flags (hidden tab, admin-only)

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────────┐
│ Feature Flags (Experimental Features)                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ ⚠️  Warning: These are experimental features. Enable only   │
│    if you understand the risks.                             │
│                                                              │
│ ┌──────────────────────────────────────────────────────┐   │
│ │ Feature Name          Status    Description          │   │
│ ├──────────────────────────────────────────────────────┤   │
│ │ AI Safe Rename        [OFF] ▼   AI-powered filename  │   │
│ │                               sanitization (Beta)     │   │
│ ├──────────────────────────────────────────────────────┤   │
│ │ AVIF Conversion       [OFF] ▼   Convert images to    │   │
│ │                               AVIF format             │   │
│ ├──────────────────────────────────────────────────────┤   │
│ │ Pro Dashboard V2      [ON]  ▼   New dashboard UI     │   │
│ │                               (Stable)                │   │
│ ├──────────────────────────────────────────────────────┤   │
│ │ Template Intelligence [ON]  ▼   Use templates before │   │
│ │                               AI (Phase 6)            │   │
│ └──────────────────────────────────────────────────────┘   │
│                                                              │
│ Rollout Options:                                             │
│ ○ Everyone  ○ Admins Only  ○ Specific Users/Roles          │
│                                                              │
│ [Save Changes]                                               │
└─────────────────────────────────────────────────────────────┘
```

**Implementation:**
```php
// admin/class-msh-feature-flags-admin.php
class MSH_Feature_Flags_Admin {

    public function render_settings_page() {
        $flags = $this->get_available_flags();
        ?>
        <div class="wrap msh-feature-flags">
            <h1>Feature Flags</h1>

            <div class="notice notice-warning">
                <p>⚠️ These are experimental features. Enable only if you understand the risks.</p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'msh_feature_flags' ); ?>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Feature Name</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Rollout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $flags as $flag_key => $flag_config ): ?>
                        <tr>
                            <td><strong><?php echo esc_html( $flag_config['name'] ); ?></strong></td>
                            <td>
                                <select name="msh_feature_<?php echo esc_attr($flag_key); ?>">
                                    <option value="0">OFF</option>
                                    <option value="1" <?php selected( msh_flag_enabled($flag_key), true ); ?>>ON</option>
                                </select>
                            </td>
                            <td><?php echo esc_html( $flag_config['description'] ); ?></td>
                            <td>
                                <select name="msh_feature_<?php echo esc_attr($flag_key); ?>_rollout">
                                    <option value="everyone">Everyone</option>
                                    <option value="admins">Admins Only</option>
                                    <option value="custom">Custom Users/Roles</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_available_flags() {
        return [
            'ai_safe_rename' => [
                'name' => 'AI Safe Rename',
                'description' => 'AI-powered filename sanitization (Beta)',
                'phase' => 'Phase 6+',
                'risk' => 'medium',
            ],
            'avif_conversion' => [
                'name' => 'AVIF Conversion',
                'description' => 'Convert images to AVIF format for better compression',
                'phase' => 'Future',
                'risk' => 'high',
            ],
            'pro_dashboard_v2' => [
                'name' => 'Pro Dashboard V2',
                'description' => 'New dashboard UI with enhanced analytics',
                'phase' => 'Track B',
                'risk' => 'low',
            ],
            'template_intelligence' => [
                'name' => 'Template Intelligence',
                'description' => 'Use templates before AI calls (Phase 6)',
                'phase' => 'Phase 6',
                'risk' => 'low',
            ],
        ];
    }
}
```

---

### 2.4 WP-CLI Flag Management

**Extend:** `includes/class-msh-jobs-cli.php`

```php
/**
 * Manage feature flags via WP-CLI.
 *
 * ## EXAMPLES
 *
 *     # List all feature flags
 *     wp msh flags list
 *
 *     # Enable a feature flag globally
 *     wp msh flag set ai_safe_rename on
 *
 *     # Disable a feature flag
 *     wp msh flag set avif_conversion off
 *
 *     # Enable for specific user
 *     wp msh flag set ai_safe_rename on --user=5
 *
 *     # Check if flag is enabled
 *     wp msh flag check template_intelligence
 *
 * @when after_wp_load
 */
class MSH_Flags_CLI {

    /**
     * List all feature flags and their status.
     *
     * @subcommand list
     */
    public function list_flags( $args, $assoc_args ) {
        $flags = MSH_Feature_Flags::get_all();

        if ( empty( $flags ) ) {
            WP_CLI::line( 'No feature flags configured.' );
            return;
        }

        $rows = [];
        foreach ( $flags as $flag => $enabled ) {
            $rows[] = [
                'flag' => $flag,
                'status' => $enabled ? WP_CLI::colorize('%G✓ ON%n') : WP_CLI::colorize('%R✗ OFF%n'),
                'value' => $enabled ? 'true' : 'false',
            ];
        }

        WP_CLI\Utils\format_items( 'table', $rows, ['flag', 'status', 'value'] );
    }

    /**
     * Set a feature flag value.
     *
     * ## OPTIONS
     *
     * <flag>
     * : Flag name (without msh_feature_ prefix)
     *
     * <value>
     * : on/off or true/false or 1/0
     *
     * [--user=<id>]
     * : Set for specific user (default: global site flag)
     *
     * @subcommand set
     */
    public function set_flag( $args, $assoc_args ) {
        $flag = $args[0];
        $value = in_array( strtolower( $args[1] ), ['on', 'true', '1', 'yes'] );
        $user_id = isset( $assoc_args['user'] ) ? absint( $assoc_args['user'] ) : 0;

        if ( $user_id ) {
            // User-level override
            update_user_meta( $user_id, "msh_feature_{$flag}", $value );
            WP_CLI::success( sprintf(
                'Feature flag "%s" set to %s for user #%d',
                $flag,
                $value ? 'ON' : 'OFF',
                $user_id
            ) );
        } else {
            // Global site flag
            MSH_Feature_Flags::set( $flag, $value );
            WP_CLI::success( sprintf(
                'Feature flag "%s" set to %s globally',
                $flag,
                $value ? 'ON' : 'OFF'
            ) );
        }

        // Log telemetry
        if ( function_exists('msh_telemetry') ) {
            msh_telemetry( 'feature_flag_changed', [
                'flag' => $flag,
                'value' => $value,
                'scope' => $user_id ? 'user' : 'global',
                'user_id' => $user_id,
                'changed_by' => get_current_user_id(),
            ]);
        }
    }

    /**
     * Check if a feature flag is enabled.
     *
     * ## OPTIONS
     *
     * <flag>
     * : Flag name (without msh_feature_ prefix)
     *
     * [--user=<id>]
     * : Check for specific user (default: current user)
     *
     * @subcommand check
     */
    public function check_flag( $args, $assoc_args ) {
        $flag = $args[0];
        $user_id = isset( $assoc_args['user'] ) ? absint( $assoc_args['user'] ) : get_current_user_id();

        $enabled = msh_flag_enabled( $flag, $user_id );

        if ( $enabled ) {
            WP_CLI::success( WP_CLI::colorize( "%GFlag '$flag' is ENABLED%n" ) );
        } else {
            WP_CLI::line( WP_CLI::colorize( "%RFlag '$flag' is DISABLED%n" ) );
        }

        return $enabled ? 0 : 1; // Exit code for scripting
    }
}

// Register with WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'msh flags', 'MSH_Flags_CLI' );
}
```

**Usage Examples:**
```bash
# List all flags
wp msh flags list

# Enable AI Safe Rename globally
wp msh flag set ai_safe_rename on

# Enable AVIF for specific user (beta testing)
wp msh flag set avif_conversion on --user=5

# Check if template intelligence is enabled
wp msh flag check template_intelligence

# Disable Pro Dashboard V2
wp msh flag set pro_dashboard_v2 off
```

---

### 2.5 Rollout Plan (Repeatable Process)

**5-Stage Rollout Strategy:**

```
Stage 1: Code Deployed (Flag OFF)
    ↓
Stage 2: Staging Only
    ↓
Stage 3: Internal Accounts (5-10 users)
    ↓
Stage 4: Beta Cohort (50-100 users)
    ↓
Stage 5: General Availability (All users)
```

**Example: Rolling Out "AI Safe Rename"**

```bash
# Stage 1: Deploy code with flag OFF (default)
# No action needed - flag defaults to false

# Stage 2: Enable on staging site
wp msh flag set ai_safe_rename on --url=staging.example.com

# Stage 3: Enable for internal team (user IDs 1, 2, 3)
wp msh flag set ai_safe_rename on --user=1
wp msh flag set ai_safe_rename on --user=2
wp msh flag set ai_safe_rename on --user=3

# Monitor for 2-3 days, check telemetry:
wp db query "
SELECT event, COUNT(*) as count
FROM wp_msh_telemetry
WHERE event LIKE '%ai_safe_rename%'
  AND created_at > DATE_SUB(NOW(), INTERVAL 3 DAY)
GROUP BY event
"

# Stage 4: Enable for beta cohort (add capability to role)
wp role add-cap editor msh_feature_ai_safe_rename
# Or: Enable for 10% of users via filter hook

# Monitor for 1-2 weeks, track:
# - Error rate
# - Job queue failures
# - Support ticket mentions
# - Filename collision rate

# Stage 5: Enable globally if metrics are good
wp msh flag set ai_safe_rename on

# Or instant rollback if issues:
wp msh flag set ai_safe_rename off
```

---

### 2.6 Metrics to Monitor

**Critical Metrics (Auto-alert if spike > 20%):**
- Error rate in flagged code paths
- Job queue failures (status='failed')
- Timeout rate (processing > 60s)
- Support tickets mentioning feature name
- Refund/cancellation rate (for paid features)

**Performance Metrics:**
- Latency (p50, p95, p99)
- Database query time
- Memory usage
- Frontend CLS/LCP (if UI feature)

**Usage Metrics:**
- Adoption rate (% of eligible users using feature)
- Feature engagement (daily/weekly active)
- Conversion impact (free → pro upgrades)

**Telemetry Queries:**
```bash
# Check error rate for flagged feature
wp db query "
SELECT COUNT(*) as errors
FROM wp_msh_telemetry
WHERE event = 'error'
  AND data LIKE '%ai_safe_rename%'
  AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
"

# Check feature usage
wp db query "
SELECT DATE(created_at) as date, COUNT(*) as evaluations
FROM wp_msh_telemetry
WHERE event = 'feature_flag_evaluation'
  AND data LIKE '%ai_safe_rename%'
GROUP BY DATE(created_at)
ORDER BY date DESC
LIMIT 7
"
```

---

### 2.7 Integration with Existing Systems

#### Works With Idea #1 (Migrations)

Feature flags are **required** for safe migrations:

```php
// Phase 6: Template Intelligence Migration
if ( msh_flag_enabled( 'read_from_templates' ) ) {
    // New path: Template-first
    $metadata = msh_try_template( $context ) ?? msh_ai_generate( $context );
} else {
    // Old path: AI-only
    $metadata = msh_ai_generate( $context );
}
```

#### Works With Track C (Enterprise)

License-based feature gating:

```php
// Check both license AND feature flag
if ( msh_has_license( 'pro' ) && msh_flag_enabled( 'pro_dashboard_v2' ) ) {
    // Pro dashboard with beta UI
} elseif ( msh_has_license( 'pro' ) ) {
    // Pro dashboard stable UI
} else {
    // Free tier dashboard
}
```

#### Works With Track A (Job Queue)

Flag risky job types:

```php
// Enqueue AVIF conversion only if flag enabled
if ( msh_flag_enabled( 'avif_conversion' ) ) {
    msh_enqueue_job( 'convert_to_avif', $media_id );
}
```

---

### 2.8 Where to Use This NOW

#### High-Priority Features to Flag

**Phase 6+ - AI Safe Rename:**
```php
// Flag: msh_feature_ai_safe_rename
// Risk: Medium (filename changes can break links)
// Rollout: Staging → 5 users → 50 users → All
```

**Idea #3 - AVIF Conversion:**
```php
// Flag: msh_feature_avif_conversion
// Risk: High (browser compatibility, server support)
// Rollout: Staging → Internal → Beta (50) → Slow rollout (10% → 50% → 100%)
```

**Track B - Pro Dashboard V2:**
```php
// Flag: msh_feature_pro_dashboard_v2
// Risk: Low (UI only, no data changes)
// Rollout: Internal → Pro users → All admins
```

**Phase 8 - Locale Rollup Table:**
```php
// Flag: msh_feature_locale_rollup
// Risk: Medium (performance impact on large sites)
// Rollout: Small sites (<1000 posts) → Medium → Large
```

---

### 2.9 Nice Extras

#### Remote Config Support

**Use Case:** Change flags without redeploying code

```php
// Filter hook for remote config
add_filter( 'msh_feature_flag', function( $enabled, $flag, $user_id ) {
    // Fetch from remote API (cached for 5 minutes)
    $remote_config = get_transient( 'msh_remote_feature_flags' );

    if ( $remote_config === false ) {
        $response = wp_remote_get( 'https://api.msh-optimizer.com/v1/feature-flags' );
        if ( ! is_wp_error( $response ) ) {
            $remote_config = json_decode( wp_remote_retrieve_body( $response ), true );
            set_transient( 'msh_remote_feature_flags', $remote_config, 5 * MINUTE_IN_SECONDS );
        }
    }

    if ( isset( $remote_config[ $flag ] ) ) {
        return (bool) $remote_config[ $flag ];
    }

    return $enabled;
}, 10, 3 );
```

#### Percentage-Based Rollouts

**Use Case:** Enable for 10% of users, then 50%, then 100%

```php
function msh_flag_enabled_with_percentage( $flag, $percentage = 100 ) {
    if ( $percentage >= 100 ) {
        return msh_flag_enabled( $flag );
    }

    $user_id = get_current_user_id();
    $hash = crc32( $flag . $user_id );
    $bucket = $hash % 100;

    return $bucket < $percentage && msh_flag_enabled( $flag );
}

// Usage: Enable for 25% of users
if ( msh_flag_enabled_with_percentage( 'avif_conversion', 25 ) ) {
    // AVIF conversion enabled for 25% cohort
}
```

#### Changelog Integration

**Pattern:** Mention flag name in changelog

```markdown
## v2.1.0 - 2025-11-15

### Added
- **AI Safe Rename (Beta)**: AI-powered filename sanitization
  - Feature flag: `msh_feature_ai_safe_rename`
  - Enable via Settings → Feature Flags or WP-CLI: `wp msh flag set ai_safe_rename on`
  - Beta testers wanted! Contact support to join.

### Fixed
- Fixed metadata row actions (Preview, Copy, Edit)
```

**Support Benefit:** Support can ask: "Can you try enabling the `ai_safe_rename` flag?"

---

### 2.10 Action Plan

#### Phase 1: Enhance Existing Implementation (1-2 days)
1. ✅ Move `MSH_Feature_Flags` from Idea #1 to standalone file
2. ✅ Add `msh_flag_enabled()` helper function with multi-level overrides
3. ✅ Add telemetry sampling on flag evaluation
4. ✅ Add user meta and role capability support

#### Phase 2: WP-CLI Commands (1 day)
1. ✅ Add `wp msh flags list` command
2. ✅ Add `wp msh flag set <name> on/off` command
3. ✅ Add `wp msh flag check <name>` command
4. ✅ Add `--user` parameter for user-specific flags

#### Phase 3: Admin UI (2-3 days)
1. ✅ Create Feature Flags settings page (hidden by default)
2. ✅ List available flags with descriptions
3. ✅ Add toggle controls (Global, Admins Only, Custom)
4. ✅ Add risk indicators (Low, Medium, High)

#### Phase 4: Rollout Playbook (1 day)
1. ✅ Document 5-stage rollout process
2. ✅ Create monitoring queries
3. ✅ Define alert thresholds
4. ✅ Create rollback checklist

**Total Effort:** 5-7 days (spread across Phase 5+9 Track B/C or Phase 6 prep)

---

### References

**External:**
- [Feature Flags Best Practices (LaunchDarkly)](https://launchdarkly.com/blog/feature-flag-best-practices/)
- [Trunk-Based Development with Feature Flags](https://trunkbaseddevelopment.com/feature-flags/)

**Internal:**
- [Idea #1: Expand-Backfill-Switch-Contract](#idea-1-expand-backfill-switch-contract-pattern) - Uses feature flags
- [TODO.md](../TODO.md) - Phase roadmap
- [Track C: Enterprise Features](../PROJECT-STATUS-ALL-PHASES.md) - License gating

---

### Conclusion (Idea #2)

Feature flags transform risky deployments into safe, controlled rollouts. Combined with Idea #1's migration infrastructure, they form a complete safety system for evolving a WordPress plugin without breaking production sites.

**Key Takeaway:** Build the infrastructure once (5-7 days), reuse forever. Every risky feature gets flagged, every rollout follows the same 5-stage process, every issue gets instant rollback.

**Next Steps:**
1. Enhance existing `MSH_Feature_Flags` class with multi-level overrides
2. Add WP-CLI commands for flag management
3. Create admin UI (optional, low priority)
4. Flag all risky features (AI Safe Rename, AVIF, etc.)

**Status:** 📋 Approved - Implement during Track B/C completion
**Priority:** 🔴 High (enables safe rollout of Idea #3 and Phase 6+)

---
---

## Idea #3: AVIF Image Conversion

**Source:** User mention - "thinking of adding AVIF conversion to the plugin"
**Priority:** 🔴 High (Competitive differentiator + enables multi-platform expansion)
**Related Phases:** Phase 10 Stage 1 (WordPress Hybrid), Stage 2-3 (Cloud + Multi-Platform)
**Status:** ✅ Approved - Staged rollout with partner → migrate strategy
**See Also:** [Idea #4: Staged Cloud Architecture](#idea-4-staged-cloud-architecture-30-60-90-day-roadmap)

### Quick Summary

AVIF (AV1 Image File Format) is a next-gen image format offering 30-50% better compression than WebP while maintaining quality. Adding AVIF support would position MSH Image Optimizer as a cutting-edge solution for performance-conscious agencies and e-commerce sites.

**✅ APPROVED STRATEGY:** Cloud-first approach (partner → migrate) solves server compatibility issues while enabling multi-platform expansion (Shopify, Webflow, etc.).

**Benefits:**
- ✅ 30-50% smaller files vs WebP (50-70% vs JPEG)
- ✅ Better quality at same file size
- ✅ HDR support (future-proof)
- ✅ Browser support growing (Chrome, Firefox, Edge, Safari 16+)
- ✅ **100% hosting compatibility** (cloud processing, no server requirements)
- ✅ **Enables multi-platform expansion** (centralized processing required for Shopify/Webflow)

**Implementation Strategy:**
- **Stage 1 (Days 1-30):** ImageKit partner integration (2-3 days dev time)
- **Stage 2 (Days 31-60):** Own cloud MVP (Supabase Edge Functions)
- **Stage 3 (Days 61-90+):** Multi-platform launch (Shopify, Webflow, API)

**Solved Problems:**
- ❌ ~~Server support required~~ → ✅ Cloud processing (100% compatibility)
- ❌ ~~CPU-intensive encoding~~ → ✅ Offloaded to cloud infrastructure
- ✅ Feature flags (Idea #2) for safe rollout
- ✅ Gate-based validation (500+ installs before Stage 2 investment)

---

### 3.1 Technical Feasibility

#### Server Requirements

**Option 1: Imagick with AVIF Support**
```php
// Check if AVIF encoding is available
function msh_has_avif_support() {
    if ( ! extension_loaded( 'imagick' ) ) {
        return false;
    }

    $imagick = new Imagick();
    $formats = $imagick->queryFormats( 'AVIF' );
    return ! empty( $formats );
}
```

**Option 2: CLI Tools (avifenc)**
```bash
# Install libavif on server
sudo apt-get install libavif-bin  # Ubuntu/Debian
brew install libavif              # macOS

# Convert with CLI
avifenc --min 0 --max 63 -a end-usage=q -a cq-level=18 input.jpg output.avif
```

**Option 3: Cloud Service (Cloudinary, ImageKit)**
```php
// Use external API for conversion
$response = wp_remote_post( 'https://api.imagekit.io/v1/convert', [
    'body' => [
        'format' => 'avif',
        'quality' => 80,
        'url' => $image_url,
    ],
]);
```

---

### 3.2 Implementation Strategy

#### Phase 1: Detection & Preparation
1. Check server AVIF support on activation
2. Add admin notice if AVIF unavailable
3. Document installation steps for hosting providers

#### Phase 2: Conversion Pipeline
1. Add 'avif' to supported formats list
2. Extend job queue to handle AVIF conversions
3. Generate AVIF alongside WebP/JPEG

#### Phase 3: Delivery & Fallback
1. Use `<picture>` tag with format stacking:
   ```html
   <picture>
       <source type="image/avif" srcset="image.avif">
       <source type="image/webp" srcset="image.webp">
       <img src="image.jpg" alt="...">
   </picture>
   ```
2. Automatic fallback for unsupported browsers

#### Phase 4: CDN Integration
1. Test compatibility with popular CDNs
2. Add CDN configuration guides
3. Handle AVIF serving via CDN rewrite rules

---

### 3.3 Feature Flag Integration (Idea #2)

**Flag Name:** `msh_feature_avif_conversion`

**Rollout Plan:**
```bash
# Stage 1: Code deployed (flag OFF)
# AVIF conversion code exists but inactive

# Stage 2: Enable on staging
wp msh flag set avif_conversion on --url=staging.example.com

# Stage 3: Check server support
wp msh check avif-support
# Output: ✓ AVIF encoding available (Imagick)

# Stage 4: Enable for internal accounts
wp msh flag set avif_conversion on --user=1

# Stage 5: Beta cohort (users with modern hosting)
wp msh flag set avif_conversion on --role=administrator

# Monitor:
# - Encoding time (target: <5s per image)
# - File size savings (target: 30-50% vs WebP)
# - Browser compatibility (check user agents)
# - CDN compatibility (test major providers)

# Stage 6: Gradual rollout (10% → 50% → 100%)
# Use percentage-based rollout from Idea #2

# Stage 7: General availability
wp msh flag set avif_conversion on
```

---

### 3.4 User Experience

#### Settings UI Mockup
```
┌─────────────────────────────────────────────────────────────┐
│ Image Formats                                                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Select which formats to generate:                           │
│                                                              │
│ ☑ JPEG (Original)                                           │
│ ☑ WebP (Recommended)                                        │
│ ☑ AVIF (Next-Gen) 🆕                                        │
│   └─ ⚠️ AVIF provides 30-50% better compression than WebP │
│       but requires server support and modern browsers.     │
│       [Check Server Support]                                │
│                                                              │
│ Format Priority: AVIF → WebP → JPEG                         │
│                                                              │
│ ☑ Generate all formats for maximum compatibility            │
│   (Recommended for best performance and fallback)           │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

#### Admin Notice (If AVIF Unavailable)
```
┌─────────────────────────────────────────────────────────────┐
│ ℹ️ AVIF Support Not Available                                │
├─────────────────────────────────────────────────────────────┤
│ Your server doesn't support AVIF encoding. To enable:       │
│                                                              │
│ Option 1: Install Imagick with AVIF support                 │
│   • Contact your hosting provider                           │
│   • Or upgrade to a host with AVIF support                  │
│                                                              │
│ Option 2: Use CLI tools (avifenc)                           │
│   • Requires shell access and libavif-bin                   │
│                                                              │
│ [Learn More] [Dismiss]                                      │
└─────────────────────────────────────────────────────────────┘
```

---

### 3.5 Performance Considerations

**Encoding Time:**
- AVIF encoding is **2-5x slower** than WebP
- Solution: Queue AVIF jobs with lower priority
- Background processing prevents UI blocking

**Storage:**
- AVIF files are **30-50% smaller** than WebP
- Trade-off: More CPU time for less storage/bandwidth
- ROI: High for high-traffic sites

**CPU Usage:**
- Monitor server load during batch conversions
- Throttle AVIF jobs if CPU > 80%
- Option: Offload to external service (Cloudinary)

---

### 3.6 Browser Compatibility Strategy

**Current Support (2025):**
- ✅ Chrome 85+ (Sep 2020)
- ✅ Firefox 93+ (Oct 2021)
- ✅ Edge 85+ (Sep 2020)
- ✅ Safari 16+ (Sep 2022) - **macOS Ventura+ and iOS 16+**
- ❌ IE 11 (discontinued)
- ❌ Safari 15 and older

**Fallback Strategy:**
```html
<picture>
    <source type="image/avif" srcset="optimized.avif">
    <source type="image/webp" srcset="optimized.webp">
    <img src="optimized.jpg" alt="Fallback for old browsers">
</picture>
```

**Result:**
- Modern browsers get AVIF (smallest)
- Older browsers get WebP (good compression)
- Ancient browsers get JPEG (universal support)

---

### 3.7 Risks & Mitigation

| Risk | Mitigation |
|------|------------|
| Server doesn't support AVIF | Detect on activation, show admin notice, provide hosting recommendations |
| Slow encoding times | Queue with low priority, batch process during off-peak hours |
| Increased server load | Monitor CPU, throttle jobs, offer external API option |
| CDN compatibility issues | Test major CDNs (Cloudflare, Fastly, AWS CloudFront), document setup |
| Browser compatibility | Always generate fallback formats (WebP, JPEG) |
| Storage costs increase | AVIF saves bandwidth (30-50%), storage cost minimal |

---

### 3.8 Competitive Analysis

**Plugins with AVIF Support:**
- ShortPixel: ✅ AVIF support (paid tiers)
- Imagify: ✅ AVIF support (paid tiers)
- EWWW Image Optimizer: ✅ AVIF support (premium)
- Smush Pro: ❌ No AVIF yet (as of 2025)

**Opportunity:** Be among the first free/affordable AVIF solutions

---

### 3.9 Action Plan

#### Research Phase (1-2 weeks)
1. Test AVIF encoding on various hosting environments
2. Benchmark encoding times vs WebP
3. Measure file size savings on real images
4. Test CDN compatibility (Cloudflare, Fastly, CloudFront)
5. Survey browser support in target audience

#### Implementation Phase (2-3 weeks)
1. Add server detection (`msh_has_avif_support()`)
2. Extend conversion pipeline for AVIF
3. Update job queue to handle AVIF jobs
4. Implement `<picture>` tag generation
5. Add settings UI with feature flag toggle
6. Write documentation and hosting guides

#### Testing Phase (1-2 weeks)
1. Test on staging with feature flag
2. Beta test with 5-10 internal users
3. Monitor encoding times, file sizes, errors
4. Test browser fallback behavior
5. Validate CDN delivery

#### Rollout Phase (2-4 weeks)
1. Enable for beta cohort (50-100 users)
2. Monitor telemetry and support tickets
3. Gradual rollout: 10% → 25% → 50% → 100%
4. Document case studies (file size savings, PageSpeed improvements)

**Total Effort:** 6-11 weeks (post-Phase 6)

---

### 3.10 Success Metrics

**Technical Metrics:**
- Average file size reduction: Target 30-50% vs WebP
- Encoding time: Target <5s per image
- Server CPU impact: Target <10% increase
- Error rate: Target <1%

**User Metrics:**
- Adoption rate: Target 40% of users enable AVIF
- PageSpeed score improvement: Target +5-10 points
- Bandwidth savings: Target 20-30% reduction
- Support ticket rate: Target <5 tickets per 100 users

**Business Metrics:**
- Feature differentiation vs competitors
- Pro plan conversion (AVIF as premium feature?)
- User satisfaction score

---

### References

**External:**
- [AVIF Image Format Explained](https://jakearchibald.com/2020/avif-has-landed/)
- [Can I Use AVIF](https://caniuse.com/avif)
- [Google: AVIF for the Web](https://web.dev/compress-images-avif/)

**Internal:**
- [Idea #2: Feature Flags](#idea-2-feature-flags-for-safe-rollouts) - Required for safe rollout
- [Track A: Job Queue](../PROJECT-STATUS-ALL-PHASES.md) - AVIF conversion uses queue
- [TODO.md](../TODO.md) - Future phase planning

---

### Conclusion (Idea #3)

AVIF conversion is a **high-value feature** that positions MSH Image Optimizer as a cutting-edge solution. However, it's **risky** due to server compatibility and encoding performance. **Feature flags (Idea #2) are essential** for safe rollout.

**Recommendation:**
- ✅ Add to Phase 7+ roadmap (post-Template Intelligence)
- ✅ Flag-gated beta rollout required
- ✅ Research phase first (2 weeks) to validate feasibility
- ⚠️ Consider as paid/pro feature (competitive advantage)

**Next Steps:**
1. Complete Idea #2 (Feature Flags) infrastructure first
2. Run research phase (hosting compatibility, benchmarks)
3. Prototype on staging with flag `msh_feature_avif_conversion`
4. Beta test with internal users
5. Gradual rollout following Idea #2 playbook

**Status:** 🎯 Proposed - Awaiting research phase
**Priority:** 🟡 Medium (High value, but post-Phase 6)
**Dependencies:** Idea #2 (Feature Flags), Track A (Job Queue)

---
---

## Idea #4: Staged Cloud Architecture (30-60-90 Day Roadmap)

**Source:** Strategic analysis + external AI validation
**Priority:** 🔴 Critical (Foundation for multi-platform strategy)
**Related Phases:** Phase 5+9 (Track C), Phase 10 (Multi-Platform)
**Status:** ✅ Approved - Execute Now

### Quick Summary

Build cloud infrastructure in three stages to de-risk investment, validate demand, and enable multi-platform expansion (WordPress → Shopify → Webflow → API). Start with WordPress-only hybrid (ImageKit), then minimal cloud service (Supabase), then full multi-platform (Google Cloud).

**Key Insight:** We're building a **platform** (multi-platform SaaS), not just a plugin. Centralized cloud is mandatory for Shopify/Webflow/API clients. But building Phase 10 infrastructure now (0 users) is premature optimization.

**Benefits:**
- ✅ 67% lower Year 1 cost ($12K vs $97K build-everything-now)
- ✅ 6 months faster to market (2 months vs 8 months)
- ✅ Profitable Year 1 (+$17K vs -$40K)
- ✅ De-risked validation (prove WordPress first)
- ✅ Cash-flow positive (WordPress funds cloud build)

**Strategy:** Start simple, validate demand, scale infrastructure as revenue grows.

---

### 4.1 The Multi-Platform Reality

#### Why Centralized Cloud Is Mandatory

**Problem:**
- Shopify apps can't do local image processing (no server-side)
- Webflow apps can't do local image processing (no server-side)
- Direct API clients need a service to call
- WordPress plugins CAN do local, but cloud is better UX

**Solution:** One centralized API serving all platforms

```
┌────────────────────────────────────────────┐
│  OPTIMIZER CLOUD SERVICE                   │
│  ✓ All image processing (AVIF, WebP, AI)  │
│  ✓ All metadata generation                │
│  ✓ All caching/optimization                │
└───────────────┬────────────────────────────┘
                │ REST API
    ┌───────────┼───────────┬───────────┐
    │           │           │           │
WordPress   Shopify    Webflow    Direct API
(thin)      (thin)     (thin)     (thin)
```

**Verdict:** Cloud is not optional for multi-platform. Question is WHEN to build it.

---

### 4.2 The Three-Stage Strategy

#### Stage 1: WordPress Hybrid (Days 1-30)

**Goal:** Validate product-market fit with minimal investment

**Architecture:**
```
WordPress Plugin (local + cloud hybrid)
    ↓
ImageKit API (AVIF conversion - MVP partner)
    ↓
Claude API (AI metadata - direct calls)
```

**What to Build:**
1. ImageKit adapter class (AVIF/WebP conversion)
2. Feature flag toggle (cloud vs local)
3. Streaming UI (progress for metadata generation)
4. Jobs page (queue visibility)
5. Telemetry (track everything for Gate decisions)

**Investment:** 2-3 weeks ($12K your time)
**Cost:** $50-100/month (ImageKit + Claude API)
**Revenue Target:** $5-10K/month by Month 6 (500-1,000 installs)

**Success Criteria (Gate 1):**
- ✅ 500+ active installs
- ✅ <5% support tickets about speed/reliability
- ✅ ImageKit costs >$150 CAD/month (time to own infrastructure)
- ✅ OR batch jobs taking >5 minutes at peak

**If Gate 1 NOT met:** Stay with ImageKit, focus on WordPress features

---

#### Stage 2: Optimizer Cloud MVP (Days 31-60)

**Goal:** Own infrastructure, test multi-tenant architecture

**Architecture:**
```
┌──────────────────────────────────────────┐
│  OPTIMIZER CLOUD SERVICE (Supabase MVP)  │
│  ✓ POST /v1/analyze   (single image)    │
│  ✓ POST /v1/batch     (async jobs)      │
│  ✓ GET  /v1/credits   (usage check)     │
└────────────────┬─────────────────────────┘
                 │
           WordPress Plugin
           (migrate from ImageKit)
```

**What to Build:**
1. Supabase Edge Functions (3 endpoints)
2. Multi-tenant database (customers, sites, credits, usage)
3. Credit system (free: 500/month, pro: 10K/month)
4. WordPress plugin toggle (cloud mode)
5. Admin dashboard (show remaining credits)

**Investment:** 6-8 weeks ($32K your time)
**Cost:** $25-50/month (Supabase Pro)
**Revenue Target:** $15-25K/month by Month 9 (1,500-2,000 installs)

**Success Criteria (Gate 2):**
- ✅ 1,500+ WordPress installs
- ✅ OR 6,000+ monthly cloud API calls
- ✅ Error rate <1% (cloud is stable)
- ✅ Positive cash flow ($10K+/month revenue)

**If Gate 2 NOT met:** Improve WordPress, grow install base before multi-platform

---

#### Stage 3: Multi-Platform Expansion (Days 61-90+)

**Goal:** Launch Shopify, Webflow, Direct API

**Architecture:**
```
┌──────────────────────────────────────────┐
│  OPTIMIZER CLOUD SERVICE (Google Cloud)  │
│  ✓ Cloud Run (API + Workers)            │
│  ✓ Cloud SQL (Multi-tenant DB)          │
│  ✓ Cloud Tasks (Async jobs)             │
│  ✓ Redis (Rate limiting + cache)        │
└────────────┬─────────────────────────────┘
                 │
    ┌────────────┼────────────┬──────────┐
WordPress    Shopify      Webflow    API
(thin)       (thin)       (thin)     (thin)
```

**What to Build:**
1. Migrate to Google Cloud (if Supabase can't scale)
2. Shopify app (thin client, calls same API)
3. Webflow app (thin client, calls same API)
4. Direct API tier (for developers)
5. Unified billing (Lemon Squeezy integration)

**Investment:** 10-12 weeks ($34K your time)
**Cost:** $200-400/month (Google Cloud)
**Revenue Target:** $40-70K/month by Month 15 (multi-platform)

**Deliverable:** True multi-platform SaaS, all clients use same cloud service

---

### 4.3 Financial Comparison

#### Scenario A: Build Multi-Platform Cloud Now

**Timeline:** 8 months before launch
**Cost:** $97,600 (infra + your time)
**Revenue Month 8:** $0-5,000 (just launched)
**Net Position Month 15:** -$47,600 to -$67,600 ❌

---

#### Scenario B: Staged Approach (Recommended)

**Timeline:**
- WordPress: 2 months to launch
- Cloud MVP: Month 7-9
- Multi-platform: Month 10-15

**Cost:** $78,820 total (3 stages)
**Revenue Month 8:** $10-15K
**Revenue Month 15:** $50-80K
**Net Position Month 15:** +$1,180 to +$46,180 ✅

**Difference:** $48K-114K better financially

---

### 4.4 The 30-60-90 Day Plan

#### Days 1-30: WordPress + ImageKit

**Week 1-2:** Complete Phase 5+9 Track A/B/C bugs
**Week 3:** Add ImageKit AVIF integration
**Week 4:** Add streaming UI, Jobs page, telemetry

**Deliverables:**
- ✅ Production-ready WordPress plugin
- ✅ AVIF works on any hosting (via ImageKit)
- ✅ Feature flag system (Idea #2)
- ✅ Basic telemetry for Gate decisions

**Code Snippets:**

```php
// ImageKit adapter
class MSH_ImageKit_Adapter {
    public function convert_avif($url) {
        return $this->convert($url, 'avif');
    }

    public function convert_webp($url) {
        return $this->convert($url, 'webp');
    }

    private function convert($url, $format) {
        $response = wp_remote_post('https://upload.imagekit.io/api/v1/files/upload', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->api_key . ':'),
            ],
            'body' => [
                'file' => $url,
                'transformation' => ['format' => $format, 'quality' => 80],
            ],
        ]);

        return json_decode(wp_remote_retrieve_body($response), true)['url'];
    }
}
```

```javascript
// Streaming UI
MSH.streamGeneration = function(mediaId) {
    const eventSource = new EventSource(
        `${mshHubData.ajaxUrl}?action=msh_stream_generate&media_id=${mediaId}`
    );

    eventSource.onmessage = function(e) {
        const data = JSON.parse(e.data);
        $('#progress').html(`Generating ${data.field}... (${data.progress})`);
    };
};
```

---

#### Days 31-60: Optimizer Cloud MVP

**Week 5-6:** Build Supabase Edge Functions (3 endpoints)
**Week 7-8:** Multi-tenant DB + credit system

**Deliverables:**
- ✅ Cloud API with 3 endpoints
- ✅ Multi-tenant architecture tested
- ✅ WordPress users migrated from ImageKit
- ✅ Credit system (free/pro tiers)

**API Implementation:**

```typescript
// Supabase Edge Function: /v1/analyze
serve(async (req) => {
  const apiKey = req.headers.get('X-API-Key');
  const { image_url, fields } = await req.json();

  // Validate API key, check credits
  const site = await getSite(apiKey);
  const credits = await checkCredits(site.customer_id);

  if (credits.remaining <= 0) {
    return new Response('Insufficient credits', { status: 402 });
  }

  // Process image (AVIF, AI, etc.)
  const result = await processImage(image_url, fields);

  // Deduct credits, log usage
  await deductCredits(site.customer_id, result.cost_usd);
  await logUsage({ site_id: site.id, ...result });

  return new Response(JSON.stringify(result));
});
```

**WordPress Integration:**

```php
// Plugin checks for API key
$api_key = get_option('msh_optimizer_cloud_api_key');

if ($api_key) {
    // CLOUD MODE
    $result = wp_remote_post('https://optimizer-cloud.supabase.co/functions/v1/analyze', [
        'headers' => ['X-API-Key' => $api_key],
        'body' => json_encode(['image_url' => $url, 'fields' => ['alt', 'title']]),
    ]);
} else {
    // LOCAL MODE (fallback)
    $result = msh_generate_local($image_id);
}
```

---

#### Days 61-90: Multi-Platform Prep

**Week 9-10:** WordPress.org launch + pricing test
**Week 11-12:** Shopify app skeleton

**Deliverables:**
- ✅ WordPress.org public listing
- ✅ Pricing validated (free/pro/agency tiers)
- ✅ Shopify app (alpha) using same cloud API
- ✅ Ready for Gate 2 evaluation

**Shopify App Example:**

```typescript
// Shopify app calls same API WordPress uses
export async function action({ request }: ActionArgs) {
  const { shop, accessToken } = await authenticate.admin(request);
  const mediaIds = formData.getAll('media_ids');

  // Call Optimizer Cloud (same API!)
  const response = await fetch('https://optimizer-cloud.supabase.co/functions/v1/batch', {
    method: 'POST',
    headers: {
      'X-API-Key': shop.apiKey,
      'X-Platform': 'shopify',
    },
    body: JSON.stringify({ media_ids: mediaIds }),
  });

  const { job_id } = await response.json();
  return json({ job_id });
}
```

---

### 4.5 Go/No-Go Gates

#### Gate 1: Open Optimizer Cloud Build

**Check at Day 30 (end of WordPress MVP)**

**Conditions to proceed:**
- ✅ ≥500 active WordPress installs
- ✅ <5% support tickets about speed/reliability
- ✅ ImageKit costs >$150 CAD/month
- ✅ OR batch jobs >5 minutes at peak

**If NOT met:** Stay with ImageKit, optimize WordPress features, grow install base

**If met:** Proceed to Days 31-60 (build Optimizer Cloud MVP)

---

#### Gate 2: Start Multi-Platform Development

**Check at Day 60 (end of Cloud MVP)**

**Conditions to proceed:**
- ✅ ≥1,500 WordPress installs
- ✅ OR ≥6,000 monthly cloud API calls
- ✅ Error rate <1% (cloud is stable)
- ✅ Positive cash flow ($10K+/month)

**If NOT met:** Improve WordPress experience, stabilize cloud

**If met:** Proceed to Days 61+ (build Shopify/Webflow apps)

---

### 4.6 Data to Log from Day 1

**Critical Telemetry:**

```php
// Every AI generation
msh_telemetry('ai_generation', [
    'tenant_id' => get_current_blog_id(),
    'site_id' => get_option('msh_site_id'),
    'endpoint' => 'analyze',
    'duration_ms' => 1250,
    'model' => 'claude-3-5-sonnet',
    'tokens' => 450,
    'cost_usd' => 0.0023,
    'cache_hit' => false,
    'error_code' => null,
]);

// Feature adoption
msh_telemetry('feature_enabled', [
    'feature' => 'avif_conversion',
    'enabled' => true,
]);

// Batch completion
msh_telemetry('batch_complete', [
    'batch_size' => 500,
    'completed' => 487,
    'failed' => 13,
    'duration_seconds' => 1834,
    'completion_ratio' => 0.974,
]);
```

**Why:**
- Gate 1: Is ImageKit costing >$150/month? Check `cost_usd`
- Gate 2: Are we stable (<1% errors)? Check `error_code`
- Optimization: Which features adopted? Check `feature_enabled`

---

### 4.7 Security & Reliability (MVP)

**Minimal but Essential:**

1. **API Key Rotation:** Allow per-site key rotation
2. **HMAC Webhook Signatures:** Verify callback authenticity
3. **Retry with Backoff:** 3 retries with 3, 9, 27 second delays
4. **P99 SLO Only:** 99th percentile <2s (no global SLA yet)
5. **Nightly Backups:** Supabase automatic backups

**Not Yet:**
- ❌ Multi-region deployment
- ❌ Edge caching layers
- ❌ Advanced monitoring (just basic logs)

---

### 4.8 Why Staged Approach Wins

**1. De-Risked Validation**
- Invest $12K to test WordPress (vs $97K for everything)
- If WordPress flops, saved $85K
- If WordPress succeeds, fund Shopify/Webflow from revenue

**2. Faster Time to Market**
- 2 months to WordPress launch (vs 8 months build-everything)
- 6 months ahead of competitors

**3. Cash Flow Positive**
- WordPress revenue ($10-15K/month by Month 6)
- Funds Cloud build (Month 7-9)
- Self-sustaining growth

**4. Learn Before Scaling**
- Real usage data informs cloud architecture
- Optimize API based on actual patterns
- Build better Shopify/Webflow (data-driven)

---

### 4.9 Integration with Other Ideas

#### Connects to Idea #1 (Safe Migrations)
- Use expand-backfill-switch-contract for cloud cutover
- Dual-write during migration (WordPress → Cloud)
- Feature flags for gradual rollout

#### Connects to Idea #2 (Feature Flags)
- `msh_feature_cloud_processing` - enable cloud mode
- `msh_feature_avif_conversion` - gate AVIF
- Per-user rollout for beta testing

#### Connects to Idea #3 (AVIF)
- Days 1-30: AVIF via ImageKit (partner)
- Days 31-60: AVIF via own cloud (migrate)
- Days 61+: AVIF for all platforms

---

### 4.10 What to Implement Right NOW

**Immediate Tasks (Days 1-7):**

1. **ImageKit Adapter Class**
   - File: `includes/cloud/class-msh-imagekit-adapter.php`
   - Methods: `convert_avif($url)`, `convert_webp($url)`

2. **Streaming UI for Metadata**
   - File: `assets/js/hub.js`
   - Function: `MSH.streamGeneration(mediaId)`

3. **Jobs Page (Queue Visibility)**
   - File: `admin/class-msh-jobs-page.php`
   - Show: Job ID, type, status, items, ETA

4. **Telemetry Logging**
   - Use existing `msh_telemetry()` helper
   - Log: AI calls, feature usage, batch stats

5. **"Use Optimizer Cloud" Toggle**
   - Settings page section
   - Disabled for now (enables after Gate 1)

---

### 4.11 Success Metrics (90 Days)

| Metric | Target |
|--------|--------|
| WordPress installs | 500+ |
| Support ticket rate | <5% |
| ImageKit costs | >$150 CAD/month (Gate 1) |
| Cloud API built | 3 endpoints (Gate 1 passed) |
| Cloud error rate | <1% (Gate 2) |
| Shopify app | Alpha working (Gate 2 passed) |

---

### References

**External:**
- [LiteSpeed Cache AVIF Implementation](https://blog.litespeedtech.com/2025/03/26/litespeed-cache-v7-avif-support/) (cloud processing model)
- [Supabase Edge Functions](https://supabase.com/docs/guides/functions)
- [Google Cloud Run](https://cloud.google.com/run/docs)

**Internal:**
- [Idea #1: Safe Migrations](#idea-1-expand-backfill-switch-contract-pattern) - Migration pattern
- [Idea #2: Feature Flags](#idea-2-feature-flags-for-safe-rollouts) - Rollout strategy
- [Idea #3: AVIF Conversion](#idea-3-avif-image-conversion) - Cloud enables AVIF
- [TODO.md](../TODO.md) - Phase roadmap (updated)
- [PROJECT-STATUS-ALL-PHASES.md](../PROJECT-STATUS-ALL-PHASES.md) - Overall status (updated)

---

### Conclusion (Idea #4)

Staged cloud architecture is the **only viable path** for multi-platform expansion. Building Phase 10 infrastructure now (0 users) wastes $85K and delays launch by 6 months. Staged approach:
- ✅ 67% lower cost
- ✅ 6 months faster to market
- ✅ Profitable Year 1
- ✅ De-risked with Gates
- ✅ Learn from real users

**Next Steps:**
1. Execute Days 1-30 plan (WordPress + ImageKit)
2. Hit Gate 1 (500 installs, $150 ImageKit cost)
3. Build Cloud MVP (Days 31-60)
4. Hit Gate 2 (1,500 installs, <1% errors)
5. Multi-platform expansion (Days 61+)

**Status:** ✅ Approved - Begin Days 1-30 implementation
**Priority:** 🔴 Critical (foundation for multi-platform)
**Owner:** Solo dev + AIs (manageable with staged approach)

---

### 🚀 IMPLEMENTATION UPDATE (October 22, 2025)

**Status:** 🔨 Foundation Complete - Hybrid Architecture Implemented

#### What We Built (Metadata Sync Foundation)

We implemented the **Hybrid Strategy** (metadata now, images later) to ship fast while staying AVIF-ready:

1. **OpenAPI Specification** (`/sync-api/openapi/sync-v1.yaml`)
   - Complete API contract for metadata sync
   - **AVIF-ready:** `storage` field in schema (NULL today, images in Phase 10)
   - Stub `/image/upload` endpoint (returns 501 now, 202 later)
   - JWT authentication, cursor pagination, conflict resolution

2. **Database Schema** (`/sync-api/db/migrations/0001_init.sql`)
   - 5 tables: licenses, sites, media_metadata, sync_operations, quota_usage
   - **AVIF-ready:** `storage` JSONB field reserved for image URLs
   - Row-Level Security (RLS) for multi-tenant isolation
   - Pure PostgreSQL (portable: Supabase → Google Cloud)

3. **Architecture Documentation** (`/sync-api/docs/HYBRID-ARCHITECTURE.md`)
   - Hybrid strategy explained (metadata sync ships in 1-2 weeks)
   - Domain separation: sync.thedot.com, images.thedot.com, cdn.thedot.com
   - Migration path: Supabase → Google Cloud (blue/green cutover)
   - Phase 10 integration guide (no rebuild needed)

#### Key Architecture Decisions

**Metadata-Only Sync (Phase 5+9):**
- ✅ Ships in 1-2 weeks (immediate Pro feature)
- ✅ No image processing complexity yet
- ✅ JSON-only (fast, cheap, simple)

**AVIF-Ready Placeholders (Phase 10):**
- ✅ `storage` field in database (currently NULL)
- ✅ `/image/upload` stub endpoint (returns 501)
- ✅ Domain reserved (images.thedot.com)
- ✅ **No rebuild needed** - just fill in stubs

**Google Cloud Portable:**
- ✅ Pure PostgreSQL (no Supabase lock-in)
- ✅ Same JWT auth works on both platforms
- ✅ RLS policies translate to Cloud SQL
- ✅ Nginx reverse proxy for blue/green cutover

#### Schema Highlight (AVIF-Ready)

```sql
create table media_metadata (
  id uuid primary key,
  site_id uuid references sites(site_id),
  media_id bigint not null,
  locale text default 'en',

  -- Active Now (Phase 5+9)
  title text,
  alt text,
  caption text,
  description text,
  custom jsonb,

  -- Reserved for Phase 10 (AVIF)
  storage jsonb,  -- ⚠️ Always NULL today
                  -- Future: {"avif":"gs://...","webp":"gs://..."}

  rev bigint default 1,
  updated_at timestamptz default now(),

  unique(site_id, media_id, locale)
);
```

#### What Happens When We Add AVIF (Phase 10)

**No Changes Needed:**
- ✅ Database schema (storage field exists)
- ✅ API contract (already documented)
- ✅ WordPress client (already sends storage field)
- ✅ Domain structure (images.thedot.com reserved)

**What We Add:**
1. Remove 501 from `/image/upload` endpoint
2. Implement image processor (Cloud Function or ImageKit)
3. Populate `storage` field after conversion
4. WordPress renders `<picture>` tags

**Estimated Effort:** 2-3 weeks (not a rebuild!)

#### Documentation Created

| File | Purpose | Status |
|------|---------|--------|
| `sync-api/openapi/sync-v1.yaml` | API contract (source of truth) | ✅ Complete |
| `sync-api/db/migrations/0001_init.sql` | Database schema + RLS | ✅ Complete |
| `sync-api/docs/HYBRID-ARCHITECTURE.md` | Strategy & rationale | ✅ Complete |
| `SYNC-FOUNDATION-COMPLETE.md` | Implementation summary | ✅ Complete |

#### Next Steps

**Immediate (This Week):**
1. Set up Supabase project
2. Run migration `0001_init.sql`
3. Implement Edge Functions (handshake, push, pull, resolve, quota)
4. Set up JWT auth with JWKS
5. Update WordPress `class-msh-remote-sync.php`

**Phase 10 (3-4 Months):**
1. Integrate with ImageKit or build own processor
2. Implement `/image/upload` (replace 501 with real logic)
3. Populate `storage` field after conversion
4. WordPress renders AVIF/WebP/JPEG stack

**Migration to Google Cloud (Phase 10+):**
1. Stand up Cloud Run + Cloud SQL
2. Blue/green cutover via Nginx proxy
3. Monitor for 1 week before full switchover

#### Success Metrics

- ✅ **Metadata sync ships:** 1-2 weeks (Pro feature complete)
- ✅ **AVIF-ready:** Zero rebuild when adding images
- ✅ **Google Cloud ready:** Pure Postgres, portable
- ✅ **Multi-platform ready:** Same API serves WordPress, Shopify, Webflow

**Related Files:**
- See: `SYNC-FOUNDATION-COMPLETE.md` for full implementation summary
- See: `sync-api/docs/HYBRID-ARCHITECTURE.md` for architecture details
- See: `sync-api/openapi/sync-v1.yaml` for API contract

**Commits:**
- `014bddd` - "feat: Hybrid cloud architecture - metadata sync with AVIF-ready foundation"
- `a07fa82` - "docs: add sync infrastructure foundation completion summary"

---
---

## Idea #5: AI-Powered Image Delivery Optimization

**Source:** User-provided modern image delivery playbook
**Priority:** 🟢 Low (Phase 10 Stage 4+ enhancement, not core feature)
**Related Phases:** Phase 10 Stage 4+ (months 4-12), Phase 2 (Context Fusion), Phase 8 (Analytics)
**Status:** 📋 Future Research - Requires Stage 2-3 cloud infrastructure + demand validation

### Quick Summary

Modern image delivery architecture using pre-generated responsive variants (AVIF/WebP/JPEG at 1x/1.5x/2x DPR), AI-based priority scoring, and intelligent lazy loading orchestration. Leverages Phase 2 Context Fusion data to rank images by perceived impact (hero > product > decorative) and load highest-scoring images first for optimal LCP and user experience.

**✅ IMMEDIATE VALUE (Stage 1):** Priority hints for hero images (1-2 hour quick win)
**🔮 FUTURE VALUE (Stage 4+):** Full responsive delivery + AI scoring + custom lazy loader

**Key Innovation:** No other WordPress plugin uses AI context data to optimize image load order. We already have the Context Fusion infrastructure (on_topic scores, usage_type, position data) - this extends it to performance optimization.

---

### 5.1 The Modern Image Delivery Pattern

#### Why Stacks Are Shifting

**Problem:** Images are the heaviest assets on most sites
- Average page: 1.9MB total, 900KB+ is images (47% of page weight)
- Slow LCP (Largest Contentful Paint) = lower SEO rankings
- Poor Core Web Vitals = reduced conversions

**Solution:** Pre-generate variants at build time, serve smartly at runtime
- AVIF/WebP formats = 30-70% smaller than JPEG
- Responsive srcset = serve right size per device
- Priority hints = load critical images first
- Edge caching = fast delivery globally

**Financial Impact:**
- Faster LCP = +0.5-2% conversion rate improvement
- Smaller images = lower CDN bandwidth costs
- Better SEO = more organic traffic

---

### 5.2 The Three-Part Strategy

#### Part 1: Pre-Generate Variants (Build Time)

**What:** Create multiple formats and sizes for each image

**Formats:**
- AVIF (best compression, modern browsers)
- WebP (good compression, wide support)
- JPEG (fallback, universal support)

**Sizes (Responsive):**
- 1x width (base)
- 1.5x width (higher DPI displays)
- 2x width (Retina displays)

**Breakpoints:**
- Mobile: 320px, 480px, 640px
- Tablet: 768px, 1024px
- Desktop: 1280px, 1920px

**Example Output:**
```
/uploads/2025/10/hero-image.jpg          # Original
/uploads/2025/10/hero-image-640w.jpg     # Mobile 1x
/uploads/2025/10/hero-image-960w.jpg     # Mobile 1.5x
/uploads/2025/10/hero-image-1280w.jpg    # Mobile 2x
/uploads/2025/10/hero-image-640w.webp    # Mobile 1x WebP
/uploads/2025/10/hero-image-640w.avif    # Mobile 1x AVIF
# ... (18 variants total per image)
```

**Storage Impact:**
- 1 image → 18 variants (3 formats × 6 sizes)
- Average increase: 1.5x storage cost
- Mitigated by: Smaller file sizes (AVIF/WebP compression)

---

#### Part 2: Smart Runtime Delivery

**Browser Format Selection:**
```html
<picture>
  <!-- AVIF: Try first (best compression) -->
  <source type="image/avif"
          srcset="hero-640w.avif 640w,
                  hero-960w.avif 960w,
                  hero-1280w.avif 1280w"
          sizes="(max-width: 640px) 100vw, 640px">

  <!-- WebP: Fallback for older browsers -->
  <source type="image/webp"
          srcset="hero-640w.webp 640w,
                  hero-960w.webp 960w,
                  hero-1280w.webp 1280w"
          sizes="(max-width: 640px) 100vw, 640px">

  <!-- JPEG: Universal fallback -->
  <img src="hero-640w.jpg"
       srcset="hero-640w.jpg 640w,
               hero-960w.jpg 960w,
               hero-1280w.jpg 1280w"
       sizes="(max-width: 640px) 100vw, 640px"
       alt="Hero image"
       loading="lazy"
       decoding="async">
</picture>
```

**Priority Hints:**
```html
<!-- Hero image: Load ASAP -->
<img src="hero.jpg"
     fetchpriority="high"
     loading="eager"
     decoding="async">

<!-- Below-the-fold: Lazy load -->
<img src="product.jpg"
     loading="lazy"
     decoding="async">
```

**Policy:**
- ✅ Never upscale (don't generate 2000px from 1000px source)
- ✅ Never send both AVIF and WebP (browser picks one via `<picture>`)
- ✅ Cap max width to container + 2x DPR
- ✅ Lazy load everything below fold

---

#### Part 3: AI Priority Scoring

**The Innovation:** Use Context Fusion data to rank images by impact

**Scoring Factors:**
```javascript
priority_score = (
    position_weight * 0.4 +        // Above fold = high
    semantic_weight * 0.3 +        // Hero/product = high
    context_score * 0.2 +          // On-topic = high
    usage_frequency * 0.1          // Frequently used = high
)
```

**Position Weight (from Phase 2 Context Fusion):**
- `usage_type: 'featured'` → 1.0 (hero images)
- `block_path: '/core/cover[0]'` → 0.95 (above fold)
- `block_path: '/core/columns[5]'` → 0.3 (below fold)
- `usage_type: 'gallery'` → 0.2 (decorative)

**Semantic Weight:**
- Product images → 0.9
- Hero images → 1.0
- Testimonial photos → 0.7
- Decorative icons → 0.2

**Context Score (from `wp_msh_optimizer_context`):**
- `intent: 'on_topic'` + `context_score: 95` → 0.95
- `intent: 'off_topic'` + `context_score: 45` → 0.45

**Usage Frequency:**
- Used in 10+ posts → 0.8 (site-wide branding)
- Used in 1 post → 0.2 (one-off image)

**Example Priority Manifest:**
```json
{
  "page_123": {
    "attachments": {
      "456": {
        "score": 0.98,
        "role": "hero",
        "position": "above_fold",
        "format": "avif",
        "width": 1280,
        "reason": "Featured image, on-topic (98%), above fold"
      },
      "789": {
        "score": 0.86,
        "role": "primary",
        "position": "in_view",
        "format": "webp",
        "width": 640,
        "reason": "Product image, on-topic (92%), main content"
      },
      "101": {
        "score": 0.22,
        "role": "decor",
        "position": "below_fold",
        "format": "jpeg",
        "width": 320,
        "reason": "Decorative, off-topic (30%), footer"
      }
    }
  }
}
```

---

### 5.3 Phase 10 Stage 1 Quick Wins (Days 1-30)

#### Quick Win #1: Picture Tag Support for AVIF

**File:** `includes/class-msh-webp-delivery.php` (enhance existing)

**Add Method:**
```php
/**
 * Render <picture> tag with AVIF, WebP, JPEG fallback.
 *
 * @param int    $attachment_id Attachment ID
 * @param string $size          Image size (thumbnail, medium, large, full)
 * @param array  $attr          Additional attributes
 * @return string HTML <picture> tag
 */
public function render_picture_tag( $attachment_id, $size = 'full', $attr = [] ) {
    $urls = $this->get_format_urls( $attachment_id, $size );

    if ( ! $urls ) {
        return '';
    }

    $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
    $classes = isset( $attr['class'] ) ? $attr['class'] : '';
    $loading = isset( $attr['loading'] ) ? $attr['loading'] : 'lazy';
    $fetchpriority = isset( $attr['fetchpriority'] ) ? $attr['fetchpriority'] : '';

    $output = '<picture>';

    // AVIF source (if available)
    if ( ! empty( $urls['avif'] ) ) {
        $output .= sprintf(
            '<source type="image/avif" srcset="%s">',
            esc_url( $urls['avif'] )
        );
    }

    // WebP source (if available)
    if ( ! empty( $urls['webp'] ) ) {
        $output .= sprintf(
            '<source type="image/webp" srcset="%s">',
            esc_url( $urls['webp'] )
        );
    }

    // JPEG fallback (always present)
    $img_attrs = [
        'src="' . esc_url( $urls['jpeg'] ) . '"',
        'alt="' . esc_attr( $alt ) . '"',
    ];

    if ( $classes ) {
        $img_attrs[] = 'class="' . esc_attr( $classes ) . '"';
    }
    if ( $loading ) {
        $img_attrs[] = 'loading="' . esc_attr( $loading ) . '"';
    }
    if ( $fetchpriority ) {
        $img_attrs[] = 'fetchpriority="' . esc_attr( $fetchpriority ) . '"';
    }

    // Always add decoding="async" for better performance
    $img_attrs[] = 'decoding="async"';

    $output .= '<img ' . implode( ' ', $img_attrs ) . '>';
    $output .= '</picture>';

    return $output;
}

/**
 * Get format URLs for an attachment.
 *
 * @param int    $attachment_id Attachment ID
 * @param string $size          Image size
 * @return array|false Array with 'avif', 'webp', 'jpeg' keys, or false
 */
private function get_format_urls( $attachment_id, $size ) {
    $image_data = wp_get_attachment_image_src( $attachment_id, $size );

    if ( ! $image_data ) {
        return false;
    }

    $jpeg_url = $image_data[0];
    $webp_url = $this->get_webp_url( $jpeg_url );
    $avif_url = $this->get_avif_url( $jpeg_url );

    return [
        'avif' => $avif_url,
        'webp' => $webp_url,
        'jpeg' => $jpeg_url,
        'alt'  => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
    ];
}

/**
 * Get AVIF URL if exists.
 *
 * @param string $jpeg_url Original JPEG URL
 * @return string|false AVIF URL or false
 */
private function get_avif_url( $jpeg_url ) {
    // Check if AVIF feature is enabled
    if ( ! msh_flag_enabled( 'avif_conversion' ) ) {
        return false;
    }

    $avif_url = preg_replace( '/\.(jpg|jpeg|png)$/i', '.avif', $jpeg_url );

    // Check if AVIF file exists
    $upload_dir = wp_upload_dir();
    $file_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $avif_url );

    if ( file_exists( $file_path ) ) {
        return $avif_url;
    }

    return false;
}
```

**Integration Hook:**
```php
// Replace default WordPress image output
add_filter( 'wp_get_attachment_image', function( $html, $attachment_id, $size, $icon, $attr ) {
    if ( msh_flag_enabled( 'picture_tag_delivery' ) ) {
        $delivery = new MSH_WebP_Delivery();
        return $delivery->render_picture_tag( $attachment_id, $size, $attr );
    }
    return $html;
}, 10, 5 );
```

**Why Now:** Aligns with Phase 10 Stage 1 AVIF rollout (ImageKit partner integration)

**Dev Time:** 2-3 hours

**Value:**
- ✅ Proper format fallback chain
- ✅ Browser picks best supported format
- ✅ No JavaScript required (native `<picture>` element)

---

#### Quick Win #2: Priority Hints for Hero Images

**File:** `includes/class-msh-webp-delivery.php` (enhance existing)

**Add Filter:**
```php
/**
 * Add fetchpriority="high" and loading="eager" to hero images.
 */
add_filter( 'wp_get_attachment_image_attributes', function( $attr, $attachment, $size ) {
    // Only apply to featured images
    if ( is_singular() && get_post_thumbnail_id() === $attachment->ID ) {
        $attr['fetchpriority'] = 'high';
        $attr['loading'] = 'eager'; // Don't lazy-load hero
        $attr['decoding'] = 'async'; // Still async decode
    }

    // Also apply to hero blocks (Gutenberg cover blocks)
    global $post;
    if ( $post && has_blocks( $post->post_content ) ) {
        $blocks = parse_blocks( $post->post_content );
        $first_cover = $this->find_first_cover_block( $blocks );

        if ( $first_cover && isset( $first_cover['attrs']['id'] ) ) {
            if ( $first_cover['attrs']['id'] === $attachment->ID ) {
                $attr['fetchpriority'] = 'high';
                $attr['loading'] = 'eager';
            }
        }
    }

    return $attr;
}, 10, 3 );

/**
 * Find first cover block in parsed blocks (recursive).
 *
 * @param array $blocks Parsed blocks
 * @return array|null First cover block or null
 */
private function find_first_cover_block( $blocks ) {
    foreach ( $blocks as $block ) {
        if ( 'core/cover' === $block['blockName'] ) {
            return $block;
        }

        // Recurse into inner blocks
        if ( ! empty( $block['innerBlocks'] ) ) {
            $found = $this->find_first_cover_block( $block['innerBlocks'] );
            if ( $found ) {
                return $found;
            }
        }
    }

    return null;
}
```

**Why Now:**
- ✅ Zero infrastructure cost (no cloud required)
- ✅ Immediate LCP improvement (100-300ms faster)
- ✅ Works with existing WebP delivery

**Dev Time:** 1-2 hours

**Value:**
- ✅ Faster LCP = better Core Web Vitals
- ✅ Better SEO rankings (Google PageSpeed Insights)
- ✅ No breaking changes (progressive enhancement)

**Testing:**
```bash
# Before (default WordPress)
<img src="hero.jpg" loading="lazy">
# LCP: ~2.5s

# After (with priority hints)
<img src="hero.jpg" fetchpriority="high" loading="eager">
# LCP: ~2.2s (300ms improvement)
```

---

### 5.4 Phase 10 Stage 4+ Advanced Features (Months 4-12)

**Prerequisites:**
- ✅ Phase 10 Stage 2-3 complete (cloud infrastructure live)
- ✅ 1,500+ active installs (proven demand)
- ✅ Cloud infrastructure stable for 3+ months
- ✅ Phase 8 Analytics collecting LCP data

---

#### Feature 1: Responsive Variant Generation

**Goal:** Generate 1x, 1.5x, 2x widths per breakpoint

**Architecture:**
```
User uploads image (2000px)
    ↓
WordPress creates standard sizes (thumbnail, medium, large, full)
    ↓
Job queue: "generate_responsive_variants"
    ↓
Cloud API: POST /api/v1/variants
    ↓
Generate 18 variants (3 formats × 6 sizes)
    ↓
Store in /uploads/2025/10/variants/
    ↓
Update attachment meta: msh_responsive_variants
```

**Variants Table:**
```sql
CREATE TABLE wp_msh_image_variants (
    variant_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attachment_id BIGINT UNSIGNED NOT NULL,
    format ENUM('avif','webp','jpeg') NOT NULL,
    width SMALLINT UNSIGNED NOT NULL,
    height SMALLINT UNSIGNED NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (variant_id),
    UNIQUE KEY idx_variant (attachment_id, format, width),
    KEY idx_attachment (attachment_id),
    KEY idx_format (format)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Breakpoint Strategy:**
```php
// includes/class-msh-variant-generator.php
class MSH_Variant_Generator {

    const BREAKPOINTS = [
        'mobile'  => [320, 480, 640],
        'tablet'  => [768, 1024],
        'desktop' => [1280, 1920],
    ];

    const DPR_MULTIPLIERS = [1.0, 1.5, 2.0];

    /**
     * Generate all responsive variants for an attachment.
     *
     * @param int $attachment_id Attachment ID
     * @return array Generated variants
     */
    public function generate_variants( $attachment_id ) {
        $original = wp_get_attachment_image_src( $attachment_id, 'full' );
        $original_width = $original[1];

        $variants = [];

        foreach ( self::BREAKPOINTS as $device => $widths ) {
            foreach ( $widths as $base_width ) {
                foreach ( self::DPR_MULTIPLIERS as $dpr ) {
                    $target_width = $base_width * $dpr;

                    // Never upscale
                    if ( $target_width > $original_width ) {
                        continue;
                    }

                    // Generate AVIF, WebP, JPEG
                    $variants[] = $this->generate_variant(
                        $attachment_id,
                        $target_width,
                        'avif'
                    );
                    $variants[] = $this->generate_variant(
                        $attachment_id,
                        $target_width,
                        'webp'
                    );
                    $variants[] = $this->generate_variant(
                        $attachment_id,
                        $target_width,
                        'jpeg'
                    );
                }
            }
        }

        return array_filter( $variants ); // Remove nulls (upscale skips)
    }

    /**
     * Generate single variant via cloud API.
     *
     * @param int    $attachment_id Attachment ID
     * @param int    $width         Target width
     * @param string $format        Target format (avif, webp, jpeg)
     * @return array|null Variant data or null
     */
    private function generate_variant( $attachment_id, $width, $format ) {
        // Send to cloud API for processing
        $response = wp_remote_post( MSH_CLOUD_API . '/v1/variants', [
            'body' => [
                'attachment_id' => $attachment_id,
                'width' => $width,
                'format' => $format,
                'quality' => 80,
            ],
        ]);

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        // Store variant in database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'msh_image_variants',
            [
                'attachment_id' => $attachment_id,
                'format' => $format,
                'width' => $width,
                'height' => $data['height'],
                'file_size' => $data['file_size'],
                'file_path' => $data['file_path'],
                'url' => $data['url'],
            ]
        );

        return $data;
    }
}
```

**WP-CLI Command:**
```bash
# Generate variants for all images
wp msh variants generate --all

# Generate for specific attachment
wp msh variants generate --id=123

# Regenerate for images missing variants
wp msh variants regenerate --missing-only

# Clean up variants for deleted attachments
wp msh variants cleanup --orphaned
```

**Storage Impact:**
- 1 image (original 1920px, 500KB) → 18 variants (~750KB total)
- Average increase: 1.5x storage
- Mitigated by: AVIF/WebP compression (net savings vs serving full-size)

**Dev Time:** 2-3 weeks

**Prerequisites:**
- ✅ Cloud API /v1/variants endpoint (Stage 2-3)
- ✅ CDN integration (Cloudflare, BunnyCDN, or ImageKit)
- ✅ Storage budget (1.5x per image)

---

#### Feature 2: AI Priority Scoring Manifest

**Goal:** Generate per-page JSON manifest with AI-scored image priorities

**Manifest Generation:**
```php
// includes/class-msh-priority-manifest.php
class MSH_Priority_Manifest_Generator {

    /**
     * Generate priority manifest for a post.
     *
     * @param int $post_id Post ID
     * @return array Priority manifest
     */
    public function generate_manifest( $post_id ) {
        $attachments = $this->get_post_attachments( $post_id );
        $manifest = [
            'post_id' => $post_id,
            'generated_at' => current_time( 'mysql' ),
            'attachments' => [],
        ];

        foreach ( $attachments as $attachment_id => $usage_data ) {
            $score = $this->calculate_priority_score(
                $attachment_id,
                $post_id,
                $usage_data
            );

            $manifest['attachments'][ $attachment_id ] = [
                'score' => $score['total'],
                'role' => $score['role'],
                'position' => $score['position'],
                'format' => $this->recommend_format( $attachment_id ),
                'width' => $this->recommend_width( $usage_data ),
                'reason' => $score['reason'],
            ];
        }

        // Sort by score (descending)
        uasort( $manifest['attachments'], function( $a, $b ) {
            return $b['score'] <=> $a['score'];
        });

        return $manifest;
    }

    /**
     * Calculate priority score using AI context data.
     *
     * @param int   $attachment_id Attachment ID
     * @param int   $post_id       Post ID
     * @param array $usage_data    Usage data from Context Fusion
     * @return array Score breakdown
     */
    private function calculate_priority_score( $attachment_id, $post_id, $usage_data ) {
        // Get Context Fusion data (Phase 2)
        global $wpdb;
        $context = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}msh_optimizer_context
             WHERE media_id = %d AND post_id = %d LIMIT 1",
            $attachment_id,
            $post_id
        ));

        // Position weight (0-1)
        $position_weight = $this->calculate_position_weight( $usage_data );

        // Semantic weight (0-1)
        $semantic_weight = $this->calculate_semantic_weight( $usage_data );

        // Context score (0-1) - from Phase 2
        $context_score = $context ? ( $context->context_score / 100 ) : 0.5;

        // Usage frequency (0-1)
        $usage_frequency = min( 1.0, $context->usage_count / 10 );

        // Weighted total
        $total = (
            $position_weight * 0.4 +
            $semantic_weight * 0.3 +
            $context_score * 0.2 +
            $usage_frequency * 0.1
        );

        return [
            'total' => round( $total, 2 ),
            'position_weight' => $position_weight,
            'semantic_weight' => $semantic_weight,
            'context_score' => $context_score,
            'usage_frequency' => $usage_frequency,
            'role' => $this->get_role( $usage_data ),
            'position' => $this->get_position( $usage_data ),
            'reason' => $this->generate_reason(
                $usage_data,
                $context,
                $total
            ),
        ];
    }

    /**
     * Calculate position weight from usage data.
     *
     * @param array $usage_data Usage data
     * @return float Weight 0-1
     */
    private function calculate_position_weight( $usage_data ) {
        $type = $usage_data['usage_type'];
        $block_path = $usage_data['block_path'];

        // Featured images = highest priority
        if ( $type === 'featured' ) {
            return 1.0;
        }

        // First block = likely above fold
        if ( strpos( $block_path, '[0]' ) !== false ) {
            return 0.95;
        }

        // Cover blocks = hero images
        if ( strpos( $block_path, 'core/cover' ) !== false ) {
            return 0.9;
        }

        // Gallery images = lower priority
        if ( $type === 'gallery' ) {
            return 0.3;
        }

        // Default: middle priority
        return 0.6;
    }

    /**
     * Calculate semantic weight from usage data.
     *
     * @param array $usage_data Usage data
     * @return float Weight 0-1
     */
    private function calculate_semantic_weight( $usage_data ) {
        // Use alt text / filename to guess semantic role
        $alt = get_post_meta( $usage_data['attachment_id'], '_wp_attachment_image_alt', true );
        $filename = basename( get_attached_file( $usage_data['attachment_id'] ) );

        $combined = strtolower( $alt . ' ' . $filename );

        // Hero images
        if ( preg_match( '/hero|banner|header/', $combined ) ) {
            return 1.0;
        }

        // Product images
        if ( preg_match( '/product|item|shop|buy/', $combined ) ) {
            return 0.9;
        }

        // Testimonial images
        if ( preg_match( '/testimonial|review|client/', $combined ) ) {
            return 0.7;
        }

        // Icons/decorative
        if ( preg_match( '/icon|decor|decoration|background/', $combined ) ) {
            return 0.2;
        }

        // Default
        return 0.6;
    }
}
```

**Manifest Storage:**
```sql
-- Store manifests in transients for 24 hours
set_transient( 'msh_priority_manifest_' . $post_id, $manifest, DAY_IN_SECONDS );
```

**API Endpoint:**
```php
// REST API: GET /wp-json/msh/v1/manifest/{post_id}
register_rest_route( 'msh/v1', '/manifest/(?P<id>\d+)', [
    'methods' => 'GET',
    'callback' => function( $request ) {
        $post_id = $request['id'];

        // Check cache first
        $manifest = get_transient( 'msh_priority_manifest_' . $post_id );

        if ( ! $manifest ) {
            $generator = new MSH_Priority_Manifest_Generator();
            $manifest = $generator->generate_manifest( $post_id );
            set_transient( 'msh_priority_manifest_' . $post_id, $manifest, DAY_IN_SECONDS );
        }

        return rest_ensure_response( $manifest );
    },
    'permission_callback' => '__return_true',
]);
```

**Dev Time:** 3-4 weeks

**Prerequisites:**
- ✅ Phase 2 Context Fusion complete (context_score, usage_type data)
- ✅ Phase 8 Analytics collecting LCP data (validate scores work)

---

#### Feature 3: Custom Lazy Loader with Priority Queue

**Goal:** Replace WordPress core lazy loading with AI-scored priority queue

**Architecture:**
```javascript
// assets/js/msh-priority-loader.js
class MSH_Priority_Loader {
    constructor() {
        this.manifest = null;
        this.loadQueue = [];
        this.loading = false;

        this.init();
    }

    async init() {
        // Fetch priority manifest
        const postId = mshData.postId;
        const response = await fetch( `/wp-json/msh/v1/manifest/${postId}` );
        this.manifest = await response.json();

        // Build load queue sorted by score
        this.buildQueue();

        // Start loading
        this.processQueue();

        // Setup intersection observer for below-fold images
        this.setupObserver();
    }

    buildQueue() {
        const images = document.querySelectorAll( 'img[data-msh-attachment-id]' );

        images.forEach( img => {
            const attachmentId = img.dataset.mshAttachmentId;
            const priority = this.manifest.attachments[ attachmentId ];

            if ( ! priority ) {
                return;
            }

            this.loadQueue.push({
                img: img,
                attachmentId: attachmentId,
                score: priority.score,
                role: priority.role,
                position: priority.position,
            });
        });

        // Sort by score (descending)
        this.loadQueue.sort( ( a, b ) => b.score - a.score );
    }

    async processQueue() {
        if ( this.loading || this.loadQueue.length === 0 ) {
            return;
        }

        this.loading = true;

        // Load top 3 images immediately (above fold)
        const immediate = this.loadQueue.splice( 0, 3 );
        await Promise.all( immediate.map( item => this.loadImage( item ) ) );

        // Load remaining images progressively
        while ( this.loadQueue.length > 0 ) {
            const item = this.loadQueue.shift();
            await this.loadImage( item );

            // Small delay to avoid blocking main thread
            await this.sleep( 50 );
        }

        this.loading = false;
    }

    async loadImage( item ) {
        return new Promise( ( resolve ) => {
            const img = item.img;
            const src = img.dataset.src;

            img.onload = () => {
                img.classList.add( 'msh-loaded' );
                resolve();
            };

            img.onerror = () => {
                console.error( `Failed to load image: ${item.attachmentId}` );
                resolve();
            };

            img.src = src;
        });
    }

    setupObserver() {
        const observer = new IntersectionObserver( ( entries ) => {
            entries.forEach( entry => {
                if ( entry.isIntersecting ) {
                    const img = entry.target;
                    const src = img.dataset.src;

                    if ( src && ! img.src ) {
                        img.src = src;
                        observer.unobserve( img );
                    }
                }
            });
        }, { rootMargin: '200px' } );

        // Observe all lazy images
        document.querySelectorAll( 'img[data-src]' ).forEach( img => {
            observer.observe( img );
        });
    }

    sleep( ms ) {
        return new Promise( resolve => setTimeout( resolve, ms ) );
    }
}

// Initialize on DOM ready
document.addEventListener( 'DOMContentLoaded', () => {
    if ( typeof mshData !== 'undefined' && mshData.priorityLoaderEnabled ) {
        new MSH_Priority_Loader();
    }
});
```

**HTML Output Modification:**
```php
// Modify image output to include data attributes
add_filter( 'wp_get_attachment_image', function( $html, $attachment_id, $size, $icon, $attr ) {
    if ( ! msh_flag_enabled( 'priority_loader' ) ) {
        return $html;
    }

    // Replace src with data-src for lazy loading
    $html = preg_replace( '/src="([^"]+)"/', 'data-src="$1" data-msh-attachment-id="' . $attachment_id . '"', $html );

    // Remove loading="lazy" (we handle it)
    $html = str_replace( 'loading="lazy"', '', $html );

    return $html;
}, 10, 5 );
```

**Performance Monitoring:**
```javascript
// Track LCP element
new PerformanceObserver( ( list ) => {
    const entries = list.getEntries();
    const lastEntry = entries[ entries.length - 1 ];

    // Send to telemetry
    fetch( '/wp-json/msh/v1/telemetry', {
        method: 'POST',
        body: JSON.stringify({
            event: 'lcp',
            element: lastEntry.element?.tagName,
            attachment_id: lastEntry.element?.dataset.mshAttachmentId,
            time: lastEntry.renderTime || lastEntry.loadTime,
            url: lastEntry.url,
        }),
    });
}).observe({ type: 'largest-contentful-paint', buffered: true });
```

**Dev Time:** 4-5 weeks

**Prerequisites:**
- ✅ Priority manifest system (Feature 2)
- ✅ Phase 8 Analytics (LCP tracking)
- ✅ Extensive testing (don't break existing sites)

---

### 5.5 Technical Specifications

#### Markup Best Practices

**Policy:**
- ✅ Never upscale images (don't generate 2000px from 1000px source)
- ✅ Never send both AVIF and WebP (browser picks one via `<picture>`)
- ✅ Cap max width to container + 2x DPR
- ✅ Use tight `sizes` attribute (don't guess "100vw")
- ✅ Always include `decoding="async"` (non-blocking decode)
- ✅ Reserve `fetchpriority="high"` for 1-2 hero images only

**Example Perfect Markup:**
```html
<picture>
  <source type="image/avif"
          srcset="hero-640w.avif 640w,
                  hero-960w.avif 960w,
                  hero-1280w.avif 1280w,
                  hero-1920w.avif 1920w"
          sizes="(max-width: 640px) 100vw,
                 (max-width: 1024px) 90vw,
                 1280px">

  <source type="image/webp"
          srcset="hero-640w.webp 640w,
                  hero-960w.webp 960w,
                  hero-1280w.webp 1280w,
                  hero-1920w.webp 1920w"
          sizes="(max-width: 640px) 100vw,
                 (max-width: 1024px) 90vw,
                 1280px">

  <img src="hero-1280w.jpg"
       srcset="hero-640w.jpg 640w,
               hero-960w.jpg 960w,
               hero-1280w.jpg 1280w,
               hero-1920w.jpg 1920w"
       sizes="(max-width: 640px) 100vw,
              (max-width: 1024px) 90vw,
              1280px"
       alt="Hero image"
       fetchpriority="high"
       loading="eager"
       decoding="async"
       width="1280"
       height="720">
</picture>
```

---

#### CDN & Caching Strategy

**TTL Strategy:**
- Image variants: `Cache-Control: public, max-age=31536000, immutable` (1 year)
- Priority manifest: `Cache-Control: public, max-age=3600` (1 hour)
- HTML pages: `Cache-Control: public, max-age=300` (5 minutes)

**CDN Integration:**
```php
// Optional: Purge CDN cache when image updated
add_action( 'attachment_updated', function( $post_id ) {
    // Purge Cloudflare cache
    if ( defined( 'CLOUDFLARE_API_KEY' ) ) {
        $url = wp_get_attachment_url( $post_id );
        msh_purge_cloudflare_cache( $url );
    }

    // Invalidate priority manifest
    delete_transient( 'msh_priority_manifest_' . get_post( $post_id )->post_parent );
});
```

---

### 5.6 Integration with Existing Phases

#### Connects to Phase 2 (Context Fusion)

**Uses Context Data:**
- `context_score` (0-100) → Priority scoring
- `usage_type` (featured, inline, gallery) → Position weight
- `block_path` → Above-fold detection
- `intent` (on_topic, off_topic) → Semantic weight

**Example Query:**
```sql
SELECT
    c.media_id,
    c.context_score,
    c.usage_type,
    c.block_path,
    c.intent
FROM wp_msh_optimizer_context c
WHERE c.post_id = %d
ORDER BY c.context_score DESC;
```

---

#### Connects to Phase 8 (Analytics)

**Telemetry Integration:**
- Track LCP element per page
- Measure priority loader impact
- Compare scores vs actual LCP times
- Validate AI scoring accuracy

**Feedback Loop:**
```php
// Adjust scoring based on real LCP data
if ( $lcp_element_score < 0.8 && $lcp_time > 2500 ) {
    // This image should have been scored higher
    $this->adjust_scoring_weights( $attachment_id, 'increase_priority' );
}
```

---

#### Connects to Idea #3 (AVIF Conversion)

**Format Selection:**
- Use AVIF for high-priority images (best compression)
- Use WebP for mid-priority (good compression, faster encode)
- Use JPEG for low-priority (universal fallback)

**Example Logic:**
```php
public function recommend_format( $attachment_id, $priority_score ) {
    if ( $priority_score > 0.8 && $this->avif_available( $attachment_id ) ) {
        return 'avif'; // Hero images get best format
    } elseif ( $priority_score > 0.5 && $this->webp_available( $attachment_id ) ) {
        return 'webp'; // Mid-priority images
    } else {
        return 'jpeg'; // Low-priority or fallback
    }
}
```

---

#### Connects to Idea #4 (Staged Cloud)

**Cloud API Endpoints:**
- `POST /v1/variants` - Generate responsive variants
- `GET /v1/manifest/{post_id}` - Fetch priority manifest
- `POST /v1/telemetry` - Send LCP/performance data

**Stage Integration:**
- **Stage 1:** Picture tag + priority hints (local processing)
- **Stage 2-3:** Variant generation in cloud
- **Stage 4:** AI priority scoring + manifest
- **Stage 5:** Custom lazy loader rollout

---

### 5.7 Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| **Storage costs 1.5x** | $$$$ | Gate on 3K+ installs, offer free tier with 3 formats only |
| **Variant generation slow** | UX | Queue in background, show progress, cache aggressively |
| **Priority scoring wrong** | UX | Start conservative (95%+ accuracy), improve via Phase 8 telemetry |
| **Breaking existing lazy load** | UX | Feature flag rollout, extensive testing, fallback to WP core |
| **CDN bandwidth costs** | $$$$ | Partner with BunnyCDN/Cloudflare, offer caching guidance |
| **Browser compatibility** | UX | Always provide JPEG fallback, test extensively |

---

### 5.8 Competitive Analysis

**Plugins with Responsive Variants:**
- ✅ **ShortPixel:** Responsive variants, AVIF/WebP, but NO AI scoring
- ✅ **Imagify:** Responsive variants, WebP only (no AVIF), NO AI scoring
- ✅ **EWWW:** Basic WebP, NO responsive variants, NO AI scoring
- ❌ **WP Rocket:** Lazy load only, NO format conversion

**Our Competitive Advantage:**
- ✅ **AI priority scoring** (unique - uses Context Fusion data)
- ✅ **Context-aware loading** (on_topic images load first)
- ✅ **Integrated with metadata** (Phase 2 context, Phase 4 versioning)
- ✅ **Phase 8 telemetry feedback loop** (self-improving scores)

**No other plugin combines:**
1. Responsive variants (AVIF/WebP/JPEG)
2. AI-based priority scoring
3. Context Fusion data (on_topic vs off_topic)
4. Telemetry-driven optimization

---

### 5.9 Action Plan & Timeline

#### Phase 10 Stage 1 (Days 1-30) - Quick Wins ✅

**Tasks:**
1. ✅ Implement `render_picture_tag()` method (2-3 hours)
2. ✅ Add priority hints filter (1-2 hours)
3. ✅ Test with AVIF partner (ImageKit integration)
4. ✅ Feature flag: `msh_feature_picture_tag_delivery`
5. ✅ Documentation: Quick start guide

**Deliverables:**
- `<picture>` tag support for AVIF/WebP/JPEG
- `fetchpriority="high"` for hero images
- 100-300ms LCP improvement (low-hanging fruit)

**Dev Time:** 3-5 hours total

---

#### Phase 10 Stage 4 (Months 4-6) - Responsive Variants

**Prerequisites:**
- ✅ 1,500+ active installs
- ✅ Cloud infrastructure stable (Stage 2-3)
- ✅ Storage budget approved

**Tasks:**
1. Design variant generation API (1 week)
2. Build `MSH_Variant_Generator` class (1 week)
3. Create `wp_msh_image_variants` table (2 days)
4. Implement WP-CLI commands (3 days)
5. Testing & rollout (1 week)

**Deliverables:**
- 18 variants per image (3 formats × 6 sizes)
- Background job queue for generation
- WP-CLI bulk commands
- Feature flag: `msh_feature_responsive_variants`

**Dev Time:** 3-4 weeks

---

#### Phase 10 Stage 5 (Months 6-9) - AI Priority Scoring

**Prerequisites:**
- ✅ Phase 2 Context Fusion complete
- ✅ Responsive variants live (Stage 4)
- ✅ Phase 8 Analytics collecting LCP data

**Tasks:**
1. Build `MSH_Priority_Manifest_Generator` class (2 weeks)
2. Implement scoring algorithm (1 week)
3. Create REST API endpoints (3 days)
4. Telemetry integration (Phase 8) (1 week)
5. Testing & validation (1 week)

**Deliverables:**
- Priority manifest per page
- AI scoring (position + semantic + context + usage)
- REST API: `/wp-json/msh/v1/manifest/{post_id}`
- Feature flag: `msh_feature_priority_manifest`

**Dev Time:** 4-5 weeks

---

#### Phase 10 Stage 6 (Months 9-12) - Custom Lazy Loader

**Prerequisites:**
- ✅ Priority manifest system (Stage 5)
- ✅ Phase 8 Analytics tracking LCP
- ✅ Proven demand (3K+ installs)

**Tasks:**
1. Build JavaScript priority loader (2 weeks)
2. Intersection observer setup (3 days)
3. LCP tracking integration (1 week)
4. Performance testing (1 week)
5. Gradual rollout (1-2 months)

**Deliverables:**
- Custom lazy loader with priority queue
- LCP element tracking
- Telemetry feedback loop
- Feature flag: `msh_feature_priority_loader`

**Dev Time:** 5-6 weeks + 1-2 months gradual rollout

---

### 5.10 Success Metrics

#### Stage 1 Quick Wins (Days 1-30)

| Metric | Target |
|--------|--------|
| LCP improvement (hero images) | 100-300ms faster |
| `<picture>` tag adoption | 80%+ of themes |
| AVIF delivery (when available) | 70%+ of requests |
| No breaking changes | 0 support tickets |

---

#### Stage 4-6 Advanced Features (Months 4-12)

| Metric | Target |
|--------|--------|
| Average LCP | <2.5s (Google "Good" threshold) |
| Storage increase | <1.5x (mitigated by compression) |
| Variant generation time | <10s per image (background) |
| Priority scoring accuracy | 95%+ (validated by Phase 8 LCP data) |
| CDN bandwidth savings | 30-50% (smaller formats + right-size) |
| User satisfaction | <1% increase in support tickets |

---

### 5.11 References

**External:**
- [Web.dev: Optimize LCP](https://web.dev/optimize-lcp/)
- [Jake Archibald: AVIF Has Landed](https://jakearchibald.com/2020/avif-has-landed/)
- [Google: Priority Hints](https://web.dev/priority-hints/)
- [MDN: Responsive Images](https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images)
- [Smashing Magazine: Responsive Image Breakpoints](https://www.smashingmagazine.com/2018/02/responsive-image-breakpoints/)

**Internal:**
- [TODO.md](../TODO.md) - Phase 10 roadmap
- [PROJECT-STATUS-ALL-PHASES.md](../PROJECT-STATUS-ALL-PHASES.md) - Phase 10 status
- [Idea #2: Feature Flags](#idea-2-feature-flags-for-safe-rollouts) - Rollout strategy
- [Idea #3: AVIF Conversion](#idea-3-avif-image-conversion) - Format strategy
- [Idea #4: Staged Cloud Architecture](#idea-4-staged-cloud-architecture-30-60-90-day-roadmap) - Infrastructure
- [Phase 2: Context Fusion](../docs/PHASE_2_CONTEXT_FUSION_PLAN.md) - Context scoring
- [Phase 8: Analytics](../TODO.md#phase-8--visibility--analytics) - LCP telemetry

---

### Conclusion (Idea #5)

AI-powered image delivery optimization is the **natural evolution** of Phase 10's AVIF strategy. By leveraging Phase 2 Context Fusion data, we can build the **only WordPress plugin** that uses AI to optimize image load order based on semantic relevance and user context.

**Why This Wins:**
- ✅ **Unique competitive advantage** (AI priority scoring)
- ✅ **Leverages existing data** (Context Fusion already built)
- ✅ **Proven demand** (LCP optimization is top SEO request)
- ✅ **Staged rollout** (quick wins first, advanced features later)
- ✅ **Self-improving** (Phase 8 telemetry feedback loop)

**Why Not Now:**
- ⏸️ **Requires cloud infrastructure** (Stage 2-3 prerequisite)
- ⏸️ **Storage costs 1.5x** (need revenue model proven)
- ⏸️ **Complex implementation** (3-6 months dev time)
- ⏸️ **WordPress core "good enough"** for MVP (loading="lazy")

**Immediate Actions:**
1. ✅ **Stage 1 Quick Wins** (3-5 hours): Picture tag + priority hints
2. ⏸️ **Stage 4-6 Advanced** (months 4-12): Responsive variants + AI scoring + custom loader

**Gates:**
- **Stage 4:** 1,500+ installs + cloud stable
- **Stage 5:** 2,500+ installs + Phase 8 analytics live
- **Stage 6:** 3,000+ installs + proven LCP improvement demand

**Status:** 📋 Future Research - Document now, implement Stage 1 with AVIF rollout, evaluate Stage 4+ after 90 days.

---
---

## Idea #6: Per-Locale Image Sitemaps with Hreflang

**Source:** User-provided article on multilingual SEO best practices
**Priority:** 🟡 Medium (Phase 7 enhancement, SEO improvement)
**Related Phases:** Phase 7 (Multilingual Admin UX), Phase 4 (Metadata Versioning), Phase 2 (Context Fusion)
**Status:** ✅ Approved for Phase 7 - Perfect fit with existing multilingual architecture

### Quick Summary

Generate per-locale image sitemaps (`/sitemap-images-en.xml`, `/sitemap-images-fr.xml`) with hreflang support, leveraging Phase 4 localized metadata and Phase 2 Context Fusion on_topic filtering. This helps Google discover and properly index localized image metadata, preventing duplicate content issues while improving multilingual SEO.

**✅ PERFECT FIT:** We already have all the data needed (Phase 4 metadata per locale, Phase 2 context per locale)
**✅ WORDPRESS-NATIVE:** Integrates with Polylang, WPML, and core sitemap system
**✅ AGENCY VALUE:** Automated multilingual SEO improvement, no manual work

**Key Innovation:** Only WordPress plugin that generates per-locale image sitemaps filtered by AI context relevance (on_topic vs off_topic).

---

### 6.1 What Image Sitemaps + Hreflang Solve

#### Problem 1: Google Doesn't Know Image Metadata Per Language

**Without image sitemaps:**
- Google crawls image files (JPG, PNG) but doesn't know localized metadata
- Alt text, title, caption in French pages not associated with images
- Image search results show wrong language metadata

**With per-locale image sitemaps:**
- Explicit mapping: "This image on FR page has FR title/caption"
- Google indexes localized metadata separately
- Image search results show correct language

---

#### Problem 2: Duplicate Content Issues Across Locales

**Without hreflang:**
- Google sees same image on EN + FR pages as duplicate
- May only index one language version
- Image search traffic goes to wrong locale

**With hreflang in sitemaps:**
- Tells Google: "EN page and FR page are language variants, not duplicates"
- Google indexes both, serves correct locale to users
- Image search traffic correctly distributed by language

---

#### Problem 3: Manual Sitemap Maintenance

**Without automation:**
- Agency manually creates image sitemaps per client
- Outdated when content changes
- Error-prone (wrong locale, missing images)

**With automated generation:**
- Plugin generates sitemaps automatically
- Updates on post save / metadata regeneration
- Always accurate, zero maintenance

---

### 6.2 Architecture & Data Flow

#### We Already Have All the Data

**Phase 4 Metadata Versioning:**
```sql
-- wp_optimizer_metadata_versions table
SELECT
    media_id,
    locale,  -- 'en', 'fr', 'es', etc.
    field,   -- 'title', 'alt', 'caption', 'description'
    value,   -- Localized metadata value
    is_active
FROM wp_optimizer_metadata_versions
WHERE locale = 'en'
  AND field IN ('title', 'caption')
  AND is_active = 1;
```

**Phase 2 Context Fusion:**
```sql
-- wp_msh_optimizer_context table
SELECT
    media_id,
    post_id,
    locale,        -- Which locale this context is for
    intent,        -- 'on_topic' or 'off_topic'
    subject,       -- "Physiotherapy in Toronto"
    keywords       -- ['physiotherapy', 'toronto', 'treatment']
FROM wp_msh_optimizer_context
WHERE locale = 'en'
  AND intent = 'on_topic';  -- Only include relevant images
```

**Result:** We can generate sitemaps that:
- ✅ Use Phase 4 localized title/caption per locale
- ✅ Filter to Phase 2 on_topic images only (no decorative clutter)
- ✅ Map images to correct posts per locale
- ✅ Respect multilingual plugin URL structure (Polylang/WPML)

---

### 6.3 Sitemap Structure

#### Sitemap Index (Master File)

**File:** `/sitemap-index.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <!-- Page sitemaps per locale -->
  <sitemap>
    <loc>https://example.com/sitemap-en.xml</loc>
    <lastmod>2025-10-22T12:00:00Z</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-fr.xml</loc>
    <lastmod>2025-10-22T12:00:00Z</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-es.xml</loc>
    <lastmod>2025-10-22T12:00:00Z</lastmod>
  </sitemap>

  <!-- Image sitemaps per locale -->
  <sitemap>
    <loc>https://example.com/sitemap-images-en.xml</loc>
    <lastmod>2025-10-22T12:00:00Z</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-images-fr.xml</loc>
    <lastmod>2025-10-22T12:00:00Z</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-images-es.xml</loc>
    <lastmod>2025-10-22T12:00:00Z</lastmod>
  </sitemap>
</sitemapindex>
```

---

#### Page Sitemap with Hreflang

**File:** `/sitemap-en.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://example.com/en/services/physiotherapy/</loc>
    <lastmod>2025-10-22</lastmod>

    <!-- Hreflang alternates -->
    <xhtml:link rel="alternate" hreflang="en"
                href="https://example.com/en/services/physiotherapy/"/>
    <xhtml:link rel="alternate" hreflang="fr"
                href="https://example.com/fr/services/physiotherapie/"/>
    <xhtml:link rel="alternate" hreflang="es"
                href="https://example.com/es/servicios/fisioterapia/"/>
    <xhtml:link rel="alternate" hreflang="x-default"
                href="https://example.com/"/>
  </url>
</urlset>
```

**Why hreflang in sitemaps:**
- Google officially supports hreflang in sitemaps (not just HTML)
- Easier to maintain than adding to every page's `<head>`
- Centralized (one place to update)

---

#### Image Sitemap Per Locale

**File:** `/sitemap-images-en.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Each <url> = one page with its images -->
  <url>
    <loc>https://example.com/en/services/physiotherapy/</loc>

    <!-- Image 1: Hero image (on_topic) -->
    <image:image>
      <image:loc>https://example.com/wp-content/uploads/2025/10/physio-hero.jpg</image:loc>
      <image:title>Physiotherapy Services at Main Street Health</image:title>
      <image:caption>Expert physiotherapy treatment in Toronto</image:caption>
    </image:image>

    <!-- Image 2: Treatment room (on_topic) -->
    <image:image>
      <image:loc>https://example.com/wp-content/uploads/2025/10/treatment-room.jpg</image:loc>
      <image:title>Modern Physiotherapy Treatment Room</image:title>
      <image:caption>State-of-the-art equipment for rehabilitation</image:caption>
    </image:image>

    <!-- NOTE: Decorative images (off_topic) excluded via Context Fusion filter -->
  </url>

  <url>
    <loc>https://example.com/en/about/team/</loc>

    <image:image>
      <image:loc>https://example.com/wp-content/uploads/2025/10/team-photo.jpg</image:loc>
      <image:title>Main Street Health Team</image:title>
      <image:caption>Our experienced physiotherapy team</image:caption>
    </image:image>
  </url>
</urlset>
```

**Key Rules:**
- ✅ List images under the page that embeds them (not standalone)
- ✅ Up to 1,000 images per `<url>` entry (Google limit)
- ✅ Title and caption in page's language (from Phase 4 metadata)
- ✅ Only on_topic images (filtered by Phase 2 Context Fusion)

---

**File:** `/sitemap-images-fr.xml` (French version)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <url>
    <loc>https://example.com/fr/services/physiotherapie/</loc>

    <image:image>
      <image:loc>https://example.com/wp-content/uploads/2025/10/physio-hero.jpg</image:loc>
      <image:title>Services de physiothérapie à Main Street Health</image:title>
      <image:caption>Traitement de physiothérapie expert à Toronto</image:caption>
    </image:image>

    <image:image>
      <image:loc>https://example.com/wp-content/uploads/2025/10/treatment-room.jpg</image:loc>
      <image:title>Salle de traitement de physiothérapie moderne</image:title>
      <image:caption>Équipement de pointe pour la réadaptation</image:caption>
    </image:image>
  </url>
</urlset>
```

**Notice:**
- Same image URLs (`physio-hero.jpg`, `treatment-room.jpg`)
- Different localized metadata (French title/caption from Phase 4)
- Different page URL (`/fr/services/physiotherapie/` vs `/en/services/physiotherapy/`)

---

### 6.4 Implementation

#### File: `includes/class-msh-sitemap-generator.php`

```php
<?php
/**
 * MSH Image Sitemap Generator
 *
 * Generates per-locale image sitemaps with hreflang support.
 * Leverages Phase 4 metadata versioning and Phase 2 Context Fusion.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSH_Sitemap_Generator {

    /**
     * Initialize sitemap generator.
     */
    public function __construct() {
        // Register custom sitemap provider
        add_action( 'init', [ $this, 'register_sitemap_provider' ] );

        // Add hreflang to core page sitemaps
        add_filter( 'wp_sitemaps_posts_entry', [ $this, 'add_hreflang_to_entry' ], 10, 2 );
    }

    /**
     * Register custom image sitemap provider.
     */
    public function register_sitemap_provider() {
        $provider = new MSH_Image_Sitemap_Provider();
        wp_register_sitemap_provider( 'msh-images', $provider );
    }

    /**
     * Add hreflang alternates to page sitemap entries.
     *
     * @param array   $entry Sitemap entry
     * @param WP_Post $post  Post object
     * @return array Modified entry
     */
    public function add_hreflang_to_entry( $entry, $post ) {
        $locales = $this->get_active_locales();
        $alternates = [];

        foreach ( $locales as $locale ) {
            $translated_url = $this->get_translated_post_url( $post->ID, $locale );

            if ( $translated_url ) {
                $alternates[] = [
                    'hreflang' => $locale,
                    'href' => $translated_url,
                ];
            }
        }

        // Add x-default (global entry point)
        $alternates[] = [
            'hreflang' => 'x-default',
            'href' => home_url( '/' ),
        ];

        $entry['alternates'] = $alternates;

        return $entry;
    }

    /**
     * Get translated post URL (Polylang/WPML compatible).
     *
     * @param int    $post_id Post ID
     * @param string $locale  Locale code
     * @return string|false Translated URL or false
     */
    private function get_translated_post_url( $post_id, $locale ) {
        // Polylang support
        if ( function_exists( 'pll_get_post' ) ) {
            $translated_id = pll_get_post( $post_id, $locale );
            if ( $translated_id ) {
                return get_permalink( $translated_id );
            }
        }

        // WPML support
        if ( function_exists( 'wpml_object_id_filter' ) ) {
            $translated_id = wpml_object_id_filter( $post_id, 'post', false, $locale );
            if ( $translated_id ) {
                return get_permalink( $translated_id );
            }
        }

        // Fallback: same post
        return get_permalink( $post_id );
    }

    /**
     * Get active locales from multilingual plugin.
     *
     * @return array Locale codes (e.g., ['en', 'fr', 'es'])
     */
    private function get_active_locales() {
        // Polylang
        if ( function_exists( 'pll_languages_list' ) ) {
            return pll_languages_list();
        }

        // WPML
        if ( function_exists( 'icl_get_languages' ) ) {
            $languages = icl_get_languages( 'skip_missing=0' );
            return array_keys( $languages );
        }

        // Fallback: site locale only
        return [ get_locale() ];
    }
}
```

---

#### File: `includes/class-msh-image-sitemap-provider.php`

```php
<?php
/**
 * MSH Image Sitemap Provider
 *
 * Custom sitemap provider for per-locale image sitemaps.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSH_Image_Sitemap_Provider extends WP_Sitemaps_Provider {

    /**
     * Provider name.
     *
     * @var string
     */
    protected $name = 'msh-images';

    /**
     * Object type.
     *
     * @var string
     */
    protected $object_type = 'msh_image';

    /**
     * Get sitemap entries (per-locale image sitemaps).
     *
     * @return array List of sitemap entries
     */
    public function get_sitemap_entries() {
        $entries = [];
        $locales = $this->get_active_locales();

        foreach ( $locales as $locale ) {
            $entries[] = [
                'loc' => home_url( "/sitemap-images-{$locale}.xml" ),
            ];
        }

        return $entries;
    }

    /**
     * Get URL list for a specific locale sitemap.
     *
     * @param int    $page_num  Page number
     * @param string $object_subtype Locale code
     * @return array URL entries
     */
    public function get_url_list( $page_num, $object_subtype = '' ) {
        $locale = $object_subtype;

        if ( empty( $locale ) ) {
            return [];
        }

        return $this->get_image_urls_for_locale( $locale );
    }

    /**
     * Get image URLs for a specific locale.
     *
     * @param string $locale Locale code
     * @return array URL entries
     */
    private function get_image_urls_for_locale( $locale ) {
        global $wpdb;

        $urls = [];

        // Get posts for this locale
        $posts = $this->get_posts_by_locale( $locale );

        foreach ( $posts as $post ) {
            $post_url = get_permalink( $post->ID );

            // Get on_topic images for this post + locale (Phase 2 Context Fusion)
            $images = $wpdb->get_results( $wpdb->prepare(
                "SELECT DISTINCT c.media_id
                 FROM {$wpdb->prefix}msh_optimizer_context c
                 WHERE c.post_id = %d
                   AND c.locale = %s
                   AND c.intent = 'on_topic'
                 LIMIT 1000",  -- Google limit per page
                $post->ID,
                $locale
            ));

            if ( empty( $images ) ) {
                continue;
            }

            $image_entries = [];

            foreach ( $images as $image ) {
                $entry = $this->generate_image_entry( $image->media_id, $locale );

                if ( $entry ) {
                    $image_entries[] = $entry;
                }
            }

            if ( ! empty( $image_entries ) ) {
                $urls[] = [
                    'loc' => $post_url,
                    'images' => $image_entries,
                ];
            }
        }

        return $urls;
    }

    /**
     * Generate single image entry with localized metadata.
     *
     * @param int    $media_id Attachment ID
     * @param string $locale   Locale code
     * @return array|false Image entry or false
     */
    private function generate_image_entry( $media_id, $locale ) {
        global $wpdb;

        // Get localized metadata from Phase 4
        $metadata = $wpdb->get_results( $wpdb->prepare(
            "SELECT field, value
             FROM {$wpdb->prefix}optimizer_metadata_versions
             WHERE media_id = %d
               AND locale = %s
               AND field IN ('title', 'caption')
               AND is_active = 1",
            $media_id,
            $locale
        ), OBJECT_K );

        $image_url = wp_get_attachment_url( $media_id );

        if ( ! $image_url ) {
            return false;
        }

        $title = $metadata['title']->value ?? get_the_title( $media_id );
        $caption = $metadata['caption']->value ?? '';

        return [
            'loc' => $image_url,
            'title' => $title,
            'caption' => $caption,
        ];
    }

    /**
     * Get posts by locale (Polylang/WPML compatible).
     *
     * @param string $locale Locale code
     * @return array Post objects
     */
    private function get_posts_by_locale( $locale ) {
        // Polylang support
        if ( function_exists( 'pll_get_posts_not_translated' ) ) {
            return get_posts([
                'numberposts' => -1,
                'post_status' => 'publish',
                'lang' => $locale,
            ]);
        }

        // WPML support
        if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            global $sitepress;
            $sitepress->switch_lang( $locale );

            $posts = get_posts([
                'numberposts' => -1,
                'post_status' => 'publish',
            ]);

            $sitepress->switch_lang( ICL_LANGUAGE_CODE );

            return $posts;
        }

        // Fallback: all posts
        return get_posts([
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);
    }

    /**
     * Get active locales.
     *
     * @return array Locale codes
     */
    private function get_active_locales() {
        // Polylang
        if ( function_exists( 'pll_languages_list' ) ) {
            return pll_languages_list();
        }

        // WPML
        if ( function_exists( 'icl_get_languages' ) ) {
            $languages = icl_get_languages( 'skip_missing=0' );
            return array_keys( $languages );
        }

        // Fallback
        return [ get_locale() ];
    }

    /**
     * Get max number of pages.
     *
     * @param string $object_subtype Locale code
     * @return int Max pages
     */
    public function get_max_num_pages( $object_subtype = '' ) {
        // Single page per locale (WordPress core handles pagination)
        return 1;
    }
}
```

---

### 6.5 WP-CLI Commands

#### File: `includes/class-msh-sitemap-cli.php`

```php
<?php
/**
 * MSH Sitemap WP-CLI Commands
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSH_Sitemap_CLI {

    /**
     * Generate image sitemaps.
     *
     * ## OPTIONS
     *
     * [--locale=<locale>]
     * : Generate for specific locale only
     * ---
     * default: all
     * ---
     *
     * [--type=<type>]
     * : Sitemap type (images, pages, all)
     * ---
     * default: all
     * options:
     *   - images
     *   - pages
     *   - all
     * ---
     *
     * [--submit]
     * : Submit to Google Search Console after generation
     *
     * ## EXAMPLES
     *
     *     # Generate all sitemaps
     *     wp msh sitemap generate
     *
     *     # Generate English image sitemap only
     *     wp msh sitemap generate --locale=en --type=images
     *
     *     # Generate and submit to Search Console
     *     wp msh sitemap generate --submit
     *
     * @param array $args       Positional arguments
     * @param array $assoc_args Named arguments
     */
    public function generate( $args, $assoc_args ) {
        $locale = $assoc_args['locale'] ?? 'all';
        $type = $assoc_args['type'] ?? 'all';
        $submit = isset( $assoc_args['submit'] );

        $generator = new MSH_Sitemap_Generator();

        if ( $locale === 'all' ) {
            $locales = $generator->get_active_locales();
        } else {
            $locales = [ $locale ];
        }

        WP_CLI::line( sprintf( 'Generating sitemaps for %d locale(s)...', count( $locales ) ) );

        foreach ( $locales as $loc ) {
            if ( $type === 'images' || $type === 'all' ) {
                $this->generate_image_sitemap( $loc );
            }

            if ( $type === 'pages' || $type === 'all' ) {
                $this->generate_page_sitemap( $loc );
            }
        }

        // Generate sitemap index
        $this->generate_sitemap_index();

        WP_CLI::success( 'Sitemap generation complete!' );

        // Submit to Search Console if requested
        if ( $submit ) {
            $this->submit_to_search_console();
        }
    }

    /**
     * Generate image sitemap for locale.
     *
     * @param string $locale Locale code
     */
    private function generate_image_sitemap( $locale ) {
        $provider = new MSH_Image_Sitemap_Provider();
        $urls = $provider->get_url_list( 1, $locale );

        WP_CLI::line( sprintf(
            '  → Generated sitemap-images-%s.xml (%d pages, %d images)',
            $locale,
            count( $urls ),
            $this->count_total_images( $urls )
        ) );
    }

    /**
     * Generate page sitemap for locale.
     *
     * @param string $locale Locale code
     */
    private function generate_page_sitemap( $locale ) {
        // WordPress core handles page sitemaps
        // We just add hreflang via filter
        WP_CLI::line( sprintf( '  → Generated sitemap-%s.xml (with hreflang)', $locale ) );
    }

    /**
     * Generate sitemap index.
     */
    private function generate_sitemap_index() {
        wp_sitemaps_get_server()->registry->generate_sitemap_index();
        WP_CLI::line( '  → Generated sitemap-index.xml' );
    }

    /**
     * Submit sitemap to Google Search Console.
     */
    private function submit_to_search_console() {
        $sitemap_url = home_url( '/sitemap-index.xml' );

        WP_CLI::line( "\nSubmitting to Google Search Console..." );
        WP_CLI::line( sprintf( 'Sitemap URL: %s', $sitemap_url ) );

        // Ping Google
        $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url );
        $response = wp_remote_get( $ping_url );

        if ( is_wp_error( $response ) ) {
            WP_CLI::warning( 'Failed to ping Google: ' . $response->get_error_message() );
        } else {
            WP_CLI::success( 'Sitemap submitted to Google!' );
        }
    }

    /**
     * Count total images in sitemap URLs.
     *
     * @param array $urls URL entries
     * @return int Total image count
     */
    private function count_total_images( $urls ) {
        $count = 0;

        foreach ( $urls as $url ) {
            if ( isset( $url['images'] ) ) {
                $count += count( $url['images'] );
            }
        }

        return $count;
    }
}

// Register WP-CLI command
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'msh sitemap', 'MSH_Sitemap_CLI' );
}
```

**Usage Examples:**

```bash
# Generate all sitemaps (all locales)
wp msh sitemap generate

# Generate English image sitemap only
wp msh sitemap generate --locale=en --type=images

# Generate French sitemaps (images + pages)
wp msh sitemap generate --locale=fr

# Generate all sitemaps and submit to Search Console
wp msh sitemap generate --submit

# Cron job: Regenerate sitemaps daily
0 2 * * * cd /var/www/html && wp msh sitemap generate --submit
```

---

### 6.6 Automatic Regeneration Triggers

**File:** `includes/automation/class-msh-sitemap-triggers.php`

```php
<?php
/**
 * MSH Sitemap Automatic Regeneration Triggers
 *
 * Regenerate sitemaps when content changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MSH_Sitemap_Triggers {

    /**
     * Initialize triggers.
     */
    public function __construct() {
        // Regenerate when post published/updated
        add_action( 'save_post', [ $this, 'schedule_sitemap_regeneration' ], 10, 2 );

        // Regenerate when metadata updated (Phase 4)
        add_action( 'msh_metadata_updated', [ $this, 'schedule_sitemap_regeneration_for_attachment' ] );

        // Regenerate when context extracted (Phase 2)
        add_action( 'msh_context_extracted', [ $this, 'schedule_sitemap_regeneration_for_post' ] );
    }

    /**
     * Schedule sitemap regeneration after post save.
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     */
    public function schedule_sitemap_regeneration( $post_id, $post ) {
        // Only for published posts
        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Debounce: Only schedule once per minute
        $transient_key = 'msh_sitemap_regen_scheduled';

        if ( get_transient( $transient_key ) ) {
            return; // Already scheduled
        }

        // Schedule background regeneration
        wp_schedule_single_event( time() + 60, 'msh_regenerate_sitemaps' );

        // Set debounce transient (1 minute)
        set_transient( $transient_key, true, MINUTE_IN_SECONDS );
    }

    /**
     * Schedule sitemap regeneration for attachment.
     *
     * @param int $media_id Attachment ID
     */
    public function schedule_sitemap_regeneration_for_attachment( $media_id ) {
        $this->schedule_sitemap_regeneration( $media_id, get_post( $media_id ) );
    }

    /**
     * Schedule sitemap regeneration for post.
     *
     * @param int $post_id Post ID
     */
    public function schedule_sitemap_regeneration_for_post( $post_id ) {
        $this->schedule_sitemap_regeneration( $post_id, get_post( $post_id ) );
    }
}

/**
 * Background cron job to regenerate sitemaps.
 */
add_action( 'msh_regenerate_sitemaps', function() {
    $generator = new MSH_Sitemap_Generator();

    // Regenerate all locales
    foreach ( $generator->get_active_locales() as $locale ) {
        $provider = new MSH_Image_Sitemap_Provider();
        $provider->get_url_list( 1, $locale );
    }

    // Regenerate index
    wp_sitemaps_get_server()->registry->generate_sitemap_index();
});
```

**Triggers:**
- ✅ Post published/updated → Regenerate sitemap (1 minute debounce)
- ✅ Metadata updated (Phase 4) → Regenerate sitemap
- ✅ Context extracted (Phase 2) → Regenerate sitemap
- ✅ Manual WP-CLI command → Immediate regeneration

---

### 6.7 Integration with Existing Phases

#### Connects to Phase 4 (Metadata Versioning)

**Uses Metadata:**
```php
// Get localized title and caption per locale
SELECT field, value
FROM wp_optimizer_metadata_versions
WHERE media_id = 123
  AND locale = 'fr'
  AND field IN ('title', 'caption')
  AND is_active = 1;
```

**Result:**
- `<image:title>` = Phase 4 localized title
- `<image:caption>` = Phase 4 localized caption
- Automatic updates when metadata regenerated

---

#### Connects to Phase 2 (Context Fusion)

**Uses Context Filtering:**
```php
// Only include on_topic images (no decorative clutter)
SELECT DISTINCT media_id
FROM wp_msh_optimizer_context
WHERE post_id = 567
  AND locale = 'en'
  AND intent = 'on_topic';  -- KEY FILTER
```

**Result:**
- Sitemaps only include relevant images
- No decorative icons, backgrounds, or off-topic images
- Higher quality signal to Google

---

#### Connects to Multilingual Plugins

**Polylang Integration:**
```php
// Get posts by language
$posts = get_posts([
    'lang' => 'fr',
    'post_status' => 'publish',
]);

// Get translated post ID
$fr_post_id = pll_get_post( $en_post_id, 'fr' );
```

**WPML Integration:**
```php
// Switch language context
global $sitepress;
$sitepress->switch_lang( 'fr' );

// Get translated post ID
$fr_post_id = wpml_object_id_filter( $en_post_id, 'post', false, 'fr' );
```

---

### 6.8 SEO Benefits

#### Benefit 1: Localized Image Search Results

**Before (no image sitemaps):**
- User searches "physiothérapie Toronto" in google.fr/images
- Google shows English alt text for FR page images
- User clicks, finds French page, but metadata was confusing

**After (with per-locale sitemaps):**
- Google indexes FR image sitemap separately
- FR title/caption associated with FR page
- User sees "Physiothérapie à Toronto" in image search
- Better relevance = higher CTR

---

#### Benefit 2: No Duplicate Content Penalties

**Before (no hreflang):**
- Same image on EN + FR pages
- Google treats as duplicate, may only index one
- FR page loses image search traffic

**After (with hreflang):**
- Hreflang tells Google: "These are language variants"
- Google indexes both, serves correct locale
- FR traffic → FR page, EN traffic → EN page

---

#### Benefit 3: Faster Indexing

**Before (no sitemaps):**
- Google discovers images by crawling pages
- May take weeks for new images to appear in search
- Deep pages may not be crawled frequently

**After (with sitemaps):**
- Google fetches sitemap immediately
- New images indexed within days
- All images discovered, even on deep pages

---

### 6.9 Competitive Analysis

**WordPress Plugins with Sitemap Support:**
- ✅ **Yoast SEO:** Generates image sitemaps, but NO per-locale separation
- ✅ **Rank Math:** Generates image sitemaps, but NO hreflang in sitemaps
- ✅ **WPML:** Hreflang in HTML, but NO per-locale image sitemaps
- ❌ **Polylang:** NO built-in image sitemap generation

**Our Competitive Advantage:**
- ✅ **Per-locale image sitemaps** (unique - no plugin does this)
- ✅ **Hreflang in sitemaps** (easier than HTML `<head>`)
- ✅ **AI context filtering** (only on_topic images via Phase 2)
- ✅ **Localized metadata from Phase 4** (automatically updated)
- ✅ **Automatic regeneration** (no manual maintenance)

**No other plugin combines:**
1. Per-locale image sitemaps
2. AI context filtering (on_topic vs off_topic)
3. Automated localized metadata (Phase 4 integration)
4. Polylang + WPML compatibility

---

### 6.10 Implementation Checklist

#### Phase 7 TODO (1 Week Dev Time)

**Day 1-2: Core Sitemap Generator**
- [ ] Create `class-msh-sitemap-generator.php`
- [ ] Create `class-msh-image-sitemap-provider.php`
- [ ] Integrate with WordPress core sitemap system
- [ ] Test sitemap index generation

**Day 3-4: Hreflang Integration**
- [ ] Add hreflang to page sitemap entries
- [ ] Test Polylang URL translation
- [ ] Test WPML URL translation
- [ ] Validate x-default fallback

**Day 5: Metadata Integration**
- [ ] Query Phase 4 metadata per locale
- [ ] Query Phase 2 context (on_topic filter)
- [ ] Test with multiple locales (en, fr, es)
- [ ] Validate XML format (Google validator)

**Day 6: WP-CLI Commands**
- [ ] Create `class-msh-sitemap-cli.php`
- [ ] Implement `wp msh sitemap generate`
- [ ] Implement Search Console submission
- [ ] Test cron automation

**Day 7: Automatic Triggers**
- [ ] Create `class-msh-sitemap-triggers.php`
- [ ] Hook post save events
- [ ] Hook metadata update events (Phase 4)
- [ ] Hook context extraction events (Phase 2)
- [ ] Test debounce logic (prevent spam regeneration)

---

### 6.11 Success Metrics

**Technical Metrics:**
- ✅ Sitemap validation (0 errors in Google Search Console)
- ✅ Images indexed per locale (track in GSC)
- ✅ Hreflang accepted (no warnings in GSC)
- ✅ Regeneration time (<10 seconds for 1,000 images)

**SEO Metrics (Phase 8 Analytics):**
- ✅ Image search impressions per locale (+20-50% target)
- ✅ Image search CTR per locale (+10-30% target)
- ✅ Time to index new images (<7 days target)
- ✅ Duplicate content warnings (0 target)

**Agency Value:**
- ✅ Zero manual sitemap maintenance
- ✅ Automatic updates on content changes
- ✅ Multi-locale support out of the box
- ✅ Search Console integration

---

### 6.12 References

**External:**
- [Google: Build and submit a sitemap](https://developers.google.com/search/docs/advanced/sitemaps/build-sitemap)
- [Google: Image sitemaps](https://developers.google.com/search/docs/advanced/sitemaps/image-sitemaps)
- [Google: Tell Google about localized versions (hreflang)](https://developers.google.com/search/docs/advanced/crawling/localized-versions)
- [Google: Use hreflang in sitemap](https://developers.google.com/search/docs/advanced/crawling/localized-versions#sitemap)
- [WordPress: Sitemaps API](https://make.wordpress.org/core/2020/07/22/new-xml-sitemaps-functionality-in-wordpress-5-5/)

**Internal:**
- [TODO.md](../TODO.md) - Phase 7 roadmap
- [PROJECT-STATUS-ALL-PHASES.md](../PROJECT-STATUS-ALL-PHASES.md) - Phase 7 status
- [Phase 2: Context Fusion](../docs/PHASE_2_CONTEXT_FUSION_PLAN.md) - on_topic filtering
- [Phase 4: Metadata Versioning](../docs/METADATA_VERSIONING_API.md) - Localized metadata

---

### Conclusion (Idea #6)

Per-locale image sitemaps with hreflang support is a **perfect fit** for Phase 7 (Multilingual Admin UX). We already have all the data needed from Phase 4 (localized metadata) and Phase 2 (context filtering), making this a natural extension of our multilingual architecture.

**Why This Wins:**
- ✅ **Unique competitive advantage** (no plugin offers per-locale image sitemaps)
- ✅ **Leverages existing data** (Phase 4 + Phase 2 already built)
- ✅ **WordPress-native** (Polylang + WPML compatible)
- ✅ **Agency value** (automated multilingual SEO, zero maintenance)
- ✅ **SEO impact** (+20-50% image search impressions target)

**Why Phase 7:**
- ✅ **Dependencies ready** (Phase 4 metadata + Phase 2 context complete)
- ✅ **Multilingual focus** (Phase 7 is all about multilingual UX)
- ✅ **Natural fit** (sitemap = admin tool for agencies)
- ✅ **1 week dev time** (reasonable scope for Phase 7)

**Immediate Actions:**
1. ✅ Add to TODO.md Phase 7 as "Image Sitemap Generator"
2. ✅ Note prerequisites (Phase 2 + Phase 4 complete)
3. ⏸️ Implement during Phase 7 (after Phase 6 complete)

**Status:** ✅ Approved for Phase 7 - Document now, implement after Phase 6.

---
---

## Idea #7: Bandwidth & API Cost Optimization

**Source:** User-provided best practices for cloud image processing (safe patterns for shared hosting)
**Priority:** 🔴 High (Cost Savings + Performance)
**Related Phases:** Current (OpenAI Vision API), Phase 10 Stage 1 (ImageKit AVIF)
**Status:** ✅ Approved - Implement before Phase 10 Stage 1

### Quick Summary

Currently, the plugin sends **full-resolution images** (up to 6000px, 10MB+) to remote APIs for AI analysis and format conversion. On cheap shared hosting, this causes:
- ❌ **Massive bandwidth usage** (10MB upload per image × 100 images = 1GB)
- ❌ **Timeout failures** (20-30 second uploads on slow connections)
- ❌ **Hosting overages** (surprise bills from exceeded bandwidth caps)
- ❌ **Redundant processing** (re-analyzing unchanged images)

**The Solution:** Pre-downscale images before sending to APIs, implement hash-based caching, and add bandwidth budgeting controls.

**Expected Impact:**
- ✅ **90-95% bandwidth reduction** (10MB → 400KB per AI analysis)
- ✅ **100% cost savings on duplicates** (hash-based skip)
- ✅ **Zero quality loss** (AI doesn't need 6000px to describe an image)
- ✅ **Fail-safe controls** (daily bandwidth caps prevent overages)

---

### 7.1 The Problem

#### Current Behavior Analysis

Looking at [class-msh-openai-connector.php:366-414](../includes/class-msh-openai-connector.php#L366-L414):

```php
// Current: Sends FULL RESOLUTION to OpenAI
$image_url = wp_get_attachment_url( $attachment_id );
// → 6000 x 4000px, 10MB JPEG sent to API

// For local dev: Encodes FULL SIZE to base64
$image_data = file_get_contents( $absolute_path );
$base64 = base64_encode( $image_data );
// → 13.3MB base64 string in API request
```

**Bandwidth Impact Example:**

| Scenario | Image Count | Size per Image | Total Bandwidth |
|----------|-------------|----------------|-----------------|
| Healthcare clinic (500 images) | 500 | 8MB avg | **4,000 MB (4GB)** |
| Small business (100 images) | 100 | 6MB avg | **600 MB** |
| Agency (10 sites × 200 images) | 2,000 | 7MB avg | **14,000 MB (14GB)** |

**Hosting Limits:**
- Cheap shared hosting: 50GB/month cap
- Processing 500 images = **8% of monthly bandwidth gone**
- Add WebP conversion + AVIF conversion = **24% of monthly bandwidth**

---

### 7.2 Current State Analysis

#### What We Have ✅

1. **Local base64 encoding** (for development)
2. **Low-detail mode** for OpenAI (`'detail' => 'low'`)
3. **Conditional processing** (checks if WebP exists before converting)

#### What We're Missing ❌

1. **No pre-downscaling** before sending to AI
2. **No hash-based deduplication** (re-analyzes unchanged images)
3. **No file size caps** (tries to process 50MB TIFFs)
4. **No bandwidth budgeting** (no daily/monthly limits)
5. **No per-provider tracking** (can't see OpenAI vs ImageKit costs)

---

### 7.3 Solution Architecture

#### Strategy: Two-Layer Optimization

Different strategies for different use cases:

| Use Case | Current Behavior | Optimized Behavior | Savings |
|----------|------------------|-------------------|---------|
| **AI Analysis** | Send 6000px original | Downscale to 1200px | 95% bandwidth |
| **Cloud AVIF Encoding** | Send full resolution | Downscale to 2000px | 80% bandwidth |
| **Local WebP** | Process full size | Process all WP sizes | Better coverage |
| **Duplicate Uploads** | Re-analyze every time | Hash check → skip | 100% on dupes |

---

### 7.4 Implementation Spec

#### Component 1: Pre-Processing Downscale Helper

**File:** `includes/cloud/class-msh-bandwidth-optimizer.php`

```php
<?php
/**
 * Bandwidth Optimizer
 *
 * Reduces bandwidth usage for remote API processing.
 *
 * @package MSH_Image_Optimizer
 * @since Phase 10 Stage 1
 */

class MSH_Bandwidth_Optimizer {

    /**
     * Prepare image for remote API processing.
     *
     * Downscales large images to reasonable dimensions for API analysis.
     * Creates a temporary working copy, original remains untouched.
     *
     * @param int    $attachment_id  Attachment ID.
     * @param int    $max_dimension  Max width/height (800-1600px recommended).
     * @param string $purpose        Purpose: 'ai_analysis', 'avif_convert', 'webp_convert'.
     * @return string|WP_Error       Path to optimized working copy or WP_Error.
     */
    public function prepare_for_remote_processing( $attachment_id, $max_dimension = 1200, $purpose = 'ai_analysis' ) {
        $original_path = get_attached_file( $attachment_id );

        if ( ! file_exists( $original_path ) ) {
            return new WP_Error( 'file_not_found', 'Original image file not found' );
        }

        // Check file size limit (default: 10MB)
        $file_size = filesize( $original_path );
        $size_cap  = $this->get_file_size_cap( $purpose );

        if ( $file_size > $size_cap ) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    'File size (%s) exceeds cap (%s) for %s',
                    size_format( $file_size ),
                    size_format( $size_cap ),
                    $purpose
                )
            );
        }

        // Get image dimensions
        $image = wp_get_image_editor( $original_path );
        if ( is_wp_error( $image ) ) {
            return $original_path; // Fallback to original
        }

        $size = $image->get_size();
        $needs_resize = max( $size['width'], $size['height'] ) > $max_dimension;

        if ( ! $needs_resize ) {
            return $original_path; // Small enough already
        }

        // Create working copy path
        $file_info = pathinfo( $original_path );
        $working_copy = $file_info['dirname'] . '/' .
                        $file_info['filename'] . '-working-' . $purpose . '.' .
                        $file_info['extension'];

        // Downscale to max dimension
        $image->resize( $max_dimension, $max_dimension, false );
        $saved = $image->save( $working_copy );

        if ( is_wp_error( $saved ) ) {
            return $original_path; // Fallback to original
        }

        // Log savings
        $bytes_saved = $file_size - filesize( $working_copy );
        $this->log_bandwidth_savings( $attachment_id, $bytes_saved, $purpose );

        error_log( sprintf(
            '[MSH Bandwidth] Downscaled #%d for %s: %s x %s → %dpx max (saved %s)',
            $attachment_id,
            $purpose,
            number_format( $size['width'] ),
            number_format( $size['height'] ),
            $max_dimension,
            size_format( $bytes_saved )
        ));

        return $working_copy;
    }

    /**
     * Check if image needs reprocessing.
     *
     * Uses SHA-256 hash to detect if file content changed.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $operation     Operation: 'ai_analysis', 'avif_convert', 'webp_convert'.
     * @return bool                 True if needs processing, false if cache hit.
     */
    public function should_process_image( $attachment_id, $operation ) {
        $image_path = get_attached_file( $attachment_id );

        if ( ! file_exists( $image_path ) ) {
            return false;
        }

        // Calculate file hash
        $current_hash = hash_file( 'sha256', $image_path );
        $cache_key = "_msh_{$operation}_hash";
        $cached_hash = get_post_meta( $attachment_id, $cache_key, true );

        // Check if file changed
        if ( $cached_hash === $current_hash ) {
            error_log( "[MSH Cache] Skipping {$operation} for #{$attachment_id} (unchanged)" );
            return false; // Skip - already processed
        }

        // Store new hash
        update_post_meta( $attachment_id, $cache_key, $current_hash );

        return true; // Needs processing
    }

    /**
     * Check if within bandwidth budget.
     *
     * @param int    $bytes_to_send Bytes about to be sent.
     * @param string $provider      Provider: 'openai', 'imagekit', 'google_vision'.
     * @return bool|WP_Error        True if OK, WP_Error if over budget.
     */
    public function check_bandwidth_budget( $bytes_to_send, $provider = 'all' ) {
        $daily_cap = $this->get_daily_bandwidth_cap();

        $today = date( 'Y-m-d' );
        $usage = get_transient( "msh_bandwidth_used_{$today}" ) ?: 0;

        if ( $usage + $bytes_to_send > $daily_cap ) {
            return new WP_Error(
                'bandwidth_exceeded',
                sprintf(
                    'Daily bandwidth cap exceeded: %s used of %s',
                    size_format( $usage ),
                    size_format( $daily_cap )
                )
            );
        }

        return true;
    }

    /**
     * Track bandwidth usage.
     *
     * @param int    $bytes    Bytes sent.
     * @param string $provider Provider: 'openai', 'imagekit', 'google_vision'.
     */
    public function track_bandwidth_usage( $bytes, $provider ) {
        $today = date( 'Y-m-d' );

        // Track total usage
        $total_usage = get_transient( "msh_bandwidth_used_{$today}" ) ?: 0;
        set_transient( "msh_bandwidth_used_{$today}", $total_usage + $bytes, DAY_IN_SECONDS );

        // Track per-provider usage
        $provider_usage = get_transient( "msh_bandwidth_{$provider}_{$today}" ) ?: 0;
        set_transient( "msh_bandwidth_{$provider}_{$today}", $provider_usage + $bytes, DAY_IN_SECONDS );

        error_log( sprintf(
            '[MSH Bandwidth] Tracked %s to %s (daily total: %s)',
            size_format( $bytes ),
            $provider,
            size_format( $total_usage + $bytes )
        ));
    }

    /**
     * Get file size cap for operation.
     *
     * @param string $purpose Purpose: 'ai_analysis', 'avif_convert', 'webp_convert'.
     * @return int            Size cap in bytes.
     */
    private function get_file_size_cap( $purpose ) {
        $defaults = array(
            'ai_analysis'  => 10 * 1024 * 1024, // 10MB
            'avif_convert' => 20 * 1024 * 1024, // 20MB
            'webp_convert' => 50 * 1024 * 1024, // 50MB (local processing)
        );

        $cap = $defaults[ $purpose ] ?? $defaults['ai_analysis'];

        /**
         * Filter file size cap for remote processing.
         *
         * @param int    $cap     Size cap in bytes.
         * @param string $purpose Processing purpose.
         */
        return apply_filters( 'msh_file_size_cap', $cap, $purpose );
    }

    /**
     * Get daily bandwidth cap.
     *
     * @return int Daily bandwidth cap in bytes.
     */
    private function get_daily_bandwidth_cap() {
        $default = 500 * 1024 * 1024; // 500MB default
        $cap = get_option( 'msh_daily_bandwidth_cap', $default );

        /**
         * Filter daily bandwidth cap.
         *
         * @param int $cap Daily bandwidth cap in bytes.
         */
        return apply_filters( 'msh_daily_bandwidth_cap', $cap );
    }

    /**
     * Log bandwidth savings.
     *
     * @param int    $attachment_id Attachment ID.
     * @param int    $bytes_saved   Bytes saved.
     * @param string $purpose       Purpose.
     */
    private function log_bandwidth_savings( $attachment_id, $bytes_saved, $purpose ) {
        $total_saved = get_option( 'msh_total_bandwidth_saved', 0 );
        update_option( 'msh_total_bandwidth_saved', $total_saved + $bytes_saved );
    }

    /**
     * Clean up working copy after processing.
     *
     * @param string $working_copy Path to working copy.
     * @param string $original     Path to original (safety check).
     */
    public function cleanup_working_copy( $working_copy, $original ) {
        // Safety: never delete the original
        if ( $working_copy === $original ) {
            return;
        }

        // Safety: only delete files with '-working-' in name
        if ( strpos( $working_copy, '-working-' ) === false ) {
            return;
        }

        if ( file_exists( $working_copy ) ) {
            @unlink( $working_copy );
            error_log( '[MSH Bandwidth] Cleaned up working copy: ' . basename( $working_copy ) );
        }
    }
}
```

---

### 7.5 Integration Points

#### OpenAI Connector Integration

**File:** `includes/class-msh-openai-connector.php`

**Before (current):**
```php
$image_url = wp_get_attachment_url( $attachment_id );
$image_data = $this->get_image_data( $image_url );
```

**After (optimized):**
```php
// Initialize bandwidth optimizer
$bandwidth_optimizer = new MSH_Bandwidth_Optimizer();

// Check if image needs processing (hash-based cache)
if ( ! $bandwidth_optimizer->should_process_image( $attachment_id, 'ai_analysis' ) ) {
    // Return cached metadata
    return get_post_meta( $attachment_id, '_msh_ai_metadata', true );
}

// Prepare downscaled working copy (6000px → 1200px)
$working_copy = $bandwidth_optimizer->prepare_for_remote_processing(
    $attachment_id,
    1200, // Max dimension for AI analysis
    'ai_analysis'
);

if ( is_wp_error( $working_copy ) ) {
    // File too large or error - use fallback mode
    error_log( '[MSH OpenAI] ' . $working_copy->get_error_message() );
    return null; // Triggers intelligent fallback
}

// Check bandwidth budget
$file_size = filesize( $working_copy );
$budget_check = $bandwidth_optimizer->check_bandwidth_budget( $file_size, 'openai' );

if ( is_wp_error( $budget_check ) ) {
    error_log( '[MSH OpenAI] ' . $budget_check->get_error_message() );
    $bandwidth_optimizer->cleanup_working_copy( $working_copy, get_attached_file( $attachment_id ) );
    return null; // Triggers fallback
}

// Get image data from working copy
$image_data = $this->get_image_data( $working_copy );

// Call OpenAI Vision API
$response = $this->call_openai_vision( $image_data, $prompt, $api_key );

// Track bandwidth usage
$bandwidth_optimizer->track_bandwidth_usage( $file_size, 'openai' );

// Clean up working copy
$bandwidth_optimizer->cleanup_working_copy( $working_copy, get_attached_file( $attachment_id ) );
```

**Bandwidth Savings:**
- **Before:** 10MB original → 10MB sent to API
- **After:** 10MB original → 400KB working copy → 400KB sent to API
- **Savings:** 9.6MB (96% reduction) per image

---

#### ImageKit Integration (Phase 10 Stage 1)

**File:** `includes/cloud/class-msh-imagekit-adapter.php` (future)

```php
public function convert_to_avif( $attachment_id ) {
    $bandwidth_optimizer = new MSH_Bandwidth_Optimizer();

    // Check if already processed (hash-based)
    if ( ! $bandwidth_optimizer->should_process_image( $attachment_id, 'avif_convert' ) ) {
        return $this->get_cached_avif_url( $attachment_id );
    }

    // Prepare for ImageKit (downscale to 2000px for quality balance)
    $working_copy = $bandwidth_optimizer->prepare_for_remote_processing(
        $attachment_id,
        2000, // Higher quality for AVIF encoding
        'avif_convert'
    );

    if ( is_wp_error( $working_copy ) ) {
        return $working_copy;
    }

    // Upload to ImageKit
    $result = $this->upload_to_imagekit( $working_copy, $attachment_id );

    // Track bandwidth
    $bandwidth_optimizer->track_bandwidth_usage( filesize( $working_copy ), 'imagekit' );

    // Cleanup
    $bandwidth_optimizer->cleanup_working_copy( $working_copy, get_attached_file( $attachment_id ) );

    return $result;
}
```

---

### 7.6 Settings UI

**Location:** Settings → Advanced Tab (or new "Bandwidth" tab)

```
┌─────────────────────────────────────────────────────────────────────┐
│ Bandwidth & API Cost Optimization                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ Remote Processing Limits                                           │
│ ━━━━━━━━━━━━━━━━━━━━━━━━━━━                                         │
│                                                                     │
│ ☑ Pre-downscale images before sending to APIs                      │
│   Reduces bandwidth by 90-95% with zero quality loss               │
│                                                                     │
│   AI Analysis max dimension:     [1200] px (recommended: 800-1600) │
│   AVIF Encoding max dimension:   [2000] px (recommended: 1600-2400)│
│                                                                     │
│ ☑ Skip unchanged images (hash-based caching)                       │
│   Prevents redundant API calls for duplicate uploads               │
│                                                                     │
│ File Size Limits                                                   │
│ ━━━━━━━━━━━━━━━━                                                    │
│                                                                     │
│   Max file size for AI analysis:   [10] MB                         │
│   Max file size for AVIF encoding: [20] MB                         │
│   Larger files will use local fallback mode                        │
│                                                                     │
│ Bandwidth Budget                                                   │
│ ━━━━━━━━━━━━━━━━━━                                                  │
│                                                                     │
│   Daily bandwidth cap: [500] MB                                    │
│   Current usage today: 127 MB (25%) [View Details]                │
│                                                                     │
│   ℹ️  Prevents unexpected hosting overages on shared hosting       │
│                                                                     │
│ Statistics                                                         │
│ ━━━━━━━━━━                                                          │
│                                                                     │
│   Total bandwidth saved: 47.2 GB                                   │
│   Images processed: 1,247                                          │
│   Average savings per image: 38.7 MB                               │
│   Cache hit rate: 23% (287 images skipped)                        │
│                                                                     │
│   [View Detailed Bandwidth Report]                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

### 7.7 WP-CLI Commands

```bash
# View bandwidth statistics
wp msh bandwidth stats

# Output:
# Today's Usage:
#   OpenAI Vision:    47 MB (12 requests)
#   ImageKit AVIF:    123 MB (34 conversions)
#   Total:            170 MB of 500 MB (34%)
#
# This Week:
#   Total:            847 MB
#   Savings:          12.4 GB (93% reduction)
#   Cache hits:       247 images (19%)
#
# All Time:
#   Processed:        1,247 images
#   Bandwidth saved:  47.2 GB
#   Avg per image:    38.7 MB saved

# Set bandwidth cap
wp msh bandwidth set-cap 1000 --unit=mb
# Success: Daily bandwidth cap set to 1000 MB

# Clear cache (force reprocess)
wp msh bandwidth clear-cache --operation=ai_analysis
# Success: Cleared 1,247 hash entries for ai_analysis

# Test downscaling
wp msh bandwidth test-downscale 123 --max-dimension=1200
# Testing downscale for attachment #123...
# Original: 6000 x 4000px, 9.8 MB
# Working copy: 1200 x 800px, 387 KB
# Savings: 9.4 MB (96% reduction)
```

---

### 7.8 ROI Analysis

#### Cost Savings Calculation

**Scenario: Small Healthcare Clinic**
- 500 images in media library
- Average image: 6000 x 4000px, 8MB

**Without Optimization:**
```
AI Analysis:    500 × 8MB = 4,000 MB (4GB upload)
AVIF Encoding:  500 × 8MB = 4,000 MB (4GB upload)
Total:          8,000 MB (8GB)

Shared hosting bandwidth cap: 50GB/month
Percentage used: 16% of monthly bandwidth
```

**With Optimization:**
```
AI Analysis:    500 × 400KB = 200 MB (1200px downscale)
AVIF Encoding:  500 × 1.2MB = 600 MB (2000px downscale)
Duplicates:     100 × 0MB = 0 MB (hash cache hits)
Total:          800 MB (after deduplication)

Percentage used: 1.6% of monthly bandwidth
Savings: 7,200 MB (90% reduction)
```

**Agency Scenario (10 Clients):**
- Without: 80GB bandwidth
- With: 8GB bandwidth
- **Savings: 72GB (90% reduction)**

#### Time Savings

**Upload Speed: 1 Mbps** (typical cheap shared hosting)

| File Size | Upload Time | Optimized Time | Time Saved |
|-----------|-------------|----------------|------------|
| 10MB original | 80 seconds | 3.2 seconds | 76.8 sec |
| 100 images | 2.2 hours | 5.3 minutes | 2 hours |
| 500 images | 11 hours | 27 minutes | 10.5 hours |

**Timeout Reduction:**
- Before: 30-40% timeout rate on slow connections
- After: <1% timeout rate

---

### 7.9 Testing Strategy

#### Unit Tests

```php
class Test_Bandwidth_Optimizer extends WP_UnitTestCase {

    public function test_downscale_large_image() {
        $attachment_id = $this->create_test_image( 6000, 4000 );
        $optimizer = new MSH_Bandwidth_Optimizer();

        $working_copy = $optimizer->prepare_for_remote_processing(
            $attachment_id,
            1200,
            'ai_analysis'
        );

        $this->assertFileExists( $working_copy );

        $size = getimagesize( $working_copy );
        $this->assertEquals( 1200, $size[0] ); // Width capped at 1200
        $this->assertLessThan( 500 * 1024, filesize( $working_copy ) ); // < 500KB
    }

    public function test_hash_cache_prevents_reprocessing() {
        $attachment_id = $this->create_test_image( 3000, 2000 );
        $optimizer = new MSH_Bandwidth_Optimizer();

        // First call: should process
        $this->assertTrue( $optimizer->should_process_image( $attachment_id, 'ai_analysis' ) );

        // Second call: should skip (hash matches)
        $this->assertFalse( $optimizer->should_process_image( $attachment_id, 'ai_analysis' ) );
    }

    public function test_bandwidth_budget_enforcement() {
        $optimizer = new MSH_Bandwidth_Optimizer();

        // Set low cap for testing
        update_option( 'msh_daily_bandwidth_cap', 1024 * 1024 ); // 1MB cap

        // First 500KB: OK
        $result = $optimizer->check_bandwidth_budget( 500 * 1024, 'test' );
        $this->assertTrue( $result );

        $optimizer->track_bandwidth_usage( 500 * 1024, 'test' );

        // Another 600KB: Should exceed 1MB cap
        $result = $optimizer->check_bandwidth_budget( 600 * 1024, 'test' );
        $this->assertWPError( $result );
        $this->assertEquals( 'bandwidth_exceeded', $result->get_error_code() );
    }
}
```

#### Integration Tests

1. **OpenAI Connector Test:**
   - Upload large image (10MB, 6000px)
   - Verify working copy created at 1200px
   - Verify API receives downscaled version
   - Verify working copy cleaned up after

2. **ImageKit Adapter Test:**
   - Upload large image
   - Verify downscale to 2000px for AVIF
   - Verify bandwidth tracking
   - Verify cleanup

3. **Cache Test:**
   - Upload image, process with AI
   - Re-upload same image (different filename)
   - Verify hash cache hit, no API call

---

### 7.10 Performance Metrics

**Success Criteria:**

| Metric | Before | Target | Measured |
|--------|--------|--------|----------|
| Bandwidth per AI analysis | 8-10MB | <500KB | TBD |
| Bandwidth per AVIF encoding | 8-10MB | <1.5MB | TBD |
| Duplicate detection rate | 0% | 15-25% | TBD |
| Timeout rate (1Mbps upload) | 30-40% | <5% | TBD |
| API cost per 100 images | $1.00 | $0.10-0.50 | TBD |

**Monitoring:**
- Dashboard widget showing daily bandwidth usage
- Email alerts at 80% of daily cap
- Weekly bandwidth reports
- Per-provider cost tracking

---

### 7.11 Implementation Timeline

**Phase 0 (Immediate - Before Phase 10):**
- Week 1: Build `MSH_Bandwidth_Optimizer` class
- Week 1: Integrate into OpenAI connector
- Week 1: Add hash-based caching
- Week 2: Settings UI + WP-CLI commands
- Week 2: Testing + documentation

**Phase 10 Stage 1:**
- Day 1: Integrate into ImageKit adapter
- Day 2-3: Multi-size processing support
- Day 4: Bandwidth dashboard widget
- Day 5: Final testing + launch

**Total:** 2-3 weeks

---

### 7.12 Conclusion

Bandwidth optimization is **critical** for:
- ✅ **Shared hosting compatibility** (won't blow bandwidth caps)
- ✅ **Cost control** (90-95% savings on API transfers)
- ✅ **Better UX** (faster processing, fewer timeouts)
- ✅ **Scalability** (agencies can process thousands of images)

**Why Now:**
- Current OpenAI integration sends 10MB images → wasteful
- Phase 10 ImageKit adds more remote processing → compounds problem
- Cheap shared hosting has strict limits → plugin must be lean

**Quick Wins:**
1. Pre-downscale to 1200px for AI analysis → **96% bandwidth savings**
2. Hash-based caching → **100% savings on duplicates**
3. File size caps → **fail-safe protection**

**Status:** ✅ Approved - Implement before Phase 10 Stage 1

---
---

## Idea #8: Multi-Size Format Conversion

**Source:** WordPress image size system analysis + responsive images best practices
**Priority:** 🟡 Medium (Quality of Life + Performance)
**Related Phases:** Current (WebP), Phase 10 (AVIF)
**Status:** ✅ Approved - Implement during Phase 10 Stage 1

### Quick Summary

WordPress automatically generates multiple image sizes (thumbnail, medium, large, custom) for responsive display, but **the plugin only converts the full-size original** to WebP/AVIF. This means:
- ❌ Themes using `wp_get_attachment_image( $id, 'thumbnail' )` → get JPG, not WebP
- ❌ Responsive images (`srcset`) → missing WebP/AVIF variants
- ❌ Cards, grids, galleries → use larger JPG files instead of tiny WebP
- ❌ Mobile users → download 300KB JPG instead of 30KB WebP

**The Solution:** Generate WebP/AVIF for **ALL WordPress image sizes**, not just full-size original.

**Expected Impact:**
- ✅ **60-80% file size reduction** on thumbnails and medium sizes
- ✅ **Complete format coverage** for all theme display sizes
- ✅ **Responsive image support** with proper `<picture>` tags
- ✅ **Mobile performance** (serve tiny WebP thumbnails, not full JPGs)

---

### 8.1 The Problem

#### Current Behavior

Looking at [class-msh-image-optimizer.php:9636-9666](../includes/class-msh-image-optimizer.php#L9636-L9666):

```php
private function convert_to_webp( $source_path, $webp_path ) {
    // Only converts: image.jpg → image.webp (full-size only)
    $image = imagecreatefromjpeg( $source_path );
    imagewebp( $image, $webp_path, 80 );
}
```

**WordPress Image Size System:**

When you upload `doctor-consultation.jpg` (6000 x 4000px), WordPress creates:

```php
Array (
    [width] => 6000
    [height] => 4000
    [file] => 2024/01/doctor-consultation.jpg
    [sizes] => Array (
        [thumbnail] => Array (
            [file] => doctor-consultation-150x150.jpg
            [width] => 150
            [height] => 150
            [mime-type] => image/jpeg
        )
        [medium] => Array (
            [file] => doctor-consultation-300x200.jpg
            [width] => 300
            [height] => 200
            [mime-type] => image/jpeg
        )
        [medium_large] => Array (
            [file] => doctor-consultation-768x512.jpg
            [width] => 768
            [height] => 512
            [mime-type] => image/jpeg
        )
        [large] => Array (
            [file] => doctor-consultation-1024x683.jpg
            [width] => 1024
            [height] => 683
            [mime-type] => image/jpeg
        )
        [1536x1536] => Array (
            [file] => doctor-consultation-1536x1024.jpg
            [width] => 1536
            [height] => 1024
            [mime-type] => image/jpeg
        )
        [2048x2048] => Array (
            [file] => doctor-consultation-2048x1365.jpg
            [width] => 2048
            [height] => 1365
            [mime-type] => image/jpeg
        )
    )
)
```

**Plugin Currently Creates:**
- ✅ `doctor-consultation.webp` (full-size, 6000px)

**Plugin Currently MISSING:**
- ❌ `doctor-consultation-150x150.webp` (thumbnail)
- ❌ `doctor-consultation-300x200.webp` (medium)
- ❌ `doctor-consultation-768x512.webp` (medium_large)
- ❌ `doctor-consultation-1024x683.webp` (large)
- ❌ `doctor-consultation-1536x1024.webp` (1536x1536)
- ❌ `doctor-consultation-2048x1365.webp` (2048x2048)

---

#### Real-World Impact

**Scenario:** Blog post with featured image displayed at 768px width

**Theme Code:**
```php
the_post_thumbnail( 'medium_large' );
// WordPress looks for: doctor-consultation-768x512.webp
// Finds: doctor-consultation-768x512.jpg (120KB JPG)
// Uses: JPG instead of WebP (would be 28KB)
```

**Result:**
- User downloads **120KB JPG** instead of **28KB WebP**
- **92KB wasted bandwidth** per image
- Slower page load, worse Core Web Vitals

**Multiply by:**
- Homepage: 10 featured images = 920KB wasted
- Blog archive: 24 cards = 2.2MB wasted
- Gallery: 50 thumbnails = 4.6MB wasted

---

### 8.2 Solution Architecture

#### Strategy: Process All WordPress Sizes

```php
/**
 * Convert all WordPress image sizes to WebP/AVIF.
 *
 * Iterates through _wp_attachment_metadata['sizes'] and converts each.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $format        Format: 'webp' or 'avif'.
 * @return array                Results for each size.
 */
public function convert_all_sizes( $attachment_id, $format = 'webp' ) {
    $metadata = wp_get_attachment_metadata( $attachment_id );
    $upload_dir = wp_get_upload_dir();
    $results = array();

    // Convert full-size original
    $original_path = get_attached_file( $attachment_id );
    $original_result = $this->convert_single_file( $original_path, $format );
    $results['full'] = $original_result;

    // Convert all generated sizes
    if ( ! empty( $metadata['sizes'] ) ) {
        $base_dir = dirname( $original_path );

        foreach ( $metadata['sizes'] as $size_name => $size_data ) {
            $size_path = $base_dir . '/' . $size_data['file'];

            // Skip if size doesn't exist
            if ( ! file_exists( $size_path ) ) {
                continue;
            }

            // Skip if size is larger than original (shouldn't happen, but safety)
            if ( filesize( $size_path ) > filesize( $original_path ) ) {
                continue;
            }

            // Convert this size
            $size_result = $this->convert_single_file( $size_path, $format );
            $results[ $size_name ] = $size_result;

            error_log( sprintf(
                '[MSH Multi-Size] Converted %s to %s: %s',
                $size_name,
                strtoupper( $format ),
                $size_result['success'] ? 'Success' : 'Failed'
            ));
        }
    }

    return $results;
}
```

---

### 8.3 Implementation Spec

#### Component: Multi-Size Converter

**File:** `includes/class-msh-multi-size-converter.php`

```php
<?php
/**
 * Multi-Size Format Converter
 *
 * Converts all WordPress image sizes to modern formats (WebP, AVIF).
 *
 * @package MSH_Image_Optimizer
 * @since Phase 10 Stage 1
 */

class MSH_Multi_Size_Converter {

    /**
     * Convert all sizes to target format.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $format        Format: 'webp' or 'avif'.
     * @return array                Results array with success/failure for each size.
     */
    public function convert_all_sizes( $attachment_id, $format = 'webp' ) {
        $metadata = wp_get_attachment_metadata( $attachment_id );
        $results = array();

        // Safety check
        if ( empty( $metadata ) ) {
            return array( 'error' => 'No metadata found' );
        }

        // Get original file path
        $original_path = get_attached_file( $attachment_id );

        if ( ! file_exists( $original_path ) ) {
            return array( 'error' => 'Original file not found' );
        }

        // Convert full-size original
        $results['full'] = $this->convert_single_size(
            $original_path,
            $format,
            $metadata['width'],
            $metadata['height']
        );

        // Convert all generated sizes
        if ( ! empty( $metadata['sizes'] ) ) {
            $base_dir = dirname( $original_path );

            foreach ( $metadata['sizes'] as $size_name => $size_data ) {
                $size_path = $base_dir . '/' . $size_data['file'];

                if ( ! file_exists( $size_path ) ) {
                    $results[ $size_name ] = array( 'success' => false, 'error' => 'File not found' );
                    continue;
                }

                $results[ $size_name ] = $this->convert_single_size(
                    $size_path,
                    $format,
                    $size_data['width'],
                    $size_data['height']
                );
            }
        }

        // Store conversion metadata
        $this->store_conversion_results( $attachment_id, $format, $results );

        return $results;
    }

    /**
     * Convert single file to target format.
     *
     * @param string $source_path Source file path.
     * @param string $format      Target format: 'webp' or 'avif'.
     * @param int    $width       Image width (for logging).
     * @param int    $height      Image height (for logging).
     * @return array              Result array with success, output_path, file_size, etc.
     */
    private function convert_single_size( $source_path, $format, $width, $height ) {
        $extension = strtolower( $format );
        $output_path = preg_replace( '/\.(jpg|jpeg|png)$/i', ".{$extension}", $source_path );

        // Check if already exists and source hasn't changed
        if ( file_exists( $output_path ) && filemtime( $source_path ) <= filemtime( $output_path ) ) {
            return array(
                'success' => true,
                'output_path' => $output_path,
                'file_size' => filesize( $output_path ),
                'skipped' => true,
                'reason' => 'Already up-to-date',
            );
        }

        // Load image
        $image = wp_get_image_editor( $source_path );

        if ( is_wp_error( $image ) ) {
            return array(
                'success' => false,
                'error' => $image->get_error_message()
            );
        }

        // Set quality based on format
        $quality = $this->get_quality_for_format( $format );
        $image->set_quality( $quality );

        // Set output format
        $image->set_mime_type( "image/{$extension}" );

        // Save converted file
        $saved = $image->save( $output_path );

        if ( is_wp_error( $saved ) ) {
            return array(
                'success' => false,
                'error' => $saved->get_error_message()
            );
        }

        // Calculate savings
        $original_size = filesize( $source_path );
        $converted_size = filesize( $output_path );
        $savings_bytes = $original_size - $converted_size;
        $savings_percent = round( ( $savings_bytes / $original_size ) * 100, 1 );

        return array(
            'success' => true,
            'output_path' => $output_path,
            'file_size' => $converted_size,
            'original_size' => $original_size,
            'savings_bytes' => $savings_bytes,
            'savings_percent' => $savings_percent,
            'dimensions' => "{$width}x{$height}",
        );
    }

    /**
     * Get quality setting for format.
     *
     * @param string $format Format: 'webp' or 'avif'.
     * @return int           Quality (0-100).
     */
    private function get_quality_for_format( $format ) {
        $defaults = array(
            'webp' => 80,
            'avif' => 75,
        );

        $quality = $defaults[ $format ] ?? 80;

        /**
         * Filter format conversion quality.
         *
         * @param int    $quality Quality (0-100).
         * @param string $format  Format: 'webp' or 'avif'.
         */
        return apply_filters( 'msh_format_quality', $quality, $format );
    }

    /**
     * Store conversion results in post meta.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $format        Format: 'webp' or 'avif'.
     * @param array  $results       Results array.
     */
    private function store_conversion_results( $attachment_id, $format, $results ) {
        $meta_key = "_msh_{$format}_conversion_results";
        $data = array(
            'timestamp' => time(),
            'results' => $results,
            'total_sizes' => count( $results ),
            'successful' => count( array_filter( $results, function( $r ) {
                return ! empty( $r['success'] );
            })),
        );

        update_post_meta( $attachment_id, $meta_key, $data );
    }

    /**
     * Get conversion statistics for attachment.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $format        Format: 'webp' or 'avif'.
     * @return array|false          Statistics array or false if not converted.
     */
    public function get_conversion_stats( $attachment_id, $format ) {
        $meta_key = "_msh_{$format}_conversion_results";
        return get_post_meta( $attachment_id, $meta_key, true );
    }
}
```

---

### 8.4 Integration with Picture Tag Support

**File:** Phase 10 Quick Win #1 (already planned)

```html
<!-- Before (current) -->
<img src="doctor-consultation-768x512.jpg" alt="...">

<!-- After (with multi-size conversion) -->
<picture>
    <source
        type="image/avif"
        srcset="
            doctor-consultation-300x200.avif 300w,
            doctor-consultation-768x512.avif 768w,
            doctor-consultation-1024x683.avif 1024w
        ">
    <source
        type="image/webp"
        srcset="
            doctor-consultation-300x200.webp 300w,
            doctor-consultation-768x512.webp 768w,
            doctor-consultation-1024x683.webp 1024w
        ">
    <img
        src="doctor-consultation-768x512.jpg"
        srcset="
            doctor-consultation-300x200.jpg 300w,
            doctor-consultation-768x512.jpg 768w,
            doctor-consultation-1024x683.jpg 1024w
        "
        sizes="(max-width: 768px) 100vw, 768px"
        alt="Medical consultation room">
</picture>
```

**Result:**
- Mobile (300px): Loads **18KB AVIF** instead of 85KB JPG → **67KB saved**
- Tablet (768px): Loads **52KB AVIF** instead of 220KB JPG → **168KB saved**
- Desktop (1024px): Loads **89KB AVIF** instead of 340KB JPG → **251KB saved**

---

### 8.5 Performance Impact

**Test Case: Blog Homepage with 10 Featured Images**

| Size Used | Format | File Size | Load Time (3G) | Total (10 images) |
|-----------|--------|-----------|----------------|-------------------|
| 768x512 JPG | JPEG | 220KB | 0.88s | **8.8s, 2.2MB** |
| 768x512 WebP | WebP | 52KB | 0.21s | **2.1s, 520KB** |
| 768x512 AVIF | AVIF | 35KB | 0.14s | **1.4s, 350KB** |

**Savings:**
- **WebP:** 7.4 seconds faster, 1.68MB saved (76% reduction)
- **AVIF:** 7.4 seconds faster, 1.85MB saved (84% reduction)

**Core Web Vitals Impact:**
- **LCP improvement:** 300-600ms (less data to download for hero image)
- **CLS improvement:** Faster load = less layout shift
- **FID improvement:** Page becomes interactive sooner

---

### 8.6 Testing Strategy

```bash
# WP-CLI command to test multi-size conversion
wp msh convert-sizes 123 --format=webp --dry-run

# Output:
# Testing multi-size WebP conversion for attachment #123...
#
# Original: doctor-consultation.jpg (6000 x 4000px, 9.8 MB)
#
# Conversion Plan:
#   ✓ full (6000x4000) → doctor-consultation.webp (2.1 MB, 79% savings)
#   ✓ thumbnail (150x150) → doctor-consultation-150x150.webp (12 KB, 73% savings)
#   ✓ medium (300x200) → doctor-consultation-300x200.webp (18 KB, 79% savings)
#   ✓ medium_large (768x512) → doctor-consultation-768x512.webp (52 KB, 76% savings)
#   ✓ large (1024x683) → doctor-consultation-1024x683.webp (89 KB, 74% savings)
#   ✓ 1536x1536 (1536x1024) → doctor-consultation-1536x1024.webp (187 KB, 76% savings)
#   ✓ 2048x2048 (2048x1365) → doctor-consultation-2048x1365.webp (312 KB, 77% savings)
#
# Total sizes: 7
# Total savings: 7.2 MB (75% average reduction)
#
# Run without --dry-run to execute conversion.

# Execute conversion
wp msh convert-sizes 123 --format=webp
# Success: Converted 7 sizes to WebP (7.2 MB saved)

# Batch convert all images
wp msh convert-sizes --all --format=webp --batch-size=50
# Processing images in batches of 50...
# Batch 1/10: 50 images converted (347 sizes, 1.2 GB saved)
# Batch 2/10: 50 images converted (341 sizes, 1.1 GB saved)
# ...
```

---

### 8.7 Implementation Timeline

**Phase 10 Stage 1:**
- Day 3-4: Build `MSH_Multi_Size_Converter` class
- Day 4: Integrate into existing WebP conversion flow
- Day 5: Add AVIF support (ImageKit adapter)
- Day 6: Picture tag integration with srcset
- Day 7: WP-CLI commands + testing

**Total:** 4-5 days (parallel with ImageKit integration)

---

### 8.8 Settings UI

**Location:** Settings → Formats Tab

```
┌─────────────────────────────────────────────────────────────┐
│ Image Format Conversion                                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ WebP Settings                                              │
│ ━━━━━━━━━━━━                                                │
│                                                             │
│ ☑ Convert images to WebP format                            │
│   ☑ Convert ALL WordPress sizes (recommended)              │
│     Generates WebP for: thumbnail, medium, large, etc.     │
│                                                             │
│   Quality: [80] (0-100, recommended: 75-85)                │
│                                                             │
│ AVIF Settings (Phase 10)                                   │
│ ━━━━━━━━━━━━━                                               │
│                                                             │
│ ☑ Convert images to AVIF format                            │
│   ☑ Convert ALL WordPress sizes (recommended)              │
│     Generates AVIF for: thumbnail, medium, large, etc.     │
│                                                             │
│   Quality: [75] (0-100, recommended: 70-80)                │
│   Provider: ● ImageKit ○ Local (requires ImageMagick 7+)  │
│                                                             │
│ Size Selection                                             │
│ ━━━━━━━━━━━━━━                                              │
│                                                             │
│ ☑ thumbnail (150x150)                                      │
│ ☑ medium (300x200)                                         │
│ ☑ medium_large (768x512)                                   │
│ ☑ large (1024x683)                                         │
│ ☑ 1536x1536                                                │
│ ☑ 2048x2048                                                │
│ ☑ All custom sizes defined by theme                        │
│                                                             │
│ [Select All] [Deselect All] [Reset to Defaults]           │
│                                                             │
│ Conversion Statistics                                      │
│ ━━━━━━━━━━━━━━━━━━━━                                        │
│                                                             │
│ Images with WebP:  847 / 1,247 (68%)                       │
│ Images with AVIF:  234 / 1,247 (19%)                       │
│ Total sizes:       8,729 WebP + 2,106 AVIF variants        │
│ Total savings:     47.2 GB                                 │
│                                                             │
│ [Regenerate All Formats] [View Detailed Report]           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

### 8.9 Conclusion

Multi-size format conversion is **essential** for:
- ✅ **Complete format coverage** (all theme display sizes)
- ✅ **Responsive image support** (proper srcset with AVIF/WebP)
- ✅ **Mobile performance** (tiny thumbnails load 10× faster)
- ✅ **Core Web Vitals** (better LCP, faster page loads)

**Why Phase 10:**
- Current WebP implementation is incomplete (only full-size)
- Phase 10 adds AVIF → perfect time to complete multi-size support
- Picture tag support (Quick Win #1) needs all sizes in all formats

**Quick Wins:**
1. Generate WebP for all sizes → **60-80% savings on thumbnails**
2. Generate AVIF for all sizes → **80-90% savings**
3. Complete srcset support → **responsive images done right**

**Status:** ✅ Approved - Implement during Phase 10 Stage 1

---
---

## Future Ideas

*This section is reserved for additional R&D ideas. Add new ideas as they come up.*

### How to Add a New Idea

1. Create a new section: `## Idea #2: [Your Idea Name]`
2. Add metadata: Source, Priority, Related Phases, Status
3. Write a quick summary (1-2 paragraphs)
4. Add detailed sections if needed
5. Update the Table of Contents at the top
6. Update TODO.md if the idea affects a specific phase

### Placeholder for Next Idea

```markdown
## Idea #2: [Idea Name Here]

**Source:** [Where this came from]
**Priority:** 🔴 High / 🟡 Medium / 🟢 Low
**Related Phases:** [Phase X, Y, Z]
**Status:** [Proposed / Under Review / Approved / Rejected]

### Quick Summary

[1-2 paragraph summary of the idea and why it matters]

### Detailed Breakdown

[Optional: Add detailed sections if needed]
```

---

**Living Document Status:** 📋 Active - Add ideas as discovered
**Last Updated:** October 27, 2025
**Total Ideas:** 8 (Safe Migrations, Feature Flags, AVIF Conversion, Staged Cloud Architecture, AI-Powered Image Delivery, Per-Locale Image Sitemaps, Bandwidth Optimization, Multi-Size Format Conversion)
