# TODO-002: Phase 1 Implementation - Admin Settings & Data Pipeline

**Created**: 2026-01-06
**Priority**: HIGH
**Status**: Ready to Start
**Depends On**: WORDPRESS-PLUGIN.md (architecture), TODO-001 (DNA research)
**Phase**: 1 of 6

---

## Objective

**Implement Phase 1 of the WordPress plugin architecture: Admin settings page, database schema updates, WP-Cron refresh job, and DNA metadata detection.**

This establishes the **data pipeline foundation** that all other phases depend on.

---

## Current State Analysis

### ✅ What's Already Implemented

**Good Foundation**:
- ✅ Basic plugin structure (activation hooks, init.php)
- ✅ Database table created (`wp_inat_observations`)
- ✅ iNaturalist API integration (basic fetch)
- ✅ WP-Cron scheduling configured (daily refresh)
- ✅ AJAX + REST API endpoints
- ✅ Security headers (X-Content-Type-Options, X-Frame-Options, HTTPS enforcement)
- ✅ Input sanitization and nonce validation

**Files Analyzed**:
- `inat-observations-wp.php`: Main plugin file (22 lines)
- `includes/init.php`: Initialization, activation/deactivation hooks (61 lines)
- `includes/admin.php`: Admin page stub (16 lines)
- `includes/db-schema.php`: Database schema (61 lines)
- `includes/api.php`: iNaturalist API fetch (66 lines)
- `includes/shortcode.php`: Frontend shortcode (113 lines)
- `includes/rest.php`: REST endpoint (82 lines)
- `assets/js/main.js`: Minimal JS (35 lines)
- `assets/css/main.css`: Minimal CSS (4 lines)

### ❌ What's Missing for Phase 1

#### 1. Admin Settings Page (CRITICAL)
**Current**: Placeholder message "Settings UI not yet implemented"

**Required**:
- ✅ Settings page exists at `/wp-admin/options-general.php?page=inat-observations`
- ❌ No USER-ID input field
- ❌ No PROJECT-ID input field
- ❌ No validation that at least ONE is required
- ❌ No save/update functionality
- ❌ No settings retrieval from WordPress options

**File**: `includes/admin.php` (needs complete rewrite)

---

#### 2. Database Schema Updates (CRITICAL)
**Current**: Basic schema with 8 columns, missing DNA and image fields

**Required Additions**:
```sql
ALTER TABLE wp_inat_observations
    ADD COLUMN user_id BIGINT UNSIGNED,
    ADD COLUMN user_login VARCHAR(255),
    ADD COLUMN taxon_name VARCHAR(255),
    ADD COLUMN has_dna BOOLEAN DEFAULT FALSE,
    ADD COLUMN dna_type VARCHAR(50),
    ADD COLUMN image_url TEXT,
    ADD COLUMN thumbnail_url TEXT,
    ADD COLUMN quality_grade VARCHAR(20),
    ADD COLUMN positional_accuracy INT,
    ADD COLUMN num_identification_agreements INT DEFAULT 0,
    ADD COLUMN num_identification_disagreements INT DEFAULT 0,
    ADD INDEX idx_user_id (user_id),
    ADD INDEX idx_has_dna (has_dna),
    ADD INDEX idx_quality_grade (quality_grade);
```

**Migration Strategy**:
- Add schema version tracking in options
- Incremental migrations on plugin update
- Backwards-compatible changes only

**File**: `includes/db-schema.php` (needs ALTER TABLE migration)

---

#### 3. DNA Metadata Parsing (HIGH PRIORITY)
**Current**: `metadata` stored as JSON blob, not parsed

**Required**: Implement `inat_obs_parse_dna_metadata()` function

**Signature**:
```php
/**
 * Detect if observation contains DNA metadata.
 *
 * @param array $observation_field_values Raw JSON from iNaturalist
 * @return array ['has_dna' => bool, 'dna_type' => string|null]
 */
function inat_obs_parse_dna_metadata($observation_field_values) {
    // Known DNA field IDs (populate from TODO-001 research)
    $dna_field_ids = []; // TBD after research

    // Known DNA field name patterns
    $dna_patterns = ['/dna/i', '/barcode/i', '/genetic/i', '/sequence/i'];

    if (empty($observation_field_values)) {
        return ['has_dna' => false, 'dna_type' => null];
    }

    foreach ($observation_field_values as $ofv) {
        $field_id = $ofv['observation_field']['id'] ?? null;
        $field_name = $ofv['observation_field']['name'] ?? '';
        $value = $ofv['value'] ?? '';

        // Check by ID (most reliable)
        if (!empty($dna_field_ids) && in_array($field_id, $dna_field_ids) && !empty($value)) {
            return [
                'has_dna' => true,
                'dna_type' => sanitize_text_field($field_name),
            ];
        }

        // Check by name pattern (fallback)
        foreach ($dna_patterns as $pattern) {
            if (preg_match($pattern, $field_name) && !empty($value)) {
                return [
                    'has_dna' => true,
                    'dna_type' => sanitize_text_field($field_name),
                ];
            }
        }
    }

    return ['has_dna' => false, 'dna_type' => null];
}
```

**File**: `includes/api.php` (add new function)

---

#### 4. WP-Cron Refresh Job (CRITICAL)
**Current**: Scheduled but function is empty stub

**Required**: Implement `inat_obs_refresh_job()` to fetch and store observations

**Implementation**:
```php
function inat_obs_refresh_job() {
    // Get settings
    $user_id = get_option('inat_obs_user_id', '');
    $project_id = get_option('inat_obs_project_id', '');

    // Validate: at least one required
    if (empty($user_id) && empty($project_id)) {
        error_log('iNat Observations: Cannot refresh - no USER-ID or PROJECT-ID configured');
        return;
    }

    // Build query args
    $args = ['per_page' => 200, 'page' => 1];
    if (!empty($user_id)) {
        $args['user_id'] = $user_id;
    }
    if (!empty($project_id)) {
        $args['project_id'] = $project_id;
    }

    // Fetch observations
    $data = inat_obs_fetch_observations($args);
    if (is_wp_error($data)) {
        error_log('iNat Observations: API fetch failed - ' . $data->get_error_message());
        return;
    }

    // Store in database
    inat_obs_store_items($data);

    // Log success
    $count = count($data['results'] ?? []);
    update_option('inat_obs_last_refresh', current_time('mysql'));
    update_option('inat_obs_last_refresh_count', $count);
}
```

**File**: `includes/init.php` (update existing function)

---

#### 5. Enhanced Data Storage (HIGH PRIORITY)
**Current**: `inat_obs_store_items()` stores minimal fields

**Required**: Parse and store all required fields including DNA metadata

**Updates to `inat_obs_store_items()`**:
```php
function inat_obs_store_items($items) {
    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';
    if (empty($items['results'])) return;

    foreach ($items['results'] as $r) {
        // Parse DNA metadata
        $dna = inat_obs_parse_dna_metadata($r['observation_field_values'] ?? []);

        // Extract image URLs
        $image_url = '';
        $thumbnail_url = '';
        if (!empty($r['photos'][0]['url'])) {
            $image_url = esc_url_raw($r['photos'][0]['url']);
            // iNaturalist CDN provides different sizes
            $thumbnail_url = str_replace('/original/', '/medium/', $image_url);
        }

        // Extract taxon name
        $taxon_name = $r['taxon']['name'] ?? '';

        // Extract user info
        $user_id = $r['user']['id'] ?? null;
        $user_login = $r['user']['login'] ?? '';

        // Quality grade
        $quality_grade = $r['quality_grade'] ?? 'casual';

        // Metadata JSON (full observation_field_values)
        $meta = json_encode($r['observation_field_values'] ?? []);

        $wpdb->replace(
            $table,
            [
                'id' => intval($r['id']),
                'uuid' => sanitize_text_field($r['uuid'] ?? ''),
                'observed_on' => $r['observed_on'] ?? null,
                'species_guess' => sanitize_text_field($r['species_guess'] ?? ''),
                'place_guess' => sanitize_text_field($r['place_guess'] ?? ''),
                'taxon_name' => sanitize_text_field($taxon_name),
                'user_id' => $user_id,
                'user_login' => sanitize_text_field($user_login),
                'has_dna' => $dna['has_dna'],
                'dna_type' => $dna['dna_type'],
                'image_url' => $image_url,
                'thumbnail_url' => $thumbnail_url,
                'quality_grade' => sanitize_text_field($quality_grade),
                'metadata' => $meta,
                'created_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1),
            ],
            ['%d','%s','%s','%s','%s','%s','%d','%s','%d','%s','%s','%s','%s','%s','%s','%s']
        );
    }
}
```

**File**: `includes/db-schema.php` (update existing function)

---

## Implementation Tasks

### Task 1: Admin Settings Page ✅ (Day 1)

**File**: `includes/admin.php`

**Requirements**:
- Form with USER-ID input field (text)
- Form with PROJECT-ID input field (text)
- Validation: at least ONE required (client-side + server-side)
- Save settings to WordPress options
- Display last refresh timestamp
- Display last refresh observation count
- Manual refresh button (triggers `inat_obs_refresh_job()`)

**WordPress Options**:
- `inat_obs_user_id` - iNaturalist user ID (numeric string)
- `inat_obs_project_id` - iNaturalist project ID (numeric string)
- `inat_obs_last_refresh` - MySQL datetime of last successful refresh
- `inat_obs_last_refresh_count` - Number of observations fetched

**UI Mockup**:
```
iNaturalist Observations Settings
==================================

Configuration
-------------
At least one of the following is required:

User ID: [___________] (e.g., 123456)
         Fetch observations by a specific iNaturalist user

Project ID: [___________] (e.g., 789012)
            Fetch observations from a specific project

[Save Settings]

Status
------
Last Refresh: 2026-01-06 10:30:45 (150 observations)
Next Scheduled: 2026-01-07 10:30:00 (daily)

[Refresh Now]
```

**Validation**:
```php
if (empty($_POST['user_id']) && empty($_POST['project_id'])) {
    add_settings_error('inat_obs_settings', 'missing_id', 'At least one of User ID or Project ID is required');
}
```

---

### Task 2: Database Schema Migration ✅ (Day 1)

**File**: `includes/db-schema.php`

**Requirements**:
- Add schema version tracking in options (`inat_obs_db_version`)
- Check version on plugin activation
- Run migrations incrementally
- Add new columns with ALTER TABLE (safe for existing data)

**Migration Function**:
```php
function inat_obs_migrate_schema() {
    global $wpdb;
    $current_version = get_option('inat_obs_db_version', '1.0');
    $table = $wpdb->prefix . 'inat_observations';

    if (version_compare($current_version, '1.1', '<')) {
        // Migration 1.0 -> 1.1: Add DNA and image columns
        $wpdb->query("ALTER TABLE $table
            ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED,
            ADD COLUMN IF NOT EXISTS user_login VARCHAR(255),
            ADD COLUMN IF NOT EXISTS taxon_name VARCHAR(255),
            ADD COLUMN IF NOT EXISTS has_dna BOOLEAN DEFAULT FALSE,
            ADD COLUMN IF NOT EXISTS dna_type VARCHAR(50),
            ADD COLUMN IF NOT EXISTS image_url TEXT,
            ADD COLUMN IF NOT EXISTS thumbnail_url TEXT,
            ADD COLUMN IF NOT EXISTS quality_grade VARCHAR(20)");

        // Add indexes
        $wpdb->query("ALTER TABLE $table
            ADD INDEX IF NOT EXISTS idx_user_id (user_id),
            ADD INDEX IF NOT EXISTS idx_has_dna (has_dna),
            ADD INDEX IF NOT EXISTS idx_quality_grade (quality_grade)");

        update_option('inat_obs_db_version', '1.1');
    }
}
```

**Call in `inat_obs_activate()`**:
```php
function inat_obs_activate() {
    inat_obs_install_schema();
    inat_obs_migrate_schema(); // NEW
    // ... rest of activation
}
```

---

### Task 3: DNA Metadata Parsing ✅ (Day 2)

**File**: `includes/api.php`

**Requirements**:
- Implement `inat_obs_parse_dna_metadata()` function
- Use field ID matching (populated from TODO-001 research)
- Fallback to name pattern matching
- Return normalized structure

**Testing**:
- Unit tests with sample observation_field_values JSON
- Test edge cases (empty values, malformed data)
- Test all known DNA field patterns

**Integration**:
- Called by `inat_obs_store_items()` during data storage
- Used for filtering in REST/AJAX endpoints (Phase 3)

---

### Task 4: WP-Cron Job Implementation ✅ (Day 2-3)

**File**: `includes/init.php`

**Requirements**:
- Implement `inat_obs_refresh_job()` function
- Fetch observations based on settings
- Handle pagination (fetch all pages, not just first 200)
- Store in database via `inat_obs_store_items()`
- Log errors to WordPress debug log
- Update last refresh timestamp/count

**Error Handling**:
```php
// API rate limiting
if (is_wp_error($data) && $data->get_error_code() === 'http_request_failed') {
    // Retry with exponential backoff
}

// Invalid credentials
if (is_wp_error($data) && strpos($data->get_error_message(), '401') !== false) {
    // Disable cron until credentials fixed
}
```

**Pagination**:
```php
$page = 1;
$max_pages = 10; // Safety limit
do {
    $args['page'] = $page;
    $data = inat_obs_fetch_observations($args);
    if (is_wp_error($data)) break;

    inat_obs_store_items($data);

    $total_results = $data['total_results'] ?? 0;
    $per_page = $data['per_page'] ?? 100;
    $pages = ceil($total_results / $per_page);

    $page++;
} while ($page <= $pages && $page <= $max_pages);
```

---

### Task 5: Enhanced Data Storage ✅ (Day 3)

**File**: `includes/db-schema.php`

**Requirements**:
- Update `inat_obs_store_items()` to parse all new fields
- Call `inat_obs_parse_dna_metadata()` for DNA detection
- Extract image URLs from photos array
- Extract taxon name from taxon object
- Extract user info from user object
- Sanitize all inputs

**XSS Safety** (Image URLs):
- Use `esc_url_raw()` for database storage
- Use `esc_url()` when outputting in HTML
- Verify URLs are from iNaturalist CDN (`*.inaturalist.org`)

---

### Task 6: API Integration Updates ✅ (Day 3)

**File**: `includes/api.php`

**Requirements**:
- Update `inat_obs_fetch_observations()` to accept `user_id` or `project_id`
- Handle both parameters correctly
- Update URL building logic

**Current**:
```php
$params = http_build_query([
    'project_id' => $opts['project'],
    'per_page' => $opts['per_page'],
    'page' => $opts['page'],
]);
```

**Updated**:
```php
$params_array = [
    'per_page' => $opts['per_page'],
    'page' => $opts['page'],
    'order' => 'desc',
    'order_by' => 'created_at',
];

if (!empty($opts['user_id'])) {
    $params_array['user_id'] = $opts['user_id'];
}

if (!empty($opts['project_id'])) {
    $params_array['project_id'] = $opts['project_id'];
}

$params = http_build_query($params_array);
```

---

### Task 7: Manual Testing & Validation ✅ (Day 4)

**Test Cases**:

1. **Install & Activate**:
   - Activate plugin
   - Verify table created
   - Verify cron scheduled

2. **Settings Page**:
   - Navigate to Settings > iNat Observations
   - Leave both fields empty, try to save → Error
   - Enter USER-ID only → Success
   - Enter PROJECT-ID only → Success
   - Enter both → Success

3. **Data Refresh**:
   - Click "Refresh Now" button
   - Check database for observations
   - Verify DNA metadata parsed correctly
   - Verify images stored

4. **Cron Job**:
   - Trigger manually: `wp cron event run inat_obs_refresh`
   - Verify observations updated

5. **Error Handling**:
   - Enter invalid USER-ID → Log error
   - Enter invalid PROJECT-ID → Log error
   - Network failure → Graceful degradation

---

## Success Criteria

- [ ] Settings page allows configuring USER-ID and/or PROJECT-ID
- [ ] At least ONE of USER-ID or PROJECT-ID is enforced
- [ ] Database schema updated with DNA and image columns
- [ ] `inat_obs_parse_dna_metadata()` function implemented and tested
- [ ] WP-Cron job fetches observations based on settings
- [ ] Observations stored with DNA metadata, images, and all required fields
- [ ] Manual "Refresh Now" button works
- [ ] Last refresh timestamp displayed
- [ ] Error logging functional
- [ ] XSS-safe image URL handling

---

## Risks & Mitigations

### Risk 1: DNA Field IDs Unknown
**Probability**: High (TODO-001 research not yet done)
**Impact**: Medium (DNA filtering unreliable)
**Mitigation**:
- Implement pattern-based fallback
- Add admin UI to configure custom field IDs
- Log unrecognized DNA patterns for analysis

### Risk 2: Database Migration Fails
**Probability**: Low
**Impact**: High (data loss)
**Mitigation**:
- Test migrations on dev environment first
- Use `IF NOT EXISTS` for safe ALTER TABLE
- Add rollback capability
- Backup reminder in admin UI

### Risk 3: API Rate Limiting
**Probability**: Medium
**Impact**: Medium (incomplete data)
**Mitigation**:
- Implement exponential backoff
- Add configurable refresh interval
- Cache API responses
- Paginate requests

### Risk 4: Large Datasets (1000+ observations)
**Probability**: High
**Impact**: Medium (slow refresh)
**Mitigation**:
- Pagination with max page limit
- Background processing (WP-Cron)
- Progress indicator in admin UI
- Incremental updates (fetch only new observations)

---

## Next Steps After Completion

Once Phase 1 is complete, proceed to **Phase 2: Basic Frontend Display**:
- Create TODO-003-phase-2-frontend.md
- Implement basic grid view
- Add image thumbnails with lazy loading
- Display DNA badge on thumbnails
- Mobile-responsive layout

---

## Related Files

- **Architecture**: `WORDPRESS-PLUGIN.md`
- **DNA Research**: `TODO-001-filter-dna-observations.md`
- **Implementation**: `includes/admin.php`, `includes/db-schema.php`, `includes/api.php`, `includes/init.php`

---

**Status**: TO DO (ready to start)
**Next Action**: Task 1 - Admin Settings Page
**Owner**: Full-Stack Developer (PHP + WordPress)
