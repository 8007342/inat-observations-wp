# TODO-QA.md - Quality Assurance & Testing Strategy

**Reviewed by:** QA Engineer
**Date:** 2026-01-02
**Plugin Version:** 0.1.0
**Test Coverage:** 0% (No tests exist)
**QA Maturity:** 1/10 (Manual testing only)

---

## Executive Summary

The inat-observations-wp plugin has **ZERO automated tests** and no testing infrastructure. This is a **critical quality risk** that makes regression detection impossible and blocks confident releases.

**Testing Gaps:**
- No PHPUnit tests (0 unit tests)
- No integration tests
- No E2E tests
- No CI/CD pipeline tests
- No code coverage tracking
- No test environment setup

**Quality Risks:**
- Cannot verify bug fixes don't break other features
- No regression detection
- Manual testing is time-consuming and error-prone
- Refactoring is dangerous without tests

---

## Test Infrastructure Setup

### QA-INFRA-001: PHPUnit Test Suite Setup

**Current State:** No PHPUnit, no `composer.json`, no test directory

**Required Setup:**

**1. Create `composer.json`:**
```json
{
    "name": "yourname/inat-observations-wp",
    "description": "WordPress plugin for displaying iNaturalist observations",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "yoast/phpunit-polyfills": "^1.0",
        "wp-phpunit/wp-phpunit": "^6.1",
        "squizlabs/php_codesniffer": "^3.7",
        "wp-coding-standards/wpcs": "^3.0"
    },
    "scripts": {
        "test": "phpunit",
        "test:coverage": "phpunit --coverage-html coverage",
        "lint": "phpcs --standard=WordPress .",
        "lint:fix": "phpcbf --standard=WordPress ."
    }
}
```

**2. Install Dependencies:**
```bash
composer install
```

**3. Create Test Directory Structure:**
```
wp-content/plugins/inat-observations-wp/
├── tests/
│   ├── bootstrap.php           # Test bootstrap
│   ├── phpunit.xml             # PHPUnit configuration
│   ├── unit/                   # Unit tests
│   │   ├── test-api.php
│   │   ├── test-db-schema.php
│   │   ├── test-shortcode.php
│   │   └── test-rest.php
│   ├── integration/            # Integration tests
│   │   ├── test-cron-sync.php
│   │   └── test-full-workflow.php
│   └── fixtures/               # Test data
│       └── sample-api-response.json
```

**4. Create `tests/bootstrap.php`:**
```php
<?php
/**
 * PHPUnit bootstrap file
 */

// Load WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    exit(1);
}

// Load WordPress
require_once $_tests_dir . '/includes/functions.php';

// Load plugin
function _manually_load_plugin() {
    require dirname(__DIR__) . '/inat-observations-wp.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Start WordPress test suite
require $_tests_dir . '/includes/bootstrap.php';
```

**5. Create `tests/phpunit.xml`:**
```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="bootstrap.php"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix=".php">./unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix=".php">./integration</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">../includes</directory>
            <file>../inat-observations-wp.php</file>
        </whitelist>
    </filter>
    <php>
        <env name="WP_TESTS_DIR" value="/tmp/wordpress-tests-lib" />
    </php>
</phpunit>
```

**Epic:** E-QA-001: PHPUnit Infrastructure Setup

**Effort:** 4 hours

---

## Unit Tests

### QA-UNIT-001: API Client Tests

**File:** `tests/unit/test-api.php`

**Test Cases:**
```php
<?php

class Test_Inat_API extends WP_UnitTestCase {

    /**
     * Test successful API fetch with mocked response
     */
    public function test_fetch_observations_success() {
        // Mock wp_remote_get
        add_filter('pre_http_request', function($preempt, $args, $url) {
            if (strpos($url, 'api.inaturalist.org') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'total_results' => 2,
                        'results' => [
                            ['id' => 1, 'species_guess' => 'Test Species 1'],
                            ['id' => 2, 'species_guess' => 'Test Species 2'],
                        ],
                    ]),
                ];
            }
            return $preempt;
        }, 10, 3);

        $result = inat_obs_fetch_observations(null, 50);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);
        $this->assertEquals('Test Species 1', $result['results'][0]['species_guess']);
    }

    /**
     * Test API failure handling
     */
    public function test_fetch_observations_api_error() {
        add_filter('pre_http_request', function($preempt, $args, $url) {
            return new WP_Error('http_request_failed', 'Network error');
        }, 10, 3);

        $result = inat_obs_fetch_observations();

        $this->assertWPError($result);
        $this->assertEquals('api_fetch_failed', $result->get_error_code());
    }

    /**
     * Test 404 response handling
     */
    public function test_fetch_observations_404() {
        add_filter('pre_http_request', function($preempt, $args, $url) {
            return [
                'response' => ['code' => 404],
                'body' => 'Not Found',
            ];
        }, 10, 3);

        $result = inat_obs_fetch_observations();

        $this->assertWPError($result);
        $this->assertEquals('api_request_failed', $result->get_error_code());
    }

    /**
     * Test caching behavior
     */
    public function test_fetch_observations_uses_cache() {
        // First call - should hit API
        $result1 = inat_obs_fetch_observations();

        // Check transient is set
        $cache_key = 'inat_obs_cache_' . md5('...');
        $cached = get_transient($cache_key);
        $this->assertNotFalse($cached);

        // Second call - should use cache (no API call)
        $result2 = inat_obs_fetch_observations();
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test malformed JSON handling
     */
    public function test_fetch_observations_invalid_json() {
        add_filter('pre_http_request', function($preempt, $args, $url) {
            return [
                'response' => ['code' => 200],
                'body' => '{invalid json',
            ];
        }, 10, 3);

        $result = inat_obs_fetch_observations();

        // Should handle gracefully (return empty array or WP_Error)
        $this->assertTrue(is_wp_error($result) || (is_array($result) && empty($result['results'])));
    }
}
```

**Epic:** E-QA-002: API Client Unit Tests

**Effort:** 6 hours

---

### QA-UNIT-002: Database Schema Tests

**File:** `tests/unit/test-db-schema.php`

**Test Cases:**
```php
<?php

class Test_Inat_DB_Schema extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        // Ensure table exists
        inat_obs_create_table();
    }

    /**
     * Test table creation
     */
    public function test_table_creation() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertEquals($table, $table_exists);
    }

    /**
     * Test storing observations
     */
    public function test_store_items() {
        $items = [
            [
                'id' => 12345,
                'uuid' => 'test-uuid-1',
                'observed_on' => '2024-01-15',
                'species_guess' => 'Quercus rubra',
                'place_guess' => 'New York',
                'observation_field_values' => [
                    ['name' => 'Height', 'value' => '10m'],
                ],
            ],
        ];

        inat_obs_store_items($items);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            12345
        ));

        $this->assertNotNull($result);
        $this->assertEquals('Quercus rubra', $result->species_guess);
        $this->assertEquals('New York', $result->place_guess);
    }

    /**
     * Test SQL injection protection
     */
    public function test_sql_injection_protection() {
        $malicious = [
            'id' => 99999,
            'species_guess' => "'; DROP TABLE wp_inat_observations; --",
            'place_guess' => "1' OR '1'='1",
        ];

        inat_obs_store_items([$malicious]);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Table should still exist
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertEquals($table, $table_exists);

        // Malicious input should be sanitized
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 99999));
        $this->assertNotContains('DROP TABLE', $result->species_guess);
    }

    /**
     * Test upsert behavior (replace on duplicate key)
     */
    public function test_upsert_behavior() {
        $item_v1 = [
            'id' => 11111,
            'species_guess' => 'Original Name',
        ];

        inat_obs_store_items([$item_v1]);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $result1 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 11111));
        $this->assertEquals('Original Name', $result1->species_guess);

        // Update same ID with new data
        $item_v2 = [
            'id' => 11111,
            'species_guess' => 'Updated Name',
        ];

        inat_obs_store_items([$item_v2]);

        $result2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 11111));
        $this->assertEquals('Updated Name', $result2->species_guess);

        // Should be only one row
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %d", 11111));
        $this->assertEquals(1, $count);
    }
}
```

**Epic:** E-QA-003: Database Schema Unit Tests

**Effort:** 4 hours

---

### QA-UNIT-003: Shortcode Tests

**File:** `tests/unit/test-shortcode.php`

**Test Cases:**
```php
<?php

class Test_Inat_Shortcode extends WP_UnitTestCase {

    /**
     * Test shortcode registration
     */
    public function test_shortcode_registered() {
        $this->assertTrue(shortcode_exists('inat_observations'));
    }

    /**
     * Test shortcode output structure
     */
    public function test_shortcode_output() {
        $output = do_shortcode('[inat_observations]');

        $this->assertStringContainsString('id="inat-observations-root"', $output);
        $this->assertStringContainsString('class="inat-filters"', $output);
        $this->assertStringContainsString('id="inat-list"', $output);
    }

    /**
     * Test shortcode enqueues assets
     */
    public function test_shortcode_enqueues_assets() {
        do_shortcode('[inat_observations]');

        $this->assertTrue(wp_script_is('inat-obs-main', 'enqueued'));
        $this->assertTrue(wp_style_is('inat-obs-main', 'enqueued'));
    }

    /**
     * Test localized script data
     */
    public function test_shortcode_localizes_script() {
        global $wp_scripts;

        do_shortcode('[inat_observations]');

        $data = $wp_scripts->get_data('inat-obs-main', 'data');
        $this->assertStringContainsString('inatObsSettings', $data);
        $this->assertStringContainsString('ajaxUrl', $data);
        $this->assertStringContainsString('nonce', $data);
    }
}
```

**Epic:** E-QA-004: Shortcode Unit Tests

**Effort:** 3 hours

---

### QA-UNIT-004: REST API Tests

**File:** `tests/unit/test-rest.php`

**Test Cases:**
```php
<?php

class Test_Inat_REST_API extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
    }

    /**
     * Test REST route registration
     */
    public function test_rest_route_registered() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/inat/v1/observations', $routes);
    }

    /**
     * Test REST endpoint response
     */
    public function test_rest_get_observations() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 10);

        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    /**
     * Test per_page parameter validation
     */
    public function test_rest_per_page_clamping() {
        // Test max clamping
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 500); // Over limit

        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        // Should clamp to 100

        // Test min clamping
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('per_page', -5); // Negative

        $response2 = rest_get_server()->dispatch($request2);
        $this->assertEquals(200, $response2->get_status());
        // Should clamp to 1
    }
}
```

**Epic:** E-QA-005: REST API Unit Tests

**Effort:** 3 hours

---

## Integration Tests

### QA-INT-001: Full Sync Workflow Test

**File:** `tests/integration/test-cron-sync.php`

**Test Cases:**
```php
<?php

class Test_Inat_Cron_Sync extends WP_UnitTestCase {

    /**
     * Test complete sync workflow
     */
    public function test_full_sync_workflow() {
        // 1. Trigger cron job
        do_action('inat_obs_refresh');

        // 2. Verify data was fetched and stored
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $this->assertGreaterThan(0, $count);
    }

    /**
     * Test cron scheduling
     */
    public function test_cron_scheduled() {
        // Activate plugin
        inat_obs_activate();

        // Check cron event is scheduled
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($timestamp);
    }

    /**
     * Test cron unschedules on deactivation
     */
    public function test_cron_unscheduled_on_deactivation() {
        inat_obs_activate();
        inat_obs_deactivate();

        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertFalse($timestamp);
    }
}
```

**Epic:** E-QA-006: Integration Tests - Cron Sync

**Effort:** 5 hours

---

### QA-INT-002: End-to-End User Flow Test

**File:** `tests/integration/test-full-workflow.php`

**Test Scenario:**
```php
<?php

class Test_Full_Workflow extends WP_UnitTestCase {

    /**
     * Test: User activates plugin → configures settings → views observations
     */
    public function test_complete_user_workflow() {
        // 1. Activate plugin
        inat_obs_activate();

        // 2. Configure settings (simulate admin)
        update_option('inat_obs_project_slug', 'test-project');
        update_option('inat_obs_api_token', 'test-token');

        // 3. Trigger sync
        inat_obs_refresh_job();

        // 4. Verify observations stored
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}inat_observations");
        $this->assertGreaterThan(0, $count);

        // 5. Render shortcode
        $output = do_shortcode('[inat_observations]');
        $this->assertStringContainsString('inat-observations-root', $output);

        // 6. Make REST API request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $response = rest_get_server()->dispatch($request);
        $this->assertEquals(200, $response->get_status());

        // 7. Deactivate plugin
        inat_obs_deactivate();

        // 8. Verify cron unscheduled
        $this->assertFalse(wp_next_scheduled('inat_obs_refresh'));
    }
}
```

**Epic:** E-QA-007: E2E Workflow Tests

**Effort:** 6 hours

---

## Test Data & Fixtures

### QA-DATA-001: Create Test Fixtures

**File:** `tests/fixtures/sample-api-response.json`

```json
{
  "total_results": 142,
  "page": 1,
  "per_page": 2,
  "results": [
    {
      "id": 123456789,
      "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "observed_on": "2024-03-15",
      "species_guess": "Northern Red Oak",
      "taxon": {
        "id": 54321,
        "name": "Quercus rubra",
        "rank": "species"
      },
      "place_guess": "Central Park, Manhattan, New York, USA",
      "location": "40.7829,-73.9654",
      "user": {
        "id": 12345,
        "login": "naturalist123"
      },
      "photos": [
        {
          "id": 987654,
          "url": "https://inaturalist-open-data.s3.amazonaws.com/photos/987654/medium.jpg",
          "attribution": "© naturalist123, some rights reserved (CC-BY)",
          "license_code": "cc-by"
        }
      ],
      "observation_field_values": [
        {
          "observation_field": {
            "id": 1,
            "name": "Tree Height"
          },
          "value": "15 meters"
        }
      ]
    }
  ]
}
```

**Epic:** E-QA-008: Test Fixtures & Mock Data

**Effort:** 2 hours

---

## Code Quality & Standards

### QA-QUALITY-001: PHP CodeSniffer Setup

**Check WordPress Coding Standards:**

```bash
# Install PHPCS
composer require --dev squizlabs/php_codesniffer
composer require --dev wp-coding-standards/wpcs

# Configure
./vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs

# Run checks
./vendor/bin/phpcs --standard=WordPress wp-content/plugins/inat-observations-wp/

# Auto-fix
./vendor/bin/phpcbf --standard=WordPress wp-content/plugins/inat-observations-wp/
```

**Epic:** E-QA-009: Coding Standards Enforcement

**Effort:** 3 hours

---

### QA-QUALITY-002: Code Coverage Tracking

**Setup PHPUnit Coverage:**

```bash
# Generate HTML coverage report
./vendor/bin/phpunit --coverage-html coverage/

# View in browser
open coverage/index.html
```

**Coverage Goals:**
- **Minimum:** 70% overall
- **Critical files:** 90% (api.php, db-schema.php)
- **Admin UI:** 50% (harder to test)

**Epic:** E-QA-010: Code Coverage Tracking

**Effort:** 2 hours

---

## Continuous Integration

### QA-CI-001: GitHub Actions Test Pipeline

**File:** `.github/workflows/tests.yml`

```yaml
name: PHPUnit Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: [ '7.4', '8.0', '8.1', '8.2' ]
        wordpress: [ 'latest', '6.3', '6.2' ]

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mysqli
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Setup WordPress test environment
        run: bash bin/install-wp-tests.sh wordpress_test root '' localhost ${{ matrix.wordpress }}

      - name: Run tests
        run: composer test

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

**Epic:** E-QA-011: CI/CD Test Pipeline

**Effort:** 4 hours

---

## Manual Testing Checklist

### QA-MANUAL-001: Browser Compatibility Testing

**Browsers to Test:**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

**Epic:** E-QA-012: Browser Compatibility Testing

**Effort:** 3 hours

---

### QA-MANUAL-002: WordPress Version Compatibility

**Test Environments:**
- [ ] WordPress 6.4 (latest)
- [ ] WordPress 6.3
- [ ] WordPress 6.2
- [ ] WordPress 5.8 (minimum supported)

**Epic:** E-QA-013: WordPress Version Testing

**Effort:** 4 hours

---

### QA-MANUAL-003: Accessibility Testing

**Tools:**
- [ ] axe DevTools
- [ ] WAVE
- [ ] Screen reader (NVDA/JAWS/VoiceOver)
- [ ] Keyboard navigation
- [ ] Color contrast checker

**Epic:** E-QA-014: Accessibility Testing

**Effort:** 6 hours

---

## Bug Tracking & Regression Testing

### QA-BUG-001: Known Bugs Regression Suite

**Critical Bugs to Test After Fixing:**
1. Database format specifier mismatch (db-schema.php:53)
2. Empty cron refresh job (init.php:33-38)
3. Filter dropdowns never populated
4. No observation list rendering

**Each bug fix should include:**
- Regression test
- Test coverage for the fix
- Manual verification steps

**Epic:** E-QA-015: Regression Test Suite

**Effort:** 8 hours

---

## Epic Summary

| Epic ID | Title | Priority | Effort | Impact |
|---------|-------|----------|--------|--------|
| E-QA-001 | PHPUnit Infrastructure | CRITICAL | 4h | Enables testing |
| E-QA-002 | API Client Tests | HIGH | 6h | Core functionality |
| E-QA-003 | Database Schema Tests | HIGH | 4h | Data integrity |
| E-QA-004 | Shortcode Tests | MEDIUM | 3h | Frontend |
| E-QA-005 | REST API Tests | MEDIUM | 3h | API reliability |
| E-QA-006 | Cron Sync Integration Tests | HIGH | 5h | Background jobs |
| E-QA-007 | E2E Workflow Tests | MEDIUM | 6h | User flows |
| E-QA-008 | Test Fixtures | MEDIUM | 2h | Test infrastructure |
| E-QA-009 | Coding Standards | HIGH | 3h | Code quality |
| E-QA-010 | Code Coverage | MEDIUM | 2h | Metrics |
| E-QA-011 | CI/CD Pipeline | HIGH | 4h | Automation |
| E-QA-012 | Browser Testing | MEDIUM | 3h | Compatibility |
| E-QA-013 | WP Version Testing | MEDIUM | 4h | Compatibility |
| E-QA-014 | Accessibility Testing | HIGH | 6h | WCAG compliance |
| E-QA-015 | Regression Suite | HIGH | 8h | Bug prevention |

**Total Estimated Effort:** ~63 hours

---

**Next Actions (Critical Path):**
1. E-QA-001 (PHPUnit setup) - 4 hours, unblocks all testing
2. E-QA-003 (Database tests) - 4 hours, catches format bug
3. E-QA-002 (API tests) - 6 hours, core functionality
4. E-QA-011 (CI pipeline) - 4 hours, automates everything

**Test Coverage Goal:** 70% by v0.2.0, 85% by v1.0.0

**Reviewed by:** QA Engineer Agent
**Date:** 2026-01-02
