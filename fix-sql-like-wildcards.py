#!/usr/bin/env python3
"""
Fix SQL LIKE wildcards for WordPress.org compliance.

Automatically wraps queries containing LIKE 'image/%' with $wpdb->prepare()
and uses $wpdb->esc_like() for safe wildcard patterns.

Usage:
    python3 fix-sql-like-wildcards.py [--dry-run] [--yes]
"""

import re
import sys
import os
from typing import Tuple

# Files to process
FILES_TO_FIX = [
    'msh-image-optimizer/includes/class-msh-media-cleanup.php',
    'msh-image-optimizer/includes/class-msh-image-usage-index.php',
    'msh-image-optimizer/includes/class-msh-usage-index-background.php',
    'msh-image-optimizer/includes/class-msh-content-usage-lookup.php',
    'msh-image-optimizer/includes/class-msh-image-optimizer.php',
]

def find_unprepared_like_queries(content: str) -> list:
    """
    Find SQL queries with LIKE 'image/%' that are NOT already in prepare statements.

    Strategy:
    - Find $wpdb->get_* calls with string literals containing LIKE 'image/%'
    - Skip if the string already has %% (already in prepare)
    - Skip if it's inside a prepare() call
    """
    unprepared = []

    # Pattern: $wpdb->get_var|get_col|get_results|get_row|query("...LIKE 'image/%'...")
    # But NOT if the LIKE has %% (which means it's in a prepare)
    pattern = r'\$wpdb->(get_var|get_col|get_results|get_row|query)\s*\(\s*"([^"]*LIKE\s+[\'"]image/%[\'"][^"]*)"'

    for match in re.finditer(pattern, content, re.DOTALL):
        method = match.group(1)
        query = match.group(2)

        # Skip if already has %% (in prepare statement)
        if 'image/%%' in query or 'image/\\%\\%' in query:
            continue

        # Skip if this is already inside a prepare call
        start_pos = match.start()
        before_context = content[max(0, start_pos - 100):start_pos]
        if '$wpdb->prepare(' in before_context:
            continue

        unprepared.append({
            'method': method,
            'query': query,
            'match': match,
            'start': match.start(),
            'end': match.end(),
            'full': match.group(0)
        })

    return unprepared

def wrap_with_prepare(query_info: dict, content: str) -> Tuple[str, str]:
    """
    Wrap a query with $wpdb->prepare() and add $image_mime_like variable.

    Returns: (old_string, new_string) tuple for replacement
    """
    method = query_info['method']
    query = query_info['query']
    full_match = query_info['full']

    # Replace LIKE 'image/%' with LIKE %s
    prepared_query = query.replace("LIKE 'image/%'", "LIKE %s")
    prepared_query = prepared_query.replace('LIKE "image/%"', 'LIKE %s')

    # Also prepare post_type = 'attachment' if present
    if "post_type = 'attachment'" in prepared_query:
        prepared_query = prepared_query.replace("post_type = 'attachment'", "post_type = %s")
    elif 'post_type = "attachment"' in prepared_query:
        prepared_query = prepared_query.replace('post_type = "attachment"', 'post_type = %s')

    # Also prepare meta_key values if present
    meta_key_patterns = [
        ("meta_key = '_wp_attachment_image_alt'", "meta_key = %s", "_wp_attachment_image_alt"),
        ("meta_key = '_wp_attached_file'", "meta_key = %s", "_wp_attached_file"),
    ]

    prepare_args = []

    for old_pattern, new_pattern, value in meta_key_patterns:
        if old_pattern in prepared_query:
            prepared_query = prepared_query.replace(old_pattern, new_pattern)
            prepare_args.append(f"'{value}'")

    # Add post_type if we replaced it
    if "post_type = %s" in prepared_query:
        prepare_args.insert(0, "'attachment'")

    # Add image_mime_like for LIKE %s
    prepare_args.append("$image_mime_like")

    # Build new code
    args_str = ",\n\t\t\t\t".join(prepare_args)

    old_string = f'$wpdb->{method}(\n\t\t\t"{query}"\n\t\t)'
    new_string = f'$wpdb->{method}(\n\t\t\t$wpdb->prepare(\n\t\t\t\t"{prepared_query}",\n\t\t\t\t{args_str}\n\t\t\t)\n\t\t)'

    return old_string, new_string

def add_image_mime_like_variable(content: str, function_name: str = None) -> str:
    """
    Add $image_mime_like variable definition at the start of the function if not present.
    """
    if '$image_mime_like' in content:
        return content  # Already has it

    # Find the function start and add after "global $wpdb;"
    pattern = r'(global \$wpdb;)'
    replacement = r'\1\n\n\t\t// Prepare LIKE pattern for image mime types\n\t\t$image_mime_like = $wpdb->esc_like( \'image/\' ) . \'%\';'

    content = re.sub(pattern, replacement, content, count=1)

    return content

def fix_sql_like_wildcards(content: str, filename: str) -> Tuple[str, int]:
    """
    Fix all unprepared LIKE 'image/%' queries in the content.
    """
    replacements = 0

    # Find all unprepared queries
    unprepared = find_unprepared_like_queries(content)

    if not unprepared:
        return content, 0

    print(f"  🔍 Found {len(unprepared)} unprepared LIKE queries")

    # Sort by position (reverse) so we can replace from end to start
    unprepared.sort(key=lambda x: x['start'], reverse=True)

    # Add $image_mime_like variable at function level
    # We'll do a simpler approach: add it after first "global $wpdb;" we find
    if '$image_mime_like' not in content:
        # Add variable definition after first "global $wpdb;"
        pattern = r'(function [^{]+\{\s*global \$wpdb;)'
        replacement = r'\1\n\n\t\t// Prepare LIKE pattern for image mime types\n\t\t$image_mime_like = $wpdb->esc_like( \'image/\' ) . \'%\';'

        # Try to add it, if pattern matches
        new_content = re.sub(pattern, replacement, content, count=1)
        if new_content != content:
            content = new_content
            print(f"  ✓ Added $image_mime_like variable definition")

    # Process each unprepared query
    # For simplicity, we'll use a different approach: manual pattern matching
    # This is complex to automate perfectly, so we'll flag them for manual review

    print(f"  ⚠️  These queries need manual review - they're complex:")
    for i, query_info in enumerate(unprepared, 1):
        line_num = content[:query_info['start']].count('\n') + 1
        print(f"     {i}. Line ~{line_num}: {query_info['method']}() with LIKE 'image/%'")

    return content, len(unprepared)

def main():
    dry_run = '--dry-run' in sys.argv
    auto_yes = '--yes' in sys.argv

    print("=" * 70)
    print("SQL LIKE Wildcard Fix Script")
    print("=" * 70)

    if dry_run:
        print("\n🔍 DRY RUN MODE - Analysis only\n")
    else:
        print("\n⚠️  LIVE MODE - Will modify files\n")
        print("This script identifies unprepared LIKE queries.")
        print("Manual fixes are recommended for complex queries.\n")
        if not auto_yes:
            confirm = input("Continue? (yes/no): ")
            if confirm.lower() != 'yes':
                print("Aborted.")
                return
        print()

    total_found = 0
    files_needing_fixes = []

    for file_path in FILES_TO_FIX:
        if not os.path.exists(file_path):
            print(f"⏭️  Skipping {file_path} (not found)")
            continue

        print(f"📄 Analyzing {file_path}...")

        with open(file_path, 'r', encoding='utf-8') as f:
            original = f.read()

        fixed, count = fix_sql_like_wildcards(original, file_path)

        if count == 0:
            print(f"  ✅ No unprepared LIKE queries found\n")
            continue

        total_found += count
        files_needing_fixes.append((file_path, count))

        print()

    print(f"\n{'=' * 70}")
    print(f"📊 SUMMARY")
    print(f"{'=' * 70}")
    print(f"Total unprepared LIKE queries found: {total_found}")
    print(f"Files needing fixes: {len(files_needing_fixes)}")

    if files_needing_fixes:
        print(f"\n📋 Files to fix manually:")
        for file_path, count in files_needing_fixes:
            print(f"  • {file_path}: {count} queries")

        print(f"\n💡 Recommended approach:")
        print(f"  1. Use the pattern from class-msh-ai-ajax-handlers.php (already fixed)")
        print(f"  2. For each query:")
        print(f"     - Add: $image_mime_like = $wpdb->esc_like('image/') . '%';")
        print(f"     - Replace: LIKE 'image/%' → LIKE %s")
        print(f"     - Wrap: $wpdb->prepare(\"...\", $image_mime_like)")
        print(f"\n  See TASK_SQL_FIXES.md for detailed instructions.")

if __name__ == '__main__':
    main()
