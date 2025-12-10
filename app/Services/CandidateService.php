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
     * إرجاع قائمة المرشحين للاسم الخام من المصادر: official + alternative names.
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, supplier_id:int, name:string}>}
     */
    public function supplierCandidates(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeName($rawSupplier);
        $candidates = [];

        if ($normalized === '') {
            return ['normalized' => '', 'candidates' => []];
        }

        foreach ($this->suppliers->findAllByNormalized($normalized) as $supplier) {
            $candidates[] = [
                'source' => 'official',
                'supplier_id' => $supplier['id'],
                'name' => $supplier['official_name'],
            ];
        }

        foreach ($this->supplierAlts->findAllByNormalized($normalized) as $alt) {
            $candidates[] = [
                'source' => 'alternative',
                'supplier_id' => $alt['supplier_id'],
                'name' => $alt['raw_name'],
            ];
        }

        return ['normalized' => $normalized, 'candidates' => $candidates];
    }
}
