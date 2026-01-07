<?php
    // Initialization for the plugin.
    if (!defined('ABSPATH')) exit;

    // Load helpers
    require_once plugin_dir_path(__DIR__) . 'includes/api.php';
    require_once plugin_dir_path(__DIR__) . 'includes/db-schema.php';
    require_once plugin_dir_path(__DIR__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__DIR__) . 'includes/rest.php';
    require_once plugin_dir_path(__DIR__) . 'includes/admin.php';

    // Activation hooks
    register_activation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_activate');
    register_deactivation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_deactivate');

    function inat_obs_activate() {
        // Create DB schema
        inat_obs_install_schema();
        // Schedule daily refresh if not already scheduled
        if (!wp_next_scheduled('inat_obs_refresh')) {
            wp_schedule_event(time(), 'daily', 'inat_obs_refresh');
        }
    }

    function inat_obs_deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('inat_obs_refresh');
    }

    // Hook the refresh task
    add_action('inat_obs_refresh', 'inat_obs_refresh_job');

    function inat_obs_refresh_job() {
        // Get settings
        $user_id = get_option('inat_obs_user_id', '');
        $project_id = get_option('inat_obs_project_id', INAT_OBS_DEFAULT_PROJECT_ID);

        // Validate: at least one required
        if (empty($user_id) && empty($project_id)) {
            error_log('iNat Observations: Cannot refresh - no USER-ID or PROJECT-ID configured');
            return;
        }

        // Pagination loop to fetch ALL observations
        $page = 1;
        $per_page = 200; // Max allowed by iNaturalist API
        $total_fetched = 0;

        do {
            // Build query args for current page
            $args = ['per_page' => $per_page, 'page' => $page];
            if (!empty($user_id)) {
                $args['user_id'] = $user_id;
            }
            if (!empty($project_id)) {
                $args['project'] = $project_id;
            }

            // Fetch observations from iNaturalist API
            $data = inat_obs_fetch_observations($args);
            if (is_wp_error($data)) {
                error_log('iNat Observations: API fetch failed on page ' . $page . ' - ' . $data->get_error_message());
                break;
            }

            // Store in database (uses REPLACE for upserts)
            inat_obs_store_items($data);

            $results_count = count($data['results'] ?? []);
            $total_fetched += $results_count;

            error_log("iNat Observations: Fetched page $page - $results_count observations");

            // Check if there are more pages
            // iNaturalist API: if results < per_page, we're on the last page
            if ($results_count < $per_page) {
                break;
            }

            $page++;

            // Rate limiting: sleep 1 second between requests (be polite to iNat API)
            if ($results_count === $per_page) {
                sleep(1);
            }

        } while (true);

        // Log success with total count
        update_option('inat_obs_last_refresh', current_time('mysql'));
        update_option('inat_obs_last_refresh_count', $total_fetched);

        error_log("iNat Observations: Refresh completed - fetched $total_fetched observations across $page page(s)");
    }

    // Security headers (S-LOW-002)
    function inat_obs_security_headers() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    add_action('send_headers', 'inat_obs_security_headers');

    // HTTPS enforcement (S-HIGH-002)
    function inat_obs_enforce_https() {
        // Only enforce on production environments and when plugin is active on frontend
        if (!is_ssl() && !is_admin() && defined('WP_ENV') && WP_ENV === 'production') {
            wp_die(
                esc_html('This plugin requires HTTPS for secure operation. Please enable SSL on your site.'),
                esc_html('HTTPS Required'),
                ['response' => 403]
            );
        }
    }
    add_action('init', 'inat_obs_enforce_https');
