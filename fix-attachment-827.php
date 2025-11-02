<?php
/**
 * Fix corrupted attachment #827 metadata
 * Run via: wp eval-file fix-attachment-827.php
 */

$attachment_id = 827;

// Get current metadata
$metadata = wp_get_attachment_metadata( $attachment_id );

echo "BEFORE:\n";
echo "  Main file: " . $metadata['file'] . "\n";
if ( ! empty( $metadata['sizes'] ) ) {
	foreach ( $metadata['sizes'] as $size_name => $size_data ) {
		echo "  Size {$size_name}: " . $size_data['file'] . "\n";
	}
}

// Fix main file: TEST-TEST-TEST-city-street-v2.jpg -> TEST-city-street-v2.jpg
$metadata['file'] = '2010/08/TEST-city-street-v2.jpg';

// Fix sizes: TEST-TEST-city-street-v2-300x225.jpg -> TEST-city-street-v2-300x225.jpg
if ( ! empty( $metadata['sizes'] ) ) {
	foreach ( $metadata['sizes'] as $size_name => $size_data ) {
		if ( ! empty( $size_data['file'] ) ) {
			// Collapse repeated prefixes
			$metadata['sizes'][ $size_name ]['file'] = preg_replace( '/^(([A-Z0-9]+)-)\1+/i', '$1', $size_data['file'] );
		}
	}
}

// Update metadata
wp_update_attachment_metadata( $attachment_id, $metadata );

// Verify
$fixed_metadata = wp_get_attachment_metadata( $attachment_id );
echo "\nAFTER:\n";
echo "  Main file: " . $fixed_metadata['file'] . "\n";
if ( ! empty( $fixed_metadata['sizes'] ) ) {
	foreach ( $fixed_metadata['sizes'] as $size_name => $size_data ) {
		echo "  Size {$size_name}: " . $size_data['file'] . "\n";
	}
}

echo "\n✅ Fixed attachment #827\n";
