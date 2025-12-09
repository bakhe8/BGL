# Database Naming Standards

## 1. Tables
Use `snake_case` and nouns:
- suppliers
- supplier_alternative_names
- imported_records

## 2. Columns
Rules:
- Avoid abbreviations.
- Use `created_at` / `updated_at` timestamp convention.
- Boolean stored as INTEGER (0 or 1).

## 3. Indexing
Always index:
- normalized_raw_name
- normalized_name

## 4. Foreign Keys
Always cascade deletes unless explicitly prevented.

## 5. Data Integrity
- Normalization must happen before insert.
- Raw input is always preserved for audit.
