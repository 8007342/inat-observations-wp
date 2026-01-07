<?php
/**
 * Helper Functions for iNaturalist Observations Plugin
 *
 * Shared utility functions used across the plugin.
 *
 * @package iNat_Observations
 */

if (!defined('ABSPATH')) exit;

/**
 * Normalize filter value for consistent matching across frontend/backend.
 *
 * CRITICAL: This function MUST be used in ALL places where filter values are processed:
 * - autocomplete.php: Building suggestion cache
 * - rest.php: Building SQL WHERE clauses
 * - shortcode.php: Building SQL WHERE clauses
 * - main.js: Building dropdown data-value attributes (JavaScript equivalent)
 *
 * Normalization steps:
 * 1. Remove accents (Montréal → Montreal, Piñon → Pinon)
 * 2. Convert to UPPERCASE
 * 3. Trim leading/trailing whitespace
 * 4. Normalize multiple spaces to single space
 *
 * This ensures:
 * - Dropdown value "MONTREAL QC" matches database "Montréal, QC"
 * - User typing "montreal" matches "Montréal"
 * - Filter values are consistent regardless of source
 *
 * Related: TODO-BUG-002-dropdown-selector-borked.md
 *
 * @param string $value Raw value (species name, location, etc.)
 * @return string Normalized value (UPPERCASE, no accents, trimmed)
 */
function inat_obs_normalize_filter_value($value) {
    if (empty($value)) {
        return '';
    }

    // Remove accents (é → e, ñ → n, ü → u, etc.)
    // WordPress provides remove_accents() function
    $value = remove_accents($value);

    // Convert to UPPERCASE for case-insensitive matching
    $value = strtoupper($value);

    // Trim leading/trailing whitespace
    $value = trim($value);

    // Normalize multiple consecutive spaces to single space
    $value = preg_replace('/\s+/', ' ', $value);

    return $value;
}

/**
 * JavaScript equivalent of inat_obs_normalize_filter_value().
 *
 * Returns JavaScript code snippet for use in inline scripts.
 * Keep this in sync with the PHP function above.
 *
 * Usage in main.js:
 * ```javascript
 * // Normalize filter value (matches PHP inat_obs_normalize_filter_value)
 * function normalizeFilterValue(value) {
 *   if (!value) return '';
 *
 *   // Remove accents (é → e, ñ → n, etc.)
 *   // NFD = Canonical Decomposition, then remove combining diacritical marks
 *   value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
 *
 *   // Uppercase
 *   value = value.toUpperCase();
 *
 *   // Trim
 *   value = value.trim();
 *
 *   // Normalize whitespace
 *   value = value.replace(/\s+/g, ' ');
 *
 *   return value;
 * }
 * ```
 *
 * @return string JavaScript function code
 */
function inat_obs_get_normalize_js() {
    return "
    // Normalize filter value (matches PHP inat_obs_normalize_filter_value)
    function normalizeFilterValue(value) {
      if (!value) return '';

      // Remove accents (é → e, ñ → n, etc.)
      // NFD = Canonical Decomposition, then remove combining diacritical marks
      value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');

      // Uppercase
      value = value.toUpperCase();

      // Trim
      value = value.trim();

      // Normalize whitespace
      value = value.replace(/\s+/g, ' ');

      return value;
    }
    ";
}
