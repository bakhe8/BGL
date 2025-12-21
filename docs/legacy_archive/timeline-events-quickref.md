# Timeline Events - Quick Reference Guide

## For Developers

### What is This?
The Timeline Events System tracks all changes to guarantee records, creating an audit trail.

### Quick Facts
- **Database Table**: `guarantee_timeline_events`
- **Core Service**: `TimelineEventService.php`
- **Entry Point**: `ImportedRecordRepository::create()`
- **API Endpoint**: `/api/guarantee-history.php`

---

## Event Types Cheat Sheet

| Event Type | Badge | When It Happens |
|---|---|---|
| `import` | استيراد | Record imported from Excel |
| `status_change` | مطابقة | Auto or manual matching |
| `extension` | تمديد | Guarantee extended |
| `release` | إفراج | Guarantee released |
| `supplier_change` | تعديل المورد | Supplier changed |
| `bank_change` | تعديل البنك | Bank changed |

---

## Common Tasks

### Add a New Event Type

1. **Add to TimelineEventService**:
```php
public function logMyEvent(string $guaranteeNumber, int $recordId, ...): int {
    $snapshot = $this->captureSnapshot($recordId);
    return $this->timeline->create([
        'guarantee_number' => $guaranteeNumber,
        'record_id' => $recordId,
        'event_type' => 'my_event',
        'snapshot_data' => json_encode($snapshot, JSON_UNESCAPED_UNICODE)
    ]);
}
```

2. **Add badge in `guarantee-history.php`**:
```php
function getEventBadge(string $eventType): string {
    $badges = [
        'my_event' => 'بادج جديد',
        // ... existing badges
    ];
    return $badges[$eventType] ?? $eventType;
}
```

### Debug Timeline Issues

```php
// 1. Check if event was created
SELECT * FROM guarantee_timeline_events 
WHERE guarantee_number = 'LG...' 
ORDER BY created_at DESC;

// 2. Check snapshot data
SELECT 
    id,
    event_type,
    snapshot_data,
    created_at
FROM guarantee_timeline_events 
WHERE record_id = 123;

// 3. Enable debug logging
error_log("Timeline: " . json_encode($snapshot));
```

### Test Timeline Feature

```php
// scripts/test_timeline.php
require 'app/Support/autoload.php';

$service = new \App\Services\TimelineEventService();

// Create test event
$service->logRecordCreation(1, 'import', [
    'guarantee_number' => 'TEST123',
    'supplier_name' => 'Test Supplier'
]);

// Verify created
$db = \App\Support\Database::connection();
$stmt = $db->query("SELECT * FROM guarantee_timeline_events WHERE guarantee_number = 'TEST123'");
print_r($stmt->fetchAll());
```

---

## Critical Rules

### ✅ DO

- **Always pass snapshot data** when calling `logRecordCreation()`
- **Build snapshot from $record object**, not DB re-fetch
- **Check recordType** before logging import events
- **Use JSON_UNESCAPED_UNICODE** when encoding Arabic text
- **Include guarantee_number** in all timeline events

### ❌ DON'T

- **Don't re-fetch from DB** for snapshot (race condition)
- **Don't log import events** for extensions/releases
- **Don't forget to restart server** after code changes (OpCache)
- **Don't use bankDisplay** (use official_name from DB)
- **Don't modify old events** (append new ones instead)

---

## Troubleshooting

### Timeline is Empty
```sql
-- Check if events exist
SELECT COUNT(*) FROM guarantee_timeline_events 
WHERE guarantee_number = 'LG...';

-- If 0, check if logging is happening
-- Add error_log in ImportedRecordRepository::create()
```

### Snapshot Data is Empty
```php
// Check snapshot_data column
SELECT id, snapshot_data FROM guarantee_timeline_events 
WHERE id = 123;

// If NULL, verify TimelineEventRepository::create() includes:
// 'snapshot_data' => $data['snapshot_data'] ?? null
```

### Events in Wrong Order
```sql
-- Verify ordering query
SELECT * FROM guarantee_timeline_events 
WHERE guarantee_number = 'LG...'
ORDER BY created_at DESC, id DESC;  -- Both columns!
```

### Duplicate Import Events
```php
// Check conditional in ImportedRecordRepository::create()
if ($record->recordType === 'import') {  // Must exist!
    $timelineService->logRecordCreation(...);
}
```

---

## File Map

```
app/
├── Repositories/
│   └── ImportedRecordRepository.php    ← Entry point (create method)
├── Services/
│   └── TimelineEventService.php       ← Core logging logic
└── Models/
    └── ImportedRecord.php             ← Data model

www/
└── api/
    └── guarantee-history.php          ← Timeline API endpoint

docs/
└── timeline-events.md                 ← Full documentation
```

---

## API Response Format

```json
{
  "success": true,
  "history": [
    {
      "id": 123,
      "event_type": "status_change",
      "badge": "مطابقة",
      "description": "match_status: يحتاج قرار ← جاهز\nsupplier: SNB ← شركة...",
      "date": "2025-12-21 05:00:00",
      "snapshot": {
        "transformation": {
          "before": {"supplier": "SNB", "bank": "SNB"},
          "after": {"supplier": "شركة...", "bank": "البنك..."}
        }
      }
    }
  ]
}
```

---

## Performance Tips

1. **Limit timeline queries**: Use `LIMIT 100`
2. **Index on guarantee_number**: Already indexed
3. **Lazy-load snapshots**: Only fetch when "عرض السجل التاريخي" clicked
4. **Cache API responses**: Consider Redis for frequent access

---

## Questions?

- Read full docs: `docs/timeline-events.md`
- Check code comments in `ImportedRecordRepository.php`
- Test with: `scripts/test_timeline.php`
- Ask team lead if stuck!
