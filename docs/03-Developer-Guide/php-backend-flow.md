# PHP Backend Flow
كيف يمر الطلب داخل الباك‑إند.

1) **Route**: تعريف داخل `www/index.php` يحدد المسار وطريقة HTTP.  
2) **Controller**: يستلم `Request`, يتحقق من المدخلات (Validator)، ويرسلها لـ Service.  
3) **Service**: منطق العمل؛ يستدعي Repositories، Helpers (Normalizer, Logger).  
4) **Repository**: يشغّل استعلامات SQLite (قراءة/كتابة) فقط.  
5) **Response**: يتم تكوين JSON موحد:
   ```json
   { "success": true, "data": { ... } }
   ```
   أو في الخطأ:
   ```json
   { "success": false, "error_code": "...", "message": "..." }
   ```

## نقاط يجب الالتزام بها
- لا SQL داخل Controllers/Services.  
- تفعيل `PRAGMA foreign_keys = ON` عند الاتصال.  
- سجّل الأخطاء في `storage/logs/app.log` مع Trace مختصر.
