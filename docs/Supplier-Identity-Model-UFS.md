# Supplier Identity Model – Unified Final Specification (SIM-UFS v1.0)
مرجع نهائي وملزِم لمنطق الموردين، يدمج ويحل كل التعارضات السابقة. خاص بالموردين فقط ولا يغيّر منطق البنوك.

---
## 0) الهدف
- توحيد الأسماء والجداول والعتبات.
- إضافة طبقة تعلّم بسيطة (alias/blocked) بدون خلط مع البنوك.
- حماية السجلات التاريخية بتجميد العرض.
- رفع دقة المطابقة وتقليل التكرار.
- تنفيذ تدريجي دون كسر النظام الحالي.

---
## 1) جدول التعلّم النهائي (Mandatory)
اسم الجدول: `supplier_aliases_learning`

الحقول:
| العمود | النوع | الوظيفة |
| --- | --- | --- |
| learning_id | INTEGER PK | معرف السجل |
| original_supplier_name | TEXT NOT NULL | الاسم كما جاء من Excel |
| normalized_supplier_name | TEXT NOT NULL UNIQUE | الاسم بعد التطبيع (مفتاح القرار) |
| learning_status | TEXT CHECK('supplier_alias','supplier_blocked') | حالة التعلم النهائية |
| linked_supplier_id | INTEGER NOT NULL | المورد المرتبط بالحكم (حتى في حالة blocked) |
| learning_source | TEXT DEFAULT 'review' | مصدر القرار (اختياري) |
| updated_at | DATETIME DEFAULT CURRENT_TIMESTAMP | آخر تعديل |

القيم:
- `supplier_alias` → الاسم ينتمي لهذا المورد حصريًا.
- `supplier_blocked` → الاسم مرفوض لهذا المورد المحدد فقط (لا يوجد حظر عام بلا مورد).

قواعد:
- normalized_supplier_name فريد → آخر قرار هو الحقيقة (upsert).
- لا يوجد حظر عام: يجب أن يكون linked_supplier_id محدداً لكل سجل.
- استخدام learning_source لا يغيّر المنطق لكنه يفيد التتبع.

---
## 2) التجميد (Display Freeze)
- عمود العرض المجمّد في imported_records: `supplier_display_name`.
- يُعبّأ عند أول قرار نهائي (matched/alias).
- العرض في /records:
  1) إذا supplier_display موجود → يُعرض.
  2) وإلا إذا supplier_id موجود → يُعرض الاسم الرسمي الحالي.
  3) وإلا → يُعرض الاسم الخام من Excel.

---
## 3) هوية المورد (Identity Key)
- الحقل النهائي في suppliers: `supplier_normalized_key`.
- إجباري تعبئته الآن بقيمة الاسم بعد التطبيع/التصفية (يمكن تحسينه لاحقًا).
- حد أدنى للطول بعد التطبيع: 5 أحرف (رفض إن أقل).

---
## 4) التطبيع (Normalization)
الدالة الرسمية: `SupplierNameNormalizer::normalizeSupplierName($name)`
- lowercase
- إزالة الرموز (., -, _, / …)
- توحيد الهمزات
- تبسيط التاء المربوطة
- إزالة الكلمات العامة (شركة، مؤسسة، Trading، Est، Ltd، Co …)
- دمج المسافات
- normalizeSupplierKey: يُجهّز لكن يمكن تأجيل استخدامه حاليًا.

---
## 5) منطق المطابقة (Pipeline)
ترتيب إلزامي:
1) Learning: `supplier_aliases_learning`
   - supplier_alias → تطابق مباشر.
- supplier_blocked + linked_id → تجاهل المورد المحدد (لا حظر عام بلا مورد).
2) Overrides: استخدام overrides الحالي كما هو (لعدم كسر النظام).
3) Exact Official: suppliers.normalized_name.
4) Exact Alternative: supplier_alternative_names.normalized_raw_name.
5) Fuzzy:
   - Strong ≥ 0.90
   - Weak ≥ 0.80 (عرض بتحذير)
   - < 0.80 → يُتجاهل.
نتيجة عدم التطابق: Needs Review.

---
## 6) منع التكرار (Dedup Policy)
- similarity = 1.0 → منع الإنشاء نهائيًا.
- similarity ≥ 0.90 → تحذير، يُسمح فقط إذا أصر المستخدم.
- < 0.90 → يُسمح بدون تحذير.
- منع normalized_name/normalized_key القصير (<5 أحرف).
- فهارس مطلوبة:
  - INDEX على suppliers.normalized_name
  - INDEX على supplier_alternative_names.normalized_raw_name
  - INDEX على supplier_aliases_learning.normalized_supplier_name

---
## 7) التعلّم (Write/Read)
- تسجيل التعلم بعد قرار المستخدم فقط (لا auto-learn أثناء الاستيراد).
- Upsert على normalized_supplier_name (آخر قرار يسود).
- الاستخدام في بداية المطابقة لتقليل “needs_review” المتكرر.
- حالات التسجيل:
  - alias: learning_status = supplier_alias، linked_supplier_id = المورد.
  - blocked: learning_status = supplier_blocked، linked_supplier_id = المورد (لا حظر عام).

---
## 8) العتبات النهائية للفزي
- Strong = 0.90
- Weak = 0.80
- Reject < 0.80
- عرض الفزي منفصل مع تحذير واضح، بدون Auto-accept.

---
## 9) خارطة التنفيذ (Phases)
1) DB Prep: إنشاء supplier_aliases_learning، إضافة supplier_display_name، supplier_normalized_key، والفهارس.
2) Normalization Update: تفعيل normalizeSupplierName الجديدة.
3) Fuzzy Improvement: رفع العتبات، فصل عرض المرشحين قوي/ضعيف.
4) Write Learning: تسجيل alias/blocked بعد قرارات المستخدم.
5) Read Learning: فحص supplier_aliases_learning قبل القاموس.
6) Freeze Activation: استخدام supplier_display_name للعرض.
7) Dedup Enforcement: تطبيق سياسات التشابه والطول.
8) (اختياري) UI Simplification: زر واحد “إدارة المورد” بعد استقرار المنطق.

---
## 10) نقاط الحسم النهائية
- جدول التعلّم: supplier_aliases_learning (حقول موحّدة).
- قيم الحالة: supplier_alias / supplier_blocked.
- حقل العرض: supplier_display_name.
- حقل الهوية: supplier_normalized_key (مستخدم الآن، تحسين لاحق مسموح).
- التطبيع: نسخة واحدة رسمية.
- overrides: تُستخدم كما هي (لعدم كسر السلوك الحالي).
- عتبات الفزي: 0.90 / 0.80.
- منع التكرار: similarity ≥ 0.90 + حد أدنى للطول 5.
- المراحل: قائمة موحّدة أعلاه.

---
## 11) المخاطر/الملاحظات
- دمج الأزرار قبل تنفيذ التعلم/التجميد يرفع خطر التكرار والربط الخاطئ.
- رفع العتبة يقلل المرشحين؛ تأكد من فصل عرض الفزي بتحذير.
- supplier_aliases_learning يُستخدم فقط بعد قرار المستخدم (لا تسجيل تلقائي).
- supplier_display_name يحمي السجلات القديمة؛ بدونه قد تتغير أسماء الموردين عند تعديل القاموس.
