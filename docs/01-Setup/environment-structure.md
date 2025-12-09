# بنية البيئة Environment Structure

```
root/
│
├── phpdesktop/              ← محرك التشغيل
│   └── settings.json
│
├── www/                     ← الواجهة الأمامية (جذر PHP Desktop)
│   ├── index.php
│   ├── assets/
│   │   ├── css/style.css    ← ملف Tailwind المبني
│   │   └── js/
│   └── views/
│
├── app/                     ← منطق النظام (PHP)
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   └── Helpers/
│
├── storage/
│   ├── database/
│   │   └── app.sqlite       ← قاعدة البيانات
│   ├── logs/
│   └── uploads/
│
└── README.md
```
