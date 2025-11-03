# TinyDot Image Optimizer — Token Model Verification Plan (v1.3.0)

**Date:** November 2, 2025
**Status:** Ready for execution
**Purpose:** Verify Phase 0B Smart Mode + Token System compliance before production
**Reference:** TOKEN_BASED_PRICING_STRATEGY.md v3.4, TOKEN-SYSTEM-COMPLIANCE-FIXES.md

---

## 0) Scope Lock

**Baseline:**
- Use pricing doc v3.0 as the baseline
- Do not change token economics or tiers during this test round
- Goal is verification of 0B and safety guards

**Sequence:**
1. Verify Phase 0B baseline (307 tokens, 90.4% reduction)
2. Fix all compliance gaps (Critical + High priority)
3. Optional Phase 0C prompt trim runs after baseline passes

---

## 1) Preconditions

### Environment
- **Staging WP site:** `thedot-optimizer-test.local` (or staging.example.com)
- **PHP:** 8.1+
- **MySQL:** 5.7+ or MariaDB 10.4+
- **WP Cron:** Enabled
- **OpenAI key:** Set for Smart mode
- **Anthropic:** If configured, leave off for this test

### Repo Paths to Confirm
```
✓ includes/class-msh-token-manager.php
✓ includes/database/schema-token-balance.sql
✓ includes/class-msh-ai-ajax-handlers.php
✓ admin/image-optimizer-admin.php
✓ assets/js/image-optimizer-admin.js
✓ includes/class-msh-telemetry.php (new)
✓ includes/database/schema-token-audit.sql (new)
```

### Golden Images
Put 30 mixed images in `wp-content/uploads/msh-test/`:
- **10 people** (portraits, team photos, testimonials)
- **10 objects** (products, equipment, close-ups)
- **10 landscapes** (facilities, outdoor scenes, stock)
- **5 very small files** (<100KB)
- **5 very large files** (>5MB)

---

## 2) DB Setup and Guardrail Fixes

### 2.1 Create Tables

**Run the schema SQL:**
```bash
cd /Users/anastasiavolkova/Local\ Sites/thedot-optimizer-test/app/public

# Source token balance table
mysql -u root -proot local < wp-content/plugins/msh-image-optimizer/includes/database/schema-token-balance.sql

# Source audit table (new)
mysql -u root -proot local < wp-content/plugins/msh-image-optimizer/includes/database/schema-token-audit.sql
```

**Verify indexes exist:**
```sql
USE local;

SHOW TABLES LIKE '%msh_ai_token%';
-- Expect: wp_msh_ai_token_balance, wp_msh_ai_token_audit

SHOW INDEX FROM wp_msh_ai_token_balance;
-- Expect: PRIMARY, idx_site, idx_status, idx_period

SHOW INDEX FROM wp_msh_ai_token_audit;
-- Expect: PRIMARY, idx_site, idx_operation, idx_underflow, idx_created
```

---

### 2.2 Atomic Deduct SQL Patch

**Issue:** Generated column `tokens_remaining` in WHERE clause is unsafe for concurrency.

**Fix in `includes/class-msh-token-manager.php`:**
```php
public function deduct( $tokens_estimated, $operation_id ) {
    global $wpdb;

    // Atomic concurrency-safe update
    $affected = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->prefix}msh_ai_token_balance
         SET tokens_used = tokens_used + %d
         WHERE site_id = %s
         AND status = 'active'
         AND period_end > NOW()
         AND (tokens_used + %d) <= tokens_allocated",
        $tokens_estimated,
        $this->get_site_id(),
        $tokens_estimated
    ) );

    if ( $affected !== 1 ) {
        throw new Exception( 'Insufficient tokens or concurrent operation' );
    }

    $this->log_transaction( $operation_id, $tokens_estimated, 'deducted' );
    return true;
}
```

**Test:**
```bash
# Concurrency test (10 simultaneous deducts)
seq 1 10 | xargs -n1 -P10 bash -c 'wp msh test-token-deduct --tokens=100 --site=SITE_PRO' 2>&1 | grep -c "Insufficient"
# Should show N failures where N × 100 > remaining balance
```

---

### 2.3 Reconcile Clamp

**Issue:** If actual < estimated, `tokens_used` can go negative.

**Fix in `includes/class-msh-token-manager.php`:**
```php
public function reconcile( $operation_id, $tokens_actual ) {
    global $wpdb;

    $transaction = $this->get_transaction( $operation_id );
    $tokens_estimated = $transaction['tokens'];
    $difference = $tokens_actual - $tokens_estimated;

    if ( $difference != 0 ) {
        $balance = $this->get_balance();
        $current_used = $balance['tokens_used'];

        // Calculate new value with floor at 0
        $new_used = max( 0, $current_used + $difference );

        // Update with explicit value
        $wpdb->update(
            $wpdb->prefix . 'msh_ai_token_balance',
            array( 'tokens_used' => $new_used ),
            array( 'site_id' => $this->get_site_id() ),
            array( '%d' ),
            array( '%s' )
        );

        // Log to audit table
        $this->log_audit( array(
            'operation_id'       => $operation_id,
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
        }
    }

    return true;
}
```

**Test:**
```sql
-- Simulate underflow
UPDATE wp_msh_ai_token_balance SET tokens_used = 10 WHERE site_id = 'SITE_TEST';
-- Deduct 100 (estimated)
-- Reconcile with 5 (actual)
-- Difference: -95
-- Expected: max(0, 10 + 100 - 95) = 15 ✓

-- Check audit table
SELECT * FROM wp_msh_ai_token_audit WHERE underflow = 1;
-- Should have no rows (or show prevented underflows)
```

---

### 2.4 Enterprise BYOK Hard Check

**Issue:** Enterprise can consume unlimited bundled AI tokens.

**Fix in `includes/class-msh-token-manager.php`:**
```php
public function can_use_bundled_ai( $license_tier ) {
    if ( $license_tier === 'enterprise' ) {
        $byok_key = get_option( 'msh_byok_openai_key' );

        if ( empty( $byok_key ) ) {
            return new WP_Error(
                'byok_required',
                'Enterprise tier requires your own OpenAI API key. Please add it in Settings > AI Configuration.'
            );
        }

        return false; // Use BYOK, not bundled
    }

    return true;
}
```

**Gate AI calls in `includes/class-msh-openai-connector.php`:**
```php
public function generate_metadata( $attachment_id, $context ) {
    $token_manager = MSH_Token_Manager::get_instance();
    $license_tier = $this->get_license_tier();

    $can_use_bundled = $token_manager->can_use_bundled_ai( $license_tier );
    if ( is_wp_error( $can_use_bundled ) ) {
        return $can_use_bundled;
    }

    // Continue...
}
```

**Test:**
```bash
# Set Enterprise without BYOK
wp option update msh_license_tier enterprise
wp option delete msh_byok_openai_key

# Attempt AI call
wp msh analyze --count=1
# Expected: Error "Enterprise tier requires your own OpenAI API key..."

# Add BYOK
wp option update msh_byok_openai_key "sk-..."

# Retry
wp msh analyze --count=1
# Expected: Success, uses BYOK key
```

---

### 2.5 Global Cap Comment and Value

**Issue:** Comment says "$10,000" but 10M tokens = $50.

**Fix in `includes/class-msh-token-manager.php`:**
```php
// BEFORE:
const GLOBAL_FREE_CAP = 10_000_000; // 10M tokens = ~$10,000 max exposure ❌

// AFTER:
const GLOBAL_FREE_CAP = 2_000_000_000; // 2B tokens = ~$10,000 max exposure at $5/M ✅
```

**Or adjust to risk tolerance:**
```php
const GLOBAL_FREE_CAP = 500_000_000; // 500M tokens = ~$2,500 max exposure at $5/M
```

---

## 3) Seed Data for Tiers

**Run once via SQL or WP shell:**

```sql
USE local;

-- Free trial user
INSERT INTO wp_msh_ai_token_balance
(site_id, license_tier, tokens_allocated, tokens_used, period_start, period_end, status, created_at)
VALUES ('SITE_FREE', 'free', 1000, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', NOW());

-- Pro user
INSERT INTO wp_msh_ai_token_balance
(site_id, license_tier, tokens_allocated, tokens_used, period_start, period_end, status, created_at)
VALUES ('SITE_PRO', 'pro', 50000, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', NOW());

-- Business user
INSERT INTO wp_msh_ai_token_balance
(site_id, license_tier, tokens_allocated, tokens_used, period_start, period_end, status, created_at)
VALUES ('SITE_BIZ', 'business', 500000, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', NOW());

-- Enterprise user, no BYOK
INSERT INTO wp_msh_ai_token_balance
(site_id, license_tier, tokens_allocated, tokens_used, period_start, period_end, status, created_at)
VALUES ('SITE_ENT_NO_BYOK', 'enterprise', 10000000, 0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', NOW());
```

**Verify:**
```sql
SELECT site_id, license_tier, tokens_allocated, tokens_remaining, status
FROM wp_msh_ai_token_balance;
```

---

## 4) REST and Cron Sanity

### 4.1 Usage Endpoint

**Test:**
```bash
curl -s http://thedot-optimizer-test.local/wp-json/msh/v1/ai-usage?site_id=SITE_PRO | jq .
```

**Expected Response:**
```json
{
  "tokens_allocated": 50000,
  "tokens_used": 0,
  "tokens_remaining": 50000,
  "percentage_used": 0.0,
  "period_start": "2025-11-02 00:00:00",
  "period_end": "2025-12-02 00:00:00",
  "days_until_reset": 30,
  "status": "active",
  "tier": "pro",
  "estimates": {
    "smart_mode": {
      "tokens_per_image": 307,
      "images_remaining": 162
    },
    "quick_mode": {
      "tokens_per_image": 85,
      "images_remaining": 588
    },
    "high_detail": {
      "tokens_per_image": 400,
      "images_remaining": 125
    }
  }
}
```

**Note:** NO `rollover_available` field until feature is enabled.

---

### 4.2 Monthly Reset

**Test:**
```bash
# List cron events
wp cron event list | grep msh

# Trigger monthly reset manually
wp cron event run msh_reset_tokens_monthly

# Verify reset
SELECT site_id, tokens_used, period_start, period_end, status
FROM wp_msh_ai_token_balance;
```

**Expected:**
- `tokens_used` reset to 0
- `period_start` and `period_end` updated
- Free trials older than 30 days → `status='expired'`

---

## 5) Functional Tests

### 5.1 Free Daily Throttle

**Decision:** Set throttle to match Smart Mode reality.

**Option A (Recommended):**
```php
const FREE_DAILY_THROTTLE = 614; // 2 Smart images per day (307 × 2)
```

**Option B:**
```php
const FREE_DAILY_THROTTLE = 200; // ~1 Smart image every 1.5 days
// Update UI: "Daily limit: 200 tokens ≈ 1 image every 1.5 days"
```

**Test:**
1. As `SITE_FREE`, queue Smart optimize for 3 images
2. Expected:
   - Image 1: ✅ Processes (307 tokens used)
   - Image 2: ✅ Processes if throttle=614, ❌ blocks if throttle=200
   - Image 3: ❌ Blocks with "Daily limit reached" message
3. Verify admin notice text is correct

**Command:**
```bash
wp msh optimize --site=SITE_FREE --mode=smart --count=3
```

---

### 5.2 Free Trial Expiry

**Test:**
```sql
-- Set trial to expired (31 days ago)
UPDATE wp_msh_ai_token_balance
SET created_at = DATE_SUB(NOW(), INTERVAL 31 DAY)
WHERE site_id = 'SITE_FREE';

-- Run cron or manual check
wp cron event run msh_reset_tokens_monthly
```

**Verify:**
```bash
# Check status
curl -s http://thedot-optimizer-test.local/wp-json/msh/v1/ai-usage?site_id=SITE_FREE | jq .status
# Expected: "expired"

# Attempt AI optimization
wp msh optimize --site=SITE_FREE --mode=smart --count=1
# Expected: Error "Free trial expired. Upgrade to Pro..."

# Verify contextual generator still works
wp msh optimize --site=SITE_FREE --mode=contextual --count=1
# Expected: Success (no AI, no tokens)
```

---

### 5.3 Pro and Business

**Test Pro:**
```bash
wp msh optimize --site=SITE_PRO --mode=smart --count=10
# Expected tokens used: ~3,070 (10 × 307)
# Remaining: 50,000 - 3,070 = 46,930

# Verify UI
curl -s http://thedot-optimizer-test.local/wp-json/msh/v1/ai-usage?site_id=SITE_PRO | jq .tokens_remaining
# Expected: 46930 (±50)
```

**Test Business:**
```bash
wp msh optimize --site=SITE_BIZ --mode=smart --count=20
# Expected tokens used: ~6,140 (20 × 307)
# Remaining: 500,000 - 6,140 = 493,860 (small dent)
```

---

### 5.4 Enterprise Without BYOK

**Test:**
```bash
# Ensure no BYOK key
wp option delete msh_byok_openai_key
wp option update msh_license_tier enterprise

# Attempt Smart optimize
wp msh optimize --site=SITE_ENT_NO_BYOK --mode=smart --count=1

# Expected output:
# Error: Enterprise tier requires your own OpenAI API key. Please add it in Settings > AI Configuration.

# Verify no token deduction
SELECT tokens_used FROM wp_msh_ai_token_balance WHERE site_id='SITE_ENT_NO_BYOK';
# Expected: 0 (unchanged)
```

---

### 5.5 Concurrency Guard

**Test atomic SQL with parallel requests:**
```bash
# Set Pro balance to 5,000 tokens
UPDATE wp_msh_ai_token_balance SET tokens_used = 45000 WHERE site_id='SITE_PRO';
# Remaining: 5,000 tokens

# Fire 10 parallel requests (each needs 307 tokens)
seq 1 10 | xargs -n1 -P10 bash -c 'curl -s -X POST http://thedot-optimizer-test.local/wp-json/msh/v1/optimize -d "site_id=SITE_PRO&mode=smart&image_id=1686" 2>&1' > /tmp/concurrency_test.log

# Count successes
grep -c "success" /tmp/concurrency_test.log
# Expected: ~16 (5000 ÷ 307 = 16.28)

# Count failures
grep -c "Insufficient tokens" /tmp/concurrency_test.log
# Expected: ~4 or more

# Verify no negative balance
SELECT tokens_used, tokens_remaining FROM wp_msh_ai_token_balance WHERE site_id='SITE_PRO';
# Expected: tokens_remaining >= 0
```

---

### 5.6 Insufficient Tokens Mid-Batch

**Test:**
```bash
# Set Pro balance to 1,200 tokens
UPDATE wp_msh_ai_token_balance SET tokens_used = 48800 WHERE site_id='SITE_PRO';
# Remaining: 1,200 tokens

# Select 25 images (needs ~7,675 tokens)
wp msh optimize --site=SITE_PRO --mode=smart --count=25

# Expected behavior:
# - Processes first ~3 images (3 × 307 = 921 tokens)
# - Stops after image 3
# - Shows modal:
#   • "Token limit reached after 3 images"
#   • "22 images remaining"
#   • Options:
#     - Upgrade to Pro
#     - Buy 6,754 tokens for $7
#     - Continue with non-AI mode
```

**Verify modal data:**
```bash
wp transient get msh_cap_modal_1  # User ID 1
# Should return modal JSON
```

---

### 5.7 Reconcile

**Test:**
```bash
# Optimize 10 images with telemetry
wp msh optimize --site=SITE_PRO --mode=smart --count=10

# Check audit table
SELECT
    operation_id,
    tokens_estimated,
    tokens_actual,
    tokens_difference,
    underflow
FROM wp_msh_ai_token_audit
ORDER BY created_at DESC
LIMIT 10;

# Expected:
# - All rows have tokens_actual populated
# - tokens_difference = tokens_actual - tokens_estimated
# - underflow = 0 (or 1 if prevented)
# - No negative tokens_used in balance table
```

---

## 6) UI Verification

**Open plugin Settings in browser:**
`http://thedot-optimizer-test.local/wp-admin/admin.php?page=msh-image-optimizer`

**Check:**
1. **Balance widget** shows images remaining first, tokens second
   - Example: "~122 images remaining (37,550 tokens)"
2. **20% warning** at 10,000 of 50,000
   - Example: "⚠️ You've used 80% of your monthly AI tokens (40,000 / 50,000)"
3. **Zero tokens banner** stops AI features, allows contextual mode
   - Example: "🚫 AI Optimization Paused - 0 tokens remaining. Upgrade or use non-AI mode."
4. **Batch estimator** equals `Token_Manager::estimate_tokens()`
   - Select 10 images: "Estimated: ~3,070 tokens"
5. **No em dashes** in copy (use hyphens or bullets)

**Screenshots Required:**
1. `ui-normal-balance.png` - Normal balance widget
2. `ui-20-percent-warning.png` - 20% remaining warning
3. `ui-zero-tokens.png` - Zero tokens banner
4. `ui-cap-modal.png` - Insufficient tokens modal
5. `ui-batch-estimator.png` - Batch estimator panel

---

## 7) Telemetry Checks

**Verify these metrics are recorded:**

**Via SQL:**
```sql
-- Total tokens used
SELECT SUM(tokens_used) as total_tokens
FROM wp_msh_ai_token_balance;

-- Average tokens per image by mode
SELECT
    mode,
    AVG(token_count) as avg_tokens,
    COUNT(*) as images
FROM wp_msh_ai_token_usage
GROUP BY mode;

-- Free pool remaining (global cap check)
SELECT
    SUM(tokens_allocated - tokens_used) as free_pool_remaining
FROM wp_msh_ai_token_balance
WHERE license_tier = 'free';

-- Conversion funnel (if tracking)
SELECT
    event_name,
    COUNT(*) as occurrences
FROM wp_msh_telemetry_events
WHERE event_name IN ('trial_started', 'token_exhausted', 'upgraded_to_pro')
GROUP BY event_name;
```

**Via Dashboard (if available):**
- `tokens_used_total` chart
- `avg_tokens_per_image` by mode
- `free_pool_remaining` gauge
- `conversion_rate` funnel

**Supabase (if connected):**
```bash
# Test ingestion
wp msh telemetry-sync

# Check Supabase dashboard for:
# - Latest sync timestamp
# - Row counts match local DB
# - Charts render correctly
```

---

## 8) Performance Sample for Phase 0B

**Run Smart Mode on 30 mixed images:**

```bash
# Use Smart Mode test harness
wp msh smart-mode-test --count=30 > /tmp/phase-0b-results.txt
```

**Collect Metrics:**

1. **Average Tokens Per Image**
   ```bash
   grep "Avg tokens:" /tmp/phase-0b-results.txt
   # Expected: ~307 tokens (±20)
   ```

2. **Average Latency**
   ```bash
   grep "Avg duration:" /tmp/phase-0b-results.txt
   # Expected: 4.9-5.5s per image
   ```

3. **Quality Spot Check**
   - Manually review 10 random images from test output
   - Check titles, alt text, subjects, attributes
   - Rate each: ✅ Good (matches visible content) or ❌ Poor (generic/wrong)
   - Expected: ≥93% Good (9-10 out of 10)

**Pass Criteria:**
- ✅ Avg tokens: 280-330 (within 10% of 307)
- ✅ Avg latency: 4.5-6.0s (acceptable for Smart Mode)
- ✅ Quality: ≥93% acceptance (9/10 images rated Good)

**If all pass:** ✅ Mark Phase 0B as **Production Ready**

---

## 9) Optional Phase 0C Prompt Trim A/B

**Only run after Phase 0B baseline passes.**

### 9.1 Prompt Patch

**Optimize prompts further:**

**System Prompt (Phase 0C):**
```php
$system_message = "Analyze image. Output JSON schema v4. No text outside JSON.";
// Target: ~15 tokens
```

**User Prompt (Phase 0C):**
```php
$user_message = "{$ctx_id}|{$context_type}|{$brand_visible}|{$business_name}

{fn,t,a,c,d,k,s,attr,conf,iss}
t≤60,a 8-140,visible only";
// Target: ~25 tokens
```

**API Config:**
```php
'max_tokens' => 120,       // Hard cap output
'temperature' => 0.1,       // Near-deterministic
'stop' => [ '}', "\n\n" ],  // Stop at JSON end
```

**Target Output:**
```json
{"fn":"wind-turbines-sunset.webp","t":"Sunset over wind turbines","a":"Wind farm at dusk, orange sky","c":"Renewable energy","k":["wind","turbines","sunset","energy"],"conf":88}
```

---

### 9.2 Test Phase 0C

**Run same 30 images with 0C prompts:**
```bash
wp msh smart-mode-test --count=30 --mode=0c > /tmp/phase-0c-results.txt
```

**Compare A vs B:**

| Metric | Phase 0B (Baseline) | Phase 0C (Optimized) | Target | Pass? |
|--------|---------------------|----------------------|--------|-------|
| Avg Tokens | 307 | ? | ≤230 | ? |
| Avg Latency | 4.9s | ? | ≤5.4s (10% margin) | ? |
| Quality | 95% | ? | ≥93% | ? |

**Promotion Decision:**
- ✅ **Promote 0C** if tokens ≤230 AND quality ≥93% AND latency acceptable
- ⚠️ **Stick with 0B** if any target fails

---

## 10) Pass or Fail Gates

**Mark the round ✅ PASSED if ALL items below are true:**

### Critical Fixes
- [ ] **Free daily throttle** behaves per chosen setting and copy is aligned
- [ ] **Free trial expiry** blocks AI after 30 days but leaves contextual mode
- [ ] **Pro and Business** deduct correctly with accurate UI
- [ ] **Enterprise without BYOK** is blocked with clear message
- [ ] **Concurrency test** shows atomic deduct works, no negative or over-spend
- [ ] **Insufficient mid-batch** finishes current image, cancels rest, shows modal with options
- [ ] **Reconcile** never underflows, audit rows exist

### Integration
- [ ] **REST endpoint** returns fields as documented, no premature `rollover_available`
- [ ] **Telemetry** shows nonzero data for all four primary metrics
- [ ] **UI** displays balance, warnings, and modals correctly

### Performance
- [ ] **Phase 0B** averages ~307 tokens per image
- [ ] **Latency** near 4.9-5.5s per image
- [ ] **Quality** ≥93% acceptance on spot check (9/10 images)

---

**If ANY item fails:**

Log an issue with:
1. **Repro steps** (exact commands to reproduce)
2. **Expected vs Actual** (what should happen vs what did happen)
3. **Environment** (PHP version, MySQL version, WP version)
4. **Logs** (error_log excerpts, SQL query logs)
5. **Screenshots** (for UI issues)
6. **Smallest failing dataset** (minimal test case)

---

## 11) Deliverables

**After completing all tests, generate:**

### 1. Phase 0B Verification Report (1 page)
```markdown
# Phase 0B Verification Report

**Date:** 2025-11-02
**Tester:** [Name]
**Environment:** thedot-optimizer-test.local

## Summary
- Images tested: 30
- Avg tokens: 307 ✅
- Avg latency: 5.1s ✅
- Quality: 95% (28/30 Good) ✅
- Cost: $0.15 for 30 images

## Token Breakdown
- System prompt: ~20 tokens
- User prompt: ~40 tokens
- Vision (640px, low): ~85 tokens
- Response (schema v4): ~150 tokens
- Total: 307 tokens

## Verdict
✅ Phase 0B is Production Ready
```

### 2. Token Manager Functional Log
```markdown
# Token Manager Functional Log

## Deduct Tests
- ✅ Single deduct: Tokens decreased correctly
- ✅ Concurrent deducts: No over-spend, atomic SQL worked
- ✅ Insufficient tokens: Threw exception as expected

## Reconcile Tests
- ✅ Over-estimate: Refunded difference
- ✅ Under-estimate: Charged difference
- ✅ Underflow prevention: Clamped at 0, logged audit row

## Audit Entries
- 30 operations logged
- 0 underflows detected
- 100% reconcile accuracy
```

### 3. UI Screenshots
- `ui-normal-balance.png`
- `ui-20-percent-warning.png`
- `ui-zero-tokens.png`
- `ui-cap-modal.png`
- `ui-batch-estimator.png`

### 4. Telemetry Snapshot
```sql
-- Capture telemetry state
SELECT
    'tokens_used_total' as metric,
    SUM(tokens_used) as value
FROM wp_msh_ai_token_balance
UNION ALL
SELECT
    CONCAT('avg_tokens_', mode),
    AVG(token_count)
FROM wp_msh_ai_token_usage
GROUP BY mode;
```

### 5. Phase 0C A/B Comparison (If Run)
```markdown
# Phase 0C A/B Comparison

| Metric | 0B Baseline | 0C Optimized | Improvement |
|--------|-------------|--------------|-------------|
| Tokens | 307 | 225 | -27% |
| Latency | 5.1s | 5.3s | +4% |
| Quality | 95% | 94% | -1% |

**Decision:** ✅ Promote 0C (meets targets)
```

---

## 12) Quick Commands Reference

### WP-CLI

```bash
# Cron
wp cron event list | grep msh
wp cron event run msh_reset_tokens_monthly

# Token balance
wp option get msh_tokens_remaining
wp option get msh_license_tier

# Test commands
wp msh smart-mode-test --count=10
wp msh optimize --site=SITE_PRO --mode=smart --count=5
wp msh test-token-deduct --tokens=100 --site=SITE_PRO

# Telemetry
wp msh telemetry-sync
wp msh telemetry-report
```

---

### REST API

```bash
# Usage endpoint
curl -s http://thedot-optimizer-test.local/wp-json/msh/v1/ai-usage?site_id=SITE_PRO | jq .

# Pretty print
curl -s http://thedot-optimizer-test.local/wp-json/msh/v1/ai-usage?site_id=SITE_PRO | jq '{
  tokens_remaining,
  percentage_used,
  status,
  tier
}'
```

---

### SQL Checks

```sql
USE local;

-- View all tiers
SELECT
    site_id,
    license_tier,
    tokens_allocated,
    tokens_used,
    tokens_allocated - tokens_used as tokens_remaining,
    status
FROM wp_msh_ai_token_balance;

-- Check specific site
SELECT * FROM wp_msh_ai_token_balance WHERE site_id IN ('SITE_FREE','SITE_PRO','SITE_BIZ','SITE_ENT_NO_BYOK');

-- Audit trail
SELECT
    operation_id,
    tokens_estimated,
    tokens_actual,
    tokens_difference,
    underflow,
    created_at
FROM wp_msh_ai_token_audit
ORDER BY created_at DESC
LIMIT 20;

-- Telemetry
SELECT
    mode,
    AVG(token_count) as avg_tokens,
    COUNT(*) as images
FROM wp_msh_ai_token_usage
GROUP BY mode;
```

---

### Concurrency Stress Test

```bash
# 10 parallel requests
seq 1 10 | xargs -n1 -P10 bash -c 'curl -s -X POST http://thedot-optimizer-test.local/wp-json/msh/v1/optimize -d "site_id=SITE_PRO&mode=smart&image_id=$RANDOM" >/dev/null'

# Check results
SELECT tokens_used, tokens_remaining FROM wp_msh_ai_token_balance WHERE site_id='SITE_PRO';
```

---

**Test Plan Status:** ✅ Ready for execution
**Estimated Duration:** 6-8 hours (full suite)
**Prerequisites:** All compliance fixes (Gaps 1-9) implemented
**Next Action:** Execute Section 1-2 (Preconditions + DB Setup)
**Owner:** Engineering Team
**Last Updated:** November 2, 2025
