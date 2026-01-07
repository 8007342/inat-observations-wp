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

## Integration Tests ✅ WRITTEN

**File:** `tests/integration/test-sql-injection-prevention.php`

**Status:** Tests written but require WordPress test environment setup.
Currently skipped in CI. Will be enabled once WP test lib is configured.

**Tests Implemented:**

### 1. test_field_property_sql_injection_blocked() ✅
- Attempts SQL injection via `inat_obs_dna_field_property` option
- Verifies table is not dropped
- Verifies whitelist validation prevents execution

### 2. test_sort_parameter_sql_injection_blocked() ✅
- Attempts SQL injection via `sort` parameter
- Verifies fallback to safe default
- Verifies table integrity maintained

### 3. test_order_parameter_sql_injection_blocked() ✅
- Attempts SQL injection via `order` parameter
- Verifies whitelist validation

### 4. test_only_whitelisted_sort_columns_allowed() ✅
- Tests all valid sort columns (date, species, location, taxon)
- Tests invalid inputs fall back to default
- Tests malicious inputs are blocked

### 5. test_only_whitelisted_field_properties_allowed() ✅
- Tests valid field_property options (name, value, datatype)
- Tests invalid inputs fall back to 'name'
- Tests malicious inputs cannot execute

### 6. test_union_sql_injection_blocked() ✅
- Attempts UNION-based injection
- Verifies parameterization prevents UNION attacks

### 7. test_boolean_blind_sql_injection_blocked() ✅
- Attempts boolean-based blind injection (OR '1'='1)
- Verifies no data leakage

### 8. test_time_based_sql_injection_blocked() ✅
- Attempts time-based injection (SLEEP)
- Verifies query completes quickly

### 9. test_stacked_query_injection_blocked() ✅
- Attempts stacked query injection (multiple statements)
- Verifies data integrity maintained

### 10. test_error_based_sql_injection_blocked() ✅
- Attempts error-based injection
- Verifies no SQL error messages leaked

**Total:** 10 comprehensive SQL injection tests

**Requirements:**
- WordPress test environment (WP_UnitTestCase)
- WordPress test database
- Install guide: https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/

**Next Steps:**
- [ ] Set up WordPress test environment
- [ ] Enable integration tests in CI
- [ ] Run tests before each release

---

## Pre-Commit Checks

### Static Analysis Rules ✅ IMPLEMENTED

Added to `.git/hooks/pre-commit`:

```bash
# Pattern 1: Unbraced variable in SELECT statement
grep -n 'SELECT.*\$[a-zA-Z_]' "$file" | grep -v '{' | grep -v '//.*SELECT'

# Pattern 2: Unbraced variable in WHERE statement
grep -n 'WHERE.*\$[a-zA-Z_]' "$file" | grep -v '{' | grep -v '//.*WHERE' | grep -v '%s' | grep -v '%d'

# Pattern 3: get_option() result used in SQL without whitelist validation
# Checks for variables from get_option() used in SQL without in_array() validation
```

**Features:**
- Scans all modified PHP files (excludes tests/ and vendor/)
- Detects unbraced variables in SELECT/WHERE clauses
- Warns about get_option() results used in SQL without whitelists
- Provides actionable error messages with file:line references
- Points to TODO-QA-002 for guidelines
- Commit aborted on critical findings

**Status:** ✅ Active in all commits

### PHPStan/Psalm Rules (Future)
- Detect string concatenation in SQL contexts
- Require all `$wpdb->prepare()` calls to use placeholders
- Flag any variables in SQL strings that aren't clearly validated

**Priority:** LOW (pre-commit checks cover most cases)

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
