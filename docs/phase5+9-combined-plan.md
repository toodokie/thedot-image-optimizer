# Phase 5+9 Combined Plan
## "Enterprise & Marketplace Release"

**Status:** 🎯 Ready for Development
**Dependencies:** ✅ Phase 4R+ Complete
**Estimated Timeline:** 6 weeks (3 parallel tracks)
**Target Version:** v2.0.0 (Enterprise-Grade)
**Last Updated:** October 19, 2025

---

## Strategic Rationale

### Why Combine Phase 5 + Phase 9?

**1. Shared Prerequisites**
- Both rely on Phase 4R+ metadata brain (cache, events, fingerprints)
- Both need metrics tables, queue system, and event logging
- Building together avoids two passes on same code paths

**2. Developer Momentum**
- Dev agents already deep in database schema, CLI, REST, events
- Licensing, telemetry, onboarding are lightweight add-ons now vs. months later

**3. Cohesive Release Narrative**
Launch publicly with one powerful story:

> "Enterprise-grade optimizer with full automation, analytics, and Pro-ready architecture."

Unifies positioning for:
- WordPress.org (free tier)
- Agencies (multi-site deployments)
- Direct enterprise clients (custom integration)

**4. Cost and Maintenance Efficiency**
- One round of code review
- One documentation set
- One QA cycle
- Clean version jump (v2.0.0 = enterprise-ready)

---

## Core Objectives

### From Phase 5 (Automation Infrastructure)
✅ Job engine + workers + retry/backoff
✅ Automation triggers (upload, publish, locale updates)
✅ Optimizer Hub dashboard (Cache, History, Queue, Events, Sync)
✅ REST + CLI parity
✅ Metrics tables (daily/hourly)

### From Phase 9 (Enterprise & Marketplace)
✅ Licensing system with plan gating
✅ Telemetry & analytics dashboard
✅ Onboarding wizard
✅ Remote sync (S3/Supabase)
✅ WordPress.org compliance
✅ Marketplace packaging

---

## Architecture: 3 Parallel Tracks

### Track A: Automation Infrastructure
**Developer:** AI #1
**Estimated Lines:** ~3,200 lines
**Timeline:** Week 1-4

**New Files:**
1. `includes/automation/class-msh-job-engine.php` (~600 lines)
2. `includes/automation/class-msh-regeneration-worker.php` (~550 lines)
3. `includes/automation/class-msh-queue-manager.php` (~450 lines)
4. `includes/automation/class-msh-automation-triggers.php` (~400 lines)
5. `includes/automation/class-msh-batch-processor.php` (~400 lines)
6. `includes/automation/class-msh-retry-handler.php` (~300 lines)
7. `includes/class-msh-automation-cli.php` (~500 lines)

**Modified Files:**
1. `msh-image-optimizer.php` - Initialize automation classes + cron hooks
2. `includes/phase4/class-msh-staleness-engine.php` - Hook into job engine

---

### Track B: Hub UI & REST API
**Developer:** AI #2
**Estimated Lines:** ~3,800 lines
**Timeline:** Week 1-4

**New Files:**
1. `admin/class-msh-hub-page.php` (~700 lines) - Main Hub controller
2. `admin/tabs/class-msh-hub-cache-tab.php` (~450 lines)
3. `admin/tabs/class-msh-hub-history-tab.php` (~400 lines)
4. `admin/tabs/class-msh-hub-queue-tab.php` (~350 lines)
5. `admin/tabs/class-msh-hub-events-tab.php` (~300 lines)
6. `admin/tabs/class-msh-hub-sync-tab.php` (~250 lines)
7. `includes/rest/class-msh-rest-metadata.php` (~500 lines)
8. `includes/rest/class-msh-rest-queue.php` (~350 lines)
9. `assets/css/hub.css` (~400 lines)
10. `assets/js/hub.js` (~600 lines)

**Modified Files:**
1. `includes/class-msh-optimizer-menu.php` - Add Hub menu item

---

### Track C: Enterprise Features
**Developer:** AI #3
**Estimated Lines:** ~2,800 lines
**Timeline:** Week 3-6 (starts after Track A/B foundation)

**New Files:**
1. `includes/enterprise/class-msh-license-manager.php` (~550 lines)
2. `includes/enterprise/class-msh-telemetry.php` (~450 lines)
3. `includes/enterprise/class-msh-onboarding-wizard.php` (~500 lines)
4. `includes/enterprise/class-msh-remote-sync.php` (~600 lines)
5. `includes/enterprise/class-msh-plan-gating.php` (~300 lines)
6. `admin/class-msh-license-settings.php` (~400 lines)

**Modified Files:**
1. `msh-image-optimizer.php` - Initialize enterprise features
2. `readme.txt` - WordPress.org compliance

---

## Track A: Automation Infrastructure (Detailed)

### File 1: `includes/automation/class-msh-job-engine.php`

**Purpose:** Core job processing engine with retry/backoff

**Key Features:**
- Job status tracking (pending, processing, complete, failed)
- Retry logic with exponential backoff
- Worker pool management
- Health monitoring
- WP-Cron integration

**Database Schema Addition:**

```sql
CREATE TABLE {$wpdb->prefix}msh_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    payload LONGTEXT,
    priority ENUM('high', 'medium', 'normal') DEFAULT 'normal',
    status ENUM('pending', 'processing', 'complete', 'failed') DEFAULT 'pending',
    attempts TINYINT UNSIGNED DEFAULT 0,
    max_attempts TINYINT UNSIGNED DEFAULT 3,
    next_retry_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_status_priority (status, priority, created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_retry (next_retry_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Core Functions:**

```php
class MSH_Job_Engine {

    /**
     * Enqueue a job
     *
     * @param string $job_type Job type (e.g., 'regenerate_metadata')
     * @param string $entity_type Entity type (e.g., 'attachment')
     * @param int $entity_id Entity ID
     * @param array $payload Job data
     * @param string $priority Priority level
     * @return int|WP_Error Job ID or error
     */
    public function enqueue( $job_type, $entity_type, $entity_id, $payload = array(), $priority = 'normal' ) {
        global $wpdb;

        // Check for duplicate (idempotency)
        $existing = $this->find_duplicate( $job_type, $entity_type, $entity_id );
        if ( $existing && in_array( $existing->status, array( 'pending', 'processing' ), true ) ) {
            return $existing->id; // Return existing job
        }

        // Insert new job
        $wpdb->insert(
            $this->jobs_table,
            array(
                'job_type'    => $job_type,
                'entity_type' => $entity_type,
                'entity_id'   => $entity_id,
                'payload'     => wp_json_encode( $payload ),
                'priority'    => $priority,
                'status'      => 'pending',
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Process next batch of jobs
     *
     * @param int $batch_size Batch size
     * @param int $timeout Max execution time in seconds
     * @return array Processing results
     */
    public function process_batch( $batch_size = 10, $timeout = 300 ) {
        $start_time = time();
        $results = array( 'processed' => 0, 'failed' => 0, 'skipped' => 0 );

        // Get jobs prioritized
        $jobs = $this->get_next_batch( $batch_size );

        foreach ( $jobs as $job ) {
            // Check timeout
            if ( ( time() - $start_time ) > $timeout ) {
                break;
            }

            // Mark as processing
            $this->update_status( $job->id, 'processing', array(
                'started_at' => current_time( 'mysql' ),
            ) );

            // Process job
            $result = $this->process_job( $job );

            if ( is_wp_error( $result ) ) {
                $this->handle_failure( $job, $result );
                $results['failed']++;
            } else {
                $this->update_status( $job->id, 'complete', array(
                    'completed_at' => current_time( 'mysql' ),
                ) );
                $results['processed']++;
            }
        }

        return $results;
    }

    /**
     * Handle job failure with retry logic
     *
     * @param object $job Job data
     * @param WP_Error $error Error details
     */
    private function handle_failure( $job, $error ) {
        global $wpdb;

        $attempts = $job->attempts + 1;

        if ( $attempts >= $job->max_attempts ) {
            // Max retries exceeded - mark as failed
            $this->update_status( $job->id, 'failed', array(
                'attempts'      => $attempts,
                'error_message' => $error->get_error_message(),
                'completed_at'  => current_time( 'mysql' ),
            ) );
        } else {
            // Schedule retry with exponential backoff
            $backoff_seconds = pow( 2, $attempts ) * 60; // 2^n minutes
            $next_retry = gmdate( 'Y-m-d H:i:s', time() + $backoff_seconds );

            $this->update_status( $job->id, 'pending', array(
                'attempts'      => $attempts,
                'next_retry_at' => $next_retry,
                'error_message' => $error->get_error_message(),
            ) );
        }

        // Emit telemetry event
        do_action( 'msh_job_failed', $job, $error );
    }

    /**
     * Get next batch of jobs (prioritized)
     *
     * @param int $batch_size Batch size
     * @return array Jobs
     */
    private function get_next_batch( $batch_size ) {
        global $wpdb;

        // Get jobs that are:
        // 1. Pending
        // 2. Not scheduled for future retry
        // 3. Ordered by priority then created_at
        $jobs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->jobs_table}
            WHERE status = 'pending'
            AND (next_retry_at IS NULL OR next_retry_at <= %s)
            ORDER BY
                CASE priority
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    ELSE 3
                END,
                created_at ASC
            LIMIT %d",
            current_time( 'mysql' ),
            $batch_size
        ) );

        return $jobs;
    }
}
```

---

### File 2: `includes/automation/class-msh-automation-triggers.php`

**Purpose:** Hook into WordPress events and auto-enqueue jobs

**Triggers:**

```php
class MSH_Automation_Triggers {

    /**
     * Hook into WordPress
     */
    public function __construct() {
        // Image upload
        add_action( 'add_attachment', array( $this, 'on_image_uploaded' ) );

        // Post published (affects image context)
        add_action( 'publish_post', array( $this, 'on_post_published' ) );
        add_action( 'save_post', array( $this, 'on_post_saved' ), 10, 2 );

        // Locale profile updated (Phase 3)
        add_action( 'msh_locale_profile_updated', array( $this, 'on_locale_updated' ) );

        // Glossary updated (Phase 3)
        add_action( 'msh_glossary_updated', array( $this, 'on_glossary_updated' ) );

        // Manual edit (mark stale, then regenerate AI in background)
        add_action( 'msh_metadata_manual_edit', array( $this, 'on_manual_edit' ), 10, 4 );
    }

    /**
     * Handle image upload
     *
     * @param int $attachment_id Attachment ID
     */
    public function on_image_uploaded( $attachment_id ) {
        // Only process images
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }

        // Get enabled locales
        $locales = $this->get_enabled_locales();

        // Enqueue jobs for all fields in all locales
        foreach ( $locales as $locale ) {
            foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) {
                MSH_Job_Engine::get_instance()->enqueue(
                    'generate_metadata',
                    'attachment',
                    $attachment_id,
                    array(
                        'locale' => $locale,
                        'field'  => $field,
                        'reason' => 'image_uploaded',
                    ),
                    'normal'
                );
            }
        }

        // Emit telemetry
        do_action( 'msh_automation_triggered', 'image_uploaded', $attachment_id );
    }

    /**
     * Handle post published
     *
     * @param int $post_id Post ID
     */
    public function on_post_published( $post_id ) {
        // Get all images in post
        $images = $this->get_post_images( $post_id );

        if ( empty( $images ) ) {
            return;
        }

        // Get enabled locales
        $locales = $this->get_enabled_locales();

        // Mark metadata as stale for all images
        foreach ( $images as $attachment_id ) {
            foreach ( $locales as $locale ) {
                foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) {
                    // Enqueue regeneration job
                    MSH_Job_Engine::get_instance()->enqueue(
                        'regenerate_metadata',
                        'attachment',
                        $attachment_id,
                        array(
                            'locale' => $locale,
                            'field'  => $field,
                            'reason' => 'context_changed',
                        ),
                        'normal'
                    );
                }
            }
        }

        // Emit telemetry
        do_action( 'msh_automation_triggered', 'post_published', $post_id, count( $images ) );
    }

    /**
     * Handle glossary updated
     *
     * @param string $locale Locale code
     */
    public function on_glossary_updated( $locale ) {
        // Get all images
        $images = $this->get_all_images();

        // Enqueue regeneration for all images in this locale
        foreach ( $images as $attachment_id ) {
            foreach ( array( 'title', 'alt', 'caption', 'description' ) as $field ) {
                MSH_Job_Engine::get_instance()->enqueue(
                    'regenerate_metadata',
                    'attachment',
                    $attachment_id,
                    array(
                        'locale' => $locale,
                        'field'  => $field,
                        'reason' => 'glossary_changed',
                    ),
                    'medium' // Higher priority than normal context changes
                );
            }
        }

        // Emit telemetry
        do_action( 'msh_automation_triggered', 'glossary_updated', $locale, count( $images ) );
    }
}
```

---

## Track B: Hub UI & REST API (Detailed)

### File 1: `admin/class-msh-hub-page.php`

**Purpose:** Main "Optimizer Hub" admin page

**Tab Structure:**

```php
class MSH_Hub_Page {

    private $tabs = array();

    public function __construct() {
        $this->register_tabs();
        $this->hooks();
    }

    /**
     * Register all tabs
     */
    private function register_tabs() {
        $this->tabs = array(
            'cache' => array(
                'label'    => __( 'Cache', 'msh-image-optimizer' ),
                'callback' => array( 'MSH_Hub_Cache_Tab', 'render' ),
                'icon'     => 'dashicons-database-view',
            ),
            'history' => array(
                'label'    => __( 'History', 'msh-image-optimizer' ),
                'callback' => array( 'MSH_Hub_History_Tab', 'render' ),
                'icon'     => 'dashicons-backup',
            ),
            'queue' => array(
                'label'    => __( 'Queue', 'msh-image-optimizer' ),
                'callback' => array( 'MSH_Hub_Queue_Tab', 'render' ),
                'icon'     => 'dashicons-update',
            ),
            'events' => array(
                'label'    => __( 'Events', 'msh-image-optimizer' ),
                'callback' => array( 'MSH_Hub_Events_Tab', 'render' ),
                'icon'     => 'dashicons-admin-generic',
            ),
            'sync' => array(
                'label'    => __( 'Sync', 'msh-image-optimizer' ),
                'callback' => array( 'MSH_Hub_Sync_Tab', 'render' ),
                'icon'     => 'dashicons-cloud',
                'pro_only' => true,
            ),
        );

        // Allow extensions to add tabs
        $this->tabs = apply_filters( 'msh_hub_tabs', $this->tabs );
    }

    /**
     * Render main page
     */
    public function render_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'cache';

        if ( ! isset( $this->tabs[ $current_tab ] ) ) {
            $current_tab = 'cache';
        }

        $tab_data = $this->tabs[ $current_tab ];

        // Check Pro feature access
        if ( ! empty( $tab_data['pro_only'] ) && ! msh_is_pro_active() ) {
            $this->render_pro_upsell();
            return;
        }

        // Render
        ?>
        <div class="wrap msh-hub-page">
            <h1><?php esc_html_e( 'Optimizer Hub', 'msh-image-optimizer' ); ?></h1>

            <?php $this->render_nav_tabs( $current_tab ); ?>

            <div class="msh-tab-content">
                <?php
                if ( is_callable( $tab_data['callback'] ) ) {
                    call_user_func( $tab_data['callback'] );
                }
                ?>
            </div>
        </div>
        <?php
    }
}
```

---

### File 2: `includes/rest/class-msh-rest-metadata.php`

**Purpose:** REST API endpoints for metadata operations

**Endpoints:**

```php
class MSH_REST_Metadata {

    private $namespace = 'msh/v1';

    public function register_routes() {
        // Get metadata cache
        register_rest_route( $this->namespace, '/metadata/cache', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cache' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args'                => array(
                'locale'     => array( 'type' => 'string' ),
                'staleness'  => array( 'type' => 'string' ),
                'source'     => array( 'type' => 'string' ),
                'search'     => array( 'type' => 'string' ),
                'page'       => array( 'type' => 'integer', 'default' => 1 ),
                'per_page'   => array( 'type' => 'integer', 'default' => 50 ),
            ),
        ) );

        // Get single metadata entry
        register_rest_route( $this->namespace, '/metadata/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_metadata' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );

        // Switch source (AI vs Manual)
        register_rest_route( $this->namespace, '/metadata/(?P<id>\d+)/source', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'switch_source' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args'                => array(
                'source' => array(
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => array( 'ai', 'manual' ),
                ),
            ),
        ) );

        // Bulk regenerate
        register_rest_route( $this->namespace, '/metadata/regenerate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'bulk_regenerate' ),
            'permission_callback' => array( $this, 'check_permissions' ),
            'args'                => array(
                'ids' => array(
                    'required' => true,
                    'type'     => 'array',
                    'items'    => array( 'type' => 'integer' ),
                ),
            ),
        ) );

        // Export to CSV
        register_rest_route( $this->namespace, '/metadata/export', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'export_csv' ),
            'permission_callback' => array( $this, 'check_permissions' ),
        ) );
    }

    /**
     * Get cache entries
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function get_cache( $request ) {
        $filters = array(
            'locale'     => $request->get_param( 'locale' ),
            'staleness'  => $request->get_param( 'staleness' ),
            'source'     => $request->get_param( 'source' ),
            'search'     => $request->get_param( 'search' ),
            'page'       => $request->get_param( 'page' ),
            'per_page'   => $request->get_param( 'per_page' ),
        );

        $results = MSH_Metadata_Core::get_instance()->query_cache( $filters );

        return rest_ensure_response( $results );
    }

    /**
     * Bulk regenerate metadata
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function bulk_regenerate( $request ) {
        $ids = $request->get_param( 'ids' );

        $enqueued = 0;

        foreach ( $ids as $cache_id ) {
            $cache = MSH_Metadata_Core::get_instance()->get_cache_by_id( $cache_id );

            if ( ! $cache ) {
                continue;
            }

            // Enqueue regeneration job
            $job_id = MSH_Job_Engine::get_instance()->enqueue(
                'regenerate_metadata',
                'attachment',
                $cache['attachment_id'],
                array(
                    'locale' => $cache['locale'],
                    'field'  => $cache['field'],
                    'reason' => 'manual_bulk',
                ),
                'high' // User-initiated = high priority
            );

            if ( $job_id ) {
                $enqueued++;
            }
        }

        return rest_ensure_response( array(
            'enqueued' => $enqueued,
            'total'    => count( $ids ),
        ) );
    }
}
```

---

## Track C: Enterprise Features (Detailed)

### File 1: `includes/enterprise/class-msh-license-manager.php`

**Purpose:** Licensing system with Lemon Squeezy integration

**Plan Tiers:**

```php
class MSH_License_Manager {

    const PLAN_FREE    = 'free';
    const PLAN_PRO     = 'pro';
    const PLAN_AGENCY  = 'agency';

    /**
     * Activate license
     *
     * @param string $license_key License key
     * @return true|WP_Error
     */
    public function activate( $license_key ) {
        // Validate format
        if ( ! $this->is_valid_format( $license_key ) ) {
            return new WP_Error( 'invalid_format', __( 'Invalid license key format', 'msh-image-optimizer' ) );
        }

        // Call Lemon Squeezy API
        $response = wp_remote_post( 'https://api.lemonsqueezy.com/v1/licenses/activate', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'license_key'  => $license_key,
                'instance_url' => home_url(),
            ) ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['activated'] ) ) {
            return new WP_Error( 'activation_failed', $body['error'] ?? __( 'Activation failed', 'msh-image-optimizer' ) );
        }

        // Store locally
        update_option( 'msh_license_key', $license_key );
        update_option( 'msh_license_data', array(
            'plan'       => $body['plan'],
            'expires_at' => $body['expires_at'],
            'activated_at' => current_time( 'mysql' ),
        ) );

        // Emit telemetry
        do_action( 'msh_license_activated', $body['plan'] );

        return true;
    }

    /**
     * Check if feature is enabled for current plan
     *
     * @param string $feature Feature key
     * @return bool
     */
    public function is_feature_enabled( $feature ) {
        $plan = $this->get_active_plan();

        $feature_map = array(
            'cloud_sync'         => array( self::PLAN_PRO, self::PLAN_AGENCY ),
            'remote_deploy'      => array( self::PLAN_AGENCY ),
            'analytics_api'      => array( self::PLAN_AGENCY ),
            'multi_locale_auto'  => array( self::PLAN_PRO, self::PLAN_AGENCY ),
            'priority_support'   => array( self::PLAN_PRO, self::PLAN_AGENCY ),
        );

        if ( ! isset( $feature_map[ $feature ] ) ) {
            return true; // Free feature
        }

        return in_array( $plan, $feature_map[ $feature ], true );
    }

    /**
     * Get active plan
     *
     * @return string Plan tier
     */
    public function get_active_plan() {
        $license_data = get_option( 'msh_license_data', array() );

        if ( empty( $license_data['plan'] ) ) {
            return self::PLAN_FREE;
        }

        // Check expiration
        if ( ! empty( $license_data['expires_at'] ) ) {
            $expires = strtotime( $license_data['expires_at'] );
            if ( $expires < time() ) {
                return self::PLAN_FREE; // Expired
            }
        }

        return $license_data['plan'];
    }
}
```

**Helper Function:**

```php
/**
 * Check if Pro feature is enabled
 *
 * @param string $feature Feature key
 * @return bool
 */
function msh_is_pro_feature_enabled( $feature ) {
    return MSH_License_Manager::get_instance()->is_feature_enabled( $feature );
}

/**
 * Check if any Pro plan is active
 *
 * @return bool
 */
function msh_is_pro_active() {
    $plan = MSH_License_Manager::get_instance()->get_active_plan();
    return in_array( $plan, array( MSH_License_Manager::PLAN_PRO, MSH_License_Manager::PLAN_AGENCY ), true );
}
```

---

### File 2: `includes/enterprise/class-msh-telemetry.php`

**Purpose:** Usage tracking and analytics

**Events Tracked:**

```php
class MSH_Telemetry {

    /**
     * Log telemetry event
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    public function log_event( $event, $data = array() ) {
        // Check if telemetry enabled (user consent)
        if ( ! $this->is_enabled() ) {
            return;
        }

        // Store locally
        $this->store_local( $event, $data );

        // Send to remote collector (Pro/Agency only)
        if ( msh_is_pro_active() ) {
            $this->send_remote( $event, $data );
        }
    }

    /**
     * Store event locally
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    private function store_local( $event, $data ) {
        global $wpdb;

        $wpdb->insert(
            $this->telemetry_table,
            array(
                'event'      => $event,
                'data'       => wp_json_encode( $data ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s' )
        );

        // Clean up old events (keep 90 days)
        $this->cleanup_old_events();
    }

    /**
     * Get dashboard metrics
     *
     * @param int $days Number of days to analyze
     * @return array Metrics
     */
    public function get_metrics( $days = 28 ) {
        global $wpdb;

        $since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        $metrics = array(
            'images_optimized'   => 0,
            'bytes_saved'        => 0,
            'ai_vs_manual_ratio' => 0,
            'avg_regen_time'     => 0,
            'total_jobs'         => 0,
            'failed_jobs'        => 0,
        );

        // Images optimized
        $metrics['images_optimized'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->telemetry_table}
            WHERE event = 'metadata_generated'
            AND created_at >= %s",
            $since
        ) );

        // Bytes saved (from compression events)
        $metrics['bytes_saved'] = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(CAST(JSON_EXTRACT(data, '$.bytes_saved') AS UNSIGNED))
            FROM {$this->telemetry_table}
            WHERE event = 'image_compressed'
            AND created_at >= %s",
            $since
        ) );

        // AI vs Manual ratio
        $ai_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->cache_table}
            WHERE chosen_source = 'ai'
            AND updated_at >= %s",
            $since
        ) );

        $manual_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->cache_table}
            WHERE chosen_source = 'manual'
            AND updated_at >= %s",
            $since
        ) );

        $total = $ai_count + $manual_count;
        $metrics['ai_vs_manual_ratio'] = $total > 0 ? round( ( $ai_count / $total ) * 100, 1 ) : 0;

        return $metrics;
    }
}
```

**Helper Function:**

```php
/**
 * Log telemetry event
 *
 * @param string $event Event name
 * @param array $data Event data
 */
function msh_telemetry( $event, $data = array() ) {
    MSH_Telemetry::get_instance()->log_event( $event, $data );
}
```

---

### File 3: `includes/enterprise/class-msh-onboarding-wizard.php`

**Purpose:** First-time setup wizard

**Steps:**

1. **Welcome** - Detect environment, check API keys
2. **Locale Setup** - Configure languages
3. **Glossary** - Add industry-specific terms
4. **Automation** - Enable triggers
5. **License** - Activate Pro (optional)
6. **Summary** - Review settings and launch

**Implementation:**

```php
class MSH_Onboarding_Wizard {

    /**
     * Render wizard
     */
    public function render() {
        $step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 1;

        ?>
        <div class="msh-onboarding-wizard">
            <div class="msh-wizard-header">
                <h1><?php esc_html_e( 'Welcome to The Dot Optimizer', 'msh-image-optimizer' ); ?></h1>
                <div class="msh-wizard-progress">
                    <span class="<?php echo $step >= 1 ? 'active' : ''; ?>">1</span>
                    <span class="<?php echo $step >= 2 ? 'active' : ''; ?>">2</span>
                    <span class="<?php echo $step >= 3 ? 'active' : ''; ?>">3</span>
                    <span class="<?php echo $step >= 4 ? 'active' : ''; ?>">4</span>
                    <span class="<?php echo $step >= 5 ? 'active' : ''; ?>">5</span>
                    <span class="<?php echo $step >= 6 ? 'active' : ''; ?>">6</span>
                </div>
            </div>

            <div class="msh-wizard-content">
                <?php
                switch ( $step ) {
                    case 1:
                        $this->render_step_welcome();
                        break;
                    case 2:
                        $this->render_step_locale();
                        break;
                    case 3:
                        $this->render_step_glossary();
                        break;
                    case 4:
                        $this->render_step_automation();
                        break;
                    case 5:
                        $this->render_step_license();
                        break;
                    case 6:
                        $this->render_step_summary();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Step 1: Welcome
     */
    private function render_step_welcome() {
        ?>
        <h2><?php esc_html_e( 'Let\'s get started!', 'msh-image-optimizer' ); ?></h2>
        <p><?php esc_html_e( 'This wizard will help you configure The Dot Optimizer in just a few minutes.', 'msh-image-optimizer' ); ?></p>

        <div class="msh-environment-check">
            <h3><?php esc_html_e( 'Environment Check', 'msh-image-optimizer' ); ?></h3>
            <ul>
                <li>
                    <?php echo $this->check_php_version() ? '✓' : '✗'; ?>
                    <?php esc_html_e( 'PHP 7.4 or higher', 'msh-image-optimizer' ); ?>
                </li>
                <li>
                    <?php echo $this->check_wp_version() ? '✓' : '✗'; ?>
                    <?php esc_html_e( 'WordPress 6.0 or higher', 'msh-image-optimizer' ); ?>
                </li>
                <li>
                    <?php echo $this->check_api_keys() ? '✓' : '✗'; ?>
                    <?php esc_html_e( 'AI API keys configured', 'msh-image-optimizer' ); ?>
                </li>
            </ul>
        </div>

        <div class="msh-wizard-actions">
            <a href="?page=msh-onboarding&step=2" class="button button-primary button-hero">
                <?php esc_html_e( 'Continue', 'msh-image-optimizer' ); ?>
            </a>
            <a href="?page=msh-onboarding&action=skip" class="button button-secondary">
                <?php esc_html_e( 'Skip and use defaults', 'msh-image-optimizer' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Handle skip action - apply sensible defaults
     */
    public function handle_skip() {
        // Apply default settings
        $defaults = array(
            'msh_enabled_locales'    => array( get_locale() ), // Current site locale only
            'msh_automation_enabled' => true,
            'msh_auto_on_upload'     => true,
            'msh_auto_on_publish'    => true,
            'msh_telemetry_enabled'  => false, // Default OFF (WordPress.org compliance)
        );

        foreach ( $defaults as $key => $value ) {
            update_option( $key, $value );
        }

        // Mark wizard as complete
        update_option( 'msh_onboarding_complete', true );

        // Redirect to Hub
        wp_safe_redirect( admin_url( 'admin.php?page=msh-hub&tab=cache' ) );
        exit;
    }
}
```

---

## WordPress.org Compliance

### Checklist

- [ ] **No external calls without consent**
  - All API calls opt-in via settings
  - Telemetry disabled by default
  - License activation only when user enters key

- [ ] **Data collection transparency**
  - Privacy policy section in readme.txt
  - Clear opt-in checkboxes
  - Data export/deletion via WP privacy tools

- [ ] **GPL compliance**
  - All code GPL v2 or later
  - No obfuscation
  - No license key required for core features

- [ ] **Security**
  - Nonces on all forms
  - Prepared SQL statements
  - Input sanitization + output escaping
  - Capability checks

- [ ] **Performance**
  - No blocking operations on page load
  - Background processing via WP-Cron
  - Assets minified and conditionally loaded

- [ ] **Accessibility**
  - WCAG 2.1 AA compliant
  - Keyboard navigation
  - Screen reader support

---

## Database Schema Additions

### Jobs Table

```sql
CREATE TABLE {$wpdb->prefix}msh_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    payload LONGTEXT,
    priority ENUM('high', 'medium', 'normal') DEFAULT 'normal',
    status ENUM('pending', 'processing', 'complete', 'failed') DEFAULT 'pending',
    attempts TINYINT UNSIGNED DEFAULT 0,
    max_attempts TINYINT UNSIGNED DEFAULT 3,
    next_retry_at DATETIME DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_status_priority (status, priority, created_at),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_retry (next_retry_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Note:** Maintains `msh_` prefix for consistency with Phase 4R+ tables (`msh_metadata_cache`, `msh_events`, `msh_versions`).

### Dead Letter Queue (Failed Job Recovery)

```sql
CREATE TABLE {$wpdb->prefix}msh_dead_letters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    job_type VARCHAR(50) NOT NULL,
    attachment_id BIGINT UNSIGNED DEFAULT NULL,
    locale VARCHAR(20) DEFAULT NULL,
    field VARCHAR(50) DEFAULT NULL,
    reason TEXT NOT NULL,
    payload LONGTEXT,
    failed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_job_id (job_id),
    INDEX idx_attachment (attachment_id, locale, field),
    INDEX idx_failed_at (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Purpose:**
- Archive permanently failed jobs (max retries exceeded)
- Enable manual recovery and analysis
- Track failure patterns for debugging

**WP-CLI Recovery:**
```bash
# List dead letters
wp msh jobs dead-letters --limit=50

# Retry dead letter
wp msh jobs retry-dead <dead-letter-id>

# Analyze failure reasons
wp msh jobs dead-letters --group-by=reason
```

### Telemetry Table

```sql
CREATE TABLE {$wpdb->prefix}msh_telemetry (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(100) NOT NULL,
    data LONGTEXT,
    site_hash CHAR(64) NOT NULL COMMENT 'Anonymized site identifier',
    created_at DATETIME NOT NULL,
    INDEX idx_event (event, created_at),
    INDEX idx_site (site_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Privacy Model:**
- `site_hash`: SHA256 hash of (site_url + salt + random_seed) - **NOT** reversible to domain
- **Default:** Telemetry opt-in is **OFF** (WordPress.org compliance)
- User must explicitly enable via Settings → Privacy → "Help improve The Dot Optimizer"
- No personal data stored (no IP, no user emails, no domain names)

**Telemetry Opt-In UI:**
```php
<label>
    <input type="checkbox" name="msh_telemetry_enabled" value="1" <?php checked( get_option( 'msh_telemetry_enabled', false ) ); ?>>
    <?php esc_html_e( 'Help improve The Dot Optimizer by sharing anonymous usage data', 'msh-image-optimizer' ); ?>
    <a href="#" class="msh-telemetry-details"><?php esc_html_e( 'What data is collected?', 'msh-image-optimizer' ); ?></a>
</label>
```

**Data Collected (when enabled):**
- Event counts (images optimized, jobs processed)
- Performance metrics (avg processing time, queue depth)
- Feature usage (which tabs visited, automation enabled)
- Error rates (job failures, API timeouts)

**NOT Collected:**
- Domain names or URLs
- User emails or names
- Image content or metadata values
- Post titles or content

### Metrics Table (Daily Aggregates)

```sql
CREATE TABLE {$wpdb->prefix}msh_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_metric (metric_date, metric_name),
    INDEX idx_date (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Automated Unit Tests for Accuracy:**

```php
class Test_Metrics_Accuracy extends WP_UnitTestCase {

    /**
     * Test: Telemetry events match actual job results
     */
    public function test_telemetry_matches_jobs() {
        // Process 10 jobs
        $job_engine = MSH_Job_Engine::get_instance();
        for ( $i = 0; $i < 10; $i++ ) {
            $job_engine->enqueue( 'regenerate_metadata', 'attachment', 100 + $i, array() );
        }
        $results = $job_engine->process_batch( 10 );

        // Check telemetry count
        $telemetry_count = $this->get_telemetry_count( 'job_processed' );

        $this->assertEquals( $results['processed'], $telemetry_count, 'Telemetry count should match processed jobs' );
    }

    /**
     * Test: Metrics aggregation prevents double-counting
     */
    public function test_metrics_no_double_count() {
        // Generate 5 events
        for ( $i = 0; $i < 5; $i++ ) {
            msh_telemetry( 'metadata_generated', array( 'attachment_id' => 200 + $i ) );
        }

        // Aggregate metrics
        MSH_Telemetry::get_instance()->aggregate_daily_metrics();

        // Re-aggregate (should be idempotent)
        MSH_Telemetry::get_instance()->aggregate_daily_metrics();

        // Check metric value
        $metric = $this->get_metric( gmdate( 'Y-m-d' ), 'metadata_generated' );

        $this->assertEquals( 5, $metric, 'Metric should count 5, not 10 (no double-counting)' );
    }
}
```

---

## WP-CLI Commands Summary

### Automation Commands

```bash
# Process next batch of jobs
wp msh jobs process --batch=50 --timeout=300

# Get job queue status
wp msh jobs status

# Get job details
wp msh jobs get <job-id>

# Retry failed job
wp msh jobs retry <job-id>

# Clear completed jobs
wp msh jobs cleanup --days=7
```

### Telemetry Commands

```bash
# Get metrics dashboard
wp msh telemetry metrics --days=28

# Export telemetry data
wp msh telemetry export --since=2025-01-01

# Clear old telemetry events
wp msh telemetry cleanup --days=90
```

### License Commands

```bash
# Activate license
wp msh license activate <license-key>

# Deactivate license
wp msh license deactivate

# Check license status
wp msh license status

# Check feature availability
wp msh license feature cloud_sync
```

---

## Timeline: 6 Weeks + 2-Stage Release

### Week 1-2: Foundation (Track A + Track B in parallel)

**Track A Progress:**
- Day 1-3: Job engine + database schema (including dead-letter queue)
- Day 4-6: Automation triggers
- Day 7-10: Queue manager + batch processor

**Track B Progress:**
- Day 1-3: Hub page + tab routing
- Day 4-6: Cache tab + History tab
- Day 7-10: Queue tab + Events tab + Sync tab

**Deliverables:**
- ✅ Jobs processing via WP-Cron
- ✅ Hub UI with 5 tabs
- ✅ AJAX working for all tabs

---

### Week 3-4: Enterprise Features (Track C)

**Track C Progress:**
- Day 1-3: License manager + Lemon Squeezy integration
- Day 4-6: Telemetry system (opt-in OFF by default) + metrics dashboard
- Day 7-10: Onboarding wizard (with "Skip and use defaults" option)
- Day 11-14: Remote sync (S3/Supabase)

**Deliverables:**
- ✅ License activation working
- ✅ Telemetry tracking usage (privacy-compliant)
- ✅ Onboarding wizard functional with skip option
- ✅ Cloud sync operational (Pro)

---

### Week 5: Integration + REST API

**All Tracks:**
- Day 1-2: REST API endpoints for all Hub tabs
- Day 3-4: Connect Hub UI to job engine
- Day 5-6: CLI commands for all features (including dead-letter recovery)
- Day 7: End-to-end testing

**Deliverables:**
- ✅ REST API complete
- ✅ WP-CLI parity with UI
- ✅ Full integration tested

---

### Week 6: QA + Internal Release Candidate

**All Tracks:**
- Day 1-2: Security audit
- Day 3-4: Performance optimization
- Day 4-5: WordPress.org compliance review
- Day 6-7: Unit tests for telemetry accuracy
- Day 8-9: Documentation (user + dev + enterprise)
- Day 10: Release **v2.0.0-rc1** (internal QA only)

**Deliverables:**
- ✅ All QA criteria met
- ✅ readme.txt compliant
- ✅ Documentation complete (3 guides)
- ✅ v2.0.0-rc1 released for internal testing

---

### Week 7-8: Stress Testing + Public Release

**2-Stage Release Plan:**

#### Stage 1: v2.0.0-rc1 (Internal QA)

**Timeline:** Week 6 Day 10 → Week 8 Day 10 (2 weeks)

**Test Environment:**
- 10,000+ image dataset
- 5 locales enabled
- All automation triggers active
- Pro features fully activated

**Validation Criteria:**

| Category | Metric | Target | Status |
|----------|--------|--------|--------|
| Job Processing | 10k jobs completed | 100% success | |
| Queue Health | Failed jobs | <1% | |
| Dead Letter Rate | Jobs in DLQ | <0.5% | |
| Telemetry Accuracy | Event count vs actual | 100% match | |
| Metrics Aggregation | No double-counting | Pass unit tests | |
| Memory Usage | Peak memory | <256 MB | |
| Database Load | Query time | <100ms avg | |
| API Response Time | REST endpoints | <500ms p95 | |
| Hub Page Load | Time to interactive | <1 sec | |
| Cron Reliability | Missed executions | 0 | |

**Telemetry Monitoring (rc1 phase):**
- Daily reports on job success rate
- Error logs reviewed every 24h
- User feedback via internal Slack channel
- Performance metrics tracked via New Relic or similar

**Go/No-Go Decision:** Week 8 Day 5
- If all validation criteria met → Proceed to Stage 2
- If critical issues found → Extend rc1 testing 1 week

#### Stage 2: v2.0.0 (Public Release)

**Timeline:** Week 8 Day 10

**Pre-Release Checklist:**
- [ ] All rc1 validation criteria passed
- [ ] No P0/P1 bugs outstanding
- [ ] Documentation reviewed by 2+ people
- [ ] WordPress.org submission approved
- [ ] Lemon Squeezy integration tested with real payments
- [ ] Rollback plan documented
- [ ] Support team trained on new features
- [ ] Marketing assets ready (blog post, social media)

**Release Process:**
1. Tag v2.0.0 in Git
2. Build release package (exclude dev files)
3. Submit to WordPress.org
4. Deploy to production sites (staged rollout)
5. Announce on blog + social media
6. Monitor error logs for 48 hours
7. Hot-patch if critical issues found (v2.0.1)

**Post-Release Monitoring (30 days):**
- Daily error rate tracking
- Weekly telemetry review (if users opt-in)
- Support ticket volume and resolution time
- Pro conversion rate tracking
- User satisfaction survey (NPS)

**Rollback Trigger:**
- >5% error rate
- Database corruption reports
- WordPress.org suspension
- Payment processing failures (Pro)

**Rollback Process:**
1. Revert to v1.x in WordPress.org repo
2. Notify active Pro users via email
3. Document known issues publicly
4. Fix issues in v2.0.1
5. Re-submit for approval

---

### Total Timeline: 8 Weeks

- **Week 1-2:** Foundation (Track A + Track B)
- **Week 3-4:** Enterprise Features (Track C)
- **Week 5:** Integration + REST API
- **Week 6:** QA + Internal rc1 Release
- **Week 7-8:** Stress Testing + Public v2.0.0 Release

---

## Definition of Done

### Category: Performance

| Criteria | Target | Status |
|----------|--------|--------|
| 10k+ items processed | No timeout errors | |
| Job processing speed | <2 sec per job | |
| Auto-resume after failure | 100% reliable | |
| Hub page load time | <1 sec | |
| AJAX response time | <500ms | |

### Category: Licensing

| Criteria | Target | Status |
|----------|--------|--------|
| Key activation | Working | |
| Key deactivation | Working | |
| Plan detection | Accurate | |
| Feature gating | Enforced | |
| Offline mode | 7-day grace | |

### Category: Automation

| Criteria | Target | Status |
|----------|--------|--------|
| Upload trigger | 100% reliable | |
| Publish trigger | 100% reliable | |
| Locale trigger | 100% reliable | |
| Glossary trigger | 100% reliable | |
| Cron execution | Every 5 min | |

### Category: Hub Dashboard

| Criteria | Target | Status |
|----------|--------|--------|
| Live metrics updating | Real-time | |
| Event log scrolling | Smooth | |
| Cache browser filtering | <1 sec | |
| Bulk actions | No timeout | |
| CSV export | Working | |

### Category: Telemetry

| Criteria | Target | Status |
|----------|--------|--------|
| Local logging | Working | |
| Remote collector (Pro) | Working | |
| Metrics dashboard | Accurate | |
| 28-day trends | Graphed | |
| ROI calculation | Correct | |

### Category: Onboarding

| Criteria | Target | Status |
|----------|--------|--------|
| End-to-end wizard | Functional | |
| Environment checks | Accurate | |
| API key detection | Working | |
| Locale setup | Saves correctly | |
| License activation | Optional | |

### Category: Documentation

| Criteria | Target | Status |
|----------|--------|--------|
| User manual | Complete | |
| Dev docs | Complete | |
| API reference | Complete | |
| CLI help | All commands | |
| readme.txt | WP.org compliant | |

### Category: Compliance

| Criteria | Target | Status |
|----------|--------|--------|
| Pass WP.org review | Approved | |
| Security audit | No issues | |
| Accessibility | WCAG AA | |
| Performance | No blocking | |
| i18n | All strings | |

---

## Success Metrics (30 Days Post-Launch)

### WordPress.org
- [ ] 1,000+ active installs
- [ ] 4.5+ star rating
- [ ] <5% support thread resolution time >48h

### Pro Conversions
- [ ] 5% free → Pro conversion rate
- [ ] 10% Pro → Agency upgrade rate
- [ ] <2% churn rate

### Technical Health
- [ ] <0.1% error rate
- [ ] 99.9% uptime (cloud sync)
- [ ] <500ms avg API response

### User Engagement
- [ ] 60%+ onboarding completion rate
- [ ] 80%+ automation enabled
- [ ] 40%+ Hub page visits weekly

---

## Documentation Deliverables (3 Guides)

### Guide 1: User Guide (`docs/user-guide.md`)

**Audience:** WordPress site owners, content managers, non-technical users

**Contents:**
1. **Getting Started**
   - Installation from WordPress.org
   - Onboarding wizard walkthrough
   - First image optimization

2. **Optimizer Hub**
   - Cache tab: Browse and compare AI vs Manual
   - History tab: View version timeline
   - Queue tab: Monitor automation status
   - Events tab: Track recent activity
   - Sync tab: Cloud sync (Pro feature overview)

3. **Automation**
   - Enable/disable triggers
   - What happens when you upload an image
   - What happens when you publish a post
   - How to manually regenerate metadata

4. **Manual Edits**
   - How to override AI metadata
   - When manual edits take priority
   - How to switch between AI and Manual

5. **Settings**
   - Locale configuration
   - Glossary management
   - Privacy settings (telemetry opt-in)
   - License activation (Pro)

6. **Troubleshooting**
   - Why is my metadata still stale?
   - How to retry failed jobs
   - How to contact support

**Format:** Markdown with screenshots, ~5,000 words

---

### Guide 2: Developer Handbook (`docs/developer-handbook.md`)

**Audience:** WordPress developers, plugin/theme authors, technical integrators

**Contents:**
1. **Architecture Overview**
   - Phase 4R+ recap (metadata cache, events, fingerprints)
   - Phase 5+9 additions (jobs, telemetry, licensing)
   - Database schema reference

2. **REST API Reference**
   - Authentication
   - Endpoints:
     - `GET /msh/v1/metadata/cache` - Query cache
     - `POST /msh/v1/metadata/regenerate` - Bulk regenerate
     - `GET /msh/v1/jobs/status` - Queue status
     - `GET /msh/v1/telemetry/metrics` - Dashboard metrics
   - Request/response examples
   - Error codes

3. **WP-CLI Reference**
   - `wp msh jobs` - Job management
   - `wp msh metadata` - Metadata operations
   - `wp msh telemetry` - Analytics
   - `wp msh license` - Licensing
   - Examples and use cases

4. **Hooks & Filters**
   - Actions:
     - `msh_job_enqueued` - When job created
     - `msh_job_completed` - When job finishes
     - `msh_metadata_regenerated` - When metadata updated
     - `msh_telemetry_event` - When event logged
   - Filters:
     - `msh_hub_tabs` - Add custom tabs
     - `msh_job_priority` - Override priority
     - `msh_telemetry_enabled` - Control telemetry
     - `msh_license_plans` - Add custom plans

5. **Extending the Plugin**
   - Add custom job types
   - Create custom Hub tabs
   - Integrate with third-party services
   - Example: Custom telemetry collector

6. **Testing**
   - Unit test setup
   - Integration test examples
   - Performance benchmarking
   - Debugging tips

**Format:** Markdown with code examples, ~8,000 words

---

### Guide 3: Enterprise Guide (`docs/enterprise-guide.md`)

**Audience:** Agency owners, enterprise clients, Pro/Agency plan users

**Contents:**
1. **Licensing**
   - Plan comparison (Free vs Pro vs Agency)
   - How to activate your license
   - Multi-site licensing
   - License renewal and upgrades
   - Offline grace period

2. **Cloud Sync (Pro Feature)**
   - S3 setup guide
   - Supabase setup guide
   - Push/pull workflows
   - Conflict resolution
   - Multi-site metadata sharing
   - Backup and restore

3. **Remote Deployment (Agency Feature)**
   - API authentication
   - Deploy metadata across multiple sites
   - Batch operations
   - Monitoring and health checks
   - Rate limits and quotas

4. **Analytics & Telemetry (Agency Feature)**
   - Dashboard metrics explained
   - 28-day trends
   - ROI calculation methodology
   - Custom analytics API
   - Data export (CSV, JSON)
   - Privacy and compliance

5. **Performance at Scale**
   - Recommended hosting requirements
   - Database optimization
   - Cron configuration for high volume
   - Memory limits and PHP settings
   - Monitoring and alerting

6. **Security Best Practices**
   - API key management
   - User permissions and capabilities
   - Audit logging
   - GDPR compliance
   - Data retention policies

7. **Support & SLA**
   - Priority support channels (Pro/Agency)
   - Response time guarantees
   - Escalation process
   - Custom development requests
   - Training and onboarding

**Format:** Markdown with diagrams, ~6,000 words

---

### Documentation Timeline

**Week 6 Day 8-9:**
- User Guide: Day 8
- Developer Handbook: Day 9 AM
- Enterprise Guide: Day 9 PM

**Review Process:**
- 2+ technical reviewers for Developer Handbook
- 1+ non-technical reviewer for User Guide
- Product manager review for Enterprise Guide

**Publication:**
- Hosted on docs.thedot.com
- Included in plugin zip (`/docs` directory)
- WordPress.org readme.txt links to full guides

---

## Next Steps

**Ready to begin Phase 5+9 development?**

### Recommended Approach: 3 Parallel Tracks

**Track A:** Automation Infrastructure (Week 1-4)
**Track B:** Hub UI & REST API (Week 1-4)
**Track C:** Enterprise Features (Week 3-6, depends on A+B)

### Start With:

1. **Track A Developer:** Create database schema + job engine + dead-letter queue
2. **Track B Developer:** Build Hub page skeleton + tab routing
3. **Week 3:** Track C Developer joins for licensing + telemetry (opt-in OFF default)

**Estimated Development:** 6 weeks
**Estimated Total (with QA):** 8 weeks
**Target Version:** v2.0.0 (Enterprise-Grade)

---

## Approval Summary

### ✅ Refinements Implemented

1. **Naming Consistency:** ✅ Maintained `msh_` prefix across all tables (consistent with Phase 4R+)
2. **Queue Resilience:** ✅ Added `msh_dead_letters` table for failed job recovery + analytics
3. **Telemetry Privacy:** ✅ Anonymized `site_hash` (SHA256), opt-in default OFF, clear data policy
4. **Onboarding UX:** ✅ Added "Skip and use defaults" button for faster agency deployment
5. **Rollout & Staging:** ✅ Documented 2-stage release (v2.0.0-rc1 → v2.0.0) with validation criteria
6. **Metrics Accuracy:** ✅ Planned unit tests to prevent double-counting and verify telemetry
7. **Documentation Split:** ✅ 3 deliverables (User Guide, Developer Handbook, Enterprise Guide)

### 🎯 Ready for Development

**Phase 5+9 Combined Plan:**
- Strategic cohesion: Aligns technical depth with monetization
- Parallel tracks: Balanced workload (infra + UI + enterprise)
- Compliance: GDPR, WCAG, i18n, GPL ready for WordPress.org
- Telemetry: 28-day deltas, job health, measurable ROI
- Success criteria: Technical + marketplace KPIs

**Approval Status:** ✅ **APPROVED** with all refinements implemented

---

**End of Phase 5+9 Combined Plan**

Questions or ready to begin Track A development?


---

## Phase 6: License Server & Payment Infrastructure

**Status:** 📋 Planned (Future Phase)  
**Priority:** High (Required for monetization)  
**Dependencies:** Phase 5+9 complete (Pro feature flags in place)

### Overview

To enable Pro/Agency plan sales and activate license-gated features in the plugin, a **separate license API server** must be built outside the WordPress plugin.

**📄 See Full Documentation:** [LICENSING-ARCHITECTURE.md](./LICENSING-ARCHITECTURE.md)

### Key Components

1. **License API Server** (`license.thedot.com`)
   - Separate Node.js/PHP application (NOT in WordPress plugin)
   - Handles license key generation, validation, activation tracking
   - Integrates with Stripe/LemonSqueezy via webhooks

2. **Database** (PostgreSQL/MySQL)
   - `licenses` table: Keys, plans, status, expiration
   - `license_activations` table: Site tracking, activation limits

3. **Payment Integration**
   - Stripe or LemonSqueezy webhooks
   - Automatic license generation on purchase
   - Subscription management (renewal, cancellation)

4. **WordPress Plugin Integration**
   - Lightweight SDK for API communication
   - Local storage in `wp_options`
   - Daily cron verification
   - Feature gating via `msh_is_pro_active()`

### Why Separate Server?

✅ **Security**: Payment secrets never in WordPress  
✅ **Scalability**: Independent deployment and scaling  
✅ **Compliance**: Easier PCI/GDPR compliance  
✅ **Testing**: Cleaner CI/CD pipeline  
✅ **WordPress.org**: Plugin remains review-friendly  

### Recommended Architecture

**Monorepo Structure:**
```
the-dot-optimizer/
├─ apps/
│  ├─ wp-plugin/         # MSH Image Optimizer
│  └─ license-api/       # License server (Node/PHP)
├─ packages/
│  └─ license-sdk/       # Client library
├─ infra/
│  └─ docker-compose.yml # Local dev (Postgres + Mailhog)
└─ README.md
```

### API Endpoints (Contract)

- `POST /activate` - Activate license on new site
- `POST /verify` - Daily license status check
- `POST /deactivate` - Free up activation slot
- `POST /portal` - Generate Stripe Customer Portal URL

### Timeline Estimate

- **Setup & Infrastructure**: 1-2 weeks
- **Core API Development**: 2-3 weeks  
- **Payment Integration**: 1-2 weeks
- **Plugin Integration**: 1 week
- **Testing & QA**: 1 week
- **Total**: 6-9 weeks

### Dependencies

- Stripe or LemonSqueezy account
- Domain: `license.thedot.com`
- Hosting: Railway/Render/AWS (~$10-20/month)
- SSL certificate (Let's Encrypt)

---

**For complete details, database schema, security considerations, and code examples:**  
👉 **[Read LICENSING-ARCHITECTURE.md](./LICENSING-ARCHITECTURE.md)**

---

