# BGL - نظام مطابقة الضمانات البنكية (v2.0)

نظام ذكي لمطابقة الضمانات البنكية الواردة من ملفات Excel مع الموردين والبنوك في النظام، مع واجهة اتخاذ قرار متقدمة.

**الإصدار الحالي:** `v2.0` (Major Release)
 🎉  
**الحالة:** إنتاجية مستقرة (Stable Production) - إعادة هيكلة شاملة + ميزات جديدة

## وثائق النظام (Documentation)

تم تحديث وتنظيم وثائق النظام في مجلد `docs/` لتكون مصدراً واحداً للحقيقة.

### الأدلة الأساسية (Core Guides)

1. **[01-System-Overview.md](docs/01-System-Overview.md)**
   - نظرة عامة على النظام، التقنيات المستخدمة، وهيكلية المجلدات.
2. **[02-User-Guide.md](docs/02-User-Guide.md)**
   - دليل المستخدم: كيفية الاستيراد، التعامل مع السجلات، والإعدادات.
3. **[03-Matching-Engine.md](docs/03-Matching-Engine.md)**
   - شرح تفصيلي لكيفية عمل "عقل" النظام في مطابقة الموردين والبنوك.
4. **[04-Technical-Reference.md](docs/04-Technical-Reference.md)**
   - **هام للمطورين**: شرح قاعدة البيانات (Schema)، والـ API، ومنطق الاستيراد.
5. **[05-Deployment.md](docs/05-Deployment.md)**
   - كيفية تشغيل النظام محلياً، ومتطلبات السيرفر (PHP/SQLite).
6. **[06-Manual-Entry.md](docs/06-Manual-Entry.md)** ✍️
   - **الإدخال اليدوي**: دليل شامل لميزة الإدخال اليدوي للسجلات.
7. **[DEPLOYMENT-CHECKLIST.md](docs/DEPLOYMENT-CHECKLIST.md)** ⭐
   - **قائمة التحقق الشاملة للنشر**: متطلبات السيرفر، خطوات النشر، النسخ الاحتياطي، والصيانة الدورية.

### للمطورين (For Developers)
- **[DEVELOPMENT-WORKFLOW.md](docs/DEVELOPMENT-WORKFLOW.md)** 🚀
  - **دليل التطوير الشامل**: استراتيجية الفروع، إدارة الإصدارات، وأفضل الممارسات.
- **[DEV-QUICK-START.md](docs/DEV-QUICK-START.md)** ⚡
  - **البداية السريعة للتطوير**: ملخص سريع للأوامر الأساسية والقواعد.

### أدلة أخرى
- **[CONTRIBUTING.md](docs/CONTRIBUTING.md)**: معايير كتابة الكود والتسمية.
- **[docs/archive/](docs/archive/)**: أرشيف يحتوي على تقارير التحليل وفحص الكود القديمة.

## التشغيل السريع (Quick Start)

```bash
php -S localhost:8000 server.php
```
ثم افتح: http://localhost:8000
