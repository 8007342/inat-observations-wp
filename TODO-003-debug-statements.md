# TODO-003: Debug Statements for Query Troubleshooting

**Created:** 2026-01-07
**Priority:** HIGH
**Status:** ⏳ IN PROGRESS
**Related:** DNA filter not working (has_dna=1 returns all results)

---

## Problem

DNA filter checkbox (`has_dna=1`) is not filtering observations - returns all results instead of only those with DNA observation fields.

**Evidence:**
```
URL: /wp-admin/admin-ajax.php?action=inat_obs_fetch&nonce=xxx&per_page=all&page=1&has_dna=1&sort=location&order=asc
Result: Returns all observations (not filtered)
```

---

## Debugging Strategy

Enable verbose console logging to see:
1. Query construction (SQL with parameters)
2. WHERE clause generation
3. Result counts
4. DNA field table queries

**Files to instrument:**
- `includes/rest.php` - REST endpoint query building
- `includes/shortcode.php` - AJAX endpoint query building (if used)

---

## Implementation

### Add Verbose Logging to rest.php

**Location:** Around line 155-197 (DNA filter logic)

```php
// DNA filter (THE STAR! 🧬)
// Query normalized observation_fields table with configurable pattern
if ($has_dna) {
    // SECURITY: Whitelist validation for field_property (SQL injection prevention)
    $allowed_field_properties = ['name', 'value', 'datatype'];
    $field_property_option = get_option('inat_obs_dna_field_property', 'name');
    $field_property = in_array($field_property_option, $allowed_field_properties, true)
        ? $field_property_option
        : 'name';

    $match_pattern = get_option('inat_obs_dna_match_pattern', 'DNA%');

    // SECURITY: Table name uses WordPress-controlled prefix (safe)
    $fields_table = $wpdb->prefix . 'inat_observation_fields';

    // DEBUG: Log DNA filter configuration
    error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    error_log('🧬 DNA FILTER ACTIVE');
    error_log("  Field Property: $field_property");
    error_log("  Match Pattern: $match_pattern");
    error_log("  Fields Table: $fields_table");

    // Debug: Check if table exists and has data
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $fields_table
    )) === $fields_table;

    error_log("  Table Exists: " . ($table_exists ? 'YES' : 'NO'));

    if ($table_exists) {
        $field_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$fields_table}"));
        $dna_count = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT observation_id) FROM {$fields_table} WHERE {$field_property} LIKE %s",
            $match_pattern
        )));
        error_log("  Total Fields: $field_count");
        error_log("  DNA Observations: $dna_count");

        // Show sample DNA fields
        $sample_fields = $wpdb->get_results($wpdb->prepare(
            "SELECT observation_id, name, value FROM {$fields_table} WHERE {$field_property} LIKE %s LIMIT 5",
            $match_pattern
        ), ARRAY_A);
        error_log("  Sample DNA Fields:");
        foreach ($sample_fields as $field) {
            error_log("    - Obs #{$field['observation_id']}: {$field['name']} = {$field['value']}");
        }
    }

    // Subquery: Get observation IDs that have matching observation fields
    $where_clauses[] = "id IN (
        SELECT DISTINCT observation_id
        FROM {$fields_table}
        WHERE {$field_property} LIKE %s
    )";

    $prepare_args[] = $match_pattern;
    error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
}
```

### Add Query Execution Logging

**Location:** Around line 212-225 (query execution)

```php
// Query database (fast!)
$table = $wpdb->prefix . 'inat_observations';
$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d";

// DEBUG: Log final query
error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
error_log('📊 EXECUTING QUERY');
error_log("  Table: $table");
error_log("  WHERE clause: " . ($where_sql ?: '(none - all observations)'));
error_log("  ORDER BY: $sort_column $sort_order");
error_log("  LIMIT: $per_page OFFSET: $offset");
error_log("  Prepare args: " . print_r($prepare_args, true));

if (!empty($prepare_args)) {
    $prepared_sql = $wpdb->prepare($sql, $prepare_args);
    error_log("  Final SQL: $prepared_sql");
    $results = $wpdb->get_results($prepared_sql, ARRAY_A);
} else {
    $prepared_sql = $wpdb->prepare("SELECT * FROM {$table} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d", $per_page, $offset);
    error_log("  Final SQL: $prepared_sql");
    $results = $wpdb->get_results($prepared_sql, ARRAY_A);
}

// DEBUG: Log results
error_log("  Results returned: " . count($results));
if ($wpdb->last_error) {
    error_log("  ❌ SQL ERROR: " . $wpdb->last_error);
}

// Show first result for verification
if (!empty($results)) {
    error_log("  First result: Obs #{$results[0]['id']} - {$results[0]['species_guess']}");
}
error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
```

### Add Count Query Logging

**Location:** Around line 252-263 (count query)

```php
// Get total count (cached separately with longer TTL)
$count_cache_key = 'inat_obs_count_' . md5(serialize([
    'species' => $species_filter,
    'place' => $place_filter,
    'has_dna' => $has_dna
]));

$total_count = wp_cache_get($count_cache_key, 'inat_observations');

if (false === $total_count) {
    // Count query (same WHERE clause, no LIMIT/OFFSET)
    $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

    // DEBUG: Log count query
    error_log('📈 COUNT QUERY');
    error_log("  SQL: $count_sql");

    if (!empty($where_clauses)) {
        $count_args = array_slice($prepare_args, 0, -2);
        $total_count = intval($wpdb->get_var($wpdb->prepare($count_sql, $count_args)));
    } else {
        $total_count = intval($wpdb->get_var($count_sql));
    }

    error_log("  Total Count: $total_count");

    wp_cache_set($count_cache_key, $total_count, 'inat_observations', $cache_ttl);
}
```

---

## Testing Steps

1. **Enable Debug Mode** - Add logging as above
2. **Check DNA Filter:**
   ```
   - Visit: http://localhost:8080
   - Check DNA checkbox (🧬)
   - Watch WordPress debug.log
   ```

3. **Verify Logs Show:**
   - DNA filter configuration
   - Table existence check
   - Field count and DNA observation count
   - Sample DNA fields
   - WHERE clause with subquery
   - Final SQL query
   - Result count

4. **Expected Behavior:**
   - If `observation_fields` table is empty → 0 results
   - If table has DNA fields → Only observations with DNA returned
   - If table doesn't exist → Error in logs

---

## Cleanup Plan

Once debugging is complete:

1. **Remove verbose logging** - Comment out or delete error_log statements
2. **Keep essential logging:**
   - Error conditions (table doesn't exist, SQL errors)
   - Warning conditions (DNA filter active but 0 matches)
3. **Document in code comments** - Explain DNA filter logic clearly

**File:** `TODO-004-cleanup-debug-statements.md` (create after debugging)

---

## Status

- [ ] Add verbose logging to rest.php
- [ ] Test DNA filter with logging enabled
- [ ] Identify root cause of filter failure
- [ ] Fix DNA filter bug
- [ ] Verify fix works
- [ ] Remove debug logging
- [ ] Commit and push fix
