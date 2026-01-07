<?php
/**
 * Shortcode Unit Tests
 *
 * Tests for shortcode rendering and AJAX handlers.
 * Uses Brain\Monkey to mock WordPress shortcode and AJAX functions.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class ShortcodeTest extends PHPUnit\Framework\TestCase {
    use MockeryPHPUnitIntegration;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }
        if (!defined('INAT_OBS_VERSION')) {
            define('INAT_OBS_VERSION', '1.0.0');
        }

        // Set up environment
        putenv('INAT_PROJECT_SLUG=test-project');

        // Load the file being tested
        require_once dirname(__DIR__, 2) . '/wp-content/plugins/inat-observations-wp/includes/shortcode.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test shortcode renders with default attributes
     */
    public function test_shortcode_render_default_attributes() {
        Functions\expect('shortcode_atts')->andReturnUsing(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });
        Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/inat-observations-wp/includes/../');
        Functions\expect('wp_enqueue_script')->once();
        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_localize_script')->once();
        Functions\when('admin_url')->justReturn('http://example.com/wp-admin/admin-ajax.php');
        Functions\when('wp_create_nonce')->justReturn('test_nonce_12345');
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();

        $output = inat_obs_shortcode_render([]);

        $this->assertStringContainsString('inat-observations-root', $output);
        $this->assertStringContainsString('inat-filters', $output);
        $this->assertStringContainsString('inat-list', $output);
        $this->assertStringContainsString('Loading observations...', $output);
    }

    /**
     * Test shortcode accepts custom attributes
     */
    public function test_shortcode_render_custom_attributes() {
        Functions\expect('shortcode_atts')->andReturnUsing(function($defaults, $atts) {
            return array_merge($defaults, $atts);
        });
        Functions\when('plugin_dir_url')->justReturn('http://example.com/');
        Functions\expect('wp_enqueue_script')->once();
        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_localize_script')->once();
        Functions\when('admin_url')->justReturn('http://example.com/wp-admin/admin-ajax.php');
        Functions\when('wp_create_nonce')->justReturn('nonce123');
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();

        $output = inat_obs_shortcode_render([
            'project' => 'my-custom-project',
            'per_page' => 100
        ]);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('div', $output);
    }

    /**
     * Test shortcode enqueues assets
     */
    public function test_shortcode_enqueues_assets() {
        $enqueued_script = null;
        $enqueued_style = null;

        Functions\expect('shortcode_atts')->andReturnUsing(function($defaults, $atts) {
            return $defaults;
        });
        Functions\when('plugin_dir_url')->justReturn('http://example.com/plugin/');
        Functions\expect('wp_enqueue_script')
            ->once()
            ->andReturnUsing(function($handle) use (&$enqueued_script) {
                $enqueued_script = $handle;
            });
        Functions\expect('wp_enqueue_style')
            ->once()
            ->andReturnUsing(function($handle) use (&$enqueued_style) {
                $enqueued_style = $handle;
            });
        Functions\expect('wp_localize_script')->once();
        Functions\when('admin_url')->justReturn('http://example.com/wp-admin/admin-ajax.php');
        Functions\when('wp_create_nonce')->justReturn('nonce');
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();

        inat_obs_shortcode_render([]);

        $this->assertEquals('inat-observations-main', $enqueued_script);
        $this->assertEquals('inat-observations-css', $enqueued_style);
    }

    /**
     * Test shortcode localizes script with AJAX settings
     */
    public function test_shortcode_localizes_ajax_settings() {
        $localized_data = null;

        Functions\expect('shortcode_atts')->andReturnUsing(function($defaults, $atts) {
            return $defaults;
        });
        Functions\when('plugin_dir_url')->justReturn('http://example.com/');
        Functions\expect('wp_enqueue_script')->once();
        Functions\expect('wp_enqueue_style')->once();
        Functions\expect('wp_localize_script')
            ->once()
            ->andReturnUsing(function($handle, $name, $data) use (&$localized_data) {
                $localized_data = $data;
            });
        Functions\when('admin_url')->justReturn('http://example.com/wp-admin/admin-ajax.php');
        Functions\when('wp_create_nonce')->justReturn('test_nonce');
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();

        inat_obs_shortcode_render([]);

        $this->assertArrayHasKey('ajaxUrl', $localized_data);
        $this->assertArrayHasKey('nonce', $localized_data);
        $this->assertEquals('http://example.com/wp-admin/admin-ajax.php', $localized_data['ajaxUrl']);
        $this->assertEquals('test_nonce', $localized_data['nonce']);
    }

    /**
     * Test AJAX fetch with valid nonce
     */
    public function test_ajax_fetch_valid_nonce() {
        $_GET['per_page'] = 50;
        $_GET['page'] = 1;

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT * FROM wp_inat_observations');
        $wpdb->shouldReceive('get_results')->andReturn([
            ['id' => 1, 'species_guess' => 'Test', 'metadata' => '{}']
        ]);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);

        $json_sent = null;
        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function($data) use (&$json_sent) {
                $json_sent = $data;
            });

        inat_obs_ajax_fetch();

        $this->assertArrayHasKey('results', $json_sent);
        $this->assertCount(1, $json_sent['results']);
    }

    /**
     * Test AJAX fetch with invalid nonce
     */
    public function test_ajax_fetch_invalid_nonce() {
        Functions\when('check_ajax_referer')->justReturn(false);

        $error_sent = null;
        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function($data, $code) use (&$error_sent) {
                $error_sent = $data;
            });

        inat_obs_ajax_fetch();

        $this->assertArrayHasKey('message', $error_sent);
        $this->assertStringContainsString('Security check failed', $error_sent['message']);
    }

    /**
     * Test AJAX fetch with pagination
     */
    public function test_ajax_fetch_with_pagination() {
        $_GET['per_page'] = 25;
        $_GET['page'] = 3;

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $captured_args = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_args) {
            $captured_args = $args;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\expect('wp_send_json_success')->once();

        inat_obs_ajax_fetch();

        // Verify pagination: page 3 with 25 per page = offset 50
        $this->assertEquals(25, $captured_args[0]); // LIMIT
        $this->assertEquals(50, $captured_args[1]); // OFFSET
    }

    /**
     * Test AJAX fetch clamps per_page
     */
    public function test_ajax_fetch_clamps_per_page() {
        $_GET['per_page'] = 500; // Should be clamped to 100

        Functions\when('check_ajax_referer')->justReturn(true);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $captured_limit = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql, ...$args) use (&$captured_limit) {
            $captured_limit = $args[0] ?? null;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\expect('wp_send_json_success')->once();

        inat_obs_ajax_fetch();

        $this->assertEquals(100, $captured_limit);
    }

    /**
     * Test AJAX fetch with filters
     */
    public function test_ajax_fetch_with_filters() {
        $_GET['species'] = 'Robin';
        $_GET['place'] = 'Seattle';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(function($text) {
            return addcslashes($text, '_%\\');
        });
        $captured_sql = null;
        $wpdb->shouldReceive('prepare')->andReturnUsing(function($sql) use (&$captured_sql) {
            $captured_sql = $sql;
            return $sql;
        });
        $wpdb->shouldReceive('get_results')->andReturn([]);

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn(false);
        Functions\when('wp_cache_set')->justReturn(true);
        Functions\expect('wp_send_json_success')->once();

        inat_obs_ajax_fetch();

        // Verify WHERE clause with both filters
        $this->assertStringContainsString('WHERE', $captured_sql);
        $this->assertStringContainsString('species_guess LIKE', $captured_sql);
        $this->assertStringContainsString('AND', $captured_sql);
        $this->assertStringContainsString('place_guess LIKE', $captured_sql);
    }

    /**
     * Test AJAX fetch uses cache
     */
    public function test_ajax_fetch_uses_cache() {
        $_GET = [];

        Functions\when('check_ajax_referer')->justReturn(true);

        $cached_data = [['id' => 999, 'species' => 'Cached']];

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('get_results')->never(); // Should not query if cached

        $GLOBALS['wpdb'] = $wpdb;

        Functions\when('wp_cache_get')->justReturn($cached_data);
        Functions\expect('wp_send_json_success')
            ->once()
            ->with(['results' => $cached_data]);

        inat_obs_ajax_fetch();
    }
}
