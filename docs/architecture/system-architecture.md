# BGL System Architecture

**Complete Lab System Architecture Diagram**

---

## 🧭 Architecture Overview (High-Level)

```
┌─────────────────────────────────────────────────────────┐
│                     Frontend Layer                      │
│                                                         │
│   ┌──────────────┐   ┌──────────────┐   ┌────────────┐ │
│   │  DesignLab   │   │  UI (Prod)   │   │   Admin    │ │
│   │  (UX Only)   │   │  Stable UI   │   │  Tools     │ │
│   └──────┬───────┘   └──────┬───────┘   └─────┬──────┘ │
│          │                  │                  │        │
└──────────┼──────────────────┼──────────────────┼────────┘
           │                  │                  │
           ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────┐
│                     API / Gateway                       │
│        (Read-only / Feature-flag / Versioned)           │
└──────────┬──────────────────┬──────────────────┬────────┘
           │                  │                  │
           ▼                  ▼                  ▼
┌─────────────────────────────────────────────────────────┐
│                   Business Logic Layer                  │
│                                                         │
│   ┌──────────────┐   ┌──────────────────────────────┐ │
│   │  LogicLab    │   │     Core Business Logic       │ │
│   │ (Simulated)  │   │   (Stable + Flagged Paths)   │ │
│   └──────┬───────┘   └──────────────┬───────────────┘ │
│          │                          │                  │
└──────────┼──────────────────────────┼──────────────────┘
           │                          │
           ▼                          ▼
┌─────────────────────────────────────────────────────────┐
│                   Schema / Data Logic                   │
│                                                         │
│   ┌──────────────┐   ┌──────────────────────────────┐ │
│   │  Schema-Lab  │   │        Data Access Layer      │ │
│   │ (Analysis &  │   │   (ORM / Queries / Mappers)  │ │
│   │  Migrations) │   └──────────────┬───────────────┘ │
│   └──────┬───────┘                  │                  │
└──────────┼──────────────────────────┼──────────────────┘
           │                          │
           ▼                          ▼
┌─────────────────────────────────────────────────────────┐
│                       Data Layer                        │
│                                                         │
│   ┌────────────┐   ┌──────────────┐   ┌────────────┐ │
│   │  Database  │   │  File Store  │   │   Indexes  │ │
│   │ (Source of │   │  (PDF, OCR,  │   │  / Search  │ │
│   │   Truth)   │   │   Uploads)   │   │            │ │
│   └────────────┘   └──────────────┘   └────────────┘ │
└─────────────────────────────────────────────────────────┘
```

---

## 🧠 كيف تقرأ هذا المخطط

### 1️⃣ المختبرات لا تملك شيئاً

**لا تملك:**
- ❌ منطق تنفيذي
- ❌ بيانات
- ❌ صلاحيات كتابة

**فقط:**
- ✅ تقرأ
- ✅ تحاكي
- ✅ تحلل

---

### 2️⃣ كل Lab يعمل على طبقة مختلفة

| Lab | الطبقة | السؤال الذي يجيب عليه |
|-----|--------|------------------------|
| **DesignLab** | UI / UX | كيف يجب أن يبدو ويتصرف؟ |
| **LogicLab** | Business Logic | كيف يجب أن نقرر؟ |
| **SchemaLab** | Data Model | كيف يجب أن نخزن؟ |

---

### 3️⃣ Data Layer في الأسفل دائماً

- ✅ مصدر الحقيقة الوحيد
- ❌ لا مختبر يكتب مباشرة
- ❌ لا UI يعبث بها
- ❌ لا منطق يتجاوزها

---

## 🔁 مسار القرار الكامل (Decision Flow)

```
User Pain
   ↓
DesignLab Finding (DF-XXX)
   ↓
LogicLab Simulation
   ↓
Decision Record (DR-XXX)
   ↓
SchemaLab (إن لزم - SDR-XXX)
   ↓
Implementation (Feature Flag)
   ↓
Production
```

**كل خطوة موثقة، كل قرار مبرر، كل تغيير آمن.**

---

## 🧱 حدود واضحة (Boundaries)

### ✅ مسموح

```
DesignLab ← Read-only API
LogicLab ← Snapshots / Fixtures
SchemaLab ← Abstract Schemas
```

### ❌ ممنوع

```
كتابة DB من Lab
تنفيذ كود إنتاج داخل Lab
تغيير Schema بدون SchemaLab
```

---

## 🧩 أين تضع الملفات فعلياً؟

```
BGL/
├── frontend/
│   ├── ui/                    ← Production UI
│   └── design-lab/            ← UX experiments
│
├── backend/
│   ├── core/                  ← Business logic
│   ├── api/                   ← API endpoints
│   ├── feature-flags/         ← Feature control
│   └── changes/               ← Implementation track
│
├── logic-lab/                 ← Logic thinking
├── schema-lab/                ← Schema planning
├── logic-impact/              ← Official docs
│   ├── proposals/
│   ├── approved/
│   └── rejected/
│
├── data/                      ← Data Layer
│   ├── database/
│   │   └── schemas/
│   └── files/
│
├── test-data/                 ← Test fixtures
│   ├── fixtures/
│   └── mocks/
│
└── docs/
    ├── architecture/          ← System architecture
    └── decisions/             ← Decision records
```

---

## 🔑 القاعدة الذهبية

```
المختبرات تفكّر
التنفيذ يغيّر
البيانات تُحترم
```

**إذا التزمت بهذا:**
- ✅ لا فوضى
- ✅ لا قرارات منسية
- ✅ لا كسر مفاجئ

---

## 📊 Data Flow في BGL

### Read Flow (القراءة)

```
UI Request
   ↓
API (read endpoint)
   ↓
Business Logic
   ↓
Data Access Layer
   ↓
Database (read-only for Labs)
```

### Write Flow (الكتابة)

```
User Action
   ↓
API (write endpoint)
   ↓
Feature Flag Check
   ↓
Business Logic Validation
   ↓
Schema Validation
   ↓
Data Access Layer
   ↓
Database + Timeline Event
```

---

## 🔄 Lab Integration Flow

### DesignLab → Production

```
1. Experiment in DesignLab
2. Create Design Finding (DF-XXX)
3. Gather metrics
4. If successful → Plan implementation
5. Create feature branch
6. Implement with feature flag
7. Deploy to production (flag OFF)
8. Test internally
9. Gradual rollout
10. Archive experiment
```

### LogicLab → Production

```
1. Analyze in LogicLab
2. Simulate scenarios
3. Create Logic Impact Note (LIN-XXX)
4. Get approval (DR-XXX)
5. If DB changes → SchemaLab
6. Implement with feature flag
7. Test thoroughly
8. Deploy
9. Monitor
10. Document outcome
```

### SchemaLab → Production

```
1. Analyze in SchemaLab
2. Create Migration Plan
3. Simulate dual-write
4. Get approval (SDR-XXX)
5. Backup database
6. Execute Phase 1 (additive)
7. Monitor
8. Execute Phase 2-N (gradual)
9. Cutover
10. Cleanup (after stabilization)
```

---

## 🎯 التكامل الكامل

```
                    ┌─────────────┐
                    │  DesignLab  │
                    └──────┬──────┘
                           │ discovers
                           ↓
                    ┌─────────────┐
                    │  LogicLab   │
                    └──────┬──────┘
                           │ analyzes
                           ↓
                  ┌────────┴────────┐
                  │   SchemaLab     │ (if needed)
                  └────────┬────────┘
                           │ plans
                           ↓
              ┌────────────────────────┐
              │   logic-impact/        │
              │   - proposals/         │
              │   - approved/          │
              └────────┬───────────────┘
                       │ documents
                       ↓
              ┌────────────────────────┐
              │   backend/changes/     │
              │   - code/              │
              │   - tests/             │
              │   - flags/             │
              └────────┬───────────────┘
                       │ implements
                       ↓
              ┌────────────────────────┐
              │   Production           │
              │   (Feature Flag)       │
              └────────────────────────┘
```

---

## 💡 المبادئ المعمارية

### 1. Separation of Concerns
- Frontend ≠ Backend
- Labs ≠ Production
- Data ≠ Logic

### 2. Single Source of Truth
- Data Layer = الحقيقة
- كل شيء آخر = نسخ أو محاكاة

### 3. Gradual Change
- Feature flags always
- Phased rollouts
- Monitored deployments

### 4. Documentation First
- Design Finding before building
- Logic Impact before changing
- Schema Analysis before migrating

### 5. Safe Migrations
- Additive changes preferred
- Dual-write when needed
- Cleanup after stabilization

---

## 🔒 Security & Access Control

| Component | Read | Write | Modify Schema |
|-----------|------|-------|---------------|
| DesignLab | ✅ | ❌ | ❌ |
| LogicLab | Simulated | ❌ | ❌ |
| SchemaLab | Schema only | ❌ | Plans only |
| Backend | ✅ | ✅ | Via migrations |
| UI | Via API | Via API | ❌ |

---

## 🎓 للمطورين الجدد

### القاعدة الأولى
> **لا تغيّر شيئاً مباشرة - فكّر أولاً**

### القاعدة الثانية
> **إذا مسّ البيانات → SchemaLab**

### القاعدة الثالثة
> **وثّق كل قرار - سيسألك أحدهم لماذا!**

---

## 📚 Related Documentation

- [design-lab/README.md](file:///c:/Users/Bakheet/Documents/Projects/BGL/design-lab/README.md) - DesignLab guide
- [logic-lab/README.md](file:///c:/Users/Bakheet/Documents/Projects/BGL/logic-lab/README.md) - LogicLab guide
- [schema-lab/README.md](file:///c:/Users/Bakheet/Documents/Projects/BGL/schema-lab/README.md) - SchemaLab guide
- [docs/architecture/data-layer.md](file:///c:/Users/Bakheet/Documents/Projects/BGL/docs/architecture/data-layer.md) - Data Layer architecture
- [design-lab/docs/three-document-system.md](file:///c:/Users/Bakheet/Documents/Projects/BGL/design-lab/docs/three-document-system.md) - Document system
- [design-lab/docs/gated-workflow.md](file:///c:/Users/Bakheet/Documents/Projects/BGL/design-lab/docs/gated-workflow.md) - Gated workflow

---

**Status:** ✅ **COMPLETE AND DOCUMENTED**

**Last Updated:** 2025-12-21
