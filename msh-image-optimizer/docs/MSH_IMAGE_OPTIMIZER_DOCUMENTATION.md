# MSH Image Optimizer – Main Street Health Guide

This handbook covers the production build of the Image Optimizer that ships with the **Medicross child theme** for Main Street Health. It explains the day-to-day workflow, the duplicate cleanup process, and the guardrails that keep your content safe. Developer history and migration plans now live in `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md` and `MSH_STANDALONE_MIGRATION_PLAN.md`.

---

## ⚡ Quick Start Workflow

1. **Run Analyzer** – refresh the table so priorities, statuses, and usage badges reflect the latest library changes.
2. **Optimize** – work through high-priority images first, then medium, then the remaining queue using the batch actions.
3. **Review Duplicates** – run Quick Scan, rely on the automatic builder usage check, and use Deep scan only when you need a serialized/meta double-check before cleanup.

---

## 1. What the Optimizer Does

| Feature | What you see in the dashboard | Why it matters |
| --- | --- | --- |
| **Analyzer & Priority Scoring** | A sortable table of every published image with priority badges (High ≥15, Medium 10–14, Standard <10) | Focus on homepage/service images first; the system knows which images carry the most SEO weight. |
| **One-click Optimization** | “Optimize Selected”, “Optimize High Priority”, “Optimize Medium Priority”, “Optimize All Remaining” | Runs WebP conversion, alt text, caption, description, and filename suggestions in a single pass. |
| **Metadata Editor** | “Show Meta” drawer with Edit/Preview toggle | Adjust generated copy before it goes live; changes save immediately. |
| **Usage-aware Duplicate Cleanup** | Visual similarity groups with status badges (“Not reviewed”, “In Use”, “Mixed”, “Unused”) and an inline “Deep scan” button | Finds look‑alike images, auto-checks builder usage, and keeps anything that is still referenced. |
| **Safety Net** | Usage refresh timestamp, confirmation prompts, and live logs | The tool double-checks every file just before rename/delete so published content never breaks. |

---

## 2. Daily Workflow

1. **Open the dashboard**  
   `WP Admin → Media → Image Optimizer`

2. **Refresh the Analyzer**  
   Click **“Run Analyzer”** (top-right). The scan finishes in a couple of seconds and updates counts, filters, and usage badges.

3. **Triage high-impact images first**  
   In the “Priority” filter, choose **High (15+)**. These are homepage hero and key marketing assets. Then work through **Medium (10–14)** and finally **Standard**.

4. **Optimize**  
   - Select the rows you want.  
   - Use the action that fits the workload (e.g. **Optimize Selected** or **Optimize High Priority**).  
   - Progress appears in the top-right log. You can continue browsing while the batch runs.

5. **Fine-tune metadata (only if needed)**  
   - Click **“Show Meta”** on any row.  
   - Toggle **Edit** mode to adjust Title, Caption, ALT text, or Description.  
   - Save your changes; the row stays in place—no full refresh required.

6. **Re-run the Analyzer**  
   After large batches, press **Run Analyzer** again to update savings, statuses, and the optimization queue.

---

## 3. Duplicate Cleanup

Once you are confident your published images are optimized, move to the **Duplicate Cleanup** section on the same screen.

1. **Run a scan**  
   - **Quick Duplicate Scan** checks the most recent uploads plus anything hashed in the cache.  
   - **Deep Library Scan** crawls the entire library (chunked in 50-item batches with progress logging).  
   Both scans generate visual similarity groups and also pull MD5/filename matches.

2. **Understand the grid**
   - **Status badge** (“Not reviewed”, “In Use”, “Mixed”, “Unused”) reflects the last usage refresh.  
   - **Usage summary** shows top references; extra locations collapse into “+N more”.  
   - **Updated timestamp** (e.g. “Updated 2 mins ago (auto refresh)”) confirms when the usage check last ran.

3. **Review each group**
   - Click **Review** to open the modal.  
   - Choose one keeper (radio button) and optionally tick any unused files to remove.  
   - The modal displays thumbnails, filenames, IDs, hashes, and the per-file usage list.

4. **Refresh usage if needed**
   - The system auto-runs a builder crawl right after each scan.  
   - Use the inline **Deep scan** button only if you want to trigger the slower, serialized postmeta search for that group.  
   - When a deep scan finishes the timestamp updates and the badge flips (e.g. from “Not reviewed” to “In Use”).

5. **Apply the cleanup plan**
   - When you are satisfied with multiple groups, use **Apply Cleanup Plan**.  
   - Files delete in 20-file batches. Each deletion re-validates usage in real time; anything still used is skipped with a log entry.  
   - Download the log if you need a record of what was removed or retained.

**Tip:** Always run the optimizer before duplicate cleanup so any new filenames/metadata are already synced with the usage detector.

---

## 4. Built-in Safeguards

- **Usage verification on every destructive action** – Renames and deletions double-check references the moment the action fires.  
- **Indexer health** – Usage indexing runs in the background whenever media changes. “Smart Build” performs a quick catch-up, and “Force Rebuild” performs a full background refresh for troubleshooting. The log and diagnostics badge track queued/running/completed status.  
- **Background queue** – Index rebuilds run as a background job. The diagnostics badge shows queued/running/completed states and you can continue working while it processes.  
- **WebP fallback logic** – Originals are kept alongside WebP versions; browsers without WebP support automatically receive the original file.  
- **Logging** – Actions and errors are streamed to the on-page log and the WordPress debug log (`MSH OPTIMIZER`, `MSH Usage Index`, `MSH DUPLICATE`).  
- **Permissions** – Only administrators can run optimization or cleanup tasks. Editors can view results but cannot delete files.

---

## 5. When to Run Each Task

| Task | Run this when… | Typical cadence |
| --- | --- | --- |
| **Run Analyzer** | You’ve uploaded new images or finished a batch | Daily or before each editing session |
| **Optimize High Priority** | Homepage/service imagery is unoptimized | Weekly (or whenever new campaigns launch) |
| **Optimize Medium Priority** | Service/blog images need captions/ALT text | Bi-weekly |
| **Deep Library Duplicate Scan** | After large imports or quarterly cleanups | Quarterly |
| **Force usage index rebuild** | Large content changes (bulk page edits, new theme widgets) | Only when prompted |

---

## 6. Support & Escalation

- **Local issues (metadata, duplicates, UI questions)** – Reach out to the Main Street Health site maintainer (internal team).  
- **Plugin defects or enhancements** – Log a ticket referencing this guide and the commit hash of the current theme version.  
- **Standalone migration** – When you are ready to deploy the optimizer as a separate plugin, follow `MSH_STANDALONE_MIGRATION_PLAN.md`.
- **Known issue** – The legacy **Deep library scan** button currently returns `Bad Request: 0` after finishing. Use the Quick scan plus automatic per-group verification as the primary workflow and trigger Deep scan only when you need a manual serialized/meta sweep.
- **Diagnostics widget** – Use the “Diagnostics Snapshot” card to confirm the timestamp of the last Analyzer, optimization batch, and duplicate scans. The usage index badge shows whether rename safeguards are ready (green), queued, or need attention with orphan counts. The “Queue Status” rows (mode, pending jobs, processed, timestamps) mirror the background worker so you can confirm progress at a glance. When Smart Build runs it also lists the attachment IDs that were re-indexed so you can double-check any recent uploads. The modal that appears when you trigger **Smart Build** or **Force Rebuild** can be dismissed immediately—background processing will continue and the diagnostics card will keep updating. The “Download Recent Log” button streams the latest debug entries for support tickets. If your host disables WP-Cron, schedule a real cron job to run `wp cron event run --due-now` every few minutes so background indexing keeps up.

---

## 7. Context-aware filenames & metadata

- **Descriptor-first slugs** – Filenames start with a two-word (max) visual descriptor sourced from the attachment title, page title, or tags (`windmill.jpg`, `unicorn-wallpaper.jpg`). Camera-style codes (`dsc200503`, `cep00032`) are filtered before the slug is composed.
- **Conditional brand & location** – The pipeline appends the business slug only when it adds context (logos, team portraits, product/facility shots, or pages like Contact/About/Team). City slugs appear only when the image is tied to a physical space or explicitly tagged as location-specific. Stock/blog graphics stay lean (`triforce-wallpaper.jpg`), while local hero shots read as `team-portrait-emberline-austin.jpg`.
- **Conditional brand & location** – The pipeline appends the business slug only when it adds context (logos, team portraits, product/facility shots, or pages like Contact/About/Team). City slugs appear only when the image is tied to a physical space, manually flagged as location-specific, and the business profile is marked as an in-person/local service. Remote/online businesses skip the city suffix by default so stock/blog graphics stay lean (`triforce-wallpaper.jpg`) while local hero shots still read as `team-portrait-emberline-austin.jpg`. If you choose an ambiguous business type (e.g., B2B/B2C/nonprofit), the optimizer falls back to “city provided = local intent” – leave the City/Region blank for fully remote operations.
- **Location toggle in the analyzer** – Each image row now includes a “Use business location context” checkbox inside the inline context editor. Flip it on when an individual asset (for example, an office tour or team photo) should inherit the city slug even though the profile is otherwise remote.
- **Length guardrail** – `assemble_slug()` keeps combined slugs under ~50 characters and automatically drops redundant terms (e.g. `logo` is not added twice).
- **Metadata mirrors the slug** – ALT text, caption, and description reuse the cleaned descriptor. Location and brand wording only appear when they were part of the slug decision, so a generic wallpaper no longer mentions Austin, while a clinic lobby still references the city.
- **Quick sanity checks**  
  - `wp eval 'print_r(MSH_Image_Optimizer::get_instance()->analyze_single_image(611));'` → Generic gallery image (no brand/location in the filename).  
  - `wp eval 'print_r(MSH_Image_Optimizer::get_instance()->analyze_single_image(1027));'` → Logo asset (brand retained, location skipped unless tagged).  
  - Add a “Contact” or “Team” page title/tag to confirm city suffixes reappear.
- **Collision handling** – If multiple assets collapse to the same descriptor the optimizer keeps the slug short and WordPress adds the numeric suffix automatically. That is expected for the WordPress theme-test dataset and disappears once real-world titles/tags come into play.

---

## 8. Technical Investigation & Troubleshooting

### File Resolver for Path Mismatches

**Feature:** `MSH_File_Resolver` class (added 2025-10-14)

The plugin includes a resilient file resolver that handles database/filesystem path mismatches commonly caused by:
- Site migrations (http→https, domain changes)
- Manual file operations via FTP
- Failed rename/optimization operations
- Plugin conflicts modifying files without updating database

**How it works:**
- When `_wp_attached_file` path doesn't exist, searches for files matching `*-{attachment_id}.{ext}` pattern
- Validates MIME family (image/* only matches image/*)
- Checks timestamp sanity (rejects orphaned files older than post creation)
- Only accepts single exact match (prevents ambiguous results)
- Logs mismatches when `WP_DEBUG` enabled
- Read-only (doesn't modify database automatically)

**Testing:**
```bash
# Test file resolver on specific attachment
wp eval "print_r(MSH_Image_Optimizer::get_instance()->analyze_single_image(611));"

# With WP_DEBUG=true, you'll see log output like:
# [MSH File Resolver] Resolved mismatch for attachment 611:
# expected "path/to/old-name.jpg" → found "path/to/actual-name.jpg"
```

### Investigation Documentation

For complete technical details on the file resolver implementation, database investigation findings, and rename verification system analysis:

- **`../../DB_INVESTIGATION_FINDINGS.md`** - Technical deep-dive with:
  - Database investigation query results
  - Root cause analysis of verification failures
  - Meta ID tracking bug explanation
  - Cache invalidation verification
  - Code walkthroughs and fix recommendations

- **`../../INVESTIGATION_SUMMARY_FOR_USER.md`** - Executive summary with:
  - Implementation status and test results
  - What works vs. what needs long-term fixing
  - Files created/modified during investigation
  - Testing checklist and next steps

**Backup Test Data:**
- `../../test-data-mismatch-state-YYYYMMDD-HHMMSS.sql` - Database exports preserving mismatch states for reproduction testing
- `../../mismatch-manifest-YYYYMMDD.txt` - Attachment path listings before cleanup

These files document a complete investigation cycle from October 2025 when database/filesystem mismatches were discovered and the file resolver was implemented to handle real-world migration scenarios.

---

## 9. Testing & Quality Assurance

### Industry-Specific Test Sites

For comprehensive testing across different business types, we maintain separate test site documentation:

**Phase 1 - Core Industries:**
- **[Wellness Test Site](TEST_SITE_WELLNESS.md)** - Radiant Bloom Studio (spa/beauty)
  - Purpose: Test wellness metadata, validate wellness ≠ healthcare bug fix
  - Critical for confirming spa services don't get "Rehabilitation Treatment" metadata
  - Tests beauty/spa/massage/skincare vocabulary

**Phase 2 - Professional Services:**
- **[Legal Test Site](TEST_SITE_LAW_FIRM.md)** - Sterling & Associates Law
  - Purpose: Test professional services metadata generation
  - Validates legal industry vocabulary (attorneys, counsel, practice areas)
  - Tests distinction between professional vs. home services vs. healthcare

**Phase 3 - Home Services:**
- **HVAC Test Site** - Arctic Comfort Systems
  - Purpose: Test home services/contractor metadata
  - Validates technical service vocabulary (licensed, emergency, 24/7)
  - Tests equipment/facility images vs. professional headshots

**General Testing:**
- **Current Test Site:** `thedot-optimizer-test` (local)
  - General edge case testing
  - WordPress test image patterns (alignment, featured, markup)
  - Dimension pattern detection (580x300, 150x150, etc.)
  - Generic filename handling (IMG_xxxx.jpg, canola2.jpg)

### Testing Documentation

Each test site includes:
- Complete business context (UVP, pain points, target audience)
- WP-CLI setup scripts for rapid deployment
- Sample page content with industry-specific copy
- Recommended stock images with download links
- Expected metadata examples (before/after bug fixes)
- Edge case scenarios to validate
- Troubleshooting steps

### Related Documentation

- [Industry Metadata Templates](../../INDUSTRY_METADATA_TEMPLATES.md) - All 17 industry specifications
- [GEO Strategy](../../GEO_STRATEGY.md) - AI discovery optimization strategy (evidence-based)
- [MSH R&D Document](MSH_IMAGE_OPTIMIZER_RND.md) - Research and development notes
- [Wellness Test Site Setup](../../WELLNESS_SITE_SETUP.md) - Quick setup guide for Radiant Bloom

---

## 10. Document History

| Date | Change |
| --- | --- |
| 2025-10-15 | Added Testing & QA section with industry-specific test site documentation (wellness, legal, HVAC). |
| 2025-10-14 | Added Technical Investigation section documenting file resolver, path mismatch handling, and references to detailed investigation findings. |
| 2025-10-09 | Added Quick Start workflow, documented diagnostics snapshot card, and noted deep scan legacy issue. |
| 2025-10-06 | Re-authored for the streamlined analyzer, auto usage refresh, and updated duplicate workflow. Older technical notes moved to `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md`. |
