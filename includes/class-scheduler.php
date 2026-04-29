<?php
/**
 * WP-Cron scheduler for SimpleBackup.
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

    public function schedule_events($interval = null) {
        if (!wp_next_scheduled($this->hook)) {
            if (empty($interval)) {
                $settings = SimpleBackup::instance()->get_settings();
                $interval = $settings['schedule_interval'];
            }
            wp_schedule_event(time(), $interval, $this->hook);
        }
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
