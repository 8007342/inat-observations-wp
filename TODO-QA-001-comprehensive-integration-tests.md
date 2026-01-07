# TODO-QA-001: Comprehensive Integration Tests

**Created:** 2026-01-07
**Status:** 🔨 In Progress
**Priority:** HIGH
**Target:** 0.2.0 Release

---

## Overview

Create comprehensive integration tests covering the full user interaction flow from dropdown population through filtered data display. Tests should use realistic mock data and verify cache behavior.

---

## Objectives

### 1. Mock Data Setup ✅
- [ ] Create 10 mock observations
- [ ] Half (5) have DNA observation fields
- [ ] Include colliding names (e.g., "Amanita muscaria", "Amanita phalloides", "Amanita virosa")
- [ ] Include various locations
- [ ] Include realistic metadata (photos, dates, coordinates)

### 2. Dropdown Integration Tests 🎯
- [ ] **Populate cached list**: Test autocomplete cache population from DB
- [ ] **Display with emoji**: Verify 🧬 prepended to DNA observations
- [ ] **Click interaction**: Test clicking dropdown item populates input field
- [ ] **Trigger refresh**: Verify clicking triggers observation list refresh
- [ ] **Query arguments**: Check correct SQL WHERE clauses generated
- [ ] **Return filtered data**: Verify correct observations returned from API
- [ ] **Display filtered data**: Confirm UI shows only filtered observations

### 3. Cache Integration Tests 🕐
- [ ] **Cache policy**: Use 3-second TTL for integration tests (not 300s/3600s)
- [ ] **Cache hits**: Verify cached data returned on repeat requests within TTL
- [ ] **Cache misses**: Verify fresh DB query after TTL expiration
- [ ] **Cache invalidation**: Test manual cache clear (if implemented)
- [ ] **View page cache**: Test full page cache for unfiltered views
- [ ] **API call mocks**: Mock iNaturalist API responses

### 4. Filter Combination Tests 🔍
- [ ] **Species only**: Filter by single species
- [ ] **Multi-select species**: Filter by multiple species (JSON array)
- [ ] **Location only**: Filter by single location
- [ ] **Multi-select location**: Filter by multiple locations
- [ ] **DNA filter only**: Filter observations with DNA fields
- [ ] **Combined filters**: Species + Location + DNA with AND logic
- [ ] **Unknown Species**: Test empty/NULL species handling
- [ ] **Case-insensitive**: Verify UPPER() matching works correctly

---

## Test Environment Configuration

### Development Cache Timing
```php
// For manual testing (30 seconds)
define('INAT_OBS_DEV_CACHE_TTL', 30);

// For integration tests (3 seconds)
define('INAT_OBS_TEST_CACHE_TTL', 3);
```

### Mock Data Structure
```php
// 10 observations
$mock_observations = [
    // DNA observations (5)
    ['species' => 'Amanita muscaria', 'location' => 'Seattle, WA', 'has_dna' => true],
    ['species' => 'Amanita phalloides', 'location' => 'Portland, OR', 'has_dna' => true],
    ['species' => 'Amanita virosa', 'location' => 'Seattle, WA', 'has_dna' => true],
    ['species' => 'Boletus edulis', 'location' => 'Vancouver, BC', 'has_dna' => true],
    ['species' => 'Cantharellus formosus', 'location' => 'Portland, OR', 'has_dna' => true],

    // Non-DNA observations (5)
    ['species' => 'Morchella esculenta', 'location' => 'Seattle, WA', 'has_dna' => false],
    ['species' => 'Pleurotus ostreatus', 'location' => 'Vancouver, BC', 'has_dna' => false],
    ['species' => 'Lactarius deliciosus', 'location' => 'Portland, OR', 'has_dna' => false],
    ['species' => 'Agaricus campestris', 'location' => 'Seattle, WA', 'has_dna' => false],
    ['species' => 'Unknown Species', 'location' => 'Portland, OR', 'has_dna' => false],
];
```

---

## Test Files

### New Files to Create
1. `tests/integration/test-dropdown-autocomplete.php` - Dropdown population and interaction
2. `tests/integration/test-cache-behavior.php` - Cache hits/misses/expiration
3. `tests/integration/test-filter-combinations.php` - All filter combinations
4. `tests/fixtures/mock-observations.php` - Shared mock data
5. `tests/fixtures/mock-api-responses.php` - iNaturalist API mocks

### Existing Files to Update
1. `tests/bootstrap.php` - Add test cache TTL configuration
2. `wp-content/plugins/inat-observations-wp/includes/rest.php` - Add dev cache TTL support
3. `wp-content/plugins/inat-observations-wp/includes/autocomplete.php` - Add dev cache TTL support

---

## Success Criteria

- [ ] All integration tests pass with mock data
- [ ] Cache behavior verified with 3-second TTL
- [ ] Dropdown interaction fully tested (populate → click → filter → display)
- [ ] Filter combinations all work correctly
- [ ] Tests run in < 10 seconds (fast feedback)
- [ ] No flaky tests (100% reliable)
- [ ] Coverage report shows > 95% integration coverage

---

## Dependencies

- WordPress test environment (`WP_UnitTestCase`)
- PHPUnit 9.6
- Mock observation data fixtures
- Test cache configuration

---

## Notes

- Use 3-second cache for tests (easy to verify expiration)
- Use 30-second cache for manual dev testing
- Production cache remains 300s (filtered) / 3600s (unfiltered)
- Mock data should be realistic and representative
- Tests should be idempotent (can run multiple times)

---

## Related Tasks

- Fix dropdown z-index issue (shows under container)
- Add pagination bar (see TODO-002-add-pagination-to-grid-and-list-views.md)
- Verify filtered counts work correctly
