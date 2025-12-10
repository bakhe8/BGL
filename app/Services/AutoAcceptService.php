<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ImportedRecordRepository;
use App\Support\Config;
use App\Support\Settings;
use App\Repositories\LearningLogRepository;
use App\Support\Normalizer;

class AutoAcceptService
{
    public function __construct(
        private ImportedRecordRepository $records,
        private Settings $settings = new Settings(),
        private LearningLogRepository $learningLog = new LearningLogRepository(),
        private Normalizer $normalizer = new Normalizer(),
    )
    {
    }

    /**
     * يحاول اعتماد السجل تلقائيًا بناءً على أعلى مرشح وعتبة AUTO.
     */
    public function tryAutoAccept(int $recordId, array $supplierCandidates): void
    {
        if (empty($supplierCandidates)) {
            return;
        }
        $best = $supplierCandidates[0];
        $second = $supplierCandidates[1] ?? null;
        $delta = $second ? (($best['score'] ?? 0) - ($second['score'] ?? 0)) : 1;

        // اعتماد تلقائي فقط إذا المصدر رسمي أو بديل مؤكد (ليس fuzzy) وبفارق كافٍ
        $allowedSources = ['official', 'alternative'];
        $autoTh = $this->settings->get('MATCH_AUTO_THRESHOLD', Config::MATCH_AUTO_THRESHOLD);
        $confDelta = $this->settings->get('CONFLICT_DELTA', Config::CONFLICT_DELTA);
        if (
            in_array($best['source'] ?? '', $allowedSources, true) &&
            ($best['score'] ?? 0) >= $autoTh &&
            $delta >= $confDelta &&
            !empty($best['supplier_id'])
        ) {
            $this->records->updateDecision($recordId, [
                'supplier_id' => $best['supplier_id'] ?? null,
                'match_status' => 'ready',
            ]);
            // تسجيل في learning_log
            $this->learningLog->create([
                'raw_input' => $best['name'] ?? '',
                'normalized_input' => $this->normalizer->normalizeName($best['name'] ?? ''),
                'suggested_supplier_id' => $best['supplier_id'] ?? null,
                'decision_result' => 'auto',
            ]);
        }
    }
}
