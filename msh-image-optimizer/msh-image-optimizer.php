<?php
/**
 * Plugin Name: MSH Image Optimizer
 * Plugin URI: https://github.com/toodokie/thedot-image-optimizer
 * Description: Standalone WordPress image optimization plugin with duplicate detection, SEO-friendly renaming, WebP delivery, and comprehensive usage tracking.
 * Version: 1.2.0
 * Author: Main Street Health
 * Author URI: https://github.com/toodokie
 * Text Domain: msh-image-optimizer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

final class MSH_Image_Optimizer_Plugin {
    const VERSION = '1.2.0';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->load_textdomain(); // Load immediately before includes()
        $this->includes();
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('msh-image-optimizer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function define_constants() {
        if (!defined('MSH_IO_PLUGIN_FILE')) {
            define('MSH_IO_PLUGIN_FILE', __FILE__);
        }
        if (!defined('MSH_IO_PLUGIN_DIR')) {
            define('MSH_IO_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }
        if (!defined('MSH_IO_PLUGIN_URL')) {
            define('MSH_IO_PLUGIN_URL', plugin_dir_url(__FILE__));
        }
        if (!defined('MSH_IO_ASSETS_URL')) {
            define('MSH_IO_ASSETS_URL', trailingslashit(MSH_IO_PLUGIN_URL . 'assets'));
        }
    }

    private function includes() {
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-safe-rename-system.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-url-variation-detector.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-targeted-replacement-engine.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-backup-verification-system.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-hash-cache-manager.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-image-usage-index.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-usage-index-background.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-content-usage-lookup.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-file-resolver.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-debug-logger.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-perceptual-hash.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-safe-rename-cli.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-qa-cli.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-media-cleanup.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-webp-delivery.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-ai-service.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-openai-connector.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-metadata-regeneration-background.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-ai-ajax-handlers.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-metadata-versioning.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-manual-edit-protection.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-image-optimizer.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-context-helper.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/image-optimizer-admin.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/image-optimizer-settings.php';
    }

    public function init() {
        if (function_exists('MSH_Safe_Rename_System::get_instance')) {
            MSH_Safe_Rename_System::get_instance();
        }
        if (class_exists('MSH_Image_Usage_Index')) {
            MSH_Image_Usage_Index::get_instance();
        }
        if (class_exists('MSH_Content_Usage_Lookup')) {
            MSH_Content_Usage_Lookup::get_instance();
        }
        if (class_exists('MSH_Usage_Index_Background')) {
            MSH_Usage_Index_Background::get_instance();
        }
        if (class_exists('MSH_Metadata_Versioning')) {
            MSH_Metadata_Versioning::get_instance();
        }
        if (class_exists('MSH_Manual_Edit_Protection')) {
            MSH_Manual_Edit_Protection::get_instance();
        }
        // Ensure admin assets are enqueued by the admin file.
        do_action('msh_image_optimizer_plugin_loaded');
    }
}

// Instantiate plugin immediately (AJAX handlers need early registration)
MSH_Image_Optimizer_Plugin::instance();
