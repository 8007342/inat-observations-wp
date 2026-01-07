# TODO-001: DNA Observation Detection Research

**Created**: 2026-01-06
**Priority**: HIGH
**Status**: Research Phase
**Assigned**: Biology & iNaturalist Specialist
**Parent**: WORDPRESS-PLUGIN.md (Phase 1)

---

## Objective

**Learn how to detect DNA metadata in iNaturalist observations and implement reliable filtering.**

Currently, iNaturalist doesn't have a standard "has_dna" boolean field. DNA information is embedded within `observation_field_values` as various field types. We need to:

1. Identify common DNA-related observation field patterns
2. Extract DNA presence and type
3. Store normalized DNA flag in our database
4. Display DNA badge prominently in UI

---

## Research Questions

### 1. What observation field names indicate DNA?

**Hypothesis**: Field names like:
- "DNA Barcode"
- "Genetic Sample"
- "DNA Sequence"
- "Barcode"
- "Sequencing Data"
- "Genetic Material"

**Method**:
- Manually inspect 50+ observations known to have DNA data
- Look for common patterns in `observation_field_values[].observation_field.name`
- Document all DNA-related field names found

### 2. Are there specific field IDs we can filter on?

**Hypothesis**: iNaturalist may have standard field IDs for DNA

**Method**:
- Check if field IDs are consistent across observations
- Query iNaturalist API: `/v1/observation_fields`
- Search for DNA-related fields by name
- Document standard field IDs if they exist

### 3. What values indicate DNA presence?

**Hypothesis**: Values vary (boolean, text, URLs)

**Possible formats**:
```json
// Boolean-like
{"value": "Yes"}
{"value": "true"}

// Text (sequence data)
{"value": "ACTGACTGACTG..."}

// URL (to external DNA database)
{"value": "https://boldsystems.org/index.php/..."}

// Structured data
{"value": "Barcode ID: XYZ123"}
```

**Method**:
- Catalog value formats found
- Determine if empty/null values mean "no DNA"
- Test edge cases (partial data, malformed values)

### 4. How to normalize across different field formats?

**Goal**: Create function `has_dna_metadata($observation_field_values)`

**Expected signature**:
```php
/**
 * Detect if observation contains DNA metadata.
 *
 * @param array $observation_field_values Raw JSON from iNaturalist
 * @return array ['has_dna' => bool, 'dna_type' => string|null]
 */
function inat_obs_parse_dna_metadata($observation_field_values) {
    // TODO: implement detection logic
}
```

**Return format**:
```php
[
    'has_dna' => true,
    'dna_type' => 'DNA Barcode', // or null if type unknown
]
```

---

## Research Plan

### Step 1: Manual Inspection (Day 1)

**Task**: Find observations with DNA metadata

**Resources**:
- iNaturalist search: Filter by "DNA Barcode" observation field
- Known projects: BOLD Systems integration projects
- Example queries:
  ```
  https://www.inaturalist.org/observations?field:DNA%20Barcode
  https://www.inaturalist.org/observations?project_id=bold-systems
  ```

**Action**:
- Collect 20 observation URLs with DNA
- Export `observation_field_values` JSON for each
- Document patterns in spreadsheet

### Step 2: API Exploration (Day 2)

**Task**: Query observation fields API

**API endpoints**:
```bash
# List all observation fields
curl https://api.inaturalist.org/v1/observation_fields

# Search for DNA-related fields
curl "https://api.inaturalist.org/v1/observation_fields?q=DNA"
curl "https://api.inaturalist.org/v1/observation_fields?q=Barcode"
curl "https://api.inaturalist.org/v1/observation_fields?q=Genetic"
```

**Action**:
- Document all DNA-related field IDs
- Note field data types (text, taxon, dna, etc.)
- Check if iNaturalist has native DNA data type

### Step 3: Pattern Analysis (Day 3)

**Task**: Analyze collected data for patterns

**Questions**:
- What % of DNA observations use field ID X?
- Are field IDs consistent globally or per-project?
- What's the most reliable detection method?

**Analysis**:
- Frequency count of field names/IDs
- Value format distribution
- Edge case catalog

### Step 4: Detection Algorithm (Day 4)

**Task**: Implement detection function

**Pseudocode**:
```php
function inat_obs_parse_dna_metadata($ofvs) {
    // Known DNA field IDs (from research)
    $dna_field_ids = [12345, 67890]; // TODO: populate from research

    // Known DNA field name patterns
    $dna_patterns = ['/dna/i', '/barcode/i', '/genetic/i', '/sequence/i'];

    foreach ($ofvs as $ofv) {
        $field_id = $ofv['observation_field']['id'];
        $field_name = $ofv['observation_field']['name'];
        $value = $ofv['value'];

        // Check by ID (most reliable)
        if (in_array($field_id, $dna_field_ids) && !empty($value)) {
            return [
                'has_dna' => true,
                'dna_type' => $field_name,
            ];
        }

        // Check by name pattern (fallback)
        foreach ($dna_patterns as $pattern) {
            if (preg_match($pattern, $field_name) && !empty($value)) {
                return [
                    'has_dna' => true,
                    'dna_type' => $field_name,
                ];
            }
        }
    }

    return ['has_dna' => false, 'dna_type' => null];
}
```

**Testing**:
- Unit tests with real observation data
- Test edge cases (empty values, malformed JSON)
- Validate against manual inspection results

### Step 5: Database Integration (Day 5)

**Task**: Store DNA metadata in `wp_inat_observations`

**Schema update** (if needed):
```sql
ALTER TABLE wp_inat_observations
    ADD COLUMN has_dna BOOLEAN DEFAULT FALSE,
    ADD COLUMN dna_type VARCHAR(50),
    ADD INDEX idx_has_dna (has_dna);
```

**Migration**:
```php
// Update existing observations with DNA metadata
function inat_obs_migrate_dna_flags() {
    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';

    $observations = $wpdb->get_results("SELECT id, observation_field_values FROM $table");

    foreach ($observations as $obs) {
        $ofvs = json_decode($obs->observation_field_values, true);
        $dna = inat_obs_parse_dna_metadata($ofvs);

        $wpdb->update(
            $table,
            [
                'has_dna' => $dna['has_dna'],
                'dna_type' => $dna['dna_type'],
            ],
            ['id' => $obs->id]
        );
    }
}
```

---

## Expected Findings

**Hypothesis 1**: Most DNA observations use field ID 12345 (placeholder)
- **If true**: Detection is simple and reliable
- **If false**: Need name-based pattern matching

**Hypothesis 2**: DNA field values are non-empty text
- **If true**: Check `!empty($value)` is sufficient
- **If false**: Need value format validation

**Hypothesis 3**: ~10% of observations have DNA metadata
- **Validate**: Count observations with `has_dna = true` after migration

---

## Success Criteria

- [ ] Identified ≥ 3 reliable DNA field IDs or name patterns
- [ ] Implemented `inat_obs_parse_dna_metadata()` function
- [ ] Unit tests with 90%+ accuracy on sample data
- [ ] Database migration script tested on dev environment
- [ ] Documentation updated with findings

---

## Risks & Mitigation

### Risk 1: No standard DNA fields exist
**Probability**: Medium
**Impact**: High (can't filter reliably)
**Mitigation**:
- Fall back to keyword search in notes/description
- Document limitation in user docs
- Feature flag: "DNA filtering (beta)"

### Risk 2: DNA field usage varies by project/region
**Probability**: Medium
**Impact**: Medium (detection unreliable)
**Mitigation**:
- Use multiple detection methods (ID + name + value)
- Allow admin to configure custom field IDs in settings
- Log unrecognized patterns for future improvement

### Risk 3: iNaturalist changes field structure
**Probability**: Low
**Impact**: High (breaks filtering)
**Mitigation**:
- Add error handling for missing fields
- Monitor iNaturalist API changelog
- Implement versioning for our parser

---

## Next Steps (After Research)

1. **Update WORDPRESS-PLUGIN.md** with DNA detection specifics
2. **Implement DNA badge UI** (cute DNA icon overlay)
3. **Add DNA filter** to FILTER BAR
4. **Document field IDs** in code comments
5. **User documentation**: How DNA filtering works

---

## Related Files

- `includes/db-schema.php`: Database schema with `has_dna` column
- `includes/api.php`: API fetch with DNA parsing
- `assets/images/dna-badge.svg`: DNA icon for badges
- `WORDPRESS-PLUGIN.md`: Architectural design

---

## Notes & Findings (To Be Filled During Research)

### Sample Observations with DNA

**Observation 1**:
- URL:
- Field ID:
- Field Name:
- Value:
- Notes:

### DNA Field IDs Found

| Field ID | Field Name | Data Type | Usage Count | Notes |
|----------|------------|-----------|-------------|-------|
|          |            |           |             |       |

### Detection Patterns Identified

| Pattern | Regex | Accuracy | Notes |
|---------|-------|----------|-------|
|         |       |          |       |

---

**Status**: TO DO (research not yet started)
**Next Action**: Begin Step 1 (Manual Inspection)
**Owner**: Biology Specialist + Developer
