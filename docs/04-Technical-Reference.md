# 04 - المرجع التقني (Technical Reference)

## 1. قاعدة البيانات (Database Schema)
يستخدم النظام SQLite ملف `storage/database.sqlite`.

### الجداول الرئيسية:
> [!IMPORTANT]
> لاحظ الاختلاف الجذري في الهيكلة بين الموردين والبنوك.

1. **suppliers**:
   - `official_name`: الاسم الرسمي.
   - `normalized_name`: الاسم بعد المعالجة (بدون همزات، مسافات...).
   - **مرتبط بـ**: `supplier_alternative_names` (جدول منفصل يحتوي مئات الأسماء البديلة).

2. **banks**:
   - `official_name`: الاسم العربي.
   - `official_name_en`: الاسم الإنجليزي (عمود في نفس الجدول).
   - `short_code`: الرمز (مثل RIB).
   - **ملاحظة**: البنوك "ذكية" بذاتها ولا تحتاج لجدول بدائل ضخم مثل الموردين.

3. **supplier_alternative_names**:
   - المحرك الأساسي لذكاء الموردين. يربط `raw_name` (من Excel) بـ `supplier_id`.
   
4. **imported_records**: السجلات المستوردة من Excel.
  - **ملاحظة**: لا يوجد مفتاح فريد (UNIQUE Constraint) على `guarantee_number` للسماح بالتكرار (History).
- `learning_logs`: سجلات لتدريب النظام (Audit Trail).

## 2. API Endpoints
جميع استجابات الـ API تكون بصيغة JSON.

- `GET /api/records`: جلب السجلات.
- `POST /api/records/{id}/decision`: حفظ قرار المستخدم.
- `GET /api/settings`: جلب الإعدادات.
- `POST /api/settings`: حفظ الإعدادات.
- `POST /api/dictionary/suppliers`: إضافة مورد جديد.

## 3. آلية الاستيراد (Import Logic)
الكلاس المسؤول: `App\Services\ImportService`.
1. قراءة الملف باستخدام `SimpleXLSX`.
2. استبعاد الصفوف الفارغة.
3. التكرار البرمجي (Duplicate Check) **معطل حالياً** للسماح بالأرشفة التاريخية.
4. حساب التطابق الأولي (Initial Matching) وتخزين الحالة `match_status`.
