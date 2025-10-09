<?php
    /**
     * Plugin Name: inat-observations-wp
     * Plugin URI:  https://example.org/
     * Description: Fetch, cache, and display iNaturalist observations with metadata filtering.
     * Version:     0.1.0
     * Author:      Your Name
     * License:     GPLv2 or later
     * Text Domain: inat-observations-wp
     */

    if (!defined('ABSPATH')) {
        exit;
    }

    define('INAT_OBS_VERSION', '0.1.0');
    define('INAT_OBS_PATH', plugin_dir_path(__FILE__));
    define('INAT_OBS_URL', plugin_dir_url(__FILE__));

    // Autoload includes
    require_once INAT_OBS_PATH . 'includes/init.php';
