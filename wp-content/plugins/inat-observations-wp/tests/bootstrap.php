<?php
/**
 * PHPUnit Bootstrap for WordPress Plugin Testing
 *
 * This bootstrap file initializes the WordPress testing environment for the
 * inat-observations-wp plugin. It configures the test framework, loads WordPress
 * core test libraries, and sets up the plugin for testing.
 *
 * Environment Requirements:
 * - WordPress test library (wordpress-develop/tests/phpunit)
 * - PHPUnit 9.x or higher
 * - MySQL test database
 *
 * Usage:
 * Run from plugin root: vendor/bin/phpunit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

// Define test environment
define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__FILE__) . '/../vendor/yoast/phpunit-polyfills');

// WordPress test configuration
// These can be overridden by environment variables
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Database configuration for tests
// Tests should use a separate database that gets reset between runs
define('DB_NAME', getenv('WP_TEST_DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('WP_TEST_DB_USER') ?: 'wordpress');
define('DB_PASSWORD', getenv('WP_TEST_DB_PASSWORD') ?: 'wordpress');
define('DB_HOST', getenv('WP_TEST_DB_HOST') ?: 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// WordPress table prefix for tests
$table_prefix = 'wptests_';

// Plugin constants (replicate main plugin file constants)
define('INAT_OBS_VERSION', '0.1.0');
define('INAT_OBS_PATH', dirname(__DIR__) . '/');
define('INAT_OBS_URL', 'http://localhost/wp-content/plugins/inat-observations-wp/');

// Environment variables for testing
putenv('INAT_PROJECT_SLUG=test-project-slug');
putenv('INAT_API_TOKEN=test_api_token_here');
putenv('CACHE_LIFETIME=3600');

// Load WordPress test library
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for testing
 *
 * The tests_add_filter callback is executed before WordPress loads plugins,
 * allowing us to manually activate our plugin for testing.
 */
tests_add_filter('muplugins_loaded', function() {
    // Load plugin files manually
    require dirname(__DIR__) . '/includes/api.php';
    require dirname(__DIR__) . '/includes/db-schema.php';
    require dirname(__DIR__) . '/includes/shortcode.php';
    require dirname(__DIR__) . '/includes/rest.php';
    require dirname(__DIR__) . '/includes/admin.php';
    require dirname(__DIR__) . '/includes/init.php';
});

// Start WordPress testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test utilities and base classes
require_once dirname(__FILE__) . '/TestCase.php';
