<?php
/**
 * Mock Observation Data for Integration Tests
 *
 * Provides realistic test data with:
 * - 10 observations total
 * - 5 with DNA observation fields
 * - Colliding names (multiple Amanita species)
 * - Varied locations
 * - Realistic metadata (photos, dates, coordinates)
 */

if (!defined('ABSPATH')) exit;

/**
 * Get mock observations for testing.
 *
 * @return array Array of mock observation data
 */
function get_mock_observations() {
    return [
        // Observation 1: Amanita muscaria with DNA
        [
            'id' => 1001,
            'species_guess' => 'Amanita muscaria',
            'taxon_name' => 'Amanita muscaria',
            'place_guess' => 'Seattle, WA',
            'observed_on' => '2026-01-01',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'photo_url' => 'https://static.inaturalist.org/photos/1001/medium.jpg',
            'photo_attribution' => 'Test User 1',
            'photo_license' => 'cc-by',
            'metadata' => json_encode([
                'quality_grade' => 'research',
                'observed_on_string' => '2026-01-01',
                'taxon_id' => 48715
            ])
        ],

        // Observation 2: Amanita phalloides with DNA
        [
            'id' => 1002,
            'species_guess' => 'Amanita phalloides',
            'taxon_name' => 'Amanita phalloides',
            'place_guess' => 'Portland, OR',
            'observed_on' => '2026-01-02',
            'latitude' => 45.5152,
            'longitude' => -122.6784,
            'photo_url' => 'https://static.inaturalist.org/photos/1002/medium.jpg',
            'photo_attribution' => 'Test User 2',
            'photo_license' => 'cc-by-nc',
            'metadata' => json_encode([
                'quality_grade' => 'research',
                'observed_on_string' => '2026-01-02',
                'taxon_id' => 48717
            ])
        ],

        // Observation 3: Amanita virosa with DNA
        [
            'id' => 1003,
            'species_guess' => 'Amanita virosa',
            'taxon_name' => 'Amanita virosa',
            'place_guess' => 'Seattle, WA',
            'observed_on' => '2026-01-03',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'photo_url' => 'https://static.inaturalist.org/photos/1003/medium.jpg',
            'photo_attribution' => 'Test User 3',
            'photo_license' => 'cc-by-sa',
            'metadata' => json_encode([
                'quality_grade' => 'research',
                'observed_on_string' => '2026-01-03',
                'taxon_id' => 48718
            ])
        ],

        // Observation 4: Boletus edulis with DNA
        [
            'id' => 1004,
            'species_guess' => 'Boletus edulis',
            'taxon_name' => 'Boletus edulis',
            'place_guess' => 'Vancouver, BC',
            'observed_on' => '2026-01-04',
            'latitude' => 49.2827,
            'longitude' => -123.1207,
            'photo_url' => 'https://static.inaturalist.org/photos/1004/medium.jpg',
            'photo_attribution' => 'Test User 4',
            'photo_license' => 'cc0',
            'metadata' => json_encode([
                'quality_grade' => 'research',
                'observed_on_string' => '2026-01-04',
                'taxon_id' => 48701
            ])
        ],

        // Observation 5: Cantharellus formosus with DNA
        [
            'id' => 1005,
            'species_guess' => 'Cantharellus formosus',
            'taxon_name' => 'Cantharellus formosus',
            'place_guess' => 'Portland, OR',
            'observed_on' => '2026-01-05',
            'latitude' => 45.5152,
            'longitude' => -122.6784,
            'photo_url' => 'https://static.inaturalist.org/photos/1005/medium.jpg',
            'photo_attribution' => 'Test User 5',
            'photo_license' => 'cc-by',
            'metadata' => json_encode([
                'quality_grade' => 'research',
                'observed_on_string' => '2026-01-05',
                'taxon_id' => 53961
            ])
        ],

        // Observation 6: Morchella esculenta (NO DNA)
        [
            'id' => 1006,
            'species_guess' => 'Morchella esculenta',
            'taxon_name' => 'Morchella esculenta',
            'place_guess' => 'Seattle, WA',
            'observed_on' => '2026-01-06',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'photo_url' => 'https://static.inaturalist.org/photos/1006/medium.jpg',
            'photo_attribution' => 'Test User 6',
            'photo_license' => 'cc-by-nc',
            'metadata' => json_encode([
                'quality_grade' => 'needs_id',
                'observed_on_string' => '2026-01-06',
                'taxon_id' => 47651
            ])
        ],

        // Observation 7: Pleurotus ostreatus (NO DNA)
        [
            'id' => 1007,
            'species_guess' => 'Pleurotus ostreatus',
            'taxon_name' => 'Pleurotus ostreatus',
            'place_guess' => 'Vancouver, BC',
            'observed_on' => '2026-01-07',
            'latitude' => 49.2827,
            'longitude' => -123.1207,
            'photo_url' => 'https://static.inaturalist.org/photos/1007/medium.jpg',
            'photo_attribution' => 'Test User 7',
            'photo_license' => 'cc-by-sa',
            'metadata' => json_encode([
                'quality_grade' => 'casual',
                'observed_on_string' => '2026-01-07',
                'taxon_id' => 47450
            ])
        ],

        // Observation 8: Lactarius deliciosus (NO DNA)
        [
            'id' => 1008,
            'species_guess' => 'Lactarius deliciosus',
            'taxon_name' => 'Lactarius deliciosus',
            'place_guess' => 'Portland, OR',
            'observed_on' => '2026-01-08',
            'latitude' => 45.5152,
            'longitude' => -122.6784,
            'photo_url' => 'https://static.inaturalist.org/photos/1008/medium.jpg',
            'photo_attribution' => 'Test User 8',
            'photo_license' => 'cc0',
            'metadata' => json_encode([
                'quality_grade' => 'needs_id',
                'observed_on_string' => '2026-01-08',
                'taxon_id' => 48743
            ])
        ],

        // Observation 9: Agaricus campestris (NO DNA)
        [
            'id' => 1009,
            'species_guess' => 'Agaricus campestris',
            'taxon_name' => 'Agaricus campestris',
            'place_guess' => 'Seattle, WA',
            'observed_on' => '2026-01-09',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'photo_url' => 'https://static.inaturalist.org/photos/1009/medium.jpg',
            'photo_attribution' => 'Test User 9',
            'photo_license' => 'C',
            'metadata' => json_encode([
                'quality_grade' => 'casual',
                'observed_on_string' => '2026-01-09',
                'taxon_id' => 48439
            ])
        ],

        // Observation 10: Unknown Species (NO DNA)
        [
            'id' => 1010,
            'species_guess' => '',  // Empty species_guess to test "Unknown Species" filter
            'taxon_name' => '',
            'place_guess' => 'Portland, OR',
            'observed_on' => '2026-01-10',
            'latitude' => 45.5152,
            'longitude' => -122.6784,
            'photo_url' => null,  // No photo
            'photo_attribution' => null,
            'photo_license' => null,
            'metadata' => json_encode([
                'quality_grade' => 'casual',
                'observed_on_string' => '2026-01-10',
                'taxon_id' => null
            ])
        ],
    ];
}

/**
 * Get mock DNA observation fields.
 *
 * @return array Array of observation_fields data
 */
function get_mock_dna_fields() {
    return [
        // DNA fields for observations 1-5
        ['observation_id' => 1001, 'name' => 'DNA Sequence ID', 'value' => 'GEN-2026-001'],
        ['observation_id' => 1002, 'name' => 'DNA Barcode', 'value' => 'ATCG1234'],
        ['observation_id' => 1003, 'name' => 'DNA Sample Number', 'value' => 'Sample-003'],
        ['observation_id' => 1004, 'name' => 'DNA GenBank Accession', 'value' => 'MZ123456'],
        ['observation_id' => 1005, 'name' => 'DNA Voucher', 'value' => 'Voucher-2026-005'],
    ];
}

/**
 * Get expected filter results for testing.
 *
 * @param string $filter_type Type of filter to test
 * @param mixed $filter_value Value to filter by
 * @return array Expected observation IDs
 */
function get_expected_filter_results($filter_type, $filter_value = null) {
    switch ($filter_type) {
        case 'species_amanita':
            // All Amanita species (colliding names)
            return [1001, 1002, 1003];

        case 'species_exact':
            // Single species exact match
            if ($filter_value === 'Amanita muscaria') {
                return [1001];
            }
            if ($filter_value === 'Boletus edulis') {
                return [1004];
            }
            return [];

        case 'location_seattle':
            // All observations in Seattle, WA
            return [1001, 1003, 1006, 1009];

        case 'location_portland':
            // All observations in Portland, OR
            return [1002, 1005, 1008, 1010];

        case 'location_vancouver':
            // All observations in Vancouver, BC
            return [1004, 1007];

        case 'has_dna':
            // All observations with DNA fields
            return [1001, 1002, 1003, 1004, 1005];

        case 'no_dna':
            // All observations without DNA fields
            return [1006, 1007, 1008, 1009, 1010];

        case 'unknown_species':
            // Observations with empty species_guess
            return [1010];

        case 'amanita_seattle_dna':
            // Combined: Amanita + Seattle + DNA
            return [1001, 1003];

        case 'all':
            // All observations
            return [1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009, 1010];

        default:
            return [];
    }
}

/**
 * Insert mock observations into database (for integration tests).
 *
 * @param wpdb $wpdb WordPress database object
 * @return int Number of observations inserted
 */
function insert_mock_observations($wpdb) {
    $table = $wpdb->prefix . 'inat_observations';
    $fields_table = $wpdb->prefix . 'inat_observation_fields';
    $count = 0;

    // Insert observations
    foreach (get_mock_observations() as $obs) {
        $wpdb->insert($table, $obs);
        $count++;
    }

    // Insert DNA fields
    foreach (get_mock_dna_fields() as $field) {
        $wpdb->insert($fields_table, $field);
    }

    return $count;
}

/**
 * Clean up mock observations from database (for integration tests).
 *
 * @param wpdb $wpdb WordPress database object
 */
function cleanup_mock_observations($wpdb) {
    $table = $wpdb->prefix . 'inat_observations';
    $fields_table = $wpdb->prefix . 'inat_observation_fields';

    // Delete mock observations (IDs 1001-1010)
    $wpdb->query("DELETE FROM $table WHERE id >= 1001 AND id <= 1010");
    $wpdb->query("DELETE FROM $fields_table WHERE observation_id >= 1001 AND observation_id <= 1010");
}
