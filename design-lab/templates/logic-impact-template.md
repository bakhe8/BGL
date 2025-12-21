# Logic Impact Note Template

**ID:** LIN-XXX  
**Related Design Finding:** DF-XXX  
**Date:** YYYY-MM-DD  
**Analyst:** [اسمك]

## Current Logic (As-Is)

**وصف المنطق الحالي:**
- الخطوة الحالية 1
- الخطوة الحالية 2
- التبعيات الموجودة

**Diagram (اختياري):**
```
[يمكن استخدام mermaid أو وصف نصي]
```

## Design Requirement (To-Be)

**ما يريده التصميم الجديد:**
- الخطوة المقترحة 1
- الخطوة المقترحة 2
- السلوك المطلوب

**Diagram (اختياري):**
```
[التدفق الجديد]
```

## Required Backend Changes

### Change 1: [عنوان التغيير]

**Current:**
```
[الكود/السلوك الحالي]
```

**Required:**
```
[الكود/السلوك المطلوب]
```

**Impact:** Type A/B/C (اختر واحد)
- Type A: Logic Addition (backward compatible)
- Type B: Behavioral Change (may break)
- Type C: Breaking Change (definitely breaks)

### Change 2: [عنوان التغيير]

[نفس الصيغة]

## Impact Classification

| Area | Level (None/Low/Medium/High/Critical) | Reason |
|------|---------------------------------------|--------|
| Database | ... | ... |
| API | ... | ... |
| Business Logic | ... | ... |
| Security | ... | ... |
| Performance | ... | ... |

**Overall Risk:** **NONE / LOW / MEDIUM / HIGH / CRITICAL**

## Edge Cases to Handle

1. **[حالة خاصة 1]**
   - التعامل المقترح

2. **[حالة خاصة 2]**
   - التعامل المقترح

## Dependencies

**[اسم Dependency]** - [سبب الحاجة]

أو:

**None** - This is self-contained.

## Testing Requirements

- [ ] Unit tests for [...]
- [ ] Integration tests for [...]
- [ ] Regression tests for [...]
- [ ] Performance tests for [...]
- [ ] Security tests for [...]

## Rollback Plan

```
[كيف نعود للوضع السابق فوراً]
```

---

**Status:** Analyzed  
**Risk:** [LEVEL]  
**Next:** Awaiting Decision Record
