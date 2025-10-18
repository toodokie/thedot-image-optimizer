# Project TODO / Roadmap

_Last updated: October 17, 2025_

## Multilingual AI Roadmap

### Phase 1 (✅ Complete)
- Language selector UI in AI Regeneration modal
- Manual language override + auto defaults (profile locale → primary locale → site locale → English)
- Prompt language instruction + brand/location emphasis

### Phase 2 – Context Fusion Layer (🚧 Planned)
- Build `optimizer_context` table (media_id, locale, subject, intent, entities, keywords, source_hash, updated_at)
- Extract context from posts (title, headings, taxonomies) per locale
- Classify intent (on_topic/off_topic) with rules + LLM fallback
- Normalize keywords (locale-aware stemming & synonyms)

### Phase 3 – AI Translation & Cultural Adaptation
- Create `optimizer_locale_profiles` (tone, patterns, stoplists)
- Maintain protected glossary (brands, cities, SKUs) per locale
- Design prompt templates respecting locale profiles & glossary
- Validate output length, forbidden tokens, subject presence

### Phase 4 – Metadata Orchestration & Versioning
- Create `optimizer_metadata` (media_id, locale, field, value, source, version, checksum)
- Write-once versions, manual edits always win until replaced
- Diff utility to compare AI vs active vs manual
- Regeneration triggers on post/context/glossary updates

### Phase 5 – Performance & Batch Layer
- Job/queue tables: `optimizer_jobs`, `optimizer_job_items`, `optimizer_logs`
- Concurrency control (default 3 workers) with exponential backoff
- Resume from checkpoints every 25 items

### Phase 6 – Template Intelligence
- `optimizer_templates` with locale-specific patterns & rules
- Apply templates for on_topic/off_topic decisions before AI call
- Validate required tokens before applying templates

### Phase 7 – Multilingual Admin UX
- Locale switcher on media detail screen
- Side-by-side diff (AI vs Active vs Manual) with version history
- Batch actions filtered by locale & intent
- REST endpoints for context/generation/activation/rollback/jobs

### Phase 8 – Visibility & Analytics
- `optimizer_metrics` to track 28-day before/after performance per locale/field
- Pull GSC/GA4 metrics via service accounts
- Reporting for wins/losses by locale

### Phase 9 – Marketplace-Ready MVP
- License activation + telemetry (opt-in, especially for EU)
- Onboarding wizard (locale selection, templates, glossary upload)
- JSON fallback when AI unavailable

## Code Quality & Maintenance

### Completed
- ✅ PHP 8.4 nullable parameter fix (`?array` syntax)
- ✅ PHPCBF auto-formatting (44,401 violations fixed - 94% reduction)
- ✅ Public API documentation (core classes)

### Deferred (Low Priority - Future Sprints)

**Remaining PHPCS Violations: 2,640 errors + 627 warnings**

These are cosmetic/style issues that don't affect functionality:

#### Yoda Conditions (537 violations)
- **Issue:** `if ( $var === 'value' )` should be `if ( 'value' === $var )`
- **Impact:** Cosmetic - WordPress style preference
- **Effort:** 2-3 hours (scriptable but needs careful testing)
- **Priority:** Low

#### Inline Comment Punctuation (810 violations)
- **Issue:** Comments don't end with periods
- **Impact:** Cosmetic only
- **Effort:** 1 hour (scriptable)
- **Priority:** Low

#### Function Docblocks (232 missing @param tags, 223 missing comments)
- **Issue:** Private/protected methods lack complete documentation
- **Impact:** Developer experience (already done for public API)
- **Effort:** 3-4 hours
- **Priority:** Medium (nice to have)

#### Other Style Issues
- Missing translators comments (162)
- Variable comments (73)
- File comments (13)
- **Impact:** Cosmetic
- **Effort:** 2-3 hours
- **Priority:** Low

**Total Deferred Effort:** ~10-15 hours

**Recommendation:** Address during maintenance sprints between feature releases. Not blocking any functionality.

**Track Progress:**
```bash
# Run periodic checks
vendor/bin/phpcs -d memory_limit=512M --standard=WordPress --report=summary msh-image-optimizer/includes/ msh-image-optimizer/admin/
```

## Cross-cutting Requirements
- Protected glossary & named entities (never translate brand terms)
- Locale tone profiles with preferred terminology
- Filename normalizer respecting CDN constraints
- Regeneration triggers (post title change, locale added, glossary updates)
- Safety & rate limits (token budgets, circuit breakers)

---

Use this file to track progress and break down tasks into sprint-ready tickets.
