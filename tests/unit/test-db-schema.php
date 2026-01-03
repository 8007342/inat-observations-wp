<?php
/**
 * Database Schema Unit Tests
 *
 * @package inat-observations-wp
 */

class Test_Inat_DB_Schema extends WP_UnitTestCase {

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        // Ensure table exists before each test
        inat_obs_install_schema();
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $wpdb->query("TRUNCATE TABLE $table");
        parent::tearDown();
    }

    /**
     * Test table creation
     */
    public function test_table_creation() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertEquals($table, $table_exists, 'Database table should exist');
    }

    /**
     * Test table has correct columns
     */
    public function test_table_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        $columns = $wpdb->get_results("DESCRIBE $table");
        $column_names = array_column($columns, 'Field');

        $expected_columns = ['id', 'uuid', 'observed_on', 'species_guess', 'place_guess', 'metadata', 'created_at', 'updated_at'];

        foreach ($expected_columns as $expected) {
            $this->assertContains($expected, $column_names, "Column '$expected' should exist in table");
        }
    }

    /**
     * Test storing observations
     */
    public function test_store_items() {
        $items = [
            'results' => [
                [
                    'id' => 12345,
                    'uuid' => 'test-uuid-1',
                    'observed_on' => '2024-01-15',
                    'species_guess' => 'Quercus rubra',
                    'place_guess' => 'New York',
                    'observation_field_values' => [
                        ['name' => 'Height', 'value' => '10m'],
                    ],
                ],
            ],
        ];

        inat_obs_store_items($items);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            12345
        ));

        $this->assertNotNull($result, 'Observation should be stored in database');
        $this->assertEquals('Quercus rubra', $result->species_guess);
        $this->assertEquals('New York', $result->place_guess);
    }

    /**
     * Test SQL injection protection
     */
    public function test_sql_injection_protection() {
        $malicious = [
            'results' => [
                [
                    'id' => 99999,
                    'uuid' => 'test-uuid-malicious',
                    'species_guess' => "'; DROP TABLE wp_inat_observations; --",
                    'place_guess' => "1' OR '1'='1",
                ],
            ],
        ];

        inat_obs_store_items($malicious);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Table should still exist
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertEquals($table, $table_exists, 'Table should not be dropped by SQL injection');

        // Malicious input should be sanitized
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 99999));
        $this->assertNotNull($result, 'Record should exist');
        // The malicious SQL should be stored as plain text, not executed
        $this->assertStringContainsString('DROP TABLE', $result->species_guess);
    }

    /**
     * Test upsert behavior (replace on duplicate key)
     */
    public function test_upsert_behavior() {
        $item_v1 = [
            'results' => [
                [
                    'id' => 11111,
                    'uuid' => 'test-uuid-11111',
                    'species_guess' => 'Original Name',
                    'place_guess' => 'Original Place',
                ],
            ],
        ];

        inat_obs_store_items($item_v1);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $result1 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 11111));
        $this->assertEquals('Original Name', $result1->species_guess);

        // Update same ID with new data
        $item_v2 = [
            'results' => [
                [
                    'id' => 11111,
                    'uuid' => 'test-uuid-11111-updated',
                    'species_guess' => 'Updated Name',
                    'place_guess' => 'Updated Place',
                ],
            ],
        ];

        inat_obs_store_items($item_v2);

        $result2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 11111));
        $this->assertEquals('Updated Name', $result2->species_guess);

        // Should be only one row
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE id = %d", 11111));
        $this->assertEquals(1, $count, 'Should have exactly one record (upsert behavior)');
    }

    /**
     * Test storing multiple items
     */
    public function test_store_multiple_items() {
        $items = [
            'results' => [
                [
                    'id' => 1001,
                    'uuid' => 'uuid-1001',
                    'species_guess' => 'Species A',
                    'place_guess' => 'Place A',
                ],
                [
                    'id' => 1002,
                    'uuid' => 'uuid-1002',
                    'species_guess' => 'Species B',
                    'place_guess' => 'Place B',
                ],
                [
                    'id' => 1003,
                    'uuid' => 'uuid-1003',
                    'species_guess' => 'Species C',
                    'place_guess' => 'Place C',
                ],
            ],
        ];

        inat_obs_store_items($items);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE id IN (1001, 1002, 1003)");

        $this->assertEquals(3, $count, 'Should store all three items');
    }

    /**
     * Test handling of empty results array
     */
    public function test_store_empty_results() {
        $items = [
            'results' => [],
        ];

        // Should not throw error
        inat_obs_store_items($items);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");

        $this->assertEquals(0, $count, 'Should have no records when storing empty results');
    }

    /**
     * Test metadata JSON storage
     */
    public function test_metadata_json_storage() {
        $items = [
            'results' => [
                [
                    'id' => 2001,
                    'uuid' => 'uuid-2001',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Place',
                    'observation_field_values' => [
                        ['name' => 'Height', 'value' => '5m'],
                        ['name' => 'DBH', 'value' => '30cm'],
                    ],
                ],
            ],
        ];

        inat_obs_store_items($items);

        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", 2001));

        $this->assertNotNull($result->metadata, 'Metadata should be stored');
        $metadata = json_decode($result->metadata, true);
        $this->assertIsArray($metadata, 'Metadata should be valid JSON');
        $this->assertCount(2, $metadata, 'Should have two observation field values');
    }
}
