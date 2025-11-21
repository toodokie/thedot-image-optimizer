# TinyDot Image Optimizer – Performance and Stability Implementation Plan

**Status**: Ready for Implementation
**Created**: 2025-10-28
**Priority**: CRITICAL - Production-affecting bugs

## Executive Summary

This plan addresses two critical issues discovered on msh-phase6-test site:

1. **11.3 MB transient bloat** - `_transient_msh_content_usage_lookup` storing massive arrays in wp_options, will kill production sites
2. **Cron scheduling failures** - 50-60 second overhead per image due to failed wp_schedule_single_event() calls

**Impact**: Non-AI optimization taking 60 seconds per image instead of 5-10 seconds. Production sites with 1,000+ images could experience 50-200MB transient bloat.

---

## Objectives

1. Restore non-AI optimization to 5–10 seconds per image even when WP-Cron is unhealthy
2. Eliminate the 11.3 MB `_transient_msh_content_usage_lookup` blow-up
3. Move from per-file cleanup to a scalable daily garbage collector
4. Provide clear System Health diagnostics and safe tools for admins
5. Keep behavior safe on multisite and on hosts with or without persistent object cache

---

## Phase 1 – Ship Now (v1.2.2)

### A. Short-circuit broken cron, remove 60s pacing

**Problem**: Each failed `wp_schedule_single_event()` call adds 50+ seconds overhead
**Solution**: Probe once, cache result, skip scheduling if cron is broken

#### Tasks

1. Add cron availability probe with transient backoff in [class-msh-safe-rename-system.php](../../includes/class-msh-safe-rename-system.php)
2. Guard both scheduling locations (after main rename + after backup creation)
3. Tokenize cron args to prevent cron option bloat
4. Add cleanup handler that resolves tokens

#### Code Changes

**Location**: [includes/class-msh-safe-rename-system.php](../../includes/class-msh-safe-rename-system.php)

```php
// Add static property at class top
private static $cron_ok = null;

// Add helper method
private static function cron_is_available(): bool {
    if ( self::$cron_ok !== null ) return self::$cron_ok;
    if ( get_transient('msh_cron_broken') ) {
        self::$cron_ok = false;
        return false;
    }

    $ts = time() + 300;
    $ok = @wp_schedule_single_event($ts, 'msh_cron_probe', []);

    if ( ! $ok ) {
        self::$cron_ok = false;
        set_transient('msh_cron_broken', 1, 10 * MINUTE_IN_SECONDS);
        error_log('TinyDot: WP-Cron unavailable, disabling cleanup scheduling for 10 minutes.');
        return false;
    }

    wp_unschedule_event($ts, 'msh_cron_probe', []);
    self::$cron_ok = true;
    return true;
}

// Add centralized scheduling method
private function schedule_backup_cleanup_for_path($backup_path) {
    // Map long path to short token to avoid cron option bloat
    $token = md5($backup_path);
    update_option('msh_cleanup_map_' . $token, $backup_path, false); // not autoloaded

    if ( self::cron_is_available() ) {
        @wp_schedule_single_event(
            time() + (int)$this->backup_retention,
            'msh_cleanup_rename_backup',
            [ $token ]
        );
    }
}
```

**LOCATION 1 (after main rename)**: Replace existing cron schedule block with:
```php
if ( $backup_path ) {
    $this->schedule_backup_cleanup_for_path($backup_path);
}
```

**LOCATION 2 (after backup creation)**: Replace existing cron schedule block with:
```php
if ( $backup_path ) {
    $this->schedule_backup_cleanup_for_path($backup_path);
}
```

**Global cleanup hook** - Add to main plugin file or bootstrap:
```php
add_action('msh_cleanup_rename_backup', function( $token ) {
    $key = 'msh_cleanup_map_' . sanitize_text_field($token);
    $path = get_option($key);
    if ( $path && is_string($path) && file_exists($path) ) {
        @unlink($path);
    }
    delete_option($key);
}, 10, 1);
```

#### Acceptance Criteria

- [ ] Sick site drops from ~60s to 5–10s per image
- [ ] Exactly one warning in logs per 10 minutes (not hundreds)
- [ ] No growth in `option_name='cron'` from large args
- [ ] PHP lint passes
- [ ] No activation notices

---

### B. Hotfix the 11.3 MB usage lookup transient

**Problem**: `MSH_Content_Usage_Lookup` stores massive arrays in wp_options with no size limits
**Solution**: Purge existing bloat, add size cap, debounce rebuilds

#### Tasks

1. Add one-time purge on upgrade
2. Add purge on deactivation
3. Add 1 MB size cap with filter override
4. Debounce rebuild into single scheduled job
5. Reduce entry size (compact format)

#### Code Changes

**Location 1**: Main plugin bootstrap file

```php
// One-time purge on load
add_action('plugins_loaded', function(){
    if ( get_option('msh_usage_cache_hotfix_done') ) return;

    delete_transient('msh_content_usage_lookup');
    delete_option('_transient_msh_content_usage_lookup');
    delete_option('_transient_timeout_msh_content_usage_lookup');

    update_option('msh_usage_cache_hotfix_done', 1, false);
}, 5);

// Purge on deactivation
register_deactivation_hook(__FILE__, function(){
    delete_transient('msh_content_usage_lookup');
    delete_option('_transient_msh_content_usage_lookup');
    delete_option('_transient_timeout_msh_content_usage_lookup');
});
```

**Location 2**: [includes/class-msh-content-usage-lookup.php](../../includes/class-msh-content-usage-lookup.php) Line 209

Replace:
```php
set_transient( $this->cache_key, $payload, $this->cache_ttl );
```

With:
```php
$serialized = maybe_serialize($payload);
$max = (int) apply_filters('msh_lookup_max_bytes', 1024 * 1024); // 1 MB default

if ( strlen($serialized) > $max ) {
    if ( ! get_transient('msh_lookup_size_warned') ) {
        error_log(
            'TinyDot: content-usage lookup skipped, payload ' .
            round(strlen($serialized)/1048576, 2) . 'MB exceeds ' .
            round($max/1048576, 2) . 'MB cap.'
        );
        set_transient('msh_lookup_size_warned', 1, HOUR_IN_SECONDS);
    }
    // Do not write. Leave existing cache in place or operate without cache.
} else {
    set_transient($this->cache_key, $payload, $this->cache_ttl);
}
```

**Location 3**: Main plugin file - Add debounced rebuild

```php
// Replace immediate rebuild hooks with queue
add_action('save_post',        'msh_queue_lookup_rebuild', 10, 0);
add_action('updated_post_meta','msh_queue_lookup_rebuild', 10, 0);
add_action('updated_option',   'msh_queue_lookup_rebuild', 10, 0);

function msh_queue_lookup_rebuild() {
    if ( get_transient('msh_lookup_rebuild_queued') ) return;
    set_transient('msh_lookup_rebuild_queued', 1, 5 * MINUTE_IN_SECONDS);

    if ( ! wp_next_scheduled('msh_lookup_rebuild') ) {
        wp_schedule_single_event(time() + 2 * MINUTE_IN_SECONDS, 'msh_lookup_rebuild');
    }
}

add_action('msh_lookup_rebuild', function() {
    delete_transient('msh_lookup_rebuild_queued');

    // Trigger the existing lookup builder
    // This will respect the size cap added above
    if ( class_exists('MSH_Content_Usage_Lookup') ) {
        $lookup = MSH_Content_Usage_Lookup::get_instance();
        if ( method_exists($lookup, 'rebuild_cache') ) {
            $lookup->rebuild_cache();
        }
    }
});
```

**Location 4**: [includes/class-msh-content-usage-lookup.php](../../includes/class-msh-content-usage-lookup.php) Lines 94-103

Compact entry format (implement in Phase 3 with custom table):
```php
// OLD FORMAT (too large):
$entry = [
    'url_full'     => $normalized['full'],
    'url_relative' => $normalized['relative'],
    'url_filename' => $normalized['filename'],
    'table'        => $table,
    'row_id'       => (int) $row_id,
    'column'       => $column,
    'context'      => $context,
    'post_type'    => $post_type,
];

// NEW COMPACT FORMAT (for Phase 3):
$entry = [
    'aid'  => (int) $attachment_id,  // Resolve once
    'obj'  => (int) $object_id,      // Post ID or option ID
    'ctx'  => $table . ':' . $column . ':' . $context,  // Colon-joined
    'seen' => time(),
];
```

#### Acceptance Criteria

- [ ] `_transient_msh_content_usage_lookup` does not exist after upgrade
- [ ] No single TinyDot transient or option exceeds 1 MB
- [ ] Saving content does not trigger immediate heavy rebuild
- [ ] One rebuild job queued per 5-minute window
- [ ] Size warning logged only once per hour if cap exceeded

---

## Phase 2 – Next Patch (v1.2.3)

### C. Replace per-file cleanup with daily garbage collector

**Problem**: Scheduling cleanup for every renamed file creates hundreds of cron events
**Solution**: Single daily job that scans and deletes old backups

#### Tasks

1. Schedule daily GC cron job
2. Implement directory-scanning cleanup function
3. Add cleanup for orphaned token map options
4. Keep Phase 1 guard in place for safety

#### Code Changes

**Location**: Main plugin file

```php
// Schedule on activation
register_activation_hook(__FILE__, function() {
    if ( ! wp_next_scheduled('msh_cleanup_gc') ) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'msh_cleanup_gc');
    }
});

// Ensure scheduled on load
add_action('plugins_loaded', function() {
    if ( ! wp_next_scheduled('msh_cleanup_gc') ) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'msh_cleanup_gc');
    }
});

// GC handler
add_action('msh_cleanup_gc', function() {
    $retention = (int) get_option('msh_backup_retention', 7 * DAY_IN_SECONDS);
    msh_delete_backups_older_than($retention);
});

/**
 * Delete backup files older than $retention seconds.
 * Also cleanup orphaned token map options.
 */
function msh_delete_backups_older_than($retention) {
    $dirs = apply_filters('msh_backup_dirs', [
        WP_CONTENT_DIR . '/uploads/msh-rename-backups'
    ]);
    $cutoff = time() - max(0, (int)$retention);

    foreach ($dirs as $dir) {
        if ( ! is_dir($dir) || ! is_readable($dir) ) continue;

        $dh = opendir($dir);
        if ( ! $dh ) continue;

        while ( false !== ($file = readdir($dh)) ) {
            if ( $file === '.' || $file === '..' ) continue;

            $path = $dir . '/' . $file;
            if ( ! is_file($path) ) continue;

            $mtime = @filemtime($path);
            if ( $mtime && $mtime < $cutoff ) {
                @unlink($path);
            }
        }
        closedir($dh);
    }

    // Cleanup orphaned token map options
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE 'msh_cleanup_map_%'
         AND LENGTH(option_value) = 0"
    );
}
```

#### Acceptance Criteria

- [ ] Daily GC scheduled on activation and plugin load
- [ ] Backups older than retention deleted by GC
- [ ] Orphaned token map options cleaned up
- [ ] Manual "run GC now" button works (added in Phase 2D)
- [ ] No per-file cron storms

---

### D. System Health tab and admin notice

**Problem**: Users have no visibility into cron health or database bloat
**Solution**: Admin tab with metrics, warnings, and action buttons

#### Tasks

1. Add System Health section to settings page
2. Show cached metrics (cron status, option sizes)
3. Add admin notice when cron is broken
4. Add action buttons (purge, rebuild, cleanup)

#### Metrics to Display

- Cron status: "Working" or "Broken" (from `msh_cron_broken` transient)
- Size of `option_name='cron'`
- Total autoloaded options size
- Count of options larger than 100 KB
- Backup directory file count and size

#### Code Changes

**Location**: Settings page renderer

```php
echo '<h2 id="system-health">System Health</h2>';

$metrics = msh_get_system_health_metrics(); // cached for 5 min
?>
<table class="widefat">
  <tr>
    <td><strong>WordPress Cron</strong></td>
    <td>
      <?php if ( $metrics['cron_broken'] ): ?>
        <span style="color:#c00">✗ Broken</span>
        <p class="description">Automatic backup cleanup is paused. Optimization will still run.</p>
      <?php else: ?>
        <span style="color:green">✓ Working</span>
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td><strong>Cron option size</strong></td>
    <td><?php echo esc_html( size_format( (int)$metrics['cron_size'] ) ); ?></td>
  </tr>
  <tr>
    <td><strong>Autoload total</strong></td>
    <td>
      <?php echo esc_html( size_format( (int)$metrics['autoload_total'] ) ); ?>
      <?php if ( $metrics['autoload_total'] > 2 * 1024 * 1024 ): ?>
        <span style="color:#c00"> (High - consider database cleanup)</span>
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td><strong>Large options (>100 KB)</strong></td>
    <td><?php echo (int)$metrics['large_options_count']; ?></td>
  </tr>
</table>

<p>
  <a href="<?php echo esc_url( wp_nonce_url(admin_url('options-general.php?page=msh-settings&action=msh_clean_transients'), 'msh_clean_transients') ); ?>"
     class="button">Clean expired transients</a>

  <a href="<?php echo esc_url( wp_nonce_url(admin_url('options-general.php?page=msh-settings&action=msh_run_gc'), 'msh_run_gc') ); ?>"
     class="button">Clean old backups now</a>

  <a href="<?php echo esc_url( wp_nonce_url(admin_url('options-general.php?page=msh-settings&action=msh_purge_usage_cache'), 'msh_purge_usage_cache') ); ?>"
     class="button">Purge usage cache</a>

  <a href="<?php echo esc_url( wp_nonce_url(admin_url('options-general.php?page=msh-settings&action=msh_rebuild_lookup'), 'msh_rebuild_lookup') ); ?>"
     class="button">Rebuild lookup now</a>
</p>

<?php if ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ): ?>
<div class="notice notice-warning inline">
  <p><strong>DISABLE_WP_CRON is enabled.</strong> Make sure you have a server cron job hitting <code>wp-cron.php</code> every 5 minutes.</p>
</div>
<?php endif; ?>
```

**Metrics function**:

```php
function msh_get_system_health_metrics() {
    $cache = get_transient('msh_sys_health');
    if ( is_array($cache) ) return $cache;

    global $wpdb;

    $cron_size = (int) $wpdb->get_var(
        "SELECT LENGTH(option_value) FROM {$wpdb->options} WHERE option_name='cron'"
    );

    $autoload_total = (int) $wpdb->get_var(
        "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'"
    );

    $large_count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->options} WHERE LENGTH(option_value) > 100000"
    );

    $out = [
        'cron_broken'        => (bool) get_transient('msh_cron_broken'),
        'cron_size'          => $cron_size,
        'autoload_total'     => $autoload_total,
        'large_options_count'=> $large_count,
    ];

    set_transient('msh_sys_health', $out, 5 * MINUTE_IN_SECONDS);
    return $out;
}
```

**Action handlers**:

```php
add_action('admin_init', function() {
    if ( ! current_user_can('manage_options') ) return;

    // Clean expired transients
    if ( isset($_GET['action']) && $_GET['action'] === 'msh_clean_transients' ) {
        check_admin_referer('msh_clean_transients');
        msh_delete_expired_transients();
        delete_transient('msh_sys_health');
        wp_safe_redirect( admin_url('options-general.php?page=msh-settings#system-health') );
        exit;
    }

    // Run GC now
    if ( isset($_GET['action']) && $_GET['action'] === 'msh_run_gc' ) {
        check_admin_referer('msh_run_gc');
        msh_delete_backups_older_than(
            (int) get_option('msh_backup_retention', 7 * DAY_IN_SECONDS)
        );
        wp_safe_redirect( admin_url('options-general.php?page=msh-settings#system-health') );
        exit;
    }

    // Purge usage cache
    if ( isset($_GET['action']) && $_GET['action'] === 'msh_purge_usage_cache' ) {
        check_admin_referer('msh_purge_usage_cache');
        delete_transient('msh_content_usage_lookup');
        delete_option('_transient_msh_content_usage_lookup');
        delete_option('_transient_timeout_msh_content_usage_lookup');
        delete_transient('msh_sys_health');
        wp_safe_redirect( admin_url('options-general.php?page=msh-settings#system-health') );
        exit;
    }

    // Rebuild lookup
    if ( isset($_GET['action']) && $_GET['action'] === 'msh_rebuild_lookup' ) {
        check_admin_referer('msh_rebuild_lookup');
        msh_queue_lookup_rebuild();
        wp_safe_redirect( admin_url('options-general.php?page=msh-settings#system-health') );
        exit;
    }
});

function msh_delete_expired_transients() {
    global $wpdb;

    // Delete expired timeout options first
    $deleted = $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_timeout_%'
        AND option_value < UNIX_TIMESTAMP()
        LIMIT 5000
    ");

    return $deleted;
}
```

**Admin notice**:

```php
add_action('admin_notices', function() {
    if ( ! current_user_can('manage_options') ) return;
    if ( ! get_transient('msh_cron_broken') ) return;

    ?>
    <div class="notice notice-warning is-dismissible">
      <p>
        <strong>TinyDot Image Optimizer</strong> detected a WordPress cron problem.
        Optimization will run, but automatic backup cleanup is paused.
      </p>
      <p>
        <a class="button" href="<?php echo esc_url( admin_url('options-general.php?page=msh-settings#system-health') ); ?>">
          Open System Health
        </a>
      </p>
    </div>
    <?php
});
```

#### Acceptance Criteria

- [ ] System Health tab loads quickly (<1s)
- [ ] Metrics cached for 5 minutes
- [ ] All buttons work with nonce protection
- [ ] Admin notice shows when cron broken
- [ ] Notice dismissible and only shown to admins
- [ ] DISABLE_WP_CRON warning shown when applicable

---

### E. WP-CLI helpers

**Problem**: No command-line tools for diagnostics and cleanup
**Solution**: Add WP-CLI commands for db-health checks and manual operations

#### Commands

1. `wp msh db-health [--fix] [--yes]` - Check database health, optionally fix
2. `wp msh usage-cache purge` - Purge usage lookup cache
3. `wp msh backup-gc` - Run backup garbage collector

#### Code Changes

**Location**: Create new file [includes/cli/class-msh-cli-commands.php](includes/cli/class-msh-cli-commands.php)

```php
<?php
if ( ! defined('WP_CLI') || ! WP_CLI ) return;

class MSH_CLI_Command {

    /**
     * Check database health and optionally fix safe items.
     *
     * ## OPTIONS
     * [--fix]    Attempt safe repairs.
     * [--yes]    Assume "yes" to prompts.
     *
     * ## EXAMPLES
     * wp msh db-health
     * wp msh db-health --fix --yes
     */
    public function db_health( $args, $assoc ) {
        global $wpdb;

        WP_CLI::log('=== TinyDot Database Health Check ===');

        // Cron option size
        $cron_size = (int) $wpdb->get_var(
            "SELECT LENGTH(option_value) FROM {$wpdb->options} WHERE option_name='cron'"
        );
        WP_CLI::log("Cron option size: " . size_format($cron_size));

        // Autoload total
        $autoload_total = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload='yes'"
        );
        WP_CLI::log("Autoload total: " . size_format($autoload_total));

        if ( $autoload_total > 2 * 1024 * 1024 ) {
            WP_CLI::warning("Autoload size is high (>2MB). Consider cleaning expired transients.");
        }

        // Expired transients
        $expired = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_timeout_%'
             AND option_value < UNIX_TIMESTAMP()"
        );

        if ( $expired > 0 ) {
            WP_CLI::warning("$expired expired transients found");

            if ( isset($assoc['fix']) ) {
                if ( \WP_CLI\Utils\get_flag_value($assoc, 'yes', false) ||
                     \WP_CLI::confirm('Delete expired transients?', $assoc) ) {

                    $deleted = $wpdb->query(
                        "DELETE FROM {$wpdb->options}
                         WHERE option_name LIKE '_transient_timeout_%'
                         AND option_value < UNIX_TIMESTAMP()"
                    );
                    WP_CLI::success("Deleted $deleted expired transients");
                }
            }
        } else {
            WP_CLI::success("No expired transients found");
        }

        // Run GC if --fix
        if ( isset($assoc['fix']) ) {
            WP_CLI::log("Running backup garbage collector...");
            msh_delete_backups_older_than(
                (int) get_option('msh_backup_retention', 7 * DAY_IN_SECONDS)
            );
            WP_CLI::success('Backup GC completed');
        }

        WP_CLI::success('Health check complete');
    }

    /**
     * Purge the content usage lookup cache.
     *
     * ## EXAMPLES
     * wp msh usage-cache purge
     */
    public function usage_cache( $args, $assoc ) {
        $command = isset($args[0]) ? $args[0] : '';

        if ( $command === 'purge' ) {
            delete_transient('msh_content_usage_lookup');
            delete_option('_transient_msh_content_usage_lookup');
            delete_option('_transient_timeout_msh_content_usage_lookup');
            WP_CLI::success('Usage cache purged');
        } else {
            WP_CLI::error('Unknown subcommand. Use: wp msh usage-cache purge');
        }
    }

    /**
     * Run backup garbage collector.
     *
     * ## EXAMPLES
     * wp msh backup-gc
     */
    public function backup_gc( $args, $assoc ) {
        WP_CLI::log('Running backup garbage collector...');

        $retention = (int) get_option('msh_backup_retention', 7 * DAY_IN_SECONDS);
        msh_delete_backups_older_than($retention);

        WP_CLI::success('Backup GC completed');
    }
}

WP_CLI::add_command('msh', 'MSH_CLI_Command');
```

**Load CLI commands** - Add to main plugin file:

```php
if ( defined('WP_CLI') && WP_CLI ) {
    require_once plugin_dir_path(__FILE__) . 'includes/cli/class-msh-cli-commands.php';
}
```

#### Acceptance Criteria

- [ ] `wp msh db-health` prints metrics
- [ ] `wp msh db-health --fix --yes` deletes expired transients and runs GC
- [ ] `wp msh usage-cache purge` clears cache
- [ ] `wp msh backup-gc` runs cleanup
- [ ] All commands complete in <10 seconds on typical sites
- [ ] Commands work on multisite when run per-site

---

## Phase 3 – Permanent Storage (v1.2.4)

### F. Migrate content usage lookup to custom table

**Problem**: Storing large arrays in wp_options is fundamentally wrong approach
**Solution**: Custom normalized table with indexed queries

#### Tasks

1. Create custom table with dbDelta
2. Create DAO class for CRUD operations
3. Add accessor function for backward compatibility
4. Migrate existing data from transient to table
5. Update all calling code to use accessor
6. Add table to System Health metrics

#### Schema

```sql
CREATE TABLE wp_msh_content_lookup (
  attachment_id BIGINT UNSIGNED NOT NULL,
  object_id     BIGINT UNSIGNED NOT NULL,
  context       VARCHAR(48) NOT NULL,
  last_seen     DATETIME NOT NULL,
  PRIMARY KEY (attachment_id, object_id, context),
  KEY object_ctx (object_id, context),
  KEY seen_idx (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Code Changes

**Location 1**: Create [includes/class-msh-content-lookup-table.php](includes/class-msh-content-lookup-table.php)

```php
<?php
class MSH_Content_Lookup_Table {

    private static $table_name = null;

    public static function get_table_name() {
        if ( self::$table_name === null ) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'msh_content_lookup';
        }
        return self::$table_name;
    }

    /**
     * Create table on activation
     */
    public static function create_table() {
        global $wpdb;
        $table = self::get_table_name();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
            attachment_id BIGINT UNSIGNED NOT NULL,
            object_id     BIGINT UNSIGNED NOT NULL,
            context       VARCHAR(48) NOT NULL,
            last_seen     DATETIME NOT NULL,
            PRIMARY KEY (attachment_id, object_id, context),
            KEY object_ctx (object_id, context),
            KEY seen_idx (last_seen)
        ) {$wpdb->get_charset_collate()};";

        dbDelta($sql);
    }

    /**
     * Insert or update a lookup entry
     */
    public static function upsert($attachment_id, $object_id, $context) {
        global $wpdb;
        $table = self::get_table_name();

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $table (attachment_id, object_id, context, last_seen)
             VALUES (%d, %d, %s, NOW())
             ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)",
            $attachment_id, $object_id, $context
        ));
    }

    /**
     * Get all references for an attachment
     */
    public static function for_attachment($attachment_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT object_id, context, last_seen
             FROM $table
             WHERE attachment_id = %d
             ORDER BY last_seen DESC",
            $attachment_id
        ), ARRAY_A );
    }

    /**
     * Purge entries older than N days
     */
    public static function purge_older_than_days($days) {
        global $wpdb;
        $table = self::get_table_name();

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table
             WHERE last_seen < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        return $deleted;
    }

    /**
     * Truncate table
     */
    public static function truncate() {
        global $wpdb;
        $table = self::get_table_name();
        $wpdb->query("TRUNCATE TABLE $table");
    }

    /**
     * Get table stats
     */
    public static function get_stats() {
        global $wpdb;
        $table = self::get_table_name();

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $size = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT (data_length + index_length)
             FROM information_schema.TABLES
             WHERE table_schema = %s
             AND table_name = %s",
            DB_NAME, $table
        ));

        return [
            'count' => $count,
            'size'  => $size,
        ];
    }
}
```

**Location 2**: Activation hook

```php
register_activation_hook(__FILE__, function() {
    MSH_Content_Lookup_Table::create_table();

    // Schedule GC
    if ( ! wp_next_scheduled('msh_cleanup_gc') ) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'msh_cleanup_gc');
    }

    // Safe housekeeping
    msh_delete_expired_transients();
});
```

**Location 3**: Accessor function for backward compatibility

```php
/**
 * Get content lookup data for an attachment.
 * Provides backward compatibility while moving from transient to table.
 *
 * @param int $attachment_id Attachment ID
 * @return array Array of lookup entries
 */
function msh_get_content_lookup($attachment_id = null) {
    // Use table if available
    if ( class_exists('MSH_Content_Lookup_Table') ) {
        if ( $attachment_id ) {
            return MSH_Content_Lookup_Table::for_attachment($attachment_id);
        }
        // For global lookups, return empty and require per-attachment queries
        return [];
    }

    // Fallback to legacy transient (Phase 1-2)
    $cache = get_transient('msh_content_usage_lookup');
    if ( ! is_array($cache) ) return [];

    // Filter by attachment if specified
    if ( $attachment_id ) {
        return array_filter($cache, function($entry) use ($attachment_id) {
            return isset($entry['attachment_id']) &&
                   $entry['attachment_id'] === $attachment_id;
        });
    }

    return $cache;
}
```

**Location 4**: Migration from transient to table

```php
/**
 * One-time migration from transient to table
 */
add_action('admin_init', function() {
    if ( get_option('msh_lookup_migrated_to_table') ) return;
    if ( ! class_exists('MSH_Content_Lookup_Table') ) return;

    // Check if legacy transient exists
    $legacy = get_transient('msh_content_usage_lookup');
    if ( ! is_array($legacy) || empty($legacy) ) {
        update_option('msh_lookup_migrated_to_table', 1, false);
        return;
    }

    // Migrate in batches of 500
    $batch = array_slice($legacy, 0, 500);
    foreach ($batch as $entry) {
        if ( ! isset($entry['attachment_id'], $entry['object_id'], $entry['context']) ) {
            continue;
        }

        MSH_Content_Lookup_Table::upsert(
            $entry['attachment_id'],
            $entry['object_id'],
            $entry['context']
        );
    }

    // If all migrated, delete legacy and mark done
    if ( count($legacy) <= 500 ) {
        delete_transient('msh_content_usage_lookup');
        delete_option('_transient_msh_content_usage_lookup');
        delete_option('_transient_timeout_msh_content_usage_lookup');
        update_option('msh_lookup_migrated_to_table', 1, false);
    }
}, 20);
```

**Location 5**: Update System Health to show table stats

Add to metrics:
```php
$stats = [];
if ( class_exists('MSH_Content_Lookup_Table') ) {
    $stats = MSH_Content_Lookup_Table::get_stats();
}

// In table display:
<?php if ( ! empty($stats) ): ?>
<tr>
    <td><strong>Lookup table entries</strong></td>
    <td><?php echo number_format($stats['count']); ?> rows</td>
</tr>
<tr>
    <td><strong>Lookup table size</strong></td>
    <td><?php echo size_format($stats['size']); ?></td>
</tr>
<?php endif; ?>
```

#### Acceptance Criteria

- [ ] Table created on activation with correct schema
- [ ] Table uses correct charset and collation
- [ ] CRUD operations work correctly
- [ ] Migration from transient to table completes
- [ ] Legacy transient deleted after migration
- [ ] Per-attachment lookups fast (<100ms)
- [ ] Memory stays flat during operations
- [ ] System Health shows table stats
- [ ] Works on multisite (table per site)

---

## Cross-Cutting Safeguards

### Security

- [ ] All cache writes use `update_option(..., ..., false)` (autoload=no)
- [ ] All admin actions have nonce checks
- [ ] All admin actions check `current_user_can('manage_options')`
- [ ] All redirects use `wp_safe_redirect()`
- [ ] All user input sanitized with `sanitize_text_field()` etc.

### Performance

- [ ] Cron probe rate-limited to once per 10 minutes
- [ ] System Health metrics cached for 5 minutes
- [ ] No probes on AJAX or REST requests
- [ ] Database queries use prepared statements
- [ ] Large operations batched (500 rows max)

### Compatibility

- [ ] Multisite: Table created per-site, uses `$wpdb->prefix`
- [ ] Multisite: Uses `$wpdb->get_charset_collate()` for correct charset
- [ ] DISABLE_WP_CRON: Detected and shown in System Health
- [ ] Object cache: Works with or without persistent cache
- [ ] PHP 7.4+: No PHP 8+ only syntax

### Filters for Extensibility

- [ ] `msh_lookup_max_bytes` - Adjust 1 MB cap
- [ ] `msh_backup_dirs` - Add additional backup directories
- [ ] `msh_backup_retention` - Override default retention

---

## Testing Checklist

### Broken Cron Site

- [ ] Batch 20 images completes in expected 5–10 s per image
- [ ] Exactly ONE warning in logs per 10 minutes
- [ ] No repeated "could_not_set" errors
- [ ] Admin notice shown with link to System Health

### Healthy Cron Site

- [ ] Tokenized cleanup events scheduled successfully
- [ ] `option_name='cron'` size stays stable
- [ ] Backups created successfully
- [ ] Daily GC runs and deletes old backups

### Transient Bloat

- [ ] After upgrade, `_transient_msh_content_usage_lookup` does not exist
- [ ] Saving content does not trigger immediate rebuild
- [ ] One rebuild job queued per 5-minute window
- [ ] Size cap prevents writes over 1 MB
- [ ] Warning logged only once per hour if capped

### System Health

- [ ] Metrics load quickly (<1s)
- [ ] Metrics cached for 5 minutes
- [ ] All buttons work with nonce protection
- [ ] "Clean expired transients" button works
- [ ] "Clean old backups" button works
- [ ] "Purge usage cache" button works
- [ ] "Rebuild lookup" button works

### WP-CLI

- [ ] `wp msh db-health` prints sizes
- [ ] `wp msh db-health --fix --yes` deletes expired transients
- [ ] `wp msh usage-cache purge` clears cache
- [ ] `wp msh backup-gc` runs cleanup
- [ ] All commands safe on multisite

### Custom Table (Phase 3)

- [ ] Table created with dbDelta
- [ ] Reads and writes work
- [ ] Migration from legacy transient completes
- [ ] Legacy transient deleted after migration
- [ ] Per-attachment queries fast
- [ ] Memory stays flat

### Edge Cases

- [ ] DISABLE_WP_CRON = true with no server cron
- [ ] Multisite activation
- [ ] Sites with persistent object cache
- [ ] Sites with 1,000+ images (stress test)
- [ ] Rapid content saves (debounce test)

---

## Rollout Plan

### v1.2.2 (Ship Immediately)

**Scope**: Phase 1A + 1B
**Priority**: CRITICAL

- Cron probe and backoff
- Tokenized cleanup args
- Transient purge hotfix
- 1 MB size cap with filter
- Debounced rebuilds

**Testing**:
- Test on msh-phase6-test site
- Verify 5-10s per image speed
- Confirm no 11MB transient after upgrade

**Release**:
- Create plugin ZIP
- Deploy to msh-phase6-test
- Monitor for 24 hours
- Copy to main-street-health

### v1.2.3 (Next Week)

**Scope**: Phase 2C + 2D + 2E
**Priority**: HIGH

- Daily GC
- System Health tab
- Admin notices
- WP-CLI commands

**Testing**:
- Test all System Health buttons
- Test WP-CLI commands
- Test on multisite
- Test with DISABLE_WP_CRON

### v1.2.4 (Future Release)

**Scope**: Phase 3F
**Priority**: MEDIUM

- Custom table migration
- Behind feature flag initially
- Enable by default after one release cycle if stable

**Testing**:
- Stress test with 5,000+ images
- Test migration from legacy transient
- Test on multisite
- Memory profiling

---

## Files to Modify

### Phase 1 (v1.2.2)

1. [msh-image-optimizer.php](../../msh-image-optimizer.php) - Main plugin file
   - Add hotfix purge on `plugins_loaded`
   - Add deactivation hook purge
   - Add debounced rebuild hooks
   - Add global cleanup handler

2. [includes/class-msh-safe-rename-system.php](../../includes/class-msh-safe-rename-system.php)
   - Add `$cron_ok` static property
   - Add `cron_is_available()` method
   - Add `schedule_backup_cleanup_for_path()` method
   - Replace both scheduling locations

3. [includes/class-msh-content-usage-lookup.php](../../includes/class-msh-content-usage-lookup.php)
   - Add size cap at line 209
   - Remove immediate rebuild hooks (moved to debounced)

### Phase 2 (v1.2.3)

4. [msh-image-optimizer.php](../../msh-image-optimizer.php)
   - Add GC scheduling
   - Add `msh_delete_backups_older_than()` function
   - Add `msh_delete_expired_transients()` function

5. [admin/image-optimizer-admin.php](../../admin/image-optimizer-admin.php) or settings page file
   - Add System Health section
   - Add `msh_get_system_health_metrics()` function
   - Add admin_init action handlers
   - Add admin_notices hook

6. Create [includes/cli/class-msh-cli-commands.php](includes/cli/class-msh-cli-commands.php)
   - New file with WP-CLI commands

### Phase 3 (v1.2.4)

7. Create [includes/class-msh-content-lookup-table.php](includes/class-msh-content-lookup-table.php)
   - New file with table DAO

8. [msh-image-optimizer.php](../../msh-image-optimizer.php)
   - Add activation hook for table creation
   - Add `msh_get_content_lookup()` accessor
   - Add migration from transient to table

---

## Risk Mitigation

### Risk: Missing global lookup during rename

**Mitigation**: Add per-attachment on-demand scan fallback in rename flow

### Risk: Backups accumulate if cron disabled

**Mitigation**: System Health warning + manual "Clean old backups" button

### Risk: Large DELETE operations on busy sites

**Mitigation**: Batch deletes in 500-row chunks, add warnings before manual truncate

### Risk: Other code reading old transient directly

**Mitigation**: Add `msh_get_content_lookup()` accessor, keep temporary fallback

### Risk: Multisite collation mismatches

**Mitigation**: Use `$wpdb->get_charset_collate()`, test on multisite

### Risk: Table creation fails on restrictive hosts

**Mitigation**: Check for errors after dbDelta, log failures, graceful fallback

---

## Success Metrics

### Performance

- Non-AI optimization: 5-10 seconds per image (down from 60s)
- System Health load time: <1 second
- Per-attachment lookup: <100ms

### Stability

- No PHP errors or warnings
- No failed cron scheduling errors in logs
- No transients over 1 MB
- Autoload size stable or decreasing

### User Experience

- Clear admin notices when issues detected
- Working action buttons in System Health
- Helpful WP-CLI commands for developers

---

## Documentation Requirements

1. **TROUBLESHOOTING-SLOW-OPTIMIZATION.md**
   - Symptom: slow optimization
   - Cause: cron/options bloat
   - How to check: System Health tab
   - Plugin tools: buttons and WP-CLI
   - Manual SQL cleanup (with warnings)

2. **README.md updates**
   - New System Health features
   - WP-CLI commands
   - Performance improvements

3. **CHANGELOG.md**
   - Document all changes by version
   - Breaking changes (if any)
   - Migration notes

---

## Questions for Review

1. Should Phase 1 be deployed immediately or wait for full Phase 1+2?
2. Should custom table (Phase 3) be behind a feature flag initially?
3. Should we add a "Protect latest backup for 24h" toggle?
4. Should we add more aggressive cleanup (e.g., auto-delete all transients on activation)?
5. Should we add telemetry to track how many users hit the 1MB cap?

---

## Implementation Priority

**IMMEDIATE (Today)**:
- Phase 1A: Cron probe and backoff
- Phase 1B: Transient hotfix and size cap

**HIGH (This Week)**:
- Phase 2C: Daily GC
- Phase 2D: System Health tab

**MEDIUM (Next Sprint)**:
- Phase 2E: WP-CLI commands
- Phase 3F: Custom table (behind feature flag)

**FUTURE**:
- Phase 3F: Enable custom table by default
- Advanced features (per-attachment cache, etc.)

---

## End of Plan

This plan addresses both the critical 11MB transient bloat and the cron scheduling failures. Implementation should proceed in phases, with Phase 1 deployed immediately to fix the production-killing issues.

**Next Steps**:
1. Review and approve this plan
2. Begin Phase 1A implementation
3. Test on msh-phase6-test site
4. Deploy to production after verification
