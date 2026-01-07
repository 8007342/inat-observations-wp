<?php
/**
 * Mock iNaturalist API Responses for Integration Tests
 *
 * Provides realistic API response mocks for testing cache behavior
 * and API integration without hitting the actual iNaturalist API.
 */

if (!defined('ABSPATH')) exit;

/**
 * Get mock project observations API response.
 *
 * @param int $per_page Results per page
 * @param int $page Page number
 * @return array Mock API response
 */
function get_mock_project_observations_response($per_page = 200, $page = 1) {
    // Use mock observations from fixtures
    require_once __DIR__ . '/mock-observations.php';
    $all_obs = get_mock_observations();

    // Paginate
    $offset = ($page - 1) * $per_page;
    $results = array_slice($all_obs, $offset, $per_page);

    return [
        'total_results' => count($all_obs),
        'page' => $page,
        'per_page' => $per_page,
        'results' => array_map(function($obs) {
            // Transform to iNaturalist API format
            return [
                'id' => $obs['id'],
                'observed_on' => $obs['observed_on'],
                'species_guess' => $obs['species_guess'],
                'place_guess' => $obs['place_guess'],
                'latitude' => $obs['latitude'],
                'longitude' => $obs['longitude'],
                'quality_grade' => json_decode($obs['metadata'], true)['quality_grade'] ?? 'casual',
                'taxon' => [
                    'id' => json_decode($obs['metadata'], true)['taxon_id'] ?? null,
                    'name' => $obs['taxon_name'],
                    'preferred_common_name' => $obs['species_guess']
                ],
                'photos' => $obs['photo_url'] ? [[
                    'id' => $obs['id'] * 10,
                    'url' => $obs['photo_url'],
                    'attribution' => $obs['photo_attribution'],
                    'license_code' => $obs['photo_license']
                ]] : [],
                'observation_fields' => []  // Added separately below
            ];
        }, $results)
    ];
}

/**
 * Get mock observation fields API response.
 *
 * @param int $observation_id Observation ID
 * @return array Mock API response
 */
function get_mock_observation_fields_response($observation_id) {
    require_once __DIR__ . '/mock-observations.php';
    $all_fields = get_mock_dna_fields();

    $fields = array_filter($all_fields, function($field) use ($observation_id) {
        return $field['observation_id'] === $observation_id;
    });

    return [
        'total_results' => count($fields),
        'page' => 1,
        'per_page' => 200,
        'results' => array_map(function($field) {
            return [
                'id' => rand(10000, 99999),
                'observation_id' => $field['observation_id'],
                'name' => $field['name'],
                'value' => $field['value'],
                'datatype' => 'text'
            ];
        }, array_values($fields))
    ];
}

/**
 * Mock wp_remote_get() for testing without hitting real API.
 *
 * @param string $url Request URL
 * @param array $args Request arguments
 * @return array Mock response
 */
function mock_wp_remote_get($url, $args = []) {
    // Parse URL to determine which endpoint
    if (strpos($url, '/observations?') !== false) {
        // Project observations endpoint
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $per_page = $query['per_page'] ?? 200;
        $page = $query['page'] ?? 1;

        return [
            'response' => ['code' => 200],
            'body' => json_encode(get_mock_project_observations_response($per_page, $page))
        ];
    }

    if (strpos($url, '/observation_field_values?') !== false) {
        // Observation fields endpoint
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $observation_id = $query['observation_id'] ?? null;

        return [
            'response' => ['code' => 200],
            'body' => json_encode(get_mock_observation_fields_response($observation_id))
        ];
    }

    // Default 404
    return [
        'response' => ['code' => 404],
        'body' => json_encode(['error' => 'Not found'])
    ];
}

/**
 * Mock rate-limited API response (for testing backoff).
 *
 * @return array Mock 429 response
 */
function mock_rate_limited_response() {
    return [
        'response' => ['code' => 429],
        'headers' => ['retry-after' => '60'],
        'body' => json_encode([
            'error' => 'Rate limit exceeded',
            'status' => 429
        ])
    ];
}

/**
 * Mock server error response (for testing error handling).
 *
 * @return array Mock 500 response
 */
function mock_server_error_response() {
    return [
        'response' => ['code' => 500],
        'body' => json_encode([
            'error' => 'Internal server error',
            'status' => 500
        ])
    ];
}

/**
 * Mock empty results response (for testing edge cases).
 *
 * @return array Mock empty response
 */
function mock_empty_response() {
    return [
        'response' => ['code' => 200],
        'body' => json_encode([
            'total_results' => 0,
            'page' => 1,
            'per_page' => 200,
            'results' => []
        ])
    ];
}

/**
 * Setup mock API responses for testing.
 *
 * Call this in test setUp() to mock wp_remote_get.
 */
function setup_mock_api() {
    // Mock wp_remote_get using Brain\Monkey or similar
    // This is test-framework specific
    if (function_exists('\Brain\Monkey\Functions\when')) {
        \Brain\Monkey\Functions\when('wp_remote_get')->alias('mock_wp_remote_get');
    }
}

/**
 * Get cache key for testing.
 *
 * Matches the cache key generation in includes/api.php
 *
 * @param string $project_id Project slug/ID
 * @param int $page Page number
 * @return string Cache key
 */
function get_test_cache_key($project_id, $page = 1) {
    return 'inat_obs_fetch_' . $project_id . '_page_' . $page;
}

/**
 * Test cache expiration helper.
 *
 * @param int $ttl Cache TTL in seconds (use 3 for tests)
 * @return bool True if cache should be expired
 */
function should_cache_expire($ttl = 3) {
    // For testing, we can sleep() for (ttl + 1) seconds
    // to force cache expiration
    sleep($ttl + 1);
    return true;
}
