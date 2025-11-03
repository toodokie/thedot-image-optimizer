# Token System Compliance Fixes - Implementation Checklist

**Date:** November 2, 2025
**Status:** 9 gaps identified, requires implementation before production
**Priority:** Critical fixes must complete before v1.2.17 ships with token system
**Reference:** `docs/TOKEN_BASED_PRICING_STRATEGY.md` Part 12

---

## Executive Summary

Compliance audit identified 9 gaps in the token-based pricing system documentation. All business logic is sound, but implementation details need correction before production deployment.

**Verdict:**
- ✅ Business model compliant
- ✅ Safety & accounting compliant (with SQL/reconcile fixes)
- ❗ UI copy needs alignment with Smart Mode reality (307 tokens vs 210 target)

---

## Critical Fixes (Block Production)

### Fix 1: Atomic UPDATE SQL (Gap 3)
**Priority:** 🔴 Critical
**File:** `includes/class-msh-token-manager.php`
**Line:** ~157 (deduct method)

**Issue:** Using generated column `tokens_remaining` in WHERE clause is unsafe for concurrency.

**Implementation:**
```php
// REPLACE this SQL:
$wpdb->query( $wpdb->prepare(
    "UPDATE {$wpdb->prefix}msh_ai_token_balance
     SET tokens_used = tokens_used + %d
     WHERE site_id = %s
     AND tokens_remaining >= %d  -- ❌ Generated column
     AND status = 'active'",
    $tokens, $site_id, $tokens
) );

// WITH this SQL:
$affected = $wpdb->query( $wpdb->prepare(
    "UPDATE {$wpdb->prefix}msh_ai_token_balance
     SET tokens_used = tokens_used + %d
     WHERE site_id = %s
     AND status = 'active'
     AND period_end > NOW()
     AND (tokens_used + %d) <= tokens_allocated",  -- ✅ Explicit atomic check
    $tokens, $site_id, $tokens
) );

if ( $affected === 0 ) {
    throw new Exception( 'Insufficient tokens or concurrent operation' );
}
```

**Test Case:**
```php
// Concurrent deduction test
$token_manager = new MSH_Token_Manager();
$threads = array();

for ( $i = 0; $i < 10; $i++ ) {
    $threads[] = wp_schedule_single_event( time(), 'test_concurrent_deduct', array( 100 ) );
}

// Should only succeed for N threads where N × 100 ≤ remaining balance
// Others should throw "Insufficient tokens" exception
```

**Verification:** Run concurrency test with 10 simultaneous deduction attempts

---

### Fix 2: Enterprise BYOK Enforcement (Gap 5)
**Priority:** 🔴 Critical
**Files:**
- `includes/class-msh-token-manager.php` (new method)
- `includes/class-msh-openai-connector.php` (gate AI calls)

**Issue:** Enterprise tier can consume unlimited bundled AI tokens. Strategy mandates BYOK but no code enforces it.

**Implementation:**

**Step 1:** Add enforcement method to Token Manager
```php
/**
 * Check if license tier can use bundled AI
 *
 * @param string $license_tier License tier.
 * @return bool|WP_Error True if can use bundled, WP_Error if BYOK required.
 */
public function can_use_bundled_ai( $license_tier ) {
    if ( $license_tier === 'enterprise' ) {
        $byok_key = get_option( 'msh_byok_openai_key' );

        if ( empty( $byok_key ) ) {
            return new WP_Error(
                'byok_required',
                'Enterprise tier requires your own OpenAI API key. Please add it in Settings > AI Configuration > Bring Your Own Key.'
            );
        }

        return false; // Use BYOK, not bundled
    }

    return true; // Free/Pro/Business can use bundled AI
}
```

**Step 2:** Gate AI calls in OpenAI Connector
```php
// In MSH_OpenAI_Connector::generate_metadata()
public function generate_metadata( $attachment_id, $context ) {
    $token_manager = MSH_Token_Manager::get_instance();
    $license_tier = $this->get_license_tier();

    // Check BYOK enforcement
    $can_use_bundled = $token_manager->can_use_bundled_ai( $license_tier );
    if ( is_wp_error( $can_use_bundled ) ) {
        return $can_use_bundled; // Block AI call, return error
    }

    // If false, use BYOK key instead of bundled
    $api_key = $can_use_bundled ? $this->get_bundled_api_key() : get_option( 'msh_byok_openai_key' );

    // Continue with AI call...
}
```

**Test Case:**
```bash
# Set license to Enterprise
wp option update msh_license_tier enterprise

# Try AI call without BYOK key
wp msh analyze --count=1
# Should fail with: "Enterprise tier requires your own OpenAI API key..."

# Add BYOK key
wp option update msh_byok_openai_key "sk-..."

# Try again
wp msh analyze --count=1
# Should succeed using BYOK key
```

**Verification:** Ensure Enterprise without BYOK cannot run AI calls

---

### Fix 3: Stop-on-Cap Queue Behavior (Gap 7)
**Priority:** 🔴 Critical
**File:** `includes/class-msh-image-optimizer.php`
**Method:** `analyze_images_batch()`

**Issue:** Undefined behavior when token cap is hit mid-batch.

**Implementation:**
```php
public function analyze_images_batch( $image_ids ) {
    $token_manager = MSH_Token_Manager::get_instance();

    $results = array(
        'completed' => array(),
        'cancelled' => array(),
        'cap_hit'   => false,
    );

    foreach ( $image_ids as $image_id ) {
        // Pre-flight token check
        $tokens_needed = $token_manager->estimate_tokens( 'ai_analysis_smart' );

        if ( ! $token_manager->has_tokens( $tokens_needed ) ) {
            // Cap hit: stop here, cancel remaining
            $results['cap_hit'] = true;
            $results['cancelled'] = array_diff( $image_ids, $results['completed'] );

            // Store modal data for frontend
            $remaining = count( $results['cancelled'] );
            $modal_data = array(
                'message'          => sprintf( 'Token limit reached after %d images.', count( $results['completed'] ) ),
                'remaining_images' => $remaining,
                'options'          => array(
                    array(
                        'id'    => 'upgrade',
                        'label' => 'Upgrade to Pro for 50,000 tokens/month',
                        'url'   => admin_url( 'admin.php?page=msh-upgrade' ),
                    ),
                    array(
                        'id'    => 'buy_pack',
                        'label' => sprintf( 'Buy %d tokens for $%d', $remaining * 307, ceil( $remaining * 0.307 ) ),
                        'url'   => admin_url( 'admin.php?page=msh-credit-packs' ),
                    ),
                    array(
                        'id'    => 'contextual',
                        'label' => 'Continue with non-AI mode (no tokens)',
                        'action' => 'continue_contextual',
                    ),
                ),
            );

            set_transient( 'msh_cap_modal_' . get_current_user_id(), $modal_data, HOUR_IN_SECONDS );

            break; // Stop processing
        }

        // Process image
        $result = $this->analyze_single_image( $image_id );
        $results['completed'][] = $image_id;
    }

    return $results;
}
```

**Frontend Modal (JavaScript):**
```javascript
// In assets/js/image-optimizer-admin.js
function checkForCapModal() {
    jQuery.get(ajaxurl, {
        action: 'msh_check_cap_modal'
    }, function(response) {
        if (response.success && response.data.modal) {
            showCapModal(response.data.modal);
        }
    });
}

function showCapModal(data) {
    const html = `
        <div class="msh-cap-modal">
            <h2>⚠️ Token Limit Reached</h2>
            <p>${data.message}</p>
            <p>You have ${data.remaining_images} images remaining in this batch.</p>
            <div class="msh-cap-options">
                ${data.options.map(opt => `
                    <button class="button button-primary" data-action="${opt.action || 'navigate'}" data-url="${opt.url}">
                        ${opt.label}
                    </button>
                `).join('')}
            </div>
        </div>
    `;

    // Display modal (use existing modal framework)
    jQuery('#msh-modal-container').html(html).show();
}
```

**Test Case:**
```bash
# Set balance to 1,000 tokens
wp option update msh_tokens_remaining 1000

# Try to optimize 10 images (10 × 307 = 3,070 tokens needed)
wp msh optimize --ids=1,2,3,4,5,6,7,8,9,10

# Should complete ~3 images, then stop with cap modal
# Output:
# Optimized: 3 images (921 tokens)
# Cancelled: 7 images (token limit reached)
# Modal: Upgrade / Buy pack / Continue non-AI
```

**Verification:** Ensure batch stops cleanly when cap is hit, surfaces modal

---

### Fix 4: Reconcile Underflow Protection (Gap 8)
**Priority:** 🔴 Critical
**File:** `includes/class-msh-token-manager.php`
**Method:** `reconcile()`

**Issue:** If actual < estimated, `tokens_used` can go negative.

**Implementation:**
```php
public function reconcile( $operation_id, $tokens_actual ) {
    global $wpdb;

    $transaction = $this->get_transaction( $operation_id );
    $tokens_estimated = $transaction['tokens'];
    $difference = $tokens_actual - $tokens_estimated;

    if ( $difference != 0 ) {
        // Get current balance
        $balance = $this->get_balance();
        $current_used = $balance['tokens_used'];

        // Calculate new value with floor at 0
        $new_used = max( 0, $current_used + $difference );

        // Update with explicit value (not increment)
        $affected = $wpdb->update(
            $wpdb->prefix . 'msh_ai_token_balance',
            array( 'tokens_used' => $new_used ),
            array( 'site_id' => $this->get_site_id() ),
            array( '%d' ),
            array( '%s' )
        );

        // Log to audit table
        $this->log_audit( array(
            'operation_id'       => $operation_id,
            'site_id'            => $this->get_site_id(),
            'tokens_estimated'   => $tokens_estimated,
            'tokens_actual'      => $tokens_actual,
            'tokens_difference'  => $difference,
            'underflow'          => ( $current_used + $difference < 0 ),
            'clamped_at'         => $new_used,
        ) );

        // Alert on underflow
        if ( $current_used + $difference < 0 ) {
            error_log( sprintf(
                '[MSH Token] Underflow prevented: op=%s, current=%d, diff=%d, clamped=%d',
                $operation_id, $current_used, $difference, $new_used
            ) );

            // Notify telemetry
            do_action( 'msh_token_underflow_detected', array(
                'operation_id' => $operation_id,
                'estimated'    => $tokens_estimated,
                'actual'       => $tokens_actual,
                'difference'   => $difference,
            ) );
        }
    }

    return true;
}
```

**Audit Table Schema:**
```sql
CREATE TABLE wp_msh_ai_token_audit (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    operation_id VARCHAR(64) NOT NULL,
    site_id VARCHAR(64) NOT NULL,
    attachment_id BIGINT,
    tokens_estimated INT NOT NULL,
    tokens_actual INT,
    tokens_difference INT,
    model VARCHAR(32),
    mode VARCHAR(32),
    underflow BOOLEAN DEFAULT FALSE,
    clamped_at INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_site (site_id),
    INDEX idx_operation (operation_id),
    INDEX idx_underflow (underflow),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Test Case:**
```php
// Test underflow protection
$token_manager = new MSH_Token_Manager();

// Set current balance: 100 tokens used
update_option( 'msh_tokens_used', 100 );

// Simulate over-estimation
$token_manager->deduct( 500, 'test_op_123' );     // Estimated: 500, deducted
$token_manager->reconcile( 'test_op_123', 250 );   // Actual: 250, difference: -250

// Check balance
$balance = $token_manager->get_balance();
// Should be: max(0, 100 + 500 - 250) = 350 ✅
// NOT: 100 + 500 - 250 = 350 (happens to work)

// Extreme case: Actual < Estimated and would go negative
update_option( 'msh_tokens_used', 50 );
$token_manager->deduct( 100, 'test_op_456' );     // Estimated: 100
$token_manager->reconcile( 'test_op_456', 10 );    // Actual: 10, difference: -90

// Check balance
$balance = $token_manager->get_balance();
// Should be: max(0, 50 + 100 - 90) = 60 ✅
// NOT: 50 + 100 - 90 = 60 (happens to work)

// Edge case that would break without max():
update_option( 'msh_tokens_used', 30 );
$token_manager->deduct( 50, 'test_op_789' );      // Estimated: 50
$token_manager->reconcile( 'test_op_789', 5 );     // Actual: 5, difference: -45

// Check balance
$balance = $token_manager->get_balance();
// Should be: max(0, 30 + 50 - 45) = 35 ✅
// Would be: 30 + 50 - 45 = 35 (still works)

// True underflow case:
update_option( 'msh_tokens_used', 20 );
$token_manager->deduct( 50, 'test_op_999' );      // Estimated: 50
$token_manager->reconcile( 'test_op_999', 5 );     // Actual: 5, difference: -45

// Check balance
$balance = $token_manager->get_balance();
// Should be: max(0, 20 + 50 - 45) = 25 ✅
// Without max: 20 + 50 - 45 = 25 (works)

// ACTUAL underflow case:
update_option( 'msh_tokens_used', 10 );
$token_manager->deduct( 100, 'test_op_underflow' );  // Estimated: 100 → 110 used
$token_manager->reconcile( 'test_op_underflow', 5 ); // Actual: 5, diff: -95

// Check balance
$balance = $token_manager->get_balance();
// Should be: max(0, 10 + 100 - 95) = 15 ✅
// Without max: 10 + 100 - 95 = 15 (works)

// The TRUE underflow only happens if:
// current_used + (actual - estimated) < 0
// 10 + (5 - 100) = 10 - 95 = -85 ❌
// With max(0, ...): 0 ✅
```

**Verification:** Check audit table shows underflow=1 when triggered, tokens_used never negative

---

## High Priority Fixes (Within 1 Week)

### Fix 5: Free Daily Throttle Math (Gap 1)
**Priority:** 🟡 High
**File:** `includes/class-msh-token-manager.php`
**Constant:** `FREE_DAILY_THROTTLE`

**Current:** 200 tokens/day
**Smart Mode Reality:** 307 tokens/image
**Result:** 0.65 images/day ❌

**Implementation:**
```php
// Option A (Recommended): Match marketing copy
const FREE_DAILY_THROTTLE = 614; // 2 Smart images per day (307 × 2)

// Option B: Update marketing copy
const FREE_DAILY_THROTTLE = 200; // ~1 Smart image every 1.5 days

// Update UI copy to use live telemetry:
$telemetry = MSH_Telemetry::get_instance();
$avg_tokens = $telemetry->get_median_tokens( 'smart', 7 );
$images_per_day = floor( self::FREE_DAILY_THROTTLE / $avg_tokens );

echo "Daily limit: " . self::FREE_DAILY_THROTTLE . " tokens = ~{$images_per_day} images/day";
```

---

### Fix 6: Telemetry-Driven UI Estimates (Gap 6)
**Priority:** 🟡 High
**Files:**
- `includes/class-msh-telemetry.php` (new class)
- `admin/partials/settings-ai.php` (update estimates)

**Issue:** All estimates hardcode 210 tokens, but Smart Mode uses 307 tokens.

**Implementation:**
```php
/**
 * Get median tokens for a mode from last N days
 *
 * @param string $mode Mode name (smart, quick, high).
 * @param int    $days Days to look back (default: 7).
 * @return int Median tokens.
 */
public function get_median_tokens( $mode, $days = 7 ) {
    global $wpdb;

    $since = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

    // Simple average for now (median query is complex)
    $avg = $wpdb->get_var( $wpdb->prepare(
        "SELECT AVG(token_count) as avg_tokens
         FROM {$wpdb->prefix}msh_ai_token_usage
         WHERE mode = %s
         AND created_at >= %s",
        $mode,
        $since
    ) );

    // Fallback to defaults if no data
    $defaults = array(
        'smart' => 307,
        'quick' => 85,
        'high'  => 400,
    );

    return (int) round( $avg ) ?: ( $defaults[ $mode ] ?? 250 );
}
```

**Update UI Estimates:**
```php
// In settings page
$telemetry = MSH_Telemetry::get_instance();
$tokens_remaining = $balance['tokens_remaining'];

$estimates = array(
    'smart' => array(
        'tokens_per_image' => $telemetry->get_median_tokens( 'smart', 7 ),
    ),
    'quick' => array(
        'tokens_per_image' => $telemetry->get_median_tokens( 'quick', 7 ),
    ),
    'high' => array(
        'tokens_per_image' => $telemetry->get_median_tokens( 'high', 7 ),
    ),
);

foreach ( $estimates as $mode => &$est ) {
    $est['images_remaining'] = floor( $tokens_remaining / $est['tokens_per_image'] );
}

// Display:
// Smart mode (~307 tokens): ~122 images remaining
// Quick mode (~85 tokens): ~441 images remaining
// High detail (~400 tokens): ~93 images remaining
```

---

### Fix 7: Global Cap Value (Gap 2)
**Priority:** 🟡 High
**File:** `includes/class-msh-token-manager.php`
**Constant:** `GLOBAL_FREE_CAP`

**Current:** 10,000,000 tokens
**Comment:** "~$10,000 exposure" ❌ (actually $50)

**Implementation:**
```php
// BEFORE:
const GLOBAL_FREE_CAP = 10_000_000; // 10M tokens = ~$10,000 max exposure ❌

// AFTER:
const GLOBAL_FREE_CAP = 2_000_000_000; // 2B tokens = ~$10,000 max exposure at $5/M ✅

// Or if $10k risk is too high, adjust:
const GLOBAL_FREE_CAP = 500_000_000; // 500M tokens = ~$2,500 max exposure at $5/M
```

---

## Medium Priority Fixes (Within 1 Month)

### Fix 8: Remove Rollover Field (Gap 4)
**Priority:** 🟢 Medium
**File:** REST API response

**Implementation:**
```php
public function get_balance_api() {
    $balance = $this->get_balance();

    $response = array(
        'tokens_allocated'   => $balance['tokens_allocated'],
        'tokens_used'        => $balance['tokens_used'],
        'tokens_remaining'   => $balance['tokens_remaining'],
        'percentage_used'    => $balance['percentage_used'],
        'period_start'       => $balance['period_start'],
        'period_end'         => $balance['period_end'],
        'days_until_reset'   => $balance['days_until_reset'],
        'status'             => $balance['status'],
        'tier'               => $balance['license_tier'],
    );

    // Only include rollover when feature is enabled (NOT YET)
    // if ( $this->is_rollover_enabled() ) {
    //     $response['rollover_available'] = $balance['rollover_available'];
    // }

    return $response;
}
```

---

### Fix 9: Clean Up Obsolete Pricing Comments (Gap 9)
**Priority:** 🟢 Medium
**Files:** All codebase

**Search and Replace:**
```bash
# Find all instances:
grep -r "210 tokens" includes/
grep -r "\$10,000 max exposure" includes/
grep -r "~\$0.0024" includes/

# Replace with:
# 210 tokens → 307 tokens (Phase 0B reality)
# $10,000 max exposure → correct calculation
# ~$0.0024 → ~$0.0015 (Smart Mode at $5/M)
```

---

## Implementation Timeline

| Fix | Priority | Effort | Dependencies | Target |
|-----|----------|--------|--------------|--------|
| **Fix 1: Atomic SQL** | 🔴 Critical | 2 hours | Database schema | Day 1 |
| **Fix 2: BYOK Enforcement** | 🔴 Critical | 3 hours | License system | Day 1 |
| **Fix 3: Stop-on-Cap** | 🔴 Critical | 4 hours | Frontend modal | Day 2 |
| **Fix 4: Underflow** | 🔴 Critical | 3 hours | Audit table | Day 2 |
| **Fix 5: Daily Throttle** | 🟡 High | 1 hour | - | Day 3 |
| **Fix 6: Telemetry UI** | 🟡 High | 4 hours | Telemetry class | Day 3-4 |
| **Fix 7: Global Cap** | 🟡 High | 30 min | - | Day 4 |
| **Fix 8: Rollover API** | 🟢 Medium | 30 min | - | Week 2 |
| **Fix 9: Comments** | 🟢 Medium | 2 hours | - | Week 2 |
| **Total** | - | **20 hours** | - | **2 weeks** |

---

## Testing Strategy

### Unit Tests
- [ ] Atomic SQL concurrency test (10 simultaneous deducts)
- [ ] BYOK enforcement (Enterprise without key fails)
- [ ] Underflow protection (negative difference clamped at 0)
- [ ] Telemetry median calculation accuracy

### Integration Tests
- [ ] Stop-on-cap: 10-image batch with 1,000 tokens → stops at ~3 images
- [ ] Daily throttle: Free user hits 614-token limit after 2 images
- [ ] Global cap: Free tier total exceeds 2B tokens → all Free users blocked
- [ ] REST API: Response matches schema, no rollover field

### Stress Tests
- [ ] 100 concurrent deduction attempts (atomic SQL)
- [ ] 1,000 reconcile operations with random over/under estimates
- [ ] Cap modal display under various balance scenarios

---

## Success Criteria

**Before Production:**
- ✅ All Critical fixes (1-4) tested and verified
- ✅ All High Priority fixes (5-7) implemented
- ✅ Concurrency test passes (100 parallel deducts)
- ✅ Underflow never occurs in audit table
- ✅ BYOK enforcement blocks Enterprise without key
- ✅ Stop-on-cap surfaces modal correctly

**Post-Production (First 7 Days):**
- ✅ Zero underflow alerts in logs
- ✅ Zero negative token balances in database
- ✅ Global free cap not exceeded
- ✅ Daily throttle working as expected
- ✅ Telemetry estimates within 10% of actual usage

---

**Status:** ⏳ Ready for implementation
**Owner:** Engineering Team
**Blocker:** None (all fixes are independent)
**Next Action:** Implement Critical fixes (1-4) immediately
**Test Plan:** See TOKEN-MODEL-VERIFICATION-PLAN.md for complete testing procedures
**GitHub Issue:** Use .github/ISSUE_TEMPLATE/token-verification.md to track progress
**Last Updated:** November 2, 2025
