# Migration Guide — SQLite Schema

## 1. Principles
- Never delete fields; mark them deprecated.
- Additive migrations only (new tables, new fields).
- Always backup db.sqlite before applying schema changes.

## 2. How to Apply Migrations
1. Create a new file in /migrations, e.g.:  
   `2026-01-10-add-bank-tables.sql`
2. Add only the required statements.
3. Apply using:  
   `.read migrations/<file>.sql`
4. Verify via:  
   `PRAGMA foreign_keys = ON;`
   `SELECT name FROM sqlite_master WHERE type='table';`

## 3. Versioning
- Schema version is stored in table `schema_meta`.
- Every migration increments the version number.

## 4. Rollback Rules
- SQLite does not support DROP COLUMN.
- Instead:
  - Create new table.
  - Copy old data.
  - Replace original table.
