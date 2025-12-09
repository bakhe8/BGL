# Validation & Error Handling

## Validation (التحقق من صحة البيانات)

- سيتم استخدام طبقة بسيطة للتحقق:
  - دالة `validate(array $data, array $rules)` في مكان مركزي (مثلاً `app/Support/Validator.php`).
  - أمثلة قواعد:
    - `required`, `string`, `integer`, `date`, `numeric`, `max:255`.

- مثال:
  ```php
  $this->validator->validate($requestData, [
      'file' => ['required'],
  ]);
  ```

## Error Handling (التعامل مع الأخطاء)

- أي خطأ يجب أن:
  - يُسجَّل في `logs/app.log`.
  - يرجع Response موحد:
    - `success: false`
    - `error_code`
    - `message` (مفهوم للمستخدم أو المبرمج حسب الحالة).

- مثال JSON عند خطأ:
  ```json
  {
    "success": false,
    "error_code": "IMPORT_INVALID_EXCEL",
    "message": "ملف الإكسل غير صالح أو لا يحتوي على الصفوف المتوقعة."
  }
  ```
