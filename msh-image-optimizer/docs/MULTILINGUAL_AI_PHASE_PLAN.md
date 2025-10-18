# Multilingual AI – Phase Plan

Last updated: October 17, 2025  
Owner: Multilingual AI track

This plan tracks the staged rollout of multilingual metadata support. Phase 1 (language selector) is complete; Phase 2 (automatic locale + prompt enrichment) is now in progress.

---

## 1. System Overview

**Front-end (JavaScript)** – `assets/js/image-optimizer-modern.js`
- Collects AI configuration in the regeneration modal
- Runs manual-edit preflight checks
- Sends `msh_analyze_images` AJAX request

**AJAX handler** – `includes/class-msh-image-optimizer.php::ajax_analyze_images()`
- Reads AI parameters, forces fresh analysis, and queues per-image processing

**Context helper** – `includes/class-msh-context-helper.php`
- Stores sanitized primary/profile context (including locale)

**AI connector** – `includes/class-msh-openai-connector.php`
- Builds OpenAI prompts using business context, location, language, and feature flags

---

## 2. Option A – Language Selector & Manual Override ✅

**Delivered features (Oct 17, 2025):**
- Language dropdown in AI Regeneration modal (Auto + EN/ES/FR/DE/PT/IT)
- Smart default: active profile locale → primary context locale → site locale → English
- AJAX payload threads `ai_language` and `profile_locale`
- OpenAI prompt includes explicit language instruction
- CSS + localized strings for new UI

**Testing recap:**
- Verified Spanish, French, and Auto outputs (native-quality metadata)
- Console clean, manual-edit flow preserves selection

---

## 3. Option B – Automatic Locale + Prompt Enrichment ▶

### Objectives
1. **Smarter defaults per profile**
   - Reset dropdown when profile changes unless the user manually overrides that profile
   - Persist locale data with profiles/primary context for reuse
2. **Prompt refinement**
   - Ensure business name and location are emphasised in metadata for brand consistency
   - Continue tuning language instructions based on QA feedback
3. **Telemetry & Logging**
   - Log resolved language and profile locale in OpenAI connector for easier debugging

### Implementation To‑Dos
- [ ] Enhance JS logic to detect profile switches and reapply defaults (per-profile user override)
- [ ] Confirm locale values are stored in `mshImageOptimizer.contextProfiles` / `onboardingContext` (added in Phase 1, validate data flow)
- [ ] Extend prompt requirements for location/brand (initial step done; monitor QA output)
- [ ] Update docs/user guides as behaviour evolves
- [ ] QA matrix: English + each locale profile → regenerate metadata, confirm language + city/brand references

### Future Considerations
- Optional per-profile “preferred AI language” setting
- Additional languages based on demand
- Potential integration with full-site translation plugins

---

## 4. References
- `assets/js/image-optimizer-modern.js`
- `admin/image-optimizer-admin.php`
- `includes/class-msh-image-optimizer.php`
- `includes/class-msh-context-helper.php`
- `includes/class-msh-openai-connector.php`
- `docs/MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md`
- `docs/USER_GUIDE_MULTILINGUAL_AI.md`
