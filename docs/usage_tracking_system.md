# نظام تتبع الاستخدام والنقاط - التوثيق الفني الشامل
# Usage Tracking & Scoring System - Comprehensive Technical Documentation

**النسخة**: 1.1 (محدثة)
**التاريخ**: 2025-12-17  
**المؤلف**: Development Team  
**الحالة**: ✅ Phase 1 مُنجزة - جاري التنفيذ

---

## ⚠️ ملاحظة هامة - تحديثات بعد التنفيذ الفعلي

### الاكتشافات الرئيسية أثناء التنفيذ:

#### 1. أسماء الجداول الفعلية
```
المخطط الأولي (خاطئ):          الأسماء الفعلية في قاعدة البيانات:
├─ supplier_learning         ❌  ├─ supplier_aliases_learning ✓
└─ bank_learning            ❌  └─ bank_aliases_learning ✓
```

**السبب**: الجداول تم إنشاؤها بأسماء مختلفة في migration سابق.

**التأثير**: 
- جميع SQL queries يجب أن تستخدم `supplier_aliases_learning`
- جميع index names يجب أن تتطابق

#### 2. هيكل الأعمدة الفعلي

##### supplier_aliases_learning:
```sql
Column Name                Type            Notes
────────────────────────────────────────────────────────
learning_id                INTEGER         Primary key
original_supplier_name     TEXT            الاسم الخام
normalized_supplier_name   TEXT            النسخة المعالجة
learning_status            TEXT            supplier_alias/blocked
linked_supplier_id         INTEGER         المورد المرتبط ✓ (ليس supplier_id)
learning_source            TEXT            
updated_at                 DATETIME        
usage_count                INTEGER         ✅ NEW - أضيف بنجاح
last_used_at               TIMESTAMP       ✅ NEW - أضيف بنجاح
```

##### bank_aliases_learning:
```sql
Column Name             Type            Notes
───────────────────────────────────────────────────
id                     INTEGER         Primary key
input_name             TEXT            الاسم المدخل
normalized_input       TEXT            النسخة المعالجة
status                 TEXT            
bank_id                INTEGER         البنك المرتبط ✓
updated_at             DATETIME        
usage_count            INTEGER         ✅ NEW - أضيف بنجاح
last_used_at           TIMESTAMP       ✅ NEW - أضيف بنجاح
```

**ملاحظة مهمة**: أعمدة الربط مختلفة:
- Suppliers: `linked_supplier_id`
- Banks: `bank_id`

#### 3. قيود SQLite
```
المشكلة المكتشفة:
ALTER TABLE supplier_aliases_learning 
ADD COLUMN last_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
                                   ↑
                                   ❌ SQLite لا يدعم هذا!

الحل المطبق:
1. ADD COLUMN last_used_at TIMESTAMP DEFAULT NULL;
2. UPDATE supplier_aliases_learning 
   SET last_used_at = CURRENT_TIMESTAMP 
   WHERE last_used_at IS NULL;
```

**السبب**: SQLite doesn't support non-constant defaults in ALTER TABLE.

---

## جدول المحتويات

1. [نظرة عامة](#overview)
2. [الهدف والدوافع](#objectives)
3. [البنية المعمارية](#architecture)
4. [تغييرات قاعدة البيانات](#database-changes) **← محدث**
5. [الملفات المتأثرة](#affected-files)
6. [خطة التنفيذ التفصيلية](#implementation-plan) **← محدث**
7. [أمثلة الكود](#code-examples) **← محدث**
8. [خطة الترحيل](#migration-plan)
9. [الاختبار والتحقق](#testing)
10. [الأسئلة الشائعة](#faq)
11. [سجل التنفيذ](#implementation-log) **← جديد**

---

## 1. نظرة عامة {#overview}

### 1.1 المشكلة الحالية

```
الوضع الحالي:
├─ المستخدم يكتب "زومو زومو زومو" يدوياً
├─ النظام يحفظ في supplier_aliases_learning
├─ في الاستيراد التالي: لا تظهر كاقتراح ❌
└─ السبب: نظام المطابقة لا يعطي أولوية للاستخدام المتكرر
```

### 1.2 الحل المقترح

```
نظام النقاط الهجين:
┌────────────────────────────────────────┐
│ Total Score = Base Score + Bonus       │
│                                        │
│ Base Score: من دقة التطابق           │
│ Bonus Score: من تكرار الاستخدام      │
└────────────────────────────────────────┘

النتيجة:
├─ الأسماء الأكثر استخداماً تظهر أولاً ✓
├─ المستخدم يرى جميع خياراته السابقة ✓
└─ منطق واضح وشفاف ✓
```

---

## 4. تغييرات قاعدة البيانات {#database-changes}

### 4.1 تعديل جدول `supplier_aliases_learning` (الاسم الصحيح)

#### 4.1.1 Migration النهائي (المطبق بنجاح)

```sql
-- File: storage/migrations/20251217_add_usage_tracking.sql
-- Status: ✅ EXECUTED SUCCESSFULLY

BEGIN TRANSACTION;

-- ════════════════════════════════════════════════════════════
-- IMPORTANT: SQLite Limitation Workaround
-- SQLite doesn't support DEFAULT CURRENT_TIMESTAMP in ALTER TABLE
-- Solution: Add columns with NULL default, then UPDATE existing rows
-- ════════════════════════════════════════════════════════════

-- Add usage_count to supplier_aliases_learning
ALTER TABLE supplier_aliases_learning 
ADD COLUMN usage_count INTEGER DEFAULT NULL;

-- Add last_used_at to supplier_aliases_learning  
ALTER TABLE supplier_aliases_learning 
ADD COLUMN last_used_at TIMESTAMP DEFAULT NULL;

-- Update existing records to have default values
UPDATE supplier_aliases_learning 
SET usage_count = 1, 
    last_used_at = CURRENT_TIMESTAMP 
WHERE usage_count IS NULL;

-- Create index for performance (using correct column: linked_supplier_id)
CREATE INDEX IF NOT EXISTS idx_supplier_aliases_learning_usage 
ON supplier_aliases_learning(linked_supplier_id, usage_count DESC, last_used_at DESC);

-- Same for bank_aliases_learning
ALTER TABLE bank_aliases_learning 
ADD COLUMN usage_count INTEGER DEFAULT NULL;

ALTER TABLE bank_aliases_learning 
ADD COLUMN last_used_at TIMESTAMP DEFAULT NULL;

UPDATE bank_aliases_learning 
SET usage_count = 1, 
    last_used_at = CURRENT_TIMESTAMP 
WHERE usage_count IS NULL;

-- Create index for performance (using correct column: bank_id)
CREATE INDEX IF NOT EXISTS idx_bank_aliases_learning_usage 
ON bank_aliases_learning(bank_id, usage_count DESC, last_used_at DESC);

COMMIT;
```

#### 4.1.2 التحقق

```bash
# Verification successful:
✓ supplier_aliases_learning: usage_count ADDED
✓ supplier_aliases_learning: last_used_at ADDED
✓ bank_aliases_learning: usage_count ADDED
✓ bank_aliases_learning: last_used_at ADDED
```

#### 4.1.3 الحقول الموجودة (بدون تغيير)

**supplier_aliases_learning**:

| Column | Type | Description |
|--------|------|-------------|
| `learning_id` | INTEGER PRIMARY KEY | معرف فريد |
| `original_supplier_name` | TEXT | الاسم كما كتبه المستخدم |
| `normalized_supplier_name` | TEXT | النسخة المُعالجة |
| `linked_supplier_id` | INTEGER | المورد المرتبط |
| `learning_status` | TEXT | الحالة (supplier_alias/blocked) |
| `learning_source` | TEXT | مصدر التعلم |
| `updated_at` | DATETIME | تاريخ التحديث |
| `usage_count` | INTEGER | **✅ NEW** - عدد مرات الاستخدام |
| `last_used_at` | TIMESTAMP | **✅ NEW** - آخر استخدام |

---

## 6. خطة التنفيذ التفصيلية {#implementation-plan}

### ✅ المرحلة 1: إعداد قاعدة البيانات (مُنجزة)

#### الخطوة 1.1: إنشاء Migration ✓
```bash
# Created: storage/migrations/20251217_add_usage_tracking.sql
```

#### الخطوة 1.2: كتابة SQL ✓
- تم استخدام الأسماء الصحيحة للجداول
- تم حل مشكلة SQLite DEFAULT CURRENT_TIMESTAMP
- تم استخدام أسماء الأعمدة الصحيحة في indexes

#### الخطوة 1.3: تشغيل Migration ✓
```bash
php run_migration.php
# Output: ✅ Migration completed successfully!
```

#### الخطوة 1.4: التحقق ✓
```bash
php verify_migration.php
# All columns added successfully
```

---

### 🔄 المرحلة 2: تحديث Repositories (جاري العمل)

**الحالة**: بدأ التنفيذ

**الملفات المطلوب تعديلها**:

1. **البحث عن Repository class** الصحيح الذي يتعامل مع `supplier_aliases_learning`
   - قد لا يكون اسمه `SupplierLearningRepository`
   - يجب البحث في `app/Repositories/`

2. **إضافة الدوال المطلوبة**:
   - `incrementUsage(int $id): bool`
   - `getUsageStats(int $supplierId): array`
   - تعديل `save()` أو `updateOrCreate()`

**ملاحظة مهمة**: يجب استخدام أسماء الأعمدة الصحيحة:
```php
// ❌ Wrong:
WHERE supplier_id = ?

// ✓ Correct:
WHERE linked_supplier_id = ?
```

---

## 7. أمثلة الكود (محدثة) {#code-examples}

### مثال 1: Repository Methods (الصحيحة)

```php
<?php
// File: app/Repositories/[ActualRepositoryName].php

/**
 * Increment usage count for a learning record
 * 
 * UPDATED (2025-12-17): Uses correct table name
 */
public function incrementUsage(int $id): bool
{
    $stmt = $this->db->prepare("
        UPDATE supplier_aliases_learning 
        SET usage_count = usage_count + 1,
            last_used_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE learning_id = ?
    ");
    
    return $stmt->execute([$id]);
}

/**
 * Get usage statistics for a supplier
 * 
 * UPDATED (2025-12-17): Uses correct column names
 */
public function getUsageStats(int $supplierId): array
{
    $stmt = $this->db->prepare("
        SELECT original_supplier_name as raw_name, 
               usage_count, 
               last_used_at
        FROM supplier_aliases_learning
        WHERE linked_supplier_id = ?
        AND learning_status = 'supplier_alias'
        ORDER BY usage_count DESC, last_used_at DESC
    ");
    
    $stmt->execute([$supplierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

---

## 11. سجل التنفيذ {#implementation-log}

### 2025-12-17 - Phase 1 Complete

#### ما تم إنجازه:
1. ✅ إنشاء migration file
2. ✅ تطبيق migration على قاعدة البيانات
3. ✅ إضافة `usage_count` لـ supplier_aliases_learning
4. ✅ إضافة `last_used_at` لـ supplier_aliases_learning
5. ✅ إضافة `usage_count` لـ bank_aliases_learning
6. ✅ إضافة `last_used_at` لـ bank_aliases_learning
7. ✅ إنشاء indexes للأداء
8. ✅ تحديث السجلات الموجودة (usage_count = 1)

#### الاكتشافات المهمة:
- الجداول الفعلية: `supplier_aliases_learning` و `bank_aliases_learning`
- الأعمدة الفعلية: `linked_supplier_id` (للموردين) و `bank_id` (للبنوك)
- SQLite limitation: لا يدعم DEFAULT CURRENT_TIMESTAMP في ALTER
- الحل: استخدام NULL ثم UPDATE

#### الملفات المُنشأة:
- `storage/migrations/20251217_add_usage_tracking.sql`
- `run_migration.php` (helper script)
- `verify_migration.php` (verification script)
- `check_schema.php` (discovery script) 
- `check_columns.php` (column inspection script)

#### المتبقي:
- Phase 2: Repository methods
- Phase 3: CandidateService scoring
- Phase 4: UI with stars

#### الوقت المستهلك: ~30 دقيقة
#### الوقت المقدر للباقي: ~3.5 ساعة

---

## ملاحظات للمطورين

### 1. عند كتابة SQL queries جديدة:

```sql
-- ✓ استخدم هذه الأسماء:
SELECT * FROM supplier_aliases_learning 
WHERE linked_supplier_id = ?;

SELECT * FROM bank_aliases_learning 
WHERE bank_id = ?;

-- ❌ وليس:
SELECT * FROM supplier_learning WHERE supplier_id = ?;
```

### 2. عند إضافة columns جديدة في SQLite:

```sql
-- ❌ لا تفعل:
ALTER TABLE table_name 
ADD COLUMN col TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ✓ افعل:
ALTER TABLE table_name 
ADD COLUMN col TIMESTAMP DEFAULT NULL;

UPDATE table_name 
SET col = CURRENT_TIMESTAMP 
WHERE col IS NULL;
```

### 3. عند البحث عن Repository classes:

```bash
# ابحث عن الملفات الفعلية:
find app/Repositories -name "*Supplier*" -o -name "*Learning*" -o -name "*Alias*"
```

---

**آخر تحديث**: 2025-12-17 12:17  
**الحالة**: Phase 1 Complete ✅ | Phases 2-4 In Progress 🔄
