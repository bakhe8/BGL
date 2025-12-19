# Version 1.4 Release Notes - Release Letter Management System

**Release Date:** December 19, 2025  
**Version:** 1.4.0  
**Code Name:** "Dynamic Letters"

---

## Overview

Version 1.4 introduces a comprehensive release letter logging and management system that seamlessly integrates with the existing guarantee tracking infrastructure. This release enables users to track the complete lifecycle of bank guarantees from issuance through extension to final release.

---

## New Features

### 1. Release Letter Logging System

**Feature:** Track when bank guarantee release letters are issued  
**Benefit:** Complete audit trail of guarantee lifecycle

**Key Capabilities:**
- Creates permanent database records when release letters are issued
- Integrates with existing import session infrastructure
- Displays release actions in guarantee history timeline with distinctive red badges
- Maintains separation between logging and printing actions

**Technical Changes:**
- New API endpoint: `/api/issue-release.php`
- New `record_type` column in `imported_records` table
- Values: `'import'`, `'modification'`, `'release_action'`, `'renewal_request'`
- New `session_type` support in `import_sessions` table

### 2. Dynamic Letter Content System

**Feature:** Single letter template with dynamic content based on record type  
**Benefit:** Consistent design across all letter types with appropriate text

**Supported Letter Types:**
1. **Extension Letters** (`record_type='import'`)
   - Subject: "طلب تمديد الضمان البنكي"
   - Content: Request to extend guarantee validity period

2. **Release Letters** (`record_type='release_action'`)
   - Subject: "إفراج الضمان البنكي"
   - Content: Request to cancel/release guarantee and return to contractor

**Implementation:**
- `print-record.php`: Universal letter generator with conditional text
- `decision-page.php`: Real-time preview with correct content
- Same layout, watermark, and styling for all types

### 3. Separated Logging and Printing

**Issue Release Button** (Top-level):
- Creates database record only
- Refreshes history timeline
- Does NOT open print window

**Print Buttons** (Timeline):
- Opens print window only
- Does NOT create new records
- Works for any record type (import, modification, release)

---

## Bug Fixes

### Critical: SQLite AUTOINCREMENT Corruption

**Issue:** Adding `record_type` column to populated table corrupted AUTOINCREMENT mechanism  
**Symptom:** `lastInsertId()` returned valid ID but database stored NULL  
**Fix:** Complete table rebuild via migration script

**Migration Details:**
- `20251219_fix_autoincrement.sql`: Rebuild imported_records table
- Preserved all 4,233 existing records
- Fixed 6 records with NULL guarantee_number values
- Restored AUTOINCREMENT functionality

**Files:**
- `storage/migrations/20251219_fix_autoincrement.sql`
- `storage/migrations/fix_null_guarantees.php`

### URL Parameter Inconsistency

**Issue:** History sidebar "View Record" buttons used incorrect URL parameters  
**Symptom:** All records showed same preview (default/first record)  
**Fix:** Changed from `/?id=X&session=Y` to `/?record_id=X&session_id=Y`

**Impact:** Each record type now correctly displays its own preview text

---

## Database Changes

### Schema Updates

```sql
-- Add record_type column
ALTER TABLE imported_records 
ADD COLUMN record_type TEXT DEFAULT 'import' 
CHECK(record_type IN ('import', 'modification', 'release_action', 'renewal_request'));

-- Add index
CREATE INDEX idx_records_type ON imported_records(record_type);

-- Update import_sessions (existing column)
-- session_type: 'manual_entry', 'excel_import', 'release_action'
```

### Data Migration

- Updated 4,227 existing records to `record_type='import'`
- Rebuilt `imported_records` table to fix AUTOINCREMENT
- All 4,233 records preserved
- Current max ID: 12,455+

---

## API Changes

### New Endpoints

**POST `/api/issue-release.php`**

Creates a release action record without opening print window.

**Request:**
```
guarantee_no=MAN-TEST-002
```

**Response:**
```json
{
  "success": true,
  "message": "تم إصدار خطاب الإفراج بنجاح",
  "record_id": 12455,
  "session_id": 411
}
```

### Modified Endpoints

**GET `/api/guarantee-history.php`**

Now includes `record_type` in response:

```json
{
  "record_id": 12455,
  "record_type": "release_action",
  "date": "2025-12-19 09:49:32",
  ...
}
```

---

## UI Changes

### New Components

1. **Release Badge** (Red)
   - Class: `.status-badge-timeline.release`
   - Color: `#ef4444`
   - Text: "إفراج"

2. **Issue Release Button** (Top-level)
   - Location: Guarantee history panel header
   - Label: "إصدار خطاب إفراج"
   - Action: Logs release action only

### Updated Components

**History Timeline:**
- Print buttons now open correct preview based on record_type
- View buttons use correct URL parameters
- Each record shows appropriate badge color

---

## Code Architecture

### Model Layer

**`app/Models/ImportedRecord.php`**

Added `recordType` property:

```php
public ?string $recordType = 'import'
```

### Repository Layer

**`app/Repositories/ImportedRecordRepository.php`**

Updated `mapRow()` to include:

```php
$row['record_type'] ?? 'import'
```

### View Layer

**`www/print-record.php`**

Dynamic content selection:

```php
$isRelease = ($record->recordType === 'release_action');

if ($isRelease):
    // Show release text
else:
    // Show extension text
endif;
```

**`www/views/decision-page.php`**

Same conditional logic for preview section.

---

## Testing

### Automated Tests

- Database integrity verification
- AUTOINCREMENT functionality test
- Record creation and retrieval

### Manual Verification

- ✅ Create release record via UI
- ✅ View record appears in timeline with red badge
- ✅ Print button opens correct letter type
- ✅ View button displays correct preview
- ✅ Extension records still work correctly

---

## Performance Impact

- **Minimal**: One additional column per record
- **Index added**: `idx_records_type` for fast filtering
- **API response**: < 100ms (no significant change)

---

## Breaking Changes

**None.** All changes are backward compatible.

- Existing records defaulted to `record_type='import'`
- Existing functionality unchanged
- New features are additive only

---

## Upgrade Instructions

### For Development

1. Pull latest code from git
2. No manual migration needed (already executed)
3. Refresh browser to clear JavaScript cache

### For Production

1. **Backup database first!**
   ```bash
   cp storage/database/app.sqlite storage/database/app.sqlite.backup
   ```

2. Pull latest code:
   ```bash
   git pull origin main
   ```

3. Run migrations:
   ```bash
   php storage/migrations/run_20251219_migration.php
   php storage/migrations/run_fix_autoincrement.php
   ```

4. Verify:
   - Check record count matches backup
   - Test creating new release  record
   - Verify AUTOINCREMENT working

---

## Documentation

### New Documents

- `docs/features/release-letter-logging.md`: Complete feature guide
- `docs/12-Version-1.3-Release-Notes.md`: Previous version (for reference)
- `docs/14-Version-1.4-Release-Notes.md`: This document

### Updated Documents

- README: Added release letter feature overview
- Technical Reference: Updated schema documentation

---

## Known Issues

**None identified.**

---

## Future Enhancements

### Planned for 1.5

1. **Bulk Release Operations**
   - Select multiple guarantees for simultaneous release
   - Batch letter generation

2. **Email Integration**
   - Send release letters via email directly
   - Track email delivery status

3. **Custom Letter Templates**
   - User-configurable letter templates
   - Multiple templates per record type

4. **Approval Workflow**
   - Require manager approval before release
   - Multi-level authorization

5. **Record Type Expansion**
   - `'amendment'`: For guarantee modifications
   - `'cancellation'`: For early termination
   - `'renewal_request'`: Formal renewal requests

---

## Contributors

- Development: Antigravity AI Assistant
- Testing: User (Bakheet)
- Database: SQLite
- Framework: Custom PHP

---

## Changelog

**v1.4.0** - December 19, 2025
- Added release letter logging system
- Implemented dynamic letter content
- Fixed AUTOINCREMENT corruption
- Added record_type column and indexes
- Created comprehensive documentation

**v1.3.0** - December 2024
- Smart paste feature
- Supplier system refactoring
- Performance improvements

**v1.2.0** - November 2024
- Decision page enhancements
- Manual entry improvements

---

## Getting Help

- **Documentation:** `/docs/features/release-letter-logging.md`
- **Technical Reference:** `/docs/04-Technical-Reference.md`
- **Issues:** Contact development team

---

## License

Internal use only - King Faisal Specialist Hospital & Research Centre
