#!/usr/bin/env python3
"""
Fix date() calls for WordPress.org compliance.

WordPress.org requires using wp_date() or gmdate() instead of date()
for timezone safety and i18n support.

Usage:
    python3 fix-date-calls.py [--dry-run]
"""

import re
import sys
import os

# Files to process
FILES_TO_FIX = [
    'msh-image-optimizer/admin/image-optimizer-admin.php',
    'msh-image-optimizer/includes/class-msh-ai-ajax-handlers.php',
    'msh-image-optimizer/includes/class-msh-ai-service.php',
    'msh-image-optimizer/includes/class-msh-backup-verification-system.php',
    'msh-image-optimizer/includes/class-msh-targeted-replacement-engine.php',
    'msh-image-optimizer/includes/class-msh-debug-logger.php',
    'msh-image-optimizer/includes/class-msh-image-optimizer.php',
]

def fix_date_calls(content):
    """
    Replace date() calls with WordPress-safe alternatives.

    Strategy:
    - date('Y-m') → wp_date('Y-m') (user-facing, needs timezone)
    - date('Y-m-d H:i:s') → current_time('mysql') (WordPress standard)
    - date('Y-m-d') in filenames → gmdate('Y-m-d') (UTC, no timezone issues)
    - date('H:i:s.') → gmdate('H:i:s.') (logging timestamps)
    """
    replacements = 0

    # Fix 1: date('Y-m') for credit tracking → wp_date('Y-m')
    # User-facing month keys should respect site timezone
    pattern = r"\bdate\(\s*['\"]Y-m['\"]\s*\)"
    replacement = r"wp_date('Y-m')"
    content, n = re.subn(pattern, replacement, content)
    replacements += n
    if n > 0:
        print(f"  ✓ Fixed {n} date('Y-m') → wp_date('Y-m')")

    # Fix 2a: date('Y-m-d H:i:s', strtotime('-X days')) → gmdate('Y-m-d H:i:s', strtotime('-X days'))
    # Relative date calculations for database queries - use gmdate for UTC
    pattern = r"\bdate\(\s*['\"]Y-m-d H:i:s['\"]\s*,\s*strtotime\("
    replacement = r"gmdate('Y-m-d H:i:s', strtotime("
    content, n = re.subn(pattern, replacement, content)
    replacements += n
    if n > 0:
        print(f"  ✓ Fixed {n} date('Y-m-d H:i:s', strtotime(...)) → gmdate('Y-m-d H:i:s', strtotime(...))")

    # Fix 2b: date('Y-m-d H:i:s') for database timestamps → current_time('mysql')
    # WordPress standard for MySQL-formatted timestamps
    pattern = r"\bdate\(\s*['\"]Y-m-d H:i:s['\"]\s*(?:,\s*\$timestamp)?\s*\)"
    replacement = r"current_time('mysql')"
    content, n = re.subn(pattern, replacement, content)
    replacements += n
    if n > 0:
        print(f"  ✓ Fixed {n} date('Y-m-d H:i:s') → current_time('mysql')")

    # Fix 3: date('Y-m-d') in filenames/logging → gmdate('Y-m-d')
    # UTC dates for internal use (filenames, logs) - no timezone conversion needed
    pattern = r"\bdate\(\s*['\"]Y-m-d['\"]\s*\)"
    replacement = r"gmdate('Y-m-d')"
    content, n = re.subn(pattern, replacement, content)
    replacements += n
    if n > 0:
        print(f"  ✓ Fixed {n} date('Y-m-d') → gmdate('Y-m-d')")

    # Fix 4: date('H:i:s.') for log timestamps → gmdate('H:i:s.')
    # UTC timestamps for internal logging
    pattern = r"\bdate\(\s*['\"]H:i:s\.\s*['\"]\s*\)"
    replacement = r"gmdate('H:i:s.')"
    content, n = re.subn(pattern, replacement, content)
    replacements += n
    if n > 0:
        print(f"  ✓ Fixed {n} date('H:i:s.') → gmdate('H:i:s.')")

    return content, replacements

def main():
    dry_run = '--dry-run' in sys.argv
    auto_yes = '--yes' in sys.argv

    if dry_run:
        print("🔍 DRY RUN MODE - No files will be modified\n")
    else:
        print("⚠️  LIVE MODE - Files will be modified\n")
        if not auto_yes:
            confirm = input("Continue? (yes/no): ")
            if confirm.lower() != 'yes':
                print("Aborted.")
                return
        print()

    total_replacements = 0

    for file_path in FILES_TO_FIX:
        if not os.path.exists(file_path):
            print(f"⏭️  Skipping {file_path} (not found)")
            continue

        print(f"📄 Processing {file_path}...")

        with open(file_path, 'r', encoding='utf-8') as f:
            original = f.read()

        fixed, count = fix_date_calls(original)

        if count == 0:
            print(f"  ℹ️  No date() calls found\n")
            continue

        total_replacements += count

        if not dry_run:
            # Create backup
            backup_path = file_path + '.pre-date-fix'
            with open(backup_path, 'w', encoding='utf-8') as f:
                f.write(original)
            print(f"  💾 Backup created: {backup_path}")

            # Write fixed content
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(fixed)
            print(f"  ✅ Applied {count} replacements\n")
        else:
            print(f"  🔍 Would replace {count} instances\n")

    print(f"\n{'📊 Summary:' if dry_run else '✅ Complete!'}")
    print(f"Total replacements: {total_replacements}")

    if not dry_run and total_replacements > 0:
        print("\n📋 Next steps:")
        print("1. Review changes: git diff")
        print("2. Test functionality")
        print("3. Commit changes")
        print("4. Copy to WordPress installation")

if __name__ == '__main__':
    main()
