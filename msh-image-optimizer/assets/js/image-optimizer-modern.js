/**
 * MSH Image Optimizer - Modern UI Implementation
 * Complete rewrite with clean architecture and modern patterns
 */

(function($) {
    'use strict';

    // =============================================================================
    // SAFETY CHECKS
    // =============================================================================

    // Ensure mshImageOptimizer is available
    if (typeof mshImageOptimizer === 'undefined') {
        console.error('MSH Image Optimizer: mshImageOptimizer object not found. Script dependencies not loaded properly.');
        return;
    }

    // =============================================================================
    // CONFIGURATION & CONSTANTS
    // =============================================================================

    const CONFIG = {
        endpoints: {
            analyze: mshImageOptimizer.ajaxurl,
            optimize: mshImageOptimizer.ajaxurl
        },
        actions: {
            queueIndex: mshImageOptimizer.indexQueueAction || 'msh_queue_usage_index_rebuild',
            statusIndex: mshImageOptimizer.indexStatusAction || 'msh_get_usage_index_status'
        },
        nonce: mshImageOptimizer.nonce,
        batchSize: 5,
        autoRefreshInterval: 30000,
        queuePollInterval: 15000,
        indexStats: mshImageOptimizer.indexStats || null,
        diagnostics: mshImageOptimizer.diagnostics || {}
    };

    const STATUS_DEFINITIONS = {
        'optimized': { label: 'Optimized', badgeClass: 'status-optimized' },
        'needs_webp_conversion': { label: 'Needs WebP', badgeClass: 'status-needs-webp' },
        'webp_timestamp_missing': { label: 'WebP Check Needed', badgeClass: 'status-warning' },
        'ready_for_optimization': { label: 'Needs Optimization', badgeClass: 'status-needs-optimization' },
        'metadata_missing': { label: 'Needs Metadata', badgeClass: 'status-warning' },
        'needs_recompression': { label: 'Needs Update', badgeClass: 'status-warning' },
        'webp_missing': { label: 'WebP Missing', badgeClass: 'status-warning' },
        'webp_optimized': { label: 'WebP Active', badgeClass: 'status-optimized' },
        'context_stale': { label: 'Context Updated', badgeClass: 'status-warning' },
        'needs_attention': { label: 'Attention Required', badgeClass: 'status-warning' }
    };

    function isLocationSpecific(image, contextOverride) {
        if (!image && !contextOverride) {
            return false;
        }

        const directFlag = image && (image.location_specific === true || image.location_specific === 1 || image.location_specific === '1');
        const contextDetails = contextOverride || (image && image.context_details) || {};
        const contextFlag = contextDetails && (contextDetails.location_specific === true || contextDetails.location_specific === 1 || contextDetails.location_specific === '1');

        return !!(directFlag || contextFlag);
    }

    // =============================================================================
    // STATE MANAGEMENT
    // =============================================================================

    const AppState = {
        images: [],
        filters: {
            status: 'all',  // Temporarily default to showing all results for verification
            priority: 'all',              // 'all', 'high', 'medium', 'low'
            issues: 'all',                // 'all', 'missing_alt', 'no_webp'
            search: ''
        },
        sort: {
            column: null,
            direction: 'asc'
        },
        renameEnabled: Boolean(Number((window.mshImageOptimizer && mshImageOptimizer.renameEnabled) || 0)),
        processing: false,
        stats: {
            total: 0,
            optimized: 0,
            remaining: 0,
            percentage: 0
        }
    };

    const WIZARD_STORAGE_KEY = 'msh_wizard_state_v1';

    const Onboarding = {
        init() {
            this.$container = $('#msh-onboarding-container');
            if (!this.$container.length) {
                return;
            }

            this.$formWrapper = $('#msh-onboarding-form');
            this.$form = $('#msh-onboarding-form-element');
            this.$steps = this.$form.find('.onboarding-step');
            this.$message = $('#onboarding-message');
            this.$progressBar = $('#onboarding-progress-bar');
            this.$progressLabel = $('#onboarding-progress-label');
            this.$btnPrev = this.$formWrapper.find('.wizard-prev');
            this.$btnNext = this.$formWrapper.find('.wizard-next');
            this.$btnSave = this.$formWrapper.find('.wizard-save');
            this.$summary = $('#msh-onboarding-summary');
            this.$summarySettings = this.$summary.find('.summary-settings');
            this.$summaryReset = this.$summary.find('.summary-reset');
            this.settingsUrl = mshImageOptimizer.settingsUrl || '';
            this.$indexStatusValue = $('#summary-index-status-value');

            this.totalSteps = this.$steps.length || 1;
            this.currentStep = 1;
            this.isBusy = false;
            this.isComplete = Boolean(mshImageOptimizer.onboardingComplete);
            this.labels = mshImageOptimizer.onboardingLabels || {};
            this.strings = mshImageOptimizer.strings || {};
            this.context = $.extend(true, {}, mshImageOptimizer.onboardingContext || {});
            this.summary = $.extend(true, {}, mshImageOptimizer.onboardingSummary || {});
            this.primaryContext = $.extend(true, {}, this.context);
            this.primarySummary = $.extend(true, {}, this.summary);
            this.profiles = Array.isArray(window.mshImageOptimizer.contextProfiles) ? window.mshImageOptimizer.contextProfiles : [];
            this.profileMap = {};
            (this.profiles || []).forEach((profile) => {
                if (profile && profile.id) {
                    this.profileMap[profile.id] = profile;
                }
            });
            this.activeProfileId = window.mshImageOptimizer.activeProfile || 'primary';
            this.$contextSelector = $('#msh-context-selector');
            this.$activeLabel = $('#summary-active-label');
            this.contextChangeInFlight = false;
            this.indexStats = window.mshImageOptimizer.indexStats || null;

            this.prefillForm();
            this.bindEvents();
            this.initializeContextSwitcher();

            if (this.isComplete) {
                this.showSummary();
            } else {
                this.showForm(true);
            }

            this.setActiveProfile(this.activeProfileId, { suppressMessage: true });
            this.updateIndexStatusLabel(this.indexStats || null);
            this.updateProgress();
            this.updateNav();
        },

        bindEvents() {
            if (!this.$form.length) {
                return;
            }

            this.$btnNext.on('click', (event) => {
                event.preventDefault();
                this.nextStep();
            });

            this.$btnPrev.on('click', (event) => {
                event.preventDefault();
                this.prevStep();
            });

            this.$btnSave.on('click', (event) => {
                event.preventDefault();
                this.save();
            });

            this.$summarySettings.on('click', (event) => {
                event.preventDefault();
                this.openSettings();
            });

            this.$summaryReset.on('click', (event) => {
                event.preventDefault();
                this.resetContext();
            });

            this.$form.on('input change', 'input, select, textarea', (event) => {
                const $field = $(event.currentTarget);
                if ($field.hasClass('field-error')) {
                    $field.removeClass('field-error');
                }
                const $radioWrapper = $field.closest('.radio-grid');
                if ($radioWrapper.hasClass('field-error')) {
                    $radioWrapper.removeClass('field-error');
                }
                if (this.$message.is(':visible')) {
                    this.hideMessage();
                }
            });
        },

        initializeContextSwitcher() {
            if (!this.$contextSelector || !this.$contextSelector.length) {
                return;
            }

            this.$contextSelector.on('change', (event) => {
                const selected = $(event.currentTarget).val();
                this.handleContextChange(selected);
            });
        },

        getContextPayload(profileId) {
            if (!profileId || profileId === 'primary') {
                return {
                    id: 'primary',
                    context: $.extend(true, {}, this.primaryContext || {}),
                    summary: $.extend(true, {}, this.primarySummary || {}),
                    label: this.getProfileLabel('primary')
                };
            }

            const profile = this.profileMap[profileId];
            if (!profile) {
                return null;
            }

            const context = profile.context ? $.extend(true, {}, profile.context) : {};
            const summary = profile.summary && Object.keys(profile.summary || {}).length
                ? $.extend(true, {}, profile.summary)
                : this.formatSummary(context);

            return {
                id: profileId,
                context,
                summary,
                label: this.getProfileLabel(profileId)
            };
        },

        getProfileLabel(profileId) {
            if (profileId === 'primary' || !profileId) {
                const template = this.strings.primaryProfileLabel || 'Primary – %s';
                const fallback = this.strings.primaryContextFallback || 'Primary Context';
                const name = this.primaryContext && this.primaryContext.business_name
                    ? this.primaryContext.business_name
                    : fallback;
                return template.replace('%s', name);
            }

            const profile = this.profileMap[profileId];
            if (!profile) {
                return '';
            }

            const template = this.strings.profileLabelTemplate || 'Profile – %s';
            const fallback = this.strings.profileContextFallback || 'Context profile';
            const label = profile.label || fallback;
            return template.replace('%s', label);
        },

        setActiveProfile(profileId, options = {}) {
            const settings = $.extend({
                contextOverride: null,
                summaryOverride: null,
                labelOverride: null,
                suppressMessage: false
            }, options || {});

            const payload = this.getContextPayload(profileId);
            if (!payload) {
                return;
            }

            const context = settings.contextOverride ? $.extend(true, {}, settings.contextOverride) : payload.context;
            const summary = settings.summaryOverride ? $.extend(true, {}, settings.summaryOverride) : payload.summary;

            if (profileId === 'primary') {
                this.primaryContext = $.extend(true, {}, context);
                this.primarySummary = $.extend(true, {}, summary);
            } else if (this.profileMap[profileId]) {
                this.profileMap[profileId].context = $.extend(true, {}, context);
                this.profileMap[profileId].summary = $.extend(true, {}, summary);
            }

            this.activeProfileId = profileId;
            this.context = $.extend(true, {}, context);
            this.summary = $.extend(true, {}, summary);
            this.applySummaryData(this.summary);
            this.updateActiveLabel(settings.labelOverride || payload.label || '');

            if (this.$contextSelector && this.$contextSelector.length) {
                this.$contextSelector.val(profileId);
            }

            window.mshImageOptimizer.activeProfile = profileId;
            window.mshImageOptimizer.onboardingContext = this.context;
            window.mshImageOptimizer.onboardingSummary = this.summary;
            window.mshImageOptimizer.indexStats = this.indexStats;
            this.updateIndexStatusLabel(this.indexStats || null);

            if (!settings.suppressMessage && this.strings.contextSwitchSuccess) {
                this.showMessage('success', this.strings.contextSwitchSuccess);
            }
        },

        updateActiveLabel(label) {
            if (!this.$activeLabel || !this.$activeLabel.length) {
                return;
            }
            this.$activeLabel.text(label || '');
        },

        updateIndexStatusLabel(indexStats = null, queued = false) {
            if (!this.$indexStatusValue || !this.$indexStatusValue.length) {
                return;
            }

            if (queued) {
                this.$indexStatusValue
                    .text(this.strings.indexStatusQueued || 'Usage index building…');
                this.$indexStatusValue.attr('data-status', 'queued');
                return;
            }

            if (indexStats && indexStats.last_update_formatted) {
                const template = this.strings.indexStatusReady || 'Usage index ready – last refreshed %s';
                this.$indexStatusValue
                    .text(template.replace('%s', indexStats.last_update_formatted));
                this.$indexStatusValue.attr('data-status', 'ready');
            } else {
                this.$indexStatusValue
                    .text(this.strings.indexStatusNotBuilt || 'Usage index not built yet');
                this.$indexStatusValue.attr('data-status', 'none');
            }
        },

        disableContextSelector(disabled) {
            if (!this.$contextSelector || !this.$contextSelector.length) {
                return;
            }
            this.$contextSelector.prop('disabled', Boolean(disabled));
        },

        handleContextChange(profileId) {
            const targetId = profileId || 'primary';
            if (targetId === this.activeProfileId || this.contextChangeInFlight) {
                if (this.$contextSelector && this.$contextSelector.length) {
                    this.$contextSelector.val(this.activeProfileId);
                }
                return;
            }

            if (!this.profileMap[targetId] && targetId !== 'primary') {
                if (this.$contextSelector && this.$contextSelector.length) {
                    this.$contextSelector.val(this.activeProfileId);
                }
                return;
            }

            this.contextChangeInFlight = true;
            this.disableContextSelector(true);
            this.hideMessage();

            const previous = this.activeProfileId;

            $.post(mshImageOptimizer.ajaxurl, {
                action: 'msh_set_active_context_profile',
                nonce: CONFIG.nonce,
                profile_id: targetId
            })
            .done((response) => {
                const data = response && response.data ? response.data : {};
                const context = data.context || this.getContextPayload(targetId)?.context;
                const summary = data.summary || this.getContextPayload(targetId)?.summary;
                const label = data.label || this.getProfileLabel(targetId);

                if (data.index_stats) {
                    this.indexStats = data.index_stats;
                }

                this.setActiveProfile(targetId, {
                    contextOverride: context,
                    summaryOverride: summary,
                    labelOverride: label,
                    suppressMessage: true
                });

                this.updateIndexStatusLabel(this.indexStats || null);

                if (data.message) {
                    this.showMessage('success', data.message);
                } else if (this.strings.contextSwitchSuccess) {
                    this.showMessage('success', this.strings.contextSwitchSuccess);
                }
            })
            .fail(() => {
                if (this.$contextSelector && this.$contextSelector.length) {
                    this.$contextSelector.val(previous);
                }
                this.showMessage('error', this.strings.contextSwitchError || 'Unable to change the active context right now.');
            })
            .always(() => {
                this.contextChangeInFlight = false;
                this.disableContextSelector(false);
            });
        },

        prefillForm() {
            if (!this.$form.length) {
                return;
            }

            this.fillFormFromContext(this.context);
        },

        fillFormFromContext(context) {
            const data = context || {};

            this.$form.find('input[type="text"], input[type="search"], textarea').each((_, element) => {
                const $element = $(element);
                const name = $element.attr('name');
                if (!name || name === 'ai_interest') {
                    return;
                }
                const value = Object.prototype.hasOwnProperty.call(data, name) ? data[name] : '';
                $element.val(value);
            });

            this.$form.find('select').each((_, element) => {
                const $element = $(element);
                const name = $element.attr('name');
                if (!name) {
                    return;
                }
                const value = Object.prototype.hasOwnProperty.call(data, name) ? data[name] : '';
                $element.val(value);
            });

            this.$form.find('input[name="brand_voice"]').prop('checked', false);
            if (data.brand_voice) {
                this.$form.find(`input[name="brand_voice"][value="${data.brand_voice}"]`).prop('checked', true);
            }

            this.$form.find('input[name="ai_interest"]').prop('checked', Boolean(data.ai_interest));
        },

        showForm(resetStep = false) {
            this.isComplete = false;
            this.clearAllErrors();
            this.hideMessage();
            this.$summary.hide();
            this.$formWrapper.show();
            if (resetStep) {
                this.currentStep = 1;
            }
            this.showStep(this.currentStep);
            this.updateProgress();
            this.updateNav();
        },

        showSummary() {
            this.isComplete = true;
            this.$formWrapper.hide();
            this.$summary.show();
            this.updateProgress();
            this.updateNav();
        },

        showStep(step) {
            const targetStep = Math.min(Math.max(step, 1), this.totalSteps);
            this.currentStep = targetStep;
            this.$steps.removeClass('is-active');
            const $activeStep = this.$steps.filter(`[data-step="${targetStep}"]`);
            if ($activeStep.length) {
                $activeStep.addClass('is-active');
            }
            this.updateProgress();
            this.updateNav();
        },

        nextStep() {
            if (this.isBusy) {
                return;
            }
            if (!this.validateStep(this.currentStep)) {
                return;
            }
            if (this.currentStep < this.totalSteps) {
                this.showStep(this.currentStep + 1);
            }
        },

        prevStep() {
            if (this.isBusy) {
                return;
            }
            if (this.currentStep > 1) {
                this.showStep(this.currentStep - 1);
            }
        },

        updateProgress() {
            if (!this.$progressBar.length) {
                return;
            }

            if (this.isComplete) {
                this.$progressBar.css('width', '100%');
                if (this.$progressLabel.length) {
                    this.$progressLabel.text(
                        this.strings.onboardingProgressComplete || 'Setup complete'
                    );
                }
                return;
            }

            const percent = Math.round(((this.currentStep - 1) / Math.max(this.totalSteps, 1)) * 100);
            this.$progressBar.css('width', `${percent}%`);

            if (this.$progressLabel.length) {
                const template = this.strings.onboardingProgressLabel || 'Step %1$d of %2$d';
                this.$progressLabel.text(
                    template.replace('%1$d', this.currentStep).replace('%2$d', this.totalSteps)
                );
            }
        },

        updateNav() {
            const atFirstStep = this.currentStep <= 1;
            const atLastStep = this.currentStep >= this.totalSteps;

            this.$btnPrev.prop('disabled', this.isBusy || atFirstStep);
            this.$btnNext.toggle(!atLastStep);
            this.$btnNext.prop('disabled', this.isBusy);
            this.$btnSave.toggle(atLastStep);
            this.$btnSave.prop('disabled', this.isBusy);
            this.$summaryReset.prop('disabled', this.isBusy);

            if (this.isComplete && this.$summarySettings && this.$summarySettings.length) {
                if (this.settingsUrl) {
                    this.$summarySettings.attr('href', this.settingsUrl);
                } else {
                    this.$summarySettings.attr('href', '#');
                }
            }
        },

        getStepRequiredFields(step) {
            switch (step) {
                case 1:
                    return ['business_name', 'industry', 'business_type'];
                case 2:
                    return ['target_audience'];
                case 3:
                    return ['brand_voice', 'uvp'];
                default:
                    return [];
            }
        },

        validateStep(step, options = {}) {
            const { showMessage = true } = options;
            const $step = this.$steps.filter(`[data-step="${step}"]`);
            if (!$step.length) {
                return true;
            }

            this.clearStepErrors(step);

            let valid = true;
            const requiredFields = this.getStepRequiredFields(step);

            requiredFields.forEach((name) => {
                if (name === 'brand_voice') {
                    const $radio = this.$form.find('input[name="brand_voice"]:checked');
                    if (!$radio.length) {
                        valid = false;
                        this.$form.find('.radio-grid').addClass('field-error');
                    }
                    return;
                }

                const $field = this.$form.find(`[name="${name}"]`);
                const value = $field.val ? $field.val() : '';
                if (!value || (Array.isArray(value) && value.length === 0)) {
                    valid = false;
                    $field.addClass('field-error');
                }
            });

            if (!valid && showMessage) {
                this.showMessage('error', this.strings.onboardingValidationError || 'Please complete the required fields before continuing.');
            }

            return valid;
        },

        validateAll() {
            for (let step = 1; step <= this.totalSteps; step += 1) {
                if (!this.validateStep(step, { showMessage: false })) {
                    this.showStep(step);
                    this.showMessage('error', this.strings.onboardingValidationError || 'Please complete the required fields before continuing.');
                    return false;
                }
            }
            return true;
        },

        clearStepErrors(step) {
            const $step = this.$steps.filter(`[data-step="${step}"]`);
            if (!$step.length) {
                return;
            }

            $step.find('.field-error').removeClass('field-error');
        },

        collectFormData() {
            const raw = {};
            const serialized = this.$form.serializeArray();

            serialized.forEach((entry) => {
                raw[entry.name] = entry.value.trim();
            });

            raw.ai_interest = this.$form.find('input[name="ai_interest"]').is(':checked') ? '1' : '';

            return raw;
        },

        toggleBusy(state) {
            this.isBusy = Boolean(state);
            this.updateNav();
        },

        save() {
            if (this.isBusy) {
                return;
            }

            if (!this.validateStep(this.currentStep) || !this.validateAll()) {
                return;
            }

            const payload = this.collectFormData();
            this.toggleBusy(true);

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'msh_save_onboarding_context',
                    nonce: CONFIG.nonce,
                    context: payload
                }
            })
            .done((response) => {
                if (response && response.success) {
                    const context = response.data && response.data.context ? response.data.context : payload;
                    const summary = response.data && response.data.summary ? response.data.summary : this.formatSummary(context);
                    const message = response.data && response.data.message
                        ? response.data.message
                        : (this.strings.onboardingSaveSuccess || 'Setup saved successfully.');

                    this.primaryContext = $.extend(true, {}, context);
                    this.primarySummary = $.extend(true, {}, summary);

                    if (this.activeProfileId === 'primary') {
                        this.setActiveProfile('primary', {
                            contextOverride: this.primaryContext,
                            summaryOverride: this.primarySummary,
                            suppressMessage: true
                        });
                    }

                    if (response.data && response.data.index_stats) {
                        this.indexStats = response.data.index_stats;
                    }

                    if (response.data && response.data.auto_index_queued) {
                        this.updateIndexStatusLabel(this.indexStats || null, true);
                    } else {
                        this.updateIndexStatusLabel(this.indexStats || null);
                    }

                    this.showMessage('success', message);
                    this.showSummary();

                    window.mshImageOptimizer.onboardingContext = this.context;
                    window.mshImageOptimizer.onboardingSummary = this.summary;
                    window.mshImageOptimizer.onboardingComplete = true;
                } else {
                    const errorMessage = response && response.data && response.data.message
                        ? response.data.message
                        : (this.strings.onboardingSaveError || 'Unable to save the setup right now. Please try again.');
                    this.showMessage('error', errorMessage);
                }
            })
            .fail(() => {
                this.showMessage('error', this.strings.onboardingSaveError || 'Unable to save the setup right now. Please try again.');
            })
            .always(() => {
                this.toggleBusy(false);
            });
        },

        resetContext() {
            if (this.isBusy) {
                return;
            }

            const confirmationMessage = this.strings.onboardingResetConfirm || 'Reset the saved context? This will clear all onboarding answers.';
            if (!window.confirm(confirmationMessage)) {
                return;
            }

            this.toggleBusy(true);

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'msh_reset_onboarding_context',
                    nonce: CONFIG.nonce
                }
            })
            .done((response) => {
                if (response && response.success) {
                    const context = response.data && response.data.context ? response.data.context : {};
                    const summary = response.data && response.data.summary ? response.data.summary : this.formatSummary(context);
                    const message = response.data && response.data.message
                        ? response.data.message
                        : (this.strings.onboardingResetDone || 'Context cleared. You can complete the setup whenever you’re ready.');

                    this.primaryContext = $.extend(true, {}, context);
                    this.primarySummary = $.extend(true, {}, summary);
                    this.fillFormFromContext(context);
                    this.clearAllErrors();
                    this.showForm(true);
                    this.setActiveProfile('primary', {
                        contextOverride: this.primaryContext,
                        summaryOverride: this.primarySummary,
                        suppressMessage: true
                    });
                    this.showMessage('success', message);
                    this.updateIndexStatusLabel(this.indexStats || null);

                    window.mshImageOptimizer.onboardingContext = this.context;
                    window.mshImageOptimizer.onboardingSummary = this.summary;
                    window.mshImageOptimizer.onboardingComplete = false;
                } else {
                    const errorMessage = response && response.data && response.data.message
                        ? response.data.message
                        : (this.strings.onboardingResetError || 'Unable to reset the setup right now. Please try again.');
                    this.showMessage('error', errorMessage);
                }
            })
            .fail(() => {
                this.showMessage('error', this.strings.onboardingResetError || 'Unable to reset the setup right now. Please try again.');
            })
            .always(() => {
                this.toggleBusy(false);
            });
        },

        clearAllErrors() {
            if (!this.$form.length) {
                return;
            }
            this.$form.find('.field-error').removeClass('field-error');
        },

        openSettings() {
            if (!this.settingsUrl) {
                return;
            }
            window.location.href = this.settingsUrl;
        },

        showMessage(type, text) {
            if (!this.$message.length) {
                return;
            }

            const normalizedType = type === 'success' ? 'is-success' : 'is-error';

            this.$message
                .removeClass('is-error is-success')
                .addClass(normalizedType)
                .text(text || '')
                .show();
        },

        hideMessage() {
            if (!this.$message.length) {
                return;
            }

            this.$message.removeClass('is-error is-success').hide();
        },

        updateSummary(context, summary) {
            this.primaryContext = $.extend(true, {}, context || {});
            this.primarySummary = summary && Object.keys(summary || {}).length
                ? $.extend(true, {}, summary)
                : this.formatSummary(this.primaryContext);

            if (this.activeProfileId === 'primary') {
                this.setActiveProfile('primary', {
                    contextOverride: this.primaryContext,
                    summaryOverride: this.primarySummary,
                    suppressMessage: true
                });
            }
        },

        applySummaryData(summary) {
            if (!this.$summary.length) {
                return;
            }

            const mappings = {
                business_name: '#summary-business-name',
                industry: '#summary-industry',
                business_type: '#summary-business-type',
                target_audience: '#summary-target-audience',
                pain_points: '#summary-pain-points',
                brand_voice: '#summary-brand-voice',
                uvp: '#summary-uvp',
                cta_preference: '#summary-cta',
                location: '#summary-location',
                ai_interest: '#summary-ai-interest'
            };

            Object.entries(mappings).forEach(([key, selector]) => {
                const value = summary && Object.prototype.hasOwnProperty.call(summary, key) ? summary[key] : '';
                const displayValue = value && typeof value === 'string' ? value : '';
                const $element = this.$summary.find(selector);
                if ($element.length) {
                    $element.text(displayValue || '—');
                }
            });
        },

        formatSummary(context) {
            const labels = this.labels || {};
            const labelFor = (group, value) => {
                if (!value) {
                    return '';
                }
                if (labels[group] && labels[group][value]) {
                    return labels[group][value];
                }
                return value;
            };

            const audienceParts = [];
            if (context.target_audience) {
                audienceParts.push(context.target_audience);
            }
            if (context.demographics) {
                const demographicLabel = this.strings.onboardingSummaryDemographics
                    ? this.strings.onboardingSummaryDemographics.replace('%s', context.demographics)
                    : `Demographics: ${context.demographics}`;
                audienceParts.push(demographicLabel);
            }

            const locationParts = [];
            if (context.city) {
                locationParts.push(context.city);
            }
            if (context.region) {
                locationParts.push(context.region);
            }
            if (context.country) {
                locationParts.push(context.country);
            }

            let location = locationParts.join(', ');
            if (context.service_area) {
                const serviceLabel = this.strings.onboardingSummaryServiceArea
                    ? this.strings.onboardingSummaryServiceArea.replace('%s', context.service_area)
                    : `Service area: ${context.service_area}`;
                location = location ? `${location} (${serviceLabel})` : serviceLabel;
            }

            if (!location) {
                location = this.strings.onboardingSummaryNotSpecified || 'Not specified';
            }

            return {
                business_name: context.business_name || '',
                industry: labelFor('industry', context.industry),
                business_type: labelFor('business_type', context.business_type),
                target_audience: audienceParts.join(' — '),
                pain_points: context.pain_points || '',
                brand_voice: labelFor('brand_voice', context.brand_voice),
                uvp: context.uvp || '',
                cta_preference: labelFor('cta_preference', context.cta_preference),
                location,
                ai_interest: context.ai_interest
                    ? (this.strings.onboardingSummaryAiYes || 'Subscribed to updates')
                    : (this.strings.onboardingSummaryAiNo || 'No updates requested')
            };
        }
    };

    class Wizard {
        static init() {
            if (!this.state) {
                this.state = {
                    dismissed: true,
                    steps: {
                        1: 'pending',
                        2: 'pending',
                        3: 'pending',
                        4: 'pending',
                        5: 'upcoming'
                    }
                };
            }
            this.$wizard = $('#msh-onboarding-wizard');
            this.$processedList = $('#scheduler-processed-list');
            this.$processedContainer = $('.scheduler-attachments');
            this.$progressBar = $('#wizard-progress-bar');
            this.$progressLabel = $('#wizard-progress-label');
            this.totalTrackedSteps = 4;

            if (!this.$wizard.length) {
                this.state.dismissed = true;
                return;
            }

            this.$steps = this.$wizard.find('.wizard-step');
            this.totalTrackedSteps = this.$steps.filter((_, el) => parseInt($(el).data('step'), 10) <= 4).length;

            this.loadState();
            this.bindUI();
            this.updateUI();
        }

        static loadState() {
            const defaultState = {
                dismissed: false,
                steps: {
                    1: 'active',
                    2: 'pending',
                    3: 'pending',
                    4: 'pending',
                    5: 'upcoming'
                }
            };

            try {
                const stored = localStorage.getItem(WIZARD_STORAGE_KEY);
                if (stored) {
                    const parsed = JSON.parse(stored);
                    this.state = Object.assign({}, defaultState, parsed);
                } else {
                    this.state = defaultState;
                }
            } catch (error) {
                console.warn('Wizard state load failed:', error);
                this.state = defaultState;
            }

            if (this.state.dismissed) {
                this.$wizard.hide();
            }
        }

        static saveState() {
            try {
                localStorage.setItem(WIZARD_STORAGE_KEY, JSON.stringify(this.state));
            } catch (error) {
                console.warn('Wizard state save failed:', error);
            }
        }

        static bindUI() {
            this.$wizard.on('click', '.wizard-action', (event) => {
                event.preventDefault();
                const action = $(event.currentTarget).data('action');
                this.triggerAction(action);
            });

            $('#wizard-restart').on('click', (event) => {
                event.preventDefault();
                this.reset();
            });

            $('#wizard-dismiss').on('click', (event) => {
                event.preventDefault();
                this.dismiss();
            });
        }

        static triggerAction(action) {
            switch (action) {
                case 'analyze':
                    this.setStepStatus(1, 'active');
                    Analysis.run(false);
                    break;
                case 'view-analysis-log':
                    const log = document.getElementById('optimization-log');
                    if (log) {
                        log.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    break;
                case 'optimize-high':
                    this.setStepStatus(2, 'active');
                    Optimization.runByPriority('high');
                    break;
                case 'optimize-medium':
                    this.setStepStatus(2, 'active');
                    Optimization.runByPriority('medium');
                    break;
                case 'duplicate-quick':
                    this.setStepStatus(3, 'active');
                    DuplicateCleanup.runQuickScan();
                    break;
                case 'duplicate-deep':
                    this.setStepStatus(3, 'active');
                    DuplicateCleanup.runDeepScan();
                    break;
                case 'smart-build':
                    this.setStepStatus(4, 'active');
                    $('#rebuild-usage-index').trigger('click');
                    break;
                case 'force-build':
                    this.setStepStatus(4, 'active');
                    $('#force-rebuild-usage-index').trigger('click');
                    break;
                case 'ai-preview':
                    alert('AI-powered mode is coming soon. Stay tuned!');
                    break;
                default:
                    console.warn('Unknown wizard action:', action);
            }
        }

        static dismiss() {
            this.state.dismissed = true;
            this.saveState();
            this.$wizard.slideUp();
        }

        static reset() {
            this.state.dismissed = false;
            this.state.steps = {
                1: 'active',
                2: 'pending',
                3: 'pending',
                4: 'pending',
                5: 'upcoming'
            };
            this.saveState();
            this.$wizard.slideDown();
            this.updateUI();
            this.recordProcessedAttachments([]);
        }

        static setStepStatus(step, status) {
            const stepNumber = parseInt(step, 10);
            if (!this.state || !this.state.steps || !this.state.steps[stepNumber]) {
                return;
            }

            this.state.steps[stepNumber] = status;

            if (status === 'complete') {
                const next = this.getNextTrackedStep(stepNumber + 1);
                if (next) {
                    if (this.state.steps[next] !== 'complete') {
                        this.state.steps[next] = 'active';
                    }
                }
            } else if (status === 'active' && this.$steps && this.$steps.length) {
                // Downgrade later steps to pending if they are not complete
                this.$steps.each((_, element) => {
                    const num = parseInt($(element).data('step'), 10);
                    if (num > stepNumber && this.state.steps[num] !== 'complete' && num <= 4) {
                        this.state.steps[num] = 'pending';
                    }
                });
            }

            this.saveState();
            this.updateUI();
        }

        static ensureActive(step) {
            if (!this.state || !this.state.steps) {
                return;
            }

            if (this.state.steps[step] === 'complete') {
                return;
            }

            this.setStepStatus(step, 'active');
        }

        static getNextTrackedStep(start) {
            let candidate = start;
            while (candidate <= 4) {
                if (this.state.steps[candidate] && this.state.steps[candidate] !== 'complete') {
                    return candidate;
                }
                candidate += 1;
            }
            return null;
        }

        static handleEvent(event) {
            switch (event) {
                case 'analysis-complete':
                    this.setStepStatus(1, 'complete');
                    break;
                case 'analysis-reset':
                    this.setStepStatus(1, 'active');
                    break;
                case 'optimize-complete':
                    this.setStepStatus(2, 'complete');
                    break;
                case 'duplicate-complete':
                    this.setStepStatus(3, 'complete');
                    break;
                case 'index-complete':
                    this.setStepStatus(4, 'complete');
                    break;
                default:
                    break;
            }
        }

        static updateUI() {
            if (!this.$wizard || !this.$wizard.length) return;

            if (this.state.dismissed) {
                this.$wizard.hide();
            } else if (!this.$wizard.is(':visible')) {
                this.$wizard.show();
            }

            this.$steps.each((_, element) => {
                const $step = $(element);
                const stepNumber = parseInt($step.data('step'), 10);
                let status = this.state.steps[stepNumber] || 'pending';

                if (stepNumber === 5) {
                    status = 'upcoming';
                }

                $step.attr('data-status', status);

                const $statusLabel = $step.find('.wizard-step-status');
                if ($statusLabel.length) {
                    switch (status) {
                        case 'complete':
                            $statusLabel.text(window.mshImageOptimizer?.strings?.wizardComplete || 'Complete');
                            break;
                        case 'active':
                            $statusLabel.text(window.mshImageOptimizer?.strings?.wizardActive || 'In progress');
                            break;
                        case 'upcoming':
                            $statusLabel.text(window.mshImageOptimizer?.strings?.wizardUpcoming || 'Coming soon');
                            break;
                        default:
                            $statusLabel.text(window.mshImageOptimizer?.strings?.wizardPending || 'Pending');
                    }
                }
            });

            const completed = Object.entries(this.state.steps)
                .filter(([step, status]) => parseInt(step, 10) <= 4 && status === 'complete').length;
            const progressPercent = this.totalTrackedSteps > 0 ? Math.round((completed / this.totalTrackedSteps) * 100) : 0;
            if (this.$progressBar.length) {
                this.$progressBar.css('width', `${progressPercent}%`);
            }
            if (this.$progressLabel.length) {
                if (completed >= this.totalTrackedSteps) {
                    this.$progressLabel.text(window.mshImageOptimizer?.strings?.wizardComplete || 'Complete');
                } else {
                    this.$progressLabel.text(`Step ${Math.min(completed + 1, this.totalTrackedSteps)} of ${this.totalTrackedSteps}`);
                }
            }
        }

        static recordProcessedAttachments(list) {
            if (!this.$processedList.length) return;

            this.$processedList.empty();
            (list || []).forEach((label) => {
                this.$processedList.append(`<li>${UI.escapeHtml(label)}</li>`);
            });

            if (this.$processedContainer && this.$processedContainer.length) {
                if ((list || []).length) {
                    this.$processedContainer.show();
                } else {
                    this.$processedContainer.hide();
                }
            }
        }
    }
    // =============================================================================
    // MODERN FILTER SYSTEM
    // =============================================================================

    class FilterEngine {
        static apply() {
            const filtered = this.getFilteredImages();
            UI.renderResults(filtered);
            UI.updateStats();
        }

        static setFilter(type, value) {
            AppState.filters[type] = value;
            this.apply();
        }

        static reset() {
            AppState.filters = {
                status: 'all',
                priority: 'all',
                issues: 'all',
                search: ''
            };
            AppState.sort = {
                column: null,
                direction: 'asc'
            };
            UI.updateFilterControls();
            this.apply();
        }

        static getFilteredImages() {
            const { images, filters } = AppState;

            const filtered = images.filter(image => {
                // Status filter
                if (filters.status !== 'all') {
                    const isOptimized = image.optimization_status === 'optimized';
                    if (filters.status === 'optimized' && !isOptimized) return false;
                    if (filters.status === 'needs_optimization' && isOptimized) return false;
                }

                // Priority filter
                if (filters.priority !== 'all') {
                    const priority = parseInt(image.priority);
                    switch (filters.priority) {
                        case 'high': if (priority < 15) return false; break;
                        case 'medium': if (priority < 10 || priority >= 15) return false; break;
                        case 'low': if (priority >= 10) return false; break;
                    }
                }

                // Filename filter
                if (filters.filename !== 'all') {
                    const hasSuggestion = image.suggested_filename && image.suggested_filename.trim() !== '';
                    switch (filters.filename) {
                        case 'has_suggestion': if (!hasSuggestion) return false; break;
                        case 'no_suggestion': if (hasSuggestion) return false; break;
                    }
                }

                // Issues filter
                if (filters.issues !== 'all') {
                    const issues = image.issues || [];
                    switch (filters.issues) {
                        case 'missing_alt': if (!issues.some(i => i.type === 'alt')) return false; break;
                        case 'no_webp': if (!issues.some(i => i.type === 'webp')) return false; break;
                        case 'large_size':
                            const fileSize = parseInt(image.file_size) || 0;
                            if (fileSize <= 1048576) return false;
                            break;
                    }
                }

                // Search filter
                if (filters.search) {
                    const searchTerm = filters.search.toLowerCase();
                    const searchable = [
                        image.filename,
                        image.title,
                        image.alt_text,
                        image.context_label
                    ].join(' ').toLowerCase();

                    if (!searchable.includes(searchTerm)) return false;
                }

                return true;
            });

            return this.applySorting(filtered);
        }

        static applySorting(images) {
            const { column, direction } = AppState.sort;
            if (!column) {
                return images;
            }

            const sorted = [...images].sort((a, b) => {
                const dir = direction === 'asc' ? 1 : -1;

                if (column === 'filename') {
                    const aName = (a.filename || '').toString().toLowerCase();
                    const bName = (b.filename || '').toString().toLowerCase();
                    return aName.localeCompare(bName) * dir;
                }

                if (column === 'priority') {
                    const aPriority = parseInt(a.priority, 10) || 0;
                    const bPriority = parseInt(b.priority, 10) || 0;
                    if (aPriority === bPriority) {
                        return ((a.filename || '').toString().toLowerCase()).localeCompare((b.filename || '').toString().toLowerCase()) * dir;
                    }
                    return (aPriority - bPriority) * dir;
                }

                if (column === 'size') {
                    const aSize = parseInt(a.file_size, 10) || 0;
                    const bSize = parseInt(b.file_size, 10) || 0;
                    if (aSize === bSize) {
                        return ((a.filename || '').toString().toLowerCase()).localeCompare((b.filename || '').toString().toLowerCase()) * dir;
                    }
                    return (aSize - bSize) * dir;
                }

                return 0;
            });

            return sorted;
        }

        static setSort(column) {
            const current = AppState.sort || { column: null, direction: 'asc' };

            if (!column) {
                AppState.sort = { column: null, direction: 'asc' };
                this.apply();
                return;
            }

            let nextColumn = column;
            let direction = 'asc';

            if (current.column === column) {
                if (current.direction === 'asc') {
                    direction = 'desc';
                } else {
                    nextColumn = null;
                    direction = 'asc';
                }
            }

            AppState.sort = nextColumn ? { column: nextColumn, direction } : { column: null, direction: 'asc' };
            this.apply();
        }
    }

    // =============================================================================
    // MODERN UI COMPONENTS
    // =============================================================================

    class UI {
        static audioContext = null;
        static init() {
            this.setupEventListeners();
            this.showWelcomeState();
            this.updateIndexStatus(CONFIG.indexStats);
            this.renderDiagnostics(CONFIG.diagnostics || {}, CONFIG.indexStats || null);
            Index.startPolling(true);
        }

        static setupEventListeners() {
            // Filter controls
            $(document).on('change', '.filter-control', function() {
                const $control = $(this);
                const filterType = $control.data('filter-type');
                const value = $control.val() || $control.data('filter-value');
                FilterEngine.setFilter(filterType, value);
            });

            // Search
            $(document).on('input', '#image-search', function() {
                FilterEngine.setFilter('search', $(this).val());
            });

            // Clear filters
            $('#clear-filters').on('click', function() {
                FilterEngine.reset();
                $('.filter-control').val('all');
            });

            // Sortable column headers
            $(document).on('click', '.results-table th[data-sort-key] .sort-trigger', function() {
                const sortKey = $(this).closest('th').data('sort-key');
                FilterEngine.setSort(sortKey);
            });

            // Action buttons - Pass event to check for Shift key
            $('#analyze-images').on('click', (e) => Analysis.run(e.shiftKey));

            const $optimizeAll = $('#optimize-all');
            if ($optimizeAll.length) {
                $optimizeAll.on('click', () => Optimization.runAll());
            }

            const $optimizeHigh = $('#optimize-high-priority');
            if ($optimizeHigh.length) {
                $optimizeHigh.on('click', () => Optimization.runByPriority('high'));
            }

            const $optimizeMedium = $('#optimize-medium-priority');
            if ($optimizeMedium.length) {
                $optimizeMedium.on('click', () => Optimization.runByPriority('medium'));
            }

            $('#optimize-selected').on('click', () => Optimization.runSelected());
            $(document).on('click', '.optimize-single', function(event) {
                event.preventDefault();
                const attachmentId = $(this).data('id');
                Optimization.runSingle(attachmentId);
            });
            $('#apply-filename-suggestions').on('click', () => UI.applyAllFilenameSuggestions());
            $('#verify-webp-status').on('click', () => WebPVerification.runVerification());
            $('#view-orphan-list').on('click', (e) => {
                e.preventDefault();
                UI.toggleOrphanList();
            });

            const $resetOptimization = $('#reset-optimization');
            if ($resetOptimization.length) {
                $resetOptimization.on('click', (event) => {
                    event.preventDefault();
                    const message = 'This will reset all optimization flags, allowing images to be re-optimized with improved metadata preservation. Continue?';
                    if (window.confirm(message)) {
                        UI.resetOptimizationFlags();
                    }
                });
            }

            $('#modal-dismiss').on('click', (e) => {
                e.preventDefault();
                UI.hideProgressModal();
            });

            const $renameToggle = $('#enable-file-rename');
            if ($renameToggle.length) {
                $renameToggle.prop('checked', AppState.renameEnabled);

                $renameToggle.on('change', function() {
                    const enabled = $(this).is(':checked') ? '1' : '0';
                    AppState.renameEnabled = enabled === '1';
                    UI.updateFilenameSuggestionsButton();

                    $.ajax({
                        url: mshImageOptimizer.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'msh_toggle_file_rename',
                            nonce: mshImageOptimizer.renameToggleNonce,
                            enabled
                        }
                    }).fail(() => {
                        alert('Unable to update rename setting. Please try again.');
                        AppState.renameEnabled = !AppState.renameEnabled;
                        $renameToggle.prop('checked', AppState.renameEnabled);
                        UI.updateFilenameSuggestionsButton();
                    });
                });
            }

            $('#rebuild-usage-index').on('click', (e) => {
                e.preventDefault();
                const force = e.shiftKey;
                if (force) {
                    const confirmed = window.confirm('Force rebuilding the usage index will truncate existing data and rebuild from scratch. Continue?');
                    if (!confirmed) {
                        return;
                    }
                }
                Index.build(force);
            });

            $('#force-rebuild-usage-index').on('click', async (e) => {
                e.preventDefault();

                // Get REMAINING attachment count from server
                let remainingInfo = null;
                try {
                    const response = await $.ajax({
                        url: mshImageOptimizer.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'msh_get_remaining_count',
                            nonce: mshImageOptimizer.nonce
                        }
                    });
                    if (response.success && response.data) {
                        remainingInfo = response.data;
                    }
                } catch (error) {
                    // If count fails, use generic message
                    remainingInfo = null;
                }

                let message;
                if (!remainingInfo) {
                    message = '🔥 FORCE REBUILD WARNING:\n\nThis will CLEAR ALL existing index data and rebuild from scratch.\n\nAll attachments will be reprocessed (~60 seconds).\n\nContinue?';
                } else if (remainingInfo.remaining === 0) {
                    message = `🔥 FORCE REBUILD WARNING:\n\n` +
                             `Index appears complete (${remainingInfo.total} attachments), but Force Rebuild will:\n\n` +
                             `• CLEAR ALL existing index data\n` +
                             `• Rebuild all ${remainingInfo.total} attachments from scratch\n` +
                             `• Take ~60 seconds (complete rebuild)\n\n` +
                             `⚠️  Use "Smart Build Index" instead if you just want to verify.\n\nContinue with Force Rebuild?`;
                } else {
                    message = `🔥 FORCE REBUILD WARNING:\n\n` +
                             `This will CLEAR ALL existing index data and rebuild from scratch.\n\n` +
                             `• Current index will be DELETED (${remainingInfo.indexed} entries)\n` +
                             `• All ${remainingInfo.total} attachments will be reprocessed\n` +
                             `• Estimated time: ~60 seconds (optimized rebuild)\n\n` +
                             `⚠️  This is a complete rebuild, not incremental.\n\nContinue?`;
                }

                const confirmed = window.confirm(message);
                if (confirmed) {
                    Index.build(true); // Always force rebuild
                }
            });

            // Duplicate cleanup handlers

            DuplicateCleanup.checkCapabilities();

            $('#test-cleanup').on('click', function(e) {
                e.preventDefault();
                DuplicateCleanup.testConnection();
            });
            $('#visual-similarity-scan').on('click', () => DuplicateCleanup.runVisualSimilarityScan());
            $('#quick-duplicate-scan').on('click', () => DuplicateCleanup.runQuickScan());
            $('#full-library-scan').on('click', () => DuplicateCleanup.runDeepScan());

            // Meta editing
            $(document).on('click', '.edit-meta', function() {
                const attachmentId = $(this).data('id');
                UI.showMetaEditModal(attachmentId);
            });

            // Filename suggestions
            $(document).on('click', '.apply-suggestion', function() {
                const attachmentId = $(this).data('id');
                UI.acceptFilenameSuggestion(attachmentId);
            });

            $(document).on('click', '.reject-suggestion', function() {
                const attachmentId = $(this).data('id');
                UI.rejectFilenameSuggestion(attachmentId);
            });

            // Edit filename suggestions
            $(document).on('click', '.edit-suggestion', function() {
                const attachmentId = $(this).data('id');
                UI.editFilenameSuggestion(attachmentId);
            });

            $(document).on('click', '.save-suggestion', function() {
                const attachmentId = $(this).data('id');
                UI.saveFilenameSuggestion(attachmentId);
            });

            $(document).on('click', '.cancel-suggestion', function() {
                const attachmentId = $(this).data('id');
                UI.cancelEditFilenameSuggestion(attachmentId);
            });

            // Edit current filename button
            $(document).on('click', '.edit-current-filename', function(e) {
                e.preventDefault();
                const attachmentId = $(this).data('id');
                UI.showCurrentFilenameEditor(attachmentId);
            });

            // Selection checkboxes - handle select all and update button text
            $(document).on('change', '.image-select, #select-all, #select-all-header', function() {

                // Handle Select All functionality
                if ($(this).is('#select-all, #select-all-header')) {
                    const isChecked = $(this).is(':checked');

                    // Update all visible image checkboxes to match Select All state
                    $('.image-select:visible').prop('checked', isChecked);

                    // Sync both Select All checkboxes
                    $('#select-all, #select-all-header').prop('checked', isChecked);
                }

                // Update all UI elements based on selection state
                UI.updateSelectAllState();
                UI.updateFilenameSuggestionsButton();
            });

            // Debug: Add manual button update function to console
            window.updateButtonText = function() {
                UI.updateFilenameSuggestionsButton();
            };

            // Context dropdown changes
            $(document).on('change', '.context-dropdown', function() {
                const attachmentId = $(this).data('attachment-id');
                const newContext = $(this).val();
                const locationSpecific = UI.getLocationSpecificState(attachmentId);
                UI.updateImageContext(attachmentId, newContext, locationSpecific);
            });

            $(document).on('change', '.context-location-checkbox', function() {
                const attachmentId = $(this).data('attachment-id');
                const locationSpecific = $(this).is(':checked');
                const $dropdown = $(`.context-dropdown[data-attachment-id="${attachmentId}"]`);
                const currentContext = $dropdown.length ? $dropdown.val() : '';
                UI.updateImageContext(attachmentId, currentContext, locationSpecific);
            });

            // Selection detection working - force update interval removed
        }

        static renderFilterControls() {
            const controlsHTML = `
                <div class="msh-filter-controls">
                    <div class="filter-group">
                        <label>Status:</label>
                        <select class="filter-control" data-filter-type="status">
                            <option value="all">All Images</option>
                            <option value="needs_optimization">Needs Optimization</option>
                            <option value="optimized">Optimized</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Priority:</label>
                        <select class="filter-control" data-filter-type="priority">
                            <option value="all">All Priorities</option>
                            <option value="high">High (15+)</option>
                            <option value="medium">Medium (10-14)</option>
                            <option value="low">Low (0-9)</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Issues:</label>
                        <select class="filter-control" data-filter-type="issues">
                            <option value="all">All Issues</option>
                            <option value="missing_alt">Missing ALT Text</option>
                            <option value="no_webp">No WebP</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Search:</label>
                        <input type="text" id="image-search" placeholder="Search images..." />
                    </div>

                    <div class="filter-group">
                        <button type="button" onclick="FilterEngine.reset()" class="button button-secondary">
                            Reset Filters
                        </button>
                    </div>
                </div>
            `;

            $('.filters-section').html(controlsHTML);
        }

        static updateFilterControls() {
            $('.filter-control').each(function() {
                const $control = $(this);
                const filterType = $control.data('filter-type');
                $control.val(AppState.filters[filterType]);
            });
            $('#image-search').val(AppState.filters.search);
        }

        static showWelcomeState() {
            this.updateResultsCount(0);
            $('#results-tbody').html(`
                <tr class="welcome-state">
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <div class="welcome-content">
                            <h3>🚀 Ready to Optimize Your Images!</h3>
                            <p>Click "Analyze Published Images" to scan your site and see what needs optimization.</p>
                            <p><strong>New:</strong> WebP conversion now included automatically!</p>
                        </div>
                    </td>
                </tr>
            `);
        }

        static renderResults(images) {
            this.updateResultsCount(images.length);

            if (images.length === 0) {
                $('#results-tbody').html(`
                    <tr class="no-results">
                        <td colspan="8" style="text-align: center; padding: 20px;">
                            No images match your current filters. Try adjusting the filter settings.
                        </td>
                    </tr>
                `);
                this.updateSortIndicators();
                return;
            }

            const rows = images.map(image => this.renderImageRow(image)).join('');
            $('#results-tbody').html(rows);
            this.updateFilenameSuggestionsButton();
            this.updateSelectAllState();
            this.updateSortIndicators();

            // Show results section and log
            $('.msh-results-section').show();
            $('.msh-log-section').show();
        }

        static updateSortIndicators() {
            const { column, direction } = AppState.sort || {};
            const $headers = $('.results-table th[data-sort-key]');
            $headers.removeClass('is-sorted sort-asc sort-desc');

            if (!column) {
                return;
            }

            const $target = $headers.filter(`[data-sort-key="${column}"]`);
            if ($target.length) {
                $target.addClass('is-sorted');
                $target.addClass(direction === 'desc' ? 'sort-desc' : 'sort-asc');
            }
        }

        static updateFilenameSuggestionsButton() {
            const imagesWithSuggestions = AppState.images.filter(img => img.suggested_filename);
            const $button = $('#apply-filename-suggestions');

            // Check if specific images are selected
            const selectedImages = AppState.images.filter(img => {
                const selector = `.image-select[value="${img.ID}"]`;
                const checkbox = $(selector);
                const exists = checkbox.length > 0;
                const checked = exists ? checkbox.is(':checked') : false;
                if (exists && checked) {
                }
                return exists && checked;
            });

            // Additional debug - check all checkboxes on page
            const allCheckboxes = $('.image-select:checked');
            if (allCheckboxes.length > 0) {
                allCheckboxes.each(function() {
                });
            }

            let buttonText;
            let shouldEnable = false;

            if (selectedImages.length > 0) {
                // Use selected images that have suggestions
                const selectedWithSuggestions = selectedImages.filter(img => img.suggested_filename);
                if (selectedWithSuggestions.length > 0) {
                    buttonText = `Apply Filename Suggestions (${selectedWithSuggestions.length} selected)`;
                    shouldEnable = true;
                } else {
                    buttonText = `Apply Filename Suggestions (0 of ${selectedImages.length} selected have suggestions)`;
                    shouldEnable = false;
                }
            } else {
                // No selection, show all available
                if (imagesWithSuggestions.length > 0) {
                    buttonText = `Apply Filename Suggestions (${imagesWithSuggestions.length} total)`;
                    shouldEnable = true;
                } else {
                    buttonText = 'Apply Filename Suggestions';
                    shouldEnable = false;
                }
            }

            if (!AppState.renameEnabled) {
                shouldEnable = false;
                if (imagesWithSuggestions.length > 0) {
                    buttonText = 'Enable file renaming to apply suggestions';
                }
            }

            $button.prop('disabled', !shouldEnable);
            $button.text(buttonText);
        }

        static updateSelectAllState() {
            const totalCheckboxes = $('.image-select:visible').length;
            const checkedCheckboxes = $('.image-select:visible:checked').length;

            // Update Select All checkbox states
            if (checkedCheckboxes === 0) {
                // None selected - uncheck Select All
                $('#select-all, #select-all-header').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                // All selected - check Select All
                $('#select-all, #select-all-header').prop('checked', true).prop('indeterminate', false);
            } else {
                // Some selected - indeterminate state
                $('#select-all, #select-all-header').prop('checked', false).prop('indeterminate', true);
            }

            // Update selected count display
            $('#selected-count').text(`${checkedCheckboxes} selected`);

            const selectedIds = $('.image-select:visible:checked').map(function() {
                return parseInt($(this).val(), 10);
            }).get();

            const selectedNeedingOptimization = AppState.images.filter(img =>
                selectedIds.includes(parseInt(img.ID, 10)) && img.optimization_status !== 'optimized'
            );

            const hasSelectionNeedingOptimization = selectedNeedingOptimization.length > 0;
            $('#optimize-selected').prop('disabled', !hasSelectionNeedingOptimization);

            const totalNeedingOptimization = AppState.images.filter(img => img.optimization_status !== 'optimized').length;
            const $priorityButtons = $('#optimize-high-priority, #optimize-medium-priority, #optimize-all');
            if ($priorityButtons.length) {
                $priorityButtons.prop('disabled', totalNeedingOptimization === 0);
            }
        }

        static renderImageRow(image) {
            // Determine display status - only show WebP Active if file was actually optimized (renamed)
            let displayStatus = image.optimization_status;
            if (image.webp_exists && image.optimization_status === 'optimized' && image.optimized_date) {
                displayStatus = 'webp_optimized';
            }

            const status = STATUS_DEFINITIONS[displayStatus] || STATUS_DEFINITIONS['needs_attention'];
            const statusClass = ['status-badge', status.badgeClass].filter(Boolean).join(' ');
            const priority = this.getPriorityDetails(image.priority);
            const issuesText = (image.issues || []).map((issue) => issue.label).join(', ');
            const issuesDisplay = issuesText ? issuesText : 'No issues flagged';
            const needsOptimization = image.optimization_status !== 'optimized';
            const needsReoptimizationHelper = image.optimization_status === 'context_stale';
            const optimizeButtonClasses = ['button', 'button-small', 'optimize-single'];
            if (!needsOptimization) {
                optimizeButtonClasses.push('is-disabled');
            }
            const optimizeDisabledAttr = needsOptimization ? '' : 'disabled';

            // Generate thumbnail URL from WordPress attachment
            const thumbnailUrl = image.thumbnail_url || `${window.location.origin}/wp-content/uploads/${image.file_path}`;
            const filename = image.filename || (image.file_path ? image.file_path.split('/').pop() : 'undefined');
            const title = image.title || image.post_title || 'No title';
            const altText = image.alt_text || image.post_title || '';

            const showEditIcon = !image.suggested_filename || image.optimized_date;
            const editButtonMarkup = showEditIcon
                ? `<button class="button button-link edit-current-filename" data-id="${image.ID}" title="Edit filename">
                        <img src="${mshImageOptimizer.pluginUrl}/assets/icons/edit.png" alt="Edit" class="edit-icon" />
                    </button>`
                : '';

            return `
                <tr class="result-row" data-attachment-id="${image.ID}">
                    <td>
                        <input type="checkbox" class="image-select" value="${image.ID}" />
                    </td>
                    <td class="image-cell">
                        <img src="${thumbnailUrl}" alt="${altText}" class="table-thumbnail" />
                    </td>
                    <td class="filename-cell">
                        <div class="filename-stack">
                            <div class="current-filename-display">
                                <strong class="filename-heading">${filename}</strong>
                                ${editButtonMarkup}
                            </div>
                            <span class="filename-subheading">${title}</span>
                        </div>
                        ${this.renderFilenameSuggestion(image)}
                        ${this.renderMetaPreview(image)}
                        ${this.renderUsageDetails(image)}
                    </td>
                    <td class="context-cell">
                        ${this.renderContextDropdown(image)}
                    </td>
                    <td class="status-cell">
                        <span class="${statusClass}">${status.label}</span>
                        <span class="status-subtext">${issuesDisplay}</span>
                    </td>
                    <td class="priority-cell">
                        <span class="priority-badge priority-${priority.level}">${priority.label}</span>
                        ${priority.showScore ? `<span class="priority-score">Score ${priority.score}</span>` : ''}
                    </td>
                    <td class="size-cell">
                        <span class="size-value">${this.formatFileSize(image.file_size)}</span>
                        ${this.renderWebPInfo(image)}
                    </td>
                    <td class="actions-cell">
                        <button class="${optimizeButtonClasses.join(' ')}" data-id="${image.ID}" ${optimizeDisabledAttr}>
                            Optimize
                        </button>
                        ${needsReoptimizationHelper ? '<div class="optimize-helper">Needs re-optimization</div>' : ''}
                    </td>
                </tr>
            `;
        }

        static getPriorityDetails(priority) {
            const score = Number.isFinite(Number(priority)) ? parseInt(priority, 10) : null;
            if (score === null) {
                return { level: 'low', label: 'Low Priority', score: null, showScore: false };
            }

            if (score >= 15) {
                return { level: 'high', label: 'High', score, showScore: true };
            }

            if (score >= 10) {
                return { level: 'medium', label: 'Medium', score, showScore: true };
            }

            return { level: 'low', label: 'Low', score, showScore: score > 0 };
        }

        static formatFileSize(bytes) {
            if (!bytes) return 'Unknown';
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }

        static renderWebPInfo(image) {
            if (!image.webp_exists) {
                return '';
            }

            // Show WebP delivery info with savings
            const savings = image.webp_savings_estimate;
            if (savings && savings.estimated_savings_percent > 0) {
                return `<span class="webp-indicator">WebP active · -${savings.estimated_savings_percent}%</span>`;
            }

            return '<span class="webp-indicator">WebP active</span>';
        }

        static renderFilenameSuggestion(image) {
            // Don't show suggestion if file has already been optimized (suggestion was applied)
            if (!image.suggested_filename || image.optimized_date) {
                return '';
            }

            return `
                <div class="filename-suggestion-card">
                    <div class="filename-suggestion-heading">Suggested filename</div>
                    <div class="filename-suggestion-display suggestion-filename-display" data-id="${image.ID}">${this.escapeHtml(image.suggested_filename)}</div>
                    <input type="text" class="filename-suggestion-edit suggestion-filename-edit" data-id="${image.ID}" value="${this.escapeHtml(image.suggested_filename)}" style="display: none;" />
                    <div class="filename-suggestion-actions">
                        <button class="button button-small apply-suggestion brand-primary" data-id="${image.ID}">Apply</button>
                        <button class="button button-small edit-suggestion" data-id="${image.ID}">Edit</button>
                        <button class="button button-small save-suggestion brand-accent" data-id="${image.ID}" style="display: none;">Save</button>
                        <button class="button button-small cancel-suggestion" data-id="${image.ID}" style="display: none;">Cancel</button>
                        <button class="button button-small reject-suggestion" data-id="${image.ID}">Dismiss</button>
                    </div>
                </div>
            `;
        }

        static renderUsageDetails(image) {
            const entries = this.getUsageEntries(image);
            const hasEntries = entries.length > 0;

            if (!hasEntries) {
                return `
                    <details class="usage-preview">
                        <summary>See where used</summary>
                        <div class="usage-empty-message">No published references detected.</div>
                    </details>
                `;
            }

            const MAX_USAGE_DISPLAY = 6;
            const visibleEntries = entries.slice(0, MAX_USAGE_DISPLAY);
            const overflowCount = entries.length - visibleEntries.length;

            const listItems = visibleEntries.map((entry) => `<li>${this.escapeHtml(entry)}</li>`).join('');
            const overflowNote = overflowCount > 0
                ? `<li class="usage-more">+${overflowCount} additional references</li>`
                : '';

            return `
                <details class="usage-preview">
                    <summary>See where used</summary>
                    <ul>${listItems}${overflowNote}</ul>
                </details>
            `;
        }

        static getUsageEntries(image) {
            if (!image) {
                return [];
            }

            const rawUsage = Array.isArray(image.used_in) ? image.used_in : (image.used_in || '');

            if (Array.isArray(rawUsage)) {
                return rawUsage.filter(Boolean);
            }

            if (typeof rawUsage !== 'string' || rawUsage.trim() === '') {
                return [];
            }

            const parts = rawUsage.split(',').map((part) => part.trim()).filter(Boolean);
            return Array.from(new Set(parts));
        }

        static renderMetaPreview(image) {
            const meta = image.generated_meta || {};
            const context = image.context_details || {};

            if (!meta || Object.keys(meta).length === 0) {
                return '';
            }

            const metaFields = [];
            if (meta.title) metaFields.push(`<strong>Title:</strong> ${this.escapeHtml(meta.title)}`);
            if (meta.alt_text) metaFields.push(`<strong>ALT:</strong> ${this.escapeHtml(meta.alt_text)}`);
            if (meta.caption) metaFields.push(`<strong>Caption:</strong> ${this.escapeHtml(meta.caption)}`);
            if (meta.description) metaFields.push(`<strong>Description:</strong> ${this.escapeHtml(meta.description)}`);

            if (metaFields.length === 0) {
                return '';
            }

            return `
                <details class="meta-preview">
                    <summary class="meta-preview-summary">Meta Preview</summary>
                    <div class="meta-preview-body">
                        ${metaFields.join('<br>')}
                        <div class="meta-preview-actions">
                            <button class="button button-small edit-meta brand-primary" data-id="${image.ID}">Edit</button>
                        </div>
                    </div>
                </details>
            `;
        }

        static renderContextDropdown(image) {
            const context = image.context_details || {};
            const contextSource = image.context_source || 'auto';
            const manualContext = image.manual_context || '';

            const defaultChoiceMap = {
                '': 'Auto-detect (default)',
                'business': 'Business / General',
                'team': 'Team Member',
                'testimonial': 'Customer Testimonial',
                'service-icon': 'Icon / Graphic',
                'facility': 'Workspace / Office',
                'equipment': 'Product / Equipment',
                'clinical': 'Service Highlight'
            };

            const contextChoiceMap = (window.mshImageOptimizer && window.mshImageOptimizer.contextChoiceMap)
                ? window.mshImageOptimizer.contextChoiceMap
                : defaultChoiceMap;

            const choiceList = (window.mshImageOptimizer && Array.isArray(window.mshImageOptimizer.contextChoices))
                ? window.mshImageOptimizer.contextChoices
                : Object.entries(contextChoiceMap).map(([value, label]) => ({ value, label }));

            const detectedType = context.type || '';
            const activeLabel = image.context_active_label
                || contextChoiceMap[manualContext || detectedType]
                || contextChoiceMap['']
                || 'Auto-detect (default)';
            const autoLabel = image.context_auto_label
                || (detectedType && contextChoiceMap[detectedType] ? contextChoiceMap[detectedType] : '');
            const locationSpecific = isLocationSpecific(image, context);
            const locationChip = locationSpecific ? '<span class="context-location-chip">Location anchored</span>' : '';

            let optionsHTML = '';
            choiceList.forEach(choice => {
                const value = choice.value;
                const label = choice.label || contextChoiceMap[value] || (value ? value.toString().split(/[-_]/).map(part => part.charAt(0).toUpperCase() + part.slice(1)).join(' ') : '');
                const selected = manualContext === value ? 'selected' : '';
                optionsHTML += `<option value="${value}" ${selected}>${this.escapeHtml(label)}</option>`;
            });

            const modeLabel = contextSource === 'manual' ? 'Manual' : 'Auto';
            const modeClass = contextSource === 'manual' ? 'context-mode context-mode--manual' : 'context-mode context-mode--auto';
            const statusBadge = `<span class="${modeClass}">${modeLabel}</span>`;

            return `
                <div class="context-control">
                    <div class="context-header">
                        <span class="context-label">${this.escapeHtml(activeLabel)}</span>
                        ${statusBadge}
                        ${locationChip}
                    </div>
                    <select class="context-dropdown" data-attachment-id="${image.ID}">
                        ${optionsHTML}
                    </select>
                    <label class="context-location-toggle">
                        <input type="checkbox" class="context-location-checkbox" data-attachment-id="${image.ID}" ${locationSpecific ? 'checked' : ''}>
                        <span>Use business location context</span>
                    </label>
                    ${autoLabel ? `<div class="context-auto-label">Auto: ${this.escapeHtml(autoLabel)}</div>` : ''}
                </div>
            `;
        }

        static escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        static getLocationSpecificState(attachmentId) {
            const $checkbox = $(`.context-location-checkbox[data-attachment-id="${attachmentId}"]`);
            if ($checkbox.length) {
                return $checkbox.is(':checked');
            }

            const image = AppState.images.find(img => img.ID == attachmentId);
            if (!image) {
                return false;
            }

            return isLocationSpecific(image);
        }

        static showMetaEditModal(attachmentId) {
            const image = AppState.images.find(img => img.ID == attachmentId);
            if (!image) return;

            const meta = image.generated_meta || {};

            const modalHTML = `
                <div id="meta-edit-modal" class="msh-modal-overlay">
                    <div id="meta-edit-content" class="msh-modal">
                        <div class="msh-modal__header">
                            <h3 class="msh-modal__title">Edit Meta for: ${this.escapeHtml(image.post_title || 'Untitled')}</h3>
                            <button type="button" class="msh-modal__close" id="close-meta-edit" aria-label="Close">&times;</button>
                        </div>
                        <form id="meta-edit-form" class="msh-form">
                            <div class="msh-form__group">
                                <label for="edit-title">Title</label>
                                <input type="text" id="edit-title" class="msh-input" value="${this.escapeHtml(meta.title || '')}">
                            </div>
                            <div class="msh-form__group">
                                <label for="edit-alt">ALT Text</label>
                                <input type="text" id="edit-alt" class="msh-input" value="${this.escapeHtml(meta.alt_text || '')}">
                            </div>
                            <div class="msh-form__group">
                                <label for="edit-caption">Caption</label>
                                <textarea id="edit-caption" class="msh-textarea" rows="3">${this.escapeHtml(meta.caption || '')}</textarea>
                            </div>
                            <div class="msh-form__group">
                                <label for="edit-description">Description</label>
                                <textarea id="edit-description" class="msh-textarea" rows="4">${this.escapeHtml(meta.description || '')}</textarea>
                            </div>
                            <div class="msh-modal__actions">
                                <button type="button" id="cancel-meta-edit" class="button button-dot-secondary">Cancel</button>
                                <button type="submit" id="save-meta-edit" class="button button-dot-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);

            // Event handlers - working version from debug session
            $('#cancel-meta-edit').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('#meta-edit-modal').remove();
            });

            $('#meta-edit-modal').on('click', function(e) {
                if (e.target.id === 'meta-edit-modal') {
                    $('#meta-edit-modal').remove();
                }
            });

            $('#meta-edit-content').on('click', function(e) {
                e.stopPropagation();
            });

            $('#meta-edit-form input, #meta-edit-form textarea, #meta-edit-form button').on('click focus blur keyup keydown', function(e) {
                e.stopPropagation();
            });

            $('#meta-edit-form').on('submit', function(e) {
                e.preventDefault();
                UI.saveMetaEdit(attachmentId);
            });
        }

        static saveMetaEdit(attachmentId) {
            const editedMeta = {
                title: $('#edit-title').val(),
                alt_text: $('#edit-alt').val(),
                caption: $('#edit-caption').val(),
                description: $('#edit-description').val()
            };


            $.post(CONFIG.endpoints.optimize, {
                action: 'msh_save_edited_meta',
                nonce: CONFIG.nonce,
                image_id: attachmentId,
                meta_data: editedMeta
            })
            .done((response) => {
                if (response.success) {
                    // Update the image data in state
                    const image = AppState.images.find(img => img.ID == attachmentId);
                    if (image) {
                        image.generated_meta = { ...image.generated_meta, ...editedMeta };
                    }

                    $('#meta-edit-modal').remove();
                    UI.renderResults(FilterEngine.getFilteredImages());
                    UI.updateLog('Meta updated successfully');
                } else {
                    alert('Error saving meta: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(() => {
                alert('Error saving meta. Please try again.');
            });
        }

        static async acceptFilenameSuggestion(attachmentId) {
            try {

                const response = await $.post(mshImageOptimizer.ajaxurl, {
                    action: 'msh_accept_filename_suggestion',
                    nonce: mshImageOptimizer.nonce,
                    image_id: attachmentId
                });


                if (response.success) {
                    UI.updateLog(`Filename suggestion applied: ${response.data.message}`);

                    // Update the image in AppState
                    const image = AppState.images.find(img => img.ID == attachmentId);
                    if (image) {
                        image.filename = response.data.new_filename;
                        image.suggested_filename = ''; // Clear suggestion
                    }

                    // Re-render results
                    UI.renderResults(FilterEngine.getFilteredImages());
                } else {
                    alert('Error applying filename suggestion: ' + (response.data || 'Unknown error'));
                }
            } catch (error) {
                alert('Error applying filename suggestion. Please try again.');
                console.error('Filename suggestion error:', error);
            }
        }

        static async rejectFilenameSuggestion(attachmentId) {
            try {
                const response = await $.post(mshImageOptimizer.ajaxurl, {
                    action: 'msh_reject_filename_suggestion',
                    nonce: mshImageOptimizer.nonce,
                    image_id: attachmentId
                });

                if (response.success) {
                    UI.updateLog(`Filename suggestion dismissed for image ${attachmentId}`);

                    // Update the image in AppState
                    const image = AppState.images.find(img => img.ID == attachmentId);
                    if (image) {
                        image.suggested_filename = ''; // Clear suggestion
                    }

                    // Re-render results
                    UI.renderResults(FilterEngine.getFilteredImages());
                } else {
                    alert('Error dismissing filename suggestion: ' + (response.data || 'Unknown error'));
                }
            } catch (error) {
                alert('Error dismissing filename suggestion. Please try again.');
                console.error('Filename suggestion error:', error);
            }
        }

        static editFilenameSuggestion(attachmentId) {
            const $container = $(`.suggestion-filename-display[data-id="${attachmentId}"]`).parent();
            const $display = $container.find('.suggestion-filename-display');
            const $input = $container.find('.suggestion-filename-edit');
            const $editBtn = $container.find('.edit-suggestion');
            const $saveBtn = $container.find('.save-suggestion');
            const $cancelBtn = $container.find('.cancel-suggestion');

            // Hide display, show input
            $display.hide();
            $input.show().focus();

            // Hide edit button, show save/cancel
            $editBtn.hide();
            $saveBtn.show();
            $cancelBtn.show();
        }

        static async saveFilenameSuggestion(attachmentId) {
            const $container = $(`.suggestion-filename-edit[data-id="${attachmentId}"]`).parent();
            const $input = $container.find('.suggestion-filename-edit');
            const newFilename = $input.val().trim();

            if (!newFilename) {
                alert('Filename cannot be empty');
                return;
            }

            try {
                const response = await $.post(mshImageOptimizer.ajaxurl, {
                    action: 'msh_save_filename_suggestion',
                    nonce: mshImageOptimizer.nonce,
                    image_id: attachmentId,
                    suggested_filename: newFilename
                });

                if (response.success) {
                    // Update the image in AppState
                    const image = AppState.images.find(img => img.ID == attachmentId);
                    if (image) {
                        image.suggested_filename = newFilename;
                    }

                    UI.updateLog(`Filename suggestion updated for image ${attachmentId}`);
                    this.cancelEditFilenameSuggestion(attachmentId);
                    UI.renderResults(FilterEngine.getFilteredImages());
                } else {
                    alert('Error saving filename suggestion: ' + (response.data || 'Unknown error'));
                }
            } catch (error) {
                alert('Error saving filename suggestion. Please try again.');
                console.error('Save filename suggestion error:', error);
            }
        }

        static cancelEditFilenameSuggestion(attachmentId) {
            const $container = $(`.suggestion-filename-edit[data-id="${attachmentId}"]`).parent();
            const $display = $container.find('.suggestion-filename-display');
            const $input = $container.find('.suggestion-filename-edit');
            const $editBtn = $container.find('.edit-suggestion');
            const $saveBtn = $container.find('.save-suggestion');
            const $cancelBtn = $container.find('.cancel-suggestion');

            // Reset input to original value
            const originalValue = $display.text();
            $input.val(originalValue);

            // Show display, hide input
            $input.hide();
            $display.show();

            // Show edit button, hide save/cancel
            $saveBtn.hide();
            $cancelBtn.hide();
            $editBtn.show();
        }

        static showCurrentFilenameEditor(attachmentId) {
            const $row = $(`.result-row[data-attachment-id="${attachmentId}"]`);
            const $filenameDisplay = $row.find('.current-filename-display');
            const $filenameHeading = $filenameDisplay.find('.filename-heading');
            const $editBtn = $filenameDisplay.find('.edit-current-filename');

            const currentFilename = $filenameHeading.text().trim();

            // Replace display with input + buttons
            $filenameDisplay.html(`
                <input type="text" class="current-filename-input" data-id="${attachmentId}" value="${this.escapeHtml(currentFilename)}" style="width: 100%; max-width: 300px;" />
                <button class="button button-small save-current-filename brand-accent" data-id="${attachmentId}">Save</button>
                <button class="button button-small cancel-current-filename" data-id="${attachmentId}">Cancel</button>
            `);

            // Focus the input
            $filenameDisplay.find('.current-filename-input').focus().select();

            // Add event handlers
            $filenameDisplay.find('.save-current-filename').on('click', function(e) {
                e.preventDefault();
                UI.saveCurrentFilename(attachmentId, currentFilename);
            });

            $filenameDisplay.find('.cancel-current-filename').on('click', function(e) {
                e.preventDefault();
                UI.cancelCurrentFilenameEdit(attachmentId, currentFilename);
            });

            // Save on Enter, cancel on Escape
            $filenameDisplay.find('.current-filename-input').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    UI.saveCurrentFilename(attachmentId, currentFilename);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    UI.cancelCurrentFilenameEdit(attachmentId, currentFilename);
                }
            });
        }

        static async saveCurrentFilename(attachmentId, originalFilename) {
            const $input = $(`.current-filename-input[data-id="${attachmentId}"]`);
            const newFilename = $input.val().trim();

            if (!newFilename) {
                alert('Filename cannot be empty');
                return;
            }

            if (newFilename === originalFilename) {
                // No change, just cancel
                this.cancelCurrentFilenameEdit(attachmentId, originalFilename);
                return;
            }

            try {
                // First, save the new filename as a suggestion
                const saveSuggestionResponse = await $.post(mshImageOptimizer.ajaxurl, {
                    action: 'msh_save_filename_suggestion',
                    nonce: mshImageOptimizer.nonce,
                    image_id: attachmentId,
                    suggested_filename: newFilename
                });

                if (!saveSuggestionResponse.success) {
                    alert('Error saving filename suggestion: ' + (saveSuggestionResponse.data || 'Unknown error'));
                    this.cancelCurrentFilenameEdit(attachmentId, originalFilename);
                    return;
                }

                // Now apply it using the batch endpoint with single image
                const applyResponse = await $.post(mshImageOptimizer.ajaxurl, {
                    action: 'msh_apply_filename_suggestions',
                    nonce: mshImageOptimizer.nonce,
                    image_ids: [attachmentId],
                    mode: 'selected',
                    batch_number: 1,
                    total_files: 1
                });

                if (applyResponse.success) {
                    const results = applyResponse.data.results || [];
                    const result = results.find(r => r.id == attachmentId);

                    if (result && result.status === 'success') {
                        UI.updateLog(`Filename updated for image ${attachmentId}: ${newFilename}`);

                        // Update the image in AppState
                        const image = AppState.images.find(img => img.ID == attachmentId);
                        if (image) {
                            const pathParts = (image.file_path || '').split('/');
                            pathParts[pathParts.length - 1] = newFilename;
                            image.file_path = pathParts.join('/');
                            image.filename = newFilename;
                            image.suggested_filename = ''; // Clear suggestion since it was applied
                        }

                        // Re-render to show updated filename
                        UI.renderResults(FilterEngine.getFilteredImages());
                    } else {
                        const errorMsg = result ? result.message : 'Unknown error during rename';
                        const statusInfo = result ? ` (status: ${result.status})` : '';
                        alert('Error applying filename: ' + errorMsg + statusInfo);
                        this.cancelCurrentFilenameEdit(attachmentId, originalFilename);
                    }
                } else {
                    alert('Error applying filename: ' + (applyResponse.data || 'Unknown error'));
                    this.cancelCurrentFilenameEdit(attachmentId, originalFilename);
                }
            } catch (error) {
                alert('Error applying filename. Please try again.');
                console.error('Apply filename error:', error);
                this.cancelCurrentFilenameEdit(attachmentId, originalFilename);
            }
        }

        static cancelCurrentFilenameEdit(attachmentId, originalFilename) {
            const $row = $(`.result-row[data-attachment-id="${attachmentId}"]`);
            const $filenameDisplay = $row.find('.current-filename-display');

            // Restore original display with PNG icon
            $filenameDisplay.html(`
                <strong class="filename-heading">${this.escapeHtml(originalFilename)}</strong>
                <button class="button button-link edit-current-filename" data-id="${attachmentId}" title="Edit filename">
                    <img src="${mshImageOptimizer.pluginUrl}/assets/icons/edit.png" alt="Edit" class="edit-icon" />
                </button>
            `);
        }

        static async updateImageContext(attachmentId, newContext, locationSpecific = null) {

            try {
                const payload = {
                    action: 'msh_update_context',
                    nonce: mshImageOptimizer.nonce,
                    attachment_id: attachmentId,
                    context: newContext
                };

                if (locationSpecific !== null) {
                    payload.location_specific = locationSpecific ? '1' : '0';
                }

                const response = await $.post(mshImageOptimizer.ajaxurl, payload);

                if (response.success) {
                    UI.updateLog(`Context updated for image ${attachmentId} to: ${newContext || 'Auto-detect'}`);

                    // Update the image in AppState with the full updated data from server
                    const image = AppState.images.find(img => img.ID == attachmentId);
                    if (image && response.data && response.data.image) {
                        const updatedImage = response.data.image;

                        // Update context-related fields
                        image.manual_context = updatedImage.manual_context || '';
                        image.auto_context = updatedImage.auto_context || '';
                        image.context_source = updatedImage.context_source || 'auto';
                        image.context_active_label = updatedImage.context_active_label || 'Auto-detect';
                        image.context_auto_label = updatedImage.context_auto_label || '';
                        image.context_details = updatedImage.context_details || {};
                        image.location_specific = isLocationSpecific(updatedImage);

                        // Refresh metadata + filename preview so UI reflects the new context immediately
                        image.generated_meta = updatedImage.generated_meta || {};
                        image.suggested_filename = updatedImage.suggested_filename || '';
                        image.file_path = updatedImage.file_path || image.file_path || '';
                        image.filename = updatedImage.filename
                            || (image.file_path ? image.file_path.split('/').pop() : image.filename);
                        image.optimization_status = updatedImage.optimization_status || image.optimization_status;
                        image.meta_preview = updatedImage.meta_preview || {};
                        image.context = updatedImage.context || updatedImage.context_details || image.context || image.context_details || {};
                    }

                    // Re-render the specific row to show updated context
                    UI.renderResults(FilterEngine.getFilteredImages());
                } else {
                    alert('Error updating context: ' + (response.data || 'Unknown error'));

                    // Revert dropdown to previous value
                    const $dropdown = $(`.context-dropdown[data-attachment-id="${attachmentId}"]`);
                    const image = AppState.images.find(img => img.ID == attachmentId);
                    if (image) {
                        $dropdown.val(image.manual_context || '');
                    }
                }
            } catch (error) {
                alert('Error updating context. Please try again.');
                console.error('Context update error:', error);

                // Revert dropdown to previous value
                const $dropdown = $(`.context-dropdown[data-attachment-id="${attachmentId}"]`);
                const image = AppState.images.find(img => img.ID == attachmentId);
                if (image) {
                    $dropdown.val(image.manual_context || '');
                }
            }
        }

        static async applyAllFilenameSuggestions() {

            // Check if specific images are selected
            const selectedImages = AppState.images.filter(img => {
                const checkbox = $(`.image-select[value="${img.ID}"]`);
                return checkbox.length > 0 && checkbox.is(':checked');
            });

            let imagesToProcess;
            let actionDescription;

            if (selectedImages.length > 0) {
                // Use selected images that have suggestions
                imagesToProcess = selectedImages.filter(img => img.suggested_filename);
                actionDescription = `selected (${imagesToProcess.length} of ${selectedImages.length} selected have suggestions)`;
            } else {
                // Fall back to all images with suggestions
                imagesToProcess = AppState.images.filter(img => img.suggested_filename);
                actionDescription = `all (${imagesToProcess.length})`;
            }

            if (imagesToProcess.length === 0) {
                if (selectedImages.length > 0) {
                    alert('None of the selected images have filename suggestions to apply.');
                } else {
                    alert('No filename suggestions found to apply.');
                }
                return;
            }

            const confirmed = confirm(`Apply filename suggestions for ${actionDescription} images?\n\nThis will rename the actual files and cannot be easily undone.`);
            if (!confirmed) {
                return;
            }

            // Start the automatic batch processing
            await this.processBatchedFileRenames(imagesToProcess);
        }

        // New method to handle automatic batch processing
        static async processBatchedFileRenames(imagesToProcess) {
            const imageIds = imagesToProcess.map(img => img.ID);

            try {
                // Show progress modal
                UI.showProgressModal(
                    'Processing in Batches',
                    `Starting automatic batch processing for ${imagesToProcess.length} files...`,
                    0
                );

                UI.updateLog(`🚀 Starting automatic batch processing for ${imagesToProcess.length} files...`);
                UI.updateLog(`📦 Processing in safe batches of 25 files each to prevent timeouts`);

                let currentBatch = 1;
                let totalProcessed = 0;
                let totalSuccess = 0;
                let totalErrors = 0;
                let totalSkipped = 0;

                while (true) {
                    UI.updateProgressModal(
                        `Processing Batch ${currentBatch}`,
                        `Processing files in batch ${currentBatch}...`,
                        Math.round((totalProcessed / imagesToProcess.length) * 100)
                    );

                    const response = await $.ajax({
                        url: mshImageOptimizer.ajaxurl,
                        type: 'POST',
                        timeout: 900000, // 15 minutes per batch
                        data: {
                            action: 'msh_apply_filename_suggestions',
                            nonce: mshImageOptimizer.nonce,
                            image_ids: imageIds,
                            batch_number: currentBatch,
                            total_files: imagesToProcess.length
                        }
                    });

                    if (!response.success) {
                        throw new Error(response.data || 'Unknown error');
                    }

                    // Update counters
                    totalProcessed += response.data.summary.total;
                    totalSuccess += response.data.summary.success;
                    totalErrors += response.data.summary.errors;
                    totalSkipped += response.data.summary.skipped;

                    // Update progress
                    const overallProgress = response.data.batch_info.overall_progress;
                    UI.updateProgressModal(
                        `Batch ${currentBatch} Complete`,
                        `Batch ${currentBatch}/${response.data.batch_info.total_batches} complete. Overall: ${Math.round(overallProgress)}%`,
                        overallProgress
                    );

                    UI.updateLog(`✅ Batch ${currentBatch}/${response.data.batch_info.total_batches} complete - ${response.data.summary.success} successful, ${response.data.summary.skipped} skipped`);

                    // Clear suggestions from AppState for successfully renamed images
                    if (response.data.results) {
                        response.data.results.forEach(result => {
                            if (!result.error) {
                                const image = AppState.images.find(img => img.ID == result.id);
                                if (image) {
                                    image.suggested_filename = '';
                                    image.filename = result.new_filename || image.filename;
                                }
                            }
                        });
                    }

                    // Check if we're done
                    if (!response.data.batch_info.has_more_batches) {
                        break;
                    }

                    currentBatch = response.data.batch_info.next_batch_number;

                    // Small delay between batches
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }

                // All batches complete!
                UI.updateProgressModal(
                    'All Batches Complete!',
                    `Successfully processed all ${imagesToProcess.length} files! ✅ ${totalSuccess} successful, ⚠️ ${totalSkipped} skipped, ❌ ${totalErrors} errors`,
                    100
                );

                // Play completion sound
                UI.playCompletionSound();
                UI.updateLog(`🎉 ALL BATCHES COMPLETE!`);
                UI.updateLog(`📊 Final results: ✅ ${totalSuccess} successful, ⚠️ ${totalSkipped} skipped, ❌ ${totalErrors} errors`);
                UI.updateLog(`⚡ Automatic batch processing prevented timeouts and ensured reliability`);

                // Re-render results
                UI.renderResults(FilterEngine.getFilteredImages());

                // Close modal after 2 seconds with failsafe
                setTimeout(() => {
                    UI.hideProgressModal();
                    alert(`Automatic batch processing complete!\n\n✅ ${totalSuccess} files renamed successfully\n⚠️ ${totalSkipped} files already processed\n❌ ${totalErrors} errors`);
                }, 2000);

                // Emergency modal cleanup failsafe after 10 seconds
                setTimeout(() => {
                    if ($('#processing-modal').is(':visible')) {
                        console.warn('Emergency modal cleanup triggered');
                        UI.hideProgressModal();
                    }
                }, 10000);

            } catch (error) {
                UI.hideProgressModal();
                alert('Error during batch processing: ' + error.message);
                console.error('Batch processing error:', error);

                // Ensure modal is always cleaned up on error
                setTimeout(() => {
                    if ($('#processing-modal').is(':visible')) {
                        console.warn('Emergency error cleanup - forcing modal close');
                        UI.hideProgressModal();
                    }
                }, 1000);
            }
        }

        static updateStats() {
            const { images } = AppState;
            const optimized = images.filter(img => img.optimization_status === 'optimized').length;
            const total = images.length;
            const remaining = total - optimized;
            const percentage = total > 0 ? Math.round((optimized / total) * 100) : 0;

            AppState.stats = { total, optimized, remaining, percentage };

            $('#total-images').text(total);
            $('#optimized-images').text(optimized);
            $('#remaining-images').text(remaining);
            $('#progress-percentage').text(percentage + '%');
            $('#progress-fill').css('width', percentage + '%');
            $('#progress-percent').text(percentage + '%');

            // Update buttons
            const $optimizeHigh = $('#optimize-high-priority');
            if ($optimizeHigh.length) {
                $optimizeHigh.prop('disabled', !images.some(img =>
                    img.priority >= 15 && img.optimization_status !== 'optimized'
                ));
            }

            const $optimizeMedium = $('#optimize-medium-priority');
            if ($optimizeMedium.length) {
                $optimizeMedium.prop('disabled', !images.some(img =>
                    img.priority >= 10 && img.priority < 15 && img.optimization_status !== 'optimized'
                ));
            }

            const $optimizeAll = $('#optimize-all');
            if ($optimizeAll.length) {
                $optimizeAll.prop('disabled', remaining === 0);
            }
        }

        static updateResultsCount(filteredCount) {
            const total = AppState.images.length;
            let label;

            if (total === 0) {
                label = '0 images';
            } else if (filteredCount === total) {
                label = `${filteredCount} image${filteredCount === 1 ? '' : 's'}`;
            } else {
                label = `${filteredCount} of ${total} images`;
            }

            $('#results-count').text(label);
        }

        static showLoading(message = 'Processing...') {
            $('#progress-status').text(message);
            $('.action-buttons button').prop('disabled', true);
        }

        static hideLoading() {
            $('#progress-status').text('Ready');
            $('.action-buttons button').prop('disabled', false);
            this.updateStats(); // Re-enable buttons based on actual state
        }

        static resetOptimizationFlags() {
            this.updateLog('Resetting optimization flags...');

            $.post(CONFIG.endpoints.optimize, {
                action: 'msh_reset_optimization',
                nonce: CONFIG.nonce
            })
            .done((response) => {
                if (response && response.success) {
                    const message = response.data && response.data.message
                        ? response.data.message
                        : 'Optimization flags have been reset.';
                    this.updateLog(message);

                    AppState.images = [];
                    FilterEngine.reset();
                    this.updateFilterControls();
                    this.showWelcomeState();
                    this.updateStats();
                    $('#progress-status').text('Ready for analysis…');
                } else {
                    this.updateLog('Error resetting optimization flags.');
                }
            })
            .fail(() => {
                this.updateLog('Error resetting optimization flags.');
            });
        }

        static showProgressModal(title, status, progress) {
            this.prepareAudioContext();
            $('#modal-title').text(title);
            $('#modal-status').text(status);
            $('#modal-progress-fill').css('width', progress + '%');
            $('#modal-progress-text').text(Math.round(progress) + '%');
            $('#modal-dismiss').hide();
            $('#processing-modal').show();
        }

        static updateProgressModal(title, status, progress) {
            if (title) $('#modal-title').text(title);
            if (status) $('#modal-status').text(status);
            if (progress !== undefined) {
                $('#modal-progress-fill').css('width', progress + '%');
                $('#modal-progress-text').text(Math.round(progress) + '%');
            }
        }

        static enableModalDismiss(buttonText = 'Dismiss') {
            const $btn = $('#modal-dismiss');
            $btn.text(buttonText);
            $btn.show();
        }

        static disableModalDismiss() {
            $('#modal-dismiss').hide();
        }

        static hideProgressModal() {
            $('#processing-modal').hide();
            $('#modal-dismiss').hide();
        }

        static updateIndexStatus(summary) {
            const stats = summary || {};
            let summaryText = 'Not built';
            let timestampText = '';
            let healthState = 'not-built';
            let healthBadgeText = mshImageOptimizer.strings?.indexNotBuilt || 'Not Built';

            const totalEntries = stats.total_entries ?? 0;
            const attachments = stats.indexed_attachments ?? 0;
            const orphanCount = stats.orphaned_entries ?? 0;
            const derivedCount = stats.derived_count ?? 0;

            if (totalEntries || attachments) {
                summaryText = `${totalEntries} entries across ${attachments} attachment${attachments === 1 ? '' : 's'}`;

                const queuedCount = stats.queued_attachments ?? 0;
                const pendingJobs = stats.scheduler?.pending_jobs ?? stats.pending_jobs ?? 0;

                if (queuedCount > 0 || pendingJobs > 0) {
                    healthState = 'queued';
                    healthBadgeText = mshImageOptimizer.strings?.indexQueued || 'Queued';
                } else if (orphanCount > 5) {
                    healthState = 'attention';
                    healthBadgeText = mshImageOptimizer.strings?.indexAttention || 'Attention';
                } else if (totalEntries > 0) {
                    healthState = 'healthy';
                    healthBadgeText = mshImageOptimizer.strings?.indexHealthy || 'Healthy';
                }

                if (pendingJobs === 1 && queuedCount === 0) {
                    $('#index-queue-warning')
                        .text(mshImageOptimizer.strings?.queueInfo || 'Background refresh queued; no action needed unless jobs pile up.')
                        .show();
                } else if (queuedCount > 0 || pendingJobs > 1) {
                    $('#index-queue-warning')
                        .text((mshImageOptimizer.strings?.queueWarning || 'Background indexing in progress') + ` (${queuedCount || pendingJobs} pending)`)
                        .show();
                } else {
                    $('#index-queue-warning').hide();
                }

                const totalFlagged = orphanCount + derivedCount;
                const $cleanupBtn = $('#cleanup-orphans');

                if (orphanCount > 0) {
                    $cleanupBtn.show();
                } else {
                    $cleanupBtn.hide();
                }

                if (totalFlagged > 0) {
                    $('#view-orphan-list').show();
                } else {
                    $('#view-orphan-list').hide();
                    $('#index-orphan-panel').hide().empty();
                }

                const byContext = Array.isArray(stats.by_context) ? stats.by_context : [];
                let postsCount = 0;
                let metaCount = 0;
                let optionsCount = 0;

                byContext.forEach((ctx) => {
                    const type = ctx.context_type || ctx.type;
                    const count = parseInt(ctx.count, 10) || 0;
                    // Map database context_type values to display categories
                    // Support both legacy and current schema values
                    if (type === 'content') {
                        postsCount += count;
                    } else if (type === 'postmeta' || type === 'meta' || type === 'serialized_meta') {
                        metaCount += count;
                    } else if (type === 'serialized_option' || type === 'options' || type === 'option') {
                        optionsCount += count;
                    }
                });

                const total = postsCount + metaCount + optionsCount;
                if (total > 0) {
                    const postsPercent = (postsCount / total * 100).toFixed(1);
                    const metaPercent = (metaCount / total * 100).toFixed(1);
                    const optionsPercent = (optionsCount / total * 100).toFixed(1);

                    $('.index-mix-segment.posts').css('width', postsPercent + '%');
                    $('.index-mix-segment.meta').css('width', metaPercent + '%');
                    $('.index-mix-segment.options').css('width', optionsPercent + '%');

                    $('#mix-posts-count').text(postsCount);
                    $('#mix-meta-count').text(metaCount);
                    $('#mix-options-count').text(optionsCount);
                }

                if (stats.last_update_display) {
                    timestampText = `Last updated: ${stats.last_update_display}`;
                } else if (stats.last_update_raw) {
                    timestampText = `Last updated: ${stats.last_update_raw}`;
                }
            } else {
                $('#index-queue-warning').hide();
                $('#cleanup-orphans').hide();
                $('#view-orphan-list').hide();
                $('#index-orphan-panel').hide().empty();
            }

            const $badge = $('#index-health-badge');
            $badge.removeClass('healthy queued attention').addClass(healthState).text(healthBadgeText);

            $('#index-status-summary').text(summaryText);
            $('#index-last-updated').text(timestampText);

            CONFIG.indexStats = stats || null;
            this.updateOrphanToggleLabel(stats);
            this.renderOrphanList(stats);
            this.renderDiagnostics(CONFIG.diagnostics || {}, CONFIG.indexStats);
            this.renderSchedulerDetails(stats?.scheduler);
        }

        static renderDiagnostics(data = {}, indexStats = null) {
            const map = {
                '#diagnostics-last-analyzer': data.last_analyzer_run,
                '#diagnostics-last-optimization': data.last_optimization_run,
                '#diagnostics-last-quickscan': data.last_quick_scan,
                '#diagnostics-last-visual': data.last_visual_scan,
            };

            Object.entries(map).forEach(([selector, value]) => {
                const $el = $(selector);
                if (!$el.length) {
                    return;
                }
                $el.text(value ? UI.formatDiagnosticsValue(value) : '—');
            });

            UI.renderIndexDiagnostics(indexStats);
        }

        static renderSchedulerDetails(data = null) {
            const formatted = data || {};
            const map = {
                '#scheduler-status-detail': formatted.status ? formatted.status : 'idle',
                '#scheduler-mode-detail': formatted.mode ? formatted.mode : 'smart',
                '#scheduler-pending-detail': Number.isFinite(formatted.pending_jobs) ? formatted.pending_jobs : '0',
                '#scheduler-processed-detail': Number.isFinite(formatted.processed) ? formatted.processed : '0',
                '#scheduler-queued-detail': UI.formatDiagnosticsValue(formatted.queued_at),
                '#scheduler-activity-detail': UI.formatDiagnosticsValue(formatted.last_activity),
                '#scheduler-next-run-detail': UI.formatDiagnosticsValue(formatted.next_run),
            };

            Object.entries(map).forEach(([selector, value]) => {
                const $el = $(selector);
                if (!$el.length) {
                    return;
                }

                $el.text(value || '—');
            });
        }

        static renderIndexDiagnostics(stats) {
            const $badge = $('#diagnostics-index-badge');
            const $details = $('#diagnostics-index-details');
            const $total = $('#diagnostics-index-total');
            const $attachments = $('#diagnostics-index-attachments');
            const $orphans = $('#diagnostics-index-orphans');
            const $lastUpdate = $('#diagnostics-index-lastupdate');

            if (!$badge.length) {
                return;
            }

            if (!stats) {
                UI.setDiagnosticsBadge($badge, 'unknown', '—');
                $details.text('—');
                $total.text('—');
                $attachments.text('—');
                $orphans.text('—');
                $lastUpdate.text('—');
                return;
            }

            const health = UI.determineIndexHealth(stats);
            UI.setDiagnosticsBadge($badge, health.variant, health.label);
            if (health.tooltip) {
                $badge.attr('title', health.tooltip);
            }

            if (health.detail) {
                $details.text(health.detail);
            } else {
                $details.text('—');
            }

            $total.text(UI.formatNumber(stats.total_entries));
            $attachments.text(UI.formatNumber(stats.indexed_attachments));
            $orphans.text(UI.formatNumber(stats.orphaned_entries));
            $lastUpdate.text(UI.formatDiagnosticsValue(stats.last_update_display || stats.last_update_raw));
        }

        static setDiagnosticsBadge($badge, variant, label) {
            if (!$badge || !$badge.length) {
                return;
            }

            const variants = ['healthy', 'queued', 'attention', 'unknown'];
            variants.forEach(state => $badge.removeClass(`diagnostics-badge--${state}`));
            const safeVariant = variants.includes(variant) ? variant : 'unknown';
            $badge.addClass(`diagnostics-badge--${safeVariant}`);
            $badge.text(label || '—');
        }

        static determineIndexHealth(stats) {
            const strings = mshImageOptimizer.strings || {};
            const result = {
                variant: 'unknown',
                label: strings.indexNotBuilt || 'Not Built',
                detail: strings.indexNotBuiltDetail || '',
                tooltip: '',
            };

            const totalEntries = parseInt(stats?.total_entries ?? 0, 10);
            const attachments = parseInt(stats?.indexed_attachments ?? 0, 10);
            const orphaned = parseInt(stats?.orphaned_entries ?? 0, 10);
            const lastUpdateRaw = stats?.last_update_raw || null;
            const lastUpdate = lastUpdateRaw ? new Date(('' + lastUpdateRaw).replace(' ', 'T')) : null;
            const now = new Date();
            const MS_IN_DAY = 86400000;

            if (!totalEntries || !attachments) {
                if (!result.detail) {
                    result.detail = 'Usage index not initialized.';
                }
                result.tooltip = result.detail;
                return result;
            }

            result.variant = 'healthy';
            result.label = strings.indexHealthy || 'Healthy';
            result.detail = strings.indexHealthyDetail || '';

            if (orphaned > 0) {
                result.variant = 'attention';
                result.label = strings.indexAttention || 'Attention';
                const baseDetail = strings.indexOrphanDetail || '';
                const formattedCount = UI.formatNumber(orphaned);
                result.detail = baseDetail
                    ? `${baseDetail} (${formattedCount})`
                    : `${formattedCount} orphaned references detected.`;
            } else if (lastUpdate && ((now - lastUpdate) > (7 * MS_IN_DAY))) {
                result.variant = 'attention';
                result.label = strings.indexAttention || 'Attention';
                const baseDetail = strings.indexStaleDetail || '';
                const staleDays = Math.max(1, Math.floor((now - lastUpdate) / MS_IN_DAY));
                result.detail = baseDetail
                    ? `${baseDetail} (${staleDays} days old)`
                    : `Index is ${staleDays} days old; refresh recommended.`;
            }

            if (!result.detail) {
                result.detail = `${UI.formatNumber(totalEntries)} entries across ${UI.formatNumber(attachments)} attachments`;
            }

            result.tooltip = result.detail;
            return result;
        }

        static formatNumber(value) {
            const num = Number(value || 0);
            if (!Number.isFinite(num) || num <= 0) {
                return '0';
            }
            return num.toLocaleString();
        }

        static formatDiagnosticsValue(rawValue) {
            if (!rawValue) {
                return '—';
            }

            const isoGuess = ('' + rawValue).replace(' ', 'T');
            const date = new Date(isoGuess);
            if (!Number.isNaN(date.getTime())) {
                return date.toLocaleString();
            }

            return rawValue;
        }

        static updateOrphanToggleLabel(summary) {
            const $button = $('#view-orphan-list');
            if (!$button.length) {
                return;
            }

            const orphanCount = summary?.orphaned_entries ?? 0;
            const derivedCount = summary?.derived_count ?? 0;
            const total = orphanCount + derivedCount;

            if (total <= 0) {
                $button.hide();
                return;
            }

            const isVisible = $('#index-orphan-panel').is(':visible');
            const viewText = mshImageOptimizer.strings?.viewOrphans || 'View Orphan List';
            const hideText = mshImageOptimizer.strings?.hideOrphans || 'Hide Orphan List';
            let countLabel = '';
            if (orphanCount > 0 && derivedCount > 0) {
                countLabel = ` (${orphanCount} • ${derivedCount} alt)`;
            } else if (orphanCount > 0) {
                countLabel = ` (${orphanCount})`;
            } else if (derivedCount > 0) {
                countLabel = ` (${derivedCount} alt)`;
            }

            const label = isVisible ? hideText : `${viewText}${countLabel}`;
            $button.text(label).show();
        }

        static renderOrphanList(summary) {
            const $panel = $('#index-orphan-panel');
            if (!$panel.length) {
                return;
            }

            const orphanCount = summary?.orphaned_entries ?? 0;
            const derivedCount = summary?.derived_count ?? 0;
            const orphanPreview = Array.isArray(summary?.orphan_preview) ? summary.orphan_preview : [];
            const derivedPreview = Array.isArray(summary?.derived_preview) ? summary.derived_preview : [];

            if (orphanCount <= 0 && derivedCount <= 0) {
                $panel.hide().empty();
                return;
            }

            const sections = [];

            if (orphanCount > 0) {
                if (orphanPreview.length === 0) {
                    const emptyMessage = mshImageOptimizer.strings?.noOrphans || 'No orphaned attachments detected.';
                    sections.push(`<p style="margin: 0; font-size: 13px; color: #4b5563;">${UI.escapeHtml(emptyMessage)}</p>`);
                } else {
                    const itemsHtml = orphanPreview.map((item) => {
                        const id = item?.ID ?? item?.id ?? 0;
                        const title = item?.title ? UI.escapeHtml(item.title) : UI.escapeHtml(`Attachment #${id}`);
                        const editUrl = item?.edit_url || item?.editUrl || '';
                        const link = editUrl ? `<a href="${UI.escapeHtml(editUrl)}" target="_blank" rel="noopener noreferrer">${title}</a>` : title;
                        const details = [];
                        if (item?.file_path) {
                            details.push(UI.escapeHtml(item.file_path));
                        }
                        if (item?.mime) {
                            details.push(UI.escapeHtml(item.mime));
                        }
                        return `<li>${link}${details.length ? ` <span style="color:#6b7280;">(${details.join(' • ')})</span>` : ''}</li>`;
                    }).join('');

                    let footer = '';
                    if (orphanCount > orphanPreview.length) {
                        const remaining = orphanCount - orphanPreview.length;
                        footer = `<p style="margin-top:8px;font-size:12px;color:#6b7280;">${UI.escapeHtml(`${remaining} additional orphan${remaining === 1 ? '' : 's'} not shown.`)}</p>`;
                    }

                    sections.push(`<h4 style="margin:0 0 6px 0; color:#b91c1c;">${UI.escapeHtml(mshImageOptimizer.strings?.orphanWarning || 'Orphaned entries detected - references to deleted attachments')}</h4><ul>${itemsHtml}</ul>${footer}`);
                }
            }

            if (derivedCount > 0) {
                const headingMargin = orphanCount > 0 ? '16px' : '0';
                if (derivedPreview.length === 0) {
                    const info = mshImageOptimizer.strings?.derivedInfo || 'Alternate formats detected.';
                    sections.push(`<p style="margin:${headingMargin} 0 0; font-size: 13px; color: #2563eb;">${UI.escapeHtml(info)}</p>`);
                } else {
                    const itemsHtml = derivedPreview.map((item) => {
                        const id = item?.ID ?? 0;
                        const title = item?.title ? UI.escapeHtml(item.title) : UI.escapeHtml(`Attachment #${id}`);
                        const editUrl = item?.edit_url || '';
                        const link = editUrl ? `<a href="${UI.escapeHtml(editUrl)}" target="_blank" rel="noopener noreferrer">${title}</a>` : title;
                        const parent = item?.parent_id ? ` → #${item.parent_id}` : '';
                        return `<li>${link}${parent ? `<span style="color:#6b7280;">${parent}</span>` : ''}</li>`;
                    }).join('');

                    sections.push(`<h4 style="margin:${headingMargin} 0 6px 0; color:#2563eb;">${UI.escapeHtml(mshImageOptimizer.strings?.derivedHeading || 'Derived copies (alternate formats)')}</h4><ul>${itemsHtml}</ul>`);
                }
            }

            if (sections.length === 0) {
                const emptyMessage = mshImageOptimizer.strings?.noOrphans || 'No orphaned attachments detected.';
                $panel.html(`<p style="margin: 0; font-size: 13px; color: #4b5563;">${UI.escapeHtml(emptyMessage)}</p>`);
            } else {
                $panel.html(sections.join(''));
            }
        }

        static toggleOrphanList(forceState = null) {
            const $panel = $('#index-orphan-panel');
            if (!$panel.length) {
                return;
            }

            const summary = CONFIG.indexStats || {};
            const orphanCount = summary?.orphaned_entries ?? 0;
            const derivedCount = summary?.derived_count ?? 0;
            if (orphanCount <= 0 && derivedCount <= 0) {
                $panel.hide();
                $('#view-orphan-list').hide();
                return;
            }

            this.renderOrphanList(summary);

            let shouldShow;
            if (forceState === null) {
                shouldShow = !$panel.is(':visible');
            } else {
                shouldShow = Boolean(forceState);
            }

            $panel.toggle(shouldShow);
            this.updateOrphanToggleLabel(summary);
        }

        static playCompletionSound() {
            try {
                const audioContext = this.prepareAudioContext();
                if (!audioContext || audioContext.state !== 'running') {
                    return;
                }

                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 800; // High pitched beep
                gainNode.gain.setValueAtTime(0.25, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.3);
            } catch (e) {
            }
        }

        static playAlertSound() {
            try {
                const audioContext = this.prepareAudioContext();
                if (!audioContext || audioContext.state !== 'running') {
                    return;
                }

                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.value = 420; // Lower alert tone
                gainNode.gain.setValueAtTime(0.35, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);

                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 0.4);
            } catch (e) {
            }
        }

        static prepareAudioContext() {
            try {
                if (!this.audioContext && (window.AudioContext || window.webkitAudioContext)) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (this.audioContext && this.audioContext.state === 'suspended') {
                    this.audioContext.resume();
                }
                return this.audioContext;
            } catch (e) {
                return null;
            }
        }

        static updateLog(message, step = 'step1') {
            const timestamp = new Date().toLocaleTimeString();
            const logMessage = `[${timestamp}] ${message}`;

            let logSelector, sectionSelector;

            if (step === 'step2') {
                // Step 2: Duplicate cleanup operations
                logSelector = '#duplicate-log';
                sectionSelector = '.step2-log';
            } else {
                // Step 1: Image optimization operations (default)
                logSelector = '#optimization-log';
                sectionSelector = '.step1-log';
            }

            // Show appropriate log section
            $(sectionSelector).show();

            // Append to appropriate log (newest at top for better visibility)
            const currentLog = $(logSelector).val();
            $(logSelector).val(logMessage + '\n' + currentLog);

            // Scroll to top to show newest message
            const logElement = document.querySelector(logSelector);
            if (logElement) {
                logElement.scrollTop = 0;
            }
        }
    }

    // =============================================================================
    // OPTIMIZATION ENGINE
    // =============================================================================

    class Optimization {
        static async runAll() {
            if (AppState.processing) return;

            // Get all images that need optimization
            const unoptimizedImages = AppState.images.filter(image =>
                image.optimization_status !== 'optimized'
            );

            if (unoptimizedImages.length === 0) {
                UI.updateLog('No images need optimization.');
                Wizard.handleEvent('optimize-complete');
                return;
            }

            UI.updateLog(`Found ${unoptimizedImages.length} images to optimize`);
            await this.processImagesBatch(unoptimizedImages, 'All Remaining');
        }

        static async runByPriority(level) {
            if (AppState.processing) return;

            let minPriority, maxPriority, label;

            switch (level) {
                case 'high':
                    minPriority = 15;
                    maxPriority = 999;
                    label = 'High Priority (15+)';
                    break;
                case 'medium':
                    minPriority = 10;
                    maxPriority = 14;
                    label = 'Medium Priority (10-14)';
                    break;
                default:
                    console.error('Unknown priority level:', level);
                    return;
            }

            // Filter images by priority and unoptimized status
            const imagesToOptimize = AppState.images.filter(img =>
                img.priority >= minPriority &&
                img.priority <= maxPriority &&
                img.optimization_status !== 'optimized'
            );

            if (imagesToOptimize.length === 0) {
                UI.updateLog(`No ${label.toLowerCase()} images need optimization.`);
                Wizard.handleEvent('optimize-complete');
                return;
            }

            UI.updateLog(`Found ${imagesToOptimize.length} ${label.toLowerCase()} images to optimize`);
            await this.processImagesBatch(imagesToOptimize, label);
        }

        static async runSelected() {
            if (AppState.processing) return;

            // Get all selected images
            const selectedImages = [];
            $('.image-select:checked').each(function() {
                const attachmentId = $(this).val();
                const image = AppState.images.find(img => img.ID == attachmentId);
                if (image && image.optimization_status !== 'optimized') {
                    selectedImages.push(image);
                }
            });

            if (selectedImages.length === 0) {
                UI.updateLog('No selected images need optimization.');
                Wizard.handleEvent('optimize-complete');
                return;
            }

            UI.updateLog(`Found ${selectedImages.length} selected images to optimize`);
            await this.processImagesBatch(selectedImages, 'Selected');
        }

        static async runSingle(imageId) {
            if (AppState.processing) return;

            const image = AppState.images.find((img) => img.ID == imageId);
            if (!image) {
                UI.updateLog(`Unable to locate image ID ${imageId} in the current analysis results.`);
                return;
            }

            if (image.optimization_status === 'optimized') {
                UI.updateLog(`Image ${imageId} is already optimized.`);
                return;
            }

            await this.processImagesBatch([image], `Image ${imageId}`);
        }

        static async makeOptimizationRequest(action) {
            return new Promise((resolve, reject) => {
                $.post(CONFIG.endpoints.optimize, {
                    action: action,
                    nonce: CONFIG.nonce
                })
                .done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Unknown error'));
                    }
                })
                .fail((xhr) => {
                    reject(new Error(`AJAX Error: ${xhr.statusText}`));
                });
            });
        }

        static async processImagesBatch(images, label) {
            if (AppState.processing || images.length === 0) return;

            Wizard.ensureActive(2);
            AppState.processing = true;
            const batchSize = 5; // Process 5 images at a time
            let processed = 0;

            UI.showProgressModal(`Optimizing ${label}`, `Processing ${images.length} images in batches of ${batchSize}...`);
            UI.updateLog(`Starting optimization of ${images.length} ${label.toLowerCase()} images`);

            try {
                for (let i = 0; i < images.length; i += batchSize) {
                    const batch = images.slice(i, i + batchSize);
                    const batchNum = Math.floor(i / batchSize) + 1;
                    const totalBatches = Math.ceil(images.length / batchSize);

                    // Update progress
                    const progressPercent = Math.floor((processed / images.length) * 100);
                    UI.updateProgressModal(
                        `Optimizing ${label}`,
                        `Processing batch ${batchNum}/${totalBatches}: images ${i + 1}-${Math.min(i + batchSize, images.length)}...`,
                        progressPercent
                    );

                    UI.updateLog(`Processing batch ${batchNum}/${totalBatches}: ${batch.length} images`);

                    // Process this batch
                    const results = await this.processBatch(batch);
                    processed += batch.length;

                    // Log results
                    results.forEach(result => {
                        if (result.result && result.result.actions) {
                            UI.updateLog(`Image ${result.id}: ${result.result.actions.join(', ')}`);
                        }
                    });

                    // Small delay between batches to prevent overload
                    if (i + batchSize < images.length) {
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }
                }

                // Final completion
                UI.updateProgressModal(`${label} Complete`, `Successfully optimized ${processed} images!`, 100);
                UI.updateLog(`✅ ${label} optimization complete! Processed ${processed} images.`);

                CONFIG.diagnostics.last_optimization_run = new Date().toISOString();
                UI.renderDiagnostics(CONFIG.diagnostics, CONFIG.indexStats);
                Wizard.handleEvent('optimize-complete');

                // Trigger re-analysis after completion
                setTimeout(() => {
                    if (!AppState.processing) {
                        Analysis.run();
                    }
                }, 2000);

            } catch (error) {
                UI.updateLog(`❌ Error during ${label.toLowerCase()} optimization: ${error.message}`);
            } finally {
                AppState.processing = false;
                setTimeout(() => UI.hideModal(), 2000); // Show completion for 2 seconds
            }
        }

        static async processBatch(batch) {
            const imageIds = batch.map(img => img.ID);

            return new Promise((resolve, reject) => {
                $.post(CONFIG.endpoints.optimize, {
                    action: 'msh_optimize_batch',
                    nonce: CONFIG.nonce,
                    image_ids: imageIds
                })
                .done((response) => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(new Error(response.data || 'Unknown error'));
                    }
                })
                .fail((xhr) => {
                    reject(new Error(`AJAX Error: ${xhr.statusText}`));
                });
            });
        }

        static handleOptimizationResponse(data, label) {
            if (data.results) {
                data.results.forEach(result => {
                    if (result.result && result.result.actions) {
                        UI.updateLog(`Image ${result.id}: ${result.result.actions.join(', ')}`);
                    }
                });
            }

            if (data.message) {
                UI.updateLog(data.message);
            } else {
                UI.updateLog(`${label} optimization complete! Processed ${data.total_processed || 0} images.`);
            }

            // Trigger a re-analysis to refresh the UI
            setTimeout(() => {
                if (!AppState.processing) {
                    Analysis.run();
                }
            }, 1000);
        }
    }

    // =============================================================================
    // ANALYSIS ENGINE
    // =============================================================================

    class Analysis {
        static async run(forceRefresh = false) {
            if (AppState.processing) return;

            // Log if force refresh is requested
            if (forceRefresh) {
                UI.updateLog('Force refresh: Clearing cache and re-analyzing...');
            }

            Wizard.ensureActive(1);
            AppState.processing = true;
            UI.showProgressModal('Analyzing Images', 'Analyzing published images...', 0);
            UI.updateLog('Starting image analysis...');

            // Start progress simulation while analysis is running
            let currentProgress = 10; // Start at 10% immediately
            let progressInterval = null;

            // Update initial progress right away
            UI.updateProgressModal('Analyzing Images',
                'Scanning published images and metadata...',
                currentProgress);

            // Start the progress simulation
            progressInterval = setInterval(() => {
                currentProgress += Math.random() * 10 + 5; // Random increment between 5-15%
                if (currentProgress > 85) currentProgress = 85; // Cap at 85% until completion

                UI.updateProgressModal(null,
                    `Scanning published images and metadata...`,
                    Math.floor(currentProgress));
            }, 400); // Update every 400ms

            try {
                const response = await $.post(CONFIG.endpoints.analyze, {
                    action: 'msh_analyze_images',
                    nonce: CONFIG.nonce,
                    force_refresh: forceRefresh ? 'true' : 'false'
                });

                // Clear progress simulation
                clearInterval(progressInterval);

                if (response.success) {
                    AppState.images = response.data.images || [];

                    UI.updateLog(`Found ${AppState.images.length} published images out of ${response.data.total_images || 0} total images in media library.`);

                    const needsOptimization = AppState.images.filter(img =>
                        img.optimization_status !== 'optimized'
                    ).length;

                    // Show completion in progress modal
                    UI.showProgressModal('Analysis Complete',
                        needsOptimization > 0 ?
                            `Analysis complete. Found ${needsOptimization} image(s) that need optimization.` :
                            'Analysis complete. All images are optimized!',
                        100);

                    if (needsOptimization > 0) {
                        UI.updateLog(`Analysis complete. ${needsOptimization} image(s) need optimization.`);
                    } else {
                        UI.updateLog('Analysis complete. All images are optimized!');
                    }

                    // Play completion sound
                    UI.playCompletionSound();

                    // Hide modal after brief delay
                    setTimeout(() => {
                        UI.hideProgressModal();
                    }, 1500);

                    FilterEngine.apply();

                    CONFIG.diagnostics.last_analyzer_run = new Date().toISOString();
                    UI.renderDiagnostics(CONFIG.diagnostics, CONFIG.indexStats);
                    Wizard.handleEvent('analysis-complete');
                } else {
                    throw new Error(response.data || 'Analysis failed');
                }

            } catch (error) {
                // Clear progress simulation on error
                clearInterval(progressInterval);
                UI.updateLog(`Analysis error: ${error.message}`);
                UI.showWelcomeState();
                UI.hideProgressModal();
                Wizard.handleEvent('analysis-reset');
            } finally {
                AppState.processing = false;
                UI.hideLoading();
            }
        }
    }

    // =============================================================================
    // INDEX BUILDER
    // =============================================================================

    class Index {
        static normalizeSummary(summary) {
            if (!summary) {
                return null;
            }

            if (summary.summary) {
                const base = this.normalizeSummary(summary.summary);
                if (!base) {
                    return null;
                }

                if (Array.isArray(summary.by_context)) {
                    base.by_context = summary.by_context.map((ctx) => ({
                        context_type: ctx.context_type || ctx.type || '',
                        count: parseInt(ctx.count, 10) || 0,
                    }));
                }

                if (summary.orphans) {
                    base.orphaned_entries = parseInt(summary.orphans.count, 10) || 0;
                    base.orphan_preview = Array.isArray(summary.orphans.items) ? summary.orphans.items : [];
                }

                if (summary.last_update_display && !base.last_update_display) {
                    base.last_update_display = summary.last_update_display;
                }

                if (summary.last_update_raw && !base.last_update_raw) {
                    base.last_update_raw = summary.last_update_raw;
                }

                if (summary.queued_attachments !== undefined) {
                    base.queued_attachments = parseInt(summary.queued_attachments, 10) || 0;
                }

                if (summary.pending_jobs !== undefined) {
                    base.pending_jobs = parseInt(summary.pending_jobs, 10) || 0;
                }

                if (summary.scheduler) {
                    base.scheduler = summary.scheduler;
                }

                return base;
            }

            const toInt = (value) => {
                const parsed = parseInt(value, 10);
                return Number.isNaN(parsed) ? 0 : parsed;
            };

            const byContext = Array.isArray(summary.by_context) ? summary.by_context.map((ctx) => ({
                context_type: ctx.context_type || ctx.type || '',
                count: toInt(ctx.count),
            })) : [];

            return {
                total_entries: toInt(summary.total_entries ?? summary.totalEntries),
                indexed_attachments: toInt(summary.indexed_attachments ?? summary.indexedAttachments),
                unique_locations: toInt(summary.unique_locations ?? summary.uniqueLocations),
                last_update_raw: summary.last_update_raw || summary.last_update || summary.lastUpdated || null,
                last_update_display: summary.last_update_display || summary.lastUpdateDisplay || null,
                by_context: byContext,
                orphaned_entries: toInt(summary.orphaned_entries ?? summary.orphanCount ?? 0),
                orphan_preview: Array.isArray(summary.orphan_preview) ? summary.orphan_preview : [],
                queued_attachments: toInt(summary.queued_attachments ?? 0),
                pending_jobs: toInt(summary.pending_jobs ?? 0),
                scheduler: summary.scheduler || null,
            };
        }

        static async build(force = false) {
            const mode = force ? 'full' : 'smart';
            UI.showLoading(force ? 'Queueing force rebuild…' : 'Queueing smart rebuild…');
            UI.showProgressModal(
                force ? 'Force Rebuild Queued' : 'Smart Rebuild Queued',
                'Background job scheduled. It\'s safe to keep working while this runs in the background.',
                5
            );
            UI.enableModalDismiss('Dismiss');
            UI.updateLog(force ? 'Force rebuild queued for the usage index…' : 'Smart usage index refresh queued…');

            try {
                const response = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: CONFIG.actions.queueIndex,
                        nonce: CONFIG.nonce,
                        mode,
                        force: force ? '1' : '0'
                    }
                });

                if (!response || !response.success) {
                    throw new Error((response && response.data) || 'Failed to queue usage index job.');
                }

                const data = response.data || {};

                if (data.message) {
                    UI.updateLog(data.message);
                }

                if (data.status && data.status.summary) {
                    const normalized = Index.normalizeSummary(data.status.summary);
                    if (normalized) {
                        CONFIG.indexStats = normalized;
                        UI.updateIndexStatus(normalized);
                    }
                }

                Index.startPolling(true);
            } catch (error) {
                const message = error?.message || 'Usage index job could not be queued.';
                UI.updateLog(`Usage index queue error: ${message}`);
                UI.playAlertSound();
                UI.updateProgressModal('Usage Index Error', message, 0);
                UI.enableModalDismiss('Close');
            } finally {
                UI.hideLoading();
            }
        }

        static startPolling(immediate = false) {
            if (Index.pollTimer) {
                window.clearInterval(Index.pollTimer);
            }

            if (immediate) {
                Index.fetchStatus();
            }

            Index.pollTimer = window.setInterval(() => {
                Index.fetchStatus();
            }, CONFIG.queuePollInterval);
        }

        static stopPolling() {
            if (Index.pollTimer) {
                window.clearInterval(Index.pollTimer);
                Index.pollTimer = null;
            }
        }

        static async fetchStatus() {
            try {
                const response = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: CONFIG.actions.statusIndex,
                        nonce: CONFIG.nonce
                    }
                });

                if (!response || !response.success) {
                    return;
                }

                Index.handleStatus(response.data);
            } catch (error) {
                // Network hiccups can be ignored; polling will retry.
            }
        }

        static handleStatus(status) {
            if (!status) {
                return;
            }

            if (!Index.loggedMessages) {
                Index.loggedMessages = {};
                Index.loggedMessageOrder = [];
            }

            if (Array.isArray(status.messages)) {
                status.messages.forEach((message) => {
                    const processedValue = parseInt(status.processed, 10);
                    const processed = Number.isNaN(processedValue) ? 0 : processedValue;
                    const completedAt = status.completed_at || '';
                    const messageKey = `${message}::${completedAt}::${processed}`;

                    if (Index.loggedMessages[messageKey]) {
                        return;
                    }

                    UI.updateLog(message);
                    Index.loggedMessages[messageKey] = true;
                    Index.loggedMessageOrder.push(messageKey);

                    if (Index.loggedMessageOrder.length > 100) {
                        const removeKey = Index.loggedMessageOrder.shift();
                        if (removeKey) {
                            delete Index.loggedMessages[removeKey];
                        }
                    }
                });
            }

            if (Array.isArray(status.errors) && status.errors.length) {
                status.errors.forEach((message) => {
                    UI.updateLog(`Usage index warning: ${message}`);
                });
            }

            const summary = status.summary ? Index.normalizeSummary(status.summary) : CONFIG.indexStats;
            if (summary) {
                CONFIG.indexStats = summary;
                UI.updateIndexStatus(summary);
            }

            const processed = parseInt(status.processed, 10) || 0;
            const total = parseInt(status.total, 10) || 0;
            const pending = parseInt(status.pending_jobs, 10);
            const queueCount = parseInt(status.queued_attachments, 10) || 0;
            const statusLabel = status.status || 'idle';
            const modeLabel = status.mode || 'smart';
            const smartDetails = (modeLabel === 'smart' && status.last_result && status.last_result.stats && Array.isArray(status.last_result.stats.processed_details))
                ? status.last_result.stats.processed_details
                : [];

            if (modeLabel !== 'smart') {
                Wizard.recordProcessedAttachments([]);
            }

            if (pending > 0 || statusLabel === 'running' || statusLabel === 'queued') {
                Wizard.ensureActive(4);
            }

            let progress = 0;
            if (total > 0) {
                progress = Math.min(100, Math.round((processed / total) * 100));
            } else if (pending <= 0) {
                progress = 100;
            }

            const detailLines = [];
            if (total > 0) {
                detailLines.push(`Processed: ${processed} of ${total} attachments`);
            } else if (processed > 0) {
                detailLines.push(`Processed: ${processed} attachment${processed === 1 ? '' : 's'}`);
            }

            if (queueCount > 0) {
                detailLines.push(`Queued: ${queueCount}`);
            }

            if (status.next_run) {
                const nextRunDate = new Date(status.next_run);
                if (!Number.isNaN(nextRunDate.getTime())) {
                    detailLines.push(`Next Run: ${nextRunDate.toLocaleString()}`);
                }
            }

            const detailText = detailLines.length
                ? detailLines.join('\n')
                : 'Status: Waiting for scheduler…';

            let processedListText = '';
            let advancedListItems = [];
            if (smartDetails.length) {
                const items = smartDetails.slice(0, 6).map((item) => {
                    const id = item?.id ? `#${item.id}` : '';
                    const parts = [];
                    if (item?.title) {
                        parts.push(item.title);
                    }
                    if (item?.filename) {
                        parts.push(item.filename);
                    }
                    const combined = [id, parts.join(' – ')].filter(Boolean).join(' ');
                    advancedListItems.push(combined || id || 'Attachment');
                    return `- ${combined || id || 'Attachment'}`;
                });

                if (smartDetails.length > 6) {
                    items.push(`- +${smartDetails.length - 6} more…`);
                    advancedListItems.push(`+${smartDetails.length - 6} more…`);
                }

                processedListText = ['Attachments re-indexed:'].concat(items).join('\n');
            }

            if (statusLabel === 'complete' || pending <= 0) {
                let completionCopy = `${detailText}\n\nNext: Review the Diagnostics Snapshot for final counts.`;
                if (modeLabel === 'smart' && processedListText) {
                    completionCopy += `\n\n${processedListText}`;
                }
                UI.updateProgressModal('Usage Index Complete', completionCopy, 100);
                UI.playCompletionSound();
                UI.disableModalDismiss();
                setTimeout(() => UI.hideProgressModal(), 1200);
                Index.stopPolling();
                if (modeLabel === 'smart' && smartDetails.length) {
                    const summary = smartDetails.map((item) => {
                        const id = item?.id ? `#${item.id}` : '';
                        const parts = [];
                        if (item?.title) parts.push(item.title);
                        if (item?.filename) parts.push(item.filename);
                        return [id, parts.join(' – ')].filter(Boolean).join(' ');
                    }).join(', ');
                    UI.updateLog(`[Smart Index] Attachments re-indexed: ${summary}`);
                    Wizard.recordProcessedAttachments(advancedListItems);
                } else {
                    Wizard.recordProcessedAttachments([]);
                }
                Wizard.handleEvent('index-complete');
                return;
            }

            if (statusLabel === 'running') {
                UI.updateProgressModal(
                    modeLabel === 'full' ? 'Rebuilding Usage Index' : 'Refreshing Usage Index',
                    `${detailText}\n\nTip: Diagnostics Snapshot updates live; you can dismiss this dialog and keep working.${processedListText ? `\n\n${processedListText}` : ''}`,
                    progress
                );
                UI.enableModalDismiss('Dismiss');
                if (modeLabel === 'smart') {
                    Wizard.recordProcessedAttachments(advancedListItems);
                }
            } else {
                UI.updateProgressModal(
                    'Usage Index Queued',
                    `${detailText}\n\nTip: Diagnostics Snapshot updates live; you can dismiss this dialog and keep working.${processedListText ? `\n\n${processedListText}` : ''}`,
                    progress
                );
                UI.enableModalDismiss('Dismiss');
                if (modeLabel === 'smart') {
                    Wizard.recordProcessedAttachments(advancedListItems);
                }
            }

            if (statusLabel === 'idle' && !smartDetails.length) {
                Wizard.recordProcessedAttachments([]);
            }
        }
    }

    Index.pollTimer = null;
    Index.loggedMessages = {};
    Index.loggedMessageOrder = [];

    // =============================================================================
    // INITIALIZATION
    // =============================================================================

    $(document).ready(function() {
        Onboarding.init();
        Wizard.init();
        UI.init();
        WebPBrowserDetection.check(); // Check browser WebP support on page load

        // DISABLED: Auto-refresh causing analysis loop every 30 seconds
        // if (CONFIG.autoRefreshInterval > 0) {
        //     setInterval(() => {
        //         if (!AppState.processing && AppState.images.length > 0) {
        //             Analysis.run();
        //         }
        //     }, CONFIG.autoRefreshInterval);
        // }

    });

    // =============================================================================
    // USAGE INDEX
    // =============================================================================

    // REMOVED: Duplicate Index declaration - using class Index above instead
    // WEBP VERIFICATION
    // =============================================================================

    const WebPVerification = {
        async runVerification() {

            try {
                const response = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'msh_verify_webp_status',
                        nonce: mshImageOptimizer.nonce
                    }
                });

                if (response.success) {
                    alert('WebP verification complete. Check console for details.');
                } else {
                    alert('WebP verification failed. Check console for details.');
                    console.error('WebP Verification Error:', response.data);
                }
            } catch (error) {
                console.error('WebP Verification Request Failed:', error);
                alert('Failed to verify WebP status. Check console for details.');
            }
        }
    };

    // =============================================================================
    // WEBP BROWSER DETECTION
    // =============================================================================

    const WebPBrowserDetection = {
        check() {
            console.log('MSH: Starting WebP browser support detection...');

            // Set a timeout fallback in case detection hangs
            const detectionTimeout = setTimeout(() => {
                console.warn('MSH: WebP detection timed out, assuming supported');
                this.updateStatus(true, 'timeout');
            }, 2000); // 2 second timeout

            // Create a WebP test image
            const webp = new Image();

            webp.onload = () => {
                clearTimeout(detectionTimeout);
                const supported = (webp.height === 2);
                console.log('MSH: WebP test image loaded, height:', webp.height, 'supported:', supported);
                this.updateStatus(supported, 'onload');
            };

            webp.onerror = () => {
                clearTimeout(detectionTimeout);
                console.log('MSH: WebP test image failed to load, not supported');
                this.updateStatus(false, 'onerror');
            };

            // Set the test image source (tiny 2x2 WebP image)
            webp.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
        },

        updateStatus(supported, source) {
            console.log('MSH: Updating WebP status - supported:', supported, 'source:', source);

            // Update Browser Support status
            const supportElement = $('#webp-browser-support');
            if (supportElement.length === 0) {
                console.error('MSH: WebP support element #webp-browser-support not found in DOM');
                return;
            }

            if (supported) {
                supportElement
                    .text('Supported')
                    .removeClass('not-supported')
                    .addClass('status-value supported');
            } else {
                supportElement
                    .text('Not Supported')
                    .removeClass('supported')
                    .addClass('status-value not-supported');
            }

            // Update Detection Method
            const cookieExists = document.cookie.indexOf('webp_support=') !== -1;
            const methodElement = $('#webp-detection-method');
            if (methodElement.length > 0) {
                methodElement
                    .text(cookieExists ? 'Cookie + JavaScript' : 'JavaScript Detection')
                    .addClass('status-value active');
            }

            // Update Delivery Status
            const deliveryElement = $('#webp-delivery-status');
            if (deliveryElement.length > 0) {
                deliveryElement
                    .text('Active')
                    .addClass('status-value active');
            }

            console.log('MSH: WebP status update complete');
        }
    };

    // =============================================================================
    // DUPLICATE CLEANUP
    // =============================================================================

    const DuplicateCleanup = {
        scanState: {
            offset: 0,
            results: {},
            pollingTimer: null
        },
        currentSummary: null,
        currentGroupList: [],
        currentScanType: '',
        reviewSelections: {},

        async checkCapabilities() {
            try {
                const response = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'msh_check_capabilities',
                        nonce: mshImageOptimizer.cleanup_nonce
                    }
                });

                if (response && response.success) {
                    const capabilities = response.data || {};
                    const deps = capabilities.dependencies || {};
                    if (!deps.imagick_compare) {
                        const $button = $('#find-similar-images');
                        if ($button.length) {
                            $button.prop('disabled', true)
                                .attr('title', 'Visual similarity requires Imagick compareImages support.');
                        }
                    }
                }
            } catch (error) {
                console.error('Capability check error:', error);
            }
        },

        async testConnection() {

            if (AppState.processing) {
                UI.updateLog('Another process is running. Please wait...', 'step2');
                return;
            }

            AppState.processing = true;

            try {
                UI.updateLog('Testing duplicate cleanup connection...', 'step2');

                const response = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'msh_test_cleanup',
                        nonce: mshImageOptimizer.cleanup_nonce
                    }
                });

                if (response.success) {
                    UI.updateLog('✅ Connection test successful: ' + response.data.message, 'step2');
                } else {
                    UI.updateLog('❌ Connection test failed: ' + (response.data || 'Unknown error'), 'step2');
                }

            } catch (error) {
                console.error('Test cleanup error:', error);
                UI.updateLog('❌ Connection test error: ' + (error.responseText || error.message || 'Unknown error'), 'step2');
            } finally {
                AppState.processing = false;
            }
        },

        async runQuickScan() {
            if (AppState.processing) {
                UI.updateLog('Another process is running. Please wait...', 'step2');
                return;
            }

            Wizard.ensureActive(3);
            AppState.processing = true;

            try {
                UI.updateLog('Starting quick duplicate scan...', 'step2');
                UI.showProgressModal('Scanning for Duplicates', 'Quick scan of recent images...', 0);

                const response = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'msh_quick_duplicate_scan',
                        nonce: mshImageOptimizer.cleanup_nonce
                    }
                });

                if (response.success) {
                    this.displayDuplicateResults(response.data, 'Quick Scan');
                    // Create meaningful summary from new response format
                    const groups = response.data.total_groups || 0;
                    const duplicates = response.data.total_duplicates || 0;
                    const summary = `${groups} duplicate groups found with ${duplicates} files for potential cleanup`;
                    UI.updateLog('✅ Quick scan completed: ' + summary, 'step2');

                    CONFIG.diagnostics.last_quick_scan = new Date().toISOString();
                    UI.renderDiagnostics(CONFIG.diagnostics, CONFIG.indexStats);
                    Wizard.handleEvent('duplicate-complete');
                } else {
                    UI.updateLog('❌ Quick scan failed: ' + (response.data || 'Unknown error'), 'step2');
                }

            } catch (error) {
                console.error('Quick scan error:', error);
                UI.updateLog('❌ Quick scan error: ' + (error.responseText || error.message || 'Unknown error'), 'step2');
            } finally {
                AppState.processing = false;
                UI.hideProgressModal();
            }
        },

        async runDeepScan() {
            if (AppState.processing) {
                UI.updateLog('Another process is running. Please wait...', 'step2');
                return;
            }

            Wizard.ensureActive(3);
            AppState.processing = true;
            this.scanState = {
                offset: 0,
                results: {},
                pollingTimer: null
            };

            UI.updateLog('Starting deep library scan...', 'step2');
            UI.showProgressModal('Deep Library Scan', 'Comprehensive scan of entire media library...', 0);

            this.startProgressPolling();
            this.fetchScanChunk(0);
        },

        async runVisualSimilarityScan() {
            if (AppState.processing) {
                UI.updateLog('Another process is running. Please wait...', 'step2');
                return;
            }

            AppState.processing = true;

            try {
                UI.updateLog('Starting visual similarity scan (MD5 + Perceptual + Filename detection)...', 'step2');
                UI.showProgressModal('Visual Similarity Scan', 'Initializing scan...', 0);

                // Start the scan
                const initResponse = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'msh_visual_similarity_scan_start',
                        nonce: mshImageOptimizer.cleanup_nonce
                    }
                });

                if (!initResponse || !initResponse.success) {
                    const initMessage = initResponse?.data?.message || initResponse?.data || 'Failed to initialize scan';
                    throw new Error(initMessage);
                }

                UI.updateLog('Scan initialized. Processing batches...', 'step2');

                // Process batches
                let completed = false;
                let batchCount = 0;
                const maxBatches = 100; // Safety limit

                while (!completed && batchCount < maxBatches) {
                    const batchResponse = await $.ajax({
                        url: mshImageOptimizer.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'msh_visual_similarity_scan_batch',
                            nonce: mshImageOptimizer.cleanup_nonce
                        }
                    });

                    if (!batchResponse || !batchResponse.success) {
                        const batchMessage = batchResponse?.data?.message || batchResponse?.data || 'Batch processing failed';
                        throw new Error(batchMessage);
                    }

                    const data = batchResponse.data;
                    completed = Boolean(data.completed || data.complete);
                    const progressSnapshot = data.progress || {};
                    const processed = data.processed || progressSnapshot.current || 0;
                    const total = data.total || progressSnapshot.total || 0;
                    const percent = Number.isFinite(progressSnapshot.percent)
                        ? progressSnapshot.percent
                        : (total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0);

                    UI.updateProgressModal(
                        'Visual Similarity Scan',
                        `Processing: ${processed}/${total} attachments`,
                        percent
                    );

                    batchCount++;

                    if (!completed) {
                        await new Promise(resolve => setTimeout(resolve, 100)); // Small delay between batches
                    }
                }

                // Get final results
                const resultsResponse = await $.ajax({
                    url: mshImageOptimizer.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'msh_visual_similarity_scan_results',
                        nonce: mshImageOptimizer.cleanup_nonce
                    }
                });

                if (resultsResponse && resultsResponse.success) {
                    const payload = resultsResponse.data?.results || resultsResponse.data;
                    this.displayVisualSimilarityResults(payload);
                    const totalGroups = payload?.groups ? payload.groups.length : (payload?.total_groups || 0);
                    const summary = payload?.summary || {};
                    const totalDuplicates = summary.total_duplicates
                        ?? payload?.total_duplicates
                        ?? (Array.isArray(payload?.groups)
                            ? payload.groups.reduce((sum, group) => {
                                const files = Array.isArray(group?.files) ? group.files : [];
                                return sum + Math.max(files.length - 1, 0);
                            }, 0)
                            : 0);
                    UI.updateLog(`✅ Visual similarity scan complete: ${totalGroups} groups found with ${totalDuplicates} potential duplicates`, 'step2');

                    CONFIG.diagnostics.last_visual_scan = new Date().toISOString();
                    UI.renderDiagnostics(CONFIG.diagnostics, CONFIG.indexStats);
                } else {
                    const resultMessage = resultsResponse?.data?.message || 'Failed to retrieve scan results';
                    throw new Error(resultMessage);
                }

            } catch (error) {
                console.error('Visual similarity scan error:', error);
                const message = error?.message || error?.responseText || 'Unknown error';
                UI.updateLog('❌ Visual similarity scan error: ' + message, 'step2');
            } finally {
                AppState.processing = false;
                UI.hideProgressModal();
            }
        },

        displayVisualSimilarityResults(data) {
            const resultPayload = data?.results || data;
            if (!resultPayload) {
                UI.updateLog('Visual similarity results unavailable. Please rerun the scan.', 'step2');
                return;
            }

            // Use the same display method as other scans, but add detection method badges
            this.displayDuplicateResults(resultPayload, 'Visual Similarity Scan');
        },

        fetchScanChunk(offset) {
            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_scan_content_chunk',
                    nonce: mshImageOptimizer.cleanup_nonce,
                    offset: offset
                },
                success: (response) => {
                    if (response && response.success) {
                        this.handleChunkSuccess(response.data || {});
                    } else {
                        const message = response && response.data ? response.data : 'Unknown error';
                        this.handleChunkError(null, 'error', message);
                    }
                },
                error: (xhr, status, error) => {
                    this.handleChunkError(xhr, status, error);
                }
            });
        },

        handleChunkSuccess(data) {
            if (!data) {
                this.handleChunkError(null, 'error', 'Empty response from server.');
                return;
            }

            if (data.complete) {
                this.stopProgressPolling();

                const finalMap = data.results || {};
                const summary = this.transformChunkResults(finalMap);
                summary.debug_info = summary.debug_info || {};
                summary.debug_info.images_analyzed = summary.processed;
                if (typeof data.total === 'number') {
                    summary.total_images = data.total;
                }

                this.displayDuplicateResults(summary, 'Deep Library Scan');
                UI.updateLog(summary.summary || '✅ Deep scan completed.', 'step2');

                this.clearScanState();
                AppState.processing = false;
                UI.hideProgressModal();
                Wizard.handleEvent('duplicate-complete');
                return;
            }

            if (!AppState.processing) {
                return;
            }

            const chunkResults = data.chunk_results || {};
            this.scanState.results = Object.assign({}, this.scanState.results, chunkResults);
            this.scanState.offset = data.offset || (this.scanState.offset + 50);

            if (data.progress && typeof data.progress.total !== 'undefined') {
                const current = parseInt(data.progress.current, 10) || 0;
                const total = parseInt(data.progress.total, 10) || 0;
                const message = data.progress.message || `Processing image ${current} of ${total}...`;
                const percent = total > 0 ? Math.round((current / total) * 100) : 0;
                UI.updateProgressModal(null, message, percent);
            }

            setTimeout(() => this.fetchScanChunk(this.scanState.offset), 150);
        },

        handleChunkError(xhr, status, error) {
            this.clearScanState();
            AppState.processing = false;
            UI.hideProgressModal();

            let message = 'Unknown error';
            if (typeof error === 'string') {
                message = error;
            } else if (error && error.message) {
                message = error.message;
            } else if (status) {
                message = status;
            }

            if (xhr && xhr.responseText) {
                message += `: ${xhr.responseText.substring(0, 200)}`;
            }

            UI.updateLog('❌ Deep scan error: ' + message, 'step2');
        },

        startProgressPolling() {
            this.stopProgressPolling();
            this.scanState.pollingTimer = setInterval(() => this.pollProgress(), 2000);
        },

        stopProgressPolling() {
            if (this.scanState.pollingTimer) {
                clearInterval(this.scanState.pollingTimer);
                this.scanState.pollingTimer = null;
            }
        },

        pollProgress() {
            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_get_scan_progress',
                    nonce: mshImageOptimizer.cleanup_nonce
                }
            }).done((response) => {
                if (!response || !response.success || !response.data) {
                    return;
                }

                const progress = response.data;
                if (progress.status === 'processing') {
                    const current = parseInt(progress.current, 10) || 0;
                    const total = parseInt(progress.total, 10) || 0;
                    const message = progress.message || `Processing image ${current} of ${total}...`;
                    const percent = total > 0 ? Math.round((current / total) * 100) : 0;
                    UI.updateProgressModal(null, message, percent);
                } else if (progress.status === 'complete') {
                    UI.updateProgressModal(null, progress.message || 'Scan complete.', 100);
                }
            });
        },

        transformChunkResults(results) {
            const hashGroups = {};
            const entries = results || {};
            Object.keys(entries).forEach((id) => {
                const item = entries[id];
                if (!item || !item.hash) {
                    return;
                }
                if (!hashGroups[item.hash]) {
                    hashGroups[item.hash] = [];
                }
                hashGroups[item.hash].push(item);
            });

            const duplicateGroups = [];
            let totalDuplicates = 0;

            Object.keys(hashGroups).forEach((hash) => {
                const groupItems = hashGroups[hash];
                if (!groupItems || groupItems.length < 2) {
                    return;
                }

                let groupHasUsage = false;
                const files = groupItems.map((entry) => {
                    const usageInfo = entry.usage || {};
                    const usageDetails = Array.isArray(usageInfo.details) ? usageInfo.details : [];
                    const isUsed = Boolean(usageInfo.is_used || usageDetails.length);
                    if (isUsed) {
                        groupHasUsage = true;
                    }

                    return {
                        id: entry.data && (entry.data.ID || entry.data.id) || null,
                        title: entry.data && (entry.data.post_title || entry.data.title) || 'Untitled',
                        filename: entry.data && entry.data.file_path ? entry.data.file_path : '',
                        thumb_url: entry.thumb_url || '',
                        full_url: entry.full_url || '',
                        hash: entry.hash,
                        usage: usageDetails,
                        is_used: isUsed,
                    };
                });

                const duplicatesCount = Math.max(files.length - 1, 0);
                const unusedCount = files.filter((file) => !file.is_used).length;
                const usedCount = files.length - unusedCount;

                duplicateGroups.push({
                    hash,
                    files,
                    total_size: 'Unknown',
                    has_usage: groupHasUsage,
                    used_count: usedCount,
                    unused_count: unusedCount,
                });

                totalDuplicates += duplicatesCount;
            });

            const processedCount = Object.keys(entries).length;
            const safeToRemove = duplicateGroups.reduce((sum, group) => {
                const unusedFiles = group.files.filter((file) => !file.is_used).length;
                if (group.has_usage) {
                    return sum + unusedFiles;
                }
                return sum + Math.max(unusedFiles - 1, 0);
            }, 0);

            return {
                groups: duplicateGroups,
                duplicate_groups: duplicateGroups,
                total_groups: duplicateGroups.length,
                total_duplicates: totalDuplicates,
                safe_to_remove: safeToRemove,
                processed: processedCount,
                summary: duplicateGroups.length
                    ? `${duplicateGroups.length} duplicate groups found with ${totalDuplicates} files for potential cleanup`
                    : 'No duplicates detected in the media library.',
                debug_info: {
                    images_analyzed: processedCount,
                },
            };
        },
        clearScanState() {
            this.stopProgressPolling();
            this.scanState = {
                offset: 0,
                results: {},
                pollingTimer: null
            };
        },

        displayDuplicateResults(results, scanType) {
            const normalizedGroups = Array.isArray(results.duplicate_groups)
                ? results.duplicate_groups
                : Array.isArray(results.groups)
                    ? results.groups
                    : results.groups && typeof results.groups === 'object'
                        ? Object.values(results.groups)
                        : [];

            const totalGroups = typeof results.total_groups === 'number' ? results.total_groups : normalizedGroups.length;
            const totalDuplicates = typeof results.total_duplicates === 'number'
                ? results.total_duplicates
                : normalizedGroups.reduce((sum, group) => {
                    const fileList = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
                    return sum + Math.max((fileList.length || 0) - 1, 0);
                }, 0);
            const safeToRemove = typeof results.safe_to_remove === 'number' ? results.safe_to_remove : totalDuplicates;

            this.currentSummary = results;
            this.currentGroupList = normalizedGroups;
            this.currentScanType = scanType || '';
            this.reviewSelections = {};

            const statsImagesScanned = results.debug_info?.images_analyzed
                || results.processed
                || results.total_images
                || normalizedGroups.reduce((count, group) => {
                    const fileList = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
                    return count + fileList.length;
                }, 0);

            const resultsHtml = `
                <div class="duplicate-scan-results" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #35332f;">${scanType} Results</h4>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 15px;">
                        <div style="text-align: center;">
                            <strong>${statsImagesScanned}</strong><br>
                            <small>Images Scanned</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${totalGroups}</strong><br>
                            <small>Duplicate Groups</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${totalDuplicates}</strong><br>
                            <small>Total Duplicates</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${safeToRemove}</strong><br>
                            <small>Safe to Remove</small>
                        </div>
                    </div>

                    ${normalizedGroups.length > 0 ? `
                        <h5 style="margin: 15px 0 10px 0; color: #d63638;">Duplicate Groups Found</h5>
                        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ccc; border-radius: 3px;">
                            <table style="width: 100%; font-size: 12px;">
                                <thead style="background: #f5f5f5;">
                                    <tr>
                                        <th style="padding: 5px; text-align: left;">Group</th>
                                        <th style="padding: 5px; text-align: left;">Files</th>
                                        <th style="padding: 5px; text-align: left;">Size</th>
                                        <th style="padding: 5px; text-align: left;">Usage</th>
                                        <th style="padding: 5px; text-align: left;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${normalizedGroups.map((group, index) => {
                                        const fileList = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
                                        const fileCount = fileList.length;
                                        const usedCount = group.used_count || 0;
                                        const unusedCount = group.unused_count || 0;
                                        const totalSize = group.total_size || 'Unknown';
                                        let statusLabel = 'Unused';
                                        let statusColor = '#46b450';
                                        if (usedCount > 0 && unusedCount === 0) {
                                            statusLabel = 'In Use';
                                            statusColor = '#d63638';
                                        } else if (usedCount > 0 && unusedCount > 0) {
                                            statusLabel = 'Mixed';
                                            statusColor = '#c28b00';
                                        }

                                        const usageEntries = [];
                                        fileList.forEach((file) => {
                                            const usageItems = Array.isArray(file.usage) ? file.usage : [];
                                            usageItems.forEach((entry) => {
                                                if (!entry || !entry.title) {
                                                    return;
                                                }
                                                usageEntries.push(entry);
                                            });
                                        });

                                        const usageSummary = usageEntries.length
                                            ? usageEntries.slice(0, 3).map((entry) => {
                                                const contextLabel = entry.context ? entry.context : 'content';
                                                return `${UI.escapeHtml(entry.title)} (${UI.escapeHtml(contextLabel)})`;
                                            }).join('; ') + (usageEntries.length > 3 ? ` +${usageEntries.length - 3} more` : '')
                                            : 'No usage detected';

                                        const groupTitle = group.label
                                            ? UI.escapeHtml(group.label)
                                            : UI.escapeHtml(group.confidence_label || `Group ${index + 1}`);

                                        const detectionBadges = Array.isArray(group.detection_badges) && group.detection_badges.length
                                            ? `<div class="duplicate-detection-badges">${group.detection_badges.map((badge) => {
                                                const label = UI.escapeHtml(badge.label || 'Match');
                                                const variant = (typeof badge.variant === 'string' && badge.variant)
                                                    ? badge.variant.toLowerCase().replace(/[^a-z0-9_-]/g, '')
                                                    : 'neutral';
                                                const icon = (typeof badge.icon === 'string' && badge.icon)
                                                    ? badge.icon.toLowerCase().replace(/[^a-z0-9_-]/g, '')
                                                    : '';
                                                const variantClass = variant ? ` is-${variant}` : '';
                                                const iconClass = icon ? ` duplicate-detection-badge--${icon}` : '';
                                                return `<span class="duplicate-detection-badge${variantClass}${iconClass}">${label}</span>`;
                                            }).join('')}</div>`
                                            : (group.detection_method
                                                ? `<div class="duplicate-detection-badges"><span class="duplicate-detection-badge duplicate-detection-badge--hash is-neutral">${UI.escapeHtml(group.detection_method)}</span></div>`
                                                : '');

                                        const confidenceBadge = group.confidence_label
                                            ? `<div style="margin-top:4px;font-size:11px;color:#374151;"><strong>${UI.escapeHtml(group.confidence_label)}</strong>${group.confidence_note ? ` · ${UI.escapeHtml(group.confidence_note)}` : ''}</div>`
                                            : '';

                                        const similarityBadge = group.similarity_label
                                            ? `<div style="margin-top:2px;font-size:11px;color:#4b5563;">${UI.escapeHtml(group.similarity_label)}</div>`
                                            : '';

                                        const patternBadges = Array.isArray(group.pattern_labels) && group.pattern_labels.length
                                            ? `<div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">${group.pattern_labels.map((label) => `<span style="display:inline-block;padding:2px 6px;border-radius:10px;background:#f3f4f6;color:#424242;font-size:10px;">${UI.escapeHtml(label)}</span>`).join('')}</div>`
                                            : '';

                                        return `
                                        <tr data-group-index="${index}">
                                            <td style="padding: 5px;">
                                                <div style="font-weight:600;">${index + 1}. ${groupTitle}</div>
                                                ${detectionBadges}
                                                ${confidenceBadge}
                                                ${similarityBadge}
                                                ${patternBadges}
                                            </td>
                                            <td style="padding: 5px;">${fileCount} files</td>
                                            <td style="padding: 5px;">${totalSize}</td>
                                            <td style="padding: 5px;">
                                                <div><span style="color: ${statusColor};">${statusLabel}</span></div>
                                                <div class="duplicate-usage-summary" data-usage-summary="${index}" style="margin-top: 4px; font-size: 11px; color: #555;">${usageSummary}</div>
                                            </td>
                                            <td style="padding: 5px;">
                                                <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                                    <button class="button button-small duplicate-review-button" data-group="${index}">Review</button>
                                                    <button class="button button-secondary button-small duplicate-verify-builders" data-group="${index}">Check builders</button>
                                                    <button class="button button-link duplicate-verify-deep" data-group="${index}">Deep scan</button>
                                                    <div class="duplicate-plan-indicator status-pending" data-plan-indicator="${index}">Not reviewed</div>
                                                </div>
                                            </td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                        <div class="duplicate-plan-summary" id="duplicate-plan-summary" style="margin-top: 15px; padding: 12px; background: #f8f9fc; border: 1px solid #dce2ec; border-radius: 6px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                            <div class="plan-stats" id="duplicate-plan-stats" style="font-size: 12px; color: #1d2327;">No duplicates selected yet.</div>
                            <div class="plan-actions" style="display: flex; gap: 8px;">
                                <button id="flag-unused-duplicates" class="button button-secondary">Flag unused</button>
                                <button id="apply-duplicate-cleanup" class="button button-primary" disabled>Apply Cleanup Plan</button>
                                <button id="reset-duplicate-plan" class="button button-secondary" disabled>Reset Plan</button>
                            </div>
                        </div>
                    ` : '<div style="color: #46b450; font-weight: bold;">✓ No duplicates found!</div>'}
                </div>
            `;

            $('.duplicate-scan-results').remove();

            const $step2LogSection = $('.step2-log');
            $step2LogSection.after(resultsHtml);

            const $resultsContainer = $step2LogSection.next('.duplicate-scan-results');
            $resultsContainer.find('.duplicate-review-button').on('click', (event) => {
                event.preventDefault();
                const index = parseInt($(event.currentTarget).data('group'), 10);
                if (!Number.isNaN(index)) {
                    this.showReviewModal(index);
                }
            });

            $resultsContainer.find('.duplicate-verify-builders').on('click', (event) => {
                event.preventDefault();
                const $button = $(event.currentTarget);
                const index = parseInt($button.data('group'), 10);
                if (Number.isNaN(index)) {
                    return;
                }
                this.verifyGroupUsage(index, 'medium', $button);
            });

            $resultsContainer.find('.duplicate-verify-deep').on('click', (event) => {
                event.preventDefault();
                const $button = $(event.currentTarget);
                const index = parseInt($button.data('group'), 10);
                if (Number.isNaN(index)) {
                    return;
                }
                this.verifyGroupUsage(index, 'deep', $button);
            });

            const $applyPlan = $resultsContainer.find('#apply-duplicate-cleanup');
            if ($applyPlan.length) {
                $applyPlan.on('click', (event) => {
                    event.preventDefault();
                    this.applyCleanupPlan();
                });
            }
            const $resetPlan = $resultsContainer.find('#reset-duplicate-plan');
            if ($resetPlan.length) {
                $resetPlan.on('click', (event) => {
                    event.preventDefault();
                    this.resetCleanupPlan();
                });
            }
            const $flagUnused = $resultsContainer.find('#flag-unused-duplicates');
            if ($flagUnused.length) {
                $flagUnused.on('click', (event) => {
                    event.preventDefault();
                    this.flagUnusedDuplicates();
                });
            }

            const autoGroups = normalizedGroups.map((group, index) => ({ group, index }))
                .filter(({ group }) => {
                    const fileList = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
                    return fileList.some((file) => !file.is_used);
                });

            autoGroups.forEach(({ index }) => {
                setTimeout(() => {
                    this.verifyGroupUsage(index, 'medium', null, true);
                }, index * 150);
            });

            $step2LogSection.show();
            this.updateCleanupPlanSummary();
        },

        

        showReviewModal(groupIndex) {
            if (!Array.isArray(this.currentGroupList) || this.currentGroupList.length === 0) {
                UI.updateLog('No duplicate data available for review.', 'step2');
                return;
            }

            const group = this.currentGroupList[groupIndex];
            if (!group) {
                UI.updateLog('Unable to load duplicate group details.', 'step2');
                return;
            }

            const placeholder = '/wp-includes/images/media/default.png';
            let rawFiles = [];

            if (Array.isArray(group.files) && group.files.length) {
                rawFiles = group.files;
            } else if (Array.isArray(group.images) && group.images.length) {
                rawFiles = group.images.map((image) => ({
                    id: image.ID || image.id || null,
                    title: image.post_title || image.title || 'Untitled',
                    filename: image.file_path || image.filename || '',
                    thumb_url: image.thumb_url || '',
                    full_url: image.full_url || image.url || '',
                    hash: group.hash || '',
                    usage: image.usage || [],
                    is_used: Boolean(image.is_used),
                }));
            }

            if (!rawFiles.length) {
                UI.updateLog('No files were returned for this duplicate group.', 'step2');
                return;
            }

            const files = rawFiles.map((file) => {
                const rawUsage = Array.isArray(file.usage)
                    ? file.usage
                    : (file.usage && Array.isArray(file.usage.details) ? file.usage.details : []);
                const normalizedUsage = Array.isArray(rawUsage) ? rawUsage : [];
                const isUsed = Boolean(file.is_used || (file.usage && file.usage.is_used) || (normalizedUsage.length > 0));

                return {
                    id: file.id || file.ID || null,
                    title: file.title || file.post_title || 'Untitled',
                    filename: file.filename || file.file_path || file.file || '',
                    thumb_url: file.thumb_url || file.full_url || placeholder,
                    full_url: file.full_url || '',
                    hash: file.hash || group.hash || '',
                    usage: normalizedUsage,
                    is_used: isUsed,
                };
            });

            let selection = this.reviewSelections[groupIndex];
            if (!selection) {
                selection = { keep: null, remove: [] };
            }
            selection.remove = Array.isArray(selection.remove) ? Array.from(new Set(selection.remove)) : [];
            if (!selection.keep && files.length) {
                const preferred = files.find((file) => file.is_used) || files[0];
                selection.keep = preferred ? preferred.id : null;
            }
            selection.remove = selection.remove.filter((id) => id !== selection.keep);
            this.reviewSelections[groupIndex] = selection;

            if (!document.getElementById('duplicate-review-modal-styles')) {
                const modalStyles = `
                    .duplicate-review-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:100000;padding:24px;box-sizing:border-box;display:flex;justify-content:center;align-items:center;}
                    .duplicate-review-content{background:#ffffff;border-radius:10px;max-width:960px;width:100%;max-height:90vh;overflow:auto;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,0.25);}
                    .duplicate-review-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;}
                    .duplicate-review-header h3{margin:0;font-size:20px;color:#1d2327;}
                    .duplicate-review-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:16px;}
                    .duplicate-review-card{background:#fafafa;border:1px solid #e5e5e5;border-radius:6px;padding:12px;display:flex;gap:12px;align-items:flex-start;}
                    .duplicate-review-thumb img{width:80px;height:80px;object-fit:cover;border-radius:4px;border:1px solid #d0d0d0;background:#fff;}
                    .duplicate-review-details p{margin:4px 0;font-size:12px;color:#2c3338;word-break:break-word;}
                    .duplicate-review-badge{display:inline-block;margin-left:6px;padding:1px 6px;border-radius:999px;font-size:10px;text-transform:uppercase;letter-spacing:0.5px;}
                    .duplicate-review-badge.in-use{background:#d63638;color:#ffffff;}
                    .duplicate-review-badge.unused{background:#46b450;color:#ffffff;}
                    .duplicate-review-usage{margin:6px 0 0 0;padding-left:18px;font-size:11px;color:#444;}
                    .duplicate-review-usage li{margin:0 0 4px;}
                    .duplicate-plan-indicator{margin-top:6px;font-size:11px;color:#666;}
                    .duplicate-plan-indicator.status-ready{color:#155724;font-weight:600;}
                    .duplicate-plan-indicator.status-keep{color:#0a4b78;font-weight:600;}
                    .duplicate-plan-indicator.status-warning{color:#d63638;font-weight:600;}
                    .duplicate-plan-indicator.status-pending{color:#666;}
                    .duplicate-review-summary{margin-top:12px;font-size:12px;color:#1d2327;background:#f1f5f9;border-left:3px solid #2271b1;padding:8px 12px;border-radius:4px;}
                    .duplicate-review-instructions{margin:12px 0 4px;font-size:12px;color:#444;}
                    .duplicate-review-actions{text-align:right;margin-top:20px;}
                    .duplicate-review-choice{margin-top:8px;font-size:11px;color:#333;display:flex;flex-direction:column;gap:4px;}
                    .duplicate-review-choice label{display:flex;align-items:center;gap:6px;font-size:11px;color:#2c3338;}
                    .duplicate-review-choice input[type="radio"], .duplicate-review-choice input[type="checkbox"]{margin:0;}
                    @media (max-width:600px){.duplicate-review-card{flex-direction:column;align-items:center;text-align:center;}.duplicate-review-thumb img{width:100%;height:auto;}}
                `;
                $('head').append(`<style id="duplicate-review-modal-styles">${modalStyles}</style>`);
            }

            const cardsHtml = files.map((file) => {
                const fileId = file.id;
                const thumbSrc = UI.escapeHtml(file.thumb_url || placeholder);
                const title = UI.escapeHtml(file.title || 'Untitled');
                const filename = file.filename ? `<p><small>${UI.escapeHtml(file.filename)}</small></p>` : '';
                const idText = fileId ? `<p><small>ID: ${fileId}</small></p>` : '';
                const link = file.full_url ? `<p style="margin-top:6px;"><a href="${UI.escapeHtml(file.full_url)}" target="_blank" rel="noopener noreferrer">Open Original</a></p>` : '';
                const hash = file.hash ? `<p><small>Hash: ${UI.escapeHtml(file.hash)}</small></p>` : '';
                const usageEntries = Array.isArray(file.usage) ? file.usage : [];
                const usageList = usageEntries.length
                    ? `<ul class="duplicate-review-usage">${usageEntries.slice(0, 5).map((entry) => {
                            if (!entry || !entry.title) {
                                return '';
                            }
                            const contextLabel = entry.context ? entry.context : 'content';
                            const statusLabel = entry.status ? ` (${entry.status})` : '';
                            return `<li>${UI.escapeHtml(entry.title)}<small> – ${UI.escapeHtml(contextLabel)}${UI.escapeHtml(statusLabel)}</small></li>`;
                        }).join('')}</ul>${usageEntries.length > 5 ? `<p style="font-size:11px;color:#666;margin:4px 0;">+${usageEntries.length - 5} more references</p>` : ''}`
                    : '<p style="font-size:11px;color:#777;margin:4px 0;">No usage detected for this file.</p>';
                const usageBadge = file.is_used
                    ? '<span class="duplicate-review-badge in-use">In Use</span>'
                    : '<span class="duplicate-review-badge unused">Unused</span>';

                const keepChecked = selection.keep === fileId ? 'checked' : '';
                const removeChecked = selection.remove.includes(fileId) ? 'checked' : '';
                const removeDisabled = keepChecked ? 'disabled' : '';

                return `
                    <div class="duplicate-review-card" data-file-id="${fileId || ''}">
                        <div class="duplicate-review-thumb">
                            <img src="${thumbSrc}" alt="${title}">
                        </div>
                        <div class="duplicate-review-details">
                            <p><strong>${title}</strong> ${usageBadge}</p>
                            ${idText}
                            ${filename}
                            ${hash}
                            <div class="duplicate-review-choice">
                                <label>
                                    <input type="radio" class="duplicate-review-keep" name="duplicate-keep-${groupIndex}" data-group="${groupIndex}" data-id="${fileId}" ${keepChecked}>
                                    Keep this copy
                                </label>
                                <label>
                                    <input type="checkbox" class="duplicate-review-remove" data-group="${groupIndex}" data-id="${fileId}" ${removeChecked} ${removeDisabled}>
                                    Flag for removal
                                </label>
                            </div>
                            ${usageList}
                            ${link}
                        </div>
                    </div>
                `;
            }).join('');

            const hashLabel = group.hash ? `<p style="margin: 4px 0 0; color: #555; font-size: 12px;">Hash: ${UI.escapeHtml(group.hash)}</p>` : '';
            const scanLabel = this.currentScanType ? `<p style="margin: 4px 0 0; color: #666; font-size: 12px;">Scan: ${UI.escapeHtml(this.currentScanType)}</p>` : '';

            const modalHtml = `
                <div id="duplicate-review-modal" class="duplicate-review-overlay">
                    <div class="duplicate-review-content">
                        <div class="duplicate-review-header">
                            <h3>Duplicate Group ${groupIndex + 1} (${files.length} files)</h3>
                            <button type="button" class="button button-secondary duplicate-review-close">Close</button>
                        </div>
                        <p class="duplicate-review-instructions">Choose the file to keep, then flag any remaining copies for removal. These selections stay saved while you review other groups.</p>
                        <div id="duplicate-review-summary" class="duplicate-review-summary"></div>
                        ${hashLabel}
                        ${scanLabel}
                        <div class="duplicate-review-grid">
                            ${cardsHtml || '<p>No files available for review.</p>'}
                        </div>
                        <div class="duplicate-review-actions" style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
                            <button type="button" class="button duplicate-flag-unused" data-group="${groupIndex}">Flag unused in this group</button>
                            <button type="button" class="button button-secondary duplicate-review-close">Close</button>
                        </div>
                    </div>
                </div>
            `;

            $('#duplicate-review-modal').remove();
            $('body').append(modalHtml);

            const updateSummary = () => {
                const activeSelection = this.reviewSelections[groupIndex] || { keep: null, remove: [] };
                const keepLabel = activeSelection.keep ? `Keeping ID ${activeSelection.keep}` : 'Select a file to keep';
                const removeCount = Array.isArray(activeSelection.remove) ? activeSelection.remove.length : 0;
                $('#duplicate-review-summary').text(`${keepLabel} • ${removeCount} flagged for removal`);
                this.updateCleanupPlanSummary();
            };

            const refreshRemoveStates = () => {
                const activeSelection = this.reviewSelections[groupIndex] || { keep: null, remove: [] };
                $('#duplicate-review-modal .duplicate-review-remove').each(function() {
                    const $checkbox = $(this);
                    const id = $checkbox.data('id');
                    if (id === activeSelection.keep) {
                        $checkbox.prop('checked', false).prop('disabled', true);
                    } else {
                        const shouldCheck = Array.isArray(activeSelection.remove) && activeSelection.remove.includes(id);
                        $checkbox.prop('checked', shouldCheck).prop('disabled', false);
                    }
                });
            };

            const closeModal = () => {
                $('#duplicate-review-modal').remove();
                $(document).off('keydown.duplicateReview');
            };

            $('#duplicate-review-modal').on('change', '.duplicate-review-keep', (event) => {
                const id = $(event.currentTarget).data('id');
                const activeSelection = this.reviewSelections[groupIndex] || { keep: null, remove: [] };
                activeSelection.keep = id;
                activeSelection.remove = Array.isArray(activeSelection.remove) ? activeSelection.remove.filter((item) => item !== id) : [];
                this.reviewSelections[groupIndex] = activeSelection;
                refreshRemoveStates();
                updateSummary();
            });

            $('#duplicate-review-modal').on('change', '.duplicate-review-remove', (event) => {
                const id = $(event.currentTarget).data('id');
                const checked = $(event.currentTarget).is(':checked');
                const activeSelection = this.reviewSelections[groupIndex] || { keep: null, remove: [] };
                const removals = new Set(Array.isArray(activeSelection.remove) ? activeSelection.remove : []);
                if (checked) {
                    removals.add(id);
                } else {
                    removals.delete(id);
                }
                activeSelection.remove = Array.from(removals).filter((item) => item !== activeSelection.keep);
                this.reviewSelections[groupIndex] = activeSelection;
                updateSummary();
            });

            updateSummary();
            refreshRemoveStates();

            $('#duplicate-review-modal').on('click', function(event) {
                if (event.target.id === 'duplicate-review-modal') {
                    closeModal();
                }
            });

            $('#duplicate-review-modal .duplicate-review-close').on('click', function(event) {
                event.preventDefault();
                closeModal();
            });

            $('#duplicate-review-modal').on('click', '.duplicate-flag-unused', (event) => {
                event.preventDefault();
                const group = parseInt($(event.currentTarget).data('group'), 10);
                this.flagUnusedInGroup(group);
                refreshRemoveStates();
                updateSummary();
            });

            $(document).on('keydown.duplicateReview', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        },

        updateGroupRowIndicators() {
            const selections = this.reviewSelections || {};
            $('.duplicate-plan-indicator').each(function() {
                const $indicator = $(this);
                const index = parseInt($indicator.data('plan-indicator'), 10);
                const selection = selections[index];

                let label = 'Not reviewed';
                let cssClass = 'status-pending';

                if (selection) {
                    const keepId = selection.keep || null;
                    const removalCount = Array.isArray(selection.remove) ? selection.remove.length : 0;

                    if (keepId && removalCount > 0) {
                        label = `Ready • remove ${removalCount}`;
                        cssClass = 'status-ready';
                    } else if (keepId && removalCount === 0) {
                        label = 'Keeper selected';
                        cssClass = 'status-keep';
                    } else if (!keepId && removalCount > 0) {
                        label = 'Choose keeper';
                        cssClass = 'status-warning';
                    } else {
                        label = 'Not reviewed';
                        cssClass = 'status-pending';
                    }
                }

                $indicator
                    .removeClass('status-pending status-ready status-keep status-warning')
                    .addClass(cssClass)
                    .text(label);
            });
        },

        verifyGroupUsage(groupIndex, level = 'medium', $button = null, silent = false) {
            const group = this.currentGroupList[groupIndex];
            if (!group) {
                return;
            }

            const files = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
            const attachmentIds = files
                .map((file) => file.id || file.ID || null)
                .filter((id) => id !== null);

            if (!attachmentIds.length) {
                return;
            }

            const $targetButton = $button || $(`.duplicate-verify-${level === 'deep' ? 'deep' : 'builders'}[data-group="${groupIndex}"]`);
            const originalLabel = $targetButton.length ? $targetButton.text() : '';

            if ($targetButton.length) {
                $targetButton.prop('disabled', true).text(level === 'deep' ? 'Scanning…' : 'Checking…');
            }

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_verify_usage_group',
                    nonce: mshImageOptimizer.cleanup_nonce,
                    level: level,
                    attachment_ids: attachmentIds,
                },
            }).done((response) => {
                if (!response || !response.success || !response.data || !response.data.usage) {
                    throw new Error('Invalid verification response');
                }

                const usageMap = response.data.usage;
                let groupHasUsage = false;

                let usedCount = 0;
                files.forEach((file) => {
                    const fileId = file.id || file.ID;
                    if (!usageMap[fileId]) {
                        return;
                    }

                    const contexts = usageMap[fileId].contexts || [];
                    const mappedUsage = contexts.map((context) => ({
                        title: context.location || '',
                        context: context.context || 'builder',
                    }));

                    if (!Array.isArray(file.usage)) {
                        file.usage = [];
                    }
                    file.usage = file.usage.concat(mappedUsage);
                    file.is_used = file.is_used || Boolean(usageMap[fileId].is_used);

                    if (file.is_used) {
                        groupHasUsage = true;
                        usedCount += 1;
                    }
                });

                const totalFiles = files.length;
                const unusedCount = Math.max(totalFiles - usedCount, 0);
                group.has_usage = groupHasUsage;
                group.used_count = usedCount;
                group.unused_count = unusedCount;

                // Remove any files that were previously flagged for removal but are now marked as used
                const currentSelection = (this.reviewSelections || {})[groupIndex];
                if (currentSelection && Array.isArray(currentSelection.remove)) {
                    const filteredRemovals = currentSelection.remove.filter((id) => {
                        const match = files.find((file) => (file.id || file.ID) === id);
                        return !(match && match.is_used);
                    });
                    currentSelection.remove = filteredRemovals;
                    this.reviewSelections[groupIndex] = currentSelection;
                }

                // Update usage summary text for the row
                const summaryText = this.buildUsageSummary(groupIndex);
                $(`.duplicate-usage-summary[data-usage-summary="${groupIndex}"]`).text(summaryText);

                this.updateCleanupPlanSummary();

                if (!silent) {
                    UI.updateLog(level === 'deep'
                        ? `Deep scan complete for group ${groupIndex + 1}`
                        : `Builder usage check complete for group ${groupIndex + 1}`, 'step2');

                    UI.playCompletionSound();
                }
            }).fail((xhr, status, error) => {
                const message = (xhr && xhr.responseText) || error || status || 'Unknown error';
                UI.updateLog('Usage verification failed: ' + message, 'step2');
                UI.playAlertSound();
            }).always(() => {
                if ($targetButton.length) {
                    $targetButton.prop('disabled', false).text(originalLabel || 'Check builders');
                }
            });
        },

        buildUsageSummary(groupIndex) {
            const group = this.currentGroupList[groupIndex];
            const files = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);

            const usageEntries = [];
            files.forEach((file) => {
                (Array.isArray(file.usage) ? file.usage : []).forEach((entry) => {
                    if (entry && entry.title) {
                        usageEntries.push(`${entry.title}${entry.context ? ` (${entry.context})` : ''}`);
                    }
                });
            });

            if (!usageEntries.length) {
                return 'No usage detected';
            }

            const summary = usageEntries.slice(0, 3).join('; ');
            return usageEntries.length > 3 ? `${summary} +${usageEntries.length - 3} more` : summary;
        },

        updateCleanupPlanSummary() {
            const planStats = $('#duplicate-plan-stats');
            const applyButton = $('#apply-duplicate-cleanup');
            const resetButton = $('#reset-duplicate-plan');
            if (!planStats.length) {
                return;
            }

            const selections = this.reviewSelections || {};
            let keepCount = 0;
            const removalSet = new Set();
            Object.values(selections).forEach((selection) => {
                if (!selection) {
                    return;
                }
                if (selection.keep) {
                    keepCount += 1;
                }
                (Array.isArray(selection.remove) ? selection.remove : []).forEach((id) => {
                    if (id) {
                        removalSet.add(id);
                    }
                });
            });

            const removalCount = removalSet.size;
            const groupCount = Array.isArray(this.currentGroupList) ? this.currentGroupList.length : 0;
            const pendingGroups = Math.max(groupCount - keepCount, 0);

            let summaryText = 'No duplicates selected yet.';
            if (keepCount || removalCount) {
                const keepSummary = keepCount ? `${keepCount} keepers chosen` : 'No keeper selected';
                const removalSummary = removalCount ? `${removalCount} flagged for removal` : 'No removals flagged';
                const pendingSummary = pendingGroups ? `${pendingGroups} group(s) pending review` : 'All groups reviewed';
                summaryText = `${keepSummary} • ${removalSummary} • ${pendingSummary}`;
            }

            planStats.text(summaryText);
            if (applyButton.length) {
                applyButton.prop('disabled', removalCount === 0);
            }
            if (resetButton.length) {
                resetButton.prop('disabled', keepCount === 0 && removalCount === 0);
            }

            this.updateGroupRowIndicators();
        },

        gatherRemovalIds() {
            const removalSet = new Set();
            Object.values(this.reviewSelections || {}).forEach((selection) => {
                if (!selection) {
                    return;
                }
                (Array.isArray(selection.remove) ? selection.remove : []).forEach((id) => {
                    if (id) {
                        removalSet.add(id);
                    }
                });
            });
            return Array.from(removalSet);
        },

        flagUnusedDuplicates() {
            const groups = this.currentGroupList || [];
            groups.forEach((group, index) => {
                const files = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
                if (!files.length) {
                    return;
                }

                this.flagUnusedInGroup(index, false);
            });

            this.updateCleanupPlanSummary();
            UI.updateLog('Unused duplicates have been flagged. Review the plan before applying.', 'step2');
            UI.playCompletionSound();
        },

        flagUnusedInGroup(groupIndex, logAction = true) {
            const groups = this.currentGroupList || [];
            const group = groups[groupIndex];
            if (!group) {
                return;
            }

            const files = Array.isArray(group.files) ? group.files : (Array.isArray(group.images) ? group.images : []);
            if (!files.length) {
                return;
            }

            let selection = this.reviewSelections[groupIndex];
            if (!selection) {
                selection = { keep: null, remove: [] };
            }

            if (!selection.keep) {
                const preferred = files.find((file) => file.is_used) || files[0];
                selection.keep = preferred ? (preferred.id || preferred.ID || null) : null;
            }

            const removalSet = new Set(Array.isArray(selection.remove) ? selection.remove : []);
            files.forEach((file) => {
                const fileId = file.id || file.ID;
                if (!fileId || fileId === selection.keep) {
                    return;
                }

                if (!file.is_used) {
                    removalSet.add(fileId);
                }
            });

            selection.remove = Array.from(removalSet).filter((id) => id !== selection.keep);
            this.reviewSelections[groupIndex] = selection;

            if (logAction) {
                UI.updateLog(`Unused duplicates flagged for group ${groupIndex + 1}.`, 'step2');
                UI.playCompletionSound();
            }
        },

        resetCleanupPlan() {
            this.reviewSelections = {};
            this.updateCleanupPlanSummary();
            $('#duplicate-review-modal').remove();
            $(document).off('keydown.duplicateReview');
            UI.updateLog('Cleanup plan reset. All selections cleared.', 'step2');
        },

        applyCleanupPlan() {
            const removalIds = this.gatherRemovalIds().map((id) => parseInt(id, 10)).filter((id) => !Number.isNaN(id));
            if (removalIds.length === 0) {
                UI.updateLog('No duplicates flagged for removal. Select files in the review modal first.', 'step2');
                return;
            }

            if (!window.confirm(`Remove ${removalIds.length} duplicate file(s)? This cannot be undone.`)) {
                return;
            }

            if (AppState.processing) {
                UI.updateLog('Another process is running. Please wait...', 'step2');
                return;
            }

            AppState.processing = true;

            const cleanupState = {
                remaining: removalIds.slice(),
                total: removalIds.length,
                processed: 0,
                deleted: 0,
                results: [],
                batchSize: 20,
            };

            this.currentCleanupState = cleanupState;
            UI.showProgressModal('Applying Cleanup Plan', `Removing ${cleanupState.total} duplicate file(s)...`, 0);
            this.runCleanupBatch();
        },

        runCleanupBatch() {
            const state = this.currentCleanupState;
            if (!state) {
                return;
            }

            if (!state.remaining.length) {
                this.finishCleanupRun();
                return;
            }

            const batch = state.remaining.splice(0, state.batchSize);
            const total = state.total;
            const startMessage = `Processing batch (${state.processed}/${total})...`;
            UI.updateProgressModal(null, startMessage, Math.round((state.processed / total) * 100));

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_cleanup_media',
                    nonce: mshImageOptimizer.cleanup_nonce,
                    action_type: 'safe',
                    image_ids: batch,
                },
            }).done((response) => {
                if (!response || !response.success) {
                    throw new Error((response && response.data && response.data.message) || 'Cleanup failed');
                }

                const data = response.data || {};
                const deletedCount = data.deleted_count || 0;
                const resultRows = Array.isArray(data.results) ? data.results : [];

                state.processed += batch.length;
                state.deleted += deletedCount;
                state.results = state.results.concat(resultRows);

                resultRows.forEach((result) => {
                    UI.updateLog(`• ID ${result.id}: ${result.reason || result.status}`, 'step2');
                });

                const percent = Math.round((state.processed / total) * 100);
                UI.updateProgressModal(null, `Removed ${state.deleted}/${total} files…`, percent);

                this.runCleanupBatch();
            }).fail((xhr, status, error) => {
                const message = (xhr && xhr.responseText) || error || status || 'Unknown error';
                UI.updateLog('❌ Cleanup failed: ' + message, 'step2');
                UI.playAlertSound();
                this.abortCleanupRun();
            });
        },

        finishCleanupRun() {
            const state = this.currentCleanupState;
            if (!state) {
                return;
            }

            UI.updateLog(`Cleanup results (${state.deleted} deleted):`, 'step2');
            if (state.deleted > 0) {
                UI.updateLog('✅ Duplicate cleanup complete. Re-run a scan to refresh results.', 'step2');
            } else {
                UI.updateLog('No files were deleted. All flagged files may still be in use.', 'step2');
            }

            $('#duplicate-review-modal').remove();
            $(document).off('keydown.duplicateReview');
            this.reviewSelections = {};
            this.updateCleanupPlanSummary();

            UI.playCompletionSound();
            AppState.processing = false;
            this.currentCleanupState = null;
            UI.hideProgressModal();
        },

        abortCleanupRun() {
            AppState.processing = false;
            this.currentCleanupState = null;
            UI.hideProgressModal();
        },

        displayVerificationResults(results) {
            const resultsHtml = `
                <div class="webp-verification-results" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0;">
                    <h4 style="margin: 0 0 10px 0; color: #35332f;">WebP Verification Results</h4>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">
                        <div style="text-align: center;">
                            <strong>${results.total_optimized}</strong><br>
                            <small>Optimized Images</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${results.webp_compatible_optimized}</strong><br>
                            <small>WebP Compatible</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${results.webp_missing}</strong><br>
                            <small>Missing WebP</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${results.svg_files}</strong><br>
                            <small>SVG Files</small>
                        </div>
                        <div style="text-align: center;">
                            <strong>${results.stats.webp_success_rate}%</strong><br>
                            <small>Success Rate</small>
                        </div>
                    </div>

                    ${results.issues_found.length > 0 ? `
                        <h5 style="margin: 15px 0 10px 0; color: #d63638;">Issues Found (${results.issues_found.length})</h5>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 3px;">
                            <table style="width: 100%; font-size: 12px;">
                                <thead style="background: #f5f5f5;">
                                    <tr>
                                        <th style="padding: 5px; text-align: left;">ID</th>
                                        <th style="padding: 5px; text-align: left;">Filename</th>
                                        <th style="padding: 5px; text-align: left;">Issue</th>
                                        <th style="padding: 5px; text-align: left;">Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${results.issues_found.map(issue => `
                                        <tr>
                                            <td style="padding: 5px;">${issue.attachment_id}</td>
                                            <td style="padding: 5px;">${issue.filename}</td>
                                            <td style="padding: 5px;">
                                                <span style="color: ${issue.issue === 'webp_missing' ? '#d63638' : '#8a2387'};">
                                                    ${issue.issue}
                                                </span>
                                            </td>
                                            <td style="padding: 5px;">${issue.message}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<div style="color: #46b450; font-weight: bold;">✓ No WebP issues found!</div>'}
                </div>
            `;

            // Insert results after the log section
            const $logSection = $('.msh-log-section');
            $logSection.after(resultsHtml);

            // Show the log section if hidden
            $logSection.show();
        }
    };

    // Expose API for debugging
    window.MSH_ImageOptimizer = {
        AppState,
        Onboarding,
        FilterEngine,
        UI,
        Optimization,
        Analysis,
        Index,
        WebPVerification,
        DuplicateCleanup
    };

    // =============================================================================
    // AI METADATA REGENERATION MODULE
    // =============================================================================

    const AIRegeneration = {
        pollInterval: null,
        pollFrequency: 2000, // 2 seconds

        init() {
            this.initDashboard();
            this.initModal();
            this.initJobControls();
            this.checkForActiveJob();
        },

        initDashboard() {
            // Populate initial credit stats
            $('#ai-credits-available').text(mshImageOptimizer.aiCredits || 0);
            $('#ai-plan-tier').text(this.getPlanLabel(mshImageOptimizer.aiPlanTier || 'free'));
            $('#ai-credits-used-month').text(mshImageOptimizer.aiCreditsUsedMonth || 0);

            // Show last job info if available
            if (mshImageOptimizer.aiLastJob) {
                const job = mshImageOptimizer.aiLastJob;
                const completed = new Date(job.completed_at);
                $('#ai-last-run').text(this.formatDateTime(completed));
                $('#ai-last-run-summary').text(
                    job.successful + ' successful, ' +
                    job.skipped + ' skipped, ' +
                    job.failed + ' failed'
                );
            }

            // Bind start button
            $('#start-ai-regeneration').on('click', () => this.openModal());
        },

        initModal() {
            const $modal = $('#ai-regen-modal');

            // Open modal handler
            $('#start-ai-regeneration').on('click', () => {
                $modal.fadeIn(200);
                this.loadModalCounts();
                this.updateEstimate();
            });

            // Close modal handlers
            $('#ai-modal-close, #ai-modal-cancel, .ai-modal-overlay').on('click', () => {
                $modal.fadeOut(200);
            });

            // Update estimate when scope/mode/fields change
            $('input[name="ai_scope"], input[name="ai_mode"], input[name="ai_fields[]"]').on('change', () => {
                this.updateEstimate();
            });

            // Start regeneration
            $('#ai-modal-start').on('click', () => this.startRegeneration());
        },

        initJobControls() {
            $('#ai-pause-job').on('click', () => this.pauseJob());
            $('#ai-resume-job').on('click', () => this.resumeJob());
            $('#ai-cancel-job').on('click', () => this.cancelJob());
        },

        openModal() {
            $('#ai-regen-modal').fadeIn(200);
            this.loadModalCounts();
            this.updateEstimate();
        },

        loadModalCounts() {
            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_get_ai_regen_counts',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#ai-scope-all-count').text('(' + response.data.all + ' images)');
                        $('#ai-scope-published-count').text('(' + response.data.published + ' images)');
                        $('#ai-scope-missing-count').text('(' + response.data.missing_metadata + ' images)');
                    }
                }
            });
        },

        updateEstimate() {
            const scope = $('input[name="ai_scope"]:checked').val();
            const mode = $('input[name="ai_mode"]:checked').val();
            const fields = $('input[name="ai_fields[]"]:checked').map(function() {
                return $(this).val();
            }).get();

            if (fields.length === 0) {
                $('#ai-estimate-count').text('0');
                $('#ai-estimate-credits').text('0');
                return;
            }

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_estimate_ai_regeneration',
                    nonce: mshImageOptimizer.nonce,
                    scope: scope,
                    mode: mode,
                    fields: fields
                },
                success: (response) => {
                    if (response.success) {
                        $('#ai-estimate-count').text(response.data.image_count);
                        $('#ai-estimate-credits').text(response.data.estimated_credits);
                        $('#ai-estimate-available').text(mshImageOptimizer.aiCredits);

                        // Show warning if insufficient credits
                        if (response.data.estimated_credits > mshImageOptimizer.aiCredits) {
                            $('#ai-insufficient-credits').show();
                            $('#ai-modal-start').prop('disabled', true);
                        } else {
                            $('#ai-insufficient-credits').hide();
                            $('#ai-modal-start').prop('disabled', false);
                        }
                    }
                }
            });
        },

        startRegeneration() {
            const scope = $('input[name="ai_scope"]:checked').val();
            const mode = $('input[name="ai_mode"]:checked').val();
            const fields = $('input[name="ai_fields[]"]:checked').map(function() {
                return $(this).val();
            }).get();

            if (fields.length === 0) {
                alert('Please select at least one field to generate.');
                return;
            }

            $('#ai-modal-start').prop('disabled', true).text('Starting...');

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_start_ai_regeneration',
                    nonce: mshImageOptimizer.nonce,
                    scope: scope,
                    mode: mode,
                    fields: fields
                },
                success: (response) => {
                    if (response.success) {
                        // Close modal
                        $('#ai-regen-modal').fadeOut(200);

                        // Show progress widget
                        $('#ai-regen-progress-widget').slideDown(300);

                        // Start polling for status
                        this.startPolling();

                        // Log initial message
                        this.addProgressLog('Job started: processing ' + response.data.total + ' images');
                    } else {
                        alert('Failed to start regeneration: ' + (response.data.message || 'Unknown error'));
                        $('#ai-modal-start').prop('disabled', false).text('Start Regeneration');
                    }
                },
                error: () => {
                    alert('Network error while starting regeneration. Please try again.');
                    $('#ai-modal-start').prop('disabled', false).text('Start Regeneration');
                }
            });
        },

        checkForActiveJob() {
            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_get_ai_regeneration_status',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success && response.data.job) {
                        const job = response.data.job;
                        if (job.status === 'queued' || job.status === 'running' || job.status === 'paused') {
                            // Resume monitoring active job
                            $('#ai-regen-progress-widget').show();
                            this.updateProgressWidget(job);
                            this.startPolling();
                        }
                    }
                }
            });
        },

        startPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }

            this.pollInterval = setInterval(() => {
                this.pollJobStatus();
            }, this.pollFrequency);
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        pollJobStatus() {
            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_get_ai_regeneration_status',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success && response.data.job) {
                        this.updateProgressWidget(response.data.job);

                        // Stop polling if job is complete
                        if (response.data.job.status === 'completed' ||
                            response.data.job.status === 'cancelled' ||
                            response.data.job.status === 'failed') {
                            this.stopPolling();
                            this.onJobComplete(response.data.job);
                        }
                    }
                }
            });
        },

        updateProgressWidget(job) {
            const percent = job.total > 0 ? Math.round((job.processed / job.total) * 100) : 0;

            // Update progress bar
            $('#ai-progress-fill').css('width', percent + '%');
            $('#ai-progress-text').text(percent + '%');

            // Update stats
            $('#ai-processed-count').text(job.processed + ' / ' + job.total);
            $('#ai-success-count').text(job.successful || 0);
            $('#ai-skipped-count').text(job.skipped || 0);
            $('#ai-failed-count').text(job.failed || 0);
            $('#ai-credits-consumed').text(job.credits_used || 0);

            // Update status badge
            $('#ai-job-status').text(this.getStatusLabel(job.status)).attr('class', 'ai-progress-status status-' + job.status);

            // Update pause/resume button visibility
            if (job.status === 'paused') {
                $('#ai-pause-job').hide();
                $('#ai-resume-job').show();
            } else {
                $('#ai-pause-job').show();
                $('#ai-resume-job').hide();
            }

            // Update credit display in dashboard
            if (job.credits_remaining !== undefined) {
                $('#ai-credits-available').text(job.credits_remaining);
            }

            // Add new log messages
            if (job.messages && job.messages.length > 0) {
                job.messages.forEach(msg => {
                    if (!this.isLogDuplicate(msg)) {
                        this.addProgressLog(msg);
                    }
                });
            }
        },

        pauseJob() {
            $('#ai-pause-job').prop('disabled', true).text('Pausing...');

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_pause_ai_regeneration',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.addProgressLog('Job paused');
                        $('#ai-pause-job').hide();
                        $('#ai-resume-job').show();
                    } else {
                        alert('Failed to pause: ' + (response.data.message || 'Unknown error'));
                    }
                    $('#ai-pause-job').prop('disabled', false).text('Pause');
                }
            });
        },

        resumeJob() {
            $('#ai-resume-job').prop('disabled', true).text('Resuming...');

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_resume_ai_regeneration',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.addProgressLog('Job resumed');
                        $('#ai-pause-job').show();
                        $('#ai-resume-job').hide();
                    } else {
                        alert('Failed to resume: ' + (response.data.message || 'Unknown error'));
                    }
                    $('#ai-resume-job').prop('disabled', false).text('Resume');
                }
            });
        },

        cancelJob() {
            if (!confirm('Are you sure you want to cancel this regeneration job?')) {
                return;
            }

            $('#ai-cancel-job').prop('disabled', true).text('Cancelling...');

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_cancel_ai_regeneration',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.addProgressLog('Job cancelled');
                        this.stopPolling();
                        this.onJobComplete(response.data.job);
                    } else {
                        alert('Failed to cancel: ' + (response.data.message || 'Unknown error'));
                    }
                    $('#ai-cancel-job').prop('disabled', false).text('Cancel');
                }
            });
        },

        onJobComplete(job) {
            this.addProgressLog('Job ' + job.status + ': ' + job.successful + ' successful, ' + job.skipped + ' skipped, ' + job.failed + ' failed');

            // Update dashboard stats
            $('#ai-last-run').text(this.formatDateTime(new Date(job.completed_at || job.updated_at)));
            $('#ai-last-run-summary').text(
                job.successful + ' successful, ' +
                job.skipped + ' skipped, ' +
                job.failed + ' failed'
            );

            // Refresh credit balance
            this.refreshCreditBalance();
        },

        refreshCreditBalance() {
            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_get_ai_credit_balance',
                    nonce: mshImageOptimizer.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#ai-credits-available').text(response.data.balance);
                        $('#ai-credits-used-month').text(response.data.used_this_month);
                        mshImageOptimizer.aiCredits = response.data.balance;
                    }
                }
            });
        },

        addProgressLog(message) {
            const $log = $('#ai-progress-log-list');
            const timestamp = new Date().toLocaleTimeString();
            const $item = $('<li></li>').html('<span class="log-time">' + timestamp + '</span> ' + message);
            $log.prepend($item);

            // Keep only last 10 messages
            if ($log.children().length > 10) {
                $log.children().last().remove();
            }
        },

        isLogDuplicate(message) {
            let isDuplicate = false;
            $('#ai-progress-log-list li').each(function() {
                if ($(this).text().includes(message)) {
                    isDuplicate = true;
                    return false;
                }
            });
            return isDuplicate;
        },

        getPlanLabel(tier) {
            const labels = {
                'free': 'Free Plan',
                'ai_starter': 'AI Starter (100/mo)',
                'ai_pro': 'AI Pro (500/mo)',
                'ai_business': 'AI Business (2000/mo)'
            };
            return labels[tier] || tier;
        },

        getStatusLabel(status) {
            const labels = {
                'queued': 'Queued',
                'running': 'Running',
                'paused': 'Paused',
                'completed': 'Completed',
                'cancelled': 'Cancelled',
                'failed': 'Failed'
            };
            return labels[status] || status;
        },

        formatDateTime(date) {
            if (!date) return 'Never';
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    };

    // Initialize AI Regeneration when DOM is ready
    $(document).ready(function() {
        if ($('#ai-regen-dashboard').length) {
            AIRegeneration.init();
        }
    });

})(jQuery);
