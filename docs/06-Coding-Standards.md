# 06 - معايير التكويد والتسمية (Coding Standards)

للحفاظ على نظافة الكود وسهولة صيانته، نلتزم بالمعايير التالية بدقة.

## 1. PHP Standards
نتبع معايير **PSR-12**.

### التسمية (Naming)
- **Classes**: `PascalCase` (مثل `ImportService`, `SupplierRepository`).
- **Methods**: `camelCase` (مثل `importExcel`, `findCandidates`).
- **Variables**: `camelCase` (مثل `$supplierName`, `$isValid`).
- **Constants**: `UPPER_CASE` (مثل `MATCH_THRESHOLD`).

### الهيكلة (Structure)
- ملف واحد لكل كلاس (Class).
- مساحات الأسماء (Namespaces) تطابق المجلدات (مثل `App\Services`).

## 2. JavaScript Standards
- **Functions**: `camelCase` (مثل `updateRow`).
- **Variables**: `camelCase` (مثل `recordId`).
- **DOM Elements**: يفضل استخدام بادئة توضح النوع (مثل `btnSubmit`, `inputName`).

## 3. الملفات والمجلدات (Files & Folders)
- **PHP Files**: تطابق اسم الكلاس تماماً (مثل `ImportService.php`).
- **HTML/JS Files**: أحرف صغيرة مع شرطة (kebab-case) إذا لزم الأمر، أو camelCase (مثل `settings.html`, `main.js`).
- **Controllers**: يجب أن تنتهي بكلمة `Controller` (مثل `RecordsController.php`).
- **Repositories**: يجب أن تنتهي بكلمة `Repository` (مثل `BankRepository.php`).

## 4. القيم والبيانات (Data & Values)
- **Database Columns**: `snake_case` (مثل `session_id`, `created_at`).
- **JSON Responses**: مفاتيح `snake_case` لسهولة ربطها مع قاعدة البيانات (مثل `{"record_id": 1}`).

## 5. قواعد عامة
- **اللغة**: الكود والتعليقات التقنية بالإنجليزية. النصوص الظاهرة للمستخدم بالعربية.
- **التوثيق**: يجب توثيق أي دالة معقدة باستخدام PHPDoc.
- **D.R.Y**: (Don't Repeat Yourself) - لا تكرر الكود، استخدم وظائف مساعدة (Helpers) أو Services.

## 6. قواعد العمل (Business Logic)
- **الفصل بين الموردين والبنوك**:
  - يمنع منعاً باتاً نسخ منطق "الموردين" وتطبيقه على "البنوك".
  - الموردون يعتمدون على `Aliases` (Many-to-One).
  - البنوك تعتمد على `Attributes` (EN Name / Short Code).
  - استخدام كلاسات منفصلة دائماً (مثل `SupplierRepository` vs `BankRepository`).
