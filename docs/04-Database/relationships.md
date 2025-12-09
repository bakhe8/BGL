# Relationships
العلاقات الرئيسية بين الجداول كما في `schema.sql`.

- **suppliers** ←1..N→ **supplier_alternative_names**  
  - كل مورد يملك عدة أسماء بديلة، حذف المورد يحذف بدائله (ON DELETE CASCADE).

- **suppliers** ←1..N→ **supplier_overrides**  
  - أسماء مفروضة مرتبطة بالمورد نفسه.

- **import_sessions** ←1..N→ **imported_records**  
  - جلسة استيراد تضم جميع الصفوف المستوردة.

- **imported_records** → **suppliers / banks**  
  - ارتباط اختياري بالسجل المعتمد بعد المطابقة.

- **learning_log**  
  - غير مرتبط بمفتاح خارجي حاليًا، يستخدم لتتبع قرارات التعلم والاقتراحات.
