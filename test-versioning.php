<?php
/**
 * Quick test script for Phase 4 Metadata Versioning
 *
 * Run this from WP-CLI:
 * wp eval-file test-versioning.php
 */

// Get the versioning instances
$versioning = MSH_Metadata_Versioning::get_instance();
$protection = MSH_Manual_Edit_Protection::get_instance();

// Pick a random attachment
$attachments = get_posts(array(
    'post_type' => 'attachment',
    'posts_per_page' => 1,
    'orderby' => 'rand'
));

if (empty($attachments)) {
    echo "❌ No attachments found!\n";
    exit;
}

$media_id = $attachments[0]->ID;
$locale = 'en';

echo "🧪 Testing Metadata Versioning System\n";
echo "=====================================\n\n";
echo "Test Image: #{$media_id} - {$attachments[0]->post_title}\n\n";

// Test 1: Save an AI version
echo "Test 1: Save AI-generated version...\n";
$v1 = $versioning->save_version(
    $media_id,
    $locale,
    'title',
    'AI Generated Title - Test',
    'ai'
);

if ($v1) {
    echo "✅ Saved AI version (ID: $v1)\n\n";
} else {
    echo "❌ Failed to save AI version\n\n";
}

// Test 2: Save a manual version
echo "Test 2: Save manual edit version...\n";
$v2 = $versioning->save_version(
    $media_id,
    $locale,
    'title',
    'Manually Edited Title - Test',
    'manual'
);

if ($v2) {
    echo "✅ Saved manual version (ID: $v2)\n\n";
} else {
    echo "❌ Failed to save manual version\n\n";
}

// Test 3: Check if AI can write (should be FALSE)
echo "Test 3: Check if AI can overwrite...\n";
$can_write = $protection->can_ai_write($media_id, 'title', $locale);

if (!$can_write) {
    echo "✅ PASS: AI correctly blocked from overwriting manual edit\n\n";
} else {
    echo "❌ FAIL: AI should NOT be able to overwrite manual edit\n\n";
}

// Test 4: Force replace (should be TRUE)
echo "Test 4: Check force replace option...\n";
$can_force = $protection->can_ai_write($media_id, 'title', $locale, true);

if ($can_force) {
    echo "✅ PASS: Force replace option works\n\n";
} else {
    echo "❌ FAIL: Force replace should allow AI to write\n\n";
}

// Test 5: Get version history
echo "Test 5: Get version history...\n";
$history = $versioning->get_version_history($media_id, $locale, 'title');

echo "Found " . count($history) . " versions:\n";
foreach ($history as $version) {
    echo "  - Version {$version['version']}: \"{$version['value']}\" (source: {$version['source']})\n";
}
echo "\n";

// Test 6: Get active version
echo "Test 6: Get active version...\n";
$active = $versioning->get_active_version($media_id, $locale, 'title');

if ($active && $active['source'] === 'manual') {
    echo "✅ PASS: Active version is manual edit (as expected)\n";
    echo "   Value: \"{$active['value']}\"\n\n";
} else {
    echo "❌ FAIL: Active version should be the manual edit\n\n";
}

// Test 7: AI vs Manual diff
echo "Test 7: Get AI vs Manual diff...\n";
$diffs = $versioning->get_ai_vs_manual_diff($media_id, $locale);

if (isset($diffs['title']) && $diffs['title']['has_manual']) {
    echo "✅ PASS: Diff shows manual edit exists\n";
    echo "   AI version: \"{$diffs['title']['ai']['value']}\"\n";
    echo "   Manual version: \"{$diffs['title']['manual']['value']}\"\n";
    echo "   Manual is active: " . ($diffs['title']['manual_is_active'] ? 'Yes' : 'No') . "\n\n";
} else {
    echo "❌ FAIL: Diff should show manual edit\n\n";
}

echo "=====================================\n";
echo "✅ All Tests Complete!\n\n";

// Cleanup test data
echo "Cleaning up test data...\n";
global $wpdb;
$wpdb->delete(
    $wpdb->prefix . 'msh_optimizer_metadata',
    array('media_id' => $media_id),
    array('%d')
);
echo "✅ Cleanup done\n";
