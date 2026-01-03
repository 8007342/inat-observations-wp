<?php
    // REST API endpoints
    if (!defined('ABSPATH')) exit;

    add_action('rest_api_init', function () {
        register_rest_route('inat/v1', '/observations', [
            'methods' => 'GET',
            'callback' => 'inat_obs_rest_get_observations',
            'permission_callback' => '__return_true',
        ]);
    });

    function inat_obs_rest_get_observations($request) {
        global $wpdb;
        $params = $request->get_params();

        // Validate and sanitize pagination parameters
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 50;
        $per_page = min(max($per_page, 1), 100); // Clamp between 1 and 100
        $page = isset($params['page']) ? max(1, absint($params['page'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Validate and sanitize filter parameters
        $species_filter = isset($params['species']) ? sanitize_text_field($params['species']) : '';
        $place_filter = isset($params['place']) ? sanitize_text_field($params['place']) : '';

        // Build cache key
        $cache_key = 'inat_obs_query_' . md5(serialize([
            'per_page' => $per_page,
            'page' => $page,
            'species' => $species_filter,
            'place' => $place_filter
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
            $table = $wpdb->prefix . 'inat_observations';
            $sql = "SELECT * FROM $table $where_sql ORDER BY observed_on DESC LIMIT %d OFFSET %d";

            if (!empty($prepare_args)) {
                $results = $wpdb->get_results($wpdb->prepare($sql, $prepare_args), ARRAY_A);
            } else {
                $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY observed_on DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);
            }

            // Decode JSON metadata for each result
            foreach ($results as &$result) {
                if (isset($result['metadata'])) {
                    $result['metadata'] = json_decode($result['metadata'], true);
                }
            }

            // Cache query result for 5 minutes
            wp_cache_set($cache_key, $results, 'inat_observations', 300);
        }

        return rest_ensure_response(['results' => $results]);
    }
