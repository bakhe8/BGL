# Migration Plan: Add decision_source Field

**MP-001**  
**Related:** SIA-001 (Schema Impact Analysis)  
**Type:** 🟢 Additive (Type 1)  
**Risk:** LOW  
**Duration:** 1 week (cautious rollout)

---

## Overview

**Goal:** Add `decision_source` column to `imported_records` table safely

**Strategy:** Phased rollout with monitoring

**Rollback:** Simple (DROP COLUMN)

---

## Phase 1: Schema Addition

### Objective
Add the new column to database

### Duration
**30 minutes**

### Actions

```sql
-- 1. Add column with DEFAULT
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';

-- 2. Verify
SELECT COUNT(*) FROM imported_records WHERE decision_source IS NULL;
-- Expected: 0 (all have default)

-- 3. Optional: Add index (for analytics)
CREATE INDEX idx_decision_source 
ON imported_records(decision_source);
```

### Validation

```sql
-- Test insert
INSERT INTO imported_records (supplier, bank, amount, match_status) 
VALUES ('Test Supplier', 'Test Bank', 1000, 'pending');

-- Check default worked
SELECT decision_source FROM imported_records 
WHERE supplier = 'Test Supplier';
-- Expected: 'manual'

-- Cleanup test
DELETE FROM imported_records WHERE supplier = 'Test Supplier';
```

### Success Criteria

- [ ] Column exists
- [ ] Default value works
- [ ] No NULL values
- [ ] Index created (if opted for)
- [ ] No errors in production logs

### Rollback (if needed)

```sql
-- Simple: just drop the column
ALTER TABLE imported_records 
DROP COLUMN decision_source;

-- Verify
PRAGMA table_info(imported_records);
-- decision_source should not appear
```

**Downtime:** None  
**Data Loss:** None

---

## Phase 2: Backend Integration (Dual-Write)

### Objective
Update backend to write to new column

### Duration
**2 days** (development + testing)

### Code Changes

**File:** `app/Controllers/DecisionController.php`

```php
public function saveDecision(int $id, array $payload) {
    // Extract source (with fallback)
    $source = $payload['source'] ?? 'manual';
    
    // Validate source
    $validSources = ['ai_quick', 'ai_manual', 'manual'];
    if (!in_array($source, $validSources)) {
        $source = 'manual'; // Safe fallback
    }
    
    // Save decision
    $this->repository->update($id, [
        'match_status' => $payload['match_status'],
        'decision_source' => $source,  // ← NEW
        'decision_date' => date('Y-m-d H:i:s'),
        'decided_by' => $this->getCurrentUser()
    ]);
    
    // Timeline logging (enhanced)
    $this->timelineService->logDecision($id, $payload['match_status'], $source);
    
    return ['success' => true];
}
```

**File:** `app/Services/TimelineEventService.php`

```php
public function logDecision(int $recordId, string $decision, string $source = 'manual') {
    $snapshot = $this->getRecordSnapshot($recordId);
    
    // Add source to snapshot
    $snapshot['decision_source'] = $source;
    
    $this->createEvent([
        'record_id' => $recordId,
        'event_type' => 'decision_made',
        'new_value' => $decision,
        'source' => $source,  // ← Track in event too
        'snapshot' => json_encode($snapshot)
    ]);
}
```

### Testing

**Unit Tests:**
```php
class DecisionSourceTest extends TestCase {
    public function test_saves_ai_quick_source() {
        $result = $this->controller->saveDecision(123, [
            'match_status' => 'approved',
            'source' => 'ai_quick'
        ]);
        
        $record = $this->repository->find(123);
        $this->assertEquals('ai_quick', $record->decision_source);
    }
    
    public function test_defaults_to_manual() {
        $result = $this->controller->saveDecision(124, [
            'match_status' => 'approved'
            // No source provided
        ]);
        
        $record = $this->repository->find(124);
        $this->assertEquals('manual', $record->decision_source);
    }
    
    public function test_rejects_invalid_source() {
        $result = $this->controller->saveDecision(125, [
            'match_status' => 'approved',
            'source' => 'invalid_value'
        ]);
        
        $record = $this->repository->find(125);
        $this->assertEquals('manual', $record->decision_source); // Fallback
    }
}
```

### Deployment

```bash
# 1. Deploy code (feature flag OFF)
git push production main

# 2. Verify deployment
curl https://app/api/health
# Expected: 200 OK

# 3. Run smoke tests
php artisan test --filter=DecisionSourceTest

# 4. Enable for internal users only
FeatureFlags::enableForUsers('QUICK_DECISION', ['user1', 'user2']);
```

### Success Criteria

- [ ] All tests pass
- [ ] Code deployed without errors
- [ ] Feature flag works
- [ ] Internal users can use feature
- [ ] decision_source is saved correctly
- [ ] No errors in logs

### Rollback

```php
// Disable feature flag
FeatureFlags::disable('QUICK_DECISION');

// Or: rollback code deployment
git revert HEAD
git push production main
```

**Downtime:** None  
**Data Loss:** None (column persists, just not written to)

---

## Phase 3: Frontend Integration

### Objective
Update DesignLab to send `source` parameter

### Duration
**1 day**

### Code Changes

**File:** `design-lab/assets/js/ai-first.js`

```javascript
// Already implemented in DesignLab!
async function quickApprove() {
    const result = await fetch('/api/decisions/save.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            record_id: recordId,
            match_status: aiRecommendation.decision,
            source: 'ai_quick'  // ← Sends source
        })
    });
    
    // Handle response...
}
```

**This is already done in DesignLab experiment!**

### Success Criteria

- [ ] AI-First experiment sends correct `source`
- [ ] Manual decisions send `source: 'manual'`
- [ ] Backend receives and saves correctly

### Rollback

None needed (frontend change is transparent)

---

## Phase 4: Monitoring & Validation

### Objective
Monitor the feature in production

### Duration
**1 week** (before full rollout)

### Metrics Dashboard

```sql
-- Daily decision source distribution
SELECT 
    DATE(decision_date) as date,
    decision_source,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY DATE(decision_date)), 2) as percentage
FROM imported_records
WHERE decision_date >= DATE('now', '-7 days')
GROUP BY DATE(decision_date), decision_source
ORDER BY date DESC, count DESC;
```

**Expected Results:**
```
date       | decision_source | count | percentage
-----------|-----------------|-------|----------
2025-12-28 | ai_quick        | 210   | 70%
2025-12-28 | ai_manual       | 45    | 15%
2025-12-28 | manual          | 45    | 15%
```

### Quality Checks

```sql
-- Check for NULL values (shouldn't exist)
SELECT COUNT(*) FROM imported_records 
WHERE decision_source IS NULL;
-- Expected: 0

-- Check for invalid values
SELECT DISTINCT decision_source 
FROM imported_records
WHERE decision_source NOT IN ('ai_quick', 'ai_manual', 'manual');
-- Expected: empty result

-- Verify old records have default
SELECT COUNT(*) FROM imported_records 
WHERE decision_date < '2025-12-21' 
AND decision_source = 'manual';
-- Expected: all old records
```

### Alerts

Set up alerts for:
- ⚠️ NULL values in decision_source
- ⚠️ Invalid values in decision_source
- 📊 ai_quick usage < 50% (adoption issue)
- 📊 ai_quick usage > 95% (verify it's working)

### Success Criteria

- [ ] No NULL values
- [ ] No invalid values
- [ ] AI adoption 60-80%
- [ ] No user complaints
- [ ] No performance issues

---

## Phase 5: Full Rollout

### Objective
Enable for all users

### Duration
**3 days** (gradual)

### Rollout Plan

**Day 1:** 25% of users
```php
FeatureFlags::enableForPercentage('QUICK_DECISION', 25);
```

**Day 2:** 50% of users
```php
FeatureFlags::enableForPercentage('QUICK_DECISION', 50);
```

**Day 3:** 100% of users
```php
FeatureFlags::enable('QUICK_DECISION');
```

### Monitoring (intensified)

Check every 4 hours:
- Error rate
- Response time
- decision_source distribution
- User feedback

### Success Criteria

- [ ] Error rate < 0.5%
- [ ] No performance degradation
- [ ] User satisfaction > 8/10
- [ ] AI adoption stable 60-80%

### Rollback Trigger

If ANY of these occur:
- Error rate > 1%
- Multiple user complaints (>3)
- Performance degradation > 20%
- Data inconsistencies

**Action:**
```php
FeatureFlags::disable('QUICK_DECISION');
```

---

## Phase 6: Stabilization

### Objective
Run for 2 weeks without issues

### Duration
**2 weeks**

### Activities

- Monitor metrics daily
- Collect user feedback
- Verify data quality
- Check for edge cases

### Success Criteria

- [ ] 14 days without incident
- [ ] Metrics stable
- [ ] No data issues
- [ ] No performance issues
- [ ] User feedback positive

---

## Phase 7: Cleanup (Optional)

### Objective
Remove feature flag (make permanent)

### Duration
**1 hour** (after Phase 6 success)

### Actions

```php
// Remove feature flag checks
// Before:
if (FeatureFlags::isEnabled('QUICK_DECISION')) {
    $handler = new QuickDecisionHandler();
    return $handler->handle($id, $payload);
}

// After:
$handler = new QuickDecisionHandler();
return $handler->handle($id, $payload);
```

**Only do this after 2+ weeks of stable operation**

---

## Timeline Summary

| Phase | Duration | Cumulative |
|-------|----------|-----------|
| 1. Schema Addition | 30 min | 30 min |
| 2. Backend Integration | 2 days | 2.5 days |
| 3. Frontend Integration | Already done | 2.5 days |
| 4. Monitoring | 1 week | 9.5 days |
| 5. Full Rollout | 3 days | 12.5 days |
| 6. Stabilization | 2 weeks | 26.5 days |
| 7. Cleanup | 1 hour | ~27 days |

**Total Duration:** ~1 month (safe, gradual)

---

## Safety Guardrails

### Before Each Phase

- [ ] Previous phase successful
- [ ] Metrics look good
- [ ] No blockers
- [ ] Team ready
- [ ] Backup taken

### During Each Phase

- [ ] Monitor continuously
- [ ] Log everything
- [ ] Be ready to rollback
- [ ] Communicate status

### After Each Phase

- [ ] Verify success criteria
- [ ] Document any issues
- [ ] Update stakeholders
- [ ] Plan next phase

---

## Rollback Procedures

### Phase 1 Rollback (Schema)
```sql
ALTER TABLE imported_records DROP COLUMN decision_source;
```
**Complexity:** Very Low  
**Data Loss:** None

### Phase 2-5 Rollback (Code/Feature)
```php
FeatureFlags::disable('QUICK_DECISION');
```
**Complexity:** Instant  
**Data Loss:** None (column remains, just not used)

### Full Rollback (Nuclear Option)
```sql
-- 1. Disable feature
FeatureFlags::disable('QUICK_DECISION');

-- 2. Revert code
git revert <commit_hash>

-- 3. Drop column (optional)
ALTER TABLE imported_records DROP COLUMN decision_source;
```

---

## Communication Plan

### Stakeholders to Notify

- [ ] Development team
- [ ] QA team
- [ ] Database admin
- [ ] End users (via release notes)

### Updates

**Before Phase 1:**
- "Starting schema migration for decision tracking"

**After Phase 2:**
- "Backend ready, testing with internal users"

**Before Phase 5:**
- "Rolling out to all users over 3 days"

**After Phase 6:**
- "Feature stable, collecting metrics"

---

## Success Definition

**Migration is successful if:**

1. ✅ Column exists and works
2. ✅ Data quality 100% (no NULLs, no invalids)
3. ✅ Feature adopted 60-80%
4. ✅ No performance impact
5. ✅ No data loss
6. ✅ Rollback tested and ready
7. ✅ Team confident in stability

---

**Status:** Ready for Execution

**Next:** Review with team → Get approval → Begin Phase 1
