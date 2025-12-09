# ملخص المخطط
شرح نصي للجداول الرئيسية (التفاصيل الكاملة في `schema.sql`).

- **suppliers**  
  - `official_name`, `display_name`, `normalized_name`, `is_confirmed`, الطوابع الزمنية.

- **supplier_alternative_names**  
  - `supplier_id`, `raw_name`, `normalized_raw_name`, `source`, `occurrence_count`, `last_seen_at`.

- **supplier_overrides**  
  - `supplier_id`, `override_name`, `notes`, `created_at`.

- **learning_log**  
  - `raw_input`, `normalized_input`, `suggested_supplier_id`, `decision_result`, `created_at`.

- **import_sessions**  
  - `session_type`, `record_count`, `created_at`.

- **imported_records**  
  - `session_id`, الأسماء الخام، `normalized_supplier/bank`, حالة المطابقة، المفاتيح إلى المورد/البنك، البيانات المرافقة (مبلغ، أرقام، تواريخ).

تأكد من تفعيل `PRAGMA foreign_keys = ON` في كل اتصال.
