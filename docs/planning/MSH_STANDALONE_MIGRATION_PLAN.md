# MSH Image Optimizer – Standalone Migration Plan

## 1. Purpose
This document captures everything required to lift the Main Street Health image optimizer out of the `medicross-child` theme and ship it as an independent plugin. It focuses on developer-facing assets: source files, build artefacts, environment expectations, and the work that still relies on the client site.

## 2. Deliverables
| Category | Description |
| --- | --- |
| Source code bundle | Extracted PHP classes, admin controller, REST/AJAX handlers, and helper libraries currently housed in the child theme. |
| Assets | Front-end JavaScript/CSS for the analyzer UI and duplicate cleanup dashboard, plus SVG/icon assets referenced by the UI. |
| Migration script | Temporary loader that registers the optimizer inside a vanilla WordPress plugin wrapper. |
| Documentation | The client guide **and** developer docs (`MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md`, `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md`, `MSH_IMAGE_OPTIMIZER_RND.md`, `MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md`) plus this plan. |
| Optional extras | Safe-rename regression scripts, CLI helpers, and unit/integration tests (if we decide to ship them with the plugin). |

> **Roadmap note:** AI-powered metadata/duplicate detection is queued for the standalone build after migration stabilises. See the “AI Implementation Roadmap” section in `MSH_IMAGE_OPTIMIZER_RND.md` for provider strategy, pricing, and phased delivery plan.
> **Onboarding note:** The legacy dashboard shipped in the client theme includes one-off WebP status messaging. The standalone plugin will replace this with a universal onboarding wizard that gathers reusable business context (business name, audience, voice, location) and feeds manual + AI workflows. Treat the Wizard + settings screen as core deliverables during packaging.

## 3. File Map

### 3.1 Core PHP Classes (Theme → Plugin `includes/`)
- `app/public/wp-content/themes/medicross-child/inc/class-msh-image-optimizer.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-media-cleanup.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-perceptual-hash.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-image-usage-index.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-webp-delivery.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-content-usage-lookup.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-safe-rename-system.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-safe-rename-cli.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-hash-cache-manager.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-targeted-replacement-engine.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-url-variation-detector.php`
- `app/public/wp-content/themes/medicross-child/inc/class-msh-navigation-widget.php` (if the standalone build keeps UI widgets)

> **Note:** The perceptual hash + hash cache stack (`class-msh-perceptual-hash.php`, `class-msh-hash-cache-manager.php`) and the usage index class are part of the live duplicate workflow. Treat them as required, not optional.

### 3.2 Admin / Hooks
- `app/public/wp-content/themes/medicross-child/admin/image-optimizer-admin.php`  
  (Will become `includes/admin/class-image-optimizer-admin.php` in the plugin namespace.)
- `app/public/wp-content/themes/medicross-child/functions.php`  
  (Audit for optimizer-specific `add_action`/`add_filter` calls; move them into the plugin bootstrap.)

### 3.3 Front-End Assets
- JavaScript (ship the production bundle, archive the rest for dev reference):
  - `app/public/wp-content/themes/medicross-child/assets/js/image-optimizer-modern.js` (current UI)
  - `app/public/wp-content/themes/medicross-child/assets/js/image-optimizer-enhanced.js`
  - `app/public/wp-content/themes/medicross-child/assets/js/image-optimizer-rename-ui.js`
  - `app/public/wp-content/themes/medicross-child/assets/js/image-optimizer-admin.js`
  - `app/public/wp-content/themes/medicross-child/assets/js/image-optimizer-modern-fixed.js`
- CSS:
  - `app/public/wp-content/themes/medicross-child/assets/css/image-optimizer-admin.css`
- Icons:
  - `app/public/wp-content/themes/medicross-child/assets/icons/Eye.svg`
  - `app/public/wp-content/themes/medicross-child/assets/icons/Tag.svg`
  - `app/public/wp-content/themes/medicross-child/assets/icons/Text.svg`

### 3.4 Language/Config Files
- Any `.json` or `.mo` translation files (none currently shipped).
- Options keys in the WordPress options table prefixed with `msh_`.

### 3.5 Documentation Bundle (`plugin/docs/`)
- `MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md`
- `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md`
- `MSH_IMAGE_OPTIMIZER_RND.md`
- `MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md`
- `MSH_STANDALONE_MIGRATION_PLAN.md` (this file)

### 3.6 Optional / Legacy Items
- The old third-party optimizer plugin under `app/public/wp-content/plugins/image-optimization/` is removed in this build. Do **not** migrate it unless the standalone project needs compatibility layers.
- `app/public/wp-content/themes/medicross-child/admin/image-optimizer-admin-backup.php` – legacy controller snapshot for rollback reference only.

## 4. Packaging Steps
1. **Create plugin skeleton**
   - `/msh-image-optimizer/`
     - `msh-image-optimizer.php` (plugin loader)
     - `/includes/` (PHP classes)
     - `/assets/css/`, `/assets/js/`, `/assets/icons/`
     - `/docs/` (copy documentation)

2. **Wire bootstrap**
   - Hook optimizer init to `plugins_loaded`.
   - Move theme-specific `add_action` registrations (AJAX, admin menu) into the plugin loader.

3. **Update namespaces & paths**
   - Replace theme-relative includes with plugin-relative paths.
   - Swap `get_stylesheet_directory()` calls for `plugin_dir_path(__FILE__)`.

4. **Enqueue assets**
   - Register CSS/JS via `wp_enqueue_style`/`wp_enqueue_script` using plugin URLs.

5. **Database table handling**
   - Move `maybe_create_index_table()` into plugin activation hook.
   - Guard table creation with plugin-specific version constants.
   - Include hash-cache table maintenance logic from `class-msh-hash-cache-manager.php`.

6. **Build artefact**
   - Generate a `.zip` or `.tar.gz` with the plugin directory.

## 5. Current Status & Environment Notes
- A standalone plugin skeleton already exists at `app/public/wp-content/plugins/msh-image-optimizer/` (`admin/`, `assets/`, `includes/`, `msh-image-optimizer.php`). Reuse or refactor that codebase instead of rebuilding the plugin structure from scratch.
- The child theme defines a fallback loader in `functions.php`:
  ```php
  if (!defined('MSH_IO_PLUGIN_FILE')) {
      // Load theme-based optimizer
  }
  ```
  Preserve this check so production sites automatically swap to the plugin when it is active.
- Perceptual hash + hash-cache classes are part of the production duplicate workflow; treat them as core components, not optional extras.
- **PHP**: 8.x compatible (client runs 8.2).
- **Extensions**: `gd` required for perceptual hashing; `imagick` optional but recommended for fallback image handling.
- **WordPress**: Tested on 6.6.x.
- **Dependencies**: None beyond Core; Action Scheduler may be introduced when we add background indexing.

## 6. Migration Validation Checklist
1. All PHP classes, JS/CSS, icons, and documentation copied into the plugin skeleton.  
2. Theme fallback verified (optimizer loads from theme only when the plugin is inactive).  
3. Usage index rebuild (`build_optimized_complete_index`) runs via plugin activation hook and creates required tables.  
4. Analyzer and duplicate UI load from plugin assets (no theme-relative paths remain).  
5. Logs/AJAX endpoints respond under the plugin namespace.  
6. Database upgrades tested on staging (hash cache, usage index tables).  
7. Developer docs (`DEV_NOTES`, `RND`, `MULTILANGUAGE`) packaged in `/docs` or provided alongside the dev hand-off.

## 7. Post-Migration TODOs
| Area | Task |
| --- | --- |
| Background processing | ✅ Cron-backed usage index queue now runs rebuilds in the background; evaluate Action Scheduler migration if we need cross-site orchestration. |
| Onboarding | Add setup wizard with progress indicator + health check. |
| Settings UI | Expose rename toggle, index rebuild controls, and diagnostic logs. |
| CLI | Bundle the rename + QA WP-CLI commands; extend duplicate reporting once quick-scan helpers are ported. |
| Testing | Add PHPUnit/Playwright suites covering optimizer batches and duplicate cleanup. |

## 8. References
- Client deployment guide: `MSH_IMAGE_OPTIMIZER_DOCUMENTATION.md`
- Developer notebook: `MSH_IMAGE_OPTIMIZER_DEV_NOTES.md`
- R&D archive: `MSH_IMAGE_OPTIMIZER_RND.md`
- Multilanguage guide: `MSH_IMAGE_OPTIMIZER_MULTILANGUAGE_GUIDE.md`
- Duplicate cleanup UI: `assets/js/image-optimizer-modern.js`
- Rename UI components: `assets/js/image-optimizer-rename-ui.js`
- Usage index optimized rebuild: `inc/class-msh-image-usage-index.php`
- Hash cache manager: `inc/class-msh-hash-cache-manager.php`
- Perceptual hash colour variance: `inc/class-msh-perceptual-hash.php`
- Theme fallback loader: `wp-content/themes/medicross-child/functions.php`
