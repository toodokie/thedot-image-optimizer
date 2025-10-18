<?php
/**
 * Test Phase 4 integration with optimization workflow
 */

// Find an attachment without manual edits to test
$attachments = get_posts([
    'post_type' => 'attachment',
    'post_status' => 'inherit',
    'posts_per_page' => 50,
    'post_mime_type' => 'image',
    'orderby' => 'ID',
    'order' => 'DESC'
]);

// Find one without manual edits
$test_attachment_id = null;
$versioning = MSH_Metadata_Versioning::get_instance();
$protection = MSH_Manual_Edit_Protection::get_instance();

foreach ($attachments as $attachment) {
    $has_manual = false;
    foreach (['title', 'alt', 'caption', 'description'] as $field) {
        if ($protection->has_manual_edit($attachment->ID, $field)) {
            $has_manual = true;
            break;
        }
    }

    if (!$has_manual) {
        $test_attachment_id = $attachment->ID;
        break;
    }
}

if (!$test_attachment_id) {
    echo "❌ ERROR: Could not find attachment without manual edits\n";
    exit(1);
}

echo "🧪 Testing Phase 4 Integration with Optimization Workflow\n";
echo "=========================================================\n\n";
echo "Test Attachment: #$test_attachment_id - " . get_the_title($test_attachment_id) . "\n\n";

// Count versions before
$before_count = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}msh_optimizer_metadata WHERE media_id = %d",
    $test_attachment_id
));

echo "Versions before optimization: $before_count\n\n";

// Get the optimizer instance and run optimization
$optimizer = MSH_Image_Optimizer::get_instance();
if (!$optimizer) {
    echo "❌ ERROR: Could not get optimizer instance\n";
    exit(1);
}

// Use reflection to access private method
$reflection = new ReflectionClass($optimizer);
$method = $reflection->getMethod('optimize_single_image');
$method->setAccessible(true);

echo "Running optimization...\n";
$result = $method->invoke($optimizer, $test_attachment_id);

if (!empty($result['actions'])) {
    echo "\nActions taken:\n";
    foreach ($result['actions'] as $action) {
        echo "  - $action\n";
    }
}

// Count versions after
$after_count = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}msh_optimizer_metadata WHERE media_id = %d",
    $test_attachment_id
));

echo "\nVersions after optimization: $after_count\n";
echo "New versions created: " . ($after_count - $before_count) . "\n\n";

// Show the versions created
if ($after_count > $before_count) {
    echo "✅ SUCCESS: Versions were saved during optimization!\n\n";

    $versions = $GLOBALS['wpdb']->get_results($GLOBALS['wpdb']->prepare(
        "SELECT field, value, source, version, created_at
         FROM {$GLOBALS['wpdb']->prefix}msh_optimizer_metadata
         WHERE media_id = %d
         ORDER BY created_at DESC
         LIMIT 5",
        $test_attachment_id
    ), ARRAY_A);

    echo "Latest versions:\n";
    foreach ($versions as $version) {
        echo sprintf(
            "  - %s v%d (%s): %s\n",
            $version['field'],
            $version['version'],
            $version['source'],
            substr($version['value'], 0, 60) . (strlen($version['value']) > 60 ? '...' : '')
        );
    }
} else {
    echo "⚠️  No new versions created (fields may have been skipped)\n";
}

echo "\n=========================================================\n";
echo "✅ Phase 4 Integration Test Complete!\n";
