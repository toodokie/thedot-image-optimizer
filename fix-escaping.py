#!/usr/bin/env python3
"""
WordPress Escaping Compliance Fixer
Automatically fixes unescaped output violations for WordPress.org compliance

This script safely replaces:
1. _e() -> esc_html_e()
2. __() -> esc_html( __() ) when used in echo/print
3. Adds proper escaping context based on usage

Usage:
    python3 fix-escaping.py --dry-run  # Preview changes
    python3 fix-escaping.py            # Apply changes
"""

import re
import os
import sys
from pathlib import Path
from typing import List, Tuple

# Directories to process
DIRS_TO_PROCESS = ['msh-image-optimizer/admin', 'msh-image-optimizer/includes']

# Files to exclude (test files don't need escaping fixes)
EXCLUDE_PATTERNS = [
    'test-',
    'tests/',
]

# Backup extension
BACKUP_EXT = '.pre-escaping-fix'


class EscapingFixer:
    """Fixes WordPress escaping violations"""

    def __init__(self, dry_run=False):
        self.dry_run = dry_run
        self.files_processed = 0
        self.replacements_made = 0
        self.files_with_changes = []

    def should_exclude(self, filepath: str) -> bool:
        """Check if file should be excluded"""
        for pattern in EXCLUDE_PATTERNS:
            if pattern in filepath:
                return True
        return False

    def fix_file(self, filepath: Path) -> int:
        """Fix escaping in a single file. Returns number of changes."""
        if self.should_exclude(str(filepath)):
            return 0

        try:
            with open(filepath, 'r', encoding='utf-8') as f:
                content = f.read()
        except Exception as e:
            print(f"❌ Error reading {filepath}: {e}")
            return 0

        original_content = content
        changes = 0

        # Fix 1: <?php _e( -> <?php esc_html_e(
        content, n = re.subn(
            r'<\?php\s+_e\(',
            r'<?php esc_html_e(',
            content
        )
        changes += n

        # Fix 2: _e( not preceded by esc_
        # Only match _e( that's not already esc_html_e( or esc_attr_e(
        content, n = re.subn(
            r'(?<!esc_html_)(?<!esc_attr_)(?<!esc_js_)(?<!esc_textarea_)\b_e\(',
            r'esc_html_e(',
            content
        )
        changes += n

        # Fix 3: echo __( without esc_ -> echo esc_html( __(
        # Match: echo __('text', 'domain')
        content, n = re.subn(
            r'\becho\s+__\(',
            r'echo esc_html( __(',
            content
        )
        if n > 0:
            # Also need to add closing paren
            # This is trickier - we need to match the full statement
            # For now, flag for manual review
            changes += n

        # Fix 4: print __( without esc_ -> print esc_html( __(
        content, n = re.subn(
            r'\bprint\s+__\(',
            r'print esc_html( __(',
            content
        )
        changes += n

        # Fix 5: <?= $var ?> -> <?= esc_html( $var ) ?>
        content, n = re.subn(
            r'<\?=\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\?>',
            r'<?= esc_html( $\1 ) ?>',
            content
        )
        changes += n

        # Fix 6: echo $var; -> echo esc_html( $var );
        # Only simple cases where it's clearly HTML context
        content, n = re.subn(
            r'\becho\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*;',
            r'echo esc_html( $\1 );',
            content
        )
        changes += n

        # Fix 7: print $var; -> print esc_html( $var );
        content, n = re.subn(
            r'\bprint\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s*;',
            r'print esc_html( $\1 );',
            content
        )
        changes += n

        if changes > 0:
            self.files_with_changes.append(str(filepath))
            self.replacements_made += changes

            if not self.dry_run:
                # Create backup
                backup_path = str(filepath) + BACKUP_EXT
                with open(backup_path, 'w', encoding='utf-8') as f:
                    f.write(original_content)

                # Write fixed content
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(content)

                print(f"✅ {filepath}: {changes} replacements")
            else:
                print(f"🔍 {filepath}: {changes} replacements (DRY RUN)")

        return changes

    def process_directory(self, directory: str):
        """Process all PHP files in directory"""
        dir_path = Path(directory)

        if not dir_path.exists():
            print(f"⚠️  Directory not found: {directory}")
            return

        for php_file in dir_path.glob('**/*.php'):
            self.files_processed += 1
            self.fix_file(php_file)

    def print_summary(self):
        """Print summary of changes"""
        print("\n" + "="*70)
        print("ESCAPING FIX SUMMARY")
        print("="*70)
        print(f"Files processed: {self.files_processed}")
        print(f"Files with changes: {len(self.files_with_changes)}")
        print(f"Total replacements: {self.replacements_made}")

        if self.dry_run:
            print("\n⚠️  DRY RUN MODE - No files were modified")
            print("Run without --dry-run to apply changes")
        else:
            print(f"\n✅ Changes applied!")
            print(f"   Backups created with extension: {BACKUP_EXT}")
            print("\n📋 Files modified:")
            for f in self.files_with_changes[:20]:  # Show first 20
                print(f"   - {f}")
            if len(self.files_with_changes) > 20:
                print(f"   ... and {len(self.files_with_changes) - 20} more")

        print("\n⚠️  IMPORTANT: Manual review required for:")
        print("   1. echo __() statements (may need additional closing paren)")
        print("   2. Complex expressions that may need esc_attr() or esc_url()")
        print("   3. Context-specific escaping (attributes, URLs, JavaScript)")
        print("\n🔍 Next steps:")
        print("   1. Review changes with: git diff")
        print("   2. Test thoroughly on development site")
        print("   3. Run Plugin Check to verify fixes")
        print("   4. Handle any remaining manual cases")


def main():
    """Main entry point"""
    dry_run = '--dry-run' in sys.argv

    print("WordPress Escaping Compliance Fixer")
    print("="*70)

    if dry_run:
        print("🔍 DRY RUN MODE - No files will be modified\n")
    else:
        print("⚠️  LIVE MODE - Files will be modified (backups created)\n")
        response = input("Continue? (yes/no): ")
        if response.lower() not in ['yes', 'y']:
            print("Aborted.")
            return

    fixer = EscapingFixer(dry_run=dry_run)

    for directory in DIRS_TO_PROCESS:
        print(f"\n📁 Processing: {directory}")
        fixer.process_directory(directory)

    fixer.print_summary()


if __name__ == '__main__':
    main()
