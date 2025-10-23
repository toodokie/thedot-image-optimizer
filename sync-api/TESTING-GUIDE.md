# Supabase Sync API - Testing Guide

**Purpose:** Test the deployed sync infrastructure end-to-end
**Prerequisites:** Supabase project deployed, Edge Functions live

---

## Quick Test Commands

### 1. Test Handshake (Site Registration)

```bash
curl -X POST https://your-project-id.supabase.co/functions/v1/handshake \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "test-license-key-12345",
    "url": "https://example.com",
    "platform": "wordpress",
    "plugin_version": "2.0.0",
    "wp_version": "6.4.1",
    "capabilities": ["field-diff", "batch-500"]
  }'

# Expected response:
# {
#   "site_id": "550e8400-e29b-41d4-a716-446655440000",
#   "license": {
#     "plan": "pro",
#     "status": "active",
#     "max_sites": 5,
#     "quota_remaining": 50000
#   },
#   "message": "Site registered successfully"
# }
```

### 2. Test Push (Upload Metadata)

```bash
curl -X POST https://your-project-id.supabase.co/functions/v1/push \
  -H "Content-Type: application/json" \
  -H "X-License-Key: test-license-key-12345" \
  -d '{
    "site_id": "550e8400-e29b-41d4-a716-446655440000",
    "changes": [
      {
        "media_id": 123,
        "locale": "en",
        "title": "Beautiful Sunset",
        "alt": "Sunset over mountains",
        "caption": "A stunning sunset view",
        "description": "Captured during our summer trip",
        "rev": 1
      }
    ]
  }'

# Expected response:
# {
#   "pushed": 1,
#   "conflicts": []
# }
```

### 3. Test Pull (Download Metadata)

```bash
curl -X POST https://your-project-id.supabase.co/functions/v1/pull \
  -H "Content-Type: application/json" \
  -H "X-License-Key: test-license-key-12345" \
  -d '{
    "site_id": "another-site-id",
    "limit": 10
  }'

# Expected response:
# {
#   "changes": [
#     {
#       "media_id": 123,
#       "locale": "en",
#       "title": "Beautiful Sunset",
#       "alt": "Sunset over mountains",
#       "caption": "A stunning sunset view",
#       "description": "Captured during our summer trip",
#       "custom": {},
#       "rev": 1,
#       "updated_at": "2025-10-22T20:15:00Z"
#     }
#   ],
#   "next_cursor": null,
#   "has_more": false
# }
```

### 4. Test Quota Check

```bash
curl -X GET https://your-project-id.supabase.co/functions/v1/quota \
  -H "X-License-Key: test-license-key-12345"

# Expected response:
# {
#   "license_key": "test-license-key-12345",
#   "plan": "pro",
#   "quota": {
#     "monthly_limit": 50000,
#     "used": 2,
#     "remaining": 49998,
#     "percentage_used": 0.004,
#     "reset_date": "2025-11-01T00:00:00Z"
#   },
#   "sites": {
#     "current": 1,
#     "max": 5
#   }
# }
```

### 5. Test Conflict Resolution

```bash
curl -X POST https://your-project-id.supabase.co/functions/v1/resolve \
  -H "Content-Type: application/json" \
  -H "X-License-Key: test-license-key-12345" \
  -d '{
    "site_id": "550e8400-e29b-41d4-a716-446655440000",
    "resolutions": [
      {
        "media_id": 123,
        "locale": "en",
        "strategy": "manual",
        "manual_value": {
          "title": "Merged Title - Final Version",
          "alt": "Merged alt text"
        }
      }
    ]
  }'

# Expected response:
# {
#   "resolved": 1,
#   "failed": []
# }
```

---

## WordPress Integration Testing

### 1. Install Plugin with Sync Enabled

```bash
# Via WordPress admin
# 1. Upload plugin ZIP
# 2. Activate plugin
# 3. Go to Settings → MSH Optimizer → Account tab
# 4. Enter license key: test-license-key-12345
# 5. Click "Activate License"
```

### 2. Test Automatic Sync

```bash
# Via WordPress admin
# 1. Go to Media Library
# 2. Edit an image
# 3. Update alt text or title
# 4. Save changes

# Check if sync happened:
wp option get msh_last_sync_time

# Should show recent timestamp
```

### 3. Test Manual Sync via WP-CLI

```bash
# Trigger manual sync
wp msh sync now

# Expected output:
# Success: Sync completed. Pushed: 5, Pulled: 3
```

### 4. Test Multi-Site Sync

**Site A (push):**
```bash
# Update image metadata
wp post meta update 123 _wp_attachment_image_alt "New alt text from Site A"

# Trigger sync
wp msh sync now

# Output: Pushed: 1, Pulled: 0
```

**Site B (pull):**
```bash
# Pull changes
wp msh sync now

# Output: Pushed: 0, Pulled: 1

# Verify change
wp post meta get 123 _wp_attachment_image_alt

# Should show: "New alt text from Site A"
```

---

## Database Verification

### Check Synced Metadata

```sql
-- Run in Supabase SQL Editor
SELECT
  m.media_id,
  m.locale,
  m.title,
  m.alt,
  m.rev,
  m.updated_at,
  s.url as site_url
FROM media_metadata m
JOIN sites s ON m.site_id = s.site_id
ORDER BY m.updated_at DESC
LIMIT 10;
```

### Check Sync Operations Log

```sql
SELECT
  o.operation_type,
  o.items_count,
  o.conflicts_count,
  o.created_at,
  s.url as site_url
FROM sync_operations o
JOIN sites s ON o.site_id = s.site_id
ORDER BY o.created_at DESC
LIMIT 20;
```

### Check Quota Usage

```sql
SELECT
  q.license_key,
  q.month,
  q.operation_count,
  l.quota_sync_ops_monthly as limit,
  l.plan
FROM quota_usage q
JOIN licenses l ON q.license_key = l.license_key
ORDER BY q.month DESC;
```

---

## Error Testing

### Test Invalid License

```bash
curl -X POST https://your-project-id.supabase.co/functions/v1/handshake \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "invalid-license",
    "url": "https://example.com",
    "plugin_version": "2.0.0"
  }'

# Expected: 403 Forbidden
# {"error": {"code": "INVALID_LICENSE", "message": "License key not found or invalid"}}
```

### Test Quota Exceeded

```bash
# Manually set quota to 0 in database
UPDATE quota_usage
SET operation_count = 50000
WHERE license_key = 'test-license-key-12345'
  AND month = '2025-10';

# Try to push
curl -X POST https://your-project-id.supabase.co/functions/v1/push \
  -H "Content-Type: application/json" \
  -H "X-License-Key: test-license-key-12345" \
  -d '{"site_id": "...", "changes": [...]}'

# Expected: 402 Payment Required
# {"error": {"code": "QUOTA_EXCEEDED", "message": "Monthly quota exceeded: 50000/50000"}}
```

### Test Conflict Detection

```bash
# 1. Push metadata with rev=1
curl -X POST .../push \
  -d '{"site_id": "...", "changes": [{"media_id": 123, "locale": "en", "title": "V1", "rev": 1}]}'

# 2. Try to push again with rev=1 (stale)
curl -X POST .../push \
  -d '{"site_id": "...", "changes": [{"media_id": 123, "locale": "en", "title": "V2", "rev": 1}]}'

# Expected: pushed=0, conflicts=[{"media_id": 123, "locale": "en", "cloud_rev": 2, "local_rev": 1}]
```

---

## Performance Testing

### Test Large Batch Push

```bash
# Generate 100 metadata changes
node -e "
const changes = [];
for (let i = 1; i <= 100; i++) {
  changes.push({
    media_id: i,
    locale: 'en',
    title: \`Image \${i}\`,
    alt: \`Alt text \${i}\`,
    rev: 1
  });
}
console.log(JSON.stringify({site_id: '...', changes}));
" | curl -X POST .../push \
  -H "Content-Type: application/json" \
  -H "X-License-Key: test-license-key-12345" \
  -d @-

# Check response time (should be <5 seconds)
```

### Test Cursor Pagination

```bash
# Push 150 items (more than limit=100)
# ... (generate 150 items) ...

# Pull with cursor
curl -X POST .../pull \
  -d '{"site_id": "...", "limit": 100}'

# Response will have next_cursor and has_more=true

# Pull next page
curl -X POST .../pull \
  -d '{"site_id": "...", "limit": 100, "cursor": "2025-10-22T20:00:00Z"}'

# Response will have remaining 50 items
```

---

## Success Criteria

- [ ] **Handshake** works (site registered)
- [ ] **Push** works (metadata uploaded)
- [ ] **Pull** works (metadata downloaded)
- [ ] **Quota** tracking works (increments correctly)
- [ ] **Conflicts** detected (optimistic locking)
- [ ] **Resolve** works (manual strategy)
- [ ] **Multi-site** sync works (2 WordPress sites)
- [ ] **Error handling** works (invalid license, quota exceeded)
- [ ] **Performance** acceptable (<3s for 100 items)
- [ ] **Pagination** works (cursor-based)

---

## Troubleshooting

### Function Logs

```bash
# View real-time logs
supabase functions logs <function-name> --tail

# View specific function
supabase functions logs push --tail

# Search for errors
supabase functions logs push | grep ERROR
```

### Database Debugging

```bash
# Enable query logging (SQL Editor)
ALTER DATABASE postgres SET log_statement = 'all';

# View recent queries
SELECT * FROM pg_stat_statements ORDER BY calls DESC LIMIT 20;
```

### WordPress Debugging

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Check logs
tail -f wp-content/debug.log | grep MSH
```

---

## Next Steps

Once all tests pass:
1. **Load test** with 10+ sites syncing simultaneously
2. **Stress test** quota limits (push to 80% quota)
3. **Monitor** for 1 week in production
4. **Gather feedback** from beta users
5. **Iterate** based on real-world usage

---

## Resources

- [Supabase Functions Docs](https://supabase.com/docs/guides/functions)
- [OpenAPI Specification](./openapi/sync-v1.yaml)
- [Setup Guide](./SUPABASE-SETUP.md)
- [Hybrid Architecture](./docs/HYBRID-ARCHITECTURE.md)
