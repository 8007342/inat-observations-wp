<?php
    // DB schema and migration helpers.
    if (!defined('ABSPATH')) exit;

    function inat_obs_install_schema() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'inat_observations';

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

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // TODO: optionally create secondary tables for observation fields normalization.
    }

    /**
     * Store a batch of items into the DB.
     * Expected to receive decoded JSON 'results' array.
     */
    function inat_obs_store_items($items) {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        if (empty($items['results'])) return;

        foreach ($items['results'] as $r) {
            // TODO: parse observation_field_values and normalize into metadata JSON
            $meta = json_encode($r['observation_field_values'] ?? []);
            $wpdb->replace(
                $table,
                [
                    'id' => intval($r['id']),
                    'uuid' => sanitize_text_field($r['uuid'] ?? ''),
                    'observed_on' => $r['observed_on'] ?? null,
                    'species_guess' => sanitize_text_field($r['species_guess'] ?? ''),
                    'place_guess' => sanitize_text_field($r['place_guess'] ?? ''),
                    'metadata' => $meta,
                    'created_at' => current_time('mysql', 1),
                    'updated_at' => current_time('mysql', 1),
                ],
                ['%d','%s','%s','%s','%s','%s','%s']
            );
        }
    }
