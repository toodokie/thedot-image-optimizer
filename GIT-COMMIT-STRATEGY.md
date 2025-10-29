# Git Commit Strategy - TinyDot Image Optimizer

**Created:** October 26, 2025
**Status:** Active
**Launch Date:** October 30, 2025 (4 days)

---

## Current Situation

We have **2 identical git repositories** pointing to the same GitHub remote:

| Repository | Purpose | Git Status | Location |
|------------|---------|------------|----------|
| **msh-image-optimizer-standalone** | ✅ **PRIMARY DEV REPO** | 60 commits, synced | `/Users/anastasiavolkova/msh-image-optimizer-standalone` |
| **thedot-optimizer-test** | ⚠️ Secondary (testing inside WordPress) | 60 commits, synced | `/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer` |

**GitHub Remote:** `https://github.com/toodokie/thedot-image-optimizer.git`

**Both repos are currently IDENTICAL:**
- Same 60 commits
- Same code (10,109 lines in main class)
- Same Phase 6 fixes
- Same documentation

---

## 🎯 The Strategy: Single Source of Truth

### Option A: Use Standalone ONLY (RECOMMENDED)

**Primary Development Repo:**
- `msh-image-optimizer-standalone` (your current VS Code workspace)

**Workflow:**
1. **Make all changes** in `msh-image-optimizer-standalone`
2. **Commit and push** from standalone to GitHub
3. **Copy to WordPress sites** when ready to test (rsync, no git)
4. **Testing sites have NO git repos** (just plugin files)

**Benefits:**
- ✅ No confusion about which repo to use
- ✅ Single source of truth
- ✅ Simple workflow
- ✅ No duplicate commits
- ✅ No sync issues

**Testing Sites (No Git):**
- `test-main-street-health` - Medical site + Elementor (Phase 6 testing)
- `thedot-optimizer-test` - General testing
- `main-street-health` - Production-like environment
- `radiant-bloom-wellness-site` - Wellness testing
- `sterling-law-firm` - Law firm testing

---

## 📋 Standard Workflow

### 1. Development
```bash
cd /Users/anastasiavolkova/msh-image-optimizer-standalone

# Make your changes
# Edit files in VS Code

# Check status
git status

# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat: Add new feature X

Detailed explanation of what was done and why.

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"

# Push to GitHub
git push origin main
```

### 2. Testing
```bash
# Copy to test site when ready
rsync -av --exclude='.git' --exclude='node_modules' \
  /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
  "/Users/anastasiavolkova/Local Sites/test-main-street-health/app/public/wp-content/plugins/msh-image-optimizer/"

# Open Local app → Start site → Test
```

### 3. Never Do This
❌ Don't commit from thedot-optimizer-test
❌ Don't make changes in WordPress plugin folders
❌ Don't maintain multiple git repos
❌ Don't copy .git folders between sites

---

## 🔧 thedot-optimizer-test: Read-Only Backup Repo

**Status: ✅ CONFIGURED AS READ-ONLY BACKUP**

The thedot repo has been deactivated for commits but kept as a safety backup:

**Protections Applied:**
- ✅ Git remote renamed to `origin-readonly` (blocks `git push origin`)
- ✅ Warning file `DO-NOT-COMMIT-HERE.txt` in root directory
- ✅ Git tag `READ-ONLY-BACKUP` marks it as backup repo
- ✅ Still connected to GitHub (can pull updates if needed)
- ✅ All 60 commits preserved safely

**Tested Protection Level:**
- ❌ `git push origin main` → **BLOCKED** (remote doesn't exist)
- ❌ `git push` → **BLOCKED** (no default remote)
- ⚠️ `git commit` → Still works locally (but can't push)

**Why commits still work:**
- You can commit locally if needed for testing
- But you **cannot push to GitHub** (the important protection)
- Warning file reminds you this is read-only backup
- If you try to push, git errors: "origin does not appear to be a git repository"

**You can still:**
- View git history (`git log`)
- Pull updates from GitHub (`git fetch origin-readonly`)
- Commit locally for testing (but can't push - harmless)
- Use it for testing WordPress integration
- Keep it as emergency backup

**To restore from backup (emergency only):**
```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
git remote rename origin-readonly origin
# Now you can pull/push if needed
```

---

## 📦 Clean Copy Command (For All Test Sites)

Use this to copy from standalone to ANY test site:

```bash
# Copy to test-main-street-health
rsync -av --exclude='.git' --exclude='node_modules' \
  /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
  "/Users/anastasiavolkova/Local Sites/test-main-street-health/app/public/wp-content/plugins/msh-image-optimizer/"

# Copy to thedot-optimizer-test
rsync -av --exclude='.git' --exclude='node_modules' \
  /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
  "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/"

# Copy to main-street-health
rsync -av --exclude='.git' --exclude='node_modules' \
  /Users/anastasiavolkova/msh-image-optimizer-standalone/ \
  "/Users/anastasiavolkova/Local Sites/main-street-health/app/public/wp-content/plugins/msh-image-optimizer/"
```

---

## 🚨 Emergency: If You Accidentally Commit to Wrong Repo

If you accidentally committed to `thedot-optimizer-test`:

```bash
# 1. Get the commit message
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
git log -1 --pretty=format:"%s%n%n%b"

# 2. Copy the changed files to standalone
rsync -av --exclude='.git' \
  "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/" \
  /Users/anastasiavolkova/msh-image-optimizer-standalone/

# 3. Commit from standalone
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
git add .
git commit -m "paste commit message here"
git push origin main

# 4. Reset thedot to match GitHub
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
git fetch origin
git reset --hard origin/main
```

---

## 📊 Repository Status Check

Run this anytime to see status:

```bash
echo "=== STANDALONE (PRIMARY) ==="
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
git status
echo ""
echo "Latest commit:"
git log -1 --oneline
echo ""
echo "=== GITHUB ==="
git fetch origin
git log origin/main -1 --oneline
```

---

## ✅ Pre-Launch Checklist (October 30, 2025)

Before launch, ensure:

- [ ] All code committed from `msh-image-optimizer-standalone`
- [ ] GitHub has latest code
- [ ] Testing sites have fresh copies (no .git folders)
- [ ] Phase 6 fixes verified in all test environments
- [ ] thedot-optimizer-test .git folder removed OR documented as read-only backup
- [ ] This strategy document reviewed and understood

---

## 🎓 Summary

**Golden Rule:** All development happens in `msh-image-optimizer-standalone`, everything else is just testing copies.

**One Repo to Rule Them All:**
```
msh-image-optimizer-standalone (git repo)
          ↓
    [Edit, Commit, Push]
          ↓
      GitHub
          ↓
    [rsync to test sites]
          ↓
WordPress Test Sites (NO git, just files)
```

**Questions?** Read this document before making any commits!
