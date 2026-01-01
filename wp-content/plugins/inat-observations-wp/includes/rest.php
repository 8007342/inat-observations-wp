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
        // TODO: accept filters, pagination, and return DB-backed results
        $params = $request->get_params();

        // Validate and sanitize per_page parameter
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 50;
        $per_page = min(max($per_page, 1), 100); // Clamp between 1 and 100

        $data = inat_obs_fetch_observations(['per_page' => $per_page]);
        return rest_ensure_response($data);
    }
