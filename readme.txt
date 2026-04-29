=== SimpleBackup ===
Contributors: simplebackup
Tags: backup, restore, incremental, scheduled, nas
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Free WordPress backup with incremental backups, scheduling, and NAS/local storage. No premium upsells.

== Description ==

SimpleBackup is a clean, lightweight WordPress backup plugin that gives you everything you need without locked "premium" features.

**All features are free:**
- Full and incremental file backups
- Database backups
- Scheduled backups via WP-Cron with time-of-day selection
- Local directory / NAS storage (mount via SMB/NFS)
- One-click restore (full, database-only, or files-only)
- Optional AES-256 encryption
- Retention policies (auto-delete old backups)
- Directory browser for Docker/container environments

**Why SimpleBackup?**
Unlike other backup plugins that lock remote storage, incremental backups, and scheduling behind a paywall, SimpleBackup provides all core functionality for free. Your backups belong to you.

**NAS Setup:**
Mount your NAS share at the OS level (e.g., `/mnt/nas-backups/`) and point SimpleBackup at that directory. No APIs, no OAuth, no vendor lock-in.

== Installation ==

1. Download `simplebackup.zip` from the release page
2. In WordPress, go to **Dashboard > Plugins > Add New > Upload Plugin**
3. Select the `simplebackup.zip` file and click **Install Now**
4. Click **Activate Plugin**
5. Go to **Tools > SimpleBackup**
6. Set your backup directory and schedule
7. Click **Backup Now** to test

== NAS Setup ==

Mount your NAS via SMB or NFS at the OS level, then set the backup directory to the mount path.

**Example (Linux/Docker):**
```bash
sudo mkdir -p /mnt/nas-backups
# Add to /etc/fstab for SMB:
//192.168.1.100/backups  /mnt/nas-backups  cifs  credentials=/etc/nas-creds,_netdev  0  0
```

In SimpleBackup settings, set **Backup Directory** to `/mnt/nas-backups/wordpress/`

== Restore ==

1. Go to **Tools > SimpleBackup**
2. Find your backup in the Existing Backups table
3. Select restore type: **Restore All**, **DB Only**, or **Files Only**
4. Click **Restore** and confirm

**Warning:** Restore will overwrite your current site. Always verify your backup first.

== Scheduling ==

1. Go to **Tools > SimpleBackup > Settings**
2. Check **Enable scheduled backups**
3. Choose interval (hourly, twice daily, daily, weekly)
4. Set time of day (e.g., 02:00 for 2 AM)
5. Save changes

**Note:** On low-traffic sites, replace WP-Cron with a system cron for reliability.

== Docker / Container Notes ==

Use the **System Info** tab to see:
- Current user and permissions
- Common Docker paths and their writable status
- Server time for scheduling alignment

Use the **Test Directory** button to verify your volume mount is accessible before running backups.

== Changelog ==

= 1.1.0 =
* Added time-of-day scheduling
* Added directory browser/tester
* Added System Info tab with Docker path detection
* Added CHANGELOG.md, FRD.md, QA.md

= 1.0.0 =
* Initial release
* Full and incremental backups
* Database export
* Scheduled backups
* Local/NAS storage
* Restore functionality
* Encryption support
