# 📘 Bank Guarantees Management System – Architecture & Technical Blueprint
Version 3.0 — Offline PHP Desktop Edition  
Author: Bakheet

> هذه الوثيقة الأساسية (Master Document) التي ينطلق منها كل شيء. تلخص الهدف، المكونات، وتدفقات البيانات، وتربط ببقية الأدلة التفصيلية في مجلد `docs/`.

## 1️⃣ Purpose — الهدف من المشروع
- إدارة كامل دورة حياة الضمانات البنكية أوفلاين بالكامل (بدون إنترنت).
- استيراد ملفات Excel وتنظيفها وتحويلها إلى سجلات موحدة.
- مطابقة الموردين والبنوك تلقائيًا عبر قواميس رسمية + أسماء بديلة (Alternative Names).
- عرض السجلات التي تحتاج مراجعة بشرية ومساعدة في اتخاذ القرار.
- التعلم من القرارات لتطوير القاموس وتحسين المطابقة مستقبلًا.
- إدارة الموردين/البنوك/الأسماء البديلة في لوحة قاموس مستقلة.
- أرشفة كل عملية وقرار للرجوع إليه لاحقًا.
- العمل محليًا باستخدام PHP Desktop + SQLite دون أي سيرفر خارجي.

## 2️⃣ System Approach — طريقة العمل العامة
أربع مراحل رئيسية:
1) **Data Input**: Excel (رئيسي)، إدخال يدوي، لصق (Paste). مستبعد: PDF.  
2) **Processing**: تطبيع الأسماء، توحيد القيم، تحويل مبالغ وتواريخ، تشغيل محرك المطابقة.  
3) **Review**: عرض السجلات غير الواضحة للمستخدم لاختيار المورد/البنك الصحيح، تعديل البيانات، إضافة اسم بديل، حفظ القرار.  
4) **Dictionary Learning**: بعد الحفظ يتم إنشاء Alternative Name (عند الحاجة) ويتحسن القاموس للاستيرادات القادمة.

## 3️⃣ Technical Stack — التقنية المستخدمة
- **Backend**: PHP 8+، PHP Desktop (WebView + PHP runtime)، بنية Modules واضحة.  
- **Database**: SQLite ملف واحد، علاقات Foreign Keys مفعلة، فهارس على الحقول المطبعّة.  
- **Frontend**: HTML5، TailwindCSS (ملف مبني واحد)، JavaScript ES Modules، بدون React/Vue/API خارجي. التواصل عبر `fetch()` مع PHP داخليًا.  
- **Libraries**: PhpSpreadsheet (Excel)، mbstring/intl (عربية)، Tailwind CLI (بناء CSS مرة واحدة).

## 4️⃣ System Architecture — المعمارية
- **Presentation Layer**: قوالب HTML، TailwindCSS، JS Modules، مكونات (Tables, Modals, Panels) ترسل طلبات `fetch` وتعرض النتائج مباشرة.  
- **Application Layer**: Controllers / Services / Repositories + Models بسيطة (بدون ORM) / Helpers لمعالجة Excel، المطابقة، المراجعة، القاموس، إنشاء الأسماء البديلة.  
- **Data Layer**: SQLite، ملفات migrations/seeders، Models بسيطة بدون ORM، CRUD كامل.

## 5️⃣ Project Structure (مقترح ورسمي)
```
project/
├─ www/                        # جذر PHP Desktop (index.php يحوي المسارات)
│  ├─ index.php
│  ├─ assets/
│  │   ├─ css/style.css        # ملف Tailwind المبني النهائي
│  │   └─ js/ (app.js, import.js, review.js, dictionary.js)
│  └─ views/ (layout, dashboard, import, review, dictionary, settings)
├─ app/
│  ├─ Controllers/ (Dashboard, Import, Review, Dictionary, Settings)
│  ├─ Services/ (Import, Matching, ReviewWorkflow, AlternativeNames, Dictionary)
│  ├─ Helpers/ (Normalize, Date, Number, Logger)
│  ├─ Models/ (Supplier, SupplierAlternativeName, GuaranteeRecord, Bank)   # Models بسيطة فوق الـ Repositories
│  └─ Database/ (migrations.sql, seeders.sql, db.php)
├─ storage/
│  ├─ database/app.sqlite
│  ├─ logs/
│  └─ uploads/
└─ tailwind.config.js
```

## 6️⃣ Database Architecture — الجداول الأساسية
- **suppliers**: id, official_name, display_name, normalized_name, timestamps, is_confirmed.  
- **supplier_alternative_names**: id, supplier_id (FK), raw_name, normalized_raw_name, source (manual/import), occurrence_count, last_seen_at.  
- **supplier_overrides**: id, supplier_id, override_name, notes, created_at.  
- **banks**: بنية مماثلة لـ suppliers.  
- **imported_records**: id, session_id, supplier_id, bank_id، الحقول الخام والمطبعّة، الحالة، المبالغ والتواريخ.  
- **import_sessions**: تتبع جلسات الاستيراد (excel/manual/paste).  
- **learning_log**: يتابع قرارات التعلم والاقتراحات.  
- فهارس على الحقول المطبعّة، وتفعيل `PRAGMA foreign_keys = ON`.

## 7️⃣ Data Flows — تدفق البيانات
- **Excel Import**: المستخدم يرفع ملف → PhpSpreadsheet يقرأ → Helpers تنظف القيم → MatchingService يحدد المورد/البنك → إنشاء ImportedRecord في SQLite → غير الواضح يذهب للمراجعة.  
- **Review Flow**: استرجاع السجلات غير المكتملة → المستخدم يختار المورد/يضيف اسم بديل → حفظ يحدّث السجل ويسجل العملية (ويضيف Alternative Name عند التفعيل).  
- **Dictionary Flow**: إنشاء/تعديل/حذف مورد، إضافة أسماء بديلة، ربط أسماء بموارد، إدارة Overrides.

## 8️⃣ Matching Logic — منطق المطابقة
الترتيب الرسمي: **Overrides → Official → Confirmed Alternatives → Learning Alternatives → Fuzzy** بعد التطبيع.  
التطبيع: إزالة التشكيل/الرموز، توحيد الهمزات، تحويل الأرقام العربية/الإنجليزية، lowercase للإنجليزي، طمس المسافات الزائدة.

## 9️⃣ User Interface Logic
- TailwindCSS + Vanilla ES Modules + Fetch.  
- شاشات: استيراد، مراجعة، قاموس، إعدادات.  
- مكونات رئيسية: Modals, Tables, Dropdowns, Supplier Selector, Date Input, Amount Formatter.

## 🔟 Error Handling
- كل Controller يعيد JSON موحد:
  ```json
  { "success": false, "error_code": "INVALID_DATE", "message": "The date format is not valid" }
  ```
- تسجيل الأخطاء في `storage/logs/app.log`.

## 1️⃣1️⃣ Distribution — التوزيع
- مجلد واحد يحتوي: PHP Desktop، public، app، storage، database.  
- النسخة تعمل مباشرة بدون تثبيت، مع CSS/JS مبنيين مسبقًا.

## 1️⃣2️⃣ Security — الأمان
- لا اتصال إنترنت أو API خارجي.  
- الملفات المقبولة: Excel فقط.  
- SQLite ضمن `storage/database/app.sqlite`.  
- Tailwind مبني مسبقًا (لا تحميل خارجي).

## 1️⃣3️⃣ Scalability — القابلية للتوسع
- خفيف وسريع على أجهزة متعددة.  
- قابل للترقية لاحقًا إلى Web SaaS دون إعادة كتابة من الصفر.

## 1️⃣4️⃣ Conclusion — الخلاصة
هذا هو الأساس الرسمي لبناء النسخة الجديدة باستخدام PHP Desktop + SQLite + TailwindCSS + PhpSpreadsheet، مع بنية منظمة، واضحة، وقابلة للتطوير، تفصل بين المنطق والواجهة.

## روابط إلى الوثائق التفصيلية
- نظرة عامة وميزات: `docs/00-Overview/`.  
- الإعداد: `docs/01-Setup/`.  
- دليل المستخدم: `docs/02-User-Guide/`.  
- دليل المطور وتدفق الباك/الفرونت: `docs/03-Developer-Guide/`.  
- المخطط وقواعد DB: `docs/04-Database/`.  
- محرك المطابقة: `docs/05-Matching-Engine/`.  
- معالجة Excel: `docs/06-Excel-Processing/`.  
- تصميم الواجهة: `docs/07-UI-Design/`.  
- الاختبارات: `docs/08-Testing/`.  
- الإصدارات والتوزيع: `docs/09-Release-Management/`.  
- الملحق: `docs/10-Appendix/`.
