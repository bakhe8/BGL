# دليل الاختبارات (Testing Guide)

يعتمد النظام حالياً على مجموعة من السكربتات اليدوية (Manual Scripts) الموجودة في مجلد `scripts/` للتأكد من سلامة الوظائف الأساسية.

## 🧪 كيفية تشغيل الاختبارات

يتم تشغيل الاختبارات من سطر الأوامر (Terminal).

### 1. اختبار مستودع Timeline
يتحقق من قدرة النظام على إنشاء وجلب الأحداث التاريخية.

```bash
php scripts/test_timeline_repository.php
```
**النتيجة المتوقعة**: ظهور رسائل "SUCCESS" لكل خطوة (إنشاء، جلب).

### 2. اختبار خدمة Timeline (Service Layer)
يتحقق من المنطق المعقد (اللقطات، التحويلات).

```bash
php scripts/test_timeline_service.php
```

### 3. اختبار التوافق مع PHP 8.1
للتأكد من عدم وجود دوال ملغاة (Deprecated).

```bash
php -l app/Controllers/DecisionController.php
```

---

## 📝 كتابة اختبار جديد

لإضافة اختبار جديد، قم بإنشاء ملف PHP في مجلد `scripts/` واستخدم الكلاسات مباشرة:

```php
<?php
require_once __DIR__ . '/../app/Support/autoload.php';

use App\Services\MyNewService;

try {
    $service = new MyNewService();
    $result = $service->doSomething();
    
    if ($result === true) {
        echo "✅ Test Passed\n";
    } else {
        echo "❌ Test Failed\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
```
