# MSH Image Optimizer – System Guide

> **Audience**: Product, engineering, and go-to-market teams preparing the Image Optimizer for standalone release.  
> **Source coverage**: Consolidated from the operator handbook (`MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md`), developer notebook (`MSH_IMAGE_OPTIMIZER_DEV_NOTES.md`), research backlog (`MSH_IMAGE_OPTIMIZER_RND.md`, `docs/RND-002-RESEARCH-IDEAS.md`), migration plan, multilingual guide, pricing strategy, and the phase status reports.

---

## 1. Deployment Snapshot

### 1.1 Current Environment
- **Client install**: Main Street Health, Hamilton ON, running the Medicross child theme with the optimizer bundled.  
- **Library scale**: 748 total uploads with ~47 high-impact public images; usage tracked across Elementor, Gutenberg, ACF, and legacy shortcodes.  
- **Runtime**: WordPress 6.6.x on PHP 8.2; GD extension required, Imagick optional fallback.  
- **Access**: Admin UI mounted at `Media → Image Optimizer`; duplicate cleanup, analyzer, and settings share a single SPA-driven dashboard.

### 1.2 Release Highlights (October 2025)
- **Optimization workflow**: Priority-scored batches, deterministic metadata, WebP conversion, and manual override UX are stable in production.  
- **Usage intelligence**: Background queue keeps the optimized usage index fresh; force rebuild and diagnostics surfaced to operators.  
- **Duplicate hygiene**: Perceptual hash quick scan plus per-group deep validation ensure safe deletions/renames with rollback artifacts.  
- **Safe rename**: Guardrails, logging, and rollback jobs were regression-tested in October 2025; rename suggestions remain opt-in.  
- **Documentation**: Operator, developer, migration, R&D, multilingual, and pricing docs are synchronized with the live UI.

### 1.3 Environment Requirements & Setup
- **Baseline stack**: WordPress 6.6.x (supports 5.0+), PHP 8.2 (compatible down to 7.4).  
- **PHP extensions**: `gd` with WebP enabled is required; `imagick` is optional for fallback conversions.  
- **Server resources**: 256 MB PHP memory minimum (512 MB recommended for large libraries) and reliable WP-Cron; set a system cron to call `wp cron event run --due-now` if WP-Cron is disabled.  
- **Filesystem layout**: Place the plugin at `wp-content/plugins/msh-image-optimizer/` with subfolders `admin/`, `assets/`, `includes/`, and `docs/` exactly as shipped (see migration plan §3).  
- **Activation checklist**  
  1. Clone or download this repository; copy the `msh-image-optimizer/` directory into your WordPress plugin path (or symlink during development).  
  2. Ensure the Medicross child theme fallback (`functions.php` guard for `MSH_IO_PLUGIN_FILE`) is present so production sites swap to the plugin automatically when activated.  
  3. Activate via WP Admin → Plugins or run `wp plugin activate msh-image-optimizer`. Activation hooks create/upgrade usage index, hash cache, and rename history tables.  
  4. Run initial background jobs: `wp msh jobs status` to confirm queue health, then `wp msh jobs process --batch=20` until pending = 0.  
  5. Trigger a first-pass usage index rebuild from the dashboard (**Force Usage Refresh**) or via CLI (`wp cron event run msh_process_usage_index_queue`).  
  6. Verify diagnostics card shows fresh timestamps and no failed jobs; download the diagnostics package if support needs to validate the environment.
- **Tooling**: WP-CLI 2.8+, access to shell for queue commands, optional local HTTPS for Media uploads, and source control to track customizations.  
- **Reference docs**: Detailed packaging steps in `docs/MSH_STANDALONE_MIGRATION_PLAN.md`, developer environment notes in `docs/MSH_IMAGE_OPTIMIZER_DEV_NOTES.md#system-requirements`, and setup checklists under `docs/setup/`.

---

## 2. Product Pillars
1. **Analyzer & Prioritization** – Classifies every published attachment with impact scoring (High ≥15, Medium 10–14, Standard <10) so teams hit the most visible assets first.  
2. **Smart Metadata & Filenames** – Deterministic templates tuned for healthcare/local SEO, optional AI overlay, and controlled filename suggestions.  
3. **Format & Delivery** – WebP conversion with original backups and delivery helpers to prevent theme regressions.  
4. **Usage Awareness** – Usage index, duplicate group intelligence, and real-time safety checks before destructive actions.  
5. **Governance** – Version history, staleness detection, job queue automation, and detailed audit trails.  
6. **Commercial Readiness (roadmap)** – Token-based AI pricing, multilingual UX, template intelligence, analytics, licensing, and telemetry.

---

## 3. Architecture & Component Map

### 3.1 Core Layers
- **Bootstrap & Hooks** (`msh-image-optimizer.php`, `includes/automation/class-msh-automation-triggers.php`)  
  - Registers admin menus, AJAX/REST routes, WP-CLI commands, cron hooks, and activation routines.  
  - Dispatches analyzer batches, job queue workers, and duplicate scans.

- **Context + Metadata Engine** (`includes/class-msh-image-optimizer.php`, `includes/class-msh-context-helper.php`, `includes/data/starter-templates.php`)  
  - Builds context payloads from onboarding settings, usage fingerprints, and attachment metadata.  
  - Applies industry-specific templates and rule sets; Phase 6 will inject template intelligence with context fusion fallbacks.

- **Usage Intelligence Stack** (`includes/class-msh-image-usage-index.php`, `includes/class-msh-perceptual-hash.php`, `includes/class-msh-hash-cache-manager.php`, `includes/class-msh-safe-rename-system.php`)  
  - Tracks attachment references across posts, builders, and serialized blobs.  
  - Maintains perceptual hash groups, duplicate badges, and rename rollback archives.  
  - Queue-backed index rebuild (`msh_process_usage_index_queue`) handles post-change refreshes.

- **Admin UX & SPA** (`admin/image-optimizer-admin.php`, `assets/js/image-optimizer-modern.js`, `assets/css/image-optimizer-admin.css`)  
  - Renders analyzer list table, batch controls, duplicate explorer, settings, diagnostics, and logs.  
  - JS modules manage state, fetch job progress, and render metadata diff previews.  
  - REST endpoints coordinate long-running tasks and websocket-style polling.

- **Supporting Services**  
  - `includes/class-msh-webp-delivery.php` – Conversion and delivery compatibility.  
  - `includes/class-msh-safe-rename-cli.php`, `wp msh jobs *` – Operational automation.  
  - `includes/class-msh-context-database.php`, `class-msh-context-extractor.php`, `class-msh-intent-classifier.php` – Phase 2 context fusion infrastructure.

### 3.2 Data Flow Overview
1. **Library Scan** → `wp_ajax_msh_analyze_images` batches through attachments, computes score/context, caches metadata suggestions.  
2. **Batch Optimize** → UI enqueues selected IDs; job queue processes WebP conversion, metadata application, rename hints, and logging.  
3. **Duplicate Review** → Quick scan fetches perceptual hash buckets + usage badges; optional Deep Scan re-checks Elementor/ACF JSON just-in-time.  
4. **Metadata Lifecycle** → Versioning cache stores active metadata; write-once history keeps manual overrides, AI output, and approval states.
5. **Telemetry & Diagnostics** → Job queue status, last usage index rebuild, and analyzer stats surface in the diagnostics panel and CLI.

### 3.3 CSS Architecture & Branding System
- **4-Layer cascade** (`assets/css/brand-guidelines.css` → `global-admin.css` → page-specific → components)
  - Layer 1 (Foundation): CSS variables for The Dot brand (colors, spacing, typography) in `brand-guidelines.css` and `typography-variables.css`.
  - Layer 2 (Global): Shared admin patterns (`global-admin.css`) including page backgrounds (#FAF9F6 cream), section cards, page headers (logo + support links), form elements, tables, and typography defaults.
  - Layer 3 (Page-Specific): Individual page styles (`dashboard.css`, `hub.css`, `image-optimizer-admin.css`, etc.) containing only unique layouts and features.
  - Layer 4 (Components): Standalone widgets (`confidence-indicators.css`, `tinydot-loader.css`, `admin-menu-branding.css`, `wp-list-table-branding.css`).
- **Standardized page header** (`admin/partials/page-header.php`) – Reusable template with The Dot logo, support links, and mail/website icons; automatically included on all admin pages (Dashboard, Hub, Settings, Analytics, Localization, Review Center, Help & Documentation).
- **Brand consistency** – Monochrome, minimalistic design with charcoal (#35332F) text, cream (#FAF9F6) backgrounds, light grey (#ddd) borders, and lime accent (#DAFF00).
- **Loading order** – Main plugin file (`msh-image-optimizer.php::enqueue_global_admin_styles()`) loads foundation and global CSS at priority 1 via `admin_enqueue_scripts` hook.
- **Reference docs** – Complete architecture documentation in `docs/CSS_ARCHITECTURE.md`, standardization summary in `docs/CSS_STANDARDIZATION_SUMMARY.md`.

---

## 4. Feature Deep Dive

### 4.1 Analyzer & Priority Scoring
- Pulls site-wide attachment list (published only), calculates SEO weight using page placement, template context, and historical performance.  
- High priority (≥15) typically captures homepage hero, service landing page imagery, and top-level navigation items; Medium (10–14) spans core service imagery; Standard (<10) covers blog and supporting assets.  
- Analyzer UX includes search, filtering, column sorting, and inline status indicators (Optimized, Manual, Needs Review).

### 4.2 Metadata Generation Engine
- Deterministic templates cover clinic, team, facility, dental, legal, and general business verticals.  
- Context fusion (Phase 2) surfaces locale-aware keywords, headings, and entity metadata from parent content, with intent classifier fallback to AI when rules cannot determine relevance.  
- Operators can toggle between View and Edit modes; manual changes persist via metadata cache and supersede automated results until explicitly regenerated.

#### 4.2.1 Metadata Parity Principles (November 2025 refresh)
To keep deterministic (non-AI) and Smart Mode (AI-assisted) paths aligned, both engines now follow four shared principles:

1. **Accuracy beats SEO for non-owned imagery** – Stock and decorative assets never guess at city/service context. It is preferable to output “Street Scene” than mention Hamilton on the Golden Gate Bridge; accessibility and trust outweigh speculative SEO copy.  
2. **Brand-owned assets already are marketing** – Facility, team, equipment, testimonial, clinical, business, service-icon, and brand_logo items stay branded in all modes. The `seo_mode` switch only controls how much optimization text (location/service tail) is appended.  
3. **SEO tail should never dominate** – Titles, ALT, and filenames remain descriptive; the location/service tail is limited to one sentence in the description. UVP clauses are clamped to ≤ 120 chars and merged into the scene sentence before the tail is evaluated.  
4. **Consistency across AI and non-AI paths** – Both pipes call the same validator (`MSH_Context_Aware_Validator`) so location placement, branding, and sentence limits match. Smart Mode output is now tested against the same matrix used for deterministic composer changes.

The November 6 2025 regression run captured the behaviour parity; for example:

> “Title: Main Street Health — Rehabilitation Facility Interior”  
> “Description: Professional rehabilitation environment at Main Street Health supports specialised care, featuring specialized first responder program with rapid physician referral system. Ideal for projects in Hamilton, Ontario, including medical topics.”

and the SEO-off mirror:

> “Description: Rehabilitation facility interior at Main Street Health supporting patient recovery.”

All 20 context × SEO combinations passed with the new rules (see `SEO-SHORT-CIRCUIT-TEST-RESULTS-2025-11-06.md` and the raw log excerpt embedded in Appendix A of `UVP-GATING-VERIFICATION-2025-11-06.md`).

### 4.3 WebP Conversion & Delivery
- Converts source files to WebP while retaining originals under `uploads/msh-backups/`.  
- Delivery helper rewrites markup or filter output while honoring theme overrides; safe fallback to original file triggered if client or browser lacks WebP support.  
- Conversion logging captures runtime, size savings, and error codes for diagnostics.

### 4.4 Filename Suggestion & Safe Rename
- Suggestion engine balances descriptive descriptors with business/location context without repeating location spam (per R&D filename research).  
- Safe rename system checks attachment usage, updates references, writes history rows, stores original basename, and facilitates rollback through CLI or admin UI.  
- Rename step is optional per client configuration; production install keeps it disabled until operators opt-in.

### 4.5 Duplicate Detection & Cleanup
- Perceptual hash + hash cache manager generate similarity fingerprints; duplicate groups carry badges (`Not reviewed`, `Mixed`, `In Use`, `Unused`).  
- Quick Scan is lightweight and safe for frequent runs; Deep Scan (currently hampered by legacy endpoint returning `Bad Request: 0`) is used selectively for serialized data validation before deletion/rename.  
- Cleanup actions enforce real-time usage refresh, multi-step confirmations, and automatic rollback artifacts.

### 4.6 Job Queue & Automation (Phase 5+9 Track A)
- Job system resides in `wp_msh_jobs` with priority levels and retry policies; triggers run on upload/edit events and manual batch actions.  
- WP-CLI suite (`wp msh jobs status|list|process|retry|clear`) supports ops workflows and automated QA harnesses.  
- Queue telemetry is piped into the diagnostics panel; next iteration adds detailed counters per job type.

---

## 5. Operational Workflows

### 5.1 Daily Optimization Loop
1. Navigate to `Media → Image Optimizer` and trigger **Run Analyzer** to refresh scores and statuses.  
2. Filter by **High** priority and batch optimize via **Optimize High Priority** or multi-select → **Optimize Selected**.  
3. Review metadata diff in the drawer, adjust tone/terminology where necessary, and save inline.  
4. Re-run Analyzer after large batches to update savings, status chips, and queue counts.  
5. Continue with Medium → Standard cohorts as time allows.

### 5.2 Duplicate Hygiene
1. Switch to the **Duplicate Cleanup** tab and run **Quick Scan**; inspect groups flagged as `Unused` or `Mixed`.  
2. Use **Deep Scan** only when serialized builder data needs verification (pending endpoint rewrite).  
3. Delete or rename duplicates once usage badges confirm no live references; rely on automatic backups and rename history for recovery.  
4. Record outcomes using duplicate cleanup log to maintain audit compliance.

### 5.3 Advanced Operations
- **Force Usage Index Rebuild** when major content sweeps occur; monitor queue status and timestamps in diagnostics.  
- **Metadata Version Rollback** available per attachment to revert to previous AI or manual entries.  
- **CLI Regression Scripts** (`wp msh qa`) cover analyzer, rename, and duplicate flows; expand with duplicate assertions once quick scan helper exposes reports.  
- **Context Overrides** per attachment allow manual specialization (e.g., service lines, practitioners, campaigns).

### 5.4 Analyze → Optimize → Reset (Bullet-Proofing Checklist – Nov 2025)
1. **Preflight** – Confirm `memory_limit`, `max_execution_time`, WebP support, writable uploads, and gather attachment locks (per-ID transient or lockfile) before touching originals.  
2. **Optimize atomically** – Write WebP/optimized assets to temp files, `fflush`/`fsync`, then `rename()`; update `_wp_attached_file` + metadata inside a transaction when available; never alter `guid`.  
3. **Post-run integrity** – After optimization, assert that `get_attached_file()` exists, derived sizes are present, and `wp_remote_head( wp_get_attachment_url() )` returns 200; run `wp_update_image_subsizes()` if any size is missing.  
4. **Reset safely** – Clear only staging keys (`_msh_ai_staged_meta`, `_msh_suggested_filename`, `_msh_context_trace`, etc.) while retaining user-facing fields (`_wp_attached_file`, `_wp_attachment_image_alt`, `_msh_loc_mode`, `seo_mode`, `brand_name_visible`).  
5. **Verify parity** – Run the 20-case context × SEO matrix (non-AI + AI) and archive the `/tmp/msh-context-tests/` JSON plus CLI transcript in the PR. Quote key lines in release notes, e.g. “Title: Main Street Health — Logo” / “Description: Main Street Health logo for use across digital and print touchpoints.”

---

## 6. Safeguards, Security & Observability

- **Access Control & Permissions** – All admin actions require `manage_options`; AJAX handlers die early on unauthorized access.  
- **CSRF Protection** – Nonces validated on every AJAX/REST request (`check_ajax_referer('msh_image_optimizer', 'nonce')`).  
- **Sanitization & Escaping** – Inputs sanitized via WordPress helpers (`sanitize_text_field`, `sanitize_textarea_field`, etc.); UI renders via escaped DOM APIs to prevent XSS.  
- **Database Safety** – Queries rely on WP APIs and prepared statements; no raw SQL injection vectors identified in the current review.  
- **Backups & Rollback** – Original images preserved; rename operations record before/after paths and maintain job history for replays.  
- **Metadata Versioning** – Write-once history (`wp_optimizer_metadata_versions`), active cache table, fingerprint-based staleness tracking to schedule regenerations.  
- **Diagnostics Panel** – Exposes analyzer totals, queue depth, last usage index rebuild, error logs, and download links for support packages.  
- **Regression Playbooks** – Phase 4 testing validated metadata versioning; Phase 5+9 includes WP-CLI-based automation and queue stress tests; outstanding automation covers duplicate report assertions and deep scan replacement.

---

## 7. Phase Progress (as of Oct 23 2025)

1. **Phase 1 – Language Selector UI** (✅)  
   - Added locale selector to AI regeneration modal with fallbacks (profile → site → English).  
   - Ensures prompts carry correct locale instructions; fully tested.

2. **Phase 2 – Context Fusion Layer** (✅)  
   - New context tables (`wp_msh_optimizer_context`) capture post-derived signals with locale keys and usage types.  
   - Extractors cover Gutenberg, Classic Editor, Elementor, and ACF; intent classifier blends rules with LLM fallback to judge relevance.

3. **Phase 3 – AI Translation & Cultural Adaptation** (✅)  
   - Locale profiles, protected glossary (brands, cities, SKUs), and prompt templates ensure culturally accurate output.  
   - Output validation enforces tone, banned terms, and subject presence.

4. **Phase 4 – Metadata Orchestration & Versioning** (✅)  
   - Introduced cache + history tables, diff viewer, and manual-first activation rules.  
   - Phase 4R+ “Staleness Engine” fingerprints context changes, scores urgency, and queues regeneration batches.

5. **Phase 5+9 – Automation & Enterprise Tracks** (🚧)  
   - **Track A (Backend Infrastructure)**: Job queue system implemented; currently under regression testing.  
   - **Track B (Frontend UI + REST)**: Queue tab + metadata fixes live; Events/History/Sync widgets in progress.  
   - **Track C (Enterprise)**: Licensing + telemetry delivered; remote sync and onboarding wizard pending.

6. **Upcoming Phases**  
   - **Phase 6 – Template Intelligence** (est. 2–3 weeks) to blend deterministic templates with harvested context and learning loops.  
   - **Phase 7 – Multilingual Admin UX** (3–4 weeks) to fully internationalize the UI and provide translation tooling.  
   - **Phase 8 – Analytics Dashboard** (4–5 weeks) focused on reporting, usage metrics, and export pipelines.  
   - **Phase 10+ – Future Enhancements** including partner integrations, advanced AI modules, and enterprise-grade governance.

---

## 8. Roadmap Themes

### 8.1 AI Automation

#### Foundation & Architecture
- Three-phase plan: **Foundation** (OpenAI wrapper, credit ledger, BYOK, error handling), **Core Features** (metadata generation, vision analysis, embeddings-based duplicate detection, hybrid review UI), and **Polish** (batch management, learning system, automated tests, tutorials).
- AI module draws from R&D insights: use GPT‑4 Vision for subject analysis, GPT‑3.5 for metadata, embeddings for similarity, and capture human edits to refine prompts over time.

#### Smart Mode Implementation (v1.2.17 - November 2025)
**Status**: ✅ Production-ready, validated in Phase 0A/0B testing

**Background**: Initial production usage revealed rate limiting issues (HTTP 429 errors) due to token consumption 15× over budget (3,217 tokens/image vs 210 target). Batches of 46 images stalled at 85% completion.

**Solution**: Smart Mode with ultra-compressed prompts and adaptive detail levels achieved 90.4% token reduction while maintaining 95% quality.

**Key Optimizations**:
1. **Schema v4 Short Keys**: Compressed JSON response format
   - Example: `file_name_suggestion` → `fn`, `title` → `t`, `alt_text` → `a`
   - Token savings: ~90 tokens per response
   - Backward compatibility via `expand_short_keys()` method

2. **Ultra-Compressed Prompts**: Removed redundancy, used pipe-delimited context
   - System prompt: 2,000 → 20 tokens (99% reduction)
   - User prompt: 400 → 40 tokens (90% reduction)
   - Context ID system (`ctx_9f11db7`) replaces inline business context

3. **Adaptive Vision Detail**: Switched from detail:high to detail:low with 640px resize
   - Vision tokens: 900 → 85 tokens (91% reduction)
   - File size: ~150KB → ~40KB
   - Quality maintained through context enrichment

**Performance Results** (Phase 0B Testing):
```
Before Smart Mode:
- 3,217 tokens/image
- 46 images → 148,000 tokens → Rate limit exceeded → HTTP 429
- Cost: $0.74 per 46-image batch
- Result: Stalls at 85%

After Smart Mode:
- 307 tokens/image (90.4% reduction)
- 46 images → 14,122 tokens → Well under rate limit
- Cost: $0.07 per 46-image batch (10.5× cheaper)
- Result: Completes in ~3 minutes with zero errors
- Speed: 1.12x faster (5.49s → 4.90s per image)
```

**Quality Validation**:
- Side-by-side comparison on 10 diverse images
- Average quality score: ~95% vs current high-detail mode
- Titles accurate and SEO-friendly
- Alt text accessible and descriptive
- Subjects/attributes relevant

**Implementation Files**:
- Test harness: `includes/class-msh-smart-mode-test-cli.php`
- Integration target: `includes/class-msh-openai-connector.php`
- Documentation: `docs/TOKEN_BASED_PRICING_STRATEGY.md` Part 10-11
- Results: `PHASE-0B-RESULTS.md`

**Rollout Plan (v1.2.17)**:
1. Replace current prompts with Smart Mode defaults
2. Change detail:high → detail:low globally
3. Add Schema v4 short-key parsing
4. Integrate `expand_short_keys()` for compatibility
5. Reduce image resize from 1600px → 640px
6. Optional Phase 0C: Further optimize to 230 tokens (current: 307)

### 8.2 Tokenized Pricing & Commercial Model
- Replace ambiguous “AI credits” with transparent OpenAI-aligned tokens.  
- Free on-ramp: 1,000 tokens expiring after 30 days to let users experience AI gains.  
- Subscription tiers: Pro ($99/yr, 50 000 tokens/month), Business ($199/yr, 5 sites, 500 000 tokens/month), Agency ($399/yr, unlimited sites, 2 000 000 tokens/month).  
- Add-ons: one-off token packs and “AI Unlimited” subscription; BYOK mode for enterprises.  
- Margins modeled at 50–80% with typical AI run costing ~$0.0024 per image in smart mode.

### 8.3 Standalone Plugin Migration
- Extract theme-embedded code into `/msh-image-optimizer/` plugin skeleton with dedicated bootstrap, assets, docs, and activation hooks.  
- Replace theme-relative paths, move AJAX registrations into plugin, enqueue assets with plugin paths, and package CLI + docs.  
- Preserve theme fallback: child theme loader checks for `MSH_IO_PLUGIN_FILE` before instantiating bundled version.  
- Activation triggers usage index table creation and hash cache maintenance.

### 8.4 Multilingual Roadmap
- **Layer 1 (i18n)**: Load text domain, wrap strings, localize JavaScript, generate POT files, and enforce translation coverage to meet WordPress.org requirements.  
- **Layer 2 (AI multilingual output)**: Locale-aware prompts, glossary enforcement, fallback language hierarchy, per-site language defaults, and eventual multi-language context profiles.  
- Phase 7 will introduce localized admin UX, translation QA, and language-aware onboarding.

### 8.5 Template Intelligence & Analytics
- Phase 6 introduces descriptor library, scene recognition rules, and learning models to supplement AI.  
- Phase 8 brings analytics dashboards, CSV export, telemetry pipelines, and diagnostics instrumentation.  
- Action Scheduler migration under evaluation for higher fidelity background processing.

### 8.6 Governance & Reliability
- Expand feature flags for safe rollouts (derived from RND-002).  
- Add background job telemetry counters, queue health visualizations, and deep scan endpoint rewrite.  
- Continue UI polish (typography, hover states) and finalize automation coverage.

---

## 9. Research & Innovation Backlog (RND-002 & RND Journal)

- **Zero-downtime Migrations** – Adopt Expand → Backfill → Switch → Contract pattern with resumable WP-CLI tooling and feature flags guarding read/write paths.  
- **Feature Flag Framework** – Roll out toggles to isolate risky launches, enabling parallel development and quick rollback.  
- **Advanced Imaging** – Explore AVIF generation, multi-size conversions, and AI-assisted delivery optimization to reduce bandwidth costs.  
- **Global SEO Enhancements** – Per-locale image sitemaps with hreflang, multilingual metadata, and cultural adaptation research (ties into Phase 7).  
- **Cost Optimization** – Bandwidth and API usage tuning, smart token budgeting, and telemetry loops to inform pricing levers.  
- **Filename Research** – Move away from blanket location stamping; use contextual relevance rules discovered during descriptor pipeline testing.

---

## 10. Business & Pricing Readiness

- **Monetization Stack**: Licensing system, telemetry, and settings panels built during Phase 5+9 Track C form the basis for paid plans.  
- **Token Dashboard**: Real-time usage bar, upgrade prompts, and trial countdown derived from pricing strategy doc; integrates with queue metrics for transparency.  
- **Support Playbooks**: Documentation workflow (index + TODO list), onboarding wizard (planned), and audit logs position the plugin for managed hosting and agency resale.  
- **WordPress.org Target**: Roadmap anticipates 13–15 weeks to public release once Phases 6–8 close and production testing completes.

---

## 11. Reference Library
- `docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md` – Operator workflow, UI screenshots, duplicate cleanup instructions.
- `docs/MSH_IMAGE_OPTIMIZER_DEV_NOTES.md` – Architecture history, QA playbooks, security posture, regression checklists.
- `docs/MSH_IMAGE_OPTIMIZER_RND.md` – Research spikes, AI strategy, filename/SEO findings.
- `docs/RND-002-RESEARCH-IDEAS.md` – Living backlog of migration patterns, imaging experiments, and cost levers.
- `docs/MSH_STANDALONE_MIGRATION_PLAN.md` – Extraction checklist, file map, activation tasks.
- `docs/MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md` – Two-layer multilingual implementation path.
- `docs/TOKEN_BASED_PRICING_STRATEGY.md` – Token economics, tier definitions, trial flow, Smart Mode implementation (Parts 10-12), compliance audit.
- `TOKEN-SYSTEM-COMPLIANCE-FIXES.md` – 9 compliance gaps identified in audit, implementation checklist with priorities.
- `TOKEN-MODEL-VERIFICATION-PLAN.md` – Complete test plan for Phase 0B + token system verification (v1.3.0).
- `.github/ISSUE_TEMPLATE/token-verification.md` – GitHub Issue template for tracking verification progress.
- `PHASE-0A-TEST-HARNESS.md` – Smart Mode test harness usage guide, test command documentation.
- `PHASE-0B-RESULTS.md` – Smart Mode production readiness validation, Phase 0B test results.
- `V1-2-17-IMPLEMENTATION-PLAN.md` – Step-by-step Smart Mode integration plan for production rollout.
- `AI-PERFORMANCE-OPTIMIZATION-PLAN.md` – Performance analysis, token optimization strategy, concurrent queue design.
- `PROJECT-STATUS-ALL-PHASES.md` – Phase-by-phase status, timelines, and owners.
- `NEXT-PHASES-PLAN.md`, `PROJECT-STATUS-ALL-PHASES.md`, `INFRASTRUCTURE-WEEK-1-COMPLETE.md` – Supporting schedule detail.
- Additional operator/QA checklists in `/docs/testing/` and release summaries across root-level reports.

**Last Reviewed:** 2025-11-02 (Smart Mode implementation documented)
