# MSH Image Optimizer

A standalone WordPress plugin for comprehensive image optimization, duplicate detection, SEO-friendly renaming, and WebP delivery.

## Features

### Core Functionality
- **Smart Image Optimization**: Analyze and optimize published images with WebP conversion, ALT text improvements, and SEO metadata
- **Duplicate Detection**: Find and remove duplicate images using MD5 hashing, perceptual hashing, and filename matching
- **Safe Renaming System**: SEO-friendly filename generation with automatic reference tracking and replacement
- **Usage Index**: Comprehensive tracking of image usage across posts, pages, widgets, and theme options
- **WebP Delivery**: Automatic WebP format delivery with browser detection
- **Visual Similarity Scan**: Perceptual hash-based duplicate detection for visually similar images

### Upcoming Features (See [Developer Notes](msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_DEV_NOTES.md#-planned-features--architecture-todo))

**Descriptor-Based Metadata (In Development - ETA: 2 hours)**
- Enhanced filename generation with visual descriptors and business context
- Industry-aware context filtering (healthcare vs non-healthcare)
- Semantic, SEO-optimized slugs: `[keywords]-[business]-[industry]-[location]-[descriptor]`
- Improved meta titles, alt text, captions, and descriptions

**Privacy-First Analytics (Documented - TODO)**
- Local performance tracking: bytes saved, images optimized, reduction percentages
- Optional anonymous aggregate reporting (opt-in only)
- CSV export for client reporting
- Zero PII collection: no filenames, URLs, emails, or IP addresses

**Secure API Key Management (Documented - TODO)**
- Encrypted key storage with OpenSSL (AES-256-GCM)
- Two-phase rotation: test "next" key before promoting to "current"
- Masked display (last 4 characters only)
- Zero-logging policy: keys never appear in logs or error messages

**Paid Plugin Infrastructure (Documented - TODO, Q1 2026)**
- Lightweight licensing via Vercel Edge Functions + Supabase
- Pro/Agency tier support with activation limits
- Native WordPress update system integration
- Lemon Squeezy payment processing

## Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **PHP Extensions**:
  - GD or Imagick (for image processing and perceptual hashing)
  - Standard PHP extensions (fileinfo, json, mbstring)

## Installation

1. Clone or download this repository
2. Copy the `msh-image-optimizer` folder to your WordPress `wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin panel
4. Navigate to **Tools > MSH Image Optimizer** to access the dashboard

## Development & Testing

### ⭐ Symlink Setup (Automatic Sync)

The plugin uses a **symlink** for instant synchronization between the standalone repository and Local test site:

```
Standalone Repository ←→ SYMLINK ←→ Local WordPress Test Site
```

**What this means:**
- ✅ Edit files in standalone repository
- ✅ Changes **instantly** appear on Local test site
- ✅ No manual copying or sync scripts needed

**Documentation:**
- [SYMLINK_SETUP.md](SYMLINK_SETUP.md) - Complete symlink configuration and workflow

**Legacy Sync Documentation** (if symlink not used):
- [SYNC_GUIDE.md](SYNC_GUIDE.md) - Manual sync commands
- [PREVENTING_SYNC_ISSUES.md](PREVENTING_SYNC_ISSUES.md) - Sync automation options

### WP-CLI Testing

The plugin includes comprehensive WP-CLI commands for automated testing:

```bash
# Rename regression test
wp msh rename-regression --ids=123,456

# Full QA suite (rename, optimize, duplicate detection)
wp msh qa --rename=123,456 --optimize=789 --duplicate --duplicate-min-coverage=5

# Duplicate scan only
wp msh qa --duplicate --duplicate-require-groups
```

See [WP_CLI_TEST_RESULTS.md](WP_CLI_TEST_RESULTS.md) for detailed test results and examples.

## Usage

### Step 1: Optimize Published Images

1. **Enable File Renaming** (optional): Toggle on if you want SEO-friendly filename suggestions
2. **Analyze Published Images**: Scans your published content and identifies optimization opportunities
3. **Apply Filename Suggestions**: Applies optimized filenames with automatic reference replacement
4. **Verify WebP Status**: Checks WebP conversion and delivery status

### Step 2: Clean Up Duplicates

1. **Visual Similarity Scan**: Uses perceptual hashing to find visually similar images
2. **Quick Duplicate Scan**: Fast MD5-based exact duplicate detection
3. **Deep Library Scan**: Comprehensive scan including filename-based matches

### Advanced Tools

- **Usage Index Management**: Build, rebuild, or refresh the image usage index
- **Orphan Cleanup**: Remove orphaned index entries for deleted attachments
- **Incremental Refresh**: Queue background index updates

## Architecture

### Core Classes

- `MSH_Image_Optimizer_Plugin` - Main plugin bootstrap
- `MSH_Safe_Rename_System` - File renaming with reference tracking
- `MSH_Image_Usage_Index` - Usage tracking across WordPress content
- `MSH_Perceptual_Hash` - Visual similarity detection
- `MSH_Hash_Cache_Manager` - Efficient hash caching
- `MSH_Media_Cleanup` - Duplicate detection and cleanup
- `MSH_WebP_Delivery` - WebP format delivery system

### Database Tables

The plugin creates custom tables for efficient indexing:
- `{prefix}_msh_image_usage` - Image usage tracking
- `{prefix}_msh_image_lookups` - Fast lookup cache
- `{prefix}_msh_rename_log` - Rename operation audit log

## Development

### Directory Structure

```
msh-image-optimizer/
├── msh-image-optimizer.php    # Main plugin file
├── includes/                   # Core functionality classes
├── admin/                      # Admin interface
├── assets/                     # Frontend assets
│   ├── css/                   # Stylesheets
│   ├── js/                    # JavaScript files
│   └── icons/                 # Plugin icons
└── docs/                      # Documentation
```

### Documentation

- [Complete Documentation](docs/MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md)
- [Developer Notes](docs/MSH_IMAGE_OPTIMIZER_DEV_NOTES.md)
- [Multilanguage Guide](docs/MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md)
- [Research & Development](docs/MSH_IMAGE_OPTIMIZER_RND.md)
- [Migration Plan](docs/MSH_STANDALONE_MIGRATION_PLAN.md)

## Environment Setup

### PHP Extensions

Verify required extensions are enabled:

```bash
php -m | grep -E 'gd|imagick|fileinfo|json|mbstring'
```

### WordPress Cron

The plugin uses WordPress cron for background tasks. Ensure wp-cron is functioning:

```php
// In wp-config.php, avoid:
define('DISABLE_WP_CRON', true);
```

For high-traffic sites, consider using system cron instead:

```bash
*/15 * * * * php /path/to/wordpress/wp-cron.php
```

### Performance Considerations

- **Perceptual Hashing**: CPU-intensive; use "Visual Similarity Scan" during low-traffic periods
- **Index Rebuilds**: For large media libraries (10,000+ images), use "Smart Build" instead of "Force Rebuild"
- **Memory Limits**: Recommend at least 256MB PHP memory for large batches

## License

GPL v2 or later

## Credits

Developed by Main Street Health
Original theme integration extracted and converted to standalone plugin

## Support

For issues, feature requests, or contributions, please visit:
https://github.com/toodokie/thedot-image-optimizer
