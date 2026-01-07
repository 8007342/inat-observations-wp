<?php
    // API utilities: fetching observations from iNaturalist.
    if (!defined('ABSPATH')) exit;

    /**
     * Fetch observations from iNaturalist.
     *
     * @param array $args Options like 'project', 'per_page', 'page'
     * @return array Decoded JSON results or WP_Error
     */
    function inat_obs_fetch_observations($args = []) {
        // TODO: implement pagination, rate limiting, exponential backoff.
        $defaults = [
            'per_page' => 100,
            'page' => 1,
        ];
        $opts = array_merge($defaults, $args);
        $base = 'https://api.inaturalist.org/v1/observations';

        // Build query parameters
        $params_array = [
            'per_page' => $opts['per_page'],
            'page' => $opts['page'],
            'order' => 'desc',
            'order_by' => 'created_at',
        ];

        // Add user_id if provided
        if (!empty($opts['user_id'])) {
            $params_array['user_id'] = $opts['user_id'];
        }

        // Add project_id if provided
        if (!empty($opts['project'])) {
            $params_array['project_id'] = $opts['project'];
        }

        $params = http_build_query($params_array);
        $url = $base . '?' . $params;

        $transient_key = 'inat_obs_cache_' . md5($url);
        $cached = get_transient($transient_key);
        if ($cached) {
            return $cached;
        }

        $headers = [
            'Accept' => 'application/json',
        ];
        // Optionally include token for authenticated requests
        // Token from settings takes precedence over environment variable (for backwards compatibility)
        $token = get_option('inat_obs_api_token', '') ?: getenv('INAT_API_TOKEN') ?: null;
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $resp = wp_remote_get($url, ['headers' => $headers, 'timeout' => 20]);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        if ($code !== 200) {
            return new WP_Error('inat_api_error', 'Unexpected HTTP code ' . $code, ['body' => $body]);
        }

        $data = json_decode($body, true);
        // TODO: validate structure and extract pagination info
        // Cache for a short duration. Use longer caching when stored in DB.
        set_transient($transient_key, $data, intval(getenv('CACHE_LIFETIME') ?: 3600));
        return $data;
    }

    // TODO: helper for paginated full fetch
    function inat_obs_fetch_all($opts = []) {
        // fetch pages until no more results
    }
