/**
 * MSH Image Optimizer - Enhanced Integration
 * Integrates the enhanced UI with existing functionality
 */

jQuery(document).ready(function($) {
    'use strict';

    // Wait for both UI and base function to be available
    function initEnhancedUI() {
        if (window.MSHRenameUI && window.applyFilenameSuggestions) {
            const originalFunction = window.applyFilenameSuggestions;

            window.applyFilenameSuggestions = function() {
                startEnhancedRename();
            };

            console.log('Enhanced UI successfully integrated');
            return true;
        }
        return false;
    }

    // Try immediate integration
    if (!initEnhancedUI()) {
        // If not ready, try again after short delay
        setTimeout(function() {
            if (!initEnhancedUI()) {
                console.log('Enhanced UI integration failed - base function not available');
            }
        }, 1000);
    }

        function startEnhancedRename() {
            // Get selected image IDs or count all suggested
            const selectedIds = [];
            $('.image-checkbox:checked').each(function() {
                selectedIds.push($(this).data('id'));
            });

            // Estimate total files from results table
            const totalFiles = selectedIds.length || ($('.results-table tr').length - 1);

            updateLog(`Preparing enhanced safe rename for ${totalFiles} image(s)...`);

            let mode = 'full';
            let limit = 0;

            const estimatedTime = Math.ceil(totalFiles * 1.5);
            const timeString = estimatedTime > 60
                ? `${Math.floor(estimatedTime / 60)}m ${estimatedTime % 60}s`
                : `${estimatedTime}s`;

            const message = `Ready to rename ${totalFiles} files?\\n\\n` +
                           `⏱️ Estimated time: ${timeString}\\n` +
                           `📁 Files processed in batches of 20\\n` +
                           `💾 Automatic backups created\\n` +
                           `🔄 References automatically updated\\n\\n` +
                           `Choose an option:\\n` +
                           `OK = Full rename (all files)\\n` +
                           `Cancel = Test mode (5 files first)`;

            const proceedFull = window.confirm(message);

            if (!proceedFull) {
                if (!window.confirm('Run 5-file safety test first?\\nThis helps verify everything works correctly.')) {
                    updateLog('Safe rename cancelled by user.');
                    return;
                }
                mode = 'test';
                limit = 5;
            }

            // Show enhanced progress UI
            const displayTotal = mode === 'test' ? 5 : totalFiles;
            window.MSHRenameUI.show(displayTotal, mode);

            // Start processing
            processBatchWithUI(selectedIds, mode, limit, 0, displayTotal);
        }

        function processBatchWithUI(imageIds, mode, limit, processedSoFar, totalFiles) {
            const batchNumber = Math.floor(processedSoFar / 20) + 1;
            const isFirstBatch = processedSoFar === 0;

            if (isFirstBatch) {
                window.MSHRenameUI.addLog(`🚀 Starting ${mode === 'test' ? 'test' : 'full'} rename operation`, 'info');
            } else {
                window.MSHRenameUI.addLog(`📦 Processing batch ${batchNumber}...`, 'info');
            }

            $.ajax({
                url: mshImageOptimizer.ajaxurl,
                type: 'POST',
                data: {
                    action: 'msh_apply_filename_suggestions',
                    nonce: mshImageOptimizer.nonce,
                    image_ids: imageIds,
                    mode: mode,
                    limit: limit
                },
                success: function(response) {
                    if (response.success) {
                        const summary = response.data.summary;
                        const newProcessed = processedSoFar + summary.success + summary.errors + summary.skipped;
                        const percent = Math.round((newProcessed / totalFiles) * 100);

                        // Update progress
                        window.MSHRenameUI.updateProgress(newProcessed, totalFiles);
                        window.MSHRenameUI.updateStats(summary.success, summary.skipped, summary.errors);

                        // Add batch results to log
                        const batchMsg = `✅ Batch ${batchNumber} complete: ${summary.success} renamed, ${summary.errors} errors, ${summary.skipped} skipped`;
                        window.MSHRenameUI.addLog(batchMsg, summary.errors > 0 ? 'error' : 'success');

                        // Log individual results for errors
                        response.data.results.forEach(function(result) {
                            if (result.status === 'error') {
                                window.MSHRenameUI.addLog(`❌ ID ${result.id}: ${result.message}`, 'error');
                            }
                        });

                        // Also log to main log
                        updateLog(`Batch ${batchNumber}: ${summary.success} renamed, ${summary.errors} errors, ${summary.skipped} skipped`);

                        // Check if more batches needed
                        if (response.data.has_more && mode !== 'test' && newProcessed < totalFiles) {
                            // Continue with next batch after short delay
                            setTimeout(() => {
                                window.MSHRenameUI.addLog(`⏳ Preparing next batch...`, 'info');
                                processBatchWithUI(imageIds, mode, limit, newProcessed, totalFiles);
                            }, 1500);
                        } else {
                            // All done!
                            const finalStats = {
                                total: newProcessed,
                                success: summary.success,
                                errors: summary.errors,
                                skipped: summary.skipped
                            };

                            window.MSHRenameUI.complete(finalStats);
                            window.MSHRenameUI.addLog(`🎉 Rename operation completed! Total files processed: ${newProcessed}`, 'success');

                            updateLog(`✅ Safe rename complete! Processed ${newProcessed} files total`);

                            // Auto-refresh the image list after 2 seconds
                            setTimeout(() => {
                                $('#analyze-images').trigger('click');
                            }, 2000);
                        }
                    } else {
                        window.MSHRenameUI.addLog(`❌ Batch failed: ${response.data || 'Unknown error'}`, 'error');
                        updateLog('Safe rename failed: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    const errorMsg = `Network error in batch ${batchNumber}: ${error}`;
                    window.MSHRenameUI.addLog(`🚨 ${errorMsg}`, 'error');
                    updateLog(errorMsg);
                }
            });
        }
    }

    // Add a visual indicator for the enhanced features
    console.log('Enhanced UI script loaded. MSHRenameUI available:', !!window.MSHRenameUI);
    console.log('applyFilenameSuggestions available:', !!window.applyFilenameSuggestions);

    // Wait for the button to exist, then add indicator
    function addUIIndicator() {
        const $button = $('#apply-filename-suggestions');
        if ($button.length > 0 && !$('#enhanced-ui-indicator').length) {
            if (window.MSHRenameUI && window.applyFilenameSuggestions) {
                $button.after(
                    '<div id="enhanced-ui-indicator" style="margin-top: 5px; font-size: 12px; color: #4CAF50; font-weight: bold;">' +
                    '✨ Enhanced progress tracking enabled' +
                    '</div>'
                );
                console.log('Enhanced UI indicator added - ENABLED');
            } else {
                $button.after(
                    '<div id="enhanced-ui-indicator" style="margin-top: 5px; font-size: 12px; color: #f44336;">' +
                    '⚠️ Enhanced UI not loaded (MSHRenameUI: ' + !!window.MSHRenameUI + ', applyFilenameSuggestions: ' + !!window.applyFilenameSuggestions + ')' +
                    '</div>'
                );
                console.log('Enhanced UI not available - MSHRenameUI:', !!window.MSHRenameUI, 'applyFilenameSuggestions:', !!window.applyFilenameSuggestions);
            }
            return true;
        }
        return false;
    }

    // Try multiple times to add indicator
    if (!addUIIndicator()) {
        setTimeout(addUIIndicator, 500);
        setTimeout(addUIIndicator, 1500);
        setTimeout(addUIIndicator, 3000);
    }
});