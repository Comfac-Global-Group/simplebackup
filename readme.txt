=== SimpleBackup ===
Contributors: simplebackup
Tags: backup, restore, incremental, scheduled, nas
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Free WordPress backup with incremental backups, scheduling, and NAS/local storage. No premium upsells.

== Description ==

SimpleBackup is a clean, lightweight WordPress backup plugin that gives you everything you need without locked "premium" features.

**All features are free:**
- Full and incremental file backups
- Database backups
- Scheduled backups via WP-Cron
- Local directory / NAS storage (mount via SMB/NFS)
- One-click restore
- Optional AES-256 encryption
- Retention policies (auto-delete old backups)

**Why SimpleBackup?**
Unlike other backup plugins that lock remote storage, incremental backups, and scheduling behind a paywall, SimpleBackup provides all core functionality for free. Your backups belong to you.

**NAS Setup:**
Mount your NAS share at the OS level (e.g., `/mnt/nas-backups/`) and point SimpleBackup at that directory. No APIs, no OAuth, no vendor lock-in.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/simplebackup/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools → SimpleBackup
4. Set your backup directory and schedule
5. Click "Backup Now" to test

== Frequently Asked Questions ==

= Does this work with NAS devices? =
Yes. Mount your NAS via SMB or NFS at the OS level, then set the backup directory to the mount point.

= Are incremental backups really free? =
Yes. Incremental backups track changed files and are included at no cost.

= Can I restore from a backup? =
Yes. Go to Tools → SimpleBackup, find your backup in the list, and click Restore.

= Is my data encrypted? =
Optional AES-256 encryption is available in the settings.

== Changelog ==

= 1.0.0 =
* Initial release
* Full and incremental backups
* Database export
* Scheduled backups
* Local/NAS storage
* Restore functionality
* Encryption support
