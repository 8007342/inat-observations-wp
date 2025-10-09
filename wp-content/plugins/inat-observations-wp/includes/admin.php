<?php
    // Admin pages and settings.
    if (!defined('ABSPATH')) exit;

    add_action('admin_menu', function () {
        add_options_page('iNaturalist Observations', 'iNat Observations', 'manage_options', 'inat-observations', 'inat_obs_settings_page');
    });

    function inat_obs_settings_page() {
        if (!current_user_can('manage_options')) return;
        // TODO: implement settings form to store INAT_API_TOKEN and project slug in options
        echo '<div class="wrap"><h1>iNaturalist Observations Settings</h1>';
        echo '<p>Settings UI not yet implemented. Edit <code>.env</code> or implement options storage.</p>';
        echo '</div>';
    }
