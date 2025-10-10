# How to Avoid Sync Issues - Quick Guide

## The Problem You Just Experienced

✅ **Fixed**: You couldn't see UI changes because the **standalone repo** had new code, but your **Local test site** was running old cached files.

## How to Prevent This

### ⭐ Recommended: Use the Sync Script

**Before testing anything on the Local site, run:**

```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
./sync-to-test.sh
```

Then hard refresh your browser (`Cmd + Shift + R`).

That's it! ✅

---

## Three Sync Options (Pick One)

### 1. Manual Sync (Simplest) ⭐
**When**: Run whenever you want to test changes

```bash
./sync-to-test.sh
```

**Pros**: Full control, simple
**Cons**: Must remember to run it

---

### 2. Auto-Sync on Git Commit (Automated)
**When**: Automatically sync after every `git commit`

```bash
# One-time setup
cp .git/hooks/post-commit.sample .git/hooks/post-commit
chmod +x .git/hooks/post-commit
```

**Pros**: Never forget to sync, great for team workflow
**Cons**: Syncs on every commit (even WIP commits)

---

### 3. File Watcher (Real-Time)
**When**: Sync automatically as you save files

```bash
# Install fswatch (once)
brew install fswatch

# Create watcher script (once)
cat > watch-and-sync.sh << 'EOF'
#!/bin/bash
STANDALONE="/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer"
TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

echo "👀 Watching for changes..."
fswatch -o "$STANDALONE" | while read f; do
    rsync -av --exclude='docs/' --exclude='*.md' "$STANDALONE/" "$TEST_SITE/"
    echo "✅ Synced at $(date +%H:%M:%S)"
done
EOF

chmod +x watch-and-sync.sh

# Run it (leave terminal open)
./watch-and-sync.sh
```

**Pros**: Instant sync, great for active development
**Cons**: Need to keep terminal open

---

## Quick Reference

| Method | Command | When to Use |
|--------|---------|-------------|
| **Manual** | `./sync-to-test.sh` | Testing new features |
| **Git Hook** | Auto after commit | Team development |
| **File Watcher** | `./watch-and-sync.sh` | Active coding sessions |

---

## After Syncing, Always:

1. **Hard refresh browser**: `Cmd + Shift + R` (Mac) or `Ctrl + Shift + R` (Windows)
2. If still not working: Restart Local site to clear PHP OPcache

---

## Full Documentation

- **[PREVENTING_SYNC_ISSUES.md](PREVENTING_SYNC_ISSUES.md)** - All sync methods explained
- **[SYNC_GUIDE.md](SYNC_GUIDE.md)** - Manual commands and troubleshooting
- **[README.md](README.md)** - Updated with sync workflow

---

## TL;DR

**Run this before testing:**
```bash
./sync-to-test.sh
```

**Then hard refresh your browser:**
- Mac: `Cmd + Shift + R`
- Windows: `Ctrl + Shift + R`

Done! ✅
