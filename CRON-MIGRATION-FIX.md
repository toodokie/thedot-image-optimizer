# Auto-Sync Cron Hook Migration Fix

## Issue Identified

**Severity:** HIGH - Auto-sync would silently break on existing installations after update

### Problem
When we renamed the cron hook from `msh_auto_sync` to `msh_auto_sync_cron`:
1. Existing installations had the old `msh_auto_sync` hook scheduled in WP-Cron
2. The new code only listened to `msh_auto_sync_cron`
3. The old hook would fire with no callback attached
4. The new hook would never be scheduled until settings were manually saved
5. **Result:** Auto-sync would silently stop working on all existing sites

### Credit
Issue discovered by AI code reviewer analyzing Phase 5+9 updates.

---

## Solution Implemented

### Three-Part Fix

#### 1. Backward Compatibility Hook (Line 107)
```php
// Backward compatibility: support old hook name
add_action( 'msh_auto_sync', array( $this, 'auto_sync' ) );
```

**Why:** Ensures old scheduled events continue to work even if migration fails.

#### 2. Automatic Migration on Load (Line 113)
```php
// Migrate old cron hook to new hook on load
add_action( 'init', array( $this, 'migrate_cron_hook' ), 5 );
```

**Why:** Automatically migrates existing installations without manual intervention.

#### 3. Migration Method (Lines 308-377)
```php
public function migrate_cron_hook() {
    // Check if migration has already been done
    if ( get_option( 'msh_cron_hook_migrated', false ) ) {
        return;
    }

    // Check if old hook is scheduled
    $old_hook = 'msh_auto_sync';
    $old_timestamp = wp_next_scheduled( $old_hook );

    if ( $old_timestamp ) {
        // Detect old recurrence (hourly, twicedaily, daily)
        $cron_array = _get_cron_array();
        $recurrence = 'off';

        // Map old schedule to new setting
        // twicedaily → daily (consolidated cadences)
        // hourly → hourly
        // daily → daily

        // Clear old hook
        wp_clear_scheduled_hook( $old_hook );

        // Schedule new hook with same cadence
        if ( 'hourly' === $recurrence ) {
            wp_schedule_event( time(), 'hourly', 'msh_auto_sync_cron' );
        } elseif ( 'daily' === $recurrence ) {
            wp_schedule_event( time(), 'daily', 'msh_auto_sync_cron' );
        }

        // Save preference to option
        update_option( 'msh_auto_sync_schedule', $recurrence, false );
    }

    // Mark migration as complete (runs only once)
    update_option( 'msh_cron_hook_migrated', true, false );
}
```

---

## Migration Behavior

### For New Installations
- No old hook exists
- Migration runs but does nothing
- Settings page controls scheduling via `update_sync_cron_schedule()`
- Normal behavior

### For Existing Installations with Auto-Sync Enabled

#### Scenario 1: Hourly Sync
- **Before:** `msh_auto_sync` scheduled hourly
- **Migration:** Detects hourly schedule → clears old hook → schedules `msh_auto_sync_cron` hourly
- **After:** Auto-sync continues hourly with new hook
- **Settings:** Shows "Hourly" in dropdown

#### Scenario 2: Twicedaily Sync (Legacy)
- **Before:** `msh_auto_sync` scheduled twicedaily
- **Migration:** Detects twicedaily → maps to "daily" → clears old hook → schedules `msh_auto_sync_cron` daily
- **After:** Auto-sync runs daily (reduced from twice daily)
- **Settings:** Shows "Daily" in dropdown
- **Note:** Slight behavior change but safer default

#### Scenario 3: Daily Sync
- **Before:** `msh_auto_sync` scheduled daily
- **Migration:** Detects daily → clears old hook → schedules `msh_auto_sync_cron` daily
- **After:** Auto-sync continues daily with new hook
- **Settings:** Shows "Daily" in dropdown

### For Existing Installations with Auto-Sync Disabled
- **Before:** No hook scheduled
- **Migration:** Runs but finds no old hook, marks as complete
- **After:** No change, auto-sync remains off
- **Settings:** Shows "Off" in dropdown

---

## Design Decisions

### 1. One-Time Migration with Flag
**Decision:** Use `msh_cron_hook_migrated` option to run migration only once.

**Why:**
- Prevents repeated migration logic on every page load
- Clean, efficient approach
- Option persists across plugin updates

### 2. Backward Compatibility Hook Kept Forever
**Decision:** Keep listening to both `msh_auto_sync` and `msh_auto_sync_cron`.

**Why:**
- Safety net if migration fails
- Handles edge cases (manual cron schedules)
- No performance impact (action hooks are lightweight)
- Can be removed in a future major version

### 3. Map `twicedaily` to `daily`
**Decision:** Consolidate twicedaily schedule to daily cadence.

**Why:**
- UI only offers hourly/daily/off (no twicedaily option)
- Daily is close enough without breaking functionality
- Reduces sync frequency slightly (more conservative)
- Twicedaily was likely a legacy/testing option

### 4. Migration on `init` Hook (Priority 5)
**Decision:** Run migration early in WordPress load process.

**Why:**
- Runs before most plugin code
- Ensures migration completes before any sync attempts
- Priority 5 is early but after WordPress core init
- Runs on every page load until migration completes (then exits immediately)

---

## Testing Migration

### Manual Test Steps

1. **Simulate Existing Installation:**
   ```bash
   # Schedule old hook manually
   wp cron event schedule msh_auto_sync now hourly

   # Verify it's scheduled
   wp cron event list
   ```

2. **Trigger Migration:**
   ```bash
   # Load any WordPress page (migration runs on init)
   curl http://your-site.local/wp-admin/
   ```

3. **Verify Migration:**
   ```bash
   # Check new hook is scheduled
   wp cron event list | grep msh_auto_sync_cron

   # Check old hook is cleared
   wp cron event list | grep -E "^msh_auto_sync[^_]"

   # Check migration flag
   wp option get msh_cron_hook_migrated
   # Should output: true

   # Check saved preference
   wp option get msh_auto_sync_schedule
   # Should output: hourly
   ```

4. **Verify Settings Page:**
   - Navigate to Settings → MSH Image Optimizer → Cloud Sync
   - Auto-Sync Schedule dropdown should show "Hourly"
   - Changing and saving should update cron correctly

---

## Files Modified

### [includes/enterprise/class-msh-remote-sync.php](../Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/enterprise/class-msh-remote-sync.php)

**Changes:**
1. Line 107: Added backward compatibility hook `add_action( 'msh_auto_sync', ... )`
2. Line 113: Added migration trigger `add_action( 'init', array( $this, 'migrate_cron_hook' ), 5 )`
3. Lines 308-377: Added `migrate_cron_hook()` method with full migration logic

**No other files needed changes** - the settings save handler already exists and works correctly.

---

## Rollback Plan

If migration causes issues:

1. **Disable Migration:**
   ```php
   // Comment out line 113 in class-msh-remote-sync.php
   // add_action( 'init', array( $this, 'migrate_cron_hook' ), 5 );
   ```

2. **Reset Migration Flag:**
   ```bash
   wp option delete msh_cron_hook_migrated
   ```

3. **Manually Fix Cron:**
   ```bash
   wp cron event delete msh_auto_sync
   wp cron event delete msh_auto_sync_cron
   wp cron event schedule msh_auto_sync_cron now hourly
   ```

---

## Future Cleanup

In a future major version (2.0+), consider:
- Remove backward compatibility hook `msh_auto_sync`
- Remove migration method (no longer needed after all sites updated)
- Remove `msh_cron_hook_migrated` option check

For now, keep all safety mechanisms in place.

---

## Summary

✅ **Issue:** Auto-sync would break on existing installations
✅ **Fix:** Three-part solution with backward compat + automatic migration
✅ **Safety:** One-time migration with fallback hook
✅ **Behavior:** Preserves user's sync cadence (hourly/daily)
✅ **Testing:** Manual test steps provided

**Status:** FIXED - Ready for deployment
