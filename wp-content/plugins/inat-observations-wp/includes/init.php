<?php
    // Initialization for the plugin.
    if (!defined('ABSPATH')) exit;

    // Load helpers
    require_once plugin_dir_path(__DIR__) . 'includes/helpers.php';  // TODO-BUG-002: Unified normalization
    require_once plugin_dir_path(__DIR__) . 'includes/api.php';
    require_once plugin_dir_path(__DIR__) . 'includes/db-schema.php';
    require_once plugin_dir_path(__DIR__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__DIR__) . 'includes/rest.php';
    require_once plugin_dir_path(__DIR__) . 'includes/admin.php';
    require_once plugin_dir_path(__DIR__) . 'includes/autocomplete.php';

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
        $refresh_rate = get_option('inat_obs_refresh_rate', 'daily');
        $api_token = get_option('inat_obs_api_token', '');

        // Log configuration at start
        error_log('========================================');
        error_log('iNat Observations: Starting refresh job');
        error_log('========================================');
        error_log('Configuration:');
        error_log('  Refresh Rate: ' . $refresh_rate);
        error_log('  Max Fetch Size: ' . $max_fetch_size . ' observations');
        error_log('  User ID: ' . ($user_id ?: '(not set)'));
        error_log('  Project ID: ' . ($project_id ?: '(not set)'));

        // Log API token (masked for security)
        if ($api_token) {
            $masked_token = substr($api_token, 0, 3) . str_repeat('*', min(20, strlen($api_token) - 3));
            error_log('  API Token: ' . $masked_token);
        } else {
            error_log('  API Token: (not set - using unauthenticated requests)');
        }
        error_log('----------------------------------------');

        // Validate: at least one required
        if (empty($user_id) && empty($project_id)) {
            error_log('ERROR: Cannot refresh - no USER-ID or PROJECT-ID configured');
            error_log('========================================');
            return;
        }

        // Pagination loop to fetch observations up to max_fetch_size
        $page = 1;
        $per_page = 200; // Max allowed by iNaturalist API (fixed)
        $total_fetched = 0;
        $total_stored = 0;
        $start_time = microtime(true);

        do {
            // Build query args for current page
            $args = [
                'per_page' => $per_page,
                'page' => $page,
                'no_cache' => true, // Skip cache during refresh to get fresh data
            ];
            if (!empty($user_id)) {
                $args['user_id'] = $user_id;
            }
            if (!empty($project_id)) {
                $args['project'] = $project_id;
            }

            $page_start_time = microtime(true);

            // Fetch observations from iNaturalist API
            error_log("Fetching page $page (requesting $per_page observations)...");
            $data = inat_obs_fetch_observations($args);

            if (is_wp_error($data)) {
                error_log('ERROR: API fetch failed on page ' . $page . ' - ' . $data->get_error_message());
                error_log('========================================');
                break;
            }

            $results_count = count($data['results'] ?? []);
            $page_fetch_time = microtime(true) - $page_start_time;

            error_log("  ✓ Received $results_count observations from iNaturalist (took " . round($page_fetch_time, 2) . "s)");

            // Store in database immediately (uses REPLACE for upserts)
            $store_start_time = microtime(true);
            $stored_count = inat_obs_store_items($data);
            $store_time = microtime(true) - $store_start_time;

            error_log("  ✓ Stored $stored_count observations to database (took " . round($store_time, 2) . "s)");

            if ($stored_count !== $results_count) {
                error_log("  WARNING: Stored count ($stored_count) != received count ($results_count)");
            }

            $total_fetched += $results_count;
            $total_stored += $stored_count;

            // Calculate memory usage
            $memory_used = round(memory_get_usage() / 1024 / 1024, 2);
            $memory_peak = round(memory_get_peak_usage() / 1024 / 1024, 2);

            error_log("Progress: $total_fetched / $max_fetch_size observations | Memory: {$memory_used}MB (peak: {$memory_peak}MB)");

            // Free memory explicitly
            unset($data);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            // Check if we've reached the configured limit
            if ($total_fetched >= $max_fetch_size) {
                error_log("✓ Reached configured limit of $max_fetch_size observations");
                break;
            }

            // Check if there are more pages available from iNaturalist
            // iNaturalist API: if results < per_page, we're on the last page
            if ($results_count < $per_page) {
                error_log("✓ Reached end of available observations ($results_count < $per_page on page $page)");
                break;
            }

            $page++;

            // Rate limiting: sleep 1 second between requests (be polite to iNat API)
            // iNaturalist rate limits: 100 req/min (recommended: 60 req/min), 10,000 req/day
            // Our 1-second sleep = 60 req/min, staying at the recommended limit
            if ($results_count === $per_page) {
                error_log("Rate limiting: sleeping 1 second before next request...");
                sleep(1);
            }

        } while (true);

        // Calculate final stats
        $total_time = microtime(true) - $start_time;
        $avg_time_per_page = $page > 0 ? round($total_time / $page, 2) : 0;
        $final_memory = round(memory_get_usage() / 1024 / 1024, 2);
        $peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2);

        // Query total observation count from database
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        // SECURITY: $table uses WordPress prefix + hardcoded table name (safe)
        $total_in_db = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table}"));

        // Update WordPress options
        update_option('inat_obs_last_refresh', current_time('mysql'));
        update_option('inat_obs_last_refresh_count', $total_fetched);
        update_option('inat_obs_total_count', $total_in_db);

        // Invalidate autocomplete caches (new data available)
        inat_obs_invalidate_autocomplete_cache();

        // Log final summary
        error_log('========================================');
        error_log('Refresh Summary:');
        error_log('  Total Observations: ' . $total_fetched . ' fetched, ' . $total_stored . ' stored');
        error_log('  Total in Database: ' . $total_in_db . ' observations');
        error_log('  Total Pages: ' . $page);
        error_log('  Total Time: ' . round($total_time, 2) . 's (' . round($total_time / 60, 2) . ' minutes)');
        error_log('  Avg Time/Page: ' . $avg_time_per_page . 's');
        error_log('  Final Memory: ' . $final_memory . 'MB');
        error_log('  Peak Memory: ' . $peak_memory . 'MB');
        error_log('  Timestamp: ' . current_time('mysql'));
        error_log('========================================');
    }

    // Security headers (S-LOW-002)
    function inat_obs_security_headers() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // TODO: CSP disabled temporarily - was too strict and broke WordPress themes/plugins
        // XSS protection relies on URL validation in inat_obs_validate_image_url() instead
        // Future: Add relaxed CSP after thorough testing with common themes
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
