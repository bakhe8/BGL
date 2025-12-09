# Naming Conventions (ملخص مطور)
المرجع التفصيلي في `docs/naming-conventions.md`. هذا الملف يختصر أهم القواعد.

- الكلاسات/الملفات: PascalCase، ملف = اسم كلاس (مثال: `SupplierRepository.php`, `ImportService.php`).  
- الدوال والمتغيرات: camelCase بوصف واضح (`matchSupplierName`, `$normalizedName`).  
- الجداول والأعمدة: snake_case جمعًا عند الحاجة (`supplier_alternative_names`, `normalized_raw_name`).  
- الثوابت: UPPER_SNAKE_CASE (`MATCH_SCORE_AUTO_THRESHOLD`).  
- المسارات: واضحة ومعبرة (`POST /api/import/excel`, `POST /api/records/{id}/decision`).  
- أسماء الواجهة: ملفات HTML توضح الشاشة (`review-workbench.html`, `dictionary-suppliers.html`).

مبدأ ذهبي: الاسم يجيب على سؤال *ماذا يفعل؟* بدون اختصارات غامضة. التزم بـ `SupplierAlternativeName*` لكل ما يخص الأسماء البديلة.
