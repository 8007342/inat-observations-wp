<?php
/**
 * REST API Integration Tests
 *
 * Full WordPress environment integration tests for REST endpoint.
 * WordPress Marketplace Compliance: Tests real database queries and WordPress REST API.
 *
 * @package inat-observations-wp
 */

class Test_REST_API extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();

        // Create test tables
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // observations table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inat_observations (
            id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            species_guess VARCHAR(255),
            place_guess VARCHAR(255),
            taxon_name VARCHAR(255),
            observed_on DATE,
            photo_url TEXT,
            photo_attribution VARCHAR(255),
            photo_license VARCHAR(50),
            metadata JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // observation_fields table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}inat_observation_fields (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            observation_id BIGINT UNSIGNED NOT NULL,
            field_id INT UNSIGNED NOT NULL,
            name VARCHAR(255),
            value TEXT,
            INDEX idx_obs_id (observation_id),
            INDEX idx_field_id (field_id),
            INDEX idx_name (name(50))
        ) $charset_collate;";

        dbDelta($sql);

        // Insert test data
        $wpdb->insert("{$wpdb->prefix}inat_observations", [
            'id' => 1,
            'species_guess' => 'American Robin',
            'place_guess' => 'Seattle, WA',
            'taxon_name' => 'Turdus migratorius',
            'observed_on' => '2024-01-15',
            'metadata' => '{}'
        ]);

        $wpdb->insert("{$wpdb->prefix}inat_observations", [
            'id' => 2,
            'species_guess' => 'Hummingbird',
            'place_guess' => 'Portland, OR',
            'taxon_name' => 'Trochilidae',
            'observed_on' => '2024-01-16',
            'metadata' => '{}'
        ]);

        $wpdb->insert("{$wpdb->prefix}inat_observations", [
            'id' => 3,
            'species_guess' => 'American Robin',
            'place_guess' => 'San Diego, CA',
            'taxon_name' => 'Turdus migratorius',
            'observed_on' => '2024-01-17',
            'metadata' => '{}'
        ]);

        // Insert DNA field
        $wpdb->insert("{$wpdb->prefix}inat_observation_fields", [
            'observation_id' => 1,
            'field_id' => 100,
            'name' => 'DNA Barcode',
            'value' => 'ATCG...'
        ]);
    }

    public function tearDown(): void {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inat_observations");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}inat_observation_fields");
        parent::tearDown();
    }

    /**
     * Test REST endpoint registration
     */
    public function test_rest_endpoint_registered() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/inat/v1/observations', $routes);
    }

    /**
     * Test REST endpoint returns observations
     */
    public function test_rest_endpoint_returns_observations() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $response = rest_get_server()->dispatch($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('results', $data);
        $this->assertGreaterThan(0, count($data['results']));
    }

    /**
     * Test REST endpoint filters by species (single value)
     */
    public function test_rest_endpoint_filters_by_species() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', 'American Robin');

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertCount(2, $data['results']);
        foreach ($data['results'] as $obs) {
            $this->assertEquals('American Robin', $obs['species_guess']);
        }
    }

    /**
     * Test REST endpoint filters by species (multi-select JSON array)
     */
    public function test_rest_endpoint_filters_by_multiselect_species() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', json_encode(['American Robin', 'Hummingbird']));

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertEquals(3, $data['total']);
    }

    /**
     * Test REST endpoint filters by location
     */
    public function test_rest_endpoint_filters_by_location() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('place', 'Seattle, WA');

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['results']);
        $this->assertEquals('Seattle, WA', $data['results'][0]['place_guess']);
    }

    /**
     * Test REST endpoint filters by DNA
     */
    public function test_rest_endpoint_filters_by_dna() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('has_dna', '1');

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['results']);
        $this->assertEquals(1, $data['results'][0]['id']);
    }

    /**
     * Test REST endpoint pagination
     */
    public function test_rest_endpoint_pagination() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 2);
        $request->set_param('page', 1);

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertEquals(2, $data['per_page']);
        $this->assertEquals(1, $data['page']);
        $this->assertLessThanOrEqual(2, count($data['results']));
    }

    /**
     * Test REST endpoint returns pagination metadata
     */
    public function test_rest_endpoint_returns_pagination_metadata() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 2);

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('total_pages', $data);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals(2, $data['total_pages']); // 3 observations / 2 per page
    }

    /**
     * Test REST endpoint clamps per_page to maximum
     */
    public function test_rest_endpoint_clamps_per_page() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('per_page', 999);

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        // Should be clamped to 100
        $this->assertEquals(100, $data['per_page']);
    }

    /**
     * Test REST endpoint decodes JSON metadata
     */
    public function test_rest_endpoint_decodes_metadata() {
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}inat_observations",
            ['metadata' => '{"custom_field": "test_value"}'],
            ['id' => 1]
        );

        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $first_obs = $data['results'][0];
        $this->assertIsArray($first_obs['metadata']);
    }

    /**
     * Test REST endpoint case-insensitive species match
     */
    public function test_rest_endpoint_case_insensitive_species() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', 'american robin'); // lowercase

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertCount(2, $data['results']);
    }

    /**
     * Test REST endpoint case-insensitive location match
     */
    public function test_rest_endpoint_case_insensitive_location() {
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('place', 'SEATTLE, WA'); // uppercase

        $response = rest_get_server()->dispatch($request);
        $data = $response->get_data();

        $this->assertCount(1, $data['results']);
    }
}
