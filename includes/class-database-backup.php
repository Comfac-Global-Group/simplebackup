<?php
/**
 * Database backup/export logic.
 */
class SimpleBackup_Database_Backup {

    public function export() {
        global $wpdb;

        $tmp_dir = get_temp_dir();
        $sql_file = $tmp_dir . 'simplebackup-db-' . wp_generate_password(8, false) . '.sql';

        // Try mysqldump first
        $result = $this->export_via_mysqldump($sql_file);
        if ($result === true) {
            return $sql_file;
        }

        // Fallback to PHP-based export
        $result = $this->export_via_php($sql_file);
        if ($result === true) {
            return $sql_file;
        }

        @unlink($sql_file);
        return new WP_Error('db_export', 'Failed to export database.');
    }

    private function export_via_mysqldump($sql_file) {
        $cmd = $this->get_mysqldump_command();
        if (empty($cmd)) return false;

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('file', $sql_file, 'w'),
            2 => array('pipe', 'w'),
        );

        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) return false;

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $return = proc_close($process);

        if ($return !== 0 || !file_exists($sql_file) || filesize($sql_file) === 0) {
            @unlink($sql_file);
            return false;
        }

        return true;
    }

    private function get_mysqldump_command() {
        $binary = $this->find_mysqldump();
        if (empty($binary)) return '';

        global $wpdb;
        $host = DB_HOST;
        $port = '';
        if (strpos($host, ':') !== false) {
            list($host, $port) = explode(':', $host, 2);
        }

        $cmd = escapeshellarg($binary);
        $cmd .= ' --no-defaults --single-transaction';
        $cmd .= ' --host=' . escapeshellarg($host);
        if (!empty($port) && is_numeric($port)) {
            $cmd .= ' --port=' . escapeshellarg(intval($port));
        }
        $cmd .= ' --user=' . escapeshellarg(DB_USER);
        $cmd .= ' --password=' . escapeshellarg(DB_PASSWORD);
        $cmd .= ' ' . escapeshellarg(DB_NAME);

        return $cmd;
    }

    private function find_mysqldump() {
        $paths = array('/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/usr/local/mysql/bin/mysqldump');
        foreach ($paths as $path) {
            if (is_executable($path)) return $path;
        }
        $which = shell_exec('which mysqldump 2>/dev/null');
        if (!empty($which)) return trim($which);
        return '';
    }

    private function export_via_php($sql_file) {
        global $wpdb;
        $handle = fopen($sql_file, 'w');
        if (!$handle) return false;

        // Header
        fwrite($handle, "-- SimpleBackup Database Export\n");
        fwrite($handle, "-- Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n");
        fwrite($handle, "-- Site: " . get_site_url() . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $wpdb->get_col('SHOW TABLES');
        foreach ($tables as $table) {
            // Drop and create table
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $create[1] . ";\n\n");

            // Get data
            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $insert_prefix = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . '`) VALUES ';

                $batch = array();
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $wpdb->_real_escape($value) . "'";
                        }
                    }
                    $batch[] = '(' . implode(', ', $values) . ')';

                    if (count($batch) >= 100) {
                        fwrite($handle, $insert_prefix . implode(', ', $batch) . ";\n");
                        $batch = array();
                    }
                }
                if (!empty($batch)) {
                    fwrite($handle, $insert_prefix . implode(', ', $batch) . ";\n");
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return filesize($sql_file) > 0;
    }
}
