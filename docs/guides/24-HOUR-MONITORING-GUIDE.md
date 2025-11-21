# 24-Hour Production Monitoring Guide - Phase 1 Deployment

**Plugin:** MSH Image Optimizer v1.2.3
**Purpose:** Verify Phase 1 fixes remain stable in production environment
**Duration:** 24-48 hours post-deployment

---

## TL;DR - Quick Monitoring

**You DON'T need to watch the site 24/7!** Just check these 3 things once or twice a day:

1. **Run one batch optimization** (any size) - does it complete without timeout?
2. **Check debug.log** - any NEW Phase 1 errors (ignore legacy cron errors)?
3. **Check transient size** - still < 1MB?

**If all 3 are OK, Phase 1 is stable!** 🎉

---

## What "Monitor for 24 Hours" Actually Means

### **Option A: Hands-Off Monitoring (Recommended)**

**What to do:**
1. Deploy plugin to production
2. Run ONE test batch optimization (3-5 images) immediately after deployment
3. Check the 3 quick checks above
4. **Walk away for 24 hours**
5. Come back, run the quick checks again
6. If still passing → Phase 1 is stable

**Time investment:** 5 minutes at deployment, 5 minutes 24 hours later

---

### **Option B: Active Monitoring (For High-Traffic Sites)**

If your production site processes a LOT of images (hundreds per day), you can monitor more actively:

**Morning (once):**
- Run the 3 Quick Checks below
- Look at debug.log for overnight activity

**Evening (once):**
- Run the 3 Quick Checks again
- Compare transient size

**Time investment:** 5-10 minutes twice per day

---

## The 3 Quick Checks

### **Check #1: Batch Optimization Still Works** ✅

**What:** Verify batch optimization completes without timeout

**How:**
1. Go to WordPress admin → MSH Image Optimizer
2. Click "Optimize" button
3. Select 3-5 images (any images)
4. Start optimization
5. Watch browser - should complete in < 1 minute (not 30+ seconds hang)

**Good Result:**
- ✅ Progress bar moves smoothly
- ✅ Browser shows "completed" or "success" message
- ✅ No browser timeout errors
- ✅ Page remains responsive

**Bad Result (report immediately):**
- ❌ Browser hangs for 30+ seconds
- ❌ "Request timeout" error
- ❌ Page becomes unresponsive
- ❌ Must refresh browser to recover

---

### **Check #2: Debug Log Shows No NEW Phase 1 Errors** ✅

**What:** Verify no new errors from Phase 1 code (ignore old legacy errors)

**How - Quick Method:**
```bash
tail -100 "/path/to/your/site/wp-content/debug.log" | grep -E "(TinyDot|Phase 1|msh_cron_ok|msh_content_usage_lookup)" | tail -20
```

**How - Manual Method:**
1. Open `wp-content/debug.log` in text editor
2. Jump to bottom of file
3. Look for recent entries (today's date/time)
4. Check for any Phase 1 related errors

**Good Result:**
- ✅ No messages about "Background probe detected WP-Cron unavailable" DURING batch optimization
- ✅ Occasional "Background probe" messages are FINE (they run in admin_init)
- ✅ Size cap warnings like "payload 10.84MB exceeds 1MB cap" are GOOD (Phase 1B working!)

**Bad Result (investigate):**
- ❌ PHP Fatal errors mentioning `cron_is_available` or `background_cron_probe`
- ❌ Repeated "WP-Cron unavailable" messages every second (should be max once per 10 min)
- ❌ Errors about `MSH_IN_OPTIMIZE_BATCH` constant already defined
- ❌ Any stack trace mentioning Phase 1A/1B code

**IGNORE These (They're Legacy, Not Phase 1):**
- `Cron unschedule event error for hook: msh_cleanup_rename_backup, Error code: could_not_set`
- `Data: ...main-street-health...` (these are from old migrated database)
- These will continue until those old events expire - not our problem!

---

### **Check #3: Transient Size Stays Small** ✅

**What:** Verify Phase 1B size cap is still preventing bloat

**How - MySQL Command:**
```bash
/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql --socket="/path/to/mysqld.sock" -u root -pYOURPASSWORD YOURDBNAME -e "SELECT option_name, LENGTH(option_value) as size_bytes, ROUND(LENGTH(option_value)/1048576, 2) as size_mb FROM wp_options WHERE option_name LIKE '%msh_content_usage%' ORDER BY size_bytes DESC LIMIT 5;"
```

**How - WP-CLI Command (if available):**
```bash
wp db query "SELECT option_name, LENGTH(option_value) as size_bytes FROM wp_options WHERE option_name LIKE '%msh_content_usage%' ORDER BY size_bytes DESC LIMIT 5;"
```

**Good Result:**
- ✅ Empty result (no transient exists) - BEST case
- ✅ `size_mb` < 1.0 - Phase 1B working perfectly
- ✅ If exactly 0 results, transient was skipped due to size cap (working as designed!)

**Bad Result (Phase 1B failed):**
- ❌ `size_mb` > 10.0 - The 11MB monster is back!
- ❌ `size_bytes` > 11000000 - Size cap NOT working

**What If Transient Exists But < 1MB:**
- This is NORMAL and GOOD
- Means site has fewer images/usage now
- Phase 1B is working (capping large ones, allowing small ones)

---

## Copy-Paste Monitoring Commands

### **For Local by Flywheel Sites:**

Replace these placeholders:
- `MNkBrZ7Kh` → Your site ID (find in Library/Application Support/Local/run/)
- `local` → Your database name (usually 'local')

```bash
# Quick Check: Transient size
/Applications/Local.app/Contents/Resources/extraResources/lightning-services/mysql-8.0.35+4/bin/darwin-arm64/bin/mysql --socket="/Users/anastasiavolkova/Library/Application Support/Local/run/MNkBrZ7Kh/mysql/mysqld.sock" -u root -proot local -e "SELECT option_name, ROUND(LENGTH(option_value)/1048576, 2) as size_mb FROM wp_options WHERE option_name LIKE '%msh_content_usage%';"

# Quick Check: Debug log last 50 Phase 1 related entries
tail -200 "/path/to/site/wp-content/debug.log" | grep -i "tinydot\|phase\|msh_cron"

# Quick Check: Any PHP fatal errors today
grep -E "Fatal|Parse error" "/path/to/site/wp-content/debug.log" | tail -20
```

### **For Regular WordPress Installs:**

```bash
# Quick Check: Transient size via WP-CLI
wp db query "SELECT option_name, ROUND(LENGTH(option_value)/1048576, 2) as size_mb FROM wp_options WHERE option_name LIKE '%msh_content_usage%';"

# Quick Check: Cleanup token count (should stay low)
wp db query "SELECT COUNT(*) as cleanup_token_count FROM wp_options WHERE option_name LIKE 'msh_cleanup_map_%';"

# Quick Check: Recent debug log entries
tail -100 wp-content/debug.log | grep -i "msh\|phase\|tinydot"
```

---

## What You're Actually Looking For

### **Phase 1A Success Indicators:**
- ✅ Batch optimization completes in < 1 minute (vs 30+ second timeout before)
- ✅ No "Background probe" messages logged DURING batch operations
- ✅ Background probe runs in admin_init (you'll see it occasionally in logs - that's GOOD)
- ✅ Browser stays responsive during optimization

### **Phase 1B Success Indicators:**
- ✅ No giant transient (> 10MB) recreation
- ✅ Size cap warnings appear if transient would exceed 1MB (this is working correctly!)
- ✅ System operates normally even without the transient cached

### **Failure Indicators (Report These):**
- ❌ Batch timeout returns (30+ seconds per request)
- ❌ Site hangs or becomes unresponsive during optimization
- ❌ Transient grows back to 11+ MB
- ❌ PHP fatal errors in debug.log
- ❌ New cron errors every request (vs legacy errors every minute)

---

## Monitoring Schedule Examples

### **Scenario 1: Low-Traffic Site (Blogger/Small Business)**

**Day 1 (Deployment):**
- 09:00 - Deploy plugin
- 09:05 - Run test batch (3 images)
- 09:10 - Check debug log
- 09:15 - Check transient size
- **Walk away**

**Day 2 (24 Hours Later):**
- 09:00 - Run test batch again
- 09:05 - Check debug log for overnight activity
- 09:10 - Check transient size
- **Done! Phase 1 validated** ✅

**Time investment:** 30 minutes total over 2 days

---

### **Scenario 2: High-Traffic Site (E-commerce/Media)**

**Day 1 (Deployment):**
- Morning: Deploy, run immediate checks (15 min)
- Afternoon: Quick check batch + logs (5 min)
- Evening: Check transient size (2 min)

**Day 2:**
- Morning: Run checks again (10 min)
- Evening: Final validation (5 min)

**Day 3 (Optional):**
- Morning: Spot check (5 min)
- **Done! Phase 1 validated** ✅

**Time investment:** ~45 minutes over 2-3 days

---

## Troubleshooting Guide

### **Problem: Batch optimization times out again**

**Diagnosis Commands:**
```bash
# Check if background probe is running
grep "Background probe" debug.log | tail -5

# Check if batch guard is working
grep "MSH_IN_OPTIMIZE_BATCH" debug.log | tail -5

# Check cron health status
wp db query "SELECT * FROM wp_options WHERE option_name LIKE '%msh_cron%';"
```

**Possible Causes:**
1. **Old version still active** - Check plugin version in admin (should be 1.2.3)
2. **Caching issue** - Hard refresh browser (Cmd+Shift+R)
3. **Different issue** - May not be Phase 1 related, check for other plugin conflicts

---

### **Problem: Giant transient returns (> 10MB)**

**Diagnosis Commands:**
```bash
# Check if hotfix ran
wp option get msh_usage_cache_hotfix_done
# Should return: 1

# Check filter value
wp eval "echo apply_filters('msh_lookup_max_bytes', 1024*1024);"
# Should return: 1048576 (1MB in bytes)
```

**Possible Causes:**
1. **Hotfix didn't run** - Plugin may not have been properly activated
2. **Filter override** - Theme or another plugin may be filtering `msh_lookup_max_bytes` to a higher value
3. **Old transient cached** - Transient may have been created before v1.2.3 was deployed

**Fix:**
```bash
# Manually delete old transient
wp transient delete msh_content_usage_lookup

# Manually run hotfix flag
wp option update msh_usage_cache_hotfix_done 1
```

---

### **Problem: Site still feels slow**

**Check These:**
- Database overall size (wp_options table)
- Other plugin conflicts
- Server resources (CPU/memory)
- Legacy cron errors accumulating (unrelated to Phase 1, but may indicate db issues)

**Remember:** Phase 1 only fixes:
1. Batch optimization timeout (30s → <10s)
2. Transient bloat (11MB → 0-1MB)

It doesn't fix:
- General server slowness
- Other plugin issues
- Database bloat from other sources

---

## What to Report as a Problem

### **Definitely Report These:**
1. Batch optimization timeout returns (30+ seconds)
2. PHP fatal errors mentioning Phase 1A/1B code
3. Transient grows back to 11+ MB
4. Site crashes or becomes unresponsive
5. Error messages about `MSH_IN_OPTIMIZE_BATCH` or `background_cron_probe`

### **Don't Report These (They're Normal):**
1. Legacy cron errors (`msh_cleanup_rename_backup...could_not_set...main-street-health`)
2. Occasional "Background probe" messages in admin_init (working as designed!)
3. Size cap warnings ("payload 10.84MB exceeds 1MB cap" - this is Phase 1B working!)
4. Backup files accumulating (Phase 2 will handle this with daily GC)
5. Season cache messages (unrelated to Phase 1)

---

## Success Criteria Summary

After 24-48 hours, Phase 1 is **STABLE AND SUCCESSFUL** if:

| Check | Status |
|-------|--------|
| Batch optimization completes without timeout | ✅ |
| No NEW Phase 1 related errors in debug.log | ✅ |
| Transient size remains < 1MB (or doesn't exist) | ✅ |
| Site remains responsive during optimization | ✅ |
| No user complaints about slow image processing | ✅ |

**If all 5 are ✅, you're good to proceed with Phase 2!**

---

## Phase 2 Preview

Once Phase 1 is stable (24-48 hours), Phase 2 will add:

1. **Daily Garbage Collector** - Cleans up old backup files automatically
2. **System Health Tab** - UI showing plugin status, cron health, backup count
3. **WP-CLI Commands** - Manual GC, status checks, diagnostics
4. **Manual GC Button** - For when cron is broken

**Phase 2 Benefits:**
- Eliminates backup file accumulation
- Removes last dependency on per-file cron scheduling
- Provides visibility and control for site admins

---

## Final Notes

**You're monitoring for regressions, not new features:**
- Phase 1 fixed 2 specific critical bugs
- We're verifying those fixes don't break over time
- Normal site operation is expected
- If you forget to check for a day, that's fine - Phase 1 either works or it doesn't

**The 24-hour window is conservative:**
- Most issues would appear in first 1-2 hours
- 24-48 hours gives confidence across multiple usage patterns
- If nothing breaks in 48 hours, it's very unlikely to break later

**When in doubt:**
- Run a test batch optimization
- Check if it completes quickly (<1 min)
- If yes, Phase 1 is working ✅

---

**Happy monitoring! And remember: No news is good news.** 🎉
