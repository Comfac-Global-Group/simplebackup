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
                    $result = SimpleBackup_Restorer::instance()->restore($backup_id, $restore_type);
                    if (is_wp_error($result)) {
                        add_settings_error('simplebackup', 'restore_error', $result->get_error_message(), 'error');
                    } else {
                        add_settings_error('simplebackup', 'restore_success', 'Restore completed successfully.', 'success');
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
}
