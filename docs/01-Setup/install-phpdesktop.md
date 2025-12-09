# تثبيت PHP Desktop

## 1. تحميل PHP Desktop
1. توجه إلى مستودع PHP Desktop الرسمي.
2. اختر نسخة:
   - **phpdesktop-chrome** في حال الحاجة لمحرك Chromium.
3. فك الضغط وضعه في مجلد المشروع الرئيسي.

## 2. تهيئة PHP Desktop
أنشئ ملف `settings.json` داخل مجلد phpdesktop:

```json
{
  "server": {
    "document_root": "public",
    "listen_on": ["127.0.0.1:8000"]
  },
  "chrome": {
    "app_window_title": "BL System",
    "show_devtools": false
  }
}
```

## 3. تشغيل PHP Desktop
شغّل الملف:
```
phpdesktop.exe
```