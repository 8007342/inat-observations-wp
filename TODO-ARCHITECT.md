# TODO-ARCHITECT.md - Architecture Review & Technical Roadmap

**Reviewed by:** Solutions Architect
**Date:** 2026-01-02
**Plugin Version:** 0.1.0
**Review Focus:** System architecture, data flow, scalability, technical debt

---

## Executive Summary

The inat-observations-wp plugin has a solid modular foundation but suffers from **architectural inconsistencies** that prevent it from functioning as designed. The most critical issue is that the database is write-only - all reads bypass it and hit the API/cache directly. This defeats the purpose of local storage and makes filtering impossible.

**Completion Status:** ~30% complete
**Architectural Soundness:** 4/10 (Good structure, broken data flow)
**Technical Debt Level:** Medium-High

---

## Critical Architecture Issues

### 1. Database Is Write-Only (BLOCKER)

**Problem:**
- Database table exists and stores data (`db-schema.php:33-56`)
- But NO queries read from it anywhere in the codebase
- Frontend fetches from API/transient cache directly (`shortcode.php:46`, `rest.php:20`)
- Database serves no purpose except consuming disk space

**Impact:**
- Cannot filter by metadata fields
- Cannot implement pagination
- Cannot display historical data
- Database writes are wasted effort

**Files Affected:**
- `wp-content/plugins/inat-observations-wp/includes/shortcode.php:46`
- `wp-content/plugins/inat-observations-wp/includes/rest.php:20`

**Solution:**
```php
// In rest.php - query database instead of API
function inat_obs_rest_get_observations($request) {
    global $wpdb;
    $per_page = max(1, min(100, absint($request->get_param('per_page') ?? 50)));

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}inat_observations
         ORDER BY observed_on DESC
         LIMIT %d",
        $per_page
    ));

    return rest_ensure_response($results);
}
```

**Epic:** E-ARCH-001: Database Read Implementation

---

### 2. Cron Sync Job Not Implemented (BLOCKER)

**Problem:**
- Daily cron event registered (`init.php:21`)
- But callback `inat_obs_refresh_job()` is empty (`init.php:33-38`)
- No automated data sync occurs
- Transient cache expires but never refreshes from background

**Impact:**
- Data becomes stale after CACHE_LIFETIME
- Manual page visits required to trigger API fetch
- No "always fresh" data guarantee

**Files Affected:**
- `wp-content/plugins/inat-observations-wp/includes/init.php:33-38`

**Solution:**
```php
function inat_obs_refresh_job() {
    $data = inat_obs_fetch_all(); // Needs implementation
    if (!is_wp_error($data) && !empty($data['results'])) {
        inat_obs_store_items($data['results']);
        do_action('inat_obs_refresh_complete', count($data['results']));
    } else {
        error_log('iNat refresh job failed: ' . $data->get_error_message());
        do_action('inat_obs_refresh_failed', $data);
    }
}
```

**Epic:** E-ARCH-002: Background Sync Implementation

---

### 3. API Pagination Not Implemented (HIGH)

**Problem:**
- `inat_obs_fetch_all()` function exists but is empty (`api.php:63-65`)
- Only fetches first page (max 100 items)
- Large projects (10,000+ observations) will be incomplete

**Impact:**
- Incomplete data for large projects
- Users see only fraction of observations
- No way to fetch historical data

**Files Affected:**
- `wp-content/plugins/inat-observations-wp/includes/api.php:63-65`

**Solution:**
```php
function inat_obs_fetch_all() {
    $all_results = [];
    $page = 1;
    $per_page = 100;

    do {
        $data = inat_obs_fetch_observations(null, $per_page, $page);
        if (is_wp_error($data)) break;

        $results = $data['results'] ?? [];
        $all_results = array_merge($all_results, $results);

        $page++;
        $total_pages = ceil(($data['total_results'] ?? 0) / $per_page);

        // Rate limiting: 1 request per second
        if ($page <= $total_pages) sleep(1);

    } while ($page <= $total_pages && count($results) > 0);

    return ['results' => $all_results, 'total_results' => count($all_results)];
}
```

**Epic:** E-ARCH-003: Pagination & Bulk Fetch

---

### 4. Metadata Not Normalized (HIGH)

**Problem:**
- `observation_field_values` stored as raw JSON blob (`db-schema.php:49`)
- Cannot query/filter by metadata fields
- Cannot build dynamic filter dropdowns

**Impact:**
- No filtering by custom observation fields
- Poor query performance on metadata searches
- Frontend filter UI cannot be populated

**Files Affected:**
- `wp-content/plugins/inat-observations-wp/includes/db-schema.php:49`

**Solution - Option A (EAV Model):**
Create separate table for observation fields:
```sql
CREATE TABLE wp_inat_observation_fields (
    id bigint(20) unsigned AUTO_INCREMENT,
    observation_id bigint(20) unsigned NOT NULL,
    field_id int unsigned NOT NULL,
    field_name varchar(255) NOT NULL,
    field_value text NOT NULL,
    PRIMARY KEY (id),
    KEY observation_id (observation_id),
    KEY field_name (field_name),
    KEY field_value (field_value(191))
)
```

**Solution - Option B (JSON Column Indexing - MySQL 5.7.8+):**
```sql
ALTER TABLE wp_inat_observations
ADD COLUMN field_1 varchar(255) GENERATED ALWAYS AS (metadata->>'$.field_1'),
ADD INDEX idx_field_1 (field_1);
```

**Recommendation:** Option A (EAV) for maximum compatibility and flexibility.

**Epic:** E-ARCH-004: Metadata Normalization

---

## Architecture Improvements

### 5. Caching Strategy Needs Refactor (MEDIUM)

**Current State:**
- Transient cache by URL hash (`api.php:29-32`)
- Different parameters create different cache entries
- No cache invalidation strategy
- No cleanup of stale transients

**Issues:**
- Cache bloat over time
- Inconsistent data between cache entries
- No way to force refresh

**Recommended Architecture:**
```
┌─────────────────────────────────────────────────────────┐
│                    Data Flow                             │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  [WP-Cron Daily]                                         │
│       ↓                                                  │
│  inat_obs_fetch_all() - Paginated API fetch             │
│       ↓                                                  │
│  inat_obs_store_items() - Batch upsert to DB            │
│       ↓                                                  │
│  [Database: wp_inat_observations + fields]               │
│       ↓                                                  │
│  [Object Cache Layer - optional]                         │
│       ↓                                                  │
│  Frontend/REST - Query DB with filters                   │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Changes:**
1. Remove URL-based transient caching
2. Cache entire dataset refresh timestamp
3. Use WordPress object cache for query results
4. Implement cache invalidation on cron sync

**Epic:** E-ARCH-005: Caching Strategy Refactor

---

### 6. Error Handling Architecture (MEDIUM)

**Current State:**
- Basic WP_Error returns (`api.php:45-46`)
- No error logging
- No admin notifications
- No retry logic

**Recommended:**
1. **Centralized Error Logger:**
```php
function inat_obs_log_error($context, $message, $data = []) {
    error_log(sprintf('[iNat Obs] %s: %s - %s',
        $context, $message, json_encode($data)
    ));

    // Store critical errors for admin dashboard
    if (in_array($context, ['api_auth_fail', 'sync_failure'])) {
        set_transient('inat_obs_error_' . time(), compact('context', 'message', 'data'), DAY_IN_SECONDS);
    }
}
```

2. **Admin Error Dashboard Widget:**
```php
function inat_obs_admin_error_widget() {
    // Display recent errors from transients
}
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget('inat_obs_errors', 'iNat Observations Errors', 'inat_obs_admin_error_widget');
});
```

3. **Exponential Backoff for API Failures:**
```php
function inat_obs_fetch_with_retry($url, $max_retries = 3) {
    $attempt = 0;
    do {
        $response = wp_remote_get($url);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return $response;
        }
        $attempt++;
        sleep(pow(2, $attempt)); // 2s, 4s, 8s
    } while ($attempt < $max_retries);

    return new WP_Error('api_failed', 'Max retries exceeded');
}
```

**Epic:** E-ARCH-006: Error Handling & Logging

---

### 7. Database Schema Versioning (MEDIUM)

**Problem:**
- No version tracking for schema (`db-schema.php:10-21`)
- No migration system
- Changes will break existing installs

**Solution:**
```php
function inat_obs_get_db_version() {
    return get_option('inat_obs_db_version', '0.0.0');
}

function inat_obs_maybe_upgrade() {
    $current = inat_obs_get_db_version();

    if (version_compare($current, '0.2.0', '<')) {
        inat_obs_migration_0_2_0(); // Add observation_fields table
    }

    if (version_compare($current, '0.3.0', '<')) {
        inat_obs_migration_0_3_0(); // Add indexes
    }

    update_option('inat_obs_db_version', INAT_OBS_VERSION);
}
add_action('plugins_loaded', 'inat_obs_maybe_upgrade');
```

**Epic:** E-ARCH-007: Schema Versioning & Migrations

---

### 8. Configuration Management (MEDIUM)

**Problem:**
- Settings in environment variables (`getenv()`)
- No WordPress options API usage
- No admin UI for configuration
- Relies on `.env` files (anti-pattern for WP)

**Solution:**
Use WordPress Settings API properly:

```php
// Register settings
register_setting('inat_obs_settings', 'inat_obs_project_slug');
register_setting('inat_obs_settings', 'inat_obs_api_token');
register_setting('inat_obs_settings', 'inat_obs_cache_lifetime');

// Helper to get settings with fallbacks
function inat_obs_get_option($key, $default = '') {
    $option_map = [
        'project_slug' => 'inat_obs_project_slug',
        'api_token' => 'inat_obs_api_token',
        'cache_lifetime' => 'inat_obs_cache_lifetime',
    ];

    $option = get_option($option_map[$key], null);

    // Fallback to env vars for backward compatibility
    if ($option === null) {
        $option = getenv(strtoupper('INAT_' . $key)) ?: $default;
    }

    return $option;
}
```

**Epic:** E-ARCH-008: Settings API Migration

---

## Technical Debt

### 9. No OOP Architecture (LOW-MEDIUM)

**Current State:** All procedural code, global functions

**Pros of Current Approach:**
- Simple to understand
- WordPress-compatible
- Low cognitive overhead

**Cons:**
- Function name pollution (40+ global functions at scale)
- No dependency injection
- Hard to test in isolation
- No interface contracts

**Recommendation:** Hybrid approach
- Keep procedural for hooks/filters
- Introduce classes for data models and services

```php
namespace InatObs;

class ObservationRepository {
    private $wpdb;

    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
    }

    public function findAll($limit = 50, $offset = 0) { }
    public function findByMetadata($field, $value) { }
    public function store(array $observations) { }
}

class InatApiClient {
    private $base_url;
    private $token;

    public function fetchObservations($project_id, $per_page, $page) { }
    public function fetchAll($project_id) { }
}
```

**Epic:** E-ARCH-009: Refactor to Hybrid OOP

---

### 10. Asset Management (LOW)

**Issues:**
- Assets loaded on all admin pages (`shortcode.php:13-14`)
- No minification
- No asset versioning beyond plugin version
- No CDN support

**Solution:**
```php
function inat_obs_enqueue_assets() {
    // Only load on pages with shortcode
    if (!has_shortcode(get_post()->post_content, 'inat_observations')) {
        return;
    }

    $version = INAT_OBS_VERSION . '-' . filemtime(INAT_OBS_PATH . 'assets/js/main.js');

    wp_enqueue_script(
        'inat-obs-main',
        INAT_OBS_URL . 'assets/js/main.min.js', // Use minified
        ['jquery'],
        $version,
        true
    );
}
```

**Epic:** E-ARCH-010: Asset Optimization

---

## Scalability Considerations

### 11. Large Dataset Handling (MEDIUM)

**Scenarios to Plan For:**
- Projects with 50,000+ observations
- Metadata fields with 100+ unique values
- Concurrent users on high-traffic sites

**Recommendations:**
1. **Lazy Loading:** Don't load all observations at once
2. **Server-Side Filtering:** Filter in SQL, not JavaScript
3. **Pagination:** Implement proper pagination with offset/limit
4. **Indexes:** Add composite indexes on common filter combinations
5. **Background Processing:** Use Action Scheduler instead of WP-Cron for reliability

**Epic:** E-ARCH-011: Scalability Improvements

---

## Architecture Principles (Going Forward)

1. **Database as Source of Truth:** All reads from DB, API only for sync
2. **Background Processing:** Heavy work in cron, not on page load
3. **Graceful Degradation:** Show cached data if API fails
4. **Separation of Concerns:** API client ≠ Data repository ≠ Frontend presenter
5. **WordPress Standards:** Use Settings API, Options API, Object Cache
6. **Testability:** Write code that can be unit tested
7. **Extensibility:** Provide hooks/filters for customization

---

## Epic Summary

| Epic ID | Title | Priority | Effort | Impact |
|---------|-------|----------|--------|--------|
| E-ARCH-001 | Database Read Implementation | CRITICAL | 4h | Enables filtering |
| E-ARCH-002 | Background Sync Implementation | CRITICAL | 6h | Automated updates |
| E-ARCH-003 | Pagination & Bulk Fetch | HIGH | 8h | Complete dataset |
| E-ARCH-004 | Metadata Normalization | HIGH | 12h | Dynamic filtering |
| E-ARCH-005 | Caching Strategy Refactor | MEDIUM | 6h | Performance |
| E-ARCH-006 | Error Handling & Logging | MEDIUM | 4h | Debuggability |
| E-ARCH-007 | Schema Versioning & Migrations | MEDIUM | 4h | Upgrade safety |
| E-ARCH-008 | Settings API Migration | MEDIUM | 6h | WordPress compliance |
| E-ARCH-009 | Refactor to Hybrid OOP | LOW | 16h | Maintainability |
| E-ARCH-010 | Asset Optimization | LOW | 3h | Performance |
| E-ARCH-011 | Scalability Improvements | MEDIUM | 12h | Large datasets |

**Total Estimated Effort:** ~81 hours

---

**Next Steps:**
1. Fix E-ARCH-001 (database reads) - Quick win
2. Implement E-ARCH-002 (cron sync) - Unblocks testing
3. Build E-ARCH-003 (pagination) - Complete dataset
4. Design E-ARCH-004 (metadata) - Enables filtering

**Reviewed by:** Solutions Architect Agent
**Date:** 2026-01-02
