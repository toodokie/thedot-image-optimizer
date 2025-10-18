# Multilingual AI – Phase 1 Plan (Option A)

Last updated: October 17, 2025  
Owner: Multilingual AI track

This document maps the current AI regeneration pipeline and outlines the work required to add a user-facing language selector (Option A). Once the selector is live we will extend the flow to automatically honour profile locales (Option B) and enrich prompts accordingly.

---

## 1. Current AI Regeneration Flow

**UI (JavaScript)** – `assets/js/image-optimizer-modern.js`
- Modal collects `ai_scope`, `ai_mode`, and `ai_fields`.
- `startRegeneration()` saves these values and runs a manual-edit preflight.
- `proceedWithRegeneration()` sends an AJAX request to `msh_analyze_images`.

**AJAX handler** – `includes/class-msh-image-optimizer.php::ajax_analyze_images()`
- Reads `ai_scope`, `ai_mode`, `ai_fields`.
- Forces a fresh analysis.
- When AI is active, fetches images via `get_images_for_ai_regeneration()` and calls `analyze_single_image($id, $ai_options)`.

**Context helper** – `includes/class-msh-context-helper.php`
- Returns sanitized primary/profile context.
- Profile payload already includes optional `locale`, `usage`, and `notes` fields.

**AI connector** – `includes/class-msh-openai-connector.php`
- Receives `context`, `ai_options`, and generates the OpenAI prompt.
- Prompt currently uses business name, industry label, UVP, and location text.
- No language parameter is passed yet.

---

## 2. Option A – Language Selector UI

Goal: allow users to pick the output language explicitly while defaulting to sensible profile/site locales. This lays the groundwork for Option B.

### 2.1 UI Changes
- Add a `<select>` control to the AI Regeneration modal with the following options:
  - `auto` (Site default / profile locale)
  - `en` English
  - `es` Spanish (Español)
  - `fr` French (Français)
  - `de` German (Deutsch)
  - `pt` Portuguese (Português)
  - `it` Italian (Italiano)
- Default selection:
  1. Active profile locale (if present)
  2. Otherwise, site locale via `get_locale()`
  3. Fallback: `en`
- Update modal helpers (`updateEstimate`, `startRegeneration`, manual-edit warning flow) to read and store `ai_language`.

### 2.2 Payload Threading
- Include `ai_language` in the AJAX request sent from `proceedWithRegeneration()`.
- Extend `ajax_analyze_images()` to read `$_POST['ai_language']` (sanitize, default to empty string).
- Pass the value into `$ai_options` before calling `analyze_single_image()`.
- Ensure `analyze_single_image()` forwards the `language` flag to the contextual meta generator / AI service.

### 2.3 Prompt Updates (Phase 1 scope)
- Update `MSH_OpenAI_Connector::build_vision_prompt()` to accept the language parameter and add an instruction such as:
  ```
  - Generate all metadata in [language name]. If language is "auto", use the site default.
  ```
- Introduce a helper in the connector to map language codes to human-readable names (re-usable for logging).
- For `auto`, fall back to profile locale or site locale inside the connector.

---

## 3. Future Enhancement (Option B Preview)

Once Option A is deployed:
- Automatically seed the selector with the active profile’s locale (already planned).
- When the selector is “auto”, resolve order precedence:
  1. Active profile locale (`contextProfiles[].locale`)
  2. Primary context locale
  3. `get_locale()` (site language)
- Consider adding a per-profile “preferred AI language” setting if required.

---

## 4. Implementation Checklist (Option A)

1. **Modal UI**
   - [ ] Add language selector HTML + styling.
   - [ ] Localize selector strings via `mshImageOptimizer.strings`.
   - [ ] Pre-populate default using `mshImageOptimizer.activeProfile` context data.

2. **JavaScript wiring**
   - [ ] Store `ai_language` in `pendingRegenerationParams`.
   - [ ] Include in AJAX payload (`msh_analyze_images` and estimate endpoint if needed).
   - [ ] Adjust manual-edit confirmation flow to resume with stored language.

3. **PHP/AJAX**
   - [ ] Accept and sanitize `ai_language` in `ajax_analyze_images()`.
   - [ ] Add to `$ai_options` (`$ai_options['language']`).
   - [ ] Ensure caching logic keys account for language (if necessary).

4. **AI Connector**
   - [ ] Accept language in `$payload['ai_options']`.
   - [ ] Map language code to prompt instruction.
   - [ ] Log language in debug output for traceability.

5. **Testing**
   - [ ] Verify default selection per profile/site.
   - [ ] Run AI regeneration choosing Spanish and confirm metadata output (prompt includes instruction).
   - [ ] Switch back to English to ensure regression-free behavior.

---

## 5. References
- `assets/js/image-optimizer-modern.js`
- `admin/image-optimizer-admin.php`
- `includes/class-msh-image-optimizer.php`
- `includes/class-msh-context-helper.php`
- `includes/class-msh-openai-connector.php`
- `docs/MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md`
- `docs/CONTEXT_PROFILE_QA_PLAN.md` (profile locale usage)

