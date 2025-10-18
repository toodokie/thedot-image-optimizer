# Task Brief: Phase 4 Comprehensive Testing

**Assigned to:** Other AI
**Priority:** High
**Estimated Effort:** 3-4 hours
**Status:** Ready to start
**Conflict Risk:** ZERO (separate test files, no overlap with Phase 2)

---

## Context

Phase 4 (Metadata Versioning & Manual Edit Protection) was implemented and deployed to production, but lacks comprehensive automated tests. This task adds a full test suite to validate the critical functionality.

---

## Objective

Write comprehensive automated tests for Phase 4 components:
- `MSH_Metadata_Versioning` class
- `MSH_Manual_Edit_Protection` class

---

## Files to Test

### Primary Classes (DO NOT MODIFY - only test them):
1. `msh-image-optimizer/includes/class-msh-metadata-versioning.php`
2. `msh-image-optimizer/includes/class-msh-manual-edit-protection.php`

### Test File to Create:
- `tests/test-phase4-metadata-versioning.php` (NEW FILE)

---

## Requirements

### 1. MSH_Metadata_Versioning Tests (15+ tests required)

#### Core Functionality Tests:
```php
// Test 1: Save version creates record
test_save_version_creates_record()
// Create media, save version, verify DB record exists

// Test 2: Version numbers increment correctly
test_version_numbers_increment()
// Save v1, v2, v3 - verify versions are 1, 2, 3

// Test 3: Get latest version returns highest
test_get_active_version_returns_latest()
// Save v1, v2, v3 - verify get_active_version() returns 3

// Test 4: Get version history ordered correctly
test_get_version_history_ordered()
// Save v1, v2, v3 - verify history returns [3, 2, 1]

// Test 5: Checksum prevents duplicates
test_checksum_prevents_duplicates()
// Save same value twice - verify only one record created

// Test 6: Source tracking works
test_source_tracking()
// Save with source='ai', verify saved correctly
// Save with source='manual', verify saved correctly
```

#### Multi-locale Tests:
```php
// Test 7: Separate versions per locale
test_separate_versions_per_locale()
// Save title 'en' v1, 'es' v1 - verify independent versions

// Test 8: Get version for specific locale
test_get_version_for_locale()
// Save 'en' v1, 'es' v1 - verify get_version(123, 'en', 'title') returns 'en' data
```

#### Multi-field Tests:
```php
// Test 9: Independent versioning per field
test_independent_field_versioning()
// Save title v1, caption v1, alt_text v1 - verify independent version numbers

// Test 10: Get all fields for media+locale
test_get_all_fields()
// Save title, caption, alt_text - verify get_all_versions() returns all
```

#### Edge Cases:
```php
// Test 11: Handle empty/null values
test_handle_empty_values()
// Save empty string, null - verify handled gracefully

// Test 12: Handle very long values
test_handle_long_values()
// Save 10,000 character string - verify saved/retrieved correctly

// Test 13: Concurrent version saves
test_concurrent_saves()
// Simulate two saves at same time - verify version numbers don't conflict

// Test 14: Invalid media ID
test_invalid_media_id()
// Try to save version for non-existent media - verify error handling

// Test 15: Version number validation
test_version_number_validation()
// Verify version numbers are always positive integers
```

---

### 2. MSH_Manual_Edit_Protection Tests (10+ tests required)

#### Manual Edit Detection:
```php
// Test 1: Detect title manual edit
test_detect_title_edit()
// Create attachment, edit title via wp_update_post(), verify detected

// Test 2: Detect alt text manual edit
test_detect_alt_text_edit()
// Create attachment, edit alt via update_post_meta(), verify detected

// Test 3: Detect caption manual edit
test_detect_caption_edit()
// Edit caption via wp_update_post(), verify detected

// Test 4: Detect description manual edit
test_detect_description_edit()
// Edit description via wp_update_post(), verify detected
```

#### AI Write Permission:
```php
// Test 5: can_ai_write returns false after manual edit
test_can_ai_write_blocked_after_manual_edit()
// Edit title manually, verify can_ai_write(id, 'title') returns false

// Test 6: can_ai_write returns true before manual edit
test_can_ai_write_allowed_before_edit()
// Fresh attachment, verify can_ai_write() returns true

// Test 7: force_replace bypasses protection
test_force_replace_bypasses_protection()
// Edit manually, verify can_ai_write(id, 'title', 'en', true) returns true
```

#### Multi-locale Protection:
```php
// Test 8: Manual edit in one locale doesn't block others
test_locale_specific_protection()
// Edit 'en' title manually, verify can_ai_write(id, 'title', 'es') still returns true

// Test 9: Each locale tracked independently
test_independent_locale_tracking()
// Edit 'en' and 'es' separately, verify both tracked
```

#### Edge Cases:
```php
// Test 10: Programmatic AI edits don't trigger protection
test_ai_edits_not_protected()
// Save version with source='ai', verify NOT marked as manual edit

// Test 11: Clear manual edit flag
test_clear_manual_edit_flag()
// Edit manually, clear flag, verify can_ai_write() returns true again

// Test 12: has_manual_edit returns correct status
test_has_manual_edit_status()
// Edit manually, verify has_manual_edit() returns true
// Fresh attachment, verify has_manual_edit() returns false
```

---

## Test Setup Requirements

### Database Setup:
```php
// Before each test
function setUp() {
    parent::setUp();

    // Create test attachment
    $this->attachment_id = $this->create_test_attachment();

    // Ensure Phase 4 tables exist
    MSH_Metadata_Versioning::maybe_create_table();
    MSH_Manual_Edit_Protection::maybe_create_table();
}

// After each test
function tearDown() {
    // Clean up test data
    wp_delete_attachment( $this->attachment_id, true );

    parent::tearDown();
}
```

### Helper Methods:
```php
// Create test attachment with image
private function create_test_attachment() {
    $filename = '/tmp/test-image.jpg';
    // Create dummy image file
    // Upload to WordPress
    // Return attachment ID
}

// Simulate manual edit (bypass protection detection)
private function edit_manually( $attachment_id, $field, $value ) {
    // Edit field directly without triggering hooks
}
```

---

## Test Execution

### Run Tests:
```bash
# Run Phase 4 tests only
vendor/bin/phpunit tests/test-phase4-metadata-versioning.php

# Run all tests
vendor/bin/phpunit
```

### Expected Output:
```
MSH_Metadata_Versioning Tests
✓ test_save_version_creates_record
✓ test_version_numbers_increment
✓ test_get_active_version_returns_latest
... (15 total)

MSH_Manual_Edit_Protection Tests
✓ test_detect_title_edit
✓ test_detect_alt_text_edit
... (10 total)

OK (25 tests, 60 assertions)
```

---

## Deliverables

1. ✅ **Test file:** `tests/test-phase4-metadata-versioning.php`
2. ✅ **25+ passing tests** (15 versioning + 10 protection)
3. ✅ **All edge cases covered** (empty values, concurrent writes, multi-locale)
4. ✅ **Test documentation** (comments explaining what each test validates)
5. ✅ **Bug report** (if any issues found during testing)

---

## Success Criteria

- [ ] All 25+ tests written and passing
- [ ] Code coverage >80% for both classes
- [ ] No false positives (tests pass when they should)
- [ ] No false negatives (tests fail when they should)
- [ ] Tests run in <5 seconds total
- [ ] Tests can run in isolation (no dependencies between tests)

---

## Notes

### What NOT to Do:
- ❌ Don't modify the Phase 4 classes themselves (only test them)
- ❌ Don't modify any files in `msh-image-optimizer/includes/` or `msh-image-optimizer/admin/`
- ❌ Don't work on PHPCS cosmetic fixes (deferred)
- ❌ Don't start Phase 2 implementation (assigned to other AI)

### What to Do if You Find Bugs:
1. Document the bug in test comments
2. Write a test that SHOULD pass but currently fails
3. Mark test as `@expectedFailure` or skip it
4. Report bug in deliverables
5. Don't try to fix the bug yourself (just document it)

---

## Questions?

If you encounter any issues:
1. Check if test database is properly initialized
2. Verify Phase 4 tables exist (run `maybe_create_table()`)
3. Check WordPress test environment setup
4. Ask for clarification if requirements unclear

---

## Reference Files

### Existing Test Examples:
- `test-versioning.php` (simple 7-scenario test created during Phase 4)
- Use this as a starting point for structure

### Classes to Reference:
- `msh-image-optimizer/includes/class-msh-metadata-versioning.php` (read methods, understand API)
- `msh-image-optimizer/includes/class-msh-manual-edit-protection.php` (read hooks, understand detection logic)

---

## Timeline

**Estimated Effort:** 3-4 hours
- 1.5 hours: MSH_Metadata_Versioning tests (15 tests)
- 1 hour: MSH_Manual_Edit_Protection tests (10 tests)
- 0.5 hours: Edge cases and cleanup
- 0.5 hours: Documentation and bug reporting

**Deadline:** No hard deadline - work at your own pace, prioritize quality over speed

---

## Commit Message Template

When complete, commit with:

```
test: add comprehensive Phase 4 test suite

- Add 15+ tests for MSH_Metadata_Versioning
- Add 10+ tests for MSH_Manual_Edit_Protection
- Test multi-locale support, concurrent writes, edge cases
- Validate version incrementing, checksum deduplication
- Verify manual edit detection across all metadata fields
- All tests passing, >80% code coverage

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

---

**Ready to start?** Let me know if you have any questions!
