# TODO-002: DNA & Custom Metadata Filtering

**Priority:** 🔴 HIGH (Core Feature - Original Project Goal!)
**Status:** 🔴 Not Started
**Effort:** ~16-24 hours (2-3 days)
**Target:** v1.0.0 release

---

## Overview

This is the **CORE FEATURE** that triggered this entire project! Enable filtering of iNaturalist observations based on custom observation fields, specifically DNA barcoding metadata.

**Use Case (DNA Barcoding Project):**
- Users want to see only observations with DNA samples taken
- Filter by: "Voucher Specimen Taken" = "Yes"
- Display DNA metadata in list view (specimen number, collector, sequence data)
- Provide user-friendly settings page (no JSON knowledge required)

**Example Observation with DNA Data:**
https://www.inaturalist.org/observations/197193940

This observation contains 11 observation fields with DNA metadata:
- Voucher Specimen Taken: Yes
- Voucher Number(s): CM24-04354
- Collector's name: Mark Jenne
- DNA Barcode ITS: [sequence data]
- Sequencing technology: Nanopore (ONT)
- ...and more

---

## iNaturalist observation_fields Structure

**API Response Structure (from research):**

```json
{
  "results": [
    {
      "id": 197193940,
      "species_guess": "Hemimycena \"sp-CA01\"",
      "ofvs": [  // ← observation field values
        {
          "id": 12345,
          "field_id": 1162,
          "name": "Voucher Specimen Taken",
          "name_ci": "voucher specimen taken",
          "value": "Yes",
          "value_ci": "yes",
          "datatype": "text",
          "observation_field": {
            "id": 1162,
            "name": "Voucher Specimen Taken",
            "datatype": "text",
            "allowed_values": "Yes|No|Unknown",
            "description": "Was a physical specimen collected?"
          }
        },
        {
          "id": 67890,
          "field_id": 2330,
          "name": "DNA Barcode ITS",
          "name_ci": "dna barcode its",
          "value": "AGCTTAGCTA...",  // DNA sequence
          "value_ci": "agcttagcta...",
          "datatype": "dna"
        }
      ]
    }
  ]
}
```

**Key Observations:**
- Array name: `ofvs` (observation field values)
- Each field has `name` and `value` properties
- Case-insensitive versions: `name_ci`, `value_ci`
- `datatype` indicates field type (text, dna, numeric, date, etc.)
- Nested `observation_field` has metadata about the field definition

---

## Feature Requirements

### 1. Settings Page: Custom Filter Builder

**User Journey:**
1. Go to **Settings → iNat Observations**
2. See section: **Custom Filters** (new)
3. Click **Add Filter**
4. Configure filter:
   - **Filter Name:** "DNA Samples" (display name)
   - **Filter Rules:** (visual builder, no JSON!)
     - Rule 1: Field Name contains "Voucher Specimen"
     - Rule 2: Field Value equals "Yes"
   - **Display in List View:** ✅ Show as column
5. Click **Save Filter**
6. Filter appears on front-end shortcode view

**Visual Filter Builder (Phase 1 - Simple):**

```
┌─────────────────────────────────────────────────────────┐
│ Filter Name: [DNA Samples________________]              │
│                                                          │
│ Rules (all must match):                                 │
│                                                          │
│ ┌────────────────────────────────────────────────────┐ │
│ │ Field:  [observation_fields ▼]                     │ │
│ │ Property: [name ▼]                                  │ │
│ │ Operator: [contains ▼]                              │ │
│ │ Value:  [Voucher_____________________]              │ │
│ │                                      [Remove Rule]  │ │
│ └────────────────────────────────────────────────────┘ │
│                                                          │
│ [+ Add Rule]                                            │
│                                                          │
│ Display Options:                                         │
│ ☑ Show in Grid View (badge)                            │
│ ☑ Show in List View (column with matched field values) │
│                                                          │
│ [Save Filter]  [Cancel]                                 │
└─────────────────────────────────────────────────────────┘
```

**Phase 1 Dropdowns (Start Simple):**

**Field Dropdown:**
- `observation_fields` (the ofvs array)
- ~~`species_guess`~~ (future: filter by species too)
- ~~`place_guess`~~ (future: filter by location too)

**Property Dropdown (when Field = observation_fields):**
- `name` - The field name (e.g., "Voucher Specimen Taken")
- `value` - The field value (e.g., "Yes")

**Operator Dropdown:**
- `starts_with` - Prefix match: `LIKE 'param%'` ⚡ **USES INDEX** (recommended!)
- `equals` - Exact match (case-sensitive)
- ~~`contains`~~ - Deprecated (use starts_with for performance)
- `regex` - Regular expression match (advanced users, no index)

**⚠️ Performance Note (Tlatoani's Directive):**
- `starts_with` uses database index → FAST even with millions of rows
- `contains` would use `LIKE '%param%'` → table scan → SLOW
- For "contains" functionality, use starts_with + multiple rules

**Value Textbox:**
- Free-form text input
- Placeholder: "Enter search text (e.g., 'Voucher', 'DNA')..."
- Examples: "DNA", "Voucher", "Yes"
- **Tip:** For best performance, enter the beginning of the field name

---

### 2. Database Schema for Filters

**New Table: `wp_inat_observation_filters`**

```sql
CREATE TABLE wp_inat_observation_filters (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,  -- Display name (e.g., "DNA Samples")
    rules json NOT NULL,  -- Array of filter rules
    show_in_grid tinyint(1) DEFAULT 1,  -- Show badge in grid view
    show_in_list tinyint(1) DEFAULT 1,  -- Show column in list view
    sort_order int DEFAULT 0,  -- Display order
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id)
)
```

**Example `rules` JSON:**

```json
[
  {
    "field": "observation_fields",
    "property": "name",
    "operator": "contains",
    "value": "Voucher"
  },
  {
    "field": "observation_fields",
    "property": "value",
    "operator": "equals",
    "value": "Yes"
  }
]
```

**Interpretation:**
"Match observations where observation_fields contains a field with name containing 'Voucher' AND value equaling 'Yes'"

---

### 3. Filter Execution Logic

**Where to Filter:**

**Option A: PHP Server-Side (Recommended for Phase 1)**
- Filter in `includes/shortcode.php` AJAX handler
- Query database, then filter results in PHP
- Pros: Works with current caching, no API changes
- Cons: Can't index on observation_fields (stored as JSON)

**Option B: JavaScript Client-Side (Alternative)**
- Fetch all observations, filter in browser
- Pros: Instant filtering, no server load
- Cons: Doesn't scale to 10,000+ observations

**Recommendation: Start with Option A (PHP), optimize later**

**PHP Filter Implementation:**

```php
// In includes/shortcode.php, inat_obs_ajax_fetch()

// After querying database
$results = $wpdb->get_results($wpdb->prepare($sql, $prepare_args), ARRAY_A);

// Get active filters
$filters = inat_obs_get_active_filters();

// Apply filters
foreach ($results as $key => $obs) {
    $matches_all_filters = true;

    foreach ($filters as $filter) {
        if (!inat_obs_observation_matches_filter($obs, $filter)) {
            $matches_all_filters = false;
            break;
        }
    }

    if (!$matches_all_filters) {
        unset($results[$key]);  // Remove non-matching observation
    }
}

// Re-index array after filtering
$results = array_values($results);
```

**Filter Matching Function:**

```php
/**
 * Check if observation matches all filter rules.
 *
 * @param array $obs Observation data (with metadata JSON decoded)
 * @param object $filter Filter configuration
 * @return bool True if matches, false otherwise
 */
function inat_obs_observation_matches_filter($obs, $filter) {
    $rules = json_decode($filter->rules, true);

    foreach ($rules as $rule) {
        if ($rule['field'] === 'observation_fields') {
            // Parse metadata JSON if not already done
            $ofvs = isset($obs['metadata']['ofvs'])
                ? $obs['metadata']['ofvs']
                : [];

            $found_match = false;

            foreach ($ofvs as $field) {
                $field_value = $field[$rule['property']] ?? '';

                if (inat_obs_value_matches_rule($field_value, $rule)) {
                    $found_match = true;
                    break;
                }
            }

            if (!$found_match) {
                return false;  // Rule not satisfied
            }
        }
    }

    return true;  // All rules satisfied
}

/**
 * Check if value matches rule criteria.
 *
 * Note: Uses prefix matching for performance (Tlatoani's Directive).
 */
function inat_obs_value_matches_rule($value, $rule) {
    $search = $rule['value'];

    switch ($rule['operator']) {
        case 'starts_with':
            // Prefix match - case-insensitive
            return stripos($value, $search) === 0;  // Must be at position 0

        case 'equals':
            return $value === $search;  // Exact match (case-sensitive)

        case 'regex':
            return preg_match($search, $value);  // Regex match (advanced)

        default:
            return false;
    }
}
```

---

### 4. Front-End Display Updates

#### 4A. Grid View (Badge/Icon)

**Current Grid Card:**
```
┌────────────────────────┐
│ [Image]                │
│                        │
│ Hemimycena "sp-CA01"   │
│ 📍 California          │
│ 📅 2024-01-15          │
│ View on iNaturalist →  │
└────────────────────────┘
```

**With Filter Badge:**
```
┌────────────────────────┐
│ [Image]          [🧬]  │  ← DNA badge (if matches filter)
│                        │
│ Hemimycena "sp-CA01"   │
│ 📍 California          │
│ 📅 2024-01-15          │
│ 🧬 DNA Sample          │  ← Filter name badge
│ View on iNaturalist →  │
└────────────────────────┘
```

**JavaScript Implementation:**

```javascript
// In assets/js/main.js, rendering loop

// Check if observation matches any filters
const matchedFilters = [];
if (obs.matched_filters && obs.matched_filters.length > 0) {
    matchedFilters = obs.matched_filters;  // Passed from PHP
}

// Add badge for each matched filter
if (matchedFilters.length > 0) {
    html += '<div class="inat-filter-badges" style="margin-top: 10px;">';
    matchedFilters.forEach(filterName => {
        html += '<span class="inat-filter-badge" style="background: #4CAF50; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; margin-right: 5px;">';
        html += '🧬 ' + escapeHtml(filterName);
        html += '</span>';
    });
    html += '</div>';
}
```

---

#### 4B. List View (NEW!)

**List View Toggle Button:**

```
┌─────────────────────────────────────────────────────┐
│ Show: [10 ▼]  [Grid 🔲] [List ☰]  Page 1 [← →]     │
└─────────────────────────────────────────────────────┘
```

**List View Table:**

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Species               │ Location    │ Date       │ DNA Sample │ Voucher #   │
├──────────────────────────────────────────────────────────────────────────────┤
│ Hemimycena "sp-CA01"  │ California  │ 2024-01-15 │ ✅ Yes     │ CM24-04354  │
│ Amanita muscaria      │ Oregon      │ 2024-01-10 │ ❌ No      │ -           │
│ Cortinarius sp.       │ Washington  │ 2024-01-05 │ ✅ Yes     │ CM24-04201  │
└──────────────────────────────────────────────────────────────────────────────┘
```

**JavaScript Implementation:**

```javascript
// Add view toggle buttons
let controlsHtml = '<div id="inat-controls">';

// View toggle
controlsHtml += '<div style="display: inline-block; margin-right: 15px;">';
controlsHtml += '<button id="inat-view-grid" class="inat-view-btn active">🔲 Grid</button>';
controlsHtml += '<button id="inat-view-list" class="inat-view-btn">☰ List</button>';
controlsHtml += '</div>';

// ... existing per-page selector, pagination ...

// Render based on view mode
let html = '';
if (currentView === 'grid') {
    html = renderGridView(results);
} else {
    html = renderListView(results);
}

function renderListView(results) {
    let html = '<table class="inat-list-table" style="width: 100%; border-collapse: collapse;">';

    // Table header
    html += '<thead><tr>';
    html += '<th>Species</th>';
    html += '<th>Location</th>';
    html += '<th>Date</th>';

    // Dynamic columns for each filter
    filters.forEach(filter => {
        html += '<th>' + escapeHtml(filter.name) + '</th>';
    });

    html += '</tr></thead>';

    // Table body
    html += '<tbody>';
    results.forEach(obs => {
        html += '<tr>';
        html += '<td>' + escapeHtml(obs.species_guess) + '</td>';
        html += '<td>' + escapeHtml(obs.place_guess) + '</td>';
        html += '<td>' + escapeHtml(obs.observed_on) + '</td>';

        // Dynamic filter columns
        filters.forEach(filter => {
            const value = getFilterValueForObs(obs, filter);
            html += '<td>' + escapeHtml(value || '-') + '</td>';
        });

        html += '</tr>';
    });
    html += '</tbody>';
    html += '</table>';

    return html;
}

function getFilterValueForObs(obs, filter) {
    // Extract relevant observation_field value for this filter
    // E.g., if filter is "DNA Samples", find "Voucher Number(s)" field
    const ofvs = obs.metadata?.ofvs || [];

    for (const field of ofvs) {
        if (field.name.toLowerCase().includes(filter.match_keyword.toLowerCase())) {
            return field.value;
        }
    }

    return null;  // Not found
}
```

---

### 5. Settings Page UI

**File:** `/var/home/machiyotl/src/inat-observations-wp/wp-content/plugins/inat-observations-wp/includes/admin.php`

**New Section (after existing settings):**

```php
<?php
add_settings_section(
    'inat_obs_filters_section',
    'Custom Filters',
    'inat_obs_filters_section_callback',
    'inat-observations'
);

function inat_obs_filters_section_callback() {
    echo '<p>Create custom filters to display only observations matching specific metadata criteria.</p>';
    echo '<p><strong>Example:</strong> Filter for observations with DNA samples, voucher specimens, or specific observation fields.</p>';
}

// Render custom filters UI
add_settings_field(
    'inat_obs_custom_filters',
    'Configured Filters',
    'inat_obs_custom_filters_field_callback',
    'inat-observations',
    'inat_obs_filters_section'
);

function inat_obs_custom_filters_field_callback() {
    global $wpdb;
    $table = $wpdb->prefix . 'inat_observation_filters';
    $filters = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC");

    ?>
    <div id="inat-filters-manager">
        <?php if (empty($filters)) : ?>
            <p style="color: #666; font-style: italic;">No filters configured yet. Click "Add Filter" to create one.</p>
        <?php else : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Filter Name</th>
                        <th>Rules</th>
                        <th>Display</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filters as $filter) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($filter->name); ?></strong></td>
                            <td>
                                <?php
                                $rules = json_decode($filter->rules, true);
                                echo count($rules) . ' rule(s)';
                                ?>
                            </td>
                            <td>
                                <?php echo $filter->show_in_grid ? '🔲 Grid' : ''; ?>
                                <?php echo $filter->show_in_list ? '☰ List' : ''; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=inat-observations&action=edit-filter&id=' . $filter->id); ?>">Edit</a> |
                                <a href="<?php echo admin_url('admin.php?page=inat-observations&action=delete-filter&id=' . $filter->id); ?>" onclick="return confirm('Delete this filter?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p style="margin-top: 15px;">
            <a href="<?php echo admin_url('admin.php?page=inat-observations&action=add-filter'); ?>" class="button button-primary">+ Add Filter</a>
        </p>
    </div>
    <?php
}
?>
```

**Filter Builder Modal/Page:**

When user clicks "Add Filter", show a dedicated page (or inline form) with the visual filter builder.

```php
function inat_obs_render_filter_builder($filter_id = null) {
    // If $filter_id provided, load existing filter, else create new

    ?>
    <div class="wrap">
        <h1><?php echo $filter_id ? 'Edit Filter' : 'Add New Filter'; ?></h1>

        <form method="post" action="">
            <?php wp_nonce_field('inat_save_filter'); ?>
            <input type="hidden" name="action" value="inat_save_filter">
            <?php if ($filter_id) : ?>
                <input type="hidden" name="filter_id" value="<?php echo esc_attr($filter_id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="filter_name">Filter Name</label>
                    </th>
                    <td>
                        <input type="text" id="filter_name" name="filter_name" value="" class="regular-text" required>
                        <p class="description">Display name for this filter (e.g., "DNA Samples", "Voucher Specimens")</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Filter Rules</th>
                    <td>
                        <div id="filter-rules-container">
                            <!-- Rules added dynamically with JavaScript -->
                        </div>
                        <button type="button" id="add-rule-btn" class="button">+ Add Rule</button>

                        <p class="description">All rules must match for an observation to be included in filter results.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Display Options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_in_grid" value="1" checked>
                            Show badge in Grid View
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="show_in_list" value="1" checked>
                            Show column in List View
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Filter">
                <a href="<?php echo admin_url('admin.php?page=inat-observations'); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Rule template
        let ruleIndex = 0;

        function addRule() {
            const html = `
                <div class="filter-rule" data-index="${ruleIndex}" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                    <select name="rules[${ruleIndex}][field]" class="rule-field">
                        <option value="observation_fields">observation_fields</option>
                    </select>

                    <select name="rules[${ruleIndex}][property]" class="rule-property">
                        <option value="name">name (field name)</option>
                        <option value="value">value (field value)</option>
                    </select>

                    <select name="rules[${ruleIndex}][operator]" class="rule-operator">
                        <option value="contains">contains</option>
                        <option value="equals">equals</option>
                        <option value="regex">regex</option>
                    </select>

                    <input type="text" name="rules[${ruleIndex}][value]" placeholder="Enter search text..." class="regular-text rule-value">

                    <button type="button" class="button remove-rule-btn">Remove</button>
                </div>
            `;

            $('#filter-rules-container').append(html);
            ruleIndex++;
        }

        // Add first rule on page load
        addRule();

        // Add rule button
        $('#add-rule-btn').on('click', addRule);

        // Remove rule button
        $(document).on('click', '.remove-rule-btn', function() {
            $(this).closest('.filter-rule').remove();
        });
    });
    </script>
    <?php
}
```

---

## Implementation Phases

### Phase 1: Basic Filtering (MVP - 8 hours)

**Goal:** Filter observations by simple observation_fields rules

**Tasks:**
- [x] Research observation_fields structure (DONE)
- [ ] Create database table `wp_inat_observation_filters`
- [ ] Add settings page section "Custom Filters"
- [ ] Implement filter builder UI (one rule: name contains X)
- [ ] Implement PHP filter execution in AJAX handler
- [ ] Test with "DNA Sample" filter (Voucher Specimen Taken)

**Deliverable:** Users can create filters and see filtered observations in grid view

---

### Phase 2: Multiple Rules & UI Polish (6 hours)

**Goal:** Support multiple rules per filter, improve UI

**Tasks:**
- [ ] Support multiple rules (AND logic)
- [ ] Add "Remove Rule" button
- [ ] Validate filter configuration (at least 1 rule required)
- [ ] Add filter badges to grid view cards
- [ ] Test with complex filters (2-3 rules)

**Deliverable:** Robust filtering with visual feedback

---

### Phase 3: List View (8 hours)

**Goal:** Implement table view with filter columns

**Tasks:**
- [ ] Add grid/list toggle buttons to controls
- [ ] Implement `renderListView()` JavaScript function
- [ ] Extract observation_field values for table columns
- [ ] Add responsive CSS for mobile (horizontal scroll)
- [ ] Test with various screen sizes

**Deliverable:** Users can switch between grid and list views

---

### Phase 4: Advanced Features (Future)

- [ ] OR logic support (match any rule, not all rules)
- [ ] Filter by species_guess, place_guess, date ranges
- [ ] Export filtered results to CSV
- [ ] Save filter as shortcode attribute: `[inat_observations filter="DNA Samples"]`
- [ ] Performance optimization (database indexing, caching)

---

## Data Storage Strategy

**Problem:** observation_fields are stored in `metadata` JSON column, can't index efficiently.

**Solutions:**

**Option A: Keep in JSON, Filter in PHP (Phase 1)**
- Pros: Simple, works with current schema
- Cons: Slower for large datasets (10,000+ observations)

**Option B: NORMALIZE to Separate Table (RECOMMENDED - Tlatoani's Directive)**

**Unfold nested JSON into proper relational structure during import!**

```sql
CREATE TABLE wp_inat_observation_fields (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    observation_id bigint(20) unsigned NOT NULL,  -- FK to wp_inat_observations.id
    field_id int,
    name varchar(255),
    value text,
    datatype varchar(50),
    PRIMARY KEY (id),
    KEY observation_id (observation_id),
    KEY idx_name_prefix (name(50)),  -- Prefix index for LIKE 'param%' queries
    FULLTEXT KEY value (value)       -- Full-text search for sequences/long text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Benefits:**
- ✅ Can index on name and value (FAST queries!)
- ✅ Proper foreign key relationship (relational integrity)
- ✅ PREFIX MATCHING uses index: `WHERE name LIKE 'Voucher%'` ⚡
- ✅ Scales to millions of observations
- ✅ Can use MySQL FULLTEXT search for DNA sequences

**⚠️ CRITICAL INDEX OPTIMIZATION (Tlatoani's Teaching):**

**FAST (uses index):**
```sql
-- Prefix match - uses idx_name_prefix
WHERE name LIKE 'Voucher%'
WHERE name LIKE 'DNA%'
```

**SLOW (table scan - ANTI-PATTERN!):**
```sql
-- ❌ DO NOT USE - forces table scan!
WHERE name LIKE '%Voucher%'  -- Wildcard at beginning = NO INDEX
WHERE name LIKE '%DNA'       -- Wildcard at beginning = NO INDEX
```

**Filter Builder Strategy:**
- "Starts with" operator → `LIKE 'param%'` (indexed, fast)
- "Contains" operator → Fall back to PHP filtering (post-query)
- OR use FULLTEXT search for "contains" on value field

**Migration:**
- Run on plugin activation to extract existing metadata
- Update `inat_obs_store_items()` to populate new table during refresh
- Keeps main table clean, observation_fields normalized

**Recommendation:**
- ✅ **IMPLEMENT OPTION B FROM START** (proper normalization)
- Skip Option A (JSON filtering) entirely - sets us up for scale
- Small upfront cost, massive long-term performance gains

---

## Testing Scenarios

### Test Case 1: DNA Sample Filter

**Filter Configuration:**
- Name: "DNA Samples"
- Rule 1: observation_fields.name starts_with "Voucher"
- Rule 2: observation_fields.value equals "Yes"

**Expected Behavior:**
- Only observations with "Voucher Specimen Taken" = "Yes" are shown
- Grid view shows 🧬 DNA Samples badge
- List view shows voucher number in column

**Test Observation:** https://www.inaturalist.org/observations/197193940

---

### Test Case 2: Collector Filter

**Filter Configuration:**
- Name: "Mark Jenne Collections"
- Rule 1: observation_fields.name contains "Collector"
- Rule 2: observation_fields.value contains "Mark Jenne"

**Expected Behavior:**
- Only observations collected by Mark Jenne are shown
- List view shows collector name

---

### Test Case 3: No Matches

**Filter Configuration:**
- Name: "Fictional Field"
- Rule 1: observation_fields.name equals "NonexistentField"

**Expected Behavior:**
- Zero observations shown
- User-friendly message: "No observations match your filters. Try adjusting your filter settings."

---

## Acceptance Criteria

- [ ] ✅ Users can create custom filters via Settings page
- [ ] ✅ Filter builder supports observation_fields.name and observation_fields.value
- [ ] ✅ Filter builder supports contains, equals, regex operators
- [ ] ✅ Multiple rules per filter (AND logic)
- [ ] ✅ Filtered results display correctly in grid view with badges
- [ ] ✅ List view toggle button works
- [ ] ✅ List view table shows filter columns
- [ ] ✅ Filtering works with pagination (correct counts)
- [ ] ✅ No JavaScript errors in browser console
- [ ] ✅ Mobile-responsive (list view scrolls horizontally on small screens)
- [ ] ✅ Documentation in readme.txt explains filter feature

---

## Files to Create/Modify

### New Files
- [ ] `includes/filters.php` - Filter logic (matching, execution)
- [ ] `includes/filters-admin.php` - Settings page UI for filter builder
- [ ] `assets/css/filters.css` - Filter-specific styles (badges, list view table)
- [ ] `assets/js/filters.js` - Filter builder interactivity (add/remove rules)

### Modified Files
- [ ] `includes/db-schema.php` - Add `wp_inat_observation_filters` table creation
- [ ] `includes/shortcode.php` - Apply filters in AJAX handler
- [ ] `includes/admin.php` - Add "Custom Filters" settings section
- [ ] `assets/js/main.js` - Add list view rendering, filter badges
- [ ] `assets/css/main.css` - Add list view table styles

---

## Security Considerations

### Input Validation
- [ ] Sanitize filter name: `sanitize_text_field()`
- [ ] Validate operator: whitelist (contains, equals, regex)
- [ ] Validate field/property: whitelist (observation_fields, name, value)
- [ ] Sanitize search value: `sanitize_text_field()` (or validate regex syntax)

### SQL Injection
- [ ] Use `$wpdb->prepare()` for all filter table queries
- [ ] Never use user input directly in SQL

### XSS Prevention
- [ ] Escape filter name in UI: `esc_html()`
- [ ] Escape observation_field values in list view: `escapeHtml()`
- [ ] Escape filter badges: `escapeHtml()`

### CSRF Protection
- [ ] Use nonces for filter save/delete actions: `wp_nonce_field()`
- [ ] Verify nonces: `check_admin_referer()`

---

## Performance Optimization

### Database Queries
- [ ] Cache filter rules (5 minutes): `wp_cache_set()`
- [ ] Load filters once per request, not per observation
- [ ] Consider moving to separate table (Phase 4) for indexing

### Frontend
- [ ] Lazy render list view (only when toggled, not on page load)
- [ ] Limit visible rows in list view (virtual scrolling for 1000+ rows)
- [ ] Use CSS `will-change` for smooth view transitions

---

## Related TODOs

- `TODO-thumbnails-legal-compliance.md` - Display images in grid/list view
- `TODO-001-wordpress-org-compliance.md` - Ensure filter feature meets WordPress.org standards
- `TODO-QA-001-sanitize-debug-logs.md` - Remove debug logs from filter JavaScript

---

## Summary for User

**What This Enables:**

You asked for the ability to filter iNaturalist observations by DNA metadata (like "Voucher Specimen Taken" = "Yes") and display that metadata in a nice list view.

This TODO outlines a **user-friendly filter builder** where you (or your users) can:

1. Go to Settings → iNat Observations
2. Click "Add Filter"
3. Configure rules using dropdowns (no JSON knowledge needed!):
   - Field: `observation_fields`
   - Property: `name` or `value`
   - Operator: `contains`, `equals`, or `regex`
   - Value: e.g., "Voucher", "DNA", "Yes"
4. Save the filter
5. View filtered observations with:
   - **Grid view:** 🧬 DNA badge on matching cards
   - **List view:** Table with columns showing DNA metadata

**Example Filter Setup (No JSON!):**

```
Filter Name: [DNA Samples]

Rule 1:
  Field: [observation_fields ▼]
  Property: [name ▼]
  Operator: [contains ▼]
  Value: [Voucher]

Rule 2:
  Field: [observation_fields ▼]
  Property: [value ▼]
  Operator: [equals ▼]
  Value: [Yes]

[Save Filter]
```

**Result:** Only observations with "Voucher Specimen Taken" = "Yes" are shown!

---

**Status:** 🔴 Ready for implementation
**Next Step:** Create database schema and basic filter builder UI
**ETA:** 2-3 days (16-24 hours) for full MVP with grid badges + list view
