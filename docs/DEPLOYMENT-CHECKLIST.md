---
last_updated: 2025-12-17
version: 1.2-production
status: production-ready
---

# قائمة التحقق لنشر الإنتاج (Production Deployment Checklist)

## قبل النشر - التحقق المحلي

### 1. التأكد من سلامة الكود
- [ ] جميع التغييرات محفوظة في Git
- [ ] لا توجد ملفات غير مدفوعة مهمة
- [ ] لا توجد أخطاء في الكود
- [ ] تم اختبار جميع الوظائف الأساسية

### 2. التحقق من الملفات الحساسة
- [ ] `storage/database/app.sqlite` ليس في Git (بيانات محلية)
- [ ] `storage/uploads/` ليس في Git (ملفات مؤقتة)
- [ ] ملفات `.env` إن وجدت ليست في Git

### 3. التحقق من الوثائق
- [ ] `README.md` محدث بآخر المعلومات
- [ ] `docs/` تحتوي على جميع الأدلة المطلوبة
- [ ] `ARCHITECTURE.md` يعكس الهيكل الحالي

---

## متطلبات السيرفر

### البيئة المطلوبة
```
PHP >= 8.1
├── Extensions Required:
│   ├── SQLite3 (إلزامي)
│   ├── PDO + PDO_SQLite (إلزامي)
│   ├── mbstring (إلزامي)
│   ├── JSON (إلزامي)
│   └── GD (اختياري - للصور)
├── Memory Limit: >= 128M
└── Upload Max Size: >= 10M
```

### التحقق من PHP
```bash
# التحقق من الإصدار
php -v

# التحقق من الإضافات
php -m | grep -E "sqlite|pdo|mbstring|json"

# التحقق من الإعدادات
php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"
```

---

## خطوات النشر

### 1. نسخ الملفات
```bash
# استنساخ المستودع
git clone git@github.com:bakhe8/BGL.git
cd BGL

# أو سحب آخر التحديثات
git pull origin main
```

### 2. تثبيت التبعيات
```bash
# إذا كنت تستخدم Composer
composer install --no-dev --optimize-autoloader
```

### 3. إعداد قاعدة البيانات
```bash
# إنشاء مجلد قاعدة البيانات
mkdir -p storage/database

# نسخ قاعدة بيانات فارغة أو موجودة
# cp path/to/backup/app.sqlite storage/database/app.sqlite

# أو تشغيل السكريبت الأولي إن وجد
# php scripts/init_database.php
```

### 4. ضبط الصلاحيات
```bash
# منح صلاحيات الكتابة لمجلد storage
chmod -R 775 storage/
chown -R www-data:www-data storage/

# إذا كنت تستخدم مستخدم آخر
# chown -R $USER:$USER storage/
```

### 5. اختبار التشغيل المحلي
```bash
# تشغيل السيرفر المدمج
php -S localhost:8000 server.php

# أو
php -S localhost:8000 -t www
```

### 6. إعداد السيرفر (Apache/Nginx)

#### Apache
```apache
<VirtualHost *:80>
    ServerName bgl.yourdomain.com
    DocumentRoot /path/to/BGL/www
    
    <Directory /path/to/BGL/www>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        
        # PHP First - إعادة التوجيه
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/bgl-error.log
    CustomLog ${APACHE_LOG_DIR}/bgl-access.log combined
</VirtualHost>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name bgl.yourdomain.com;
    root /path/to/BGL/www;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # منع الوصول للملفات الحساسة
    location ~ /\. {
        deny all;
    }
    
    location ~ ^/(storage|vendor|scripts) {
        deny all;
    }
}
```

---

## بعد النشر - التحقق

### 1. اختبار الوظائف الأساسية
- [ ] الصفحة الرئيسية تعمل
- [ ] استيراد ملف Excel يعمل
- [ ] المطابقة التلقائية تعمل
- [ ] صفحة اتخاذ القرار تعمل
- [ ] التقارير تعمل
- [ ] الإعدادات تعمل

### 2. اختبار الأداء
- [ ] سرعة تحميل الصفحات مقبولة
- [ ] استيراد ملفات كبيرة يعمل
- [ ] لا توجد أخطاء في سجلات الخادم

### 3. التحقق من الأمان
- [ ] لا يمكن الوصول لـ `/storage/` من المتصفح
- [ ] لا يمكن الوصول لـ `/vendor/` من المتصفح
- [ ] لا يمكن الوصول لـ `/scripts/` من المتصفح
- [ ] لا يمكن تحميل ملفات خطرة (PHP, executable, etc.)

---

## النسخ الاحتياطي (Backup)

### ملفات مهمة للنسخ الاحتياطي
```bash
# قاعدة البيانات (الأهم)
storage/database/app.sqlite

# الملفات المرفوعة (إن وجدت)
storage/uploads/

# الإعدادات (إن وجدت)
storage/settings.json
```

### أتمتة النسخ الاحتياطي
```bash
#!/bin/bash
# backup.sh - سكريبت نسخ احتياطي يومي

BACKUP_DIR="/path/to/backups"
DATE=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/path/to/BGL"

# نسخ قاعدة البيانات
cp "$PROJECT_DIR/storage/database/app.sqlite" "$BACKUP_DIR/app_$DATE.sqlite"

# حذف النسخ الأقدم من 30 يوم
find "$BACKUP_DIR" -name "app_*.sqlite" -mtime +30 -delete

# ضغط النسخة الاحتياطية (اختياري)
gzip "$BACKUP_DIR/app_$DATE.sqlite"
```

### إضافة إلى Cron
```cron
# تشغيل النسخ الاحتياطي يومياً الساعة 2 صباحاً
0 2 * * * /path/to/backup.sh
```

---

## استعادة البيانات (Recovery)

### من نسخة احتياطية
```bash
# إيقاف التطبيق
# systemctl stop apache2  # أو nginx

# استبدال قاعدة البيانات
cp /path/to/backup/app_20251217.sqlite storage/database/app.sqlite

# ضبط الصلاحيات
chmod 664 storage/database/app.sqlite
chown www-data:www-data storage/database/app.sqlite

# إعادة تشغيل التطبيق
# systemctl start apache2
```

---

## الصيانة الدورية

### يومياً
- [ ] التحقق من سجلات الأخطاء
- [ ] النسخ الاحتياطي التلقائي

### أسبوعياً
- [ ] مراجعة أداء قاعدة البيانات
- [ ] تنظيف الملفات المؤقتة

### شهرياً
- [ ] تحديث التبعيات (إن وجدت)
- [ ] مراجعة التحديثات الأمنية
- [ ] اختبار النسخ الاحتياطية

---

## حل المشاكل الشائعة

### قاعدة البيانات مقفلة
```bash
# التحقق من العمليات المفتوحة
lsof | grep app.sqlite

# إغلاق العمليات المعلقة
# kill -9 <PID>
```

### صلاحيات خاطئة
```bash
# إصلاح الصلاحيات
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

### أخطاء PHP
```bash
# تفعيل عرض الأخطاء (للتطوير فقط)
php -d display_errors=1 -d error_reporting=E_ALL -S localhost:8000 server.php
```

---

## معلومات الاتصال والدعم

- **المستودع**: https://github.com/bakhe8/BGL
- **النسخة الحالية**: v1.2-production
- **الفرع الرئيسي**: main
- **آخر تحديث**: 2025-12-17

