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
        // TODO: call fetch and store functions
        // Example:
        // $items = inat_obs_fetch_observations(['per_page' => 200]);
        // inat_obs_store_items($items);
    }
