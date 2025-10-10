#!/bin/bash
# Auto-sync script: Standalone → Local Test Site
# Keeps test site in sync with latest standalone development

set -e  # Exit on any error

STANDALONE="/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer"
TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
WP_CLI="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"
WP_PATH="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

echo "🔄 Syncing MSH Image Optimizer: standalone → test site"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if standalone directory exists
if [ ! -d "$STANDALONE" ]; then
    echo "❌ Error: Standalone directory not found at $STANDALONE"
    exit 1
fi

# Check if test site directory exists
if [ ! -d "$TEST_SITE" ]; then
    echo "❌ Error: Test site directory not found at $TEST_SITE"
    exit 1
fi

# Sync files (exclude docs, README, and markdown files)
echo "📦 Copying files..."
rsync -av \
    --exclude='docs/' \
    --exclude='*.md' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    "$STANDALONE/" "$TEST_SITE/"

echo "✅ Files synced"

# Flush WordPress cache
echo "🧹 Flushing WordPress cache..."
cd "$WP_PATH"
"$WP_CLI" cache flush 2>&1 | grep -q "Success" && echo "✅ Cache flushed" || echo "⚠️  Cache flush may have failed"

# Get plugin version
VERSION=$("$WP_CLI" plugin list --name=msh-image-optimizer --field=version 2>/dev/null || echo "unknown")

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Sync complete!"
echo ""
echo "Plugin version: $VERSION"
echo "Test site: http://thedot-optimizer-test.local/wp-admin"
echo ""
echo "Next steps:"
echo "  1. Hard refresh your browser (Cmd + Shift + R)"
echo "  2. If changes not visible, restart Local site"
echo ""
