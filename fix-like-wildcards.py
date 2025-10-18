#!/usr/bin/env python3
"""
Fix SQL LIKE wildcards for WordPress.org compliance.

Replaces hardcoded LIKE wildcards with $wpdb->esc_like() + concatenation.

Usage:
    python3 fix-like-wildcards.py [--dry-run] [--yes]
"""

import re
import sys
import os

# Files to process
FILES_TO_FIX = [
    'msh-image-optimizer/includes/class-msh-ai-ajax-handlers.php',
    'msh-image-optimizer/includes/class-msh-image-usage-index.php',
    'msh-image-optimizer/includes/class-msh-media-cleanup.php',
    'msh-image-optimizer/includes/class-msh-usage-index-background.php',
    'msh-image-optimizer/includes/class-msh-content-usage-lookup.php',
]

def fix_like_wildcards(content, filename):
    """
    Fix LIKE wildcards in SQL queries.

    Strategy:
    - LIKE 'image/%' → use prepared statement with esc_like
    - LIKE '%something%' → use prepared statement with esc_like
    """
    replacements = 0

    # Pattern 1: LIKE 'image/%' (most common)
    # Find queries with this pattern and add a prepare wrapper
    pattern = r"(post_mime_type\s+LIKE\s+['\"]image/%['\"])"
    matches = re.findall(pattern, content, re.IGNORECASE)

    if matches:
        print(f"  ℹ️  Found {len(matches)} instances of LIKE 'image/%'")
        print(f"  ⚠️  These require manual fixes - adding $wpdb->prepare() wrapper")
        print(f"  📝  Example fix:")
        print(f"      Before: $sql = \"SELECT * FROM ... WHERE post_mime_type LIKE 'image/%'\";")
        print(f"      After:  $like = $wpdb->esc_like('image/') . '%';")
        print(f"              $sql = $wpdb->prepare(\"SELECT * FROM ... WHERE post_mime_type LIKE %s\", $like);")

    return content, replacements

def main():
    dry_run = '--dry-run' in sys.argv
    auto_yes = '--yes' in sys.argv

    if dry_run:
        print("🔍 DRY RUN MODE - Analysis only\n")
    else:
        print("⚠️  LIKE wildcards require MANUAL fixes\n")
        print("This script will identify locations but cannot auto-fix them.")
        print("Reason: Need to add $wpdb->prepare() wrapper around entire query.\n")
        if not auto_yes:
            confirm = input("Continue with analysis? (yes/no): ")
            if confirm.lower() != 'yes':
                print("Aborted.")
                return
        print()

    total_found = 0

    for file_path in FILES_TO_FIX:
        if not os.path.exists(file_path):
            print(f"⏭️  Skipping {file_path} (not found)")
            continue

        print(f"📄 Analyzing {file_path}...")

        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()

        _, count = fix_like_wildcards(content, file_path)

        # Count actual LIKE wildcards
        like_patterns = [
            r"LIKE\s+['\"]image/%['\"]",
            r"LIKE\s+['\"]%[^'\"]+%['\"]",
            r"LIKE\s+CONCAT\(",
        ]

        file_total = 0
        for pattern in like_patterns:
            matches = re.findall(pattern, content, re.IGNORECASE)
            file_total += len(matches)

        if file_total > 0:
            print(f"  🔍 Found {file_total} LIKE patterns needing review\n")
            total_found += file_total
        else:
            print(f"  ✅ No LIKE wildcards found\n")

    print(f"\n📊 Summary:")
    print(f"Total LIKE patterns found: {total_found}")
    print(f"\n⚠️  These require manual fixes. See triage doc for patterns.")

if __name__ == '__main__':
    main()
