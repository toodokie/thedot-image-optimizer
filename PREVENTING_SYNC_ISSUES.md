# Preventing Sync Issues: Keeping Test Site Up-to-Date

## The Problem

When working on the **standalone repository**, changes don't automatically appear on the **Local test site**. This causes confusion when:
- Other contributors make changes to standalone
- You switch between different tasks
- Files get cached by PHP OPcache or browser cache

## Solutions (Pick One or Combine)

---

## ✅ Option 1: Manual Sync Script (Recommended)

**When to use**: Run this whenever you want to test changes on the Local site.

### Quick Start
```bash
# From the standalone repo directory
./sync-to-test.sh
```

That's it! The script:
- ✅ Copies all files from standalone → test site
- ✅ Flushes WordPress cache
- ✅ Shows you what to do next

### First Time Setup
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
chmod +x sync-to-test.sh
```

---

## ✅ Option 2: Auto-Sync After Git Commits (Advanced)

**When to use**: Automatically sync every time you commit changes to Git.

### Setup
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone

# Enable the Git hook
cp .git/hooks/post-commit.sample .git/hooks/post-commit
chmod +x .git/hooks/post-commit
```

### How it Works
After every `git commit`, the hook automatically:
1. Detects the test site exists
2. Runs `sync-to-test.sh`
3. Syncs all files
4. Flushes cache

### Disable Auto-Sync
```bash
rm .git/hooks/post-commit
```

---

## ✅ Option 3: File Watcher (Real-Time Sync)

**When to use**: Automatically sync as you save files (most convenient).

### Install fswatch (Mac)
```bash
brew install fswatch
```

### Create Watcher Script

Save as `watch-and-sync.sh`:
```bash
#!/bin/bash
STANDALONE="/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer"
TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

echo "👀 Watching for file changes in standalone repo..."
echo "Press Ctrl+C to stop"

fswatch -o "$STANDALONE" | while read f; do
    echo "🔄 Change detected, syncing..."
    rsync -av \
        --exclude='docs/' \
        --exclude='*.md' \
        --exclude='.git' \
        "$STANDALONE/" "$TEST_SITE/"
    echo "✅ Synced at $(date +%H:%M:%S)"
done
```

### Run the Watcher
```bash
chmod +x watch-and-sync.sh
./watch-and-sync.sh
```

Leave it running in a terminal tab while you work. Every time you save a file, it auto-syncs!

---

## ✅ Option 4: Symlink (Direct Link)

**When to use**: Work directly on the test site's files (not recommended for production workflow).

⚠️ **Warning**: This bypasses the standalone repo entirely. Only use for quick testing.

### Setup
```bash
# Backup test site plugin first
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.backup"

# Create symlink
ln -s "/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer" \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

Now changes to standalone files instantly appear on the test site!

### Undo Symlink
```bash
rm "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
mv "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer.backup" \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
```

---

## Recommended Workflow

### For Solo Development
**Use Option 1 (Manual Sync Script)** - Run when you want to test:
```bash
./sync-to-test.sh
```

### For Team Development
**Use Option 2 (Git Hook)** - Auto-sync after commits so team members always have latest:
```bash
cp .git/hooks/post-commit.sample .git/hooks/post-commit
chmod +x .git/hooks/post-commit
```

### For Active Development Sessions
**Use Option 3 (File Watcher)** - Run in background while working:
```bash
./watch-and-sync.sh
```

---

## After Syncing

Always do these steps to see changes:

### 1. Hard Refresh Browser
- **Mac**: `Cmd + Shift + R`
- **Windows**: `Ctrl + Shift + R`

### 2. Clear PHP OPcache (if changes still not visible)
```bash
# Restart Local site
# Or run:
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" cache flush
```

### 3. Check Browser DevTools
- Open DevTools → Network tab
- Look for cached JS/CSS files
- Clear browser cache completely if needed

---

## Quick Reference Commands

```bash
# Manual sync
./sync-to-test.sh

# Enable auto-sync on commit
cp .git/hooks/post-commit.sample .git/hooks/post-commit
chmod +x .git/hooks/post-commit

# Start file watcher
./watch-and-sync.sh

# Flush WordPress cache
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" cache flush
```

---

## Troubleshooting

### Changes still not showing after sync?

1. **Hard refresh browser** (`Cmd + Shift + R`)
2. **Restart Local site** (stops PHP OPcache)
3. **Clear all browser cache** (DevTools → Application → Clear storage)
4. **Check file timestamps**:
   ```bash
   ls -lh "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php"
   ```

### Sync script not working?

```bash
# Check paths are correct
ls -la /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer
ls -la "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# Make script executable
chmod +x sync-to-test.sh
```

### Git hook not triggering?

```bash
# Check hook exists and is executable
ls -la .git/hooks/post-commit
chmod +x .git/hooks/post-commit

# Test manually
.git/hooks/post-commit
```

---

## Best Practices

1. ✅ **Always work in standalone repo** - Never edit test site files directly
2. ✅ **Sync before testing** - Run `./sync-to-test.sh` before each test session
3. ✅ **Hard refresh browser** - After every sync
4. ✅ **Check Git status** - Before syncing to avoid partial changes
5. ✅ **Document workflow** - Let team know which sync method you're using
