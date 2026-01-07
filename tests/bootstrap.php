<?php
/**
 * PHPUnit bootstrap file for inat-observations-wp plugin
 *
 * Supports two testing modes:
 * 1. Integration tests: Full WordPress environment (tests/integration/)
 * 2. Unit tests: Brain\Monkey mocking, no WordPress (tests/unit/)
 */

// Load Composer autoloader for Brain\Monkey and Mockery
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Determine if we're running unit tests or integration tests
// Unit tests use Brain\Monkey, integration tests use WordPress test environment
$is_unit_test = (getenv('TEST_TYPE') === 'unit') ||
                (!getenv('WP_TESTS_DIR') && !is_dir(sys_get_temp_dir() . '/wordpress-tests-lib'));

if ($is_unit_test) {
    /*
     * Unit Test Mode: Brain\Monkey + Mockery
     * No WordPress environment loaded
     */
    echo "Bootstrap: Unit test mode (Brain\Monkey)\n";

    // Brain\Monkey and Mockery are loaded via Composer autoloader
    // Individual test files will call Brain\Monkey\setUp() and tearDown()

    // Define WordPress functions needed for coverage processing
    // These are called when files are loaded for coverage analysis
    if (!function_exists('plugin_dir_path')) {
        function plugin_dir_path($file) {
            return dirname($file) . '/';
        }
    }
    if (!function_exists('register_activation_hook')) {
        function register_activation_hook($file, $function) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('register_deactivation_hook')) {
        function register_deactivation_hook($file, $function) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('add_shortcode')) {
        function add_shortcode($tag, $callback) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('add_action')) {
        function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('add_filter')) {
        function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('register_rest_route')) {
        function register_rest_route($namespace, $route, $args = []) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('register_setting')) {
        function register_setting($option_group, $option_name, $args = []) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('add_settings_section')) {
        function add_settings_section($id, $title, $callback, $page) {
            // Stub for coverage processing
        }
    }
    if (!function_exists('add_settings_field')) {
        function add_settings_field($id, $title, $callback, $page, $section, $args = []) {
            // Stub for coverage processing
        }
    }

} else {
    /*
     * Integration Test Mode: Full WordPress Environment
     */
    echo "Bootstrap: Integration test mode (WordPress)\n";

    // Determine WordPress test library path
    $_tests_dir = getenv('WP_TESTS_DIR');
    if (!$_tests_dir) {
        $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
    }

    // Check if WordPress test library is available
    if (!file_exists($_tests_dir . '/includes/functions.php')) {
        echo "Could not find $_tests_dir/includes/functions.php\n";
        echo "Please install WordPress test library:\n";
        echo "  bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
        exit(1);
    }

    // Give access to tests_add_filter() function
    require_once $_tests_dir . '/includes/functions.php';

    /**
     * Manually load the plugin being tested
     */
    function _manually_load_plugin() {
        require dirname(__DIR__) . '/wp-content/plugins/inat-observations-wp/inat-observations-wp.php';
    }

    // Load plugin before WordPress test suite starts
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    // Start up the WP testing environment
    require $_tests_dir . '/includes/bootstrap.php';
}
