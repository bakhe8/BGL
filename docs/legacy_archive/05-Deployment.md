---
last_updated: 2025-12-13
version: 1.1
status: active
---

# 05 - النشر والتشغيل (Deployment)

## المتطلبات (Requirements)
- **PHP 8.1** أو أحدث.
- **SQLite3 Extension** مفعلة في `php.ini`.
- **GD Extension** (اختياري للصور).

## التشغيل (Run)
لتشغيل النظام محلياً:

```bash
cd /path/to/project
php -S localhost:8000 -t www
```

ثم افتح المتصفح على: `http://localhost:8000`

## النسخ الاحتياطي (Backup)
1. **قاعدة البيانات**: الملف `storage/database.sqlite` يحتوي على كل شيء. انسخه يدوياً أو استخدم زر "Backup" في صفحة الإعدادات.
2. **الإعدادات**: الملف `storage/settings.json`.
3. **الملفات المرفوعة**: المجلد `storage/uploads/`.
