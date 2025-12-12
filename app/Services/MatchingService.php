<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierOverrideRepository;
use App\Repositories\SupplierLearningRepository;
use App\Support\Config;
use App\Support\Normalizer;
use App\Support\Settings;
use App\Services\CandidateService;

class MatchingService
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private BankRepository $banks,
        private Normalizer $normalizer = new Normalizer(),
        private SupplierOverrideRepository $overrides = new SupplierOverrideRepository(),
        private Settings $settings = new Settings(),
        private ?CandidateService $candidates = null,
        private ?\App\Repositories\BankLearningRepository $bankLearning = null,
        private ?SupplierLearningRepository $supplierLearning = null,
    ) {
        $this->candidates = $this->candidates ?: new CandidateService(
            new \App\Repositories\SupplierRepository(),
            new \App\Repositories\SupplierAlternativeNameRepository(),
            new Normalizer(),
            $this->banks,
            new SupplierOverrideRepository(),
            $this->settings
        );
        $this->bankLearning = $this->bankLearning ?: new \App\Repositories\BankLearningRepository();
        $this->supplierLearning = $this->supplierLearning ?: new SupplierLearningRepository();
    }

    /**
     * @return array{normalized:string, supplier_id?:int, match_status:string}
     */
    public function matchSupplier(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeSupplierName($rawSupplier);
        $autoTh = $this->settings->get('MATCH_AUTO_THRESHOLD', Config::MATCH_AUTO_THRESHOLD);
        $result = [
            'normalized' => $normalized,
            'match_status' => 'needs_review',
        ];

        if ($normalized === '') {
            return $result;
        }

        // التعلم أولاً
        $learned = $this->supplierLearning->findByNormalized($normalized);
        if ($learned) {
            if ($learned['learning_status'] === 'supplier_alias') {
                $result['supplier_id'] = (int)$learned['linked_supplier_id'];
                $result['match_status'] = $autoTh >= 0.9 ? 'ready' : 'needs_review';
                return $result;
            }
            if ($learned['learning_status'] === 'supplier_blocked' && (int)$learned['linked_supplier_id'] > 0) {
                // تجاهل المورد المحظور لهذا الاسم
                $result['_blocked_supplier_id'] = (int)$learned['linked_supplier_id'];
            }
        }

        // Overrides أولاً
        foreach ($this->overrides->allNormalized() as $ov) {
            $ovNorm = $this->normalizer->normalizeSupplierName($ov['override_name']);
            if ($ovNorm === $normalized) {
                if (!isset($result['_blocked_supplier_id']) || $result['_blocked_supplier_id'] !== (int)$ov['supplier_id']) {
                    $result['supplier_id'] = $ov['supplier_id'];
                    $result['match_status'] = 'ready';
                    return $result;
                }
            }
        }

        // official by normalized_key (بدون مسافات)
        $supplierKey = $this->suppliers->findByNormalizedKey($this->normalizer->makeSupplierKey($rawSupplier));
        if ($supplierKey && (!isset($result['_blocked_supplier_id']) || $result['_blocked_supplier_id'] !== $supplierKey->id)) {
            $result['supplier_id'] = $supplierKey->id;
            $result['match_status'] = $autoTh >= 0.9 ? 'ready' : 'needs_review';
            return $result;
        }

        $supplier = $this->suppliers->findByNormalizedName($normalized);
        if ($supplier) {
            if (!isset($result['_blocked_supplier_id']) || $result['_blocked_supplier_id'] !== $supplier->id) {
                $result['supplier_id'] = $supplier->id;
                $result['match_status'] = $autoTh >= 0.9 ? 'ready' : 'needs_review';
                return $result;
            }
        }

        // alternative names
        $alt = $this->supplierAlts->findByNormalized($normalized);
        if ($alt) {
            if (!isset($result['_blocked_supplier_id']) || $result['_blocked_supplier_id'] !== $alt->supplierId) {
                $result['supplier_id'] = $alt->supplierId;
                // أقل ثقة -> يظل needs_review، يمكن رفعها لاحقاً
                $result['match_status'] = 'needs_review';
                return $result;
            }
        }

        // Fuzzy قوي فقط
        $best = null;
        $bestScore = 0.0;
        foreach ($this->suppliers->allNormalized() as $row) {
            $candNorm = $this->normalizer->normalizeSupplierName($row['normalized_name'] ?? $row['official_name']);
            if ($candNorm === '') {
                continue;
            }
            if (isset($result['_blocked_supplier_id']) && $result['_blocked_supplier_id'] === (int)$row['id']) {
                continue;
            }
            $score = $this->levenshteinRatio($normalized, $candNorm);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }
        if ($best && $bestScore >= 0.9) {
            $result['supplier_id'] = (int)$best['id'];
            $result['match_status'] = 'needs_review'; // fuzzy → مراجعة
        }

        return $result;
    }

    /**
     * @return array{normalized:string, bank_id?:int, final_name?:string}
     */
    public function matchBank(string $rawBank): array
    {
        $normalized = $this->normalizer->normalizeBankName($rawBank);
        $short = $this->normalizer->normalizeBankShortCode($rawBank);
        $result = [
            'normalized' => $normalized,
        ];

        // Step 0: التعلم (alias/blocked) آخر قرار فقط
        $learned = $this->bankLearning?->findByNormalized($normalized);
        if ($learned) {
            if ($learned['status'] === 'alias' && !empty($learned['bank_id'])) {
                $result['bank_id'] = (int)$learned['bank_id'];
                return $result;
            }
            if ($learned['status'] === 'blocked') {
                if (!empty($learned['bank_id'])) {
                    $result['_blocked_bank_id'] = (int)$learned['bank_id'];
                } else {
                    // محظور بشكل عام لهذا الاسم → لا تطابق
                    return $result;
                }
            }
        }

        // Step 1: short code exact
        if ($short !== '') {
            foreach ($this->banks->allNormalized() as $row) {
                $sc = strtoupper(trim((string)($row['short_code'] ?? '')));
                if ($sc !== '' && $sc === $short) {
                    if (isset($result['_blocked_bank_id']) && $result['_blocked_bank_id'] === (int)$row['id']) {
                        continue;
                    }
                    $result['bank_id'] = (int)$row['id'];
                    $result['final_name'] = $row['official_name'] ?? null;
                    return $result;
                }
            }
        }

        // Step 2: short code fuzzy (>=0.9)
        if ($short !== '') {
            $best = null;
            $bestScore = 0.0;
            $threshold = 0.9;
            foreach ($this->banks->allNormalized() as $row) {
                $sc = strtoupper(trim((string)($row['short_code'] ?? '')));
                if ($sc === '') {
                    continue;
                }
                if (isset($result['_blocked_bank_id']) && $result['_blocked_bank_id'] === (int)$row['id']) {
                    continue;
                }
                $score = $this->levenshteinRatio($short, $sc);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $row;
                }
            }
            if ($best && $bestScore >= $threshold) {
                $result['bank_id'] = (int)$best['id'];
                $result['final_name'] = $best['official_name'] ?? null;
                return $result;
            }
        }

        if ($normalized === '') {
            return $result;
        }

        // Step 3: full name exact via normalized_key
        $bank = $this->banks->findByNormalizedKey($normalized);
        if ($bank) {
            if (!isset($result['_blocked_bank_id']) || $result['_blocked_bank_id'] !== $bank->id) {
                $result['bank_id'] = $bank->id;
                $result['final_name'] = $bank->officialNameAr ?? $bank->officialName ?? null;
                return $result;
            }
        }

        // Step 4: full name fuzzy on normalized_key (>=0.95)
        $best = null;
        $bestScore = 0.0;
        $threshold = 0.95; // تطابق قوي فقط
        foreach ($this->banks->allNormalized() as $row) {
            $key = $row['normalized_key'] ?? '';
            if ($key === '') {
                continue;
            }
            if (isset($result['_blocked_bank_id']) && $result['_blocked_bank_id'] === (int)$row['id']) {
                continue; // محظور لهذا البنك
            }
            $score = $this->levenshteinRatio($normalized, $key);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }
        if ($best && $bestScore >= $threshold) {
            $result['bank_id'] = (int)$best['id'];
            $result['final_name'] = $best['official_name'] ?? null;
        }
        return $result;
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
}
