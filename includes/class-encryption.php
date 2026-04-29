<?php
/**
 * ZIP encryption support for SimpleBackup.
 * Uses ZipArchive AES-256 encryption when available, otherwise system zip command.
 */
class SimpleBackup_Encryption {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function encrypt_zip($zip_path, $password) {
        // Try ZipArchive encryption (PHP 7.2+)
        if (defined('ZipArchive::EM_AES_256')) {
            return $this->encrypt_via_ziparchive($zip_path, $password);
        }

        // Fallback to system zip
        return $this->encrypt_via_zip_command($zip_path, $password);
    }

    private function encrypt_via_ziparchive($zip_path, $password) {
        $new_path = preg_replace('/\.zip$/', '-encrypted.zip', $zip_path);

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('encrypt_open', 'Failed to open archive for encryption.');
        }

        $tmp_dir = get_temp_dir() . 'simplebackup-enc-' . wp_generate_password(8, false) . '/';
        wp_mkdir_p($tmp_dir);
        $zip->extractTo($tmp_dir);
        $zip->close();

        $new_zip = new ZipArchive();
        if ($new_zip->open($new_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->cleanup_dir($tmp_dir);
            return new WP_Error('encrypt_create', 'Failed to create encrypted archive.');
        }

        $new_zip->setPassword($password);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($tmp_dir));
            if ($file->isDir()) {
                $new_zip->addEmptyDir($relative);
            } else {
                $new_zip->addFile($file->getPathname(), $relative);
                $new_zip->setEncryptionName($relative, ZipArchive::EM_AES_256);
            }
        }

        $new_zip->close();
        $this->cleanup_dir($tmp_dir);

        if (file_exists($new_path) && filesize($new_path) > 0) {
            @unlink($zip_path);
            return $new_path;
        }

        return new WP_Error('encrypt_failed', 'Encryption failed.');
    }

    private function encrypt_via_zip_command($zip_path, $password) {
        $new_path = preg_replace('/\.zip$/', '-encrypted.zip', $zip_path);
        $zip_bin = $this->find_zip_binary();

        if (empty($zip_bin)) {
            return new WP_Error('no_zip', 'zip command not found. Install zip or upgrade to PHP 7.2+.');
        }

        $tmp_dir = get_temp_dir() . 'simplebackup-enc-' . wp_generate_password(8, false) . '/';
        wp_mkdir_p($tmp_dir);

        $unzip = shell_exec('unzip -o ' . escapeshellarg($zip_path) . ' -d ' . escapeshellarg($tmp_dir) . ' 2>&1');
        if (!is_dir($tmp_dir)) {
            return new WP_Error('unzip_failed', 'Failed to extract archive for encryption.');
        }

        $cmd = 'cd ' . escapeshellarg($tmp_dir) . ' && ' . escapeshellarg($zip_bin);
        $cmd .= ' -r -P ' . escapeshellarg($password) . ' ' . escapeshellarg($new_path) . ' .';
        exec($cmd . ' 2>&1', $output, $return);

        $this->cleanup_dir($tmp_dir);

        if ($return === 0 && file_exists($new_path) && filesize($new_path) > 0) {
            @unlink($zip_path);
            return $new_path;
        }

        return new WP_Error('encrypt_failed', 'Encryption failed via zip command.');
    }

    private function find_zip_binary() {
        $paths = array('/usr/bin/zip', '/usr/local/bin/zip');
        foreach ($paths as $path) {
            if (is_executable($path)) return $path;
        }
        $which = shell_exec('which zip 2>/dev/null');
        if (!empty($which)) return trim($which);
        return '';
    }

    private function cleanup_dir($dir) {
        if (!is_dir($dir)) return;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
