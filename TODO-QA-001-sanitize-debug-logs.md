# TODO-QA-001: Sanitize Debug Logs for Production

**Priority:** 🔴 CRITICAL
**Status:** 🔴 Blocking WordPress.org submission
**Effort:** 30 minutes
**Close Date:** Before submitting to WordPress.org (very last step)

---

## Overview

This TODO tracks removal of all debug/development code before submitting the plugin to WordPress.org. Debug statements are helpful during development but must be removed before public release.

**Why This Matters:**
- WordPress.org Plugin Check tool WILL FAIL with console.log present
- Clutters browser console for end users
- Unprofessional appearance
- Can leak sensitive information (API keys, internal paths)
- Violates WordPress.org guidelines: "production-ready: without unnecessary logs"

---

## Debug Code to Remove

### 1. JavaScript Console Logs (CRITICAL)

**File:** `/var/home/machiyotl/src/inat-observations-wp/wp-content/plugins/inat-observations-wp/assets/js/main.js`

**Lines to remove:**

| Line | Code | Reason |
|------|------|--------|
| 3 | `console.log('[iNat] Script loaded');` | Development debugging |
| 6 | `console.log('[iNat] DOM ready');` | Development debugging |
| 10 | `console.error('[iNat] Root element not found');` | Keep as warning, or remove |
| 13 | `console.log('[iNat] Root element found');` | Development debugging |
| 20 | `console.error('[iNat] inatObsSettings not found');` | Keep as error, remove log |
| 24 | `console.log('[iNat] Settings:', inatObsSettings);` | 🔴 **DANGEROUS** - exposes nonce! |
| 33 | `console.log('[iNat] Fetching observations...', { page: currentPage, perPage: currentPerPage });` | Development debugging |
| 42 | `console.log('[iNat] Fetch URL:', url.toString());` | Development debugging |
| 47 | `console.log('[iNat] Response status:', r.status);` | Development debugging |
| 54 | `console.log('[iNat] Response data:', j);` | Development debugging |
| 56 | `console.error('[iNat] API error:', j.data?.message);` | Keep as error |
| 63 | `console.log('[iNat] Got', totalResults, 'results');` | Development debugging |
| 170 | `console.error('iNat observations fetch error:', e);` | Keep as error |
| 175 | `console.log('[iNat] Starting initial fetch...');` | Development debugging |

**Security Risk:**
- **Line 24** exposes the nonce in browser console! This is a CSRF token leak.
- Even though nonces expire, this is bad practice and must be removed.

---

## Action Plan

### Option 1: Complete Removal (RECOMMENDED)

**Replace all console.log with silent operation:**

```javascript
// Before (development)
console.log('[iNat] Fetching observations...', { page: currentPage, perPage: currentPerPage });

// After (production)
// (removed - no logging)
```

**Pros:**
- ✅ Clean, professional code
- ✅ Passes WordPress.org Plugin Check
- ✅ No information leakage
- ✅ Smaller file size (slightly)

**Cons:**
- ❌ Harder to debug issues reported by users
- ❌ No visibility into what's happening

---

### Option 2: Conditional Debug Mode (ADVANCED)

**Keep logs but only show when WordPress debug mode enabled:**

```javascript
// assets/js/main.js
(function(){
  // Debug logger (only logs if WP_DEBUG enabled)
  const debug = (typeof inatObsSettings !== 'undefined' && inatObsSettings.debug)
    ? console.log.bind(console, '[iNat]')
    : function() {};

  // Usage
  debug('Script loaded');  // Only logs if debug enabled
  debug('Settings:', inatObsSettings);  // Only logs if debug enabled
})();
```

**In shortcode.php, pass debug flag:**

```php
wp_localize_script('inat-observations-main', 'inatObsSettings', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('inat_obs_fetch'),
    'project' => $atts['project'],
    'perPage' => $atts['per_page'],
    'enableThumbnails' => get_option('inat_obs_enable_thumbnails', true),
    'debug' => defined('WP_DEBUG') && WP_DEBUG,  // NEW
]);
```

**Pros:**
- ✅ Debugging available for developers when needed
- ✅ Clean for end users (no logs in production)
- ✅ WordPress-standard approach (uses WP_DEBUG)

**Cons:**
- ❌ More code complexity
- ❌ Still need to be careful about sensitive data (nonces)

---

### Option 3: Keep Only Errors (COMPROMISE)

**Remove all console.log, keep only console.error for critical issues:**

```javascript
// Remove all console.log
// console.log('[iNat] Fetching observations...');  // REMOVED

// Keep console.error for user-facing issues
console.error('[iNat] Configuration error: inatObsSettings not found');
console.error('[iNat] API error:', j.data?.message);
console.error('iNat observations fetch error:', e);
```

**Pros:**
- ✅ Users see critical errors (helps with support tickets)
- ✅ No verbose logging clutter
- ✅ Passes WordPress.org Plugin Check (errors are OK)

**Cons:**
- ❌ No visibility into normal operation
- ❌ Users might be alarmed by console errors

---

## Recommended Approach

**For WordPress.org submission:** Use **Option 1 (Complete Removal)**

**For long-term:** Implement **Option 2 (Conditional Debug)** after approval

**Why:**
1. WordPress.org reviewers prefer clean code with no console statements
2. Easier to get approved with zero logs
3. Can add conditional debugging in future update (1.0.1)

---

## Implementation Checklist

### Step 1: Remove All Console Logs

```bash
cd /var/home/machiyotl/src/inat-observations-wp/wp-content/plugins/inat-observations-wp/assets/js

# Backup original
cp main.js main.js.backup

# Edit main.js and remove all console.log lines
# (Do manually with Edit tool to avoid breaking code)
```

**Lines to remove from main.js:**
- [ ] Line 3: `console.log('[iNat] Script loaded');`
- [ ] Line 6: `console.log('[iNat] DOM ready');`
- [ ] Line 10: `console.error('[iNat] Root element not found');` (or keep if helpful)
- [ ] Line 13: `console.log('[iNat] Root element found');`
- [ ] Line 20: `console.error('[iNat] inatObsSettings not found');` (or keep if helpful)
- [ ] Line 24: `console.log('[iNat] Settings:', inatObsSettings);` 🔴 **MUST REMOVE** (security)
- [ ] Line 33: `console.log('[iNat] Fetching observations...', ...);`
- [ ] Line 42: `console.log('[iNat] Fetch URL:', url.toString());`
- [ ] Line 47: `console.log('[iNat] Response status:', r.status);`
- [ ] Line 54: `console.log('[iNat] Response data:', j);`
- [ ] Line 56: `console.error('[iNat] API error:', j.data?.message);` (keep or remove)
- [ ] Line 63: `console.log('[iNat] Got', totalResults, 'results');`
- [ ] Line 170: `console.error('iNat observations fetch error:', e);` (keep or remove)
- [ ] Line 175: `console.log('[iNat] Starting initial fetch...');`

---

### Step 2: Verify No Other Debug Code

**Search for debug patterns:**

```bash
cd /var/home/machiyotl/src/inat-observations-wp

# Search for console.log in all files
grep -r "console\." wp-content/plugins/inat-observations-wp/ --include="*.js"

# Search for var_dump in PHP files (should find none)
grep -r "var_dump\|print_r\|var_export" wp-content/plugins/inat-observations-wp/ --include="*.php"

# Search for wp_die (OK in error handling, but check usage)
grep -r "wp_die" wp-content/plugins/inat-observations-wp/ --include="*.php"
```

**Expected results:**
- ✅ Zero console.* statements in main.js (after cleanup)
- ✅ Zero var_dump/print_r in PHP files
- ✅ wp_die only in HTTPS enforcement (init.php:224) - this is OK

---

### Step 3: Test Functionality Still Works

**After removing logs:**

1. Refresh WordPress page with shortcode
2. Verify observations still display
3. Check browser console - should be clean (no iNat logs)
4. Test pagination (Next/Previous buttons)
5. Test per-page selector (10, 50, 200, all)
6. Verify no JavaScript errors

**If anything breaks:**
- Restore from backup: `cp main.js.backup main.js`
- Carefully remove logs one by one
- Test after each removal

---

### Step 4: Run Plugin Check Tool

```bash
cd /var/home/machiyotl/src/inat-observations-wp
docker-compose exec wordpress wp plugin install plugin-check --activate
docker-compose exec wordpress wp plugin check inat-observations-wp
```

**Expected output:**
```
Checking plugin: inat-observations-wp
✅ No errors found
✅ No warnings found
Plugin is ready for submission!
```

**If Plugin Check still fails:**
- Read the error message carefully
- Fix the issue
- Re-run Plugin Check
- Repeat until zero errors

---

## PHP Error Logging (KEEP AS-IS)

**These are OK to keep (not user-facing):**

`includes/init.php` lines 73-207 contain extensive `error_log()` statements for the cron job refresh process. **These are acceptable** because:

✅ Only run in background (WP-Cron)
✅ Not visible to end users
✅ Essential for debugging sync issues
✅ Follow WordPress best practices
✅ Can be disabled with `WP_DEBUG_LOG = false`

**Examples of acceptable error_log:**
```php
error_log('iNat Observations: Starting refresh job');
error_log('ERROR: API fetch failed on page ' . $page);
error_log('✓ Stored ' . $stored_count . ' observations to database');
```

**No action required for PHP error_log statements.**

---

## Acceptance Criteria

- [ ] ✅ Zero `console.log` statements in production JavaScript
- [ ] ✅ Zero `console.debug` statements in production JavaScript
- [ ] ✅ Keep `console.error` for critical user-facing errors (optional)
- [ ] ✅ Zero `var_dump`, `print_r`, `var_export` in PHP files
- [ ] ✅ Plugin Check tool passes with zero errors
- [ ] ✅ Plugin Check tool passes with zero warnings (recommended)
- [ ] ✅ Functionality still works after log removal
- [ ] ✅ Browser console is clean (no iNat debug spam)

---

## When to Close This TODO

**🚨 CLOSE IMMEDIATELY BEFORE WORDPRESS.ORG SUBMISSION 🚨**

**Timeline:**
1. Implement all other features (DNA filtering, thumbnails, etc.)
2. Complete all testing
3. **THEN** remove debug logs (last step)
4. Run Plugin Check tool
5. Submit to WordPress.org

**Why wait until last?**
- Need debug logs during active development
- Easier to troubleshoot issues with logging enabled
- Clean removal in one pass (less chance of missing logs)

**Trigger:** When `TODO-001-wordpress-org-compliance.md` is 95% complete and ready for final submission

---

## Rollback Plan

**If WordPress.org submission is delayed:**

1. Restore debug logs for continued development:
   ```bash
   cp main.js.backup main.js
   ```

2. Add comment to main.js:
   ```javascript
   // DEVELOPMENT VERSION - Remove all console.log before WordPress.org submission
   // See TODO-QA-001-sanitize-debug-logs.md
   ```

3. Re-sanitize before eventual submission

---

## Related TODOs

- `TODO-001-wordpress-org-compliance.md` - Master WordPress.org submission checklist
- `TODO-wordpress-distribution-options.md` - Distribution strategy overview

---

**Status:** 🔴 Open (debug logs still present)
**Blocking:** WordPress.org submission (CRITICAL)
**ETA:** Close immediately before submission (30 min effort)
**Next Action:** Wait for `TODO-001-wordpress-org-compliance.md` to reach 95% completion, then execute removal plan
