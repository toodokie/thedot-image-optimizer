# Debug Logger - Quick Start Guide

## 🚀 Get Started in 3 Steps

### Step 1: Enable Logging (30 seconds)

Open `wp-config.php` and add this line:

```php
define('MSH_DEBUG_LOGGING', true);
```

**That's it!** Logging is now active.

---

### Step 2: Run Your Tests (2 minutes)

1. Go to **WP Admin → Media → Image Optimizer**
2. Click **"Run Analyzer"**
3. Watch the analyzer process all images
4. All activity is being logged in real-time

---

### Step 3: View the Logs (1 minute)

```bash
# Navigate to log directory
cd wp-content/uploads/msh-debug-logs/

# View the latest log file
ls -lht | head -1
# Shows: msh-debug-2025-10-14-abc123de.log

# Read the log
cat msh-debug-2025-10-14-abc123de.log
```

---

## 📊 What You'll See

### Sample Log Output:

```
==============================================
MSH DEBUG SESSION START
Session ID: abc123de
Date: 2025-10-14 14:23:45
User: admin (ID: 1)
WP_DEBUG: false
==============================================

[14:23:46.001] [FILE_RESOLVER] Direct match: Attachment 1692 - spectacles.gif
  attachment_id: 1692
  expected_path: 2014/01/spectacles.gif
  found_path: 2014/01/spectacles.gif
  method: direct
  mismatch: NO

[14:23:46.123] [FILE_RESOLVER] MISMATCH RESOLVED: Attachment 611 - Expected "workspace-facility-austin-equipment-austin-texas-611-equipment-austin-texas-611-equipment-austin-texas-611.jpg" → Found "workspace-facility-austin-equipment-austin-texas-611.jpg"
  attachment_id: 611
  expected_path: 2008/06/workspace-facility-austin-equipment-austin-texas-611-equipment-austin-texas-611-equipment-austin-texas-611.jpg
  found_path: 2008/06/workspace-facility-austin-equipment-austin-texas-611.jpg
  method: fallback
  mismatch: YES
```

---

## 🔍 Quick Analysis Commands

### Find All Mismatches

```bash
grep "MISMATCH RESOLVED" wp-content/uploads/msh-debug-logs/*.log
```

**Shows:** All attachments where file resolver used fallback to find files

### Count Resolver Operations

```bash
grep -c "FILE_RESOLVER" wp-content/uploads/msh-debug-logs/msh-debug-2025-10-14-*.log
```

**Shows:** How many file resolution attempts were made

### Find Errors

```bash
grep "❌" wp-content/uploads/msh-debug-logs/*.log
```

**Shows:** Any errors that occurred during operations

### Check Specific Attachment

```bash
grep "Attachment 611" wp-content/uploads/msh-debug-logs/*.log
```

**Shows:** All log entries for a specific attachment ID

---

## 🎯 What Gets Logged

| Operation | What You See |
|-----------|--------------|
| **File Resolver** | • Direct path matches<br>• Fallback pattern resolutions<br>• Mismatch details<br>• Method used (direct/fallback) |
| **Analyzer** | • Which attachments analyzed<br>• Success/failure status<br>• File paths and sizes<br>• Context details |
| **Rename** | • Old → New filenames<br>• Verification results<br>• Success/failure status |
| **Errors** | • What went wrong<br>• Stack context<br>• Attachment IDs affected |

---

## 💡 Testing Scenarios

### Test 1: Check File Resolver Performance

**Goal:** See how many attachments have path mismatches

```bash
# Enable logging
echo "define('MSH_DEBUG_LOGGING', true);" >> wp-config.php

# Run analyzer in WP Admin
# Then check results:

grep "FILE_RESOLVER" wp-content/uploads/msh-debug-logs/*.log | wc -l
# Shows: Total resolver calls

grep "MISMATCH RESOLVED" wp-content/uploads/msh-debug-logs/*.log | wc -l
# Shows: How many needed fallback
```

### Test 2: Monitor Specific Operation

**Goal:** Track analyzer run from start to finish

```bash
# Start logging
tail -f wp-content/uploads/msh-debug-logs/msh-debug-$(date +%Y-%m-%d)-*.log

# In another terminal, trigger analyzer
# Watch live log output
```

### Test 3: Verify Clean Import

**Goal:** Confirm fresh test data has no mismatches

```bash
# After importing WordPress theme test data
# Run analyzer, then check:

grep "mismatch: YES" wp-content/uploads/msh-debug-logs/msh-debug-$(date +%Y-%m-%d)-*.log

# Should return nothing (no mismatches)
```

---

## 🧹 Cleanup

### Disable Logging

```php
// Remove or comment out in wp-config.php:
// define('MSH_DEBUG_LOGGING', true);
```

### Delete Old Logs

```bash
# Delete logs older than 7 days (automatic)
# Or manually:
rm wp-content/uploads/msh-debug-logs/msh-debug-2025-10-13-*.log
```

---

## 🆘 Troubleshooting

### "No log files created?"

**Check 1:** Is logging enabled?
```bash
grep "MSH_DEBUG_LOGGING" wp-config.php
# Should show: define('MSH_DEBUG_LOGGING', true);
```

**Check 2:** Directory permissions
```bash
ls -la wp-content/uploads/ | grep msh-debug-logs
# Should show: drwxr-xr-x ... msh-debug-logs

# If missing or wrong permissions:
mkdir -p wp-content/uploads/msh-debug-logs
chmod 755 wp-content/uploads/msh-debug-logs
```

**Check 3:** Run a test operation
- Go to WP Admin
- Click "Run Analyzer"
- Check for new log file

### "Log file too large?"

Clear the current log:
```bash
> wp-content/uploads/msh-debug-logs/msh-debug-2025-10-14-abc123de.log
```

Or filter to specific context:
```bash
grep "FILE_RESOLVER" large-log.log > resolver-only.log
```

---

## 📁 Log File Structure

### Location:
```
wp-content/uploads/msh-debug-logs/
└── msh-debug-YYYY-MM-DD-SESSION.log
```

### Format:
```
[HH:MM:SS.mmm] [CONTEXT] Message
  key: value
  nested_key: nested_value
```

### Contexts:
- `FILE_RESOLVER` - File resolution operations
- `ANALYZER` - Image analysis
- `RENAME` - File rename operations
- `VERIFICATION` - Verification system
- `ERROR` - Errors (❌ prefix)
- `WARNING` - Warnings (⚠️ prefix)
- `SUCCESS` - Success messages (✅ prefix)

---

## 🎓 Example Session

```bash
# 1. Enable
define('MSH_DEBUG_LOGGING', true);

# 2. Test
# Run analyzer in WP Admin

# 3. Review
cd wp-content/uploads/msh-debug-logs
cat msh-debug-2025-10-14-abc123de.log

# 4. Analyze
grep "MISMATCH" msh-debug-2025-10-14-abc123de.log
# Result: Shows which attachments had path mismatches

# 5. Verify fix
grep "method: fallback" msh-debug-2025-10-14-abc123de.log | wc -l
# Result: Count of successful fallback resolutions

# 6. Disable
# Remove MSH_DEBUG_LOGGING from wp-config.php
```

---

## ✅ Expected Results (Fresh Test Data)

With clean WordPress theme test data (no mismatches):

```
[14:23:46.001] [FILE_RESOLVER] Direct match: Attachment 1692 - spectacles.gif
[14:23:46.045] [FILE_RESOLVER] Direct match: Attachment 1691 - dsc20050315_145007_132.jpg
[14:23:46.089] [FILE_RESOLVER] Direct match: Attachment 1690 - 2014-slider-mobile-behavior.mov
... (all should be "Direct match")
```

**Zero "MISMATCH RESOLVED" entries** = Everything working correctly!

---

## 📖 Full Documentation

For complete details, see: **`DEBUG_LOGGING_INSTRUCTIONS.md`**

Includes:
- Advanced usage examples
- Programmatic access via PHP
- Performance impact details
- Log rotation and cleanup
- Integration with other systems

---

**Happy testing! 🔍**
