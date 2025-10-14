# Sales Platform Strategy

**Date**: October 13, 2025
**Question**: What platform are we planning to use to sell the plugin?

---

## Executive Summary

**Current Plan**: Custom micro-server architecture using **Lemon Squeezy + Vercel + Supabase**

**Historical Context**: Early documents mentioned Freemius, but later analysis revealed it takes 10% of gross revenue, reducing margins significantly. We've since pivoted to self-hosted licensing.

**Decision**: Self-host licensing infrastructure for 10% better margins (+$35K annually at 1,000 users)

---

## The Evolution of Our Platform Strategy

### Phase 1: Early Planning (Mentioned Freemius)

**From older docs** ([MSH_IMAGE_OPTIMIZER_RND.md:100](msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_RND.md#L100)):
> "Credits stored per licence/site (Freemius compatible) with monthly allowance + rollover + purchased packs."

**What Freemius Is**:
- WordPress plugin licensing platform (like Gumroad for WordPress)
- Handles payments, license activation, updates, support ticketing
- Takes **10% of gross revenue** as commission
- Used by 30,000+ WordPress plugins

**Why It Was Considered**:
- ✅ Fast to launch (1-2 weeks integration)
- ✅ Proven platform (handles VAT, EU compliance, global payments)
- ✅ Built-in analytics and customer management
- ✅ WordPress.org integration for freemium model

---

### Phase 2: Margin Analysis (Freemius Cost Revealed)

**From** ([MARGIN_ANALYSIS_STARTER_TIERS.md:86-96](msh-image-optimizer/docs/MARGIN_ANALYSIS_STARTER_TIERS.md#L86-L96)):

```
License Management (Freemius):
- 10% of gross revenue (if using Freemius)
- Cost: $9 × 0.10 = $0.90/month per customer
- Alternative: Self-hosted (EDD) = $0/month

Non-AI Starter ($9/month):
Total Third-Party: $1.47/month (with Freemius)
OR $0.57/month (self-hosted)
```

**Impact on margins**:
```
With Freemius:    28.8% margin
Self-Hosted:      38.8% margin
Improvement:      +10 percentage points
Annual Impact:    +$35k on 1,000 users
```

**Decision**: Freemius 10% fee is too high. Build custom licensing.

---

### Phase 3: Custom Licensing Architecture (Current Plan)

**From** ([MSH_IMAGE_OPTIMIZER_DEV_NOTES.md:2703-2829](msh-image-optimizer/docs/MSH_IMAGE_OPTIMIZER_DEV_NOTES.md#L2703-L2829)):

## Our Planned Architecture

### The Stack

```
┌─────────────────────────────────────────────┐
│         CUSTOMER JOURNEY                     │
├─────────────────────────────────────────────┤
│  1. User visits pricing page                │
│  2. Clicks "Buy Pro" button                 │
│  3. Redirected to Lemon Squeezy checkout    │
│  4. Enters payment info (Stripe/PayPal)     │
│  5. Lemon Squeezy processes payment         │
│  6. Webhook → Vercel Edge Function          │
│  7. Vercel creates license in Supabase      │
│  8. User receives email with license key    │
│  9. User activates plugin in WordPress      │
│ 10. WordPress pings Vercel for validation   │
│ 11. Vercel checks Supabase, returns OK      │
│ 12. Plugin unlocks Pro features             │
└─────────────────────────────────────────────┘
```

---

### 1. **Lemon Squeezy** - Payment Processing

**What it is**: Modern payment platform for digital products (alternative to Gumroad/Paddle)

**What it does**:
- Processes payments (credit cards, PayPal, Apple Pay)
- Handles VAT/tax compliance (180+ countries)
- Manages subscriptions (monthly/annual billing)
- Sends webhooks on purchase/renewal/cancellation

**Why we chose it**:
- ✅ **2.9% + $0.30 per transaction** (same as Stripe, but includes tax handling)
- ✅ **No monthly fees** (vs Paddle's $500/month minimum)
- ✅ **Built for SaaS** (subscription management, failed payment recovery)
- ✅ **Merchant of record** (they handle EU VAT, sales tax, invoicing)
- ✅ **Webhook-first** (easy integration with Vercel)

**Pricing**:
- Transaction fee: 2.9% + $0.30
- $9 tier: $0.56 per transaction
- $29 tier: $1.14 per transaction
- $199 tier (agency): $6.07 per transaction

**Alternatives considered**:
- Stripe (requires tax handling, invoicing, subscription logic)
- Paddle (good but $500/month minimum)
- Gumroad (takes 10% like Freemius)

---

### 2. **Vercel Edge Functions** - License Validation API

**What it is**: Serverless API endpoints that run at the edge (near users)

**What it does**:
- Receives webhooks from Lemon Squeezy
- Validates license keys from WordPress installations
- Serves update manifests (plugin version checks)
- Generates signed download URLs for Pro plugin ZIPs

**Why we chose it**:
- ✅ **$0 for first 100,000 requests/month** (we'll use ~10,000)
- ✅ **Global edge network** (fast response times worldwide)
- ✅ **Node.js runtime** (easy to use Supabase JS client)
- ✅ **Zero DevOps** (no servers to maintain)
- ✅ **Built-in monitoring** (Vercel dashboard shows errors, latency)

**Our endpoints**:
```
POST /api/webhooks/lemonsqueezy
- Receives purchase events
- Creates license records
- Sends activation email

POST /api/licenses/validate
- Checks license key validity
- Returns activation status
- Enforces domain limits (2 sites for Pro, 10 for Agency)

GET /api/updates/check
- WordPress pings this on admin page load
- Returns latest plugin version
- Provides signed download URL

GET /api/updates/download/:license
- Returns signed Cloudflare R2 URL
- Time-limited (15 minutes)
- Logs download for analytics
```

**Cost**:
```
Free tier: 100,000 requests/month
Our usage (1,000 customers):
- License checks: 1,000 × 30 days = 30,000 requests/month
- Update checks: 1,000 × 30 days = 30,000 requests/month
- Webhook events: 1,000 × 1 = 1,000 requests/month
- Total: 61,000 requests/month (well under free tier)

Cost: $0/month ✅
```

**Alternatives considered**:
- AWS Lambda (complex setup, requires API Gateway)
- Cloudflare Workers (good alternative, similar pricing)
- Self-hosted VPS (costs $20-50/month, requires maintenance)

---

### 3. **Supabase Postgres** - Database

**What it is**: Open-source Firebase alternative (Postgres + realtime + auth)

**What it does**:
- Stores license records (key, email, plan, activation dates)
- Tracks domain activations (which sites use this license)
- Logs release history (plugin versions, changelogs)
- Manages customer records (linked to Lemon Squeezy customer ID)

**Why we chose it**:
- ✅ **$0 for first 500MB** (we'll use ~50MB)
- ✅ **Postgres** (full SQL, no vendor lock-in)
- ✅ **Official JS client** (easy to use in Vercel Edge Functions)
- ✅ **Built-in auth** (if we add customer portal later)
- ✅ **Realtime subscriptions** (if we add live license management)

**Schema**:
```sql
-- Licenses table
CREATE TABLE licenses (
  id UUID PRIMARY KEY,
  license_key VARCHAR(48) UNIQUE NOT NULL,
  email VARCHAR(255) NOT NULL,
  plan_code VARCHAR(50) NOT NULL, -- 'pro', 'agency', 'enterprise'
  status VARCHAR(20) NOT NULL, -- 'active', 'expired', 'cancelled'
  max_activations INT DEFAULT 2,
  purchase_date TIMESTAMP,
  expires_at TIMESTAMP,
  lemon_squeezy_order_id VARCHAR(100),
  lemon_squeezy_customer_id VARCHAR(100),
  created_at TIMESTAMP DEFAULT NOW(),
  last_seen_at TIMESTAMP
);

-- Activations table
CREATE TABLE activations (
  id UUID PRIMARY KEY,
  license_id UUID REFERENCES licenses(id),
  domain VARCHAR(255) NOT NULL,
  site_url VARCHAR(512),
  activated_at TIMESTAMP DEFAULT NOW(),
  last_seen_at TIMESTAMP,
  is_active BOOLEAN DEFAULT TRUE,
  UNIQUE(license_id, domain)
);

-- Releases table
CREATE TABLE releases (
  id UUID PRIMARY KEY,
  version VARCHAR(20) NOT NULL,
  changelog TEXT,
  zip_url VARCHAR(512),
  min_plan VARCHAR(50), -- 'free', 'pro', 'agency'
  released_at TIMESTAMP DEFAULT NOW(),
  is_stable BOOLEAN DEFAULT TRUE
);
```

**Cost**:
```
Free tier: 500MB database, 2GB bandwidth/month
Our usage (1,000 customers):
- Licenses: 1,000 rows × 1KB = 1MB
- Activations: 2,000 rows × 0.5KB = 1MB
- Releases: 50 rows × 2KB = 0.1MB
- Total: ~2MB (well under free tier)

Bandwidth:
- License checks: 61,000 × 1KB = 61MB/month
- Well under 2GB free tier

Cost: $0/month ✅
```

**Alternatives considered**:
- PlanetScale (good but less generous free tier)
- Railway (Postgres but costs $5/month minimum)
- Self-hosted MySQL (costs $20/month VPS + maintenance)

---

### 4. **Cloudflare R2** - Plugin ZIP Storage

**What it is**: S3-compatible object storage (like Amazon S3 but cheaper)

**What it does**:
- Stores plugin ZIP files (Pro, Agency versions)
- Generates signed URLs (time-limited, secure downloads)
- Serves downloads globally (Cloudflare CDN)

**Why we chose it**:
- ✅ **$0 egress fees** (S3 charges $0.09/GB)
- ✅ **$0.015/GB storage** (10GB = $0.15/month)
- ✅ **Signed URLs** (time-limited access, no direct download links)
- ✅ **Global CDN** (fast downloads worldwide)

**Our usage**:
```
Storage:
- Pro plugin ZIP: 5MB × 10 versions = 50MB
- Agency plugin ZIP: 5MB × 10 versions = 50MB
- Total: 100MB = $0.0015/month

Bandwidth (1,000 customers):
- 1,000 downloads/month × 5MB = 5GB
- R2 egress: $0 (free!)

Cost: $0/month ✅
```

**Alternatives considered**:
- Amazon S3 (costs $0.45/month for 5GB egress)
- BunnyCDN (costs $1/month for storage + $0.01/GB egress)
- Vercel Blob Storage (costs $0.15/GB bandwidth = $0.75/month)

---

### 5. **WordPress Updater** - Client-Side Integration

**What it is**: PHP class that hooks into WordPress's native update system

**What it does**:
- Checks for plugin updates (pings Vercel API)
- Shows "Update Available" in WordPress admin
- Downloads new version when user clicks "Update"
- Validates license before allowing update

**Why we need it**:
- WordPress core expects plugins to provide update info
- Users are familiar with the native update UI
- No custom dashboard needed (uses WP's built-in screens)

**How it works**:
```php
// Check for updates (runs daily via WP cron)
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    $license_key = get_option('msh_license_key');
    $current_version = MSH_IMAGE_OPTIMIZER_VERSION;

    $response = wp_remote_post('https://licenses.thedotcreative.com/api/updates/check', [
        'body' => [
            'license' => $license_key,
            'domain' => home_url(),
            'current_version' => $current_version
        ]
    ]);

    $data = json_decode(wp_remote_retrieve_body($response));

    if ($data->new_version > $current_version) {
        $transient->response['msh-image-optimizer/msh-image-optimizer.php'] = (object) [
            'slug' => 'msh-image-optimizer',
            'new_version' => $data->new_version,
            'package' => $data->download_url, // Signed R2 URL
            'url' => 'https://thedotcreative.com/msh-image-optimizer'
        ];
    }

    return $transient;
});
```

**License activation UI**:
```php
// Settings page
<input type="text" name="msh_license_key" placeholder="Enter license key">
<button class="button button-primary">Activate License</button>

// AJAX handler
add_action('wp_ajax_msh_activate_license', function() {
    $license_key = sanitize_text_field($_POST['license_key']);
    $domain = home_url();

    $response = wp_remote_post('https://licenses.thedotcreative.com/api/licenses/validate', [
        'body' => [
            'license' => $license_key,
            'domain' => $domain,
            'action' => 'activate'
        ]
    ]);

    $data = json_decode(wp_remote_retrieve_body($response));

    if ($data->valid) {
        update_option('msh_license_key', $license_key);
        update_option('msh_license_status', 'active');
        wp_send_json_success('License activated!');
    } else {
        wp_send_json_error($data->error);
    }
});
```

---

## Cost Comparison: Freemius vs Self-Hosted

### Freemius (10% Commission)

| Component | Cost per Customer/Month | Notes |
|-----------|------------------------|-------|
| Payment processing | $0.56 | 2.9% + $0.30 on $9 |
| License management | $0.90 | 10% of $9 |
| Infrastructure | $0.60 | Hosting, CDN, email |
| Support | $1.83 | 3 tickets/year |
| Overhead | $2.50 | Tools, legal, misc |
| **Total** | **$6.41** | **28.8% margin** |

---

### Self-Hosted (Our Plan)

| Component | Cost per Customer/Month | Notes |
|-----------|------------------------|-------|
| Lemon Squeezy | $0.56 | 2.9% + $0.30 on $9 |
| Vercel Edge | $0 | Free tier (100K requests) |
| Supabase DB | $0 | Free tier (500MB) |
| Cloudflare R2 | $0 | Free tier (10GB) |
| Infrastructure | $0.60 | Email, monitoring |
| Support | $1.83 | 3 tickets/year |
| Overhead | $2.50 | Tools, legal, misc |
| **Total** | **$5.51** | **38.8% margin** |

**Savings**: $0.90/customer/month = **+10% margin**

**Annual impact** (1,000 customers):
- Freemius: $6.41 × 1,000 × 12 = $76,920
- Self-hosted: $5.51 × 1,000 × 12 = $66,120
- **Savings: $10,800/year** at 1,000 customers
- **Savings: $35,000/year** at 3,000 customers (Year 3 goal)

---

## Implementation Timeline

### Phase 1: Database Setup (Week 1)
- [x] Design Supabase schema
- [ ] Deploy Supabase project
- [ ] Create tables (licenses, activations, releases)
- [ ] Seed with test data

### Phase 2: Vercel API (Week 2)
- [ ] Deploy Vercel project
- [ ] Build `/api/licenses/validate` endpoint
- [ ] Build `/api/updates/check` endpoint
- [ ] Build `/api/webhooks/lemonsqueezy` endpoint
- [ ] Test with Postman/Insomnia

### Phase 3: WordPress Updater (Week 3)
- [ ] Create `class-msh-updater.php`
- [ ] Hook into `pre_set_site_transient_update_plugins`
- [ ] Build license activation UI (settings page)
- [ ] Test update flow on local site

### Phase 4: Lemon Squeezy (Week 4)
- [ ] Create Lemon Squeezy account
- [ ] Set up products (Pro $99/year, Agency $199/year)
- [ ] Configure webhook URL (Vercel endpoint)
- [ ] Test purchase flow end-to-end

### Phase 5: R2 Storage (Week 5)
- [ ] Create Cloudflare R2 bucket
- [ ] Upload plugin ZIPs
- [ ] Generate signed URLs from Vercel
- [ ] Test download flow

### Phase 6: Beta Testing (Week 6)
- [ ] Invite 10 The Dot Creative clients to beta
- [ ] Collect feedback on activation flow
- [ ] Fix bugs and edge cases
- [ ] Prepare for public launch

**Total timeline**: 6 weeks (Q1 2026)

---

## Why This Architecture Is Better Than Freemius

### 1. **Cost Savings** (+10% margin)
- Save $0.90/customer/month
- $35K/year savings at 3,000 customers

### 2. **No Vendor Lock-In**
- Own all customer data (Supabase Postgres)
- Can migrate payment processor (Lemon Squeezy → Stripe)
- Can switch storage (R2 → S3)

### 3. **Full Control**
- Custom license validation logic
- White-label experience (no Freemius branding)
- Agency-specific features (bulk activation, client management)

### 4. **Scalability**
- Vercel Edge scales automatically
- Supabase handles 500K requests/month on free tier
- R2 has no egress fees (unlimited bandwidth)

### 5. **Better Customer Experience**
- Native WordPress update UI (users are familiar)
- Instant license activation (no Freemius redirect)
- Faster update checks (edge network vs Freemius API)

---

## Risks & Mitigation

### Risk 1: Development Time
**Risk**: Building custom licensing takes 6 weeks vs 1 week for Freemius integration
**Mitigation**:
- Timeline is Q1 2026 (we have time)
- Can launch with WordPress.org free version first
- Beta test with 10 The Dot clients before public launch

### Risk 2: Support Burden
**Risk**: We handle license activation support (Freemius would handle this)
**Mitigation**:
- Clear activation docs (step-by-step screenshots)
- Video tutorial (3-minute walkthrough)
- Automated email with instructions
- Support chatbot for common issues (Phase 2)

### Risk 3: Payment Processing Issues
**Risk**: Lemon Squeezy webhook fails, license not created
**Mitigation**:
- Webhook retries (Lemon Squeezy auto-retries failed webhooks)
- Manual license creation (admin dashboard for edge cases)
- Email notification on failed webhooks (monitor Vercel logs)

### Risk 4: Update Server Downtime
**Risk**: Vercel API down, users can't check for updates
**Mitigation**:
- Vercel has 99.99% uptime SLA
- Graceful degradation (cache last update check for 24 hours)
- Fallback to manual download (download from website)

---

## Comparison: Freemius vs Our Plan

| Feature | Freemius | Our Plan (Lemon Squeezy + Vercel) |
|---------|----------|-----------------------------------|
| **Payment processing** | Built-in | Lemon Squeezy |
| **License validation** | Freemius API | Vercel Edge Functions |
| **Plugin updates** | Freemius | Vercel + Cloudflare R2 |
| **Customer portal** | Built-in | Custom (Phase 2) |
| **Support ticketing** | Built-in | Intercom/Zendesk (Phase 2) |
| **Analytics** | Built-in | Google Analytics + custom |
| **Cost (Year 1)** | $6.41/customer/month | $5.51/customer/month |
| **Margin (Year 2+)** | 28.8% | 38.8% (+10%) |
| **Setup time** | 1 week | 6 weeks |
| **Vendor lock-in** | High | Low (own all data) |
| **White-label** | No (Freemius branding) | Yes (100% our brand) |
| **Agency features** | Limited | Custom (bulk activation, etc.) |

---

## Final Decision

**We're building a custom licensing system using**:
1. **Lemon Squeezy** - Payment processing
2. **Vercel Edge Functions** - License API
3. **Supabase Postgres** - Database
4. **Cloudflare R2** - Plugin ZIP storage
5. **WordPress Updater** - Client integration

**Why**: Save 10% margin (+$35K/year at scale), full control, no vendor lock-in, better customer experience

**Timeline**: Q1 2026 (6 weeks development + 2 weeks beta testing)

**Backup plan**: If development timeline slips, we can launch with Freemius temporarily and migrate to self-hosted later (all our code will work with either platform)

---

## Next Steps

1. **This month**: Focus on WordPress.org compliance and descriptor pipeline
2. **Next month**: Deploy Supabase schema and Vercel API
3. **Month 3**: Build WordPress updater and test with 10 beta customers
4. **Month 4**: Launch Pro tier with self-hosted licensing

**Bottom line**: We're NOT using Freemius. We're building a custom, self-hosted licensing system that saves 10% margin and gives us full control.
