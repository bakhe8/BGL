<?php
/**
 * =============================================================================
 * CandidateService - Unified Supplier & Bank Matching Facade
 * =============================================================================
 * 
 * VERSION: 6.0 (2025-12-19) - Refactored to Facade Pattern
 * 
 * 📚 DOCUMENTATION: docs/09-Supplier-System-Refactoring.md
 * 
 * PURPOSE:
 * --------
 * This service acts as a FACADE that delegates to:
 * - SupplierCandidateService for supplier matching
 * - BankCandidateService for bank matching
 * 
 * This maintains backward compatibility with existing code that uses
 * CandidateService while keeping the implementation clean and separated.
 * 
 * USAGE:
 * ------
 * $service = new CandidateService(...);
 * $suppliers = $service->supplierCandidates($rawName);
 * $banks = $service->bankCandidates($rawName);
 * 
 * @see SupplierCandidateService for supplier matching implementation
 * @see BankCandidateService for bank matching implementation
 * =============================================================================
 */
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierOverrideRepository;
use App\Repositories\BankLearningRepository;
use App\Support\Normalizer;
use App\Support\Settings;

class CandidateService
{
    private SupplierCandidateService $supplierService;
    private BankCandidateService $bankService;

    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private Normalizer $normalizer = new Normalizer(),
        private BankRepository $banks = new BankRepository(),
        private SupplierOverrideRepository $overrides = new SupplierOverrideRepository(),
        private Settings $settings = new Settings(),
        private ?BankLearningRepository $bankLearning = null,
    ) {
        // Initialize the split services
        $this->supplierService = new SupplierCandidateService(
            $this->suppliers,
            $this->supplierAlts,
            $this->normalizer,
            $this->overrides,
            $this->settings
        );
        
        $this->bankService = new BankCandidateService(
            $this->banks,
            $this->normalizer,
            $this->settings,
            $this->bankLearning
        );
    }

    /**
     * إرجاع قائمة المرشحين للاسم الخام من المصادر
     * Delegates to SupplierCandidateService
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, supplier_id:int, name:string, score:float}>}
     */
    public function supplierCandidates(string $rawSupplier): array
    {
        return $this->supplierService->supplierCandidates($rawSupplier);
    }

    /**
     * مرشحي البنوك
     * Delegates to BankCandidateService
     *
     * @return array{normalized:string, candidates: array<int, array{source:string, bank_id:int, name:string, score:float}>}
     */
    public function bankCandidates(string $rawBank): array
    {
        return $this->bankService->bankCandidates($rawBank);
    }
}
