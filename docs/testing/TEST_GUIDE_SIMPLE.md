# Simple Test Guide - October 15, 2025

## 🚀 Quick Setup

```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
WP="/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp"

# Start fresh
$WP transient delete --all
$WP db query "DELETE FROM wp_postmeta WHERE meta_key LIKE 'msh_%' OR meta_key LIKE '_msh_%'"
$WP db query "TRUNCATE TABLE wp_msh_image_usage_index"
```

---

## ✅ What to Test (5 Steps)

### 1. Run Analyzer
- Open WP Admin → Media → Image Optimizer
- Click "Run Analyzer"
- ✅ Check: Business context shows "Northwind" (NOT "Hamilton")
- ✅ Check: All images detected

### 2. Check Key Filenames

```bash
# The 3 critical test cases
$WP post meta get 1692 _msh_suggested_filename  # spectacles
$WP post meta get 1628 _msh_suggested_filename  # triforce
$WP post meta get 1045 _msh_suggested_filename  # unicorn
```

**Expected:**
- Spectacles: `spectacles-clearing-minneapolis-1692.gif` ✅
- Triforce: `triforce-wallpaper-1628.jpg` (no location) ✅
- Unicorn: `unicorn-wallpaper-1045.jpg` (no location) ✅

**NOT:**
- Spectacles: `spectacles-clearing-spectacles-clearing-spectacles-...` ❌
- Triforce: `triforce-wallpaper-minneapolis-minneapolis-minneapolis.jpg` ❌

### 3. Optimize 3 Images
- Select spectacles, triforce, unicorn
- Click "Optimize Selected"
- Wait for completion
- ✅ Check: No errors, files renamed

### 4. Verify WebP & Filesystem

```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/uploads"

# Check spectacles directory
ls -lh 2014/01/ | grep spectacles

# Check for orphaned WebP files
find . -name "*.webp" -type f | while read webp; do
  base="${webp%.webp}"
  if [[ ! -f "${base}.jpg" && ! -f "${base}.jpeg" && ! -f "${base}.png" && ! -f "${base}.gif" ]]; then
    echo "ORPHANED: $webp"
  fi
done
```

**Expected:**
- ✅ Main files renamed
- ✅ WebP files have matching names
- ❌ No orphaned WebP with old names

### 5. Check Usage Index

```bash
$WP db query "SELECT COUNT(*) as entries FROM wp_msh_image_usage_index"
```

**Expected:** > 0 entries (NOT zero!)

---

## 🐛 The 3 Critical Bugs to Verify

### Bug #1: Recursive Duplication (FIXED)
```bash
$WP post meta get 1692 _wp_attached_file
```
✅ Should NOT have: `spectacles-clearing-spectacles-clearing-spectacles-...`

### Bug #2: Location Spam (FIXED)
```bash
$WP post meta get 1628 _wp_attached_file
```
✅ Wallpapers should NOT have location suffix

### Bug #3: Empty Usage Index (TO VERIFY)
```bash
$WP db query "SELECT COUNT(*) FROM wp_msh_image_usage_index"
```
✅ Should be > 0, NOT 0

---

## 🚨 What to Look For

**GOOD:**
- Clean filenames without duplication
- Wallpapers have no location suffix
- WebP files match renamed source files
- Usage index has entries
- No errors in browser console
- No errors in debug log

**BAD:**
- Recursive duplication (spectacles-clearing-spectacles-clearing-...)
- Location spam (minneapolis-minneapolis-minneapolis)
- Orphaned WebP files with old names
- Empty usage index (0 entries)
- Broken image previews
- JavaScript errors

---

## 📝 Quick Status Check

```bash
# One command to check everything
$WP eval "
  echo 'Total images: ' . wp_count_posts('attachment')->inherit . PHP_EOL;
  echo 'Optimized: ' . get_option('msh_total_optimized_images', 0) . PHP_EOL;
  echo 'Usage index entries: ' . \$wpdb->get_var('SELECT COUNT(*) FROM wp_msh_image_usage_index') . PHP_EOL;
  echo PHP_EOL . 'Test Cases:' . PHP_EOL;
  echo '  Spectacles (1692): ' . get_post_meta(1692, '_wp_attached_file', true) . PHP_EOL;
  echo '  Triforce (1628): ' . get_post_meta(1628, '_wp_attached_file', true) . PHP_EOL;
  echo '  Unicorn (1045): ' . get_post_meta(1045, '_wp_attached_file', true) . PHP_EOL;
"
```

---

## ✅ Pass/Fail

**PASS if:**
- ✅ No recursive duplication
- ✅ Location logic works (wallpapers have no location)
- ✅ WebP files aligned with renamed files
- ✅ Usage index populated (not empty)
- ✅ No errors

**FAIL if:**
- ❌ Recursive duplication found
- ❌ All files get location suffix (spam)
- ❌ Orphaned WebP files
- ❌ Usage index empty (0 entries)
- ❌ Errors in logs or console

---

## 🆘 If Something Breaks

```bash
# Check recent errors
tail -n 100 "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/debug.log" | grep -i error

# Clear and restart
$WP transient delete --all
$WP db query "DELETE FROM wp_postmeta WHERE meta_key LIKE 'msh_%'"
```

**Then report:** Which specific images failed and what the error was.

---

**That's it!** Just these 5 steps should tell you if it's working or not.
