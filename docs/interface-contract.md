# Interface Contract - Phase 5+9
## Agreement Between AI #1 (Backend) and AI #2 (Frontend)

**Date:** October 19, 2025
**Status:** Draft - Ready for Joint Planning Session
**Purpose:** Define ALL interfaces before coding starts to prevent conflicts

---

## 1. Database Tables (AI #1 Creates)

### Table 1: `msh_jobs`

**Purpose:** Job queue for metadata regeneration

**Schema:**
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

**AI #2 Needs to Know:**
- Query by `status` for Queue tab
- Group by `priority` for priority breakdown
- Count by `status` for dashboard stats

---

### Table 2: `msh_dead_letters`

**Purpose:** Archive permanently failed jobs for recovery

**Schema:**
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

**AI #2 Needs to Know:**
- Display count in Queue tab ("X failed jobs")
- Group by `reason` for failure analysis
- Link to original job via `job_id`

---

### Table 3: `msh_telemetry`

**Purpose:** Usage tracking events (opt-in only)

**Schema:**
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

**AI #2 Needs to Know:**
- Read for Events tab (live feed)
- Aggregate for metrics dashboard
- Filter by `event` type
- Privacy: `site_hash` is anonymized, never show to user

---

### Table 4: `msh_metrics`

**Purpose:** Daily aggregated metrics for dashboard

**Schema:**
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

**AI #2 Needs to Know:**
- Query last 28 days for trend graphs
- Metric names: `jobs_processed`, `jobs_failed`, `metadata_generated`, `bytes_saved`
- Use for dashboard widgets

---

## 2. Helper Functions (AI #1 Creates, AI #2 Uses)

### Function 1: `msh_enqueue_job()`

**Purpose:** Enqueue a job for background processing

**Signature:**
```php
/**
 * Enqueue a job for background processing
 *
 * @param string $job_type Job type (e.g., 'regenerate_metadata', 'generate_metadata')
 * @param string $entity_type Entity type (e.g., 'attachment')
 * @param int $entity_id Entity ID
 * @param array $payload Job payload data
 * @param string $priority Priority level ('high', 'medium', 'normal')
 * @return int|WP_Error Job ID on success, WP_Error on failure
 */
function msh_enqueue_job( $job_type, $entity_type, $entity_id, $payload = array(), $priority = 'normal' ) {
    return MSH_Job_Engine::get_instance()->enqueue( $job_type, $entity_type, $entity_id, $payload, $priority );
}
```

**Example Usage (AI #2):**
```php
// In Cache tab "Regenerate" button handler
$job_id = msh_enqueue_job(
    'regenerate_metadata',
    'attachment',
    1686,
    array(
        'locale' => 'es_ES',
        'field'  => 'alt',
        'reason' => 'manual_trigger',
    ),
    'high' // User-triggered = high priority
);

if ( is_wp_error( $job_id ) ) {
    wp_send_json_error( $job_id->get_error_message() );
}

wp_send_json_success( array( 'job_id' => $job_id ) );
```

---

### Function 2: `msh_get_job_stats()`

**Purpose:** Get queue statistics for dashboard

**Signature:**
```php
/**
 * Get job queue statistics
 *
 * @return array {
 *     @type int $pending Pending jobs
 *     @type int $processing Currently processing
 *     @type int $complete Completed jobs (last 24h)
 *     @type int $failed Failed jobs (in dead letter queue)
 *     @type int $high_priority High priority pending
 *     @type int $medium_priority Medium priority pending
 *     @type int $normal_priority Normal priority pending
 * }
 */
function msh_get_job_stats() {
    return MSH_Queue_Manager::get_instance()->get_stats();
}
```

**Example Usage (AI #2):**
```php
// In Queue tab rendering
$stats = msh_get_job_stats();
?>
<div class="msh-queue-stats">
    <div class="stat">
        <span class="label">Pending</span>
        <span class="value"><?php echo esc_html( $stats['pending'] ); ?></span>
    </div>
    <div class="stat">
        <span class="label">Processing</span>
        <span class="value"><?php echo esc_html( $stats['processing'] ); ?></span>
    </div>
    <div class="stat">
        <span class="label">Failed</span>
        <span class="value"><?php echo esc_html( $stats['failed'] ); ?></span>
    </div>
</div>
```

---

### Function 3: `msh_telemetry()`

**Purpose:** Log telemetry event (respects opt-in setting)

**Signature:**
```php
/**
 * Log telemetry event
 *
 * @param string $event Event name
 * @param array $data Event data
 * @return bool True if logged, false if telemetry disabled
 */
function msh_telemetry( $event, $data = array() ) {
    return MSH_Telemetry::get_instance()->log_event( $event, $data );
}
```

**Example Usage (AI #2):**
```php
// In Cache tab when user clicks "View Both"
msh_telemetry( 'cache_view_both_clicked', array(
    'attachment_id' => 1686,
    'locale'        => 'es_ES',
    'field'         => 'alt',
) );
```

---

### Function 4: `msh_is_pro_active()`

**Purpose:** Check if Pro or Agency plan is active

**Signature:**
```php
/**
 * Check if Pro or Agency plan is active
 *
 * @return bool True if Pro/Agency, false if Free
 */
function msh_is_pro_active() {
    $plan = MSH_License_Manager::get_instance()->get_active_plan();
    return in_array( $plan, array( 'pro', 'agency' ), true );
}
```

**Example Usage (AI #2):**
```php
// In Sync tab rendering
if ( ! msh_is_pro_active() ) {
    $this->render_pro_upsell();
    return;
}

// Show full sync UI for Pro users
$this->render_sync_dashboard();
```

---

### Function 5: `msh_get_cache_entries()`

**Purpose:** Query metadata cache with filters (uses Phase 4R+ table)

**Signature:**
```php
/**
 * Query metadata cache entries
 *
 * @param array $args {
 *     @type string $locale Locale code filter (optional)
 *     @type string $staleness 'stale' or 'fresh' filter (optional)
 *     @type string $source 'ai' or 'manual' filter (optional)
 *     @type string $search Search term for image name (optional)
 *     @type int $page Page number (default: 1)
 *     @type int $per_page Results per page (default: 50)
 * }
 * @return array {
 *     @type array $items Array of cache entry objects
 *     @type int $total Total matching entries
 *     @type int $total_pages Total pages
 * }
 */
function msh_get_cache_entries( $args = array() ) {
    return MSH_Metadata_Core::get_instance()->query_cache( $args );
}
```

**Example Usage (AI #2):**
```php
// In Cache tab AJAX handler
$results = msh_get_cache_entries( array(
    'locale'     => $_POST['locale'] ?? '',
    'staleness'  => $_POST['staleness'] ?? '',
    'source'     => $_POST['source'] ?? '',
    'search'     => $_POST['search'] ?? '',
    'page'       => $_POST['page'] ?? 1,
    'per_page'   => 50,
) );

// Results structure:
// $results['items'] = array of objects with:
//   - attachment_id
//   - locale
//   - field
//   - ai_value
//   - manual_value
//   - chosen_source ('ai' or 'manual')
//   - stale_reason (null if fresh)
//   - updated_at
```

---

## 3. WordPress Hooks (AI #1 Emits, AI #2 Listens)

### Action: `msh_job_enqueued`

**When Fired:** After job successfully added to queue

**Parameters:**
```php
do_action( 'msh_job_enqueued', $job_id, $job_type, $entity_id, $priority );
```

**AI #2 Usage:**
```php
// In Queue tab JavaScript - listen for new jobs
add_action( 'msh_job_enqueued', function( $job_id, $job_type, $entity_id, $priority ) {
    // Refresh queue stats via AJAX
}, 10, 4 );
```

---

### Action: `msh_job_completed`

**When Fired:** After job successfully processed

**Parameters:**
```php
do_action( 'msh_job_completed', $job_id, $result );
```

**AI #2 Usage:**
```php
// Show success notification in Queue tab
add_action( 'msh_job_completed', function( $job_id, $result ) {
    // Update UI: decrement pending count, increment complete count
}, 10, 2 );
```

---

### Action: `msh_job_failed`

**When Fired:** After job fails (all retries exhausted)

**Parameters:**
```php
do_action( 'msh_job_failed', $job_id, $error_message );
```

**AI #2 Usage:**
```php
// Show error notification in Queue tab
add_action( 'msh_job_failed', function( $job_id, $error_message ) {
    // Update UI: show failed job in dead letter section
}, 10, 2 );
```

---

### Filter: `msh_hub_tabs`

**Purpose:** Allow adding custom tabs to Hub

**Usage (AI #2 creates this filter):**
```php
$tabs = apply_filters( 'msh_hub_tabs', array(
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
    // ... etc
) );
```

**AI #1 Usage:**
```php
// Extensions can add custom tabs
add_filter( 'msh_hub_tabs', function( $tabs ) {
    $tabs['custom'] = array(
        'label'    => 'My Custom Tab',
        'callback' => 'my_custom_tab_render',
    );
    return $tabs;
} );
```

---

## 4. REST API Endpoints (AI #1 Creates, AI #2 Calls)

### Endpoint 1: GET `/msh/v1/jobs/status`

**Purpose:** Get queue statistics

**Request:**
```http
GET /wp-json/msh/v1/jobs/status
Authorization: Bearer {token}
```

**Response:**
```json
{
  "pending": 42,
  "processing": 3,
  "complete": 1250,
  "failed": 5,
  "high_priority": 10,
  "medium_priority": 15,
  "normal_priority": 17
}
```

**AI #2 Usage (JavaScript):**
```javascript
// In Queue tab - refresh stats every 5 seconds
setInterval( function() {
    fetch( '/wp-json/msh/v1/jobs/status', {
        headers: {
            'Authorization': 'Bearer ' + mshData.apiToken
        }
    } )
    .then( response => response.json() )
    .then( stats => {
        document.getElementById( 'pending-count' ).textContent = stats.pending;
        document.getElementById( 'processing-count' ).textContent = stats.processing;
        // ... etc
    } );
}, 5000 );
```

---

### Endpoint 2: POST `/msh/v1/jobs/process`

**Purpose:** Manually trigger job processing

**Request:**
```http
POST /wp-json/msh/v1/jobs/process
Authorization: Bearer {token}
Content-Type: application/json

{
  "batch_size": 10
}
```

**Response:**
```json
{
  "processed": 10,
  "failed": 0,
  "skipped": 0
}
```

**AI #2 Usage (JavaScript):**
```javascript
// In Queue tab "Process Now" button
document.getElementById( 'process-now' ).addEventListener( 'click', function() {
    fetch( '/wp-json/msh/v1/jobs/process', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + mshData.apiToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify( { batch_size: 50 } )
    } )
    .then( response => response.json() )
    .then( result => {
        alert( result.processed + ' jobs processed!' );
        // Refresh stats
    } );
} );
```

---

### Endpoint 3: GET `/msh/v1/metadata/cache`

**Purpose:** Query metadata cache with filters

**Request:**
```http
GET /wp-json/msh/v1/metadata/cache?locale=es_ES&staleness=stale&page=1&per_page=50
Authorization: Bearer {token}
```

**Response:**
```json
{
  "items": [
    {
      "id": 123,
      "attachment_id": 1686,
      "locale": "es_ES",
      "field": "alt",
      "ai_value": "Fisioterapeuta ayudando a paciente...",
      "manual_value": "Terapia física profesional",
      "chosen_source": "manual",
      "stale_reason": "context_changed",
      "updated_at": "2025-10-19 14:30:00"
    }
  ],
  "total": 87,
  "total_pages": 2
}
```

**AI #2 Usage (JavaScript):**
```javascript
// In Cache tab - apply filters
function loadCacheEntries( filters ) {
    const params = new URLSearchParams( filters );

    fetch( '/wp-json/msh/v1/metadata/cache?' + params, {
        headers: { 'Authorization': 'Bearer ' + mshData.apiToken }
    } )
    .then( response => response.json() )
    .then( data => {
        renderCacheTable( data.items );
        renderPagination( data.total_pages );
    } );
}
```

---

### Endpoint 4: POST `/msh/v1/metadata/regenerate`

**Purpose:** Bulk regenerate metadata

**Request:**
```http
POST /wp-json/msh/v1/metadata/regenerate
Authorization: Bearer {token}
Content-Type: application/json

{
  "cache_ids": [123, 124, 125]
}
```

**Response:**
```json
{
  "enqueued": 3,
  "job_ids": [501, 502, 503]
}
```

**AI #2 Usage (JavaScript):**
```javascript
// In Cache tab - bulk regenerate selected rows
document.getElementById( 'bulk-regenerate' ).addEventListener( 'click', function() {
    const selectedIds = getSelectedCacheIds(); // [123, 124, 125]

    fetch( '/wp-json/msh/v1/metadata/regenerate', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + mshData.apiToken,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify( { cache_ids: selectedIds } )
    } )
    .then( response => response.json() )
    .then( result => {
        alert( result.enqueued + ' jobs enqueued!' );
    } );
} );
```

---

## 5. Shared Constants (Both AIs Use)

### File: `includes/class-msh-constants.php` (AI #1 creates)

```php
<?php
/**
 * Plugin constants
 */
class MSH_Constants {

    // Job types
    const JOB_TYPE_GENERATE = 'generate_metadata';
    const JOB_TYPE_REGENERATE = 'regenerate_metadata';

    // Job priorities
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_NORMAL = 'normal';

    // Job statuses
    const JOB_STATUS_PENDING = 'pending';
    const JOB_STATUS_PROCESSING = 'processing';
    const JOB_STATUS_COMPLETE = 'complete';
    const JOB_STATUS_FAILED = 'failed';

    // Telemetry events
    const EVENT_JOB_ENQUEUED = 'job_enqueued';
    const EVENT_JOB_COMPLETED = 'job_completed';
    const EVENT_JOB_FAILED = 'job_failed';
    const EVENT_METADATA_GENERATED = 'metadata_generated';
    const EVENT_CACHE_VIEW_BOTH = 'cache_view_both_clicked';

    // License plans
    const PLAN_FREE = 'free';
    const PLAN_PRO = 'pro';
    const PLAN_AGENCY = 'agency';
}
```

**AI #2 Usage:**
```php
// In Queue tab - filter by priority
$high_jobs = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$jobs_table}
    WHERE priority = %s
    AND status = %s",
    MSH_Constants::PRIORITY_HIGH,
    MSH_Constants::JOB_STATUS_PENDING
) );
```

---

## 6. Asset Enqueuing (AI #2 Handles)

### JavaScript Localization Data

**AI #1 Provides:**
```php
// In msh-image-optimizer.php (main plugin file)
// AI #1 adds this hook for AI #2 to use

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'toplevel_page_msh-hub' ) {
        return;
    }

    // Localize script with backend data
    wp_localize_script( 'msh-hub-js', 'mshHubData', array(
        'apiUrl'     => rest_url( 'msh/v1' ),
        'apiToken'   => wp_create_nonce( 'wp_rest' ),
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'ajaxNonce'  => wp_create_nonce( 'msh_hub_nonce' ),
        'isPro'      => msh_is_pro_active(),
        'stats'      => msh_get_job_stats(),
    ) );
} );
```

**AI #2 Usage (JavaScript):**
```javascript
// Access backend data
console.log( 'API URL:', mshHubData.apiUrl );
console.log( 'Is Pro:', mshHubData.isPro );
console.log( 'Current stats:', mshHubData.stats );

// Make authenticated requests
fetch( mshHubData.apiUrl + '/jobs/status', {
    headers: {
        'Authorization': 'Bearer ' + mshHubData.apiToken
    }
} );
```

---

## 7. Error Handling Contract

### WP_Error Standards (Both AIs)

**AI #1 Returns:**
```php
// On failure, always return WP_Error
if ( ! $valid ) {
    return new WP_Error(
        'invalid_job_type',
        __( 'Invalid job type specified', 'msh-image-optimizer' ),
        array( 'status' => 400 )
    );
}
```

**AI #2 Handles:**
```php
// Always check for WP_Error
$result = msh_enqueue_job( ... );

if ( is_wp_error( $result ) ) {
    wp_send_json_error( array(
        'message' => $result->get_error_message(),
        'code'    => $result->get_error_code(),
    ) );
}
```

---

## 8. Testing Contract

### Mock Data (AI #2 Uses Initially)

**Before AI #1 backend is ready, AI #2 can use mock data:**

```php
// In Cache tab - mock data for initial UI development
function get_mock_cache_entries() {
    return array(
        'items' => array(
            (object) array(
                'id'            => 1,
                'attachment_id' => 1686,
                'locale'        => 'es_ES',
                'field'         => 'alt',
                'ai_value'      => 'Fisioterapeuta ayudando a paciente',
                'manual_value'  => 'Terapia física',
                'chosen_source' => 'manual',
                'stale_reason'  => 'context_changed',
                'updated_at'    => '2025-10-19 14:30:00',
            ),
            // ... more mock items
        ),
        'total'       => 87,
        'total_pages' => 2,
    );
}
```

**Integration Day:** Replace mock with real `msh_get_cache_entries()` call

---

## 9. File Modification Rules

### Shared File: `msh-image-optimizer.php`

**AI #1 Section (Lines 1-100):**
```php
// Includes - Backend classes
require_once plugin_dir_path( __FILE__ ) . 'includes/automation/class-msh-job-engine.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/automation/class-msh-queue-manager.php';
// ... etc

// Initialize backend
if ( class_exists( 'MSH_Job_Engine' ) ) {
    MSH_Job_Engine::get_instance();
}
```

**AI #2 Section (Lines 101-200):**
```php
// Includes - Admin classes
require_once plugin_dir_path( __FILE__ ) . 'admin/class-msh-hub-page.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/tabs/class-msh-hub-cache-tab.php';
// ... etc

// Initialize admin
if ( class_exists( 'MSH_Hub_Page' ) ) {
    MSH_Hub_Page::get_instance();
}

// Enqueue assets
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'toplevel_page_msh-hub' ) {
        return;
    }

    wp_enqueue_style( 'msh-hub-css', plugin_dir_url( __FILE__ ) . 'assets/css/hub.css', array(), '2.0.0' );
    wp_enqueue_script( 'msh-hub-js', plugin_dir_url( __FILE__ ) . 'assets/js/hub.js', array( 'jquery' ), '2.0.0', true );
} );
```

**Rule:** AI #1 never touches lines 101+, AI #2 never touches lines 1-100

---

## 10. Approval & Sign-Off

### AI #1 (Backend) Commitments:

- [ ] Create 4 database tables by end of Day 3
- [ ] Provide 5 helper functions with exact signatures above
- [ ] Emit 3 WordPress actions at correct times
- [ ] Create 4 REST API endpoints with responses matching spec
- [ ] Populate `mshHubData` JavaScript object for AI #2
- [ ] Never modify files in `admin/` or `assets/` directories

**Signed:** AI #1 (Claude - Backend) ✅

---

### AI #2 (Frontend) Commitments:

- [ ] Use only helper functions defined above (no direct database queries)
- [ ] Call REST API endpoints with exact request format above
- [ ] Listen to WordPress actions for real-time updates
- [ ] Use mock data until AI #1 backend is ready (Day 6)
- [ ] Never modify files in `includes/` directory
- [ ] Enqueue CSS/JS only on Hub page (`toplevel_page_msh-hub` hook)

**Signed:** AI #2 (TBD - Frontend) ⏳

---

### User (Anastasia) Approval:

- [ ] I approve this interface contract
- [ ] Both AIs can begin coding with these agreements
- [ ] Any changes to this contract require my approval

**Signed:** Anastasia Volkova ⏳

---

## Next Steps After Approval

1. **AI #1 (Backend):** Start with database schema creation
2. **AI #2 (Frontend):** Start with Hub page skeleton (using mock data)
3. **Day 6:** Integration - AI #2 switches from mock to real data
4. **Daily:** Both AIs update this doc if new interfaces needed

---

**End of Interface Contract**

This is a living document - update as needed during development.
