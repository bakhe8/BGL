-- =============================================================================
-- Migration: Fix AUTOINCREMENT for imported_records
-- =============================================================================
-- Date: 2025-12-19
-- Issue: ALTER TABLE caused AUTOINCREMENT corruption - new records have NULL id
-- Solution: Rebuild table with proper structure
-- =============================================================================

BEGIN TRANSACTION;

-- Step 1: Create new table with correct structure (matching actual columns)
CREATE TABLE imported_records_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    raw_supplier_name TEXT NULL,
    raw_bank_name TEXT NULL,
    amount TEXT NULL,
    guarantee_number TEXT NOT NULL,
    issue_date TEXT NULL,
    expiry_date TEXT NULL,
    normalized_supplier TEXT NULL,
    normalized_bank TEXT NULL,
    match_status TEXT DEFAULT 'needs_review' CHECK(match_status IN ('ready', 'needs_review')),
    supplier_id INTEGER NULL,
    bank_id INTEGER NULL,
    created_at TEXT DEFAULT (datetime('now')),
    decision_result TEXT NULL,
    contract_number TEXT NULL,
    type TEXT NULL,
    comment TEXT NULL,
    bank_display TEXT NULL,
    supplier_display_name TEXT NULL,
    related_to TEXT NULL,
    record_type TEXT DEFAULT 'import' CHECK(record_type IN ('import', 'modification', 'release_action', 'renewal_request', 'extension_action')),
    
    FOREIGN KEY (session_id) REFERENCES import_sessions(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (bank_id) REFERENCES banks(id)
);

-- Step 2: Copy all data from old table
INSERT INTO imported_records_new (
    id, session_id, raw_supplier_name, raw_bank_name,
    amount, guarantee_number, issue_date, expiry_date,
    normalized_supplier, normalized_bank, match_status,
    supplier_id, bank_id, created_at, decision_result,
    contract_number, type, comment, bank_display,
    supplier_display_name, related_to, record_type
)
SELECT 
    id, session_id, raw_supplier_name, raw_bank_name,
    amount, guarantee_number, issue_date, expiry_date,
    normalized_supplier, normalized_bank, match_status,
    supplier_id, bank_id, created_at, decision_result,
    contract_number, type, comment, bank_display,
    supplier_display_name, related_to,
    COALESCE(record_type, 'import') as record_type
FROM imported_records;

-- Step 3: Drop old table
DROP TABLE imported_records;

-- Step 4: Rename new table
ALTER TABLE imported_records_new RENAME TO imported_records;

-- Step 5: Recreate indexes
CREATE INDEX IF NOT EXISTS idx_records_session 
ON imported_records(session_id);

CREATE INDEX IF NOT EXISTS idx_records_supplier 
ON imported_records(supplier_id);

CREATE INDEX IF NOT EXISTS idx_records_bank 
ON imported_records(bank_id);

CREATE INDEX IF NOT EXISTS idx_records_match_status 
ON imported_records(match_status);

CREATE INDEX IF NOT EXISTS idx_records_type 
ON imported_records(record_type);

CREATE INDEX IF NOT EXISTS idx_records_guarantee
ON imported_records(guarantee_number);

COMMIT;

-- =============================================================================
-- Verification
-- =============================================================================
-- SELECT COUNT(*) as total FROM imported_records;
-- SELECT MAX(id) as max_id FROM imported_records;

