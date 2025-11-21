# Deployment Checklist - v1.3.0-0B

**Release:** Phase 0B Smart Mode + Token System
**Date:** November 2, 2025
**Status:** ✅ READY TO SHIP

---

## Pre-Deployment Checklist

### ✅ Code Integration (COMPLETE)

- [x] Version bumped to 1.3.0-0B in plugin header
- [x] Version constant updated in `MSH_Image_Optimizer_Plugin::VERSION`
- [x] Token Manager class included (`class-msh-token-manager.php`)
- [x] Token Balance Widget included (`class-msh-token-balance-widget.php`)
- [x] Precision Nudge included (`class-msh-precision-nudge.php`)
- [x] Widget JavaScript ready (`token-balance-widget.js`)
- [x] Widget CSS complete (`token-balance-widget.css`) - Layer 4 Component

### ✅ Testing & Validation (COMPLETE)

- [x] **Smoke test** - All 5 categories passed
- [x] **Underflow test** - Clamping at zero validated
- [x] **Phase 0B baseline** - 30 images, 309 avg tokens, 5.2s latency
- [x] **All 9 compliance gaps** - Validated and passing
- [x] **Database tables** - Created and indexed
- [x] **REST API** - `/msh/v1/ai-usage` working
- [x] **Token deduction** - Atomic SQL concurrency-safe

### ✅ Documentation (COMPLETE)

- [x] Release notes (`RELEASE-v1.3.0-0B.md`)
- [x] Widget integration guide (`WIDGET-INTEGRATION-GUIDE.md`)
- [x] Phase 0B testing guide (`PHASE-0B-TESTING-GUIDE.md`)
- [x] Verification report (`PHASE-0B-VERIFICATION-REPORT.md`)
- [x] Production runbook (`PRODUCTION-RUNBOOK.md`)
- [x] Completion summary (`TOKEN-SYSTEM-COMPLETION-SUMMARY.md`)

---

## Deployment Steps

### Step 1: Git Tag & Push (5 min)

```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# Verify current status
git status

# Add all changes
git add .

# Commit
git commit -m "$(cat <<'EOF'
Release v1.3.0-0B: Phase 0B Smart Mode + Token System

Performance Improvements:
- 90.4% token reduction (3,208 → 309 avg tokens/image)
- 20% latency improvement (6.5s → 5.2s)
- 90.6% cost reduction ($0.016 → $0.0015 per image)

New Features:
- Token-based pricing system with 4 tiers
- Smart Mode with ultra-compressed prompts
- Precision Nudge for quality improvement (10% max)
- Token Balance Widget for admin UI
- REST API for token balance queries

Infrastructure:
- All 9 compliance gaps resolved
- Atomic SQL concurrency-safe operations
- Underflow protection with audit logging
- Telemetry-driven token estimates
- Production monitoring ready

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"

# Create tag
git tag v1.3.0-0B -a -m "Phase 0B Smart Mode + Token System
- 90.4% token reduction (309 avg tokens/image)
- All 9 compliance gaps resolved
- Production-ready token management"

# Push commit and tag
git push origin main
git push origin v1.3.0-0B
```

**Verification:**
```bash
git log -1 --oneline
git tag -l | grep v1.3.0-0B
```

### Step 2: Widget Integration (15 min)

Follow the detailed instructions in [WIDGET-INTEGRATION-GUIDE.md](../guides/WIDGET-INTEGRATION-GUIDE.md).

**Quick summary:**
1. Add widget render call to Hub page (`admin/class-msh-hub-page.php`)
2. Add widget render call to Settings page (`admin/image-optimizer-settings.php`)
3. Add widget render call to Media Library page (optional)
4. Test widget display and API calls

**Example integration:**
```php
// In display_page() method
if ( class_exists( 'MSH_Token_Balance_Widget' ) ) {
    $widget = MSH_Token_Balance_Widget::get_instance();
    echo $widget->render( array(
        'site_id'      => $this->get_current_site_id(),
        'show_details' => true,
        'show_upgrade' => true,
    ) );
}
```

### Step 3: Production Monitoring Setup (30 min)

Follow the monitoring queries in [PRODUCTION-RUNBOOK.md](../guides/PRODUCTION-RUNBOOK.md).

**Daily KPIs to track:**
- Avg tokens/image (target: 287-327)
- P95 latency (target: <6.0s)
- Error rate (target: <1%)
- Quality acceptance (target: ≥93%)
- Underflow events (target: 0)

**Set up automated queries:**
```sql
-- Token usage monitoring (run daily)
SELECT
    DATE(created_at) as date,
    COUNT(*) as n_images,
    AVG(token_count) as avg_tokens,
    MIN(token_count) as min_tokens,
    MAX(token_count) as max_tokens
FROM wp_msh_ai_token_usage
WHERE created_at >= NOW() - INTERVAL 7 DAY
AND mode = 'smart'
GROUP BY DATE(created_at);
```

**Configure alerts:**
- Warning (Yellow): avg_tokens >330 or <280
- Critical (Red): avg_tokens >350 or <260
- Emergency (Black): Token costs >$100/day unexpected

### Step 4: Safety Controls (5 min)

Ensure emergency controls are accessible:

```php
// Emergency pause (if needed)
update_option('msh_ai_globally_paused', 1);

// Disable Smart Mode only (if needed)
define( 'MSH_SMART_MODE_ENABLED', false );

// Adjust free tier throttle (if needed)
update_option('msh_free_daily_throttle', 200);
```

**Document these in internal wiki/runbook.**

### Step 5: Deploy to Production (15 min)

**For WordPress Multisite or single site:**

1. **Backup production database** (critical - token tables)
   ```bash
   wp db export backup-pre-v1.3.0-0B.sql
   ```

2. **Deploy code** (via git, FTP, or deployment tool)
   ```bash
   # If using git
   cd /path/to/production/wp-content/plugins/msh-image-optimizer
   git fetch origin
   git checkout v1.3.0-0B
   ```

3. **Run database migrations** (token tables)
   ```bash
   # Tables should auto-create on plugin activation
   wp plugin deactivate msh-image-optimizer
   wp plugin activate msh-image-optimizer
   ```

4. **Verify tables exist**
   ```bash
   wp db query "SHOW TABLES LIKE 'wp_msh_ai_token%'"
   # Should show:
   # - wp_msh_ai_token_balance
   # - wp_msh_ai_token_audit
   # - wp_msh_ai_token_usage
   ```

5. **Seed test tiers** (if not already present)
   ```bash
   wp eval-file seed-token-tiers.php
   ```

6. **Test token deduction**
   ```bash
   wp eval '$m = new MSH_Token_Manager("SITE_PRO"); print_r($m->get_balance());'
   ```

### Step 6: Post-Deployment Verification (15 min)

Run these checks immediately after deployment:

1. **Widget displays correctly**
   - Visit admin Hub page
   - Check token balance widget shows
   - Verify API calls work (check browser console)

2. **Smart Mode works**
   - Process 1 test image in Smart Mode
   - Verify tokens deducted
   - Check telemetry logged

3. **REST API accessible**
   ```bash
   curl -s "https://yoursite.com/wp-json/msh/v1/ai-usage?site_id=SITE_PRO" | jq .
   ```

4. **Monitoring queries return data**
   ```bash
   wp db query "SELECT COUNT(*) FROM wp_msh_ai_token_usage WHERE mode='smart'"
   ```

5. **No errors in logs**
   ```bash
   tail -f /path/to/wp-content/debug.log
   ```

---

## Post-Deployment Monitoring

### First 24 Hours

- [ ] Check error rate every 4 hours
- [ ] Monitor token usage (should be 287-327 avg)
- [ ] Review 10 random Smart Mode outputs for quality
- [ ] Check for any underflow events (should be 0)
- [ ] Verify free tier throttle working

### First Week

- [ ] Daily KPI review (tokens, latency, errors)
- [ ] Quality spot check (20 images)
- [ ] Cost vs budget review
- [ ] User feedback collection
- [ ] Precision Nudge effectiveness (10% max?)

### First Month

- [ ] Full quality audit (50 images)
- [ ] Cost trend analysis
- [ ] Consider Phase 0C optimization if needed
- [ ] A/B test prompt variations
- [ ] Review and update thresholds

---

## Rollback Plan

If critical issues arise, follow these steps:

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
git checkout v1.2.16
wp plugin deactivate msh-image-optimizer
wp plugin activate msh-image-optimizer

# Restore database if needed
wp db import backup-pre-v1.3.0-0B.sql
```
**Impact:** High - Reverts all Phase 0B changes

---

## Success Metrics

Phase 0B is considered successful if after 1 week:

- ✅ **Token usage:** 280-330 avg (within 10% of 307)
- ✅ **Latency:** 4.5-6.0s avg
- ✅ **Error rate:** <1%
- ✅ **Quality acceptance:** ≥93% (with precision nudge)
- ✅ **Underflow events:** 0
- ✅ **Cost:** Within budget projections

If any metric fails, refer to [PRODUCTION-RUNBOOK.md](../guides/PRODUCTION-RUNBOOK.md) for troubleshooting.

---

## Internal Announcement Template

**Subject:** 🚀 MSH Image Optimizer v1.3.0-0B Deployed - 90% Cost Reduction!

**Team,**

We've successfully deployed Phase 0B Smart Mode with the new token-based pricing system.

**Key Wins:**
- 90.4% token reduction (3,208 → 309 avg tokens/image)
- 90.6% cost reduction ($0.016 → $0.0015 per image)
- 20% latency improvement (6.5s → 5.2s)
- Production-ready token management with 4 pricing tiers

**What's New:**
- Smart Mode with ultra-compressed prompts
- Token Balance Widget in admin UI
- Precision Nudge for quality improvement
- REST API for token usage queries
- Comprehensive monitoring and safety controls

**Testing Results:**
- 30 images tested: 309 avg tokens, 5.2s latency ✅
- All 9 compliance gaps validated ✅
- Zero critical issues ✅

**Monitoring:**
We're tracking 5 key metrics daily for the first week. See the production runbook for details.

**Questions?**
Check the documentation in `/docs` or ping the engineering team.

**Great work, team!**

---

## Files Reference

All deployment documentation is located in the standalone repo:

```
/Users/anastasiavolkova/msh-image-optimizer-standalone/
├── DEPLOYMENT-CHECKLIST-v1.3.0-0B.md (this file)
├── RELEASE-v1.3.0-0B.md
├── WIDGET-INTEGRATION-GUIDE.md
├── PRODUCTION-RUNBOOK.md
├── PHASE-0B-VERIFICATION-REPORT.md
├── PHASE-0B-TESTING-GUIDE.md
└── TOKEN-SYSTEM-COMPLETION-SUMMARY.md
```

---

## Status

**Current Status:** ✅ READY TO SHIP

**Code Status:** ✅ Integrated and tested
**Testing Status:** ✅ All tests passed
**Documentation Status:** ✅ Complete
**Deployment Status:** ⏳ Pending git tag and production deploy

**Next Action:** Execute Step 1 (Git Tag & Push) when ready to release.

---

**Prepared By:** Engineering Team
**Date:** November 2, 2025
**Approval:** ✅ Production Ready
**Sign-off:** Ready for deployment
