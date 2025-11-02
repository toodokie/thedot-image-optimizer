# Reset Button - Test Plan

## Overview
The Reset button AJAX call was hanging without completing. I've added comprehensive debugging and safety features to identify and fix the issue.

---

## What Changed (v1.2.11)

### JavaScript Enhancements
✅ **jQuery availability checks** - Verifies jQuery is loaded before making AJAX call
✅ **30-second timeout** - Prevents infinite hanging
✅ **Enhanced error logging** - Shows detailed error information
✅ **Exception handling** - Catches and reports any exceptions
✅ **Comprehensive debug logging** - Traces every step of execution

### PHP Enhancements
✅ **Server-side debug logging** - Confirms request reaches backend
✅ **Step-by-step execution tracking** - Shows nonce check, auth, DB operations

### HTML Fixes
✅ **Explicit button types** - All buttons now have `type="button"`

---

## Quick Test (5 minutes)

### 1. Refresh Page
- Hard refresh: **Cmd+Shift+R** (Mac) or **Ctrl+Shift+F5** (Windows)
- Verify script version in page source: `image-optimizer-modern.js?ver=1.2.11`

### 2. Open Console
- Chrome DevTools: **Cmd+Option+I** (Mac) or **F12** (Windows)
- Go to **Console** tab
- Clear existing logs

### 3. Click Reset Button
- Click "Clear All Data & Refresh"
- Confirm dialog
- **Watch console output**

### 4. What to Look For

**Success indicators:**
- ✅ Console shows "AJAX success callback triggered"
- ✅ Log shows completion message: "✓ Reset complete!..."
- ✅ Results table clears
- ✅ Stats reset to "Ready for analysis..."

**Failure indicators (but with useful debug info):**
- ❌ Console stops at "Awaiting execute()..." (hanging)
- ❌ Console shows "timeout" after 30 seconds
- ❌ Console shows "jQuery not available" error
- ❌ Console shows exception

---

## Debug Output to Collect

If the button still doesn't work, **copy and share**:

1. **Console output** (all debug messages)
2. **Network tab** (screenshot showing admin-ajax.php requests)
3. **PHP error log** (any lines with `[MSH DEBUG]`)

### Where to Find PHP Error Logs

**Option A: Terminal**
```bash
tail -f ~/Library/Application\ Support/Local/log/php-error.log | grep "MSH DEBUG"
```

**Option B: Local App**
1. Right-click site → "Open Site Shell"
2. Run: `tail -n 50 ~/Library/Application\ Support/Local/log/php-error.log | grep MSH`

---

## Expected Results

### Console (Working)
```
[DEBUG] resetOptimizationFlags called
[DEBUG] CONFIG: {endpoints: {...}, nonce: "..."}
[DEBUG] jQuery available? true
[DEBUG] $.ajax available? true
[DEBUG] Calling $.ajax...
[DEBUG] $.ajax returned: {readyState: 1, ...}
[DEBUG] AJAX success callback triggered
```

### Console (Still Broken)
```
[DEBUG] resetOptimizationFlags called
[DEBUG] Awaiting execute()...
(stops here - OR timeout after 30s)
```

### PHP Log (If Request Reaches Server)
```
[MSH DEBUG] ajax_reset_optimization handler called
[MSH DEBUG] Nonce check passed
[MSH DEBUG] User authorization passed
[MSH DEBUG] Reset count: 10
[MSH DEBUG] Sending JSON success response
```

---

## Next Steps

### If Working
1. Share success message
2. I'll remove all debug logging (cleanup)
3. Done!

### If Still Broken
1. Share all debug output (console + PHP logs + network screenshot)
2. The debug info will show exactly where it's failing:
   - **jQuery not available** → Script loading issue
   - **AJAX call not initiated** → JavaScript error
   - **Timeout** → Server not responding
   - **No PHP logs** → Request not reaching WordPress
3. I'll fix the root cause based on the debug output

---

## Rollback (If Needed)

If debugging causes issues, revert to previous version:
```bash
cd /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/
git checkout HEAD~1 msh-image-optimizer.php
git checkout HEAD~1 assets/js/image-optimizer-modern.js
git checkout HEAD~1 includes/class-msh-image-optimizer.php
git checkout HEAD~1 admin/image-optimizer-admin.php
```

Then hard refresh the page.

---

## Documentation

- **[WHEN-YOU-RETURN.md](WHEN-YOU-RETURN.md)** - Quick action checklist
- **[RESET-BUTTON-FIX-SUMMARY.md](RESET-BUTTON-FIX-SUMMARY.md)** - Complete technical summary
- **[DEBUG-RESET-BUTTON.md](DEBUG-RESET-BUTTON.md)** - Detailed testing instructions

---

**Ready to test!** Just refresh the page and click the Reset button while watching the console. 🚀
