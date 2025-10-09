<?php
/**
 * MSH URL Variation Detector
 * Identifies all possible URL variations for images to ensure complete reference updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_URL_Variation_Detector {
    private static $instance = null;
    private $upload_dir;
    private $base_url;
    private $base_path;

    private function __construct() {
        $this->upload_dir = wp_upload_dir();
        $this->base_url = trailingslashit($this->upload_dir['baseurl']);
        $this->base_path = trailingslashit($this->upload_dir['basedir']);
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get all possible URL variations for an attachment
     */
    public function get_all_variations($attachment_id) {
        $variations = [];

        // Get attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        $attached_file = get_attached_file($attachment_id);
        $attachment_url = wp_get_attachment_url($attachment_id);

        if (!$attached_file || !$attachment_url) {
            return $variations;
        }

        // Original file variations
        $variations = array_merge($variations, $this->get_file_variations($attachment_url, $attached_file));

        // Size variations (thumbnails, medium, large, etc.)
        if (is_array($metadata) && !empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $data) {
                if (empty($data['file'])) {
                    continue;
                }

                $size_path = dirname($attached_file) . '/' . $data['file'];
                $size_url = dirname($attachment_url) . '/' . $data['file'];

                $variations = array_merge($variations, $this->get_file_variations($size_url, $size_path));
            }
        }

        // WebP variations (both original and sizes)
        $webp_variations = $this->get_webp_variations($variations);
        $variations = array_merge($variations, $webp_variations);

        // Remove duplicates and empty values
        $variations = array_unique(array_filter($variations));

        return $variations;
    }

    /**
     * Get variations for a single file (absolute URLs, relative URLs, filenames)
     */
    private function get_file_variations($url, $path) {
        $variations = [];

        // Absolute URL
        $variations[] = $url;
        $variations[] = urldecode($url);
        $variations[] = html_entity_decode($url, ENT_QUOTES, 'UTF-8');

        // Relative URL (from uploads directory)
        $relative = str_replace($this->base_url, '', $url);
        $variations[] = $relative;
        $variations[] = '/' . ltrim($relative, '/');

        // Filename only
        $variations[] = basename($url);

        // URL without protocol
        $variations[] = str_replace(['http://', 'https://'], '//', $url);

        // Encoded URLs
        $variations[] = urlencode($url);
        $variations[] = rawurlencode($url);

        // Path variations (for file system references)
        if ($path) {
            $variations[] = $path;
            $variations[] = str_replace($this->base_path, '', $path);
        }

        // Generated permutations based on filename normalisation
        $permutations = $this->generate_filename_permutations(basename($url));
        if (!empty($permutations)) {
            $dirname_url = trailingslashit(pathinfo($url, PATHINFO_DIRNAME));
            $relative_dir = trailingslashit(str_replace($this->base_url, '', $dirname_url));
            foreach ($permutations as $permutation) {
                $variations[] = $permutation;
                $variations[] = $dirname_url . $permutation;
                $variations[] = $relative_dir . $permutation;
                $variations[] = '/' . ltrim($relative_dir . $permutation, '/');
            }
        }

        return $variations;
    }

    /**
     * Generate WebP variations for existing variations
     */
    private function get_webp_variations($base_variations) {
        $webp_variations = [];

        foreach ($base_variations as $variation) {
            if (empty($variation)) {
                continue;
            }

            // Skip if already a WebP
            if (pathinfo($variation, PATHINFO_EXTENSION) === 'webp') {
                continue;
            }

            // Generate WebP equivalent
            $webp_variation = $this->convert_to_webp_url($variation);
            if ($webp_variation && $webp_variation !== $variation) {
                $webp_variations[] = $webp_variation;
            }
        }

        return $webp_variations;
    }

    /**
     * Convert a URL to its WebP equivalent
     */
    private function convert_to_webp_url($url) {
        $pathinfo = pathinfo($url);

        // Only convert image extensions
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower($pathinfo['extension'] ?? '');

        if (!in_array($ext, $image_extensions)) {
            return null;
        }

        return $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.webp';
    }

    /**
     * Build permutations of a filename to catch CDN/rename variants.
     */
    private function generate_filename_permutations($filename) {
        $results = [];

        if (empty($filename)) {
            return $results;
        }

        $info = pathinfo($filename);
        $name = $info['filename'] ?? $filename;
        $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        $candidates = [];
        $base = $name;

        $candidates[] = $base;
        $candidates[] = strtolower($base);
        $candidates[] = strtoupper($base);
        $candidates[] = $this->strip_wp_resize_suffix($base);
        $candidates[] = $this->strip_numeric_suffix($base);
        $candidates[] = $this->strip_wp_resize_suffix($this->strip_numeric_suffix($base));

        if (!function_exists('sanitize_title')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
        }

        $candidates[] = sanitize_title($base);
        $candidates[] = str_replace(['_', ' '], '-', $base);
        $candidates[] = str_replace(['_', ' '], '', $base);

        $decoded = html_entity_decode($base, ENT_QUOTES, 'UTF-8');
        if ($decoded !== $base) {
            $candidates[] = $decoded;
            $candidates[] = sanitize_title($decoded);
        }

        $candidates = array_filter(array_unique($candidates));

        foreach ($candidates as $candidate) {
            $results[] = $candidate . $extension;
            $results[] = $candidate;
        }

        return array_unique($results);
    }

    private function strip_wp_resize_suffix($name) {
        $stripped = preg_replace('/-(scaled|rotated)(?:-[0-9x]+)*/i', '', $name);
        $stripped = preg_replace('/(-copy)+$/i', '', $stripped);
        return $stripped ?: $name;
    }

    private function strip_numeric_suffix($name) {
        $stripped = preg_replace('/(-\d+)+$/', '', $name);
        return $stripped ?: $name;
    }

    /**
     * Build a search/replace map for safe URL replacement
     */
    public function build_replacement_map($old_attachment_id, $new_attachment_id) {
        $old_variations = $this->get_all_variations($old_attachment_id);
        $new_variations = $this->get_all_variations($new_attachment_id);

        $map = [];

        // Map corresponding variations
        $old_count = count($old_variations);
        $new_count = count($new_variations);

        for ($i = 0; $i < min($old_count, $new_count); $i++) {
            $old = $old_variations[$i];
            $new = $new_variations[$i];

            if ($old && $new && $old !== $new) {
                $map[$old] = $new;
            }
        }

        return $map;
    }

    /**
     * Build replacement map for filename change (same attachment)
     */
    public function build_filename_replacement_map($attachment_id, $old_filename, $new_filename) {
        $map = [];

        // Get current metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        $attached_file = get_attached_file($attachment_id);
        $current_url = wp_get_attachment_url($attachment_id);

        if (!$attached_file || !$current_url) {
            return $map;
        }

        // Calculate old and new URLs
        $old_url = str_replace(basename($current_url), $old_filename, $current_url);
        $new_url = str_replace(basename($current_url), $new_filename, $current_url);

        // Build variations for both old and new
        $old_path = str_replace(basename($attached_file), $old_filename, $attached_file);
        $new_path = str_replace(basename($attached_file), $new_filename, $attached_file);

        $old_variations = $this->get_file_variations($old_url, $old_path);
        $new_variations = $this->get_file_variations($new_url, $new_path);

        // Map variations
        for ($i = 0; $i < min(count($old_variations), count($new_variations)); $i++) {
            $old = $old_variations[$i];
            $new = $new_variations[$i];

            if ($old && $new && $old !== $new) {
                $map[$old] = $new;
            }
        }

        // Handle size variations if metadata exists
        if (is_array($metadata) && !empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $data) {
                if (empty($data['file'])) {
                    continue;
                }

                // Calculate old and new size filenames
                $old_size_basename = $data['file'];
                $ext = pathinfo($old_size_basename, PATHINFO_EXTENSION);
                $size_suffix = '-' . $data['width'] . 'x' . $data['height'] . '.' . $ext;

                $old_size_filename = str_replace('.' . pathinfo($old_filename, PATHINFO_EXTENSION), $size_suffix, pathinfo($old_filename, PATHINFO_FILENAME)) . '.' . $ext;
                $new_size_filename = str_replace('.' . pathinfo($new_filename, PATHINFO_EXTENSION), $size_suffix, pathinfo($new_filename, PATHINFO_FILENAME)) . '.' . $ext;

                $old_size_url = dirname($old_url) . '/' . $old_size_filename;
                $new_size_url = dirname($new_url) . '/' . $new_size_filename;
                $old_size_path = dirname($old_path) . '/' . $old_size_filename;
                $new_size_path = dirname($new_path) . '/' . $new_size_filename;

                $old_size_variations = $this->get_file_variations($old_size_url, $old_size_path);
                $new_size_variations = $this->get_file_variations($new_size_url, $new_size_path);

                for ($j = 0; $j < min(count($old_size_variations), count($new_size_variations)); $j++) {
                    $old_var = $old_size_variations[$j];
                    $new_var = $new_size_variations[$j];

                    if ($old_var && $new_var && $old_var !== $new_var) {
                        $map[$old_var] = $new_var;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Validate a replacement map for safety
     */
    public function validate_replacement_map($map) {
        $errors = [];

        foreach ($map as $old => $new) {
            // Check for empty values
            if (empty($old) || empty($new)) {
                $errors[] = 'Empty URL in replacement map';
                continue;
            }

            // Check for identical values (no-op)
            if ($old === $new) {
                $errors[] = "Identical old and new URL: {$old}";
                continue;
            }

            // Validate URL structure
            if (filter_var($old, FILTER_VALIDATE_URL) === false && !$this->is_relative_path($old)) {
                $errors[] = "Invalid old URL format: {$old}";
            }

            if (filter_var($new, FILTER_VALIDATE_URL) === false && !$this->is_relative_path($new)) {
                $errors[] = "Invalid new URL format: {$new}";
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Check if a string is a relative path
     */
    private function is_relative_path($path) {
        return !empty($path) && (
            strpos($path, '/') === 0 ||
            strpos($path, './') === 0 ||
            strpos($path, '../') === 0 ||
            !preg_match('/^[a-z]+:\/\//', $path)
        );
    }

    /**
     * Get upload directory information
     */
    public function get_upload_info() {
        return [
            'base_url' => $this->base_url,
            'base_path' => $this->base_path,
            'upload_dir' => $this->upload_dir
        ];
    }
}
