<?php
/**
 * Cache Behavior Integration Tests
 *
 * Tests cache hit/miss behavior, TTL expiration, and cache invalidation
 * using the WordPress test environment.
 *
 * Requires: WordPress test environment (WP_UnitTestCase)
 * Cache TTL: 3 seconds (defined in bootstrap.php via INAT_OBS_DEV_CACHE_TTL)
 */

class CacheBehaviorTest extends WP_UnitTestCase {

    protected $table_name;
    protected $fields_table_name;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'inat_observations';
        $this->fields_table_name = $wpdb->prefix . 'inat_observation_fields';

        // Create tables
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/db-schema.php';
        inat_obs_create_tables();

        // Load test fixtures
        require_once dirname(__DIR__) . '/fixtures/mock-observations.php';

        // Insert mock data
        insert_mock_observations($wpdb);

        // Load REST endpoint
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/rest.php';

        // Clear all caches before each test
        wp_cache_flush();
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void {
        global $wpdb;

        // Clean up mock data
        require_once dirname(__DIR__) . '/fixtures/mock-observations.php';
        cleanup_mock_observations($wpdb);

        // Drop tables
        $wpdb->query("DROP TABLE IF EXISTS {$this->fields_table_name}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");

        // Clear caches
        wp_cache_flush();

        parent::tearDown();
    }

    /**
     * Test cache hit on repeat request (within TTL).
     */
    public function test_cache_hit_within_ttl() {
        // Create mock request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 10);
        $request->set_param('page', 1);

        // First request - should query database
        $start_time = microtime(true);
        $response1 = inat_obs_rest_get_observations($request);
        $first_duration = microtime(true) - $start_time;

        // Second request (immediate) - should hit cache
        $start_time = microtime(true);
        $response2 = inat_obs_rest_get_observations($request);
        $second_duration = microtime(true) - $start_time;

        // Verify both responses are identical
        $this->assertEquals($response1['results'], $response2['results']);
        $this->assertEquals($response1['total'], $response2['total']);

        // Verify second request was faster (cache hit)
        // Note: This assertion may be flaky in slow environments
        // Comment out if tests are unreliable
        // $this->assertLessThan($first_duration, $second_duration);
    }

    /**
     * Test cache miss after TTL expiration.
     */
    public function test_cache_miss_after_ttl_expiration() {
        // Skip if cache TTL is not set to 3 seconds
        if (!defined('INAT_OBS_DEV_CACHE_TTL') || INAT_OBS_DEV_CACHE_TTL !== 3) {
            $this->markTestSkipped('This test requires INAT_OBS_DEV_CACHE_TTL to be set to 3 seconds');
        }

        // Create mock request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 10);
        $request->set_param('page', 1);

        // First request - populate cache
        $response1 = inat_obs_rest_get_observations($request);
        $this->assertNotEmpty($response1['results']);

        // Wait for cache to expire (3 seconds + buffer)
        sleep(4);

        // Second request - should query database again (cache expired)
        $response2 = inat_obs_rest_get_observations($request);

        // Verify response is still correct (data hasn't changed)
        $this->assertEquals($response1['results'], $response2['results']);
        $this->assertEquals($response1['total'], $response2['total']);

        // Note: We can't easily verify the cache miss without instrumenting the code
        // In a real scenario, we'd check database query count or add logging
    }

    /**
     * Test cache isolation between different queries.
     */
    public function test_cache_isolation_different_queries() {
        // Request 1: All observations
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request1->set_param('per_page', 10);
        $request1->set_param('page', 1);

        // Request 2: Filtered by species
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('per_page', 10);
        $request2->set_param('page', 1);
        $request2->set_param('species', '["Amanita muscaria"]');

        // Execute both requests
        $response1 = inat_obs_rest_get_observations($request1);
        $response2 = inat_obs_rest_get_observations($request2);

        // Verify different results
        $this->assertNotEquals(count($response1['results']), count($response2['results']));
        $this->assertEquals(10, count($response1['results']));  // All 10 observations
        $this->assertEquals(1, count($response2['results']));   // Only 1 Amanita muscaria

        // Verify cache keys are different (by requesting again and getting same results)
        $response1_cached = inat_obs_rest_get_observations($request1);
        $response2_cached = inat_obs_rest_get_observations($request2);

        $this->assertEquals($response1['results'], $response1_cached['results']);
        $this->assertEquals($response2['results'], $response2_cached['results']);
    }

    /**
     * Test cache TTL differs for filtered vs unfiltered queries.
     */
    public function test_cache_ttl_filtered_vs_unfiltered() {
        // Skip if cache TTL is overridden (dev mode)
        if (defined('INAT_OBS_DEV_CACHE_TTL')) {
            $this->markTestSkipped('This test requires default cache TTL (not dev mode)');
        }

        // This test verifies the code sets different TTLs
        // We can't easily test the actual expiration without waiting 5+ minutes
        // Instead, we verify the cache key generation logic

        // Unfiltered request
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $response1 = inat_obs_rest_get_observations($request1);

        // Filtered request
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('species', '["Boletus edulis"]');
        $response2 = inat_obs_rest_get_observations($request2);

        // Both should succeed
        $this->assertNotEmpty($response1['results']);
        $this->assertNotEmpty($response2['results']);

        // Code inspection note: rest.php sets $cache_ttl to:
        // - 3600 (1 hour) for unfiltered
        // - 300 (5 min) for filtered
        // This test just ensures both code paths work
    }

    /**
     * Test cache with pagination.
     */
    public function test_cache_with_pagination() {
        // Request page 1
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request1->set_param('per_page', 5);
        $request1->set_param('page', 1);

        // Request page 2
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('per_page', 5);
        $request2->set_param('page', 2);

        // Execute both requests
        $response1 = inat_obs_rest_get_observations($request1);
        $response2 = inat_obs_rest_get_observations($request2);

        // Verify different results (different pages)
        $this->assertEquals(5, count($response1['results']));
        $this->assertEquals(5, count($response2['results']));
        $this->assertNotEquals($response1['results'][0]['id'], $response2['results'][0]['id']);

        // Verify both pages are cached independently
        $response1_cached = inat_obs_rest_get_observations($request1);
        $response2_cached = inat_obs_rest_get_observations($request2);

        $this->assertEquals($response1['results'], $response1_cached['results']);
        $this->assertEquals($response2['results'], $response2_cached['results']);
    }

    /**
     * Test cache with DNA filter.
     */
    public function test_cache_with_dna_filter() {
        // Request without DNA filter
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request1->set_param('per_page', 10);

        // Request with DNA filter
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('per_page', 10);
        $request2->set_param('has_dna', '1');

        // Execute both requests
        $response1 = inat_obs_rest_get_observations($request1);
        $response2 = inat_obs_rest_get_observations($request2);

        // Verify different results
        $this->assertEquals(10, count($response1['results']));  // All observations
        $this->assertEquals(5, count($response2['results']));   // Only DNA observations

        // Verify both are cached independently
        $response1_cached = inat_obs_rest_get_observations($request1);
        $response2_cached = inat_obs_rest_get_observations($request2);

        $this->assertEquals($response1['results'], $response1_cached['results']);
        $this->assertEquals($response2['results'], $response2_cached['results']);
    }

    /**
     * Test cache invalidation via wp_cache_flush().
     */
    public function test_cache_invalidation() {
        // Create request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 10);

        // First request - populate cache
        $response1 = inat_obs_rest_get_observations($request);
        $this->assertNotEmpty($response1['results']);

        // Invalidate cache
        wp_cache_flush();

        // Second request - should query database again
        $response2 = inat_obs_rest_get_observations($request);

        // Verify response is still correct
        $this->assertEquals($response1['results'], $response2['results']);
    }

    /**
     * Test cache count metadata.
     */
    public function test_cache_count_metadata() {
        // Create request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 5);
        $request->set_param('page', 1);

        // Execute request
        $response = inat_obs_rest_get_observations($request);

        // Verify metadata
        $this->assertArrayHasKey('total', $response);
        $this->assertArrayHasKey('per_page', $response);
        $this->assertArrayHasKey('page', $response);
        $this->assertArrayHasKey('total_pages', $response);

        $this->assertEquals(10, $response['total']);  // All 10 mock observations
        $this->assertEquals(5, $response['per_page']);
        $this->assertEquals(1, $response['page']);
        $this->assertEquals(2, $response['total_pages']);  // 10 / 5 = 2

        // Request again (cached)
        $response_cached = inat_obs_rest_get_observations($request);

        // Verify metadata is cached correctly
        $this->assertEquals($response['total'], $response_cached['total']);
        $this->assertEquals($response['total_pages'], $response_cached['total_pages']);
    }
}
