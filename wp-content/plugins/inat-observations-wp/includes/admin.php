<?php
    /**
     * WordPress Admin Interface Module
     *
     * Administrator Interface and Configuration Management
     * ====================================================
     * This module provides WordPress administrative interfaces for plugin management,
     * enabling site administrators to view plugin configuration and manage settings.
     * It integrates with WordPress admin menu system and capabilities.
     *
     * Exported Functions
     * ==================
     * - inat_obs_settings_page(): Render admin settings page
     *
     * Architecture Relationships
     * ==========================
     * Called by:
     *   - WordPress core: admin_menu hook for menu registration
     *   - WordPress core: When admin accesses settings page
     *
     * Calls to:
     *   - WordPress core: add_options_page() for menu registration
     *   - WordPress core: wp_get_current_user(), current_user_can()
     *   - WordPress core: getenv() and wp_options API (future)
     *   - WordPress core: esc_html(), esc_attr() for output escaping
     *
     * WordPress Hook Integration
     * ==========================
     * - add_action('admin_menu'): Register settings menu item
     *   Triggered after all admin pages load
     * - Capability check: manage_options (admin-only)
     * - Menu location: Settings submenu (add_options_page)
     *
     * Admin Interface Details
     * =======================
     * Menu Item:
     *   - Location: WordPress Admin > Settings > iNat Observations
     *   - Title: 'iNat Observations' (menu item) / 'iNaturalist Observations' (page title)
     *   - Slug: 'inat-observations'
     *   - URL: /wp-admin/options-general.php?page=inat-observations
     *   - Capability: manage_options (administrators only)
     *
     * Current Page Content:
     *   - Current Configuration display (read-only)
     *     - Project Slug (from INAT_PROJECT_SLUG env var)
     *     - API Token status (from INAT_API_TOKEN env var)
     *     - Cache Lifetime (from CACHE_LIFETIME env var)
     *   - Configuration Instructions section
     *     - .env file setup example
     *     - Variable descriptions
     *     - Links to documentation
     *   - Shortcode Usage section
     *     - [inat_observations] examples
     *     - Attribute descriptions
     *     - Usage examples
     *   - Notice: Settings form not yet implemented
     *
     * Security Features
     * =================
     * - Capability check: current_user_can('manage_options')
     * - Access logging: error_log() for auditing
     * - Output escaping: esc_html(), esc_attr() throughout
     * - ARIA labels: Screen reader support for accessibility
     * - Role-based access: admin-only via WordPress capabilities
     *
     * Future Implementation (TODO)
     * ===========================
     * Settings Form:
     *   - Create nonce-protected form with nonce_field()
     *   - Add input fields:
     *     - INAT_API_TOKEN (password input, never displayed)
     *     - INAT_PROJECT_SLUG (text input with validation)
     *     - CACHE_LIFETIME (select dropdown: 1hr, 6hr, 24hr)
     *     - Manual Refresh button (trigger immediate fetch)
     *   - Form submission handler in $_POST
     *   - Validation and sanitization of inputs
     *   - Storage to wp_options table instead of .env
     *   - Success/error message display
     *
     * Configuration Display (Future):
     *   - Last refresh timestamp
     *   - Total cached observations count
     *   - API rate limit status (if available)
     *   - Manual refresh button
     *   - Database table size
     *   - Cache hit/miss statistics
     *
     * Security (Future):
     *   - Implement nonce_field() for form protection
     *   - Sanitize all inputs with sanitize_text_field()
     *   - Validate project slug format
     *   - Token masking (never echo plain token)
     *   - CSRF protection via nonce verification
     *   - Rate limiting on manual refresh button
     *
     * Accessibility (Future):
     *   - Form labels with for attributes
     *   - Error message role="alert"
     *   - Success message role="status"
     *   - Keyboard navigation of form fields
     *   - Screen reader announcements for status changes
     */

    // Exit immediately if accessed directly (security check)
    if (!defined('ABSPATH')) exit;

    /**
     * Register Admin Menu Item
     *
     * Creates a menu item in WordPress admin under Settings menu.
     * WordPress calls the 'admin_menu' hook after all admin page actions.
     *
     * Hooks into: admin_menu (WordPress core)
     * Creates: Settings submenu item "iNat Observations"
     */
    add_action('admin_menu', function () {
        error_log('[iNat Observations] Registering admin menu page');

        // Add submenu page under Settings
        // Parameters: parent_slug, page_title, menu_title, capability, menu_slug, function
        add_options_page(
            'iNaturalist Observations',  // Browser tab title
            'iNat Observations',         // Menu item label
            'manage_options',            // Required capability (admin-only)
            'inat-observations',         // Menu slug for URL
            'inat_obs_settings_page'     // Callback function to render
        );
    });

    /**
     * Render the Plugin Settings Page
     *
     * Displays the settings interface in WordPress admin.
     * Currently a stub - full implementation pending.
     *
     * Access Control:
     * - Only accessible to users with manage_options capability (admins)
     * - Access attempts logged for auditing
     *
     * Page Location:
     * WordPress admin Settings menu > iNat Observations
     * URL: /wp-admin/options-general.php?page=inat-observations
     *
     * Current Content:
     * - Page title and description
     * - Placeholder message directing users to .env file
     *
     * Called by: WordPress admin system when accessing the settings page
     * Side Effects: Logs access, outputs HTML, exits if permission denied
     *
     * TODO - Full Implementation:
     * - Create form with fields:
     *   - API Token input (password field, never displayed)
     *   - Project Slug input with validation
     *   - Cache Lifetime selector (dropdown: 1hr, 6hr, 24hr)
     *   - Manual Refresh button
     * - Add current configuration display:
     *   - Last refresh timestamp
     *   - Total cached observations
     *   - API quota status
     * - Implement form submission handler
     * - Add nonce verification for security
     * - Store values in wp_options table
     * - Display success/error messages after save
     * - Consider using WordPress Settings API for consistency
     *
     * Security TODOs:
     * - Verify user has manage_options capability
     * - Implement nonce_field() for form protection
     * - Sanitize all form inputs with sanitize_text_field()
     * - Validate project slug format
     * - Never echo API tokens in plain text
     * - Escape all output with esc_html/esc_attr
     */
    function inat_obs_settings_page() {
        $current_user = wp_get_current_user();
        error_log('[iNat Observations] Settings page accessed by user: ' . $current_user->user_login);

        // Verify user has required capability to access this page
        if (!current_user_can('manage_options')) {
            error_log('[iNat Observations] Access denied - user lacks manage_options capability');
            return;
        }

        error_log('[iNat Observations] Rendering settings page');

        // Get current configuration values for display
        $current_project = getenv('INAT_PROJECT_SLUG') ?: '';
        $current_cache_lifetime = getenv('CACHE_LIFETIME') ?: '3600';
        $has_api_token = !empty(getenv('INAT_API_TOKEN'));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('iNaturalist Observations Settings', 'inat-observations-wp'); ?></h1>

            <!-- Screen reader announcement for page context -->
            <div class="screen-reader-text" role="status">
                <?php esc_html_e('Settings page for configuring iNaturalist Observations plugin.', 'inat-observations-wp'); ?>
            </div>

            <!-- Notice about current implementation status -->
            <div class="notice notice-info" role="alert" aria-live="polite">
                <p>
                    <strong><?php esc_html_e('Note:', 'inat-observations-wp'); ?></strong>
                    <?php esc_html_e('The settings form is not yet implemented. Configuration is currently managed through environment variables.', 'inat-observations-wp'); ?>
                </p>
            </div>

            <!-- Current Configuration Status -->
            <section aria-labelledby="inat-config-heading">
                <h2 id="inat-config-heading"><?php esc_html_e('Current Configuration', 'inat-observations-wp'); ?></h2>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Project Slug', 'inat-observations-wp'); ?></label>
                            </th>
                            <td>
                                <?php if ($current_project) : ?>
                                    <code><?php echo esc_html($current_project); ?></code>
                                <?php else : ?>
                                    <span class="description">
                                        <?php esc_html_e('Not configured', 'inat-observations-wp'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('API Token', 'inat-observations-wp'); ?></label>
                            </th>
                            <td>
                                <?php if ($has_api_token) : ?>
                                    <span class="dashicons dashicons-yes-alt" aria-hidden="true" style="color: #46b450;"></span>
                                    <span class="screen-reader-text"><?php esc_html_e('API token is configured', 'inat-observations-wp'); ?></span>
                                    <?php esc_html_e('Configured', 'inat-observations-wp'); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-warning" aria-hidden="true" style="color: #f0b849;"></span>
                                    <span class="screen-reader-text"><?php esc_html_e('API token is not configured', 'inat-observations-wp'); ?></span>
                                    <?php esc_html_e('Not configured (optional)', 'inat-observations-wp'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Cache Lifetime', 'inat-observations-wp'); ?></label>
                            </th>
                            <td>
                                <code><?php echo esc_html($current_cache_lifetime); ?></code>
                                <?php esc_html_e('seconds', 'inat-observations-wp'); ?>
                                <span class="description">
                                    (<?php echo esc_html(human_time_diff(0, intval($current_cache_lifetime))); ?>)
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Configuration Instructions -->
            <section aria-labelledby="inat-instructions-heading">
                <h2 id="inat-instructions-heading"><?php esc_html_e('Configuration Instructions', 'inat-observations-wp'); ?></h2>

                <p><?php esc_html_e('To configure this plugin, create or edit the .env file in your WordPress root directory with the following variables:', 'inat-observations-wp'); ?></p>

                <div class="code-block" style="background: #f5f5f5; padding: 1rem; border-left: 4px solid #0073aa; margin: 1rem 0;">
                    <pre style="margin: 0; white-space: pre-wrap;" aria-label="<?php esc_attr_e('Environment variable configuration example', 'inat-observations-wp'); ?>"><code>INAT_PROJECT_SLUG=your-project-slug
INAT_API_TOKEN=your-api-token-here
CACHE_LIFETIME=3600</code></pre>
                </div>

                <h3><?php esc_html_e('Variable Descriptions', 'inat-observations-wp'); ?></h3>
                <dl>
                    <dt><strong>INAT_PROJECT_SLUG</strong></dt>
                    <dd><?php esc_html_e('The unique identifier for your iNaturalist project. You can find this in the URL of your project page.', 'inat-observations-wp'); ?></dd>

                    <dt><strong>INAT_API_TOKEN</strong></dt>
                    <dd><?php esc_html_e('Optional. An API token for higher rate limits. Generate one from your iNaturalist account settings.', 'inat-observations-wp'); ?></dd>

                    <dt><strong>CACHE_LIFETIME</strong></dt>
                    <dd><?php esc_html_e('How long to cache API responses, in seconds. Default is 3600 (1 hour).', 'inat-observations-wp'); ?></dd>
                </dl>
            </section>

            <!-- Shortcode Usage -->
            <section aria-labelledby="inat-usage-heading">
                <h2 id="inat-usage-heading"><?php esc_html_e('Shortcode Usage', 'inat-observations-wp'); ?></h2>

                <p><?php esc_html_e('Add the following shortcode to any page or post to display observations:', 'inat-observations-wp'); ?></p>

                <div class="code-block" style="background: #f5f5f5; padding: 1rem; border-left: 4px solid #0073aa; margin: 1rem 0;">
                    <code>[inat_observations]</code>
                </div>

                <h3><?php esc_html_e('Optional Attributes', 'inat-observations-wp'); ?></h3>
                <table class="widefat" role="table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Attribute', 'inat-observations-wp'); ?></th>
                            <th scope="col"><?php esc_html_e('Description', 'inat-observations-wp'); ?></th>
                            <th scope="col"><?php esc_html_e('Default', 'inat-observations-wp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>project</code></td>
                            <td><?php esc_html_e('Override the project slug from environment variable', 'inat-observations-wp'); ?></td>
                            <td><?php esc_html_e('INAT_PROJECT_SLUG env var', 'inat-observations-wp'); ?></td>
                        </tr>
                        <tr>
                            <td><code>per_page</code></td>
                            <td><?php esc_html_e('Number of observations to display', 'inat-observations-wp'); ?></td>
                            <td>50</td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php esc_html_e('Example', 'inat-observations-wp'); ?></h3>
                <div class="code-block" style="background: #f5f5f5; padding: 1rem; border-left: 4px solid #0073aa; margin: 1rem 0;">
                    <code>[inat_observations project="my-project" per_page="25"]</code>
                </div>
            </section>
        </div>
        <?php

        // TODO: Implement full settings form:
        // 1. Create form with nonce_field() for security
        // 2. Add input fields for:
        //    - INAT_API_TOKEN (password input)
        //    - INAT_PROJECT_SLUG (text input with validation)
        //    - CACHE_LIFETIME (select dropdown)
        // 3. Add submit button for form
        // 4. Implement $_POST handler to validate and save to wp_options
        // 5. Display success/error messages after save
        // 6. Show current configuration and last refresh timestamp
    }
