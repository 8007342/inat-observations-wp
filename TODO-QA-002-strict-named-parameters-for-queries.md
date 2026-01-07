# TODO-QA-002: Strict Named Parameters for ALL SQL Queries

**Created:** 2026-01-07
**Status:** 🚨 CRITICAL
**Priority:** URGENT
**Severity:** SQL Injection Risk

---

## Problem Statement

**CRITICAL SECURITY ISSUE**: Code is mixing SQL string interpolation with parameterized queries, creating SQL injection vulnerabilities.

### Anti-Patterns Found

1. **Direct interpolation of `$field_property` in rest.php**:
   ```php
   // WRONG - $field_property comes from get_option() (admin-controlled)
   WHERE $field_property LIKE %s
   ```

2. **Mixing interpolation styles**:
   ```php
   // CONFUSING - mixes interpolation ($sort_column) with placeholders (%d)
   $sql = "SELECT * FROM $table $where_sql ORDER BY $sort_column $sort_order LIMIT %d OFFSET %d";
   ```

3. **Table name interpolation without clear validation**:
   ```php
   $fields_table = $wpdb->prefix . 'inat_observation_fields';
   // Then used directly in query
   ```

---

## Security Implications

1. **SQL Injection via `$field_property`**: Admin with database access could set malicious option value
2. **Confusion between interpolated and parameterized values**: Makes auditing difficult
3. **Future developers may copy unsafe patterns**

---

## Correct Approach

### Rule 1: NEVER interpolate dynamic values
```php
// ❌ WRONG
$sql = "SELECT * WHERE $column = %s";

// ✅ CORRECT (with whitelist)
$allowed_columns = ['name', 'value'];
$column = in_array($user_column, $allowed_columns) ? $user_column : 'name';
$sql = "SELECT * WHERE {$column} = %s";  // Clearly validated
```

### Rule 2: Always use placeholders for VALUES
```php
// ❌ WRONG
$sql = "SELECT * WHERE name = '$name'";

// ✅ CORRECT
$sql = "SELECT * WHERE name = %s";
$wpdb->prepare($sql, $name);
```

### Rule 3: Whitelist ALL identifiers (columns, tables)
```php
// ✅ CORRECT
$whitelist = ['date' => 'observed_on', 'species' => 'species_guess'];
$column = $whitelist[$user_input] ?? 'observed_on';
// Now $column is guaranteed safe
```

### Rule 4: Use consistent style
```php
// ❌ WRONG - mixing styles
$sql = "SELECT * FROM $table WHERE $field_property LIKE %s";

// ✅ CORRECT - explicit validation, consistent placeholders
$allowed_properties = ['name', 'value', 'datatype'];
$property = in_array($field_property, $allowed_properties) ? $field_property : 'name';
$table = $wpdb->prefix . 'inat_observation_fields';  // Safe prefix
$sql = "SELECT * FROM {$table} WHERE {$property} LIKE %s";
```

---

## Files to Fix

### 1. `includes/rest.php` 🚨 CRITICAL
**Line 169**: `$field_property` interpolated without whitelist
```php
// CURRENT (UNSAFE):
$field_property = get_option('inat_obs_dna_field_property', 'name');
WHERE $field_property LIKE %s

// FIX:
$allowed_properties = ['name', 'value', 'datatype'];
$field_property_option = get_option('inat_obs_dna_field_property', 'name');
$field_property = in_array($field_property_option, $allowed_properties)
    ? $field_property_option
    : 'name';
WHERE {$field_property} LIKE %s  // Explicitly validated
```

### 2. `includes/rest.php` - ORDER BY clause
**Line 184**: Mixed interpolation style
```php
// CURRENT (CONFUSING):
$sql = "SELECT * FROM $table $where_sql ORDER BY $sort_column $sort_order LIMIT %d OFFSET %d";

// FIX (EXPLICIT):
$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d";
// With clear comment: $sort_column and $sort_order are whitelisted above
```

### 3. `includes/shortcode.php` - Same issues
**Lines 130, 135**: Mixed interpolation style

### 4. `includes/api.php` - Audit all queries
Check for any interpolation patterns

---

## Integration Tests Required

### Test 1: SQL Injection Attempt via field_property
```php
public function test_malicious_field_property_rejected() {
    // Attempt to inject SQL via admin option
    update_option('inat_obs_dna_field_property', "name'; DROP TABLE wp_inat_observations; --");

    $request = new WP_REST_Request('GET', '/inat/v1/observations');
    $request->set_param('has_dna', '1');

    $response = inat_obs_rest_get_observations($request);

    // Should NOT execute malicious SQL
    // Should fall back to safe default 'name'
    $this->assertArrayHasKey('results', $response);

    // Verify table still exists
    global $wpdb;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}inat_observations'");
    $this->assertNotNull($table_exists);
}
```

### Test 2: SQL Injection via sort parameter
```php
public function test_malicious_sort_parameter_rejected() {
    $request = new WP_REST_Request('GET', '/inat/v1/observations');
    $request->set_param('sort', "date; DROP TABLE wp_inat_observations; --");
    $request->set_param('order', 'desc');

    $response = inat_obs_rest_get_observations($request);

    // Should fall back to safe default 'observed_on'
    $this->assertArrayHasKey('results', $response);
}
```

### Test 3: Whitelist validation
```php
public function test_only_whitelisted_columns_allowed() {
    $test_cases = [
        ['sort' => 'date', 'expected_column' => 'observed_on'],
        ['sort' => 'species', 'expected_column' => 'species_guess'],
        ['sort' => 'INVALID', 'expected_column' => 'observed_on'],  // Falls back
        ['sort' => 'id; DROP TABLE', 'expected_column' => 'observed_on'],  // Falls back
    ];

    foreach ($test_cases as $case) {
        // Test that only whitelisted columns are used
    }
}
```

---

## Pre-Commit Checks

### Static Analysis Rule
```bash
# Add to pre-commit hook
grep -r "WHERE.*\$" includes/ && echo "ERROR: Potential SQL interpolation detected" && exit 1
grep -r "SELECT.*\$" includes/ && echo "ERROR: Potential SQL interpolation detected" && exit 1
```

### PHPStan/Psalm Rules
- Detect string concatenation in SQL contexts
- Require all `$wpdb->prepare()` calls to use placeholders
- Flag any variables in SQL strings that aren't clearly validated

---

## Audit Checklist

- [ ] Review ALL `$wpdb->prepare()` calls
- [ ] Review ALL `$wpdb->get_results()` calls
- [ ] Review ALL `$wpdb->get_var()` calls
- [ ] Review ALL `$wpdb->query()` calls
- [ ] Ensure ALL identifiers (columns, tables) are whitelisted
- [ ] Ensure ALL values use placeholders (%s, %d, %i)
- [ ] Add explicit comments above each query explaining validation
- [ ] Add integration tests for SQL injection attempts
- [ ] Update TODO-AUDIT.md with SQL injection anti-patterns

---

## Implementation Plan

1. **IMMEDIATE** (< 1 hour):
   - Fix `$field_property` interpolation in rest.php
   - Add whitelist validation with clear comments
   - Fix mixed interpolation style in rest.php and shortcode.php

2. **SHORT TERM** (< 2 hours):
   - Audit ALL queries in all files
   - Add integration tests for SQL injection
   - Add pre-commit static analysis

3. **LONG TERM** (< 1 day):
   - Document SQL query guidelines in SECURITY.md
   - Add PHPStan rules for SQL safety
   - Review all WordPress marketplace guidelines for SQL

---

## Related

- TODO-AUDIT.md: Add SQL injection anti-patterns section
- TODO-SECURITY.md: Add SQL query guidelines
- TODO-COMPLIANCE.md: WordPress marketplace SQL requirements

---

## Lesson Learned

**NEVER mix interpolation with parameterization.**
**ALWAYS whitelist identifiers.**
**ALWAYS use placeholders for values.**
**ALWAYS add comments explaining validation.**

This is n00b mistake level 0. Unacceptable.
