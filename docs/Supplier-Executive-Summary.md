# Executive Summary — Supplier Matching Overhaul v1.1
> ملاحظة: المرجع النهائي الملزِم هو مستند SIM-UFS v1.0 (`docs/Supplier-Identity-Model-UFS.md`). هذا الملخص يبقى كصفحة تعريف سريعة، بينما التفاصيل النهائية موجودة في UFS.
نسخة مختصرة/صفحة واحدة خاصة بالموردين فقط.

## الهدف
- دقة أعلى، ضوضاء أقل.
- تعلّم من قرارات المستخدم (alias/blocked).
- حماية السجلات القديمة (عرض مجمّد).
- منع التكرار والربط الخاطئ، دون المساس بباقي البرنامج.

## 1) تغييرات قاعدة البيانات (Suppliers Only)
- جدول تعلّم: `supplier_name_learning`
  - learning_id PK
  - original_supplier_name
  - normalized_supplier_name UNIQUE
  - learning_status: 'supplier_alias' | 'supplier_blocked'
  - linked_supplier_id (لـ alias فقط)
  - updated_at
  - القواعد: normalized_supplier_name فريد؛ alias = ينتمي، blocked = لا ينتمي (أو مرفوض إن كان supplier_id NULL).

- حقل تجميد العرض: `supplier_display_name` في `imported_records`
  - عرض الاسم المعتمد وقت المراجعة حتى لو تغيّر القاموس لاحقاً.

- حقل هوية نصية اختياري: `supplier_normalized_key` في `suppliers`
  - للتمييز بين موارد متشابهة عند الحاجة.

## 2) قواعد التطبيع (Supplier Normalization)
- دالة مقترحة: `SupplierNameNormalizer::normalizeSupplierName($name)`
  - lowercase
  - إزالة الترقيم والرموز
  - توحيد الهمزات (أ/إ/آ → ا) والتاء المربوطة
  - حذف الكلمات العامة مثل: شركة، مؤسسة، Trading، Est، Ltd، Co…
  - حذف المسافات المكررة

## 3) منطق المطابقة (Pipeline)
1) **supplier_name_learning**: alias → ربط مباشر، blocked → منع الاقتراح.
2) Exact Match: suppliers.normalized_name.
3) Exact Alternative: supplier_alternative_names.normalized_raw_name.
4) Fuzzy:
   - Strong ≥ 0.90
   - Weak ≥ 0.80 مع تحذير
   - أقل من ذلك → Needs Review.

## 4) طبقة التعلّم (Supplier Learning Layer)
- عند المراجعة:
  - اختيار مورد: سجل supplier_alias (status='supplier_alias', linked_supplier_id=…).
  - رفض مورد: سجل supplier_blocked مع linked_supplier_id.
  - “مورد غير معروف”: supplier_blocked مع linked_supplier_id = NULL.
- آخر قرار هو الساري (ON CONFLICT DO UPDATE).

## 5) منع التكرار (Dedup Policy)
- عند إنشاء مورد جديد:
  - تطبيع الاسم الجديد.
  - similarity = 1.0 → منع الإنشاء.
  - similarity ≥ 0.90 → تحذير؛ يُسمح بالإنشاء فقط إذا أصرّ المستخدم.

## 6) مراحل التنفيذ (بدون كسر النظام)
1) إضافة الجداول/الأعمدة الجديدة فقط.
2) رفع عتبة الفزي وفصل المرشحين الضعفاء في الواجهة.
3) تفعيل تسجيل التعلّم (Write Only).
4) تفعيل قراءة التعلّم في المطابقة (Read + Write).
5) تفعيل `supplier_display_name`.
6) تفعيل منع التكرار عند الإضافة.
7) (اختياري) توحيد زر “إدارة المورد”.

## 7) النتيجة المتوقعة
- انخفاض كبير في أخطاء المراجعة.
- عدم عرض نفس الاسم للمراجعة مرتين (بعد التعلم).
- ثبات أسماء الموردين في السجلات القديمة.
- تقليل التكرار في جدول الموردين.
- مطابقة أوضح وأكثر أماناً، منطق مستقل ومتوقع.
