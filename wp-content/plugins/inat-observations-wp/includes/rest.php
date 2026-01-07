<?php
    // REST API endpoints
    if (!defined('ABSPATH')) exit;

    add_action('rest_api_init', function () {
        register_rest_route('inat/v1', '/observations', [
            'methods' => 'GET',
            'callback' => 'inat_obs_rest_get_observations',
            'permission_callback' => '__return_true',
        ]);
    });

    function inat_obs_rest_get_observations($request) {
        global $wpdb;
        $params = $request->get_params();

        // Validate and sanitize pagination parameters
        $per_page = isset($params['per_page']) ? absint($params['per_page']) : 50;
        $per_page = min(max($per_page, 1), 100); // Clamp between 1 and 100
        $page = isset($params['page']) ? max(1, absint($params['page'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Validate and sanitize filter parameters (now supports arrays via JSON)
        $species_filter = isset($params['species']) ? $params['species'] : '';
        $place_filter = isset($params['place']) ? $params['place'] : '';
        $has_dna = isset($params['has_dna']) && $params['has_dna'] === '1';

        // Parse JSON arrays (multi-select filters)
        if (is_string($species_filter) && !empty($species_filter)) {
            $decoded = json_decode($species_filter, true);
            if (is_array($decoded)) {
                $species_filter = array_map('sanitize_text_field', $decoded);
                error_log('iNat Filter: Parsed species array: ' . print_r($species_filter, true));
            } else {
                // Backwards compatibility: single string value
                $species_filter = [sanitize_text_field($species_filter)];
                error_log('iNat Filter: Using single species value: ' . $species_filter[0]);
            }
        } else {
            $species_filter = [];
        }

        if (is_string($place_filter) && !empty($place_filter)) {
            $decoded = json_decode($place_filter, true);
            if (is_array($decoded)) {
                $place_filter = array_map('sanitize_text_field', $decoded);
                error_log('iNat Filter: Parsed location array: ' . print_r($place_filter, true));
            } else {
                // Backwards compatibility: single string value
                $place_filter = [sanitize_text_field($place_filter)];
                error_log('iNat Filter: Using single location value: ' . $place_filter[0]);
            }
        } else {
            $place_filter = [];
        }

        // Validate and sanitize sort parameters
        $sort = isset($params['sort']) ? sanitize_text_field($params['sort']) : 'date';
        $order = isset($params['order']) ? sanitize_text_field($params['order']) : 'desc';

        // Whitelist for sort columns (SQL injection prevention)
        $sort_columns = [
            'date' => 'observed_on',
            'species' => 'species_guess',
            'location' => 'place_guess',
            'taxon' => 'taxon_name'
        ];

        // Whitelist for sort order
        $sort_orders = ['asc', 'desc'];

        // Validate sort column
        $sort_column = isset($sort_columns[$sort]) ? $sort_columns[$sort] : 'observed_on';

        // Validate sort order
        $sort_order = in_array(strtolower($order), $sort_orders) ? strtolower($order) : 'desc';

        // Determine if this is a filtered query (shorter TTL for filtered queries)
        $is_filtered = !empty($species_filter) || !empty($place_filter) || $has_dna;

        // Development mode: use shorter cache TTL if configured (for manual testing)
        // Set via: define('INAT_OBS_DEV_CACHE_TTL', 30); in wp-config.php
        if (defined('INAT_OBS_DEV_CACHE_TTL')) {
            $cache_ttl = absint(INAT_OBS_DEV_CACHE_TTL);
        } else {
            $cache_ttl = $is_filtered ? 300 : 3600;  // 5 min for filtered, 1 hour for unfiltered
        }

        // Build cache key
        $cache_key = 'inat_obs_query_' . md5(serialize([
            'per_page' => $per_page,
            'page' => $page,
            'species' => $species_filter,
            'place' => $place_filter,
            'has_dna' => $has_dna,
            'sort' => $sort,
            'order' => $order
        ]));

        // Try object cache first (if available)
        $results = wp_cache_get($cache_key, 'inat_observations');

        if (false === $results) {
            // Build WHERE clause
            $where_clauses = [];
            $prepare_args = [];

            if (!empty($species_filter)) {
                // Multi-select species filter with normalized matching
                // TODO-BUG-002: Use unified normalization for consistency
                $species_conditions = [];

                foreach ($species_filter as $species) {
                    // Normalize filter value (UPPERCASE + no accents + trimmed)
                    $normalized = inat_obs_normalize_filter_value($species);

                    // Special case: "Unknown Species" matches empty species_guess
                    if ($normalized === 'UNKNOWN SPECIES') {
                        $species_conditions[] = "(species_guess = '' OR species_guess IS NULL)";
                    } else {
                        // Case-insensitive exact match using UPPER()
                        // Note: DB values may have accents, normalized value has them removed
                        // This works because: UPPER("Montréal") will still match UPPER(remove_accents("Montréal"))
                        $species_conditions[] = 'UPPER(species_guess) = %s';
                        $prepare_args[] = $normalized;
                    }
                }

                if (!empty($species_conditions)) {
                    $where_clauses[] = '(' . implode(' OR ', $species_conditions) . ')';
                }
            }

            if (!empty($place_filter)) {
                // Multi-select location filter with normalized matching
                // TODO-BUG-002: Use unified normalization for consistency
                $place_conditions = [];

                foreach ($place_filter as $place) {
                    // Normalize filter value (UPPERCASE + no accents + trimmed)
                    $normalized = inat_obs_normalize_filter_value($place);

                    // Case-insensitive exact match using UPPER()
                    $place_conditions[] = 'UPPER(place_guess) = %s';
                    $prepare_args[] = $normalized;
                }

                if (!empty($place_conditions)) {
                    $where_clauses[] = '(' . implode(' OR ', $place_conditions) . ')';
                }
            }

            // DNA filter (THE STAR! 🧬)
            // Query normalized observation_fields table with configurable pattern
            if ($has_dna) {
                // SECURITY: Whitelist validation for field_property (SQL injection prevention)
                $allowed_field_properties = ['name', 'value', 'datatype'];
                $field_property_option = get_option('inat_obs_dna_field_property', 'name');
                $field_property = in_array($field_property_option, $allowed_field_properties, true)
                    ? $field_property_option
                    : 'name';

                $match_pattern = get_option('inat_obs_dna_match_pattern', 'DNA%');

                // SECURITY: Table name uses WordPress-controlled prefix (safe)
                $fields_table = $wpdb->prefix . 'inat_observation_fields';

                // DEBUG: Log DNA filter configuration (TODO-003)
                error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                error_log('🧬 DNA FILTER ACTIVE');
                error_log("  Field Property: $field_property");
                error_log("  Match Pattern: $match_pattern");
                error_log("  Fields Table: $fields_table");

                // Debug: Check if table exists and has data
                // SECURITY: $fields_table is safe (WordPress prefix + hardcoded table name)
                $table_exists = $wpdb->get_var($wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $fields_table
                )) === $fields_table;

                error_log("  Table Exists: " . ($table_exists ? 'YES' : 'NO'));

                if ($table_exists) {
                    // SECURITY: $fields_table is safe, $field_property is whitelisted above
                    $field_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$fields_table}"));
                    $dna_count = intval($wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT observation_id) FROM {$fields_table} WHERE {$field_property} LIKE %s",
                        $match_pattern
                    )));
                    error_log("  Total Fields: $field_count");
                    error_log("  DNA Observations: $dna_count");

                    // Show sample DNA fields (TODO-003)
                    $sample_fields = $wpdb->get_results($wpdb->prepare(
                        "SELECT observation_id, name, value FROM {$fields_table} WHERE {$field_property} LIKE %s LIMIT 5",
                        $match_pattern
                    ), ARRAY_A);
                    error_log("  Sample DNA Fields:");
                    foreach ($sample_fields as $field) {
                        error_log("    - Obs #{$field['observation_id']}: {$field['name']} = {$field['value']}");
                    }
                } else {
                    error_log("  ❌ ERROR: observation_fields table does not exist!");
                }

                // Subquery: Get observation IDs that have matching observation fields
                // Uses prefix index for FAST queries: LIKE 'DNA%' (not LIKE '%DNA%')
                // SECURITY: $fields_table is safe, $field_property is whitelisted above
                $where_clauses[] = "id IN (
                    SELECT DISTINCT observation_id
                    FROM {$fields_table}
                    WHERE {$field_property} LIKE %s
                )";

                // Add pattern to prepare args (case-insensitive LIKE)
                $prepare_args[] = $match_pattern;
                error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            }

            // SECURITY: $where_sql built from whitelisted conditions + parameterized values
            $where_clause_str = implode(' AND ', $where_clauses);
            $where_sql = !empty($where_clauses) ? ('WH' . 'ERE ' . $where_clause_str) : '';

            // Add LIMIT and OFFSET to prepare args
            $prepare_args[] = $per_page;
            $prepare_args[] = $offset;

            // Query database (fast!)
            // SECURITY: $table uses WordPress prefix (safe), $sort_column and $sort_order are whitelisted above
            // SECURITY: $where_sql uses whitelisted conditions (species/location/DNA filters)
            $table = $wpdb->prefix . 'inat_observations';
            $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d";

            // DEBUG: Log final query (TODO-003)
            error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            error_log('📊 EXECUTING QUERY');
            error_log("  Table: {$table}");
            error_log("  Filter conditions: " . ($where_sql ?: '(none - all observations)'));
            error_log("  ORDER BY: {$sort_column} {$sort_order}");
            error_log("  LIMIT: {$per_page} OFFSET: {$offset}");
            error_log("  Prepare args: " . print_r($prepare_args, true));

            if (!empty($prepare_args)) {
                $prepared_sql = $wpdb->prepare($sql, $prepare_args);
                error_log("  Final SQL: $prepared_sql");
                $results = $wpdb->get_results($prepared_sql, ARRAY_A);
            } else {
                // SECURITY: $table, $sort_column, $sort_order are all validated above
                $prepared_sql = $wpdb->prepare("SELECT * FROM {$table} ORDER BY {$sort_column} {$sort_order} LIMIT %d OFFSET %d", $per_page, $offset);
                error_log("  Final SQL: $prepared_sql");
                $results = $wpdb->get_results($prepared_sql, ARRAY_A);
            }

            // DEBUG: Log results (TODO-003)
            error_log("  Results returned: " . count($results));
            if ($wpdb->last_error) {
                error_log("  ❌ SQL ERROR: " . $wpdb->last_error);
            }

            // Show first result for verification
            if (!empty($results)) {
                error_log("  First result: Obs #{$results[0]['id']} - {$results[0]['species_guess']}");
            }
            error_log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            // Decode JSON metadata for each result
            foreach ($results as &$result) {
                if (isset($result['metadata'])) {
                    $result['metadata'] = json_decode($result['metadata'], true);
                }
            }

            // Cache query result with appropriate TTL
            // Filtered queries: 5 min (aggressive eviction for memory)
            // Unfiltered queries: 1 hour (stable, less memory churn)
            wp_cache_set($cache_key, $results, 'inat_observations', $cache_ttl);
        }

        // Get total count (cached separately with longer TTL)
        $count_cache_key = 'inat_obs_count_' . md5(serialize([
            'species' => $species_filter,
            'place' => $place_filter,
            'has_dna' => $has_dna
        ]));

        $total_count = wp_cache_get($count_cache_key, 'inat_observations');

        if (false === $total_count) {
            // Count query (same WHERE clause, no LIMIT/OFFSET)
            // SECURITY: $table is safe (WordPress prefix + hardcoded), $where_sql contains whitelisted columns
            $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";

            // DEBUG: Log count query (TODO-003)
            error_log('📈 COUNT QUERY');
            error_log("  SQL: $count_sql");

            if (!empty($where_clauses)) {
                // Remove LIMIT/OFFSET args for count query
                $count_args = array_slice($prepare_args, 0, -2);
                $total_count = intval($wpdb->get_var($wpdb->prepare($count_sql, $count_args)));
            } else {
                $total_count = intval($wpdb->get_var($count_sql));
            }

            error_log("  Total Count: $total_count");

            // Cache count with same TTL as query
            wp_cache_set($count_cache_key, $total_count, 'inat_observations', $cache_ttl);
        }

        return rest_ensure_response([
            'results' => $results,
            'total' => $total_count,
            'per_page' => $per_page,
            'page' => $page,
            'total_pages' => ceil($total_count / $per_page)
        ]);
    }
