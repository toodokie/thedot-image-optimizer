# Production Runbook - Phase 0B Smart Mode

**Last Updated:** November 2, 2025
**Version:** 1.3.0-0B
**Status:** Production Ready

---

## Monitoring Dashboard

### Key Performance Indicators (KPIs)

| Metric | Target | Alert Threshold | Critical Threshold |
|--------|--------|-----------------|-------------------|
| Avg tokens/image | 287-327 | <280 or >330 | <260 or >350 |
| P95 latency | <6.0s | >6.5s | >8.0s |
| Error rate | <1% | >2% | >5% |
| Quality acceptance | ≥93% | <90% | <85% |
| Underflow events | 0 | >0 | >5 |

---

## Monitoring Queries

### 1. Token Usage (Daily)

```sql
-- Average tokens per image
SELECT
    DATE(created_at) as date,
    COUNT(*) as n_images,
    AVG(token_count) as avg_tokens,
    MIN(token_count) as min_tokens,
    MAX(token_count) as max_tokens,
    STDDEV(token_count) as stddev_tokens
FROM wp_msh_ai_token_usage
WHERE created_at >= NOW() - INTERVAL 7 DAY
AND mode = 'smart'
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

**Expected:**
- avg_tokens: 287-327
- stddev_tokens: <30

**Action if out of range:**
- <280: May indicate prompt truncation, investigate
- >330: Check for prompt bloat, consider Phase 0C

### 2. Latency (P95)

```sql
-- Latency percentiles
SELECT
    AVG(duration_ms)/1000 as avg_latency_s,
    MIN(duration_ms)/1000 as min_latency_s,
    MAX(duration_ms)/1000 as max_latency_s
FROM wp_msh_ai_token_usage
WHERE created_at >= NOW() - INTERVAL 7 DAY
AND mode = 'smart';
```

**Expected:**
- avg_latency_s: 4.5-6.0
- max_latency_s: <8.0

**Action if P95 >6.5s:**
- Check OpenAI API status
- Review image sizes (large images slow processing)
- Consider caching or queue optimization

### 3. Error Rate

```sql
-- Error count and rate
SELECT
    COUNT(*) as total_calls,
    SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) as errors,
    (SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as error_rate_pct
FROM wp_msh_ai_token_usage
WHERE created_at >= NOW() - INTERVAL 7 DAY;
```

**Expected:**
- error_rate_pct: <1.0%

**Action if >2%:**
- Check error logs: `wp db query "SELECT * FROM wp_msh_ai_token_usage WHERE status='error' LIMIT 20"`
- Verify OpenAI API key is valid
- Check for rate limiting

### 4. Underflow Events

```sql
-- Underflow detection
SELECT COUNT(*) as underflow_count
FROM wp_msh_ai_token_audit
WHERE underflow = 1
AND created_at >= NOW() - INTERVAL 7 DAY;
```

**Expected:**
- underflow_count: 0

**Action if >0:**
- **CRITICAL** - Review audit logs immediately
- Check for reconciliation bugs
- Verify Token Manager logic

### 5. Token Balance Health

```sql
-- Balance health check
SELECT
    site_id,
    license_tier,
    tokens_allocated,
    tokens_used,
    tokens_remaining,
    ROUND((tokens_used * 100.0 / tokens_allocated), 1) as usage_pct,
    period_end,
    DATEDIFF(period_end, NOW()) as days_remaining
FROM wp_msh_ai_token_balance
WHERE status = 'active'
ORDER BY usage_pct DESC;
```

**Look for:**
- Sites >80% usage (send warning notification)
- Sites at 100% (should be blocked from AI)
- Expired periods (should auto-reset)

---

## Safety Controls

### Emergency Pause (Global)

```php
// Pause all AI processing
update_option('msh_ai_globally_paused', 1);
```

**Effect:** All AI calls return early with error. Falls back to contextual mode.

**When to use:**
- Token costs spiking unexpectedly
- Quality issues detected
- OpenAI API issues
- Critical bug discovered

**How to resume:**
```php
update_option('msh_ai_globally_paused', 0);
```

### Disable Smart Mode

```php
// wp-config.php or plugin settings
define( 'MSH_SMART_MODE_ENABLED', false );
```

**Effect:** Smart Mode disabled, other modes still work.

**When to use:**
- Smart Mode specific issues
- Want to A/B test with other modes

### Adjust Free Tier Throttle

```php
// Reduce daily limit (from 614 to 200)
update_option('msh_free_daily_throttle', 200);
```

**Effect:** Free users limited to 1 Smart image every 1.5 days.

**When to use:**
- Free tier usage too high
- Want to encourage upgrades
- Temporary capacity reduction

### Block Specific Site

```sql
-- Suspend a site
UPDATE wp_msh_ai_token_balance
SET status = 'suspended'
WHERE site_id = 'SITE_ABUSIVE';
```

**Effect:** Site cannot use AI features.

**When to use:**
- Abuse detected
- Payment issues
- Terms violation

---

## Rollback Procedures

### Level 1: Disable Smart Mode Only

```php
define( 'MSH_SMART_MODE_ENABLED', false );
```

**Impact:** Low - Other modes still work

### Level 2: Pause All AI

```php
update_option('msh_ai_globally_paused', 1);
```

**Impact:** Medium - Falls back to contextual mode

### Level 3: Full Rollback

```bash
cd /path/to/plugin
git checkout v1.2.x
wp plugin deactivate msh-image-optimizer
wp plugin activate msh-image-optimizer
```

**Impact:** High - Reverts all Phase 0B changes

---

## Alert Triggers

### Warning (Yellow)

- Avg tokens >330 or <280
- P95 latency >6.5s
- Error rate >2%
- Quality acceptance <90%
- Any underflow event

**Action:** Investigate within 4 hours

### Critical (Red)

- Avg tokens >350 or <260
- P95 latency >8.0s
- Error rate >5%
- Quality acceptance <85%
- Underflow events >5

**Action:** Pause AI immediately, investigate

### Emergency (Black)

- Token costs >$100/day unexpected
- Mass underflow events
- Data corruption detected
- Security breach

**Action:** Emergency pause, full rollback, incident response

---

## Common Issues & Solutions

### Issue: Token Usage Creeping Up

**Symptoms:** Avg tokens slowly increasing from 309 → 320 → 330

**Causes:**
- Prompt drift
- Image complexity increasing
- Model changes by OpenAI

**Solutions:**
1. Review prompt templates
2. Check for unintended context additions
3. Run Phase 0C optimization
4. Consider output token caps

### Issue: High Latency

**Symptoms:** P95 >6.5s

**Causes:**
- Large images (>2MB)
- OpenAI API slow
- Network issues

**Solutions:**
1. Resize images before AI call (recommended: 640px)
2. Check OpenAI status page
3. Implement caching for repeated calls
4. Consider async queue processing

### Issue: Generic Titles (Quality Gap)

**Symptoms:** Quality acceptance <93%

**Causes:**
- Insufficient context
- Images are truly generic
- Prompt too compressed

**Solutions:**
1. Enable Precision Nudge (max 10% images)
2. Add more context to prompts
3. A/B test prompt variations
4. Manual review for edge cases

### Issue: Free Tier Abuse

**Symptoms:** Free tier usage unexpectedly high

**Causes:**
- Users gaming daily reset
- Multiple accounts
- Bots

**Solutions:**
1. Reduce FREE_DAILY_THROTTLE to 200
2. Implement IP-based rate limiting
3. Add CAPTCHA for free tier
4. Monitor for suspicious patterns

---

## Daily Checklist

- [ ] Check token usage (target: 287-327)
- [ ] Check latency (target: <6.0s)
- [ ] Check error rate (target: <1%)
- [ ] Review underflow events (target: 0)
- [ ] Check balance health (sites >80%)

## Weekly Checklist

- [ ] Review quality sample (10 random images)
- [ ] Analyze cost trends
- [ ] Check for prompt drift
- [ ] Review precision nudge stats
- [ ] Update monitoring thresholds if needed

## Monthly Checklist

- [ ] Full quality audit (50 images)
- [ ] Cost vs budget review
- [ ] Performance trend analysis
- [ ] Consider Phase 0C optimization
- [ ] Review and update runbook

---

## Contact & Escalation

**Level 1 (Warning):**
- Engineering team
- Slack: #optimizer-alerts
- Response: 4 hours

**Level 2 (Critical):**
- Engineering lead
- Slack: @channel in #optimizer-alerts
- Response: 1 hour

**Level 3 (Emergency):**
- CTO
- Phone + Slack
- Response: Immediate

---

## Reference Commands

```bash
# Check current status
wp option get msh_ai_globally_paused
wp option get msh_free_daily_throttle

# View recent errors
wp db query "SELECT * FROM wp_msh_ai_token_usage WHERE status='error' ORDER BY created_at DESC LIMIT 10"

# Check balance for site
wp eval '$m = new MSH_Token_Manager("SITE_PRO"); print_r($m->get_balance_api());'

# Manual token reset (careful!)
wp db query "UPDATE wp_msh_ai_token_balance SET tokens_used = 0 WHERE site_id='SITE_TEST'"

# Clear daily throttle
wp transient delete msh_daily_tokens_SITE_FREE
```

---

**Last Reviewed:** November 2, 2025
**Next Review:** December 2, 2025
**Owner:** Engineering Team
