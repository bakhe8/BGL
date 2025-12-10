<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ImportedRecordRepository;
use App\Support\Config;

class AutoAcceptService
{
    public function __construct(private ImportedRecordRepository $records)
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

        if (($best['score'] ?? 0) >= Config::MATCH_AUTO_THRESHOLD && $delta >= Config::CONFLICT_DELTA && !empty($best['supplier_id'])) {
            $this->records->updateDecision($recordId, [
                'supplier_id' => $best['supplier_id'] ?? null,
                'match_status' => 'ready',
            ]);
        }
    }
}
