# AI #2: Track B Completion - Your Next Tasks

**Date:** October 20, 2025
**Status:** 🚀 Ready to Resume Track B Development
**From:** AI #1 (Backend completed Track A testing prep)
**Priority:** HIGH - Complete Track B before Phase 7

---

## Context: Where We Are

### ✅ Track A Status (AI #1's Work)
- Backend infrastructure: **COMPLETE**
- WP-CLI commands: **COMPLETE & TESTED** (90% pass rate)
- Job queue system: **COMPLETE**
- Automation triggers: **COMPLETE**
- **Next:** User testing tomorrow (Oct 21, browser-based)

### 🚧 Track B Status (AI #2's Work - YOUR WORK)
**Hub Page Progress:**
- ✅ Main Hub controller (`admin/class-msh-hub-page.php`) - EXISTS
- ✅ Queue tab - **READY FOR TESTING**
- ✅ Metadata tab - **FIXED (your metadata row actions fix works!)**
- 🚧 Events tab - **INCOMPLETE**
- 🚧 History tab - **INCOMPLETE**
- 🚧 Sync tab - **INCOMPLETE**
- ⏸️ REST API endpoints - **NOT STARTED**

---

## Your Current Task: Complete Track B

### Priority 1: Finish Hub Tabs (HIGH)

#### Tab 1: Events Tab ⏭️ NEXT
**File:** Already integrated in `admin/class-msh-hub-page.php` (lines 665-784)

**Current State:**
- Tab exists with basic structure
- Shows recent events
- Has pause/resume functionality
- Empty state message

**What Needs Work:**
1. ✅ **Backend exists** - `ajax_get_recent_events()` handler (line 1245)
2. 🚧 **Frontend incomplete:**
   - Auto-refresh not working properly
   - Event formatting needs polish
   - Filters (by type, by media ID) not implemented
   - Live feed indicator needs improvement

**Your Tasks:**
- [ ] Test auto-refresh functionality
- [ ] Add event type filters (regenerate, upload, edit, etc.)
- [ ] Add media ID filter
- [ ] Improve event formatting (icons, timestamps, readable messages)
- [ ] Polish pause/resume UI
- [ ] Add "Clear old events" button (optional)

**Estimated Time:** 1-2 days

---

#### Tab 2: History Tab ⏭️ AFTER EVENTS
**File:** Already integrated in `admin/class-msh-hub-page.php` (lines 785-845)

**Current State:**
- Tab exists with basic structure
- Shows version timeline concept
- Empty state message
- Needs full implementation

**What Needs Work:**
1. ✅ **Backend exists** - Metadata versioning system ready
2. 🚧 **Frontend incomplete:**
   - Version timeline not implemented
   - Diff comparison not implemented
   - Rollback functionality not implemented

**Your Tasks:**
- [ ] Build version timeline UI (list of versions by attachment/locale/field)
- [ ] Add version comparison view (show diff between versions)
- [ ] Add rollback button (revert to previous version)
- [ ] Add filters (by attachment, by locale, by field, by date)
- [ ] Show who created each version (AI vs manual vs migration)
- [ ] Show approval status

**Backend Help Available:**
- `MSH_Metadata_Versioning::get_versions($attachment_id, $locale, $field)` - Get version list
- `MSH_Metadata_Versioning::get_version($version_id)` - Get specific version
- `MSH_Metadata_Versioning::activate_version($version_id)` - Rollback to version

**Estimated Time:** 2-3 days

---

#### Tab 3: Sync Tab ⏭️ AFTER HISTORY
**File:** Already integrated in `admin/class-msh-hub-page.php` (lines 846-934)

**Current State:**
- Tab exists with Pro upsell
- Shows feature preview
- Not functional (Pro feature)

**What Needs Work:**
1. ⏸️ **Backend pending** - Remote Sync class exists but not configured
2. 🚧 **Frontend needs polish:**
   - Upsell card looks basic
   - No preview of sync features
   - No configuration UI (even for Pro users)

**Your Tasks:**
- [ ] Polish upsell card design
- [ ] Add feature preview (what sync does)
- [ ] Add "Coming Soon" or "Pro Feature" badge
- [ ] Build configuration UI for Pro users:
  - [ ] S3 bucket configuration
  - [ ] Supabase configuration
  - [ ] Test connection button
  - [ ] Sync status display
  - [ ] Manual sync trigger button

**Note:** Full sync functionality is Track C work, but UI should be ready

**Estimated Time:** 1-2 days

---

### Priority 2: REST API Endpoints (MEDIUM)

**Status:** ⏸️ NOT STARTED

**Files to Create:**
1. `includes/rest/class-msh-rest-metadata.php`
2. `includes/rest/class-msh-rest-queue.php`

**Required Endpoints:**

#### Metadata Endpoints
```php
GET  /wp-json/msh/v1/metadata/cache
POST /wp-json/msh/v1/metadata/regenerate
GET  /wp-json/msh/v1/metadata/versions
POST /wp-json/msh/v1/metadata/activate
```

#### Queue Endpoints
```php
GET  /wp-json/msh/v1/jobs/status
POST /wp-json/msh/v1/jobs/process
GET  /wp-json/msh/v1/jobs/list
POST /wp-json/msh/v1/jobs/retry
POST /wp-json/msh/v1/jobs/clear
```

**Why Needed:**
- External integrations
- Headless WordPress support
- Mobile app support (future)
- Third-party tools integration

**Backend Help Available:**
- All helper functions from AI #1 ready to use
- Check `docs/interface-contract.md` for function signatures

**Estimated Time:** 2-3 days

---

## Timeline Estimate

**Total Track B Completion:** 6-10 days

| Task | Time | Dependencies |
|------|------|--------------|
| Events tab polish | 1-2 days | None - start now |
| History tab implementation | 2-3 days | After Events |
| Sync tab polish | 1-2 days | After History |
| REST API endpoints | 2-3 days | Can do in parallel |

**Parallel Work Possible:**
- Events/History/Sync tabs = sequential (one at a time)
- REST API = can do in parallel with tabs

**Recommended Approach:**
1. Days 1-2: Events tab
2. Days 3-5: History tab
3. Days 6-7: Sync tab
4. Days 5-10: REST API (parallel with Sync tab)

---

## What You Have Available

### Backend Systems (AI #1 Built These)

**Job Queue System:**
- `msh_enqueue_job($type, $entity_id, $payload)` - Create job
- `msh_process_queue($batch_size, $priority)` - Process jobs
- `msh_get_job_stats()` - Get queue statistics
- `msh_clear_failed_jobs()` - Clear failed jobs

**Metadata Versioning:**
- `MSH_Metadata_Versioning::get_versions()` - Get version history
- `MSH_Metadata_Versioning::create_version()` - Create new version
- `MSH_Metadata_Versioning::activate_version()` - Make version active
- `MSH_Metadata_Versioning::get_version()` - Get specific version

**Telemetry:**
- `msh_telemetry($event, $data)` - Log events

### Database Tables Ready

- `wp_msh_jobs` - Job queue
- `wp_optimizer_metadata_cache` - Active metadata
- `wp_optimizer_metadata_versions` - Version history
- `wp_msh_telemetry` - Event log

### Your Files (Already Created)

- `admin/class-msh-hub-page.php` - Main controller
- `assets/js/hub.js` - JavaScript
- `assets/css/hub.css` - Styles

---

## Testing Protocol

### How to Test Your Work

**1. Browser Testing:**
- Navigate to Hub page: `wp-admin/admin.php?page=msh-optimizer-hub`
- Test each tab individually
- Check browser console for JavaScript errors
- Verify AJAX calls work

**2. Backend Verification:**
```bash
# Check if events are being logged
wp db query "SELECT * FROM wp_msh_telemetry ORDER BY timestamp DESC LIMIT 10"

# Check if versions exist
wp db query "SELECT * FROM wp_optimizer_metadata_versions ORDER BY created_at DESC LIMIT 10"
```

**3. User Acceptance:**
- Tomorrow (Oct 21) user will test Track A (Queue tab, metadata row actions)
- You should have Events tab ready for testing by Oct 22-23
- Full Track B testing by Oct 25-28

---

## Success Criteria

### Track B Complete When:
- ✅ Events tab fully functional (auto-refresh, filters, formatting)
- ✅ History tab shows version timeline and diff comparison
- ✅ Sync tab shows polished upsell + config UI for Pro
- ✅ REST API endpoints implemented and tested
- ✅ All tabs load without JavaScript errors
- ✅ All AJAX calls work correctly
- ✅ User can navigate all tabs smoothly

---

## Communication

### What AI #1 is Doing (Me)
- **Today (Oct 20):** Completed Track A testing prep, created documentation
- **Tomorrow (Oct 21):** User testing Track A (browser-based)
- **Oct 22+:** Fix any Track A bugs found, then available for Track C or Phase 7

### What You Should Do (AI #2)
- **Oct 21:** Start Events tab implementation
- **Oct 22-23:** Complete Events tab
- **Oct 24-25:** Complete History tab
- **Oct 26-27:** Complete Sync tab
- **Oct 28-30:** Complete REST API

### Coordination Points
- If you need backend help, check `docs/interface-contract.md` first
- If you need new backend functions, we can coordinate
- Test your work regularly (don't wait until the end)
- User will test as features become ready

---

## Files You'll Modify

### Main Files
- `admin/class-msh-hub-page.php` - Polish existing tabs
- `assets/js/hub.js` - Add functionality for tabs
- `assets/css/hub.css` - Polish styles

### New Files to Create
- `includes/rest/class-msh-rest-metadata.php` - Metadata REST API
- `includes/rest/class-msh-rest-queue.php` - Queue REST API

### Files to Register New REST Classes
- `msh-image-optimizer.php` - Add REST class initialization

---

## Next Steps (Immediate)

### Step 1: Review Current State (30 min)
- Read this document fully
- Check `admin/class-msh-hub-page.php` (Events, History, Sync tab code)
- Check `assets/js/hub.js` (current JavaScript)
- Understand what's already there

### Step 2: Plan Events Tab (30 min)
- Sketch out event type filters UI
- Plan media ID filter implementation
- Design event formatting (icons, colors, timestamps)
- List AJAX calls needed

### Step 3: Start Implementation (Rest of Day 1)
- Implement event type filters
- Add media ID filter
- Improve event formatting
- Test auto-refresh

### Step 4: Continue Sequential (Days 2-10)
- Complete Events tab (Days 1-2)
- Build History tab (Days 3-5)
- Polish Sync tab (Days 6-7)
- Create REST API (Days 5-10, parallel)

---

## Questions?

**Q: Should I wait for Track A testing results?**
**A:** No, start now. Track A testing is separate (Queue tab, which is already ready).

**Q: Can I modify AI #1's backend files?**
**A:** No, use helper functions and create REST endpoints. Don't modify `includes/` files.

**Q: What if I need new backend functionality?**
**A:** Check `docs/interface-contract.md` first. If not available, we can coordinate to add it.

**Q: Should I test as I go?**
**A:** YES! Test each tab individually as you complete it. Don't wait.

**Q: When will this be tested by the user?**
**A:** Events tab ~Oct 22-23, History/Sync ~Oct 25-28, Full Track B ~Oct 28-30

---

## Resources

### Documentation
- [Interface Contract](docs/interface-contract.md) - Backend APIs you can use
- [AI2 Onboarding](docs/ai2-onboarding-instructions.md) - Your original instructions
- [Track B Overview](TRACKS-OVERVIEW.md) - What Track B includes

### Backend Help
- Helper functions: Check `includes/class-msh-helper-functions.php`
- Metadata versioning: Check `includes/class-msh-metadata-versioning.php`
- Job engine: Check `includes/automation/class-msh-job-engine.php`

### Testing
- Hub URL: `http://thedot-optimizer-test.local/wp-admin/admin.php?page=msh-optimizer-hub`
- Debug log: `.../wp-content/debug.log`
- Browser DevTools: F12 for JavaScript console

---

## Let's Go! 🚀

**Your Goal:** Complete Track B in 6-10 days (by Oct 28-30)

**Start with:** Events tab implementation (today/tomorrow)

**End with:** All Hub tabs functional + REST API working

**Then:** Phase 7 frontend work can begin!

---

**Any questions before you start? Let's get Track B done!**
