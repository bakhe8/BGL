# Project Architecture
ملخص سريع لكيفية تماسك المكونات (بدون إطار ثقيل).

## الطبقات
- **Shell**: PHP Desktop (Chromium + PHP 8.x مدمج).
- **Backend**: PHP خفيف بلا إطار، منظم كـ Routes (داخل index.php) → Controllers → Services → Repositories + Models بسيطة (بدون ORM).
- **Database**: SQLite داخل `storage/database/app.sqlite`.
- **Frontend**: HTML/CSS/JS (بدون React). TailwindCSS مُسبق التجميع إلى ملف واحد ثابت.

## تدفق أساسي
1) الواجهة ترسل طلب `fetch` إلى مسار PHP (POST/GET).  
2) `www/index.php` يعرّف المسار ويربطه بالـ Controller المناسب.  
3) الـ Controller يستدعي Service ويجمع الاستجابة.  
4) الـ Service يتعامل مع Repositories فقط للوصول لـ SQLite.  
5) يتم إرجاع JSON أو HTML حسب نوع المسار.

## الروابط المهمة
- تفاصيل أوسع في `docs/architecture-overview.md`.  
- دورة حياة الطلب في `docs/request-lifecycle.md`.
