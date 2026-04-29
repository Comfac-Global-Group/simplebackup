<?php
/**
 * Plugin Name: SimpleBackup
 * Description: Free WordPress backup plugin with incremental backups, scheduling, and NAS/local storage support. No premium upsells.
 * Version: 1.0.0
 * Author: SimpleBackup
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: simplebackup
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SIMPLEBACKUP_VERSION', '1.0.0');
define('SIMPLEBACKUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIMPLEBACKUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIMPLEBACKUP_DEFAULT_DIR', WP_CONTENT_DIR . '/simplebackup-backups');

require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-simplebackup.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-storage-local.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-database-backup.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-file-backup.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-incremental.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-restorer.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-encryption.php';
require_once SIMPLEBACKUP_PLUGIN_DIR . 'includes/class-admin.php';

function simplebackup_init() {
    SimpleBackup::instance();
}
add_action('plugins_loaded', 'simplebackup_init');

register_activation_hook(__FILE__, 'simplebackup_activate');
register_deactivation_hook(__FILE__, 'simplebackup_deactivate');

function simplebackup_activate() {
    SimpleBackup::instance()->activate();
}

function simplebackup_deactivate() {
    SimpleBackup::instance()->deactivate();
}
