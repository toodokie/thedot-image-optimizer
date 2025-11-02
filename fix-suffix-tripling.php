#!/usr/bin/env php
<?php
/**
 * Fix Suffix Tripling Bug - Repair Script
 *
 * Fixes attachments #616, #617, #754 that have suffix-tripled filenames
 * from the WebP conversion bug (Oct 17, 2025).
 *
 * Issue: Database points to .jpg files with tripled suffixes, but actual files
 * on disk are .webp with tripled suffixes.
 *
 * Fix Strategy:
 * 1. Update database to point to existing .webp files with correct paths
 * 2. Normalize thumbnail filenames in metadata (remove one level of suffix duplication)
 * 3. Update _wp_attached_file meta
 * 4. Update post_mime_type to image/webp
 */

// Load WordPress
$wp_load = '/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-load.php';
if (!file_exists($wp_load)) {
	die("ERROR: Cannot find wp-load.php at: $wp_load\n");
}
require_once $wp_load;

echo "===== MSH Suffix Tripling Repair Script =====\n\n";

// Define corrupted attachments
$corrupted_attachments = [
	[
		'id' => 616,
		'old_path' => '2008/06/lettuce-field-sunrise-main-main-main.jpg',
		'new_path' => '2008/06/lettuce-field-sunrise-main-main-main.webp',
		'expected_basename' => 'lettuce-field-sunrise-main',
	],
	[
		'id' => 617,
		'old_path' => '2008/06/serene-forest-landscape-main-main-main.jpg',
		'new_path' => '2008/06/serene-forest-landscape-main-main-main.webp',
		'expected_basename' => 'serene-forest-landscape-main',
	],
	[
		'id' => 754,
		'old_path' => '2008/06/red-pier-structure-testimonial-testimonial-testimonial.jpg',
		'new_path' => '2008/06/red-pier-structure-testimonial-testimonial-testimonial.webp',
		'expected_basename' => 'red-pier-structure-testimonial',
	],
];

// Normalization function (matches the updated guard pattern)
function normalize_suffix_tripling($basename) {
	// Remove suffix tripling like "file-main-main-main.ext" → "file-main.ext"
	if (preg_match('/^(.+?)(-([a-z0-9]+)(?:-\3)+)(\.[a-z0-9]+)$/i', $basename, $m)) {
		return $m[1] . '-' . $m[3] . $m[4];
	}
	return $basename;
}

foreach ($corrupted_attachments as $att) {
	$id = $att['id'];
	echo "Processing attachment #{$id}...\n";

	// Get current state
	$current_file = get_post_meta($id, '_wp_attached_file', true);
	$current_meta = wp_get_attachment_metadata($id);
	$upload_dir = wp_upload_dir();
	$base_path = $upload_dir['basedir'];

	echo "  Current _wp_attached_file: $current_file\n";
	echo "  Expected new path: {$att['new_path']}\n";

	// Check if WebP file exists
	$webp_full_path = $base_path . '/' . $att['new_path'];
	if (!file_exists($webp_full_path)) {
		echo "  ❌ ERROR: WebP file not found at: $webp_full_path\n";
		continue;
	}

	echo "  ✓ WebP file exists: $webp_full_path\n";

	// Rename the WebP file to normalized name
	$normalized_path = str_replace(
		basename($att['new_path']),
		$att['expected_basename'] . '.webp',
		$att['new_path']
	);
	$normalized_full_path = $base_path . '/' . $normalized_path;

	if (rename($webp_full_path, $normalized_full_path)) {
		echo "  ✓ Renamed WebP file: {$att['new_path']} → $normalized_path\n";
	} else {
		echo "  ❌ ERROR: Failed to rename WebP file\n";
		continue;
	}

	// Update _wp_attached_file to point to normalized WebP
	update_post_meta($id, '_wp_attached_file', $normalized_path);
	echo "  ✓ Updated _wp_attached_file\n";

	// Update metadata
	if (!empty($current_meta)) {
		// Update main file path
		$current_meta['file'] = $att['new_path'];

		// Normalize thumbnail filenames
		if (!empty($current_meta['sizes']) && is_array($current_meta['sizes'])) {
			foreach ($current_meta['sizes'] as $size_name => $size_data) {
				if (!empty($size_data['file'])) {
					$old_thumb = $size_data['file'];
					$new_thumb = normalize_suffix_tripling($old_thumb);

					if ($old_thumb !== $new_thumb) {
						$current_meta['sizes'][$size_name]['file'] = $new_thumb;
						echo "  ✓ Normalized thumbnail: $old_thumb → $new_thumb\n";
					}
				}
			}
		}

		// Update metadata
		wp_update_attachment_metadata($id, $current_meta);
		echo "  ✓ Updated attachment metadata\n";
	}

	// Update post_mime_type to image/webp
	wp_update_post([
		'ID' => $id,
		'post_mime_type' => 'image/webp',
	]);
	echo "  ✓ Updated mime type to image/webp\n";

	// Verify
	$verify_file = get_post_meta($id, '_wp_attached_file', true);
	$verify_meta = wp_get_attachment_metadata($id);
	$verify_post = get_post($id);

	echo "  Verification:\n";
	echo "    _wp_attached_file: $verify_file\n";
	echo "    metadata['file']: {$verify_meta['file']}\n";
	echo "    post_mime_type: {$verify_post->post_mime_type}\n";

	if ($verify_file === $normalized_path &&
	    $verify_meta['file'] === $normalized_path &&
	    $verify_post->post_mime_type === 'image/webp' &&
	    file_exists($normalized_full_path)) {
		echo "  ✅ Attachment #{$id} repaired successfully!\n\n";
	} else {
		echo "  ⚠️  Verification mismatch for #{$id}\n\n";
	}
}

echo "===== Repair Complete =====\n";
echo "\nThe WebP files have been renamed to normalized names (suffix tripling removed).\n";
echo "The database has been updated to match the new filenames.\n";
echo "The guards will prevent this from happening in future operations.\n";
