# Backend Impact Analysis

**Purpose:** Translate LogicLab thinking into engineering requirements  
**Scope:** What needs to change in the backend to support the proposed logic  
**Status:** Pre-implementation analysis

---

## Executive Summary

### What Changes?
- **API:** 1 modified endpoint, 1 optional field
- **Database:** 1 optional column
- **Business Logic:** Add AI path, keep manual path
- **Risk:** **LOW** (backward compatible)

### What Stays?
- Current manual flow (unchanged)
- Validation rules (same)
- Timeline logging (enhanced)
- Error handling (preserved)

---

## Required Backend Changes

### Change 1: Merge Validation with Save

**Current Structure:**
```php
// Two separate endpoints
POST /api/decisions/validate.php
  ↓ {valid: true/false}
POST /api/decisions/save.php
  ↓ {success: true/false}
```

**Proposed Structure:**
```php
// One endpoint with inline validation
POST /api/decisions/save.php
  ↓ validates internally
  ↓ {success: true/false, errors: [...]}
```

**Implementation:**

```php
// في DecisionController.php
public function saveDecision(int $id, array $payload) {
    // Validation inline
    $errors = $this->validateDecision($id, $payload);
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'errors' => $errors,
            'code' => 'VALIDATION_FAILED'
        ];
    }
    
    // Proceed with save
    $result = $this->repository->update($id, $payload);
    
    return [
        'success' => true,
        'message' => 'تم الحفظ بنجاح'
    ];
}

private function validateDecision(int $id, array $payload) {
    // نفس القواعد الحالية
    $errors = [];
    
    if (empty($payload['supplier_id'])) {
        $errors[] = 'Supplier required';
    }
    
    if (empty($payload['bank_id'])) {
        $errors[] = 'Bank required';
    }
    
    // ... باقي القواعد
    
    return $errors;
}
```

**Impact:**
- **Type:** Addition (backward compatible)
- **Risk:** Low
- **Benefit:** -50% API calls, -40% latency

---

### Change 2: Accept AI-Driven Decisions

**Current Logic:**
```php
// يتوقع user_selected = true دائماً
if (!isset($payload['confirmed']) || !$payload['confirmed']) {
    return error("Decision must be confirmed");
}
```

**Proposed Logic:**
```php
// Support multiple sources
$source = $payload['source'] ?? 'manual';

if ($source === 'ai_quick') {
    // Validate AI suggestion is still valid
    $aiRec = AIEngine::getRecommendation($id);
    
    if ($aiRec['confidence'] < 0.9) {
        return error("AI confidence too low for quick approve");
    }
    
    // Use AI's suggestion
    $payload['supplier_id'] = $aiRec['supplier_id'];
    $payload['bank_id'] = $aiRec['bank_id'];
    $payload['match_status'] = $aiRec['decision'];
    
} else if ($source === 'ai_manual') {
    // User selected AI's suggestion manually
    // (exists confirmation in manual mode)
    
} else {
    // source === 'manual'
    // Traditional flow
    if (!$payload['confirmed']) {
        return error("Manual decisions require confirmation");
    }
}

// Proceed with save
```

**Implementation:**

```php
class QuickDecisionHandler {
    
    public function handle(int $recordId, array $payload) {
        $source = $payload['source'] ?? 'manual';
        
        switch ($source) {
            case 'ai_quick':
                return $this->handleAIQuick($recordId, $payload);
            
            case 'ai_manual':
                return $this->handleAIManual($recordId, $payload);
            
            case 'manual':
            default:
                return $this->handleManual($recordId, $payload);
        }
    }
    
    private function handleAIQuick(int $recordId, array $payload) {
        // Re-validate AI suggestion
        $record = $this->repository->find($recordId);
        $aiRec = AIEngine::getRecommendation($recordId);
        
        // Check staleness
        if (!$this->isAIRecommendationFresh($record, $aiRec)) {
            throw new StaleRecommendationException(
                "Record changed - AI recommendation outdated"
            );
        }
        
        // Check confidence
        if ($aiRec['confidence'] < 0.9) {
            throw new LowConfidenceException(
                "AI confidence too low for quick approve"
            );
        }
        
        // Merge AI data
        $finalPayload = array_merge($payload, [
            'supplier_id' => $aiRec['supplier_id'],
            'bank_id' => $aiRec['bank_id'],
            'match_status' => $aiRec['decision'],
            'decision_source' => 'ai_quick'
        ]);
        
        return $this->save($recordId, $finalPayload);
    }
    
    private function handleManual(int $recordId, array $payload) {
        // Traditional validation
        if (!isset($payload['confirmed'])) {
            throw new ConfirmationRequiredException();
        }
        
        $finalPayload = array_merge($payload, [
            'decision_source' => 'manual'
        ]);
        
        return $this->save($recordId, $finalPayload);
    }
}
```

**Impact:**
- **Type:** Feature addition
- **Risk:** Low (existing flow unchanged)
- **Benefit:** Enables quick approve

---

### Change 3: Optional Decision Source Tracking

**Current Schema:**
```sql
imported_records (
    id INTEGER,
    supplier_id INTEGER,
    bank_id INTEGER,
    match_status TEXT,
    ...
)
```

**Proposed Schema (Optional):**
```sql
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';

-- Values: 'ai_quick', 'ai_manual', 'manual'
```

**Benefits:**
- Track AI adoption rate
- Measure AI accuracy over time
- Analyze user behavior
- Improve AI with feedback

**Migration:**
```sql
-- Safe migration (zero downtime)
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';

-- Backfill existing records (optional)
UPDATE imported_records 
SET decision_source = 'manual' 
WHERE decision_source IS NULL;
```

**Impact:**
- **Type:** Schema addition (optional)
- **Risk:** None (nullable, has default)
- **Rollback:** Simple (ignore column)

---

### Change 4: Feature Flag System

**Implementation:**

```php
// config/features.php
class FeatureFlags {
    
    const QUICK_DECISION = 'quick_decision';
    
    private static $flags = [
        self::QUICK_DECISION => false  // Start disabled
    ];
    
    public static function isEnabled(string $flag): bool {
        return self::$flags[$flag] ?? false;
    }
    
    public static function enable(string $flag): void {
        self::$flags[$flag] = true;
    }
    
    public static function disable(string $flag): void {
        self::$flags[$flag] = false;
    }
}
```

**Usage in Controller:**

```php
public function saveDecision(int $id, array $payload) {
    if (FeatureFlags::isEnabled(FeatureFlags::QUICK_DECISION)) {
        // Use new flow
        $handler = new QuickDecisionHandler();
        return $handler->handle($id, $payload);
    }
    
    // Use legacy flow (unchanged)
    return $this->legacySaveDecision($id, $payload);
}
```

**Impact:**
- **Deployment:** Instant toggle
- **Rollback:** Set flag to false (<1 minute)
- **Testing:** A/B test capability

---

## API Changes Detail

### Modified Endpoint: `POST /api/decisions/save.php`

**Current Request:**
```json
{
  "record_id": 14002,
  "supplier_id": 123,
  "bank_id": 45,
  "match_status": "approved",
  "confirmed": true
}
```

**Proposed Request:**
```json
{
  "record_id": 14002,
  "supplier_id": 123,      // optional for ai_quick
  "bank_id": 45,           // optional for ai_quick
  "match_status": "approved",  // optional for ai_quick
  "source": "ai_quick",    // NEW: 'ai_quick' | 'ai_manual' | 'manual'
  "confirmed": false       // optional: required only for 'manual'
}
```

**Response (Enhanced):**
```json
{
  "success": true,
  "message": "تم الحفظ بنجاح",
  "decision_source": "ai_quick",
  "time_saved": 105  // seconds saved vs traditional flow
}
```

**Backward Compatibility:**
```json
// Old clients still work
{
  "record_id": 14002,
  "supplier_id": 123,
  "bank_id": 45,
  "match_status": "approved",
  "confirmed": true
  // No "source" → defaults to 'manual'
}
```

---

## Dependencies

### Existing Systems (Read-Only):

**AIEngine** (already exists):
```php
AIEngine::getRecommendation($recordId)
// Returns:
// [
//   'decision' => 'approve',
//   'confidence' => 0.95,
//   'supplier_id' => 123,
//   'bank_id' => 45,
//   'reasons' => [...]
// ]
```

**TimelineEventService** (already exists):
```php
TimelineEventService::logDecision($recordId, $decision, $source)
// Existing, just add $source parameter
```

### No New Dependencies:
- ✅ No new libraries
- ✅ No new services
- ✅ No external APIs

---

## Performance Impact

### Current Performance:
```
User Request
  ↓
Validate (150ms)
  ↓
Save (150ms)
  ↓
Total: 300ms + network overhead
```

### Proposed Performance:
```
User Request
  ↓
Save with inline validation (180ms)
  ↓
Total: 180ms

Improvement: -40% latency
```

### Database Impact:
- **Queries:** Same number
- **Writes:** Same number
- **Indexes:** No new indexes needed
- **Load:** No additional load

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| AI suggestion outdated | Medium | Low | Re-validate before save |
| Validation fails | Low | Low | Graceful error + fallback |
| Feature flag fails | Very Low | High | Default to legacy flow |
| Database migration | Very Low | Low | Column is optional |
| Performance regression | Very Low | Medium | Load testing before deploy |

**Overall Risk:** **LOW**

---

## Testing Requirements

### Unit Tests:

```php
// tests/QuickDecisionTest.php

class QuickDecisionTest extends TestCase {
    
    public function test_ai_quick_approve_with_high_confidence() {
        // Given: AI confidence = 95%
        // When: User clicks quick approve
        // Then: Decision saved with source='ai_quick'
    }
    
    public function test_ai_quick_fails_when_confidence_low() {
        // Given: AI confidence = 60%
        // When: Backend receives ai_quick request
        // Then: Error returned, manual mode suggested
    }
    
    public function test_ai_quick_detects_stale_recommendation() {
        // Given: Record changed after AI analysis
        // When: User clicks quick approve
        // Then: Error returned, refresh suggested
    }
    
    public function test_manual_flow_unchanged() {
        // Given: User chooses manual
        // When: User selects and confirms
        // Then: Works exactly as before
    }
    
    public function test_feature_flag_disabled_uses_legacy() {
        // Given: Feature flag = OFF
        // When: Any decision request
        // Then: Legacy flow used
    }
}
```

### Integration Tests:

- [ ] End-to-end quick approve
- [ ] End-to-end manual (verify unchanged)
- [ ] AI service down → fallback
- [ ] Validation errors handled
- [ ] Timeline logging works
- [ ] Metrics collected

### Performance Tests:

- [ ] Response time < 200ms (p95)
- [ ] No N+1 queries introduced
- [ ] Database load unchanged

---

## Rollback Plan

### Instant Rollback (< 1 minute):

```php
// In config/features.php
FeatureFlags::disable(FeatureFlags::QUICK_DECISION);
```

**Result:** All requests use legacy flow

### Data Rollback (if needed):

```sql
-- If decision_source column added
ALTER TABLE imported_records DROP COLUMN decision_source;

-- No data loss - column is optional
```

---

## Deployment Strategy

### Phase 1: Backend Deployment

1. Deploy code with feature flag **OFF**
2. Verify no regressions
3. Run sanity tests

### Phase 2: Controlled Rollout

1. Enable for 5 internal users
2. Monitor for 48 hours:
   - Error rate
   - Response time
   - User feedback
3. If metrics good → expand to 20 users

### Phase 3: Full Rollout

1. Enable for all users
2. Monitor for 1 week
3. If stable → remove flag, make permanent

---

## Monitoring & Metrics

### Metrics to Track:

```php
// Dashboard metrics
[
    'quick_approve_usage' => 85%,  // % using quick vs manual
    'avg_decision_time' => 18s,     // vs 120s before
    'api_latency_p95' => 180ms,     // vs 300ms before
    'error_rate' => 0.2%,           // vs 0.3% before
    'ai_accuracy' => 94%,           // decisions not overridden
]
```

### Alerts:

- 🚨 Error rate > 1%
- 🚨 Latency > 500ms (p95)
- 🚨 AI accuracy < 85%
- ⚠️ Quick approve usage < 50%

---

## Estimated Effort

| Task | Effort | Risk |
|------|--------|------|
| Merge validation logic | 2 hours | Low |
| Add feature flag | 1 hour | None |
| Implement QuickDecisionHandler | 3 hours | Low |
| Add decision_source field | 1 hour | None |
| Write tests | 2 hours | None |
| Documentation | 1 hour | None |

**Total:** 10 hours development
**Testing:** 8 hours
**Deployment:** 1 hour

**Overall:** 2-3 days

---

## Success Criteria

### Technical:
- [ ] All tests pass
- [ ] No regressions in legacy flow
- [ ] Feature flag works
- [ ] Rollback tested

### Performance:
- [ ] Latency < 200ms (p95)
- [ ] Error rate < 0.5%
- [ ] No database bottlenecks

### Business:
- [ ] Time to decision < 30s average
- [ ] User satisfaction > 8/10
- [ ] AI adoption > 70%

---

## Conclusion

**From LogicLab thinking to engineering reality:**

1. ✅ Changes are **well-defined**
2. ✅ Risk is **LOW**
3. ✅ Rollback is **instant**
4. ✅ Testing is **comprehensive**
5. ✅ Effort is **reasonable** (2-3 days)

**This analysis becomes the foundation for:**
- `logic-impact/proposals/LIN-001.md` (official documentation)
- `logic-impact/approved/DR-001.md` (decision)
- `backend/changes/` (implementation)

---

**Status:** ✅ Ready for formal Logic Impact Note
