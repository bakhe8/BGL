<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;
use App\Support\Config;

class DictionaryController
{
    private Normalizer $normalizer;

    public function __construct(
        private SupplierRepository $suppliers = new SupplierRepository(),
        private BankRepository $banks = new BankRepository(),
        private SupplierAlternativeNameRepository $altNames = new SupplierAlternativeNameRepository(),
    ) {
        $this->normalizer = new Normalizer();
    }

    // ---------- Suppliers ----------
    public function listSuppliers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        $dataRaw = $q !== ''
            ? $this->suppliers->search($this->normalizer->normalizeSupplierName($q))
            : $this->suppliers->allNormalized();
        $data = array_map(function ($row) {
            $alts = $this->altNames->listBySupplier((int)$row['id']);
            $row['alternatives'] = $alts;
            $row['alternatives_count'] = count($alts);
            return $row;
        }, $dataRaw);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createSupplier(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $display = null;
        $normalized = $this->normalizer->normalizeSupplierName($name);
        $key = $this->normalizer->makeSupplierKey($name);
        if ($name === '' || mb_strlen($normalized) < 5 || mb_strlen($key) < 5) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب ويجب أن يكون صالحاً بعد التطبيع']);
            return;
        }
        if ($this->isSimilarToExisting($normalized, null)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'يوجد مورد مشابه بنسبة عالية (≥0.90)، يرجى الربط أو تعديل الاسم.']);
            return;
        }
        if ($this->suppliers->findByNormalizedName($normalized)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'مورد بنفس الاسم المطبع موجود مسبقاً']);
            return;
        }
        $supplier = $this->suppliers->create([
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
            'supplier_normalized_key' => $key,
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
        $normalized = $this->normalizer->normalizeSupplierName($name);
        $key = $this->normalizer->makeSupplierKey($name);
        if (mb_strlen($normalized) < 5 || mb_strlen($key) < 5) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم قصير جداً بعد التطبيع']);
            return;
        }
        if ($this->isSimilarToExisting($normalized, $id)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'يوجد مورد مشابه بنسبة عالية (≥0.90)، يرجى التأكد قبل الحفظ.']);
            return;
        }
        $this->suppliers->update($id, [
            'official_name' => $name,
            'display_name' => $display ?: null,
            'normalized_name' => $normalized,
            'supplier_normalized_key' => $key,
        ]);
        echo json_encode(['success' => true]);
    }

    public function deleteSupplier(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->suppliers->delete($id);
        echo json_encode(['success' => true]);
    }

    // ---------- Banks ----------
    public function listBanks(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string)($_GET['q'] ?? ''));
        $dataRaw = $q !== ''
            ? $this->banks->search($this->normalizer->normalizeName($q))
            : $this->banks->allNormalized();
        echo json_encode(['success' => true, 'data' => $dataRaw]);
    }

    public function createBank(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $nameEn = trim((string)($payload['official_name_en'] ?? ''));
        $short = trim((string)($payload['short_code'] ?? ''));
        $normalizedKey = trim((string)($payload['normalized_key'] ?? ''));
        $normalized = $this->normalizer->normalizeBankName($name);
        if ($name === '' || mb_strlen($normalized) < 2) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم العربي الرسمي للبنك مطلوب ولا يمكن تركه فارغاً.']);
            return;
        }
        if ($this->banks->findByNormalizedKey($normalized)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'بنك بنفس الاسم المطبع موجود مسبقاً']);
            return;
        }
        $bank = $this->banks->create([
            'official_name' => $name,
            'official_name_en' => $nameEn ?: null,
            'normalized_key' => $normalizedKey !== '' ? $normalizedKey : $this->normalizer->normalizeBankName($name),
            'short_code' => $short ?: null,
            'is_confirmed' => 1,
        ]);
        echo json_encode(['success' => true, 'data' => $bank]);
    }

    public function updateBank(int $id, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string)($payload['official_name'] ?? ''));
        $nameEn = trim((string)($payload['official_name_en'] ?? ''));
        $short = trim((string)($payload['short_code'] ?? ''));
        $normalizedKey = trim((string)($payload['normalized_key'] ?? ''));
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم العربي الرسمي للبنك مطلوب ولا يمكن تركه فارغاً.']);
            return;
        }
        $normalized = $this->normalizer->normalizeBankName($name);
        if (mb_strlen($normalized) < 2) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم قصير جداً بعد التطبيع']);
            return;
        }
        $this->banks->update($id, [
            'official_name' => $name,
            'official_name_en' => $nameEn ?: null,
            'normalized_key' => $normalizedKey !== '' ? $normalizedKey : $this->normalizer->normalizeBankName($name),
            'short_code' => $short ?: null,
        ]);
        echo json_encode(['success' => true]);
    }

    public function deleteBank(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->banks->delete($id);
        echo json_encode(['success' => true]);
    }

    // ---------- Supplier Alternatives ----------
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
        $normalized = $this->normalizer->normalizeSupplierName($raw);
        if (mb_strlen($normalized) < 5) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم قصير جداً بعد التطبيع']);
            return;
        }
        // منع alias = official
        $supplier = $this->suppliers->find($supplierId);
        if ($supplier && $this->normalizer->normalizeSupplierName($supplier->officialName ?? '') === $normalized) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'الاسم البديل يطابق الاسم الرسمي']);
            return;
        }
        if ($this->altNames->findByNormalized($normalized)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'الاسم البديل موجود مسبقاً']);
            return;
        }
        $created = $this->altNames->create($supplierId, $raw, $normalized, 'manual');
        echo json_encode(['success' => true, 'data' => $created]);
    }

    public function deleteAlternativeName(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->altNames->delete($id);
        echo json_encode(['success' => true]);
    }

    /**
     * التحقق من تشابه مرتفع مع مورد موجود (لمنع/تحذير التكرار).
     */
    private function isSimilarToExisting(string $normalized, ?int $excludeId = null): bool
    {
        $existing = $this->suppliers->allNormalized();
        foreach ($existing as $s) {
            if ($excludeId !== null && (int)$s['id'] === $excludeId) {
                continue;
            }
            $candNorm = $this->normalizer->normalizeSupplierName($s['normalized_name'] ?? $s['official_name']);
            $score = $this->levenshteinRatio($normalized, $candNorm);
            if ($score >= 0.90) {
                return true;
            }
        }
        return false;
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

    // ---------- Suggestions (لا تحفظ في DB) ----------
    public function suggestAlias(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $raw = trim((string)($payload['raw'] ?? ''));
        if ($raw === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'النص فارغ']);
            return;
        }
        $normalized = $this->normalizer->normalizeSupplierName($raw);
        echo json_encode([
            'success' => true,
            'data' => [
                'raw' => $raw,
                'normalized' => $normalized,
            ],
        ]);
    }
}
