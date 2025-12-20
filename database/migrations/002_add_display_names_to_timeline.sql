-- ============================================================================
-- Add Display Names to Timeline Events
-- ============================================================================
-- Purpose: Store supplier and bank display names for faster queries
-- Version: 1.1
-- Date: 2025-12-20
-- ============================================================================

-- Add supplier_display_name column
ALTER TABLE guarantee_timeline_events 
ADD COLUMN supplier_display_name TEXT;

-- Add bank_display column
ALTER TABLE guarantee_timeline_events 
ADD COLUMN bank_display TEXT;

-- ============================================================================
-- Verification
-- ============================================================================
-- Check that columns were added:
-- PRAGMA table_info(guarantee_timeline_events);
-- ============================================================================
