-- =============================================================================
-- Migration: Add record_type to imported_records
-- =============================================================================
-- Date: 2025-12-19
-- Purpose: Enable tracking of different record types (import, release_action, etc.)
-- =============================================================================

-- Step 1: Add the new column
ALTER TABLE imported_records 
ADD COLUMN record_type TEXT DEFAULT 'import' 
CHECK(record_type IN ('import', 'modification', 'release_action', 'renewal_request'));

-- Step 2: Update existing records to have the default type
UPDATE imported_records 
SET record_type = 'import' 
WHERE record_type IS NULL;

-- Step 3: Create index for performance
CREATE INDEX IF NOT EXISTS idx_records_type 
ON imported_records(record_type);

-- =============================================================================
-- Verification Query
-- =============================================================================
-- Run this to verify the migration worked:
-- SELECT record_type, COUNT(*) FROM imported_records GROUP BY record_type;
-- Expected: All existing records should show 'import'
