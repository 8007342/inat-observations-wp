<?php
    // REST API endpoints
    if (!defined('ABSPATH')) exit;

    add_action('rest_api_init', function () {
        error_log('[iNat Observations] Registering REST API routes');
        register_rest_route('inat/v1', '/observations', [
            'methods' => 'GET',
            'callback' => 'inat_obs_rest_get_observations',
            'permission_callback' => '__return_true',
        ]);
    });

    function inat_obs_rest_get_observations($request) {
        error_log('[iNat Observations] REST API endpoint /inat/v1/observations called');

        // TODO: accept filters, pagination, and return DB-backed results
        $args = $request->get_params();
        error_log('[iNat Observations] REST API request parameters: ' . json_encode($args));

        $data = inat_obs_fetch_observations(['per_page' => 50]);

        if (is_wp_error($data)) {
            error_log('[iNat Observations] REST API request failed: ' . $data->get_error_message());
            return new WP_Error(
                'inat_api_error',
                $data->get_error_message(),
                ['status' => 500]
            );
        }

        error_log('[iNat Observations] REST API request successful, returning response');
        return rest_ensure_response($data);
    }
