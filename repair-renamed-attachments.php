#!/usr/bin/env php
<?php
/**
 * Repair script for attachments with mismatched _wp_attached_file meta
 *
 * Run with: wp eval-file repair-renamed-attachments.php
 */

$broken_ids = array( 2068, 2067, 2064, 2062, 2060, 2059, 2058, 2057, 2054, 2053, 2051, 968, 1628 );

$upload_dir = wp_upload_dir();
$basedir = $upload_dir['basedir'];

$fixed_count = 0;
$skipped_count = 0;

foreach ( $broken_ids as $attachment_id ) {
	echo "\n=== Attachment $attachment_id ===\n";

	// Get current meta value
	$current_meta = get_post_meta( $attachment_id, '_wp_attached_file', true );
	echo "Current meta: $current_meta\n";

	// Use exec to run find command (more reliable than glob)
	$cmd = sprintf(
		'find %s -name "*-%d.*" ! -name "*-[0-9]*x[0-9]*.*" 2>/dev/null',
		escapeshellarg( $basedir ),
		$attachment_id
	);
	exec( $cmd, $files, $return_code );

	if ( empty( $files ) ) {
		echo "ERROR: No file found for attachment $attachment_id\n";
		$skipped_count++;
		continue;
	}

	// Get first match (should be the main file, not thumbnails)
	$actual_file = $files[0];
	$relative_path = str_replace( trailingslashit( $basedir ), '', $actual_file );

	echo "Actual file: $relative_path\n";

	if ( $current_meta === $relative_path ) {
		echo "SKIPPED: Meta already correct\n";
		$skipped_count++;
		continue;
	}

	// Update the meta
	update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );
	echo "FIXED: Updated meta from '$current_meta' to '$relative_path'\n";
	$fixed_count++;
}

echo "\n\n=== SUMMARY ===\n";
echo "Fixed: $fixed_count\n";
echo "Skipped: $skipped_count\n";
echo "Total: " . count( $broken_ids ) . "\n";
