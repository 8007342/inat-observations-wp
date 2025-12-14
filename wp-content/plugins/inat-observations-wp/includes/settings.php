<?php
/**
 * Plugin Settings Management Module
 *
 * Centralized Configuration Access Layer
 * ======================================
 * This module provides a unified interface for accessing plugin settings,
 * with intelligent fallback to environment variables for backward compatibility.
 * All settings are stored in WordPress wp_options table using the Settings API.
 *
 * Exported Functions
 * ==================
 * - inat_obs_get_setting($key, $default): Get a single setting value
 * - inat_obs_get_all_settings(): Get all settings as array
 * - inat_obs_register_settings(): Register settings with WordPress
 *
 * Architecture Relationships
 * ==========================
 * Called by:
 *   - api.php: Gets project_slug, api_token, cache_lifetime
 *   - shortcode.php: Gets project_slug, default_per_page
 *   - init.php: Gets refresh_interval for cron scheduling
 *   - admin.php: Gets all settings for display and form population
 *
 * Settings Stored
 * ===============
 * Option name: inat_obs_settings (single serialized array in wp_options)
 *
 * Keys:
 *   - project_slug: iNaturalist project identifier (string)
 *   - api_token: Optional API authentication token (string, encrypted)
 *   - default_per_page: Default observations per page (int, 1-200)
 *   - cache_lifetime: Transient cache duration in seconds (int)
 *   - refresh_interval: WP-Cron schedule frequency (string: hourly|twicedaily|daily)
 *   - enable_rest_api: Whether REST endpoint is enabled (bool)
 *   - debug_mode: Enable verbose logging (bool)
 *
 * Fallback Chain
 * ==============
 * Settings are retrieved in this priority order:
 * 1. wp_options (inat_obs_settings) - User-configured via admin UI
 * 2. Environment variables (INAT_PROJECT_SLUG, etc.) - Legacy/server config
 * 3. Default values defined in this module
 */

// Exit immediately if accessed directly (security check)
if (!defined('ABSPATH')) exit;

/**
 * Default Settings Values
 *
 * Centralized defaults ensure consistency across all getters.
 * These are used when neither wp_options nor env vars are set.
 */
define('INAT_OBS_DEFAULTS', [
    'project_slug'      => '',
    'api_token'         => '',
    'default_per_page'  => 50,
    'cache_lifetime'    => 3600,        // 1 hour in seconds
    'refresh_interval'  => 'daily',     // WP-Cron schedule
    'enable_rest_api'   => true,
    'debug_mode'        => false,
]);

/**
 * Environment Variable Mapping
 *
 * Maps setting keys to their legacy environment variable names.
 * Used for backward compatibility with .env-based configuration.
 */
define('INAT_OBS_ENV_MAP', [
    'project_slug'     => 'INAT_PROJECT_SLUG',
    'api_token'        => 'INAT_API_TOKEN',
    'cache_lifetime'   => 'CACHE_LIFETIME',
]);

/**
 * Get a Single Plugin Setting
 *
 * Retrieves a setting value using the fallback chain:
 * wp_options → environment variable → default value
 *
 * @param string $key     The setting key to retrieve
 * @param mixed  $default Optional override for default value
 * @return mixed The setting value
 *
 * Example:
 *   $slug = inat_obs_get_setting('project_slug');
 *   $per_page = inat_obs_get_setting('default_per_page', 25);
 */
function inat_obs_get_setting($key, $default = null) {
    // Get all stored settings from wp_options
    $settings = get_option('inat_obs_settings', []);

    // Determine the final default value
    $defaults = INAT_OBS_DEFAULTS;
    $final_default = $default !== null ? $default : ($defaults[$key] ?? null);

    // Check wp_options first
    if (isset($settings[$key]) && $settings[$key] !== '') {
        return $settings[$key];
    }

    // Fallback to environment variable if mapped
    $env_map = INAT_OBS_ENV_MAP;
    if (isset($env_map[$key])) {
        $env_value = getenv($env_map[$key]);
        if ($env_value !== false && $env_value !== '') {
            // Type-cast based on default type
            if (is_int($final_default)) {
                return absint($env_value);
            }
            if (is_bool($final_default)) {
                return filter_var($env_value, FILTER_VALIDATE_BOOLEAN);
            }
            return $env_value;
        }
    }

    // Return default
    return $final_default;
}

/**
 * Get All Plugin Settings
 *
 * Returns all settings as an associative array.
 * Useful for bulk operations or passing to JavaScript.
 *
 * @return array All settings with current values
 *
 * Example:
 *   $all = inat_obs_get_all_settings();
 *   wp_localize_script('my-script', 'inatConfig', $all);
 */
function inat_obs_get_all_settings() {
    $settings = [];
    foreach (array_keys(INAT_OBS_DEFAULTS) as $key) {
        $settings[$key] = inat_obs_get_setting($key);
    }
    return $settings;
}

/**
 * Register Plugin Settings with WordPress
 *
 * Uses the WordPress Settings API to register all plugin settings.
 * Called during admin_init to set up settings, sections, and fields.
 *
 * Security:
 * - All settings sanitized via sanitize callback
 * - Nonce verification handled by WordPress Settings API
 * - Capability check: manage_options (admin only)
 *
 * Called by: add_action('admin_init', 'inat_obs_register_settings')
 */
function inat_obs_register_settings() {
    // Register the settings group - stores all settings as single serialized option
    register_setting(
        'inat_obs_settings_group',      // Option group (used in settings_fields())
        'inat_obs_settings',            // Option name in wp_options table
        [
            'type'              => 'array',
            'sanitize_callback' => 'inat_obs_sanitize_settings',
            'default'           => INAT_OBS_DEFAULTS,
        ]
    );

    // Add settings section for API Configuration
    add_settings_section(
        'inat_obs_api_section',
        __('API Configuration', 'inat-observations-wp'),
        'inat_obs_api_section_callback',
        'inat-observations'
    );

    // Add settings section for Display Options
    add_settings_section(
        'inat_obs_display_section',
        __('Display Options', 'inat-observations-wp'),
        'inat_obs_display_section_callback',
        'inat-observations'
    );

    // Add settings section for Performance
    add_settings_section(
        'inat_obs_performance_section',
        __('Performance & Caching', 'inat-observations-wp'),
        'inat_obs_performance_section_callback',
        'inat-observations'
    );

    // Add settings section for Advanced
    add_settings_section(
        'inat_obs_advanced_section',
        __('Advanced Options', 'inat-observations-wp'),
        'inat_obs_advanced_section_callback',
        'inat-observations'
    );

    // Register individual settings fields

    // Project Slug (API Section)
    add_settings_field(
        'project_slug',
        __('Project Slug', 'inat-observations-wp'),
        'inat_obs_field_project_slug',
        'inat-observations',
        'inat_obs_api_section',
        ['label_for' => 'inat_obs_project_slug']
    );

    // API Token (API Section)
    add_settings_field(
        'api_token',
        __('API Token', 'inat-observations-wp'),
        'inat_obs_field_api_token',
        'inat-observations',
        'inat_obs_api_section',
        ['label_for' => 'inat_obs_api_token']
    );

    // Default Per Page (Display Section)
    add_settings_field(
        'default_per_page',
        __('Default Page Size', 'inat-observations-wp'),
        'inat_obs_field_per_page',
        'inat-observations',
        'inat_obs_display_section',
        ['label_for' => 'inat_obs_default_per_page']
    );

    // Cache Lifetime (Performance Section)
    add_settings_field(
        'cache_lifetime',
        __('Cache Lifetime', 'inat-observations-wp'),
        'inat_obs_field_cache_lifetime',
        'inat-observations',
        'inat_obs_performance_section',
        ['label_for' => 'inat_obs_cache_lifetime']
    );

    // Refresh Interval (Performance Section)
    add_settings_field(
        'refresh_interval',
        __('Auto-Refresh Schedule', 'inat-observations-wp'),
        'inat_obs_field_refresh_interval',
        'inat-observations',
        'inat_obs_performance_section',
        ['label_for' => 'inat_obs_refresh_interval']
    );

    // Enable REST API (Advanced Section)
    add_settings_field(
        'enable_rest_api',
        __('REST API', 'inat-observations-wp'),
        'inat_obs_field_rest_api',
        'inat-observations',
        'inat_obs_advanced_section',
        ['label_for' => 'inat_obs_enable_rest_api']
    );

    // Debug Mode (Advanced Section)
    add_settings_field(
        'debug_mode',
        __('Debug Mode', 'inat-observations-wp'),
        'inat_obs_field_debug_mode',
        'inat-observations',
        'inat_obs_advanced_section',
        ['label_for' => 'inat_obs_debug_mode']
    );
}
add_action('admin_init', 'inat_obs_register_settings');

/**
 * Sanitize Settings on Save
 *
 * Validates and sanitizes all settings before storing in database.
 * Called automatically by WordPress Settings API on form submission.
 *
 * @param array $input Raw input from settings form
 * @return array Sanitized settings array
 */
function inat_obs_sanitize_settings($input) {
    $sanitized = [];

    // Project Slug - alphanumeric, hyphens, underscores only
    if (isset($input['project_slug'])) {
        $sanitized['project_slug'] = sanitize_text_field($input['project_slug']);
        // Remove any characters that aren't valid in a URL slug
        $sanitized['project_slug'] = preg_replace('/[^a-zA-Z0-9_-]/', '', $sanitized['project_slug']);
    }

    // API Token - sanitize but preserve token characters
    if (isset($input['api_token'])) {
        // Only update if not the placeholder
        if ($input['api_token'] !== '••••••••••••••••') {
            $sanitized['api_token'] = sanitize_text_field($input['api_token']);
            // Remove control characters
            $sanitized['api_token'] = preg_replace('/[\x00-\x1F\x7F]/', '', trim($sanitized['api_token']));
        } else {
            // Keep existing token
            $existing = get_option('inat_obs_settings', []);
            $sanitized['api_token'] = $existing['api_token'] ?? '';
        }
    }

    // Default Per Page - integer between 1 and 200
    if (isset($input['default_per_page'])) {
        $sanitized['default_per_page'] = absint($input['default_per_page']);
        $sanitized['default_per_page'] = max(1, min(200, $sanitized['default_per_page']));
    }

    // Cache Lifetime - integer in seconds (preset values)
    if (isset($input['cache_lifetime'])) {
        $valid_lifetimes = [300, 900, 1800, 3600, 7200, 14400, 43200, 86400];
        $sanitized['cache_lifetime'] = absint($input['cache_lifetime']);
        if (!in_array($sanitized['cache_lifetime'], $valid_lifetimes, true)) {
            $sanitized['cache_lifetime'] = 3600; // Default to 1 hour
        }
    }

    // Refresh Interval - must be valid WP-Cron schedule
    if (isset($input['refresh_interval'])) {
        $valid_intervals = ['hourly', 'twicedaily', 'daily', 'weekly'];
        $sanitized['refresh_interval'] = sanitize_text_field($input['refresh_interval']);
        if (!in_array($sanitized['refresh_interval'], $valid_intervals, true)) {
            $sanitized['refresh_interval'] = 'daily';
        }
    }

    // Enable REST API - boolean
    $sanitized['enable_rest_api'] = isset($input['enable_rest_api']) && $input['enable_rest_api'] === '1';

    // Debug Mode - boolean
    $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] === '1';

    // Handle cron schedule update if refresh interval changed
    $existing = get_option('inat_obs_settings', []);
    $old_interval = $existing['refresh_interval'] ?? 'daily';
    $new_interval = $sanitized['refresh_interval'] ?? 'daily';

    if ($old_interval !== $new_interval) {
        // Clear old schedule and set new one
        wp_clear_scheduled_hook('inat_obs_refresh');
        if (!wp_next_scheduled('inat_obs_refresh')) {
            wp_schedule_event(time(), $new_interval, 'inat_obs_refresh');
        }
    }

    return $sanitized;
}

// ============================================================================
// Settings Section Callbacks
// ============================================================================

/**
 * API Configuration Section Description
 */
function inat_obs_api_section_callback() {
    echo '<p>' . esc_html__('Configure your connection to the iNaturalist API.', 'inat-observations-wp') . '</p>';
}

/**
 * Display Options Section Description
 */
function inat_obs_display_section_callback() {
    echo '<p>' . esc_html__('Customize how observations are displayed on your site.', 'inat-observations-wp') . '</p>';
}

/**
 * Performance Section Description
 */
function inat_obs_performance_section_callback() {
    echo '<p>' . esc_html__('Optimize caching and data refresh frequency to balance performance with data freshness.', 'inat-observations-wp') . '</p>';
}

/**
 * Advanced Section Description
 */
function inat_obs_advanced_section_callback() {
    echo '<p>' . esc_html__('Additional configuration options for advanced users.', 'inat-observations-wp') . '</p>';
}

// ============================================================================
// Settings Field Callbacks
// ============================================================================

/**
 * Project Slug Field
 */
function inat_obs_field_project_slug($args) {
    $value = inat_obs_get_setting('project_slug');
    $env_value = getenv('INAT_PROJECT_SLUG');
    ?>
    <input type="text"
           id="<?php echo esc_attr($args['label_for']); ?>"
           name="inat_obs_settings[project_slug]"
           value="<?php echo esc_attr($value); ?>"
           class="regular-text"
           placeholder="<?php esc_attr_e('your-project-slug', 'inat-observations-wp'); ?>"
           aria-describedby="project_slug_description">
    <p class="description" id="project_slug_description">
        <?php esc_html_e('The unique identifier for your iNaturalist project. Find this in your project URL:', 'inat-observations-wp'); ?>
        <code>inaturalist.org/projects/<strong>your-project-slug</strong></code>
        <?php if ($env_value) : ?>
            <br><span class="dashicons dashicons-info" aria-hidden="true"></span>
            <?php printf(
                /* translators: %s: environment variable value */
                esc_html__('Environment variable fallback: %s', 'inat-observations-wp'),
                '<code>' . esc_html($env_value) . '</code>'
            ); ?>
        <?php endif; ?>
    </p>
    <?php
}

/**
 * API Token Field
 */
function inat_obs_field_api_token($args) {
    $value = inat_obs_get_setting('api_token');
    $has_token = !empty($value);
    $env_has_token = !empty(getenv('INAT_API_TOKEN'));
    ?>
    <input type="password"
           id="<?php echo esc_attr($args['label_for']); ?>"
           name="inat_obs_settings[api_token]"
           value="<?php echo $has_token ? '••••••••••••••••' : ''; ?>"
           class="regular-text"
           placeholder="<?php esc_attr_e('Enter API token (optional)', 'inat-observations-wp'); ?>"
           aria-describedby="api_token_description"
           autocomplete="off">
    <?php if ($has_token || $env_has_token) : ?>
        <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;" aria-hidden="true"></span>
        <span style="color: #00a32a;"><?php esc_html_e('Token configured', 'inat-observations-wp'); ?></span>
    <?php endif; ?>
    <p class="description" id="api_token_description">
        <?php esc_html_e('Optional. An API token enables higher rate limits. Generate one from your', 'inat-observations-wp'); ?>
        <a href="https://www.inaturalist.org/users/api_token" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('iNaturalist account settings', 'inat-observations-wp'); ?>
            <span class="dashicons dashicons-external" aria-hidden="true" style="font-size: 14px; text-decoration: none;"></span>
        </a>.
        <?php esc_html_e('Leave blank to clear the token.', 'inat-observations-wp'); ?>
    </p>
    <?php
}

/**
 * Default Per Page Field
 */
function inat_obs_field_per_page($args) {
    $value = inat_obs_get_setting('default_per_page');
    ?>
    <input type="number"
           id="<?php echo esc_attr($args['label_for']); ?>"
           name="inat_obs_settings[default_per_page]"
           value="<?php echo esc_attr($value); ?>"
           min="1"
           max="200"
           step="1"
           class="small-text"
           aria-describedby="per_page_description">
    <p class="description" id="per_page_description">
        <?php esc_html_e('Number of observations to display by default (1-200). Can be overridden in shortcode with per_page attribute.', 'inat-observations-wp'); ?>
    </p>
    <?php
}

/**
 * Cache Lifetime Field
 */
function inat_obs_field_cache_lifetime($args) {
    $value = inat_obs_get_setting('cache_lifetime');
    $options = [
        300    => __('5 minutes', 'inat-observations-wp'),
        900    => __('15 minutes', 'inat-observations-wp'),
        1800   => __('30 minutes', 'inat-observations-wp'),
        3600   => __('1 hour', 'inat-observations-wp'),
        7200   => __('2 hours', 'inat-observations-wp'),
        14400  => __('4 hours', 'inat-observations-wp'),
        43200  => __('12 hours', 'inat-observations-wp'),
        86400  => __('24 hours', 'inat-observations-wp'),
    ];
    ?>
    <select id="<?php echo esc_attr($args['label_for']); ?>"
            name="inat_obs_settings[cache_lifetime]"
            aria-describedby="cache_lifetime_description">
        <?php foreach ($options as $seconds => $label) : ?>
            <option value="<?php echo esc_attr($seconds); ?>" <?php selected($value, $seconds); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description" id="cache_lifetime_description">
        <?php esc_html_e('How long to cache API responses. Shorter times mean fresher data but more API requests.', 'inat-observations-wp'); ?>
    </p>
    <?php
}

/**
 * Refresh Interval Field
 */
function inat_obs_field_refresh_interval($args) {
    $value = inat_obs_get_setting('refresh_interval');
    $next_scheduled = wp_next_scheduled('inat_obs_refresh');
    $options = [
        'hourly'     => __('Hourly', 'inat-observations-wp'),
        'twicedaily' => __('Twice Daily', 'inat-observations-wp'),
        'daily'      => __('Daily', 'inat-observations-wp'),
        'weekly'     => __('Weekly', 'inat-observations-wp'),
    ];
    ?>
    <select id="<?php echo esc_attr($args['label_for']); ?>"
            name="inat_obs_settings[refresh_interval]"
            aria-describedby="refresh_interval_description">
        <?php foreach ($options as $interval => $label) : ?>
            <option value="<?php echo esc_attr($interval); ?>" <?php selected($value, $interval); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($next_scheduled) : ?>
        <p class="description">
            <span class="dashicons dashicons-clock" aria-hidden="true"></span>
            <?php printf(
                /* translators: %s: human-readable time until next refresh */
                esc_html__('Next refresh: %s', 'inat-observations-wp'),
                esc_html(human_time_diff(time(), $next_scheduled))
            ); ?>
        </p>
    <?php endif; ?>
    <p class="description" id="refresh_interval_description">
        <?php esc_html_e('How often the plugin automatically fetches fresh data from iNaturalist in the background.', 'inat-observations-wp'); ?>
    </p>
    <?php
}

/**
 * Enable REST API Field
 */
function inat_obs_field_rest_api($args) {
    $value = inat_obs_get_setting('enable_rest_api');
    ?>
    <label for="<?php echo esc_attr($args['label_for']); ?>">
        <input type="checkbox"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="inat_obs_settings[enable_rest_api]"
               value="1"
               <?php checked($value, true); ?>
               aria-describedby="rest_api_description">
        <?php esc_html_e('Enable REST API endpoint', 'inat-observations-wp'); ?>
    </label>
    <p class="description" id="rest_api_description">
        <?php esc_html_e('When enabled, observations are accessible at:', 'inat-observations-wp'); ?>
        <code><?php echo esc_url(rest_url('inat/v1/observations')); ?></code>
    </p>
    <?php
}

/**
 * Debug Mode Field
 */
function inat_obs_field_debug_mode($args) {
    $value = inat_obs_get_setting('debug_mode');
    $wp_debug = defined('WP_DEBUG') && WP_DEBUG;
    ?>
    <label for="<?php echo esc_attr($args['label_for']); ?>">
        <input type="checkbox"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="inat_obs_settings[debug_mode]"
               value="1"
               <?php checked($value, true); ?>
               aria-describedby="debug_mode_description">
        <?php esc_html_e('Enable verbose debug logging', 'inat-observations-wp'); ?>
    </label>
    <?php if ($wp_debug) : ?>
        <span class="dashicons dashicons-warning" style="color: #dba617; vertical-align: middle;" aria-hidden="true"></span>
        <span style="color: #996800;"><?php esc_html_e('WP_DEBUG is enabled', 'inat-observations-wp'); ?></span>
    <?php endif; ?>
    <p class="description" id="debug_mode_description">
        <?php esc_html_e('Logs detailed information to the WordPress debug log. Useful for troubleshooting but may impact performance.', 'inat-observations-wp'); ?>
    </p>
    <?php
}

/**
 * Check if Debug Logging Should Be Active
 *
 * Helper function that checks both plugin setting and WP_DEBUG constant.
 * Use this instead of checking WP_DEBUG directly throughout the plugin.
 *
 * @return bool True if debug logging should be active
 */
function inat_obs_is_debug() {
    // Plugin debug mode setting takes precedence if explicitly enabled
    if (inat_obs_get_setting('debug_mode')) {
        return true;
    }
    // Fall back to WP_DEBUG for backward compatibility
    return defined('WP_DEBUG') && WP_DEBUG;
}

/**
 * Add Custom Cron Schedule for Weekly
 *
 * WordPress doesn't have a built-in 'weekly' schedule, so we add it.
 */
function inat_obs_add_cron_schedules($schedules) {
    $schedules['weekly'] = [
        'interval' => 604800, // 7 days in seconds
        'display'  => __('Once Weekly', 'inat-observations-wp'),
    ];
    return $schedules;
}
add_filter('cron_schedules', 'inat_obs_add_cron_schedules');
