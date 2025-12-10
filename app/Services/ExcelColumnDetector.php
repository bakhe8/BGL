<?php
declare(strict_types=1);

namespace App\Services;

class ExcelColumnDetector
{
    /**
     * يحاول اكتشاف الأعمدة من الصف الأول بناءً على قائمة كلمات مفتاحية بالعربي والإنجليزي.
     *
     * @param string[] $headers
     * @return array<string,int> مفاتيح محتملة: supplier, bank, amount, guarantee, type, expiry, po
     */
    public function detect(array $headers): array
    {
        $keywords = [
            'supplier' => [
                'supplier', 'vendor', 'supplier name', 'vendor name', 'party name',
                'المورد', 'اسم المورد', 'اسم الموردين', 'الشركة', 'اسم الشركة', 'مقدم الخدمة',
            ],
            'bank' => [
                'bank', 'bank name', 'issuing bank', 'beneficiary bank',
                'البنك', 'اسم البنك', 'البنك المصدر', 'بنك الاصدار', 'بنك الإصدار',
            ],
            'guarantee' => [
                'guarantee no', 'guarantee number', 'reference', 'ref no',
                'رقم الضمان', 'رقم المرجع', 'مرجع الضمان',
            ],
            'type' => [
                'guarantee type', 'type', 'category',
                'نوع الضمان', 'فئة الضمان',
            ],
            'amount' => [
                'amount', 'value', 'total amount', 'guarantee amount',
                'المبلغ', 'قيمة الضمان', 'قيمة', 'مبلغ الضمان',
            ],
            'expiry' => [
                'expiry date', 'exp date', 'validity', 'valid until', 'end date',
                'تاريخ الانتهاء', 'صلاحية', 'تاريخ الصلاحية', 'ينتهي في',
            ],
            'po' => [
                'po number', 'po#', 'purchase order', 'order no',
                'رقم الطلب', 'رقم أمر الشراء', 'رقم po', 'رقم po.',
            ],
        ];

        $map = [];
        foreach ($headers as $idx => $header) {
            $h = $this->normalize($header);
            foreach ($keywords as $field => $syns) {
                foreach ($syns as $syn) {
                    if (str_contains($h, $this->normalize($syn))) {
                        $map[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    private function normalize(string $str): string
    {
        $str = trim(mb_strtolower($str));
        $str = preg_replace('/\s+/u', ' ', $str);
        return $str ?? '';
    }
}
