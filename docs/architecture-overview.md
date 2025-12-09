# Backend Architecture Overview
نظرة عامة على معمارية الباك‑إند في النظام الجديد.

## 1. طبقات الباك‑إند

الباك‑إند مقسوم إلى 4 طبقات رئيسية:

1. **Routing Layer (www/index.php)**
   - تستقبل الـ HTTP Request (GET/POST).
   - توجّه الطلب إلى الـ Controller المناسب.
   - لا تحتوي أي منطق عمل حقيقي.

2. **Controller Layer (app/Controllers)**
   - وظيفتها: ربط الـ Request بالـ Service المناسب.
   - تقوم بقراءة المدخلات من الطلب، واستدعاء Service، ثم تجهيز الـ Response (JSON أو HTML).
   - لا تتعامل مباشرة مع قاعدة البيانات.

3. **Service Layer (app/Services)**
   - تحتوي منطق العمل الفعلي (Business Logic):
     - مطابقة الموردين والبنوك.
     - التعامل مع Alternative Names.
     - بناء مجموعات المراجعة (Review Groups).
   - تستدعي Repositories للوصول للبيانات.

4. **Repository Layer (app/Repositories)**
   - الطبقة الوحيدة التي تتعامل مع SQLite مباشرة.
   - توفر دوال مثل:
     - `findSupplierById($id)`
     - `searchSuppliersByName($normalizedName)`
     - `saveAlternativeName(...)`
   - أي استعلام SQL يجب أن يكون داخل Repository وليس داخل Service أو Controller.

## 2. العلاقة بين الطبقات

التسلسل النموذجي:

1. المستخدم يرفع ملف Excel أو يلصق نص أسماء.
2. الـ Controller يستقبل الطلب وينادي Service مثل:
   - `ImportService::handleExcelUpload(...)`
3. الـ Service يقوم بـ:
   - Parsing الملف.
   - Normalization للأسماء.
   - استدعاء Repositories لتطبيق المطابقة.
   - تكوين سجلّات المراجعة (Review Records) في قاعدة البيانات.
4. الـ Controller يعيد JSON فيه:
   - قائمة السجلات.
   - حالة كل سجل (جاهز / يحتاج قرار / فيه خطأ).

## 3. ملفات أساسية في الباك‑إند

المقترح أن تكون الملفات كالتالي:

- `www/index.php`  
  نقطة الدخول الوحيدة للتطبيق. تهيئة الـ autoload، إعداد الاتصال بـ SQLite، تحميل ملف routes.

- `www/index.php`  
  يحتوي جميع المسارات، مثلاً:
  - `POST /import/excel`
  - `POST /records/decision`
  - `GET  /dictionary/suppliers`
  - `GET  /dictionary/banks`

- `app/Controllers/DashboardController.php`
- `app/Controllers/ImportController.php`
- `app/Controllers/ReviewController.php`
- `app/Controllers/DictionaryController.php`
- `app/Controllers/SettingsController.php`

- `app/Services/ImportService.php`
- `app/Services/MatchingService.php`
- `app/Services/ReviewWorkflowService.php`
- `app/Services/AlternativeNamesService.php`
- `app/Services/DictionaryService.php`

- `app/Repositories/SupplierRepository.php`
- `app/Repositories/SupplierAlternativeNameRepository.php`
- `app/Repositories/BankRepository.php`

## 4. مبادئ عامة

- لا يُسمح بكتابة SQL خام داخل Controllers أو Services.
- أي عملية على البيانات → تمر بـ Service → الذي يستدعي Repository.
- أي تعديل على منطق المطابقة → يتم داخل Service مخصص (مثلاً `MatchingService`).
- أي تعديل على أسماء الجداول أو الأعمدة → ينعكس داخل Repositories فقط، دون تغيير في بقية الطبقات.
