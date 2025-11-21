# Documentation Workflow - Standard Operating Procedure

## Purpose
Establish a clear, repeatable process for documenting all changes to prevent rework loops and ensure solid foundation for launch.

---

## 1. Before Making ANY Code Change

### Document the Problem
1. Create or update session log: `LOG-[DATE].md`
2. Write problem description with:
   - User's exact words (quoted)
   - Current behavior vs expected behavior
   - Steps to reproduce
   - Why this is a problem

### Verify Root Cause
1. Read relevant code
2. Trace execution flow
3. Check git history for related changes
4. Identify when regression was introduced (if applicable)
5. **Document the root cause** before coding

---

## 2. Planning the Fix

### Design the Solution
1. Document proposed solution in session log
2. List all files that will be modified
3. Identify potential side effects
4. Check if this will break anything else
5. **Get user approval if ambiguous**

### Check for Existing Fixes
1. Search logs for similar issues
2. Check if we solved this before
3. If yes: **Why did it break again?**
4. Document what was different this time

---

## 3. Implementing the Change

### Make Changes Systematically
1. Edit ONE file at a time
2. Document each change as you make it
3. Include:
   - File path
   - Line numbers
   - Before/after code snippets
   - Why this specific change

### Version Control
1. Bump version in `msh-image-optimizer.php`
2. Update ANALYSIS_CACHE_VERSION if analyze logic changed
3. Document version number in session log

---

## 4. After Making Changes

### Update Session Log
1. Add "Changes Made" section with:
   - Complete list of modified files
   - Exact line numbers
   - Code snippets
   - Rationale for each change

### Create Testing Instructions
1. Clear steps to verify fix
2. Expected vs actual behavior
3. How to check for regressions
4. Console output to look for

### Update Version History
1. Document in LOG file:
   - Version number
   - What was fixed
   - What files changed
   - Testing status

---

## 5. File Naming Convention

### Session Logs
- Format: `LOG-[MONTH]-[DAY-RANGE]-[YEAR].md`
- Example: `LOG-NOVEMBER-1-2-2024.md`
- One file per session or closely related work

### Problem-Specific Docs
- Format: `[PROBLEM-NAME]-[TYPE].md`
- Examples:
  - `AI-PERFORMANCE-FIX.md`
  - `RESET-BUTTON-FIX-SUMMARY.md`
  - `METADATA-DISPLAY-ISSUE.md`

### Reference Docs
- Format: `[TOPIC]-[TYPE].md`
- Examples:
  - `DOCUMENTATION-WORKFLOW.md`
  - `TESTING-CHECKLIST.md`
  - `DEPLOYMENT-GUIDE.md`

---

## 6. What to Document

### Always Document:
1. **User's exact request** (quoted)
2. **Root cause analysis** (not just symptoms)
3. **Why this solution** (not just what changed)
4. **Files modified** (with line numbers)
5. **Testing instructions** (step-by-step)
6. **Version history** (what changed in each version)
7. **Known issues** (if any remain)

### Never:
1. Create files without clear purpose
2. Duplicate information across files
3. Use temporary file names
4. Leave TODO comments without tickets
5. Skip testing instructions

---

## 7. When Bugs Recur

### Investigate Before Coding
1. **Stop and search logs** for previous fixes
2. **Read the original fix** completely
3. **Understand why it broke again**:
   - Did we revert something?
   - Did another change conflict?
   - Was the original fix incomplete?
4. **Document the loop** in session log
5. **Create "RECURRING-BUGS.md"** if this happens more than twice

### Fix the Process, Not Just the Bug
1. Why did we miss this?
2. What documentation would have prevented it?
3. Update this workflow if needed
4. Add regression test if possible

---

## 8. Pre-Launch Checklist

### Documentation Must Include:
- [ ] Complete session logs for all major changes
- [ ] Version history with all releases
- [ ] Testing instructions for each feature
- [ ] Known issues document
- [ ] Rollback procedures
- [ ] Performance benchmarks
- [ ] Security audit results

### Code Must Include:
- [ ] No DEBUG logging in production
- [ ] All features tested
- [ ] No regression bugs
- [ ] Performance acceptable
- [ ] Error handling complete

---

## 9. Daily Workflow

### Start of Session:
1. Review previous session log
2. Check for recurring issues
3. Create new session log or continue existing

### During Session:
1. Document as you go (not after)
2. One change at a time
3. Test after each change
4. Update log immediately

### End of Session:
1. Complete session log
2. Create testing summary
3. Document any unresolved issues
4. List next steps

---

## 10. Emergency Fixes

### When Something Breaks Urgently:
1. **Still follow this workflow** (faster but not sloppy)
2. Document problem immediately
3. Quick root cause analysis
4. Implement minimal fix
5. Mark as "URGENT FIX - NEEDS REVIEW"
6. Schedule proper fix later if needed

---

## This Workflow Is:
- **Mandatory** for all code changes
- **Living document** (update as we learn)
- **Launch blocker** if not followed
- **Quality gate** for production

---

## User Feedback Integration

### When User Reports Issue:
1. Quote exact user message
2. Reproduce issue
3. Search logs for similar
4. Document if recurring
5. Follow workflow above

### When User Gets Frustrated:
1. **STOP** and review what went wrong
2. Did we skip documentation?
3. Did we not check logs?
4. Update workflow to prevent
5. Apologize and fix process

---

**Last Updated:** November 2, 2024
**Status:** ACTIVE - MUST FOLLOW
**Owner:** Development Team
