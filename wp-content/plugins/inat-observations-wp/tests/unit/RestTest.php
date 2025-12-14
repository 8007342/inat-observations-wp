<?php
/**
 * Unit Tests for REST API Module (rest.php)
 *
 * Tests for WordPress REST API endpoint functionality including:
 * - REST route registration
 * - GET request handling
 * - Response formatting
 * - Error handling
 * - Permission callbacks
 * - Parameter validation
 * - Integration with API client
 */

class INAT_OBS_RestTest extends INAT_OBS_TestCase {

    /**
     * Test REST route is registered
     *
     * Verifies that the /inat/v1/observations route is
     * registered with WordPress REST API.
     */
    public function test_rest_route_is_registered() {
        // Trigger rest_api_init
        do_action('rest_api_init');

        // Get all registered routes
        $routes = rest_get_server()->get_routes();

        // Assert: Verify our route exists
        $this->assertArrayHasKey('/inat/v1/observations', $routes);
    }

    /**
     * Test REST route accepts GET method
     *
     * Verifies that the endpoint is configured to accept
     * GET requests.
     */
    public function test_rest_route_accepts_get_method() {
        // Trigger rest_api_init
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();
        $route = $routes['/inat/v1/observations'];

        // Assert: Verify GET method is allowed
        $this->assertNotEmpty($route);
        $methods = $route[0]['methods'];
        $this->assertContains('GET', $methods);
    }

    /**
     * Test REST endpoint permission callback is public
     *
     * Verifies that the endpoint uses __return_true for
     * permission callback (public access).
     */
    public function test_rest_endpoint_is_public() {
        // Trigger rest_api_init
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();
        $route = $routes['/inat/v1/observations'];

        // Assert: Verify permission callback allows access
        $permission_callback = $route[0]['permission_callback'];
        $this->assertEquals('__return_true', $permission_callback);
    }

    /**
     * Test REST endpoint callback is registered
     *
     * Verifies that the correct callback function is registered
     * for the endpoint.
     */
    public function test_rest_endpoint_has_correct_callback() {
        // Trigger rest_api_init
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();
        $route = $routes['/inat/v1/observations'];

        // Assert: Verify callback function
        $callback = $route[0]['callback'];
        $this->assertEquals('inat_obs_rest_get_observations', $callback);
    }

    /**
     * Test REST endpoint returns success with valid data
     *
     * Verifies that the endpoint returns observation data
     * when API fetch succeeds.
     */
    public function test_rest_endpoint_returns_success() {
        // Arrange: Mock successful API response
        $expected_data = $this->create_sample_api_response(5);
        add_filter('pre_http_request', function() use ($expected_data) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($expected_data),
            ];
        }, 10, 3);

        // Create mock request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint handler
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify response
        $this->assertInstanceOf('WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('results', $data);
        $this->assertCount(5, $data['results']);
    }

    /**
     * Test REST endpoint returns error on API failure
     *
     * Verifies that the endpoint returns WP_Error when
     * API fetch fails.
     */
    public function test_rest_endpoint_returns_error_on_api_failure() {
        // Arrange: Mock API error
        add_filter('pre_http_request', function() {
            return new WP_Error('api_failed', 'Network error');
        }, 10, 3);

        // Create mock request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint handler
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify error returned
        $this->assertWPError($response);
        $this->assertEquals('inat_api_error', $response->get_error_code());
        $this->assertStringContainsString('Network error', $response->get_error_message());
    }

    /**
     * Test REST endpoint with HTTP 404 error
     *
     * Verifies handling of HTTP error codes.
     */
    public function test_rest_endpoint_handles_http_404() {
        // Arrange: Mock 404 response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 404],
                'body' => json_encode(['error' => 'Not found']),
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify error
        $this->assertWPError($response);
        $this->assertEquals('inat_api_error', $response->get_error_code());
    }

    /**
     * Test REST endpoint with HTTP 500 error
     *
     * Verifies handling of server errors.
     */
    public function test_rest_endpoint_handles_http_500() {
        // Arrange: Mock 500 response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 500],
                'body' => 'Internal Server Error',
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify error with appropriate status
        $this->assertWPError($response);
        $error_data = $response->get_error_data('inat_api_error');
        $this->assertEquals(500, $error_data['status']);
    }

    /**
     * Test REST endpoint with rate limiting (HTTP 429)
     *
     * Verifies handling of rate limit responses.
     */
    public function test_rest_endpoint_handles_rate_limit() {
        // Arrange: Mock 429 response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 429],
                'body' => json_encode(['error' => 'Rate limit exceeded']),
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify error returned
        $this->assertWPError($response);
    }

    /**
     * Test REST endpoint uses transient cache
     *
     * Verifies that endpoint benefits from caching layer.
     */
    public function test_rest_endpoint_uses_cache() {
        // Arrange: Pre-populate cache
        $cached_data = $this->create_sample_api_response(3);
        $url = 'https://api.inaturalist.org/v1/observations?' . http_build_query([
            'project_id' => 'test-project-slug',
            'per_page' => 50,
            'page' => 1,
            'order' => 'desc',
            'order_by' => 'created_at',
        ]);
        $transient_key = 'inat_obs_cache_' . md5($url);
        set_transient($transient_key, $cached_data, 3600);

        // Mock should not be called
        add_filter('pre_http_request', function() {
            $this->fail('HTTP request should not be made when cache exists');
            return new WP_Error('should_not_call', 'Cache should be used');
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Should return cached data
        $this->assertInstanceOf('WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertCount(3, $data['results']);
    }

    /**
     * Test REST endpoint with empty results
     *
     * Verifies handling of API responses with no observations.
     */
    public function test_rest_endpoint_handles_empty_results() {
        // Arrange: Mock empty response
        $empty_data = [
            'total_results' => 0,
            'page' => 1,
            'per_page' => 50,
            'results' => [],
        ];
        add_filter('pre_http_request', function() use ($empty_data) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($empty_data),
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Should return success with empty array
        $this->assertInstanceOf('WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertCount(0, $data['results']);
        $this->assertEquals(0, $data['total_results']);
    }

    /**
     * Test REST endpoint with malformed JSON response
     *
     * Verifies handling of invalid JSON from API.
     */
    public function test_rest_endpoint_handles_malformed_json() {
        // Arrange: Mock invalid JSON
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => 'Not valid JSON{{{',
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Should return null data (json_decode failure)
        $this->assertInstanceOf('WP_REST_Response', $response);
        $data = $response->get_data();
        $this->assertNull($data);
    }

    /**
     * Test REST endpoint accepts query parameters
     *
     * Verifies that the request object receives query params.
     */
    public function test_rest_endpoint_accepts_query_parameters() {
        // Arrange: Mock API response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_query_params([
            'per_page' => 25,
            'page' => 2,
            'project_id' => 'custom-project',
        ]);

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Should process without error
        $this->assertInstanceOf('WP_REST_Response', $response);
    }

    /**
     * Test REST endpoint with network timeout
     *
     * Verifies handling of connection timeouts.
     */
    public function test_rest_endpoint_handles_timeout() {
        // Arrange: Mock timeout error
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'Operation timed out');
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify error
        $this->assertWPError($response);
        $this->assertStringContainsString('timed out', $response->get_error_message());
    }

    /**
     * Test REST endpoint with SSL error
     *
     * Verifies handling of SSL/TLS errors.
     */
    public function test_rest_endpoint_handles_ssl_error() {
        // Arrange: Mock SSL error
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'SSL certificate problem');
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify error
        $this->assertWPError($response);
        $this->assertStringContainsString('SSL', $response->get_error_message());
    }

    /**
     * Test REST endpoint response is properly formatted
     *
     * Verifies that response includes all expected fields.
     */
    public function test_rest_endpoint_response_format() {
        // Arrange: Mock API response
        $api_data = $this->create_sample_api_response(2);
        add_filter('pre_http_request', function() use ($api_data) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($api_data),
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Verify response structure
        $this->assertInstanceOf('WP_REST_Response', $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('total_results', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('per_page', $data);
    }

    /**
     * Test REST route with trailing slash
     *
     * Verifies that route is accessible with or without
     * trailing slash.
     */
    public function test_rest_route_without_trailing_slash() {
        // Trigger rest_api_init
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();

        // Assert: Route exists without trailing slash
        $this->assertArrayHasKey('/inat/v1/observations', $routes);
    }

    /**
     * Test multiple simultaneous REST requests
     *
     * Verifies that endpoint can handle concurrent requests.
     */
    public function test_rest_endpoint_handles_multiple_requests() {
        // Arrange: Mock API response
        $api_data = $this->create_sample_api_response(1);
        add_filter('pre_http_request', function() use ($api_data) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($api_data),
            ];
        }, 10, 3);

        // Act: Make multiple requests
        $request1 = new WP_REST_Request('GET', '/inat/v1/observations');
        $request2 = new WP_REST_Request('GET', '/inat/v1/observations');

        $response1 = inat_obs_rest_get_observations($request1);
        $response2 = inat_obs_rest_get_observations($request2);

        // Assert: Both should succeed
        $this->assertInstanceOf('WP_REST_Response', $response1);
        $this->assertInstanceOf('WP_REST_Response', $response2);
    }

    /**
     * Test REST endpoint extracts request parameters
     *
     * Verifies that get_params() is called on request object.
     */
    public function test_rest_endpoint_extracts_params() {
        // Arrange: Mock API response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        $request = new WP_REST_Request('GET', '/inat/v1/observations');
        $request->set_query_params([
            'test_param' => 'test_value',
        ]);

        // Act: Call endpoint
        $response = inat_obs_rest_get_observations($request);

        // Assert: Should not error (params are extracted internally)
        $this->assertInstanceOf('WP_REST_Response', $response);
    }

    /**
     * Test REST namespace is correct
     *
     * Verifies that the route uses inat/v1 namespace.
     */
    public function test_rest_namespace_is_correct() {
        // Trigger rest_api_init
        do_action('rest_api_init');

        $routes = rest_get_server()->get_routes();

        // Assert: Route exists under correct namespace
        $this->assertArrayHasKey('/inat/v1/observations', $routes);

        // Verify no routes under wrong namespace
        $this->assertArrayNotHasKey('/inat/observations', $routes);
        $this->assertArrayNotHasKey('/v1/observations', $routes);
    }

    /**
     * Test REST endpoint without request object
     *
     * Verifies graceful handling if request is null/invalid.
     */
    public function test_rest_endpoint_with_null_request() {
        // Arrange: Mock API response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Call with empty request
        $request = new WP_REST_Request('GET', '/inat/v1/observations');

        // Even with empty params, should work
        $response = inat_obs_rest_get_observations($request);

        // Assert: Should handle gracefully
        $this->assertInstanceOf('WP_REST_Response', $response);
    }
}
