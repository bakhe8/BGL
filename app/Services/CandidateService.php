<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Support\Normalizer;
use App\Support\Settings;
use App\Support\Config;
use App\Repositories\SupplierLearningRepository;

class CandidateService
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private Normalizer $normalizer = new Normalizer(),
        private \App\Repositories\BankRepository $banks = new \App\Repositories\BankRepository(),
        private \App\Repositories\SupplierOverrideRepository $overrides = new \App\Repositories\SupplierOverrideRepository(),
        private Settings $settings = new Settings(),
        private ?\App\Repositories\BankLearningRepository $bankLearning = null,
        private ?SupplierLearningRepository $supplierLearning = null,
    ) {
        $this->bankLearning = $this->bankLearning ?: new \App\Repositories\BankLearningRepository();
        $this->supplierLearning = $this->supplierLearning ?: new SupplierLearningRepository();
    }

    /**
     * إرجاع قائمة المرشحين للاسم الخام من المصادر: official + alternative names، مع درجات أساسية (Exact/StartsWith/Contains/Distance/Token).
     * لا يوجد Auto-Accept هنا؛ النتائج للعرض.
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, supplier_id:int, name:string, score:float}>}
     */
    public function supplierCandidates(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeSupplierName($rawSupplier);
        // عتبات الفزي الجديدة: قوي 0.90، ضعيف 0.80
        $strongTh = 0.90;
        $weakTh = 0.80;
        $reviewThreshold = $this->settings->get('MATCH_REVIEW_THRESHOLD', Config::MATCH_REVIEW_THRESHOLD);
        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        $candidates = [];
        $blockedId = null;

        // التعلم أولاً: إذا كان alias → تطابق وحيد، إذا blocked → استبعاد المورد المحظور
        $learned = $this->supplierLearning?->findByNormalized($normalized);
        if ($learned) {
            if ($learned['learning_status'] === 'supplier_alias') {
                return [
                    'normalized' => $normalized,
                    'candidates' => [[
                        'source' => 'learning',
                        'match_type' => 'exact',
                        'strength' => 'strong',
                        'supplier_id' => (int)$learned['linked_supplier_id'],
                        'name' => $this->suppliers->find($learned['linked_supplier_id'])?->officialName ?? $rawSupplier,
                        'score' => 1.0,
                        'score_raw' => 1.0,
                    ]],
                ];
            }
            if ($learned['learning_status'] === 'supplier_blocked') {
                $blockedId = (int)$learned['linked_supplier_id'];
            }
        }

        // Overrides
        foreach ($this->overrides->allNormalized() as $ov) {
            $candNorm = $this->normalizer->normalizeSupplierName($ov['override_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            if ($scoreRaw < $reviewThreshold) {
                continue;
            }
            if ($blockedId && (int)$ov['supplier_id'] === $blockedId) {
                continue;
            }
            $candidates[] = [
                'source' => 'override',
                'match_type' => 'exact',
                'strength' => 'strong',
                'supplier_id' => $ov['supplier_id'],
                'name' => $ov['override_name'],
                'score' => $scoreRaw * Config::WEIGHT_OFFICIAL,
                'score_raw' => $scoreRaw,
            ];
        }

        // تطابق رسمي
        foreach ($this->suppliers->findAllByNormalized($normalized) as $supplier) {
            $candNorm = $this->normalizer->normalizeSupplierName($supplier['normalized_name'] ?? $supplier['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $scoreWeighted = $scoreRaw * Config::WEIGHT_OFFICIAL;
            if ($scoreRaw < $reviewThreshold) {
                continue;
            }
            if ($blockedId && (int)$supplier['id'] === $blockedId) {
                continue;
            }
            $candidates[] = [
                'source' => 'official',
                'match_type' => 'exact',
                'strength' => 'strong',
                'supplier_id' => $supplier['id'],
                'name' => $supplier['official_name'],
                'score' => $scoreWeighted,
                'score_raw' => $scoreRaw,
            ];
        }

        // تطابق أسماء بديلة
        foreach ($this->supplierAlts->findAllByNormalized($normalized) as $alt) {
            $candNorm = $this->normalizer->normalizeSupplierName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $scoreWeighted = $scoreRaw * Config::WEIGHT_ALT_CONFIRMED;
            if ($scoreRaw < $reviewThreshold) {
                continue;
            }
            if ($blockedId && (int)$alt['supplier_id'] === $blockedId) {
                continue;
            }
            $candidates[] = [
                'source' => 'alternative',
                'match_type' => 'alternative',
                'strength' => 'strong',
                'supplier_id' => $alt['supplier_id'],
                'name' => $alt['raw_name'],
                'score' => $scoreWeighted,
                'score_raw' => $scoreRaw,
            ];
        }

        // Fuzzy أساسي (Levenshtein + token) على كل الموردين
        foreach ($this->suppliers->allNormalized() as $supplier) {
            if ($blockedId && (int)$supplier['id'] === $blockedId) {
                continue;
            }
            $candNorm = $this->normalizer->normalizeSupplierName($supplier['normalized_name'] ?? $supplier['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $score = $scoreRaw * Config::WEIGHT_FUZZY;
            if ($scoreRaw >= $weakTh) {
                $candidates[] = [
                    'source' => 'fuzzy_official',
                    'match_type' => $scoreRaw >= $strongTh ? 'fuzzy_strong' : 'fuzzy_weak',
                    'strength' => $scoreRaw >= $strongTh ? 'strong' : 'weak',
                    'supplier_id' => $supplier['id'],
                    'name' => $supplier['official_name'],
                    'score' => $score,
                    'score_raw' => $scoreRaw,
                ];
            }
        }

        // Fuzzy على الأسماء البديلة
        foreach ($this->supplierAlts->allNormalized() as $alt) {
            if ($blockedId && (int)$alt['supplier_id'] === $blockedId) {
                continue;
            }
            $candNorm = $this->normalizer->normalizeSupplierName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $score = $scoreRaw * Config::WEIGHT_FUZZY;
            if ($scoreRaw >= $weakTh) {
                $candidates[] = [
                    'source' => 'fuzzy_alternative',
                    'match_type' => $scoreRaw >= $strongTh ? 'fuzzy_strong' : 'fuzzy_weak',
                    'strength' => $scoreRaw >= $strongTh ? 'strong' : 'weak',
                    'supplier_id' => $alt['supplier_id'],
                    'name' => $alt['raw_name'],
                    'score' => $score,
                    'score_raw' => $scoreRaw,
                ];
            }
        }

        // أفضل درجة لكل supplier_id
        $bestBySupplier = [];
        foreach ($candidates as $c) {
            $sid = $c['supplier_id'];
            if (!isset($bestBySupplier[$sid]) || $c['score'] > $bestBySupplier[$sid]['score']) {
                $bestBySupplier[$sid] = $c;
            }
        }

        $unique = array_values($bestBySupplier);
        // فلترة نهائية حسب العتبات: رفض ما دون 0.80
        $unique = array_filter($unique, fn($c) => ($c['score_raw'] ?? $c['score'] ?? 0) >= $weakTh);
        usort($unique, fn($a, $b) => $b['score'] <=> $a['score']);

        return ['normalized' => $normalized, 'candidates' => $unique];
    }

    private function scoreComponents(string $input, string $candidate): array
    {
        $exact = $input === $candidate ? 1.0 : 0.0;
        $starts = (str_starts_with($candidate, $input) || str_starts_with($input, $candidate)) ? 0.85 : 0.0;
        $contains = (str_contains($candidate, $input) || str_contains($input, $candidate)) ? 0.75 : 0.0;
        $lev = $this->levenshteinRatio($input, $candidate);
        $tokens = $this->tokenSimilarity($input, $candidate);
        return compact('exact', 'starts', 'contains', 'lev', 'tokens');
    }

    private function maxScore(array $sim): float
    {
        return max($sim['exact'], $sim['starts'], $sim['contains'], $sim['lev'], $sim['tokens']);
    }

    private function levenshteinRatio(string $a, string $b): float
    {
        $len = max(mb_strlen($a), mb_strlen($b));
        if ($len === 0) {
            return 0.0;
        }
        $dist = levenshtein($a, $b);
        return max(0.0, 1.0 - ($dist / $len));
    }

    private function tokenSimilarity(string $a, string $b): float
    {
        $ta = array_filter(explode(' ', $a));
        $tb = array_filter(explode(' ', $b));
        if (!$ta || !$tb) {
            return 0.0;
        }
        $intersect = count(array_intersect($ta, $tb));
        $union = count(array_unique(array_merge($ta, $tb)));
        return $union === 0 ? 0.0 : $intersect / $union;
    }

    /**
     * مرشحي البنوك (official + fuzzy بسيط).
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, bank_id:int, name:string, score:float}>}
     */
    public function bankCandidates(string $rawBank): array
    {
        $normalized = $this->normalizer->normalizeBankName($rawBank);
        $short = $this->normalizer->normalizeBankShortCode($rawBank);
        $reviewThreshold = $this->settings->get('MATCH_REVIEW_THRESHOLD', Config::MATCH_REVIEW_THRESHOLD);
        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        $blockedId = null;
        $blockAll = false;
        $learning = $this->bankLearning?->findByNormalized($normalized);
        if ($learning) {
            if ($learning['status'] === 'alias' && !empty($learning['bank_id'])) {
                return [
                    'normalized' => $normalized,
                    'candidates' => [[
                        'source' => 'learning_alias',
                        'bank_id' => (int)$learning['bank_id'],
                        'name' => $this->banks->find((int)$learning['bank_id'])?->officialName ?? '',
                        'score' => 1.0,
                        'score_raw' => 1.0,
                    ]],
                ];
            }
            if ($learning['status'] === 'blocked' && !empty($learning['bank_id'])) {
                $blockedId = (int)$learning['bank_id'];
            } elseif ($learning['status'] === 'blocked') {
                // محظور بشكل عام (بدون بنك محدد) -> لا اقتراحات
                return ['normalized' => $normalized, 'candidates' => []];
            }
        }

        $candidates = [];
        // Step 1: short code exact
        if ($short !== '') {
            foreach ($this->banks->allNormalized() as $row) {
                $sc = strtoupper(trim((string)($row['short_code'] ?? '')));
                if ($sc !== '' && $sc === $short && ($blockedId === null || $blockedId !== (int)$row['id'])) {
                    $candidates[] = [
                        'source' => 'short_exact',
                        'bank_id' => (int)$row['id'],
                        'name' => $row['official_name'] ?? '',
                        'score' => 1.0,
                        'score_raw' => 1.0,
                    ];
                }
            }
        }

        // Step 2: short code fuzzy (>=0.9) إذا لم يوجد تطابق دقيق
        if (empty($candidates) && $short !== '') {
            $best = null;
            $bestScore = 0.0;
            $thresholdShort = 0.9;
            foreach ($this->banks->allNormalized() as $row) {
                $sc = strtoupper(trim((string)($row['short_code'] ?? '')));
                if ($sc === '' || ($blockedId !== null && $blockedId === (int)$row['id'])) {
                    continue;
                }
                $score = $this->levenshteinRatio($short, $sc);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $row;
                }
            }
            if ($best && $bestScore >= $thresholdShort) {
                $candidates[] = [
                    'source' => 'short_fuzzy',
                    'bank_id' => (int)$best['id'],
                    'name' => $best['official_name'] ?? '',
                    'score' => $bestScore,
                    'score_raw' => $bestScore,
                ];
            }
        }

        // Step 3: full name exact via normalized_key
        if (empty($candidates)) {
            $bank = $this->banks->findByNormalizedKey($normalized);
            if ($bank && ($blockedId === null || $blockedId !== $bank->id)) {
                $candidates[] = [
                    'source' => 'official',
                    'bank_id' => $bank->id,
                    'name' => $bank->officialName ?? '',
                    'score' => 1.0,
                    'score_raw' => 1.0,
                ];
            }
        }

        // Step 4: full name fuzzy on normalized_key (>=0.95) إذا لم يوجد تطابق
        if (empty($candidates)) {
            $best = null;
            $bestScore = 0.0;
            $threshold = 0.95;
            foreach ($this->banks->allNormalized() as $row) {
                $key = $row['normalized_key'] ?? '';
                if ($key === '' || ($blockedId !== null && $blockedId === (int)$row['id'])) {
                    continue;
                }
                $score = $this->levenshteinRatio($normalized, $key);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $row;
                }
            }
            if ($best && $bestScore >= $threshold) {
                $displayName = $best['official_name'] ?? '';
                $candidates[] = [
                    'source' => 'fuzzy_official',
                    'bank_id' => (int)$best['id'],
                    'name' => $displayName,
                    'score' => $bestScore,
                    'score_raw' => $bestScore,
                ];
            }
        }

        // تصفية حسب العتبة
        $candidates = array_filter($candidates, fn($c) => ($c['score'] ?? 0) >= $reviewThreshold);

        // أفضل لكل بنك
        $best = [];
        foreach ($candidates as $c) {
            $bid = $c['bank_id'];
            if (!isset($best[$bid]) || $c['score'] > $best[$bid]['score']) {
                $best[$bid] = $c;
            }
        }

        $unique = array_values($best);
        usort($unique, fn($a, $b) => $b['score'] <=> $a['score']);

        return ['normalized' => $normalized, 'candidates' => $unique];
    }

}
