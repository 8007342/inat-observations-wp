# WordPress Plugin Distribution Options

## Quick Answers to Your Questions

### 1. Can plugins be installed from GitHub repository links?
**YES** - Using the [Git Updater plugin](https://github.com/afragen/git-updater) or similar tools, users can install and auto-update plugins directly from GitHub.

### 2. Are there tests required for WordPress.org marketplace?
**YES** - Since September 2024, all new plugins must pass the [Plugin Check tool](https://wordpress.org/plugins/plugin-check/) automated review. Manual security review also required.

### 3. Is there a fee for WordPress.org marketplace?
**NO** - WordPress.org plugin directory is **100% FREE**. No submission fee, no hosting fee, no listing fee.

---

## Distribution Method Comparison

| Method | Pros | Cons | Best For |
|--------|------|------|----------|
| **WordPress.org Directory** | One-click install, automatic updates, high discoverability, trusted by users | Strict guidelines, 5-14 day review, must use GPL license, no proprietary code | Public open-source plugins |
| **GitHub + Git Updater** | Full control, instant releases, private repos supported, no review process | Users must install Git Updater first, less discoverable, manual setup | Developer-focused plugins, rapid iteration |
| **Manual ZIP Download** | Simple, no dependencies, works anywhere | No automatic updates, users must manually check for updates | One-off deployments, custom client work |
| **Composer/Packagist** | Developer-friendly, version management, dependency resolution | Requires technical knowledge, not for average users | Agency/enterprise workflows |

---

## Option 1: WordPress.org Plugin Directory (Recommended for Public Release)

### Requirements

#### Mandatory
- ✅ **GPL-compatible license** (recommended: GPLv2 or later)
- ✅ **Two-Factor Authentication (2FA)** on WordPress.org account
- ✅ **Complete, production-ready plugin** at time of submission
- ✅ **Pass Plugin Check tool** automated tests (since Sept 2024)
- ✅ **Under 10MB ZIP file** in standard WordPress plugin format
- ✅ **No obfuscated code** - all source must be readable
- ✅ **All third-party libraries GPL-compatible** (verify API TOS compliance)

#### Security Requirements (Top 3 Rejection Reasons)
1. **No unescaped output** - Use `esc_html()`, `esc_attr()`, `esc_url()`, etc.
2. **Sanitize all input** - Use `sanitize_text_field()`, `absint()`, etc.
3. **Use nonces for forms** - CSRF protection via `wp_nonce_field()`

#### Code Quality
- No development tools (console logs, debug code)
- No trial/freemium with locked features (must be separate free/pro plugins)
- No tracking users without explicit consent + clear documentation
- No embedded external links/credits without user opt-in

### Submission Process

1. **Enable 2FA** on your WordPress.org account
2. **Run Plugin Check tool** locally before submitting
   ```bash
   wp plugin install plugin-check --activate
   wp plugin check /path/to/your-plugin
   ```
3. **Submit plugin** at https://wordpress.org/plugins/developers/add/
4. **Wait for review** (5-14 business days, typically faster if code is clean)
5. **Address feedback** (if any issues found)
6. **Approval & SVN access** granted

### Post-Approval: Managing Updates

**WordPress.org uses SVN (not Git) for distribution:**

```bash
# Initial checkout after approval
svn co https://plugins.svn.wordpress.org/your-plugin-slug

# Update trunk (development)
svn add trunk/*
svn ci -m "Version 1.0.1 updates"

# Create release tag
svn cp trunk tags/1.0.1
svn ci -m "Tagging version 1.0.1"
```

**Pro tip:** You can use GitHub for development and sync to SVN for releases. Many developers use GitHub Actions to automate this.

### Costs
- **Submission fee:** $0
- **Hosting fee:** $0
- **Update fee:** $0
- **Total cost:** **FREE FOREVER**

### Timeline
- **Review start:** Within 10 business days
- **Approval (clean code):** 5-14 days from initial review
- **Approval (needs fixes):** Depends on your response time

---

## Option 2: GitHub-Based Distribution (Recommended for Developer Audience)

### How It Works

Users install [Git Updater](https://github.com/afragen/git-updater) plugin once, then can install/update your plugin from GitHub with automatic update notifications.

### Setup for Your Plugin

Add headers to your main plugin file (`inat-observations-wp.php`):

```php
/**
 * Plugin Name: inat-observations-wp
 * Plugin URI:  https://github.com/8007342/inat-observations-wp
 * Description: Fetch, cache, and display iNaturalist observations with metadata filtering.
 * Version:     0.1.0
 * Author:      Ayahuitl Tlatoani
 * License:     GPLv2 or later
 * Text Domain: inat-observations-wp
 *
 * GitHub Plugin URI: 8007342/inat-observations-wp
 * GitHub Branch: main
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */
```

### User Installation Process

**One-time setup:**
1. Install Git Updater from https://git-updater.com/
2. Activate Git Updater

**Installing your plugin:**
1. Go to **Plugins → Add New → Upload Plugin**
2. Download ZIP from GitHub (Code → Download ZIP) and upload
3. **OR** use Git Updater's "Install Plugin" tab with repo URL

**Automatic updates:**
- Git Updater checks GitHub for new releases
- Users see "Update Available" notification in WordPress admin
- One-click update just like WordPress.org plugins

### Pros
- ✅ Instant releases (no review process)
- ✅ Full control over code and features
- ✅ Can use private repositories (with authentication)
- ✅ No licensing restrictions (can use proprietary licenses)
- ✅ Rapid iteration during development

### Cons
- ❌ Users must install Git Updater first (extra step)
- ❌ Not discoverable in WordPress.org search
- ❌ Less trusted by non-technical users
- ❌ No official WordPress.org "stamp of approval"

### Best For
- Beta testing before WordPress.org submission
- Developer-focused plugins
- Custom agency/client plugins
- Plugins that need frequent updates
- Plugins with proprietary components

---

## Option 3: Hybrid Approach (Best of Both Worlds)

### Strategy

1. **Development:** Use GitHub for version control and rapid iteration
2. **Testing:** Distribute to beta testers via GitHub + Git Updater
3. **Public Release:** Submit to WordPress.org for wide distribution
4. **Maintenance:** Sync GitHub → SVN automatically via GitHub Actions

### Workflow

```
GitHub (main branch)
  ↓
  └─→ Git Updater (beta testers get instant updates)
  ↓
  └─→ GitHub Release (v1.0.0 tag)
       ↓
       └─→ GitHub Action triggers
            ↓
            └─→ SVN commit (WordPress.org users get update)
```

### Example GitHub Action (Auto-sync to WordPress.org)

```yaml
# .github/workflows/deploy-to-wordpress.yml
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

**Result:** One `git tag` command updates both GitHub and WordPress.org automatically!

---

## Our Recommendation for inat-observations-wp

### Phase 1: Beta (Current) - GitHub Distribution
- ✅ Already have GitHub repo
- ✅ Easy for contributors and testers
- ✅ Rapid iteration during development
- ✅ Add Git Updater headers to plugin file

**User installation:**
```markdown
## Installation (Beta)
1. Install [Git Updater](https://git-updater.com/)
2. Download [latest release](https://github.com/8007342/inat-observations-wp/releases)
3. Upload ZIP via Plugins → Add New → Upload Plugin
4. Activate and configure in Settings → iNat Observations
```

### Phase 2: Public Release - WordPress.org Directory
When ready for 1.0.0 stable release:

1. **Pre-submission checklist:**
   - [ ] Run Plugin Check tool (fix all errors/warnings)
   - [ ] Security audit (XSS, CSRF, SQL injection, input sanitization)
   - [ ] Remove all development code (console.log, debug logs, TODO comments)
   - [ ] Verify GPL compliance for all third-party code
   - [ ] Write comprehensive README.txt (WordPress.org format)
   - [ ] Add screenshots to `assets/` folder
   - [ ] Test on fresh WordPress install (no conflicts)
   - [ ] Test with PHP 7.4, 8.0, 8.1, 8.2 (compatibility)

2. **Submit to WordPress.org**
   - Enable 2FA on WordPress.org account
   - Submit at https://wordpress.org/plugins/developers/add/
   - Wait for review (respond quickly to feedback)

3. **Post-approval:**
   - Set up GitHub Action for auto-deployment
   - Maintain both GitHub and WordPress.org
   - WordPress.org becomes primary distribution channel

**User installation (after WordPress.org approval):**
```markdown
## Installation (Stable)
1. Go to Plugins → Add New
2. Search "inat observations"
3. Click Install Now → Activate
4. Configure in Settings → iNat Observations
```

---

## WordPress.org Submission Checklist for inat-observations-wp

### Code Security ✅ (Already Good!)
- [x] ✅ **Nonces:** Already using `wp_nonce_field()` in admin.php, `check_ajax_referer()` in shortcode.php
- [x] ✅ **Input sanitization:** Using `sanitize_text_field()`, `absint()`, `esc_like()`
- [x] ✅ **Output escaping:** Using `esc_html()`, `esc_attr()`, `esc_url()`
- [x] ✅ **Prepared statements:** Using `$wpdb->prepare()` for all queries
- [x] ✅ **HTTPS enforcement:** Already implemented in init.php (production only)
- [x] ✅ **Security headers:** X-Content-Type-Options, X-Frame-Options, X-XSS-Protection

### Code Quality 🟡 (Minor Cleanup Needed)
- [ ] **Remove console.log:** Check main.js for debug logs (we just added some!)
- [x] ✅ **No development tools:** No var_dump, print_r in production code
- [x] ✅ **Version control:** Using semantic versioning (0.1.0)
- [x] ✅ **GPL license:** Already GPLv2 in plugin header
- [ ] **README.txt:** Need to create WordPress.org format README

### Plugin Check Tool 🔴 (Not Yet Run)
- [ ] **Install Plugin Check:** `wp plugin install plugin-check --activate`
- [ ] **Run checks:** `wp plugin check inat-observations-wp`
- [ ] **Fix errors:** Address all ERROR-level issues
- [ ] **Fix warnings:** Address all WARNING-level issues (recommended)

### Required Files 🟡 (Mostly Complete)
- [x] ✅ **Main plugin file:** inat-observations-wp.php with valid headers
- [ ] **readme.txt:** Need WordPress.org format (see template below)
- [ ] **Screenshots:** Add 2-4 screenshots to `assets/screenshot-{1,2,3,4}.png`
- [x] ✅ **License file:** LICENSE already exists (GPLv2)
- [ ] **Changelog:** Add to readme.txt

### Documentation 🔴 (Needs Work)
- [ ] **Installation instructions:** For WordPress.org users
- [ ] **FAQ section:** Common questions
- [ ] **Upgrade notices:** For version transitions
- [ ] **Support info:** Where to report bugs

### Testing 🟡 (Partially Done)
- [x] ✅ **Unit tests:** Already have tests/ directory with fixtures
- [ ] **Fresh WordPress install:** Test on vanilla WP (no other plugins)
- [ ] **PHP compatibility:** Test 7.4, 8.0, 8.1, 8.2
- [ ] **WordPress compatibility:** Test WP 5.8+, 6.0+, 6.4+
- [ ] **Theme compatibility:** Test with Twenty Twenty-Four theme

---

## README.txt Template for WordPress.org

Create `/var/home/machiyotl/src/inat-observations-wp/readme.txt`:

```
=== iNaturalist Observations ===
Contributors: ayahuitltlatoani
Tags: inaturalist, nature, observations, science, biodiversity
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display iNaturalist observations on your WordPress site with automatic caching and filtering.

== Description ==

iNaturalist Observations allows you to display nature observations from iNaturalist.org on your WordPress site. Perfect for nature blogs, science educators, and biodiversity projects.

**Features:**

* Automatic caching of observations in your WordPress database
* Fast, responsive grid display
* Pagination controls (10, 50, 200, or all observations)
* Configurable automatic refresh (4 hours, daily, weekly)
* Filter by user ID or project ID
* REST API endpoint for custom integrations
* Privacy-focused: no tracking, no external dependencies

**Usage:**

Add the shortcode to any page or post:

`[inat_observations]`

Configure global settings in Settings → iNat Observations, or customize per-page:

`[inat_observations project="12345" per_page="50"]`

**Security:**

* Input sanitization and output escaping
* CSRF protection with nonces
* Prepared SQL statements
* HTTPS enforcement (production)
* No user tracking

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/inat-observations-wp/`, or install via Plugins → Add New
2. Activate the plugin through the 'Plugins' screen
3. Go to Settings → iNat Observations
4. Enter your iNaturalist User ID or Project ID
5. Click "Refresh Now" to fetch initial observations
6. Add `[inat_observations]` shortcode to any page

== Frequently Asked Questions ==

= Where do I get my iNaturalist User ID? =

Visit your iNaturalist profile page. The URL will be `https://www.inaturalist.org/people/USERNAME`. Your user ID is shown in your profile settings.

= Can I display observations from multiple projects? =

Currently, one project per shortcode. Use multiple shortcodes for multiple projects.

= How often are observations refreshed? =

Configurable in Settings: every 4 hours, daily, or weekly. You can also manually refresh anytime.

= Does this plugin track my users? =

No. Zero tracking, no analytics, no external calls except to iNaturalist API.

= Is this plugin affiliated with iNaturalist? =

No, this is an independent open-source project. iNaturalist is a trademark of the California Academy of Sciences.

== Screenshots ==

1. Observation grid display with pagination controls
2. Admin settings page with refresh options
3. Shortcode configuration example

== Changelog ==

= 0.1.0 =
* Initial public release
* Core features: caching, pagination, REST API
* Security: input sanitization, CSRF protection, prepared statements
* Automatic refresh scheduling

== Upgrade Notice ==

= 0.1.0 =
Initial release. Requires PHP 7.4+ and WordPress 5.8+.

== Third-Party Services ==

This plugin fetches data from the iNaturalist API (https://api.inaturalist.org/v1/observations).

* iNaturalist Terms of Service: https://www.inaturalist.org/pages/terms
* iNaturalist API Terms: https://www.inaturalist.org/pages/api+terms+of+use
* Privacy Policy: https://www.inaturalist.org/pages/privacy

Observation data is cached locally. No user data is sent to iNaturalist.
```

---

## Cost Summary

| Distribution Method | Setup Cost | Annual Cost | Update Cost |
|---------------------|-----------|-------------|-------------|
| **WordPress.org** | $0 | $0 | $0 |
| **GitHub + Git Updater** | $0 | $0 | $0 |
| **Manual ZIP** | $0 | $0 | $0 |
| **Hybrid (both)** | $0 | $0 | $0 |

**Total investment required: $0**

WordPress.org is truly free, community-driven, and supported by Automattic (the company behind WordPress). There is no "premium tier" or paid listing option.

---

## Timeline Estimate

### If we submit to WordPress.org today:

**Week 1:**
- [ ] Run Plugin Check tool, fix issues (2-3 hours)
- [ ] Remove debug console.log statements (30 min)
- [ ] Create readme.txt with WordPress.org format (1 hour)
- [ ] Take screenshots (30 min)
- [ ] Test on fresh WordPress install (1 hour)
- [ ] **Total prep time: 5-6 hours**

**Week 2:**
- [ ] Submit to WordPress.org
- [ ] Wait for initial review (5-10 business days)

**Week 3:**
- [ ] Address reviewer feedback (if any)
- [ ] Resubmit for re-review (1-3 days)

**Week 4:**
- [ ] **Approval & go live!** 🎉

**Realistic timeline: 3-4 weeks from start to WordPress.org approval**

---

## Sources & References

- [WordPress.org Plugin Developer Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress.org Plugin Developer FAQ](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/)
- [WordPress Plugin Submission Portal](https://wordpress.org/plugins/developers/add/)
- [Git Updater Plugin](https://github.com/afragen/git-updater)
- [WordPress Plugin Update from GitHub Tutorial (BlogVault)](https://blogvault.net/wordpress-plugin-update-from-github/)
- [Distributing Plugins in GitHub with Automatic Updates (Envato Tuts+)](https://code.tutsplus.com/tutorials/distributing-your-plugins-in-github-with-automatic-updates--wp-34817)
- [Plugin Update Checker Library](https://github.com/YahnisElsts/plugin-update-checker)
- [WordPress Plugin Growth in 2025](https://make.wordpress.org/plugins/2025/05/21/the-wordpress-ecosystem-is-growing-new-plugin-submissions-have-doubled-in-2025/)

---

## Next Steps

**Immediate (This Week):**
1. Add Git Updater headers to `inat-observations-wp.php` for GitHub distribution
2. Update README.md with installation instructions for both methods

**Short-term (Before 1.0.0 release):**
1. Run Plugin Check tool and fix all issues
2. Create readme.txt in WordPress.org format
3. Take 3-4 screenshots of plugin in action
4. Remove debug console.log statements

**Long-term (After 1.0.0 stable):**
1. Submit to WordPress.org
2. Set up GitHub Action for auto-deployment
3. Maintain both GitHub and WordPress.org distributions

**Recommendation:** Start with GitHub distribution for beta users (add headers today, 5 minutes), then submit to WordPress.org when we hit 1.0.0 stable (3-4 weeks from now).
