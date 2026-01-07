# TODO-BUG-004: Array Filter Query Construction

**Created:** 2026-01-07
**Status:** 🚨 CRITICAL
**Priority:** URGENT
**Related:** TODO-BUG-002 (Dropdown Selector), TODO-003 (DNA Filter Debug)

---

## Problem Statement

Selecting filter items from the dropdown triggers unwanted autocomplete API calls and uses incorrect query construction for array filters.

**Symptoms:**
1. Selecting dropdown item triggers autocomplete endpoint reload (should NOT happen)
2. URL parameter format: `species=["AMANITA"]` (JSON array)
3. API expects: `species=AMANITA` (single value) ✅ Works
4. API fails with: `species=["AMANITA","CHANTERELLE"]` ❌ Broken
5. Current query uses OR conditions instead of IN clause

**Example URLs:**
```
Working:    species=AMANITA
Broken:     species=["AMANITA"]
Very Broken: species=["AMANITA","CHANTERELLE"]
```

---

## Root Cause

### Issue 1: Autocomplete API Reload on Selection

When a dropdown item is selected, the autocomplete endpoint is being called again unnecessarily. The autocomplete data should be loaded ONCE on page load and cached in JavaScript.

**Current Behavior:**
```javascript
// Selecting item triggers:
// → AJAX call to autocomplete endpoint
// → Re-fetches species/location lists (wasteful!)
```

**Expected Behavior:**
```javascript
// Autocomplete data loaded once:
fetch(ajaxUrl + '?action=inat_obs_autocomplete&field=species')
  .then(data => cachedSpecies = data)  // Cache in memory

// Selection uses cached data:
item.addEventListener('click', () => {
  // No API call! Just use cached data
  currentFilters.species.push(value);
  fetchObservations();  // Only this should trigger API call
});
```

### Issue 2: Query Construction with Arrays

**Current Code** (rest.php lines 108-132):
```php
foreach ($species_filter as $species) {
    $normalized = inat_obs_normalize_filter_value($species);
    if ($normalized === 'UNKNOWN SPECIES') {
        $species_conditions[] = "(species_guess = '' OR species_guess IS NULL)";
    } else {
        $species_conditions[] = 'UPPER(species_guess) = %s';
        $prepare_args[] = $normalized;
    }
}
$where_clauses[] = '(' . implode(' OR ', $species_conditions) . ')';
```

**Generated SQL:**
```sql
WHERE (UPPER(species_guess) = 'AMANITA' OR UPPER(species_guess) = 'CHANTERELLE')
```

**Problem:** This works, but is verbose and doesn't scale. Should use IN clause.

**Desired SQL:**
```sql
WHERE UPPER(species_guess) IN ('AMANITA', 'CHANTERELLE')
```

---

## Fix Strategy

### Part 1: Prevent Autocomplete Reload ✅ (Fix in main.js)

**File:** `assets/js/main.js`

**Change:** Load autocomplete data once, cache in closure variables:

```javascript
// Load autocomplete data ONCE
let cachedSpecies = null;
let cachedLocations = null;

Promise.all([
  fetch(ajaxUrl + '?action=inat_obs_autocomplete&field=species&nonce=' + nonce)
    .then(r => r.json())
    .then(res => cachedSpecies = res.data.suggestions),
  fetch(ajaxUrl + '?action=inat_obs_autocomplete&field=location&nonce=' + nonce)
    .then(r => r.json())
    .then(res => cachedLocations = res.data.suggestions)
])
.then(() => {
  // Autocomplete ready, no more API calls needed
  console.log('[iNat] Autocomplete loaded:', cachedSpecies.length, 'species,', cachedLocations.length, 'locations');
});

// Click handler NEVER calls autocomplete endpoint
item.addEventListener('click', function() {
  // Just use cached data
  currentFilters.species.push(value);
  fetchObservations();  // Only this API call
});
```

### Part 2: Fix Query Construction with IN Clause

**File:** `includes/rest.php`

**Replace Species Filter** (lines 108-132):

```php
if (!empty($species_filter)) {
    // Multi-select species filter with normalized matching
    // TODO-BUG-004: Use IN clause for array filters
    $normalized_species = [];
    $has_unknown = false;

    foreach ($species_filter as $species) {
        $normalized = inat_obs_normalize_filter_value($species);

        if ($normalized === 'UNKNOWN SPECIES') {
            $has_unknown = true;
        } else {
            $normalized_species[] = $normalized;
        }
    }

    $species_conditions = [];

    // Add IN clause for known species
    if (!empty($normalized_species)) {
        $placeholders = implode(', ', array_fill(0, count($normalized_species), '%s'));
        $species_conditions[] = "UPPER(species_guess) IN ($placeholders)";
        foreach ($normalized_species as $species) {
            $prepare_args[] = $species;
        }
    }

    // Add NULL check for "Unknown Species"
    if ($has_unknown) {
        $species_conditions[] = "(species_guess = '' OR species_guess IS NULL)";
    }

    if (!empty($species_conditions)) {
        $where_clauses[] = '(' . implode(' OR ', $species_conditions) . ')';
    }
}
```

**Replace Location Filter** (lines 134-151):

```php
if (!empty($place_filter)) {
    // Multi-select location filter with normalized matching
    // TODO-BUG-004: Use IN clause for array filters
    $normalized_locations = [];

    foreach ($place_filter as $place) {
        $normalized = inat_obs_normalize_filter_value($place);
        $normalized_locations[] = $normalized;
    }

    if (!empty($normalized_locations)) {
        $placeholders = implode(', ', array_fill(0, count($normalized_locations), '%s'));
        $where_clauses[] = "UPPER(place_guess) IN ($placeholders)";

        foreach ($normalized_locations as $location) {
            $prepare_args[] = $location;
        }
    }
}
```

**Final Query Logic:**
```sql
WHERE
    -- DNA filter (if checked)
    id IN (SELECT DISTINCT observation_id FROM observation_fields WHERE name LIKE 'DNA%')
AND
    -- Species filter (if present)
    UPPER(species_guess) IN ('AMANITA', 'CHANTERELLE')
AND
    -- Location filter (if present)
    UPPER(place_guess) IN ('SEATTLE', 'PORTLAND')
```

---

## Expected Behavior

### Single Filter
```
URL: species=["AMANITA"]
SQL: WHERE UPPER(species_guess) IN ('AMANITA')
Result: All Amanita observations
```

### Multiple Filters (Same Type)
```
URL: species=["AMANITA","CHANTERELLE"]
SQL: WHERE UPPER(species_guess) IN ('AMANITA', 'CHANTERELLE')
Result: All Amanita OR Chanterelle observations
```

### Combined Filters
```
URL: species=["AMANITA"]&location=["SEATTLE","PORTLAND"]&has_dna=1
SQL: WHERE
  id IN (SELECT DISTINCT observation_id FROM observation_fields WHERE name LIKE 'DNA%')
  AND UPPER(species_guess) IN ('AMANITA')
  AND UPPER(place_guess) IN ('SEATTLE', 'PORTLAND')
Result: Amanita observations in Seattle OR Portland with DNA fields
```

### Unknown Species
```
URL: species=["UNKNOWN SPECIES","AMANITA"]
SQL: WHERE (
  UPPER(species_guess) IN ('AMANITA')
  OR (species_guess = '' OR species_guess IS NULL)
)
Result: Amanita observations + observations with no species identified
```

---

## Testing Checklist

### Autocomplete Cache
- [ ] Autocomplete data loaded once on page load
- [ ] No autocomplete API calls when selecting dropdown items
- [ ] Only fetchObservations() called when filter added

### Single Value Filters
- [ ] `species=["AMANITA"]` returns Amanita observations
- [ ] `location=["SEATTLE"]` returns Seattle observations
- [ ] `has_dna=1` returns only DNA observations

### Multi-Value Filters
- [ ] `species=["AMANITA","CHANTERELLE"]` returns both
- [ ] `location=["SEATTLE","PORTLAND"]` returns both
- [ ] Normalized values work (MONTREAL = Montréal)

### Combined Filters
- [ ] Species + Location works (AND logic)
- [ ] Species + DNA works
- [ ] Location + DNA works
- [ ] Species + Location + DNA works (all three)

### Edge Cases
- [ ] "Unknown Species" + known species works
- [ ] Empty filter arrays ignored
- [ ] Special characters in names (test after basic fix)

---

## Implementation Steps

1. ✅ Create this TODO file
2. ⏳ Fix query construction in rest.php (use IN clause)
3. ⏳ Prevent autocomplete reload in main.js
4. ⏳ Test single value filters
5. ⏳ Test multi-value filters
6. ⏳ Test combined filters
7. ⏳ Verify no autocomplete API calls on selection
8. ⏳ Commit and push fix

---

## Notes

**User Quote:**
> "This should at least work transparently for names without special characters. We'll look at those afterwards"

**Translation:** Focus on getting IN clause working for basic alphanumeric names first. Special character handling (accents, apostrophes, etc.) can be addressed in a follow-up fix.

**Performance:** IN clause is more efficient than multiple OR conditions, especially with database indexes.

---

## Related Issues

- **TODO-BUG-002**: Dropdown selector fix (normalized values)
- **TODO-003**: DNA filter debug logging
- **TODO-QA-next-steps**: Browser testing plan

---

## Success Criteria

1. ✅ Autocomplete loaded once, no reload on selection
2. ✅ Single value filters work: `species=["AMANITA"]`
3. ✅ Multi-value filters work: `species=["AMANITA","CHANTERELLE"]`
4. ✅ Combined filters work: `species + location + DNA`
5. ✅ SQL uses IN clause for array filters
6. ✅ All unit tests pass
7. ✅ Browser testing confirms correct filtering
