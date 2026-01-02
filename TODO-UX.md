# TODO-UX.md - User Experience & Interface Design

**Reviewed by:** UX/UI Designer
**Date:** 2026-01-02
**Plugin Version:** 0.1.0
**UX Maturity:** 2/10 (Skeleton UI, no interactive features)

---

## Executive Summary

The inat-observations-wp plugin has **minimal UI** - a basic HTML container with placeholder text. No observations are displayed, filters don't work, and there's no visual feedback for user actions. The current state is **unusable for end users**.

**Critical UX Gaps:**
1. No observation list rendering
2. Filter dropdowns never populated
3. No loading states or skeleton screens
4. No error messages shown to users
5. Zero accessibility features
6. Not responsive (mobile-broken)
7. No internationalization

**WCAG Compliance:** ~10% AA (fails most criteria)

---

## CRITICAL UX Issues (Blockers)

### UX-CRIT-001: No Observation List Display 🔴

**Problem:**
Frontend only shows "Loaded X observations." count (`main.js:27`)
- No actual observation cards
- No species names, photos, locations
- No links to iNaturalist

**Files:**
- `wp-content/plugins/inat-observations-wp/assets/js/main.js:20-34`
- `wp-content/plugins/inat-observations-wp/assets/css/main.css` (no card styles)

**User Impact:**
Plugin appears broken. Users see count but no data.

**Design Mockup:**
```
┌──────────────────────────────────────────────────┐
│  🌿 iNaturalist Observations                     │
├──────────────────────────────────────────────────┤
│  Filters: [Taxon ▾] [Location ▾] [Date ▾]       │
├──────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────┐  │
│  │ 📷 [Photo]  Quercus rubra                  │  │
│  │             Northern Red Oak                │  │
│  │             📍 Central Park, New York       │  │
│  │             📅 March 15, 2024               │  │
│  │             [View on iNaturalist →]        │  │
│  └────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────┐  │
│  │ ...next observation...                      │  │
└──────────────────────────────────────────────────┘
```

**Implementation:**
```javascript
function renderObservations(observations) {
    const container = document.getElementById('inat-list');

    if (!observations || observations.length === 0) {
        container.innerHTML = '<p class="inat-empty">No observations found.</p>';
        return;
    }

    const html = observations.map(obs => `
        <div class="inat-card">
            <div class="inat-card-image">
                ${obs.photos?.[0]?.url ?
                    `<img src="${obs.photos[0].url}" alt="${obs.species_guess || 'Observation'}" loading="lazy">` :
                    '<div class="inat-no-image">📷</div>'
                }
            </div>
            <div class="inat-card-content">
                <h3 class="inat-species">${obs.species_guess || 'Unknown species'}</h3>
                <p class="inat-location">📍 ${obs.place_guess || 'Location unknown'}</p>
                <p class="inat-date">📅 ${formatDate(obs.observed_on)}</p>
                <a href="${obs.uri}" class="inat-link" target="_blank" rel="noopener">
                    View on iNaturalist →
                </a>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}
```

**CSS (Responsive Card Layout):**
```css
.inat-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    padding: 1rem;
}

.inat-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.inat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.inat-card-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.inat-no-image {
    width: 100%;
    height: 200px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
}
```

**Epic:** E-UX-001: Observation List Rendering

---

### UX-CRIT-002: Filters Never Populated 🔴

**Problem:**
Filter dropdown shows "Loading filters..." forever (`shortcode.php:28`)
- No metadata parsing
- No unique value extraction
- No filter change handling

**Files:**
- `wp-content/plugins/inat-observations-wp/includes/shortcode.php:28`
- `wp-content/plugins/inat-observations-wp/assets/js/main.js` (no filter logic)

**User Impact:**
Cannot filter observations by any criteria.

**Design Pattern:**
```
┌─────────────────────────────────────────────────┐
│  Filter by:                                      │
│  [All Taxa ▾] [All Locations ▾] [All Dates ▾]  │
│  [Quality Grade: Any ▾] [Reset Filters]         │
└─────────────────────────────────────────────────┘
```

**Implementation:**
```javascript
function buildFilters(observations) {
    const species = [...new Set(observations.map(o => o.species_guess).filter(Boolean))];
    const locations = [...new Set(observations.map(o => o.place_guess).filter(Boolean))];

    const filterSelect = document.getElementById('inat-filter-field');
    filterSelect.innerHTML = `
        <option value="">All Species</option>
        ${species.map(s => `<option value="${s}">${s}</option>`).join('')}
    `;

    filterSelect.addEventListener('change', (e) => {
        const filtered = e.target.value
            ? observations.filter(o => o.species_guess === e.target.value)
            : observations;
        renderObservations(filtered);
    });
}
```

**Epic:** E-UX-002: Dynamic Filter Implementation

---

### UX-CRIT-003: No Loading States 🟡

**Problem:**
Users see "Loading observations..." text with no visual feedback
- No spinner
- No skeleton screens
- No progress indication

**User Impact:**
Appears frozen on slow connections.

**Design:**
```
┌─────────────────────────────────────────────────┐
│  ⏳ Loading observations...                     │
│  ┌────────────────────────────────────────────┐ │
│  │  ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │ │  Skeleton
│  │  ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │ │  card
│  └────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────┘
```

**Implementation:**
```javascript
function showLoadingState() {
    const container = document.getElementById('inat-list');
    container.innerHTML = `
        <div class="inat-loading">
            <div class="inat-spinner"></div>
            <p>Loading observations...</p>
        </div>
    `;
}
```

```css
.inat-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
```

**Epic:** E-UX-003: Loading State & Skeleton Screens

---

### UX-CRIT-004: No Error Messages 🟡

**Problem:**
Errors logged to console but not shown to users (`main.js:30-32`)
- Users see "Fetch failed" in console (if they open DevTools)
- No user-facing error UI

**User Impact:**
Silent failures. Users don't know what went wrong.

**Design:**
```
┌─────────────────────────────────────────────────┐
│  ⚠️ Unable to load observations                 │
│  The iNaturalist API may be temporarily         │
│  unavailable. Please try again later.           │
│  [Retry]                                         │
└─────────────────────────────────────────────────┘
```

**Implementation:**
```javascript
function showError(message) {
    const container = document.getElementById('inat-list');
    container.innerHTML = `
        <div class="inat-error">
            <span class="inat-error-icon">⚠️</span>
            <p class="inat-error-message">${message}</p>
            <button class="inat-retry" onclick="fetchObservations()">Retry</button>
        </div>
    `;
}

// In fetch handler:
.catch(error => {
    console.error('Fetch failed:', error);
    showError('Unable to load observations. Please try again later.');
});
```

**Epic:** E-UX-004: Error State UI

---

### UX-CRIT-005: Admin Settings UI Missing 🟡

**Problem:**
Admin page shows placeholder text only (`admin.php:13`)
- No form for API token
- No project slug input
- No cache settings

**Files:**
- `wp-content/plugins/inat-observations-wp/includes/admin.php:9-15`

**User Impact:**
Cannot configure plugin without editing `.env` files (technical users only).

**Design:**
```
┌─────────────────────────────────────────────────┐
│  iNaturalist Observations Settings              │
├─────────────────────────────────────────────────┤
│  iNaturalist Project Slug:                      │
│  [________________________] (e.g. city-nature)  │
│                                                  │
│  API Token (optional):                          │
│  [________________________] Get token from...   │
│                                                  │
│  Cache Lifetime (seconds):                      │
│  [3600____] (1 hour)                            │
│                                                  │
│  Manual Sync:                                   │
│  [Sync Now] Last sync: 2 hours ago             │
│                                                  │
│  [Save Changes]                                 │
└─────────────────────────────────────────────────┘
```

**Implementation:**
```php
function inat_obs_admin_page() {
    if (!current_user_can('manage_options')) return;

    // Save settings
    if (isset($_POST['inat_obs_save'])) {
        check_admin_referer('inat_obs_settings');
        update_option('inat_obs_project_slug', sanitize_text_field($_POST['project_slug']));
        update_option('inat_obs_api_token', sanitize_text_field($_POST['api_token']));
        update_option('inat_obs_cache_lifetime', absint($_POST['cache_lifetime']));
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    $project_slug = get_option('inat_obs_project_slug', '');
    $api_token = get_option('inat_obs_api_token', '');
    $cache_lifetime = get_option('inat_obs_cache_lifetime', 3600);

    ?>
    <div class="wrap">
        <h1>iNaturalist Observations Settings</h1>
        <form method="post">
            <?php wp_nonce_field('inat_obs_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="project_slug">Project Slug</label></th>
                    <td>
                        <input type="text" id="project_slug" name="project_slug"
                               value="<?php echo esc_attr($project_slug); ?>"
                               class="regular-text" required>
                        <p class="description">Your iNaturalist project identifier</p>
                    </td>
                </tr>
                <!-- ... more fields ... -->
            </table>
            <?php submit_button('Save Changes', 'primary', 'inat_obs_save'); ?>
        </form>
    </div>
    <?php
}
```

**Epic:** E-UX-005: Admin Settings UI

---

## HIGH Priority UX Improvements

### UX-HIGH-001: Mobile Responsiveness

**Problem:**
- Fixed-width layout
- No viewport meta tag
- No mobile-specific styles
- Breaks on screens < 600px

**Testing Needed:**
- iPhone SE (375px)
- iPad (768px)
- Desktop (1920px)

**Solution:**
```css
/* Mobile-first approach */
.inat-list {
    grid-template-columns: 1fr; /* Single column on mobile */
}

@media (min-width: 640px) {
    .inat-list {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .inat-list {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1440px) {
    .inat-list {
        grid-template-columns: repeat(4, 1fr);
    }
}
```

**Epic:** E-UX-006: Responsive Design

---

### UX-HIGH-002: Accessibility (WCAG AA)

**Current Issues:**
- No ARIA labels
- No keyboard navigation
- No focus indicators
- No screen reader support
- No alt text on images
- Poor color contrast

**WCAG Failures:**
- ❌ 1.1.1 Non-text Content (no alt text)
- ❌ 1.4.3 Contrast (poor contrast)
- ❌ 2.1.1 Keyboard (no keyboard nav)
- ❌ 2.4.7 Focus Visible (no focus indicators)
- ❌ 4.1.2 Name, Role, Value (no ARIA)

**Fixes Needed:**
```html
<!-- Add ARIA labels -->
<div role="main" aria-label="iNaturalist observations">
    <form role="search" aria-label="Filter observations">
        <label for="inat-filter-field">Filter by species:</label>
        <select id="inat-filter-field" aria-label="Species filter">
            <option value="">All species</option>
        </select>
    </form>

    <div id="inat-list"
         role="list"
         aria-live="polite"
         aria-atomic="true">
        <!-- Cards with role="listitem" -->
    </div>
</div>

<!-- Focus indicators -->
<style>
a:focus, button:focus, select:focus {
    outline: 3px solid #3498db;
    outline-offset: 2px;
}
</style>

<!-- Alt text for images -->
<img src="${photo}"
     alt="${species} observed at ${location} on ${date}"
     loading="lazy">
```

**Epic:** E-UX-007: Accessibility Compliance (WCAG AA)

---

### UX-HIGH-003: Pagination / Infinite Scroll

**Problem:**
- Loads all observations at once
- Slow on large datasets (1000+ items)
- No pagination controls

**Design Options:**

**Option A: Classic Pagination**
```
[← Previous]  1 2 [3] 4 5  [Next →]
```

**Option B: Infinite Scroll**
```
[Observations 1-50]
... scroll ...
[Loading more...]  ← Auto-load on scroll
[Observations 51-100]
```

**Recommendation:** Infinite scroll for better UX, with "Load More" button fallback.

**Implementation:**
```javascript
let page = 1;
const perPage = 50;

function loadMore() {
    fetch(`${ajaxurl}?action=inat_obs_fetch&page=${page}&per_page=${perPage}`)
        .then(r => r.json())
        .then(data => {
            appendObservations(data.results);
            page++;
        });
}

// Infinite scroll trigger
const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) {
        loadMore();
    }
}, { threshold: 0.5 });

observer.observe(document.getElementById('inat-list-end'));
```

**Epic:** E-UX-008: Pagination / Infinite Scroll

---

### UX-HIGH-004: Image Lazy Loading & Lightbox

**Enhancements:**
1. Native lazy loading (`loading="lazy"`)
2. Lightbox for full-size images
3. Thumbnail optimization

**Implementation:**
```javascript
// Lightbox
function openLightbox(imageUrl) {
    const lightbox = document.createElement('div');
    lightbox.className = 'inat-lightbox';
    lightbox.innerHTML = `
        <div class="lightbox-backdrop" onclick="closeLightbox()"></div>
        <img src="${imageUrl}" alt="Full size observation">
        <button class="lightbox-close" onclick="closeLightbox()">✕</button>
    `;
    document.body.appendChild(lightbox);
}
```

**Epic:** E-UX-009: Image Enhancements

---

## MEDIUM Priority UX Enhancements

### UX-MED-001: Search / Autocomplete

**Feature:**
- Search box for species names
- Autocomplete suggestions
- Highlight matching text

**Epic:** E-UX-010: Search & Autocomplete

---

### UX-MED-002: Sort Controls

**Options:**
- Most recent first (default)
- Oldest first
- A-Z species name
- Most popular (by faves)

**Epic:** E-UX-011: Sort Controls

---

### UX-MED-003: Map View

**Feature:**
- Toggle between list and map view
- Cluster markers for dense areas
- Popup on marker click

**Tech:** Leaflet.js (open source maps)

**Epic:** E-UX-012: Map View

---

### UX-MED-004: Internationalization (i18n)

**Problem:**
- All text hardcoded in English
- No text domain defined
- Not translatable

**Solution:**
```php
// Define text domain in main file
load_plugin_textdomain('inat-observations', false, dirname(plugin_basename(__FILE__)) . '/languages');

// Use translation functions
echo '<h1>' . __('iNaturalist Observations', 'inat-observations') . '</h1>';
echo '<p>' . sprintf(__('Loaded %d observations.', 'inat-observations'), $count) . '</p>';
```

**Epic:** E-UX-013: Internationalization

---

### UX-MED-005: Dark Mode

**Feature:**
- Auto-detect system preference
- Manual toggle
- High contrast mode

```css
@media (prefers-color-scheme: dark) {
    .inat-card {
        background: #1e1e1e;
        color: #e0e0e0;
        border-color: #444;
    }
}
```

**Epic:** E-UX-014: Dark Mode Support

---

## LOW Priority / Nice-to-Have

### UX-LOW-001: Share Buttons

Social sharing for individual observations.

**Epic:** E-UX-015: Social Sharing

---

### UX-LOW-002: Favorite / Bookmark

Let users save favorite observations locally (localStorage).

**Epic:** E-UX-016: Favorites Feature

---

### UX-LOW-003: Print Stylesheet

Printer-friendly layout for observations.

**Epic:** E-UX-017: Print Styles

---

## User Flows

### Flow 1: First-Time Setup
```
Admin logs in
  → WP Admin > Settings > iNat Observations
  → Enters project slug
  → (Optional) Enters API token
  → Clicks "Save Changes"
  → Success message shown
  → Clicks "Sync Now"
  → Observations fetched in background
  → Success notification: "Synced 142 observations"
```

### Flow 2: Viewing Observations (Site Visitor)
```
User visits page with [inat_observations] shortcode
  → Sees loading skeleton
  → Observations load (0.5-2s)
  → Grid of observation cards displayed
  → User scrolls down
  → More observations auto-load (infinite scroll)
  → User clicks species filter dropdown
  → List filters to show only selected species
  → User clicks "View on iNaturalist" link
  → Opens iNaturalist in new tab
```

### Flow 3: Error Recovery
```
User visits page
  → Loading spinner shows
  → API request fails (network error)
  → Error message displayed with retry button
  → User clicks "Retry"
  → Request succeeds
  → Observations displayed
```

---

## Epic Summary

| Epic ID | Title | Priority | Effort | Impact |
|---------|-------|----------|--------|--------|
| E-UX-001 | Observation List Rendering | CRITICAL | 8h | Core feature |
| E-UX-002 | Dynamic Filter Implementation | CRITICAL | 6h | Core feature |
| E-UX-003 | Loading State & Skeletons | CRITICAL | 3h | Perceived performance |
| E-UX-004 | Error State UI | HIGH | 2h | User feedback |
| E-UX-005 | Admin Settings UI | HIGH | 8h | Usability |
| E-UX-006 | Responsive Design | HIGH | 6h | Mobile users |
| E-UX-007 | Accessibility (WCAG AA) | HIGH | 12h | Legal/ethical |
| E-UX-008 | Pagination / Infinite Scroll | HIGH | 6h | Performance |
| E-UX-009 | Image Enhancements | MEDIUM | 4h | Visual appeal |
| E-UX-010 | Search & Autocomplete | MEDIUM | 8h | Findability |
| E-UX-011 | Sort Controls | MEDIUM | 3h | Flexibility |
| E-UX-012 | Map View | MEDIUM | 12h | Visualization |
| E-UX-013 | Internationalization | MEDIUM | 6h | Global reach |
| E-UX-014 | Dark Mode Support | MEDIUM | 4h | Accessibility |
| E-UX-015 | Social Sharing | LOW | 2h | Engagement |
| E-UX-016 | Favorites Feature | LOW | 4h | Engagement |
| E-UX-017 | Print Styles | LOW | 2h | Utility |

**Total Estimated Effort:** ~96 hours

---

**Next Actions:**
1. E-UX-001 (List Rendering) - Enables visible output
2. E-UX-003 (Loading States) - Better perceived performance
3. E-UX-004 (Error States) - User feedback
4. E-UX-002 (Filters) - Core filtering feature
5. E-UX-007 (Accessibility) - Legal requirement

**Reviewed by:** UX/UI Designer Agent
**Date:** 2026-01-02
