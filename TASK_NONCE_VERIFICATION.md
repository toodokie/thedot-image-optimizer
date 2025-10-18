# Task: Add Nonce Verification and Input Sanitization

**Priority:** 🟡 HIGH - Blocks WordPress.org approval (security)
**Estimated time:** 90-120 minutes
**Violations:** ~30 instances across multiple files

## Context

WordPress.org requires proper nonce verification and input sanitization for all user input handling. This prevents CSRF attacks and ensures data integrity.

**Plugin Check violations being fixed:**
- `WordPress.Security.NonceVerification.Missing`
- `WordPress.Security.NonceVerification.Recommended`
- `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`
- `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`

## Pattern from Expert Triage

### For POST/GET Handlers

```php
// ❌ WRONG - No nonce, no sanitization
if ( isset( $_POST['mode'] ) ) {
    $mode = $_POST['mode'];
    $ids = $_POST['image_ids'];
    // process...
}

// ✅ CORRECT - Nonce + capability + sanitize
if ( isset( $_POST['mshio_nonce'] ) && check_admin_referer( 'mshio_bulk', 'mshio_nonce' ) ) {
    if ( current_user_can( 'upload_files' ) ) {
        $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
        $ids  = isset( $_POST['image_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['image_ids'] ) ) : [];
        // process...
    }
}
```

### For Forms (add nonce field)

```php
<form method="post">
    <?php wp_nonce_field( 'mshio_bulk', 'mshio_nonce' ); ?>
    <input type="text" name="business_name" />
    <button type="submit">Save</button>
</form>
```

### For $_SERVER Reads

```php
// ❌ WRONG
$user_agent = $_SERVER['HTTP_USER_AGENT'];

// ✅ CORRECT - Sanitize (no unslash needed for $_SERVER)
$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
    ? sanitize_text_field( (string) $_SERVER['HTTP_USER_AGENT'] )
    : '';
```

## Files Needing Fixes

Based on Plugin Check output:

1. **class-msh-image-optimizer.php** (~15 instances)
   - Lines: 7373-7376, 7557, 8137, 8329, 8703, 8837, 9500

2. **class-msh-webp-delivery.php** (~4 instances)
   - Lines: 236, 251-253

3. **class-msh-metadata-regeneration-background.php** (~3 instances)
   - Lines: 393-395

4. **class-msh-ai-ajax-handlers.php** (~3 instances)
   - Lines: 77-79

5. **admin/image-optimizer-settings.php** (~5 instances)
   - Lines: 118-119, 552, 561, 565

6. **admin/image-optimizer-admin.php** (~2 instances)
   - Line: 1253

## Sanitization Functions Reference

| Input Type | Sanitization Function | Example |
|-----------|----------------------|---------|
| Text string | `sanitize_text_field()` | Names, titles |
| Textarea | `sanitize_textarea_field()` | Long text, descriptions |
| Email | `sanitize_email()` | Email addresses |
| URL | `esc_url_raw()` | URLs being saved |
| Key/slug | `sanitize_key()` | Mode, type, slug |
| Integer | `(int)` or `intval()` | IDs, counts |
| Array of IDs | `array_map('intval', $array)` | Image IDs |
| Boolean | `(bool)` or `rest_sanitize_boolean()` | Checkboxes |
| HTML | `wp_kses_post()` | Rich text content |

## Step-by-Step Instructions

### Step 1: Identify Input Handling

Search for patterns:
```bash
grep -n "\$_POST\[" FILE.php
grep -n "\$_GET\[" FILE.php
grep -n "\$_SERVER\[" FILE.php
grep -n "\$_COOKIE\[" FILE.php
```

### Step 2: Add Nonce Verification

**For AJAX handlers:**
```php
public function handle_ajax_action() {
    // Verify nonce
    check_ajax_referer( 'mshio_action', 'nonce' );

    // Check capability
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
    }

    // Sanitize input
    $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';

    // Process...
}
```

**For admin POST handlers:**
```php
public function handle_settings_save() {
    // Verify nonce
    if ( ! isset( $_POST['mshio_settings_nonce'] ) ||
         ! wp_verify_nonce( $_POST['mshio_settings_nonce'], 'mshio_save_settings' ) ) {
        wp_die( esc_html__( 'Security check failed', 'msh-image-optimizer' ) );
    }

    // Check capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized', 'msh-image-optimizer' ) );
    }

    // Sanitize input
    $business_name = isset( $_POST['business_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) )
        : '';

    // Process...
}
```

### Step 3: Add Nonce Fields to Forms

Find the form in the corresponding template/admin file and add:

```php
<form method="post" action="">
    <?php wp_nonce_field( 'mshio_save_settings', 'mshio_settings_nonce' ); ?>

    <input type="text" name="business_name" value="<?php echo esc_attr( $business_name ); ?>" />

    <button type="submit">Save</button>
</form>
```

### Step 4: Sanitize All Inputs

Apply the right sanitization function for each input type:

```php
// Text fields
$name = isset( $_POST['business_name'] )
    ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) )
    : '';

// Integers
$id = isset( $_POST['image_id'] )
    ? (int) $_POST['image_id']
    : 0;

// Array of integers
$ids = isset( $_POST['image_ids'] )
    ? array_map( 'intval', (array) wp_unslash( $_POST['image_ids'] ) )
    : [];

// URLs
$url = isset( $_POST['website'] )
    ? esc_url_raw( wp_unslash( $_POST['website'] ) )
    : '';

// Email
$email = isset( $_POST['email'] )
    ? sanitize_email( wp_unslash( $_POST['email'] ) )
    : '';

// Checkboxes (present = '1', absent = not set)
$enabled = isset( $_POST['enable_feature'] ) && '1' === $_POST['enable_feature'];

// Radio/select with known values
$mode = isset( $_POST['mode'] )
    ? sanitize_key( wp_unslash( $_POST['mode'] ) )
    : 'default';

// Validate against whitelist
$mode = in_array( $mode, ['manual', 'auto', 'smart'], true ) ? $mode : 'manual';

// Textarea
$description = isset( $_POST['description'] )
    ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) )
    : '';
```

## Detailed File-by-File Guide

### File 1: admin/image-optimizer-settings.php

**Lines 118-119: Onboarding form submission**

Find:
```php
if ( isset( $_POST['msh_onboarding_submit'] ) ) {
```

Add before processing:
```php
if ( isset( $_POST['msh_onboarding_submit'] ) ) {
    // Verify nonce
    if ( ! isset( $_POST['msh_onboarding_nonce'] ) ||
         ! wp_verify_nonce( $_POST['msh_onboarding_nonce'], 'msh_save_onboarding' ) ) {
        wp_die( esc_html__( 'Security check failed', 'msh-image-optimizer' ) );
    }

    // Check capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized', 'msh-image-optimizer' ) );
    }

    // Sanitize all inputs
    $business_name = isset( $_POST['msh_business_name'] )
        ? sanitize_text_field( wp_unslash( $_POST['msh_business_name'] ) )
        : '';

    // ... sanitize other fields ...
}
```

Find the onboarding form and add nonce field:
```php
<form method="post">
    <?php wp_nonce_field( 'msh_save_onboarding', 'msh_onboarding_nonce' ); ?>
    <!-- rest of form -->
</form>
```

### File 2: class-msh-webp-delivery.php

**Lines 236, 251-253: $_SERVER reads**

Find:
```php
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$accept = $_SERVER['HTTP_ACCEPT'];
```

Replace with:
```php
$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
    ? sanitize_text_field( (string) $_SERVER['HTTP_USER_AGENT'] )
    : '';

$accept = isset( $_SERVER['HTTP_ACCEPT'] )
    ? sanitize_text_field( (string) $_SERVER['HTTP_ACCEPT'] )
    : '';
```

### File 3: class-msh-ai-ajax-handlers.php

**Lines 77-79: AJAX input handling**

The file likely already has `check_ajax_referer()` at the top of the function. If not, add:

```php
public function handle_regenerate_metadata() {
    // Verify AJAX nonce
    check_ajax_referer( 'msh-image-optimizer-nonce', 'nonce' );

    // Check capability
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
    }

    // Sanitize inputs
    $scope = isset( $_POST['scope'] )
        ? sanitize_key( wp_unslash( $_POST['scope'] ) )
        : 'all';

    $image_ids = isset( $_POST['image_ids'] )
        ? array_map( 'intval', (array) wp_unslash( $_POST['image_ids'] ) )
        : [];

    // Validate scope
    $scope = in_array( $scope, ['all', 'published', 'missing'], true ) ? $scope : 'all';

    // ... rest of function
}
```

Make sure the JavaScript sends the nonce:
```javascript
$.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'msh_regenerate_metadata',
        nonce: mshImageOptimizer.nonce,  // Should be localized
        scope: scope,
        image_ids: imageIds
    }
});
```

## Testing

After adding nonce verification:

1. **Test the functionality** - make sure forms still submit
2. **Check for nonce errors** - look for "Security check failed" messages
3. **Verify AJAX calls work** - check browser console for errors
4. **Test with and without permissions** - make sure capability checks work

## Common Issues

### Issue 1: Form doesn't submit / "Security check failed"

**Cause:** Nonce field missing from form
**Fix:** Add `wp_nonce_field()` to the form

### Issue 2: AJAX returns "403 Forbidden"

**Cause:** Nonce not being sent in AJAX request
**Fix:** Add nonce to AJAX data and localize it:

```php
wp_localize_script( 'msh-admin-js', 'mshImageOptimizer', array(
    'nonce' => wp_create_nonce( 'msh-image-optimizer-nonce' ),
    'ajaxurl' => admin_url( 'admin-ajax.php' )
) );
```

### Issue 3: Values are empty after sanitization

**Cause:** Using wrong sanitization function
**Fix:** Check the sanitization reference table above

## Checklist

### admin/image-optimizer-settings.php
- [ ] Line 118-119: Add nonce verification to onboarding submit
- [ ] Add wp_nonce_field() to onboarding form
- [ ] Line 552, 561, 565: Sanitize POST inputs
- [ ] Test onboarding flow
- [ ] Commit

### admin/image-optimizer-admin.php
- [ ] Line 1253: Add nonce verification and sanitization
- [ ] Test affected functionality
- [ ] Commit

### class-msh-webp-delivery.php
- [ ] Line 236: Sanitize HTTP_USER_AGENT
- [ ] Lines 251-253: Sanitize HTTP_ACCEPT and related $_SERVER vars
- [ ] Test WebP delivery
- [ ] Commit

### class-msh-ai-ajax-handlers.php
- [ ] Lines 77-79: Add check_ajax_referer() and sanitize inputs
- [ ] Verify nonce is localized in JavaScript
- [ ] Test AJAX regeneration
- [ ] Commit

### class-msh-metadata-regeneration-background.php
- [ ] Lines 393-395: Sanitize POST inputs
- [ ] Add nonce verification if needed
- [ ] Test background processing
- [ ] Commit

### class-msh-image-optimizer.php
- [ ] Lines 7373-7376: Add nonce + sanitization
- [ ] Line 7557: Sanitize input
- [ ] Line 8137: Sanitize input
- [ ] Line 8329: Sanitize input
- [ ] Line 8703: Sanitize input
- [ ] Line 8837: Sanitize input
- [ ] Line 9500: Sanitize input
- [ ] Test all affected features
- [ ] Commit

## Success Criteria

- [ ] All $_POST, $_GET, $_COOKIE, $_SERVER reads are sanitized
- [ ] All form submissions have nonce verification
- [ ] All AJAX handlers use check_ajax_referer()
- [ ] All handlers check user capabilities
- [ ] Plugin functionality still works correctly
- [ ] Ready for Plugin Check re-run

## Reference

WordPress Codex:
- https://developer.wordpress.org/apis/security/nonces/
- https://developer.wordpress.org/apis/security/sanitizing-securing-output/
- https://developer.wordpress.org/apis/security/data-validation/
