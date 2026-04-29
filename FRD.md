# SimpleBackup — Feature Requirements Document

**Version:** 1.1.0  
**Date:** 260429-160400  
**Status:** Active Development

---

## 1. Core Backup Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| BK-01 | Full database backup | ✅ Done | mysqldump preferred, PHP fallback |
| BK-02 | Full file backup | ✅ Done | Themes, plugins, uploads, core, custom dirs |
| BK-03 | Incremental file backup | ✅ Done | Compares mtimes against last manifest |
| BK-04 | ZIP archive output | ✅ Done | Single ZIP per backup with db/ + files/ + manifest.json |
| BK-05 | Backup manifest | ✅ Done | JSON metadata: timestamp, type, file list, hashes |
| BK-06 | Manual backup trigger | ✅ Done | "Backup Now" button in admin |

## 2. Scheduling Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| SC-01 | WP-Cron integration | ✅ Done | Uses WordPress native scheduling |
| SC-02 | Interval selection | ✅ Done | Hourly, twice daily, daily, weekly |
| SC-03 | Time-of-day selection | ✅ Done | e.g. "Run daily at 02:00" |
| SC-04 | System cron instructions | 📝 Planned | Documentation for replacing WP-Cron |
| SC-05 | Email notifications | 📝 Planned | Success/failure alerts |

## 3. Storage Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| ST-01 | Local directory storage | ✅ Done | Any absolute path |
| ST-02 | NAS via OS mount | ✅ Done | SMB/NFS mounted paths |
| ST-03 | Directory browser/tester | ✅ Done | Shows permissions, space, contents |
| ST-04 | SFTP remote storage | 📝 Planned | Native SFTP without OS mount |
| ST-05 | S3-compatible storage | 📝 Planned | MinIO, Wasabi, etc. |
| ST-06 | Multiple destinations | 📝 Planned | Backup to multiple locations simultaneously |

## 4. Restore Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| RS-01 | Full restore | ✅ Done | Database + files from backup ZIP |
| RS-02 | Database-only restore | ✅ Done | Import SQL without touching files |
| RS-03 | Files-only restore | ✅ Done | Extract files without touching DB |
| RS-04 | Selective file restore | 📝 Planned | Restore individual directories |
| RS-05 | Pre-restore dry run | 📝 Planned | Preview what will change |

## 5. Security Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| SEC-01 | Backup directory protection | ✅ Done | .htaccess + index.php deny access |
| SEC-02 | AES-256 ZIP encryption | ✅ Done | Password-protected archives |
| SEC-03 | Role-based access | ✅ Done | Requires manage_options capability |
| SEC-04 | Secure credential storage | ✅ Done | Passwords in wp_options (WordPress standard) |

## 6. Management Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| MG-01 | Retention policy | ✅ Done | Keep N backups, auto-delete older |
| MG-02 | Per-backup logging | ✅ Done | Individual .log files per backup |
| MG-03 | Backup listing | ✅ Done | Table view with size, date, type |
| MG-04 | Backup deletion | ✅ Done | Single-click delete with confirmation |
| MG-05 | Settings export/import | 📝 Planned | JSON export of plugin settings |
| MG-06 | Backup verification | 📝 Planned | Checksum validation post-backup |

## 7. Docker / Container Support

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| DOC-01 | Path awareness | ✅ Done | System Info shows common Docker paths |
| DOC-02 | Volume mount detection | ✅ Done | Tests /mnt/, /backup/, /data/ paths |
| DOC-03 | Container user detection | ✅ Done | Shows current user in System Info |

## 8. UI/UX Features

| ID | Feature | Status | Notes |
|----|---------|--------|-------|
| UI-01 | Tabbed interface | ✅ Done | Backups, Settings, System Info, Logs |
| UI-02 | Real-time directory tester | ✅ Done | AJAX directory browser |
| UI-03 | Responsive design | ✅ Done | WordPress admin CSS |
| UI-04 | Dark mode support | 📝 Planned | WordPress admin dark mode |

---

## Future Roadmap

### v1.2.0 (Planned)
- Email notifications
- Backup verification checksums
- Settings export/import

### v1.3.0 (Planned)
- SFTP remote storage
- Pre-restore dry run
- Selective file restore

### v2.0.0 (Planned)
- S3-compatible storage
- Multisite network support
- Multiple backup destinations
