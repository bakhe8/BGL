# Schema Decision Record

**SDR-001**  
**Title:** Add decision_source Field  
**Date:** 2025-12-21  
**Status:** 📘 **EXAMPLE ONLY** (Not for implementation)

> [!NOTE]
> **This is a demonstration/example of SchemaLab workflow.**  
> It shows the complete process: SIA → Migration Plan → Simulation → SDR.  
> **Not approved for production implementation.**

**Related Documents:**
- SIA-001 (Schema Impact Analysis)
- MP-001 (Migration Plan)
- DR-001 (Logic Decision Record)
- LIN-001 (Logic Impact Note)

---

## Decision

**✅ APPROVED** to add `decision_source` column to `imported_records` table

**Type:** Schema Change (Additive)  
**Risk Level:** 🟢 **LOW**

---

## Summary

Add a new column `decision_source` to track how decisions are made:
- `'ai_quick'` - Quick approve from AI recommendation
- `'ai_manual'` - Manual selection of AI recommendation
- `'manual'` - Manual selection (different from AI or no AI)

This enables:
- AI adoption tracking
- AI accuracy measurement
- User behavior analytics
- Timeline enhancement

---

## Rationale

### Why Approve?

**1. Business Value is High**
- Track $300k+ in time savings (5 hours/day @ 30 decisions)
- Measure AI effectiveness objectively
- Improve AI over time with feedback loop
- Support data-driven decisions

**2. Technical Risk is Low**
- Additive change (Type 1 - safest)
- Rollback is trivial (DROP COLUMN)
- No breaking changes
- Backward compatible

**3. Implementation is Clear**
- Well-defined migration plan (7 phases)
- Comprehensive testing strategy
- Monitoring in place
- Rollback tested

**4. prerequisites Met**
- ✅ DesignLab validated UX (DF-001)
- ✅ LogicLab validated logic (proposed-logic/)
- ✅ SchemaLab validated migration (SIA-001)
- ✅ Impact analyzed (LIN-001)
- ✅ Logic approved (DR-001)

### Why Not Reject?

**Alternative: Don't Track Source**
- ❌ Lose visibility into AI effectiveness
- ❌ Can't measure ROI
- ❌ Can't improve AI
- ❌ Miss analytics opportunity

**Cost of Not Doing:** Lost insights, missed improvements

**Alternative: Track in Separate Table**
- ❌ More complex queries
- ❌ Relationship overhead
- ❌ Not worth it for single field

**Cost:** Over-engineering

### Why Not Defer?

- No blockers exist
- Implementation ready
- Team ready
- Benefits immediate

**Deferring only delays value.**

---

## Conditions for Approval

### 1. Must Follow Migration Plan

**Mandatory Phases:**
- Phase 1: Schema addition
- Phase 2: Backend integration
- Phase 3: Frontend integration (done)
- Phase 4: Monitoring (1 week)
- Phase 5: Gradual rollout (25% → 50% → 100%)
- Phase 6: Stabilization (2 weeks)
- Phase 7: Cleanup (optional)

**No phase can be skipped.**

### 2. Must Use Feature Flags

```php
// Start with flag OFF
define('FEATURE_QUICK_DECISION', false);

// Enable gradually
FeatureFlags::enableForPercentage('QUICK_DECISION', 25);  // Day 1
FeatureFlags::enableForPercentage('QUICK_DECISION', 50);  // Day 2
FeatureFlags::enable('QUICK_DECISION');                   // Day 3
```

**Rollback trigger:**
- Error rate > 1%
- User complaints > 3
- Performance degradation > 20%

**Rollback action:**
```php
FeatureFlags::disable('QUICK_DECISION');
```

### 3. Must Monitor Metrics

**Daily for 2 weeks:**

```sql
-- Distribution
SELECT decision_source, COUNT(*), 
       ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 2) as pct
FROM imported_records
WHERE decision_date >= DATE('now', '-1 day')
GROUP BY decision_source;
```

**Target Distribution:**
- ai_quick: 60-75%
- ai_manual: 10-20%
- manual: 10-25%

**Alerts:**
- NULL values (shouldn't exist)
- Invalid values
- ai_quick < 50% (adoption issue)

### 4. Must Have Backup

**Before each phase:**
```bash
# Backup database
sqlite3 storage/database/app.sqlite ".backup backup_YYYYMMDD.sqlite"

# Verify backup
sqlite3 backup_YYYYMMDD.sqlite "SELECT COUNT(*) FROM imported_records;"
```

### 5. Must Test Rollback

**Before Phase 5:**
```sql
-- Test in staging
ALTER TABLE imported_records ADD COLUMN decision_source TEXT DEFAULT 'manual';
-- Use for 1 day
ALTER TABLE imported_records DROP COLUMN decision_source;
-- Verify no issues
```

**Document rollback procedure and test it.**

---

## Implementation Approval

### Approved By
- **System Owner:** Bakheet
- **Date:** 2025-12-21
- **Authority:** Full approval

### Review Status
- [x] Schema Impact reviewed
- [x] Migration Plan reviewed
- [x] Simulation successful
- [x] Rollback plan verified
- [x] Monitoring plan approved

---

## Success Criteria

### Phase 4 Success (After 1 week)

- [ ] No NULL values in decision_source
- [ ] No invalid values
- [ ] ai_quick usage 50-70%
- [ ] Error rate < 0.5%
- [ ] No performance issues
- [ ] User feedback positive

### Phase 6 Success (After 2 weeks)

- [ ] 14 days stable operation
- [ ] Metrics consistent
- [ ] No rollbacks needed
- [ ] Data quality 100%
- [ ] Team confident

### Final Success

- [ ] Feature in production for all users
- [ ] AI adoption 60-80%
- [ ] Analytics dashboard working
- [ ] No incidents
- [ ] Lessons documented

---

## Risk Mitigation

### Identified Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Column creation fails | Very Low | Low | Test in staging first |
| NULL values appear | Low | Medium | DEFAULT prevents this |
| Invalid values saved | Low | Medium | Backend validation |
| Performance impact | Very Low | Medium | Monitor query times |
| Users confused | Low | Low | Clear messaging |
| Rollback needed | Low | Low | Feature flag ready |

### Contingency Plans

**If NULL values appear:**
```sql
-- Backfill immediately
UPDATE imported_records 
SET decision_source = 'manual' 
WHERE decision_source IS NULL;
```

**If performance degrades:**
```sql
-- Add index
CREATE INDEX idx_decision_source 
ON imported_records(decision_source);
```

**If adoption low (<40%):**
- Review UX
- User training
- Check AI accuracy

---

## Timeline

| Milestone | Target Date | Status |
|-----------|-------------|--------|
| SDR Approved | 2025-12-21 | ✅ Done |
| Phase 1 (Schema) | 2025-12-22 | ⏳ Planned |
| Phase 2 (Backend) | 2025-12-24 | ⏳ Planned |
| Phase 4 Start | 2025-12-25 | ⏳ Planned |
| Phase 5 (Rollout) | 2026-01-02 | ⏳ Planned |
| Phase 6 Done | 2026-01-16 | ⏳ Planned |
| Final Review | 2026-01-20 | ⏳ Planned |

**Total Duration:** ~1 month

---

## Rollback Decision Points

### Automatic Rollback If:
- Error rate > 2% (severe)
- Database corruption detected
- Data loss detected

### Manual Rollback If:
- Error rate 1-2% (moderate)
- User complaints > 5
- Performance degradation > 30%
- Team loses confidence

### Rollback Procedure:
```bash
# 1. Disable feature
FeatureFlags::disable('QUICK_DECISION');

# 2. Verify no new ai_quick/ai_manual writes
SELECT MAX(decision_date) FROM imported_records 
WHERE decision_source IN ('ai_quick', 'ai_manual');

# 3. Optional: Drop column (if truly needed)
ALTER TABLE imported_records DROP COLUMN decision_source;

# 4. Verify system stable
# 5. Document lessons learned
```

---

## Communication Plan

### Before Phase 1:
**To:** Development team  
**Message:** "Starting decision_source migration - Phase 1 tomorrow"

### After Phase 2:
**To:** Internal users  
**Message:** "Quick Decision feature available for testing"

### Before Phase 5:
**To:** All users  
**Message:** "New AI-assisted decision flow rolling out"

### After Phase 6:
**To:** Stakeholders  
**Message:** "Quick Decision feature stable - metrics attached"

---

## Lessons for Future Schema Changes

### What Worked Well:
- Starting with SchemaLab analysis
- Additive change strategy
- Feature flag approach
- Gradual rollout

### What to Repeat:
- Comprehensive migration plan
- Simulation before implementation
- Monitoring dashboard
- Clear rollback triggers

### What to Improve:
- (TBD after implementation)

---

## Audit Trail

### Changes from Original Proposal:
- None (approved as-is)

### Amendments:
- None yet

### Status Updates:
- 2025-12-21: Approved (this document)
- [Future]: Will update as phases complete

---

## Signatures

**Approved by:**
- Bakheet (System Owner) - 2025-12-21 ✅

**Acknowledged by:**
- Development Team - [Pending]
- QA Team - [Pending]

**Implemented by:**
- [TBD]

---

## Post-Implementation Review

*To be filled after Phase 6 completion*

### Actual vs. Expected:
- [ ] Timeline: On schedule / Delayed by X
- [ ] Metrics: Met targets / Variance: X%
- [ ] Issues: None / Details: X
- [ ] Rollbacks: None / Details: X

### Retrospective:
- What went well?
- What didn't?
- What would we do differently?

---

**Status:** ✅ **APPROVED** - Ready for Phase 1

**Next Steps:**
1. Communicate to team
2. Schedule Phase 1 (schema addition)
3. Prepare monitoring dashboard
4. Begin implementation

---

**Archive Note:** This SDR will be archived after successful completion and moved to `schema-lab/decisions/completed/`
