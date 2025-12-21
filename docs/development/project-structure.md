# هيكل المشروع (Project Structure)

تم تنظيم المشروع ليكون بسيطاً وواضحاً، مع فصل الكود المصدري عن الملفات العامة.

## 📂 المجلدات الرئيسية

### `app/` (Back-end Logic)
يحتوي على كافة الكلاسات ومنطق العمل (PHP Classes).
*   **`Controllers/`**: معالجة الطلبات الواردة (مثل `DecisionController`, `ImportController`).
*   **`Services/`**: منطق العمل المعقد (مثل `MatchingService`, `TimelineEventService`).
*   **`Repositories/`**: التعامل مع قاعدة البيانات (مثل `ImportedRecordRepository`).
*   **`Models/`**: كائنات البيانات البسيطة (DTOs).
*   **`Support/`**: أدوات مساعدة (`ScoringConfig`, `Normalizer`, Database Connection).

### `www/` (Web Root)
هذا هو المجلد الوحيد الذي يجب أن يكون متاحاً للعموم (Publicly Accessible).
*   **`index.php`**: نقطة الدخول الرئيسية (Router).
*   **`api/`**: نقاط الوصول للواجهة (API Endpoints).
*   **`assets/`**: ملفات CSS و JS والصور.

### `www/assets/js/` (JavaScript - منظم v2.8)
```
js/
├── core/           # الأساسيات (api.js, dialog.js)
├── components/     # مكونات قابلة للإستخدام (autocomplete, chips, dropdown)
├── features/       # ميزات محددة (add-supplier, import, smart-paste, etc.)
└── pages/          # ملف واحد لكل صفحة PHP (decision.js, settings.js)
```

### `storage/` (Data & Logs)
*   **`database/app.sqlite`**: ملف قاعدة البيانات.
*   **`uploads/`**: الملفات المرفوعة مؤقتاً.
*   **`logs/app.log`**: سجلات التطبيق.

### `tests/` (Unit Tests)
*   **`unit/`**: اختبارات الوحدة (NormalizerTest, ScoringConfigTest, SimilarityCalculatorTest).
*   **`runner.php`**: مشغل الاختبارات.

### `docs/` (Documentation)
يحتوي على وثائق المشروع (التي تقرأها الآن).

---

## 🗝️ ملفات هامة

| الملف | الوظيفة |
|-------|---------|
| `server.php` | سكربت تشغيل خادم PHP المحلي. |
| `app/Support/Database.php` | إعدادات اتصال قاعدة البيانات (PDO). |
| `app/Support/ScoringConfig.php` | ثوابت نظام التقييم المركزية. |
| `app/Services/TimelineEventService.php` | المسؤول عن تسجيل الأحداث التاريخية. |
| `www/assets/js/pages/decision.js` | كود الجافاسكربت الرئيسي لصفحة القرار. |

---

## 📏 معايير الكود (Coding Standards)

*   **PHP**: نستخدم PHP 8.1+ مع Type Hinting صارم.
*   **Database**: نستخدم Prepared Statements دائماً لمنع SQL Injection.
*   **Architecture**: نمط MVC مبسط (Controller -> Service -> Repository).
*   **JavaScript**: منظم في طبقات (core -> components -> features -> pages).

