<?php
/**
 * WordPress Admin Interface Module
 *
 * Administrator Interface and Configuration Management
 * ====================================================
 * This module provides WordPress administrative interfaces for plugin management,
 * enabling site administrators to configure settings, view status, and manage
 * the iNaturalist Observations plugin through a user-friendly UI.
 *
 * Exported Functions
 * ==================
 * - inat_obs_settings_page(): Render admin settings page with form
 *
 * Architecture Relationships
 * ==========================
 * Called by:
 *   - WordPress core: admin_menu hook for menu registration
 *   - WordPress core: When admin accesses settings page
 *
 * Calls to:
 *   - settings.php: inat_obs_get_setting(), inat_obs_get_all_settings()
 *   - WordPress core: Settings API functions (settings_fields, do_settings_sections)
 *   - WordPress core: add_options_page() for menu registration
 *
 * WordPress Hook Integration
 * ==========================
 * - add_action('admin_menu'): Register settings menu item
 * - add_action('admin_notices'): Display success/error messages
 * - Capability check: manage_options (admin-only)
 * - Menu location: Settings submenu (add_options_page)
 *
 * Security Features
 * =================
 * - Capability check: current_user_can('manage_options')
 * - Nonce verification: Via WordPress Settings API
 * - Output escaping: esc_html(), esc_attr() throughout
 * - Input sanitization: Via settings.php sanitize callback
 */

// Exit immediately if accessed directly (security check)
if (!defined('ABSPATH')) exit;

/**
 * Register Admin Menu Item
 *
 * Creates a menu item in WordPress admin under Settings menu.
 */
add_action('admin_menu', function () {
    if (inat_obs_is_debug()) {
        error_log('[iNat Observations] Registering admin menu page');
    }

    add_options_page(
        __('iNaturalist Observations Settings', 'inat-observations-wp'),
        __('iNat Observations', 'inat-observations-wp'),
        'manage_options',
        'inat-observations',
        'inat_obs_settings_page'
    );
});

/**
 * Display Admin Notices for Settings Save
 */
add_action('admin_notices', function () {
    // Only show on our settings page
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'settings_page_inat-observations') {
        return;
    }

    // Check for settings updated
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        ?>
        <div class="notice notice-success is-dismissible" role="status">
            <p>
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e('Settings saved successfully.', 'inat-observations-wp'); ?>
            </p>
        </div>
        <?php
    }
});

/**
 * Render the Plugin Settings Page
 *
 * Displays the complete settings interface with:
 * - Configuration form using WordPress Settings API
 * - Status dashboard showing current configuration
 * - Shortcode usage examples
 * - Quick actions (clear cache, test connection)
 */
function inat_obs_settings_page() {
    // Verify user has required capability
    if (!current_user_can('manage_options')) {
        if (inat_obs_is_debug()) {
            error_log('[iNat Observations] Access denied - user lacks manage_options capability');
        }
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'inat-observations-wp'));
    }

    if (inat_obs_is_debug()) {
        error_log('[iNat Observations] Rendering settings page');
    }

    // Get current settings for status display
    $project_slug = inat_obs_get_setting('project_slug');
    $has_token = !empty(inat_obs_get_setting('api_token'));
    $cache_lifetime = inat_obs_get_setting('cache_lifetime');
    $next_refresh = wp_next_scheduled('inat_obs_refresh');

    // Handle cache clear action
    if (isset($_POST['inat_clear_cache']) && check_admin_referer('inat_clear_cache_action')) {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_inat_obs_cache_%' OR option_name LIKE '_transient_timeout_inat_obs_cache_%'");
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared successfully.', 'inat-observations-wp') . '</p></div>';
    }

    // Handle manual refresh action
    if (isset($_POST['inat_manual_refresh']) && check_admin_referer('inat_manual_refresh_action')) {
        do_action('inat_obs_refresh');
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Manual refresh triggered.', 'inat-observations-wp') . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-admin-site-alt3" aria-hidden="true" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px; vertical-align: middle;"></span>
            <?php esc_html_e('iNaturalist Observations Settings', 'inat-observations-wp'); ?>
        </h1>

        <!-- Status Dashboard -->
        <div class="inat-status-dashboard" style="display: flex; gap: 1rem; margin: 1.5rem 0; flex-wrap: wrap;">
            <!-- Project Status Card -->
            <div class="card" style="flex: 1; min-width: 200px; max-width: 300px;">
                <h2 class="title" style="margin-top: 0;">
                    <span class="dashicons dashicons-location" aria-hidden="true"></span>
                    <?php esc_html_e('Project', 'inat-observations-wp'); ?>
                </h2>
                <?php if ($project_slug) : ?>
                    <p style="font-size: 1.2em; margin: 0;">
                        <code><?php echo esc_html($project_slug); ?></code>
                    </p>
                    <p style="margin: 0.5em 0 0 0;">
                        <a href="https://www.inaturalist.org/projects/<?php echo esc_attr($project_slug); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('View on iNaturalist', 'inat-observations-wp'); ?>
                            <span class="dashicons dashicons-external" aria-hidden="true" style="font-size: 14px;"></span>
                        </a>
                    </p>
                <?php else : ?>
                    <p style="color: #d63638;">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <?php esc_html_e('Not configured', 'inat-observations-wp'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- API Token Status Card -->
            <div class="card" style="flex: 1; min-width: 200px; max-width: 300px;">
                <h2 class="title" style="margin-top: 0;">
                    <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                    <?php esc_html_e('API Token', 'inat-observations-wp'); ?>
                </h2>
                <?php if ($has_token) : ?>
                    <p style="color: #00a32a; font-size: 1.1em; margin: 0;">
                        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php esc_html_e('Configured', 'inat-observations-wp'); ?>
                    </p>
                    <p class="description" style="margin: 0.5em 0 0 0;">
                        <?php esc_html_e('Higher rate limits enabled', 'inat-observations-wp'); ?>
                    </p>
                <?php else : ?>
                    <p style="color: #72aee6; font-size: 1.1em; margin: 0;">
                        <span class="dashicons dashicons-info" aria-hidden="true"></span>
                        <?php esc_html_e('Not configured', 'inat-observations-wp'); ?>
                    </p>
                    <p class="description" style="margin: 0.5em 0 0 0;">
                        <?php esc_html_e('Optional - basic limits apply', 'inat-observations-wp'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Cache Status Card -->
            <div class="card" style="flex: 1; min-width: 200px; max-width: 300px;">
                <h2 class="title" style="margin-top: 0;">
                    <span class="dashicons dashicons-performance" aria-hidden="true"></span>
                    <?php esc_html_e('Cache', 'inat-observations-wp'); ?>
                </h2>
                <p style="font-size: 1.1em; margin: 0;">
                    <?php echo esc_html(human_time_diff(0, $cache_lifetime)); ?>
                    <?php esc_html_e('lifetime', 'inat-observations-wp'); ?>
                </p>
                <?php if ($next_refresh) : ?>
                    <p class="description" style="margin: 0.5em 0 0 0;">
                        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                        <?php printf(
                            esc_html__('Next refresh: %s', 'inat-observations-wp'),
                            esc_html(human_time_diff(time(), $next_refresh))
                        ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Settings Form -->
        <form method="post" action="options.php">
            <?php
            // Output security fields (nonce, action, option_page)
            settings_fields('inat_obs_settings_group');

            // Output settings sections and fields
            do_settings_sections('inat-observations');

            // Submit button
            submit_button(__('Save Settings', 'inat-observations-wp'));
            ?>
        </form>

        <!-- Quick Actions -->
        <div class="card" style="max-width: 600px; margin-top: 2rem;">
            <h2 class="title" style="margin-top: 0;">
                <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                <?php esc_html_e('Quick Actions', 'inat-observations-wp'); ?>
            </h2>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <!-- Clear Cache -->
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('inat_clear_cache_action'); ?>
                    <button type="submit" name="inat_clear_cache" class="button">
                        <span class="dashicons dashicons-trash" aria-hidden="true" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Clear Cache', 'inat-observations-wp'); ?>
                    </button>
                </form>

                <!-- Manual Refresh -->
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('inat_manual_refresh_action'); ?>
                    <button type="submit" name="inat_manual_refresh" class="button">
                        <span class="dashicons dashicons-update" aria-hidden="true" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Refresh Now', 'inat-observations-wp'); ?>
                    </button>
                </form>

                <!-- Test Page -->
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>" class="button">
                    <span class="dashicons dashicons-welcome-add-page" aria-hidden="true" style="vertical-align: middle;"></span>
                    <?php esc_html_e('Create Test Page', 'inat-observations-wp'); ?>
                </a>
            </div>
        </div>

        <!-- Shortcode Usage -->
        <div class="card" style="max-width: 800px; margin-top: 2rem;">
            <h2 class="title" style="margin-top: 0;">
                <span class="dashicons dashicons-shortcode" aria-hidden="true"></span>
                <?php esc_html_e('Shortcode Usage', 'inat-observations-wp'); ?>
            </h2>

            <p><?php esc_html_e('Add the following shortcode to any page or post:', 'inat-observations-wp'); ?></p>

            <div style="background: #f6f7f7; padding: 1rem; border-left: 4px solid #2271b1; margin: 1rem 0;">
                <code style="font-size: 14px;">[inat_observations]</code>
            </div>

            <h3><?php esc_html_e('Optional Attributes', 'inat-observations-wp'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Attribute', 'inat-observations-wp'); ?></th>
                        <th><?php esc_html_e('Description', 'inat-observations-wp'); ?></th>
                        <th><?php esc_html_e('Default', 'inat-observations-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>project</code></td>
                        <td><?php esc_html_e('Override the project slug', 'inat-observations-wp'); ?></td>
                        <td><?php echo $project_slug ? '<code>' . esc_html($project_slug) . '</code>' : esc_html__('(from settings)', 'inat-observations-wp'); ?></td>
                    </tr>
                    <tr>
                        <td><code>per_page</code></td>
                        <td><?php esc_html_e('Number of observations (1-200)', 'inat-observations-wp'); ?></td>
                        <td><code><?php echo esc_html(inat_obs_get_setting('default_per_page')); ?></code></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php esc_html_e('Examples', 'inat-observations-wp'); ?></h3>
            <div style="background: #f6f7f7; padding: 1rem; border-left: 4px solid #2271b1; margin: 1rem 0;">
                <p style="margin: 0 0 0.5em 0;"><strong><?php esc_html_e('Basic:', 'inat-observations-wp'); ?></strong></p>
                <code>[inat_observations]</code>

                <p style="margin: 1em 0 0.5em 0;"><strong><?php esc_html_e('Custom settings:', 'inat-observations-wp'); ?></strong></p>
                <code>[inat_observations project="my-project" per_page="25"]</code>
            </div>
        </div>

        <!-- REST API Info (if enabled) -->
        <?php if (inat_obs_get_setting('enable_rest_api')) : ?>
        <div class="card" style="max-width: 800px; margin-top: 2rem;">
            <h2 class="title" style="margin-top: 0;">
                <span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
                <?php esc_html_e('REST API Endpoint', 'inat-observations-wp'); ?>
            </h2>
            <p><?php esc_html_e('Observations are available via the REST API at:', 'inat-observations-wp'); ?></p>
            <div style="background: #f6f7f7; padding: 1rem; border-left: 4px solid #2271b1; margin: 1rem 0;">
                <code style="word-break: break-all;"><?php echo esc_url(rest_url('inat/v1/observations')); ?></code>
            </div>
            <p class="description">
                <?php esc_html_e('Query parameters: per_page (1-200), page, project', 'inat-observations-wp'); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
