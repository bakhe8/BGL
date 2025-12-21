# Test Scripts - Timeline Events

This directory contains test scripts used during development of the Timeline Events feature.

## Purpose
These scripts were created to:
- Debug snapshot data issues
- Verify event logging
- Test API responses
- Validate database schema

## Organization

### Keep (Useful for Future)
- `health_check.php` - General system health check
- `cleanup_database.php` - Database maintenance
- `apply_schema.php` - Schema migrations

### Archive (Timeline Tests)
All other files are timeline-specific debug scripts that can be archived or deleted:
- `check_*.php` - Various snapshot/timeline checks
- `test_*.php` - API and service tests
- `debug_*.php` - Debug helpers
- `*.sql` - SQL debugging queries

## Recommendation
Move timeline-specific test scripts to `scripts/archive/timeline-tests/` for reference.

## Usage
To run any script:
```bash
php scripts/[script-name].php
```

## Cleanup Notes
- Debug scripts created during: 2025-12-19 to 2025-12-21
- Related feature: Timeline Events System
- Can be safely archived after feature is stable
