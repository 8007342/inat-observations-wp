<?php
// Autocomplete data provider with caching
if (!defined('ABSPATH')) exit;

/**
 * Get distinct species list (cached) with normalized values.
 *
 * CRITICAL: This query is EXPENSIVE (DISTINCT + ORDER BY on large dataset).
 * Result is cached for 1 hour to avoid repeated table scans.
 * Cache is invalidated when observations are refreshed.
 *
 * Returns array of associative arrays with:
 * - common_name: Display name (original case, accents preserved)
 * - scientific_name: Binomial nomenclature
 * - normalized_value: UPPERCASE, no accents, trimmed (for query matching)
 *
 * TODO-BUG-002: normalized_value ensures consistent dropdown/query matching
 *
 * @return array List of species with display and normalized values
 */
function inat_obs_get_species_autocomplete() {
    // Try cache first (Tlatoani's performance directive)
    // v3: Now includes normalized_value for TODO-BUG-002 fix
    $cache_key = 'inat_obs_species_autocomplete_v3';
    $species = get_transient($cache_key);

    if ($species !== false) {
        // Cache hit - prepend "Unknown Species" and return
        array_unshift($species, [
            'common_name' => 'Unknown Species',
            'scientific_name' => '',
            'normalized_value' => 'UNKNOWN SPECIES'
        ]);
        error_log('iNat Autocomplete: Species list from cache (' . count($species) . ' items)');
        return $species;
    }

    // Cache miss - run expensive query
    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';

    $start_time = microtime(true);

    // Query distinct species with taxon names (EXPENSIVE!)
    $results = $wpdb->get_results("
        SELECT DISTINCT
            species_guess as common_name,
            taxon_name as scientific_name
        FROM $table
        WHERE species_guess != ''
        ORDER BY species_guess ASC
        LIMIT 1000
    ", ARRAY_A);

    $query_time = microtime(true) - $start_time;

    // Add normalized_value to each result (TODO-BUG-002)
    foreach ($results as &$result) {
        $result['normalized_value'] = inat_obs_normalize_filter_value($result['common_name']);
    }
    unset($result);  // Break reference

    // Cache autocomplete results
    $cache_ttl = defined('INAT_OBS_DEV_CACHE_TTL') ? absint(INAT_OBS_DEV_CACHE_TTL) : HOUR_IN_SECONDS;
    set_transient($cache_key, $results, $cache_ttl);

    error_log(sprintf(
        'iNat Autocomplete: Generated species list (%d items, %.2fms) - cached for 1 hour',
        count($results),
        $query_time * 1000
    ));

    // Prepend "Unknown Species" as special filter value
    array_unshift($results, [
        'common_name' => 'Unknown Species',
        'scientific_name' => '',
        'normalized_value' => 'UNKNOWN SPECIES'
    ]);

    return $results;
}

/**
 * Get distinct location list (cached) with normalized values.
 *
 * Returns array of associative arrays with:
 * - display: Display name (original case, accents preserved)
 * - normalized_value: UPPERCASE, no accents, trimmed (for query matching)
 *
 * TODO-BUG-002: normalized_value ensures consistent dropdown/query matching
 *
 * @return array List of locations with display and normalized values
 */
function inat_obs_get_location_autocomplete() {
    // v2: Now includes normalized_value for TODO-BUG-002 fix
    $cache_key = 'inat_obs_location_autocomplete_v2';
    $locations = get_transient($cache_key);

    if ($locations !== false) {
        error_log('iNat Autocomplete: Location list from cache (' . count($locations) . ' items)');
        return $locations;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'inat_observations';

    $start_time = microtime(true);

    // Get distinct locations (original case preserved)
    $results = $wpdb->get_col("
        SELECT DISTINCT place_guess
        FROM $table
        WHERE place_guess != ''
        ORDER BY place_guess ASC
        LIMIT 1000
    ");

    $query_time = microtime(true) - $start_time;

    // Transform to structured format with normalized values (TODO-BUG-002)
    $structured_results = [];
    foreach ($results as $location) {
        $structured_results[] = [
            'display' => $location,
            'normalized_value' => inat_obs_normalize_filter_value($location)
        ];
    }

    // Cache autocomplete results (same TTL config as species)
    $cache_ttl = defined('INAT_OBS_DEV_CACHE_TTL') ? absint(INAT_OBS_DEV_CACHE_TTL) : HOUR_IN_SECONDS;
    set_transient($cache_key, $structured_results, $cache_ttl);

    error_log(sprintf(
        'iNat Autocomplete: Generated location list (%d items, %.2fms) - cached for 1 hour',
        count($structured_results),
        $query_time * 1000
    ));

    return $structured_results;
}

/**
 * Invalidate autocomplete caches.
 * Called after observation refresh.
 */
function inat_obs_invalidate_autocomplete_cache() {
    delete_transient('inat_obs_species_autocomplete_v1');  // Legacy
    delete_transient('inat_obs_species_autocomplete_v2');  // Legacy
    delete_transient('inat_obs_species_autocomplete_v3');  // Current (TODO-BUG-002)
    delete_transient('inat_obs_location_autocomplete_v1');  // Legacy
    delete_transient('inat_obs_location_autocomplete_v2');  // Current (TODO-BUG-002)
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
