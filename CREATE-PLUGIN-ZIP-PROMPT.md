# Prompt for Creating WordPress Plugin ZIP

## Task
Create a clean, installable WordPress plugin ZIP file from the msh-image-optimizer-standalone repository.

## Critical Requirements

### 1. WordPress ZIP Structure
WordPress requires this EXACT structure:
```
msh-image-optimizer.zip
└── msh-image-optimizer/
    ├── msh-image-optimizer.php (main plugin file with header)
    ├── readme.txt
    ├── admin/
    ├── assets/
    ├── includes/
    └── languages/
```

**NOT this:**
```
msh-image-optimizer.zip
└── msh-image-optimizer/
    └── msh-image-optimizer/  ← WRONG! No nested folder!
        ├── files...
```

### 2. Repository Issues to Avoid

**CRITICAL:** The repository has a **symlink** in the root directory:
```bash
msh-image-optimizer -> /Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer
```

This symlink MUST be excluded from the ZIP or it will:
- Create a nested `msh-image-optimizer/msh-image-optimizer/` structure
- Include 18MB of unnecessary files
- Make WordPress fail with "The plugin does not have a valid header"

### 3. Failed Attempts (DO NOT REPEAT)

#### Failed Attempt #1: rsync with exclude
```bash
rsync -a --exclude='.git' ... ./ /tmp/msh-package/msh-image-optimizer/
```
**Problem:** Rsync followed the symlink and copied the nested folder

#### Failed Attempt #2: rsync with explicit exclude
```bash
rsync -a --exclude='msh-image-optimizer' ...
```
**Problem:** Still followed the symlink

#### Failed Attempt #3: tar with exclude
```bash
tar cf - --exclude='msh-image-optimizer' ... | tar xf - -C /tmp/msh-package/
```
**Problem:** tar followed the symlink content

#### Failed Attempt #4: cp -r
```bash
cp -r admin assets includes ... /tmp/dest/
```
**Problem:** cp -r followed symlinks

### 4. Working Solution

**This command WORKED:**
```bash
rm -rf /tmp/msh-clean && \
mkdir -p /tmp/msh-clean/msh-image-optimizer && \
cp -RL admin /tmp/msh-clean/msh-image-optimizer/ && \
cp -RL assets /tmp/msh-clean/msh-image-optimizer/ && \
cp -RL includes /tmp/msh-clean/msh-image-optimizer/ && \
cp -RL languages /tmp/msh-clean/msh-image-optimizer/ && \
cp msh-image-optimizer.php readme.txt /tmp/msh-clean/msh-image-optimizer/ && \
cd /tmp/msh-clean && \
zip -q -r msh-image-optimizer-v1.2.2-DATABASE-FIX.zip msh-image-optimizer && \
mv msh-image-optimizer-v1.2.2-DATABASE-FIX.zip /Users/anastasiavolkova/msh-image-optimizer-standalone/ && \
rm -rf /tmp/msh-clean
```

**Why it worked:**
- Explicitly lists directories to copy (no wildcards that might catch symlinks)
- Uses absolute paths for each directory
- Excludes the symlink by not mentioning it

### 5. What to Include in ZIP

**Essential files/folders:**
- `admin/` - Admin interface files
- `assets/` - CSS, JS, images
- `includes/` - All PHP classes (including subdirectories)
- `languages/` - Translation files
- `msh-image-optimizer.php` - Main plugin file
- `readme.txt` - WordPress plugin readme

**DO NOT include:**
- `msh-image-optimizer/` (symlink directory)
- `msh-image-optimizer.backup.*` folders
- `*.zip` files
- `*.md` files (documentation)
- `*.json` files (except if needed for plugin functionality)
- `.git/` folder
- `.github/` folder
- `.claude/` folder
- `.local-wp/` folder
- `node_modules/`
- `vendor/`
- `composer.*`
- `tests/`
- `docs/`
- `*.sh` shell scripts
- `.DS_Store`
- `.gitignore`

### 6. Verification Steps

After creating the ZIP, verify:

```bash
# Check ZIP size (should be ~1.2-1.3MB, NOT 18MB)
ls -lh msh-image-optimizer-v1.2.2-DATABASE-FIX.zip

# Check structure (no nested folders)
unzip -l msh-image-optimizer-v1.2.2-DATABASE-FIX.zip | head -30

# Verify plugin header is readable
unzip -q msh-image-optimizer-v1.2.2-DATABASE-FIX.zip -d /tmp/verify
head -15 /tmp/verify/msh-image-optimizer/msh-image-optimizer.php
rm -rf /tmp/verify
```

**Expected results:**
- Size: ~1.3MB (NOT 18MB)
- First entry in ZIP: `msh-image-optimizer/`
- Second level should be files like `msh-image-optimizer/admin/`, NOT `msh-image-optimizer/msh-image-optimizer/`
- Plugin header should show: `Plugin Name: MSH Image Optimizer`

### 7. Error Messages to Watch For

If WordPress shows these errors, the ZIP is malformed:

- **"The plugin does not have a valid header"** = Nested folder structure or symlink followed
- **"Plugin could not be activated"** = Different issue (not ZIP structure)

### 8. Repository Location

Working directory: `/Users/anastasiavolkova/msh-image-optimizer-standalone`

Output file: `msh-image-optimizer-v1.2.2-DATABASE-FIX.zip`

## Your Task

1. Navigate to `/Users/anastasiavolkova/msh-image-optimizer-standalone`
2. Create the ZIP file using the working solution above
3. Verify the ZIP is correct (size ~1.3MB, no nested folders)
4. Report back with:
   - ZIP file size
   - First 30 lines of `unzip -l` output
   - Confirmation that plugin header is readable

## Recent Changes to Include

The ZIP must include this critical database fix in `includes/class-msh-image-usage-index.php` around line 696:

```php
// Skip if no variation matched (defensive check)
if ( $matched_variation === null ) {
    continue;
}
```

This fix prevents the "Column 'url_variation' cannot be null" database error during batch optimization.

---

**Good luck! The user needs this ASAP to continue testing.**
