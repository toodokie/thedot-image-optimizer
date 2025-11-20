# Phase 0D Optimization Plan

**Date:** November 10, 2025
**Goal:** Reduce Phase 0C.1 token usage (484 avg) while maintaining 100% reliability
**Target:** 450-460 tokens/image (5-7% reduction from Phase 0C.1)

---

## Current State (Phase 0C.1)

**Token Breakdown:**
- System prompt: ~20 tokens
- User prompt: ~377 tokens
  - Context flags: ~30 tokens
  - Schema + `req:` directive: ~40 tokens
  - Rules: ~64 tokens (symbolic grammar)
  - Page context: ~20 tokens
- Vision (detail:low): 85 tokens
- Completion: ~109 tokens avg
- **Total: 484 tokens/image**

**Quality Metrics:**
- Success rate: 100% (5/5 images)
- Keywords: 100% coverage
- Subjects: 100% coverage (with fallbacks)
- Field length compliance: 100%
- Cost: $1.59 per 1,000 images

---

## Phase 0D Optimization Targets

### 1. Optimize `req:` Directive (-10-15 tokens)

**Current (Phase 0C.1):**
```
req:fn,t,a,c,d,k[],s[],attr[],conf,iss[]
```
- 49 characters, ~12-15 tokens

**Proposed Phase 0D:**
```
req:fn,t,a,c,d,k,s
```
- 22 characters, ~5-7 tokens
- **Savings: ~7-10 tokens**

**Rationale:**
- `attr[]`, `conf`, and `iss[]` have server-side defaults and are not critical
- Server-side guardrails already handle empty `k` and `s`
- Only require fields that would cause Non-AI fallback if missing

### 2. Further Compress Context Flags (-3-5 tokens)

**Current context line:**
```
ctx:{ctx_id}|ct:{ct}|cm:{cm}|seo:{seo}|bm:{bm}|bn:{bn}|bl:{bl}|sv:{sv}|bv:{bv}
```

**Potential optimizations:**
- Remove redundant separators
- Use single-char keys where possible
- Combine boolean flags into bitfield

**Expected savings: 3-5 tokens**

### 3. Optimize Page Context Line (-2-3 tokens)

**Current:**
```
pg:ti={title}|kw={keyword}|pr={role}
```

**Could compress to:**
```
pg:{title}|{keyword}|{role}
```

**Expected savings: 2-3 tokens**

---

## Expected Phase 0D Performance

**Token Distribution:**
- System prompt: ~20 tokens (unchanged)
- User prompt: ~360-365 tokens (-12-17 from Phase 0C.1)
  - Context flags: ~27-29 tokens (-3-5)
  - Schema + `req:` directive: ~30-33 tokens (-7-10)
  - Rules: ~64 tokens (unchanged)
  - Page context: ~18-20 tokens (-2-3)
- Vision (detail:low): 85 tokens (unchanged)
- Completion: ~109 tokens (unchanged)
- **Total: 457-467 tokens/image**

**Target: 460 tokens/image average**

---

## Test Plan

### Validation Set (Same 5 Baseline Images)
1. #2056 - Coyote in forest (stock, no brand)
2. #2061 - Forest sunrise with vehicle (stock, no brand)
3. #2063 - Golden Gate Bridge (stock, landmark)
4. #2066 - Main Street Health facility (branded, SEO)
5. #2068 - Professional portrait (branded, team)

### Success Criteria
- ✅ 100% success rate (5/5 images)
- ✅ 100% keyword coverage (3-4 items per image)
- ✅ 100% subject coverage (3-4 items per image)
- ✅ 100% field length compliance (t≤60, a≤125, c≤150, d≤200)
- ✅ Token average ≤ 460 tokens/image
- ✅ Quality maintained (natural language, accurate descriptions)

### Failure Criteria
- ❌ Any image fails to generate metadata
- ❌ Missing title field (triggers Non-AI fallback)
- ❌ Empty keywords or subjects arrays (regression from Phase 0C.1)
- ❌ Field length violations
- ❌ Token increase above Phase 0C.1 baseline

---

## Comparison Matrix

| Metric | Non-AI | Phase 0B | Phase 0C | Phase 0C.1 | Phase 0D (Target) |
|--------|--------|----------|----------|------------|-------------------|
| **Prompt Tokens** | 0 | 349 | 324 | 377 | **360-365** |
| **Completion Tokens** | 0 | 118 | 104 | 109 | **~109** |
| **Total Tokens** | 0 | 467 | 428 | 484 | **460-470** |
| **Success Rate** | 100% | 100% | 80% | 100% | **100%** ✅ |
| **Keywords Coverage** | N/A | Inconsistent | 0% | 100% | **100%** ✅ |
| **Subjects Coverage** | N/A | Inconsistent | 0% | 100% | **100%** ✅ |
| **Field Compliance** | 100% | 60% | 60% | 100% | **100%** ✅ |
| **Quality Score** | 6-7/10 | 9/10 | 8/10 | 9/10 | **9/10** ✅ |
| **Cost per 1K** | $0 | $1.54 | $1.41 | $1.59 | **$1.51** |

**Phase 0D vs Phase 0C.1:**
- Token reduction: -24 tokens (-5.0%)
- Cost reduction: -$0.08 per 1,000 images (-5.0%)
- Quality: Maintained
- Reliability: Maintained

**Phase 0D vs Phase 0B:**
- Token increase: +10 tokens (+2.1%)
- Cost increase: -$0.03 per 1,000 images (-2.1%)
- Quality: Equal
- Reliability: Better (guaranteed keyword/subject coverage, field compliance)

---

## Implementation Steps

1. **Modify [class-msh-openai-connector.php](includes/class-msh-openai-connector.php)**
   - Line 534: Update schema line with shortened `req:fn,t,a,c,d,k,s` directive
   - Optional: Compress context flags (lines 533-548)
   - Optional: Compress page context line

2. **Deploy to test site**
   ```bash
   cp includes/class-msh-openai-connector.php \
      "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/"
   ```

3. **Run validation tests**
   ```bash
   cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"

   # Test each baseline image
   wp msh check-analyze --id=2056
   wp msh check-analyze --id=2061
   wp msh check-analyze --id=2063
   wp msh check-analyze --id=2066
   wp msh check-analyze --id=2068
   ```

4. **Extract results from logs**
   - Capture token counts (prompt/completion/total)
   - Verify keywords/subjects populated
   - Check field lengths
   - Confirm no failures

5. **Document results in [PHASE-0B-TO-0C-COMPARISON.md](PHASE-0B-TO-0C-COMPARISON.md)**
   - Add Phase 0D section with all 5 sample results
   - Update comparison tables
   - Add final recommendation

6. **Update [LOG-NOVEMBER-8.md](LOG-NOVEMBER-8.md)**
   - Document Phase 0D implementation
   - Note token improvements
   - Confirm production readiness

---

## Risk Assessment

**Low Risk:**
- ✅ Server-side guardrails remain intact (keyword/subject fallbacks, field trimming, title synthesis)
- ✅ Only shortening `req:` directive - GPT-4o should still understand required fields
- ✅ Compression changes are minor and tested in Phase 0C

**Mitigation:**
- If Phase 0D shows regressions, immediately revert to Phase 0C.1
- Monitor first 5 images closely for any failures
- Keep Phase 0C.1 code as fallback

---

## Decision Framework

**Ship Phase 0D if:**
- ✅ 100% success rate maintained
- ✅ Token average ≤ 465 tokens/image
- ✅ Keywords/subjects coverage = 100%
- ✅ Quality equivalent to Phase 0C.1

**Stay with Phase 0C.1 if:**
- ❌ Any regression in success rate
- ❌ Token savings < 20 tokens
- ❌ Quality degradation

**Consider Phase 0D.1 if:**
- ⚠️ Minor regressions but good token savings
- Need further iteration to balance quality vs cost

---

## Status: ⏳ READY FOR IMPLEMENTATION

Next step: Implement Phase 0D code changes and run validation tests.
