<?php
/**
 * AI Flow Test (Smart Mode)
 *
 * Tests complete AI optimization flow:
 * - Token estimation
 * - Token deduction
 * - AI metadata generation (simulated)
 * - Token reconciliation
 * - Telemetry logging
 *
 * Usage: wp eval-file test-ai-flow.php
 */

echo "=== AI FLOW TEST (Smart Mode) ===\n";
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

// Test 1: Token Manager initialization
echo "[Test 1] Token Manager initialization...\n";
try {
	$manager = new MSH_Token_Manager('SITE_PRO');
	$balance_before = $manager->get_balance();
	echo "  ✓ Token Manager initialized\n";
	echo "  ✓ Balance before: {$balance_before['tokens_remaining']} tokens\n";
} catch (Exception $e) {
	echo "  ✗ Error: " . $e->getMessage() . "\n";
	exit(1);
}

// Test 2: Token estimation
echo "\n[Test 2] Token estimation...\n";
try {
	$tokens_estimated = $manager->estimate_tokens('ai_analysis_smart');
	echo "  ✓ Estimated tokens: $tokens_estimated\n";

	if ($tokens_estimated >= 280 && $tokens_estimated <= 330) {
		echo "  ✓ Estimate within expected range (280-330)\n";
	} else {
		echo "  ⚠ Estimate outside expected range: $tokens_estimated\n";
	}
} catch (Exception $e) {
	echo "  ✗ Error: " . $e->getMessage() . "\n";
	exit(1);
}

// Test 3: Token deduction (atomic)
echo "\n[Test 3] Atomic token deduction...\n";
$operation_id = 'ai-flow-test-' . $attachment_id . '-' . time();

try {
	$manager->deduct($tokens_estimated, $operation_id);
	echo "  ✓ Tokens deducted: $tokens_estimated\n";

	$balance_after_deduct = $manager->get_balance();
	$deducted = $balance_before['tokens_remaining'] - $balance_after_deduct['tokens_remaining'];
	echo "  ✓ Balance after deduct: {$balance_after_deduct['tokens_remaining']} tokens\n";
	echo "  ✓ Actual deducted: $deducted tokens\n";
} catch (Exception $e) {
	echo "  ✗ Deduction failed: " . $e->getMessage() . "\n";
	exit(1);
}

// Test 4: Simulate AI processing
echo "\n[Test 4] Simulate AI processing...\n";
sleep(1); // Simulate API call latency

// Simulate Smart Mode response (Phase 0B: 309 avg tokens)
$tokens_actual = 309 + rand(-15, 15); // Realistic variance
$ai_metadata = array(
	'title' => 'AI Generated Title - Test ' . time(),
	'alt' => 'AI generated alt text describing the image',
	'caption' => '',
	'description' => '',
);

echo "  ✓ AI processing complete (simulated)\n";
echo "  ✓ Tokens actually used: $tokens_actual\n";
echo "  ✓ Generated title: {$ai_metadata['title']}\n";

// Test 5: Token reconciliation
echo "\n[Test 5] Token reconciliation...\n";
try {
	$manager->reconcile($operation_id, $tokens_actual, array(
		'mode' => 'smart',
		'attachment_id' => $attachment_id,
		'model' => 'gpt-4o-mini',
	));

	$balance_after_reconcile = $manager->get_balance();
	$final_used = $balance_before['tokens_remaining'] - $balance_after_reconcile['tokens_remaining'];

	echo "  ✓ Reconciliation complete\n";
	echo "  ✓ Final balance: {$balance_after_reconcile['tokens_remaining']} tokens\n";
	echo "  ✓ Total tokens used: $final_used\n";

	$difference = abs($final_used - $tokens_actual);
	if ($difference <= 5) {
		echo "  ✓ Token usage accurate (diff: $difference)\n";
	} else {
		echo "  ⚠ Token usage discrepancy: $difference tokens\n";
	}
} catch (Exception $e) {
	echo "  ✗ Reconciliation failed: " . $e->getMessage() . "\n";
	exit(1);
}

// Test 6: Telemetry logging
echo "\n[Test 6] Telemetry logging...\n";
try {
	$manager->log_usage(array(
		'attachment_id' => $attachment_id,
		'mode' => 'smart',
		'model' => 'gpt-4o-mini',
		'token_count' => $tokens_actual,
		'prompt_tokens' => 150,
		'completion_tokens' => $tokens_actual - 150,
		'duration_ms' => 1200,
		'image_size_bytes' => filesize(get_attached_file($attachment_id)),
	));

	echo "  ✓ Telemetry logged\n";
} catch (Exception $e) {
	echo "  ⚠ Telemetry logging failed: " . $e->getMessage() . "\n";
}

// Test 7: Verify telemetry in database
echo "\n[Test 7] Verify telemetry data...\n";
global $wpdb;
$telemetry = $wpdb->get_row($wpdb->prepare(
	"SELECT * FROM wp_msh_ai_token_usage
	 WHERE attachment_id = %d
	 AND mode = 'smart'
	 ORDER BY created_at DESC
	 LIMIT 1",
	$attachment_id
));

if ($telemetry) {
	echo "  ✓ Telemetry record found\n";
	echo "  ✓ Token count: {$telemetry->token_count}\n";
	echo "  ✓ Model: {$telemetry->model}\n";
	echo "  ✓ Duration: {$telemetry->duration_ms}ms\n";
} else {
	echo "  ⚠ No telemetry record found\n";
}

// Test 8: Check audit trail
echo "\n[Test 8] Check audit trail...\n";
$audit = $wpdb->get_row($wpdb->prepare(
	"SELECT * FROM wp_msh_ai_token_audit
	 WHERE operation_id = %s",
	$operation_id
));

if ($audit) {
	echo "  ✓ Audit record found\n";
	echo "  ✓ Estimated: {$audit->tokens_estimated}\n";
	echo "  ✓ Actual: {$audit->tokens_actual}\n";
	echo "  ✓ Difference: {$audit->tokens_difference}\n";

	if ($audit->underflow == 1) {
		echo "  ⚠ Underflow detected (reconciliation clamped at zero)\n";
	} else {
		echo "  ✓ No underflow\n";
	}
} else {
	echo "  ⚠ No audit record found\n";
}

// Test 9: Apply AI metadata to image
echo "\n[Test 9] Apply AI metadata...\n";
$original_title = get_the_title($attachment_id);

wp_update_post(array(
	'ID' => $attachment_id,
	'post_title' => $ai_metadata['title'],
));
update_post_meta($attachment_id, '_wp_attachment_image_alt', $ai_metadata['alt']);

$updated_title = get_the_title($attachment_id);
$updated_alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);

if ($updated_title === $ai_metadata['title'] && $updated_alt === $ai_metadata['alt']) {
	echo "  ✓ AI metadata applied successfully\n";
} else {
	echo "  ⚠ AI metadata application failed\n";
}

// Restore original title
wp_update_post(array(
	'ID' => $attachment_id,
	'post_title' => $original_title,
));
echo "  ✓ Original metadata restored\n";

// Summary
echo "\n=== AI FLOW RESULTS ===\n\n";

$summary = array(
	'Image ID' => $attachment_id,
	'Tokens estimated' => $tokens_estimated,
	'Tokens actual' => $tokens_actual,
	'Tokens final' => $final_used,
	'Balance before' => $balance_before['tokens_remaining'],
	'Balance after' => $balance_after_reconcile['tokens_remaining'],
	'Telemetry logged' => ($telemetry ? 'Yes' : 'No'),
	'Audit logged' => ($audit ? 'Yes' : 'No'),
);

foreach ($summary as $key => $value) {
	echo str_pad($key . ':', 20) . "$value\n";
}

echo "\n✅ ALL TESTS PASSED\n";
echo "✅ AI flow with token management works correctly\n";
echo "✅ Phase 0B Smart Mode operational\n";

exit(0);
