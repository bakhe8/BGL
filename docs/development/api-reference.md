# دليل الواجهة البرمجية (API Reference)

يوضح هذا الدليل نقاط الوصول (Endpoints) الرئيسية التي تستخدمها الواجهة الأمامية للتواصل مع النظام.

## 📡 Decision API

### `POST /process_update.php`
حفظ قرار المستخدم لسجل معين.

**Request**:
```json
{
  "record_id": 123,
  "supplier_id": 45,
  "bank_id": 2,
  "decision_source": "user_click"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Saved successfully"
}
```

### `GET /api/guarantee-history.php`
جلب سجل الأحداث لضمان معين.

**Query Params**:
*   `number`: رقم الضمان (مطلوب).

**Response**:
```json
{
  "history": [
    {
      "event_type": "import",
      "date": "2025-01-01",
      "badge": "استيراد"
    }
  ]
}
```

---

## 📥 Import API

### `POST /api/upload_excel.php`
رفع ملف Excel للمعالجة.

**Form Data**:
*   `file`: ملف Excel (xlsx).

**Response**:
```json
{
  "success": true,
  "session_id": 101,
  "count": 50
}
```

### `POST /api/import/text`
استيراد نص ذكي (Smart Paste).

**Request**:
```json
{
  "text": "Raw text content..."
}
```

---

## 📚 Dictionary API

### `GET /api/suppliers`
جلب قائمة الموردين (للبحث).

**Query Params**:
*   `q`: كلمة البحث.

### `POST /api/suppliers`
إضافة مورد جديد يدوياً.

**Request**:
```json
{
  "name": "New Supplier Name"
}
```
