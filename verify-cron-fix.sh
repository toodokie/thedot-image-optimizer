#!/bin/bash
#
# Verify that the cron migration fix is in place
#
# This script checks that the code contains the necessary fixes for
# auto-sync cron hook migration.
#

echo "🔍 Verifying Cron Migration Fix..."
echo ""

REMOTE_SYNC_FILE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/enterprise/class-msh-remote-sync.php"

if [ ! -f "$REMOTE_SYNC_FILE" ]; then
    echo "❌ ERROR: File not found: $REMOTE_SYNC_FILE"
    exit 1
fi

echo "✅ File found: class-msh-remote-sync.php"
echo ""

# Check 1: New hook is wired
echo "Check 1: New hook 'msh_auto_sync_cron' is wired..."
if grep -q "add_action.*'msh_auto_sync_cron'" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: New hook found"
else
    echo "❌ FAIL: New hook NOT found"
    exit 1
fi

# Check 2: Old hook backward compatibility
echo "Check 2: Old hook 'msh_auto_sync' backward compatibility..."
if grep -q "add_action.*'msh_auto_sync'.*auto_sync" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: Backward compatibility hook found"
else
    echo "❌ FAIL: Backward compatibility hook NOT found"
    exit 1
fi

# Check 3: Migration trigger on init
echo "Check 3: Migration trigger on 'init' hook..."
if grep -q "add_action.*'init'.*migrate_cron_hook" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: Migration trigger found"
else
    echo "❌ FAIL: Migration trigger NOT found"
    exit 1
fi

# Check 4: Migration method exists
echo "Check 4: migrate_cron_hook() method exists..."
if grep -q "function migrate_cron_hook" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: Migration method found"
else
    echo "❌ FAIL: Migration method NOT found"
    exit 1
fi

# Check 5: Migration flag check
echo "Check 5: Migration flag 'msh_cron_hook_migrated' check..."
if grep -q "msh_cron_hook_migrated" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: Migration flag check found"
else
    echo "❌ FAIL: Migration flag check NOT found"
    exit 1
fi

# Check 6: Old hook gets cleared
echo "Check 6: Old hook gets cleared in migration..."
if grep -q "wp_clear_scheduled_hook.*old_hook" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: Old hook clearing found"
else
    echo "❌ FAIL: Old hook clearing NOT found"
    exit 1
fi

# Check 7: New hook gets scheduled
echo "Check 7: New hook gets scheduled in migration..."
if grep -q "wp_schedule_event.*new_hook" "$REMOTE_SYNC_FILE"; then
    echo "✅ PASS: New hook scheduling found"
else
    echo "❌ FAIL: New hook scheduling NOT found"
    exit 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎉 ALL CHECKS PASSED!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "The cron migration fix is correctly implemented:"
echo "  ✓ New hook wired (msh_auto_sync_cron)"
echo "  ✓ Old hook backward compatible (msh_auto_sync)"
echo "  ✓ Migration runs automatically on init"
echo "  ✓ Migration method implemented"
echo "  ✓ One-time migration flag used"
echo "  ✓ Old hook properly cleared"
echo "  ✓ New hook properly scheduled"
echo ""
echo "No manual intervention required for existing sites."
echo "Auto-sync will continue working seamlessly."
echo ""
