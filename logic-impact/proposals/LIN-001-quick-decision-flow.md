# Logic Impact Note

**ID:** LIN-001  
**Related Design Finding:** DF-001  
**Date:** 2025-12-21  
**Analyst:** Bakheet

## Current Logic (As-Is)

### Decision Flow

```
User Opens Page
    ↓
Display 4 Decision Options (always visible)
    ↓
User Clicks One Option
    ↓
Show Confirmation Modal
    ↓
User Confirms
    ↓
POST /api/decisions/validate.php
    ↓
POST /api/decisions/save.php
    ↓
Redirect to Next Record
```

**Key Dependencies:**
- Decision requires manual selection from 4 options
- Confirmation modal is mandatory
- Validation is separate from save (2 API calls)
- No distinction between AI-suggested vs manual decisions

### Current Code Structure

**Frontend (decision.js):**
```javascript
// يعرض كل الخيارات دائماً
function showDecisionCards() {
    // 4 cards always visible
}

// يفتح modal للتأكيد
function confirmDecision(decision) {
    showConfirmationModal(decision);
}

// نقرتان على الأقل
function saveDecision() {
    validate() → save() → redirect()
}
```

**Backend (DecisionController.php):**
```php
// endpoint منفصل للـ validation
public function validateDecision($id, $data) {
    // validate fields
    // return validation result
}

// endpoint منفصل للـ save
public function saveDecision($id, $data) {
    // expects: supplier_id, bank_id, match_status
    // no tracking of decision source (AI vs manual)
}
```

## Design Requirement (To-Be)

### Desired Flow

```
User Opens Page
    ↓
AI Recommendation (HERO) ← 95% confidence shown
    ↓
    ├─→ Quick Approve (1 click) ─→ Save & Done (15s)
    │
    └─→ Manual Mode Toggle
            ↓
        Show 4 Options (collapsed by default)
            ↓
        User Selects
            ↓
        Save (no extra confirmation for high-confidence AI)
```

**What Changes:**
- Decision can be implicit (pre-selected by AI)
- Confirmation can be conditional (skip for high confidence)
- Validation must be inline with save (one call)
- Track decision source (ai_quick, ai_manual, fully_manual)

## Required Backend Changes

### Change 1: Merge Validation & Save

**Current:**
```php
POST /api/decisions/validate.php → {valid: true/false}
POST /api/decisions/save.php → {success: true/false}
```

**Required:**
```php
POST /api/decisions/save.php
  - Validation happens internally
  - Returns success/error in one round-trip
  - Accepts optional 'source' parameter
```

**Impact:** Type A (Logic Addition - backward compatible)

**Implementation:**
```php
// في DecisionController.php
public function saveDecision(int $id, array $payload) {
    // Validate inline
    $errors = $this->validate($payload);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Save with source tracking
    $source = $payload['source'] ?? 'manual'; // ai_quick, ai_manual, manual
    
    // Existing save logic...
    $this->recordRepository->update($id, [
        'supplier_id' => $payload['supplier_id'],
        'bank_id' => $payload['bank_id'],
        'match_status' => $payload['match_status'],
        'decision_source' => $source, // NEW field
    ]);
    
    // Existing timeline logic...
    
    return ['success' => true];
}
```

### Change 2: Support Pre-filled Decisions (AI Source)

**Current:**
```php
// Backend يتوقع user_selected = true
if (!isset($_POST['user_selected'])) {
    return error("Decision must be manually selected");
}
```

**Required:**
```php
// Accept AI suggestions
$source = $payload['source'] ?? 'manual';

if ($source === 'ai_quick') {
    // Validate AI suggestion is still valid
    $aiRec = AIEngine::getRecommendation($id);
    
    if ($aiRec['confidence'] < 0.8) {
        return error("AI confidence too low for quick approve");
    }
    
    // Use AI's suggestion
    $payload['supplier_id'] = $aiRec['supplier_id'];
    $payload['bank_id'] = $aiRec['bank_id'];
}

// Proceed with save
```

**Impact:** Type A (Feature Addition - no breaking change)

### Change 3: Optional Confirmation (Feature Flag)

**Current:**
```php
// Confirmation دائماً required
if (!$payload['confirmed']) {
    return ['status' => 'needs_confirmation'];
}
```

**Required:**
```php
// Confirmation optional based on confidence + source
$requiresConfirmation = true;

if (FEATURE_QUICK_DECISION_ENABLED) {
    if ($source === 'ai_quick' && $aiConfidence >= 0.9) {
        $requiresConfirmation = false;
    }
}

if ($requiresConfirmation && !$payload['confirmed']) {
    return ['status' => 'needs_confirmation'];
}

// Proceed
```

**Impact:** Type A (Feature Flag - zero breaking change)

### Change 4: Add Decision Source Field (Optional DB Change)

**Current Schema:**
```sql
imported_records (
    ...
    match_status TEXT
)
```

**Proposed (Optional):**
```sql
ALTER TABLE imported_records 
ADD COLUMN decision_source TEXT DEFAULT 'manual';
-- Values: 'ai_quick', 'ai_manual', 'manual'
```

**Impact:** Type A (Schema addition - backward compatible)

**Benefits:**
- Track which decisions came from AI
- Measure AI adoption rate
- Validate AI accuracy over time

## Impact Classification

| Area | Level | Reason |
|------|-------|--------|
| Database | **None** | Schema change is optional |
| API | **Low** | Adds parameters, doesn't remove |
| Business Logic | **Low** | Adds AI path, keeps manual path |
| Security | **None** | Same validation rules apply |
| Performance | **Positive** | Fewer API calls (1 vs 2) |

**Overall Risk:** **LOW**

## Edge Cases to Handle

### 1. AI Suggestion is Wrong/Outdated

**Scenario:** User clicks "Quick Approve" but data changed since AI analyzed

**Solution:**
```php
// Re-validate AI suggestion at save time
$currentRecord = $this->getRecord($id);
$aiRec = AIEngine::getRecommendation($id);

if (!$this->isAIRecommendationStillValid($currentRecord, $aiRec)) {
    return error("Record changed - AI recommendation outdated. Please review manually.");
}
```

### 2. Backend Unavailable / AI Engine Down

**Scenario:** AI service temporarily unavailable

**Solution:**
```php
// Graceful fallback in LabDataAccess
public function getAIRecommendation($recordId) {
    try {
        return AIEngine::analyze($recordId);
    } catch (Exception $e) {
        // Fallback: no recommendation
        return [
            'decision' => null,
            'confidence' => 0,
            'reasons' => [],
            'error' => 'AI temporarily unavailable - decide manually'
        ];
    }
}
```

Frontend auto-hides AI Hero if confidence < 0.7

### 3. Low Confidence Cases

**Scenario:** AI confidence is 60% - not clear enough for quick approve

**Solution:**
- Don't show "Quick Approve" button if confidence < 80%
- Only show "Choose Manually" option
- AI Recommendation becomes informative, not actionable

### 4. Validation Fails After Quick Approve

**Scenario:** User clicks quick approve, but validation fails (rare)

**Solution:**
```javascript
// في ai-first.js
async quickApprove() {
    try {
        const result = await saveDecision({source: 'ai_quick'});
        
        if (!result.success) {
            alert('❌ خطأ: ' + result.error + '\n\nيرجى المراجعة اليدوية');
            this.toggleManualMode(); // Auto-open manual cards
        } else {
            // Success
        }
    } catch (e) {
        // Network error
        alert('خطأ في الاتصال - حاول مرة أخرى');
    }
}
```

## Dependencies

**على AIEngine (موجود):**
- `AIEngine::getRecommendation($recordId)` يجب أن يعيد:
  ```php
  [
      'supplier_id' => int,
      'bank_id' => int,
      'confidence' => float (0-1),
      'reasons' => array,
  ]
  ```

**على Timeline (موجود):**
- نفس منطق تسجيل الأحداث
- إضافة حقل `decision_source` في الـ snapshot

**None otherwise** - التغييرات محلية في DecisionController

## Testing Requirements

### Unit Tests

- [ ] `test_save_decision_with_ai_quick_source()`
- [ ] `test_save_decision_with_ai_manual_source()`
- [ ] `test_save_decision_validation_inline()`
- [ ] `test_ai_recommendation_outdated_rejected()`
- [ ] `test_feature_flag_disabled_requires_confirmation()`

### Integration Tests

- [ ] End-to-end: Quick Approve flow
- [ ] End-to-end: Manual flow (unchanged)
- [ ] AI Engine failure → graceful fallback
- [ ] Confidence < 80% → no quick approve button

### Regression Tests

- [ ] Existing manual decision flow still works
- [ ] Timeline events still logged correctly
- [ ] Learning system still receives data

### Performance Tests

- [ ] Single API call < 200ms (vs 2 calls @ 150ms each)
- [ ] No N+1 queries introduced

## Rollback Plan

### Feature Flag

```php
// في config/features.php
define('FEATURE_QUICK_DECISION', false); // ← Instant rollback
```

Falls back to current flow immediately.

### Database Rollback (if decision_source added)

```sql
-- Optional - doesn't affect existing logic
ALTER TABLE imported_records DROP COLUMN decision_source;
```

### Frontend Rollback

Simply redirect `/lab/experiments/ai-first` to 404 or show "experiment ended"

## Migration Path

### Phase 1: Backend Changes (Backward Compatible)

1. Add optional `source` parameter to `saveDecision()`
2. Merge validation logic inline
3. Add feature flag (default: OFF)

**Timeline:** 2-3 hours  
**Risk:** Zero (backward compatible)

### Phase 2: Frontend Integration

1. Update decision.js to support quick approve
2. Add new UI components from DesignLab
3. Toggle feature flag ON for test users

**Timeline:** 4-5 hours  
**Risk:** Low (behind feature flag)

### Phase 3: Monitoring & Rollout

1. Monitor metrics for 48 hours
2. Compare: time, clicks, errors
3. Full rollout if metrics meet targets

**Timeline:** 2 days monitoring  
**Risk:** Low (can rollback instantly)

---

**Status:** Analyzed  
**Risk:** **LOW**  
**Estimated Effort:** 6-8 hours development + 2 days testing  
**Next:** Awaiting Decision Record → DR-001
