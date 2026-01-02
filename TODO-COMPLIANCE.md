# TODO-COMPLIANCE.md - Legal & Compliance Review

**Reviewed by:** Compliance & Legal Advisor
**Date:** 2026-01-02
**Plugin Version:** 0.1.0
**Compliance Status:** 3/10 (Basic structure, missing critical requirements)

---

## Executive Summary

The inat-observations-wp plugin has **significant compliance gaps** that must be addressed before public distribution, especially for WordPress.org submission. Key issues include missing licensing information, no privacy policy, incomplete attribution, and no internationalization support.

**Critical Compliance Gaps:**
1. No WordPress.org readme.txt (required for distribution)
2. No license declaration in code headers
3. iNaturalist data attribution incomplete
4. No privacy policy or data handling disclosure
5. No GDPR/CCPA compliance measures
6. Not internationalized (WordPress requirement)

**Distribution Risk:** Cannot be submitted to WordPress.org in current state.

---

## CRITICAL Compliance Issues

### COMP-CRIT-001: Missing WordPress.org readme.txt 🔴

**Requirement:** WordPress.org requires `readme.txt` in specific format

**Status:** File does not exist

**Impact:** Plugin cannot be submitted to WordPress.org repository

**Required Sections:**
```
=== iNaturalist Observations ===
Contributors: (your-wp-username)
Tags: inaturalist, biodiversity, observations, nature, citizen-science
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display iNaturalist project observations with filterable UI on your WordPress site.

== Description ==

This plugin fetches observation data from the iNaturalist API and displays
it on your WordPress site with customizable filters and views.

Features:
* Automatic daily sync with iNaturalist API
* Filterable observation list by species, location, date
* Responsive card-based layout
* REST API endpoint for custom integrations
* Shortcode support: [inat_observations]

== Installation ==

1. Upload plugin files to `/wp-content/plugins/inat-observations-wp/`
2. Activate the plugin through the 'Plugins' menu
3. Go to Settings > iNat Observations
4. Enter your iNaturalist project slug
5. (Optional) Add API token for higher rate limits
6. Click "Sync Now" to fetch initial data

== Frequently Asked Questions ==

= Do I need an iNaturalist account? =
No, but you need to know the project slug you want to display.

= How often does data update? =
Automatically once per day via WP-Cron.

== Changelog ==

= 0.1.0 =
* Initial release
* Basic observation display
* Shortcode support
* REST API endpoint

== Upgrade Notice ==

= 0.1.0 =
Initial release.
```

**Epic:** E-COMP-001: Create readme.txt

---

### COMP-CRIT-002: License Declaration Missing 🔴

**Issue:** No license headers in PHP files

**WordPress.org Requirement:** Must be GPL-compatible (GPL, MIT, Apache, etc.)

**Current State:**
```php
// File: inat-observations-wp.php
/**
 * Plugin Name: iNaturalist Observations WP
 * Version: 0.1.0
 * ... other headers ...
 */
// ❌ No license declared
```

**Required:**
```php
/**
 * Plugin Name: iNaturalist Observations WP
 * Plugin URI: https://github.com/yourusername/inat-observations-wp
 * Description: Display iNaturalist project observations on your WordPress site
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inat-observations
 * Domain Path: /languages
 */

// Copyright notice
/**
 * iNaturalist Observations WP - WordPress Plugin
 * Copyright (C) 2024 Your Name
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
```

**Also Needed:**
- `LICENSE` or `LICENSE.txt` file in root directory
- License notice in all PHP files

**Epic:** E-COMP-002: Add GPL License Headers

---

### COMP-CRIT-003: iNaturalist Attribution Required 🟡

**Legal Requirement:** iNaturalist API Terms of Service require attribution

**iNaturalist ToS:** https://www.inaturalist.org/pages/terms

**Key Requirements:**
1. Display photo licenses (CC-BY, CC-BY-NC, etc.)
2. Credit observers for their observations
3. Link back to iNaturalist observation page
4. Display photo copyright holder

**Current State:** No attribution displayed

**Required Implementation:**
```javascript
function renderObservation(obs) {
    return `
        <div class="inat-card">
            <img src="${obs.photos[0].url}" alt="${obs.species_guess}">

            <div class="inat-card-content">
                <h3>${obs.species_guess}</h3>

                <!-- REQUIRED: Observer credit -->
                <p class="inat-observer">
                    Observed by <a href="${obs.user.uri}">${obs.user.login}</a>
                </p>

                <!-- REQUIRED: Photo license -->
                <p class="inat-license">
                    Photo: © ${obs.photos[0].attribution}
                    <a href="${obs.photos[0].license_code_url}">
                        ${obs.photos[0].license_code}
                    </a>
                </p>

                <!-- REQUIRED: Link to iNaturalist -->
                <a href="${obs.uri}" target="_blank">
                    View on iNaturalist
                </a>
            </div>
        </div>
    `;
}
```

**Footer Attribution:**
```php
// In shortcode output
echo '<div class="inat-footer">';
echo '<p>Data provided by <a href="https://www.inaturalist.org">iNaturalist</a>.</p>';
echo '<p>Photos are copyright of their respective owners.</p>';
echo '</div>';
```

**Epic:** E-COMP-003: iNaturalist Attribution Compliance

---

### COMP-CRIT-004: No Privacy Policy / GDPR Compliance 🟡

**Regulations:**
- **GDPR** (EU): General Data Protection Regulation
- **CCPA** (California): California Consumer Privacy Act
- **WordPress.org**: Requires privacy policy declaration

**Current State:** No privacy policy, no data handling disclosure

**Data Collected by Plugin:**
1. **API Data:** iNaturalist observations (public data)
2. **User Settings:** Project slug, API token (stored in WordPress options)
3. **Transient Cache:** API responses (temporary)
4. **Potentially:** User IP addresses (if rate limiting implemented)

**Required:**

**1. Privacy Policy Section (for readme.txt):**
```
== Privacy Policy ==

This plugin fetches public observation data from the iNaturalist API
(https://api.inaturalist.org). No personal data from your site visitors
is transmitted to iNaturalist.

Data stored locally:
* iNaturalist observations (public data)
* Your iNaturalist API token (if configured)
* Cached API responses (temporary, expires after 1 hour)

If you use this plugin, we recommend adding the following to your site's
privacy policy:

"This site displays biodiversity observations from iNaturalist.org.
The observation data shown is publicly available and sourced from the
iNaturalist API. No visitor data is shared with iNaturalist."
```

**2. Data Export/Deletion Hooks (GDPR Right to Erasure):**
```php
// Register privacy policy content
function inat_obs_add_privacy_policy_content() {
    if (!function_exists('wp_add_privacy_policy_content')) return;

    $content = '<h2>iNaturalist Observations</h2>'
             . '<p>This plugin displays public biodiversity data from iNaturalist.org...</p>';

    wp_add_privacy_policy_content('iNaturalist Observations', $content);
}
add_action('admin_init', 'inat_obs_add_privacy_policy_content');

// Personal data exporter (GDPR compliance)
function inat_obs_register_exporters($exporters) {
    $exporters['inat-observations'] = [
        'exporter_friendly_name' => __('iNaturalist Observations Settings', 'inat-observations'),
        'callback' => 'inat_obs_exporter',
    ];
    return $exporters;
}
add_filter('wp_privacy_personal_data_exporters', 'inat_obs_register_exporters');

function inat_obs_exporter($email_address) {
    // Export user's configured settings if admin email matches
    $admin_email = get_option('admin_email');
    if ($email_address !== $admin_email) {
        return ['data' => [], 'done' => true];
    }

    $data = [
        [
            'group_id' => 'inat-settings',
            'group_label' => 'iNaturalist Observations Settings',
            'item_id' => 'settings',
            'data' => [
                ['name' => 'Project Slug', 'value' => get_option('inat_obs_project_slug')],
                ['name' => 'API Token', 'value' => '[REDACTED]'],
            ],
        ],
    ];

    return ['data' => $data, 'done' => true];
}
```

**Epic:** E-COMP-004: Privacy Policy & GDPR Compliance

---

### COMP-CRIT-005: No Internationalization (i18n) 🟡

**WordPress.org Requirement:** Plugins must be translation-ready

**Current State:**
- No text domain defined
- No translation functions used
- Hardcoded English strings

**Required Changes:**

**1. Define Text Domain:**
```php
// In main plugin file
load_plugin_textdomain('inat-observations', false, dirname(plugin_basename(__FILE__)) . '/languages');
```

**2. Wrap All Strings:**
```php
// Before:
echo '<h1>iNaturalist Observations Settings</h1>';
echo '<p>Settings UI not yet implemented.</p>';

// After:
echo '<h1>' . esc_html__('iNaturalist Observations Settings', 'inat-observations') . '</h1>';
echo '<p>' . esc_html__('Settings UI not yet implemented.', 'inat-observations') . '</p>';

// With variables:
printf(
    esc_html__('Loaded %d observations.', 'inat-observations'),
    $count
);
```

**3. JavaScript Strings:**
```php
// Localize JavaScript strings
wp_localize_script('inat-obs-main', 'inatObsStrings', [
    'loading' => __('Loading observations...', 'inat-observations'),
    'loadingFilters' => __('Loading filters...', 'inat-observations'),
    'fetchFailed' => __('Fetch failed. Please try again.', 'inat-observations'),
    'noResults' => __('No observations found.', 'inat-observations'),
]);
```

**4. Create POT File (Translation Template):**
```bash
# Generate .pot file for translators
wp i18n make-pot . languages/inat-observations.pot
```

**Epic:** E-COMP-005: Internationalization (i18n)

---

## HIGH Priority Compliance

### COMP-HIGH-001: WordPress Coding Standards

**Issue:** Code does not follow WordPress Coding Standards (WPCS)

**Standards:** https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/

**Common Violations:**
- Mixed indentation (tabs vs spaces)
- Inconsistent brace placement
- Missing PHPDoc blocks
- No output escaping

**Solution:**
```bash
# Install PHPCS and WordPress standards
composer require --dev squizlabs/php_codesniffer
composer require --dev wp-coding-standards/wpcs

# Configure PHPCS
./vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs

# Check code
./vendor/bin/phpcs --standard=WordPress wp-content/plugins/inat-observations-wp/

# Auto-fix some issues
./vendor/bin/phpcbf --standard=WordPress wp-content/plugins/inat-observations-wp/
```

**Epic:** E-COMP-006: WordPress Coding Standards Compliance

---

### COMP-HIGH-002: Sanitization & Validation Documentation

**WordPress.org Requirement:** Document all sanitization/validation

**Required Comments:**
```php
function inat_obs_admin_page() {
    // Check user capabilities (WordPress security requirement)
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized access.', 'inat-observations'));
    }

    // Verify nonce (CSRF protection - WordPress security requirement)
    check_admin_referer('inat_obs_settings');

    // Sanitize text input (prevent XSS - WordPress security requirement)
    $project_slug = sanitize_text_field($_POST['project_slug']);

    // Validate and sanitize numeric input (WordPress security requirement)
    $cache_lifetime = absint($_POST['cache_lifetime']);
    $cache_lifetime = max(60, min(86400, $cache_lifetime)); // Clamp to 1 min - 24 hours

    // Escape output (prevent XSS - WordPress security requirement)
    echo '<h1>' . esc_html__('Settings', 'inat-observations') . '</h1>';
}
```

**Epic:** E-COMP-007: Security Documentation

---

### COMP-HIGH-003: Accessibility Statement

**Requirement:** Document accessibility compliance level

**In readme.txt:**
```
== Accessibility ==

This plugin strives for WCAG 2.1 Level AA compliance:

* All images have descriptive alt text
* Keyboard navigation is fully supported
* Color contrast ratios meet AA standards (4.5:1 minimum)
* ARIA labels provided for interactive elements
* Focus indicators are visible
* Screen reader compatible

If you encounter accessibility issues, please report them on our
GitHub repository.
```

**Epic:** E-COMP-008: Accessibility Statement

---

## MEDIUM Priority Compliance

### COMP-MED-001: Uninstall Data Cleanup (GDPR)

**GDPR Requirement:** "Right to Erasure" - clean up all data on uninstall

**Current State:** `uninstall.php` is a stub

**Required:**
```php
// uninstall.php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// 1. Drop custom tables (per GDPR Right to Erasure)
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inat_observations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inat_observation_fields");

// 2. Delete all plugin options
delete_option('inat_obs_project_slug');
delete_option('inat_obs_api_token');
delete_option('inat_obs_cache_lifetime');
delete_option('inat_obs_db_version');
delete_option('inat_obs_last_sync');

// 3. Delete transients (cached data)
$wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_inat_obs_%'");
$wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_timeout_inat_obs_%'");

// 4. Clear scheduled cron jobs
wp_clear_scheduled_hook('inat_obs_refresh');

// 5. Log uninstall for compliance audit trail (optional)
error_log('[iNat Obs] Plugin uninstalled - all data removed per GDPR');
```

**Epic:** E-COMP-009: GDPR-Compliant Uninstall

---

### COMP-MED-002: Trademark Usage

**Issue:** "iNaturalist" is a registered trademark of California Academy of Sciences

**Guideline:** https://www.inaturalist.org/pages/about

**Allowed:**
- ✅ "iNaturalist Observations for WordPress"
- ✅ "Displays data from iNaturalist"

**Not Allowed:**
- ❌ "Official iNaturalist Plugin" (implies endorsement)
- ❌ "iNaturalist WP" (too similar to brand name)

**Recommendation:**
```
Plugin Name: Biodiversity Observations (for iNaturalist)
or
Plugin Name: Nature Observer - iNaturalist Display
```

**Epic:** E-COMP-010: Trademark Compliance Review

---

### COMP-MED-003: API Rate Limit Compliance

**iNaturalist API Guidelines:**
- Rate limit: 60 requests/minute (unauthenticated)
- Rate limit: 100 requests/minute (with token)
- Must respect 429 Too Many Requests responses

**Current State:** No rate limit handling

**Required:**
```php
function inat_obs_fetch_observations($url = null, $per_page = 100) {
    // Check rate limit budget
    $requests_this_minute = get_transient('inat_api_requests') ?: 0;
    $max_requests = get_option('inat_obs_api_token') ? 100 : 60;

    if ($requests_this_minute >= $max_requests) {
        return new WP_Error('rate_limit', 'iNaturalist API rate limit reached');
    }

    $response = wp_remote_get($url);

    // Handle 429 Too Many Requests
    if (wp_remote_retrieve_response_code($response) === 429) {
        $retry_after = wp_remote_retrieve_header($response, 'retry-after') ?: 60;
        return new WP_Error('rate_limited', "Rate limited. Retry after {$retry_after}s");
    }

    // Track request count
    set_transient('inat_api_requests', $requests_this_minute + 1, 60);

    return $response;
}
```

**Epic:** E-COMP-011: API Rate Limit Compliance

---

## LOW Priority Compliance

### COMP-LOW-001: Screenshot Requirements

**WordPress.org Requirement:** 3-5 screenshots for plugin directory

**Required:**
- `assets/screenshot-1.png` (772×250px) - Main feature
- `assets/screenshot-2.png` - Settings page
- `assets/screenshot-3.png` - Frontend display

**Epic:** E-COMP-012: Screenshots for WordPress.org

---

### COMP-LOW-002: Plugin Icon/Banner

**WordPress.org Assets:**
- `assets/icon-128x128.png`
- `assets/icon-256x256.png`
- `assets/banner-772x250.png`
- `assets/banner-1544x500.png` (2x retina)

**Epic:** E-COMP-013: WordPress.org Graphics Assets

---

## Compliance Checklist (Pre-Submission)

### WordPress.org Submission Requirements

- [ ] `readme.txt` with all required sections
- [ ] GPL-compatible license declared
- [ ] License headers in all PHP files
- [ ] Text domain matches plugin slug
- [ ] All strings internationalized
- [ ] WordPress Coding Standards compliance (PHPCS clean)
- [ ] No PHP errors/warnings
- [ ] Tested on latest WordPress version
- [ ] Tested on minimum WordPress version (5.8+)
- [ ] Security review passed (no known vulnerabilities)
- [ ] All output escaped
- [ ] All input sanitized
- [ ] Nonces verified
- [ ] Capability checks in place
- [ ] Accessibility statement included
- [ ] Privacy policy content registered
- [ ] Uninstall cleanup implemented
- [ ] No "call home" or external tracking
- [ ] No minified scripts without source
- [ ] Screenshots provided
- [ ] Icon/banner graphics provided

### Legal Compliance

- [ ] iNaturalist attribution displayed
- [ ] Photo licenses shown
- [ ] Observer credits included
- [ ] Privacy policy section in readme
- [ ] GDPR data export hooks
- [ ] GDPR data erasure hooks
- [ ] API rate limits respected
- [ ] Terms of Service compliance documented

---

## Epic Summary

| Epic ID | Title | Priority | Effort | Impact |
|---------|-------|----------|--------|--------|
| E-COMP-001 | Create readme.txt | CRITICAL | 3h | WP.org requirement |
| E-COMP-002 | Add GPL License Headers | CRITICAL | 2h | Legal requirement |
| E-COMP-003 | iNaturalist Attribution | CRITICAL | 4h | Legal requirement |
| E-COMP-004 | Privacy Policy & GDPR | HIGH | 6h | GDPR compliance |
| E-COMP-005 | Internationalization | HIGH | 8h | WP.org requirement |
| E-COMP-006 | Coding Standards | HIGH | 6h | WP.org requirement |
| E-COMP-007 | Security Documentation | MEDIUM | 2h | Best practice |
| E-COMP-008 | Accessibility Statement | MEDIUM | 1h | Transparency |
| E-COMP-009 | GDPR Uninstall | MEDIUM | 2h | GDPR compliance |
| E-COMP-010 | Trademark Review | MEDIUM | 1h | Legal safety |
| E-COMP-011 | API Rate Limit Compliance | MEDIUM | 3h | Terms of Service |
| E-COMP-012 | Screenshots | LOW | 2h | WP.org listing |
| E-COMP-013 | Graphics Assets | LOW | 3h | WP.org branding |

**Total Estimated Effort:** ~43 hours

---

**Next Actions (Critical Path):**
1. E-COMP-002 (License headers) - 2 hours, legal requirement
2. E-COMP-001 (readme.txt) - 3 hours, WP.org blocker
3. E-COMP-003 (iNaturalist attribution) - 4 hours, legal requirement
4. E-COMP-005 (i18n) - 8 hours, WP.org requirement

**Reviewed by:** Compliance & Legal Advisor Agent
**Date:** 2026-01-02
