# Naming Conventions — قواعد التسمية

هذه الوثيقة تضع معايير واضحة لأسماء الملفات، الكلاسات، الدوال، والمتغيرات في الباك‑إند (PHP + SQLite).
الهدف:  
- سهولة القراءة والفهم.  
- تقليل الالتباس مع الأسماء القديمة في المشروع السابق.  
- التزام بأسلوب ثابت يشبه الأطر الحديثة (Laravel، Symfony) بدون استخدام إطار ثقيل.

## 1. أسماء المجلدات

- المجلدات الأساسية باللغة الإنجليزية وبصيغة PascalCase أو lowercase مع شرطات:
  - `app/`
  - `app/Services/`
  - `app/Repositories/`
- `app/Controllers/`
  - `database/`
  - `database/migrations/`
  - `storage/`
  - `www/views/`

- لا نستخدم أسماء مبهمة مثل `logic/` أو `core/` بدون تحديد وظيفتها.
  - بدل `logic/` نستخدم `Services/`.
  - بدل `core/` نستخدم `Repositories/` أو `Support/` حسب الحاجة.

## 2. أسماء الملفات (PHP)

- الكلاسات: PascalCase مطابق لاسم الملف:
  - ملف: `SupplierRepository.php` → كلاس: `SupplierRepository`
- ملف: `ImportService.php` → كلاس: `ImportService`
  - ملف: `DictionaryController.php` → كلاس: `DictionaryController`

- لا نستخدم أسماء عامة مثل:
  - `helper.php`
  - `functions.php`
  - `logic.php`
  - `utils.php` (إلا إذا كان هناك سبب قوي).

- بدائل أفضل:
  - `NameNormalizer.php`
  - `ExcelImportHelper.php`
  - `DateFormatter.php`

## 3. أسماء الدوال (Functions / Methods)

- صيغة camelCase باللغة الإنجليزية، وتصف الفعل بوضوح:

  - في الخدمات (Services):
    - `matchSupplierName(string $rawName): SupplierMatchResult`
    - `createReviewRecordsFromExcel(array $rows): array`
    - `finalizeUserDecision(int $recordId, array $payload): void`

  - في المستودعات (Repositories):
    - `findById(int $id)`
    - `findByNormalizedName(string $normalizedName)`
    - `createAlternativeName(int $supplierId, string $rawName, string $normalizedName)`
    - `incrementUsageCount(int $alternativeNameId)`

- عدم استخدام أسماء مبهمة مثل:
  - `doProcess()`, `handleStuff()`, `runLogic()`, `process2()`

## 4. أسماء المتغيرات

- متغيرات مفهومة وواضحة:

  - **في المطابقة:**
    - `$rawName`  → الاسم الخام القادم من Excel أو الإدخال اليدوي.
    - `$normalizedName` → الاسم بعد التطبيع (إزالة مسافات/تشكيل/...).
    - `$supplierId` → رقم المورد في قاعدة البيانات.
    - `$bankId` → رقم البنك في قاعدة البيانات.
    - `$matchScore` → درجة التشابه (0–1).

  - **في الجلسات/المراجعة:**
    - `$recordId` → رقم سجل الضمان بعد إدخاله في النظام.
    - `$needsReview` → قيمة منطقية تشير إذا كان السجل يحتاج مراجعة بشرية.
    - `$reviewStatus` → قيم مثل: `"pending" | "approved" | "rejected"`.

- تجنب:
  - `$a`, `$b`, `$x1`, `$data2`, `$temp`, `$arr` إلا داخل حلقات صغيرة جدًا.
  - استخدام العربية داخل أسماء المتغيرات (العربية فقط في التعليقات).

## 5. أسماء جداول قاعدة البيانات

- بصيغة **snake_case** وجمع (plural) عندما يناسب:

  - `suppliers`
  - `supplier_alternative_names`
  - `banks`
  - `import_sessions`
  - `imported_records`
  - `learning_log`

- الأعمدة أيضًا snake_case:

  - `id`
  - `supplier_id`
  - `bank_id`
  - `raw_name`
  - `normalized_name`
  - `display_name`
  - `is_confirmed`
  - `created_at`
  - `updated_at`

- لا نستخدم أسماء مختصرة جدًا مثل:
  - `sup_id`, `bnk_nm`, `cr_at`

## 6. أسماء الثوابت (Constants)

- بصيغة UPPER_SNAKE_CASE:

  - `MATCH_SCORE_AUTO_THRESHOLD`
  - `MAX_IMPORT_ROWS`
  - `DATE_DISPLAY_FORMAT`
  - `CURRENCY_DISPLAY_LOCALE`

- مكان الثوابت:
  - إما في ملف إعدادات `config/app.php`.
  - أو في كلاس ثابت `App\Config\MatchingConfig`.

## 7. أسماء نقاط النهاية (HTTP Endpoints)

- واضحة وتعبر عن الفعل:

  - استيراد:
    - `POST /api/import/excel`
    - `POST /api/import/paste`
    - `POST /api/import/manual`

  - السجلات:
    - `GET  /api/records`
    - `GET  /api/records/{id}`
    - `POST /api/records/{id}/decision`

  - القواميس:
    - `GET  /api/dictionary/suppliers`
    - `POST /api/dictionary/suppliers`
    - `GET  /api/dictionary/banks`
    - `POST /api/dictionary/banks`

- تجنّب المسارات العامة مثل:
  - `/api/doAction`
  - `/api/process`
  - `/api/handle`

## 8. أسماء ملفات الواجهة (Front-End HTML/CSS داخل PHP Desktop)

- لوحات رئيسية:
  - `records.html` → شاشة إدارة السجلات.
  - `dictionary-suppliers.html` → شاشة قواميس الموردين.
  - `dictionary-banks.html` → شاشة قواميس البنوك.
  - `review-workbench.html` → شاشة مراجعة الأسماء (التي كانت سابقًا SCPE).

- ملفات CSS:
  - `base.css`
  - `layout.css`
  - `components.css`
  - `theme-light.css` (إن احتجنا ثيمات).

## 9. مبادئ عامة أخيرة

- أي اسم ملف/دالة يجب أن يجيب على سؤال واحد: **"ماذا تفعل؟"**
- لا نكرر أسماء مختلفة لنفس المعنى:
  - مثلاً: نلتزم باسم واحد لمفهوم "Alternative Names" ولا نستخدم:
    - variants, aliases, alt_names بشكل عشوائي.
  - المقترح:
    - في DB: `supplier_alternative_names`
    - في الكود: `SupplierAlternativeName`, `SupplierAlternativeNameRepository`.
- إذا احتجنا تغيير اسم مفهوم (مثلاً من `variants` إلى `alternative_names`):
  - يتم ذلك مرة واحدة على مستوى:
    - قاعدة البيانات.
    - الكود (Services/Repositories).
    - الواجهة (التسميات).
  - ويوثق التغيير في ملف `CHANGELOG.md`.

بهذه القواعد، تصبح أسماء الملفات والدوال متناسقة، ويمكنك أنت أو أي مبرمج آخر فهم المشروع بسرعة بدون الرجوع إلى أسماء مبهمة أو قديمة.
