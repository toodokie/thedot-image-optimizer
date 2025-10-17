# Testing Guide: AI Metadata Bulk Regeneration

## Step 1: Pull Latest Code

### Option A: If you're on a different machine or fresh clone
```bash
cd /path/to/your/wordpress/wp-content/plugins/msh-image-optimizer
git pull origin main
```

### Option B: If you're on this machine (current state)
The code is already on your machine since we just committed it. You just need to ensure the WordPress site can see it.

**Current plugin location on your machine:**
```
/Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer/
```

**Your WordPress site location:**
```
/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/
```

## Step 2: Update the Plugin in WordPress

You have two options:

### Option 1: Copy the Updated Files (Quick)
```bash
# Navigate to your WordPress plugins directory
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/"

# Remove old plugin files
rm -rf msh-image-optimizer

# Copy updated plugin from standalone repo
cp -r /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer ./
```

### Option 2: Symlink (Better for Development)
```bash
# Navigate to your WordPress plugins directory
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/"

# Remove old plugin
rm -rf msh-image-optimizer

# Create symlink to standalone repo
ln -s /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer ./msh-image-optimizer
```

**Symlink benefits:**
- Changes in standalone repo immediately reflect in WordPress
- No need to copy files repeatedly
- Easy to test local edits

## Step 3: Verify Installation

1. **SSH into Local site:**
   ```bash
   # Using Local's WP-CLI
   /Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp plugin list
   ```

2. **Check for the new files:**
   ```bash
   ls -la "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/includes/" | grep ai
   ```

   **Should see:**
   - `class-msh-ai-ajax-handlers.php` (NEW)
   - `class-msh-ai-service.php` (existing)
   - `class-msh-metadata-regeneration-background.php` (existing)
   - `class-msh-openai-connector.php` (existing)

## Step 4: Access the WordPress Dashboard

1. **Open Local by Flywheel app**

2. **Start the thedot-optimizer-test site** (if not running)

3. **Click "Admin" button** or go to:
   ```
   http://thedot-optimizer-test.local/wp-admin
   ```

4. **Navigate to Image Optimizer:**
   - Left sidebar → Media → **Image Optimizer**
   - Or direct URL: `http://thedot-optimizer-test.local/wp-admin/media.php?page=msh-image-optimizer`

## Step 5: Test the AI Regeneration UI

### You should see:

1. **New Dashboard Card** (below Optimization Context):
   ```
   ┌─────────────────────────────────────────────┐
   │ AI Metadata Regeneration                    │
   │ Bulk regenerate metadata using AI           │
   ├─────────────────────────────────────────────┤
   │ Credits Available    Last Run    This Month │
   │     [100]            [Never]       [0]      │
   │  [AI Starter]          [-]    [credits used]│
   ├─────────────────────────────────────────────┤
   │ [Start AI Regeneration]  [AI Settings]      │
   └─────────────────────────────────────────────┘
   ```

2. **Click "Start AI Regeneration" button**
   - Modal should open
   - See scope options (All/Published/Missing)
   - See mode options (Fill empty/Overwrite)
   - See field checkboxes (Title/Alt/Caption/Description)
   - See credit estimate

### Initial Check:
Open browser console (F12) and check for errors. You should see:
```javascript
// No errors
// AIRegeneration module initialized
```

## Step 6: Configure Test Settings

### Set Plan Tier (if not already set):
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_plan_tier "ai_starter"
```

### Initialize Credits:
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_ai_credit_balance 100
```

### Verify AI Service is Active:
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_ai_access_method
# Should return: bundled
```

## Step 7: Run a Test Job

### Small Test (5 images):
1. In modal, select:
   - Scope: "Images with missing metadata"
   - Mode: "Fill empty fields only"
   - Fields: Check "Title" and "Alt Text" only

2. Check the estimate:
   - Should show: "Images to process: X"
   - Should show: "Estimated credits: X"
   - Should show: "Credits available: 100"

3. Click "Start Regeneration"

4. **Watch for:**
   - Modal closes
   - Progress widget appears
   - Progress bar animates
   - Stats update every 2 seconds
   - Log messages appear
   - Credits decrement in real-time

### Monitor Progress:
Watch the progress widget update:
```
┌─────────────────────────────────────────┐
│ Regeneration in Progress    [Running]   │
├─────────────────────────────────────────┤
│ ████████░░░░░░░░░░░░░░░░░░░░░ 35%      │
├─────────────────────────────────────────┤
│ Processed: 3/8  Success: 2  Credits: 2  │
│ Recent Updates:                         │
│ • 14:32:15 Processing image ID 1245     │
│ • 14:32:13 Metadata generated for 1244  │
├─────────────────────────────────────────┤
│ [Pause] [Cancel]                        │
└─────────────────────────────────────────┘
```

## Step 8: Test Job Controls

### Test Pause:
1. Click "Pause" during job execution
2. Verify:
   - Status badge changes to "Paused" (pink)
   - "Pause" button hides
   - "Resume" button appears
   - Polling continues

### Test Resume:
1. Click "Resume"
2. Verify:
   - Status badge changes to "Running" (orange)
   - Job continues processing
   - Progress updates resume

### Test Cancel:
1. Click "Cancel"
2. Confirm in dialog
3. Verify:
   - Status badge changes to "Cancelled" (red)
   - Final stats shown
   - Polling stops

## Step 9: Verify Credit Tracking

### Check credit decrement:
```bash
# Before job
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_ai_credit_balance
# Should show: 100

# After job (processed 8 images)
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_ai_credit_balance
# Should show: 92 (100 - 8)
```

### Check usage tracking:
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_ai_credit_usage --format=json
# Should show: {"2025-10": 8}
```

### Check job history:
```bash
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_metadata_regen_jobs --format=json
# Should show job record with stats
```

## Step 10: Debugging

### Enable WordPress Debug Mode:
```bash
# Edit wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Debug Log:
```bash
tail -f "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/debug.log"
```

### Check Browser Console:
Open DevTools (F12) → Console tab

Look for:
- ✅ `AIRegeneration.init()` called
- ✅ AJAX requests to `admin-ajax.php`
- ✅ Response data (job status, counts, estimates)
- ❌ Any JavaScript errors

### Check Network Tab:
DevTools → Network tab → Filter: "XHR"

Monitor AJAX requests:
- `msh_get_ai_regen_counts` → Should return image counts
- `msh_estimate_ai_regeneration` → Should return estimate
- `msh_start_ai_regeneration` → Should return job_id
- `msh_get_ai_regeneration_status` → Should return job state (every 2 sec)

## Troubleshooting

### Issue: Dashboard card not visible
**Fix:**
```bash
# Clear WordPress cache
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp cache flush

# Verify plugin is active
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp plugin list | grep msh-image-optimizer
```

### Issue: Modal doesn't open
**Fix:**
1. Check browser console for JavaScript errors
2. Verify jQuery is loaded: `typeof jQuery` in console should return `"function"`
3. Check if CSS is loaded: Inspect element, should see `.ai-regen-modal` styles

### Issue: "Credits available" shows "-"
**Fix:**
```bash
# Initialize credit balance
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_ai_credit_balance 100

# Set plan tier
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_plan_tier "ai_starter"

# Refresh page
```

### Issue: AJAX request fails (403 Forbidden)
**Fix:**
1. Check nonce: `console.log(mshImageOptimizer.nonce)`
2. Verify user has admin permissions
3. Check if AJAX URL is correct: `console.log(mshImageOptimizer.ajaxurl)`

### Issue: Job doesn't start
**Fix:**
```bash
# Check if WP-Cron is running
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp cron event list

# Manually trigger queue processor
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp cron event run msh_process_metadata_regen_queue
```

### Issue: Progress doesn't update
**Fix:**
1. Check browser console for polling errors
2. Verify 2-second polling is active: `setInterval` should be running
3. Check Network tab for `msh_get_ai_regeneration_status` requests every 2 seconds

## Quick Commands Reference

```bash
# Go to WordPress plugins directory
cd "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/"

# Copy updated plugin
cp -r /Users/anastasiavolkova/msh-image-optimizer-standalone/msh-image-optimizer ./

# Check plugin files
ls -la msh-image-optimizer/includes/ | grep ai

# Initialize credits
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option update msh_ai_credit_balance 100

# Check credit balance
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_ai_credit_balance

# View debug log
tail -f "/Users/anastasiavolkova/Local Sites/thedot-optimizer-test/app/public/wp-content/debug.log"

# Check job state
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option get msh_metadata_regen_queue_state --format=json

# List all AI options
/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/posix/wp option list | grep msh_ai
```

## Success Criteria

✅ **UI Loads:**
- Dashboard card visible
- Credit stats populated
- "Start" button works

✅ **Modal Works:**
- Opens on button click
- Shows image counts
- Calculates estimates
- Validates credits

✅ **Job Execution:**
- Starts successfully
- Progress widget appears
- Stats update every 2 seconds
- Log messages appear

✅ **Job Controls:**
- Pause works
- Resume works
- Cancel works (with confirmation)

✅ **Credit Tracking:**
- Balance decrements per image
- Monthly usage tracks
- Dashboard updates in real-time

✅ **Completion:**
- Job finishes
- Final stats persist
- Polling stops
- Credit balance correct

---

## Desktop Notifications

1. In **Step 1**, click **Enable Notifications** (shown only if the browser supports the Notification API).
2. Approve the browser permission prompt and confirm the status text flips to “Notifications enabled.”
3. **Secure-origin requirement:** browser notifications only work on `https://…` or `http://localhost`. For Local URLs like `http://thedot-optimizer-test.local`, either trust the SSL cert in Local (switching the site to HTTPS) or enable the Chromium flag `unsafely-treat-insecure-origin-as-secure` for that origin before testing.
4. Kick off an Analyze or Optimize run, switch to another tab, and wait for completion. You should receive a desktop toast summarizing the outcome; if notifications were blocked, the status message will call it out and the enable button will be hidden.

---

## Next Steps After Testing

Once basic testing passes:

1. **Test edge cases:**
   - Insufficient credits mid-job
   - Page refresh during active job
   - Multiple concurrent jobs (should block)

2. **Test with larger datasets:**
   - 50+ images
   - Various image types (JPG, PNG, WebP)
   - Mixed metadata states

3. **Performance testing:**
   - Monitor memory usage
   - Check WP-Cron execution
   - Verify no PHP timeouts

4. **User experience polish:**
   - Test on different screen sizes
   - Verify accessibility (keyboard navigation)
   - Check for any UI glitches
