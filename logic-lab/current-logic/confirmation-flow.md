# Current Logic: Confirmation Flow

**Documented:** 2025-12-21  
**Related Problem:** `problems/manual-confirmation.md`

---

## التسلسل الحالي (As-Is)

```
┌────────────────────────────────────┐
│ Step 1: Load Decision Page         │
│ URL: /?view=decision&record_id=X   │
└──────────────┬─────────────────────┘
               ↓
┌────────────────────────────────────┐
│ Step 2: Display Interface          │
│ - 4 Decision Cards (always visible)│
│ - AI Recommendation (visible)      │
│ - Metadata (supplier, bank, etc)   │
└──────────────┬─────────────────────┘
               ↓
┌────────────────────────────────────┐
│ Step 3: User Selects One Option    │
│ Options: موافقة | تمديد | رفض | تعليق │
│ Action: Click on card              │
└──────────────┬─────────────────────┘
               ↓
┌────────────────────────────────────┐
│ Step 4: Show Confirmation Modal    │
│ Text: "هل أنت متأكد؟"              │
│ Buttons: [تأكيد] [إلغاء]           │
└──────────────┬─────────────────────┘
               ↓
      User clicks [تأكيد]
               ↓
┌────────────────────────────────────┐
│ Step 5: Validate Decision          │
│ POST /api/decisions/validate.php   │
│ Body: {id, supplier_id, bank_id}   │
└──────────────┬─────────────────────┘
               ↓
        {valid: true}
               ↓
┌────────────────────────────────────┐
│ Step 6: Save Decision               │
│ POST /api/decisions/save.php       │
│ Body: {id, decision, confirmed: true}│
└──────────────┬─────────────────────┘
               ↓
      {success: true}
               ↓
┌────────────────────────────────────┐
│ Step 7: Timeline Update             │
│ Log event: "decision_made"         │
│ Snapshot: record state             │
└──────────────┬─────────────────────┘
               ↓
┌────────────────────────────────────┐
│ Step 8: Redirect to Next Record     │
│ or: Show success message           │
└────────────────────────────────────┘
```

**Total:** 8 steps, 4 user interactions, 2 API calls

---

## الحالات الموجودة (Existing Scenarios)

### Case 1: Normal Flow (Happy Path)
```
User → Select → Confirm → Validate ✓ → Save ✓ → Done
Time: ~120s
Result: Success
```

### Case 2: User Cancels at Confirmation
```
User → Select → Confirm → [إلغاء]
Result: Returns to Step 3
```

### Case 3: Validation Fails
```
User → Select → Confirm → Validate ✗ → Error
Message: "بيانات غير صحيحة"
Result: Returns to Step 3
```

### Case 4: Save Fails
```
User → Select → Confirm → Validate ✓ → Save ✗ → Error
Message: "فشل الحفظ"
Result: Can retry
```

### Case 5: Network Error
```
User → Select → Confirm → [Network timeout]
Message: "تحقق من الاتصال"
Result: Can retry
```

---

## القرارات المضمنة في المنطق الحالي

### 1. Confirmation is Mandatory
```php
// في decision.js
function saveDecision() {
    if (!confirmed) {
        showConfirmationModal();
        return; // لا يمكن التجاوز
    }
    // proceed...
}
```

**Assumption:** المستخدم قد يخطئ، التأكيد ضروري دائماً

### 2. All Options Always Visible
```php
// في decision-page.php
<div class="decision-cards">
    <div class="card">موافقة</div>  <!-- دائماً ظاهر -->
    <div class="card">تمديد</div>  <!-- دائماً ظاهر -->
    <div class="card">رفض</div>    <!-- دائماً ظاهر -->
    <div class="card">تعليق</div>  <!-- دائماً ظاهر -->
</div>
```

**Assumption:** المستخدم يحتاج رؤية كل الخيارات دائماً

### 3. Validation Separate from Save
```php
// API Structure
POST /api/decisions/validate.php  // Step 1
  ↓
{valid: true/false}
  ↓
POST /api/decisions/save.php      // Step 2
  ↓
{success: true/false}
```

**Assumption:** Validation يجب أن تكون منفصلة للأمان

### 4. AI Recommendation is Informative Only
```php
// في DataAccess.php
$aiRec = getAIRecommendation($recordId);
// يُعرض للمستخدم
// لكن لا يُستخدم في القرار
```

**Assumption:** AI تساعد لكن لا تُنفذ

---

## الافتراضات (Assumptions)

### تقنية:
1. **المستخدم دائماً يحتاج التأكيد**
   - حتى لو كان واثقاً 100%
   
2. **Validation منفصلة أكثر أماناً**
   - على الرغم من إضافة latency

3. **كل الخيارات يجب أن تكون ظاهرة**
   - Progressive disclosure غير مُطبق

### تجربة المستخدم:
1. **المستخدم يراجع كل الخيارات**
   - قبل الاختيار

2. **Confirmation Modal يمنع الأخطاء**
   - على الرغم من الإحباط

3. **AI توصية فقط، ليست قرار**
   - المستخدم يجب أن يختار يدوياً

---

## البيانات المُرسلة والمُستقبلة

### Request 1: Validate

**POST** `/api/decisions/validate.php`

```json
{
  "record_id": 14002,
  "supplier_id": 123,
  "bank_id": 45,
  "match_status": "approved"
}
```

**Response:**
```json
{
  "valid": true,
  "errors": []
}
```

### Request 2: Save

**POST** `/api/decisions/save.php`

```json
{
  "record_id": 14002,
  "supplier_id": 123,
  "bank_id": 45,
  "match_status": "approved",
  "confirmed": true  // ← إلزامي
}
```

**Response:**
```json
{
  "success": true,
  "message": "تم الحفظ بنجاح"
}
```

---

## ال Dependencies (الاعتماديات)

### Frontend:
- `decision.js` - يدير التدفق
- `decision-page.php` - الواجهة
- Modal system - للتأكيد

### Backend:
- `DecisionController.php` - routing
- `validate.php` endpoint
- `save.php` endpoint
- `TimelineEventService.php` - logging

### Database:
- `imported_records` - البيانات
- `guarantee_timeline_events` - السجل

---

## النقاط الحرجة (Critical Points)

### 1. Modal Dependency
```javascript
// في decision.js
if (!userConfirmed) {
    return;  // ← يمنع أي تقدم
}
```

**Impact:** لا توجد طريقة لتجاوز الـ modal

### 2. Separate Validation
```javascript
validate()
  .then(() => save())  // ← 2 network calls
```

**Impact:** Latency + إمكانية فشل منفصلة

### 3. No Decision Source Tracking
```php
// لا يوجد حقل "source" في البيانات
// لا نعرف: هل القرار من AI أم يدوي؟
```

**Impact:** لا يمكن تحليل accuracy AI

---

## Performance Metrics (Current)

| Metric | Value | Notes |
|--------|-------|-------|
| User Time | ~120s | 30s thinking + 90s process |
| Network Calls | 2 | validate + save |
| Total Latency | ~300ms | 150ms × 2 |
| User Clicks | 4 | choose + confirm + ok + next |
| Steps | 8 | من load إلى redirect |

---

## الملاحظات (Observations)

### ما يعمل جيداً:
- ✅ Timeline logging يعمل بشكل متسق
- ✅ Validation تمنع بيانات خاطئة
- ✅ Error handling موجود

### ما يمكن تحسينه:
- ⚠️ Confirmation دائماً، حتى للحالات الواضحة
- ⚠️ API calls منفصلة تضيف latency
- ⚠️ AI recommendation لا تُستغل
- ⚠️ لا توجد مرونة في التدفق

---

## Code References

### Frontend:
- `www/assets/js/pages/decision-page.js` (lines 145-290)
- `app/Views/pages/decision-page.php` (lines 200-450)

### Backend:
- `app/Controllers/DecisionController.php::saveDecision()` (lines 150-200)
- `app/Services/TimelineEventService.php` (lines 50-100)

---

📌 **هذا ما يحدث فعلياً - لا تفسيرات، لا أحكام**

**Next:** راجع `proposed-logic/implicit-confirmation.md` للبديل المقترح
