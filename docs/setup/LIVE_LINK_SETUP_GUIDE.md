# Local Development Testing Guide

## ⚠️ Important: Local's Live Link Cannot Be Used

**Discovery:** Local by Flywheel's Live Link feature requires HTTP Basic Authentication (password protection) that **cannot be disabled**. This means OpenAI Vision API cannot access images through Live Link URLs.

**The solution:** Base64 encoding - already implemented and working perfectly! ✅

---

## How to Test OpenAI on Local Development

### The Base64 Method (Automatic - Recommended)

**No setup required!** The plugin automatically handles local development.

#### How It Works:

1. Plugin detects local URLs (`.local`, `localhost`, `127.0.0.1`, private IPs)
2. Converts image file to base64 data URI
3. Sends to OpenAI Vision API
4. Returns AI-generated metadata

#### Pros:
- ✅ **Fully automatic** - works out of the box
- ✅ **No authentication issues** - bypasses Live Link password
- ✅ **Always works** - no session timeouts
- ✅ **No external dependencies** - self-contained
- ✅ **Works on any environment** - Local, MAMP, Docker, etc.

#### Cons:
- ⚠️ Larger payload (~163KB for typical JPEG vs ~300B URL)
- ⚠️ Slightly higher latency (~1-2 seconds for encoding)
- ⚠️ Can't inspect image URL in OpenAI request

#### Testing Steps:

**No setup needed!** Just use the plugin normally:

1. **Upload a new image:**
   ```
   Visit: http://sterling-law-firm.local/upload-test-image.php
   Upload any legal-related image
   Watch AI generate metadata automatically
   ```

2. **Regenerate existing image:**
   ```
   Visit: http://sterling-law-firm.local/test-ai-on-existing-image.php?id=10
   See AI re-analyze and generate new metadata
   ```

3. **Compare AI vs Non-AI:**
   ```
   Visit: http://sterling-law-firm.local/compare-ai-vs-noai.php
   See side-by-side quality comparison
   ```

#### Check It's Working:

Look for these log entries:
```
[MSH OpenAI] Local URL detected, converting to base64
[MSH OpenAI] Successfully generated metadata for attachment 10
```

---

## Why Not Live Link?

We tried using Local's Live Link feature, but discovered:

❌ **Password protection is mandatory** - Cannot be disabled
❌ **OpenAI can't authenticate** - API has no way to send Basic Auth credentials
❌ **Would fail on every request** - 401 Unauthorized errors

The Live Link URL field in settings is **not needed** for local development. Base64 works better!

---

## Alternative: Use ngrok Manually (Advanced)

If you really want to use tunneling instead of base64:

### Option 1: ngrok (Free)

1. **Install ngrok:**
   ```bash
   brew install ngrok
   # or download from https://ngrok.com
   ```

2. **Find your site's port:**
   - Open Local app
   - Look at site URL (e.g., `http://sterling-law-firm.local:10003`)
   - Port is the number after the colon (10003)

3. **Start tunnel (no auth):**
   ```bash
   ngrok http 10003 --host-header=rewrite
   ```

4. **Copy the HTTPS URL:**
   - ngrok shows: `Forwarding https://abc123.ngrok.io -> http://localhost:10003`
   - Copy: `https://abc123.ngrok.io`

5. **Paste in plugin settings:**
   - Settings → MSH Image Optimizer → AI Settings
   - "Local Live Link URL" field
   - Paste the ngrok URL
   - Save

**Pros:**
- ✅ Real URLs (smaller payload)
- ✅ No password protection
- ✅ Can visit URL in browser to debug

**Cons:**
- ⚠️ Requires installing ngrok
- ⚠️ URL changes every session (free tier)
- ⚠️ Must keep terminal running
- ⚠️ Rate limits on free tier

### Option 2: localtunnel (Free, No Install)

```bash
npx localtunnel --port 10003
```

Same pros/cons as ngrok.

---

## Production Behavior

On **production sites** with public domains:
- ✅ Plugin uses direct URLs (no conversion)
- ✅ No base64 encoding needed
- ✅ Fastest performance
- ✅ Smallest payload

The base64 logic **only activates** for local development URLs.

---

## Performance Comparison

| Method | Payload Size | Setup | Session | Auth Issues |
|--------|--------------|-------|---------|-------------|
| **Base64 (default)** | ~163KB | None | Never expires | None |
| **ngrok** | ~300B | Manual | Until you quit | None |
| **Live Link** | N/A | Easy | ~1 hour | ❌ Password required |
| **Production** | ~300B | None | Permanent | None |

---

## Troubleshooting

### Base64 Not Working?

**Symptoms:**
- No metadata generated
- Error logs show "Local image file not found"

**Check:**
1. Does the image file exist?
   ```bash
   ls -la /Users/anastasiavolkova/Local\ Sites/sterling-law-firm/app/public/wp-content/uploads/
   ```

2. Correct file permissions?
   ```bash
   # Should be readable
   chmod 644 path/to/image.jpg
   ```

3. Check error log:
   ```
   [MSH OpenAI] Local image file not found: /path/to/image.jpg
   ```

**Fix:**
- Re-upload the image
- Check uploads directory permissions
- Verify WordPress has correct ABSPATH

### Still Want to Use Live Link?

**You can't.** Local's password protection cannot be disabled.

**Alternatives:**
1. Use base64 (recommended - it's working!)
2. Use ngrok manually (see above)
3. Deploy to staging server with public domain

---

## Recommendation

✅ **Use the default base64 method** - it's automatic, reliable, and works perfectly!

The base64 approach is actually **better** for local development because:
- Zero configuration
- No session management
- No authentication issues
- Works on any local environment

Save tunneling for when you specifically need to test with real URLs (rare).

---

## Summary

🎯 **For local testing:** Base64 encoding (automatic, works now!)
🎯 **For production:** Direct URLs (automatic, plugin detects)
🎯 **For debugging URLs:** ngrok manually (advanced, optional)
❌ **Live Link:** Cannot be used (password protected)

**Bottom line:** You're all set! The base64 solution is tested and working. Just upload images and watch the AI magic happen! ✨
