# Bug Fix: AI Features Option Name Mismatch

**Date**: October 16, 2025
**Severity**: CRITICAL - Blocked all AI access
**Status**: ✅ FIXED

---

## Problem

AI access was **always denied** with reason `feature_disabled`, even for paid/BYOK users who enabled the "meta" checkbox in settings.

### Root Cause

Option name mismatch between two components:

| Component | File | Option Name Used |
|-----------|------|-----------------|
| **AI Service** (reads) | `includes/class-msh-ai-service.php:60` | `msh_ai_features` |
| **Settings Page** (writes) | `admin/image-optimizer-settings.php:114, 546` | `msh_ai_enabled_features` |

**Result**: The AI service always read an empty array, causing it to fail the feature check:

```php
// class-msh-ai-service.php:81-85
if (!in_array('meta', $features, true)) {
    $state['reason'] = 'feature_disabled';
    $this->last_state = $state;
    return $state;
}
```

Since `$features` was always empty (reading from wrong option), AI was always denied.

---

## Impact

- **100% of AI access attempts failed** with `feature_disabled`
- Paid plan users couldn't use AI features
- BYOK users couldn't use AI features
- Test stub never triggered
- No AI metadata generation possible

---

## Fix

Standardized on `msh_ai_features` as the canonical option name.

### Files Changed

#### 1. `admin/image-optimizer-settings.php`

**Line 114** - Changed read operation:
```php
// BEFORE
$ai_features = get_option('msh_ai_enabled_features', array());

// AFTER
$ai_features = get_option('msh_ai_features', array());
```

**Line 546** - Changed write operation:
```php
// BEFORE
update_option('msh_ai_enabled_features', $ai_features_sanitized, false);

// AFTER
update_option('msh_ai_features', $ai_features_sanitized, false);
```

#### 2. `test-ai-gate.php` (test page)

**Line 88** - Fixed test page to use correct option:
```php
// Ensured array_values() to prevent index gaps
update_option('msh_ai_features', array_values($features), false);
```

---

## Verification

After fix, the gate logic should work correctly:

1. **Settings UI** saves to `msh_ai_features` ✅
2. **AI Service** reads from `msh_ai_features` ✅
3. **Test page** reads/writes `msh_ai_features` ✅

### Test Scenario

1. Visit settings page: `wp-admin/options-general.php?page=msh-image-optimizer-settings`
2. Set AI Mode to "Assist"
3. Check "Metadata & alt text suggestions"
4. Save settings
5. Check option: `wp option get msh_ai_features` → should return `["meta"]`
6. Set plan tier: `wp msh plan set ai_starter`
7. Check status: `wp msh ai-status` → should show "Access: GRANTED"

---

## Why This Bug Existed

**Likely cause**: Different developers/sessions working on different parts of the codebase:
- AI Service class created with `msh_ai_features` convention
- Settings page created with `msh_ai_enabled_features` convention (more descriptive name)
- No integration test caught the mismatch

**Lesson**: Need automated tests that verify option read/write consistency across components.

---

## Migration Consideration

Sites that used settings UI before this fix might have data in `msh_ai_enabled_features` that needs migration:

```php
// Optional: Add to plugin activation hook
$old_features = get_option('msh_ai_enabled_features', array());
if (!empty($old_features)) {
    $new_features = get_option('msh_ai_features', array());
    if (empty($new_features)) {
        // Migrate old data to new option
        update_option('msh_ai_features', $old_features, false);
    }
    delete_option('msh_ai_enabled_features'); // Clean up old option
}
```

**Decision**: Not critical for current testing phase since no production sites exist yet.

---

## Testing Checklist

After applying fix:

- [ ] Settings UI saves features correctly
- [ ] AI Service reads features correctly
- [ ] Test page shows correct feature status
- [ ] AI access granted when all conditions met
- [ ] AI metadata generated with test stub
- [ ] No `feature_disabled` errors when features are enabled

---

## Credit

**Bug found by**: Other AI (parallel session)
**Bug fixed by**: This session
**Date fixed**: October 16, 2025

---

## Related Files

- [class-msh-ai-service.php](msh-image-optimizer/includes/class-msh-ai-service.php#L60)
- [image-optimizer-settings.php](msh-image-optimizer/admin/image-optimizer-settings.php#L114)
- [test-ai-gate.php](http://sterling-law-firm.local/test-ai-gate.php)
- [AI_GATE_TESTING_GUIDE.md](AI_GATE_TESTING_GUIDE.md)
