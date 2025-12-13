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
    private ?array $cachedSuppliers = null;
    private ?array $cachedBanks = null;

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
        // عتبات الفزي الجديدة
        $strongTh = (float) $this->settings->get('MATCH_AUTO_THRESHOLD', Config::MATCH_AUTO_THRESHOLD);
        $weakTh = (float) $this->settings->get('MATCH_WEAK_THRESHOLD', 0.80);
        $reviewThreshold = $this->settings->get('MATCH_REVIEW_THRESHOLD', Config::MATCH_REVIEW_THRESHOLD);
        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        $candidates = [];
        $blockedId = null;

        // التعلم أولاً
        $learned = $this->supplierLearning?->findByNormalized($normalized);
        if ($learned) {
            if ($learned['learning_status'] === 'supplier_alias') {
                return [
                    'normalized' => $normalized,
                    'candidates' => [
                        [
                            'source' => 'learning',
                            'match_type' => 'exact',
                            'strength' => 'strong',
                            'supplier_id' => (int) $learned['linked_supplier_id'],
                            'name' => $this->suppliers->find($learned['linked_supplier_id'])?->officialName ?? $rawSupplier,
                            'score' => 1.0,
                            'score_raw' => 1.0,
                        ]
                    ],
                ];
            }
            if ($learned['learning_status'] === 'supplier_blocked') {
                $blockedId = (int) $learned['linked_supplier_id'];
            }
        }

        // Cache ONCE
        if ($this->cachedSuppliers === null) {
            $this->cachedSuppliers = $this->suppliers->allNormalized();
        }

        // Overrides
        foreach ($this->overrides->allNormalized() as $ov) {
            $candNorm = $this->normalizer->normalizeSupplierName($ov['override_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            if ($scoreRaw < $reviewThreshold) {
                continue;
            }
            if ($blockedId && (int) $ov['supplier_id'] === $blockedId) {
                continue;
            }
            $candidates[] = [
                'source' => 'override',
                'match_type' => 'exact',
                'strength' => 'strong',
                'supplier_id' => $ov['supplier_id'],
                'name' => $ov['override_name'],
                'score' => $scoreRaw * (float) $this->settings->get('WEIGHT_OFFICIAL', Config::WEIGHT_OFFICIAL),
                'score_raw' => $scoreRaw,
            ];
        }

        // تطابق رسمي (FROM CACHE)
        foreach ($this->cachedSuppliers as $supplier) {
            if (($supplier['normalized_name'] === $normalized) || ($supplier['supplier_normalized_key'] ?? '') === $this->normalizer->makeSupplierKey($rawSupplier)) {
                // Logic for exact match duplicated here for candidate listing...
            }

            // Actually, let's just run the full scan on cache since we need similarity scores for everyone
            $candNorm = $this->normalizer->normalizeSupplierName($supplier['normalized_name'] ?? $supplier['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);

            // Adjust weight based on if it was exact or fuzzy
            // Simplification: if scoreRaw is 1.0 -> Official Exact
            $scoreWeighted = $scoreRaw * (float) $this->settings->get('WEIGHT_OFFICIAL', Config::WEIGHT_OFFICIAL);

            // Fuzzy Logic mixed in
            if ($scoreRaw < $reviewThreshold && $scoreRaw < $weakTh) {
                continue;
            }

            if ($blockedId && (int) $supplier['id'] === $blockedId) {
                continue;
            }

            $type = 'fuzzy_weak';
            $strength = 'weak';
            if ($scoreRaw >= 1.0) {
                $type = 'exact';
                $strength = 'strong';
            } elseif ($scoreRaw >= $strongTh) {
                $type = 'fuzzy_strong';
                $strength = 'strong';
                $scoreWeighted = $scoreRaw * (float) $this->settings->get('WEIGHT_FUZZY', Config::WEIGHT_FUZZY);
            } else {
                $scoreWeighted = $scoreRaw * (float) $this->settings->get('WEIGHT_FUZZY', Config::WEIGHT_FUZZY);
            }

            $candidates[] = [
                'source' => ($scoreRaw >= 1.0) ? 'official' : 'fuzzy_official',
                'match_type' => $type,
                'strength' => $strength,
                'supplier_id' => $supplier['id'],
                'name' => $supplier['official_name'],
                'score' => $scoreWeighted,
                'score_raw' => $scoreRaw,
            ];
        }

        // Create a map for fast lookup of official names from cache
        $supplierMap = [];
        foreach ($this->cachedSuppliers as $s) {
            $supplierMap[$s['id']] = $s['official_name'];
        }

        // تطابق أسماء بديلة (Direct DB still required unless cached)
        foreach ($this->supplierAlts->findAllByNormalized($normalized) as $alt) {
            // ... [Logic kept same but wrapped for blockedId]
            if ($blockedId && (int) $alt['supplier_id'] === $blockedId)
                continue;

            // Resolve Official Name
            $officialName = $supplierMap[$alt['supplier_id']] ?? $alt['raw_name'];

            // If exact match found via `findAllByNormalized`
            $candidates[] = [
                'source' => 'alternative',
                'match_type' => 'alternative',
                'strength' => 'strong',
                'supplier_id' => $alt['supplier_id'],
                'name' => $officialName, // PRIMARY: Official Name
                'matched_on' => $alt['raw_name'], // CONTEXT: What matched
                'score' => 1.0 * (float) $this->settings->get('WEIGHT_ALT_CONFIRMED', Config::WEIGHT_ALT_CONFIRMED),
                'score_raw' => 1.0,
            ];
        }

        // Fuzzy Alts
        foreach ($this->supplierAlts->allNormalized() as $alt) {
            if ($blockedId && (int) $alt['supplier_id'] === $blockedId) {
                continue;
            }
            $candNorm = $this->normalizer->normalizeSupplierName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $score = $scoreRaw * (float) $this->settings->get('WEIGHT_FUZZY', Config::WEIGHT_FUZZY);
            if ($scoreRaw >= $weakTh) {
                $officialName = $supplierMap[$alt['supplier_id']] ?? $alt['raw_name'];
                $candidates[] = [
                    'source' => 'fuzzy_alternative',
                    'match_type' => $scoreRaw >= $strongTh ? 'fuzzy_strong' : 'fuzzy_weak',
                    'strength' => $scoreRaw >= $strongTh ? 'strong' : 'weak',
                    'supplier_id' => $alt['supplier_id'],
                    'name' => $officialName, // PRIMARY
                    'matched_on' => $alt['raw_name'], // CONTEXT
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

        $limit = (int) $this->settings->get('CANDIDATES_LIMIT', 20);
        if ($limit > 0) {
            $unique = array_slice($unique, 0, $limit);
        }

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
        // levenshtein limit is 255 bytes. mb_strlen is chars, but typically safely bounded check helps.
        // We will just cap checks for very long strings to avoid warnings/errors.
        if (strlen($a) > 255 || strlen($b) > 255) {
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
        $learning = $this->bankLearning?->findByNormalized($normalized);
        if ($learning) {
            if ($learning['status'] === 'alias' && !empty($learning['bank_id'])) {
                return [
                    'normalized' => $normalized,
                    'candidates' => [
                        [
                            'source' => 'learning_alias',
                            'bank_id' => (int) $learning['bank_id'],
                            'name' => $this->banks->find((int) $learning['bank_id'])?->officialName ?? '',
                            'score' => 1.0,
                            'score_raw' => 1.0,
                        ]
                    ],
                ];
            }
            if ($learning['status'] === 'blocked') {
                // Block logic...
                if (!empty($learning['bank_id']))
                    $blockedId = (int) $learning['bank_id'];
                else
                    return ['normalized' => $normalized, 'candidates' => []];
            }
        }

        // Cache Banks
        if ($this->cachedBanks === null) {
            $this->cachedBanks = $this->banks->allNormalized();
        }

        $candidates = [];

        // Iterate Cache Once for both Short and Long
        foreach ($this->cachedBanks as $row) {
            if ($blockedId && (int) $row['id'] === $blockedId)
                continue;

            // Short Code Logic
            if ($short !== '') {
                $sc = strtoupper(trim((string) ($row['short_code'] ?? '')));
                if ($sc !== '') {
                    if ($sc === $short) {
                        $candidates[] = [
                            'source' => 'short_exact',
                            'bank_id' => (int) $row['id'],
                            'name' => $row['official_name'] ?? '',
                            'score' => 1.0,
                            'score_raw' => 1.0,
                        ];
                    } else {
                        // Fuzzy Short
                        $score = $this->levenshteinRatio($short, $sc);
                        if ($score >= 0.9) {
                            $candidates[] = [
                                'source' => 'short_fuzzy',
                                'bank_id' => (int) $row['id'],
                                'name' => $row['official_name'] ?? '',
                                'score' => $score,
                                'score_raw' => $score,
                            ];
                        }
                    }
                }
            }

            // Full Name Logic
            $key = $row['normalized_key'] ?? '';
            if ($key !== '') {
                // Exact
                // Note: normalized_key check against 'normalized'
                if ($key === $normalized) {
                    $candidates[] = [
                        'source' => 'official',
                        'bank_id' => (int) $row['id'],
                        'name' => $row['official_name'] ?? '',
                        'score' => 1.0,
                        'score_raw' => 1.0,
                    ];
                } else {
                    // Fuzzy
                    $score = $this->levenshteinRatio($normalized, $key);
                    if ($score >= 0.95) {
                        $candidates[] = [
                            'source' => 'fuzzy_official',
                            'bank_id' => (int) $row['id'],
                            'name' => $row['official_name'] ?? '',
                            'score' => $score,
                            'score_raw' => $score,
                        ];
                    }
                }
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
