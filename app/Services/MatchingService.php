<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierAlternativeNameRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\BankRepository;
use App\Repositories\SupplierOverrideRepository;
use App\Repositories\SupplierSuggestionRepository;
use App\Support\Config;
use App\Support\Normalizer;
use App\Support\Settings;
use App\Support\SimilarityCalculator;
use App\Services\CandidateService;

/**
 * =============================================================================
 * MatchingService - خدمة المطابقة أثناء الاستيراد
 * =============================================================================
 * 
 * استخدام SimilarityCalculator في هذا الملف:
 * ----------------------------------------
 * يستخدم هذا الملف SimilarityCalculator::fastLevenshteinRatio() لأن:
 * 
 * 1. السياق: الاستيراد (Import) - النصوص تأتي من Excel
 * 2. ضمان الطول: ملفات Excel محدودة الطول (< 255 حرف لكل خلية)
 * 3. الأداء: عمليات كثيرة أثناء الاستيراد تحتاج سرعة
 * 4. الأمان: النصوص مضمونة ومُتحقق منها قبل الوصول لهنا
 * 
 * ⚠️ ملاحظة هامة:
 * لا تستخدم fastLevenshteinRatio() في:
 * - الواجهات الأمامية (استخدم safeLevenshteinRatio)
 * - البحث اليدوي من المستخدم
 * - أي مكان قد يدخل فيه المستخدم نصوص طويلة
 * 
 * راجع: app/Support/SimilarityCalculator.php للتفاصيل
 * =============================================================================
 */

class MatchingService
{
    private ?array $cachedSuppliers = null;
    private ?array $cachedBanks = null;

    public function __construct(
        private SupplierRepository $suppliers,
        private SupplierAlternativeNameRepository $supplierAlts,
        private BankRepository $banks,
        private Normalizer $normalizer = new Normalizer(),
        private SupplierOverrideRepository $overrides = new SupplierOverrideRepository(),
        private Settings $settings = new Settings(),
        private ?CandidateService $candidates = null,
        private ?\App\Repositories\BankLearningRepository $bankLearning = null,
    ) {
        $this->candidates = $this->candidates ?: new CandidateService(
            new SupplierRepository(),
            new SupplierAlternativeNameRepository(),
            new Normalizer(),
            $this->banks,
            new SupplierOverrideRepository(),
            $this->settings
        );
        $this->bankLearning = $this->bankLearning ?: new \App\Repositories\BankLearningRepository();
    }

    /**
     * @return array{normalized:string, supplier_id?:int, match_status:string}
     */
    public function matchSupplier(string $rawSupplier): array
    {
        $normalized = $this->normalizer->normalizeSupplierName($rawSupplier);
        $autoTh = $this->settings->get('MATCH_AUTO_THRESHOLD', Config::MATCH_AUTO_THRESHOLD);
        $result = [
            'normalized' => $normalized,
            'match_status' => 'needs_review',
        ];

        if ($normalized === '') {
            return $result;
        }

        // ═══════════════════════════════════════════════════════════════════
        // CACHE-FIRST APPROACH (Updated 2025-12-17)
        // Check supplier_suggestions cache for learning/blocking info
        // ═══════════════════════════════════════════════════════════════════
        $suggestionRepo = new SupplierSuggestionRepository();
        $suggestions = $suggestionRepo->getSuggestions($normalized, 1);
        $blockedIds = $suggestionRepo->getBlockedSupplierIds($normalized);
        
        // If we have a high-score suggestion, use it
        if (!empty($suggestions)) {
            $top = $suggestions[0];
            if (($top['effective_score'] ?? $top['total_score']) >= 180) {
                $result['supplier_id'] = (int) $top['supplier_id'];
                $result['match_status'] = $autoTh >= 0.9 ? 'ready' : 'needs_review';
                return $result;
            }
        }
        
        // Store blocked IDs for later filtering
        if (!empty($blockedIds)) {
            $result['_blocked_supplier_ids'] = $blockedIds;
        }

        // Overrides أولاً
        foreach ($this->overrides->allNormalized() as $ov) {
            $ovNorm = $this->normalizer->normalizeSupplierName($ov['override_name']);
            if ($ovNorm === $normalized) {
                if (!in_array((int) $ov['supplier_id'], $blockedIds, true)) {
                    $result['supplier_id'] = $ov['supplier_id'];
                    $result['match_status'] = 'ready';
                    return $result;
                }
            }
        }

        // Load Cache if needed
        if ($this->cachedSuppliers === null) {
            $this->cachedSuppliers = $this->suppliers->allNormalized();
        }

        // official by normalized_key (بدون مسافات)
        $supplierKey = null;
        $normKey = $this->normalizer->makeSupplierKey($rawSupplier);
        foreach ($this->cachedSuppliers as $s) {
            if (($s['supplier_normalized_key'] ?? '') === $normKey) {
                $supplierKey = $s;
                break;
            }
        }

        if ($supplierKey && !in_array((int) $supplierKey['id'], $blockedIds, true)) {
            $result['supplier_id'] = (int) $supplierKey['id'];
            $result['match_status'] = $autoTh >= 0.9 ? 'ready' : 'needs_review';
            return $result;
        }

        // exact normalized match
        $supplierExact = null;
        foreach ($this->cachedSuppliers as $s) {
            if ($s['normalized_name'] === $normalized) {
                $supplierExact = $s;
                break;
            }
        }

        if ($supplierExact) {
            if (!in_array((int) $supplierExact['id'], $blockedIds, true)) {
                $result['supplier_id'] = (int) $supplierExact['id'];
                $result['match_status'] = $autoTh >= 0.9 ? 'ready' : 'needs_review';
                return $result;
            }
        }

        // alternative names
        // Note: Keeping alts separate for now to avoid huge memory spike if aliases > official.
        // Can be optimized later if needed.
        $alt = $this->supplierAlts->findByNormalized($normalized);
        if ($alt) {
            if (!in_array($alt->supplierId, $blockedIds, true)) {
                $result['supplier_id'] = $alt->supplierId;
                $result['match_status'] = 'needs_review';
                return $result;
            }
        }

        // Fuzzy قوي فقط
        $best = null;
        $bestScore = 0.0;
        foreach ($this->cachedSuppliers as $row) {
            $candNorm = $this->normalizer->normalizeSupplierName($row['normalized_name'] ?? $row['official_name']);
            if ($candNorm === '') {
                continue;
            }
            if (in_array((int) $row['id'], $blockedIds, true)) {
                continue;
            }
            // استخدام النسخة السريعة - آمن لأن النصوص من Excel (< 255 بايت)
            $score = SimilarityCalculator::fastLevenshteinRatio($normalized, $candNorm);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }
        if ($best && $bestScore >= 0.9) {
            $result['supplier_id'] = (int) $best['id'];
            $result['match_status'] = 'needs_review'; // fuzzy → مراجعة
        }

        return $result;
    }

    /**
     * @return array{normalized:string, bank_id?:int, final_name?:string}
     */
    public function matchBank(string $rawBank): array
    {
        $normalized = $this->normalizer->normalizeBankName($rawBank);
        $short = $this->normalizer->normalizeBankShortCode($rawBank);
        $result = [
            'normalized' => $normalized,
        ];

        // Step 0: التعلم (alias/blocked) آخر قرار فقط
        $learned = $this->bankLearning?->findByNormalized($normalized);
        if ($learned) {
            if ($learned['status'] === 'alias' && !empty($learned['bank_id'])) {
                $result['bank_id'] = (int) $learned['bank_id'];
                return $result;
            }
            if ($learned['status'] === 'blocked') {
                if (!empty($learned['bank_id'])) {
                    $result['_blocked_bank_id'] = (int) $learned['bank_id'];
                } else {
                    // محظور بشكل عام لهذا الاسم → لا تطابق
                    return $result;
                }
            }
        }

        // Load Cache
        if ($this->cachedBanks === null) {
            $this->cachedBanks = $this->banks->allNormalized();
        }

        // Step 1: short code exact
        if ($short !== '') {
            foreach ($this->cachedBanks as $row) {
                $sc = strtoupper(trim((string) ($row['short_code'] ?? '')));
                if ($sc !== '' && $sc === $short) {
                    if (isset($result['_blocked_bank_id']) && $result['_blocked_bank_id'] === (int) $row['id']) {
                        continue;
                    }
                    $result['bank_id'] = (int) $row['id'];
                    $result['final_name'] = $row['official_name'] ?? null;
                    return $result;
                }
            }
        }

        // Step 2: short code fuzzy (>=0.9)
        if ($short !== '') {
            $best = null;
            $bestScore = 0.0;
            $threshold = 0.9;
            foreach ($this->cachedBanks as $row) {
                $sc = strtoupper(trim((string) ($row['short_code'] ?? '')));
                if ($sc === '') {
                    continue;
                }
                if (isset($result['_blocked_bank_id']) && $result['_blocked_bank_id'] === (int) $row['id']) {
                    continue;
                }
                // استخدام النسخة السريعة - الرموز المختصرة دائماً قصيرة
                $score = SimilarityCalculator::fastLevenshteinRatio($short, $sc);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $row;
                }
            }
            if ($best && $bestScore >= $threshold) {
                $result['bank_id'] = (int) $best['id'];
                $result['final_name'] = $best['official_name'] ?? null;
                return $result;
            }
        }

        if ($normalized === '') {
            return $result;
        }

        // Step 3: full name exact via normalized_key
        $bankKey = null;
        foreach ($this->cachedBanks as $b) {
            if (($b['normalized_key'] ?? '') === $normalized) {
                $bankKey = $b;
                break;
            }
        }

        if ($bankKey) {
            if (!isset($result['_blocked_bank_id']) || $result['_blocked_bank_id'] !== (int) $bankKey['id']) {
                $result['bank_id'] = (int) $bankKey['id'];
                $result['final_name'] = $bankKey['officialNameAr'] ?? $bankKey['officialName'] ?? null;
                return $result;
            }
        }

        // Step 4: full name fuzzy on normalized_key (>=0.95)
        $best = null;
        $bestScore = 0.0;
        $threshold = 0.95; // تطابق قوي فقط
        foreach ($this->cachedBanks as $row) {
            $key = $row['normalized_key'] ?? '';
            if ($key === '') {
                continue;
            }
            if (isset($result['_blocked_bank_id']) && $result['_blocked_bank_id'] === (int) $row['id']) {
                continue; // محظور لهذا البنك
            }
            // استخدام النسخة السريعة - normalized_key مضمون القصر
            $score = SimilarityCalculator::fastLevenshteinRatio($normalized, $key);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row;
            }
        }
        if ($best && $bestScore >= $threshold) {
            $result['bank_id'] = (int) $best['id'];
            $result['final_name'] = $best['official_name'] ?? null;
        }
        return $result;
    }

    // ملاحظة: تم نقل دوال حساب التشابه إلى SimilarityCalculator
    // راجع: app/Support/SimilarityCalculator.php
}
