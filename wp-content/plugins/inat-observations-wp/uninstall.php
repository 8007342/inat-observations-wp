<?php
    /**
     * Plugin Uninstall Handler
     *
     * Complete Cleanup and Data Removal
     * ==================================
     * This file is executed when a user deletes the plugin from WordPress admin.
     * It performs comprehensive cleanup to remove all plugin-created data, tables,
     * and configuration from the WordPress installation.
     *
     * Lifecycle Context
     * =================
     * This file is part of the plugin lifecycle:
     *
     * 1. Activation (init.php - register_activation_hook)
     *    - Creates database table
     *    - Schedules daily cron job
     *
     * 2. Normal Operation
     *    - Plugin functions execute, data stored/cached
     *
     * 3. Deactivation (init.php - register_deactivation_hook)
     *    - Clears scheduled cron job
     *    - Data tables remain (preserves data)
     *
     * 4. Deletion (this file - uninstall.php)
     *    - User clicks "Delete" on plugin in WordPress admin
     *    - WordPress calls uninstall.php with WP_UNINSTALL_PLUGIN=true
     *    - All plugin data permanently removed (CANNOT BE UNDONE)
     *
     * Important Distinctions
     * ======================
     * - Deactivation ≠ Deletion: Deactivating leaves data intact
     * - Users can reactivate plugin and resume with existing data
     * - Deletion removes all traces of plugin (for clean removal)
     * - Some users want to preserve data when switching to another plugin
     *
     * Execution Context
     * =================
     * When this file is called:
     *   - WordPress constant WP_UNINSTALL_PLUGIN is defined as true
     *   - Full WordPress environment is loaded (database, functions available)
     *   - Executes with admin/root privileges
     *   - Runs in uninstall context only, not during normal operations
     *
     * Security and Safety
     * ===================
     * - WP_UNINSTALL_PLUGIN check prevents accidental execution
     * - Prevents running if someone accesses uninstall.php directly
     * - All operations are reversible before plugin deletion completes
     * - Error conditions logged for debugging
     *
     * Architecture Relationships
     * ==========================
     * Called by:
     *   - WordPress plugin management system (wpdb access, capabilities check)
     *
     * Data to Remove:
     *   - Custom table: wp_inat_observations (created in db-schema.php)
     *   - WordPress options: future wp_options entries
     *   - Transient cache: all inat_obs_cache_* transients
     *   - Cron jobs: inat_obs_refresh (already cleared on deactivation)
     *
     * Implementation Status: Stub (TODO)
     * ==================================
     * This file currently contains only the security check and documentation.
     * Full implementation needed to:
     *
     * Data Removal Steps (To Implement):
     * 1. Drop custom observation table
     *    - global $wpdb;
     *    - $table_name = $wpdb->prefix . 'inat_observations';
     *    - $wpdb->query("DROP TABLE IF EXISTS $table_name");
     *
     * 2. Drop secondary tables (when created)
     *    - wp_inat_observation_fields
     *    - wp_inat_observation_field_values
     *    - Use IF EXISTS for safety
     *
     * 3. Remove wp_options entries
     *    - delete_option('inat_observations_api_token');
     *    - delete_option('inat_observations_project_slug');
     *    - delete_option('inat_observations_cache_lifetime');
     *
     * 4. Clear transient cache
     *    - Query wp_options for transients
     *    - Limit to inat_obs_cache_* transients
     *    - Delete all matching entries
     *
     * 5. Logging and verification
     *    - Log completion to error_log
     *    - Verify tables were dropped
     *    - Record uninstall timestamp
     *
     * Future Enhancement: Data Preservation (TODO)
     * ============================================
     * Users may want to preserve observation data when uninstalling:
     * - Add admin setting: "Keep data when uninstalling"
     * - Skip table deletion if setting is enabled
     * - Allow data export before deletion
     * - Implement backup/download functionality
     *
     * Considerations and Best Practices
     * =================================
     * - DROP TABLE IF EXISTS prevents errors if called multiple times
     * - Keep operation logs for audit trail
     * - Consider user experience (do they want to backup data first?)
     * - Verify operations completed successfully
     * - Consider foreign key constraints (none currently)
     * - Cache clear should be thorough and complete
     * - Consider edge cases (partially deleted data)
     *
     * Example Implementation Pattern
     * ==============================
     * global $wpdb;
     * $table_name = $wpdb->prefix . 'inat_observations';
     * $wpdb->query('DROP TABLE IF EXISTS ' . $table_name);
     * delete_option('inat_observations_settings');
     * // Clear transients with prefix
     * $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%inat_obs_cache%'");
     * error_log('[iNat Observations] Plugin uninstalled - all data removed');
     */

    // Exit immediately if not called by WordPress during uninstall
    // This prevents accidental execution if someone calls uninstall.php directly
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }

    // TODO: Implement complete plugin uninstall cleanup
    // When implemented, this function should:
    // 1. Drop the wp_inat_observations custom table
    // 2. Remove any secondary tables created for observation fields
    // 3. Delete plugin options from wp_options table
    // 4. Clear transient cache entries
    // 5. Log completion for debugging
    //
    // Example implementation:
    // global $wpdb;
    // $table_name = $wpdb->prefix . 'inat_observations';
    // $wpdb->query('DROP TABLE IF EXISTS ' . $table_name);
    // delete_option('inat_observations_api_token');
    // delete_option('inat_observations_project_slug');
    // delete_option('inat_observations_cache_lifetime');
