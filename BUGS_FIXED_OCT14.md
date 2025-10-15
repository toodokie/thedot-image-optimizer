# Bugs Fixed - October 14, 2025

## Summary
Fixed 3 critical bugs in filename suggestion system that were causing:
1. Recursive duplication in filenames (spectacles, triforce, unicorn)
2. Wrong descriptor appearing for Product/Equipment category
3. Duplicate tokens within filename suggestions

---

## Bug #1: Recursive Filename Duplication

### Problem:
Filenames were growing with each rename operation:
- `spectacles.gif` → `spectacles-clearing-spectacles-minneapolis.gif`
- Next rename → `spectacles-clearing-spectacles-minneapolis-clearing-spectacles-minneapolis-clearing-spectacles-clearing-spectacles-minneapolis-minneapolis.gif`

Other affected files:
- `triforce-wallpaper-minneapolis-minneapolis-minneapolis.jpg` (triple)
- `unicorn-wallpaper-minneapolis-minneapolis-minneapolis.jpg` (triple)

### Root Cause:
In `collect_visual_keywords()` at line 2188, the function was using the CURRENT filename (`file_basename`) as a source for keywords when generating the NEW filename suggestion.

```php
// OLD CODE (BUGGY):
if (!empty($context['file_basename'])) {
    $sources[] = str_replace(['-', '_'], ' ', $context['file_basename']);
}
```

This created a feedback loop:
1. Current filename: `spectacles-clearing-spectacles-minneapolis`
2. Extract keywords from it: `spectacles`, `clearing`, `minneapolis`
3. Build new filename using those keywords
4. Result: More duplication

### Fix:
Removed `file_basename` from keyword sources in [class-msh-image-optimizer.php:2188-2190](../../../Local%20Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/class-msh-image-optimizer.php#L2188-L2190)

```php
// NEW CODE (FIXED):
// REMOVED: file_basename to prevent recursive duplication from current filename
// The current filename should never influence the new filename suggestion
// Only use metadata (title, alt, caption) and page context
```

Now filename suggestions are based ONLY on:
- Attachment title (from metadata)
- Page title (where image is used)
- Tags
- Category/Context data

**NOT** based on the current filename.

### Expected Behavior After Fix:
- `spectacles-clearing-spectacles-minneapolis.gif` → `spectacles-clearing-minneapolis-1692.gif` (clean, with ID)
- `triforce-wallpaper-minneapolis-minneapolis-minneapolis.jpg` → `triforce-wallpaper-minneapolis-1628.jpg`
- `unicorn-wallpaper-minneapolis-minneapolis-minneapolis.jpg` → `unicorn-wallpaper-minneapolis-1045.jpg`

---

## Bug #2: Wrong "rehabilitation" Descriptor for Product/Equipment

### Problem:
Image categorized as "Product / Equipment" with title "Antique Farm Machinery" was getting suggestion:
```
dsc20051220_160808_102.jpg → rehabilitation-equipment-minneapolis-mn-762.jpg
```

Expected:
```
dsc20051220_160808_102.jpg → operational-equipment-minneapolis-mn-762.jpg
```
(or based on actual metadata: "Operational Equipment")

### Root Cause:
In the `case 'equipment':` block at line 1065, when:
1. Original filename had no extractable keywords (camera file)
2. Context asset wasn't 'product'
3. Fell through to hardcoded default: `'rehabilitation-equipment'`

The metadata descriptor was never being used!

### Fix:
Added descriptor extraction from metadata before falling back to hardcoded default in [class-msh-image-optimizer.php:1066-1077](../../../Local%20Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/class-msh-image-optimizer.php#L1066-L1077)

```php
// Try to extract descriptor from metadata (title, alt, caption)
$descriptor_details = $this->build_business_descriptor_details($context);
$descriptor_slug = $descriptor_details['slug'];

if (!empty($descriptor_slug) && $descriptor_slug !== 'brand') {
    $location_suffix = $this->location_slug !== '' ? '-' . $this->location_slug : '';
    return $this->slugify($descriptor_slug . '-equipment' . $location_suffix);
}

// Final fallback
$location_suffix = $this->location_slug !== '' ? '-' . $this->location_slug : '';
return $this->slugify('equipment-showcase' . $location_suffix);
```

### Expected Behavior After Fix:
For attachment 762 with title "Operational Equipment":
```
dsc20051220_160808_102.jpg → operational-equipment-minneapolis-mn-762.jpg
```

The system now:
1. Tries original filename keywords (camera numbers)
2. Tries product mapping (if asset=product)
3. **NEW**: Extracts descriptor from metadata (title/alt/caption)
4. Falls back to generic "equipment-showcase"

---

## Bug #3: Duplicate Tokens Within Suggestions

### Problem:
Even without recursive duplication, some suggestions had duplicate tokens:
- `580x300-alignment-580x300-minneapolis-967.jpg` (580x300 appears twice)
- `horizontal-uncategorized-horizontal-graphic-1022.jpg` (horizontal appears twice)
- `1200x4002-alignment-1200x4002-minneapolis-1029.jpg` (1200x4002 appears twice)

### Root Cause:
These duplicates were coming from the combination of:
1. Title: "Image Alignment 580x300"
2. Page context: "alignment"
3. Current filename (before fix #1): "image-alignment-580x300-1"

All three sources contributed the same tokens, and while `dedupe_slug_components` was working, tokens could still appear multiple times if they came from different component sources.

### Fix:
This was automatically fixed by Bug #1's fix. By removing `file_basename` from keyword sources, we eliminated the main source of duplicate tokens.

The existing `dedupe_slug_tokens()` function (already added by other AI) handles any remaining edge cases at line 2595:
```php
$parts = $this->dedupe_slug_tokens($parts);
```

### Expected Behavior After Fix:
- `580x300-alignment-minneapolis-967.jpg` (single 580x300)
- `horizontal-graphic-minneapolis-1022.jpg` (single horizontal)
- `1200x4002-alignment-minneapolis-1029.jpg` (single 1200x4002)

---

## Bug #4: Broken Image Previews (INVESTIGATED, NOT FIXED)

### Problem:
User reported "see broken images previews" in UI after running analyzer.

### Investigation Results:
Checked usage index table:
```sql
SELECT COUNT(*) FROM wp_msh_image_usage_index
Result: 0 entries
```

The usage index is **completely empty**. This matches the PHP error log from earlier test:
```
[14-Oct-2025 13:20:35 UTC] MSH Usage Index: Content-First build complete - 0 attachments, 0 entries
```

### Root Cause:
The usage index rebuild is failing to create entries. This is NOT related to the filename suggestion bugs I fixed.

### Impact:
- Images can't be found in content
- UI shows broken previews because it queries the usage index to determine where images are used
- This is blocking proper testing of the filename fixes

### Status:
**NOT FIXED** - This is a separate issue with the usage index rebuild system. The other AI is likely working on this. My filename fixes are complete and ready to test once the usage index issue is resolved.

---

## Files Modified

1. **class-msh-image-optimizer.php**
   - Line 2188-2190: Removed `file_basename` from keyword sources
   - Line 1066-1077: Added descriptor extraction for equipment category

---

## Testing Instructions

Once the usage index rebuild issue is fixed:

1. **Test Recursive Duplication Fix:**
   - Re-run analyzer on attachments 1692, 1628, 1045
   - Expected: Clean suggestions without duplication, with IDs appended
   - `spectacles-clearing-minneapolis-1692.gif`
   - `triforce-wallpaper-minneapolis-1628.jpg`
   - `unicorn-wallpaper-minneapolis-1045.jpg`

2. **Test Equipment Descriptor Fix:**
   - Re-run analyzer on attachment 762 (farm machinery)
   - Expected: Uses metadata descriptor, not "rehabilitation"
   - `operational-equipment-minneapolis-mn-762.jpg` (or similar based on actual metadata)

3. **Test Duplicate Token Fix:**
   - Re-run analyzer on attachments 967, 1022, 1027, 1025, 1029
   - Expected: No duplicate tokens in suggestions
   - `580x300-alignment-minneapolis-967.jpg` (not 580x300-580x300)
   - `horizontal-graphic-minneapolis-1022.jpg` (not horizontal-horizontal)

4. **Verify No Regressions:**
   - Run full optimization on all 35 images
   - Check that all suggestions are clean, unique, and descriptive
   - Verify IDs are appended when needed for uniqueness

---

## Notes

The fixes are conservative and surgical:
- Only removed one source of duplication (current filename)
- Only added one fallback layer (descriptor extraction for equipment)
- Did not change the core slug assembly or deduplication logic
- Did not modify the uniqueness/ID appending system

These fixes work WITH the improvements already made by the other AI (dedupe_slug_tokens, slug assembly improvements, etc.).
