# SimpleBackup — Quality Assurance & Known Issues

**Version:** 1.1.0  
**Date:** 260429-160400  
**Status:** Active Tracking

---

## Known Issues

| ID | Severity | Issue | Workaround | Status |
|----|----------|-------|------------|--------|
| QA-01 | Medium | Restore may fail on very large databases (>500MB) with PHP fallback | Use mysqldump binary if available; increase PHP memory limit | Open |
| QA-02 | Low | Incremental backup does not detect deleted files | Deleted files remain in newer backups; run periodic full backup to clean | Open |
| QA-03 | Medium | Encryption requires ZipArchive with EM_AES_256 (PHP 7.2+) or system `zip` command | Ensure PHP 7.2+ or install zip/unzip packages | Open |
| QA-04 | Low | WP-Cron may delay backups on low-traffic sites | Replace WP-Cron with system cron (see docs) | Documented |
| QA-05 | Medium | File restore overwrites existing files without backup | Manual backup before restore recommended | Open |
| QA-06 | Low | Directory tester shows only first 30 items | Use to verify path is correct, not for full browsing | By Design |

---

## Testing Checklist

### Backup Testing
- [x] Full backup completes without errors
- [x] Incremental backup only includes changed files
- [x] Database export works via mysqldump
- [x] Database export falls back to PHP method
- [x] ZIP archive created with correct structure
- [x] Manifest JSON contains accurate file metadata
- [x] Backup log written successfully

### Restore Testing
- [x] Full restore restores database + files
- [x] Database-only restore works
- [x] Files-only restore works
- [x] Restore confirmation dialog appears
- [x] Large backup restore tested (up to 1GB)

### Scheduling Testing
- [x] WP-Cron event registered on activation
- [x] WP-Cron event cleared on deactivation
- [x] Interval changes update cron schedule
- [x] Time-of-day scheduling calculates next run correctly

### Storage Testing
- [x] Default local directory created and protected
- [x] Custom directory path accepted
- [x] Directory browser shows permissions correctly
- [x] Docker paths detected and tested

### Security Testing
- [x] Backup directory protected by .htaccess
- [x] Encryption produces valid password-protected ZIP
- [x] Only manage_options users can access plugin
- [x] Nonce verification on all destructive actions

---

## Compatibility Matrix

| Environment | PHP | MySQL | Status |
|-------------|-----|-------|--------|
| Standard LAMP | 7.4+ | 5.7+ | ✅ Tested |
| Docker WordPress | 8.1 | 8.0 | ✅ Tested |
| Docker + NAS mount | 8.1 | 8.0 | ✅ Tested |
| Shared hosting | 7.4 | 5.7 | ✅ Expected |
| Windows IIS | 8.0 | N/A | ⚠️ Not tested |

---

## Bug Reports

### How to Report
1. Check this QA.md first — your issue may be known
2. Include: WordPress version, PHP version, server OS
3. Include: Backup directory path and permissions
4. Include: Relevant log file contents
5. File issue at: https://github.com/Comfac-Global-Group/simplebackup/issues

### Template
```
**Version:** SimpleBackup X.X.X
**WordPress:** X.X.X
**PHP:** X.X.X
**Environment:** Docker / Shared Hosting / VPS
**Issue:** [Description]
**Steps to Reproduce:**
1. 
2. 
3. 
**Expected:** 
**Actual:** 
**Logs:** [paste relevant log content]
```
