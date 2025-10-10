# Quick Start Guide

## Current Setup (Symlink Configuration)

Your development environment uses **symlinks** for instant synchronization. Here's everything you need to know:

---

## 📁 File Locations

### Work Here (Source of Truth):
```
/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/
```
- Edit all files here
- Git commits happen here
- This is under version control

### Test Site (Symlink - Auto-Updates):
```
/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/
```
- This is a **symlink** pointing to standalone repo
- Changes appear here **instantly**
- No manual copying needed

### Backup (Old Copy):
```
/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP-20251010-075529/
```
- Old separate copy (before symlink)
- Can be deleted after verifying symlink works

---

## 🚀 Development Workflow

### 1. Edit Files
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/

# Edit any file
vim admin/image-optimizer-admin.php
```

### 2. Test Instantly
- Open: http://thedot-optimizer-test.local/wp-admin
- Navigate to: **Tools → MSH Image Optimizer**
- Hard refresh if needed: `Cmd + Shift + R`
- Changes are **already there!** ✅

### 3. Commit Changes
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone/

git add .
git commit -m "feat: your changes"
git push origin main  # When ready
```

---

## 🔧 WP-CLI Testing

### Quick Commands
```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

# List plugins
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" plugin list

# Test rename
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" msh rename-regression --ids=123,456

# Full QA suite
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" msh qa --rename=123 --optimize=456 --duplicate
```

See [WP_CLI_TEST_RESULTS.md](WP_CLI_TEST_RESULTS.md) for more examples.

---

## 🔍 Verify Symlink Works

### Check Symlink Status
```bash
ls -lah "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/" | grep msh

# Should show:
# lrwxr-xr-x  msh-image-optimizer -> /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
```

### Test Instant Sync
```bash
# 1. Edit in standalone
echo "// Test" >> /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/msh-image-optimizer.php

# 2. Check it appears in test site
tail -1 "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/msh-image-optimizer.php"

# Should show: // Test

# 3. Remove test line
git checkout -- msh-image-optimizer/msh-image-optimizer.php
```

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [SYMLINK_SETUP.md](SYMLINK_SETUP.md) | Complete symlink configuration details |
| [WP_CLI_TEST_RESULTS.md](WP_CLI_TEST_RESULTS.md) | CLI testing commands and results |
| [README.md](README.md) | Plugin overview and features |

**Legacy (before symlink):**
- [SYNC_GUIDE.md](SYNC_GUIDE.md) - Manual sync methods
- [PREVENTING_SYNC_ISSUES.md](PREVENTING_SYNC_ISSUES.md) - Sync automation

---

## ⚠️ Important Notes

### ✅ DO:
- Edit files in standalone repo
- Commit from standalone repo
- Test on Local WP site
- Push to GitHub when ready

### ❌ DON'T:
- Edit via Local WP path (they're the same files, but maintain workflow clarity)
- Try to sync manually (not needed - symlink auto-syncs!)
- Delete backup yet (wait until confident symlink works)

---

## 🆘 Troubleshooting

### Changes Not Appearing?

**1. Hard refresh browser:**
```bash
Cmd + Shift + R  # Mac
Ctrl + Shift + R # Windows
```

**2. Verify symlink:**
```bash
readlink "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# Should output:
# /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
```

**3. Restart Local site:**
- Open Local app
- Stop `thedot-optimizer-test`
- Start it again

---

## Summary

**Setup:** ✅ Symlink configured (Oct 10, 2025)
**Workflow:** Edit in standalone → Instantly on test site
**No sync needed:** Automatic via symlink
**Git control:** Only in standalone repo

**You're all set!** 🎉
