<?php
/**
 * WP-CLI helpers for exercising the Safe Rename system.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('msh rename-regression', function($args, $assoc_args) {
        $ids = isset($assoc_args['ids']) ? array_filter(array_map('intval', explode(',', $assoc_args['ids']))) : [];
        $mode = isset($assoc_args['mode']) ? strtolower($assoc_args['mode']) : 'test';

        if (empty($ids)) {
            WP_CLI::error('Provide a comma-separated list of attachment IDs via --ids=123,456.');
            return;
        }

        $safe_rename = MSH_Safe_Rename_System::get_instance();
        $success = 0;
        $failures = [];

        foreach ($ids as $attachment_id) {
            $path = get_attached_file($attachment_id);
            if (!$path || !file_exists($path)) {
                $failures[] = [
                    'id' => $attachment_id,
                    'message' => 'Original file not found.'
                ];
                continue;
            }

            $extension = pathinfo($path, PATHINFO_EXTENSION);
            $base = pathinfo($path, PATHINFO_FILENAME);
            $target_name = sanitize_file_name($base . '-msh-regression.' . $extension);
            $test_mode = $mode !== 'live';

            $result = $safe_rename->rename_attachment($attachment_id, $target_name, $test_mode);

            if (is_wp_error($result)) {
                $failures[] = [
                    'id' => $attachment_id,
                    'message' => $result->get_error_message()
                ];
                continue;
            }

            if (!empty($result['skipped'])) {
                WP_CLI::warning("Attachment {$attachment_id}: rename skipped (identical filename)");
                continue;
            }

            $success++;
            $mode_label = $test_mode ? 'TEST' : 'LIVE';
            WP_CLI::log("Attachment {$attachment_id}: {$mode_label} rename simulated; references touched: " . intval($result['replaced']));
        }

        WP_CLI::log('--------------------------------------------------');
        WP_CLI::log("Successful operations: {$success}");

        if (!empty($failures)) {
            WP_CLI::warning('Failures detected:');
            foreach ($failures as $failure) {
                WP_CLI::warning(" - ID {$failure['id']}: {$failure['message']}");
            }
        }
    });
}
