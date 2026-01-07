<?php
    // Initialization for the plugin.
    if (!defined('ABSPATH')) exit;

    // Load helpers
    require_once plugin_dir_path(__DIR__) . 'includes/api.php';
    require_once plugin_dir_path(__DIR__) . 'includes/db-schema.php';
    require_once plugin_dir_path(__DIR__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__DIR__) . 'includes/rest.php';
    require_once plugin_dir_path(__DIR__) . 'includes/admin.php';

    // Add custom cron schedule for 4 hours
    add_filter('cron_schedules', 'inat_obs_custom_cron_schedules');

    function inat_obs_custom_cron_schedules($schedules) {
        $schedules['fourhours'] = [
            'interval' => 4 * HOUR_IN_SECONDS,
            'display' => __('Every 4 Hours'),
        ];
        return $schedules;
    }

    // Activation hooks
    register_activation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_activate');
    register_deactivation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_deactivate');

    function inat_obs_activate() {
        // Create DB schema
        inat_obs_install_schema();
        // Schedule refresh based on settings
        inat_obs_schedule_refresh();
    }

    function inat_obs_schedule_refresh() {
        // Get refresh rate from settings
        $refresh_rate = get_option('inat_obs_refresh_rate', 'daily');

        // Map setting to cron schedule
        $schedule_map = [
            '4hours' => 'fourhours',
            'daily' => 'daily',
            'weekly' => 'weekly',
        ];
        $schedule = $schedule_map[$refresh_rate] ?? 'daily';

        // Clear any existing schedule
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'inat_obs_refresh');
        }

        // Schedule new event
        wp_schedule_event(time(), $schedule, 'inat_obs_refresh');
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
        $max_fetch_size = get_option('inat_obs_api_fetch_size', 2000);

        // Validate: at least one required
        if (empty($user_id) && empty($project_id)) {
            error_log('iNat Observations: Cannot refresh - no USER-ID or PROJECT-ID configured');
            return;
        }

        // Pagination loop to fetch observations up to max_fetch_size
        $page = 1;
        $per_page = 200; // Max allowed by iNaturalist API (fixed)
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

            error_log("iNat Observations: Fetched page $page - $results_count observations (total: $total_fetched / $max_fetch_size)");

            // Check if we've reached the configured limit
            if ($total_fetched >= $max_fetch_size) {
                error_log("iNat Observations: Reached configured limit of $max_fetch_size observations");
                break;
            }

            // Check if there are more pages available from iNaturalist
            // iNaturalist API: if results < per_page, we're on the last page
            if ($results_count < $per_page) {
                break;
            }

            $page++;

            // Rate limiting: sleep 1 second between requests (be polite to iNat API)
            // iNaturalist rate limits: 100 req/min (recommended: 60 req/min), 10,000 req/day
            // Our 1-second sleep = 60 req/min, staying at the recommended limit
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
