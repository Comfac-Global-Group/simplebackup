<?php
/**
 * File scanner and ZIP builder.
 */
class SimpleBackup_File_Backup {

    private $excluded_patterns = array(
        '/simplebackup-backups/',
        '/simplebackup/',
        '/updraft/',
        '/cache/',
        '/node_modules/',
        '/.git/',
        '/.svn/',
    );

    public function add_directory($zip, $dir, $last_manifest, &$manifest_files) {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            return true;
        }

        $base_name = basename($dir);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $file_path = $file->getPathname();
            $relative_path = $this->get_relative_path($file_path);

            if ($this->is_excluded($relative_path)) continue;

            $mtime = $file->getMTime();
            $size = $file->getSize();

            // Incremental check
            if ($last_manifest !== null && isset($last_manifest['files'][$relative_path])) {
                $last_file = $last_manifest['files'][$relative_path];
                if ($last_file['mtime'] === $mtime && $last_file['size'] === $size) {
                    continue; // Unchanged
                }
            }

            $zip->addFile($file_path, 'files/' . $relative_path);
            $manifest_files[$relative_path] = array(
                'mtime' => $mtime,
                'size'  => $size,
                'hash'  => md5_file($file_path),
            );
        }

        return true;
    }

    private function get_relative_path($path) {
        $abspath = rtrim(ABSPATH, '/');
        $content_dir = rtrim(WP_CONTENT_DIR, '/');
        $plugin_dir = rtrim(WP_PLUGIN_DIR, '/');
        $theme_root = rtrim(get_theme_root(), '/');
        $upload_dir = rtrim(wp_upload_dir()['basedir'], '/');

        if (strpos($path, $upload_dir . '/') === 0) {
            return 'uploads/' . substr($path, strlen($upload_dir) + 1);
        } elseif (strpos($path, $plugin_dir . '/') === 0) {
            return 'plugins/' . substr($path, strlen($plugin_dir) + 1);
        } elseif (strpos($path, $theme_root . '/') === 0) {
            return 'themes/' . substr($path, strlen($theme_root) + 1);
        } elseif (strpos($path, $content_dir . '/') === 0) {
            return 'wp-content/' . substr($path, strlen($content_dir) + 1);
        } elseif (strpos($path, $abspath . '/') === 0) {
            return 'core/' . substr($path, strlen($abspath) + 1);
        }

        return basename($path);
    }

    private function is_excluded($relative_path) {
        foreach ($this->excluded_patterns as $pattern) {
            if (strpos($relative_path, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}
