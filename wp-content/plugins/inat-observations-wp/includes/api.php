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
        error_log('[iNat Observations] Fetching observations with args: ' . json_encode($args));

        // TODO: implement pagination, rate limiting, exponential backoff.
        $defaults = [
            'project' => getenv('INAT_PROJECT_SLUG') ?: 'project_slug_here',
            'per_page' => 100,
            'page' => 1,
        ];
        $opts = array_merge($defaults, $args);
        $base = 'https://api.inaturalist.org/v1/observations';
        $params = http_build_query([
            'project_id' => $opts['project'],
            'per_page' => $opts['per_page'],
            'page' => $opts['page'],
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $url = $base . '?' . $params;
        error_log('[iNat Observations] API URL: ' . $url);

        $transient_key = 'inat_obs_cache_' . md5($url);
        $cached = get_transient($transient_key);
        if ($cached) {
            error_log('[iNat Observations] Cache hit for key: ' . $transient_key);
            return $cached;
        }
        error_log('[iNat Observations] Cache miss - fetching from API');

        $headers = [
            'Accept' => 'application/json',
        ];
        // Optionally include token for higher rate limits or private data
        $token = getenv('INAT_API_TOKEN') ?: null;
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
            error_log('[iNat Observations] Using API token for authentication');
        } else {
            error_log('[iNat Observations] No API token provided');
        }

        error_log('[iNat Observations] Making API request...');
        $resp = wp_remote_get($url, ['headers' => $headers, 'timeout' => 20]);
        if (is_wp_error($resp)) {
            error_log('[iNat Observations] API request failed: ' . $resp->get_error_message());
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        error_log('[iNat Observations] API response code: ' . $code);

        if ($code !== 200) {
            error_log('[iNat Observations] API error - HTTP ' . $code . ': ' . substr($body, 0, 200));
            return new WP_Error('inat_api_error', 'Unexpected HTTP code ' . $code, ['body' => $body]);
        }

        $data = json_decode($body, true);
        $result_count = isset($data['results']) ? count($data['results']) : 0;
        error_log('[iNat Observations] Successfully fetched ' . $result_count . ' observations');

        // TODO: validate structure and extract pagination info
        // Cache for a short duration. Use longer caching when stored in DB.
        $cache_lifetime = intval(getenv('CACHE_LIFETIME') ?: 3600);
        set_transient($transient_key, $data, $cache_lifetime);
        error_log('[iNat Observations] Cached results for ' . $cache_lifetime . ' seconds');
        return $data;
    }

    // TODO: helper for paginated full fetch
    function inat_obs_fetch_all($opts = []) {
        // fetch pages until no more results
    }
