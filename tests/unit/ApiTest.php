<?php
/**
 * API Unit Tests
 *
 * Tests for iNaturalist API integration functions.
 * Uses Brain\Monkey to mock WordPress functions.
 *
 * @package inat-observations-wp
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class Test_Inat_API extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    /**
     * Set up test environment with Brain\Monkey
     */
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // Set environment variables directly (getenv is used by api.php)
        putenv('CACHE_LIFETIME=3600');
        putenv('INAT_API_TOKEN='); // No token by default

        // Mock WordPress functions used in api.php
        Functions\when('get_option')->justReturn(''); // Default: no API token in options

        // Define WordPress constants that the plugin checks
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Load the file being tested
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/api.php';
    }

    /**
     * Clean up Brain\Monkey
     */
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Load fixture data
     */
    private function getFixture($filename) {
        $path = dirname(__DIR__) . '/fixtures/' . $filename;
        return json_decode(file_get_contents($path), true);
    }

    /**
     * Test successful API fetch with default parameters
     */
    public function test_fetch_observations_success() {
        $fixture = $this->getFixture('inat-api-response.json');

        // Mock transient cache (miss on first call)
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        // Mock HTTP request
        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 200],
            'body' => json_encode($fixture),
        ]);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($fixture));

        $result = inat_obs_fetch_observations(['project' => 'test-project']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(3, $result['results']);
        $this->assertEquals(123456, $result['results'][0]['id']);
    }

    /**
     * Test API fetch with network error (WP_Error)
     */
    public function test_fetch_observations_network_error() {
        Functions\when('get_transient')->justReturn(false);

        // Mock WP_Error
        $wp_error = Mockery::mock('WP_Error');
        $wp_error->shouldReceive('get_error_message')->andReturn('Connection timeout');

        Functions\when('wp_remote_get')->justReturn($wp_error);
        Functions\when('is_wp_error')->alias(function($arg) use ($wp_error) {
            return $arg === $wp_error;
        });

        $result = inat_obs_fetch_observations(['project' => 'test-project']);

        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Test API fetch with HTTP 404 response
     */
    public function test_fetch_observations_http_404() {
        Functions\when('get_transient')->justReturn(false);

        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 404],
            'body' => 'Not Found',
        ]);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);
        Functions\when('wp_remote_retrieve_body')->justReturn('Not Found');

        $result = inat_obs_fetch_observations(['project' => 'test-project']);

        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Test API fetch with HTTP 500 server error
     */
    public function test_fetch_observations_http_500() {
        Functions\when('get_transient')->justReturn(false);

        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 500],
            'body' => 'Internal Server Error',
        ]);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(500);
        Functions\when('wp_remote_retrieve_body')->justReturn('Internal Server Error');

        $result = inat_obs_fetch_observations(['project' => 'test-project']);

        $this->assertInstanceOf('WP_Error', $result);
    }

    /**
     * Test API fetch with malformed JSON response
     */
    public function test_fetch_observations_malformed_json() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 200],
            'body' => '{invalid json}',
        ]);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('{invalid json}');

        $result = inat_obs_fetch_observations(['project' => 'test-project']);

        // Should return null (json_decode failure) or empty array
        $this->assertTrue($result === null || $result === []);
    }

    /**
     * Test cache hit (returns cached data without HTTP call)
     */
    public function test_fetch_uses_cache_on_repeat() {
        $fixture = $this->getFixture('inat-api-response.json');

        // Mock cache hit
        Functions\when('get_transient')->justReturn($fixture);

        // wp_remote_get should NOT be called
        Functions\expect('wp_remote_get')->never();

        $result = inat_obs_fetch_observations(['project' => 'test-project']);

        $this->assertEquals($fixture, $result);
    }

    /**
     * Test fetch with project ID parameter
     */
    public function test_fetch_with_project_id() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $captured_url = null;

        Functions\when('wp_remote_get')->alias(function($url) use (&$captured_url) {
            $captured_url = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['results' => []]),
            ];
        });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['results' => []]));

        inat_obs_fetch_observations(['project' => 'my-project']);

        $this->assertStringContainsString('project_id=my-project', $captured_url);
    }

    /**
     * Test fetch with user ID parameter
     */
    public function test_fetch_with_user_id() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $captured_url = null;

        Functions\when('wp_remote_get')->alias(function($url) use (&$captured_url) {
            $captured_url = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['results' => []]),
            ];
        });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['results' => []]));

        inat_obs_fetch_observations(['user_id' => 'user123']);

        $this->assertStringContainsString('user_id=user123', $captured_url);
    }

    /**
     * Test fetch with pagination parameters
     */
    public function test_fetch_with_pagination() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $captured_url = null;

        Functions\when('wp_remote_get')->alias(function($url) use (&$captured_url) {
            $captured_url = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['results' => []]),
            ];
        });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['results' => []]));

        inat_obs_fetch_observations(['page' => 2, 'per_page' => 50]);

        $this->assertStringContainsString('page=2', $captured_url);
        $this->assertStringContainsString('per_page=50', $captured_url);
    }

    /**
     * Test fetch with API token adds Authorization header
     */
    public function test_fetch_with_api_token() {
        // Set API token for this test
        putenv('INAT_API_TOKEN=secret_token_123');

        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $captured_args = null;

        Functions\when('wp_remote_get')->alias(function($url, $args) use (&$captured_args) {
            $captured_args = $args;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['results' => []]),
            ];
        });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['results' => []]));

        inat_obs_fetch_observations(['project' => 'test']);

        $this->assertArrayHasKey('headers', $captured_args);
        $this->assertArrayHasKey('Authorization', $captured_args['headers']);
        $this->assertEquals('Bearer secret_token_123', $captured_args['headers']['Authorization']);

        // Reset for other tests
        putenv('INAT_API_TOKEN=');
    }

    /**
     * Test fetch without API token (no Authorization header)
     */
    public function test_fetch_without_api_token() {
        // Ensure no API token is set (already done in setUp, but explicit here)
        putenv('INAT_API_TOKEN=');

        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $captured_args = null;

        Functions\when('wp_remote_get')->alias(function($url, $args) use (&$captured_args) {
            $captured_args = $args;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['results' => []]),
            ];
        });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['results' => []]));

        inat_obs_fetch_observations(['project' => 'test']);

        $this->assertArrayHasKey('headers', $captured_args);
        $this->assertArrayNotHasKey('Authorization', $captured_args['headers']);
    }

    /**
     * Test default parameters are applied
     */
    public function test_fetch_with_default_parameters() {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $captured_url = null;

        Functions\when('wp_remote_get')->alias(function($url) use (&$captured_url) {
            $captured_url = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['results' => []]),
            ];
        });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['results' => []]));

        inat_obs_fetch_observations(); // No args

        // Should have default page=1, per_page=100
        $this->assertStringContainsString('page=1', $captured_url);
        $this->assertStringContainsString('per_page=100', $captured_url);
        $this->assertStringContainsString('order=desc', $captured_url);
        $this->assertStringContainsString('order_by=created_at', $captured_url);
    }
}
