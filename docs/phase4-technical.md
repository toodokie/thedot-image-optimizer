# Phase 4R+ Technical Documentation
## Intelligent Metadata Orchestration

**Version:** 2.0.0
**Status:** Foundation Complete (Track A) | Core Services In Progress (Track B)
**Author:** The Dot Development Team

---

## Overview

Phase 4R+ transforms metadata from scattered post meta into a self-healing, event-driven orchestration layer. Instead of enterprise workflow management, it focuses on **metadata infrastructure** with intelligent staleness detection, version control, and optional cloud sync.

### Core Principles

1. **Event-Driven Architecture** - Changes emit events, workers consume asynchronously
2. **Fingerprint-Based Staleness** - SHA1 hashes detect when metadata needs regeneration
3. **AI + Manual Coexistence** - Store both values, let policy decide which is active
4. **Version Control** - Full history with diffs for auditing
5. **Cloud Sync Optional** - Pro feature with driver pattern for S3/Supabase

### Admin UI Structure (Phase 5)

All Phase 4R+/5 features will be consolidated under a single **"Metadata Hub"** menu item with tabbed navigation:

```
📊 The Dot Menu
├── Dashboard
├── ──────────
├── Image Optimizer
├── Context Analytics
├── Locale Profiles
├── Glossary
├── Metadata Hub ← All Phase 4R+/5 features here
│   └── Tabs:
│       ├── [Cache] - Browse metadata (AI vs Manual)
│       ├── [History] - Version timeline & rollback
│       ├── [Queue] - Regeneration worker status
│       ├── [Events] - Event log monitoring
│       └── [Sync] 🔒 PRO - Cloud sync (S3/Supabase)
├── ──────────
└── Settings
```

**Design Rationale:**
- Clean menu structure (1 new item instead of 5)
- Related features grouped logically
- Tab navigation is intuitive
- Pro features visible but locked for upsell

---

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Phase 4R+ Architecture                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │  Event Bus   │───▶│ Staleness    │───▶│  Decision    │ │
│  │  (Track A)   │    │  Engine      │    │  Layer       │ │
│  │              │    │  (Track B)   │    │  (Track B)   │ │
│  └──────────────┘    └──────────────┘    └──────────────┘ │
│         │                    │                    │         │
│         ▼                    ▼                    ▼         │
│  ┌──────────────────────────────────────────────────────┐  │
│  │          Metadata Cache (Single Source of Truth)     │  │
│  │  - attachment_id, locale, field                      │  │
│  │  - ai_value + manual_value                           │  │
│  │  - chosen_source (manual|ai)                         │  │
│  │  - input_fingerprint (SHA1)                          │  │
│  │  - stale_reason                                      │  │
│  └──────────────────────────────────────────────────────┘  │
│         │                    │                              │
│         ▼                    ▼                              │
│  ┌──────────────┐    ┌──────────────────┐                 │
│  │  Versions    │    │  Cloud Sync      │                 │
│  │  (Track B)   │    │  (Track B - Pro) │                 │
│  └──────────────┘    └──────────────────┘                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **Content Change** → Event Bus emits `post.updated`
2. **Fingerprint Calculation** → Compare current vs. stored fingerprint
3. **Staleness Detection** → Mark as stale with reason
4. **Worker Consumption** → Process event, regenerate metadata
5. **Decision Layer** → Choose AI vs. manual based on policy
6. **Version Recording** → Store new version with diff
7. **Cloud Sync** (Pro) → Push to S3/Supabase with ETag

---

## Database Schema

### Table 1: `wp_optimizer_metadata_cache`

**Purpose:** Central source of truth for all image metadata across all locales

```sql
CREATE TABLE wp_optimizer_metadata_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attachment_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(12) NOT NULL DEFAULT 'en_US',
    field ENUM('title','alt','caption','description') NOT NULL,
    ai_value LONGTEXT,
    manual_value LONGTEXT,
    chosen_source ENUM('manual','ai') DEFAULT 'manual',
    input_fingerprint CHAR(40),
    stale_reason ENUM(
        'fresh',
        'context_changed',
        'locale_updated',
        'glossary_changed',
        'template_changed',
        'file_replaced',
        'manual_override'
    ) DEFAULT 'fresh',
    ai_model VARCHAR(64),
    updated_at DATETIME,
    created_at DATETIME,
    UNIQUE KEY unique_metadata (attachment_id, locale, field),
    KEY idx_stale (attachment_id, stale_reason),
    KEY idx_locale (locale),
    KEY idx_fingerprint (input_fingerprint)
);
```

**Key Fields:**

- `chosen_source` - Which value is currently active (manual always wins)
- `input_fingerprint` - SHA1 hash of input signals for staleness detection
- `stale_reason` - Why metadata needs regeneration (or 'fresh' if current)

**Example Row:**

```
attachment_id: 1686
locale: es_ES
field: alt
ai_value: "Fisioterapeuta ayudando a paciente con rehabilitación de rodilla"
manual_value: "Terapia física profesional"
chosen_source: manual
input_fingerprint: 102e047175bccad284f4b27915a0ffd9de735580
stale_reason: fresh
```

### Table 2: `wp_optimizer_metadata_versions`

**Purpose:** Version history for auditing and rollback

```sql
CREATE TABLE wp_optimizer_metadata_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cache_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    source ENUM('ai','manual','import') NOT NULL,
    value LONGTEXT,
    input_fingerprint CHAR(40),
    created_at DATETIME,
    notes VARCHAR(255),
    KEY idx_cache_id (cache_id),
    KEY idx_created (created_at)
);
```

**Notes Field:** Optional user-provided context for why change was made

### Table 3: `wp_optimizer_events`

**Purpose:** Event log for observability and worker consumption

```sql
CREATE TABLE wp_optimizer_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(64) NOT NULL,
    entity_type ENUM('post','attachment','site') NOT NULL,
    entity_id BIGINT UNSIGNED,
    payload LONGTEXT,
    trigger_user_id BIGINT UNSIGNED,
    idempotency_key VARCHAR(64),
    processed_at DATETIME,
    created_at DATETIME,
    KEY idx_event (event, created_at),
    KEY idx_processed (processed_at),
    KEY idx_entity (entity_type, entity_id),
    UNIQUE KEY unique_event (idempotency_key)
);
```

**Idempotency:** `idempotency_key` prevents duplicate events from being emitted

**Event Types:**
- `post.updated` - Post content changed
- `attachment.uploaded` - New image uploaded
- `attachment.replaced` - Image file replaced
- `locale.added` - New locale profile created
- `glossary.updated` - Glossary terms changed
- `template.updated` - Prompt template changed
- `metadata.manual_edit` - User manually edited metadata

### Table 4: `wp_optimizer_sync_state` (Pro)

**Purpose:** Track cloud sync state for conflict resolution

```sql
CREATE TABLE wp_optimizer_sync_state (
    attachment_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(12) NOT NULL DEFAULT 'en_US',
    field ENUM('title','alt','caption','description') NOT NULL,
    remote_etag VARCHAR(64),
    last_push DATETIME,
    last_pull DATETIME,
    PRIMARY KEY (attachment_id, locale, field),
    KEY idx_push (last_push),
    KEY idx_pull (last_pull)
);
```

**ETag Strategy:** Use fingerprint as ETag to detect remote changes

---

## Event Bus System

**Class:** `MSH_Event_Bus`
**File:** `includes/class-msh-event-bus.php`
**Status:** ✅ Complete (Track A)

### Key Methods

#### `emit( $event, $entity_type, $entity_id, $payload, $idempotency_key )`

Emit event to event log with idempotency protection.

**Parameters:**
- `$event` (string) - Event name (e.g., 'post.updated')
- `$entity_type` (string) - 'post', 'attachment', or 'site'
- `$entity_id` (int|null) - Entity ID (null for site-wide events)
- `$payload` (array) - Event payload data
- `$idempotency_key` (string|null) - Optional key to prevent duplicates

**Returns:** `int|false` - Event ID or false on failure

**Example:**

```php
$event_bus = MSH_Event_Bus::get_instance();
$event_id = $event_bus->emit(
    'post.updated',
    'post',
    123,
    array(
        'post_type' => 'page',
        'post_title' => 'About Us',
    )
);
```

#### `get_unprocessed( $event, $limit )`

Get unprocessed events for worker consumption.

**Parameters:**
- `$event` (string|null) - Filter by event type
- `$limit` (int) - Maximum events to fetch (default: 100)

**Returns:** `array` - Array of event objects with decoded payload

**Example:**

```php
$events = $event_bus->get_unprocessed( 'post.updated', 50 );
foreach ( $events as $event ) {
    // Process event
    process_post_update( $event->entity_id, $event->payload );

    // Mark as processed
    $event_bus->mark_processed( $event->id );
}
```

#### `mark_processed( $event_id )`

Mark event as processed.

**Returns:** `bool` - Success status

### WordPress Hooks

The Event Bus automatically hooks into WordPress events:

```php
add_action( 'save_post', array( $this, 'on_post_updated' ) );
add_action( 'add_attachment', array( $this, 'on_attachment_uploaded' ) );
add_action( 'wp_update_attachment_metadata', array( $this, 'on_attachment_replaced' ) );
add_action( 'updated_post_meta', array( $this, 'on_metadata_manual_edit' ) );
```

**Custom Hooks for Phase 3:**

```php
add_action( 'msh_locale_created', array( $this, 'on_locale_added' ) );
add_action( 'msh_glossary_updated', array( $this, 'on_glossary_updated' ) );
add_action( 'msh_template_updated', array( $this, 'on_template_updated' ) );
```

### Action Hooks for Consumers

```php
// Generic event emission
do_action( 'msh_event_emitted', $event, $entity_type, $entity_id, $payload, $event_id );

// Specific event types
do_action( 'msh_event_post.updated', $entity_type, $entity_id, $payload, $event_id );
do_action( 'msh_event_attachment.replaced', $entity_type, $entity_id, $payload, $event_id );
```

---

## Fingerprint Builder

**Class:** `MSH_Fingerprint_Builder`
**File:** `includes/class-msh-fingerprint-builder.php`
**Status:** ✅ Complete (Track A)

### Purpose

Calculate SHA1 fingerprints from input signals to detect when metadata should be regenerated. A fingerprint change = metadata is stale.

### Input Signals

```php
$signals = array(
    'page_context'   => md5( /* where image appears */ ),
    'image_features' => md5( /* file hash, dimensions, phash */ ),
    'locale_profile' => md5( /* language, region, cultural context */ ),
    'template'       => md5( /* prompt template content */ ),
    'model_prompt'   => md5( /* AI model + system prompt */ ),
    'glossary'       => md5( /* locale-specific terms */ ),
);

$fingerprint = sha1( json_encode( $signals ) ); // Sort signals first
```

### Key Methods

#### `build_fingerprint( $attachment_id, $locale, $field )`

Calculate fingerprint for attachment metadata.

**Parameters:**
- `$attachment_id` (int) - Attachment ID
- `$locale` (string) - Locale code (e.g., 'en_US')
- `$field` (string) - 'title', 'alt', 'caption', or 'description'

**Returns:** `string` - SHA1 fingerprint (40 chars)

**Example:**

```php
$fingerprint_builder = MSH_Fingerprint_Builder::get_instance();
$fingerprint = $fingerprint_builder->build_fingerprint( 1686, 'es_ES', 'alt' );
// Returns: 102e047175bccad284f4b27915a0ffd9de735580
```

#### `detect_staleness_reason( $attachment_id, $locale, $field, $stored_fingerprint )`

Compare fingerprints and determine staleness reason.

**Returns:** `string|null` - Staleness reason or null if fresh

**Possible Reasons:**
- `context_changed` - Post content where image appears changed
- `file_replaced` - Image file was replaced
- `locale_updated` - Locale profile settings changed
- `glossary_changed` - Glossary terms updated
- `template_changed` - Prompt template modified
- `manual_override` - User manually edited metadata

**Example:**

```php
$stored = '102e047175bccad284f4b27915a0ffd9de735580';
$reason = $fingerprint_builder->detect_staleness_reason( 1686, 'es_ES', 'alt', $stored );

if ( $reason ) {
    echo "Metadata is stale because: $reason";
} else {
    echo "Metadata is fresh";
}
```

### Signal Details

#### 1. Page Context Hash

**Query:** Find all posts where image appears (content or featured image)

```sql
SELECT ID FROM wp_posts
WHERE post_status = 'publish'
AND (
    post_content LIKE '%wp-image-{attachment_id}%'
    OR ID IN (
        SELECT post_id FROM wp_postmeta
        WHERE meta_key = '_thumbnail_id' AND meta_value = {attachment_id}
    )
)
```

**Context Data:**
- Post ID
- Intent (from Context Fusion Phase 2)
- Primary keyword
- Top 5 keywords
- Content hash (MD5 of first 16 chars)

#### 2. Image Features Hash

**Data Sources:**
- File hash: `md5_file( get_attached_file( $attachment_id ) )`
- Dimensions: `wp_get_attachment_metadata()['width']` and `['height']`
- Perceptual hash: `get_post_meta( $attachment_id, 'msh_phash' )`

#### 3. Locale Profile Hash

**Database Query:**

```sql
SELECT language, region, cultural_context, formality, tone
FROM wp_msh_locale_profiles
WHERE locale_code = '{locale}'
```

**Fallback:** If no profile exists, hash just the locale code

#### 4. Template Hash

**Database Query:**

```sql
SELECT prompt_template
FROM wp_msh_locale_profiles
WHERE locale_code = '{locale}'
```

**Note:** Currently uses same template for all fields. Future: field-specific templates.

#### 5. Model + Prompt Hash

**Options:**
- `msh_ai_model` (e.g., 'gpt-4o-mini')
- `msh_system_prompt` (system-level instructions)

**Hash:** `md5( json_encode( array( 'model' => $model, 'prompt' => $prompt ) ) )`

#### 6. Glossary Hash

**Database Query:**

```sql
SELECT glossary FROM wp_msh_locale_profiles WHERE locale_code = '{locale}'
```

**Processing:** Decode JSON, sort keys, hash result

---

## WP-CLI Commands

**Class:** `MSH_Metadata_CLI`
**File:** `includes/class-msh-metadata-cli.php`
**Status:** ✅ Complete (Track A)

### `wp msh metadata fingerprint`

Calculate and inspect fingerprint for attachment metadata.

**Syntax:**

```bash
wp msh metadata fingerprint <attachment_id> <locale> <field> [--verbose]
```

**Arguments:**
- `<attachment_id>` - Attachment ID
- `<locale>` - Locale code (e.g., 'en_US', 'es_ES')
- `<field>` - 'title', 'alt', 'caption', or 'description'

**Options:**
- `--verbose` - Show detailed signal breakdown

**Example:**

```bash
wp msh metadata fingerprint 1686 es_ES alt --verbose
```

**Output:**

```
Success: Fingerprint: 102e047175bccad284f4b27915a0ffd9de735580

Signal Breakdown:
  - page_context: 112111a91ae1aea4ec4f7dc23748b28f
  - image_features: f0b2a22e21f60d46d5784b2639960deb
  - locale_profile: 71095c56c641f2c4a4f189b9dfcd7a38
  - template: 40bf2cd45f8bac148bbb696022119b69
  - model_prompt: 886f7e23fdc6054b5e6d7b4b0883f58c
  - glossary: d8e8fca2dc0f896fd7cb4cb0031ba249
```

### `wp msh metadata events`

List events from event bus.

**Syntax:**

```bash
wp msh metadata events [--unprocessed] [--event=<event>] [--limit=<N>] [--format=<format>]
```

**Options:**
- `--unprocessed` - Show only unprocessed events
- `--event=<event>` - Filter by event type (e.g., 'post.updated')
- `--limit=<N>` - Number of events to show (default: 20)
- `--format=<format>` - Output format: table, json, csv (default: table)

**Example:**

```bash
wp msh metadata events --unprocessed --limit=50
```

**Output:**

```
ID  Event              Entity           User  Created              Processed
1   post.updated       post:123         1     2025-10-19 14:30:00  pending
2   attachment.uploaded attachment:456  1     2025-10-19 14:31:00  pending
```

### `wp msh metadata cache`

Show metadata cache for attachment.

**Syntax:**

```bash
wp msh metadata cache <attachment_id> [--locale=<locale>] [--format=<format>]
```

**Options:**
- `--locale=<locale>` - Filter by locale (default: all locales)
- `--format=<format>` - Output format: table, json, yaml (default: table)

**Example:**

```bash
wp msh metadata cache 1686 --locale=es_ES
```

**Output:**

```
Locale  Field        Source   Value                                        Stale   Updated
es_ES   title        manual   Rehabilitación profesional                  fresh   2025-10-19 14:00:00
es_ES   alt          ai       Fisioterapeuta ayudando a paciente...       fresh   2025-10-19 14:01:00
es_ES   caption      manual   Terapia física especializada                fresh   2025-10-19 14:02:00
```

### `wp msh metadata stats`

Show metadata orchestration statistics.

**Syntax:**

```bash
wp msh metadata stats [--format=<format>]
```

**Example:**

```bash
wp msh metadata stats
```

**Output:**

```
Metric                Value
Total Events          127
Unprocessed Events    3
Processed Events      124
Total Metadata Cache  456
Stale Metadata        12
AI-Generated Active   234
Manual Active         222
Total Versions        892

Event Breakdown:
  - post.updated: 87
  - attachment.uploaded: 23
  - metadata.manual_edit: 17
```

### `wp msh metadata test_event`

Test event emission (for debugging).

**Syntax:**

```bash
wp msh metadata test_event <event> <entity_type> [<entity_id>]
```

**Example:**

```bash
wp msh metadata test_event post.updated post 123
```

**Output:**

```
Success: Event emitted with ID: 5
```

---

## Integration Points

### Phase 2 (Context Fusion)

**Integration:** Fingerprint Builder uses context data for page context hash

**Query:**

```sql
SELECT intent, primary_keyword, keywords
FROM wp_msh_context
WHERE post_id = {post_id}
```

**Fallback:** If no context exists, use simple content hash

### Phase 3 (AI Translation)

**Integration:** Fingerprint Builder uses locale profiles and glossary

**Tables:**
- `wp_msh_locale_profiles` - Language, region, cultural context, templates
- Glossary stored as JSON in `glossary` column

**Events Emitted:**
- `locale.added` - When new locale profile created
- `glossary.updated` - When glossary terms modified
- `template.updated` - When prompt template changed

### Phase 5 (Future)

**Integration:** Phase 5 will consume events from Event Bus

**Example Worker:**

```php
add_action( 'msh_event_post.updated', function( $entity_type, $entity_id, $payload, $event_id ) {
    // Check which images in this post need regeneration
    $images = get_attached_media( 'image', $entity_id );

    foreach ( $images as $image ) {
        // Queue regeneration job
        MSH_Regeneration_Queue::add( $image->ID );
    }

    // Mark event as processed
    MSH_Event_Bus::get_instance()->mark_processed( $event_id );
}, 10, 4 );
```

---

## Phase 5 Admin UI Implementation

### Metadata Hub Page Structure

**File:** `admin/metadata-hub-page.php` (Phase 5)

**Menu Registration:**

```php
// In class-msh-optimizer-menu.php
add_submenu_page(
    'msh-optimizer',
    __( 'Metadata Hub', 'msh-image-optimizer' ),
    '<span class="dashicons dashicons-database"></span> ' . __( 'Metadata Hub', 'msh-image-optimizer' ),
    'manage_options',
    'msh-metadata-hub',
    array( $this, 'render_metadata_hub_page' )
);
```

**Tab Implementation:**

```php
public function render_metadata_hub_page() {
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msh-image-optimizer' ) );
    }

    // Get active tab from URL
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'cache';
    $valid_tabs = array( 'cache', 'history', 'queue', 'events', 'sync' );

    if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
        $active_tab = 'cache';
    }

    ?>
    <div class="wrap msh-metadata-hub">
        <h1><?php esc_html_e( 'Metadata Hub', 'msh-image-optimizer' ); ?></h1>

        <nav class="nav-tab-wrapper">
            <a href="?page=msh-metadata-hub&tab=cache"
               class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Cache', 'msh-image-optimizer' ); ?>
            </a>
            <a href="?page=msh-metadata-hub&tab=history"
               class="nav-tab <?php echo $active_tab === 'history' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'History', 'msh-image-optimizer' ); ?>
            </a>
            <a href="?page=msh-metadata-hub&tab=queue"
               class="nav-tab <?php echo $active_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Queue', 'msh-image-optimizer' ); ?>
            </a>
            <a href="?page=msh-metadata-hub&tab=events"
               class="nav-tab <?php echo $active_tab === 'events' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e( 'Events', 'msh-image-optimizer' ); ?>
            </a>
            <a href="#"
               class="nav-tab msh-pro-tab <?php echo $active_tab === 'sync' ? 'nav-tab-active' : ''; ?>"
               data-pro-feature="cloud-sync">
                <?php esc_html_e( 'Sync', 'msh-image-optimizer' ); ?>
                🔒 <span class="msh-pro-badge">PRO</span>
            </a>
        </nav>

        <div class="tab-content">
            <?php
            switch ( $active_tab ) {
                case 'cache':
                    $this->render_cache_tab();
                    break;
                case 'history':
                    $this->render_history_tab();
                    break;
                case 'queue':
                    $this->render_queue_tab();
                    break;
                case 'events':
                    $this->render_events_tab();
                    break;
                case 'sync':
                    if ( MSH_Pro::is_active() ) {
                        $this->render_sync_tab();
                    } else {
                        $this->render_pro_upsell( 'cloud-sync' );
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
```

### Tab 1: Cache Browser

**Visual Mockup:**

```
┌─────────────────────────────────────────────────────────────┐
│ Metadata Hub                                                │
├─────────────────────────────────────────────────────────────┤
│ [Cache] [History] [Queue] [Events] [Sync 🔒]               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Filters:                                        [Export CSV]│
│ [Locale: All ▾] [Staleness: All ▾] [Source: All ▾]        │
│ [Search images...]                     [Regenerate Selected]│
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐│
│ │ Image    Field   Locale  Source  Status    Updated     ││
│ ├─────────────────────────────────────────────────────────┤│
│ │ ☑ 🖼️ team.jpg                                           ││
│ │   └ Alt    es_ES   Manual  Fresh      Oct 19 14:01     ││
│ │   └ Title  es_ES   AI      Stale¹     Oct 18 10:30     ││
│ │                                                         ││
│ │ ☐ 🖼️ rehab.jpg                                          ││
│ │   └ Alt    es_ES   AI      Fresh      Oct 19 12:00     ││
│ │   └ Alt    fr_FR   AI      Stale²     Oct 15 09:00     ││
│ │                                                         ││
│ │ ☐ 🖼️ office.jpg                                         ││
│ │   └ Caption de_DE  Manual  Fresh      Oct 19 15:30     ││
│ └─────────────────────────────────────────────────────────┘│
│                                                             │
│ ¹ context_changed  ² locale_updated                        │
│                                            [Load More...]   │
└─────────────────────────────────────────────────────────────┘

Click row → Slide-out panel:
┌───────────────────────────────┐
│ team.jpg - Title - es_ES      │
├───────────────────────────────┤
│ Status: Stale (context changed)│
│ Fingerprint: 102e047175...    │
│                               │
│ ✓ Manual (Active)             │
│ "Equipo de rehabilitación"    │
│ Last edited: Oct 19 by admin  │
│                               │
│ AI (Available)                │
│ "Equipo profesional de..."    │
│ Generated: Oct 18             │
│                               │
│ [Switch to AI]                │
│ [Edit Manual]                 │
│ [View History]                │
│ [Regenerate Now]              │
└───────────────────────────────┘
```

**Implementation:**

```php
private function render_cache_tab() {
    global $wpdb;

    // Get filters
    $locale = isset( $_GET['locale'] ) ? sanitize_text_field( $_GET['locale'] ) : '';
    $staleness = isset( $_GET['staleness'] ) ? sanitize_text_field( $_GET['staleness'] ) : '';
    $source = isset( $_GET['source'] ) ? sanitize_text_field( $_GET['source'] ) : '';

    // Build query
    $table = MSH_Metadata_Database::get_table_name( MSH_Metadata_Database::TABLE_CACHE );
    $where = array( '1=1' );
    $prepare_args = array();

    if ( $locale ) {
        $where[] = 'locale = %s';
        $prepare_args[] = $locale;
    }

    if ( $staleness && $staleness !== 'all' ) {
        if ( $staleness === 'stale' ) {
            $where[] = "stale_reason != 'fresh'";
        } else {
            $where[] = 'stale_reason = %s';
            $prepare_args[] = $staleness;
        }
    }

    if ( $source && $source !== 'all' ) {
        $where[] = 'chosen_source = %s';
        $prepare_args[] = $source;
    }

    $where_clause = implode( ' AND ', $where );
    $prepare_args[] = 50; // Limit

    $query = "SELECT * FROM $table WHERE $where_clause ORDER BY updated_at DESC LIMIT %d";
    $results = $wpdb->get_results( $wpdb->prepare( $query, $prepare_args ) );

    // Render table (implementation details...)
}
```

### Tab 2: Version History

**Visual Mockup:**

```
┌─────────────────────────────────────────────────────────────┐
│ [Cache] [History] [Queue] [Events] [Sync 🔒]               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Filter: [Image: All ▾] [Locale: All ▾] [Field: All ▾]     │
│                                                             │
│ Timeline View                                               │
│ ┌─────────────────────────────────────────────────────────┐│
│ │ Oct 19, 2025 14:01                                      ││
│ │ 🖼️ team.jpg - Alt - es_ES                               ││
│ │ ✏️ Manual Edit by admin                                  ││
│ │ "Terapia física profesional"                            ││
│ │ ← Changed from: "Fisioterapeuta ayudando..."            ││
│ │ [View Diff] [Restore This Version]                      ││
│ │                                                         ││
│ │ Oct 18, 2025 10:30                                      ││
│ │ 🖼️ team.jpg - Alt - es_ES                               ││
│ │ 🤖 AI Regeneration (trigger: post.updated #123)         ││
│ │ "Fisioterapeuta ayudando a paciente con..."             ││
│ │ Notes: Auto-regenerated due to context change           ││
│ │ [View Diff] [Restore This Version]                      ││
│ └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

### Tab 3: Regeneration Queue

**Visual Mockup:**

```
┌─────────────────────────────────────────────────────────────┐
│ [Cache] [History] [Queue] [Events] [Sync 🔒]               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Queue Status:  87 Stale | 12 Processing | 456 Fresh        │
│ Worker Health: ● Active (last run: 2 min ago)              │
│                               [Regenerate All Stale] [Pause]│
│                                                             │
│ Priority Queue                                              │
│ ┌─────────────────────────────────────────────────────────┐│
│ │ 🔴 High Priority (Manual Overrides)                      ││
│ │ team.jpg - Alt - es_ES (stale: context_changed)         ││
│ │ [Skip] [Process Now]                                    ││
│ │                                                         ││
│ │ 🟡 Medium Priority (Glossary Changes)                    ││
│ │ rehab.jpg - Title - es_ES (stale: glossary_changed)     ││
│ │ [Skip] [Process Now]                                    ││
│ │                                                         ││
│ │ 🟢 Normal Priority                                       ││
│ │ office.jpg - Caption - fr_FR (stale: locale_updated)    ││
│ │ ... (84 more)                                           ││
│ │                                                         ││
│ │ Currently Processing                                    ││
│ │ doctor.jpg - Alt - de_DE                                ││
│ │ [████████░░] 80% complete (ETA: 30 sec)                 ││
│ └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

### Tab 4: Event Log

**Visual Mockup:**

```
┌─────────────────────────────────────────────────────────────┐
│ [Cache] [History] [Queue] [Events] [Sync 🔒]               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ [Live Feed: ● On ▾] [Filter: All Events ▾]    [Export CSV] │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐│
│ │ Time     Event              Entity       User  Status   ││
│ ├─────────────────────────────────────────────────────────┤│
│ │ 14:32:05 glossary.updated   site         admin ✓        ││
│ │ 14:31:42 attachment.uploaded attachment:789 admin ✓     ││
│ │ 14:30:15 post.updated       post:123     admin ⏳       ││
│ │ 14:29:30 metadata.manual_edit attachment:456 admin ✓    ││
│ │                                                         ││
│ │ [Load More...]                                          ││
│ └─────────────────────────────────────────────────────────┘│
│                                                             │
│ Click event → Details panel appears                        │
└─────────────────────────────────────────────────────────────┘
```

### Tab 5: Cloud Sync (Pro)

**Visual Mockup (Pro Active):**

```
┌─────────────────────────────────────────────────────────────┐
│ [Cache] [History] [Queue] [Events] [Sync 🔒 PRO]           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Status: ✓ Connected to S3 (us-east-1)      [Configure Pro] │
│ Last Sync: 2 minutes ago                                    │
│ Sync Mode: Auto-push on change                             │
│                                                             │
│ Sync Statistics                                             │
│ Total Synced: 4,560 metadata entries                        │
│ Pending Push: 12 items                                      │
│ Conflicts: 0                                                │
│                                                             │
│ [Manual Push All] [Manual Pull All] [Resolve Conflicts]    │
│                                                             │
│ Recent Sync Activity                                        │
│ ┌─────────────────────────────────────────────────────────┐│
│ │ ✓ 14:30 - Pushed team.jpg alt (es_ES)                   ││
│ │ ✓ 14:28 - Pushed rehab.jpg title (fr_FR)                ││
│ │ ⚠️ 14:25 - Conflict: office.jpg caption (de_DE)         ││
│ │           [Use Local | Use Remote | Merge]              ││
│ └─────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────┘
```

**Visual Mockup (Pro Upsell):**

```
┌─────────────────────────────────────────────────────────────┐
│ [Cache] [History] [Queue] [Events] [Sync 🔒 PRO]           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│    🔒 Cloud Sync - Pro Feature                              │
│                                                             │
│    Unlock powerful cloud synchronization:                   │
│                                                             │
│    ✓ Sync metadata across multiple sites                   │
│    ✓ Team collaboration with conflict resolution           │
│    ✓ Automatic backup to S3 or Supabase                    │
│    ✓ Multi-site metadata sharing                           │
│    ✓ Export/import with version control                    │
│                                                             │
│    [Upgrade to Pro - $99/year]  [Learn More]                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Pro Upsell Implementation

```php
private function render_pro_upsell( $feature ) {
    $features = array(
        'cloud-sync' => array(
            'title'       => __( 'Cloud Sync - Pro Feature', 'msh-image-optimizer' ),
            'description' => __( 'Unlock powerful cloud synchronization', 'msh-image-optimizer' ),
            'benefits'    => array(
                __( 'Sync metadata across multiple sites', 'msh-image-optimizer' ),
                __( 'Team collaboration with conflict resolution', 'msh-image-optimizer' ),
                __( 'Automatic backup to S3 or Supabase', 'msh-image-optimizer' ),
                __( 'Multi-site metadata sharing', 'msh-image-optimizer' ),
                __( 'Export/import with version control', 'msh-image-optimizer' ),
            ),
        ),
    );

    $feature_data = $features[ $feature ] ?? $features['cloud-sync'];

    ?>
    <div class="msh-pro-upsell">
        <div class="msh-pro-upsell-icon">🔒</div>
        <h2><?php echo esc_html( $feature_data['title'] ); ?></h2>
        <p class="msh-pro-upsell-description">
            <?php echo esc_html( $feature_data['description'] ); ?>
        </p>

        <ul class="msh-pro-upsell-benefits">
            <?php foreach ( $feature_data['benefits'] as $benefit ) : ?>
                <li>✓ <?php echo esc_html( $benefit ); ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="msh-pro-upsell-actions">
            <a href="https://thedot.com/pricing" class="button button-primary button-hero">
                <?php esc_html_e( 'Upgrade to Pro - $99/year', 'msh-image-optimizer' ); ?>
            </a>
            <a href="https://thedot.com/features/cloud-sync" class="button button-secondary button-hero">
                <?php esc_html_e( 'Learn More', 'msh-image-optimizer' ); ?>
            </a>
        </div>
    </div>
    <?php
}
```

---

## Performance Considerations

### Fingerprint Calculation Cost

**Signal Query Counts:**
- Page context: 1 query (find posts) + N queries (context lookup per post)
- Image features: 1 query (metadata), 1 file read
- Locale profile: 1 query
- Template: 1 query (combined with locale profile)
- Model/prompt: 2 option lookups (cached by WordPress)
- Glossary: 1 query (combined with locale profile)

**Optimization:** Consider caching fingerprints for 5 minutes using transients

### Event Bus Scaling

**Current:** Synchronous event emission (blocking)

**Future Optimization:**
- Batch event emission (insert multiple events at once)
- Async event processing with WP-Cron or external queue
- Event retention policy (auto-delete processed events older than 30 days)

### Index Usage

**Critical Indexes:**

```sql
-- Fast unprocessed event lookup
CREATE INDEX idx_processed ON wp_optimizer_events(processed_at);

-- Fast attachment cache lookup
CREATE INDEX idx_attachment ON wp_optimizer_metadata_cache(attachment_id);

-- Fast stale metadata detection
CREATE INDEX idx_stale ON wp_optimizer_metadata_cache(attachment_id, stale_reason);
```

---

## Security Considerations

### SQL Injection Protection

All queries use `$wpdb->prepare()` with placeholders:

```php
$wpdb->get_var( $wpdb->prepare(
    "SELECT glossary FROM {$wpdb->prefix}msh_locale_profiles WHERE locale_code = %s",
    $locale
) );
```

### User Capability Checks

WP-CLI commands run with `@when after_wp_load` to ensure WordPress is fully loaded.

Admin UI (Track B) will check `manage_options` capability.

### Data Validation

**Locale Code:** Must match pattern `[a-z]{2}_[A-Z]{2}` (e.g., 'en_US', 'es_ES')

**Field Name:** Must be one of: 'title', 'alt', 'caption', 'description'

**Event Type:** Validated against enum in database schema

---

## Testing

### Unit Tests (Future)

```php
class MSH_Fingerprint_Builder_Test extends WP_UnitTestCase {
    public function test_fingerprint_changes_when_content_changes() {
        $fingerprint1 = $this->builder->build_fingerprint( 123, 'en_US', 'alt' );

        // Update post content
        wp_update_post( array( 'ID' => 456, 'post_content' => 'New content' ) );

        $fingerprint2 = $this->builder->build_fingerprint( 123, 'en_US', 'alt' );

        $this->assertNotEquals( $fingerprint1, $fingerprint2 );
    }
}
```

### Manual Testing

**Test Event Emission:**

```bash
wp msh metadata test_event post.updated post 123
wp msh metadata events --unprocessed
```

**Test Fingerprint Calculation:**

```bash
wp msh metadata fingerprint 1686 en_US alt --verbose
```

**Test Staleness Detection:**

1. Calculate initial fingerprint
2. Change post content where image appears
3. Calculate new fingerprint
4. Compare and detect `context_changed`

---

## Troubleshooting

### Issue: Fingerprint calculation fails

**Symptoms:** PHP fatal error when running `wp msh metadata fingerprint`

**Causes:**
- Missing Phase 2 context table (`wp_msh_context`)
- Missing Phase 3 locale profiles table (`wp_msh_locale_profiles`)

**Solution:** Fingerprint Builder gracefully falls back to simple hashes

### Issue: Events not being emitted

**Symptoms:** `wp msh metadata events` shows no events

**Debugging:**

```php
add_action( 'msh_event_emitted', function( $event, $entity_type, $entity_id ) {
    error_log( "Event emitted: $event for $entity_type:$entity_id" );
}, 10, 3 );
```

**Check:** Ensure Event Bus is initialized in `init()` method

### Issue: Duplicate events

**Symptoms:** Same event appears multiple times

**Cause:** Idempotency key not working

**Solution:** Check `idempotency_key` uniqueness constraint in database

---

## Migration from Phase 4 (Old Workflow Version)

**Status:** Phase 4 workflow features removed in Track A cleanup

**Removed:**
- Version History UI (replaced with CLI inspection)
- A/B Testing campaigns (not part of metadata infrastructure)
- Approval Queue (not needed for metadata orchestration)

**Data Migration:** Not applicable - old Phase 4 was never in production

---

## Future Enhancements

### Track B Components (In Progress)

1. **MSH_Metadata_Core** - CRUD API for cache + versions
2. **MSH_Staleness_Engine** - Automatic staleness detection
3. **MSH_Decision_Layer** - Policy-based manual vs. AI choice
4. **MSH_Cloud_Sync_Driver** - S3/Supabase cloud sync

### Phase 5 Integration

**Regeneration Workers:**
- Consume events from Event Bus
- Batch regeneration for efficiency
- Priority queue (manual edits first)

### Advanced Features

**Smart Batch Regeneration:**
- Detect related images (same post, similar context)
- Regenerate in single API call to save costs

**Conflict Resolution:**
- Manual edit always wins over AI
- Show diff UI when user wants to restore AI version

**Analytics:**
- Track staleness distribution
- Monitor regeneration frequency
- Measure AI vs. manual preference by locale

---

## API Reference

### MSH_Metadata_Database

**Static Methods:**

```php
MSH_Metadata_Database::init(); // Create/update tables
MSH_Metadata_Database::get_table_name( $table ); // Get full table name
MSH_Metadata_Database::drop_tables(); // Uninstall only
MSH_Metadata_Database::get_version(); // Get DB version
```

### MSH_Event_Bus

**Instance Methods:**

```php
$bus = MSH_Event_Bus::get_instance();

$bus->emit( $event, $entity_type, $entity_id, $payload, $idempotency_key );
$bus->get_unprocessed( $event, $limit );
$bus->mark_processed( $event_id );
$bus->get_stats();
```

### MSH_Fingerprint_Builder

**Instance Methods:**

```php
$builder = MSH_Fingerprint_Builder::get_instance();

$builder->build_fingerprint( $attachment_id, $locale, $field );
$builder->detect_staleness_reason( $attachment_id, $locale, $field, $stored_fingerprint );
```

---

## Changelog

### Version 2.0.0 (2025-10-19)

**Track A Complete:**
- ✅ Database schema with 4 tables
- ✅ Event Bus system with idempotency
- ✅ Fingerprint Builder with 6 input signals
- ✅ WP-CLI inspector commands
- ✅ Technical documentation

**Track B In Progress:**
- ⏳ Metadata Core (CRUD API)
- ⏳ Staleness Engine
- ⏳ Decision Layer
- ⏳ Cloud Sync Driver

---

## Support

**Documentation:** `/docs/phase4-technical.md` (this file)
**User Manual:** `/docs/phase4-manual.md`
**CLI Help:** `wp help msh metadata`

**Contact:** development@thedot.com
