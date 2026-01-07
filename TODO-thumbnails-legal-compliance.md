# TODO: Pretty Thumbnails with Legal Compliance

## Overview

Add thumbnail images to observation displays while ensuring full compliance with iNaturalist's Terms of Service, API usage policies, and XSS security guidelines.

## Requirements

### Functional
- [ ] Display thumbnail images for each observation
- [ ] Settings page toggle: "Enable Thumbnails" (default: CHECKED)
- [ ] Support both GRID and LIST view modes
- [ ] Graceful degradation when images unavailable

### Legal & Compliance
- [ ] **CRITICAL**: Verify iNaturalist Terms of Service allows embedding their hosted images
- [ ] **CRITICAL**: Review iNaturalist API Terms (https://www.inaturalist.org/pages/api+terms+of+use)
- [ ] **CRITICAL**: Check iNaturalist Developer Documentation for image usage guidelines
- [ ] Add legal disclaimer in Settings page: "Images are hosted by iNaturalist"
- [ ] Include attribution requirements (if any) per iNat TOS
- [ ] Document rate limits for image requests (if separate from API limits)

### Security (XSS Prevention)
- [ ] **S-HIGH-001**: Sanitize all image URLs before rendering
- [ ] **S-HIGH-002**: Use Content Security Policy (CSP) headers for img-src
- [ ] **S-HIGH-003**: Validate image URLs match iNaturalist domain pattern
- [ ] **S-HIGH-004**: Use `crossorigin="anonymous"` attribute on img tags (CORS)
- [ ] **S-HIGH-005**: Escape all alt text and metadata displayed alongside images
- [ ] **S-MED-001**: Implement lazy loading for performance
- [ ] **S-LOW-001**: Add loading placeholder/fallback for broken images

## Implementation Options

### Option 1: Hotlink iNaturalist-Hosted Images (PREFERRED IF ALLOWED)
**Pros:**
- Zero storage overhead
- Always up-to-date with iNaturalist
- No bandwidth costs for plugin user
- Respects photographer's rights via iNat

**Cons:**
- Requires iNat TOS compliance check
- External dependency (images may break if iNat changes URL structure)
- Privacy consideration: User browsers make requests to iNaturalist

**Implementation:**
```php
// In inat_obs_store_items(), extract thumbnail URL from API response
$photo_url = $obs['photos'][0]['url'] ?? null;
// Store sanitized URL in database
```

```javascript
// In main.js, render with security
const imgUrl = escapeHtml(obs.photo_url);
html += '<img src="' + imgUrl + '" alt="' + escapeHtml(species) + '" crossorigin="anonymous" loading="lazy" />';
```

### Option 2: Cache Thumbnail Blobs in Database
**Pros:**
- Full control over availability
- No external requests after initial fetch
- Works offline (if WP site cached)

**Cons:**
- Database bloat (2000 obs × ~50KB thumbnail = ~100MB)
- Bandwidth usage during refresh (download all images)
- Copyright compliance more complex (redistributing images)
- Cache invalidation complexity

**NOT RECOMMENDED** unless Option 1 violates iNat TOS

### Option 3: Cache Thumbnails in WordPress Filesystem
**Pros:**
- Faster than database blobs
- Can use WordPress media library
- Easy to clean with cron job

**Cons:**
- Same copyright/TOS concerns as Option 2
- Filesystem management complexity
- Needs eviction policy synchronized with data refresh

**NOT RECOMMENDED** unless Option 1 violates iNat TOS

## Research Checklist

- [ ] Read: https://www.inaturalist.org/pages/terms
- [ ] Read: https://www.inaturalist.org/pages/api+terms+of+use
- [ ] Read: https://api.inaturalist.org/v1/docs/ (check for image usage notes)
- [ ] Search iNat forum/GitHub for "embedding images" or "hotlinking"
- [ ] Check if iNat provides CDN URLs specifically for embedding
- [ ] Verify attribution requirements (e.g., must display photographer username)
- [ ] Check if iNat requires backlinks to observation pages

## Settings Page UI

Add to `/includes/admin.php` settings:

```html
<tr>
    <th scope="row">
        <label for="inat_obs_enable_thumbnails">Enable Thumbnails</label>
    </th>
    <td>
        <input type="checkbox" id="inat_obs_enable_thumbnails" name="inat_obs_enable_thumbnails" value="1" <?php checked(get_option('inat_obs_enable_thumbnails', '1'), '1'); ?> />
        <p class="description">
            Display thumbnail images for observations.
            <strong>Images are hosted by iNaturalist.</strong>
            By enabling this feature, you agree to comply with
            <a href="https://www.inaturalist.org/pages/terms" target="_blank">iNaturalist's Terms of Service</a>.
        </p>
    </td>
</tr>
```

## Code Documentation Template

Add to relevant PHP files:

```php
/**
 * Image Usage Compliance (iNaturalist TOS)
 *
 * This plugin displays thumbnail images hosted by iNaturalist.org.
 * Per iNaturalist Terms of Service (verified: YYYY-MM-DD):
 * - Images may be embedded via hotlinking to iNat CDN URLs
 * - Attribution: [DESCRIBE REQUIREMENTS]
 * - Rate Limits: [DESCRIBE LIMITS]
 * - XSS Prevention: All URLs validated against iNat domain pattern
 *
 * @see https://www.inaturalist.org/pages/terms
 * @see https://www.inaturalist.org/pages/api+terms+of+use
 */
```

## Next Steps

1. **RESEARCH FIRST** - Do NOT implement until TOS verified
2. Run Plan agent to architect final approach based on research findings
3. Implement chosen option with full security measures
4. Add comprehensive inline documentation citing TOS sections
5. Test XSS prevention (malicious URL injection attempts)
6. Test with missing images (graceful degradation)

## Stakeholders

- **Security**: Must validate image URLs, prevent XSS
- **Legal**: Must comply with iNaturalist TOS, CC licenses
- **UX**: Images should enhance experience, not break it
- **Performance**: Lazy loading, no excessive bandwidth

---

**STATUS**: 🔴 BLOCKED ON LEGAL RESEARCH
**PRIORITY**: High (major UX enhancement)
**RISK**: Medium (TOS violation could require disabling feature)
