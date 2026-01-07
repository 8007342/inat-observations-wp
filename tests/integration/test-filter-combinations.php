<?php
/**
 * Filter Combination Integration Tests
 *
 * Tests all filter combinations (species, location, DNA) to ensure:
 * - Value normalization is consistent across frontend/backend
 * - Multiple filters work together correctly
 * - Case-insensitive matching works
 * - Accent removal works (Montréal → MONTREAL)
 * - Whitespace trimming works
 *
 * CRITICAL: These tests validate TODO-BUG-002 fixes.
 *
 * Related: TODO-BUG-002-dropdown-selector-borked.md
 */

class FilterCombinationsTest extends WP_UnitTestCase {

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

        // Insert mock data (10 observations, 5 with DNA)
        insert_mock_observations($wpdb);

        // Load REST endpoint
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/rest.php';
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void {
        global $wpdb;

        // Clean up mock data
        require_once dirname(__DIR__) . '/fixtures/mock-observations.php';
        cleanup_mock_observations($wpdb);

        parent::tearDown();
    }

    /**
     * Test 1: Single species filter (exact match).
     */
    public function test_single_species_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Amanita muscaria"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: Only Amanita muscaria (ID 1001)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);
        $this->assertCount(1, $response['results'], 'Should return exactly 1 Amanita muscaria');
        $this->assertEquals(1001, $response['results'][0]['id']);
        $this->assertEquals('Amanita muscaria', $response['results'][0]['species_guess']);
    }

    /**
     * Test 2: Single location filter.
     */
    public function test_single_location_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('place', '["Seattle, WA"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: 4 observations in Seattle (IDs 1001, 1003, 1006, 1009)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);
        $this->assertCount(4, $response['results'], 'Should return 4 Seattle observations');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1003, 1006, 1009], $ids);
    }

    /**
     * Test 3: Multiple species filter (collision test - multiple Amanita).
     */
    public function test_multiple_species_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Amanita muscaria", "Amanita phalloides", "Amanita virosa"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: 3 Amanita species (IDs 1001, 1002, 1003)
        $this->assertIsArray($response);
        $this->assertCount(3, $response['results'], 'Should return 3 Amanita observations');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1002, 1003], $ids);
    }

    /**
     * Test 4: Multiple location filter.
     */
    public function test_multiple_location_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('place', '["Seattle, WA", "Portland, OR"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: 8 observations (Seattle: 4, Portland: 4)
        $this->assertIsArray($response);
        $this->assertCount(8, $response['results'], 'Should return 8 observations from Seattle or Portland');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1002, 1003, 1005, 1006, 1008, 1009, 1010], $ids);
    }

    /**
     * Test 5: Species + Location combined filter.
     */
    public function test_species_and_location_combined() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Amanita muscaria", "Amanita virosa"]');
        $request->set_param('place', '["Seattle, WA"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: Only Amanita in Seattle (IDs 1001, 1003)
        $this->assertIsArray($response);
        $this->assertCount(2, $response['results'], 'Should return 2 Amanita in Seattle');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1003], $ids);
    }

    /**
     * Test 6: Species + DNA filter.
     */
    public function test_species_and_dna_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Amanita muscaria", "Amanita phalloides", "Amanita virosa"]');
        $request->set_param('has_dna', '1');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: 3 Amanita with DNA (IDs 1001, 1002, 1003 - all have DNA)
        $this->assertIsArray($response);
        $this->assertCount(3, $response['results'], 'Should return 3 Amanita with DNA');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1002, 1003], $ids);
    }

    /**
     * Test 7: Location + DNA filter.
     */
    public function test_location_and_dna_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('place', '["Seattle, WA"]');
        $request->set_param('has_dna', '1');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: Seattle observations with DNA (IDs 1001, 1003)
        // (1006 and 1009 are in Seattle but have no DNA)
        $this->assertIsArray($response);
        $this->assertCount(2, $response['results'], 'Should return 2 Seattle observations with DNA');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1003], $ids);
    }

    /**
     * Test 8: Triple filter (Species + Location + DNA).
     */
    public function test_triple_filter_species_location_dna() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Amanita muscaria", "Amanita virosa"]');
        $request->set_param('place', '["Seattle, WA"]');
        $request->set_param('has_dna', '1');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: Amanita in Seattle with DNA (IDs 1001, 1003)
        $this->assertIsArray($response);
        $this->assertCount(2, $response['results'], 'Should return 2 Amanita in Seattle with DNA');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1003], $ids);
    }

    /**
     * Test 9: Unknown species filter.
     */
    public function test_unknown_species_filter() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Unknown Species"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: Only observation with empty species_guess (ID 1010)
        $this->assertIsArray($response);
        $this->assertCount(1, $response['results'], 'Should return 1 unknown species observation');
        $this->assertEquals(1010, $response['results'][0]['id']);
    }

    /**
     * Test 10: Case-insensitive matching (UPPERCASE vs lowercase vs MiXeD).
     */
    public function test_case_insensitive_matching() {
        // Test with all lowercase
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request1->set_param('species', '["amanita muscaria"]');
        $request1->set_param('per_page', 50);

        $response1 = inat_obs_rest_get_observations($request1);
        $this->assertCount(1, $response1['results'], 'Lowercase should match');

        // Test with UPPERCASE
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('species', '["AMANITA MUSCARIA"]');
        $request2->set_param('per_page', 50);

        $response2 = inat_obs_rest_get_observations($request2);
        $this->assertCount(1, $response2['results'], 'UPPERCASE should match');

        // Test with MiXeD cAsE
        $request3 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request3->set_param('species', '["AmAnItA mUsCaRiA"]');
        $request3->set_param('per_page', 50);

        $response3 = inat_obs_rest_get_observations($request3);
        $this->assertCount(1, $response3['results'], 'MiXeD case should match');

        // All should return same result
        $this->assertEquals($response1['results'][0]['id'], $response2['results'][0]['id']);
        $this->assertEquals($response1['results'][0]['id'], $response3['results'][0]['id']);
    }

    /**
     * Test 11: Accent handling (é → E, ñ → N, etc.).
     *
     * Note: This test assumes we add a mock observation with accents.
     * For now, test the principle with location names.
     */
    public function test_accent_removal() {
        global $wpdb;

        // Insert test observation with accented location
        $wpdb->insert($this->table_name, [
            'id' => 9999,
            'species_guess' => 'Piñon Pine',
            'taxon_name' => 'Pinus edulis',
            'place_guess' => 'Montréal, QC',
            'observed_on' => '2026-01-07',
            'latitude' => 45.5017,
            'longitude' => -73.5673,
            'photo_url' => 'https://example.com/photo.jpg',
            'photo_attribution' => 'Test User',
            'photo_license' => 'cc-by',
            'metadata' => json_encode(['quality_grade' => 'research'])
        ]);

        // Test with accents
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request1->set_param('place', '["Montréal, QC"]');
        $request1->set_param('per_page', 50);

        $response1 = inat_obs_rest_get_observations($request1);
        $this->assertGreaterThanOrEqual(1, count($response1['results']), 'Should match with accents');

        // Test without accents
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('place', '["Montreal, QC"]');
        $request2->set_param('per_page', 50);

        $response2 = inat_obs_rest_get_observations($request2);
        $this->assertGreaterThanOrEqual(1, count($response2['results']), 'Should match without accents');

        // Both should return same results
        $this->assertEquals(count($response1['results']), count($response2['results']));

        // Clean up
        $wpdb->delete($this->table_name, ['id' => 9999]);
    }

    /**
     * Test 12: Whitespace trimming and normalization.
     */
    public function test_whitespace_normalization() {
        // Test with extra spaces
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request1->set_param('species', '["  Amanita muscaria  "]');
        $request1->set_param('per_page', 50);

        $response1 = inat_obs_rest_get_observations($request1);
        $this->assertCount(1, $response1['results'], 'Should match with leading/trailing spaces');

        // Test with multiple internal spaces
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2->set_param('species', '["Amanita    muscaria"]');
        $request2->set_param('per_page', 50);

        $response2 = inat_obs_rest_get_observations($request2);
        $this->assertCount(1, $response2['results'], 'Should match with multiple internal spaces');
    }

    /**
     * Test 13: Empty filter (should return all observations).
     */
    public function test_empty_filter_returns_all() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: All 10 mock observations
        $this->assertIsArray($response);
        $this->assertCount(10, $response['results'], 'Should return all 10 observations');
    }

    /**
     * Test 14: DNA filter only.
     */
    public function test_dna_filter_only() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('has_dna', '1');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: 5 observations with DNA (IDs 1001-1005)
        $this->assertIsArray($response);
        $this->assertCount(5, $response['results'], 'Should return 5 observations with DNA');

        $ids = array_column($response['results'], 'id');
        sort($ids);
        $this->assertEquals([1001, 1002, 1003, 1004, 1005], $ids);
    }

    /**
     * Test 15: Filter with no matches.
     */
    public function test_filter_with_no_matches() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', '["Nonexistent Species"]');
        $request->set_param('per_page', 50);

        $response = inat_obs_rest_get_observations($request);

        // Expected: Empty results
        $this->assertIsArray($response);
        $this->assertCount(0, $response['results'], 'Should return 0 results for nonexistent species');
        $this->assertEquals(0, $response['total']);
    }
}
