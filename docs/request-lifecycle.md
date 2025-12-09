# Request Lifecycle — دورة حياة الطلب

هذه الوثيقة تشرح ما يحدث من لحظة ضغط المستخدم على زر في الواجهة، حتى يتم حفظ القرار في قاعدة البيانات والرد عليه.

## 1. مثال: استيراد ملف Excel

1. المستخدم يضغط زر **استيراد ملف Excel** في واجهة السجلات.
2. الواجهة ترسل طلب:
   - `POST /api/import/excel`
   - Body: الملف (multipart/form-data).
3. في الباك‑إند:
   - `ImportController` يستدعي `ImportService`.
   - الـ Service:
     - يحفظ معلومات الجلسة في جدول `import_sessions`.
     - يقرأ الصفوف باستخدام `ExcelImportHelper`.
     - يمر على كل صف، يطبع الأسماء (`normalizeName`).
     - يستدعي `MatchingService`.
     - ينشئ سجلات في `imported_records`.
   - في النهاية يرجع مصفوفة السجلات مع حالة كل واحد:
     - جاهز (auto).
     - يحتاج مراجعة.
4. الواجهة تعرض النتائج في قائمة السجلات.

## 2. مثال: اتخاذ قرار على سجل

1. المستخدم يفتح سجل في واجهة المراجعة ويعدّل الاسم العربي أو يختار مورد مختلف.
2. عند الحفظ:
   - إرسال `POST /api/records/{id}/decision`
   - Body:
     - `supplier_id` أو `new_supplier_name`
     - `bank_id` (إن تغيّر)
     - ملاحظات القرار (اختياري).
3. في الباك‑إند:
   - `ReviewController::saveDecision`
   - يستدعي `ReviewWorkflowService::applyDecision`.
   - الـ Service:
     - يحدّث سجل `imported_records`.
     - إذا هناك اسم جديد:
       - ينشئ Alternative Name في `supplier_alternative_names`.
     - يسجّل الحركة في `learning_log` أو سجل تدقيق داخلي.
4. يتم إرجاع السجل بعد التحديث، والواجهة تعكس الحالة الجديدة.

## 3. مثال: إدارة القاموس (مورد جديد)

1. من شاشة القاموس، المستخدم يضغط "إضافة مورد جديد".
2. الطلب:
   - `POST /api/dictionary/suppliers`
   - Body:
     - `official_name`
     - `display_name_ar`
3. في الباك‑إند:
   - `DictionaryController::createSupplier`
   - يستدعي `DictionaryService::createSupplier`.
   - يتم:
     - إدخال سجل جديد في `suppliers`.
     - إنشاء normalized_name تلقائياً.
