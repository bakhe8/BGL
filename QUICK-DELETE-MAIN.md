# حذف الفرع main البعيد - الخطوات السريعة

## ⚠️ مهم: اقرأ هذا أولاً!

**برنامجك الحالي (v1.2) موجود على:** `php-first-restructure`  
**الفرع `main` قديم ويحتوي على:** نسخة ما قبل إعادة الهيكلة (867edbb)

---

## الخطوات

### 1. غيّر الفرع الافتراضي على GitHub

**اذهب إلى:**
https://github.com/bakhe8/BGL/settings/branches

**الخطوات:**
1. ابحث عن "Default branch" (الفرع الافتراضي)
2. سترى: `main` مع سهم للتبديل
3. اضغط على السهم أو زر "Switch to another branch"
4. اختر: `php-first-restructure`
5. اضغط: "Update"
6. أكد: "I understand, update the default branch"

---

### 2. احذف الفرع `main` البعيد

بعد تغيير الفرع الافتراضي، نفّذ في Terminal:

```bash
git push origin --delete main
```

سيظهر لك:
```
To github.com:bakhe8/BGL.git
 - [deleted]         main
```

---

### 3. تحديث المراجع المحلية

```bash
git fetch --prune
```

---

### 4. التحقق

```bash
git branch -a
```

يجب أن ترى فقط:
```
* php-first-restructure
  remotes/origin/php-first-restructure
```

بدون `remotes/origin/main` ✅

---

## إذا واجهتك مشكلة

إذا قال Git أن الفرع لا يزال محمياً:
1. ارجع للخطوة 1 وتأكد من تغيير الفرع الافتراضي
2. تأكد من حفظ التغيير على GitHub
3. انتظر دقيقة ثم حاول مرة أخرى

---

## بعد الحذف

✅ المستودع سيصبح أنظف  
✅ الفرع الرئيسي الوحيد: `php-first-restructure`  
✅ النسخة المعتمدة: `v1.2-production`  
