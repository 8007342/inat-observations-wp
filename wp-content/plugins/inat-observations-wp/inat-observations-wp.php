<?php
    /**
     * Plugin Name: inat-observations-wp
     * Plugin URI:  https://github.com/8007342/inat-observations-wp
     * Description: Fetch, cache, and display iNaturalist observations with metadata filtering.
     * Version:     0.1.0
     * Author:      Ayahuitl Tlatoani
     * License:     GPLv2 or later
     * Text Domain: inat-observations-wp
     * Requires at least: 5.8
     * Requires PHP: 7.4
     *
     * GitHub Plugin URI: 8007342/inat-observations-wp
     * GitHub Branch: main
     * Primary Branch: main
     */

    if (!defined('ABSPATH')) {
        exit;
    }

    define('INAT_OBS_VERSION', '0.1.0');
    define('INAT_OBS_PATH', plugin_dir_path(__FILE__));
    define('INAT_OBS_URL', plugin_dir_url(__FILE__));

    // Autoload includes
    require_once INAT_OBS_PATH . 'includes/init.php';
