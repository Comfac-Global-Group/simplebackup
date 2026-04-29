<?php
/**
 * Health Check Dashboard for SimpleBackup.
 * Runs diagnostics on page load and shows green/yellow/red status.
 */
class SimpleBackup_Health_Check {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Run all health checks and return results.
     */
    public function run_checks() {
        $checks = array();
        $overall = 'success';

        // 1. Backup Directory
        $dir = SimpleBackup::instance()->get_backup_dir();
        if (!file_exists($dir)) {
            $checks[] = array(
                'id'      => 'backup_dir_exists',
                'title'   => 'Backup Directory Exists',
                'status'  => 'warning',
                'message' => "Directory does not exist: {$dir}. It will be created automatically on first backup.",
            );
            $overall = $this->worsen($overall, 'warning');
        } elseif (!is_dir($dir)) {
            $checks[] = array(
                'id'      => 'backup_dir_exists',
                'title'   => 'Backup Directory Exists',
                'status'  => 'error',
                'message' => "Path exists but is not a directory: {$dir}",
            );
            $overall = $this->worsen($overall, 'error');
        } elseif (!is_writable($dir)) {
            $checks[] = array(
                'id'      => 'backup_dir_writable',
                'title'   => 'Backup Directory Writable',
                'status'  => 'error',
                'message' => "Directory is not writable: {$dir}. Check permissions.",
            );
            $overall = $this->worsen($overall, 'error');
        } else {
            $free = function_exists('disk_free_space') ? disk_free_space($dir) : false;
            $checks[] = array(
                'id'      => 'backup_dir_writable',
                'title'   => 'Backup Directory Writable',
                'status'  => 'success',
                'message' => "Directory is writable. " . ($free !== false ? 'Free space: ' . size_format($free) . '.' : ''),
            );
        }

        // 2. ZipArchive
        if (class_exists('ZipArchive')) {
            $checks[] = array(
                'id'      => 'ziparchive',
                'title'   => 'ZIP Extension',
                'status'  => 'success',
                'message' => 'ZipArchive extension is available.',
            );
        } else {
            $checks[] = array(
                'id'      => 'ziparchive',
                'title'   => 'ZIP Extension',
                'status'  => 'error',
                'message' => 'ZipArchive extension is missing. Backups cannot run.',
            );
            $overall = $this->worsen($overall, 'error');
        }

        // 3. Database Backup Method
        $mysqldump = shell_exec('which mysqldump 2>/dev/null');
        if (!empty($mysqldump)) {
            $checks[] = array(
                'id'      => 'mysqldump',
                'title'   => 'Database Export (mysqldump)',
                'status'  => 'success',
                'message' => 'mysqldump found: ' . trim($mysqldump),
            );
        } else {
            $checks[] = array(
                'id'      => 'mysqldump',
                'title'   => 'Database Export (mysqldump)',
                'status'  => 'warning',
                'message' => 'mysqldump not found. Will use PHP fallback (slower for large databases).',
            );
            $overall = $this->worsen($overall, 'warning');
        }

        // 4. Cron Status
        $next = wp_next_scheduled('simplebackup_run_scheduled');
        $settings = SimpleBackup::instance()->get_settings();
        if ($settings['schedule_enabled']) {
            if ($next) {
                $checks[] = array(
                    'id'      => 'cron',
                    'title'   => 'Scheduled Backups',
                    'status'  => 'success',
                    'message' => 'Next backup scheduled for: ' . gmdate('Y-m-d H:i:s', $next) . ' (server time).',
                );
            } else {
                $checks[] = array(
                    'id'      => 'cron',
                    'title'   => 'Scheduled Backups',
                    'status'  => 'warning',
                    'message' => 'Scheduling is enabled but no cron event is registered. Try toggling schedule off and on again.',
                );
                $overall = $this->worsen($overall, 'warning');
            }
        } else {
            $checks[] = array(
                'id'      => 'cron',
                'title'   => 'Scheduled Backups',
                'status'  => 'info',
                'message' => 'Scheduled backups are disabled. Only manual backups will run.',
            );
        }

        // 5. PHP Memory
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
        if ($memory_bytes >= 268435456) { // 256MB
            $checks[] = array(
                'id'      => 'php_memory',
                'title'   => 'PHP Memory Limit',
                'status'  => 'success',
                'message' => "Memory limit is {$memory_limit}. Sufficient for most backups.",
            );
        } elseif ($memory_bytes >= 134217728) { // 128MB
            $checks[] = array(
                'id'      => 'php_memory',
                'title'   => 'PHP Memory Limit',
                'status'  => 'warning',
                'message' => "Memory limit is {$memory_limit}. May be insufficient for large sites. Recommended: 256M+",
            );
            $overall = $this->worsen($overall, 'warning');
        } else {
            $checks[] = array(
                'id'      => 'php_memory',
                'title'   => 'PHP Memory Limit',
                'status'  => 'error',
                'message' => "Memory limit is {$memory_limit}. Too low for reliable backups. Recommended: 256M+",
            );
            $overall = $this->worsen($overall, 'error');
        }

        // 6. Max Execution Time
        $max_time = ini_get('max_execution_time');
        if ($max_time == 0 || $max_time >= 300) {
            $checks[] = array(
                'id'      => 'max_execution',
                'title'   => 'Max Execution Time',
                'status'  => 'success',
                'message' => "Max execution time is {$max_time} seconds. Sufficient for backups.",
            );
        } elseif ($max_time >= 60) {
            $checks[] = array(
                'id'      => 'max_execution',
                'title'   => 'Max Execution Time',
                'status'  => 'warning',
                'message' => "Max execution time is {$max_time} seconds. May timeout on large backups. Recommended: 300s+",
            );
            $overall = $this->worsen($overall, 'warning');
        } else {
            $checks[] = array(
                'id'      => 'max_execution',
                'title'   => 'Max Execution Time',
                'status'  => 'error',
                'message' => "Max execution time is {$max_time} seconds. Too low for backups. Recommended: 300s+",
            );
            $overall = $this->worsen($overall, 'error');
        }

        // 7. Recent Backups
        $backups = SimpleBackup::instance()->get_backups();
        if (empty($backups)) {
            $checks[] = array(
                'id'      => 'recent_backups',
                'title'   => 'Recent Backups',
                'status'  => 'info',
                'message' => 'No backups found yet. Run your first backup to verify everything works.',
            );
        } else {
            $latest = $backups[0];
            $age_hours = (current_time('timestamp') - $latest['timestamp']) / 3600;
            if ($age_hours < 48) {
                $checks[] = array(
                    'id'      => 'recent_backups',
                    'title'   => 'Recent Backups',
                    'status'  => 'success',
                    'message' => 'Latest backup: ' . gmdate('Y-m-d H:i:s', $latest['timestamp']) . ' (' . round($age_hours, 1) . ' hours ago).',
                );
            } else {
                $checks[] = array(
                    'id'      => 'recent_backups',
                    'title'   => 'Recent Backups',
                    'status'  => 'warning',
                    'message' => 'Latest backup is ' . round($age_hours / 24, 1) . ' days old. Consider running a new backup.',
                );
                $overall = $this->worsen($overall, 'warning');
            }
        }

        // 8. Encryption Check
        if ($settings['encryption_enabled']) {
            if (empty($settings['encryption_password'])) {
                $checks[] = array(
                    'id'      => 'encryption',
                    'title'   => 'Encryption',
                    'status'  => 'error',
                    'message' => 'Encryption is enabled but no password is set.',
                );
                $overall = $this->worsen($overall, 'error');
            } elseif (strlen($settings['encryption_password']) < 8) {
                $checks[] = array(
                    'id'      => 'encryption',
                    'title'   => 'Encryption',
                    'status'  => 'warning',
                    'message' => 'Encryption password is very short. Recommended: 12+ characters.',
                );
                $overall = $this->worsen($overall, 'warning');
            } else {
                $checks[] = array(
                    'id'      => 'encryption',
                    'title'   => 'Encryption',
                    'status'  => 'success',
                    'message' => 'Encryption is configured with a password.',
                );
            }
        } else {
            $checks[] = array(
                'id'      => 'encryption',
                'title'   => 'Encryption',
                'status'  => 'info',
                'message' => 'Encryption is disabled. Backups are stored unencrypted.',
            );
        }

        return array(
            'overall' => $overall,
            'checks'  => $checks,
        );
    }

    /**
     * Get overall status color.
     */
    private function worsen($current, $new) {
        $order = array('success' => 0, 'info' => 1, 'warning' => 2, 'error' => 3);
        return $order[$new] > $order[$current] ? $new : $current;
    }

    /**
     * Check if there's enough disk space for a backup.
     */
    public function check_disk_space($estimated_size = 0) {
        $dir = SimpleBackup::instance()->get_backup_dir();
        if (!file_exists($dir) || !function_exists('disk_free_space')) {
            return array('ok' => true, 'message' => 'Cannot check disk space.');
        }

        $free = disk_free_space($dir);
        $required = $estimated_size * 2; // 2x safety margin

        if ($free === false) {
            return array('ok' => true, 'message' => 'Cannot determine free space.');
        }

        if ($required > 0 && $free < $required) {
            return array(
                'ok'      => false,
                'message' => "Insufficient disk space. Free: " . size_format($free) . ", Estimated need: " . size_format($required) . ".",
            );
        }

        return array(
            'ok'      => true,
            'message' => "Disk space OK. Free: " . size_format($free) . ".",
        );
    }
}
