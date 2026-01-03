<?php
/**
 * Plugin Activation/Deactivation Integration Tests
 *
 * @package inat-observations-wp
 */

class Test_Inat_Activation extends WP_UnitTestCase {

    /**
     * Clean up before each test
     */
    public function setUp(): void {
        parent::setUp();

        // Clear any existing cron jobs
        wp_clear_scheduled_hook('inat_obs_refresh');

        // Drop table if exists
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        wp_clear_scheduled_hook('inat_obs_refresh');
        parent::tearDown();
    }

    /**
     * Test plugin activation creates database table
     */
    public function test_activation_creates_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Verify table doesn't exist initially
        $table_exists_before = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertNull($table_exists_before, 'Table should not exist before activation');

        // Activate plugin
        inat_obs_activate();

        // Verify table exists after activation
        $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertEquals($table, $table_exists_after, 'Table should exist after activation');
    }

    /**
     * Test plugin activation schedules cron job
     */
    public function test_activation_schedules_cron() {
        // Verify no cron job exists initially
        $timestamp_before = wp_next_scheduled('inat_obs_refresh');
        $this->assertFalse($timestamp_before, 'Cron job should not be scheduled before activation');

        // Activate plugin
        inat_obs_activate();

        // Verify cron job is scheduled after activation
        $timestamp_after = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($timestamp_after, 'Cron job should be scheduled after activation');
        $this->assertIsInt($timestamp_after, 'Scheduled timestamp should be an integer');
    }

    /**
     * Test plugin activation doesn't duplicate cron jobs
     */
    public function test_activation_no_duplicate_cron() {
        // Activate plugin twice
        inat_obs_activate();
        $first_timestamp = wp_next_scheduled('inat_obs_refresh');

        inat_obs_activate();
        $second_timestamp = wp_next_scheduled('inat_obs_refresh');

        // Should not create duplicate cron jobs
        $this->assertEquals($first_timestamp, $second_timestamp, 'Should not create duplicate cron jobs');
    }

    /**
     * Test plugin deactivation clears cron job
     */
    public function test_deactivation_clears_cron() {
        // Activate plugin first
        inat_obs_activate();
        $timestamp_after_activation = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($timestamp_after_activation, 'Cron job should be scheduled after activation');

        // Deactivate plugin
        inat_obs_deactivate();

        // Verify cron job is cleared
        $timestamp_after_deactivation = wp_next_scheduled('inat_obs_refresh');
        $this->assertFalse($timestamp_after_deactivation, 'Cron job should be cleared after deactivation');
    }

    /**
     * Test plugin deactivation doesn't delete database table
     */
    public function test_deactivation_preserves_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // Activate plugin and store some data
        inat_obs_activate();

        $items = [
            'results' => [
                [
                    'id' => 5001,
                    'uuid' => 'uuid-5001',
                    'species_guess' => 'Test Species',
                    'place_guess' => 'Test Place',
                ],
            ],
        ];
        inat_obs_store_items($items);

        // Verify data exists
        $count_before = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE id = 5001");
        $this->assertEquals(1, $count_before, 'Data should exist before deactivation');

        // Deactivate plugin
        inat_obs_deactivate();

        // Verify table and data still exist
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertEquals($table, $table_exists, 'Table should still exist after deactivation');

        $count_after = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE id = 5001");
        $this->assertEquals(1, $count_after, 'Data should be preserved after deactivation');
    }

    /**
     * Test complete activation/deactivation/reactivation cycle
     */
    public function test_activation_cycle() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        // First activation
        inat_obs_activate();
        $this->assertNotNull($wpdb->get_var("SHOW TABLES LIKE '$table'"), 'Table should exist after first activation');
        $this->assertNotFalse(wp_next_scheduled('inat_obs_refresh'), 'Cron should be scheduled after first activation');

        // Deactivation
        inat_obs_deactivate();
        $this->assertNotNull($wpdb->get_var("SHOW TABLES LIKE '$table'"), 'Table should still exist after deactivation');
        $this->assertFalse(wp_next_scheduled('inat_obs_refresh'), 'Cron should be cleared after deactivation');

        // Reactivation
        inat_obs_activate();
        $this->assertNotNull($wpdb->get_var("SHOW TABLES LIKE '$table'"), 'Table should exist after reactivation');
        $this->assertNotFalse(wp_next_scheduled('inat_obs_refresh'), 'Cron should be scheduled after reactivation');
    }

    /**
     * Test cron job has correct recurrence
     */
    public function test_cron_recurrence() {
        inat_obs_activate();

        $crons = _get_cron_array();
        $found_event = false;
        $recurrence = null;

        foreach ($crons as $timestamp => $cron) {
            if (isset($cron['inat_obs_refresh'])) {
                $found_event = true;
                foreach ($cron['inat_obs_refresh'] as $event) {
                    $recurrence = $event['schedule'];
                }
            }
        }

        $this->assertTrue($found_event, 'Cron event should exist in cron array');
        $this->assertEquals('daily', $recurrence, 'Cron recurrence should be daily');
    }

    /**
     * Test database table has correct primary key
     */
    public function test_table_primary_key() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        inat_obs_activate();

        // Check primary key
        $keys = $wpdb->get_results("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");

        $this->assertNotEmpty($keys, 'Table should have a primary key');
        $this->assertEquals('id', $keys[0]->Column_name, 'Primary key should be on id column');
    }

    /**
     * Test database table has index on observed_on
     */
    public function test_table_indexes() {
        global $wpdb;
        $table = $wpdb->prefix . 'inat_observations';

        inat_obs_activate();

        // Check for index on observed_on
        $indexes = $wpdb->get_results("SHOW KEYS FROM $table WHERE Column_name = 'observed_on'");

        $this->assertNotEmpty($indexes, 'Table should have index on observed_on column');
    }
}
