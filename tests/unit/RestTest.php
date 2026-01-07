<?php
/**
 * REST API Unit Tests
 *
 * Tests for REST endpoint handlers.
 * Uses Brain\Monkey to mock WordPress REST and database functions.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class RestTest extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Define WordPress constants needed by rest.php
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
        if (!defined('ARRAY_A')) {
            define('ARRAY_A', 'ARRAY_A');
        }

        // Load the file being tested
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/rest.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test REST endpoint with default parameters
     */
    public function test_rest_get_observations_default_params() {
        // Mock WP_REST_Request
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([]);

        // Mock wpdb
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT * FROM wp_inat_observations');
        $wpdb->shouldReceive('get_results')->andReturn([
            ['id' => 1, 'species_guess' => 'Test Species', 'metadata' => '{}']
        ]);
        $wpdb->shouldReceive('get_var')->andReturn(10); // Total count for pagination

        $GLOBALS['wpdb'] = $wpdb;

        // Mock WordPress cache functions
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();

        // Execute
        $result = inat_obs_rest_get_observations($request);

        // Verify
        $this->assertArrayHasKey('results', $result);
        $this->assertIsArray($result['results']);
        $this->assertCount(1, $result['results']);
    }

    /**
     * Test REST endpoint with pagination parameters
     */
    public function test_rest_get_observations_with_pagination() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'per_page' => 25,
            'page' => 2
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_args) {
            // Handle array args (prepare can receive an array)
            $flat_args = [];
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $flat_args = array_merge($flat_args, $arg);
                } else {
                    $flat_args[] = $arg;
                }
            }
            $captured_args = $flat_args;
            return vsprintf(str_replace(['%d', '%s'], ['%d', "'%s'"], $sql), $flat_args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(100); // Total count for pagination

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();

        inat_obs_rest_get_observations($request);

        // Verify pagination: page 2 with 25 per page = offset 25
        $this->assertEquals(25, $captured_args[0]); // per_page (LIMIT)
        $this->assertEquals(25, $captured_args[1]); // offset (page 2 - 1) * 25
    }

    /**
     * Test REST endpoint clamps per_page to valid range
     */
    public function test_rest_get_observations_clamps_per_page() {
        // Test upper limit
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn(['per_page' => 999]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_limit = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_limit) {
            // Handle array args (prepare can receive an array)
            $flat_args = [];
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $flat_args = array_merge($flat_args, $arg);
                } else {
                    $flat_args[] = $arg;
                }
            }
            $captured_limit = $flat_args[0] ?? null;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(50); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();

        inat_obs_rest_get_observations($request);

        // Should be clamped to 100
        $this->assertEquals(100, $captured_limit);
    }

    /**
     * Test REST endpoint with species filter
     */
    public function test_rest_get_observations_with_species_filter() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => 'Hummingbird'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function($text) {
            return addcslashes($text, '_%\\');
        });
        $captured_sql = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_sql) {
            $captured_sql = $sql;
            // Handle array args (prepare can receive an array)
            $flat_args = [];
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    $flat_args = array_merge($flat_args, $arg);
                } else {
                    $flat_args[] = $arg;
                }
            }
            return vsprintf(str_replace(['%d', '%s'], ['%d', "'%s'"], $sql), $flat_args);
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(25); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name'); // DNA field property

        inat_obs_rest_get_observations($request);

        // Verify WHERE clause - Updated for new API using UPPER() = instead of LIKE
        $this->assertStringContainsString('WHERE', $captured_sql);
        $this->assertStringContainsString('UPPER(species_guess) = %s', $captured_sql);
    }

    /**
     * Test REST endpoint with place filter
     */
    public function test_rest_get_observations_with_place_filter() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'place' => 'California'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function($text) {
            return addcslashes($text, '_%\\');
        });
        $captured_sql = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(30); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name'); // DNA field property

        inat_obs_rest_get_observations($request);

        // Verify WHERE clause - Updated for new API using UPPER() = instead of LIKE
        $this->assertStringContainsString('UPPER(place_guess) = %s', $captured_sql);
    }

    /**
     * Test REST endpoint with both species and place filters
     */
    public function test_rest_get_observations_with_multiple_filters() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => 'Robin',
            'place' => 'Seattle'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function($text) {
            return addcslashes($text, '_%\\');
        });
        $captured_sql = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(15); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name'); // DNA field property

        inat_obs_rest_get_observations($request);

        // Verify both filters in WHERE clause - Updated for new API using UPPER() = instead of LIKE
        $this->assertStringContainsString('UPPER(species_guess) = %s', $captured_sql);
        $this->assertStringContainsString('AND', $captured_sql);
        $this->assertStringContainsString('UPPER(place_guess) = %s', $captured_sql);
    }

    /**
     * Test REST endpoint uses cache
     */
    public function test_rest_get_observations_uses_cache() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([]);

        $cached_data = [
            ['id' => 999, 'species_guess' => 'Cached Species']
        ];

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        // get_results should NOT be called when cache hit
        $wpdb->shouldReceive('get_results')->never();

        $GLOBALS['wpdb'] = $wpdb;

        // Mock cache to return both results and count
        $cache_call_count = 0;
        Functions\when('wp_cache_get')->alias(function($key) use ($cached_data, &$cache_call_count) {
            $cache_call_count++;
            if ($cache_call_count === 1) {
                return $cached_data; // First call: results
            }
            return 1; // Second call: count
        });
        Functions\when('rest_ensure_response')->returnArg();

        $result = inat_obs_rest_get_observations($request);

        // Verify cached data is returned
        $this->assertEquals($cached_data, $result['results']);
    }

    /**
     * Test REST endpoint decodes JSON metadata
     */
    public function test_rest_get_observations_decodes_metadata() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT * FROM wp_inat_observations');
        $wpdb->shouldReceive('get_results')->andReturn([
            [
                'id' => 1,
                'species_guess' => 'Test',
                'metadata' => '{"field1": "value1", "field2": "value2"}'
            ]
        ]);
        $wpdb->shouldReceive('get_var')->andReturn(1); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name'); // DNA field property

        $result = inat_obs_rest_get_observations($request);

        // Verify metadata is decoded
        $this->assertIsArray($result['results'][0]['metadata']);
        $this->assertEquals('value1', $result['results'][0]['metadata']['field1']);
    }

    /**
     * Test REST endpoint sanitizes filter inputs
     */
    public function test_rest_get_observations_sanitizes_filters() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => '<script>alert("xss")</script>',
            'place' => '<b>Bold Place</b>'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function($text) {
            return addcslashes($text, '_%\\');
        });
        $wpdb->shouldReceive('prepare')->andReturn('SELECT');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(0); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        $sanitized_values = [];
        Functions\when('sanitize_text_field')->alias(function($text) use (&$sanitized_values) {
            $sanitized_values[] = $text;
            return strip_tags($text);
        });
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name'); // DNA field property

        inat_obs_rest_get_observations($request);

        // Verify sanitize_text_field was called
        $this->assertContains('<script>alert("xss")</script>', $sanitized_values);
        $this->assertContains('<b>Bold Place</b>', $sanitized_values);
    }
}
