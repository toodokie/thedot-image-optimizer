# AI Feature Rollout Plan

**Updated:** October 16, 2025  
**Scope:** Prepare the MSH Image Optimizer for AI‑assisted metadata without blocking current users.

---

## Goals

1. Allow the existing plugin build to toggle AI behaviour without shipping a separate “AI edition”.
2. Give power users immediate access via “bring your own key” (BYOK).
3. Preserve a clear upgrade path for paid tiers (“AI Starter”, “AI Pro”, etc.).
4. Leave room for credit/usage metering in a later release without refactoring.

---

## Current Foundations

- **Settings UI** already exposes:
  - `AI Mode` radio buttons (`manual`, `assist`, `hybrid`)
  - Optional API key field (`msh_ai_api_key`)
  - Feature checkboxes (`meta`, `vision`, `duplicate`)
- Values persist in WordPress options (`msh_ai_mode`, `msh_ai_api_key`, `msh_ai_features`).
- No licensing, credit tracking, or AI service connector exists yet.

---

## Phased Implementation

### Phase 1 – Hybrid Gate (MVP)

1. **Plan Tier Detection**
   - Add `msh_plan_tier` option (`free`, `starter`, `ai_starter`, `ai_pro`, etc.).
   - Future license activation can update this value.

2. **Access Helper**
   ```php
   $state = MSH_AI_Service::get_instance()->determine_access_state();
   // Returns ['allowed' => bool, 'mode' => 'byok'|'bundled', 'plan_tier' => 'free', 'reason' => 'upgrade_required'…]
   ```

3. **Enforcement**
   - Analyzer/optimizer checks `can_use_ai()` before calling the AI connector.
   - When denied, fall back to manual heuristics and optionally show an upsell notice.

4. **AI Connector Stub**
   - `msh_ai_generate_metadata` filter point allows immediate experimentation.
   - No external API call baked in yet; default behaviour simply returns `null`.

5. **BYOK Support**
   - If a user supplies their own API key, AI is always allowed (no plan gate).

**Outcome:** Paid plans and BYOK users get AI automatically; free users see the controls but are prompted to upgrade or supply a key.

### Phase 2 – Credit Metering (Future)

- License validation endpoint issues plan tier + credit allowance.
- Track `msh_ai_credit_balance`; decrement on successful AI calls.
- Nightly cron refreshes credits based on plan.
- Upsell messaging when credits are depleted.

---

## Design Decisions

- **Single plugin**: AI lives in the main codebase. Behaviour is feature‑flagged by plan tier + settings.
- **Filters first**: External services hook into `msh_ai_generate_metadata` to return AI output. Core keeps deterministic heuristics as a fallback.
- **Graceful fallback**: If AI is disabled/misconfigured, the existing heuristic pipeline runs without exposing errors to users.

---

## Next Steps (Phase 1)

1. Add `MSH_AI_Service` helper to centralise access checks and payload preparation.
2. Wire `generate_meta_fields()` to call `maybe_generate_metadata()` before the heuristic switch.
3. Require the new class from the main plugin bootstrap.
4. Update the analyze workflow to log denial reasons for UI messaging.

Once these pieces land, we can begin integrating the actual AI API (OpenAI Vision, Vertex, etc.) through the new filter without touching business logic again.

