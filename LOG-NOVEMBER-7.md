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
- **Next steps**: Run `wp msh sweep --days=36500 --limit=5000` once post-deploy to repair historical records, then rely on the nightly sweep (default 7 days / 100 items) plus parity CI to catch regressions.
