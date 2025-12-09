# إعداد TailwindCSS محليًا

## 1. تثبيت Node.js (مرة واحدة فقط)
حمّل النسخة LTS من Node.js.

## 2. تهيئة Tailwind
داخل مجلد المشروع:

```
npm init -y
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init
```

## 3. إنشاء ملف Tailwind
أنشئ ملف:
```
www/assets/css/tailwind.css
```

ضع داخله:
```
@tailwind base;
@tailwind components;
@tailwind utilities;
```

## 4. بناء Tailwind لإنتاج ملف ثابت
```
npx tailwindcss -i ./www/assets/css/tailwind.css -o ./www/assets/css/style.css --minify
```

## 5. استخدام الملف في المشروع:
```
<link rel="stylesheet" href="/assets/css/style.css">
```

## النتيجة:
لن تحتاج للإنترنت — سيتم استخدام الملف المحلي فقط.
