<?php
    // Initialization for the plugin.
    if (!defined('ABSPATH')) exit;

    error_log('[iNat Observations] Loading plugin components...');

    // Load helpers
    require_once plugin_dir_path(__DIR__) . 'includes/api.php';
    require_once plugin_dir_path(__DIR__) . 'includes/db-schema.php';
    require_once plugin_dir_path(__DIR__) . 'includes/shortcode.php';
    require_once plugin_dir_path(__DIR__) . 'includes/rest.php';
    require_once plugin_dir_path(__DIR__) . 'includes/admin.php';

    error_log('[iNat Observations] All components loaded');

    // Activation hooks
    register_activation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_activate');
    register_deactivation_hook(plugin_dir_path(__DIR__) . 'inat-observations-wp.php', 'inat_obs_deactivate');

    function inat_obs_activate() {
        error_log('[iNat Observations] Plugin activation started');

        // Create DB schema
        inat_obs_install_schema();

        // Schedule daily refresh if not already scheduled
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

    function inat_obs_deactivate() {
        error_log('[iNat Observations] Plugin deactivation started');

        // Clear scheduled hooks
        $cleared = wp_clear_scheduled_hook('inat_obs_refresh');
        error_log('[iNat Observations] Cleared ' . $cleared . ' scheduled refresh job(s)');

        error_log('[iNat Observations] Plugin deactivation completed');
    }

    // Hook the refresh task
    add_action('inat_obs_refresh', 'inat_obs_refresh_job');

    function inat_obs_refresh_job() {
        error_log('[iNat Observations] Scheduled refresh job started');

        // TODO: call fetch and store functions
        // Example:
        // $items = inat_obs_fetch_observations(['per_page' => 200]);
        // inat_obs_store_items($items);

        error_log('[iNat Observations] Scheduled refresh job completed (no implementation yet)');
    }
