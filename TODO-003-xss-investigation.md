# TODO-003: XSS Investigation - Image Loading Security

**Created**: 2026-01-06
**Priority**: MEDIUM
**Status**: Research Phase
**Depends On**: TODO-002 (Phase 1 implementation)
**Security Level**: S-HIGH-001

---

## Objective

**Investigate XSS risks when loading images from iNaturalist CDN and implement secure image handling.**

Ensure that images loaded from `*.inaturalist.org` cannot be used as attack vectors for:
1. Cross-Site Scripting (XSS)
2. Content injection
3. Malicious redirects
4. Data exfiltration

---

## Background

### Current Implementation

**Image URL Storage** (`includes/db-schema.php:86-91`):
```php
// Extract image URLs
$image_url = '';
$thumbnail_url = '';
if (!empty($r['photos'][0]['url'])) {
    $image_url = esc_url_raw($r['photos'][0]['url']);
    // iNaturalist CDN provides different sizes
    $thumbnail_url = str_replace('/original/', '/medium/', $image_url);
}
```

**Image URL Output** (not yet implemented):
```php
// TODO: Frontend rendering
<img src="<?php echo esc_url($observation['thumbnail_url']); ?>"
     alt="<?php echo esc_attr($observation['species_guess']); ?>">
```

### iNaturalist CDN Structure

**Typical image URL**:
```
https://inaturalist-open-data.s3.amazonaws.com/photos/12345/original.jpg
https://static.inaturalist.org/photos/67890/medium.jpg
```

**Domains**:
- `inaturalist-open-data.s3.amazonaws.com` - AWS S3 bucket
- `static.inaturalist.org` - iNaturalist CDN
- `*.inaturalist.org` - Various subdomains

---

## Research Questions

### 1. Can images contain executable code?

**Hypothesis**: JPEG/PNG files are binary data, not executable

**Investigation**:
- Review WordPress `esc_url()` function behavior
- Test with malformed image URLs
- Check if Content-Type header is validated
- Investigate polyglot file attacks (valid image + valid JS)

**Expected Finding**: Images themselves are safe, but URL injection is a risk

---

### 2. Can image URLs be injected with XSS payloads?

**Attack Vector**:
```php
// Malicious observation with crafted URL
$image_url = 'javascript:alert(1)';
$image_url = 'data:text/html,<script>alert(1)</script>';
$image_url = 'https://evil.com/xss.jpg?redirect=javascript:alert(1)';
```

**Defense**:
```php
// WordPress esc_url_raw() validation
esc_url_raw($image_url, ['http', 'https']); // Only allow http/https protocols

// Additional validation: verify domain
function inat_obs_is_valid_image_url($url) {
    $parsed = parse_url($url);

    // Only allow iNaturalist domains
    $allowed_hosts = [
        'inaturalist-open-data.s3.amazonaws.com',
        'static.inaturalist.org',
        'inaturalist.org',
    ];

    $host = $parsed['host'] ?? '';

    // Check if host matches allowed patterns
    foreach ($allowed_hosts as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return true;
        }
    }

    return false;
}
```

**Test Cases**:
- `javascript:alert(1)` → Rejected
- `data:text/html,...` → Rejected
- `https://evil.com/image.jpg` → Rejected
- `https://static.inaturalist.org/photos/123.jpg` → Accepted

---

### 3. Can Content Security Policy (CSP) headers protect against image-based attacks?

**Current Security Headers** (`includes/init.php:41-47`):
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

**Missing**: Content Security Policy (CSP)

**Recommended CSP**:
```php
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "img-src 'self' https://*.inaturalist.org https://inaturalist-open-data.s3.amazonaws.com; " .
    "script-src 'self' 'unsafe-inline'; " . // WordPress requires inline scripts
    "style-src 'self' 'unsafe-inline'; " .  // WordPress requires inline styles
    "frame-ancestors 'self'"
);
```

**Benefit**: Even if URL validation fails, CSP prevents loading from unauthorized domains

---

### 4. Can images trigger CORS-based attacks?

**Attack**: Malicious image with CORS headers could be used to exfiltrate data

**WordPress Protection**: Images loaded via `<img>` tag don't execute JavaScript

**Risk**: Low (images are passive content)

**Mitigation**: None needed (inherent browser security)

---

### 5. Can lazy loading introduce security risks?

**Lazy Loading Implementation** (to be implemented in Phase 2):
```javascript
// IntersectionObserver for lazy loading
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src; // XSS RISK if data-src not sanitized
            observer.unobserve(img);
        }
    });
});
```

**Risk**: If `data-src` attribute contains malicious URL

**Mitigation**:
```php
// Server-side validation before output
<img data-src="<?php echo esc_url(inat_obs_validate_image_url($url)); ?>"
     alt="<?php echo esc_attr($species); ?>">
```

---

## Security Recommendations

### Recommendation 1: URL Validation Function ✅

**Priority**: CRITICAL

**Implementation**:
```php
/**
 * Validate that image URL is from iNaturalist CDN.
 *
 * @param string $url Raw image URL from API
 * @return string|false Validated URL or false if invalid
 */
function inat_obs_validate_image_url($url) {
    // Sanitize URL
    $url = esc_url_raw($url, ['http', 'https']);

    if (empty($url)) {
        return false;
    }

    // Parse URL
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';

    // Allowed iNaturalist domains
    $allowed_hosts = [
        'inaturalist-open-data.s3.amazonaws.com',
        'static.inaturalist.org',
        'inaturalist.org',
    ];

    // Check if host matches
    foreach ($allowed_hosts as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return $url;
        }
    }

    // Log rejected URL for investigation
    error_log("iNat Observations: Rejected invalid image URL: $url");

    return false;
}
```

**Usage**:
```php
// In inat_obs_store_items()
$image_url = inat_obs_validate_image_url($r['photos'][0]['url'] ?? '');
if (!$image_url) {
    $image_url = ''; // Fallback to empty (no image)
}
```

---

### Recommendation 2: Content Security Policy Header ✅

**Priority**: HIGH

**Implementation**:
```php
// In includes/init.php, update inat_obs_security_headers()
function inat_obs_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // NEW: Content Security Policy
    $csp = [
        "default-src 'self'",
        "img-src 'self' https://*.inaturalist.org https://inaturalist-open-data.s3.amazonaws.com",
        "script-src 'self' 'unsafe-inline'", // WordPress requires inline scripts
        "style-src 'self' 'unsafe-inline'",  // WordPress requires inline styles
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
}
```

**Testing**: Verify CSP doesn't break WordPress admin or frontend

---

### Recommendation 3: Fallback Image for Invalid URLs ✅

**Priority**: MEDIUM

**Implementation**:
```php
// Placeholder image for observations with no valid image
define('INAT_OBS_PLACEHOLDER_IMAGE', INAT_OBS_URL . 'assets/images/placeholder.svg');

function inat_obs_get_safe_image_url($observation) {
    $url = $observation['thumbnail_url'] ?? '';

    // Validate URL
    $validated = inat_obs_validate_image_url($url);

    // Return validated URL or fallback placeholder
    return $validated ?: INAT_OBS_PLACEHOLDER_IMAGE;
}
```

**Placeholder SVG**:
```svg
<!-- assets/images/placeholder.svg -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <rect width="100" height="100" fill="#f0f0f0"/>
  <text x="50" y="50" text-anchor="middle" font-size="12" fill="#999">No Image</text>
</svg>
```

---

### Recommendation 4: Image Loading Tests ✅

**Priority**: MEDIUM

**Test Cases**:

1. **Valid iNaturalist URL**:
   ```php
   $url = 'https://static.inaturalist.org/photos/12345/medium.jpg';
   assert(inat_obs_validate_image_url($url) !== false);
   ```

2. **Invalid domain**:
   ```php
   $url = 'https://evil.com/image.jpg';
   assert(inat_obs_validate_image_url($url) === false);
   ```

3. **JavaScript protocol**:
   ```php
   $url = 'javascript:alert(1)';
   assert(inat_obs_validate_image_url($url) === false);
   ```

4. **Data URI**:
   ```php
   $url = 'data:text/html,<script>alert(1)</script>';
   assert(inat_obs_validate_image_url($url) === false);
   ```

5. **Malformed URL**:
   ```php
   $url = 'not a url';
   assert(inat_obs_validate_image_url($url) === false);
   ```

---

## Implementation Plan

### Step 1: Add URL Validation Function (Day 1)

**File**: `includes/api.php`

**Action**:
- Add `inat_obs_validate_image_url()` function
- Update `inat_obs_store_items()` to use validation
- Add error logging for rejected URLs

---

### Step 2: Update Security Headers (Day 1)

**File**: `includes/init.php`

**Action**:
- Add CSP header to `inat_obs_security_headers()`
- Test on WordPress admin (ensure no breakage)
- Test on frontend (ensure images load)

---

### Step 3: Create Placeholder Image (Day 1)

**File**: `assets/images/placeholder.svg`

**Action**:
- Create simple SVG placeholder
- Add `inat_obs_get_safe_image_url()` helper
- Update frontend rendering to use helper

---

### Step 4: Write Security Tests (Day 2)

**File**: `tests/test-image-security.php` (if using PHPUnit)

**Action**:
- Test valid URLs accepted
- Test invalid URLs rejected
- Test XSS payloads blocked
- Test fallback image works

---

### Step 5: Documentation (Day 2)

**File**: `SECURITY.md`

**Action**:
- Document image URL validation strategy
- List allowed domains
- Explain CSP headers
- Provide examples of rejected URLs

---

## Success Criteria

- [ ] `inat_obs_validate_image_url()` function implemented
- [ ] URL validation rejects all XSS payloads
- [ ] URL validation only accepts iNaturalist domains
- [ ] CSP header configured correctly
- [ ] CSP doesn't break WordPress functionality
- [ ] Placeholder image created and working
- [ ] All security tests pass
- [ ] Documentation complete

---

## Risks & Mitigations

### Risk 1: CSP Breaks WordPress Plugins
**Probability**: Medium
**Impact**: High (site broken)
**Mitigation**:
- Test on dev environment first
- Use `Content-Security-Policy-Report-Only` header initially
- Monitor violation reports
- Whitelist specific plugin domains if needed

### Risk 2: iNaturalist Changes CDN Domains
**Probability**: Low
**Impact**: Medium (images stop loading)
**Mitigation**:
- Monitor error logs for rejected URLs
- Add admin UI to configure allowed domains
- Document domain whitelist in code comments
- Regular review of iNaturalist API docs

### Risk 3: False Positives (Valid URLs Rejected)
**Probability**: Low
**Impact**: Medium (missing images)
**Mitigation**:
- Log all rejected URLs
- Review logs monthly
- Add admin tool to view rejected URLs
- Allow manual override in settings

---

## Related Documents

- **Architecture**: `WORDPRESS-PLUGIN.md` (XSS Investigation section)
- **Security Reference**: WordPress Codex - Data Validation
- **iNaturalist API**: https://api.inaturalist.org/v1/docs/

---

**Status**: TO DO (research phase)
**Next Action**: Step 1 - Add URL validation function
**Owner**: Security Specialist + Full-Stack Developer
