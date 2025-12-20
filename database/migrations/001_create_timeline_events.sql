-- ============================================================================
-- Timeline Events Table Migration
-- ============================================================================
-- Purpose: Unified event logging for all guarantee timeline activities
-- Version: 1.0
-- Created: 2025-12-20
-- ============================================================================

CREATE TABLE IF NOT EXISTS guarantee_timeline_events (
    -- Primary Key
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- ========================================================================
    -- Core References
    -- ========================================================================
    guarantee_number TEXT NOT NULL,     -- Reference to guarantee
    record_id INTEGER,                  -- Reference to imported_records (nullable)
    session_id INTEGER NOT NULL,        -- Reference to import_sessions
    
    -- ========================================================================
    -- Event Classification
    -- ========================================================================
    event_type TEXT NOT NULL,
    -- Enum values:
    --   'import'           - Excel import
    --   'extension'        - Extension letter issued
    --   'release'          - Release letter issued
    --   'reduction'        - Amount reduction
    --   'supplier_change'  - Supplier modified
    --   'bank_change'      - Bank modified
    --   'amount_change'    - Amount modified
    --   'expiry_change'    - Expiry date modified
    
    -- ========================================================================
    -- Change Details
    -- ========================================================================
    field_name TEXT,                    -- 'supplier', 'bank', 'amount', 'expiry_date'
    old_value TEXT,                     -- Display value before change
    new_value TEXT,                     -- Display value after change
    old_id INTEGER,                     -- Entity ID before change (nullable)
    new_id INTEGER,                     -- Entity ID after change (nullable)
    
    -- ========================================================================
    -- Metadata
    -- ========================================================================
    change_type TEXT,                   -- 'entity_change', 'name_correction', 'action'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,                 -- Future: user tracking
    
    -- ========================================================================
    -- Foreign Keys
    -- ========================================================================
    FOREIGN KEY (session_id) REFERENCES import_sessions(id)
);

-- ============================================================================
-- Performance Indexes
-- ============================================================================

-- Main timeline query: Get all events for a guarantee
CREATE INDEX IF NOT EXISTS idx_timeline_guarantee 
ON guarantee_timeline_events(guarantee_number, created_at DESC);

-- Event type filtering
CREATE INDEX IF NOT EXISTS idx_timeline_event_type 
ON guarantee_timeline_events(event_type, created_at DESC);

-- Supplier change analytics
CREATE INDEX IF NOT EXISTS idx_timeline_supplier_changes 
ON guarantee_timeline_events(new_id) 
WHERE event_type = 'supplier_change';

-- Bank change analytics
CREATE INDEX IF NOT EXISTS idx_timeline_bank_changes 
ON guarantee_timeline_events(new_id) 
WHERE event_type = 'bank_change';

-- Session-based queries
CREATE INDEX IF NOT EXISTS idx_timeline_session 
ON guarantee_timeline_events(session_id);

-- Supplier reversion tracking (for success rate)
CREATE INDEX IF NOT EXISTS idx_timeline_supplier_reversions 
ON guarantee_timeline_events(old_id) 
WHERE event_type = 'supplier_change';

-- ============================================================================
-- Verification Query
-- ============================================================================
-- Run this to verify table was created correctly:
-- 
-- SELECT sql FROM sqlite_master 
-- WHERE type='table' AND name='guarantee_timeline_events';
-- 
-- SELECT name FROM sqlite_master 
-- WHERE type='index' AND tbl_name='guarantee_timeline_events';
-- ============================================================================
