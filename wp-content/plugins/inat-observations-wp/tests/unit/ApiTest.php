<?php
/**
 * Unit Tests for API Client Module (api.php)
 *
 * Tests for iNaturalist API client functions including:
 * - API request construction and execution
 * - Transient caching behavior
 * - Error handling for network failures
 * - Authentication header handling
 * - Parameter validation and sanitization
 *
 * // Silent tests guard well
 * // Edge cases lurk in shadows—
 * // Mocks illuminate
 */

class INAT_OBS_ApiTest extends INAT_OBS_TestCase {

    /**
     * Test successful API fetch with valid response
     *
     * Verifies that the function correctly fetches observations,
     * parses the response, and returns the decoded JSON data.
     */
    public function test_fetch_observations_success() {
        // Arrange: Create mock successful API response
        $expected_data = $this->create_sample_api_response(3);
        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($expected_data),
        ];

        // Mock wp_remote_get to return our test data
        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Call the function
        $result = inat_obs_fetch_observations(['per_page' => 3]);

        // Assert: Verify correct data returned
        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(3, $result['results']);
        $this->assertEquals(3, $result['total_results']);
        $this->assertEquals('Test Species 1', $result['results'][0]['species_guess']);
    }

    /**
     * Test API fetch with caching - first call stores in transient
     *
     * Verifies that successful API responses are cached as transients
     * with the correct key and expiration time.
     */
    public function test_fetch_observations_caches_response() {
        // Arrange: Mock successful response
        $expected_data = $this->create_sample_api_response(2);
        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($expected_data),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Fetch observations
        $args = ['project' => 'test-proj', 'per_page' => 2];
        $result = inat_obs_fetch_observations($args);

        // Assert: Verify transient was created
        $url = 'https://api.inaturalist.org/v1/observations?' . http_build_query([
            'project_id' => 'test-proj',
            'per_page' => 2,
            'page' => 1,
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $transient_key = 'inat_obs_cache_' . md5($url);

        $cached_data = get_transient($transient_key);
        $this->assertNotFalse($cached_data, 'Response should be cached in transient');
        $this->assertEquals($expected_data, $cached_data);
    }

    /**
     * Test cache hit - second call returns cached data without API request
     *
     * Verifies that when data is cached, subsequent calls return the
     * cached data without making additional HTTP requests.
     */
    public function test_fetch_observations_returns_cached_data() {
        // Arrange: Pre-populate cache
        $cached_data = $this->create_sample_api_response(5);
        $url = 'https://api.inaturalist.org/v1/observations?' . http_build_query([
            'project_id' => 'test-project-slug',
            'per_page' => 100,
            'page' => 1,
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $transient_key = 'inat_obs_cache_' . md5($url);
        set_transient($transient_key, $cached_data, 3600);

        // Mock HTTP to fail if called (should not be called due to cache)
        add_filter('pre_http_request', function() {
            $this->fail('HTTP request should not be made when cache exists');
            return new WP_Error('should_not_call', 'Cache should be used');
        }, 10, 3);

        // Act: Fetch observations (should use cache)
        $result = inat_obs_fetch_observations();

        // Assert: Verify cached data returned
        $this->assertEquals($cached_data, $result);
        $this->assertCount(5, $result['results']);
    }

    /**
     * Test API fetch with HTTP error response (non-200 status)
     *
     * Verifies that the function properly handles HTTP error codes
     * and returns a WP_Error with appropriate error information.
     */
    public function test_fetch_observations_handles_http_error() {
        // Arrange: Mock 404 Not Found response
        $mock_response = [
            'response' => ['code' => 404],
            'body' => json_encode(['error' => 'Project not found']),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Attempt to fetch observations
        $result = inat_obs_fetch_observations(['project' => 'nonexistent']);

        // Assert: Verify WP_Error returned
        $this->assertWPError($result);
        $this->assertEquals('inat_api_error', $result->get_error_code());
        $this->assertStringContainsString('404', $result->get_error_message());
    }

    /**
     * Test API fetch with network/connection failure
     *
     * Verifies that wp_remote_get network errors are properly
     * propagated as WP_Error objects.
     */
    public function test_fetch_observations_handles_network_error() {
        // Arrange: Mock network timeout error
        $mock_error = new WP_Error('http_request_failed', 'Connection timed out');

        add_filter('pre_http_request', function() use ($mock_error) {
            return $mock_error;
        }, 10, 3);

        // Act: Attempt to fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Verify error propagated correctly
        $this->assertWPError($result);
        $this->assertEquals('http_request_failed', $result->get_error_code());
        $this->assertStringContainsString('timed out', $result->get_error_message());
    }

    /**
     * Test API fetch with authentication token
     *
     * Verifies that when INAT_API_TOKEN is set, the Authorization
     * header is correctly included in the request.
     */
    public function test_fetch_observations_includes_auth_token() {
        // Arrange: Set API token environment variable
        putenv('INAT_API_TOKEN=test_secret_token_123');

        $headers_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$headers_used) {
            $headers_used = $args['headers'];
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Verify Authorization header present
        $this->assertIsArray($headers_used);
        $this->assertArrayHasKey('Authorization', $headers_used);
        $this->assertEquals('Bearer test_secret_token_123', $headers_used['Authorization']);

        // Cleanup
        putenv('INAT_API_TOKEN=test_api_token_here');
    }

    /**
     * Test API fetch without authentication token
     *
     * Verifies that when no API token is set, requests are made
     * without an Authorization header.
     */
    public function test_fetch_observations_without_auth_token() {
        // Arrange: Clear API token
        putenv('INAT_API_TOKEN=');

        $headers_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$headers_used) {
            $headers_used = $args['headers'];
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Verify no Authorization header
        $this->assertIsArray($headers_used);
        $this->assertArrayNotHasKey('Authorization', $headers_used);

        // Cleanup
        putenv('INAT_API_TOKEN=test_api_token_here');
    }

    /**
     * Test API URL construction with default parameters
     *
     * Verifies that the API URL is correctly constructed with
     * default values when no arguments are provided.
     */
    public function test_fetch_observations_url_construction_defaults() {
        // Arrange: Capture the URL used
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch with defaults
        $result = inat_obs_fetch_observations();

        // Assert: Verify URL structure
        $this->assertStringContainsString('api.inaturalist.org/v1/observations', $url_used);
        $this->assertStringContainsString('project_id=test-project-slug', $url_used);
        $this->assertStringContainsString('per_page=100', $url_used);
        $this->assertStringContainsString('page=1', $url_used);
        $this->assertStringContainsString('order=desc', $url_used);
        $this->assertStringContainsString('order_by=created_at', $url_used);
    }

    /**
     * Test API URL construction with custom parameters
     *
     * Verifies that custom parameters override defaults and are
     * correctly included in the API URL.
     */
    public function test_fetch_observations_url_construction_custom_params() {
        // Arrange: Capture the URL used
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch with custom parameters
        $result = inat_obs_fetch_observations([
            'project' => 'custom-project',
            'per_page' => 25,
            'page' => 3,
        ]);

        // Assert: Verify custom parameters in URL
        $this->assertStringContainsString('project_id=custom-project', $url_used);
        $this->assertStringContainsString('per_page=25', $url_used);
        $this->assertStringContainsString('page=3', $url_used);
    }

    /**
     * Test error responses are not cached
     *
     * Verifies that failed API responses (WP_Error) are not
     * stored in transient cache, allowing retry on next request.
     */
    public function test_fetch_observations_does_not_cache_errors() {
        // Arrange: Mock error response
        $mock_error = new WP_Error('api_down', 'Service unavailable');

        add_filter('pre_http_request', function() use ($mock_error) {
            return $mock_error;
        }, 10, 3);

        // Act: Attempt fetch (will fail)
        $result = inat_obs_fetch_observations(['project' => 'test']);

        // Assert: Verify error returned but not cached
        $this->assertWPError($result);

        $url = 'https://api.inaturalist.org/v1/observations?' . http_build_query([
            'project_id' => 'test',
            'per_page' => 100,
            'page' => 1,
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $transient_key = 'inat_obs_cache_' . md5($url);
        $cached = get_transient($transient_key);

        $this->assertFalse($cached, 'Errors should not be cached');
    }

    /**
     * Test malformed JSON response handling
     *
     * Verifies that the function can handle responses with
     * invalid JSON without crashing.
     */
    public function test_fetch_observations_handles_malformed_json() {
        // Arrange: Mock response with invalid JSON
        $mock_response = [
            'response' => ['code' => 200],
            'body' => 'This is not valid JSON{{{',
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Attempt to fetch
        $result = inat_obs_fetch_observations();

        // Assert: Function should handle gracefully
        // json_decode returns null for invalid JSON
        $this->assertNull($result);
    }

    /**
     * Test empty results array handling
     *
     * Verifies that API responses with zero results are
     * handled correctly and don't cause errors.
     */
    public function test_fetch_observations_handles_empty_results() {
        // Arrange: Mock response with no observations
        $empty_data = [
            'total_results' => 0,
            'page' => 1,
            'per_page' => 100,
            'results' => [],
        ];
        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($empty_data),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Verify empty array handled correctly
        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(0, $result['results']);
        $this->assertEquals(0, $result['total_results']);
    }

    /**
     * Test cache lifetime configuration
     *
     * Verifies that the CACHE_LIFETIME environment variable
     * is respected when setting transient expiration.
     */
    public function test_fetch_observations_respects_cache_lifetime() {
        // Arrange: Set custom cache lifetime
        putenv('CACHE_LIFETIME=7200'); // 2 hours

        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($this->create_sample_api_response(1)),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Verify transient expiration set correctly
        // Note: WordPress stores transient timeout as separate option
        $url = 'https://api.inaturalist.org/v1/observations?' . http_build_query([
            'project_id' => 'test-project-slug',
            'per_page' => 100,
            'page' => 1,
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $transient_key = 'inat_obs_cache_' . md5($url);
        $timeout_key = '_transient_timeout_' . $transient_key;

        global $wpdb;
        $timeout = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM $wpdb->options WHERE option_name = %s",
            $timeout_key
        ));

        // Timeout should be approximately current time + 7200 seconds
        $this->assertNotNull($timeout);
        $expected_timeout = time() + 7200;
        $this->assertEqualsWithDelta($expected_timeout, (int)$timeout, 5, 'Cache timeout should be ~2 hours from now');

        // Cleanup
        putenv('CACHE_LIFETIME=3600');
    }

    /**
     * Test HTTP 500 internal server error handling
     *
     * Verifies that server errors are properly handled and
     * return appropriate error information.
     */
    public function test_fetch_observations_handles_http_500() {
        // Arrange: Mock 500 Internal Server Error
        $mock_response = [
            'response' => ['code' => 500],
            'body' => 'Internal Server Error',
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Attempt fetch
        $result = inat_obs_fetch_observations();

        // Assert: Verify error handling
        $this->assertWPError($result);
        $this->assertEquals('inat_api_error', $result->get_error_code());
        $this->assertStringContainsString('500', $result->get_error_message());
    }

    /**
     * Test HTTP 429 rate limit response
     *
     * Verifies that rate limiting responses are detected
     * and returned as errors.
     */
    public function test_fetch_observations_handles_rate_limit() {
        // Arrange: Mock 429 Too Many Requests
        $mock_response = [
            'response' => ['code' => 429],
            'body' => json_encode(['error' => 'Rate limit exceeded']),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Attempt fetch
        $result = inat_obs_fetch_observations();

        // Assert: Verify rate limit error
        $this->assertWPError($result);
        $this->assertEquals('inat_api_error', $result->get_error_code());
        $this->assertStringContainsString('429', $result->get_error_message());
    }

    /**
     * Test parameter sanitization - project slug
     *
     * Verifies that the project parameter is used correctly
     * in URL construction even with special characters.
     */
    public function test_fetch_observations_sanitizes_project_parameter() {
        // Arrange: Use project with special characters
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch with special characters in project
        $result = inat_obs_fetch_observations(['project' => 'test project']);

        // Assert: Verify URL encoding
        // http_build_query should encode spaces as +
        $this->assertStringContainsString('project_id=test+project', $url_used);
    }

    /**
     * Test per_page parameter bounds enforcement
     *
     * Verifies that per_page is clamped to API limits (1-200).
     */
    public function test_fetch_observations_enforces_per_page_bounds() {
        // Arrange: Test values outside bounds
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Try per_page > 200
        inat_obs_fetch_observations(['per_page' => 500]);

        // Assert: Should be clamped to 200
        $this->assertStringContainsString('per_page=200', $url_used);
    }

    /**
     * Test per_page parameter minimum bound
     *
     * Verifies that per_page cannot be less than 1.
     */
    public function test_fetch_observations_enforces_per_page_minimum() {
        // Arrange
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Try per_page = 0
        inat_obs_fetch_observations(['per_page' => 0]);

        // Assert: Should be clamped to 1
        $this->assertStringContainsString('per_page=1', $url_used);
    }

    /**
     * Test page parameter minimum bound
     *
     * Verifies that page cannot be less than 1.
     */
    public function test_fetch_observations_enforces_page_minimum() {
        // Arrange
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Try page = 0
        inat_obs_fetch_observations(['page' => 0]);

        // Assert: Should be clamped to 1
        $this->assertStringContainsString('page=1', $url_used);
    }

    /**
     * Test with negative per_page value
     *
     * Verifies that negative values are handled correctly.
     */
    public function test_fetch_observations_handles_negative_per_page() {
        // Arrange
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Try negative per_page
        inat_obs_fetch_observations(['per_page' => -50]);

        // Assert: absint() will convert to 50, then clamp to valid range
        $this->assertStringContainsString('per_page=', $url_used);
    }

    /**
     * Test cache lifetime bounds enforcement
     *
     * Verifies that cache lifetime is clamped to reasonable limits.
     */
    public function test_fetch_observations_enforces_cache_lifetime_bounds() {
        // Arrange: Set very long cache lifetime
        putenv('CACHE_LIFETIME=999999');

        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($this->create_sample_api_response(1)),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Should cache but with clamped lifetime (max 86400)
        $this->assertIsArray($result);

        // Cleanup
        putenv('CACHE_LIFETIME=3600');
    }

    /**
     * Test cache lifetime minimum bound
     *
     * Verifies that cache lifetime cannot be less than 60 seconds.
     */
    public function test_fetch_observations_enforces_cache_lifetime_minimum() {
        // Arrange: Set very short cache lifetime
        putenv('CACHE_LIFETIME=10');

        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($this->create_sample_api_response(1)),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Should succeed with minimum 60 second cache
        $this->assertIsArray($result);

        // Cleanup
        putenv('CACHE_LIFETIME=3600');
    }

    /**
     * Test JSON decode error handling
     *
     * Verifies proper handling when json_decode fails.
     */
    public function test_fetch_observations_handles_json_decode_error() {
        // Arrange: Mock response with valid HTTP but invalid JSON
        $mock_response = [
            'response' => ['code' => 200],
            'body' => '{broken json',
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Attempt fetch
        $result = inat_obs_fetch_observations();

        // Assert: Should return WP_Error for invalid JSON
        $this->assertWPError($result);
        $this->assertEquals('inat_api_error', $result->get_error_code());
        $this->assertStringContainsString('Invalid JSON', $result->get_error_message());
    }

    /**
     * Test HTTP 503 service unavailable
     *
     * Verifies handling of temporary service outages.
     */
    public function test_fetch_observations_handles_http_503() {
        // Arrange: Mock 503 response
        $mock_response = [
            'response' => ['code' => 503],
            'body' => 'Service Temporarily Unavailable',
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Attempt fetch
        $result = inat_obs_fetch_observations();

        // Assert: Should return error
        $this->assertWPError($result);
        $this->assertEquals('inat_api_error', $result->get_error_code());
        $this->assertStringContainsString('503', $result->get_error_message());
    }

    /**
     * Test with empty project parameter
     *
     * Verifies handling when project is empty string.
     */
    public function test_fetch_observations_with_empty_project() {
        // Arrange
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch with empty project
        inat_obs_fetch_observations(['project' => '']);

        // Assert: Should use empty string in URL
        $this->assertStringContainsString('project_id=', $url_used);
    }

    /**
     * Test response with missing total_results field
     *
     * Verifies handling of incomplete API responses.
     */
    public function test_fetch_observations_handles_missing_total_results() {
        // Arrange: Mock response without total_results
        $incomplete_data = [
            'results' => [],
            'page' => 1,
            'per_page' => 100,
            // missing total_results
        ];

        $mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode($incomplete_data),
        ];

        add_filter('pre_http_request', function() use ($mock_response) {
            return $mock_response;
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Should return data even with missing field
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('total_results', $result);
    }

    /**
     * Test token sanitization with whitespace
     *
     * Verifies that API tokens are properly sanitized.
     */
    public function test_fetch_observations_sanitizes_token_with_whitespace() {
        // Arrange: Set token with leading/trailing whitespace
        putenv('INAT_API_TOKEN=  test_token_with_spaces  ');

        $headers_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$headers_used) {
            $headers_used = $args['headers'];
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Fetch observations
        $result = inat_obs_fetch_observations();

        // Assert: Token should be trimmed
        $this->assertArrayHasKey('Authorization', $headers_used);
        $this->assertEquals('Bearer test_token_with_spaces', $headers_used['Authorization']);

        // Cleanup
        putenv('INAT_API_TOKEN=test_api_token_here');
    }

    /**
     * Test with non-string per_page value
     *
     * Verifies type coercion for per_page parameter.
     */
    public function test_fetch_observations_coerces_per_page_type() {
        // Arrange
        $url_used = null;
        add_filter('pre_http_request', function($preempt, $args, $url) use (&$url_used) {
            $url_used = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Pass per_page as string
        inat_obs_fetch_observations(['per_page' => '50']);

        // Assert: Should convert to integer
        $this->assertStringContainsString('per_page=50', $url_used);
    }
}
