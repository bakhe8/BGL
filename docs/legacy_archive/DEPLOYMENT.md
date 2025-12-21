# 🚀 دليل الإطلاق السريع - Timeline Events System

## الخطوات قبل الإطلاق

### 1. نظافة البيانات (5 دقائق)

```bash
# تنظيف بيانات التطوير
php -r "
\$db = new PDO('sqlite:bgl.sqlite');

// Backup
copy('bgl.sqlite', 'bgl_backup_' . date('Y-m-d') . '.sqlite');

// Delete development modifications
\$stmt = \$db->prepare('DELETE FROM imported_records WHERE record_type = \"modification\" AND created_at < \"2025-12-20\"');
\$stmt->execute();
echo 'Deleted ' . \$db->lastAffectedRows() . ' old modifications\n';

// Delete test timeline events
\$stmt = \$db->prepare('DELETE FROM guarantee_timeline_events WHERE guarantee_number LIKE \"TEST%\"');
\$stmt->execute();
echo 'Deleted ' . \$db->lastAffectedRows() . ' test events\n';

// Vacuum
\$db->exec('VACUUM');
echo 'Database optimized!\n';
"
```

### 2. اختبار سريع (10 دقائق)

```bash
# Test 1: Repository
php scripts/test_timeline_repository.php

# Test 2: Service
php scripts/test_timeline_service.php

# Test 3: API
curl "http://localhost:8000/www/api/guarantee-history.php?number=<any_guarantee>"
```

**النتيجة المتوقعة:** ✅ كل الاختبارات تنجح

### 3. اختبار في المتصفح (5 دقائق)

1. افتح أي ضمان
2. غيّر المورد
3. احفظ
4. افتح سجل الضمان (Timeline)
5. **تحقق:** الحدث ظاهر مع اسم المورد

---

## ✅ Checklist قبل الإطلاق

- [ ] النسخ الاحتياطي تم
- [ ] بيانات التطوير محذوفة
- [ ] الاختبارات نجحت
- [ ] Timeline يعمل في المتصفح
- [ ] الأداء سريع (<100ms)

---

## 🎯 بعد الإطلاق

### المراقبة (أول 7 أيام)

راقب هذه الملفات:
```bash
# Check error logs
tail -f error.log

# Check timeline events being created
php -r "
\$db = new PDO('sqlite:bgl.sqlite');
\$stmt = \$db->query('SELECT COUNT(*), event_type FROM guarantee_timeline_events WHERE created_at > datetime(\"now\", \"-1 day\") GROUP BY event_type');
print_r(\$stmt->fetchAll(PDO::FETCH_ASSOC));
"
```

### مؤشرات النجاح

**اليوم 1:**
- ✅ لا أخطاء في logs
- ✅ Events يتم إنشاؤها
- ✅ Timeline يظهر للمستخدمين

**الأسبوع 1:**
- ✅ الأداء مستقر
- ✅ لا شكاوى من المستخدمين
- ✅ البيانات صحيحة

**الشهر 1:**
- ✅ النظام مستقر تماماً
- ✅ يمكن حذف الكود القديم

---

## 🆘 حل المشاكل

### المشكلة: Timeline فارغ
```sql
-- Check 1: Are events being created?
SELECT COUNT(*) FROM guarantee_timeline_events;

-- Check 2: For specific guarantee
SELECT * FROM guarantee_timeline_events 
WHERE guarantee_number = 'XXX';
```

**الحل:** تحقق من أن الضمان تم تعديله بعد 2025-12-20

### المشكلة: الأداء بطيء
```sql
-- Check indexes
SELECT * FROM sqlite_master 
WHERE type='index' AND tbl_name='guarantee_timeline_events';
```

**الحل:** يجب أن يكون 6 indexes موجودة

### المشكلة: أسماء الموردين لا تظهر
```sql
-- Check display names
SELECT 
    event_type,
    supplier_display_name,
    bank_display
FROM guarantee_timeline_events 
LIMIT 5;
```

**الحل:** إذا NULL، تحقق من TimelineEventService

---

## 📞 الدعم

**المشاكل الشائعة:** راجع `future-tasks.md`  
**التوثيق التقني:** راجع `walkthrough.md`  
**الكود:** راجع `TimelineEventService.php`

---

## 🎉 تهانينا!

النظام جاهز للإطلاق! 🚀

**من الآن فصاعداً:** كل التغييرات ستُسجل تلقائياً في Timeline Events!
