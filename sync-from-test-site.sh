#!/bin/bash
# REVERSE Sync: Test Site → Standalone Repository
# Use this when another AI makes changes directly to the Local test site

set -e

TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"
STANDALONE="/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer"

echo "🔄 REVERSE Sync: Test Site → Standalone Repository"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "⚠️  WARNING: This will OVERWRITE standalone repo with test site changes!"
echo "   Press Ctrl+C within 5 seconds to cancel..."
sleep 5

echo ""
echo "📦 Copying files from test site to standalone..."

# Sync from test site to standalone
rsync -av \
    --exclude='docs/' \
    --exclude='*.md' \
    --exclude='wp-config.php' \
    --exclude='wp-cli.yml' \
    "$TEST_SITE/" "$STANDALONE/"

echo "✅ Files copied"

# Show what changed
echo ""
echo "📊 Git status in standalone repo:"
cd /Users/anastasiavolkova/msh-image-optimizer-standalone
git status --short

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Reverse sync complete!"
echo ""
echo "Next steps:"
echo "  1. Review changes: git diff"
echo "  2. Commit changes: git add . && git commit -m 'sync: updates from test site'"
echo "  3. Push to GitHub: git push"
echo ""
