# Phase 4R+ User Manual
## Intelligent Metadata Orchestration

**Plugin:** The Dot Image Optimizer
**Version:** 2.0.0
**Last Updated:** October 19, 2025

---

## What is Phase 4R+?

Phase 4R+ transforms how The Dot manages image metadata. Instead of manually updating alt text, titles, captions, and descriptions every time your content changes, Phase 4R+ **automatically detects when metadata becomes outdated** and marks it for regeneration.

Think of it as a "metadata health monitor" that:

- 📊 **Tracks changes** - Knows when posts, images, or locales are updated
- 🔍 **Detects staleness** - Identifies outdated metadata automatically
- 📝 **Preserves history** - Keeps all versions for auditing
- 🤝 **Respects manual edits** - Your manual changes always take priority over AI
- ☁️ **Syncs to cloud** (Pro) - Optional backup to S3 or Supabase

---

## Key Concepts

### 1. Metadata Cache

**What it is:** A central database that stores BOTH AI-generated and manually-edited metadata for every image, in every language.

**Example:**

```
Image: rehabilitation-photo.jpg
Locale: Spanish (es_ES)
Field: Alt Text

AI Value: "Fisioterapeuta ayudando a paciente con rehabilitación de rodilla"
Manual Value: "Terapia física profesional"
Active: Manual (you edited it, so manual wins)
Status: Fresh
```

**Why it matters:** You can see both versions side-by-side and choose which to use.

### 2. Fingerprints

**What it is:** A unique "signature" calculated from everything that affects metadata:

- Where the image appears (which posts/pages)
- The image itself (file, dimensions)
- Language settings (locale profile)
- AI model and prompts
- Glossary terms

**Why it matters:** When the fingerprint changes, metadata is marked as "stale" and needs regeneration.

**Analogy:** Like a checksum for your metadata. If the checksum changes, something important changed.

### 3. Staleness Reasons

When metadata becomes outdated, Phase 4R+ tells you **why**:

| Reason | What Changed | Example |
|--------|--------------|---------|
| `context_changed` | Post content where image appears | You rewrote the blog post |
| `file_replaced` | Image file was replaced | You uploaded a new version |
| `locale_updated` | Language settings changed | You updated Spanish formality |
| `glossary_changed` | Glossary terms modified | You added new medical terms |
| `template_changed` | AI prompt template updated | You changed the alt text template |
| `manual_override` | You manually edited metadata | You typed your own alt text |

### 4. Event Bus

**What it is:** An event log that tracks every significant change in your WordPress site.

**Example Events:**

```
[2025-10-19 14:30:00] Post #123 updated - "About Our Services"
[2025-10-19 14:31:00] Image #456 uploaded - "team-photo.jpg"
[2025-10-19 14:32:00] Glossary updated - Spanish (es_ES)
```

**Why it matters:** Phase 5 will read this event log to know what metadata to regenerate.

---

## How It Works

### The Lifecycle of Image Metadata

```
1. You upload an image
   ↓
2. AI generates metadata (title, alt, caption, description)
   ↓
3. Fingerprint is calculated and stored
   ↓
4. You update the blog post where image appears
   ↓
5. Event Bus emits "post.updated" event
   ↓
6. Fingerprint changes (context changed)
   ↓
7. Metadata marked as STALE with reason "context_changed"
   ↓
8. Phase 5 worker regenerates metadata (future)
   ↓
9. New version saved, fingerprint updated
   ↓
10. Metadata marked as FRESH
```

### Manual vs. AI - Who Wins?

**Simple Rule:** Manual edits ALWAYS win.

**Example Scenario:**

1. AI generates alt text: "Fisioterapeuta ayudando a paciente con rehabilitación"
2. You manually edit to: "Terapia física profesional"
3. Post content changes (fingerprint changes)
4. Metadata marked as stale
5. Phase 5 regenerates AI version: "Fisioterapeuta trabajando con paciente en sesión de rehabilitación"
6. **Your manual version remains active** - AI version stored but not shown
7. You can see both versions and choose to switch

**Why this matters:** You're always in control. AI is a helper, not a dictator.

---

## Using WP-CLI Commands

Phase 4R+ is currently a backend system. The main interface is WP-CLI commands (terminal commands for WordPress).

### Prerequisites

- SSH access to your server
- WP-CLI installed
- Basic terminal knowledge

### Command 1: Check Fingerprint

**What it does:** Calculate the fingerprint for an image's metadata

**When to use:** Debugging why metadata is marked as stale

**Syntax:**

```bash
wp msh metadata fingerprint <image-id> <locale> <field> --verbose
```

**Example:**

```bash
wp msh metadata fingerprint 1686 es_ES alt --verbose
```

**Output:**

```
Success: Fingerprint: 102e047175bccad284f4b27915a0ffd9de735580

Signal Breakdown:
  - page_context: 112111a91ae1aea4ec4f7dc23748b28f
  - image_features: f0b2a22e21f60d46d5784b2639960deb
  - locale_profile: 71095c56c641f2c4a4f189b9dfcd7a38
  - template: 40bf2cd45f8bac148bbb696022119b69
  - model_prompt: 886f7e23fdc6054b5e6d7b4b0883f58c
  - glossary: d8e8fca2dc0f896fd7cb4cb0031ba249
```

**Understanding the output:**

- Main fingerprint: `102e047...` (40-character SHA1 hash)
- Each signal contributes to the fingerprint
- If ANY signal changes, the fingerprint changes

### Command 2: List Events

**What it does:** Show recent changes detected by the Event Bus

**When to use:** Monitoring what's triggering metadata regeneration

**Syntax:**

```bash
wp msh metadata events [--unprocessed] [--limit=50]
```

**Example:**

```bash
# Show only unprocessed events
wp msh metadata events --unprocessed

# Show all events (last 20)
wp msh metadata events

# Show last 100 events
wp msh metadata events --limit=100
```

**Output:**

```
ID  Event                Entity          User  Created              Processed
1   post.updated         post:123        1     2025-10-19 14:30:00  pending
2   attachment.uploaded  attachment:456  1     2025-10-19 14:31:00  2025-10-19 14:35:00
3   glossary.updated     site            1     2025-10-19 14:32:00  pending
```

**Understanding the output:**

- **ID:** Event identifier
- **Event:** What type of change happened
- **Entity:** What was changed (post, image, or site-wide)
- **User:** Who made the change (0 = system)
- **Processed:** When Phase 5 handled it (pending = not yet)

### Command 3: View Metadata Cache

**What it does:** Show stored metadata for an image

**When to use:** Checking what's stored for AI vs. manual

**Syntax:**

```bash
wp msh metadata cache <image-id> [--locale=es_ES]
```

**Example:**

```bash
# Show all metadata for image 1686
wp msh metadata cache 1686

# Show only Spanish metadata
wp msh metadata cache 1686 --locale=es_ES
```

**Output:**

```
Locale  Field      Source  Value                                          Stale             Updated
es_ES   title      manual  Rehabilitación profesional                    fresh             2025-10-19 14:00:00
es_ES   alt        ai      Fisioterapeuta ayudando a paciente con...     fresh             2025-10-19 14:01:00
es_ES   caption    manual  Terapia física especializada                  context_changed   2025-10-19 14:02:00
es_ES   description ai     Sesión de fisioterapia profesional...         fresh             2025-10-19 14:03:00
```

**Understanding the output:**

- **Source:** Which version is active (manual or ai)
- **Value:** First 60 characters of the metadata
- **Stale:** Why it needs regeneration (or "fresh" if current)

### Command 4: System Statistics

**What it does:** Show overall health of metadata system

**When to use:** Monthly checkups, debugging performance

**Syntax:**

```bash
wp msh metadata stats
```

**Output:**

```
Metric                Value
Total Events          1,247
Unprocessed Events    12
Processed Events      1,235
Total Metadata Cache  4,560
Stale Metadata        87
AI-Generated Active   2,134
Manual Active         2,426
Total Versions        8,920

Event Breakdown:
  - post.updated: 567
  - attachment.uploaded: 234
  - metadata.manual_edit: 189
  - glossary.updated: 78
  - locale.added: 12
```

**Key metrics:**

- **Stale Metadata:** How many items need regeneration
- **AI vs. Manual:** Distribution of active metadata sources
- **Total Versions:** How many historical versions stored

### Command 5: Test Event Emission

**What it does:** Manually trigger an event (for testing)

**When to use:** Debugging, testing Phase 5 workers

**Syntax:**

```bash
wp msh metadata test_event <event-name> <entity-type> <entity-id>
```

**Example:**

```bash
wp msh metadata test_event post.updated post 123
```

**Output:**

```
Success: Event emitted with ID: 47
```

---

## Common Workflows

### Workflow 1: Monthly Metadata Audit

**Goal:** Find and regenerate stale metadata

**Steps:**

1. Check statistics:

```bash
wp msh metadata stats
```

2. If "Stale Metadata" is high (>100), investigate:

```bash
wp msh metadata cache <image-id>
```

3. Look at "Stale" column to see reasons
4. Common reasons:
   - `context_changed` - Normal, posts were updated
   - `glossary_changed` - Expected after glossary updates
   - `file_replaced` - Normal, images were replaced

5. Phase 5 will auto-regenerate (future)

### Workflow 2: Investigating Slow Metadata Updates

**Goal:** Find bottlenecks in metadata system

**Steps:**

1. Check unprocessed events:

```bash
wp msh metadata events --unprocessed
```

2. If count is high (>50), workers may be slow
3. Check event age:

```bash
wp msh metadata events --unprocessed --limit=100
```

4. Look at "Created" column
5. If events are hours old, Phase 5 workers need attention

### Workflow 3: Troubleshooting Manual Edit Issues

**Goal:** Verify manual edit was saved correctly

**Steps:**

1. Check metadata cache:

```bash
wp msh metadata cache <image-id> --locale=<locale>
```

2. Verify "Source" column shows "manual"
3. Check "Stale" column:
   - Should show "manual_override" right after edit
   - Should show "fresh" after regeneration
4. If "Source" shows "ai" instead of "manual", manual edit was lost

### Workflow 4: Locale Profile Change Impact

**Goal:** See what metadata will regenerate after changing locale settings

**Steps:**

1. Before changing locale profile, note current fingerprint:

```bash
wp msh metadata fingerprint 1686 es_ES alt
# Note: 102e047175bccad284f4b27915a0ffd9de735580
```

2. Change locale profile in WordPress admin (Phase 3)
3. Recalculate fingerprint:

```bash
wp msh metadata fingerprint 1686 es_ES alt
# Note: 8f3a92b4c1d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9 (DIFFERENT!)
```

4. Check staleness:

```bash
wp msh metadata cache 1686 --locale=es_ES
# Should show stale_reason: locale_updated
```

5. Phase 5 will regenerate (future)

---

## Understanding the Data

### What Gets Stored

For **every image**, in **every language**, for **every field** (title, alt, caption, description):

```
Image: team-photo.jpg (ID: 1686)
Locale: Spanish (es_ES)

┌─────────────────────────────────────────────────────────────┐
│ TITLE                                                       │
├─────────────────────────────────────────────────────────────┤
│ AI: "Equipo profesional de fisioterapia"                   │
│ Manual: (empty)                                             │
│ Active: AI                                                  │
│ Fingerprint: 102e047175bccad284f4b27915a0ffd9de735580      │
│ Status: Fresh                                               │
│ Last Updated: 2025-10-19 14:00:00                          │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ ALT TEXT                                                    │
├─────────────────────────────────────────────────────────────┤
│ AI: "Fisioterapeuta ayudando a paciente con..."            │
│ Manual: "Terapia física profesional"                       │
│ Active: Manual (you edited it)                             │
│ Fingerprint: 8f3a92b4c1d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9      │
│ Status: Fresh                                               │
│ Last Updated: 2025-10-19 14:01:00                          │
└─────────────────────────────────────────────────────────────┘

(Caption and Description follow same pattern)
```

### Version History

Every time metadata changes, a new version is saved:

```
Image 1686 - Spanish Alt Text - Version History

Version 3 (Active)
├─ Value: "Terapia física profesional"
├─ Source: Manual
├─ Created: 2025-10-19 14:01:00
├─ Notes: "Simplified for better accessibility"
└─ Fingerprint: 8f3a92b4c1d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9

Version 2
├─ Value: "Fisioterapeuta ayudando a paciente con rehabilitación de rodilla"
├─ Source: AI
├─ Created: 2025-10-18 10:30:00
├─ Notes: (auto-generated)
└─ Fingerprint: 102e047175bccad284f4b27915a0ffd9de735580

Version 1
├─ Value: "Fisioterapeuta con paciente"
├─ Source: AI
├─ Created: 2025-10-15 09:00:00
├─ Notes: (initial generation)
└─ Fingerprint: 7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e
```

**Future Feature:** Admin UI to browse versions and restore old ones

---

## Best Practices

### 1. Trust the System

**Don't:**
- Manually regenerate all metadata after every small change
- Worry about "stale" metadata immediately

**Do:**
- Let Phase 5 workers handle regeneration automatically
- Check stats monthly to ensure system is healthy
- Investigate only if stale count exceeds 10% of total

### 2. Manual Edits Are Sacred

**Don't:**
- Mix manual and AI edits randomly across languages
- Overwrite manual edits unless necessary

**Do:**
- Use manual edits for critical accessibility fixes
- Document why you made manual edits (use Notes field in future UI)
- Review AI suggestions before manual editing

### 3. Monitor Event Bus

**Don't:**
- Ignore unprocessed events piling up
- Emit test events in production

**Do:**
- Check unprocessed count weekly: `wp msh metadata stats`
- Investigate if unprocessed > 50
- Use test events only in staging environment

### 4. Fingerprint Understanding

**Don't:**
- Try to reverse-engineer fingerprints manually
- Compare fingerprints across different images

**Do:**
- Use `--verbose` flag to see signal breakdown
- Focus on staleness reasons, not fingerprint values
- Document what caused fingerprint changes

---

## Limitations (Current Version)

### No Admin UI Yet

**What's missing:**
- Browse metadata cache in WordPress admin
- View version history visually
- Compare AI vs. manual side-by-side
- Restore old versions with one click

**Workaround:** Use WP-CLI commands (see above)

**Timeline:** Admin UI planned for Phase 5

### No Automatic Regeneration Yet

**What's missing:**
- Background workers to regenerate stale metadata
- Priority queue (manual edits first)
- Batch regeneration for efficiency

**Workaround:** Phase 5 will handle this

**Timeline:** Phase 5 in development

### No Cloud Sync Yet

**What's missing:**
- Sync metadata to S3 or Supabase
- Conflict resolution between local and remote
- Multi-site metadata sharing

**Workaround:** N/A (Pro feature)

**Timeline:** Phase 5 Pro version

---

## Troubleshooting

### Problem: Fingerprint command fails with error

**Error Message:**

```
PHP Fatal error: Call to undefined method...
```

**Cause:** Database tables from Phase 2 or 3 don't exist

**Solution:**

1. Check if Phase 2 (Context Fusion) is active
2. Check if Phase 3 (Locale Profiles) is active
3. Fingerprint Builder gracefully falls back, but warnings may appear
4. Safe to ignore if you see "Success: Fingerprint: ..." at the end

### Problem: Events table is empty

**Symptoms:**

```bash
wp msh metadata events
# Output: Warning: No events found.
```

**Cause:** Event Bus hasn't been triggered yet

**Solution:**

1. Make a change to trigger events:
   - Update a post with images
   - Upload a new image
   - Update a locale profile
2. Check again:

```bash
wp msh metadata events
```

3. If still empty, check if Event Bus is initialized:

```bash
wp eval "var_dump(class_exists('MSH_Event_Bus'));"
# Should output: bool(true)
```

### Problem: Stale metadata not regenerating

**Symptoms:**
- `wp msh metadata stats` shows "Stale Metadata: 50"
- Metadata stays stale for days

**Cause:** Phase 5 workers not implemented yet

**Solution:**

- This is expected in current version
- Phase 5 will add automatic regeneration
- Manual regeneration available in future UI

### Problem: Manual edit was overwritten

**Symptoms:**
- You edited alt text manually
- Later, AI version is showing instead

**Cause:** Bug in decision layer (should never happen)

**Solution:**

1. Check metadata cache:

```bash
wp msh metadata cache <image-id> --locale=<locale>
```

2. If "Source" shows "ai", something went wrong
3. Re-edit manually
4. Report bug to development team

---

## FAQ

### Q: Will Phase 4R+ slow down my site?

**A:** No. Event emission is fast (<10ms). Fingerprint calculation only happens on-demand via WP-CLI. Phase 5 workers will run in background.

### Q: How much database space does this use?

**A:** Approximately:
- 100 images × 5 locales × 4 fields = 2,000 cache rows (~500 KB)
- 10 versions per field = 20,000 version rows (~5 MB)
- Events auto-delete after 30 days (~1 MB steady state)

**Total:** ~6-7 MB for typical site

### Q: Can I disable Phase 4R+ if I don't need it?

**A:** Yes. Simply deactivate the plugin. Data is preserved but no new events are emitted.

### Q: Does this work with Polylang/WPML?

**A:** Phase 4R+ is locale-agnostic. It stores metadata per locale code (e.g., 'es_ES', 'fr_FR'). Works with any translation plugin.

### Q: Can I export metadata cache?

**A:** Not in current version. Phase 5 will add CSV export. For now, use:

```bash
wp msh metadata cache <image-id> --format=json > metadata.json
```

### Q: What happens if I delete an image?

**A:** WordPress automatically deletes associated metadata via foreign key constraints (future). Currently, orphaned metadata remains in cache table.

### Q: Can I see what changed between versions?

**A:** Not in current version. Phase 5 admin UI will show visual diffs.

### Q: Does Phase 4R+ work without Phase 2 or 3?

**A:** Yes, but with reduced functionality:
- No page context analysis (Phase 2 required)
- No locale profiles (Phase 3 required)
- Fingerprints fall back to simple hashes

---

## Glossary

**Attachment ID:** WordPress internal ID for media uploads (e.g., 1686)

**Cache:** Database table storing current metadata (both AI and manual versions)

**Chosen Source:** Which metadata version is active (manual or ai)

**Entity:** Thing being tracked (post, attachment, or site)

**Event Bus:** System that logs and distributes change notifications

**Field:** Type of metadata (title, alt, caption, or description)

**Fingerprint:** SHA1 hash calculated from input signals (40 characters)

**Idempotency:** Preventing duplicate events (same input = same result)

**Input Signals:** Data points used to calculate fingerprint

**Locale:** Language and region code (e.g., 'en_US', 'es_ES')

**Payload:** Event data (JSON-encoded details)

**Staleness Reason:** Why metadata needs regeneration

**Version:** Historical snapshot of metadata value

**WP-CLI:** WordPress Command Line Interface (terminal commands)

---

## Getting Help

### Support Channels

**Documentation:**
- Technical: `/docs/phase4-technical.md`
- User Manual: `/docs/phase4-manual.md` (this file)

**Command Help:**

```bash
wp help msh metadata
wp help msh metadata fingerprint
wp help msh metadata events
```

**Contact:**

- Email: support@thedot.com
- Documentation: https://docs.thedot.com
- GitHub Issues: https://github.com/thedot/image-optimizer/issues

### Before Contacting Support

Please gather this information:

1. Plugin version:

```bash
wp plugin list --name=msh-image-optimizer
```

2. System stats:

```bash
wp msh metadata stats
```

3. Recent events:

```bash
wp msh metadata events --limit=20
```

4. Sample fingerprint:

```bash
wp msh metadata fingerprint <problem-image-id> <locale> alt --verbose
```

5. Error logs (if applicable):
   - Location: `/wp-content/debug.log`
   - Enable: Set `WP_DEBUG` and `WP_DEBUG_LOG` to true in `wp-config.php`

---

## What's Next?

### Phase 5: Metadata Hub (Admin UI + Automation)

**Coming Soon:** All Phase 4R+/5 features consolidated under one **"Metadata Hub"** menu item with tabbed navigation.

```
📊 The Dot Menu
├── Dashboard
├── ──────────
├── Image Optimizer
├── Context Analytics
├── Locale Profiles
├── Glossary
├── Metadata Hub ← NEW
│   └── Tabs:
│       ├── [Cache] - Browse metadata (AI vs Manual)
│       ├── [History] - Version timeline & rollback
│       ├── [Queue] - Regeneration worker status
│       ├── [Events] - Event log monitoring
│       └── [Sync] 🔒 PRO - Cloud sync
├── ──────────
└── Settings
```

### Tab 1: Cache Browser

**What you'll be able to do:**

- Browse all image metadata in one place
- Filter by locale, staleness, source (AI/manual)
- Search images by name or ID
- Compare AI vs. manual side-by-side
- Switch between AI and manual versions
- Bulk regenerate selected images
- Export to CSV

**Visual Preview:**

```
Filters: [Locale: All ▾] [Staleness: All ▾] [Source: All ▾]

Image         Field   Locale  Source  Status          Updated
🖼️ team.jpg    Alt     es_ES   Manual  Fresh           Oct 19
              Title   es_ES   AI      Stale¹          Oct 18
🖼️ rehab.jpg   Alt     es_ES   AI      Fresh           Oct 19
              Alt     fr_FR   AI      Stale²          Oct 15

¹ context_changed  ² locale_updated

Click any row → See both AI and manual versions, switch between them
```

### Tab 2: Version History

**What you'll be able to do:**

- View timeline of all metadata changes
- Filter by image, locale, or field
- See who made each change (user or AI)
- View diffs (before/after comparison)
- Restore old versions with one click
- Add notes to manual edits

**Visual Preview:**

```
Timeline View

Oct 19, 2025 14:01
🖼️ team.jpg - Alt - es_ES
✏️ Manual Edit by admin
"Terapia física profesional"
← Changed from: "Fisioterapeuta ayudando..."
[View Diff] [Restore This Version]

Oct 18, 2025 10:30
🖼️ team.jpg - Alt - es_ES
🤖 AI Regeneration (trigger: post.updated #123)
"Fisioterapeuta ayudando a paciente con..."
Notes: Auto-regenerated due to context change
[View Diff] [Restore This Version]
```

### Tab 3: Regeneration Queue

**What you'll be able to do:**

- Monitor background regeneration workers
- See queue status (pending, processing, complete)
- Manually trigger "Regenerate All Stale"
- Skip or prioritize specific images
- Pause/resume workers
- View progress bars for active jobs

**Visual Preview:**

```
Queue Status: 87 Stale | 12 Processing | 456 Fresh
Worker Health: ● Active (last run: 2 min ago)

Priority Queue
🔴 High Priority (Manual Overrides)
team.jpg - Alt - es_ES (stale: context_changed)
[Skip] [Process Now]

🟡 Medium Priority (Glossary Changes)
rehab.jpg - Title - es_ES (stale: glossary_changed)
[Skip] [Process Now]

Currently Processing
doctor.jpg - Alt - de_DE
[████████░░] 80% complete (ETA: 30 sec)
```

### Tab 4: Event Log

**What you'll be able to do:**

- Monitor all system events in real-time
- Filter by event type
- See which user triggered each event
- View event details and payload
- Export events to CSV
- Debug staleness issues

**Visual Preview:**

```
[Live Feed: ● On ▾] [Filter: All Events ▾]

Time     Event               Entity          User   Status
14:32:05 glossary.updated    site            admin  ✓
14:31:42 attachment.uploaded attachment:789  admin  ✓
14:30:15 post.updated        post:123        admin  ⏳ Pending
14:29:30 metadata.manual_edit attachment:456 admin  ✓

Click event → See full payload, affected images, related events
```

### Tab 5: Cloud Sync (Pro Feature)

**What Pro users will be able to do:**

- Sync metadata to S3 or Supabase
- Share metadata across multiple sites
- Automatic backup with version control
- Conflict resolution (local vs. remote)
- Team collaboration
- Manual push/pull control

**Visual Preview (Pro Active):**

```
Status: ✓ Connected to S3 (us-east-1)
Last Sync: 2 minutes ago
Sync Mode: Auto-push on change

Sync Statistics
Total Synced: 4,560 metadata entries
Pending Push: 12 items
Conflicts: 0

[Manual Push All] [Manual Pull All]

Recent Sync Activity
✓ 14:30 - Pushed team.jpg alt (es_ES)
✓ 14:28 - Pushed rehab.jpg title (fr_FR)
⚠️ 14:25 - Conflict: office.jpg caption (de_DE)
         [Use Local | Use Remote | Merge]
```

**Visual Preview (Free Users):**

```
🔒 Cloud Sync - Pro Feature

Unlock powerful cloud synchronization:

✓ Sync metadata across multiple sites
✓ Team collaboration with conflict resolution
✓ Automatic backup to S3 or Supabase
✓ Multi-site metadata sharing
✓ Export/import with version control

[Upgrade to Pro - $99/year]  [Learn More]
```

### Automated Regeneration (Background Workers)

**Coming in Phase 5:**

- Background workers consume events automatically
- Batch regeneration (process 50 images at once)
- Priority queue (manual edits regenerate first)
- Smart cost optimization (group similar images)
- WP-Cron integration or external queue
- Health monitoring and alerts

---

## Changelog

### Version 2.0.0 (2025-10-19)

**New:**
- ✅ Event Bus system with 7 event types
- ✅ Fingerprint Builder with 6 input signals
- ✅ Metadata cache with AI + manual coexistence
- ✅ Version history tracking
- ✅ WP-CLI commands for inspection
- ✅ Idempotent event emission
- ✅ Staleness detection with reasons

**Changed:**
- Removed Phase 4 workflow features (Version History UI, A/B Testing, Approval Queue)
- Pivoted from enterprise governance to metadata infrastructure

**Known Limitations:**
- No admin UI yet (WP-CLI only)
- No automatic regeneration (Phase 5)
- No cloud sync (Phase 5 Pro)

---

**End of User Manual**

For technical details, see `/docs/phase4-technical.md`
