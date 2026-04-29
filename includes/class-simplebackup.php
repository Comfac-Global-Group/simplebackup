<?php
/**
 * Main SimpleBackup orchestrator.
 */
class SimpleBackup {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    public function init() {
        SimpleBackup_Scheduler::instance();
        SimpleBackup_Admin::instance();
    }

    public function activate() {
        $dir = $this->get_backup_dir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        $this->protect_dir($dir);
        SimpleBackup_Scheduler::instance()->schedule_events();
    }

    public function deactivate() {
        SimpleBackup_Scheduler::instance()->clear_schedules();
    }

    public function get_settings() {
        $defaults = array(
            'backup_dir'           => SIMPLEBACKUP_DEFAULT_DIR,
            'schedule_enabled'     => false,
            'schedule_interval'    => 'daily',
            'schedule_time'        => '02:00',
            'retention_count'      => 7,
            'backup_themes'        => true,
            'backup_plugins'       => true,
            'backup_uploads'       => true,
            'backup_core'          => false,
            'backup_database'      => true,
            'incremental_enabled'  => true,
            'encryption_enabled'   => false,
            'encryption_password'  => '',
            'custom_dirs'          => array(),
        );
        return wp_parse_args(get_option('simplebackup_settings', array()), $defaults);
    }

    public function update_settings($settings) {
        update_option('simplebackup_settings', $settings);
    }

    public function get_backup_dir() {
        $settings = $this->get_settings();
        $dir = $settings['backup_dir'];
        if (empty($dir)) {
            $dir = SIMPLEBACKUP_DEFAULT_DIR;
        }
        return rtrim($dir, '/');
    }

    public function protect_dir($dir) {
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Options -Indexes\ndeny from all\n");
        }
        $index = $dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden.");
        }
    }

    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['simplebackup_action']) && check_admin_referer('simplebackup_nonce')) {
            $action = sanitize_text_field($_POST['simplebackup_action']);

            if ($action === 'backup_now') {
                $type = isset($_POST['backup_type']) ? sanitize_text_field($_POST['backup_type']) : 'full';
                $result = $this->run_backup($type);
                if (is_wp_error($result)) {
                    add_settings_error('simplebackup', 'backup_error', $result->get_error_message(), 'error');
                } else {
                    add_settings_error('simplebackup', 'backup_success', 'Backup completed successfully.', 'success');
                }
            }

            if ($action === 'restore') {
                $backup_id = isset($_POST['backup_id']) ? sanitize_text_field($_POST['backup_id']) : '';
                $restore_type = isset($_POST['restore_type']) ? sanitize_text_field($_POST['restore_type']) : 'both';
                if ($backup_id) {
                    // Auto-create safety backup before restore
                    $safety = $this->create_safety_backup();
                    if (is_wp_error($safety)) {
                        add_settings_error('simplebackup', 'restore_warning', 'Warning: Could not create safety backup. Proceeding with restore anyway.', 'warning');
                    }

                    $result = SimpleBackup_Restorer::instance()->restore($backup_id, $restore_type);
                    if (is_wp_error($result)) {
                        add_settings_error('simplebackup', 'restore_error', $result->get_error_message(), 'error');
                    } else {
                        $msg = 'Restore completed successfully.';
                        if (!is_wp_error($safety)) {
                            $msg .= ' A safety backup was created. You can undo this restore if needed.';
                        }
                        add_settings_error('simplebackup', 'restore_success', $msg, 'success');
                    }
                }
            }

            if ($action === 'delete_backup') {
                $backup_id = isset($_POST['backup_id']) ? sanitize_text_field($_POST['backup_id']) : '';
                if ($backup_id) {
                    SimpleBackup_Storage_Local::instance()->delete_backup($backup_id);
                    add_settings_error('simplebackup', 'delete_success', 'Backup deleted.', 'success');
                }
            }

            if ($action === 'undo_restore') {
                $result = $this->undo_restore();
                if (is_wp_error($result)) {
                    add_settings_error('simplebackup', 'undo_error', $result->get_error_message(), 'error');
                } else {
                    add_settings_error('simplebackup', 'undo_success', 'Undo completed. Site reverted to pre-restore state.', 'success');
                }
            }
        }
    }

    public function run_backup($type = 'full') {
        $settings = $this->get_settings();
        $dir = $this->get_backup_dir();
        $this->protect_dir($dir);

        $timestamp = current_time('timestamp');
        $backup_id = 'simplebackup-' . gmdate('Y-m-d-His', $timestamp);
        $zip_path = $dir . '/' . $backup_id . '.zip';

        $log = array();
        $log[] = "Starting backup: {$backup_id}";
        $log[] = "Type: {$type}";

        $zip = new ZipArchive();
        $res = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            return new WP_Error('zip_open', 'Failed to create backup archive.');
        }

        $manifest = array(
            'id'          => $backup_id,
            'timestamp'   => $timestamp,
            'type'        => $type,
            'wp_version'  => get_bloginfo('version'),
            'db_version'  => get_option('db_version'),
            'site_url'    => get_site_url(),
            'home_url'    => get_home_url(),
            'files'       => array(),
        );

        // Database backup
        if ($settings['backup_database']) {
            $log[] = "Backing up database...";
            $db_backup = new SimpleBackup_Database_Backup();
            $sql_file = $db_backup->export();
            if (is_wp_error($sql_file)) {
                $zip->close();
                @unlink($zip_path);
                return $sql_file;
            }
            $zip->addFile($sql_file, 'db/backup.sql');
            $manifest['database'] = true;
            $log[] = "Database backup complete.";
        }

        // File backup
        $file_backup = new SimpleBackup_File_Backup();
        $incremental = new SimpleBackup_Incremental();
        $last_manifest = ($type === 'incremental' && $settings['incremental_enabled']) ? $incremental->get_last_manifest() : null;

        $dirs = array();
        if ($settings['backup_themes'])  $dirs[] = get_theme_root();
        if ($settings['backup_plugins']) $dirs[] = WP_PLUGIN_DIR;
        if ($settings['backup_uploads']) $dirs[] = wp_upload_dir()['basedir'];
        if ($settings['backup_core'])    $dirs[] = ABSPATH;
        foreach ($settings['custom_dirs'] as $custom) {
            if (is_dir($custom)) $dirs[] = $custom;
        }

        $log[] = "Backing up files...";
        foreach ($dirs as $dir_path) {
            $result = $file_backup->add_directory($zip, $dir_path, $last_manifest, $manifest['files']);
            if (is_wp_error($result)) {
                $zip->close();
                @unlink($zip_path);
                return $result;
            }
        }
        $log[] = "File backup complete. " . count($manifest['files']) . " files.";

        // Add manifest
        $zip->addFromString('manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT));

        // Encryption
        if ($settings['encryption_enabled'] && !empty($settings['encryption_password'])) {
            $zip->close();
            $encrypted = SimpleBackup_Encryption::instance()->encrypt_zip($zip_path, $settings['encryption_password']);
            if (is_wp_error($encrypted)) {
                @unlink($zip_path);
                return $encrypted;
            }
            $zip_path = $encrypted;
            $backup_id .= '-encrypted';
        } else {
            $zip->close();
        }

        // Cleanup old SQL temp file
        if (isset($sql_file) && file_exists($sql_file)) {
            @unlink($sql_file);
        }

        // Write log
        file_put_contents($dir . '/' . $backup_id . '.log', implode("\n", $log));

        // Retention cleanup
        $this->cleanup_old_backups();

        return $backup_id;
    }

    public function cleanup_old_backups() {
        $settings = $this->get_settings();
        $retention = intval($settings['retention_count']);
        if ($retention <= 0) return;

        $storage = SimpleBackup_Storage_Local::instance();
        $backups = $storage->list_backups();

        if (count($backups) > $retention) {
            // Sort by timestamp descending
            usort($backups, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            $to_delete = array_slice($backups, $retention);
            foreach ($to_delete as $backup) {
                $storage->delete_backup($backup['id']);
            }
        }
    }

    public function get_backups() {
        return SimpleBackup_Storage_Local::instance()->list_backups();
    }

    /**
     * Test settings without saving. Returns array of test results.
     */
    public function test_settings($settings) {
        $results = array();
        $pass = true;

        // Test backup directory
        $dir = !empty($settings['backup_dir']) ? rtrim($settings['backup_dir'], '/') : SIMPLEBACKUP_DEFAULT_DIR;
        if (!file_exists($dir)) {
            $results[] = array('status' => 'warning', 'message' => "Directory does not exist: {$dir}. It will be created on first backup.");
        } elseif (!is_dir($dir)) {
            $results[] = array('status' => 'error', 'message' => "Path exists but is not a directory: {$dir}");
            $pass = false;
        } elseif (!is_writable($dir)) {
            $results[] = array('status' => 'error', 'message' => "Directory is not writable: {$dir}. Check permissions.");
            $pass = false;
        } else {
            $free = function_exists('disk_free_space') ? disk_free_space($dir) : false;
            $results[] = array('status' => 'success', 'message' => "Directory is writable: {$dir}" . ($free !== false ? ' (Free space: ' . size_format($free) . ')' : ''));
        }

        // Test what will be backed up
        $dirs = array();
        if (!empty($settings['backup_themes']))  $dirs[] = get_theme_root();
        if (!empty($settings['backup_plugins'])) $dirs[] = WP_PLUGIN_DIR;
        if (!empty($settings['backup_uploads'])) $dirs[] = wp_upload_dir()['basedir'];
        if (!empty($settings['backup_core']))    $dirs[] = ABSPATH;

        $total_files = 0;
        $total_size = 0;
        foreach ($dirs as $dir_path) {
            if (!is_dir($dir_path)) continue;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $total_files++;
                    $total_size += $file->getSize();
                }
            }
        }
        $results[] = array('status' => 'info', 'message' => "Estimated backup: ~{$total_files} files, ~" . size_format($total_size));

        // Test mysqldump
        $mysqldump = shell_exec('which mysqldump 2>/dev/null');
        if (!empty($mysqldump)) {
            $results[] = array('status' => 'success', 'message' => 'mysqldump available: ' . trim($mysqldump));
        } else {
            $results[] = array('status' => 'warning', 'message' => 'mysqldump not found. Will use PHP fallback (slower for large DBs).');
        }

        // Test ZipArchive
        if (class_exists('ZipArchive')) {
            $results[] = array('status' => 'success', 'message' => 'ZipArchive extension available.');
        } else {
            $results[] = array('status' => 'error', 'message' => 'ZipArchive extension missing. Backups cannot run.');
            $pass = false;
        }

        // Test cron
        if (!empty($settings['schedule_enabled'])) {
            $next = SimpleBackup_Scheduler::instance()->get_next_scheduled_time($settings['schedule_interval'], $settings['schedule_time'] ?? '02:00');
            $results[] = array('status' => 'info', 'message' => 'Next scheduled backup: ' . gmdate('Y-m-d H:i:s', $next) . ' (server time)');
        }

        // Test encryption
        if (!empty($settings['encryption_enabled'])) {
            if (empty($settings['encryption_password'])) {
                $results[] = array('status' => 'error', 'message' => 'Encryption enabled but no password set.');
                $pass = false;
            } elseif (strlen($settings['encryption_password']) < 8) {
                $results[] = array('status' => 'warning', 'message' => 'Encryption password is very short (recommended: 12+ characters).');
            } else {
                $results[] = array('status' => 'success', 'message' => 'Encryption configured.');
            }
        }

        return array('pass' => $pass, 'results' => $results);
    }

    /**
     * Dry-run backup. Estimates what would be backed up without creating files.
     */
    public function test_backup($type = 'full') {
        $settings = $this->get_settings();
        $dir = $this->get_backup_dir();

        $estimate = array(
            'type'          => $type,
            'database'      => false,
            'db_size'       => 0,
            'files'         => 0,
            'file_size'     => 0,
            'incremental'   => false,
            'skipped_files' => 0,
            'dest_dir'      => $dir,
            'dest_writable' => is_writable($dir),
        );

        // Estimate database size
        if ($settings['backup_database']) {
            global $wpdb;
            $db_size = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
            $estimate['database'] = true;
            $estimate['db_size'] = intval($db_size);
        }

        // Estimate files
        $file_backup = new SimpleBackup_File_Backup();
        $incremental = new SimpleBackup_Incremental();
        $last_manifest = ($type === 'incremental' && $settings['incremental_enabled']) ? $incremental->get_last_manifest() : null;
        $estimate['incremental'] = ($last_manifest !== null);

        $dirs = array();
        if ($settings['backup_themes'])  $dirs[] = get_theme_root();
        if ($settings['backup_plugins']) $dirs[] = WP_PLUGIN_DIR;
        if ($settings['backup_uploads']) $dirs[] = wp_upload_dir()['basedir'];
        if ($settings['backup_core'])    $dirs[] = ABSPATH;

        foreach ($dirs as $dir_path) {
            if (!is_dir($dir_path)) continue;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                $relative_path = $file_backup->get_relative_path_for_test($file->getPathname());
                if ($file_backup->is_excluded_for_test($relative_path)) continue;

                if ($last_manifest !== null && isset($last_manifest['files'][$relative_path])) {
                    $last_file = $last_manifest['files'][$relative_path];
                    if ($last_file['mtime'] === $file->getMTime() && $last_file['size'] === $file->getSize()) {
                        $estimate['skipped_files']++;
                        continue;
                    }
                }
                $estimate['files']++;
                $estimate['file_size'] += $file->getSize();
            }
        }

        $estimate['db_size_formatted'] = size_format($estimate['db_size']);
        $estimate['file_size_formatted'] = size_format($estimate['file_size']);
        return $estimate;
    }

    /**
     * Create a quick safety backup before restore.
     */
    public function create_safety_backup() {
        $timestamp = current_time('timestamp');
        $backup_id = 'simplebackup-safety-' . gmdate('Y-m-d-His', $timestamp);
        update_option('simplebackup_last_safety_backup', $backup_id);
        return $this->run_backup('full');
    }

    /**
     * Undo last restore using the safety backup.
     */
    public function undo_restore() {
        $safety_id = get_option('simplebackup_last_safety_backup');
        if (empty($safety_id)) {
            return new WP_Error('no_safety', 'No safety backup found. Cannot undo.');
        }
        $result = SimpleBackup_Restorer::instance()->restore($safety_id, 'both');
        if (!is_wp_error($result)) {
            delete_option('simplebackup_last_safety_backup');
        }
        return $result;
    }

    /**
     * Check if undo is available.
     */
    public function can_undo_restore() {
        $safety_id = get_option('simplebackup_last_safety_backup');
        if (empty($safety_id)) return false;
        $path = SimpleBackup_Storage_Local::instance()->get_backup_path($safety_id);
        return file_exists($path);
    }
}
