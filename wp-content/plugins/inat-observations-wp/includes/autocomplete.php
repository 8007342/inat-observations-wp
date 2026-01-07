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
        // Cache hit - prepend "Unknown Species" and return
        array_unshift($species, 'Unknown Species');
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

    // Prepend "Unknown Species" as special filter value
    array_unshift($results, 'Unknown Species');

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
