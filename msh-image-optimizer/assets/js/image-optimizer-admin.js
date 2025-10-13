/**
 * MSH Image Optimizer Admin JavaScript
 * Handles the admin interface for image optimization
 */

(function($) {
    'use strict';
    
    const DEFAULT_CONTEXT_CHOICES = [
        { value: '', label: 'Auto-detect (default)' },
        { value: 'clinical', label: 'Clinical / Treatment' },
        { value: 'team', label: 'Team Member' },
        { value: 'testimonial', label: 'Patient Testimonial' },
        { value: 'service-icon', label: 'Service Icon' },
        { value: 'facility', label: 'Facility / Clinic' },
        { value: 'equipment', label: 'Equipment' },
        { value: 'business', label: 'Business / General' }
    ];

    const CONTEXT_CHOICES = (window.mshImageOptimizer && Array.isArray(window.mshImageOptimizer.contextChoices))
        ? window.mshImageOptimizer.contextChoices
        : DEFAULT_CONTEXT_CHOICES;

    const CONTEXT_CHOICE_MAP = (function buildContextChoiceMap() {
        if (window.mshImageOptimizer && window.mshImageOptimizer.contextChoiceMap) {
            return window.mshImageOptimizer.contextChoiceMap;
        }

        return CONTEXT_CHOICES.reduce((acc, option) => {
            acc[option.value] = option.label;
            return acc;
        }, {});
    }());

    let imageData = [];
    let isProcessing = false;
    let currentBatch = [];
    
    $(document).ready(function() {
        initializeOptimizer();
        bindEvents();
        // loadProgress(); // Disabled - was causing page hangs
        checkWebPSupport();
    });
    
    function initializeOptimizer() {
        console.log('MSH Image Optimizer JavaScript loaded successfully');
        // Add a results container if it doesn't exist
        if ($('#results-container').length === 0) {
            $('.msh-optimizer-dashboard').append('<div id="results-container" style="margin-top: 20px;"></div>');
        }
    }
    
    function bindEvents() {
        // Test if button exists and jQuery is working
        console.log('Button found:', $('#analyze-images').length);

        // Build usage index button
        $('#build-usage-index').on('click', function(e) {
            e.preventDefault();
            console.log('Build usage index clicked');
            buildUsageIndex();
        });

        // Analysis button
        $('#analyze-images').on('click', function(e) {
            e.preventDefault();
            console.log('Click handler called');
            try {
                analyzeImages();
            } catch (error) {
                console.error('Error calling analyzeImages:', error);
                alert('Error: ' + error.message);
            }
        });
        
        // Optimization buttons
        $('#optimize-high-priority').on('click', function() {
            optimizeByPriority(15, 100);
        });
        
        $('#optimize-medium-priority').on('click', function() {
            optimizeByPriority(10, 14);
        });
        
        $('#optimize-all').on('click', function() {
            optimizeByPriority(1, 100);
        });
        
        // Apply filename suggestions - DISABLED: Now handled by image-optimizer-modern.js with selection logic
        // $('#apply-filename-suggestions').on('click', function() {
        //     if (confirm('This will rename files to their suggested SEO-friendly names. This action cannot be undone. Continue?')) {
        //         applyFilenameSuggestions();
        //     }
        // });
        console.log('MSH: Old batch handler disabled, using modern selection-aware handler');
        
        // Save individual filename suggestions (delegated event for dynamic content)
        $(document).on('click', '.save-filename', function() {
            const imageId = $(this).data('id');
            const newSuggestion = $(`.filename-suggestion[data-id="${imageId}"]`).val();
            saveFilenameSuggestion(imageId, newSuggestion);
        });
        
        // Keep current filename (remove suggestion)
        $(document).on('click', '.keep-current', function() {
            const imageId = $(this).data('id');
            if (confirm('Remove filename suggestion and keep the current filename?')) {
                keepCurrentFilename(imageId);
            }
        });
        
        // Preview meta text
        $(document).on('click', '.preview-meta', function() {
            const imageId = $(this).data('id');
            previewMetaText(imageId);
        });
        
        // Select all checkbox
        $('#select-all').on('change', function() {
            const isChecked = $(this).is(':checked');
            $('.image-checkbox:visible').prop('checked', isChecked);
        });
        
        // Filter checkboxes
        $('.filters-section input[type="checkbox"]').on('change', filterResults);
        
        // Log controls
        $('#clear-log').on('click', function() {
            $('#optimization-log').empty();
            updateLog('Log cleared.');
        });
        
        // Cancel processing
        $('#cancel-processing').on('click', function() {
            isProcessing = false;
            hideProcessingModal();
            updateLog('Processing cancelled by user.');
        });
        
        // Reset optimization flags
        $('#reset-optimization').on('click', function() {
            if (confirm('This will reset all optimization flags, allowing images to be re-optimized with improved metadata preservation. Continue?')) {
                resetOptimization();
            }
        });
        
        // Remove old cleanup-duplicates button handler (button removed)
        
        // Test cleanup connection
        $('#test-cleanup').on('click', function() {
            testCleanupConnection();
        });
        
        // Quick duplicate scan
        $('#quick-duplicate-scan').on('click', function() {
            startQuickDuplicateScan();
        });
        
        // Deep library scan
        $('#full-library-scan').on('click', function() {
            if (confirm('Deep Library Scan will analyze all 748 images for comprehensive duplicate detection. This takes 30-60 seconds but finds all duplicates that Quick Scan misses. Continue?')) {
                startDeepLibraryScan();
            }
        });

        $(document).on('click', '.edit-context-inline', function(e) {
            e.preventDefault();
            const $summary = $(this).closest('.context-summary');
            openContextEditor($summary);
        });

        $(document).on('click', '.inline-context-cancel', function(e) {
            e.preventDefault();
            const $summary = $(this).closest('.context-summary');
            closeContextEditor($summary);
        });

        $(document).on('click', '.inline-context-save', function(e) {
            e.preventDefault();
            const $button = $(this);
            saveContextSelection($button);
        });
    }
    
    function analyzeImages() {
        console.log('analyzeImages() called');
        
        if (isProcessing) {
            console.log('Already processing, skipping');
            return;
        }
        
        isProcessing = true;
        console.log('Starting AJAX request...');

        const $analyzeButton = $('#analyze-images');
        const originalButtonText = $analyzeButton.data('original-text') || $analyzeButton.text();
        $analyzeButton
            .data('original-text', originalButtonText)
            .prop('disabled', true)
            .addClass('is-loading')
            .text('Analyzing...');

        showProcessingModal(
            'Analyzing Published Images',
            'Scanning published posts and pages for image usage…',
            { showProgress: false }
        );

        updateLog('Starting image analysis...');
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            timeout: 60000, // 60 second timeout
            data: {
                action: 'msh_analyze_images',
                nonce: mshImageOptimizer.nonce
            },
            success: function(response) {
                if (response.success) {
                    let rawImages = [];

                    // Handle new response format with debug info
                    if (response.data.images) {
                        rawImages = Array.isArray(response.data.images) ? response.data.images : [];
                        const debug = response.data.debug;

                        if (debug) {
                            updateLog(`Found ${debug.published_images_found} published images out of ${debug.total_images_in_db} total images in media library.`);
                        }

                    } else if (Array.isArray(response.data)) {
                        // Fallback for old format
                        rawImages = response.data;
                    }

                    const filteredImages = rawImages.filter((img) => {
                        const status = (img.optimization_status || '').toLowerCase();
                        
                        // Console warning for missing optimization_status (WordPress uses uppercase ID)
                        if (!img.optimization_status) {
                            const imageId = img.ID || img.id || 'unknown';
                            console.warn('MSH Optimizer: Missing optimization_status for image', imageId);
                        }
                        
                        return status !== 'optimized';
                    });
                    const optimizedCount = rawImages.length - filteredImages.length;

                    imageData = filteredImages;

                    $('#modal-status').text('Analysis complete. Preparing results...');

                    displayResults(imageData);
                    enableOptimizationButtons(imageData.length > 0);
                    updateLog(`Analysis complete. ${imageData.length} image(s) need optimization.`);

                    if (optimizedCount > 0) {
                        updateLog(`${optimizedCount} image(s) already optimized and removed from the queue.`);
                    }

                    if (imageData.length === 0) {
                        updateLog('All published images are already optimized.');
                    }

                    // Update button counts
                    const highPriority = imageData.filter(img => img.priority >= 15).length;
                    const mediumPriority = imageData.filter(img => img.priority >= 10 && img.priority < 15).length;

                    $('#optimize-high-priority').text(`Optimize High Priority (${highPriority})`);
                    $('#optimize-medium-priority').text(`Optimize Medium Priority (${mediumPriority})`);

                    // Refresh progress stats after analysis
                    loadProgress();

                } else {
                    updateLog('Error: Failed to analyze images.');
                }
            },
            error: function() {
                updateLog('Error: AJAX request failed during analysis.');
                $('#modal-status').text('Analysis request failed. Please try again.');
            },
            complete: function() {
                isProcessing = false;
                const $button = $('#analyze-images');
                const restoreText = $button.data('original-text') || 'Analyze Published Images';
                $button
                    .prop('disabled', false)
                    .removeClass('is-loading')
                    .text(restoreText);
                setTimeout(hideProcessingModal, 500);
            }
        });
    }
    
    function displayResults(images) {
        $('.msh-results-section').show();
        const tbody = $('#results-tbody');
        tbody.empty();

        const pendingImages = (images || []).filter((img) => {
            const status = (img.optimization_status || '').toLowerCase();
            
            // Console warning for missing optimization_status (display filtering, WordPress uses uppercase ID)
            if (!img.optimization_status) {
                const imageId = img.ID || img.id || 'unknown';
                console.warn('MSH Optimizer: Missing optimization_status for image', imageId, 'in display filtering');
            }
            
            return status !== 'optimized';
        });

        if (pendingImages.length === 0) {
            tbody.append('<tr><td colspan="8" class="no-results">All published images are fully optimized. Great job!</td></tr>');
            return;
        }

        pendingImages.forEach(function(image) {
            const row = createResultRow(image);
            tbody.append(row);
        });
        
        filterResults(); // Apply current filters
    }
    
    function getStatusInfo(image) {
        const statusKey = (image.optimization_status || (image.optimized_date ? 'optimized' : 'ready_for_optimization')).toLowerCase();
        const statusMap = {
            optimized: { key: 'optimized', className: 'status-optimized', label: 'Optimized' },
            ready_for_optimization: { key: 'ready_for_optimization', className: 'status-pending', label: 'Needs Optimization' },
            metadata_missing: { key: 'metadata_missing', className: 'status-warning', label: 'Metadata Missing' },
            metadata_current: { key: 'metadata_current', className: 'status-info', label: 'Metadata Updated' },
            needs_recompression: { key: 'needs_recompression', className: 'status-warning', label: 'Source Updated' },
            webp_missing: { key: 'webp_missing', className: 'status-warning', label: 'WebP Missing' },
            needs_attention: { key: 'needs_attention', className: 'status-error', label: 'Needs Attention' }
        };

        return statusMap[statusKey] || statusMap.ready_for_optimization;
    }

    function createResultRow(image) {
        const priorityClass = getPriorityClass(image.priority);
        const issues = getImageIssues(image);
        const thumbnail = image.file_path ? 
            `<img src="${getImageUrl(image.file_path)}" class="thumbnail-preview" alt="${image.post_title}">` : 
            '<div class="thumbnail-preview" style="background:#f0f0f1;"></div>';
        
        const savings = image.webp_savings_estimate ? 
            `${image.webp_savings_estimate.estimated_savings_percent}% (WebP)` : 'N/A';
        const statusInfo = getStatusInfo(image);
        const isOptimized = statusInfo.key === 'optimized';
        const contextMetaPreview = renderContextMetaPreview(image);
        const contextSummary = renderContextSummary(image);
        const attachmentId = image.ID || image.id || '';
        const manualContext = image.manual_context || '';
        const autoContext = image.auto_context || '';

        // Suggested filename - make it editable with keep current option
        const suggestedFilename = image.suggested_filename || '';
        const currentFilename = image.file_path ? image.file_path.split('/').pop() : 'Unknown';
        const filenameDisplay = suggestedFilename ? 
            `<div>
                <strong>Current:</strong> ${currentFilename}<br>
                <strong>Suggested:</strong><br>
                <input type="text" class="filename-suggestion" data-id="${image.ID}" 
                       value="${suggestedFilename.split('/').pop()}" 
                       style="width: 100%; font-size: 11px; margin-top: 2px;" 
                       placeholder="Edit suggestion...">
                <div style="margin-top: 3px;">
                    <button class="button button-small save-filename" data-id="${image.ID}" 
                            style="font-size: 10px; margin-right: 5px;">Save</button>
                    <button class="button button-small keep-current" data-id="${image.ID}" 
                            style="font-size: 10px; background: #faf9f6; color: #35332f; border: 1px solid #35332f;">Keep Current</button>
                </div>
             </div>` : 
            currentFilename;
        
        return `
            <tr class="result-row ${priorityClass}" data-attachment-id="${attachmentId}" data-manual-context="${manualContext}" data-auto-context="${autoContext}" data-priority="${image.priority}" data-issues="${issues.map(i => i.type).join(',')}" data-optimized="${isOptimized}" data-status="${statusInfo.key}">
                <td><input type="checkbox" class="image-checkbox" data-id="${image.ID}" value="${image.ID}"></td>
                <td>
                    ${thumbnail}
                    <div style="margin-left: 50px;">
                        <strong>${image.post_title || 'Untitled'}</strong><br>
                        <small>File: ${image.file_path || 'No file path'}</small><br>
                        <small>Used in: ${image.used_in || 'Not used'}</small><br>
                        <small class="context-summary">${contextSummary}</small>
                        ${contextMetaPreview}
                    </div>
                </td>
                <td style="min-width: 200px;">${filenameDisplay}</td>
                <td><span class="${priorityClass}">${image.priority}</span></td>
                <td>${issues.map(issue => `<span class="issue-tag ${issue.type}">${issue.text}</span>`).join(' ')}</td>
                <td>
                    ${image.current_size_mb || '0'} MB<br>
                    <small>${image.current_dimensions || 'Unknown'}</small>
                </td>
                <td>${image.used_in || 'Not used'}</td>
                <td>
                    <button class="button button-small optimize-single" data-id="${image.ID}">Optimize</button><br>
                    <button class="button button-small preview-meta" data-id="${image.ID}" 
                            style="margin-top: 3px; font-size: 10px; background: #faf9f6; color: #35332f; border: 1px solid #35332f;">Preview Meta</button>
                    <div style="margin-top: 6px;"><span class="${statusInfo.className}">${statusInfo.label}</span></div>
                </td>
            </tr>
        `;
    }
    
    function getPriorityClass(priority) {
        if (priority >= 15) return 'priority-high';
        if (priority >= 10) return 'priority-medium';
        return 'priority-low';
    }
    
    function getImageIssues(image) {
        const issues = [];
        
        if (!image.alt_text || image.alt_text.trim() === '') {
            issues.push({type: 'alt', text: 'No ALT'});
        }
        
        if (!image.webp_exists) {
            issues.push({type: 'webp', text: 'No WebP'});
        }
        
        if (image.optimization_potential && image.optimization_potential.needs_resize) {
            issues.push({type: 'size', text: 'Oversized'});
        }
        
        return issues;
    }

    function renderContextMetaPreview(image) {
        const context = image.context_details || {};
        const meta = image.generated_meta || {};
        const hasContext = Object.keys(context).length > 0;
        const hasMeta = Object.keys(meta).length > 0;

        if (!hasContext && !hasMeta) {
            return '';
        }

        const contextSource = image.context_source || (context.manual || context.source === 'manual' ? 'manual' : (context.type ? 'auto' : 'pending'));
        const manualContext = image.manual_context || '';
        const autoContext = image.auto_context || '';
        const activeLabel = image.context_active_label || (context.type ? formatLabel(context.type) : '');
        const autoLabel = image.context_auto_label || (autoContext ? formatLabel(autoContext) : '');

        const infoParts = [];

        if (contextSource === 'manual') {
            infoParts.push('<div class="context-source-line"><strong>Context Source:</strong> Manual override</div>');
            if (activeLabel) {
                infoParts.push(`<div><strong>Override Type:</strong> ${escapeHtml(activeLabel)}</div>`);
            }
            if (autoLabel && manualContext !== autoContext) {
                infoParts.push(`<div><strong>Auto Suggestion:</strong> ${escapeHtml(autoLabel)}</div>`);
            }
        } else if (contextSource === 'auto') {
            infoParts.push('<div class="context-source-line"><strong>Context Source:</strong> Auto-detected</div>');
            if (activeLabel) {
                infoParts.push(`<div><strong>Detected Type:</strong> ${escapeHtml(activeLabel)}</div>`);
            }
        }

        if (context.service) {
            infoParts.push(`<div><strong>Service Focus:</strong> ${escapeHtml(formatLabel(context.service))}</div>`);
        }

        if (context.asset) {
            infoParts.push(`<div><strong>Asset Type:</strong> ${escapeHtml(formatLabel(context.asset))}</div>`);
        }

        if (context.product_type) {
            infoParts.push(`<div><strong>Product Indicator:</strong> ${escapeHtml(formatLabel(context.product_type))}</div>`);
        }

        if (context.icon_type) {
            infoParts.push(`<div><strong>Icon Category:</strong> ${escapeHtml(formatLabel(context.icon_type))}</div>`);
        }

        if (context.staff_name) {
            infoParts.push(`<div><strong>Team Member:</strong> ${escapeHtml(context.staff_name)}</div>`);
        }

        if (context.subject_name) {
            infoParts.push(`<div><strong>Testimonial:</strong> ${escapeHtml(context.subject_name)}</div>`);
        }

        if (context.page_title) {
            infoParts.push(`<div><strong>Appears On:</strong> ${escapeHtml(context.page_title)}</div>`);
        }

        const metaFields = [
            { key: 'title', label: 'Title' },
            { key: 'alt_text', label: 'ALT' },
            { key: 'caption', label: 'Caption' },
            { key: 'description', label: 'Description' }
        ];

        metaFields.forEach(({ key, label }) => {
            const value = meta[key];
            if (value) {
                infoParts.push(`<div><strong>${label}:</strong> ${escapeHtml(value)}</div>`);
            }
        });

        if (infoParts.length === 0) {
            return '';
        }

        return `
            <details class="context-meta-details">
                <summary>Context &amp; Meta Preview</summary>
                <div class="context-meta-content">
                    ${infoParts.join('')}
                </div>
            </details>
        `;
    }

    function renderContextSummary(image) {
        if (!image || typeof image !== 'object') {
            return 'Context: Unknown';
        }

        const context = image.context_details || {};
        const manualContext = image.manual_context || '';
        const autoContext = image.auto_context || '';
        const sourceRaw = image.context_source || (context.manual || context.source === 'manual' ? 'manual' : (context.type ? 'auto' : 'pending'));
        const source = sourceRaw === 'manual' || sourceRaw === 'auto' ? sourceRaw : 'pending';
        const activeLabel = image.context_active_label || (context.type ? formatLabel(context.type) : (manualContext ? formatLabel(manualContext) : ''));
        const autoLabel = image.context_auto_label || (autoContext ? formatLabel(autoContext) : '');

        const sourceLabels = {
            manual: 'Manual override',
            auto: 'Auto-detected',
            pending: 'Context pending'
        };

        const chips = [];
        const sourceLabel = sourceLabels[source] || sourceLabels.pending;
        const sourceClass = source === 'manual' ? 'manual' : source === 'auto' ? 'auto' : 'pending';

        chips.push(`<span class="msh-context-chip ${sourceClass}">${escapeHtml(sourceLabel)}</span>`);

        if (activeLabel) {
            chips.push(`<span class="msh-context-chip context">${escapeHtml(activeLabel)}</span>`);
        }

        if (source === 'manual' && autoLabel && manualContext !== autoContext) {
            chips.push(`<span class="msh-context-chip auto-note">${escapeHtml(`Auto: ${autoLabel}`)}</span>`);
        }

        const highlightParts = [];

        if (context.service) {
            highlightParts.push(`Service: ${escapeHtml(formatLabel(context.service))}`);
        }

        if (context.asset) {
            highlightParts.push(`Asset: ${escapeHtml(formatLabel(context.asset))}`);
        }

        if (context.product_type) {
            highlightParts.push(`Product: ${escapeHtml(formatLabel(context.product_type))}`);
        }

        if (context.icon_type) {
            highlightParts.push(`Icon: ${escapeHtml(formatLabel(context.icon_type))}`);
        }

        if (context.page_title) {
            highlightParts.push(`Page: ${escapeHtml(context.page_title)}`);
        }

        const attachmentId = image.ID || image.id || '';
        const chipsHtml = chips.join(' ');
        const highlightsHtml = highlightParts.length ? `<div class="msh-context-highlights">${highlightParts.join(' • ')}</div>` : '';
        const editButton = `<button type="button" class="button-link edit-context-inline" aria-label="Edit context" title="Edit context"><span class="dashicons dashicons-edit"></span></button>`;
        const selectedValue = manualContext || '';
        const optionsHtml = CONTEXT_CHOICES.map(choice => {
            const label = choice.label || CONTEXT_CHOICE_MAP[choice.value] || formatLabel(choice.value);
            const selected = choice.value === selectedValue ? ' selected' : '';
            return `<option value="${choice.value}"${selected}>${escapeHtml(label)}</option>`;
        }).join('');

        const editorHtml = `
            <div class="context-inline-editor hidden">
                <label class="screen-reader-text" for="context-inline-${attachmentId}">Image Context</label>
                <select id="context-inline-${attachmentId}" class="context-inline-select">
                    ${optionsHtml}
                </select>
                <button type="button" class="button button-small button-primary inline-context-save">Save</button>
                <button type="button" class="button button-small inline-context-cancel">Cancel</button>
            </div>
        `;

        const chipMarkup = chipsHtml || '<span class="msh-context-chip pending">Context Pending</span>';

        return `
            <div class="context-summary" data-attachment-id="${attachmentId}">
                <div class="context-summary-controls">
                    ${chipMarkup}
                    ${editButton}
                </div>
                ${highlightsHtml}
                ${editorHtml}
            </div>
        `;
    }

    function findImageById(attachmentId) {
        const numericId = parseInt(attachmentId, 10);
        if (Number.isNaN(numericId)) {
            return null;
        }

        return imageData.find((item) => parseInt(item.ID || item.id, 10) === numericId) || null;
    }

    function closeContextEditor($summary) {
        if (!$summary || !$summary.length) {
            return;
        }

        $summary.removeClass('is-editing');
        const $editor = $summary.find('.context-inline-editor');
        $editor.addClass('hidden');

        const $saveButton = $summary.find('.inline-context-save');
        const originalText = $saveButton.data('original-text');
        if (originalText) {
            $saveButton.text(originalText);
        }
        $saveButton.prop('disabled', false);

        $summary.removeData('existingSuggestion existingFilePath existingFileSize');
    }

    function closeAllContextEditors() {
        $('.context-summary.is-editing').each(function() {
            closeContextEditor($(this));
        });
    }

    function openContextEditor($summary) {
        if (!$summary || !$summary.length) {
            return;
        }

        closeAllContextEditors();

        const attachmentId = $summary.data('attachmentId');
        const record = findImageById(attachmentId);
        const manualValue = record && record.manual_context ? record.manual_context : '';
        const existingSuggestion = record && record.suggested_filename ? record.suggested_filename : '';
        const existingFilePath = record && record.file_path ? record.file_path : '';
        const existingFileSize = record && record.current_size_mb ? record.current_size_mb : '';

        const $select = $summary.find('.context-inline-select');
        $select.val(manualValue);

        $summary.addClass('is-editing');
        $summary.find('.context-inline-editor').removeClass('hidden');

        $summary.data('existingSuggestion', existingSuggestion);
        $summary.data('existingFilePath', existingFilePath);
        $summary.data('existingFileSize', existingFileSize);

        setTimeout(() => {
            $select.trigger('focus');
        }, 0);
    }

    function saveContextSelection($button) {
        if (!$button || !$button.length) {
            return;
        }

        const $summary = $button.closest('.context-summary');
        if (!$summary.length) {
            return;
        }

        const attachmentId = parseInt($summary.data('attachmentId'), 10);
        if (!attachmentId) {
            alert('Invalid attachment reference.');
            closeContextEditor($summary);
            return;
        }

        const $row = $summary.closest('tr');
        const $select = $summary.find('.context-inline-select');
        const newContext = ($select.val() || '').toString();
        const wasChecked = $row.find('.image-checkbox').is(':checked');
        const originalStatus = ($row.data('status') || '').toString();
        const originalPriority = parseInt($row.data('priority'), 10);
        const existingSuggestion = $summary.data('existingSuggestion') || '';
        const existingFilePath = $summary.data('existingFilePath') || '';
        const existingFileSize = $summary.data('existingFileSize') || '';

        const originalText = $button.data('original-text') || $button.text();
        $button.data('original-text', originalText);
        $button.text('Saving...').prop('disabled', true);

        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'msh_update_context',
                attachment_id: attachmentId,
                context: newContext,
                nonce: mshImageOptimizer.nonce
            }
        }).done(function(response) {
            if (response && response.success && response.data && response.data.image) {
                const updatedImage = response.data.image;

                if (existingSuggestion) {
                    updatedImage.suggested_filename = existingSuggestion;
                }
                if (existingFilePath) {
                    updatedImage.file_path = existingFilePath;
                }
                if (existingFileSize && !updatedImage.current_size_mb) {
                    updatedImage.current_size_mb = existingFileSize;
                }

                if (originalStatus && originalStatus !== 'optimized') {
                    updatedImage.optimization_status = originalStatus;
                }

                if (!Number.isFinite(parseInt(updatedImage.priority, 10)) && Number.isInteger(originalPriority)) {
                    updatedImage.priority = originalPriority;
                }

                const index = imageData.findIndex((img) => parseInt(img.ID || img.id, 10) === attachmentId);
                if (index > -1) {
                    imageData[index] = updatedImage;
                } else {
                    imageData.push(updatedImage);
                }

                const $newRow = $(createResultRow(updatedImage));
                if (wasChecked) {
                    $newRow.find('.image-checkbox').prop('checked', true);
                }

                $newRow.addClass('context-updated');
                setTimeout(() => $newRow.removeClass('context-updated'), 2000);

                $row.replaceWith($newRow);
                closeAllContextEditors();
                updateLog(`Context updated for image #${attachmentId}.`);
                filterResults();
            } else {
                const errorMessage = response && response.data ? response.data : 'Unexpected response.';
                alert(`Error updating context: ${errorMessage}`);
                $button.text(originalText).prop('disabled', false);
            }
        }).fail(function() {
            alert('Network error while saving context. Please try again.');
            $button.text(originalText).prop('disabled', false);
        });
    }

    function escapeHtml(value) {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function capitalizeFirst(value) {
        if (!value) {
            return '';
        }

        const str = String(value);
        if (str === str.toUpperCase()) {
            return str;
        }

        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function formatLabel(value) {
        if (!value) {
            return '';
        }

        if (CONTEXT_CHOICE_MAP && CONTEXT_CHOICE_MAP[value]) {
            return CONTEXT_CHOICE_MAP[value];
        }

        return String(value)
            .split(/[-_]/)
            .filter(Boolean)
            .map(part => capitalizeFirst(part.toLowerCase()))
            .join(' ');
    }

    function getImageUrl(filePath) {
        // Construct full URL from file path
        const uploadUrl = window.location.origin + '/wp-content/uploads/';
        return uploadUrl + filePath;
    }
    
    function filterResults() {
        const filters = {
            highPriority: $('#filter-high-priority').is(':checked'),
            mediumPriority: $('#filter-medium-priority').is(':checked'),
            lowPriority: $('#filter-low-priority').is(':checked'),
            missingAlt: $('#filter-missing-alt').is(':checked'),
            noWebp: $('#filter-no-webp').is(':checked'),
            unoptimizedOnly: $('#filter-unoptimized-only').is(':checked')
        };
        
        $('.result-row').each(function() {
            const $row = $(this);
            const priority = parseInt($row.data('priority'));
            const issues = ($row.data('issues') || '').split(',');
            
            let show = false;
            
            // Priority filters
            if (filters.highPriority && priority >= 15) show = true;
            if (filters.mediumPriority && priority >= 10 && priority < 15) show = true;
            if (filters.lowPriority && priority < 10) show = true;
            
            // Issue filters
            if (filters.missingAlt && issues.includes('alt')) show = true;
            if (filters.noWebp && issues.includes('webp')) show = true;

            // Hide optimized images filter
            if (filters.unoptimizedOnly) {
                const isOptimized = $row.data('optimized') === true || $row.data('optimized') === 'true' || $row.data('status') === 'optimized';

                // Debug logging (remove later)
                if ($row.data('attachment-id') && parseInt($row.data('attachment-id')) < 220) {
                    console.log('Filter Debug - ID:', $row.data('attachment-id'), 'optimized:', $row.data('optimized'), 'status:', $row.data('status'), 'isOptimized:', isOptimized);
                }

                if (isOptimized) {
                    show = false;
                }
            }

            // If no filters are checked, show all
            if (!filters.highPriority && !filters.mediumPriority && !filters.lowPriority &&
                !filters.missingAlt && !filters.noWebp) {
                show = true;
            }
            
            $row.toggle(show);
        });
    }
    
    function optimizeByPriority(minPriority, maxPriority) {
        if (isProcessing) return;
        
        const imagesToOptimize = imageData.filter(function(image) {
            const status = (image.optimization_status || '').toLowerCase();
            return image.priority >= minPriority && 
                   image.priority <= maxPriority && 
                   status !== 'optimized';
        });
        
        if (imagesToOptimize.length === 0) {
            updateLog('No images found matching the selected priority range.');
            return;
        }
        
        optimizeImages(imagesToOptimize);
    }
    
    function optimizeImages(images) {
        if (isProcessing) return;
        
        isProcessing = true;
        currentBatch = images.slice(); // Copy array
        
        const priorityText = getCurrentPriorityText(images);
        showProcessingModal(`Optimizing Images`, `Starting optimization of ${images.length} images...`);
        
        updateLog(`Starting optimization of ${images.length} images (${priorityText})`);
        
        processImageBatch(0);
    }
    
    function getCurrentPriorityText(images) {
        const priorities = images.map(img => img.priority);
        const minPriority = Math.min(...priorities);
        const maxPriority = Math.max(...priorities);
        
        if (maxPriority >= 15) return 'High Priority';
        if (maxPriority >= 10) return 'Medium Priority';
        return 'Low Priority';
    }
    
    function processImageBatch(startIndex) {
        if (!isProcessing || startIndex >= currentBatch.length) {
            // Processing complete
            isProcessing = false;
            hideProcessingModal();
            updateLog('Batch optimization complete!');
            loadProgress(); // Refresh progress stats
            
            // DISABLED: Auto-refresh causing analysis loop
            // setTimeout(analyzeImages, 1000);
            console.log('MSH: Auto-analysis disabled to prevent loop');
            return;
        }
        
        const batchSize = 5; // Process 5 images at a time
        const endIndex = Math.min(startIndex + batchSize, currentBatch.length);
        const batch = currentBatch.slice(startIndex, endIndex);
        
        const progress = Math.round((startIndex / currentBatch.length) * 100);
        updateProcessingModal(progress, `Processing images ${startIndex + 1}-${endIndex} of ${currentBatch.length}...`);
        
        updateLog(`Processing batch: images ${startIndex + 1}-${endIndex}...`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_optimize_batch',
                nonce: mshImageOptimizer.nonce,
                image_ids: batch.map(img => img.ID)
            },
            success: function(response) {
                if (response.success) {
                    response.data.forEach(function(result) {
                        const actions = result.result.actions || [];
                        updateLog(`Image ${result.id}: ${actions.join(', ')}`);
                    });
                    
                    // Process next batch
                    setTimeout(function() {
                        processImageBatch(endIndex);
                    }, 500); // Small delay to prevent overwhelming server
                    
                } else {
                    updateLog('Error processing batch.');
                    isProcessing = false;
                    hideProcessingModal();
                }
            },
            error: function() {
                updateLog('AJAX error during batch processing.');
                isProcessing = false;
                hideProcessingModal();
            }
        });
    }
    
    function loadProgress() {
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_get_progress',
                nonce: mshImageOptimizer.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateProgressStats(response.data);
                }
            }
        });
    }
    
    function updateProgressStats(progress) {
        const total = parseInt(progress.total, 10) || 0;
        const optimized = parseInt(progress.optimized, 10) || 0;
        const remaining = Number.isFinite(progress.remaining) ? Math.max(0, parseInt(progress.remaining, 10)) : Math.max(0, total - optimized);
        const percentageRaw = Number(progress.percentage);
        const percentage = total > 0 && Number.isFinite(percentageRaw)
            ? Math.min(100, Math.max(0, Math.round(percentageRaw)))
            : (total === 0 ? 0 : Math.min(100, Math.max(0, Math.round((optimized / total) * 100))));

        $('#total-images').text(total);
        $('#optimized-images').text(optimized);
        $('#remaining-images').text(remaining);
        $('#progress-percentage').text(percentage + '%');

        const $progressFill = $('#progress-fill');
        $progressFill.css('width', percentage + '%');
        $progressFill.toggleClass('completed', percentage >= 100 && total > 0);

        $('#progress-percent').text(percentage + '%');

        let statusMessage = 'Waiting for analysis…';
        if (total > 0) {
            statusMessage = percentage >= 100
                ? 'All published images are optimized. Great job!'
                : `${optimized} of ${total} images optimized • ${remaining} remaining`;
        }

        $('#progress-status').text(statusMessage);
    }
    
    function enableOptimizationButtons(hasImages = true) {
        const shouldDisable = !hasImages;
        $('#optimize-high-priority, #optimize-medium-priority, #optimize-all, #apply-filename-suggestions')
            .prop('disabled', shouldDisable);
    }
    
    function showProcessingModal(title, status, options = {}) {
        const settings = Object.assign({ showProgress: true, progress: 0 }, options);

        $('#modal-title').text(title);
        $('#modal-status').text(status);

        if (settings.showProgress) {
            const initialProgress = Number.isFinite(Number(settings.progress))
                ? Math.max(0, Math.min(100, Number(settings.progress)))
                : 0;
            $('#modal-progress').show();
            $('#modal-progress-fill').css('width', initialProgress + '%');
            $('#modal-progress-text').text(initialProgress + '%');
        } else {
            $('#modal-progress').hide();
            $('#modal-progress-fill').css('width', '0%');
            $('#modal-progress-text').text('');
        }

        $('#processing-modal').show();
    }
    
    function hideProcessingModal() {
        $('#modal-progress').show();
        $('#modal-progress-fill').css('width', '0%');
        $('#modal-progress-text').text('0%');
        $('#processing-modal').hide();
    }
    
    function updateProcessingModal(progress, status) {
        $('#modal-progress-fill').css('width', progress + '%');
        $('#modal-progress-text').text(progress + '%');
        $('#modal-status').text(status);
    }
    
    function resetOptimization() {
        updateLog('Resetting optimization flags...');
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_reset_optimization',
                nonce: mshImageOptimizer.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateLog(`${response.data.message}`);
                    loadProgress(); // Refresh progress stats
                    
                    // Clear image data to force re-analysis
                    imageData = [];
                    $('#results-tbody').html('<tr><td colspan="8" class="no-results">Click "Analyze Published Images" to re-scan with improved metadata preservation.</td></tr>');
                } else {
                    updateLog('Error resetting optimization flags.');
                }
            },
            error: function() {
                updateLog('AJAX error during reset.');
            }
        });
    }
    
    function analyzeDuplicates() {
        if (isProcessing) return;
        
        isProcessing = true;
        showProcessingModal('Analyzing Duplicates', 'Scanning media library for duplicate images...');
        updateLog('Starting duplicate image analysis...');
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            timeout: 30000, // 30 second timeout (optimized queries)
            data: {
                action: 'msh_analyze_duplicates',
                nonce: mshImageOptimizer.cleanup_nonce
            },
            success: function(response) {
                if (response.success) {
                    displayDuplicateResults(response.data);
                    updateLog(`Found ${response.data.total_groups} groups with ${response.data.total_duplicates} potential duplicates.`);
                    updateLog(`Note: Analyzed recent 50 images for duplicates (optimized for speed). Most duplicates are in recent uploads.`);
                    if (response.data.debug_info) {
                        updateLog(`Debug: Memory used: ${Math.round(response.data.debug_info.memory_usage / 1024 / 1024)}MB`);
                    }
                } else {
                    updateLog('Error: Failed to analyze duplicates.');
                    if (response.data && response.data.message) {
                        updateLog('Server error: ' + response.data.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                updateLog('Error: AJAX request failed during duplicate analysis.');
                updateLog('Debug: ' + status + ' - ' + error);
                updateLog('Response: ' + (xhr.responseText || 'No response'));
                console.log('AJAX Error:', xhr, status, error);
            },
            complete: function() {
                isProcessing = false;
                hideProcessingModal();
            }
        });
    }
    
    function displayDuplicateResults(data) {
        const tbody = $('#results-tbody');
        tbody.empty();
        
        if (data.total_groups === 0) {
            tbody.append('<tr><td colspan="8" class="no-results">No duplicate image groups found. Your media library is clean!</td></tr>');
            return;
        }
        
        // Add header for duplicate results
        tbody.append(`
            <tr style="background: #f0f0f1; font-weight: bold;">
                <td colspan="8">
                    <h3 style="margin: 10px 0;">Duplicate Image Groups Found: ${data.total_groups} groups, ${data.total_duplicates} potential duplicates</h3>
                    <button id="check-usage-status" class="button button-secondary" style="margin-right: 10px; background: #3498db;">Check Usage Status</button>
                    <button id="cleanup-safe-duplicates" class="button button-primary" style="margin-right: 10px;">Clean Up Safe Duplicates</button>
                    <br><small style="color: #666; margin-top: 10px; display: block;">
                        <strong>IMPORTANT:</strong> Click "Check Usage Status" first to see which files are actually used on your website.
                    </small>
                </td>
            </tr>
        `);
        
        Object.keys(data.groups).forEach(function(groupName) {
            const group = data.groups[groupName];
            
            // Group header
            tbody.append(`
                <tr style="background: #e8f4f8;">
                    <td colspan="8">
                        <strong>Group: "${groupName}"</strong> 
                        (${group.total_count} images, ${group.published_count} in use, ${group.cleanup_potential} can be removed)
                        <br><small>Sizes: ${group.sizes_available.join(', ')}</small>
                    </td>
                </tr>
            `);
            
            // Individual images in group
            group.images.forEach(function(image) {
                const isRecommendedKeep = group.recommended_keep && group.recommended_keep.ID === image.ID;
                const usageText = image.usage.length > 0 ? 
                    image.usage.map(u => `${u.title} (${u.type})`).join(', ') : 
                    'Not used';
                    
                const rowStyle = isRecommendedKeep ? 
                    'background: #d4edda; border-left: 4px solid #28a745;' : 
                    (image.is_published ? 'background: #fff3cd;' : 'background: #f8d7da;');
                
                tbody.append(`
                    <tr style="${rowStyle}">
                        <td>
                            <input type="checkbox" class="duplicate-checkbox" value="${image.ID}" 
                                   ${!isRecommendedKeep && !image.is_published ? 'checked' : ''}>
                        </td>
                        <td>
                            <strong>${image.post_title || 'Untitled'}</strong><br>
                            <small>${image.file_path}</small><br>
                            <small>ID: ${image.ID}, Score: ${image.keep_score}</small>
                            ${isRecommendedKeep ? '<br><span style="color: green; font-weight: bold;">RECOMMENDED KEEP</span>' : ''}
                        </td>
                        <td>${isRecommendedKeep ? 'KEEP' : (image.is_published ? 'IN USE' : 'DUPLICATE')}</td>
                        <td>-</td>
                        <td>-</td>
                        <td>${usageText}</td>
                        <td>-</td>
                        <td>${isRecommendedKeep ? 'Keep' : (image.is_published ? 'Protected' : 'Can Delete')}</td>
                    </tr>
                `);
            });
        });
        
        // Add usage check button event
        $('#check-usage-status').on('click', function() {
            checkDuplicateUsageStatus();
        });
        
        // Add cleanup button event
        $('#cleanup-safe-duplicates').on('click', function() {
            const selectedIds = $('.duplicate-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedIds.length === 0) {
                alert('No duplicates selected for cleanup.');
                return;
            }
            
            if (confirm(`This will permanently delete ${selectedIds.length} duplicate images. This cannot be undone. Continue?`)) {
                cleanupDuplicates(selectedIds);
            }
        });
    }
    
    function checkDuplicateUsageStatus() {
        if (isProcessing) return;
        
        // Get all image IDs from the duplicate results
        const allImageIds = $('.duplicate-checkbox').map(function() {
            return $(this).val();
        }).get();
        
        if (allImageIds.length === 0) {
            alert('No duplicate images found to check.');
            return;
        }
        
        isProcessing = true;
        updateLog(`Starting usage check for ${allImageIds.length} duplicate images...`);
        
        // Process in smaller batches to avoid timeouts
        processUsageCheckBatch(allImageIds, 0, {});
    }
    
    function processUsageCheckBatch(allImageIds, processed, allUsageData) {
        if (!isProcessing) {
            hideProcessingModal();
            return;
        }
        
        const batchSize = 20; // Check 20 images at a time
        const batch = allImageIds.slice(processed, processed + batchSize);
        
        if (batch.length === 0) {
            // All done - display results
            const usedCount = Object.values(allUsageData).filter(u => u.is_used).length;
            const safeToDelete = Object.values(allUsageData).length - usedCount;
            
            updateDuplicateUsageDisplay({
                usage_details: allUsageData,
                used_count: usedCount,
                safe_to_delete: safeToDelete
            });
            
            updateLog(`Usage check complete! Found ${usedCount} images in use, ${safeToDelete} safe to delete.`);
            isProcessing = false;
            hideProcessingModal();
            return;
        }
        
        const progress = Math.round((processed / allImageIds.length) * 100);
        showProcessingModal('Checking Usage Status', `Checking batch ${Math.floor(processed/batchSize) + 1}... (${progress}%)`);
        updateProcessingModal(progress, `Checking images ${processed + 1}-${processed + batch.length} of ${allImageIds.length}`);
        
        updateLog(`Checking batch: images ${processed + 1}-${processed + batch.length} (${progress}%)`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            timeout: 20000, // 20 seconds per batch
            data: {
                action: 'msh_check_duplicate_usage',
                nonce: mshImageOptimizer.cleanup_nonce,
                image_ids: batch
            },
            success: function(response) {
                if (response.success) {
                    // Merge this batch's results with previous batches
                    Object.assign(allUsageData, response.data.usage_details);
                    
                    updateLog(`Batch complete: Found ${response.data.used_count} in use, ${response.data.safe_to_delete} safe to delete`);
                    
                    // Process next batch
                    setTimeout(() => {
                        processUsageCheckBatch(allImageIds, processed + batch.length, allUsageData);
                    }, 500); // Small delay between batches
                } else {
                    updateLog('Usage check batch failed: ' + (response.data.message || 'Unknown error'));
                    isProcessing = false;
                    hideProcessingModal();
                }
            },
            error: function(xhr, status, error) {
                updateLog(`Usage check batch error: ${status} - ${error}`);
                if (xhr.responseText) {
                    updateLog('Server response: ' + xhr.responseText.substring(0, 200));
                }
                console.log('Usage check error:', {xhr: xhr, status: status, error: error});
                
                // Try to continue with next batch (skip failed batch)
                updateLog('Skipping failed batch and continuing...');
                setTimeout(() => {
                    processUsageCheckBatch(allImageIds, processed + batch.length, allUsageData);
                }, 1000);
            }
        });
    }
    
    function updateDuplicateUsageDisplay(usageData) {
        // Update each row with usage information
        Object.keys(usageData.usage_details).forEach(function(imageId) {
            const usage = usageData.usage_details[imageId];
            const $row = $(`.duplicate-checkbox[value="${imageId}"]`).closest('tr');
            
            if ($row.length > 0) {
                // Update the status column (last column)
                const $statusCell = $row.find('td:last');
                let statusText = '';
                let statusClass = '';
                let rowStyle = '';
                
                if (usage.is_used) {
                    statusText = 'IN USE - Keep';
                    statusClass = 'status-protected';
                    rowStyle = 'background: #fff3cd; border-left: 4px solid #ffc107;';
                    // Uncheck the checkbox since it's in use
                    $row.find('.duplicate-checkbox').prop('checked', false);
                } else {
                    statusText = 'Safe to Delete';
                    statusClass = 'status-safe-delete';
                    rowStyle = 'background: #f8d7da; border-left: 4px solid #dc3545;';
                    // Keep it checked since it's safe to delete
                    $row.find('.duplicate-checkbox').prop('checked', true);
                }
                
                $statusCell.html(`<span class="${statusClass}">${statusText}</span>`);
                $row.attr('style', rowStyle);
                
                // Update usage details in the "Issues" column
                const $issuesCell = $row.find('td:nth-child(6)');
                if (usage.usage_details && usage.usage_details.length > 0) {
                    const usageList = usage.usage_details.map(u => `${u.title} (${u.type})`).join(', ');
                    $issuesCell.html(`<small><strong>Used in:</strong> ${usageList}</small>`);
                } else {
                    $issuesCell.html(`<span style="color: #666;">Not used in published content</span>`);
                }
            }
        });
        
        updateLog(`Updated ${Object.keys(usageData.usage_details).length} duplicate entries with usage status.`);
    }
    
    function cleanupDuplicates(imageIds) {
        if (isProcessing) return;
        
        isProcessing = true;
        showProcessingModal('Cleaning Up Duplicates', `Removing ${imageIds.length} duplicate images...`);
        updateLog(`Starting cleanup of ${imageIds.length} duplicate images...`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_cleanup_media',
                nonce: mshImageOptimizer.cleanup_nonce,
                action_type: 'safe',
                image_ids: imageIds
            },
            success: function(response) {
                if (response.success) {
                    updateLog(`Cleanup complete! Deleted ${response.data.deleted_count} duplicate images.`);
                    
                    // Show detailed results
                    response.data.results.forEach(function(result) {
                        const status = result.status === 'deleted' ? 'DELETED' : 
                                     result.status === 'skipped' ? 'SKIPPED' : 'ERROR';
                        updateLog(`${status} Image ${result.id}: ${result.reason}`);
                    });
                    
                    // DISABLED: Auto-refresh causing analysis loop
                    // setTimeout(analyzeDuplicates, 1000);
                    console.log('MSH: Auto-duplicate analysis disabled to prevent loop');
                } else {
                    updateLog('Error during cleanup.');
                }
            },
            error: function() {
                updateLog('AJAX error during cleanup.');
            },
            complete: function() {
                isProcessing = false;
                hideProcessingModal();
            }
        });
    }
    
    function startDeepLibraryScan() {
        if (isProcessing) return;
        
        isProcessing = true;
        showProcessingModal('Deep Library Scan', 'Analyzing all 748 images...');
        updateLog('Starting deep library scan of all 748 images...');
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            timeout: 90000, // 90 seconds for full scan
            data: {
                action: 'msh_deep_library_scan',
                nonce: mshImageOptimizer.cleanup_nonce
            },
            success: function(response) {
                if (response.success) {
                    displayDuplicateResults(response.data);
                    updateLog(`Deep scan complete! Found ${response.data.total_groups} duplicate groups with ${response.data.total_duplicates} potential duplicates.`);
                    updateLog('Note: Deep scan analyzed ALL images in your media library for comprehensive duplicate detection.');
                    if (response.data.debug_info) {
                        const debug = response.data.debug_info;
                        updateLog(`Debug: Total scanned: ${debug.total_scanned}, All groups found: ${debug.all_groups_found}, Memory: ${Math.round(debug.memory_usage / 1024 / 1024)}MB`);
                        updateLog(`Scan type: ${debug.scan_type}`);
                    }
                } else {
                    updateLog('Deep scan failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                updateLog('Deep scan AJAX error: ' + status + ' - ' + error);
                if (xhr.responseText) {
                    updateLog('Server response: ' + xhr.responseText.substring(0, 200));
                }
                updateLog('Status code: ' + xhr.status);
                console.log('Deep scan error details:', {xhr: xhr, status: status, error: error});
            },
            complete: function() {
                isProcessing = false;
                hideProcessingModal();
            }
        });
    }
    
    // Keep old function for backward compatibility but mark as deprecated
    function startFullLibraryScan() {
        updateLog('Using legacy scan method - switching to new deep scan...');
        startDeepLibraryScan();
    }
    
    let allDuplicateGroups = {};
    
    function processFullScanBatch(offset) {
        if (!isProcessing) {
            hideProcessingModal();
            return;
        }
        
        // Safety limit to prevent infinite loops
        if (offset >= 800) {
            isProcessing = false;
            hideProcessingModal();
            updateLog('Scan stopped: Maximum scan limit reached (800 images)');
            return;
        }
        
        // Increase timeout for final batches (when progress > 95%)
        const isNearEnd = offset >= 700; // Final batches need more time for processing
        const timeoutMs = isNearEnd ? 20000 : 8000; // 20 seconds for final processing, 8 for regular batches
        
        if (isNearEnd) {
            updateLog('Approaching final processing - this may take 15-20 seconds...');
        }
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            timeout: timeoutMs,
            data: {
                action: 'msh_scan_full_library',
                nonce: mshImageOptimizer.cleanup_nonce,
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    if (data.completed) {
                        // Scan complete
                        isProcessing = false;
                        hideProcessingModal();
                        
                        updateLog(`Full library scan complete! Found ${Object.keys(allDuplicateGroups).length} duplicate groups.`);
                        displayDuplicateResults({
                            total_groups: Object.keys(allDuplicateGroups).length,
                            total_duplicates: Object.values(allDuplicateGroups).reduce((sum, group) => sum + group.cleanup_potential, 0),
                            groups: allDuplicateGroups
                        });
                        
                    } else {
                        // Continue with next batch
                        updateProcessingModal(data.progress_percent, 
                            `Processed ${data.processed}/${data.total_images} images (${data.progress_percent}%)`);
                        
                        // Merge new groups with existing ones
                        Object.assign(allDuplicateGroups, data.groups);
                        
                        updateLog(`Batch complete: ${data.processed}/${data.total_images} (${data.progress_percent}%) - Found ${data.duplicates_found} new duplicates`);
                        
                        // Show debug info on first batch
                        if (data.debug_info && data.processed <= 100) {
                            updateLog(`Debug: Processed ${data.debug_info.batch_image_count} images in this batch, found ${data.debug_info.groups_found} duplicate groups`);
                        }
                        
                        // Process next batch with small delay
                        setTimeout(() => {
                            processFullScanBatch(data.next_offset);
                        }, 500);
                    }
                } else {
                    isProcessing = false;
                    hideProcessingModal();
                    updateLog('Full scan failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                isProcessing = false;
                hideProcessingModal();
                updateLog('Full scan AJAX error: ' + status + ' - ' + error);
                if (xhr.responseText) {
                    updateLog('Server response: ' + xhr.responseText.substring(0, 500));
                }
                updateLog('Status code: ' + xhr.status);
                console.log('Full scan error details:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
            }
        });
    }
    
    function testCleanupConnection() {
        updateLog('Testing cleanup connection...');
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_test_cleanup',
                nonce: mshImageOptimizer.cleanup_nonce
            },
            success: function(response) {
                if (response.success) {
                    updateLog('Connection test successful: ' + response.data.message);
                } else {
                    updateLog('Connection test failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                updateLog('Connection test AJAX error: ' + status + ' - ' + error);
                updateLog('Response: ' + (xhr.responseText || 'No response'));
                console.log('Test AJAX Error:', xhr, status, error);
            }
        });
    }
    
    function startQuickDuplicateScan() {
        if (isProcessing) return;
        
        isProcessing = true;
        showProcessingModal('Quick Duplicate Scan', 'Scanning for obvious duplicate patterns...');
        updateLog('Starting quick duplicate scan (targets obvious duplicates)...');
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            timeout: 10000, // 10 second timeout for quick scan
            data: {
                action: 'msh_quick_duplicate_scan',
                nonce: mshImageOptimizer.cleanup_nonce
            },
            success: function(response) {
                if (response.success) {
                    displayDuplicateResults(response.data);
                    updateLog(`Quick scan complete! Found ${response.data.total_groups} duplicate groups with ${response.data.total_duplicates} potential duplicates.`);
                    updateLog('Note: Quick scan targets obvious duplicates (-copy, -scaled, size variations). Use Full Library Scan for comprehensive analysis.');
                    if (response.data.debug_info) {
                        const debug = response.data.debug_info;
                        updateLog(`Debug: Pattern matches: ${debug.pattern_matches}, Recent files: ${debug.recent_scanned}, Total analyzed: ${debug.total_scanned}, Groups found: ${debug.all_groups_found}`);
                        updateLog(`Memory: ${Math.round(debug.memory_usage / 1024 / 1024)}MB`);
                        if (debug.sample_files && debug.sample_files.length > 0) {
                            updateLog(`Sample files: ${debug.sample_files.join(', ')}`);
                        }
                    }
                } else {
                    updateLog('Quick scan failed: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                updateLog('Quick scan AJAX error: ' + status + ' - ' + error);
                if (xhr.responseText) {
                    updateLog('Server response: ' + xhr.responseText.substring(0, 200));
                }
                updateLog('Status code: ' + xhr.status);
                console.log('Quick scan error details:', {xhr: xhr, status: status, error: error});
            },
            complete: function() {
                isProcessing = false;
                hideProcessingModal();
            }
        });
    }
    
    function checkWebPSupport() {
        console.log('MSH: Starting WebP support detection...');

        // Set a timeout fallback in case detection hangs
        const detectionTimeout = setTimeout(function() {
            console.warn('MSH: WebP detection timed out, assuming supported');
            updateWebPStatus(true, 'timeout');
        }, 2000); // 2 second timeout

        // Create a WebP test image
        var webp = new Image();

        webp.onload = function() {
            clearTimeout(detectionTimeout);
            var supported = (webp.height == 2);
            console.log('MSH: WebP test image loaded, height:', webp.height, 'supported:', supported);
            updateWebPStatus(supported, 'onload');
        };

        webp.onerror = function() {
            clearTimeout(detectionTimeout);
            console.log('MSH: WebP test image failed to load, not supported');
            updateWebPStatus(false, 'onerror');
        };

        // Set the test image source
        webp.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    }

    function updateWebPStatus(supported, source) {
        console.log('MSH: Updating WebP status - supported:', supported, 'source:', source);

        // Update UI
        const supportElement = $('#webp-browser-support');

        if (supportElement.length === 0) {
            console.error('MSH: WebP support element not found in DOM');
            return;
        }

        if (supported) {
            supportElement.text('Supported').removeClass('not-supported').addClass('status-value supported');
            updateLog('WebP support detected - optimized images will be served automatically.');
        } else {
            supportElement.text('Not Supported').removeClass('supported').addClass('status-value not-supported');
            updateLog('WebP not supported - original images will be served (full compatibility).');
        }

        // Check if cookie exists
        const cookieExists = document.cookie.indexOf('webp_support=') !== -1;
        if (cookieExists) {
            $('#webp-detection-method').text('Cookie + JavaScript').addClass('status-value active');
        } else {
            $('#webp-detection-method').text('JavaScript Detection').addClass('status-value active');
        }

        $('#webp-delivery-status').text('Active').addClass('status-value active');

        console.log('MSH: WebP status update complete');
    }
    
    function updateLog(message) {
        const timestamp = new Date().toLocaleTimeString();
        const logMessage = `[${timestamp}] ${message}\n`;
        $('#optimization-log').append(logMessage);
        
        // Scroll to bottom
        const logContainer = $('#optimization-log').parent();
        logContainer.scrollTop(logContainer[0].scrollHeight);
    }
    
    function applyFilenameSuggestions() {
        // Get selected image IDs or use all if none selected
        const selectedIds = [];
        $('.image-checkbox:checked').each(function() {
            selectedIds.push($(this).data('id'));
        });

        const totalTargets = selectedIds.length || 'all suggested';
        updateLog(`Preparing safe rename for ${totalTargets} image(s)...`);

        let mode = 'full';
        let limit = 0;

        const proceedFull = window.confirm('Run full safe rename now?\nClick “Cancel” to run a 5-file safety test first.');
        if (!proceedFull) {
            if (!window.confirm('Run 5-file safe test? This will rename a small sample and update references.')) {
                updateLog('Safe rename cancelled by user.');
                return;
            }
            mode = 'test';
            limit = 5;
        } else {
            if (!window.confirm('This will rename files, update all references, and create backups/logs. Continue?')) {
                updateLog('Safe rename cancelled by user.');
                return;
            }
        }

        updateLog(mode === 'test'
            ? 'Running safe rename test on 5 image(s)...'
            : 'Starting full safe rename routine (all suggested files)...');

        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_apply_filename_suggestions',
                nonce: mshImageOptimizer.nonce,
                image_ids: selectedIds,
                mode: mode,
                limit: limit
            },
            success: function(response) {
                if (response.success) {
                    const summary = response.data.summary;
                    const modeLabel = summary.mode === 'test' ? 'Test run complete!' : 'Safe rename complete!';
                    updateLog(modeLabel);
                    updateLog(`Renamed successfully: ${summary.success}`);

                    if (summary.errors > 0) {
                        updateLog(`Errors: ${summary.errors}`);
                    }
                    if (summary.skipped > 0) {
                        updateLog(`Skipped: ${summary.skipped} (missing suggestion or unchanged)`);
                    }

                    response.data.results.forEach(function(result) {
                        if (result.status === 'success') {
                            updateLog(`✅ ${result.new_url} (references updated: ${result.references_updated})`);
                        } else if (result.status === 'error') {
                            updateLog(`❌ ID ${result.id}: ${result.message}`);
                        } else {
                            updateLog(`ℹ️ ID ${result.id}: ${result.message}`);
                        }
                    });

                    $('#analyze-images').trigger('click');
                } else {
                    updateLog('Safe rename failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                updateLog('Network error while applying safe rename.');
            }
        });
    }
    
    function saveFilenameSuggestion(imageId, newSuggestion) {
        if (!newSuggestion || newSuggestion.trim() === '') {
            alert('Please enter a valid filename suggestion.');
            return;
        }
        
        updateLog(`Saving filename suggestion for image ${imageId}: ${newSuggestion}`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_save_filename_suggestion',
                nonce: mshImageOptimizer.nonce,
                image_id: imageId,
                suggested_filename: newSuggestion
            },
            success: function(response) {
                if (response.success) {
                    updateLog(`Filename suggestion saved for image ${imageId}`);
                    // Update the button to show it was saved
                    $(`.save-filename[data-id="${imageId}"]`).text('Saved!').prop('disabled', true);
                    setTimeout(() => {
                        $(`.save-filename[data-id="${imageId}"]`).text('Save').prop('disabled', false);
                    }, 2000);
                } else {
                    updateLog(`Failed to save filename suggestion for image ${imageId}: ${response.data || 'Unknown error'}`);
                }
            },
            error: function() {
                updateLog(`Network error while saving filename suggestion for image ${imageId}`);
            }
        });
    }
    
    function keepCurrentFilename(imageId) {
        updateLog(`Removing filename suggestion for image ${imageId} (keeping current)`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_remove_filename_suggestion',
                nonce: mshImageOptimizer.nonce,
                image_id: imageId
            },
            success: function(response) {
                if (response.success) {
                    updateLog(`Filename suggestion removed for image ${imageId} - will keep current name`);
                    // Update the display to show current filename only
                    const $row = $(`.save-filename[data-id="${imageId}"]`).closest('tr');
                    const $cell = $row.find('td:nth-child(3)');
                    const currentFilename = $cell.find('strong:contains("Current:")').next().text().trim();
                    $cell.html(currentFilename);
                } else {
                    updateLog(`Failed to remove filename suggestion for image ${imageId}: ${response.data || 'Unknown error'}`);
                }
            },
            error: function() {
                updateLog(`Network error while removing filename suggestion for image ${imageId}`);
            }
        });
    }
    
    function previewMetaText(imageId) {
        updateLog(`Generating meta text preview for image ${imageId}...`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_preview_meta_text',
                nonce: mshImageOptimizer.nonce,
                image_id: imageId
            },
            success: function(response) {
                if (response.success) {
                    const meta = response.data;
                    
                    // Create safe modal structure using DOM methods
                    const $modal = $('<div>', {
                        id: 'meta-preview-modal',
                        css: {
                            position: 'fixed',
                            top: 0,
                            left: 0,
                            width: '100%',
                            height: '100%',
                            background: 'rgba(53, 51, 47, 0.8)',
                            zIndex: 9999,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center'
                        }
                    });
                    
                    const $content = $('<div>', {
                        css: {
                            background: '#ffffff',
                            padding: '30px',
                            borderRadius: '8px',
                            border: '1px solid #35332f',
                            maxWidth: '700px',
                            width: '95%',
                            maxHeight: '85%',
                            overflowY: 'auto',
                            position: 'relative'
                        }
                    });
                    
                    // Safe edit button
                    const $editButton = $('<button>', {
                        id: 'edit-meta-toggle',
                        text: 'Edit',
                        css: {
                            position: 'absolute',
                            top: '15px',
                            right: '15px',
                            background: '#daff00',
                            color: '#35332f',
                            border: '1px solid #35332f',
                            padding: '6px 10px',
                            borderRadius: '4px',
                            cursor: 'pointer',
                            fontSize: '12px'
                        }
                    });
                    
                    // Safe title
                    const $title = $('<h3>', {
                        text: `Meta Text Preview (Image ${imageId})`,
                        css: { marginTop: 0, marginRight: '80px', color: '#35332f' }
                    });
                    
                    // Helper function to create safe field sections
                    function createFieldSection(label, value, fieldName) {
                        const $section = $('<div>', { css: { marginBottom: '15px' } });
                        const $label = $('<strong>', { text: label + ':', css: { color: '#35332f' } });
                        const $br = $('<br>');
                        
                        const $display = $('<div>', {
                            class: 'meta-display',
                            text: value || 'No changes needed',
                            css: {
                                background: '#faf9f6',
                                padding: '8px',
                                borderRadius: '4px',
                                marginTop: '5px'
                            }
                        });
                        
                        const $textarea = $('<textarea>', {
                            class: 'meta-edit',
                            'data-field': fieldName,
                            val: value || '',
                            css: {
                                display: 'none',
                                width: '100%',
                                padding: '8px',
                                border: '1px solid #35332f',
                                borderRadius: '4px',
                                marginTop: '5px',
                                fontFamily: 'inherit',
                                resize: 'vertical'
                            }
                        });
                        
                        if (fieldName === 'description') {
                            $textarea.css('height', '80px');
                        }
                        
                        $section.append($label, $br, $display, $textarea);
                        return $section;
                    }
                    
                    // Create field sections safely
                    const $titleSection = createFieldSection('Title', meta.title, 'title');
                    const $captionSection = createFieldSection('Caption', meta.caption, 'caption');
                    const $altSection = createFieldSection('ALT Text', meta.alt_text, 'alt_text');
                    const $descSection = createFieldSection('Description', meta.description, 'description');
                    
                    // Safe buttons
                    const $buttonContainer = $('<div>', { css: { textAlign: 'right' } });
                    const $saveButton = $('<button>', {
                        id: 'save-meta-changes',
                        text: 'Save Changes',
                        css: {
                            display: 'none',
                            background: '#35332f',
                            color: '#faf9f6',
                            border: '1px solid #35332f',
                            padding: '8px 16px',
                            borderRadius: '4px',
                            cursor: 'pointer',
                            marginRight: '10px'
                        }
                    });
                    
                    const $closeButton = $('<button>', {
                        text: 'Close',
                        css: {
                            background: '#faf9f6',
                            color: '#35332f',
                            border: '1px solid #35332f',
                            padding: '8px 16px',
                            borderRadius: '4px',
                            cursor: 'pointer'
                        },
                        click: function() {
                            $modal.remove();
                        }
                    });
                    
                    $buttonContainer.append($saveButton, $closeButton);
                    $content.append($editButton, $title, $titleSection, $captionSection, $altSection, $descSection, $buttonContainer);
                    $modal.append($content);
                    
                    // Remove any existing modal and add new one
                    $('#meta-preview-modal').remove();
                    $('body').append($modal);
                    
                    // Add edit functionality
                    $('#edit-meta-toggle').on('click', function() {
                        const $button = $(this);
                        const isEditing = $button.text().includes('Edit');
                        
                        if (isEditing) {
                            // Switch to edit mode
                            $('.meta-display').hide();
                            $('.meta-edit').show();
                            $('#save-meta-changes').show();
                            $button.text('Preview');
                        } else {
                            // Switch to preview mode
                            $('.meta-edit').hide();
                            $('.meta-display').show();
                            $('#save-meta-changes').hide();
                            $button.text('Edit');
                        }
                    });
                    
                    // Save changes functionality
                    $('#save-meta-changes').on('click', function() {
                        const editedMeta = {
                            title: $('.meta-edit[data-field="title"]').val(),
                            caption: $('.meta-edit[data-field="caption"]').val(),
                            alt_text: $('.meta-edit[data-field="alt_text"]').val(),
                            description: $('.meta-edit[data-field="description"]').val()
                        };
                        
                        saveEditedMeta(imageId, editedMeta);
                    });
                    
                    updateLog(`Meta text preview generated for image ${imageId}`);
                } else {
                    updateLog(`Failed to generate meta text preview for image ${imageId}: ${response.data || 'Unknown error'}`);
                    alert('Failed to generate meta text preview. Check the log for details.');
                }
            },
            error: function() {
                updateLog(`Network error while generating meta text preview for image ${imageId}`);
                alert('Network error while generating preview.');
            }
        });
    }
    
    function saveEditedMeta(imageId, metaData) {
        updateLog(`Saving edited meta text for image ${imageId}...`);
        
        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_save_edited_meta',
                nonce: mshImageOptimizer.nonce,
                image_id: imageId,
                meta_data: metaData
            },
            success: function(response) {
                if (response.success) {
                    updateLog(`Meta text saved successfully for image ${imageId}`);
                    
                    // Update the display with the new values
                    $('.meta-display').each(function() {
                        const $display = $(this);
                        const $edit = $display.siblings('.meta-edit');
                        const field = $edit.data('field');
                        if (metaData[field]) {
                            $display.text(metaData[field]);
                        }
                    });
                    
                    // Switch back to preview mode
                    $('.meta-edit').hide();
                    $('.meta-display').show();
                    $('#save-meta-changes').hide();
                    $('#edit-meta-toggle').text('Edit');
                    
                    alert('Meta text saved successfully!');
                } else {
                    updateLog(`Failed to save meta text for image ${imageId}: ${response.data || 'Unknown error'}`);
                    alert('Failed to save meta text. Check the log for details.');
                }
            },
            error: function() {
                updateLog(`Network error while saving meta text for image ${imageId}`);
                alert('Network error while saving meta text.');
            }
        });
    }

    /**
     * Build the image usage index for faster URL replacement
     */
    function buildUsageIndex() {
        console.log('Building usage index...');

        const $button = $('#build-usage-index');
        const originalText = $button.text();

        $button.prop('disabled', true).text('Building Index...');
        updateLog('🚀 Building image usage index for faster safe rename operations...');

        $.ajax({
            url: mshImageOptimizer.ajaxurl,
            type: 'POST',
            data: {
                action: 'msh_build_usage_index',
                nonce: mshImageOptimizer.nonce
            },
            success: function(response) {
                $button.prop('disabled', false).text(originalText);

                if (response.success) {
                    const stats = response.data.stats;
                    const summary = stats.summary || {};

                    updateLog(`✅ Safe rename system enabled successfully!`);
                    updateLog(`📊 Database tables created and ready`);
                    updateLog(`⚡ Enhanced targeted replacement engine active`);
                    updateLog(`🔒 Automatic backup and verification enabled`);
                    updateLog(`🚀 System will build usage index on-demand for maximum speed`);

                    if (stats.note) {
                        updateLog(`ℹ️  ${stats.note}`);
                    }

                    updateLog('🎉 Safe rename system ready! Files will be renamed safely with full URL replacement.');

                    // Show success message
                    alert('✅ Safe rename system activated!\n\n' +
                          '• Enhanced targeted replacement ready\n' +
                          '• Automatic backups enabled\n' +
                          '• On-demand indexing for speed\n\n' +
                          'You can now safely rename files!');
                } else {
                    updateLog('❌ Failed to enable safe rename system: ' + (response.data || 'Unknown error'));
                    alert('Failed to enable safe rename system. Check the log for details.');
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false).text(originalText);
                updateLog('🚨 Network error while building usage index: ' + error);
                alert('Network error while building usage index.');
            }
        });
    }

})(jQuery);
