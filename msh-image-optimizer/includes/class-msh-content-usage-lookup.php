<?php
/**
 * MSH Content Usage Lookup
 * Builds a content-first map of /uploads/ references across posts, postmeta, and options.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSH_Content_Usage_Lookup {
    private static $instance = null;
    private $cache_key = 'msh_content_usage_lookup';
    private $cache_ttl = DAY_IN_SECONDS;
    private $snapshot_option = 'msh_content_lookup_snapshot';
    private $queue_option = 'msh_content_lookup_queue';
    private $scheduled_hook = 'msh_content_usage_lookup_refresh';
    private $max_postmeta_small = 131072; // 128 KB
    private $option_excerpt_cache = null;
    private $option_excerpt_cache_length = 0;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_hooks']);
        add_action($this->scheduled_hook, [$this, 'handle_scheduled_refresh']);
        add_action('wp_ajax_msh_trigger_incremental_refresh', [$this, 'ajax_trigger_incremental_refresh']);
    }

    public function get_scheduled_hook() {
        return $this->scheduled_hook;
    }

    public function register_hooks() {
        // Re-enabled with proper locking and debouncing to prevent cron job flooding
        add_action('save_post', [$this, 'handle_post_change'], 20, 1);
        add_action('deleted_post', [$this, 'handle_post_change'], 20, 1);
        add_action('added_post_meta', [$this, 'handle_meta_change'], 20, 3);
        add_action('updated_post_meta', [$this, 'handle_meta_change'], 20, 3);
        add_action('deleted_post_meta', [$this, 'handle_meta_change'], 20, 3);
        add_action('updated_option', [$this, 'handle_option_change'], 20, 3);
    }

    public function get_lookup($force = false) {
        $cached = get_transient($this->cache_key);
        if (!$force && is_array($cached)) {
            return $cached;
        }

        return $this->build_lookup(true, ['trigger' => 'cache_miss']);
    }

    public function build_lookup($force = false, array $context = []) {
        if (!$force) {
            $cached = get_transient($this->cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        global $wpdb;

        $started_at = microtime(true);
        $context = array_merge([
            'trigger' => $force ? 'forced' : 'manual',
            'source' => null,
        ], $context);

        $entries = [];
        $unique = [
            'full' => [],
            'relative' => [],
            'filename' => [],
        ];
        $table_counts = [];
        $context_counts = [];

        $record_entry = function ($normalized, $table, $row_id, $column, $context, $post_type = null) use (&$entries, &$unique, &$table_counts, &$context_counts) {
            if (empty($normalized['full']) && empty($normalized['relative']) && empty($normalized['filename'])) {
                return;
            }

            $entries[] = [
                'url_full' => $normalized['full'],
                'url_relative' => $normalized['relative'],
                'url_filename' => $normalized['filename'],
                'table' => $table,
                'row_id' => (int) $row_id,
                'column' => $column,
                'context' => $context,
                'post_type' => $post_type,
            ];

            $table_counts[$table] = isset($table_counts[$table]) ? $table_counts[$table] + 1 : 1;
            $context_counts[$context] = isset($context_counts[$context]) ? $context_counts[$context] + 1 : 1;

            if (!empty($normalized['full'])) {
                $unique['full'][$normalized['full']] = true;
            }
            if (!empty($normalized['relative'])) {
                $unique['relative'][$normalized['relative']] = true;
            }
            if (!empty($normalized['filename'])) {
                $unique['filename'][$normalized['filename']] = true;
            }
        };

        // Posts (content + excerpt)
        $posts = $wpdb->get_results("SELECT ID, post_type, post_content, post_excerpt FROM {$wpdb->posts} WHERE (post_content != '' OR post_excerpt != '') AND post_status IN ('publish','draft','private')");
        foreach ($posts as $post) {
            $content_paths = $this->extract_upload_paths_from_string($post->post_content);
            foreach ($content_paths as $path) {
                $record_entry($path, 'posts', $post->ID, 'post_content', 'content', $post->post_type);
            }

            $excerpt_paths = $this->extract_upload_paths_from_string($post->post_excerpt);
            foreach ($excerpt_paths as $path) {
                $record_entry($path, 'posts', $post->ID, 'post_excerpt', 'excerpt', $post->post_type);
            }
        }

        // Postmeta (<=128 KB)
        $last_meta_id = 0;
        $meta_batch = 50;
        while (true) {
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_id, post_id, meta_key, meta_value\n                 FROM {$wpdb->postmeta}\n                 WHERE meta_id > %d\n                 AND meta_value LIKE '%%/uploads/%%'\n                 AND LENGTH(meta_value) <= %d\n                 ORDER BY meta_id ASC\n                 LIMIT %d",
                $last_meta_id,
                $this->max_postmeta_small,
                $meta_batch
            ));

            if (empty($meta_rows)) {
                break;
            }

            foreach ($meta_rows as $meta) {
                $paths = $this->extract_upload_paths_from_string($meta->meta_value);
                foreach ($paths as $path) {
                    $usage_context = $this->determine_meta_context($meta->meta_key, $meta->meta_value);
                    $record_entry($path, 'postmeta', $meta->meta_id, 'meta_value', $usage_context, null);
                }
                $last_meta_id = $meta->meta_id;
            }
        }

        // Postmeta (>128 KB) streamed excerpts
        $large_threshold = $this->max_postmeta_small;
        $large_batch = 10;
        $last_large_meta = 0;
        while (true) {
            $large_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_id, post_id, meta_key, SUBSTRING(meta_value, 1, %d) AS meta_excerpt\n                 FROM {$wpdb->postmeta}\n                 WHERE meta_id > %d\n                 AND meta_value LIKE '%%/uploads/%%'\n                 AND LENGTH(meta_value) > %d\n                 ORDER BY meta_id ASC\n                 LIMIT %d",
                $large_threshold * 2,
                $last_large_meta,
                $large_threshold,
                $large_batch
            ));

            if (empty($large_rows)) {
                break;
            }

            foreach ($large_rows as $meta) {
                $paths = $this->extract_upload_paths_from_string($meta->meta_excerpt);
                foreach ($paths as $path) {
                    $usage_context = $this->determine_meta_context($meta->meta_key, $meta->meta_excerpt);
                    $record_entry($path, 'postmeta', $meta->meta_id, 'meta_value', $usage_context, null);
                }
                $last_large_meta = $meta->meta_id;
            }
        }

        // Options (reuse cached excerpts)
        $options = $this->get_option_excerpts($large_threshold * 2);
        foreach ($options as $option) {
            $paths = $this->extract_upload_paths_from_string($option['option_excerpt']);
            foreach ($paths as $path) {
                $usage_context = $this->determine_option_context($option['option_name'], $option['option_excerpt']);
                $record_entry($path, 'options', $option['option_id'], 'option_value', $usage_context, null);
            }
        }

        $payload = [
            'generated_at' => current_time('mysql'),
            'entries' => $entries,
            'unique' => $unique,
            'table_counts' => $table_counts,
            'context_counts' => $context_counts,
        ];

        set_transient($this->cache_key, $payload, $this->cache_ttl);

        $snapshot = [
            'generated_at' => $payload['generated_at'],
            'entry_count' => count($entries),
            'unique_counts' => [
                'full' => count($unique['full']),
                'relative' => count($unique['relative']),
                'filename' => count($unique['filename']),
            ],
            'table_counts' => $table_counts,
            'context_counts' => $context_counts,
            'duration_ms' => (int) round((microtime(true) - $started_at) * 1000),
            'force' => (bool) $force,
            'trigger' => $context['trigger'],
            'source' => $context['source'],
        ];

        update_option($this->snapshot_option, $snapshot, false);

        return $payload;
    }

    public function handle_post_change($post_id) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        $this->queue_lookup_refresh();
    }

    public function handle_meta_change($meta_id = 0, $object_id = 0, $meta_key = '') {
        $this->queue_lookup_refresh();
    }

    public function handle_option_change($option, $old_value, $value) {
        // Only queue if either old or new values reference uploads to reduce noise
        $serialized = maybe_serialize($value);
        if (is_string($serialized) && strpos($serialized, 'uploads/') === false) {
            $serialized_old = maybe_serialize($old_value);
            if (!is_string($serialized_old) || strpos($serialized_old, 'uploads/') === false) {
                return;
            }
        }
        $this->queue_lookup_refresh();
    }

    public function handle_scheduled_refresh() {
        $lookup = $this->build_lookup(true, [
            'trigger' => 'scheduler',
            'source' => 'scheduled_refresh',
        ]);
        MSH_Image_Usage_Index::get_instance()->build_optimized_complete_index(true, $lookup, [
            'trigger' => 'scheduler',
            'source' => 'scheduled_refresh',
        ]);
    }

    public function queue_lookup_refresh($delay = 60) {
        // Debouncing: Don't queue if we recently queued (within 5 minutes)
        $debounce_key = 'msh_lookup_queue_debounce';
        $last_queue_time = get_transient($debounce_key);
        if ($last_queue_time !== false) {
            // Already queued recently, skip to prevent flooding
            return false;
        }

        // Transient-based locking to prevent race conditions
        $lock_key = 'msh_lookup_queue_lock';
        $lock_acquired = set_transient($lock_key, time(), 10); // 10 second lock

        if (!$lock_acquired) {
            // Another process is scheduling right now, skip
            return false;
        }

        $delay = max(0, (int) $delay);
        $requested_time = time() + $delay;
        $scheduled_time = null;

        try {
            if (function_exists('as_next_scheduled_action') && function_exists('as_schedule_single_action')) {
                $scheduled_time = as_next_scheduled_action($this->scheduled_hook);
                if (!$scheduled_time) {
                    as_schedule_single_action($requested_time, $this->scheduled_hook);
                    $scheduled_time = $requested_time;
                }
            } else {
                $scheduled_time = wp_next_scheduled($this->scheduled_hook);
                if (!$scheduled_time) {
                    wp_schedule_single_event($requested_time, $this->scheduled_hook);
                    $scheduled_time = $requested_time;
                }
            }

            if (!$scheduled_time) {
                $scheduled_time = $requested_time;
            }

            $scheduled_gmt = gmdate('Y-m-d H:i:s', $scheduled_time);
            $queue_payload = [
                'queued_at' => current_time('mysql'),
                'scheduled_for_gmt' => $scheduled_gmt,
                'scheduled_for_local' => function_exists('get_date_from_gmt') ? get_date_from_gmt($scheduled_gmt, 'Y-m-d H:i:s') : $scheduled_gmt,
                'scheduled_timestamp' => (int) $scheduled_time,
                'delay' => $delay,
            ];

            update_option($this->queue_option, $queue_payload, false);

            // Set debounce to prevent re-queuing for 5 minutes
            set_transient($debounce_key, time(), 5 * MINUTE_IN_SECONDS);

            return $scheduled_time;
        } finally {
            // Always release the lock
            delete_transient($lock_key);
        }
    }

    private function extract_upload_paths_from_string($content) {
        $results = [];

        if (empty($content) || stripos($content, 'uploads') === false) {
            return $results;
        }

        $pattern = '#(?P<full>(?:https?:\/\/[^"\'"<>\s]+)?\/?wp-content\/uploads\/[^"\'"<>\s\?]+)#i';
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches['full'] as $full) {
                $normalized = $this->normalize_upload_path($full);
                $results[$normalized['key']] = $normalized;
            }
        }

        return array_values($results);
    }

    private function normalize_upload_path($path) {
        $full = trim($path);
        $query_pos = strpos($full, '?');
        if ($query_pos !== false) {
            $full = substr($full, 0, $query_pos);
        }

        $lower = strtolower($full);
        $relative = '';

        if (($pos = strpos($lower, '/wp-content/uploads/')) !== false) {
            $relative = substr($lower, $pos + strlen('/wp-content/uploads/'));
        } elseif (($pos = strpos($lower, 'wp-content/uploads/')) !== false) {
            $relative = substr($lower, $pos + strlen('wp-content/uploads/'));
        }

        $relative = ltrim($relative, '/');
        $filename = $relative !== '' ? basename($relative) : basename($lower);

        return [
            'key' => md5($full),
            'full' => $lower,
            'relative' => $relative,
            'filename' => $filename ? strtolower($filename) : '',
        ];
    }

    private function get_option_excerpts($excerpt_length = 262144) {
        if (is_array($this->option_excerpt_cache) && $this->option_excerpt_cache_length >= $excerpt_length) {
            return $this->option_excerpt_cache;
        }

        global $wpdb;

        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_id, option_name, SUBSTRING(option_value, 1, %d) AS option_excerpt\n             FROM {$wpdb->options}\n             WHERE option_value LIKE '%/uploads/%'",
            $excerpt_length
        ));

        $prepared = [];
        foreach ($options as $option) {
            $prepared[] = [
                'option_id' => (int) $option->option_id,
                'option_name' => $option->option_name,
                'option_excerpt' => $option->option_excerpt,
            ];
        }

        $this->option_excerpt_cache = $prepared;
        $this->option_excerpt_cache_length = $excerpt_length;

        return $this->option_excerpt_cache;
    }

    private function determine_meta_context($meta_key, $meta_value) {
        if ($meta_key === '_thumbnail_id') {
            return 'featured_image';
        }

        if (strpos($meta_key, 'field_') === 0 || function_exists('get_field_object')) {
            return 'acf_field';
        }

        if (strpos($meta_key, 'gallery') !== false || strpos($meta_key, '_gallery') !== false) {
            return 'gallery';
        }

        if (strpos($meta_key, '_elementor_data') !== false || strpos($meta_key, 'vc_') === 0) {
            return 'page_builder';
        }

        if (is_serialized($meta_value)) {
            return 'serialized_meta';
        }

        return 'meta';
    }

    private function determine_option_context($option_name, $option_value) {
        if (strpos($option_name, 'theme_') === 0 || strpos($option_name, 'mods_') === 0) {
            return 'theme_options';
        }

        if (strpos($option_name, 'widget_') === 0) {
            return 'widget';
        }

        if (strpos($option_name, 'customize_') === 0) {
            return 'customizer';
        }

        if (is_serialized($option_value)) {
            return 'serialized_option';
        }

        return 'option';
    }

    public function ajax_trigger_incremental_refresh() {
        check_ajax_referer('msh_image_optimizer', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        try {
            $this->queue_lookup_refresh(60);

            $snapshot = get_option($this->snapshot_option, []);
            $pending_jobs = isset($snapshot['scheduler']['pending_jobs']) ? (int) $snapshot['scheduler']['pending_jobs'] : 1;

            wp_send_json_success([
                'message' => 'Incremental refresh queued successfully',
                'pending_jobs' => $pending_jobs + 1,
                'scheduled_for' => $snapshot['scheduler']['next_run_display'] ?? 'Next cron cycle'
            ]);
        } catch (Exception $e) {
            error_log('MSH Incremental Refresh: ' . $e->getMessage());
            wp_send_json_error('Failed to queue refresh: ' . $e->getMessage());
        }
    }
}
