<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierAlternativeNameRepository;
use App\Support\Normalizer;
use App\Support\Config;
use App\Support\SimilarityCalculator;

/**
 * =============================================================================
 * استخدام SimilarityCalculator في DictionaryController
 * =============================================================================
 * 
 * هذا الملف يستخدم SimilarityCalculator::fastLevenshteinRatio() لأن:
 * 
 * 1. السياق: التحقق من التكرارات في القواميس
 * 2. ضمان الطول: أسماء الموردين المطبعة عادة قصيرة (< 255 حرف)
 * 3. الأداء: التحقق السريع مطلوب عند الإضافة/التحديث
 * 4. الأمان: البيانات مُدخلة من واجهة منضبطة
 * 
 * ملاحظة: إذا أضاف المستخدم نصوص طويلة جدًا يدويًا، يجب التحويل لـ safeLevenshteinRatio
 * 
 * راجع: app/Support/SimilarityCalculator.php للتفاصيل
 * =============================================================================
 */

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
        $q = trim((string) ($_GET['q'] ?? ''));
        $dataRaw = $q !== ''
            ? $this->suppliers->search($this->normalizer->normalizeSupplierName($q))
            : $this->suppliers->allNormalized();
        $data = array_map(function ($row) {
            $alts = $this->altNames->listBySupplier((int) $row['id']);
            $row['alternatives'] = $alts;
            $row['alternatives_count'] = count($alts);
            return $row;
        }, $dataRaw);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createSupplier(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string) ($payload['official_name'] ?? ''));
        $display = null;
        $normalized = $this->normalizer->normalizeSupplierName($name);
        $key = $this->normalizer->makeSupplierKey($name);
        if ($name === '' || mb_strlen($normalized) < 5 || mb_strlen($key) < 5) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب ويجب أن يكون صالحاً بعد التطبيع']);
            return;
        }

        $similar = $this->findSimilarExisting($normalized, null);
        if ($similar) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "يوجد مورد مشابه بنسبة عالية (≥0.90): [$similar]. يرجى استخدام المورد الموجود."
            ]);
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
        $name = trim((string) ($payload['official_name'] ?? ''));
        $display = trim((string) ($payload['display_name'] ?? ''));
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

        $similar = $this->findSimilarExisting($normalized, $id);
        if ($similar) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "يوجد مورد مشابه بنسبة عالية (≥0.90): [$similar]. يرجى الدمج أو استخدام المورد الموجود."
            ]);
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

    // ---------- Banks ----------
    public function listBanks(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // Simple all list, no search needed for dropdown usually
        $data = $this->banks->allNormalized();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    public function createBank(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string) ($payload['official_name'] ?? ''));
        $nameEn = trim((string) ($payload['official_name_en'] ?? ''));
        $norm = trim((string) ($payload['normalized_key'] ?? ''));
        $short = trim((string) ($payload['short_code'] ?? ''));
        
        // New address fields
        $department = trim((string) ($payload['department'] ?? ''));
        $addressLine1 = trim((string) ($payload['address_line_1'] ?? ''));
        $addressLine2 = trim((string) ($payload['address_line_2'] ?? ''));
        $contactEmail = trim((string) ($payload['contact_email'] ?? ''));

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب']);
            return;
        }

        // Basic Auto-Normalize if empty
        if (!$norm) {
            $norm = $this->normalizer->normalizeBankName($name);
        }

        // Check duplicates? (Optional for now)
        // Note: Repository calculates ID and confirmed status
        $bank = $this->banks->create([
            'official_name' => $name,
            'official_name_en' => $nameEn,
            'normalized_key' => $norm,
            'short_code' => $short,
            'department' => $department ?: null,
            'address_line_1' => $addressLine1 ?: null,
            'address_line_2' => $addressLine2 ?: null,
            'contact_email' => $contactEmail ?: null,
            'is_confirmed' => 1,
        ]);
        echo json_encode(['success' => true, 'data' => $bank]);
    }

    public function updateBank(int $id, array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string) ($payload['official_name'] ?? ''));
        $nameEn = trim((string) ($payload['official_name_en'] ?? ''));
        $norm = trim((string) ($payload['normalized_key'] ?? ''));
        $short = trim((string) ($payload['short_code'] ?? ''));
        
        // New address fields
        $department = trim((string) ($payload['department'] ?? ''));
        $addressLine1 = trim((string) ($payload['address_line_1'] ?? ''));
        $addressLine2 = trim((string) ($payload['address_line_2'] ?? ''));
        $contactEmail = trim((string) ($payload['contact_email'] ?? ''));

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'الاسم الرسمي مطلوب']);
            return;
        }

        // Build update array dynamically to only update provided fields
        $update = [
            'official_name' => $name,
            'official_name_en' => $nameEn,
            'normalized_key' => $norm,
            'short_code' => $short,
        ];
        
        // Only include address fields if provided (allows partial updates)
        if (isset($payload['department'])) $update['department'] = $department ?: null;
        if (isset($payload['address_line_1'])) $update['address_line_1'] = $addressLine1 ?: null;
        if (isset($payload['address_line_2'])) $update['address_line_2'] = $addressLine2 ?: null;
        if (isset($payload['contact_email'])) $update['contact_email'] = $contactEmail ?: null;
        
        $this->banks->update($id, $update);
        echo json_encode(['success' => true]);
    }

    // ... (rest of the file until findSimilarExisting)

    // ---------- Delete Methods ----------

    public function deleteSupplier(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // Check if exists
        $s = $this->suppliers->find($id);
        if (!$s) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'المورد غير موجود']);
            return;
        }

        // Hard Delete (as per repository)
        $this->suppliers->delete($id);
        echo json_encode(['success' => true, 'message' => 'تم حذف المورد بنجاح']);
    }

    public function deleteBank(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
         // Check if exists
         $b = $this->banks->find($id);
         if (!$b) {
             http_response_code(404);
             echo json_encode(['success' => false, 'message' => 'البنك غير موجود']);
             return;
         }
 
         // Hard Delete
         $this->banks->delete($id);
         echo json_encode(['success' => true, 'message' => 'تم حذف البنك بنجاح']);
    }

    public function deleteAlias(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // Simple Delete
        $this->altNames->delete($id);
        echo json_encode(['success' => true, 'message' => 'تم حذف الاسم البديل']);
    }

    /**
     * التحقق من تشابه مرتفع وأرجاع الاسم المشابه
     */
    private function findSimilarExisting(string $normalized, ?int $excludeId = null): ?string
    {
        $existing = $this->suppliers->allNormalized();
        foreach ($existing as $s) {
            if ($excludeId !== null && (int) $s['id'] === $excludeId) {
                continue;
            }
            $candNorm = $this->normalizer->normalizeSupplierName($s['normalized_name'] ?? $s['official_name']);
            // استخدام النسخة السريعة - أسماء الموردين عادة قصيرة
            $score = SimilarityCalculator::fastLevenshteinRatio($normalized, $candNorm);
            if ($score >= 0.90) {
                return $s['official_name'];
            }
        }
        return null;
    }

    // ملاحظة: تم نقل دوال حساب التشابه إلى SimilarityCalculator
    // راجع: app/Support/SimilarityCalculator.php

    // ---------- Suggestions (لا تحفظ في DB) ----------
    public function suggestAlias(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $raw = trim((string) ($payload['raw'] ?? ''));
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
