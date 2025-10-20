/**
 * MSH Image Optimizer - Enhanced Rename UI
 * Provides progress tracking, time estimates, and notifications
 */

(function($) {
    'use strict';

    // Store for rename progress tracking
    window.MSHRenameUI = {
        totalFiles: 0,
        processedFiles: 0,
        startTime: null,
        isProcessing: false,
        currentBatch: 1,
        totalBatches: 1
    };

    // Create the enhanced rename UI modal
    function createRenameModal() {
        const modalHtml = `
        <div id="msh-rename-modal" class="msh-modal" style="display:none;">
            <div class="msh-modal-overlay"></div>
            <div class="msh-modal-content">
                <div class="msh-modal-header">
                    <h2>🔄 Safe File Rename</h2>
                    <span class="msh-modal-close">&times;</span>
                </div>

                <div class="msh-modal-body">
                    <!-- Status Section -->
                    <div class="msh-rename-status">
                        <div class="status-icon">⏳</div>
                        <div class="status-text">Preparing rename operation...</div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="msh-progress-container">
                        <div class="msh-progress-bar">
                            <div class="msh-progress-fill" style="width: 0%">
                                <span class="msh-progress-text">0%</span>
                            </div>
                        </div>
                        <div class="msh-progress-details">
                            <span class="files-count">0 / 0 files</span>
                            <span class="time-estimate">Calculating...</span>
                        </div>
                    </div>

                    <!-- Current Operation -->
                    <div class="msh-current-operation">
                        <div class="operation-label">Current file:</div>
                        <div class="operation-file">Waiting to start...</div>
                    </div>

                    <!-- Live Log -->
                    <div class="msh-rename-log">
                        <div class="log-header">
                            <span>📋 Operation Log</span>
                            <button class="clear-log">Clear</button>
                        </div>
                        <div class="log-content"></div>
                    </div>

                    <!-- Statistics -->
                    <div class="msh-rename-stats">
                        <div class="stat-item success">
                            <span class="stat-icon">✅</span>
                            <span class="stat-value">0</span>
                            <span class="stat-label">Renamed</span>
                        </div>
                        <div class="stat-item skipped">
                            <span class="stat-icon">⏭️</span>
                            <span class="stat-value">0</span>
                            <span class="stat-label">Skipped</span>
                        </div>
                        <div class="stat-item error">
                            <span class="stat-icon">❌</span>
                            <span class="stat-value">0</span>
                            <span class="stat-label">Errors</span>
                        </div>
                        <div class="stat-item time">
                            <span class="stat-icon">⏱️</span>
                            <span class="stat-value">0s</span>
                            <span class="stat-label">Elapsed</span>
                        </div>
                    </div>
                </div>

                <div class="msh-modal-footer">
                    <button id="msh-rename-cancel" class="button button-secondary">Cancel</button>
                    <button id="msh-rename-background" class="button">Run in Background</button>
                    <button id="msh-rename-close" class="button button-primary" style="display:none;">Close</button>
                </div>
            </div>
        </div>

        <style>
        .msh-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 100000;
        }

        .msh-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
        }

        .msh-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .msh-modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .msh-modal-header h2 {
            margin: 0;
            color: #35332f;
            font-size: 24px;
        }

        .msh-modal-close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.2s;
        }

        .msh-modal-close:hover {
            color: #333;
        }

        .msh-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .msh-rename-status {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .status-icon {
            font-size: 32px;
            margin-right: 15px;
        }

        .status-text {
            font-size: 18px;
            color: #35332f;
            font-weight: 500;
        }

        .msh-progress-container {
            margin-bottom: 25px;
        }

        .msh-progress-bar {
            height: 32px;
            background: #e0e0e0;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }

        .msh-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #c5ff00, #daff00);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .msh-progress-text {
            color: #35332f;
            font-weight: bold;
            font-size: 14px;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .msh-progress-details {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }

        .msh-current-operation {
            padding: 12px;
            background: #fafafa;
            border-left: 3px solid #daff00;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .operation-label {
            font-size: 12px;
            color: #888;
            margin-bottom: 5px;
        }

        .operation-file {
            font-size: 14px;
            color: #35332f;
            font-family: monospace;
            word-break: break-all;
        }

        .msh-rename-log {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 20px;
            max-height: 200px;
        }

        .log-header {
            padding: 10px;
            background: #f5f5f5;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .clear-log {
            font-size: 12px;
            padding: 2px 8px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
        }

        .log-content {
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
            font-size: 12px;
            font-family: monospace;
        }

        .log-entry {
            padding: 2px 0;
        }

        .log-entry.success { color: #4CAF50; }
        .log-entry.error { color: #f44336; }
        .log-entry.info { color: #2196F3; }

        .msh-rename-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px 10px;
            background: #f9f9f9;
            border-radius: 6px;
        }

        .stat-item.success { background: #e8f5e9; }
        .stat-item.skipped { background: #fff3e0; }
        .stat-item.error { background: #ffebee; }
        .stat-item.time { background: #e3f2fd; }

        .stat-icon {
            display: block;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-value {
            display: block;
            font-size: 20px;
            font-weight: bold;
            color: #35332f;
            margin-bottom: 3px;
        }

        .stat-label {
            display: block;
            font-size: 12px;
            color: #666;
        }

        .msh-modal-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .processing .status-icon {
            animation: pulse 1.5s infinite;
        }
        </style>`;

        if ($('#msh-rename-modal').length === 0) {
            $('body').append(modalHtml);

            // Set up event handlers
            $('.msh-modal-close, #msh-rename-cancel').on('click', function() {
                if (MSHRenameUI.isProcessing) {
                    if (confirm('Rename operation is in progress. Are you sure you want to cancel?')) {
                        MSHRenameUI.isProcessing = false;
                        closeRenameModal();
                    }
                } else {
                    closeRenameModal();
                }
            });

            $('#msh-rename-background').on('click', function() {
                $('#msh-rename-modal').fadeOut();
                showMinimizedProgress();
            });

            $('.clear-log').on('click', function() {
                $('.log-content').empty();
            });
        }
    }

    function showRenameModal(totalFiles, mode) {
        createRenameModal();

        MSHRenameUI.totalFiles = totalFiles;
        MSHRenameUI.processedFiles = 0;
        MSHRenameUI.startTime = Date.now();
        MSHRenameUI.isProcessing = true;

        const estimatedSeconds = Math.ceil(totalFiles * 1.5);
        const estimatedMinutes = Math.floor(estimatedSeconds / 60);
        const remainingSeconds = estimatedSeconds % 60;
        const timeString = estimatedMinutes > 0
            ? `${estimatedMinutes}m ${remainingSeconds}s`
            : `${estimatedSeconds}s`;

        $('#msh-rename-modal').fadeIn();
        $('.msh-modal-content').addClass('processing');
        $('.status-text').text(mode === 'test'
            ? 'Running test mode (5 files)...'
            : `Processing ${totalFiles} files...`);
        $('.files-count').text(`0 / ${totalFiles} files`);
        $('.time-estimate').text(`Est: ${timeString}`);

        // Reset stats
        $('.stat-item.success .stat-value').text('0');
        $('.stat-item.skipped .stat-value').text('0');
        $('.stat-item.error .stat-value').text('0');

        // Start elapsed time counter
        startElapsedTimer();
    }

    function updateRenameProgress(processed, total, currentFile) {
        const percent = Math.round((processed / total) * 100);

        $('.msh-progress-fill').css('width', percent + '%');
        $('.msh-progress-text').text(percent + '%');
        $('.files-count').text(`${processed} / ${total} files`);

        if (currentFile) {
            $('.operation-file').text(currentFile);
        }

        // Update time estimate
        if (processed > 0) {
            const elapsed = (Date.now() - MSHRenameUI.startTime) / 1000;
            const avgPerFile = elapsed / processed;
            const remaining = (total - processed) * avgPerFile;
            const remainingMinutes = Math.floor(remaining / 60);
            const remainingSeconds = Math.ceil(remaining % 60);

            if (remaining > 0) {
                const timeString = remainingMinutes > 0
                    ? `${remainingMinutes}m ${remainingSeconds}s remaining`
                    : `${remainingSeconds}s remaining`;
                $('.time-estimate').text(timeString);
            }
        }
    }

    function addLogEntry(message, type = 'info') {
        const $log = $('.log-content');
        const timestamp = new Date().toLocaleTimeString();
        const entry = $(`<div class="log-entry ${type}">[${timestamp}] ${message}</div>`);

        $log.append(entry);
        $log.scrollTop($log[0].scrollHeight);

        // Limit log entries to prevent memory issues
        const entries = $log.find('.log-entry');
        if (entries.length > 100) {
            entries.first().remove();
        }
    }

    function completeRename(stats) {
        MSHRenameUI.isProcessing = false;

        $('.msh-modal-content').removeClass('processing');
        $('.status-icon').text('✅');
        $('.status-text').text('Rename operation complete!');
        $('.msh-progress-fill').css({
            'width': '100%',
            'background': 'linear-gradient(90deg, #4CAF50, #66BB6A)'
        });

        // Play completion sound
        playCompletionSound();

        // Show notification
        showNotification('Rename Complete!', `Successfully processed ${MSHRenameUI.totalFiles} files`);

        // Update buttons
        $('#msh-rename-cancel, #msh-rename-background').hide();
        $('#msh-rename-close').show().on('click', function() {
            closeRenameModal();
            // Trigger refresh of the image list
            $('#analyze-images').trigger('click');
        });

        addLogEntry('🎉 All rename operations completed successfully!', 'success');
    }

    function startElapsedTimer() {
        const updateTimer = () => {
            if (!MSHRenameUI.isProcessing) return;

            const elapsed = Math.floor((Date.now() - MSHRenameUI.startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            const timeString = minutes > 0
                ? `${minutes}m ${seconds}s`
                : `${seconds}s`;

            $('.stat-item.time .stat-value').text(timeString);

            setTimeout(updateTimer, 1000);
        };

        updateTimer();
    }

    function playCompletionSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUasm99eCNwcZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N+RQAoUXrTp66hVFApGn+DyvmwhBSuBzvLaiTYIGWi78OScTgwOUarm7+RZQgouev3swXcqBied9+yhUBIHPZ/u1JVMIhxfl+PyvmwhBSuBzvLaiTYIGWi78OScTgwOUarm7+RZQgouev3swXcqBied9+yhUBIHPZ/u1JVMIhxfl+PyvmwhBSuBzvLaiTYIGWi78OScTgwOUarm7+RZQgouev3swXcqBied9+yhUBIHPZ/u1JVMIhxfl+PyvmwhBSuBzvLaiTYIGWi78OScTgwOUarm7+RZQgouev3swXcqBied9+yhUBIHPZ/u1JVMIhxfl+PyvmwhBSuBzvLaiTYIGWi78OScTgwOUarm7+RZQgouevu0fC0GKY/y8KxVEgU9oO/anEwhHlOh5+6pWBcLNYjS9NB3Lwo1htDvuVIKCjGAyfLTgjQHF12w7OGhXRQLO47V8ceANwgZaLvt559NEAxQqOPwtmMcBjiS1/LLeSwFJHfH8N+RQAoUXrTp66hVFApGn+DyvmwhBSuBzvLaiTYIGWm98OScTgwOUqvm7+RaQgoreP3swXcqBied9+yhUBIHPZ/u1JVMIhxfl+PyvmwhBSyAzvLaiTYIGWi78OScTgwOUqrm7+RZQgouev3swXcqBied9+yhUBIHPZ/u1JVMIhxfl+PyvmwhBSuBzvLaiTYIGWi78OScTgwOUqrm7+RZQgouev3swXcqBied9+yhUBIHPZ/u1JVMIh1gkOa2tVsWDECY6+O4Ywcxicfz2YwzBh5TsuXptyYNL4H29d5'));
            audio.volume = 0.3;
            audio.play();
        } catch (e) {
            console.log('Audio not available');
        }
    }

    function showNotification(title, message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, {
                body: message,
                icon: '/favicon.ico',
                tag: 'msh-rename'
            });
        }
    }

    function closeRenameModal() {
        $('#msh-rename-modal').fadeOut();
        MSHRenameUI.isProcessing = false;
    }

    function showMinimizedProgress() {
        // Create a small floating progress indicator
        const miniHtml = `
        <div id="msh-mini-progress" style="position:fixed; bottom:20px; right:20px; background:white; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.2); z-index:10000;">
            <div style="font-size:14px; color:#35332f; margin-bottom:5px;">Renaming files...</div>
            <div style="width:200px; height:20px; background:#e0e0e0; border-radius:10px; overflow:hidden;">
                <div class="mini-progress-fill" style="width:0%; height:100%; background:#daff00; transition:width 0.3s;"></div>
            </div>
            <div style="font-size:12px; color:#666; margin-top:5px;">
                <span class="mini-progress-text">0%</span> •
                <a href="#" onclick="$('#msh-rename-modal').fadeIn(); $('#msh-mini-progress').remove(); return false;">Show details</a>
            </div>
        </div>`;

        if ($('#msh-mini-progress').length === 0) {
            $('body').append(miniHtml);
        }
    }

    // Request notification permission on page load
    $(document).ready(function() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    });

    // Export functions for use in main script
    window.MSHRenameUI.show = showRenameModal;
    window.MSHRenameUI.updateProgress = updateRenameProgress;
    window.MSHRenameUI.addLog = addLogEntry;
    window.MSHRenameUI.complete = completeRename;
    window.MSHRenameUI.updateStats = function(success, skipped, errors) {
        $('.stat-item.success .stat-value').text(success);
        $('.stat-item.skipped .stat-value').text(skipped);
        $('.stat-item.error .stat-value').text(errors);
    };

})(jQuery);