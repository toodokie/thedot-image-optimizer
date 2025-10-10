## ⭐ BEST SOLUTION: Use a Symlink (Auto-Update)

### The Problem You're Experiencing

**Current Setup** (requires manual sync):
```
Standalone Repo (/msh-image-optimizer-standalone)
    ↓ (manual copy needed)
Test Site (/Local Sites/.../msh-image-optimizer)  ← SEPARATE FILES
```

When another AI edits the test site directly, you don't see it in standalone (and vice versa).

---

### THE SOLUTION: Create a Symlink ✨

A symlink makes BOTH locations point to THE SAME FILES. Changes appear instantly in both places!

**New Setup** (automatic):
```
Standalone Repo (/msh-image-optimizer-standalone)
    ↕ (SAME FILES via symlink)
Test Site (/Local Sites/.../msh-image-optimizer)  ← SYMLINK
```

---

## How to Set It Up (One-Time)

### Step 1: Backup Current Test Site Plugin

```bash
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP"
```

### Step 2: Create Symlink

```bash
ln -s "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

### Step 3: Verify It Works

```bash
ls -lah "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

You should see:
```
lrwxr-xr-x  ... msh-image-optimizer -> /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
```

The `->` arrow means it's a symlink!

---

## What This Means

### ✅ Benefits

1. **Instant Updates**: Any change to standalone repo = instant update on test site
2. **Instant Updates**: Any change on test site = instant update in standalone repo
3. **No Manual Sync**: Never run `sync-to-test.sh` again
4. **One Source of Truth**: Only one copy of files exists
5. **Works with Git**: Commit from standalone, see changes on test site immediately

### ✅ How It Works

- **Edit in standalone**: Changes visible on test site instantly
- **Another AI edits test site**: Changes visible in standalone instantly
- **Git commits**: Commit from standalone as usual
- **WordPress sees**: The plugin exactly as if it were in plugins folder

---

## Testing the Symlink

### Test 1: Edit Standalone, See on Test Site

```bash
# Edit a file in standalone
echo "// Test change" >> /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/admin/image-optimizer-admin.php

# Check test site has same change
tail -1 "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php"

# Should see: // Test change
```

### Test 2: Edit Test Site, See in Standalone

```bash
# Edit a file via test site path
echo "// Another test" >> "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php"

# Check standalone has same change
tail -1 /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/admin/image-optimizer-admin.php

# Should see: // Another test
```

They're THE SAME FILE!

---

## Undo Symlink (If Needed)

If you want to go back to separate copies:

```bash
# Remove symlink
rm "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# Restore backup
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

---

## Important Notes

### ⚠️ Don't Commit Test Site Configs

The symlink works great, but be careful NOT to commit these files (they're test-site specific):
- `wp-config.php` (has socket path)
- `wp-cli.yml` (test site config)

They're already in `.gitignore`, so you should be fine.

### ✅ Git Workflow Still Works

```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone

# Make changes (in standalone OR via test site - same thing!)
git add .
git commit -m "feat: new feature"
git push
```

Changes are committed from standalone repo as usual.

---

## Why This Is Better Than Sync Scripts

| Sync Scripts | Symlink |
|--------------|---------|
| ❌ Must run manually | ✅ Automatic |
| ❌ Can forget to sync | ✅ Always in sync |
| ❌ Two copies of files | ✅ One copy |
| ❌ Can edit wrong version | ✅ Impossible - only one version |
| ❌ Wastes disk space | ✅ Saves disk space |
| ✅ Safer (can't break test site) | ⚠️ Editing affects both instantly |

---

## Quick Setup Command (Copy-Paste)

```bash
# One command to set it all up:
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP" && \
ln -s "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer" && \
echo "✅ Symlink created! Changes now sync automatically."
```

---

## TL;DR

**Run this one command:**
```bash
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.BACKUP" && \
ln -s "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

**Result:** ✅ **Automatic sync forever!** No more sync issues. Ever.
