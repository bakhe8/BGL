# Indexing Strategy
اختصر الاستعلامات الأكثر استخدامًا وركّز الفهارس عليها.

- `suppliers(normalized_name)` — للبحث السريع أثناء المطابقة.  
- `supplier_alternative_names(normalized_raw_name)` — أهم فهرس لمرور التطبيع.  
- `imported_records(session_id)` — للاستعلام عن جلسة محددة بسرعة.  
- أضف فهرسًا مركبًا عند الحاجة (مثال: `guarantee_number`, `bank_id`) إذا أصبحت عمليات البحث متكررة.  
- تجنب الفهارس المفرطة على جداول صغيرة (لن تكسب سرعة وتزيد الحجم).
