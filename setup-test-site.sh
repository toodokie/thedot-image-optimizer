#!/bin/bash

# Setup script for thedot-optimizer-test site
# Run this from Local's site shell: Right-click site > Open Site Shell

echo "Setting up test content for thedot-optimizer-test..."

# Navigate to WordPress root
cd ~/Local\ Sites/thedot-optimizer-test/app/public

# Install and activate WordPress Importer
echo "Installing WordPress Importer plugin..."
wp plugin install wordpress-importer --activate

# Download WordPress test data
echo "Downloading WordPress theme unit test data..."
curl -O https://raw.githubusercontent.com/WPTT/theme-unit-test/master/themeunittestdata.wordpress.xml

# Import the test data
echo "Importing test content..."
wp import themeunittestdata.wordpress.xml --authors=create

# Clean up
rm themeunittestdata.wordpress.xml

echo "✅ Test content imported successfully!"
echo "You now have ~30 posts with images to test the optimizer."
echo ""
echo "Next steps:"
echo "1. Go to WP Admin"
echo "2. Navigate to Tools > MSH Image Optimizer"
echo "3. Click 'Analyze Published Images'"
