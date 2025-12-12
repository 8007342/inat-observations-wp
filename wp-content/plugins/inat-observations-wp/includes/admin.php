<?php
    // Admin pages and settings.
    if (!defined('ABSPATH')) exit;

    add_action('admin_menu', function () {
        error_log('[iNat Observations] Registering admin menu page');
        add_options_page('iNaturalist Observations', 'iNat Observations', 'manage_options', 'inat-observations', 'inat_obs_settings_page');
    });

    function inat_obs_settings_page() {
        error_log('[iNat Observations] Settings page accessed by user: ' . wp_get_current_user()->user_login);

        if (!current_user_can('manage_options')) {
            error_log('[iNat Observations] Access denied - user lacks manage_options capability');
            return;
        }

        error_log('[iNat Observations] Rendering settings page');
        // TODO: implement settings form to store INAT_API_TOKEN and project slug in options
        echo '<div class="wrap"><h1>iNaturalist Observations Settings</h1>';
        echo '<p>Settings UI not yet implemented. Edit <code>.env</code> or implement options storage.</p>';
        echo '</div>';
    }
