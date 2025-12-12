<?php
declare(strict_types=1);

namespace App\Models;

class Bank
{
    public function __construct(
        public ?int $id,
        public string $officialName,
        public ?string $officialNameEn = null,
        public ?string $officialNameAr = null,
        public ?string $normalizedKey = null,
        public ?string $shortCode = null,
        public int $isConfirmed = 0,
        public ?string $createdAt = null,
    ) {
    }
}
