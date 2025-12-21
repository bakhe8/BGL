-- ============================================================================
-- Clean Development Data (Pre-Production Cleanup)
-- ============================================================================
-- Purpose: Remove test/development data created during system development
-- Run ONCE before going to production
-- Production Start Date: 2025-12-20
-- ============================================================================

-- Backup first (safety!)
.output
.backup 'bgl_before_cleanup.sqlite'

-- ============================================================================
-- Step 1: Identify Development Data
-- ============================================================================

SELECT '=== Development Data Summary ===' as step;

SELECT 
    'Old Modifications' as type,
    COUNT(*) as count,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM imported_records 
WHERE record_type = 'modification'
  AND created_at < '2025-12-20 00:00:00';

SELECT 
    'Old Timeline Events' as type,
    COUNT(*) as count,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM guarantee_timeline_events 
WHERE created_at < '2025-12-20 00:00:00';

-- ============================================================================
-- Step 2: Archive Development Data (Optional)
-- ============================================================================

-- Create archive table for reference
CREATE TABLE IF NOT EXISTS dev_data_archive AS 
SELECT 
    *,
    'archived_' || datetime('now') as archive_timestamp
FROM imported_records 
WHERE record_type = 'modification'
  AND created_at < '2025-12-20 00:00:00';

SELECT 'Archived ' || changes() || ' development modification records' as result;

-- ============================================================================
-- Step 3: Delete Development Data
-- ============================================================================

-- Delete old modification records
DELETE FROM imported_records 
WHERE record_type = 'modification'
  AND created_at < '2025-12-20 00:00:00';

SELECT 'Deleted ' || changes() || ' old modification records' as result;

-- Delete test timeline events (TEST guarantees only)
DELETE FROM guarantee_timeline_events 
WHERE created_at < '2025-12-20 00:00:00'
  AND guarantee_number LIKE 'TEST%';

SELECT 'Deleted ' || changes() || ' test timeline events' as result;

-- ============================================================================
-- Step 4: Optimize Database
-- ============================================================================

VACUUM;

SELECT '=== Database Optimized ===' as result;

-- ============================================================================
-- Step 5: Verify Cleanup
-- ============================================================================

SELECT '=== Post-Cleanup Summary ===' as step;

SELECT 
    'Remaining Modifications' as type,
    COUNT(*) as count
FROM imported_records 
WHERE record_type = 'modification';

SELECT 
    'Production Timeline Events' as type,
    COUNT(*) as count,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM guarantee_timeline_events;

SELECT 
    'Archive Table' as type,
    COUNT(*) as count
FROM dev_data_archive;

-- ============================================================================
-- Step 6: Create Protection Trigger (Prevent Future Accidental Deletion)
-- ============================================================================

DROP TRIGGER IF EXISTS prevent_production_timeline_deletion;

CREATE TRIGGER prevent_production_timeline_deletion
BEFORE DELETE ON guarantee_timeline_events
WHEN OLD.created_at >= '2025-12-20 00:00:00'
BEGIN
    SELECT RAISE(FAIL, 'Cannot delete production timeline data! Created after 2025-12-20.');
END;

SELECT '=== Production Data Protection Active ===' as result;

-- ============================================================================
-- Cleanup Complete!
-- ============================================================================
-- 
-- What was done:
-- ✅ Development modifications archived to dev_data_archive
-- ✅ Development modifications deleted from imported_records
-- ✅ Test timeline events deleted
-- ✅ Database optimized (VACUUM)
-- ✅ Protection trigger created for production data
--
-- Production data (from 2025-12-20) is now:
-- - Clean and ready
-- - Protected from accidental deletion
-- - Will be preserved forever
-- ============================================================================
