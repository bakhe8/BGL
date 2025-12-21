# README - Three-Document System Templates

## الاستخدام السريع

### إنشاء Design Finding جديد

```bash
cp templates/design-finding-template.md findings/DF-XXX-[وصف-مختصر].md
```

ثم املأ:
- ID (رقم تسلسلي)
- الملاحظات من DesignLab
- Metrics المقاسة
- Design Blocker

### إنشاء Logic Impact Note جديد

```bash
cp templates/logic-impact-template.md ../logic-impact/proposals/LIN-XXX-[وصف-مختصر].md
```

ثم املأ:
- ربطه بـ Design Finding
- المنطق الحالي vs المطلوب
- التغييرات المطلوبة
- تقييم المخاطر

### إنشاء Decision Record جديد

```bash
cp templates/decision-record-template.md ../logic-impact/approved/DR-XXX-[وصف-مختصر].md
```

ثم املأ:
- القرار (موافق/مرفوض/مؤجل)
- السبب
- الشروط إذا لزم
- الموافقة

---

## القواعد الإلزامية

1. ✅ **كل Design Finding يحتاج Logic Impact Note**
2. ✅ **كل Logic Impact Note يحتاج Decision Record**
3. ✅ **لا تنفيذ بدون الوثائق الثلاث**
4. ❌ **لا تجاوز أي مرحلة**

---

## الترقيم

- **DF-XXX**: Design Finding (001, 002, 003...)
- **LIN-XXX**: Logic Impact Note (001, 002, 003...)
- **DR-XXX**: Decision Record (001, 002, 003...)

كل نوع له ترقيم مستقل متسلسل.

---

## المراجع

راجع `docs/three-document-system.md` للتفاصيل الكاملة.
