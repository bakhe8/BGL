# دليل الاختبارات (Testing Guide)

يعتمد النظام على إطار اختبارات وحدة محلي (Native PHP Test Runner) بالإضافة إلى بعض السكربتات اليدوية.

## 🧪 تشغيل اختبارات الوحدة (Unit Tests)

```bash
php tests/runner.php tests/unit
```

**الناتج المتوقع:**
```
🚀 Starting BGL Native Test Runner...
---------------------------------------------------
📂 Testing NormalizerTest:
   ✅ testNormalizeSupplierName_Basic
   ...

📂 Testing ScoringConfigTest:
   ✅ testGetStarRating_ThreeStars
   ...

📂 Testing SimilarityCalculatorTest:
   ✅ testPerfectMatch
   ...

---------------------------------------------------
🏁 Summary:
   Passed: 22
   Failed: 0

✨ All tests passed!
```

---

## 📋 الاختبارات المتوفرة

| الملف | الوظيفة | عدد الاختبارات |
|-------|---------|---------------|
| `NormalizerTest.php` | تطبيع أسماء الموردين/البنوك | 10 |
| `ScoringConfigTest.php` | ثوابت التقييم ودوال المساعدة | 7 |
| `SimilarityCalculatorTest.php` | حساب التشابه النصي | 5 |

---

## 📝 كتابة اختبار جديد

لإضافة اختبار جديد، قم بإنشاء ملف في `tests/unit/`:

```php
<?php
require_once __DIR__ . '/../../app/Support/autoload.php';
require_once __DIR__ . '/../TestCase.php';

use App\Services\MyNewService;

class MyNewServiceTest extends TestCase
{
    public function testBasicFunctionality(): void
    {
        $service = new MyNewService();
        $result = $service->doSomething();
        
        $this->assertTrue($result);
    }
}
```

---

## 🔧 السكربتات اليدوية (Legacy)

للاختبارات اليدوية القديمة:

```bash
# اختبار مستودع Timeline
php scripts/test_timeline_repository.php

# اختبار خدمة Timeline
php scripts/test_timeline_service.php
```

