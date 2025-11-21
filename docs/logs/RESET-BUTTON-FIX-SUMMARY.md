# Reset Button AJAX Issue - Complete Summary

## Problem
The "Clear All Data & Refresh" button's AJAX call never completes. The initial log message appears ("[11:30:29 PM] Resetting all optimization data and clearing cache...") but no backend response or completion message follows.

**Symptoms:**
- Initial log message displays correctly (proving JavaScript loads)
- No subsequent completion message
- No `msh_reset_optimization` request visible in Network tab (only heartbeat)
- No PHP errors in Local error logs
- User stuck at initial message with no feedback

---

## Changes Made

### 1. JavaScript Debugging + Enhancements ([image-optimizer-modern.js](../../assets/css/image-optimizer-modern.js))

#### Lines 3119-3153: `resetOptimizationFlags()`
Added console.log statements to track execution:
```javascript
static resetOptimizationFlags() {
    this.updateLog('Resetting all optimization data and clearing cache...');
    console.log('[DEBUG] resetOptimizationFlags called');
    console.log('[DEBUG] CONFIG:', CONFIG);  // Shows ajaxurl and nonce

    this.postWithNonceRetry({ action: 'msh_reset_optimization' })
        .then((response) => {
            console.log('[DEBUG] AJAX success response:', response);
            // ... rest of success handler
        })
        .catch((error) => {
            console.log('[DEBUG] AJAX error caught:', error);
            // ... rest of error handler
        });
}
```

#### Lines 3832-3890: `postWithNonceRetry()`
Added comprehensive debugging and error handling:
```javascript
static async postWithNonceRetry(payload, retry = true) {
    const execute = () => new Promise((resolve, reject) => {
        console.log('[DEBUG] postWithNonceRetry executing AJAX call');
        console.log('[DEBUG] jQuery available?', typeof $ !== 'undefined');
        console.log('[DEBUG] $.ajax available?', typeof $.ajax === 'function');
        console.log('[DEBUG] URL:', CONFIG.endpoints.optimize);
        console.log('[DEBUG] Payload:', payload);
        console.log('[DEBUG] Nonce:', CONFIG.nonce);

        // Check jQuery availability
        if (typeof $ === 'undefined' || typeof $.ajax !== 'function') {
            console.error('[DEBUG] jQuery or $.ajax not available!');
            reject(new Error('jQuery not available'));
            return;
        }

        const ajaxOptions = {
            url: CONFIG.endpoints.optimize,
            method: 'POST',
            data: { nonce: CONFIG.nonce, ...payload },
            timeout: 30000, // 30 second timeout - prevents infinite hanging
            success: (response) => {
                console.log('[DEBUG] AJAX success callback triggered');
                resolve(response);
            },
            error: (xhr, textStatus, errorThrown) => {
                console.log('[DEBUG] AJAX error callback triggered');
                console.log('[DEBUG] XHR:', xhr);
                console.log('[DEBUG] Status:', textStatus);
                console.log('[DEBUG] Error:', errorThrown);
                reject(xhr);
            }
        };

        console.log('[DEBUG] AJAX options:', ajaxOptions);
        console.log('[DEBUG] Calling $.ajax...');

        try {
            const jqXHR = $.ajax(ajaxOptions);
            console.log('[DEBUG] $.ajax returned:', jqXHR);
        } catch (e) {
            console.error('[DEBUG] Exception calling $.ajax:', e);
            reject(e);
        }
    });

    try {
        console.log('[DEBUG] Awaiting execute()...');
        const result = await execute();
        console.log('[DEBUG] execute() completed with result:', result);
        return result;
    } catch (xhr) {
        console.log('[DEBUG] execute() threw error:', xhr);
        // ... error handling
    }
}
```

**Why This Helps:**
- **Checks jQuery availability** - Verifies $ and $.ajax exist
- **30-second timeout** - Prevents infinite hanging (will trigger error callback after 30s)
- **Enhanced error logging** - Shows XHR object, status text, and error details
- **Exception handling** - Catches any exceptions thrown during $.ajax call
- Shows if AJAX call is actually initiated
- Reveals CONFIG values (URL, nonce, payload)
- Indicates whether promise resolves or rejects
- Identifies exactly where execution stops

---

### 2. PHP Debugging ([class-msh-image-optimizer.php](../../includes/class-msh-image-optimizer.php))

#### Lines 9304-9366: `ajax_reset_optimization()`
Added error_log statements at every step:
```php
public function ajax_reset_optimization() {
    error_log( '[MSH DEBUG] ajax_reset_optimization handler called' );
    error_log( '[MSH DEBUG] POST data: ' . print_r( $_POST, true ) );

    check_ajax_referer( 'msh_image_optimizer', 'nonce' );
    error_log( '[MSH DEBUG] Nonce check passed' );

    if ( ! current_user_can( 'manage_options' ) ) {
        error_log( '[MSH DEBUG] User lacks manage_options capability' );
        wp_die( 'Unauthorized' );
    }

    error_log( '[MSH DEBUG] User authorization passed' );

    // ... database operations ...

    error_log( '[MSH DEBUG] Reset count: ' . $reset_count );
    error_log( '[MSH DEBUG] AI reset count: ' . $ai_reset_count );
    error_log( '[MSH DEBUG] Cache cleared. Sending JSON success response' );

    wp_send_json_success( ... );
}
```

**Why This Helps:**
- Confirms if request reaches the server
- Shows nonce verification result
- Reveals user permission issues
- Tracks database operation counts
- Verifies JSON response is sent

---

### 3. Button Type Attributes ([image-optimizer-admin.php](../../admin/image-optimizer-admin.php))

**Lines 973-988:**
Added explicit `type="button"` to all action buttons:
```php
<button type="button" id="analyze-images" class="button button-dot-primary">
<button type="button" id="apply-filename-suggestions" class="button button-dot-primary" disabled>
<button type="button" id="verify-webp-status" class="button button-dot-secondary">
<button type="button" id="reset-optimization" class="button button-dot-secondary">
```

**Why This Helps:**
- Prevents buttons from accidentally trying to submit forms
- Ensures buttons only perform JavaScript click actions
- Eliminates potential page reload issues

---

### 4. Version Bump ([msh-image-optimizer.php](../../msh-image-optimizer.php))

**Lines 6 and 36:**
```php
// Header comment:
Version: 1.2.11

// Class constant:
const VERSION = '1.2.11';
```

**Why This Helps:**
- Forces browser to reload JavaScript file (cache busting)
- Ensures new debugging code is loaded
- Previous version: 1.2.9 → New version: 1.2.11

---

## Verification of Setup

I verified that the AJAX infrastructure is correctly configured:

### Script Enqueuing ([image-optimizer-admin.php](../../admin/image-optimizer-admin.php))
**Lines 193-199:**
```php
wp_enqueue_script(
    'msh-image-optimizer-modern',
    trailingslashit( MSH_IO_ASSETS_URL ) . 'js/image-optimizer-modern.js',
    array( 'jquery' ),
    MSH_Image_Optimizer_Plugin::VERSION,  // Uses 1.2.10 for cache busting
    true
);
```

### Script Localization ([image-optimizer-admin.php](../../admin/image-optimizer-admin.php))
**Lines 231-236:**
```php
wp_localize_script(
    'msh-image-optimizer-modern',
    'mshImageOptimizer',
    array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'msh_image_optimizer' ),
        // ... other config values
    )
);
```

### AJAX Action Hook ([class-msh-image-optimizer.php](../../includes/class-msh-image-optimizer.php))
**Line 6074:**
```php
add_action( 'wp_ajax_msh_reset_optimization', array( $this, 'ajax_reset_optimization' ) );
```

**Result:** All infrastructure is correctly set up. The issue is not with the configuration but with the runtime AJAX execution.

---

## Next Steps for Diagnosis

See [DEBUG-RESET-BUTTON.md](DEBUG-RESET-BUTTON.md) for detailed testing instructions.

### Quick Steps:
1. **Hard refresh** the admin page (Cmd+Shift+R / Ctrl+Shift+F5)
2. Verify script loads with `?ver=1.2.10` in page source
3. **Open browser console** (DevTools → Console tab)
4. **Click Reset button** and confirm dialog
5. **Copy all console output** showing debug logs
6. **Check PHP error log** for `[MSH DEBUG]` entries
7. **Check Network tab** for `admin-ajax.php` request

### Expected Debug Output

**If working correctly (JavaScript → Network → PHP):**
```
[Console]
[DEBUG] resetOptimizationFlags called
[DEBUG] CONFIG: {endpoints: {optimize: "..."}, nonce: "..."}
[DEBUG] postWithNonceRetry executing AJAX call
[DEBUG] URL: http://thedot-optimizer-test.local/wp-admin/admin-ajax.php
[DEBUG] Payload: {action: "msh_reset_optimization"}
[DEBUG] Nonce: abc123...
[DEBUG] Awaiting execute()...
[DEBUG] AJAX success callback triggered
[DEBUG] execute() completed with result: {success: true, data: {...}}
[DEBUG] AJAX success response: {success: true, data: {...}}

[Network Tab]
POST admin-ajax.php
  action: msh_reset_optimization
  nonce: abc123...

[PHP Error Log]
[MSH DEBUG] ajax_reset_optimization handler called
[MSH DEBUG] POST data: Array(...)
[MSH DEBUG] Nonce check passed
[MSH DEBUG] User authorization passed
[MSH DEBUG] Reset count: 10
[MSH DEBUG] AI reset count: 25
[MSH DEBUG] Cache cleared. Sending JSON success response
```

**Current behavior (AJAX never completes):**
```
[Console]
[DEBUG] resetOptimizationFlags called
[DEBUG] CONFIG: {...}
[DEBUG] postWithNonceRetry executing AJAX call
[DEBUG] URL: ...
[DEBUG] Payload: ...
[DEBUG] Nonce: ...
[DEBUG] Awaiting execute()...
(nothing more - promise hangs)

[Network Tab]
(only heartbeat requests, no admin-ajax.php for msh_reset_optimization)

[PHP Error Log]
(no [MSH DEBUG] entries)
```

This output will pinpoint exactly where the AJAX call fails:
- **Stops after "Awaiting execute()..."** → jQuery AJAX call not initiating
- **Shows "AJAX error callback"** → Server returned error (check status code)
- **PHP logs appear** → Request reached server (check for nonce/permission errors)

---

## Files Modified

1. **[msh-image-optimizer/assets/js/image-optimizer-modern.js](../../assets/css/image-optimizer-modern.js)**
   - Lines 3119-3153: Added debugging to `resetOptimizationFlags()`
   - Lines 3832-3890: Enhanced `postWithNonceRetry()` with:
     - jQuery availability checks
     - 30-second timeout
     - Enhanced error logging
     - Exception handling

2. **[msh-image-optimizer/includes/class-msh-image-optimizer.php](../../includes/class-msh-image-optimizer.php)**
   - Lines 9304-9366: Added comprehensive debugging to `ajax_reset_optimization()`

3. **[msh-image-optimizer/admin/image-optimizer-admin.php](../../admin/image-optimizer-admin.php)**
   - Lines 973-988: Added explicit `type="button"` to all action buttons

4. **[msh-image-optimizer/msh-image-optimizer.php](../../msh-image-optimizer.php)**
   - Line 6: Version bumped to 1.2.11 (header comment)
   - Line 36: Version bumped to 1.2.11 (class constant)

---

## Cleanup (After Fix)

Once the issue is identified and fixed, we'll remove all debugging:
```bash
# Search for all debug logs:
grep -r '\[DEBUG\]' msh-image-optimizer/assets/js/
grep -r '\[MSH DEBUG\]' msh-image-optimizer/includes/

# Remove console.log('[DEBUG] ...') from JavaScript
# Remove error_log('[MSH DEBUG] ...') from PHP
```

This will keep the production code clean.

---

## Additional Notes

- The AJAX action (`msh_reset_optimization`) is properly registered
- The nonce is correctly generated and passed
- The script localization is working (CONFIG object should be populated)
- The issue appears to be runtime-specific (promise not resolving)

The comprehensive debugging will reveal exactly what's happening.
