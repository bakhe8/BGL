# Services & Repositories

## Services (طبقة الخدمات)

أمثلة لخدمات رئيسية:

- `ImportService`
  - مسؤول عن:
    - قراءة بيانات Excel/لصق/إدخال يدوي.
    - تحويلها إلى نموذج موحد.
    - إنشاء سجلات `imported_records`.

- `MatchingService`
  - مسؤول عن:
    - تطبيع الاسم الخام.
    - البحث في `suppliers` و `supplier_alternative_names`.
    - إرجاع نتيجة مطابقة تتضمن:
      - supplier_id
      - match_score
      - match_source (override / official / alternative / learning / fuzzy).

- `ReviewWorkflowService`
  - مسؤول عن:
    - تطبيق قرارات المستخدم على السجلات.
    - إنشاء alternative names عند الحاجة.
    - تحديث حالة السجل (approved/rejected/needs_review).

- `AlternativeNamesService`
  - مسؤول عن:
    - إدارة أسماء المورد البديلة (إنشاء، ربط، زيادة الاستخدام).
    - دعم التعلم من قرارات المراجعة.

- `DictionaryService`
  - مسؤول عن CRUD للموردين/البنوك وتحديث الحقول المؤكدة.

## Repositories (طبقة المستودعات)

- `SupplierRepository`
  - `findById($id)`
  - `findByNormalizedName($normalizedName)`
  - `create(array $data)`
  - `update($id, array $data)`

- `SupplierAlternativeNameRepository`
  - `findByNormalizedName($normalizedName)`
  - `createForSupplier($supplierId, array $data)`
  - `incrementUsage($id)`
