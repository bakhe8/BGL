-- =============================================================================
-- Migration: Add New Session Architecture
-- Date: 2025-12-20
-- Purpose: Add new tables for guarantees/actions separation without breaking existing code
-- =============================================================================

-- =============================================================================
-- TABLE 1: import_batches
-- Purpose: Group related imports together (replaces session concept for imports)
-- =============================================================================

CREATE TABLE IF NOT EXISTS import_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_type TEXT NOT NULL,                  -- excel_import, manual_batch, text_paste
    description TEXT,
    filename TEXT NULL,
    
    -- Statistics
    total_records INTEGER DEFAULT 0,
    ready_records INTEGER DEFAULT 0,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_batches_type ON import_batches(batch_type);
CREATE INDEX IF NOT EXISTS idx_batches_created ON import_batches(created_at);

-- =============================================================================
-- TABLE 2: action_sessions
-- Purpose: Group related actions together (for batch operations)
-- =============================================================================

CREATE TABLE IF NOT EXISTS action_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_date DATE NOT NULL,
    description TEXT,                          -- "جلسة تمديدات - 2025-12-20"
    
    -- Statistics
    total_actions INTEGER DEFAULT 0,
    issued_actions INTEGER DEFAULT 0,
    
    -- Locking (for history protection)
    is_locked BOOLEAN DEFAULT 0,
    locked_at DATETIME,
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_action_sessions_date ON action_sessions(session_date);
CREATE INDEX IF NOT EXISTS idx_action_sessions_locked ON action_sessions(is_locked);

-- =============================================================================
-- TABLE 3: guarantees
-- Purpose: Store guarantee data (imports & manual entries)
-- =============================================================================

CREATE TABLE IF NOT EXISTS guarantees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Guarantee Information
    guarantee_number TEXT NOT NULL,
    raw_supplier_name TEXT NOT NULL,
    raw_bank_name TEXT NOT NULL,
    contract_number TEXT,
    amount REAL,
    issue_date DATE,
    expiry_date DATE,
    type TEXT,
    comment TEXT,
    
    -- Matching (editable)
    supplier_id INTEGER,
    bank_id INTEGER,
    supplier_display_name TEXT,
    bank_display TEXT,
    match_status TEXT DEFAULT 'needs_review' CHECK(match_status IN ('needs_review', 'ready')),
    
    -- Import source (for grouping and bulk operations)
    import_batch_id INTEGER NULL,              -- NULL for individual records
    import_type TEXT NOT NULL,                 -- excel, manual, paste
    import_date DATE NOT NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_guarantees_number ON guarantees(guarantee_number);
CREATE INDEX IF NOT EXISTS idx_guarantees_batch ON guarantees(import_batch_id);
CREATE INDEX IF NOT EXISTS idx_guarantees_date ON guarantees(import_date);
CREATE INDEX IF NOT EXISTS idx_guarantees_status ON guarantees(match_status);
CREATE INDEX IF NOT EXISTS idx_guarantees_supplier ON guarantees(supplier_id);
CREATE INDEX IF NOT EXISTS idx_guarantees_bank ON guarantees(bank_id);
CREATE INDEX IF NOT EXISTS idx_guarantees_type ON guarantees(import_type);

-- =============================================================================
-- TABLE 4: guarantee_actions
-- Purpose: Store guarantee actions (extension, release, etc) as historical events
-- =============================================================================

CREATE TABLE IF NOT EXISTS guarantee_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- Related guarantee
    guarantee_number TEXT NOT NULL,
    guarantee_id INTEGER NULL,                 -- Reference (optional)
    
    -- Action type
    action_type TEXT NOT NULL CHECK(action_type IN ('extension', 'release', 'renewal', 'modification')),
    action_session_id INTEGER NULL,            -- Optional grouping session
    action_date DATE NOT NULL,
    
    -- Action details
    previous_expiry_date DATE,
    new_expiry_date DATE,
    previous_amount REAL,
    new_amount REAL,
    notes TEXT,
    
    -- Related data (snapshot at action time)
    supplier_id INTEGER,
    bank_id INTEGER,
    supplier_display_name TEXT,
    bank_display TEXT,
    
    -- Action status
    action_status TEXT DEFAULT 'draft' CHECK(action_status IN ('draft', 'issued')),
    is_locked BOOLEAN DEFAULT 0,               -- After issuance = locked
    
    -- Tracking
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by TEXT,
    issued_at DATETIME,
    
    FOREIGN KEY (guarantee_id) REFERENCES guarantees(id) ON DELETE SET NULL,
    FOREIGN KEY (action_session_id) REFERENCES action_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_actions_number ON guarantee_actions(guarantee_number);
CREATE INDEX IF NOT EXISTS idx_actions_session ON guarantee_actions(action_session_id);
CREATE INDEX IF NOT EXISTS idx_actions_date ON guarantee_actions(action_date);
CREATE INDEX IF NOT EXISTS idx_actions_type ON guarantee_actions(action_type);
CREATE INDEX IF NOT EXISTS idx_actions_status ON guarantee_actions(action_status);
CREATE INDEX IF NOT EXISTS idx_actions_locked ON guarantee_actions(is_locked);

-- =============================================================================
-- ADD TRANSITION COLUMNS TO OLD TABLE
-- Purpose: Link old records to new tables during migration
-- =============================================================================

-- Check if columns don't exist before adding
-- SQLite doesn't have IF NOT EXISTS for ALTER TABLE, so we'll use a different approach in the script

-- These will be added by the migration script:
-- ALTER TABLE imported_records ADD COLUMN migrated_guarantee_id INTEGER NULL;
-- ALTER TABLE imported_records ADD COLUMN migrated_action_id INTEGER NULL;
-- ALTER TABLE imported_records ADD COLUMN import_batch_id INTEGER NULL;

-- =============================================================================
-- NOTES
-- =============================================================================
-- 
-- 1. This migration is ADDITIVE ONLY - no existing data is modified
-- 2. Old tables (imported_records, import_sessions) remain unchanged
-- 3. New code will dual-write to both old and new tables
-- 4. Migration links will be created via migrated_* columns
-- 5. Safe to rollback by simply dropping the new tables
--
-- =============================================================================
