<?php
/**
 * Autocomplete Unit Tests
 *
 * Tests for autocomplete data providers and caching.
 * Uses Brain\Monkey to mock WordPress functions.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class AutocompleteTest extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        if (!defined('HOUR_IN_SECONDS')) {
            define('HOUR_IN_SECONDS', 3600);
        }

        // Load the file being tested
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/autocomplete.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test species autocomplete returns cached data
     */
    public function test_get_species_autocomplete_uses_cache() {
        // TODO-BUG-002: Mock data now includes normalized_value
        $cached_species = [
            ['common_name' => 'Robin', 'scientific_name' => 'Turdus migratorius', 'normalized_value' => 'ROBIN'],
            ['common_name' => 'Sparrow', 'scientific_name' => 'Passer domesticus', 'normalized_value' => 'SPARROW'],
            ['common_name' => 'Eagle', 'scientific_name' => 'Aquila chrysaetos', 'normalized_value' => 'EAGLE']
        ];

        Functions\when('get_transient')->justReturn($cached_species);

        $result = inat_obs_get_species_autocomplete();

        // Should return cached data with "Unknown Species" prepended
        // TODO-BUG-002: Now includes normalized_value field
        $this->assertCount(4, $result);
        $this->assertEquals([
            'common_name' => 'Unknown Species',
            'scientific_name' => '',
            'normalized_value' => 'UNKNOWN SPECIES'
        ], $result[0]);
        $this->assertEquals([
            'common_name' => 'Robin',
            'scientific_name' => 'Turdus migratorius',
            'normalized_value' => 'ROBIN'
        ], $result[1]);
    }

    /**
     * Test species autocomplete queries database on cache miss
     */
    public function test_get_species_autocomplete_queries_on_cache_miss() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('get_results')->andReturn([
            ['common_name' => 'Hummingbird', 'scientific_name' => 'Archilochus colubris'],
            ['common_name' => 'Finch', 'scientific_name' => 'Haemorhous mexicanus'],
            ['common_name' => 'Crow', 'scientific_name' => 'Corvus brachyrhynchos']
        ]);

        $GLOBALS['wpdb'] = $wpdb;

        $result = inat_obs_get_species_autocomplete();

        // Should query database and prepend "Unknown Species"
        // TODO-BUG-002: Now includes normalized_value field
        $this->assertCount(4, $result);
        $this->assertEquals([
            'common_name' => 'Unknown Species',
            'scientific_name' => '',
            'normalized_value' => 'UNKNOWN SPECIES'
        ], $result[0]);
        $this->assertEquals([
            'common_name' => 'Hummingbird',
            'scientific_name' => 'Archilochus colubris',
            'normalized_value' => 'HUMMINGBIRD'
        ], $result[1]);
    }

    /**
     * Test species autocomplete limits results
     */
    public function test_get_species_autocomplete_limits_results() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $captured_sql = null;
        $wpdb->shouldReceive('get_results')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return [];
        });

        $GLOBALS['wpdb'] = $wpdb;

        inat_obs_get_species_autocomplete();

        // Verify SQL has LIMIT 1000
        $this->assertStringContainsString('LIMIT 1000', $captured_sql);
    }

    /**
     * Test species autocomplete filters empty values
     */
    public function test_get_species_autocomplete_filters_empty() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $captured_sql = null;
        $wpdb->shouldReceive('get_results')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return [];
        });

        $GLOBALS['wpdb'] = $wpdb;

        inat_obs_get_species_autocomplete();

        // Verify SQL filters empty species_guess
        $this->assertStringContainsString("species_guess != ''", $captured_sql);
    }

    /**
     * Test location autocomplete returns cached data
     */
    public function test_get_location_autocomplete_uses_cache() {
        // TODO-BUG-002: Location cache now returns structured format
        $cached_locations = [
            ['display' => 'California', 'normalized_value' => 'CALIFORNIA'],
            ['display' => 'Oregon', 'normalized_value' => 'OREGON'],
            ['display' => 'Washington', 'normalized_value' => 'WASHINGTON']
        ];

        Functions\when('get_transient')->justReturn($cached_locations);

        $result = inat_obs_get_location_autocomplete();

        // Should return cached data with structured format
        $this->assertCount(3, $result);
        $this->assertEquals(['display' => 'California', 'normalized_value' => 'CALIFORNIA'], $result[0]);
    }

    /**
     * Test location autocomplete queries database on cache miss
     */
    public function test_get_location_autocomplete_queries_on_cache_miss() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('get_col')->andReturn(['Seattle', 'Portland', 'San Diego']);

        $GLOBALS['wpdb'] = $wpdb;

        $result = inat_obs_get_location_autocomplete();

        // Should query database and return structured format (TODO-BUG-002)
        $this->assertCount(3, $result);
        $this->assertEquals(['display' => 'Seattle', 'normalized_value' => 'SEATTLE'], $result[0]);
    }

    /**
     * Test cache invalidation
     */
    public function test_invalidate_autocomplete_cache() {
        $deleted_keys = [];
        Functions\when('delete_transient')->alias(function($key) use (&$deleted_keys) {
            $deleted_keys[] = $key;
            return true;
        });

        inat_obs_invalidate_autocomplete_cache();

        // Verify both legacy v1 and current v2 species caches are deleted
        $this->assertContains('inat_obs_species_autocomplete_v1', $deleted_keys);
        $this->assertContains('inat_obs_species_autocomplete_v2', $deleted_keys);
        $this->assertContains('inat_obs_location_autocomplete_v1', $deleted_keys);
    }

    /**
     * Test autocomplete AJAX endpoint with species field
     */
    public function test_autocomplete_ajax_with_species() {
        $_GET['field'] = 'species';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('get_transient')->justReturn([
            ['common_name' => 'Robin', 'scientific_name' => 'Turdus migratorius', 'normalized_value' => 'ROBIN'],
            ['common_name' => 'Sparrow', 'scientific_name' => 'Passer domesticus', 'normalized_value' => 'SPARROW']
        ]);

        $sent_json = null;
        Functions\when('wp_send_json_success')->alias(function($data) use (&$sent_json) {
            $sent_json = $data;
        });

        inat_obs_autocomplete_ajax();

        // Verify response contains suggestions
        $this->assertArrayHasKey('suggestions', $sent_json);
        $this->assertIsArray($sent_json['suggestions']);
        // Should have "Unknown Species" prepended
        $this->assertCount(3, $sent_json['suggestions']);
        $this->assertEquals(['common_name' => 'Unknown Species', 'scientific_name' => '', 'normalized_value' => 'UNKNOWN SPECIES'], $sent_json['suggestions'][0]);
    }

    /**
     * Test autocomplete AJAX endpoint with location field
     */
    public function test_autocomplete_ajax_with_location() {
        $_GET['field'] = 'location';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('get_transient')->justReturn([
            ['display' => 'Seattle', 'normalized_value' => 'SEATTLE'],
            ['display' => 'Portland', 'normalized_value' => 'PORTLAND']
        ]);

        $sent_json = null;
        Functions\when('wp_send_json_success')->alias(function($data) use (&$sent_json) {
            $sent_json = $data;
        });

        inat_obs_autocomplete_ajax();

        // Verify response contains suggestions
        $this->assertArrayHasKey('suggestions', $sent_json);
        $this->assertCount(2, $sent_json['suggestions']);
    }

    /**
     * Test autocomplete AJAX endpoint rejects invalid field
     */
    public function test_autocomplete_ajax_rejects_invalid_field() {
        $_GET['field'] = 'invalid_field';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();

        $error_message = null;
        Functions\when('wp_send_json_error')->alias(function($data) use (&$error_message) {
            $error_message = $data['message'] ?? null;
        });

        inat_obs_autocomplete_ajax();

        // Verify error response
        $this->assertEquals('Invalid field', $error_message);
    }

    /**
     * Test autocomplete AJAX endpoint checks nonce
     */
    public function test_autocomplete_ajax_checks_nonce() {
        $_GET['field'] = 'species';

        Functions\when('check_ajax_referer')->justReturn(false);
        Functions\when('sanitize_text_field')->returnArg();

        $error_code = null;
        Functions\when('wp_send_json_error')->alias(function($data, $code) use (&$error_code) {
            $error_code = $code;
        });

        inat_obs_autocomplete_ajax();

        // Verify security check failed
        $this->assertEquals(403, $error_code);
    }
}
