# Known Issues & Future Enhancements

**Last Updated:** October 17, 2025

This document tracks known issues, limitations, and planned enhancements for the MSH Image Optimizer plugin.

---

## 🐛 Active Issues

### Issue #1: Usage Index Not Auto-Refreshed After Rename/Optimize

**Status:** 📝 Documented - Will fix in Phase 2
**Priority:** Medium
**Reported:** October 17, 2025

#### Problem Description:

After running optimize operations (which can include renaming files), the usage index is not automatically refreshed. This causes:

1. **Stale data in index** - Old URLs remain in index even though files were renamed
2. **"Action needed" warning persists** - Shows "build usage index before renaming" even after recent rebuild
3. **Manual intervention required** - User must manually click "Rebuild Index" again

#### User Experience Impact:

- **Confusion:** "I just rebuilt the index, why is it asking me again?"
- **Extra steps:** User has to rebuild index after every rename batch
- **Performance:** Full site scan required each time (slow on large sites)

#### Current Behavior:

```
User Flow:
1. Rebuild usage index ✅
2. Run optimize batch (includes renames) ✅
3. Files renamed, URLs updated in database ✅
4. Usage index still has OLD URLs ❌
5. Warning appears: "Action needed - build usage index" ❌
6. User must manually rebuild again ❌
```

#### Expected Behavior:

```
User Flow:
1. Rebuild usage index ✅
2. Run optimize batch (includes renames) ✅
3. Files renamed, URLs updated in database ✅
4. Index automatically refreshed in background ✅
5. No warning shown (or "Refreshing..." indicator) ✅
6. User continues working ✅
```

#### Technical Analysis:

**Files Involved:**
- `includes/class-msh-image-optimizer.php` - Optimize batch handler
- `includes/class-msh-safe-rename-system.php` - Rename operations
- `includes/class-msh-image-usage-index.php` - Index management
- `includes/class-msh-usage-index-background.php` - Background jobs

**Current Code Gaps:**
```php
// In ajax_optimize_batch() - Line ~7300
// After batch completes:
update_option('msh_last_optimization_run', current_time('mysql'));
wp_send_json_success(['results' => $results]);

// ❌ Missing: Index refresh trigger
// ❌ Missing: Queue background job
```

#### Proposed Solutions:

**Option A: Full Auto-Rebuild (Heavy)**
- Trigger full index rebuild after each batch
- ❌ Slow (scans entire site)
- ❌ Blocks user workflow
- Not recommended

**Option B: Background Queue Refresh (Better)**
- Queue background job to rebuild index
- ✅ Non-blocking
- ⚠️ Still scans entire site (wasteful)
- ⚠️ Slight delay before accuracy

**Option C: Incremental Update (Best) ⭐ RECOMMENDED**
- Track which attachments were renamed
- Only refresh those specific entries in index
- ✅ Fast (only changed items)
- ✅ Immediate update
- ✅ Efficient (minimal queries)
- Requires new method: `refresh_single_attachment($id)`

**Option D: Smarter Warning Message (Quick Fix)**
- Change message from "Action needed" to "Refresh recommended"
- ✅ Less alarming
- ✅ Quick to implement
- ❌ Doesn't fix root cause

**Option E: Hybrid Approach**
- Phase 1: Background queue + softer messaging
- Phase 2: Incremental updates for efficiency

#### Recommended Implementation (Phase 2):

**Step 1: Track Changed Attachments**
```php
// In optimize_single_image()
if ($rename_feedback['status'] === 'success') {
    $changed_attachment_ids[] = $attachment_id;
}
```

**Step 2: Incremental Index Refresh**
```php
// After batch completes
if (!empty($changed_attachment_ids)) {
    $usage_index = MSH_Image_Usage_Index::get_instance();
    foreach ($changed_attachment_ids as $id) {
        $usage_index->refresh_single_attachment($id);
    }
}
```

**Step 3: Add New Method to Usage Index**
```php
// In class-msh-image-usage-index.php
public function refresh_single_attachment($attachment_id) {
    // 1. Get current file path
    // 2. Find old entries for this attachment
    // 3. Re-scan content for new URL references
    // 4. Update index entries
    // 5. Invalidate stats cache
}
```

**Step 4: Update Warning Logic**
```php
// More intelligent warning
if ($index_stale && !$recently_optimized) {
    return 'Action needed – build usage index before renaming';
} elseif ($index_stale && $recently_optimized) {
    return 'Index refresh recommended after recent optimizations';
}
```

#### Effort Estimate:

- **Investigation:** 1 hour (already done)
- **Implementation:** 3-4 hours
  - New `refresh_single_attachment()` method: 2 hours
  - Integration with optimize batch: 1 hour
  - Testing: 1 hour
- **Total:** 4-5 hours

#### Testing Plan:

1. Rebuild usage index (verify counts accurate)
2. Rename 10 files via optimize batch
3. Verify index auto-refreshes (no manual rebuild needed)
4. Check usage counts still accurate
5. Verify performance (should be fast - only 10 items refreshed)
6. Test with large batch (100+ files)

#### Related Enhancements:

- Add "Refreshing index..." progress indicator in UI
- Show "Last refreshed: X minutes ago" timestamp
- Add manual "Refresh Now" button (quick incremental refresh)
- Cache index results per session to reduce queries

#### Workaround (Current):

**For Users:**
1. After running optimize batch
2. Manually click "Rebuild Usage Index" again
3. Wait for full site scan to complete
4. Proceed with next batch

**For Developers:**
```bash
# Via WP-CLI - faster than UI
wp msh rebuild-index
```

---

## 🎯 Future Enhancements

### Enhancement #1: AI Metadata with Profile Location Context

**Status:** 📝 Planned for Multilingual AI Phase
**Priority:** Medium

Currently, AI-generated metadata doesn't use the active profile's city/business location in the generated text. For example:

- **Profile:** "Spanish Landing Pages" in Barcelona
- **Expected:** Metadata mentions "Barcelona" or "España"
- **Actual:** Generic descriptions without location reference

**Planned:** Thread profile location into AI prompts during multilingual implementation.

**Ref:** Context Profiles QA findings, October 17, 2025

---

## 📋 Resolved Issues

### ✅ Issue: Syntax Error in Admin File (Unescaped Apostrophe)

**Resolved:** October 17, 2025
**Commit:** Part of i18n implementation

**Problem:** Settings page returned 500 error due to unescaped apostrophe in translation string.
**Fix:** Changed `"you're"` to `"you\'re"` in PHP string.

**File:** `admin/image-optimizer-admin.php:276`

---

## 📝 Issue Reporting

To report a new issue:
1. Check this document first (issue may already be known)
2. Open GitHub issue: https://github.com/toodokie/thedot-image-optimizer/issues
3. Include:
   - Clear description of the problem
   - Steps to reproduce
   - Expected vs. actual behavior
   - WordPress version, PHP version
   - Error logs if available

---

**Document maintained by:** Development Team
**Next Review:** When implementing Phase 2 enhancements
