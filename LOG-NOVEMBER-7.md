# Log — November 7, 2025

## Triple “-ID” filename corruption
- **Symptoms**: `_wp_attached_file` stored `…-ID-ID-ID.ext` while disk only had `…-ID.ext`, causing broken images (IDs 754–756).
- **Root cause**: Both slug generation and post-rename uniqueness guards appended `-$attachment_id` without collapsing existing suffixes; subsequent optimizations re-saved mutated paths even when the base file never moved.
- **Mitigations shipped**:
  - Shared helper `msh_collapse_id_suffix()` now normalizes basenames before writing `_wp_attached_file`.
  - Atomic IO + safe rename pipelines use `msh_update_attached_file_collapsed()` so duplicated suffixes are removed and physical files follow suit.
  - Uniqueness logic appends `-ID` only after collisions and only once.
  - Neutral regeneration ensures we regenerate subsizes without altering basenames when the file didn’t move.
  - A nightly sweep (plus `wp msh sweep`) verifies recent attachments and heals any lingering mismatches.
- **Analyze guard**:
  - Analyze now runs under a hard guard: any attempt to write `_wp_attached_file` while the analyzer is active is blocked at the metadata layer.
  - `msh_update_attached_file_collapsed()` refuses to update DB paths unless the target file exists, preventing stale suggestions from corrupting metadata.
  - New CLI coverage (`wp msh check-analyze`, `wp msh repair-db`) plus the nightly sweep’s “repair from disk” fallback heal any remaining DB-only drifts.
- **Next steps**: Run `wp msh sweep --days=36500 --limit=5000` once post-deploy to repair historical records, then rely on the nightly sweep (default 7 days / 100 items) plus parity CI to catch regressions.

## Post-Deployment Critical Fix
- **Issue**: Fatal error "Access to undeclared static property MSH_Image_Optimizer::$analyze_guard_depth"
- **Root cause**: Property `$analyze_guard_depth` was declared in `MSH_Contextual_Meta_Generator` (line 49) instead of `MSH_Image_Optimizer`
- **Fix**: Added `private static $analyze_guard_depth = 0;` to `MSH_Image_Optimizer` class (line 5957)
- **Testing**: All CLI commands now passing:
  - ✅ `wp msh check-analyze --id=754` - Analyze guard working correctly
  - ✅ `wp msh repair-db --ids=762,769,770` - Repair command functional
  - ✅ `wp msh sweep --days=1 --limit=20` - Nightly sweep working
  - ⚠️ `wp msh parity verify_attachments` - 2/3 fixtures passed (fixture 402 correctly detected geo term violations)
