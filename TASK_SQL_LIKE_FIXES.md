# Task: Fix Unprepared SQL LIKE Wildcards

**Priority:** 🔴 CRITICAL - Blocks WordPress.org approval
**Estimated time:** 90-120 minutes
**Files affected:** 4 files, 26 queries total

## Context

WordPress.org Plugin Check is flagging 26 SQL queries with unprepared LIKE wildcards. These MUST be fixed for plugin approval.

**Plugin Check violations:**
- `WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery`
- `WordPress.DB.PreparedSQL.NotPrepared`

## What Was Already Fixed

✅ **class-msh-ai-ajax-handlers.php** - All 6 queries fixed in commit 8f347d9
Use this file as your reference implementation!

## Files Needing Fixes (26 queries)

1. **class-msh-media-cleanup.php** - 6 queries (lines ~839, 1008, 1204, 1235, 2264, 2281)
2. **class-msh-image-usage-index.php** - 10 queries (lines ~74, 134, 234, 1032, 1280, 1411, 1847, 1894, 2151, 2167)
3. **class-msh-usage-index-background.php** - 2 queries (lines ~89, 296)
4. **class-msh-image-optimizer.php** - 8 queries (lines ~5344, 5707, 6012, 6026, 7411, 8253, 9189, 9218)

## Fix Pattern (Copy from class-msh-ai-ajax-handlers.php)

### Before (WRONG ❌):
```php
$all_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);
```

### After (CORRECT ✅):
```php
// Add this variable ONCE at the start of each function (after global $wpdb;)
$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

$all_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
        'attachment',
        $image_mime_like
    )
);
```

## Step-by-Step Instructions

For each file:

### Step 1: Add the $image_mime_like variable

At the start of each function that has unprepared LIKE queries, add:

```php
function some_function() {
    global $wpdb;

    // Prepare LIKE pattern for image mime types
    $image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

    // ... rest of function
}
```

**Note:** Only add this ONCE per function, even if there are multiple queries.

### Step 2: Identify the query pattern

Find queries that look like:
```php
$wpdb->get_var( "...LIKE 'image/%'..." )
$wpdb->get_col( "...LIKE 'image/%'..." )
$wpdb->get_results( "...LIKE 'image/%'..." )
```

**Skip queries that already have:**
- `LIKE 'image/%%'` (double %% means already prepared)
- `$wpdb->prepare(` wrapper (already fixed)

### Step 3: Replace the query

1. **Replace the LIKE pattern:**
   - Change `LIKE 'image/%'` → `LIKE %s`
   - Change `post_type = 'attachment'` → `post_type = %s` (if present)
   - Change `meta_key = '_wp_attachment_image_alt'` → `meta_key = %s` (if present)

2. **Wrap with $wpdb->prepare():**
   ```php
   $wpdb->prepare(
       "SELECT ... WHERE post_mime_type LIKE %s",
       $image_mime_like
   )
   ```

3. **Add all placeholder values:**
   ```php
   $wpdb->prepare(
       "SELECT ... WHERE post_type = %s AND post_mime_type LIKE %s",
       'attachment',           // for post_type = %s
       $image_mime_like       // for LIKE %s
   )
   ```

## Examples from Already-Fixed File

### Example 1: Simple COUNT query

**Before:**
```php
$all_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
);
```

**After:**
```php
$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

$all_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_mime_type LIKE %s",
        'attachment',
        $image_mime_like
    )
);
```

### Example 2: Complex query with JOIN and meta_key

**Before:**
```php
$missing_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
    WHERE p.post_type = 'attachment'
    AND p.post_mime_type LIKE 'image/%'
    AND (p.post_title = '' OR p.post_title IS NULL)"
);
```

**After:**
```php
$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

$missing_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
        WHERE p.post_type = %s
        AND p.post_mime_type LIKE %s
        AND (p.post_title = '' OR p.post_title IS NULL)",
        '_wp_attachment_image_alt',
        'attachment',
        $image_mime_like
    )
);
```

### Example 3: Subquery

**Before:**
```php
$published_count = $wpdb->get_var(
    "SELECT COUNT(DISTINCT attachment_id)
    FROM {$wpdb->prefix}msh_image_usage_index
    WHERE attachment_id IN (
        SELECT ID FROM {$wpdb->posts}
        WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'
    )"
);
```

**After:**
```php
$image_mime_like = $wpdb->esc_like( 'image/' ) . '%';

$published_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(DISTINCT attachment_id)
        FROM {$wpdb->prefix}msh_image_usage_index
        WHERE attachment_id IN (
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = %s AND post_mime_type LIKE %s
        )",
        'attachment',
        $image_mime_like
    )
);
```

## Testing After Fixes

1. **Read the fixed file** to verify syntax
2. **Run git diff** to review changes
3. **Copy to WordPress** installation:
   ```bash
   cp msh-image-optimizer/includes/FILE.php "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/"
   ```
4. **Test the plugin** - make sure functionality still works
5. **Commit when done**:
   ```bash
   git add FILE.php
   git commit -m "fix: prepare SQL LIKE wildcards in FILE.php

   - Wrap X queries with \$wpdb->prepare()
   - Use \$wpdb->esc_like('image/') for LIKE patterns
   - Fixes WordPress.org Plugin Check violations"
   ```

## Checklist

### File 1: class-msh-media-cleanup.php (6 queries)
- [ ] Line ~839: get_var() - COUNT query
- [ ] Line ~1008: get_results() - batch query
- [ ] Line ~1204: get_var() - hash count
- [ ] Line ~1235: get_col() - image IDs
- [ ] Line ~2264: get_var() - total images
- [ ] Line ~2281: get_results() - batch images
- [ ] Test functionality
- [ ] Commit

### File 2: class-msh-image-usage-index.php (10 queries)
- [ ] Line ~74: get_var() - COUNT attachments
- [ ] Line ~134: get_var() - COUNT attachments (rebuild)
- [ ] Line ~234: get_var() - COUNT total
- [ ] Line ~1032: get_var() - COUNT entries
- [ ] Line ~1280: get_results() - get attachments
- [ ] Line ~1411: get_results() - get attachments (reindex)
- [ ] Line ~1847: get_var() - total images
- [ ] Line ~1894: get_col() - attachment IDs
- [ ] Line ~2151: get_var() - COUNT (full rebuild)
- [ ] Line ~2167: get_var() - COUNT (batch)
- [ ] Test functionality
- [ ] Commit

### File 3: class-msh-usage-index-background.php (2 queries)
- [ ] Line ~89: get_var() - total count
- [ ] Line ~296: get_var() - batch total
- [ ] Test functionality
- [ ] Commit

### File 4: class-msh-image-optimizer.php (8 queries)
- [ ] Line ~5344: get_col() - attachment IDs
- [ ] Line ~5707: get_results() - attachments batch
- [ ] Line ~6012: get_col() - IDs (smart mode)
- [ ] Line ~6026: get_col() - IDs (simple mode)
- [ ] Line ~7411: get_var() - total images
- [ ] Line ~8253: get_var() - optimized count
- [ ] Line ~9189: get_var() - count for CLI
- [ ] Line ~9218: get_var() - total for CLI
- [ ] Test functionality
- [ ] Commit

## Common Pitfalls to Avoid

❌ **Don't** forget to add `$image_mime_like` variable
❌ **Don't** use `LIKE 'image/%%'` - use `LIKE %s` with the variable
❌ **Don't** put literal table/column names in prepare() placeholders
❌ **Don't** forget to update ALL LIKE 'image/%' instances in the same query

✅ **Do** add the variable once per function
✅ **Do** use %s placeholder for the LIKE pattern
✅ **Do** keep table names like `{$wpdb->posts}` as-is (identifiers can't be placeholders)
✅ **Do** test after each file to catch any syntax errors

## Reference Implementation

See: `/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/includes/class-msh-ai-ajax-handlers.php`

This file has 6 queries that were already fixed correctly. Use it as your template!

## Questions?

- Check the already-fixed class-msh-ai-ajax-handlers.php for patterns
- All queries follow the same pattern - add variable, replace LIKE, wrap prepare
- Test frequently to catch issues early

## Success Criteria

- [ ] All 26 queries wrapped with `$wpdb->prepare()`
- [ ] All use `$image_mime_like` variable
- [ ] Plugin still functions correctly
- [ ] All 4 files committed separately
- [ ] Ready for Plugin Check re-run
