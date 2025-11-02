<?php
/**
 * Fix corrupted attachment #1022 metadata
 */

$attachment_id = 1022;

echo "Fixing attachment #$attachment_id\n";

// Fix _wp_attached_file
update_post_meta( $attachment_id, '_wp_attached_file', '2013/03/TEST-service-icon-horizontal.jpg' );

// Fix metadata
$metadata = wp_get_attachment_metadata( $attachment_id );
$metadata['file'] = '2013/03/TEST-service-icon-horizontal.jpg';

if ( ! empty( $metadata['sizes'] ) ) {
	foreach ( $metadata['sizes'] as $size_name => $size_data ) {
		if ( ! empty( $size_data['file'] ) ) {
			$metadata['sizes'][ $size_name ]['file'] = preg_replace( '/^(([A-Z0-9]+)-)\1+/i', '$1', $size_data['file'] );
		}
	}
}

wp_update_attachment_metadata( $attachment_id, $metadata );

// Verify
$fixed_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
$fixed_meta = wp_get_attachment_metadata( $attachment_id );

echo "  _wp_attached_file: $fixed_file\n";
echo "  metadata['file']: {$fixed_meta['file']}\n";

echo "✅ Fixed!\n";
