<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Support\Config;
use App\Support\Normalizer;

class MatchingService
{
    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private BankRepository $banks,
        private Normalizer $normalizer = new Normalizer(),
    ) {
    }

    /**
     * @return array{normalized:string, supplier_id?:int, match_status:string}
     */
    public function matchSupplier(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeName($rawSupplier);
        $result = [
            'normalized' => $normalized,
            'match_status' => 'needs_review',
        ];

        if ($normalized === '') {
            return $result;
        }

        // official
        $supplier = $this->suppliers->findByNormalizedName($normalized);
        if ($supplier) {
            $result['supplier_id'] = $supplier->id;
            $result['match_status'] = Config::MATCH_AUTO_THRESHOLD >= 0.9 ? 'ready' : 'needs_review';
            return $result;
        }

        // alternative names
        $alt = $this->supplierAlts->findByNormalized($normalized);
        if ($alt) {
            $result['supplier_id'] = $alt->supplierId;
            // أقل ثقة -> يظل needs_review، يمكن رفعها لاحقاً
            $result['match_status'] = Config::MATCH_AUTO_THRESHOLD >= 0.9 ? 'needs_review' : 'needs_review';
            return $result;
        }

        return $result;
    }

    /**
     * @return array{normalized:string, bank_id?:int}
     */
    public function matchBank(string $rawBank): array
    {
        $normalized = $this->normalizer->normalizeName($rawBank);
        $result = [
            'normalized' => $normalized,
        ];
        if ($normalized === '') {
            return $result;
        }

        $bank = $this->banks->findByNormalizedName($normalized);
        if ($bank) {
            $result['bank_id'] = $bank->id;
        }
        return $result;
    }
}
