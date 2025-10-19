# Phase 5 Implementation Plan
## "Metadata Hub" - Admin UI + Background Workers

**Status:** 🎯 Ready for Development
**Dependencies:** ✅ Phase 4R+ Complete
**Estimated Timeline:** 3-4 weeks (parallel Track A + B development)
**Last Updated:** October 19, 2025

---

## Overview

Phase 5 transforms the Phase 4R+ backend infrastructure into a fully functional, user-friendly admin interface with automated background processing.

### What Phase 4R+ Built (Backend Foundation)
- ✅ Metadata cache with transactional safety
- ✅ Event bus with idempotency
- ✅ Fingerprint-based staleness detection
- ✅ AI vs Manual decision layer
- ✅ Cloud sync driver (S3 ready)
- ✅ WP-CLI inspection tools

### What Phase 5 Adds (Frontend + Automation)
- 🎯 "Metadata Hub" admin page with 5 tabs
- 🎯 Background workers for automatic regeneration
- 🎯 Brand-compliant UI with The Dot visual identity
- 🎯 Pro upsell integration
- 🎯 Real-time monitoring dashboards

---

## Architecture: Track A + Track B Parallel Development

### Track A: Admin UI (Frontend)
**Developer:** AI #1
**Estimated Lines:** ~2,500 lines
**Timeline:** 2-3 weeks

**Files to Create:**
1. `admin/metadata-hub-page.php` (~800 lines) - Main page controller with tab routing
2. `admin/class-msh-hub-cache-tab.php` (~400 lines) - Cache browser UI
3. `admin/class-msh-hub-history-tab.php` (~350 lines) - Version history timeline
4. `admin/class-msh-hub-queue-tab.php` (~300 lines) - Worker management UI
5. `admin/class-msh-hub-events-tab.php` (~250 lines) - Event log viewer
6. `admin/class-msh-hub-sync-tab.php` (~200 lines) - Cloud sync UI (Pro)
7. `assets/css/metadata-hub.css` (~400 lines) - Brand-compliant styles
8. `assets/js/metadata-hub.js` (~600 lines) - AJAX, live feed, interactions

**Files to Modify:**
1. `includes/class-msh-optimizer-menu.php` - Add "Metadata Hub" menu item
2. `msh-image-optimizer.php` - Enqueue assets conditionally

### Track B: Background Workers (Automation)
**Developer:** AI #2
**Estimated Lines:** ~1,800 lines
**Timeline:** 2-3 weeks

**Files to Create:**
1. `includes/phase5/class-msh-regeneration-worker.php` (~500 lines) - Event consumer
2. `includes/phase5/class-msh-queue-manager.php` (~400 lines) - Priority queue logic
3. `includes/phase5/class-msh-batch-processor.php` (~350 lines) - Batch regeneration
4. `includes/phase5/class-msh-worker-health.php` (~250 lines) - Monitoring + alerts
5. `includes/class-msh-regen-cli.php` (~300 lines) - WP-CLI worker management

**Files to Modify:**
1. `msh-image-optimizer.php` - Initialize workers + cron hooks
2. `includes/phase4/class-msh-staleness-engine.php` - Add queue_regeneration hook

---

## Track A: Detailed Breakdown (Admin UI)

### File 1: `admin/metadata-hub-page.php`

**Purpose:** Main controller for "Metadata Hub" admin page

**Responsibilities:**
- Register admin menu item
- Handle tab routing (`?tab=cache`, `?tab=history`, etc.)
- Render tab navigation (WordPress nav-tab-wrapper)
- Enqueue CSS/JS conditionally
- Handle Pro feature access checks
- AJAX endpoints for all tabs

**Key Functions:**

```php
class MSH_Metadata_Hub_Page {

    /**
     * Add menu item
     */
    public function register_menu() {
        add_menu_page(
            __( 'Metadata Hub', 'msh-image-optimizer' ),
            __( 'Metadata Hub', 'msh-image-optimizer' ),
            'manage_options',
            'msh-metadata-hub',
            array( $this, 'render_page' ),
            'dashicons-database-view',
            30
        );
    }

    /**
     * Render main page with tab navigation
     */
    public function render_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'cache';

        // Render nav tabs
        $this->render_nav_tabs( $current_tab );

        // Render active tab content
        switch ( $current_tab ) {
            case 'cache':
                MSH_Hub_Cache_Tab::get_instance()->render();
                break;
            case 'history':
                MSH_Hub_History_Tab::get_instance()->render();
                break;
            case 'queue':
                MSH_Hub_Queue_Tab::get_instance()->render();
                break;
            case 'events':
                MSH_Hub_Events_Tab::get_instance()->render();
                break;
            case 'sync':
                if ( msh_is_pro_active() ) {
                    MSH_Hub_Sync_Tab::get_instance()->render();
                } else {
                    $this->render_pro_upsell();
                }
                break;
        }
    }

    /**
     * AJAX: Get metadata cache entries
     */
    public function ajax_get_cache() {
        check_ajax_referer( 'msh_hub_nonce' );

        $filters = array(
            'locale'     => isset( $_POST['locale'] ) ? sanitize_text_field( $_POST['locale'] ) : '',
            'staleness'  => isset( $_POST['staleness'] ) ? sanitize_text_field( $_POST['staleness'] ) : '',
            'source'     => isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '',
            'search'     => isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '',
            'page'       => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
            'per_page'   => 50,
        );

        $results = MSH_Metadata_Core::get_instance()->query_cache( $filters );

        wp_send_json_success( $results );
    }
}
```

**Template Structure:**

```php
<div class="wrap msh-metadata-hub">
    <h1><?php esc_html_e( 'Metadata Hub', 'msh-image-optimizer' ); ?></h1>

    <nav class="nav-tab-wrapper">
        <a href="?page=msh-metadata-hub&tab=cache"
           class="nav-tab <?php echo $current_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Cache', 'msh-image-optimizer' ); ?>
        </a>
        <a href="?page=msh-metadata-hub&tab=history"
           class="nav-tab <?php echo $current_tab === 'history' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'History', 'msh-image-optimizer' ); ?>
        </a>
        <a href="?page=msh-metadata-hub&tab=queue"
           class="nav-tab <?php echo $current_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Queue', 'msh-image-optimizer' ); ?>
        </a>
        <a href="?page=msh-metadata-hub&tab=events"
           class="nav-tab <?php echo $current_tab === 'events' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Events', 'msh-image-optimizer' ); ?>
        </a>
        <a href="#"
           class="nav-tab msh-pro-tab <?php echo $current_tab === 'sync' ? 'nav-tab-active' : ''; ?>"
           data-pro-feature="cloud-sync">
            <?php esc_html_e( 'Sync', 'msh-image-optimizer' ); ?> 🔒
            <span class="msh-pro-badge"><?php esc_html_e( 'PRO', 'msh-image-optimizer' ); ?></span>
        </a>
    </nav>

    <div class="msh-tab-content">
        <!-- Tab-specific content rendered here -->
    </div>
</div>
```

---

### File 2: `admin/class-msh-hub-cache-tab.php`

**Purpose:** Metadata cache browser with filters

**Features:**
- Filter by locale, staleness, source
- Search by image name/ID
- Compare AI vs Manual side-by-side
- Switch active source
- Bulk regenerate
- Export to CSV

**UI Components:**

```php
class MSH_Hub_Cache_Tab {

    public function render() {
        ?>
        <div class="msh-cache-tab">
            <!-- Filters -->
            <div class="msh-filters">
                <select id="msh-filter-locale">
                    <option value=""><?php esc_html_e( 'All Locales', 'msh-image-optimizer' ); ?></option>
                    <?php foreach ( $this->get_locales() as $locale ) : ?>
                        <option value="<?php echo esc_attr( $locale ); ?>">
                            <?php echo esc_html( $locale ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="msh-filter-staleness">
                    <option value=""><?php esc_html_e( 'All Statuses', 'msh-image-optimizer' ); ?></option>
                    <option value="fresh"><?php esc_html_e( 'Fresh', 'msh-image-optimizer' ); ?></option>
                    <option value="stale"><?php esc_html_e( 'Stale', 'msh-image-optimizer' ); ?></option>
                </select>

                <select id="msh-filter-source">
                    <option value=""><?php esc_html_e( 'All Sources', 'msh-image-optimizer' ); ?></option>
                    <option value="ai"><?php esc_html_e( 'AI', 'msh-image-optimizer' ); ?></option>
                    <option value="manual"><?php esc_html_e( 'Manual', 'msh-image-optimizer' ); ?></option>
                </select>

                <input type="text" id="msh-search" placeholder="<?php esc_attr_e( 'Search images...', 'msh-image-optimizer' ); ?>">

                <button class="button" id="msh-apply-filters">
                    <?php esc_html_e( 'Apply Filters', 'msh-image-optimizer' ); ?>
                </button>
            </div>

            <!-- Results Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="msh-select-all"></th>
                        <th><?php esc_html_e( 'Image', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Field', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Updated', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
                    </tr>
                </thead>
                <tbody id="msh-cache-results">
                    <!-- AJAX-loaded results -->
                </tbody>
            </table>

            <!-- Bulk Actions -->
            <div class="msh-bulk-actions">
                <button class="button" id="msh-bulk-regenerate">
                    <?php esc_html_e( 'Regenerate Selected', 'msh-image-optimizer' ); ?>
                </button>
                <button class="button" id="msh-export-csv">
                    <?php esc_html_e( 'Export to CSV', 'msh-image-optimizer' ); ?>
                </button>
            </div>

            <!-- Pagination -->
            <div class="msh-pagination">
                <!-- Pagination controls -->
            </div>
        </div>
        <?php
    }

    /**
     * AJAX endpoint for getting cache data
     */
    public function ajax_get_cache() {
        check_ajax_referer( 'msh_hub_nonce' );

        $filters = $this->sanitize_filters( $_POST );
        $results = MSH_Metadata_Core::get_instance()->query_cache( $filters );

        ob_start();
        foreach ( $results['items'] as $item ) {
            $this->render_row( $item );
        }
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html'        => $html,
            'total'       => $results['total'],
            'total_pages' => $results['total_pages'],
        ) );
    }
}
```

---

### File 3: `assets/css/metadata-hub.css`

**Purpose:** Brand-compliant styles for Metadata Hub

**Key Variables:**

```css
:root {
    --msh-charcoal: #35332f;
    --msh-lime: #daff00;
    --msh-warm-gray: #8b8883;
    --msh-cream: #FAF9F6;
    --msh-font-heading: 'futura-pt', sans-serif;
    --msh-font-body: 'ff-real-text-pro', sans-serif;
}
```

**Tab Navigation:**

```css
.msh-metadata-hub .nav-tab-wrapper {
    border-bottom: 1px solid var(--msh-charcoal);
    margin-bottom: 30px;
}

.msh-metadata-hub .nav-tab {
    font-family: var(--msh-font-heading);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--msh-warm-gray);
    border: none;
    background: transparent;
    padding: 12px 24px;
    transition: color 0.2s, border-bottom 0.2s;
}

.msh-metadata-hub .nav-tab-active,
.msh-metadata-hub .nav-tab:hover {
    color: var(--msh-charcoal);
    border-bottom: 2px solid var(--msh-lime);
    background: transparent;
}

.msh-metadata-hub .msh-pro-tab {
    opacity: 0.7;
}

.msh-metadata-hub .msh-pro-badge {
    background: var(--msh-lime);
    color: var(--msh-charcoal);
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 0.7em;
    font-weight: 700;
    margin-left: 6px;
}
```

**Filters:**

```css
.msh-filters {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    padding: 20px;
    background: var(--msh-cream);
    border-radius: 8px;
}

.msh-filters select,
.msh-filters input[type="text"] {
    font-family: var(--msh-font-body);
    padding: 8px 12px;
    border: 1px solid var(--msh-warm-gray);
    border-radius: 4px;
}
```

**Status Badges:**

```css
.msh-status-fresh {
    color: #46b450;
    font-weight: 600;
}

.msh-status-stale {
    color: #dc3232;
    font-weight: 600;
}

.msh-status-reason {
    font-size: 0.9em;
    color: var(--msh-warm-gray);
}
```

---

### File 4: `assets/js/metadata-hub.js`

**Purpose:** Interactive behaviors for all tabs

**Key Features:**
- AJAX filtering and pagination
- Live event feed updates
- Slide-out panels for metadata comparison
- Pro modal trigger
- Bulk actions

**Core Functions:**

```javascript
(function($) {
    'use strict';

    const MetadataHub = {

        /**
         * Initialize on page load
         */
        init: function() {
            this.bindEvents();
            this.loadInitialData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Filter changes
            $('#msh-apply-filters').on('click', this.applyFilters.bind(this));

            // Bulk actions
            $('#msh-bulk-regenerate').on('click', this.bulkRegenerate.bind(this));
            $('#msh-export-csv').on('click', this.exportCSV.bind(this));

            // Row actions
            $(document).on('click', '.msh-view-both', this.viewBoth.bind(this));
            $(document).on('click', '.msh-switch-source', this.switchSource.bind(this));

            // Pro tab click
            $('.msh-pro-tab').on('click', this.showProModal.bind(this));

            // Live feed (if on Events tab)
            if ($('#msh-events-feed').length) {
                this.startLiveFeed();
            }
        },

        /**
         * Apply filters via AJAX
         */
        applyFilters: function(e) {
            e.preventDefault();

            const filters = {
                locale: $('#msh-filter-locale').val(),
                staleness: $('#msh-filter-staleness').val(),
                source: $('#msh-filter-source').val(),
                search: $('#msh-search').val(),
                page: 1
            };

            this.loadCache(filters);
        },

        /**
         * Load cache data via AJAX
         */
        loadCache: function(filters) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'msh_get_cache',
                    nonce: mshHubData.nonce,
                    ...filters
                },
                beforeSend: function() {
                    $('#msh-cache-results').html('<tr><td colspan="8">Loading...</td></tr>');
                },
                success: function(response) {
                    if (response.success) {
                        $('#msh-cache-results').html(response.data.html);
                        MetadataHub.updatePagination(response.data.total_pages);
                    }
                }
            });
        },

        /**
         * Show AI vs Manual comparison slide-out
         */
        viewBoth: function(e) {
            e.preventDefault();

            const $row = $(e.currentTarget).closest('tr');
            const attachmentId = $row.data('attachment-id');
            const locale = $row.data('locale');
            const field = $row.data('field');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'msh_get_both_versions',
                    nonce: mshHubData.nonce,
                    attachment_id: attachmentId,
                    locale: locale,
                    field: field
                },
                success: function(response) {
                    if (response.success) {
                        MetadataHub.showSlideOut(response.data);
                    }
                }
            });
        },

        /**
         * Live feed for Events tab
         */
        startLiveFeed: function() {
            setInterval(function() {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'msh_get_recent_events',
                        nonce: mshHubData.nonce,
                        since: $('#msh-events-feed').data('last-event-id')
                    },
                    success: function(response) {
                        if (response.success && response.data.events.length > 0) {
                            MetadataHub.prependEvents(response.data.events);
                            $('#msh-events-feed').data('last-event-id', response.data.latest_id);
                        }
                    }
                });
            }, 5000); // Poll every 5 seconds
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        MetadataHub.init();
    });

})(jQuery);
```

---

## Track B: Detailed Breakdown (Background Workers)

### File 1: `includes/phase5/class-msh-regeneration-worker.php`

**Purpose:** Consume `metadata.regen_queued` events and regenerate metadata

**Responsibilities:**
- Poll event bus for unprocessed events
- Fetch attachment + context data
- Call AI service to regenerate metadata
- Update cache with new AI value
- Mark event as processed
- Handle errors and retries

**Key Functions:**

```php
class MSH_Regeneration_Worker {

    /**
     * Process next batch of events
     *
     * @param int $batch_size How many to process
     * @return array Processing results
     */
    public function process_batch( $batch_size = 10 ) {
        global $wpdb;

        // Get unprocessed events from queue
        $events = MSH_Event_Bus::get_instance()->get_unprocessed_events( $batch_size );

        if ( empty( $events ) ) {
            return array( 'processed' => 0, 'skipped' => 0, 'errors' => 0 );
        }

        $results = array( 'processed' => 0, 'skipped' => 0, 'errors' => 0 );

        foreach ( $events as $event ) {
            $result = $this->process_event( $event );

            if ( $result['status'] === 'success' ) {
                $results['processed']++;
                MSH_Event_Bus::get_instance()->mark_processed( $event->id );
            } elseif ( $result['status'] === 'skip' ) {
                $results['skipped']++;
                MSH_Event_Bus::get_instance()->mark_processed( $event->id );
            } else {
                $results['errors']++;
                // Leave unprocessed for retry
            }
        }

        return $results;
    }

    /**
     * Process single event
     *
     * @param object $event Event data
     * @return array Processing result
     */
    private function process_event( $event ) {
        // Parse payload
        $payload = json_decode( $event->payload, true );

        if ( ! isset( $payload['attachment_id'], $payload['locale'], $payload['field'] ) ) {
            return array( 'status' => 'skip', 'reason' => 'invalid_payload' );
        }

        $attachment_id = $payload['attachment_id'];
        $locale        = $payload['locale'];
        $field         = $payload['field'];

        // Check if manual edit exists
        $cache = MSH_Metadata_Core::get_instance()->get_cache( $attachment_id, $locale, $field );

        if ( ! empty( $cache['manual_value'] ) && 'manual' === $cache['chosen_source'] ) {
            // Don't regenerate if manual is active - just update AI value silently
            $ai_value = $this->generate_metadata( $attachment_id, $locale, $field );

            if ( is_wp_error( $ai_value ) ) {
                return array( 'status' => 'error', 'reason' => $ai_value->get_error_message() );
            }

            // Update AI value only (manual stays active)
            MSH_Metadata_Core::get_instance()->update_ai_value( $attachment_id, $locale, $field, $ai_value );

            return array( 'status' => 'success', 'action' => 'updated_ai_background' );
        }

        // Regenerate and activate AI value
        $ai_value = $this->generate_metadata( $attachment_id, $locale, $field );

        if ( is_wp_error( $ai_value ) ) {
            return array( 'status' => 'error', 'reason' => $ai_value->get_error_message() );
        }

        // Save and activate
        MSH_Metadata_Core::get_instance()->save(
            $attachment_id,
            $locale,
            $field,
            $ai_value,
            '', // No manual value
            'ai' // Choose AI
        );

        return array( 'status' => 'success', 'action' => 'regenerated' );
    }

    /**
     * Generate metadata via AI service
     *
     * @param int $attachment_id Attachment ID
     * @param string $locale Locale code
     * @param string $field Field name
     * @return string|WP_Error Generated metadata
     */
    private function generate_metadata( $attachment_id, $locale, $field ) {
        // Get AI service
        $ai_service = MSH_AI_Service::get_instance();

        // Build prompt with context
        $context = $this->build_context( $attachment_id, $locale );
        $prompt  = $this->build_prompt( $attachment_id, $locale, $field, $context );

        // Call AI
        $response = $ai_service->generate( $prompt, array( 'locale' => $locale ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Validate
        $decision_layer = MSH_Decision_Layer::get_instance();
        $is_valid = $decision_layer->validate_value( $field, $response['text'] );

        if ( ! $is_valid ) {
            return new WP_Error( 'invalid_ai_output', 'AI output failed validation' );
        }

        return $response['text'];
    }
}
```

---

### File 2: `includes/phase5/class-msh-queue-manager.php`

**Purpose:** Manage priority queue for regeneration

**Priority Levels:**
1. **High:** Manual overrides (user manually marked stale)
2. **Medium:** Glossary/locale changes (affects multiple images)
3. **Normal:** Context changes (post updated)

**Key Functions:**

```php
class MSH_Queue_Manager {

    /**
     * Get queue statistics
     *
     * @return array Queue stats
     */
    public function get_stats() {
        global $wpdb;

        $event_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );

        $stats = array(
            'high_priority'   => 0,
            'medium_priority' => 0,
            'normal_priority' => 0,
            'total'           => 0,
            'processing'      => 0,
        );

        // Count by priority
        $high = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$event_table}
            WHERE event = %s
            AND processed_at IS NULL
            AND JSON_EXTRACT(payload, '$.priority') = 'high'",
            'metadata.regen_queued'
        ) );

        $medium = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$event_table}
            WHERE event = %s
            AND processed_at IS NULL
            AND JSON_EXTRACT(payload, '$.priority') = 'medium'",
            'metadata.regen_queued'
        ) );

        $normal = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$event_table}
            WHERE event = %s
            AND processed_at IS NULL
            AND (JSON_EXTRACT(payload, '$.priority') IS NULL OR JSON_EXTRACT(payload, '$.priority') = 'normal')",
            'metadata.regen_queued'
        ) );

        $stats['high_priority']   = (int) $high;
        $stats['medium_priority'] = (int) $medium;
        $stats['normal_priority'] = (int) $normal;
        $stats['total']           = $stats['high_priority'] + $stats['medium_priority'] + $stats['normal_priority'];

        return $stats;
    }

    /**
     * Get next batch prioritized
     *
     * @param int $batch_size Batch size
     * @return array Events ordered by priority
     */
    public function get_next_batch( $batch_size = 10 ) {
        global $wpdb;

        $event_table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_EVENTS );

        // Get high priority first, then medium, then normal
        $events = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$event_table}
            WHERE event = %s
            AND processed_at IS NULL
            ORDER BY
                CASE JSON_EXTRACT(payload, '$.priority')
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    ELSE 3
                END,
                created_at ASC
            LIMIT %d",
            'metadata.regen_queued',
            $batch_size
        ) );

        return $events;
    }
}
```

---

### File 3: `includes/class-msh-regen-cli.php`

**Purpose:** WP-CLI commands for worker management

**Commands:**

```bash
# Process next batch manually
wp msh regen process --batch=50

# Get queue status
wp msh regen status

# Start worker (continuous processing)
wp msh regen start --interval=60

# Manually queue image for regeneration
wp msh regen queue 1686 es_ES alt --priority=high
```

**Implementation:**

```php
class MSH_Regen_CLI {

    /**
     * Process next batch of regeneration queue
     *
     * @synopsis [--batch=<size>] [--timeout=<seconds>]
     */
    public function process( $args, $assoc_args ) {
        $batch_size = isset( $assoc_args['batch'] ) ? (int) $assoc_args['batch'] : 10;
        $timeout    = isset( $assoc_args['timeout'] ) ? (int) $assoc_args['timeout'] : 300;

        $worker = MSH_Regeneration_Worker::get_instance();

        WP_CLI::line( sprintf( 'Processing batch of %d...', $batch_size ) );

        $results = $worker->process_batch( $batch_size );

        WP_CLI::success( sprintf(
            'Processed: %d | Skipped: %d | Errors: %d',
            $results['processed'],
            $results['skipped'],
            $results['errors']
        ) );
    }

    /**
     * Get queue status
     */
    public function status( $args, $assoc_args ) {
        $queue_manager = MSH_Queue_Manager::get_instance();
        $stats = $queue_manager->get_stats();

        WP_CLI::line( 'Queue Status:' );
        WP_CLI::line( sprintf( '  High Priority:   %d', $stats['high_priority'] ) );
        WP_CLI::line( sprintf( '  Medium Priority: %d', $stats['medium_priority'] ) );
        WP_CLI::line( sprintf( '  Normal Priority: %d', $stats['normal_priority'] ) );
        WP_CLI::line( sprintf( '  Total Pending:   %d', $stats['total'] ) );
    }
}
```

---

## Integration Points

### Track A ↔ Track B Integration

**1. Cache Tab calls Queue Manager:**

```php
// In admin/class-msh-hub-cache-tab.php
$queue_stats = MSH_Queue_Manager::get_instance()->get_stats();
echo sprintf( '%d items in regeneration queue', $queue_stats['total'] );
```

**2. Queue Tab triggers Worker:**

```php
// In admin/class-msh-hub-queue-tab.php
if ( isset( $_POST['process_now'] ) ) {
    $worker = MSH_Regeneration_Worker::get_instance();
    $results = $worker->process_batch( 50 );
    // Show results
}
```

**3. Worker updates trigger Event Log refresh:**

```javascript
// In assets/js/metadata-hub.js
// After bulk regenerate completes, refresh Events tab if open
if ($('#msh-events-feed').length) {
    MetadataHub.refreshEvents();
}
```

---

## WordPress Standards Compliance

### Security
- ✅ Nonces for all AJAX requests
- ✅ `check_ajax_referer()` on every endpoint
- ✅ `current_user_can('manage_options')` for all admin pages
- ✅ SQL prepared statements
- ✅ Input sanitization (`sanitize_text_field`, `absint`)
- ✅ Output escaping (`esc_html`, `esc_attr`, `esc_url`)

### i18n
- ✅ All strings wrapped in `__()` or `esc_html__()`
- ✅ Text domain: `'msh-image-optimizer'`
- ✅ Load translations: `load_plugin_textdomain()`

### Accessibility
- ✅ ARIA labels on interactive elements
- ✅ Keyboard navigation support
- ✅ Focus indicators
- ✅ Screen reader announcements

### Performance
- ✅ Assets enqueued conditionally (only on Metadata Hub page)
- ✅ AJAX pagination (don't load all results)
- ✅ Batch processing (limit 50 per request)
- ✅ Debounced search input (300ms delay)

---

## Testing Strategy

### Track A Testing (UI)

**Manual Testing:**
1. Navigate to "The Dot → Metadata Hub"
2. Test each tab loads without errors
3. Test filters on Cache tab
4. Test search functionality
5. Test "View Both" slide-out panel
6. Test switching between AI and Manual
7. Test Pro tab shows upsell modal
8. Test bulk actions
9. Test CSV export
10. Test pagination

**Browser Testing:**
- Chrome, Firefox, Safari, Edge
- Mobile responsive (iPad, iPhone)

### Track B Testing (Workers)

**Unit Tests (PHPUnit):**

```php
class Test_Regeneration_Worker extends WP_UnitTestCase {

    public function test_process_batch() {
        // Create test events
        $event_id = MSH_Event_Bus::get_instance()->emit( 'metadata.regen_queued', 'attachment', 1686, array(
            'attachment_id' => 1686,
            'locale'        => 'es_ES',
            'field'         => 'alt',
        ) );

        // Process
        $worker = MSH_Regeneration_Worker::get_instance();
        $results = $worker->process_batch( 1 );

        // Assert
        $this->assertEquals( 1, $results['processed'] );

        // Verify event marked processed
        $event = MSH_Event_Bus::get_instance()->get_event( $event_id );
        $this->assertNotNull( $event->processed_at );
    }
}
```

**WP-CLI Testing:**

```bash
# Test queue status
wp msh regen status

# Test processing
wp msh regen process --batch=5

# Verify results
wp msh metadata stats
```

---

## Deployment Checklist

### Pre-Deployment

- [ ] All Track A files created
- [ ] All Track B files created
- [ ] Menu item registered
- [ ] Assets enqueued
- [ ] AJAX endpoints registered
- [ ] WP-CLI commands registered
- [ ] Database schema unchanged (use existing Phase 4R+ tables)
- [ ] i18n strings extracted
- [ ] Security audit passed
- [ ] Browser testing passed
- [ ] WP-CLI testing passed

### Post-Deployment

- [ ] Verify menu item appears
- [ ] Test all 5 tabs load
- [ ] Test worker processes events
- [ ] Monitor error logs
- [ ] Check queue stats weekly
- [ ] User acceptance testing

---

## Timeline Estimate

### Week 1-2: Track A (UI)
- Day 1-2: Main page + tab routing
- Day 3-4: Cache tab UI
- Day 5-6: History tab UI
- Day 7-8: Queue + Events + Sync tabs
- Day 9-10: CSS + JS + testing

### Week 2-3: Track B (Workers)
- Day 1-2: Regeneration worker
- Day 3-4: Queue manager
- Day 5-6: Batch processor
- Day 7-8: WP-CLI commands
- Day 9-10: Integration + testing

### Week 3-4: Integration + Polish
- Day 1-2: Track A ↔ Track B integration
- Day 3-4: Bug fixes
- Day 5-6: Security audit
- Day 7-8: Performance optimization
- Day 9-10: Documentation + deployment

---

## Success Criteria

### Phase 5 Complete When:

- ✅ "Metadata Hub" menu item visible
- ✅ All 5 tabs functional
- ✅ Cache browser shows AI vs Manual
- ✅ Version history displays correctly
- ✅ Queue tab shows worker status
- ✅ Events tab live feed working
- ✅ Pro upsell modal triggers on Sync tab
- ✅ Background worker processes events
- ✅ Queue prioritization working
- ✅ WP-CLI commands functional
- ✅ All WordPress standards met
- ✅ No console errors
- ✅ Mobile responsive
- ✅ Accessible (WCAG AA)
- ✅ i18n ready

---

## Next Steps

**Ready to begin Phase 5 development?**

**Option 1: Parallel Development (Faster)**
- Track A Developer: Start with `admin/metadata-hub-page.php`
- Track B Developer: Start with `includes/phase5/class-msh-regeneration-worker.php`
- Timeline: 3 weeks

**Option 2: Sequential Development (Safer)**
- Week 1-2: Track A (UI first, WP-CLI testing)
- Week 3-4: Track B (Workers connect to existing UI)
- Timeline: 4 weeks

**Recommendation:** Parallel development, daily sync meetings between Track A and Track B developers.

---

**End of Phase 5 Plan**

**Questions Before Starting?**
- Preferred development approach (parallel vs sequential)?
- Any UI/UX preferences beyond brand guidelines?
- Worker processing frequency (WP-Cron every 5 min, hourly, manual)?
- Pro feature pricing confirmed ($99/year)?
