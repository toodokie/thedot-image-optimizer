# Testing Task for MSH Image Optimizer Plugin

## Context

We've successfully bootstrapped the standalone MSH Image Optimizer WordPress plugin from the Main Street Health client project. The plugin has been:

1. ✅ Extracted from the theme and made standalone
2. ✅ Pushed to GitHub: https://github.com/toodokie/thedot-image-optimizer
3. ✅ Copied to a clean Local test site: `thedot-optimizer-test`
4. ✅ Test site populated with WordPress theme unit test data (~30 posts with images)

## Plugin Location

**Local Test Site**: `~/Local Sites/thedot-optimizer-test/app/public/`
**Plugin Path**: `~/Local Sites/thedot-optimizer-test/app/public/wp-content/plugins/msh-image-optimizer/`

## WordPress Admin Credentials

- **URL**: http://thedot-optimizer-test.local/wp-admin
- **Username**: admin
- **Password**: password

## Your Task

Monitor the testing process and document any errors or issues found. Guide me through troubleshooting if problems arise.

### Test 1: Smart Build Index

1. Navigate to **Tools > MSH Image Optimizer** in WP Admin
2. In the **Advanced Tools** section, click **"Smart Build Index"**
3. Wait for completion
4. Verify index status shows as "Healthy"
5. Check that reference distribution shows counts for Posts, Meta, Options

**Expected Result**: Index builds successfully, attachments are processed

**Document**: Any errors, warnings, or unexpected behavior

---

### Test 2: Analyze Published Images

1. Ensure the usage index is built (from Test 1)
2. In **Step 1: Optimize Published Images**, click **"Analyze Published Images"**
3. Wait for analysis to complete
4. Check the results table displays:
   - ✅ Image thumbnails
   - ✅ Current filenames
   - ✅ Priority scores (High/Medium/Low)
   - ✅ Content category/context detection
   - ✅ Status (Needs Optimization vs Optimized)
   - ✅ File sizes
   - ✅ Action buttons

**Expected Result**: Table populates with imported images and optimization data

**Document**:
- How many images were analyzed?
- Are there any images with filename suggestions?
- Any JavaScript console errors?

---

### Test 3: File Renaming (Optional)

1. Toggle **"Enable File Renaming"** to ON
2. Select an image that has a filename suggestion
3. Click the action button to apply the suggestion
4. Verify:
   - Filename changes in the filesystem
   - References in posts/pages are updated
   - No broken image links

**Expected Result**: File renames successfully, all references update

**Document**: Success/failure, any reference replacement issues

---

### Test 4: Duplicate Detection

1. Scroll to **Step 2: Clean Up Duplicate Images**
2. Click **"Quick Duplicate Scan"** (MD5-based)
3. Wait for scan to complete
4. Check if any duplicates are found
5. Review duplicate groups display:
   - ✅ Thumbnails of duplicates
   - ✅ Confidence labels (Exact match, Likely duplicate, etc.)
   - ✅ File details (size, upload date)

**Expected Result**: Scan completes, displays any duplicate images found

**Document**:
- Number of duplicates found
- Scan performance (time taken)
- Any errors during scanning

---

### Test 5: Visual Similarity Scan (Advanced)

1. Click **"Visual Similarity Scan"** button
2. Wait for perceptual hash processing
3. Check for visually similar images (even if filenames differ)
4. Verify confidence scores and similarity percentages

**Expected Result**: Perceptual hashing works, similar images detected

**Document**:
- GD/Imagick extension availability
- Processing speed
- Accuracy of visual matches

---

## Error Reporting

For any issues found, document:

1. **Error message** (exact text)
2. **Browser console errors** (JavaScript)
3. **PHP errors** (check Local logs or WordPress debug.log)
4. **Steps to reproduce**
5. **Expected vs actual behavior**

## Success Criteria

- ✅ All core features work without fatal errors
- ✅ Usage index builds successfully
- ✅ Image analysis runs and displays results
- ✅ UI is responsive and functional
- ✅ No JavaScript console errors
- ✅ Database tables created correctly

## Next Steps After Testing

Based on test results, we may need to:
- Fix any bugs discovered
- Optimize performance bottlenecks
- Improve error handling
- Update documentation

---

**Start testing and report your findings!**
