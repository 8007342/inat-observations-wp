<?php
    // Shortcode and display logic.
    if (!defined('ABSPATH')) exit;

    add_shortcode('inat_observations', 'inat_obs_shortcode_render');

    function inat_obs_shortcode_render($atts = []) {
        // Get defaults from plugin settings
        $default_project = get_option('inat_obs_project_id', INAT_OBS_DEFAULT_PROJECT_ID);
        $default_per_page = get_option('inat_obs_display_page_size', '50');

        // Merge shortcode attributes with defaults
        // Shortcode attributes override plugin settings for flexibility
        $atts = shortcode_atts([
            'project' => $default_project,
            'per_page' => $default_per_page,
        ], $atts, 'inat_observations');

        // Enqueue assets
        wp_enqueue_script('inat-observations-main', INAT_OBS_URL . 'assets/js/main.js', ['jquery'], INAT_OBS_VERSION, true);
        wp_enqueue_style('inat-observations-css', INAT_OBS_URL . 'assets/css/main.css', [], INAT_OBS_VERSION);

        // Localize script with AJAX URL, nonce, and initial settings for security
        wp_localize_script('inat-observations-main', 'inatObsSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('inat_obs_fetch'),
            'project' => $atts['project'],
            'perPage' => $atts['per_page'],
        ]);

        // Minimal render. JS will enhance filters.
        ob_start();
        echo '<div id="' . esc_attr('inat-observations-root') . '" style="max-width: 100%; overflow-x: auto; overflow-y: visible; box-sizing: border-box;">';
        echo '<div class="' . esc_attr('inat-filters') . '">';
        echo '<select id="' . esc_attr('inat-filter-field') . '"><option value="">' . esc_html('Loading filters...') . '</option></select>';
        echo '</div>';
        echo '<div id="' . esc_attr('inat-list') . '" style="max-width: 100%; box-sizing: border-box; overflow: visible;">' . esc_html('Loading observations...') . '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // AJAX endpoint for client-side fetch
    add_action('wp_ajax_inat_obs_fetch', 'inat_obs_ajax_fetch');
    add_action('wp_ajax_nopriv_inat_obs_fetch', 'inat_obs_ajax_fetch');

    function inat_obs_ajax_fetch() {
        // Verify nonce for security (CSRF protection)
        if (!check_ajax_referer('inat_obs_fetch', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
            return;
        }

        global $wpdb;

        // Validate and sanitize pagination parameters
        $per_page_param = isset($_GET['per_page']) ? sanitize_text_field($_GET['per_page']) : '50';
        $page = isset($_GET['page']) ? max(1, absint($_GET['page'])) : 1;

        // Handle "all" option
        if ($per_page_param === 'all') {
            $per_page = PHP_INT_MAX; // No limit
            $offset = 0;
        } else {
            $per_page = absint($per_page_param);
            $per_page = min(max($per_page, 1), 10000); // Clamp between 1 and 10000
            $offset = ($page - 1) * $per_page;
        }

        // Validate and sanitize filter parameters
        $species_filter = isset($_GET['species']) ? sanitize_text_field($_GET['species']) : '';
        $place_filter = isset($_GET['place']) ? sanitize_text_field($_GET['place']) : '';

        // Validate and sanitize sort parameters
        $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';

        // Whitelist for sort columns (SQL injection prevention)
        $sort_columns = [
            'date' => 'observed_on',
            'species' => 'species_guess',
            'location' => 'place_guess',
            'taxon' => 'taxon_name'
        ];

        // Whitelist for sort order
        $sort_orders = ['asc', 'desc'];

        // Validate sort column
        $sort_column = isset($sort_columns[$sort]) ? $sort_columns[$sort] : 'observed_on';

        // Validate sort order
        $sort_order = in_array(strtolower($order), $sort_orders) ? strtolower($order) : 'desc';

        // Build cache key
        $cache_key = 'inat_obs_ajax_' . md5(serialize([
            'per_page' => $per_page,
            'page' => $page,
            'species' => $species_filter,
            'place' => $place_filter,
            'sort' => $sort,
            'order' => $order
        ]));

        // Try object cache first (if available)
        $results = wp_cache_get($cache_key, 'inat_observations');

        if (false === $results) {
            // Build WHERE clause
            $where_clauses = [];
            $prepare_args = [];

            if (!empty($species_filter)) {
                $where_clauses[] = 'species_guess LIKE %s';
                $prepare_args[] = '%' . $wpdb->esc_like($species_filter) . '%';
            }

            if (!empty($place_filter)) {
                $where_clauses[] = 'place_guess LIKE %s';
                $prepare_args[] = '%' . $wpdb->esc_like($place_filter) . '%';
            }

            $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

            // Add LIMIT and OFFSET to prepare args
            $prepare_args[] = $per_page;
            $prepare_args[] = $offset;

            // Query database (fast!)
            // SECURITY: $table uses WordPress prefix (safe), $sort_column and $sort_order are whitelisted above
            $table = $wpdb->prefix . 'inat_observations';
            $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d";

            if (!empty($prepare_args)) {
                $results = $wpdb->get_results($wpdb->prepare($sql, $prepare_args), ARRAY_A);
            } else {
                // SECURITY: $table, $sort_column, $sort_order are all validated above
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
            }

            // Decode JSON metadata for each result
            foreach ($results as &$result) {
                if (isset($result['metadata'])) {
                    $result['metadata'] = json_decode($result['metadata'], true);
                }
            }

            // Cache query result
            // Development mode: use shorter cache TTL if configured (for manual testing)
            // Set via: define('INAT_OBS_DEV_CACHE_TTL', 30); in wp-config.php
            $cache_ttl = defined('INAT_OBS_DEV_CACHE_TTL') ? absint(INAT_OBS_DEV_CACHE_TTL) : 300;  // Default: 5 minutes
            wp_cache_set($cache_key, $results, 'inat_observations', $cache_ttl);
        }

        wp_send_json_success(['results' => $results]);
    }
