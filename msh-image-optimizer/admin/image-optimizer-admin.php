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
        add_action('admin_head', array($this, 'add_admin_favicon'));
        add_action('wp_ajax_msh_save_onboarding_context', array($this, 'ajax_save_onboarding_context'));
        add_action('wp_ajax_msh_reset_onboarding_context', array($this, 'ajax_reset_onboarding_context'));
        add_action('wp_ajax_msh_set_active_context_profile', array($this, 'ajax_set_active_context_profile'));
    }

    /**
     * Add favicon to admin pages
     */
    public function add_admin_favicon() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'media_page_msh-image-optimizer') {
            $icons_url = trailingslashit(MSH_IO_ASSETS_URL) . 'icons/';

            // Standard favicon formats - match actual filenames
            $favicon_png = $icons_url . 'Favicon.png';
            $favicon_svg = $icons_url . 'Favicon.svg';
            $favicon_light_data = $this->generate_light_favicon_data_uri();

            // Standard favicon.ico (browsers check this first)
            echo '<link rel="shortcut icon" href="' . esc_url($favicon_png) . '" />' . "\n";

            // PNG format (best compatibility and quality for most browsers)
            if ($favicon_light_data) {
                echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($favicon_png) . '" media="(prefers-color-scheme: light)" />' . "\n";
                echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url($favicon_png) . '" media="(prefers-color-scheme: light)" />' . "\n";
                echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($favicon_light_data) . '" media="(prefers-color-scheme: dark)" />' . "\n";
                echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url($favicon_light_data) . '" media="(prefers-color-scheme: dark)" />' . "\n";
            } else {
                echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url($favicon_png) . '" />' . "\n";
                echo '<link rel="icon" type="image/png" sizes="16x16" href="' . esc_url($favicon_png) . '" />' . "\n";
            }

            // SVG format (fallback for modern browsers that support it)
            echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($favicon_svg) . '" />' . "\n";

            // Apple touch icon for better mobile support
            echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url($favicon_png) . '" />' . "\n";
        }
    }

    private function generate_light_favicon_data_uri() {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
            return null;
        }

        if (!defined('MSH_IO_PLUGIN_DIR')) {
            return null;
        }

        $favicon_path = trailingslashit(MSH_IO_PLUGIN_DIR) . 'assets/icons/Favicon.png';
        if (!file_exists($favicon_path)) {
            return null;
        }

        $image = @imagecreatefrompng($favicon_path);
        if (!$image) {
            return null;
        }

        // Lighten favicon for dark mode
        imagealphablending($image, true);
        imagesavealpha($image, true);
        @imagefilter($image, IMG_FILTER_BRIGHTNESS, 70);

        ob_start();
        imagepng($image);
        $png_data = ob_get_clean();
        imagedestroy($image);

        if (!$png_data) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($png_data);
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

        $onboarding_context = get_option('msh_onboarding_context', array());
        if (!is_array($onboarding_context)) {
            $onboarding_context = array();
        }

        $sanitized_context = MSH_Image_Optimizer_Context_Helper::sanitize_context($onboarding_context, false);
        $profiles = MSH_Image_Optimizer_Context_Helper::get_profiles();
        $active_profile = get_option('msh_active_context_profile', 'primary');
        if ('primary' !== $active_profile && !isset($profiles[$active_profile])) {
            $active_profile = 'primary';
        }

        $profile_payload = array();
        foreach ($profiles as $profile) {
            $profile_payload[] = array(
                'id' => $profile['id'],
                'label' => $profile['label'],
                'usage' => $profile['usage'],
                'notes' => $profile['notes'],
                'context' => $profile['context'],
                'summary' => MSH_Image_Optimizer_Context_Helper::format_summary($profile['context']),
            );
        }

        $diagnostics_snapshot = $this->get_diagnostics_snapshot();
        $active_context_payload = MSH_Image_Optimizer_Context_Helper::get_active_context($profiles);
        $active_industry = isset($active_context_payload['industry']) ? $active_context_payload['industry'] : '';
        $context_menu_options = MSH_Image_Optimizer_Context_Helper::get_context_menu_options($active_industry);
        $context_choice_map = MSH_Image_Optimizer_Context_Helper::get_context_choice_map($active_industry);

        $required_onboarding_keys = array('business_name', 'industry', 'business_type', 'target_audience', 'brand_voice', 'uvp');
        $onboarding_complete = true;
        foreach ($required_onboarding_keys as $required_key) {
            if (empty($sanitized_context[$required_key])) {
                $onboarding_complete = false;
                break;
            }
        }

        wp_enqueue_script(
            'msh-image-optimizer-modern',
            trailingslashit(MSH_IO_ASSETS_URL) . 'js/image-optimizer-modern.js',
            array('jquery'),
            MSH_Image_Optimizer_Plugin::VERSION,
            true
        );
        
        // Get AI service data
        $ai_credits = 0;
        $ai_plan_tier = get_option('msh_plan_tier', 'free');
        $ai_credits_used = 0;
        $ai_last_job = null;
        $ai_credits = 0;
        $ai_access_mode = '';

        if (class_exists('MSH_AI_Service')) {
            $ai_service = MSH_AI_Service::get_instance();
            $access_state = $ai_service->determine_access_state();
            $ai_access_mode = isset($access_state['access_mode']) ? $access_state['access_mode'] : '';

            if ($ai_access_mode === 'byok') {
                $ai_credits = PHP_INT_MAX;
            } else {
                $ai_credits = $ai_service->get_credit_balance();
            }

            $credit_usage = get_option('msh_ai_credit_usage', array());
            $current_month = date('Y-m');
            $ai_credits_used = isset($credit_usage[$current_month]) ? $credit_usage[$current_month] : 0;
        }

        // Get last AI regeneration job
        $ai_jobs = get_option('msh_metadata_regen_jobs', array());
        if (!empty($ai_jobs)) {
            $ai_last_job = reset($ai_jobs); // Get first (most recent) job
        }

        wp_localize_script('msh-image-optimizer-modern', 'mshImageOptimizer', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msh_image_optimizer'),
            'cleanup_nonce' => wp_create_nonce('msh_media_cleanup'),
            'pluginUrl' => untrailingslashit(MSH_IO_PLUGIN_URL),
            'renameEnabled' => get_option('msh_enable_file_rename', '0'),
            'renameToggleNonce' => wp_create_nonce('msh_toggle_file_rename'),
            'aiMode' => get_option('msh_ai_mode', 'manual'),
            'aiToggleNonce' => wp_create_nonce('msh_toggle_ai_mode'),
            'indexStats' => $index_summary,
            'onboardingContext' => $sanitized_context,
            'onboardingComplete' => $onboarding_complete,
            'onboardingSummary' => MSH_Image_Optimizer_Context_Helper::format_summary($sanitized_context),
            'onboardingLabels' => MSH_Image_Optimizer_Context_Helper::get_label_map(),
            'settingsUrl' => admin_url('options-general.php?page=msh-image-optimizer-settings'),
            'contextProfiles' => $profile_payload,
            'activeProfile' => $active_profile,
            'diagnostics' => $diagnostics_snapshot,
            'contextChoices' => $context_menu_options,
            'contextChoiceMap' => $context_choice_map,
            'aiCredits' => $ai_credits,
            'aiAccessMode' => $ai_access_mode,
            'aiPlanTier' => $ai_plan_tier,
            'aiCreditsUsedMonth' => $ai_credits_used,
            'aiLastJob' => $ai_last_job,
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
                'derivedInfo' => __('Alternate formats detected; these mirror another attachment.', 'msh-image-optimizer'),
                'onboardingValidationError' => __('Please complete the required fields before continuing.', 'msh-image-optimizer'),
                'onboardingSaveSuccess' => __('Setup saved successfully.', 'msh-image-optimizer'),
                'onboardingSaveError' => __('Unable to save the setup right now. Please try again.', 'msh-image-optimizer'),
                'onboardingResetError' => __('Unable to reset the setup right now. Please try again.', 'msh-image-optimizer'),
                'onboardingProgressLabel' => __('Step %1$d of %2$d', 'msh-image-optimizer'),
                'onboardingProgressComplete' => __('Setup complete', 'msh-image-optimizer'),
                'onboardingSummaryDemographics' => __('Demographics: %s', 'msh-image-optimizer'),
                'onboardingSummaryServiceArea' => __('Service area: %s', 'msh-image-optimizer'),
                'onboardingSummaryNotSpecified' => __('Not specified', 'msh-image-optimizer'),
                'onboardingSummaryAiYes' => __('Subscribed to updates', 'msh-image-optimizer'),
                'onboardingSummaryAiNo' => __('No updates requested', 'msh-image-optimizer'),
                'aiEnabled' => __('✓ AI suggestions enabled', 'msh-image-optimizer'),
                'aiDisabled' => __('AI suggestions disabled', 'msh-image-optimizer'),
                'aiToggleError' => __('Unable to update AI setting. Please try again.', 'msh-image-optimizer'),
                'contextSwitchSuccess' => __('Active context updated.', 'msh-image-optimizer'),
                'contextSwitchError' => __('Unable to change the active context right now.', 'msh-image-optimizer'),
                'contextSwitcherLabel' => __('Active Context', 'msh-image-optimizer'),
                'contextSwitcherNote' => __('Switch profiles to keep metadata, automation, and AI prompts aligned.', 'msh-image-optimizer'),
                'primaryProfileLabel' => __('Primary – %s', 'msh-image-optimizer'),
                'profileLabelTemplate' => __('Profile – %s', 'msh-image-optimizer'),
                'primaryContextFallback' => __('Primary Context', 'msh-image-optimizer'),
                'profileContextFallback' => __('Context profile', 'msh-image-optimizer'),
                'indexStatusReady' => __('Usage index ready – last refreshed %s', 'msh-image-optimizer'),
                'indexStatusQueued' => __('Usage index building…', 'msh-image-optimizer'),
                'indexStatusNotBuilt' => __('Usage index not built yet', 'msh-image-optimizer'),
                'onboardingResetConfirm' => __('Reset the saved context? This will clear all onboarding answers.', 'msh-image-optimizer'),
                'onboardingResetDone' => __('Context cleared. You can complete the setup whenever you\'re ready.', 'msh-image-optimizer'),
                // Status labels
                'supported' => __('Supported', 'msh-image-optimizer'),
                'notSupported' => __('Not Supported', 'msh-image-optimizer'),
                'active' => __('Active', 'msh-image-optimizer'),
                'inactive' => __('Inactive', 'msh-image-optimizer'),
                'never' => __('Never', 'msh-image-optimizer'),
                // Button states
                'save' => __('Save', 'msh-image-optimizer'),
                'edit' => __('Edit', 'msh-image-optimizer'),
                'preview' => __('Preview', 'msh-image-optimizer'),
                // Wizard states
                'wizardComplete' => __('Complete', 'msh-image-optimizer'),
                'wizardActive' => __('In progress', 'msh-image-optimizer'),
                'wizardUpcoming' => __('Coming soon', 'msh-image-optimizer'),
                'wizardPending' => __('Pending', 'msh-image-optimizer'),
                // Progress states
                'ready' => __('Ready', 'msh-image-optimizer'),
                // Language selector
                'languageAuto' => __('Auto (Site/Profile Default)', 'msh-image-optimizer'),
                'languageSelectorDescription' => __('AI will generate titles, ALT text, and descriptions in the selected language.', 'msh-image-optimizer'),
                // WebP detection
                'javascriptDetection' => __('JavaScript Detection', 'msh-image-optimizer'),
                'cookieJavascript' => __('Cookie + JavaScript', 'msh-image-optimizer')
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
        $primary_context = MSH_Image_Optimizer_Context_Helper::sanitize_context(
            get_option('msh_onboarding_context', array()),
            false
        );
        $profiles = MSH_Image_Optimizer_Context_Helper::get_profiles();
        $active_profile = get_option('msh_active_context_profile', 'primary');
        if ('primary' !== $active_profile && !isset($profiles[$active_profile])) {
            $active_profile = 'primary';
        }
        $primary_label = !empty($primary_context['business_name'])
            ? $primary_context['business_name']
            : __('Primary Context', 'msh-image-optimizer');
        $active_label = ('primary' === $active_profile)
            ? sprintf(__('Primary – %s', 'msh-image-optimizer'), $primary_label)
            : sprintf(
                __('Profile – %s', 'msh-image-optimizer'),
                !empty($profiles[$active_profile]['label']) ? $profiles[$active_profile]['label'] : __('Context profile', 'msh-image-optimizer')
            );
        ?>
        <div class="wrap">
            <div class="msh-page-header">
                <div class="msh-logo-container">
                    <img src="<?php echo esc_url(trailingslashit(MSH_IO_ASSETS_URL) . 'icons/Optimizer logo.svg'); ?>"
                         alt="<?php esc_attr_e('The Dot Image Optimizer', 'msh-image-optimizer'); ?>"
                         class="msh-logo" />
                </div>
                <div class="msh-header-links">
                    <a href="mailto:support@thedot.com" class="msh-support-link">
                        <span class="msh-support-text"><?php esc_html_e('reach out for support', 'msh-image-optimizer'); ?></span>
                        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-mail-icon">
                            <path d="M4 20.3735C3.45 20.3735 2.975 20.1819 2.575 19.7985C2.19167 19.3985 2 18.9235 2 18.3735V6.37353C2 5.82354 2.19167 5.35687 2.575 4.97353C2.975 4.57353 3.45 4.37354 4 4.37354H20C20.55 4.37354 21.0167 4.57353 21.4 4.97353C21.8 5.35687 22 5.82354 22 6.37353V18.3735C22 18.9235 21.8 19.3985 21.4 19.7985C21.0167 20.1819 20.55 20.3735 20 20.3735H4ZM12 13.3735L20 8.37354V6.37353L12 11.3735L4 6.37353V8.37354L12 13.3735Z" fill="#35332F"/>
                        </svg>
                    </a>
                    <a href="https://thedot.com" target="_blank" rel="noopener noreferrer" class="msh-website-link">
                        <span class="msh-website-text"><?php esc_html_e('visit our website', 'msh-image-optimizer'); ?></span>
                        <svg width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg" class="msh-website-icon">
                            <path d="M10 20.3735C8.63333 20.3735 7.34167 20.111 6.125 19.586C4.90833 19.061 3.84583 18.3444 2.9375 17.436C2.02917 16.5277 1.3125 15.4652 0.7875 14.2485C0.2625 13.0319 0 11.7402 0 10.3735C0 8.9902 0.2625 7.69437 0.7875 6.48604C1.3125 5.2777 2.02917 4.21937 2.9375 3.31104C3.84583 2.4027 4.90833 1.68604 6.125 1.16104C7.34167 0.636035 8.63333 0.373535 10 0.373535C11.3833 0.373535 12.6792 0.636035 13.8875 1.16104C15.0958 1.68604 16.1542 2.4027 17.0625 3.31104C17.9708 4.21937 18.6875 5.2777 19.2125 6.48604C19.7375 7.69437 20 8.9902 20 10.3735C20 11.7402 19.7375 13.0319 19.2125 14.2485C18.6875 15.4652 17.9708 16.5277 17.0625 17.436C16.1542 18.3444 15.0958 19.061 13.8875 19.586C12.6792 20.111 11.3833 20.3735 10 20.3735ZM10 18.3235C10.4333 17.7235 10.8083 17.0985 11.125 16.4485C11.4417 15.7985 11.7 15.1069 11.9 14.3735H8.1C8.3 15.1069 8.55833 15.7985 8.875 16.4485C9.19167 17.0985 9.56667 17.7235 10 18.3235ZM7.4 17.9235C7.1 17.3735 6.8375 16.8027 6.6125 16.211C6.3875 15.6194 6.2 15.0069 6.05 14.3735H3.1C3.58333 15.2069 4.1875 15.9319 4.9125 16.5485C5.6375 17.1652 6.46667 17.6235 7.4 17.9235ZM12.6 17.9235C13.5333 17.6235 14.3625 17.1652 15.0875 16.5485C15.8125 15.9319 16.4167 15.2069 16.9 14.3735H13.95C13.8 15.0069 13.6125 15.6194 13.3875 16.211C13.1625 16.8027 12.9 17.3735 12.6 17.9235ZM2.25 12.3735H5.65C5.6 12.0402 5.5625 11.711 5.5375 11.386C5.5125 11.061 5.5 10.7235 5.5 10.3735C5.5 10.0235 5.5125 9.68604 5.5375 9.36104C5.5625 9.03604 5.6 8.70687 5.65 8.37354H2.25C2.16667 8.70687 2.10417 9.03604 2.0625 9.36104C2.02083 9.68604 2 10.0235 2 10.3735C2 10.7235 2.02083 11.061 2.0625 11.386C2.10417 11.711 2.16667 12.0402 2.25 12.3735ZM7.65 12.3735H12.35C12.4 12.0402 12.4375 11.711 12.4625 11.386C12.4875 11.061 12.5 10.7235 12.5 10.3735C12.5 10.0235 12.4875 9.68604 12.4625 9.36104C12.4375 9.03604 12.4 8.70687 12.35 8.37354H7.65C7.6 8.70687 7.5625 9.03604 7.5375 9.36104C7.5125 9.68604 7.5 10.0235 7.5 10.3735C7.5 10.7235 7.5125 11.061 7.5375 11.386C7.5625 11.711 7.6 12.0402 7.65 12.3735ZM14.35 12.3735H17.75C17.8333 12.0402 17.8958 11.711 17.9375 11.386C17.9792 11.061 18 10.7235 18 10.3735C18 10.0235 17.9792 9.68604 17.9375 9.36104C17.8958 9.03604 17.8333 8.70687 17.75 8.37354H14.35C14.4 8.70687 14.4375 9.03604 14.4625 9.36104C14.4875 9.68604 14.5 10.0235 14.5 10.3735C14.5 10.7235 14.4875 11.061 14.4625 11.386C14.4375 11.711 14.4 12.0402 14.35 12.3735ZM13.95 6.37354H16.9C16.4167 5.5402 15.8125 4.8152 15.0875 4.19854C14.3625 3.58187 13.5333 3.12354 12.6 2.82354C12.9 3.37354 13.1625 3.94437 13.3875 4.53604C13.6125 5.1277 13.8 5.7402 13.95 6.37354ZM8.1 6.37354H11.9C11.7 5.6402 11.4417 4.94854 11.125 4.29854C10.8083 3.64854 10.4333 3.02354 10 2.42354C9.56667 3.02354 9.19167 3.64854 8.875 4.29854C8.55833 4.94854 8.3 5.6402 8.1 6.37354ZM3.1 6.37354H6.05C6.2 5.7402 6.3875 5.1277 6.6125 4.53604C6.8375 3.94437 7.1 3.37354 7.4 2.82354C6.46667 3.12354 5.6375 3.58187 4.9125 4.19854C4.1875 4.8152 3.58333 5.5402 3.1 6.37354Z" fill="#35332F"/>
                        </svg>
                    </a>
                </div>
            </div>
            
            <div class="msh-optimizer-dashboard">
                
                <div id="msh-onboarding-container">
                    <div id="msh-onboarding-form" class="msh-onboarding-wizard">
                        <div class="wizard-header">
                            <h2><?php _e('Set Up Your Image Optimizer', 'msh-image-optimizer'); ?></h2>
                            <p><?php _e('Share a few business details so metadata, reports, and future AI features stay on brand.', 'msh-image-optimizer'); ?></p>
                            <div class="wizard-progress">
                                <div class="wizard-progress-track">
                                    <div class="wizard-progress-bar" id="onboarding-progress-bar"></div>
                                </div>
                                <span class="wizard-progress-label" id="onboarding-progress-label"></span>
                            </div>
                        </div>
                        <div class="wizard-message" id="onboarding-message" role="alert" style="display:none;"></div>
                        <form id="msh-onboarding-form-element">
                            <div class="onboarding-step" data-step="1">
                                <h3><?php _e('Business Identity', 'msh-image-optimizer'); ?></h3>
                                <p class="step-description"><?php _e('Used across optimizer summaries, logs, and future AI prompts.', 'msh-image-optimizer'); ?></p>
                                <label for="msh_business_name"><?php _e('Business Name*', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_business_name" name="business_name" class="msh-input" required />

                                <label for="msh_industry"><?php _e('Industry*', 'msh-image-optimizer'); ?></label>
                                <select id="msh_industry" name="industry" class="msh-select" required>
                                    <option value=""><?php _e('Select industry…', 'msh-image-optimizer'); ?></option>
                                    <optgroup label="<?php esc_attr_e('Professional Services', 'msh-image-optimizer'); ?>">
                                        <option value="legal"><?php _e('Legal Services', 'msh-image-optimizer'); ?></option>
                                        <option value="accounting"><?php _e('Accounting & Tax', 'msh-image-optimizer'); ?></option>
                                        <option value="consulting"><?php _e('Business Consulting', 'msh-image-optimizer'); ?></option>
                                        <option value="marketing"><?php _e('Marketing Agency', 'msh-image-optimizer'); ?></option>
                                        <option value="web_design"><?php _e('Web Design / Development', 'msh-image-optimizer'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Home Services', 'msh-image-optimizer'); ?>">
                                        <option value="plumbing"><?php _e('Plumbing', 'msh-image-optimizer'); ?></option>
                                        <option value="hvac"><?php _e('HVAC', 'msh-image-optimizer'); ?></option>
                                        <option value="electrical"><?php _e('Electrical', 'msh-image-optimizer'); ?></option>
                                        <option value="renovation"><?php _e('Renovation / Construction', 'msh-image-optimizer'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Healthcare', 'msh-image-optimizer'); ?>">
                                        <option value="dental"><?php _e('Dental', 'msh-image-optimizer'); ?></option>
                                        <option value="medical"><?php _e('Medical Practice', 'msh-image-optimizer'); ?></option>
                                        <option value="therapy"><?php _e('Therapy / Counseling', 'msh-image-optimizer'); ?></option>
                                        <option value="wellness"><?php _e('Wellness / Alternative', 'msh-image-optimizer'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Retail & E-commerce', 'msh-image-optimizer'); ?>">
                                        <option value="online_store"><?php _e('Online Store', 'msh-image-optimizer'); ?></option>
                                        <option value="local_retail"><?php _e('Local Retail', 'msh-image-optimizer'); ?></option>
                                        <option value="specialty"><?php _e('Specialty Products', 'msh-image-optimizer'); ?></option>
                                    </optgroup>
                                    <option value="other"><?php _e('Other / Not listed', 'msh-image-optimizer'); ?></option>
                                </select>

                                <label for="msh_business_type"><?php _e('Business Type*', 'msh-image-optimizer'); ?></label>
                                <select id="msh_business_type" name="business_type" class="msh-select" required>
                                    <option value=""><?php _e('Select business type…', 'msh-image-optimizer'); ?></option>
                                    <option value="local_service"><?php _e('Local Service Provider', 'msh-image-optimizer'); ?></option>
                                    <option value="online_service"><?php _e('Online Service Provider', 'msh-image-optimizer'); ?></option>
                                    <option value="ecommerce"><?php _e('E-commerce', 'msh-image-optimizer'); ?></option>
                                    <option value="saas"><?php _e('SaaS / Software', 'msh-image-optimizer'); ?></option>
                                    <option value="b2b"><?php _e('B2B Services', 'msh-image-optimizer'); ?></option>
                                    <option value="b2c"><?php _e('B2C Services', 'msh-image-optimizer'); ?></option>
                                    <option value="nonprofit"><?php _e('Non-profit / Public Sector', 'msh-image-optimizer'); ?></option>
                                </select>
                            </div>

                            <div class="onboarding-step" data-step="2">
                                <h3><?php _e('Target Audience', 'msh-image-optimizer'); ?></h3>
                                <p class="step-description"><?php _e('Helps the optimizer speak to the right people and surface relevant keywords.', 'msh-image-optimizer'); ?></p>
                                <label for="msh_target_audience"><?php _e('Ideal customer*', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_target_audience" name="target_audience" class="msh-input" placeholder="<?php esc_attr_e('e.g., Homeowners aged 35-65, Small business owners, Tech startups', 'msh-image-optimizer'); ?>" required />

                                <label for="msh_pain_points"><?php _e('Problems you solve', 'msh-image-optimizer'); ?></label>
                                <textarea id="msh_pain_points" name="pain_points" class="msh-textarea" rows="3" placeholder="<?php esc_attr_e('e.g., Same-day emergency repairs, Streamlined accounting, Custom treatment plans', 'msh-image-optimizer'); ?>"></textarea>

                                <label for="msh_demographics"><?php _e('Demographics (optional)', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_demographics" name="demographics" class="msh-input" placeholder="<?php esc_attr_e('e.g., Income $50k+, College educated, Urban professionals', 'msh-image-optimizer'); ?>" />
                            </div>

                            <div class="onboarding-step" data-step="3">
                                <h3><?php _e('Brand Voice & Value', 'msh-image-optimizer'); ?></h3>
                                <p class="step-description"><?php _e('We’ll match metadata suggestions to your brand personality and highlight what makes you different.', 'msh-image-optimizer'); ?></p>
                                <span class="field-label"><?php _e('Brand voice*', 'msh-image-optimizer'); ?></span>
                                <div class="radio-grid">
                                    <label>
                                        <input type="radio" name="brand_voice" value="professional" required />
                                        <strong><?php _e('Professional', 'msh-image-optimizer'); ?></strong>
                                        <span><?php _e('Formal, expert, authoritative', 'msh-image-optimizer'); ?></span>
                                    </label>
                                    <label>
                                        <input type="radio" name="brand_voice" value="friendly" />
                                        <strong><?php _e('Friendly', 'msh-image-optimizer'); ?></strong>
                                        <span><?php _e('Approachable, helpful, warm', 'msh-image-optimizer'); ?></span>
                                    </label>
                                    <label>
                                        <input type="radio" name="brand_voice" value="casual" />
                                        <strong><?php _e('Casual', 'msh-image-optimizer'); ?></strong>
                                        <span><?php _e('Conversational and relaxed', 'msh-image-optimizer'); ?></span>
                                    </label>
                                    <label>
                                        <input type="radio" name="brand_voice" value="technical" />
                                        <strong><?php _e('Technical', 'msh-image-optimizer'); ?></strong>
                                        <span><?php _e('Detailed, precise, specialist', 'msh-image-optimizer'); ?></span>
                                    </label>
                                </div>

                                <label for="msh_uvp"><?php _e('What makes you different?*', 'msh-image-optimizer'); ?></label>
                                <textarea id="msh_uvp" name="uvp" class="msh-textarea" rows="3" placeholder="<?php esc_attr_e('e.g., 24/7 emergency team, 20 years experience, Free consultations, Same-day delivery', 'msh-image-optimizer'); ?>" required></textarea>

                                <label for="msh_cta_preference"><?php _e('Call-to-action style', 'msh-image-optimizer'); ?></label>
                                <select id="msh_cta_preference" name="cta_preference" class="msh-select">
                                    <option value="soft"><?php _e('Helpful / soft reminders', 'msh-image-optimizer'); ?></option>
                                    <option value="balanced"><?php _e('Neutral / informative', 'msh-image-optimizer'); ?></option>
                                    <option value="direct"><?php _e('Direct / action-focused', 'msh-image-optimizer'); ?></option>
                                </select>
                            </div>

                            <div class="onboarding-step" data-step="4">
                                <h3><?php _e('Location & Service Area', 'msh-image-optimizer'); ?></h3>
                                <p class="step-description"><?php _e('Used for local SEO hints and AI localisation. Leave blank if not location specific.', 'msh-image-optimizer'); ?></p>
                                <label for="msh_location_city"><?php _e('City', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_location_city" name="city" class="msh-input" placeholder="<?php esc_attr_e('e.g., Toronto', 'msh-image-optimizer'); ?>" />

                                <label for="msh_location_region"><?php _e('Province / Region', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_location_region" name="region" class="msh-input" placeholder="<?php esc_attr_e('e.g., Ontario', 'msh-image-optimizer'); ?>" />

                                <label for="msh_location_country"><?php _e('Country', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_location_country" name="country" class="msh-input" placeholder="<?php esc_attr_e('e.g., Canada', 'msh-image-optimizer'); ?>" />

                                <label for="msh_service_area"><?php _e('Service area', 'msh-image-optimizer'); ?></label>
                                <input type="text" id="msh_service_area" name="service_area" class="msh-input" placeholder="<?php esc_attr_e('e.g., Greater Toronto Area, Nationwide, Remote', 'msh-image-optimizer'); ?>" />

                                <label class="checkbox-inline">
                                    <input type="checkbox" id="msh_ai_interest" name="ai_interest" value="1" />
                                    <?php _e('Keep me posted about AI metadata and duplicate detection features', 'msh-image-optimizer'); ?>
                                </label>

                                <p class="step-description small-print"><?php _e('Saving completes the setup. You can edit these details at any time.', 'msh-image-optimizer'); ?></p>
                            </div>
                        </form>
                        <div class="wizard-navigation">
                            <button type="button" class="button button-dot-secondary wizard-prev" disabled><?php _e('Back', 'msh-image-optimizer'); ?></button>
                            <div class="wizard-nav-spacer"></div>
                            <button type="button" class="button wizard-next button-dot-primary"><?php _e('Next', 'msh-image-optimizer'); ?></button>
                            <button type="button" class="button button-dot-primary wizard-save" style="display:none;">
                                <?php _e('Save Context', 'msh-image-optimizer'); ?>
                            </button>
                        </div>
                    </div>

                    <div id="msh-onboarding-summary" class="msh-onboarding-summary" style="display:none;">
                        <details class="summary-collapsible" open>
                            <summary class="summary-header">
                                <h2><?php _e('Optimization Context', 'msh-image-optimizer'); ?></h2>
                                <p class="summary-active-label" id="summary-active-label"><?php echo esc_html($active_label); ?></p>
                            </summary>
                            <div class="summary-content">
                                <p class="summary-description"><?php _e('These preferences drive metadata suggestions, reports, and upcoming AI enhancements. Manage additional profiles (landing pages, multilingual) from Settings.', 'msh-image-optimizer'); ?></p>
                                <div class="summary-context-switcher">
                            <label for="msh-context-selector"><?php _e('Active Context', 'msh-image-optimizer'); ?></label>
                            <select id="msh-context-selector" class="msh-select">
                                <option value="primary" <?php selected($active_profile, 'primary'); ?>>
                                    <?php echo esc_html(sprintf(__('Primary – %s', 'msh-image-optimizer'), $primary_label)); ?>
                                </option>
                                <?php foreach ($profiles as $profile) : ?>
                                    <?php
                                    $label = !empty($profile['label']) ? $profile['label'] : __('Context profile', 'msh-image-optimizer');
                                    ?>
                                    <option value="<?php echo esc_attr($profile['id']); ?>" <?php selected($active_profile, $profile['id']); ?>>
                                        <?php echo esc_html(sprintf(__('Profile – %s', 'msh-image-optimizer'), $label)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="summary-context-note">
                                <?php _e('Switch profiles without leaving the dashboard. Updates apply instantly across optimization workflows and AI prompts.', 'msh-image-optimizer'); ?>
                            </p>
                        </div>
                        <div class="summary-index-status">
                            <span class="summary-index-status-label"><?php _e('Usage Index', 'msh-image-optimizer'); ?></span>
                            <span class="summary-index-status-value" id="summary-index-status-value"></span>
                        </div>
                        <dl class="summary-grid">
                            <div>
                                <dt><?php _e('Business Name', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-business-name">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Industry', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-industry">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Business Type', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-business-type">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Target Audience', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-target-audience">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Pain Points', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-pain-points">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Brand Voice', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-brand-voice">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Value Proposition', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-uvp">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('CTA Style', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-cta">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('Location', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-location">&mdash;</dd>
                            </div>
                            <div>
                                <dt><?php _e('AI Updates', 'msh-image-optimizer'); ?></dt>
                                <dd id="summary-ai-interest">&mdash;</dd>
                            </div>
                        </dl>
                        <div class="summary-actions">
                            <a href="<?php echo esc_url(admin_url('options-general.php?page=msh-image-optimizer-settings')); ?>" class="button button-dot-primary summary-settings">
                                <?php _e('Open Settings', 'msh-image-optimizer'); ?>
                            </a>
                            <button type="button" class="button button-dot-secondary summary-reset"><?php _e('Reset', 'msh-image-optimizer'); ?></button>
                        </div>
                            </div><!-- .summary-content -->
                        </details><!-- .summary-collapsible -->
                    </div>
                </div>

                <!-- AI Regeneration Confirmation Modal -->
                <div id="ai-regen-modal" class="ai-regen-modal" style="display: none;">
                    <div class="ai-modal-overlay"></div>
                    <div class="ai-modal-content">
                        <div class="ai-modal-header">
                            <h3><?php _e('Confirm AI Metadata Regeneration', 'msh-image-optimizer'); ?></h3>
                            <button type="button" class="ai-modal-close" aria-label="<?php esc_attr_e('Close', 'msh-image-optimizer'); ?>">×</button>
                        </div>

                        <div class="ai-modal-body">
                            <p class="ai-modal-description">
                                <?php _e('Configure how you want to regenerate metadata for your images. This will use AI to analyze images and generate SEO-optimized metadata.', 'msh-image-optimizer'); ?>
                            </p>

                            <div class="ai-modal-section">
                                <label class="ai-modal-label"><?php _e('Selection Scope', 'msh-image-optimizer'); ?></label>
                                <div class="ai-radio-group">
                                    <label>
                                        <input type="radio" name="ai_scope" value="all" checked>
                                        <span><?php _e('All images in media library', 'msh-image-optimizer'); ?></span>
                                        <span class="ai-radio-count" id="ai-scope-all-count">(0 images)</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="ai_scope" value="published">
                                        <span><?php _e('Only published images', 'msh-image-optimizer'); ?></span>
                                        <span class="ai-radio-count" id="ai-scope-published-count">(0 images)</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="ai_scope" value="missing">
                                        <span><?php _e('Images with missing metadata', 'msh-image-optimizer'); ?></span>
                                        <span class="ai-radio-count" id="ai-scope-missing-count">(0 images)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="ai-modal-section">
                                <label class="ai-modal-label"><?php _e('Regeneration Mode', 'msh-image-optimizer'); ?></label>
                                <div class="ai-radio-group">
                                    <label>
                                        <input type="radio" name="ai_mode" value="fill-empty" checked>
                                        <span><?php _e('Fill empty fields only', 'msh-image-optimizer'); ?></span>
                                        <small class="ai-radio-help"><?php _e('Only generate metadata for fields that are currently empty', 'msh-image-optimizer'); ?></small>
                                    </label>
                                    <label>
                                        <input type="radio" name="ai_mode" value="overwrite">
                                        <span><?php _e('Overwrite all metadata', 'msh-image-optimizer'); ?></span>
                                        <small class="ai-radio-help"><?php _e('Replace existing metadata with AI-generated content (backup created)', 'msh-image-optimizer'); ?></small>
                                    </label>
                                </div>
                            </div>

                            <div class="ai-modal-section">
                                <label class="ai-modal-label"><?php _e('Fields to Generate', 'msh-image-optimizer'); ?></label>
                                <div class="ai-checkbox-group">
                                    <label>
                                        <input type="checkbox" name="ai_fields[]" value="title" checked>
                                        <span><?php _e('Title', 'msh-image-optimizer'); ?></span>
                                    </label>
                                    <label>
                                        <input type="checkbox" name="ai_fields[]" value="alt_text" checked>
                                        <span><?php _e('Alt Text', 'msh-image-optimizer'); ?></span>
                                    </label>
                                    <label>
                                        <input type="checkbox" name="ai_fields[]" value="caption" checked>
                                        <span><?php _e('Caption', 'msh-image-optimizer'); ?></span>
                                    </label>
                                    <label>
                                        <input type="checkbox" name="ai_fields[]" value="description" checked>
                                        <span><?php _e('Description', 'msh-image-optimizer'); ?></span>
                                    </label>
                                </div>
                            </div>

                            <div class="ai-modal-section">
                                <h4 class="ai-modal-label"><?php _e('Output Language', 'msh-image-optimizer'); ?></h4>
                                <label for="ai-language-select" class="screen-reader-text">
                                    <?php _e('Generate metadata in', 'msh-image-optimizer'); ?>
                                </label>
                                <select id="ai-language-select" name="ai_language" class="ai-language-selector">
                                    <option value="auto"><?php _e('Auto (Site/Profile Default)', 'msh-image-optimizer'); ?></option>
                                    <option value="en"><?php _e('English', 'msh-image-optimizer'); ?></option>
                                    <option value="es"><?php _e('Spanish (Español)', 'msh-image-optimizer'); ?></option>
                                    <option value="fr"><?php _e('French (Français)', 'msh-image-optimizer'); ?></option>
                                    <option value="de"><?php _e('German (Deutsch)', 'msh-image-optimizer'); ?></option>
                                    <option value="pt"><?php _e('Portuguese (Português)', 'msh-image-optimizer'); ?></option>
                                    <option value="it"><?php _e('Italian (Italiano)', 'msh-image-optimizer'); ?></option>
                                </select>
                                <p class="ai-field-description">
                                    <?php _e('AI will generate titles, ALT text, and descriptions in the selected language.', 'msh-image-optimizer'); ?>
                                </p>
                            </div>

                            <div class="ai-modal-estimate" id="ai-modal-estimate">
                                <div class="ai-estimate-row">
                                    <span class="ai-estimate-label"><?php _e('Images to process:', 'msh-image-optimizer'); ?></span>
                                    <span class="ai-estimate-value" id="ai-estimate-count">-</span>
                                </div>
                                <div class="ai-estimate-row">
                                    <span class="ai-estimate-label"><?php _e('Estimated credits:', 'msh-image-optimizer'); ?></span>
                                    <span class="ai-estimate-value" id="ai-estimate-credits">-</span>
                                </div>
                                <div class="ai-estimate-row">
                                    <span class="ai-estimate-label"><?php _e('Credits available:', 'msh-image-optimizer'); ?></span>
                                    <span class="ai-estimate-value" id="ai-estimate-available">-</span>
                                </div>
                            </div>

                            <div class="ai-modal-warning" id="ai-insufficient-credits" style="display: none;">
                                <strong><?php _e('Insufficient Credits', 'msh-image-optimizer'); ?></strong>
                                <p><?php _e('You do not have enough credits to process all selected images. Please reduce the selection scope or upgrade your plan.', 'msh-image-optimizer'); ?></p>
                            </div>
                        </div>

                        <div class="ai-modal-footer">
                            <button type="button" id="ai-modal-cancel" class="button button-dot-secondary">
                                <?php _e('Cancel', 'msh-image-optimizer'); ?>
                            </button>
                            <button type="button" id="ai-modal-start" class="button button-dot-primary">
                                <?php _e('Start Regeneration', 'msh-image-optimizer'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Manual Edit Warning Modal -->
                <div id="ai-manual-edit-warning-modal" class="ai-regen-modal" style="display: none;">
                    <div class="ai-modal-overlay"></div>
                    <div class="ai-modal-content" style="max-width: 500px;">
                        <div class="ai-modal-header">
                            <h3><?php _e('Manual Edits Detected', 'msh-image-optimizer'); ?></h3>
                            <button type="button" class="ai-manual-warning-close" aria-label="<?php esc_attr_e('Close', 'msh-image-optimizer'); ?>">×</button>
                        </div>

                        <div class="ai-modal-body">
                            <p class="ai-modal-description" id="ai-manual-edit-message">
                                <!-- Message will be populated by JavaScript -->
                            </p>
                            <p style="margin-top: 15px; color: #666; font-size: 14px;">
                                <?php _e('AI Regeneration will stage new metadata but won\'t overwrite your manual changes unless you explicitly apply it during the Optimize step.', 'msh-image-optimizer'); ?>
                            </p>
                        </div>

                        <div class="ai-modal-footer">
                            <button type="button" id="ai-manual-warning-cancel" class="button button-dot-secondary">
                                <?php _e('Cancel', 'msh-image-optimizer'); ?>
                            </button>
                            <button type="button" id="ai-manual-warning-continue" class="button button-dot-primary">
                                <?php _e('Continue', 'msh-image-optimizer'); ?>
                            </button>
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
                    <div class="msh-ai-toggle-section">
                        <div class="msh-rename-settings-section ai-toggle-panel">
                            <div class="rename-setting-card">
                                <div class="rename-setting-content">
                                    <label class="rename-toggle-wrapper ai-toggle-wrapper">
                                        <input type="checkbox" id="enable-ai-mode" class="rename-toggle-checkbox"
                                               <?php checked(get_option('msh_ai_mode', 'manual') !== 'manual'); ?>>
                                        <span class="rename-toggle-slider"></span>
                                        <div class="rename-toggle-text">
                                            <strong><?php _e('Enable AI Metadata Suggestions', 'msh-image-optimizer'); ?></strong>
                                        </div>
                                    </label>
                                    <div id="ai-mode-status-indicator" class="rename-status ai-mode-status">
                                        <span class="rename-status-text">
                                            <?php
                                            $ai_enabled = get_option('msh_ai_mode', 'manual') !== 'manual';
                                            if ($ai_enabled) {
                                                echo '<span class="status-ready">' . __('✓ AI suggestions enabled', 'msh-image-optimizer') . '</span>';
                                            } else {
                                                echo '<span class="status-disabled">' . __('AI suggestions disabled', 'msh-image-optimizer') . '</span>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="msh-notification-section">
                        <div class="msh-rename-settings-section notification-panel">
                            <div class="rename-setting-card">
                                <div class="notification-setting-content">
                                    <div class="notification-copy">
                                        <strong><?php _e('Desktop Notifications', 'msh-image-optimizer'); ?></strong>
                                        <p><?php _e('Get an OS notification when optimization finishes so you can work in other tabs.', 'msh-image-optimizer'); ?></p>
                                    </div>
                                    <div class="notification-actions">
                                        <button type="button" class="button button-dot-secondary" id="enable-desktop-notifications">
                                            <?php _e('Enable Notifications', 'msh-image-optimizer'); ?>
                                        </button>
                                        <span class="notification-status-text" id="notification-status-text"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

                    <!-- AI Metadata Regeneration (Advanced) -->
                    <details class="msh-ai-regen-section-inline summary-collapsible step-rename-settings" id="ai-regen-dashboard">
                        <summary class="summary-header ai-regen-summary">
                            <div class="summary-title-group">
                                <h3><?php _e('AI Metadata Regeneration (Advanced)', 'msh-image-optimizer'); ?></h3>
                                <p class="summary-description ai-regen-summary-helper"><?php _e('Use this when you want AI-generated suggestions to appear in the results table before applying.', 'msh-image-optimizer'); ?></p>
                            </div>
                        </summary>
                        <div class="summary-content ai-regen-inline-content">
                            <p class="ai-regen-help-text">
                                <?php _e('Use AI to bulk-regenerate metadata for existing images. This analyzes images with OpenAI Vision and overwrites metadata.', 'msh-image-optimizer'); ?>
                            </p>
                            <div class="ai-when-to-use">
                                <strong><?php _e('When to use this:', 'msh-image-optimizer'); ?></strong>
                                <ul>
                                    <li><?php _e('You have old images with poor/missing metadata', 'msh-image-optimizer'); ?></li>
                                    <li><?php _e('You want to bulk-update metadata for your entire library', 'msh-image-optimizer'); ?></li>
                                    <li><?php _e('You\'ve changed business context and need fresh SEO content', 'msh-image-optimizer'); ?></li>
                                </ul>
                            </div>
                            <div class="ai-regen-stats-inline">
                                <div class="ai-stat-inline">
                                    <span class="ai-stat-inline-label"><?php _e('Credits:', 'msh-image-optimizer'); ?></span>
                                    <span class="ai-stat-inline-value" id="ai-credits-available">-</span>
                                    <span class="ai-stat-inline-sublabel" id="ai-plan-tier">-</span>
                                </div>
                                <div class="ai-stat-inline">
                                    <span class="ai-stat-inline-label"><?php _e('Last Run:', 'msh-image-optimizer'); ?></span>
                                    <span class="ai-stat-inline-value" id="ai-last-run"><?php esc_html_e('Never', 'msh-image-optimizer'); ?></span>
                                </div>
                                <div class="ai-stat-inline">
                                    <span class="ai-stat-inline-label"><?php _e('This Month:', 'msh-image-optimizer'); ?></span>
                                    <span class="ai-stat-inline-value" id="ai-credits-used-month">-</span>
                                    <span class="ai-stat-inline-sublabel"><?php _e('credits used', 'msh-image-optimizer'); ?></span>
                                </div>
                            </div>
                            <div class="ai-regen-actions-inline">
                                <button id="start-ai-regeneration" class="button button-dot-primary">
                                    <?php _e('Regenerate Metadata with AI', 'msh-image-optimizer'); ?>
                                </button>
                                <a href="<?php echo esc_url(admin_url('options-general.php?page=msh-image-optimizer-settings&tab=ai')); ?>" class="button button-dot-secondary">
                                    <?php _e('AI Settings', 'msh-image-optimizer'); ?>
                                </a>
                            </div>

                            <!-- Progress shown in existing analyze modal -->
                        </div>
                    </details>

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
                    <h2><?php _e('Advanced Tools (Developers)', 'msh-image-optimizer'); ?></h2>
                    <p><?php _e('Optional workflows for power users. Safe to ignore for day-to-day optimization.', 'msh-image-optimizer'); ?></p>

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

                <!-- WebP delivery status (advanced readout) -->
                <div class="msh-webp-status-section advanced-widget">
                    <h3><?php _e('WebP Delivery Status', 'msh-image-optimizer'); ?></h3>
                    <p class="webp-status-description"><?php _e('Reference dashboard showing the current detection method and delivery state. No action is typically required.', 'msh-image-optimizer'); ?></p>
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
                            <span id="webp-delivery-status" class="status-value"><?php esc_html_e('Active', 'msh-image-optimizer'); ?></span>
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

    public function ajax_save_onboarding_context() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array(
                    'message' => __('You do not have permission to update these settings.', 'msh-image-optimizer')
                ),
                403
            );
        }

        $previous_signature = get_option('msh_context_signature', '');

        $raw_context = isset($_POST['context']) ? $_POST['context'] : array();
        if (!is_array($raw_context)) {
            $raw_context = array();
        }

        $sanitized = MSH_Image_Optimizer_Context_Helper::sanitize_context($raw_context, true, isset($raw_context['updated_at']) ? absint($raw_context['updated_at']) : 0);
        $required_keys = array('business_name', 'industry', 'business_type', 'target_audience', 'brand_voice', 'uvp');
        $missing = array();

        foreach ($required_keys as $required_key) {
            if (empty($sanitized[$required_key])) {
                $missing[] = $required_key;
            }
        }

        if (!empty($missing)) {
            wp_send_json_error(
                array(
                    'message' => __('Please complete the required fields before continuing.', 'msh-image-optimizer'),
                    'missing' => $missing
                ),
                400
            );
        }

        update_option('msh_onboarding_context', $sanitized, false);
        $signature = MSH_Image_Optimizer_Context_Helper::build_context_signature($sanitized);
        update_option('msh_context_signature', $signature, false);
        if ($previous_signature !== $signature && class_exists('MSH_Image_Optimizer')) {
            $optimizer = MSH_Image_Optimizer::get_instance();
            if ($optimizer && method_exists($optimizer, 'handle_context_signature_change')) {
                $optimizer->handle_context_signature_change($previous_signature, $signature);
            }
        }

        $auto_index_queued = false;
        $queue_state = null;
        $bootstrap_flag = get_option('msh_usage_index_bootstrap_triggered', '0');
        $last_index_build = get_option('msh_usage_index_last_build');

        if ('1' !== $bootstrap_flag && empty($last_index_build) && class_exists('MSH_Usage_Index_Background')) {
            try {
                $queue_state = MSH_Usage_Index_Background::get_instance()->queue_rebuild('smart', false, 'onboarding');
                if (is_array($queue_state)) {
                    $auto_index_queued = true;
                    update_option('msh_usage_index_bootstrap_triggered', '1', false);
                }
            } catch (Exception $exception) {
                // Leave $auto_index_queued false so UI can prompt manually.
            }
        }

        wp_send_json_success(
            array(
                'context' => $sanitized,
                'summary' => MSH_Image_Optimizer_Context_Helper::format_summary($sanitized),
                'message' => __('Setup saved successfully.', 'msh-image-optimizer'),
                'context_signature' => $signature,
                'auto_index_queued' => $auto_index_queued,
                'queue_state' => $queue_state,
                'index_stats' => $this->get_usage_index_stats(),
            )
        );
    }

    public function ajax_reset_onboarding_context() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array(
                    'message' => __('You do not have permission to update these settings.', 'msh-image-optimizer')
                ),
                403
            );
        }

        $previous_signature = get_option('msh_context_signature', '');
        delete_option('msh_onboarding_context');

        $context = MSH_Image_Optimizer_Context_Helper::sanitize_context(array(), false);
        $signature = MSH_Image_Optimizer_Context_Helper::build_context_signature($context);
        update_option('msh_context_signature', $signature, false);
        if ($previous_signature !== $signature && class_exists('MSH_Image_Optimizer')) {
            $optimizer = MSH_Image_Optimizer::get_instance();
            if ($optimizer && method_exists($optimizer, 'handle_context_signature_change')) {
                $optimizer->handle_context_signature_change($previous_signature, $signature);
            }
        }

        wp_send_json_success(
            array(
                'context' => $context,
                'summary' => MSH_Image_Optimizer_Context_Helper::format_summary($context),
                'message' => __('Context cleared. You can complete the setup whenever you’re ready.', 'msh-image-optimizer'),
                'context_signature' => $signature,
            )
        );
    }

    public function ajax_set_active_context_profile() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(
                array(
                    'message' => __('You do not have permission to update these settings.', 'msh-image-optimizer')
                ),
                403
            );
        }

        $previous_signature = get_option('msh_context_signature', '');
        $requested = isset($_POST['profile_id']) ? sanitize_text_field(wp_unslash($_POST['profile_id'])) : 'primary';
        $profiles = MSH_Image_Optimizer_Context_Helper::get_profiles();

        if ('primary' !== $requested && !isset($profiles[$requested])) {
            wp_send_json_error(
                array(
                    'message' => __('The selected context profile no longer exists.', 'msh-image-optimizer')
                ),
                400
            );
        }

        update_option('msh_active_context_profile', $requested, false);

        if ('primary' === $requested) {
            $context = MSH_Image_Optimizer_Context_Helper::sanitize_context(get_option('msh_onboarding_context', array()), false);
            $summary = MSH_Image_Optimizer_Context_Helper::format_summary($context);
            $label = sprintf(
                __('Primary – %s', 'msh-image-optimizer'),
                !empty($context['business_name']) ? $context['business_name'] : __('Primary Context', 'msh-image-optimizer')
            );
        } else {
            $profile = $profiles[$requested];
            $context = $profile['context'];
            $summary = MSH_Image_Optimizer_Context_Helper::format_summary($context);
            $label = sprintf(
                __('Profile – %s', 'msh-image-optimizer'),
                !empty($profile['label']) ? $profile['label'] : __('Context profile', 'msh-image-optimizer')
            );
        }

        $signature = MSH_Image_Optimizer_Context_Helper::build_context_signature($context);
        update_option('msh_context_signature', $signature, false);
        if ($previous_signature !== $signature && class_exists('MSH_Image_Optimizer')) {
            $optimizer = MSH_Image_Optimizer::get_instance();
            if ($optimizer && method_exists($optimizer, 'handle_context_signature_change')) {
                $optimizer->handle_context_signature_change($previous_signature, $signature);
            }
        }

        wp_send_json_success(
            array(
                'profile_id' => $requested,
                'context' => $context,
                'summary' => $summary,
                'label' => $label,
                'index_stats' => $this->get_usage_index_stats(),
                'message' => __('Active context updated.', 'msh-image-optimizer'),
                'context_signature' => $signature,
            )
        );
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
                        'total_entries' => (int) $stats['summary']->total_entries,
                        'unique_attachments' => (int) $stats['summary']->indexed_attachments,
                        'last_update' => $stats['summary']->last_update,
                        'last_update_formatted' => $this->format_datetime($stats['summary']->last_update),
                    ];
                }
            }
        } catch (Exception $e) {
            // Debug logging removed for production
        }

        return false;
    }

    private function get_diagnostics_snapshot() {
        return array(
            'last_analyzer_run' => $this->format_datetime(get_option('msh_last_analyzer_run')),
            'last_optimization_run' => $this->format_datetime(get_option('msh_last_optimization_run')),
            'last_duplicate_scan' => $this->format_datetime(get_option('msh_last_duplicate_scan')),
            'last_visual_scan' => $this->format_datetime(get_option('msh_last_visual_scan')),
            'last_cli_run' => $this->format_datetime(get_option('msh_last_cli_optimization')),
        );
    }

    private function format_datetime($value) {
        if (empty($value)) {
            return __('—', 'msh-image-optimizer');
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            return $value;
        }

        $format = get_option('date_format') . ' ' . get_option('time_format');
        return date_i18n($format, $timestamp);
    }
}

// Initialize the admin interface
new MSH_Image_Optimizer_Admin();
