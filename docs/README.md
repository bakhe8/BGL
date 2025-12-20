# BGL Documentation

مجلد الوثائق الفنية لنظام إدارة الضمانات البنكية

---

## 📚 دليل الوثائق

### للبداية السريعة
- **[README.md](../README.md)** - نظرة عامة على المشروع
- **[DEPLOYMENT.md](../DEPLOYMENT.md)** - دليل الإطلاق والتشغيل

### الوثائق التقنية

#### نظام Timeline Events (جديد!)
- **[sessions-vs-batches.md](sessions-vs-batches.md)** - الفرق بين Sessions و Batches
- **[quick-reference.md](quick-reference.md)** - مرجع سريع للمطورين
- **[timeline-enhancement.md](timeline-enhancement.md)** - تحسينات Timeline

#### الهندسة المعمارية
راجع artifacts في:
```
C:\Users\Bakheet\.gemini\antigravity\brain\<session-id>\
├── architecture-review.md       - مراجعة معمارية شاملة
├── timeline-redesign.md         - تصميم نظام Timeline
├── system-integration-map.md    - خريطة التكامل
└── detailed-technical-analysis.md - تحليل تقني مفصل
```

#### خطط المستقبل
- **[future-tasks.md](../.gemini/antigravity/brain/.../future-tasks.md)** - المهام المستقبلية
- **[CHANGELOG.md](../CHANGELOG.md)** - سجل التغييرات

---

## 🎯 دليل سريع حسب الدور

### للمطورين الجدد
1. ابدأ بـ [README.md](../README.md)
2. اقرأ [quick-reference.md](quick-reference.md)
3. راجع [architecture-review.md](../.gemini/antigravity/brain/.../architecture-review.md)
4. اختبر مع `scripts/health_check.php`

### لمديري النظام
1. راجع [DEPLOYMENT.md](../DEPLOYMENT.md)
2. تابع [future-tasks.md](../.gemini/antigravity/brain/.../future-tasks.md)
3. استخدم `scripts/health_check.php` للمراقبة

### للمطورين الحاليين
1. تحديثات جديدة في [CHANGELOG.md](../CHANGELOG.md)
2. تفاصيل Timeline في [timeline-redesign.md](../.gemini/antigravity/brain/.../timeline-redesign.md)
3. Best practices في [sessions-vs-batches.md](sessions-vs-batches.md)

---

## 🔍 البحث في الوثائق

### حسب الموضوع

**Timeline Events:**
- Architecture: `architecture-review.md`
- Implementation: `timeline-redesign.md`
- API: `guarantee-history.php` inline docs

**Database:**
- Schema: `database/migrations/`
- Sessions: `sessions-vs-batches.md`

**Testing:**
- Health check: `scripts/health_check.php`
- Repository tests: `scripts/test_timeline_repository.php`
- Service tests: `scripts/test_timeline_service.php`

**Deployment:**
- Guide: `DEPLOYMENT.md`
- Cleanup: `scripts/cleanup_dev_data.sql`

---

## 📝 معايير التوثيق

### كود PHP
```php
/**
 * وصف مختصر للدالة
 * 
 * وصف تفصيلي إن لزم
 * 
 * @param string $param وصف المعامل
 * @return int وصف القيمة المرجعة
 */
public function exampleMethod(string $param): int
{
    // تعليق إذا كان المنطق معقد
    return 1;
}
```

### SQL
```sql
-- ==========================================================================
-- Table: table_name
-- Purpose: وصف الغرض
-- ==========================================================================
CREATE TABLE ...
```

### JavaScript
```javascript
/**
 * Function description
 * @param {string} param - Parameter description
 * @returns {boolean} Return value description
 */
function example(param) {
    // Comment if logic is complex
    return true;
}
```

---

## 🔄 تحديث الوثائق

### عند إضافة ميزة جديدة:
1. حدّث `CHANGELOG.md`
2. أضف/حدّث inline documentation
3. حدّث `README.md` إذا لزم
4. أنشئ artifact إذا كان معقداً

### عند إصلاح bug:
1. سجّل في `CHANGELOG.md`
2. حدّث التعليقات ذات الصلة

---

## 🤝 المساهمة

لإضافة/تحديث وثائق:
1. اتبع المعايير أعلاه
2. كن واضحاً ومختصراً
3. أضف أمثلة عملية
4. استخدم العربية للوثائق العامة
5. استخدم الإنجليزية للكود والتعليقات التقنية

---

## 📞 المساعدة

**لم تجد ما تبحث عنه؟**
- راجع inline code documentation
- استخدم `scripts/health_check.php` للتشخيص
- راجع artifacts في `.gemini/antigravity/brain/`

---

*آخر تحديث: 2025-12-20*  
*الإصدار: 2.1.0*
