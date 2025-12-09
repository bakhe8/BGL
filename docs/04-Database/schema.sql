-- ==========================================
-- DATABASE SCHEMA — SQLite
-- Banking Guarantees Management System
-- ==========================================

PRAGMA foreign_keys = ON;

-- =========================
-- TABLE: suppliers
-- =========================
CREATE TABLE suppliers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    official_name TEXT NOT NULL,
    display_name TEXT,
    normalized_name TEXT NOT NULL,
    is_confirmed INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_suppliers_normalized ON suppliers (normalized_name);

-- =========================
-- TABLE: supplier_alternative_names
-- =========================
CREATE TABLE supplier_alternative_names (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    raw_name TEXT NOT NULL,
    normalized_raw_name TEXT NOT NULL,
    source TEXT NOT NULL,
    occurrence_count INTEGER DEFAULT 1,
    last_seen_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

CREATE INDEX idx_alt_normalized ON supplier_alternative_names (normalized_raw_name);

-- =========================
-- TABLE: supplier_overrides
-- =========================
CREATE TABLE supplier_overrides (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_id INTEGER NOT NULL,
    override_name TEXT NOT NULL,
    notes TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- =========================
-- TABLE: learning_log
-- =========================
CREATE TABLE learning_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    raw_input TEXT NOT NULL,
    normalized_input TEXT NOT NULL,
    suggested_supplier_id INTEGER,
    decision_result TEXT NOT NULL, -- auto | manual | rejected
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- TABLE: import_sessions
-- =========================
CREATE TABLE import_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_type TEXT NOT NULL, -- excel | manual | paste
    record_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- TABLE: imported_records
-- =========================
CREATE TABLE imported_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    raw_supplier_name TEXT NOT NULL,
    raw_bank_name TEXT NOT NULL,
    amount TEXT,
    guarantee_number TEXT,
    issue_date TEXT,
    expiry_date TEXT,
    normalized_supplier TEXT,
    normalized_bank TEXT,
    match_status TEXT, -- auto | needs_review | matched
    supplier_id INTEGER,
    bank_id INTEGER,
    FOREIGN KEY (session_id) REFERENCES import_sessions(id) ON DELETE CASCADE
);

