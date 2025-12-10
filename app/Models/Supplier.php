<?php
declare(strict_types=1);

namespace App\Models;

class Supplier
{
    public function __construct(
        public ?int $id,
        public string $officialName,
        public ?string $displayName = null,
        public string $normalizedName = '',
        public int $isConfirmed = 0,
        public ?string $createdAt = null,
    ) {
    }
}
