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
        $args = $request->get_params();
        $data = inat_obs_fetch_observations(['per_page' => 50]);
        return rest_ensure_response($data);
    }
