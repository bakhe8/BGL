# BGL - Guarantee Management System

## نظام إدارة الضمانات البنكية

نظام متكامل لإدارة خطابات الضمان البنكية مع تتبع كامل للتاريخ والتغييرات.

---

## ✨ الميزات الأساسية

### 📊 إدارة الضمانات
- استيراد خطابات الضمان من Excel
- مطابقة ذكية للموردين والبنوك
- تتبع حالة كل ضمان
- تمديد وإفراج الضمانات

### 📝 Timeline Events (جديد!)
- تسجيل تلقائي لكل التغييرات
- تتبع تغييرات الموردين والبنوك
- سجل كامل للتمديدات والإفراجات
- واجهة سهلة لعرض التاريخ

### 🎯 نظام القرارات
- اقتراحات ذكية للموردين
- نظام تعلم من القرارات السابقة
- أوزان تلقائية للموردين
- مطابقة بالتشابه النصي

---

## 🚀 التشغيل السريع

### المتطلبات
- PHP 8.1+
- SQLite 3
- Composer

### التثبيت

```bash
# 1. Clone المشروع
git clone <repo-url>
cd BGL

# 2. Install dependencies
composer install

# 3. Run migrations
php scripts/migrate_timeline_events.php
php scripts/migrate_add_display_names.php

# 4. Start server
php -S localhost:8000 server.php
```

### الوصول
افتح المتصفح: `http://localhost:8000`

---

## 📁 هيكل المشروع

```
BGL/
├── app/
│   ├── Controllers/      # DecisionController, etc.
│   ├── Services/         # TimelineEventService ⭐
│   ├── Repositories/     # TimelineEventRepository ⭐
│   ├── Models/
│   └── Support/
├── www/
│   ├── api/             # guarantee-history.php ⭐
│   ├── assets/
│   │   ├── js/          # guarantee-history.js ⭐
│   │   └── css/
│   └── index.php
├── database/
│   └── migrations/      # Timeline events migrations ⭐
├── scripts/             # Test & utility scripts
└── docs/                # Documentation
```

⭐ = جديد في Timeline Events System

---

## 🎯 الاستخدام

### استيراد الضمانات
1. اذهب إلى "Import"
2. ارفع ملف Excel
3. راجع المطابقات
4. أكد الاستيراد

### عرض التاريخ (Timeline)
1. افتح أي ضمان
2. انقر على رقم الضمان
3. سيظهر Timeline مع كل الأحداث

### تعديل الضمان
1. افتح الضمان
2. عدّل المورد/البنك/المبلغ
3. احفظ
4. **تلقائياً:** يُسجل في Timeline!

---

## 🔧 التطوير

### اختبار النظام

```bash
# Repository tests
php scripts/test_timeline_repository.php

# Service tests
php scripts/test_timeline_service.php

# API test
curl "http://localhost:8000/www/api/guarantee-history.php?number=TEST/001"
```

### إضافة Event جديد

```php
// في TimelineEventService.php
public function logNewEventType(
    string $guaranteeNumber,
    int $recordId,
    string $oldValue,
    string $newValue
): int {
    return $this->timeline->create([
        'guarantee_number' => $guaranteeNumber,
        'record_id' => $recordId,
        'event_type' => 'your_event_type',
        'old_value' => $oldValue,
        'new_value' => $newValue,
        // ... المزيد
    ]);
}
```

---

## 📊 قاعدة البيانات

### الجداول الرئيسية

**imported_records** - الحالة الحالية للضمانات  
**guarantee_timeline_events** - كل الأحداث التاريخية ⭐  
**suppliers** - قائمة الموردين  
**banks** - قائمة البنوك  
**import_sessions** - جلسات الاستيراد

---

## 🎨 الواجهة

### التصميم
- Modern, clean interface
- Arabic RTL support
- Responsive design
- Dark mode compatible

### المكونات الرئيسية
- Decision cards
- Timeline view ⭐
- Import wizard
- Statistics dashboard

---

## 📈 الأداء

### مقاييس النظام

| Feature | Performance |
|---------|-------------|
| Timeline Query | ~40ms |
| Import 1000 records | ~2s |
| Decision suggestions | ~100ms |
| Page load | <1s |

### التحسينات الأخيرة
- ✅ Timeline Events: 7.5x faster
- ✅ No JOIN queries
- ✅ Optimized indexes
- ✅ Direct column access

---

## 🔐 الأمان

- SQLite with prepared statements
- Input validation
- XSS protection
- CSRF tokens (in forms)

---

## 📝 التوثيق

### للمطورين
- `walkthrough.md` - نظرة شاملة للنظام
- `future-tasks.md` - المهام المستقبلية
- `DEPLOYMENT.md` - دليل الإطلاق

### للمستخدمين
- User guide (قيد الإنشاء)
- Video tutorials (مخطط)

---

## 🤝 المساهمة

### إضافة ميزة جديدة
1. Fork the repo
2. Create feature branch
3. Write tests
4. Submit PR

### الإبلاغ عن مشكلة
افتح issue مع:
- وصف المشكلة
- خطوات إعادة المشكلة
- Screenshots (إن أمكن)

---

## 📜 السجل

### v2.0 (2025-12-20) - Timeline Events System ⭐
- نظام Timeline Events جديد كاملاً
- تسجيل تلقائي لكل التغييرات
- أداء محسّن 7.5x
- واجهة Timeline جديدة

### v1.x (السابق)
- النظام الأساسي
- استيراد Excel
- نظام القرارات

---

## 📞 الدعم

**Technical Issues:** انظر `DEPLOYMENT.md`  
**Documentation:** انظر `docs/`  
**Code Questions:** راجع inline comments

---

## 📄 الترخيص

[حدد نوع الترخيص]

---

## 🙏 شكر خاص

تم تطوير نظام Timeline Events باستخدام:
- PHP 8.1
- SQLite 3
- Modern JavaScript
- Clean Architecture principles

---

**تم التحديث:** 2025-12-20  
**الإصدار:** 2.1.0  
**الحالة:** Production Ready ✅
