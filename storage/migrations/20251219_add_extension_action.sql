-- =============================================================================
-- Migration: Add extension_action to record_type CHECK constraint
-- =============================================================================
-- Date: 2025-12-19
-- Description: Updates record_type constraint to allow extension_action value
-- This allows the Extension button feature to create extension records
-- =============================================================================

BEGIN TRANSACTION;

-- Step 1: Create backup table
CREATE TABLE IF NOT EXISTS imported_records_backup AS 
SELECT * FROM imported_records;

-- Step 2: Drop old table
DROP TABLE imported_records;

-- Step 3: Recreate table with updated CHECK constraint
CREATE TABLE imported_records (
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

-- Step 4: Restore data
INSERT INTO imported_records 
SELECT * FROM imported_records_backup;

-- Step 5: Drop backup table
DROP TABLE imported_records_backup;

-- Step 6: Recreate indexes
CREATE INDEX IF NOT EXISTS idx_records_session ON imported_records(session_id);
CREATE INDEX IF NOT EXISTS idx_records_supplier ON imported_records(supplier_id);
CREATE INDEX IF NOT EXISTS idx_records_bank ON imported_records(bank_id);
CREATE INDEX IF NOT EXISTS idx_records_match_status ON imported_records(match_status);
CREATE INDEX IF NOT EXISTS idx_records_type ON imported_records(record_type);
CREATE INDEX IF NOT EXISTS idx_records_guarantee ON imported_records(guarantee_number);

COMMIT;
