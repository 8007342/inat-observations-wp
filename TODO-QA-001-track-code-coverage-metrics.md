# TODO-QA-001: Code Coverage & Quality Metrics Tracking

**Created:** 2026-01-06
**Status:** In Progress
**Priority:** HIGH
**Target:** 97%+ line coverage, 0 warnings, 0% dead code

---

## Overview

Track code quality metrics and improve test coverage systematically to achieve excellent code quality standards.

**Quality Targets:**
- ✅ Line Coverage: 97%+
- ✅ Function Coverage: 98%+
- ✅ Class Coverage: 100%
- ✅ Warnings: 0
- ✅ Errors: 0
- ✅ Dead Code: 0% (production code only)

---

## Current State (Baseline - 2026-01-06)

**Code Coverage:**
- Line Coverage: ~15% (estimated, no tests yet)
- Function Coverage: ~10%
- Class Coverage: ~20%

**Code Quality:**
- Warnings: Unknown (need to run phpcs)
- Errors: Unknown
- Dead Code: Unknown

**Test Suite:**
- Total Tests: 2 (skeleton tests only)
- Integration Tests: 1 (test-activation.php)
- Unit Tests: 1 (test-db-schema.php)

**Files Without Tests:**
- `includes/api.php` - 0% coverage
- `includes/rest.php` - 0% coverage
- `includes/shortcode.php` - 0% coverage
- `includes/admin.php` - 0% coverage
- `includes/init.php` - Partial (activation only)

---

## Coverage Improvement Roadmap

### Phase 1: Critical Path Coverage (Target: 60%)

**Priority Files:**

#### 1. `includes/api.php` (CRITICAL)
**Current:** 0% coverage
**Target:** 95% coverage

**Test Cases Needed:**
- ✅ Test successful API fetch with valid project ID
- ✅ Test API fetch with invalid project ID (error handling)
- ✅ Test API fetch with network timeout
- ✅ Test caching behavior (transient hit/miss)
- ✅ Test pagination parameters
- ✅ Test API token header injection
- ✅ Test rate limiting (429 response)
- ✅ Test malformed JSON response
- ✅ Test empty results array

**Effort:** 4 hours

---

#### 2. `includes/db-schema.php` (CRITICAL)
**Current:** ~30% coverage (basic activation test only)
**Target:** 98% coverage

**Test Cases Needed:**
- ✅ Test table creation on activation
- ✅ Test table already exists (no duplicate)
- ✅ Test column types and constraints
- ✅ Test index creation
- ✅ Test store_items() with valid data
- ✅ Test store_items() with missing fields
- ✅ Test store_items() with duplicate IDs (replace)
- ✅ Test metadata JSON encoding
- ✅ Test sanitization of species_guess/place_guess

**Effort:** 3 hours

---

#### 3. `includes/init.php` (HIGH)
**Current:** ~20% coverage
**Target:** 95% coverage

**Test Cases Needed:**
- ✅ Test activation hook registers cron job
- ✅ Test deactivation hook clears cron job
- ✅ Test refresh job with valid user_id
- ✅ Test refresh job with valid project_id
- ✅ Test refresh job with no config (error log)
- ✅ Test refresh job with API error
- ✅ Test refresh job updates options (last_refresh, count)
- ✅ Test security headers are sent
- ✅ Test HTTPS enforcement in production

**Effort:** 3 hours

---

### Phase 2: User-Facing Features (Target: 85%)

#### 4. `includes/shortcode.php` (HIGH)
**Current:** 0% coverage
**Target:** 90% coverage

**Test Cases Needed:**
- ✅ Test shortcode renders container HTML
- ✅ Test assets are enqueued
- ✅ Test wp_localize_script passes ajaxurl and nonce
- ✅ Test shortcode attributes (future: limit, filter)

**Effort:** 2 hours

---

#### 5. `includes/rest.php` (HIGH)
**Current:** 0% coverage
**Target:** 92% coverage

**Test Cases Needed:**
- ✅ Test REST route registration
- ✅ Test GET /inat/v1/observations returns data
- ✅ Test per_page parameter validation
- ✅ Test per_page max limit (200)
- ✅ Test nonce verification
- ✅ Test permission check
- ✅ Test error responses (500, 400)

**Effort:** 2.5 hours

---

### Phase 3: Polish & Edge Cases (Target: 97%+)

#### 6. `includes/admin.php` (MEDIUM)
**Current:** 0% coverage
**Target:** 95% coverage

**Test Cases Needed:**
- ✅ Test admin menu registration
- ✅ Test settings page render (capability check)
- ✅ Test settings save (nonce verification)
- ✅ Test settings sanitization
- ✅ Test manual sync button

**Effort:** 2 hours

---

#### 7. Edge Cases & Error Handling
**Target:** Cover all error paths

**Test Cases Needed:**
- ✅ Test database write failures (wpdb error)
- ✅ Test invalid JSON in API response
- ✅ Test API rate limit exceeded
- ✅ Test expired nonce
- ✅ Test user without capabilities
- ✅ Test malformed observation data

**Effort:** 2 hours

---

## Code Quality Improvements

### PHPCS (WordPress Coding Standards)

**Current Issues (estimated):**
- Inconsistent indentation
- Missing docblocks
- Long lines (>100 chars)
- No @since tags

**Target:** 0 warnings, 0 errors

**Action Items:**
1. Run `composer lint` to establish baseline
2. Fix critical errors first
3. Auto-fix with `composer lint:fix` where possible
4. Manual fixes for complex issues
5. Add pre-commit hook to enforce standards

**Effort:** 3 hours

---

### Dead Code Detection

**Tools:**
- PHPMD (PHP Mess Detector)
- PHP-Assumptions

**Target:** 0% dead code in production files

**Known Dead Code:**
- `inat_obs_fetch_all()` in `includes/api.php` - empty stub
- Commented-out code in early commits

**Action Items:**
1. Run PHPMD to detect unused functions
2. Remove or implement stubbed functions
3. Clean up commented-out code
4. Add PHPMD to CI/CD pipeline

**Effort:** 1 hour

---

## Metrics Generation & Dashboard

### Build Targets (composer.json)

```json
{
  "scripts": {
    "test": "phpunit",
    "test:coverage": "phpunit --coverage-html dashboard/coverage --coverage-clover dashboard/coverage/clover.xml",
    "lint": "phpcs --standard=WordPress wp-content/plugins/inat-observations-wp/",
    "lint:fix": "phpcbf --standard=WordPress wp-content/plugins/inat-observations-wp/",
    "metrics:generate": "php bin/generate-metrics.php",
    "dashboard:build": [
      "@test:coverage",
      "@metrics:generate"
    ],
    "install-hooks": "php bin/install-hooks.php"
  }
}
```

### Pre-Commit Hook

Automatically rebuild dashboard on commit:

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Running tests with coverage..."
composer test:coverage --quiet

echo "Generating metrics..."
composer metrics:generate

# Snapshot metrics to history
TIMESTAMP=$(date +"%Y-%m-%d-%H%M%S")
COMMIT=$(git rev-parse --short HEAD)
cp dashboard/metrics.json dashboard/history/${TIMESTAMP}-${COMMIT}-metrics.json

# Stage dashboard artifacts
git add dashboard/metrics.json
git add dashboard/metrics.md
git add dashboard/history/

echo "✅ Dashboard updated"
```

---

## CI/CD Quality Gates

### GitHub Actions

```yaml
name: Quality Gates

on: [push, pull_request]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: xdebug

      - name: Install Dependencies
        run: composer install

      - name: Run Tests with Coverage
        run: composer test:coverage

      - name: Generate Metrics
        run: composer metrics:generate

      - name: Check Coverage Threshold
        run: |
          COVERAGE=$(jq -r '.coverage.line_percentage' dashboard/metrics.json)
          echo "Coverage: $COVERAGE%"
          if (( $(echo "$COVERAGE < 97" | bc -l) )); then
            echo "❌ Coverage below 97%: $COVERAGE%"
            exit 1
          fi

      - name: Check Code Quality
        run: |
          composer lint
          WARNINGS=$(jq -r '.quality.warnings' dashboard/metrics.json)
          if [ "$WARNINGS" -gt 0 ]; then
            echo "❌ Found $WARNINGS warnings"
            exit 1
          fi

      - name: Upload Coverage Report
        uses: actions/upload-artifact@v3
        with:
          name: coverage-report
          path: dashboard/coverage/
```

---

## Testing Strategy

### Unit Tests

**Focus:** Individual functions in isolation

**Mock Dependencies:**
- WordPress functions (wp_remote_get, get_option, etc.)
- Database (wpdb)
- External APIs

**Location:** `tests/unit/`

**Example:**
```php
// tests/unit/test-api.php
public function test_fetch_observations_with_valid_project() {
    // Mock wp_remote_get to return fake iNat data
    Functions\expect('wp_remote_get')
        ->once()
        ->andReturn(['body' => json_encode(['results' => [/*...*/]])]);

    $data = inat_obs_fetch_observations(['project' => 'test-project']);

    $this->assertIsArray($data);
    $this->assertArrayHasKey('results', $data);
}
```

---

### Integration Tests

**Focus:** Full workflows with WordPress environment

**Real Dependencies:**
- Actual database (test DB)
- WordPress core functions
- Plugin activation/deactivation

**Location:** `tests/integration/`

**Example:**
```php
// tests/integration/test-cron-refresh.php
public function test_refresh_job_fetches_and_stores_data() {
    // Set up test options
    update_option('inat_obs_project_id', 'test-project');

    // Trigger refresh job
    do_action('inat_obs_refresh');

    // Verify database has observations
    global $wpdb;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}inat_observations");

    $this->assertGreaterThan(0, $count);
}
```

---

## Coverage by File (Target State)

| File | Current | Target | Priority | Effort |
|------|---------|--------|----------|--------|
| `includes/api.php` | 0% | 95% | CRITICAL | 4h |
| `includes/db-schema.php` | 30% | 98% | CRITICAL | 3h |
| `includes/init.php` | 20% | 95% | HIGH | 3h |
| `includes/rest.php` | 0% | 92% | HIGH | 2.5h |
| `includes/shortcode.php` | 0% | 90% | HIGH | 2h |
| `includes/admin.php` | 0% | 95% | MEDIUM | 2h |
| `inat-observations-wp.php` | 0% | 100% | LOW | 0.5h |
| `uninstall.php` | 0% | 95% | LOW | 1h |

**Total Effort:** ~18 hours

---

## Milestones

- ⏸ **M1:** Baseline established (current state documented) - 2026-01-06
- ⏸ **M2:** Phase 1 complete (60% coverage) - Target: +1 week
- ⏸ **M3:** Phase 2 complete (85% coverage) - Target: +2 weeks
- ⏸ **M4:** Phase 3 complete (97%+ coverage) - Target: +3 weeks
- ⏸ **M5:** Quality gates enforced in CI/CD - Target: +3 weeks
- ⏸ **M6:** Pre-commit hooks active - Target: +3 weeks

---

## Success Criteria

✅ **Coverage:**
- Line coverage ≥ 97%
- Function coverage ≥ 98%
- Class coverage = 100%

✅ **Quality:**
- PHPCS warnings = 0
- PHPCS errors = 0
- Dead code = 0% (production)

✅ **Testing:**
- All critical paths tested
- Error handling tested
- Edge cases covered

✅ **Automation:**
- Pre-commit hook generates dashboard
- CI/CD enforces quality gates
- Metrics tracked over time

---

**Next Actions:**
1. Create `bin/generate-metrics.php` script
2. Update `composer.json` with build targets
3. Install pre-commit hook
4. Run baseline coverage report
5. Start Phase 1: Write tests for `includes/api.php`

**Reviewed by:** QA Engineer
**Last Updated:** 2026-01-06
