# TODO-QA-003: Quick Test Coverage Fixes

**Status**: In Progress
**Priority**: High
**Estimated Effort**: 2-3 hours

## Overview

This tracks remaining test failures and coverage gaps after the initial test writing pass. We've successfully written tests for db-schema.php, rest.php, and shortcode.php, but some tests are failing due to WordPress file dependencies and constant definitions.

## Current Test Status

### ✅ Passing Tests (26/40 tests passing)

**DbSchemaTest**: 5/6 passing (83%)
- ✅ Store items inserts valid data
- ✅ Store items handles missing fields
- ✅ Store items sanitizes input
- ✅ Store items handles empty results
- ✅ Store items encodes metadata JSON

**ApiTest**: 12/12 passing (100%) 🎉
- All 12 API tests passing
- 95.24% line coverage on api.php

**SimpleTest**: 3/3 passing (100%) 🎉
- PHPUnit smoke tests
- Brain\Monkey validation tests

### ❌ Failing Tests (14/40 tests failing)

**DbSchemaTest**: 1 failure
- ❌ `test_install_schema_creates_table`
  - **Issue**: Tries to `require_once(ABSPATH . 'wp-admin/includes/upgrade.php')`
  - **Fix**: Mock the `require_once` or create stub file

**RestTest**: ~7 failures (ARRAY_A undefined)
- ❌ Multiple tests failing with `Undefined constant "ARRAY_A"`
  - **Issue**: wpdb->get_results() uses ARRAY_A constant at runtime
  - **Root Cause**: Constants defined in bootstrap but not available during test execution
  - **Fix**: Ensure ARRAY_A is defined globally before any code loads

**ShortcodeTest**: ~6 failures (ARRAY_A undefined)
- ❌ Similar ARRAY_A issues in AJAX tests
  - **Issue**: Same as RestTest
  - **Fix**: Same constant definition fix

## Issues to Fix

### 1. ARRAY_A Constant Not Available (Priority: HIGH)

**Problem**: WordPress wpdb constants (ARRAY_A, ARRAY_N, OBJECT, OBJECT_K) defined in bootstrap.php but not available when test functions execute.

**Root Cause**: The constants are defined in bootstrap, but when code coverage loads the plugin files OR when tests execute the functions, the constants aren't in scope.

**Attempted Fixes**:
- ✅ Added constants to bootstrap.php (didn't fix it)
- ✅ Defined ABSPATH in bootstrap (fixed file loading)

**Next Steps**:
1. Debug why constants aren't available at runtime
2. Try defining constants as true PHP constants before bootstrap loads
3. Consider loading all plugin files in bootstrap AFTER defining constants

**File**: `tests/bootstrap.php` lines 35-46

### 2. upgrade.php File Not Found (Priority: MEDIUM)

**Problem**: `db-schema.php:27` tries to `require_once(ABSPATH . 'wp-admin/includes/upgrade.php')` which doesn't exist in test environment.

**Solutions**:
- **Option A**: Create stub file at `/tmp/wordpress/wp-admin/includes/upgrade.php` with `dbDelta()` function
- **Option B**: Use Brain\Monkey to intercept `require_once` (complex)
- **Option C**: Skip test that calls `inat_obs_install_schema()` (not ideal)

**Recommended**: Option A - create minimal stub file

**File**: `wp-content/plugins/inat-observations-wp/includes/db-schema.php:27`

### 3. Missing WordPress Function Stubs (Priority: LOW)

Some WordPress functions might not be stubbed yet. Monitor test output for:
- `shortcode_atts()`
- `wp_enqueue_script()`
- `wp_enqueue_style()`
- `wp_localize_script()`
- `admin_url()`
- `wp_create_nonce()`
- `check_ajax_referer()`
- `wp_send_json_success()`
- `wp_send_json_error()`
- `rest_ensure_response()`

**Status**: Most are mocked in tests via Brain\Monkey, but might need global stubs for coverage.

## Uncovered Code

### Files with 0% Coverage

**Priority Order for Testing**:

1. **init.php** (44 lines) - HARDER
   - Lots of WordPress hooks (add_action, add_filter)
   - Cron job registration
   - Security headers
   - **Effort**: Medium-High (hooks are hard to test)
   - **Recommendation**: Focus on testing individual functions, skip hook registration

2. **admin.php** (119 lines) - HARDEST
   - WordPress admin UI functions
   - Settings pages, forms, AJAX handlers
   - Requires extensive WordPress admin mocking
   - **Effort**: High
   - **Recommendation**: Lower priority, admin UI less critical

3. **rest.php** (44 lines) - WRITTEN BUT FAILING
   - Tests written, just need constant fixes
   - **Effort**: Low (just fix ARRAY_A issue)

4. **shortcode.php** (61 lines) - WRITTEN BUT FAILING
   - Tests written, just need constant fixes
   - **Effort**: Low (just fix ARRAY_A issue)

## Quick Wins

These can be fixed quickly to boost coverage:

1. **Fix ARRAY_A constants** → Fixes 13 tests → Adds ~44 lines of coverage (rest.php + shortcode.php)
2. **Create upgrade.php stub** → Fixes 1 test → Completes db-schema.php testing
3. **Total**: +14 tests passing, +69 lines covered

**Estimated Impact**:
- Current: 40 lines covered / 335 total = 11.94%
- After fixes: 109 lines covered / 335 total = **32.54%** 🎯

## Deferred / Lower Priority

### init.php Testing Strategy

**Challenge**: Heavy use of WordPress hooks makes testing difficult

**Approach**:
1. Test individual functions (activation, deactivation, refresh job)
2. Skip testing hook registration itself
3. Mock WordPress globals ($wpdb, cron functions)

**Functions to Test**:
- `inat_obs_activate()` - Simple activation logic
- `inat_obs_deactivate()` - Unschedule cron
- `inat_obs_refresh_job()` - Fetch and store observations
- Security functions can be skipped (not critical for coverage)

### admin.php Testing Strategy

**Challenge**: WordPress admin functions require heavy mocking

**Approach**:
1. Test settings validation/sanitization functions
2. Test AJAX handlers in isolation
3. Skip rendering functions (low value)

**Priority**: LOW - Admin UI is less critical than API/data functions

## Success Criteria

- [ ] All 40 written tests passing (currently 26/40)
- [ ] 30%+ line coverage (currently 11.94%)
- [ ] DbSchemaTest 100% passing (currently 83%)
- [ ] RestTest 100% passing (currently failing)
- [ ] ShortcodeTest 100% passing (currently failing)

## Next Actions

1. **Immediate** (30 min):
   - Debug ARRAY_A constant issue
   - Create `/tmp/wordpress/wp-admin/includes/upgrade.php` stub
   - Run tests, verify all 40 passing

2. **Short Term** (1-2 hours):
   - Add tests for init.php functions (skip hooks)
   - Achieve 30%+ coverage milestone

3. **Future** (defer to TODO-QA-004):
   - admin.php testing
   - Edge case coverage
   - Achieve 97%+ coverage goal (per TODO-QA-002)

## Notes

- Brain\Monkey is working well for function mocking
- Mockery is working well for object mocking (wpdb, WP_REST_Request)
- Coverage analysis is working with pcov
- Main blocker is WordPress constant/file dependencies

## Related Files

- `tests/bootstrap.php` - Test environment setup
- `tests/unit/ApiTest.php` - ✅ Passing
- `tests/unit/DbSchemaTest.php` - ⚠️  5/6 passing
- `tests/unit/RestTest.php` - ❌ Failing (ARRAY_A)
- `tests/unit/ShortcodeTest.php` - ❌ Failing (ARRAY_A)
- `tests/unit/SimpleTest.php` - ✅ Passing

## Commits

- Initial test files created
- Bootstrap updated with WordPress stubs
- Constants added (ABSPATH, ARRAY_A, etc.)
- **Next**: Fix remaining failures, commit passing tests
