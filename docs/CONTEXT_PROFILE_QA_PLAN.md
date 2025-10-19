# Context Profiles QA Plan

Last updated: October 17, 2025  
Owner: AI QA (Context Profiles track)

This checklist validates the “Context Profiles” feature that ships in the plugin settings (`Media → Image Optimizer → Settings`). Complete these steps whenever we make changes to business context handling, localization, or AI prompt wiring.

---

## 1. Environment Prep

1. **Deploy latest plugin build** to the Local test site  
   ```bash
   rsync -av --delete \
     /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/ \
     "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/"
   ```
2. **Restart the Local site** (or run `php /Applications/Local.app/.../wp-cli.phar opcache reset` + `transient delete --all`).
3. **Hard refresh** the WP admin (Cmd+Shift+R) before testing.
4. Optional sanity check of stored context:
   ```bash
   wp option get msh_onboarding_context
   wp option get msh_onboarding_context_profiles
   wp option get msh_active_context_profile
   ```

---

## 2. Create Profile Workflow

1. Navigate to **Media → Image Optimizer → Settings**.
2. Fill out the **Primary context** (business name, industry, location, brand voice).
3. Click **Add Context Profile**:
   - Enter `Profile label` (e.g., “Spanish Landing Pages”).
   - Confirm the slug auto-fills and is unique. Edit manually to verify manual mode sticks.
   - Populate context fields with alternate details (language, location, CTA, etc.).
4. Click **Save Settings**.
5. Verify via WP-CLI:
   ```bash
   wp option get msh_onboarding_context_profiles --format=json | jq .
   ```
   Ensure the profile appears with sanitized fields and a timestamp.

### Expected results
| Item | Expectation |
| --- | --- |
| Auto slug | Re-generates from label until edited manually |
| Timestamp | `context.updated_at` changes only after Save |
| Sanitisation | Fields are trimmed and safe (no HTML) |

---

## 3. Switching Active Profiles

1. In settings, use **Active Context** dropdown to select the new profile (e.g. “Profile – Spanish Landing Pages”).
2. Save settings and reload the dashboard.
3. In the analyzer screen, confirm:
   - Right-hand summary shows the profile label.
   - `window.mshImageOptimizer.activeProfile` matches the selected slug.
4. Developer console checks:
   ```js
   MSH_ImageOptimizer.AppState.activeProfileId        // => profile slug
   MSH_ImageOptimizer.AppState.contextSummary.business_name
   ```
   These should reflect the profile data.

### Additional validation
| Scenario | How to verify |
| --- | --- |
| Analyzer copy | Run **Analyze Published Images** and ensure log entries mention the active profile label. |
| AI prompts | Trigger AI Regeneration; inspect network payloads for `context_active_label` & `context_details` matching the profile. |

---

## 4. Metadata + AI Regeneration

With a non-primary profile active:

1. Run **AI Regeneration** (scope: published images).
2. Confirm the **AI Metadata Ready** badge appears.
3. Expand Meta Preview for several rows and ensure:
   - Generated titles/ALT reference the active profile brand name, city, tone.
   - Location logic follows the profile’s geography.
4. Run **Optimize Selected** for a subset and confirm the metadata persists in the Media Library (`wp media get ATTACHMENT_ID --field=post_title`).

Repeat the steps after switching back to primary to ensure metadata is re-personalised.

---

## 5. Deleting Profiles & Fallbacks

1. In settings, remove a profile using the trash icon.
2. Save settings.
3. Verify:
   - Profile slug no longer appears in `msh_onboarding_context_profiles`.
   - `msh_active_context_profile` automatically reverts to `primary` if the deleted profile was active.
4. Reload the dashboard and confirm the primary context is restored in summaries and analyzers.

Edge cases to test:

| Case | Expectation |
| --- | --- |
| Delete inactive profile | Active profile remains unchanged. |
| Delete active profile | Active profile falls back to primary. |
| Duplicate slugs | Adding profile with existing slug should append `-2` style suffix. |

---

## 6. Regression Checklist

- [ ] Profile add/remove works with multiple entries (≥3).
- [ ] Manual slug editing sticks (no overwrite on Save).
- [ ] `mshImageOptimizer.contextChoiceMap` reflects active profile industry.
- [ ] Analyzer, AI prompts, and filename suggestions respect the active profile.
- [ ] Manual-edit modal & badge still function after profile switches.

---

## 7. Known Data Issues

- The Local test site contains placeholder images that throw 404s after renames (`raindrops-raindrops…`, `placeholder-graphic…`). These are dataset artefacts—note them in test logs but do not treat as regressions.

---

## 8. Reporting

Log findings (pass/fail, screenshots, console logs) in the session recap or GitHub issue, referencing this checklist. Flag any discrepancies before moving on to localization work.

