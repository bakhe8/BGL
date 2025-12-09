# نقاط النهاية (داخلي)
قائمة قصيرة بالمسارات JSON المستخدمة بين الواجهة وPHP.

- `POST /api/import/excel` — رفع ملف Excel.  
- `POST /api/import/paste` — استيراد نص ملصوق.  
- `POST /api/import/manual` — إنشاء سجل يدوي.  
- `GET  /api/records` — جلب السجلات حسب الجلسة/الحالة.  
- `POST /api/records/{id}/decision` — حفظ قرار المراجعة.  
- `GET  /api/dictionary/suppliers` — قائمة الموردين.  
- `POST /api/dictionary/suppliers` — إنشاء مورد جديد.  
- `GET  /api/dictionary/banks` — قائمة البنوك.  
- `POST /api/dictionary/banks` — إنشاء بنك جديد.  
- `GET  /api/exports/{type}` — تحميل ملف تصدير.
