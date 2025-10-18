# Symlink Setup Documentation

## Overview

The MSH Image Optimizer plugin uses a **symlink setup** to enable instant synchronization between the development repository and the Local WordPress test site.

---

## Current Setup

### Directory Structure

```
📁 Standalone Repository (Source of Truth)
   /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/
   ├── admin/
   ├── assets/
   ├── includes/
   ├── docs/
   └── msh-image-optimizer.php

   [Git Repository - Under Version Control]
   Remote: https://github.com/toodokie/thedot-image-optimizer.git

        ↕️  SYMLINK (Instant Sync)

📁 Local WordPress Test Site (Symlink)
   /Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer

   → Points to: /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/

   [No Git Repository - Uses Standalone's Git]

📁 Backup (Old Separate Copy)
   /Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP-20251010-075529
```

---

## How It Works

### The Symlink

The Local WordPress plugin directory is a **symbolic link** (symlink) that points to the standalone repository:

```bash
$ ls -la /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public/wp-content/plugins/

lrwxr-xr-x  msh-image-optimizer -> /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
```

**What this means:**
- Both paths point to the **same physical files** on disk
- Changes in one location **instantly** appear in the other
- No copying, no syncing, no manual steps required

---

## Benefits

### ✅ Instant Synchronization
- Edit in standalone repository → Changes **instantly** visible on Local WP test site
- No manual copying or sync scripts needed
- Impossible to have out-of-sync versions

### ✅ Single Source of Truth
- Only **one copy** of the plugin files exists
- All edits happen in the standalone repository
- No confusion about which files to edit

### ✅ Git Control
- Git repository exists **only** in the standalone directory
- Commits and version control happen in one place
- Local WP site simply references the same files

### ✅ Disk Space Savings
- No duplicate files taking up space
- Backup preserved for safety, can be deleted when confident

---

## Workflow

### For Development

**1. Edit Files**
```bash
# Work in the standalone repository
cd /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/

# Edit any file (PHP, JS, CSS, etc.)
vim admin/image-optimizer-admin.php
```

**2. Changes Appear Instantly**
- Save the file
- Changes are **immediately** visible on the Local WP test site
- No additional steps required

**3. Test on Local Site**
- Open: http://thedot-optimizer-test.local/wp-admin
- Navigate to: Tools → MSH Image Optimizer
- Hard refresh browser if needed: `Cmd + Shift + R` (Mac)

**4. Commit Changes**
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone/

# Stage changes
git add .

# Commit
git commit -m "feat: description of changes"

# Push to GitHub (when ready)
git push origin main
```

---

## Git Workflow

### Git Repository Location

**✅ Only in Standalone Repository**
```bash
/Users/anastasiavolkova/msh-image-optimizer-standalone/.git
```

**❌ NOT in Local WP Site**
```bash
/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/.git
# Does not exist - symlink points to standalone
```

### Version Control Commands

All Git commands run in the standalone repository:

```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone/

# Check status
git status

# View changes
git diff

# Commit changes
git add .
git commit -m "message"

# Push to GitHub
git push origin main

# Pull from GitHub
git pull origin main
```

---

## WordPress Integration

### Plugin Detection

WordPress correctly detects the symlinked plugin:

```bash
$ wp plugin list --name=msh-image-optimizer

name                status  version
msh-image-optimizer active  1.1.0
```

**Verification:**
- ✅ Plugin detected by WordPress
- ✅ Plugin activates successfully
- ✅ WP-CLI commands work
- ✅ Admin interface loads correctly
- ✅ All features functional

---

## Setup Details

### Creation Date
**October 10, 2025 at 07:55**

### Commands Used

```bash
# 1. Backup existing plugin
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP-20251010-075529"

# 2. Create symlink
ln -s "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# 3. Verify symlink
ls -lah "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

---

## Verification

### Check Symlink Status

```bash
# View symlink
ls -lah "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/" | grep msh

# Should show:
# lrwxr-xr-x  msh-image-optimizer -> /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
```

### Check Symlink Target

```bash
# Get symlink destination
readlink "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# Should output:
# /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
```

### Verify Files Are Identical

```bash
# Compare files in both locations
diff \
  /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/msh-image-optimizer.php \
  "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/msh-image-optimizer.php"

# Should output nothing (files are identical because they're the same file)
```

---

## Troubleshooting

### Symlink Broken or Missing

**Check if symlink exists:**
```bash
ls -la "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/" | grep msh
```

**Recreate symlink if needed:**
```bash
ln -s "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

### WordPress Not Detecting Plugin

**Restart Local site:**
1. Open Local app
2. Stop site: `thedot-optimizer-test`
3. Start site again

**Or flush cache:**
```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" cache flush
```

### Changes Not Appearing

**1. Hard refresh browser:**
- Mac: `Cmd + Shift + R`
- Windows: `Ctrl + Shift + R`

**2. Check file was actually saved:**
```bash
ls -lh /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/path/to/file.php
```

**3. Verify symlink still works:**
```bash
readlink "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

---

## Removing Symlink (If Needed)

### To Revert to Separate Copies

```bash
# 1. Remove symlink
rm "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# 2. Restore backup
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP-20251010-075529" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# 3. Or copy fresh from standalone
cp -r "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/"
```

---

## Best Practices

### ✅ DO:
- Edit files in the standalone repository
- Commit changes from the standalone repository
- Test on Local WP site via http://thedot-optimizer-test.local
- Keep the backup until confident symlink works

### ❌ DON'T:
- Edit files via the Local WP path (they're the same files, but maintain clear workflow)
- Commit from inside the Local WP plugins directory
- Delete the backup until symlink is verified working for a few days
- Create files that shouldn't be tracked (they'll show in Git status)

---

## Team Coordination

### For Multiple Developers/AIs

**Working Copy:**
- Everyone edits: `/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/`
- Changes instantly visible to all

**Git Workflow:**
```bash
# Before starting work
git pull origin main

# After completing work
git add .
git commit -m "descriptive message"
git push origin main
```

**Testing:**
- All developers see changes instantly on Local WP test site
- No sync scripts needed
- No manual file copying

---

## Summary

### Current Configuration

| Component | Location | Type | Git Control |
|-----------|----------|------|-------------|
| **Working Copy** | `/msh-image-optimizer-standalone/msh-image-optimizer/` | Directory | ✅ Yes |
| **Local WP Plugin** | `/Local Sites/.../plugins/msh-image-optimizer` | Symlink | ❌ No (uses standalone) |
| **Backup** | `/Local Sites/.../plugins/msh-image-optimizer.BACKUP-...` | Directory | ❌ No |

### Result

✅ **Instant synchronization between development and testing**
✅ **No manual copying or sync scripts required**
✅ **Single source of truth for all code changes**
✅ **Git version control in one location only**

---

## Related Documentation

- [SYNC_GUIDE.md](SYNC_GUIDE.md) - Legacy manual sync methods (no longer needed)
- [PREVENTING_SYNC_ISSUES.md](PREVENTING_SYNC_ISSUES.md) - Various sync strategies
- [USE_SYMLINK_INSTEAD.md](USE_SYMLINK_INSTEAD.md) - Why symlink is best solution
- [WP-CLI Test Results](../testing/WP_CLI_TEST_RESULTS.md) - CLI testing documentation

---

**Setup Date:** October 10, 2025
**Status:** ✅ Active and Working
**Last Verified:** October 10, 2025
