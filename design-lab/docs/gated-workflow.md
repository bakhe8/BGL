# Gated Workflow System - نظام التحكم المتدرج

## المفهوم الأساسي

كل تصميم أو تغيير يمر بـ **4 مراحل إلزامية** قبل أن يصل للـ production:

```
1. Discovery     → DesignLab يكتشف
2. Assessment    → Logic Impact يقيّم  
3. Decision      → Decision Record يقرر
4. Execution     → Backend ينفذ (بعد الإذن فقط)
```

---

## 🔍 Stage 1: Discovery (الاكتشاف)

### الهدف
اكتشاف التغييرات المقترحة وتوثيقها بدقة.

### العملية

#### 1.1 Design Change Detection

```php
// design-lab/core/Discovery.php

class DiscoveryEngine {
    
    /**
     * كشف التغييرات بين تصميمين
     */
    public function detectChanges($experimentA, $experimentB) {
        $changes = [
            'layout_changes' => $this->compareLayouts($experimentA, $experimentB),
            'component_changes' => $this->compareComponents($experimentA, $experimentB),
            'interaction_changes' => $this->compareInteractions($experimentA, $experimentB),
            'data_flow_changes' => $this->compareDataFlow($experimentA, $experimentB),
        ];
        
        return new ChangeReport($changes);
    }
    
    /**
     * تحليل التأثير المحتمل
     */
    public function analyzePotentialImpact($changes) {
        return [
            'ui_impact' => $this->assessUIImpact($changes),
            'ux_impact' => $this->assessUXImpact($changes),
            'logic_impact' => $this->assessLogicImpact($changes),  // ← مهم
            'data_impact' => $this->assessDataImpact($changes),
            'performance_impact' => $this->assessPerformanceImpact($changes),
        ];
    }
}
```

#### 1.2 Change Documentation

```json
// design-lab/discoveries/2025-12-21-ai-hero.json
{
  "discovery_id": "DISC-001",
  "date": "2025-12-21",
  "title": "AI Hero Component",
  "description": "إضافة مكون AI Recommendation كبير في أعلى صفحة القرار",
  
  "changes_detected": {
    "new_components": ["AIHero"],
    "modified_components": ["DecisionBoard"],
    "removed_components": [],
    
    "layout_changes": {
      "before": "Timeline + Decision Board side-by-side",
      "after": "AI Hero on top, Decision Board below, Timeline collapsible"
    },
    
    "interaction_changes": {
      "new_interactions": ["Quick approve from AI suggestion"],
      "modified_interactions": ["Decision selection"],
      "removed_interactions": []
    }
  },
  
  "potential_impacts": {
    "ui": "Major - complete layout restructure",
    "ux": "High - changes primary user flow",
    "logic": "Medium - new AI integration point",
    "data": "Low - same data, different presentation",
    "performance": "Low - minimal additional load"
  },
  
  "status": "discovered",
  "next_stage": "assessment"
}
```

---

## ⚖️ Stage 2: Assessment (التقييم)

### الهدف
تقييم التأثير على المنطق والبيانات والسلوك.

### العملية

#### 2.1 Logic Impact Analyzer

```php
// design-lab/core/LogicImpactAnalyzer.php

class LogicImpactAnalyzer {
    
    /**
     * تحليل التأثير على المنطق
     */
    public function analyze($discoveryId) {
        $discovery = Discovery::load($discoveryId);
        
        $analysis = [
            'database_impact' => $this->analyzeDatabaseImpact($discovery),
            'api_impact' => $this->analyzeAPIImpact($discovery),
            'business_logic_impact' => $this->analyzeBusinessLogicImpact($discovery),
            'dependencies_impact' => $this->analyzeDependenciesImpact($discovery),
            'security_impact' => $this->analyzeSecurityImpact($discovery),
        ];
        
        return new LogicImpactReport($analysis);
    }
    
    /**
     * تحليل تأثير قاعدة البيانات
     */
    private function analyzeDatabaseImpact($discovery) {
        $newComponents = $discovery->getNewComponents();
        $modifiedComponents = $discovery->getModifiedComponents();
        
        $impact = [
            'new_tables_needed' => [],
            'schema_changes_needed' => [],
            'new_queries_needed' => [],
            'query_modifications_needed' => [],
            'migration_required' => false,
        ];
        
        // مثال: AI Hero يحتاج AI recommendations
        if (in_array('AIHero', $newComponents)) {
            $impact['new_queries_needed'][] = [
                'query' => 'SELECT ai_recommendation FROM ai_cache WHERE record_id = ?',
                'reason' => 'Fetch AI recommendation for hero display',
                'risk_level' => 'low',
            ];
        }
        
        return $impact;
    }
    
    /**
     * تحليل تأثير API
     */
    private function analyzeAPIImpact($discovery) {
        return [
            'new_endpoints_needed' => [],
            'endpoint_modifications_needed' => [],
            'breaking_changes' => [],
            'backward_compatible' => true,
        ];
    }
    
    /**
     * تحليل منطق الأعمال
     */
    private function analyzeBusinessLogicImpact($discovery) {
        return [
            'workflow_changes' => [],
            'validation_changes' => [],
            'permission_changes' => [],
            'risk_assessment' => 'low', // low, medium, high, critical
        ];
    }
    
    /**
     * تحديد المخاطر
     */
    public function assessRisk($analysis) {
        $riskFactors = [
            'database_changes' => count($analysis['database_impact']['schema_changes_needed']) > 0,
            'breaking_changes' => count($analysis['api_impact']['breaking_changes']) > 0,
            'critical_path_affected' => $this->affectsCriticalPath($analysis),
            'data_loss_risk' => $this->hasDataLossRisk($analysis),
        ];
        
        if ($riskFactors['data_loss_risk'] || $riskFactors['breaking_changes']) {
            return 'CRITICAL';
        } elseif ($riskFactors['critical_path_affected']) {
            return 'HIGH';
        } elseif ($riskFactors['database_changes']) {
            return 'MEDIUM';
        }
        return 'LOW';
    }
}
```

#### 2.2 Impact Report

```json
// design-lab/assessments/DISC-001-assessment.json
{
  "assessment_id": "ASSESS-001",
  "discovery_id": "DISC-001",
  "date": "2025-12-21",
  "title": "Logic Impact Assessment: AI Hero Component",
  
  "database_impact": {
    "new_tables_needed": [],
    "schema_changes_needed": [],
    "new_queries_needed": [
      {
        "query": "SELECT ai_recommendation, confidence FROM ai_cache WHERE record_id = ?",
        "reason": "Display AI recommendation in hero",
        "risk_level": "low"
      }
    ],
    "migration_required": false
  },
  
  "api_impact": {
    "new_endpoints_needed": [],
    "endpoint_modifications_needed": [],
    "breaking_changes": [],
    "backward_compatible": true
  },
  
  "business_logic_impact": {
    "workflow_changes": [
      "User can now approve directly from AI suggestion without seeing all options"
    ],
    "validation_changes": [],
    "permission_changes": [],
    "risk_assessment": "low"
  },
  
  "dependencies": {
    "new_dependencies": [],
    "modified_dependencies": ["AIEngine (existing)"],
    "removed_dependencies": []
  },
  
  "security_impact": {
    "authentication_changes": false,
    "authorization_changes": false,
    "data_exposure_risk": "none",
    "xss_risk": "low",
    "sql_injection_risk": "none"
  },
  
  "overall_risk": "LOW",
  "recommendation": "PROCEED_WITH_CAUTION",
  "required_actions": [
    "Ensure AI recommendation query is cached",
    "Add fallback for when AI is unavailable",
    "Test with users who prefer manual selection"
  ],
  
  "status": "assessed",
  "next_stage": "decision"
}
```

---

## 📋 Stage 3: Decision Record (القرار)

### الهدف
اتخاذ قرار موثق بناءً على البيانات.

### العملية

#### 3.1 Decision Framework

```php
// design-lab/core/DecisionRecord.php

class DecisionRecord {
    
    /**
     * إنشاء قرار بناءً على التقييم
     */
    public static function create($assessmentId) {
        $assessment = Assessment::load($assessmentId);
        
        $decision = [
            'decision_id' => 'DEC-' . date('YmdHis'),
            'assessment_id' => $assessmentId,
            'discovery_id' => $assessment->getDiscoveryId(),
            
            'context' => [
                'ux_metrics' => Metrics::getForExperiment($assessment->getExperimentName()),
                'user_feedback' => Feedback::getForExperiment($assessment->getExperimentName()),
                'risk_level' => $assessment->getRiskLevel(),
            ],
            
            'options' => [
                [
                    'option' => 'APPROVE_FULL',
                    'description' => 'Adopt the entire design as-is',
                    'pros' => [],
                    'cons' => [],
                ],
                [
                    'option' => 'APPROVE_PARTIAL',
                    'description' => 'Adopt specific components only',
                    'components' => [],
                ],
                [
                    'option' => 'APPROVE_MODIFIED',
                    'description' => 'Adopt with modifications',
                    'modifications' => [],
                ],
                [
                    'option' => 'REJECT',
                    'description' => 'Do not adopt, archive experiment',
                    'reason' => '',
                ],
                [
                    'option' => 'DEFER',
                    'description' => 'Need more testing/data',
                    'additional_tests' => [],
                ],
            ],
            
            'chosen_option' => null,  // To be filled
            'rationale' => null,      // To be filled
            'approved_by' => null,    // To be filled
            'approved_at' => null,    // To be filled
            
            'status' => 'pending_decision',
        ];
        
        return new DecisionRecord($decision);
    }
    
    /**
     * Approve decision
     */
    public function approve($option, $rationale, $approver) {
        $this->data['chosen_option'] = $option;
        $this->data['rationale'] = $rationale;
        $this->data['approved_by'] = $approver;
        $this->data['approved_at'] = date('Y-m-d H:i:s');
        $this->data['status'] = 'approved';
        
        $this->save();
        
        // Trigger next stage if approved
        if (in_array($option, ['APPROVE_FULL', 'APPROVE_PARTIAL', 'APPROVE_MODIFIED'])) {
            ExecutionPlan::create($this->data['decision_id']);
        }
    }
}
```

#### 3.2 Decision Document

```markdown
# Decision Record: DEC-20251221-001

## Context

**Discovery:** DISC-001 - AI Hero Component  
**Assessment:** ASSESS-001  
**Date:** 2025-12-21  
**Risk Level:** LOW

## Metrics

| Metric | Current | With AI Hero | Improvement |
|--------|---------|--------------|-------------|
| Time to Decision | 180s | 45s | -75% ✅ |
| Clicks to Decision | 5 | 2 | -60% ✅ |
| User Confidence | 6/10 | 9/10 | +50% ✅ |

## Assessment Summary

- **Database Impact:** Minimal - reuses existing AI cache
- **API Impact:** None - uses existing endpoints
- **Business Logic:** Low risk - adds shortcut, doesn't remove options
- **Security:** No new vulnerabilities
- **Overall Risk:** **LOW**

## Options Considered

### Option 1: APPROVE_FULL ✅ CHOSEN
Adopt the entire AI Hero component as designed.

**Pros:**
- Massive UX improvement (75% faster decisions)
- Higher user confidence
- No breaking changes
- Backward compatible

**Cons:**
- Requires user education on new flow
- Hides manual options (but still accessible)

### Option 2: APPROVE_PARTIAL
Adopt AI recommendation but keep it smaller, inline.

**Pros:**
- Less dramatic change
- Easier to reverse

**Cons:**
- Loses the "hero" impact
- Metrics show hero size matters

### Option 3: REJECT
Keep current design.

**Pros:**
- No change risk

**Cons:**
- Miss massive UX improvement
- Users keep wasting time

## Decision

**APPROVE_FULL**

## Rationale

The metrics are overwhelming:
- 75% reduction in decision time
- 50% increase in confidence
- Zero breaking changes
- Low risk (no DB/API changes)

The only concern is user adaptation, which can be addressed with:
1. Onboarding tooltip on first use
2. "Switch to manual mode" link always visible
3. Release notes communication

ROI is clear: save 2+ minutes per decision × 30 decisions/day × 5 users = **5 hours/day** saved.

## Approved By

Bakheet - 2025-12-21 18:00

## Next Steps

→ **Proceed to Execution Planning** (Stage 4)

## Rollback Plan

If issues arise:
1. Toggle feature flag `AI_HERO_ENABLED = false`
2. Falls back to current design immediately
3. No data migration needed
```

---

## ⚙️ Stage 4: Execution (التنفيذ المُتحكم)

### الهدف
تنفيذ التغيير بشكل آمن ومُتحكم فيه.

### العملية

#### 4.1 Execution Planner

```php
// design-lab/core/ExecutionPlanner.php

class ExecutionPlanner {
    
    /**
     * إنشاء خطة تنفيذ
     */
    public static function create($decisionId) {
        $decision = DecisionRecord::load($decisionId);
        
        // فقط إذا كان القرار "موافقة"
        if (!in_array($decision->getOption(), ['APPROVE_FULL', 'APPROVE_PARTIAL', 'APPROVE_MODIFIED'])) {
            throw new Exception("Cannot create execution plan for rejected/deferred decisions");
        }
        
        $plan = [
            'plan_id' => 'EXEC-' . date('YmdHis'),
            'decision_id' => $decisionId,
            
            'prerequisites' => [
                'backups' => [
                    'database' => true,
                    'files' => ['views/decision.php', 'assets/css/decision.css'],
                ],
                'feature_flags' => [
                    'AI_HERO_ENABLED' => false, // Start disabled
                ],
                'tests' => [
                    'unit_tests' => 'required',
                    'integration_tests' => 'required',
                    'user_acceptance' => 'required',
                ],
            ],
            
            'steps' => [
                [
                    'step' => 1,
                    'action' => 'Extract components from DesignLab',
                    'commands' => [
                        'cp design-lab/views/components/ai-hero.php views/components/',
                        'cp design-lab/assets/css/ai-hero.css assets/css/',
                    ],
                    'validation' => 'Files copied successfully',
                ],
                [
                    'step' => 2,
                    'action' => 'Update main decision view',
                    'files_to_modify' => ['views/decision.php'],
                    'validation' => 'Syntax check passed',
                ],
                [
                    'step' => 3,
                    'action' => 'Run tests',
                    'commands' => ['./scripts/test.sh'],
                    'validation' => 'All tests pass',
                ],
                [
                    'step' => 4,
                    'action' => 'Enable feature flag',
                    'commands' => ['php scripts/feature-flag.php --enable AI_HERO_ENABLED'],
                    'validation' => 'Feature visible to test users',
                ],
                [
                    'step' => 5,
                    'action' => 'Monitor',
                    'duration' => '24 hours',
                    'metrics_to_watch' => ['error_rate', 'decision_time', 'user_feedback'],
                ],
            ],
            
            'rollback_plan' => [
                'trigger_conditions' => [
                    'error_rate > 5%',
                    'user_feedback < 7/10',
                    'manual_trigger',
                ],
                'rollback_steps' => [
                    'Disable feature flag',
                    'Restore backed up files',
                    'Verify rollback',
                ],
            ],
            
            'status' => 'planned',
            'executed' => false,
            'executed_at' => null,
            'executed_by' => null,
        ];
        
        return new ExecutionPlan($plan);
    }
    
    /**
     * تنفيذ الخطة (يحتاج موافقة صريحة)
     */
    public function execute($approvedBy, $confirmationCode) {
        // Double confirmation required
        if ($confirmationCode !== $this->generateConfirmationCode()) {
            throw new Exception("Invalid confirmation code. Execution aborted.");
        }
        
        // Backup first
        $this->backup();
        
        // Execute steps
        foreach ($this->plan['steps'] as $step) {
            try {
                $this->executeStep($step);
            } catch (Exception $e) {
                // Auto-rollback on any error
                $this->rollback();
                throw $e;
            }
        }
        
        $this->plan['executed'] = true;
        $this->plan['executed_at'] = date('Y-m-d H:i:s');
        $this->plan['executed_by'] = $approvedBy;
        $this->plan['status'] = 'executed';
        
        $this->save();
    }
}
```

#### 4.2 Controlled Execution Interface

```php
<!-- design-lab/views/execute.php -->

<div class="execution-control">
    <h1>⚙️ Execution Control Panel</h1>
    
    <div class="plan-summary">
        <h2>Execution Plan: <?= $plan->getId() ?></h2>
        <p>Decision: <?= $decision->getTitle() ?></p>
        <p>Risk: <span class="badge-<?= strtolower($risk) ?>"><?= $risk ?></span></p>
    </div>
    
    <div class="prerequisites">
        <h3>✓ Prerequisites</h3>
        <ul>
            <li>✓ Database backup created</li>
            <li>✓ Files backed up</li>
            <li>✓ Feature flags configured</li>
            <li>✓ Tests ready</li>
        </ul>
    </div>
    
    <div class="execution-steps">
        <h3>Execution Steps</h3>
        <ol>
            <?php foreach ($plan->getSteps() as $step): ?>
            <li>
                <?= $step['action'] ?>
                <span class="validation"><?= $step['validation'] ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    
    <div class="confirmation-required">
        <h3>⚠️ Confirmation Required</h3>
        <p>This will modify the production system. Please confirm:</p>
        
        <form method="POST">
            <label>
                Enter confirmation code: <strong><?= $confirmationCode ?></strong>
            </label>
            <input type="text" name="code" required>
            
            <label>
                Your name:
            </label>
            <input type="text" name="executor" required>
            
            <button type="submit" class="btn-danger">
                🚀 Execute Plan
            </button>
            
            <button type="button" class="btn-secondary" onclick="history.back()">
                Cancel
            </button>
        </form>
    </div>
    
    <div class="rollback-info">
        <h3>Rollback Plan</h3>
        <p>If anything goes wrong:</p>
        <ul>
            <?php foreach ($plan->getRollbackSteps() as $step): ?>
            <li><?= $step ?></li>
            <?php endforeach; ?>
        </ul>
        <p>Estimated rollback time: <strong>< 5 minutes</strong></p>
    </div>
</div>
```

---

## 🔄 Complete Workflow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    1. DISCOVERY                             │
│  DesignLab Experiment → Change Detection → Documentation   │
│                                                             │
│  Output: discovery.json                                    │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│                    2. ASSESSMENT                            │
│  Logic Impact Analyzer → Risk Assessment → Impact Report   │
│                                                             │
│  Output: assessment.json (LOW/MEDIUM/HIGH/CRITICAL risk)   │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────────────────────────┐
│                    3. DECISION                              │
│  Review Metrics → Consider Options → Document Decision     │
│                                                             │
│  Output: decision.md (APPROVE/REJECT/DEFER)                │
└─────────────────┬───────────────────────────────────────────┘
                  ↓
         ┌────────┴────────┐
         │                 │
    APPROVE           REJECT/DEFER
         │                 │
         ↓                 ↓
┌─────────────────┐  ┌──────────┐
│  4. EXECUTION   │  │ ARCHIVE  │
│  Plan → Backup  │  └──────────┘
│  Execute → Test │
│  Monitor        │
└─────────────────┘
```

---

## 📁 File Structure

```
design-lab/
├── core/
│   ├── Discovery.php              # Stage 1
│   ├── LogicImpactAnalyzer.php    # Stage 2
│   ├── DecisionRecord.php         # Stage 3
│   └── ExecutionPlanner.php       # Stage 4
├── discoveries/
│   └── DISC-001.json
├── assessments/
│   └── ASSESS-001.json
├── decisions/
│   └── DEC-001.md
├── execution-plans/
│   └── EXEC-001.json
└── views/
    ├── discovery-dashboard.php
    ├── assessment-review.php
    ├── decision-maker.php
    └── execution-control.php
```

---

## ✅ Benefits

1. **🔒 Safety**: لا شيء يُنفذ بدون 4 مراحل موافقة
2. **📊 Data-Driven**: كل قرار مبني على metrics حقيقية
3. **📝 Documented**: كل شيء موثق وقابل للمراجعة
4. **🔄 Reversible**: Rollback plan جاهز دائماً
5. **⚖️ Risk-Aware**: تقييم المخاطر قبل التنفيذ
6. **🎯 Traceable**: من الفكرة للتنفيذ - مسار واضح

---

## 🚀 Next Steps

سأبني الآن:
1. ✅ `Discovery.php` - كشف التغييرات
2. ✅ `LogicImpactAnalyzer.php` - تحليل التأثير
3. ✅ `DecisionRecord.php` - نظام القرارات
4. ✅ `ExecutionPlanner.php` - التنفيذ المُتحكم

**هذا النظام يضمن ألا يصل أي شيء للـ production إلا بعد موافقة صريحة مدروسة!**
