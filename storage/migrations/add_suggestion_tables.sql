-- =============================================================================
-- Migration: Add Suggestion & Decision Tables
-- Date: 2025-12-17
-- Purpose: Simplify supplier matching by caching suggestions and logging decisions
-- =============================================================================

-- =============================================================================
-- TABLE 1: supplier_suggestions
-- Purpose: Cache pre-computed suggestions for each normalized Excel name
-- Instead of calculating on every page load, we store and retrieve
-- =============================================================================

CREATE TABLE IF NOT EXISTS supplier_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- المفتاح: الاسم المطبّع من Excel
    -- Key: normalized version of the raw Excel name
    normalized_input VARCHAR(500) NOT NULL,
    
    -- المورد المقترح
    -- The suggested supplier
    supplier_id INTEGER NOT NULL,
    
    -- اسم العرض
    -- Display name for this suggestion
    display_name VARCHAR(500) NOT NULL,
    
    -- مصدر الاقتراح (مهم للأوزان!)
    -- Source of suggestion: determines weight in scoring
    -- Values: 'dictionary', 'alternatives', 'learning', 'user_history'
    source VARCHAR(50) NOT NULL,
    
    -- درجة التشابه (Fuzzy matching result)
    -- Score from 0.0 to 1.0
    fuzzy_score REAL DEFAULT 0.0,
    
    -- وزن المصدر
    -- Source weight: dictionary=40, alternatives=60, user_history=80, learning=100
    source_weight INTEGER DEFAULT 0,
    
    -- عدد مرات الاستخدام
    -- How many times user selected this suggestion
    usage_count INTEGER DEFAULT 0,
    
    -- النتيجة الإجمالية (يُحسب عند الحفظ)
    -- Total score = (fuzzy_score × 100) + source_weight + min(usage_count × 15, 75)
    total_score REAL DEFAULT 0.0,
    
    -- تصنيف النجوم (1, 2, or 3)
    -- Star rating: ≥220=3, ≥160=2, else=1
    star_rating INTEGER DEFAULT 1,
    
    -- آخر تحديث
    -- When this record was last updated
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- مفتاح فريد: لا يمكن أن يكون نفس المورد مرتين لنفس الاسم من نفس المصدر
    -- Unique constraint: same supplier can appear from different sources
    UNIQUE(normalized_input, supplier_id, source)
);

-- Indexes for fast lookups
CREATE INDEX IF NOT EXISTS idx_suggestions_input ON supplier_suggestions(normalized_input);
CREATE INDEX IF NOT EXISTS idx_suggestions_score ON supplier_suggestions(total_score DESC);
CREATE INDEX IF NOT EXISTS idx_suggestions_supplier ON supplier_suggestions(supplier_id);


-- =============================================================================
-- TABLE 2: user_decisions
-- Purpose: Log every decision made (user selection or propagation)
-- This solves the "where did this name come from?" problem
-- =============================================================================

CREATE TABLE IF NOT EXISTS user_decisions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- السجل المتأثر
    -- The record that was updated
    record_id INTEGER NOT NULL,
    
    -- جلسة الاستيراد
    -- Import session (for grouping)
    session_id INTEGER NOT NULL,
    
    -- الاسم الأصلي من Excel
    -- Raw name exactly as it appeared in Excel
    raw_name VARCHAR(500) NOT NULL,
    
    -- الاسم المطبّع
    -- Normalized version for matching
    normalized_name VARCHAR(500) NOT NULL,
    
    -- المورد المختار
    -- The supplier that was chosen
    chosen_supplier_id INTEGER NOT NULL,
    
    -- اسم العرض المختار
    -- Display name that was selected
    chosen_display_name VARCHAR(500),
    
    -- مصدر القرار (الأهم!)
    -- How this decision was made:
    -- 'user_click' = User clicked on a chip
    -- 'user_typed' = User typed a new name
    -- 'auto_select' = System auto-selected (99%+ match)
    -- 'propagation' = Copied from another record in same session
    -- 'import' = Came from Excel with supplier_id already set
    decision_source VARCHAR(50) NOT NULL,
    
    -- متى تم القرار
    -- When the decision was made
    decided_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (chosen_supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (record_id) REFERENCES imported_records(id)
);

-- Indexes for common queries
CREATE INDEX IF NOT EXISTS idx_decisions_normalized ON user_decisions(normalized_name);
CREATE INDEX IF NOT EXISTS idx_decisions_supplier ON user_decisions(chosen_supplier_id);
CREATE INDEX IF NOT EXISTS idx_decisions_record ON user_decisions(record_id);
CREATE INDEX IF NOT EXISTS idx_decisions_session ON user_decisions(session_id);
CREATE INDEX IF NOT EXISTS idx_decisions_source ON user_decisions(decision_source);


-- =============================================================================
-- MIGRATION NOTES
-- =============================================================================
-- 
-- 1. These tables are ADDITIVE - they don't modify existing tables
-- 2. Existing learning data remains in supplier_aliases_learning (backward compatibility)
-- 3. Run this script with: sqlite3 storage/database.sqlite < storage/migrations/add_suggestion_tables.sql
-- 4. To rollback: DROP TABLE supplier_suggestions; DROP TABLE user_decisions;
--
-- =============================================================================
