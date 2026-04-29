<?php
/**
 * WP-Cron scheduler for SimpleBackup.
 * Supports both interval-based and specific time-of-day scheduling.
 */
class SimpleBackup_Scheduler {

    private static $instance = null;
    private $hook = 'simplebackup_run_scheduled';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action($this->hook, array($this, 'run_backup'));
    }

    /**
     * Calculate the next occurrence timestamp based on interval and time preference.
     */
    public function get_next_scheduled_time($interval = null, $time_str = null) {
        if (empty($interval)) {
            $settings = SimpleBackup::instance()->get_settings();
            $interval = $settings['schedule_interval'];
            $time_str = $settings['schedule_time'];
        }

        $now = current_time('timestamp');
        
        // For hourly, just start from next hour boundary
        if ($interval === 'hourly') {
            return $now + 3600;
        }

        // For intervals with a preferred time, calculate next occurrence at that time
        if (!empty($time_str) && preg_match('/^(\d{1,2}):(\d{2})$/', $time_str, $matches)) {
            $hour = intval($matches[1]);
            $minute = intval($matches[2]);
            
            // Build today's target time
            $target = strtotime(gmdate('Y-m-d', $now) . sprintf(' %02d:%02d:00', $hour, $minute));
            
            // If today's time has passed, move to next occurrence based on interval
            if ($target <= $now) {
                switch ($interval) {
                    case 'twicedaily':
                        // If morning slot (e.g. 2am) passed, schedule evening (e.g. 2pm)
                        // But user sets ONE time; we'll run every 12 hours from that time
                        $target += 43200; // 12 hours
                        break;
                    case 'daily':
                        $target += 86400; // 24 hours
                        break;
                    case 'weekly':
                        $target += 604800; // 7 days
                        break;
                    default:
                        $target += 86400;
                }
            }
            return $target;
        }

        // Fallback: start now
        return $now;
    }

    public function schedule_events($interval = null, $time_str = null) {
        $this->clear_schedules();
        
        if (empty($interval)) {
            $settings = SimpleBackup::instance()->get_settings();
            $interval = $settings['schedule_interval'];
            $time_str = $settings['schedule_time'];
        }

        $next_run = $this->get_next_scheduled_time($interval, $time_str);
        wp_schedule_event($next_run, $interval, $this->hook);
    }

    public function clear_schedules() {
        $timestamp = wp_next_scheduled($this->hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $this->hook);
        }
    }

    public function run_backup() {
        $settings = SimpleBackup::instance()->get_settings();
        if (!$settings['schedule_enabled']) return;

        $type = $settings['incremental_enabled'] ? 'incremental' : 'full';
        SimpleBackup::instance()->run_backup($type);
    }
}
