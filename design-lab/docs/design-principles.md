# مبادئ DesignLab - الوثيقة التوجيهية

## 📋 نظرة عامة

هذه الوثيقة تحدد المبادئ الأساسية التي يجب الالتزام بها عند بناء أي شيء داخل DesignLab. كل قرار تقني يجب أن يُقيّم بناءً على هذه المبادئ.

---

## 1️⃣ العزل الصارم (Strict Isolation)

### المبدأ
> DesignLab يجب ألا يؤثر على النظام الأساسي.

### الشرح
يعمل المختبر على نفس البيانات والمنطق، لكن بصلاحيات مقيدة (قراءة فقط)، ولا يسمح بتغيير القواعد أو البيانات أو السلوك الأساسي للنظام.

### التطبيق العملي

#### ✅ يُسمح
- قراءة البيانات من Database
- استدعاء API endpoints للقراءة
- استخدام نفس Models (في وضع readonly)
- نسخ Assets للتعديل الآمن

#### ❌ يُمنع
- الكتابة المباشرة على Database
- تعديل ملفات النظام الحالي (`app/`, `views/`, `assets/`)
- استدعاء API endpoints للكتابة/التعديل/الحذف
- تغيير Config الرئيسي

### Implementation Checklist

```php
// ✅ صحيح - قراءة فقط
$record = GuaranteeRecord::getById($id);
$timeline = Timeline::getEvents($sessionId);

// ❌ خطأ - كتابة
$record->status = 'approved';
$record->save();

// ✅ صحيح - Simulation في المختبر
$simulatedDecision = [
    'decision' => 'approve',
    'timestamp' => time(),
    'simulated' => true // Flag واضح
];
echo json_encode($simulatedDecision);

// ❌ خطأ - تنفيذ حقيقي
Decision::save($sessionId, $decision);
```

### قواعد الملفات

```
✅ آمن للتعديل:
- design-lab/**/*
- lab.php

❌ ممنوع التعديل:
- app/**/*
- views/**/*
- assets/**/*
- config/**/*
- www/**/*

⚠️ تعديل محدود (فقط لإضافة routing):
- server.php (سطر واحد فقط)
```

---

## 2️⃣ استخدام بيانات حقيقية (Real Data, Controlled)

### المبدأ
> لا يمكن تقييم التصميم على بيانات وهمية.

### الشرح
تُستخدم بيانات حقيقية من النظام مع منع أي آثار جانبية (لا حفظ، لا تعديل)، لضمان أن التصميم يُختبر في ظروف واقعية.

### التطبيق العملي

#### استراتيجية البيانات

```php
// في design-lab/views/decision-v2.php

// ✅ جلب بيانات حقيقية
$db = Database::getInstance();
$record = $db->query(
    "SELECT * FROM guarantee_records WHERE record_id = ?", 
    [$recordId]
)->fetch();

// ✅ إضافة flag للوضع
$isLabMode = true;
$canEdit = false; // Force readonly

// ✅ استخدام نفس AI Logic لكن بدون حفظ
$aiRecommendation = AIEngine::analyze($record, [
    'readonly' => true,
    'simulate' => true
]);
```

#### حماية من Side Effects

```javascript
// في design-lab/assets/js/lab-decision.js

// ✅ Override لمنع الحفظ الفعلي
const saveDecision = (decision) => {
    if (window.LAB_MODE) {
        console.log('[LAB] Decision simulated:', decision);
        showSimulationNotice(decision);
        return Promise.resolve({ simulated: true });
    }
    // في النظام الحقيقي فقط
    return fetch('/api/decisions/save.php', {...});
};

// ✅ Visual indicator
const showSimulationNotice = (decision) => {
    alert(`🧪 وضع المختبر: تم محاكاة القرار (${decision}) - لم يُحفظ فعلياً`);
};
```

### Data Integrity Checklist

- [ ] البيانات تُجلب من Database الحقيقي
- [ ] لا يوجد INSERT/UPDATE/DELETE queries
- [ ] كل عملية "حفظ" تُحاكى فقط
- [ ] Visual indicator واضح للوضع التجريبي
- [ ] Logging للأحداث المحاكاة

---

## 3️⃣ الاعتماد على Design Tokens

### المبدأ
> التصميم يبدأ من النظام، لا من الأذواق.

### الشرح
الألوان، المسافات، الخطوط، والزوايا تُدار كـ Tokens مشتركة، بحيث تكون الاختلافات بين التصاميم في البنية والتفاعل وليس في القيم العشوائية.

### التطبيق العملي

#### Design Tokens Structure

```css
/* design-lab/assets/css/tokens.css */

:root {
  /* ===== Color Tokens ===== */
  --color-primary: #6366f1;
  --color-success: #10b981;
  --color-warning: #f59e0b;
  --color-danger: #ef4444;
  --color-neutral: #6b7280;
  
  /* ===== Spacing Scale ===== */
  --space-xs: 0.25rem;   /* 4px */
  --space-sm: 0.5rem;    /* 8px */
  --space-md: 1rem;      /* 16px */
  --space-lg: 1.5rem;    /* 24px */
  --space-xl: 2rem;      /* 32px */
  --space-2xl: 3rem;     /* 48px */
  
  /* ===== Typography Scale ===== */
  --text-xs: 0.75rem;    /* 12px */
  --text-sm: 0.875rem;   /* 14px */
  --text-base: 1rem;     /* 16px */
  --text-lg: 1.125rem;   /* 18px */
  --text-xl: 1.25rem;    /* 20px */
  --text-2xl: 1.5rem;    /* 24px */
  --text-3xl: 1.875rem;  /* 30px */
  --text-4xl: 2.25rem;   /* 36px */
  
  /* ===== Border Radius ===== */
  --radius-sm: 0.25rem;  /* 4px */
  --radius-md: 0.5rem;   /* 8px */
  --radius-lg: 1rem;     /* 16px */
  --radius-full: 9999px;
  
  /* ===== Shadows ===== */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
  
  /* ===== Transitions ===== */
  --transition-fast: 150ms ease;
  --transition-base: 300ms ease;
  --transition-slow: 500ms ease;
}
```

#### ✅ استخدام صحيح

```css
/* design-lab/assets/css/lab-decision.css */

.ai-hero {
  padding: var(--space-2xl);           /* ✅ من Tokens */
  border-radius: var(--radius-lg);     /* ✅ من Tokens */
  background: var(--color-primary);    /* ✅ من Tokens */
  box-shadow: var(--shadow-lg);        /* ✅ من Tokens */
  transition: var(--transition-base);  /* ✅ من Tokens */
}

.ai-hero:hover {
  transform: translateY(-2px);         /* ✅ قيمة ثابتة معقولة */
  box-shadow: var(--shadow-xl);        /* ✅ من Tokens */
}
```

#### ❌ استخدام خاطئ

```css
/* تجنب القيم العشوائية */

.bad-example {
  padding: 23px;                       /* ❌ ليس من scale */
  border-radius: 7px;                  /* ❌ ليس من Tokens */
  background: #5856d6;                 /* ❌ لون عشوائي */
  box-shadow: 0 3px 8px rgba(...);     /* ❌ ظل مخصص */
  transition: 275ms;                   /* ❌ توقيت عشوائي */
}
```

### Tokens Checklist

- [ ] كل الألوان من `--color-*`
- [ ] كل المسافات من `--space-*`
- [ ] كل الخطوط من `--text-*`
- [ ] كل الزوايا من `--radius-*`
- [ ] لا قيم hardcoded عشوائية

---

## 4️⃣ قابلية المقارنة بين النماذج (Comparability)

### المبدأ
> أي تجربة غير قابلة للمقارنة لا تنتج قرارًا.

### الشرح
جميع النماذج تُعرض على نفس السيناريو والبيانات، ويُغيَّر عنصر واحد فقط في كل نموذج (تخطيط، تنقل، كثافة…)، لتسهيل التقييم العادل.

### التطبيق العملي

#### هيكل التجارب

```
design-lab/views/experiments/
├── ai-first.php          # تجربة 1: AI-First approach
├── timeline-integrated.php  # تجربة 2: Timeline مدمج
├── minimal.php           # تجربة 3: تصميم بسيط
└── dashboard.php         # تجربة 4: Dashboard view
```

#### قواعد التجارب

```php
// كل تجربة تستخدم نفس:
// 1. نفس البيانات
$recordId = $_GET['record_id'] ?? 14002; // Fixed للمقارنة

// 2. نفس الـ Tokens
require_once __DIR__ . '/../assets/css/tokens.css';

// 3. نفس المنطق
$aiRecommendation = AIEngine::analyze($record);

// لكن تختلف في:
// - Layout
// - Information Hierarchy
// - Interaction Pattern
```

#### Template للتجربة

```php
<!-- design-lab/views/experiments/_template.php -->

<?php
/**
 * Experiment Name: [اسم التجربة]
 * Focus: [ما يتم اختباره - مثلاً: تخطيط، تفاعل، كثافة]
 * Changed Variable: [المتغير المختلف عن التجارب الأخرى]
 * Control Variables: [ما هو ثابت مع كل التجارب]
 */

// نفس البيانات لكل التجارب
$FIXED_RECORD_ID = 14002;
$FIXED_SESSION_ID = 511;

// جلب البيانات
$record = getRecord($FIXED_RECORD_ID);
$timeline = getTimeline($FIXED_SESSION_ID);
$aiRecommendation = getAIRecommendation($record);

// متغيرات التجربة
$experimentVariables = [
    'layout' => 'ai-first',        // ما يتغير
    'density' => 'comfortable',    // ما يتغير
    'interactions' => 'minimal'    // ما يتغير
];
?>

<!-- التصميم هنا -->
```

### Comparison Matrix

| Variable | Exp 1: AI-First | Exp 2: Timeline | Exp 3: Minimal | Exp 4: Dashboard |
|----------|----------------|----------------|----------------|-----------------|
| **Data** | Record #14002 | Record #14002 | Record #14002 | Record #14002 |
| **Layout** | Hero + Collapsible | Integrated | Single Column | Grid |
| **AI Prominence** | Very High | Medium | Low | Medium |
| **Timeline** | Hidden | Visible | Summary | Chart |
| **Clicks to Decide** | 1-2 | 2-3 | 1 | 3-4 |

### Comparability Checklist

- [ ] نفس record_id لكل التجارب
- [ ] نفس AI logic
- [ ] متغير واحد فقط يتغير بين التجارب
- [ ] كل تجربة موثقة في header
- [ ] Comparison matrix محدثة

---

## 5️⃣ قياس تجربة المستخدم (Measurable UX)

### المبدأ
> الانطباع وحده لا يكفي.

### الشرح
يُقاس الجهد المطلوب من المستخدم عبر مؤشرات بسيطة مثل عدد النقرات، زمن اتخاذ القرار، وضوح الأخطاء، حتى لو كان المستخدم هو المصمم نفسه.

### التطبيق العملي

#### Metrics System

```javascript
// design-lab/assets/js/lab-metrics.js

class LabMetrics {
    constructor(experimentName) {
        this.experiment = experimentName;
        this.startTime = Date.now();
        this.clicks = 0;
        this.scrolls = 0;
        this.hovers = 0;
        this.interactions = [];
    }
    
    trackClick(element) {
        this.clicks++;
        this.interactions.push({
            type: 'click',
            element: element,
            timestamp: Date.now() - this.startTime
        });
    }
    
    trackDecision(decision) {
        const timeToDecision = Date.now() - this.startTime;
        return {
            experiment: this.experiment,
            decision: decision,
            timeToDecision: timeToDecision,
            totalClicks: this.clicks,
            totalScrolls: this.scrolls,
            interactions: this.interactions
        };
    }
    
    save() {
        const metrics = this.trackDecision();
        localStorage.setItem(
            `lab_metrics_${this.experiment}_${Date.now()}`,
            JSON.stringify(metrics)
        );
    }
}

// استخدام
const metrics = new LabMetrics('ai-first');
document.addEventListener('click', (e) => {
    metrics.trackClick(e.target);
});
```

#### المؤشرات المقاسة

```javascript
// المؤشرات الأساسية
const coreMetrics = {
    // 1. الوقت
    timeToFirstDecision: 0,      // كم ثانية حتى أول قرار
    timeToConfirmation: 0,       // كم ثانية حتى التأكيد
    
    // 2. الجهد
    clicksToDecision: 0,         // عدد النقرات
    scrollDistance: 0,           // مسافة التمرير (pixels)
    
    // 3. التردد
    decisionChanges: 0,          // كم مرة غيّر رأيه
    
    // 4. الوضوح
    errorsEncountered: 0,        // أخطاء واجهها
    helpClicks: 0,               // نقرات على المساعدة
    
    // 5. الثقة
    confidenceRating: 0          // تقييم الثقة من 1-10
};
```

#### Results Dashboard

```php
<!-- design-lab/views/metrics.php -->

<div class="metrics-dashboard">
    <h2>نتائج المقارنة</h2>
    
    <table>
        <tr>
            <th>Metric</th>
            <th>AI-First</th>
            <th>Timeline</th>
            <th>Minimal</th>
            <th>Winner</th>
        </tr>
        <tr>
            <td>وقت القرار</td>
            <td>45s</td>
            <td>120s</td>
            <td>30s</td>
            <td>✅ Minimal</td>
        </tr>
        <tr>
            <td>عدد النقرات</td>
            <td>2</td>
            <td>5</td>
            <td>1</td>
            <td>✅ Minimal</td>
        </tr>
        <tr>
            <td>الثقة بالقرار</td>
            <td>9/10</td>
            <td>8/10</td>
            <td>6/10</td>
            <td>✅ AI-First</td>
        </tr>
    </table>
</div>
```

### Measurement Checklist

- [ ] Metrics tracking مفعّل في كل تجربة
- [ ] localStorage يحفظ النتائج
- [ ] Dashboard يعرض المقارنات
- [ ] المؤشرات تُوثق في كل session
- [ ] النتائج قابلة للتصدير

---

## 6️⃣ قابلية الاستخلاص والتبني (Extractable Outcomes)

### المبدأ
> المختبر وسيلة، وليس وجهة دائمة.

### الشرح
كل تصميم يجب أن ينتج قرارًا واضحًا: ما الذي سيُعتمد؟ ما الذي سيُرفض؟ وما الذي يمكن نقله إلى النسخة النهائية كنمط أو مكوّن.

### التطبيق العملي

#### Decision Log

```markdown
<!-- design-lab/docs/decisions.md -->

# قرارات التصميم

## Decision #001: AI Hero Component
**Date:** 2025-12-21
**Status:** ✅ Approved for Production

**What:**
بطاقة AI Recommendation كبيرة في أعلى الصفحة

**Why:**
- قلل وقت القرار بنسبة 60%
- زاد الثقة من 6/10 إلى 9/10
- 90% من المستخدمين اختاروها بدلاً من الخيارات اليدوية

**How to Extract:**
1. نسخ `design-lab/views/components/ai-hero.php` إلى `views/components/`
2. نسخ `design-lab/assets/css/lab-decision.css` (القسم الخاص بـ `.ai-hero`)
3. تحديث `views/decision.php` لاستخدام المكون الجديد

**Migration Checklist:**
- [ ] نسخ Component
- [ ] نسخ Styles
- [ ] Update main view
- [ ] Test في النظام الحقيقي
- [ ] Deploy

---

## Decision #002: Collapsible Timeline
**Date:** 2025-12-21
**Status:** ⏳ Testing

**What:**
Timeline مخفي افتراضياً، يظهر عند الحاجة

**Why:**
- يقلل Cognitive Load
- لكن قد يخفي معلومات مهمة

**Next Steps:**
- [ ] اختبار مع 5 مستخدمين
- [ ] قياس معدل فتح Timeline
- [ ] قرار نهائي خلال أسبوع
```

#### Extraction Guide

```markdown
<!-- design-lab/docs/extraction-guide.md -->

# دليل استخلاص المكونات

## الخطوات العامة

### 1. تحديد المكون الناجح
- مراجعة Metrics
- تأكيد التحسين الملموس
- موافقة من الفريق

### 2. عزل الكود
```bash
# مثال: استخلاص AI Hero
cp design-lab/views/components/ai-hero.php views/components/
cp design-lab/assets/css/lab-decision.css assets/css/ai-hero.css
cp design-lab/assets/js/lab-decision.js assets/js/ai-hero.js
```

### 3. تنظيف الكود
- إزالة LAB_MODE flags
- إزالة metrics tracking
- إزالة simulation code
- تفعيل real saving

### 4. Integration Testing
- اختبار في النظام الحقيقي
- تأكيد عدم كسر الميزات الحالية
- User acceptance testing

### 5. Documentation
- توثيق API الجديد
- تحديث user guide
- Archive التجربة في المختبر
```

### Extractability Checklist

- [ ] كل component معزول في ملف منفصل
- [ ] التبعيات واضحة
- [ ] لا mixing بين lab code و production code
- [ ] Extraction guide محدث
- [ ] Decision log محدث

---

## 7️⃣ قابلية الإغلاق والتنظيف (Sunset Rule)

### المبدأ
> كل تجربة لها نهاية.

### الشرح
لكل نموذج هدف وزمن محدد، وبعد اتخاذ القرار يتم أرشفته أو إغلاقه لمنع تراكم تجارب غير مستخدمة داخل المشروع.

### التطبيق العملي

#### Experiment Lifecycle

```
1. Planning      → تحديد الهدف والمدة
2. Building      → بناء التجربة
3. Testing       → جمع البيانات (1-7 أيام)
4. Decision      → اتخاذ القرار
5. Extraction    → نقل الناجح للـ production
6. Archiving     → أرشفة أو حذف التجربة
```

#### Experiment Metadata

```php
<!-- في header كل تجربة -->

<?php
/**
 * Experiment: AI-First Decision Flow
 * 
 * Start Date: 2025-12-21
 * End Date: 2025-12-28 (7 days)
 * 
 * Goal: تقليل وقت اتخاذ القرار بنسبة 50%
 * 
 * Success Criteria:
 * - Time to decision < 60 seconds
 * - Confidence rating > 8/10
 * - Click count < 3
 * 
 * Status: 🟢 Active
 * 
 * Decision: [To be filled after testing]
 */
?>
```

#### Archive Structure

```
design-lab/
├── experiments/
│   └── active/              # التجارب النشطة
│       ├── ai-first.php
│       └── timeline.php
├── archive/
│   ├── 2025-12/
│   │   ├── ai-first/        # ✅ نجحت - تم النقل
│   │   │   ├── experiment.php
│   │   │   ├── metrics.json
│   │   │   ├── decision.md
│   │   │   └── screenshots/
│   │   └── minimal/         # ❌ فشلت - أرشفة فقط
│   │       ├── experiment.php
│   │       ├── metrics.json
│   │       └── decision.md
```

#### Sunset Workflow

```bash
# بعد اتخاذ القرار

# 1. إذا نجحت التجربة
./scripts/extract-experiment.sh ai-first

# 2. أرشفة التجربة
./scripts/archive-experiment.sh ai-first --status=success

# 3. تنظيف المختبر
rm design-lab/experiments/active/ai-first.php

# 4. تحديث Documentation
echo "Experiment moved to production on $(date)" >> design-lab/docs/changelog.md
```

### Cleanup Checklist

- [ ] كل تجربة لها end date
- [ ] Metrics محفوظة قبل الحذف
- [ ] Decision موثق
- [ ] الناجح مُستخلص
- [ ] الفاشل مؤرشف للدروس
- [ ] المختبر نظيف

---

## ✅ Master Checklist

قبل أي commit أو merge من المختبر:

### العزل والأمان
- [ ] لا تعديلات على ملفات النظام الحالي
- [ ] كل الكود في `design-lab/` أو `lab.php`
- [ ] لا write operations على Database
- [ ] Simulation mode واضح بصرياً

### الجودة والاتساق
- [ ] كل القيم من Design Tokens
- [ ] لا قيم hardcoded عشوائية
- [ ] Components معزولة وقابلة لإعادة الاستخدام

### القياس والمقارنة
- [ ] Metrics tracking مفعّل
- [ ] نفس البيانات عبر التجارب
- [ ] متغير واحد فقط يتغير

### النتائج والقرارات
- [ ] Decision log محدث
- [ ] Extraction guide محدث إذا لزم
- [ ] التجارب المنتهية مؤرشفة

---

## 📚 المراجع

- `design-lab/docs/decisions.md` - سجل القرارات
- `design-lab/docs/extraction-guide.md` - دليل الاستخلاص
- `design-lab/docs/comparison.md` - مقارنة مع النظام الحالي
- `design-lab/docs/changelog.md` - سجل التغييرات

---

> **Remember:** المختبر ليس هدفاً، بل أداة لاتخاذ قرارات تصميم أفضل. كل تجربة يجب أن تقودك لقرار واضح: اعتماد، رفض، أو تحسين.
