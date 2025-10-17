# Local Live Link Setup Guide

## Two Ways to Test OpenAI Integration Locally

The plugin now supports **both methods** - you can choose which one works best for you!

### Method 1: Live Link (Recommended for Testing)

**Pros:**
- ✅ Cleaner API calls (just URLs, not huge base64 strings)
- ✅ Easier to debug (you can visit the Live Link URL yourself)
- ✅ More similar to production behavior
- ✅ Can test with real URLs in browser dev tools

**Cons:**
- ⚠️ Requires manual setup each session
- ⚠️ Live Link URL changes each time (on free tier)
- ⚠️ Session timeout after ~1 hour (free tier)
- ⚠️ Bandwidth limits

**Setup Steps:**

1. **Enable Live Link in Local by Flywheel**
   - Open Local app
   - Select your site (sterling-law-firm)
   - Click the "Live Link" toggle in the top right
   - **IMPORTANT:** Disable "Password Protection" (if you see it) - OpenAI can't use Basic Auth
   - Wait for Local to generate a public URL (e.g., `https://abc123.loca.lt`)
   - **Note:** If Local requires password protection, use Method 2 (Base64) instead

2. **Configure in Plugin Settings**
   - Go to: `http://sterling-law-firm.local/wp-admin/options-general.php?page=msh-image-optimizer-settings`
   - Scroll to "AI Settings" section
   - Find the "Local Live Link URL (for testing)" field
   - Paste your Live Link URL (e.g., `https://abc123.loca.lt`)
   - Click "Save Changes"

3. **Test It**
   - Visit: `http://sterling-law-firm.local/upload-test-image.php`
   - Upload an image
   - Check error logs - you should see: `[MSH OpenAI] Using Live Link URL: https://abc123.loca.lt/wp-content/uploads/...`

4. **When You're Done Testing**
   - Clear the Live Link URL field in settings
   - Or just leave it - plugin will auto-fallback to base64 if Live Link expires

---

### Method 2: Base64 Encoding (Automatic Fallback)

**Pros:**
- ✅ Fully automatic - no setup needed
- ✅ Works on any environment
- ✅ Never expires or times out
- ✅ No external dependencies

**Cons:**
- ⚠️ Larger API payloads (~163KB base64 for typical JPEG)
- ⚠️ Slightly higher latency (encoding time + larger upload)
- ⚠️ Can't inspect image URL in browser dev tools

**Setup Steps:**

No setup required! Just leave the "Live Link URL" field empty and the plugin automatically:

1. Detects local URLs (`.local`, `localhost`, `127.0.0.1`, private IPs)
2. Converts images to base64 data URIs
3. Sends to OpenAI Vision API

Check error logs - you should see:
```
[MSH OpenAI] Local URL detected, converting to base64
```

---

## Priority Order

The plugin uses this priority:

1. **Live Link URL** (if configured) - Uses the public URL
2. **Base64 encoding** (if local URL detected) - Automatic fallback
3. **Direct URL** (for production sites) - No conversion needed

---

## Testing Both Methods

Want to compare? Try this:

### Test Base64:
1. Clear the Live Link URL field → Save
2. Upload an image → Check logs for "converting to base64"

### Test Live Link:
1. Enable Live Link in Local → Copy URL
2. Paste in settings → Save
3. Upload an image → Check logs for "Using Live Link URL"

---

## Troubleshooting

### Live Link Not Working?

**Check:**
- Is Live Link enabled in Local? (Green toggle in Local app)
- Did you paste the full URL including `https://`?
- Is the session still active? (Free tier expires after ~1 hour)
- **Is password protection disabled?** OpenAI cannot authenticate with Basic Auth
- Can you visit the Live Link URL in your browser WITHOUT entering a password?

**Fix:**
- Regenerate Live Link in Local (toggle off/on)
- Disable password protection in Local's Live Link settings
- Update the URL in plugin settings
- Or clear the field to use base64 fallback

**If Local forces password protection:**
Just use Method 2 (Base64) - it's automatic and works perfectly!

### Base64 Not Working?

**Check:**
- Does the image file exist on disk?
- Correct file permissions?
- Check error log for "Local image file not found"

**Fix:**
- Verify file path in error logs
- Check `uploads` directory permissions
- Try re-uploading the image

---

## Production Behavior

On production sites with public URLs (no `.local` domain), the plugin:
- Uses direct URLs (no conversion)
- Skips both Live Link and base64
- Works exactly like calling OpenAI from any public site

---

## Recommendation

For **active development/testing**: Use Live Link
- Faster testing iterations
- More realistic behavior
- Easier debugging

For **quick tests/CI**: Use Base64
- No manual setup
- Always works
- Great for automated testing

For **production**: Neither needed (automatic direct URLs)

---

**Current Status:** Both methods tested and working! ✅
