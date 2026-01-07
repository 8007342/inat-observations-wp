# TODO-001: WordPress.org Plugin Directory Compliance

**Priority:** High
**Status:** 🔴 Blocked on implementation tasks
**Target:** Before 1.0.0 stable release
**Effort:** ~8-12 hours total prep + 5-14 days review

---

## Overview

This TODO tracks all requirements for submitting the inat-observations-wp plugin to the WordPress.org Plugin Directory for one-click installation by WordPress users worldwide.

**Why WordPress.org?**
- ✅ FREE forever (no fees, no costs)
- ✅ One-click install from WordPress admin
- ✅ Automatic updates for users
- ✅ Trusted by 60+ million WordPress sites
- ✅ Highest discoverability for new users

**Current Distribution Status:**
- ✅ GitHub repository with Git Updater headers (developer-friendly)
- 🔴 Not yet on WordPress.org (general public can't find it)

---

## Requirements Checklist

### 1. Code Security ✅ (Already Compliant!)

- [x] **Input sanitization** - Using `sanitize_text_field()`, `absint()`, `esc_like()`
  - See: `includes/rest.php:24`, `includes/shortcode.php:56,70,71`
- [x] **Output escaping** - Using `esc_html()`, `esc_attr()`, `esc_url()`
  - See: `includes/shortcode.php:33-38`, `assets/js/main.js:163-186`
- [x] **CSRF protection** - Using `wp_nonce_field()` and `check_ajax_referer()`
  - See: `includes/admin.php:134`, `includes/shortcode.php:48-51`
- [x] **SQL injection prevention** - Using `$wpdb->prepare()` for all queries
  - See: `includes/rest.php:64,66`, `includes/shortcode.php:110,112`
- [x] **HTTPS enforcement** - Implemented for production environments
  - See: `includes/init.php:220-230`
- [x] **Security headers** - X-Content-Type-Options, X-Frame-Options, X-XSS-Protection
  - See: `includes/init.php:211-217`

**Verdict:** ✅ Security code is EXCELLENT and already meets WordPress.org standards!

---

### 2. Code Quality 🟡 (Minor Cleanup Needed)

- [ ] **Remove debug console.log** - Added during development, must remove before submission
  - Action: Remove from `assets/js/main.js` (lines 3, 6, 13, 24, 33, 42, 47, 54, 63, 175)
  - See: `TODO-QA-001-sanitize-debug-logs.md` (created separately)
- [x] ✅ **No development tools** - No `var_dump()`, `print_r()`, `error_log()` in user-facing code
  - Note: `error_log()` used only in admin cron jobs (acceptable)
- [x] ✅ **Version control** - Using semantic versioning (0.1.0)
- [x] ✅ **GPL license** - GPLv2 or later (header + LICENSE file)
- [x] ✅ **No obfuscated code** - All code is readable and maintainable
- [x] ✅ **No tracking** - Zero user tracking, no analytics, no external calls

**Verdict:** 🟡 Almost perfect! Just need to remove debug logs.

---

### 3. Plugin Check Tool 🔴 (Must Run Before Submission)

**What is Plugin Check?**
Since September 2024, WordPress.org requires all new plugin submissions to pass automated testing via the [Plugin Check tool](https://wordpress.org/plugins/plugin-check/).

**Installation:**
```bash
cd /var/home/machiyotl/src/inat-observations-wp
docker-compose exec wordpress wp plugin install plugin-check --activate
```

**Running Checks:**
```bash
# Full check with all tests
docker-compose exec wordpress wp plugin check inat-observations-wp

# Check specific category
docker-compose exec wordpress wp plugin check inat-observations-wp --checks=security

# Output format for CI/CD
docker-compose exec wordpress wp plugin check inat-observations-wp --format=json
```

**Expected Issues to Fix:**
- [ ] Console.log statements (will fail "general" checks)
- [ ] Missing readme.txt (will fail "plugin_repo" checks)
- [ ] Potentially missing text domain in translatable strings

**Acceptance Criteria:**
- ✅ Zero ERROR-level issues
- ✅ Zero WARNING-level issues (recommended, not required)

**Status:** 🔴 Not yet run

---

### 4. Required Files 🟡 (Mostly Complete)

- [x] ✅ **Main plugin file** - `inat-observations-wp.php` with valid headers
- [ ] 🔴 **readme.txt** - WordPress.org format README (REQUIRED)
  - Action: Create `readme.txt` following WordPress.org template
  - See template in `TODO-wordpress-distribution-options.md` (lines 378-465)
- [ ] 🟡 **Screenshots** - 2-4 images showing plugin in action (RECOMMENDED)
  - Action: Create `assets/screenshot-{1,2,3,4}.png` (1280x720 or larger)
  - Screenshot 1: Observation grid display with pagination
  - Screenshot 2: Admin settings page
  - Screenshot 3: Shortcode example in post editor
  - Screenshot 4: Filter/search functionality (after implementation)
- [x] ✅ **License file** - `LICENSE` exists with GPLv2 text
- [ ] 🔴 **Changelog** - Must be in readme.txt (REQUIRED)
  - Action: Add changelog section to readme.txt

**Status:** 🟡 Need readme.txt and screenshots

---

### 5. Documentation 🔴 (Critical Gap)

#### readme.txt Structure (REQUIRED)

**Must include:**
- [x] Plugin name and description
- [ ] Installation instructions
- [ ] FAQ section (at least 3-5 common questions)
- [ ] Changelog (version history)
- [ ] Screenshots description
- [ ] Third-party service disclosure (iNaturalist API)

**Template:** See `TODO-wordpress-distribution-options.md` lines 378-465

#### Action Items:
1. Copy template to `readme.txt`
2. Customize for inat-observations-wp specifics
3. Test with [WordPress Readme Validator](https://wordpress.org/plugins/developers/readme-validator/)
4. Ensure "Tested up to" matches latest WordPress version

**Status:** 🔴 Not started

---

### 6. Testing & Compatibility 🟡 (Partially Done)

#### Automated Tests ✅
- [x] Unit tests exist (`tests/unit/`)
- [x] Test fixtures exist (`tests/fixtures/`)
- [ ] Run full test suite before submission

#### Manual Testing 🔴
- [ ] **Fresh WordPress install** - Test on vanilla WP with no other plugins
  - Action: Spin up clean Docker container, install plugin, verify works
- [ ] **PHP compatibility** - Test on PHP 7.4, 8.0, 8.1, 8.2
  - Current Docker uses PHP 8.2 (✅)
  - Action: Test on 7.4 (minimum supported)
- [ ] **WordPress compatibility** - Test on WP 5.8+, 6.0+, 6.4+
  - Action: Use [WordPress Playground](https://playground.wordpress.net/) for quick testing
- [ ] **Theme compatibility** - Test with Twenty Twenty-Four default theme
  - Action: Activate theme, verify shortcode renders correctly
- [ ] **Plugin conflicts** - Test with popular plugins (Yoast SEO, WooCommerce, etc.)
  - Action: Install top 10 plugins, verify no conflicts

#### Performance Testing 🟡
- [x] Tested with 2000 observations (✅ works!)
- [ ] Test with 10,000+ observations (stress test)
- [ ] Profile page load times (<2 seconds ideal)
- [ ] Check database query performance (WordPress Debug Bar)

**Status:** 🟡 Basic testing done, need cross-version testing

---

### 7. Licensing & Third-Party Compliance ✅

- [x] ✅ **Plugin licensed GPLv2+** - Header and LICENSE file present
- [x] ✅ **Third-party libraries** - None used (all native WordPress/PHP)
- [x] ✅ **API compliance** - iNaturalist API usage follows their TOS
  - Verified: CC BY-NC licensed data, no commercial AI training
  - See: `TODO-thumbnails-legal-compliance.md` research findings
- [x] ✅ **No proprietary code** - All code is open source
- [x] ✅ **No external dependencies** - No npm, composer, or CDN dependencies

**Third-Party Service Disclosure (for readme.txt):**
```
This plugin fetches data from the iNaturalist API (https://api.inaturalist.org/v1/observations).
- iNaturalist Terms of Service: https://www.inaturalist.org/pages/terms
- iNaturalist API Terms: https://www.inaturalist.org/pages/api+terms+of+use
- Privacy Policy: https://www.inaturalist.org/pages/privacy
Observation data is cached locally. No user data is sent to iNaturalist.
```

**Status:** ✅ Fully compliant

---

### 8. WordPress.org Account Setup 🔴

- [ ] **Create WordPress.org account** (if not already have)
  - URL: https://login.wordpress.org/register
- [ ] **Enable Two-Factor Authentication (2FA)** - MANDATORY since 2024
  - URL: https://wordpress.org/support/users/{username}/edit/
  - Recommended: Use authenticator app (Authy, Google Authenticator)
- [ ] **Verify email address**

**Status:** 🔴 Unknown (need to check if user has account)

---

### 9. Submission Preparation 🔴

#### Pre-Submission Checklist

**Week 1 (Development Cleanup):**
- [ ] Remove all console.log debug statements (`TODO-QA-001`)
- [ ] Run Plugin Check tool, fix all errors/warnings
- [ ] Create readme.txt with complete documentation
- [ ] Take 3-4 screenshots (1280x720 minimum)
- [ ] Update version to 1.0.0 (stable release)
- [ ] Test on fresh WordPress install
- [ ] Test on PHP 7.4 and 8.2
- [ ] Validate readme.txt at https://wordpress.org/plugins/developers/readme-validator/

**Week 2 (Final Review):**
- [ ] Code review by second developer (if available)
- [ ] Security audit (double-check XSS, CSRF, SQL injection)
- [ ] Performance audit (page load <2s, database queries optimized)
- [ ] Accessibility audit (WCAG 2.1 Level A minimum)
- [ ] Create GitHub release tag v1.0.0
- [ ] Create plugin ZIP file for submission

**Week 3 (Submission):**
- [ ] Enable 2FA on WordPress.org account
- [ ] Submit plugin at https://wordpress.org/plugins/developers/add/
- [ ] Monitor email for review feedback
- [ ] Respond to reviewer questions within 24-48 hours

---

### 10. Post-Submission Workflow 🔴

**After Approval (5-14 days):**

1. **SVN Access Granted**
   - You'll receive SVN repository URL: `https://plugins.svn.wordpress.org/inat-observations-wp`

2. **Initial SVN Commit**
   ```bash
   # Checkout SVN repo
   svn co https://plugins.svn.wordpress.org/inat-observations-wp svn-inat-obs
   cd svn-inat-obs

   # Add files to trunk
   cp -r /path/to/plugin/* trunk/
   svn add trunk/*
   svn ci -m "Initial commit of version 1.0.0"

   # Create release tag
   svn cp trunk tags/1.0.0
   svn ci -m "Tagging version 1.0.0"
   ```

3. **Add Screenshots (Optional)**
   ```bash
   # Screenshots go in assets/ directory (not trunk/)
   svn mkdir assets
   svn add assets/screenshot-*.png
   svn ci -m "Add plugin screenshots"
   ```

4. **Plugin Goes Live!** 🎉
   - Appears in WordPress.org search within 30 minutes
   - Users can install via Plugins → Add New → Search

---

### 11. Long-Term Maintenance Strategy

**GitHub → SVN Sync (Recommended):**

Use GitHub Actions to automatically deploy releases to WordPress.org SVN.

**Create `.github/workflows/deploy-to-wordpress.yml`:**

```yaml
name: Deploy to WordPress.org
on:
  release:
    types: [published]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: WordPress Plugin Deploy
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
          SLUG: inat-observations-wp
```

**Workflow:**
1. Develop on GitHub (main branch)
2. Create GitHub release (e.g., v1.0.1)
3. GitHub Action automatically pushes to WordPress.org SVN
4. Users get update notification within 1 hour

**Benefits:**
- ✅ Single source of truth (GitHub)
- ✅ Automated deployments (no manual SVN)
- ✅ Both GitHub and WordPress.org stay in sync

---

## Timeline Estimate

| Week | Tasks | Hours |
|------|-------|-------|
| **Week 1** | Remove debug logs, create readme.txt, run Plugin Check | 6-8 hours |
| **Week 2** | Screenshots, testing, final review | 4-6 hours |
| **Week 3** | Submission, wait for review | 30 min + wait |
| **Week 4** | Address feedback (if any), resubmit | 2-4 hours |
| **Week 5** | Approval, SVN setup, go live | 2 hours |

**Total effort:** 14-20 hours spread over 5 weeks
**Review wait time:** 5-14 business days

---

## Blocking Issues

### Critical Blockers (Must Fix Before Submission)
1. 🔴 **Remove debug console.log statements** (`TODO-QA-001`)
2. 🔴 **Create readme.txt** (WordPress.org format)
3. 🔴 **Run Plugin Check tool** and fix all errors
4. 🔴 **Enable 2FA** on WordPress.org account

### Recommended (Not Blocking)
- 🟡 Take screenshots (can submit without, but looks unprofessional)
- 🟡 Test on PHP 7.4 (minimum supported version)
- 🟡 Test with popular themes/plugins (avoid conflicts)

---

## Success Metrics

**Submission Success:**
- ✅ Plugin approved within 14 days
- ✅ Zero critical issues found during review
- ✅ SVN access granted

**Post-Launch Success:**
- ✅ 100+ active installs within 3 months
- ✅ 4.5+ star rating
- ✅ Zero security vulnerabilities reported
- ✅ <1% support ticket rate

---

## Resources

- [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [Plugin Check Tool](https://wordpress.org/plugins/plugin-check/)
- [Readme Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Submit Plugin](https://wordpress.org/plugins/developers/add/)
- [10up GitHub Action (SVN Deploy)](https://github.com/10up/action-wordpress-plugin-deploy)

---

## Related TODOs

- `TODO-QA-001-sanitize-debug-logs.md` - Remove console.log before submission (CRITICAL)
- `TODO-wordpress-distribution-options.md` - Distribution strategy comparison
- `TODO-thumbnails-legal-compliance.md` - iNaturalist image usage compliance
- `TODO-002-dna-filtering.md` - Core feature for 1.0.0 (optional for first submission)

---

**Next Actions:**
1. Create `TODO-QA-001-sanitize-debug-logs.md`
2. Create `readme.txt` from template
3. Run Plugin Check tool in Docker
4. Take screenshots of current functionality
5. Test on fresh WordPress install

**Status:** 🔴 Not ready for submission (need QA cleanup + documentation)
**ETA to submission-ready:** 2-3 weeks (14-20 hours effort)
