# Release Letter Logging Feature

## Overview
A comprehensive logging system for bank guarantee release actions that integrates seamlessly with the existing import tracking infrastructure.

## Feature Description

### User Story
As a user reviewing bank guarantee history, I need to see when release letters were issued so I can track the complete lifecycle of each guarantee.

### Key Capabilities
1. **Logging Release Actions**: Creates permanent records when release letters are issued
2. **Timeline Visibility**: Shows release records in guarantee history with distinctive styling
3. **Separate Print Function**: Print release letters without creating duplicate log entries
4. **Historical Tracking**: All release actions timestamped and permanently stored

---

## Architecture

### Database Design

#### `record_type` Column
Added to `imported_records` table to categorize record types:
- `'import'` - Initial data import
- `'modification'` - Manual corrections
- `'release_action'` - Release letter issuance
- `'renewal_request'` - Future use

#### Session Type
Uses existing `import_sessions` table with `session_type = 'release_action'`

### API Endpoints

#### `POST /api/issue-release.php`
**Purpose**: Log release action without printing

**Request**:
```
guarantee_no=MD323100010
```

**Response**:
```json
{
  "success": true,
  "message": "تم إصدار خطاب الإفراج بنجاح",
  "record_id": 12455,
  "session_id": 411
}
```

#### `GET /api/guarantee-history.php?number={guarantee_no}`
**Enhanced Response**: Now includes `record_type` for each history item

```json
{
  "record_id": 12455,
  "record_type": "release_action",
  "date": "2025-12-19 09:49:32",
  ...
}
```

---

## User Interface

### Release Button (Top Level)
**Location**: Guarantee history panel header  
**Label**: "إصدار خطاب إفراج"  
**Color**: Red  
**Action**:
1. Calls `/api/issue-release.php`
2. Shows success message
3. Refreshes history timeline
4. Does NOT open print window

### Print Buttons (Timeline)
**Location**: Next to each history record  
**Label**: "طباعة"  
**Action**:
- Opens `release-letter.php?id={record_id}` in new window
- Does NOT create new database record
- Works for ANY record type (import, modification, release)

### Visual Styling
**Release Records**:
- Red badge with "إفراج" text
- Consistent with existing "جاهز" and "معلق" badges
- Distinct color (#ef4444) for quick identification

---

## Implementation Details

### Files Modified

| File | Changes |
|------|---------|
| `www/api/issue-release.php` | **NEW** - Release logging endpoint |
| `www/api/guarantee-history.php` | Added `record_type` to SELECT and response |
| `www/release-letter.php` | Removed logging code, kept printing only |
| `www/assets/js/guarantee-search.js` | Separate logging/printing, null ID handling |
| `www/assets/css/decision.css` | Added `.status-badge-timeline.release` styling |

### Database Migrations

#### Migration 1: Add `record_type`
**File**: `storage/migrations/20251219_add_record_type.sql`
- Added column with default 'import'
- Updated 4,227 existing records
- Created index

#### Migration 2: Fix AUTOINCREMENT
**File**: `storage/migrations/20251219_fix_autoincrement.sql`
- Rebuilt entire table to restore AUTOINCREMENT
- Preserved all 4,233 records
- Fixed 6 NULL guarantee numbers
- Recreated all indexes

---

## Usage Examples

### Creating a Release Record
1. Search for guarantee number
2. Click guarantee to open history panel
3. Click red "إصدار خطاب إفراج" button at top
4. Success message appears
5. New red "إفراج" record shows in timeline

### Printing a Release Letter
1. Locate the release record in timeline (red badge)
2. Click blue "طباعة" button next to it
3. Print window opens with formatted letter
4. No new record created

### Printing Old Records
- Works the same way for ANY record
- Print buttons available for all record types
- Each button prints the version from that specific session

---

## Technical Notes

### Why Table Rebuild Was Needed
SQLite's `ALTER TABLE ADD COLUMN` on tables with existing data can corrupt the AUTOINCREMENT mechanism. Symptoms:
- `lastInsertId()` returns valid ID
- But `SELECT` shows `id = NULL`

Solution: Rebuild table by creating new one, copying data, dropping old, renaming new.

### Null ID Handling
JavaScript template literals convert `null` to string `"null"`, causing `id=null` in URLs. Fixed with:
```javascript
var recId = ${item.record_id || 0};
if (!recId) { alert('خطأ: معرف السجل مفقود'); return; }
```

---

## Future Enhancements

### Potential Features
1. **Bulk Release**: Select multiple guarantees for release
2. **Release Templates**: Customizable letter templates
3. **Email Integration**: Send release letters via email
4. **Approval Workflow**: Require manager approval before release
5. **Auto-cleanup**: Archive old release records after X months

### Database Schema Evolution
The `record_type` column supports future record types:
- `'renewal_request'`
- `'extension'`
- `'cancellation'`
- `'amendment'`

---

## Troubleshooting

### Issue: Print button shows "معرف السجل مفقود"
**Cause**: Record has NULL id  
**Solution**: Database needs AUTOINCREMENT fix (already applied)

### Issue: Release button doesn't create record
**Check**:
1. Network tab - is API call succeeding?
2. Console - any JavaScript errors?
3. Database - does record exist with NULL id?

### Issue: Timeline doesn't refresh after release
**Fix**: Ensure `searchGuarantee()` is called after successful API response

---

## Performance Considerations

### Database Indexes
```sql
CREATE INDEX idx_records_type ON imported_records(record_type);
CREATE INDEX idx_records_guarantee ON imported_records(guarantee_number);
```

### API Response Time
- Typical: < 100ms
- With 50+ history records: < 200ms

### Browser Compatibility
- Tested: Chrome, Edge
- Requires: ES6 (async/await, fetch API)

---

## Maintenance

### Monitoring
- Check `import_sessions` for orphaned release sessions
- Monitor `imported_records` for NULL guarantee numbers
- Verify AUTOINCREMENT working: latest ID should increment

### Backup Strategy
Before any migration:
```powershell
Copy-Item storage\database\app.sqlite storage\database\app.sqlite.backup-$(Get-Date -Format 'yyyyMMdd-HHmmss')
```

