# SimpleBackup Changelog

All changes are tracked with timestamp `YYMMDD-HHMMSS` format.

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
