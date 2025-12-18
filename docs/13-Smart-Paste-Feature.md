# Smart Paste Feature (Text Import)

**Version:** 1.0  
**Date:** 2025-12-18

## 1. Overview
The **Smart Paste** feature allows users to copy unstructured text (e.g., from emails, PDFs, or Excel) containing Bank Guarantee details and paste it directly into the application. The system analyzes the text, extracts key fields using advanced regex logic, and creates new structured records.

## 2. Key Capabilities
-   **Unstructured Text Parsing**: Extracts Amount, Currency, Guarantee No, Contract No, Dates, Supplier, and Bank.
-   **Sequential Consumption Strategy**: Increases accuracy by consuming (removing) matched parts of the text to prevent false positives in subsequent steps.
-   **Multi-Row (Bulk) Support**: Automatically detects table-like structures (multiple records) and splits them into separate records.
-   **Bilingual Support**: Handles Arabic and English labels and dates (e.g., "8 يناير 2026").
-   **Integration**: Fully integrated with the Matching Engine (auto-detection of Suppliers/Banks) and Stats system.

## 3. Architecture

### 3.1 Flow
1.  **Frontend**: User pastes text into Modal -> `POST /api/import/text`.
2.  **Controller**: `TextImportController` receives text.
3.  **Parsing**: `TextParsingService::parseBulk($text)` is called.
    -   Splits text by newline.
    -   Detects if multiple lines contain "Amounts".
    -   If Yes (Bulk): Processes each line as a separate record.
    -   If No (Single): Processes the entire text as one record.
4.  **Extraction (Per Record)**: `TextParsingService::parse($text)` executes fields in specific order:
    1.  Amount & Currency
    2.  Dates (Expiry)
    3.  Contract Number
    4.  Bank (Acronyms & Names)
    5.  Type (Final/Advance)
    6.  Guarantee Number
    7.  Supplier (Remaining text)
5.  **Storage**: `TextImportController` creates an `ImportSession` and `ImportedRecord`(s).
6.  **Matching**: `MatchingService` runs to identify Supplier/Bank IDs.
7.  **Response**: Returns `session_id` to redirect the user.

### 3.2 Sequential Consumption
To avoid confusion (e.g., a Contract Number like `1014` being matched as part of a Supplier Name), the parser "consumes" matches.
-   **Step 1**: Find Amount `500,000`. Extracted. Replaced with spaces in working text.
-   **Step 2**: Find Date `2025-01-01`. Extracted. Replaced with spaces.
-   ...
-   **Final Step**: Extract Supplier. Only the company name remains (mostly).

## 4. API Reference
**Endpoint**: `POST /api/import/text`
**Payload**:
```json
{
  "text": "Your unstructured text here..."
}
```
**Response**:
```json
{
  "success": true,
  "record_id": 12345, // First record ID
  "session_id": 395,
  "count": 2 // Number of records created
}
```

## 5. Extending & Maintenance
The core logic resides in `app/Services/TextParsingService.php`.

### To Add a New Bank Acronym:
Update the regex in `extractBank`:
```php
$knownBanks = 'SAB|SABB|SNB|ANB|...|NEWBANK';
```

### To Improve Supplier Detection:
Adjust the regex in `extractSupplier`. Note that `Co`, `Ltd`, `Inc` are used as anchors.

### Troubleshooting
-   If a field is not extracted, check if it was "consumed" by an earlier step (unlikely, as specific fields are prioritized).
-   If Supplier name includes noise (e.g. `101 Supplier`), check the "Leading Digits" regex in `extractSupplier`.
