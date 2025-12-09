# تنسيق Excel المطلوب
الأعمدة الأساسية وأي أعمدة اختيارية.

## الأعمدة الأساسية
- `supplier_name` (نص)
- `bank_name` (نص)
- `guarantee_number` (نص/رقم)
- `amount` (رقم أو نص قابل للتحويل)
- `issue_date` (تاريخ)
- `expiry_date` (تاريخ)

## أعمدة اختيارية
- `notes`, `reference`, `currency` إن وجدت.

## مثال صف
```
supplier_name   bank_name    guarantee_number   amount   issue_date   expiry_date
Alpha Trading   HSBC         12345              50000    2024-01-05   2025-01-05
```

يفضّل إزالة التنسيقات المعقدة والاعتماد على نصوص بسيطة.
