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
- 13 comprehensive test cases
- Covers all filter combinations and edge cases

**Phase 2: Remove Dropdown** ✅ COMPLETE
- Removed broken dropdown code
- Committed and pushed

**Phase 3: Unified Cleanup** ⏳ IN PROGRESS
- Creating helper function
- Updating all integration points

**Phase 4: Rewire Dropdown** ⏸ PENDING
- Blocked by Phase 3 completion

**Phase 5: Backend Consistency** ⏸ PENDING
- Blocked by Phase 3 completion

**Phase 6: Autocomplete Cache** ⏸ PENDING
- Blocked by Phase 3 completion

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
