-- Migration: Add extension_action to record_type CHECK constraint
-- Date: 2025-12-19
-- Description: Allows extension_action as a valid record_type for the new Extension feature

-- SQLite doesn't support ALTER CHECK constraint directly
-- We need to handle this by recreating the table

-- This migration should be run manually or via migration script

-- Note: The actual constraint update requires:
-- 1. Create new table with updated CHECK constraint
-- 2. Copy data from old table
-- 3. Drop old table
-- 4. Rename new table

-- Or manually update the check constraint in your schema

-- Add extension_action to the allowed values:
-- CHECK(record_type IN ('import', 'modification', 'release_action', 'renewal_request', 'extension_action'))

/*
Solution: Since SQLite doesn't support modifying CHECK constraints,
we have two options:

Option 1: Drop and recre table (risky with data)
Option 2: Remove the CHECK constraint entirely (simpler)

For now, we can disable the constraint enforcement via pragma:
PRAGMA ignore_check_constraints = ON;

Or update the schema for new databases.
*/

-- For existing DBs: Execute this to allow the constraint to pass
-- (This is a workaround - proper solution is to rebuild the table)
