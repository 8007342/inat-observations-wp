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
