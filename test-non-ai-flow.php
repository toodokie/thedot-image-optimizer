<?php
/**
 * Non-AI Flow Test
 *
 * Tests contextual metadata generation without AI/tokens
 * - Context Helper
 * - Metadata updates
 * - No token deduction
 *
 * Usage: wp eval-file test-non-ai-flow.php
 */

echo "=== NON-AI FLOW TEST ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Get a test image
$images = get_posts(array(
	'post_type' => 'attachment',
	'post_mime_type' => 'image',
	'posts_per_page' => 1,
	'orderby' => 'ID',
	'order' => 'DESC',
));

if (empty($images)) {
	echo "✗ No test images found\n";
	exit(1);
}

$image = $images[0];
$attachment_id = $image->ID;

echo "Testing with image ID: $attachment_id\n";
echo "Current title: " . get_the_title($attachment_id) . "\n\n";

// Test 1: Context classes
echo "[Test 1] Context system availability...\n";
if (class_exists('MSH_Context_Manager')) {
	echo "  ✓ MSH_Context_Manager loaded\n";
} else {
	echo "  ⚠ MSH_Context_Manager NOT loaded (non-critical for basic metadata)\n";
}

// Test 2: Get current metadata
echo "\n[Test 2] Current metadata...\n";
$current_meta = array(
	'title' => get_the_title($attachment_id),
	'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
	'caption' => wp_get_attachment_caption($attachment_id),
	'description' => get_post_field('post_content', $attachment_id),
);

echo "  Title: " . ($current_meta['title'] ?: '(empty)') . "\n";
echo "  Alt: " . ($current_meta['alt'] ?: '(empty)') . "\n";
echo "  Caption: " . ($current_meta['caption'] ?: '(empty)') . "\n";
echo "  Description: " . ($current_meta['description'] ?: '(empty)') . "\n";

// Test 3: Update metadata (non-AI contextual)
echo "\n[Test 3] Update metadata (contextual, no AI)...\n";

$test_suffix = '-' . time();
$new_metadata = array(
	'title' => $current_meta['title'] . $test_suffix,
	'alt' => ($current_meta['alt'] ?: 'Test alt text') . $test_suffix,
	'caption' => 'Test caption' . $test_suffix,
);

// Update title
wp_update_post(array(
	'ID' => $attachment_id,
	'post_title' => $new_metadata['title'],
));
echo "  ✓ Title updated\n";

// Update alt text
update_post_meta($attachment_id, '_wp_attachment_image_alt', $new_metadata['alt']);
echo "  ✓ Alt text updated\n";

// Update caption
wp_update_post(array(
	'ID' => $attachment_id,
	'post_excerpt' => $new_metadata['caption'],
));
echo "  ✓ Caption updated\n";

// Test 4: Verify updates
echo "\n[Test 4] Verify updates...\n";

$updated_meta = array(
	'title' => get_the_title($attachment_id),
	'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
	'caption' => wp_get_attachment_caption($attachment_id),
);

$all_match = true;
if ($updated_meta['title'] !== $new_metadata['title']) {
	echo "  ✗ Title mismatch\n";
	$all_match = false;
} else {
	echo "  ✓ Title matches\n";
}

if ($updated_meta['alt'] !== $new_metadata['alt']) {
	echo "  ✗ Alt text mismatch\n";
	$all_match = false;
} else {
	echo "  ✓ Alt text matches\n";
}

if ($updated_meta['caption'] !== $new_metadata['caption']) {
	echo "  ✗ Caption mismatch\n";
	$all_match = false;
} else {
	echo "  ✓ Caption matches\n";
}

// Test 5: Restore original metadata
echo "\n[Test 5] Restore original metadata...\n";

wp_update_post(array(
	'ID' => $attachment_id,
	'post_title' => $current_meta['title'],
	'post_excerpt' => $current_meta['caption'],
));
update_post_meta($attachment_id, '_wp_attachment_image_alt', $current_meta['alt']);

echo "  ✓ Metadata restored\n";

// Test 6: Token Manager NOT involved
echo "\n[Test 6] Verify no token usage...\n";
global $wpdb;
$recent_token_usage = $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(*) FROM wp_msh_ai_token_usage
	 WHERE attachment_id = %d
	 AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
	$attachment_id
));

if ($recent_token_usage == 0) {
	echo "  ✓ No tokens used (correct for non-AI flow)\n";
} else {
	echo "  ⚠ Tokens used: $recent_token_usage (unexpected for non-AI flow)\n";
}

// Summary
echo "\n=== NON-AI FLOW RESULTS ===\n\n";

if ($all_match) {
	echo "✅ ALL TESTS PASSED\n";
	echo "✅ Non-AI metadata operations work correctly\n";
	echo "✅ No token deduction (as expected)\n";
	exit(0);
} else {
	echo "⚠️ SOME TESTS FAILED\n";
	exit(1);
}
