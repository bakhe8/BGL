# Data Layer - مصدر الحقيقة

**Production Data - Do Not Modify Directly**

---

## الهيكل

```
data/
├── database/           ← قاعدة البيانات
│   ├── app.sqlite     ← الملف الفعلي (حالياً في storage/)
│   └── schemas/       ← تعريفات Schema
│       ├── current/   ← الـ schema الحالي
│       └── migrations/ ← تاريخ التغييرات
│
└── files/             ← الملفات المحملة
    ├── guarantees/    ← مستندات الضمانات
    └── invoices/      ← الفواتير
```

---

## ملاحظة مهمة

**الملف الفعلي لقاعدة البيانات حالياً:**
```
storage/database/app.sqlite
```

**هذا المجلد (`data/`) للتوثيق حالياً.**

في المستقبل، قد نقل:
- `storage/database/` → `data/database/`

لكن **ليس الآن** - نحتاج:
- اختبار شامل
- تحديث config
- Migration آمن

---

## القواعد

### ✅ يمكن:
- القراءة (read-only) من التطبيق
- الكتابة عبر Backend فقط
- النسخ الاحتياطي (backup)

### ❌ لا يمكن:
- التعديل اليدوي المباشر
- الكتابة من Labs
- الحذف بدون backup

---

## الوصول

**Backend:**
```php
// app/Support/Database.php
Database::connect()->query("...");
```

**DesignLab:**
```php
// design-lab/core/DataAccess.php (read-only)
LabDataAccess::getRecords();
```

**SchemaLab:**
```sql
-- للفحص فقط
sqlite3 storage/database/app.sqlite
.schema
```

---

## Related Documentation

- `docs/architecture/data-layer.md` - المعمارية الكاملة
- `schema-lab/README.md` - تغييرات Schema
- `design-lab/core/DataAccess.php` - Read-only access
