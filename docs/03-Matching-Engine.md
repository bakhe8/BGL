# 03 - محرك المطابقة والتقييم (Matching & Scoring Engine)

> **آخر تحديث**: 2025-12-17  
> **النسخة**: 3.0 (مع Usage Tracking & Scoring)  
> **الحالة**: Active - Production

---

## 🎯 نظرة عامة

محرك المطابقة والتقييم هو العقل المدبر للنظام - يربط البيانات من Excel مع القواميس **ويتعلم** من اختيارات المستخدم.

### التطورات الرئيسية:

**v1.0** (2024): Fuzzy matching only  
**v2.0** (2025-12-13): Learning system  
**v3.0** (2025-12-17): **Usage tracking + Scoring + Star ratings** ⭐⭐⭐

---

## 📊 نظام التقييم والنقاط (Scoring System)

### Formula:

```
Total Score = Base Score + Bonus Points

Base Score (40-100):
  - Exact match: 100
  - Fuzzy ≥ 90%: 80
  - Fuzzy ≥ 80%: 60
  - Fuzzy < 80%: 40

Bonus Points (0-225):
  - Previously used: +50
  - Frequency: +(count-1) × 25 (max +150)
  - Recent (≤30 days): +25
```

### Star Ratings:

| Score | Stars | Meaning |
|-------|-------|---------|
| ≥ 200 | ⭐⭐⭐ | **Top choice** - used frequently |
| 120-199 | ⭐⭐ | **Good match** - used before or high similarity |
| < 120 | ⭐ | **Basic suggestion** - dictionary match |

### Example:

```
Supplier: "شركة ABC"
Excel: "ABC TRADING"

Candidate 1: "شركة ABC للتجارة"
  Base: 80 (fuzzy 90%)
  Bonus: 50 (used) + 75 (3 times) + 25 (last week) = 150
  Total: 230 → ⭐⭐⭐

Candidate 2: "ABC COMPANY"
  Base: 60 (fuzzy 82%)
  Bonus: 0 (never used)
  Total: 60 → ⭐
```

---

## 🔍 خوارزميات المطابقة (Matching Algorithms)

### 1. Exact Match (تطابق تام)
```php
if (normalize($a) === normalize($b)) {
    return 1.0; // 100%
}
```

### 2. Starts With / Contains
```php
if (starts_with($normalized_a, $normalized_b)) {
    return 0.85;
}
if (contains($normalized_a, $normalized_b)) {
    return 0.75;
}
```

### 3. Levenshtein Distance (Fuzzy)
```php
$distance = levenshtein($a, $b);
$maxLen = max(strlen($a), strlen($b));
$similarity = 1 - ($distance / $maxLen);
return $similarity; // 0.0 - 1.0
```

### 4. Token Jaccard (تشابه الكلمات)
```php
$tokensA = explode(' ', $a);
$tokensB = explode(' ', $b);
$intersection = array_intersect($tokensA, $tokensB);
$union = array_unique(array_merge($tokensA, $tokensB));
return count($intersection) / count($union);
```

**Best Match Wins**: النظام يجرب كل الخوارزميات ويأخذ الأعلى.

---

## 📚 مصادر المطابقة (Matching Sources)

### للموردين (Suppliers):

1. **official_name** (الاسم الرسمي)
   - من جدول `suppliers`
   - الأعلى أولوية

2. **alternative_names** (الأسماء البديلة)
   - من جدول `supplier_alternative_names`
   - Manually added

3. **learning aliases** (التعلم)
   - من جدول `supplier_aliases_learning`
   - Auto-created from user selections
   - **NEW**: مع `usage_count` و `last_used_at`

### للبنوك (Banks):

1. **official_name_ar / official_name_en**
   - النص بالعربي أو الإنجليزي

2. **short_code**
   - مثل: SNB, NCB, SABB

3. **learning aliases** (نادر)
   - من `bank_aliases_learning`

---

## 🧠 نظام التعلم (Learning System)

### متى يتعلم النظام؟

```php
// في process_update.php عند حفظ القرار
if (user selects supplier for raw_name) {
    if (exact match exists in learning) {
        // Increment usage_count
        $repository->incrementUsage($normalized);
    } else {
        // Create new alias
        $repository->upsert([
            'original_supplier_name' => $rawName,
            'normalized_supplier_name' => $normalized,
            'linked_supplier_id' => $supplierId,
            'learning_status' => 'supplier_alias',
            'usage_count' => 1,
            'last_used_at' => now(),
        ]);
    }
}
```

### Usage Statistics:

```php
class SupplierLearningRepository {
    public function incrementUsage(string $normalized): bool {
        $sql = "UPDATE supplier_aliases_learning 
                SET usage_count = COALESCE(usage_count, 0) + 1,
                    last_used_at = CURRENT_TIMESTAMP
                WHERE normalized_supplier_name = ?";
        // ...
    }
    
    public function getUsageStats(int $supplierId): array {
        $sql = "SELECT original_supplier_name, 
  

              COALESCE(usage_count, 1) as usage_count,
                       last_used_at
                FROM supplier_aliases_learning
                WHERE linked_supplier_id = ?
                ORDER BY usage_count DESC, last_used_at DESC";
        // ...
    }
}
```

---

## ⚙️ CandidateService - العقل المدبر

```php
class CandidateService {
    public function supplierCandidates(string $rawName): array {
        // 1. Generate base candidates (dictionary + learning)
        $candidates = $this->generateCandidates($rawName);
        
        // 2. Enrich with usage statistics
        $enriched = $this->enrichWithUsageData($candidates);
        
        // 3. Calculate scores
        foreach ($enriched as &$cand) {
            $baseScore = $this->calculateBaseScore($cand['score_raw'], $cand['match_type']);
            $bonus = $this->calculateBonusPoints($cand['usage_data']);
            $cand['total_score'] = $baseScore + $bonus;
            $cand['star_rating'] = $this->assignStarRating($cand['total_score']);
        }
        
        // 4. Sort by total score DESC
        usort($enriched, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        return ['candidates' => $enriched];
    }
    
    private function calculateBaseScore(float $fuzzyScore, string $type): int {
        if ($type === 'exact') return 100;
        if ($fuzzyScore >= 0.90) return 80;
        if ($fuzzyScore >= 0.80) return 60;
        return 40;
    }
    
    private function calculateBonusPoints(?array $usageData): int {
        if (!$usageData) return 0;
        
        $bonus = 50; // Used before
        $count = (int)$usageData['usage_count'];
        $bonus += min(($count - 1) * 25, 150); // Frequency (capped)
        
        if ($usageData['last_used_at']) {
            $daysSince = (new DateTime())->diff(new DateTime($usageData['last_used_at']))->days;
            if ($daysSince <= 30) $bonus += 25; // Recency
        }
        
        return $bonus;
    }
    
    private function assignStarRating(int $totalScore): int {
        if ($totalScore >= 200) return 3;
        if ($totalScore >= 120) return 2;
        return 1;
    }
}
```

---

## 🎨 Visual Hierarchy (التسلسل البصري)

```
Decision Page Chips Order:

1. [✓ Current Selection - Badge] ← Green, disabled, Phase 5
2. [⭐⭐⭐ Most Used Name] ← Gold gradient, clickable
3. [⭐⭐ Used Before] ← Yellow, clickable
4. [⭐ Dictionary Match] ← Default gray, clickable
5. [+ Add New Supplier] ← Only if no good match
```

---

## 📐 عتبات القبول (Acceptance Thresholds)

| Threshold | Value | Purpose |
|-----------|-------|---------|
| **Auto-accept** | ≥ 0.99 | Exact match → auto-fill |
| **High confidence** | ≥ 0.90 | Show as first suggestion |
| **Medium confidence** | ≥ 0.80 | Show in suggestions |
| **Low confidence** | < 0.80 | Show with % score |
| **Reject** | < 0.60 | Don't show |

---

## 🔄 Integration with Decision Page

```mermaid
graph LR
    A[User opens decision.php] --> B[Load record]
    B --> C[CandidateService.supplierCandidates]
    C --> D[Generate base matches]
    D --> E[Enrich with usage stats]
    E --> F[Calculate scores]
    F --> G[Assign star ratings]
    G --> H[Sort by total score]
    H --> I[Render chips with stars]
    I --> J[User selects]
    J --> K[Save to DB]
    K --> L[Increment usage_count]
    L --> M[Update last_used_at]
```

---

## 📖 Examples

### Example 1: High Usage Supplier

```
Excel: "ABC TRADING"
Database:
  - ID: 15, Name: "شركة ABC للتجارة"
    Usage: 5 times, Last: 2 days ago

Result:
  Base: 80 (fuzzy 92%)
  Bonus: 50 + 100 (4×25) + 25 = 175
  Total: 255
  Stars: ⭐⭐⭐
```

### Example 2: New Dictionary Match

```
Excel: "XYZ COMPANY"
Database:
  - ID: 42, Name: "شركة XYZ"
    Usage: never

Result:
  Base: 75 (fuzzy 88%)
  Bonus: 0
  Total: 75
  Stars: ⭐
```

---

## 🔗 Related Documentation

- [`docs/06-Decision-Page.md`](./06-Decision-Page.md) - UI implementation
- [`docs/usage_tracking_system.md`](./usage_tracking_system.md) - Technical spec
- [`app/Services/CandidateService.php`](../app/Services/CandidateService.php) - Source code
- [`app/Repositories/SupplierLearningRepository.php`](../app/Repositories/SupplierLearningRepository.php) - Usage tracking

---

## ✅ What's New in v3.0

- ✅ Usage count tracking (`usage_count`, `last_used_at`)
- ✅ Scoring algorithm (Base + Bonus)
- ✅ Star rating system (1-3 stars)
- ✅ Visual hierarchy in chips
- ✅ Smart sorting by total score
- ✅ Integration with current selection indicator
