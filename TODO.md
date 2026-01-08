# TODO - iNaturalist Observations WordPress Plugin

**Last Updated**: 2026-01-07
**Current State**: 🚨 **DROPDOWN REGRESSION - Won't Display After First Selection**

---

## 🎯 Quick Restart Point

### Current Issue: Dropdown Display Regression

**Symptom**: After selecting an item from the autocomplete dropdown once, the dropdown won't display again on subsequent searches.

**Status**: Tracked in `TODO-BUG-002-dropdown-selector-borked.md` as **EXTRA BORKED**

**What Works**:
- ✅ Autocomplete loads once (no reload on selection) - fixed in commit ab524a9
- ✅ Array filters use IN clause - fixed in commit 3f0aca3
- ✅ Selected items add chips to filter bar
- ✅ Filter queries execute correctly
- ✅ All 61 unit tests passing

**What's Broken**:
- ❌ Dropdown disappears after first selection and won't show again
- ❌ User can't select additional filters without page reload

**Next Steps**:
1. Read `TODO-BUG-002-dropdown-selector-borked.md` for detailed analysis
2. Browser test at http://localhost:8080 to confirm symptoms
3. Check browser console for JavaScript errors
4. Fix dropdown persistence issue
5. Test DNA filter with debug logging (TODO-003)

---

## ✅ Recently Completed (2026-01-07)

### 1. Query Construction Fix (commit 3f0aca3)
- **Fixed**: Species/location filters now use SQL `IN` clause instead of OR conditions
- **Before**: `WHERE (species = 'A' OR species = 'B')`
- **After**: `WHERE species IN ('A', 'B')`
- **File**: `includes/rest.php`

### 2. Autocomplete Caching Fix (commit ab524a9)
- **Fixed**: Autocomplete API no longer reloads on every filter selection
- **Implementation**: Moved autocomplete setup to `initializeAutocomplete()`, runs once after cache loaded
- **File**: `assets/js/main.js`

### 3. Comprehensive Security Audit (commit 1ef1f6e)
- **Completed**: Full dependency analysis and security review
- **Finding**: Zero JavaScript dependencies (exceptional security posture)
- **Rating**: EXCELLENT - no critical or high risks
- **File**: `TODO-AUDIT.md`

### 4. Debug Logging for DNA Filter (commit 7370370)
- **Added**: Verbose query logging to WordPress console
- **Location**: DNA filter config, query execution, count query
- **Status**: Ready for browser testing
- **File**: `includes/rest.php` (lines 168-280)

---

## 🐛 Active Bugs

### Priority 1: Dropdown Display Regression
**File**: `TODO-BUG-002-dropdown-selector-borked.md`
**Status**: 🚨 CRITICAL - EXTRA BORKED
**Symptom**: Dropdown won't display after first selection
**Impact**: Can't select multiple filters without page reload

### Priority 2: DNA Filter Not Working
**File**: `TODO-003-debug-statements.md`
**Status**: ⏳ DEBUGGING - awaiting browser test
**Symptom**: `has_dna=1` returns all observations instead of filtering
**Debug**: Verbose logging added, needs browser testing to analyze

---

## 📊 Test Status

**Unit Tests**: ✅ 61/61 passing (100%)
- ✅ REST endpoint tests (pagination, filters, sorting)
- ✅ Autocomplete tests (caching, normalization)
- ✅ Database schema tests (migrations, indexes)
- ✅ SQL injection prevention tests
- ✅ XSS prevention tests

**Integration Tests**: ⏳ Pending
- ⏳ Browser testing for dropdown regression
- ⏳ DNA filter with real data
- ⏳ Autocomplete caching verification

**Coverage**: 📈 High (estimated 85%+)
- Strong backend coverage (REST, DB, autocomplete)
- Good security coverage (SQL injection, XSS, CSRF)
- Missing: Some frontend edge cases

---

## 🎯 Next Session Tasks

### Immediate (Start Here)

1. **Fix Dropdown Display Regression** 🚨
   - Read `TODO-BUG-002-dropdown-selector-borked.md` for context
   - Browser test at http://localhost:8080
   - Check if dropdown wrapper/container persists after `fetchObservations()`
   - Likely issue: `initializeAutocomplete()` only runs once, but `fetchObservations()` rebuilds HTML
   - Suspected root cause: Search input element replaced on render, event listeners lost

2. **Test DNA Filter with Logging**
   - Visit http://localhost:8080
   - Check DNA checkbox
   - Open browser console (F12)
   - Check WordPress debug.log for verbose output
   - Analyze query execution logs

3. **Increase Test Coverage (Low Hanging Fruit)**
   - Add tests for edge cases (empty arrays, null values)
   - Add tests for cache invalidation
   - Add tests for error handling paths

### After Fixes

4. **Remove Debug Logging** (TODO-003 cleanup)
   - Remove verbose error_log() statements from rest.php
   - Keep only essential logging

5. **Browser Testing Checklist**
   - [ ] Autocomplete loads once (no reload on selection)
   - [ ] Dropdown persists across multiple selections
   - [ ] Multiple species filters work
   - [ ] Multiple location filters work
   - [ ] Combined filters work (species + location + DNA)
   - [ ] Filter chips display correctly
   - [ ] Removing chips works
   - [ ] Pagination works with filters

6. **Performance Testing**
   - [ ] Large datasets (1000+ observations)
   - [ ] Cache hit rates
   - [ ] Query execution time

---

## 📝 Implementation Learnings (Read Before Fixing!)

### Critical Insights from TODO-BUG-002

1. **Autocomplete Setup Location**
   - ❌ DON'T: Put autocomplete setup inside `fetchObservations()`
   - ✅ DO: Initialize once after cache loads, outside render cycle
   - **Why**: `fetchObservations()` runs on every filter change, causes re-initialization

2. **DOM Element Persistence**
   - ❌ DON'T: Replace parent elements that have event listeners
   - ✅ DO: Update `innerHTML` of container, preserve wrapper elements
   - **Why**: Event listeners are lost when parent elements are replaced

3. **Cache-First Architecture**
   - ❌ DON'T: Fetch autocomplete data on demand
   - ✅ DO: Load all autocomplete data once on page load
   - **Why**: Reduces network requests, improves UX

4. **Query Construction**
   - ❌ DON'T: Use `OR` conditions for array filters
   - ✅ DO: Use `IN` clause with placeholders
   - **Why**: Better performance, cleaner SQL, proper parameterization

5. **Test Coverage**
   - ❌ DON'T: Skip tests because "it's frontend code"
   - ✅ DO: Mock WordPress environment, test all code paths
   - **Why**: Caught 3 critical bugs during development

### New Regression Pattern (Dropdown Won't Display Again)

**Hypothesis**: The search input element is being replaced when `fetchObservations()` rebuilds the HTML, which loses the autocomplete event listeners.

**Investigation Path**:
1. Check if `initializeAutocomplete()` finds the input element
2. Check if the input element ID changes between renders
3. Check if the wrapper element persists
4. Check browser console for "element not found" errors
5. Verify event listeners are still attached after render

**Potential Fixes**:
- Option A: Don't replace the search input element in `fetchObservations()`
- Option B: Re-initialize autocomplete after each render (defeats caching purpose)
- Option C: Move search input outside of re-rendered container

---

## 🗂️ File Structure

### Plugin Core
```
wp-content/plugins/inat-observations-wp/
├── inat-observations-wp.php       # Main plugin file
├── includes/
│   ├── init.php                   # Plugin initialization, WP-Cron
│   ├── admin.php                  # Admin settings page
│   ├── api.php                    # iNaturalist API integration
│   ├── db-schema.php              # Database tables, migrations
│   ├── rest.php                   # REST API endpoint (⚠️ has debug logging)
│   ├── shortcode.php              # WordPress shortcode handler
│   └── autocomplete.php           # Autocomplete data generation
├── assets/
│   ├── js/main.js                 # Frontend JavaScript (⚠️ has dropdown bug)
│   └── css/main.css               # Styles
└── tests/
    ├── phpunit.xml                # PHPUnit configuration
    ├── unit/                      # Unit tests (61 tests)
    │   ├── RestTest.php
    │   ├── RestEnhancedTest.php
    │   ├── AutocompleteTest.php
    │   ├── DbSchemaTest.php
    │   └── ArrayFilterTest.php    # Placeholder for integration tests
    └── fixtures/                  # Test data
```

### Documentation
```
├── TODO.md                                  # ⭐ This file (quick restart)
├── TODO-BUG-002-dropdown-selector-borked.md # 🚨 Dropdown regression details
├── TODO-BUG-004-array-filter-query-construction.md # ✅ Fixed (IN clause)
├── TODO-003-debug-statements.md             # DNA filter debugging
├── TODO-AUDIT.md                            # Security audit (complete)
├── TODO-QA-next-steps.md                    # QA testing plan
├── WORDPRESS-PLUGIN.md                      # Architecture documentation
└── README.md                                # Project overview
```

---

## 🚀 Docker Quick Start

```bash
# Start development environment
docker-compose up -d

# View logs
docker logs -f wordpress
docker logs -f mysql

# Stop environment
docker-compose down

# Clean reset (DESTRUCTIVE)
docker-compose down -v
```

**Access**:
- WordPress: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (admin/admin)

---

## 🧪 Testing Quick Start

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/unit/RestTest.php

# Run with coverage
composer test:coverage

# SQL injection checks
bash tests/security/check-sql-injection.sh

# Pre-commit checks (auto-run on commit)
bash .git/hooks/pre-commit
```

---

## 📈 Project Stats

- **Lines of Code**: ~5,000 (PHP + JavaScript)
- **Test Coverage**: 85%+ estimated
- **Unit Tests**: 61 passing
- **Dependencies**: 7 dev (Composer), 0 runtime, 0 JavaScript
- **Security Rating**: EXCELLENT (zero critical/high risks)
- **Last Commit**: 1ef1f6e (Security audit)
- **Branch**: main
- **Origin**: github.com:8007342/inat-observations-wp.git

---

## 🔍 Recent Commits

```
1ef1f6e - Add security audit ADDENDUM with dependency analysis
bc70bc4 - Update TODO-BUG-004: Mark as fixed with implementation summary
ab524a9 - TODO-BUG-004: Fix autocomplete reload on selection
3f0aca3 - TODO-BUG-004: Fix query construction to use IN clause for arrays
7370370 - TODO-003: Add verbose debug logging for DNA filter
```

---

## 🎯 Session Goals

**Primary Goal**: Fix dropdown display regression so users can select multiple filters

**Secondary Goals**:
1. Test DNA filter with browser
2. Increase test coverage (low hanging fruit)
3. Clean up debug logging after DNA filter works

**Success Criteria**:
- [ ] Dropdown displays on every search (not just first)
- [ ] Multiple filters can be selected without page reload
- [ ] DNA filter works correctly with real data
- [ ] All tests still passing
- [ ] Code committed and pushed

---

## 💡 Quick Commands

```bash
# Check what's uncommitted
git status

# Run tests
composer test

# Start browser testing
docker-compose up -d
# → Visit http://localhost:8080

# Check WordPress logs
docker logs wordpress | grep "DNA FILTER"
docker logs wordpress | grep "EXECUTING QUERY"

# Check database
docker exec -it mysql mysql -u wordpress -pwordpress wordpress
SELECT COUNT(*) FROM wp_inat_observations WHERE id IN (SELECT observation_id FROM wp_inat_observation_fields WHERE name LIKE 'DNA%');

# Commit workflow
git add .
git commit -m "Fix: Dropdown persistence across filter selections"
git push origin main
```

---

**Current Status**: 🚨 Dropdown regression blocking multiple filter selection
**Next Action**: Read TODO-BUG-002, browser test, fix dropdown persistence
**Owner**: Claude Code Session
**Priority**: CRITICAL - User experience severely degraded

---

**Remember**:
1. Autocomplete caching is working - don't break it!
2. IN clause query construction is working - don't break it!
3. The regression is likely in the DOM manipulation, not the logic
4. Check browser console first before diving into code
5. All learnings are documented in TODO-BUG-002 to avoid repeating mistakes
