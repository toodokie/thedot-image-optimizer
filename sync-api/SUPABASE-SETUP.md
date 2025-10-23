# Supabase Setup Guide - MSH Image Optimizer Sync

**Purpose:** Deploy metadata sync infrastructure for Phase 5+9
**Time:** 30-60 minutes
**Prerequisites:** Supabase account, Node.js 18+, Supabase CLI

---

## Step 1: Create Supabase Project

### 1.1 Create Project via Dashboard

1. Go to https://supabase.com/dashboard
2. Click "New Project"
3. Fill in details:
   - **Name:** `msh-optimizer-sync`
   - **Database Password:** Generate strong password (save to 1Password)
   - **Region:** Choose closest to your users (e.g., `us-east-1`)
   - **Pricing Plan:** Start with Free tier (upgrade to Pro when needed)

4. Wait 2-3 minutes for project provisioning

### 1.2 Save Project Details

From project settings, save these values:

```bash
# Project URL
SUPABASE_URL=https://your-project-id.supabase.co

# API Keys
SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

# Database Connection
DB_HOST=db.your-project-id.supabase.co
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres
DB_PASSWORD=your-strong-password
```

---

## Step 2: Run Database Migration

### 2.1 Connect via Supabase CLI

```bash
# Install Supabase CLI (if not installed)
npm install -g supabase

# Login to Supabase
supabase login

# Link to your project
cd sync-api
supabase link --project-ref your-project-id
```

### 2.2 Run Migration Script

```bash
# Option 1: Via SQL Editor in Supabase Dashboard
# 1. Go to SQL Editor in dashboard
# 2. Paste contents of db/migrations/0001_init.sql
# 3. Click "Run"

# Option 2: Via psql (local PostgreSQL client)
PGPASSWORD=your-db-password psql \
  -h db.your-project-id.supabase.co \
  -U postgres \
  -d postgres \
  -f db/migrations/0001_init.sql

# Option 3: Via Supabase CLI
supabase db push
```

### 2.3 Verify Migration

```bash
# Check tables were created
supabase db diff --linked

# Or query directly
psql -h db.your-project-id.supabase.co -U postgres -d postgres -c "\dt"

# Expected output:
#  public | licenses        | table | postgres
#  public | sites           | table | postgres
#  public | media_metadata  | table | postgres
#  public | sync_operations | table | postgres
#  public | quota_usage     | table | postgres
```

---

## Step 3: Set Up Row-Level Security (RLS)

The migration script already includes RLS policies, but verify they're enabled:

```sql
-- Run in SQL Editor
SELECT schemaname, tablename, rowsecurity
FROM pg_tables
WHERE schemaname = 'public';

-- All tables should show rowsecurity = true
```

If RLS is not enabled, run:

```sql
ALTER TABLE licenses ENABLE ROW LEVEL SECURITY;
ALTER TABLE sites ENABLE ROW LEVEL SECURITY;
ALTER TABLE media_metadata ENABLE ROW LEVEL SECURITY;
ALTER TABLE sync_operations ENABLE ROW LEVEL SECURITY;
ALTER TABLE quota_usage ENABLE ROW LEVEL SECURITY;
```

---

## Step 4: Deploy Edge Functions

### 4.1 Install Dependencies

```bash
cd sync-api/supabase/functions

# Each function has its own deps (Deno)
# No npm install needed - Deno handles imports
```

### 4.2 Deploy Functions

```bash
# Deploy all functions at once
supabase functions deploy handshake
supabase functions deploy push
supabase functions deploy pull
supabase functions deploy resolve
supabase functions deploy quota

# Or deploy individually
supabase functions deploy handshake --project-ref your-project-id
```

### 4.3 Verify Deployment

```bash
# List deployed functions
supabase functions list

# Expected output:
# NAME       STATUS   VERSION
# handshake  ACTIVE   1
# push       ACTIVE   1
# pull       ACTIVE   1
# resolve    ACTIVE   1
# quota      ACTIVE   1
```

### 4.4 Test Function Endpoints

```bash
# Test handshake (should return 400 - missing API key)
curl https://your-project-id.supabase.co/functions/v1/handshake

# Expected: {"error": {"code": "MISSING_API_KEY", ...}}
```

---

## Step 5: Configure Environment Variables

### 5.1 Set Function Secrets

```bash
# Set Supabase service role key for functions
supabase secrets set SUPABASE_SERVICE_ROLE_KEY=your-service-role-key

# Set JWT secret for token validation
supabase secrets set JWT_SECRET=your-jwt-secret

# Verify secrets
supabase secrets list
```

### 5.2 Update WordPress Plugin Config

Edit WordPress plugin settings:

```php
// wp-content/plugins/msh-image-optimizer/includes/enterprise/class-msh-remote-sync.php

// Update sync server URL
private $sync_server = 'https://your-project-id.supabase.co/functions/v1';
```

Or set via wp-config.php:

```php
// wp-config.php
define('MSH_SYNC_API_URL', 'https://your-project-id.supabase.co/functions/v1');
define('MSH_SYNC_API_KEY', 'your-license-key'); // From licenses table
```

---

## Step 6: Seed Test Data

### 6.1 Create Test License

```sql
-- Run in SQL Editor
INSERT INTO licenses (license_key, plan, status, max_sites, quota_sync_ops_monthly)
VALUES
  ('test-license-key-12345', 'pro', 'active', 5, 50000);
```

### 6.2 Register Test Site (via handshake)

```bash
# Test handshake endpoint
curl -X POST https://your-project-id.supabase.co/functions/v1/handshake \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "test-license-key-12345",
    "url": "https://example.com",
    "platform": "wordpress",
    "plugin_version": "2.0.0",
    "capabilities": ["field-diff", "batch-500"]
  }'

# Expected response:
# {
#   "site_id": "550e8400-e29b-41d4-a716-446655440000",
#   "license": {
#     "plan": "pro",
#     "max_sites": 5,
#     "quota_remaining": 50000
#   }
# }
```

---

## Step 7: Test End-to-End Sync

### 7.1 Push Metadata from WordPress

```bash
# In WordPress admin, update an image's metadata
# Or via WP-CLI:
wp post meta update 123 _wp_attachment_image_alt "Test alt text"

# Check Supabase logs
supabase functions logs push --tail

# Verify data in database
SELECT * FROM media_metadata ORDER BY created_at DESC LIMIT 1;
```

### 7.2 Pull Metadata to Another Site

```bash
# On second WordPress site with same license
wp msh sync pull

# Or via REST API:
curl -X POST https://your-project-id.supabase.co/functions/v1/pull \
  -H "Content-Type: application/json" \
  -H "X-License-Key: test-license-key-12345" \
  -d '{
    "site_id": "550e8400-e29b-41d4-a716-446655440000",
    "since": "2025-10-22T00:00:00Z"
  }'
```

---

## Step 8: Monitor & Optimize

### 8.1 Enable Analytics

1. Go to **Settings → API** in Supabase dashboard
2. Enable **Analytics** (free on Pro plan)
3. Monitor:
   - Request volume
   - Error rates
   - Response times
   - Database queries

### 8.2 Set Up Alerts

```bash
# Create alert for quota limits (via Supabase dashboard)
# Alert when monthly quota >80% used
SELECT license_key,
       SUM(operation_count) as used,
       l.quota_sync_ops_monthly as max
FROM quota_usage q
JOIN licenses l ON q.license_key = l.license_key
WHERE q.month = EXTRACT(MONTH FROM NOW())
GROUP BY license_key, l.quota_sync_ops_monthly
HAVING SUM(operation_count) > l.quota_sync_ops_monthly * 0.8;
```

### 8.3 Enable Backup

```bash
# Automatic daily backups (Pro plan)
# Configure in Settings → Database → Backups
# Retention: 7 days (free), 30 days (Pro)
```

---

## Step 9: Production Checklist

Before going live:

- [ ] **Database migration** completed successfully
- [ ] **RLS policies** enabled and tested
- [ ] **Edge Functions** deployed and responding
- [ ] **Environment variables** set correctly
- [ ] **Test license** created and verified
- [ ] **WordPress plugin** updated with API URL
- [ ] **End-to-end sync** tested (2 sites)
- [ ] **Quota tracking** working
- [ ] **Error handling** tested (invalid license, quota exceeded)
- [ ] **Monitoring** enabled
- [ ] **Backups** configured
- [ ] **Documentation** updated with production URLs

---

## Costs & Scaling

### Free Tier Limits
- **Database:** 500 MB storage
- **Bandwidth:** 5 GB/month
- **Edge Functions:** 500K invocations/month
- **Row-Level Security:** Included

**Estimate:** Supports ~100-200 active sites on Free tier

### Pro Plan ($25/month)
- **Database:** 8 GB storage
- **Bandwidth:** 250 GB/month
- **Edge Functions:** 2M invocations/month
- **Point-in-time recovery:** 7 days
- **Daily backups:** 7 days retention

**Estimate:** Supports ~1,500-2,000 active sites

### When to Upgrade
- **Free → Pro:** When you hit 100 active installs or 5 GB bandwidth
- **Supabase → Google Cloud:** When you hit 2,000 installs or need multi-region

---

## Troubleshooting

### Issue: Migration Fails with Permission Error

```bash
# Grant necessary permissions
GRANT ALL ON ALL TABLES IN SCHEMA public TO postgres;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO postgres;
```

### Issue: Edge Function Returns 500

```bash
# Check function logs
supabase functions logs <function-name> --tail

# Common causes:
# - Missing SUPABASE_SERVICE_ROLE_KEY secret
# - Invalid SQL query syntax
# - Network timeout (increase timeout in function)
```

### Issue: RLS Blocking Queries

```bash
# Disable RLS temporarily for debugging
ALTER TABLE media_metadata DISABLE ROW LEVEL SECURITY;

# Re-enable after testing
ALTER TABLE media_metadata ENABLE ROW LEVEL SECURITY;
```

### Issue: WordPress Plugin Can't Connect

```bash
# Test API endpoint directly
curl https://your-project-id.supabase.co/functions/v1/handshake

# Check WordPress error log
tail -f wp-content/debug.log

# Verify API URL in plugin settings
wp option get msh_sync_server_url
```

---

## Next Steps

1. **Deploy to production** (follow checklist above)
2. **Test with real WordPress sites** (2-3 sites with same license)
3. **Monitor for 1 week** (check logs, quota usage, errors)
4. **Gather feedback** (support tickets, feature requests)
5. **Iterate** (improve error messages, add features)

Once stable, consider:
- Adding **webhook notifications** (site disconnected, quota exceeded)
- Building **admin dashboard** (view all sites, usage stats)
- Implementing **conflict resolution UI** (show diffs, manual merge)

---

## Resources

- [Supabase Documentation](https://supabase.com/docs)
- [Edge Functions Guide](https://supabase.com/docs/guides/functions)
- [Row-Level Security](https://supabase.com/docs/guides/auth/row-level-security)
- [OpenAPI Specification](./openapi/sync-v1.yaml)
- [Hybrid Architecture Docs](./docs/HYBRID-ARCHITECTURE.md)
