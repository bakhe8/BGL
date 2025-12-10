<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Config;

class ConflictDetector
{
    /**
     * @param array{supplier?:array, bank?:array} $candidates
     * @param array $record
     * @return string[]
     */
    public function detect(array $candidates, array $record): array
    {
        $conflicts = [];
        if (empty($record['raw_supplier_name'])) {
            $conflicts[] = 'لا يوجد اسم مورد خام';
        }
        if (empty($record['raw_bank_name'])) {
            $conflicts[] = 'لا يوجد اسم بنك خام';
        }

        // Supplier conflicts
        $supplierList = $candidates['supplier']['candidates'] ?? [];
        if (count($supplierList) > 1) {
            $diff = ($supplierList[0]['score'] ?? 0) - ($supplierList[1]['score'] ?? 0);
            if ($diff < Config::CONFLICT_DELTA) {
                $conflicts[] = 'مرشحا مورد متقاربان في الدرجة';
            }
        }
        // Official vs alternative تعارض بسيط: إذا أعلى مرشح مصدره alternative والدرجة منخفضة
        if (!empty($supplierList)) {
            $top = $supplierList[0];
            if (($top['source'] ?? '') === 'alternative' && ($top['score'] ?? 0) < Config::MATCH_AUTO_THRESHOLD) {
                $conflicts[] = 'أعلى مرشح من الأسماء البديلة وبدرجة منخفضة، يحتاج مراجعة';
            }
        }

        // Bank conflicts
        $bankList = $candidates['bank']['candidates'] ?? [];
        if (count($bankList) > 1) {
            $diff = ($bankList[0]['score'] ?? 0) - ($bankList[1]['score'] ?? 0);
            if ($diff < Config::CONFLICT_DELTA) {
                $conflicts[] = 'مرشحا بنك متقاربان في الدرجة';
            }
        }

        return $conflicts;
    }
}
