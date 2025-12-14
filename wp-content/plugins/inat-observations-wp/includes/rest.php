<?php
    /**
     * WordPress REST API Module
     *
     * External API Interface for Observation Data
     * ===========================================
     * This module provides a standards-based REST API endpoint for external applications
     * to access iNaturalist observations. It enables third-party integrations, mobile apps,
     * and JavaScript frameworks to consume plugin data via the WordPress REST API.
     *
     * Exported Functions
     * ==================
     * - inat_obs_rest_get_observations($request): Handle GET /wp-json/inat/v1/observations
     *
     * REST Endpoint Details
     * ====================
     * Route: GET /wp-json/inat/v1/observations
     * Namespace: inat/v1
     * Authentication: None required (public endpoint)
     * CORS: Handled by WordPress REST API core
     *
     * Architecture Relationships
     * ==========================
     * Called by:
     *   - WordPress REST API initialization system (rest_api_init hook)
     *   - External applications making HTTP GET requests
     *
     * Calls to:
     *   - api.php: inat_obs_fetch_observations() to fetch data
     *   - WordPress core: register_rest_route(), rest_ensure_response()
     *   - WordPress core: WP_REST_Request, WP_Error
     *
     * Data Flow
     * =========
     * External Request → Register Hook (rest_api_init)
     *                 → Route Handler (inat_obs_rest_get_observations)
     *                 → API Client (inat_obs_fetch_observations)
     *                 → Transient Cache / iNaturalist API
     *                 → JSON Response
     *
     * Query Parameters (TODO - to be implemented)
     * ============================================
     * - per_page: Number of results per page (default: 50, max: 200)
     * - page: Page number for pagination (default: 1, min: 1)
     * - project_id: Filter by iNaturalist project (default: INAT_PROJECT_SLUG env var)
     * - filters: JSON-encoded field filters for observation field values
     * - order_by: Sort field (default: created_at) - 'created_at', 'observed_on'
     * - order: Sort direction (default: desc) - 'asc' or 'desc'
     *
     * Response Format (Success)
     * ========================
     * HTTP 200 OK - Returns iNaturalist standard response:
     * {
     *     "results": [
     *         {
     *             "id": 12345,
     *             "uuid": "abc-def-ghi",
     *             "species_guess": "Homo sapiens",
     *             "place_guess": "San Francisco, CA, USA",
     *             "observed_on": "2024-01-15",
     *             "observation_field_values": [...],
     *             "taxon_name": "...",
     *             "photos": [...]
     *         },
     *         ...
     *     ],
     *     "total_results": 1523,
     *     "page": 1,
     *     "per_page": 50
     * }
     *
     * Response Format (Error)
     * ======================
     * HTTP 500 or appropriate status code - WordPress WP_Error format:
     * {
     *     "code": "inat_api_error",
     *     "message": "Error description",
     *     "data": {
     *         "status": 500
     *     }
     * }
     *
     * Use Cases
     * =========
     * - JavaScript SPA/framework integration (Vue, React, Angular)
     * - Mobile native apps (iOS, Android)
     * - Third-party WordPress plugins extending functionality
     * - Machine-to-machine data integration
     * - Data export/analytics tools
     * - Public data feeds
     *
     * Performance Considerations
     * ==========================
     * Current implementation:
     *   - Calls iNaturalist API directly (via inat_obs_fetch_observations)
     *   - API responses cached as transients (CACHE_LIFETIME env var)
     *   - Multiple requests for same page return cached results
     *   - No database queries
     *
     * Future Optimization (TODO):
     *   - Query database instead of API for significantly better performance
     *   - Implement database-backed caching with transient fallback
     *   - Support offset/limit pagination for consistency
     *   - Add aggregate queries on observation fields
     *
     * Future Enhancements (TODO)
     * =========================
     * - Accept and validate query parameters (per_page, page, filters)
     * - Return database results instead of API for better performance
     * - Implement full pagination with total_results metadata
     * - Support filtering by observation field values
     * - Add sorting/ordering options (multiple fields)
     * - Cache database queries in transients
     * - Document response schema in REST schema definition
     * - Add optional authentication/permission checks
     * - Support search/text filtering
     * - Add date range filtering on observed_on
     */

    // Exit immediately if accessed directly (security check)
    if (!defined('ABSPATH')) exit;

    /**
     * Register REST API Routes on Initialization
     *
     * WordPress calls the 'rest_api_init' hook after the REST API has loaded.
     * This is the proper time to register custom routes.
     *
     * Hooks into: rest_api_init (WordPress core)
     */
    add_action('rest_api_init', function () {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Registering REST API routes');
        }

        // Register the main observations endpoint
        register_rest_route('inat/v1', '/observations', [
            'methods' => 'GET',
            'callback' => 'inat_obs_rest_get_observations',
            'permission_callback' => '__return_true',  // Public endpoint, no auth required
        ]);
    });

    /**
     * REST API Handler for Observations Endpoint
     *
     * Handles GET requests to /wp-json/inat/v1/observations
     * Fetches observation data and returns it in WordPress REST format.
     *
     * Parameters:
     * @param WP_REST_Request $request The REST request object containing query params
     *
     * Returns:
     * @return WP_REST_Response|WP_Error Observations data on success, WP_Error on failure
     *
     * Request Object Properties:
     * - get_params(): Returns array of all query parameters
     * - Individual params like get_param('per_page') for type-safe access
     *
     * Response:
     * Wrapped in rest_ensure_response() which handles JSON conversion and headers.
     * WordPress automatically sets Content-Type: application/json and handles CORS.
     *
     * Called by: WordPress REST API routing system
     * Calls: inat_obs_fetch_observations() from api.php
     * Side Effects: Makes HTTP call to iNaturalist API, logs
     *
     * TODO:
     * - Extract and validate per_page, page, filters from $request->get_params()
     * - Add bounds checking (per_page max 200, min 1)
     * - Return database results for better performance
     * - Handle WP_Error status codes properly
     * - Add pagination metadata to response
     * - Support field-based filtering
     */
    function inat_obs_rest_get_observations($request) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] REST API endpoint /inat/v1/observations called');
        }

        // Extract query parameters from the REST request
        // These would be used to pass to inat_obs_fetch_observations()
        // TODO: Validate and use these parameters
        $args = $request->get_params();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] REST API request parameters: ' . wp_json_encode($args));
        }

        // Fetch observation data from iNaturalist API with caching
        // TODO: Consider returning database results instead for better performance
        $data = inat_obs_fetch_observations(['per_page' => 50]);

        // Check if API fetch succeeded or failed
        if (is_wp_error($data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] REST API request failed');
            }
            // Return error in WordPress REST format with appropriate HTTP status
            return new WP_Error(
                'inat_api_error',
                esc_html__('Unable to fetch observations. Please try again later.', 'inat-observations-wp'),
                ['status' => 500]
            );
        }

        // Convert response to REST format and return to client
        // rest_ensure_response handles JSON encoding and proper headers
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] REST API request successful, returning response');
        }
        return rest_ensure_response($data);
    }
