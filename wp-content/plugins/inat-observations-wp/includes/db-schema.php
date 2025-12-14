<?php
    /**
     * Database Schema and Persistence Layer
     *
     * Data Storage and Retrieval Foundation
     * ======================================
     * This module manages the plugin's persistent data storage, creating and maintaining
     * a custom WordPress database table for caching iNaturalist observations. It provides
     * the bridge between API responses and local database storage.
     *
     * Exported Functions
     * ==================
     * - inat_obs_install_schema(): Create/upgrade database table (called during activation)
     * - inat_obs_store_items($items): Persist fetched observations to database
     * - inat_obs_sanitize_date($date): Validate and sanitize date strings
     *
     * Architecture Relationships
     * ==========================
     * Called by:
     *   - init.php: inat_obs_install_schema() called during plugin activation
     *   - init.php: inat_obs_store_items() should be called from inat_obs_refresh_job() (pending)
     *   - Future shortcode/rest: Could query database instead of API for performance
     *
     * Calls to (dependencies):
     *   - WordPress dbDelta() for table schema management
     *   - WordPress $wpdb->replace() and $wpdb->prepare() for safe data storage
     *   - WordPress sanitize_text_field() for input validation
     *
     * Database Design
     * ===============
     * Primary table: wp_inat_observations
     *
     * Table Purpose:
     *   - Caches observation data fetched from iNaturalist API
     *   - Stores denormalized fields for efficient filtering
     *   - Flexible JSON metadata column for extensibility
     *
     * Column Structure:
     *   - id (bigint PK): iNaturalist observation ID (unique identifier)
     *   - uuid (varchar): UUID from iNaturalist API
     *   - observed_on (datetime): When observation was made in nature
     *   - species_guess (varchar): User's species identification
     *   - place_guess (varchar): Human-readable location
     *   - metadata (json): Parsed observation_field_values from API
     *   - created_at (datetime): When record was first cached
     *   - updated_at (datetime): When record was last updated
     *   - Index on observed_on for date-range filtering
     *
     * Design Rationale:
     *   - iNaturalist ID as primary key ensures data integrity
     *   - Denormalized species_guess/place_guess enable quick filtering
     *   - JSON metadata column provides extensibility without schema migration
     *   - created_at/updated_at support data freshness tracking
     *
     * Future Improvements
     * ===================
     * - Create secondary tables for observation_field normalization
     * - Replace JSON storage with normalized relational design
     * - Enable better performance for field-based queries and filtering
     * - Support aggregation queries on observation field values
     */

    // Exit immediately if accessed directly (security check)
    if (!defined('ABSPATH')) exit;

    /**
     * Install or Upgrade Database Schema
     *
     * Creates the custom wp_inat_observations table if it doesn't exist.
     * Uses WordPress dbDelta() function to safely create/upgrade the table schema.
     * This function is idempotent and can be called multiple times without issues.
     *
     * Table Structure:
     * - id (bigint): iNaturalist observation ID, primary key
     * - uuid (varchar): Unique identifier from iNaturalist API
     * - observed_on (datetime): When the observation was made in nature
     * - species_guess (varchar): User's species identification guess
     * - place_guess (varchar): Human-readable location description
     * - metadata (json): Parsed observation_field_values from API (flexible schema)
     * - created_at (datetime): When record was first stored
     * - updated_at (datetime): When record was last updated
     * - Indexes: observed_on for date-based filtering
     *
     * Called by: inat_obs_activate() during plugin activation
     * Triggers: Once per plugin activation, not on every page load
     *
     * Error Handling:
     * - Logs success/failure to error_log for debugging
     * - Verifies table creation with subsequent SHOW TABLES query
     * - Handles charset/collation automatically via get_charset_collate()
     *
     * Future TODOs:
     * - Create secondary tables for observation_field_values normalization
     * - This would replace JSON storage with proper relational schema
     * - Would enable better performance for field-based filtering
     */
    function inat_obs_install_schema() {
        global $wpdb;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Installing database schema');
        }

        // Get WordPress database charset and collation for consistent encoding
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'inat_observations';

        // Define the complete table structure
        // Using IF NOT EXISTS to prevent errors on re-runs
        // Includes metadata JSON column for flexible observation field storage
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL,
            uuid varchar(100) DEFAULT '' NOT NULL,
            observed_on datetime DEFAULT NULL,
            species_guess varchar(255) DEFAULT '' NOT NULL,
            place_guess varchar(255) DEFAULT '' NOT NULL,
            metadata json DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY observed_on (observed_on)
        ) $charset_collate;";

        // Use WordPress's dbDelta function to safely create/update the table
        // dbDelta handles idempotency and proper schema comparison
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify the table was actually created (dbDelta can fail silently)
        // Use prepare() for safe table name comparison
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        if ($table_exists) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] Database table created/verified successfully');
            }
        } else {
            error_log('[iNat Observations] ERROR: Failed to create database table');
        }

        // TODO: Create secondary tables for observation_field_values normalization
        // This would improve query performance for field-based filtering
        // Structure: wp_inat_observation_fields with field_id, observation_id, value
    }

    /**
     * Store or Update a Batch of Observations in the Database
     *
     * Persists observation records from the iNaturalist API into the local database.
     * Uses REPLACE syntax for upserts - updates existing records or inserts new ones.
     *
     * Expected Input:
     * @param array $items Decoded JSON response from iNaturalist API with this structure:
     *                      {
     *                          'results': [
     *                              {
     *                                  'id': 12345,
     *                                  'uuid': 'abc-def-ghi',
     *                                  'observed_on': '2024-01-15',
     *                                  'species_guess': 'Homo sapiens',
     *                                  'place_guess': 'San Francisco, CA, USA',
     *                                  'observation_field_values': [...]
     *                              },
     *                              ...
     *                          ]
     *                      }
     *
     * Processing:
     * - Extracts observation_field_values array and stores as JSON in metadata column
     * - Sanitizes text fields using WordPress sanitize_text_field()
     * - Converts iNaturalist observation ID (int) to database primary key
     * - Records creation and update timestamps
     *
     * Database Behavior:
     * - Uses REPLACE statement (upsert) for efficiency
     * - Existing records (matching id) are updated with new data
     * - New records are inserted with creation timestamp
     * - Failures are logged individually but don't halt processing
     *
     * Called by: inat_obs_refresh_job() during cron (stub), manual refresh endpoints
     * Calls: WordPress $wpdb->replace() and sanitize_text_field()
     * Side Effects: Inserts/updates database records, logs results
     *
     * Error Handling:
     * - Silently skips items with empty/null results array
     * - Logs individual item failures with observation ID and MySQL error
     * - Returns summary counts (success/error) for monitoring
     *
     * TODO:
     * - Implement observation_field_values parsing and normalization
     * - Store parsed fields in secondary table for better queryability
     * - Add validation of required fields before insert
     * - Implement batch error recovery (partial success handling)
     * - Consider transaction wrapping for consistency on large batches
     */
    /**
     * Validate and sanitize date string for database storage
     *
     * @param string|null $date Date string from API
     * @return string|null Sanitized date or null if invalid
     */
    function inat_obs_sanitize_date($date) {
        if (empty($date)) {
            return null;
        }
        // Validate date format (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
        if (preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$/', $date)) {
            return sanitize_text_field($date);
        }
        return null;
    }

    function inat_obs_store_items($items) {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Early return if no data to process
        if (empty($items['results'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[iNat Observations] No items to store - results array is empty');
            }
            return;
        }

        $count = count($items['results']);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Storing ' . absint($count) . ' observations to database');
        }

        // Track success/failure for logging and debugging
        $success_count = 0;
        $error_count = 0;

        // Process each observation from the API response
        foreach ($items['results'] as $r) {
            // Validate observation ID is a positive integer
            $obs_id = isset($r['id']) ? absint($r['id']) : 0;
            if ($obs_id <= 0) {
                $error_count++;
                continue;
            }

            // Convert observation_field_values array to JSON for metadata column
            // Defaults to empty array if field is missing from API response
            // Use JSON_UNESCAPED_UNICODE for proper character encoding
            $meta = wp_json_encode($r['observation_field_values'] ?? [], JSON_UNESCAPED_UNICODE);

            // Use REPLACE to perform upsert - updates if id exists, inserts if new
            // This is more efficient than checking then insert/update separately
            // Sanitize all fields to prevent injection and enforce data integrity
            $result = $wpdb->replace(
                $table,
                [
                    'id' => $obs_id,
                    'uuid' => sanitize_text_field($r['uuid'] ?? ''),
                    'observed_on' => inat_obs_sanitize_date($r['observed_on'] ?? null),
                    'species_guess' => sanitize_text_field($r['species_guess'] ?? ''),
                    'place_guess' => sanitize_text_field($r['place_guess'] ?? ''),
                    'metadata' => $meta,
                    'created_at' => current_time('mysql', 1),
                    'updated_at' => current_time('mysql', 1),
                ],
                ['%d','%s','%s','%s','%s','%s','%s','%s']
            );

            // Track result - $wpdb->replace returns false on error, affected rows count on success
            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[iNat Observations] Failed to store observation - database error occurred');
                }
            }
        }

        // Log completion with summary statistics
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[iNat Observations] Database storage complete - Success: ' . absint($success_count) . ', Errors: ' . absint($error_count));
        }
    }
