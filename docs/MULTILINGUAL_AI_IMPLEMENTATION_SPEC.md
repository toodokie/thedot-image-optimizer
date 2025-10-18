# Multilingual AI - Complete Implementation Specification

_Developer-ready specification for Phases 1-9 of the Multilingual AI metadata system_

---

## 1. Context Fusion Layer

### Purpose
Fuse multilingual content context with image signals so the AI knows why an image exists, not only what it shows.

### Inputs
- WP post object, taxonomies, post meta, featured image relationships
- Media item fields: filename, title, caption, alt, description
- Locale code. Example: `en-CA`, `fr-CA`, `es-MX`
- Site-level brand and location settings

### Outputs
- Context bundle per `media_id` per `locale`

### Data Model

**Table: `optimizer_context`**

```sql
CREATE TABLE optimizer_context (
    media_id BIGINT,
    locale VARCHAR(10),
    post_id BIGINT NULL,
    subject TEXT,
    intent ENUM('on_topic','off_topic','unknown'),
    entities JSON,           -- detected people, places, brands
    keywords JSON,           -- deduped, stemmed, per-locale
    source_hash CHAR(64),    -- hash of upstream content
    updated_at DATETIME,
    PRIMARY KEY(media_id, locale)
);
```

### Implementation

1. **Context extractor:**
   - Pull post title, H1, categories, tags, nearest heading if media is embedded
   - Normalize by locale
   - Extract entities. Keep brand, city, product lines as protected terms

2. **Intent classifier:**
   - Decide `on_topic` vs `off_topic` using a lightweight rule tree backed by an LLM check for edge cases
   - If no post link, mark `off_topic` unless filename strongly matches site niche

3. **Keyword normalizer:**
   - Stem and dedupe per locale
   - Maintain synonyms per locale

### Acceptance Tests

- ✅ Given a product page with localized titles, the context bundle includes subject, brand, and city in both `fr-CA` and `en-CA` with identical entity IDs
- ✅ Changing the post title invalidates `source_hash` and triggers a re-fuse

---

## 2. AI Translation and Cultural Adaptation

### Purpose
Generate natural, market-ready text per locale, not literal translations.

### Inputs
- Context bundle
- Protected glossary of brands and place names
- Locale tone profile

### Outputs
- Localized strings for filename base, title, alt, caption, description

### Data Model

**Table: `optimizer_locale_profiles`**

```sql
CREATE TABLE optimizer_locale_profiles (
    locale VARCHAR(10) PRIMARY KEY,
    tone JSON,                          -- formality, energy, sentence length
    title_pattern VARCHAR(128),         -- e.g. "{subject} | {city} - {brand}"
    should_translate_entities BOOLEAN,
    stoplist JSON,                      -- tokens never translated
    created_at DATETIME,
    updated_at DATETIME
);
```

### Implementation

1. Build a locale profile for each target
2. Create a protected glossary: brand names, product SKUs, city names in canonical form
3. **LLM prompt templates:**
   - System: apply locale profile, avoid literalism, follow SEO title pattern, never translate glossary terms
   - Provide examples per locale
4. Validate outputs: length, forbidden tokens, presence of subject

### Acceptance Tests

- ✅ "HVAC" in `fr-CA` becomes "climatisation" when context is repair, but "HVAC Pro" brand remains unchanged
- ✅ Title structures differ correctly between `en-CA` and `fr-CA`

---

## 3. Intelligent Metadata Orchestration

### Purpose
Centralize generation, versioning, and comparison of metadata.

### Inputs
- Context bundle and localized strings
- User edits from WP admin

### Outputs
- Versioned records and diff

### Data Model

**Table: `optimizer_metadata`**

```sql
CREATE TABLE optimizer_metadata (
    media_id BIGINT,
    locale VARCHAR(10),
    field ENUM('filename','title','alt','caption','description'),
    value TEXT,
    source ENUM('ai','template','manual','import'),
    version INT,
    checksum CHAR(64),
    created_at DATETIME,
    PRIMARY KEY(media_id, locale, field, version)
);
```

### Implementation

1. Write-once versions. The active version is the max version per key
2. Diff utility returns AI vs manual differences for review
3. Regeneration triggers on post update, locale added, or glossary change

### Acceptance Tests

- ✅ Manual edit increments version with `source=manual` and becomes active
- ✅ Regeneration does not overwrite manual unless user selects Replace

---

## 4. Performance and Batch Layer

### Purpose
Scale to thousands of media items safely.

### Inputs
- Batch selection rules
- Concurrency caps

### Outputs
- Resumable jobs, progress, and error logs

### Data Model

**Tables: `optimizer_jobs`, `optimizer_job_items`, `optimizer_logs`**

```sql
CREATE TABLE optimizer_jobs (
    job_id VARCHAR(64) PRIMARY KEY,
    type VARCHAR(32),
    locale VARCHAR(10),
    status ENUM('queued','running','paused','completed','failed','cancelled'),
    created_by BIGINT,
    created_at DATETIME,
    updated_at DATETIME,
    progress_pct DECIMAL(5,2),
    totals JSON
);

CREATE TABLE optimizer_job_items (
    job_id VARCHAR(64),
    media_id BIGINT,
    status ENUM('pending','processing','completed','failed','skipped'),
    attempt_count INT DEFAULT 0,
    last_error TEXT,
    updated_at DATETIME,
    PRIMARY KEY(job_id, media_id)
);

CREATE TABLE optimizer_logs (
    log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(64),
    level ENUM('debug','info','warning','error'),
    message TEXT,
    meta JSON,
    created_at DATETIME,
    INDEX(job_id),
    INDEX(created_at)
);
```

### Implementation

1. Use Action Scheduler or a custom queue with locking
2. Concurrency control per site: default 3 workers
3. Exponential backoff on LLM errors
4. Checkpoint every 25 items

### Acceptance Tests

- ✅ Killing PHP mid-run and resuming continues from the last checkpoint
- ✅ Large library of 5,000 items completes within configured rate limits without memory errors

---

## 5. Template Intelligence

### Purpose
Blend deterministic templates with AI reasoning for `on_topic` vs `off_topic`.

### Inputs
- Context bundle, locale profile, user template

### Outputs
- Selected template and filled fields

### Data Model

**Table: `optimizer_templates`**

```sql
CREATE TABLE optimizer_templates (
    template_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64),
    field ENUM('filename','title','alt','caption','description'),
    locale VARCHAR(10) NULL,         -- null means default
    pattern TEXT,                    -- e.g. "{subject} | {city} - {brand}"
    on_topic_only BOOLEAN,
    rules JSON,                      -- conditions for applicability
    created_at DATETIME
);
```

### Implementation

1. Resolve applicable templates in priority order: locale-specific, then default
2. If `on_topic` required but `intent=off_topic`, fall back to off_topic template
   - Example filename: `{subject}.ext` with no city or brand
3. Validate tokens are present before apply

### Acceptance Tests

- ✅ Off-topic image never receives city or brand in filename
- ✅ Missing subject prevents apply and logs a clear error

---

## 6. Multilingual Admin UX

### Purpose
Make operations visible and reversible inside WordPress.

### UI Requirements

- Locale switcher on Media detail
- Inline side-by-side view: AI vs Active vs Manual
- Batch selector with filters: locale, on_topic, missing fields
- One-click rollback to any version

### REST API Endpoints

```
GET  /wp-json/optimizer/v1/context?media_id=...&locale=...
POST /wp-json/optimizer/v1/generate   { media_ids, locale, fields }
POST /wp-json/optimizer/v1/activate   { media_id, locale, field, version }
POST /wp-json/optimizer/v1/rollback   { media_id, locale, field, version }
GET  /wp-json/optimizer/v1/jobs?status=...
```

### Acceptance Tests

- ✅ Editor can switch to `fr-CA` and approve AI alt for 20 images in one action
- ✅ Rollback restores the exact previous version value and updates the active pointer

---

## 7. Visibility and Analytics Layer

### Purpose
Measure impact on search and workflow.

### Inputs
- Timestamps of changes
- GSC or GA4 data pulled via service accounts
- Job logs

### Outputs
- Per-locale performance report

### Data Model

**Table: `optimizer_metrics`**

```sql
CREATE TABLE optimizer_metrics (
    media_id BIGINT,
    locale VARCHAR(10),
    field ENUM('filename','title','alt','caption','description'),
    activated_at DATETIME,
    clicks_28d_before INT,
    clicks_28d_after INT,
    impr_28d_before INT,
    impr_28d_after INT,
    avg_pos_before DECIMAL(5,2),
    avg_pos_after DECIMAL(5,2),
    updated_at DATETIME,
    PRIMARY KEY(media_id, locale, field, activated_at)
);
```

### Implementation

1. When a field becomes active, snapshot baseline metrics
2. After 28 days, pull comparison and store deltas
3. Report generator groups by locale and field, with top winners and laggards

### Acceptance Tests

- ✅ Activating new titles for `fr-CA` stores a dated baseline
- ✅ Report shows delta and links back to the exact version that was activated

---

## 8. Marketplace-Ready MVP

### Purpose
Prepare for private beta and external testers.

### Requirements

- License activation with remote key validation
- Telemetry with clear opt-in
- Onboarding wizard with locale selection, template defaults, and glossary upload
- Local JSON fallback if AI is unavailable

### Telemetry Schema

```json
{
  "site_id": "...",
  "plugin_version": "...",
  "wp_version": "...",
  "locales_enabled": ["en-CA", "fr-CA"],
  "features_used": ["ai_regen", "context_profiles"],
  "job_runs": {
    "count": 42,
    "avg_duration": 120
  },
  "errors": {
    "type": "api_timeout",
    "count": 3
  },
  "consent": true
}
```

### Acceptance Tests

- ✅ Plugin works in read-only mode when AI keys are missing and guides the user through onboarding
- ✅ Telemetry off by default in the EU unless explicitly enabled

---

## Cross-cutting Specifications

### A) Protected Glossary and Named Entities

- Maintain a `protected_terms` list per site
- Never translate tokens in this list
- Provide UI to add and remove items
- Unit tests cover edge cases like "Apple" the brand vs apple the fruit using entity tags

### B) Locale Tone Profiles

**Example `fr-CA` profile:**

```json
{
  "formality": "medium",
  "sentence_length": "short",
  "punctuation": "prefer_colon_and_pipe",
  "avoid_terms": ["HVAC"],
  "preferred_terms": {
    "HVAC": "climatisation"
  },
  "title_pattern": "{subject} | {city} - {brand}"
}
```

- Store in `optimizer_locale_profiles`
- Allow overrides per site

### C) Filename Normalizer

- ASCII-safe slug for filename base per locale
- Remove diacritics only when the OS or CDN demands it
- Validate against CDN length and character rules

### D) Regeneration Triggers

- Post title or taxonomy changes
- Locale added or removed
- Glossary updated
- Manual force regenerate

### E) Safety and Rate Limits

- Global token budget per minute
- Circuit breaker when error rate exceeds threshold
- Graceful degradation to templates-only mode

---

## Developer Prompts and Function Signatures

### LLM System Prompt Skeleton

```
You are the Multilingual Metadata Writer.
Follow the provided locale profile strictly.
Never translate protected terms.
Use the title pattern for the locale.
Prefer natural phrasing used by native speakers in the specified region.
Return JSON only with fields: filename_base, title, alt, caption, description.
```

### LLM User Prompt Skeleton

```
Locale: fr-CA
Context:
- Subject: "Technicians for residential AC repair"
- Brand: "Main Street Health"
- City: "Hamilton"
- Intent: on_topic
- Glossary: ["Main Street Health", "Hamilton"]

Constraints:
- 60 char title target, 110 alt target
- Do not include contact info
- Respect fr-CA tone profile

Return JSON.
```

### Server Functions

```php
generateLocalizedMetadata(media_id, locale, fields[]) -> { field:value }
fuseContext(media_id, locale) -> contextBundle
applyTemplate(contextBundle, field, locale) -> string
saveVersion(media_id, locale, field, value, source) -> version_id
activateVersion(media_id, locale, field, version_id)
enqueueJob(type, payload) -> job_id
```

---

## Example End-to-End Flow

1. **User selects 200 images, locale `fr-CA`, fields: title and alt**

2. **For each `media_id`:**
   - `fuseContext` → returns subject, entities, intent
   - If `on_topic` → apply template title pattern
   - Send to LLM with locale profile and glossary
   - `saveVersion` with `source=ai`

3. **Batch shows preview for approval**

4. **On approve:**
   - `activateVersion` and rename file if requested

5. **Metrics snapshot taken for 28-day follow-up**

---

## QA Checklist

- ✅ Titles in `fr-CA` never include the brand translated
- ✅ Off-topic images never receive city or brand in filenames
- ✅ Manual edits always win until explicitly replaced
- ✅ Killing a batch mid-run does not corrupt versions
- ✅ Removing a locale hides associated versions from the UI but keeps history

---

## Implementation Roadmap

### Phase 1: Context Fusion Layer (✅ Complete - Basic version)
- Language selector with profile-aware defaults
- Basic business context integration (name, location, UVP)
- Prompt enrichment with locale instruction

### Phase 2: Context Fusion Layer (Advanced)
- Build `optimizer_context` table
- Extract context from posts (title, headings, taxonomies) per locale
- Classify intent (on_topic/off_topic) with rules + LLM fallback
- Normalize keywords (locale-aware stemming & synonyms)

### Phase 3: AI Translation & Cultural Adaptation
- Create `optimizer_locale_profiles` (tone, patterns, stoplists)
- Maintain protected glossary (brands, cities, SKUs) per locale
- Design prompt templates respecting locale profiles & glossary
- Validate output length, forbidden tokens, subject presence

### Phase 4: Metadata Orchestration & Versioning
- Create `optimizer_metadata` (versioned metadata per locale)
- Write-once versions, manual edits always win until replaced
- Diff utility to compare AI vs active vs manual
- Regeneration triggers on post/context/glossary updates

### Phase 5: Performance & Batch Layer
- Job/queue tables: `optimizer_jobs`, `optimizer_job_items`, `optimizer_logs`
- Concurrency control (default 3 workers) with exponential backoff
- Resume from checkpoints every 25 items

### Phase 6: Template Intelligence
- `optimizer_templates` with locale-specific patterns & rules
- Apply templates for on_topic/off_topic decisions before AI call
- Validate required tokens before applying templates

### Phase 7: Multilingual Admin UX
- Locale switcher on media detail screen
- Side-by-side diff (AI vs Active vs Manual) with version history
- Batch actions filtered by locale & intent
- REST endpoints for context/generation/activation/rollback/jobs

### Phase 8: Visibility & Analytics
- `optimizer_metrics` to track 28-day before/after performance per locale/field
- Pull GSC/GA4 metrics via service accounts
- Reporting for wins/losses by locale

### Phase 9: Marketplace-Ready MVP
- License activation + telemetry (opt-in, especially for EU)
- Onboarding wizard (locale selection, templates, glossary upload)
- JSON fallback when AI unavailable

---

_Last updated: October 17, 2025_
_Status: Phase 1 complete, Phases 2-9 planned_
