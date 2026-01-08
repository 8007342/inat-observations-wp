# TODO-BUG-002: Dropdown Selector Broken (Autocomplete Filter)

**Created:** 2026-01-07
**Status:** 🚨 CRITICAL
**Priority:** URGENT
**Severity:** Complete Feature Failure

---

## Problem Statement

The unified search dropdown (species/location autocomplete) is completely broken. Users cannot select items from the dropdown, making the filter system unusable.

**Symptoms:**
- Dropdown may not populate correctly
- Click events not working on dropdown items
- Selected values not being added to filter chips
- Value matching/cleanup inconsistent between frontend and backend

---

## Root Cause Analysis

**Value Normalization Mismatch:**
1. **Frontend** builds dropdown with values that may include:
   - Mixed case (wEiRd CaSe)
   - Accents (Montréal)
   - Extra whitespace
   - Special characters

2. **Backend** expects UPPERCASE cleaned values for SQL queries

3. **Mismatch** occurs when:
   - Dropdown value: "Montréal, QC"
   - Query expects: "MONTREAL, QC"
   - No matches found

**Index Consistency:**
- Autocomplete cache uses one normalization method
- Query building uses different normalization
- Dropdown value construction uses yet another method
- All three MUST use identical cleanup logic

---

## Multi-Step Fix Plan

### Phase 1: Integration Tests (FIRST PRIORITY) ✅

**File:** `tests/integration/test-filter-combinations.php`

**Test Coverage:**
1. ✅ Single species filter
2. ✅ Single location filter
3. ✅ Multiple species (collision test: multiple Amanita)
4. ✅ Multiple locations
5. ✅ Species + Location combined
6. ✅ Species + DNA filter
7. ✅ Location + DNA filter
8. ✅ Species + Location + DNA (triple filter)
9. ✅ Unknown species filter
10. ✅ Case-insensitive matching (SPECIES vs species vs SpEcIeS)
11. ✅ Accent handling (Montréal = MONTREAL)
12. ✅ Whitespace trimming
13. ✅ Empty filter (all observations)

**Status:** ✅ TESTS WRITTEN - Need WordPress test environment

### Phase 2: Remove Broken Dropdown (CLEAN SLATE) ✅

**Actions:**
- ✅ Remove dropdown HTML generation from main.js
- ✅ Remove dropdown event handlers
- ✅ Remove attachCombinedAutocomplete() function
- ✅ Keep backend autocomplete endpoint (working correctly)
- ✅ Annotate/disable dropdown-related unit tests
- ✅ Commit: "Remove broken dropdown selector for clean reimplementation"
- ✅ Push to origin

**Why:** Clean slate prevents mixing old broken code with new implementation.

### Phase 3: Unified Cleanup Function (CRITICAL)

**File:** `includes/helpers.php` (create if needed)

**Function:** `inat_obs_normalize_filter_value($value)`

```php
/**
 * Normalize filter value for consistent matching.
 *
 * CRITICAL: This function MUST be used in ALL places:
 * - autocomplete.php: Building cache
 * - rest.php: Building SQL queries
 * - main.js: Building dropdown option values
 *
 * @param string $value Raw value (species name, location, etc.)
 * @return string Normalized value (UPPERCASE, no accents, trimmed)
 */
function inat_obs_normalize_filter_value($value) {
    // Remove accents (Montréal → Montreal)
    $value = remove_accents($value);

    // Uppercase
    $value = strtoupper($value);

    // Trim whitespace
    $value = trim($value);

    // Normalize multiple spaces to single space
    $value = preg_replace('/\s+/', ' ', $value);

    return $value;
}
```

**Integration Points:**
1. **autocomplete.php** - Use when building suggestion arrays
2. **rest.php** - Use when parsing filter parameters
3. **main.js** - Use JavaScript equivalent when building dropdown values

### Phase 4: Rewire Dropdown from Scratch

**File:** `assets/js/main.js`

**JavaScript Cleanup Function:**
```javascript
// Normalize filter value (matches PHP inat_obs_normalize_filter_value)
function normalizeFilterValue(value) {
  // Remove accents
  value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

  // Uppercase
  value = value.toUpperCase();

  // Trim
  value = value.trim();

  // Normalize whitespace
  value = value.replace(/\s+/g, ' ');

  return value;
}
```

**Dropdown Structure:**
```html
<div class="inat-dropdown-item"
     data-value="MONTREAL QC"           <!-- UPPERCASE cleaned -->
     data-display="Montréal, QC"         <!-- Original for display -->
     data-field="location">              <!-- species or location -->
  📍 Montréal, QC
</div>
```

**Event Handler:**
```javascript
dropdownItem.addEventListener('click', function() {
  const field = this.getAttribute('data-field');
  const value = this.getAttribute('data-value');  // Already normalized!
  const display = this.getAttribute('data-display');

  // Add to filter (value is already UPPERCASE/cleaned)
  if (!currentFilters[field].includes(value)) {
    currentFilters[field].push(value);
    fetchObservations();  // Will send UPPERCASE value to backend
  }
});
```

### Phase 5: Backend Query Consistency

**File:** `includes/rest.php` and `includes/shortcode.php`

**Ensure consistent normalization:**
```php
// BEFORE (WRONG - inconsistent):
$species = json_decode($request->get_param('species'));
$where_parts[] = "UPPER(species_guess) = '" . esc_sql($species[0]) . "'";

// AFTER (CORRECT - normalized):
$species = json_decode($request->get_param('species'));
$normalized = inat_obs_normalize_filter_value($species[0]);
$where_parts[] = "UPPER(species_guess) = %s";
$params[] = $normalized;
```

### Phase 6: Autocomplete Cache Consistency

**File:** `includes/autocomplete.php`

**Ensure suggestions use normalized values:**
```php
$suggestions = [];
foreach ($results as $value) {
    $normalized = inat_obs_normalize_filter_value($value);
    $suggestions[] = [
        'value' => $normalized,      // UPPERCASE cleaned
        'display' => $value,         // Original for display
        'count' => $counts[$value]   // Optional: how many observations
    ];
}
```

---

## Verification Checklist

### Dropdown Functionality
- [ ] Dropdown populates on typing
- [ ] Click selects item
- [ ] Selected item creates filter chip
- [ ] Filter chip has correct emoji (📋 species, 📍 location)
- [ ] Chip X button removes filter
- [ ] Observations update when filter applied

### Value Consistency
- [ ] Dropdown data-value is UPPERCASE
- [ ] Query receives UPPERCASE value
- [ ] SQL query uses UPPER(column) comparison
- [ ] Accents are removed (Montréal → MONTREAL)
- [ ] Whitespace is trimmed
- [ ] Case-insensitive matching works

### Edge Cases
- [ ] Multiple filters (species + location + DNA)
- [ ] Collision names (multiple Amanita species)
- [ ] Empty/null values
- [ ] Special characters (é, ñ, ü, etc.)
- [ ] Mobile browsers (iOS Safari, Chrome Android)

### Integration Tests
- [ ] All 13 filter tests pass
- [ ] Autocomplete tests pass
- [ ] REST endpoint tests pass

---

## Current Status

**Phase 1: Integration Tests** ✅ COMPLETE
- Created `tests/integration/test-filter-combinations.php`
- 15 comprehensive test cases
- Covers all filter combinations and edge cases

**Phase 2: Remove Dropdown** ✅ COMPLETE
- Removed broken dropdown code
- Committed and pushed (08ba99c)

**Phase 3: Unified Cleanup** ✅ COMPLETE
- Created `includes/helpers.php` with `inat_obs_normalize_filter_value()`
- Updated `rest.php` to use normalization
- Updated `autocomplete.php` cache v3 (species) and v2 (locations)
- All 60 unit tests passing
- Committed and pushed (c4eade8)

**Phase 4: Rewire Dropdown** ✅ COMPLETE
- Added `normalizeFilterValue()` JavaScript function
- Rebuilt `attachCombinedAutocomplete()` from scratch
- Dropdown uses normalized values in data attributes
- Scope issue fixed (moved inline to access currentFilters)
- Overflow CSS issue fixed (dropdown displays correctly)
- Committed and pushed (f735052, 9cd8094)

**Phase 5: Backend Consistency** ✅ COMPLETE
- All queries use `inat_obs_normalize_filter_value()`
- Species/location filters normalized consistently
- Completed in Phase 3

**Phase 6: Autocomplete Cache** ✅ COMPLETE
- Cache includes `normalized_value` field
- Species cache v3, Locations cache v2
- Cache invalidation updated
- Completed in Phase 3

**Phase 7: UI Layout Unification** ✅ COMPLETE (PR #2)
- Moved DNA checkbox + search to controls bar (same level as pagination)
- Added First/Prev/Next/Last pagination buttons
- Moved filter chips below controls
- Cleaner visual hierarchy
- Branch: `fix/unified-controls-layout`

---

## Related Files

### Backend
- `includes/rest.php` - REST endpoint (query building)
- `includes/shortcode.php` - AJAX endpoint (query building)
- `includes/autocomplete.php` - Autocomplete cache
- `includes/helpers.php` - Shared normalization function (NEW)

### Frontend
- `assets/js/main.js` - Dropdown UI and event handlers

### Tests
- `tests/integration/test-filter-combinations.php` - Filter integration tests
- `tests/unit/AutocompleteTest.php` - Autocomplete unit tests
- `tests/unit/RestTest.php` - REST endpoint unit tests

---

## Success Criteria

1. **User can type in unified search** → Dropdown populates
2. **User clicks dropdown item** → Filter chip appears
3. **Observations update** → Only matching observations shown
4. **All 13 integration tests pass** → Value normalization is consistent
5. **Works on mobile** → iOS Safari and Chrome Android

---

## Lessons Learned

**NEVER mix normalization methods.**

1. Define ONE canonical cleanup function
2. Use it EVERYWHERE (frontend, backend, cache, queries)
3. Write integration tests FIRST to catch inconsistencies
4. Test with real-world edge cases (accents, mixed case, whitespace)

**When in doubt, start from scratch.**

Debugging broken autocomplete is harder than rewriting it cleanly with proper normalization from the start.

---

## Next Steps

1. ✅ Write filter integration tests (DONE)
2. ✅ Remove broken dropdown (DONE)
3. ⏳ Create unified cleanup function
4. Update all integration points
5. Rewire dropdown from scratch
6. Run all tests
7. Test in browser with real data
8. Close bug

**ETA:** < 2 hours (systematic approach with tests)

---

## 🚨 NEW REGRESSION: Dropdown Won't Display After First Selection

**Date**: 2026-01-07 (after commit ab524a9)
**Status**: 🔴 EXTRA BORKED
**Impact**: CRITICAL - User experience severely degraded

### Symptoms

1. **First Selection Works**:
   - User types in search input
   - Dropdown displays with autocomplete suggestions
   - User clicks an item
   - Item is added to filter chips
   - Filter query executes correctly

2. **Subsequent Searches Broken**:
   - User types in search input again
   - Dropdown DOES NOT appear
   - No autocomplete suggestions shown
   - Input behaves like plain text field
   - Page reload required to restore functionality

### Root Cause Hypothesis

**The Problem**: `initializeAutocomplete()` runs once after cache loads, but `fetchObservations()` rebuilds the HTML, replacing the search input element.

**Evidence**:
```javascript
// This runs ONCE (main.js:928)
function initializeAutocomplete() {
  const unifiedSearch = document.getElementById('inat-unified-search');
  // ... creates wrapper, dropdown, attaches event listeners
}

// This runs EVERY TIME filters change (main.js:68-533)
function fetchObservations() {
  // ...
  listContainer.innerHTML = controlsHtml + filterChipsHtml + (noResultsMessage || html);
  // ^^^ THIS REPLACES THE SEARCH INPUT!
}
```

**What Happens**:
1. Page loads → `initializeAutocomplete()` runs → wraps input, attaches listeners
2. User selects item → `fetchObservations()` runs → `innerHTML` replaces ALL content
3. New input element created (same ID), but:
   - Not wrapped by autocomplete wrapper
   - No event listeners attached
   - Dropdown container doesn't exist
4. User types → nothing happens (no `input` event listener)

### Investigation Checklist

**Before Fixing, Verify**:
- [ ] Check if `unifiedSearch` element still exists after render
- [ ] Check if wrapper element persists
- [ ] Check if dropdown container persists
- [ ] Check browser console for "element not found" errors
- [ ] Check if event listeners are still attached (use Event Listener Inspector)

**Root Cause Confirmation**:
- [ ] Does the input element ID change? (shouldn't)
- [ ] Is the input element inside `listContainer`? (it is!)
- [ ] Does `innerHTML =` destroy child elements? (YES!)
- [ ] Are new elements created with same ID? (yes)
- [ ] Do event listeners transfer to new elements? (NO!)

### Potential Solutions

**Option A: Move Search Input Outside Re-rendered Container** ✅ RECOMMENDED
```javascript
// Keep search input outside of listContainer
// Only re-render observation results, not filters
```

**Benefits**:
- Search input persists across renders
- Event listeners never lost
- Dropdown container persists
- Cleanest solution

**Implementation**:
1. Split HTML into two containers: `filtersContainer` + `resultsContainer`
2. Only update `resultsContainer.innerHTML` in `fetchObservations()`
3. Never touch `filtersContainer` after initial render

**Option B: Re-initialize Autocomplete After Each Render** ❌ BAD
```javascript
function fetchObservations() {
  // ...
  listContainer.innerHTML = controlsHtml + ...;
  
  // Re-attach autocomplete (defeats caching purpose!)
  initializeAutocomplete();
}
```

**Problems**:
- Defeats the purpose of caching
- Inefficient (re-attaches listeners every time)
- Race conditions with async cache loading

**Option C: Use Event Delegation on Parent** ⚠️ COMPLEX
```javascript
// Attach listeners to parent that never changes
document.addEventListener('input', function(e) {
  if (e.target.id === 'inat-unified-search') {
    // Handle autocomplete
  }
});
```

**Problems**:
- Harder to maintain
- Still need to preserve dropdown container
- Doesn't solve wrapper/dropdown persistence

### Implementation Plan (Recommended: Option A)

**Step 1: Separate Containers**
```javascript
// Create persistent filter container (NEVER re-rendered)
const filterBarContainer = document.createElement('div');
filterBarContainer.id = 'inat-filter-bar-persistent';

// Create results container (re-rendered on every fetch)
const resultsContainer = document.createElement('div');
resultsContainer.id = 'inat-results';

listContainer.appendChild(filterBarContainer);
listContainer.appendChild(resultsContainer);
```

**Step 2: Move Filter HTML to Persistent Container**
```javascript
// Build filter bar HTML (DNA checkbox, search input, chips)
filterBarContainer.innerHTML = filterBarHtml;

// Initialize autocomplete ONCE (element never replaced)
initializeAutocomplete();
```

**Step 3: Update Only Results Container**
```javascript
function fetchObservations() {
  // ...
  
  // Only update results, NOT filters
  resultsContainer.innerHTML = controlsHtml + observationsHtml;
  
  // Re-attach event handlers for NEW elements (pagination, etc.)
  attachPaginationHandlers();
  attachViewToggleHandlers();
}
```

**Step 4: Update Chip Management**
```javascript
// Chips are in persistent container, but need dynamic updates
function updateFilterChips() {
  const chipsContainer = document.getElementById('filter-chips-container');
  chipsContainer.innerHTML = generateChipsHtml();
  attachChipRemoveHandlers();
}
```

### Learnings to Document

**What We Learned (the Hard Way)**:

1. **innerHTML Destroys Everything**
   - Using `innerHTML =` destroys all child elements
   - Event listeners are NOT preserved
   - New elements with same ID are NOT the same objects

2. **Event Listener Lifecycle**
   - Listeners attached to elements, not IDs
   - When element destroyed, listeners destroyed
   - Must re-attach or use delegation

3. **DOM Persistence Requirements**
   - Elements with listeners must never be replaced
   - Use separate containers for static vs dynamic content
   - Static: filter bar, search input, DNA checkbox
   - Dynamic: observation results, pagination controls

4. **Autocomplete Architecture**
   - Initialize once after cache loads ✅
   - Never re-initialize (expensive, defeats caching) ✅
   - Ensure target element persists ❌ FAILED HERE

5. **Testing Gap**
   - Unit tests don't catch DOM persistence bugs
   - Need integration tests with real browser
   - Manual testing caught this regression

### Code Locations

**Files to Modify**:
- `assets/js/main.js:68-533` - `fetchObservations()` function
- `assets/js/main.js:766-928` - `initializeAutocomplete()` function
- `assets/js/main.js:145-230` - HTML generation (split into filter/results)

**Tests to Add**:
- Integration test: Select multiple filters without page reload
- Integration test: Verify dropdown appears on second search
- Integration test: Verify event listeners persist

### Commit Strategy

**Commit 1**: Refactor HTML generation into separate containers
```
Refactor: Split filter bar and results into separate containers

- Create persistent filter bar container
- Create re-renderable results container
- Move search input to persistent container
- Prepare for autocomplete persistence fix
```

**Commit 2**: Fix autocomplete persistence
```
Fix: Dropdown persists across filter selections

- Initialize autocomplete on persistent element
- Only re-render results container, not filters
- Event listeners preserved across renders
- Fixes regression from commit ab524a9

Closes: TODO-BUG-002 (EXTRA BORKED status)
```

**Commit 3**: Add integration tests
```
Test: Add dropdown persistence integration tests

- Test multiple filter selections
- Test dropdown displays on second search
- Test event listener persistence
```

### Success Criteria

- [ ] Dropdown displays on every search (not just first)
- [ ] Multiple filters can be selected without page reload
- [ ] Event listeners persist across renders
- [ ] No browser console errors
- [ ] All existing tests still pass
- [ ] New integration tests pass
- [ ] Autocomplete caching still works (no API reload)
- [ ] IN clause query construction still works

### Priority

**CRITICAL** - User cannot use the filter system without page reload after each selection. This makes the plugin nearly unusable for multi-filter scenarios.

**Estimated Effort**: 2-3 hours
- 1 hour: Refactor HTML generation
- 30 min: Fix autocomplete persistence
- 30 min: Testing
- 30 min: Documentation

**Risk**: Low - Clean refactoring, well-understood problem

---

## Summary of All Fixes

### Fix 1: Dropdown Broken (Original) ✅ FIXED
- **Commit**: Multiple commits during initial implementation
- **Fix**: Unified normalization, IN clause queries
- **Status**: Working

### Fix 2: Autocomplete Reload on Selection ✅ FIXED
- **Commit**: ab524a9
- **Fix**: Initialize once, use cached data
- **Status**: Working
- **Side Effect**: Introduced regression below

### Fix 3: Dropdown Won't Display Again 🔴 NEW REGRESSION
- **Caused By**: Commit ab524a9 (autocomplete caching)
- **Root Cause**: `innerHTML` replaces search input, loses listeners
- **Fix**: Move search input to persistent container
- **Status**: PENDING
- **Priority**: CRITICAL

---

**Next Action**: Implement Option A (persistent containers) to fix dropdown display regression
