<?php
    // Admin pages and settings.
    if (!defined('ABSPATH')) exit;

    // Default project ID for San Diego Mycological Society (sdmyco.org)
    // This is the original inspiration for this plugin - aiding their observation tracking work
    define('INAT_OBS_DEFAULT_PROJECT_ID', 'sdmyco');

    add_action('admin_menu', function () {
        add_options_page('iNaturalist Observations', 'iNat Observations', 'manage_options', 'inat-observations', 'inat_obs_settings_page');
    });

    // Add "Settings" link on plugin page
    add_filter('plugin_action_links_inat-observations-wp/inat-observations-wp.php', function($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=inat-observations') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    });

    add_action('admin_init', 'inat_obs_register_settings');

    function inat_obs_register_settings() {
        register_setting('inat_obs_settings_group', 'inat_obs_user_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);

        register_setting('inat_obs_settings_group', 'inat_obs_project_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => INAT_OBS_DEFAULT_PROJECT_ID,
        ]);

        add_settings_section(
            'inat_obs_config_section',
            'Configuration',
            'inat_obs_config_section_callback',
            'inat-observations'
        );

        add_settings_field(
            'inat_obs_user_id',
            'User ID',
            'inat_obs_user_id_field_callback',
            'inat-observations',
            'inat_obs_config_section'
        );

        add_settings_field(
            'inat_obs_project_id',
            'Project ID',
            'inat_obs_project_id_field_callback',
            'inat-observations',
            'inat_obs_config_section'
        );

        add_settings_section(
            'inat_obs_status_section',
            'Status',
            'inat_obs_status_section_callback',
            'inat-observations'
        );
    }

    function inat_obs_config_section_callback() {
        echo '<p>At least one of the following is required. Configure either a User ID or a Project ID (or both) to fetch observations from iNaturalist.</p>';
    }

    function inat_obs_user_id_field_callback() {
        $value = get_option('inat_obs_user_id', '');
        echo '<input type="text" id="inat_obs_user_id" name="inat_obs_user_id" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., 123456">';
        echo '<p class="description">Fetch observations by a specific iNaturalist user. Find the user ID in the URL: <code>https://www.inaturalist.org/people/<strong>123456</strong></code></p>';
    }

    function inat_obs_project_id_field_callback() {
        $value = get_option('inat_obs_project_id', INAT_OBS_DEFAULT_PROJECT_ID);
        echo '<input type="text" id="inat_obs_project_id" name="inat_obs_project_id" value="' . esc_attr($value) . '" class="regular-text" placeholder="e.g., sdmyco">';
        echo '<p class="description">Fetch observations from a specific project. Find the project slug in the URL: <code>https://www.inaturalist.org/projects/<strong>sdmyco</strong></code></p>';
        // Nearby documentation about default value (not prominent)
        if ($value === INAT_OBS_DEFAULT_PROJECT_ID) {
            echo '<p class="description" style="color: #666; font-size: 0.9em;">Default: San Diego Mycological Society (<a href="https://sdmyco.org" target="_blank">sdmyco.org</a>) - the original inspiration for this plugin.</p>';
        }
    }

    function inat_obs_status_section_callback() {
        $last_refresh = get_option('inat_obs_last_refresh', '');
        $last_count = get_option('inat_obs_last_refresh_count', 0);
        $next_scheduled = wp_next_scheduled('inat_obs_refresh');

        if ($last_refresh) {
            echo '<p><strong>Last Refresh:</strong> ' . esc_html($last_refresh) . ' (' . esc_html($last_count) . ' observations)</p>';
        } else {
            echo '<p><strong>Last Refresh:</strong> Never</p>';
        }

        if ($next_scheduled) {
            $next_time = date('Y-m-d H:i:s', $next_scheduled);
            echo '<p><strong>Next Scheduled:</strong> ' . esc_html($next_time) . ' (daily)</p>';
        } else {
            echo '<p><strong>Next Scheduled:</strong> Not scheduled</p>';
        }

        echo '<p><button type="button" id="inat_obs_refresh_now" class="button button-secondary">Refresh Now</button></p>';
        echo '<p class="description">Manually trigger a refresh to fetch the latest observations from iNaturalist.</p>';
        echo '<div id="inat_obs_refresh_message" style="display:none; margin-top: 10px;"></div>';
    }

    function inat_obs_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
        }

        // Handle form submission
        if (isset($_POST['inat_obs_settings_submit'])) {
            // Verify nonce
            check_admin_referer('inat_obs_settings_save', 'inat_obs_settings_nonce');

            // Get values
            $user_id = sanitize_text_field($_POST['inat_obs_user_id'] ?? '');
            $project_id = sanitize_text_field($_POST['inat_obs_project_id'] ?? '');

            // Validation: at least one required
            if (empty($user_id) && empty($project_id)) {
                add_settings_error(
                    'inat_obs_settings',
                    'missing_id',
                    'At least one of User ID or Project ID is required.',
                    'error'
                );
            } else {
                // Save settings
                update_option('inat_obs_user_id', $user_id);
                update_option('inat_obs_project_id', $project_id);

                add_settings_error(
                    'inat_obs_settings',
                    'settings_saved',
                    'Settings saved successfully.',
                    'updated'
                );
            }
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('inat_obs_settings'); ?>

            <form method="post" action="">
                <?php
                wp_nonce_field('inat_obs_settings_save', 'inat_obs_settings_nonce');
                settings_fields('inat_obs_settings_group');
                do_settings_sections('inat-observations');
                submit_button('Save Settings', 'primary', 'inat_obs_settings_submit');
                ?>
            </form>

            <hr style="margin: 40px 0;">

            <div style="background: #f0f0f1; padding: 20px; border-left: 4px solid #2271b1;">
                <h2 style="margin-top: 0;">📖 Usage Instructions</h2>

                <h3>Display Observations on Your Site</h3>
                <p>Create a new page or post and use the shortcode:</p>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; font-family: monospace;">[inat_observations]</pre>

                <h3>Shortcode Attributes</h3>
                <p>Customize the display with optional attributes:</p>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; font-family: monospace;">[inat_observations project="sdmyco" per_page="50"]</pre>

                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Attribute</th>
                            <th>Description</th>
                            <th style="width: 150px;">Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>project</code></td>
                            <td>Project slug from iNaturalist URL</td>
                            <td><?php echo esc_html(get_option('inat_obs_project_id', INAT_OBS_DEFAULT_PROJECT_ID)); ?></td>
                        </tr>
                        <tr>
                            <td><code>per_page</code></td>
                            <td>Number of observations to display</td>
                            <td>50</td>
                        </tr>
                    </tbody>
                </table>

                <h3>REST API Endpoint</h3>
                <p>Access observations programmatically:</p>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; font-family: monospace;"><?php echo esc_url(home_url('/wp-json/inat/v1/observations')); ?></pre>

                <p style="margin-top: 20px; color: #666; font-size: 0.95em;">
                    <strong>Tip:</strong> After saving settings above, click "Refresh Now" to fetch observations from iNaturalist.
                    The plugin will automatically refresh daily, but you can trigger manual refreshes anytime.
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#inat_obs_refresh_now').on('click', function() {
                var button = $(this);
                var message = $('#inat_obs_refresh_message');

                button.prop('disabled', true).text('Refreshing...');
                message.hide().removeClass('notice-success notice-error');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'inat_obs_manual_refresh',
                        nonce: '<?php echo wp_create_nonce('inat_obs_manual_refresh'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            message.addClass('notice notice-success')
                                .html('<p>' + response.data.message + '</p>')
                                .show();
                            // Reload page to show updated status
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            message.addClass('notice notice-error')
                                .html('<p>' + response.data.message + '</p>')
                                .show();
                            button.prop('disabled', false).text('Refresh Now');
                        }
                    },
                    error: function() {
                        message.addClass('notice notice-error')
                            .html('<p>An error occurred while refreshing.</p>')
                            .show();
                        button.prop('disabled', false).text('Refresh Now');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // AJAX handler for manual refresh
    add_action('wp_ajax_inat_obs_manual_refresh', 'inat_obs_ajax_manual_refresh');

    function inat_obs_ajax_manual_refresh() {
        // Verify nonce
        if (!check_ajax_referer('inat_obs_manual_refresh', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
            return;
        }

        // Trigger refresh job
        do_action('inat_obs_refresh');

        // Get updated stats
        $last_count = get_option('inat_obs_last_refresh_count', 0);

        wp_send_json_success([
            'message' => 'Refresh completed successfully. Fetched ' . $last_count . ' observations.'
        ]);
    }
