# MSH Image Optimizer – User Guide

> **Who this is for:** Content editors and site managers using the Main Street Health Image Optimizer dashboard (Media → Image Optimizer).  
> **What you get:** Step-by-step directions, best practices, and quick fixes so you can optimize images confidently without digging through developer docs.

---

## 1. Before You Start
- **Permissions:** You need a WordPress administrator account (capability `manage_options`).  
- **Where to go:** In the WordPress admin sidebar, choose **Media → Image Optimizer**. The optimizer opens in a single-page dashboard with tabs for Analyzer, Duplicate Cleanup, and Settings.  
- **Recommended cadence:** Run the Analyzer at least once a week, or after big media uploads, so priority scores and usage badges stay accurate.

---

## 2. Dashboard Tour
- **Header bar** – Primary actions (`Run Analyzer`, `Force Usage Refresh`), status chips (last scan time, library totals), and queue progress.  
- **Analyzer table** – Lists every published image, sorted by **Priority** (High ≥15, Medium 10–14, Standard <10). Columns show thumbnail, title, optimization status, last optimized time, and quick actions.  
- **Metadata drawer** – Click **Show Meta** to reveal generated ALT text, captions, descriptions, and filename suggestions with Edit/Preview toggle.  
- **Batch tray** – Appears after you select rows; offers **Optimize Selected**, **Optimize High Priority**, **Optimize Medium Priority**, or **Optimize All Remaining**.  
- **Duplicate Cleanup tab** – Groups visually similar images with badges (`Not reviewed`, `In Use`, `Mixed`, `Unused`) and provides Quick Scan or optional Deep Scan.  
- **Diagnostics card** – Displays queue depth, last usage index rebuild time, and links to detailed logs if something stalls.

---

## 3. Quick Start Checklist
1. **Run the Analyzer** – Press the button in the top-right corner. Wait for the toast confirming the scan completed.  
2. **Filter by Priority** – Use the dropdown above the table to work through **High**, then **Medium**, then **Standard** items.  
3. **Select Your Batch** – Tick the box beside each image (or use the select-all checkbox).  
4. **Launch Optimization** – Choose the batch action that matches your selection. The live log in the header shows progress.  
5. **Review Metadata** – Spot-check high-visibility images by opening the Metadata drawer, switching to Edit if you want to tweak text, and saving inline.  
6. **Refresh Analyzer** – Re-run the analyzer after a large batch so scores, statuses, and savings columns update.  
7. **Handle Duplicates** – Move to the Duplicate Cleanup tab, run **Quick Scan**, and archive or delete safe duplicates (details in section 5).  
8. **Log Major Actions** – If you delete or rename files, capture notes in your team’s release log for future reference.

---

## 4. Optimizing Images Step by Step

### 4.1 Selecting Images
- Use **Search** (top right of table) by keyword or filename.  
- Combine filters: e.g., Priority = High, Status = Needs attention.  
- Multi-select with shift-click or the column checkbox to grab a whole page of results.

### 4.2 Running Batch Optimization
- Pick the appropriate batch action:
  - **Optimize Selected** – Works on only the items you picked.
  - **Optimize High Priority** – Instantly runs High (15+) rows; handy when you want a quick reset.
  - **Optimize Medium Priority** – Same, but for Medium (10–14).
  - **Optimize All Remaining** – Queue everything that isn’t already optimized.
- Let the batch finish. The dashboard keeps working while the queue runs, but avoid closing the tab until the log reports success.

### 4.3 Editing Metadata (Optional)
- Click **Show Meta** → **Edit** to override text.  
- Changes save immediately; no page reload required.  
- Tip: Keep ALT text concise (under 125 characters) and ensure it matches what’s in the image. Use the Description field for longer marketing copy.

### 4.4 Rolling Back Metadata
- If a change doesn’t look right, use the dropdown in the metadata drawer to revert to a previous version. The optimizer stores a full history, so you can always restore manual or AI-generated entries.

### 4.5 Force Usage Index Rebuild (Advanced)
- If new posts or builder changes aren’t reflected, click **Force Usage Refresh** in the header. This rebuilds the usage index. Watch the diagnostics card to confirm the queue completes.

### 4.6 Understanding SEO Mode (Metadata Toggle)
- **What the toggle does:** `SEO Mode` controls whether the optimizer appends marketing copy (UVP + location tail) to descriptions. It never turns branding on or off for owned assets.  
- **When OFF:** You get clean, accessibility-first copy — e.g.  
  > “Description: Specialist care team at Main Street Health delivering personalised support.”  
  No UVP, no location tail, ALT stays geo-free.  
- **When ON:** The optimizer adds a short UVP clause (only for facility/clinical/business/testimonial) followed by one location tail sentence, capped at two sentences total. For icons and other non-owned imagery the UVP stays suppressed:  
  > “Description: Custom service icon reinforces Main Street Health across digital channels. Ideal for projects in Hamilton, Ontario, including medical topics.”  
- **AI parity:** Smart Mode follows the same rules because all metadata runs through the shared validator — stock/decorative never guess at location, owned contexts stay branded, and only the description receives a tail sentence.

---

## 5. Duplicate Cleanup

### 5.1 Quick Scan Workflow
1. Open the **Duplicate Cleanup** tab.  
2. Press **Run Quick Scan**. The system groups visually similar images using perceptual hashing and shows status badges.  
3. Review each group:
   - **Unused** – Safe to archive or delete.  
   - **Mixed** – Some items are used; inspect carefully.  
   - **In Use** – Leave these alone unless you are replacing the referenced file.  
4. Select the attachments you want to remove and use **Archive/Delete** (depending on your policy).

### 5.2 Deep Scan (Use When Needed)
- For serialized builders (Elementor, ACF repeaters) run **Deep Scan** inside a duplicate group to double-check references.  
- Current limitation: the legacy endpoint sometimes returns `Bad Request: 0`. If that happens, re-run the scan or wait for engineering to finish the replacement endpoint.

### 5.3 Rename vs Delete
- **Rename** keeps the asset but updates the filename. Choose this when the image stays on the site but needs an SEO-friendly slug.  
- **Delete** removes the file permanently (after confirmation) and clears database references. The optimizer makes a backup and logs the action in case rollback is needed.

---

## 6. Optimization Hub (Metadata Hub)

> Find this under **The Dot → Metadata Hub**. It consolidates Phase 4R+/5 tools into a single workspace.

### 6.1 Cache Tab – Manage Active Metadata
- Browse every attachment’s current metadata across locales.  
- Filter by locale, field source (AI/manual/WordPress), or staleness status.  
- Use the actions bar to regenerate stale metadata, export results to CSV, or switch the active source back to a manual version after an AI run.  
- Tip: Use the **Stale** filter weekly to keep the library fresh without running full-site batches.

### 6.2 History Tab – Rollback & Audit Trail
- View a chronological timeline for any attachment and locale.  
- Click an entry to open side-by-side diffs (before/after) and restore older versions when needed.  
- Each record shows who made the change and any notes captured during manual edits. Add brief context (e.g., “Client-approved headline”) when you override metadata so teammates know why it was changed.

### 6.3 Queue Tab – Monitor Background Jobs
- Shows real-time queue health: pending counts, in-progress jobs, and average completion time.  
- Use the action buttons to retry failed jobs, pause low-priority batches, or trigger **Regenerate All Stale** during scheduled maintenance windows.  
- If the queue stalls, download diagnostics from here or run the matching WP-CLI commands (`wp msh jobs status`, `wp msh jobs process`) if you have shell access.

### 6.4 Events Tab – Live Activity Feed
- Streams analyzer runs, metadata regenerations, duplicate cleanups, and queue operations as they happen.  
- Filter by event type (e.g., Regeneration, Queue, Cleanup) or by attachment ID.  
- Export CSV when you need to share a summary with stakeholders or attach context to a support ticket.

### 6.5 Sync Tab – Pro Cloud Workflows
- Available to Pro plans: manage S3/Supabase sync, trigger manual push/pull jobs, and resolve content conflicts.  
- Displays recent sync history, current status, and ETag checks so you can confirm remote copies match WordPress.  
- Free users see the locked tab with upgrade messaging; once licensed, the full UI unlocks without additional setup.

### 6.6 Related Tools
- **Context Analytics** – Reports on how images tie back to page content; use it to spot service pages that still need coverage.  
- **Locale Profiles** – Configure tone, terminology, and translation rules per language.  
- **Glossary** – Manage protected terms so AI never mistranslates brand names, cities, or medical procedures.  
- **Settings** – Toggle rename behavior, adjust automation defaults, and access diagnostic downloads.

---

## 7. Safety Nets & Best Practices
- **Usage checks:** Every destructive action triggers a real-time usage refresh; if new references appear, the job stops.  
- **Backups:** Originals are preserved in `/uploads/msh-backups/`; rename history captures old/new paths.  
- **Version history:** Manual edits always win until you select **Regenerate**; history is flat-filed in the metadata cache tables.  
- **Run Analyzer often:** Scores and usage badges drift if you skip weekly scans.  
- **Work in priority order:** High → Medium → Standard ensures the homepage and critical service pages stay polished.  
- **Document big changes:** Keep a brief change log whenever you delete, rename, or bulk-edit metadata so your team can trace issues quickly.

---

## 8. Troubleshooting
| Symptom | What to Check | Quick Fix |
| --- | --- | --- |
| Analyzer stops mid-run | See Diagnostics card (queue depth, errors). | Re-run Analyzer; if queues are stuck, try `Force Usage Refresh`. |
| Duplicate Deep Scan fails (`Bad Request: 0`) | Legacy endpoint limitation. | Retry once. If it persists, rely on Quick Scan + manual inspection and flag the issue to engineering. |
| Metadata not updating on site | Browser cache or CDN delaying changes. | Clear WordPress cache/CDN, confirm metadata drawer shows new version. |
| WebP images not displaying | Older browsers or theme override. | Check fallback mode (Settings tab). The optimizer automatically serves original file when needed. |
| Admin actions greyed out | User lacks `manage_options` capability. | Contact a site admin to grant the correct role. |
| Job queue backlog | Diagnostics card shows high pending count. | Leave the tab open; consider running `Optimize Selected` in smaller batches or contact support if backlog persists. |

---

## 9. Advanced Tips
- **Use Search filters** to isolate campaign-specific assets before a launch.  
- **Combine manual + AI workflows**: Accept AI suggestions for routine library images, but hand-craft copy for hero sections.  
- **Leverage WP-CLI** (admins only): `wp msh jobs status` to inspect queues, `wp msh qa` scripts for regression checks (see developer notes).  
- **Context overrides:** For images tied to a specific service or practitioner, add context via the attachment editor so future metadata honors those details.  
- **Trial token awareness (future standalone build):** Keep an eye on the token meter once AI tiers launch; the header will show remaining tokens and upgrade prompts.

---

## 10. Glossary
- **Analyzer:** The table view that scores and lists every published image.  
- **Priority Score:** Numeric value (0–20+) indicating how visible/important an image is; ≥15 is “High”.  
- **Quick Scan:** Fast duplicate detection using perceptual hashes; recommended first step.  
- **Deep Scan:** Detailed duplicate check that searches serialized data before delete/rename.  
- **Usage Index:** Background-maintained map of where each image appears across the site.  
- **Metadata Cache:** Active set of ALT text, captions, descriptions stored for each attachment/locale.  
- **Staleness Engine:** Service that flags metadata needing regeneration after content updates.  
- **Token:** Unit that measures AI usage (planned for commercial release).

---

## 11. Need Help?
- **Diagnostics download:** Use the link in the Diagnostics card to export logs for support.  
- **Raise issues:** Log tickets with time of action, attachment IDs, and any error messages; include whether Quick Scan or Deep Scan was used.  
- **Documentation hub:** Visit `docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md` for the full operator handbook and links to troubleshooting playbooks.

**Last Updated:** 2025-10-30 (User guide first edition)
