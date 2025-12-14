<?php
    /**
     * Plugin Name: inat-observations-wp
     * Plugin URI:  https://github.com/8007342/inat-observations-wp
     * Description: Fetch, cache, and display iNaturalist observations with metadata filtering.
     * Version:     0.1.0
     * Author:      Ayahuitl Tlatoani
     * License:     GPLv2 or later
     * Text Domain: inat-observations-wp
     */

    /**
     * Main Plugin File for iNaturalist Observations WordPress Plugin
     *
     * Entry Point and Bootstrap Loader
     * ================================
     * This file establishes the plugin's foundation in WordPress and is the sole entry point
     * for the inat-observations-wp plugin. It:
     * - Defines global constants for version, path, and URL
     * - Loads all plugin modules through the centralized init.php file
     * - Establishes the plugin's presence in WordPress via header comments
     *
     * Plugin Overview
     * ================
     * The iNaturalist Observations plugin fetches observation data from the public iNaturalist API,
     * caches results efficiently using WordPress transients, stores structured observation metadata
     * in a custom database table, and exposes this data to users and external systems via:
     * - Shortcodes: [inat_observations] for embedding in pages/posts
     * - AJAX Endpoints: Client-side data fetching and filtering
     * - REST API: /wp-json/inat/v1/observations for external integrations
     *
     * Modular Architecture
     * ====================
     * All plugin logic is organized into single-responsibility modules loaded via init.php:
     * - init.php: Plugin lifecycle (activation/deactivation hooks), WP-Cron scheduling
     * - api.php: iNaturalist API client with transient-based caching
     * - db-schema.php: Custom observation table schema and data persistence
     * - shortcode.php: [inat_observations] shortcode rendering and AJAX endpoint handler
     * - rest.php: WordPress REST API endpoint registration and response handling
     * - admin.php: WordPress admin interface (settings page, menu items)
     *
     * Module Dependencies (Loaded in Order)
     * =====================================
     * init.php loads all modules in dependency order to ensure proper functionality.
     * - api.php must load first (dependency for all other modules)
     * - db-schema.php loads before shortcode/rest (needed to define storage)
     * - shortcode.php and rest.php can load in any order (both use api.php)
     * - admin.php loads last (no dependencies)
     */

    // Exit immediately if this file is accessed directly (not through WordPress)
    if (!defined('ABSPATH')) {
        exit;
    }

    // Define global constants for plugin version, filesystem path, and URL
    // These are used throughout the plugin to reference assets and paths consistently
    define('INAT_OBS_VERSION', '0.1.0');
    define('INAT_OBS_PATH', plugin_dir_path(__FILE__));
    define('INAT_OBS_URL', plugin_dir_url(__FILE__));

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[iNat Observations] Plugin loading - Version: ' . INAT_OBS_VERSION);
    }

    // Load the initialization module which handles:
    // - Loading all other plugin modules
    // - Registering activation/deactivation hooks
    // - Scheduling WordPress cron jobs
    // - Registering action hooks for REST API and admin pages
    require_once INAT_OBS_PATH . 'includes/init.php';
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[iNat Observations] Plugin initialized successfully');
    }
