<?php
declare(strict_types=1);

namespace App\Models;

class ImportedRecord
{
    public function __construct(
        public ?int $id,
        public int $sessionId,
        public string $rawSupplierName,
        public string $rawBankName,
        public ?string $amount = null,
        public ?string $guaranteeNumber = null,
        public ?string $issueDate = null,
        public ?string $expiryDate = null,
        public ?string $normalizedSupplier = null,
        public ?string $normalizedBank = null,
        public ?string $matchStatus = null,
        public ?int $supplierId = null,
        public ?int $bankId = null,
        public ?string $createdAt = null,
    ) {
    }
}
