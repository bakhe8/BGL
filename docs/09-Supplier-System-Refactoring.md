# نظام إدارة أسماء الموردين - توثيق شامل

> **آخر تحديث**: 2025-12-17  
> **الإصدار**: 4.0 (قبل التبسيط)  
> **الحالة**: 🔄 قيد التحديث - Refactoring in Progress

---

## 📋 الفهرس

1. [نظرة عامة](#نظرة-عامة)
2. [الوضع الحالي (Before)](#الوضع-الحالي-before)
3. [المشاكل والتعقيدات](#المشاكل-والتعقيدات)
4. [الحل المقترح (After)](#الحل-المقترح-after)
5. [خطة التنفيذ التفصيلية](#خطة-التنفيذ-التفصيلية)
6. [مرجع التراجع](#مرجع-التراجع)

---

## 🎯 نظرة عامة

### ما هذا النظام؟

نظام يتعامل مع أسماء الموردين في عدة مراحل:
1. **استيراد** أسماء من ملفات Excel
2. **مطابقة** مع القواميس الرسمية
3. **تعلم** من اختيارات المستخدم
4. **اقتراح** أسماء ذكية للسجلات الجديدة
5. **نشر** القرارات على سجلات مشابهة

### لماذا التغيير؟

| المشكلة | التأثير |
|---------|---------|
| المنطق معقد جداً | صعوبة الصيانة والتطوير |
| الحسابات تتكرر | بطء في الأداء |
| الجداول متداخلة | صعوبة التتبع والفهم |
| لا سجل للقرارات | لا نعرف مصدر كل اسم |

---

## 📊 الوضع الحالي (Before)

### الجداول المستخدمة حالياً:

```
┌─────────────────────────────────────────────────────────────┐
│                    الهيكل الحالي                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  1. suppliers                                               │
│     └─ id, official_name                                    │
│     └─ القاموس الرسمي للموردين                              │
│                                                             │
│  2. supplier_alternative_names                              │
│     └─ supplier_id, alternative_name                        │
│     └─ أسماء بديلة يدوية                                    │
│                                                             │
│  3. supplier_aliases_learning                               │
│     └─ original_supplier_name                               │
│     └─ normalized_supplier_name                             │
│     └─ linked_supplier_id                                   │
│     └─ usage_count, last_used_at                            │
│     └─ التعلم الآلي من اختيارات المستخدم                    │
│                                                             │
│  4. imported_records                                        │
│     └─ raw_supplier_name (من Excel)                         │
│     └─ supplier_id (المختار)                                │
│     └─ supplier_display_name (للعرض)                        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### تدفق العمل الحالي:

#### 1. عند الاستيراد:
```
Excel → import.php
  │
  ▼
INSERT INTO imported_records (
    raw_supplier_name = "ABC TRADING CO",
    supplier_id = NULL,
    supplier_display_name = NULL
)
```

#### 2. عند فتح صفحة القرار:
```
decision.php?record_id=123
        │
        ▼
┌───────────────────────────────────────┐
│ CandidateService->supplierCandidates()│
└───────────────────────────────────────┘
        │
        ├── Query 1: SELECT FROM suppliers WHERE name LIKE...
        ├── Query 2: SELECT FROM supplier_alternative_names WHERE...
        ├── Query 3: SELECT FROM supplier_aliases_learning WHERE...
        │
        ▼
┌───────────────────────────────────────┐
│ لكل نتيجة:                            │
│   - Calculate Levenshtein distance    │
│   - Calculate base score              │
│   - Get usage stats                   │
│   - Calculate bonus points            │
│   - Assign star rating                │
└───────────────────────────────────────┘
        │
        ▼
┌───────────────────────────────────────┐
│ Sort by total_score DESC              │
│ Return top 6 candidates               │
└───────────────────────────────────────┘
```

**المشكلة**: كل هذا يحدث **في كل مرة** يفتح فيها المستخدم سجل!

#### 3. عند الحفظ:
```
User clicks Save → process_update.php
        │
        ├── UPDATE imported_records SET supplier_id = X
        │
        ├── INSERT/UPDATE supplier_aliases_learning
        │   (create or increment usage)
        │
        └── UPDATE imported_records 
            WHERE session_id = same AND raw_name = same
            AND supplier_id IS NULL
            (propagation)
```

### الملفات المعنية:

| الملف | الدور |
|-------|------|
| `www/decision.php` | عرض الصفحة + توليد Candidates |
| `www/process_update.php` | حفظ القرار + التعلم + النشر |
| `app/Services/CandidateService.php` | خوارزمية المطابقة والتقييم |
| `app/Repositories/SupplierLearningRepository.php` | التعلم وتتبع الاستخدام |
| `app/Repositories/SupplierRepository.php` | القاموس الرسمي |
| `app/Repositories/SupplierAlternativeNameRepository.php` | الأسماء البديلة |

---

## ⚠️ المشاكل والتعقيدات

### المشكلة 1: الحسابات المتكررة

```
السجل 12028 يُفتح 5 مرات = 5 × (3 queries + fuzzy matching + scoring)

لو كان لدينا 100 سجل بنفس الاسم:
  = 100 × نفس الحسابات!
```

**الحل**: Cache النتائج في جدول.

---

### المشكلة 2: التعلم والـ Scoring متداخلان

```
supplier_aliases_learning يحتوي:
  - original_name → linked_supplier_id (للتعلم)
  - usage_count, last_used_at (للـ Scoring)

جدول واحد = غرضان مختلفان = صعوبة الصيانة
```

**الحل**: فصلهما في جدولين.

---

### المشكلة 3: لا سجل للقرارات

```
السؤال: هذا السجل، من أين جاء اسم المورد فيه؟
- من Excel؟
- من اختيار المستخدم؟
- من Propagation؟
- من التعلم؟

الجواب: لا نعرف! لا يوجد سجل.
```

**الحل**: جدول `user_decisions` يسجل كل قرار.

---

### المشكلة 4: Current Selection معقد

```
لعرض "الاختيار الحالي" نحتاج:
  1. Check if supplier_id exists
  2. Fetch official_name from suppliers
  3. Compare with raw_supplier_name
  4. Check if from learning or dictionary
  5. Determine badge text
  
5 خطوات لمجرد badge!
```

**الحل**: حقل `decision_source` يخزّن المصدر مباشرة.

---

## ✅ الحل المقترح (After)

### الجداول الجديدة:

```
┌─────────────────────────────────────────────────────────────┐
│                    الهيكل الجديد                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  [الجداول الحالية تبقى كما هي]                              │
│                                                             │
│  + جدول جديد: supplier_suggestions                         │
│    └─ normalized_input (from Excel)                        │
│    └─ supplier_id                                          │
│    └─ display_name                                         │
│    └─ source (dictionary/learning/alternatives/history)    │
│    └─ fuzzy_score, source_weight, usage_count              │
│    └─ total_score, star_rating                             │
│    └─ = Cache للاقتراحات المحسوبة                          │
│                                                             │
│  + جدول جديد: user_decisions                               │
│    └─ record_id, session_id                                │
│    └─ raw_name, normalized_name                            │
│    └─ chosen_supplier_id, chosen_display_name              │
│    └─ decision_source (user_click/propagation/auto)        │
│    └─ decided_at                                           │
│    └─ = سجل كل القرارات                                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### التدفق الجديد:

#### عند فتح صفحة القرار:
```
decision.php?record_id=123
        │
        ▼
SELECT * FROM supplier_suggestions
WHERE normalized_input = ?
ORDER BY total_score DESC
LIMIT 6
        │
        ├── Found? → Use cached suggestions ✓
        │
        └── Not found? → Generate once → Save to cache
```

**الفرق**: Query واحد بسيط بدلاً من 3 queries + matching!

#### عند الحفظ:
```
User clicks Save
        │
        ├── 1. UPDATE imported_records
        │
        ├── 2. INSERT INTO user_decisions (مع decision_source)
        │
        ├── 3. UPDATE supplier_suggestions SET usage_count++
        │       (تحديث فوري للـ cache)
        │
        └── 4. Propagate + INSERT user_decisions لكل propagated
```

---

## 📋 خطة التنفيذ التفصيلية

### Phase 1: Database (الجداول)

**الملف**: `storage/migrations/add_suggestion_tables.sql`

```sql
-- 1. جدول الاقتراحات المُخزّنة
CREATE TABLE IF NOT EXISTS supplier_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    normalized_input VARCHAR(500) NOT NULL,
    supplier_id INTEGER NOT NULL,
    display_name VARCHAR(500) NOT NULL,
    source VARCHAR(50) NOT NULL,
    fuzzy_score REAL DEFAULT 0.0,
    source_weight INTEGER DEFAULT 0,
    usage_count INTEGER DEFAULT 0,
    total_score REAL DEFAULT 0.0,
    star_rating INTEGER DEFAULT 1,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(normalized_input, supplier_id, source)
);

CREATE INDEX idx_suggestions_input ON supplier_suggestions(normalized_input);
CREATE INDEX idx_suggestions_score ON supplier_suggestions(total_score DESC);

-- 2. جدول سجل القرارات
CREATE TABLE IF NOT EXISTS user_decisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    session_id INTEGER NOT NULL,
    raw_name VARCHAR(500) NOT NULL,
    normalized_name VARCHAR(500) NOT NULL,
    chosen_supplier_id INTEGER NOT NULL,
    chosen_display_name VARCHAR(500),
    decision_source VARCHAR(50) NOT NULL,
    decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chosen_supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (record_id) REFERENCES imported_records(id)
);

CREATE INDEX idx_decisions_normalized ON user_decisions(normalized_name);
CREATE INDEX idx_decisions_supplier ON user_decisions(chosen_supplier_id);
CREATE INDEX idx_decisions_record ON user_decisions(record_id);
```

**الخطوات**:
1. [ ] إنشاء ملف migration
2. [ ] تشغيل SQL على database.sqlite
3. [ ] التحقق من الجداول

---

### Phase 2: Repositories (الكود)

**ملفات جديدة**:

#### 2.1. `app/Repositories/SupplierSuggestionRepository.php`

```php
class SupplierSuggestionRepository {
    
    // جلب الاقتراحات من الـ cache
    public function getSuggestions(string $normalizedInput, int $limit = 6): array;
    
    // إضافة اقتراحات جديدة للـ cache
    public function saveSuggestions(string $normalizedInput, array $suggestions): void;
    
    // تحديث usage و score
    public function incrementUsage(string $normalizedInput, int $supplierId): void;
    
    // إعادة حساب الـ scores
    public function recalculateScore(string $normalizedInput, int $supplierId): void;
    
    // التحقق من وجود cache
    public function hasCachedSuggestions(string $normalizedInput): bool;
}
```

#### 2.2. `app/Repositories/UserDecisionRepository.php`

```php
class UserDecisionRepository {
    
    // تسجيل قرار جديد
    public function logDecision(
        int $recordId,
        int $sessionId,
        string $rawName,
        string $normalizedName,
        int $supplierId,
        string $displayName,
        string $source  // 'user_click', 'user_typed', 'propagation', 'auto_select'
    ): int;
    
    // جلب آخر قرار لسجل معين
    public function getLastDecision(int $recordId): ?array;
    
    // جلب أكثر الموردين اختياراً لاسم معين
    public function getMostChosenSuppliers(string $normalizedName, int $limit = 5): array;
}
```

**الخطوات**:
1. [ ] إنشاء `SupplierSuggestionRepository.php`
2. [ ] إنشاء `UserDecisionRepository.php`
3. [ ] اختبار كل method

---

### Phase 3: decision.php (العرض)

**التغييرات**:

```php
// الكود القديم (معقد):
$supplierCandidates = $candidateService->supplierCandidates($rawName)['candidates'];
// + enrichment + scoring + sorting + ...

// الكود الجديد (بسيط):
$suggestionRepo = new SupplierSuggestionRepository();
$normalized = $normalizer->normalizeSupplierName($rawName);

if ($suggestionRepo->hasCachedSuggestions($normalized)) {
    $supplierCandidates = $suggestionRepo->getSuggestions($normalized);
} else {
    // Generate once and cache
    $candidates = $candidateService->generateAndScore($rawName);
    $suggestionRepo->saveSuggestions($normalized, $candidates);
    $supplierCandidates = $candidates;
}

// Current selection from decisions table
$decisionRepo = new UserDecisionRepository();
$lastDecision = $decisionRepo->getLastDecision($recordId);
$decisionSource = $lastDecision['decision_source'] ?? null;
```

**الخطوات**:
1. [ ] إضافة imports للـ repositories الجديدة
2. [ ] تعديل قسم توليد الـ candidates
3. [ ] تعديل قسم Current Selection
4. [ ] اختبار العرض

---

### Phase 4: process_update.php (الحفظ)

**التغييرات**:

```php
// بعد تحديث السجل:

// 1. تسجيل القرار
$decisionRepo = new UserDecisionRepository();
$decisionRepo->logDecision(
    $recordId,
    $sessionId,
    $rawName,
    $normalizedName,
    $supplierId,
    $displayName,
    'user_click' // or 'user_typed'
);

// 2. تحديث الـ cache
$suggestionRepo = new SupplierSuggestionRepository();
$suggestionRepo->incrementUsage($normalizedName, $supplierId);

// 3. Propagation مع تسجيل
$propagated = $records->propagateToSession($sessionId, $rawName, $supplierId);
foreach ($propagated as $propagatedRecordId) {
    $decisionRepo->logDecision(
        $propagatedRecordId,
        $sessionId,
        $rawName,
        $normalizedName,
        $supplierId,
        $displayName,
        'propagation'  // ← نوع مختلف!
    );
}
```

**الخطوات**:
1. [ ] إضافة logging للقرارات
2. [ ] إضافة تحديث الـ cache
3. [ ] تعديل propagation ليسجل
4. [ ] اختبار الحفظ

---

### Phase 5: Migration & Cleanup

**الخطوات**:
1. [ ] نقل بيانات learning الحالية إلى suggestions
2. [ ] التحقق من backward compatibility
3. [ ] اختبار شامل لكل السيناريوهات
4. [ ] تنظيف الكود القديم (لاحقاً)

---

## 🔙 مرجع التراجع

### إذا حدثت مشكلة:

**الـ Tag للتراجع**:
```bash
git checkout v3.0-pre-refactor
```

**أو من الـ Branch**:
```bash
git log --oneline
git checkout b6f634b  # آخر commit قبل التغييرات
```

**الوضع الآمن**:
- كل الجداول الحالية **لن تُحذف**
- الكود القديم **لن يُزال** حتى الاختبار الكامل
- الجداول الجديدة **إضافية** فقط

---

## ✅ Checklist التنفيذ

- [ ] **Phase 1**: Database tables created
- [ ] **Phase 2**: Repositories implemented
- [ ] **Phase 3**: decision.php updated
- [ ] **Phase 4**: process_update.php updated
- [ ] **Phase 5**: Migration complete
- [ ] **Testing**: All scenarios verified
- [ ] **Cleanup**: Old code removed (optional)
