---
name: Token Model Verification
about: Track Phase 0B Smart Mode + Token System verification testing
title: 'Token Model Verification v1.3.0'
labels: testing, token-system, phase-0b
assignees: ''
---

# Token Model Verification (v1.3.0)

**Reference:** TOKEN-MODEL-VERIFICATION-PLAN.md
**Goal:** Verify Phase 0B Smart Mode (307 tokens, 90.4% reduction) + Token System compliance
**Environment:** thedot-optimizer-test.local (or staging)

---

## 1. Preconditions

- [ ] Staging WP site ready (PHP 8.1+, MySQL 5.7+, WP Cron enabled)
- [ ] OpenAI key configured for Smart mode
- [ ] Repo paths confirmed (Token Manager, Admin, AJAX handlers, etc.)
- [ ] 30 golden images prepared (10 people, 10 objects, 10 landscapes)

---

## 2. DB Setup & Guardrail Fixes

### Critical Fixes
- [ ] **2.1** Token balance + audit tables created, indexes verified
- [ ] **2.2** Atomic deduct SQL patched (no generated column in WHERE)
- [ ] **2.3** Reconcile clamp implemented (prevents underflow)
- [ ] **2.4** Enterprise BYOK hard check added (blocks without key)
- [ ] **2.5** Global cap value fixed (2B tokens = $10k at $5/M)

**Verification:**
```bash
# Check tables
mysql -u root -proot local -e "SHOW TABLES LIKE '%msh_ai_token%';"

# Verify atomic SQL
wp msh test-token-deduct --concurrent=10 --site=SITE_PRO
```

---

## 3. Seed Data

- [ ] **3.1** Free trial user (`SITE_FREE`, 1,000 tokens)
- [ ] **3.2** Pro user (`SITE_PRO`, 50,000 tokens)
- [ ] **3.3** Business user (`SITE_BIZ`, 500,000 tokens)
- [ ] **3.4** Enterprise user without BYOK (`SITE_ENT_NO_BYOK`)

**Verification:**
```sql
SELECT site_id, license_tier, tokens_allocated, status FROM wp_msh_ai_token_balance;
```

---

## 4. REST & Cron Sanity

- [ ] **4.1** Usage endpoint returns correct JSON (no `rollover_available` field)
- [ ] **4.2** Monthly reset cron works (resets `tokens_used`, expires old trials)

**Commands:**
```bash
curl -s http://thedot-optimizer-test.local/wp-json/msh/v1/ai-usage?site_id=SITE_PRO | jq .
wp cron event run msh_reset_tokens_monthly
```

---

## 5. Functional Tests

### 5.1 Free Daily Throttle
- [ ] Set throttle to 614 tokens (2 Smart images) OR 200 tokens (1 image)
- [ ] Test 3-image batch, verify correct blocking behavior
- [ ] Admin notice text matches chosen throttle

**Command:** `wp msh optimize --site=SITE_FREE --mode=smart --count=3`

---

### 5.2 Free Trial Expiry
- [ ] Set trial to 31 days old, run cron
- [ ] Verify `status=expired`, AI blocked, contextual mode still works

**Command:** `wp msh optimize --site=SITE_FREE --mode=contextual --count=1`

---

### 5.3 Pro and Business
- [ ] Pro: Optimize 10 images, verify ~3,070 tokens used
- [ ] Business: Optimize 20 images, verify small dent (~6,140 tokens)

**Commands:**
```bash
wp msh optimize --site=SITE_PRO --mode=smart --count=10
wp msh optimize --site=SITE_BIZ --mode=smart --count=20
```

---

### 5.4 Enterprise Without BYOK
- [ ] Attempt AI call without BYOK key
- [ ] Verify error: "Enterprise requires your own API key..."
- [ ] Verify no token deduction

**Command:** `wp msh optimize --site=SITE_ENT_NO_BYOK --mode=smart --count=1`

---

### 5.5 Concurrency Guard
- [ ] Fire 10 parallel requests with limited balance (5,000 tokens)
- [ ] Verify no negative balance, ~16 successes, ~4 failures
- [ ] Check atomic UPDATE affected rows

**Command:**
```bash
seq 1 10 | xargs -n1 -P10 bash -c 'curl -s -X POST http://thedot-optimizer-test.local/wp-json/msh/v1/optimize -d "site_id=SITE_PRO&mode=smart&image_id=1686"'
```

---

### 5.6 Insufficient Tokens Mid-Batch
- [ ] Set balance to 1,200 tokens, batch 25 images
- [ ] Verify stops after ~3 images
- [ ] Modal shows: "Optimize N now / Buy pack / Upgrade / Use non-AI"

**Command:** `wp msh optimize --site=SITE_PRO --mode=smart --count=25`

---

### 5.7 Reconcile
- [ ] Optimize 10 images, check audit table
- [ ] Verify `tokens_actual` populated, `underflow=0`, no negative balances

**SQL:**
```sql
SELECT * FROM wp_msh_ai_token_audit ORDER BY created_at DESC LIMIT 10;
```

---

## 6. UI Verification

- [ ] **6.1** Balance widget shows images first, tokens second
- [ ] **6.2** 20% warning at 10,000 of 50,000
- [ ] **6.3** Zero tokens banner stops AI, allows contextual
- [ ] **6.4** Batch estimator matches `Token_Manager::estimate_tokens()`
- [ ] **6.5** No em dashes in copy

**Screenshots Required:**
- [ ] `ui-normal-balance.png`
- [ ] `ui-20-percent-warning.png`
- [ ] `ui-zero-tokens.png`
- [ ] `ui-cap-modal.png`
- [ ] `ui-batch-estimator.png`

---

## 7. Telemetry Checks

- [ ] **7.1** `tokens_used_total` metric recorded
- [ ] **7.2** `avg_tokens_per_image` by mode tracked
- [ ] **7.3** `free_pool_remaining` for global cap
- [ ] **7.4** `conversion_rate` funnel events (if applicable)
- [ ] **7.5** Supabase ingestion works (if connected)

**SQL:**
```sql
SELECT mode, AVG(token_count) as avg_tokens, COUNT(*) as images
FROM wp_msh_ai_token_usage GROUP BY mode;
```

---

## 8. Performance Sample (Phase 0B)

- [ ] **8.1** Run Smart Mode on 30 mixed images
- [ ] **8.2** Avg tokens: 280-330 (target: 307)
- [ ] **8.3** Avg latency: 4.5-6.0s (target: ~5s)
- [ ] **8.4** Quality: ≥93% on spot check (9/10 images rated Good)

**Command:** `wp msh smart-mode-test --count=30 > /tmp/phase-0b-results.txt`

**Results:**
- Avg tokens: ___ (expected: 307)
- Avg latency: ___ (expected: ~5s)
- Quality: ___% (expected: ≥93%)

**Decision:**
- [ ] ✅ Phase 0B is Production Ready
- [ ] ⚠️ Phase 0B needs adjustments (explain below)

---

## 9. Optional Phase 0C (If 0B Passes)

- [ ] **9.1** Implement 0C prompt patches (`max_tokens=120`, tighter prompts)
- [ ] **9.2** Run same 30 images with 0C
- [ ] **9.3** Compare: Tokens ≤230, Quality ≥93%, Latency ≤5.4s

**Results:**
- Avg tokens: ___ (target: ≤230)
- Avg latency: ___ (target: ≤5.4s)
- Quality: ___% (target: ≥93%)

**Decision:**
- [ ] ✅ Promote 0C (meets targets)
- [ ] ⚠️ Stick with 0B (0C failed targets)

---

## 10. Pass/Fail Gates

**Mark ✅ PASSED if ALL items below are true:**

### Critical
- [ ] Free daily throttle behaves correctly, copy aligned
- [ ] Free trial expiry blocks AI, contextual still works
- [ ] Pro/Business deduct correctly
- [ ] Enterprise without BYOK blocked
- [ ] Concurrency test: atomic SQL works, no over-spend
- [ ] Mid-batch cap: stops cleanly, shows modal
- [ ] Reconcile never underflows

### Integration
- [ ] REST endpoint correct (no premature rollover field)
- [ ] Telemetry data populated
- [ ] UI displays correctly

### Performance
- [ ] Phase 0B: ~307 tokens/image
- [ ] Latency: 4.9-5.5s
- [ ] Quality: ≥93%

---

## 11. Deliverables

**Attach to this issue:**

- [ ] Phase 0B Verification Report (1 page summary)
- [ ] Token Manager Functional Log (deduct/reconcile/audit notes)
- [ ] UI screenshots (5 images)
- [ ] Telemetry snapshot (SQL export or screenshot)
- [ ] Phase 0C A/B comparison (if run)

---

## 12. Failures & Issues

**If any test fails, document:**

1. **Test ID:** (e.g., 5.5 Concurrency Guard)
2. **Expected:** (what should happen)
3. **Actual:** (what did happen)
4. **Logs:** (error_log excerpts)
5. **Repro:** (exact commands to reproduce)
6. **Environment:** (PHP, MySQL, WP versions)

**Failure Log:**

```
[Add failures here as they occur]
```

---

## Final Verdict

- [ ] ✅ **PASSED** - All tests passed, Phase 0B is Production Ready
- [ ] ⚠️ **NEEDS WORK** - Some tests failed, issues logged above
- [ ] 🚫 **BLOCKED** - Critical failure, cannot proceed

**Notes:**

```
[Add summary notes here]
```

---

**Test Duration:** ___ hours
**Tester:** ___
**Date Completed:** ___
**Next Steps:** ___
