# SchemaLab - مختبر قاعدة البيانات

## ⚠️ لماذا SchemaLab مختلف؟

**SchemaLab ليس مثل DesignLab أو LogicLab.**

تغييرات قاعدة البيانات:
- ❌ **غير سهلة التراجع**
- ❌ **تؤثر على كل النظام**
- ❌ **قد تفسد بيانات تاريخية**
- ❌ **قد تكسر تقارير وتكاملات**

**لذلك: SchemaLab أكثر صرامة.**

---

## القاعدة الذهبية

> **لا تغيير في قاعدة البيانات بدون مرحلة "تحييد المخاطر"**

```
غيّر السلوك أولًا،
ثم غيّر المنطق،
ثم غيّر البيانات،
ولا تعكس الترتيب أبدًا.
```

---

## متى تستخدم SchemaLab؟

### ✅ استخدم SchemaLab إذا كان القرار يتضمن:

- [ ] إضافة حقل جديد
- [ ] حذف حقل موجود
- [ ] تغيير معنى قيمة
- [ ] إضافة جدول جديد
- [ ] إعادة تطبيع / دمج جداول
- [ ] تأثير على بيانات قديمة
- [ ] تغيير نوع بيانات (type)
- [ ] تغيير constraints (null, unique, etc)

**إذا أجبت بـ "نعم" على أي سؤال → SchemaLab إلزامي**

### ❌ لا تستخدم SchemaLab إذا:

- تغيير منطق بدون مس البيانات → `LogicLab`
- تغيير UI فقط → `DesignLab`
- إضافة feature flag → `LogicLab`

---

## البنية الداخلية

```
schema-lab/
├── README.md              ← البوصلة (أنت هنا)
├── impact/                ← Schema Impact Analysis
├── migration-plans/       ← خطط الترحيل الآمن
├── simulations/           ← محاكاة Dual-Write/Read
├── dual-phases/           ← توثيق مراحل التشغيل المزدوج
└── decisions/             ← قرارات Schema (أخطر من Logic)
```

### 1. impact/ - تحليل التأثير على Schema

**الهدف:** فصل المنطق عن البيانات

**محتوى:**
- Current Schema (كما هو)
- Proposed Change (ما سيتغير)
- Why (لماذا؟)
- Affected Areas (API, Reports, UI)
- Risk Level (🟢 🟡 🔴)

**⚠️ بدون هذا الملف → لا تنفيذ**

### 2. migration-plans/ - خطط الترحيل

**الهدف:** خطة تنفيذ مرحلية (ليست كود بعد)

**محتوى:**
- Phase 1: إضافة
- Phase 2: تشغيل مزدوج
- Phase 3: Cutover
- Phase 4: Cleanup

**⚠️ كل Phase قابلة للإيقاف**

### 3. simulations/ - المحاكاة

**الهدف:** اختبار استراتيجية Dual-Write/Read

**محتوى:**
- كيف يعمل الكتابة المزدوجة؟
- كيف تُقرأ البيانات؟
- ماذا لو فشلت الكتابة في الجديد؟
- كيف يتم Rollback؟

### 4. dual-phases/ - مراحل التشغيل المزدوج

**الهدف:** توثيق كل مرحلة انتقالية

**محتوى:**
- Phase log
- Metrics
- Issues found
- Rollback triggers

### 5. decisions/ - قرارات Schema

**الهدف:** قرارات أخطر من Logic Decisions

**محتوى:**
- Schema Decision Record (SDR-XXX)
- أكثر صرامة من DR
- يتطلب موافقات إضافية

---

## المسار الكامل (من الفكرة للتنفيذ)

```
DesignLab (DF-001)
   ↓ discovers need
LogicLab (logic analysis)
   ↓ realizes schema change needed
SchemaLab (schema analysis)
   ↓ creates migration plan
Decision Record (SDR-XXX)
   ↓ approved with conditions
Safe Migration (Phase 1-4)
   ↓ dual-write/read
Cutover
   ↓ switch to new
Cleanup (after weeks)
   ↓ remove old
```

**لا خطوة تُتخطى.**

---

## تصنيف التغييرات (Risk Levels)

### 🟢 Type 1: Additive (الأكثر أماناً)

**ما هو:**
- إضافة جدول جديد
- إضافة عمود nullable
- إضافة index

**لماذا آمن:**
- لا يؤثر على البيانات الموجودة
- سهل التراجع (حذف الجديد)
- لا يكسر الكود القديم

**مثال:**
```sql
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';
```

**Rollback:**
```sql
ALTER TABLE imported_records 
DROP COLUMN decision_source;
```

---

### 🟡 Type 2: Dual-Phase (يحتاج حذر)

**ما هو:**
- عمود جديد يحل محل قديم
- تغيير معنى قيمة
- إعادة هيكلة

**لماذا خطر:**
- يحتاج Dual-Write
- قد يربك المطورين
- يحتاج مراقبة دقيقة

**مثال:**
```sql
-- Phase 1: Add new
ALTER TABLE records ADD COLUMN status_v2 TEXT;

-- Phase 2: Dual-write (في الكود)
-- write to both status and status_v2

-- Phase 3: Cutover
-- read from status_v2 only

-- Phase 4: Cleanup (بعد أسابيع)
ALTER TABLE records DROP COLUMN status;
```

**Rollback:** ممكن في Phase 1-2، صعب في Phase 3-4

---

### 🔴 Type 3: Destructive (الأخطر)

**ما هو:**
- حذف عمود
- تغيير نوع بيانات (type)
- حذف جدول

**لماذا خطر جداً:**
- قد يفقد بيانات
- صعب التراجع
- قد يكسر أشياء غير متوقعة

**القاعدة:**
> **لا حذف ولا تغيير معنى إلا بعد فترة تشغيل مزدوجة**

**مثال (ممنوع مباشرة):**
```sql
-- ❌ NEVER do this directly
ALTER TABLE records DROP COLUMN old_field;
```

**الطريقة الصحيحة:**
1. Stop writing to `old_field` (feature flag)
2. Monitor for 2 weeks
3. Verify no reads from `old_field`
4. Backup database
5. Then (and only then): DROP

---

## Dual-Write / Dual-Read Strategy

### المفهوم الأساسي

**خلال الانتقال:**
- النظام يكتب في القديم **و** الجديد
- النظام يقرأ من الجديد (إن وُجد)، وإلا من القديم

**الفائدة:**
- لا بيانات تُفقد
- التراجع سهل
- الانتقال تدريجي

### مثال عملي:

```php
// Phase 1: Dual-Write
function saveDecision($data) {
    // Write to OLD field (for backward compat)
    $this->db->query("UPDATE records SET confirmed = ?", [$data['confirmed']]);
    
    // Write to NEW field (for future)
    if (columnExists('decision_source')) {
        $this->db->query("UPDATE records SET decision_source = ?", [$data['source']]);
    }
}

// Phase 2: Dual-Read (prefer new)
function getDecision($id) {
    $record = $this->db->query("SELECT * FROM records WHERE id = ?", [$id]);
    
    if (!empty($record['decision_source'])) {
        // New field exists and has value → use it
        return mapFromNewSchema($record);
    } else {
        // Fallback to old field
        return mapFromOldSchema($record);
    }
}

// Phase 3: New-only (after cutover)
function getDecision($id) {
    $record = $this->db->query("SELECT * FROM records WHERE id = ?", [$id]);
    return mapFromNewSchema($record); // No fallback
}

// Phase 4: Cleanup (remove old column)
// ALTER TABLE records DROP COLUMN confirmed;
```

---

## Checklist قبل التنفيذ

### Schema Impact Analysis

- [ ] هل تم توثيق Current Schema؟
- [ ] هل تم توثيق Proposed Change؟
- [ ] هل تم تحديد Affected Areas؟
- [ ] هل تم تصنيف Risk Level؟

### Migration Plan

- [ ] هل الخطة مقسمة لمراحل؟
- [ ] هل كل مرحلة قابلة للإيقاف؟
- [ ] هل هناك Rollback plan لكل مرحلة؟
- [ ] هل الترتيب صحيح؟ (Add → Dual → Cutover → Cleanup)

### Safety Measures

- [ ] هل هناك Backup قبل كل مرحلة؟
- [ ] هل هناك Monitoring؟
- [ ] هل هناك Feature Flags؟
- [ ] هل تم اختبار Rollback؟

### Approval

- [ ] هل تمت الموافقة من Schema Decision Record؟
- [ ] هل reviewers فهموا المخاطر؟
- [ ] هل هناك timeline واضح؟
- [ ] هل هناك success criteria؟

---

## الأسئلة الإلزامية قبل أي تنفيذ

1. **هل يمكن التراجع بدون فقد بيانات؟**
   - ✅ نعم → استمر
   - ❌ لا → أعد التفكير

2. **هل التغيير إضافي أم كسري؟**
   - 🟢 إضافي → أقل خطورة
   - 🔴 كسري → احذر

3. **هل هناك تشغيل مزدوج؟**
   - ✅ نعم → آمن
   - ❌ لا → لماذا؟

4. **هل هناك مراقبة؟**
   - ✅ نعم → جيد
   - ❌ لا → أضفها

5. **هل هناك Cleanup plan؟**
   - ✅ نعم → ممتاز
   - ❌ لا → اكتبه الآن

**❌ إذا فشل سؤال واحد → يُؤجل القرار**

---

## العلاقة مع بقية المشروع

```
DesignLab     → يكتشف مشاكل UX
   ↓
LogicLab      → يفكر في حلول منطقية
   ↓ (realizes schema change needed)
SchemaLab     → يخطط لتغيير آمن للبيانات
   ↓
logic-impact  → يوثق التحليل الكامل
   ↓
Decision (SDR) → قرار أكثر صرامة
   ↓
backend/changes → التنفيذ المرحلي
```

---

## مثال حي: Quick Decision + decision_source

راجع:
- `impact/decision-source-field.md` - تأثير الحقل الجديد
- `migration-plans/add-decision-source.md` - خطة الإضافة
- `simulations/dual-write-decision-source.md` - المحاكاة
- `decisions/SDR-001-decision-source.md` - القرار

---

## الفرق بين SchemaLab و LogicLab

| Aspect | LogicLab | SchemaLab |
|--------|----------|-----------|
| **Focus** | Business logic | Database structure |
| **Risk** | Medium | High |
| **Rollback** | Easy (feature flag) | Hard (data involved) |
| **Duration** | Days | Weeks/Months |
| **Phases** | 2-3 | 4+ (always) |
| **Approval** | DR-XXX | SDR-XXX (stricter) |

---

## متى يُغلق SchemaLab Experiment؟

### Scenario 1: نجح التنفيذ ✅

```markdown
## المشاريع المكتملة

### decision_source Field
- **Status:** ✅ Deployed & Cleaned
- **Started:** 2025-12-21
- **Cutover:** 2026-01-15
- **Cleanup:** 2026-02-01
- **Outcome:** Zero data loss, smooth migration
```

### Scenario 2: فشل في Phase 2 ❌

```markdown
### status_v2 Migration
- **Status:** ❌ Rolled Back
- **Phase Reached:** Dual-Write
- **Reason:** Performance degradation
- **Lessons:** Indexing needed first
- **Rollback:** Complete, no data loss
```

---

## القواعد الحديدية (Iron Rules)

1. **لا حذف مباشر** - دائماً Dual-Phase أولاً
2. **لا تغيير معنى** - أضف جديد، اترك القديم
3. **Backup قبل كل مرحلة** - لا استثناءات
4. **Monitoring إلزامي** - راقب كل شيء
5. **Cleanup متأخر** - أسابيع، ليس أيام

---

## الخلاصة (جملة واحدة)

> **SchemaLab هو المكان الذي نُحضّر فيه تغييرات قاعدة البيانات بدقة جراح،  
> لأن البيانات لا تُعوّض،  
> والتراجع ليس دائماً ممكناً.**

---

**Next:** راجع `impact/` لبدء أول تحليل Schema
