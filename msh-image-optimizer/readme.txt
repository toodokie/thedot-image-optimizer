=== MSH Image Optimizer ===
Contributors: mainstreethost
Tags: image optimization, seo, alt text, ai metadata, image compression
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Intelligent image optimization with AI-powered metadata generation, WebP conversion, and multilingual support for WordPress media libraries.

== Description ==

MSH Image Optimizer is a comprehensive image optimization plugin that enhances your WordPress media library with AI-powered metadata generation, automatic WebP conversion, and advanced SEO optimization features.

**Key Features:**

* **AI-Powered Metadata Generation** - Automatically generate SEO-optimized titles, alt text, captions, and descriptions using OpenAI's GPT-4 Vision
* **Multi-Locale Support** - Generate metadata in multiple languages (English, Spanish, French, and more) with Polylang/WPML integration
* **Metadata Versioning** - Track all metadata changes with version history and automatic manual edit protection
* **WebP Conversion** - Automatically convert JPEG and PNG images to WebP format for better performance
* **Perceptual Hash Analysis** - Detect duplicate images across your media library
* **Usage Index** - Track where images are used across your site (posts, pages, widgets)
* **Batch Optimization** - Process multiple images at once with priority-based queuing
* **Context-Aware Metadata** - Generate metadata based on your business context, industry, and location
* **Template System** - Use predefined templates for consistent metadata across your images
* **Manual Edit Protection** - Automatically detects and preserves user-edited metadata
* **WP-CLI Support** - Command-line tools for bulk operations and automation

**Perfect For:**

* Multi-location businesses managing large image libraries
* Multilingual websites requiring localized image metadata
* SEO professionals optimizing image search visibility
* Healthcare providers, service businesses, and local companies
* Developers managing client sites with diverse image needs

**AI Integration:**

This plugin integrates with OpenAI's GPT-4 Vision API to analyze images and generate contextually relevant metadata. You'll need an OpenAI API key to use AI features.

== Installation ==

1. Upload the `msh-image-optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Media > Image Optimizer in your WordPress admin
4. Configure your settings:
   - Add your OpenAI API key (optional, for AI features)
   - Set up your business context (name, location, industry)
   - Choose your optimization preferences
5. Start optimizing! You can process images individually or in batches

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

An OpenAI API key is required only if you want to use AI-powered metadata generation. You can still use template-based metadata, WebP conversion, and other features without an API key.

= Does this plugin work with multilingual sites? =

Yes! MSH Image Optimizer has full integration with Polylang and WPML. You can generate metadata in multiple languages for the same image.

= Will it overwrite my manual edits? =

No. The plugin includes automatic manual edit protection. Once you manually edit any metadata field, the plugin will preserve your changes and not overwrite them with AI-generated content (unless you explicitly force regeneration).

= How does the version history work? =

Every metadata change is recorded with a version number, timestamp, source (AI, manual, template), and checksum. You can view the complete history and compare versions at any time.

= Can I use this with existing images? =

Yes! The plugin can optimize your entire existing media library. Use the batch optimization feature to process hundreds or thousands of images at once.

= Does it create WebP files automatically? =

Yes, if you enable WebP conversion, the plugin will automatically create WebP versions of your JPEG and PNG images and serve them to browsers that support WebP.

= Is there a file size limit? =

The plugin respects WordPress's upload_max_filesize and post_max_size settings. Very large images (>10MB) may require increased PHP memory limits.

= Can I revert to a previous version of metadata? =

Yes. The version history feature allows you to view all previous versions and see exactly what changed and when.

== Screenshots ==

1. Main optimization dashboard with batch processing controls
2. AI-powered metadata generation with preview
3. Multi-locale metadata management
4. Version history showing all metadata changes
5. Usage index showing where images are used
6. Context profile configuration
7. WebP conversion status and controls

== Changelog ==

= 1.2.0 =
* Added: Phase 4 Metadata Versioning system with complete version history
* Added: Automatic manual edit protection
* Added: AI vs manual diff comparison
* Added: Multi-locale version tracking
* Fixed: PHP 8.4 nullable parameter deprecation warnings
* Fixed: Text-domain loading timing
* Improved: PHPCS WordPress Coding Standards compliance (44,401 auto-fixes)
* Improved: Public API documentation

= 1.1.0 =
* Added: Perceptual hash analysis for duplicate detection
* Added: Image usage index tracking
* Added: Multi-locale support with Polylang/WPML integration
* Added: Template-based metadata generation
* Added: WebP conversion with fallback support
* Improved: AI metadata generation with GPT-4 Vision
* Improved: Batch processing performance

= 1.0.0 =
* Initial release
* Basic image optimization
* OpenAI GPT-4 Vision integration
* Context-aware metadata generation
* WP-CLI commands

== Upgrade Notice ==

= 1.2.0 =
Major update with metadata versioning and manual edit protection. All metadata changes are now tracked with complete version history. Automatic detection of manual edits prevents AI from overwriting your custom metadata.

= 1.1.0 =
Adds powerful new features including perceptual hash duplicate detection, usage tracking, and multi-locale support. Recommended for all users.

== Privacy Policy ==

This plugin integrates with OpenAI's GPT-4 Vision API when AI features are enabled. When you use AI-powered metadata generation:

* Images are sent to OpenAI's servers for analysis
* No personal data is included in API requests
* OpenAI's privacy policy applies to image analysis
* You can disable AI features at any time

Local features (templates, WebP conversion, usage tracking, versioning) do not send any data to external services.

== Third-Party Services ==

**OpenAI API** (optional)
* Service: GPT-4 Vision API for AI-powered metadata generation
* Terms of Service: https://openai.com/policies/terms-of-use
* Privacy Policy: https://openai.com/policies/privacy-policy
* Data sent: Image files and business context (no personal data)
* When: Only when using AI metadata generation features

No other third-party services are used.

== Support ==

For support, bug reports, or feature requests:
* GitHub: https://github.com/toodokie/thedot-image-optimizer
* Email: support@mainstreethost.com

== Credits ==

Developed by Main Street Host
