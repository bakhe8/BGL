# Timeline Events System - Complete Documentation

## Overview
The Timeline Events System tracks all modifications to guarantee records, providing a complete audit trail and historical view of changes. This system was one of the most complex features to implement, requiring multiple iterations to handle edge cases correctly.

---

## Table of Contents
1. [Architecture Overview](#architecture-overview)
2. [Data Flow](#data-flow)
3. [Key Components](#key-components)
4. [Event Types](#event-types)
5. [Snapshot Mechanism](#snapshot-mechanism)
6. [Common Pitfalls & Solutions](#common-pitfalls--solutions)
7. [Testing Guidelines](#testing-guidelines)
8. [Future Enhancements](#future-enhancements)

---

## Architecture Overview

### System Design
```
┌─────────────────┐
│  User Action    │
│  (Import/Edit)  │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│  ImportedRecordRepository       │
│  - create()                     │
│  - Initial validation           │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  TimelineEventService           │
│  - logRecordCreation()          │
│  - logStatusChange()            │
│  - captureSnapshot()            │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  TimelineEventRepository        │
│  - create()                     │
│  - Save to DB                   │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│  guarantee_timeline_events      │
│  Database Table                 │
└─────────────────────────────────┘
```

### Database Schema
```sql
-- Timeline Events Table
CREATE TABLE guarantee_timeline_events (
    id INTEGER PRIMARY KEY,
    guarantee_number TEXT NOT NULL,
    record_id INTEGER NOT NULL,
    session_id INTEGER,
    event_type TEXT NOT NULL,  -- 'import', 'status_change', 'extension', etc.
    field_name TEXT,
    old_value TEXT,
    new_value TEXT,
    old_id INTEGER,
    new_id INTEGER,
    supplier_display_name TEXT,
    bank_display TEXT,
    change_type TEXT,
    snapshot_data TEXT,  -- JSON snapshot of record state
    created_at TEXT DEFAULT (datetime('now', 'localtime'))
);
```

---

## Data Flow

### 1. Import Flow (Auto-Match)
```
Excel File Import
      ↓
ImportService processes row
      ↓
ImportedRecordRepository::create()
      ↓
[CHECKPOINT 1] Record inserted into DB
      ↓
Check: recordType === 'import'? YES
      ↓
Build snapshot from $record object (RAW Excel data)
      ↓
TimelineEventService::logRecordCreation($id, $snapshotData)
      ↓
[TIMELINE EVENT 1] "import" event created
      ↓
Check: matchStatus === 'ready' && supplierId exists? YES
      ↓
Fetch official names from suppliers/banks tables
      ↓
Build transformation data:
  - before: {supplier: "SNB", bank: "SNB"}
  - after: {supplier: "شركة...", bank: "البنك الأهلي"}
      ↓
TimelineEventService::logStatusChange($rawNames, $officialNames)
      ↓
[TIMELINE EVENT 2] "status_change" event created
      ↓
RESULT: 2 timeline events
```

### 2. Manual Match Flow
```
User views pending record
      ↓
User selects supplier/bank
      ↓
DecisionController::updateDecision()
      ↓
[CHECKPOINT] Capture snapshot BEFORE update
      ↓
Update record in DB
      ↓
TimelineEventService::logStatusChange()
      ↓
[TIMELINE EVENT] "status_change" created
```

---

## Key Components

### 1. ImportedRecordRepository::create()
**File**: `app/Repositories/ImportedRecordRepository.php`  
**Lines**: 52-144

**Purpose**: Central entry point for all record creation. Handles timeline logging for imports.

**Key Logic**:
```php
// CRITICAL: Only log timeline events for actual imports
if ($record->recordType === 'import') {
    // Build snapshot from $record object (NOT from DB!)
    // This ensures we capture the ORIGINAL Excel data
    $snapshotData = [
        'guarantee_number' => $record->guaranteeNumber,
        'supplier_name' => $record->rawSupplierName,  // RAW from Excel
        'bank_name' => $record->rawBankName,          // RAW from Excel
        // ... other fields
    ];
    
    // Log import event
    $timelineService->logRecordCreation($record->id, 'import', $snapshotData);
    
    // If auto-matched, log matching event
    if ($record->matchStatus === 'ready' && $record->supplierId) {
        // Fetch OFFICIAL names from database
        $supplierOfficialName = /* fetch from suppliers table */;
        $bankOfficialName = /* fetch from banks table */;
        
        // Log transformation
        $timelineService->logStatusChange(
            $record->guaranteeNumber,
            $record->id,
            'pending',
            'ready',
            $record->sessionId,
            ['supplier' => $record->rawSupplierName, 'bank' => $record->rawBankName],
            ['supplier' => $supplierOfficialName, 'bank' => $bankOfficialName]
        );
    }
}
```

**Why This Matters**:
- Extension and Release actions also call `create()` but with different `recordType`
- Without the check, we'd log spurious "import" events for non-imports
- Snapshot must be built from `$record` object, NOT re-fetched from DB (to avoid race conditions)

---

### 2. TimelineEventService::logRecordCreation()
**File**: `app/Services/TimelineEventService.php`  
**Lines**: 567-590

**Purpose**: Log initial creation of a record (import event).

**Signature**:
```php
public function logRecordCreation(
    int $recordId,
    string $recordType,
    ?array $snapshotData = null  // OPTIONAL: Pre-built snapshot
): int
```

**Key Innovation**:
- Accepts optional `$snapshotData` parameter
- If provided, uses it directly (avoids DB re-fetch)
- If not provided, calls `captureSnapshot($recordId)` as fallback

**Why This Design**:
- Original implementation re-fetched from DB: `$snapshot = $this->captureSnapshot($recordId)`
- Problem: Record might not be fully committed or visible yet
- Solution: Pass pre-built snapshot from `$record` object at call site

---

### 3. TimelineEventService::logStatusChange()
**File**: `app/Services/TimelineEventService.php`  
**Lines**: 272-320

**Purpose**: Log when record status changes (pending → ready) during matching.

**Signature**:
```php
public function logStatusChange(
    string $guaranteeNumber,
    int $recordId,
    string $oldStatus,
    string $newStatus,
    ?int $sessionId = null,
    ?array $rawNames = null,      // Excel names (before)
    ?array $officialNames = null  // Official names (after)
): int
```

**Snapshot Structure**:
```php
[
    'guarantee_number' => 'LG...',
    'transformation' => [
        'before' => [
            'supplier' => 'SAUDI BUSINESS MACHINES',  // From Excel
            'bank' => 'SNB'                          // From Excel
        ],
        'after' => [
            'supplier' => 'شركة الأعمال التجارية السعودية',  // Official
            'bank' => 'البنك الأهلي السعودي'                // Official
        ]
    ]
]
```

---

### 4. TimelineEventService::captureSnapshot()
**File**: `app/Services/TimelineEventService.php`  
**Lines**: 62-86

**Purpose**: Capture current state of a record for historical view.

**Special Logic for Import Events**:
```php
if ($record->recordType === 'import' && $record->matchStatus === 'pending') {
    // For pending imports, use RAW names from Excel
    $supplierName = $record->rawSupplierName;
    $bankName = $record->rawBankName;
} else {
    // For matched records, use official names
    $supplierName = $record->supplierDisplayName ?? $record->rawSupplierName;
    $bankName = $record->bankDisplay ?? $record->rawBankName;
}
```

**Why This Matters**:
- Import events should show what came from Excel (raw data)
- Status change events should show official names (after matching)

---

## Event Types

### 1. `import` (Record Creation)
- **When**: New record imported from Excel
- **Snapshot**: Raw Excel data (before any matching)
- **Display**: "استيراد" badge
- **Example**:
  ```
  Supplier: SAUDI BUSINESS MACHINES
  Bank: SNB
  Amount: 100,000.00
  ```

### 2. `status_change` (Matching)
- **When**: Record matched (auto or manual)
- **Snapshot**: Transformation data (before → after)
- **Display**: "مطابقة" badge
- **Example**:
  ```
  match_status: يحتاج قرار ← جاهز
  supplier: SAUDI BUSINESS MACHINES ← شركة الأعمال التجارية السعودية
  bank: SNB ← البنك الأهلي السعودي
  ```

### 3. `extension`
- **When**: Guarantee extended
- **Snapshot**: Record state at extension time
- **Display**: "تمديد" badge

### 4. `release`
- **When**: Guarantee released
- **Snapshot**: Record state at release time
- **Display**: "إفراج" badge

### 5. `supplier_change`
- **When**: Supplier manually changed
- **Display**: Shows old → new supplier

### 6. `bank_change`
- **When**: Bank manually changed
- **Display**: Shows old → new bank

---

## Snapshot Mechanism

### Purpose
Snapshots preserve the state of a record at a specific point in time, enabling:
1. Historical view of letters (print letter as it was)
2. Audit trail (what changed and when)
3. Undo functionality (future enhancement)

### Snapshot Capture Points
1. **Import**: When record first created
2. **Status Change**: When matching occurs
3. **Extension**: When guarantee extended
4. **Release**: When guarantee released
5. **Supplier/Bank Change**: When manually edited

### Snapshot Storage
- Stored as JSON in `snapshot_data` column
- Serialized using `JSON_UNESCAPED_UNICODE` to preserve Arabic text
- Retrieved and parsed by API endpoint

---

## Common Pitfalls & Solutions

### Problem 1: Duplicate Import Events
**Symptom**: Multiple "import" events for same record

**Root Cause**: 
- Extensions and releases also call `ImportedRecordRepository::create()`
- Without filtering, all record types logged as "import"

**Solution**:
```php
if ($record->recordType === 'import') {
    $timelineService->logRecordCreation(...);
}
```

---

### Problem 2: Empty Snapshots
**Symptom**: Historical view shows blank data

**Root Causes**:
1. Re-fetching from DB too early (record not committed)
2. `snapshot_data` column not included in SELECT
3. Snapshot not passed to `create()` method

**Solutions**:
1. Build snapshot from `$record` object, pass directly
2. Add `snapshot_data` to all timeline SELECT queries
3. Modified `logRecordCreation()` to accept pre-built snapshot

---

### Problem 3: Wrong Event Order
**Symptom**: "Matching" event appears before "Import" event

**Root Cause**: 
- Both events created at same timestamp
- `ORDER BY created_at DESC` not deterministic

**Solution**:
```sql
ORDER BY created_at DESC, id DESC
```
This ensures consistent ordering when timestamps are identical.

---

### Problem 4: English Names Instead of Arabic
**Symptom**: Timeline shows "SNB", "RIYAD BANK" instead of Arabic names

**Root Causes**:
1. Using `bankDisplay` field (stores abbreviations)
2. `officialName` property doesn't exist (should be `official_name` column)

**Solution**:
```php
// Fetch directly from DB using column name
$stmt = $pdo->prepare("SELECT official_name FROM banks WHERE id = :id");
$stmt->execute([':id' => $record->bankId]);
$bankOfficialName = $stmt->fetchColumn();
```

---

### Problem 5: Missing Status Change Events for Auto-Match
**Symptom**: Auto-matched imports don't show "مطابقة" event

**Root Cause**: 
- `logStatusChange()` only called in `DecisionController` (manual match)
- Auto-match in `ImportedRecordRepository` didn't call it

**Solution**:
Added conditional logic in `ImportedRecordRepository::create()`:
```php
if ($record->matchStatus === 'ready' && $record->supplierId) {
    // Fetch official names
    // Build transformation data
    $timelineService->logStatusChange(..., $rawNames, $officialNames);
}
```

---

## Testing Guidelines

### Manual Testing Checklist

#### Import Testing
- [ ] Import file with auto-matched records
- [ ] Verify "استيراد" event appears first
- [ ] Verify "مطابقة" event appears second (for ready records)
- [ ] Verify no duplicate events

#### Snapshot Testing
- [ ] Click "عرض السجل التاريخي" on import event
- [ ] Verify letter shows RAW Excel data (not matched names)
- [ ] Click "عرض السجل التاريخي" on match event
- [ ] Verify letter shows official Arabic names

#### Transformation Testing
- [ ] Check "مطابقة" event description
- [ ] Verify shows: `supplier: [Excel] ← [Official]`
- [ ] Verify shows: `bank: [Excel] ← [Official]`
- [ ] Verify Arabic names are correct (not abbreviations)

#### Ordering Testing
- [ ] Import multiple records in same batch
- [ ] Verify events appear in correct chronological order
- [ ] Verify no events appear "out of order"

---

## Code Comments Standards

All modified files should include:

### File Header Comment
```php
/**
 * Timeline Event Logging - [Component Name]
 * 
 * This file handles timeline event tracking for [specific purpose].
 * 
 * Key Concepts:
 * - Snapshots: Preserve record state at event time
 * - Transformation: Show before/after matching
 * - Deduplication: Prevent duplicate events
 * 
 * Related Files:
 * - TimelineEventService.php - Core event logging
 * - guarantee-history.php - Timeline API endpoint
 * 
 * @see docs/timeline-events.md for complete documentation
 */
```

### Critical Section Comments
```php
// CRITICAL: Only log for actual imports (not extensions/releases)
// WHY: Extensions/releases call same create() method but aren't imports
// RESULT: Prevents duplicate/incorrect timeline entries
if ($record->recordType === 'import') {
    // ... logging code
}
```

### Complex Logic Comments
```php
// Build snapshot from $record object (NOT from DB re-fetch)
// 
// RATIONALE:
// - DB re-fetch may not find record yet (transaction timing)
// - $record object has fresh, accurate data
// - Avoids race condition where DB state differs from object state
$snapshotData = [
    'supplier_name' => $record->rawSupplierName,  // RAW Excel data
    // ...
];
```

---

## Future Enhancements

### Planned Features
1. **Undo Functionality**: Use snapshots to revert changes
2. **Diff View**: Show detailed field-by-field changes
3. **Timeline Filtering**: Filter by event type, date range, user
4. **Timeline Search**: Search within timeline events
5. **Export Timeline**: Export audit trail to PDF/Excel
6. **Automated Alerts**: Notify on specific event types

### UI Improvements
- Redesign timeline modal for better aesthetics
- Add timeline visualization (graph/chart)
- Implement infinite scroll for long timelines
- Add event grouping (collapse related events)

---

## Performance Considerations

### Current Implementation
- Timeline events queried per record (not bulk)
- Snapshots stored as JSON (no parsing overhead)
- Limited to 100 events per query (prevents memory issues)

### Optimization Opportunities
1. **Batch Loading**: Load timelines for multiple records
2. **Lazy Loading**: Load snapshots only when viewed
3. **Caching**: Cache timeline data per record
4. **Indexing**: Add composite index on (guarantee_number, created_at)

---

## Troubleshooting

### Debug Checklist
1. Check server logs for "DEBUG" messages
2. Verify `snapshot_data` column has data in DB
3. Inspect API response (`/api/guarantee-history.php?number=XXX`)
4. Check browser console for JavaScript errors
5. Clear OpCache if code changes not reflected

### Common Fixes
- **Empty Timeline**: Check `guarantee_number` matches exactly
- **Wrong Data**: Verify snapshot captured at correct time
- **Missing Events**: Check conditional logic in create() method
- **Order Issues**: Verify ORDER BY includes `id DESC`

---

## References

### Related Documentation
- [Database Schema](../database/schema.md)
- [API Endpoints](../api/endpoints.md)
- [Service Layer Architecture](../architecture/services.md)

### External Resources
- [JSON Snapshot Best Practices](https://example.com/json-snapshots)
- [Audit Trail Patterns](https://example.com/audit-trails)
- [Event Sourcing](https://example.com/event-sourcing)

---

**Document Version**: 1.0  
**Last Updated**: 2025-12-21  
**Maintained By**: Development Team  
**Review Cycle**: Quarterly
