# Metadata Versioning API Documentation

**Version:** 2.0.0
**Status:** ✅ Production Ready
**Last Updated:** October 18, 2025

---

## Overview

The Metadata Versioning system provides complete version control for image metadata across multiple locales. It tracks every change, protects manual edits from AI overwrite, and enables rollback to any previous version.

### Key Features

- ✅ **Multi-locale Support:** Track metadata per language (en, es, fr-CA, etc.)
- ✅ **Version History:** Complete audit trail of all changes
- ✅ **Manual Edit Protection:** User edits always win over AI unless explicitly replaced
- ✅ **Source Tracking:** Know if metadata came from AI, template, manual edit, or import
- ✅ **Diff Utility:** Compare any two versions or AI vs manual
- ✅ **Duplicate Prevention:** Identical values don't create new versions
- ✅ **Checksum Validation:** SHA-256 checksums for integrity

---

## Database Schema

### Table: `wp_msh_optimizer_metadata`

```sql
CREATE TABLE wp_msh_optimizer_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(10) NOT NULL DEFAULT 'en',
    field VARCHAR(20) NOT NULL,
    value TEXT NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'ai',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    checksum CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,

    UNIQUE KEY unique_version (media_id, locale, field, version),
    KEY media_locale (media_id, locale),
    KEY media_field (media_id, field),
    KEY source_idx (source),
    KEY created_idx (created_at)
);
```

### Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | BIGINT | Auto-increment primary key |
| `media_id` | BIGINT | WordPress attachment ID |
| `locale` | VARCHAR(10) | Locale code (en, es, fr-CA, etc.) |
| `field` | VARCHAR(20) | Metadata field (title, alt, caption, description, filename) |
| `value` | TEXT | Metadata value |
| `source` | VARCHAR(20) | Source type (ai, template, manual, import) |
| `version` | INT | Version number (auto-increments per media/locale/field) |
| `checksum` | CHAR(64) | SHA-256 hash of value |
| `created_at` | DATETIME | When this version was created |

---

## Core API

### MSH_Metadata_Versioning Class

#### `get_instance()`

Get singleton instance of the versioning system.

```php
$versioning = MSH_Metadata_Versioning::get_instance();
```

---

### Version Management

#### `save_version($media_id, $locale, $field, $value, $source)`

Save a new metadata version.

**Parameters:**
- `$media_id` (int) - Attachment ID
- `$locale` (string) - Locale code (e.g., 'en', 'es', 'fr-CA')
- `$field` (string) - One of: `title`, `alt`, `caption`, `description`, `filename`
- `$value` (string) - Metadata value
- `$source` (string) - One of: `ai`, `template`, `manual`, `import`

**Returns:** `int|false` - Version ID on success, false on failure

**Example:**
```php
$versioning = MSH_Metadata_Versioning::get_instance();

$version_id = $versioning->save_version(
    123,                    // media_id
    'es',                   // locale
    'title',                // field
    'Título en Español',    // value
    'ai'                    // source
);

if ($version_id) {
    echo "Saved version: $version_id";
}
```

---

#### `get_active_version($media_id, $locale, $field)`

Get the current active (latest) version for a field.

**Returns:** `array|null` - Version record or null if not found

**Example:**
```php
$active = $versioning->get_active_version(123, 'en', 'title');

if ($active) {
    echo "Current title: " . $active['value'];
    echo "Source: " . $active['source'];
    echo "Version: " . $active['version'];
}
```

---

#### `get_version_history($media_id, $locale, $field)`

Get complete version history for a field (newest first).

**Returns:** `array` - Array of version records

**Example:**
```php
$history = $versioning->get_version_history(123, 'en', 'title');

foreach ($history as $version) {
    echo "Version {$version['version']}: {$version['value']} ({$version['source']})\n";
}
```

---

#### `get_version($media_id, $locale, $field, $version)`

Get a specific version by number.

**Returns:** `array|null` - Version record or null if not found

**Example:**
```php
// Get version 3 of the title
$v3 = $versioning->get_version(123, 'en', 'title', 3);

if ($v3) {
    echo "Version 3: " . $v3['value'];
}
```

---

### Comparison & Diff

#### `compare_versions($media_id, $locale, $field, $version_a, $version_b)`

Compare two versions and see what changed.

**Returns:** `array|null` - Diff object with both versions and comparison

**Example:**
```php
$diff = $versioning->compare_versions(123, 'en', 'title', 1, 3);

if ($diff) {
    echo "Version 1: " . $diff['version_a']['value'] . "\n";
    echo "Version 3: " . $diff['version_b']['value'] . "\n";
    echo "Values match: " . ($diff['values_match'] ? 'Yes' : 'No') . "\n";
    echo "Source changed: " . ($diff['source_changed'] ? 'Yes' : 'No') . "\n";
}
```

---

#### `get_ai_vs_manual_diff($media_id, $locale)`

Get diff showing AI-generated vs manually-edited metadata for all fields.

**Returns:** `array` - Diff data per field

**Example:**
```php
$diffs = $versioning->get_ai_vs_manual_diff(123, 'en');

foreach ($diffs as $field => $diff) {
    echo "$field:\n";
    echo "  Active: " . $diff['active']['value'] . "\n";
    echo "  Has manual edit: " . ($diff['has_manual'] ? 'Yes' : 'No') . "\n";
    echo "  Manual is active: " . ($diff['manual_is_active'] ? 'Yes' : 'No') . "\n";
}
```

---

### Utility Methods

#### `value_exists($media_id, $locale, $field, $value)`

Check if a value already exists to prevent duplicates.

**Returns:** `bool` - True if value exists in latest version

**Example:**
```php
if (!$versioning->value_exists(123, 'en', 'title', 'New Title')) {
    $versioning->save_version(123, 'en', 'title', 'New Title', 'ai');
}
```

---

## Manual Edit Protection

### MSH_Manual_Edit_Protection Class

Automatically detects and protects manual edits from being overwritten by AI.

#### `has_manual_edit($media_id, $field, $locale)`

Check if a field has been manually edited.

**Example:**
```php
$protection = MSH_Manual_Edit_Protection::get_instance();

if ($protection->has_manual_edit(123, 'title', 'en')) {
    echo "Title was manually edited - don't overwrite!";
}
```

---

#### `can_ai_write($media_id, $field, $locale, $force_replace)`

Check if AI is allowed to write to this field.

**Parameters:**
- `$force_replace` (bool) - Set to true to allow AI to overwrite manual edits

**Example:**
```php
$protection = MSH_Manual_Edit_Protection::get_instance();

// Check without force
if ($protection->can_ai_write(123, 'title', 'en')) {
    // Safe to write AI metadata
    $versioning->save_version(123, 'en', 'title', 'AI Title', 'ai');
}

// Force replace manual edit (user explicitly requested)
if ($protection->can_ai_write(123, 'title', 'en', true)) {
    $versioning->save_version(123, 'en', 'title', 'New AI Title', 'ai');
}
```

---

## Automatic Tracking

Manual edits are tracked automatically via WordPress hooks:

### Tracked Events

1. **Title changes** - `wp_update_post()` with `post_title`
2. **ALT text changes** - `update_post_meta()` with `_wp_attachment_image_alt`
3. **Description changes** - `wp_update_post()` with `post_content`
4. **Caption changes** - Image metadata updates

### Bulk Operations

Manual edit protection is bypassed during bulk operations to prevent false positives:

```php
// During AI regeneration
define('MSH_AI_REGENERATION_RUNNING', true);

// During bulk optimize
define('MSH_BULK_OPTIMIZE_RUNNING', true);
```

---

## Usage Examples

### Example 1: Save AI-Generated Metadata

```php
$versioning = MSH_Metadata_Versioning::get_instance();
$protection = MSH_Manual_Edit_Protection::get_instance();

$media_id = 123;
$locale = 'es';

// Check if we can write
if ($protection->can_ai_write($media_id, 'title', $locale)) {
    $versioning->save_version(
        $media_id,
        $locale,
        'title',
        'Título generado por IA',
        'ai'
    );

    $versioning->save_version(
        $media_id,
        $locale,
        'alt',
        'Texto ALT generado por IA',
        'ai'
    );
}
```

---

### Example 2: Get Version History

```php
$media_id = 123;
$locale = 'en';

$history = $versioning->get_version_history($media_id, $locale, 'title');

echo "<h3>Version History</h3>";
echo "<table>";
echo "<tr><th>Version</th><th>Value</th><th>Source</th><th>Created</th></tr>";

foreach ($history as $version) {
    echo "<tr>";
    echo "<td>{$version['version']}</td>";
    echo "<td>{$version['value']}</td>";
    echo "<td>{$version['source']}</td>";
    echo "<td>{$version['created_at']}</td>";
    echo "</tr>";
}

echo "</table>";
```

---

### Example 3: Compare AI vs Manual Edits

```php
$media_id = 123;
$locale = 'en';

$diffs = $versioning->get_ai_vs_manual_diff($media_id, $locale);

foreach ($diffs as $field => $diff) {
    if ($diff['has_manual']) {
        echo "<strong>{$field}:</strong> User manually edited this field<br>";
        echo "AI suggestion: {$diff['ai']['value']}<br>";
        echo "User's version: {$diff['manual']['value']}<br>";
        echo "Currently active: {$diff['active']['value']}<br>";
        echo "<hr>";
    }
}
```

---

### Example 4: Rollback to Previous Version

```php
// Get version 2
$v2 = $versioning->get_version(123, 'en', 'title', 2);

if ($v2) {
    // Save it again as a new version (rollback)
    $versioning->save_version(
        123,
        'en',
        'title',
        $v2['value'],
        'manual' // Mark as manual since user initiated rollback
    );

    echo "Rolled back to version 2";
}
```

---

## Best Practices

### 1. Always Check Before Writing

```php
// ✅ GOOD
if ($protection->can_ai_write($media_id, $field, $locale)) {
    $versioning->save_version(...);
}

// ❌ BAD
$versioning->save_version(...); // Might overwrite manual edits!
```

### 2. Use Appropriate Source Types

```php
// AI-generated
$versioning->save_version($id, $locale, 'title', $value, 'ai');

// Template-based
$versioning->save_version($id, $locale, 'title', $value, 'template');

// User manual edit
$versioning->save_version($id, $locale, 'title', $value, 'manual');

// Imported from external source
$versioning->save_version($id, $locale, 'title', $value, 'import');
```

### 3. Check for Duplicates

```php
// ✅ GOOD - Prevent duplicate versions
if (!$versioning->value_exists($media_id, $locale, $field, $value)) {
    $versioning->save_version($media_id, $locale, $field, $value, 'ai');
}
```

### 4. Handle Bulk Operations

```php
// Define flag before bulk operations
define('MSH_AI_REGENERATION_RUNNING', true);

// Process many images...
foreach ($images as $image) {
    $versioning->save_version(...);
}
```

---

## Database Queries

### Get Latest Version for All Fields

```sql
SELECT
    field,
    value,
    source,
    version,
    created_at
FROM wp_msh_optimizer_metadata m1
WHERE media_id = 123
  AND locale = 'en'
  AND version = (
      SELECT MAX(version)
      FROM wp_msh_optimizer_metadata m2
      WHERE m2.media_id = m1.media_id
        AND m2.locale = m1.locale
        AND m2.field = m1.field
  );
```

### Get All Manual Edits

```sql
SELECT
    media_id,
    locale,
    field,
    value,
    version,
    created_at
FROM wp_msh_optimizer_metadata
WHERE source = 'manual'
ORDER BY created_at DESC;
```

### Count Versions per Image

```sql
SELECT
    media_id,
    locale,
    field,
    COUNT(*) as version_count
FROM wp_msh_optimizer_metadata
GROUP BY media_id, locale, field
HAVING version_count > 1
ORDER BY version_count DESC;
```

---

## Migration & Cleanup

### Seed Initial Versions from Existing Metadata

```php
// Get all attachments
$attachments = get_posts(array(
    'post_type' => 'attachment',
    'posts_per_page' => -1,
));

$versioning = MSH_Metadata_Versioning::get_instance();

foreach ($attachments as $attachment) {
    $media_id = $attachment->ID;
    $locale = 'en'; // Default locale

    // Import existing title
    if (!empty($attachment->post_title)) {
        $versioning->save_version($media_id, $locale, 'title', $attachment->post_title, 'import');
    }

    // Import existing ALT
    $alt = get_post_meta($media_id, '_wp_attachment_image_alt', true);
    if (!empty($alt)) {
        $versioning->save_version($media_id, $locale, 'alt', $alt, 'import');
    }

    // Import existing description
    if (!empty($attachment->post_content)) {
        $versioning->save_version($media_id, $locale, 'description', $attachment->post_content, 'import');
    }
}
```

---

## Troubleshooting

### Table Not Created

```php
// Force table creation
$versioning = MSH_Metadata_Versioning::get_instance();
$versioning->maybe_create_table();
```

### Versions Not Saving

Check error log for details:
```bash
tail -f /path/to/wp-content/debug.log | grep "MSH Versioning"
```

### Manual Edits Not Detected

Ensure hooks are not blocked:
```php
// Check if bulk operation flag is set
var_dump(defined('MSH_AI_REGENERATION_RUNNING'));
var_dump(defined('MSH_BULK_OPTIMIZE_RUNNING'));
```

---

## Performance Considerations

- **Indexes:** All critical fields are indexed for fast queries
- **Checksums:** SHA-256 hashing is efficient for duplicate detection
- **Unique Constraints:** Prevent database bloat from failed duplicate insertions
- **Cleanup:** Consider archiving versions older than 90 days for active sites

---

_Last updated: October 18, 2025_
_Version: 2.0.0_
_Status: Production Ready_
