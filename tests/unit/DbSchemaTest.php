<?php
/**
 * Database Schema Unit Tests
 *
 * Tests for database table creation and data storage functions.
 * Uses Brain\Monkey to mock WordPress database functions.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class DbSchemaTest extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Mock WordPress functions used in db-schema.php
        Functions\when('get_option')->justReturn('');
        Functions\when('update_option')->justReturn(true);

        // Define WordPress constants
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Load the file being tested
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/db-schema.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test schema installation creates table with correct SQL
     */
    public function test_install_schema_creates_table() {
        // Mock wpdb global
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('get_charset_collate')->andReturn('DEFAULT CHARSET=utf8mb4');
        $wpdb->shouldReceive('get_col')->andReturn([]); // Mock column check (used by schema upgrade)
        $wpdb->shouldReceive('query')->andReturn(true); // Mock ALTER TABLE queries
        $wpdb->shouldReceive('get_var')->andReturn('wp_inat_observation_fields'); // Mock SHOW TABLES check

        // Mock dbDelta function
        $captured_sql = null;
        Functions\expect('dbDelta')
            ->once()
            ->andReturnUsing(function($sql) use (&$captured_sql) {
                $captured_sql = $sql;
                return ['wp_inat_observations' => 'Created table'];
            });

        // Set global wpdb
        $GLOBALS['wpdb'] = $wpdb;

        // Execute
        inat_obs_install_schema();

        // Verify SQL contains expected structure
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS wp_inat_observations', $captured_sql);
        $this->assertStringContainsString('id bigint(20) unsigned NOT NULL', $captured_sql);
        $this->assertStringContainsString('uuid varchar(100)', $captured_sql);
        $this->assertStringContainsString('observed_on datetime', $captured_sql);
        $this->assertStringContainsString('species_guess varchar(255)', $captured_sql);
        $this->assertStringContainsString('place_guess varchar(255)', $captured_sql);
        $this->assertStringContainsString('metadata json', $captured_sql);
        $this->assertStringContainsString('PRIMARY KEY  (id)', $captured_sql);
        $this->assertStringContainsString('KEY observed_on', $captured_sql);
        $this->assertStringContainsString('DEFAULT CHARSET=utf8mb4', $captured_sql);
    }

    /**
     * Test storing items with valid data
     */
    public function test_store_items_inserts_valid_data() {
        // Mock wpdb
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $captured_data = [];
        $wpdb->shouldReceive('replace')
            ->times(2)
            ->andReturnUsing(function($table, $data, $format) use (&$captured_data) {
                $captured_data[] = $data;
                return 1;
            });

        // Mock delete() and flush() methods used in inat_obs_store_items
        $wpdb->shouldReceive('delete')->andReturn(1);
        $wpdb->shouldReceive('flush')->andReturn(true);

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');

        $GLOBALS['wpdb'] = $wpdb;

        // Test data
        $items = [
            'results' => [
                [
                    'id' => 123456,
                    'uuid' => 'abc-123-def',
                    'observed_on' => '2024-01-01 10:00:00',
                    'species_guess' => 'Rufous Hummingbird',
                    'place_guess' => 'San Francisco, CA',
                    'observation_field_values' => [
                        ['field_id' => 1, 'value' => 'test']
                    ]
                ],
                [
                    'id' => 789012,
                    'uuid' => 'xyz-789-ghi',
                    'observed_on' => '2024-01-02 14:30:00',
                    'species_guess' => 'Anna\'s Hummingbird',
                    'place_guess' => 'Oakland, CA',
                    'observation_field_values' => []
                ]
            ]
        ];

        // Execute
        inat_obs_store_items($items);

        // Verify first item
        $this->assertEquals(123456, $captured_data[0]['id']);
        $this->assertEquals('abc-123-def', $captured_data[0]['uuid']);
        $this->assertEquals('2024-01-01 10:00:00', $captured_data[0]['observed_on']);
        $this->assertEquals('Rufous Hummingbird', $captured_data[0]['species_guess']);
        $this->assertEquals('San Francisco, CA', $captured_data[0]['place_guess']);
        $this->assertJson($captured_data[0]['metadata']);

        // Verify second item
        $this->assertEquals(789012, $captured_data[1]['id']);
        $this->assertEquals('xyz-789-ghi', $captured_data[1]['uuid']);
    }

    /**
     * Test storing items with missing optional fields
     */
    public function test_store_items_handles_missing_fields() {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $captured_data = null;
        $wpdb->shouldReceive('replace')
            ->once()
            ->andReturnUsing(function($table, $data, $format) use (&$captured_data) {
                $captured_data = $data;
                return 1;
            });

        // Mock delete() and flush() methods used in inat_obs_store_items
        $wpdb->shouldReceive('delete')->andReturn(1);
        $wpdb->shouldReceive('flush')->andReturn(true);

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');

        $GLOBALS['wpdb'] = $wpdb;

        // Minimal data
        $items = [
            'results' => [
                [
                    'id' => 999,
                    // uuid missing
                    // observed_on missing
                    // species_guess missing
                    // place_guess missing
                    // observation_field_values missing
                ]
            ]
        ];

        inat_obs_store_items($items);

        // Verify defaults
        $this->assertEquals(999, $captured_data['id']);
        $this->assertEquals('', $captured_data['uuid']);
        $this->assertNull($captured_data['observed_on']);
        $this->assertEquals('', $captured_data['species_guess']);
        $this->assertEquals('', $captured_data['place_guess']);
        $this->assertEquals('[]', $captured_data['metadata']);
    }

    /**
     * Test storing items sanitizes text fields
     */
    public function test_store_items_sanitizes_input() {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $captured_data = null;
        $wpdb->shouldReceive('replace')
            ->once()
            ->andReturnUsing(function($table, $data, $format) use (&$captured_data) {
                $captured_data = $data;
                return 1;
            });

        // Mock delete() and flush() methods used in inat_obs_store_items
        $wpdb->shouldReceive('delete')->andReturn(1);
        $wpdb->shouldReceive('flush')->andReturn(true);

        // Mock sanitize_text_field to actually transform input
        Functions\when('sanitize_text_field')->alias(function($text) {
            return strip_tags($text);
        });
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');

        $GLOBALS['wpdb'] = $wpdb;

        $items = [
            'results' => [
                [
                    'id' => 111,
                    'uuid' => '<script>alert("xss")</script>',
                    'species_guess' => '<b>Bold Species</b>',
                    'place_guess' => '<em>Italic Place</em>',
                ]
            ]
        ];

        inat_obs_store_items($items);

        // Verify sanitization
        $this->assertEquals('alert("xss")', $captured_data['uuid']);
        $this->assertEquals('Bold Species', $captured_data['species_guess']);
        $this->assertEquals('Italic Place', $captured_data['place_guess']);
    }

    /**
     * Test storing empty results array does nothing
     */
    public function test_store_items_handles_empty_results() {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        // replace should never be called
        $wpdb->shouldReceive('replace')->never();

        $GLOBALS['wpdb'] = $wpdb;

        // Empty results
        inat_obs_store_items(['results' => []]);
        inat_obs_store_items([]);
    }

    /**
     * Test metadata is properly JSON encoded
     */
    public function test_store_items_encodes_metadata_json() {
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $captured_data = null;
        $wpdb->shouldReceive('replace')
            ->once()
            ->andReturnUsing(function($table, $data, $format) use (&$captured_data) {
                $captured_data = $data;
                return 1;
            });

        // Mock delete() and flush() methods used in inat_obs_store_items
        $wpdb->shouldReceive('delete')->andReturn(1);
        $wpdb->shouldReceive('flush')->andReturn(true);

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('current_time')->justReturn('2024-01-01 12:00:00');

        $GLOBALS['wpdb'] = $wpdb;

        $items = [
            'results' => [
                [
                    'id' => 555,
                    'observation_field_values' => [
                        ['field_id' => 10, 'value' => 'Adult', 'name' => 'Life Stage'],
                        ['field_id' => 20, 'value' => 'Female', 'name' => 'Sex']
                    ]
                ]
            ]
        ];

        inat_obs_store_items($items);

        // Verify JSON encoding
        $this->assertJson($captured_data['metadata']);
        $decoded = json_decode($captured_data['metadata'], true);
        $this->assertCount(2, $decoded);
        $this->assertEquals('Adult', $decoded[0]['value']);
        $this->assertEquals('Female', $decoded[1]['value']);
    }
}
