<?php
/**
 * Base Test Case for Plugin Tests
 *
 * Extends WP_UnitTestCase to provide common test utilities and setup
 * for all plugin test cases. This class handles WordPress-specific
 * testing requirements and provides helper methods for assertions.
 */

class INAT_OBS_TestCase extends WP_UnitTestCase {

    /**
     * Set up test environment before each test
     *
     * Called automatically by PHPUnit before each test method.
     * Use this to set up clean state for each test.
     */
    public function setUp(): void {
        parent::setUp();

        // Clear all transients to ensure clean cache state
        $this->clear_all_transients();

        // Reset environment variables to defaults
        putenv('INAT_PROJECT_SLUG=test-project-slug');
        putenv('INAT_API_TOKEN=test_api_token_here');
        putenv('CACHE_LIFETIME=3600');
    }

    /**
     * Clean up after each test
     *
     * Called automatically by PHPUnit after each test method.
     * Use this to clean up any test data or restore state.
     */
    public function tearDown(): void {
        // Clear transients after each test
        $this->clear_all_transients();

        // Truncate custom tables
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}inat_observations");

        parent::tearDown();
    }

    /**
     * Clear all WordPress transients
     *
     * Helper method to ensure clean cache state between tests.
     * Removes all transients with the inat_obs prefix.
     */
    protected function clear_all_transients() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_inat_obs_%'
             OR option_name LIKE '_transient_timeout_inat_obs_%'"
        );
    }

    /**
     * Mock wp_remote_get responses
     *
     * @param array $response The response to return (or WP_Error)
     * @return void
     */
    protected function mock_http_response($response) {
        add_filter('pre_http_request', function($preempt, $args, $url) use ($response) {
            return $response;
        }, 10, 3);
    }

    /**
     * Create sample iNaturalist API response
     *
     * @param int $count Number of observations to generate
     * @return array Simulated API response structure
     */
    protected function create_sample_api_response($count = 5) {
        $results = [];
        for ($i = 1; $i <= $count; $i++) {
            $results[] = [
                'id' => 1000 + $i,
                'uuid' => sprintf('uuid-test-%04d', $i),
                'observed_on' => '2024-01-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'species_guess' => 'Test Species ' . $i,
                'place_guess' => 'Test Location ' . $i,
                'observation_field_values' => [
                    [
                        'observation_field' => [
                            'id' => 1,
                            'name' => 'Test Field',
                        ],
                        'value' => 'Test Value ' . $i,
                    ],
                ],
            ];
        }

        return [
            'total_results' => $count,
            'page' => 1,
            'per_page' => $count,
            'results' => $results,
        ];
    }

    /**
     * Assert that a transient exists and has expected value
     *
     * @param string $key Transient key
     * @param mixed $expected Expected value (null to just check existence)
     */
    protected function assertTransientExists($key, $expected = null) {
        $value = get_transient($key);
        $this->assertNotFalse($value, "Transient '{$key}' should exist");

        if ($expected !== null) {
            $this->assertEquals($expected, $value, "Transient '{$key}' should have expected value");
        }
    }

    /**
     * Assert that a transient does not exist
     *
     * @param string $key Transient key
     */
    protected function assertTransientNotExists($key) {
        $value = get_transient($key);
        $this->assertFalse($value, "Transient '{$key}' should not exist");
    }
}
