# Endpoint Fix - Edit Current Filename Feature

## Issue Identified by Review
**High Priority:** `saveCurrentFilename()` was calling non-existent AJAX action `msh_apply_filename_suggestion` (singular), but only `msh_apply_filename_suggestions` (plural) exists in PHP.

## Root Cause
The batch rename endpoint is registered as:
```php
add_action('wp_ajax_msh_apply_filename_suggestions', array($this, 'ajax_apply_filename_suggestions'));
```

But the JavaScript was calling:
```javascript
action: 'msh_apply_filename_suggestion' // singular - doesn't exist!
```

WordPress would return `0` and the rename would silently fail.

## Fix Applied
Changed `saveCurrentFilename()` method in [image-optimizer-modern.js:2432-2490](../../../Local%20Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/js/image-optimizer-modern.js#L2432-L2490) to use a two-step process:

### Step 1: Save as Suggestion
```javascript
const saveSuggestionResponse = await $.post(mshImageOptimizer.ajaxurl, {
    action: 'msh_save_filename_suggestion',
    nonce: mshImageOptimizer.nonce,
    image_id: attachmentId,
    suggested_filename: newFilename
});
```

### Step 2: Apply via Batch Endpoint
```javascript
const applyResponse = await $.post(mshImageOptimizer.ajaxurl, {
    action: 'msh_apply_filename_suggestions', // plural - correct!
    nonce: mshImageOptimizer.nonce,
    image_ids: [attachmentId],
    mode: 'selected',
    batch_number: 1,
    total_files: 1
});
```

## Why This Approach
1. **Reuses existing endpoints** - No need to create new PHP handler
2. **Consistent with UI** - Same endpoints used by "Apply" button on suggestions
3. **Full validation** - Goes through Safe Rename system with verification
4. **Batch-compatible** - Single image is just a batch of 1

## Testing
The flow now:
1. User edits filename inline → clicks Save
2. Suggestion saved via `msh_save_filename_suggestion` ✅
3. Suggestion applied via `msh_apply_filename_suggestions` ✅
4. Safe Rename executes with backup/verification ✅
5. UI updates with new filename ✅

## Second Issue: Response Parsing Mismatch

**High Priority:** The batch API returns results with `status` field (string), not `success` (boolean).

### The Problem
```javascript
// API returns:
{
    id: 123,
    status: 'success',  // or 'error', 'skipped', 'test'
    message: 'References updated: 5'
}

// JavaScript was checking:
if (result.success)  // ❌ undefined, always false - falls into error branch

// So even successful renames showed error alert!
```

### Fix Applied
Changed response check in [image-optimizer-modern.js:2461](../../../Local%20Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/js/image-optimizer-modern.js#L2461):

```javascript
// OLD (BUGGY):
if (result && result.success) {

// NEW (FIXED):
if (result && result.status === 'success') {
```

Also added status info to error messages for debugging:
```javascript
const statusInfo = result ? ` (status: ${result.status})` : '';
alert('Error applying filename: ' + errorMsg + statusInfo);
```

Now properly handles all status values:
- `'success'` → Updates UI, shows new filename
- `'error'` → Shows error message
- `'skipped'` → Shows skip reason
- `'test'` → Shows test result

## Changed Files
- **assets/js/image-optimizer-modern.js** (Lines 2432-2490)
  - Changed from single AJAX call to two-step process
  - Added proper error handling for both steps
  - **Fixed response parsing: `result.success` → `result.status === 'success'`**
  - Added status info to error messages

## Status
✅ **FULLY FIXED** - Now calls correct endpoints AND parses responses correctly.
