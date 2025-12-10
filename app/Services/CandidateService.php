<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Support\Normalizer;

class CandidateService
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private Normalizer $normalizer = new Normalizer(),
    ) {
    }

    /**
     * إرجاع قائمة المرشحين للاسم الخام من المصادر: official + alternative names مع درجة إرشادية.
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, supplier_id:int, name:string, score:float}>}
     */
    public function supplierCandidates(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeName($rawSupplier);
        $candidates = [];

        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        foreach ($this->suppliers->findAllByNormalized($normalized) as $supplier) {
            $candNorm = $this->normalizer->normalizeName($supplier['normalized_name'] ?? $supplier['official_name']);
            $candidates[] = [
                'source' => 'official',
                'supplier_id' => $supplier['id'],
                'name' => $supplier['official_name'],
                'score' => $this->score($normalized, $candNorm),
            ];
        }

        foreach ($this->supplierAlts->findAllByNormalized($normalized) as $alt) {
            $candNorm = $this->normalizer->normalizeName($alt['normalized_raw_name'] ?? $alt['raw_name']);
            $candidates[] = [
                'source' => 'alternative',
                'supplier_id' => $alt['supplier_id'],
                'name' => $alt['raw_name'],
                'score' => $this->score($normalized, $candNorm),
            ];
        }

        // لا يوجد ترتيب وزني متقدم هنا؛ فقط فرز تنازلي بالدرجة الإرشادية
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return ['normalized' => $normalized, 'candidates' => $candidates];
    }

    private function score(string $input, string $candidate): float
    {
        if ($input === '' || $candidate === '') {
            return 0.0;
        }
        if ($input === $candidate) {
            return 1.0;
        }

        $starts = (str_starts_with($candidate, $input) || str_starts_with($input, $candidate)) ? 0.8 : 0.0;
        $contains = (str_contains($candidate, $input) || str_contains($input, $candidate)) ? 0.7 : 0.0;
        $lev = $this->levenshteinRatio($input, $candidate);
        $tokens = $this->tokenSimilarity($input, $candidate);

        return max($starts, $contains, $lev, $tokens);
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
}
