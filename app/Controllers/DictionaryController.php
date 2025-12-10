<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankRepository;
use App\Repositories\SupplierRepository;
use App\Support\Normalizer;

class DictionaryController
{
    private Normalizer $normalizer;

    public function __construct(
        private SupplierRepository $suppliers = new SupplierRepository(),
        private BankRepository $banks = new BankRepository(),
    ) {
        $this->normalizer = new Normalizer();
    }

    public function listSuppliers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->suppliers->allNormalized();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createSupplier(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $display = trim((string)($payload['display_name'] ?? ''));
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب']);
            return;
        }
        $normalized = $this->normalizer->normalizeName($name);
        $supplier = $this->suppliers->create([
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
            'is_confirmed' => 1,
        ]);
        echo json_encode(['success' => true, 'data' => $supplier]);
    }

    public function listBanks(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->banks->allNormalized();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createBank(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $display = trim((string)($payload['display_name'] ?? ''));
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب']);
            return;
        }
        $normalized = $this->normalizer->normalizeName($name);
        $bank = $this->banks->create([
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
            'is_confirmed' => 1,
        ]);
        echo json_encode(['success' => true, 'data' => $bank]);
    }
}
