# Token Balance Widget - Integration Guide

**Purpose:** Add the token balance widget to your admin interface
**Status:** Ready for integration

---

## Step 1: Include Widget Class

Add to `msh-image-optimizer.php`:

```php
// After line 130 (Token Manager include)
if (!class_exists('MSH_Token_Balance_Widget')) {
    require_once MSH_IO_PLUGIN_DIR . 'includes/class-msh-token-balance-widget.php';
}
```

---

## Step 2: Render Widget in Admin Page

### Option A: In Optimizer Hub (Recommended)

Edit `admin/class-msh-hub-page.php`, add after page header:

```php
public function display_page() {
    ?>
    <div class="wrap msh-optimizer-hub">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php
        // Token Balance Widget
        if ( class_exists( 'MSH_Token_Balance_Widget' ) ) {
            $widget = MSH_Token_Balance_Widget::get_instance();
            echo $widget->render( array(
                'site_id'      => $this->get_current_site_id(),
                'show_details' => true,
                'show_upgrade' => true,
            ) );
        }
        ?>

        <!-- Rest of your page content -->
        ...
    </div>
    <?php
}

private function get_current_site_id() {
    // Your site ID logic
    return get_option( 'msh_site_id', 'SITE_FREE' );
}
```

### Option B: In Settings Page

Edit `admin/image-optimizer-settings.php`:

```php
public function render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Image Optimizer Settings</h1>

        <?php
        // Token Balance Widget
        if ( class_exists( 'MSH_Token_Balance_Widget' ) ) {
            $widget = MSH_Token_Balance_Widget::get_instance();
            echo $widget->render();
        }
        ?>

        <form method="post" action="options.php">
            ...
        </form>
    </div>
    <?php
}
```

---

## Step 3: Dynamic Updates via JavaScript

The widget auto-refreshes every 30 seconds. To manually trigger updates:

```javascript
// After processing images
jQuery(document).trigger('msh:refresh-token-balance');

// Or call directly
if (window.MSH_TokenBalanceWidget) {
    MSH_TokenBalanceWidget.refreshBalance();
}
```

---

## Step 4: Handle Cap Modal

When batch processing hits the token limit:

```php
// In your batch processor
try {
    $manager = new MSH_Token_Manager( $site_id );
    $manager->deduct( $tokens_estimated, $operation_id );

    // Process image...

} catch ( Exception $e ) {
    // Token limit reached
    $balance = $manager->get_balance();

    $modal_data = array(
        'processed'     => $images_processed,
        'remaining'     => $images_total - $images_processed,
        'tokens_needed' => ( $images_total - $images_processed ) * 307,
        'tier'          => $balance['license_tier'],
    );

    // Store in transient for JS to display
    set_transient( 'msh_cap_modal_' . get_current_user_id(), $modal_data, HOUR_IN_SECONDS );

    wp_send_json_error( array(
        'message' => 'Token limit reached',
        'show_modal' => true,
    ) );
}
```

JavaScript handler:

```javascript
// In your AJAX response handler
if (response.data.show_modal) {
    // Fetch modal data
    $.get('/wp-json/msh/v1/cap-modal', function(data) {
        MSH_TokenBalanceWidget.showCapModal(data);
    });
}
```

---

## Step 5: Batch Estimator

Show estimated tokens before processing:

```php
// In your batch UI
public function render_batch_controls() {
    ?>
    <div class="msh-batch-controls">
        <input type="checkbox" class="msh-batch-selector" data-image-id="123" />
        <input type="checkbox" class="msh-batch-selector" data-image-id="124" />
        ...

        <div class="msh-batch-estimate" style="display: none;">
            Estimated: <span class="msh-batch-estimate-tokens">0</span> tokens
            for <span class="msh-batch-estimate-count">0</span> images
        </div>

        <button class="button button-primary msh-batch-process">
            Process Selected
        </button>
    </div>
    <?php
}
```

The JavaScript in `token-balance-widget.js` automatically handles the estimate display when checkboxes change.

---

## Step 6: CSS Integration

Once your CSS revamp is complete, add these classes to your stylesheet:

```css
/* Token Balance Widget */
.msh-token-balance-widget { }
.msh-token-balance-display { }
.msh-token-balance-primary { }
.msh-token-balance-images { }
.msh-token-balance-tokens { }
.msh-token-balance-bar { }
.msh-token-balance-bar-fill { }

/* Banners */
.msh-token-banner { }
.msh-token-banner-warning { }
.msh-token-banner-depleted { }

/* Modal */
.msh-token-cap-modal { }
.msh-token-cap-modal-overlay { }
.msh-token-cap-modal-content { }

/* Batch Estimate */
.msh-batch-estimate { }
.msh-batch-estimate.insufficient { }
```

---

## Complete Example

```php
// admin/class-msh-hub-page.php

public function display_page() {
    $site_id = get_option( 'msh_site_id', 'SITE_FREE' );
    $manager = new MSH_Token_Manager( $site_id );
    $balance = $manager->get_balance_api();
    ?>
    <div class="wrap msh-optimizer-hub">
        <h1>Optimizer Hub</h1>

        <?php
        // Token Balance Widget
        if ( class_exists( 'MSH_Token_Balance_Widget' ) ) {
            $widget = MSH_Token_Balance_Widget::get_instance();
            echo $widget->render( array(
                'site_id'      => $site_id,
                'show_details' => true,
                'show_upgrade' => ( $balance['tier'] === 'free' ),
            ) );
        }
        ?>

        <!-- Your existing content -->
        <div class="msh-hub-content">
            <!-- Batch selector, queue stats, etc. -->
        </div>
    </div>
    <?php
}
```

---

## Testing

### Test Widget Rendering
```bash
# Visit your admin page
open "http://thedot-optimizer-test.local/wp-admin/admin.php?page=msh-optimizer-hub"
```

### Test Balance Updates
```bash
# Deduct tokens manually
wp eval '$m = new MSH_Token_Manager("SITE_PRO"); $m->deduct(307, "test-' . time() . '");'

# Refresh admin page and verify widget updates
```

### Test Cap Modal
```bash
# Set balance low
wp db query "UPDATE wp_msh_ai_token_balance SET tokens_used = 49800 WHERE site_id='SITE_PRO'"

# Try to process images (should trigger modal after ~1 image)
```

---

## Troubleshooting

### Widget Not Showing
- Check if widget class is included: `class_exists('MSH_Token_Balance_Widget')`
- Check browser console for JavaScript errors
- Verify token-balance-widget.js is enqueued

### Balance Not Updating
- Check AJAX nonce validity
- Verify REST endpoint accessible: `/wp-json/msh/v1/ai-usage`
- Check browser network tab for failed requests

### Modal Not Appearing
- Check transient is set: `get_transient('msh_cap_modal_' . $user_id)`
- Verify JavaScript event listener is attached
- Check CSS display properties

---

**Status:** Ready for integration
**Next:** Add render call to your admin pages
