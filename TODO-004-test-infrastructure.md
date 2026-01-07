# TODO-004: Test Infrastructure & Coverage

**Priority:** 🔴 HIGH (WordPress just broke in production!)
**Status:** 🟡 In Progress
**Effort:** ~8-12 hours
**Dependencies:** None

---

## Overview

**The Problem:**
We've been adding features rapidly without automated tests, which led to a **production-breaking bug** (PHP fatal error in db-schema.php:197). WordPress wouldn't load at all.

**Root Cause:**
```php
// BROKEN: Null coalescing on nested array throws error if parent is null
$taxon_name = sanitize_text_field($r['taxon']['name'] ?? '');

// FIXED: Check if key exists first
$taxon_name = !empty($r['taxon']['name']) ? sanitize_text_field($r['taxon']['name']) : '';
```

**The Solution:**
Implement comprehensive test infrastructure to catch bugs BEFORE they break WordPress.

---

## Test Strategy

### 1. Unit Tests (PHPUnit)
Test individual functions in isolation.

**Critical Functions to Test:**
- `inat_obs_validate_image_url()` - XSS protection (SECURITY CRITICAL)
- `inat_obs_store_items()` - Data extraction and storage
- `inat_obs_get_species_autocomplete()` - Cache behavior
- `inat_obs_get_location_autocomplete()` - Cache behavior
- `inat_obs_invalidate_autocomplete_cache()` - Cache invalidation
- `buildImageSrcset()` (JavaScript) - URL parsing

### 2. Integration Tests (PHPUnit + WordPress Test Suite)
Test plugin behavior with real WordPress environment.

**Critical Workflows to Test:**
- Database schema creation (v1.0 → v2.0 → v2.1 migrations)
- Observation fetch and storage workflow
- AJAX endpoints (autocomplete, observations)
- Filter query building (SQL injection protection)
- Nonce verification (CSRF protection)

### 3. JavaScript Tests (Jest or QUnit)
Test frontend behavior.

**Critical Functions to Test:**
- `buildImageSrcset()` - URL parsing with edge cases
- `attachAutocomplete()` - Dropdown rendering
- `buildINatUrl()` - Mobile deep link detection
- Filter state management

### 4. Manual Testing Checklist
For things hard to automate.

**Visual/UX Tests:**
- [ ] Grid view renders correctly
- [ ] List view renders correctly
- [ ] Autocomplete dropdowns appear and filter
- [ ] Cards are clickable and open iNaturalist
- [ ] Mobile deep links open app (test on real device)
- [ ] Responsive images load correct size
- [ ] Photo attribution tooltips work

---

## Implementation Plan

### Phase 1: PHPUnit Setup (2-3 hours)

**File:** `tests/bootstrap.php` (NEW)

```php
<?php
// WordPress test environment bootstrap
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load WordPress test suite
require_once $_tests_dir . '/includes/functions.php';

// Manually load plugin
function _manually_load_plugin() {
    require dirname(dirname(__FILE__)) . '/inat-observations-wp.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start WordPress
require $_tests_dir . '/includes/bootstrap.php';
```

**File:** `phpunit.xml` (NEW)

```xml
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="inat-observations-unit">
            <directory>tests/unit</directory>
        </testsuite>
        <testsuite name="inat-observations-integration">
            <directory>tests/integration</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist>
            <directory>includes</directory>
            <directory>assets</directory>
        </whitelist>
    </filter>
</phpunit>
```

**File:** `composer.json` (NEW)

```json
{
  "require-dev": {
    "phpunit/phpunit": "^9.0",
    "yoast/phpunit-polyfills": "^1.0"
  },
  "autoload-dev": {
    "psr-4": {
      "INatObservations\\Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite inat-observations-unit",
    "test:integration": "phpunit --testsuite inat-observations-integration"
  }
}
```

**Installation:**
```bash
cd wp-content/plugins/inat-observations-wp
composer install
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
composer test
```

---

### Phase 2: Critical Unit Tests (3-4 hours)

**File:** `tests/unit/ImageValidationTest.php` (NEW)

```php
<?php
namespace INatObservations\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ImageValidationTest extends TestCase {
    public function test_validate_image_url_accepts_inaturalist() {
        $url = 'https://static.inaturalist.org/photos/12345/medium.jpg';
        $result = inat_obs_validate_image_url($url);
        $this->assertEquals($url, $result);
    }

    public function test_validate_image_url_accepts_s3() {
        $url = 'https://inaturalist-open-data.s3.amazonaws.com/photos/12345/medium.jpg';
        $result = inat_obs_validate_image_url($url);
        $this->assertEquals($url, $result);
    }

    public function test_validate_image_url_rejects_javascript() {
        $url = 'javascript:alert(1)';
        $result = inat_obs_validate_image_url($url);
        $this->assertFalse($result);
    }

    public function test_validate_image_url_rejects_data_uri() {
        $url = 'data:image/svg+xml,<svg onload=alert(1)>';
        $result = inat_obs_validate_image_url($url);
        $this->assertFalse($result);
    }

    public function test_validate_image_url_rejects_unauthorized_domain() {
        $url = 'https://evil.com/malicious.jpg';
        $result = inat_obs_validate_image_url($url);
        $this->assertFalse($result);
    }

    public function test_validate_image_url_accepts_empty() {
        $url = '';
        $result = inat_obs_validate_image_url($url);
        $this->assertEquals('', $result);
    }
}
```

**File:** `tests/unit/DataExtractionTest.php` (NEW)

```php
<?php
namespace INatObservations\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DataExtractionTest extends TestCase {
    /**
     * Test that missing taxon doesn't break store_items()
     * Regression test for the bug that broke WordPress
     */
    public function test_store_items_handles_missing_taxon() {
        global $wpdb;

        // Mock observation with NO taxon field
        $items = [
            'results' => [
                [
                    'id' => 12345,
                    'uuid' => 'test-uuid',
                    'observed_on' => '2024-01-01',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Location',
                    'observation_field_values' => [],
                    'photos' => []
                    // NOTE: 'taxon' field is MISSING - should not throw error!
                ]
            ]
        ];

        // Should not throw error
        $count = inat_obs_store_items($items);
        $this->assertEquals(1, $count);

        // Verify taxon_name is empty string
        $result = $wpdb->get_row("SELECT taxon_name FROM {$wpdb->prefix}inat_observations WHERE id = 12345", ARRAY_A);
        $this->assertEquals('', $result['taxon_name']);
    }

    public function test_store_items_extracts_taxon_name() {
        global $wpdb;

        $items = [
            'results' => [
                [
                    'id' => 67890,
                    'uuid' => 'test-uuid-2',
                    'observed_on' => '2024-01-02',
                    'species_guess' => 'Fly Agaric',
                    'place_guess' => 'Test Forest',
                    'taxon' => [
                        'name' => 'Amanita muscaria',  // Scientific name
                        'rank' => 'species'
                    ],
                    'observation_field_values' => [],
                    'photos' => []
                ]
            ]
        ];

        $count = inat_obs_store_items($items);
        $this->assertEquals(1, $count);

        $result = $wpdb->get_row("SELECT taxon_name FROM {$wpdb->prefix}inat_observations WHERE id = 67890", ARRAY_A);
        $this->assertEquals('Amanita muscaria', $result['taxon_name']);
    }

    public function test_store_items_sanitizes_taxon_name() {
        global $wpdb;

        $items = [
            'results' => [
                [
                    'id' => 11111,
                    'uuid' => 'test-uuid-3',
                    'observed_on' => '2024-01-03',
                    'species_guess' => 'Test',
                    'place_guess' => 'Test',
                    'taxon' => [
                        'name' => '<script>alert(1)</script>',  // XSS attempt
                    ],
                    'observation_field_values' => [],
                    'photos' => []
                ]
            ]
        ];

        $count = inat_obs_store_items($items);
        $this->assertEquals(1, $count);

        $result = $wpdb->get_row("SELECT taxon_name FROM {$wpdb->prefix}inat_observations WHERE id = 11111", ARRAY_A);
        $this->assertStringNotContainsString('<script>', $result['taxon_name']);
    }
}
```

---

### Phase 3: Integration Tests (3-4 hours)

**File:** `tests/integration/DatabaseMigrationTest.php` (NEW)

```php
<?php
namespace INatObservations\Tests\Integration;

use WP_UnitTestCase;

class DatabaseMigrationTest extends WP_UnitTestCase {
    public function test_fresh_install_creates_v2_1_schema() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Drop table to simulate fresh install
        $wpdb->query("DROP TABLE IF EXISTS $table");
        delete_option('inat_obs_db_version');

        // Run installation
        inat_obs_install_schema();

        // Verify version
        $version = get_option('inat_obs_db_version');
        $this->assertEquals('2.1', $version);

        // Verify taxon_name column exists
        $columns = $wpdb->get_col("DESCRIBE $table", 0);
        $this->assertContains('taxon_name', $columns);

        // Verify index exists
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
        $index_names = array_column($indexes, 'Key_name');
        $this->assertContains('taxon_name', $index_names);
    }

    public function test_migration_from_v2_0_to_v2_1() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Simulate v2.0 schema (without taxon_name)
        $wpdb->query("DROP TABLE IF EXISTS $table");
        $wpdb->query("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL,
            species_guess varchar(255) DEFAULT '' NOT NULL,
            photo_url varchar(500) DEFAULT '' NOT NULL,
            PRIMARY KEY (id)
        )");
        update_option('inat_obs_db_version', '2.0');

        // Run migration
        inat_obs_install_schema();

        // Verify taxon_name was added
        $columns = $wpdb->get_col("DESCRIBE $table", 0);
        $this->assertContains('taxon_name', $columns);

        $version = get_option('inat_obs_db_version');
        $this->assertEquals('2.1', $version);
    }

    public function test_migration_is_idempotent() {
        // Running migration twice should not break
        inat_obs_install_schema();
        inat_obs_install_schema();

        $version = get_option('inat_obs_db_version');
        $this->assertEquals('2.1', $version);
    }
}
```

**File:** `tests/integration/AutocompleteTest.php` (NEW)

```php
<?php
namespace INatObservations\Tests\Integration;

use WP_UnitTestCase;

class AutocompleteTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();

        // Insert test data
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $wpdb->query("TRUNCATE TABLE $table");

        $wpdb->insert($table, [
            'id' => 1,
            'species_guess' => 'Amanita muscaria',
            'taxon_name' => 'Amanita muscaria',
            'place_guess' => 'California',
            'observed_on' => '2024-01-01'
        ]);
        $wpdb->insert($table, [
            'id' => 2,
            'species_guess' => 'Amanita phalloides',
            'taxon_name' => 'Amanita phalloides',
            'place_guess' => 'Oregon',
            'observed_on' => '2024-01-02'
        ]);

        // Clear cache
        inat_obs_invalidate_autocomplete_cache();
    }

    public function test_species_autocomplete_returns_distinct() {
        $species = inat_obs_get_species_autocomplete();

        $this->assertIsArray($species);
        $this->assertCount(2, $species);
        $this->assertContains('Amanita muscaria', $species);
        $this->assertContains('Amanita phalloides', $species);
    }

    public function test_autocomplete_caches_results() {
        // First call - generates cache
        $start = microtime(true);
        $species1 = inat_obs_get_species_autocomplete();
        $time1 = microtime(true) - $start;

        // Second call - from cache (should be MUCH faster)
        $start = microtime(true);
        $species2 = inat_obs_get_species_autocomplete();
        $time2 = microtime(true) - $start;

        $this->assertEquals($species1, $species2);
        $this->assertLessThan($time1 / 10, $time2);  // Cache should be 10x faster
    }

    public function test_cache_invalidation_works() {
        // Populate cache
        inat_obs_get_species_autocomplete();

        // Verify cache exists
        $cached = get_transient('inat_obs_species_autocomplete_v1');
        $this->assertNotFalse($cached);

        // Invalidate
        inat_obs_invalidate_autocomplete_cache();

        // Verify cache cleared
        $cached = get_transient('inat_obs_species_autocomplete_v1');
        $this->assertFalse($cached);
    }
}
```

---

### Phase 4: JavaScript Tests (2-3 hours)

**File:** `tests/js/image-srcset.test.js` (NEW)

```javascript
// Jest test for buildImageSrcset()
describe('buildImageSrcset', () => {
  // Copy function from main.js
  function buildImageSrcset(photoUrl) {
    if (!photoUrl) return { src: '', srcset: '', sizes: '' };
    const base = photoUrl.substring(0, photoUrl.lastIndexOf('/') + 1);
    return {
      src: base + 'medium.jpg',
      srcset: [
        base + 'small.jpg 240w',
        base + 'medium.jpg 500w',
        base + 'large.jpg 1024w',
        base + 'original.jpg 2048w'
      ].join(', '),
      sizes: '(max-width: 480px) 240px, (max-width: 768px) 500px, (max-width: 1200px) 1024px, 2048px'
    };
  }

  test('handles empty URL', () => {
    const result = buildImageSrcset('');
    expect(result.src).toBe('');
    expect(result.srcset).toBe('');
  });

  test('extracts base URL correctly', () => {
    const url = 'https://static.inaturalist.org/photos/12345/medium.jpg';
    const result = buildImageSrcset(url);
    expect(result.src).toBe('https://static.inaturalist.org/photos/12345/medium.jpg');
    expect(result.srcset).toContain('https://static.inaturalist.org/photos/12345/small.jpg 240w');
  });

  test('handles square size input', () => {
    const url = 'https://static.inaturalist.org/photos/12345/square.jpg';
    const result = buildImageSrcset(url);
    expect(result.srcset).toContain('https://static.inaturalist.org/photos/12345/large.jpg 1024w');
  });

  test('regression: does not replace size in path', () => {
    // Bug we had: regex was replacing 'small' in 'photos/small123/medium.jpg'
    const url = 'https://static.inaturalist.org/photos/small123/medium.jpg';
    const result = buildImageSrcset(url);
    expect(result.srcset).toContain('https://static.inaturalist.org/photos/small123/large.jpg');
    expect(result.srcset).not.toContain('https://static.inaturalist.org/photos/123/');  // Should NOT remove 'small' from path
  });
});

describe('buildINatUrl', () => {
  function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }

  function buildINatUrl(obsId) {
    if (isMobile()) {
      return 'inaturalist://observations/' + obsId;
    }
    return 'https://www.inaturalist.org/observations/' + obsId;
  }

  test('returns web URL on desktop', () => {
    const originalUA = navigator.userAgent;
    Object.defineProperty(navigator, 'userAgent', {
      value: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
      configurable: true
    });

    const url = buildINatUrl('12345');
    expect(url).toBe('https://www.inaturalist.org/observations/12345');

    Object.defineProperty(navigator, 'userAgent', {
      value: originalUA,
      configurable: true
    });
  });

  test('returns deep link on mobile', () => {
    const originalUA = navigator.userAgent;
    Object.defineProperty(navigator, 'userAgent', {
      value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
      configurable: true
    });

    const url = buildINatUrl('12345');
    expect(url).toBe('inaturalist://observations/12345');

    Object.defineProperty(navigator, 'userAgent', {
      value: originalUA,
      configurable: true
    });
  });
});
```

---

## CI/CD Integration

**File:** `.github/workflows/test.yml` (NEW)

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Install WordPress Test Suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
      - name: Run tests
        run: composer test

  jest:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm ci
      - run: npm test
```

---

## Test Coverage Goals

| Component | Target Coverage | Priority |
|-----------|-----------------|----------|
| XSS Protection (`inat_obs_validate_image_url`) | 100% | 🔴 CRITICAL |
| Data Extraction (`inat_obs_store_items`) | 90% | 🔴 HIGH |
| Database Migrations | 100% | 🔴 HIGH |
| Autocomplete Caching | 80% | 🟡 MEDIUM |
| JavaScript (main.js) | 70% | 🟡 MEDIUM |

---

## Lessons Learned

**Why This Happened:**
1. ❌ No automated tests to catch regressions
2. ❌ No staging environment for manual testing
3. ❌ Rapid feature development without test-first approach
4. ❌ No code review process
5. ❌ File permissions not validated (Write tool creates 600, needs 644 for web server)

**How to Prevent:**
1. ✅ Write tests BEFORE adding features (TDD)
2. ✅ Run PHPUnit in CI/CD on every commit
3. ✅ Test database migrations separately
4. ✅ Manual QA checklist before merging
5. ✅ Validate file permissions (chmod 644 for PHP files, 755 for directories)
6. ✅ Docker health checks to catch plugin load failures

---

## Acceptance Criteria

- [ ] ✅ PHPUnit installed and configured
- [ ] ✅ All critical functions have unit tests
- [ ] ✅ Database migrations tested (v1.0 → v2.0 → v2.1)
- [ ] ✅ Regression test for taxon_name bug
- [ ] ✅ XSS protection tests (100% coverage)
- [ ] ✅ JavaScript tests with Jest
- [ ] ✅ CI/CD pipeline running tests automatically
- [ ] ✅ Test coverage report generated
- [ ] ✅ Manual testing checklist completed

---

## Files to Create

**New Files:**
- [ ] `composer.json` - PHPUnit dependencies
- [ ] `phpunit.xml` - PHPUnit configuration
- [ ] `tests/bootstrap.php` - WordPress test environment
- [ ] `tests/unit/ImageValidationTest.php` - XSS protection tests
- [ ] `tests/unit/DataExtractionTest.php` - Data parsing tests (REGRESSION TEST!)
- [ ] `tests/integration/DatabaseMigrationTest.php` - Schema migration tests
- [ ] `tests/integration/AutocompleteTest.php` - Cache behavior tests
- [ ] `tests/js/image-srcset.test.js` - JavaScript unit tests
- [ ] `package.json` - Jest dependencies
- [ ] `bin/install-wp-tests.sh` - WordPress test suite installer
- [ ] `.github/workflows/test.yml` - CI/CD pipeline

---

**Status:** 🟡 TODO documented, ready for implementation
**Next Action:** Install PHPUnit and write regression test for taxon_name bug
**ETA:** 8-12 hours for full test infrastructure

