<?php
/**
 * Local storage handler for backups.
 */
class SimpleBackup_Storage_Local {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get_backup_path($backup_id) {
        $dir = SimpleBackup::instance()->get_backup_dir();
        $path = $dir . '/' . $backup_id . '.zip';
        if (!file_exists($path)) {
            $path = $dir . '/' . $backup_id . '-encrypted.zip';
        }
        return $path;
    }

    public function list_backups() {
        $dir = SimpleBackup::instance()->get_backup_dir();
        if (!is_dir($dir)) return array();

        $backups = array();
        $files = glob($dir . '/*.zip');
        foreach ($files as $file) {
            $filename = basename($file, '.zip');
            $id = preg_replace('/-encrypted$/', '', $filename);
            $manifest = $this->read_manifest($file);

            $backups[] = array(
                'id'           => $id,
                'timestamp'    => $manifest['timestamp'] ?? filemtime($file),
                'type'         => $manifest['type'] ?? 'full',
                'files_count'  => count($manifest['files'] ?? array()),
                'has_database' => !empty($manifest['database']),
                'size'         => filesize($file),
                'path'         => $file,
                'encrypted'    => strpos($filename, '-encrypted') !== false,
            );
        }

        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    public function read_manifest($zip_path) {
        if (!file_exists($zip_path)) return array();

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return array();
        }

        $index = $zip->locateName('manifest.json');
        if ($index === false) {
            $zip->close();
            return array();
        }

        $json = $zip->getFromIndex($index);
        $zip->close();

        $data = json_decode($json, true);
        return is_array($data) ? $data : array();
    }

    public function delete_backup($backup_id) {
        $dir = SimpleBackup::instance()->get_backup_dir();
        $files = array(
            $dir . '/' . $backup_id . '.zip',
            $dir . '/' . $backup_id . '-encrypted.zip',
            $dir . '/' . $backup_id . '.log',
            $dir . '/' . $backup_id . '-encrypted.log',
        );
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
