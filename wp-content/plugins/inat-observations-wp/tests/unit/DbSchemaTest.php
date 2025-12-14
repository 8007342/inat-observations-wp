<?php
/**
 * Unit Tests for Database Schema Module (db-schema.php)
 *
 * Tests for database table creation and observation storage including:
 * - Schema installation and table creation
 * - Data storage with proper sanitization
 * - Upsert behavior (insert vs update)
 * - Handling of malformed/incomplete data
 * - Metadata JSON serialization
 * - Database error handling
 *
 * // Parameters flow
 * // Through validation's fine mesh—
 * // Null shall not pass here
 */

class INAT_OBS_DbSchemaTest extends INAT_OBS_TestCase {

    /**
     * Test schema installation creates table successfully
     *
     * Verifies that inat_obs_install_schema() creates the
     * wp_inat_observations table with correct structure.
     */
    public function test_install_schema_creates_table() {
        global $wpdb;

        // Arrange: Drop table if exists to ensure clean state
        $table_name = $wpdb->prefix . 'inat_observations';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Verify table doesn't exist
        $before = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $this->assertNull($before, 'Table should not exist before installation');

        // Act: Install schema
        inat_obs_install_schema();

        // Assert: Verify table was created
        $after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $this->assertEquals($table_name, $after, 'Table should be created');
    }

    /**
     * Test schema installation is idempotent
     *
     * Verifies that calling inat_obs_install_schema() multiple times
     * doesn't cause errors or duplicate tables.
     */
    public function test_install_schema_is_idempotent() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Act: Install schema multiple times
        inat_obs_install_schema();
        inat_obs_install_schema();
        inat_obs_install_schema();

        // Assert: Table still exists and no errors
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $this->assertEquals($table_name, $result);

        // Verify table structure is correct
        $columns = $wpdb->get_results("DESCRIBE $table_name");
        $column_names = array_map(function($col) {
            return $col->Field;
        }, $columns);

        $this->assertContains('id', $column_names);
        $this->assertContains('uuid', $column_names);
        $this->assertContains('observed_on', $column_names);
        $this->assertContains('species_guess', $column_names);
        $this->assertContains('place_guess', $column_names);
        $this->assertContains('metadata', $column_names);
        $this->assertContains('created_at', $column_names);
        $this->assertContains('updated_at', $column_names);
    }

    /**
     * Test storing valid observations successfully
     *
     * Verifies that inat_obs_store_items() correctly inserts
     * observation data into the database.
     */
    public function test_store_items_inserts_observations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create sample API response
        $items = $this->create_sample_api_response(3);

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify records inserted
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(3, $count, 'Should insert 3 observations');

        // Verify first record data
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1001", ARRAY_A);
        $this->assertNotNull($record);
        $this->assertEquals('uuid-test-0001', $record['uuid']);
        $this->assertEquals('Test Species 1', $record['species_guess']);
        $this->assertEquals('Test Location 1', $record['place_guess']);
        $this->assertEquals('2024-01-01', $record['observed_on']);
    }

    /**
     * Test storing items with empty results array
     *
     * Verifies that the function handles empty results gracefully
     * without attempting to insert anything.
     */
    public function test_store_items_handles_empty_results() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create empty response
        $items = ['results' => []];

        // Act: Store empty items (should not error)
        inat_obs_store_items($items);

        // Assert: No records inserted
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(0, $count);
    }

    /**
     * Test storing items with null results
     *
     * Verifies that the function handles missing results key
     * without crashing.
     */
    public function test_store_items_handles_null_results() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create response without results key
        $items = [];

        // Act: Store items with missing results (should not error)
        inat_obs_store_items($items);

        // Assert: No records inserted, no errors
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(0, $count);
    }

    /**
     * Test upsert behavior - insert new records
     *
     * Verifies that REPLACE works correctly for new records
     * (behaves like INSERT).
     */
    public function test_store_items_upsert_insert() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation
        $items = [
            'results' => [
                [
                    'id' => 999,
                    'uuid' => 'test-uuid-999',
                    'observed_on' => '2024-01-15',
                    'species_guess' => 'Original Species',
                    'place_guess' => 'Original Location',
                    'observation_field_values' => [
                        ['field' => 'test', 'value' => 'original']
                    ],
                ],
            ],
        ];

        // Act: Store items (first time - INSERT)
        inat_obs_store_items($items);

        // Assert: Verify record inserted
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 999", ARRAY_A);
        $this->assertNotNull($record);
        $this->assertEquals('Original Species', $record['species_guess']);
        $this->assertEquals('Original Location', $record['place_guess']);
    }

    /**
     * Test upsert behavior - update existing records
     *
     * Verifies that REPLACE works correctly for existing records
     * (behaves like UPDATE).
     */
    public function test_store_items_upsert_update() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Insert initial record
        $initial = [
            'results' => [
                [
                    'id' => 888,
                    'uuid' => 'test-uuid-888',
                    'observed_on' => '2024-01-10',
                    'species_guess' => 'Original Species',
                    'place_guess' => 'Original Location',
                    'observation_field_values' => [],
                ],
            ],
        ];
        inat_obs_store_items($initial);

        // Act: Update with new data (same id)
        $updated = [
            'results' => [
                [
                    'id' => 888,
                    'uuid' => 'test-uuid-888-modified',
                    'observed_on' => '2024-01-11',
                    'species_guess' => 'Updated Species',
                    'place_guess' => 'Updated Location',
                    'observation_field_values' => [
                        ['field' => 'new', 'value' => 'data']
                    ],
                ],
            ],
        ];
        inat_obs_store_items($updated);

        // Assert: Verify record updated (not duplicated)
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE id = 888");
        $this->assertEquals(1, $count, 'Should only have one record with id=888');

        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 888", ARRAY_A);
        $this->assertEquals('Updated Species', $record['species_guess']);
        $this->assertEquals('Updated Location', $record['place_guess']);
        $this->assertEquals('test-uuid-888-modified', $record['uuid']);
    }

    /**
     * Test metadata JSON serialization
     *
     * Verifies that observation_field_values are correctly
     * serialized to JSON in the metadata column.
     */
    public function test_store_items_serializes_metadata_json() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with complex metadata
        $items = [
            'results' => [
                [
                    'id' => 777,
                    'uuid' => 'test-uuid-777',
                    'observed_on' => '2024-01-20',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Location',
                    'observation_field_values' => [
                        [
                            'observation_field' => ['id' => 1, 'name' => 'Field 1'],
                            'value' => 'Value 1',
                        ],
                        [
                            'observation_field' => ['id' => 2, 'name' => 'Field 2'],
                            'value' => 'Value 2',
                        ],
                    ],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify metadata stored as JSON
        $metadata = $wpdb->get_var("SELECT metadata FROM $table_name WHERE id = 777");
        $this->assertNotNull($metadata);

        $decoded = json_decode($metadata, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertEquals('Value 1', $decoded[0]['value']);
        $this->assertEquals('Value 2', $decoded[1]['value']);
    }

    /**
     * Test handling of missing observation fields
     *
     * Verifies that the function handles observations with
     * missing optional fields gracefully.
     */
    public function test_store_items_handles_missing_fields() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with minimal data
        $items = [
            'results' => [
                [
                    'id' => 666,
                    // uuid missing
                    // observed_on missing
                    // species_guess missing
                    // place_guess missing
                    // observation_field_values missing
                ],
            ],
        ];

        // Act: Store items (should not error)
        inat_obs_store_items($items);

        // Assert: Record inserted with default/empty values
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 666", ARRAY_A);
        $this->assertNotNull($record);
        $this->assertEquals('', $record['uuid']);
        $this->assertNull($record['observed_on']);
        $this->assertEquals('', $record['species_guess']);
        $this->assertEquals('', $record['place_guess']);
        $this->assertEquals('[]', $record['metadata']); // Empty array JSON
    }

    /**
     * Test sanitization of text fields
     *
     * Verifies that species_guess and place_guess are properly
     * sanitized to prevent XSS and injection attacks.
     */
    public function test_store_items_sanitizes_text_fields() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with potentially dangerous content
        $items = [
            'results' => [
                [
                    'id' => 555,
                    'uuid' => 'test-uuid-555',
                    'observed_on' => '2024-01-25',
                    'species_guess' => '<script>alert("XSS")</script>Homo sapiens',
                    'place_guess' => '<img src=x onerror=alert(1)>San Francisco',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify HTML tags stripped by sanitize_text_field
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 555", ARRAY_A);
        $this->assertNotNull($record);

        // sanitize_text_field strips all HTML tags
        $this->assertStringNotContainsString('<script>', $record['species_guess']);
        $this->assertStringNotContainsString('<img', $record['place_guess']);
        $this->assertStringNotContainsString('onerror', $record['place_guess']);
    }

    /**
     * Test handling of null observed_on date
     *
     * Verifies that NULL datetime values are handled correctly.
     */
    public function test_store_items_handles_null_datetime() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with null date
        $items = [
            'results' => [
                [
                    'id' => 444,
                    'uuid' => 'test-uuid-444',
                    'observed_on' => null,
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Location',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify NULL stored correctly
        $observed_on = $wpdb->get_var("SELECT observed_on FROM $table_name WHERE id = 444");
        $this->assertNull($observed_on);
    }

    /**
     * Test handling of malformed date strings
     *
     * Verifies behavior when observed_on contains invalid date format.
     */
    public function test_store_items_handles_invalid_date_format() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with invalid date
        $items = [
            'results' => [
                [
                    'id' => 333,
                    'uuid' => 'test-uuid-333',
                    'observed_on' => 'not-a-date',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Location',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify record inserted (MySQL may convert to NULL or keep as-is)
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 333", ARRAY_A);
        $this->assertNotNull($record);
    }

    /**
     * Test storing very long text values
     *
     * Verifies that varchar(255) fields are truncated appropriately
     * for values exceeding maximum length.
     */
    public function test_store_items_handles_long_text() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with very long strings
        $long_species = str_repeat('A', 500);
        $long_place = str_repeat('B', 500);

        $items = [
            'results' => [
                [
                    'id' => 222,
                    'uuid' => 'test-uuid-222',
                    'observed_on' => '2024-01-30',
                    'species_guess' => $long_species,
                    'place_guess' => $long_place,
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify data stored (may be truncated by MySQL)
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 222", ARRAY_A);
        $this->assertNotNull($record);

        // MySQL varchar(255) will truncate to 255 chars
        $this->assertLessThanOrEqual(255, strlen($record['species_guess']));
        $this->assertLessThanOrEqual(255, strlen($record['place_guess']));
    }

    /**
     * Test storing observation with special characters
     *
     * Verifies that Unicode and special characters are handled
     * correctly in text fields.
     */
    public function test_store_items_handles_unicode_characters() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with Unicode characters
        $items = [
            'results' => [
                [
                    'id' => 111,
                    'uuid' => 'test-uuid-111',
                    'observed_on' => '2024-02-01',
                    'species_guess' => 'Ñandú común 日本語 العربية',
                    'place_guess' => 'São Paulo, Montréal, Москва',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify Unicode preserved
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 111", ARRAY_A);
        $this->assertNotNull($record);
        $this->assertStringContainsString('Ñandú', $record['species_guess']);
        $this->assertStringContainsString('São Paulo', $record['place_guess']);
    }

    /**
     * Test storing multiple observations in batch
     *
     * Verifies that batch processing works correctly for
     * large datasets.
     */
    public function test_store_items_handles_large_batch() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create large batch of observations
        $items = $this->create_sample_api_response(100);

        // Act: Store large batch
        inat_obs_store_items($items);

        // Assert: Verify all records inserted
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(100, $count);
    }

    /**
     * Test metadata with nested arrays
     *
     * Verifies that complex nested observation field structures
     * are correctly serialized.
     */
    public function test_store_items_handles_nested_metadata() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with deeply nested metadata
        $items = [
            'results' => [
                [
                    'id' => 100,
                    'uuid' => 'test-uuid-100',
                    'observed_on' => '2024-02-05',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Location',
                    'observation_field_values' => [
                        [
                            'observation_field' => [
                                'id' => 1,
                                'name' => 'Complex Field',
                                'nested' => [
                                    'level1' => ['level2' => 'deep value'],
                                ],
                            ],
                            'value' => 'Test',
                        ],
                    ],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify nested structure preserved in JSON
        $metadata = $wpdb->get_var("SELECT metadata FROM $table_name WHERE id = 100");
        $decoded = json_decode($metadata, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('observation_field', $decoded[0]);
        $this->assertArrayHasKey('nested', $decoded[0]['observation_field']);
    }

    /**
     * Test integer type coercion for id field
     *
     * Verifies that the id field is properly cast to integer
     * even if provided as string.
     */
    public function test_store_items_coerces_id_to_integer() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with string id
        $items = [
            'results' => [
                [
                    'id' => '12345',  // String instead of int
                    'uuid' => 'test-uuid-str',
                    'observed_on' => '2024-02-10',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Location',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify id stored as integer
        $id = $wpdb->get_var("SELECT id FROM $table_name WHERE uuid = 'test-uuid-str'");
        $this->assertEquals(12345, (int)$id);
    }

    /**
     * Test created_at and updated_at timestamps
     *
     * Verifies that timestamp fields are automatically set.
     */
    public function test_store_items_sets_timestamps() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation
        $items = $this->create_sample_api_response(1);
        $items['results'][0]['id'] = 9999;

        // Act: Store items
        $before = current_time('mysql', 1);
        inat_obs_store_items($items);
        $after = current_time('mysql', 1);

        // Assert: Verify timestamps set
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 9999", ARRAY_A);
        $this->assertNotNull($record['created_at']);
        $this->assertNotNull($record['updated_at']);

        // Timestamps should be between before and after
        $this->assertGreaterThanOrEqual($before, $record['created_at']);
        $this->assertLessThanOrEqual($after, $record['created_at']);
    }

    /**
     * Test handling of observation with zero id
     *
     * Verifies behavior when id is 0 (edge case).
     */
    public function test_store_items_handles_zero_id() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with id = 0
        $items = [
            'results' => [
                [
                    'id' => 0,
                    'uuid' => 'test-uuid-zero',
                    'observed_on' => '2024-02-15',
                    'species_guess' => 'Zero Species',
                    'place_guess' => 'Zero Location',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify record with id=0 can be stored
        $record = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 0", ARRAY_A);
        $this->assertNotNull($record);
        $this->assertEquals('Zero Species', $record['species_guess']);
    }

    /**
     * Test empty metadata array serialization
     *
     * Verifies that empty observation_field_values becomes
     * empty JSON array "[]".
     */
    public function test_store_items_handles_empty_metadata() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Create observation with empty metadata
        $items = [
            'results' => [
                [
                    'id' => 8888,
                    'uuid' => 'test-uuid-empty',
                    'observed_on' => '2024-02-20',
                    'species_guess' => 'Empty Metadata Species',
                    'place_guess' => 'Empty Metadata Location',
                    'observation_field_values' => [],
                ],
            ],
        ];

        // Act: Store items
        inat_obs_store_items($items);

        // Assert: Verify empty array stored as JSON
        $metadata = $wpdb->get_var("SELECT metadata FROM $table_name WHERE id = 8888");
        $this->assertEquals('[]', $metadata);

        $decoded = json_decode($metadata, true);
        $this->assertIsArray($decoded);
        $this->assertCount(0, $decoded);
    }
}
