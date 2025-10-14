# Database Investigation Findings
## Investigation Date: 2025-10-13

## Executive Summary

Investigation into rename rollback failures revealed that **row 25 (wp_postmeta) was being shared by multiple attachments** during rename operations, causing verification failures. The verification system was correctly detecting that old filenames still existed in metadata after updates, triggering rollbacks.

### Root Cause: NOT Cache Invalidation

Initial hypothesis about object cache issues was **INCORRECT**. Investigation confirmed:
- ✅ Object cache type: **Default** (no Redis/Memcached active)
- ✅ Verification reads directly from database via `$wpdb->get_var()` (bypasses cache)
- ✅ `update_wordpress_metadata()` uses `wp_update_attachment_metadata()` (handles cache correctly)

---

## Investigation Results

### Query 1: Row 25 Current State

```sql
SELECT * FROM wp_postmeta WHERE meta_id = 25;
```

**Result:**
- `post_id`: 616
- `meta_key`: `_wp_attachment_metadata`
- `meta_value`: Serialized array containing `"file":"2008/06/classic-gallery-616.jpg"`

**Current state:** Row 25 belongs to attachment 616 and contains `classic-gallery-616.jpg`

---

### Query 2: Object Cache Status

```bash
wp cache type
```

**Result:** `Default`

**Conclusion:** No object caching active (no Redis/Memcached). Cache invalidation hypothesis **RULED OUT**.

---

### Query 3: Recent Rename Failures

```sql
SELECT * FROM wp_msh_rename_log WHERE status = 'failed' ORDER BY renamed_date DESC LIMIT 5;
```

**Results show pattern of verification failures:**

| ID | Attachment | Old Filename | New Filename | Status | Details |
|----|------------|--------------|--------------|--------|---------|
| 157 | 616 | classic-gallery-616.jpg | classic-gallery-austin-616.jpg | failed | Replacement verification failed, backup restored |
| 153 | 611 | emberline-creative-agency-facility-austin-texas-611.jpg | emberline-creative-agency-facility-austin-texas.jpg | failed | Replacement verification failed, backup restored |
| 151 | 611 | rehabilitation-physiotherapy.jpg | emberline-creative-agency-facility-austin-texas.jpg | failed | Replacement verification failed, backup restored |
| 35 | 1692 | spectacles.gif | rehabilitation-physiotherapy-1692.gif | failed | Replacement verification failed, backup restored |
| 34 | 1691 | dsc20050315_145007_132.jpg | rehabilitation-physiotherapy-1691.jpg | failed | Replacement verification failed, backup restored |

**Pattern identified:** All failures show "Replacement verification failed, backup restored"

---

### Query 4: Row 25 Backup History

```sql
SELECT * FROM wp_msh_rename_backups WHERE row_id = 25 ORDER BY backup_date DESC LIMIT 5;
```

**Critical Finding - Row 25 Was Shared Between Attachments:**

| Operation ID | Attachment | Original Value (filename in metadata) | Backup Date | Status |
|--------------|------------|---------------------------------------|-------------|---------|
| 48af5cf2... | 616 | `classic-gallery-austin-616.jpg` | 2025-10-13 18:27:26 | restored |
| 126abbc6... | 611 | `emberline-creative-agency-facility-austin-texas-611-616.jpg` | 2025-10-13 16:45:18 | restored |
| 902159008... | 611 | `rehabilitation-physiotherapy-616.jpg` | 2025-10-13 16:39:40 | active |
| d6cef4bb... | 611 | `rehabilitation-physiotherapy-616.jpg` | 2025-10-13 16:35:22 | restored |
| 393b2533... | 617 | `rehabilitation-physiotherapy.jpg` | 2025-10-09 14:05:52 | active |

**The Smoking Gun:**

Row 25 contains `_wp_attachment_metadata` for attachment **616**, but backup records show it was modified during rename operations for:
- Attachment **616** (correct owner)
- Attachment **611** (different attachment!)
- Attachment **617** (another different attachment!)

**What happened:**

1. Row 25 stores metadata for attachment 616
2. When attachment 611 was renamed, it ALSO tried to update row 25
3. Verification detected the mismatch: "I just renamed 611, but row 25 still shows 616's filename"
4. Verification correctly triggered rollback
5. Physical files for 611 were already renamed → mismatch created

---

### Query 5: Attachment 611 Current State

```bash
wp post meta get 611 _wp_attached_file
```

**Result:**
```
2008/06/workspace-facility-austin-equipment-austin-texas-611-equipment-austin-texas-611-equipment-austin-texas-611.jpg
```

**This is a different mismatch** - the database has a long repeated filename, but the physical file is simpler.

---

## Root Cause Analysis

### The Real Problem: Metadata Row Confusion

**Why row 25 was shared:**

The backup system tracks changes by `row_id` (the `meta_id` in `wp_postmeta`), not by `(post_id, meta_key)` combination.

**Scenario that caused the issue:**

1. Attachment 616's metadata is in row 25
2. Rename operation on attachment 611 starts
3. Backup system backs up row 25 (thinking it's for 611)
4. Update tries to modify row 25
5. But row 25 belongs to 616, not 611!
6. Verification reads row 25, sees 616's filename
7. Verification expects 611's new filename
8. Mismatch detected → rollback triggered

### Why This Happened

The backup/verification system is using **meta_id (row ID)** as the tracking key, but `meta_id` is NOT stable across attachment operations. When WordPress regenerates metadata or updates postmeta, the `meta_id` can change.

**The targeted replacement engine is finding the OLD meta_id** from a previous scan, but by the time the rename runs, that row might belong to a different attachment.

---

## Why Verification Was Correct

The verification system DID its job correctly:
1. ✅ Read directly from database (no cache issues)
2. ✅ Detected actual mismatch (row 25 had wrong filename)
3. ✅ Triggered rollback to prevent corruption
4. ✅ Restored backups correctly

**The problem isn't verification - it's the tracking mechanism using unstable meta_id.**

---

## Code Analysis: Cache Invalidation (Confirmed Working)

### update_wordpress_metadata() - Line 498-528

```php
private function update_wordpress_metadata($attachment_id, $new_path, $old_metadata, $new_relative) {
    update_attached_file($attachment_id, $new_path);  // ← Handles cache

    if (is_array($old_metadata)) {
        $metadata = $old_metadata;
        $metadata['file'] = $new_relative;
        // ... update sizes ...
        wp_update_attachment_metadata($attachment_id, $metadata);  // ← Clears cache
    }

    // Regenerate metadata
    wp_generate_attachment_metadata($attachment_id, $new_path);  // ← Also clears cache
}
```

**Conclusion:** Cache invalidation is working correctly. Uses WordPress core functions that handle cache automatically.

### verify_targeted_updates() - Line 335-375

```php
private function verify_targeted_updates($operation_id, $attachment_id, $targeted_updates, $replacement_map) {
    foreach ($targeted_updates as $update) {
        // Check if this specific row still contains the old URL
        $current_value = $wpdb->get_var($wpdb->prepare("
            SELECT {$column}
            FROM {$table}
            WHERE {$id_column} = %d
        ", $row_id));  // ← Direct DB query, bypasses cache

        // Check if old value still present
        if (strpos($current_value, $old_value) !== false) {
            $still_contains_old = true;  // ← Triggers rollback
        }
    }
}
```

**Conclusion:** Verification reads directly from database. No cache involvement.

---

## Implications

### For The Rename System

**The targeted replacement engine has a design flaw:**
- Uses `meta_id` (row_id) for tracking database changes
- But `meta_id` is not stable - WordPress can regenerate postmeta rows
- Results in tracking the wrong row for the wrong attachment

**Fix needed:**
- Track changes by `(post_id, meta_key)` combination, NOT by `meta_id`
- Or verify that `meta_id` still belongs to correct attachment before updating

### For The File Resolver

**The file resolver (already implemented) solves the USER-VISIBLE problem:**
- Attachments with mismatches will now be visible in analyzer
- Optimizer can process them despite database/filesystem differences
- Users get working plugin even with underlying metadata issues

**But it doesn't fix the ROOT CAUSE:**
- The rename system still needs the tracking fix
- Otherwise, future rename operations will continue to fail verification

---

## Recommendations

### Immediate (Already Done)
- ✅ **File resolver implemented** - Makes plugin resilient to mismatches
- ✅ **Committed with comprehensive documentation**

### Short-Term (Should Do Next)
1. **Fix targeted replacement tracking:**
   - Change from tracking by `row_id` (meta_id)
   - To tracking by `(post_id, meta_key, table)` combination
   - Verify row still belongs to correct post before updating

2. **Add row ownership verification:**
   - Before updating row 25, verify it still belongs to attachment 611
   - If ownership changed, re-scan to find correct row
   - Log when rows change during operation

3. **Reset test data:**
   - Export current state for reproduction
   - Delete all attachments
   - Re-import WordPress theme test data
   - Clean baseline for testing fixes

### Long-Term (Consider)
1. **Add transaction support:**
   - Wrap rename operations in WordPress transients or custom locking
   - Prevent concurrent modifications during rename

2. **Improve verification granularity:**
   - Verify by attachment, not by absolute row IDs
   - More resilient to metadata regeneration

3. **Add pre-flight checks:**
   - Before rename, verify all target rows are stable
   - Warn if metadata was recently modified by another process

---

## Test Data State

### Current Mismatches Documented

**Attachment 611:**
- Database: `workspace-facility-austin-equipment-austin-texas-611-equipment-austin-texas-611-equipment-austin-texas-611.jpg`
- Filesystem: (need to verify actual file)

**Attachments 616-617, 754-757, etc:**
- Database: `classic-gallery-NNN.jpg`
- Filesystem: `rehabilitation-physiotherapy-NNN.jpg`

**These mismatches are SYMPTOMS of the meta_id tracking bug**, not independent issues.

---

## Next Steps

1. ✅ **Phase 1 Complete:** DB Investigation finished
2. 🔄 **Phase 2 In Progress:** Test file resolver with current mismatches
3. ⏭️ **Phase 3 Ready:** Export state, reset test data
4. ⏭️ **Phase 4 Pending:** Fix meta_id tracking bug in targeted replacement engine
5. ⏭️ **Phase 5 Pending:** End-to-end validation with clean data

---

## Investigation Conclusions

### What We Thought vs. What It Actually Was

| Hypothesis | Result |
|------------|--------|
| Object cache not invalidated | ❌ FALSE - No object cache active |
| Verification reads stale cache | ❌ FALSE - Reads directly from DB |
| update_wordpress_metadata() doesn't clear cache | ❌ FALSE - Uses wp_update_attachment_metadata() |
| Transient not cleared on rollback | ❌ FALSE - Already implemented correctly |
| Row 25 had write failure | ❌ FALSE - Row was successfully updated |
| **meta_id tracking bug in targeted replacement** | ✅ **TRUE - This is the root cause** |

### Key Insight

The verification system was **CORRECT** to trigger rollbacks. It detected that row 25 was being updated for the wrong attachment. The bug is in the **tracking mechanism**, not the verification or cache invalidation.

The file resolver makes the plugin work despite this bug, but the underlying tracking issue should still be fixed to prevent future verification failures.
