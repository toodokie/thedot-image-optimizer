# Testing Instructions - Oct 14, 2025

## What Was Fixed

This testing session covers the following fixes:

1. **Edit Button Icon** - Replaced dashicon with custom PNG icon
2. **Usage Index Rebuild** - Populated empty usage index
3. **Reference Distribution Mapping** - Fixed context_type mapping to support both legacy and current schema values

---

## Pre-Testing Setup

### 1. Clear Browser Cache
**CRITICAL:** Hard refresh your browser to clear CSS and JavaScript cache:
- **Mac:** `Cmd + Shift + R`
- **Windows/Linux:** `Ctrl + Shift + R`

### 2. Verify Plugin Files Updated
Check the file timestamps to confirm changes were applied:
```bash
ls -la /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/js/image-optimizer-modern.js
ls -la /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/css/image-optimizer-admin.css
ls -la /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php
```

All should show timestamp: `Oct 14 17:XX` (current time)

---

## Test 1: Edit Button Icon Display

### What to Test
The edit button next to optimized filenames should now display as a small pen/pencil icon (from edit.png), not as "||" or broken dashicon.

### Steps
1. Navigate to the Image Optimizer admin page
2. Run **Analyze** if needed to populate the results table
3. Locate any image with an optimized filename (shows in the "Filename" column)
4. Look for the edit button next to the filename

### Expected Results
✅ **PASS:** Small pen/pencil icon appears next to the filename
✅ **PASS:** Icon is semi-transparent (60% opacity)
✅ **PASS:** Icon becomes fully opaque on hover
✅ **PASS:** Icon is properly sized (~16px × 16px)

❌ **FAIL:** Icon shows as "||" or broken text
❌ **FAIL:** Icon is missing or shows broken image placeholder
❌ **FAIL:** Icon is too large/small or misaligned

### Debug Steps if Failed
1. Open browser console (F12 → Console tab)
2. Check for 404 errors loading `edit.png`
3. Verify the icon path in Network tab:
   - Should be: `http://thedot-optimizer-test.local/wp-content/plugins/msh-image-optimizer/assets/icons/edit.png`
4. Check if `mshImageOptimizer.pluginUrl` is defined:
   ```javascript
   console.log(mshImageOptimizer.pluginUrl);
   ```
   Should output: `http://thedot-optimizer-test.local/wp-content/plugins/msh-image-optimizer`

---

## Test 2: Edit Filename Functionality

### What to Test
The edit button should allow inline editing and renaming of already-applied filenames.

### Steps
1. Click the edit icon next to any optimized filename
2. Input field should appear with current filename
3. Modify the filename (e.g., change `resinous-532-minneapolis.jpg` to `resinous-equipment-532-minneapolis.jpg`)
4. Click **Save** or press **Enter**
5. Wait for success message

### Expected Results
✅ **PASS:** Input field appears with current filename pre-filled
✅ **PASS:** Save and Cancel buttons appear
✅ **PASS:** Enter key saves, Escape key cancels
✅ **PASS:** On save: success message appears ("Filename updated successfully!")
✅ **PASS:** Filename in the table updates to new value
✅ **PASS:** File is actually renamed in Media Library (verify in WP Media)

❌ **FAIL:** Input doesn't appear or is empty
❌ **FAIL:** Save button doesn't work or shows error
❌ **FAIL:** Filename updates in UI but file isn't actually renamed

### Debug Steps if Failed
1. Open browser console and check for JavaScript errors
2. Open Network tab and filter for XHR/Fetch requests
3. Look for two AJAX calls:
   - `msh_save_filename_suggestion` (should return success)
   - `msh_apply_filename_suggestions` (should return `status: 'success'`)
4. Check the response from `msh_apply_filename_suggestions`:
   ```json
   {
     "status": "success",
     "old_url": "...",
     "new_url": "...",
     "message": "..."
   }
   ```

---

## Test 3: Usage Index - Index Stats

### What to Test
The usage index should now show populated data instead of "0 entries, 0 attachments".

### Steps
1. Scroll to the **Usage Index** section on the admin page
2. Check the index statistics display

### Expected Results
✅ **PASS:** Shows "158 entries across 10 attachments" (or similar non-zero values)
✅ **PASS:** Health status shows "Healthy" (green)
✅ **PASS:** Last updated timestamp is recent (within last hour)

❌ **FAIL:** Still shows "0 entries, 0 attachments"
❌ **FAIL:** Shows error or "Unknown" status

### Debug Steps if Failed
1. Check database directly via WP-CLI:
   ```bash
   cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "SELECT COUNT(*) as total_entries, COUNT(DISTINCT attachment_id) as total_attachments FROM wp_msh_image_usage_index"
   ```
   Should return non-zero values.

2. If database is empty, trigger rebuild manually:
   ```bash
   cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval "
   \$background = MSH_Usage_Index_Background::get_instance();
   \$background->queue_rebuild('full', true, 'manual');
   echo 'Rebuild queued\n';
   "
   ```

3. Then run the processor:
   ```bash
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp cron event run msh_process_usage_index_queue
   ```
   Repeat until no more cron events are scheduled (check with `wp cron event list | grep msh_process_usage_index_queue`)

---

## Test 4: Reference Distribution Display

### What to Test
The Reference Distribution bar chart should show correct percentages for Posts, Meta, and Options instead of all zeros.

### Steps
1. Locate the **Reference Distribution** section under Usage Index
2. Check the bar chart and numerical counts

### Expected Results
✅ **PASS:** Posts count: **6** (3.8%)
✅ **PASS:** Meta count: **143** (90.5%) - combines `meta` (47) + `serialized_meta` (96)
✅ **PASS:** Options count: **9** (5.7%) - from `serialized_option`
✅ **PASS:** Bar chart visually reflects percentages (Meta bar much larger than others)

❌ **FAIL:** All counts show 0
❌ **FAIL:** Percentages don't add up to 100%
❌ **FAIL:** Bar chart doesn't match numerical values

### Debug Steps if Failed
1. Open browser console
2. Check `mshImageOptimizer.indexStats` object:
   ```javascript
   console.log(mshImageOptimizer.indexStats);
   ```
3. Look for `by_context` array and check `context_type` values:
   ```javascript
   console.log(mshImageOptimizer.indexStats.by_context);
   ```
   Should contain objects like:
   ```json
   [
     {"context_type": "content", "count": 6},
     {"context_type": "meta", "count": 47},
     {"context_type": "serialized_meta", "count": 96},
     {"context_type": "serialized_option", "count": 9}
   ]
   ```

4. Verify the mapping logic is correct in the JavaScript:
   - Open DevTools → Sources
   - Find `image-optimizer-modern.js` line ~2930-2941
   - Verify it maps `'postmeta'`, `'meta'`, and `'serialized_meta'` to Meta count

---

## Test 5: Backward Compatibility - Legacy Schema

### What to Test
The Reference Distribution should also work with legacy `'postmeta'` context_type values (for backward compatibility).

### Steps
This test requires simulating legacy data. You can skip this unless you have a site with old data, or manually test by:

1. Insert a test entry with legacy `'postmeta'` type:
   ```bash
   cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
   INSERT INTO wp_msh_image_usage_index (attachment_id, context_type, context_id, usage_count, first_seen, last_seen)
   VALUES (1692, 'postmeta', 999, 1, NOW(), NOW())
   "
   ```

2. Hard refresh browser and check Reference Distribution
3. Clean up test data:
   ```bash
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
   DELETE FROM wp_msh_image_usage_index WHERE context_id = 999
   "
   ```

### Expected Results
✅ **PASS:** Meta count increases by 1 when `'postmeta'` entry is added
✅ **PASS:** No JavaScript errors in console

---

## Test 6: End-to-End Workflow

### What to Test
Complete workflow from analysis to optimization to editing.

### Steps
1. Click **Analyze** button
2. Wait for analysis to complete
3. Select images that need optimization
4. Click **Optimize Selected**
5. Wait for optimization to complete
6. Verify filenames were renamed correctly
7. Click edit icon on a renamed file
8. Change the filename
9. Save and verify the change applied

### Expected Results
✅ **PASS:** Analysis runs without errors
✅ **PASS:** Optimization renames files correctly
✅ **PASS:** Edit button appears with proper icon
✅ **PASS:** Edit functionality works end-to-end
✅ **PASS:** Usage index updates after optimization

---

## Common Issues & Solutions

### Issue: Edit Icon Shows as Broken Image
**Solution:**
1. Verify icon file exists:
   ```bash
   ls -lh "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/icons/edit.png"
   ```
2. Check file permissions (should be readable)
3. Verify `MSH_IMAGE_OPTIMIZER_PLUGIN_URL` constant is defined in main plugin file

### Issue: Reference Distribution Still Shows Zeros
**Solution:**
1. Clear browser cache completely (not just hard refresh)
2. Check if JavaScript file was actually updated:
   ```bash
   grep -n "postmeta.*meta.*serialized_meta" /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/js/image-optimizer-modern.js
   ```
   Should return line ~2937 with the updated mapping
3. Verify page is loading the latest JS (check Network tab for cache headers)

### Issue: Edit Functionality Doesn't Save
**Solution:**
1. Check browser console for errors
2. Verify two-step AJAX process is working:
   - First call saves suggestion
   - Second call applies rename via batch endpoint
3. Check response parsing uses `result.status === 'success'` (not `result.success`)

### Issue: Usage Index Empty After Rebuild
**Solution:**
1. Check if images are actually referenced in content:
   ```bash
   cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
   SELECT ID, post_title FROM wp_posts
   WHERE post_content LIKE '%wp-content/uploads/%'
   AND post_type IN ('post', 'page')
   "
   ```
2. Images only get indexed if they're used somewhere (content, meta, options)
3. Orphaned images (not used anywhere) won't appear in the index

---

## Database Verification Commands

### Check Current Usage Index State
```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

# Total entries and attachments
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT
    COUNT(*) as total_entries,
    COUNT(DISTINCT attachment_id) as unique_attachments
FROM wp_msh_image_usage_index
"

# Breakdown by context type
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT
    context_type,
    COUNT(*) as count,
    COUNT(DISTINCT attachment_id) as unique_attachments
FROM wp_msh_image_usage_index
GROUP BY context_type
ORDER BY count DESC
"
```

### Check Background Queue Status
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_usage_index_queue_state --format=json | python3 -m json.tool
```

Should show:
```json
{
  "status": "complete",
  "mode": "full",
  "total": 35,
  "processed": 35,
  "completed_at": [recent timestamp]
}
```

---

## Success Criteria

All tests pass when:

- ✅ Edit icon displays as proper pen/pencil image (not "||")
- ✅ Edit functionality allows inline filename editing and saving
- ✅ Usage index shows 158 entries across 10 attachments
- ✅ Reference Distribution shows Posts: 6, Meta: 143, Options: 9
- ✅ Bar chart percentages match numerical counts
- ✅ No JavaScript errors in browser console
- ✅ No 404 errors loading assets (icons, JS, CSS)

---

## Files Changed

1. **[image-optimizer-modern.js:1879](image-optimizer-modern.js:1879)** - Changed dashicon to PNG image
2. **[image-optimizer-modern.js:2937](image-optimizer-modern.js:2937)** - Added backward-compatible context_type mapping
3. **[image-optimizer-admin.php:138](image-optimizer-admin.php:138)** - Added pluginUrl to localized script data
4. **[image-optimizer-admin.css:3349-3354](image-optimizer-admin.css:3349-3354)** - Updated CSS for PNG icon styling

---

## Report Template

After testing, please report results using this format:

```
TEST RESULTS - [Date/Time]

Test 1 - Edit Button Icon: [PASS/FAIL]
Notes:

Test 2 - Edit Functionality: [PASS/FAIL]
Notes:

Test 3 - Usage Index Stats: [PASS/FAIL]
Notes:

Test 4 - Reference Distribution: [PASS/FAIL]
Notes:

Test 5 - Backward Compatibility: [PASS/FAIL or SKIPPED]
Notes:

Test 6 - End-to-End Workflow: [PASS/FAIL]
Notes:

Browser Console Errors: [YES/NO]
If yes, paste errors:

Network Tab Issues: [YES/NO]
If yes, describe:

Screenshots: [Attached/Not Attached]

Overall Status: [ALL PASS / ISSUES FOUND]
```
