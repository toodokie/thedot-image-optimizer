<?php
/**
 * File resolver helper for attachment path mismatches.
 *
 * @package MSH_Image_Optimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_File_Resolver {

    /**
     * Attempt to locate an attachment's file on disk, falling back to pattern search when needed.
     *
     * @param int    $attachment_id Attachment post ID.
     * @param string $expected_path Relative path stored in _wp_attached_file.
     * @param bool   $strict        If true, only return the expected path and skip fallback search.
     *
     * @return array{
     *     path: string|null,
     *     mismatch: bool,
     *     method: 'direct'|'fallback'|'not_found',
     * }
     */
    public static function find_attachment_file($attachment_id, $expected_path, $strict = false) {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']);

        $normalized_expected = ltrim((string) $expected_path, '/');
        $absolute_expected   = $base_dir . $normalized_expected;

        if (self::file_exists_case_sensitive($absolute_expected)) {
            // Log direct match to debug logger if available
            if (class_exists('MSH_Debug_Logger')) {
                MSH_Debug_Logger::get_instance()->log_resolver(
                    $attachment_id,
                    $normalized_expected,
                    $normalized_expected,
                    'direct',
                    false
                );
            }

            return [
                'path'     => $absolute_expected,
                'mismatch' => false,
                'method'   => 'direct',
            ];
        }

        if ($strict) {
            return [
                'path'     => null,
                'mismatch' => false,
                'method'   => 'not_found',
            ];
        }

        $pattern_candidates = self::find_by_attachment_id($attachment_id, $expected_path, $upload_dir);

        if (empty($pattern_candidates)) {
            return [
                'path'     => null,
                'mismatch' => false,
                'method'   => 'not_found',
            ];
        }

        if (count($pattern_candidates) > 1) {
            return [
                'path'     => null,
                'mismatch' => true,
                'method'   => 'not_found',
            ];
        }

        $candidate = $pattern_candidates[0];

        if (!self::validate_candidate($attachment_id, $candidate)) {
            return [
                'path'     => null,
                'mismatch' => true,
                'method'   => 'not_found',
            ];
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[MSH File Resolver] Resolved mismatch for attachment %d: expected "%s" → found "%s"',
                $attachment_id,
                $normalized_expected,
                str_replace($base_dir, '', $candidate)
            ));
        }

        // Log to debug logger if available
        if (class_exists('MSH_Debug_Logger')) {
            MSH_Debug_Logger::get_instance()->log_resolver(
                $attachment_id,
                $normalized_expected,
                str_replace($base_dir, '', $candidate),
                'fallback',
                true
            );
        }

        return [
            'path'     => $candidate,
            'mismatch' => true,
            'method'   => 'fallback',
        ];
    }

    private static function find_by_attachment_id($attachment_id, $expected_path, $upload_dir) {
        $normalized_expected = ltrim((string) $expected_path, '/');
        $path_info           = pathinfo($normalized_expected);

        $dir_name  = isset($path_info['dirname']) && $path_info['dirname'] !== '.'
            ? $path_info['dirname']
            : '';
        $extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';

        if ($dir_name === '' || $extension === '') {
            return [];
        }

        $absolute_dir = trailingslashit($upload_dir['basedir']) . $dir_name;
        if (!is_dir($absolute_dir) || !is_readable($absolute_dir)) {
            return [];
        }

        $pattern = sprintf('*-%d.%s', (int) $attachment_id, $extension);
        $matches = glob(trailingslashit($absolute_dir) . $pattern, GLOB_NOSORT);

        if (!is_array($matches)) {
            return [];
        }

        return array_values(array_filter($matches, [__CLASS__, 'file_exists_case_sensitive']));
    }

    private static function validate_candidate($attachment_id, $absolute_path) {
        if (!self::file_exists_case_sensitive($absolute_path)) {
            return false;
        }

        $expected_mime  = get_post_mime_type($attachment_id);
        $candidate_type = wp_check_filetype($absolute_path);
        $candidate_mime = isset($candidate_type['type']) ? $candidate_type['type'] : '';

        if (!empty($expected_mime) && !empty($candidate_mime)) {
            $expected_family  = strtok($expected_mime, '/');
            $candidate_family = strtok($candidate_mime, '/');
            if ($expected_family !== $candidate_family) {
                return false;
            }
        }

        $file_mtime = @filemtime($absolute_path);
        $post_time  = get_post_time('U', true, $attachment_id);

        if ($file_mtime && $post_time && $file_mtime < ($post_time - HOUR_IN_SECONDS)) {
            return false;
        }

        return true;
    }

    private static function file_exists_case_sensitive($absolute_path) {
        if (!file_exists($absolute_path)) {
            return false;
        }

        $basename     = basename($absolute_path);
        $directory    = dirname($absolute_path);
        $directory_ls = @scandir($directory);

        if ($directory_ls === false) {
            return true;
        }

        return in_array($basename, $directory_ls, true);
    }
}

