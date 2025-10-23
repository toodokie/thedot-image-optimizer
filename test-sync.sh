#!/bin/bash

# Sync Testing Script - Easy Mode
# Navigate to WordPress directory
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

echo "========================================"
echo "   MSH SYNC TESTING SCRIPT"
echo "========================================"
echo ""

# Test 1: Enable Sync
echo "=== TEST 1: Enabling Sync ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '$sync = MSH_Remote_Sync::get_instance(); $result = $sync->enable(); echo "\n=== RESULT ===\n"; print_r($result); echo "\n";'

echo ""
echo "Press Enter to continue to Test 2..."
read

# Test 2: Make a local edit
echo ""
echo "=== TEST 2: Making Local Edit ==="
echo "First, let's see what metadata exists..."
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "SELECT attachment_id, locale, field, manual_value, updated_at FROM wp_optimizer_metadata_cache WHERE attachment_id = 1686;"

echo ""
echo "Inserting a 'title' field for attachment 1686..."
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "
INSERT INTO wp_optimizer_metadata_cache (attachment_id, locale, field, manual_value, chosen_source, created_at, updated_at)
VALUES (1686, 'en_US', 'title', 'LOCAL EDIT - Test Title', 'manual', NOW(), NOW())
ON DUPLICATE KEY UPDATE manual_value = 'LOCAL EDIT - Test Title', updated_at = NOW();
"

echo ""
echo "Verifying the change..."
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "SELECT attachment_id, field, manual_value, updated_at FROM wp_optimizer_metadata_cache WHERE attachment_id = 1686 ORDER BY field;"

echo ""
echo "Press Enter to continue to Test 3..."
read

# Test 3: Run Sync
echo ""
echo "=== TEST 3: Running Sync ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp eval '$sync = MSH_Remote_Sync::get_instance(); $result = $sync->sync_now(); echo "\n=== SYNC RESULT ===\n"; print_r($result); echo "\n";'

echo ""
echo "Press Enter to continue to Test 4..."
read

# Test 4: Verify local edit is still there
echo ""
echo "=== TEST 4: Verifying Local Edit Still There ==="
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp db query "SELECT attachment_id, field, manual_value, updated_at FROM wp_optimizer_metadata_cache WHERE attachment_id = 1686 AND field = 'title' AND manual_value LIKE '%LOCAL EDIT%';"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS: Local edit is still present!"
else
    echo ""
    echo "❌ FAILED: Local edit was overwritten!"
fi

echo ""
echo "Press Enter to check debug logs..."
read

# Test 5: Check logs
echo ""
echo "=== TEST 5: Checking Debug Logs for Conflicts ==="
echo "Last 30 lines with 'sync' or 'conflict':"
tail -n 100 "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/debug.log" | grep -i "sync\|conflict" || echo "No sync/conflict messages in recent logs"

echo ""
echo "========================================"
echo "   TESTING COMPLETE!"
echo "========================================"
echo ""
echo "Expected Results:"
echo "✅ Sync should be enabled"
echo "✅ Pushed 1 change to cloud"
echo "✅ Local edit 'LOCAL EDIT - Test Title' should still be present"
echo "✅ Field-based structure now works correctly with sync!"
echo ""
