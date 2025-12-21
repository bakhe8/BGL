# Schema Impact Analysis: decision_source Field

**SIA-001**  
**Date:** 2025-12-21  
**Related:** LIN-001 (Logic Impact), DR-001 (Decision Record)  
**Risk Level:** 🟢 **LOW** (Additive change)

---

## Current Schema

### Table: `imported_records`

```sql
CREATE TABLE imported_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier TEXT,
    bank TEXT,
    amount REAL,
    match_status TEXT,  -- 'approved', 'rejected', 'extended', 'hold'
    
    -- Decision tracking (current)
    decision_date DATETIME,
    decided_by TEXT,
    
    -- Other fields...
    created_at DATETIME,
    updated_at DATETIME
);
```

**Observations:**
- ✅ نعرف **ماذا** تم القرار (`match_status`)
- ✅ نعرف **متى** (`decision_date`)
- ✅ نعرف **من** (`decided_by`)
- ❌ **لا نعرف كيف** (AI quick? AI manual? Manual?)

---

## Proposed Change

### Add Column: `decision_source`

```sql
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';
```

**Values:**
- `'ai_quick'` - موافقة سريعة من توصية AI (1 click)
- `'ai_manual'` - اختيار يدوي لتوصية AI
- `'manual'` - اختيار يدوي مختلف عن AI
- `'manual'` (default) - قرارات قديمة قبل الميزة

**Nullable:** No (has DEFAULT)  
**Type:** TEXT  
**Indexed:** Optional (for analytics)

---

## Why This Change?

### Business Reasons:

1. **Track AI Adoption**
   - كم % من القرارات تستخدم Quick Approve؟
   - هل المستخدمون يثقون في AI؟

2. **Measure AI Accuracy**
   - عندما AI توصي بـ X والمستخدم يختار Y
   - نستطيع قياس: متى AI صحيحة؟ متى خاطئة؟

3. **Improve AI Over Time**
   - Cases where user overrides AI
   - → Feed back to learning system
   - → AI becomes smarter

4. **Analytics & Reporting**
   - تقارير: "AI ساعدت في 80% من القرارات"
   - مقارنة: وقت القرار حسب المصدر

### Technical Reasons:

1. **Audit Trail Enhancement**
   - Timeline يصبح أكثر فائدة
   - نعرف السياق: AI أم يدوي؟

2. **Feature Monitoring**
   - track Quick Decision feature usage
   - A/B testing support

3. **Future Extensibility**
   - مستقبلاً: مصادر أخرى (API, Bulk, etc)

---

## Affected Areas

### 1. Backend

**Files:**
- `app/Controllers/DecisionController.php`
  - يحتاج قراءة `decision_source` من request
  - يحتاج كتابتها في database

**Impact:** Minor (add parameter)

**Migration Needed:** Yes (Dual-Write)

---

### 2. API

**Endpoint:** `POST /api/decisions/save.php`

**Current Request:**
```json
{
  "record_id": 14002,
  "match_status": "approved"
}
```

**New Request:**
```json
{
  "record_id": 14002,
  "match_status": "approved",
  "source": "ai_quick"  // ← NEW (optional)
}
```

**Impact:** Backward compatible (optional parameter)

---

### 3. Frontend

**New Code:**
- `design-lab/assets/js/ai-first.js`
  - يرسل `source` parameter

**Existing Code:**
- لا يحتاج تغيير (backward compatible)

**Impact:** None on existing code

---

### 4. Database

**Tables Affected:**
- `imported_records` - يحتاج عمود جديد

**Indexes:**
```sql
-- Optional: for analytics queries
CREATE INDEX idx_decision_source 
ON imported_records(decision_source);
```

**Impact:** Low (index is optional)

---

### 5. Reports

**Existing Reports:**
- ✅ لا تتأثر (العمود nullable مع default)

**New Reports:**
- Decision Source Distribution
- AI Accuracy Report
- Quick Approve Usage

**Impact:** New capabilities (no breakage)

---

### 6. Timeline / Audit

**TimelineEventService:**
- يمكنه تسجيل `decision_source` في snapshot

**Impact:** Enhancement (not breaking)

---

## Risk Assessment

### 🟢 Overall Risk: **LOW**

| Risk Factor | Level | Reason |
|-------------|-------|--------|
| Data Loss | None | Additive only |
| Breaking Change | None | Backward compatible |
| Performance | Low | Single column, optional index |
| Rollback Difficulty | Very Low | DROP COLUMN easy |
| User Impact | None | Transparent to users |

---

## Migration Strategy

### Type: 🟢 **Additive (Type 1)**

**Why Safe:**
- لا يؤثر على بيانات موجودة
- لا يكسر كود قديم
- Rollback سهل

### Phases:

**Phase 1: Add Column** (1 hour)
```sql
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';
```

**Phase 2: Dual-Write** (immediate)
```php
// New code writes to decision_source
if (isset($payload['source'])) {
    $record->decision_source = $payload['source'];
}
```

**Phase 3: Backfill** (optional, can be skipped)
```sql
-- All existing records already have default 'manual'
-- No backfill needed
```

**Phase 4: Enable Feature** (controlled)
```php
// Via feature flag
if (FeatureFlags::isEnabled('QUICK_DECISION')) {
    // Frontend sends 'source' parameter
}
```

---

## Backward Compatibility

### Old Clients (don't send 'source'):

```php
// Backend handles gracefully
$source = $payload['source'] ?? 'manual';  // ← defaults

$record->decision_source = $source;  // ← always works
```

**Result:** 
- ✅ Old requests still work
- ✅ default to 'manual'
- ✅ No errors

### New Clients, Old Schema:

```php
// If column doesn't exist yet
if (schemaHasColumn('imported_records', 'decision_source')) {
    $record->decision_source = $source;
}
// else: silently skip (temporary)
```

**Result:**
- ✅ Deployment order flexible
- ✅ No crashes

---

## Rollback Plan

### If Needed:

**Step 1: Disable Feature**
```php
FeatureFlags::disable('QUICK_DECISION');
```

**Step 2: Stop Writing**
```php
// Remove decision_source from saves
// (optional - can just leave it)
```

**Step 3: Drop Column** (if truly needed)
```sql
ALTER TABLE imported_records 
DROP COLUMN decision_source;
```

**Data Loss:** None (column is optional)  
**Downtime:** None  
**Complexity:** Very Low

---

## Testing Requirements

### Unit Tests:

```php
test_saves_decision_source_when_provided()
test_defaults_to_manual_when_not_provided()
test_accepts_all_valid_sources()
test_rejects_invalid_sources()
```

### Integration Tests:

```php
test_decision_source_stored_in_database()
test_decision_source_in_timeline_snapshot()
test_analytics_query_by_source()
```

### Migration Tests:

```sql
-- Test default value
INSERT INTO imported_records (...) VALUES (...);
SELECT decision_source FROM imported_records WHERE id = LAST_INSERT_ID();
-- Should return 'manual'
```

---

## Monitoring

### Metrics to Track:

```sql
-- Distribution
SELECT decision_source, COUNT(*) 
FROM imported_records 
WHERE decision_date >= '2025-12-21'
GROUP BY decision_source;

-- Expected:
-- ai_quick: 70%
-- ai_manual: 15%
-- manual: 15%
```

### Alerts:

- 🚨 If `decision_source` is NULL (shouldn't happen)
- ⚠️ If 'ai_quick' usage < 50% (adoption issue)

---

## Dependencies

### None New:

- ✅ No new libraries
- ✅ No new services
- ✅ No schema dependencies

### Existing (used):

- Timeline (enhanced with source)
- Analytics (new queries possible)

---

## Performance Impact

### Storage:

```
1 million records × ~10 bytes/value = ~10 MB
```

**Impact:** Negligible

### Queries:

**Without Index:**
- SELECT with WHERE decision_source: Full scan (acceptable)

**With Optional Index:**
- SELECT with WHERE decision_source: Index scan (fast)

**Recommendation:** Add index if analytics queries become frequent

---

## Future Considerations

### Extensibility:

```sql
-- Future sources might include:
'api'        -- من API خارجي
'bulk'       -- import جماعي
'automated'  -- مؤتمت بالكامل
'manual'     -- يدوي
```

**This field supports future expansion**

### Analytics Dashboard:

可以 بناء dashboard يعرض:
- AI adoption over time
- AI accuracy per supplier/bank
- Time saved by Quick Approve

---

## Approval Checklist

- [x] Schema change documented
- [x] Risk assessed (LOW)
- [x] Migration plan defined
- [x] Rollback plan exists
- [x] Backward compatibility ensured
- [x] Testing strategy defined
- [x] Monitoring plan exists
- [x] No breaking changes
- [x] No data loss risk

---

## Decision

**Recommendation:** ✅ **APPROVE**

**Rationale:**
- Risk is minimal (additive)
- Rollback is trivial
- Value is high (analytics + AI improvement)
- No breaking changes

**Conditions:**
- Feature flag must be used
- Monitor adoption metrics
- Optional index if needed later

---

**Status:** Ready for Migration Plan

**Next:** `migration-plans/add-decision-source.md`
