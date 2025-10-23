# Hybrid Architecture: Metadata Now, Images Later

**Status:** Phase 5+9 (Metadata Sync) ✅ | Phase 10 (AVIF/Images) 🔮
**Last Updated:** October 22, 2025
**Purpose:** Document the staged cloud build-out strategy

---

## TL;DR

We're building cloud infrastructure in **2 stages** to ship fast while staying AVIF-ready:

| **Stage** | **What** | **Timeline** | **Status** |
|-----------|----------|--------------|------------|
| **Phase 5+9** | Metadata sync only (JSON) | Now (1-2 weeks) | 🚧 In Progress |
| **Phase 10** | Image storage + AVIF/WebP conversion | Later (3-4 months) | 🔮 Planned |

**Key Decision:** Build the metadata sync API with **AVIF-ready placeholders** so we don't have to rebuild when Phase 10 arrives.

---

## Why Hybrid?

### The Problem
- **Metadata sync** is urgent (Phase 5+9 Pro feature)
- **AVIF conversion** is valuable but complex (Phase 10)
- Building both together = 6-8 weeks (too slow)

### The Solution
1. **Ship metadata sync now** (1-2 weeks)
2. **Reserve storage fields** in schema for future images
3. **Add stub image endpoint** that returns `501 Not Implemented`
4. **Plug in AVIF later** without breaking existing clients

---

## Architecture Overview

```
┌────────────────────────────────────────────────────────┐
│                   CLOUD SERVICES                        │
├────────────────────────────────────────────────────────┤
│                                                          │
│  sync.thedot.com (Phase 5+9) ← BUILD THIS NOW          │
│  ├─ /handshake                                          │
│  ├─ /sync/push       ← Metadata JSON only              │
│  ├─ /sync/pull       ← No images yet                   │
│  ├─ /sync/resolve                                       │
│  └─ /quota                                              │
│                                                          │
│  images.thedot.com (Phase 10) ← STUB FOR NOW           │
│  ├─ /image/upload    ← Returns 501 today               │
│  └─ /image/status    ← Will work in Phase 10           │
│                                                          │
│  cdn.thedot.com (Phase 10) ← NOT BUILT YET             │
│  └─ Delivers AVIF/WebP from GCS/Cloud Storage          │
│                                                          │
└────────────────────────────────────────────────────────┘
           ▲                    ▲                 ▲
           │                    │                 │
     WordPress Plugin    Shopify Plugin    Webflow Plugin
     (uses sync only)    (future)          (future)
```

---

## Domain Separation Strategy

| **Domain** | **Purpose** | **Backend** | **Phase** | **Status** |
|------------|-------------|-------------|-----------|------------|
| `sync.thedot.com` | Metadata sync (JSON) | Supabase Edge Functions → Cloud Run | 5+9 | 🚧 Building |
| `images.thedot.com` | Image upload + processing | GCS + Cloud Functions → ImageKit | 10 | 🔮 Stub only |
| `cdn.thedot.com` | Image delivery (AVIF/WebP) | Cloud CDN or ImageKit | 10 | 🔮 Planned |
| `license.thedot.com` | License validation + JWT | Cloud Run | 6 | ⏸️ Pending |

**Migration Path:**
- **Phase 1:** Supabase (Postgres + Edge Functions)
- **Phase 2:** Google Cloud (Cloud SQL + Cloud Run)
- **Transition:** Blue/green cutover via Nginx reverse proxy

**Why Separate?**
- Clean separation of concerns
- Independent scaling (sync is light, images are heavy)
- Can swap backends without affecting other services
- Supports multi-platform clients (WordPress, Shopify, etc.)

---

## Database Schema (AVIF-Ready)

### Current Implementation (Phase 5+9)

```sql
create table media_metadata (
  id uuid primary key default gen_random_uuid(),
  site_id uuid references sites(site_id) on delete cascade,
  media_id bigint not null,           -- WordPress attachment ID
  locale text not null default 'en',

  -- Metadata fields (ACTIVE NOW)
  title text,
  alt text,
  caption text,
  description text,
  custom jsonb,                       -- Custom metadata (JSON)

  -- Image storage (PLACEHOLDER - Phase 10)
  storage jsonb,                      -- ⚠️ Reserved, currently NULL

  -- Versioning
  rev bigint not null default 1,
  updated_at timestamptz not null default now(),

  unique(site_id, media_id, locale)
);
```

### Storage Field Structure (Phase 10)

**Example value when AVIF is implemented:**

```json
{
  "original": "gs://dot-images/original/8c5bffbe6f13.jpg",
  "avif": "gs://dot-images/avif/8c5bffbe6f13.avif",
  "webp": "gs://dot-images/webp/8c5bffbe6f13.webp",
  "jpeg": "gs://dot-images/jpeg/8c5bffbe6f13.jpg",
  "status": "complete",
  "cdn_url": "https://cdn.thedot.com/8c5bffbe6f13"
}
```

**Today (Phase 5+9):** `storage` is always `NULL`
**Phase 10:** Populated by image processor after upload

---

## API Contract (Metadata + Storage Placeholder)

### Push Metadata (Works Today)

```http
POST /sync/push
Content-Type: application/json
Authorization: Bearer <jwt>

{
  "site_id": "550e8400-e29b-41d4-a716-446655440000",
  "changes": [
    {
      "media_id": 1234,
      "locale": "en_US",
      "base_rev": 5,
      "fields": {
        "title": "Beautiful sunset over mountains",
        "alt": "Sunset landscape photo",
        "caption": "Taken in Colorado, USA",
        "description": "Professional landscape photography",
        "storage": null  // ← Always null in Phase 5+9
      }
    }
  ]
}
```

### Upload Image (Stub in Phase 5+9)

```http
POST /image/upload
Content-Type: multipart/form-data
Authorization: Bearer <jwt>

image=<binary>
media_hash=8c5bffbe6f13a2d4
locale=en_US
```

**Response (Phase 5+9):**
```json
{
  "error": {
    "code": "NOT_IMPLEMENTED",
    "message": "Image storage not yet enabled. Currently metadata-only sync."
  }
}
```

**Response (Phase 10):**
```json
{
  "status": "accepted",
  "media_hash": "8c5bffbe6f13a2d4",
  "processing_url": "/image/status/8c5bffbe6f13a2d4"
}
```

---

## Phase 10 Integration (Future)

### How AVIF Will Work

1. **WordPress plugin uploads image:**
   ```php
   $response = wp_remote_post('https://images.thedot.com/image/upload', [
       'body' => [
           'image' => $image_data,
           'media_hash' => $hash,
       ]
   ]);
   ```

2. **Cloud processor converts asynchronously:**
   - Original → Store in GCS
   - Convert to AVIF → Store in GCS
   - Convert to WebP → Store in GCS
   - Optimize JPEG → Store in GCS

3. **Processor updates metadata storage field:**
   ```sql
   update media_metadata
   set storage = '{"original": "gs://...", "avif": "gs://...", ...}'::jsonb
   where media_hash = '8c5bffbe6f13a2d4';
   ```

4. **Plugin pulls updated metadata:**
   ```php
   $metadata = $sync->pull_changes();
   // $metadata['storage']['avif'] now contains URL
   ```

5. **Plugin renders with `<picture>` tag:**
   ```html
   <picture>
     <source type="image/avif" srcset="https://cdn.thedot.com/...avif">
     <source type="image/webp" srcset="https://cdn.thedot.com/...webp">
     <img src="https://cdn.thedot.com/...jpg" alt="...">
   </picture>
   ```

---

## What Changes When Adding AVIF?

### No Changes Needed ✅
- ✅ Database schema (storage field already exists)
- ✅ API contract (storage field already in OpenAPI spec)
- ✅ WordPress plugin sync logic (already sends/receives storage)
- ✅ Domain structure (images.thedot.com reserved)

### What We Add 🔨
- 🔨 Implement `/image/upload` (replace 501 with real logic)
- 🔨 Build image processor (Cloud Function or ImageKit integration)
- 🔨 Set up GCS buckets + CDN
- 🔨 Update WordPress plugin to actually upload images
- 🔨 Add `<picture>` tag generation in WordPress

**Estimated Effort:** 2-3 weeks (not a rebuild!)

---

## Testing Strategy

### Phase 5+9 (Now)
```bash
# Test metadata sync
curl -X POST https://sync.thedot.com/api/v1/sync/push \
  -H "Authorization: Bearer <jwt>" \
  -d '{"site_id":"...","changes":[{...,"storage":null}]}'

# Verify storage is always null
SELECT media_id, storage FROM media_metadata;
# storage | NULL

# Test image upload stub
curl -X POST https://images.thedot.com/image/upload \
  -F "image=@test.jpg" -F "media_hash=abc123"
# Response: 501 Not Implemented ✓
```

### Phase 10 (Future)
```bash
# Upload image
curl -X POST https://images.thedot.com/image/upload \
  -F "image=@test.jpg" -F "media_hash=abc123"
# Response: 202 Accepted ✓

# Check processing status
curl https://images.thedot.com/image/status/abc123
# Response: {"status":"complete","storage":{...}}

# Verify storage populated
SELECT media_id, storage FROM media_metadata WHERE media_hash = 'abc123';
# storage | {"original":"gs://...","avif":"gs://..."}
```

---

## Documentation Checklist

When implementing Phase 10, update:

- [ ] `/sync-api/openapi/sync-v1.yaml` - Remove 501 from `/image/upload`
- [ ] `/sync-api/docs/HYBRID-ARCHITECTURE.md` (this file) - Update status from 🔮 to ✅
- [ ] `/docs/PROJECT-STATUS-ALL-PHASES.md` - Mark Phase 10 as complete
- [ ] `/docs/RND-002-RESEARCH-IDEAS.md` - Update Idea #3 (AVIF) status
- [ ] WordPress plugin `class-msh-remote-sync.php` - Add image upload logic
- [ ] Main plugin docs `MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md` - Document AVIF feature

---

## Key Takeaways

1. **We're building metadata sync NOW** (1-2 weeks)
2. **Database schema is AVIF-ready** (storage field reserved)
3. **API includes stub image endpoint** (returns 501 today)
4. **No rebuild needed for Phase 10** (just implement the stubs)
5. **Domain separation allows independent scaling**
6. **Google Cloud migration path is clear** (Supabase → Cloud Run)

**Bottom Line:** Ship fast today, extend easily tomorrow. The foundation is portable and future-proof.

---

## Related Documentation

- **OpenAPI Spec:** `../openapi/sync-v1.yaml`
- **Database Migrations:** `../db/migrations/` (to be created)
- **AVIF Research:** `/docs/RND-002-RESEARCH-IDEAS.md` (Idea #3)
- **Cloud Architecture:** `/docs/RND-002-RESEARCH-IDEAS.md` (Idea #4)
- **Project Status:** `/docs/PROJECT-STATUS-ALL-PHASES.md`

---

**Next Step:** Create database migrations with storage field ✅
