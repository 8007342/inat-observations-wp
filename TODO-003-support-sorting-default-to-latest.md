# TODO-003: Support Sorting (Default to Latest)

**Created:** 2026-01-07
**Status:** 🔨 In Progress
**Priority:** MEDIUM
**Target:** 0.2.0 Release

---

## Overview

Add sorting support to observation lists with clickable column headers (list view), sort arrows for ascending/descending, and query parameter support. Default to date descending (most recent first).

---

## Requirements

### 1. Sort Columns
- **Date** (observed_on) - DEFAULT DESC
- **Species** (species_guess) - Alphabetical
- **Location** (place_guess) - Alphabetical
- **Taxon Name** (taxon_name) - Scientific name, alphabetical

### 2. Sort UI (List View)
- Clickable column headers
- Sort arrows: ↑ (ASC) / ↓ (DESC)
- Active column highlighted
- Initial state: Date column with ↓ (most recent first)

### 3. Query Parameter
```
?sort=date&order=desc     (default)
?sort=species&order=asc
?sort=location&order=asc
?sort=taxon&order=asc
```

### 4. API Integration
- REST endpoint accepts `sort` and `order` params
- AJAX endpoint accepts `sort` and `order` params
- Cache keys include sort/order
- Pagination preserved when sorting changes

---

## UI Design

### List View Header (Before)
```
┌─────────┬─────────┬──────────┬──────┬─────────┐
│ Photo   │ Species │ Location │ Date │ Actions │
└─────────┴─────────┴──────────┴──────┴─────────┘
```

### List View Header (After)
```
┌─────────┬───────────────┬─────────────────┬────────────┬─────────┐
│ Photo   │ Species ↕     │ Location ↕      │ Date ↓     │ Actions │
└─────────┴───────────────┴─────────────────┴────────────┴─────────┘
```

**Active Column:** Bold text + colored arrow
**Inactive Columns:** Gray arrows (↕)
**Hover:** Underline, cursor pointer

---

## Implementation Tasks

### 1. Backend (REST API) ✅
- [ ] Add `sort` param validation (date, species, location, taxon)
- [ ] Add `order` param validation (asc, desc)
- [ ] Update SQL ORDER BY clause dynamically
- [ ] Include sort/order in cache key
- [ ] Default to `date DESC` if not specified
- [ ] Sanitize column names (SQL injection prevention)

### 2. Backend (AJAX Endpoint) ✅
- [ ] Add `sort` and `order` params to shortcode.php
- [ ] Update query builder to include ORDER BY
- [ ] Include sort/order in cache key
- [ ] Default to `date DESC`

### 3. Frontend (List View) 🎯
- [ ] Add click handlers to column headers
- [ ] Toggle sort order on same column click (ASC ↔ DESC)
- [ ] Switch to new column on different column click (default ASC)
- [ ] Add arrow indicators (↑ ↓ ↕)
- [ ] Highlight active sort column
- [ ] Update URL with sort/order params
- [ ] Preserve filters when sorting changes

### 4. Frontend (Grid View) 📱
- [ ] Add sort dropdown above grid
- [ ] Options: "Date (Latest)", "Date (Oldest)", "Species A-Z", "Species Z-A", "Location A-Z", "Location Z-A"
- [ ] Apply same query params as list view
- [ ] Responsive: Show on mobile

### 5. Additional Enhancements 🌟
- [ ] Add taxon_name (scientific name) to autocomplete search
- [ ] Shorten date format to YYYY-MM-DD (remove time 00:00:00)
- [ ] Add keyboard shortcuts (Shift+Click for reverse sort)
- [ ] Add sort persistence (localStorage)

---

## SQL Implementation

### Column Mapping
```php
$sort_columns = [
    'date' => 'observed_on',
    'species' => 'species_guess',
    'location' => 'place_guess',
    'taxon' => 'taxon_name'
];

$sort_orders = ['asc', 'desc'];
```

### Safe ORDER BY
```php
$sort = sanitize_text_field($params['sort'] ?? 'date');
$order = sanitize_text_field($params['order'] ?? 'desc');

// Validate against whitelist
$sort_column = $sort_columns[$sort] ?? 'observed_on';
$sort_order = in_array(strtolower($order), $sort_orders) ? strtolower($order) : 'desc';

// Build ORDER BY
$order_by = "$sort_column $sort_order";
$sql = "SELECT * FROM $table WHERE ... ORDER BY $order_by LIMIT %d OFFSET %d";
```

---

## UI Components

### Sort Arrow CSS
```css
.inat-sort-arrow {
  font-size: 12px;
  margin-left: 4px;
  opacity: 0.3;
  transition: opacity 0.2s;
}

.inat-sort-arrow--active {
  opacity: 1;
  color: #2271b1;
  font-weight: bold;
}

.inat-sortable-column {
  cursor: pointer;
  user-select: none;
  transition: background 0.2s;
}

.inat-sortable-column:hover {
  background: #f0f0f0;
  text-decoration: underline;
}

.inat-sortable-column--active {
  font-weight: 600;
  color: #2271b1;
}
```

### JavaScript Sort Logic
```javascript
let currentSort = 'date';
let currentOrder = 'desc';

function handleColumnClick(column) {
  if (column === currentSort) {
    // Toggle order
    currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
  } else {
    // Switch column, default to asc (except date defaults to desc)
    currentSort = column;
    currentOrder = column === 'date' ? 'desc' : 'asc';
  }

  // Update URL
  updateURLParams({ sort: currentSort, order: currentOrder });

  // Fetch observations
  fetchObservations();
}
```

---

## Date Format Update

### Before
```
📅 2026-01-01 00:00:00
```

### After
```
📅 2026-01-01
```

**Implementation:**
```javascript
// In observation rendering
const date = obs.observed_on || 'Unknown date';
const displayDate = date.split(' ')[0];  // Remove time portion
html += '<p>📅 ' + escapeHtml(displayDate) + '</p>';
```

---

## Autocomplete Enhancement

### Add Scientific Name Search

**Current:** Only searches common names (species_guess)
**New:** Also searches scientific names (taxon_name)

**Implementation:**
```javascript
// Autocomplete query
const speciesMatches = (cache.species || [])
  .filter(item => {
    const commonName = item.common_name.toLowerCase();
    const scientificName = item.taxon_name.toLowerCase();
    return commonName.startsWith(query) || scientificName.startsWith(query);
  })
  .slice(0, 10)
  .map(item => ({
    value: item.common_name,
    type: 'species',
    emoji: '📋',
    subtitle: item.taxon_name  // Show scientific name below
  }));
```

**Display:**
```
📋 Amanita muscaria
   Amanita muscaria
```

---

## Success Criteria

- [ ] Clicking column headers sorts observations
- [ ] Sort arrows display correctly (↑ ↓ ↕)
- [ ] Active column highlighted
- [ ] Default sort is date DESC (most recent first)
- [ ] URL updates with sort params
- [ ] Filters preserved when sorting
- [ ] Cache keys include sort/order
- [ ] Scientific names searchable in autocomplete
- [ ] Dates display as YYYY-MM-DD (no time)
- [ ] All integration tests pass

---

## Testing Tasks

### Unit Tests
- [ ] Test sort param validation
- [ ] Test ORDER BY SQL generation
- [ ] Test default sort (date DESC)
- [ ] Test cache key with sort/order

### Integration Tests
- [ ] Test sort by date (ASC/DESC)
- [ ] Test sort by species (ASC/DESC)
- [ ] Test sort by location (ASC/DESC)
- [ ] Test sort by taxon (ASC/DESC)
- [ ] Test sort with filters
- [ ] Test sort with pagination
- [ ] Test invalid sort params (fallback to default)

---

## Security Considerations

- **SQL Injection**: Use whitelist for column names (never interpolate user input)
- **XSS**: Sanitize all sort params before display
- **Cache Poisoning**: Include sort/order in cache key to prevent serving wrong data

---

## Related Tasks

- TODO-QA-001: Add sort integration tests
- TODO-002: Pagination works with sorting
- Autocomplete enhancement (scientific names)
- Date format cleanup (remove time)

---

## Notes

- Sort state NOT persisted across sessions (no localStorage) - keeps UI simple
- Grid view gets dropdown instead of column headers (mobile-friendly)
- Keyboard shortcuts optional (nice-to-have)
- Sort affects all views (grid and list)
