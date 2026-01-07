# TODO-QA-002: Test Coverage Goals - 97% Excellence

**Created:** 2026-01-06
**Status:** In Progress
**Priority:** CRITICAL
**Target:** 97%+ Code Coverage, EXCELLENT Quality

---

## Mission: EXCELLENT CODE QUALITY

> **"We don't ship mediocre code. We ship excellent code."**

**Quality Standards:**
- ✅ **97%+** Line Coverage (Excellent)
- ✅ **98%+** Function Coverage (Excellent)
- ✅ **100%** Class Coverage (Perfect)
- ✅ **0** PHPCS Warnings
- ✅ **0** PHPCS Errors
- ✅ **0%** Dead Code (production files)

---

## Current Baseline (2026-01-06)

**Coverage Status:**
```
Line Coverage:       ~30% (from db-schema tests only)
Function Coverage:   ~20%
Class Coverage:      N/A (no classes yet)

Files Tested:  1/8  (12.5%)
Total Tests:   9 tests (all in test-db-schema.php)
```

**Files Status:**
| File | Coverage | Tests | Status |
|------|----------|-------|--------|
| `inat-observations-wp.php` | 0% | None | ⚪ Not Started |
| `includes/init.php` | ~15% | Partial (activation only) | 🟡 Needs Work |
| `includes/db-schema.php` | ~85% | 9 tests | 🟢 Good |
| `includes/api.php` | 0% | None | ⚪ Not Started |
| `includes/rest.php` | 0% | None | ⚪ Not Started |
| `includes/shortcode.php` | 0% | None | ⚪ Not Started |
| `includes/admin.php` | 0% | None | ⚪ Not Started |
| `uninstall.php` | 0% | None | ⚪ Not Started |

---

## Testing Philosophy

### What We Believe

1. **Tests Are Documentation** - Tests show how code should be used
2. **Fast Feedback Loops** - Tests run in < 5 seconds
3. **Confidence to Refactor** - High coverage means safe refactoring
4. **No Hacky Tests** - Clean, readable test code (test code can be pragmatic)
5. **Real-World Scenarios** - Test what users actually do

### Unit vs Integration

**Unit Tests:**
- Test individual functions in isolation
- Mock ALL external dependencies (WordPress functions, HTTP calls, database)
- Fast (<1 second for entire suite)
- No side effects

**Integration Tests:**
- Test full workflows with real WordPress environment
- Real database, real WordPress functions
- Slower (can take 5-10 seconds)
- Test actual behavior users see

**Goal:** 80% unit tests, 20% integration tests

---

## Testing Tools & Framework

### Core Tools

```json
{
  "require-dev": {
    "phpunit/phpunit": "^9.5",
    "yoast/phpunit-polyfills": "^1.0",
    "wp-phpunit/wp-phpunit": "^6.1",
    "brain/monkey": "^2.6",
    "mockery/mockery": "^1.5"
  }
}
```

**Brain\Monkey:**
- Mocks WordPress functions (e.g., `wp_remote_get`, `get_option`)
- Works without WordPress loaded
- Perfect for fast unit tests

**Mockery:**
- Mock objects and dependencies
- Fluent assertion API
- Works with Brain\Monkey

**WP_UnitTestCase:**
- Integration tests with real WordPress
- Access to database, hooks, etc.
- Slower but tests real behavior

---

## Test File Structure

```
tests/
├── bootstrap.php              # Test environment setup
├── phpunit.xml                # PHPUnit configuration
├── unit/                      # Unit tests (fast, mocked)
│   ├── test-api.php           # api.php tests
│   ├── test-db-schema.php     # db-schema.php tests (existing)
│   ├── test-init.php          # init.php tests
│   ├── test-rest.php          # rest.php tests
│   ├── test-shortcode.php     # shortcode.php tests
│   └── test-admin.php         # admin.php tests
├── integration/               # Integration tests (slow, real WP)
│   ├── test-activation.php    # Plugin activation (existing)
│   ├── test-full-sync.php     # Full API sync workflow
│   ├── test-cron.php          # WP-Cron refresh job
│   └── test-rest-api.php      # REST endpoint end-to-end
└── fixtures/                  # Test data (mock API responses)
    └── inat-api-response.json # Sample iNaturalist API data
```

---

## Priority 1: Critical Path (Target: 60% → Week 1)

### 1.1 API Tests (`test-api.php`) ⚪

**Functions to Test:**
- `inat_obs_fetch_observations()` - 12 test cases
- `inat_obs_fetch_all()` - 4 test cases (once implemented)

**Test Cases:**

```php
class Test_Inat_API extends PHPUnit\Framework\TestCase {
    use Brain\Monkey\Functions;

    // ✅ Happy path
    public function test_fetch_observations_success() {}

    // ✅ Error handling
    public function test_fetch_observations_network_error() {}
    public function test_fetch_observations_http_404() {}
    public function test_fetch_observations_http_500() {}
    public function test_fetch_observations_malformed_json() {}

    // ✅ Caching
    public function test_fetch_uses_cache_on_repeat() {}
    public function test_fetch_bypasses_expired_cache() {}

    // ✅ Parameters
    public function test_fetch_with_project_id() {}
    public function test_fetch_with_user_id() {}
    public function test_fetch_with_pagination() {}

    // ✅ API token
    public function test_fetch_with_api_token() {}
    public function test_fetch_without_api_token() {}
}
```

**Coverage Target:** 95%

**Effort:** 4 hours

---

### 1.2 Init Tests (`test-init.php`) ⚪

**Functions to Test:**
- `inat_obs_activate()` - 3 test cases
- `inat_obs_deactivate()` - 2 test cases
- `inat_obs_refresh_job()` - 8 test cases
- `inat_obs_security_headers()` - 3 test cases
- `inat_obs_enforce_https()` - 3 test cases

**Test Cases:**

```php
class Test_Inat_Init extends PHPUnit\Framework\TestCase {
    use Brain\Monkey\Functions;

    // Activation
    public function test_activation_creates_cron_job() {}
    public function test_activation_calls_install_schema() {}
    public function test_activation_idempotent() {}

    // Deactivation
    public function test_deactivation_clears_cron() {}

    // Refresh job
    public function test_refresh_job_with_user_id() {}
    public function test_refresh_job_with_project_id() {}
    public function test_refresh_job_with_both_ids() {}
    public function test_refresh_job_with_no_ids_logs_error() {}
    public function test_refresh_job_on_api_error() {}
    public function test_refresh_job_updates_options() {}

    // Security
    public function test_security_headers_sent() {}
    public function test_https_enforcement_production() {}
    public function test_https_not_enforced_dev() {}
}
```

**Coverage Target:** 95%

**Effort:** 3 hours

---

### 1.3 Database Tests (`test-db-schema.php`) ✅

**Status:** Already comprehensive (9 tests)

**Additional Tests Needed:**
```php
// Edge cases
public function test_store_items_with_missing_required_fields() {}
public function test_store_items_with_invalid_date_format() {}
public function test_metadata_with_nested_arrays() {}
```

**Coverage Target:** 98% (from 85%)

**Effort:** 1 hour

---

## Priority 2: User-Facing Features (Target: 85% → Week 2)

### 2.1 REST API Tests (`test-rest.php`) ⚪

**Functions to Test:**
- `inat_obs_register_rest_routes()` - 2 tests
- `inat_obs_rest_get_observations()` - 8 tests

**Test Cases:**

```php
class Test_Inat_REST extends WP_UnitTestCase {
    // Integration tests (real WordPress)

    public function test_rest_route_registered() {}
    public function test_rest_get_returns_json() {}
    public function test_rest_pagination() {}
    public function test_rest_per_page_validation() {}
    public function test_rest_nonce_required() {}
    public function test_rest_permission_check() {}
    public function test_rest_error_on_api_failure() {}
    public function test_rest_returns_cached_data() {}
}
```

**Coverage Target:** 92%

**Effort:** 2.5 hours

---

### 2.2 Shortcode Tests (`test-shortcode.php`) ⚪

**Functions to Test:**
- `inat_obs_register_shortcode()` - 2 tests
- `inat_obs_shortcode_handler()` - 5 tests
- `inat_obs_enqueue_assets()` - 3 tests

**Test Cases:**

```php
class Test_Inat_Shortcode extends WP_UnitTestCase {
    public function test_shortcode_registered() {}
    public function test_shortcode_renders_container() {}
    public function test_shortcode_enqueues_scripts() {}
    public function test_shortcode_localizes_ajax_vars() {}
    public function test_shortcode_nonce_generated() {}
}
```

**Coverage Target:** 90%

**Effort:** 2 hours

---

## Priority 3: Admin & Polish (Target: 97%+ → Week 3)

### 3.1 Admin Tests (`test-admin.php`) ⚪

**Functions to Test:**
- `inat_obs_admin_menu()` - 2 tests
- `inat_obs_admin_page()` - 6 tests

**Test Cases:**

```php
class Test_Inat_Admin extends WP_UnitTestCase {
    public function test_admin_menu_registered() {}
    public function test_admin_page_requires_capability() {}
    public function test_admin_settings_save() {}
    public function test_admin_settings_sanitization() {}
    public function test_admin_manual_sync_button() {}
}
```

**Coverage Target:** 95%

**Effort:** 2 hours

---

### 3.2 Uninstall Tests (`test-uninstall.php`) ⚪

**Test Cases:**

```php
class Test_Inat_Uninstall extends WP_UnitTestCase {
    public function test_uninstall_deletes_table() {}
    public function test_uninstall_deletes_options() {}
    public function test_uninstall_clears_transients() {}
}
```

**Coverage Target:** 95%

**Effort:** 1 hour

---

## Integration Tests (Full Workflows)

### test-full-sync.php ⚪

**Scenario:** Complete API sync workflow

```php
public function test_complete_sync_workflow() {
    // 1. Activate plugin
    // 2. Configure settings (project ID)
    // 3. Trigger manual sync
    // 4. Verify data in database
    // 5. Verify REST endpoint returns data
    // 6. Verify shortcode displays observations
}
```

**Effort:** 2 hours

---

### test-cron.php ⚪

**Scenario:** WP-Cron scheduled refresh

```php
public function test_cron_refresh_job_runs() {
    // 1. Set up cron schedule
    // 2. Simulate cron trigger
    // 3. Verify data refreshed
    // 4. Verify options updated
}
```

**Effort:** 1.5 hours

---

## Mocking Strategy

### WordPress Functions (Brain\Monkey)

```php
use Brain\Monkey\Functions;

// Mock wp_remote_get
Functions\when('wp_remote_get')->justReturn([
    'body' => json_encode(['results' => [/*...*/]]),
    'response' => ['code' => 200],
]);

// Mock get_option
Functions\when('get_option')->alias(function($key, $default = null) {
    return ['inat_obs_project_id' => 'test-project'][$key] ?? $default;
});

// Mock current_time
Functions\when('current_time')->justReturn('2026-01-06 14:30:00');
```

### External API (Fixtures)

```json
// tests/fixtures/inat-api-response.json
{
  "results": [
    {
      "id": 12345,
      "uuid": "abc-123",
      "observed_on": "2024-01-15",
      "species_guess": "Quercus rubra",
      "place_guess": "Central Park, New York",
      "observation_field_values": [
        {"name": "Height", "value": "10m"}
      ]
    }
  ],
  "total_results": 1
}
```

---

## Coverage Milestones

### Week 1: Critical Path (60%)
- ✅ test-api.php (95% coverage)
- ✅ test-init.php (95% coverage)
- ✅ test-db-schema.php (98% coverage)

### Week 2: User-Facing (85%)
- ✅ test-rest.php (92% coverage)
- ✅ test-shortcode.php (90% coverage)
- ✅ Integration tests

### Week 3: Polish (97%+)
- ✅ test-admin.php (95% coverage)
- ✅ test-uninstall.php (95% coverage)
- ✅ Edge cases & error paths
- ✅ All quality gates green

---

## Quality Gates (CI/CD)

```yaml
# .github/workflows/tests.yml

quality-gates:
  - name: Coverage Check
    threshold: 97%
    blocking: true

  - name: PHPCS Check
    warnings: 0
    errors: 0
    blocking: true

  - name: Test Suite
    min_tests: 50
    all_passing: true
    blocking: true
```

---

## Running Tests

```bash
# All tests
composer test

# Unit tests only (fast)
phpunit tests/unit/

# Integration tests only
phpunit tests/integration/

# Specific file
phpunit tests/unit/test-api.php

# With coverage
composer test:coverage

# Watch mode (re-run on file change)
phpunit --watch tests/unit/
```

---

## Success Metrics

**Coverage:**
- ✅ 97%+ line coverage
- ✅ 98%+ function coverage
- ✅ 100% class coverage

**Test Suite:**
- ✅ 50+ total tests
- ✅ All tests passing
- ✅ Test execution < 10 seconds

**Quality:**
- ✅ 0 PHPCS warnings
- ✅ 0 PHPCS errors
- ✅ 0% dead code

**CI/CD:**
- ✅ All quality gates green
- ✅ Dashboard auto-updates on commit
- ✅ Coverage metrics tracked over time

---

## Resources

**WordPress Testing:**
- https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/
- https://github.com/wp-phpunit/wp-phpunit

**Brain\Monkey:**
- https://brain-wp.github.io/BrainMonkey/
- https://giuseppe-mazzapica.gitbook.io/brain-monkey/

**PHPUnit Best Practices:**
- https://phpunit.de/documentation.html
- https://github.com/sebastianbergmann/phpunit/wiki

---

**Next Actions:**
1. Add Brain\Monkey and Mockery to composer.json
2. Write test-api.php (Priority 1.1)
3. Write test-init.php (Priority 1.2)
4. Improve test-db-schema.php coverage (Priority 1.3)
5. Run `composer dashboard:build` to track progress

**Target:** Week 1 complete by 2026-01-13

---

**Created:** 2026-01-06
**Status:** In Progress (0% → 97% journey begins)
**Commitment:** EXCELLENT CODE QUALITY - No shortcuts, no excuses.
