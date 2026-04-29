<?php
/**
 * Incremental backup change detection.
 */
class SimpleBackup_Incremental {

    public function get_last_manifest() {
        $backups = SimpleBackup_Storage_Local::instance()->list_backups();
        if (empty($backups)) return null;

        // Get the most recent backup's manifest
        $latest = $backups[0];
        return SimpleBackup_Storage_Local::instance()->read_manifest($latest['path']);
    }
}
