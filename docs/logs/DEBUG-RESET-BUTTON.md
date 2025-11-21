# Reset Button AJAX Debugging - Instructions

## What Was Done

I've added comprehensive debugging to trace why the Reset button's AJAX call wasn't completing. The changes include:

### 1. JavaScript Debugging (image-optimizer-modern.js)
**Lines 3119-3153**: Added console.log statements to `resetOptimizationFlags()`:
- Logs when function is called
- Logs the CONFIG object to verify endpoints and nonce
- Logs AJAX success response
- Logs any errors caught

**Lines 3832-3876**: Added console.log statements to `postWithNonceRetry()`:
- Logs when AJAX call starts
- Logs the URL, payload, and nonce being sent
- Logs when success/error callbacks are triggered
- Logs promise resolution/rejection

### 2. PHP Debugging (class-msh-image-optimizer.php)
**Lines 9304-9366**: Added error_log statements to `ajax_reset_optimization()`:
- Logs when handler is called
- Logs POST data received
- Logs nonce verification result
- Logs user authorization result
- Logs database operation counts
- Logs before sending JSON response

### 3. Version Bump
**msh-image-optimizer.php**: Bumped version from 1.2.9 to **1.2.10** to force browser cache refresh.

---

## Testing Instructions

### Step 1: Clear Browser Cache
1. Open the WordPress admin page with the Image Optimizer
2. Do a **hard refresh**: Cmd+Shift+R (Mac) or Ctrl+Shift+F5 (Windows)
3. Verify the version in the page source shows `?ver=1.2.10`

### Step 2: Open Browser Console
1. Open Chrome DevTools (Cmd+Option+I on Mac, F12 on Windows)
2. Go to the **Console** tab
3. Clear any existing logs

### Step 3: Click Reset Button
1. Click "Clear All Data & Refresh" button
2. Confirm the dialog

### Step 4: Check Console Output

**Expected Console Output (if working correctly):**
```
[DEBUG] resetOptimizationFlags called
[DEBUG] CONFIG: {endpoints: {...}, nonce: "..."}
[DEBUG] postWithNonceRetry executing AJAX call
[DEBUG] URL: http://thedot-optimizer-test.local/wp-admin/admin-ajax.php
[DEBUG] Payload: {action: "msh_reset_optimization"}
[DEBUG] Nonce: abc123...
[DEBUG] Awaiting execute()...
[DEBUG] AJAX success callback triggered
[DEBUG] execute() completed with result: {success: true, data: {...}}
[DEBUG] AJAX success response: {success: true, data: {...}}
```

**If AJAX call hangs (current issue):**
```
[DEBUG] resetOptimizationFlags called
[DEBUG] CONFIG: {endpoints: {...}, nonce: "..."}
[DEBUG] postWithNonceRetry executing AJAX call
[DEBUG] URL: ...
[DEBUG] Payload: ...
[DEBUG] Nonce: ...
[DEBUG] Awaiting execute()...
(nothing more - promise never resolves)
```

**If AJAX call errors:**
```
[DEBUG] resetOptimizationFlags called
...
[DEBUG] AJAX error callback triggered: {status: 500, ...}
[DEBUG] execute() threw error: ...
```

### Step 5: Check PHP Error Log

**Location**: Check Local's PHP error log:
- Local app → Site → Open site shell
- Check: `~/Library/Application Support/Local/log/php-error.log`
- OR: Check site-specific logs in Local app

**Expected PHP Log Output (if request reaches server):**
```
[MSH DEBUG] ajax_reset_optimization handler called
[MSH DEBUG] POST data: Array(...)
[MSH DEBUG] Nonce check passed
[MSH DEBUG] User authorization passed
[MSH DEBUG] Reset count: 10
[MSH DEBUG] AI reset count: 25
[MSH DEBUG] Cache cleared. Sending JSON success response
```

**If no PHP logs appear:**
- The AJAX request is NOT reaching the server
- This points to a JavaScript or WordPress AJAX routing issue

### Step 6: Check Network Tab

1. In DevTools, go to **Network** tab
2. Clear network log
3. Click Reset button again
4. Look for a request to `admin-ajax.php` with:
   - **Name**: `admin-ajax.php`
   - **Method**: POST
   - **Payload**: Should include `action=msh_reset_optimization` and `nonce=...`

**If you only see "heartbeat" requests:**
- The AJAX call is NOT being sent
- This indicates a JavaScript issue (likely CONFIG values or AJAX setup)

---

## Possible Issues & Solutions

### Issue 1: CONFIG.endpoints.optimize is undefined
**Symptom**: Console shows `URL: undefined`

**Solution**: Check that `mshImageOptimizer` is properly localized in the HTML:
```javascript
// Should be in page source:
var mshImageOptimizer = {
    ajaxurl: "http://..../admin-ajax.php",
    nonce: "abc123..."
};
```

### Issue 2: Nonce is invalid/expired
**Symptom**: Console shows 403 error, PHP log shows nonce check failure

**Solution**: Refresh the page to get a new nonce

### Issue 3: AJAX request never sent
**Symptom**: No network request, console stops at "Awaiting execute()..."

**Solution**: Check for JavaScript errors preventing AJAX call (should appear in console)

### Issue 4: AJAX request times out
**Symptom**: Request appears in Network tab but stays "pending"

**Solution**: Check PHP error logs for fatal errors, increase PHP max_execution_time

---

## Next Steps After Testing

1. **Copy ALL console output** from Step 4
2. **Copy PHP error log entries** from Step 5 (if any)
3. **Take screenshot of Network tab** from Step 6
4. Share these with me so I can diagnose the exact issue

The debugging output will tell us exactly where the AJAX call is failing:
- **JavaScript side**: CONFIG values, AJAX call initiation
- **Network**: Request being sent/received
- **PHP side**: Handler execution, database operations

---

## Cleanup (After Issue is Fixed)

Once we've identified and fixed the issue, we'll remove all the debugging logs:
- Remove console.log statements from image-optimizer-modern.js
- Remove error_log statements from class-msh-image-optimizer.php
- This will keep the code clean for production
