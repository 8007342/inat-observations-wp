<?php
    /**
     * Shortcode and Front-End Display Module
     *
     * User-Facing Content Display and AJAX Handler
     * =============================================
     * This module enables WordPress pages and posts to display iNaturalist observations
     * through the [inat_observations] shortcode. It bridges server-side rendering with
     * client-side JavaScript to provide dynamic, accessible observation displays.
     *
     * Exported Functions
     * ==================
     * - inat_obs_shortcode_render($atts): Render shortcode HTML container
     * - inat_obs_ajax_fetch(): Handle AJAX data requests from JavaScript
     *
     * Architecture Relationships
     * ==========================
     * Called by:
     *   - WordPress core: Processes [inat_observations] shortcode in post content
     *   - Client-side JavaScript (main.js): Calls AJAX endpoint for data
     *
     * Calls to:
     *   - api.php: inat_obs_fetch_observations() to fetch data from API
     *   - WordPress core: wp_enqueue_script(), wp_enqueue_style(), wp_localize_script()
     *   - WordPress core: shortcode_atts(), check_ajax_referer(), wp_send_json_*()
     *
     * Data Flow
     * =========
     * 1. Author adds [inat_observations] to page/post content
     * 2. WordPress calls inat_obs_shortcode_render() when page renders
     * 3. Shortcode outputs minimal HTML container + enqueues JavaScript/CSS
     * 4. Browser renders page; JavaScript detects shortcode container
     * 5. JavaScript calls AJAX endpoint: wp_ajax_inat_obs_fetch
     * 6. AJAX handler calls inat_obs_fetch_observations() from api.php
     * 7. Data returned as JSON; JavaScript renders observations dynamically
     * 8. User can filter observations client-side (no server requests needed)
     *
     * Security Model
     * ==============
     * - Shortcode: Accessible to all users (public content)
     * - AJAX: Available to logged-in and guests (wp_ajax + wp_ajax_nopriv)
     * - Nonce: Implemented for CSRF protection on AJAX requests
     * - Input validation: All shortcode attributes sanitized
     * - XSS prevention: Output escaped via wp_localize_script()
     *
     * Performance Optimizations
     * =========================
     * - Lazy loading: Data fetched asynchronously after page renders
     * - Non-blocking: Page renders immediately while data loads
     * - Transient caching: AJAX calls leverage api.php transient cache
     * - Client-side filtering: Filter changes don't require server requests
     * - Asset optimization: CSS/JS only enqueued when shortcode used
     *
     * WordPress Hook Integration
     * ==========================
     * - add_shortcode('inat_observations'): Registers shortcode handler
     * - add_action('wp_ajax_inat_obs_fetch'): Handles AJAX for logged-in users
     * - add_action('wp_ajax_nopriv_inat_obs_fetch'): Handles AJAX for guests
     *
     * Future Enhancements (TODO)
     * ==========================
     * - Add rate limiting to prevent abuse of AJAX endpoint
     * - Implement database query fallback (return cached rows instead of API)
     * - Cache AJAX responses in browser localStorage
     * - Support additional shortcode attributes (filters, columns, sorting)
     * - Add pagination controls for large datasets
     */

    // Exit immediately if accessed directly (security check)
    if (!defined('ABSPATH')) exit;

    /**
     * Register the [inat_observations] Shortcode
     *
     * Adds the shortcode handler that WordPress calls when it encounters
     * [inat_observations] in page/post content.
     */
    add_shortcode('inat_observations', 'inat_obs_shortcode_render');

    /**
     * Render the [inat_observations] Shortcode
     *
     * Outputs a minimal HTML container with a loading state. The actual observation
     * data is fetched and rendered by client-side JavaScript via AJAX.
     *
     * Shortcode Attributes:
     * @param array $atts {
     *     Optional. Shortcode attributes passed by WordPress.
     *     @type string $project The iNaturalist project slug. Default: INAT_PROJECT_SLUG env var.
     *     @type int $per_page Number of observations to display per page. Default: 50.
     *     @type string $filters (TODO) Comma-separated list of field filters to apply.
     * }
     *
     * Output:
     * - HTML div with id="inat-observations-root" containing placeholder markup
     * - Selector div.inat-filters for JavaScript to populate with dynamic filters
     * - Div id="inat-list" for JavaScript to populate with observation records
     *
     * Side Effects:
     * - Enqueues jQuery and custom JavaScript (assets/js/main.js)
     * - Enqueues custom CSS (assets/css/main.css)
     * - Adds filter/action hooks that other plugins can hook into
     *
     * Return:
     * @return string HTML markup for the observation container
     *
     * TODO:
     * - Support additional shortcode attributes for filtering
     * - Add localization of JavaScript data via wp_localize_script
     * - Implement nonce token for AJAX requests
     * - Support multiple shortcodes per page with unique IDs
     */
    function inat_obs_shortcode_render($atts = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Rendering shortcode');
        }

        // Parse and validate shortcode attributes with defaults
        // Accepts project slug and per_page from shortcode, falls back to environment variables
        $atts = shortcode_atts([
            'project' => getenv('INAT_PROJECT_SLUG') ?: 'project_slug_here',
            'per_page' => 50,
        ], $atts, 'inat_observations');

        // Sanitize shortcode attributes to prevent injection
        $project = sanitize_text_field($atts['project']);
        $per_page = absint($atts['per_page']);
        $per_page = max(1, min(200, $per_page)); // Enforce bounds

        // Enqueue JavaScript and CSS assets for the shortcode
        // These are only loaded when the shortcode is actually used on a page
        wp_enqueue_script('inat-observations-main', plugin_dir_url(__FILE__) . '/../assets/js/main.js', ['jquery'], INAT_OBS_VERSION, true);
        wp_enqueue_style('inat-observations-css', plugin_dir_url(__FILE__) . '/../assets/css/main.css', [], INAT_OBS_VERSION);

        // Localize script with AJAX URL, nonce for security, and configuration
        // This ensures ajaxurl is available on the frontend for non-logged-in users
        wp_localize_script('inat-observations-main', 'inatObsConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('inat_obs_nonce'),
            'project' => esc_js($project),
            'perPage' => $per_page,
            'i18n' => [
                'loading' => esc_html__('Loading observations...', 'inat-observations-wp'),
                'loadingFilters' => esc_html__('Loading filters...', 'inat-observations-wp'),
                'filterByField' => esc_html__('Filter by observation field', 'inat-observations-wp'),
                'allFields' => esc_html__('All observation fields', 'inat-observations-wp'),
                'errorLoading' => esc_html__('Unable to load observations. Please try refreshing the page.', 'inat-observations-wp'),
                'errorNetwork' => esc_html__('Network error. Please check your connection and try again.', 'inat-observations-wp'),
                'observationsLoaded' => esc_html__('Loaded %d observations.', 'inat-observations-wp'),
                'noObservations' => esc_html__('No observations found.', 'inat-observations-wp'),
                'skipToObservations' => esc_html__('Skip to observations list', 'inat-observations-wp'),
            ],
        ]);

        // Render accessible HTML container with loading state
        // JavaScript will fetch data via AJAX and populate these containers
        // Using ob_start/ob_get_clean to safely capture output
        ob_start();
        ?>
        <section id="inat-observations-root"
                 class="inat-observations-widget"
                 aria-label="<?php esc_attr_e('iNaturalist Observations', 'inat-observations-wp'); ?>">

            <!-- Skip link for keyboard users to bypass filter controls -->
            <a href="#inat-list" class="inat-skip-link screen-reader-text">
                <?php esc_html_e('Skip to observations list', 'inat-observations-wp'); ?>
            </a>

            <!-- Filter controls region -->
            <div class="inat-filters" role="search" aria-label="<?php esc_attr_e('Filter observations', 'inat-observations-wp'); ?>">
                <label for="inat-filter-field" class="inat-filter-label">
                    <?php esc_html_e('Filter by observation field:', 'inat-observations-wp'); ?>
                </label>
                <select id="inat-filter-field"
                        class="inat-filter-select"
                        aria-describedby="inat-filter-help">
                    <option value=""><?php esc_html_e('Loading filters...', 'inat-observations-wp'); ?></option>
                </select>
                <span id="inat-filter-help" class="inat-help-text screen-reader-text">
                    <?php esc_html_e('Select an observation field to filter the list below.', 'inat-observations-wp'); ?>
                </span>
            </div>

            <!-- Live region for status announcements to screen readers -->
            <div id="inat-status"
                 class="screen-reader-text"
                 role="status"
                 aria-live="polite"
                 aria-atomic="true"></div>

            <!-- Observations list region -->
            <div id="inat-list"
                 class="inat-observations-list"
                 role="region"
                 aria-label="<?php esc_attr_e('Observations list', 'inat-observations-wp'); ?>"
                 aria-busy="true"
                 tabindex="-1">
                <p class="inat-loading-message">
                    <span class="inat-spinner" aria-hidden="true"></span>
                    <?php esc_html_e('Loading observations...', 'inat-observations-wp'); ?>
                </p>
            </div>

        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Register AJAX Endpoint for Observation Data
     *
     * The 'wp_ajax_' hook is for authenticated users (logged-in)
     * The 'wp_ajax_nopriv_' hook is for non-authenticated users (guests)
     * Both are registered so this endpoint is accessible to everyone.
     *
     * Action name: 'inat_obs_fetch' - called with action=inat_obs_fetch in AJAX request
     */
    add_action('wp_ajax_inat_obs_fetch', 'inat_obs_ajax_fetch');
    add_action('wp_ajax_nopriv_inat_obs_fetch', 'inat_obs_ajax_fetch');

    /**
     * AJAX Handler for Fetching Observation Data
     *
     * Called by client-side JavaScript when user views a page with the shortcode.
     * Fetches observation data from the iNaturalist API (with transient caching)
     * and returns it as JSON to the client for rendering.
     *
     * Request Parameters (from $_GET or $_POST):
     * - action: 'inat_obs_fetch' (WordPress AJAX routing)
     * - per_page: (optional) Number of items to fetch
     * - page: (optional) Page number for pagination
     * - filters: (optional, TODO) JSON-encoded filters to apply
     *
     * Response:
     * @return json {
     *     success: true|false,
     *     data: {
     *         results: [...observations...],
     *         total_results: number,
     *         page: number,
     *         per_page: number
     *     },
     *     message: "error message if success=false"
     * }
     *
     * Called by: Client-side JavaScript (main.js)
     * Calls: inat_obs_fetch_observations() from api.php
     * Side Effects: Sends JSON response via wp_send_json_success/error, logs
     *
     * Security Considerations:
     * - Currently accessible to all users (public data)
     * - TODO: Add rate limiting to prevent DoS
     * - TODO: Add nonce validation if needed
     * - TODO: Validate and sanitize query parameters
     *
     * Performance:
     * - Relies on transient caching in inat_obs_fetch_observations()
     * - Multiple requests for same data return cached results
     * - TODO: Consider returning database results instead of API for speed
     *
     * TODO:
     * - Add rate limiting (max requests per user/IP per time period)
     * - Return database results instead of API for better performance
     * - Add parameter validation and sanitization
     * - Implement request nonce validation
     * - Support filter parameters for observation field values
     */
    function inat_obs_ajax_fetch() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] AJAX fetch request received');
        }

        // Verify nonce for CSRF protection
        // check_ajax_referer will die with -1 if nonce is invalid
        if (!check_ajax_referer('inat_obs_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'inat-observations-wp')
            ], 403);
            return;
        }

        // Sanitize and validate request parameters
        $per_page = isset($_REQUEST['per_page']) ? absint($_REQUEST['per_page']) : 50;
        $per_page = max(1, min(200, $per_page)); // Enforce bounds

        $page = isset($_REQUEST['page']) ? absint($_REQUEST['page']) : 1;
        $page = max(1, $page);

        $project = isset($_REQUEST['project']) ? sanitize_text_field($_REQUEST['project']) : '';

        // Build args array with validated parameters
        $args = [
            'per_page' => $per_page,
            'page' => $page,
        ];
        if (!empty($project)) {
            $args['project'] = $project;
        }

        // Fetch observation data from iNaturalist API with caching
        $data = inat_obs_fetch_observations($args);

        // Check if fetch succeeded or failed
        if (is_wp_error($data)) {
            // API fetch failed - return error response to client
            // Escape error message before sending
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] AJAX fetch failed');
            }
            wp_send_json_error([
                'message' => esc_html__('Unable to fetch observations. Please try again later.', 'inat-observations-wp')
            ]);
        } else {
            // API fetch succeeded - return data to client
            wp_send_json_success($data);
        }
    }
