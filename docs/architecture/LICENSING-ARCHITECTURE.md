# MSH Image Optimizer - Licensing Architecture

## Overview

The MSH Image Optimizer uses a **server-side license validation system** to manage Pro/Agency plan activations, prevent piracy, and handle subscription billing.

**Key Principle:** License generation and validation happens on a **separate API server**, NOT inside the WordPress plugin.

---

## Architecture Decision: Separate License Server

### ❌ DON'T: Build License Server Inside WordPress Plugin

**Why Not:**
- WordPress plugin contains payment secrets (bad for security)
- WordPress.org review process more complex
- Can't independently scale/deploy
- PCI compliance scope increases
- Harder to test and CI/CD

### ✅ DO: Separate License API Server

**Benefits:**
- Independent deploys, rollbacks, scaling
- Security isolation for Stripe/PayPal webhooks and secrets
- Cleaner testing and CI
- Plugin stays lean and review-friendly for WordPress.org
- Can later migrate to "Optimizer Cloud Service" without plugin changes

---

## Recommended Project Structure

### Option 1: Monorepo (Recommended)

```
the-dot-optimizer/
├─ apps/
│  ├─ wp-plugin/                     # MSH Image Optimizer plugin
│  └─ license-api/                   # Node (Nest/Fastify) or PHP (Laravel/Slim)
├─ packages/
│  └─ license-sdk/                   # Tiny client library used by plugin to call API
├─ infra/
│  ├─ docker-compose.dev.yml         # Local Postgres + Mailhog
│  └─ terraform/                     # Optional: Infrastructure as Code for production
├─ .github/workflows/                # CI/CD for both apps
└─ README.md
```

**Monorepo Tools:**
- Turborepo
- Nx
- pnpm workspaces
- Yarn workspaces

### Option 2: Polyrepo

Keep `wp-plugin` and `license-api` as **separate repositories**.

- Publish `license-sdk` to a private npm/composer registry
- Functionally the same, just different repo strategy
- Better for teams with separate ownership

---

## System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     User Purchases Pro                       │
│                    (thedot.com website)                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    Stripe/PayPal Checkout
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                  License API Server                          │
│              (license.thedot.com/api/v1)                     │
│                                                              │
│  • Receives payment webhooks                                 │
│  • Generates license keys: MSH-XXXX-XXXX-XXXX-XXXX          │
│  • Stores in database (Postgres/MySQL)                      │
│  • Sends email with license key                             │
│  • Validates activations                                     │
│  • Tracks subscription status                                │
└─────────────────────────────────────────────────────────────┘
                              ↓
                         REST API
                              ↓
┌─────────────────────────────────────────────────────────────┐
│               WordPress Plugin (wp-plugin/)                  │
│                                                              │
│  • User enters license key in Settings                       │
│  • Calls license-api via license-sdk                         │
│  • Stores validation locally (wp_options)                    │
│  • Daily cron checks license status                          │
│  • Gates Pro features based on status                        │
└─────────────────────────────────────────────────────────────┘
```

---

## API Endpoints (Minimal Contract)

Lock these in now - they form the stable interface between plugin and server.

### POST /activate

**Request:**
```json
{
  "license_key": "MSH-XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://example.com",
  "site_id": "hash-of-home+salt",
  "plugin_version": "1.2.0"
}
```

**Response 200:**
```json
{
  "status": "active",
  "plan": "pro",
  "email": "user@site.com",
  "expires_at": "2026-10-22T00:00:00Z",
  "max_activations": 5,
  "activated": 2
}
```

**Response 400:** Invalid license key format
**Response 403:** License invalid, expired, or activation limit reached
**Response 429:** Rate limit exceeded

---

### POST /verify

**Request:**
```json
{
  "license_key": "MSH-XXXX-XXXX-XXXX-XXXX",
  "site_id": "hash-of-home+salt"
}
```

**Response 200:**
```json
{
  "status": "active",
  "plan": "pro",
  "remaining_activations": 3,
  "expires_at": "2026-10-22T00:00:00Z"
}
```

**Possible Statuses:**
- `active` - License valid and active
- `expired` - Subscription lapsed
- `cancelled` - User cancelled subscription
- `invalid` - License key doesn't exist or revoked

---

### POST /deactivate

**Request:**
```json
{
  "license_key": "MSH-XXXX-XXXX-XXXX-XXXX",
  "site_id": "hash-of-home+salt"
}
```

**Response 200:**
```json
{
  "status": "deactivated",
  "remaining_activations": 4
}
```

---

### POST /portal (Stripe Customer Portal)

**Request:**
```json
{
  "license_key": "MSH-XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://example.com"
}
```

**Response 200:**
```json
{
  "portal_url": "https://billing.stripe.com/p/session/cs_test_abc123..."
}
```

**Purpose:** Generate Stripe Customer Portal link for user to:
- Update payment method
- View invoices
- Change plan
- Cancel subscription

---

## Database Schema (license-api)

### Table: `licenses`

```sql
CREATE TABLE licenses (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  license_key TEXT UNIQUE NOT NULL,
  user_email TEXT NOT NULL,
  plan TEXT NOT NULL,                    -- 'pro', 'agency'
  status TEXT NOT NULL,                  -- 'active', 'expired', 'cancelled'
  stripe_customer_id TEXT,               -- For Stripe integration
  stripe_subscription_id TEXT,           -- For subscription management
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  expires_at TIMESTAMPTZ NOT NULL,
  max_activations INTEGER NOT NULL DEFAULT 5,

  INDEX idx_user_email (user_email),
  INDEX idx_license_key (license_key),
  INDEX idx_status (status)
);
```

### Table: `license_activations`

```sql
CREATE TABLE license_activations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  license_id UUID NOT NULL REFERENCES licenses(id) ON DELETE CASCADE,
  site_id TEXT NOT NULL,                 -- Stable hash from plugin
  site_url TEXT NOT NULL,
  activated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  deactivated_at TIMESTAMPTZ,            -- NULL if active
  plugin_version TEXT,
  ip INET,                               -- Optional: Track activation IP

  INDEX idx_license_id (license_id),
  INDEX idx_site_id (site_id),
  UNIQUE (license_id, site_id)           -- One site can only activate once per license
);
```

---

## Payment Integration (Stripe/LemonSqueezy)

### Webhook Flow

```
User purchases Pro on thedot.com
         ↓
Stripe/LemonSqueezy processes payment
         ↓
Webhook sent to license-api
         ↓
┌────────────────────────────────────────┐
│ Webhook Handler (license-api)          │
│                                        │
│ checkout.session.completed:            │
│  → Generate license key                │
│  → Insert into licenses table          │
│  → Set expires_at (1 year from now)    │
│  → Send email with key                 │
│                                        │
│ invoice.payment_succeeded:             │
│  → Extend expires_at (renewal)         │
│  → Update status to 'active'           │
│                                        │
│ invoice.payment_failed:                │
│  → Mark status as 'expired'            │
│  → Send warning email                  │
│                                        │
│ customer.subscription.deleted:         │
│  → Update status to 'cancelled'        │
│  → Set expires_at to period_end        │
└────────────────────────────────────────┘
```

### License Key Generation

**Format:** `MSH-XXXX-XXXX-XXXX-XXXX`

**Generation Algorithm:**
```javascript
// Node.js example
const crypto = require('crypto');

function generateLicenseKey() {
  const segments = 4;
  const segmentLength = 4;
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No ambiguous chars (0,O,1,I)

  const parts = [];
  for (let i = 0; i < segments; i++) {
    let segment = '';
    for (let j = 0; j < segmentLength; j++) {
      const randomIndex = crypto.randomInt(0, chars.length);
      segment += chars[randomIndex];
    }
    parts.push(segment);
  }

  return 'MSH-' + parts.join('-');
}
```

**Validation Regex:**
```regex
^MSH-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$
```

---

## Plugin Responsibilities (wp-plugin/)

### Local Storage (wp_options)

```php
msh_license_key      = "MSH-XXXX-XXXX-XXXX-XXXX"
msh_license_status   = "active" | "expired" | "cancelled" | "inactive"
msh_license_data     = [
    'plan' => 'pro',
    'email' => 'user@example.com',
    'expires' => '2026-10-22',
    'activations' => 2,
    'max_activations' => 5
]
```

### Activation Flow

1. User enters license key in Settings → Account tab
2. Plugin validates format locally (regex check)
3. Plugin calls `license-sdk->activate()`
4. API validates and responds
5. Plugin stores result in wp_options
6. Pro features unlock via `msh_is_pro_active()`

### Daily Verification Cron

```php
// wp-cron.php
wp_schedule_event(time(), 'daily', 'msh_daily_license_check');

add_action('msh_daily_license_check', function() {
    $license_key = get_option('msh_license_key');
    $site_id = msh_get_site_id();

    $result = MSH_License_SDK::verify($license_key, $site_id);

    update_option('msh_license_status', $result['status']);
    update_option('msh_license_data', $result);
});
```

### Site ID Generation

**Important:** Site ID must be **stable** and **unique**.

```php
function msh_get_site_id() {
    $cached = get_option('msh_site_id');
    if ($cached) {
        return $cached;
    }

    // Generate stable hash
    $site_id = hash('sha256', home_url() . wp_salt('auth'));
    update_option('msh_site_id', $site_id, false);

    return $site_id;
}
```

### Feature Gating

```php
function msh_is_pro_active() {
    // Dev mode bypass
    if (defined('MSH_DEV_MODE') && MSH_DEV_MODE) {
        return true;
    }

    $status = get_option('msh_license_status', 'inactive');
    return ('active' === $status);
}

// Usage throughout plugin
if (msh_is_pro_active()) {
    // Show Pro feature
} else {
    // Show upsell
}
```

---

## Shared SDK (packages/license-sdk)

Very small wrapper library that handles API communication.

### PHP Example (Composer Package)

```php
namespace TheDot\LicenseSDK;

class LicenseClient {
    private $apiUrl;

    public function __construct($apiUrl = 'https://license.thedot.com/api/v1') {
        $this->apiUrl = $apiUrl;
    }

    public function activate($licenseKey, $siteUrl, $siteId, $pluginVersion) {
        return $this->request('POST', '/activate', [
            'license_key' => $licenseKey,
            'site_url' => $siteUrl,
            'site_id' => $siteId,
            'plugin_version' => $pluginVersion
        ]);
    }

    public function verify($licenseKey, $siteId) {
        return $this->request('POST', '/verify', [
            'license_key' => $licenseKey,
            'site_id' => $siteId
        ]);
    }

    public function deactivate($licenseKey, $siteId) {
        return $this->request('POST', '/deactivate', [
            'license_key' => $licenseKey,
            'site_id' => $siteId
        ]);
    }

    private function request($method, $endpoint, $data) {
        $url = $this->apiUrl . $endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'body' => wp_json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'MSH-Image-Optimizer/' . MSH_IO_VERSION
            ]
        ]);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
}
```

---

## Local Development Workflow

### 1. Start Infrastructure

```bash
cd infra
docker compose up -d
```

**docker-compose.dev.yml:**
```yaml
version: '3.8'
services:
  postgres:
    image: postgres:15
    environment:
      POSTGRES_DB: license_db
      POSTGRES_USER: dev
      POSTGRES_PASSWORD: dev
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

  mailhog:
    image: mailhog/mailhog
    ports:
      - "1025:1025"  # SMTP
      - "8025:8025"  # Web UI

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  postgres_data:
```

### 2. Run License API

```bash
cd apps/license-api
npm install
npm run dev
# Runs on http://localhost:8787
```

### 3. Configure Plugin for Local Dev

```php
// wp-config.php
define('MSH_LICENSE_API_URL', 'http://host.docker.internal:8787');
define('MSH_DEV_MODE', true); // Bypass license checks
```

### 4. Seed Test License

```bash
cd apps/license-api
npm run seed:licenses
```

**Seed script creates:**
```
License Key: MSH-TEST-DEV1-DEV2-DEV3
Plan: pro
Status: active
Expires: 1 year from now
Max Activations: 999
```

---

## Security Considerations

### 1. Never Store Secrets in WordPress

❌ **DON'T:**
```php
// wp-config.php
define('STRIPE_SECRET_KEY', 'sk_live_...');  // NEVER DO THIS
```

✅ **DO:**
- Store secrets in license-api only
- Use environment variables
- Rotate keys regularly

### 2. Rate Limiting

Protect `/activate` endpoint:
- 5 attempts per IP per hour
- 10 attempts per license key per day
- Use Redis for tracking

```javascript
// Example: Express middleware
const rateLimit = require('express-rate-limit');

const activateLimiter = rateLimit({
  windowMs: 60 * 60 * 1000, // 1 hour
  max: 5,
  keyGenerator: (req) => req.ip
});

app.post('/activate', activateLimiter, handleActivate);
```

### 3. Site ID Hashing

```php
// Use WordPress salt for stability
function msh_get_site_id() {
    return hash('sha256', home_url() . wp_salt('auth'));
}
```

**Why SHA256 + salt:**
- Stable across plugin reinstalls
- Unique per site
- Can't be guessed by attackers

### 4. HTTPS Only

- License API must use HTTPS in production
- Validate SSL certificates
- Use Let's Encrypt for free SSL

### 5. Response Signing (Optional, Advanced)

Sign API responses to prevent MITM attacks:

```javascript
const crypto = require('crypto');

function signResponse(data, secret) {
  const payload = JSON.stringify(data);
  const signature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  return { data, signature };
}
```

---

## Migration Path

Start simple, evolve later:

### Phase 1: MVP (Now)
- Basic license-api server
- Activate/verify/deactivate endpoints
- Simple Stripe webhook integration
- Manual key generation via admin panel

### Phase 2: Automation
- Automatic key generation on purchase
- Email delivery system
- Customer portal integration
- Invoice management

### Phase 3: Cloud Service
- Rename to "Optimizer Cloud Service"
- Add additional endpoints (telemetry, sync, etc.)
- Multi-product support
- Advanced analytics

**Key:** Keep license-sdk host configurable via filter:

```php
apply_filters('msh_license_api_url', 'https://license.thedot.com/api/v1');
```

This allows changing the server without plugin updates!

---

## Testing Strategy

### Unit Tests (license-api)

```javascript
describe('License Activation', () => {
  it('should activate valid license', async () => {
    const response = await activateLicense({
      license_key: 'MSH-TEST-TEST-TEST-TEST',
      site_url: 'https://example.com',
      site_id: 'abc123'
    });

    expect(response.status).toBe('active');
    expect(response.plan).toBe('pro');
  });

  it('should reject invalid license', async () => {
    const response = await activateLicense({
      license_key: 'MSH-FAKE-FAKE-FAKE-FAKE',
      site_url: 'https://example.com',
      site_id: 'abc123'
    });

    expect(response.error).toBeDefined();
  });
});
```

### Integration Tests (wp-plugin)

```php
class Test_License_Manager extends WP_UnitTestCase {
    public function test_activate_license() {
        $license_key = 'MSH-TEST-TEST-TEST-TEST';

        $result = MSH_License_Manager::get_instance()->activate_license($license_key);

        $this->assertTrue($result['success']);
        $this->assertEquals('active', get_option('msh_license_status'));
    }
}
```

### E2E Tests (Playwright/Cypress)

```javascript
test('User can activate license', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=msh-image-optimizer-settings&tab=account');

  await page.fill('#license-key-input', 'MSH-TEST-TEST-TEST-TEST');
  await page.click('#activate-license-button');

  await expect(page.locator('.success-message')).toContainText('License activated');
  await expect(page.locator('.license-status')).toContainText('Active');
});
```

---

## Deployment

### License API Server

**Option 1: Traditional VPS**
- DigitalOcean Droplet ($12/mo)
- Linode Nanode ($5/mo)
- Nginx + PM2 for Node.js
- PostgreSQL database

**Option 2: Platform-as-a-Service**
- Railway.app
- Render.com
- Fly.io
- Heroku

**Option 3: Serverless**
- AWS Lambda + API Gateway + RDS
- Google Cloud Run + Cloud SQL
- Azure Functions + Cosmos DB

### WordPress Plugin

- WordPress.org Plugin Directory (free version)
- Direct distribution for Pro version
- Auto-updates via plugin update API

---

## Cost Estimates

### MVP (Phase 1)

| Service | Cost/Month |
|---------|------------|
| License API Server (Railway/Render) | $7-15 |
| PostgreSQL Database | Included |
| Stripe Fees (2.9% + $0.30) | Per transaction |
| Email Service (SendGrid Free Tier) | $0 |
| Domain (license.thedot.com) | $1 |
| **Total** | **$8-16/mo** |

### Scale (500 active licenses)

| Service | Cost/Month |
|---------|------------|
| License API Server (scaled) | $20-30 |
| Database (managed Postgres) | $15-25 |
| Stripe Fees | Variable |
| Email Service (SendGrid) | $15 |
| Monitoring (Sentry) | $0 (free tier) |
| **Total** | **$50-70/mo** |

---

## Support & Troubleshooting

### Common Issues

**Issue 1: "License activation failed - connection error"**
- Check firewall allows outbound HTTPS
- Verify license.thedot.com is accessible
- Check server isn't blocking wp_remote_post()

**Issue 2: "Activation limit reached"**
- User needs to deactivate on another site
- Or upgrade to plan with more activations
- Admin can manually increase limit in database

**Issue 3: "License expired but user says they paid"**
- Check Stripe webhook delivery
- Manually verify payment in Stripe Dashboard
- May need to manually extend expires_at

### Admin Tools

Build admin dashboard for support team:
- Search licenses by email/key
- View activation history
- Manually activate/deactivate
- Extend expiration dates
- Refund handling

---

## References

- [Stripe API Documentation](https://stripe.com/docs/api)
- [Stripe Webhooks Guide](https://stripe.com/docs/webhooks)
- [WordPress Plugin Handbook - Licensing](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#6-software-licensing)
- [Best Practices for License Keys](https://www.keygen.sh/blog/5-tips-for-generating-license-keys/)

---

## Questions?

Contact: dev@thedot.com
