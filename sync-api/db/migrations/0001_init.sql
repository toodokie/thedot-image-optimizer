-- MSH Image Optimizer Sync - Initial Schema
-- Migration: 0001_init
-- Created: 2025-10-22
-- Purpose: Core schema for metadata sync (Phase 5+9) with AVIF-ready storage placeholders
--
-- Portability: Pure PostgreSQL (works on Supabase and Google Cloud SQL)
-- No Supabase-specific extensions used

-- ============================================================================
-- Table: licenses
-- Purpose: License key registration and plan tracking
-- ============================================================================

create table if not exists licenses (
  license_key text primary key,
  plan text not null check (plan in ('free', 'pro', 'enterprise')),
  status text not null check (status in ('active', 'suspended', 'expired', 'cancelled')),
  max_sites integer not null default 1,
  quota_sync_ops_monthly integer not null default 10000,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  expires_at timestamptz
);

comment on table licenses is 'License key management for multi-site sync';
comment on column licenses.plan is 'Subscription tier: free (metadata only), pro (sync+images), enterprise (unlimited)';
comment on column licenses.quota_sync_ops_monthly is 'Maximum sync operations (push+pull) per month';

create index idx_licenses_status on licenses(status);
create index idx_licenses_expires on licenses(expires_at) where expires_at is not null;

-- ============================================================================
-- Table: sites
-- Purpose: WordPress/Shopify/Webflow site registration per license
-- ============================================================================

create table if not exists sites (
  site_id uuid primary key default gen_random_uuid(),
  license_key text not null references licenses(license_key) on delete cascade,
  url text not null,
  platform text not null default 'wordpress' check (platform in ('wordpress', 'shopify', 'webflow', 'api')),
  wp_version text,
  plugin_version text not null,
  capabilities jsonb not null default '[]'::jsonb,
  last_handshake_at timestamptz not null default now(),
  created_at timestamptz not null default now(),
  unique(license_key, url)
);

comment on table sites is 'Registered sites under each license';
comment on column sites.capabilities is 'Client feature flags: ["field-diff", "batch-500", "cursor-pull"]';
comment on column sites.platform is 'Platform type for future multi-platform support (Phase 10+)';

create index idx_sites_license on sites(license_key);
create index idx_sites_platform on sites(platform);
create index idx_sites_last_handshake on sites(last_handshake_at);

-- ============================================================================
-- Table: media_metadata
-- Purpose: Localized metadata with AVIF-ready storage placeholder
-- ============================================================================

create table if not exists media_metadata (
  id uuid primary key default gen_random_uuid(),
  site_id uuid not null references sites(site_id) on delete cascade,
  media_id bigint not null,
  locale text not null default 'en',

  -- Metadata fields (ACTIVE in Phase 5+9)
  title text,
  alt text,
  caption text,
  description text,
  custom jsonb default '{}'::jsonb,

  -- Image storage URLs (PLACEHOLDER for Phase 10 - AVIF/WebP)
  -- Currently always NULL. Will be populated when image processing is implemented.
  -- Structure: {"original": "gs://...", "avif": "gs://...", "webp": "gs://...", "status": "complete"}
  storage jsonb,

  -- Versioning and conflict resolution
  rev bigint not null default 1,
  updated_at timestamptz not null default now(),
  created_at timestamptz not null default now(),

  unique(site_id, media_id, locale)
);

comment on table media_metadata is 'Image metadata with versioning and multi-locale support';
comment on column media_metadata.media_id is 'WordPress attachment ID or platform-specific media identifier';
comment on column media_metadata.storage is '⚠️ AVIF-READY PLACEHOLDER: Reserved for future image storage URLs (Phase 10). Currently unused (NULL).';
comment on column media_metadata.rev is 'Revision number for optimistic locking and conflict resolution';
comment on column media_metadata.custom is 'Custom metadata fields (JSON) for extensibility';

create index idx_metadata_site_media on media_metadata(site_id, media_id);
create index idx_metadata_locale on media_metadata(locale);
create index idx_metadata_updated on media_metadata(updated_at desc);
create index idx_metadata_storage on media_metadata using gin(storage) where storage is not null; -- Future: fast lookup by storage status

-- ============================================================================
-- Table: sync_operations
-- Purpose: Track push/pull operations for quota enforcement and auditing
-- ============================================================================

create table if not exists sync_operations (
  id uuid primary key default gen_random_uuid(),
  license_key text not null references licenses(license_key) on delete cascade,
  site_id uuid not null references sites(site_id) on delete cascade,
  operation text not null check (operation in ('push', 'pull', 'resolve')),
  items_count integer not null default 0,
  conflicts_count integer not null default 0,
  idempotency_key text,
  created_at timestamptz not null default now()
);

comment on table sync_operations is 'Audit log of all sync operations for quota tracking and debugging';
comment on column sync_operations.idempotency_key is 'Client-provided key to prevent duplicate processing of same push';

create index idx_sync_ops_license on sync_operations(license_key, created_at desc);
create index idx_sync_ops_site on sync_operations(site_id, created_at desc);
create index idx_sync_ops_idem on sync_operations(idempotency_key) where idempotency_key is not null;

-- ============================================================================
-- Table: quota_usage
-- Purpose: Monthly quota consumption tracking (materialized for performance)
-- ============================================================================

create table if not exists quota_usage (
  license_key text not null references licenses(license_key) on delete cascade,
  period_start date not null,
  period_end date not null,
  sync_ops_used integer not null default 0,
  last_updated_at timestamptz not null default now(),
  primary key (license_key, period_start)
);

comment on table quota_usage is 'Monthly quota consumption cache for fast quota checks';
comment on column quota_usage.period_start is 'First day of the month (YYYY-MM-01)';
comment on column quota_usage.sync_ops_used is 'Total push+pull operations in this period';

create index idx_quota_period on quota_usage(period_start, period_end);

-- ============================================================================
-- Function: apply_metadata_change
-- Purpose: Upsert metadata with revision bump (used by Edge Functions)
-- ============================================================================

create or replace function apply_metadata_change(
  p_site_id uuid,
  p_media_id bigint,
  p_locale text,
  p_fields jsonb
) returns table(new_rev bigint) language plpgsql as $$
declare
  v_rev bigint;
begin
  -- Upsert with revision increment
  insert into media_metadata(site_id, media_id, locale, title, alt, caption, description, custom, storage, rev)
  values (
    p_site_id,
    p_media_id,
    p_locale,
    p_fields->>'title',
    p_fields->>'alt',
    p_fields->>'caption',
    p_fields->>'description',
    coalesce(p_fields->'custom', '{}'::jsonb),
    p_fields->'storage',  -- Will be NULL in Phase 5+9, populated in Phase 10
    1
  )
  on conflict (site_id, media_id, locale) do update set
    title = coalesce(excluded.title, media_metadata.title),
    alt = coalesce(excluded.alt, media_metadata.alt),
    caption = coalesce(excluded.caption, media_metadata.caption),
    description = coalesce(excluded.description, media_metadata.description),
    custom = media_metadata.custom || excluded.custom,
    storage = coalesce(excluded.storage, media_metadata.storage),  -- Preserve existing if NULL
    rev = media_metadata.rev + 1,
    updated_at = now()
  returning rev into v_rev;

  return query select v_rev;
end $$;

comment on function apply_metadata_change is 'Atomic upsert with revision bump for conflict detection';

-- ============================================================================
-- Function: get_quota_usage
-- Purpose: Fast quota check for current month
-- ============================================================================

create or replace function get_quota_usage(p_license_key text)
returns table(
  limit_monthly integer,
  used_monthly integer,
  reset_at timestamptz
) language plpgsql as $$
declare
  v_limit integer;
  v_used integer;
  v_period_start date;
  v_period_end date;
begin
  -- Get license quota limit
  select quota_sync_ops_monthly into v_limit
  from licenses
  where license_key = p_license_key;

  if not found then
    raise exception 'License not found: %', p_license_key;
  end if;

  -- Calculate current period
  v_period_start := date_trunc('month', now())::date;
  v_period_end := (date_trunc('month', now()) + interval '1 month' - interval '1 day')::date;

  -- Get or create usage record
  insert into quota_usage(license_key, period_start, period_end, sync_ops_used)
  values (p_license_key, v_period_start, v_period_end, 0)
  on conflict (license_key, period_start) do nothing;

  select sync_ops_used into v_used
  from quota_usage
  where license_key = p_license_key and period_start = v_period_start;

  return query select v_limit, v_used, (v_period_end + interval '1 day')::timestamptz;
end $$;

comment on function get_quota_usage is 'Returns current quota limit, usage, and reset date for license';

-- ============================================================================
-- RLS Policies (Row-Level Security)
-- Purpose: Enforce data isolation per license in Supabase
-- ============================================================================

-- Enable RLS on all tables
alter table licenses enable row level security;
alter table sites enable row level security;
alter table media_metadata enable row level security;
alter table sync_operations enable row level security;
alter table quota_usage enable row level security;

-- Licenses: Only readable by own license key from JWT
create policy licenses_by_license on licenses
  for select
  using (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

-- Sites: Only readable by own license
create policy sites_by_license on sites
  for select
  using (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

create policy sites_insert_by_license on sites
  for insert
  with check (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

create policy sites_update_by_license on sites
  for update
  using (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

-- Metadata: Only accessible by sites under same license
create policy metadata_by_license on media_metadata
  for select
  using (exists (
    select 1 from sites s
    where s.site_id = media_metadata.site_id
      and s.license_key = current_setting('request.jwt.claims', true)::json->>'license_key'
  ));

create policy metadata_insert_by_license on media_metadata
  for insert
  with check (exists (
    select 1 from sites s
    where s.site_id = media_metadata.site_id
      and s.license_key = current_setting('request.jwt.claims', true)::json->>'license_key'
  ));

create policy metadata_update_by_license on media_metadata
  for update
  using (exists (
    select 1 from sites s
    where s.site_id = media_metadata.site_id
      and s.license_key = current_setting('request.jwt.claims', true)::json->>'license_key'
  ));

-- Sync operations: Only own license
create policy sync_ops_by_license on sync_operations
  for select
  using (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

create policy sync_ops_insert_by_license on sync_operations
  for insert
  with check (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

-- Quota usage: Only own license
create policy quota_by_license on quota_usage
  for select
  using (license_key = current_setting('request.jwt.claims', true)::json->>'license_key');

-- ============================================================================
-- Seed Data (Development only - comment out for production)
-- ============================================================================

-- Example license for testing
insert into licenses(license_key, plan, status, max_sites, quota_sync_ops_monthly)
values ('dev-license-12345', 'pro', 'active', 5, 100000)
on conflict (license_key) do nothing;

comment on table licenses is 'Seed data: dev-license-12345 (pro plan) for testing';

-- ============================================================================
-- Migration Complete
-- ============================================================================

-- Log migration
do $$
begin
  raise notice 'Migration 0001_init complete';
  raise notice 'Schema version: Phase 5+9 (Metadata sync with AVIF placeholders)';
  raise notice 'Tables created: licenses, sites, media_metadata, sync_operations, quota_usage';
  raise notice 'RLS policies: ✅ Enabled for multi-tenant isolation';
  raise notice 'AVIF readiness: ✅ storage field reserved for Phase 10';
end $$;
