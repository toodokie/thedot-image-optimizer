# Region Property Fix - November 4, 2025

## Bug Summary

**Issue**: PHP Warning: Undefined property `MSH_Contextual_Meta_Generator::$region`
**Location**: [class-msh-image-optimizer.php:4419](includes/class-msh-image-optimizer.php#L4419)
**Triggered By**: Stock images during analyze in manual (non-AI) mode
**Impact**: Non-fatal warning, metadata still generated despite warning

## Root Cause

The `generate_stock_smart_rephrase()` method was trying to access `$this->region` property which didn't exist in the class.

### Code Context (Line 4419)

```php
'biz_context' => array(
    'business_name' => $this->business_name,
    'city'          => $this->city,
    'region'        => $this->region,  // ← Undefined property
    'country'       => $this->country,
    ...
)
```

### Why It Happened

In the `hydrate_active_context()` method (line 252), `$region` was loaded from the business context as a **local variable**:

```php
$region = isset( $context['region'] ) ? sanitize_text_field( $context['region'] ) : '';
```

But it was **never stored as a class property**:
- ✅ `$this->city = $city;` (stored)
- ✅ `$this->country = $country;` (stored)
- ❌ `$this->region = $region;` (missing!)

The `$region` variable was only used to build the `$this->location` string and then discarded.

## Fix Applied

### 1. Added Property Declaration

**File**: [includes/class-msh-image-optimizer.php:17](includes/class-msh-image-optimizer.php#L17)

```php
class MSH_Contextual_Meta_Generator {
    private $business_name        = '';
    private $location             = '';
    private $location_slug        = '';
    private $city                 = '';
    private $city_slug            = '';
    private $region               = '';  // ← ADDED
    private $country              = '';
    private $service_area         = '';
    ...
}
```

### 2. Added Property Assignment

**File**: [includes/class-msh-image-optimizer.php:266](includes/class-msh-image-optimizer.php#L266)

```php
private function hydrate_active_context() {
    ...
    $this->service_area = $service_area;
    $this->country      = $country;
    $this->region       = $region;  // ← ADDED

    $this->city      = $city;
    $this->city_slug = $city !== '' ? MSH_Image_Optimizer_Context_Helper::slugify( $city ) : '';
    ...
}
```

## Testing

### Verification Steps Completed

✅ **1. Property Declaration Verified**
```bash
grep -n "private \$region" class-msh-image-optimizer.php
# Result: 17:	private $region               = '';
```

✅ **2. Property Assignment Verified**
```bash
grep -n "this->region\s*=" class-msh-image-optimizer.php
# Result: 266:		$this->region       = $region;
```

✅ **3. Property Access Verified**
```bash
sed -n '4415,4420p' class-msh-image-optimizer.php
# Result: Shows 'region' => $this->region, at line 4419
```

✅ **4. Reflection Test Passed**
```php
$reflection = new ReflectionClass( 'MSH_Contextual_Meta_Generator' );
// Confirmed: $region property exists in class
```

### Expected Result

When user runs **Reset + Analyze** in the WordPress admin:

**Before Fix** (logs from 23:29:29 UTC):
```
[04-Nov-2025 23:29:29 UTC] [ANALYZE] ROW 755 ct=stock seo=1 ai=0 needs_ai=0 mode=manual
[04-Nov-2025 23:29:29 UTC] [ANALYZE] AI_SKIP - Attachment #755 skipped (manual mode)
[04-Nov-2025 23:29:29 UTC] PHP Warning:  Undefined property: MSH_Contextual_Meta_Generator::$region
in .../class-msh-image-optimizer.php on line 4417
```

**After Fix** (expected):
```
[04-Nov-2025 XX:XX:XX UTC] [ANALYZE] ROW 755 ct=stock seo=1 ai=0 needs_ai=0 mode=manual
[04-Nov-2025 XX:XX:XX UTC] [ANALYZE] AI_SKIP - Attachment #755 skipped (manual mode)
(no PHP warning)
```

## Test Plan for User

To verify the fix is working:

1. **Navigate to**: Image Optimizer admin page
2. **Click**: "Analyze" button (or "Clear All Data & Refresh" then "Analyze")
3. **Monitor logs**:
   ```bash
   tail -f wp-content/debug.log | grep -E "(ANALYZE|region|Warning)"
   ```
4. **Expected**: No "Undefined property: MSH_Contextual_Meta_Generator::$region" warnings
5. **Stock images should process cleanly**: IDs 755, 756, 757, 758, 760, 761, etc.

## Files Modified

- ✅ `/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/class-msh-image-optimizer.php`
- ✅ `/Users/anastasiavolkova/msh-image-optimizer-standalone/includes/class-msh-image-optimizer.php`

## Context Data Verified

Business context **does have region data** (confirmed via wp-cli):
```json
{
  "business_name": "Main Street Health",
  "city": "Hamilton",
  "region": "Ontario",  // ← Present in context
  "country": "Canada",
  ...
}
```

So the fix correctly exposes this data to the metadata generator.

---

**Fix Applied**: November 4, 2025
**Status**: ✅ Complete - Ready for user testing
**Impact**: Non-AI (manual mode) metadata generation will no longer trigger property warnings
