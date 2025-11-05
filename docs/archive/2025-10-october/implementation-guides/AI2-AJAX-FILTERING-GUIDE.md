# AI #2 - AJAX Filtering Implementation Guide

**Date:** October 19, 2025
**Task:** Wire up AJAX filtering for Cache tab (no page reloads)
**Current Status:** PHP form filtering works ✅ | AJAX filtering ⏳ IN PROGRESS

---

## 🎯 Goal

Transform the Cache tab from:
- ❌ **Current:** Click Filter → Full page reload → New results
- ✅ **Target:** Click Filter → AJAX call → Table updates instantly (no reload)

**User Experience:**
- Faster filtering (no page flicker)
- Preserves scroll position
- Loading spinner while fetching
- Smooth transitions

---

## 📋 What's Already Done

### ✅ You Have:
1. **PHP filtering logic** - `render_cache_tab()` already builds filtered results
2. **Filter form HTML** - Dropdowns for Locale, Staleness, Source
3. **Table markup** - `<table class="msh-cache-table">` with proper structure
4. **JavaScript file** - `assets/js/hub.js` with AJAX handlers ready
5. **Helper function** - `msh_get_cache_entries()` works perfectly

### 🎯 What You Need:
1. Create REST API endpoint to return JSON (instead of HTML)
2. Bind JavaScript to filter form submit
3. Update table HTML via JavaScript (no page reload)
4. Add loading spinner

---

## 🚀 Implementation Steps

### Step 1: Create AJAX Handler for Cache Entries

**File:** `admin/class-msh-hub-page.php`

Add this new method to the `MSH_Hub_Page` class (after `render_cache_tab()`, around line 424):

```php
/**
 * AJAX handler for cache entries filtering.
 *
 * Returns JSON response with filtered cache entries.
 *
 * @return void
 */
public function ajax_get_cache_entries() {
	// Security check
	check_ajax_referer( 'msh_hub_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'msh-image-optimizer' ) ) );
		return;
	}

	// Get filter parameters from AJAX request
	$locale    = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';
	$staleness = isset( $_POST['staleness'] ) ? sanitize_text_field( wp_unslash( $_POST['staleness'] ) ) : '';
	$source    = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
	$paged     = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1;

	if ( $paged < 1 ) {
		$paged = 1;
	}

	// Build args (same as render_cache_tab)
	$args = array(
		'page'     => $paged,
		'per_page' => 50,
	);

	if ( '' !== $locale ) {
		$args['locale'] = $locale;
	}
	if ( '' !== $staleness ) {
		$args['staleness'] = $staleness;
	}
	if ( '' !== $source ) {
		$args['source'] = $source;
	}

	// Get results using helper function
	$results = function_exists( 'msh_get_cache_entries' ) ? msh_get_cache_entries( $args ) : array();

	$items       = isset( $results['items'] ) ? $results['items'] : array();
	$total       = isset( $results['total'] ) ? (int) $results['total'] : 0;
	$total_pages = isset( $results['total_pages'] ) ? max( 1, (int) $results['total_pages'] ) : 1;

	// Build HTML for table rows
	ob_start();
	foreach ( $items as $entry ) {
		?>
		<tr>
			<td><?php echo (int) $entry->attachment_id; ?></td>
			<td><code><?php echo esc_html( $entry->locale ); ?></code></td>
			<td><?php echo esc_html( $entry->field ); ?></td>
			<td>
				<?php
				$value = 'manual' === $entry->chosen_source && ! empty( $entry->manual_value )
					? $entry->manual_value
					: $entry->ai_value;
				echo esc_html( wp_trim_words( (string) $value, 10 ) );
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
				<button
					type="button"
					class="button button-small msh-regenerate-btn"
					data-attachment-id="<?php echo (int) $entry->attachment_id; ?>"
					data-locale="<?php echo esc_attr( $entry->locale ); ?>"
					data-field="<?php echo esc_attr( $entry->field ); ?>"
				>
					<?php esc_html_e( 'Regenerate', 'msh-image-optimizer' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}
	$table_html = ob_get_clean();

	// Build pagination HTML
	ob_start();
	if ( $total_pages > 1 ) {
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$current = ( $i === $paged ) ? ' current' : '';
			printf(
				'<button type="button" class="button%s msh-page-btn" data-page="%d">%d</button> ',
				esc_attr( $current ),
				(int) $i,
				(int) $i
			);
		}
	}
	$pagination_html = ob_get_clean();

	// Send JSON response
	wp_send_json_success(
		array(
			'table_html'      => $table_html,
			'pagination_html' => $pagination_html,
			'total'           => $total,
			'current_page'    => $paged,
			'total_pages'     => $total_pages,
		)
	);
}
```

---

### Step 2: Register AJAX Action

In the `__construct()` method (around line 47-50), add the AJAX action hook:

**Find this:**
```php
private function __construct() {
	add_action( 'admin_menu', array( $this, 'register_menu' ) );
	add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
}
```

**Change to:**
```php
private function __construct() {
	add_action( 'admin_menu', array( $this, 'register_menu' ) );
	add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	add_action( 'wp_ajax_msh_get_cache_entries', array( $this, 'ajax_get_cache_entries' ) );
}
```

---

### Step 3: Update Filter Form to Use AJAX

**File:** `admin/class-msh-hub-page.php`

In `render_cache_tab()`, find the form (around line 281-310) and add an ID:

**Change line 282 from:**
```php
<form method="get" action="">
```

**To:**
```php
<form method="get" action="" id="msh-cache-filter-form">
```

This gives JavaScript a hook to bind to.

---

### Step 4: Add Loading Spinner HTML

In `render_cache_tab()`, after the filters div (around line 310), add:

```php
		</div>

		<!-- Loading Spinner (hidden by default) -->
		<div id="msh-loading-spinner" style="display: none; text-align: center; padding: 20px;">
			<span class="spinner is-active" style="float: none; margin: 0;"></span>
			<p><?php esc_html_e( 'Loading cache entries...', 'msh-image-optimizer' ); ?></p>
		</div>

		<p class="msh-results-count">
```

---

### Step 5: Update Table to Have ID for JavaScript Updates

In `render_cache_tab()`, find the table (around line 323) and add ID to tbody:

**Change line 336 from:**
```php
<tbody>
```

**To:**
```php
<tbody id="msh-cache-table-body">
```

---

### Step 6: Update Pagination Div to Have ID

Find the pagination div (around line 384) and add ID:

**Change line 385 from:**
```php
<div class="tablenav-pages">
```

**To:**
```php
<div class="tablenav-pages" id="msh-cache-pagination">
```

---

### Step 7: Create JavaScript Handler

**File:** `assets/js/hub.js`

Replace the entire file with this:

```javascript
/**
 * Optimizer Hub JavaScript
 *
 * Handles AJAX interactions for all Hub tabs.
 */

(function($) {
	'use strict';

	const MSH_Hub = {

		/**
		 * Initialize all handlers
		 */
		init: function() {
			console.log('Optimizer Hub JS loaded');
			console.log('Hub Data:', window.mshHubData);

			// Cache tab handlers
			this.bindCacheFilters();
			this.bindCachePagination();
			this.bindRegenerateButtons();
		},

		/**
		 * Bind AJAX to cache filter form
		 */
		bindCacheFilters: function() {
			const $form = $('#msh-cache-filter-form');

			if ($form.length === 0) {
				return; // Not on Cache tab
			}

			// Prevent default form submission
			$form.on('submit', function(e) {
				e.preventDefault();
				MSH_Hub.loadCacheEntries(1); // Start at page 1 when filtering
			});

			// Also trigger on dropdown change
			$form.find('select').on('change', function() {
				$form.trigger('submit');
			});
		},

		/**
		 * Bind pagination clicks
		 */
		bindCachePagination: function() {
			$(document).on('click', '.msh-page-btn', function(e) {
				e.preventDefault();
				const page = $(this).data('page');
				MSH_Hub.loadCacheEntries(page);
			});
		},

		/**
		 * Bind regenerate button clicks
		 */
		bindRegenerateButtons: function() {
			$(document).on('click', '.msh-regenerate-btn', function(e) {
				e.preventDefault();
				const $btn = $(this);
				const attachmentId = $btn.data('attachment-id');
				const locale = $btn.data('locale');
				const field = $btn.data('field');

				MSH_Hub.regenerateEntry(attachmentId, locale, field, $btn);
			});
		},

		/**
		 * Load cache entries via AJAX
		 */
		loadCacheEntries: function(page) {
			const $form = $('#msh-cache-filter-form');
			const locale = $form.find('[name="locale"]').val();
			const staleness = $form.find('[name="staleness"]').val();
			const source = $form.find('[name="source"]').val();

			// Show loading spinner
			$('#msh-loading-spinner').show();
			$('#msh-cache-table-body').css('opacity', '0.5');

			// AJAX request
			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'msh_get_cache_entries',
					nonce: window.mshHubData.ajaxNonce,
					locale: locale,
					staleness: staleness,
					source: source,
					paged: page
				},
				success: function(response) {
					if (response.success) {
						// Update table body
						$('#msh-cache-table-body').html(response.data.table_html);

						// Update pagination
						$('#msh-cache-pagination').html(response.data.pagination_html);

						// Update results count
						$('.msh-results-count').html(
							'Showing ' + response.data.total + ' cache entries'
						);

						// Hide loading spinner
						$('#msh-loading-spinner').hide();
						$('#msh-cache-table-body').css('opacity', '1');

						console.log('Cache entries loaded:', response.data);
					} else {
						alert('Error: ' + response.data.message);
						$('#msh-loading-spinner').hide();
						$('#msh-cache-table-body').css('opacity', '1');
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', error);
					alert('Failed to load cache entries. Check console for details.');
					$('#msh-loading-spinner').hide();
					$('#msh-cache-table-body').css('opacity', '1');
				}
			});
		},

		/**
		 * Regenerate a single cache entry
		 */
		regenerateEntry: function(attachmentId, locale, field, $btn) {
			// Show loading state
			const originalText = $btn.text();
			$btn.prop('disabled', true).text('Processing...');

			// AJAX request to enqueue regeneration job
			$.ajax({
				url: window.mshHubData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'msh_regenerate_entry',
					nonce: window.mshHubData.ajaxNonce,
					attachment_id: attachmentId,
					locale: locale,
					field: field
				},
				success: function(response) {
					if (response.success) {
						// Show success message
						$btn.text('✓ Queued').addClass('button-primary');

						setTimeout(function() {
							$btn.text(originalText).removeClass('button-primary').prop('disabled', false);
						}, 2000);

						console.log('Job enqueued:', response.data);
					} else {
						alert('Error: ' + response.data.message);
						$btn.text(originalText).prop('disabled', false);
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', error);
					alert('Failed to enqueue job. Check console for details.');
					$btn.text(originalText).prop('disabled', false);
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		MSH_Hub.init();
	});

})(jQuery);
```

---

## 🧪 Testing Your AJAX Implementation

### Test Checklist:

**1. Filter Without Page Reload:**
- [ ] Navigate to The Dot → Optimizer Hub → Cache tab
- [ ] Open browser DevTools Network tab
- [ ] Select "Spanish (es_ES)" from Locale dropdown
- [ ] Should see AJAX request to `admin-ajax.php` (NOT full page reload)
- [ ] Table updates instantly with filtered results
- [ ] No page flicker

**2. Loading Spinner:**
- [ ] When filtering, loading spinner appears briefly
- [ ] Table dims to 50% opacity during load
- [ ] Spinner disappears when results arrive

**3. Pagination:**
- [ ] When results > 50 entries, pagination buttons appear
- [ ] Clicking page 2 → AJAX request → Table updates
- [ ] Pagination preserves active filters
- [ ] Current page button has "current" class

**4. Results Count:**
- [ ] "Showing X cache entries" updates dynamically
- [ ] Count reflects filtered results

**5. Regenerate Button:**
- [ ] Click "Regenerate" on any row
- [ ] Button text changes to "Processing..."
- [ ] Button becomes disabled
- [ ] Success: Button shows "✓ Queued" briefly, then resets
- [ ] Error: Alert shows error message

**6. Console Logs:**
- [ ] Check browser console for errors
- [ ] Should see: "Optimizer Hub JS loaded"
- [ ] Should see: "Cache entries loaded: {data...}"
- [ ] NO errors about undefined functions or 404s

---

## 🐛 Debugging Guide

### Issue: Form still reloads page

**Check:**
1. `e.preventDefault()` is called in `$form.on('submit')`
2. Form has ID `msh-cache-filter-form`
3. JavaScript is loading (check Network tab)

---

### Issue: AJAX returns 0 or -1

**Check:**
1. Action hook registered: `wp_ajax_msh_get_cache_entries`
2. Nonce is correct: `msh_hub_nonce`
3. `ajax_get_cache_entries()` method exists

**Debug:**
```php
// Add to top of ajax_get_cache_entries()
error_log('AJAX handler called!');
error_log(print_r($_POST, true));
```

---

### Issue: Table doesn't update

**Check:**
1. `#msh-cache-table-body` ID exists on `<tbody>`
2. `response.data.table_html` contains HTML (check console)
3. jQuery selector is correct

**Debug:**
```javascript
console.log('Table HTML:', response.data.table_html);
console.log('Tbody element:', $('#msh-cache-table-body'));
```

---

### Issue: Spinner doesn't show

**Check:**
1. `#msh-loading-spinner` ID exists
2. Initial `display: none` style is set
3. JavaScript changes it to `.show()`

---

## 📚 How AJAX Flow Works

```
User changes filter dropdown
    ↓
JavaScript: bindCacheFilters() detects change
    ↓
JavaScript: Calls loadCacheEntries(page)
    ↓
JavaScript: Shows spinner, dims table
    ↓
AJAX POST to admin-ajax.php
    action: msh_get_cache_entries
    nonce: msh_hub_nonce
    locale: es_ES
    staleness: stale
    source: manual
    paged: 1
    ↓
WordPress routes to: MSH_Hub_Page::ajax_get_cache_entries()
    ↓
PHP: Validates nonce, capabilities
    ↓
PHP: Calls msh_get_cache_entries($args)
    ↓
PHP: Builds HTML for table rows + pagination
    ↓
PHP: wp_send_json_success([table_html, pagination_html, total])
    ↓
JavaScript: Receives JSON response
    ↓
JavaScript: Updates #msh-cache-table-body with table_html
    ↓
JavaScript: Updates #msh-cache-pagination with pagination_html
    ↓
JavaScript: Updates .msh-results-count with total
    ↓
JavaScript: Hides spinner, restores opacity
    ↓
User sees updated table (no page reload!)
```

---

## ✅ Success Criteria

**Your AJAX filtering is complete when:**

1. ✅ Changing filters updates table without page reload
2. ✅ Loading spinner appears during AJAX request
3. ✅ Pagination works via AJAX
4. ✅ Filters preserve state across pagination
5. ✅ Regenerate button queues job (even if stub)
6. ✅ No JavaScript errors in console
7. ✅ No PHP errors in debug.log

---

## 🚀 Next Steps After AJAX Works

Once AJAX filtering is solid, you can move to:

**Queue Tab** (recommended next):
- Display job stats: pending, processing, complete, failed
- Use `msh_get_job_stats()` helper (already stubbed)
- Add "Process Now" button
- Simpler than Cache tab (no complex filtering)

---

## ❓ Questions?

### "Do I need to create a REST API endpoint?"

No! WordPress AJAX (`admin-ajax.php`) is simpler for admin interfaces. REST API is better for public-facing or third-party integrations.

### "What about the Regenerate button AJAX handler?"

The `ajax_get_cache_entries()` handles filtering. You'll also need `ajax_regenerate_entry()` for the Regenerate button (similar pattern). Want me to add that?

### "Should I use fetch() instead of jQuery?"

jQuery is fine since WordPress includes it. If you prefer vanilla JS fetch(), that works too!

---

**Good luck with AJAX implementation, AI #2!** 🚀

This will make the Cache tab feel super snappy and professional.

---

**End of AJAX Filtering Guide**
