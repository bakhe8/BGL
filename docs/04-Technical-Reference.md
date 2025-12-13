---
last_updated: 2025-12-13
version: 2.0
status: active
---

# 02 - المرجع التقني (Technical Reference)

يوفر هذا المرجع تفاصيل تقنية حول بنية النظام، قاعدة البيانات، والـ API.

## 1. قاعدة البيانات (Database Schema)

يستخدم النظام **SQLite** في ملف `storage/database.sqlite`.

### الجداول الرئيسية (Core Tables)

> [!IMPORTANT]
> لاحظ الاختلاف الجذري في الهيكلة بين الموردين والبنوك.

1. **`suppliers`**:
   - `official_name`: الاسم الرسمي.
   - `normalized_name`: الاسم بعد المعالجة (Lowercased, Trimmed).
   - العلاقة: **Many-to-One** مع `supplier_alternative_names`.

2. **`supplier_alternative_names`** (عقل النظام):
   - يربط الأسماء الخام (`raw_name`) من ملفات Excel بـ `supplier_id`.
   - هو الجدول الذي ينمو مع "تعلم" النظام.

3. **`banks`**:
   - `official_name`: الاسم العربي.
   - `official_name_en`: الاسم الإنجليزي.
   - `short_code`: الرمز (مثل RIB, SNB).
   - البنوك لا تحتاج عادةً لجدول بدائل لأن قائمتها محدودة ومعروفة.

4. **`imported_records`**:
   - السجلات الخام المستوردة.
   - لا يوجد مفتاح فريد (Unique Key) على رقم الضمان، للسماح بالأرشفة التاريخية (Snapshots).

---

## 2. API Reference

جميع الاستجابات تكون بصيغة JSON.

### الردود القياسية (Standard Responses)

**النجاح (Success):**
```json
{"success": true, "data": { ... }}
```

**الفشل (Error):**
```json
{"success": false, "error": "Error message"}
```

### نقطة النهاية: `/api/records/{id}/candidates`

هذه أهم نقطة نهاية في النظام، وتستخدم لجلب الاقتراحات للمطابقة.

**بنية الاستجابة (Response Structure):**

```json
{
  "success": true,
  "data": {
    "supplier": {
      "normalized": "اسم المورد المعالج",
      "candidates": [
        {
          "source": "official|alternative|learning|fuzzy",
          "supplier_id": 123,
          "name": "اسم المورد المقترح",
          "score": 0.95
        }
      ]
    },
    "bank": {
      "normalized": "اسم البنك المعالج",
      "candidates": [ ... ]
    },
    "conflicts": []
  }
}
```

> [!WARNING]
> **خطأ شائع جداً**: المصفوفة اسمها `candidates` داخل كائن `supplier`، وليست `suppliers` مباشرة.
> - ✅ Correct: `data.supplier.candidates`
> - ❌ Wrong: `data.suppliers`

---

## 3. آلية الاستيراد (Import Logic)

الكلاس المسؤول: `App\Services\ImportService`.

1. **القراءة**: استخدام مكتبة `SimpleXLSX`.
2. **التنظيف**: تجاهل الصفوف الفارغة تماماً.
3. **التاريخ**: السماح بالتكرار (لغرض الأرشفة).
4. **المعالجة الأولية**: تشغيل `CandidateService` فوراً لحساب حالة المطابقة الأولية (`match_status`).

---

## 4. المجلدات والملفات (Directory Structure)

- `app/`: كود الواجهة الخلفية (PHP).
  - `Services/`: منطق العمل (Business Logic).
  - `Repositories/`: التعامل مع قاعدة البيانات.
  - `Support/`: أدوات مساعدة (Normalizer, Settings).
- `storage/`: البيانات المتغيرة (DB, Uploads, Logs).
- `www/`: الواجهة الأمامية (Public).

## 5. واجهة المستخدم (Frontend Architecture)

يعتمد النظام الآن على **Single Interface Architecture** (واجهة القرار الموحدة).

### الملف الرئيسي: `decision.js`
هو المتحكم الرئيسي في الواجهة (`assets/js/decision/decision.js`) ويعمل ضمن الـ Namespace `BGL.Decision`.

**المسؤوليات:**
1. **إدارة الحالة (State):** يحتفظ بالسجلات، المؤشر الحالي، والقوائم المساعدة.
2. **الاستيراد (Import):** يرسل الملفات إلى `/api/import/excel`.
3. **التحديث (Refresh):** يعيد تحميل البيانات وتصفية "آخر جلسة" (`latestSessionId`).
4. **المطابقة (Matching):**
   - يجلب الاقتراحات من `/api/records/{id}/candidates`.
   - يدمجها مع القاموس المحلي (`localStorage`/Memory).
5. **التعلم والحفظ**:
   - يرسل القرار إلى `/api/records/{id}/decision`.
   - يدعم **Hybrid Case** (يقبل الـ Backend الآن `match_status` و `matchStatus`).
   - يرسل الأسماء الخام (`raw_name`) لتمكين التعلم.

---

