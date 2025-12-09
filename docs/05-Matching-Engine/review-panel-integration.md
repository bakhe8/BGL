# التكامل مع لوحة المراجعة
البيانات التي يرسلها المحرك للواجهة وكيف تُعرض.

## استجابة نموذجية
```json
{
  "id": 123,
  "raw_supplier_name": "ال فاء تريدنج",
  "normalized_supplier": "الفاء تريدنج",
  "suggestion": {
    "supplier_id": 5,
    "supplier_name": "Alpha Trading",
    "match_score": 0.92,
    "match_source": "alt_confirmed",
    "needs_review": false
  },
  "alternatives": [
    { "supplier_id": 5, "name": "Alpha Trading", "score": 0.92 },
    { "supplier_id": 7, "name": "Alfa Trade", "score": 0.81 }
  ]
}
```

## إرشادات العرض
- وضّح مصدر النتيجة (رسالة جانبية مثل "من اسم بديل").  
- إذا `needs_review = true` أضف شارة تحذير ولا تسمح بالتصدير قبل القرار.  
- أزرار القرار يجب أن تعيد الحقول اللازمة: `record_id`, `supplier_id` أو `new_supplier_name`, خيار "أضف كبديل" (يرتبط بـ SupplierAlternativeName).
