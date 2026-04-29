<?php
/**
 * Restore logic for SimpleBackup.
 */
class SimpleBackup_Restorer {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function restore($backup_id, $type = 'both') {
        $storage = SimpleBackup_Storage_Local::instance();
        $path = $storage->get_backup_path($backup_id);

        if (!file_exists($path)) {
            return new WP_Error('restore_not_found', 'Backup file not found.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return new WP_Error('restore_zip', 'Failed to open backup archive.');
        }

        $tmp_dir = get_temp_dir() . 'simplebackup-restore-' . wp_generate_password(8, false) . '/';
        wp_mkdir_p($tmp_dir);

        $zip->extractTo($tmp_dir);
        $zip->close();

        $result = true;

        if ($type === 'both' || $type === 'database') {
            $sql_file = $tmp_dir . 'db/backup.sql';
            if (file_exists($sql_file)) {
                $result = $this->restore_database($sql_file);
                if (is_wp_error($result)) {
                    $this->cleanup($tmp_dir);
                    return $result;
                }
            }
        }

        if ($type === 'both' || $type === 'files') {
            $files_dir = $tmp_dir . 'files/';
            if (is_dir($files_dir)) {
                $result = $this->restore_files($files_dir);
                if (is_wp_error($result)) {
                    $this->cleanup($tmp_dir);
                    return $result;
                }
            }
        }

        $this->cleanup($tmp_dir);
        return true;
    }

    private function restore_database($sql_file) {
        global $wpdb;

        // Try mysql command first
        $cmd = $this->get_mysql_command();
        if (!empty($cmd)) {
            $cmd .= ' < ' . escapeshellarg($sql_file) . ' 2>&1';
            exec($cmd, $output, $return);
            if ($return === 0) return true;
        }

        // Fallback: PHP-based import
        $handle = fopen($sql_file, 'r');
        if (!$handle) {
            return new WP_Error('restore_db', 'Failed to read SQL file.');
        }

        $query = '';
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) break;

            $line = trim($line);
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) continue;

            $query .= $line;
            if (substr($line, -1) === ';') {
                $wpdb->query($query);
                $query = '';
            }
        }
        fclose($handle);

        return true;
    }

    private function get_mysql_command() {
        $binary = $this->find_mysql();
        if (empty($binary)) return '';

        $host = DB_HOST;
        $port = '';
        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host, 2);
        }

        $cmd = escapeshellarg($binary);
        $cmd .= ' --no-defaults';
        $cmd .= ' --host=' . escapeshellarg($host);
        if (!empty($port) && is_numeric($port)) {
            $cmd .= ' --port=' . escapeshellarg(intval($port));
        }
        $cmd .= ' --user=' . escapeshellarg(DB_USER);
        $cmd .= ' --password=' . escapeshellarg(DB_PASSWORD);
        $cmd .= ' ' . escapeshellarg(DB_NAME);

        return $cmd;
    }

    private function find_mysql() {
        $paths = array('/usr/bin/mysql', '/usr/local/bin/mysql', '/usr/local/mysql/bin/mysql');
        foreach ($paths as $path) {
            if (is_executable($path)) return $path;
        }
        $which = shell_exec('which mysql 2>/dev/null');
        if (!empty($which)) return trim($which);
        return '';
    }

    private function restore_files($files_dir) {
        $abspath = rtrim(ABSPATH, '/');
        $content_dir = rtrim(WP_CONTENT_DIR, '/');
        $plugin_dir = rtrim(WP_PLUGIN_DIR, '/');
        $theme_root = rtrim(get_theme_root(), '/');
        $upload_dir = rtrim(wp_upload_dir()['basedir'], '/');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($files_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $relative = substr($file->getPathname(), strlen($files_dir));
            $relative = ltrim($relative, '/');

            $dest = $this->resolve_destination($relative, $abspath, $content_dir, $plugin_dir, $theme_root, $upload_dir);
            if (empty($dest)) continue;

            wp_mkdir_p(dirname($dest));
            if (!copy($file->getPathname(), $dest)) {
                return new WP_Error('restore_file', 'Failed to restore: ' . $relative);
            }
        }

        return true;
    }

    private function resolve_destination($relative, $abspath, $content_dir, $plugin_dir, $theme_root, $upload_dir) {
        if (strpos($relative, 'uploads/') === 0) {
            return $upload_dir . '/' . substr($relative, 8);
        } elseif (strpos($relative, 'plugins/') === 0) {
            return $plugin_dir . '/' . substr($relative, 8);
        } elseif (strpos($relative, 'themes/') === 0) {
            return $theme_root . '/' . substr($relative, 7);
        } elseif (strpos($relative, 'wp-content/') === 0) {
            return $content_dir . '/' . substr($relative, 11);
        } elseif (strpos($relative, 'core/') === 0) {
            return $abspath . '/' . substr($relative, 5);
        }
        return '';
    }

    private function cleanup($tmp_dir) {
        if (is_dir($tmp_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    @rmdir($file->getPathname());
                } else {
                    @unlink($file->getPathname());
                }
            }
            @rmdir($tmp_dir);
        }
    }
}
