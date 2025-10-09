<?php
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    // TODO: remove custom tables and options on uninstall if user chooses.
    // Example:
    // global $wpdb;
    // $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'inat_observations');
