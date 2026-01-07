<?php
    // DB schema and migration helpers.
    if (!defined('ABSPATH')) exit;

    // Current database schema version
    define('INAT_OBS_DB_VERSION', '2.2');  // v2.2 adds normalized observation_fields table

    /**
     * Install or upgrade database schema.
     * Runs on plugin activation and version changes.
     */
    function inat_obs_install_schema() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'inat_observations';
        $current_version = get_option('inat_obs_db_version', '0');

        // Check if migration needed
        if (version_compare($current_version, INAT_OBS_DB_VERSION, '>=')) {
            return; // Already up to date
        }

        // Run migrations
        if (version_compare($current_version, '1.0', '<')) {
            inat_obs_create_initial_schema();
        }

        if (version_compare($current_version, '2.0', '<')) {
            inat_obs_migrate_to_v2();
        }

        if (version_compare($current_version, '2.1', '<')) {
            inat_obs_migrate_to_v2_1();
        }

        if (version_compare($current_version, '2.2', '<')) {
            inat_obs_migrate_to_v2_2();
        }

        // Update version
        update_option('inat_obs_db_version', INAT_OBS_DB_VERSION);
        error_log('iNat Observations: Database upgraded to v' . INAT_OBS_DB_VERSION);
    }

    /**
     * Create initial database schema (v1.0).
     */
    function inat_obs_create_initial_schema() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'inat_observations';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL,
            uuid varchar(100) DEFAULT '' NOT NULL,
            observed_on datetime DEFAULT NULL,
            species_guess varchar(255) DEFAULT '' NOT NULL,
            taxon_name varchar(255) DEFAULT '' NOT NULL,
            place_guess varchar(255) DEFAULT '' NOT NULL,
            metadata json DEFAULT NULL,
            photo_url varchar(500) DEFAULT '' NOT NULL,
            photo_attribution varchar(255) DEFAULT '' NOT NULL,
            photo_license varchar(20) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY observed_on (observed_on),
            KEY species_guess (species_guess),
            KEY taxon_name (taxon_name),
            KEY place_guess (place_guess),
            KEY uuid (uuid),
            KEY observed_species (observed_on, species_guess),
            KEY has_photo (photo_url(1))
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Set initial version
        update_option('inat_obs_db_version', '1.0');
        error_log('iNat Observations: Created initial schema v1.0');
    }

    /**
     * Migrate to v2.0: Add photo columns.
     * Runs only if upgrading from v1.0.
     */
    function inat_obs_migrate_to_v2() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Check if columns already exist (safety check)
        $columns = $wpdb->get_col("DESCRIBE $table_name", 0);

        if (!in_array('photo_url', $columns)) {
            // Add photo columns
            $wpdb->query("ALTER TABLE $table_name
                ADD COLUMN photo_url varchar(500) DEFAULT '' NOT NULL AFTER metadata,
                ADD COLUMN photo_attribution varchar(255) DEFAULT '' NOT NULL AFTER photo_url,
                ADD COLUMN photo_license varchar(20) DEFAULT '' NOT NULL AFTER photo_attribution,
                ADD INDEX has_photo (photo_url(1))
            ");

            error_log('iNat Observations: Migrated to v2.0 - added photo columns');
        } else {
            error_log('iNat Observations: Photo columns already exist, skipping migration');
        }
    }

    /**
     * Migrate to v2.1: Add taxon_name column (scientific name).
     * Runs only if upgrading from v2.0.
     */
    function inat_obs_migrate_to_v2_1() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Check if column already exists (safety check)
        $columns = $wpdb->get_col("DESCRIBE $table_name", 0);

        if (!in_array('taxon_name', $columns)) {
            // Add taxon_name column (scientific/binomial name)
            $wpdb->query("ALTER TABLE $table_name
                ADD COLUMN taxon_name varchar(255) DEFAULT '' NOT NULL AFTER species_guess,
                ADD INDEX taxon_name (taxon_name)
            ");

            error_log('iNat Observations: Migrated to v2.1 - added taxon_name (scientific name) column');
        } else {
            error_log('iNat Observations: taxon_name column already exists, skipping migration');
        }
    }

    /**
     * Migrate to v2.2: Create normalized observation_fields table.
     * Runs only if upgrading from v2.1.
     *
     * This is the CORE of the DNA filter feature - denormalizing ofvs array
     * into proper relational structure with prefix indexes for fast queries.
     */
    function inat_obs_migrate_to_v2_2() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observation_fields';

        // Check if table exists
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

        if (!$exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $obs_table = $wpdb->prefix . 'inat_observations';

            $sql = "CREATE TABLE $table_name (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                observation_id bigint(20) unsigned NOT NULL,
                field_id int,
                name varchar(255) NOT NULL,
                value text,
                datatype varchar(50),
                PRIMARY KEY (id),
                KEY observation_id (observation_id),
                KEY idx_name_prefix (name(50))
            ) $charset_collate ENGINE=InnoDB;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            error_log('iNat Observations: Migrated to v2.2 - created observation_fields table for DNA filtering');
        } else {
            error_log('iNat Observations: observation_fields table already exists, skipping migration');
        }
    }

    /**
     * Validate image URL is from authorized iNaturalist domains.
     *
     * Security: Prevents XSS via malicious image URLs.
     * Only allows images from trusted iNaturalist CDNs.
     *
     * @param string $url Raw URL from API
     * @return string|false Validated URL or false if invalid
     */
    function inat_obs_validate_image_url($url) {
        // Empty URLs are valid (no photo)
        if (empty($url)) {
            return '';
        }

        // Sanitize URL (blocks javascript:, data:, etc.)
        $url = esc_url_raw($url, ['http', 'https']);

        if (empty($url)) {
            error_log('iNat: Rejected non-HTTP(S) image URL');
            return false;
        }

        // Parse and validate domain
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');

        // Whitelist authorized domains (Tlatoani's security directive)
        $allowed_patterns = [
            'inaturalist.org',
            'inaturalist-open-data.s3.amazonaws.com',
            'staticflickr.com',
        ];

        foreach ($allowed_patterns as $pattern) {
            if ($host === $pattern || str_ends_with($host, '.' . $pattern)) {
                return $url; // SAFE - authorized domain
            }
        }

        error_log("iNat: Rejected image URL from unauthorized domain: $host");
        return false;
    }

    /**
     * Store a batch of items into the DB.
     * Expected to receive decoded JSON 'results' array.
     * Processes items one at a time to minimize memory usage.
     *
     * Image Usage Compliance (iNaturalist TOS):
     * - Images hotlinked from iNaturalist CDN (Amazon ODP covers bandwidth)
     * - Attribution stored for CC BY-NC license compliance
     * - URL validation prevents XSS attacks
     * - For science! 🧬
     */
    function inat_obs_store_items($items) {
        global $wpdb;
        $obs_table = $wpdb->prefix . 'inat_observations';
        $fields_table = $wpdb->prefix . 'inat_observation_fields';

        if (empty($items['results'])) return 0;

        $stored_count = 0;

        foreach ($items['results'] as $r) {
            $obs_id = intval($r['id']);

            // Keep old metadata for backward compatibility
            $meta = json_encode($r['observation_field_values'] ?? []);

            // Extract taxon name (scientific/binomial name)
            $taxon_name = !empty($r['taxon']['name']) ? sanitize_text_field($r['taxon']['name']) : '';

            // Extract photo data (first photo only for thumbnail)
            $photo_url = '';
            $photo_attribution = '';
            $photo_license = '';

            if (!empty($r['photos'][0])) {
                $photo = $r['photos'][0];

                // Get medium-sized photo URL (500px - perfect for thumbnails)
                $raw_url = $photo['url'] ?? '';
                $validated_url = inat_obs_validate_image_url($raw_url);

                if ($validated_url !== false) {
                    $photo_url = $validated_url;

                    // Extract attribution (photographer credit for CC BY-NC license)
                    $photo_attribution = sanitize_text_field($photo['attribution'] ?? '');

                    // Extract license code
                    $photo_license = sanitize_text_field($photo['license_code'] ?? 'C');
                }
            }

            // Store main observation
            $result = $wpdb->replace(
                $obs_table,
                [
                    'id' => $obs_id,
                    'uuid' => sanitize_text_field($r['uuid'] ?? ''),
                    'observed_on' => $r['observed_on'] ?? null,
                    'species_guess' => sanitize_text_field($r['species_guess'] ?? ''),
                    'taxon_name' => $taxon_name,
                    'place_guess' => sanitize_text_field($r['place_guess'] ?? ''),
                    'metadata' => $meta,
                    'photo_url' => $photo_url,
                    'photo_attribution' => $photo_attribution,
                    'photo_license' => $photo_license,
                    'created_at' => current_time('mysql', 1),
                    'updated_at' => current_time('mysql', 1),
                ],
                ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
            );

            if ($result === false) {
                continue;  // Skip on error
            }

            // NORMALIZE observation fields (ofvs array) into separate table
            // Delete old fields first to avoid duplicates
            $wpdb->delete($fields_table, ['observation_id' => $obs_id], ['%d']);

            // Extract ofvs array (observation field values)
            if (!empty($r['ofvs'])) {
                foreach ($r['ofvs'] as $field) {
                    $wpdb->insert(
                        $fields_table,
                        [
                            'observation_id' => $obs_id,
                            'field_id' => isset($field['field_id']) ? intval($field['field_id']) : null,
                            'name' => sanitize_text_field($field['name'] ?? ''),
                            'value' => sanitize_textarea_field($field['value'] ?? ''),
                            'datatype' => sanitize_text_field($field['datatype'] ?? '')
                        ],
                        ['%d', '%d', '%s', '%s', '%s']
                    );
                }
            }

            $stored_count++;

            // Free memory
            unset($meta);
        }

        // Flush to ensure writes are committed immediately
        $wpdb->flush();

        return $stored_count;
    }
