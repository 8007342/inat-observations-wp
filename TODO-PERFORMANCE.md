# TODO-PERFORMANCE.md - Performance Optimization Strategy

**Reviewed by:** Performance Optimizer
**Date:** 2026-01-02
**Plugin Version:** 0.1.0
**Performance Grade:** C- (Poor architecture, no optimization)

---

## Executive Summary

The inat-observations-wp plugin has **significant performance bottlenecks** that will prevent it from scaling to production workloads. The most critical issue is that the database is never queried for reads - all data comes from API/transient cache, causing unnecessary API calls and slow page loads.

**Critical Performance Issues:**
1. Database never queried (API called on every page load after cache expires)
2. No pagination (loads all data at once)
3. No database indexes on filterable fields
4. Transient cache bloat over time
5. No lazy loading for images
6. Blocking JavaScript fetch on page load
7. No query optimization

**Load Time Estimate (1000 observations):**
- **Current:** 5-10 seconds (initial load)
- **After optimization:** <500ms (sub-second)

---

## CRITICAL Performance Bottlenecks

### PERF-CRIT-001: Database Never Queried for Reads 🔴

**Problem:**
- All frontend/REST requests hit API directly (`shortcode.php:46`, `rest.php:20`)
- Database writes occur but data is never read
- Transient cache expires after 1 hour → fresh API call required
- No benefit from local storage

**Impact:**
- Slow page loads after cache expiration (2-5 second API calls)
- Unnecessary API requests (rate limit risk)
- Cannot filter data locally (no SQL WHERE clauses)

**Current Flow:**
```
User Request → Check Transient → Miss → API Call (slow!) → Display
```

**Optimized Flow:**
```
User Request → Query Database (fast!) → Display
Background Cron → API Call → Update Database
```

**Solution:**
```php
// REST endpoint - query DB instead of API
function inat_obs_rest_get_observations($request) {
    global $wpdb;

    $per_page = max(1, min(100, absint($request->get_param('per_page') ?? 50)));
    $page = max(1, absint($request->get_param('page') ?? 1));
    $offset = ($page - 1) * $per_page;

    $cache_key = "inat_obs_query_{$per_page}_{$page}";

    // Try object cache first (if available)
    $results = wp_cache_get($cache_key, 'inat_observations');

    if (false === $results) {
        // Query database (fast!)
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}inat_observations
             ORDER BY observed_on DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);

        // Cache query result
        wp_cache_set($cache_key, $results, 'inat_observations', 300); // 5 min
    }

    return rest_ensure_response($results);
}
```

**Performance Gain:**
- API call: 2-5 seconds
- Database query: 10-50ms (200x faster!)

**Epic:** E-PERF-001: Database-First Read Architecture

**Effort:** 4 hours

---

### PERF-CRIT-002: No Database Indexes 🟡

**Problem:**
- Primary key on `id` only
- Index on `observed_on` only
- No indexes on commonly filtered fields

**Files:**
- `wp-content/plugins/inat-observations-wp/includes/db-schema.php:10-21`

**Impact:**
- Full table scans on filter queries
- Slow as dataset grows (1000+ rows)

**Current Schema:**
```sql
CREATE TABLE wp_inat_observations (
    id bigint(20) unsigned NOT NULL,
    uuid varchar(100) DEFAULT '' NOT NULL,
    observed_on datetime DEFAULT NULL,
    species_guess varchar(255) DEFAULT '' NOT NULL,
    place_guess varchar(255) DEFAULT '' NOT NULL,
    metadata json DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY observed_on (observed_on)  -- Only 2 indexes!
)
```

**Optimized Schema:**
```sql
CREATE TABLE wp_inat_observations (
    id bigint(20) unsigned NOT NULL,
    uuid varchar(100) DEFAULT '' NOT NULL,
    observed_on datetime DEFAULT NULL,
    species_guess varchar(255) DEFAULT '' NOT NULL,
    place_guess varchar(255) DEFAULT '' NOT NULL,
    metadata json DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY observed_on (observed_on),
    KEY species_guess (species_guess),          -- NEW
    KEY place_guess (place_guess),              -- NEW
    KEY uuid (uuid),                            -- NEW
    KEY observed_species (observed_on, species_guess)  -- NEW: Composite index
)
```

**Usage Example:**
```sql
-- Before: Full table scan
SELECT * FROM wp_inat_observations WHERE species_guess = 'Quercus rubra';

-- After: Uses species_guess index (1000x faster on large tables)
```

**Performance Gain:**
- Unindexed query: O(n) - 500ms for 10,000 rows
- Indexed query: O(log n) - <5ms for 10,000 rows

**Epic:** E-PERF-002: Add Database Indexes

**Effort:** 2 hours

---

### PERF-CRIT-003: No Pagination (Loads All Data) 🟡

**Problem:**
- Frontend loads all observations at once
- No limit on query size
- Large datasets (1000+ obs) cause slow page loads

**Current:**
```javascript
// Fetches ALL observations
fetch(ajaxUrl + '?action=inat_obs_fetch')
    .then(r => r.json())
    .then(data => {
        // Renders all 1000+ observations to DOM
        renderObservations(data.results);
    });
```

**Impact:**
- Slow initial page load (5-10s for 1000 items)
- High memory usage in browser
- Poor mobile experience

**Solution - Infinite Scroll:**
```javascript
let page = 1;
const perPage = 50;
let loading = false;

function loadMore() {
    if (loading) return;
    loading = true;

    fetch(`${ajaxUrl}?action=inat_obs_fetch&page=${page}&per_page=${perPage}`)
        .then(r => r.json())
        .then(data => {
            appendObservations(data.results);
            page++;
            loading = false;
        });
}

// Initial load
loadMore();

// Infinite scroll trigger
window.addEventListener('scroll', () => {
    if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
        loadMore();
    }
});
```

**Backend Support:**
```php
function inat_obs_ajax_fetch() {
    check_ajax_referer('inat_obs_fetch', 'nonce');

    $page = max(1, absint($_GET['page'] ?? 1));
    $per_page = max(1, min(100, absint($_GET['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;

    global $wpdb;
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}inat_observations
         ORDER BY observed_on DESC
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ), ARRAY_A);

    wp_send_json_success(['results' => $results]);
}
```

**Performance Gain:**
- Initial load: 1000 items (5s) → 50 items (300ms) = **17x faster**

**Epic:** E-PERF-003: Implement Pagination/Infinite Scroll

**Effort:** 6 hours

---

### PERF-CRIT-004: Transient Cache Bloat 🟡

**Problem:**
- Each unique API URL creates new transient
- Different `per_page` values = different caches
- No cleanup of expired transients
- Database `wp_options` table grows unbounded

**Example:**
```php
// Each URL gets own cache entry
inat_obs_cache_md5(url?per_page=50)
inat_obs_cache_md5(url?per_page=100)
inat_obs_cache_md5(url?per_page=25)
// = 3 separate cache entries for same project!
```

**Impact:**
- Database bloat (100s of transients)
- Slower option queries
- Memory usage

**Solution:**
```php
// Normalize cache key - ignore per_page
function inat_obs_get_cache_key() {
    $project_slug = inat_obs_get_option('project_slug');
    return 'inat_obs_project_' . md5($project_slug);
}

// Single cache entry per project
function inat_obs_fetch_observations($url = null, $per_page = 100) {
    $cache_key = inat_obs_get_cache_key();
    $cached = get_transient($cache_key);

    if (false !== $cached) {
        return $cached;
    }

    // Fetch from API
    $data = /* API call */;

    set_transient($cache_key, $data, HOUR_IN_SECONDS);
    return $data;
}

// Cleanup old transients on cron
function inat_obs_cleanup_transients() {
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->prefix}options
         WHERE option_name LIKE '_transient_timeout_inat_obs_%'
         AND option_value < UNIX_TIMESTAMP()"
    );
}
add_action('inat_obs_daily_cleanup', 'inat_obs_cleanup_transients');
```

**Epic:** E-PERF-004: Optimize Transient Caching

**Effort:** 3 hours

---

## HIGH Priority Optimizations

### PERF-HIGH-001: Lazy Load Images

**Problem:**
- All observation images loaded on page load
- High-res images (>1MB each)
- Blocks page rendering

**Solution:**
```html
<!-- Native lazy loading -->
<img src="${photo.url}"
     alt="${species}"
     loading="lazy"
     decoding="async">
```

**Advanced - Intersection Observer:**
```javascript
const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.classList.remove('lazy');
            observer.unobserve(img);
        }
    });
});

document.querySelectorAll('img.lazy').forEach(img => imageObserver.observe(img));
```

**Performance Gain:**
- Initial page load: 10MB images → 500KB (20x less data)
- Time to interactive: 5s → 1s

**Epic:** E-PERF-005: Lazy Load Images

**Effort:** 3 hours

---

### PERF-HIGH-002: WordPress Object Cache

**Problem:**
- No persistent object caching
- Query results not cached
- Every request hits database

**Solution - Use WordPress Object Cache:**
```php
function inat_obs_get_observations_cached($limit = 50, $offset = 0) {
    $cache_key = "observations_{$limit}_{$offset}";
    $cache_group = 'inat_observations';

    // Try cache first
    $results = wp_cache_get($cache_key, $cache_group);

    if (false === $results) {
        global $wpdb;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}inat_observations
             ORDER BY observed_on DESC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);

        // Cache for 5 minutes
        wp_cache_set($cache_key, $results, $cache_group, 300);
    }

    return $results;
}

// Invalidate cache on data update
function inat_obs_store_items($items) {
    // ... store logic ...

    // Clear cache
    wp_cache_flush_group('inat_observations');
}
```

**With Redis/Memcached:**
```php
// In wp-config.php
define('WP_CACHE', true);

// Install object-cache.php drop-in
// e.g., Redis Object Cache plugin
```

**Performance Gain:**
- Cached query: <1ms (vs 20ms database query)

**Epic:** E-PERF-006: Object Cache Implementation

**Effort:** 4 hours

---

### PERF-HIGH-003: Optimize JSON Metadata Queries

**Problem:**
- Metadata stored as JSON blob
- Cannot filter efficiently
- Full table scan to find metadata values

**Solution - JSON Column Indexing (MySQL 5.7.8+):**
```sql
-- Create virtual columns from JSON
ALTER TABLE wp_inat_observations
ADD COLUMN field_tree_height VARCHAR(255) AS (JSON_UNQUOTE(JSON_EXTRACT(metadata, '$[0].value'))),
ADD INDEX idx_tree_height (field_tree_height);

-- Query with index
SELECT * FROM wp_inat_observations
WHERE field_tree_height > '10 meters';
```

**Alternative - Normalize to EAV Table:**
```sql
CREATE TABLE wp_inat_observation_fields (
    id bigint(20) unsigned AUTO_INCREMENT,
    observation_id bigint(20) unsigned NOT NULL,
    field_name varchar(255) NOT NULL,
    field_value text NOT NULL,
    PRIMARY KEY (id),
    KEY observation_id (observation_id),
    KEY field_name_value (field_name, field_value(191))
);

-- Query with index
SELECT o.* FROM wp_inat_observations o
JOIN wp_inat_observation_fields f ON o.id = f.observation_id
WHERE f.field_name = 'Tree Height' AND f.field_value = '15 meters';
```

**Performance Gain:**
- JSON scan: O(n) - 500ms for 10,000 rows
- Indexed query: O(log n) - <5ms

**Epic:** E-PERF-007: Optimize Metadata Queries

**Effort:** 8 hours

---

### PERF-HIGH-004: Asset Minification & Concatenation

**Problem:**
- JavaScript not minified (34 lines → could be smaller)
- CSS not minified
- Separate HTTP requests for each asset
- No CDN usage

**Solution:**
```bash
# Install build tools
npm install --save-dev terser clean-css-cli

# Minify JavaScript
npx terser assets/js/main.js -o assets/js/main.min.js -c -m

# Minify CSS
npx cleancss assets/css/main.css -o assets/css/main.min.css
```

**WordPress Integration:**
```php
function inat_obs_enqueue_assets() {
    $version = INAT_OBS_VERSION;

    // Use minified in production
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

    wp_enqueue_script(
        'inat-obs-main',
        INAT_OBS_URL . "assets/js/main{$suffix}.js",
        ['jquery'],
        $version,
        true
    );

    wp_enqueue_style(
        'inat-obs-main',
        INAT_OBS_URL . "assets/css/main{$suffix}.css",
        [],
        $version
    );
}
```

**Performance Gain:**
- File size: 2KB → 1KB (50% smaller)
- Load time: -100ms (fewer bytes transferred)

**Epic:** E-PERF-008: Asset Minification

**Effort:** 2 hours

---

## MEDIUM Priority Optimizations

### PERF-MED-001: Batch Database Operations

**Problem:**
- `inat_obs_store_items()` calls `$wpdb->replace()` in loop
- One query per observation
- 100 observations = 100 queries

**Current:**
```php
foreach ($items as $item) {
    $wpdb->replace($table, $data, $format);  // 100 queries!
}
```

**Optimized:**
```php
function inat_obs_store_items_batch($items) {
    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';

    // Build multi-row INSERT
    $values = [];
    foreach ($items as $item) {
        $values[] = $wpdb->prepare(
            "(%d, %s, %s, %s, %s, %s, %s, %s)",
            $item['id'],
            $item['uuid'],
            $item['observed_on'],
            // ... more fields
        );
    }

    // Single query for all rows
    $sql = "INSERT INTO $table (id, uuid, observed_on, ...)
            VALUES " . implode(',', $values) . "
            ON DUPLICATE KEY UPDATE
            updated_at = CURRENT_TIMESTAMP";

    $wpdb->query($sql);
}
```

**Performance Gain:**
- 100 queries: 500ms
- 1 batched query: 20ms (25x faster!)

**Epic:** E-PERF-009: Batch Database Inserts

**Effort:** 3 hours

---

### PERF-MED-002: Debounce Filter Changes

**Problem:**
- Filter change triggers immediate re-render
- Typing in search box causes many fetches

**Solution:**
```javascript
let debounceTimer;

function handleFilterChange(event) {
    clearTimeout(debounceTimer);

    debounceTimer = setTimeout(() => {
        fetchFiltered(event.target.value);
    }, 300); // Wait 300ms after last keystroke
}

document.getElementById('search').addEventListener('input', handleFilterChange);
```

**Performance Gain:**
- Typing "Quercus": 7 requests → 1 request

**Epic:** E-PERF-010: Debounce User Input

**Effort:** 1 hour

---

### PERF-MED-003: Browser Caching Headers

**Problem:**
- No cache headers on assets
- Browser refetches on every page load

**Solution:**
```php
function inat_obs_add_cache_headers() {
    if (strpos($_SERVER['REQUEST_URI'], 'wp-content/plugins/inat-observations-wp/assets/') !== false) {
        header('Cache-Control: public, max-age=31536000'); // 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    }
}
add_action('send_headers', 'inat_obs_add_cache_headers');
```

**Performance Gain:**
- Repeat visit: Loads from disk cache (0ms vs 100ms HTTP request)

**Epic:** E-PERF-011: Browser Cache Headers

**Effort:** 1 hour

---

### PERF-MED-004: Database Query Result Caching

**Problem:**
- Same queries executed on every page load
- "Get all species" query runs on every filter dropdown render

**Solution:**
```php
function inat_obs_get_unique_species() {
    $cache_key = 'inat_unique_species';

    $species = wp_cache_get($cache_key, 'inat_observations');

    if (false === $species) {
        global $wpdb;
        $species = $wpdb->get_col(
            "SELECT DISTINCT species_guess
             FROM {$wpdb->prefix}inat_observations
             WHERE species_guess != ''
             ORDER BY species_guess ASC"
        );

        wp_cache_set($cache_key, $species, 'inat_observations', 3600);
    }

    return $species;
}
```

**Epic:** E-PERF-012: Query Result Caching

**Effort:** 2 hours

---

## LOW Priority Optimizations

### PERF-LOW-001: Sprite Sheets for Icons

**Problem:**
- Individual icon files (multiple HTTP requests)

**Solution:**
- Combine icons into CSS sprite sheet
- Or use icon font (Font Awesome)
- Or use SVG sprites

**Epic:** E-PERF-013: Icon Sprite Sheet

**Effort:** 2 hours

---

### PERF-LOW-002: Service Worker for Offline Support

**Progressive Web App:**
```javascript
// sw.js
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});
```

**Epic:** E-PERF-014: Offline Support (PWA)

**Effort:** 6 hours

---

## Performance Monitoring

### PERF-MON-001: Add Performance Logging

**Track Query Performance:**
```php
function inat_obs_log_query_time($query_name, $callback) {
    if (!WP_DEBUG) {
        return $callback();
    }

    $start = microtime(true);
    $result = $callback();
    $duration = (microtime(true) - $start) * 1000; // ms

    if ($duration > 100) {
        error_log("[Performance] $query_name took {$duration}ms (slow!)");
    }

    return $result;
}

// Usage
$results = inat_obs_log_query_time('fetch_observations', function() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM ...");
});
```

**Epic:** E-PERF-015: Performance Logging

**Effort:** 2 hours

---

### PERF-MON-002: Query Monitor Integration

**Install Query Monitor plugin for dev:**
```php
if (defined('QM_COOKIE')) {
    do_action('qm/info', 'iNat Obs: Fetched ' . count($results) . ' items');
}
```

**Epic:** E-PERF-016: Query Monitor Integration

**Effort:** 1 hour

---

## Performance Testing

### PERF-TEST-001: Load Testing

**Apache Bench:**
```bash
# Test REST endpoint
ab -n 1000 -c 10 https://site.com/wp-json/inat/v1/observations

# Test shortcode page
ab -n 100 -c 5 https://site.com/observations-page/
```

**Load Test Targets:**
- 1000 observations: <500ms response time
- 10,000 observations: <1s response time
- 10 concurrent users: No degradation

**Epic:** E-PERF-017: Load Testing

**Effort:** 3 hours

---

### PERF-TEST-002: Lighthouse Audit

**Chrome Lighthouse Metrics:**
- Performance: Target 90+
- Accessibility: Target 100
- Best Practices: Target 100
- SEO: Target 100

**Key Metrics:**
- First Contentful Paint: <1s
- Time to Interactive: <2s
- Cumulative Layout Shift: <0.1

**Epic:** E-PERF-018: Lighthouse Optimization

**Effort:** 4 hours

---

## Epic Summary

| Epic ID | Title | Priority | Effort | Impact |
|---------|-------|----------|--------|--------|
| E-PERF-001 | Database-First Reads | CRITICAL | 4h | 200x faster queries |
| E-PERF-002 | Add Database Indexes | CRITICAL | 2h | 1000x faster filters |
| E-PERF-003 | Pagination/Infinite Scroll | CRITICAL | 6h | 17x faster initial load |
| E-PERF-004 | Optimize Transient Cache | HIGH | 3h | Reduce DB bloat |
| E-PERF-005 | Lazy Load Images | HIGH | 3h | 20x less data |
| E-PERF-006 | Object Cache | HIGH | 4h | Sub-ms queries |
| E-PERF-007 | Optimize Metadata Queries | HIGH | 8h | 100x faster filtering |
| E-PERF-008 | Asset Minification | MEDIUM | 2h | 50% smaller files |
| E-PERF-009 | Batch DB Operations | MEDIUM | 3h | 25x faster sync |
| E-PERF-010 | Debounce Input | MEDIUM | 1h | 80% fewer requests |
| E-PERF-011 | Cache Headers | MEDIUM | 1h | Instant repeat loads |
| E-PERF-012 | Query Result Caching | MEDIUM | 2h | Faster filters |
| E-PERF-013 | Icon Sprites | LOW | 2h | Fewer HTTP requests |
| E-PERF-014 | Offline Support (PWA) | LOW | 6h | Offline capability |
| E-PERF-015 | Performance Logging | MEDIUM | 2h | Monitoring |
| E-PERF-016 | Query Monitor | LOW | 1h | Dev tools |
| E-PERF-017 | Load Testing | MEDIUM | 3h | Benchmarking |
| E-PERF-018 | Lighthouse Optimization | MEDIUM | 4h | Web Vitals |

**Total Estimated Effort:** ~57 hours

---

**Next Actions (Critical Path):**
1. E-PERF-001 (Database reads) - 4 hours, MUST FIX
2. E-PERF-002 (Indexes) - 2 hours, easy win
3. E-PERF-003 (Pagination) - 6 hours, massive UX improvement
4. E-PERF-005 (Lazy loading) - 3 hours, mobile critical

**Performance Goals:**
- **v0.2.0:** <1s initial load, 70+ Lighthouse score
- **v1.0.0:** <500ms load, 90+ Lighthouse score, 10K observations supported

**Reviewed by:** Performance Optimizer Agent
**Date:** 2026-01-02
