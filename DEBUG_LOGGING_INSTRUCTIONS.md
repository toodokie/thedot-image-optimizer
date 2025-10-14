# Debug Logging Instructions

## Purpose

The debug logger tracks all file resolver activity, analyzer operations, rename operations, and verification processes in real-time during frontend testing. Each session creates a separate log file for easy review.

---

## How to Enable Debug Logging

### Method 1: Enable WP_DEBUG (Recommended)

Edit `wp-config.php`:

```php
define('WP_DEBUG', true);
```

This will automatically enable the debug logger.

### Method 2: Use MSH_DEBUG_LOGGING Constant

If you don't want WP_DEBUG enabled (which may show other warnings), add this to `wp-config.php`:

```php
define('MSH_DEBUG_LOGGING', true);
```

---

## What Gets Logged

### File Resolver Activity
- **Direct matches**: When database path matches filesystem
- **Mismatch resolutions**: When fallback pattern matching finds the file
- **Failed resolutions**: When file cannot be found

**Example Log Entry:**
```
[14:23:45.123] [FILE_RESOLVER] MISMATCH RESOLVED: Attachment 611 - Expected "workspace-facility...jpg" → Found "workspace-facility-611.jpg"
  attachment_id: 611
  expected_path: 2008/06/workspace-facility-austin-equipment-austin-texas-611-equipment-austin-texas-611-equipment-austin-texas-611.jpg
  found_path: 2008/06/workspace-facility-austin-equipment-austin-texas-611.jpg
  method: fallback
  mismatch: YES
```

### Analyzer Operations
- Which attachments are being analyzed
- Success/failure status
- File paths and dimensions
- Context analysis results

### Rename Operations
- Old filename → New filename
- Success/failure status
- Verification results
- Rollback triggers

### Verification Process
- Operation IDs
- What's being verified
- Pass/fail results
- Why verification failed (if applicable)

---

## Log File Locations

### Where Logs Are Stored:
```
wp-content/uploads/msh-debug-logs/
```

### Log File Naming:
```
msh-debug-YYYY-MM-DD-SESSION.log
```

**Example:**
```
msh-debug-2025-10-14-a7b3c4d2.log
```

Each browser session/page load creates a unique session ID so you can track specific test runs.

---

## How to View Logs

### Option 1: Via File Manager / FTP

1. Navigate to `wp-content/uploads/msh-debug-logs/`
2. Download the log file for your test session
3. Open in text editor

### Option 2: Via WP-CLI

```bash
# List today's log files
ls -lh wp-content/uploads/msh-debug-logs/msh-debug-$(date +%Y-%m-%d)-*.log

# View latest log file
tail -f wp-content/uploads/msh-debug-logs/msh-debug-$(date +%Y-%m-%d)-*.log | head -100

# View specific log
cat wp-content/uploads/msh-debug-logs/msh-debug-2025-10-14-a7b3c4d2.log
```

### Option 3: Via PHP (programmatic access)

```php
// Get today's logs
$logs = MSH_Debug_Logger::get_todays_logs();

foreach ($logs as $log) {
    echo $log['name'] . ' - ' . $log['size'] . ' bytes<br>';
    echo '<a href="' . $log['url'] . '">Download</a><br><br>';
}

// Get current session log
$logger = MSH_Debug_Logger::get_instance();
echo 'Log file: ' . $logger->get_log_file();
echo 'Log size: ' . $logger->get_log_size();

// Get recent entries (last 50 lines)
$recent = $logger->get_recent_entries(50);
foreach ($recent as $line) {
    echo $line . '<br>';
}
```

---

## Testing Workflow

### Step 1: Enable Logging

```php
// In wp-config.php
define('WP_DEBUG', true);
// or
define('MSH_DEBUG_LOGGING', true);
```

### Step 2: Run Your Tests

1. Go to WordPress admin: `Media → Image Optimizer`
2. Click **"Run Analyzer"**
3. Perform operations you want to track:
   - Optimize images
   - Run rename operations
   - Check duplicate detection
4. All activity is logged in real-time

### Step 3: Review Logs

```bash
# Find your session log
ls -lht wp-content/uploads/msh-debug-logs/ | head -5

# View the latest log
cat wp-content/uploads/msh-debug-logs/msh-debug-2025-10-14-a7b3c4d2.log
```

### Step 4: Look for Specific Patterns

```bash
# Find all mismatch resolutions
grep "MISMATCH RESOLVED" wp-content/uploads/msh-debug-logs/msh-debug-*.log

# Find errors
grep "❌" wp-content/uploads/msh-debug-logs/msh-debug-*.log

# Find warnings
grep "⚠️" wp-content/uploads/msh-debug-logs/msh-debug-*.log

# Find specific attachment
grep "Attachment 611" wp-content/uploads/msh-debug-logs/msh-debug-*.log
```

---

## Log File Structure

### Session Header:
```
==============================================
MSH DEBUG SESSION START
Session ID: a7b3c4d2
Date: 2025-10-14 14:23:45
User: admin (ID: 1)
WP_DEBUG: true
==============================================
```

### Log Entries:
```
[HH:MM:SS.mmm] [CONTEXT] Message
  key: value
  nested_key: nested_value
```

**Contexts:**
- `FILE_RESOLVER` - File resolution operations
- `ANALYZER` - Image analysis operations
- `RENAME` - File rename operations
- `VERIFICATION` - Verification system
- `ERROR` - Errors (prefixed with ❌)
- `WARNING` - Warnings (prefixed with ⚠️)
- `SUCCESS` - Successful operations (prefixed with ✅)
- `GENERAL` - General information

---

## Cleanup

### Automatic Cleanup

Old log files (>7 days) are automatically cleaned up. You can manually trigger cleanup:

```php
$deleted = MSH_Debug_Logger::cleanup_old_logs();
echo "Deleted $deleted old log files";
```

### Manual Cleanup

```bash
# Delete logs older than 7 days
find wp-content/uploads/msh-debug-logs/ -name "msh-debug-*.log" -mtime +7 -delete

# Delete all logs (careful!)
rm wp-content/uploads/msh-debug-logs/msh-debug-*.log
```

---

## Troubleshooting

### Logs Not Being Created?

**Check 1:** Is logging enabled?
```php
$logger = MSH_Debug_Logger::get_instance();
if ($logger->is_enabled()) {
    echo "Logging is enabled";
} else {
    echo "Logging is disabled";
}
```

**Check 2:** Directory permissions
```bash
# Check if log directory exists and is writable
ls -la wp-content/uploads/ | grep msh-debug-logs

# If needed, fix permissions
chmod 755 wp-content/uploads/msh-debug-logs/
```

**Check 3:** Is WP_DEBUG or MSH_DEBUG_LOGGING defined?
```php
echo 'WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false') . '<br>';
echo 'MSH_DEBUG_LOGGING: ' . (defined('MSH_DEBUG_LOGGING') && MSH_DEBUG_LOGGING ? 'true' : 'false');
```

### Logs Too Large?

If logs grow too large (>10MB), consider:

1. **Clear current log:**
```php
MSH_Debug_Logger::get_instance()->clear_log();
```

2. **Reduce logging scope** - Only enable during specific test runs

3. **Filter log entries** - Use grep to extract only relevant lines:
```bash
grep "FILE_RESOLVER" msh-debug-2025-10-14-a7b3c4d2.log > resolver-only.log
```

---

## Example Testing Session

### 1. Enable Logging
```php
// wp-config.php
define('MSH_DEBUG_LOGGING', true);
```

### 2. Run Test
- Go to WP Admin → Media → Image Optimizer
- Click "Run Analyzer"
- Wait for completion

### 3. Check Log
```bash
cd wp-content/uploads/msh-debug-logs
ls -lht | head -1
# Shows: msh-debug-2025-10-14-a7b3c4d2.log

cat msh-debug-2025-10-14-a7b3c4d2.log
```

### 4. Review Results
Look for:
- Number of file resolver mismatches found
- Which attachments needed fallback resolution
- Any errors or warnings
- Verification results

### 5. Sample Output
```
==============================================
MSH DEBUG SESSION START
Session ID: a7b3c4d2
Date: 2025-10-14 14:23:45
User: admin (ID: 1)
WP_DEBUG: true
==============================================

[14:23:46.001] [FILE_RESOLVER] Direct match: Attachment 1692 - spectacles.gif
[14:23:46.045] [FILE_RESOLVER] Direct match: Attachment 1691 - dsc20050315_145007_132.jpg
[14:23:46.089] [FILE_RESOLVER] Direct match: Attachment 1690 - 2014-slider-mobile-behavior.mov
[14:23:46.123] [FILE_RESOLVER] MISMATCH RESOLVED: Attachment 611 - Expected "workspace-facility...jpg" → Found "workspace-facility-611.jpg"
  attachment_id: 611
  expected_path: 2008/06/workspace-facility-austin-equipment-austin-texas-611-equipment-austin-texas-611-equipment-austin-texas-611.jpg
  found_path: 2008/06/workspace-facility-austin-equipment-austin-texas-611.jpg
  method: fallback
  mismatch: YES

[14:23:46.234] [ANALYZER] Analyzed attachment 611 - Status: needs_attention
  current_size_mb: 0.21
  webp_exists: false
  context: blog_featured

[14:23:46.345] ✅ [SUCCESS] Analysis complete: 37 attachments processed
```

---

## Performance Impact

**Minimal overhead:**
- Logging only activates when WP_DEBUG or MSH_DEBUG_LOGGING is true
- File writes are buffered and locked for thread safety
- Each log entry adds ~0.5ms to operation time
- Log files typically < 1MB per analysis run

**Disable in production** unless actively troubleshooting.

---

## Quick Command Reference

```bash
# Enable logging
echo "define('MSH_DEBUG_LOGGING', true);" >> wp-config.php

# View latest log
tail -100 wp-content/uploads/msh-debug-logs/msh-debug-$(date +%Y-%m-%d)-*.log

# Search for mismatches
grep -r "MISMATCH" wp-content/uploads/msh-debug-logs/

# Count resolver calls
grep -c "FILE_RESOLVER" wp-content/uploads/msh-debug-logs/msh-debug-*.log

# Find errors
grep -r "❌" wp-content/uploads/msh-debug-logs/

# Clean up old logs
find wp-content/uploads/msh-debug-logs/ -name "*.log" -mtime +7 -delete

# Disable logging
# Remove or comment out the define in wp-config.php
```

---

## Support

If you find issues or need additional logging features:

1. Check `DB_INVESTIGATION_FINDINGS.md` for technical details
2. Review `INVESTIGATION_SUMMARY_FOR_USER.md` for context
3. Include relevant log excerpts when reporting issues

**Log files contain:**
- Timestamps (precise to millisecond)
- Context tags (FILE_RESOLVER, ANALYZER, etc.)
- Structured data (indented key-value pairs)
- Visual indicators (❌ ⚠️ ✅)

This makes troubleshooting significantly easier than parsing WordPress debug.log.
