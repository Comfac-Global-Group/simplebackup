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

## 9. Zero-CLI Setup Features (New)

| ID | Feature | Priority | Status | Notes |
|----|---------|----------|--------|-------|
| ZC-01 | **Setup Wizard** | 🔴 High | 📝 Planned | First-run guided setup: what to backup, where to store, schedule |
| ZC-02 | **Built-in FTP/SFTP** | 🔴 High | 📝 Planned | Connect to NAS without OS mount — host, user, pass, port in UI |
| ZC-03 | **WebDAV Support** | 🟡 Medium | 📝 Planned | Many NAS support WebDAV; pure PHP, no OS deps |
| ZC-04 | **Health Check Dashboard** | 🔴 High | 📝 Planned | Pre-flight: disk space, permissions, mysqldump, ZipArchive, cron status |
| ZC-05 | **Test Restore Sandbox** | 🟡 Medium | 📝 Planned | Verify backup integrity without touching live site |
| ZC-06 | **Backup Download** | 🟡 Medium | 📝 Planned | Download ZIP directly from browser |
| ZC-07 | **Email Notifications** | 🟡 Medium | 📝 Planned | Success/failure emails with summary |
| ZC-08 | **Quick Setup Presets** | 🟢 Low | 📝 Planned | "Docker", "Shared Hosting", "VPS" presets with sensible defaults |
| ZC-09 | **Disk Space Warning** | 🔴 High | 📝 Planned | Alert before backup if destination has < 2x estimated size free |
| ZC-10 | **Backup Verification** | 🟡 Medium | 📝 Planned | Auto-verify ZIP integrity after creation |
| ZC-11 | **Remote Storage Test** | 🔴 High | 📝 Planned | "Test Connection" button for FTP/SFTP/WebDAV |
| ZC-12 | **One-Click Migration** | 🟢 Low | 📝 Planned | Export/import settings JSON between sites |
| ZC-13 | **Auto-Detection** | 🟢 Low | 📝 Planned | Detect install type and suggest best paths |
| ZC-14 | **Backup Search/Filter** | 🟢 Low | 📝 Planned | Search backups by date, type, size in UI |
| ZC-15 | **Backup Preview** | 🟢 Low | 📝 Planned | Preview what's inside a ZIP before restore |

---

## Recommended Implementation Order (Zero-CLI Priority)

### Phase 1: Health & Diagnostics (Biggest UX Win)
1. **Health Check Dashboard** — Run on plugin page load, show green/yellow/red status for every requirement
2. **Disk Space Warning** — Pre-backup check with clear alert
3. **Backup Verification** — Auto-check ZIP after creation

### Phase 2: Remote Storage Without Mount
4. **Built-in FTP/SFTP** — Most NAS devices have FTP; no OS mount needed
5. **WebDAV Support** — Pure PHP, works with Nextcloud, ownCloud, many NAS
6. **Remote Storage Test** — "Test Connection" button before saving

### Phase 3: Setup & Polish
7. **Setup Wizard** — Trigger on first activation, guide through everything
8. **Email Notifications** — WP Mail / SMTP integration
9. **Backup Download** — Stream ZIP to browser
10. **Quick Setup Presets** — One-click Docker/shared/VPS config

---

## Future Roadmap

### v1.2.0 (Planned)
- Health Check Dashboard
- Disk Space Warning
- Backup Verification
- Email Notifications

### v1.3.0 (Planned)
- Built-in FTP/SFTP storage
- WebDAV Support
- Remote Storage Test
- Backup Download

### v2.0.0 (Planned)
- Setup Wizard
- S3-compatible storage
- Multisite network support
- Multiple backup destinations
