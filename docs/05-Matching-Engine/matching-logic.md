# منطق المطابقة
ترتيب الخطوات لتحديد المورد/البنك الصحيح.

1) **Check Overrides** — إذا وجد تطابق للـ `override_name` يعود مباشرة.  
2) **Official Name Match** — مقارنة `normalized_name` مع جدول suppliers.  
3) **Alternative Names (confirmed)** — البحث في `supplier_alternative_names` المؤكدة.  
4) **Alternative Names (unconfirmed/learning)** — نتائج أقل ثقة.  
5) **Raw Similarity** — استخدام تطابق تقريبي (Levenshtein/Jaro) مع عتبة منخفضة.

النتيجة ترجع:
- `supplier_id` المحتمل.
- `match_score` (0..1).
- `match_source` (override | official | alt_confirmed | alt_learning | fuzzy_raw).
- `needs_review` (boolean) بناءً على العتبة.
