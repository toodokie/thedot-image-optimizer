# MSH Image Optimizer – Main Street Health Guide

This handbook covers the production build of the Image Optimizer that ships with the **Medicross child theme** for Main Street Health. It explains the day-to-day workflow, the duplicate cleanup process, and the guardrails that keep your content safe. Developer history and migration plans now live in `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md` and `MSH_STANDALONE_MIGRATION_PLAN.md`.

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
- **Indexer health** – The “Force rebuild” button calls the optimized usage-index rebuild (`build_optimized_complete_index`). Logs confirm progress and the last run timestamp shows on the dashboard.  
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

---

## 7. Document History

| Date | Change |
| --- | --- |
| 2025-10-06 | Re-authored for the streamlined analyzer, auto usage refresh, and updated duplicate workflow. Older technical notes moved to `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md`. |
