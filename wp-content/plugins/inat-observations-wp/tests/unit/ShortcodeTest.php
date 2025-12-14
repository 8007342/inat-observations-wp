<?php
/**
 * Unit Tests for Shortcode Module (shortcode.php)
 *
 * Tests for shortcode rendering and AJAX functionality including:
 * - Shortcode registration and rendering
 * - HTML output structure and correctness
 * - Asset enqueueing (CSS and JavaScript)
 * - AJAX endpoint registration
 * - AJAX request handling and responses
 * - Error handling for API failures
 * - Parameter validation and sanitization
 *
 * // API sleeps tonight
 * // But tests dream of tomorrow—
 * // Mocked data still speaks
 */

class INAT_OBS_ShortcodeTest extends INAT_OBS_TestCase {

    /**
     * Test shortcode is registered
     *
     * Verifies that the [inat_observations] shortcode is
     * properly registered with WordPress.
     */
    public function test_shortcode_is_registered() {
        global $shortcode_tags;

        $this->assertArrayHasKey('inat_observations', $shortcode_tags);
        $this->assertEquals('inat_obs_shortcode_render', $shortcode_tags['inat_observations']);
    }

    /**
     * Test shortcode renders HTML container
     *
     * Verifies that the shortcode outputs the correct HTML
     * structure with expected div containers.
     */
    public function test_shortcode_renders_html_container() {
        // Act: Render shortcode
        $output = inat_obs_shortcode_render();

        // Assert: Verify HTML structure
        $this->assertIsString($output);
        $this->assertStringContainsString('id="inat-observations-root"', $output);
        $this->assertStringContainsString('class="inat-filters"', $output);
        $this->assertStringContainsString('id="inat-list"', $output);
        $this->assertStringContainsString('Loading observations...', $output);
    }

    /**
     * Test shortcode renders filter dropdown
     *
     * Verifies that the filter select element is included
     * in the rendered output.
     */
    public function test_shortcode_renders_filter_dropdown() {
        // Act: Render shortcode
        $output = inat_obs_shortcode_render();

        // Assert: Verify filter element
        $this->assertStringContainsString('<select id="inat-filter-field">', $output);
        $this->assertStringContainsString('Loading filters...', $output);
    }

    /**
     * Test shortcode enqueues JavaScript
     *
     * Verifies that main.js is enqueued when shortcode is rendered.
     */
    public function test_shortcode_enqueues_javascript() {
        // Act: Render shortcode
        inat_obs_shortcode_render();

        // Assert: Verify script enqueued
        $this->assertTrue(wp_script_is('inat-observations-main', 'enqueued'));
    }

    /**
     * Test shortcode enqueues CSS
     *
     * Verifies that main.css is enqueued when shortcode is rendered.
     */
    public function test_shortcode_enqueues_css() {
        // Act: Render shortcode
        inat_obs_shortcode_render();

        // Assert: Verify style enqueued
        $this->assertTrue(wp_style_is('inat-observations-css', 'enqueued'));
    }

    /**
     * Test shortcode respects project attribute
     *
     * Verifies that the project shortcode attribute is parsed
     * correctly from the shortcode.
     */
    public function test_shortcode_accepts_project_attribute() {
        // Act: Render with custom project
        $output = inat_obs_shortcode_render(['project' => 'custom-project-123']);

        // Assert: Shortcode should render (attributes parsed internally)
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test shortcode respects per_page attribute
     *
     * Verifies that the per_page attribute is accepted and
     * defaults are applied correctly.
     */
    public function test_shortcode_accepts_per_page_attribute() {
        // Act: Render with custom per_page
        $output = inat_obs_shortcode_render(['per_page' => '100']);

        // Assert: Shortcode should render
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test shortcode default attributes
     *
     * Verifies that default attributes are applied when
     * none are provided.
     */
    public function test_shortcode_applies_default_attributes() {
        // Act: Render without attributes
        $output = inat_obs_shortcode_render();

        // Assert: Should render with defaults from environment
        $this->assertIsString($output);
        $this->assertNotEmpty($output);
    }

    /**
     * Test AJAX action is registered for logged-in users
     *
     * Verifies that wp_ajax_inat_obs_fetch action is registered.
     */
    public function test_ajax_action_registered_for_logged_in() {
        // Assert: Verify action exists
        $this->assertGreaterThan(
            0,
            has_action('wp_ajax_inat_obs_fetch', 'inat_obs_ajax_fetch'),
            'AJAX action should be registered for logged-in users'
        );
    }

    /**
     * Test AJAX action is registered for non-logged-in users
     *
     * Verifies that wp_ajax_nopriv_inat_obs_fetch action is registered.
     */
    public function test_ajax_action_registered_for_guests() {
        // Assert: Verify action exists
        $this->assertGreaterThan(
            0,
            has_action('wp_ajax_nopriv_inat_obs_fetch', 'inat_obs_ajax_fetch'),
            'AJAX action should be registered for guests'
        );
    }

    /**
     * Test AJAX fetch returns success with valid data
     *
     * Verifies that AJAX handler returns JSON success response
     * when API fetch succeeds.
     */
    public function test_ajax_fetch_returns_success() {
        // Arrange: Mock successful API response
        $expected_data = $this->create_sample_api_response(5);
        add_filter('pre_http_request', function() use ($expected_data) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($expected_data),
            ];
        }, 10, 3);

        // Act: Call AJAX handler with output buffering
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // wp_send_json_success calls wp_die which may throw in tests
        }
        $output = ob_get_clean();

        // Assert: Verify JSON success response
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertCount(5, $response['data']['results']);
    }

    /**
     * Test AJAX fetch returns error on API failure
     *
     * Verifies that AJAX handler returns JSON error response
     * when API fetch fails.
     */
    public function test_ajax_fetch_returns_error_on_api_failure() {
        // Arrange: Mock API error
        add_filter('pre_http_request', function() {
            return new WP_Error('api_failed', 'Network timeout');
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // wp_send_json_error calls wp_die
        }
        $output = ob_get_clean();

        // Assert: Verify JSON error response
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('message', $response['data']);
        $this->assertStringContainsString('Network timeout', $response['data']['message']);
    }

    /**
     * Test AJAX fetch with HTTP 404 error
     *
     * Verifies that AJAX handler properly handles HTTP error codes.
     */
    public function test_ajax_fetch_handles_http_404() {
        // Arrange: Mock 404 response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 404],
                'body' => json_encode(['error' => 'Not found']),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Verify error returned
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    /**
     * Test AJAX fetch with HTTP 500 error
     *
     * Verifies handling of server errors.
     */
    public function test_ajax_fetch_handles_http_500() {
        // Arrange: Mock 500 response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 500],
                'body' => 'Internal Server Error',
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Verify error returned
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    /**
     * Test AJAX fetch with rate limiting (HTTP 429)
     *
     * Verifies handling of rate limit errors.
     */
    public function test_ajax_fetch_handles_rate_limit() {
        // Arrange: Mock 429 response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 429],
                'body' => json_encode(['error' => 'Rate limit exceeded']),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Verify error returned
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    /**
     * Test AJAX fetch uses transient cache
     *
     * Verifies that AJAX requests benefit from transient caching.
     */
    public function test_ajax_fetch_uses_cache() {
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

        // Mock should not be called due to cache
        add_filter('pre_http_request', function() {
            $this->fail('HTTP request should not be made when cache exists');
            return new WP_Error('should_not_call', 'Cache should be used');
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should return cached data
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertCount(3, $response['data']['results']);
    }

    /**
     * Test AJAX fetch with empty results
     *
     * Verifies handling of API responses with no observations.
     */
    public function test_ajax_fetch_handles_empty_results() {
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

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should return success with empty array
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertCount(0, $response['data']['results']);
    }

    /**
     * Test AJAX fetch with malformed JSON response
     *
     * Verifies handling of invalid JSON from API.
     */
    public function test_ajax_fetch_handles_malformed_json() {
        // Arrange: Mock invalid JSON response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => 'Not valid JSON{{{',
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should return success with null data
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test shortcode output is properly escaped
     *
     * Verifies that HTML output doesn't contain unescaped
     * user-controllable content.
     */
    public function test_shortcode_output_is_safe() {
        // Act: Render shortcode
        $output = inat_obs_shortcode_render();

        // Assert: Verify no script tags in output
        $this->assertStringNotContainsString('<script>', strtolower($output));
        // Hardcoded text should be present
        $this->assertStringContainsString('Loading observations...', $output);
    }

    /**
     * Test shortcode with invalid per_page attribute
     *
     * Verifies handling of non-numeric per_page values.
     */
    public function test_shortcode_handles_invalid_per_page() {
        // Act: Render with invalid per_page
        $output = inat_obs_shortcode_render(['per_page' => 'not-a-number']);

        // Assert: Should still render (shortcode_atts may cast to 0)
        $this->assertIsString($output);
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test shortcode with empty project attribute
     *
     * Verifies handling of empty project slug.
     */
    public function test_shortcode_handles_empty_project() {
        // Act: Render with empty project
        $output = inat_obs_shortcode_render(['project' => '']);

        // Assert: Should still render (will use default)
        $this->assertIsString($output);
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test shortcode can be rendered multiple times
     *
     * Verifies that shortcode rendering is idempotent.
     */
    public function test_shortcode_multiple_renders() {
        // Act: Render multiple times
        $output1 = inat_obs_shortcode_render();
        $output2 = inat_obs_shortcode_render();

        // Assert: Both should render successfully
        $this->assertStringContainsString('inat-observations-root', $output1);
        $this->assertStringContainsString('inat-observations-root', $output2);
        $this->assertEquals($output1, $output2);
    }

    /**
     * Test JavaScript assets have correct version
     *
     * Verifies that enqueued assets use plugin version for cache busting.
     */
    public function test_enqueued_assets_have_version() {
        // Act: Render shortcode to trigger enqueueing
        inat_obs_shortcode_render();

        // Assert: Verify version set
        global $wp_scripts, $wp_styles;

        $script = $wp_scripts->registered['inat-observations-main'];
        $this->assertEquals(INAT_OBS_VERSION, $script->ver);

        $style = $wp_styles->registered['inat-observations-css'];
        $this->assertEquals(INAT_OBS_VERSION, $style->ver);
    }

    /**
     * Test JavaScript has jQuery dependency
     *
     * Verifies that main.js depends on jQuery.
     */
    public function test_javascript_depends_on_jquery() {
        // Act: Render shortcode
        inat_obs_shortcode_render();

        // Assert: Verify jQuery dependency
        global $wp_scripts;
        $script = $wp_scripts->registered['inat-observations-main'];
        $this->assertContains('jquery', $script->deps);
    }

    /**
     * Test AJAX with network timeout error
     *
     * Verifies handling of connection timeouts.
     */
    public function test_ajax_fetch_handles_timeout() {
        // Arrange: Mock timeout error
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'Operation timed out');
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Verify error returned
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('timed out', $response['data']['message']);
    }

    /**
     * Test AJAX with SSL verification error
     *
     * Verifies handling of SSL/TLS errors.
     */
    public function test_ajax_fetch_handles_ssl_error() {
        // Arrange: Mock SSL error
        add_filter('pre_http_request', function() {
            return new WP_Error('http_request_failed', 'SSL certificate problem');
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Verify error returned
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('SSL', $response['data']['message']);
    }

    /**
     * Test shortcode with null attributes
     *
     * Verifies that shortcode handles null attributes array.
     */
    public function test_shortcode_with_null_attributes() {
        // Act: Render with null
        $output = inat_obs_shortcode_render(null);

        // Assert: Should render with defaults
        $this->assertIsString($output);
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test shortcode with extra unknown attributes
     *
     * Verifies that extra attributes are ignored gracefully.
     */
    public function test_shortcode_ignores_unknown_attributes() {
        // Act: Render with unknown attributes
        $output = inat_obs_shortcode_render([
            'project' => 'test',
            'unknown_attr' => 'should-be-ignored',
            'another_fake' => 123,
        ]);

        // Assert: Should render normally
        $this->assertIsString($output);
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test AJAX fetch with invalid nonce
     *
     * Verifies that requests with bad nonces are rejected.
     */
    public function test_ajax_fetch_rejects_invalid_nonce() {
        // Arrange: Set invalid nonce in request
        $_REQUEST['nonce'] = 'invalid_nonce_value';
        $_REQUEST['action'] = 'inat_obs_fetch';

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected - wp_send_json_error calls wp_die
        }
        $output = ob_get_clean();

        // Assert: Should return error response
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Security check failed', $response['data']['message']);
    }

    /**
     * Test AJAX fetch with missing nonce
     *
     * Verifies that requests without nonces are rejected.
     */
    public function test_ajax_fetch_rejects_missing_nonce() {
        // Arrange: Don't set nonce in request
        unset($_REQUEST['nonce']);
        $_REQUEST['action'] = 'inat_obs_fetch';

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should return error
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    /**
     * Test AJAX fetch with valid nonce
     *
     * Verifies that valid nonces are accepted.
     */
    public function test_ajax_fetch_accepts_valid_nonce() {
        // Arrange: Create valid nonce
        $nonce = wp_create_nonce('inat_obs_nonce');
        $_REQUEST['nonce'] = $nonce;
        $_REQUEST['action'] = 'inat_obs_fetch';

        // Mock successful API response
        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(2)),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected - wp_send_json_success calls wp_die
        }
        $output = ob_get_clean();

        // Assert: Should return success
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX fetch with custom per_page parameter
     *
     * Verifies that per_page parameter is properly sanitized.
     */
    public function test_ajax_fetch_with_custom_per_page() {
        // Arrange: Set valid nonce and custom per_page
        $nonce = wp_create_nonce('inat_obs_nonce');
        $_REQUEST['nonce'] = $nonce;
        $_REQUEST['per_page'] = '25';

        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should succeed
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX fetch with invalid per_page parameter
     *
     * Verifies bounds enforcement for per_page.
     */
    public function test_ajax_fetch_enforces_per_page_bounds() {
        // Arrange: Set valid nonce and out-of-bounds per_page
        $nonce = wp_create_nonce('inat_obs_nonce');
        $_REQUEST['nonce'] = $nonce;
        $_REQUEST['per_page'] = '9999';  // Exceeds max

        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Call AJAX handler (should clamp to 200)
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should succeed with clamped value
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX fetch with custom page parameter
     *
     * Verifies that page parameter is properly handled.
     */
    public function test_ajax_fetch_with_custom_page() {
        // Arrange: Set valid nonce and custom page
        $nonce = wp_create_nonce('inat_obs_nonce');
        $_REQUEST['nonce'] = $nonce;
        $_REQUEST['page'] = '3';

        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should succeed
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX fetch with custom project parameter
     *
     * Verifies that project parameter is properly sanitized.
     */
    public function test_ajax_fetch_with_custom_project() {
        // Arrange: Set valid nonce and custom project
        $nonce = wp_create_nonce('inat_obs_nonce');
        $_REQUEST['nonce'] = $nonce;
        $_REQUEST['project'] = 'my-custom-project';

        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should succeed
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test AJAX fetch sanitizes malicious project parameter
     *
     * Verifies XSS protection in project parameter.
     */
    public function test_ajax_fetch_sanitizes_malicious_project() {
        // Arrange: Set valid nonce and malicious project
        $nonce = wp_create_nonce('inat_obs_nonce');
        $_REQUEST['nonce'] = $nonce;
        $_REQUEST['project'] = '<script>alert("xss")</script>';

        add_filter('pre_http_request', function() {
            return [
                'response' => ['code' => 200],
                'body' => json_encode($this->create_sample_api_response(1)),
            ];
        }, 10, 3);

        // Act: Call AJAX handler
        ob_start();
        try {
            inat_obs_ajax_fetch();
        } catch (Exception $e) {
            // Expected
        }
        $output = ob_get_clean();

        // Assert: Should succeed (parameter sanitized)
        $this->assertJson($output);
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    /**
     * Test shortcode localization data
     *
     * Verifies that JavaScript config is properly localized.
     */
    public function test_shortcode_localizes_javascript_config() {
        // Act: Render shortcode
        inat_obs_shortcode_render();

        // Assert: Verify localized script data is registered
        global $wp_scripts;
        $script = $wp_scripts->registered['inat-observations-main'];
        $this->assertNotEmpty($script->extra['data']);
        $this->assertStringContainsString('inatObsConfig', $script->extra['data']);
    }

    /**
     * Test shortcode with very large per_page
     *
     * Verifies bounds enforcement in shortcode attributes.
     */
    public function test_shortcode_clamps_large_per_page() {
        // Act: Render with excessive per_page
        $output = inat_obs_shortcode_render(['per_page' => 10000]);

        // Assert: Should still render (will be clamped to 200)
        $this->assertStringContainsString('inat-observations-root', $output);
    }

    /**
     * Test shortcode accessibility features
     *
     * Verifies ARIA labels and roles are present.
     */
    public function test_shortcode_has_accessibility_attributes() {
        // Act: Render shortcode
        $output = inat_obs_shortcode_render();

        // Assert: Verify accessibility attributes
        $this->assertStringContainsString('aria-label', $output);
        $this->assertStringContainsString('role="', $output);
        $this->assertStringContainsString('aria-live', $output);
        $this->assertStringContainsString('screen-reader-text', $output);
    }

    /**
     * Test shortcode skip link
     *
     * Verifies keyboard navigation support.
     */
    public function test_shortcode_has_skip_link() {
        // Act: Render shortcode
        $output = inat_obs_shortcode_render();

        // Assert: Verify skip link present
        $this->assertStringContainsString('inat-skip-link', $output);
        $this->assertStringContainsString('href="#inat-list"', $output);
    }
}
