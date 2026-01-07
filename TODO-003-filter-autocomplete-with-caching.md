# TODO-003: Filter Autocomplete with Server-Side Caching

**Priority:** 🟡 Medium (UX Enhancement)
**Status:** 🔴 Not Started
**Effort:** ~4-6 hours
**Dependencies:** Working filter bar (✅ DONE)

---

## Overview

Add autocomplete dropdowns to filter inputs that suggest popular species and locations as users type. Backend uses **cached DISTINCT queries** to avoid expensive database scans on every keystroke.

**User Experience:**
```
Filter: [Ama▾____________] [Cali▾____________] [Apply] [Clear]
        ↓ Shows dropdown
        ┌─────────────────┐
        │ Amanita muscaria│ ← Suggestions from cache
        │ Amanita phalloi.│
        │ Amanita virosa  │
        └─────────────────┘
```

**Performance Goal:**
- Initial dropdown load: <100ms (from cache)
- Typing filter: <10ms (client-side only)
- Cache refresh: Every 1 hour (or on observation refresh)

---

## Why Caching is CRITICAL

**Problem:**
```sql
-- WITHOUT CACHE - runs on every page load!
SELECT DISTINCT species_guess FROM wp_inat_observations
ORDER BY species_guess ASC;
-- 2000 rows → ~50-100ms

SELECT DISTINCT place_guess FROM wp_inat_observations
ORDER BY place_guess ASC;
-- 2000 rows → ~50-100ms

TOTAL: 100-200ms per page load ❌ UNACCEPTABLE
```

**With 10,000+ observations:**
- DISTINCT query: ~500ms-1s ❌ **TABLE SCAN**
- Blocks page rendering
- Kills server under load

**Solution:**
```sql
-- WITH CACHE - runs once per hour
Cache hit: ~1ms ✅ FAST
Cache miss: ~100ms, then cached for 1 hour
```

---

## WordPress Caching Options

### Option 1: Transients API (RECOMMENDED)
**Built into WordPress**, no plugins required.

```php
// Set cache (1 hour expiration)
set_transient('inat_obs_species_list', $species_array, HOUR_IN_SECONDS);

// Get cache
$species = get_transient('inat_obs_species_list');
if ($species === false) {
    // Cache miss - regenerate
    $species = inat_obs_fetch_distinct_species();
    set_transient('inat_obs_species_list', $species, HOUR_IN_SECONDS);
}
```

**Pros:**
- ✅ Built-in, no setup
- ✅ Automatic expiration
- ✅ Survives across requests
- ✅ Works with persistent object cache (Redis/Memcached) if available

**Cons:**
- ❌ Stored in database (wp_options table) - slower than memory
- ❌ But still 1000x faster than DISTINCT query!

### Option 2: Object Cache (Advanced)
Requires persistent cache backend (Redis, Memcached, APCu).

```php
// Set cache (1 hour)
wp_cache_set('inat_obs_species_list', $species_array, 'inat_autocomplete', HOUR_IN_SECONDS);

// Get cache
$species = wp_cache_get('inat_obs_species_list', 'inat_autocomplete');
```

**Pros:**
- ✅ Much faster (in-memory)
- ✅ Scales to millions of observations

**Cons:**
- ❌ Requires Redis/Memcached setup
- ❌ Overkill for <10k observations

**Recommendation:** Start with Transients (Option 1), upgrade to Object Cache if needed.

---

## Implementation Plan

### Phase 1: Backend - Cached Autocomplete Endpoint

**File:** `includes/autocomplete.php` (NEW)

```php
<?php
// Autocomplete data provider with caching
if (!defined('ABSPATH')) exit;

/**
 * Get distinct species list (cached).
 *
 * CRITICAL: This query is EXPENSIVE (DISTINCT + ORDER BY on large dataset).
 * Result is cached for 1 hour to avoid repeated table scans.
 * Cache is invalidated when observations are refreshed.
 *
 * @return array List of species names
 */
function inat_obs_get_species_autocomplete() {
    // Try cache first (Tlatoani's performance directive)
    $cache_key = 'inat_obs_species_autocomplete_v1';
    $species = get_transient($cache_key);

    if ($species !== false) {
        // Cache hit - return immediately
        error_log('iNat Autocomplete: Species list from cache (' . count($species) . ' items)');
        return $species;
    }

    // Cache miss - run expensive query
    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';

    $start_time = microtime(true);

    // Query distinct species (EXPENSIVE!)
    $results = $wpdb->get_col("
        SELECT DISTINCT species_guess
        FROM $table
        WHERE species_guess != ''
        ORDER BY species_guess ASC
        LIMIT 1000
    ");

    $query_time = microtime(true) - $start_time;

    // Cache for 1 hour
    set_transient($cache_key, $results, HOUR_IN_SECONDS);

    error_log(sprintf(
        'iNat Autocomplete: Generated species list (%d items, %.2fms) - cached for 1 hour',
        count($results),
        $query_time * 1000
    ));

    return $results;
}

/**
 * Get distinct location list (cached).
 */
function inat_obs_get_location_autocomplete() {
    $cache_key = 'inat_obs_location_autocomplete_v1';
    $locations = get_transient($cache_key);

    if ($locations !== false) {
        error_log('iNat Autocomplete: Location list from cache (' . count($locations) . ' items)');
        return $locations;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';

    $start_time = microtime(true);

    $results = $wpdb->get_col("
        SELECT DISTINCT place_guess
        FROM $table
        WHERE place_guess != ''
        ORDER BY place_guess ASC
        LIMIT 1000
    ");

    $query_time = microtime(true) - $start_time;

    set_transient($cache_key, $results, HOUR_IN_SECONDS);

    error_log(sprintf(
        'iNat Autocomplete: Generated location list (%d items, %.2fms) - cached for 1 hour',
        count($results),
        $query_time * 1000
    ));

    return $results;
}

/**
 * Invalidate autocomplete caches.
 * Called after observation refresh.
 */
function inat_obs_invalidate_autocomplete_cache() {
    delete_transient('inat_obs_species_autocomplete_v1');
    delete_transient('inat_obs_location_autocomplete_v1');
    error_log('iNat Autocomplete: Cache invalidated (will regenerate on next request)');
}

// AJAX endpoint for autocomplete data
add_action('wp_ajax_inat_obs_autocomplete', 'inat_obs_autocomplete_ajax');
add_action('wp_ajax_nopriv_inat_obs_autocomplete', 'inat_obs_autocomplete_ajax');

function inat_obs_autocomplete_ajax() {
    // Verify nonce
    if (!check_ajax_referer('inat_obs_fetch', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
        return;
    }

    $field = isset($_GET['field']) ? sanitize_text_field($_GET['field']) : '';

    if ($field === 'species') {
        $data = inat_obs_get_species_autocomplete();
    } elseif ($field === 'location') {
        $data = inat_obs_get_location_autocomplete();
    } else {
        wp_send_json_error(['message' => 'Invalid field'], 400);
        return;
    }

    wp_send_json_success(['suggestions' => $data]);
}
```

**Add to `includes/init.php`:**
```php
require_once plugin_dir_path(__DIR__) . 'includes/autocomplete.php';
```

**Invalidate cache after refresh (in `includes/init.php`):**
```php
function inat_obs_refresh_job() {
    // ... existing refresh logic ...

    // Update WordPress options
    update_option('inat_obs_last_refresh', current_time('mysql'));
    update_option('inat_obs_last_refresh_count', $total_fetched);

    // NEW: Invalidate autocomplete caches
    inat_obs_invalidate_autocomplete_cache();

    // ... rest of function ...
}
```

---

### Phase 2: Frontend - Autocomplete Dropdown

**File:** `assets/js/main.js`

**Add state:**
```javascript
// Autocomplete state
let autocompleteCache = {
  species: null,
  location: null
};
```

**Fetch autocomplete data on page load:**
```javascript
// In DOMContentLoaded handler, before fetchObservations()

// Load autocomplete suggestions
loadAutocomplete('species');
loadAutocomplete('location');

function loadAutocomplete(field) {
  const url = new URL(inatObsSettings.ajaxUrl);
  url.searchParams.set('action', 'inat_obs_autocomplete');
  url.searchParams.set('nonce', inatObsSettings.nonce);
  url.searchParams.set('field', field);

  fetch(url)
    .then(r => r.json())
    .then(j => {
      if (j.success) {
        autocompleteCache[field] = j.data.suggestions;
        console.log('[iNat] Loaded ' + field + ' autocomplete: ' + autocompleteCache[field].length + ' items');
      }
    })
    .catch(e => {
      console.error('[iNat] Autocomplete fetch failed:', e);
    });
}
```

**Render autocomplete dropdown:**
```javascript
// After rendering filter inputs

// Attach autocomplete to species input
const speciesInput = document.getElementById('inat-filter-species');
if (speciesInput) {
  attachAutocomplete(speciesInput, 'species');
}

const locationInput = document.getElementById('inat-filter-location');
if (locationInput) {
  attachAutocomplete(locationInput, 'location');
}

function attachAutocomplete(input, field) {
  // Create dropdown container
  const dropdown = document.createElement('div');
  dropdown.className = 'inat-autocomplete-dropdown';
  dropdown.style.cssText = `
    position: absolute;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  `;

  // Insert dropdown after input
  input.parentNode.style.position = 'relative';
  input.parentNode.appendChild(dropdown);

  // Handle input events
  input.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();

    if (!query || !autocompleteCache[field]) {
      dropdown.style.display = 'none';
      return;
    }

    // Filter suggestions (client-side - FAST!)
    // Tlatoani's directive: Prefix matching only (uses brain index!)
    const matches = autocompleteCache[field].filter(item =>
      item.toLowerCase().startsWith(query)
    ).slice(0, 10);  // Limit to 10 results

    if (matches.length === 0) {
      dropdown.style.display = 'none';
      return;
    }

    // Render dropdown
    dropdown.innerHTML = '';
    matches.forEach(item => {
      const option = document.createElement('div');
      option.textContent = item;
      option.style.cssText = `
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
      `;
      option.addEventListener('mouseenter', function() {
        this.style.background = '#f0f0f0';
      });
      option.addEventListener('mouseleave', function() {
        this.style.background = 'white';
      });
      option.addEventListener('click', function() {
        input.value = item;
        dropdown.style.display = 'none';
      });
      dropdown.appendChild(option);
    });

    // Position dropdown
    dropdown.style.width = input.offsetWidth + 'px';
    dropdown.style.display = 'block';
  });

  // Hide dropdown when clicking outside
  document.addEventListener('click', function(e) {
    if (e.target !== input && e.target !== dropdown) {
      dropdown.style.display = 'none';
    }
  });

  // Keyboard navigation (future enhancement)
  // - Arrow up/down to navigate
  // - Enter to select
  // - Escape to close
}
```

---

## Performance Benchmarks

**Without Caching:**
- Initial page load: 100-200ms (2 DISTINCT queries)
- 10 page loads: 1000-2000ms total database time ❌
- With 10,000 observations: 500ms-1s per query ❌❌❌

**With Caching:**
- Initial page load (cache miss): 100-200ms (generates cache)
- Subsequent loads (cache hit): <1ms (from transient) ✅
- 10 page loads: ~10ms total ✅
- User typing: <1ms (client-side filter) ✅✅✅

**Savings:** ~99% reduction in database load!

---

## Cache Invalidation Strategy

**When to invalidate:**
1. After observation refresh (`inat_obs_refresh_job()`)
2. After manual "Refresh Now" in admin
3. After 1 hour (automatic expiration)

**When NOT to invalidate:**
- On every page load ❌
- On user filter input ❌
- On pagination ❌

**Cache key versioning:**
- Use `_v1` suffix in cache keys
- Increment version when changing data structure
- Old caches auto-expire after 1 hour

---

## Database Optimization (Optional Future)

If autocomplete gets slow with 100k+ observations, add index:

```sql
-- Prefix index for autocomplete (Tlatoani's teaching)
ALTER TABLE wp_inat_observations
ADD INDEX idx_species_autocomplete (species_guess(50)),
ADD INDEX idx_location_autocomplete (place_guess(50));
```

**Effect:**
- DISTINCT query: 1s → 100ms ⚡
- Still benefits from caching!

---

## Testing Checklist

- [ ] Cache generates on first page load
- [ ] Subsequent loads use cache (<1ms)
- [ ] Cache invalidates after observation refresh
- [ ] Autocomplete dropdown appears when typing
- [ ] Dropdown filters as user types (prefix match)
- [ ] Clicking suggestion fills input
- [ ] Dropdown closes when clicking outside
- [ ] Enter key in input applies filter
- [ ] Works on mobile (touch events)
- [ ] No JavaScript errors in console
- [ ] Check error_log for cache hit/miss messages

---

## Acceptance Criteria

- [ ] ✅ DISTINCT query runs max once per hour (cached)
- [ ] ✅ Autocomplete suggestions load in <100ms
- [ ] ✅ Client-side filtering is instant (<10ms)
- [ ] ✅ Dropdown shows max 10 suggestions
- [ ] ✅ Prefix matching only (no substring search)
- [ ] ✅ Cache invalidates after data refresh
- [ ] ✅ Works for both species and location fields
- [ ] ✅ Mobile-friendly dropdown
- [ ] ✅ Keyboard navigation (arrow keys, Enter, Escape)

---

## Files to Create/Modify

**New Files:**
- [ ] `includes/autocomplete.php` - Cache logic + AJAX endpoint

**Modified Files:**
- [ ] `includes/init.php` - Require autocomplete.php + invalidate cache after refresh
- [ ] `assets/js/main.js` - Autocomplete dropdown rendering
- [ ] `assets/css/main.css` - Autocomplete dropdown styles (optional)

---

## Future Enhancements

- [ ] **Keyboard navigation** - Arrow keys + Enter to select
- [ ] **Fuzzy matching** - Match "aman" → "Amanita" (requires library)
- [ ] **Popular first** - Sort by observation count, not alphabetically
- [ ] **Recent searches** - Remember user's last 5 searches
- [ ] **Multi-select** - Filter by multiple species at once
- [ ] **Debounce typing** - Wait 300ms after typing stops (optimization)

---

## Security Considerations

- [ ] ✅ AJAX endpoint uses nonce verification
- [ ] ✅ Field parameter validated (species|location whitelist)
- [ ] ✅ LIMIT 1000 prevents memory exhaustion
- [ ] ✅ Input value sanitized before filtering
- [ ] ✅ Dropdown values escaped before rendering

---

## Related TODOs

- `TODO-002-dna-metadata-filtering.md` - DNA field autocomplete (future)
- `TODO-QA-001-sanitize-debug-logs.md` - Remove console.log before release

---

**Status:** 🔴 Not started (documented)
**Next Action:** Create `includes/autocomplete.php` with cached DISTINCT queries
**ETA:** 4-6 hours for full implementation with testing
