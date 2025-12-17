# نظام تتبع الاستخدام - ملخص الأسماء الفعلية
# Actual Names Reference - Quick Guide

**النسخة**: 1.0  
**آخر تحديث**: 2025-12-17

---

## الجداول (Tables)

### ❌ الأسماء المتوقعة (خاطئة):
```
supplier_learning
bank_learning
```

### ✅ الأسماء الفعلية:
```
supplier_aliases_learning
bank_aliases_learning
```

---

## الأعمدة - supplier_aliases_learning

| الاسم المتوقع | الاسم الفعلي ✓ | النوع |
|---------------|----------------|-------|
| ❌ id | ✅ learning_id | INTEGER PRIMARY KEY |
| ❌ raw_name | ✅ original_supplier_name | TEXT |
| ❌ normalized_raw | ✅ normalized_supplier_name | TEXT |
| ❌ supplier_id | ✅ linked_supplier_id | INTEGER |
| ✅ learning_status | ✅ learning_status | TEXT |
| ❌ source | ✅ learning_source | TEXT |
| ✅ updated_at | ✅ updated_at | DATETIME |
| ✅ usage_count | ✅ usage_count | INTEGER (NEW) |
| ✅ last_used_at | ✅ last_used_at | TIMESTAMP (NEW) |

---

## الأعمدة - bank_aliases_learning

| الاسم المتوقع | الاسم الفعلي ✓ | النوع |
|---------------|----------------|-------|
| ✅ id | ✅ id | INTEGER PRIMARY KEY |
| ❌ raw_name | ✅ input_name | TEXT |
| ❌ normalized | ✅ normalized_input | TEXT |
| ✅ status | ✅ status | TEXT |
| ✅ bank_id | ✅ bank_id | INTEGER |
| ✅ updated_at | ✅ updated_at | DATETIME |
| ✅ usage_count | ✅ usage_count | INTEGER (NEW) |
| ✅ last_used_at | ✅ last_used_at | TIMESTAMP (NEW) |

---

## أمثلة SQL الصحيحة

### للموردين (Suppliers):

```sql
-- ✓ الصحيح:
SELECT original_supplier_name, usage_count 
FROM supplier_aliases_learning 
WHERE linked_supplier_id = ?;

-- ❌ خاطئ:
SELECT raw_name, usage_count 
FROM supplier_learning 
WHERE supplier_id = ?;
```

### للبنوك (Banks):

```sql
-- ✓ الصحيح:
SELECT input_name, usage_count 
FROM bank_aliases_learning 
WHERE bank_id = ?;

-- ❌ خاطئ:
SELECT raw_name, usage_count 
FROM bank_learning 
WHERE bank_id = ?;
```

---

## Repository Methods - الأسماء الفعلية

### SupplierLearningRepository:

```php
// ✓ استخدام الأسماء الصحيحة:
public function getUsageStats(int $supplierId): array
{
    $stmt = $pdo->prepare("
        SELECT original_supplier_name,  // ✓ ليس raw_name
               usage_count, 
               last_used_at
        FROM supplier_aliases_learning  // ✓ ليس supplier_learning
        WHERE linked_supplier_id = ?    // ✓ ليس supplier_id
        AND learning_status = 'supplier_alias'
        ORDER BY usage_count DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### BankLearningRepository:

```php
// ✓ استخدام الأسماء الصحيحة:
public function getUsageStats(int $bankId): array
{
    $stmt = $pdo->prepare("
        SELECT input_name,              // ✓ ليس raw_name
               usage_count, 
               last_used_at
        FROM bank_aliases_learning      // ✓ ليس bank_learning
        WHERE bank_id = ?               // ✓ صحيح
        AND status = 'alias'
        ORDER BY usage_count DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

---

## CandidateService - استخدام الأسماء الصحيحة

```php
// عند استخدام getUsageStats:
$stats = $this->supplierLearning->getUsageStats($supplierId);

// $stats format:
[
    [
        'original_supplier_name' => 'زومو زومو زومو',  // ✓ ليس 'raw_name'
        'usage_count' => 5,
        'last_used_at' => '2025-12-17 10:30:00'
    ],
    // ...
]

// استخدامه في الكود:
foreach ($stats as $stat) {
    $name = $stat['original_supplier_name'];  // ✓ الاسم الصحيح
    $count = $stat['usage_count'];
    // ...
}
```

---

## ملخص الاختلافات الرئيسية

### الموردين (Suppliers):
- **الجدول**: `supplier_aliases_learning` (ليس `supplier_learning`)
- **الاسم الخام**: `original_supplier_name` (ليس `raw_name`)
- **الاسم المعالج**: `normalized_supplier_name` (ليس `normalized_raw`)
- **معرف المورد**: `linked_supplier_id` (ليس `supplier_id`)
- **معرف السجل**: `learning_id` (ليس `id`)

### البنوك (Banks):
- **الجدول**: `bank_aliases_learning` (ليس `bank_learning`)
- **الاسم المدخل**: `input_name` (ليس `raw_name`)
- **الاسم المعالج**: `normalized_input` (ليس `normalized`)
- **معرف البنك**: `bank_id` ✓ (صحيح كما هو)
- **معرف السجل**: `id` ✓ (صحيح كما هو)

---

**استخدم هذا المرجع** عند كتابة أي كود يتعامل مع learning tables!
