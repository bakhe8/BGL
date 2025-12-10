<?php
declare(strict_types=1);

namespace App\Support;

class Normalizer
{
    public function normalizeName(string $value): string
    {
        $value = trim(mb_strtolower($value));
        // توحيد مسافات
        $value = preg_replace('/\s+/u', ' ', $value);
        // توحيد بعض الحروف العربية الشائعة
        $value = str_replace(
            ['أ', 'إ', 'آ', 'ة', 'ى', 'ئ', 'ؤ'],
            ['ا', 'ا', 'ا', 'ه', 'ي', 'ي', 'و'],
            $value
        );
        // إزالة رموز زائدة
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value);
        return trim($value);
    }
}
