# Folder Structure
هيكل مقترح للنسخة النهائية داخل مجلد التطبيق.

```
www/                      # جذر PHP Desktop
  index.php               # نقطة الدخول وتعريف المسارات
  assets/                 # ملفات CSS/JS النهائية (Tailwind مبني)
  views/                  # قوالب الواجهة
app/
  Controllers/            # يستقبل الطلبات ويمررها للخدمات
  Services/               # منطق الأعمال (مطابقة، مراجعة، استيراد)
  Repositories/           # استعلامات SQLite فقط
  Models/                 # أغلفة بيانات بسيطة، بدون ORM
  Support/                # مساعدين (Validator، Normalizer، Logger)
config/
  app.php                 # إعدادات عامة وثوابت
database/
  migrations/             # ملفات .sql للترقيات
  seeders/                # بيانات تجريبية إن وجدت
storage/
  database/app.sqlite     # قاعدة البيانات الرسمية
  logs/                   # app.log
  uploads/exports/        # ملفات الإدخال/الإخراج
```

أي ملفات عامة أخرى (مثل أدوات التطبيع أو القاموس) توضع تحت `app/Support/` مع تسميتها بوضوح. استخدم Repositories + Models بسيطة ولا تضف ORM آخر.
