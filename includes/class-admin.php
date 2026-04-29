<?php
/**
 * Admin UI for SimpleBackup.
 */
class SimpleBackup_Admin {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_notices', array($this, 'show_notices'));
        add_action('wp_ajax_simplebackup_test_dir', array($this, 'ajax_test_dir'));
    }

    public function add_menu() {
        add_management_page(
            __('SimpleBackup', 'simplebackup'),
            __('SimpleBackup', 'simplebackup'),
            'manage_options',
            'simplebackup',
            array($this, 'render_page')
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'tools_page_simplebackup') return;
        wp_enqueue_style('simplebackup-admin', SIMPLEBACKUP_PLUGIN_URL . 'assets/css/admin.css', array(), SIMPLEBACKUP_VERSION);
        wp_enqueue_script('simplebackup-admin', SIMPLEBACKUP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SIMPLEBACKUP_VERSION, true);
        wp_localize_script('simplebackup-admin', 'simplebackup_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('simplebackup_ajax_nonce'),
        ));
    }

    public function show_notices() {
        settings_errors('simplebackup');
    }

    public function ajax_test_dir() {
        check_ajax_referer('simplebackup_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        $dir = isset($_POST['dir']) ? sanitize_text_field($_POST['dir']) : '';
        if (empty($dir)) {
            wp_send_json_error('No directory specified.');
        }

        $result = array(
            'exists'      => file_exists($dir),
            'is_dir'      => is_dir($dir),
            'readable'    => is_readable($dir),
            'writable'    => is_writable($dir),
            'perms'       => file_exists($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : '----',
            'realpath'    => file_exists($dir) ? realpath($dir) : 'N/A',
            'free_space'  => function_exists('disk_free_space') && file_exists($dir) ? size_format(disk_free_space($dir)) : 'Unknown',
            'contents'    => array(),
        );

        if (is_dir($dir) && is_readable($dir)) {
            $items = scandir($dir);
            foreach (array_slice($items, 0, 30) as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = rtrim($dir, '/') . '/' . $item;
                $result['contents'][] = array(
                    'name'  => $item,
                    'type'  => is_dir($full) ? 'dir' : 'file',
                    'size'  => is_file($full) ? size_format(filesize($full)) : '',
                );
            }
            if (count($items) > 30) {
                $result['contents'][] = array('name' => '... (' . (count($items) - 2) . ' total items)', 'type' => 'info', 'size' => '');
            }
        }

        wp_send_json_success($result);
    }

    public function get_system_info() {
        global $wpdb;
        $info = array(
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version'       => phpversion(),
            'MySQL Version'     => $wpdb->db_version(),
            'Server OS'         => php_uname('s') . ' ' . php_uname('r'),
            'Document Root'     => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'ABSPATH'           => ABSPATH,
            'WP_CONTENT_DIR'    => WP_CONTENT_DIR,
            'Current User'      => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user(),
            'Server Time'       => current_time('mysql'),
            'ZipArchive'        => class_exists('ZipArchive') ? 'Available' : 'Missing',
            'mysqldump'         => shell_exec('which mysqldump 2>/dev/null') ? 'Available (' . trim(shell_exec('which mysqldump 2>/dev/null')) . ')' : 'Not found',
        );
        return $info;
    }

    public function render_page() {
        $plugin = SimpleBackup::instance();
        $settings = $plugin->get_settings();
        $backups = $plugin->get_backups();
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'backups';
        ?>
        <div class="wrap simplebackup-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?> <span class="version">v<?php echo esc_html(SIMPLEBACKUP_VERSION); ?></span></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=simplebackup&tab=backups" class="nav-tab <?php echo $tab === 'backups' ? 'nav-tab-active' : ''; ?>"><?php _e('Backups', 'simplebackup'); ?></a>
                <a href="?page=simplebackup&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'simplebackup'); ?></a>
                <a href="?page=simplebackup&tab=system" class="nav-tab <?php echo $tab === 'system' ? 'nav-tab-active' : ''; ?>"><?php _e('System Info', 'simplebackup'); ?></a>
                <a href="?page=simplebackup&tab=logs" class="nav-tab <?php echo $tab === 'logs' ? 'nav-tab-active' : ''; ?>"><?php _e('Logs', 'simplebackup'); ?></a>
            </nav>

            <?php if ($tab === 'backups') : ?>
                <div class="simplebackup-section">
                    <h2><?php _e('Manual Backup', 'simplebackup'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('simplebackup_nonce'); ?>
                        <input type="hidden" name="simplebackup_action" value="backup_now">
                        <p>
                            <label><input type="radio" name="backup_type" value="full" checked> <?php _e('Full Backup', 'simplebackup'); ?></label><br>
                            <label><input type="radio" name="backup_type" value="incremental"> <?php _e('Incremental Backup (changed files only)', 'simplebackup'); ?></label>
                        </p>
                        <p>
                            <button type="submit" class="button button-primary"><?php _e('Backup Now', 'simplebackup'); ?></button>
                        </p>
                    </form>
                </div>

                <div class="simplebackup-section">
                    <h2><?php _e('Existing Backups', 'simplebackup'); ?> (<?php echo count($backups); ?>)</h2>
                    <?php if (empty($backups)) : ?>
                        <p><?php _e('No backups found.', 'simplebackup'); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Date', 'simplebackup'); ?></th>
                                    <th><?php _e('Type', 'simplebackup'); ?></th>
                                    <th><?php _e('Files', 'simplebackup'); ?></th>
                                    <th><?php _e('Database', 'simplebackup'); ?></th>
                                    <th><?php _e('Size', 'simplebackup'); ?></th>
                                    <th><?php _e('Actions', 'simplebackup'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup) : ?>
                                    <tr>
                                        <td><?php echo esc_html(gmdate('Y-m-d H:i:s', $backup['timestamp'])); ?></td>
                                        <td><?php echo esc_html($backup['type']); ?></td>
                                        <td><?php echo isset($backup['files_count']) ? number_format($backup['files_count']) : '—'; ?></td>
                                        <td><?php echo !empty($backup['has_database']) ? '✓' : '—'; ?></td>
                                        <td><?php echo esc_html(size_format($backup['size'])); ?></td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('simplebackup_nonce'); ?>
                                                <input type="hidden" name="simplebackup_action" value="restore">
                                                <input type="hidden" name="backup_id" value="<?php echo esc_attr($backup['id']); ?>">
                                                <select name="restore_type">
                                                    <option value="both"><?php _e('Restore All', 'simplebackup'); ?></option>
                                                    <option value="database"><?php _e('DB Only', 'simplebackup'); ?></option>
                                                    <option value="files"><?php _e('Files Only', 'simplebackup'); ?></option>
                                                </select>
                                                <button type="submit" class="button" onclick="return confirm('<?php _e('Are you sure? This will overwrite your current site.', 'simplebackup'); ?>');"><?php _e('Restore', 'simplebackup'); ?></button>
                                            </form>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field('simplebackup_nonce'); ?>
                                                <input type="hidden" name="simplebackup_action" value="delete_backup">
                                                <input type="hidden" name="backup_id" value="<?php echo esc_attr($backup['id']); ?>">
                                                <button type="submit" class="button button-link-delete" onclick="return confirm('<?php _e('Delete this backup?', 'simplebackup'); ?>');"><?php _e('Delete', 'simplebackup'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab === 'settings') : ?>
                <div class="simplebackup-section">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('simplebackup_settings_group');
                        do_settings_sections('simplebackup_settings_group');
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="backup_dir"><?php _e('Backup Directory', 'simplebackup'); ?></label></th>
                                <td>
                                    <input type="text" name="simplebackup_settings[backup_dir]" id="backup_dir" value="<?php echo esc_attr($settings['backup_dir']); ?>" class="regular-text">
                                    <button type="button" class="button" id="simplebackup-test-dir"><?php _e('Test Directory', 'simplebackup'); ?></button>
                                    <p class="description"><?php _e('Absolute path for backups. Mount your NAS here via SMB/NFS.', 'simplebackup'); ?></p>
                                    <div id="simplebackup-dir-results" style="margin-top:10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Schedule', 'simplebackup'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="simplebackup_settings[schedule_enabled]" value="1" <?php checked($settings['schedule_enabled']); ?>>
                                        <?php _e('Enable scheduled backups', 'simplebackup'); ?>
                                    </label><br><br>
                                    <label>
                                        <?php _e('Interval:', 'simplebackup'); ?>
                                        <select name="simplebackup_settings[schedule_interval]">
                                            <option value="hourly" <?php selected($settings['schedule_interval'], 'hourly'); ?>><?php _e('Hourly', 'simplebackup'); ?></option>
                                            <option value="twicedaily" <?php selected($settings['schedule_interval'], 'twicedaily'); ?>><?php _e('Twice Daily', 'simplebackup'); ?></option>
                                            <option value="daily" <?php selected($settings['schedule_interval'], 'daily'); ?>><?php _e('Daily', 'simplebackup'); ?></option>
                                            <option value="weekly" <?php selected($settings['schedule_interval'], 'weekly'); ?>><?php _e('Weekly', 'simplebackup'); ?></option>
                                        </select>
                                    </label>
                                    <label style="margin-left:15px;">
                                        <?php _e('At time:', 'simplebackup'); ?>
                                        <input type="time" name="simplebackup_settings[schedule_time]" value="<?php echo esc_attr($settings['schedule_time']); ?>">
                                    </label>
                                    <p class="description"><?php _e('Backups will run at the specified time. Server time is used.', 'simplebackup'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="retention_count"><?php _e('Retention', 'simplebackup'); ?></label></th>
                                <td>
                                    <input type="number" name="simplebackup_settings[retention_count]" id="retention_count" value="<?php echo esc_attr($settings['retention_count']); ?>" min="1" max="365">
                                    <p class="description"><?php _e('Number of backups to keep. Older backups are auto-deleted.', 'simplebackup'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('What to Backup', 'simplebackup'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="simplebackup_settings[backup_database]" value="1" <?php checked($settings['backup_database']); ?>> <?php _e('Database', 'simplebackup'); ?></label><br>
                                    <label><input type="checkbox" name="simplebackup_settings[backup_themes]" value="1" <?php checked($settings['backup_themes']); ?>> <?php _e('Themes', 'simplebackup'); ?></label><br>
                                    <label><input type="checkbox" name="simplebackup_settings[backup_plugins]" value="1" <?php checked($settings['backup_plugins']); ?>> <?php _e('Plugins', 'simplebackup'); ?></label><br>
                                    <label><input type="checkbox" name="simplebackup_settings[backup_uploads]" value="1" <?php checked($settings['backup_uploads']); ?>> <?php _e('Uploads', 'simplebackup'); ?></label><br>
                                    <label><input type="checkbox" name="simplebackup_settings[backup_core]" value="1" <?php checked($settings['backup_core']); ?>> <?php _e('WordPress Core', 'simplebackup'); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Incremental', 'simplebackup'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="simplebackup_settings[incremental_enabled]" value="1" <?php checked($settings['incremental_enabled']); ?>>
                                        <?php _e('Enable incremental backups (only changed files)', 'simplebackup'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Encryption', 'simplebackup'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="simplebackup_settings[encryption_enabled]" value="1" <?php checked($settings['encryption_enabled']); ?>>
                                        <?php _e('Encrypt backups with password', 'simplebackup'); ?>
                                    </label><br>
                                    <input type="password" name="simplebackup_settings[encryption_password]" value="<?php echo esc_attr($settings['encryption_password']); ?>" placeholder="Password" class="regular-text">
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>

            <?php elseif ($tab === 'system') : ?>
                <div class="simplebackup-section">
                    <h2><?php _e('System Information', 'simplebackup'); ?></h2>
                    <p class="description"><?php _e('Useful for Docker and server troubleshooting.', 'simplebackup'); ?></p>
                    <table class="widefat striped">
                        <tbody>
                            <?php foreach ($this->get_system_info() as $key => $value) : ?>
                                <tr>
                                    <th style="width:200px;"><?php echo esc_html($key); ?></th>
                                    <td><code><?php echo esc_html($value); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="simplebackup-section">
                    <h2><?php _e('Common Docker Paths', 'simplebackup'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Path', 'simplebackup'); ?></th>
                                <th><?php _e('Status', 'simplebackup'); ?></th>
                                <th><?php _e('Notes', 'simplebackup'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $docker_paths = array(
                                '/var/www/html/wp-content/' => 'Standard Docker WordPress content dir',
                                '/var/www/html/'            => 'Standard Docker WordPress root',
                                '/mnt/'                     => 'Common mount point for NAS volumes',
                                '/backup/'                  => 'Alternative Docker volume mount',
                                '/data/'                    => 'Alternative Docker volume mount',
                                WP_CONTENT_DIR . '/'        => 'Current WP_CONTENT_DIR',
                                ABSPATH                     => 'Current ABSPATH',
                            );
                            foreach ($docker_paths as $path => $note) :
                                $exists = file_exists($path);
                                $writable = $exists && is_writable($path);
                            ?>
                                <tr>
                                    <td><code><?php echo esc_html($path); ?></code></td>
                                    <td>
                                        <?php if (!$exists) : ?>
                                            <span style="color:#999;">Not found</span>
                                        <?php elseif ($writable) : ?>
                                            <span style="color:green;">✓ Writable</span>
                                        <?php else : ?>
                                            <span style="color:orange;">Exists, not writable</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($note); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab === 'logs') : ?>
                <div class="simplebackup-section">
                    <h2><?php _e('Backup Logs', 'simplebackup'); ?></h2>
                    <?php
                    $log_files = glob($plugin->get_backup_dir() . '/*.log');
                    if (empty($log_files)) : ?>
                        <p><?php _e('No logs found.', 'simplebackup'); ?></p>
                    <?php else :
                        rsort($log_files);
                        foreach (array_slice($log_files, 0, 20) as $log_file) : ?>
                            <div class="simplebackup-log">
                                <h4><?php echo esc_html(basename($log_file)); ?></h4>
                                <pre><?php echo esc_html(file_get_contents($log_file)); ?></pre>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Register settings
add_action('admin_init', function() {
    register_setting('simplebackup_settings_group', 'simplebackup_settings', function($input) {
        $sanitized = array();
        $sanitized['backup_dir'] = sanitize_text_field($input['backup_dir'] ?? SIMPLEBACKUP_DEFAULT_DIR);
        $sanitized['schedule_enabled'] = !empty($input['schedule_enabled']);
        $sanitized['schedule_interval'] = in_array($input['schedule_interval'] ?? '', array('hourly','twicedaily','daily','weekly')) ? $input['schedule_interval'] : 'daily';
        $sanitized['schedule_time'] = preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $input['schedule_time'] ?? '') ? $input['schedule_time'] : '02:00';
        $sanitized['retention_count'] = max(1, min(365, intval($input['retention_count'] ?? 7)));
        $sanitized['backup_themes'] = !empty($input['backup_themes']);
        $sanitized['backup_plugins'] = !empty($input['backup_plugins']);
        $sanitized['backup_uploads'] = !empty($input['backup_uploads']);
        $sanitized['backup_core'] = !empty($input['backup_core']);
        $sanitized['backup_database'] = !empty($input['backup_database']);
        $sanitized['incremental_enabled'] = !empty($input['incremental_enabled']);
        $sanitized['encryption_enabled'] = !empty($input['encryption_enabled']);
        $sanitized['encryption_password'] = sanitize_text_field($input['encryption_password'] ?? '');
        $sanitized['custom_dirs'] = array(); // Advanced: can add UI later

        // Reschedule cron if changed
        SimpleBackup_Scheduler::instance()->clear_schedules();
        if ($sanitized['schedule_enabled']) {
            SimpleBackup_Scheduler::instance()->schedule_events($sanitized['schedule_interval'], $sanitized['schedule_time']);
        }

        return $sanitized;
    });
});
