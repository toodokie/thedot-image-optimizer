# Task: Replace Filesystem Functions with WordPress Alternatives

**Priority:** 🟡 HIGH - Blocks WordPress.org approval (security)
**Estimated time:** 45-60 minutes
**Violations:** 17 instances (11× unlink, 6× rename)

## Context

WordPress.org requires using WordPress filesystem functions or adding strict path validation for direct PHP filesystem operations. This ensures files are only manipulated within the uploads directory.

**Plugin Check violations being fixed:**
- `WordPress.WP.AlternativeFunctions.unlink_unlink` (11 instances)
- `WordPress.WP.AlternativeFunctions.rename_rename` (6 instances)
- `WordPress.WP.AlternativeFunctions.file_system_operations_is_writable` (if present)
- `WordPress.WP.AlternativeFunctions.file_system_operations_chmod` (if present)

## Pattern from Expert Triage

### Option 1: Use WordPress Helper (Recommended for unlink)

```php
// ❌ WRONG
unlink( $file_path );

// ✅ CORRECT
wp_delete_file( $file_path );
```

### Option 2: Use WP_Filesystem (Recommended for rename/move)

```php
// ❌ WRONG
rename( $old_path, $new_path );

// ✅ CORRECT
global $wp_filesystem;
if ( ! $wp_filesystem ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
}
$wp_filesystem->move( $old_path, $new_path, true );
```

### Option 3: Add Path Validation (If keeping native functions)

```php
// ❌ WRONG - No validation
unlink( $path );

// ✅ CORRECT - Validate path is in uploads
$uploads = wp_get_upload_dir();
$root    = wp_normalize_path( $uploads['basedir'] );
$target  = realpath( $path );

if ( $target && str_starts_with( wp_normalize_path( $target ), $root ) ) {
    wp_delete_file( $target );
}
```

## Files Needing Fixes

Primary file with most instances:

**class-msh-safe-rename-system.php** - Most or all 17 instances
- Multiple `unlink()` calls for backup cleanup
- Multiple `rename()` calls for file renaming operations
- Possibly `chmod()`, `is_writable()` checks

Other files (check with grep):
- class-msh-debug-logger.php:377 (possibly file operations)

## Recommended Approach

### For unlink() → wp_delete_file()

WordPress's `wp_delete_file()` handles:
- Path normalization
- Hook for filtering (`wp_delete_file` filter)
- Proper error handling

```php
// Simple replacement
unlink( $backup_file );
// becomes
wp_delete_file( $backup_file );
```

### For rename() → WP_Filesystem()->move()

WordPress's WP_Filesystem provides:
- Abstraction layer for different servers
- Proper permissions handling
- FTP/SSH support if needed

```php
// Before
rename( $old_path, $new_path );

// After
global $wp_filesystem;
if ( ! function_exists( 'WP_Filesystem' ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();

$result = $wp_filesystem->move( $old_path, $new_path, true );
if ( ! $result ) {
    // Handle error
    error_log( 'Failed to rename file: ' . $old_path );
}
```

### For chmod() → WP_Filesystem()->chmod()

```php
// Before
chmod( $file, 0644 );

// After
global $wp_filesystem;
if ( ! $wp_filesystem ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
}
$wp_filesystem->chmod( $file, 0644 );
```

### For is_writable() → wp_is_writable()

```php
// Before
if ( is_writable( $dir ) ) {

// After
if ( wp_is_writable( $dir ) ) {
```

## Step-by-Step Instructions

### Step 1: Find All Filesystem Operations

```bash
cd msh-image-optimizer
grep -n "unlink(" includes/class-msh-safe-rename-system.php
grep -n "rename(" includes/class-msh-safe-rename-system.php
grep -n "chmod(" includes/class-msh-safe-rename-system.php
grep -n "is_writable(" includes/class-msh-safe-rename-system.php
```

### Step 2: Initialize WP_Filesystem (Once per class)

Add a helper method to the class:

```php
/**
 * Initialize WP_Filesystem
 *
 * @return bool True if filesystem is available
 */
private function init_filesystem() {
    global $wp_filesystem;

    if ( ! $wp_filesystem ) {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
    }

    return isset( $wp_filesystem );
}
```

Use it before filesystem operations:
```php
if ( ! $this->init_filesystem() ) {
    return new WP_Error( 'filesystem_error', 'Could not initialize filesystem' );
}
```

### Step 3: Replace Each Operation

#### Replace unlink()

**Pattern 1: Simple deletion**
```php
// Before
if ( file_exists( $backup_file ) ) {
    unlink( $backup_file );
}

// After
if ( file_exists( $backup_file ) ) {
    wp_delete_file( $backup_file );
}
```

**Pattern 2: With error handling**
```php
// Before
if ( file_exists( $file ) ) {
    if ( ! unlink( $file ) ) {
        error_log( 'Failed to delete: ' . $file );
    }
}

// After
if ( file_exists( $file ) ) {
    $result = wp_delete_file( $file );
    if ( ! $result ) {
        error_log( 'Failed to delete: ' . $file );
    }
}
```

#### Replace rename()

**Pattern 1: File renaming**
```php
// Before
$result = rename( $old_path, $new_path );

// After
global $wp_filesystem;
$this->init_filesystem();
$result = $wp_filesystem->move( $old_path, $new_path, true );
```

**Pattern 2: With rollback**
```php
// Before
if ( ! rename( $backup_path, $original_path ) ) {
    error_log( 'Rollback failed' );
    return false;
}

// After
global $wp_filesystem;
if ( ! $this->init_filesystem() ) {
    error_log( 'Filesystem init failed' );
    return false;
}

if ( ! $wp_filesystem->move( $backup_path, $original_path, true ) ) {
    error_log( 'Rollback failed' );
    return false;
}
```

### Step 4: Add Path Validation (Defense in Depth)

Even when using WordPress functions, validate paths are in uploads:

```php
/**
 * Validate path is within uploads directory
 *
 * @param string $path Path to validate
 * @return bool True if path is safe
 */
private function is_safe_path( $path ) {
    $uploads = wp_get_upload_dir();
    $uploads_root = wp_normalize_path( $uploads['basedir'] );
    $normalized = wp_normalize_path( $path );

    // Get real path to resolve symlinks
    $real_path = realpath( $path );
    if ( $real_path ) {
        $normalized = wp_normalize_path( $real_path );
    }

    return str_starts_with( $normalized, $uploads_root );
}
```

Use before operations:
```php
if ( ! $this->is_safe_path( $file_path ) ) {
    return new WP_Error( 'invalid_path', 'File path outside uploads directory' );
}

wp_delete_file( $file_path );
```

## Example: Complete Refactor of a Method

**Before:**
```php
public function delete_backup( $backup_id ) {
    $backup_file = $this->get_backup_path( $backup_id );

    if ( file_exists( $backup_file ) ) {
        if ( ! unlink( $backup_file ) ) {
            error_log( 'Failed to delete backup: ' . $backup_file );
            return false;
        }
    }

    return true;
}
```

**After:**
```php
public function delete_backup( $backup_id ) {
    $backup_file = $this->get_backup_path( $backup_id );

    // Validate path
    if ( ! $this->is_safe_path( $backup_file ) ) {
        error_log( 'Invalid backup path: ' . $backup_file );
        return false;
    }

    if ( file_exists( $backup_file ) ) {
        $result = wp_delete_file( $backup_file );
        if ( ! $result ) {
            error_log( 'Failed to delete backup: ' . $backup_file );
            return false;
        }
    }

    return true;
}
```

**Before:**
```php
public function restore_backup( $media_id ) {
    $backup_path = $this->get_backup_path( $media_id );
    $original_path = $this->get_original_path( $media_id );

    if ( ! file_exists( $backup_path ) ) {
        return false;
    }

    // Restore from backup
    if ( ! rename( $backup_path, $original_path ) ) {
        error_log( 'Failed to restore: ' . $backup_path );
        return false;
    }

    return true;
}
```

**After:**
```php
public function restore_backup( $media_id ) {
    $backup_path = $this->get_backup_path( $media_id );
    $original_path = $this->get_original_path( $media_id );

    // Validate paths
    if ( ! $this->is_safe_path( $backup_path ) || ! $this->is_safe_path( $original_path ) ) {
        error_log( 'Invalid file paths for restore' );
        return false;
    }

    if ( ! file_exists( $backup_path ) ) {
        return false;
    }

    // Initialize filesystem
    if ( ! $this->init_filesystem() ) {
        error_log( 'Filesystem initialization failed' );
        return false;
    }

    global $wp_filesystem;

    // Restore from backup
    if ( ! $wp_filesystem->move( $backup_path, $original_path, true ) ) {
        error_log( 'Failed to restore: ' . $backup_path );
        return false;
    }

    return true;
}
```

## Testing

After replacing filesystem functions:

1. **Test file renaming** - Upload an image, trigger rename
2. **Test backup/restore** - Verify backups are created and can be restored
3. **Test cleanup** - Verify old backups are deleted
4. **Check file permissions** - Make sure files are still accessible
5. **Test on different servers** - If possible, test on shared hosting

## Common Issues

### Issue 1: WP_Filesystem not initialized

**Symptom:** Fatal error - Call to member function move() on null
**Fix:** Call `$this->init_filesystem()` before using `$wp_filesystem`

### Issue 2: Move operation fails silently

**Symptom:** rename() worked but $wp_filesystem->move() returns false
**Fix:** Check file permissions and that parent directory exists:

```php
$dir = dirname( $new_path );
if ( ! $wp_filesystem->is_dir( $dir ) ) {
    $wp_filesystem->mkdir( $dir, 0755, true );
}
```

### Issue 3: Path validation too strict

**Symptom:** Valid files being rejected
**Fix:** Use `wp_normalize_path()` consistently and handle symlinks:

```php
$real_path = realpath( $path );
if ( ! $real_path ) {
    // File doesn't exist yet (e.g., for new file)
    $real_path = $path;
}
```

## Checklist

### class-msh-safe-rename-system.php
- [ ] Add `init_filesystem()` helper method
- [ ] Add `is_safe_path()` validation method
- [ ] Replace all `unlink()` with `wp_delete_file()` (11 instances)
- [ ] Replace all `rename()` with `$wp_filesystem->move()` (6 instances)
- [ ] Replace any `chmod()` with `$wp_filesystem->chmod()`
- [ ] Replace any `is_writable()` with `wp_is_writable()`
- [ ] Add path validation before all operations
- [ ] Test backup creation
- [ ] Test backup restoration
- [ ] Test backup deletion
- [ ] Test file renaming
- [ ] Commit

### class-msh-debug-logger.php (if applicable)
- [ ] Check line 377 for filesystem operations
- [ ] Replace with WordPress equivalents
- [ ] Test logging functionality
- [ ] Commit

## Success Criteria

- [ ] All `unlink()` replaced with `wp_delete_file()`
- [ ] All `rename()` replaced with `WP_Filesystem()->move()`
- [ ] All `chmod()` replaced with `WP_Filesystem()->chmod()`
- [ ] All `is_writable()` replaced with `wp_is_writable()`
- [ ] Path validation added for all operations
- [ ] Plugin functionality still works
- [ ] Ready for Plugin Check re-run

## Reference

WordPress Codex:
- https://developer.wordpress.org/apis/wp-filesystem/
- https://developer.wordpress.org/reference/functions/wp_delete_file/
- https://developer.wordpress.org/reference/functions/wp_is_writable/
