<?php
declare(strict_types=1);

namespace App\Models;

class BankAlternativeName
{
    public function __construct(
        public ?int $id,
        public int $bankId,
        public string $rawName,
        public string $normalizedRawName,
        public string $source = 'manual',
        public int $occurrenceCount = 0,
        public ?string $lastSeenAt = null,
    ) {
    }
}
