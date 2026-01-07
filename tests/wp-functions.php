<?php
/**
 * WordPress Function Stubs for Testing
 *
 * Defines WordPress functions needed for unit testing without full WordPress.
 * Loaded early in bootstrap.php before any plugin files.
 */

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
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

if (!function_exists('remove_accents')) {
    /**
     * Remove accents from a string (WordPress function stub for testing).
     *
     * Converts accented characters to their non-accented equivalents.
     * Simplified implementation for testing - WordPress has more comprehensive handling.
     *
     * @param string $string Text to remove accents from
     * @return string Text with accents removed
     */
    function remove_accents($string) {
        // Simple accent removal for common cases (TODO-BUG-002)
        // This is a simplified version - WordPress has more comprehensive handling
        $chars = [
            // Decompositions for Latin-1 Supplement
            'ª' => 'a', 'º' => 'o',
            'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Æ' => 'AE', 'Ç' => 'C',
            'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ð' => 'D', 'Ñ' => 'N',
            'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'd', 'ñ' => 'n',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'þ' => 'th', 'ÿ' => 'y',
        ];

        return strtr($string, $chars);
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
