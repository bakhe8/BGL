# 06 - الإدخال اليدوي (Manual Entry)

---
**last_updated**: 2025-12-18  
**version**: 1.0  
**status**: active  
**feature_type**: Data Import Method
---

## نظرة عامة

**الإدخال اليدوي** هو طريقة ثالثة لإدخال بيانات الضمانات البنكية إلى نظام BGL، بجانب:
1. استيراد ملفات Excel
2. الاستيراد النصي الذكي (Smart Paste)

هذه الميزة توفر واجهة مباشرة وسهلة لإدخال سجل ضمان بنكي واحد يدوياً من خلال نموذج تفاعلي في صفحة القرار.

### الاستخدام المثالي
- إدخال سجلات فردية بسرعة
- الحالات الطارئة التي لا تتطلب ملف Excel كامل
- إضافة سجلات تكميلية لجلسة موجودة
- اختبار النظام بسجلات تجريبية

### المبدأ الأساسي
**السجل المُنشأ يدوياً = سجل من Excel تماماً**

بعد إنشاء السجل:
- يُعامل بنفس طريقة السجلات من Excel
- يمر بنفس نظام المطابقة التلقائية
- يحتاج لنفس المراجعة والاعتماد
- يمكن طباعة خطابه بنفس الطريقة

---

## الهيكلية التقنية

### Backend (PHP)

#### 1. Controller: `ManualEntryController.php`

**المسار**: `app/Controllers/ManualEntryController.php`

**الوظائف الرئيسية**:

```php
public function handle(array $input): void
```
- **الوصف**: نقطة الدخول الرئيسية للـ API
- **المدخلات**: بيانات النموذج من Frontend (JSON)
- **العمليات**:
  1. Validation للبيانات المطلوبة
  2. إنشاء جلسة استيراد جديدة (نوع: `manual`)
  3. إنشاء `ImportedRecord`
  4. تطبيق المطابقة التلقائية (MatchingService)
  5. تطبيق القبول التلقائي (AutoAcceptService)
  6. إرجاع النتيجة (record_id + session_id)
- **المخرجات**: JSON response

```php
private function validateInput(array $input): array
```
- **الوصف**: التحقق من صحة البيانات المدخلة
- **الحقول الإلزامية**:
  - `supplier` - اسم المورد
  - `bank` - اسم البنك
  - `guarantee_number` - رقم الضمان
  - `contract_number` - رقم العقد
  - `amount` - المبلغ (يجب أن يكون رقماً)
- **الإرجاع**: مصفوفة من رسائل الخطأ (فارغة إذا كانت البيانات صحيحة)

```php
private function processMatching(ImportedRecord $record): void
```
- **الوصف**: تطبيق المطابقة التلقائية
- **العمليات**:
  1. مطابقة المورد (`MatchingService::matchSupplier`)
  2. مطابقة البنك (`MatchingService::matchBank`)
  3. جلب المرشحين (`CandidateService`)
  4. كشف التعارضات (`ConflictDetector`)
  5. محاولة القبول التلقائي (`AutoAcceptService`)
  6. تحديث السجل في قاعدة البيانات

```php
private function normalizeAmount(string $amount): ?string
```
- **الوصف**: تنسيق المبلغ إلى صيغة موحدة
- **الدعم**: 
  - الصيغة الأمريكية: `1,234.56`
  - الصيغة الأوروبية: `1.234,56`
- **الإرجاع**: المبلغ بصيغة موحدة (`xxxx.xx`)

```php
private function normalizeDate(string $value): ?string
```
- **الوصف**: تحويل التاريخ إلى صيغة ISO (YYYY-MM-DD)
- **الدعم**: معظم صيغ التواريخ الشائعة
- **الإرجاع**: التاريخ بصيغة ISO

#### 2. API Endpoint

**Route**: `/api/import/manual`  
**Method**: `POST`  
**Content-Type**: `application/json`

**Request Body**:
```json
{
  "supplier": "شركة المراعي",
  "bank": "البنك الأهلي",
  "guarantee_number": "12345",
  "contract_number": "CTR-001",
  "amount": "50000.00",
  "expiry_date": "2025-12-31",
  "issue_date": "2025-01-01",
  "type": "FINAL",
  "comment": "ملاحظات إضافية"
}
```

**الحقول**:
| الحقل | النوع | إلزامي | الوصف |
|------|------|--------|-------|
| `supplier` | string | ✅ | اسم المورد |
| `bank` | string | ✅ | اسم البنك |
| `guarantee_number` | string | ✅ | رقم الضمان البنكي |
| `contract_number` | string | ✅ | رقم العقد أو أمر الشراء |
| `amount` | string | ✅ | المبلغ (يقبل أرقام وفواصل) |
| `expiry_date` | string | ❌ | تاريخ انتهاء الضمان (ISO format) |
| `issue_date` | string | ❌ | تاريخ إصدار الضمان (ISO format) |
| `type` | string | ❌ | نوع الضمان: `FINAL` أو `ADVANCED` |
| `comment` | string | ❌ | ملاحظات إضافية |

**Success Response** (200 OK):
```json
{
  "success": true,
  "record_id": 12449,
  "session_id": 405,
  "message": "تم إنشاء السجل بنجاح"
}
```

**Error Response** (400 Bad Request):
```json
{
  "success": false,
  "error": "بيانات غير صحيحة: اسم المورد مطلوب، المبلغ مطلوب"
}
```

**Error Response** (500 Internal Server Error):
```json
{
  "success": false,
  "error": "فشل إنشاء السجل: رسالة الخطأ"
}
```

#### 3. Router Configuration

**الملف**: `www/includes/router.php`

```php
// 6. Manual Entry API
if ($method === 'POST' && $uri === '/api/import/manual') {
    $manualEntryController = new \App\Controllers\ManualEntryController(
        $apiImportSessionRepo,
        $apiRecords,
        new \App\Services\MatchingService(
            new SupplierRepository(),
            new SupplierAlternativeNameRepository(),
            new BankRepository()
        )
    );
    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $manualEntryController->handle($payload);
    exit;
}
```

---

### Frontend (HTML + JavaScript)

#### 1. UI Components

**الملف**: `www/views/decision-page.php`

##### أ. زر الإدخال اليدوي

**الموقع**: شريط الأدوات (Toolbar Zone End)  
**الكود**:
```html
<button class="flex items-center justify-center w-7 h-7 rounded hover:bg-gray-100 transition-colors"
    id="btnOpenManualEntry" title="إدخال يدوي">✍️</button>
```

**السلوك**:
- يظهر بجانب زر استيراد Excel
- عند النقر، يفتح Modal الإدخال اليدوي

##### ب. Modal الإدخال اليدوي

**التصميم**:
- **عرض**: `max-w-2xl` (responsive)
- **Layout**: Grid من عمودين على شاشات MD وأكبر
- **الحقول**: 8 حقول إدخال + منطقة نص للملاحظات

**الحقول الإلزامية** (مميزة بـ `*` حمراء):
1. المورد (Supplier)
2. البنك (Bank)
3. رقم الضمان (Guarantee Number)
4. رقم العقد (Contract Number)
5. المبلغ (Amount)

**الحقول الاختيارية**:
1. تاريخ الانتهاء (Expiry Date) - `<input type="date">`
2. نوع الضمان (Type) - `<select>` بخيارين: FINAL / ADVANCED
3. تاريخ الإصدار (Issue Date) - `<input type="date">`
4. ملاحظات (Comment) - `<textarea>`

**الأزرار**:
- **إلغاء**: يغلق Modal ويعيد تعيين النموذج
- **حفظ وإضافة**: يتحقق من البيانات ويرسلها إلى API

#### 2. JavaScript Module

**الملف**: `www/assets/js/manual-entry.js`

**الهيكل**:
```javascript
(function() {
    'use strict';
    
    // DOM Elements
    const modal = document.getElementById('manualEntryModal');
    const btnOpen = document.getElementById('btnOpenManualEntry');
    // ... المزيد من العناصر
    
    // Functions
    function openModal() { ... }
    function closeModal() { ... }
    function resetForm() { ... }
    function validateForm() { ... }
    async function saveManualEntry() { ... }
    
    // Event Listeners
    btnOpen.addEventListener('click', openModal);
    // ... المزيد من المستمعين
})();
```

##### أ. `openModal()`
- يعرض Modal
- يعيد تعيين النموذج
- يركز على حقل المورد

##### ب. `closeModal()`
- يخفي Modal
- يعيد تعيين النموذج
- يمسح رسائل الخطأ

##### ج. `resetForm()`
- يمسح جميع الحقول
- يزيل تمييز الأخطاء (الحدود الحمراء)
- يخفي رسائل الخطأ

##### د. `validateForm()`
- **يتحقق من الحقول الإلزامية**:
  - يجب ألا تكون فارغة
  - يضيف `border-red-500` للحقول الفارغة
- **يتحقق من صحة المبلغ**:
  - يجب أن يكون رقماً
  - يجب أن يكون أكبر من صفر
- **الإرجاع**: مصفوفة من رسائل الخطأ

##### هـ. `saveManualEntry()`
**سير العمل**:

1. **Validation**:
   ```javascript
   const errors = validateForm();
   if (errors.length > 0) {
       showError(errors.join('، '));
       return;
   }
   ```

2. **تحضير البيانات**:
   ```javascript
   const data = {
       supplier: inputs.supplier.value.trim(),
       bank: inputs.bank.value.trim(),
       guarantee_number: inputs.guarantee.value.trim(),
       contract_number: inputs.contract.value.trim(),
       amount: inputs.amount.value.trim(),
       expiry_date: inputs.expiry.value || null,
       issue_date: inputs.issue.value || null,
       type: inputs.type.value || null,
       comment: inputs.comment.value.trim() || 'إدخال يدوي'
   };
   ```

3. **إرسال الطلب**:
   ```javascript
   const response = await fetch('/api/import/manual', {
       method: 'POST',
       headers: { 'Content-Type': 'application/json' },
       body: JSON.stringify(data)
   });
   ```

4. **معالجة النتيجة**:
   - **نجاح**: 
     - عرض رسالة نجاح (`showSuccess`)
     - إعادة توجيه إلى السجل الجديد
   - **فشل**: 
     - عرض رسالة الخطأ

5. **إعادة التوجيه**:
   ```javascript
   const currentUrl = new URL(window.location.href);
   currentUrl.searchParams.set('id', result.record_id);
   currentUrl.searchParams.set('session', result.session_id);
   window.location.href = currentUrl.toString();
   ```

##### و. Event Listeners

**فتح/إغلاق Modal**:
```javascript
btnOpen.addEventListener('click', openModal);
btnClose.addEventListener('click', closeModal);
btnCancel.addEventListener('click', closeModal);

// إغلاق عند النقر خارج Modal
modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
});

// إغلاق بمفتاح Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
        closeModal();
    }
});
```

**حفظ السجل**:
```javascript
btnSave.addEventListener('click', saveManualEntry);

// Enter key (ما عدا في textarea)
form.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        saveManualEntry();
    }
});
```

---

## قاعدة البيانات

### جلسات الاستيراد (import_sessions)

**لا توجد تعديلات على الجدول** - يستخدم الهيكل الحالي

**نوع الجلسة الجديد**:
```
source = 'manual'
```

**أنواع الجلسات المدعومة الآن**:
- `excel` - استيراد من ملف Excel
- `paste` - الاستيراد النصي الذكي
- `manual` - الإدخال اليدوي (جديد)

### السجلات المستوردة (imported_records)

**لا توجد تعديلات** - يستخدم نفس جدول `ImportedRecord`

**الحقول المستخدمة**:
```sql
id, session_id, raw_supplier_name, raw_bank_name, 
amount, guarantee_number, contract_number, contract_source,
expiry_date, issue_date, type, comment,
match_status, supplier_id, bank_id, 
normalized_supplier, normalized_bank, 
bank_display, supplier_display_name
```

**ملاحظات**:
- `contract_source` يُعيّن إلى `'contract'` افتراضياً
- `comment` يُعيّن إلى `'إدخال يدوي'` إذا كان فارغاً
- `match_status` يبدأ بـ `'needs_review'` ويُحدّث بعد المطابقة

---

## سير العمل الكامل

```
1. المستخدم يضغط زر ✍️
   ↓
2. يفتح Modal الإدخال اليدوي
   ↓
3. المستخدم يملأ الحقول المطلوبة
   ↓
4. المستخدم يضغط "حفظ وإضافة"
   ↓
5. JavaScript: validateForm()
   ↓ (إذا صحيح)
6. JavaScript: POST /api/import/manual
   ↓
7. ManualEntryController::validateInput()
   ↓ (إذا صحيح)
8. ManualEntryController: إنشاء session (manual)
   ↓
9. ManualEntryController: إنشاء ImportedRecord
   ↓
10. ManualEntryController::processMatching()
    - MatchingService::matchSupplier()
    - MatchingService::matchBank()
    - AutoAcceptService::tryAutoAccept()
   ↓
11. إرجاع JSON { record_id, session_id }
   ↓
12. JavaScript: إعادة توجيه إلى /?id=xxx&session=yyy
   ↓
13. صفحة القرار تعرض السجل الجديد
   ↓
14. المستخدم يراجع ويحفظ القرار (كالمعتاد)
```

---

## أمثلة الاستخدام

### مثال 1: سجل كامل

**الطلب**:
```bash
curl -X POST http://localhost:8000/api/import/manual \
  -H "Content-Type: application/json" \
  -d '{
    "supplier": "شركة الاتصالات السعودية",
    "bank": "البنك الأهلي",
    "guarantee_number": "GU-2025-001",
    "contract_number": "CTR-2025-001",
    "amount": "100000.00",
    "expiry_date": "2025-12-31",
    "issue_date": "2025-01-15",
    "type": "FINAL",
    "comment": "ضمان نهائي للمشروع X"
  }'
```

**الاستجابة**:
```json
{
  "success": true,
  "record_id": 12450,
  "session_id": 406,
  "message": "تم إنشاء السجل بنجاح"
}
```

### مثال 2: سجل بحد أدنى من البيانات

**الطلب**:
```bash
curl -X POST http://localhost:8000/api/import/manual \
  -H "Content-Type: application/json" \
  -d '{
    "supplier": "شركة المراعي",
    "bank": "البنك السعودي الفرنسي",
    "guarantee_number": "TEST-001",
    "contract_number": "CTR-TEST",
    "amount": "50000"
  }'
```

**الاستجابة**:
```json
{
  "success": true,
  "record_id": 12451,
  "session_id": 407,
  "message": "تم إنشاء السجل بنجاح"
}
```

### مثال 3: بيانات ناقصة (خطأ)

**الطلب**:
```bash
curl -X POST http://localhost:8000/api/import/manual \
  -H "Content-Type: application/json" \
  -d '{
    "supplier": "شركة المراعي",
    "amount": "50000"
  }'
```

**الاستجابة**:
```json
{
  "success": false,
  "error": "بيانات غير صحيحة: اسم البنك مطلوب، رقم الضمان مطلوب، رقم العقد مطلوب"
}
```

---

## الاختبار

### اختبار يدوي (UI)

1. **فتح التطبيق**:
   ```
   http://localhost:8000
   ```

2. **فتح Modal**:
   - انقر على زر ✍️ في شريط الأدوات

3. **ملء البيانات**:
   - المورد: "شركة اختبار"
   - البنك: "بنك اختبار"
   - رقم الضمان: "TEST-XXX"
   - رقم العقد: "CTR-XXX"
   - المبلغ: "10000"

4. **التحقق**:
   - ✅ كل الحقول الإلزامية ممتلئة
   - ✅ المبلغ رقم صحيح
   - ✅ لا توجد رسائل خطأ

5. **حفظ**:
   - انقر "حفظ وإضافة"
   - تحقق من رسالة النجاح
   - تحقق من إعادة التوجيه إلى السجل الجديد

6. **التحقق من السجل**:
   - تأكد من ظهور البيانات في صفحة القرار
   - تأكد من تطبيق المطابقة التلقائية
   - جرّب حفظ القرار

### اختبار API (cURL)

```powershell
# Test 1: إنشاء سجل كامل
curl -X POST http://localhost:8000/api/import/manual `
  -H "Content-Type: application/json" `
  -d '{
    "supplier": "شركة المراعي",
    "bank": "البنك الأهلي",
    "guarantee_number": "API-TEST-001",
    "contract_number": "CTR-API-001",
    "amount": "75000.00",
    "expiry_date": "2025-12-31",
    "type": "FINAL"
  }'

# Test 2: بيانات ناقصة (يجب أن يفشل)
curl -X POST http://localhost:8000/api/import/manual `
  -H "Content-Type: application/json" `
  -d '{"supplier": "test"}'

# Test 3: مبلغ غير صحيح (يجب أن يفشل)
curl -X POST http://localhost:8000/api/import/manual `
  -H "Content-Type: application/json" `
  -d '{
    "supplier": "test",
    "bank": "test",
    "guarantee_number": "test",
    "contract_number": "test",
    "amount": "abc"
  }'
```

---

## الملفات المتأثرة

### ملفات جديدة

| الملف | النوع | الوصف |
|------|------|-------|
| `app/Controllers/ManualEntryController.php` | PHP | Controller الإدخال اليدوي |
| `www/assets/js/manual-entry.js` | JavaScript | منطق Frontend للإدخال اليدوي |
| `docs/06-Manual-Entry.md` | Markdown | هذا الملف - الوثائق |

### ملفات معدّلة

| الملف | التعديل | السبب |
|------|---------|-------|
| `www/includes/router.php` | إضافة route لـ `/api/import/manual` | ربط API endpoint بـ Controller |
| `www/views/decision-page.php` | إضافة زر + Modal | واجهة الإدخال اليدوي |
| `www/views/decision-page.php` | إضافة `<script>` tag | تحميل `manual-entry.js` |

### ملفات غير متأثرة (Reuse)

- `app/Services/MatchingService.php` - مُعاد استخدامه
- `app/Services/CandidateService.php` - مُعاد استخدامه
- `app/Services/AutoAcceptService.php` - مُعاد استخدامه
- `app/Services/ConflictDetector.php` - مُعاد استخدامه
- `app/Models/ImportedRecord.php` - مُعاد استخدامه
- `app/Repositories/ImportSessionRepository.php` - مُعاد استخدامه
- `app/Repositories/ImportedRecordRepository.php` - مُعاد استخدامه

---

## الأسئلة الشائعة (FAQ)

### 1. هل يمكن إدخال أكثر من سجل في نفس الوقت؟
**لا**. هذه الميزة مصممة لإدخال سجل واحد فقط في كل مرة. لإدخال سجلات متعددة، استخدم:
- استيراد Excel (للسجلات الكثيرة)
- الاستيراد النصي الذكي (للنصوص المنسوخة)

### 2. ما الفرق بين السجل اليدوي وسجل Excel؟
**لا يوجد فرق** بعد الإنشاء. السجل المُنشأ يدوياً يُعامل تماماً مثل أي سجل آخر.

### 3. هل يطبق النظام المطابقة التلقائية على السجلات اليدوية؟
**نعم**. يتم تطبيق نفس `MatchingService` و `AutoAcceptService` تماماً.

### 4. ماذا يحدث إذا أدخلت مورداً أو بنكاً جديداً غير موجود؟
النظام سيحفظ الاسم كما هو في `raw_supplier_name` / `raw_bank_name` وسيترك `supplier_id` / `bank_id` فارغاً. ستحتاج لمراجعة السجل واختيار/إضافة المورد أو البنك في صفحة القرار.

### 5. هل يمكن تعديل السجل بعد إنشائه؟
**نعم**. يمكنك مراجعة السجل في صفحة القرار وتعديل اختيارات المورد والبنك تماماً مثل أي سجل آخر.

### 6. كيف أعرف أن السجل تم إنشاؤه يدوياً؟
- في حقل `comment` ستجد "إدخال يدوي" (إذا لم تدخل ملاحظة مخصصة)
- `session_id` سيكون مرتبط بجلسة من نوع `manual`

### 7. هل يمكنني استخدام الـ API مباشرة من أنظمة خارجية؟
**نعم**. يمكنك استدعاء `/api/import/manual` من أي نظام خارجي طالما أنه يرسل JSON صحيح.

---

## الصيانة والتطوير المستقبلي

### إضافات محتملة

1. **Bulk Manual Entry**: السماح بإدخال عدة سجلات في نفس الوقت
2. **Auto-complete**: إضافة auto-complete للموردين والبنوك في الـ Modal
3. **Templates**: حفظ قوالب للسجلات المتكررة
4. **Duplicate Detection**: فحص السجلات المكررة قبل الحفظ
5. **Edit Existing**: إمكانية تعديل سجل موجود من خلال نفس الـ Modal

### ملاحظات للمطورين

1. **Validation**: أي تعديل على حقول `ImportedRecord` يتطلب تحديث Validation
2. **Backward Compatibility**: السجلات اليدوية القديمة يجب أن تبقى متوافقة
3. **API Versioning**: عند تعديل API، احتفظ بالتوافق مع الإصدارات القديمة
4. **Error Messages**: رسائل الخطأ يجب أن تكون واضحة وبالعربية

---

## المراجع

- **User Guide**: [02-User-Guide.md](02-User-Guide.md)
- **Technical Reference**: [04-Technical-Reference.md](04-Technical-Reference.md)
- **Matching Engine**: [03-Matching-Engine.md](03-Matching-Engine.md)

---

**تاريخ الإنشاء**: 2025-12-18  
**الإصدار**: 1.0  
**المطور**: Antigravity AI  
**الحالة**: ✅ مكتمل ومختبر
