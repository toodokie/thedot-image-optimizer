# Testing Checklist for Tomorrow

**Date:** October 21, 2025
**Time Estimate:** 1-2 hours
**Status:** Ready to test

---

## Quick Start

### WordPress Admin Login
URL: http://thedot-optimizer-test.local/wp-admin/

### Testing Order
1. WordPress UI Testing (Hub tabs)
2. Image Upload & Automation
3. WP-CLI Verification
4. Report Issues

---

## Part 1: WordPress UI Testing (30-45 min)

### Hub → Queue Tab ✓

**Navigate to:** The Dot → Optimizer Hub → Queue

**Test:**
- [ ] Page loads without errors
- [ ] Stats display shows: Pending, Processing, Complete, Failed counts
- [ ] Priority breakdown shows: High, Medium, Normal
- [ ] Auto-refresh checkbox toggles updates (every 5 seconds)
- [ ] "Process Now" button exists and is clickable
- [ ] If failed jobs exist:
  - [ ] "Clear Failed Jobs" button appears
  - [ ] Click button → confirmation dialog
  - [ ] Confirm → shows "Clearing..." → success toast
  - [ ] Failed count goes to 0

**Expected Result:** All stats and buttons work correctly

---

### Hub → Metadata Tab ✓

**Navigate to:** The Dot → Optimizer Hub → Metadata

**⚠️ IMPORTANT:** Hard-refresh page first (Cmd+Shift+R or Ctrl+Shift+R)

**Test each row action button:**

**1. Preview Button:**
- [ ] Click Preview on any row
- [ ] Modal opens with metadata details
- [ ] Shows attachment ID, locale, source
- [ ] Shows field values (title, alt, caption, description)
- [ ] Close button works

**Expected:** Modal displays without "Entry not found" error

**2. Copy Button:**
- [ ] Click Copy on any row
- [ ] Toast appears: "Copied to clipboard!"
- [ ] Paste somewhere to verify (Cmd+V)
- [ ] Content is copied correctly

**Expected:** Either success toast OR fallback toast:
> "Copied the visible value because the metadata record was unavailable."

**3. Edit Button:**
- [ ] Click Edit on any row
- [ ] Modal opens with form fields
- [ ] Edit some text
- [ ] Click "Save Changes"
- [ ] Success toast appears
- [ ] Table refreshes

**Expected:** Changes save as new manual version

**4. Lock Button:**
- [ ] Click Lock on any row
- [ ] Toast appears: "Entry locked" or "Entry unlocked"
- [ ] Icon changes (locked/unlocked indicator)

**Expected:** Lock status toggles correctly

**5. Regenerate Button:**
- [ ] Click Regenerate on any row
- [ ] Toast appears: "Regeneration queued"
- [ ] Go to Queue tab → pending count increases

**Expected:** Job appears in queue

---

### Hub → Events Tab ✓

**Navigate to:** The Dot → Optimizer Hub → Events

**Test:**
- [ ] Page loads with event feed
- [ ] Events are listed (or empty state if no events)
- [ ] "Pause Live Feed" button exists
- [ ] Click pause → button changes to "Resume Live Feed"
- [ ] Status text changes: "Live feed paused"

**Expected:** Live feed pauses/resumes correctly

---

### Hub → History Tab ✓

**Navigate to:** The Dot → Optimizer Hub → History

**Test:**
- [ ] Page loads without errors
- [ ] Shows empty state OR version timeline

**Expected:** Empty state with message:
> "No Version History Yet. Once you start optimizing images..."

---

### Hub → Sync Tab ✓

**Navigate to:** The Dot → Optimizer Hub → Sync

**Test:**
- [ ] Page loads without errors
- [ ] Shows Pro upsell card (since you're on free tier)
- [ ] "Learn More" and "Upgrade to Pro" buttons exist

**Expected:** Pro upsell displays correctly

---

## Part 2: Image Upload & Automation (15-30 min)

### Test Automation Triggers

**Navigate to:** Media → Add New

**Test Steps:**

1. **Before Upload:**
   - [ ] Go to Hub → Queue tab
   - [ ] Note current Pending count (should be 0 or low)

2. **Upload Image:**
   - [ ] Click "Select Files" or drag-drop
   - [ ] Choose any JPG/PNG image from your computer
   - [ ] Wait for upload to complete

3. **Check Queue:**
   - [ ] Go to Hub → Queue tab (refresh if needed)
   - [ ] Pending count increased by 4-8 jobs
   - [ ] Jobs show in list with status "pending"

4. **Process Jobs:**
   - [ ] Click "Process Now" button
   - [ ] Wait 30-60 seconds
   - [ ] Refresh page if needed
   - [ ] Jobs move to "complete" or "failed"

5. **Check Results:**
   - [ ] Go to Hub → Metadata tab
   - [ ] Find your uploaded image in the list
   - [ ] Verify metadata was generated (or job failed with error message)

**Expected:** Jobs are auto-created and can be processed

---

## Part 3: WP-CLI Verification (15 min)

### Terminal Commands

Open Terminal and navigate to project:
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
```

Set up shortcuts:
```bash
WP_PATH="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
WP_CLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"
```

### Test Commands:

**1. Check Queue Status:**
```bash
$WP_CLI msh jobs status --path="$WP_PATH"
```
- [ ] Command runs without errors
- [ ] Shows queue statistics
- [ ] Health status displays

**2. List Jobs:**
```bash
$WP_CLI msh jobs list --limit=10 --path="$WP_PATH"
```
- [ ] Command runs without errors
- [ ] Shows job table
- [ ] Includes: ID, job_type, entity_id, priority, status

**3. Create Test Job:**
```bash
$WP_CLI db query "INSERT INTO wp_msh_jobs (job_type, entity_type, entity_id, payload, priority, status, created_at) VALUES ('regenerate_metadata', 'attachment', 2049, '{\"locale\":\"en_US\",\"field\":\"title\"}', 'high', 'pending', NOW())" --path="$WP_PATH"
```
- [ ] Command runs without errors
- [ ] Shows "Success: Query succeeded"

**4. Process Job:**
```bash
$WP_CLI msh jobs process --batch=1 --path="$WP_PATH"
```
- [ ] Command runs (may take 30-60 seconds)
- [ ] Shows "Processing up to 1 job(s)..."
- [ ] Shows result: "Processed: X | Failed: Y"

**5. Clear Failed Jobs:**
```bash
$WP_CLI msh jobs clear --status=failed --yes --path="$WP_PATH"
```
- [ ] Command runs without errors
- [ ] Shows "Success: Deleted X job(s)"

---

## Part 4: Issue Tracking

### If Something Doesn't Work

**For each issue, note:**

1. **What you tested:**
   - Example: "Hub → Queue tab → Clear Failed Jobs button"

2. **What happened:**
   - Example: "Button clicked, but no confirmation dialog appeared"

3. **Expected behavior:**
   - Example: "Should show 'Are you sure?' confirmation"

4. **Console errors (if any):**
   - Open browser DevTools (F12)
   - Check Console tab
   - Copy any red error messages

5. **WordPress debug log (if PHP error):**
   - Check: `/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/debug.log`

---

## Quick Reference

### Important URLs
- WordPress Admin: http://thedot-optimizer-test.local/wp-admin/
- Hub Page: http://thedot-optimizer-test.local/wp-admin/admin.php?page=msh-optimizer-hub
- Queue Tab: ...?page=msh-optimizer-hub&tab=queue
- Metadata Tab: ...?page=msh-optimizer-hub&tab=cache

### Important Paths
- WP Path: `/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public`
- Plugin Path: `.../wp-content/plugins/msh-image-optimizer`
- Debug Log: `.../wp-content/debug.log`

### Browser DevTools
- **Open:** F12 or Cmd+Option+I (Mac)
- **Console Tab:** See JavaScript errors
- **Network Tab:** See AJAX requests/responses

---

## Success Criteria

### Minimum Requirements ✓
- [ ] All Hub tabs load without fatal errors
- [ ] Queue tab shows stats and buttons work
- [ ] Metadata row actions work (at least Copy and Preview)
- [ ] Image upload creates jobs automatically
- [ ] WP-CLI commands execute successfully

### Nice to Have ✓
- [ ] Jobs process successfully (generate metadata)
- [ ] Edit/Lock buttons work correctly
- [ ] Auto-refresh works smoothly
- [ ] No JavaScript console errors
- [ ] All buttons show proper feedback (toasts, disabled states)

---

## Time Estimates

| Task | Time | Priority |
|------|------|----------|
| Hub tabs walkthrough | 20 min | High |
| Metadata row actions | 15 min | High |
| Image upload test | 15 min | High |
| WP-CLI tests | 15 min | Medium |
| Issue documentation | 15-30 min | As needed |

**Total:** 1-2 hours

---

## Notes for Tomorrow

**What's been fixed:**
- ✅ Context Manager dependency (jobs can process)
- ✅ Metadata row actions (Preview/Copy/Edit/Lock) now use correct tables (AI #2)
- ✅ Copy button has fallback with clear message
- ✅ Clear Failed Jobs button fully functional
- ✅ WP-CLI commands complete and tested

**What to focus on:**
1. Verify AI #2's fixes work in browser
2. Test complete image upload → process → view metadata workflow
3. Confirm all buttons show proper feedback
4. Document any remaining issues

**If you find issues:**
- Don't panic! Document them clearly
- Check browser console and WordPress debug log
- We can fix them in the next session

---

## Quick Test Script (Optional)

If you want to run automated tests via CLI:

```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
./test-automation.sh
```

This will:
- Check queue status
- Upload test image
- Verify jobs created
- Process jobs
- Show results

**Time:** ~30 seconds

---

**Everything is ready for testing!** 🚀

See you tomorrow! If you have any questions, refer to the main documentation:
`/Users/anastasiavolkova/msh-image-optimizer-standalone/AUTOMATION-INFRASTRUCTURE-COMPLETE.md`
