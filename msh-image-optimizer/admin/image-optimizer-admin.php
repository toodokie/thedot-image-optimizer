<?php
/**
 * MSH Image Optimizer Admin Interface - COMPLETE ORIGINAL VERSION
 * WordPress admin interface for image optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_Image_Optimizer_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_media_page(
            'The Dot Image Optimizer',
            'Image Optimizer',
            'manage_options',
            'msh-image-optimizer',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('media_page_msh-image-optimizer' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'msh-image-optimizer-fonts',
            'https://use.typekit.net/gac6jnd.css',
            array(),
            null
        );

        $index_summary = null;
        if (class_exists('MSH_Image_Usage_Index')) {
            $usage_index = MSH_Image_Usage_Index::get_instance();
            $stats = $usage_index->get_index_stats();
            $formatted = $usage_index->format_stats_for_ui($stats);
            if ($formatted) {
                $index_summary = $formatted;
            }
        }

        wp_enqueue_script(
            'msh-image-optimizer-modern',
            trailingslashit(MSH_IO_ASSETS_URL) . 'js/image-optimizer-modern.js',
            array('jquery'),
            MSH_Image_Optimizer_Plugin::VERSION,
            true
        );
        
        wp_localize_script('msh-image-optimizer-modern', 'mshImageOptimizer', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msh_image_optimizer'),
            'cleanup_nonce' => wp_create_nonce('msh_media_cleanup'),
            'renameEnabled' => get_option('msh_enable_file_rename', '0'),
            'renameToggleNonce' => wp_create_nonce('msh_toggle_file_rename'),
            'indexStats' => $index_summary,
            'strings' => array(
                'analyzing' => __('Analyzing images...', 'msh-image-optimizer'),
                'optimizing' => __('Optimizing images...', 'msh-image-optimizer'),
                'complete' => __('Optimization complete!', 'msh-image-optimizer'),
                'error' => __('An error occurred. Please try again.', 'msh-image-optimizer'),
                'indexHealthy' => __('Healthy', 'msh-image-optimizer'),
                'indexQueued' => __('Queued', 'msh-image-optimizer'),
                'indexAttention' => __('Attention', 'msh-image-optimizer'),
                'indexNotBuilt' => __('Not Built', 'msh-image-optimizer'),
                'queueWarning' => __('Background indexing in progress - attachments queued for processing', 'msh-image-optimizer'),
                'queueInfo' => __('Background refresh queued; no action needed unless jobs pile up.', 'msh-image-optimizer'),
                'orphanWarning' => __('Orphaned entries detected - references to deleted attachments', 'msh-image-optimizer'),
                'viewOrphans' => __('View Orphan List', 'msh-image-optimizer'),
                'hideOrphans' => __('Hide Orphan List', 'msh-image-optimizer'),
                'noOrphans' => __('No orphaned attachments detected.', 'msh-image-optimizer'),
                'derivedHeading' => __('Derived copies (alternate formats)', 'msh-image-optimizer'),
                'derivedInfo' => __('Alternate formats detected; these mirror another attachment.', 'msh-image-optimizer')
            )
        ));
        
        wp_enqueue_style(
            'msh-image-optimizer-admin',
            trailingslashit(MSH_IO_ASSETS_URL) . 'css/image-optimizer-admin.css',
            array('msh-image-optimizer-fonts'),
            MSH_Image_Optimizer_Plugin::VERSION
        );
    }
    
    /**
     * Admin page content - COMPLETE ORIGINAL VERSION
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: 1: brand name, 2: emphasized product name. */
                        __('%1$s %2$s', 'msh-image-optimizer'),
                        '<span class="msh-title-brand">' . esc_html__('The Dot', 'msh-image-optimizer') . '</span>',
                        '<em>' . esc_html__('Image Optimizer', 'msh-image-optimizer') . '</em>'
                    )
                );
                ?>
            </h1>
            
            <div class="msh-optimizer-dashboard">
                
                <!-- WebP Delivery Status -->
                <div class="msh-webp-status-section">
                    <h2><?php _e('WebP Delivery Status', 'msh-image-optimizer'); ?></h2>
                    <div id="webp-status-display">
                        <div class="webp-status-item">
                            <span class="status-label"><?php _e('Browser Support:', 'msh-image-optimizer'); ?></span>
                            <span id="webp-browser-support" class="status-value">Detecting...</span>
                        </div>
                        <div class="webp-status-item">
                            <span class="status-label"><?php _e('Detection Method:', 'msh-image-optimizer'); ?></span>
                            <span id="webp-detection-method" class="status-value">JavaScript + Cookie</span>
                        </div>
                        <div class="webp-status-item">
                            <span class="status-label"><?php _e('Delivery Status:', 'msh-image-optimizer'); ?></span>
                            <span id="webp-delivery-status" class="status-value">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Progress Overview -->
                <div class="msh-progress-section">
                    <h2><?php _e('Image Optimization Progress', 'msh-image-optimizer'); ?></h2>
                    <p style="margin-bottom: 15px; color: #666; font-size: 14px;">
                        <strong>Image Optimization:</strong> Converts images to WebP, adds ALT text, improves SEO metadata for published images.<br>
                        <strong>Duplicate Cleanup:</strong> Removes unused duplicate files to clean up media library (separate process).
                    </p>
                    <div class="progress-stats">
                        <div class="stat-box">
                            <span class="stat-number" id="total-images">-</span>
                            <span class="stat-label"><?php _e('Total Published Images', 'msh-image-optimizer'); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-number" id="optimized-images">-</span>
                            <span class="stat-label"><?php _e('Optimized', 'msh-image-optimizer'); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-number" id="remaining-images">-</span>
                            <span class="stat-label"><?php _e('Remaining', 'msh-image-optimizer'); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-number" id="progress-percentage">-</span>
                            <span class="stat-label"><?php _e('Complete', 'msh-image-optimizer'); ?></span>
                        </div>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                        <span class="progress-percent" id="progress-percent">0%</span>
                    </div>
                    <div class="progress-status" id="progress-status">Waiting for analysis…</div>
                </div>

                <!-- Step 1: Image Optimization -->
                <div class="msh-actions-section">
                    <h2 style="color: #35332f;"><?php _e('Step 1: Optimize Published Images', 'msh-image-optimizer'); ?></h2>
                    <p style="margin-bottom: 15px; color: #35332f; font-size: 14px; background: #faf9f6; padding: 10px; border-radius: 4px;">
                        <strong>RECOMMENDED FIRST:</strong> Optimize your published images with WebP conversion, proper ALT text, and SEO improvements before cleaning duplicates.
                    </p>
                    <p class="msh-inline-note" style="margin-top: 4px;"><em><?php _e('We scan published content (pages, posts, widgets) and include images that are in use, plus auto-include newer SVG icons so they never get missed.', 'msh-image-optimizer'); ?></em></p>
                    <p class="msh-inline-note"><em><?php _e('Smart Indexing: Files are indexed automatically when renamed for optimal performance', 'msh-image-optimizer'); ?></em></p>
                    <div class="msh-rename-settings-section step-rename-settings">
                        <div class="rename-important-callout">
                            <strong><?php _e('File renaming powers Step 1', 'msh-image-optimizer'); ?></strong>
                            <p><?php _e('Turn this on when you want Analyze & Apply to generate clean, SEO-friendly filenames. Leave it off for audit-only runs where URLs must stay untouched.', 'msh-image-optimizer'); ?></p>
                            <p><?php _e('We check this toggle before every optimization task, so you are always in control of when filename updates happen.', 'msh-image-optimizer'); ?></p>
                        </div>
                        <div class="rename-setting-card">
                            <div class="rename-setting-content">
                                <label class="rename-toggle-wrapper">
                                    <input type="checkbox" id="enable-file-rename" class="rename-toggle-checkbox"
                                           <?php checked(get_option('msh_enable_file_rename', '0'), '1'); ?>>
                                    <span class="rename-toggle-slider"></span>
                                    <div class="rename-toggle-text">
                                        <strong><?php _e('Enable File Renaming', 'msh-image-optimizer'); ?></strong>
                                        <span class="rename-toggle-description">
                                            <?php _e('Provides optimized filenames when Apply Suggestions runs. Requires usage index to prevent broken links.', 'msh-image-optimizer'); ?>
                                        </span>
                                    </div>
                                </label>
                                <div id="rename-status-indicator" class="rename-status">
                                    <span class="rename-status-text">
                                        <?php
                                        $rename_enabled = get_option('msh_enable_file_rename', '0') === '1';
                                        $index_built = get_option('msh_usage_index_last_build') !== false;

                                        if ($rename_enabled && $index_built) {
                                            echo '<span class="status-ready">' . __('✓ Ready for renaming', 'msh-image-optimizer') . '</span>';
                                        } elseif ($rename_enabled && !$index_built) {
                                            echo '<span class="status-pending">' . __('⚠ Index required', 'msh-image-optimizer') . '</span>';
                                        } else {
                                            echo '<span class="status-disabled">' . __('Renaming disabled', 'msh-image-optimizer') . '</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="action-buttons step-actions">
                        <button id="analyze-images" class="button button-dot-primary">
                            <?php _e('Analyze Published Images', 'msh-image-optimizer'); ?>
                        </button>
                        <button id="apply-filename-suggestions" class="button button-dot-primary" disabled>
                            <?php _e('Apply Filename Suggestions', 'msh-image-optimizer'); ?>
                        </button>
                        <div class="step-actions__secondary">
                            <button id="verify-webp-status" class="button button-dot-secondary">
                                <?php _e('Verify WebP Status', 'msh-image-optimizer'); ?>
                            </button>
                            <button id="reset-optimization" class="button button-dot-secondary">
                                <?php _e('Reset Optimization Flags', 'msh-image-optimizer'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Optimization Activity Log -->
                <div class="msh-log-section step1-log" style="display: none;">
                    <h3><?php _e('Optimization Activity', 'msh-image-optimizer'); ?></h3>
                    <div class="log-container">
                        <textarea id="optimization-log" readonly placeholder="<?php _e('Optimization activity will appear here...', 'msh-image-optimizer'); ?>"></textarea>
                    </div>
                </div>

                <!-- Results Display -->
                <div class="msh-results-section" style="display: none;">
                    <h2 class="results-title"><?php _e('Analysis Results', 'msh-image-optimizer'); ?></h2>

                    <!-- Modern Filters -->
                    <div class="filter-controls">
                        <div class="filter-group">
                            <label class="filter-label"><?php _e('Status:', 'msh-image-optimizer'); ?></label>
                            <select class="filter-control filter-select" data-filter-type="status">
                                <option value="all"><?php _e('All Images', 'msh-image-optimizer'); ?></option>
                                <option value="needs_optimization"><?php _e('Needs Optimization', 'msh-image-optimizer'); ?></option>
                                <option value="optimized"><?php _e('Optimized', 'msh-image-optimizer'); ?></option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label"><?php _e('Priority:', 'msh-image-optimizer'); ?></label>
                            <select class="filter-control filter-select" data-filter-type="priority">
                                <option value="all"><?php _e('All Priorities', 'msh-image-optimizer'); ?></option>
                                <option value="high"><?php _e('High (15+)', 'msh-image-optimizer'); ?></option>
                                <option value="medium"><?php _e('Medium (10-14)', 'msh-image-optimizer'); ?></option>
                                <option value="low"><?php _e('Low (0-9)', 'msh-image-optimizer'); ?></option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label"><?php _e('Filename:', 'msh-image-optimizer'); ?></label>
                            <select class="filter-control filter-select" data-filter-type="filename">
                                <option value="all"><?php _e('All Files', 'msh-image-optimizer'); ?></option>
                                <option value="has_suggestion"><?php _e('Has Filename Suggestion', 'msh-image-optimizer'); ?></option>
                                <option value="no_suggestion"><?php _e('No Filename Suggestion', 'msh-image-optimizer'); ?></option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label"><?php _e('Issues:', 'msh-image-optimizer'); ?></label>
                            <select class="filter-control filter-select" data-filter-type="issues">
                                <option value="all"><?php _e('All Issues', 'msh-image-optimizer'); ?></option>
                                <option value="missing_alt"><?php _e('Missing ALT Text', 'msh-image-optimizer'); ?></option>
                                <option value="no_webp"><?php _e('No WebP', 'msh-image-optimizer'); ?></option>
                                <option value="large_size"><?php _e('Large File Size', 'msh-image-optimizer'); ?></option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <span class="results-count" id="results-count">0 images</span>
                            <button id="clear-filters" class="button button-secondary"><?php _e('Clear', 'msh-image-optimizer'); ?></button>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bulk-actions">
                        <label class="select-all-label">
                            <input type="checkbox" id="select-all" class="select-all-checkbox">
                            <?php _e('Select All', 'msh-image-optimizer'); ?>
                        </label>
                        <button id="optimize-selected" class="button" disabled><?php _e('Optimize Selected', 'msh-image-optimizer'); ?></button>
                        <span class="selected-count" id="selected-count">0 selected</span>
                    </div>

                    <!-- Results Table -->
                    <div class="results-container">
                        <table class="results-table" id="results-table">
                            <colgroup>
                                <col class="col-select" />
                                <col class="col-image" />
                                <col class="col-filename" />
                                <col class="col-context" />
                                <col class="col-status" />
                                <col class="col-priority" />
                                <col class="col-size" />
                                <col class="col-actions" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="select-column"><input type="checkbox" id="select-all-header"></th>
                                    <th class="image-column"><?php _e('Image', 'msh-image-optimizer'); ?></th>
                                    <th class="filename-column sortable" data-sort-key="filename">
                                        <button type="button" class="sort-trigger">
                                            <span class="sort-label"><?php _e('Filename', 'msh-image-optimizer'); ?></span>
                                            <span class="sort-indicator" aria-hidden="true"></span>
                                        </button>
                                    </th>
                                    <th class="context-column"><?php _e('Content Category', 'msh-image-optimizer'); ?></th>
                                    <th class="status-column"><?php _e('Status', 'msh-image-optimizer'); ?></th>
                                    <th class="priority-column sortable" data-sort-key="priority">
                                        <button type="button" class="sort-trigger">
                                            <span class="sort-label"><?php _e('Priority', 'msh-image-optimizer'); ?></span>
                                            <span class="sort-indicator" aria-hidden="true"></span>
                                        </button>
                                    </th>
                                    <th class="size-column sortable" data-sort-key="size">
                                        <button type="button" class="sort-trigger">
                                            <span class="sort-label"><?php _e('Size', 'msh-image-optimizer'); ?></span>
                                            <span class="sort-indicator" aria-hidden="true"></span>
                                        </button>
                                    </th>
                                    <th class="actions-column"><?php _e('Actions', 'msh-image-optimizer'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="results-tbody">
                                <tr class="no-results-row">
                                    <td colspan="8" class="no-results"><?php _e('Click "Analyze Published Images" to begin analysis.', 'msh-image-optimizer'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Step 2: Duplicate Management -->
                <div class="msh-actions-section">
                    <h2 style="color: #35332f;"><?php _e('Step 2: Clean Up Duplicate Images', 'msh-image-optimizer'); ?></h2>
                    <p class="step-description">
                        <strong><?php _e('AFTER OPTIMIZATION', 'msh-image-optimizer'); ?>:</strong> <?php _e('Find and safely remove duplicate images to free up storage space and organize your media library.', 'msh-image-optimizer'); ?>
                    </p>
                    <div class="duplicate-legend" aria-label="<?php esc_attr_e('Duplicate detection methods', 'msh-image-optimizer'); ?>">
                        <span class="legend-item legend-item--hash">
                            <span class="legend-icon legend-icon--hash" aria-hidden="true"></span>
                            <span class="legend-label"><?php _e('MD5 exact matches', 'msh-image-optimizer'); ?></span>
                        </span>
                        <span class="legend-item legend-item--visual">
                            <span class="legend-icon legend-icon--visual" aria-hidden="true"></span>
                            <span class="legend-label"><?php _e('Perceptual (visually similar)', 'msh-image-optimizer'); ?></span>
                        </span>
                        <span class="legend-item legend-item--filename">
                            <span class="legend-icon legend-icon--filename" aria-hidden="true"></span>
                            <span class="legend-label"><?php _e('Filename-based matches', 'msh-image-optimizer'); ?></span>
                        </span>
                    </div>
                    <div class="action-buttons step-actions">
                        <button id="visual-similarity-scan" class="button button-dot-primary">
                            <?php _e('Visual Similarity Scan', 'msh-image-optimizer'); ?>
                        </button>
                        <button id="quick-duplicate-scan" class="button button-dot-secondary">
                            <?php _e('Quick Duplicate Scan', 'msh-image-optimizer'); ?>
                        </button>
                        <button id="full-library-scan" class="button button-dot-secondary">
                            <?php _e('Deep Library Scan', 'msh-image-optimizer'); ?>
                        </button>
                        <button id="test-cleanup" class="button button-dot-secondary">
                            <?php _e('Test Connection', 'msh-image-optimizer'); ?>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Duplicate Cleanup Activity Log -->
                <div class="msh-log-section step2-log" style="display: none;">
                    <h3><?php _e('Duplicate Cleanup Activity', 'msh-image-optimizer'); ?></h3>
                    <div class="log-container">
                        <textarea id="duplicate-log" readonly placeholder="<?php _e('Duplicate cleanup activity will appear here...', 'msh-image-optimizer'); ?>"></textarea>
                    </div>
                </div>

                <!-- Advanced Tools (Developers) -->
                <div class="msh-advanced-section">
                    <h2 class="advanced-heading"><?php _e('Advanced Tools (Developers)', 'msh-image-optimizer'); ?></h2>
                    <p class="advanced-description"><?php _e('Optional workflows for power users. Safe to ignore for day-to-day optimization.', 'msh-image-optimizer'); ?></p>

                    <div class="advanced-stack">
                        <div class="index-status-card">
                            <div class="index-status-info">
                                <div class="index-health-copy">
                                    <div>
                                        <span class="index-status-label"><?php _e('Usage Index:', 'msh-image-optimizer'); ?></span>
                                        <span id="index-health-badge" class="index-health-badge">Loading...</span>
                                    </div>
                                    <span id="index-status-summary" class="index-status-value">&mdash;</span>
                                </div>
                                <div id="index-last-updated" class="index-status-timestamp"></div>
                                <div id="index-queue-warning" class="index-queue-warning" style="display: none;"></div>
                                <div class="index-table-mix">
                                    <div class="index-mix-heading"><?php _e('Reference Distribution', 'msh-image-optimizer'); ?></div>
                                    <div id="index-mix-bar" class="index-mix-bar">
                                        <span class="index-mix-segment posts" style="width: 33%;" title="Posts"></span>
                                        <span class="index-mix-segment meta" style="width: 33%;" title="Post Meta"></span>
                                        <span class="index-mix-segment options" style="width: 34%;" title="Options"></span>
                                    </div>
                                    <div class="index-mix-legend">
                                        <span><span class="index-mix-swatch posts"></span><?php _e('Posts:', 'msh-image-optimizer'); ?> <span id="mix-posts-count">0</span></span>
                                        <span><span class="index-mix-swatch meta"></span><?php _e('Meta:', 'msh-image-optimizer'); ?> <span id="mix-meta-count">0</span></span>
                                        <span><span class="index-mix-swatch options"></span><?php _e('Options:', 'msh-image-optimizer'); ?> <span id="mix-options-count">0</span></span>
                                    </div>
                                </div>
                                <div class="index-scheduler-details">
                                    <div><strong><?php _e('Queue Status:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-status-detail">&mdash;</span></div>
                                    <div><strong><?php _e('Mode:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-mode-detail">&mdash;</span></div>
                                    <div><strong><?php _e('Pending Jobs:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-pending-detail">&mdash;</span></div>
                                    <div><strong><?php _e('Processed:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-processed-detail">&mdash;</span></div>
                                    <div><strong><?php _e('Queued At:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-queued-detail">&mdash;</span></div>
                                    <div><strong><?php _e('Last Activity:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-activity-detail">&mdash;</span></div>
                                    <div><strong><?php _e('Next Run:', 'msh-image-optimizer'); ?></strong> <span id="scheduler-next-run-detail">&mdash;</span></div>
                                    <div class="scheduler-attachments" style="display:none;">
                                        <strong><?php _e('Attachments Re-indexed:', 'msh-image-optimizer'); ?></strong>
                                        <ul id="scheduler-processed-list"></ul>
                                    </div>
                                </div>
                            </div>
                            <div class="index-status-actions">
                                <button id="rebuild-usage-index" class="button button-dot-secondary">
                                    <?php _e('Smart Build Index', 'msh-image-optimizer'); ?>
                                </button>
                                <button id="force-rebuild-usage-index" class="button button-dot-primary">
                                    <?php _e('Force Rebuild', 'msh-image-optimizer'); ?>
                                </button>
                                <button id="view-orphan-list" class="button button-dot-secondary" style="display: none;">
                                    <?php _e('View Orphan List', 'msh-image-optimizer'); ?>
                                </button>
                                <button id="cleanup-orphans" class="button button-dot-secondary" style="display: none;">
                                    <?php _e('Clean Orphans', 'msh-image-optimizer'); ?>
                                </button>
                                <div class="index-button-help">
                                    <div><strong><?php _e('Automatic monitoring:', 'msh-image-optimizer'); ?></strong> <?php _e('New uploads and edits are queued automatically—no manual action needed under normal use.', 'msh-image-optimizer'); ?></div>
                                    <div><strong><?php _e('Smart Build:', 'msh-image-optimizer'); ?></strong> <?php _e('Background refresh for out-of-sync entries or after large content edits (fast, incremental).', 'msh-image-optimizer'); ?></div>
                                    <div><strong><?php _e('Force Rebuild:', 'msh-image-optimizer'); ?></strong> <?php _e('Background full rebuild for troubleshooting or post-migration validation (slower, comprehensive).', 'msh-image-optimizer'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="index-orphan-panel" class="index-orphan-list" style="display: none;"></div>
                </div>

                <!-- Processing Modal -->
                <div id="processing-modal" class="processing-modal" style="display: none;">
                    <div class="modal-content">
                        <h3 id="modal-title"><?php _e('Processing...', 'msh-image-optimizer'); ?></h3>
                        <div class="modal-spinner"></div>
                        <p id="modal-status"><?php _e('Please wait while we process your request.', 'msh-image-optimizer'); ?></p>
                        <div id="modal-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="modal-progress-fill" style="width: 0%"></div>
                            </div>
                            <span id="modal-progress-text">0%</span>
                        </div>
                        <button type="button" id="modal-dismiss" class="button button-dot-secondary" style="display: none; margin-top: 12px;">
                            <?php _e('Dismiss', 'msh-image-optimizer'); ?>
                        </button>
                    </div>
                </div>
                
            </div>
            
        </div>
        <?php
    }

    /**
     * Get usage index statistics for display
     */
    private function get_usage_index_stats() {
        try {
            if (class_exists('MSH_Image_Usage_Index')) {
                $usage_index = MSH_Image_Usage_Index::get_instance();
                $stats = $usage_index->get_index_stats();

                if ($stats && $stats['summary'] && $stats['summary']->total_entries > 0) {
                    return [
                        'total_entries' => $stats['summary']->total_entries,
                        'unique_attachments' => $stats['summary']->indexed_attachments,
                        'last_update' => $stats['summary']->last_update
                    ];
                }
            }
        } catch (Exception $e) {
            // Debug logging removed for production
        }

        return false;
    }
}

// Initialize the admin interface
new MSH_Image_Optimizer_Admin();
