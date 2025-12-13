# BGL - Bank Guarantee Letters Management System

نظام إدارة خطابات الضمان: تطبيق ويب محلي (Self-Hosted) لأتمتة معالجة ومطابقة خطابات الضمان البنكية.

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

### أدلة أخرى
- **[CONTRIBUTING.md](docs/CONTRIBUTING.md)**: معايير كتابة الكود والتسمية.
- **[docs/archive/](docs/archive/)**: أرشيف يحتوي على تقارير التحليل وفحص الكود القديمة.

## التشغيل السريع (Quick Start)

```bash
php -S localhost:8000 -t www
```
ثم افتح: http://localhost:8000
