<?php
/**
 * SQL Injection Prevention Integration Tests
 *
 * Tests that SQL injection attempts are properly blocked by whitelist validation
 * and parameterization. These tests verify the fixes from TODO-QA-002.
 *
 * CRITICAL: These tests must ALWAYS pass. Failure indicates SQL injection vulnerability.
 *
 * REQUIREMENTS:
 * - WordPress test environment (WP_UnitTestCase)
 * - WordPress test database
 * - Install: https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/
 *
 * STATUS: Tests written but require WordPress test environment setup.
 * Currently skipped in CI. Will be enabled once WP test lib is configured.
 *
 * Related: TODO-QA-002-strict-named-parameters-for-queries.md
 */

class SQLInjectionPreventionTest extends WP_UnitTestCase {

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

        // Insert test data
        $wpdb->insert($this->table_name, [
            'id' => 9001,
            'species_guess' => 'Test Species',
            'taxon_name' => 'Testus specius',
            'place_guess' => 'Test Location',
            'observed_on' => '2026-01-07',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'photo_url' => 'https://example.com/photo.jpg',
            'photo_attribution' => 'Test User',
            'photo_license' => 'cc-by',
            'metadata' => json_encode(['quality_grade' => 'research'])
        ]);

        $wpdb->insert($this->fields_table_name, [
            'observation_id' => 9001,
            'name' => 'DNA Sequence ID',
            'value' => 'TEST-001'
        ]);

        // Load REST endpoint
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/rest.php';
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void {
        global $wpdb;

        // Clean up test data
        $wpdb->query("DELETE FROM {$this->table_name} WHERE id = 9001");
        $wpdb->query("DELETE FROM {$this->fields_table_name} WHERE observation_id = 9001");

        parent::tearDown();
    }

    /**
     * Test SQL injection attempt via field_property option.
     *
     * CRITICAL: Verifies whitelist validation prevents SQL injection via admin option.
     */
    public function test_field_property_sql_injection_blocked() {
        // ATTACK: Admin sets malicious field_property option
        update_option('inat_obs_dna_field_property', "name'; DROP TABLE {$this->table_name}; --");

        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('has_dna', '1');
        $request->set_param('per_page', 10);

        // Execute request (should NOT execute malicious SQL)
        $response = inat_obs_rest_get_observations($request);

        // Verify response is valid (not error)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);

        // CRITICAL: Verify table still exists (was not dropped)
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ));
        $this->assertEquals($this->table_name, $table_exists, 'Table was dropped - SQL injection succeeded!');

        // Verify test data still exists
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE id = 9001"));
        $this->assertEquals(1, $count, 'Test data was deleted - SQL injection may have succeeded!');
    }

    /**
     * Test SQL injection attempt via sort parameter.
     *
     * CRITICAL: Verifies whitelist validation blocks malicious sort columns.
     */
    public function test_sort_parameter_sql_injection_blocked() {
        // ATTACK: Inject SQL via sort parameter
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('sort', "species; DROP TABLE {$this->table_name}; --");
        $request->set_param('order', 'desc');
        $request->set_param('per_page', 10);

        // Execute request (should fall back to safe default)
        $response = inat_obs_rest_get_observations($request);

        // Verify response is valid
        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);

        // CRITICAL: Verify table still exists
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ));
        $this->assertEquals($this->table_name, $table_exists, 'Table was dropped - SQL injection succeeded!');

        // Verify test data still exists
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE id = 9001"));
        $this->assertEquals(1, $count);
    }

    /**
     * Test SQL injection attempt via order parameter.
     */
    public function test_order_parameter_sql_injection_blocked() {
        // ATTACK: Inject SQL via order parameter
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('sort', 'date');
        $request->set_param('order', "asc; DROP TABLE {$this->table_name}; --");
        $request->set_param('per_page', 10);

        $response = inat_obs_rest_get_observations($request);

        // Verify table still exists
        global $wpdb;
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $this->table_name
        ));
        $this->assertEquals($this->table_name, $table_exists);

        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE id = 9001"));
        $this->assertEquals(1, $count);
    }

    /**
     * Test whitelist validation allows only approved columns.
     */
    public function test_only_whitelisted_sort_columns_allowed() {
        $test_cases = [
            ['sort' => 'date', 'expected_safe' => true],
            ['sort' => 'species', 'expected_safe' => true],
            ['sort' => 'location', 'expected_safe' => true],
            ['sort' => 'taxon', 'expected_safe' => true],
            ['sort' => 'INVALID', 'expected_safe' => true],  // Falls back to default
            ['sort' => 'id; DROP TABLE', 'expected_safe' => true],  // Falls back to default
            ['sort' => '../../../etc/passwd', 'expected_safe' => true],  // Falls back
        ];

        global $wpdb;

        foreach ($test_cases as $case) {
            $request = new WP_REST_Request('GET', '/inat/v1/observations');
            $request->set_param('sort', $case['sort']);
            $request->set_param('per_page', 10);

            $response = inat_obs_rest_get_observations($request);

            // All cases should succeed (invalid sorts fall back to default)
            $this->assertIsArray($response, "Sort parameter '{$case['sort']}' caused error");
            $this->assertArrayHasKey('results', $response);

            // Verify table still exists after each attempt
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            ));
            $this->assertEquals($this->table_name, $table_exists, "Table dropped after sort='{$case['sort']}'");
        }
    }

    /**
     * Test whitelist validation for field_property.
     */
    public function test_only_whitelisted_field_properties_allowed() {
        global $wpdb;

        // Valid options
        $valid_options = ['name', 'value', 'datatype'];

        foreach ($valid_options as $option) {
            update_option('inat_obs_dna_field_property', $option);

            $request = new WP_REST_Request('GET', '/inat/v1/observations');
            $request->set_param('has_dna', '1');
            $request->set_param('per_page', 10);

            $response = inat_obs_rest_get_observations($request);

            // Should succeed
            $this->assertIsArray($response, "Valid field_property '$option' failed");
            $this->assertArrayHasKey('results', $response);
        }

        // Invalid options (should fall back to 'name')
        $invalid_options = [
            "id'; DROP TABLE {$this->table_name}; --",
            '../../../etc/passwd',
            'invalid_column',
            'observation_id; DELETE FROM',
        ];

        foreach ($invalid_options as $option) {
            update_option('inat_obs_dna_field_property', $option);

            $request = new WP_REST_Request('GET', '/inat/v1/observations');
            $request->set_param('has_dna', '1');
            $request->set_param('per_page', 10);

            $response = inat_obs_rest_get_observations($request);

            // Should succeed (falls back to safe default 'name')
            $this->assertIsArray($response, "Invalid field_property '$option' caused error (should fall back)");
            $this->assertArrayHasKey('results', $response);

            // Verify table still exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $this->table_name
            ));
            $this->assertEquals($this->table_name, $table_exists, "Table dropped with field_property='$option'");
        }
    }

    /**
     * Test UNION-based SQL injection attempt.
     */
    public function test_union_sql_injection_blocked() {
        // ATTACK: Try to inject UNION query
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', "' UNION SELECT * FROM {$this->fields_table_name} WHERE '1'='1");
        $request->set_param('per_page', 10);

        $response = inat_obs_rest_get_observations($request);

        // Should return empty results (no match for that literal string)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);

        // Verify UNION did not work (results should be empty or from normal query)
        $this->assertCount(0, $response['results'], 'UNION injection may have succeeded');
    }

    /**
     * Test boolean-based blind SQL injection attempt.
     */
    public function test_boolean_blind_sql_injection_blocked() {
        // ATTACK: Try boolean-based blind injection
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', "' OR '1'='1");
        $request->set_param('per_page', 10);

        $response = inat_obs_rest_get_observations($request);

        // Should return 0 results (looking for literal string "' OR '1'='1")
        $this->assertIsArray($response);
        $this->assertEquals(0, count($response['results']), 'Boolean injection may have bypassed filters');
    }

    /**
     * Test time-based blind SQL injection attempt.
     */
    public function test_time_based_sql_injection_blocked() {
        // ATTACK: Try time-based blind injection (MySQL SLEEP)
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('sort', "date; SELECT SLEEP(10); --");
        $request->set_param('per_page', 10);

        $start = microtime(true);
        $response = inat_obs_rest_get_observations($request);
        $duration = microtime(true) - $start;

        // Should complete quickly (not sleep for 10 seconds)
        $this->assertLessThan(2, $duration, 'Query took too long - time-based injection may have succeeded');
        $this->assertIsArray($response);
    }

    /**
     * Test stacked query SQL injection attempt.
     */
    public function test_stacked_query_injection_blocked() {
        global $wpdb;

        // ATTACK: Try to execute multiple statements
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('sort', "date; DELETE FROM {$this->table_name} WHERE id = 9001; --");
        $request->set_param('per_page', 10);

        $response = inat_obs_rest_get_observations($request);

        // Verify test data still exists (was not deleted)
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE id = 9001"));
        $this->assertEquals(1, $count, 'Test data was deleted - stacked query injection succeeded!');
    }

    /**
     * Test error-based SQL injection attempt.
     */
    public function test_error_based_sql_injection_blocked() {
        // ATTACK: Try to trigger SQL errors for information disclosure
        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_param('species', "' AND 1=CONVERT(int, (SELECT @@version))--");
        $request->set_param('per_page', 10);

        $response = inat_obs_rest_get_observations($request);

        // Should return valid response (not SQL error)
        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);

        // Verify no error messages leaked
        $this->assertArrayNotHasKey('error', $response);
    }
}
