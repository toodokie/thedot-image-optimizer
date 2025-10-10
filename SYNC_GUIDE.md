# Sync Guide: Standalone → Test Site

## Overview
The **standalone repository** contains the latest code, while the **Local test site** may lag behind. This guide helps you sync changes.

---

## Quick Sync Command

```bash
# Sync all core files from standalone to test site
STANDALONE="/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer"
TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

# Copy PHP files
cp "$STANDALONE/admin/image-optimizer-admin.php" "$TEST_SITE/admin/"
cp "$STANDALONE/msh-image-optimizer.php" "$TEST_SITE/"

# Copy includes (classes)
cp -r "$STANDALONE/includes/"*.php "$TEST_SITE/includes/"

# Copy assets
cp "$STANDALONE/assets/js/image-optimizer-modern.js" "$TEST_SITE/assets/js/"
cp "$STANDALONE/assets/css/image-optimizer-admin.css" "$TEST_SITE/assets/css/"

# Flush WordPress cache
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" cache flush

echo "✅ Sync complete! Hard refresh your browser (Cmd+Shift+R)"
```

---

## Individual File Sync

### Admin UI
```bash
cp /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/admin/image-optimizer-admin.php \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/"
```

### JavaScript
```bash
cp /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/assets/js/image-optimizer-modern.js \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/js/"
```

### CSS
```bash
cp /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/assets/css/image-optimizer-admin.css \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/assets/css/"
```

### PHP Classes
```bash
# Sync all includes
cp /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/includes/*.php \
   "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/"
```

---

## After Syncing

### 1. Flush Cache
```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" cache flush
```

### 2. Clear Browser Cache
- **Mac**: `Cmd + Shift + R`
- **Windows**: `Ctrl + Shift + R`
- Or use incognito/private mode

### 3. Restart Local Site (if needed)
If PHP OPcache is holding old files:
1. Open **Local** app
2. Select `thedot-optimizer-test` site
3. Click **"Stop Site"**
4. Wait 5 seconds
5. Click **"Start Site"**

---

## Verification

### Check Plugin Version
```bash
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" plugin list --name=msh-image-optimizer
```

### Check File Timestamps
```bash
# See when files were last modified
ls -lh "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php"
```

### Verify Specific Features
```bash
# Check if old button removed
grep "trigger-incremental-refresh" \
  "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php" \
  || echo "✅ Old button removed"

# Check if diagnostics card added
grep "diagnostics-card" \
  "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/admin/image-optimizer-admin.php" \
  && echo "✅ Diagnostics card present"
```

---

## Common Issues

### Issue: Changes not showing after sync
**Solution**:
1. Hard refresh browser (`Cmd + Shift + R`)
2. Flush WP cache: `wp cache flush`
3. Restart Local site (stops/starts PHP)

### Issue: JavaScript not updating
**Solution**:
1. Check browser DevTools → Network tab
2. Look for `image-optimizer-modern.js?ver=X.X.X`
3. Clear browser cache completely
4. May need to bump version in `msh-image-optimizer.php`

### Issue: CSS not applying
**Solution**:
1. Check for cached CSS in browser
2. Verify file copied: `ls -lh assets/css/image-optimizer-admin.css`
3. Check WP admin → Settings → General → force HTTPS (mixed content issues)

---

## Sync Checklist

Before testing new features on the Local site:

- [ ] Sync admin PHP file
- [ ] Sync JavaScript files
- [ ] Sync CSS files
- [ ] Sync PHP class files (if modified)
- [ ] Flush WordPress cache
- [ ] Hard refresh browser
- [ ] Verify changes visible in UI
- [ ] Check browser console for errors

---

## Auto-Sync Script (Optional)

Save this as `sync-to-test.sh` in the standalone repo:

```bash
#!/bin/bash
STANDALONE="/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer"
TEST_SITE="/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer"

echo "🔄 Syncing standalone → test site..."

# Sync files
rsync -av --exclude='*.md' "$STANDALONE/" "$TEST_SITE/"

# Flush cache
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public"
"/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp" cache flush

echo "✅ Sync complete! Hard refresh your browser."
```

Make it executable:
```bash
chmod +x sync-to-test.sh
```

Run it:
```bash
./sync-to-test.sh
```

---

## Notes

- **Always sync from standalone → test site** (standalone is the source of truth)
- Test site is for validation only, don't make changes there
- After major changes, consider restarting Local site to clear PHP OPcache
- Keep wp-config.php changes separate (DB socket path)
