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
        $this->includes();
        add_action('plugins_loaded', [$this, 'init']);
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
        // Dev mode - enables Pro features for testing without license
        if (!defined('MSH_DEV_MODE')) {
            define('MSH_DEV_MODE', defined('WP_DEBUG') && WP_DEBUG);
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

        // Phase 4 - Advanced metadata governance.
        require_once MSH_IO_PLUGIN_DIR . 'includes/phase4/class-msh-version-manager.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/phase4/class-msh-ab-testing.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/phase4/class-msh-approval-workflow.php';
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once MSH_IO_PLUGIN_DIR . 'includes/phase4/class-msh-phase4-cli.php';
        }

        // Context Fusion Layer (Phase 2)
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-database.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-manager.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-extractor.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-intent-classifier.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-keyword-normalizer.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-processor.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-ai-integration.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-recommender.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-analytics.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-snapshots.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-batch-scheduler.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-performance.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-i18n-database.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-i18n-metadata.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-i18n-integration.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-translation-analyzer.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/context-fusion/class-msh-context-cli.php';

        // AI Translation & Cultural Adaptation (Phase 3)
        require_once MSH_IO_PLUGIN_DIR . 'includes/ai-translation/class-msh-locale-database.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/ai-translation/class-msh-locale-profile-manager.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/ai-translation/class-msh-prompt-template.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/ai-translation/class-msh-metadata-validator.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/ai-translation/class-msh-locale-cli.php';

        // Phase 5+9: Automation & Enterprise Infrastructure
        require_once MSH_IO_PLUGIN_DIR . 'includes/automation/class-msh-database-schema.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/automation/class-msh-job-engine.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/automation/class-msh-regeneration-worker.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/automation/class-msh-queue-manager.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/automation/class-msh-automation-triggers.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/automation/class-msh-metrics-collector.php';

        // Phase 5+9: Enterprise Features (License, Telemetry, Remote Sync)
        require_once MSH_IO_PLUGIN_DIR . 'includes/enterprise/class-msh-license-manager.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/enterprise/class-msh-telemetry.php';
        require_once MSH_IO_PLUGIN_DIR . 'includes/enterprise/class-msh-remote-sync.php';

        // Initialize automation infrastructure
        MSH_Database_Schema::get_instance();
        MSH_Job_Engine::get_instance();
        MSH_Queue_Manager::get_instance();
        MSH_Automation_Triggers::get_instance();
        MSH_Metrics_Collector::get_instance();

        // Initialize enterprise features
        MSH_License_Manager::get_instance();
        MSH_Telemetry::get_instance();
        MSH_Remote_Sync::get_instance();

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-database-cli.php';
            require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-jobs-cli.php';
        }

        // Phase 5+9: Helper functions (Public API for frontend)
        require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-helper-functions.php';

        // Admin menu structure (must load first, priority 5)
        require_once MSH_IO_PLUGIN_DIR . 'admin/class-msh-optimizer-menu.php';

        // Admin pages
        require_once MSH_IO_PLUGIN_DIR . 'admin/dashboard-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/glossary-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/locale-profiles-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/class-msh-hub-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/image-optimizer-admin.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/image-optimizer-settings.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/context-fusion-admin.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/context-analytics-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/version-history-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/ab-testing-page.php';
        require_once MSH_IO_PLUGIN_DIR . 'admin/approval-queue-page.php';
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
        if (class_exists('MSH_Context_Manager')) {
            MSH_Context_Manager::get_instance();
        }
        if (class_exists('MSH_Context_Processor')) {
            MSH_Context_Processor::get_instance();
        }
        if (class_exists('MSH_Context_AI_Integration')) {
            MSH_Context_AI_Integration::get_instance();
        }
        if (class_exists('MSH_I18n_Metadata')) {
            MSH_I18n_Metadata::get_instance();
        }
        // Phase 5+9: Initialize Queue Manager (schedules cron jobs)
        if (class_exists('MSH_Queue_Manager')) {
            MSH_Queue_Manager::get_instance();
        }
        if (class_exists('MSH_I18n_Integration')) {
            MSH_I18n_Integration::get_instance();
        }
        if (class_exists('MSH_Context_Snapshots')) {
            MSH_Context_Snapshots::get_instance();
        }
        if (class_exists('MSH_Context_Batch_Scheduler')) {
            MSH_Context_Batch_Scheduler::get_instance();
        }
        if (class_exists('MSH_Context_Performance')) {
            MSH_Context_Performance::get_instance();
        }
        if (class_exists('MSH_Version_Manager')) {
            MSH_Version_Manager::get_instance();
        }
        if (class_exists('MSH_AB_Testing')) {
            MSH_AB_Testing::get_instance();
        }
        if (class_exists('MSH_Approval_Workflow')) {
            MSH_Approval_Workflow::get_instance();
        }
        if (class_exists('MSH_Context_Fusion_Admin') && is_admin()) {
            MSH_Context_Fusion_Admin::get_instance();
        }
        // Phase 3: AI Translation & Cultural Adaptation
        if (class_exists('MSH_Locale_Database')) {
            MSH_Locale_Database::get_instance();
        }
        if (class_exists('MSH_Locale_Profile_Manager')) {
            MSH_Locale_Profile_Manager::get_instance();
        }
        if (class_exists('MSH_Prompt_Template')) {
            MSH_Prompt_Template::get_instance();
        }
        if (class_exists('MSH_Metadata_Validator')) {
            MSH_Metadata_Validator::get_instance();
        }
        // Ensure admin assets are enqueued by the admin file.
        do_action('msh_image_optimizer_plugin_loaded');
    }
}

// Instantiate plugin immediately (AJAX handlers need early registration)
MSH_Image_Optimizer_Plugin::instance();

/**
 * Activation hook - Create database tables
 */
function msh_image_optimizer_activate() {
    // Create context fusion table
    if ( class_exists( 'MSH_Context_Database' ) ) {
        MSH_Context_Database::init();
    }
}
register_activation_hook( __FILE__, 'msh_image_optimizer_activate' );

/**
 * Deactivation hook - Cleanup (but keep data)
 */
function msh_image_optimizer_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook( 'msh_ctx_update_post_context' );
}

/**
 * Register WP-CLI commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    if ( class_exists( 'MSH_Context_CLI' ) ) {
        WP_CLI::add_command( 'msh context', 'MSH_Context_CLI' );
    }
    if ( class_exists( 'MSH_Locale_CLI' ) ) {
        WP_CLI::add_command( 'msh locale', 'MSH_Locale_CLI' );
    }
    if ( class_exists( 'MSH_Version_CLI' ) ) {
        WP_CLI::add_command( 'msh version', 'MSH_Version_CLI' );
    }
    if ( class_exists( 'MSH_AB_Testing_CLI' ) ) {
        WP_CLI::add_command( 'msh ab', 'MSH_AB_Testing_CLI' );
    }
    if ( class_exists( 'MSH_Jobs_CLI' ) ) {
        WP_CLI::add_command( 'msh jobs', 'MSH_Jobs_CLI' );
    }
}
