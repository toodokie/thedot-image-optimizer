# AI #2 - Queue Tab Implementation Guide

**Date:** October 19, 2025
**Task:** Build Queue tab with job stats widgets and "Process Now" action
**Status:** Cache tab ✅ DONE | Queue tab 🎯 IN PROGRESS

---

## 🎯 Goal

Build the Queue tab to display job processing statistics and allow manual queue processing.

**Features:**
1. **Stats Dashboard** - Show pending/processing/complete/failed job counts
2. **Priority Breakdown** - High/Medium/Normal queue visualization
3. **Process Now Button** - Manually trigger job processing
4. **Auto-refresh** - Update stats every 5 seconds (AJAX)
5. **Recent Jobs Table** - Show last 20 jobs with status

---

## 📋 What You're Building

```
┌─────────────────────────────────────────────────────┐
│ Queue Tab                                            │
├─────────────────────────────────────────────────────┤
│                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────┐│
│  │ Pending  │  │Processing│  │ Complete │  │Failed││
│  │   1,234  │  │     5    │  │  45,678  │  │  12 ││
│  └──────────┘  └──────────┘  └──────────┘  └─────┘│
│                                                      │
│  Priority Breakdown:                                 │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━ High: 234               │
│  ━━━━━━━━━━━━━━━━━━ Medium: 456                     │
│  ━━━━━━━━━━ Normal: 544                             │
│                                                      │
│  [Process Now] [Clear Failed Jobs]                  │
│                                                      │
│  Recent Jobs (last 20):                             │
│  ┌────────────────────────────────────────────────┐ │
│  │ ID │ Type   │ Entity │ Priority │ Status │ ... │ │
│  ├────┼────────┼────────┼──────────┼────────┼─────┤ │
│  │ 123│ regen  │ att:45 │ normal   │ pending│ ... │ │
│  └────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

---

## 🚀 Step-by-Step Implementation

### Step 1: Update `render_tab_content()` Queue Case

**File:** `admin/class-msh-hub-page.php`

Find the queue case in `render_tab_content()` method (around line 221):

**Current code:**
```php
case 'queue':
	echo '<p>' . esc_html__( 'Queue tab - Coming soon...', 'msh-image-optimizer' ) . '</p>';
	break;
```

**Change to:**
```php
case 'queue':
	$this->render_queue_tab();
	break;
```

---

### Step 2: Add `render_queue_tab()` Method

Add this new method after `render_cache_tab()` (around line 422):

```php
/**
 * Render Queue tab content.
 *
 * Displays job queue statistics and recent jobs table.
 *
 * @return void
 */
private function render_queue_tab() {
	// Get job stats from helper function
	$stats = function_exists( 'msh_get_job_stats' ) ? msh_get_job_stats() : array();

	// Extract stats with defaults
	$pending    = isset( $stats['pending'] ) ? (int) $stats['pending'] : 0;
	$processing = isset( $stats['processing'] ) ? (int) $stats['processing'] : 0;
	$complete   = isset( $stats['complete'] ) ? (int) $stats['complete'] : 0;
	$failed     = isset( $stats['failed'] ) ? (int) $stats['failed'] : 0;

	$priority_high   = isset( $stats['priority_high'] ) ? (int) $stats['priority_high'] : 0;
	$priority_medium = isset( $stats['priority_medium'] ) ? (int) $stats['priority_medium'] : 0;
	$priority_normal = isset( $stats['priority_normal'] ) ? (int) $stats['priority_normal'] : 0;

	$total_pending = $pending + $processing;
	?>
	<div class="msh-queue-tab">

		<!-- Stats Dashboard -->
		<div class="msh-queue-stats">
			<div class="msh-stat-card msh-stat-pending">
				<div class="msh-stat-icon">⏳</div>
				<div class="msh-stat-value" id="msh-stat-pending"><?php echo number_format( $pending ); ?></div>
				<div class="msh-stat-label"><?php esc_html_e( 'Pending', 'msh-image-optimizer' ); ?></div>
			</div>

			<div class="msh-stat-card msh-stat-processing">
				<div class="msh-stat-icon">⚙️</div>
				<div class="msh-stat-value" id="msh-stat-processing"><?php echo number_format( $processing ); ?></div>
				<div class="msh-stat-label"><?php esc_html_e( 'Processing', 'msh-image-optimizer' ); ?></div>
			</div>

			<div class="msh-stat-card msh-stat-complete">
				<div class="msh-stat-icon">✓</div>
				<div class="msh-stat-value" id="msh-stat-complete"><?php echo number_format( $complete ); ?></div>
				<div class="msh-stat-label"><?php esc_html_e( 'Complete', 'msh-image-optimizer' ); ?></div>
			</div>

			<div class="msh-stat-card msh-stat-failed">
				<div class="msh-stat-icon">✗</div>
				<div class="msh-stat-value" id="msh-stat-failed"><?php echo number_format( $failed ); ?></div>
				<div class="msh-stat-label"><?php esc_html_e( 'Failed', 'msh-image-optimizer' ); ?></div>
			</div>
		</div>

		<!-- Priority Breakdown -->
		<?php if ( $total_pending > 0 ) : ?>
			<div class="msh-priority-breakdown">
				<h3><?php esc_html_e( 'Priority Breakdown', 'msh-image-optimizer' ); ?></h3>

				<div class="msh-priority-bar">
					<div class="msh-priority-label">
						<span class="msh-priority-badge msh-priority-high"><?php esc_html_e( 'High', 'msh-image-optimizer' ); ?></span>
						<span class="msh-priority-count"><?php echo number_format( $priority_high ); ?></span>
					</div>
					<div class="msh-progress-bar">
						<?php
						$high_percent = $total_pending > 0 ? ( $priority_high / $total_pending ) * 100 : 0;
						?>
						<div class="msh-progress-fill msh-progress-high" style="width: <?php echo esc_attr( $high_percent ); ?>%;"></div>
					</div>
				</div>

				<div class="msh-priority-bar">
					<div class="msh-priority-label">
						<span class="msh-priority-badge msh-priority-medium"><?php esc_html_e( 'Medium', 'msh-image-optimizer' ); ?></span>
						<span class="msh-priority-count"><?php echo number_format( $priority_medium ); ?></span>
					</div>
					<div class="msh-progress-bar">
						<?php
						$medium_percent = $total_pending > 0 ? ( $priority_medium / $total_pending ) * 100 : 0;
						?>
						<div class="msh-progress-fill msh-progress-medium" style="width: <?php echo esc_attr( $medium_percent ); ?>%;"></div>
					</div>
				</div>

				<div class="msh-priority-bar">
					<div class="msh-priority-label">
						<span class="msh-priority-badge msh-priority-normal"><?php esc_html_e( 'Normal', 'msh-image-optimizer' ); ?></span>
						<span class="msh-priority-count"><?php echo number_format( $priority_normal ); ?></span>
					</div>
					<div class="msh-progress-bar">
						<?php
						$normal_percent = $total_pending > 0 ? ( $priority_normal / $total_pending ) * 100 : 0;
						?>
						<div class="msh-progress-fill msh-progress-normal" style="width: <?php echo esc_attr( $normal_percent ); ?>%;"></div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<!-- Action Buttons -->
		<div class="msh-queue-actions">
			<button type="button" id="msh-process-now" class="button button-primary">
				<?php esc_html_e( 'Process Now', 'msh-image-optimizer' ); ?>
			</button>

			<?php if ( $failed > 0 ) : ?>
				<button type="button" id="msh-clear-failed" class="button">
					<?php esc_html_e( 'Clear Failed Jobs', 'msh-image-optimizer' ); ?>
				</button>
			<?php endif; ?>

			<label class="msh-auto-refresh-toggle">
				<input type="checkbox" id="msh-auto-refresh" checked>
				<?php esc_html_e( 'Auto-refresh (5s)', 'msh-image-optimizer' ); ?>
			</label>
		</div>

		<!-- Recent Jobs Table -->
		<div class="msh-recent-jobs">
			<h3><?php esc_html_e( 'Recent Jobs (last 20)', 'msh-image-optimizer' ); ?></h3>
			<div id="msh-recent-jobs-container">
				<p class="msh-placeholder"><?php esc_html_e( 'Recent jobs will appear here...', 'msh-image-optimizer' ); ?></p>
			</div>
		</div>

	</div>
	<?php
}
```

---

### Step 3: Add AJAX Handler for Queue Stats

Add this method after `ajax_regenerate_entry()` (around line 622):

```php
/**
 * AJAX handler for refreshing queue stats.
 *
 * Returns updated job statistics.
 *
 * @return void
 */
public function ajax_refresh_queue_stats() {
	check_ajax_referer( 'msh_hub_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
			)
		);
	}

	$stats = function_exists( 'msh_get_job_stats' ) ? msh_get_job_stats() : array();

	wp_send_json_success(
		array(
			'stats' => $stats,
		)
	);
}

/**
 * AJAX handler for processing queue manually.
 *
 * Triggers job processing and returns results.
 *
 * @return void
 */
public function ajax_process_queue() {
	check_ajax_referer( 'msh_hub_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => esc_html__( 'Permission denied.', 'msh-image-optimizer' ),
			)
		);
	}

	// Trigger queue processing (stub will just log for now)
	if ( function_exists( 'msh_process_queue' ) ) {
		$result = msh_process_queue();
	} else {
		// Stub behavior - just return success
		$result = array(
			'processed' => 0,
			'message'   => __( 'Queue processing triggered. (Stub implementation)', 'msh-image-optimizer' ),
		);
	}

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
			)
		);
	}

	// Log telemetry
	if ( function_exists( 'msh_telemetry' ) ) {
		msh_telemetry(
			'hub_queue_process_manual',
			array(
				'user_id' => get_current_user_id(),
			)
		);
	}

	wp_send_json_success( $result );
}
```

---

### Step 4: Register AJAX Actions

In the `__construct()` method (around line 47), add the new AJAX actions:

**Current:**
```php
add_action( 'wp_ajax_msh_get_cache_entries', array( $this, 'ajax_get_cache_entries' ) );
add_action( 'wp_ajax_msh_regenerate_entry', array( $this, 'ajax_regenerate_entry' ) );
```

**Add:**
```php
add_action( 'wp_ajax_msh_refresh_queue_stats', array( $this, 'ajax_refresh_queue_stats' ) );
add_action( 'wp_ajax_msh_process_queue', array( $this, 'ajax_process_queue' ) );
```

---

### Step 5: Add JavaScript for Queue Tab

**File:** `assets/js/hub.js`

Add these methods to the `MSH_Hub` object (after `regenerateEntry`):

```javascript
/**
 * Refresh queue statistics via AJAX.
 */
refreshQueueStats: function() {
	$.ajax({
		url: window.mshHubData.ajaxUrl,
		type: 'POST',
		dataType: 'json',
		data: {
			action: 'msh_refresh_queue_stats',
			nonce: window.mshHubData.ajaxNonce
		}
	})
		.done((response) => {
			if (!response || !response.success) {
				console.warn('Failed to refresh queue stats.');
				return;
			}

			const stats = response.data.stats || {};

			// Update stat cards
			$('#msh-stat-pending').text(this.formatNumber(stats.pending || 0));
			$('#msh-stat-processing').text(this.formatNumber(stats.processing || 0));
			$('#msh-stat-complete').text(this.formatNumber(stats.complete || 0));
			$('#msh-stat-failed').text(this.formatNumber(stats.failed || 0));

			console.log('Queue stats refreshed:', stats);
		})
		.fail((xhr, status, error) => {
			console.error('Queue stats AJAX error:', error);
		});
},

/**
 * Process queue manually.
 */
processQueue: function() {
	const $button = $('#msh-process-now');
	const originalText = $button.text();

	$button.prop('disabled', true).text('Processing...');

	$.ajax({
		url: window.mshHubData.ajaxUrl,
		type: 'POST',
		dataType: 'json',
		data: {
			action: 'msh_process_queue',
			nonce: window.mshHubData.ajaxNonce
		}
	})
		.done((response) => {
			if (!response || !response.success) {
				const message = response && response.data && response.data.message
					? response.data.message
					: 'Unable to process queue.';
				alert(message);
				$button.text(originalText).prop('disabled', false);
				return;
			}

			// Show success
			$button.text('✓ Processing Complete').addClass('button-primary');

			// Refresh stats after processing
			this.refreshQueueStats();

			// Reset button after 2 seconds
			setTimeout(() => {
				$button.text(originalText).removeClass('button-primary').prop('disabled', false);
			}, 2000);

			console.log('Queue processed:', response.data);
		})
		.fail((xhr, status, error) => {
			console.error('Process queue AJAX error:', error);
			alert('Failed to process queue. Check console for details.');
			$button.text(originalText).prop('disabled', false);
		});
},

/**
 * Start auto-refresh for queue stats.
 */
startQueueAutoRefresh: function() {
	if (this.queueRefreshInterval) {
		clearInterval(this.queueRefreshInterval);
	}

	this.queueRefreshInterval = setInterval(() => {
		if ($('#msh-auto-refresh').is(':checked')) {
			this.refreshQueueStats();
		}
	}, 5000); // Every 5 seconds
},

/**
 * Format number with commas.
 */
formatNumber: function(num) {
	return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
},
```

---

### Step 6: Bind Queue Tab Events

In the `init()` method, add queue tab initialization:

```javascript
init: function() {
	if (!window.mshHubData) {
		console.warn('mshHubData missing – Hub scripts skipped.');
		return;
	}

	this.bindCacheFilters();
	this.bindCachePagination();
	this.bindRegenerateButtons();

	// Queue tab bindings
	this.bindQueueActions();

	this.bindAdditionalPlaceholders();
},

bindQueueActions: function() {
	// Process Now button
	$(document).on('click', '#msh-process-now', (event) => {
		event.preventDefault();
		this.processQueue();
	});

	// Auto-refresh toggle
	$(document).on('change', '#msh-auto-refresh', (event) => {
		if ($(event.currentTarget).is(':checked')) {
			this.startQueueAutoRefresh();
		} else {
			clearInterval(this.queueRefreshInterval);
		}
	});

	// Start auto-refresh if on queue tab
	if ($('.msh-queue-tab').length) {
		this.startQueueAutoRefresh();
	}
},
```

---

### Step 7: Add CSS Styles

**File:** `assets/css/hub.css`

Add these styles at the end:

```css
/* Queue Tab Styles */
.msh-queue-tab {
	padding: 20px 0;
}

.msh-queue-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
	margin-bottom: 30px;
}

.msh-stat-card {
	background: var(--msh-cream);
	border: 2px solid var(--msh-warm-gray);
	border-radius: 8px;
	padding: 20px;
	text-align: center;
	transition: transform 0.2s, border-color 0.2s;
}

.msh-stat-card:hover {
	transform: translateY(-2px);
	border-color: var(--msh-lime);
}

.msh-stat-icon {
	font-size: 32px;
	margin-bottom: 10px;
}

.msh-stat-value {
	font-family: var(--msh-font-heading);
	font-size: 36px;
	font-weight: 700;
	color: var(--msh-charcoal);
	margin-bottom: 5px;
}

.msh-stat-label {
	font-family: var(--msh-font-body);
	font-size: 14px;
	color: var(--msh-warm-gray);
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.msh-stat-pending .msh-stat-value { color: #f0ad4e; }
.msh-stat-processing .msh-stat-value { color: #5bc0de; }
.msh-stat-complete .msh-stat-value { color: #5cb85c; }
.msh-stat-failed .msh-stat-value { color: #d9534f; }

/* Priority Breakdown */
.msh-priority-breakdown {
	background: var(--msh-cream);
	border: 1px solid var(--msh-warm-gray);
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 30px;
}

.msh-priority-breakdown h3 {
	font-family: var(--msh-font-heading);
	margin-top: 0;
	margin-bottom: 20px;
}

.msh-priority-bar {
	margin-bottom: 15px;
}

.msh-priority-label {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}

.msh-priority-badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: 4px;
	font-size: 12px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.msh-priority-high {
	background: #d9534f;
	color: white;
}

.msh-priority-medium {
	background: #f0ad4e;
	color: white;
}

.msh-priority-normal {
	background: #5bc0de;
	color: white;
}

.msh-priority-count {
	font-weight: 600;
	color: var(--msh-charcoal);
}

.msh-progress-bar {
	height: 24px;
	background: #e9ecef;
	border-radius: 4px;
	overflow: hidden;
}

.msh-progress-fill {
	height: 100%;
	transition: width 0.3s ease;
}

.msh-progress-high {
	background: linear-gradient(90deg, #d9534f, #c9302c);
}

.msh-progress-medium {
	background: linear-gradient(90deg, #f0ad4e, #ec971f);
}

.msh-progress-normal {
	background: linear-gradient(90deg, #5bc0de, #46b8da);
}

/* Queue Actions */
.msh-queue-actions {
	display: flex;
	gap: 15px;
	align-items: center;
	margin-bottom: 30px;
}

.msh-auto-refresh-toggle {
	margin-left: auto;
	font-weight: 500;
}

.msh-auto-refresh-toggle input {
	margin-right: 8px;
}

/* Recent Jobs */
.msh-recent-jobs h3 {
	font-family: var(--msh-font-heading);
	margin-bottom: 15px;
}

#msh-recent-jobs-container {
	background: var(--msh-cream);
	border: 1px solid var(--msh-warm-gray);
	border-radius: 8px;
	padding: 20px;
	min-height: 100px;
}

.msh-placeholder {
	text-align: center;
	color: var(--msh-warm-gray);
	font-style: italic;
}
```

---

## 🧪 Testing Your Implementation

### Test Checklist:

1. **Navigate to Queue Tab:**
   - Go to: The Dot → Optimizer Hub → Queue tab
   - Should see 4 stat cards (Pending, Processing, Complete, Failed)
   - Numbers should match stub data

2. **Verify Stats Display:**
   - Check that `msh_get_job_stats()` stub returns mock data
   - Stat cards should show formatted numbers (e.g., "1,234")
   - Icons should display: ⏳ ⚙️ ✓ ✗

3. **Test Priority Breakdown:**
   - Should see 3 progress bars (High, Medium, Normal)
   - Bar widths should reflect proportions
   - Colors: High=red, Medium=orange, Normal=blue

4. **Test Process Now Button:**
   - Click "Process Now"
   - Button text should change: "Processing..." → "✓ Processing Complete"
   - Should reset after 2 seconds
   - Console should log: "Queue processed"

5. **Test Auto-Refresh:**
   - Checkbox should be checked by default
   - Stats should refresh every 5 seconds
   - Console should log: "Queue stats refreshed" every 5s
   - Uncheck checkbox → refreshing stops

6. **Check Console:**
   - No JavaScript errors
   - AJAX requests to `admin-ajax.php` visible in Network tab
   - Action: `msh_refresh_queue_stats` every 5 seconds

---

## 💡 How It Works

### Data Flow:

```
Page Load
    ↓
render_queue_tab() called
    ↓
Calls msh_get_job_stats()  ← AI #1's stub
    ↓
Returns: {pending: 5, processing: 2, complete: 123, failed: 1, ...}
    ↓
PHP renders stat cards with initial values
    ↓
JavaScript init() detects .msh-queue-tab
    ↓
Starts auto-refresh (setInterval 5s)
    ↓
Every 5 seconds:
    AJAX POST to msh_refresh_queue_stats
    ↓
    Returns updated stats JSON
    ↓
    JavaScript updates #msh-stat-pending, etc.
```

### When AI #1 Replaces Stubs:

Your code won't change! The function signature stays the same:

```php
// Stub (now):
function msh_get_job_stats() {
    return array(
        'pending'    => 5,
        'processing' => 2,
        'complete'   => 123,
        'failed'     => 1,
        // ...
    );
}

// Real (later):
function msh_get_job_stats() {
    global $wpdb;
    // Query msh_jobs table
    $pending = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}msh_jobs WHERE status='pending'" );
    // ...
    return array( ... );
}
```

Your UI will instantly show real data!

---

## ✅ Success Criteria

**Your Queue tab is complete when:**

1. ✅ Stat cards display job counts
2. ✅ Priority breakdown shows high/medium/normal queues
3. ✅ Process Now button triggers AJAX
4. ✅ Auto-refresh updates stats every 5 seconds
5. ✅ No JavaScript errors in console
6. ✅ No PHP errors in debug.log
7. ✅ Brand styles applied (lime accents, charcoal text)

---

## 🚀 After Queue Tab Works

Once this is working, you can add:

**Phase 1: Recent Jobs Table** (optional enhancement)
- Call `msh_get_recent_jobs()` helper (AI #1 will create stub)
- Display last 20 jobs in table
- Show: ID, Type, Entity, Priority, Status, Created

**Phase 2: Clear Failed Jobs** (optional)
- Wire up "Clear Failed Jobs" button
- AJAX call to `msh_clear_failed_jobs()`

**Phase 3: Move to Events Tab**
- Real-time event stream
- Use `msh_get_recent_events()` helper

---

## 📝 Summary

**What to build:**
1. Update `render_tab_content()` queue case
2. Add `render_queue_tab()` method (stats cards + priority bars)
3. Add AJAX handlers (`ajax_refresh_queue_stats`, `ajax_process_queue`)
4. Register AJAX actions in constructor
5. Add JavaScript methods (`refreshQueueStats`, `processQueue`, auto-refresh)
6. Add CSS styles for stat cards and progress bars

**Estimated time:** 45-60 minutes

**Uses these stub functions:**
- `msh_get_job_stats()` - Returns job counts
- `msh_process_queue()` - Triggers processing (stub just logs)
- `msh_telemetry()` - Logs user action

---

**Good luck, AI #2! Let's build an awesome Queue tab! 🚀**

---

**End of Queue Tab Guide**
