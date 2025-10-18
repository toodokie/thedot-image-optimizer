# AI Gate Testing Guide

## Overview

This guide walks through testing the Phase 1 AI integration foundation - specifically the access control gate logic that determines whether AI features should be enabled based on plan tier, BYOK (Bring Your Own Key), and feature toggles.

## Test Environment

- **Test Site**: Sterling & Associates Law (http://sterling-law-firm.local/)
- **Test Stub**: `/wp-content/mu-plugins/msh-ai-test-stub.php`
- **Test Page**: http://sterling-law-firm.local/test-ai-gate.php
- **Plugin Version**: 1.1.1 (with AI service foundation)
- **Critical Bug Fixed**: Option name mismatch (`msh_ai_features` vs `msh_ai_enabled_features`) - See [Bugfix Summary](../development/BUGFIX_AI_OPTION_NAME_MISMATCH.md)

## Test Stub Implementation

The test stub hooks into the `msh_ai_generate_metadata` filter and returns simple test metadata prefixed with `[AI Test]` to verify the gate logic works without requiring actual OpenAI API integration.

**Location**: `/wp-content/mu-plugins/msh-ai-test-stub.php`

When AI access is granted, uploaded images will get metadata like:
- **Title**: `[AI Test - BYOK] Professional Service`
- **Alt Text**: `AI-generated test alt text (ai_starter plan, byok mode)`

## Test Scenarios

### Scenario 1: Free User (Default State)

**Expected Behavior**: AI access DENIED

1. Visit: http://sterling-law-firm.local/test-ai-gate.php
2. Verify status shows:
   - AI Access: ❌ DENIED
   - Plan Tier: `free`
   - Denial Reason: `upgrade_required` or `manual_mode`

3. Upload a test image
4. Verify metadata uses heuristic generation (no `[AI Test]` prefix)

### Scenario 2: Paid Plan Activation

**Expected Behavior**: AI access GRANTED via bundled credits

1. On test page, click **AI Starter** button (sets `msh_plan_tier` to `ai_starter`)
2. Click **Assist** mode button (sets `msh_ai_mode` to `assist`)
3. Click **Toggle 'meta'** to enable metadata feature
4. Verify status shows:
   - AI Access: ✅ GRANTED
   - Access Mode: BUNDLED
   - Plan Tier: `ai_starter`
   - Enabled Features: `meta`

5. Upload a test image
6. Verify metadata includes `[AI Test - BUNDLED]` prefix

### Scenario 3: BYOK (Bring Your Own Key)

**Expected Behavior**: AI access GRANTED regardless of plan tier

1. Click **Free** plan tier button (reset to free)
2. Click **Set Test Key** button (adds test API key)
3. Ensure AI mode is **Assist** or **Hybrid**
4. Ensure **meta** feature is enabled
5. Verify status shows:
   - AI Access: ✅ GRANTED
   - Access Mode: BYOK
   - Plan Tier: `free`
   - API Key: `sk-test...cdef`

6. Upload a test image
7. Verify metadata includes `[AI Test - BYOK]` prefix

### Scenario 4: Manual Mode Override

**Expected Behavior**: AI access DENIED even with paid plan

1. Set plan tier to **AI Pro**
2. Click **Manual** mode button
3. Verify status shows:
   - AI Access: ❌ DENIED
   - Denial Reason: `manual_mode`

4. Upload a test image
5. Verify metadata uses heuristic generation (no AI)

### Scenario 5: Feature Flag Disabled

**Expected Behavior**: AI access DENIED when 'meta' feature is disabled

1. Set plan tier to **AI Starter**
2. Set mode to **Assist**
3. Click **Toggle 'meta'** to disable the feature
4. Verify status shows:
   - AI Access: ❌ DENIED
   - Denial Reason: `feature_disabled`

5. Upload a test image
6. Verify metadata uses heuristic generation

### Scenario 6: Graceful Fallback

**Expected Behavior**: Heuristic generation when AI filter returns null

1. Temporarily disable the test stub:
   - Rename `/wp-content/mu-plugins/msh-ai-test-stub.php` to `.php.disabled`
2. Set up AI access (paid tier + assist mode + meta feature)
3. Upload a test image
4. Verify metadata still generates using heuristic system
5. No errors or warnings in logs

## Verification Checklist

After running all scenarios:

- [ ] Free users are blocked from AI (upgrade_required)
- [ ] Paid plan tiers (ai_starter, ai_pro, ai_business) grant AI access
- [ ] BYOK grants AI access regardless of plan tier
- [ ] Manual mode blocks AI even with paid plan
- [ ] Disabled 'meta' feature blocks AI metadata generation
- [ ] AI metadata includes `[AI Test]` prefix when stub is active
- [ ] Heuristic generation works when AI is denied
- [ ] Heuristic generation works when AI filter returns null (graceful fallback)
- [ ] No PHP errors or warnings in debug.log

## WP-CLI Commands (Alternative Testing)

If WP-CLI is properly configured:

```bash
# Check AI status
wp msh ai-status

# Set plan tier
wp msh plan set ai_starter
wp msh plan set free

# Set AI mode
wp option update msh_ai_mode "assist"
wp option update msh_ai_mode "manual"

# Set BYOK API key
wp option update msh_ai_api_key "sk-test-1234567890abcdef"

# Enable features
wp option update msh_ai_features '["meta","vision","duplicate"]'

# Clear all AI settings
wp msh plan clear
wp option delete msh_ai_mode
wp option delete msh_ai_api_key
wp option delete msh_ai_features
```

## Debug Logging

The test stub logs every AI generation attempt:

```
[MSH AI Test Stub] Generated metadata for attachment 123 | Access: byok | Plan: free | Mode: assist
```

Check logs at: `/wp-content/debug.log` (if `WP_DEBUG_LOG` is enabled)

## Next Steps After Validation

Once all test scenarios pass:

1. **Document Results** - Record which scenarios passed/failed
2. **OpenAI Integration** - Implement real `MSH_OpenAI_Connector` class
3. **Settings UI Enhancement** - Add conditional rendering for free vs paid users
4. **Version Bump** - Update to v1.2.0
5. **Phase 2 Planning** - Design credit tracking system

## Troubleshooting

### Issue: Test stub not running

**Solution**: Check that file exists at `/wp-content/mu-plugins/msh-ai-test-stub.php` (mu-plugins must be in that exact directory)

### Issue: AI access always denied

**Solution**: Check all three requirements:
1. Plan tier is `ai_starter`, `ai_pro`, or `ai_business` (OR BYOK key is set)
2. AI mode is `assist` or `hybrid` (NOT `manual`)
3. Feature `meta` is enabled in `msh_ai_features` array

### Issue: Metadata still heuristic even when granted

**Solution**:
- Verify test stub file is named `.php` not `.php.txt`
- Check mu-plugins directory is correct location
- Ensure no fatal errors in stub file (check debug.log)

### Issue: Can't access test page

**Solution**: Verify file exists at `/app/public/test-ai-gate.php` and site is running (http://sterling-law-firm.local/)

---

## Test Results Log

Record results here:

**Date**: _____________
**Tester**: _____________

| Scenario | Pass/Fail | Notes |
|----------|-----------|-------|
| Free User | ☐ | |
| Paid Plan | ☐ | |
| BYOK | ☐ | |
| Manual Mode Override | ☐ | |
| Feature Flag Disabled | ☐ | |
| Graceful Fallback | ☐ | |

**Overall Status**: ☐ Ready for OpenAI Integration  ☐ Needs Fixes

**Issues Found**:
