# إعداد وتشغيل المشروع

## 1. تجهيز المجلدات الأساسية
هيكل المشروع:

```
project/
  www/
  app/
  storage/
```

## 2. تثبيت PHP
يجب أن يكون PHP ≥ 8.2 مع الإضافات:
- sqlite3
- mbstring
- json

## 3. إنشاء قاعدة البيانات
```
mkdir storage/database
touch storage/database/app.sqlite
```

## 4. تشغيل المشروع عبر PHP Desktop
افتح:
```
phpdesktop.exe
```
وسيعمل الموقع على بيئة محلية بدون إنترنت.
