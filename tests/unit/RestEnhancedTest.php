<?php
/**
 * Enhanced REST API Unit Tests
 *
 * Tests for new REST endpoint features:
 * - Multi-select species filters (JSON arrays)
 * - Multi-select location filters (JSON arrays)
 * - DNA filtering with observation_fields join
 * - Cache TTL differences (filtered vs unfiltered)
 *
 * Uses Brain\Monkey to mock WordPress REST and database functions.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class RestEnhancedTest extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Load the file being tested
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/rest.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test REST endpoint with JSON array species filter (multi-select)
     */
    public function test_rest_get_observations_with_multiselect_species() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => '["Robin","Sparrow","Eagle"]'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_sql = null;
        $captured_args = [];
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_sql, &$captured_args) {
            $captured_sql = $sql;
            $captured_args = $args;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(0);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name');

        inat_obs_rest_get_observations($request);

        // Verify WHERE clause has multiple species conditions with OR
        $this->assertStringContainsString('UPPER(species_guess) = %s', $captured_sql);
        $this->assertStringContainsString('OR', $captured_sql);

        // Verify uppercase values are passed
        $this->assertContains('ROBIN', $captured_args);
        $this->assertContains('SPARROW', $captured_args);
        $this->assertContains('EAGLE', $captured_args);
    }

    /**
     * Test REST endpoint with JSON array location filter (multi-select)
     */
    public function test_rest_get_observations_with_multiselect_location() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'place' => '["California","Oregon","Washington"]'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_sql = null;
        $captured_args = [];
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_sql, &$captured_args) {
            $captured_sql = $sql;
            $captured_args = $args;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(0);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name');

        inat_obs_rest_get_observations($request);

        // Verify WHERE clause has multiple location conditions with OR
        $this->assertStringContainsString('UPPER(place_guess) = %s', $captured_sql);
        $this->assertStringContainsString('OR', $captured_sql);

        // Verify uppercase values are passed
        $this->assertContains('CALIFORNIA', $captured_args);
        $this->assertContains('OREGON', $captured_args);
    }

    /**
     * Test REST endpoint with "Unknown Species" filter
     */
    public function test_rest_get_observations_with_unknown_species() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => '["Unknown Species","Robin"]'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_sql = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(0);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name');

        inat_obs_rest_get_observations($request);

        // Verify "Unknown Species" translates to empty/NULL check
        $this->assertStringContainsString("species_guess = ''", $captured_sql);
        $this->assertStringContainsString("species_guess IS NULL", $captured_sql);
    }

    /**
     * Test REST endpoint with DNA filter
     */
    public function test_rest_get_observations_with_dna_filter() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'has_dna' => '1'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_sql = null;
        $captured_pattern = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_sql, &$captured_pattern) {
            $captured_sql = $sql;
            $captured_pattern = end($args); // Last arg should be DNA pattern
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn('wp_inat_observation_fields');

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->alias(function($key, $default) {
            if ($key === 'inat_obs_dna_field_property') return 'name';
            if ($key === 'inat_obs_dna_match_pattern') return 'DNA%';
            return $default;
        });

        inat_obs_rest_get_observations($request);

        // Verify subquery with observation_fields table
        $this->assertStringContainsString('id IN', $captured_sql);
        $this->assertStringContainsString('SELECT DISTINCT observation_id', $captured_sql);
        $this->assertStringContainsString('wp_inat_observation_fields', $captured_sql);
        $this->assertStringContainsString('LIKE', $captured_sql);

        // Verify DNA pattern
        $this->assertEquals('DNA%', $captured_pattern);
    }

    /**
     * Test REST endpoint with DNA filter respects configurable pattern
     */
    public function test_rest_get_observations_with_custom_dna_pattern() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'has_dna' => '1'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_pattern = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_pattern) {
            $captured_pattern = end($args);
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn('wp_inat_observation_fields');

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->alias(function($key, $default) {
            if ($key === 'inat_obs_dna_field_property') return 'name';
            if ($key === 'inat_obs_dna_match_pattern') return 'GenBank%'; // Custom pattern
            return $default;
        });

        inat_obs_rest_get_observations($request);

        // Verify custom pattern is used
        $this->assertEquals('GenBank%', $captured_pattern);
    }

    /**
     * Test REST endpoint cache TTL differs for filtered vs unfiltered
     */
    public function test_rest_get_observations_cache_ttl_filtered() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => 'Robin'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(0);

        $GLOBALS['wpdb'] = $wpdb;

        $cache_ttl = null;
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->alias(function($key, $value, $group, $ttl) use (&$cache_ttl) {
            $cache_ttl = $ttl;
            return true;
        });
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name');

        inat_obs_rest_get_observations($request);

        // Filtered queries should use 300s (5 min) TTL
        $this->assertEquals(300, $cache_ttl);
    }

    /**
     * Test REST endpoint cache TTL for unfiltered queries
     */
    public function test_rest_get_observations_cache_ttl_unfiltered() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(0);

        $GLOBALS['wpdb'] = $wpdb;

        $cache_ttl = null;
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->alias(function($key, $value, $group, $ttl) use (&$cache_ttl) {
            $cache_ttl = $ttl;
            return true;
        });
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name');

        inat_obs_rest_get_observations($request);

        // Unfiltered queries should use 3600s (1 hour) TTL
        $this->assertEquals(3600, $cache_ttl);
    }

    /**
     * Test REST endpoint with combined filters (species + location + DNA)
     */
    public function test_rest_get_observations_with_all_filters() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'species' => '["Robin"]',
            'place' => '["California"]',
            'has_dna' => '1'
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $captured_sql = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn('wp_inat_observation_fields');

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->alias(function($key, $default) {
            if ($key === 'inat_obs_dna_field_property') return 'name';
            if ($key === 'inat_obs_dna_match_pattern') return 'DNA%';
            return $default;
        });

        inat_obs_rest_get_observations($request);

        // Verify all three filters in WHERE clause with AND
        $this->assertStringContainsString('species_guess', $captured_sql);
        $this->assertStringContainsString('place_guess', $captured_sql);
        $this->assertStringContainsString('id IN', $captured_sql);
        $this->assertStringContainsString('AND', $captured_sql);
    }

    /**
     * Test REST endpoint returns correct pagination metadata
     */
    public function test_rest_get_observations_pagination_metadata() {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_params')->andReturn([
            'per_page' => 25,
            'page' => 3
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT');
        $wpdb->shouldReceive('get_results')->andReturn([]);
        $wpdb->shouldReceive('get_var')->andReturn(150); // Total count

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\when('rest_ensure_response')->returnArg();
        Functions\when('get_option')->justReturn('name');

        $result = inat_obs_rest_get_observations($request);

        // Verify pagination metadata
        $this->assertEquals(150, $result['total']);
        $this->assertEquals(25, $result['per_page']);
        $this->assertEquals(3, $result['page']);
        $this->assertEquals(6, $result['total_pages']); // 150 / 25 = 6
    }
}
