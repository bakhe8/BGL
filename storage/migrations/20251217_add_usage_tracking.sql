-- ══════════════════════════════════════════════════════════════
-- Migration: Add Usage Tracking to Learning Tables
-- Purpose: Track how many times user has used each learned name
-- Author: Development Team
-- Date: 2025-12-17
-- ══════════════════════════════════════════════════════════════

BEGIN TRANSACTION;

-- ══════════════════════════════════════════════════════════════
-- IMPORTANT: SQLite Limitation Workaround
-- SQLite doesn't support DEFAULT CURRENT_TIMESTAMP in ALTER TABLE
-- Solution: Add columns with NULL default, then UPDATE existing rows
-- ══════════════════════════════════════════════════════════════

-- Add usage_count to supplier_aliases_learning
ALTER TABLE supplier_aliases_learning 
ADD COLUMN usage_count INTEGER DEFAULT NULL;

-- Add last_used_at to supplier_aliases_learning  
ALTER TABLE supplier_aliases_learning 
ADD COLUMN last_used_at TIMESTAMP DEFAULT NULL;

-- Update existing records to have default values
UPDATE supplier_aliases_learning 
SET usage_count = 1, 
    last_used_at = CURRENT_TIMESTAMP 
WHERE usage_count IS NULL;

-- Create index for performance (using correct column: linked_supplier_id)
CREATE INDEX IF NOT EXISTS idx_supplier_aliases_learning_usage 
ON supplier_aliases_learning(linked_supplier_id, usage_count DESC, last_used_at DESC);

-- Same for bank_aliases_learning
ALTER TABLE bank_aliases_learning 
ADD COLUMN usage_count INTEGER DEFAULT NULL;

ALTER TABLE bank_aliases_learning 
ADD COLUMN last_used_at TIMESTAMP DEFAULT NULL;

UPDATE bank_aliases_learning 
SET usage_count = 1, 
    last_used_at = CURRENT_TIMESTAMP 
WHERE usage_count IS NULL;

-- Create index for performance (using correct column: bank_id)
CREATE INDEX IF NOT EXISTS idx_bank_aliases_learning_usage 
ON bank_aliases_learning(bank_id, usage_count DESC, last_used_at DESC);

COMMIT;
