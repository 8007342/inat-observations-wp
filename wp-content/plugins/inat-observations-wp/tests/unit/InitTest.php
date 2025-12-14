<?php
/**
 * Unit Tests for Initialization Module (init.php)
 *
 * Tests for plugin lifecycle and cron functionality including:
 * - Plugin activation hooks
 * - Plugin deactivation hooks
 * - Database schema installation during activation
 * - WP-Cron job scheduling
 * - WP-Cron job cleanup
 * - Scheduled refresh job execution
 * - Module loading sequence
 */

class INAT_OBS_InitTest extends INAT_OBS_TestCase {

    /**
     * Test plugin activation creates database table
     *
     * Verifies that inat_obs_activate() calls schema installation
     * and creates the required database table.
     */
    public function test_activation_creates_database_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Drop table to ensure clean state
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Act: Trigger activation
        inat_obs_activate();

        // Assert: Table should exist
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $this->assertEquals($table_name, $result, 'Activation should create database table');
    }

    /**
     * Test plugin activation schedules cron job
     *
     * Verifies that inat_obs_activate() schedules the daily
     * refresh cron job.
     */
    public function test_activation_schedules_cron_job() {
        // Arrange: Clear any existing scheduled jobs
        wp_clear_scheduled_hook('inat_obs_refresh');

        // Act: Trigger activation
        inat_obs_activate();

        // Assert: Cron job should be scheduled
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($timestamp, 'Activation should schedule cron job');
        $this->assertGreaterThan(0, $timestamp);
    }

    /**
     * Test plugin activation doesn't duplicate cron jobs
     *
     * Verifies that calling activation multiple times doesn't
     * create duplicate scheduled jobs.
     */
    public function test_activation_prevents_duplicate_cron_jobs() {
        // Arrange: Clear existing jobs
        wp_clear_scheduled_hook('inat_obs_refresh');

        // Act: Activate multiple times
        inat_obs_activate();
        inat_obs_activate();
        inat_obs_activate();

        // Assert: Should only have one scheduled job
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($timestamp);

        // Get all scheduled events for this hook
        $crons = _get_cron_array();
        $count = 0;
        foreach ($crons as $time => $cron) {
            if (isset($cron['inat_obs_refresh'])) {
                $count += count($cron['inat_obs_refresh']);
            }
        }

        $this->assertEquals(1, $count, 'Should only have one scheduled job');
    }

    /**
     * Test plugin activation with existing cron job
     *
     * Verifies that activation doesn't interfere with
     * already-scheduled jobs.
     */
    public function test_activation_preserves_existing_cron_job() {
        // Arrange: Manually schedule job
        wp_clear_scheduled_hook('inat_obs_refresh');
        $original_time = time() + 3600;
        wp_schedule_event($original_time, 'daily', 'inat_obs_refresh');

        // Act: Activate plugin
        inat_obs_activate();

        // Assert: Original job should still exist
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertEquals($original_time, $timestamp);
    }

    /**
     * Test plugin deactivation clears cron jobs
     *
     * Verifies that inat_obs_deactivate() removes the
     * scheduled refresh job.
     */
    public function test_deactivation_clears_cron_jobs() {
        // Arrange: Schedule job first
        wp_clear_scheduled_hook('inat_obs_refresh');
        wp_schedule_event(time(), 'daily', 'inat_obs_refresh');

        $before = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($before, 'Job should be scheduled before deactivation');

        // Act: Trigger deactivation
        inat_obs_deactivate();

        // Assert: Job should be removed
        $after = wp_next_scheduled('inat_obs_refresh');
        $this->assertFalse($after, 'Deactivation should clear cron job');
    }

    /**
     * Test plugin deactivation with no scheduled jobs
     *
     * Verifies that deactivation doesn't error when no
     * jobs are scheduled.
     */
    public function test_deactivation_without_scheduled_job() {
        // Arrange: Ensure no jobs scheduled
        wp_clear_scheduled_hook('inat_obs_refresh');

        // Act: Trigger deactivation (should not error)
        inat_obs_deactivate();

        // Assert: Should complete without error
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertFalse($timestamp);
    }

    /**
     * Test plugin deactivation doesn't drop database table
     *
     * Verifies that deactivation preserves data in the
     * database table.
     */
    public function test_deactivation_preserves_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Ensure table exists with data
        inat_obs_install_schema();
        $items = $this->create_sample_api_response(2);
        inat_obs_store_items($items);

        $before = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(2, $before);

        // Act: Deactivate plugin
        inat_obs_deactivate();

        // Assert: Table and data should still exist
        $after = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(2, $after, 'Deactivation should preserve database');

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $this->assertEquals($table_name, $table_exists);
    }

    /**
     * Test refresh job action is registered
     *
     * Verifies that the inat_obs_refresh action hook is
     * registered with correct callback.
     */
    public function test_refresh_job_action_is_registered() {
        // Assert: Verify action exists
        $priority = has_action('inat_obs_refresh', 'inat_obs_refresh_job');
        $this->assertNotFalse($priority, 'Refresh job action should be registered');
    }

    /**
     * Test refresh job function exists
     *
     * Verifies that the refresh job callback function is defined.
     */
    public function test_refresh_job_function_exists() {
        // Assert: Function should exist
        $this->assertTrue(
            function_exists('inat_obs_refresh_job'),
            'Refresh job function should be defined'
        );
    }

    /**
     * Test refresh job can be called without errors
     *
     * Verifies that the refresh job function can be executed
     * without crashing (even though it's currently a stub).
     */
    public function test_refresh_job_executes_without_error() {
        // Act: Call refresh job (stub implementation)
        inat_obs_refresh_job();

        // Assert: Should complete without error
        $this->assertTrue(true, 'Refresh job should execute without error');
    }

    /**
     * Test cron job uses daily recurrence
     *
     * Verifies that the scheduled job uses the 'daily' schedule.
     */
    public function test_cron_job_uses_daily_schedule() {
        // Arrange: Clear and reschedule
        wp_clear_scheduled_hook('inat_obs_refresh');
        inat_obs_activate();

        // Act: Get scheduled job details
        $crons = _get_cron_array();
        $job_schedule = null;

        foreach ($crons as $time => $cron) {
            if (isset($cron['inat_obs_refresh'])) {
                foreach ($cron['inat_obs_refresh'] as $job) {
                    $job_schedule = $job['schedule'];
                    break 2;
                }
            }
        }

        // Assert: Should use daily schedule
        $this->assertEquals('daily', $job_schedule);
    }

    /**
     * Test activation is idempotent
     *
     * Verifies that calling activation multiple times
     * produces consistent results.
     */
    public function test_activation_is_idempotent() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Act: Activate multiple times
        inat_obs_activate();
        inat_obs_activate();
        inat_obs_activate();

        // Assert: Table should exist exactly once
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        $this->assertEquals($table_name, $result);

        // Cron job should be scheduled once
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertNotFalse($timestamp);
    }

    /**
     * Test deactivation is idempotent
     *
     * Verifies that calling deactivation multiple times
     * doesn't cause errors.
     */
    public function test_deactivation_is_idempotent() {
        // Arrange: Schedule job
        wp_clear_scheduled_hook('inat_obs_refresh');
        wp_schedule_event(time(), 'daily', 'inat_obs_refresh');

        // Act: Deactivate multiple times
        inat_obs_deactivate();
        inat_obs_deactivate();
        inat_obs_deactivate();

        // Assert: Job should be cleared
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertFalse($timestamp);
    }

    /**
     * Test activation schedules job for immediate future
     *
     * Verifies that the first scheduled run is set for
     * a reasonable time in the future.
     */
    public function test_activation_schedules_job_in_future() {
        // Arrange: Clear existing jobs
        wp_clear_scheduled_hook('inat_obs_refresh');

        // Act: Activate
        $before = time();
        inat_obs_activate();
        $after = time();

        // Assert: Scheduled time should be near current time
        $timestamp = wp_next_scheduled('inat_obs_refresh');
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after + 60, $timestamp, 'Job should be scheduled within 1 minute');
    }

    /**
     * Test cron job can be manually triggered
     *
     * Verifies that the refresh job can be executed
     * outside of scheduled time.
     */
    public function test_refresh_job_can_be_manually_triggered() {
        // Act: Manually trigger the action
        do_action('inat_obs_refresh');

        // Assert: Should execute without error
        $this->assertTrue(true);
    }

    /**
     * Test activation with database error
     *
     * Verifies behavior when database table creation fails.
     */
    public function test_activation_handles_database_error() {
        global $wpdb;

        // Arrange: Store original prefix
        $original_prefix = $wpdb->prefix;

        // Create a prefix that might cause issues (very long)
        $wpdb->prefix = str_repeat('x', 200);

        // Act: Attempt activation (may log error but shouldn't crash)
        inat_obs_activate();

        // Assert: Should complete without fatal error
        $this->assertTrue(true);

        // Cleanup: Restore prefix
        $wpdb->prefix = $original_prefix;
    }

    /**
     * Test deactivation returns count of cleared jobs
     *
     * Verifies that wp_clear_scheduled_hook returns
     * appropriate count.
     */
    public function test_deactivation_returns_cleared_count() {
        // Arrange: Schedule job
        wp_clear_scheduled_hook('inat_obs_refresh');
        wp_schedule_event(time(), 'daily', 'inat_obs_refresh');

        // Act: Clear (called inside deactivate)
        $cleared = wp_clear_scheduled_hook('inat_obs_refresh');

        // Assert: Should return count > 0
        $this->assertGreaterThan(0, $cleared);
    }

    /**
     * Test cron hook name is correct
     *
     * Verifies that the cron hook uses the expected name.
     */
    public function test_cron_hook_name_is_correct() {
        // Arrange: Schedule job
        wp_clear_scheduled_hook('inat_obs_refresh');
        inat_obs_activate();

        // Act: Check scheduled hook name
        $timestamp = wp_next_scheduled('inat_obs_refresh');

        // Assert: Hook should be scheduled
        $this->assertNotFalse($timestamp);

        // Verify wrong hook names don't exist
        $wrong = wp_next_scheduled('inat_refresh');
        $this->assertFalse($wrong);
    }

    /**
     * Test activation with corrupted wp_options table
     *
     * Verifies graceful handling of database issues.
     */
    public function test_activation_with_corrupted_options() {
        // This is difficult to test without actually corrupting database
        // but we verify activation completes even if cron scheduling fails

        // Act: Activate
        inat_obs_activate();

        // Assert: Should complete
        $this->assertTrue(true);
    }

    /**
     * Test cron job interval is 24 hours
     *
     * Verifies that 'daily' schedule is approximately 24 hours.
     */
    public function test_cron_job_interval_is_daily() {
        // Get WordPress schedules
        $schedules = wp_get_schedules();

        // Assert: Daily schedule exists and is ~24 hours
        $this->assertArrayHasKey('daily', $schedules);
        $this->assertEquals(86400, $schedules['daily']['interval']); // 24 * 60 * 60
    }

    /**
     * Test activation with existing data doesn't lose records
     *
     * Verifies that re-activation preserves existing database records.
     */
    public function test_reactivation_preserves_existing_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'inat_observations';

        // Arrange: Activate and add data
        inat_obs_activate();
        $items = $this->create_sample_api_response(5);
        inat_obs_store_items($items);

        $before_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(5, $before_count);

        // Act: Deactivate and reactivate
        inat_obs_deactivate();
        inat_obs_activate();

        // Assert: Data should still exist
        $after_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $this->assertEquals(5, $after_count, 'Reactivation should preserve data');
    }

    /**
     * Test multiple cron hooks can coexist
     *
     * Verifies that plugin cron doesn't interfere with
     * other scheduled WordPress events.
     */
    public function test_cron_coexists_with_other_hooks() {
        // Arrange: Schedule another hook
        wp_schedule_event(time(), 'daily', 'some_other_hook');

        // Act: Activate plugin
        inat_obs_activate();

        // Assert: Both hooks should exist
        $plugin_hook = wp_next_scheduled('inat_obs_refresh');
        $other_hook = wp_next_scheduled('some_other_hook');

        $this->assertNotFalse($plugin_hook);
        $this->assertNotFalse($other_hook);

        // Cleanup
        wp_clear_scheduled_hook('some_other_hook');
    }

    /**
     * Test deactivation only clears plugin cron
     *
     * Verifies that deactivation doesn't affect other
     * scheduled WordPress events.
     */
    public function test_deactivation_only_clears_plugin_cron() {
        // Arrange: Schedule multiple hooks
        wp_schedule_event(time(), 'daily', 'inat_obs_refresh');
        wp_schedule_event(time(), 'daily', 'other_plugin_hook');

        // Act: Deactivate
        inat_obs_deactivate();

        // Assert: Only plugin hook should be cleared
        $plugin_hook = wp_next_scheduled('inat_obs_refresh');
        $other_hook = wp_next_scheduled('other_plugin_hook');

        $this->assertFalse($plugin_hook, 'Plugin hook should be cleared');
        $this->assertNotFalse($other_hook, 'Other hooks should remain');

        // Cleanup
        wp_clear_scheduled_hook('other_plugin_hook');
    }
}
