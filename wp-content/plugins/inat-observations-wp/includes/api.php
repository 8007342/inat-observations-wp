<?php
    /**
     * API Client Module - iNaturalist Observations Data Fetching
     *
     * Foundational Data Source for the Plugin
     * =======================================
     * This module provides the primary interface to the iNaturalist public API (v1),
     * serving as the data foundation for the entire plugin. All observation data flows
     * through these functions before being cached, stored, or displayed.
     *
     * Exported Functions
     * ==================
     * - inat_obs_fetch_observations($args): Fetch a single page of observations with caching
     * - inat_obs_fetch_all($opts): Fetch all observations across multiple pages (stub)
     *
     * Key Features
     * ============
     * - Transient-based caching: Uses WordPress transients to minimize API calls
     * - Bearer token authentication: Optional API token for higher rate limits
     * - Project filtering: Automatically filters observations by iNaturalist project
     * - Error handling: Returns WP_Error on API failures for proper error propagation
     * - Logging: Comprehensive debug logging for troubleshooting
     *
     * Architecture Relationships
     * ==========================
     * Called by:
     *   - shortcode.php: inat_obs_ajax_fetch() calls inat_obs_fetch_observations() via AJAX
     *   - rest.php: inat_obs_rest_get_observations() calls inat_obs_fetch_observations()
     *   - init.php: inat_obs_refresh_job() should call inat_obs_fetch_observations() (stub)
     *
     * Called by (none internally): This module has no internal dependencies; uses only WordPress core APIs
     *
     * Dependencies:
     *   - WordPress transient functions (get_transient, set_transient)
     *   - WordPress HTTP functions (wp_remote_get, wp_remote_retrieve_response_code, wp_remote_retrieve_body)
     *   - Environment variables: INAT_PROJECT_SLUG, INAT_API_TOKEN, CACHE_LIFETIME
     *
     * Configuration (from .env or environment)
     * ========================================
     * - INAT_PROJECT_SLUG: Required. iNaturalist project identifier to filter observations
     * - INAT_API_TOKEN: Optional. Bearer token for authentication (increases rate limits)
     * - CACHE_LIFETIME: Optional. Transient cache duration in seconds (default: 3600/1 hour)
     *
     * Data Flow Context
     * =================
     * API Fetch → Transient Cache → Caller (Shortcode/REST/Cron)
     *                            → Database Storage (via db-schema.php)
     *                            → Display/Output
     */

    // Exit immediately if accessed directly (security check)
    if (!defined('ABSPATH')) exit;

    /**
     * Fetch a Single Page of Observations from iNaturalist API
     *
     * Retrieves observations from iNaturalist.org for a specified project.
     * Results are cached using WordPress transients to minimize API calls.
     *
     * Parameters:
     * @param array $args {
     *     Optional. Configuration array for the API request.
     *     @type string $project The iNaturalist project slug/ID. Defaults to INAT_PROJECT_SLUG env var.
     *     @type int $per_page Number of results per page (1-200). Default is 100.
     *     @type int $page Page number for pagination (1-indexed). Default is 1.
     * }
     *
     * Returns:
     * @return array|WP_Error Decoded JSON response from iNaturalist API or WP_Error on failure.
     *                        Success response includes 'results' array with observations.
     *                        See CLAUDE.md for response structure details.
     *
     * Caching:
     * - Results cached as WordPress transients with key 'inat_obs_cache_' + MD5(url)
     * - Cache duration set by CACHE_LIFETIME env var (default 3600 seconds = 1 hour)
     * - Cache is invalidated after duration expires, triggering fresh API fetch
     *
     * Authentication:
     * - Optional bearer token from INAT_API_TOKEN env var
     * - Recommended to include token for higher rate limits and access to private observations
     *
     * Error Handling:
     * - Returns WP_Error on network failure or non-200 HTTP responses
     * - Logs errors with full context for debugging
     * - Includes response body (first 200 chars) in error for API error analysis
     *
     * TODO:
     * - Implement pagination wrapper (use inat_obs_fetch_all() when complete)
     * - Add exponential backoff for rate-limited responses (HTTP 429)
     * - Implement request retry logic for transient failures
     * - Validate response structure before caching
     * - Add timeout/circuit breaker to prevent cascading failures
     */
    function inat_obs_fetch_observations($args = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Fetching observations');
        }

        // Merge provided arguments with defaults
        // Use environment variables for project slug and cache lifetime
        $defaults = [
            'project' => getenv('INAT_PROJECT_SLUG') ?: 'project_slug_here',
            'per_page' => 100,
            'page' => 1,
        ];
        $opts = array_merge($defaults, $args);

        // Sanitize and validate all input parameters
        $project = sanitize_text_field($opts['project']);
        $per_page = absint($opts['per_page']);
        $page = absint($opts['page']);

        // Enforce bounds for pagination parameters
        $per_page = max(1, min(200, $per_page)); // iNaturalist API limit is 200
        $page = max(1, $page);

        // Build the API endpoint URL with sanitized query parameters
        $base = 'https://api.inaturalist.org/v1/observations';
        $params = http_build_query([
            'project_id' => $project,
            'per_page' => $per_page,
            'page' => $page,
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $url = $base . '?' . $params;

        // Use transient cache to avoid repeated API calls for the same request
        // Cache key is based on MD5 hash of URL to keep key length under MySQL limits
        $transient_key = 'inat_obs_cache_' . md5($url);
        $cached = get_transient($transient_key);
        if ($cached) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] Cache hit');
            }
            return $cached;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Cache miss - fetching from API');
        }

        // Prepare HTTP headers for the API request
        $headers = [
            'Accept' => 'application/json',
        ];

        // Optional: Include bearer token for higher API rate limits and private data access
        // Token is read from INAT_API_TOKEN environment variable (kept in .env, not committed)
        $token = getenv('INAT_API_TOKEN') ?: null;
        if ($token) {
            // Sanitize token - remove any whitespace or control characters
            $token = preg_replace('/[\x00-\x1F\x7F]/', '', trim($token));
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // Make the actual HTTP request to iNaturalist API
        // Using WordPress wp_remote_get which handles SSL, redirects, and timeouts
        $resp = wp_remote_get($url, ['headers' => $headers, 'timeout' => 20]);

        // Check for network-level errors (connection failures, timeouts, etc.)
        if (is_wp_error($resp)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] API request failed: ' . esc_html($resp->get_error_message()));
            }
            return $resp;
        }

        // Extract HTTP status code and response body
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        // Only accept successful (200 OK) responses
        // Other codes indicate API errors, rate limiting, or server issues
        if ($code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] API error - HTTP ' . absint($code));
            }
            return new WP_Error('inat_api_error', 'Unexpected HTTP code ' . absint($code), ['status' => $code]);
        }

        // Parse JSON response into PHP array
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('inat_api_error', 'Invalid JSON response from API');
        }

        $result_count = isset($data['results']) ? count($data['results']) : 0;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Successfully fetched ' . absint($result_count) . ' observations');
        }

        // Cache results using WordPress transients (stored in database by default)
        // Configurable lifetime allows balancing between data freshness and API quota
        // Set transient only on successful fetch - errors are not cached to allow retry
        $cache_lifetime = absint(getenv('CACHE_LIFETIME') ?: 3600);
        $cache_lifetime = max(60, min(86400, $cache_lifetime)); // Bounds: 1 minute to 24 hours
        set_transient($transient_key, $data, $cache_lifetime);
        return $data;
    }

    /**
     * Fetch All Observations with Automatic Pagination
     *
     * Helper function to retrieve complete observation dataset across multiple pages.
     * Currently a stub - needs implementation.
     *
     * This function should:
     * - Call inat_obs_fetch_observations() multiple times with increasing page numbers
     * - Continue fetching until no more results are returned
     * - Aggregate results into a single array
     * - Handle rate limiting and backoff appropriately
     * - Cache final aggregated result for improved performance
     *
     * Parameters:
     * @param array $opts Options to pass to inat_obs_fetch_observations()
     *
     * Returns:
     * @return array|WP_Error Aggregated results from all pages, or WP_Error on failure
     *
     * TODO:
     * - Implement pagination loop (check total_results in API response)
     * - Add rate limiting/backoff between pages
     * - Consider memory implications for very large datasets
     * - Cache aggregated results for performance
     */
    function inat_obs_fetch_all($opts = []) {
        // TODO: Implement full dataset fetch with pagination
        // Pseudocode:
        // 1. Determine total number of results from first response
        // 2. Loop: fetch subsequent pages until reaching total
        // 3. Handle rate limiting between requests
        // 4. Aggregate all results
        // 5. Return combined array or WP_Error
    }
