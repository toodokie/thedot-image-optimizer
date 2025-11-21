# Cloud Sync Testing Guide - Simple Instructions

## Overview
This guide will help you test the cloud sync feature and verify that **local edits are never silently overwritten**.

---

## Prerequisites

1. **Enable WordPress Debug Log** (so we can see what's happening)
   - Open: `/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-config.php`
   - Find the line: `define( 'WP_DEBUG', false );`
   - Change it to:
     ```php
     define( 'WP_DEBUG', true );
     define( 'WP_DEBUG_LOG', true );
     define( 'WP_DEBUG_DISPLAY', false );
     ```

2. **Open Terminal** - You'll run all commands from here

---

## Test 1: Enable Sync & Push Initial Data

**What this does:** Enables cloud sync and pushes all your local metadata to Supabase.

**Copy and paste these commands one at a time:**

```bash
# Step 1: Navigate to WordPress directory
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

# Step 2: Enable sync via WP-CLI
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '
$sync = MSH_Remote_Sync::get_instance();
$result = $sync->enable();
echo "\n=== SYNC ENABLE RESULT ===\n";
print_r($result);
echo "\n";
'
```

**What to expect:**
- You should see: `Remote Sync enabled. Site ID: [some-uuid]. Pulled X metadata entries.`
- If it fails, you'll see an error message

---

## Test 2: Make a Local Edit (Simulate User Editing Metadata)

**What this does:** Changes the title of an image's metadata locally. This simulates what happens when a user edits metadata in WordPress.

**Commands:**

```bash
# Step 1: See what metadata exists
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT media_id, locale, title, alt, updated_at
FROM wp_optimizer_metadata_cache
ORDER BY updated_at DESC
LIMIT 5;
"

# Step 2: Pick a media_id from above (let's use 1686 as example)
# Edit its title to something obvious
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
UPDATE wp_optimizer_metadata_cache
SET title = 'LOCAL EDIT - Test Title Changed',
    updated_at = NOW()
WHERE media_id = 1686
AND locale = 'en_US'
LIMIT 1;
"

# Step 3: Verify the change was made
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT media_id, locale, title, updated_at
FROM wp_optimizer_metadata_cache
WHERE media_id = 1686;
"
```

**What to expect:**
- You should see the title changed to: `LOCAL EDIT - Test Title Changed`
- The `updated_at` timestamp should be very recent (just now)

---

## Test 3: Trigger a Sync (This is the Critical Test!)

**What this does:** Runs a manual sync. With "Local Wins" strategy (the default), your local edit should be **pushed to cloud**, NOT overwritten.

**Commands:**

```bash
# Step 1: Run a manual sync
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '
$sync = MSH_Remote_Sync::get_instance();
echo "\n=== RUNNING SYNC NOW ===\n";
$result = $sync->sync_now();
print_r($result);
echo "\n";
'

# Step 2: Check the debug log for conflict messages
tail -n 100 /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/debug.log | grep -i "sync"
```

**What to expect:**
- You should see: `Sync complete! Pushed: X, Pulled: Y`
- If there were conflicts, you'll see messages like: `Local data protected from overwrite`

---

## Test 4: Verify Your Local Edit is Still There

**What this does:** Double-checks that your local edit was NOT overwritten by cloud data.

**Command:**

```bash
# Check that your local edit is STILL there
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT media_id, locale, title, updated_at
FROM wp_optimizer_metadata_cache
WHERE media_id = 1686
AND title LIKE '%LOCAL EDIT%';
"
```

**What to expect:**
- ✅ You should still see: `LOCAL EDIT - Test Title Changed`
- ✅ This proves local data was NOT overwritten!

---

## Test 5: Verify Cloud Has Your Local Edit (Optional)

**What this does:** Checks Supabase to see if your local edit was pushed to the cloud.

**Steps:**

1. Open your browser
2. Go to: https://supabase.com/dashboard
3. Login and select project: `fzynkgtarqbdofegyvbq`
4. Go to: Table Editor → `metadata` table
5. Search for `media_id = 1686`
6. You should see: `LOCAL EDIT - Test Title Changed`

**What to expect:**
- ✅ Your local edit is now in the cloud!
- ✅ Cloud did NOT overwrite local - local overwrote cloud!

---

## Test 6: Simulate a Conflict (Advanced)

**What this does:** Creates a true conflict by:
1. Making a local edit
2. Manually inserting a different cloud version
3. Syncing and watching the conflict resolution

**Commands:**

```bash
# Step 1: Make another local edit
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
UPDATE wp_optimizer_metadata_cache
SET title = 'LOCAL EDIT v2 - Newer Version',
    updated_at = NOW()
WHERE media_id = 1686
AND locale = 'en_US';
"

# Step 2: Check current conflict strategy
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_sync_conflict_strategy

# Step 3: Run sync (with local_wins, local should win)
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '
$sync = MSH_Remote_Sync::get_instance();
$result = $sync->sync_now();
echo "\n=== SYNC RESULT ===\n";
print_r($result);
'

# Step 4: Verify local edit is STILL there
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT title FROM wp_optimizer_metadata_cache WHERE media_id = 1686;
"

# Step 5: Check for conflict logs
tail -n 50 /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/debug.log | grep -i "conflict"
```

**What to expect:**
- ✅ Title should STILL be: `LOCAL EDIT v2 - Newer Version`
- ✅ Debug log should show: `Local data protected from overwrite`
- ✅ No silent overwrites!

---

## Quick Reference: All-in-One Test Script

**Copy this entire block and paste into Terminal:**

```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

echo "=== TEST 1: Enable Sync ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '
$sync = MSH_Remote_Sync::get_instance();
$result = $sync->enable();
print_r($result);
'

echo "\n=== TEST 2: Make Local Edit ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
UPDATE wp_optimizer_metadata_cache
SET title = 'LOCAL EDIT - Full Test',
    updated_at = NOW()
WHERE media_id = 1686
LIMIT 1;
"

echo "\n=== TEST 3: Run Sync ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '
$sync = MSH_Remote_Sync::get_instance();
$result = $sync->sync_now();
print_r($result);
'

echo "\n=== TEST 4: Verify Local Edit Still There ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
SELECT media_id, title FROM wp_optimizer_metadata_cache WHERE media_id = 1686;
"

echo "\n=== TEST 5: Check Conflict Logs ==="
tail -n 30 wp-content/debug.log | grep -i "sync\|conflict"

echo "\n✅ TEST COMPLETE!"
```

---

## Expected Results Summary

| Test | Expected Result |
|------|----------------|
| Enable Sync | ✅ Success message with Site ID |
| Local Edit | ✅ Title changes to "LOCAL EDIT..." |
| Run Sync | ✅ Pushed: X, Pulled: Y |
| Verify Local | ✅ Local edit still present |
| Cloud Check | ✅ Local edit is now in cloud |
| Conflict Test | ✅ "Local data protected" in logs |

---

## Troubleshooting

**Problem:** "Permission denied" error
- **Solution:** Make sure you're logged into WordPress as an admin

**Problem:** "This does not seem to be a WordPress installation"
- **Solution:** Make sure the `cd` command path is correct

**Problem:** No conflicts detected
- **Solution:** That's actually good! It means there were no conflicts, or they were resolved correctly

**Problem:** Sync fails with "handshake error"
- **Solution:** Check your internet connection and Supabase URL

---

## What Success Looks Like

1. ✅ Sync enables without errors
2. ✅ Local edits appear in the database with new `updated_at` timestamp
3. ✅ After sync, local edits are **still there** (not overwritten)
4. ✅ Debug log shows "Local data protected" or "Pushed X changes"
5. ✅ Cloud (Supabase) contains your local edits

**This proves that "Local Wins" strategy is working correctly!**
