<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\BankRepository;
use App\Repositories\SupplierRepository;
use App\Support\Normalizer;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\BankAlternativeNameRepository;

class DictionaryController
{
    private Normalizer $normalizer;

    public function __construct(
        private SupplierRepository $suppliers = new SupplierRepository(),
        private BankRepository $banks = new BankRepository(),
        private SupplierAlternativeNameRepository $altNames = new SupplierAlternativeNameRepository(),
        private BankAlternativeNameRepository $bankAlts = new BankAlternativeNameRepository(),
    ) {
        $this->normalizer = new Normalizer();
    }

    public function listSuppliers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        $data = $q !== ''
            ? $this->suppliers->search($this->normalizer->normalizeName($q))
            : $this->suppliers->allNormalized();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createSupplier(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $display = trim((string)($payload['display_name'] ?? ''));
        $normalized = $this->normalizer->normalizeName($name);
        // منع التكرار
        if ($this->suppliers->findByNormalizedName($normalized)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'مورد بنفس الاسم المطبع موجود مسبقاً']);
            return;
        }
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب']);
            return;
        }
        $supplier = $this->suppliers->create([
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
            'is_confirmed' => 1,
        ]);
        echo json_encode(['success' => true, 'data' => $supplier]);
    }

    public function updateSupplier(int $id, array $payload): void
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
        $this->suppliers->update($id, [
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
        ]);
        echo json_encode(['success' => true]);
    }

    public function deleteSupplier(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->suppliers->delete($id);
        echo json_encode(['success' => true]);
    }

    public function listBanks(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        $data = $q !== ''
            ? $this->banks->search($this->normalizer->normalizeName($q))
            : $this->banks->allNormalized();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createBank(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $display = trim((string)($payload['display_name'] ?? ''));
        $normalized = $this->normalizer->normalizeName($name);
        if ($this->banks->findByNormalizedName($normalized)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'بنك بنفس الاسم المطبع موجود مسبقاً']);
            return;
        }
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب']);
            return;
        }
        $bank = $this->banks->create([
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
            'is_confirmed' => 1,
        ]);
        echo json_encode(['success' => true, 'data' => $bank]);
    }

    public function updateBank(int $id, array $payload): void
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
        $this->banks->update($id, [
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
        ]);
        echo json_encode(['success' => true]);
    }

    public function deleteBank(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->banks->delete($id);
        echo json_encode(['success' => true]);
    }

    // الأسماء البديلة
    public function listAlternativeNames(int $supplierId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->altNames->listBySupplier($supplierId);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createAlternativeName(int $supplierId, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $raw = trim((string)($payload['raw_name'] ?? ''));
        if ($raw === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'اسم بديل مطلوب']);
            return;
        }
        $normalized = $this->normalizer->normalizeName($raw);
        $created = $this->altNames->create($supplierId, $raw, $normalized, 'manual');
        echo json_encode(['success' => true, 'data' => $created]);
    }

    public function deleteAlternativeName(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->altNames->delete($id);
        echo json_encode(['success' => true]);
    }

    // بدائل البنوك
    public function listBankAlternativeNames(int $bankId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $data = $this->bankAlts->listByBank($bankId);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createBankAlternativeName(int $bankId, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $raw = trim((string)($payload['raw_name'] ?? ''));
        if ($raw === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'اسم بديل مطلوب']);
            return;
        }
        $normalized = $this->normalizer->normalizeName($raw);
        $created = $this->bankAlts->create($bankId, $raw, $normalized, 'manual');
        echo json_encode(['success' => true, 'data' => $created]);
    }

    public function deleteBankAlternativeName(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->bankAlts->delete($id);
        echo json_encode(['success' => true]);
    }
}
