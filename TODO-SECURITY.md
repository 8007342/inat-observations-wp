# TODO-SECURITY.md - Security Audit & Hardening

**Reviewed by:** Ethical Hacker / Security Auditor
**Date:** 2026-01-02
**Plugin Version:** 0.1.0
**Security Posture:** 5/10 (Basic protections present, critical gaps exist)

---

## Executive Summary

The inat-observations-wp plugin demonstrates **good security fundamentals** (CSRF protection, input sanitization, SQL injection prevention) but has **critical vulnerabilities** that must be fixed before production use.

**Most Critical Issues:**
1. REST API publicly accessible without authentication
2. No rate limiting (DoS/quota exhaustion risk)
3. Database format specifier mismatch (potential SQL errors)
4. No output escaping (XSS risk)
5. No API response validation (injection risk)

**Risk Level:** MEDIUM-HIGH (not safe for production)

---

## CRITICAL Vulnerabilities

### S-CRIT-001: REST API Publicly Accessible 🔴

**Severity:** HIGH
**CVSS Score:** 6.5 (Medium) - Information Disclosure + Resource Exhaustion

**Vulnerability:**
```php
// File: includes/rest.php:9
register_rest_route('inat/v1', '/observations', [
    'methods'             => 'GET',
    'callback'            => 'inat_obs_rest_get_observations',
    'permission_callback' => '__return_true', // ⚠️ DANGEROUS
]);
```

**Attack Vectors:**
1. **DDoS Amplification:** Attacker requests `/wp-json/inat/v1/observations?per_page=100` repeatedly
2. **API Quota Exhaustion:** Burns through iNaturalist API rate limits
3. **Information Disclosure:** Exposes project data without authentication

**Exploitation:**
```bash
# Automated scraping
while true; do
    curl https://target.com/wp-json/inat/v1/observations?per_page=100
    sleep 0.1 # 10 requests/second
done
```

**Impact:**
- Site bandwidth exhaustion
- iNaturalist API key blacklisted
- Denial of service for legitimate users
- Unwanted data scraping

**Remediation:**
```php
register_rest_route('inat/v1', '/observations', [
    'methods'             => 'GET',
    'callback'            => 'inat_obs_rest_get_observations',
    'permission_callback' => function() {
        // Option 1: Require authentication
        return is_user_logged_in();

        // Option 2: Allow public but with rate limiting
        return inat_obs_check_rate_limit(get_client_ip());

        // Option 3: Require specific capability
        return current_user_can('read');
    },
]);
```

**Epic:** E-SEC-001: REST API Access Control

---

### S-CRIT-002: No Rate Limiting 🔴

**Severity:** HIGH
**CVSS Score:** 7.5 (High) - Availability Impact

**Vulnerability:**
- AJAX endpoint unprotected (`shortcode.php:46`)
- REST endpoint unprotected (`rest.php`)
- No request throttling
- No IP-based blocking

**Attack Scenario:**
```javascript
// Attacker script
setInterval(() => {
    fetch('/wp-admin/admin-ajax.php?action=inat_obs_fetch&_ajax_nonce=' + nonce)
}, 10); // 100 requests/second
```

**Impact:**
- Server resource exhaustion (CPU, memory, bandwidth)
- Database connection pool saturation
- API rate limit exceeded → service blocked
- Transient storage bloat

**Remediation - Rate Limiter Implementation:**
```php
function inat_obs_check_rate_limit($client_id, $max_requests = 10, $window = 60) {
    $transient_key = 'inat_rate_limit_' . md5($client_id);
    $requests = get_transient($transient_key) ?: [];

    // Remove requests older than window
    $cutoff = time() - $window;
    $requests = array_filter($requests, fn($ts) => $ts > $cutoff);

    if (count($requests) >= $max_requests) {
        return new WP_Error('rate_limit', 'Too many requests', ['status' => 429]);
    }

    $requests[] = time();
    set_transient($transient_key, $requests, $window);

    return true;
}

function inat_obs_ajax_fetch() {
    // Rate limit by IP
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $limit_check = inat_obs_check_rate_limit($client_ip);

    if (is_wp_error($limit_check)) {
        wp_send_json_error(['message' => 'Rate limit exceeded'], 429);
    }

    check_ajax_referer('inat_obs_fetch', 'nonce');
    // ... rest of handler
}
```

**Epic:** E-SEC-002: Rate Limiting Implementation

---

### S-CRIT-003: Database Format Specifier Mismatch 🟡

**Severity:** MEDIUM
**CVSS Score:** 4.3 (Medium) - Availability Impact

**Vulnerability:**
```php
// File: includes/db-schema.php:41-54
$wpdb->replace(
    $table,
    [
        'id'          => $item['id'],
        'uuid'        => sanitize_text_field($item['uuid'] ?? ''),
        'observed_on' => $obs_on,
        'species_guess' => sanitize_text_field($item['species_guess'] ?? ''),
        'place_guess' => sanitize_text_field($item['place_guess'] ?? ''),
        'metadata'    => json_encode($item['observation_field_values'] ?? []),
        'created_at'  => current_time('mysql'),
        'updated_at'  => current_time('mysql'), // ⚠️ 8th field
    ],
    ['%d','%s','%s','%s','%s','%s','%s'] // ⚠️ Only 7 format specifiers!
);
```

**Issue:**
- 8 data fields provided
- Only 7 format specifiers
- `updated_at` has no format specifier
- Could cause unpredictable behavior or errors

**Impact:**
- Database write failures
- Silent data corruption
- `updated_at` may not auto-update properly

**Remediation:**
```php
$wpdb->replace(
    $table,
    [
        'id'          => $item['id'],
        'uuid'        => sanitize_text_field($item['uuid'] ?? ''),
        'observed_on' => $obs_on,
        'species_guess' => sanitize_text_field($item['species_guess'] ?? ''),
        'place_guess' => sanitize_text_field($item['place_guess'] ?? ''),
        'metadata'    => json_encode($item['observation_field_values'] ?? []),
        'created_at'  => current_time('mysql'),
        'updated_at'  => current_time('mysql'),
    ],
    ['%d','%s','%s','%s','%s','%s','%s','%s'] // ✅ 8 format specifiers
);
```

**Epic:** E-SEC-003: Fix Database Format Specifiers

---

### S-CRIT-004: No Output Escaping 🟡

**Severity:** MEDIUM (mitigated by input sanitization)
**CVSS Score:** 6.1 (Medium) - XSS Potential

**Vulnerability:**
```php
// File: includes/shortcode.php:26-31
echo '<div id="inat-observations-root">';
echo '<div class="inat-filters">';
echo '<select id="inat-filter-field"><option value="">Loading filters...</option></select>';
echo '</div>';
echo '<div id="inat-list">Loading observations...</div>';
echo '</div>';
```

**Issue:**
- Static HTML for now (safe)
- But if dynamic data added without escaping → XSS
- No `esc_html()`, `esc_attr()`, `esc_url()` usage

**Attack Scenario (if species_guess rendered):**
```php
// Unsafe (hypothetical):
echo '<li>' . $observation['species_guess'] . '</li>';

// If species_guess contains: <script>alert('XSS')</script>
// Result: XSS execution
```

**Mitigation:**
```php
// Correct escaping
echo '<li>' . esc_html($observation['species_guess']) . '</li>';
echo '<a href="' . esc_url($observation['uri']) . '">';
echo '<img src="' . esc_url($photo_url) . '" alt="' . esc_attr($species) . '">';
```

**Current Protection:**
- Input sanitized with `sanitize_text_field()` in `db-schema.php:45-48`
- Strips HTML tags on input
- **But:** Defense in depth requires output escaping too

**Epic:** E-SEC-004: Add Output Escaping

---

### S-CRIT-005: No API Response Validation 🟡

**Severity:** MEDIUM
**CVSS Score:** 5.3 (Medium) - Data Integrity

**Vulnerability:**
```php
// File: includes/api.php:56
$data = json_decode(wp_remote_retrieve_body($response), true);
// No validation of $data structure
return $data;
```

**Risks:**
1. **Malformed JSON:** `json_decode()` returns null, causes errors
2. **Missing Fields:** Accessing `$data['results']` when key doesn't exist
3. **Type Confusion:** Expecting array, get string
4. **Injection via API:** If iNaturalist compromised, could inject malicious data

**Exploitation:**
```json
// Attacker MitM response:
{
  "results": [
    {
      "species_guess": "<script>payload</script>",
      "metadata": "<?php system($_GET['cmd']); ?>"
    }
  ]
}
```

**Remediation:**
```php
function inat_obs_validate_response($data) {
    if (!is_array($data)) {
        return new WP_Error('invalid_response', 'API response is not an array');
    }

    if (!isset($data['results']) || !is_array($data['results'])) {
        return new WP_Error('missing_results', 'API response missing results array');
    }

    // Validate each observation
    foreach ($data['results'] as $obs) {
        if (!isset($obs['id']) || !is_numeric($obs['id'])) {
            return new WP_Error('invalid_observation', 'Observation missing ID');
        }
    }

    return $data;
}

function inat_obs_fetch_observations($url = null, $per_page = 100) {
    // ... fetch logic ...

    $json_error = json_last_error();
    if ($json_error !== JSON_ERROR_NONE) {
        return new WP_Error('json_decode_failed', json_last_error_msg());
    }

    $validation = inat_obs_validate_response($data);
    if (is_wp_error($validation)) {
        return $validation;
    }

    return $data;
}
```

**Epic:** E-SEC-005: API Response Validation

---

## HIGH Priority Security Issues

### S-HIGH-001: API Token in Environment Variables

**Severity:** MEDIUM
**File:** `includes/api.php:17`

**Issue:**
- API token read from `getenv('INAT_API_TOKEN')`
- Exposed in `phpinfo()`
- May appear in error logs
- Not encrypted at rest

**Best Practice:**
```php
// Store in WordPress options (encrypted in DB)
function inat_obs_get_api_token() {
    $token = get_option('inat_obs_api_token', '');

    // Fallback to env var for backward compatibility
    if (empty($token)) {
        $token = getenv('INAT_API_TOKEN');
    }

    return $token;
}

// Encrypt token before storage (optional)
function inat_obs_encrypt_token($token) {
    if (!function_exists('openssl_encrypt')) {
        return base64_encode($token); // Fallback
    }

    $key = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);

    return base64_encode($iv . $encrypted);
}
```

**Epic:** E-SEC-006: Secure Token Storage

---

### S-HIGH-002: No HTTPS Enforcement

**Severity:** MEDIUM
**File:** Multiple

**Issue:**
- API calls use hardcoded `https://` (good)
- But no enforcement that WordPress itself uses HTTPS
- Nonces, cookies transmitted over HTTP if WP not HTTPS
- AJAX requests vulnerable to MitM

**Remediation:**
```php
function inat_obs_enforce_https() {
    if (!is_ssl() && !is_admin()) {
        wp_die(
            'This plugin requires HTTPS. Please enable SSL on your site.',
            'HTTPS Required',
            ['response' => 403]
        );
    }
}
add_action('init', 'inat_obs_enforce_https');
```

**Epic:** E-SEC-007: HTTPS Enforcement

---

### S-HIGH-003: No Nonce Expiry Handling

**Severity:** LOW-MEDIUM
**File:** `assets/js/main.js`

**Issue:**
- Nonce embedded in page load
- Nonces expire after 12-24 hours
- Long-lived browser tabs will fail silently

**User Impact:**
- "Fetch failed" error after 12 hours
- No auto-refresh mechanism
- Poor UX

**Remediation:**
```javascript
function fetchWithNonceRefresh(url, nonce) {
    return fetch(url)
        .then(response => {
            if (response.status === 403) {
                // Nonce expired, refresh page
                location.reload();
            }
            return response.json();
        });
}
```

**Epic:** E-SEC-008: Nonce Expiry Handling

---

## MEDIUM Priority Security Issues

### S-MED-001: No Input Length Validation

**Severity:** LOW-MEDIUM
**File:** `includes/db-schema.php`

**Issue:**
- No maximum length checks before database insert
- Could store huge JSON blobs (malicious or accidental)
- Database storage exhaustion risk

**Remediation:**
```php
function inat_obs_store_items($items) {
    foreach ($items as $item) {
        $metadata = json_encode($item['observation_field_values'] ?? []);

        // Prevent storage of excessively large metadata
        if (strlen($metadata) > 65535) { // TEXT column limit
            inat_obs_log_error('metadata_too_large', 'Metadata exceeds 64KB', [
                'observation_id' => $item['id'],
                'size' => strlen($metadata),
            ]);
            continue;
        }

        // ... insert logic
    }
}
```

**Epic:** E-SEC-009: Input Length Validation

---

### S-MED-002: No SQL Injection Testing

**Severity:** LOW (protected by wpdb, but untested)
**File:** `includes/db-schema.php`

**Current Protection:**
- Uses `wpdb->replace()` with format specifiers ✅
- No raw SQL concatenation ✅

**Risk:**
- If future queries use `$wpdb->query()` without preparation
- Dynamic table/column names

**Testing Needed:**
```php
// Unit test for SQL injection protection
function test_sql_injection_protection() {
    $malicious_input = [
        'id' => 1,
        'species_guess' => "'; DROP TABLE wp_inat_observations; --",
        'place_guess' => "1' OR '1'='1",
    ];

    inat_obs_store_items([$malicious_input]);

    // Assert table still exists
    global $wpdb;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}inat_observations'");
    assert($table_exists !== null);
}
```

**Epic:** E-SEC-010: Security Test Suite

---

### S-MED-003: No Content Security Policy

**Severity:** LOW
**File:** N/A (missing)

**Recommendation:**
```php
function inat_obs_add_csp_header() {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; img-src 'self' https://inaturalist-open-data.s3.amazonaws.com;");
}
add_action('send_headers', 'inat_obs_add_csp_header');
```

**Epic:** E-SEC-011: Content Security Policy

---

## LOW Priority Security Hygiene

### S-LOW-001: Uninstall Protection Incomplete

**File:** `uninstall.php:2-3`

**Current:**
```php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
// TODO: Drop custom tables, delete transients, remove options
```

**Issue:**
- No actual cleanup
- Leaves data in database after uninstall
- Privacy concern (GDPR)

**Remediation:**
```php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

// Drop custom tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inat_observations");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inat_observation_fields");

// Delete options
delete_option('inat_obs_project_slug');
delete_option('inat_obs_api_token');
delete_option('inat_obs_db_version');

// Delete all transients
$wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_inat_obs_%'");
$wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_timeout_inat_obs_%'");

// Clear scheduled cron
wp_clear_scheduled_hook('inat_obs_refresh');
```

**Epic:** E-SEC-012: Complete Uninstall Cleanup

---

### S-LOW-002: No Security Headers

**Recommendation:**
```php
function inat_obs_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
add_action('send_headers', 'inat_obs_security_headers');
```

**Epic:** E-SEC-013: Security Headers

---

## Security Checklist (Pre-Production)

- [ ] **S-CRIT-001:** REST API authentication required
- [ ] **S-CRIT-002:** Rate limiting implemented
- [ ] **S-CRIT-003:** Database format specifiers fixed
- [ ] **S-CRIT-004:** Output escaping on all dynamic content
- [ ] **S-CRIT-005:** API response validation implemented
- [ ] **S-HIGH-001:** API token stored securely
- [ ] **S-HIGH-002:** HTTPS enforced
- [ ] **S-HIGH-003:** Nonce expiry handled gracefully
- [ ] **S-MED-001:** Input length validation
- [ ] **S-MED-002:** SQL injection tests passing
- [ ] **S-LOW-001:** Uninstall cleanup complete

---

## Penetration Testing Plan

### Automated Scans Needed:
1. **WPScan** - WordPress vulnerability database
2. **OWASP ZAP** - Web app security scanner
3. **Burp Suite** - Manual penetration testing
4. **SQLMap** - SQL injection testing

### Manual Tests:
1. CSRF bypass attempts
2. XSS injection in all input fields
3. SQL injection in API parameters
4. Rate limit bypass techniques
5. Authentication bypass attempts
6. Session hijacking tests

---

## Epic Summary

| Epic ID | Title | Severity | Effort | Impact |
|---------|-------|----------|--------|--------|
| E-SEC-001 | REST API Access Control | CRITICAL | 2h | Prevents abuse |
| E-SEC-002 | Rate Limiting | CRITICAL | 4h | DoS prevention |
| E-SEC-003 | Fix Format Specifiers | CRITICAL | 0.5h | Data integrity |
| E-SEC-004 | Output Escaping | HIGH | 3h | XSS prevention |
| E-SEC-005 | API Response Validation | HIGH | 3h | Injection prevention |
| E-SEC-006 | Secure Token Storage | MEDIUM | 2h | Data protection |
| E-SEC-007 | HTTPS Enforcement | MEDIUM | 1h | MitM prevention |
| E-SEC-008 | Nonce Expiry Handling | MEDIUM | 2h | UX improvement |
| E-SEC-009 | Input Length Validation | LOW | 1h | Storage protection |
| E-SEC-010 | Security Test Suite | MEDIUM | 8h | Quality assurance |
| E-SEC-011 | Content Security Policy | LOW | 1h | Defense in depth |
| E-SEC-012 | Uninstall Cleanup | LOW | 1h | Privacy hygiene |
| E-SEC-013 | Security Headers | LOW | 1h | Defense in depth |

**Total Estimated Effort:** ~29.5 hours

---

**Next Actions:**
1. Fix S-CRIT-003 (format specifiers) - 30 minutes, immediate
2. Implement S-CRIT-001 (REST auth) - 2 hours, blocks production
3. Add S-CRIT-002 (rate limiting) - 4 hours, DoS protection
4. Apply S-CRIT-004 (output escaping) - 3 hours, XSS prevention

**Reviewed by:** Ethical Hacker Agent
**Date:** 2026-01-02
