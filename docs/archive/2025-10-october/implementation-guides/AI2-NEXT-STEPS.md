# AI #2 - What To Do RIGHT NOW

**Date:** October 19, 2025
**Status:** Hub skeleton ✅ DONE | Cache tab 🎯 NEXT

---

## ✅ What's Already Done (Don't Redo!)

### 1. Hub Page Skeleton
**File:** `admin/class-msh-hub-page.php`
**What it has:**
- ✅ Menu registration (under "The Dot" → after Glossary)
- ✅ 5-tab navigation (Cache, History, Queue, Events, Sync)
- ✅ Tab routing (`?tab=cache` works)
- ✅ Asset enqueuing (hub.css, hub.js)
- ✅ Placeholder content for each tab

**Status:** ✅ Complete - move to next task

---

### 2. CSS Styles
**File:** `assets/css/hub.css`
**What it has:**
- ✅ The Dot brand colors (charcoal, lime, warm gray, cream)
- ✅ Tab navigation styles
- ✅ Table styles
- ✅ Button styles
- ✅ Filter styles

**Status:** ✅ Complete - ready to use

---

### 3. JavaScript Handlers
**File:** `assets/js/hub.js`
**What it has:**
- ✅ AJAX handlers for cache entries
- ✅ Filter event bindings
- ✅ Pagination handlers
- ✅ Error handling

**Status:** ✅ Complete - ready to use

---

### 4. Helper Function Stubs (AI #1 Created These!)
**File:** `includes/class-msh-helper-stubs.php`
**What it has:**
- ✅ `msh_get_cache_entries()` - Returns 3 mock cache entries
- ✅ `msh_get_job_stats()` - Returns mock queue stats
- ✅ `msh_enqueue_job()` - Logs and returns mock job ID
- ✅ `msh_is_pro_active()` - Returns false (Free tier)
- ✅ `msh_telemetry()` - Logs events to error_log
- ✅ `msh_get_cache_entry()` - Returns single mock entry
- ✅ `msh_get_recent_events()` - Returns mock events
- ✅ `msh_get_version_history()` - Returns mock version history

**Status:** ✅ Complete - USE THESE NOW!

**⚠️ IMPORTANT:** These are STUBS (temporary mock data). AI #1 will replace with real implementations later. But they work perfectly for building your UI right now!

---

## 🎯 YOUR NEXT TASK: Build Cache Tab UI

### What You're Building

A **metadata cache browser** that displays:
- All cached metadata entries (alt text, titles, captions, descriptions)
- Filters: Locale, Staleness, Source
- Actions: Regenerate individual entries
- Pagination: 50 per page

### Where To Add Code

**File to edit:** `admin/class-msh-hub-page.php`
**Method to update:** `render_tab_content()` at line 216

**Current code (placeholder):**
```php
case 'cache':
default:
    echo '<p>' . esc_html__( 'Cache tab - Coming soon...', 'msh-image-optimizer' ) . '</p>';
    break;
```

**Replace with:** ⬇️ See below

---

## 📝 Step-by-Step Implementation

### Step 1: Update `render_tab_content()` Cache Case

Replace the placeholder with this:

```php
case 'cache':
default:
    $this->render_cache_tab();
    break;
```

---

### Step 2: Add New Method `render_cache_tab()`

Add this method to the `MSH_Hub_Page` class (after `render_tab_content()`, around line 240):

```php
/**
 * Render Cache tab content.
 *
 * Displays metadata cache entries with filters and pagination.
 *
 * @return void
 */
private function render_cache_tab() {
    // Get filter values from URL
    $locale     = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : '';
    $staleness  = isset( $_GET['staleness'] ) ? sanitize_text_field( wp_unslash( $_GET['staleness'] ) ) : '';
    $source     = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
    $paged      = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

    // Build args for helper function
    $args = array(
        'page'     => $paged,
        'per_page' => 50,
    );

    if ( ! empty( $locale ) ) {
        $args['locale'] = $locale;
    }
    if ( ! empty( $staleness ) ) {
        $args['staleness'] = $staleness;
    }
    if ( ! empty( $source ) ) {
        $args['source'] = $source;
    }

    // Get cache entries using AI #1's helper function
    $results = msh_get_cache_entries( $args );

    $items       = isset( $results['items'] ) ? $results['items'] : array();
    $total       = isset( $results['total'] ) ? $results['total'] : 0;
    $total_pages = isset( $results['total_pages'] ) ? $results['total_pages'] : 1;

    ?>
    <div class="msh-cache-tab">
        <!-- Filters -->
        <div class="msh-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="msh-hub" />
                <input type="hidden" name="tab" value="cache" />

                <label for="filter-locale"><?php esc_html_e( 'Locale:', 'msh-image-optimizer' ); ?></label>
                <select name="locale" id="filter-locale">
                    <option value=""><?php esc_html_e( 'All Locales', 'msh-image-optimizer' ); ?></option>
                    <option value="es_ES" <?php selected( $locale, 'es_ES' ); ?>>Spanish (es_ES)</option>
                    <option value="fr_FR" <?php selected( $locale, 'fr_FR' ); ?>>French (fr_FR)</option>
                    <option value="de_DE" <?php selected( $locale, 'de_DE' ); ?>>German (de_DE)</option>
                </select>

                <label for="filter-staleness"><?php esc_html_e( 'Staleness:', 'msh-image-optimizer' ); ?></label>
                <select name="staleness" id="filter-staleness">
                    <option value=""><?php esc_html_e( 'All', 'msh-image-optimizer' ); ?></option>
                    <option value="stale" <?php selected( $staleness, 'stale' ); ?>><?php esc_html_e( 'Stale Only', 'msh-image-optimizer' ); ?></option>
                    <option value="fresh" <?php selected( $staleness, 'fresh' ); ?>><?php esc_html_e( 'Fresh Only', 'msh-image-optimizer' ); ?></option>
                </select>

                <label for="filter-source"><?php esc_html_e( 'Source:', 'msh-image-optimizer' ); ?></label>
                <select name="source" id="filter-source">
                    <option value=""><?php esc_html_e( 'All Sources', 'msh-image-optimizer' ); ?></option>
                    <option value="ai" <?php selected( $source, 'ai' ); ?>><?php esc_html_e( 'AI Generated', 'msh-image-optimizer' ); ?></option>
                    <option value="manual" <?php selected( $source, 'manual' ); ?>><?php esc_html_e( 'Manual Override', 'msh-image-optimizer' ); ?></option>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'msh-image-optimizer' ); ?></button>
            </form>
        </div>

        <!-- Results Count -->
        <p class="msh-results-count">
            <?php
            printf(
                /* translators: %d: total number of cache entries */
                esc_html__( 'Showing %d cache entries', 'msh-image-optimizer' ),
                (int) $total
            );
            ?>
        </p>

        <!-- Cache Entries Table -->
        <?php if ( ! empty( $items ) ) : ?>
            <table class="wp-list-table widefat fixed striped msh-cache-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Attachment ID', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Locale', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Field', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Value', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Source', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Staleness', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Updated', 'msh-image-optimizer' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'msh-image-optimizer' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $entry ) : ?>
                        <tr>
                            <td><?php echo (int) $entry->attachment_id; ?></td>
                            <td><code><?php echo esc_html( $entry->locale ); ?></code></td>
                            <td><?php echo esc_html( $entry->field ); ?></td>
                            <td>
                                <?php
                                $value = 'manual' === $entry->chosen_source ? $entry->manual_value : $entry->ai_value;
                                echo esc_html( wp_trim_words( $value, 10 ) );
                                ?>
                            </td>
                            <td>
                                <span class="msh-badge msh-badge-<?php echo esc_attr( $entry->chosen_source ); ?>">
                                    <?php echo esc_html( ucfirst( $entry->chosen_source ) ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( ! empty( $entry->stale_reason ) ) : ?>
                                    <span class="msh-badge msh-badge-stale" title="<?php echo esc_attr( $entry->stale_reason ); ?>">
                                        <?php esc_html_e( 'Stale', 'msh-image-optimizer' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="msh-badge msh-badge-fresh">
                                        <?php esc_html_e( 'Fresh', 'msh-image-optimizer' ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $entry->updated_at ); ?></td>
                            <td>
                                <button class="button button-small msh-regenerate-btn"
                                        data-attachment-id="<?php echo (int) $entry->attachment_id; ?>"
                                        data-locale="<?php echo esc_attr( $entry->locale ); ?>"
                                        data-field="<?php echo esc_attr( $entry->field ); ?>">
                                    <?php esc_html_e( 'Regenerate', 'msh-image-optimizer' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $base_url = add_query_arg(
                            array(
                                'page'      => 'msh-hub',
                                'tab'       => 'cache',
                                'locale'    => $locale,
                                'staleness' => $staleness,
                                'source'    => $source,
                            ),
                            admin_url( 'admin.php' )
                        );

                        for ( $i = 1; $i <= $total_pages; $i++ ) {
                            $page_url = add_query_arg( 'paged', $i, $base_url );
                            $current  = ( $i === $paged ) ? ' current' : '';
                            printf(
                                '<a class="button%s" href="%s">%d</a> ',
                                esc_attr( $current ),
                                esc_url( $page_url ),
                                (int) $i
                            );
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No cache entries found.', 'msh-image-optimizer' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
```

---

## 🧪 Testing Your Implementation

### Test Checklist:

1. **Navigate to Hub:**
   - Go to WordPress Admin → The Dot → Optimizer Hub
   - Should see "Cache" tab active

2. **Verify Mock Data Displays:**
   - Should see 3 cache entries (from stub function)
   - Table should show: Attachment ID, Locale, Field, Value, Source, Staleness, Updated, Actions

3. **Test Filters:**
   - Select "Spanish (es_ES)" from Locale dropdown → Click Filter
   - Should filter results (mock data has es_ES entries)
   - Try other filters (Staleness, Source)

4. **Test Pagination:**
   - With only 3 mock entries, pagination won't show yet
   - When AI #1 replaces stubs with real data (50+ entries), pagination will work

5. **Test Regenerate Button:**
   - Click "Regenerate" button on any row
   - Open browser console (F12)
   - Should see JavaScript event fired (hub.js handles this)

---

## 💡 How It Works

### Data Flow:

```
User visits Cache tab
    ↓
render_cache_tab() called
    ↓
Calls msh_get_cache_entries( $args )  ← AI #1's stub function
    ↓
Returns mock data (3 entries)
    ↓
PHP renders table HTML
    ↓
User sees cache entries!
```

### When AI #1 Replaces Stubs:

**Nothing changes in your code!** The helper function signature stays the same:

```php
// Stub (now):
function msh_get_cache_entries( $args ) {
    // Returns 3 mock entries
}

// Real implementation (later):
function msh_get_cache_entries( $args ) {
    // Queries real database, returns 100s of entries
}
```

Your UI code works with both!

---

## 🎨 Styling Notes

The CSS is already done in `assets/css/hub.css`. Your table will automatically get:
- ✅ Brand colors (charcoal text, lime accents)
- ✅ Responsive layout
- ✅ Hover effects on rows
- ✅ Badge styles for Source/Staleness

---

## 🚀 After This Works

Once the Cache tab displays mock data successfully, your next tasks are:

### Phase 1: Cache Tab Polish (Day 2)
- [ ] Add AJAX filtering (no page reload when filtering)
- [ ] Wire up "Regenerate" button to actually enqueue job
- [ ] Add bulk actions (select multiple entries → Regenerate All)

### Phase 2: Queue Tab (Day 3-4)
- [ ] Display job stats (pending/processing/complete/failed counts)
- [ ] Show queue table with priority, status, created date
- [ ] Add "Process Now" button

### Phase 3: Events Tab (Day 5)
- [ ] Real-time event stream (using `msh_get_recent_events()`)
- [ ] Auto-refresh every 5 seconds

### Phase 4: History Tab (Day 6)
- [ ] Version history table (using `msh_get_version_history()`)
- [ ] Show before/after values with diff highlighting

### Phase 5: Sync Tab (Day 7)
- [ ] Pro feature check (using `msh_is_pro_active()`)
- [ ] Show upsell modal if Free tier
- [ ] Show sync dashboard if Pro

---

## ❓ Questions?

### "What if the helper function doesn't exist?"

The stubs are already loaded! Check your plugin bootstrap file (`msh-image-optimizer.php`):

```php
// Line 116-117: Helper stubs are included
require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-helper-stubs.php';
```

If you get "undefined function" errors, make sure this line exists.

---

### "What if I want different mock data?"

You can temporarily edit `includes/class-msh-helper-stubs.php` to add more mock entries. But don't spend too much time on this - AI #1 will replace it soon.

---

### "Should I use AJAX or PHP forms?"

**Start with PHP forms** (what the code above does). It's simpler and works immediately.

**Later, upgrade to AJAX** for better UX (no page reload). The `hub.js` file already has AJAX handlers ready - you'll just need to wire them up.

---

## ✅ Success Criteria

**Your Cache tab is done when:**

1. ✅ Table displays 3 mock entries
2. ✅ Filters work (locale, staleness, source)
3. ✅ Pagination HTML renders (will show pages when data > 50 entries)
4. ✅ "Regenerate" button exists on each row
5. ✅ No PHP errors in debug.log
6. ✅ No JavaScript errors in browser console
7. ✅ Brand styles applied (lime accents, charcoal text)

---

## 🎯 Start Now!

**Your immediate action:**

1. Open `admin/class-msh-hub-page.php`
2. Find `render_tab_content()` method (line 216)
3. Replace the cache case placeholder with `$this->render_cache_tab();`
4. Add the new `render_cache_tab()` method (copy from Step 2 above)
5. Save file
6. Refresh WordPress Admin → The Dot → Optimizer Hub
7. Should see cache table with 3 entries!

**Estimated time:** 15-20 minutes

---

**Questions? Check the interface contract first:**
[`docs/interface-contract.md`](../../../interface-contract.md) - Section 2 (Helper Functions)

---

**Good luck, AI #2! 🚀**

*Last updated: October 19, 2025 by AI #1*
