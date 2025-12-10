<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankAlternativeNameRepository;
use App\Support\Normalizer;
use App\Support\Settings;
use App\Support\Config;

class CandidateService
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private Normalizer $normalizer = new Normalizer(),
        private \App\Repositories\BankRepository $banks = new \App\Repositories\BankRepository(),
        private BankAlternativeNameRepository $bankAlts = new BankAlternativeNameRepository(),
        private \App\Repositories\SupplierOverrideRepository $overrides = new \App\Repositories\SupplierOverrideRepository(),
        private Settings $settings = new Settings(),
    ) {
    }

    /**
     * إرجاع قائمة المرشحين للاسم الخام من المصادر: official + alternative names، مع درجات أساسية (Exact/StartsWith/Contains/Distance/Token).
     * لا يوجد Auto-Accept هنا؛ النتائج للعرض.
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, supplier_id:int, name:string, score:float}>}
     */
    public function supplierCandidates(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeName($rawSupplier);
        $reviewThreshold = $this->settings->get('MATCH_REVIEW_THRESHOLD', Config::MATCH_REVIEW_THRESHOLD);
        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        $candidates = [];

        // Overrides
        foreach ($this->overrides->allNormalized() as $ov) {
            $candNorm = $this->normalizer->normalizeName($ov['override_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $candidates[] = [
                'source' => 'override',
                'supplier_id' => $ov['supplier_id'],
                'name' => $ov['override_name'],
                'score' => $scoreRaw * Config::WEIGHT_OFFICIAL,
                'score_raw' => $scoreRaw,
            ];
        }

        // تطابق رسمي
        foreach ($this->suppliers->findAllByNormalized($normalized) as $supplier) {
            $candNorm = $this->normalizer->normalizeName($supplier['normalized_name'] ?? $supplier['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $scoreWeighted = $scoreRaw * Config::WEIGHT_OFFICIAL;
            $candidates[] = [
                'source' => 'official',
                'supplier_id' => $supplier['id'],
                'name' => $supplier['official_name'],
                'score' => $scoreWeighted,
                'score_raw' => $scoreRaw,
            ];
        }

        // تطابق أسماء بديلة
        foreach ($this->supplierAlts->findAllByNormalized($normalized) as $alt) {
            $candNorm = $this->normalizer->normalizeName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $scoreWeighted = $scoreRaw * Config::WEIGHT_ALT_CONFIRMED;
            $candidates[] = [
                'source' => 'alternative',
                'supplier_id' => $alt['supplier_id'],
                'name' => $alt['raw_name'],
                'score' => $scoreWeighted,
                'score_raw' => $scoreRaw,
            ];
        }

        // Fuzzy أساسي (Levenshtein + token) على كل الموردين
        foreach ($this->suppliers->allNormalized() as $supplier) {
            $candNorm = $this->normalizer->normalizeName($supplier['normalized_name'] ?? $supplier['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $score = $scoreRaw * Config::WEIGHT_FUZZY;
            if ($score >= $reviewThreshold) {
                $candidates[] = [
                    'source' => 'fuzzy_official',
                    'supplier_id' => $supplier['id'],
                    'name' => $supplier['official_name'],
                    'score' => $score,
                    'score_raw' => $scoreRaw,
                ];
            }
        }

        // Fuzzy على الأسماء البديلة
        foreach ($this->supplierAlts->allNormalized() as $alt) {
            $candNorm = $this->normalizer->normalizeName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $score = $scoreRaw * Config::WEIGHT_FUZZY;
            if ($score >= $reviewThreshold) {
                $candidates[] = [
                    'source' => 'fuzzy_alternative',
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
        $normalized = $this->normalizer->normalizeName($rawBank);
        $reviewThreshold = $this->settings->get('MATCH_REVIEW_THRESHOLD', Config::MATCH_REVIEW_THRESHOLD);
        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        $candidates = [];
        foreach ($this->banks->findAllByNormalized($normalized) as $bank) {
            $candNorm = $this->normalizer->normalizeName($bank['normalized_name'] ?? $bank['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $candidates[] = [
                'source' => 'official',
                'bank_id' => $bank['id'],
                'name' => $bank['official_name'],
                'score' => $scoreRaw * Config::WEIGHT_OFFICIAL,
                'score_raw' => $scoreRaw,
            ];
        }

        // أسماء بديلة للبنوك
        foreach ($this->bankAlts->findAllByNormalized($normalized) as $alt) {
            $candNorm = $this->normalizer->normalizeName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $candidates[] = [
                'source' => 'alternative',
                'bank_id' => $alt['bank_id'],
                'name' => $alt['raw_name'],
                'score' => $scoreRaw * Config::WEIGHT_ALT_CONFIRMED,
                'score_raw' => $scoreRaw,
            ];
        }

        foreach ($this->banks->allNormalized() as $bank) {
            $candNorm = $this->normalizer->normalizeName($bank['normalized_name'] ?? $bank['official_name']);
            $sim = $this->scoreComponents($normalized, $candNorm);
            $scoreRaw = $this->maxScore($sim);
            $score = $scoreRaw * Config::WEIGHT_FUZZY;
            if ($score >= $reviewThreshold) {
                $candidates[] = [
                    'source' => 'fuzzy_official',
                    'bank_id' => $bank['id'],
                    'name' => $bank['official_name'],
                    'score' => $score,
                    'score_raw' => $scoreRaw,
                ];
            }
        }

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
