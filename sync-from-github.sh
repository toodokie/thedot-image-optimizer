#!/bin/bash
# Complete sync workflow: GitHub → Standalone → Test Site
# This ensures you always have the latest changes from all contributors

set -e

STANDALONE_DIR="/Users/anastasiavolkova/msh-image-optimizer-standalone"
TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
WP_CLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"
WP_PATH="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

echo "🔄 Complete Sync: GitHub → Standalone → Test Site"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Step 1: Pull latest from GitHub
echo "📥 Step 1: Pulling latest changes from GitHub..."
cd "$STANDALONE_DIR"

# Check for uncommitted changes
if ! git diff-index --quiet HEAD -- 2>/dev/null; then
    echo "⚠️  Warning: You have uncommitted changes in standalone repo"
    echo "   Stashing changes temporarily..."
    git stash
    STASHED=true
else
    STASHED=false
fi

# Pull from GitHub
git fetch origin
BEFORE_COMMIT=$(git rev-parse HEAD)
git pull origin main --rebase

AFTER_COMMIT=$(git rev-parse HEAD)

if [ "$BEFORE_COMMIT" != "$AFTER_COMMIT" ]; then
    echo "✅ Pulled new changes from GitHub"
    git log --oneline "$BEFORE_COMMIT..$AFTER_COMMIT"
else
    echo "✅ Already up to date with GitHub"
fi

# Restore stashed changes if any
if [ "$STASHED" = true ]; then
    echo "   Restoring your uncommitted changes..."
    git stash pop
fi

echo ""
echo "📦 Step 2: Syncing to Local test site..."

# Step 2: Sync to test site
rsync -av \
    --exclude='docs/' \
    --exclude='*.md' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='sync-*.sh' \
    "$STANDALONE_DIR/msh-image-optimizer/" "$TEST_SITE/"

echo "✅ Files synced to test site"

# Step 3: Flush WordPress cache
echo ""
echo "🧹 Step 3: Flushing WordPress cache..."
cd "$WP_PATH"
"$WP_CLI" cache flush 2>&1 | grep -q "Success" && echo "✅ Cache flushed" || echo "⚠️  Cache flush may have failed"

# Get plugin version
VERSION=$("$WP_CLI" plugin list --name=msh-image-optimizer --field=version 2>/dev/null || echo "unknown")

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Complete sync finished!"
echo ""
echo "Plugin version: $VERSION"
echo "Test site: http://thedot-optimizer-test.local/wp-admin"
echo ""
echo "Changes pulled from GitHub:"
if [ "$BEFORE_COMMIT" != "$AFTER_COMMIT" ]; then
    git -C "$STANDALONE_DIR" log --oneline -5
else
    echo "  (No new commits)"
fi
echo ""
echo "Next steps:"
echo "  1. Hard refresh your browser (Cmd + Shift + R)"
echo "  2. If changes not visible, restart Local site"
echo ""
