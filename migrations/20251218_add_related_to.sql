-- Migration: Add related_to field
-- Date: 2025-12-18
-- Purpose: Replace contract_source with properly named related_to field

-- Step 1: Add new column
ALTER TABLE imported_records 
ADD COLUMN related_to VARCHAR(20);

-- Step 2: Copy and transform existing data
UPDATE imported_records 
SET related_to = CASE 
    WHEN contract_source = 'contract' THEN 'contract'
    WHEN contract_source = 'po' THEN 'purchase_order'
    WHEN contract_source = 'manual' THEN NULL  -- Will be backfilled
    ELSE NULL
END
WHERE contract_source IS NOT NULL;

-- Step 3: Backfill empty records using heuristic
UPDATE imported_records
SET related_to = CASE 
    WHEN contract_number LIKE 'C/%' OR contract_number LIKE '%Contract%' THEN 'contract'
    ELSE 'purchase_order'
END
WHERE related_to IS NULL 
  AND contract_number IS NOT NULL 
  AND contract_number != '';

-- Step 4: Create audit trail table
CREATE TABLE IF NOT EXISTS related_to_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    record_id INTEGER NOT NULL,
    old_value VARCHAR(20),
    new_value VARCHAR(20) NOT NULL,
    changed_by VARCHAR(100),
    changed_at TEXT NOT NULL,
    change_reason TEXT,
    FOREIGN KEY (record_id) REFERENCES imported_records(id)
);

CREATE INDEX IF NOT EXISTS idx_audit_record ON related_to_audit(record_id);
CREATE INDEX IF NOT EXISTS idx_audit_date ON related_to_audit(changed_at);

-- Verification queries (to run manually):
-- SELECT COUNT(*) as null_count FROM imported_records WHERE related_to IS NULL;
-- SELECT related_to, COUNT(*) FROM imported_records GROUP BY related_to;
