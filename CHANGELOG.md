# SimpleBackup Changelog

All changes are tracked with timestamp `YYMMDD-HHMMSS` format.

---

## 260429-180455 — v1.2.0 Release

### Added
- **Test Settings** — Validate all settings (paths, permissions, mysqldump, cron, encryption) BEFORE saving. Shows green/yellow/red results.
- **Revert to Saved** — One-click reset of settings form back to last saved values.
- **Test Backup (Dry Run)** — Simulate a backup and see exactly what would be backed up: file count, size estimate, skipped files, without creating any archive.
- **Auto Safety Backup on Restore** — Before every restore, a safety backup is created automatically.
- **Undo Restore** — If restore breaks something, revert back to the pre-restore safety backup with one click.

### Changed
- Restore confirmation now warns that a safety backup will be created first
- Admin UI buttons reorganized: Test Settings, Revert, Save on Settings tab
- Backup tab now shows Dry Run button alongside Backup Now

---

## 260429-181214 — v1.3.0 Release

### Added
- **Health Check Dashboard** — Auto-runs on Backups tab. Shows green/yellow/red status for: backup directory, ZIP extension, mysqldump, cron schedule, PHP memory, max execution time, recent backups, encryption. Instant visual diagnosis.
- **Pre-backup Disk Space Warning** — Before every backup, estimates size and checks if destination has 2x free space. Fails fast with clear message if disk is full.
- **Backup Verification** — After every backup, automatically verifies ZIP integrity: checks manifest.json exists, is valid JSON, database file present, file count matches manifest.

### Changed
- Backup tab now shows Health Check dashboard at the top
- Failed backups now report specific verification errors
- Disk space check prevents wasted time on full destinations

---

## 260429-162445 — v1.1.0 Published

### Released
- GitHub release created: https://github.com/Comfac-Global-Group/simplebackup/releases/tag/v1.1.0
- Download: `simplebackup-v1.1.0.zip` (26 KB)
- Activation: WordPress Dashboard > Plugins > Add New > Upload > Activate > Tools > SimpleBackup

---

## 260429-160400 — v1.1.0 Release

### Added
- **Time-of-day scheduling** — Set exact backup time (e.g. 02:00) instead of just interval
- **Directory browser/tester** — Settings page now has "Test Directory" button showing permissions, free space, and contents
- **System Info tab** — Shows PHP version, WordPress version, MySQL version, current user, server time
- **Docker path checker** — Common Docker paths tested and displayed with writable status
- **CHANGELOG.md** — This file
- **FRD.md** — Feature Requirements Document
- **QA.md** — Known issues and quality tracking

### Changed
- Scheduler now calculates next run based on both interval AND preferred time
- Admin UI reorganized into tabs: Backups, Settings, System Info, Logs
- Restore confirmation dialog made more explicit with "overwrite" warning

### Plans
- 260430-000000: Add email notifications on backup success/failure
- 260501-000000: Add SFTP remote storage option
- 260505-000000: Add backup verification (checksum validation)
- 260510-000000: Multisite network support

---

## 260429-080400 — v1.0.0 Release

### Added
- Initial release
- Full and incremental file backups
- Database backup via mysqldump + PHP fallback
- Scheduled backups via WP-Cron (hourly, twice daily, daily, weekly)
- Local/NAS directory storage
- One-click restore (full, DB only, files only)
- AES-256 encryption support
- Retention policy (auto-delete old backups)
- Per-backup logging
- WordPress admin UI under Tools → SimpleBackup
- GitHub release with downloadable ZIP

### Notes
- First stable release
- All features free, no premium upsells
