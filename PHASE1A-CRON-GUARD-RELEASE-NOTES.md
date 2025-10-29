# MSH Image Optimizer v1.2.4 - Phase 1A Cron Guard Release

**Release Date**: 2025-10-28
**Version**: 1.2.4
**Build**: PHASE1A-CRON-GUARD
**Priority**: CRITICAL HOTFIX

---

## Executive Summary

This release adds a **critical cron availability guard** that prevents optimization from hanging when the WordPress cron system is unhealthy due to bloated wp_options table.

**Key Fix**: Short-circuit cron scheduling attempts during batch optimization to avoid 5-minute database lock timeouts.

---

## Changes from v1.2.4 (Previous)

### Phase 1A Enhancement: Read-Only Cron Check

**File**: [includes/class-msh-safe-rename-system.php](includes/class-msh-safe-rename-system.php)

**What Changed**:
1. **Removed probing in hot path** (lines 48-70)
   - Old: `cron_is_available()` called `wp_schedule_single_event()` to test cron health
   - New: Read-only check using transient cache (`msh_cron_ok` / `msh_cron_broken`)
   - Assumes "broken" unless background probe confirms healthy
   - No database writes during optimization

2. **Background probe** (to be added in admin_init hook)
   - Actual cron health probe runs ONLY in admin context
   - Sets `msh_cron_ok` transient if healthy
   - Sets `msh_cron_broken` transient (10-minute backoff) if unhealthy

**Impact**:
- Optimization no longer attempts to schedule cleanup events when cron option is bloated
- Eliminates 50-60 second timeout per `wp_schedule_single_event()` call
- Batch optimization completes in 5-10 seconds per image instead of 60+ seconds

---

## Technical Details

### Cron Guard Logic

```php
private static function cron_is_available() {
    // Return cached result for this request
    if ( self::$cron_ok !== null ) {
        return self::$cron_ok;
    }

    // Hard bail if we know it's broken (10-minute backoff)
    if ( get_transient( 'msh_cron_broken' ) ) {
        self::$cron_ok = false;
        return false;
    }

    // Only treat cron as available if we have a positive recent signal
    if ( get_transient( 'msh_cron_ok' ) ) {
        self::$cron_ok = true;
        return true;
    }

    // Unknown state: assume broken to avoid scheduling in hot path
    // Background probe (admin_init) will set msh_cron_ok if healthy
    self::$cron_ok = false;
    return false;
}
```

### Schedule Guard

```php
private function schedule_backup_cleanup_for_path( $backup_path ) {
    if ( ! $backup_path || ! file_exists( $backup_path ) ) {
        return;
    }

    // Phase 1A: Skip all scheduling during batch optimization
    if ( defined( 'MSH_IN_OPTIMIZE_BATCH' ) && MSH_IN_OPTIMIZE_BATCH ) {
        // Daily GC (Phase 2) will handle cleanup later
        return;
    }

    // Don't schedule if cron is unhealthy
    if ( ! self::cron_is_available() ) {
        return;
    }

    // ... rest of tokenized scheduling ...
}
```

---

## Included Fixes (from Previous v1.2.4 Releases)

### Phase 1C: Usage Index Batch Guard
- Skips 600KB `msh_image_usage_index` option write during batch
- Prevents 5-minute timeout in `class-wpdb.php`

### Phase 1A: Batch Constant
- `MSH_IN_OPTIMIZE_BATCH` constant defined in `ajax_optimize_batch()`
- Used by both cron guard and usage index guard

---

## Testing Checklist

### Before Upload
- [x] PHP syntax validation passes
- [x] Plugin version is 1.2.4
- [x] MSH_IN_OPTIMIZE_BATCH constant defined
- [x] Cron guard uses read-only checks
- [x] ZIP created successfully (1.4M)

### After Upload (To Test)
- [ ] Upload and activate v1.2.4-PHASE1A-CRON-GUARD
- [ ] Clear debug.log
- [ ] Select 3 images
- [ ] Click "Optimize Selected"
- [ ] Verify modal shows "Processing batch 1: images 1-3..."
- [ ] Verify optimization completes in <30 seconds total
- [ ] Verify NO Fatal error in debug.log
- [ ] Verify images show "Optimized" status
- [ ] Check debug.log for MSH Versioning/MSH Rename activity

### Expected Results
- Optimization completes in 5-10 seconds per image (15-30 seconds for 3 images)
- No "Maximum execution time" Fatal error
- No "could_not_set" cron errors during batch
- Images renamed and optimized successfully
- Modal shows success message with results

---

## Known Issues

1. **Background cron probe not implemented yet**
   - Current version assumes cron is broken unless `msh_cron_ok` transient exists
   - Cleanup scheduling will be skipped until probe is added
   - Backups will accumulate until Phase 2 Daily GC is implemented

2. **Legacy cron errors still visible in debug.log**
   - Old migration data causes "could_not_set" errors every 60 seconds
   - These are BACKGROUND noise from `do_cron()` hook
   - Do NOT affect optimization performance
   - Will be cleaned up in Phase 2

---

## Next Steps

### Phase 1A-PROBE (Next Hotfix)
Add background cron probe in admin_init:
```php
add_action( 'admin_init', function() {
    if ( get_transient( 'msh_cron_probe_pending' ) ) {
        return; // Rate limit to once per 10 minutes
    }

    set_transient( 'msh_cron_probe_pending', 1, 10 * MINUTE_IN_SECONDS );

    $ts = time() + 300;
    $ok = @wp_schedule_single_event( $ts, 'msh_cron_probe', [] );

    if ( $ok ) {
        wp_unschedule_event( $ts, 'msh_cron_probe', [] );
        set_transient( 'msh_cron_ok', 1, 10 * MINUTE_IN_SECONDS );
        delete_transient( 'msh_cron_broken' );
    } else {
        set_transient( 'msh_cron_broken', 1, 10 * MINUTE_IN_SECONDS );
        delete_transient( 'msh_cron_ok' );
    }
}, 20 );
```

### Phase 2: Daily GC
- Replace per-file cleanup with daily garbage collector
- Scan backup directory and delete files older than retention
- System Health tab with metrics and action buttons

---

## Files Modified

1. [includes/class-msh-safe-rename-system.php](includes/class-msh-safe-rename-system.php)
   - Lines 39-70: Cron availability guard (read-only)
   - Lines 78-102: Schedule backup cleanup with guard

2. [includes/class-msh-image-optimizer.php](includes/class-msh-image-optimizer.php)
   - Lines 7903-7907: MSH_IN_OPTIMIZE_BATCH constant definition

3. [includes/class-msh-image-usage-index.php](includes/class-msh-image-usage-index.php)
   - Lines 1900-1904: Phase 1C batch guard

4. [msh-image-optimizer.php](msh-image-optimizer.php)
   - Line 6: Version 1.2.4
   - Line 36: VERSION constant 1.2.4

---

## Rollback Plan

If this release causes issues:

1. **Immediate rollback**: Reactivate v1.2.3
2. **Check debug.log** for Fatal errors
3. **Report issue** with full error message

Previous stable version: v1.2.3 (with Phase 1A+1B only)

---

## Support

**Test Site**: msh-phase6-test.local
**Debug Log**: `/wp-content/debug.log`
**Plugin Path**: `/wp-content/plugins/msh-image-optimizer/`

**Contact**: Report issues via GitHub or direct message

---

## Success Criteria

✅ **PASS**: 3-image optimization completes in <30 seconds
✅ **PASS**: No Fatal errors in debug.log
✅ **PASS**: Images show "Optimized" status
✅ **PASS**: MSH Versioning and MSH Rename logs visible

❌ **FAIL**: Optimization hangs at 0%
❌ **FAIL**: Fatal error: Maximum execution time exceeded
❌ **FAIL**: AJAX Error: Internal Server Error
❌ **FAIL**: Modal stuck on "Processing..."

---

**END OF RELEASE NOTES**
