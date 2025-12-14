<?php
    /**
     * Plugin Initialization and Lifecycle Management
     *
     * Central Hub for Module Loading and WordPress Hook Registration
     * ==============================================================
     * This module serves as the control center for the entire plugin, responsible for:
     * - Loading all plugin sub-modules in correct dependency order
     * - Registering WordPress lifecycle hooks (activation/deactivation)
     * - Setting up WordPress cron jobs for scheduled observation refresh
     * - Establishing the plugin's integration points with WordPress core
     *
     * Module Loading Sequence (Dependency Order)
     * ===========================================
     * Modules are loaded in strict order to ensure dependencies are satisfied:
     *
     * 1. api.php (First - Foundation Layer)
     *    - Provides iNaturalist API client functions
     *    - All other modules depend on: inat_obs_fetch_observations(), inat_obs_fetch_all()
     *
     * 2. db-schema.php (Storage Layer)
     *    - Defines custom database table structure
     *    - Provides storage functions: inat_obs_install_schema(), inat_obs_store_items()
     *    - Must load before shortcode/REST (which need storage functions)
     *
     * 3. shortcode.php (Front-End Layer)
     *    - Registers [inat_observations] shortcode handler
     *    - Sets up AJAX endpoint: wp_ajax_inat_obs_fetch
     *    - Depends on: api.php functions, INAT_OBS_VERSION constant
     *
     * 4. rest.php (External API Layer)
     *    - Registers REST API endpoint: GET /wp-json/inat/v1/observations
     *    - Depends on: api.php functions, WordPress REST API (hooks into rest_api_init)
     *
     * 5. admin.php (Admin Interface Layer)
     *    - Registers WordPress admin menu items
     *    - Renders admin settings page
     *    - No functional dependencies on other modules
     *
     * WordPress Hooks Registered
     * ==========================
     * - register_activation_hook: Calls inat_obs_activate() when plugin is activated
     * - register_deactivation_hook: Calls inat_obs_deactivate() when plugin is deactivated
     * - add_action('inat_obs_refresh'): Scheduled daily cron job for data refresh
     *
     * Lifecycle Flow
     * ==============
     * 1. User activates plugin in WordPress admin
     * 2. inat_obs_activate() runs: creates database table, schedules daily cron
     * 3. Daily cron triggers inat_obs_refresh() job to fetch fresh data
     * 4. User deactivates plugin
     * 5. inat_obs_deactivate() runs: clears scheduled cron (data table remains)
     * 6. User deletes plugin
     * 7. uninstall.php runs: permanently removes tables and settings
     */

    // Exit immediately if accessed directly (security check)
    if (!defined('ABSPATH')) exit;

    error_log('[iNat Observations] Loading plugin components...');

    // Load all plugin modules in dependency order
    // Each module is conditionally required to prevent conflicts
    require_once plugin_dir_path(__DIR__) . 'includes/api.php';
    require_once plugin_dir_path(__DIR__) . 'includes/db-schema.php';
    require_once plugin_dir_path(__DIR__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__DIR__) . 'includes/rest.php';
    require_once plugin_dir_path(__DIR__) . 'includes/admin.php';

    error_log('[iNat Observations] All components loaded');

    // Activation hooks
    register_activation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_activate');
    register_deactivation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_deactivate');

    /**
     * Plugin Activation Handler
     *
     * Executes critical setup tasks when the plugin is activated:
     * 1. Installs/creates the custom wp_inat_observations table
     * 2. Schedules the daily WordPress cron job for data refresh
     *
     * Called by: register_activation_hook (WordPress lifecycle)
     * Calls: inat_obs_install_schema() from db-schema.php
     *
     * Note: This only runs once during activation, not on every page load.
     * Use register_deactivation_hook to reverse these changes if needed.
     */
    function inat_obs_activate() {
        error_log('[iNat Observations] Plugin activation started');

        // Create or verify the custom database table for storing observations
        inat_obs_install_schema();

        // Schedule a daily WordPress cron job to refresh observation data
        // wp_next_scheduled checks if the job already exists to prevent duplicates
        if (!wp_next_scheduled('inat_obs_refresh')) {
            $scheduled = wp_schedule_event(time(), 'daily', 'inat_obs_refresh');
            if ($scheduled !== false) {
                error_log('[iNat Observations] Daily refresh job scheduled successfully');
            } else {
                error_log('[iNat Observations] Failed to schedule daily refresh job');
            }
        } else {
            error_log('[iNat Observations] Daily refresh job already scheduled');
        }

        error_log('[iNat Observations] Plugin activation completed');
    }

    /**
     * Plugin Deactivation Handler
     *
     * Executes cleanup tasks when the plugin is deactivated:
     * 1. Removes the scheduled WordPress cron job for data refresh
     *
     * Note: This does NOT delete the database table (data persists).
     * To fully uninstall and remove data, use the uninstall.php hook instead.
     *
     * Called by: register_deactivation_hook (WordPress lifecycle)
     */
    function inat_obs_deactivate() {
        error_log('[iNat Observations] Plugin deactivation started');

        // Remove the scheduled daily cron job to prevent execution when plugin is inactive
        // wp_clear_scheduled_hook returns the count of events cleared (usually 0 or 1)
        $cleared = wp_clear_scheduled_hook('inat_obs_refresh');
        error_log('[iNat Observations] Cleared ' . $cleared . ' scheduled refresh job(s)');

        error_log('[iNat Observations] Plugin deactivation completed');
    }

    // Register the callback function that executes when the scheduled cron job runs
    add_action('inat_obs_refresh', 'inat_obs_refresh_job');

    /**
     * Scheduled Daily Refresh Job
     *
     * Executes daily (via WordPress cron) to fetch fresh observations from iNaturalist
     * and store them in the local database. This keeps cached data current without
     * requiring real-time API calls for every page view.
     *
     * Current Status: Stub implementation - see TODO items below
     *
     * Triggered by: WordPress cron job 'inat_obs_refresh' scheduled during activation
     * Calls: inat_obs_fetch_observations() and inat_obs_store_items()
     *
     * TODO: Implement the following:
     * - Call inat_obs_fetch_observations() with appropriate pagination settings
     * - Handle pagination for projects with >100 observations
     * - Implement error handling and logging for API failures
     * - Consider implementing rate limiting to respect API quotas
     * - Parse and normalize observation_field_values metadata
     * - Implement exponential backoff for failed requests
     *
     * Example implementation (when complete):
     * $items = inat_obs_fetch_observations(['per_page' => 200]);
     * if (!is_wp_error($items)) {
     *     inat_obs_store_items($items);
     * }
     */
    function inat_obs_refresh_job() {
        error_log('[iNat Observations] Scheduled refresh job started');

        // TODO: Implement daily observation refresh logic
        // See comments above for implementation requirements and examples

        error_log('[iNat Observations] Scheduled refresh job completed (no implementation yet)');
    }
