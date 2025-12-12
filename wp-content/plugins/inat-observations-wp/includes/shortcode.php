<?php
    // Shortcode and display logic.
    if (!defined('ABSPATH')) exit;

    add_shortcode('inat_observations', 'inat_obs_shortcode_render');

    function inat_obs_shortcode_render($atts = []) {
        error_log('[iNat Observations] Rendering shortcode with attributes: ' . json_encode($atts));

        // TODO: accept attributes like project, per_page, filters
        $atts = shortcode_atts([
            'project' => getenv('INAT_PROJECT_SLUG') ?: 'project_slug_here',
            'per_page' => 50,
        ], $atts, 'inat_observations');

        error_log('[iNat Observations] Shortcode final attributes: project=' . $atts['project'] . ', per_page=' . $atts['per_page']);

        // Enqueue assets
        wp_enqueue_script('inat-observations-main', plugin_dir_url(__FILE__) . '/../assets/js/main.js', ['jquery'], INAT_OBS_VERSION, true);
        wp_enqueue_style('inat-observations-css', plugin_dir_url(__FILE__) . '/../assets/css/main.css', [], INAT_OBS_VERSION);
        error_log('[iNat Observations] Enqueued shortcode assets');

        // Minimal render. JS will enhance filters.
        ob_start();
        echo '<div id="inat-observations-root">';
        echo '<div class="inat-filters">';
        echo '<select id="inat-filter-field"><option value="">Loading filters...</option></select>';
        echo '</div>';
        echo '<div id="inat-list">Loading observations...</div>';
        echo '</div>';
        error_log('[iNat Observations] Shortcode rendered successfully');
        return ob_get_clean();
    }

    // AJAX endpoint for client-side fetch
    add_action('wp_ajax_inat_obs_fetch', 'inat_obs_ajax_fetch');
    add_action('wp_ajax_nopriv_inat_obs_fetch', 'inat_obs_ajax_fetch');

    function inat_obs_ajax_fetch() {
        error_log('[iNat Observations] AJAX fetch request received');

        // TODO: rate-limit this endpoint. Return cached DB rows or transient.
        $data = inat_obs_fetch_observations(['per_page' => 50]);

        if (is_wp_error($data)) {
            error_log('[iNat Observations] AJAX fetch failed: ' . $data->get_error_message());
            wp_send_json_error(['message' => $data->get_error_message()]);
        } else {
            error_log('[iNat Observations] AJAX fetch successful, sending response');
            wp_send_json_success($data);
        }
    }
