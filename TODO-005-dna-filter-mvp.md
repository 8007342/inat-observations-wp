# TODO-005: Has DNA Filter MVP (Proper Implementation)

**Priority:** 🔴 CRITICAL (Core Feature - The Whole Project!)
**Status:** 🟡 In Progress
**Effort:** ~6-8 hours
**Dependencies:** TODO-002 research (DONE)

---

## Overview

Implement the **"Has DNA Sequence" checkbox filter** - the entire reason this project exists! 🧬

**Key Insight from User:**
> "The DNA metadata is stored in nested observation fields... The second dropdown appeared elsewhere, instead of over the element, but this is excellent progress."
>
> "The DNA metadata is in observation fields, look at another sample https://www.inaturalist.org/observations/197193940 don't just parse the data with regexes. NO REGEX!! NO HACKY HACKS!! Don't hallucinate for DNA source. This is sciency stuff."

---

## Data Structure (From iNaturalist API)

**API Endpoint:** `https://api.inaturalist.org/v1/observations/197193940`

**Response Structure:**
```json
{
  "results": [{
    "id": 197193940,
    "ofvs": [  // ← observation field values (NOT observation_field_values!)
      {
        "id": 12345,
        "field_id": 2330,
        "name": "DNA Barcode ITS",
        "name_ci": "dna barcode its",
        "value": "AGCTTAGCTA...",
        "value_ci": "agcttagcta...",
        "datatype": "dna"
      },
      {
        "field_id": 1162,
        "name": "Voucher Specimen Taken",
        "value": "Yes"
      }
    ]
  }]
}
```

**Key Fields:**
- `ofvs` - Array of observation field values
- `ofvs[].name` - Field name (e.g., "DNA Barcode ITS", "Voucher Specimen Taken")
- `ofvs[].value` - Field value (e.g., "AGCTTAGCTA...", "Yes")

---

## Phase 1: Database Normalization (The Right Way!)

### Step 1.1: Create Normalized Table

**File:** `includes/db-schema.php`

**New Table: `wp_inat_observation_fields`**

```sql
CREATE TABLE wp_inat_observation_fields (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    observation_id bigint(20) unsigned NOT NULL,  -- FK to wp_inat_observations.id
    field_id int,
    name varchar(255) NOT NULL,
    value text,
    datatype varchar(50),
    PRIMARY KEY (id),
    KEY observation_id (observation_id),
    KEY idx_name_prefix (name(50)),  -- Prefix index for LIKE 'DNA%' queries ⚡
    FOREIGN KEY (observation_id) REFERENCES wp_inat_observations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why This Matters (Tlatoani's Directive):**
- ✅ Prefix index allows FAST queries: `WHERE name LIKE 'DNA%'` uses index
- ✅ Foreign key maintains relational integrity
- ✅ Cascading delete cleans up orphaned records
- ✅ No JSON parsing in queries - proper relational design

**Migration Function:**

```php
function inat_obs_migrate_to_v2_2() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'inat_observation_fields';

    // Check if table exists
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

    if (!$exists) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            observation_id bigint(20) unsigned NOT NULL,
            field_id int,
            name varchar(255) NOT NULL,
            value text,
            datatype varchar(50),
            PRIMARY KEY (id),
            KEY observation_id (observation_id),
            KEY idx_name_prefix (name(50)),
            FOREIGN KEY (observation_id) REFERENCES {$wpdb->prefix}inat_observations(id) ON DELETE CASCADE
        ) $charset_collate ENGINE=InnoDB;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        error_log('iNat Observations: Created observation_fields table (v2.2)');
    }
}
```

### Step 1.2: Update Data Extraction

**File:** `includes/db-schema.php` - `inat_obs_store_items()`

```php
function inat_obs_store_items($items) {
    global $wpdb;
    $obs_table = $wpdb->prefix . 'inat_observations';
    $fields_table = $wpdb->prefix . 'inat_observation_fields';

    if (empty($items['results'])) return 0;

    $stored_count = 0;

    foreach ($items['results'] as $r) {
        $obs_id = intval($r['id']);

        // Extract taxon name
        $taxon_name = !empty($r['taxon']['name']) ? sanitize_text_field($r['taxon']['name']) : '';

        // Extract photo data
        $photo_url = '';
        $photo_attribution = '';
        $photo_license = '';

        if (!empty($r['photos'][0])) {
            $photo = $r['photos'][0];
            $raw_url = $photo['url'] ?? '';
            $validated_url = inat_obs_validate_image_url($raw_url);

            if ($validated_url !== false) {
                $photo_url = $validated_url;
                $photo_attribution = sanitize_text_field($photo['attribution'] ?? '');
                $photo_license = sanitize_text_field($photo['license_code'] ?? 'C');
            }
        }

        // Store main observation
        $result = $wpdb->replace(
            $obs_table,
            [
                'id' => $obs_id,
                'uuid' => sanitize_text_field($r['uuid'] ?? ''),
                'observed_on' => $r['observed_on'] ?? null,
                'species_guess' => sanitize_text_field($r['species_guess'] ?? ''),
                'taxon_name' => $taxon_name,
                'place_guess' => sanitize_text_field($r['place_guess'] ?? ''),
                'metadata' => json_encode($r['observation_field_values'] ?? []),  // Keep for backward compat
                'photo_url' => $photo_url,
                'photo_attribution' => $photo_attribution,
                'photo_license' => $photo_license,
                'created_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1),
            ],
            ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
        );

        if ($result === false) {
            continue;  // Skip on error
        }

        // CRITICAL: Delete old observation fields before inserting new ones
        $wpdb->delete($fields_table, ['observation_id' => $obs_id], ['%d']);

        // Store observation fields (DENORMALIZED from ofvs array)
        if (!empty($r['ofvs'])) {
            foreach ($r['ofvs'] as $field) {
                $wpdb->insert(
                    $fields_table,
                    [
                        'observation_id' => $obs_id,
                        'field_id' => isset($field['field_id']) ? intval($field['field_id']) : null,
                        'name' => sanitize_text_field($field['name'] ?? ''),
                        'value' => sanitize_textarea_field($field['value'] ?? ''),
                        'datatype' => sanitize_text_field($field['datatype'] ?? '')
                    ],
                    ['%d', '%d', '%s', '%s', '%s']
                );
            }
        }

        $stored_count++;
    }

    $wpdb->flush();

    return $stored_count;
}
```

---

## Phase 2: Plugin Settings (Configurable DNA Filter)

### Step 2.1: Add Settings Fields

**File:** `includes/admin.php`

**New Settings Section:**

```php
// DNA Filter Settings Section
add_settings_section(
    'inat_obs_dna_filter_section',
    '🧬 DNA Filter Configuration',
    'inat_obs_dna_filter_section_callback',
    'inat-observations'
);

function inat_obs_dna_filter_section_callback() {
    echo '<p>Configure the "Has DNA Sequence" filter that appears on the front-end.</p>';
    echo '<p><strong>Default:</strong> Matches any observation field where name starts with "DNA" (e.g., "DNA Barcode ITS", "DNA Sequence", etc.)</p>';
}

// Field: DNA Filter - Field Property
add_settings_field(
    'inat_obs_dna_field_property',
    'Field Property to Check',
    'inat_obs_dna_field_property_callback',
    'inat-observations',
    'inat_obs_dna_filter_section'
);

function inat_obs_dna_field_property_callback() {
    $value = get_option('inat_obs_dna_field_property', 'name');
    ?>
    <select name="inat_obs_dna_field_property" id="inat_obs_dna_field_property">
        <option value="name" <?php selected($value, 'name'); ?>>name (field name)</option>
        <option value="value" <?php selected($value, 'value'); ?>>value (field value)</option>
    </select>
    <p class="description">
        <strong>Default:</strong> <code>name</code> - Checks the field name (e.g., "DNA Barcode ITS")<br>
        <strong>Alternative:</strong> <code>value</code> - Checks the field value (e.g., actual DNA sequence)
    </p>
    <?php
}

// Field: DNA Filter - Match Pattern
add_settings_field(
    'inat_obs_dna_match_pattern',
    'Match Pattern',
    'inat_obs_dna_match_pattern_callback',
    'inat-observations',
    'inat_obs_dna_filter_section'
);

function inat_obs_dna_match_pattern_callback() {
    $value = get_option('inat_obs_dna_match_pattern', 'DNA%');
    ?>
    <input type="text" name="inat_obs_dna_match_pattern" id="inat_obs_dna_match_pattern"
           value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="DNA%">
    <p class="description">
        <strong>Pattern to match (case-insensitive).</strong> Use <code>%</code> as wildcard.<br>
        <strong>Examples:</strong><br>
        - <code>DNA%</code> - Matches "DNA Barcode ITS", "DNA Sequence", etc. (default)<br>
        - <code>Voucher%</code> - Matches "Voucher Specimen Taken", "Voucher Number(s)"<br>
        - <code>%Barcode%</code> - Matches any field containing "Barcode" (slower - avoid if possible)
    </p>
    <p class="description" style="color: #d63638;">
        ⚠️ <strong>Performance Note:</strong> For best performance, start your pattern at the beginning (e.g., <code>DNA%</code> not <code>%DNA</code>).
        Prefix matching uses the database index and is 1000x faster!
    </p>
    <?php
}

// Register settings
register_setting('inat-observations', 'inat_obs_dna_field_property');
register_setting('inat-observations', 'inat_obs_dna_match_pattern');
```

---

## Phase 3: Backend Query (Proper SQL, No Regex!)

### Step 3.1: Update REST Endpoint

**File:** `includes/rest.php`

```php
// DNA filter (THE STAR! 🧬)
// Query normalized observation_fields table with configurable pattern
if ($has_dna) {
    $field_property = get_option('inat_obs_dna_field_property', 'name');
    $match_pattern = get_option('inat_obs_dna_match_pattern', 'DNA%');

    $fields_table = $wpdb->prefix . 'inat_observation_fields';

    // Subquery: Get observation IDs that have matching observation fields
    $where_clauses[] = "id IN (
        SELECT DISTINCT observation_id
        FROM $fields_table
        WHERE $field_property LIKE %s
    )";

    // Add pattern to prepare args (case-insensitive LIKE)
    $prepare_args[] = $match_pattern;
}
```

**Why This Works:**
- ✅ Uses normalized table (no JSON parsing)
- ✅ Prefix matching with `LIKE 'DNA%'` uses index ⚡
- ✅ Configurable via plugin settings (no code changes)
- ✅ Case-insensitive by default (MySQL LIKE is case-insensitive)
- ✅ NO REGEX - pure SQL prefix matching

---

## Phase 4: Fix UX Issues

### Issue 1: Remove Blue Border

**Current:**
```css
border: 2px solid #2271b1;
```

**Fixed:**
```css
border: 1px solid #ddd;
```

### Issue 2: Remove "Apply" Button

- DNA checkbox triggers reload immediately ✅ (already works)
- Autocomplete selection should also trigger reload
- Remove "Apply" button entirely

### Issue 3: Add (X) Clear Buttons

**Replace:**
```html
<button id="inat-filter-clear">Clear All</button>
```

**With:**
```html
<!-- Individual (X) buttons for each input -->
<input type="text" id="inat-filter-species" value="...">
<button class="inat-clear-field" data-field="species" style="...">✕</button>

<input type="text" id="inat-filter-location" value="...">
<button class="inat-clear-field" data-field="location" style="...">✕</button>
```

### Issue 4: Auto-Collapse When Empty

**Logic:**
- Advanced Search visible when:
  - User clicks "🔍 Advanced Search" button, OR
  - Either species or location filter has a value
- Advanced Search hidden when:
  - Both inputs are empty AND user scrolls away

---

## Implementation Checklist

### Database
- [ ] Create `wp_inat_observation_fields` table (migration v2.2)
- [ ] Add foreign key constraint to observations table
- [ ] Add prefix index on `name` column
- [ ] Update `inat_obs_store_items()` to populate new table
- [ ] Test with sample observation (197193940)

### Plugin Settings
- [ ] Add "DNA Filter Configuration" section
- [ ] Add "Field Property to Check" dropdown (default: `name`)
- [ ] Add "Match Pattern" text input (default: `DNA%`)
- [ ] Register settings with WordPress
- [ ] Add performance warning about prefix matching

### Backend Query
- [ ] Update `includes/rest.php` to use normalized table
- [ ] Use configurable field property and match pattern
- [ ] Use SQL `LIKE` with prefix matching (NO REGEX!)
- [ ] Test query performance with 10,000+ observations

### Frontend UX
- [ ] Fix broken Advanced Filters after autocomplete changes
- [ ] Remove blue 2px border (use 1px #ddd instead)
- [ ] Remove "Apply" button
- [ ] Add (X) clear buttons for individual inputs
- [ ] Make autocomplete selection trigger reload
- [ ] Auto-show Advanced Search when filters have values
- [ ] Auto-hide Advanced Search when both inputs empty + scroll away

### Testing
- [ ] Test with observation 197193940 (has DNA fields)
- [ ] Test with observations without DNA (should not match)
- [ ] Test custom patterns (e.g., `Voucher%`, `%Barcode%`)
- [ ] Verify index is used (EXPLAIN query)
- [ ] Test pagination with DNA filter active

---

## Performance Expectations

**With Prefix Index:**
- Query: `WHERE name LIKE 'DNA%'` → ~5-10ms (uses index)
- 10,000 observations → still fast
- 1,000,000 observations → still fast (index scales!)

**Without Index (Anti-Pattern):**
- Query: `WHERE name LIKE '%DNA%'` → ~500ms-1s (table scan)
- Does NOT scale - avoid at all costs!

---

## Example: Testing with Real Data

**Observation:** https://www.inaturalist.org/observations/197193940

**API Response (ofvs array):**
```json
{
  "ofvs": [
    { "name": "DNA Barcode ITS", "value": "TTTCC..." },
    { "name": "DNA Barcode LSU", "value": "TCCAA..." },
    { "name": "Voucher Specimen Taken", "value": "Yes" },
    { "name": "Voucher Number(s)", "value": "CM24-04354" }
  ]
}
```

**Stored in `wp_inat_observation_fields`:**
```
| observation_id | name                      | value         |
|----------------|---------------------------|---------------|
| 197193940      | DNA Barcode ITS           | TTTCC...      |
| 197193940      | DNA Barcode LSU           | TCCAA...      |
| 197193940      | Voucher Specimen Taken    | Yes           |
| 197193940      | Voucher Number(s)         | CM24-04354    |
```

**Query with Default Settings:**
```sql
SELECT * FROM wp_inat_observations
WHERE id IN (
    SELECT DISTINCT observation_id
    FROM wp_inat_observation_fields
    WHERE name LIKE 'DNA%'  -- Matches "DNA Barcode ITS" and "DNA Barcode LSU"
)
```

**Result:** Observation 197193940 is included! ✅

---

## Acceptance Criteria

- [ ] ✅ "Has DNA Sequence" checkbox filters observations correctly
- [ ] ✅ Filter uses normalized `observation_fields` table (not JSON)
- [ ] ✅ Query uses SQL `LIKE` with prefix matching (NO REGEX!)
- [ ] ✅ Filter configuration stored in plugin settings
- [ ] ✅ Default pattern `DNA%` matches common DNA fields
- [ ] ✅ Performance <10ms for 10,000+ observations
- [ ] ✅ Advanced Search UX fixed (no broken filters)
- [ ] ✅ No blue 2px border, replaced with 1px #ddd
- [ ] ✅ No "Apply" button (autocomplete triggers reload)
- [ ] ✅ Individual (X) clear buttons work
- [ ] ✅ Advanced Search auto-shows/hides based on values

---

**Status:** 🟡 In Progress (Database schema next)
**Next Action:** Create `wp_inat_observation_fields` table migration
**ETA:** 6-8 hours for complete MVP implementation
